<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/ricerca.php
 *  Descrizione ..: Ricerca combinata e presentazione dei risultati in tabella,
 *                  schede o mappa (10).
 *
 *                  Tutto passa da GET e non da POST: una ricerca deve essere un
 *                  indirizzo condivisibile, aggiungibile ai preferiti e
 *                  ricaricabile senza che il browser chieda di reinviare i dati.
 *
 *                  Se il testo cercato e un codice — anche dismesso da una
 *                  migrazione — si va dritti alla scheda: e il caso d'uso piu
 *                  frequente, quello di chi ha in mano una pubblicazione.
 *  Versione .....: 1.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.1.0  2026-08-07  D.Candela  Filtri su prosecuzioni e verifica sul campo
 *                                (fase 12).
 *  0.14.0  2026-08-06  D.Candela  Selezione dei risultati per la migrazione.
 *  0.13.0  2026-08-06  D.Candela  Prima stesura (fase 8).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('ricerca');
require_once CATAGEO_ROOT . '/app/view/parti-avvisi.php';

$titolo = 'Ricerca';

// Si legge una volta sola in una variabile: scrivere il "??" nella condizione
// e poi l'accesso nudo nel ramo positivo lascia scoperto proprio il caso in cui
// la chiave manca, che e quello normale al primo ingresso nella pagina.
$vistaRichiesta = isset($_GET['vista']) ? (string) $_GET['vista'] : 'tabella';
$vista = in_array($vistaRichiesta, ['tabella', 'schede', 'mappa'], true)
    ? $vistaRichiesta
    : 'tabella';

/** Criteri presi dalla query, senza fidarsi delle chiavi. */
$criteri = [];
foreach (Ricerca::CRITERI as $chiave => $riposo) {
    if (!isset($_GET[$chiave])) {
        $criteri[$chiave] = $riposo;
        continue;
    }
    $criteri[$chiave] = is_array($riposo)
        ? (array) $_GET[$chiave]
        : (string) $_GET[$chiave];
}

$haCriteri = Ricerca::haCriteri($criteri);

// ============================================================================
//  SCORCIATOIA: il testo e un codice
// ============================================================================

/*
 * Si salta alla scheda solo se il codice e l'UNICO criterio: chi ha impostato
 * anche altri filtri sta costruendo una query, e portarlo altrove gli farebbe
 * perdere il lavoro fatto.
 */
$soloTesto = trim((string) $criteri['testo']) !== '';
foreach (Ricerca::CRITERI as $chiave => $riposo) {
    if (in_array($chiave, ['testo', 'ordina', 'verso', 'nelleDescrizioni'], true)) {
        continue;
    }
    $valore = $criteri[$chiave] ?? $riposo;
    if (is_array($valore) ? array_filter($valore) !== [] : trim((string) $valore) !== '' && (string) $valore !== '0') {
        $soloTesto = false;
        break;
    }
}

if ($soloTesto && empty($_GET['nosalto'])) {
    $risolto = Ricerca::risolviCodice((string) $criteri['testo']);
    if ($risolto !== null) {
        $scheda = Ipogeo::trova($risolto['codice']);
        $visibile = $scheda !== null && Visibilita::schedaVisibile(
            (string) $scheda['ubicazione']['riservatezza'],
            (string) $scheda['catasto']['statoScheda']
        );

        if ($visibile) {
            if ($risolto['storico']) {
                Auth::messaggio('info',
                    'Il codice "' . trim((string) $criteri['testo']) . '" e stato dismesso: '
                    . 'la scheda corrente e ' . $risolto['codice'] . '.');
            }
            header('Location: index.php?p=ipogei&azione=scheda&codice=' . urlencode($risolto['codice']));
            exit;
        }
    }
}

// ============================================================================
//  ESECUZIONE
// ============================================================================

$esito = $haCriteri
    ? Ricerca::esegui($criteri)
    : ['righe' => [], 'totale' => 0, 'esaminate' => 0, 'troncato' => false,
       'apertiPerSpecialistici' => 0, 'apertiPerDescrizioni' => 0, 'avvisi' => []];

$righe = $esito['righe'];

/** Query corrente senza la vista, per i collegamenti che la cambiano. */
$queryBase = $_GET;
unset($queryBase['vista'], $queryBase['p']);
$comeLink = static function (array $extra) use ($queryBase): string {
    return 'index.php?p=ricerca&' . http_build_query(array_merge($queryBase, $extra));
};

