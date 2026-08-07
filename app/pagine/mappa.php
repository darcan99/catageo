<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/mappa.php
 *  Descrizione ..: Mappa generale del catasto: tutti gli ipogei georeferenziati
 *                  visibili all'utente, con filtri immediati e layer tematici.
 *
 *                  I dati non vengono stampati nella pagina ma richiesti al
 *                  GeoJSON: la pagina resta leggera e lo stesso endpoint serve
 *                  la mappa, le esportazioni e qualunque uso futuro.
 *  Versione .....: 0.6.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.6.0  2026-08-05  D.Candela  Prima stesura (fase 4).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

// Risorse cartografiche: solo su questa pagina, non su tutte.
$cssPagina = [
    'assets/vendor/leaflet-1.9.4/leaflet.css',
    'assets/css/catageo-mappa.css',
];
$jsPagina = array_merge(
    ['assets/vendor/proj4-2.21.0/proj4.min.js'],
    Mappa::scriptBrowser()
);

$cataloghi = Cataloghi::elenco();

/** Etichette delle nature, per la legenda e per il filtro. */
$nature = [];
foreach (Tipologie::perLivello('natura', '', false) as $voce) {
    $nature[(string) $voce['codice']] = (string) $voce['nome'];
}

// Preselezione dal parametro, cosi che i collegamenti dall'elenco portino sulla
// mappa gia filtrata.
$catalogoScelto = isset($_GET['catalogo']) ? Cataloghi::normalizzaSigla((string) $_GET['catalogo']) : '';

$totaleVisibili = IndiceIpogei::conta(Visibilita::filtroIndice());
?>

<div class="catageo-intestazione">
  <h1><i class="bi bi-map" aria-hidden="true"></i> Mappa</h1>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="mappaAdatta"
            title="Inquadra tutti gli ipogei visibili">
      <i class="bi bi-arrows-angle-contract"></i> Inquadra tutto
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="mappaPosizione"
            title="Centra sulla posizione rilevata dal dispositivo">
      <i class="bi bi-crosshair"></i> Dove sono
    </button>
    <a class="btn btn-sm btn-outline-secondary" href="index.php?p=ipogei">
      <i class="bi bi-list-ul"></i> Elenco
    </a>
  </div>
</div>

<?php if ($totaleVisibili === 0): ?>

  <div class="alert alert-info d-flex align-items-start gap-2">
    <i class="bi bi-info-circle-fill mt-1" aria-hidden="true"></i>
    <div>
      Nessun ipogeo da mostrare. Censire almeno una scheda con le coordinate
      dell'ingresso per vederla comparire sulla mappa.
      <?php if (Auth::puo('modifica_scheda')): ?>
        <a class="alert-link" href="index.php?p=ipogei&amp;azione=nuovo">Censisci un ipogeo</a>.
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>

  <div class="card mb-3">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label for="mappaFiltroTesto" class="form-label mb-1">Cerca</label>
          <input type="search" class="form-control form-control-sm" id="mappaFiltroTesto"
                 placeholder="codice, nome, comune, localita" autocomplete="off">
        </div>
        <div class="col-md-2">
          <label for="mappaFiltroNatura" class="form-label mb-1">Natura</label>
          <select class="form-select form-select-sm" id="mappaFiltroNatura">
            <option value="">Tutte</option>
            <?php foreach ($nature as $codice => $nome): ?>
              <option value="<?= Testo::esc($codice) ?>"><?= Testo::esc($nome) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="mappaFiltroCatalogo" class="form-label mb-1">Catalogo</label>
          <select class="form-select form-select-sm" id="mappaFiltroCatalogo">
            <option value="">Tutti</option>
            <?php foreach ($cataloghi as $c): ?>
              <option value="<?= Testo::esc((string) $c['sigla']) ?>"
                <?= $catalogoScelto === (string) $c['sigla'] ? 'selected' : '' ?>>
                <?= Testo::esc((string) $c['sigla']) ?> — <?= Testo::esc((string) $c['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="mappaFiltroAccesso" class="form-label mb-1">Accesso</label>
          <select class="form-select form-select-sm" id="mappaFiltroAccesso">
            <option value="">Qualunque stato</option>
            <option value="aperto">Praticabile</option>
            <option value="chiuso">Non praticabile</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div id="catageoMappaStato" class="alert alert-secondary py-2">
    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
    Caricamento degli ipogei…
  </div>

  <div id="catageoMappa" class="catageo-mappa"
       data-catageo-geojson="index.php?p=geojson"
       data-catageo-perimetri="index.php?p=area-geojson"></div>

  <div class="catageo-mappa-stato mt-2 text-body-secondary">
    <span id="catageoMappaVisibili"></span>
  </div>

  <?php
  // La configurazione cartografica passa in un blocco JSON, non in codice
  // inline: cosi la Content-Security-Policy puo vietare gli script inline.
  ?>
  <script type="application/json" id="catageoMappaConfig"><?= Testo::escJson(Mappa::perBrowser()) ?></script>
  <script type="application/json" id="catageoMappaNature"><?= Testo::escJson($nature) ?></script>

<?php endif; ?>
