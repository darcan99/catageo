<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Integrita.php
 *  Descrizione ..: Verifica dell'integrita dell'archivio (9.14).
 *
 *                  Cerca cio che il funzionamento normale non fa emergere: XML
 *                  non validi, riferimenti rotti, codici duplicati fra
 *                  cataloghi, contatori disallineati, file orfani del loro
 *                  descrittore, cartelle fuori standard.
 *
 *                  Non corregge nulla. Un archivio dove i dati sono file
 *                  leggibili a mano si ripara guardando, e una correzione
 *                  automatica che indovina male su un catasto di trent'anni fa
 *                  piu danni di un problema segnalato. L'unica cosa che si
 *                  offre di rifare e l'INDICE, che e una cache e si rigenera
 *                  dai dati.
 *
 *                  Ogni problema porta con se il percorso del file e cosa
 *                  farne: un elenco di anomalie senza indicazioni e solo un
 *                  motivo di preoccupazione.
 *  Versione .....: 0.15.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.15.0  2026-08-06  D.Candela  Prima stesura (fase 9).
 * ============================================================================
 */

final class Integrita
{
    public const ERRORE     = 'errore';
    public const ATTENZIONE = 'attenzione';

    /**
     * Tetto ai problemi raccolti per categoria.
     *
     * Un archivio molto malandato produrrebbe migliaia di righe, e una pagina
     * di migliaia di righe non la legge nessuno. Il taglio viene dichiarato.
     */
    public const LIMITE_PER_CATEGORIA = 200;

