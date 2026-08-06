<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Scientifici.php
 *  Descrizione ..: Dati scientifici di un ipogeo: punti di misura, serie e
 *                  letture (6.13).
 *
 *                  Sezione a due file, ed e una scelta deliberata (D8):
 *                  - un XML descrive punti di misura, strumenti e serie;
 *                  - un CSV per serie contiene le letture.
 *                  Un datalogger produce decine di migliaia di righe: dentro
 *                  un XML sarebbero illeggibili e costose da riscrivere a ogni
 *                  aggiunta, mentre un CSV si accoda senza rileggerlo e si apre
 *                  in un foglio di calcolo.
 *
 *                  Il CSV ripete in ogni riga strumento, unita e provenienza.
 *                  E denormalizzazione voluta: un CSV estratto dall'archivio e
 *                  aperto da solo deve restare comprensibile senza il suo XML.
 *
 *                  Una lettura sbagliata non si cancella mai: si marca. In un
 *                  monitoraggio pluriennale la cancellazione silenziosa di un
 *                  dato scomodo e il modo piu rapido per rendere inutilizzabile
 *                  una serie.
 *  Versione .....: 0.11.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.11.0  2026-08-06  D.Candela  Prima stesura (fase 7c).
 * ============================================================================
 */

final class Scientifici
{
    public const VERSIONE_SCHEMA = '1.0';
    public const SIGLA = 'SC';

    /** Colonne del CSV di una serie, nell'ordine in cui vengono scritte. */
    public const COLONNE = [
        'data', 'ora', 'valore', 'unita', 'grandezza', 'punto_misura',
        'strumento', 'matricola', 'esploratore_id', 'provenienza', 'validita', 'note',
    ];

    public const ACQUISIZIONI = [
        'puntuale'   => 'Misura puntuale',
        'datalogger' => 'Datalogger',
        'campagna'   => 'Campagna di misure',
    ];

    public const PROVENIENZE = [
        'rilevamento_proprio' => 'Rilevamento proprio',
        'ente_esterno'        => 'Ente esterno',
        'pubblicazione'       => 'Pubblicazione',
        'stima'               => 'Stima',
    ];

    /**
     * Una lettura errata non si cancella: si marca. "scartato" toglie il dato
     * dalle statistiche ma non dal file, cosi resta possibile capire in seguito
     * perche una serie ha un buco.
     */
    public const VALIDITA = [
        'valido'   => 'Valido',
        'sospetto' => 'Sospetto',
        'anomalo'  => 'Anomalo',
        'scartato' => 'Scartato',
    ];

    /** Validita che entrano nelle statistiche. */
    public const VALIDITA_UTILI = ['valido', 'sospetto'];

    public const CAMPI_SERIE = [
        'titolo' => '', 'grandezza' => '', 'unita' => '', 'puntoMisura' => '',
        'tipoAcquisizione' => 'puntuale', 'passoTemporale' => '',
        'strumentoModello' => '', 'strumentoMatricola' => '',
        'strumentoTaratura' => '', 'strumentoIncertezza' => '',
        'responsabile' => '', 'gruppo' => '',
        'provenienzaTipo' => 'rilevamento_proprio', 'provenienza' => '',
        'riservatezza' => 'pubblica', 'note' => '',
    ];

    /**
     * Tetto alle righe lette da un CSV in una sola volta.
     *
     * Serve a non far esplodere la memoria su un file corrotto o su una serie
     * cresciuta oltre ogni previsione. Il limite e dichiarato a chi legge
     * invece che applicato in silenzio: una statistica calcolata su meta serie
     * senza dirlo sarebbe peggio di nessuna statistica.
     */
    public const LIMITE_LETTURE = 200000;

    // ========================================================================
    //  PERCORSI
    // ========================================================================

    public static function cartella(string $codice): ?string
    {
        $cartellaIpogeo = Ipogeo::cartella($codice);

        return $cartellaIpogeo === null
            ? null
            : Percorsi::unisci($cartellaIpogeo, Sezioni::nomeCartella($codice, self::SIGLA));
    }

    public static function percorso(string $codice): ?string
    {
        $cartella = self::cartella($codice);

        return $cartella === null
            ? null
            : Percorsi::unisci($cartella, Sezioni::nomeIndice($codice, self::SIGLA));
    }

    /** Percorso del CSV di una serie, ricavato dal nome registrato nell'XML. */
    public static function percorsoCsv(string $codice, array $serie): ?string
    {
        $cartella = self::cartella($codice);
        $file     = trim((string) ($serie['file'] ?? ''));

        if ($cartella === null || $file === '') {
            return null;
        }

        // basename() e la barriera: il nome del file viene da un XML che puo
        // essere stato modificato a mano, e non deve poter uscire dalla
        // cartella della sezione.
        return Percorsi::unisci($cartella, basename($file));
    }

    // ========================================================================
    //  LETTURA
    // ========================================================================

