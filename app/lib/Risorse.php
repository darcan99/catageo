<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Risorse.php
 *  Descrizione ..: File di una sezione dell'ipogeo e loro indice XML
 *                  "[codice] - [Sezione].xml" (D1).
 *
 *                  L'indice e la fonte di verita dei metadati; i file stanno
 *                  accanto, con il nome normativo di 4.1. Le due cose vanno
 *                  tenute insieme: un file senza riga nell'indice e un orfano,
 *                  una riga senza file e un riferimento rotto. Ogni scrittura
 *                  avviene sotto lock e l'XML si salva in modo atomico.
 *
 *                  Il progressivo non viene MAI riusato, nemmeno dopo una
 *                  rimozione: e citato dalle altre sezioni (FO001 in un diario,
 *                  RI002 in una scheda) e riassegnarlo farebbe puntare un
 *                  riferimento vecchio a un contenuto nuovo. Per questo l'ultimo
 *                  assegnato e memorizzato nell'indice e non ricalcolato dai
 *                  file presenti.
 *  Versione .....: 0.12.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.0 2026-08-06  D.Candela  RisorsaEccezione spostata in app/lib/RisorsaEccezione.php:
 *                                l'autoload risolve una classe per file.
 *  0.8.0  2026-08-05  D.Candela  Campi del rilievo e riconoscimento dei
 *                                formati mappabili e tridimensionali.
 *  0.7.1  2026-08-05  D.Candela  Coordinate della risorsa e acquisizione dei
 *                                metadati incorporati al caricamento.
 *  0.7.0  2026-08-05  D.Candela  Prima stesura (fase 5).
 * ============================================================================
 */

final class Risorse
{
    /** Versione dello schema scritta negli indici nuovi. */
    public const VERSIONE_SCHEMA = '1.0';

    /** Cartella in cui finiscono i file rimossi, dentro quella dell'ipogeo. */
    public const CARTELLA_RIMOSSI = '_rimossi';

    /**
     * Metadati liberi di una risorsa, con il loro valore iniziale.
     *
     * Elencati qui e non sparsi fra form e lettura: un campo aggiunto in un
     * punto solo diventa un campo che si salva e non si rilegge.
     */
    public const CAMPI = [
        'titolo'          => '',
        'descrizione'     => '',
        'data'            => '',
        'autoreId'        => '',
        'autore'          => '',
        'gruppoId'        => '',
        'licenza'         => '',
        'riservatezza'    => 'pubblica',
        'categoriaAllegato' => '',
        'urlEsterno'      => '',
        'latitudine'      => '',
        'longitudine'     => '',

        // Specifici dei rilievi (6.9).
        'tipoRilievo'     => '',
        'scala'           => '',
        'sistemaRiferimento' => '',
        'dataRilievo'     => '',
        'strumentazione'  => '',
        'rilevatori'      => '',
        'mostraInMappa'   => '1',
    ];

    // ========================================================================
    //  LETTURA
    // ========================================================================

    /**
     * Elenco delle risorse di una sezione, in ordine di progressivo.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(string $codice, string $sigla): array
    {
        return self::leggiIndice($codice, $sigla)['risorse'];
    }

    /**
     * Una singola risorsa.
     *
     * @return array<string,mixed>|null
     */
    public static function trova(string $codice, string $sigla, int $progressivo): ?array
    {
        foreach (self::elenco($codice, $sigla) as $risorsa) {
            if ((int) $risorsa['progressivo'] === $progressivo) {
                return $risorsa;
            }
        }
        return null;
    }

    /** Numero di risorse presenti in una sezione. */
    public static function conta(string $codice, string $sigla): int
    {
        return count(self::elenco($codice, $sigla));
    }

    /**
     * Foto di copertina della scheda, se ne e stata scelta una.
     *
     * @return array<string,mixed>|null
     */
    public static function copertina(string $codice): ?array
    {
        foreach (self::elenco($codice, 'FO') as $foto) {
            if (!empty($foto['copertina'])) {
                return $foto;
            }
        }
        return null;
    }

