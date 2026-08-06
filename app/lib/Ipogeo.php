<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Ipogeo.php
 *  Descrizione ..: Scheda ipogeo: creazione dell'albero di cartelle secondo lo
 *                  standard di nomenclatura, lettura e scrittura di
 *                  "[codice] - Dati.xml", storicizzazione delle revisioni,
 *                  rinomina, cambio di codice e cancellazione conservativa.
 *
 *                  Ogni scrittura passa da qui. Le regole di nomenclatura non
 *                  sono suggerimenti: sono la struttura su cui si regge la
 *                  leggibilita dell'archivio senza l'applicativo, e vengono
 *                  applicate in un unico punto per non divergere.
 *  Versione .....: 0.12.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.0 2026-08-06  D.Candela  IpogeoEccezione spostata in app/lib/IpogeoEccezione.php:
 *                                l'autoload risolve una classe per file.
 *  0.4.0  2026-08-04  D.Candela  Prima stesura (fase 3).
 * ============================================================================
 */

final class Ipogeo
{
    /** Versione dello schema scritta nelle schede nuove. */
    public const VERSIONE_SCHEMA = '1.1';

    /** Stati possibili della scheda. */
    public const STATI_SCHEDA = ['bozza', 'verificata', 'pubblicata'];

    /** Stati possibili dell'accesso. */
    public const STATI_ACCESSO = ['aperto', 'chiuso', 'interrato', 'distrutto', 'non_localizzato'];

    /** Livelli di riservatezza (D12). */
    public const RISERVATEZZE = ['pubblica', 'coordinate_offuscate', 'riservata'];

    /** Metodi di rilevamento delle coordinate. */
    public const METODI_COORDINATE = ['GPS', 'CTR', 'cartografia', 'Google', 'stima'];

    /** Presenza d'acqua. */
    public const PRESENZA_ACQUA = ['assente', 'stagionale', 'permanente', 'allagato'];

    /** Cartella dove finiscono gli ipogei eliminati. */
    public const CARTELLA_ELIMINATI = '_eliminati';

    // ------------------------------------------------------------------- template

    /**
     * Scheda vuota con tutte le sezioni previste.
     *
     * Il template e unico per tutti gli ipogei e contiene sempre ogni sezione,
     * anche vuota: cosi l'archivio resta omogeneo e due schede sono
     * confrontabili riga per riga con un normale diff.
     *
     * @return array<string,mixed>
     */
    public static function template(): array
    {
        return [
            'identificazione' => [
                'codice'         => '',
                'nome'           => '',
                'sinonimi'       => [],
                'natura'         => '',
                'tipologia'      => '',
                'sottotipologia' => '',
                'codiciEsterni'  => [],   // [{ente, catasto, codice}]
                'codiciStorici'  => [],   // [{codice, catalogo, nomeCatalogo, dal, al, motivo, utente}]
            ],
            'ubicazione' => [
                'stato'      => 'IT',
                'statoNome'  => 'Italia',
                'regione'    => '',
                'provincia'  => '',
                'comune'     => '',
                'localita'   => '',
                'indirizzo'  => '',
                'coordinate' => [
                    // Forma canonica: gradi decimali WGS84. E l'unica su cui
                    // lavorano mappa, ricerca per raggio ed esportazioni.
                    'latitudine'      => '',
                    'longitudine'     => '',
                    'quota'           => '',
                    'precisione'      => '',
                    'metodo'          => '',
                    'dataRilevamento' => '',
                    // Memoria di come il dato era stato rilevato: un catasto che
                    // ha misurato in UTM ha misurato in UTM, e conservare solo la
                    // conversione perderebbe cosa fu letto sullo strumento.
                    'sistemaOriginale' => '',
                    'formatoOriginale' => '',
                    'valoreOriginale'  => '',
                ],
                'cartografia' => ['tavolettaIGM' => '', 'sezioneCTR' => ''],
                'accesso'     => [
                    'stato'               => 'aperto',
                    'descrizione'         => '',
                    'proprieta'           => '',
                    'permessiNecessari'   => false,
                    'riferimentoPermessi' => '',
                ],
                'riservatezza' => 'pubblica',
            ],
            'caratteristiche' => [
                'sviluppoPlanimetrico' => '',
                'sviluppoSpaziale'     => '',
                'dislivelloPositivo'   => '',
                'dislivelloNegativo'   => '',
                'profonditaMassima'    => '',
                'numeroIngressi'       => '',
                'ingressi'             => [],  // [{descrizione, latitudine, longitudine, quota, dimensioni, stato}]
                'idrologia'            => ['presenzaAcqua' => '', 'note' => ''],
                'interesse'            => [],
                'percorribilita'       => [
                    'difficolta'              => '',
                    'attrezzaturaNecessaria'  => '',
                    'pericoli'                => '',
                    'tempoPercorrenza'        => '',
                ],
            ],
            'descrizione' => ['sintesi' => '', 'testo' => '', 'storia' => '', 'note' => ''],
            'collegamenti' => [],  // [{codice, relazione}]
            'catasto' => [
                'catalogo'        => '',
                'nomeCatalogo'    => '',
                'serieCodice'     => '',
                'dataCensimento'  => '',
                'censitoDa'       => '',
                'gruppoCensore'   => '',
                'statoScheda'     => 'bozza',
                'creazioneData'   => '',
                'creazioneUtente' => '',
                'modificaData'    => '',
                'modificaUtente'  => '',
                'revisione'       => 0,
            ],
        ];
    }

    // -------------------------------------------------------------------- lettura

    /**
     * Percorso della cartella di un ipogeo, cercandolo in tutti i cataloghi.
     *
     * Si passa dall'indice quando c'e, ma si ricade sulla scansione delle
     * cartelle: un archivio ripristinato a mano da backup puo avere indici
     * disallineati, e la cartella e la fonte di verita.
     */
    public static function cartella(string $codice): ?string
    {
        $codice = trim($codice);
        if ($codice === '') {
            return null;
        }

        // 1. Tentativo rapido dall'indice.
        $riga = IndiceIpogei::trova($codice);
        if ($riga !== null && ($riga['cartella'] ?? '') !== '') {
            $percorso = Percorsi::cataloghi((string) $riga['cartella']);
            if (is_dir($percorso)) {
                return $percorso;
            }
        }

        // 2. Scansione dei cataloghi.
        foreach (Cataloghi::elenco() as $catalogo) {
            $ipogei = Percorsi::unisci(Percorsi::cataloghi((string) $catalogo['cartella']), 'ipogei');
            if (!is_dir($ipogei)) {
                continue;
            }
            foreach (scandir($ipogei) ?: [] as $voce) {
                if ($voce === '.' || $voce === '..') {
                    continue;
                }
                if (strcasecmp(self::codiceDaNomeCartella($voce), $codice) === 0) {
                    $percorso = Percorsi::unisci($ipogei, $voce);
                    if (is_dir($percorso)) {
                        return $percorso;
                    }
                }
            }
        }

        return null;
    }