    /**
     * Punti di misura dell'ipogeo.
     *
     * Sono stabili nel tempo e condivisi con le osservazioni biologiche: due
     * misure "in sala grande" prese a cinque anni di distanza restano
     * confrontabili solo se si riferiscono allo stesso punto dichiarato.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function puntiMisura(string $codice): array
    {
        return self::leggi($codice)['punti'];
    }

    /** @return array<string,mixed>|null */
    public static function puntoMisura(string $codice, string $id): ?array
    {
        foreach (self::puntiMisura($codice) as $punto) {
            if ((string) $punto['id'] === $id) {
                return $punto;
            }
        }

        return null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function serie(string $codice): array
    {
        return self::leggi($codice)['serie'];
    }

    public static function conta(string $codice): int
    {
        return count(self::serie($codice));
    }

    /** @return array<string,mixed>|null */
    public static function trovaSerie(string $codice, int $progressivo): ?array
    {
        foreach (self::serie($codice) as $serie) {
            if ((int) $serie['progressivo'] === $progressivo) {
                return $serie;
            }
        }

        return null;
    }

    /**
     * Serie visibili con il livello di utenza in uso.
     *
     * La riservatezza della serie e indipendente da quella dell'ipogeo: una
     * cavita pubblica puo ospitare un monitoraggio che non va divulgato.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function serieVisibili(string $codice): array
    {
        return array_values(array_filter(
            self::serie($codice),
            static fn (array $s): bool => Visibilita::livelloVisibile((string) $s['riservatezza'])
        ));
    }

    /**
     * Letture di una serie.
     *
     * @param  int $limite 0 per il tetto predefinito
     * @return array{letture:array<int,array<string,string>>,troncato:bool,totale:int}
     */
    public static function letture(string $codice, array $serie, int $limite = 0): array
    {
        $percorso = self::percorsoCsv($codice, $serie);
        $limite   = $limite > 0 ? $limite : self::LIMITE_LETTURE;

        $esito = ['letture' => [], 'troncato' => false, 'totale' => 0];
        if ($percorso === null || !is_file($percorso)) {
            return $esito;
        }

        $letture  = [];
        $totale   = 0;
        $troncato = false;

        Csv::leggi($percorso, static function (array $riga) use (&$letture, &$totale, &$troncato, $limite): bool {
            $totale++;
            if (count($letture) >= $limite) {
                $troncato = true;
                // Si continua a contare senza accumulare: sapere quante righe
                // ci sono davvero e cio che permette di dichiarare il taglio.
                return true;
            }
            $letture[] = $riga;

            return true;
        });

        return ['letture' => $letture, 'troncato' => $troncato, 'totale' => $totale];
    }

    /**
     * Riepilogo statistico di una serie.
     *
     * La chiave "esclusePerValidita" conta le letture tolte perche marcate
     * anomale o scartate: e volutamente distinta dal valore di validita
     * "scartato", che ne e solo uno dei due casi.
     *
     * Entrano solo le letture valide o sospette, e solo quelle con un valore:
     * una lettura mancante e un'informazione (lo strumento c'era e non ha
     * misurato), ma non e un numero e non puo entrare in una media.
     *
     * @param  array<int,array<string,string>> $letture
     * @return array<string,mixed>
     */
    public static function statistiche(array $letture): array
    {
        $valori = [];
        $esclusePerValidita = 0;
        $senzaValore = 0;
        $primaData = '';
        $ultimaData = '';

        foreach ($letture as $riga) {
            $data = trim((string) ($riga['data'] ?? ''));
            if ($data !== '') {
                if ($primaData === '' || $data < $primaData) { $primaData = $data; }
                if ($ultimaData === '' || $data > $ultimaData) { $ultimaData = $data; }
            }

            $validita = trim((string) ($riga['validita'] ?? 'valido'));
            if ($validita !== '' && !in_array($validita, self::VALIDITA_UTILI, true)) {
                $esclusePerValidita++;
                continue;
            }

            $grezzo = trim((string) ($riga['valore'] ?? ''));
            if ($grezzo === '') {
                $senzaValore++;
                continue;
            }

            $numero = self::aNumero($grezzo);
            if ($numero === null) {
                $senzaValore++;
                continue;
            }
            $valori[] = $numero;
        }

        $conteggio = count($valori);
        if ($conteggio === 0) {
            return [
                'conteggio' => 0, 'esclusePerValidita' => $esclusePerValidita,
                'senzaValore' => $senzaValore,
                'min' => null, 'max' => null, 'media' => null, 'mediana' => null,
                'dal' => $primaData, 'al' => $ultimaData,
            ];
        }

        sort($valori);
        $meta = intdiv($conteggio, 2);
        $mediana = $conteggio % 2 === 1
            ? $valori[$meta]
            : ($valori[$meta - 1] + $valori[$meta]) / 2;

        return [
            'conteggio'   => $conteggio,
            'esclusePerValidita' => $esclusePerValidita,
            'senzaValore' => $senzaValore,
            'min'         => $valori[0],
            'max'         => $valori[$conteggio - 1],
            'media'       => array_sum($valori) / $conteggio,
            'mediana'     => $mediana,
            'dal'         => $primaData,
            'al'          => $ultimaData,
        ];
    }

    /**
     * Converte in numero un valore letto da CSV.
     *
     * Accetta la virgola decimale: un CSV prodotto da un foglio di calcolo
     * italiano la usa, e rifiutarla renderebbe inutilizzabile meta dei file
     * che questa funzione deve leggere. Restituisce null se non e un numero,
     * cosi chi chiama distingue "zero" da "non misurabile".
     */
    public static function aNumero(string $valore): ?float
    {
        $valore = trim(str_replace(["\u{00A0}", ' '], '', $valore));
        if ($valore === '') {
            return null;
        }

        // La virgola si traduce solo se non c'e gia un punto decimale: in
        // "1.234,5" la virgola e il separatore decimale, in "1,234.5" e quello
        // delle migliaia, e scambiarli cambierebbe il valore di mille volte.
        if (str_contains($valore, ',') && !str_contains($valore, '.')) {
            $valore = str_replace(',', '.', $valore);
        } elseif (str_contains($valore, ',') && str_contains($valore, '.')) {
            $valore = strrpos($valore, ',') > strrpos($valore, '.')
                ? str_replace(['.', ','], ['', '.'], $valore)
                : str_replace(',', '', $valore);
        }

        return is_numeric($valore) ? (float) $valore : null;
    }

    // ========================================================================
    //  SCRITTURA — PUNTI DI MISURA
    // ========================================================================

    /**
     * Crea o aggiorna un punto di misura.
     *
     * @param array<string,mixed> $dati
     */
    public static function salvaPunto(string $codice, string $id, array $dati): string
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new ScientificiEccezione('Ipogeo non trovato: ' . $codice);
        }
        Percorsi::assicuraCartella((string) self::cartella($codice));