    /**
     * Percorso assoluto del file di una risorsa, o null se manca dal disco.
     *
     * Restituire null invece di lanciare e voluto: un file sparito a mano
     * dall'archivio e una situazione che l'interfaccia deve saper mostrare, non
     * un errore che interrompe la pagina.
     */
    public static function percorsoFile(string $codice, string $sigla, int $progressivo): ?string
    {
        $risorsa = self::trova($codice, $sigla, $progressivo);
        if ($risorsa === null || (string) $risorsa['file'] === '') {
            return null;
        }

        $cartella = self::cartellaSezione($codice, $sigla);
        if ($cartella === null) {
            return null;
        }

        $percorso = Percorsi::unisci($cartella, (string) $risorsa['file']);

        // Il nome viene dall'indice, che e un file dell'archivio e quindi
        // modificabile a mano: si verifica comunque che non esca dalla cartella.
        if (!Percorsi::dentro($cartella, $percorso) || !is_file($percorso)) {
            return null;
        }

        return $percorso;
    }

    // ------------------------------------------------------------ rilievi

    /** Formati che il visualizzatore tridimensionale sa aprire. */
    public const FORMATI_3D = ['ply', 'obj', 'stl', 'gltf', 'glb'];

    /** Formati che si mostrano direttamente nel browser, in due dimensioni. */
    public const FORMATI_2D = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif'];

    /**
     * True se il tracciato si puo sovrapporre alla mappa.
     *
     * Dipende dal formato, non da una scelta: un DXF non diventa mappabile
     * spuntando una casella. La casella serve solo a spegnere un tracciato
     * convertibile che sporcherebbe la mappa.
     *
     * @param array<string,mixed> $risorsa
     */
    public static function mappabile(array $risorsa): bool
    {
        return Tracciato::convertibile((string) $risorsa['file'])
            && (string) ($risorsa['mostraInMappa'] ?? '1') !== '0';
    }

    /**
     * True se il file e un modello apribile nel visualizzatore 3D.
     *
     * @param array<string,mixed> $risorsa
     */
    public static function tridimensionale(array $risorsa): bool
    {
        return in_array(self::estensione($risorsa), self::FORMATI_3D, true);
    }

    /**
     * True se il file si guarda direttamente nel browser.
     *
     * @param array<string,mixed> $risorsa
     */
    public static function bidimensionale(array $risorsa): bool
    {
        return in_array(self::estensione($risorsa), self::FORMATI_2D, true);
    }

    /** @param array<string,mixed> $risorsa */
    public static function estensione(array $risorsa): string
    {
        return strtolower((string) pathinfo((string) $risorsa['file'], PATHINFO_EXTENSION));
    }

    /**
     * Rilievi di un ipogeo sovrapponibili alla mappa.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function tracciati(string $codice): array
    {
        return array_values(array_filter(
            self::elenco($codice, 'RI'),
            static fn (array $r): bool => self::mappabile($r)
        ));
    }

    /** Cartella di una sezione per un ipogeo, o null se l'ipogeo non esiste. */
    public static function cartellaSezione(string $codice, string $sigla): ?string
    {
        $cartellaIpogeo = Ipogeo::cartella($codice);
        if ($cartellaIpogeo === null) {
            return null;
        }
        return Percorsi::unisci($cartellaIpogeo, Sezioni::nomeCartella($codice, $sigla));
    }

    /** Percorso dell'indice XML di una sezione, o null se l'ipogeo non esiste. */
    public static function percorsoIndice(string $codice, string $sigla): ?string
    {
        $cartella = self::cartellaSezione($codice, $sigla);
        if ($cartella === null) {
            return null;
        }
        return Percorsi::unisci($cartella, Sezioni::nomeIndice($codice, $sigla));
    }

    // ========================================================================
    //  SCRITTURA
    // ========================================================================

