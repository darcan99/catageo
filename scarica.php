<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: scarica.php
 *  Descrizione ..: Consegna mediata dei file dell'archivio (9.2).
 *
 *                  Nessun file dell'archivio e raggiungibile direttamente via
 *                  HTTP: dati/ e protetta da .htaccess e, dove quello non viene
 *                  letto, dalla collocazione fuori dal webroot. Tutto passa da
 *                  qui, che verifica sessione, permessi e riservatezza della
 *                  singola scheda prima di aprire il file. E l'unico modo per
 *                  far valere la riservatezza anche sui media: una foto
 *                  scaricabile per URL diretto renderebbe inutile ogni regola
 *                  applicata alla scheda che la contiene.
 *
 *                  Supporta le richieste parziali (Range) perche senza di quelle
 *                  un video non si puo scorrere: il browser puo solo riprodurlo
 *                  dall'inizio, riscaricandolo ogni volta.
 *  Versione .....: 0.7.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.7.0  2026-08-05  D.Candela  Prima stesura (fase 5).
 * ============================================================================
 */

require_once __DIR__ . '/app/bootstrap.php';

/**
 * Interrompe con un codice e un messaggio in testo semplice.
 *
 * Non si usa il layout HTML: chi chiama questo file si aspetta un file, e una
 * pagina di errore travestita da immagine confonderebbe e basta.
 */
function fermati(int $codice, string $messaggio): never
{
    if (!headers_sent()) {
        http_response_code($codice);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo $messaggio;
    exit;
}

if (!Config::caricata()) {
    fermati(503, 'CATAGEO non è configurato.');
}

// --------------------------------------------------------------- accesso

if (!Auth::autenticato() && !Config::booleano('sicurezza.accessoAnonimo', false)) {
    // 403 e non un rimando al login: la richiesta arriva quasi sempre da un tag
    // <img> o <video>, dove una pagina di accesso non ha modo di essere mostrata.
    fermati(403, 'Accesso non consentito: sessione assente o scaduta.');
}

if (!Auth::puo('consulta')) {
    fermati(403, 'Accesso non consentito.');
}

// --------------------------------------------------------------- parametri

$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';
$sigla  = isset($_GET['sez']) ? strtoupper(trim((string) $_GET['sez'])) : '';
$prog   = isset($_GET['prog']) ? (int) $_GET['prog'] : 0;
$mini   = isset($_GET['mini']) && $_GET['mini'] === '1';
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';

if ($codice === '' || $prog < 1 || !Sezioni::valida($sigla)) {
    fermati(400, 'Richiesta incompleta.');
}

// --------------------------------------------------------------- riservatezza

$risoluzione = Ipogeo::risolvi($codice);
if ($risoluzione === null) {
    fermati(404, 'Ipogeo non trovato.');
}

$scheda = $risoluzione['scheda'];
$codice = $risoluzione['codiceCorrente'];

// La stessa regola dell'elenco, della scheda e della mappa: sta in Visibilita
// proprio perche non diverga fra i quattro punti che la applicano.
if (!Visibilita::schedaVisibile(
    (string) $scheda['ubicazione']['riservatezza'],
    (string) $scheda['catasto']['statoScheda']
)) {
    fermati(403, 'Contenuto non consultabile con il livello di utenza in uso.');
}

$risorsa = Risorse::trova($codice, $sigla, $prog);
if ($risorsa === null) {
    fermati(404, 'Risorsa non trovata.');
}

// Una singola risorsa puo essere piu riservata della scheda che la contiene:
// la foto che mostra l'ingresso di una cavita protetta, per esempio.
if ((string) $risorsa['riservatezza'] === 'riservata' && !Auth::puo('vedi_riservati')) {
    fermati(403, 'Risorsa riservata.');
}

// --------------------------------------------------------------- file

if ($mini) {
    $percorso = Risorse::percorsoMiniatura($codice, $sigla, $risorsa);
    if ($percorso === null) {
        // La miniatura e una comodita, non un dato: se manca si consegna
        // l'originale invece di restituire un riquadro rotto.
        $percorso = Risorse::percorsoFile($codice, $sigla, $prog);
        $mini = false;
    }
} else {
    $percorso = Risorse::percorsoFile($codice, $sigla, $prog);
}

if ($percorso === null || !is_file($percorso)) {
    fermati(404, 'File non presente nell\'archivio.');
}

$nomeScaricato = $mini ? basename($percorso) : (string) $risorsa['file'];
$dimensione    = (int) filesize($percorso);

// Il tipo si rilegge dal contenuto invece di fidarsi dell'indice: l'indice e un
// file dell'archivio, modificabile a mano, e un Content-Type sbagliato e il
// primo passo di un XSS.
$tipo = $mini ? 'image/jpeg' : Upload::tipoReale($percorso);

// Gli SVG si consegnano SEMPRE come allegato, mai visualizzati: un SVG e un
// documento XML che puo contenere script, e mostrarlo in linea significherebbe
// eseguirli nell'origine dell'applicativo.
$estensione = strtolower((string) pathinfo($percorso, PATHINFO_EXTENSION));
if ($estensione === 'svg') {
    $inline = false;
    $tipo   = 'application/octet-stream';
}

// In linea si mostrano solo i tipi che il browser sa rendere senza rischi.
$mostrabili = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
               'video/mp4', 'video/webm', 'video/ogg', 'application/pdf'];
