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
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

// --------------------------------------------------------------- costanti base

/** Root dell'applicativo: la cartella che contiene index.php. */
define('CATAGEO_ROOT', str_replace('\\', '/', dirname(__DIR__)));

/** Versione dell'applicativo. Unica fonte di verita per l'interfaccia. */
define('CATAGEO_VERSIONE', '0.1.0');

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
 * La Content-Security-Policy NON viene emessa in questa fase: andra definita
 * in fase 4, quando saranno noti gli host effettivi dei tile server e
 * l'eventuale script di Google Maps. Scriverla ora troppo permissiva sarebbe
 * inutile, troppo stretta romperebbe la mappa.
 */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
}

// --------------------------------------------------------------------- sessione

if ($catageoConfigurato) {
    Auth::avviaSessione();
}
