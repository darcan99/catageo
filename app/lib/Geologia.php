<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Geologia.php
 *  Descrizione ..: Sezione geologica dell'ipogeo (6.16, fase 6b):
 *                  inquadramento, genesi, assetto strutturale, morfologie,
 *                  idrogeologia, rischi e campioni.
 *
 *                  Il criterio con cui i campi sono stati scelti e uno solo:
 *                  **cose che uno speleologo puo compilare in cavita o
 *                  desumere dalla cartografia pubblica**, senza analisi di
 *                  laboratorio. Un modello che pretendesse dati da laboratorio
 *                  resterebbe vuoto, ed e il modo piu sicuro di rendere inutile
 *                  una sezione.
 *
 *                  **La fonte dell'inquadramento e obbligatoria** ed e la cosa
 *                  piu importante di questa sezione. Litologia e formazione
 *                  lette su una carta 1:50.000 inquadrano la formazione
 *                  regionale e non distinguono una lente di dieci metri: se non
 *                  si sa da dove viene il dato, chi legge non puo sapere quanto
 *                  fidarsi. Un dato osservato in cavita e un dato dedotto da una
 *                  carta hanno lo stesso aspetto e valore diverso.
 *
 *                  I rischi alimentano la barra avvisi della scheda: chi
 *                  programma un'uscita deve vedere subito che una cavita e
 *                  soggetta a crolli o si allaga in piena.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Prima stesura (fase 6b).
 * ============================================================================
 */

final class Geologia
{
    public const VERSIONE_SCHEMA = '1.0';
    public const SIGLA = 'GE';

    /**
     * Come si e ottenuto il dato di inquadramento.
     *
     * E il campo che da valore a tutti gli altri: senza, una litologia dedotta
     * da una carta regionale e una osservata sul posto si assomigliano e
     * valgono in modo diverso.
     */
    public const MODALITA_FONTE = [
        ''              => 'non dichiarata',
        'osservazione'  => 'Osservazione diretta in cavità',
        'manuale'       => 'Lettura manuale della cartografia',
        'GetFeatureInfo' => 'Interrogazione automatica del servizio cartografico',
        'bibliografia'  => 'Bibliografia',
    ];

    public const TIPI_GENESI = [
        ''           => 'non determinata',
        'carsica'    => 'Carsica',
        'vulcanica'  => 'Vulcanica',
        'tettonica'  => 'Tettonica',
        'erosiva'    => 'Erosiva',
        'glaciale'   => 'Glaciale',
        'marina'     => 'Marina',
        'antropica'  => 'Antropica',
        'mista'      => 'Mista',
    ];

    public const FRATTURAZIONE = [
        ''         => 'non valutata',
        'assente'  => 'Assente',
        'debole'   => 'Debole',
        'media'    => 'Media',
        'intensa'  => 'Intensa',
    ];

    /**
     * Morfologie osservabili.
     *
     * Comprende le **tracce di scavo**, che su una cavita artificiale sono la
     * morfologia principale: un elenco che le omettesse sarebbe utilizzabile
     * solo sulle grotte (16.2).
     */
    public const TIPI_MORFOLOGIA = [
        'concrezionamento'    => 'Concrezionamento',
        'marmitte'            => 'Marmitte',
        'scallops'            => 'Scallops',
        'canali di volta'     => 'Canali di volta',
        'crolli'              => 'Crolli',
        'riempimenti'         => 'Riempimenti',
        'forme di corrosione' => 'Forme di corrosione',
        'forme di erosione'   => 'Forme di erosione',
        'tracce di scavo'     => 'Tracce di scavo',
        'altro'               => 'Altro',
    ];

    public const PERMEABILITA = [
        ''                 => 'non valutata',
        'per porosita'     => 'Per porosità',
        'per fessurazione' => 'Per fessurazione',
        'per carsismo'     => 'Per carsismo',
        'impermeabile'     => 'Impermeabile',
    ];

