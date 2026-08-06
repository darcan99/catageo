<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Ricerca.php
 *  Descrizione ..: Motore di ricerca sull'indice degli ipogei (10).
 *
 *                  Tutti i criteri sono in AND e si valutano in tre passate,
 *                  dalla piu economica alla piu costosa:
 *
 *                  1. sull'INDICE CSV, in streaming: una riga per volta, senza
 *                     caricare l'indice in memoria. Copre testo, catalogo,
 *                     attributi, presenza di risorse, intervalli numerici e il
 *                     pre-filtro geografico a riquadro;
 *                  2. la DISTANZA esatta sui soli candidati del riquadro;
 *                  3. i criteri SPECIALISTICI (grandezza misurata, specie
 *                     osservata, vincolo, periodo storico), che l'indice non
 *                     conosce, aprendo i file di sezione dei soli sopravvissuti.
 *
 *                  E il compromesso che tiene l'indice di dimensioni
 *                  ragionevoli senza rinunciare alla ricerca specialistica: un
 *                  filtro per specie su tremila ipogei apre solo i file di
 *                  biospeleologia dei pochi che hanno passato tutto il resto.
 *
 *                  L'esito dichiara sempre quante schede sono state esaminate e
 *                  se il risultato e stato troncato: un elenco tagliato in
 *                  silenzio si scambia per un elenco completo.
 *  Versione .....: 0.13.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.13.0  2026-08-06  D.Candela  Prima stesura (fase 8).
 * ============================================================================
 */

final class Ricerca
{
    /**
     * Tetto ai risultati restituiti.
     *
     * Non protegge il server — la scansione avviene comunque — ma la pagina e
     * l'export: centomila righe in una tabella non le legge nessuno.
     */
    public const LIMITE = 2000;

    /** Raggio predefinito e massimo della ricerca geografica, in metri. */
    public const RAGGIO_PREDEFINITO = 2000;
    public const RAGGIO_MASSIMO     = 200000;

    /** Criteri di ordinamento offerti. */
    public const ORDINAMENTI = [
        'codice'    => 'Codice',
        'nome'      => 'Nome',
        'comune'    => 'Comune',
        'sviluppo'  => 'Sviluppo',
        'dislivello' => 'Dislivello',
        'quota'     => 'Quota',
        'data'      => 'Data di censimento',
        'distanza'  => 'Distanza dal punto',
    ];

    /**
     * Presenze verificabili direttamente sull'indice: colonna => etichetta.
     *
     * Le chiavi che iniziano per "n_" si verificano come "maggiore di zero",
     * quelle che iniziano per "ha_" come "uguale a 1".
     */
    public const PRESENZE = [
        'n_foto'          => 'Foto',
        'n_rilievi'       => 'Rilievi',
        'n_allegati'      => 'Allegati',
        'n_video'         => 'Video',
        'n_esplorazioni'  => 'Diari di esplorazione',
        'n_biblio'        => 'Bibliografia',
        'n_serie_misure'  => 'Serie di misure',
        'ha_kml'          => 'Tracciato su mappa',
        'ha_3d'           => 'Modello 3D',
        'ha_chirotteri'   => 'Colonie di chirotteri',
        'ha_archeologia'  => 'Dati archeologici',
    ];

    /** Criteri accettati, col valore di riposo. */
    public const CRITERI = [
        'testo' => '', 'nelleDescrizioni' => '0',
        'cataloghi' => [], 'natura' => '', 'tipologia' => '', 'sottotipologia' => '',
        'stato' => '', 'regione' => '', 'provincia' => '', 'comune' => '',
        'statoAccesso' => '', 'statoScheda' => '',
        'presenze' => [],
        'sviluppoMin' => '', 'sviluppoMax' => '',
        'dislivelloMin' => '', 'dislivelloMax' => '',
        'quotaMin' => '', 'quotaMax' => '',
        'censitoDal' => '', 'censitoAl' => '',
        // specialistici, risolti in seconda passata
        'grandezza' => '', 'specie' => '', 'periodo' => '',
        'annoDa' => '', 'annoA' => '', 'conVincolo' => '0',
        // geografici
        'latitudine' => '', 'longitudine' => '', 'raggio' => '',
        // presentazione
        'ordina' => 'codice', 'verso' => 'asc',
    ];

