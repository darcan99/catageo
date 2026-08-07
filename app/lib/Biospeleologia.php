<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Biospeleologia.php
 *  Descrizione ..: Osservazioni faunistiche e colonie di chirotteri (6.14).
 *
 *                  Le colonie hanno un trattamento a se, e non per gusto di
 *                  classificare: sono il dato per cui questa sezione esiste.
 *                  Da esse dipendono le due funzioni che servono davvero —
 *                  l'avviso di periodo critico, che evita che un'uscita
 *                  programmata disturbi uno svernamento, e la riservatezza
 *                  rafforzata, perche l'ubicazione di un roost e un dato
 *                  sensibile per la conservazione.
 *
 *                  La riservatezza della colonia e INDIPENDENTE da quella
 *                  dell'ipogeo e prevale su di essa: una cavita pubblica puo
 *                  ospitare una colonia visibile solo a OPE e ADM.
 *
 *                  Il conteggio ammette il numero esatto oppure il solo
 *                  intervallo minimo-massimo: chi conta pipistrelli in uscita
 *                  al crepuscolo produce quasi sempre una stima, e costringere
 *                  a un numero secco falserebbe il dato.
 *  Versione .....: 0.12.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.0  2026-08-06  D.Candela  Prima stesura (fase 7d).
 * ============================================================================
 */

final class Biospeleologia
{
    public const VERSIONE_SCHEMA = '1.0';
    public const SIGLA = 'BI';

    /** Colonne del CSV dei conteggi di una colonia. */
    public const COLONNE = [
        'data', 'ora', 'colonia', 'specie', 'metodo', 'numero',
        'stima_min', 'stima_max', 'fase', 'temperatura',
        'rilevatore_id', 'provenienza', 'validita', 'note',
    ];

    public const GRUPPI_TASSONOMICI = [
        'chirotteri'        => 'Chirotteri',
        'invertebrati'      => 'Invertebrati',
        'anfibi'            => 'Anfibi',
        'rettili'           => 'Rettili',
        'altri vertebrati'  => 'Altri vertebrati',
        'flora'             => 'Flora',
        'microbiologia'     => 'Microbiologia',
    ];

    /** Quanto la specie dipende dall'ambiente ipogeo. */
    public const CATEGORIE_ECOLOGICHE = [
        ''             => '—',
        'troglobio'    => 'Troglobio (vive solo in grotta)',
        'troglofilo'   => 'Troglofilo (frequenta la grotta)',
        'trogloxeno'   => 'Trogloxeno (occasionale)',
        'accidentale'  => 'Accidentale',
    ];

    /** A cosa serve la cavita alla colonia: determina il periodo critico. */
    public const RUOLI_COLONIA = [
        'svernamento'        => 'Svernamento',
        'riproduzione'       => 'Riproduzione',
        'transito'           => 'Transito',
        'swarming'           => 'Swarming',
        'rifugio temporaneo' => 'Rifugio temporaneo',
    ];

    public const TREND = [
        'ignoto'   => 'Ignoto',
        'crescita' => 'In crescita',
        'stabile'  => 'Stabile',
        'calo'     => 'In calo',
        'estinta'  => 'Estinta',
    ];

    /** Categorie della Lista Rossa IUCN, dalla meno alla piu preoccupante. */
    public const IUCN = [
        ''   => '—',
        'LC' => 'LC — Minore preoccupazione',
        'NT' => 'NT — Quasi minacciata',
        'VU' => 'VU — Vulnerabile',
        'EN' => 'EN — In pericolo',
        'CR' => 'CR — In pericolo critico',
        'DD' => 'DD — Dati insufficienti',
    ];

    public const CAMPI_OSSERVAZIONE = [
        'data' => '', 'ora' => '', 'nomeScientifico' => '', 'nomeComune' => '',
        'gruppoTassonomico' => 'invertebrati', 'classe' => '', 'ordine' => '', 'famiglia' => '',
        'categoriaEcologica' => '', 'endemismo' => '0', 'specieProtetta' => '0',
        'direttivaHabitat' => '', 'listaRossaIucn' => '',
        'zonaCavita' => '', 'puntoMisura' => '', 'numeroIndividui' => '', 'metodo' => '',
        'rilevatore' => '', 'determinatore' => '', 'provenienzaTipo' => 'rilevamento_proprio',
        'fotoRif' => '', 'note' => '',
    ];