    /**
     * Registra un file gia verificato da Upload dentro una sezione.
     *
     * @param  array{nome:string,tmp:string,dimensione:int,estensione:string,mime:string} $file
     * @param  array<string,mixed> $metadati
     * @return array<string,mixed> la risorsa creata
     * @throws RisorsaEccezione
     */
    public static function aggiungi(string $codice, string $sigla, array $file, array $metadati = []): array
    {
        $sigla = strtoupper($sigla);
        if (!Sezioni::valida($sigla)) {
            throw new RisorsaEccezione('Sezione non riconosciuta: ' . $sigla);
        }

        $cartella = self::cartellaSezione($codice, $sigla);
        if ($cartella === null) {
            throw new RisorsaEccezione('Ipogeo non trovato: ' . $codice);
        }
        Percorsi::assicuraCartella($cartella);

        $indice = self::percorsoIndice($codice, $sigla);
        $ascii  = Config::booleano('upload.nomiFileAscii', false);

        return Xml::conLock((string) $indice, static function () use (
            $codice, $sigla, $file, $metadati, $cartella, $ascii
        ): array {
            $dati        = self::leggiIndice($codice, $sigla);
            $progressivo = $dati['ultimoProgressivo'] + 1;

            $nomeFile = Sezioni::nomeFile($codice, $sigla, $progressivo, $file['nome'], $ascii);
            $destinazione = Percorsi::unisci($cartella, $nomeFile);

            // Non dovrebbe succedere, perche il progressivo non si riusa: se
            // succede c'e un disallineamento fra indice e disco, e sovrascrivere
            // un file esistente sarebbe la reazione peggiore.
            if (file_exists($destinazione)) {
                throw new RisorsaEccezione(
                    'Esiste già un file con il nome "' . $nomeFile . '". '
                    . 'L\'indice della sezione e disallineato rispetto alla cartella.'
                );
            }

            if (!@move_uploaded_file($file['tmp'], $destinazione)) {
                throw new RisorsaEccezione('Impossibile scrivere il file in ' . $cartella . '.');
            }
            @chmod($destinazione, 0644);

            $risorsa = array_merge(self::CAMPI, [
                'progressivo' => $progressivo,
                'sigla'       => $sigla,
                'file'        => $nomeFile,
                'mime'        => $file['mime'],
                'dimensione'  => $file['dimensione'],
                'hash'        => 'sha256:' . hash_file('sha256', $destinazione),
                'copertina'   => false,
                'caricatoDa'  => Auth::usernameCorrente(),
                'caricatoIl'  => date('c'),
            ], self::ripulisci($metadati));

            if ((string) $risorsa['titolo'] === '') {
                // Senza titolo la galleria mostrerebbe solo nomi di file: si usa
                // il nome originale senza estensione, che e quello che chi
                // carica ha in mente.
                $risorsa['titolo'] = (string) pathinfo($file['nome'], PATHINFO_FILENAME);
            }

            // Data e posizione dai metadati del file, SOLO nei campi lasciati
            // vuoti: quello che l'operatore ha scritto vale piu dell'EXIF di un
            // telefono, e non va mai sovrascritto.
            $incorporati = MetadatiMedia::leggi($destinazione, Sezioni::anteprima($sigla));
            foreach (['data', 'latitudine', 'longitudine'] as $campo) {
                if ((string) $risorsa[$campo] === '' && $incorporati[$campo] !== '') {
                    $risorsa[$campo] = $incorporati[$campo];
                }
            }

            $dati['risorse'][] = $risorsa;
            $dati['ultimoProgressivo'] = $progressivo;
            self::scriviIndice($codice, $sigla, $dati);

            Log::modifica('risorsa_aggiunta', self::catalogoDi($codice), $codice, $sigla,
                $sigla . $progressivo . ' ' . $nomeFile);

            return $risorsa;
        });
    }

