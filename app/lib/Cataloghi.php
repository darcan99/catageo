<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Cataloghi.php
 *  Descrizione ..: Gestione dei cataloghi (D5). Un catalogo e una cartella
 *                  dati/cataloghi/[sigla] - [nome]/ con il proprio
 *                  catalogo.xml e la propria cartella ipogei/.
 *
 *                  I cataloghi non hanno un registro centrale: vengono scoperti
 *                  scandendo l'archivio. Cosi un catalogo copiato a mano compare
 *                  da solo, e non esiste un elenco che possa disallinearsi dai
 *                  dati. I contatori dei codici vivono dentro il catalogo,
 *                  quindi due cataloghi non si contendono lo stesso lock.
 *  Versione .....: 0.3.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.3.0  2026-08-04  D.Candela  Prima stesura (fase 2b).
 * ============================================================================
 */

final class Cataloghi
{
    /** Nome fisso del descrittore dentro la cartella del catalogo. */
    public const DESCRITTORE = 'catalogo.xml';

    /** Chiave di sessione del catalogo attivo. */
    private const K_ATTIVO = 'catageo_catalogo_attivo';

    /** Cache dell'elenco, per richiesta. */
    private static ?array $cache = null;

    // ------------------------------------------------------------------ scoperta

    /**
     * Elenco dei cataloghi presenti nell'archivio, ordinato per sigla.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(bool $soloAttivi = false): array
    {
        if (self::$cache === null) {
            $radice = Percorsi::cataloghi();
            $trovati = [];

            if (is_dir($radice)) {
                foreach (scandir($radice) ?: [] as $voce) {
                    if ($voce === '.' || $voce === '..') {
                        continue;
                    }
                    $cartella = Percorsi::unisci($radice, $voce);
                    if (!is_dir($cartella)) {
                        continue;
                    }
                    $descrittore = Percorsi::unisci($cartella, self::DESCRITTORE);
                    if (!is_file($descrittore)) {
                        continue; // cartella senza descrittore: non e un catalogo
                    }

                    try {
                        $trovati[] = self::daDescrittore($descrittore, $voce);
                    } catch (Throwable $e) {
                        // Un catalogo illeggibile non deve nascondere gli altri:
                        // si annota e si prosegue.
                        Log::errore('Catalogo non leggibile in "' . $voce . '": ' . $e->getMessage(), 'avviso');
                    }
                }
            }

            usort($trovati, static fn (array $a, array $b): int => strcasecmp((string) $a['sigla'], (string) $b['sigla']));
            self::$cache = $trovati;
        }

        if (!$soloAttivi) {
            return self::$cache;
        }
        return array_values(array_filter(self::$cache, static fn (array $c): bool => $c['attivo']));
    }

    /**
     * Cerca un catalogo per sigla.
     *
     * @return array<string,mixed>|null
     */
    public static function trova(string $sigla): ?array
    {
        foreach (self::elenco() as $catalogo) {
            if (strcasecmp((string) $catalogo['sigla'], $sigla) === 0) {
                return $catalogo;
            }
        }
        return null;
    }

    /** Numero di cataloghi presenti. */
    public static function conta(bool $soloAttivi = false): int
    {
        return count(self::elenco($soloAttivi));
    }

    /** Svuota la cache: da chiamare dopo ogni scrittura. */
    public static function invalidaCache(): void
    {
        self::$cache = null;
    }

    // --------------------------------------------------------------- catalogo attivo