    /**
     * Esegue tutti i controlli.
     *
     * @return array{
     *     problemi:array<int,array{gravita:string,categoria:string,oggetto:string,descrizione:string,rimedio:string}>,
     *     conteggi:array<string,int>,
     *     esaminati:array<string,int>,
     *     troncate:array<int,string>
     * }
     */
    public static function verifica(): array
    {
        $problemi = [];
        $esaminati = ['ipogei' => 0, 'cataloghi' => 0, 'sezioni' => 0, 'file' => 0];
        $troncate = [];

        $aggiungi = static function (
            string $gravita, string $categoria, string $oggetto,
            string $descrizione, string $rimedio
        ) use (&$problemi, &$troncate): void {
            $quanti = 0;
            foreach ($problemi as $p) {
                if ($p['categoria'] === $categoria) {
                    $quanti++;
                }
            }
            if ($quanti >= self::LIMITE_PER_CATEGORIA) {
                if (!in_array($categoria, $troncate, true)) {
                    $troncate[] = $categoria;
                }
                return;
            }
            $problemi[] = [
                'gravita' => $gravita, 'categoria' => $categoria, 'oggetto' => $oggetto,
                'descrizione' => $descrizione, 'rimedio' => $rimedio,
            ];
        };

        // ------------------------------------------------------- cataloghi
        $cataloghi = Cataloghi::elenco();
        $esaminati['cataloghi'] = count($cataloghi);

        if ($cataloghi === []) {
            $aggiungi(self::ERRORE, 'Cataloghi', 'archivio',
                'Nessun catalogo presente.',
                'Crearne uno da Cataloghi: senza, non si può censire nulla.');
        }

        foreach ($cataloghi as $catalogo) {
            $sigla = (string) $catalogo['sigla'];

            if ($catalogo['serie'] === []) {
                $aggiungi(self::ERRORE, 'Cataloghi', $sigla,
                    'Il catalogo non ha nessuna serie di codifica.',
                    'Aggiungere una serie: senza, il catalogo non può assegnare codici.');
            }

            $cartella = Percorsi::cataloghi((string) $catalogo['cartella']);
            if (!is_dir($cartella)) {
                $aggiungi(self::ERRORE, 'Cataloghi', $sigla,
                    'La cartella del catalogo non esiste: ' . (string) $catalogo['cartella'],
                    'Ripristinarla da un backup, oppure correggere il descrittore.');
            }
        }

        // ------------------------------------- codici duplicati fra cataloghi
        $visti = [];
        foreach (IndiceIpogei::elenco() as $riga) {
            $codice = strtoupper((string) $riga['codice']);
            if (isset($visti[$codice])) {
                $aggiungi(self::ERRORE, 'Codici', (string) $riga['codice'],
                    'Codice presente due volte, nei cataloghi '
                    . $visti[$codice] . ' e ' . (string) $riga['catalogo'] . '.',
                    'Rinominare uno dei due da scheda: due ipogei con lo stesso codice '
                    . 'rendono ambiguo ogni riferimento.');
            }
            $visti[$codice] = (string) $riga['catalogo'];
        }

        // ------------------------------------- scansione degli ipogei sul disco
        $codiciSuDisco = [];

        foreach ($cataloghi as $catalogo) {
            $cartellaIpogei = Percorsi::unisci(
                Percorsi::cataloghi((string) $catalogo['cartella']), 'ipogei');
            if (!is_dir($cartellaIpogei)) {
                continue;
            }

            foreach (scandir($cartellaIpogei) ?: [] as $voce) {
                if ($voce === '.' || $voce === '..') {
                    continue;
                }
                $percorso = Percorsi::unisci($cartellaIpogei, $voce);
                if (!is_dir($percorso)) {
                    $aggiungi(self::ATTENZIONE, 'Cartelle', $voce,
                        'File sciolto nella cartella degli ipogei di ' . (string) $catalogo['sigla'] . '.',
                        'Spostarlo dentro l\'ipogeo a cui appartiene, o rimuoverlo.');
                    continue;
                }

                /*
                 * La conformita si verifica qui e non con
                 * Ipogeo::codiceDaNomeCartella(), che davanti a un nome senza
                 * " - " restituisce l'intero nome: e il comportamento giusto
                 * per chi deve ricavare un codice da un nome corretto, ma qui
                 * serve sapere se il nome e corretto, che e domanda diversa.
                 */
                $codice = Ipogeo::codiceDaNomeCartella($voce);
                if (!str_contains($voce, ' - ') || $codice === ''
                    || !CodiceCatastale::formaValida($codice)) {
                    $aggiungi(self::ATTENZIONE, 'Cartelle', $voce,
                        'Nome di cartella non conforme allo standard "[codice] - [nome]".',
                        'Rinominarla secondo lo standard, altrimenti l\'ipogeo non viene indicizzato.');
                    continue;
                }

                $esaminati['ipogei']++;
                $codiciSuDisco[strtoupper($codice)] = $percorso;

                /*
                 * Ogni ipogeo si verifica dentro un try: e uno strumento che
                 * cerca file rotti, e non puo fermarsi sul primo che trova.
                 * Senza questa rete un solo XML troncato interrompeva l'intera
                 * scansione, lasciando credere che il resto dell'archivio non
                 * fosse stato controllato — o peggio, che fosse a posto.
                 */
                try {
                    self::verificaIpogeo($codice, $percorso, $catalogo, $aggiungi, $esaminati);
                } catch (Throwable $e) {
                    $aggiungi(self::ERRORE, 'Schede', $codice,
                        'La verifica di questo ipogeo si è interrotta: ' . $e->getMessage(),
                        'Aprire i file dell\'ipogeo e correggerli: finché non sono leggibili, '
                        . 'i controlli successivi su questa scheda non si possono fare.');
                }
            }
        }

        // ------------------------------------- indice contro disco, e viceversa
        foreach (IndiceIpogei::elenco() as $riga) {
            $codice = strtoupper((string) $riga['codice']);
            if (!isset($codiciSuDisco[$codice])) {
                $aggiungi(self::ERRORE, 'Indice', (string) $riga['codice'],
                    'Presente nell\'indice ma non sul disco.',
                    'Ricostruire l\'indice: se l\'ipogeo è stato eliminato, la riga sparira.');
            }
        }

        foreach (array_keys($codiciSuDisco) as $codice) {
            if (IndiceIpogei::trova($codice) === null) {
                $aggiungi(self::ATTENZIONE, 'Indice', $codice,
                    'Presente sul disco ma non nell\'indice.',
                    'Ricostruire l\'indice: la riga verra aggiunta.');
            }
        }

        // ---------------------------------------------- indice dei codici
        foreach (array_keys($codiciSuDisco) as $codice) {
            if (!IndiceCodici::esiste($codice)) {
                $aggiungi(self::ATTENZIONE, 'Codici', $codice,
                    'Codice non registrato in codici.csv.',
                    'Ricostruire gli indici: senza registrazione il codice potrebbe '
                    . 'essere riassegnato a un altro ipogeo.');
            }
        }

        // ------------------------------------------- contatori delle serie
        foreach ($cataloghi as $catalogo) {
            foreach ($catalogo['serie'] as $serie) {
                $prefisso = (string) $serie['prefisso'];
                $prossimo = (int) $serie['prossimoProgressivo'];

                $massimo = 0;
                foreach (array_keys($codiciSuDisco) as $codice) {
                    if (stripos($codice, $prefisso) !== 0) {
                        continue;
                    }
                    $numero = (int) preg_replace('/\D/', '', substr($codice, strlen($prefisso)));
                    $massimo = max($massimo, $numero);
                }

                if ($massimo >= $prossimo) {
                    $aggiungi(self::ERRORE, 'Contatori',
                        (string) $catalogo['sigla'] . ' / ' . $prefisso,
                        'Il contatore e a ' . $prossimo . ' ma esiste già il codice numero '
                        . $massimo . '.',
                        'Allineare il contatore ad almeno ' . ($massimo + 1)
                        . ' da Cataloghi: così com\'è, il prossimo censimento tenterebbe '
                        . 'un codice già usato.');
                }
            }
        }

        // ----------------------------------------------------- riepilogo
        $conteggi = [self::ERRORE => 0, self::ATTENZIONE => 0];
        foreach ($problemi as $p) {
            $conteggi[$p['gravita']]++;
        }

        return [
            'problemi'  => $problemi,
            'conteggi'  => $conteggi,
            'esaminati' => $esaminati,
            'troncate'  => $troncate,
        ];
    }