    /**
     * Aggiorna i metadati di una risorsa. Il file non viene toccato.
     *
     * @param array<string,mixed> $metadati
     */
    public static function aggiorna(string $codice, string $sigla, int $progressivo, array $metadati): void
    {
        $sigla  = strtoupper($sigla);
        $indice = self::percorsoIndice($codice, $sigla);
        if ($indice === null) {
            throw new RisorsaEccezione('Ipogeo non trovato: ' . $codice);
        }

        Xml::conLock($indice, static function () use ($codice, $sigla, $progressivo, $metadati): void {
            $dati    = self::leggiIndice($codice, $sigla);
            $trovata = false;

            foreach ($dati['risorse'] as $i => $risorsa) {
                if ((int) $risorsa['progressivo'] !== $progressivo) {
                    continue;
                }
                $dati['risorse'][$i] = array_merge($risorsa, self::ripulisci($metadati));
                $trovata = true;
                break;
            }

            if (!$trovata) {
                throw new RisorsaEccezione('Risorsa non trovata: ' . $sigla . $progressivo);
            }

            self::scriviIndice($codice, $sigla, $dati);
            Log::modifica('risorsa_aggiornata', self::catalogoDi($codice), $codice, $sigla, $sigla . $progressivo);
        });
    }

    /**
     * Sceglie la foto di copertina, togliendola alle altre.
     *
     * Con progressivo 0 si toglie la copertina senza assegnarla a nessuna.
     */
    public static function impostaCopertina(string $codice, int $progressivo): void
    {
        $indice = self::percorsoIndice($codice, 'FO');
        if ($indice === null) {
            throw new RisorsaEccezione('Ipogeo non trovato: ' . $codice);
        }

        Xml::conLock($indice, static function () use ($codice, $progressivo): void {
            $dati = self::leggiIndice($codice, 'FO');

            foreach ($dati['risorse'] as $i => $risorsa) {
                $dati['risorse'][$i]['copertina'] = ((int) $risorsa['progressivo'] === $progressivo);
            }

            self::scriviIndice($codice, 'FO', $dati);
            Log::modifica('copertina_scelta', self::catalogoDi($codice), $codice, 'FO',
                $progressivo > 0 ? 'FO' . $progressivo : '(nessuna)');
        });
    }

    /**
     * Rimuove una risorsa dalla sezione.
     *
     * Il file NON viene cancellato: viene spostato in "[codice] - _rimossi",
     * accanto alle altre sezioni. E la stessa scelta conservativa fatta per gli
     * ipogei: un archivio di catasto conserva anche gli errori, e un file
     * cancellato per sbaglio da un'interfaccia web non si recupera piu.
     */
    public static function elimina(string $codice, string $sigla, int $progressivo): void
    {
        $sigla  = strtoupper($sigla);
        $indice = self::percorsoIndice($codice, $sigla);
        if ($indice === null) {
            throw new RisorsaEccezione('Ipogeo non trovato: ' . $codice);
        }

        Xml::conLock($indice, static function () use ($codice, $sigla, $progressivo): void {
            $dati    = self::leggiIndice($codice, $sigla);
            $rimossa = null;
            $restanti = [];

            foreach ($dati['risorse'] as $risorsa) {
                if ((int) $risorsa['progressivo'] === $progressivo) {
                    $rimossa = $risorsa;
                    continue;
                }
                $restanti[] = $risorsa;
            }

            if ($rimossa === null) {
                throw new RisorsaEccezione('Risorsa non trovata: ' . $sigla . $progressivo);
            }

            $cartella = self::cartellaSezione($codice, $sigla);
            $file     = Percorsi::unisci((string) $cartella, (string) $rimossa['file']);

            if (is_file($file) && Percorsi::dentro((string) $cartella, $file)) {
                $deposito = Percorsi::assicuraCartella(Percorsi::unisci(
                    (string) Ipogeo::cartella($codice),
                    $codice . ' - ' . self::CARTELLA_RIMOSSI
                ));
                // Marca temporale nel nome: due rimozioni dello stesso file, a
                // distanza di tempo, non devono sovrascriversi.
                $destinazione = Percorsi::unisci($deposito, date('Ymd-His') . '-' . basename($file));
                @rename($file, $destinazione);
            }

            self::eliminaMiniatura($codice, $sigla, $rimossa);

            // L'ultimo progressivo resta dov'e: non si riusa.
            $dati['risorse'] = $restanti;
            self::scriviIndice($codice, $sigla, $dati);

            Log::modifica('risorsa_rimossa', self::catalogoDi($codice), $codice, $sigla,
                $sigla . $progressivo . ' ' . $rimossa['file']);
        });
    }