$cataloghi = Cataloghi::elenco();

$jsPagina = ['assets/js/catageo-ricerca.js'];

if ($vista === 'mappa' && $righe !== []) {
    $cssPagina = ['assets/vendor/leaflet-1.9.4/leaflet.css', 'assets/css/catageo-mappa.css'];
    $jsPagina[] = 'assets/vendor/leaflet-1.9.4/leaflet.js';
    $jsPagina[] = 'assets/js/catageo-mappa.js';
}
?>

<div class="catageo-intestazione">
  <div>
    <h1>Ricerca</h1>
    <p class="text-body-secondary mb-0">
      <?php if (!$haCriteri): ?>
        Imposta almeno un criterio. I criteri si combinano fra loro.
      <?php else: ?>
        <?= $esito['totale'] ?> risultat<?= $esito['totale'] === 1 ? 'o' : 'i' ?>
        su <?= $esito['esaminate'] ?> schede esaminate
      <?php endif; ?>
    </p>
  </div>
  <?php if ($haCriteri && $righe !== []): ?>
    <div class="d-flex gap-2 flex-wrap catageo-non-stampare">
      <div class="btn-group" role="group" aria-label="Vista dei risultati">
        <?php foreach (['tabella' => 'bi-table', 'schede' => 'bi-grid', 'mappa' => 'bi-map'] as $v => $icona): ?>
          <a class="btn btn-sm <?= $vista === $v ? 'btn-primary' : 'btn-outline-secondary' ?>"
             href="<?= Testo::esc($comeLink(['vista' => $v])) ?>">
            <i class="bi <?= $icona ?>"></i> <?= ucfirst($v) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="btn-group" role="group" aria-label="Esportazione">
        <?php foreach (['csv' => 'CSV', 'geojson' => 'GeoJSON', 'kml' => 'KML'] as $formato => $etichetta): ?>
          <a class="btn btn-sm btn-outline-secondary"
             href="<?= Testo::esc('index.php?p=esporta&formato=' . $formato . '&'
                 . http_build_query($queryBase)) ?>">
            <i class="bi bi-download"></i> <?= $etichetta ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================ modulo -->