    /**
     * Esegue la ricerca.
     *
     * @param  array<string,mixed> $criteri
     * @return array{
     *     righe:array<int,array<string,mixed>>,
     *     totale:int,
     *     esaminate:int,
     *     troncato:bool,
     *     apertiPerSpecialistici:int,
     *     apertiPerDescrizioni:int,
     *     avvisi:array<int,string>
     * }
     */
    public static function esegui(array $criteri): array
    {
        $c = self::normalizza($criteri);

        $avvisi = [];
        $esaminate = 0;
        $apertiDescrizioni = 0;

        // --- pre-filtro geografico ------------------------------------------
        $riquadro = null;
        $centro = null;
        if ($c['latitudine'] !== null && $c['longitudine'] !== null && $c['raggio'] > 0) {
            $centro = [$c['latitudine'], $c['longitudine']];
            $riquadro = Geo::riquadro($c['latitudine'], $c['longitudine'], (float) $c['raggio']);
        }

        // --- passata 1: indice ----------------------------------------------
        $candidati = [];

        Csv::leggi(IndiceIpogei::percorso(), static function (array $riga) use (
            $c, $riquadro, &$candidati, &$esaminate, &$apertiDescrizioni
        ): bool {
            $esaminate++;

            if (!Visibilita::schedaVisibile(
                (string) ($riga['riservatezza'] ?? ''),
                (string) ($riga['stato_scheda'] ?? '')
            )) {
                return true;
            }

            if (!self::passaIndice($riga, $c)) {
                return true;
            }

            if ($riquadro !== null) {
                $lat = self::numero((string) ($riga['lat'] ?? ''));
                $lon = self::numero((string) ($riga['lon'] ?? ''));
                if ($lat === null || $lon === null || !Geo::nelRiquadro($riquadro, $lat, $lon)) {
                    return true;
                }
            }

            // Il testo nelle descrizioni si cerca per ultimo fra i criteri di
            // questa passata, perche costa l'apertura di un XML: prima si
            // scremano gli altri filtri, che costano un confronto di stringhe.
            if ($c['nelleDescrizioni'] && $c['testo'] !== '' && !self::testoInIndice($riga, $c['testo'])) {
                $apertiDescrizioni++;
                if (!self::testoInScheda((string) $riga['codice'], $c['testo'])) {
                    return true;
                }
            }

            $candidati[] = $riga;

            return true;
        });

        // --- passata 2: distanza esatta -------------------------------------
        if ($centro !== null) {
            $conDistanza = [];
            foreach ($candidati as $riga) {
                $lat = self::numero((string) ($riga['lat'] ?? ''));
                $lon = self::numero((string) ($riga['lon'] ?? ''));
                if ($lat === null || $lon === null) {
                    continue;
                }
                $distanza = Geo::distanza($centro[0], $centro[1], $lat, $lon);
                if ($distanza > $c['raggio']) {
                    continue;
                }
                $riga['distanza'] = $distanza;
                $conDistanza[] = $riga;
            }
            $candidati = $conDistanza;

            /*
             * Le coordinate offuscate spostano il punto di alcune centinaia di
             * metri: una ricerca per raggio su di esse da un risultato che PUO
             * essere sbagliato in entrambi i sensi. L'indice porta le coordinate
             * vere, quindi il filtro e corretto, ma la distanza mostrata a un
             * USR e quella del punto arrotondato. Va detto invece che lasciato
             * intuire.
             */
            foreach ($candidati as $riga) {
                if ((string) ($riga['riservatezza'] ?? '') === 'coordinate_offuscate'
                    && !Auth::puo('vedi_riservati')) {
                    $avvisi[] = 'Alcuni risultati hanno coordinate offuscate: '
                        . 'la distanza mostrata e approssimata.';
                    break;
                }
            }
        }

        // --- passata 3: criteri specialistici -------------------------------
        $apertiSpecialistici = 0;
        if (self::haSpecialistici($c)) {
            $sopravvissuti = [];
            foreach ($candidati as $riga) {
                $apertiSpecialistici++;
                if (self::passaSpecialistici((string) $riga['codice'], $c)) {
                    $sopravvissuti[] = $riga;
                }
            }
            $candidati = $sopravvissuti;
        }

        // --- ordinamento e taglio -------------------------------------------
        self::ordina($candidati, (string) $c['ordina'], (string) $c['verso']);

        $totale = count($candidati);
        $troncato = $totale > self::LIMITE;
        if ($troncato) {
            $candidati = array_slice($candidati, 0, self::LIMITE);
            $avvisi[] = 'Trovati ' . $totale . ' risultati: ne sono mostrati i primi '
                . self::LIMITE . '. Restringi i criteri per vederli tutti.';
        }

        if ($c['nelleDescrizioni'] && $apertiDescrizioni > 0) {
            $avvisi[] = 'Ricerca estesa alle descrizioni: aperte ' . $apertiDescrizioni
                . ' schede oltre all\'indice.';
        }

        return [
            'righe'     => $candidati,
            'totale'    => $totale,
            'esaminate' => $esaminate,
            'troncato'  => $troncato,
            'apertiPerSpecialistici' => $apertiSpecialistici,
            'apertiPerDescrizioni'   => $apertiDescrizioni,
            'avvisi'    => array_values(array_unique($avvisi)),
        ];
    }

