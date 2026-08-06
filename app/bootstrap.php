<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/bootstrap.php
 *  Descrizione ..: Inizializzazione dell'applicativo: costanti, autoload delle
 *                  classi, gestione degli errori, caricamento della
 *                  configurazione, fuso orario e avvio della sessione.
 *                  Va incluso da index.php e da installa.php.
 *  Versione .....: 0.13.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.13.0 2026-08-06  D.Candela  Versione 0.13.0 (fase 8).
 *  0.12.0 2026-08-06  D.Candela  Versione 0.12.0 (fase 7d).
 *  0.11.0 2026-08-06  D.Candela  Versione 0.11.0 (fase 7c).
 *  0.10.0 2026-08-06  D.Candela  Versione 0.10.0 (fase 7b).
 *  0.9.0  2026-08-06  D.Candela  Versione 0.9.0 (fase 7).
 *  0.8.1  2026-08-05  D.Candela  Versione 0.8.1.
 *  0.8.0  2026-08-05  D.Candela  Versione 0.8.0 (fase 6).
 *  0.7.1  2026-08-05  D.Candela  Versione 0.7.1.
 *  0.7.0  2026-08-05  D.Candela  Versione 0.7.0 (fase 5).
 *  0.6.4  2026-08-05  D.Candela  CATAGEO_VERSIONE segue i rilasci 0.6.1-0.6.4.
 *  0.6.0  2026-08-05  D.Candela  Content-Security-Policy con le origini dei
 *                                tile server ricavate dai layer configurati.
 *                                CATAGEO_VERSIONE allineata al rilascio.
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

// --------------------------------------------------------------- costanti base

/** Root dell'applicativo: la cartella che contiene index.php. */
define('CATAGEO_ROOT', str_replace('\\', '/', dirname(__DIR__)));

/** Versione dell'applicativo. Unica fonte di verita per l'interfaccia. */
define('CATAGEO_VERSIONE', '0.13.0');

/** Percorso del file di configurazione. */
define('CATAGEO_CONFIG', CATAGEO_ROOT . '/config.xml');

/** Marcatore di installazione completata. */
define('CATAGEO_INSTALLATO', CATAGEO_ROOT . '/installato.txt');

// ------------------------------------------------------------------- autoload

/**
 * Autoload minimale: una classe per file dentro app/lib.
 * Non serve Composer, coerentemente col vincolo di zero dipendenze.
 */
spl_autoload_register(static function (string $classe): void {
    // Nessun namespace in uso: si rifiuta qualunque nome non atteso, cosi che
    // il nome della classe non possa mai diventare un percorso arbitrario.
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $classe)) {
        return;
    }
    $file = CATAGEO_ROOT . '/app/lib/' . $classe . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// ------------------------------------------------------- gestione degli errori

/**
 * In produzione gli errori non vengono mostrati: finiscono nel log
 * dell'archivio. Il debug si attiva da config.xml, non modificando il codice.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/** Converte gli errori PHP in eccezioni, per gestirli in un unico punto. */
set_error_handler(static function (int $severita, string $messaggio, string $file = '', int $riga = 0): bool {
    if (!(error_reporting() & $severita)) {
        return false;
    }
    throw new ErrorException($messaggio, 0, $severita, $file, $riga);
});

// --------------------------------------------------------------- configurazione

$catageoConfigurato = false;

if (is_file(CATAGEO_CONFIG)) {
    try {
        Config::carica(CATAGEO_CONFIG);
        $catageoConfigurato = true;
    } catch (Throwable $e) {
        // Una configurazione illeggibile e un guasto grave: si mostra un
        // messaggio esplicito invece di una pagina bianca.
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><meta charset="utf-8"><title>CATAGEO — errore di configurazione</title>';
        echo '<div style="font-family:system-ui,sans-serif;max-width:44rem;margin:3rem auto;line-height:1.6">';
        echo '<h1 style="font-size:1.4rem">Errore di configurazione</h1>';
        echo '<p>Il file <code>config.xml</code> esiste ma non e leggibile come XML valido.</p>';
        echo '<p style="background:#fff3cd;border:1px solid #ffe69c;padding:.75rem;border-radius:.375rem">'
           . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p>Correggere il file oppure ripartire da <code>config.xml.dist</code>.</p></div>';
        exit;
    }
}

// Debug: attivabile solo da configurazione.
if ($catageoConfigurato && Config::booleano('sistema.debug', false)) {
    ini_set('display_errors', '1');
}

// Fuso orario: quello di config.xml prevale su php.ini, che su hosting
// condiviso e spesso impostato su un altro paese.
$catageoFuso = $catageoConfigurato ? Config::testo('sistema.fusoOrario', 'Europe/Rome') : 'Europe/Rome';
if (!in_array($catageoFuso, timezone_identifiers_list(), true)) {
    $catageoFuso = 'Europe/Rome';
}
date_default_timezone_set($catageoFuso);

// ----------------------------------------------------------------- intestazioni

/**
 * Intestazioni di sicurezza applicate a ogni risposta.
 *
 * La Content-Security-Policy elenca esplicitamente le origini dei tile server
 * ricavandole dai layer configurati: e l'unico punto in cui l'applicativo si
 * collega a host esterni, e resta visibile a chi amministra il sistema.
 *
 * script-src resta 'self' senza 'unsafe-inline': i dati che le pagine passano al
 * JavaScript viaggiano in blocchi <script type="application/json">, che non sono
 * codice eseguibile. style-src ammette 'unsafe-inline' perche Leaflet posiziona
 * i tile scrivendo l'attributo style degli elementi, e non c'e modo di evitarlo
 * senza rinunciare alla mappa.
 */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');

    $catageoOrigini = '';
    if ($catageoConfigurato) {
        try {
            $catageoElenco = Mappa::originiEsterne();
            $catageoOrigini = $catageoElenco === [] ? '' : ' ' . implode(' ', $catageoElenco);
        } catch (Throwable $e) {
            // Una configurazione cartografica incompleta non deve impedire
            // l'accesso: si resta sulla policy piu restrittiva. Il guasto va
            // pero registrato, perche il sintomo visibile sarebbe soltanto una
            // mappa senza sfondo, senza alcuna spiegazione.
            $catageoOrigini = '';
            try {
                Log::errore('Origini cartografiche non determinate: ' . $e->getMessage(),
                    'errore', $e->getFile(), $e->getLine());
            } catch (Throwable $ignorato) {
                // Archivio non ancora pronto: non c'e dove annotare.
            }
        }
    }

    header(
        "Content-Security-Policy: default-src 'self'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self'; "
        . "object-src 'none'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "font-src 'self'; "
        . "img-src 'self' data: blob:" . $catageoOrigini . '; '
        . "connect-src 'self'" . $catageoOrigini
    );
}

// --------------------------------------------------------------------- sessione

if ($catageoConfigurato) {
    Auth::avviaSessione();
}
