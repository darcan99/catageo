<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/completezza-csv.php
 *  Descrizione ..: Il report di completezza (9.17.6) in CSV.
 *
 *                  La tabella a video si ferma alle prime schede, questo file
 *                  no: chi cura un catasto lavora sull'elenco intero, di solito
 *                  in un foglio di calcolo, e un CSV troncato in silenzio
 *                  gliene farebbe correggere una parte credendo di averle viste
 *                  tutte.
 *
 *                  Con BOM, perche la destinazione tipica e Excel: senza, gli
 *                  accenti dei nomi di cavita arrivano sbagliati.
 *  Versione .....: 1.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.1.0  2026-08-07  D.Candela  Prima stesura (fase 12).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('strumenti');

$catalogo = strtoupper(trim((string) ($_GET['catalogo'] ?? '')));
$report   = Completezza::report($catalogo);

$nome = 'completezza-' . ($catalogo !== '' ? strtolower($catalogo) . '-' : '') . date('Ymd');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nome . '.csv"');
header('Cache-Control: no-store, private');

echo "\xEF\xBB\xBF";
echo Completezza::csv($report);
