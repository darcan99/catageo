<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/scarica-backup.php
 *  Descrizione ..: Consegna un file di backup (9.14).
 *
 *                  I backup stanno dentro l'archivio, che non e raggiungibile
 *                  via HTTP: servono consegnati da qui, e solo a chi ha i
 *                  permessi di manutenzione. Un backup contiene l'intero
 *                  catasto, comprese le ubicazioni riservate.
 *  Versione .....: 0.15.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.15.0  2026-08-06  D.Candela  Prima stesura (fase 9).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

// Non basta "esporta": un backup contiene tutto, riservatezza compresa.
Auth::esigi('strumenti');

$nome = basename(trim((string) ($_GET['nome'] ?? '')));

$nega = static function (int $stato, string $messaggio): void {
    http_response_code($stato);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo $messaggio . "\n";
};

if ($nome === '' || !str_ends_with(strtolower($nome), '.zip')) {
    $nega(400, 'Nome di backup non valido.');
    return;
}

$percorso = Percorsi::unisci(Backup::cartella(), $nome);

if (!is_file($percorso)) {
    $nega(404, 'Backup non trovato.');
    return;
}

// basename() sopra gia impedisce di uscire dalla cartella; qui si verifica il
// risultato, perche una barriera sola su un percorso costruito da input e poca.
if (!Percorsi::dentro(Backup::cartella(), $percorso)) {
    Log::errore('Tentata consegna di un backup fuori cartella: ' . $percorso);
    $nega(403, 'Percorso non ammesso.');
    return;
}

if (!headers_sent()) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . (string) filesize($percorso));
    header('Cache-Control: no-store, private');
}

// I buffer si svuotano prima di readfile(): un backup puo pesare parecchio, e
// accumularlo in memoria per poi emetterlo sarebbe inutile e rischioso.
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($percorso);

Log::modifica('backup_scaricato', '', '', 'strumenti', $nome);