    /**
     * Controlli su un singolo ipogeo.
     *
     * @param array<string,mixed> $catalogo
     * @param callable            $aggiungi
     * @param array<string,int>   $esaminati
     */
    private static function verificaIpogeo(
        string $codice, string $cartella, array $catalogo, callable $aggiungi, array &$esaminati
    ): void {
        // --- scheda
        $dati = Percorsi::unisci($cartella, $codice . ' - Dati.xml');
        if (!is_file($dati)) {
            $aggiungi(self::ERRORE, 'Schede', $codice,
                'Manca il file dei dati: ' . basename($dati),
                'Ripristinarlo da un backup o dallo storico della scheda.');
            return;
        }
        $esaminati['file']++;

        $errori = Xml::valida(self::caricaSilenzioso($dati), Percorsi::schema('ipogeo.xsd'));
        if ($errori !== []) {
            $aggiungi(self::ERRORE, 'Schede', $codice,
                'La scheda non è valida secondo lo schema: ' . implode('; ', array_slice($errori, 0, 3)),
                'Aprire il file e correggerlo: è leggibile a mano. '
                . 'Finché non è valido, ogni salvataggio da interfaccia verra rifiutato.');
        }

        /*
         * Se la scheda non e leggibile ci si ferma QUI, ma senza rilanciare: il
         * problema e gia stato segnalato dalla validazione, e i controlli che
         * seguono hanno tutti bisogno di una scheda interpretabile. Continuare
         * produrrebbe una cascata di segnalazioni derivate che nascondono la
         * causa vera.
         */
        try {
            $scheda = Ipogeo::trova($codice);
        } catch (Throwable $e) {
            $aggiungi(self::ERRORE, 'Schede', $codice,
                'La scheda non è leggibile: ' . $e->getMessage(),
                'Correggere il file, oppure ripristinarlo dallo storico della scheda '
                . 'o da un backup.');
            return;
        }

        if ($scheda === null) {
            return;
        }

        // Il catalogo dichiarato in scheda deve essere quello in cui la
        // cartella si trova: se divergono, migrazione o spostamento a mano
        // sono rimasti a meta.
        if (strcasecmp((string) $scheda['catasto']['catalogo'], (string) $catalogo['sigla']) !== 0) {
            $aggiungi(self::ERRORE, 'Schede', $codice,
                'La scheda dichiara il catalogo "' . (string) $scheda['catasto']['catalogo']
                . '" ma si trova in "' . (string) $catalogo['sigla'] . '".',
                'Correggere il catalogo in scheda, oppure spostare la cartella.');
        }

        if (strcasecmp((string) $scheda['identificazione']['codice'], $codice) !== 0) {
            $aggiungi(self::ERRORE, 'Schede', $codice,
                'La scheda dichiara il codice "' . (string) $scheda['identificazione']['codice']
                . '" ma la cartella si chiama "' . $codice . '".',
                'Allinearli: e il genere di disallineamento che rende un ipogeo '
                . 'irraggiungibile dai riferimenti.');
        }

        // --- sezioni
        foreach (Sezioni::sigle() as $sigla) {
            $sotto = Percorsi::unisci($cartella, Sezioni::nomeCartella($codice, $sigla));
            if (!is_dir($sotto)) {
                continue;
            }
            $esaminati['sezioni']++;

            self::verificaSezione($codice, $sigla, $sotto, $aggiungi, $esaminati);
        }

        // --- riferimenti alle risorse
        try {
            self::verificaRiferimenti($codice, $aggiungi);
        } catch (Throwable $e) {
            $aggiungi(self::ERRORE, 'Riferimenti', $codice,
                'Il controllo dei riferimenti si è interrotto: ' . $e->getMessage(),
                'Di solito significa che un indice di sezione o un\x27anagrafica non è '
                . 'leggibile: correggerlo e rieseguire la verifica.');
        }
    }