    public const CAMPI_COLONIA = [
        'nome' => '', 'specie' => '', 'specieAggiuntive' => '', 'ruolo' => 'svernamento',
        'zonaCavita' => '', 'consistenzaStimata' => '', 'trend' => 'ignoto',
        'riservatezza' => 'riservata',
        'periodoCriticoDal' => '', 'periodoCriticoAl' => '',
        'accessoSconsigliato' => '1', 'prescrizioni' => '',
        'riferimentoNormativo' => '', 'biblioRif' => '', 'note' => '',
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

    /** @param array<string,mixed> $colonia */
    public static function percorsoConteggi(string $codice, array $colonia): ?string
    {
        $cartella = self::cartella($codice);
        $file     = trim((string) ($colonia['serieConteggi'] ?? ''));

        if ($cartella === null || $file === '') {
            return null;
        }

        // basename() e la barriera: il nome viene da un XML modificabile a mano.
        return Percorsi::unisci($cartella, basename($file));
    }

    // ========================================================================
    //  LETTURA
    // ========================================================================

    /** @return array<int,array<string,mixed>> */
    public static function osservazioni(string $codice): array
    {
        return self::leggi($codice)['osservazioni'];
    }

    /** @return array<string,mixed>|null */
    public static function osservazione(string $codice, string $id): ?array
    {
        foreach (self::osservazioni($codice) as $voce) {
            if ((string) $voce['id'] === $id) {
                return $voce;
            }
        }

        return null;
    }

    /**
     * Tutte le colonie, comprese quelle riservate.
     *
     * Da usare solo dove serve la verita completa: conteggi, integrita,
     * ricostruzione dell'indice. Per mostrare qualcosa si usa colonieVisibili().
     *
     * @return array<int,array<string,mixed>>
     */
    public static function colonie(string $codice): array
    {
        return self::leggi($codice)['colonie'];
    }

    /**
     * Colonie visibili con il livello di utenza in uso.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function colonieVisibili(string $codice): array
    {
        return array_values(array_filter(
            self::colonie($codice),
            static fn (array $c): bool => Visibilita::livelloVisibile((string) $c['riservatezza'])
        ));
    }

    /** @return array<string,mixed>|null */
    public static function colonia(string $codice, string $id): ?array
    {
        foreach (self::colonie($codice) as $colonia) {
            if ((string) $colonia['id'] === $id) {
                return $colonia;
            }
        }

        return null;
    }

    public static function conta(string $codice): int
    {
        $stato = self::leggi($codice);

        return count($stato['osservazioni']) + count($stato['colonie']);
    }

    /**
     * Conteggi registrati per una colonia.
     *
     * @param  array<string,mixed> $colonia
     * @return array<int,array<string,string>>
     */
    public static function conteggi(string $codice, array $colonia): array
    {
        $percorso = self::percorsoConteggi($codice, $colonia);
        if ($percorso === null || !is_file($percorso)) {
            return [];
        }

        $righe = [];
        Csv::leggi($percorso, static function (array $riga) use (&$righe): bool {
            $righe[] = $riga;

            return true;
        });

        usort($righe, static fn (array $a, array $b): int =>
            strcmp((string) ($a['data'] ?? ''), (string) ($b['data'] ?? '')));

        return $righe;
    }

    /**
     * Consistenza di un conteggio: il numero esatto, o il centro della stima.
     *
     * Restituisce null se la riga non porta ne l'uno ne l'altra: una riga senza
     * consistenza e comunque una visita registrata, e va conservata.
     *
     * @param array<string,string> $riga
     */
    public static function consistenza(array $riga): ?float
    {
        $numero = Scientifici::aNumero((string) ($riga['numero'] ?? ''));
        if ($numero !== null) {
            return $numero;
        }

        $min = Scientifici::aNumero((string) ($riga['stima_min'] ?? ''));
        $max = Scientifici::aNumero((string) ($riga['stima_max'] ?? ''));

        if ($min !== null && $max !== null) {
            return ($min + $max) / 2;
        }

        return $min ?? $max;
    }

    // ========================================================================
    //  PERIODO CRITICO
    // ========================================================================

    /**
     * True se la data cade dentro il periodo critico della colonia.
     *
     * Il periodo e RICORRENTE ogni anno e si scrive "MM-GG": uno svernamento
     * va dal 1 novembre al 31 marzo, cioe scavalca il capodanno. Trattarlo come
     * un intervallo ordinato darebbe un intervallo vuoto e l'avviso non
     * comparirebbe mai proprio nei mesi in cui serve.
     *
     * @param array<string,mixed> $colonia
     */
    public static function inPeriodoCritico(array $colonia, string $data = ''): bool
    {
        $dal = trim((string) ($colonia['periodoCriticoDal'] ?? ''));
        $al  = trim((string) ($colonia['periodoCriticoAl'] ?? ''));

        if (!self::giornoValido($dal) || !self::giornoValido($al)) {
            return false;
        }

        $data  = $data !== '' ? $data : date('Y-m-d');
        $oggi  = substr($data, 5, 5);
        if (!self::giornoValido($oggi)) {
            return false;
        }

        // Intervallo che scavalca l'anno: dentro se e dopo l'inizio OPPURE
        // prima della fine, non "e".
        if ($dal > $al) {
            return $oggi >= $dal || $oggi <= $al;
        }

        return $oggi >= $dal && $oggi <= $al;
    }

    /**
     * Avvisi di conservazione per la scheda di un ipogeo.
     *
     * Una colonia riservata genera comunque l'avviso, ma OSCURATO. Tacerlo
     * sarebbe il contrario di cio per cui questi dati si raccolgono: chi
     * programma un'uscita e proprio la persona che deve sapere di non entrare,
     * ed e quasi sempre chi non ha diritto a vedere il roost.
     *
     * L'avviso oscurato dice il periodo e la ragione — conservazione — e tace
     * nome, specie e zona: e la stessa informazione di un cartello di
     * chiusura temporanea, che non rivela dove si trova la colonia.
     *
     * @return array<int,array{livello:string,titolo:string,testo:string}>
     */
    public static function avvisi(string $codice, string $data = ''): array
    {
        $avvisi = [];

        foreach (self::colonie($codice) as $colonia) {
            if (!self::inPeriodoCritico($colonia, $data)) {
                continue;
            }

            $sconsigliato = (string) $colonia['accessoSconsigliato'] === '1';
            $periodo = 'dal ' . self::giornoLeggibile((string) $colonia['periodoCriticoDal'])
                     . ' al ' . self::giornoLeggibile((string) $colonia['periodoCriticoAl']);

            if (Visibilita::livelloVisibile((string) $colonia['riservatezza'])) {
                $testo = 'Colonia "' . (string) $colonia['nome'] . '"'
                    . ((string) $colonia['specie'] !== '' ? ' (' . (string) $colonia['specie'] . ')' : '')
                    . ' in periodo di '
                    . (self::RUOLI_COLONIA[(string) $colonia['ruolo']] ?? (string) $colonia['ruolo'])
                    . ', ' . $periodo . '.';

                if ((string) $colonia['prescrizioni'] !== '') {
                    $testo .= ' ' . (string) $colonia['prescrizioni'];
                }
            } else {
                $testo = 'La cavità ospita fauna protetta in periodo critico, ' . $periodo . '. '
                    . 'Il dettaglio non è consultabile con il livello di utenza in uso.';
            }

            $avvisi[] = [
                'livello' => $sconsigliato ? 'danger' : 'warning',
                'titolo'  => $sconsigliato
                    ? 'Accesso sconsigliato: periodo critico in corso'
                    : 'Periodo critico in corso',
                'testo'   => $testo,
            ];
        }

        return $avvisi;
    }

    // ========================================================================
    //  SCRITTURA
    // ========================================================================

    /**
     * Crea o aggiorna un'osservazione, e ne restituisce l'identificativo.
     *
     * @param array<string,mixed> $dati
     */
    public static function salvaOsservazione(string $codice, string $id, array $dati): string
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new BiospeleologiaEccezione('Ipogeo non trovato: ' . $codice);
        }
        Percorsi::assicuraCartella((string) self::cartella($codice));

