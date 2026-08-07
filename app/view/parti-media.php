<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/view/parti-media.php
 *  Descrizione ..: Pezzi di interfaccia condivisi fra la scheda dell'ipogeo e
 *                  la pagina di gestione delle risorse: gli attributi che
 *                  aprono la finestra dei media e la riga di dati sotto una
 *                  miniatura.
 *
 *                  Sono funzioni e non metodi di una classe di dominio perche
 *                  producono HTML: Risorse sa cos'e una risorsa, non come si
 *                  disegna. Stanno insieme perche le due pagine devono mostrare
 *                  le stesse cose, e duplicarle vorrebbe dire vederle divergere
 *                  alla prima modifica.
 *  Versione .....: 1.3.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.3.0  2026-08-07  D.Candela  I PDF si aprono nella finestra; la scelta si fa
 *                                sul file e non sulla sezione.
 *  0.7.1  2026-08-05  D.Candela  Prima stesura.
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

/**
 * Estensioni che si possono mostrare dentro la finestra, con il modo in cui
 * vanno mostrate.
 *
 * L'elenco NON e una preferenza estetica: deve coincidere con i tipi che
 * scarica.php accetta di consegnare in linea. Se qui comparisse un formato che
 * li non passa, la finestra si aprirebbe vuota e il browser scaricherebbe il
 * file alle spalle dell'utente — che e esattamente il comportamento che questa
 * finestra esiste per evitare. Quando si tocca la lista in scarica.php si
 * tocca anche questa.
 */
const CATAGEO_FINESTRA_PER_ESTENSIONE = [
    'jpg'  => 'immagine', 'jpeg' => 'immagine', 'png' => 'immagine',
    'gif'  => 'immagine', 'webp' => 'immagine', 'bmp' => 'immagine',
    'mp4'  => 'video',    'webm' => 'video',    'ogg' => 'video', 'ogv' => 'video',
    'pdf'  => 'documento',
];

/**
 * Come va aperta una risorsa nella finestra, o '' se non si puo aprire.
 *
 * La scelta si fa sul FILE e non sulla sezione. Sulla sezione sembrava
 * ragionevole finche le sezioni erano omogenee, ma non lo sono: fra le foto la
 * configurazione ammette il TIFF, che nessun browser disegna, e fra i video il
 * MOV e l'AVI. Per quelli la finestra si apriva su un riquadro rotto mentre il
 * browser scaricava il file di nascosto. Guardare il file copre anche il caso
 * opposto, cioe il PDF in mezzo agli allegati, che ora si legge senza uscire
 * dalla pagina.
 *
 * @param array<string,mixed> $risorsa
 */
function catageoFinestraPer(array $risorsa): string
{
    $estensione = strtolower((string) pathinfo((string) $risorsa['file'], PATHINFO_EXTENSION));

    /*
     * Un SVG e un documento XML che puo contenere script: scarica.php lo
     * consegna sempre come allegato, mai in linea. Non compare nella lista
     * qui sopra, ma vale la pena dirlo dove qualcuno potrebbe pensare di
     * aggiungercelo.
     */
    return CATAGEO_FINESTRA_PER_ESTENSIONE[$estensione] ?? '';
}

/**
 * Indirizzo di consegna di una risorsa.
 *
 * @param bool $mini   true per la miniatura
 * @param bool $inline true per mostrarla nel browser invece di scaricarla
 */
function catageoUrlRisorsa(string $codice, string $sigla, int $prog, bool $mini = false, bool $inline = false): string
{
    return 'scarica.php?codice=' . urlencode($codice) . '&sez=' . $sigla . '&prog=' . $prog
        . ($mini ? '&mini=1' : '') . ($inline ? '&inline=1' : '');
}

/**
 * Collegamento a Google Maps sulla posizione della risorsa, se ce l'ha.
 *
 * @param array<string,mixed> $risorsa
 */
function catageoUrlMappaEsterna(array $risorsa): string
{
    $lat = trim((string) ($risorsa['latitudine'] ?? ''));
    $lon = trim((string) ($risorsa['longitudine'] ?? ''));

    if ($lat === '' || $lon === '') {
        return '';
    }

    // Formato "q=lat,lon": e quello che Google Maps interpreta come punto
    // esatto invece che come ricerca testuale.
    return 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lon);
}

