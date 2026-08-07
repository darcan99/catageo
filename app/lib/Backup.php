<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Backup.php
 *  Descrizione ..: Backup ZIP dell'archivio, per intero o per singolo
 *                  catalogo (9.14).
 *
 *                  Il backup si scrive su file e non si emette direttamente al
 *                  browser: un archivio di qualche gigabyte prodotto in
 *                  streaming, su un hosting con un limite di tempo di
 *                  esecuzione, si interromperebbe a meta lasciando all'utente
 *                  uno ZIP corrotto che sembra buono. Scritto su disco, se il
 *                  tempo finisce il file resta incompleto ma se ne accorge chi
 *                  guarda l'elenco, non chi tenta di ripristinarlo.
 *
 *                  Dentro lo ZIP finisce anche un file di manifesto: chi lo
 *                  apre fra cinque anni deve poter sapere da quale versione
 *                  proviene e cosa contiene, senza doverlo dedurre.
 *  Versione .....: 0.15.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.15.0  2026-08-06  D.Candela  Prima stesura (fase 9).
 * ============================================================================
 */

final class Backup
{
    /** Sottocartella dell'archivio dove finiscono i backup. */
    public const CARTELLA = '_backup';

    /** Nome del manifesto dentro lo ZIP. */
    public const MANIFESTO = 'CATAGEO-backup.txt';

    /**
     * Cartelle e file che non entrano mai in un backup.
     *
     * I backup stessi per primi: senza questa esclusione ogni backup
     * conterrebbe tutti i precedenti, e il secondo peserebbe il doppio del
     * primo. I temporanei e i lock non servono a un ripristino.
     */
    public const ESCLUSI = [self::CARTELLA, '_tmp'];

    public static function cartella(): string
    {
        return Percorsi::dati(self::CARTELLA);
    }

