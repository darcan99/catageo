<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Esplorazioni.php
 *  Descrizione ..: Diari di esplorazione: un XML per uscita,
 *                  "[codice]-ES001-[titolo].xml" (6.10).
 *
 *                  Un file per esplorazione e non un unico registro: il diario
 *                  di un'uscita e un documento che ha senso da solo, si legge
 *                  senza l'applicativo, si allega a una relazione e si archivia
 *                  a parte. Accanto vive "[codice] - Esplorazioni.xml", un
 *                  indice leggero con le sole date e i titoli, per non dover
 *                  aprire venti diari solo per elencarli.
 *
 *                  Le foto NON vengono duplicate qui: restano nella galleria
 *                  dell'ipogeo e il diario le richiama per codice (FO001). Una
 *                  foto sola su disco, richiamabile da piu punti.
 *
 *                  I progressivi non si riusano mai: l'indice porta
 *                  "ultimoProgressivo" e il numero si cerca anche fra i file in
 *                  "_rimossi", perche un ES003 citato in una relazione deve
 *                  indicare la stessa uscita anche fra dieci anni.
 *  Versione .....: 0.9.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.9.0  2026-08-05  D.Candela  Prima stesura (fase 7).
 * ============================================================================
 */

final class Esplorazioni
{
    /** Versione dello schema scritta nei diari nuovi. */
    public const VERSIONE_SCHEMA = '1.0';

    /** Sigla della sezione, per i nomi dei file e i riferimenti. */
    public const SIGLA = 'ES';

    /**
     * Tipi di uscita.
     *
     * Non e un vocabolario modificabile come le tipologie di cavita: sono i
     * modi in cui si entra in un ipogeo, e sono gli stessi ovunque.
     */
    public const TIPI = [
        'ricognizione'   => 'Ricognizione',
        'esplorazione'   => 'Esplorazione',
        'rilievo'        => 'Rilievo',
        'documentazione' => 'Documentazione',
        'disostruzione'  => 'Disostruzione',
        'monitoraggio'   => 'Monitoraggio',
    ];

    /** Ruoli dei partecipanti. */
    public const RUOLI = [
        ''            => '—',
        'capogita'    => 'Capogita',
        'rilevatore'  => 'Rilevatore',
        'fotografo'   => 'Fotografo',
        'topografo'   => 'Topografo',
        'assistente'  => 'Assistente',
        'ospite'      => 'Ospite',
    ];

    /** Campi di testo del diario, con il loro valore iniziale. */
    public const CAMPI = [
        'titolo'     => '',
        'tipo'       => 'esplorazione',
        'dataInizio' => '',
        'oraInizio'  => '',
        'dataFine'   => '',
        'oraFine'    => '',
        'meteo'      => '',
        'obiettivi'  => '',
        'risultati'  => '',
        'note'       => '',
        'traccia'    => '',
    ];

    // ========================================================================
    //  LETTURA
    // ========================================================================

    /**
     * Elenco dei diari di un ipogeo, dal piu recente.
     *
     * Legge l'INDICE e non i diari: elencare venti uscite non deve costare
     * venti aperture di XML.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(string $codice): array
    {
        $percorso = self::percorsoIndice($codice);
        if ($percorso === null || !is_file($percorso)) {
            return [];
        }

        try {
            $doc = Xml::carica($percorso);
        } catch (Throwable $e) {
            Log::errore('Indice delle esplorazioni illeggibile: ' . $percorso . ' — ' . $e->getMessage());
            return [];
        }

        $voci = [];
        foreach (Xml::elenco($doc, '/esplorazioni/esplorazione') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $voci[] = [
                'progressivo' => (int) $nodo->getAttribute('progressivo'),
                'file'        => Xml::testo($nodo, 'file'),
                'titolo'      => Xml::testo($nodo, 'titolo'),
                'tipo'        => Xml::testo($nodo, 'tipo'),
                'dataInizio'  => Xml::testo($nodo, 'dataInizio'),
                'dataFine'    => Xml::testo($nodo, 'dataFine'),
                'gruppi'      => array_values(array_filter(
                    explode(',', Xml::testo($nodo, 'gruppi')),
                    static fn (string $v): bool => trim($v) !== ''
                )),
                'partecipanti' => (int) Xml::intero($nodo, 'partecipanti'),
                'voci'         => (int) Xml::intero($nodo, 'voci'),
            ];
        }

        // Dal piu recente: un diario si consulta partendo dall'ultima uscita.
        usort($voci, static function (array $a, array $b): int {
            $perData = strcmp((string) $b['dataInizio'], (string) $a['dataInizio']);
            return $perData !== 0 ? $perData : ($b['progressivo'] <=> $a['progressivo']);
        });

        return $voci;
    }

    /** Numero di diari di un ipogeo. */
    public static function conta(string $codice): int
    {
        return count(self::elenco($codice));
    }