    /** Percorso del file di scheda di un ipogeo. */
    public static function percorsoDati(string $codice): ?string
    {
        $cartella = self::cartella($codice);
        if ($cartella === null) {
            return null;
        }
        $file = Percorsi::unisci($cartella, $codice . ' - Dati.xml');

        return is_file($file) ? $file : null;
    }

    /**
     * Carica la scheda di un ipogeo.
     *
     * @return array<string,mixed>|null
     */
    public static function trova(string $codice): ?array
    {
        $file = self::percorsoDati($codice);
        if ($file === null) {
            return null;
        }

        $doc     = Xml::carica($file);
        $scheda  = self::leggiScheda($doc);
        $cartella = self::cartella($codice);

        $scheda['_percorso'] = $cartella;
        $scheda['_cartella'] = $cartella === null ? '' : basename($cartella);

        return $scheda;
    }

    /**
     * Risolve un codice anche se storico, restituendo la scheda corrente.
     *
     * @return array{scheda:array<string,mixed>,codiceCorrente:string,eraStorico:bool}|null
     */
    public static function risolvi(string $codice): ?array
    {
        $scheda = self::trova($codice);
        if ($scheda !== null) {
            return ['scheda' => $scheda, 'codiceCorrente' => $codice, 'eraStorico' => false];
        }

        $risoluzione = IndiceCodici::risolvi($codice);
        if ($risoluzione === null || $risoluzione['codice'] === '') {
            return null;
        }

        $scheda = self::trova($risoluzione['codice']);
        if ($scheda === null) {
            return null;
        }

        return [
            'scheda'         => $scheda,
            'codiceCorrente' => $risoluzione['codice'],
            'eraStorico'     => true,
        ];
    }

    // ------------------------------------------------------------------ scrittura

    /**
     * Censisce un ipogeo nuovo: assegna il codice, crea l'albero di cartelle e
     * scrive la scheda.
     *
     * @param  array<string,mixed> $dati scheda parziale, fusa sul template
     * @return string codice assegnato
     * @throws IpogeoEccezione
     */
    public static function crea(string $siglaCatalogo, array $dati): string
    {
        $catalogo = Cataloghi::trova($siglaCatalogo);
        if ($catalogo === null) {
            throw new IpogeoEccezione('Catalogo non trovato: ' . $siglaCatalogo);
        }
        if (!$catalogo['attivo']) {
            throw new IpogeoEccezione('Il catalogo "' . $siglaCatalogo . '" e disattivato: non accetta nuovi censimenti.');
        }

        $scheda = self::fondi(self::template(), $dati);
        self::valida($scheda, true);

        // Il codice: manuale se indicato e consentito, altrimenti dalla serie.
        $codiceManuale = trim((string) ($dati['codiceManuale'] ?? ''));
        $prefissoDaAllineare = '';
        $progressivoDaAllineare = 0;

        if ($codiceManuale !== '') {
            $esito = CodiceCatastale::verificaManuale($siglaCatalogo, $codiceManuale);
            if (!$esito['ok']) {
                throw new IpogeoEccezione($esito['messaggio']);
            }
            $codice = $codiceManuale;
            $prefissoDaAllineare    = $esito['prefisso'];
            $progressivoDaAllineare = $esito['progressivo'];
            $serie = $esito['prefisso'];
        } else {
            $assegnato = CodiceCatastale::assegna($siglaCatalogo, self::attributiPerSerie($scheda));
            $codice = $assegnato['codice'];
            $serie  = $assegnato['prefisso'];
        }

        $nome = trim((string) $scheda['identificazione']['nome']);

        // Cartella dell'ipogeo: "[codice] - [nome]".
        $cartellaIpogei = Percorsi::unisci(Percorsi::cataloghi((string) $catalogo['cartella']), 'ipogei');
        Percorsi::assicuraCartella($cartellaIpogei);

        $nomeCartella = self::nomeCartella($codice, $nome);
        $cartella     = Percorsi::unisci($cartellaIpogei, $nomeCartella);

        if (is_dir($cartella)) {
            throw new IpogeoEccezione('Esiste gia una cartella "' . $nomeCartella . '" nel catalogo.');
        }

        // Completamento dei dati di catasto.
        $adesso = date('Y-m-d\TH:i:s');
        $scheda['identificazione']['codice'] = $codice;
        $scheda['catasto']['catalogo']       = (string) $catalogo['sigla'];
        $scheda['catasto']['nomeCatalogo']   = (string) $catalogo['nome'];
        $scheda['catasto']['serieCodice']    = $serie;
        $scheda['catasto']['creazioneData']  = $adesso;
        $scheda['catasto']['creazioneUtente'] = Auth::usernameCorrente();
        $scheda['catasto']['modificaData']   = $adesso;
        $scheda['catasto']['modificaUtente'] = Auth::usernameCorrente();
        $scheda['catasto']['revisione']      = 1;
        if ($scheda['catasto']['dataCensimento'] === '') {
            $scheda['catasto']['dataCensimento'] = date('Y-m-d');
        }

        // Creazione dell'albero e della scheda. Se qualcosa va storto si
        // rimuove quanto creato: meglio nessun ipogeo che uno a meta.
        try {
            Percorsi::assicuraCartella($cartella);
            foreach (Sezioni::cartelleDiIpogeo($codice) as $sotto) {
                Percorsi::assicuraCartella(Percorsi::unisci($cartella, $sotto));
            }

            $doc = Xml::nuovo('ipogeo', [
                'versioneSchema' => self::VERSIONE_SCHEMA,
                'catalogo'       => (string) $catalogo['sigla'],
            ]);
            self::scriviScheda($doc, $scheda);
            Xml::salva($doc, Percorsi::unisci($cartella, $codice . ' - Dati.xml'), self::xsd());

            IndiceCodici::registra($codice, (string) $catalogo['sigla']);
        } catch (Throwable $e) {
            self::rimuoviAlberoVuoto($cartella, $codice);
            throw new IpogeoEccezione('Censimento non completato: ' . $e->getMessage(), 0, $e);
        }

        // Allineamento del contatore dopo un codice manuale: mai indietro.
        if ($prefissoDaAllineare !== '') {
            CodiceCatastale::allineaDopoManuale($siglaCatalogo, $prefissoDaAllineare, $progressivoDaAllineare);
        }

        IndiceIpogei::aggiorna($codice);
        Log::modifica('crea', (string) $catalogo['sigla'], $codice, 'ipogei', 'censito: ' . $nome);

        return $codice;
    }