        $nome = trim((string) ($dati['nome'] ?? ''));
        if ($nome === '') {
            throw new ScientificiEccezione('Il nome del punto di misura e obbligatorio.');
        }

        foreach (['latitudine' => 90.0, 'longitudine' => 180.0] as $campo => $massimo) {
            $grezzo = trim(str_replace(',', '.', (string) ($dati[$campo] ?? '')));
            if ($grezzo !== '' && (!is_numeric($grezzo) || abs((float) $grezzo) > $massimo)) {
                throw new ScientificiEccezione('Coordinata fuori intervallo: ' . $campo);
            }
        }

        return Xml::conLock($percorso, static function () use ($codice, $id, $dati, $percorso): string {
            $stato = self::leggi($codice);

            if ($id === '') {
                $id = self::prossimoIdPunto($stato['punti']);
            }

            $nuovo = [
                'id'          => $id,
                'nome'        => trim((string) ($dati['nome'] ?? '')),
                'descrizione' => (string) ($dati['descrizione'] ?? ''),
                'latitudine'  => self::gradi((string) ($dati['latitudine'] ?? '')),
                'longitudine' => self::gradi((string) ($dati['longitudine'] ?? '')),
                'quota'       => trim((string) ($dati['quota'] ?? '')),
                'progressiva' => trim((string) ($dati['progressiva'] ?? '')),
            ];

            $sostituito = false;
            foreach ($stato['punti'] as $i => $punto) {
                if ((string) $punto['id'] === $id) {
                    $stato['punti'][$i] = $nuovo;
                    $sostituito = true;
                }
            }
            if (!$sostituito) {
                $stato['punti'][] = $nuovo;
            }

            self::scrivi($codice, $stato, $percorso);

            return $id;
        });
    }

    /**
     * Toglie un punto di misura.
     *
     * Si rifiuta se una serie lo usa: togliere il punto lascerebbe la serie a
     * riferire un luogo che non esiste piu, e una misura senza luogo non e
     * confrontabile con nulla.
     */
    public static function eliminaPunto(string $codice, string $id): void
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new ScientificiEccezione('Ipogeo non trovato: ' . $codice);
        }

        Xml::conLock($percorso, static function () use ($codice, $id, $percorso): void {
            $stato = self::leggi($codice);

            $usi = 0;
            foreach ($stato['serie'] as $serie) {
                if ((string) $serie['puntoMisura'] === $id) {
                    $usi++;
                }
            }
            if ($usi > 0) {
                throw new ScientificiEccezione(
                    'Il punto ' . $id . ' e usato da ' . $usi . ' serie: '
                    . 'spostale su un altro punto prima di toglierlo.');
            }

            $stato['punti'] = array_values(array_filter(
                $stato['punti'],
                static fn (array $p): bool => (string) $p['id'] !== $id
            ));

            self::scrivi($codice, $stato, $percorso);
        });
    }

    // ========================================================================
    //  SCRITTURA — SERIE
    // ========================================================================

    /**
     * Crea una serie e il suo CSV, e ne restituisce il progressivo.
     *
     * @param array<string,mixed> $dati
     */
    public static function creaSerie(string $codice, array $dati): int
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new ScientificiEccezione('Ipogeo non trovato: ' . $codice);
        }
        $cartella = (string) self::cartella($codice);
        Percorsi::assicuraCartella($cartella);

        self::validaSerie($codice, $dati);

        return Xml::conLock($percorso, static function () use ($codice, $dati, $percorso, $cartella): int {
            $stato = self::leggi($codice);
            $progressivo = $stato['ultimoProgressivo'] + 1;

            $serie = array_merge(self::CAMPI_SERIE, $dati);
            $serie['progressivo'] = $progressivo;
            $serie['file'] = Sezioni::nomeFile($codice, self::SIGLA, $progressivo,
                                               (string) $serie['titolo'] . '.csv');

            // Il CSV nasce con la sola intestazione: cosi la serie esiste come
            // file anche prima della prima lettura, e chi apre la cartella
            // vede che il monitoraggio e stato avviato.
            $csv = Percorsi::unisci($cartella, $serie['file']);
            if (!is_file($csv)) {
                Csv::scrivi($csv, self::COLONNE, [], true);
            }

            $stato['serie'][] = $serie;
            $stato['ultimoProgressivo'] = $progressivo;
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('serie_creata', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo) . ' ' . $serie['titolo']);

            return $progressivo;
        });
    }

    /**
     * Aggiorna il descrittore di una serie. Il CSV non viene toccato.
     *
     * @param array<string,mixed> $dati
     */
    public static function aggiornaSerie(string $codice, int $progressivo, array $dati): void
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new ScientificiEccezione('Ipogeo non trovato: ' . $codice);
        }

        self::validaSerie($codice, $dati);

        Xml::conLock($percorso, static function () use ($codice, $progressivo, $dati, $percorso): void {
            $stato = self::leggi($codice);

            $trovata = false;
            foreach ($stato['serie'] as $i => $serie) {
                if ((int) $serie['progressivo'] !== $progressivo) {
                    continue;
                }
                $aggiornata = array_merge(self::CAMPI_SERIE, $dati);
                $aggiornata['progressivo'] = $progressivo;
                // Il nome del file non segue il titolo: rinominare un CSV
                // spezzerebbe i riferimenti di chi lo ha gia scaricato o
                // citato, e il file contiene dati, non solo metadati.
                $aggiornata['file'] = (string) $serie['file'];
                $stato['serie'][$i] = $aggiornata;
                $trovata = true;
            }

            if (!$trovata) {
                throw new ScientificiEccezione(
                    'Serie non trovata: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
            }

            self::scrivi($codice, $stato, $percorso);

            Log::modifica('serie_aggiornata', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo));
        });
    }

    /**
     * Toglie una serie: il descrittore esce dall'XML, il CSV va in "_rimossi".
     *
     * Il file non si cancella mai. Un monitoraggio pluriennale e un dato che
     * non si rifa: se e stato tolto per errore deve essere recuperabile.
     */
    public static function eliminaSerie(string $codice, int $progressivo): void
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new ScientificiEccezione('Ipogeo non trovato: ' . $codice);
        }

        Xml::conLock($percorso, static function () use ($codice, $progressivo, $percorso): void {
            $stato = self::leggi($codice);

            $tolta = null;
            $rimaste = [];
            foreach ($stato['serie'] as $serie) {
                if ((int) $serie['progressivo'] === $progressivo) {
                    $tolta = $serie;
                    continue;
                }
                $rimaste[] = $serie;
            }

            if ($tolta === null) {
                throw new ScientificiEccezione(
                    'Serie non trovata: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
            }

            $csv = self::percorsoCsv($codice, $tolta);
            if ($csv !== null && is_file($csv)) {
                $deposito = Percorsi::assicuraCartella(Percorsi::unisci(
                    (string) Ipogeo::cartella($codice),
                    $codice . ' - ' . Risorse::CARTELLA_RIMOSSI
                ));
                @rename($csv, Percorsi::unisci($deposito, date('Ymd-His') . '-' . basename($csv)));
            }

            $stato['serie'] = $rimaste;
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('serie_rimossa', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo) . ' ' . $tolta['titolo']);
        });
    }

    // ========================================================================
    //  LETTURE
    // ========================================================================

    /**
     * Accoda una lettura al CSV della serie.
     *
     * Si accoda e non si riscrive: un file di trentamila righe riletto e
     * riscritto a ogni misura sarebbe lento e, a meta strada, distruttivo.
     *
     * @param array<string,mixed> $lettura
     */
    public static function aggiungiLettura(string $codice, int $progressivo, array $lettura): void
    {
        $serie = self::trovaSerie($codice, $progressivo);
        if ($serie === null) {
            throw new ScientificiEccezione(
                'Serie non trovata: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
        }

        $csv = self::percorsoCsv($codice, $serie);
        if ($csv === null) {
            throw new ScientificiEccezione('La serie non ha un file associato.');
        }

        $riga = self::componiRiga($serie, $lettura);
        if ($riga['data'] === '') {
            throw new ScientificiEccezione('La data della lettura e obbligatoria.');
        }

        Csv::accoda($csv, self::COLONNE, $riga);
        self::aggiornaConteggio($codice, $progressivo);
    }

    /**
     * Importa piu letture in una volta.
     *
     * @param  array<int,array<string,mixed>> $letture
     * @return array{importate:int,scartate:int,motivi:array<int,string>}
     */
    public static function importaLetture(string $codice, int $progressivo, array $letture): array
    {
        $serie = self::trovaSerie($codice, $progressivo);
        if ($serie === null) {
            throw new ScientificiEccezione(
                'Serie non trovata: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
        }

        $csv = self::percorsoCsv($codice, $serie);
        if ($csv === null) {
            throw new ScientificiEccezione('La serie non ha un file associato.');
        }

        $righe = [];
        $scartate = 0;
        $motivi = [];

        foreach ($letture as $indice => $lettura) {
            $riga = self::componiRiga($serie, $lettura);

            if ($riga['data'] === '') {
                $scartate++;
                // Si conservano solo i primi motivi: su un file da diecimila
                // righe sbagliate l'elenco completo non aiuterebbe nessuno.
                if (count($motivi) < 10) {
                    $motivi[] = 'riga ' . ($indice + 1) . ': data mancante o non riconosciuta';
                }
                continue;
            }

            $righe[] = $riga;
        }

        foreach ($righe as $riga) {
            Csv::accoda($csv, self::COLONNE, $riga);
        }

        self::aggiornaConteggio($codice, $progressivo);

        return ['importate' => count($righe), 'scartate' => $scartate, 'motivi' => $motivi];
    }

    /**
     * Riconosce una data nei formati che i datalogger producono davvero.
     *
     * Restituisce ISO, oppure stringa vuota se non e una data. Non si usa
     * strtotime(): su "03/04/2026" sceglierebbe il mese all'americana, e in un
     * archivio italiano sbaglierebbe undici giorni su dodici.
     */
    public static function normalizzaData(string $valore): string
    {
        $valore = trim($valore);
        if ($valore === '') {
            return '';
        }

        // ISO, gia nella forma giusta, eventualmente con l'ora attaccata.
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valore, $p)) {
            return self::dataValida((int) $p[1], (int) $p[2], (int) $p[3]);
        }

        // Giorno prima del mese: e la convenzione italiana ed europea.
        if (preg_match('~^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})~', $valore, $p)) {
            $anno = (int) $p[3];
            if ($anno < 100) {
                // Finestra a due cifre: 70-99 e Novecento, 00-69 e Duemila.
                $anno += $anno >= 70 ? 1900 : 2000;
            }

            return self::dataValida($anno, (int) $p[2], (int) $p[1]);
        }

        return '';
    }

    /** Ora normalizzata a HH:MM, o stringa vuota. */
    public static function normalizzaOra(string $valore): string
    {
        $valore = trim($valore);

        // L'ora puo arrivare attaccata alla data: "2026-03-01 08:00:00".
        if (preg_match('/(\d{1,2}):(\d{2})/', $valore, $p)) {
            $ore = (int) $p[1];
            $minuti = (int) $p[2];
            if ($ore <= 23 && $minuti <= 59) {
                return sprintf('%02d:%02d', $ore, $minuti);
            }
        }

        return '';
    }

    /**
     * Anteprima di un CSV da importare: intestazione e prime righe.
     *
     * Serve alla mappatura interattiva delle colonne: chi importa deve vedere
     * cosa sta mappando, perche i datalogger nominano le colonne in modi che
     * nessun riconoscimento automatico puo indovinare tutti.
     *
     * @return array{intestazione:array<int,string>,righe:array<int,array<int,string>>,separatore:string}
     */
    public static function anteprimaCsv(string $percorso, int $quante = 5): array
    {
        $vuoto = ['intestazione' => [], 'righe' => [], 'separatore' => ';'];
        if (!is_file($percorso)) {
            return $vuoto;
        }

        $contenuto = (string) file_get_contents($percorso, false, null, 0, 65536);
        if ($contenuto === '') {
            return $vuoto;
        }

        // Il separatore si deduce contando: un file esportato da uno strumento
        // anglosassone usa la virgola, uno italiano il punto e virgola, e
        // sbagliarlo produrrebbe una sola colonna con dentro tutto.
        $separatore = ';';
        $conteggi = [';' => substr_count($contenuto, ';'),
                     ',' => substr_count($contenuto, ','),
                     "\t" => substr_count($contenuto, "\t")];
        arsort($conteggi);
        $primo = array_key_first($conteggi);
        if ($primo !== null && $conteggi[$primo] > 0) {
            $separatore = (string) $primo;
        }

        $maniglia = @fopen($percorso, 'r');
        if ($maniglia === false) {
            return $vuoto;
        }

        // Il BOM di un "CSV UTF-8" salvato da Excel finirebbe dentro il nome
        // della prima colonna, rendendola non riconoscibile.
        $inizio = fread($maniglia, 3);
        if ($inizio !== "\xEF\xBB\xBF") {
            rewind($maniglia);
        }

        $intestazione = fgetcsv($maniglia, 0, $separatore) ?: [];
        $righe = [];
        while (count($righe) < $quante && ($riga = fgetcsv($maniglia, 0, $separatore)) !== false) {
            if ($riga === [null]) {
                continue;
            }
            $righe[] = array_map(static fn ($v): string => (string) $v, $riga);
        }
        fclose($maniglia);

        return [
            'intestazione' => array_map(static fn ($v): string => trim((string) $v), $intestazione),
            'righe'        => $righe,
            'separatore'   => $separatore,
        ];
    }

    /**
     * Legge un CSV esterno applicando una mappatura colonna -> campo.
     *
     * @param  array<string,int> $mappatura campo => indice di colonna
     * @return array<int,array<string,string>>
     */
    public static function leggiCsvEsterno(string $percorso, array $mappatura, string $separatore = ';', int $limite = 0): array
    {
        $limite = $limite > 0 ? $limite : self::LIMITE_LETTURE;
        $esiti = [];

        $maniglia = @fopen($percorso, 'r');
        if ($maniglia === false) {
            return $esiti;
        }

        $inizio = fread($maniglia, 3);
        if ($inizio !== "\xEF\xBB\xBF") {
            rewind($maniglia);
        }

        fgetcsv($maniglia, 0, $separatore); // intestazione

        while (count($esiti) < $limite && ($riga = fgetcsv($maniglia, 0, $separatore)) !== false) {
            if ($riga === [null] || $riga === false) {
                continue;
            }

            $voce = [];
            foreach ($mappatura as $campo => $colonna) {
                $voce[$campo] = isset($riga[$colonna]) ? trim((string) $riga[$colonna]) : '';
            }
            if (implode('', $voce) === '') {
                continue;
            }
            $esiti[] = $voce;
        }
        fclose($maniglia);

        return $esiti;
    }

    // ========================================================================
    //  INTERNI
    // ========================================================================

    /**
     * Compone una riga di CSV a partire da una lettura e dal descrittore.
     *
     * Strumento, unita e provenienza si ripetono in ogni riga: e la
     * denormalizzazione che rende il CSV comprensibile anche estratto da solo.
     *
     * @param  array<string,mixed> $serie
     * @param  array<string,mixed> $lettura
     * @return array<string,string>
     */
    private static function componiRiga(array $serie, array $lettura): array
    {
        $validita = trim((string) ($lettura['validita'] ?? 'valido'));

        return [
            'data'           => self::normalizzaData((string) ($lettura['data'] ?? '')),
            'ora'            => self::normalizzaOra((string) ($lettura['ora'] ?? ($lettura['data'] ?? ''))),
            'valore'         => trim((string) ($lettura['valore'] ?? '')),
            'unita'          => (string) $serie['unita'],
            'grandezza'      => (string) $serie['grandezza'],
            'punto_misura'   => (string) $serie['puntoMisura'],
            'strumento'      => (string) $serie['strumentoModello'],
            'matricola'      => (string) $serie['strumentoMatricola'],
            'esploratore_id' => trim((string) ($lettura['esploratoreId'] ?? $serie['responsabile'])),
            'provenienza'    => (string) $serie['provenienzaTipo'],
            'validita'       => isset(self::VALIDITA[$validita]) ? $validita : 'valido',
            'note'           => trim((string) ($lettura['note'] ?? '')),
        ];
    }

    /** Ricalcola numeroLetture e periodo dal CSV, e li riscrive nel descrittore. */
    private static function aggiornaConteggio(string $codice, int $progressivo): void
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            return;
        }

        Xml::conLock($percorso, static function () use ($codice, $progressivo, $percorso): void {
            $stato = self::leggi($codice);

            foreach ($stato['serie'] as $i => $serie) {
                if ((int) $serie['progressivo'] !== $progressivo) {
                    continue;
                }

                $dati = self::letture($codice, $serie);
                $stat = self::statistiche($dati['letture']);

                $stato['serie'][$i]['numeroLetture'] = (string) $dati['totale'];
                $stato['serie'][$i]['periodoDal'] = (string) $stat['dal'];
                $stato['serie'][$i]['periodoAl']  = (string) $stat['al'];
            }

            self::scrivi($codice, $stato, $percorso);
        });
    }

    /**
     * @return array{punti:array<int,array<string,mixed>>,serie:array<int,array<string,mixed>>,ultimoProgressivo:int}
     */
    private static function leggi(string $codice): array
    {
        $vuoto = ['punti' => [], 'serie' => [], 'ultimoProgressivo' => 0];

        $percorso = self::percorso($codice);
        if ($percorso === null || !is_file($percorso)) {
            return $vuoto;
        }

        try {
            $doc = Xml::carica($percorso);
        } catch (Throwable $e) {
            Log::errore('Dati scientifici illeggibili: ' . $percorso . ' — ' . $e->getMessage());
            return $vuoto;
        }

        $radice = $doc->documentElement;
        if ($radice === null) {
            return $vuoto;
        }

        $punti = [];
        foreach (Xml::elenco($doc, '/scientifici/puntiMisura/punto') as $nodo) {
            $punti[] = [
                'id'          => $nodo->getAttribute('id'),
                'nome'        => $nodo->getAttribute('nome'),
                'descrizione' => Xml::testo($nodo, 'descrizione'),
                'latitudine'  => Xml::testo($nodo, 'latitudine'),
                'longitudine' => Xml::testo($nodo, 'longitudine'),
                'quota'       => Xml::testo($nodo, 'quota'),
                'progressiva' => Xml::testo($nodo, 'progressivaInterna'),
            ];
        }

        $serie = [];
        foreach (Xml::elenco($doc, '/scientifici/serie') as $nodo) {
            $voce = ['progressivo' => (int) $nodo->getAttribute('progressivo')];

            $voce['file']             = Xml::testo($nodo, 'file');
            $voce['titolo']           = Xml::testo($nodo, 'titolo');
            $voce['grandezza']        = Xml::testo($nodo, 'grandezza');
            $voce['unita']            = Xml::testo($nodo, 'unita');
            $voce['puntoMisura']      = Xml::testo($nodo, 'puntoMisura');
            $voce['tipoAcquisizione'] = Xml::testo($nodo, 'tipoAcquisizione');
            $voce['passoTemporale']   = Xml::testo($nodo, 'passoTemporale');

            $voce['strumentoModello']    = Xml::testo($nodo, 'strumento/modello');
            $voce['strumentoMatricola']  = Xml::testo($nodo, 'strumento/matricola');
            $voce['strumentoTaratura']   = Xml::testo($nodo, 'strumento/ultimaTaratura');
            $voce['strumentoIncertezza'] = Xml::testo($nodo, 'strumento/incertezza');

            $responsabile = Xml::primo($nodo, 'responsabile');
            $voce['responsabile'] = $responsabile instanceof DOMElement
                ? $responsabile->getAttribute('esploratoreId') : '';

            $gruppo = Xml::primo($nodo, 'gruppo');
            $voce['gruppo'] = $gruppo instanceof DOMElement ? $gruppo->getAttribute('id') : '';

            $provenienza = Xml::primo($nodo, 'provenienza');
            $voce['provenienzaTipo'] = $provenienza instanceof DOMElement
                ? $provenienza->getAttribute('tipo') : 'rilevamento_proprio';
            $voce['provenienza'] = Xml::testo($nodo, 'provenienza');

            $periodo = Xml::primo($nodo, 'periodo');
            $voce['periodoDal'] = $periodo instanceof DOMElement ? $periodo->getAttribute('dal') : '';
            $voce['periodoAl']  = $periodo instanceof DOMElement ? $periodo->getAttribute('al') : '';

            $voce['numeroLetture'] = Xml::testo($nodo, 'numeroLetture');
            $voce['riservatezza']  = Xml::testo($nodo, 'riservatezza', 'pubblica');
            $voce['note']          = Xml::testo($nodo, 'note');

            $serie[] = $voce;
        }

        usort($serie, static fn (array $a, array $b): int => $a['progressivo'] <=> $b['progressivo']);

        $ultimo = (int) $radice->getAttribute('ultimoProgressivo');
        foreach ($serie as $s) {
            $ultimo = max($ultimo, (int) $s['progressivo']);
        }

        return ['punti' => $punti, 'serie' => $serie, 'ultimoProgressivo' => $ultimo];
    }

    /**
     * @param array{punti:array<int,array<string,mixed>>,serie:array<int,array<string,mixed>>,ultimoProgressivo:int} $stato
     */
    private static function scrivi(string $codice, array $stato, string $percorso): void
    {
        $doc = Xml::nuovo('scientifici', [
            'versioneSchema'    => self::VERSIONE_SCHEMA,
            'codiceIpogeo'      => $codice,
            'ultimoProgressivo' => (string) $stato['ultimoProgressivo'],
        ]);
        $radice = $doc->documentElement;

        $contenitore = Xml::aggiungi($radice, 'puntiMisura');
        foreach ($stato['punti'] as $punto) {
            $nodo = Xml::aggiungi($contenitore, 'punto', null, [
                'id'   => (string) $punto['id'],
                'nome' => (string) $punto['nome'],
            ]);
            Xml::imposta($nodo, 'descrizione', (string) $punto['descrizione'], true);
            Xml::imposta($nodo, 'latitudine', (string) $punto['latitudine']);
            Xml::imposta($nodo, 'longitudine', (string) $punto['longitudine']);
            if ((string) $punto['quota'] !== '') {
                Xml::imposta($nodo, 'quota', (string) $punto['quota'])->setAttribute('unita', 'm');
            }
            if ((string) $punto['progressiva'] !== '') {
                Xml::imposta($nodo, 'progressivaInterna', (string) $punto['progressiva'])
                    ->setAttribute('unita', 'm');
            }
        }

        foreach ($stato['serie'] as $serie) {
            $serie = array_merge(self::CAMPI_SERIE, $serie);

            $nodo = Xml::aggiungi($radice, 'serie', null, [
                'progressivo' => (string) $serie['progressivo'],
                'sigla'       => self::SIGLA,
            ]);

            Xml::imposta($nodo, 'file', (string) $serie['file']);
            Xml::imposta($nodo, 'titolo', (string) $serie['titolo']);
            Xml::imposta($nodo, 'grandezza', (string) $serie['grandezza']);
            Xml::imposta($nodo, 'unita', (string) $serie['unita']);
            Xml::imposta($nodo, 'puntoMisura', (string) $serie['puntoMisura']);

            $acquisizione = (string) $serie['tipoAcquisizione'];
            Xml::imposta($nodo, 'tipoAcquisizione',
                isset(self::ACQUISIZIONI[$acquisizione]) ? $acquisizione : 'puntuale');
            Xml::imposta($nodo, 'passoTemporale', (string) $serie['passoTemporale']);

            $strumento = Xml::imposta($nodo, 'strumento', null);
            Xml::imposta($strumento, 'modello', (string) $serie['strumentoModello']);
            Xml::imposta($strumento, 'matricola', (string) $serie['strumentoMatricola']);
            Xml::imposta($strumento, 'ultimaTaratura', (string) $serie['strumentoTaratura']);
            Xml::imposta($strumento, 'incertezza', (string) $serie['strumentoIncertezza']);

            Xml::imposta($nodo, 'responsabile', null)
                ->setAttribute('esploratoreId', (string) $serie['responsabile']);
            Xml::imposta($nodo, 'gruppo', null)->setAttribute('id', (string) $serie['gruppo']);

            $tipoProvenienza = (string) $serie['provenienzaTipo'];
            Xml::imposta($nodo, 'provenienza', (string) $serie['provenienza'])
                ->setAttribute('tipo', isset(self::PROVENIENZE[$tipoProvenienza])
                    ? $tipoProvenienza : 'rilevamento_proprio');

            $periodo = Xml::imposta($nodo, 'periodo', null);
            $periodo->setAttribute('dal', (string) ($serie['periodoDal'] ?? ''));
            $periodo->setAttribute('al', (string) ($serie['periodoAl'] ?? ''));

            Xml::imposta($nodo, 'numeroLetture', (string) ($serie['numeroLetture'] ?? '0'));

            $riservatezza = (string) $serie['riservatezza'];
            Xml::imposta($nodo, 'riservatezza',
                in_array($riservatezza, ['pubblica', 'riservata'], true)
                    ? $riservatezza : 'pubblica');

            Xml::imposta($nodo, 'note', (string) $serie['note'], true);
        }

        Xml::salva($doc, $percorso, Percorsi::schema('scientifici.xsd'));
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws ScientificiEccezione
     */
    private static function validaSerie(string $codice, array $dati): void
    {
        if (trim((string) ($dati['titolo'] ?? '')) === '') {
            throw new ScientificiEccezione('Il titolo della serie e obbligatorio: compare nel nome del file.');
        }

        $grandezza = trim((string) ($dati['grandezza'] ?? ''));
        if ($grandezza === '') {
            throw new ScientificiEccezione('Indicare la grandezza misurata.');
        }
        if (Grandezze::trova($grandezza) === null) {
            throw new ScientificiEccezione(
                'Grandezza non presente nel vocabolario: ' . $grandezza
                . '. Censiscila fra le anagrafiche prima di usarla.');
        }

        $acquisizione = trim((string) ($dati['tipoAcquisizione'] ?? 'puntuale'));
        if ($acquisizione !== '' && !isset(self::ACQUISIZIONI[$acquisizione])) {
            throw new ScientificiEccezione('Tipo di acquisizione non riconosciuto: ' . $acquisizione);
        }

        $provenienza = trim((string) ($dati['provenienzaTipo'] ?? 'rilevamento_proprio'));
        if ($provenienza !== '' && !isset(self::PROVENIENZE[$provenienza])) {
            throw new ScientificiEccezione('Provenienza non riconosciuta: ' . $provenienza);
        }

        $punto = trim((string) ($dati['puntoMisura'] ?? ''));
        if ($punto !== '' && self::puntoMisura($codice, $punto) === null) {
            throw new ScientificiEccezione('Punto di misura inesistente: ' . $punto);
        }
    }

    /** @param array<int,array<string,mixed>> $punti */
    private static function prossimoIdPunto(array $punti): string
    {
        $massimo = 0;
        foreach ($punti as $punto) {
            $massimo = max($massimo, (int) preg_replace('/\D/', '', (string) $punto['id']));
        }

        return 'PM' . ($massimo + 1);
    }

    private static function gradi(string $valore): string
    {
        $valore = trim(str_replace(',', '.', $valore));

        return $valore !== '' && is_numeric($valore) ? number_format((float) $valore, 6, '.', '') : '';
    }

    /** Data ISO se il giorno esiste davvero, stringa vuota altrimenti. */
    private static function dataValida(int $anno, int $mese, int $giorno): string
    {
        if (!checkdate($mese, $giorno, $anno)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $anno, $mese, $giorno);
    }

    private static function catalogoDi(string $codice): string
    {
        $riga = IndiceIpogei::trova($codice);

        return $riga === null ? '' : (string) ($riga['catalogo'] ?? '');
    }
}