    /**
     * Sigla del catalogo attivo per l'utente corrente.
     *
     * Si parte dal catalogo scelto in sessione, poi da quello predefinito in
     * configurazione, poi dal primo attivo disponibile.
     */
    public static function siglaAttiva(): string
    {
        $candidati = [];

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[self::K_ATTIVO])) {
            $candidati[] = (string) $_SESSION[self::K_ATTIVO];
        }
        if (Config::caricata()) {
            $candidati[] = Config::testo('cataloghi.predefinito', '');
        }

        foreach ($candidati as $sigla) {
            if ($sigla !== '' && self::trova($sigla) !== null) {
                return $sigla;
            }
        }

        $attivi = self::elenco(true);
        return $attivi === [] ? '' : (string) $attivi[0]['sigla'];
    }

    /**
     * Imposta il catalogo attivo in sessione.
     *
     * @throws CatalogoEccezione
     */
    public static function impostaAttivo(string $sigla): void
    {
        if (self::trova($sigla) === null) {
            throw new CatalogoEccezione('Catalogo non trovato.');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::K_ATTIVO] = $sigla;
        }
    }

    // ------------------------------------------------------------------ percorsi

    /**
     * Cartella di un catalogo.
     *
     * @throws CatalogoEccezione
     */
    public static function cartella(string $sigla): string
    {
        $catalogo = self::trova($sigla);
        if ($catalogo === null) {
            throw new CatalogoEccezione('Catalogo non trovato: ' . $sigla);
        }
        return Percorsi::cataloghi((string) $catalogo['cartella']);
    }

    /** Percorso del descrittore di un catalogo. */
    public static function descrittore(string $sigla): string
    {
        return Percorsi::unisci(self::cartella($sigla), self::DESCRITTORE);
    }

    /** Cartella degli ipogei di un catalogo. */
    public static function cartellaIpogei(string $sigla): string
    {
        return Percorsi::unisci(self::cartella($sigla), 'ipogei');
    }

    /** Nome di cartella normativo per un catalogo: "[sigla] - [nome]". */
    public static function nomeCartella(string $sigla, string $nome): string
    {
        return Testo::nomeFileSicuro($sigla . ' - ' . $nome, false, 150);
    }

    // ------------------------------------------------------------------ scrittura

    /**
     * Crea un catalogo con una serie di codifica iniziale.
     *
     * @param  array<string,mixed> $dati sigla, nome, ente, descrizione, stato,
     *                                   regione, responsabile, prefisso, cifre
     * @return string sigla assegnata
     * @throws CatalogoEccezione
     */
    public static function crea(array $dati): string
    {
        $sigla = self::normalizzaSigla((string) ($dati['sigla'] ?? ''));
        $nome  = trim((string) ($dati['nome'] ?? ''));

        self::validaSigla($sigla);
        if ($nome === '') {
            throw new CatalogoEccezione('Il nome del catalogo è obbligatorio.');
        }
        if (self::trova($sigla) !== null) {
            throw new CatalogoEccezione("Esiste già un catalogo con sigla \"{$sigla}\".");
        }

        $cartella = Percorsi::cataloghi(self::nomeCartella($sigla, $nome));
        if (is_dir($cartella)) {
            throw new CatalogoEccezione('Esiste già una cartella con questo nome nell\'archivio.');
        }

        // La serie iniziale e obbligatoria: un catalogo senza serie non
        // potrebbe assegnare nessun codice, quindi sarebbe inutilizzabile.
        $prefisso = self::normalizzaSigla((string) ($dati['prefisso'] ?? $sigla));
        $cifre    = (int) ($dati['cifre'] ?? 3);
        CodiceCatastale::validaPrefisso($prefisso);
        CodiceCatastale::validaCifre($cifre);

        Percorsi::assicuraCartella($cartella);
        Percorsi::assicuraCartella(Percorsi::unisci($cartella, 'ipogei'));

        $doc    = Xml::nuovo('catalogo', ['versioneSchema' => '1.0']);
        $radice = $doc->documentElement;
        if ($radice === null) {
            throw new CatalogoEccezione('Creazione del descrittore non riuscita.');
        }

        $identita = Xml::aggiungi($radice, 'identita');
        Xml::imposta($identita, 'sigla', $sigla);
        Xml::imposta($identita, 'nome', $nome);
        Xml::imposta($identita, 'ente', trim((string) ($dati['ente'] ?? '')));
        Xml::imposta($identita, 'descrizione', (string) ($dati['descrizione'] ?? ''), true);
        $responsabile = trim((string) ($dati['responsabile'] ?? ''));
        Xml::aggiungi($identita, 'responsabile', null, ['esploratoreId' => $responsabile]);
        $ambito = Xml::aggiungi($identita, 'ambito');
        Xml::imposta($ambito, 'stato', strtoupper(trim((string) ($dati['stato'] ?? 'IT'))));
        Xml::imposta($ambito, 'regione', trim((string) ($dati['regione'] ?? '')));
        Xml::imposta($identita, 'dataIstituzione', date('Y-m-d'));
        Xml::imposta($identita, 'attivo', '1');

        $codifica = Xml::aggiungi($radice, 'codifica');
        Xml::imposta($codifica, 'separatore', (string) ($dati['separatore'] ?? ''));
        Xml::imposta($codifica, 'consentiCodiceManuale', !empty($dati['consentiCodiceManuale']) ? '1' : '0');
        $serie = Xml::aggiungi($codifica, 'serie');
        Xml::aggiungi($serie, 'serieCodice', null, [
            'prefisso'            => $prefisso,
            'nome'                => 'Serie unica',
            'cifre'               => (string) $cifre,
            'prossimoProgressivo' => '1',
        ]);

        $visualizzazione = Xml::aggiungi($radice, 'visualizzazione');
        Xml::imposta($visualizzazione, 'sistemaPreferito', trim((string) ($dati['sistemaPreferito'] ?? '')));

        Xml::aggiungi($radice, 'origine');

        Xml::salva($doc, Percorsi::unisci($cartella, self::DESCRITTORE), self::xsd());
        self::invalidaCache();

        return $sigla;
    }

    /**
     * Aggiorna l'identita e le opzioni di codifica di un catalogo.
     * La sigla non e modificabile: compare nel nome della cartella e nei codici.
     *
     * @param  array<string,mixed> $dati
     * @throws CatalogoEccezione
     */
    public static function aggiorna(string $sigla, array $dati): void
    {
        $catalogo = self::trova($sigla);
        if ($catalogo === null) {
            throw new CatalogoEccezione('Catalogo non trovato.');
        }

        $nome = trim((string) ($dati['nome'] ?? ''));
        if ($nome === '') {
            throw new CatalogoEccezione('Il nome del catalogo è obbligatorio.');
        }

        $descrittore = self::descrittore($sigla);

        Xml::conLock($descrittore, static function () use ($descrittore, $dati, $nome): void {
            $doc = Xml::carica($descrittore);

            $identita = Xml::primo($doc, '/catalogo/identita');
            if (!$identita instanceof DOMElement) {
                throw new CatalogoEccezione('Descrittore del catalogo malformato.');
            }
            Xml::imposta($identita, 'nome', $nome);
            Xml::imposta($identita, 'ente', trim((string) ($dati['ente'] ?? '')));
            Xml::imposta($identita, 'descrizione', (string) ($dati['descrizione'] ?? ''), true);
            Xml::imposta($identita, 'attivo', !empty($dati['attivo']) ? '1' : '0');

            $responsabile = Xml::primo($identita, 'responsabile');
            if (!$responsabile instanceof DOMElement) {
                $responsabile = Xml::aggiungi($identita, 'responsabile');
            }
            $responsabile->setAttribute('esploratoreId', trim((string) ($dati['responsabile'] ?? '')));

            $ambito = Xml::primo($identita, 'ambito');
            if (!$ambito instanceof DOMElement) {
                $ambito = Xml::aggiungi($identita, 'ambito');
            }
            Xml::imposta($ambito, 'stato', strtoupper(trim((string) ($dati['stato'] ?? 'IT'))));
            Xml::imposta($ambito, 'regione', trim((string) ($dati['regione'] ?? '')));

            $codifica = Xml::primo($doc, '/catalogo/codifica');
            if ($codifica instanceof DOMElement) {
                Xml::imposta($codifica, 'separatore', (string) ($dati['separatore'] ?? ''));
                Xml::imposta($codifica, 'consentiCodiceManuale', !empty($dati['consentiCodiceManuale']) ? '1' : '0');
            }

            $visualizzazione = Xml::primo($doc, '/catalogo/visualizzazione');
            if (!$visualizzazione instanceof DOMElement) {
                $radiceDoc = $doc->documentElement;
                if ($radiceDoc === null) {
                    throw new CatalogoEccezione('Descrittore del catalogo malformato.');
                }
                $visualizzazione = Xml::aggiungi($radiceDoc, 'visualizzazione');
            }
            Xml::imposta($visualizzazione, 'sistemaPreferito', trim((string) ($dati['sistemaPreferito'] ?? '')));

            Xml::salva($doc, $descrittore, self::xsd());
        });

        // Se il nome e cambiato, la cartella va rinominata: il nome della
        // cartella e parte dello standard di nomenclatura, non un'etichetta.
        if ($nome !== (string) $catalogo['nome']) {
            self::rinominaCartella($sigla, (string) $catalogo['cartella'], $nome);
        }

        self::invalidaCache();
    }

    /**
     * Elimina un catalogo, solo se non contiene ipogei.
     *
     * @throws CatalogoEccezione
     */
    public static function elimina(string $sigla): void
    {
        $catalogo = self::trova($sigla);
        if ($catalogo === null) {
            throw new CatalogoEccezione('Catalogo non trovato.');
        }
        if ((int) $catalogo['numeroIpogei'] > 0) {
            throw new CatalogoEccezione(
                'Cancellazione rifiutata: il catalogo contiene ' . $catalogo['numeroIpogei'] . ' ipogei. '
                . 'Migrarli in un altro catalogo, oppure disattivare questo catalogo.'
            );
        }

        $cartella = self::cartella($sigla);

        // Si rimuovono solo i file che l'applicativo ha creato. Se dentro c'e
        // altro, la cancellazione si ferma: meglio un rifiuto che perdere file
        // che qualcuno ha messo a mano nell'archivio.
        $attesi = [self::DESCRITTORE, self::DESCRITTORE . '.lock', 'ipogei'];
        foreach (scandir($cartella) ?: [] as $voce) {
            if ($voce === '.' || $voce === '..' || in_array($voce, $attesi, true)) {
                continue;
            }
            throw new CatalogoEccezione(
                'Cancellazione rifiutata: la cartella del catalogo contiene "' . $voce . '", '
                . 'che non è stato creato dall\'applicativo. Rimuoverlo a mano se non serve.'
            );
        }

        @unlink(Percorsi::unisci($cartella, self::DESCRITTORE));
        @unlink(Percorsi::unisci($cartella, self::DESCRITTORE . '.lock'));
        @rmdir(Percorsi::unisci($cartella, 'ipogei'));

        if (!@rmdir($cartella)) {
            throw new CatalogoEccezione('La cartella del catalogo non è stata rimossa: verificarne il contenuto.');
        }

        self::invalidaCache();
    }

    // -------------------------------------------------------- serie di codifica

    /**
     * Aggiunge una serie di codifica in coda all'elenco.
     *
     * L'ordine conta: vince la prima serie i cui criteri sono soddisfatti,
     * quindi le serie piu specifiche vanno prima di quelle generiche.
     *
     * @param  array<string,mixed> $dati
     * @throws CatalogoEccezione
     */
    public static function aggiungiSerie(string $sigla, array $dati): void
    {
        $prefisso = self::normalizzaSigla((string) ($dati['prefisso'] ?? ''));
        $cifre    = (int) ($dati['cifre'] ?? 3);

        CodiceCatastale::validaPrefisso($prefisso);
        CodiceCatastale::validaCifre($cifre);

        $catalogo = self::trova($sigla);
        if ($catalogo === null) {
            throw new CatalogoEccezione('Catalogo non trovato.');
        }
        foreach ($catalogo['serie'] as $serie) {
            if (strcasecmp((string) $serie['prefisso'], $prefisso) === 0) {
                throw new CatalogoEccezione("Il prefisso \"{$prefisso}\" è già usato da un'altra serie di questo catalogo.");
            }
        }

        $progressivo = (string) ($dati['prossimoProgressivo'] ?? '1');
        if (!preg_match('/^[0-9]+$/', $progressivo) || (int) $progressivo < 1) {
            throw new CatalogoEccezione('Il progressivo iniziale deve essere un intero maggiore di zero.');
        }

        $descrittore = self::descrittore($sigla);

        Xml::conLock($descrittore, static function () use ($descrittore, $dati, $prefisso, $cifre, $progressivo): void {
            $doc   = Xml::carica($descrittore);
            $serie = Xml::primo($doc, '/catalogo/codifica/serie');
            if (!$serie instanceof DOMElement) {
                $codifica = Xml::primo($doc, '/catalogo/codifica');
                if (!$codifica instanceof DOMElement) {
                    throw new CatalogoEccezione('Descrittore del catalogo malformato.');
                }
                $serie = Xml::aggiungi($codifica, 'serie');
            }

            $attributi = [
                'prefisso'            => $prefisso,
                'nome'                => trim((string) ($dati['nome'] ?? '')),
                'cifre'               => (string) $cifre,
                'prossimoProgressivo' => $progressivo,
            ];
            foreach (CodiceCatastale::CRITERI as $criterio) {
                $valore = trim((string) ($dati[$criterio] ?? ''));
                if ($valore !== '') {
                    $attributi[$criterio] = $valore;
                }
            }

            Xml::aggiungi($serie, 'serieCodice', null, $attributi);
            Xml::salva($doc, $descrittore, self::xsd());
        });

        self::invalidaCache();
    }

    /**
     * Aggiorna una serie esistente. Il prefisso non e modificabile: compare nei
     * codici gia assegnati.
     *
     * @param  array<string,mixed> $dati
     * @throws CatalogoEccezione
     */
    public static function aggiornaSerie(string $sigla, string $prefisso, array $dati): void
    {
        $cifre = (int) ($dati['cifre'] ?? 3);
        CodiceCatastale::validaCifre($cifre);

        $progressivo = (string) ($dati['prossimoProgressivo'] ?? '1');
        if (!preg_match('/^[0-9]+$/', $progressivo) || (int) $progressivo < 1) {
            throw new CatalogoEccezione('Il progressivo deve essere un intero maggiore di zero.');
        }

        $descrittore = self::descrittore($sigla);

        Xml::conLock($descrittore, static function () use ($descrittore, $prefisso, $dati, $cifre, $progressivo): void {
            $doc  = Xml::carica($descrittore);
            $nodo = self::nodoSerie($doc, $prefisso);
            if ($nodo === null) {
                throw new CatalogoEccezione('Serie non trovata.');
            }

            $nodo->setAttribute('nome', trim((string) ($dati['nome'] ?? '')));
            $nodo->setAttribute('cifre', (string) $cifre);
            $nodo->setAttribute('prossimoProgressivo', $progressivo);

            foreach (CodiceCatastale::CRITERI as $criterio) {
                $valore = trim((string) ($dati[$criterio] ?? ''));
                if ($valore === '') {
                    $nodo->removeAttribute($criterio);
                } else {
                    $nodo->setAttribute($criterio, $valore);
                }
            }

            Xml::salva($doc, $descrittore, self::xsd());
        });

        self::invalidaCache();
    }

    /**
     * Elimina una serie, solo se nessun codice l'ha usata.
     *
     * @throws CatalogoEccezione
     */
    public static function eliminaSerie(string $sigla, string $prefisso): void
    {
        $catalogo = self::trova($sigla);
        if ($catalogo === null) {
            throw new CatalogoEccezione('Catalogo non trovato.');
        }
        if (count($catalogo['serie']) <= 1) {
            throw new CatalogoEccezione(
                'Cancellazione rifiutata: e l\'unica serie del catalogo, che senza serie non potrebbe assegnare codici.'
            );
        }

        $usati = IndiceCodici::contaConPrefisso($prefisso);
        if ($usati > 0) {
            throw new CatalogoEccezione(
                "Cancellazione rifiutata: {$usati} codici sono stati assegnati con il prefisso \"{$prefisso}\"."
            );
        }

        $descrittore = self::descrittore($sigla);

        Xml::conLock($descrittore, static function () use ($descrittore, $prefisso): void {
            $doc  = Xml::carica($descrittore);
            $nodo = self::nodoSerie($doc, $prefisso);
            if ($nodo === null) {
                throw new CatalogoEccezione('Serie non trovata.');
            }
            Xml::rimuovi($nodo);
            Xml::salva($doc, $descrittore, self::xsd());
        });

        self::invalidaCache();
    }

    /**
     * Sposta una serie di una posizione su o giu nell'elenco.
     *
     * Serve perche l'ordine determina quale serie vince: una serie generica
     * messa per prima intercetterebbe tutto, rendendo inutili le successive.
     *
     * @param  int $direzione -1 per salire, +1 per scendere
     * @throws CatalogoEccezione
     */
    public static function spostaSerie(string $sigla, string $prefisso, int $direzione): void
    {
        $descrittore = self::descrittore($sigla);

        Xml::conLock($descrittore, static function () use ($descrittore, $prefisso, $direzione): void {
            $doc       = Xml::carica($descrittore);
            $contenitore = Xml::primo($doc, '/catalogo/codifica/serie');
            if (!$contenitore instanceof DOMElement) {
                throw new CatalogoEccezione('Nessuna serie da riordinare.');
            }

            $nodi = Xml::elenco($contenitore, 'serieCodice');
            $indice = null;
            foreach ($nodi as $i => $nodo) {
                if (strcasecmp($nodo->getAttribute('prefisso'), $prefisso) === 0) {
                    $indice = $i;
                    break;
                }
            }
            if ($indice === null) {
                throw new CatalogoEccezione('Serie non trovata.');
            }

            $nuovo = $indice + $direzione;
            if ($nuovo < 0 || $nuovo >= count($nodi)) {
                return; // gia al limite: non e un errore, semplicemente non si muove
            }

            $daSpostare = $nodi[$indice];
            if ($direzione < 0) {
                $contenitore->insertBefore($daSpostare, $nodi[$nuovo]);
            } else {
                $riferimento = $nodi[$nuovo]->nextSibling;
                if ($riferimento === null) {
                    $contenitore->appendChild($daSpostare);
                } else {
                    $contenitore->insertBefore($daSpostare, $riferimento);
                }
            }

            Xml::salva($doc, $descrittore, self::xsd());
        });

        self::invalidaCache();
    }

    /**
     * Incrementa e restituisce il progressivo di una serie, sotto lock.
     * Usata da CodiceCatastale in fase di assegnazione.
     *
     * @return int progressivo assegnato
     * @throws CatalogoEccezione
     */
    public static function prelevaProgressivo(string $sigla, string $prefisso): int
    {
        $descrittore = self::descrittore($sigla);

        $progressivo = Xml::conLock($descrittore, static function () use ($descrittore, $prefisso): int {
            $doc  = Xml::carica($descrittore);
            $nodo = self::nodoSerie($doc, $prefisso);
            if ($nodo === null) {
                throw new CatalogoEccezione('Serie non trovata: ' . $prefisso);
            }

            $corrente = $nodo->getAttribute('prossimoProgressivo');
            $valore   = $corrente === '' ? 1 : (int) $corrente;

            // Il contatore e conservato come stringa numerica e incrementato in
            // intero: nessun passaggio in virgola mobile, che oltre i 2^53
            // comincerebbe a sbagliare in silenzio (D7).
            $nodo->setAttribute('prossimoProgressivo', (string) ($valore + 1));

            Xml::salva($doc, $descrittore, self::xsd());

            return $valore;
        });

        self::invalidaCache();

        return $progressivo;
    }

    /**
     * Allinea in avanti il contatore di una serie, mai indietro.
     * Serve dopo l'inserimento manuale di un codice piu alto del contatore,
     * tipico dell'importazione di un catasto esistente.
     */
    public static function allineaProgressivo(string $sigla, string $prefisso, int $minimo): void
    {
        $descrittore = self::descrittore($sigla);

        Xml::conLock($descrittore, static function () use ($descrittore, $prefisso, $minimo): void {
            $doc  = Xml::carica($descrittore);
            $nodo = self::nodoSerie($doc, $prefisso);
            if ($nodo === null) {
                return;
            }
            $corrente = (int) ($nodo->getAttribute('prossimoProgressivo') ?: '1');
            if ($minimo > $corrente) {
                $nodo->setAttribute('prossimoProgressivo', (string) $minimo);
                Xml::salva($doc, $descrittore, self::xsd());
            }
        });

        self::invalidaCache();
    }

    // ------------------------------------------------------------------- interni

    /** Percorso dello schema, se presente. */
    private static function xsd(): ?string
    {
        $p = Percorsi::schema('catalogo.xsd');
        return is_file($p) ? $p : null;
    }

    /** Nodo di una serie dato il prefisso. */
    private static function nodoSerie(DOMDocument $doc, string $prefisso): ?DOMElement
    {
        foreach (Xml::elenco($doc, '/catalogo/codifica/serie/serieCodice') as $nodo) {
            if (strcasecmp($nodo->getAttribute('prefisso'), $prefisso) === 0) {
                return $nodo;
            }
        }
        return null;
    }

    /**
     * Legge un descrittore e ne ricava la struttura in array.
     *
     * @return array<string,mixed>
     */
    private static function daDescrittore(string $percorso, string $cartella): array
    {
        $doc = Xml::carica($percorso);

        $serie = [];
        foreach (Xml::elenco($doc, '/catalogo/codifica/serie/serieCodice') as $nodo) {
            $criteri = [];
            foreach (CodiceCatastale::CRITERI as $criterio) {
                $valore = $nodo->getAttribute($criterio);
                if ($valore !== '') {
                    $criteri[$criterio] = $valore;
                }
            }
            $serie[] = [
                'prefisso'            => $nodo->getAttribute('prefisso'),
                'nome'                => $nodo->getAttribute('nome'),
                'cifre'               => $nodo->getAttribute('cifre') !== '' ? (int) $nodo->getAttribute('cifre') : 3,
                'prossimoProgressivo' => $nodo->getAttribute('prossimoProgressivo') !== ''
                    ? (int) $nodo->getAttribute('prossimoProgressivo') : 1,
                'criteri'             => $criteri,
            ];
        }

        $cartellaIpogei = Percorsi::unisci(Percorsi::cataloghi($cartella), 'ipogei');
        $numeroIpogei   = 0;
        if (is_dir($cartellaIpogei)) {
            foreach (scandir($cartellaIpogei) ?: [] as $voce) {
                if ($voce !== '.' && $voce !== '..' && is_dir(Percorsi::unisci($cartellaIpogei, $voce))) {
                    $numeroIpogei++;
                }
            }
        }

        return [
            'sigla'                 => Xml::testo($doc, '/catalogo/identita/sigla'),
            'nome'                  => Xml::testo($doc, '/catalogo/identita/nome'),
            'ente'                  => Xml::testo($doc, '/catalogo/identita/ente'),
            'descrizione'           => Xml::testo($doc, '/catalogo/identita/descrizione'),
            'responsabile'          => (string) (Xml::primo($doc, '/catalogo/identita/responsabile') instanceof DOMElement
                ? Xml::primo($doc, '/catalogo/identita/responsabile')->getAttribute('esploratoreId') : ''),
            'stato'                 => Xml::testo($doc, '/catalogo/identita/ambito/stato', 'IT'),
            'regione'               => Xml::testo($doc, '/catalogo/identita/ambito/regione'),
            'dataIstituzione'       => Xml::testo($doc, '/catalogo/identita/dataIstituzione'),
            'attivo'                => Xml::booleano($doc, '/catalogo/identita/attivo', true),
            'separatore'            => Xml::testo($doc, '/catalogo/codifica/separatore'),
            'consentiCodiceManuale' => Xml::booleano($doc, '/catalogo/codifica/consentiCodiceManuale', false),
            'serie'                 => $serie,
            // Notazione con cui il catalogo e abituato a scrivere le posizioni:
            // il catasto del Lazio lavora in UTM WGS84 33N, e le sue schede
            // devono mostrare quella per prima. Vuoto = solo gradi e UTM del
            // fuso che contiene il punto.
            'sistemaPreferito'      => Xml::testo($doc, '/catalogo/visualizzazione/sistemaPreferito'),
            'origine'               => [
                'catastoOrigine'   => Xml::testo($doc, '/catalogo/origine/catastoOrigine'),
                'riferimento'      => Xml::testo($doc, '/catalogo/origine/riferimento'),
                'dataImportazione' => Xml::testo($doc, '/catalogo/origine/dataImportazione'),
                'licenzaDati'      => Xml::testo($doc, '/catalogo/origine/licenzaDati'),
            ],
            'cartella'              => $cartella,
            'numeroIpogei'          => $numeroIpogei,
        ];
    }

    /**
     * Rinomina la cartella di un catalogo conservando il contenuto.
     *
     * @throws CatalogoEccezione
     */
    private static function rinominaCartella(string $sigla, string $cartellaAttuale, string $nuovoNome): void
    {
        $nuovaCartella = self::nomeCartella($sigla, $nuovoNome);
        if ($nuovaCartella === $cartellaAttuale) {
            return;
        }

        $da = Percorsi::cataloghi($cartellaAttuale);
        $a  = Percorsi::cataloghi($nuovaCartella);

        if (is_dir($a)) {
            throw new CatalogoEccezione('Esiste già una cartella "' . $nuovaCartella . '" nell\'archivio.');
        }
        if (!@rename($da, $a)) {
            throw new CatalogoEccezione(
                'Il nome del catalogo è stato salvato, ma la cartella non è stata rinominata: '
                . 'verificare che non sia aperta da un altro programma.'
            );
        }
    }

    /** Normalizza una sigla o un prefisso: maiuscole, senza spazi. */
    public static function normalizzaSigla(string $valore): string
    {
        return strtoupper(str_replace(' ', '', trim($valore)));
    }

    /**
     * @throws CatalogoEccezione
     */
    private static function validaSigla(string $sigla): void
    {
        if ($sigla === '') {
            throw new CatalogoEccezione('La sigla del catalogo è obbligatoria.');
        }
        // Nessun limite di lunghezza imposto dal dominio: il vincolo e solo
        // quello dei nomi di file, perche la sigla compare nella cartella.
        if (!preg_match('/^[A-Z0-9.\-_]{1,40}$/', $sigla)) {
            throw new CatalogoEccezione(
                'Sigla non valida: fino a 40 caratteri fra lettere maiuscole, cifre, punto, trattino e underscore.'
            );
        }
    }
}