    /**
     * Aggiorna la scheda, storicizzando la versione precedente.
     *
     * @param  array<string,mixed> $dati
     * @throws IpogeoEccezione
     */
    public static function aggiorna(string $codice, array $dati): void
    {
        $attuale = self::trova($codice);
        if ($attuale === null) {
            throw new IpogeoEccezione('Ipogeo non trovato: ' . $codice);
        }

        $cartella = (string) $attuale['_percorso'];
        $file     = Percorsi::unisci($cartella, $codice . ' - Dati.xml');

        // Si parte dalla scheda esistente, non dal template: cosi i campi che
        // il form non presenta non vengono azzerati a ogni salvataggio.
        $scheda = self::fondi(self::rimuoviTecnici($attuale), $dati);
        $scheda['identificazione']['codice'] = $codice;  // il codice non si cambia da qui
        self::valida($scheda, false);

        $scheda['catasto']['modificaData']   = date('Y-m-d\TH:i:s');
        $scheda['catasto']['modificaUtente'] = Auth::usernameCorrente();
        $scheda['catasto']['revisione']      = (int) $attuale['catasto']['revisione'] + 1;

        Xml::conLock($file, static function () use ($file, $cartella, $codice, $scheda): void {
            self::storicizza($cartella, $codice);

            $doc = Xml::nuovo('ipogeo', [
                'versioneSchema' => self::VERSIONE_SCHEMA,
                'catalogo'       => (string) $scheda['catasto']['catalogo'],
            ]);
            self::scriviScheda($doc, $scheda);
            Xml::salva($doc, $file, self::xsd());
        });

        // Rinomina della cartella se il nome e cambiato: il nome della cartella
        // fa parte dello standard, non e un'etichetta.
        $nomeNuovo = trim((string) $scheda['identificazione']['nome']);
        if ($nomeNuovo !== trim((string) $attuale['identificazione']['nome'])) {
            self::rinominaCartella($cartella, $codice, $nomeNuovo);
        }

        IndiceIpogei::aggiorna($codice);
        Log::modifica('modifica', (string) $scheda['catasto']['catalogo'], $codice, 'ipogei',
            'revisione ' . $scheda['catasto']['revisione']);
    }

    /**
     * Cambia il codice di un ipogeo: rinomina cartella, sottocartelle e tutti i
     * file, e conserva la memoria del codice precedente.
     *
     * E la macchina che riusera la migrazione fra cataloghi (fase 8b).
     *
     * @throws IpogeoEccezione
     */
    public static function cambiaCodice(string $codice, string $nuovoCodice, string $motivo = 'rinumerazione'): void
    {
        $nuovoCodice = trim($nuovoCodice);

        if (strcasecmp($codice, $nuovoCodice) === 0) {
            return;
        }
        if (!CodiceCatastale::formaValida($nuovoCodice)) {
            throw new IpogeoEccezione('Il nuovo codice contiene caratteri non ammessi.');
        }
        if (IndiceCodici::esiste($nuovoCodice) || CodiceCatastale::cartellaEsistente($nuovoCodice)) {
            throw new IpogeoEccezione('Il codice "' . $nuovoCodice . '" e gia presente in archivio.');
        }

        $scheda = self::trova($codice);
        if ($scheda === null) {
            throw new IpogeoEccezione('Ipogeo non trovato: ' . $codice);
        }

        $cartella = (string) $scheda['_percorso'];
        $catalogo = (string) $scheda['catasto']['catalogo'];

        // 1. Traccia storica nella scheda, prima di toccare il filesystem.
        $scheda['identificazione']['codiciStorici'][] = [
            'codice'       => $codice,
            'catalogo'     => $catalogo,
            'nomeCatalogo' => (string) $scheda['catasto']['nomeCatalogo'],
            'dal'          => (string) ($scheda['catasto']['creazioneData'] !== ''
                ? substr((string) $scheda['catasto']['creazioneData'], 0, 10) : ''),
            'al'           => date('Y-m-d'),
            'motivo'       => $motivo,
            'utente'       => Auth::usernameCorrente(),
        ];
        $scheda['identificazione']['codice'] = $nuovoCodice;

        // 2. Rinomina di tutti i file che portano il vecchio codice.
        self::rinominaContenuto($cartella, $codice, $nuovoCodice);

        // 3. Rinomina della cartella dell'ipogeo.
        $nuovaCartella = self::rinominaCartella($cartella, $nuovoCodice, (string) $scheda['identificazione']['nome'], $codice);

        // 4. Riscrittura della scheda col nuovo nome di file.
        $scheda['catasto']['modificaData']   = date('Y-m-d\TH:i:s');
        $scheda['catasto']['modificaUtente'] = Auth::usernameCorrente();
        $scheda['catasto']['revisione']      = (int) $scheda['catasto']['revisione'] + 1;

        $doc = Xml::nuovo('ipogeo', [
            'versioneSchema' => self::VERSIONE_SCHEMA,
            'catalogo'       => $catalogo,
        ]);
        self::scriviScheda($doc, $scheda);
        Xml::salva($doc, Percorsi::unisci($nuovaCartella, $nuovoCodice . ' - Dati.xml'), self::xsd());

        // 5. Indici: il vecchio codice diventa storico e punta al nuovo.
        IndiceCodici::sostituisci($codice, $nuovoCodice, $catalogo);
        IndiceIpogei::rimuovi($codice);
        IndiceIpogei::aggiorna($nuovoCodice);

        Log::modifica('rinomina', $catalogo, $nuovoCodice, 'ipogei',
            'codice cambiato da ' . $codice . ' (' . $motivo . ')');
    }

    /**
     * Cancellazione conservativa: l'albero dell'ipogeo viene spostato in
     * dati/_eliminati, non rimosso.
     *
     * Nessuna cancellazione ricorsiva: un ipogeo puo contenere anni di foto e
     * rilievi, e un clic sbagliato non deve poterli distruggere. Il codice resta
     * registrato negli indici dei codici, cosi non potra mai essere riassegnato.
     *
     * @return string percorso di destinazione
     * @throws IpogeoEccezione
     */
    public static function elimina(string $codice): string
    {
        $scheda = self::trova($codice);
        if ($scheda === null) {
            throw new IpogeoEccezione('Ipogeo non trovato: ' . $codice);
        }

        $cartella = (string) $scheda['_percorso'];
        $catalogo = (string) $scheda['catasto']['catalogo'];

        $deposito = Percorsi::dati(self::CARTELLA_ELIMINATI);
        Percorsi::assicuraCartella($deposito);
        Percorsi::proteggiCartella($deposito);

        $destinazione = Percorsi::unisci($deposito, date('Ymd-His') . ' ' . basename($cartella));
        if (is_dir($destinazione)) {
            $destinazione .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        if (!@rename($cartella, $destinazione)) {
            throw new IpogeoEccezione(
                'Spostamento in _eliminati non riuscito: verificare che la cartella non sia aperta da un altro programma.'
            );
        }

        IndiceIpogei::rimuovi($codice);
        Log::modifica('elimina', $catalogo, $codice, 'ipogei', 'spostato in ' . self::CARTELLA_ELIMINATI);

        return $destinazione;
    }

    /**
     * Elenco delle revisioni storicizzate di una scheda, dalla piu recente.
     *
     * @return array<int,array{file:string,percorso:string,data:string,dimensione:int}>
     */
    public static function storico(string $codice): array
    {
        $cartella = self::cartella($codice);
        if ($cartella === null) {
            return [];
        }

        $storico = Percorsi::unisci($cartella, $codice . ' - ' . Sezioni::STORICO);
        if (!is_dir($storico)) {
            return [];
        }

        $revisioni = [];
        foreach (scandir($storico) ?: [] as $voce) {
            if (!preg_match('/ - Dati\.([0-9]{8})-([0-9]{6})\.xml$/', $voce, $parti)) {
                continue;
            }
            $percorso = Percorsi::unisci($storico, $voce);
            $revisioni[] = [
                'file'       => $voce,
                'percorso'   => $percorso,
                'data'       => substr($parti[1], 0, 4) . '-' . substr($parti[1], 4, 2) . '-' . substr($parti[1], 6, 2)
                              . ' ' . substr($parti[2], 0, 2) . ':' . substr($parti[2], 2, 2) . ':' . substr($parti[2], 4, 2),
                'dimensione' => (int) @filesize($percorso),
            ];
        }

        usort($revisioni, static fn (array $a, array $b): int => strcmp($b['file'], $a['file']));

        return $revisioni;
    }

    // ------------------------------------------------------------------ nomenclatura

    /** Nome normativo della cartella di un ipogeo: "[codice] - [nome]". */
    public static function nomeCartella(string $codice, string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            $nome = 'senza nome';
        }
        // Il codice non passa dalla sanitizzazione: e gia validato per forma e
        // deve restare identico a quello scritto nei file.
        return $codice . ' - ' . Testo::nomeFileSicuro($nome, false, 120);
    }

