<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/in-sviluppo.php
 *  Descrizione ..: Segnaposto per le sezioni non ancora realizzate. Dichiara
 *                  in quale fase del piano di sviluppo verranno costruite,
 *                  invece di presentare una voce di menu che non fa nulla.
 *  Versione .........: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 *
 * @var string               $titolo        titolo della sezione
 * @var string               $richiesta     identificativo della pagina
 * @var array<string,string> $fasiPreviste  mappa pagina => fase prevista
 */

// Seconda barriera contro l'accesso diretto via HTTP: questo file ha senso
// solo se incluso da index.php, che definisce CATAGEO_ROOT. La guardia vale
// anche sui server dove il file .htaccess non viene letto.
defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

$fase = $fasiPreviste[$richiesta] ?? 'Fase da definire';
?>

<div class="catageo-intestazione">
  <h1><?= Testo::esc($titolo) ?></h1>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body d-flex gap-3">
        <i class="bi bi-cone-striped fs-2 text-warning" aria-hidden="true"></i>
        <div>
          <h2 class="h5 mb-2">Sezione non ancora realizzata</h2>
          <p class="text-body-secondary mb-3">
            Questa parte dell'applicativo e prevista nel piano di sviluppo:
            <strong><?= Testo::esc($fase) ?></strong>.
          </p>
          <p class="catageo-nota mb-3">
            Il dettaglio di quanto verra realizzato, con il modello dati e le
            funzioni previste, e nel documento di analisi.
          </p>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="docs/ANALISI.md">
              <i class="bi bi-file-text"></i> Documento di analisi
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="index.php">
              <i class="bi bi-house"></i> Pagina iniziale
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
