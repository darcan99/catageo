<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Auth.php
 *  Descrizione ..: Autenticazione, sessione, protezione CSRF e verifica dei
 *                  permessi per livello (ADM / OPE / USR).
 *                  D2: in versione 1 il login e sempre obbligatorio.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

class AuthEccezione extends RuntimeException {}

final class Auth
{
    /** Nome del cookie di sessione: distinto da PHPSESSID per non collidere
     *  con altre applicazioni ospitate sullo stesso dominio. */
    private const NOME_SESSIONE = 'CATAGEOSESSID';

    /** Chiavi usate in $_SESSION. */
    private const K_UTENTE   = 'catageo_utente';
    private const K_ULTIMA   = 'catageo_ultima_attivita';
    private const K_TOKEN    = 'catageo_csrf';
    private const K_MESSAGGI = 'catageo_messaggi';

    /**
     * Matrice dei permessi: permesso => livello minimo richiesto.
     * Tenerla in un unico punto evita che i controlli si sparpaglino nelle
     * pagine e divergano fra loro.
     */
    private const PERMESSI = [
        'consulta'              => 'USR',
        'ricerca'               => 'USR',
        'esporta'               => 'USR',
        'vedi_bozze'            => 'OPE',
        'vedi_riservati'        => 'OPE',
        'modifica_scheda'       => 'OPE',
        'carica_risorse'        => 'OPE',
        'redigi_esplorazioni'   => 'OPE',
        'compila_sezioni'       => 'OPE',
        'inserisci_misure'      => 'OPE',
        'importa_serie'         => 'OPE',
        'anagrafiche'           => 'OPE',
        'elimina_ipogeo'        => 'ADM',
        'modifica_codice'       => 'ADM',
        'migra_catalogo'        => 'ADM',
        'gestisci_cataloghi'    => 'ADM',
        'gestisci_utenti'       => 'ADM',
        'configura'             => 'ADM',
        'strumenti'             => 'ADM',
    ];

    /**
     * Avvia la sessione con parametri sicuri.
     * Va chiamata da bootstrap.php prima di qualunque output.
     */
    public static function avviaSessione(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $durataMinuti = max(5, Config::caricata() ? Config::intero('sicurezza.durataSessioneMinuti', 120) : 120);

        session_name(self::NOME_SESSIONE);
        session_set_cookie_params([
            'lifetime' => 0,               // cookie di sessione, non persistente
            'path'     => self::percorsoBase(),
            'domain'   => '',
            'secure'   => self::inHttps(), // su HTTPS il cookie non esce in chiaro
            'httponly' => true,            // non leggibile da JavaScript
            'samesite' => 'Strict',        // nessun invio da siti terzi
        ]);

        session_start();

        // Scadenza per inattivita: piu prudente della sola durata del cookie,
        // che il browser puo tenere aperto per giorni.
        $ultima = (int) ($_SESSION[self::K_ULTIMA] ?? 0);
        if ($ultima > 0 && (time() - $ultima) > $durataMinuti * 60) {
            self::chiudi();
            session_start();
            self::messaggio('avviso', 'Sessione scaduta per inattivita: e necessario accedere di nuovo.');
        }
        $_SESSION[self::K_ULTIMA] = time();
    }

