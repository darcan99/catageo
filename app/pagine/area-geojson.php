<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/area-geojson.php
 *  Descrizione ..: Perimetro di un'area speleologica in GeoJSON (9.17.5).
 *
 *                  Serve a due cose: la sovrapposizione in mappa, che lo chiede
 *                  al volo, e lo scarico per chi vuole riportarlo in QGIS.
 *
 *                  Senza "id" restituisce **tutti** i perimetri in una sola
 *                  FeatureCollection: la mappa generale ne disegna molti insieme,
 *                  e una richiesta per area farebbe trenta chiamate dove ne basta
 *                  una.
 *
 *                  Il perimetro di un'area non e un dato riservato: e cartografia
 *                  di inquadramento, non la posizione di una cavita. Chi puo
 *                  consultare puo vederlo.
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

Auth::esigi('consulta');

header('Content-Type: application/geo+json; charset=UTF-8');
header('Cache-Control: no-store, private');

$id = strtoupper(trim((string) ($_GET['id'] ?? '')));

/**
 * Aggiunge alle proprieta di ogni feature il nome dell'area, cosi il popup in
 * mappa puo dire di chi e il perimetro senza una seconda richiesta.
 *
 * @param  array<string,mixed> $geojson
 * @param  array<string,mixed> $area
 * @return array<int,array<string,mixed>>
 */
function catageoFeatureArea(array $geojson, array $area): array
{
    $features = [];
    foreach ((array) ($geojson['features'] ?? []) as $feature) {
        if (!is_array($feature)) {
            continue;
        }
        $proprieta = (array) ($feature['properties'] ?? []);
        $proprieta['areaId']   = (string) $area['id'];
        $proprieta['areaNome'] = (string) $area['nome'];
        $feature['properties'] = $proprieta;
        $features[] = $feature;
    }

    return $features;
}

if ($id !== '') {
    $area = Aree::trova($id);
    if ($area === null) {
        http_response_code(404);
        echo json_encode(['type' => 'FeatureCollection', 'features' => [],
            'errore' => 'Area non trovata.']);
        exit;
    }

    // Con "scarica" il file esce come allegato, per riportarlo in QGIS.
    if (!empty($_GET['scarica'])) {
        header('Content-Disposition: attachment; filename="' . $id . '.geojson"');
    }

    $perimetro = PerimetroArea::leggi($id);
    echo json_encode(
        $perimetro === null
            ? ['type' => 'FeatureCollection', 'features' => []]
            : ['type' => 'FeatureCollection', 'features' => catageoFeatureArea($perimetro, $area)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// --- tutti i perimetri, per la mappa generale
$features = [];
foreach (Aree::elenco(true) as $area) {
    $perimetro = PerimetroArea::leggi((string) $area['id']);
    if ($perimetro === null) {
        continue;
    }
    foreach (catageoFeatureArea($perimetro, $area) as $feature) {
        $features[] = $feature;
    }
}

echo json_encode(['type' => 'FeatureCollection', 'features' => $features],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