    public const RUOLI_IDRO = [
        ''             => 'non determinato',
        'assorbimento' => 'Assorbimento',
        'drenaggio'    => 'Drenaggio',
        'risorgenza'   => 'Risorgenza',
        'nessuno'      => 'Nessuno',
    ];

    public const TIPI_RISCHIO = [
        'crollo'      => 'Crollo',
        'allagamento' => 'Allagamento',
        'sinkhole'    => 'Sinkhole',
        'subsidenza'  => 'Subsidenza',
        'gas'         => 'Gas',
        'sismico'     => 'Sismico',
    ];

    public const LIVELLI_RISCHIO = [
        'basso'  => 'Basso',
        'medio'  => 'Medio',
        'alto'   => 'Alto',
    ];

    /** Livelli che finiscono nella barra avvisi della scheda. */
    public const RISCHI_DA_AVVISARE = ['medio', 'alto'];

    public const CAMPI_INQUADRAMENTO = [
        'litologia' => '', 'formazione' => '', 'unitaGeologica' => '',
        'etaFormazione' => '', 'sistemaCrono' => '', 'serieCrono' => '',
        'foglioGeologico' => '',
        'fonteTipo' => '', 'fonteNome' => '', 'fonteData' => '', 'fonteModalita' => '',
    ];

    public const CAMPI_GENESI = [
        'tipoGenesi' => '', 'processo' => '', 'rocciaIncassante' => '',
    ];

    public const CAMPI_ASSETTO = [
        'immersione' => '', 'inclinazione' => '', 'fratturazione' => '', 'note' => '',
    ];

    public const CAMPI_IDROGEOLOGIA = [
        'acquifero' => '', 'permeabilita' => '', 'ruoloIdrogeologico' => '',
        'serieMisureRif' => '', 'note' => '',
    ];

    public const CAMPI_MORFOLOGIA = [
        'tipo' => 'altro', 'descrizione' => '', 'zonaCavita' => '', 'fotoRif' => '',
    ];

    public const CAMPI_CAMPIONE = [
        'tipo' => '', 'data' => '', 'prelevatoDa' => '', 'zonaCavita' => '',
        'finalita' => '', 'depositatoPresso' => '', 'esitoAnalisi' => '',
        'allegatoRif' => '', 'autorizzazione' => '',
    ];

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

    // ========================================================================
    //  LETTURA
    // ========================================================================

    /** @return array<string,mixed> */
    public static function inquadramento(string $codice): array
    {
        return self::leggi($codice)['inquadramento'];
    }

    /** @return array<string,mixed> */
    public static function genesi(string $codice): array
    {
        return self::leggi($codice)['genesi'];
    }

    /** @return array<string,mixed> */
    public static function assetto(string $codice): array
    {
        return self::leggi($codice)['assetto'];
    }

    /** @return array<int,array<string,mixed>> */
    public static function morfologie(string $codice): array
    {
        return self::leggi($codice)['morfologie'];
    }

    /** @return array<string,mixed> */
    public static function idrogeologia(string $codice): array
    {
        return self::leggi($codice)['idrogeologia'];
    }

    /** @return array<int,array<string,mixed>> */
    public static function rischi(string $codice): array
    {
        return self::leggi($codice)['rischi'];
    }

    /** @return array<int,array<string,mixed>> */
    public static function campioni(string $codice): array
    {
        return self::leggi($codice)['campioni'];
    }