        self::validaOsservazione($dati);

        return Xml::conLock($percorso, static function () use ($codice, $id, $dati, $percorso): string {
            $stato = self::leggi($codice);

            if ($id === '') {
                $id = 'OS' . str_pad((string) (self::massimoId($stato['osservazioni']) + 1), 3, '0', STR_PAD_LEFT);
            }

            $voce = array_merge(self::CAMPI_OSSERVAZIONE, $dati);
            $voce['id'] = $id;

            $sostituita = false;
            foreach ($stato['osservazioni'] as $i => $vecchia) {
                if ((string) $vecchia['id'] === $id) {
                    $stato['osservazioni'][$i] = $voce;
                    $sostituita = true;
                }
            }
            if (!$sostituita) {
                $stato['osservazioni'][] = $voce;
            }

            self::scrivi($codice, $stato, $percorso);

            Log::modifica('osservazione_salvata', self::catalogoDi($codice), $codice, self::SIGLA,
                $id . ' ' . (string) $voce['nomeScientifico']);

            return $id;
        });
    }

    public static function eliminaOsservazione(string $codice, string $id): void
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new BiospeleologiaEccezione('Ipogeo non trovato: ' . $codice);
        }

        Xml::conLock($percorso, static function () use ($codice, $id, $percorso): void {
            $stato = self::leggi($codice);

            $rimaste = array_values(array_filter(
                $stato['osservazioni'],
                static fn (array $o): bool => (string) $o['id'] !== $id
            ));

            if (count($rimaste) === count($stato['osservazioni'])) {
                throw new BiospeleologiaEccezione('Osservazione non trovata: ' . $id);
            }

            $stato['osservazioni'] = $rimaste;
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('osservazione_rimossa', self::catalogoDi($codice), $codice, self::SIGLA, $id);
        });
    }

    /**
     * Crea o aggiorna una colonia.
     *
     * Il numero della colonia e anche il progressivo della sezione: e cosi che
     * si compone il nome del suo file di conteggi, e tenere due contatori per
     * la stessa cosa significherebbe farli divergere.
     *
     * @param array<string,mixed> $dati
     */
    public static function salvaColonia(string $codice, string $id, array $dati): string
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new BiospeleologiaEccezione('Ipogeo non trovato: ' . $codice);
        }
        $cartella = (string) self::cartella($codice);
        Percorsi::assicuraCartella($cartella);

        self::validaColonia($dati);

        return Xml::conLock($percorso, static function () use ($codice, $id, $dati, $percorso, $cartella): string {
            $stato = self::leggi($codice);

            $nuova = $id === '';
            if ($nuova) {
                $stato['ultimoProgressivo']++;
                $id = 'CH' . $stato['ultimoProgressivo'];
            }

            $colonia = array_merge(self::CAMPI_COLONIA, $dati);
            $colonia['id'] = $id;

            $numero = (int) preg_replace('/\D/', '', $id);
            $colonia['progressivo'] = $numero;

            // Il file dei conteggi non segue il nome della colonia: contiene
            // dati e potrebbe essere gia stato scaricato o citato.
            $esistente = null;
            foreach ($stato['colonie'] as $vecchia) {
                if ((string) $vecchia['id'] === $id) {
                    $esistente = $vecchia;
                }
            }
            $colonia['serieConteggi'] = $esistente !== null && (string) $esistente['serieConteggi'] !== ''
                ? (string) $esistente['serieConteggi']
                : Sezioni::nomeFile($codice, self::SIGLA, $numero,
                                    'Conteggi ' . (string) $colonia['nome'] . '.csv');

            $csv = Percorsi::unisci($cartella, $colonia['serieConteggi']);
            if (!is_file($csv)) {
                Csv::scrivi($csv, self::COLONNE, [], true);
            }

            if ($esistente !== null) {
                foreach ($stato['colonie'] as $i => $vecchia) {
                    if ((string) $vecchia['id'] === $id) {
                        $stato['colonie'][$i] = $colonia;
                    }
                }
            } else {
                $stato['colonie'][] = $colonia;
            }

            self::scrivi($codice, $stato, $percorso);

            Log::modifica('colonia_salvata', self::catalogoDi($codice), $codice, self::SIGLA,
                $id . ' ' . (string) $colonia['nome']);

            return $id;
        });
    }

    /** Toglie una colonia; il CSV dei conteggi va in "_rimossi", mai cancellato. */
    public static function eliminaColonia(string $codice, string $id): void
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new BiospeleologiaEccezione('Ipogeo non trovato: ' . $codice);
        }

        Xml::conLock($percorso, static function () use ($codice, $id, $percorso): void {
            $stato = self::leggi($codice);

            $tolta = null;
            $rimaste = [];
            foreach ($stato['colonie'] as $colonia) {
                if ((string) $colonia['id'] === $id) {
                    $tolta = $colonia;
                    continue;
                }
                $rimaste[] = $colonia;
            }

            if ($tolta === null) {
                throw new BiospeleologiaEccezione('Colonia non trovata: ' . $id);
            }

            $csv = self::percorsoConteggi($codice, $tolta);
            if ($csv !== null && is_file($csv)) {
                $deposito = Percorsi::assicuraCartella(Percorsi::unisci(
                    (string) Ipogeo::cartella($codice),
                    $codice . ' - ' . Risorse::CARTELLA_RIMOSSI
                ));
                @rename($csv, Percorsi::unisci($deposito, date('Ymd-His') . '-' . basename($csv)));
            }

            $stato['colonie'] = $rimaste;
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('colonia_rimossa', self::catalogoDi($codice), $codice, self::SIGLA,
                $id . ' ' . (string) $tolta['nome']);
        });
    }

    /**
     * Registra un conteggio nel CSV della colonia.
     *
     * @param array<string,mixed> $conteggio
     */
    public static function aggiungiConteggio(string $codice, string $idColonia, array $conteggio): void
    {
        $colonia = self::colonia($codice, $idColonia);
        if ($colonia === null) {
            throw new BiospeleologiaEccezione('Colonia non trovata: ' . $idColonia);
        }

        $csv = self::percorsoConteggi($codice, $colonia);
        if ($csv === null) {
            throw new BiospeleologiaEccezione('La colonia non ha un file di conteggi.');
        }

        $data = Scientifici::normalizzaData((string) ($conteggio['data'] ?? ''));
        if ($data === '') {
            throw new BiospeleologiaEccezione('La data del conteggio è obbligatoria.');
        }

        $numero = trim((string) ($conteggio['numero'] ?? ''));
        $min    = trim((string) ($conteggio['stima_min'] ?? ''));
        $max    = trim((string) ($conteggio['stima_max'] ?? ''));

        if ($numero === '' && $min === '' && $max === '') {
            throw new BiospeleologiaEccezione(
                'Indicare il numero contato oppure una stima: un conteggio senza '
                . 'consistenza non dice nulla.');
        }
        if ($min !== '' && $max !== '' && (float) $min > (float) $max) {
            throw new BiospeleologiaEccezione('La stima minima supera la massima.');
        }

        $validita = trim((string) ($conteggio['validita'] ?? 'valido'));

        Csv::accoda($csv, self::COLONNE, [
            'data'          => $data,
            'ora'           => Scientifici::normalizzaOra((string) ($conteggio['ora'] ?? '')),
            'colonia'       => $idColonia,
            // La specie si ripete in ogni riga: una colonia mista cambia
            // composizione nel tempo, e il CSV deve dire quale specie e stata
            // contata quel giorno, non quale la colonia ha "in generale".
            'specie'        => trim((string) ($conteggio['specie'] ?? $colonia['specie'])),
            'metodo'        => trim((string) ($conteggio['metodo'] ?? '')),
            'numero'        => $numero,
            'stima_min'     => $min,
            'stima_max'     => $max,
            'fase'          => trim((string) ($conteggio['fase'] ?? $colonia['ruolo'])),
            'temperatura'   => trim((string) ($conteggio['temperatura'] ?? '')),
            'rilevatore_id' => trim((string) ($conteggio['rilevatore'] ?? '')),
            'provenienza'   => trim((string) ($conteggio['provenienza'] ?? 'rilevamento_proprio')),
            'validita'      => isset(Scientifici::VALIDITA[$validita]) ? $validita : 'valido',
            'note'          => trim((string) ($conteggio['note'] ?? '')),
        ]);

        Log::modifica('conteggio_registrato', self::catalogoDi($codice), $codice, self::SIGLA,
            $idColonia . ' ' . $data);
    }

    // ========================================================================
    //  INTERNI
    // ========================================================================

    /**
     * @return array{osservazioni:array<int,array<string,mixed>>,colonie:array<int,array<string,mixed>>,ultimoProgressivo:int}
     */
    private static function leggi(string $codice): array
    {
        $vuoto = ['osservazioni' => [], 'colonie' => [], 'ultimoProgressivo' => 0];

        $percorso = self::percorso($codice);
        if ($percorso === null || !is_file($percorso)) {
            return $vuoto;
        }

        try {
            $doc = Xml::carica($percorso);
        } catch (Throwable $e) {
            Log::errore('Biospeleologia illeggibile: ' . $percorso . ' — ' . $e->getMessage());
            return $vuoto;
        }

        $radice = $doc->documentElement;
        if ($radice === null) {
            return $vuoto;
        }

        $osservazioni = [];
        foreach (Xml::elenco($doc, '/biospeleologia/osservazioni/osservazione') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $voce = ['id' => $nodo->getAttribute('id')];

            $voce['data'] = Xml::testo($nodo, 'data');
            $voce['ora']  = Xml::testo($nodo, 'ora');

            foreach (['nomeScientifico', 'nomeComune', 'gruppoTassonomico', 'classe', 'ordine',
                      'famiglia', 'categoriaEcologica', 'endemismo', 'specieProtetta',
                      'direttivaHabitat', 'listaRossaIucn'] as $campo) {
                $voce[$campo] = Xml::testo($nodo, 'taxon/' . $campo);
            }

            foreach (['zonaCavita', 'puntoMisura', 'numeroIndividui', 'metodo',
                      'determinatore', 'fotoRif', 'note'] as $campo) {
                $voce[$campo] = Xml::testo($nodo, $campo);
            }

            $rilevatore = Xml::primo($nodo, 'rilevatore');
            $voce['rilevatore'] = $rilevatore instanceof DOMElement
                ? $rilevatore->getAttribute('esploratoreId') : '';

            $provenienza = Xml::primo($nodo, 'provenienza');
            $voce['provenienzaTipo'] = $provenienza instanceof DOMElement
                ? $provenienza->getAttribute('tipo') : 'rilevamento_proprio';

            $osservazioni[] = array_merge(self::CAMPI_OSSERVAZIONE, $voce);
        }

        usort($osservazioni, static fn (array $a, array $b): int =>
            strcmp((string) $b['data'], (string) $a['data']));

        $colonie = [];
        foreach (Xml::elenco($doc, '/biospeleologia/colonieChirotteri/colonia') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $voce = ['id' => $nodo->getAttribute('id')];
            $voce['progressivo'] = (int) preg_replace('/\D/', '', $voce['id']);

            foreach (['nome', 'specie', 'ruolo', 'zonaCavita', 'serieConteggi',
                      'consistenzaStimata', 'trend', 'riservatezza',
                      'riferimentoNormativo', 'biblioRif', 'note'] as $campo) {
                $voce[$campo] = Xml::testo($nodo, $campo);
            }

            $aggiuntive = [];
            foreach (Xml::elenco($nodo, 'specieAggiuntive/specie') as $sp) {
                $valore = trim($sp->textContent);
                if ($valore !== '') {
                    $aggiuntive[] = $valore;
                }
            }
            $voce['specieAggiuntive'] = implode(', ', $aggiuntive);

            $periodo = Xml::primo($nodo, 'disturbo/periodoCritico');
            $voce['periodoCriticoDal'] = $periodo instanceof DOMElement ? $periodo->getAttribute('dal') : '';
            $voce['periodoCriticoAl']  = $periodo instanceof DOMElement ? $periodo->getAttribute('al') : '';
            $voce['accessoSconsigliato'] = Xml::testo($nodo, 'disturbo/accessoSconsigliato', '0');
            $voce['prescrizioni'] = Xml::testo($nodo, 'disturbo/prescrizioni');

            $colonie[] = array_merge(self::CAMPI_COLONIA, $voce);
        }

        usort($colonie, static fn (array $a, array $b): int => $a['progressivo'] <=> $b['progressivo']);

        $ultimo = (int) $radice->getAttribute('ultimoProgressivo');
        foreach ($colonie as $colonia) {
            $ultimo = max($ultimo, (int) $colonia['progressivo']);
        }

        return ['osservazioni' => $osservazioni, 'colonie' => $colonie, 'ultimoProgressivo' => $ultimo];
    }

    /**
     * @param array{osservazioni:array<int,array<string,mixed>>,colonie:array<int,array<string,mixed>>,ultimoProgressivo:int} $stato
     */
    private static function scrivi(string $codice, array $stato, string $percorso): void
    {
        $doc = Xml::nuovo('biospeleologia', [
            'versioneSchema'    => self::VERSIONE_SCHEMA,
            'codiceIpogeo'      => $codice,
            'ultimoProgressivo' => (string) $stato['ultimoProgressivo'],
        ]);
        $radice = $doc->documentElement;

        $contenitore = Xml::aggiungi($radice, 'osservazioni');
        foreach ($stato['osservazioni'] as $voce) {
            $voce = array_merge(self::CAMPI_OSSERVAZIONE, $voce);
            $nodo = Xml::aggiungi($contenitore, 'osservazione', null, ['id' => (string) $voce['id']]);

            Xml::imposta($nodo, 'data', (string) $voce['data']);
            Xml::imposta($nodo, 'ora', (string) $voce['ora']);

            $taxon = Xml::imposta($nodo, 'taxon', null);
            Xml::imposta($taxon, 'nomeScientifico', (string) $voce['nomeScientifico']);
            Xml::imposta($taxon, 'nomeComune', (string) $voce['nomeComune']);

            $gruppo = (string) $voce['gruppoTassonomico'];
            Xml::imposta($taxon, 'gruppoTassonomico',
                isset(self::GRUPPI_TASSONOMICI[$gruppo]) ? $gruppo : 'invertebrati');

            foreach (['classe', 'ordine', 'famiglia'] as $campo) {
                Xml::imposta($taxon, $campo, (string) $voce[$campo]);
            }

            $categoria = (string) $voce['categoriaEcologica'];
            Xml::imposta($taxon, 'categoriaEcologica',
                isset(self::CATEGORIE_ECOLOGICHE[$categoria]) ? $categoria : '');

            Xml::imposta($taxon, 'endemismo', (string) $voce['endemismo'] === '1' ? '1' : '0');
            Xml::imposta($taxon, 'specieProtetta', (string) $voce['specieProtetta'] === '1' ? '1' : '0');
            Xml::imposta($taxon, 'direttivaHabitat', (string) $voce['direttivaHabitat']);

            $iucn = (string) $voce['listaRossaIucn'];
            Xml::imposta($taxon, 'listaRossaIucn', isset(self::IUCN[$iucn]) ? $iucn : '');

            foreach (['zonaCavita', 'puntoMisura', 'numeroIndividui', 'metodo', 'fotoRif'] as $campo) {
                Xml::imposta($nodo, $campo, (string) $voce[$campo]);
            }

            Xml::imposta($nodo, 'rilevatore', null)
                ->setAttribute('esploratoreId', (string) $voce['rilevatore']);
            Xml::imposta($nodo, 'determinatore', (string) $voce['determinatore'], true);

            $tipo = (string) $voce['provenienzaTipo'];
            Xml::imposta($nodo, 'provenienza', null)->setAttribute('tipo',
                isset(Scientifici::PROVENIENZE[$tipo]) ? $tipo : 'rilevamento_proprio');

            Xml::imposta($nodo, 'note', (string) $voce['note'], true);
        }

        $contenitore = Xml::aggiungi($radice, 'colonieChirotteri');
        foreach ($stato['colonie'] as $colonia) {
            $colonia = array_merge(self::CAMPI_COLONIA, $colonia);
            $nodo = Xml::aggiungi($contenitore, 'colonia', null, ['id' => (string) $colonia['id']]);

            Xml::imposta($nodo, 'nome', (string) $colonia['nome']);
            Xml::imposta($nodo, 'specie', (string) $colonia['specie']);

            $aggiuntive = Xml::imposta($nodo, 'specieAggiuntive', null);
            foreach (preg_split('/\s*,\s*/', (string) $colonia['specieAggiuntive']) ?: [] as $specie) {
                if (trim($specie) !== '') {
                    Xml::aggiungi($aggiuntive, 'specie', trim($specie));
                }
            }

            $ruolo = (string) $colonia['ruolo'];
            Xml::imposta($nodo, 'ruolo', isset(self::RUOLI_COLONIA[$ruolo]) ? $ruolo : 'svernamento');
            Xml::imposta($nodo, 'zonaCavita', (string) $colonia['zonaCavita']);
            Xml::imposta($nodo, 'serieConteggi', (string) $colonia['serieConteggi']);
            Xml::imposta($nodo, 'consistenzaStimata', (string) $colonia['consistenzaStimata']);

            $trend = (string) $colonia['trend'];
            Xml::imposta($nodo, 'trend', isset(self::TREND[$trend]) ? $trend : 'ignoto');

            // Il valore di riposo e "riservata", non "pubblica": l'ubicazione di
            // un roost e un dato sensibile, e il caso in cui l'utente non
            // sceglie deve essere quello prudente.
            $riservatezza = (string) $colonia['riservatezza'];
            Xml::imposta($nodo, 'riservatezza',
                $riservatezza === 'pubblica' ? 'pubblica' : 'riservata');

            $disturbo = Xml::imposta($nodo, 'disturbo', null);
            $periodo = Xml::aggiungi($disturbo, 'periodoCritico');
            $periodo->setAttribute('dal', self::giornoValido((string) $colonia['periodoCriticoDal'])
                ? (string) $colonia['periodoCriticoDal'] : '');
            $periodo->setAttribute('al', self::giornoValido((string) $colonia['periodoCriticoAl'])
                ? (string) $colonia['periodoCriticoAl'] : '');
            Xml::imposta($disturbo, 'accessoSconsigliato',
                (string) $colonia['accessoSconsigliato'] === '1' ? '1' : '0');
            Xml::imposta($disturbo, 'prescrizioni', (string) $colonia['prescrizioni'], true);

            Xml::imposta($nodo, 'riferimentoNormativo', (string) $colonia['riferimentoNormativo']);
            Xml::imposta($nodo, 'biblioRif', (string) $colonia['biblioRif']);
            Xml::imposta($nodo, 'note', (string) $colonia['note'], true);
        }

        Xml::salva($doc, $percorso, Percorsi::schema('biospeleologia.xsd'));
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws BiospeleologiaEccezione
     */
    private static function validaOsservazione(array $dati): void
    {
        if (trim((string) ($dati['nomeScientifico'] ?? '')) === ''
            && trim((string) ($dati['nomeComune'] ?? '')) === '') {
            throw new BiospeleologiaEccezione(
                'Indicare almeno il nome scientifico o il nome comune.');
        }

        $data = trim((string) ($dati['data'] ?? ''));
        if ($data !== '' && Scientifici::normalizzaData($data) === '') {
            throw new BiospeleologiaEccezione('Data dell\'osservazione non riconosciuta.');
        }

        $gruppo = trim((string) ($dati['gruppoTassonomico'] ?? ''));
        if ($gruppo !== '' && !isset(self::GRUPPI_TASSONOMICI[$gruppo])) {
            throw new BiospeleologiaEccezione('Gruppo tassonomico non riconosciuto: ' . $gruppo);
        }

        $numero = trim((string) ($dati['numeroIndividui'] ?? ''));
        if ($numero !== '' && !ctype_digit($numero)) {
            throw new BiospeleologiaEccezione('Il numero di individui va indicato in cifre.');
        }
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws BiospeleologiaEccezione
     */
    private static function validaColonia(array $dati): void
    {
        if (trim((string) ($dati['nome'] ?? '')) === '') {
            throw new BiospeleologiaEccezione('Il nome della colonia è obbligatorio.');
        }

        $ruolo = trim((string) ($dati['ruolo'] ?? ''));
        if ($ruolo !== '' && !isset(self::RUOLI_COLONIA[$ruolo])) {
            throw new BiospeleologiaEccezione('Ruolo della cavità non riconosciuto: ' . $ruolo);
        }

        foreach (['periodoCriticoDal', 'periodoCriticoAl'] as $campo) {
            $valore = trim((string) ($dati[$campo] ?? ''));
            if ($valore !== '' && !self::giornoValido($valore)) {
                throw new BiospeleologiaEccezione(
                    'Il periodo critico va indicato come MM-GG, per esempio 11-01.');
            }
        }

        // Mezzo periodo non e un periodo: senza uno dei due estremi l'avviso
        // non potrebbe mai comparire, e la prescrizione resterebbe muta.
        $dal = trim((string) ($dati['periodoCriticoDal'] ?? ''));
        $al  = trim((string) ($dati['periodoCriticoAl'] ?? ''));
        if (($dal === '') !== ($al === '')) {
            throw new BiospeleologiaEccezione(
                'Il periodo critico va indicato per intero: servono sia l\'inizio sia la fine.');
        }
    }

    /** "MM-GG" con mese e giorno plausibili. */
    private static function giornoValido(string $valore): bool
    {
        if (!preg_match('/^(\d{2})-(\d{2})$/', $valore, $p)) {
            return false;
        }
        $mese = (int) $p[1];
        $giorno = (int) $p[2];

        // Si usa un anno bisestile come riferimento, cosi il 29 febbraio resta
        // un giorno legittimo di un periodo ricorrente.
        return $mese >= 1 && $mese <= 12 && checkdate($mese, $giorno, 2024);
    }

    private static function giornoLeggibile(string $valore): string
    {
        if (!self::giornoValido($valore)) {
            return $valore;
        }
        [$mese, $giorno] = explode('-', $valore);

        $mesi = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
                 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

        return (int) $giorno . ' ' . ($mesi[(int) $mese] ?? $mese);
    }

    /** @param array<int,array<string,mixed>> $voci */
    private static function massimoId(array $voci): int
    {
        $massimo = 0;
        foreach ($voci as $voce) {
            $massimo = max($massimo, (int) preg_replace('/\D/', '', (string) $voce['id']));
        }

        return $massimo;
    }

    private static function catalogoDi(string $codice): string
    {
        $riga = IndiceIpogei::trova($codice);

        return $riga === null ? '' : (string) ($riga['catalogo'] ?? '');
    }
}
