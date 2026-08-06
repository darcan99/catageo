<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: index.php
 *  Descrizione ..: Front controller. Unico punto di ingresso dell'applicativo:
 *                  risolve la pagina richiesta da ?p=, verifica autenticazione
 *                  e permessi, esegue la pagina e la incapsula nel layout.
 *
 *                  Le pagine si raggiungono via querystring e non con URL
 *                  riscritti: nessun mod_rewrite richiesto, quindi
 *                  l'applicativo funziona su qualunque hosting.
 *  Versione .....: 1.0.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.0.0  2026-08-07  D.Candela  Rotta della scheda da stampare.
 *  0.16.0 2026-08-06  D.Candela  Rotta dell'importazione massiva.
 *  0.15.0 2026-08-06  D.Candela  Rotte degli strumenti e del backup.
 *  0.14.0 2026-08-06  D.Candela  Rotta della migrazione fra cataloghi.
 *  0.13.0 2026-08-06  D.Candela  Rotte di ricerca ed esportazione.
 *  0.12.0 2026-08-06  D.Candela  Rotte di biospeleologia e archeologia.
 *  0.11.0 2026-08-06  D.Candela  Rotte dei dati scientifici.
 *  0.10.0 2026-08-06  D.Candela  Rotte di bibliografia, opere ed export BibTeX.
 *  0.9.0  2026-08-06  D.Candela  Rotte delle esplorazioni.
 *  0.8.0  2026-08-05  D.Candela  Rotte del rilievo e del tracciato.
 *  0.7.0  2026-08-05  D.Candela  Rotta della gestione risorse.
 *  0.6.0  2026-08-05  D.Candela  Rotte della mappa e del GeoJSON; risposte
 *                                grezze emesse senza layout.
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

require_once __DIR__ . '/app/bootstrap.php';

// ----------------------------------------------------------------- installazione

// Senza configurazione l'unica cosa sensata e mandare all'installer.
if (!Config::caricata()) {
    if (is_file(__DIR__ . '/installa.php')) {
        header('Location: installa.php');
        exit;
    }
    http_response_code(503);
    echo 'CATAGEO non e configurato e installa.php non e presente. '
       . 'Copiare config.xml.dist in config.xml.';
    exit;
}

// ------------------------------------------------------------------- instradamento

/**
 * Tabella delle pagine.
 *
 * Chiave      = valore ammesso di ?p=
 * file        = file in app/pagine
 * permesso    = permesso richiesto, oppure null per le pagine pubbliche
 * titolo      = titolo mostrato nella pagina e nel tab del browser
 * grezza      = true per le risposte che non sono pagine (JSON, KML, download):
 *               vengono emesse cosi come sono, senza layout
 *
 * La whitelist e l'unico modo per raggiungere un file: il parametro ?p= non
 * viene mai usato per costruire un percorso, quindi non esiste superficie per
 * inclusioni arbitrarie.
 */