    /**
     * Quante voci geologiche ha l'ipogeo.
     *
     * L'inquadramento conta come una voce se compilato: un ipogeo di cui si
     * conosce la sola litologia ha comunque contenuto geologico, e un
     * conteggio a zero lo farebbe sparire dalle ricerche.
     */
    public static function conta(string $codice): int
    {
        $stato = self::leggi($codice);

        $totale = count($stato['morfologie']) + count($stato['rischi']) + count($stato['campioni']);
        if ((string) $stato['inquadramento']['litologia'] !== ''
            || (string) $stato['inquadramento']['formazione'] !== '') {
            $totale++;
        }
        if ((string) $stato['genesi']['tipoGenesi'] !== '') {
            $totale++;
        }
        if ((string) $stato['idrogeologia']['ruoloIdrogeologico'] !== '') {
            $totale++;
        }

        return $totale;
    }

    /** Litologia, per la colonna dell'indice e per la ricerca. */
    public static function litologia(string $codice): string
    {
        return (string) self::inquadramento($codice)['litologia'];
    }

    /** Tipo di genesi, per l'indice. */
    public static function tipoGenesi(string $codice): string
    {
        return (string) self::genesi($codice)['tipoGenesi'];
    }

    /**
     * Avvisi di rischio geologico per la scheda.
     *
     * Solo medio e alto: un rischio basso segnalato accanto a un vincolo e a un
     * periodo critico dei chirotteri abituerebbe a ignorare la barra, che e il
     * modo migliore per rendere inutili anche gli avvisi che contano.
     *
     * @return array<int,array{livello:string,titolo:string,testo:string}>
     */
    public static function avvisi(string $codice): array
    {
        $avvisi = [];

        foreach (self::rischi($codice) as $rischio) {
            $livello = (string) $rischio['livello'];
            if (!in_array($livello, self::RISCHI_DA_AVVISARE, true)) {
                continue;
            }

            $descrizione = trim((string) $rischio['descrizione']);
            $avvisi[] = [
                'livello' => $livello === 'alto' ? 'danger' : 'warning',
                'titolo'  => 'Rischio geologico: ' . (self::TIPI_RISCHIO[(string) $rischio['tipo']]
                    ?? (string) $rischio['tipo']) . ' (' . $livello . ')',
                'testo'   => $descrizione !== ''
                    ? rtrim($descrizione, '.') . '.'
                    : 'Nessun dettaglio registrato.',
            ];
        }

        return $avvisi;
    }

    // ========================================================================
    //  SCRITTURA
    // ========================================================================

    /** @param array<string,mixed> $dati */
    public static function salvaInquadramento(string $codice, array $dati): void
    {
        self::modifica($codice, static function (array $stato) use ($dati): array {
            $stato['inquadramento'] = array_merge(self::CAMPI_INQUADRAMENTO, $dati);
            return $stato;
        });
    }

    /** @param array<string,mixed> $dati */
    public static function salvaGenesi(string $codice, array $dati): void
    {
        self::modifica($codice, static function (array $stato) use ($dati): array {
            $stato['genesi'] = array_merge(self::CAMPI_GENESI, $dati);
            return $stato;
        });
    }

    /** @param array<string,mixed> $dati */
    public static function salvaAssetto(string $codice, array $dati): void
    {
        self::modifica($codice, static function (array $stato) use ($dati): array {
            $stato['assetto'] = array_merge(self::CAMPI_ASSETTO, $dati);
            return $stato;
        });
    }

    /** @param array<string,mixed> $dati */
    public static function salvaIdrogeologia(string $codice, array $dati): void
    {
        self::modifica($codice, static function (array $stato) use ($dati): array {
            $stato['idrogeologia'] = array_merge(self::CAMPI_IDROGEOLOGIA, $dati);
            return $stato;
        });
    }

    /**
     * Sostituisce per intero un elenco (morfologie, rischi, campioni).
     *
     * Si riscrive tutto invece di aggiornare voce per voce: gli elenchi si
     * compilano in un modulo unico con righe libere in coda, e una gestione a
     * progressivi qui aggiungerebbe complessita senza servire a nessuno.
     *
     * @param array<int,array<string,mixed>> $voci
     */
    public static function salvaElenco(string $codice, string $quale, array $voci): void
    {
        if (!in_array($quale, ['morfologie', 'rischi', 'campioni'], true)) {
            throw new GeologiaEccezione('Elenco non riconosciuto: ' . $quale);
        }

        self::modifica($codice, static function (array $stato) use ($quale, $voci): array {
            $stato[$quale] = array_values($voci);
            return $stato;
        });
    }