/**
 * Attributi che fanno aprire la risorsa nella finestra dei media.
 *
 * Vanno messi su un <a>: se il JavaScript non parte il collegamento resta
 * valido e il file si apre comunque, solo in una scheda nuova.
 *
 * @param array<string,mixed> $risorsa
 */
function catageoAttributiMedia(array $risorsa, string $codice, string $sigla): string
{
    $tipo = catageoFinestraPer($risorsa);
    if ($tipo === '') {
        // Niente attributi: il collegamento resta quello che era e il file si
        // scarica. Meglio del nulla di una finestra che non sa cosa mostrare.
        return '';
    }

    $prog = (int) $risorsa['progressivo'];

    $sottotitolo = Sezioni::riferimento($sigla, $prog);
    if ((string) $risorsa['data'] !== '') {
        $sottotitolo .= ' · ' . $risorsa['data'];
    }

    $piede = catageoTipoFile($risorsa) . ' · ' . Testo::dimensione((int) $risorsa['dimensione']);
    if ((string) $risorsa['descrizione'] !== '') {
        $piede .= ' · ' . Testo::estratto((string) $risorsa['descrizione'], 90);
    }

    $attributi = [
        'data-catageo-media'    => '1',
        'data-media-tipo'       => $tipo,
        'data-media-url'        => catageoUrlRisorsa($codice, $sigla, $prog, false, true),
        'data-media-scarica'    => catageoUrlRisorsa($codice, $sigla, $prog),
        'data-media-titolo'     => (string) $risorsa['titolo'],
        'data-media-sottotitolo' => $sottotitolo,
        'data-media-piede'      => $piede,
        'data-media-mappa'      => catageoUrlMappaEsterna($risorsa),
    ];

    $html = '';
    foreach ($attributi as $nome => $valore) {
        if ($valore === '') {
            continue;
        }
        $html .= ' ' . $nome . '="' . Testo::esc($valore) . '"';
    }

    return $html;
}

/**
 * Tipo del file in forma breve e leggibile.
 *
 * Si mostra l'estensione e non il MIME: "JPG" dice a un operatore quello che
 * "image/jpeg" direbbe a un programmatore.
 *
 * @param array<string,mixed> $risorsa
 */
function catageoTipoFile(array $risorsa): string
{
    $estensione = strtoupper((string) pathinfo((string) $risorsa['file'], PATHINFO_EXTENSION));

    return $estensione !== '' ? $estensione : (string) $risorsa['mime'];
}

/**
 * Riga di dati sotto una miniatura: tipo, peso, data e posizione.
 *
 * La posizione e un collegamento a una mappa esterna e non un'etichetta: le
 * coordinate di uno scatto servono per andarci, non per leggerle.
 *
 * @param array<string,mixed> $risorsa
 */
function catageoDatiMedia(array $risorsa, bool $conRiferimento = true, string $sigla = ''): string
{
    $pezzi = [];

    if ($conRiferimento && $sigla !== '') {
        $pezzi[] = '<span class="catageo-valore">'
            . Testo::esc(Sezioni::riferimento($sigla, (int) $risorsa['progressivo'])) . '</span>';
    }

    $pezzi[] = '<span class="catageo-tipo-file" title="' . Testo::esc((string) $risorsa['mime']) . '">'
        . Testo::esc(catageoTipoFile($risorsa)) . '</span>';

    $pezzi[] = '<span>' . Testo::esc(Testo::dimensione((int) $risorsa['dimensione'])) . '</span>';

    if ((string) $risorsa['data'] !== '') {
        $pezzi[] = '<span title="Data di scatto o di ripresa"><i class="bi bi-calendar3"></i> '
            . Testo::esc((string) $risorsa['data']) . '</span>';
    }

    $mappa = catageoUrlMappaEsterna($risorsa);
    if ($mappa !== '') {
        $pezzi[] = '<a class="catageo-geotag" href="' . Testo::esc($mappa) . '"'
            . ' target="_blank" rel="noopener"'
            . ' title="Posizione dello scatto: ' . Testo::esc((string) $risorsa['latitudine'])
            . ', ' . Testo::esc((string) $risorsa['longitudine']) . ' — apre Google Maps">'
            . '<i class="bi bi-geo-alt-fill"></i> GPS</a>';
    }

    return '<div class="catageo-dati-media">' . implode('', $pezzi) . '</div>';
}
