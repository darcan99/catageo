<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/geo-interroga.php
 *  Descrizione ..: Risposta JSON alla compilazione assistita della sezione
 *                  geologia: interroga i servizi cartografici sul punto di un
 *                  ipogeo e restituisce i valori proposti (6.16.2, fase 6b).
 *
 *                  Qui vive la politica sulle coordinate riservate. Il browser
 *                  chiede il modo, ma non lo decide: un modo non riconosciuto
 *                  su una scheda riservata diventa "niente", non "manda tutto".
 *                  Metterla nel JavaScript significherebbe affidarla a chi puo
 *                  cambiarla con la console del browser.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Prima stesura (fase 6b).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('compila_sezioni');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

/**
 * @param array<string,mixed> $dati
 */
function catageoRispondi(array $dati, int $stato = 200): never
{
    http_response_code($stato);
    echo json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/*
 * POST e non GET: l'interrogazione manda una coordinata a server di terzi, ed
 * e un'azione con un effetto fuori da qui. Il token impedisce che una pagina
 * altrui la faccia scattare a insaputa dell'utente.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    catageoRispondi(['errore' => 'Metodo non ammesso.'], 405);
}
Auth::esigiToken();

$codice      = trim((string) ($_POST['codice'] ?? ''));
$risoluzione = $codice === '' ? null : Ipogeo::risolvi($codice);
if ($risoluzione === null) {
    catageoRispondi(['errore' => 'Nessun ipogeo con codice "' . $codice . '".'], 404);
}

$scheda = $risoluzione['scheda'];
if (!Visibilita::schedaVisibile(
    (string) $scheda['ubicazione']['riservatezza'],
    (string) $scheda['catasto']['statoScheda']
)) {
    catageoRispondi(['errore' => 'Scheda non consultabile con il livello di utenza in uso.'], 403);
}

$coordinate = $scheda['ubicazione']['coordinate'];
$lat = trim((string) ($coordinate['latitudine'] ?? ''));
$lon = trim((string) ($coordinate['longitudine'] ?? ''));
if ($lat === '' || $lon === '') {
    catageoRispondi(['errore' => 'La scheda non ha coordinate: non c\'è un punto da interrogare.'], 422);
}

$riservate = Visibilita::coordinateRiservate((string) $scheda['ubicazione']['riservatezza']);
$modo      = (string) ($_POST['modo'] ?? '');
if (!in_array($modo, Geoservizi::MODI, true)) {
    $modo = $riservate ? 'niente' : 'puntuale';
}

/*
 * Su una scheda pubblica il modo offuscato non serve a nessuno: la coordinata
 * esatta e gia pubblica, e arrotondarla peggiorerebbe soltanto il risultato.
 */
if (!$riservate) {
    $modo = 'puntuale';
}

/*
 * Declassamento: chi non e autorizzato a vedere il punto esatto non puo
 * nemmeno spedirlo altrove, perche sarebbe un modo di leggerlo di rimbalzo.
 *
 * Oggi questo ramo non scatta mai, e va detto invece di lasciarlo sembrare una
 * difesa attiva: nella matrice dei permessi compila_sezioni e vedi_riservati
 * richiedono entrambi OPE, quindi chiunque arrivi fin qui il punto esatto lo
 * vede gia. Resta scritto perche la matrice e un unico array in Auth: il
 * giorno in cui qualcuno separasse i due permessi — un livello che compila le
 * sezioni ma non vede i riservati e una richiesta ragionevole — senza questa
 * riga si aprirebbe una via d'uscita silenziosa. La prova verifica proprio
 * l'accoppiamento, cosi se cambia qualcuno se ne accorge.
 */
if ($modo === 'puntuale' && $riservate && !Auth::puo('vedi_riservati')) {
    $modo = 'offuscata';
}

if (Mappa::layerInterrogabili() === []) {
    catageoRispondi([
        'errore' => 'Nessun layer interrogabile in configurazione: '
            . 'serve almeno un WMS con l\'attributo interroga (vedi config.xml).',
    ], 422);
}

$esito = Geoservizi::interroga((float) $lat, (float) $lon, $modo);

/*
 * Il messaggio che il browser mostra sotto le proposte. Si compone qui perche
 * dipende da cosa e successo davvero, e "nessuna proposta" ha tre cause molto
 * diverse fra loro che l'utente deve poter distinguere.
 */
$quante = count($esito['proposte']);
if ($esito['modo'] === 'niente') {
    $esito['messaggio'] = 'Nessuna richiesta inviata: le coordinate non hanno lasciato questo server.';
} elseif ($quante > 0) {
    $esito['messaggio'] = $quante === 1
        ? 'Un valore proposto. Va confermato: una carta non ha visto la cavità.'
        : $quante . ' valori proposti. Vanno confermati: una carta non ha visto la cavità.';
} elseif ($esito['interrogati'] === []) {
    $esito['messaggio'] = 'Nessun servizio ha risposto. Riprovare più tardi.';
} else {
    $esito['messaggio'] = 'I servizi hanno risposto, ma su questo punto non hanno dati.';
}

$esito['riservate'] = $riservate;
$esito['codice']    = (string) $risoluzione['codiceCorrente'];

/*
 * Si registra ogni interrogazione, anche quella andata a vuoto. E l'unico
 * punto in cui una coordinata di questo archivio esce verso un server di
 * terzi: se un domani qualcuno chiede se e successo, la risposta deve stare
 * scritta, con il modo e con l'arrotondamento applicato.
 */
$rigaIndice = IndiceIpogei::trova((string) $risoluzione['codiceCorrente']);
Log::modifica(
    'cartografia_interrogata',
    $rigaIndice === null ? '' : (string) ($rigaIndice['catalogo'] ?? ''),
    (string) $risoluzione['codiceCorrente'],
    Geologia::SIGLA,
    'modo ' . $esito['modo']
        . ($esito['coordinate']['offuscate']
            ? '; coordinata arrotondata a ' . $esito['coordinate']['metri'] . ' m'
            : '')
        . '; servizi interrogati: ' . count($esito['interrogati'])
        . '; proposte: ' . $quante
);

/*
 * Sempre un oggetto, anche quando e vuoto: un array PHP vuoto diventa [] in
 * JSON, e chi legge la risposta si troverebbe un tipo diverso a seconda di
 * quante proposte sono arrivate. La conversione va per ultima, dopo che
 * nessuno deve piu contarle: count() su un oggetto in PHP 8 e un errore fatale.
 */
$esito['proposte'] = (object) $esito['proposte'];

catageoRispondi($esito);
