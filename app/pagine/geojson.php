<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/geojson.php
 *  Descrizione ..: Restituisce gli ipogei visibili come GeoJSON, per la mappa e
 *                  per l'esportazione.
 *
 *                  Le regole di riservatezza sono applicate QUI, non nel
 *                  browser: un filtro fatto lato client non e un filtro, perche
 *                  i dati sono comunque partiti dal server. Le schede riservate
 *                  non entrano nella risposta e le coordinate offuscate escono
 *                  gia arrotondate.
 *  Versione .....: 0.13.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.13.0 2026-08-06  D.Candela  La forma delle feature passa a Esportazione.
 *  0.6.0  2026-08-05  D.Candela  Prima stesura (fase 4).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');

$cerca          = isset($_GET['cerca']) ? trim((string) $_GET['cerca']) : '';
$filtroCatalogo = isset($_GET['catalogo']) ? Cataloghi::normalizzaSigla((string) $_GET['catalogo']) : '';
$filtroNatura   = isset($_GET['natura']) ? trim((string) $_GET['natura']) : '';
$cercaNorm      = Testo::normalizzaRicerca($cerca);

/** Filtro applicato in streaming sull'indice, riservatezza compresa. */
$filtro = static function (array $riga) use ($cercaNorm, $filtroCatalogo, $filtroNatura): bool {
    if (!Visibilita::schedaVisibile((string) ($riga['riservatezza'] ?? ''), (string) ($riga['stato_scheda'] ?? ''))) {
        return false;
    }
    if ($filtroCatalogo !== '' && strcasecmp((string) ($riga['catalogo'] ?? ''), $filtroCatalogo) !== 0) {
        return false;
    }
    if ($filtroNatura !== '' && (string) ($riga['natura'] ?? '') !== $filtroNatura) {
        return false;
    }
    if ($cercaNorm === '') {
        return true;
    }
    foreach (['codice', 'nome', 'comune', 'localita'] as $campo) {
        if (str_contains(Testo::normalizzaRicerca((string) ($riga[$campo] ?? '')), $cercaNorm)) {
            return true;
        }
    }
    return false;
};

// La forma delle feature vive in Esportazione: la stessa raccolta viene
// prodotta dai risultati di ricerca e dagli export, e tre copie della stessa
// struttura divergerebbero alla prima proprieta aggiunta.
$geojson = Esportazione::geojson(IndiceIpogei::elenco($filtro));

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    // Nessuna cache: la riservatezza dipende da chi chiede, e una risposta
    // memorizzata da un proxy potrebbe finire a un altro utente.
    header('Cache-Control: no-store, private');
}

echo json_encode($geojson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