    /** Estrae il codice dal nome di una cartella "[codice] - [nome]". */
    public static function codiceDaNomeCartella(string $nomeCartella): string
    {
        $posizione = strpos($nomeCartella, ' - ');
        return trim($posizione === false ? $nomeCartella : substr($nomeCartella, 0, $posizione));
    }

    /**
     * Attributi della scheda su cui le serie di codifica discriminano.
     *
     * @param  array<string,mixed> $scheda
     * @return array<string,string>
     */
    public static function attributiPerSerie(array $scheda): array
    {
        return [
            'natura'         => (string) ($scheda['identificazione']['natura'] ?? ''),
            'tipologia'      => (string) ($scheda['identificazione']['tipologia'] ?? ''),
            'sottotipologia' => (string) ($scheda['identificazione']['sottotipologia'] ?? ''),
            'stato'          => (string) ($scheda['ubicazione']['stato'] ?? ''),
            'regione'        => (string) ($scheda['ubicazione']['regione'] ?? ''),
            'provincia'      => (string) ($scheda['ubicazione']['provincia'] ?? ''),
        ];
    }

    // ------------------------------------------------------------------- validazione

    /**
     * @param  array<string,mixed> $scheda
     * @throws IpogeoEccezione
     */
    public static function valida(array $scheda, bool $inCreazione): void
    {
        $nome = trim((string) ($scheda['identificazione']['nome'] ?? ''));
        if ($nome === '') {
            throw new IpogeoEccezione('Il nome dell\'ipogeo e obbligatorio.');
        }

        $natura = trim((string) ($scheda['identificazione']['natura'] ?? ''));
        if ($natura === '') {
            throw new IpogeoEccezione('La natura (artificiale o naturale) e obbligatoria.');
        }
        if (Tipologie::trova($natura) === null) {
            throw new IpogeoEccezione('Natura non presente in tassonomia: ' . $natura);
        }

        $tipologia = trim((string) ($scheda['identificazione']['tipologia'] ?? ''));
        if ($tipologia === '') {
            throw new IpogeoEccezione('La tipologia e obbligatoria.');
        }
        if (Tipologie::trova($tipologia) === null) {
            throw new IpogeoEccezione('Tipologia non presente in tassonomia: ' . $tipologia);
        }

        $sotto = trim((string) ($scheda['identificazione']['sottotipologia'] ?? ''));
        if ($sotto !== '' && Tipologie::trova($sotto) === null) {
            throw new IpogeoEccezione('Sottotipologia non presente in tassonomia: ' . $sotto);
        }

        // Coordinate: obbligatorie e nei limiti terrestri.
        $lat = self::numero($scheda['ubicazione']['coordinate']['latitudine'] ?? '');
        $lon = self::numero($scheda['ubicazione']['coordinate']['longitudine'] ?? '');

        if ($lat === null || $lon === null) {
            throw new IpogeoEccezione(
                'Latitudine e longitudine sono obbligatorie: senza posizione l\'ipogeo non comparirebbe '
                . 'ne in mappa ne nelle ricerche per area.'
            );
        }
        if ($lat < -90.0 || $lat > 90.0) {
            throw new IpogeoEccezione('Latitudine fuori intervallo: deve stare fra -90 e 90.');
        }
        if ($lon < -180.0 || $lon > 180.0) {
            throw new IpogeoEccezione('Longitudine fuori intervallo: deve stare fra -180 e 180.');
        }

        $riservatezza = (string) ($scheda['ubicazione']['riservatezza'] ?? 'pubblica');
        if (!in_array($riservatezza, self::RISERVATEZZE, true)) {
            throw new IpogeoEccezione('Livello di riservatezza non valido.');
        }

        $statoAccesso = (string) ($scheda['ubicazione']['accesso']['stato'] ?? 'aperto');
        if ($statoAccesso !== '' && !in_array($statoAccesso, self::STATI_ACCESSO, true)) {
            throw new IpogeoEccezione('Stato di accesso non valido.');
        }

        $statoScheda = (string) ($scheda['catasto']['statoScheda'] ?? 'bozza');
        if (!in_array($statoScheda, self::STATI_SCHEDA, true)) {
            throw new IpogeoEccezione('Stato della scheda non valido.');
        }

        $stato = strtoupper(trim((string) ($scheda['ubicazione']['stato'] ?? '')));
        if ($stato !== '' && !preg_match('/^[A-Z]{2}$/', $stato)) {
            throw new IpogeoEccezione('Il codice di stato va indicato con due lettere (ISO 3166-1).');
        }

        foreach (['sviluppoPlanimetrico', 'sviluppoSpaziale', 'profonditaMassima'] as $campo) {
            $valore = self::numero($scheda['caratteristiche'][$campo] ?? '');
            if ($valore !== null && $valore < 0) {
                throw new IpogeoEccezione('Le misure di sviluppo e profondita non possono essere negative.');
            }
        }
    }

    // --------------------------------------------------------------------- interni

    /** Percorso dello schema, se presente. */
    private static function xsd(): ?string
    {
        $p = Percorsi::schema('ipogeo.xsd');
        return is_file($p) ? $p : null;
    }

