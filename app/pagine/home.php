<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/home.php
 *  Descrizione ..: Pagina iniziale: riepilogo dell'archivio, stato di
 *                  avanzamento delle fasi di sviluppo e collegamenti rapidi.
 *  Versione .....: 1.0.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.0.0  2026-08-07  D.Candela  Fase 10 conclusa: prima release.
 *  0.16.0 2026-08-06  D.Candela  Fase 9b conclusa: resta solo la 10.
 *  0.15.0 2026-08-06  D.Candela  Fase 9 conclusa; l'import CSV diventa 9b.
 *  0.14.0 2026-08-06  D.Candela  Fase 8b conclusa: la fase 8 e completa.
 *  0.13.0 2026-08-06  D.Candela  Fase 8 conclusa; la migrazione diventa 8b.
 *  0.12.0 2026-08-06  D.Candela  Fase 7d conclusa: la fase 7 e completa.
 *  0.11.0 2026-08-06  D.Candela  Fase 7c conclusa; il resto diventa 7d.
 *  0.10.0 2026-08-06  D.Candela  Fase 7b conclusa; il resto diventa 7c.
 *  0.9.0  2026-08-06  D.Candela  Fase 7 conclusa; la parte non fatta della 7
 *                                diventa 7b, cosi l'elenco dice il vero.
 *  0.8.1  2026-08-05  D.Candela  Elenco delle fasi riallineato allo stato.
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

// Seconda barriera contro l'accesso diretto via HTTP: questo file ha senso
// solo se incluso da index.php, che definisce CATAGEO_ROOT. La guardia vale
// anche sui server dove il file .htaccess non viene letto.
defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

/** Conta i cataloghi presenti scandendo l'archivio (nessun registro centrale). */
$cataloghi = [];
$radice    = Percorsi::cataloghi();
if (is_dir($radice)) {
    foreach (scandir($radice) ?: [] as $voce) {
        if ($voce === '.' || $voce === '..') {
            continue;
        }
        $descrittore = Percorsi::unisci($radice, $voce . '/catalogo.xml');
        if (is_file($descrittore)) {
            $cataloghi[] = $voce;
        }
    }
}

$numeroIpogei = Csv::conta(Percorsi::indice('ipogei.csv'));
$numeroUtenti = count(Utenti::elenco());

/**
 * Stato delle fasi.
 *
 * ATTENZIONE: lo stesso elenco compare in README.md e nel piano di ANALISI.md.
 * Sono tre copie, e questa e gia rimasta indietro una volta di tre fasi: a fine
 * fase vanno aggiornate tutte e tre insieme, con il CHANGELOG.
 */
$fasi = [
    ['0',  'Struttura, configurazione, diagnostica',           'fatta'],
    ['1',  'Core, autenticazione, utenti, installer',          'fatta'],
    ['2',  'Anagrafiche: gruppi, esploratori, vocabolari',     'fatta'],
    ['2b', 'Cataloghi e serie di codifica',                    'fatta'],
    ['3',  'Ipogei: scheda, censimento, indice',               'fatta'],
    ['—',  'Coordinate: gradi, UTM, Gauss-Boaga, ED50',        'fatta'],
    ['4',  'Mappa: Leaflet/OSM, marker, WMS',                  'fatta'],
    ['4b', 'Provider Google Maps alternativo',                 'da fare'],
    ['5',  'Allegati, foto con miniature, video, metadati',    'fatta'],
    ['6',  'Rilievi 2D/3D, KML su mappa, viewer three.js',     'fatta'],
    ['6b', 'Geologia e layer cartografici tematici',           'da fare'],
    ['7',  'Esplorazioni: diari, partecipanti, cronologia',    'fatta'],
    ['7b', 'Bibliografia: opere, citazioni, export BibTeX',   'fatta'],
    ['7c', 'Dati scientifici: serie, import, grafici SVG',     'fatta'],
    ['7d', 'Biospeleologia, chirotteri, archeologia, avvisi',  'fatta'],
    ['8',  'Ricerca combinata, viste ed esportazioni',         'fatta'],
    ['8b', 'Migrazione fra cataloghi, con anteprima',          'fatta'],
    ['9',  'Indici, integrita, backup, verifica collegamenti',  'fatta'],
    ['9b', 'Import CSV massivo, con anteprima',                'fatta'],
    ['10', 'Stampa, manuale, dati di esempio, rilascio',      'fatta'],
    ['11', 'Acquisizione da fonti pubbliche (post-release)',   'da fare'],
];
?>