<form method="get" action="index.php" class="card mb-4">
  <input type="hidden" name="p" value="ricerca">
  <input type="hidden" name="vista" value="<?= Testo::esc($vista) ?>">

  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-8">
        <label for="testo" class="form-label">Testo</label>
        <input type="search" class="form-control form-control-lg" id="testo" name="testo"
               value="<?= Testo::esc((string) $criteri['testo']) ?>"
               placeholder="Nome, codice anche storico, comune, localita">
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="nelleDescrizioni"
                 name="nelleDescrizioni" value="1"
                 <?= (string) $criteri['nelleDescrizioni'] === '1' ? 'checked' : '' ?>>
          <label class="form-check-label" for="nelleDescrizioni">
            Cerca anche nelle descrizioni
            <div class="catageo-nota">Piu lenta: apre le schede una per una.</div>
          </label>
        </div>
      </div>
    </div>

    <div class="accordion mt-3" id="catageoFiltri">

      <!-- ------------------------------------------------- attributi -->
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#filtriAttributi">
            Dove e cosa
          </button>
        </h2>
        <div id="filtriAttributi" class="accordion-collapse collapse" data-bs-parent="#catageoFiltri">
          <div class="accordion-body">
            <div class="row g-3">
              <?php if (count($cataloghi) > 1): ?>
                <div class="col-md-3">
                  <label for="cataloghi" class="form-label">Cataloghi</label>
                  <select class="form-select" id="cataloghi" name="cataloghi[]" multiple size="4">
                    <?php foreach ($cataloghi as $c): ?>
                      <option value="<?= Testo::esc((string) $c['sigla']) ?>"
                        <?= in_array(strtoupper((string) $c['sigla']),
                              array_map('strtoupper', (array) $criteri['cataloghi']), true) ? 'selected' : '' ?>>
                        <?= Testo::esc((string) $c['sigla'] . ' — ' . (string) $c['nome']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="catageo-nota">Nessuna scelta = tutti.</div>
                </div>
              <?php endif; ?>

              <div class="col-md-3">
                <label for="natura" class="form-label">Natura</label>
                <select class="form-select" id="natura" name="natura">
                  <option value="">Tutte</option>
                  <?php foreach (Tipologie::perLivello('natura') as $n): ?>
                    <option value="<?= Testo::esc((string) $n['codice']) ?>"
                      <?= (string) $criteri['natura'] === (string) $n['codice'] ? 'selected' : '' ?>>
                      <?= Testo::esc((string) $n['nome']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-3">
                <label for="regione" class="form-label">Regione</label>
                <input type="text" class="form-control" id="regione" name="regione"
                       value="<?= Testo::esc((string) $criteri['regione']) ?>">
              </div>
              <div class="col-md-1">
                <label for="provincia" class="form-label">Prov.</label>
                <input type="text" class="form-control" id="provincia" name="provincia"
                       maxlength="2" value="<?= Testo::esc((string) $criteri['provincia']) ?>">
              </div>
              <div class="col-md-2">
                <label for="comune" class="form-label">Comune</label>
                <input type="text" class="form-control" id="comune" name="comune"
                       value="<?= Testo::esc((string) $criteri['comune']) ?>">
              </div>
              <?php
              // L'area sta accanto ai criteri amministrativi e non al posto
              // loro: sono modi diversi di collocare la stessa cavita, e chi
              // cerca puo volerli combinare.
              $areeDisponibili = Aree::elenco(true);
              ?>
              <?php if ($areeDisponibili !== []): ?>
                <div class="col-md-3">
                  <label for="area" class="form-label">Area speleologica</label>
                  <select class="form-select" id="area" name="area">
                    <option value="">Qualunque</option>
                    <?php foreach ($areeDisponibili as $areaVoce): ?>
                      <option value="<?= Testo::esc((string) $areaVoce['id']) ?>"
                              <?= (string) $criteri['area'] === (string) $areaVoce['id'] ? 'selected' : '' ?>>
                        <?= Testo::esc(Aree::etichetta($areaVoce)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

              <?php $complessiDisponibili = Complessi::elenco(true); ?>
              <?php if ($complessiDisponibili !== []): ?>
                <div class="col-md-3">
                  <label for="complesso" class="form-label">Complesso</label>
                  <select class="form-select" id="complesso" name="complesso">
                    <option value="">Qualunque</option>
                    <?php foreach ($complessiDisponibili as $cx): ?>
                      <option value="<?= Testo::esc((string) $cx['id']) ?>"
                              <?= (string) $criteri['complesso'] === (string) $cx['id'] ? 'selected' : '' ?>>
                        <?= Testo::esc(Complessi::etichetta($cx)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

              <div class="col-md-3">
                <label for="statoAccesso" class="form-label">Stato di accesso</label>
                <select class="form-select" id="statoAccesso" name="statoAccesso">
                  <option value="">Qualunque</option>
                  <?php foreach (['aperto', 'chiuso', 'interrato', 'distrutto', 'non_localizzato'] as $sa): ?>
                    <option value="<?= $sa ?>" <?= (string) $criteri['statoAccesso'] === $sa ? 'selected' : '' ?>>
                      <?= Testo::esc(str_replace('_', ' ', $sa)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label for="statoScheda" class="form-label">Stato della scheda</label>
                <select class="form-select" id="statoScheda" name="statoScheda">
                  <option value="">Qualunque</option>
                  <?php foreach (['bozza', 'pubblicata', 'verificata'] as $ss): ?>
                    <option value="<?= $ss ?>" <?= (string) $criteri['statoScheda'] === $ss ? 'selected' : '' ?>>
                      <?= Testo::esc($ss) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- --------------------------------------- stato esplorativo -->
      <?php
      /*
       * Il riquadro sta in cima e non in fondo: e la ricerca che si fa quando
       * si programma un'uscita, ed e il motivo per cui questi campi esistono
       * (9.17.1). Sepolta sotto "dimensioni e quota" non la userebbe nessuno.
       */
      ?>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#filtriEsplorazione">
            Stato esplorativo, percorribilita e verifica sul campo
          </button>
        </h2>
        <div id="filtriEsplorazione" class="accordion-collapse collapse" data-bs-parent="#catageoFiltri">
          <div class="accordion-body">
            <div class="row g-3">
              <?php foreach ([
                  'prosegue'  => ['Prosecuzioni note', 'Le cavita dove c\'e ancora da andare.'],
                  'esplorata' => ['Esplorazione conclusa', ''],
              ] as $campo => [$etichetta, $nota]): ?>
                <div class="col-md-3">
                  <label for="f_<?= $campo ?>" class="form-label"><?= Testo::esc($etichetta) ?></label>
                  <select class="form-select" id="f_<?= $campo ?>" name="<?= $campo ?>">
                    <option value="">Qualunque</option>
                    <option value="si"     <?= (string) $criteri[$campo] === 'si' ? 'selected' : '' ?>>si</option>
                    <option value="no"     <?= (string) $criteri[$campo] === 'no' ? 'selected' : '' ?>>no</option>
                    <option value="ignoto" <?= (string) $criteri[$campo] === 'ignoto' ? 'selected' : '' ?>>non si sa</option>
                  </select>
                  <?php if ($nota !== ''): ?>
                    <div class="catageo-nota"><?= Testo::esc($nota) ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>

              <div class="col-md-3">
                <label for="posVerificata" class="form-label">Posizione verificata</label>
                <select class="form-select" id="posVerificata" name="posVerificata">
                  <option value="">Qualunque</option>
                  <option value="si" <?= (string) $criteri['posVerificata'] === 'si' ? 'selected' : '' ?>>si</option>
                  <option value="no" <?= (string) $criteri['posVerificata'] === 'no' ? 'selected' : '' ?>>no</option>
                </select>
              </div>

              <?php
              // Percorribilita strutturata (9.17.7): sta qui e non fra le
              // dimensioni perche risponde alla stessa domanda dello stato
              // esplorativo — si puo andarci, e come?
              ?>
              <div class="col-md-3">
                <label for="grado" class="form-label">Grado di progressione</label>
                <select class="form-select" id="grado" name="grado">
                  <option value="">Qualunque</option>
                  <?php foreach (Ipogeo::GRADI_PROGRESSIONE as $valore => $etichetta): ?>
                    <option value="<?= Testo::esc((string) $valore) ?>"
                            <?= (string) $criteri['grado'] === (string) $valore ? 'selected' : '' ?>>
                      <?= Testo::esc($etichetta) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label for="gradoIdrico" class="form-label">Difficolta idriche</label>
                <select class="form-select" id="gradoIdrico" name="gradoIdrico">
                  <option value="">Qualunque</option>
                  <?php foreach (Ipogeo::GRADI_IDRICI as $valore => $etichetta): ?>
                    <option value="<?= Testo::esc((string) $valore) ?>"
                            <?= (string) $criteri['gradoIdrico'] === (string) $valore ? 'selected' : '' ?>>
                      <?= Testo::esc($etichetta) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label for="armo" class="form-label">Necessita armo</label>
                <select class="form-select" id="armo" name="armo">
                  <option value="">Qualunque</option>
                  <option value="si" <?= (string) $criteri['armo'] === 'si' ? 'selected' : '' ?>>si</option>
                  <option value="no" <?= (string) $criteri['armo'] === 'no' ? 'selected' : '' ?>>no</option>
                </select>
              </div>

              <div class="col-md-3">
                <label for="nonVerificataDaAnni" class="form-label">Non verificata da (anni)</label>
                <input type="number" class="form-control" id="nonVerificataDaAnni"
                       name="nonVerificataDaAnni" min="0" max="200" step="1"
                       value="<?= Testo::esc((string) $criteri['nonVerificataDaAnni']) ?>">
                <div class="catageo-nota">
                  Le mai verificate rientrano sempre: sono il caso piu vecchio.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ------------------------------------------------- grandezze -->
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#filtriMisure">
            Dimensioni, quota e data
          </button>
        </h2>
        <div id="filtriMisure" class="accordion-collapse collapse" data-bs-parent="#catageoFiltri">
          <div class="accordion-body">
            <div class="row g-3">
              <?php foreach ([
                  ['Sviluppo (m)', 'sviluppoMin', 'sviluppoMax'],
                  ['Dislivello (m)', 'dislivelloMin', 'dislivelloMax'],
                  ['Quota (m)', 'quotaMin', 'quotaMax'],
              ] as [$etichetta, $min, $max]): ?>
                <div class="col-md-4">
                  <label class="form-label"><?= $etichetta ?></label>
                  <div class="input-group">
                    <input type="text" class="form-control catageo-valore" name="<?= $min ?>"
                           value="<?= Testo::esc((string) $criteri[$min]) ?>" placeholder="da"
                           inputmode="decimal">
                    <input type="text" class="form-control catageo-valore" name="<?= $max ?>"
                           value="<?= Testo::esc((string) $criteri[$max]) ?>" placeholder="a"
                           inputmode="decimal">
                  </div>
                </div>
              <?php endforeach; ?>

              <div class="col-md-3">
                <label for="censitoDal" class="form-label">Censito dal</label>
                <input type="date" class="form-control" id="censitoDal" name="censitoDal"
                       value="<?= Testo::esc((string) $criteri['censitoDal']) ?>">
              </div>
              <div class="col-md-3">
                <label for="censitoAl" class="form-label">Censito al</label>
                <input type="date" class="form-control" id="censitoAl" name="censitoAl"
                       value="<?= Testo::esc((string) $criteri['censitoAl']) ?>">
              </div>

              <div class="col-12">
                <div class="catageo-nota">
                  Un ipogeo senza il dato non compare quando si filtra su quel dato:
                  altrimenti i risultati si riempirebbero di schede di cui non si sa
                  nulla proprio sul criterio scelto.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- -------------------------------------------------- contenuti -->
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#filtriContenuti">
            Contenuti presenti
          </button>
        </h2>
        <div id="filtriContenuti" class="accordion-collapse collapse" data-bs-parent="#catageoFiltri">
          <div class="accordion-body">
            <div class="row g-2">
              <?php foreach (Ricerca::PRESENZE as $colonna => $etichetta): ?>
                <div class="col-md-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="presenze[]"
                           id="pres_<?= $colonna ?>" value="<?= $colonna ?>"
                           <?= in_array($colonna, (array) $criteri['presenze'], true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="pres_<?= $colonna ?>">
                      <?= Testo::esc($etichetta) ?>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ---------------------------------------------- specialistici -->
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#filtriSpecialistici">
            Scienza, biologia, archeologia
          </button>
        </h2>
        <div id="filtriSpecialistici" class="accordion-collapse collapse" data-bs-parent="#catageoFiltri">
          <div class="accordion-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label for="grandezza" class="form-label">Grandezza misurata</label>
                <select class="form-select" id="grandezza" name="grandezza">
                  <option value="">Qualunque</option>
                  <?php foreach (Grandezze::categorie(true) as $categoria): ?>
                    <optgroup label="<?= Testo::esc((string) $categoria['nome']) ?>">
                      <?php foreach ($categoria['grandezze'] as $g): ?>
                        <option value="<?= Testo::esc((string) $g['codice']) ?>"
                          <?= (string) $criteri['grandezza'] === (string) $g['codice'] ? 'selected' : '' ?>>
                          <?= Testo::esc((string) $g['nome']) ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-4">
                <label for="specie" class="form-label">Specie osservata</label>
                <input type="text" class="form-control fst-italic" id="specie" name="specie"
                       value="<?= Testo::esc((string) $criteri['specie']) ?>"
                       placeholder="Rhinolophus">
                <div class="catageo-nota">
                  Le colonie non consultabili non contribuiscono al risultato.
                </div>
              </div>

              <div class="col-md-4">
                <label for="periodo" class="form-label">Periodo archeologico</label>
                <select class="form-select" id="periodo" name="periodo">
                  <option value="">Qualunque</option>
                  <?php foreach (Periodi::elenco(true) as $p): ?>
                    <option value="<?= Testo::esc((string) $p['codice']) ?>"
                      <?= (string) $criteri['periodo'] === (string) $p['codice'] ? 'selected' : '' ?>>
                      <?= Testo::esc(Periodi::etichetta($p)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">Intervallo di anni</label>
                <div class="input-group">
                  <input type="text" class="form-control catageo-valore" name="annoDa"
                         value="<?= Testo::esc((string) $criteri['annoDa']) ?>" placeholder="-100">
                  <input type="text" class="form-control catageo-valore" name="annoA"
                         value="<?= Testo::esc((string) $criteri['annoA']) ?>" placeholder="100">
                </div>
                <div class="catageo-nota">
                  Negativi per le date avanti Cristo. Trova anche chi ha dichiarato
                  solo il periodo, usando gli estremi del vocabolario.
                </div>
              </div>

              <div class="col-md-4 d-flex align-items-center">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="conVincolo"
                         name="conVincolo" value="1"
                         <?= (string) $criteri['conVincolo'] === '1' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="conVincolo">
                    Solo cavita sottoposte a vincolo
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ------------------------------------------------- geografica -->
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#filtriGeo">
            Vicino a un punto
          </button>
        </h2>
        <div id="filtriGeo" class="accordion-collapse collapse" data-bs-parent="#catageoFiltri">
          <div class="accordion-body">
            <div class="row g-3">
              <div class="col-md-3">
                <label for="latitudine" class="form-label">Latitudine</label>
                <input type="text" class="form-control catageo-valore" id="latitudine"
                       name="latitudine" value="<?= Testo::esc((string) $criteri['latitudine']) ?>"
                       placeholder="41.856231" inputmode="decimal">
              </div>
              <div class="col-md-3">
                <label for="longitudine" class="form-label">Longitudine</label>
                <input type="text" class="form-control catageo-valore" id="longitudine"
                       name="longitudine" value="<?= Testo::esc((string) $criteri['longitudine']) ?>"
                       placeholder="12.532104" inputmode="decimal">
              </div>
              <div class="col-md-3">
                <label for="raggio" class="form-label">Raggio (m)</label>
                <input type="text" class="form-control catageo-valore" id="raggio" name="raggio"
                       value="<?= Testo::esc((string) $criteri['raggio']) ?>"
                       placeholder="<?= Ricerca::RAGGIO_PREDEFINITO ?>" inputmode="numeric">
                <div class="catageo-nota">Massimo <?= (int) (Ricerca::RAGGIO_MASSIMO / 1000) ?> km.</div>
              </div>
              <div class="col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-outline-secondary w-100"
                        id="catageoUsaPosizione">
                  <i class="bi bi-crosshair"></i> Usa la mia posizione
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3 flex-wrap">
      <div class="d-flex gap-2 align-items-center">
        <label for="ordina" class="form-label mb-0">Ordina per</label>
        <select class="form-select form-select-sm w-auto" id="ordina" name="ordina">
          <?php foreach (Ricerca::ORDINAMENTI as $valore => $etichetta): ?>
            <option value="<?= $valore ?>" <?= (string) $criteri['ordina'] === $valore ? 'selected' : '' ?>>
              <?= Testo::esc($etichetta) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select class="form-select form-select-sm w-auto" name="verso">
          <option value="asc" <?= (string) $criteri['verso'] !== 'desc' ? 'selected' : '' ?>>crescente</option>
          <option value="desc" <?= (string) $criteri['verso'] === 'desc' ? 'selected' : '' ?>>decrescente</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="bi bi-search"></i> Cerca
      </button>
      <?php if ($haCriteri): ?>
        <a class="btn btn-outline-secondary" href="index.php?p=ricerca">
          <i class="bi bi-x-lg"></i> Azzera
        </a>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php foreach ($esito['avvisi'] as $avviso): ?>
  <div class="alert alert-warning d-flex align-items-start gap-2">
    <i class="bi bi-info-circle-fill mt-1" aria-hidden="true"></i>
    <div><?= Testo::esc($avviso) ?></div>
  </div>
<?php endforeach; ?>

<?php if (!$haCriteri): ?>

  <div class="card">
    <div class="card-body d-flex gap-3">
      <i class="bi bi-search fs-3 text-body-secondary" aria-hidden="true"></i>
      <div>
        <h2 class="h6 mb-1">Nessuna ricerca impostata</h2>
        <p class="text-body-secondary mb-0">
          Digitando un codice — anche uno dismesso da una migrazione — si va
          dritti alla scheda. Gli altri criteri si combinano fra loro.
        </p>
      </div>
    </div>
  </div>

<?php elseif ($righe === []): ?>

  <div class="card">
    <div class="card-body d-flex gap-3">
      <i class="bi bi-slash-circle fs-3 text-body-secondary" aria-hidden="true"></i>
      <div>
        <h2 class="h6 mb-1">Nessun risultato</h2>
        <p class="text-body-secondary mb-0">
          Esaminate <?= $esito['esaminate'] ?> schede: nessuna soddisfa tutti i
          criteri, che si combinano fra loro.
        </p>
      </div>
    </div>
  </div>

<?php elseif ($vista === 'tabella'): ?>

  <?php $puoMigrare = Auth::puo('migra_catalogo'); ?>
  <form method="get" action="index.php">
  <input type="hidden" name="p" value="migrazione">

  <div class="card">
    <div class="table-responsive">
      <table class="table catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <?php if ($puoMigrare): ?>
              <th style="width:2.5rem">
                <span class="visually-hidden">Selezione</span>
              </th>
            <?php endif; ?>
            <th style="width:6rem">Codice</th>
            <th>Nome</th>
            <th>Tipologia</th>
            <th>Comune</th>
            <th class="text-end">Sviluppo</th>
            <th class="text-end">Disl.</th>
            <?php if (isset($righe[0]['distanza'])): ?>
              <th class="text-end">Distanza</th>
            <?php endif; ?>
            <th>Contenuti</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($righe as $riga): ?>
            <tr>
              <?php if ($puoMigrare): ?>
                <td>
                  <input class="form-check-input" type="checkbox" name="codici[]"
                         value="<?= Testo::esc((string) $riga['codice']) ?>"
                         aria-label="Seleziona <?= Testo::esc((string) $riga['codice']) ?>">
                </td>
              <?php endif; ?>
              <td>
                <a href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode((string) $riga['codice']) ?>">
                  <span class="catageo-codice"><?= Testo::esc((string) $riga['codice']) ?></span>
                </a>
              </td>
              <td>
                <?= Testo::esc((string) $riga['nome']) ?>
                <?php if ((string) $riga['stato_scheda'] === 'bozza'): ?>
                  <span class="badge text-bg-secondary">bozza</span>
                <?php endif; ?>
              </td>
              <td><?= Testo::esc(Tipologie::nome((string) $riga['tipologia'])) ?></td>
              <td>
                <?= Testo::esc((string) $riga['comune']) ?>
                <?php if ((string) $riga['provincia'] !== ''): ?>
                  <span class="catageo-nota">(<?= Testo::esc((string) $riga['provincia']) ?>)</span>
                <?php endif; ?>
              </td>
              <td class="text-end catageo-valore"><?= Testo::esc((string) $riga['sviluppo']) ?></td>
              <td class="text-end catageo-valore"><?= Testo::esc((string) $riga['dislivello']) ?></td>
              <?php if (isset($righe[0]['distanza'])): ?>
                <td class="text-end catageo-valore">
                  <?= isset($riga['distanza']) ? Testo::esc(Geo::distanzaLeggibile((float) $riga['distanza'])) : '' ?>
                </td>
              <?php endif; ?>
              <td class="catageo-nota">
                <?php foreach (['n_foto' => 'bi-image', 'n_rilievi' => 'bi-bounding-box',
                                'n_esplorazioni' => 'bi-journal-text', 'n_biblio' => 'bi-book',
                                'n_serie_misure' => 'bi-graph-up'] as $colonna => $icona): ?>
                  <?php if ((int) ($riga[$colonna] ?? 0) > 0): ?>
                    <span class="me-2" title="<?= Testo::esc(Ricerca::PRESENZE[$colonna] ?? '') ?>">
                      <i class="bi <?= $icona ?>"></i> <?= (int) $riga[$colonna] ?>
                    </span>
                  <?php endif; ?>
                <?php endforeach; ?>
                <?php if ((string) ($riga['ha_chirotteri'] ?? '') === '1'): ?>
                  <i class="bi bi-bug" title="Colonie di chirotteri"></i>
                <?php endif; ?>
                <?php if ((string) ($riga['ha_archeologia'] ?? '') === '1'): ?>
                  <i class="bi bi-bank" title="Dati archeologici"></i>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($puoMigrare): ?>
      <div class="card-body catageo-non-stampare">
        <button type="submit" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left-right"></i> Migra i selezionati
        </button>
        <span class="catageo-nota ms-2">
          Porta alla schermata di anteprima: da li si sceglie il catalogo e si
          vedono i codici che verrebbero assegnati, prima di confermare.
        </span>
      </div>
    <?php endif; ?>
  </div>
  </form>

<?php elseif ($vista === 'schede'): ?>

  <div class="row g-3">
    <?php foreach ($righe as $riga): ?>
      <?php
      $codiceRiga = (string) $riga['codice'];
      $copertina = Risorse::copertina($codiceRiga);
      // L'avviso di periodo critico compare anche qui: chi sceglie una cavita
      // da un elenco di risultati deve vederlo prima di programmare l'uscita,
      // non solo aprendo la scheda.
      $avvisiRiga = catageoAvvisiDi($codiceRiga);
      ?>
      <div class="col-md-6 col-xl-4">
        <div class="card h-100">
          <?php if ($copertina !== null): ?>
            <img class="card-img-top catageo-copertina-ricerca" loading="lazy"
                 src="<?= Testo::esc('scarica.php?codice=' . urlencode($codiceRiga)
                     . '&sez=FO&prog=' . (int) $copertina['progressivo'] . '&mini=1&inline=1') ?>"
                 alt="">
          <?php endif; ?>
          <div class="card-body">
            <h2 class="h6 mb-1">
              <a href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode($codiceRiga) ?>">
                <?= Testo::esc((string) $riga['nome']) ?>
              </a>
            </h2>
            <div class="catageo-nota mb-2">
              <span class="catageo-codice"><?= Testo::esc($codiceRiga) ?></span>
              · <?= Testo::esc(Tipologie::nome((string) $riga['tipologia'])) ?>
            </div>

            <?php if ($avvisiRiga !== []): ?>
              <?php foreach ($avvisiRiga as $avviso): ?>
                <div class="badge text-bg-<?= $avviso['livello'] === 'danger' ? 'danger' : 'warning' ?> mb-1 text-wrap text-start">
                  <i class="bi bi-exclamation-triangle-fill"></i>
                  <?= Testo::esc($avviso['titolo']) ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <dl class="row catageo-dl mb-0 small">
              <dt class="col-5 fw-normal text-body-secondary">Comune</dt>
              <dd class="col-7"><?= Testo::esc((string) $riga['comune']) ?></dd>
              <dt class="col-5 fw-normal text-body-secondary">Sviluppo</dt>
              <dd class="col-7"><?= Testo::esc((string) $riga['sviluppo']) ?: '—' ?></dd>
              <?php if (isset($riga['distanza'])): ?>
                <dt class="col-5 fw-normal text-body-secondary">Distanza</dt>
                <dd class="col-7"><?= Testo::esc(Geo::distanzaLeggibile((float) $riga['distanza'])) ?></dd>
              <?php endif; ?>
            </dl>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php else: ?>

  <div class="card">
    <div class="card-body">
      <!-- Si riusa la vista di elenco della mappa, con raggruppamento,
           legenda e popup, alimentandola con lo stesso endpoint di export:
           il percorso "tracciato" disegnerebbe pallini senza informazioni. -->
      <div id="catageoMappa" class="catageo-mappa"
           data-catageo-geojson="<?= Testo::esc('index.php?p=esporta&formato=geojson&' . http_build_query($queryBase)) ?>"></div>
      <div class="catageo-nota mt-2">
        <?php
        $geo = Esportazione::geojson($righe);
        ?>
        <?= (int) $geo['catageo']['totale'] ?> risultati sulla mappa<?php
        if ((int) $geo['catageo']['senzaCoordinate'] > 0): ?>,
          <?= (int) $geo['catageo']['senzaCoordinate'] ?> senza coordinate e quindi
          non rappresentabili<?php endif; ?>.
      </div>
    </div>
  </div>

  <script type="application/json" id="catageoMappaConfig"><?= Testo::escJson(Mappa::perBrowser()) ?></script>

<?php endif; ?>

<?php if ($haCriteri && ($esito['apertiPerSpecialistici'] > 0 || $esito['apertiPerDescrizioni'] > 0)): ?>
  <p class="catageo-nota mt-3">
    <?php if ($esito['apertiPerSpecialistici'] > 0): ?>
      Criteri specialistici risolti aprendo <?= $esito['apertiPerSpecialistici'] ?>
      schede fra quelle sopravvissute agli altri filtri.
    <?php endif; ?>
  </p>
<?php endif; ?>
