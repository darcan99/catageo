<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Csv.php
 *  Descrizione ..: Lettura e scrittura dei file CSV: indici interni, serie di
 *                  misure, log ed esportazioni. La lettura e in streaming
 *                  (una riga per volta) perche gli indici e le serie da
 *                  datalogger possono contare decine di migliaia di righe.
 *  Versione .....: 0.6.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.6.0  2026-08-05  D.Candela  BOM saltato prima di fgetcsv: un file salvato
 *                                da Excel in "CSV UTF-8" ha BOM e intestazione
 *                                fra apici, e la prima colonna non si leggeva.
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Csv
{
    /** Separatore di campo, uniforme in tutto l'archivio. */
    public const SEPARATORE = ';';

    /** Delimitatore di stringa. */
    public const DELIMITATORE = '"';

    /** Byte Order Mark UTF-8. */
    private const BOM = "\xEF\xBB\xBF";

    /**
     * Legge un CSV riga per riga, passando ogni riga alla callback come array
     * associativo intestazione => valore.
     *
     * Il BOM iniziale, se presente, viene scartato: altrimenti il nome della
     * prima colonna risulterebbe sporco e i confronti fallirebbero.
     *
     * La callback puo restituire false per interrompere la scansione: utile per
     * le ricerche che si fermano al primo risultato utile.
     *
     * @param  callable(array<string,string>,int):(bool|null) $perRiga
     * @return int numero di righe di dati esaminate
     */
    public static function leggi(string $percorso, callable $perRiga): int
    {
        if (!is_file($percorso)) {
            return 0;
        }

        $handle = @fopen($percorso, 'r');
        if ($handle === false) {
            throw new CsvEccezione("CSV non apribile in lettura: {$percorso}");
        }

        $conteggio = 0;

        try {
            // Lock condiviso: una scrittura concorrente non deve far leggere
            // una riga a meta.
            @flock($handle, LOCK_SH);

            self::saltaBom($handle);

            $intestazione = fgetcsv($handle, 0, self::SEPARATORE, self::DELIMITATORE);
            if ($intestazione === false || $intestazione === [null]) {
                return 0;
            }
            if (isset($intestazione[0]) && is_string($intestazione[0])) {
                $intestazione[0] = self::togliBom($intestazione[0]);
            }
            $intestazione = array_map(static fn ($v) => trim((string) $v), $intestazione);

            while (($riga = fgetcsv($handle, 0, self::SEPARATORE, self::DELIMITATORE)) !== false) {
                if ($riga === [null]) {
                    continue; // riga vuota
                }
                $conteggio++;

                // Allinea la riga all'intestazione: i file scritti a mano
                // possono avere colonne in meno o in piu.
                $valori = [];
                foreach ($intestazione as $indice => $nome) {
                    $valori[$nome] = isset($riga[$indice]) ? (string) $riga[$indice] : '';
                }

                if ($perRiga($valori, $conteggio) === false) {
                    break;
                }
            }
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $conteggio;
    }

    /**
     * Legge l'intestazione di un CSV senza scorrerne il contenuto.
     *
     * @return string[]
     */
    public static function intestazione(string $percorso): array
    {
        if (!is_file($percorso)) {
            return [];
        }
        $handle = @fopen($percorso, 'r');
        if ($handle === false) {
            return [];
        }
        try {
            self::saltaBom($handle);

            $riga = fgetcsv($handle, 0, self::SEPARATORE, self::DELIMITATORE);
            if ($riga === false || $riga === [null]) {
                return [];
            }
            if (isset($riga[0]) && is_string($riga[0])) {
                $riga[0] = self::togliBom($riga[0]);
            }
            return array_map(static fn ($v) => trim((string) $v), $riga);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Riscrive integralmente un CSV in modo atomico.
     *
     * @param string[]                        $intestazione
     * @param iterable<array<string,mixed>>   $righe
     * @param bool                            $bom  true per i file destinati
     *                                              all'apertura in Excel
     *                                              (serie di misure, export);
     *                                              false per gli indici interni
     */
    public static function scrivi(string $percorso, array $intestazione, iterable $righe, bool $bom = false): void
    {
        $cartella = dirname($percorso);
        if (!is_dir($cartella) && !@mkdir($cartella, 0775, true) && !is_dir($cartella)) {
            throw new CsvEccezione("Impossibile creare la cartella: {$cartella}");
        }

        $temporaneo = $percorso . '.' . getmypid() . '.tmp';
        $handle     = @fopen($temporaneo, 'w');
        if ($handle === false) {
            throw new CsvEccezione("CSV temporaneo non apribile: {$temporaneo}");
        }

        try {
            if ($bom) {
                fwrite($handle, self::BOM);
            }
            fputcsv($handle, $intestazione, self::SEPARATORE, self::DELIMITATORE);
            foreach ($righe as $riga) {
                fputcsv($handle, self::allinea($riga, $intestazione), self::SEPARATORE, self::DELIMITATORE);
            }
        } finally {
            fclose($handle);
        }

        if (!@rename($temporaneo, $percorso)) {
            @unlink($percorso);
            if (!@rename($temporaneo, $percorso)) {
                @unlink($temporaneo);
                throw new CsvEccezione("Sostituzione atomica fallita per {$percorso}");
            }
        }
    }

    /**
     * Accoda una riga, creando il file con l'intestazione se non esiste.
     *
     * L'append e la ragione per cui le serie di misure stanno in CSV e non in
     * XML: aggiungere una lettura non richiede di rileggere e riscrivere
     * l'intero file.
     *
     * @param string[]             $intestazione
     * @param array<string,mixed>  $riga
     */
    public static function accoda(string $percorso, array $intestazione, array $riga, bool $bom = true): void
    {
        $cartella = dirname($percorso);
        if (!is_dir($cartella) && !@mkdir($cartella, 0775, true) && !is_dir($cartella)) {
            throw new CsvEccezione("Impossibile creare la cartella: {$cartella}");
        }

        $nuovo  = !is_file($percorso) || filesize($percorso) === 0;
        $handle = @fopen($percorso, 'a');
        if ($handle === false) {
            throw new CsvEccezione("CSV non apribile in append: {$percorso}");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new CsvEccezione("Lock esclusivo non ottenuto su {$percorso}");
            }
            if ($nuovo) {
                if ($bom) {
                    fwrite($handle, self::BOM);
                }
                fputcsv($handle, $intestazione, self::SEPARATORE, self::DELIMITATORE);
            }
            fputcsv($handle, self::allinea($riga, $intestazione), self::SEPARATORE, self::DELIMITATORE);
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Conta le righe di dati di un CSV senza costruire array in memoria.
     */
    public static function conta(string $percorso): int
    {
        if (!is_file($percorso)) {
            return 0;
        }
        $handle = @fopen($percorso, 'r');
        if ($handle === false) {
            return 0;
        }
        $righe = -1; // l'intestazione non e un dato
        try {
            while (fgets($handle) !== false) {
                $righe++;
            }
        } finally {
            fclose($handle);
        }
        return max(0, $righe);
    }

    /**
     * Riordina i valori di una riga secondo l'intestazione, riempiendo di
     * stringa vuota le colonne assenti.
     *
     * @param  array<string,mixed> $riga
     * @param  string[]            $intestazione
     * @return string[]
     */
    private static function allinea(array $riga, array $intestazione): array
    {
        $ordinata = [];
        foreach ($intestazione as $colonna) {
            $valore     = $riga[$colonna] ?? '';
            $ordinata[] = self::normalizza($valore);
        }
        return $ordinata;
    }

    /**
     * Rende sicuro un valore da scrivere in CSV: i ritorni a capo diventano
     * spazi, perche una riga di indice deve restare su una riga fisica.
     * I testi lunghi non passano da qui, restano negli XML (D6).
     */
    public static function normalizza(mixed $valore): string
    {
        if ($valore === null || $valore === false) {
            return '';
        }
        if ($valore === true) {
            return '1';
        }
        $testo = (string) $valore;
        $testo = str_replace(["\r\n", "\r", "\n"], ' ', $testo);
        return trim($testo);
    }

    /**
     * Posiziona il puntatore dopo il BOM, se il file ne ha uno.
     *
     * Va fatto PRIMA di fgetcsv, non dopo: se il BOM precede l'apice di
     * apertura, fgetcsv non riconosce il primo campo come delimitato e
     * l'intestazione diventa "catalogo" con gli apici dentro il nome. E
     * precisamente cio che produce Excel salvando in "CSV UTF-8", cioe il modo
     * piu probabile in cui un file dell'archivio verra riscritto a mano.
     *
     * @param resource $handle
     */
    private static function saltaBom($handle): void
    {
        $inizio = fread($handle, strlen(self::BOM));
        if ($inizio !== self::BOM) {
            rewind($handle);
        }
    }

    /** Rimuove il BOM UTF-8 iniziale, se presente. */
    private static function togliBom(string $valore): string
    {
        return str_starts_with($valore, self::BOM) ? substr($valore, strlen(self::BOM)) : $valore;
    }
}