$pagine = [
    'login'        => ['file' => 'login.php',        'permesso' => null,                 'titolo' => 'Accesso'],
    'esci'         => ['file' => 'esci.php',         'permesso' => null,                 'titolo' => 'Uscita'],
    'home'         => ['file' => 'home.php',         'permesso' => 'consulta',           'titolo' => 'Pagina iniziale'],
    'mappa'        => ['file' => 'mappa.php',        'permesso' => 'consulta',           'titolo' => 'Mappa'],
    'geojson'      => ['file' => 'geojson.php',      'permesso' => 'consulta',           'titolo' => 'GeoJSON', 'grezza' => true],
    'tracciato'    => ['file' => 'tracciato.php',    'permesso' => 'consulta',           'titolo' => 'Tracciato', 'grezza' => true],
    'ipogei'       => ['file' => 'ipogei.php',       'permesso' => 'consulta',           'titolo' => 'Ipogei'],
    'stampa'       => ['file' => 'stampa.php',       'permesso' => 'consulta',           'titolo' => 'Scheda da stampare', 'grezza' => true],
    'risorse'      => ['file' => 'risorse.php',      'permesso' => 'consulta',           'titolo' => 'Risorse'],
    'rilievo'      => ['file' => 'rilievo.php',      'permesso' => 'consulta',           'titolo' => 'Rilievo'],
    'ricerca'      => ['file' => 'ricerca.php',      'permesso' => 'ricerca',            'titolo' => 'Ricerca'],
    'esporta'      => ['file' => 'esporta.php',      'permesso' => 'esporta',            'titolo' => 'Esportazione', 'grezza' => true],
    'migrazione'   => ['file' => 'migrazione.php',   'permesso' => 'migra_catalogo',     'titolo' => 'Migrazione fra cataloghi'],
    'esplorazioni' => ['file' => 'esplorazioni.php', 'permesso' => 'consulta',           'titolo' => 'Esplorazioni'],
    'esplorazione' => ['file' => 'esplorazione.php', 'permesso' => 'consulta',           'titolo' => 'Diario'],
    'bibliografia' => ['file' => 'bibliografia.php', 'permesso' => 'consulta',           'titolo' => 'Bibliografia'],
    'bibtex'       => ['file' => 'bibtex.php',       'permesso' => 'esporta',            'titolo' => 'BibTeX', 'grezza' => true],
    'scientifici'  => ['file' => 'scientifici.php',  'permesso' => 'consulta',           'titolo' => 'Dati scientifici'],
    'serie-csv'    => ['file' => 'serie-csv.php',    'permesso' => 'esporta',            'titolo' => 'Serie CSV', 'grezza' => true],
    'biospeleologia' => ['file' => 'biospeleologia.php', 'permesso' => 'consulta',        'titolo' => 'Biospeleologia'],
    'archeologia'  => ['file' => 'archeologia.php',  'permesso' => 'consulta',           'titolo' => 'Archeologia'],
    'anagrafiche'  => ['file' => 'anagrafiche.php',  'permesso' => 'anagrafiche',        'titolo' => 'Anagrafiche'],
    'gruppi'       => ['file' => 'gruppi.php',       'permesso' => 'anagrafiche',        'titolo' => 'Gruppi speleologici'],
    'esploratori'  => ['file' => 'esploratori.php',  'permesso' => 'anagrafiche',        'titolo' => 'Esploratori'],
    'opere'        => ['file' => 'opere.php',        'permesso' => 'anagrafiche',        'titolo' => 'Catalogo delle opere'],
    'vocabolari'   => ['file' => 'vocabolari.php',   'permesso' => 'anagrafiche',        'titolo' => 'Vocabolari'],
    'cataloghi'    => ['file' => 'cataloghi.php',    'permesso' => 'gestisci_cataloghi', 'titolo' => 'Cataloghi'],
    'strumenti'    => ['file' => 'strumenti.php',    'permesso' => 'strumenti',          'titolo' => 'Strumenti'],
    'scarica-backup' => ['file' => 'scarica-backup.php', 'permesso' => 'strumenti',        'titolo' => 'Backup', 'grezza' => true],
    'importa'      => ['file' => 'importa.php',      'permesso' => 'strumenti',          'titolo' => 'Importazione da CSV'],
    'utenti'       => ['file' => 'utenti.php',       'permesso' => 'gestisci_utenti',    'titolo' => 'Gestione utenti'],
    'diagnostica'  => ['file' => 'diagnostica.php',  'permesso' => 'strumenti',          'titolo' => 'Diagnostica'],
];

/** Fase di sviluppo dichiarata nelle pagine non ancora realizzate. */
$fasiPreviste = [
    'ipogei'       => 'Fase 3 — scheda ipogeo, censimento, indice',
    'anagrafiche'  => 'Fase 2 — gruppi, esploratori, tipologie, grandezze',
    'cataloghi'    => 'Fase 2b — cataloghi e serie di codifica',
];

$richiesta = isset($_GET['p']) ? (string) $_GET['p'] : 'home';
if (!isset($pagine[$richiesta])) {
    $richiesta = 'home';
}
$pagina = $pagine[$richiesta];

