<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Archeologia.php
 *  Descrizione ..: Inquadramento archeologico, evidenze, tutela e indagini
 *                  (6.15).
 *
 *                  Sezione particolarmente rilevante per le cavita artificiali,
 *                  dove il periodo di riferimento e chiave sia di lettura sia
 *                  di ricerca. Un cunicolo romano riusato come ricovero
 *                  antiaereo appartiene a due epoche, e la scheda deve dirlo:
 *                  per questo accanto al periodo principale c'e un elenco di
 *                  periodi secondari e uno di funzioni successive.
 *
 *                  La presenza di un vincolo alimenta un avviso in scheda: chi
 *                  programma un'uscita deve vedere subito che serve
 *                  un'autorizzazione, informazione che oggi vive nella memoria
 *                  di chi ha fatto le pratiche.
 *  Versione .....: 1.0.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.0.0 2026-08-06  D.Candela  Via il punto doppio in coda all'avviso di
 *                                vincolo, quando le prescrizioni finiscono con
 *                                un punto loro.
 *  0.12.0  2026-08-06  D.Candela  Prima stesura (fase 7d).
 * ============================================================================
 */

final class Archeologia
{
    public const VERSIONE_SCHEMA = '1.0';
    public const SIGLA = 'AR';

    public const TIPI_EVIDENZA = [
        'struttura muraria'      => 'Struttura muraria',
        'tecnica costruttiva'    => 'Tecnica costruttiva',
        'iscrizione'             => 'Iscrizione',
        'graffito'               => 'Graffito',
        'ceramica'               => 'Ceramica',
        'affresco'               => 'Affresco',
        'mosaico'                => 'Mosaico',
        'sepoltura'              => 'Sepoltura',
        'impianto idraulico'     => 'Impianto idraulico',
        'traccia di strumenti'   => 'Traccia di strumenti',
        'materiale di reimpiego' => 'Materiale di reimpiego',
        'altro'                  => 'Altro',
    ];

    public const CONSERVAZIONE = [
        ''            => '—',
        'ottimo'      => 'Ottimo',
        'buono'       => 'Buono',
        'discreto'    => 'Discreto',
        'degradato'   => 'Degradato',
        'in pericolo' => 'In pericolo',
        'perduto'     => 'Perduto',
    ];

    /** Quanto stretta e la datazione: dichiararlo evita false precisioni. */
    public const PRECISIONI = [
        'ignota'   => 'Ignota',
        'anno'     => 'Anno',
        'decennio' => 'Decennio',
        'secolo'   => 'Secolo',
        'periodo'  => 'Periodo',
    ];

    public const TIPI_INDAGINE = [
        'ricognizione'   => 'Ricognizione',
        'scavo'          => 'Scavo',
        'documentazione' => 'Documentazione',
        'rilievo'        => 'Rilievo',
        'datazione'      => 'Datazione',
    ];

    public const CAMPI_INQUADRAMENTO = [
        'periodoPrincipale' => '', 'periodiSecondari' => '',
        'datazioneDa' => '', 'datazioneA' => '',
        'datazionePrecisione' => 'ignota', 'datazioneCriterio' => '',
        'funzioneOriginaria' => '', 'contestoTopografico' => '', 'sintesi' => '',
    ];

    public const CAMPI_EVIDENZA = [
        'tipo' => 'altro', 'descrizione' => '', 'zonaCavita' => '', 'periodo' => '',
        'statoConservazione' => '', 'fotoRif' => '', 'rilievoRif' => '', 'biblioRif' => '',
    ];

