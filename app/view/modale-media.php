<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/view/modale-media.php
 *  Descrizione ..: Finestra per guardare una foto, un video o un documento
 *                  senza lasciare la pagina.
 *
 *                  Aprire ogni immagine in una scheda nuova costringe a tornare
 *                  indietro dopo ogni sguardo, e in una galleria di venti foto
 *                  significa venti andate e ritorni. Qui si resta dove si era.
 *
 *                  E una vista condivisa fra la scheda dell'ipogeo e la pagina
 *                  di gestione: due finestre uguali scritte due volte finirebbero
 *                  per divergere alla prima modifica.
 *  Versione .....: 1.3.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.3.0  2026-08-07  D.Candela  Ospita anche i documenti (PDF).
 *  0.7.1  2026-08-05  D.Candela  Prima stesura.
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');
?>
<div class="modal fade catageo-non-stampare" id="catageoMedia" tabindex="-1"
     aria-labelledby="catageoMediaTitolo" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header py-2">
        <div class="me-auto">
          <h2 class="h6 mb-0" id="catageoMediaTitolo">—</h2>
          <div class="catageo-nota" id="catageoMediaSottotitolo"></div>
        </div>

        <div class="d-flex align-items-center gap-1">
          <a class="btn btn-sm btn-outline-secondary" id="catageoMediaMappa"
             target="_blank" rel="noopener" hidden
             title="Apri la posizione dello scatto su Google Maps">
            <i class="bi bi-geo-alt"></i>
          </a>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="catageoMediaSchermo"
                  title="Schermo intero">
            <i class="bi bi-arrows-fullscreen"></i>
          </button>
          <a class="btn btn-sm btn-outline-secondary" id="catageoMediaScarica"
             download title="Scarica il file originale">
            <i class="bi bi-download"></i>
          </a>
          <button type="button" class="btn-close ms-1" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
      </div>

      <?php
      // Il corpo e vuoto: img o video ci vengono messi dal JavaScript secondo
      // cio che si apre. Tenere entrambi gli elementi sempre presenti
      // significherebbe che un video resta caricato mentre si guarda una foto.
      ?>
      <div class="modal-body p-0 catageo-media-corpo" id="catageo-media-corpo"></div>

      <div class="modal-footer py-2">
        <span class="catageo-nota me-auto" id="catageoMediaPiede"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
      </div>

    </div>
  </div>
</div>