    /**
     * Risolve un testo che potrebbe essere un codice, corrente o storico.
     *
     * Serve al caso piu frequente di tutti: si digita un codice letto su una
     * pubblicazione e si vuole la scheda, anche se quel codice e stato
     * dismesso da una migrazione.
     *
     * @return array{codice:string,storico:bool}|null
     */
    public static function risolviCodice(string $testo): ?array
    {
        $testo = trim($testo);
        if ($testo === '') {
            return null;
        }

        // risolvi() restituisce gia il codice CORRENTE nella chiave "codice",
        // sia che quello cercato sia corrente sia che sia storico: e la chiave
        // "codice_corrente" esiste solo nella riga grezza del CSV, non qui.
        $voce = IndiceCodici::risolvi($testo);
        if ($voce === null) {
            return null;
        }

        $corrente = trim((string) ($voce['codice'] ?? ''));
        if ($corrente === '') {
            return null;
        }

        return [
            'codice'  => $corrente,
            'storico' => !empty($voce['storico']),
        ];
    }

    // ========================================================================
    //  FILTRI
    // ========================================================================

    /**
     * @param array<string,string> $riga
     * @param array<string,mixed>  $c
     */
    private static function passaIndice(array $riga, array $c): bool
    {
        // --- testo
        if ($c['testo'] !== '' && !$c['nelleDescrizioni'] && !self::testoInIndice($riga, $c['testo'])) {
            return false;
        }

        // --- catalogo
        if ($c['cataloghi'] !== []) {
            $catalogo = strtoupper((string) ($riga['catalogo'] ?? ''));
            if (!in_array($catalogo, $c['cataloghi'], true)) {
                return false;
            }
        }

        // --- attributi a corrispondenza esatta
        foreach (['natura' => 'natura', 'tipologia' => 'tipologia',
                  'sottotipologia' => 'sottotipologia', 'stato' => 'stato',
                  'regione' => 'regione', 'provincia' => 'provincia',
                  'statoAccesso' => 'stato_accesso', 'statoScheda' => 'stato_scheda'] as $criterio => $colonna) {
            if ((string) $c[$criterio] === '') {
                continue;
            }
            if (strcasecmp((string) ($riga[$colonna] ?? ''), (string) $c[$criterio]) !== 0) {
                return false;
            }
        }

        // Il comune si cerca per contenuto e non per uguaglianza: chi scrive
        // "Roma" deve trovare anche "Roma (Municipio VII)".
        if ((string) $c['comune'] !== ''
            && !str_contains(Testo::normalizzaRicerca((string) ($riga['comune'] ?? '')),
                             Testo::normalizzaRicerca((string) $c['comune']))) {
            return false;
        }

        // --- presenze
        foreach ((array) $c['presenze'] as $colonna) {
            if (!isset(self::PRESENZE[$colonna])) {
                continue;
            }
            $valore = (string) ($riga[$colonna] ?? '');
            $presente = str_starts_with($colonna, 'ha_')
                ? $valore === '1'
                : (int) $valore > 0;
            if (!$presente) {
                return false;
            }
        }

        // --- intervalli numerici
        foreach ([['sviluppo', 'sviluppoMin', 'sviluppoMax'],
                  ['dislivello', 'dislivelloMin', 'dislivelloMax'],
                  ['quota', 'quotaMin', 'quotaMax']] as [$colonna, $chiaveMin, $chiaveMax]) {

            if ($c[$chiaveMin] === null && $c[$chiaveMax] === null) {
                continue;
            }

            $valore = self::numero((string) ($riga[$colonna] ?? ''));
            if ($valore === null) {
                // Un ipogeo senza il dato non soddisfa un filtro su quel dato.
                // L'alternativa — includerlo — riempirebbe i risultati di
                // schede di cui non si sa nulla proprio sul criterio scelto.
                return false;
            }
            if ($c[$chiaveMin] !== null && $valore < $c[$chiaveMin]) {
                return false;
            }
            if ($c[$chiaveMax] !== null && $valore > $c[$chiaveMax]) {
                return false;
            }
        }

        // --- intervallo di date
        $data = (string) ($riga['data_censimento'] ?? '');
        if ((string) $c['censitoDal'] !== '' && ($data === '' || $data < (string) $c['censitoDal'])) {
            return false;
        }
        if ((string) $c['censitoAl'] !== '' && ($data === '' || $data > (string) $c['censitoAl'])) {
            return false;
        }

        // --- vincolo: e in indice, quindi si risolve qui
        if ((string) $c['conVincolo'] === '1' && (string) ($riga['ha_archeologia'] ?? '') !== '1') {
            return false;
        }

        return true;
    }