    /**
     * Diario completo.
     *
     * @return array<string,mixed>|null
     */
    public static function trova(string $codice, int $progressivo): ?array
    {
        $percorso = self::percorsoDiario($codice, $progressivo);
        if ($percorso === null) {
            return null;
        }

        try {
            $doc = Xml::carica($percorso);
        } catch (Throwable $e) {
            Log::errore('Diario illeggibile: ' . $percorso . ' — ' . $e->getMessage());
            return null;
        }

        $radice = $doc->documentElement;
        if ($radice === null) {
            return null;
        }

        $diario = array_merge(self::CAMPI, [
            'progressivo' => (int) $radice->getAttribute('progressivo'),
            'codice'      => $radice->getAttribute('codiceIpogeo'),
            'file'        => basename($percorso),
        ]);

        foreach (array_keys(self::CAMPI) as $campo) {
            $diario[$campo] = Xml::testo($doc, '/esplorazione/' . $campo);
        }

        $diario['gruppi'] = [];
        foreach (Xml::elenco($doc, '/esplorazione/gruppi/gruppo') as $nodo) {
            if ($nodo instanceof DOMElement && $nodo->getAttribute('id') !== '') {
                $diario['gruppi'][] = $nodo->getAttribute('id');
            }
        }

        $diario['partecipanti'] = [];
        foreach (Xml::elenco($doc, '/esplorazione/partecipanti/partecipante') as $nodo) {
            if (!$nodo instanceof DOMElement) {
                continue;
            }
            $diario['partecipanti'][] = [
                'esploratoreId' => $nodo->getAttribute('esploratoreId'),
                // Chi non e in anagrafica si registra col nome: un ospite di una
                // sola uscita non deve costringere a creare una scheda.
                'nome'          => $nodo->getAttribute('nome'),
                'ruolo'         => $nodo->getAttribute('ruolo'),
            ];
        }

        $diario['voci'] = [];
        foreach (Xml::elenco($doc, '/esplorazione/diario/voce') as $nodo) {
            $diario['voci'][] = [
                'ora'         => Xml::testo($nodo, 'ora'),
                'testo'       => Xml::testo($nodo, 'testo'),
                'latitudine'  => Xml::testo($nodo, 'coordinate/latitudine'),
                'longitudine' => Xml::testo($nodo, 'coordinate/longitudine'),
                'quota'       => Xml::testo($nodo, 'coordinate/quota'),
                'foto'        => array_map(
                    static fn (DOMNode $n): string => trim($n->textContent),
                    Xml::elenco($nodo, 'fotoRif')
                ),
            ];
        }

        $diario['materiale'] = [];
        foreach (['rilievoRif' => 'RI', 'allegatoRif' => 'AL'] as $elemento => $sigla) {
            foreach (Xml::elenco($doc, '/esplorazione/materialeProdotto/' . $elemento) as $nodo) {
                $riferimento = trim($nodo->textContent);
                if ($riferimento !== '') {
                    $diario['materiale'][] = ['sigla' => $sigla, 'riferimento' => $riferimento];
                }
            }
        }

        $redatto = Xml::primo($doc, '/esplorazione/redatto');
        $diario['redattoDa'] = $redatto instanceof DOMElement ? $redatto->getAttribute('utente') : '';
        $diario['redattoIl'] = Xml::testo($doc, '/esplorazione/redatto');

        return $diario;
    }

