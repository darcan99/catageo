<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/bibtex.php
 *  Descrizione ..: Export BibTeX: la bibliografia di un ipogeo, oppure
 *                  l'intero catalogo generale delle opere (9.7).
 *
 *                  Risposta grezza, senza layout: il file va aperto da Zotero,
 *                  JabRef o LaTeX, non da un browser che lo impagina.
 *
 *                  L'export esiste perche CATAGEO non impone uno stile
 *                  bibliografico normalizzato: chi deve applicarne uno lo fa
 *                  con lo strumento che gia usa, invece di ribattere a mano.
 *  Versione .....: 0.10.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.10.0  2026-08-06  D.Candela  Prima stesura (fase 7b).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('esporta');

$tutto  = !empty($_GET['tutto']);
$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';

if ($tutto) {
    // Il catalogo generale e un'anagrafica: chi la gestisce puo esportarla.
    Auth::esigi('anagrafiche');
    $contenuto = Opere::bibtexCatalogo();
    $nomeFile  = 'catageo-opere.bib';
} else {
    $risoluzione = $codice === '' ? null : Ipogeo::risolvi($codice);
    if ($risoluzione === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "% Ipogeo non trovato.\n";
        return;
    }

    $scheda = $risoluzione['scheda'];
    $codice = $risoluzione['codiceCorrente'];

    // La riservatezza vale anche qui: un export non deve essere la via di
    // servizio per leggere una scheda che l'interfaccia non mostrerebbe.
    if (!Visibilita::schedaVisibile(
        (string) $scheda['ubicazione']['riservatezza'],
        (string) $scheda['catasto']['statoScheda']
    )) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "% Scheda non consultabile con il livello di utenza in uso.\n";
        return;
    }

    $contenuto = Bibliografia::bibtex($codice);
    $nomeFile  = Testo::nomeFileSicuro($codice . '-bibliografia', true) . '.bib';
}

if (!headers_sent()) {
    // application/x-bibtex e il tipo riconosciuto dai gestori di bibliografia;
    // il "download" e esplicito perche un .bib mostrato nel browser e solo
    // testo che l'utente dovrebbe poi salvare a mano.
    header('Content-Type: application/x-bibtex; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeFile . '"');
    header('Cache-Control: no-store, private');
}

echo $contenuto;