    /**
     * Testo cercato nei campi dell'indice.
     *
     * @param array<string,string> $riga
     */
    private static function testoInIndice(array $riga, string $ago): bool
    {
        $pagliaio = Testo::normalizzaRicerca(implode(' ', [
            (string) ($riga['codice'] ?? ''),
            (string) ($riga['nome'] ?? ''),
            (string) ($riga['comune'] ?? ''),
            (string) ($riga['localita'] ?? ''),
            (string) ($riga['provincia'] ?? ''),
            (string) ($riga['regione'] ?? ''),
        ]));

        if (str_contains($pagliaio, $ago)) {
            return true;
        }

        /*
         * I codici storici non stanno nell'indice degli ipogei ma in
         * codici.csv: chi cerca "LA297" dopo una migrazione deve comunque
         * trovare la scheda. Il controllo si fa solo se il testo cercato ha
         * l'aria di un codice, per non interrogare l'indice dei codici a ogni
         * riga di una ricerca per nome.
         */
        if (preg_match('/^[A-Za-z0-9._\-]{2,60}$/', $ago) === 1) {
            foreach (IndiceCodici::storiciDi((string) ($riga['codice'] ?? '')) as $storico) {
                if (str_contains(Testo::normalizzaRicerca((string) $storico), $ago)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Testo cercato nei campi discorsivi della scheda. */
    private static function testoInScheda(string $codice, string $ago): bool
    {
        $scheda = Ipogeo::trova($codice);
        if ($scheda === null) {
            return false;
        }

        $pezzi = [
            (string) ($scheda['identificazione']['nome'] ?? ''),
            (string) ($scheda['descrizione']['sintesi'] ?? ''),
            (string) ($scheda['descrizione']['testo'] ?? ''),
            (string) ($scheda['descrizione']['storia'] ?? ''),
            (string) ($scheda['descrizione']['note'] ?? ''),
            (string) ($scheda['ubicazione']['localita'] ?? ''),
            (string) ($scheda['ubicazione']['indirizzo'] ?? ''),
            (string) ($scheda['ubicazione']['accesso']['descrizione'] ?? ''),
        ];

        // I sinonimi sono il motivo principale per cui questa ricerca esiste:
        // una cavita nota localmente con un altro nome non si trova per codice.
        foreach ((array) ($scheda['identificazione']['sinonimi'] ?? []) as $sinonimo) {
            $pezzi[] = (string) $sinonimo;
        }

        // I codici esterni sono quelli con cui la cavita compare nei catasti
        // altrui: chi arriva da una pubblicazione ha in mano quelli.
        foreach ((array) ($scheda['identificazione']['codiciEsterni'] ?? []) as $esterno) {
            $pezzi[] = (string) ($esterno['codice'] ?? '');
            $pezzi[] = (string) ($esterno['catasto'] ?? '');
        }

        return str_contains(Testo::normalizzaRicerca(implode(' ', $pezzi)), $ago);
    }

    /** @param array<string,mixed> $c */
    private static function haSpecialistici(array $c): bool
    {
        return (string) $c['grandezza'] !== ''
            || (string) $c['specie'] !== ''
            || (string) $c['periodo'] !== ''
            || $c['annoDa'] !== null
            || $c['annoA'] !== null
            || (string) $c['conVincolo'] === '1';
    }

    /**
     * Criteri che l'indice non conosce, risolti aprendo i file di sezione.
     *
     * @param array<string,mixed> $c
     */
    private static function passaSpecialistici(string $codice, array $c): bool
    {
        if ((string) $c['grandezza'] !== '') {
            $trovata = false;
            foreach (Scientifici::serieVisibili($codice) as $serie) {
                if (strcasecmp((string) $serie['grandezza'], (string) $c['grandezza']) === 0) {
                    $trovata = true;
                    break;
                }
            }
            if (!$trovata) {
                return false;
            }
        }

        if ((string) $c['specie'] !== '') {
            $ago = Testo::normalizzaRicerca((string) $c['specie']);
            $trovata = false;

            foreach (Biospeleologia::osservazioni($codice) as $osservazione) {
                $pagliaio = Testo::normalizzaRicerca(
                    (string) $osservazione['nomeScientifico'] . ' ' . (string) $osservazione['nomeComune']);
                if (str_contains($pagliaio, $ago)) {
                    $trovata = true;
                    break;
                }
            }

            // Le colonie non visibili non contribuiscono: comparire fra i
            // risultati di una ricerca per specie rivelerebbe l'esistenza di un
            // roost che l'utente non ha diritto di conoscere.
            if (!$trovata) {
                foreach (Biospeleologia::colonieVisibili($codice) as $colonia) {
                    $pagliaio = Testo::normalizzaRicerca(
                        (string) $colonia['specie'] . ' ' . (string) $colonia['specieAggiuntive']);
                    if (str_contains($pagliaio, $ago)) {
                        $trovata = true;
                        break;
                    }
                }
            }

            if (!$trovata) {
                return false;
            }
        }

        if ((string) $c['conVincolo'] === '1'
            && (string) Archeologia::tutela($codice)['vincolo'] !== '1') {
            return false;
        }

        if ((string) $c['periodo'] !== '' || $c['annoDa'] !== null || $c['annoA'] !== null) {
            if (!self::passaPeriodo($codice, $c)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $c */
    private static function passaPeriodo(string $codice, array $c): bool
    {
        $inquadramento = Archeologia::inquadramento($codice);

        $periodi = [(string) $inquadramento['periodoPrincipale']];
        foreach (preg_split('/\s*,\s*/', (string) $inquadramento['periodiSecondari']) ?: [] as $p) {
            if (trim($p) !== '') {
                $periodi[] = trim($p);
            }
        }
        $periodi = array_values(array_filter($periodi));

        if ((string) $c['periodo'] !== '' && !in_array((string) $c['periodo'], $periodi, true)) {
            return false;
        }

        if ($c['annoDa'] === null && $c['annoA'] === null) {
            return true;
        }

        /*
         * L'intervallo di anni si confronta con la datazione dichiarata se c'e,
         * altrimenti con gli estremi dei periodi dichiarati, che il vocabolario
         * conosce. Cosi "cerca fra il 100 a.C. e il 100 d.C." trova anche le
         * schede che hanno solo scritto "eta romana".
         */
        $da = self::numero((string) $inquadramento['datazioneDa']);
        $a  = self::numero((string) $inquadramento['datazioneA']);

        if ($da === null && $a === null) {
            foreach ($periodi as $codicePeriodo) {
                $voce = Periodi::trova($codicePeriodo);
                if ($voce === null) {
                    continue;
                }
                $pDa = self::numero((string) $voce['da']);
                $pA  = self::numero((string) $voce['a']);
                if (self::intervalliSiToccano($pDa, $pA, $c['annoDa'], $c['annoA'])) {
                    return true;
                }
            }

            return false;
        }

        return self::intervalliSiToccano($da, $a, $c['annoDa'], $c['annoA']);
    }

    /**
     * True se due intervalli, eventualmente aperti, hanno intersezione.
     *
     * Si cerca la sovrapposizione e non l'inclusione: una cavita usata dal 27
     * a.C. al 476 d.C. deve comparire cercando "il I secolo", anche se il
     * secolo e solo una parte del suo arco di vita.
     */
    private static function intervalliSiToccano(?float $aDa, ?float $aA, ?float $bDa, ?float $bA): bool
    {
        if ($aDa === null && $aA === null) {
            return false;
        }

        $aDa ??= -PHP_FLOAT_MAX;
        $aA  ??= PHP_FLOAT_MAX;
        $bDa ??= -PHP_FLOAT_MAX;
        $bA  ??= PHP_FLOAT_MAX;

        return $aDa <= $bA && $bDa <= $aA;
    }

    // ========================================================================
    //  ORDINAMENTO E NORMALIZZAZIONE
    // ========================================================================

    /** @param array<int,array<string,mixed>> $righe */
    private static function ordina(array &$righe, string $criterio, string $verso): void
    {
        $segno = $verso === 'desc' ? -1 : 1;

        $colonne = [
            'codice' => 'codice', 'nome' => 'nome', 'comune' => 'comune',
            'sviluppo' => 'sviluppo', 'dislivello' => 'dislivello',
            'quota' => 'quota', 'data' => 'data_censimento',
        ];

        if ($criterio === 'distanza') {
            usort($righe, static function (array $a, array $b) use ($segno): int {
                // Le righe senza distanza vanno in fondo comunque, anche in
                // ordine discendente: "non misurabile" non e "molto lontano".
                $da = $a['distanza'] ?? null;
                $db = $b['distanza'] ?? null;
                if ($da === null && $db === null) { return 0; }
                if ($da === null) { return 1; }
                if ($db === null) { return -1; }

                return $segno * ($da <=> $db);
            });

            return;
        }

        $colonna = $colonne[$criterio] ?? 'codice';
        $numerica = in_array($criterio, ['sviluppo', 'dislivello', 'quota'], true);

        usort($righe, static function (array $a, array $b) use ($colonna, $numerica, $segno): int {
            if ($numerica) {
                $va = self::numero((string) ($a[$colonna] ?? ''));
                $vb = self::numero((string) ($b[$colonna] ?? ''));
                if ($va === null && $vb === null) { return 0; }
                if ($va === null) { return 1; }
                if ($vb === null) { return -1; }

                return $segno * ($va <=> $vb);
            }

            // strnatcasecmp: cosi LA9 viene prima di LA10, che e l'ordine in
            // cui una persona si aspetta di leggere dei codici.
            return $segno * strnatcasecmp((string) ($a[$colonna] ?? ''), (string) ($b[$colonna] ?? ''));
        });
    }

    /**
     * Porta i criteri grezzi in una forma su cui i filtri possono contare.
     *
     * @param  array<string,mixed> $criteri
     * @return array<string,mixed>
     */
    private static function normalizza(array $criteri): array
    {
        $c = array_merge(self::CRITERI, $criteri);

        $c['testo'] = Testo::normalizzaRicerca(trim((string) $c['testo']));
        $c['nelleDescrizioni'] = (string) $c['nelleDescrizioni'] === '1';

        $c['cataloghi'] = array_values(array_filter(array_map(
            static fn ($v): string => strtoupper(trim((string) $v)),
            (array) $c['cataloghi']
        )));

        $c['presenze'] = array_values(array_filter(
            (array) $c['presenze'],
            static fn ($v): bool => isset(self::PRESENZE[(string) $v])
        ));

        foreach (['sviluppoMin', 'sviluppoMax', 'dislivelloMin', 'dislivelloMax',
                  'quotaMin', 'quotaMax', 'annoDa', 'annoA'] as $campo) {
            $c[$campo] = self::numero((string) $c[$campo]);
        }

        foreach (['latitudine' => 90.0, 'longitudine' => 180.0] as $campo => $massimo) {
            $valore = self::numero((string) $c[$campo]);
            $c[$campo] = ($valore !== null && abs($valore) <= $massimo) ? $valore : null;
        }

        $raggio = self::numero((string) $c['raggio']);
        $c['raggio'] = $raggio === null ? 0 : (int) min(max(0, $raggio), self::RAGGIO_MASSIMO);

        // Ordinare per distanza senza un punto darebbe un ordine casuale che
        // sembra significativo: si ricade sul codice.
        if ((string) $c['ordina'] === 'distanza' && ($c['latitudine'] === null || $c['raggio'] <= 0)) {
            $c['ordina'] = 'codice';
        }
        if (!isset(self::ORDINAMENTI[(string) $c['ordina']])) {
            $c['ordina'] = 'codice';
        }
        $c['verso'] = (string) $c['verso'] === 'desc' ? 'desc' : 'asc';

        return $c;
    }

    /**
     * Numero da una stringa, o null se non lo e.
     *
     * Accetta la virgola decimale, che e quella della tastiera italiana.
     */
    private static function numero(string $valore): ?float
    {
        $valore = trim(str_replace(',', '.', $valore));

        return $valore !== '' && is_numeric($valore) ? (float) $valore : null;
    }

    /**
     * True se almeno un criterio e stato indicato.
     *
     * Serve a distinguere "nessun risultato" da "non hai ancora cercato": due
     * schermate identiche che dicono cose opposte.
     *
     * @param array<string,mixed> $criteri
     */
    public static function haCriteri(array $criteri): bool
    {
        foreach (self::CRITERI as $chiave => $riposo) {
            if (in_array($chiave, ['ordina', 'verso', 'nelleDescrizioni'], true)) {
                continue;
            }
            $valore = $criteri[$chiave] ?? $riposo;
            if (is_array($valore)) {
                if (array_filter($valore) !== []) {
                    return true;
                }
                continue;
            }
            if (trim((string) $valore) !== '' && (string) $valore !== '0') {
                return true;
            }
        }

        return false;
    }
}
