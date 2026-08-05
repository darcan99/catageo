<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: docs/prove/coordinate/genera-dati.php
 *  Descrizione ..: Genera i dati per la verifica incrociata fra il motore di
 *                  conversione in PHP e proj4js.
 *
 *                  PHP converte una griglia di punti verso ogni sistema del
 *                  vocabolario e scrive il risultato in dati.json; la pagina
 *                  index.html ripete le stesse conversioni con proj4js, che usa
 *                  le medesime definizioni, e confronta.
 *
 *                  Due implementazioni scritte separatamente che concordano
 *                  sono una verifica ben piu forte di un test che confronta il
 *                  codice con se stesso. Serve soprattutto per la
 *                  trasformazione di datum: sbagliare il verso delle rotazioni
 *                  di Helmert produce errori di decine di metri che sembrano
 *                  del tutto plausibili.
 *
 *                  Uso, dalla cartella dell'applicativo:
 *                      php docs/prove/coordinate/genera-dati.php
 *                      php -S 127.0.0.1:8140 -t docs/prove/coordinate
 *                  poi aprire http://127.0.0.1:8140/index.html
 *  Versione .....: 0.5.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.5.0  2026-08-05  D.Candela  Prima stesura.
 * ============================================================================
 */

define('CATAGEO_ROOT', str_replace('\\', '/', dirname(__DIR__, 3)));
define('CATAGEO_VERSIONE', 'prove');

spl_autoload_register(static function (string $classe): void {
    $file = CATAGEO_ROOT . '/app/lib/' . $classe . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

/** Punti di prova: casi reali, capoluoghi e una griglia sull'Italia. */
$punti = [
    ['Sant Oreste', 42.233887, 12.527326],
    ['Roma',        41.890200, 12.492200],
    ['Torino',      45.070300,  7.686900],
    ['Trieste',     45.649500, 13.777800],
    ['Bari',        41.117200, 16.871900],
    ['Lecce',       40.351500, 18.172000],
    ['Palermo',     38.115700, 13.361500],
    ['Cagliari',    39.223800,  9.121600],
    ['Lampedusa',   35.501000, 12.606000],
    ['Bolzano',     46.498300, 11.354700],
];
for ($lat = 37.0; $lat <= 46.0; $lat += 1.5) {
    for ($lon = 7.0; $lon <= 18.0; $lon += 2.0) {
        $punti[] = [sprintf('griglia %.1f %.1f', $lat, $lon), $lat, $lon];
    }
}

$uscita = ['sistemi' => [], 'punti' => [], 'rifiutati' => []];

foreach (SistemiRiferimento::PREDEFINITI as $codice => $dati) {
    $uscita['sistemi'][$codice] = ['def' => $dati['def'], 'nome' => $dati['nome']];
}

foreach ($punti as [$nome, $lat, $lon]) {
    $voce = ['nome' => $nome, 'lat' => $lat, 'lon' => $lon, 'php' => []];

    foreach (SistemiRiferimento::PREDEFINITI as $codice => $dati) {
        if ($codice === 'EPSG:4326') {
            continue;
        }
        try {
            $proiettate = SistemiRiferimento::daWgs84($codice, $lat, $lon);
            $ritorno    = SistemiRiferimento::versoWgs84($codice, $proiettate['x'], $proiettate['y']);

            $voce['php'][$codice] = [
                'x'          => $proiettate['x'],
                'y'          => $proiettate['y'],
                'ritornoLat' => $ritorno['latitudine'],
                'ritornoLon' => $ritorno['longitudine'],
            ];
        } catch (Throwable $e) {
            // Il rifiuto fuori fuso e un comportamento voluto, non un errore:
            // si annota e si esclude dal confronto.
            $uscita['rifiutati'][] = ['punto' => $nome, 'sistema' => $codice, 'motivo' => $e->getMessage()];
        }
    }

    $uscita['punti'][] = $voce;
}

$destinazione = __DIR__ . '/dati.json';
file_put_contents(
    $destinazione,
    (string) json_encode($uscita, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo 'Sistemi ...........: ' . count($uscita['sistemi']) . "\n";
echo 'Punti .............: ' . count($uscita['punti']) . "\n";
echo 'Conversioni escluse: ' . count($uscita['rifiutati']) . " (fuori dal campo di validita del fuso)\n";
echo 'Scritto ...........: dati.json (' . round(filesize($destinazione) / 1024) . " KB)\n";

// Giro completo interno al solo PHP: andata e ritorno devono ridare il punto.
$scartoMax = 0.0;
$peggiore  = '';
foreach ($uscita['punti'] as $p) {
    foreach ($p['php'] as $codice => $r) {
        $dLat = abs($r['ritornoLat'] - $p['lat']) * 111320.0;
        $dLon = abs($r['ritornoLon'] - $p['lon']) * 111320.0 * cos(deg2rad($p['lat']));
        $scarto = sqrt($dLat * $dLat + $dLon * $dLon);
        if ($scarto > $scartoMax) {
            $scartoMax = $scarto;
            $peggiore  = $p['nome'] . ' / ' . $codice;
        }
    }
}
printf("Giro completo PHP .: scarto massimo %.4f mm (%s)\n", $scartoMax * 1000, $peggiore);
echo "\nOra servire questa cartella e aprire index.html per il confronto con proj4js.\n";