    // ========================================================================
    //  INTERNI
    // ========================================================================

    /** @return array<string,mixed> */
    private static function vuoto(): array
    {
        return [
            'inquadramento' => self::CAMPI_INQUADRAMENTO,
            'genesi'        => self::CAMPI_GENESI,
            'assetto'       => self::CAMPI_ASSETTO,
            'sistemi'       => [],
            'morfologie'    => [],
            'idrogeologia'  => self::CAMPI_IDROGEOLOGIA,
            'sorgenti'      => [],
            'tracciamenti'  => [],
            'rischi'        => [],
            'campioni'      => [],
        ];
    }

    /** @return array<string,mixed> */
    private static function leggi(string $codice): array
    {
        $vuoto    = self::vuoto();
        $percorso = self::percorso($codice);

        if ($percorso === null || !is_file($percorso)) {
            return $vuoto;
        }

        try {
            $doc = Xml::carica($percorso);
        } catch (Throwable $e) {
            Log::errore('Geologia illeggibile: ' . $percorso . ' — ' . $e->getMessage());
            return $vuoto;
        }

        $stato = $vuoto;

        // --- inquadramento
        foreach (['litologia', 'formazione', 'unitaGeologica', 'etaFormazione',
                  'foglioGeologico'] as $campo) {
            $stato['inquadramento'][$campo] = Xml::testo($doc, '/geologia/inquadramento/' . $campo);
        }
        $crono = Xml::primo($doc, '/geologia/inquadramento/cronostratigrafia');
        if ($crono instanceof DOMElement) {
            $stato['inquadramento']['sistemaCrono'] = $crono->getAttribute('sistema');
            $stato['inquadramento']['serieCrono']   = $crono->getAttribute('serie');
        }
        $fonte = Xml::primo($doc, '/geologia/inquadramento/fonte');
        if ($fonte instanceof DOMElement) {
            $stato['inquadramento']['fonteTipo']     = $fonte->getAttribute('tipo');
            $stato['inquadramento']['fonteNome']     = $fonte->getAttribute('nome');
            $stato['inquadramento']['fonteData']     = $fonte->getAttribute('dataConsultazione');
            $stato['inquadramento']['fonteModalita'] = $fonte->getAttribute('modalita');
        }

        // --- genesi
        foreach (['tipoGenesi', 'processo', 'rocciaIncassante'] as $campo) {
            $stato['genesi'][$campo] = Xml::testo($doc, '/geologia/genesi/' . $campo);
        }

        // --- assetto strutturale
        $giacitura = Xml::primo($doc, '/geologia/assettoStrutturale/giacitura');
        if ($giacitura instanceof DOMElement) {
            $stato['assetto']['immersione']   = $giacitura->getAttribute('immersione');
            $stato['assetto']['inclinazione'] = $giacitura->getAttribute('inclinazione');
        }
        $stato['assetto']['fratturazione'] = Xml::testo($doc, '/geologia/assettoStrutturale/fratturazione');
        $stato['assetto']['note']          = Xml::testo($doc, '/geologia/assettoStrutturale/note');

        foreach (Xml::elenco($doc, '/geologia/assettoStrutturale/sistemiDiscontinuita/sistema') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $stato['sistemi'][] = [
                'direzione' => $nodo->getAttribute('direzione'),
                'tipo'      => $nodo->getAttribute('tipo'),
            ];
        }

        // --- morfologie
        foreach (Xml::elenco($doc, '/geologia/morfologie/morfologia') as $nodo) {
            $stato['morfologie'][] = array_merge(self::CAMPI_MORFOLOGIA, [
                'tipo'        => Xml::testo($nodo, 'tipo'),
                'descrizione' => Xml::testo($nodo, 'descrizione'),
                'zonaCavita'  => Xml::testo($nodo, 'zonaCavita'),
                'fotoRif'     => Xml::testo($nodo, 'fotoRif'),
            ]);
        }

        // --- idrogeologia
        foreach (['acquifero', 'permeabilita', 'ruoloIdrogeologico', 'serieMisureRif', 'note'] as $campo) {
            $stato['idrogeologia'][$campo] = Xml::testo($doc, '/geologia/idrogeologia/' . $campo);
        }
        foreach (Xml::elenco($doc, '/geologia/idrogeologia/sorgentiCollegate/sorgente') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $stato['sorgenti'][] = [
                'nome'     => $nodo->getAttribute('nome'),
                'distanza' => $nodo->getAttribute('distanza'),
            ];
        }
        foreach (Xml::elenco($doc, '/geologia/idrogeologia/tracciamenti/tracciamento') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $stato['tracciamenti'][] = [
                'data'       => $nodo->getAttribute('data'),
                'tracciante' => $nodo->getAttribute('tracciante'),
                'esito'      => $nodo->getAttribute('esito'),
            ];
        }

        // --- rischi
        foreach (Xml::elenco($doc, '/geologia/rischi/rischio') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $stato['rischi'][] = [
                'tipo'        => $nodo->getAttribute('tipo'),
                'livello'     => $nodo->getAttribute('livello'),
                'descrizione' => trim($nodo->textContent),
            ];
        }

        // --- campioni
        foreach (Xml::elenco($doc, '/geologia/campioni/campione') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $voce = array_merge(self::CAMPI_CAMPIONE, [
                'tipo'             => Xml::testo($nodo, 'tipo'),
                'data'             => Xml::testo($nodo, 'data'),
                'zonaCavita'       => Xml::testo($nodo, 'zonaCavita'),
                'finalita'         => Xml::testo($nodo, 'finalita'),
                'depositatoPresso' => Xml::testo($nodo, 'depositatoPresso'),
                'esitoAnalisi'     => Xml::testo($nodo, 'esitoAnalisi'),
                'allegatoRif'      => Xml::testo($nodo, 'allegatoRif'),
                'autorizzazione'   => Xml::testo($nodo, 'autorizzazione'),
            ]);
            $prelevato = Xml::primo($nodo, 'prelevatoDa');
            $voce['prelevatoDa'] = $prelevato instanceof DOMElement
                ? $prelevato->getAttribute('esploratoreId') : '';
            $stato['campioni'][] = $voce;
        }

        return $stato;
    }