    /**
     * Copia la scheda corrente nello storico, con rotazione.
     *
     * @throws IpogeoEccezione
     */
    private static function storicizza(string $cartella, string $codice): void
    {
        $file = Percorsi::unisci($cartella, $codice . ' - Dati.xml');
        if (!is_file($file)) {
            return;
        }

        $storico = Percorsi::unisci($cartella, $codice . ' - ' . Sezioni::STORICO);
        Percorsi::assicuraCartella($storico);

        $destinazione = Percorsi::unisci($storico, $codice . ' - Dati.' . date('Ymd-His') . '.xml');

        // Due salvataggi nello stesso secondo non devono sovrascriversi.
        $tentativo = 1;
        while (is_file($destinazione) && $tentativo < 100) {
            $destinazione = Percorsi::unisci($storico, $codice . ' - Dati.' . date('Ymd-His') . '-' . $tentativo . '.xml');
            $tentativo++;
        }

        if (!@copy($file, $destinazione)) {
            throw new IpogeoEccezione('Storicizzazione non riuscita: la revisione precedente non e stata copiata.');
        }

        self::ruotaStorico($storico);
    }

    /** Mantiene solo le ultime N revisioni, come da configurazione. */
    private static function ruotaStorico(string $storico): void
    {
        $massimo = Config::caricata() ? Config::intero('sistema.versioniStorico', 20) : 20;
        if ($massimo <= 0) {
            return;
        }

        $file = [];
        foreach (scandir($storico) ?: [] as $voce) {
            if (preg_match('/ - Dati\.[0-9]{8}-[0-9]{6}(-[0-9]+)?\.xml$/', $voce)) {
                $file[] = $voce;
            }
        }
        if (count($file) <= $massimo) {
            return;
        }

        sort($file); // ordine cronologico: il nome contiene la data
        $daRimuovere = array_slice($file, 0, count($file) - $massimo);
        foreach ($daRimuovere as $voce) {
            @unlink(Percorsi::unisci($storico, $voce));
        }
    }

    /**
     * Rinomina la cartella di un ipogeo.
     *
     * @param  string      $codice       codice da usare nel nuovo nome
     * @param  string|null $codiceVecchio se diverso, e un cambio di codice
     * @return string nuovo percorso
     * @throws IpogeoEccezione
     */
    private static function rinominaCartella(string $cartella, string $codice, string $nome, ?string $codiceVecchio = null): string
    {
        $genitore = dirname($cartella);
        $nuovo    = Percorsi::unisci($genitore, self::nomeCartella($codice, $nome));

        if ($nuovo === str_replace('\\', '/', $cartella)) {
            return $cartella;
        }
        if (is_dir($nuovo)) {
            throw new IpogeoEccezione('Esiste gia una cartella con il nome di destinazione.');
        }
        if (!@rename($cartella, $nuovo)) {
            throw new IpogeoEccezione(
                'La scheda e stata salvata, ma la cartella non e stata rinominata: '
                . 'verificare che non sia aperta da un altro programma.'
            );
        }

        return $nuovo;
    }

    /**
     * Rinomina sottocartelle e file che portano il vecchio codice.
     *
     * Si procede dal basso verso l'alto: prima i file, poi le cartelle che li
     * contengono, altrimenti i percorsi cambierebbero sotto i piedi.
     *
     * @throws IpogeoEccezione
     */
    private static function rinominaContenuto(string $cartella, string $codiceVecchio, string $codiceNuovo): void
    {
        $sottocartelle = [];
        foreach (scandir($cartella) ?: [] as $voce) {
            if ($voce === '.' || $voce === '..') {
                continue;
            }
            $percorso = Percorsi::unisci($cartella, $voce);
            if (is_dir($percorso)) {
                $sottocartelle[] = $voce;
            } else {
                self::rinominaSeContiene($cartella, $voce, $codiceVecchio, $codiceNuovo);
            }
        }

        foreach ($sottocartelle as $sotto) {
            $percorso = Percorsi::unisci($cartella, $sotto);

            foreach (scandir($percorso) ?: [] as $voce) {
                if ($voce === '.' || $voce === '..') {
                    continue;
                }
                $interno = Percorsi::unisci($percorso, $voce);
                if (is_dir($interno)) {
                    // Un solo livello ulteriore, le miniature: piu profondita
                    // non e prevista dallo standard.
                    foreach (scandir($interno) ?: [] as $foglia) {
                        if ($foglia !== '.' && $foglia !== '..' && !is_dir(Percorsi::unisci($interno, $foglia))) {
                            self::rinominaSeContiene($interno, $foglia, $codiceVecchio, $codiceNuovo);
                        }
                    }
                } else {
                    self::rinominaSeContiene($percorso, $voce, $codiceVecchio, $codiceNuovo);
                }
            }

            self::rinominaSeContiene($cartella, $sotto, $codiceVecchio, $codiceNuovo);
        }
    }

    /**
     * Rinomina una voce sostituendo il codice, se lo contiene.
     *
     * @throws IpogeoEccezione
     */
    private static function rinominaSeContiene(string $genitore, string $voce, string $codiceVecchio, string $codiceNuovo): void
    {
        if (stripos($voce, $codiceVecchio) !== 0) {
            return; // il codice sta sempre in testa al nome
        }

        $nuovo = $codiceNuovo . substr($voce, strlen($codiceVecchio));
        if ($nuovo === $voce) {
            return;
        }

        $da = Percorsi::unisci($genitore, $voce);
        $a  = Percorsi::unisci($genitore, $nuovo);

        if (file_exists($a)) {
            throw new IpogeoEccezione('Cambio di codice interrotto: "' . $nuovo . '" esiste gia.');
        }
        if (!@rename($da, $a)) {
            throw new IpogeoEccezione('Cambio di codice interrotto: impossibile rinominare "' . $voce . '".');
        }
    }

    /**
     * Rimuove l'albero appena creato, solo se contiene esclusivamente le
     * cartelle previste e nessun file estraneo. Serve a non lasciare residui
     * dopo un censimento fallito, senza rischiare di cancellare dati.
     */
    private static function rimuoviAlberoVuoto(string $cartella, string $codice): void
    {
        if (!is_dir($cartella)) {
            return;
        }

        $previste = Sezioni::cartelleDiIpogeo($codice);

        foreach (scandir($cartella) ?: [] as $voce) {
            if ($voce === '.' || $voce === '..') {
                continue;
            }
            $percorso = Percorsi::unisci($cartella, $voce);

            if (is_dir($percorso)) {
                if (!in_array($voce, $previste, true)) {
                    return; // cartella non prevista: non si tocca niente
                }
                if (count(array_diff(scandir($percorso) ?: [], ['.', '..'])) > 0) {
                    return; // contiene qualcosa
                }
            } elseif (!str_ends_with($voce, ' - Dati.xml') && !str_ends_with($voce, '.tmp') && !str_ends_with($voce, '.lock')) {
                return; // file estraneo
            }
        }

        foreach (scandir($cartella) ?: [] as $voce) {
            if ($voce === '.' || $voce === '..') {
                continue;
            }
            $percorso = Percorsi::unisci($cartella, $voce);
            if (is_dir($percorso)) {
                @rmdir($percorso);
            } else {
                @unlink($percorso);
            }
        }
        @rmdir($cartella);
    }

