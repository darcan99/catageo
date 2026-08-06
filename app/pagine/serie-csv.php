<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/serie-csv.php
 *  Descrizione ..: Consegna il CSV di una serie di misure (6.13).
 *
 *                  Non passa da scarica.php perche una serie non e una risorsa
 *                  dell'indice di sezione: e un file citato dal descrittore, e
 *                  ha una riservatezza propria e prevalente su quella
 *                  dell'ipogeo.
 *
 *                  Il file si consegna intero, anche quando e piu grande del
 *                  tetto che l'interfaccia usa per statistiche e grafico: chi
 *                  scarica vuole i dati, non un campione.
 *  Versione .....: 0.11.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.11.0  2026-08-06  D.Candela  Prima stesura (fase 7c).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('esporta');

$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';
$prog   = isset($_GET['prog']) ? (int) $_GET['prog'] : 0;

/** Risposta d'errore in chiaro: chi scarica un CSV non vuole una pagina HTML. */
$nega = static function (int $stato, string $messaggio): void {
    http_response_code($stato);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo $messaggio . "\n";
};

$risoluzione = $codice === '' ? null : Ipogeo::risolvi($codice);
if ($risoluzione === null) {
    $nega(404, 'Ipogeo non trovato.');
    return;
}

$scheda = $risoluzione['scheda'];
$codice = $risoluzione['codiceCorrente'];

if (!Visibilita::schedaVisibile(
    (string) $scheda['ubicazione']['riservatezza'],
    (string) $scheda['catasto']['statoScheda']
)) {
    $nega(403, 'Scheda non consultabile con il livello di utenza in uso.');
    return;
}

$serie = Scientifici::trovaSerie($codice, $prog);
if ($serie === null) {
    $nega(404, 'Serie non trovata.');
    return;
}

// La riservatezza della serie prevale: una cavita pubblica puo ospitare un
// monitoraggio che non va divulgato.
if (!Visibilita::livelloVisibile((string) $serie['riservatezza'])) {
    $nega(403, 'Serie riservata.');
    return;
}

$percorso = Scientifici::percorsoCsv($codice, $serie);
if ($percorso === null || !is_file($percorso)) {
    $nega(404, 'Il file della serie non e presente sul disco.');
    return;
}

// Doppia barriera sul percorso: il nome del file viene da un XML che potrebbe
// essere stato modificato a mano. percorsoCsv() applica gia basename(), qui si
// verifica che il risultato stia davvero dentro l'archivio.
if (!Percorsi::dentro(Percorsi::cataloghi(), $percorso)) {
    Log::errore('Tentata consegna di un CSV fuori archivio: ' . $percorso);
    $nega(403, 'Percorso non ammesso.');
    return;
}

$nomeConsegna = Testo::nomeFileSicuro(basename($percorso), true);

if (!headers_sent()) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeConsegna . '"');
    header('Content-Length: ' . (string) filesize($percorso));
    header('Cache-Control: no-store, private');
}

// I buffer si svuotano prima di readfile(): su una serie da decine di megabyte
// accumulare tutto in memoria per poi emetterlo sarebbe inutile e rischioso.
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($percorso);

Log::modifica('serie_scaricata', '', $codice, Scientifici::SIGLA,
    Sezioni::riferimento(Scientifici::SIGLA, $prog));