// --------------------------------------------------------------- controllo accessi

// D2: login sempre obbligatorio. L'accesso anonimo e predisposto in
// configurazione ma resta disattivato in versione 1.
$anonimoAmmesso = Config::booleano('sicurezza.accessoAnonimo', false);

if (!Auth::autenticato() && !$anonimoAmmesso && $pagina['permesso'] !== null) {
    // Si memorizza la pagina richiesta per tornarci dopo l'accesso.
    $_SESSION['catageo_destinazione'] = $richiesta;
    header('Location: index.php?p=login');
    exit;
}

if ($pagina['permesso'] !== null && Auth::autenticato() && !Auth::puo($pagina['permesso'])) {
    http_response_code(403);
    $richiesta = 'negato';
    $pagina    = ['file' => 'negato.php', 'permesso' => null, 'titolo' => 'Accesso negato'];
}

// Un utente gia autenticato non ha motivo di vedere la pagina di accesso.
if ($richiesta === 'login' && Auth::autenticato()) {
    header('Location: index.php?p=home');
    exit;
}

// ---------------------------------------------------------------- esecuzione

$titolo       = $pagina['titolo'];
$paginaAttiva = $richiesta;
$contenuto    = '';

// Le risposte grezze non passano dal layout: emettono il proprio contenuto e
// terminano. Gli errori li restituiscono nel loro stesso formato, altrimenti un
// client che si aspetta JSON riceverebbe una pagina HTML e non capirebbe nulla.
if (!empty($pagina['grezza'])) {
    try {
        require __DIR__ . '/app/pagine/' . $pagina['file'];
    } catch (Throwable $e) {
        Log::errore($e->getMessage(), 'errore', $e->getFile(), $e->getLine());
        if (!headers_sent()) {
            http_response_code($e instanceof AuthEccezione ? 403 : 500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(['errore' => Config::booleano('sistema.debug', false)
            ? $e->getMessage()
            : 'Richiesta non completata. L\'errore e stato registrato nel log.']);
    }
    exit;
}

try {
    ob_start();
    require __DIR__ . '/app/pagine/' . $pagina['file'];
    $contenuto = (string) ob_get_clean();
} catch (AuthEccezione $e) {
    ob_end_clean();
    http_response_code(403);
    $titolo    = 'Operazione non consentita';
    $contenuto = vistaErrore('Operazione non consentita', $e->getMessage(), 'bi-shield-exclamation');
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);

    Log::errore($e->getMessage(), 'errore', $e->getFile(), $e->getLine());

    $dettaglio = Config::booleano('sistema.debug', false)
        ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : 'L\'errore e stato registrato nel log dell\'archivio. Se il problema persiste, '
          . 'attivare il debug in configurazione per vedere il dettaglio.';

    $titolo    = 'Errore';
    $contenuto = vistaErrore('Si e verificato un errore', $dettaglio, 'bi-exclamation-octagon');
}

require __DIR__ . '/app/view/layout.php';

/**
 * Costruisce il riquadro di errore mostrato all'utente.
 *
 * Definita qui e non in una classe perche serve solo al front controller e
 * deve funzionare anche se l'errore e avvenuto caricando le librerie.
 */
function vistaErrore(string $titolo, string $messaggio, string $icona): string
{
    return '<div class="row justify-content-center"><div class="col-lg-7">'
        . '<div class="card border-danger-subtle">'
        . '<div class="card-body d-flex gap-3">'
        . '<i class="bi ' . Testo::esc($icona) . ' fs-2 text-danger" aria-hidden="true"></i>'
        . '<div><h1 class="h5 mb-2">' . Testo::esc($titolo) . '</h1>'
        . '<p class="mb-3 text-body-secondary">' . Testo::esc($messaggio) . '</p>'
        . '<a class="btn btn-sm btn-outline-secondary" href="index.php">'
        . '<i class="bi bi-house"></i> Torna alla pagina iniziale</a>'
        . '</div></div></div></div></div>';
}