if ($inline && !in_array($tipo, $mostrabili, true)) {
    $inline = false;
}

// --------------------------------------------------------------- consegna

// Il buffer va svuotato prima di scrivere il file: un solo byte di output
// residuo corromperebbe il contenuto consegnato.
while (ob_get_level() > 0) {
    ob_end_clean();
}

@set_time_limit(0);

if (!headers_sent()) {
    header('Content-Type: ' . $tipo);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
        . '; filename="' . str_replace('"', '', $nomeScaricato) . '"'
        . "; filename*=UTF-8''" . rawurlencode($nomeScaricato));
    header('Accept-Ranges: bytes');

    // Contenuti dell'archivio: mai in cache condivisa, perche la visibilita
    // dipende da chi li ha chiesti.
    header('Cache-Control: private, max-age=600');
    header('X-Content-Type-Options: nosniff');
}

$inizio = 0;
$fine   = $dimensione - 1;

$intervallo = isset($_SERVER['HTTP_RANGE']) ? (string) $_SERVER['HTTP_RANGE'] : '';
if ($intervallo !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($intervallo), $parti)) {
    $daInizio = $parti[1] !== '';
    $daFine   = $parti[2] !== '';

    if ($daInizio) {
        $inizio = (int) $parti[1];
        if ($daFine) {
            $fine = (int) $parti[2];
        }
    } elseif ($daFine) {
        // "bytes=-500": gli ultimi 500 byte.
        $inizio = max(0, $dimensione - (int) $parti[2]);
    }

    $fine = min($fine, $dimensione - 1);

    if ($inizio > $fine || $inizio >= $dimensione) {
        if (!headers_sent()) {
            http_response_code(416);
            header('Content-Range: bytes */' . $dimensione);
        }
        exit;
    }

    if (!headers_sent()) {
        http_response_code(206);
        header('Content-Range: bytes ' . $inizio . '-' . $fine . '/' . $dimensione);
    }
}

if (!headers_sent()) {
    header('Content-Length: ' . (string) ($fine - $inizio + 1));
}

$handle = @fopen($percorso, 'rb');
if ($handle === false) {
    fermati(500, 'File non apribile.');
}

fseek($handle, $inizio);
$restanti = $fine - $inizio + 1;

// A blocchi e non con readfile(): un video da centinaia di megabyte letto in un
// colpo solo supererebbe memory_limit su qualunque hosting economico.
while ($restanti > 0 && !feof($handle)) {
    $blocco = (int) min(262144, $restanti);
    $dati   = fread($handle, $blocco);
    if ($dati === false || $dati === '') {
        break;
    }
    echo $dati;
    $restanti -= strlen($dati);

    // Se chi ha chiesto il file chiude la connessione a meta, non ha senso
    // continuare a leggere dal disco.
    if (connection_aborted()) {
        break;
    }
    flush();
}

fclose($handle);
exit;
