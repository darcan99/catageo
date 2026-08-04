<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Utenti.php
 *  Descrizione ..: Gestione di dati/utenti.xml: elenco, creazione, modifica,
 *                  password, contatori di tentativi falliti e blocco.
 *                  Le password sono conservate solo come hash BCRYPT.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Utenti
{
    /** Livelli ammessi, dal piu al meno potente. */
    public const LIVELLI = ['ADM', 'OPE', 'USR'];

    /** Etichette dei livelli per l'interfaccia. */
    public const ETICHETTE_LIVELLO = [
        'ADM' => 'Amministratore',
        'OPE' => 'Operatore',
        'USR' => 'Utente',
    ];

    /**
     * Costo dell'algoritmo BCRYPT. 12 e un compromesso ragionevole su hosting
     * economico: circa 0,2-0,4 s per verifica, abbastanza lento da rendere
     * inutile un attacco a dizionario sull'hash, abbastanza rapido da non
     * pesare su un login.
     */
    private const COSTO_HASH = 12;

    /** Lunghezza minima della password imposta in creazione e modifica. */
    public const MIN_PASSWORD = 8;

    /** Percorso del file utenti. */
    public static function percorso(): string
    {
        return Percorsi::dati('utenti.xml');
    }

    /** Percorso dello schema di validazione. */
    private static function xsd(): string
    {
        return Percorsi::schema('utenti.xsd');
    }

    /**
     * Elenco completo degli utenti.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(): array
    {
        $percorso = self::percorso();
        if (!is_file($percorso)) {
            return [];
        }

        $doc     = Xml::carica($percorso);
        $risultato = [];

        foreach (Xml::elenco($doc, '/utenti/utente') as $nodo) {
            $risultato[] = self::daNodo($nodo);
        }

        // Ordine stabile: prima per livello, poi per username.
        usort($risultato, static function (array $a, array $b): int {
            $pa = array_search($a['livello'], self::LIVELLI, true);
            $pb = array_search($b['livello'], self::LIVELLI, true);
            if ($pa !== $pb) {
                return (int) $pa <=> (int) $pb;
            }
            return strcasecmp((string) $a['username'], (string) $b['username']);
        });

        return $risultato;
    }

    /**
     * Cerca un utente per username (confronto case-insensitive).
     *
     * @return array<string,mixed>|null
     */
    public static function trovaPerUsername(string $username): ?array
    {
        $cercato = mb_strtolower(trim($username), 'UTF-8');
        foreach (self::elenco() as $utente) {
            if (mb_strtolower((string) $utente['username'], 'UTF-8') === $cercato) {
                return $utente;
            }
        }
        return null;
    }

    /**
     * Cerca un utente per identificativo.
     *
     * @return array<string,mixed>|null
     */
    public static function trovaPerId(string $id): ?array
    {
        foreach (self::elenco() as $utente) {
            if ($utente['id'] === $id) {
                return $utente;
            }
        }
        return null;
    }

    /** Numero di amministratori attivi: serve a non rimanere senza ADM. */
    public static function contaAmministratoriAttivi(): int
    {
        $conta = 0;
        foreach (self::elenco() as $utente) {
            if ($utente['livello'] === 'ADM' && $utente['attivo']) {
                $conta++;
            }
        }
        return $conta;
    }

    /**
     * Crea un utente.
     *
     * @param  array<string,mixed> $dati chiavi: username, nomeCompleto, email,
     *                                   password, livello, esploratoreId, attivo
     * @return string id assegnato
     * @throws UtenteEccezione
     */
    public static function crea(array $dati): string
    {
        $username = trim((string) ($dati['username'] ?? ''));
        $password = (string) ($dati['password'] ?? '');
        $livello  = strtoupper(trim((string) ($dati['livello'] ?? 'USR')));

        self::validaUsername($username);
        self::validaPassword($password);
        self::validaLivello($livello);

        if (self::trovaPerUsername($username) !== null) {
            throw new UtenteEccezione("Esiste gia un utente con username \"{$username}\".");
        }

        $email = trim((string) ($dati['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new UtenteEccezione('Indirizzo email non valido.');
        }

        return Xml::conLock(self::percorso(), static function () use ($dati, $username, $password, $livello, $email): string {
            $doc    = self::documento();
            $radice = $doc->documentElement;
            if ($radice === null) {
                throw new UtenteEccezione('File utenti.xml senza elemento radice.');
            }

            $id    = self::prossimoId($doc);
            $nodo  = Xml::aggiungi($radice, 'utente', null, ['id' => $id]);

            Xml::imposta($nodo, 'username', $username);
            Xml::imposta($nodo, 'nomeCompleto', trim((string) ($dati['nomeCompleto'] ?? '')));
            Xml::imposta($nodo, 'email', $email);
            Xml::imposta($nodo, 'password', self::hash($password));
            Xml::imposta($nodo, 'livello', $livello);
            Xml::imposta($nodo, 'esploratoreId', trim((string) ($dati['esploratoreId'] ?? '')));
            Xml::imposta($nodo, 'attivo', !empty($dati['attivo']) ? '1' : '0');
            Xml::imposta($nodo, 'dataCreazione', date('Y-m-d'));
            Xml::imposta($nodo, 'ultimoAccesso', '');
            Xml::imposta($nodo, 'tentativiFalliti', '0');
            Xml::imposta($nodo, 'bloccatoFino', '');

            Xml::salva($doc, self::percorso(), is_file(self::xsd()) ? self::xsd() : null);

            return $id;
        });
    }

    /**
     * Aggiorna i dati di un utente. La password si cambia solo se valorizzata.
     *
     * @param array<string,mixed> $dati
     * @throws UtenteEccezione
     */
    public static function aggiorna(string $id, array $dati): void
    {
        $livello = isset($dati['livello']) ? strtoupper(trim((string) $dati['livello'])) : null;
        if ($livello !== null) {
            self::validaLivello($livello);
        }

        $password = (string) ($dati['password'] ?? '');
        if ($password !== '') {
            self::validaPassword($password);
        }

        $email = isset($dati['email']) ? trim((string) $dati['email']) : null;
        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new UtenteEccezione('Indirizzo email non valido.');
        }

        $attuale = self::trovaPerId($id);
        if ($attuale === null) {
            throw new UtenteEccezione('Utente non trovato.');
        }

        // Non si consente di lasciare l'installazione senza amministratori
        // attivi: significherebbe perdere l'accesso alla configurazione.
        $perdeAdm = $attuale['livello'] === 'ADM'
            && $attuale['attivo']
            && (($livello !== null && $livello !== 'ADM') || (isset($dati['attivo']) && empty($dati['attivo'])));

        if ($perdeAdm && self::contaAmministratoriAttivi() <= 1) {
            throw new UtenteEccezione('Operazione rifiutata: resterebbe nessun amministratore attivo.');
        }

        Xml::conLock(self::percorso(), static function () use ($id, $dati, $livello, $password, $email): void {
            $doc  = self::documento();
            $nodo = self::nodoPerId($doc, $id);

            foreach (['nomeCompleto', 'esploratoreId'] as $campo) {
                if (isset($dati[$campo])) {
                    Xml::imposta($nodo, $campo, trim((string) $dati[$campo]));
                }
            }
            if ($email !== null) {
                Xml::imposta($nodo, 'email', $email);
            }
            if ($livello !== null) {
                Xml::imposta($nodo, 'livello', $livello);
            }
            if (array_key_exists('attivo', $dati)) {
                Xml::imposta($nodo, 'attivo', !empty($dati['attivo']) ? '1' : '0');
            }
            if ($password !== '') {
                Xml::imposta($nodo, 'password', self::hash($password));
                // Cambiare password sblocca: chi ha dimenticato la vecchia non
                // deve aspettare la scadenza del blocco.
                Xml::imposta($nodo, 'tentativiFalliti', '0');
                Xml::imposta($nodo, 'bloccatoFino', '');
            }

            Xml::salva($doc, self::percorso(), is_file(self::xsd()) ? self::xsd() : null);
        });
    }

    /**
     * Elimina un utente.
     *
     * @throws UtenteEccezione
     */
    public static function elimina(string $id): void
    {
        $utente = self::trovaPerId($id);
        if ($utente === null) {
            throw new UtenteEccezione('Utente non trovato.');
        }
        if ($utente['livello'] === 'ADM' && $utente['attivo'] && self::contaAmministratoriAttivi() <= 1) {
            throw new UtenteEccezione('Operazione rifiutata: e l\'ultimo amministratore attivo.');
        }

        Xml::conLock(self::percorso(), static function () use ($id): void {
            $doc = self::documento();
            Xml::rimuovi(self::nodoPerId($doc, $id));
            Xml::salva($doc, self::percorso(), is_file(self::xsd()) ? self::xsd() : null);
        });
    }

    /**
     * Verifica una password contro l'hash memorizzato.
     *
     * Se l'hash e stato generato con un costo diverso da quello corrente viene
     * rigenerato: le installazioni vecchie si allineano da sole al primo login.
     */
    public static function verificaPassword(array $utente, string $password): bool
    {
        $hash = (string) $utente['password'];
        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::COSTO_HASH])) {
            try {
                Xml::conLock(self::percorso(), static function () use ($utente, $password): void {
                    $doc = self::documento();
                    Xml::imposta(self::nodoPerId($doc, (string) $utente['id']), 'password', self::hash($password));
                    Xml::salva($doc, self::percorso(), is_file(self::xsd()) ? self::xsd() : null);
                });
            } catch (Throwable $e) {
                // Il rehash e un miglioramento, non una condizione del login.
                Log::errore('Rehash password non riuscito: ' . $e->getMessage(), 'avviso');
            }
        }

        return true;
    }

    /** Azzera i contatori e registra l'accesso riuscito. */
    public static function registraAccessoRiuscito(string $id): void
    {
        Xml::conLock(self::percorso(), static function () use ($id): void {
            $doc  = self::documento();
            $nodo = self::nodoPerId($doc, $id);
            Xml::imposta($nodo, 'ultimoAccesso', date('Y-m-d\TH:i:s'));
            Xml::imposta($nodo, 'tentativiFalliti', '0');
            Xml::imposta($nodo, 'bloccatoFino', '');
            Xml::salva($doc, self::percorso(), is_file(self::xsd()) ? self::xsd() : null);
        });
    }

    /**
     * Incrementa i tentativi falliti e, superata la soglia, blocca l'utente
     * per il numero di minuti configurato.
     *
     * @return array{tentativi:int,bloccatoFino:string}
     */
    public static function registraTentativoFallito(string $id): array
    {
        $soglia = max(1, Config::intero('sicurezza.tentativiLogin', 5));
        $minuti = max(1, Config::intero('sicurezza.bloccoMinuti', 15));

        return Xml::conLock(self::percorso(), static function () use ($id, $soglia, $minuti): array {
            $doc  = self::documento();
            $nodo = self::nodoPerId($doc, $id);

            $tentativi = Xml::intero($nodo, 'tentativiFalliti', 0) + 1;
            Xml::imposta($nodo, 'tentativiFalliti', (string) $tentativi);

            $bloccatoFino = '';
            if ($tentativi >= $soglia) {
                $bloccatoFino = date('Y-m-d\TH:i:s', time() + $minuti * 60);
                Xml::imposta($nodo, 'bloccatoFino', $bloccatoFino);
            }

            Xml::salva($doc, self::percorso(), is_file(self::xsd()) ? self::xsd() : null);

            return ['tentativi' => $tentativi, 'bloccatoFino' => $bloccatoFino];
        });
    }

    /** True se l'utente e attualmente bloccato per troppi tentativi. */
    public static function eBloccato(array $utente): bool
    {
        $fino = (string) $utente['bloccatoFino'];
        if ($fino === '') {
            return false;
        }
        $scadenza = strtotime($fino);
        return $scadenza !== false && $scadenza > time();
    }

    /** Genera l'hash di una password. */
    public static function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => self::COSTO_HASH]);
        if (!is_string($hash) || $hash === '') {
            throw new UtenteEccezione('Generazione dell\'hash della password non riuscita.');
        }
        return $hash;
    }

    /** Crea il file utenti.xml vuoto, se assente. */
    public static function assicuraFile(): void
    {
        $percorso = self::percorso();
        if (is_file($percorso)) {
            return;
        }
        Percorsi::assicuraCartella(dirname($percorso));
        Xml::salva(Xml::nuovo('utenti', ['versioneSchema' => '1.0']), $percorso);
    }

    // ------------------------------------------------------------- validazioni

    /** @throws UtenteEccezione */
    public static function validaUsername(string $username): void
    {
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username)) {
            throw new UtenteEccezione(
                'Username non valido: da 3 a 40 caratteri, ammessi lettere, cifre, punto, underscore e trattino.'
            );
        }
    }

    /** @throws UtenteEccezione */
    public static function validaPassword(string $password): void
    {
        if (mb_strlen($password, 'UTF-8') < self::MIN_PASSWORD) {
            throw new UtenteEccezione('La password deve essere lunga almeno ' . self::MIN_PASSWORD . ' caratteri.');
        }
    }

    /** @throws UtenteEccezione */
    public static function validaLivello(string $livello): void
    {
        if (!in_array($livello, self::LIVELLI, true)) {
            throw new UtenteEccezione('Livello non valido: ammessi ADM, OPE, USR.');
        }
    }

    // ------------------------------------------------------------------ interni

    /** Carica utenti.xml, creandolo se assente. */
    private static function documento(): DOMDocument
    {
        self::assicuraFile();
        return Xml::carica(self::percorso());
    }

    /**
     * Nodo dell'utente con l'id indicato.
     *
     * @throws UtenteEccezione
     */
    private static function nodoPerId(DOMDocument $doc, string $id): DOMElement
    {
        foreach (Xml::elenco($doc, '/utenti/utente') as $nodo) {
            if ($nodo->getAttribute('id') === $id) {
                return $nodo;
            }
        }
        throw new UtenteEccezione('Utente non trovato.');
    }

    /** Prossimo identificativo disponibile, nella forma U001. */
    private static function prossimoId(DOMDocument $doc): string
    {
        $massimo = 0;
        foreach (Xml::elenco($doc, '/utenti/utente') as $nodo) {
            $numero = (int) preg_replace('/\D/', '', $nodo->getAttribute('id'));
            $massimo = max($massimo, $numero);
        }
        // Il padding e una soglia minima, non un tetto: oltre U999 il numero
        // continua a crescere (stessa regola dei codici catastali, D7).
        return 'U' . str_pad((string) ($massimo + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Converte un nodo <utente> in array.
     *
     * @return array<string,mixed>
     */
    private static function daNodo(DOMElement $nodo): array
    {
        return [
            'id'               => $nodo->getAttribute('id'),
            'username'         => Xml::testo($nodo, 'username'),
            'nomeCompleto'     => Xml::testo($nodo, 'nomeCompleto'),
            'email'            => Xml::testo($nodo, 'email'),
            'password'         => Xml::testo($nodo, 'password'),
            'livello'          => strtoupper(Xml::testo($nodo, 'livello', 'USR')),
            'esploratoreId'    => Xml::testo($nodo, 'esploratoreId'),
            'attivo'           => Xml::booleano($nodo, 'attivo', false),
            'dataCreazione'    => Xml::testo($nodo, 'dataCreazione'),
            'ultimoAccesso'    => Xml::testo($nodo, 'ultimoAccesso'),
            'tentativiFalliti' => Xml::intero($nodo, 'tentativiFalliti', 0),
            'bloccatoFino'     => Xml::testo($nodo, 'bloccatoFino'),
        ];
    }
}
