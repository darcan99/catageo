<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/tracciato.php
 *  Descrizione ..: Restituisce il rilievo georiferito di un ipogeo come
 *                  GeoJSON, pronto per essere sovrapposto alla mappa (§7.3).
 *
 *                  Con "prog" si chiede un singolo rilievo; senza, tutti quelli
 *                  dell'ipogeo marcati come visibili in mappa, uniti in una sola
 *                  raccolta con l'indicazione di quale rilievo viene da dove.
 *
 *                  La conversione avviene qui e non nel browser: il file non e
 *                  mai raggiungibile per URL diretto, quindi questa e anche la
 *                  porta in cui si applicano riservatezza e permessi.
 *  Versione .....: 0.8.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.8.0  2026-08-05  D.Candela  Prima stesura (fase 6).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');

$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';
$prog   = isset($_GET['prog']) ? (int) $_GET['prog'] : 0;

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
}

/** Risposta di errore nello stesso formato di quella buona. */
$errore = static function (int $stato, string $messaggio): never {
    if (!headers_sent()) {
        http_response_code($stato);
    }
    echo json_encode(['errore' => $messaggio], JSON_UNESCAPED_UNICODE);
    exit;
};

$risoluzione = $codice === '' ? null : Ipogeo::risolvi($codice);
if ($risoluzione === null) {
    $errore(404, 'Ipogeo non trovato.');
}

$scheda = $risoluzione['scheda'];
$codice = $risoluzione['codiceCorrente'];

if (!Visibilita::schedaVisibile(
    (string) $scheda['ubicazione']['riservatezza'],
    (string) $scheda['catasto']['statoScheda']
)) {
    $errore(403, 'Scheda non consultabile con il livello di utenza in uso.');
}

// Un rilievo puo mostrare l'andamento della cavita sotto una proprieta privata:
// se e riservato non esce, esattamente come le foto.
$riservatiAmmessi = Auth::puo('vedi_riservati');

$daConvertire = [];
if ($prog > 0) {
    $risorsa = Risorse::trova($codice, 'RI', $prog);
    if ($risorsa === null) {
        $errore(404, 'Rilievo non trovato.');
    }
    if (!Tracciato::convertibile((string) $risorsa['file'])) {
        $errore(400, 'Questo rilievo non è in un formato sovrapponibile alla mappa.');
    }
    $daConvertire = [$risorsa];
} else {
    $daConvertire = Risorse::tracciati($codice);
}

$elementi = [];
$avvisi   = [];

foreach ($daConvertire as $risorsa) {
    if ((string) $risorsa['riservatezza'] === 'riservata' && !$riservatiAmmessi) {
        continue;
    }

    $p = (int) $risorsa['progressivo'];
    $percorso = Risorse::percorsoFile($codice, 'RI', $p);
    if ($percorso === null) {
        $avvisi[] = Sezioni::riferimento('RI', $p) . ': file assente dall\'archivio.';
        continue;
    }

    try {
        $geojson = Tracciato::aGeoJson($percorso);
    } catch (TracciatoEccezione $e) {
        // Un rilievo illeggibile non deve far sparire gli altri: si annota e si
        // prosegue, cosi la mappa mostra quello che c'e e dice cosa manca.
        $avvisi[] = Sezioni::riferimento('RI', $p) . ': ' . $e->getMessage();
        continue;
    }

    foreach ($geojson['features'] as $elemento) {
        // Ogni geometria porta con se da quale rilievo viene: sulla mappa i
        // tracciati di piu rilievi si sovrappongono e devono restare distinguibili.
        $elemento['properties'] = array_merge($elemento['properties'] ?? [], [
            'rilievo'       => Sezioni::riferimento('RI', $p),
            'rilievoTitolo' => (string) $risorsa['titolo'],
            'progressivo'   => $p,
        ]);
        $elementi[] = $elemento;
    }
}

$raccolta = ['type' => 'FeatureCollection', 'features' => $elementi];

echo json_encode([
    'type'     => 'FeatureCollection',
    'features' => $elementi,
    'catageo'  => [
        'codice'    => $codice,
        'rilievi'   => count($daConvertire),
        'geometrie' => count($elementi),
        'riquadro'  => Tracciato::riquadro($raccolta),
        'riepilogo' => Tracciato::riepilogo($raccolta),
        'avvisi'    => $avvisi,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