    /**
     * Tenta l'accesso.
     *
     * Il messaggio d'errore restituito all'utente non distingue fra username
     * inesistente e password errata: dirlo permetterebbe di scoprire quali
     * utenze esistono. La distinzione resta nel log, dove serve.
     *
     * @return array{ok:bool,messaggio:string}
     */
    public static function login(string $username, string $password): array
    {
        $username = trim($username);
        $generico = 'Credenziali non valide.';

        if ($username === '' || $password === '') {
            return ['ok' => false, 'messaggio' => 'Inserire username e password.'];
        }

        $utente = Utenti::trovaPerUsername($username);

        if ($utente === null) {
            Log::accesso($username, 'utente_inesistente');
            // Ritardo anche sull'utente inesistente: senza di esso i tempi di
            // risposta rivelerebbero quali username sono validi.
            usleep(random_int(200_000, 400_000));
            return ['ok' => false, 'messaggio' => $generico];
        }

        if (!$utente['attivo']) {
            Log::accesso($username, 'disattivato');
            return ['ok' => false, 'messaggio' => 'Utenza disattivata. Rivolgersi a un amministratore.'];
        }

        if (Utenti::eBloccato($utente)) {
            Log::accesso($username, 'bloccato', 'fino a ' . $utente['bloccatoFino']);
            $fino = strtotime((string) $utente['bloccatoFino']);
            $minuti = $fino !== false ? max(1, (int) ceil(($fino - time()) / 60)) : 1;
            return [
                'ok'         => false,
                'messaggio'  => "Utenza temporaneamente bloccata per troppi tentativi. Riprovare fra {$minuti} minuti.",
            ];
        }

        if (!Utenti::verificaPassword($utente, $password)) {
            $esito = Utenti::registraTentativoFallito((string) $utente['id']);
            Log::accesso($username, 'password_errata', 'tentativo ' . $esito['tentativi']);

            if ($esito['bloccatoFino'] !== '') {
                $minuti = max(1, Config::intero('sicurezza.bloccoMinuti', 15));
                return [
                    'ok'        => false,
                    'messaggio' => "Troppi tentativi falliti: utenza bloccata per {$minuti} minuti.",
                ];
            }

            $rimasti = max(0, Config::intero('sicurezza.tentativiLogin', 5) - $esito['tentativi']);
            return [
                'ok'        => false,
                'messaggio' => $generico . ($rimasti > 0 ? " Tentativi rimasti: {$rimasti}." : ''),
            ];
        }

        // Accesso riuscito: nuovo id di sessione per impedire il fissaggio
        // di una sessione creata prima dell'autenticazione.
        session_regenerate_id(true);

        $_SESSION[self::K_UTENTE] = [
            'id'           => $utente['id'],
            'username'     => $utente['username'],
            'nomeCompleto' => $utente['nomeCompleto'],
            'livello'      => $utente['livello'],
            'esploratoreId' => $utente['esploratoreId'],
        ];
        $_SESSION[self::K_ULTIMA] = time();
        unset($_SESSION[self::K_TOKEN]); // token nuovo per la sessione nuova

        Utenti::registraAccessoRiuscito((string) $utente['id']);
        Log::accesso($username, 'ok');

        return ['ok' => true, 'messaggio' => ''];
    }

    /**
     * Chiude la sessione dell'utente corrente e ne apre una nuova, vuota.
     *
     * La riapertura con id rigenerato non e un dettaglio: chiudendo la sessione
     * si invia al client l'header che cancella il cookie, e una semplice
     * session_start() successiva riuserebbe lo stesso id senza emettere un
     * Set-Cookie nuovo. Il client resterebbe quindi senza cookie e perderebbe
     * tutto cio che viene scritto in sessione dopo l'uscita, a partire dal
     * messaggio di conferma.
     */
    public static function logout(): void
    {
        $username = self::usernameCorrente();
        if ($username !== '') {
            Log::accesso($username, 'uscita');
        }

        self::chiudi();
        self::avviaSessione();
        session_regenerate_id(true);
    }

    /** True se c'e un utente autenticato. */
    public static function autenticato(): bool
    {
        return isset($_SESSION[self::K_UTENTE]) && is_array($_SESSION[self::K_UTENTE]);
    }

    /**
     * Utente corrente.
     *
     * @return array<string,mixed>|null
     */
    public static function utente(): ?array
    {
        return self::autenticato() ? $_SESSION[self::K_UTENTE] : null;
    }

    /** Username corrente, stringa vuota se non autenticato. */
    public static function usernameCorrente(): string
    {
        return (string) (self::utente()['username'] ?? '');
    }

    /** Livello corrente; USR come default prudente. */
    public static function livello(): string
    {
        return (string) (self::utente()['livello'] ?? 'USR');
    }

    /** True se il livello corrente e almeno quello indicato. */
    public static function almeno(string $livelloMinimo): bool
    {
        if (!self::autenticato()) {
            return false;
        }
        $posCorrente = array_search(self::livello(), Utenti::LIVELLI, true);
        $posMinimo   = array_search(strtoupper($livelloMinimo), Utenti::LIVELLI, true);

        if ($posCorrente === false || $posMinimo === false) {
            return false;
        }
        // L'array e ordinato dal piu potente: indice minore = piu permessi.
        return $posCorrente <= $posMinimo;
    }