    // ========================================================================
    //  INDICE
    // ========================================================================

    /**
     * Legge l'indice di sezione. Se manca, restituisce un indice vuoto.
     *
     * @return array{risorse:array<int,array<string,mixed>>,ultimoProgressivo:int}
     */
    public static function leggiIndice(string $codice, string $sigla): array
    {
        $vuoto = ['risorse' => [], 'ultimoProgressivo' => 0];

        $percorso = self::percorsoIndice($codice, strtoupper($sigla));
        if ($percorso === null || !is_file($percorso)) {
            return $vuoto;
        }

        try {
            $doc = Xml::carica($percorso);
        } catch (Throwable $e) {
            // Un indice illeggibile non deve far sparire la scheda: si annota e
            // si mostra la sezione vuota, che e visibilmente sbagliata e quindi
            // porta a indagare, invece di una pagina di errore.
            Log::errore('Indice di sezione illeggibile: ' . $percorso . ' — ' . $e->getMessage());
            return $vuoto;
        }

        $radice = $doc->documentElement;
        if ($radice === null) {
            return $vuoto;
        }

        $risorse = [];
        foreach (Xml::elenco($doc, '/risorse/risorsa') as $nodo) {
            $risorse[] = self::daNodo($nodo);
        }

        usort($risorse, static fn (array $a, array $b): int => $a['progressivo'] <=> $b['progressivo']);

        $ultimo = (int) $radice->getAttribute('ultimoProgressivo');
        foreach ($risorse as $r) {
            // Difesa contro un attributo modificato a mano al ribasso: il
            // massimo presente e comunque gia stato assegnato.
            $ultimo = max($ultimo, (int) $r['progressivo']);
        }

        return ['risorse' => $risorse, 'ultimoProgressivo' => $ultimo];
    }

    /**
     * Riscrive l'indice di sezione.
     *
     * @param array{risorse:array<int,array<string,mixed>>,ultimoProgressivo:int} $dati
     */
    public static function scriviIndice(string $codice, string $sigla, array $dati): void
    {
        $sigla    = strtoupper($sigla);
        $percorso = self::percorsoIndice($codice, $sigla);
        if ($percorso === null) {
            throw new RisorsaEccezione('Ipogeo non trovato: ' . $codice);
        }

        Percorsi::assicuraCartella(dirname($percorso));

        $doc = Xml::nuovo('risorse', [
            'versioneSchema'    => self::VERSIONE_SCHEMA,
            'sezione'           => strtolower(Sezioni::cartella($sigla)),
            'codiceIpogeo'      => $codice,
            'ultimoProgressivo' => (string) $dati['ultimoProgressivo'],
        ]);
        $radice = $doc->documentElement;

        foreach ($dati['risorse'] as $risorsa) {
            self::aNodo($radice, $risorsa);
        }

        Xml::salva($doc, $percorso, Percorsi::schema('risorse.xsd'));
    }

    // ========================================================================
    //  MINIATURE
    // ========================================================================

    /** Cartella delle miniature di una sezione. */
    public static function cartellaMiniature(string $codice, string $sigla): ?string
    {
        $cartella = self::cartellaSezione($codice, $sigla);
        return $cartella === null ? null : Percorsi::unisci($cartella, Sezioni::MINIATURE);
    }