    public const CAMPI_TUTELA = [
        'vincolo' => '0', 'tipoVincolo' => '', 'enteCompetente' => '',
        'riferimentoProvvedimento' => '', 'dataProvvedimento' => '',
        'prescrizioni' => '', 'allegatoRif' => '',
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

    /** @return array<int,array<string,mixed>> */
    public static function evidenze(string $codice): array
    {
        return self::leggi($codice)['evidenze'];
    }

    /** @return array<string,mixed>|null */
    public static function evidenza(string $codice, int $progressivo): ?array
    {
        foreach (self::evidenze($codice) as $voce) {
            if ((int) $voce['progressivo'] === $progressivo) {
                return $voce;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    public static function tutela(string $codice): array
    {
        return self::leggi($codice)['tutela'];
    }

    /** @return array<int,array<string,mixed>> */
    public static function indagini(string $codice): array
    {
        return self::leggi($codice)['indagini'];
    }

    /**
     * Quante voci archeologiche ha l'ipogeo.
     *
     * L'inquadramento conta come una voce se e stato compilato: un ipogeo con
     * il solo periodo dichiarato ha comunque contenuto archeologico, e un
     * conteggio a zero lo farebbe sparire dalle ricerche.
     */
    public static function conta(string $codice): int
    {
        $stato = self::leggi($codice);

        $totale = count($stato['evidenze']) + count($stato['indagini']);
        if ((string) $stato['inquadramento']['periodoPrincipale'] !== ''
            || (string) $stato['inquadramento']['sintesi'] !== '') {
            $totale++;
        }
        if ((string) $stato['tutela']['vincolo'] === '1') {
            $totale++;
        }

        return $totale;
    }

    /** Periodo principale, per la colonna dell'indice e per la ricerca. */
    public static function periodoPrincipale(string $codice): string
    {
        return (string) self::inquadramento($codice)['periodoPrincipale'];
    }

    /**
     * Avvisi di tutela per la scheda.
     *
     * @return array<int,array{livello:string,titolo:string,testo:string}>
     */
    public static function avvisi(string $codice): array
    {
        $tutela = self::tutela($codice);
        if ((string) $tutela['vincolo'] !== '1') {
            return [];
        }

        $pezzi = [];
        if ((string) $tutela['tipoVincolo'] !== '') {
            $pezzi[] = (string) $tutela['tipoVincolo'];
        }
        if ((string) $tutela['enteCompetente'] !== '') {
            $pezzi[] = 'Ente competente: ' . (string) $tutela['enteCompetente'];
        }
        if ((string) $tutela['riferimentoProvvedimento'] !== '') {
            $pezzi[] = 'Provvedimento ' . (string) $tutela['riferimentoProvvedimento']
                . ((string) $tutela['dataProvvedimento'] !== ''
                    ? ' del ' . (string) $tutela['dataProvvedimento'] : '');
        }
        if ((string) $tutela['prescrizioni'] !== '') {
            $pezzi[] = (string) $tutela['prescrizioni'];
        }

        // Le prescrizioni sono testo libero e di solito finiscono gia con un
        // punto: senza togliere quello dei pezzi, l'avviso chiudeva con due.
        $pezzi = array_map(static fn (string $p): string => rtrim(trim($p), '.'), $pezzi);

        return [[
            'livello' => 'warning',
            'titolo'  => 'Cavita sottoposta a vincolo',
            'testo'   => $pezzi === []
                ? 'La cavita risulta vincolata; il dettaglio non e stato compilato.'
                : implode('. ', $pezzi) . '.',
        ]];
    }

    // ========================================================================
    //  SCRITTURA
    // ========================================================================

    /** @param array<string,mixed> $dati */
    public static function salvaInquadramento(string $codice, array $dati): void
    {
        $percorso = self::apri($codice);

        self::validaInquadramento($dati);

        Xml::conLock($percorso, static function () use ($codice, $dati, $percorso): void {
            $stato = self::leggi($codice);
            $stato['inquadramento'] = array_merge(self::CAMPI_INQUADRAMENTO, $dati);
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('inquadramento_salvato', self::catalogoDi($codice), $codice, self::SIGLA,
                (string) $stato['inquadramento']['periodoPrincipale']);
        });
    }

    /** @param array<string,mixed> $dati */
    public static function salvaTutela(string $codice, array $dati): void
    {
        $percorso = self::apri($codice);

        Xml::conLock($percorso, static function () use ($codice, $dati, $percorso): void {
            $stato = self::leggi($codice);
            $stato['tutela'] = array_merge(self::CAMPI_TUTELA, $dati);
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('tutela_salvata', self::catalogoDi($codice), $codice, self::SIGLA,
                (string) $stato['tutela']['vincolo'] === '1' ? 'vincolata' : 'non vincolata');
        });
    }

    /**
     * Aggiunge un'evidenza e ne restituisce il progressivo.
     *
     * @param array<string,mixed> $dati
     */
    public static function aggiungiEvidenza(string $codice, array $dati): int
    {
        $percorso = self::apri($codice);

        self::validaEvidenza($dati);

        return Xml::conLock($percorso, static function () use ($codice, $dati, $percorso): int {
            $stato = self::leggi($codice);

            $progressivo = $stato['ultimoProgressivo'] + 1;
            $voce = array_merge(self::CAMPI_EVIDENZA, $dati);
            $voce['progressivo'] = $progressivo;

            $stato['evidenze'][] = $voce;
            $stato['ultimoProgressivo'] = $progressivo;
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('evidenza_aggiunta', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo) . ' ' . (string) $voce['tipo']);

            return $progressivo;
        });
    }

    /** @param array<string,mixed> $dati */
    public static function aggiornaEvidenza(string $codice, int $progressivo, array $dati): void
    {
        $percorso = self::apri($codice);

        self::validaEvidenza($dati);

        Xml::conLock($percorso, static function () use ($codice, $progressivo, $dati, $percorso): void {
            $stato = self::leggi($codice);

            $trovata = false;
            foreach ($stato['evidenze'] as $i => $voce) {
                if ((int) $voce['progressivo'] !== $progressivo) {
                    continue;
                }
                $nuova = array_merge(self::CAMPI_EVIDENZA, $dati);
                $nuova['progressivo'] = $progressivo;
                $stato['evidenze'][$i] = $nuova;
                $trovata = true;
            }

            if (!$trovata) {
                throw new ArcheologiaEccezione(
                    'Evidenza non trovata: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
            }

            self::scrivi($codice, $stato, $percorso);

            Log::modifica('evidenza_aggiornata', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo));
        });
    }

    public static function eliminaEvidenza(string $codice, int $progressivo): void
    {
        $percorso = self::apri($codice);

        Xml::conLock($percorso, static function () use ($codice, $progressivo, $percorso): void {
            $stato = self::leggi($codice);

            $rimaste = array_values(array_filter(
                $stato['evidenze'],
                static fn (array $e): bool => (int) $e['progressivo'] !== $progressivo
            ));

            if (count($rimaste) === count($stato['evidenze'])) {
                throw new ArcheologiaEccezione(
                    'Evidenza non trovata: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
            }

            $stato['evidenze'] = $rimaste;
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('evidenza_rimossa', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo));
        });
    }

    /** @param array<string,mixed> $dati */
    public static function aggiungiIndagine(string $codice, array $dati): void
    {
        $percorso = self::apri($codice);

        $tipo = trim((string) ($dati['tipo'] ?? ''));
        if (!isset(self::TIPI_INDAGINE[$tipo])) {
            throw new ArcheologiaEccezione('Tipo di indagine non riconosciuto: ' . $tipo);
        }

        $data = trim((string) ($dati['data'] ?? ''));
        if ($data !== '' && Scientifici::normalizzaData($data) === '') {
            throw new ArcheologiaEccezione('Data dell\'indagine non riconosciuta.');
        }

        Xml::conLock($percorso, static function () use ($codice, $dati, $percorso): void {
            $stato = self::leggi($codice);
            $stato['indagini'][] = [
                'tipo'            => trim((string) ($dati['tipo'] ?? '')),
                'data'            => Scientifici::normalizzaData((string) ($dati['data'] ?? '')),
                'soggetto'        => trim((string) ($dati['soggetto'] ?? '')),
                'esplorazioneRif' => trim((string) ($dati['esplorazioneRif'] ?? '')),
                'esito'           => (string) ($dati['esito'] ?? ''),
                'allegatoRif'     => trim((string) ($dati['allegatoRif'] ?? '')),
            ];
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('indagine_aggiunta', self::catalogoDi($codice), $codice, self::SIGLA,
                (string) $dati['tipo']);
        });
    }

    public static function eliminaIndagine(string $codice, int $posizione): void
    {
        $percorso = self::apri($codice);

        Xml::conLock($percorso, static function () use ($codice, $posizione, $percorso): void {
            $stato = self::leggi($codice);

            if (!isset($stato['indagini'][$posizione])) {
                throw new ArcheologiaEccezione('Indagine non trovata.');
            }

            unset($stato['indagini'][$posizione]);
            $stato['indagini'] = array_values($stato['indagini']);
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('indagine_rimossa', self::catalogoDi($codice), $codice, self::SIGLA,
                'posizione ' . $posizione);
        });
    }

    // ========================================================================
    //  INTERNI
    // ========================================================================

    private static function apri(string $codice): string
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new ArcheologiaEccezione('Ipogeo non trovato: ' . $codice);
        }
        Percorsi::assicuraCartella((string) self::cartella($codice));

        return $percorso;
    }

    /**
     * @return array{inquadramento:array<string,mixed>,evidenze:array<int,array<string,mixed>>,tutela:array<string,mixed>,indagini:array<int,array<string,mixed>>,ultimoProgressivo:int}
     */
    private static function leggi(string $codice): array
    {
        $vuoto = [
            'inquadramento'     => self::CAMPI_INQUADRAMENTO,
            'evidenze'          => [],
            'tutela'            => self::CAMPI_TUTELA,
            'indagini'          => [],
            'ultimoProgressivo' => 0,
        ];

        $percorso = self::percorso($codice);
        if ($percorso === null || !is_file($percorso)) {
            return $vuoto;
        }

        try {
            $doc = Xml::carica($percorso);
        } catch (Throwable $e) {
            Log::errore('Archeologia illeggibile: ' . $percorso . ' — ' . $e->getMessage());
            return $vuoto;
        }

        $radice = $doc->documentElement;
        if ($radice === null) {
            return $vuoto;
        }

        // --- inquadramento
        $inquadramento = self::CAMPI_INQUADRAMENTO;
        $inquadramento['periodoPrincipale'] = Xml::testo($doc, '/archeologia/inquadramento/periodoPrincipale');

        $secondari = [];
        foreach (Xml::elenco($doc, '/archeologia/inquadramento/periodiSecondari/periodo') as $nodo) {
            $valore = trim($nodo->textContent);
            if ($valore !== '') {
                $secondari[] = $valore;
            }
        }
        $inquadramento['periodiSecondari'] = implode(',', $secondari);

        $datazione = Xml::primo($doc, '/archeologia/inquadramento/datazione');
        if ($datazione instanceof DOMElement) {
            $inquadramento['datazioneDa']         = $datazione->getAttribute('da');
            $inquadramento['datazioneA']          = $datazione->getAttribute('a');
            $inquadramento['datazionePrecisione'] = $datazione->getAttribute('precisione');
            $inquadramento['datazioneCriterio']   = $datazione->getAttribute('criterio');
        }

        foreach (['funzioneOriginaria', 'contestoTopografico', 'sintesi'] as $campo) {
            $inquadramento[$campo] = Xml::testo($doc, '/archeologia/inquadramento/' . $campo);
        }

        $funzioni = [];
        foreach (Xml::elenco($doc, '/archeologia/inquadramento/funzioniSuccessive/funzione') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $funzioni[] = [
                'periodo' => $nodo->getAttribute('periodo'),
                'testo'   => trim($nodo->textContent),
            ];
        }
        $inquadramento['funzioniSuccessive'] = $funzioni;

        // --- evidenze
        $evidenze = [];
        foreach (Xml::elenco($doc, '/archeologia/evidenze/evidenza') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $voce = ['progressivo' => (int) $nodo->getAttribute('progressivo')];
            foreach (array_keys(self::CAMPI_EVIDENZA) as $campo) {
                $voce[$campo] = Xml::testo($nodo, $campo);
            }
            $evidenze[] = array_merge(self::CAMPI_EVIDENZA, $voce);
        }
        usort($evidenze, static fn (array $a, array $b): int => $a['progressivo'] <=> $b['progressivo']);

        // --- tutela
        $tutela = self::CAMPI_TUTELA;
        foreach (array_keys(self::CAMPI_TUTELA) as $campo) {
            $tutela[$campo] = Xml::testo($doc, '/archeologia/tutela/' . $campo);
        }

        // --- indagini
        $indagini = [];
        foreach (Xml::elenco($doc, '/archeologia/indagini/indagine') as $nodo) {
            $indagini[] = [
                'tipo'            => Xml::testo($nodo, 'tipo'),
                'data'            => Xml::testo($nodo, 'data'),
                'soggetto'        => Xml::testo($nodo, 'soggetto'),
                'esplorazioneRif' => Xml::testo($nodo, 'esplorazioneRif'),
                'esito'           => Xml::testo($nodo, 'esito'),
                'allegatoRif'     => Xml::testo($nodo, 'allegatoRif'),
            ];
        }

        $ultimo = (int) $radice->getAttribute('ultimoProgressivo');
        foreach ($evidenze as $evidenza) {
            $ultimo = max($ultimo, (int) $evidenza['progressivo']);
        }

        return [
            'inquadramento'     => $inquadramento,
            'evidenze'          => $evidenze,
            'tutela'            => $tutela,
            'indagini'          => $indagini,
            'ultimoProgressivo' => $ultimo,
        ];
    }

    /** @param array<string,mixed> $stato */
    private static function scrivi(string $codice, array $stato, string $percorso): void
    {
        $doc = Xml::nuovo('archeologia', [
            'versioneSchema'    => self::VERSIONE_SCHEMA,
            'codiceIpogeo'      => $codice,
            'ultimoProgressivo' => (string) $stato['ultimoProgressivo'],
        ]);
        $radice = $doc->documentElement;

        // --- inquadramento
        $i = array_merge(self::CAMPI_INQUADRAMENTO, $stato['inquadramento']);
        $nodo = Xml::aggiungi($radice, 'inquadramento');

        Xml::imposta($nodo, 'periodoPrincipale', (string) $i['periodoPrincipale']);

        $secondari = Xml::imposta($nodo, 'periodiSecondari', null);
        $elenco = is_array($i['periodiSecondari'])
            ? $i['periodiSecondari']
            : (preg_split('/\s*,\s*/', (string) $i['periodiSecondari']) ?: []);
        foreach ($elenco as $periodo) {
            if (trim((string) $periodo) !== '') {
                Xml::aggiungi($secondari, 'periodo', trim((string) $periodo));
            }
        }

        $datazione = Xml::imposta($nodo, 'datazione', null);
        $datazione->setAttribute('da', (string) $i['datazioneDa']);
        $datazione->setAttribute('a', (string) $i['datazioneA']);
        $precisione = (string) $i['datazionePrecisione'];
        $datazione->setAttribute('precisione',
            isset(self::PRECISIONI[$precisione]) ? $precisione : 'ignota');
        $datazione->setAttribute('criterio', (string) $i['datazioneCriterio']);

        Xml::imposta($nodo, 'funzioneOriginaria', (string) $i['funzioneOriginaria']);

        $funzioni = Xml::imposta($nodo, 'funzioniSuccessive', null);
        foreach ((array) ($i['funzioniSuccessive'] ?? []) as $funzione) {
            $testo = trim((string) ($funzione['testo'] ?? ''));
            if ($testo === '') {
                continue;
            }
            Xml::aggiungi($funzioni, 'funzione', $testo,
                ['periodo' => trim((string) ($funzione['periodo'] ?? ''))]);
        }

        Xml::imposta($nodo, 'contestoTopografico', (string) $i['contestoTopografico']);
        Xml::imposta($nodo, 'sintesi', (string) $i['sintesi'], true);

        // --- evidenze
        $contenitore = Xml::aggiungi($radice, 'evidenze');
        foreach ($stato['evidenze'] as $evidenza) {
            $evidenza = array_merge(self::CAMPI_EVIDENZA, $evidenza);
            $nodo = Xml::aggiungi($contenitore, 'evidenza', null, [
                'progressivo' => (string) $evidenza['progressivo'],
                'sigla'       => self::SIGLA,
            ]);

            $tipo = (string) $evidenza['tipo'];
            Xml::imposta($nodo, 'tipo', isset(self::TIPI_EVIDENZA[$tipo]) ? $tipo : 'altro');
            Xml::imposta($nodo, 'descrizione', (string) $evidenza['descrizione'], true);
            Xml::imposta($nodo, 'zonaCavita', (string) $evidenza['zonaCavita']);
            Xml::imposta($nodo, 'periodo', (string) $evidenza['periodo']);

            $stato_c = (string) $evidenza['statoConservazione'];
            Xml::imposta($nodo, 'statoConservazione',
                isset(self::CONSERVAZIONE[$stato_c]) ? $stato_c : '');

            foreach (['fotoRif', 'rilievoRif', 'biblioRif'] as $campo) {
                Xml::imposta($nodo, $campo, (string) $evidenza[$campo]);
            }
        }

        // --- tutela
        $t = array_merge(self::CAMPI_TUTELA, $stato['tutela']);
        $nodo = Xml::aggiungi($radice, 'tutela');
        Xml::imposta($nodo, 'vincolo', (string) $t['vincolo'] === '1' ? '1' : '0');
        foreach (['tipoVincolo', 'enteCompetente', 'riferimentoProvvedimento',
                  'dataProvvedimento', 'allegatoRif'] as $campo) {
            Xml::imposta($nodo, $campo, trim((string) $t[$campo]));
        }
        Xml::imposta($nodo, 'prescrizioni', (string) $t['prescrizioni'], true);

        // --- indagini
        $contenitore = Xml::aggiungi($radice, 'indagini');
        foreach ($stato['indagini'] as $indagine) {
            $nodo = Xml::aggiungi($contenitore, 'indagine');
            $tipo = (string) ($indagine['tipo'] ?? '');
            Xml::imposta($nodo, 'tipo', isset(self::TIPI_INDAGINE[$tipo]) ? $tipo : 'ricognizione');
            Xml::imposta($nodo, 'data', (string) ($indagine['data'] ?? ''));
            Xml::imposta($nodo, 'soggetto', (string) ($indagine['soggetto'] ?? ''));
            Xml::imposta($nodo, 'esplorazioneRif', (string) ($indagine['esplorazioneRif'] ?? ''));
            Xml::imposta($nodo, 'esito', (string) ($indagine['esito'] ?? ''), true);
            Xml::imposta($nodo, 'allegatoRif', (string) ($indagine['allegatoRif'] ?? ''));
        }

        Xml::salva($doc, $percorso, Percorsi::schema('archeologia.xsd'));
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws ArcheologiaEccezione
     */
    private static function validaInquadramento(array $dati): void
    {
        $periodo = trim((string) ($dati['periodoPrincipale'] ?? ''));
        if ($periodo !== '' && Periodi::trova($periodo) === null) {
            throw new ArcheologiaEccezione(
                'Periodo non presente nel vocabolario: ' . $periodo
                . '. Censiscilo fra le anagrafiche prima di usarlo.');
        }

        $secondari = $dati['periodiSecondari'] ?? '';
        $elenco = is_array($secondari)
            ? $secondari
            : (preg_split('/\s*,\s*/', (string) $secondari) ?: []);
        foreach ($elenco as $codice) {
            $codice = trim((string) $codice);
            if ($codice !== '' && Periodi::trova($codice) === null) {
                throw new ArcheologiaEccezione('Periodo secondario non riconosciuto: ' . $codice);
            }
        }

        $precisione = trim((string) ($dati['datazionePrecisione'] ?? ''));
        if ($precisione !== '' && !isset(self::PRECISIONI[$precisione])) {
            throw new ArcheologiaEccezione('Precisione della datazione non riconosciuta.');
        }

        // Gli anni possono essere negativi: -27 e il 27 a.C., ed e proprio il
        // genere di datazione che questa sezione deve saper esprimere.
        foreach (['datazioneDa', 'datazioneA'] as $campo) {
            $valore = trim((string) ($dati[$campo] ?? ''));
            if ($valore !== '' && !preg_match('/^-?\d{1,5}$/', $valore)) {
                throw new ArcheologiaEccezione(
                    'La datazione va indicata in anni, eventualmente negativi per le date avanti Cristo.');
            }
        }

        $da = trim((string) ($dati['datazioneDa'] ?? ''));
        $a  = trim((string) ($dati['datazioneA'] ?? ''));
        if ($da !== '' && $a !== '' && (int) $da > (int) $a) {
            throw new ArcheologiaEccezione('La datazione iniziale e successiva a quella finale.');
        }
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws ArcheologiaEccezione
     */
    private static function validaEvidenza(array $dati): void
    {
        $tipo = trim((string) ($dati['tipo'] ?? ''));
        if (!isset(self::TIPI_EVIDENZA[$tipo])) {
            throw new ArcheologiaEccezione('Tipo di evidenza non riconosciuto: ' . $tipo);
        }

        if (trim((string) ($dati['descrizione'] ?? '')) === '') {
            throw new ArcheologiaEccezione('La descrizione dell\'evidenza e obbligatoria.');
        }

        $periodo = trim((string) ($dati['periodo'] ?? ''));
        if ($periodo !== '' && Periodi::trova($periodo) === null) {
            throw new ArcheologiaEccezione('Periodo non riconosciuto: ' . $periodo);
        }

        $conservazione = trim((string) ($dati['statoConservazione'] ?? ''));
        if ($conservazione !== '' && !isset(self::CONSERVAZIONE[$conservazione])) {
            throw new ArcheologiaEccezione('Stato di conservazione non riconosciuto.');
        }
    }

    private static function catalogoDi(string $codice): string
    {
        $riga = IndiceIpogei::trova($codice);

        return $riga === null ? '' : (string) ($riga['catalogo'] ?? '');
    }
}