    /**
     * Crea un backup e ne restituisce il percorso.
     *
     * @param  string $siglaCatalogo vuoto per l'intero archivio
     * @throws BackupEccezione
     */
    public static function crea(string $siglaCatalogo = ''): string
    {
        if (!class_exists('ZipArchive')) {
            throw new BackupEccezione(
                'L\'estensione zip di PHP non è disponibile: il backup ZIP non si può creare. '
                . 'Copiare a mano la cartella dell\'archivio.');
        }

        $siglaCatalogo = trim($siglaCatalogo);
        $sorgenti = [];

        if ($siglaCatalogo === '') {
            // Tutto l'archivio: si parte dalla radice dei dati e si escludono
            // le cartelle di servizio scorrendo.
            $sorgenti[''] = Percorsi::dati();
            $etichetta = 'archivio completo';
            $nome = 'catageo-archivio';
        } else {
            $catalogo = Cataloghi::trova($siglaCatalogo);
            if ($catalogo === null) {
                throw new BackupEccezione('Catalogo non trovato: ' . $siglaCatalogo);
            }
            $cartella = Percorsi::cataloghi((string) $catalogo['cartella']);
            if (!is_dir($cartella)) {
                throw new BackupEccezione('La cartella del catalogo non esiste.');
            }

            /*
             * Il backup di un solo catalogo comprende ANCHE le anagrafiche e
             * gli indici: un catalogo ripristinato senza i gruppi, gli
             * esploratori e i vocabolari che le sue schede citano sarebbe pieno
             * di riferimenti a identificativi che non esistono piu.
             */
            $sorgenti['cataloghi/' . basename($cartella)] = $cartella;
            foreach (glob(Percorsi::dati('*.xml')) ?: [] as $anagrafica) {
                $sorgenti[basename($anagrafica)] = $anagrafica;
            }
            $indice = Percorsi::indice();
            if (is_dir($indice)) {
                $sorgenti['_indice'] = $indice;
            }

            $etichetta = 'catalogo ' . (string) $catalogo['sigla'] . ' con anagrafiche e indici';
            $nome = 'catageo-' . Testo::nomeFileSicuro((string) $catalogo['sigla'], true);
        }

        $deposito = Percorsi::assicuraCartella(self::cartella());
        Percorsi::proteggiCartella($deposito);

        /*
         * Il nome porta data e ora al secondo, ma due backup creati nello
         * stesso secondo avrebbero lo stesso nome, e ZipArchive::OVERWRITE
         * sostituirebbe il primo senza dire nulla: si perderebbe un backup
         * proprio mentre si crede di averne fatti due. Se il nome e occupato
         * si aggiunge un contatore.
         */
        $base = $nome . '-' . date('Ymd-His');
        $percorso = Percorsi::unisci($deposito, $base . '.zip');
        for ($n = 2; is_file($percorso) && $n < 100; $n++) {
            $percorso = Percorsi::unisci($deposito, $base . '-' . $n . '.zip');
        }

        $zip = new ZipArchive();
        if ($zip->open($percorso, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupEccezione('Impossibile creare il file ZIP: ' . $percorso);
        }

        $conteggio = 0;
        $byte = 0;

        foreach ($sorgenti as $prefisso => $sorgente) {
            if (is_file($sorgente)) {
                $zip->addFile($sorgente, $prefisso === '' ? basename($sorgente) : $prefisso);
                $conteggio++;
                $byte += (int) filesize($sorgente);
                continue;
            }
            self::aggiungiCartella($zip, $sorgente, $prefisso, $conteggio, $byte);
        }

        $zip->addFromString(self::MANIFESTO, self::manifesto($etichetta, $conteggio, $byte));

        if (!$zip->close()) {
            throw new BackupEccezione(
                'Chiusura dello ZIP non riuscita: il backup potrebbe essere incompleto. '
                . 'Verificare lo spazio disponibile.');
        }

        Log::modifica('backup', $siglaCatalogo, '', 'strumenti',
            basename($percorso) . ' (' . $conteggio . ' file, ' . Testo::dimensione($byte) . ')');

        return $percorso;
    }

    /**
     * Backup presenti, dal piu recente.
     *
     * @return array<int,array{nome:string,percorso:string,dimensione:int,data:int}>
     */
    public static function elenco(): array
    {
        $cartella = self::cartella();
        if (!is_dir($cartella)) {
            return [];
        }

        $voci = [];
        foreach (scandir($cartella) ?: [] as $file) {
            if (!str_ends_with(strtolower($file), '.zip')) {
                continue;
            }
            $percorso = Percorsi::unisci($cartella, $file);
            $voci[] = [
                'nome'       => $file,
                'percorso'   => $percorso,
                'dimensione' => (int) filesize($percorso),
                'data'       => (int) filemtime($percorso),
            ];
        }

        usort($voci, static fn (array $a, array $b): int => $b['data'] <=> $a['data']);

        return $voci;
    }

    /**
     * Toglie un backup.
     *
     * Qui la cancellazione e definitiva, al contrario del resto
     * dell'applicativo: un backup e gia una copia, e conservare le copie delle
     * copie riempirebbe il disco proprio mentre si cerca di liberarlo.
     *
     * @throws BackupEccezione
     */
    public static function elimina(string $nome): void
    {
        $nome = basename(trim($nome));
        if ($nome === '' || !str_ends_with(strtolower($nome), '.zip')) {
            throw new BackupEccezione('Nome di backup non valido.');
        }

        $percorso = Percorsi::unisci(self::cartella(), $nome);
        if (!is_file($percorso)) {
            throw new BackupEccezione('Backup non trovato: ' . $nome);
        }
        if (!Percorsi::dentro(self::cartella(), $percorso)) {
            throw new BackupEccezione('Percorso fuori dalla cartella dei backup.');
        }

        if (!@unlink($percorso)) {
            throw new BackupEccezione('Rimozione non riuscita: ' . $nome);
        }

        Log::modifica('backup_rimosso', '', '', 'strumenti', $nome);
    }

    /** Spazio occupato complessivamente dai backup. */
    public static function spazioOccupato(): int
    {
        $totale = 0;
        foreach (self::elenco() as $voce) {
            $totale += $voce['dimensione'];
        }

        return $totale;
    }

    // ========================================================================
    //  INTERNI
    // ========================================================================

    /**
     * Aggiunge ricorsivamente una cartella allo ZIP.
     *
     * Le cartelle vuote si aggiungono esplicitamente: senza, un archivio
     * ripristinato perderebbe le sottocartelle di sezione ancora senza
     * contenuti, e l'ipogeo sembrerebbe incompleto.
     */
    private static function aggiungiCartella(
        ZipArchive $zip, string $cartella, string $prefisso, int &$conteggio, int &$byte
    ): void {
        $voci = scandir($cartella) ?: [];
        $vuota = true;

        foreach ($voci as $voce) {
            if ($voce === '.' || $voce === '..') {
                continue;
            }
            if ($prefisso === '' && in_array($voce, self::ESCLUSI, true)) {
                continue;
            }
            if (str_ends_with($voce, '.lock') || str_ends_with($voce, '.tmp')) {
                continue;
            }

            $vuota = false;
            $percorso = Percorsi::unisci($cartella, $voce);
            $interno  = $prefisso === '' ? $voce : $prefisso . '/' . $voce;

            if (is_dir($percorso)) {
                self::aggiungiCartella($zip, $percorso, $interno, $conteggio, $byte);
                continue;
            }

            if ($zip->addFile($percorso, $interno)) {
                $conteggio++;
                $byte += (int) filesize($percorso);
            }
        }

        if ($vuota && $prefisso !== '') {
            $zip->addEmptyDir($prefisso);
        }
    }

    private static function manifesto(string $contenuto, int $file, int $byte): string
    {
        $righe = [
            'CATAGEO — backup dell\'archivio',
            str_repeat('=', 60),
            '',
            'Contenuto ....: ' . $contenuto,
            'Creato il ....: ' . date('Y-m-d H:i:s'),
            'Da ...........: ' . (Auth::usernameCorrente() ?: '(riga di comando)'),
            'Versione .....: CATAGEO ' . CATAGEO_VERSIONE,
            'PHP ..........: ' . PHP_VERSION,
            'Catasto ......: ' . Config::testo('catasto.nome', ''),
            'File .........: ' . $file,
            'Dimensione ...: ' . Testo::dimensione($byte) . ' (non compressa)',
            '',
            'RIPRISTINO',
            str_repeat('-', 60),
            'I file di questo archivio vanno estratti dentro la cartella "dati"',
            'dell\'installazione, sovrascrivendo quelli presenti.',
            '',
            'Se il backup e di un solo catalogo, contiene anche le anagrafiche e',
            'gli indici: le schede citano gruppi, esploratori e vocabolari per',
            'identificativo, e senza di essi i riferimenti resterebbero appesi.',
            '',
            'Dopo il ripristino, da Strumenti eseguire "Ricostruisci indici":',
            'gli indici sono una cache e si rigenerano dai dati.',
            '',
            'I dati sono XML e CSV leggibili a mano: anche senza CATAGEO,',
            'questo archivio resta consultabile con un editor di testo.',
            '',
        ];

        return implode("\r\n", $righe);
    }
}