    /**
     * Percorso della miniatura di una risorsa, se esiste.
     *
     * @param array<string,mixed> $risorsa
     */
    public static function percorsoMiniatura(string $codice, string $sigla, array $risorsa): ?string
    {
        $cartella = self::cartellaMiniature($codice, $sigla);
        if ($cartella === null) {
            return null;
        }
        $percorso = Percorsi::unisci($cartella, self::nomeMiniatura($risorsa));

        return is_file($percorso) ? $percorso : null;
    }

    /** Nome del file di miniatura corrispondente a una risorsa. */
    public static function nomeMiniatura(array $risorsa): string
    {
        return (string) pathinfo((string) $risorsa['file'], PATHINFO_FILENAME) . '.jpg';
    }

    /** @param array<string,mixed> $risorsa */
    private static function eliminaMiniatura(string $codice, string $sigla, array $risorsa): void
    {
        $mini = self::percorsoMiniatura($codice, $sigla, $risorsa);
        if ($mini !== null) {
            // La miniatura si rigenera dall'originale: qui si puo cancellare.
            @unlink($mini);
        }
    }

    // ========================================================================
    //  CONVERSIONE XML
    // ========================================================================

    /** @return array<string,mixed> */
    private static function daNodo(DOMNode $nodo): array
    {
        $risorsa = self::CAMPI;

        $risorsa['progressivo'] = $nodo instanceof DOMElement ? (int) $nodo->getAttribute('progressivo') : 0;
        $risorsa['sigla']       = $nodo instanceof DOMElement ? strtoupper($nodo->getAttribute('sigla')) : '';
        $risorsa['file']        = Xml::testo($nodo, 'file');
        $risorsa['mime']        = Xml::testo($nodo, 'mime');
        $risorsa['dimensione']  = Xml::intero($nodo, 'dimensione');
        $risorsa['hash']        = Xml::testo($nodo, 'hash');
        $risorsa['copertina']   = Xml::booleano($nodo, 'copertina');
        $risorsa['caricatoDa']  = '';
        $risorsa['caricatoIl']  = Xml::testo($nodo, 'caricato');

        $caricato = Xml::primo($nodo, 'caricato');
        if ($caricato instanceof DOMElement) {
            $risorsa['caricatoDa'] = $caricato->getAttribute('utente');
        }

        foreach (['titolo', 'descrizione', 'data', 'licenza', 'riservatezza',
                  'categoriaAllegato', 'urlEsterno'] as $campo) {
            $risorsa[$campo] = Xml::testo($nodo, $campo);
        }

        $risorsa['autore'] = Xml::testo($nodo, 'autore');
        $autore = Xml::primo($nodo, 'autore');
        if ($autore instanceof DOMElement) {
            $risorsa['autoreId'] = $autore->getAttribute('esploratoreId');
        }

        $gruppo = Xml::primo($nodo, 'gruppo');
        if ($gruppo instanceof DOMElement) {
            $risorsa['gruppoId'] = $gruppo->getAttribute('id');
        }

        $risorsa['latitudine']  = Xml::testo($nodo, 'coordinate/latitudine');
        $risorsa['longitudine'] = Xml::testo($nodo, 'coordinate/longitudine');

        foreach (['tipoRilievo', 'scala', 'sistemaRiferimento', 'dataRilievo',
                  'strumentazione', 'rilevatori'] as $campo) {
            $risorsa[$campo] = Xml::testo($nodo, $campo);
        }

        // Assente significa acceso: un tracciato appena caricato si vede.
        $mostra = Xml::primo($nodo, 'mostraInMappa');
        $risorsa['mostraInMappa'] = $mostra === null ? '1' : (trim($mostra->textContent) === '0' ? '0' : '1');

        if ((string) $risorsa['riservatezza'] === '') {
            $risorsa['riservatezza'] = 'pubblica';
        }

        return $risorsa;
    }

