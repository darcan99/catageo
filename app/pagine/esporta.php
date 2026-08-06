<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/esporta.php
 *  Descrizione ..: Esportazione dei risultati di una ricerca in CSV, GeoJSON
 *                  o KML (10).
 *
 *                  Prende gli stessi criteri della pagina di ricerca e li
 *                  riesegue: un indirizzo di export e cosi lo stesso indirizzo
 *                  della ricerca con un formato in piu, condivisibile e
 *                  ripetibile. Non si conserva nulla in sessione, che
 *                  scadrebbe proprio mentre si prepara il file.
 *
 *                  Serve anche alla vista mappa dei risultati, che si alimenta
 *                  dal formato GeoJSON di questa stessa pagina.
 *  Versione .....: 0.13.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.13.0  2026-08-06  D.Candela  Prima stesura (fase 8).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('esporta');

$formato = strtolower((string) ($_GET['formato'] ?? 'csv'));
if (!in_array($formato, ['csv', 'geojson', 'kml'], true)) {
    $formato = 'csv';
}

/** Gli stessi criteri della pagina di ricerca, letti allo stesso modo. */
$criteri = [];
foreach (Ricerca::CRITERI as $chiave => $riposo) {
    if (!isset($_GET[$chiave])) {
        $criteri[$chiave] = $riposo;
        continue;
    }
    $criteri[$chiave] = is_array($riposo) ? (array) $_GET[$chiave] : (string) $_GET[$chiave];
}

/*
 * Senza criteri si esporta tutto l'archivio visibile. Non e un errore: e la
 * richiesta legittima di chi vuole una copia dell'indice. Il tetto di
 * Ricerca::LIMITE vale comunque, e viene dichiarato nel file.
 */
$esito = Ricerca::esegui($criteri);
$righe = $esito['righe'];

$nomeBase = 'catageo-' . date('Ymd-His');

if ($formato === 'geojson') {
    $contenuto = json_encode(
        Esportazione::geojson($righe),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        // La vista mappa consuma questa stessa risposta: mostrarla inline
        // invece che come allegato, altrimenti il browser la scaricherebbe.
        header('Cache-Control: no-store, private');
        if (empty($_GET['inline'])) {
            header('Content-Disposition: attachment; filename="' . $nomeBase . '.geojson"');
        }
    }

    echo (string) $contenuto;

    return;
}

if ($formato === 'kml') {
    $contenuto = Esportazione::kml($righe, Config::testo('catasto.nome', 'CATAGEO'));

    if (!headers_sent()) {
        header('Content-Type: application/vnd.google-earth.kml+xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nomeBase . '.kml"');
        header('Cache-Control: no-store, private');
    }

    echo $contenuto;

    return;
}

// --- CSV ---------------------------------------------------------------------

if (!headers_sent()) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeBase . '.csv"');
    header('Cache-Control: no-store, private');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$uscita = fopen('php://output', 'w');
if ($uscita === false) {
    return;
}

// BOM: senza, Excel apre un CSV UTF-8 interpretandolo come ANSI e i nomi con
// accento arrivano illeggibili. E il formato in cui questo file verra aperto
// nella grande maggioranza dei casi.
fwrite($uscita, "\xEF\xBB\xBF");

fputcsv($uscita, Esportazione::COLONNE_CSV, Csv::SEPARATORE, Csv::DELIMITATORE);

foreach (Esportazione::csv($righe) as $riga) {
    fputcsv($uscita, array_values($riga), Csv::SEPARATORE, Csv::DELIMITATORE);
}

if ($esito['troncato']) {
    // Il taglio si dichiara dentro il file, non solo nella pagina: chi apre il
    // CSV domani non ha piu davanti l'avviso dell'interfaccia.
    fputcsv($uscita, ['# ATTENZIONE: esportati i primi ' . Ricerca::LIMITE
        . ' risultati su ' . $esito['totale'] . '. Restringi i criteri per averli tutti.'],
        Csv::SEPARATORE, Csv::DELIMITATORE);
}

fclose($uscita);
