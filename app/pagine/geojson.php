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
 *  Versione .....: 0.6.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
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

$elementi   = [];
$senzaCoord = 0;

foreach (IndiceIpogei::elenco($filtro) as $riga) {
    $coord = Visibilita::coordinateDaRiga($riga);

    if (trim($coord['lat']) === '' || trim($coord['lon']) === '') {
        $senzaCoord++;
        continue;
    }

    $elementi[] = [
        'type'     => 'Feature',
        'geometry' => [
            'type'        => 'Point',
            // GeoJSON vuole longitudine prima della latitudine: e la sorgente
            // piu frequente di marker finiti in mezzo al mare.
            'coordinates' => [(float) $coord['lon'], (float) $coord['lat']],
        ],
        'properties' => [
            'codice'       => (string) $riga['codice'],
            'nome'         => (string) $riga['nome'],
            'catalogo'     => (string) $riga['catalogo'],
            'natura'       => (string) $riga['natura'],
            'tipologia'    => (string) $riga['tipologia'],
            'tipologiaNome' => Tipologie::nome((string) $riga['tipologia']),
            'comune'       => (string) $riga['comune'],
            'localita'     => (string) $riga['localita'],
            'quota'        => (string) $riga['quota'],
            'sviluppo'     => (string) $riga['sviluppo'],
            'dislivello'   => (string) $riga['dislivello'],
            'statoAccesso' => (string) $riga['stato_accesso'],
            'statoScheda'  => (string) $riga['stato_scheda'],
            'riservatezza' => (string) $riga['riservatezza'],
            'offuscate'    => $coord['offuscate'],
            'nFoto'        => (int) $riga['n_foto'],
            'nRilievi'     => (int) $riga['n_rilievi'],
            'nEsplorazioni' => (int) $riga['n_esplorazioni'],
            'haKml'        => $riga['ha_kml'] === '1',
            'url'          => 'index.php?p=ipogei&azione=scheda&codice=' . urlencode((string) $riga['codice']),
        ],
    ];
}

$geojson = [
    'type'     => 'FeatureCollection',
    'features' => $elementi,
    // Metadati fuori dallo standard ma dentro l'oggetto: comodi per l'interfaccia
    // e ignorati da qualunque lettore GeoJSON conforme.
    'catageo'  => [
        'totale'          => count($elementi),
        'senzaCoordinate' => $senzaCoord,
        'generato'        => date('c'),
    ],
];

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    // Nessuna cache: la riservatezza dipende da chi chiede, e una risposta
    // memorizzata da un proxy potrebbe finire a un altro utente.
    header('Cache-Control: no-store, private');
}

echo json_encode($geojson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