    /** @param array<string,mixed> $risorsa */
    private static function aNodo(DOMElement $radice, array $risorsa): void
    {
        $nodo = Xml::aggiungi($radice, 'risorsa', null, [
            'progressivo' => (string) $risorsa['progressivo'],
            'sigla'       => (string) $risorsa['sigla'],
        ]);

        Xml::imposta($nodo, 'file', (string) $risorsa['file']);
        Xml::imposta($nodo, 'titolo', (string) $risorsa['titolo']);

        // Descrizione in CDATA: e testo libero senza limiti di lunghezza (D6) e
        // puo contenere caratteri che altrimenti andrebbero protetti a mano.
        Xml::imposta($nodo, 'descrizione', (string) $risorsa['descrizione'], true);

        Xml::imposta($nodo, 'data', (string) $risorsa['data']);

        if ((string) $risorsa['autore'] !== '' || (string) $risorsa['autoreId'] !== '') {
            $autore = Xml::imposta($nodo, 'autore', (string) $risorsa['autore']);
            if ((string) $risorsa['autoreId'] !== '') {
                $autore->setAttribute('esploratoreId', (string) $risorsa['autoreId']);
            }
        }

        if ((string) $risorsa['gruppoId'] !== '') {
            Xml::aggiungi($nodo, 'gruppo', null, ['id' => (string) $risorsa['gruppoId']]);
        }

        foreach (['licenza', 'categoriaAllegato', 'urlEsterno', 'tipoRilievo', 'scala',
                  'sistemaRiferimento', 'dataRilievo', 'strumentazione', 'rilevatori'] as $campo) {
            if ((string) $risorsa[$campo] !== '') {
                Xml::imposta($nodo, $campo, (string) $risorsa[$campo]);
            }
        }

        // Le coordinate della singola risorsa: dove e stata scattata la foto,
        // che non e detto coincida con l'ingresso registrato nella scheda.
        if ((string) $risorsa['latitudine'] !== '' && (string) $risorsa['longitudine'] !== '') {
            $coord = Xml::imposta($nodo, 'coordinate', null);
            Xml::imposta($coord, 'latitudine', (string) $risorsa['latitudine']);
            Xml::imposta($coord, 'longitudine', (string) $risorsa['longitudine']);
        }

        Xml::imposta($nodo, 'riservatezza', (string) $risorsa['riservatezza']);

        if (!empty($risorsa['copertina'])) {
            Xml::imposta($nodo, 'copertina', '1');
        }

        // Si scrive solo quando e spento: l'acceso e il comportamento normale e
        // riempire l'indice di 1 inutili lo rende solo piu lungo da leggere.
        if ((string) $risorsa['mostraInMappa'] === '0') {
            Xml::imposta($nodo, 'mostraInMappa', '0');
        }

        Xml::imposta($nodo, 'mime', (string) $risorsa['mime']);
        Xml::imposta($nodo, 'dimensione', (string) $risorsa['dimensione']);

        if ((string) $risorsa['hash'] !== '') {
            Xml::imposta($nodo, 'hash', (string) $risorsa['hash']);
        }

        $caricato = Xml::imposta($nodo, 'caricato', (string) $risorsa['caricatoIl']);
        if ((string) $risorsa['caricatoDa'] !== '') {
            $caricato->setAttribute('utente', (string) $risorsa['caricatoDa']);
        }
    }

    /**
     * Sigla del catalogo a cui appartiene l'ipogeo, per il log.
     *
     * Si legge dall'indice e non dalla scheda: e una sola riga di CSV invece
     * dell'apertura di un XML, e il log non deve costare piu dell'operazione
     * che registra.
     */
    private static function catalogoDi(string $codice): string
    {
        $riga = IndiceIpogei::trova($codice);
        return $riga === null ? '' : (string) ($riga['catalogo'] ?? '');
    }

    /**
     * Tiene dei metadati in arrivo solo i campi previsti.
     *
     * @param  array<string,mixed> $metadati
     * @return array<string,mixed>
     */
    private static function ripulisci(array $metadati): array
    {
        $puliti = [];
        foreach ($metadati as $chiave => $valore) {
            if (array_key_exists($chiave, self::CAMPI)) {
                $puliti[$chiave] = is_string($valore) ? trim($valore) : $valore;
            }
        }
        return $puliti;
    }
}