    /** True se l'utente corrente ha il permesso indicato. */
    public static function puo(string $permesso): bool
    {
        $richiesto = self::PERMESSI[$permesso] ?? null;
        if ($richiesto === null) {
            // Un permesso non censito viene negato: e piu sicuro che
            // concederlo per distrazione.
            Log::errore("Permesso non censito richiesto: {$permesso}", 'avviso');
            return false;
        }
        return self::almeno($richiesto);
    }

    /**
     * Interrompe l'esecuzione se il permesso manca.
     *
     * @throws AuthEccezione
     */
    public static function esigi(string $permesso): void
    {
        if (!self::puo($permesso)) {
            throw new AuthEccezione('Permesso negato per questa operazione.');
        }
    }

    // -------------------------------------------------------------------- CSRF

    /** Token CSRF della sessione, generato al primo utilizzo. */
    public static function token(): string
    {
        if (empty($_SESSION[self::K_TOKEN]) || !is_string($_SESSION[self::K_TOKEN])) {
            $_SESSION[self::K_TOKEN] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::K_TOKEN];
    }

    /** Campo hidden da inserire in ogni form POST. */
    public static function campoToken(): string
    {
        return '<input type="hidden" name="_token" value="' . Testo::esc(self::token()) . '">';
    }

    /** Verifica il token ricevuto, con confronto a tempo costante. */
    public static function verificaToken(?string $ricevuto): bool
    {
        $atteso = (string) ($_SESSION[self::K_TOKEN] ?? '');
        if ($atteso === '' || $ricevuto === null || $ricevuto === '') {
            return false;
        }
        return hash_equals($atteso, $ricevuto);
    }

    /**
     * Verifica il token della richiesta POST corrente.
     *
     * @throws AuthEccezione
     */
    public static function esigiToken(): void
    {
        if (!self::verificaToken(isset($_POST['_token']) ? (string) $_POST['_token'] : null)) {
            Log::errore('Token CSRF non valido su ' . ($_SERVER['REQUEST_URI'] ?? ''), 'avviso');
            throw new AuthEccezione('Richiesta non valida o scaduta: ricaricare la pagina e riprovare.');
        }
    }

    // -------------------------------------------------------- messaggi in flash

    /**
     * Accoda un messaggio da mostrare alla prossima pagina.
     *
     * @param string $tipo "successo", "avviso", "errore", "info"
     */
    public static function messaggio(string $tipo, string $testo): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION[self::K_MESSAGGI][] = ['tipo' => $tipo, 'testo' => $testo];
    }

    /**
     * Restituisce e svuota i messaggi accodati.
     *
     * @return array<int,array{tipo:string,testo:string}>
     */
    public static function messaggi(): array
    {
        $messaggi = $_SESSION[self::K_MESSAGGI] ?? [];
        unset($_SESSION[self::K_MESSAGGI]);
        return is_array($messaggi) ? $messaggi : [];
    }

    // ----------------------------------------------------------------- interni

    /** Distrugge la sessione e il relativo cookie. */
    private static function chiudi(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametri = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $parametri['path'],
                'domain'   => $parametri['domain'],
                'secure'   => (bool) $parametri['secure'],
                'httponly' => (bool) $parametri['httponly'],
                'samesite' => 'Strict',
            ]);
        }

        session_destroy();
    }

    /** True se la richiesta arriva su HTTPS (anche dietro proxy TLS). */
    private static function inHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    /**
     * Percorso base dell'applicativo, per limitare il cookie alla sola
     * sottocartella in cui CATAGEO e installato.
     *
     * Attenzione: su Windows dirname() restituisce il separatore di sistema,
     * quindi dirname('/index.php') vale '\'. Senza normalizzare il RISULTATO
     * (e non solo l'ingresso) il percorso del cookie diventerebbe '\/', che i
     * client scartano: la sessione non persisterebbe fra le richieste e ogni
     * POST fallirebbe la verifica CSRF.
     */
    private static function percorsoBase(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base   = rtrim(str_replace('\\', '/', dirname($script)), '/');

        return $base === '' ? '/' : $base . '/';
    }
}