    /**
     * Confronto fra i file presenti e l'indice della sezione.
     *
     * @param callable          $aggiungi
     * @param array<string,int> $esaminati
     */
    private static function verificaSezione(
        string $codice, string $sigla, string $cartella, callable $aggiungi, array &$esaminati
    ): void {
        $indice = Sezioni::nomeIndice($codice, $sigla);
        $percorsoIndice = Percorsi::unisci($cartella, $indice);

        // Le sezioni di soli metadati non hanno file per voce: confrontare
        // file e indice non avrebbe senso.
        $conFileProprio = in_array($sigla, ['AL', 'FO', 'VI', 'RI'], true);

        if (is_file($percorsoIndice)) {
            $errori = Xml::valida(self::caricaSilenzioso($percorsoIndice),
                                  Percorsi::schema(self::xsdDiSezione($sigla)));
            if ($errori !== []) {
                $aggiungi(self::ERRORE, 'Sezioni', $codice . ' / ' . $sigla,
                    'Indice di sezione non valido: ' . implode('; ', array_slice($errori, 0, 2)),
                    'Correggerlo a mano, oppure rimuoverlo e ricaricare i contenuti: '
                    . 'i file restano dove sono.');
            }
        }

        if (!$conFileProprio) {
            return;
        }

        $registrati = [];
        foreach (Risorse::elenco($codice, $sigla) as $risorsa) {
            $registrati[strtolower((string) $risorsa['file'])] = true;

            $percorso = Percorsi::unisci($cartella, (string) $risorsa['file']);
            if (!is_file($percorso)) {
                $aggiungi(self::ERRORE, 'Risorse', $codice . ' / ' . Sezioni::riferimento($sigla, (int) $risorsa['progressivo']),
                    'L\'indice cita un file che non c\'e: ' . (string) $risorsa['file'],
                    'Ripristinare il file da un backup, oppure togliere la voce dall\'indice.');
            }
        }

        foreach (scandir($cartella) ?: [] as $voce) {
            if ($voce === '.' || $voce === '..' || is_dir(Percorsi::unisci($cartella, $voce))) {
                continue;
            }
            if (strcasecmp($voce, $indice) === 0) {
                continue;
            }
            if (str_ends_with($voce, '.tmp') || str_ends_with($voce, '.lock')) {
                continue;
            }
            $esaminati['file']++;

            if (!isset($registrati[strtolower($voce)])) {
                $aggiungi(self::ATTENZIONE, 'Risorse', $codice . ' / ' . $sigla,
                    'File presente ma non registrato nell\'indice: ' . $voce,
                    'Ricaricarlo dall\'interfaccia, oppure aggiungerlo a mano all\'indice. '
                    . 'Cosi com\'e non compare nella scheda.');
            }
        }
    }

