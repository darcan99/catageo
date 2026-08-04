<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/esci.php
 *  Descrizione ..: Chiude la sessione e riporta alla pagina di accesso.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

// logout() chiude la sessione e ne apre subito una nuova con id rigenerato:
// il messaggio scritto qui sotto sopravvive quindi al redirect.
Auth::logout();
Auth::messaggio('info', 'Sessione chiusa.');

header('Location: index.php?p=login');
exit;