    /**
     * Fonde dati parziali su una struttura completa, preservando le chiavi
     * assenti. Le liste vengono sostituite in blocco, non fuse elemento per
     * elemento: togliere una voce da un elenco deve poter funzionare.
     *
     * @param  array<string,mixed> $base
     * @param  array<string,mixed> $nuovi
     * @return array<string,mixed>
     */
    private static function fondi(array $base, array $nuovi): array
    {
        foreach ($nuovi as $chiave => $valore) {
            if (!array_key_exists($chiave, $base)) {
                continue; // chiave non prevista dal template: ignorata
            }
            if (is_array($base[$chiave]) && is_array($valore) && !self::eLista($base[$chiave])) {
                $base[$chiave] = self::fondi($base[$chiave], $valore);
            } else {
                $base[$chiave] = $valore;
            }
        }
        return $base;
    }

    /** True se l'array e una lista (indici numerici) e non una mappa. */
    private static function eLista(array $valore): bool
    {
        return $valore === [] || array_keys($valore) === range(0, count($valore) - 1);
    }

    /**
     * Togli le chiavi tecniche aggiunte alla lettura.
     *
     * @param  array<string,mixed> $scheda
     * @return array<string,mixed>
     */
    private static function rimuoviTecnici(array $scheda): array
    {
        unset($scheda['_percorso'], $scheda['_cartella']);
        return $scheda;
    }

    /** Converte in float, restituendo null per i valori vuoti o non numerici. */
    private static function numero(mixed $valore): ?float
    {
        $testo = trim((string) $valore);
        if ($testo === '') {
            return null;
        }
        $testo = str_replace(',', '.', $testo);
        return is_numeric($testo) ? (float) $testo : null;
    }

    // ---------------------------------------------------------------- XML: scrittura