    /**
     * Riferimenti fra sezioni: una foto citata da un diario, un allegato citato
     * da una voce bibliografica, e cosi via.
     *
     * @param callable $aggiungi
     */
    private static function verificaRiferimenti(string $codice, callable $aggiungi): void
    {
        $esiste = static function (string $riferimento) use ($codice): bool {
            $parti = Sezioni::scomponiRiferimento($riferimento);

            return $parti !== null
                && Risorse::trova($codice, $parti['sigla'], $parti['progressivo']) !== null;
        };

        // --- diari: foto citate nelle voci
        foreach (Esplorazioni::elenco($codice) as $voce) {
            $diario = Esplorazioni::trova($codice, (int) $voce['progressivo']);
            if ($diario === null) {
                continue;
            }
            foreach ($diario['voci'] as $riga) {
                foreach ((array) $riga['foto'] as $riferimento) {
                    if ((string) $riferimento !== '' && !$esiste((string) $riferimento)) {
                        $aggiungi(self::ATTENZIONE, 'Riferimenti',
                            $codice . ' / ' . Sezioni::riferimento('ES', (int) $voce['progressivo']),
                            'Il diario cita la foto ' . $riferimento . ', che non esiste.',
                            'Togliere il riferimento dalla voce, oppure ricaricare la foto.');
                    }
                }
            }
        }

        // --- bibliografia: allegati e opere citate
        foreach (Bibliografia::elenco($codice) as $fonte) {
            foreach (['allegatoRif', 'copiaArchiviata'] as $campo) {
                $riferimento = (string) $fonte[$campo];
                if ($riferimento !== '' && !$esiste($riferimento)) {
                    $aggiungi(self::ATTENZIONE, 'Riferimenti',
                        $codice . ' / ' . Sezioni::riferimento('BB', (int) $fonte['progressivo']),
                        'La voce cita l\'allegato ' . $riferimento . ', che non esiste.',
                        'Togliere il riferimento, oppure ricaricare l\'allegato.');
                }
            }

            if ((string) $fonte['tipo'] === 'riferimento'
                && (string) $fonte['operaId'] !== ''
                && Opere::trova((string) $fonte['operaId']) === null) {
                $aggiungi(self::ERRORE, 'Riferimenti',
                    $codice . ' / ' . Sezioni::riferimento('BB', (int) $fonte['progressivo']),
                    'La voce cita l\'opera ' . (string) $fonte['operaId']
                    . ', che non è nel catalogo generale.',
                    'Ricensire l\'opera con quell\'identificativo, oppure convertire '
                    . 'la voce in una fonte propria dell\'ipogeo.');
            }
        }

        // --- serie di misure: CSV orfano del descrittore, e viceversa
        $cartellaSc = Scientifici::cartella($codice);
        if ($cartellaSc !== null && is_dir($cartellaSc)) {
            $attesi = [];
            foreach (Scientifici::serie($codice) as $serie) {
                $attesi[strtolower((string) $serie['file'])] = true;

                $csv = Scientifici::percorsoCsv($codice, $serie);
                if ($csv === null || !is_file($csv)) {
                    $aggiungi(self::ERRORE, 'Serie di misure',
                        $codice . ' / ' . Sezioni::riferimento('SC', (int) $serie['progressivo']),
                        'Il descrittore cita un CSV che non c\'e: ' . (string) $serie['file'],
                        'Ripristinare il file, oppure togliere la serie: le letture '
                        . 'non si ricostruiscono da nulla.');
                }
            }

            foreach (scandir($cartellaSc) ?: [] as $voce) {
                if (!str_ends_with(strtolower($voce), '.csv')) {
                    continue;
                }
                if (!isset($attesi[strtolower($voce)])) {
                    $aggiungi(self::ATTENZIONE, 'Serie di misure', $codice,
                        'CSV senza descrittore: ' . $voce,
                        'Creare la serie corrispondente, oppure spostare il file: '
                        . 'così com\'è non è raggiungibile dall\'interfaccia.');
                }
            }
        }

        // --- colonie: CSV dei conteggi
        foreach (Biospeleologia::colonie($codice) as $colonia) {
            $csv = Biospeleologia::percorsoConteggi($codice, $colonia);
            if ((string) $colonia['serieConteggi'] !== '' && ($csv === null || !is_file($csv))) {
                $aggiungi(self::ATTENZIONE, 'Serie di misure',
                    $codice . ' / ' . (string) $colonia['id'],
                    'La colonia cita un file di conteggi che non c\'e: '
                    . (string) $colonia['serieConteggi'],
                    'Ripristinarlo, oppure lasciarlo: verra ricreato vuoto al prossimo '
                    . 'salvataggio della colonia.');
            }
        }
    }

    /** Nome dello schema che valida l'indice di una sezione. */
    private static function xsdDiSezione(string $sigla): string
    {
        return match ($sigla) {
            'ES' => 'esplorazioni-indice.xsd',
            'BB' => 'bibliografia.xsd',
            'SC' => 'scientifici.xsd',
            'BI' => 'biospeleologia.xsd',
            'AR' => 'archeologia.xsd',
            default => 'risorse.xsd',
        };
    }

    /**
     * Carica un XML senza far fallire la verifica se e malformato.
     *
     * Un documento vuoto non passa nessuno schema, quindi il problema viene
     * segnalato lo stesso — ma come "non valido" invece che come eccezione che
     * interrompe l'intera scansione a meta.
     */
    private static function caricaSilenzioso(string $percorso): DOMDocument
    {
        $doc = new DOMDocument();

        $precedente = libxml_use_internal_errors(true);
        @$doc->load($percorso, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);

        return $doc;
    }
}