    /**
     * Durata in ore, calcolata da date e orari.
     *
     * Si ricalcola invece di conservarla: un dato derivato memorizzato e un dato
     * che prima o poi contraddice quelli da cui deriva. Restituisce null se non
     * ci sono abbastanza informazioni.
     *
     * @param array<string,mixed> $diario
     */
    public static function durataOre(array $diario): ?float
    {
        $inizio = trim((string) $diario['dataInizio']) . ' ' . trim((string) $diario['oraInizio']);
        $fine   = trim((string) ($diario['dataFine'] ?: $diario['dataInizio'])) . ' ' . trim((string) $diario['oraFine']);

        if (trim($inizio) === '' || trim((string) $diario['oraInizio']) === ''
            || trim((string) $diario['oraFine']) === '') {
            return null;
        }

        $t1 = strtotime($inizio);
        $t2 = strtotime($fine);
        if ($t1 === false || $t2 === false || $t2 < $t1) {
            return null;
        }

        return round(($t2 - $t1) / 3600, 2);
    }

    // ========================================================================
    //  PERCORSI
    // ========================================================================

    /** Cartella delle esplorazioni di un ipogeo. */
    public static function cartella(string $codice): ?string
    {
        return Risorse::cartellaSezione($codice, self::SIGLA);
    }

    /** Indice leggero delle esplorazioni. */
    public static function percorsoIndice(string $codice): ?string
    {
        return Risorse::percorsoIndice($codice, self::SIGLA);
    }

    /**
     * File di un diario, cercato per progressivo.
     *
     * Il nome contiene il titolo, che puo cambiare: si cerca per prefisso
     * "[codice]-ES[NNN]-" invece di ricostruire il nome, cosi un diario
     * rinominato a mano resta raggiungibile.
     */
    public static function percorsoDiario(string $codice, int $progressivo): ?string
    {
        $cartella = self::cartella($codice);
        if ($cartella === null || !is_dir($cartella)) {
            return null;
        }

        $prefisso = $codice . '-' . Sezioni::riferimento(self::SIGLA, $progressivo) . '-';

        foreach (scandir($cartella) ?: [] as $voce) {
            if (str_starts_with($voce, $prefisso) && str_ends_with(strtolower($voce), '.xml')) {
                return Percorsi::unisci($cartella, $voce);
            }
        }

        return null;
    }

    // ========================================================================
    //  SCRITTURA
    // ========================================================================