    /**
     * Scrive la scheda nel documento.
     *
     * @param array<string,mixed> $s
     */
    private static function scriviScheda(DOMDocument $doc, array $s): void
    {
        $radice = $doc->documentElement;
        if ($radice === null) {
            throw new IpogeoEccezione('Documento senza elemento radice.');
        }

        // -------------------------------------------------- identificazione
        $ident = Xml::aggiungi($radice, 'identificazione');
        Xml::imposta($ident, 'codice', (string) $s['identificazione']['codice']);
        Xml::imposta($ident, 'nome', (string) $s['identificazione']['nome']);

        $sinonimi = Xml::aggiungi($ident, 'sinonimi');
        foreach ((array) $s['identificazione']['sinonimi'] as $sinonimo) {
            $valore = trim((string) $sinonimo);
            if ($valore !== '') {
                Xml::aggiungi($sinonimi, 'sinonimo', $valore);
            }
        }

        Xml::imposta($ident, 'natura', (string) $s['identificazione']['natura']);
        Xml::imposta($ident, 'tipologia', (string) $s['identificazione']['tipologia']);
        Xml::imposta($ident, 'sottotipologia', (string) $s['identificazione']['sottotipologia']);

        $esterni = Xml::aggiungi($ident, 'codiciEsterni');
        foreach ((array) $s['identificazione']['codiciEsterni'] as $voce) {
            $codice = trim((string) ($voce['codice'] ?? ''));
            if ($codice === '') {
                continue;
            }
            Xml::aggiungi($esterni, 'codiceEsterno', $codice, [
                'ente'    => trim((string) ($voce['ente'] ?? '')),
                'catasto' => trim((string) ($voce['catasto'] ?? '')),
            ]);
        }

        $storici = Xml::aggiungi($ident, 'codiciStorici');
        foreach ((array) $s['identificazione']['codiciStorici'] as $voce) {
            $codice = trim((string) ($voce['codice'] ?? ''));
            if ($codice === '') {
                continue;
            }
            Xml::aggiungi($storici, 'codiceStorico', null, [
                'codice'       => $codice,
                'catalogo'     => trim((string) ($voce['catalogo'] ?? '')),
                'nomeCatalogo' => trim((string) ($voce['nomeCatalogo'] ?? '')),
                'dal'          => trim((string) ($voce['dal'] ?? '')),
                'al'           => trim((string) ($voce['al'] ?? '')),
                'motivo'       => trim((string) ($voce['motivo'] ?? '')),
                'utente'       => trim((string) ($voce['utente'] ?? '')),
            ]);
        }

        // ------------------------------------------------------- ubicazione
        $ub = Xml::aggiungi($radice, 'ubicazione');
        Xml::aggiungi($ub, 'stato', (string) $s['ubicazione']['statoNome'], [
            'codice' => strtoupper((string) $s['ubicazione']['stato']),
        ]);
        foreach (['regione', 'provincia', 'comune', 'localita', 'indirizzo'] as $campo) {
            Xml::imposta($ub, $campo, (string) $s['ubicazione'][$campo]);
        }

        $coord = Xml::aggiungi($ub, 'coordinate', null, ['sistema' => 'EPSG:4326']);
        Xml::imposta($coord, 'latitudine', (string) $s['ubicazione']['coordinate']['latitudine']);
        Xml::imposta($coord, 'longitudine', (string) $s['ubicazione']['coordinate']['longitudine']);
        Xml::aggiungi($coord, 'quota', (string) $s['ubicazione']['coordinate']['quota'], ['unita' => 'm']);
        Xml::aggiungi($coord, 'precisione', (string) $s['ubicazione']['coordinate']['precisione'], ['unita' => 'm']);
        Xml::imposta($coord, 'metodo', (string) $s['ubicazione']['coordinate']['metodo']);
        Xml::imposta($coord, 'dataRilevamento', (string) $s['ubicazione']['coordinate']['dataRilevamento']);
        Xml::imposta($coord, 'sistemaOriginale', (string) $s['ubicazione']['coordinate']['sistemaOriginale']);
        Xml::imposta($coord, 'formatoOriginale', (string) $s['ubicazione']['coordinate']['formatoOriginale']);
        Xml::imposta($coord, 'valoreOriginale', (string) $s['ubicazione']['coordinate']['valoreOriginale']);

        $carto = Xml::aggiungi($ub, 'cartografia');
        Xml::imposta($carto, 'tavolettaIGM', (string) $s['ubicazione']['cartografia']['tavolettaIGM']);
        Xml::imposta($carto, 'sezioneCTR', (string) $s['ubicazione']['cartografia']['sezioneCTR']);

        $acc = Xml::aggiungi($ub, 'accesso');
        Xml::imposta($acc, 'stato', (string) $s['ubicazione']['accesso']['stato']);
        Xml::imposta($acc, 'descrizione', (string) $s['ubicazione']['accesso']['descrizione'], true);
        Xml::imposta($acc, 'proprieta', (string) $s['ubicazione']['accesso']['proprieta']);
        Xml::imposta($acc, 'permessiNecessari', !empty($s['ubicazione']['accesso']['permessiNecessari']) ? '1' : '0');
        Xml::imposta($acc, 'riferimentoPermessi', (string) $s['ubicazione']['accesso']['riferimentoPermessi'], true);

        Xml::imposta($ub, 'riservatezza', (string) $s['ubicazione']['riservatezza']);

        // -------------------------------------------------- caratteristiche
        $car = Xml::aggiungi($radice, 'caratteristiche');
        foreach (['sviluppoPlanimetrico', 'sviluppoSpaziale', 'dislivelloPositivo',
                  'dislivelloNegativo', 'profonditaMassima'] as $campo) {
            Xml::aggiungi($car, $campo, (string) $s['caratteristiche'][$campo], ['unita' => 'm']);
        }
        Xml::imposta($car, 'numeroIngressi', (string) $s['caratteristiche']['numeroIngressi']);

        $ingressi = Xml::aggiungi($car, 'ingressi');
        $n = 0;
        foreach ((array) $s['caratteristiche']['ingressi'] as $ingresso) {
            $descrizione = trim((string) ($ingresso['descrizione'] ?? ''));
            $lat         = trim((string) ($ingresso['latitudine'] ?? ''));
            if ($descrizione === '' && $lat === '') {
                continue;
            }
            $n++;
            $nodo = Xml::aggiungi($ingressi, 'ingresso', null, ['n' => (string) $n]);
            Xml::imposta($nodo, 'descrizione', $descrizione, true);
            Xml::imposta($nodo, 'latitudine', $lat);
            Xml::imposta($nodo, 'longitudine', trim((string) ($ingresso['longitudine'] ?? '')));
            Xml::aggiungi($nodo, 'quota', trim((string) ($ingresso['quota'] ?? '')), ['unita' => 'm']);
            Xml::imposta($nodo, 'dimensioni', trim((string) ($ingresso['dimensioni'] ?? '')));
            Xml::imposta($nodo, 'stato', trim((string) ($ingresso['stato'] ?? '')));
        }

        $idro = Xml::aggiungi($car, 'idrologia');
        Xml::imposta($idro, 'presenzaAcqua', (string) $s['caratteristiche']['idrologia']['presenzaAcqua']);
        Xml::imposta($idro, 'note', (string) $s['caratteristiche']['idrologia']['note'], true);

        $interesse = Xml::aggiungi($car, 'interesse');
        foreach ((array) $s['caratteristiche']['interesse'] as $voce) {
            $valore = trim((string) $voce);
            if ($valore !== '') {
                Xml::aggiungi($interesse, 'voce', $valore);
            }
        }

        $perc = Xml::aggiungi($car, 'percorribilita');
        foreach (['difficolta', 'attrezzaturaNecessaria', 'pericoli', 'tempoPercorrenza'] as $campo) {
            Xml::imposta($perc, $campo, (string) $s['caratteristiche']['percorribilita'][$campo], true);
        }

        // ------------------------------------------------------ descrizione
        // Nessun limite di lunghezza (D6): tutto in CDATA, nessun troncamento.
        $desc = Xml::aggiungi($radice, 'descrizione');
        foreach (['sintesi', 'testo', 'storia', 'note'] as $campo) {
            Xml::imposta($desc, $campo, (string) $s['descrizione'][$campo], true);
        }

        // ----------------------------------------------------- collegamenti
        $coll = Xml::aggiungi($radice, 'collegamenti');
        foreach ((array) $s['collegamenti'] as $voce) {
            $codice = trim((string) ($voce['codice'] ?? ''));
            if ($codice === '') {
                continue;
            }
            Xml::aggiungi($coll, 'ipogeoCorrelato', null, [
                'codice'    => $codice,
                'relazione' => trim((string) ($voce['relazione'] ?? '')),
            ]);
        }

        // ---------------------------------------------------------- catasto
        $cat = Xml::aggiungi($radice, 'catasto');
        Xml::aggiungi($cat, 'catalogo', null, [
            'sigla' => (string) $s['catasto']['catalogo'],
            'nome'  => (string) $s['catasto']['nomeCatalogo'],
        ]);
        Xml::aggiungi($cat, 'serieCodice', null, ['prefisso' => (string) $s['catasto']['serieCodice']]);
        Xml::imposta($cat, 'dataCensimento', (string) $s['catasto']['dataCensimento']);
        Xml::aggiungi($cat, 'censitoDa', null, ['esploratoreId' => (string) $s['catasto']['censitoDa']]);
        Xml::aggiungi($cat, 'gruppoCensore', null, ['id' => (string) $s['catasto']['gruppoCensore']]);
        Xml::imposta($cat, 'statoScheda', (string) $s['catasto']['statoScheda']);
        Xml::aggiungi($cat, 'creazione', (string) $s['catasto']['creazioneData'], [
            'utente' => (string) $s['catasto']['creazioneUtente'],
        ]);
        Xml::aggiungi($cat, 'ultimaModifica', (string) $s['catasto']['modificaData'], [
            'utente' => (string) $s['catasto']['modificaUtente'],
        ]);
        Xml::imposta($cat, 'revisione', (string) $s['catasto']['revisione']);
    }

    // ------------------------------------------------------------- XML: lettura

