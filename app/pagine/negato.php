<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/negato.php
 *  Descrizione ..: Pagina mostrata quando l'utente autenticato non ha i
 *                  permessi per la sezione richiesta.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

// Seconda barriera contro l'accesso diretto via HTTP: questo file ha senso
// solo se incluso da index.php, che definisce CATAGEO_ROOT. La guardia vale
// anche sui server dove il file .htaccess non viene letto.
defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');
?>
<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card border-warning-subtle">
      <div class="card-body d-flex gap-3">
        <i class="bi bi-shield-lock fs-2 text-warning" aria-hidden="true"></i>
        <div>
          <h1 class="h5 mb-2">Accesso negato</h1>
          <p class="text-body-secondary mb-3">
            L'utenza in uso ha livello
            <strong><?= Testo::esc(Utenti::ETICHETTE_LIVELLO[Auth::livello()] ?? Auth::livello()) ?></strong>
            e non è autorizzata a questa sezione.
          </p>
          <a class="btn btn-sm btn-outline-secondary" href="index.php">
            <i class="bi bi-house"></i> Torna alla pagina iniziale
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