    /**
     * Crea un diario nuovo e restituisce il suo progressivo.
     *
     * @param  array<string,mixed> $dati
     * @throws EsplorazioneEccezione
     */
    public static function crea(string $codice, array $dati): int
    {
        $cartella = self::cartella($codice);
        if ($cartella === null) {
            throw new EsplorazioneEccezione('Ipogeo non trovato: ' . $codice);
        }
        Percorsi::assicuraCartella($cartella);

        self::valida($dati);

        $indice = self::percorsoIndice($codice);

        return Xml::conLock((string) $indice, static function () use ($codice, $dati, $cartella): int {
            $progressivo = self::prossimoProgressivo($codice);

            $dati['progressivo'] = $progressivo;
            $percorso = Percorsi::unisci($cartella, self::nomeFile($codice, $progressivo, (string) $dati['titolo']));

            self::scriviDiario($codice, $progressivo, $dati, $percorso);
            self::ricostruisciIndice($codice);

            Log::modifica('esplorazione_creata', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo) . ' ' . $dati['titolo']);

            return $progressivo;
        });
    }

    /**
     * Aggiorna un diario esistente.
     *
     * @param array<string,mixed> $dati
     */
    public static function aggiorna(string $codice, int $progressivo, array $dati): void
    {
        $vecchio = self::percorsoDiario($codice, $progressivo);
        if ($vecchio === null) {
            throw new EsplorazioneEccezione('Diario non trovato: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
        }

        self::valida($dati);

        $indice = self::percorsoIndice($codice);

        Xml::conLock((string) $indice, static function () use ($codice, $progressivo, $dati, $vecchio): void {
            $cartella = (string) self::cartella($codice);
            $nuovo    = Percorsi::unisci($cartella, self::nomeFile($codice, $progressivo, (string) $dati['titolo']));

            self::scriviDiario($codice, $progressivo, $dati, $nuovo);

            // Il nome contiene il titolo: se il titolo cambia, il vecchio file
            // resterebbe accanto al nuovo come doppione. Si rimuove solo dopo
            // che il nuovo e stato scritto davvero.
            if ($nuovo !== $vecchio && is_file($nuovo) && is_file($vecchio)) {
                @unlink($vecchio);
            }

            self::ricostruisciIndice($codice);

            Log::modifica('esplorazione_aggiornata', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo));
        });
    }

    /**
     * Rimuove un diario, spostandolo fra i file rimossi.
     *
     * Come per le altre risorse non si cancella: un diario e un documento
     * storico, e chi lo toglie per sbaglio da un'interfaccia web non lo
     * riscrive a memoria.
     */
    public static function elimina(string $codice, int $progressivo): void
    {
        $percorso = self::percorsoDiario($codice, $progressivo);
        if ($percorso === null) {
            throw new EsplorazioneEccezione('Diario non trovato: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
        }

        $indice = self::percorsoIndice($codice);

        Xml::conLock((string) $indice, static function () use ($codice, $progressivo, $percorso): void {
            $deposito = Percorsi::assicuraCartella(Percorsi::unisci(
                (string) Ipogeo::cartella($codice),
                $codice . ' - ' . Risorse::CARTELLA_RIMOSSI
            ));
            @rename($percorso, Percorsi::unisci($deposito, date('Ymd-His') . '-' . basename($percorso)));

            self::ricostruisciIndice($codice);

            Log::modifica('esplorazione_rimossa', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo) . ' ' . basename($percorso));
        });
    }

    // ========================================================================
    //  VISTE TRASVERSALI
    // ========================================================================

    /**
     * Tutte le esplorazioni dell'archivio, per le viste per gruppo, per
     * esploratore e per la cronologia.
     *
     * Legge gli indici di sezione e non i diari: su un catasto con migliaia di
     * ipogei aprire ogni diario per costruire un elenco sarebbe insostenibile.
     * Il filtro per partecipante, che l'indice non conosce, apre solo i diari
     * degli ipogei che hanno almeno un'esplorazione.
     *
     * @param  array{gruppo?:string,esploratore?:string,dal?:string,al?:string,catalogo?:string} $filtri
     * @return array<int,array<string,mixed>>
     */
    public static function tutte(array $filtri = []): array
    {
        $esiti = [];

        $perGruppo      = trim((string) ($filtri['gruppo'] ?? ''));
        $perEsploratore = trim((string) ($filtri['esploratore'] ?? ''));
        $dal            = trim((string) ($filtri['dal'] ?? ''));
        $al             = trim((string) ($filtri['al'] ?? ''));
        $catalogo       = trim((string) ($filtri['catalogo'] ?? ''));

        foreach (IndiceIpogei::elenco(Visibilita::filtroIndice()) as $riga) {
            if ((int) ($riga['n_esplorazioni'] ?? 0) === 0) {
                continue;
            }
            if ($catalogo !== '' && strcasecmp((string) $riga['catalogo'], $catalogo) !== 0) {
                continue;
            }

            $codice = (string) $riga['codice'];

            foreach (self::elenco($codice) as $voce) {
                if ($dal !== '' && (string) $voce['dataInizio'] < $dal) {
                    continue;
                }
                if ($al !== '' && (string) $voce['dataInizio'] > $al) {
                    continue;
                }
                if ($perGruppo !== '' && !in_array($perGruppo, $voce['gruppi'], true)) {
                    continue;
                }

                if ($perEsploratore !== '') {
                    // L'indice non conosce i partecipanti: qui si apre il
                    // diario, ma solo per le uscite gia scremate dagli altri
                    // filtri.
                    $diario = self::trova($codice, (int) $voce['progressivo']);
                    if ($diario === null || !self::haPartecipante($diario, $perEsploratore)) {
                        continue;
                    }
                }

                $voce['codice']     = $codice;
                $voce['nomeIpogeo'] = (string) $riga['nome'];
                $voce['catalogo']   = (string) $riga['catalogo'];
                $esiti[] = $voce;
            }
        }

        usort($esiti, static function (array $a, array $b): int {
            return strcmp((string) $b['dataInizio'], (string) $a['dataInizio']);
        });

        return $esiti;
    }

    /** @param array<string,mixed> $diario */
    public static function haPartecipante(array $diario, string $esploratoreId): bool
    {
        foreach ($diario['partecipanti'] as $partecipante) {
            if ((string) $partecipante['esploratoreId'] === $esploratoreId) {
                return true;
            }
        }
        return false;
    }

    // ========================================================================
    //  INTERNI
    // ========================================================================

    /**
     * Nome normativo del diario: "[codice]-ES[NNN]-[titolo].xml".
     */
    public static function nomeFile(string $codice, int $progressivo, string $titolo): string
    {
        $ascii  = Config::booleano('upload.nomiFileAscii', false);
        $pulito = Testo::nomeFileSicuro($titolo === '' ? 'Senza titolo' : $titolo, $ascii);

        // Il titolo e testo libero e puo gia finire in ".pdf" o simili: si toglie
        // qualunque estensione e si impone .xml, che e quello che il file e.
        $pulito = (string) pathinfo($pulito, PATHINFO_FILENAME);
        if (trim($pulito) === '') {
            $pulito = 'Senza titolo';
        }

        return $codice . '-' . Sezioni::riferimento(self::SIGLA, $progressivo) . '-' . $pulito . '.xml';
    }

    /**
     * Prossimo progressivo, mai riusato.
     *
     * Si prende dall'indice e si confronta con i file presenti: un diario
     * aggiunto a mano nella cartella non deve far riassegnare il suo numero.
     */
    private static function prossimoProgressivo(string $codice): int
    {
        // Il massimo si cerca in tre posti perche nessuno dei tre da solo basta:
        // l'attributo dell'indice si perde se l'indice viene cancellato, i file
        // presenti dimenticano cio che e stato rimosso, e _rimossi non sa nulla
        // dei diari ancora vivi. Un progressivo riusato farebbe puntare a un
        // altro diario ogni "ES003" gia citato in una relazione.
        $massimo = self::ultimoProgressivoRegistrato($codice);

        $cartella = self::cartella($codice);
        if ($cartella !== null && is_dir($cartella)) {
            foreach (scandir($cartella) ?: [] as $file) {
                if (preg_match('/-' . self::SIGLA . '(\d+)-/', $file, $parti)) {
                    $massimo = max($massimo, (int) $parti[1]);
                }
            }
        }

        foreach (self::rimossiDi($codice) as $progressivo) {
            $massimo = max($massimo, $progressivo);
        }

        return $massimo + 1;
    }

    /** Il valore scritto nell'indice, 0 se l'indice manca o e stato rifatto a mano. */
    private static function ultimoProgressivoRegistrato(string $codice): int
    {
        $percorso = self::percorsoIndice($codice);
        if ($percorso === null || !is_file($percorso)) {
            return 0;
        }

        try {
            $doc = Xml::carica($percorso);
        } catch (Throwable $e) {
            return 0;
        }

        $radice = $doc->documentElement;

        return $radice === null ? 0 : (int) $radice->getAttribute('ultimoProgressivo');
    }

    /**
     * Progressivi dei diari finiti in "[codice] - _rimossi".
     *
     * @return array<int,int>
     */
    private static function rimossiDi(string $codice): array
    {
        $cartellaIpogeo = Ipogeo::cartella($codice);
        if ($cartellaIpogeo === null) {
            return [];
        }

        $deposito = Percorsi::unisci($cartellaIpogeo, $codice . ' - ' . Risorse::CARTELLA_RIMOSSI);
        if (!is_dir($deposito)) {
            return [];
        }

        $progressivi = [];
        foreach (scandir($deposito) ?: [] as $file) {
            if (preg_match('/-' . self::SIGLA . '(\d+)-/', $file, $parti)) {
                $progressivi[] = (int) $parti[1];
            }
        }

        return $progressivi;
    }

    /**
     * Scrive il file del diario.
     *
     * @param array<string,mixed> $dati
     */
    private static function scriviDiario(string $codice, int $progressivo, array $dati, string $percorso): void
    {
        // Le chiavi mancanti prendono il valore di riposo. Pretenderle tutte
        // significherebbe che chi registra un'uscita minima — titolo, tipo e
        // data — deve comunque elencare i campi che non ha, e dimenticarne uno
        // farebbe fallire il salvataggio invece di lasciarlo vuoto.
        $dati = array_merge(self::CAMPI, $dati);

        $doc = Xml::nuovo('esplorazione', [
            'versioneSchema' => self::VERSIONE_SCHEMA,
            'progressivo'    => (string) $progressivo,
            'codiceIpogeo'   => $codice,
        ]);
        $radice = $doc->documentElement;

        Xml::imposta($radice, 'titolo', (string) $dati['titolo']);
        Xml::imposta($radice, 'tipo', (string) $dati['tipo']);
        Xml::imposta($radice, 'dataInizio', (string) $dati['dataInizio']);
        Xml::imposta($radice, 'oraInizio', (string) $dati['oraInizio']);
        Xml::imposta($radice, 'dataFine', (string) $dati['dataFine']);
        Xml::imposta($radice, 'oraFine', (string) $dati['oraFine']);

        $gruppi = Xml::imposta($radice, 'gruppi', null);
        foreach ((array) ($dati['gruppi'] ?? []) as $id) {
            if (trim((string) $id) !== '') {
                Xml::aggiungi($gruppi, 'gruppo', null, ['id' => trim((string) $id)]);
            }
        }

        $partecipanti = Xml::imposta($radice, 'partecipanti', null);
        foreach ((array) ($dati['partecipanti'] ?? []) as $p) {
            $attributi = [];
            if (trim((string) ($p['esploratoreId'] ?? '')) !== '') {
                $attributi['esploratoreId'] = trim((string) $p['esploratoreId']);
            }
            if (trim((string) ($p['nome'] ?? '')) !== '') {
                $attributi['nome'] = trim((string) $p['nome']);
            }
            if ($attributi === []) {
                continue;
            }
            if (trim((string) ($p['ruolo'] ?? '')) !== '') {
                $attributi['ruolo'] = trim((string) $p['ruolo']);
            }
            Xml::aggiungi($partecipanti, 'partecipante', null, $attributi);
        }

        // Testi liberi in CDATA: non hanno limiti di lunghezza (D6) e un diario
        // contiene virgolette, apici e simboli senza doverci pensare.
        Xml::imposta($radice, 'meteo', (string) $dati['meteo']);
        Xml::imposta($radice, 'obiettivi', (string) $dati['obiettivi'], true);

        $diario = Xml::imposta($radice, 'diario', null);
        foreach ((array) ($dati['voci'] ?? []) as $voce) {
            $testo = trim((string) ($voce['testo'] ?? ''));
            $ora   = trim((string) ($voce['ora'] ?? ''));
            $lat   = trim((string) ($voce['latitudine'] ?? ''));
            $lon   = trim((string) ($voce['longitudine'] ?? ''));

            // Una voce senza testo, ora, coordinate ne foto e una riga vuota
            // lasciata nel modulo: non va scritta.
            if ($testo === '' && $ora === '' && $lat === '' && ($voce['foto'] ?? []) === []) {
                continue;
            }

            $nodo = Xml::aggiungi($diario, 'voce');
            Xml::imposta($nodo, 'ora', $ora);

            if ($lat !== '' && $lon !== '') {
                $coord = Xml::imposta($nodo, 'coordinate', null);
                Xml::imposta($coord, 'latitudine', $lat);
                Xml::imposta($coord, 'longitudine', $lon);
                if (trim((string) ($voce['quota'] ?? '')) !== '') {
                    Xml::imposta($coord, 'quota', trim((string) $voce['quota']))
                        ->setAttribute('unita', 'm');
                }
            }

            Xml::imposta($nodo, 'testo', $testo, true);

            foreach ((array) ($voce['foto'] ?? []) as $riferimento) {
                $riferimento = strtoupper(trim((string) $riferimento));
                if ($riferimento !== '') {
                    Xml::aggiungi($nodo, 'fotoRif', $riferimento);
                }
            }
        }

        Xml::imposta($radice, 'risultati', (string) $dati['risultati'], true);

        $materiale = Xml::imposta($radice, 'materialeProdotto', null);
        foreach ((array) ($dati['materiale'] ?? []) as $riferimento) {
            $riferimento = strtoupper(trim((string) $riferimento));
            $parti = Sezioni::scomponiRiferimento($riferimento);
            if ($parti === null) {
                continue;
            }
            $elemento = match ($parti['sigla']) {
                'RI'    => 'rilievoRif',
                'AL'    => 'allegatoRif',
                default => null,
            };
            if ($elemento !== null) {
                Xml::aggiungi($materiale, $elemento, $riferimento);
            }
        }

        if (trim((string) $dati['traccia']) !== '') {
            Xml::imposta($radice, 'traccia', trim((string) $dati['traccia']));
        }

        Xml::imposta($radice, 'note', (string) $dati['note'], true);

        $redatto = Xml::imposta($radice, 'redatto', date('c'));
        $redatto->setAttribute('utente', Auth::usernameCorrente());

        Percorsi::assicuraCartella(dirname($percorso));
        Xml::salva($doc, $percorso, Percorsi::schema('esplorazione.xsd'));
    }

    /**
     * Riscrive l'indice leggero leggendo i diari presenti nella cartella.
     *
     * Si ricostruisce invece di aggiornarlo riga per riga: e un derivato, e
     * ricalcolarlo da zero costa poco e non puo disallinearsi.
     */
    public static function ricostruisciIndice(string $codice): void
    {
        $cartella = self::cartella($codice);
        $percorso = self::percorsoIndice($codice);
        if ($cartella === null || $percorso === null) {
            throw new EsplorazioneEccezione('Ipogeo non trovato: ' . $codice);
        }
        Percorsi::assicuraCartella($cartella);

        // Si legge prima di riscrivere: ultimoProgressivo e l'unico dato che
        // non si ricava dai diari presenti, e rifare l'indice da zero non deve
        // essere il modo per far rinascere un numero gia usato.
        $ultimo = self::ultimoProgressivoRegistrato($codice);
        foreach (self::rimossiDi($codice) as $progressivo) {
            $ultimo = max($ultimo, $progressivo);
        }

        $doc = Xml::nuovo('esplorazioni', [
            'versioneSchema' => self::VERSIONE_SCHEMA,
            'codiceIpogeo'   => $codice,
        ]);
        $radice = $doc->documentElement;

        $trovati = [];
        foreach (scandir($cartella) ?: [] as $file) {
            if (!preg_match('/-' . self::SIGLA . '(\d+)-.*\.xml$/i', $file, $parti)) {
                continue;
            }
            $trovati[] = (int) $parti[1];
        }
        sort($trovati);

        foreach ($trovati as $progressivo) {
            $diario = self::trova($codice, $progressivo);
            if ($diario === null) {
                continue;
            }

            $nodo = Xml::aggiungi($radice, 'esplorazione', null, [
                'progressivo' => (string) $progressivo,
            ]);
            Xml::imposta($nodo, 'file', (string) $diario['file']);
            Xml::imposta($nodo, 'titolo', (string) $diario['titolo']);
            Xml::imposta($nodo, 'tipo', (string) $diario['tipo']);
            Xml::imposta($nodo, 'dataInizio', (string) $diario['dataInizio']);
            Xml::imposta($nodo, 'dataFine', (string) $diario['dataFine']);
            Xml::imposta($nodo, 'gruppi', implode(',', $diario['gruppi']));
            Xml::imposta($nodo, 'partecipanti', (string) count($diario['partecipanti']));
            Xml::imposta($nodo, 'voci', (string) count($diario['voci']));

            $ultimo = max($ultimo, $progressivo);
        }

        $radice->setAttribute('ultimoProgressivo', (string) $ultimo);

        Xml::salva($doc, $percorso, Percorsi::schema('esplorazioni-indice.xsd'));
    }

    /**
     * Controlli minimi prima di scrivere.
     *
     * @param array<string,mixed> $dati
     */
    private static function valida(array $dati): void
    {
        if (trim((string) ($dati['titolo'] ?? '')) === '') {
            throw new EsplorazioneEccezione('Il titolo dell\'esplorazione è obbligatorio.');
        }

        if (trim((string) ($dati['dataInizio'] ?? '')) === '') {
            throw new EsplorazioneEccezione('La data dell\'uscita è obbligatoria.');
        }

        $tipo = (string) ($dati['tipo'] ?? '');
        if (!isset(self::TIPI[$tipo])) {
            throw new EsplorazioneEccezione('Tipo di uscita non riconosciuto: ' . $tipo);
        }

        $fine = trim((string) ($dati['dataFine'] ?? ''));
        if ($fine !== '' && $fine < trim((string) $dati['dataInizio'])) {
            throw new EsplorazioneEccezione(
                'La data di fine e precedente a quella di inizio.'
            );
        }
    }

    /** Sigla del catalogo, per il log. */
    private static function catalogoDi(string $codice): string
    {
        $riga = IndiceIpogei::trova($codice);
        return $riga === null ? '' : (string) ($riga['catalogo'] ?? '');
    }
}