<div class="catageo-intestazione">
  <div>
    <h1><?= Testo::esc(Config::testo('catasto.nome', 'CATAGEO')) ?></h1>
    <p class="text-body-secondary mb-0">
      Catasto degli ipogei — cavita artificiali e naturali
    </p>
  </div>
  <span class="badge text-bg-warning">Versione <?= Testo::esc(CATAGEO_VERSIONE) ?> — in sviluppo</span>
</div>

<div class="row g-3 mb-4">

  <div class="col-sm-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-safe fs-2 text-primary" aria-hidden="true"></i>
          <div>
            <div class="fs-4 fw-semibold"><?= (int) $numeroIpogei ?></div>
            <div class="text-body-secondary small">Ipogei censiti</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-collection fs-2 text-primary" aria-hidden="true"></i>
          <div>
            <div class="fs-4 fw-semibold"><?= count($cataloghi) ?></div>
            <div class="text-body-secondary small">
              Catalogh<?= count($cataloghi) === 1 ? 'o' : 'i' ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-people fs-2 text-primary" aria-hidden="true"></i>
          <div>
            <div class="fs-4 fw-semibold"><?= (int) $numeroUtenti ?></div>
            <div class="text-body-secondary small">Utenti</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-hdd fs-2 text-primary" aria-hidden="true"></i>
          <div>
            <div class="fs-6 fw-semibold catageo-valore">
              <?= Testo::esc(Config::testo('percorsi.dati', 'dati')) ?>
            </div>
            <div class="text-body-secondary small">Archivio</div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<div class="row g-4">

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <h2 class="h6 mb-0"><i class="bi bi-list-check"></i> Avanzamento dello sviluppo</h2>
      </div>
      <div class="table-responsive">
        <table class="table table-sm catageo-tabella mb-0">
          <thead>
            <tr>
              <th scope="col" style="width:4rem">Fase</th>
              <th scope="col">Contenuto</th>
              <th scope="col" style="width:7rem">Stato</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fasi as [$numero, $contenutoFase, $stato]): ?>
              <tr>
                <td class="catageo-valore"><?= Testo::esc($numero) ?></td>
                <td><?= Testo::esc($contenutoFase) ?></td>
                <td>
                  <?php if ($stato === 'fatta'): ?>
                    <span class="badge text-bg-success">completata</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">da fare</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <h2 class="h6 mb-0"><i class="bi bi-info-circle"></i> Stato dell'installazione</h2>
      </div>
      <div class="card-body">
        <?php if ($cataloghi === []): ?>
          <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
            <div>
              Nessun catalogo presente nell'archivio. Il censimento degli ipogei
              richiede almeno un catalogo con le proprie serie di codifica.
            </div>
          </div>
        <?php else: ?>
          <p class="mb-2 text-body-secondary small">Cataloghi presenti nell'archivio:</p>
          <ul class="list-unstyled mb-3">
            <?php foreach ($cataloghi as $catalogo): ?>
              <li class="mb-1">
                <i class="bi bi-folder2-open text-primary" aria-hidden="true"></i>
                <span class="catageo-valore"><?= Testo::esc($catalogo) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2">
          <?php if (Auth::puo('strumenti')): ?>
            <a class="btn btn-sm btn-outline-primary" href="index.php?p=diagnostica">
              <i class="bi bi-activity"></i> Diagnostica
            </a>
          <?php endif; ?>
          <?php if (Auth::puo('gestisci_utenti')): ?>
            <a class="btn btn-sm btn-outline-primary" href="index.php?p=utenti">
              <i class="bi bi-people-fill"></i> Utenti
            </a>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline-secondary" href="docs/MANUALE.md">
            <i class="bi bi-book"></i> Manuale
          </a>
          <a class="btn btn-sm btn-outline-secondary" href="docs/ANALISI.md">
            <i class="bi bi-file-text"></i> Documento di analisi
          </a>
        </div>
      </div>
    </div>
  </div>

</div>