    /**
     * Applica una modifica allo stato e riscrive il file.
     *
     * Tutto passa da qui e sotto lock: la sezione ha sette parti e ciascuna ha
     * il proprio modulo, quindi due salvataggi vicini sullo stesso ipogeo sono
     * la normalita e non l'eccezione.
     *
     * @param callable(array<string,mixed>):array<string,mixed> $trasforma
     */
    private static function modifica(string $codice, callable $trasforma): void
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new GeologiaEccezione('Ipogeo non trovato: ' . $codice);
        }
        Percorsi::assicuraCartella((string) self::cartella($codice));

        Xml::conLock($percorso, static function () use ($codice, $percorso, $trasforma): void {
            $stato = $trasforma(self::leggi($codice));
            self::scrivi($codice, $percorso, $stato);
        });

        IndiceIpogei::aggiorna($codice);
    }

    /** @param array<string,mixed> $stato */
    private static function scrivi(string $codice, string $percorso, array $stato): void
    {
        $doc = Xml::nuovo('geologia', [
            'versioneSchema' => self::VERSIONE_SCHEMA,
            'codiceIpogeo'   => $codice,
        ]);
        $radice = $doc->documentElement;
        if ($radice === null) {
            throw new GeologiaEccezione('Creazione del documento non riuscita.');
        }

        // --- inquadramento
        $inq = Xml::aggiungi($radice, 'inquadramento');
        foreach (['litologia', 'formazione', 'unitaGeologica', 'etaFormazione',
                  'foglioGeologico'] as $campo) {
            Xml::imposta($inq, $campo, (string) $stato['inquadramento'][$campo]);
        }
        Xml::aggiungi($inq, 'cronostratigrafia', null, [
            'sistema' => (string) $stato['inquadramento']['sistemaCrono'],
            'serie'   => (string) $stato['inquadramento']['serieCrono'],
        ]);
        Xml::aggiungi($inq, 'fonte', null, [
            'tipo'              => (string) $stato['inquadramento']['fonteTipo'],
            'nome'              => (string) $stato['inquadramento']['fonteNome'],
            'dataConsultazione' => (string) $stato['inquadramento']['fonteData'],
            'modalita'          => self::daVocabolario(
                $stato['inquadramento']['fonteModalita'], self::MODALITA_FONTE),
        ]);

        // --- genesi
        $gen = Xml::aggiungi($radice, 'genesi');
        Xml::imposta($gen, 'tipoGenesi', self::daVocabolario($stato['genesi']['tipoGenesi'], self::TIPI_GENESI));
        Xml::imposta($gen, 'processo', (string) $stato['genesi']['processo'], true);
        Xml::imposta($gen, 'rocciaIncassante', (string) $stato['genesi']['rocciaIncassante']);

        // --- assetto
        $ass = Xml::aggiungi($radice, 'assettoStrutturale');
        Xml::aggiungi($ass, 'giacitura', null, [
            'immersione'   => (string) $stato['assetto']['immersione'],
            'inclinazione' => (string) $stato['assetto']['inclinazione'],
            'unita'        => 'gradi',
        ]);
        Xml::imposta($ass, 'fratturazione',
            self::daVocabolario($stato['assetto']['fratturazione'], self::FRATTURAZIONE));
        $sistemi = Xml::aggiungi($ass, 'sistemiDiscontinuita');
        foreach ((array) $stato['sistemi'] as $sistema) {
            $direzione = trim((string) ($sistema['direzione'] ?? ''));
            if ($direzione === '') {
                continue;
            }
            Xml::aggiungi($sistemi, 'sistema', null, [
                'direzione' => $direzione,
                'tipo'      => trim((string) ($sistema['tipo'] ?? '')),
            ]);
        }
        Xml::imposta($ass, 'note', (string) $stato['assetto']['note'], true);

        // --- morfologie
        $morf = Xml::aggiungi($radice, 'morfologie');
        $n = 0;
        foreach ((array) $stato['morfologie'] as $voce) {
            $descrizione = trim((string) ($voce['descrizione'] ?? ''));
            $tipo        = self::daVocabolario($voce['tipo'] ?? '', self::TIPI_MORFOLOGIA);
            if ($descrizione === '' && $tipo === '') {
                continue;
            }
            $n++;
            $nodo = Xml::aggiungi($morf, 'morfologia', null, ['n' => (string) $n]);
            Xml::imposta($nodo, 'tipo', $tipo !== '' ? $tipo : 'altro');
            Xml::imposta($nodo, 'descrizione', $descrizione, true);
            Xml::imposta($nodo, 'zonaCavita', trim((string) ($voce['zonaCavita'] ?? '')));
            Xml::imposta($nodo, 'fotoRif', trim((string) ($voce['fotoRif'] ?? '')));
        }

        // --- idrogeologia
        $idro = Xml::aggiungi($radice, 'idrogeologia');
        Xml::imposta($idro, 'acquifero', (string) $stato['idrogeologia']['acquifero']);
        Xml::imposta($idro, 'permeabilita',
            self::daVocabolario($stato['idrogeologia']['permeabilita'], self::PERMEABILITA));
        Xml::imposta($idro, 'ruoloIdrogeologico',
            self::daVocabolario($stato['idrogeologia']['ruoloIdrogeologico'], self::RUOLI_IDRO));

        $sorgenti = Xml::aggiungi($idro, 'sorgentiCollegate');
        foreach ((array) $stato['sorgenti'] as $sorgente) {
            $nome = trim((string) ($sorgente['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            Xml::aggiungi($sorgenti, 'sorgente', null, [
                'nome'     => $nome,
                'distanza' => trim((string) ($sorgente['distanza'] ?? '')),
                'unita'    => 'm',
            ]);
        }

        $tracciamenti = Xml::aggiungi($idro, 'tracciamenti');
        foreach ((array) $stato['tracciamenti'] as $tracciamento) {
            $tracciante = trim((string) ($tracciamento['tracciante'] ?? ''));
            if ($tracciante === '') {
                continue;
            }
            Xml::aggiungi($tracciamenti, 'tracciamento', null, [
                'data'       => trim((string) ($tracciamento['data'] ?? '')),
                'tracciante' => $tracciante,
                'esito'      => trim((string) ($tracciamento['esito'] ?? '')),
            ]);
        }

        Xml::imposta($idro, 'serieMisureRif', (string) $stato['idrogeologia']['serieMisureRif']);
        Xml::imposta($idro, 'note', (string) $stato['idrogeologia']['note'], true);

        // --- rischi
        $risc = Xml::aggiungi($radice, 'rischi');
        foreach ((array) $stato['rischi'] as $rischio) {
            $tipo = self::daVocabolario($rischio['tipo'] ?? '', self::TIPI_RISCHIO);
            if ($tipo === '') {
                continue;
            }
            $livello = self::daVocabolario($rischio['livello'] ?? '', self::LIVELLI_RISCHIO);
            Xml::aggiungi($risc, 'rischio', trim((string) ($rischio['descrizione'] ?? '')), [
                'tipo'    => $tipo,
                'livello' => $livello !== '' ? $livello : 'basso',
            ], true);
        }

        // --- campioni
        $camp = Xml::aggiungi($radice, 'campioni');
        $n = 0;
        foreach ((array) $stato['campioni'] as $campione) {
            $tipo = trim((string) ($campione['tipo'] ?? ''));
            if ($tipo === '') {
                continue;
            }
            $n++;
            $nodo = Xml::aggiungi($camp, 'campione', null, [
                'progressivo' => (string) $n,
                'sigla'       => self::SIGLA,
            ]);
            Xml::imposta($nodo, 'tipo', $tipo);
            Xml::imposta($nodo, 'data', trim((string) ($campione['data'] ?? '')));
            Xml::aggiungi($nodo, 'prelevatoDa', null, [
                'esploratoreId' => trim((string) ($campione['prelevatoDa'] ?? '')),
            ]);
            foreach (['zonaCavita', 'finalita', 'depositatoPresso', 'allegatoRif'] as $campo) {
                Xml::imposta($nodo, $campo, trim((string) ($campione[$campo] ?? '')));
            }
            Xml::imposta($nodo, 'esitoAnalisi', (string) ($campione['esitoAnalisi'] ?? ''), true);
            Xml::imposta($nodo, 'autorizzazione', (string) ($campione['autorizzazione'] ?? ''), true);
        }

        Xml::salva($doc, $percorso, is_file(self::xsd()) ? self::xsd() : null);
    }

    /** Riporta un valore a una chiave del vocabolario; vuoto se non lo e. */
    private static function daVocabolario(mixed $valore, array $vocabolario): string
    {
        $valore = trim((string) $valore);

        return isset($vocabolario[$valore]) && $valore !== '' ? $valore : '';
    }

    private static function xsd(): string
    {
        return Percorsi::schema('geologia.xsd');
    }
}
