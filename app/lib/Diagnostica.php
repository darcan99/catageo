<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Diagnostica.php
 *  Descrizione ..: Verifiche sull'ambiente di esecuzione. Usata sia da
 *                  installa.php (per bloccare un'installazione impossibile)
 *                  sia dalla pagina Strumenti > Diagnostica.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Diagnostica
{
    /** Esiti possibili di una verifica. */
    public const OK         = 'ok';
    public const ATTENZIONE = 'attenzione';
    public const ERRORE     = 'errore';

    /** Versione minima di PHP richiesta. */
    public const PHP_MINIMA = '7.4.0';

    /** Versione di PHP consigliata. */
    public const PHP_CONSIGLIATA = '8.0.0';

    /** Estensioni indispensabili: senza queste l'applicativo non funziona. */
    public const ESTENSIONI_RICHIESTE = ['dom', 'libxml', 'SimpleXML', 'mbstring', 'json', 'fileinfo', 'session'];

    /** Estensioni opzionali, con la funzione che perdono se assenti. */
    public const ESTENSIONI_OPZIONALI = [
        'gd'   => 'generazione delle miniature delle foto',
        'zip'  => 'backup in formato ZIP e lettura dei file KMZ',
        'curl' => 'compilazione assistita della sezione geologica (GetFeatureInfo)',
    ];

    /**
     * Esegue tutte le verifiche.
     *
     * @param bool $conArchivio false in fase di installazione, quando
     *                          l'archivio non esiste ancora
     * @return array<int,array{gruppo:string,nome:string,esito:string,valore:string,nota:string}>
     */
    public static function verifiche(bool $conArchivio = true): array
    {
        $v = [];

        // ------------------------------------------------------------ PHP
        $v[] = self::voce(
            'PHP',
            'Versione di PHP',
            version_compare(PHP_VERSION, self::PHP_CONSIGLIATA, '>=') ? self::OK
                : (version_compare(PHP_VERSION, self::PHP_MINIMA, '>=') ? self::ATTENZIONE : self::ERRORE),
            PHP_VERSION,
            version_compare(PHP_VERSION, self::PHP_CONSIGLIATA, '>=')
                ? ''
                : 'Consigliata la ' . self::PHP_CONSIGLIATA . ' o superiore; minima richiesta la ' . self::PHP_MINIMA . '.'
        );

        $bit = PHP_INT_SIZE * 8;
        $v[] = self::voce(
            'PHP',
            'Interi della piattaforma',
            $bit >= 64 ? self::OK : self::ATTENZIONE,
            $bit . ' bit (max ' . PHP_INT_MAX . ')',
            $bit >= 64 ? '' : 'Su 32 bit il progressivo dei codici si fermerebbe a 2.147.483.647: ampiamente sufficiente per un catasto, ma la piattaforma a 64 bit e preferibile.'
        );

        $v[] = self::voce(
            'PHP',
            'Interfaccia server',
            self::OK,
            PHP_SAPI,
            ''
        );

        // ------------------------------------------------------ estensioni
        foreach (self::ESTENSIONI_RICHIESTE as $estensione) {
            $presente = extension_loaded($estensione);
            $v[] = self::voce(
                'Estensioni richieste',
                $estensione,
                $presente ? self::OK : self::ERRORE,
                $presente ? 'presente' : 'assente',
                $presente ? '' : 'Estensione indispensabile: abilitarla in php.ini.'
            );
        }

        foreach (self::ESTENSIONI_OPZIONALI as $estensione => $funzione) {
            $presente = extension_loaded($estensione);
            $v[] = self::voce(
                'Estensioni opzionali',
                $estensione,
                $presente ? self::OK : self::ATTENZIONE,
                $presente ? 'presente' : 'assente',
                $presente ? '' : 'Senza questa estensione non è disponibile: ' . $funzione . '. Il resto funziona.'
            );
        }

        // ---------------------------------------------------------- limiti
        $post   = Testo::aByte((string) ini_get('post_max_size'));
        $upload = Testo::aByte((string) ini_get('upload_max_filesize'));

        $v[] = self::voce(
            'Limiti PHP',
            'upload_max_filesize',
            $upload >= 8 * 1024 * 1024 ? self::OK : self::ATTENZIONE,
            (string) ini_get('upload_max_filesize'),
            $upload >= 8 * 1024 * 1024 ? '' : 'Valore basso: i rilievi e i video superano facilmente questa soglia.'
        );

        $v[] = self::voce(
            'Limiti PHP',
            'post_max_size',
            $post >= $upload ? self::OK : self::ATTENZIONE,
            (string) ini_get('post_max_size'),
            $post >= $upload
                ? ''
                : 'Deve essere maggiore o uguale a upload_max_filesize, altrimenti gli upload al limite falliscono senza messaggio.'
        );

        $memoria = Testo::aByte((string) ini_get('memory_limit'));
        $v[] = self::voce(
            'Limiti PHP',
            'memory_limit',
            ($memoria === -1 || $memoria >= 64 * 1024 * 1024) ? self::OK : self::ATTENZIONE,
            (string) ini_get('memory_limit'),
            ($memoria === -1 || $memoria >= 64 * 1024 * 1024) ? '' : 'Consigliati almeno 64M per la generazione delle miniature.'
        );

        $v[] = self::voce(
            'Limiti PHP',
            'max_input_vars',
            (int) ini_get('max_input_vars') >= 1000 ? self::OK : self::ATTENZIONE,
            (string) ini_get('max_input_vars'),
            (int) ini_get('max_input_vars') >= 1000 ? '' : 'Le schede con molte voci di diario possono superare questo limite in salvataggio.'
        );

        $reteInUscita = self::reteInUscitaDisponibile();
        $v[] = self::voce(
            'Limiti PHP',
            'Chiamate HTTP in uscita',
            $reteInUscita ? self::OK : self::ATTENZIONE,
            $reteInUscita ? 'disponibili' : 'non disponibili',
            $reteInUscita
                ? ''
                : 'Senza chiamate in uscita non è disponibile la compilazione assistita della sezione geologica; la mappa e tutto il resto funzionano.'
        );

        // ------------------------------------------------- configurazione
        $configurazione = Percorsi::app('config.xml');
        $v[] = self::voce(
            'Configurazione',
            'config.xml',
            is_file($configurazione) ? self::OK : self::ERRORE,
            is_file($configurazione) ? 'presente' : 'assente',
            is_file($configurazione) ? '' : 'Copiare config.xml.dist in config.xml oppure eseguire installa.php.'
        );

        if (Config::caricata()) {
            $fuso  = Config::testo('sistema.fusoOrario', 'Europe/Rome');
            $valido = in_array($fuso, timezone_identifiers_list(), true);
            $v[] = self::voce(
                'Configurazione',
                'Fuso orario',
                $valido ? self::OK : self::ATTENZIONE,
                $fuso . ' (php.ini: ' . ((string) ini_get('date.timezone') ?: 'non impostato') . ')',
                $valido ? '' : 'Identificativo di fuso orario non riconosciuto: si usa Europe/Rome.'
            );
        }

        // ------------------------------------------------------- archivio
        if ($conArchivio) {
            $cartelle = [
                'Archivio dati'      => Percorsi::dati(),
                'Cataloghi'          => Percorsi::cataloghi(),
                'Indici'             => Percorsi::indice(),
                'Log'                => Percorsi::log(),
                'Temporanei'         => Percorsi::tmp(),
            ];

            foreach ($cartelle as $nome => $percorso) {
                if (!is_dir($percorso)) {
                    $v[] = self::voce('Archivio', $nome, self::ERRORE, 'cartella assente', 'Eseguire installa.php oppure gli strumenti di ripristino.');
                    continue;
                }
                $scrivibile = is_writable($percorso);
                $v[] = self::voce(
                    'Archivio',
                    $nome,
                    $scrivibile ? self::OK : self::ERRORE,
                    $scrivibile ? 'presente e scrivibile' : 'presente ma NON scrivibile',
                    $scrivibile ? '' : 'Assegnare i permessi di scrittura al processo del web server.'
                );
            }

            $utenti = Utenti::percorso();
            $v[] = self::voce(
                'Archivio',
                'utenti.xml',
                is_file($utenti) ? self::OK : self::ERRORE,
                is_file($utenti) ? 'presente' : 'assente',
                is_file($utenti) ? '' : 'Eseguire installa.php per creare il primo amministratore.'
            );

            if (is_file($utenti)) {
                $adm = Utenti::contaAmministratoriAttivi();
                $v[] = self::voce(
                    'Archivio',
                    'Amministratori attivi',
                    $adm >= 1 ? self::OK : self::ERRORE,
                    (string) $adm,
                    $adm >= 1 ? '' : 'Nessun amministratore attivo: la configurazione non è più modificabile dall\'interfaccia.'
                );
            }

            // Protezione dell'archivio via HTTP: si verifica la presenza del
            // .htaccess, non la sua efficacia, che dipende da AllowOverride.
            $htaccess = Percorsi::dati('.htaccess');
            $fuoriWebroot = !Percorsi::dentro(CATAGEO_ROOT, Percorsi::dati());
            $v[] = self::voce(
                'Sicurezza',
                'Protezione dell\'archivio',
                $fuoriWebroot ? self::OK : (is_file($htaccess) ? self::ATTENZIONE : self::ERRORE),
                $fuoriWebroot ? 'archivio fuori dal webroot' : (is_file($htaccess) ? '.htaccess presente' : 'nessuna protezione'),
                $fuoriWebroot
                    ? ''
                    : 'La protezione più solida è spostare l\'archivio fuori dal webroot e indicarne il percorso in percorsi/dati. Il .htaccess non ha effetto se il server ha AllowOverride None o non è Apache.'
            );

            $spazio = @disk_free_space(Percorsi::dati());
            if (is_float($spazio)) {
                $v[] = self::voce(
                    'Archivio',
                    'Spazio disponibile',
                    $spazio > 200 * 1024 * 1024 ? self::OK : self::ATTENZIONE,
                    Testo::dimensione((int) $spazio),
                    $spazio > 200 * 1024 * 1024 ? '' : 'Spazio residuo ridotto: foto, video e rilievi lo consumano rapidamente.'
                );
            }
        }

        // -------------------------------------------------- librerie locali
        $vendor = [
            'Bootstrap (CSS)'      => 'assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css',
            'Bootstrap (JS)'       => 'assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js',
            'Bootstrap Icons'      => 'assets/vendor/bootstrap-icons-1.13.1/bootstrap-icons.min.css',
        ];
        foreach ($vendor as $nome => $relativo) {
            $percorso = Percorsi::app($relativo);
            $v[] = self::voce(
                'Librerie locali',
                $nome,
                is_file($percorso) ? self::OK : self::ERRORE,
                is_file($percorso) ? Testo::dimensione((int) filesize($percorso)) : 'assente',
                is_file($percorso) ? '' : 'Libreria non presente in assets/vendor: l\'interfaccia risultera senza stile.'
            );
        }

        return $v;
    }

    /**
     * Riepilogo dei conteggi per esito.
     *
     * @param  array<int,array<string,string>> $verifiche
     * @return array{ok:int,attenzione:int,errore:int}
     */
    public static function riepilogo(array $verifiche): array
    {
        $conteggi = [self::OK => 0, self::ATTENZIONE => 0, self::ERRORE => 0];
        foreach ($verifiche as $voce) {
            $esito = $voce['esito'] ?? self::OK;
            if (isset($conteggi[$esito])) {
                $conteggi[$esito]++;
            }
        }
        return ['ok' => $conteggi[self::OK], 'attenzione' => $conteggi[self::ATTENZIONE], 'errore' => $conteggi[self::ERRORE]];
    }

    /**
     * True se non ci sono errori bloccanti.
     *
     * @param array<int,array<string,string>> $verifiche
     */
    public static function installabile(array $verifiche): bool
    {
        foreach ($verifiche as $voce) {
            // Le voci sull'archivio non bloccano l'installazione: e l'installer
            // che deve crearlo.
            if (($voce['esito'] ?? '') === self::ERRORE && ($voce['gruppo'] ?? '') !== 'Archivio' && ($voce['nome'] ?? '') !== 'config.xml') {
                return false;
            }
        }
        return true;
    }

    /**
     * True se il server puo effettuare chiamate HTTP verso l'esterno.
     * Si verifica solo la disponibilita del meccanismo, senza contattare
     * nessuno: una diagnostica non deve generare traffico di rete.
     */
    public static function reteInUscitaDisponibile(): bool
    {
        return extension_loaded('curl') || (bool) ini_get('allow_url_fopen');
    }

    /**
     * @return array{gruppo:string,nome:string,esito:string,valore:string,nota:string}
     */
    private static function voce(string $gruppo, string $nome, string $esito, string $valore, string $nota): array
    {
        return ['gruppo' => $gruppo, 'nome' => $nome, 'esito' => $esito, 'valore' => $valore, 'nota' => $nota];
    }
}
