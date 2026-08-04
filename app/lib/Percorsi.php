<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Percorsi.php
 *  Descrizione ..: Risoluzione dei percorsi dell'archivio e barriera contro il
 *                  path traversal. Ogni percorso costruito a partire da un
 *                  parametro di richiesta deve passare da dentro().
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

class PercorsoEccezione extends RuntimeException {}

final class Percorsi
{
    /** Nomi delle sottocartelle di servizio dell'archivio. */
    public const INDICE = '_indice';
    public const LOG    = '_log';
    public const TMP    = '_tmp';

    /** Root dell'applicativo (dove sta index.php). */
    public static function app(string $relativo = ''): string
    {
        return self::unisci(CATAGEO_ROOT, $relativo);
    }

    /**
     * Root dell'archivio dati, come indicato in config.xml.
     * Un percorso assoluto permette di collocare i dati fuori dal webroot.
     */
    public static function dati(string $relativo = ''): string
    {
        static $radice = null;

        if ($radice === null) {
            $configurato = Config::caricata() ? Config::testo('percorsi.dati', 'dati') : 'dati';
            $radice = self::eAssoluto($configurato)
                ? rtrim(str_replace('\\', '/', $configurato), '/')
                : self::unisci(CATAGEO_ROOT, $configurato);
        }

        return self::unisci($radice, $relativo);
    }

    /** Cartella dei cataloghi. */
    public static function cataloghi(string $relativo = ''): string
    {
        return self::unisci(self::dati('cataloghi'), $relativo);
    }

    /** Cartella degli indici CSV. */
    public static function indice(string $relativo = ''): string
    {
        return self::unisci(self::dati(self::INDICE), $relativo);
    }

    /** Cartella dei log. */
    public static function log(string $relativo = ''): string
    {
        return self::unisci(self::dati(self::LOG), $relativo);
    }

    /** Cartella dei temporanei di upload. */
    public static function tmp(string $relativo = ''): string
    {
        return self::unisci(self::dati(self::TMP), $relativo);
    }

    /** Cartella degli schemi XSD. */
    public static function schema(string $nomeFile): string
    {
        return self::unisci(CATAGEO_ROOT . '/schemi', $nomeFile);
    }

    /**
     * Verifica che un percorso stia effettivamente sotto una radice.
     *
     * Si usa realpath() su entrambi i termini, cosi che "..", i link simbolici
     * e le junction non permettano di uscire dall'archivio. Se il percorso non
     * esiste ancora (creazione di un file nuovo) si controlla la cartella
     * genitore, che deve esistere.
     */
    public static function dentro(string $radice, string $percorso): bool
    {
        $radiceReale = realpath($radice);
        if ($radiceReale === false) {
            return false;
        }

        $daVerificare = realpath($percorso);
        if ($daVerificare === false) {
            $genitore = realpath(dirname($percorso));
            if ($genitore === false) {
                return false;
            }
            $daVerificare = $genitore . DIRECTORY_SEPARATOR . basename($percorso);
        }

        $radiceReale  = rtrim(str_replace('\\', '/', $radiceReale), '/') . '/';
        $daVerificare = str_replace('\\', '/', $daVerificare);

        // Confronto case-insensitive su Windows, dove i percorsi non lo sono.
        if (DIRECTORY_SEPARATOR === '\\') {
            return stripos($daVerificare . '/', $radiceReale) === 0;
        }
        return str_starts_with($daVerificare . '/', $radiceReale);
    }

    /**
     * Come dentro(), ma solleva un'eccezione invece di restituire false.
     *
     * @throws PercorsoEccezione
     */
    public static function esigiDentro(string $radice, string $percorso): string
    {
        if (!self::dentro($radice, $percorso)) {
            // Il messaggio non riporta il percorso richiesto: in caso di
            // tentativo di traversal non si restituisce all'attaccante
            // informazione sulla struttura del filesystem.
            throw new PercorsoEccezione('Percorso non consentito');
        }
        return $percorso;
    }

    /**
     * Crea una cartella se assente, con i permessi dell'archivio.
     *
     * @throws PercorsoEccezione
     */
    public static function assicuraCartella(string $percorso): string
    {
        if (is_dir($percorso)) {
            return $percorso;
        }
        if (!@mkdir($percorso, 0775, true) && !is_dir($percorso)) {
            throw new PercorsoEccezione("Impossibile creare la cartella: {$percorso}");
        }
        return $percorso;
    }

    /**
     * Scrive nella cartella i file che ne impediscono la lettura via HTTP.
     * Il .htaccess vale su Apache; l'index.html vuoto evita almeno il listing
     * dove .htaccess non e supportato (nginx, hosting con AllowOverride None).
     * La protezione veramente solida resta collocare dati/ fuori dal webroot.
     */
    public static function proteggiCartella(string $percorso): void
    {
        $htaccess = self::unisci($percorso, '.htaccess');
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, self::contenutoHtaccessArchivio());
        }

        $index = self::unisci($percorso, 'index.html');
        if (!is_file($index)) {
            @file_put_contents($index, "<!-- CATAGEO: cartella dati, nessun contenuto pubblico -->\n");
        }
    }

    /** Contenuto del .htaccess posto nelle cartelle dell'archivio. */
    public static function contenutoHtaccessArchivio(): string
    {
        return <<<'HTACCESS'
# ============================================================================
#  CATAGEO - protezione della cartella dati
#  Generato automaticamente: non modificare a mano.
# ============================================================================

# Apache 2.4
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

# Apache 2.2
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>

# Nessuna esecuzione di codice dentro l'archivio, in nessun caso.
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>

RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .py .sh
AddType text/plain .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .py .sh

Options -Indexes -ExecCGI
HTACCESS;
    }

    /** Unisce due segmenti di percorso normalizzando i separatori. */
    public static function unisci(string $base, string $relativo): string
    {
        $base = rtrim(str_replace('\\', '/', $base), '/');
        if ($relativo === '') {
            return $base;
        }
        return $base . '/' . ltrim(str_replace('\\', '/', $relativo), '/');
    }

    /** True se il percorso e assoluto (Unix o Windows). */
    private static function eAssoluto(string $percorso): bool
    {
        return str_starts_with($percorso, '/')
            || str_starts_with($percorso, '\\')
            || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $percorso);
    }
}