    /**
     * Legge la scheda dal documento, fondendola sul template: una scheda
     * scritta da una versione precedente resta leggibile, e i campi nuovi
     * arrivano col proprio valore vuoto.
     *
     * @return array<string,mixed>
     */
    private static function leggiScheda(DOMDocument $doc): array
    {
        $s = self::template();

        // -------------------------------------------------- identificazione
        $s['identificazione']['codice']         = Xml::testo($doc, '/ipogeo/identificazione/codice');
        $s['identificazione']['nome']           = Xml::testo($doc, '/ipogeo/identificazione/nome');
        $s['identificazione']['natura']         = Xml::testo($doc, '/ipogeo/identificazione/natura');
        $s['identificazione']['tipologia']      = Xml::testo($doc, '/ipogeo/identificazione/tipologia');
        $s['identificazione']['sottotipologia'] = Xml::testo($doc, '/ipogeo/identificazione/sottotipologia');

        foreach (Xml::elenco($doc, '/ipogeo/identificazione/sinonimi/sinonimo') as $nodo) {
            $s['identificazione']['sinonimi'][] = trim($nodo->textContent);
        }
        foreach (Xml::elenco($doc, '/ipogeo/identificazione/codiciEsterni/codiceEsterno') as $nodo) {
            $s['identificazione']['codiciEsterni'][] = [
                'ente'    => $nodo->getAttribute('ente'),
                'catasto' => $nodo->getAttribute('catasto'),
                'codice'  => trim($nodo->textContent),
            ];
        }
        foreach (Xml::elenco($doc, '/ipogeo/identificazione/codiciStorici/codiceStorico') as $nodo) {
            $s['identificazione']['codiciStorici'][] = [
                'codice'       => $nodo->getAttribute('codice'),
                'catalogo'     => $nodo->getAttribute('catalogo'),
                'nomeCatalogo' => $nodo->getAttribute('nomeCatalogo'),
                'dal'          => $nodo->getAttribute('dal'),
                'al'           => $nodo->getAttribute('al'),
                'motivo'       => $nodo->getAttribute('motivo'),
                'utente'       => $nodo->getAttribute('utente'),
            ];
        }

        // ------------------------------------------------------- ubicazione
        $stato = Xml::primo($doc, '/ipogeo/ubicazione/stato');
        if ($stato instanceof DOMElement) {
            $s['ubicazione']['stato']     = $stato->getAttribute('codice') ?: 'IT';
            $s['ubicazione']['statoNome'] = trim($stato->textContent);
        }
        foreach (['regione', 'provincia', 'comune', 'localita', 'indirizzo'] as $campo) {
            $s['ubicazione'][$campo] = Xml::testo($doc, '/ipogeo/ubicazione/' . $campo);
        }
        foreach (['latitudine', 'longitudine', 'quota', 'precisione', 'metodo', 'dataRilevamento',
                  'sistemaOriginale', 'formatoOriginale', 'valoreOriginale'] as $campo) {
            $s['ubicazione']['coordinate'][$campo] = Xml::testo($doc, '/ipogeo/ubicazione/coordinate/' . $campo);
        }
        $s['ubicazione']['cartografia']['tavolettaIGM'] = Xml::testo($doc, '/ipogeo/ubicazione/cartografia/tavolettaIGM');
        $s['ubicazione']['cartografia']['sezioneCTR']   = Xml::testo($doc, '/ipogeo/ubicazione/cartografia/sezioneCTR');

        $s['ubicazione']['accesso']['stato']               = Xml::testo($doc, '/ipogeo/ubicazione/accesso/stato', 'aperto');
        $s['ubicazione']['accesso']['descrizione']         = Xml::testo($doc, '/ipogeo/ubicazione/accesso/descrizione');
        $s['ubicazione']['accesso']['proprieta']           = Xml::testo($doc, '/ipogeo/ubicazione/accesso/proprieta');
        $s['ubicazione']['accesso']['permessiNecessari']   = Xml::booleano($doc, '/ipogeo/ubicazione/accesso/permessiNecessari', false);
        $s['ubicazione']['accesso']['riferimentoPermessi'] = Xml::testo($doc, '/ipogeo/ubicazione/accesso/riferimentoPermessi');

        $s['ubicazione']['riservatezza'] = Xml::testo($doc, '/ipogeo/ubicazione/riservatezza', 'pubblica');

        // -------------------------------------------------- caratteristiche
        foreach (['sviluppoPlanimetrico', 'sviluppoSpaziale', 'dislivelloPositivo',
                  'dislivelloNegativo', 'profonditaMassima', 'numeroIngressi'] as $campo) {
            $s['caratteristiche'][$campo] = Xml::testo($doc, '/ipogeo/caratteristiche/' . $campo);
        }
        foreach (Xml::elenco($doc, '/ipogeo/caratteristiche/ingressi/ingresso') as $nodo) {
            $s['caratteristiche']['ingressi'][] = [
                'descrizione' => Xml::testo($nodo, 'descrizione'),
                'latitudine'  => Xml::testo($nodo, 'latitudine'),
                'longitudine' => Xml::testo($nodo, 'longitudine'),
                'quota'       => Xml::testo($nodo, 'quota'),
                'dimensioni'  => Xml::testo($nodo, 'dimensioni'),
                'stato'       => Xml::testo($nodo, 'stato'),
            ];
        }
        $s['caratteristiche']['idrologia']['presenzaAcqua'] = Xml::testo($doc, '/ipogeo/caratteristiche/idrologia/presenzaAcqua');
        $s['caratteristiche']['idrologia']['note']          = Xml::testo($doc, '/ipogeo/caratteristiche/idrologia/note');

        foreach (Xml::elenco($doc, '/ipogeo/caratteristiche/interesse/voce') as $nodo) {
            $s['caratteristiche']['interesse'][] = trim($nodo->textContent);
        }
        foreach (['difficolta', 'attrezzaturaNecessaria', 'pericoli', 'tempoPercorrenza'] as $campo) {
            $s['caratteristiche']['percorribilita'][$campo] = Xml::testo($doc, '/ipogeo/caratteristiche/percorribilita/' . $campo);
        }

        // ------------------------------------------------------ descrizione
        foreach (['sintesi', 'testo', 'storia', 'note'] as $campo) {
            $s['descrizione'][$campo] = Xml::testo($doc, '/ipogeo/descrizione/' . $campo);
        }

        // ----------------------------------------------------- collegamenti
        foreach (Xml::elenco($doc, '/ipogeo/collegamenti/ipogeoCorrelato') as $nodo) {
            $s['collegamenti'][] = [
                'codice'    => $nodo->getAttribute('codice'),
                'relazione' => $nodo->getAttribute('relazione'),
            ];
        }

        // ---------------------------------------------------------- catasto
        $catalogo = Xml::primo($doc, '/ipogeo/catasto/catalogo');
        if ($catalogo instanceof DOMElement) {
            $s['catasto']['catalogo']     = $catalogo->getAttribute('sigla');
            $s['catasto']['nomeCatalogo'] = $catalogo->getAttribute('nome');
        }
        $serie = Xml::primo($doc, '/ipogeo/catasto/serieCodice');
        if ($serie instanceof DOMElement) {
            $s['catasto']['serieCodice'] = $serie->getAttribute('prefisso');
        }
        $s['catasto']['dataCensimento'] = Xml::testo($doc, '/ipogeo/catasto/dataCensimento');

        $censitoDa = Xml::primo($doc, '/ipogeo/catasto/censitoDa');
        if ($censitoDa instanceof DOMElement) {
            $s['catasto']['censitoDa'] = $censitoDa->getAttribute('esploratoreId');
        }
        $gruppo = Xml::primo($doc, '/ipogeo/catasto/gruppoCensore');
        if ($gruppo instanceof DOMElement) {
            $s['catasto']['gruppoCensore'] = $gruppo->getAttribute('id');
        }

        $s['catasto']['statoScheda'] = Xml::testo($doc, '/ipogeo/catasto/statoScheda', 'bozza');

        $creazione = Xml::primo($doc, '/ipogeo/catasto/creazione');
        if ($creazione instanceof DOMElement) {
            $s['catasto']['creazioneData']   = trim($creazione->textContent);
            $s['catasto']['creazioneUtente'] = $creazione->getAttribute('utente');
        }
        $modifica = Xml::primo($doc, '/ipogeo/catasto/ultimaModifica');
        if ($modifica instanceof DOMElement) {
            $s['catasto']['modificaData']   = trim($modifica->textContent);
            $s['catasto']['modificaUtente'] = $modifica->getAttribute('utente');
        }
        $s['catasto']['revisione'] = Xml::intero($doc, '/ipogeo/catasto/revisione', 0);

        return $s;
    }
}
