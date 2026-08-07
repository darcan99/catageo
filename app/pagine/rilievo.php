<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/rilievo.php
 *  Descrizione ..: Vista di un singolo rilievo: documento 2D, modello 3D o
 *                  tracciato sulla mappa, secondo cosa il file sa fare (9.4).
 *
 *                  Sta in una pagina propria e non in una finestra perche un
 *                  rilievo si guarda a lungo: ci si gira intorno, si legge la
 *                  scala, si confronta con la mappa. Una finestra che copre il
 *                  resto sarebbe d'intralcio, non d'aiuto.
 *
 *                  Cosa si puo fare con un file dipende dal suo formato e non
 *                  da una casella: un DXF non diventa navigabile spuntando
 *                  un'opzione. I formati topografici specialistici (Therion,
 *                  Survex, VisualTopo, Compass) restano archiviati e scaricabili,
 *                  con l'indicazione di cosa esportare per vederli.
 *  Versione .....: 0.8.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.8.0  2026-08-05  D.Candela  Prima stesura (fase 6).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');
require_once CATAGEO_ROOT . '/app/view/parti-media.php';

$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';
$prog   = isset($_GET['prog']) ? (int) $_GET['prog'] : 0;

$risoluzione = $codice === '' ? null : Ipogeo::risolvi($codice);
if ($risoluzione === null || $prog < 1) {
    Auth::messaggio('errore', 'Rilievo non indicato correttamente.');
    header('Location: index.php?p=ipogei');
    exit;
}

$scheda = $risoluzione['scheda'];
$codice = $risoluzione['codiceCorrente'];

if (!Visibilita::schedaVisibile(
    (string) $scheda['ubicazione']['riservatezza'],
    (string) $scheda['catasto']['statoScheda']
)) {
    Auth::messaggio('errore', 'La scheda richiesta non è consultabile con il livello di utenza in uso.');
    header('Location: index.php?p=ipogei');
    exit;
}

$rilievo = Risorse::trova($codice, 'RI', $prog);
if ($rilievo === null) {
    Auth::messaggio('errore', 'Rilievo non trovato.');
    header('Location: index.php?p=risorse&codice=' . urlencode($codice) . '&sez=RI');
    exit;
}

if ((string) $rilievo['riservatezza'] === 'riservata' && !Auth::puo('vedi_riservati')) {
    Auth::messaggio('errore', 'Rilievo riservato: non consultabile con il livello di utenza in uso.');
    header('Location: index.php?p=risorse&codice=' . urlencode($codice) . '&sez=RI');
    exit;
}

$presente   = Risorse::percorsoFile($codice, 'RI', $prog) !== null;
$estensione = Risorse::estensione($rilievo);
$in3d       = $presente && Risorse::tridimensionale($rilievo);
$in2d       = $presente && Risorse::bidimensionale($rilievo);
$inMappa    = $presente && Tracciato::convertibile((string) $rilievo['file']);

$titolo = 'Rilievo ' . Sezioni::riferimento('RI', $prog) . ' — ' . $codice;
$urlFile = catageoUrlRisorsa($codice, 'RI', $prog);
$urlVedi = catageoUrlRisorsa($codice, 'RI', $prog, false, true);

// Il visualizzatore e un modulo ES: i caricatori di three.js lo sono, e i loro
// import sono stati riscritti per puntare al file locale invece che a un nome
// nudo, che senza import map il browser non saprebbe risolvere.
if ($in3d) {
    $jsModuli = ['assets/js/catageo-3d.js'];
}

if ($inMappa) {
    $cssPagina = array_merge($cssPagina ?? [], [
        'assets/vendor/leaflet-1.9.4/leaflet.css',
        'assets/css/catageo-mappa.css',
    ]);
    $jsPagina = array_merge($jsPagina ?? [], Mappa::scriptBrowser());
}
?>

<div class="catageo-intestazione">
  <div>
    <h1><?= Testo::esc((string) $rilievo['titolo']) ?></h1>
    <p class="text-body-secondary mb-0">
      <span class="catageo-valore"><?= Testo::esc(Sezioni::riferimento('RI', $prog)) ?></span>
      · <span class="catageo-codice"><?= Testo::esc($codice) ?></span>
      <?= Testo::esc((string) $scheda['identificazione']['nome']) ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <?php if ($presente): ?>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($urlFile) ?>">
        <i class="bi bi-download"></i> Scarica
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary"
       href="index.php?p=risorse&amp;codice=<?= urlencode($codice) ?>&amp;sez=RI">
      <i class="bi bi-arrow-left"></i> Rilievi
    </a>
  </div>
</div>

<?php if (!$presente): ?>

  <div class="alert alert-danger d-flex align-items-start gap-2">
    <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
    <div>
      Il file <span class="catageo-valore"><?= Testo::esc((string) $rilievo['file']) ?></span>
      e registrato nell'indice ma non è presente nella cartella dell'archivio.
    </div>
  </div>

<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">

    <?php if ($in3d): ?>

      <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h2 class="h6 mb-0">Modello tridimensionale</h2>
          <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="catageo3dFilo"
                    title="Mostra il modello a filo di ferro">
              <i class="bi bi-bounding-box"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="catageo3dAssi"
                    title="Mostra gli assi di riferimento">
              <i class="bi bi-arrows-move"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="catageo3dSchermo"
                    title="Schermo intero">
              <i class="bi bi-arrows-fullscreen"></i>
            </button>
          </div>
        </div>
        <div class="card-body">
          <div id="catageo3dMessaggio" class="alert alert-secondary py-2" hidden></div>

          <?php
          // Il modello NON si carica all'apertura: una nuvola di punti puo
          // pesare decine di megabyte, e chi apre la scheda per leggere la
          // scala non deve scaricarla.
          ?>
          <button type="button" class="btn btn-primary mb-3" id="catageo3dCarica">
            <i class="bi bi-badge-3d"></i>
            Carica il modello (<?= Testo::esc(Testo::dimensione((int) $rilievo['dimensione'])) ?>)
          </button>

          <div id="catageo3d" class="catageo-3d"
               data-modello="<?= Testo::esc($urlVedi) ?>"
               data-formato="<?= Testo::esc($estensione) ?>"></div>

          <div class="catageo-nota mt-2" id="catageo3dDati"></div>
          <div class="catageo-nota">
            Trascinare per ruotare, rotella per avvicinare, tasto destro per spostare.
          </div>
        </div>
      </div>

    <?php elseif ($in2d && $estensione === 'pdf'): ?>

      <div class="card mb-4">
        <div class="card-header"><h2 class="h6 mb-0">Documento</h2></div>
        <div class="card-body">
          <?php
          // Il PDF si mostra con il visualizzatore nativo del browser: e gia
          // installato ovunque, sa cercare e stampare, ed evita di portarsi
          // dietro una libreria di rendering.
          ?>
          <iframe class="catageo-documento" src="<?= Testo::esc($urlVedi) ?>"
                  title="<?= Testo::esc((string) $rilievo['titolo']) ?>"></iframe>
        </div>
      </div>

    <?php elseif ($in2d): ?>

      <div class="card mb-4">
        <div class="card-header"><h2 class="h6 mb-0">Rilievo</h2></div>
        <div class="card-body">
          <a href="<?= Testo::esc($urlVedi) ?>" target="_blank" rel="noopener"
             title="Apri a dimensione piena">
            <img class="catageo-rilievo-immagine" src="<?= Testo::esc($urlVedi) ?>"
                 alt="<?= Testo::esc((string) $rilievo['titolo']) ?>">
          </a>
        </div>
      </div>

    <?php elseif ($presente && !$inMappa): ?>

      <?php
      // Formati topografici specialistici: si conservano e si scaricano, ma
      // scrivere in PHP un lettore di Therion o Compass non sarebbe sostenibile.
      // Meglio dirlo, e dire cosa esportare per vedere il rilievo qui dentro.
      $specialistici = [
          'th' => 'Therion', 'th2' => 'Therion', '3d' => 'Survex',
          'tro' => 'VisualTopo', 'plt' => 'Compass', 'dxf' => 'DXF', 'dwg' => 'DWG',
      ];
      $nomeFormato = $specialistici[$estensione] ?? strtoupper($estensione);
      ?>
      <div class="card mb-4">
        <div class="card-body d-flex gap-3">
          <i class="bi bi-file-earmark-binary fs-3 text-body-secondary" aria-hidden="true"></i>
          <div>
            <h2 class="h6 mb-1">Formato <?= Testo::esc($nomeFormato) ?>: archiviato e scaricabile</h2>
            <p class="text-body-secondary mb-2">
              Questo formato si conserva nell'archivio con tutti i suoi metadati, ma non
              si visualizza qui dentro: leggerlo richiederebbe di riscrivere in PHP il
              programma che lo ha prodotto.
            </p>
            <p class="catageo-nota mb-0">
              Per vedere il rilievo in CATAGEO, esportarlo dal programma di origine in
              <strong>KML</strong> o <strong>GPX</strong> per la mappa, oppure in
              <strong>PLY</strong> o <strong>OBJ</strong> per il modello tridimensionale,
              e caricare anche quel file. I due file convivono nella stessa sezione.
            </p>
          </div>
        </div>
      </div>

    <?php endif; ?>

    <?php if ($inMappa): ?>
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">Tracciato sulla mappa</h2>
          <span class="catageo-nota" id="catageoTracciatoStato"></span>
        </div>
        <div class="card-body">
          <div id="catageoMappa" class="catageo-mappa"
               data-catageo-tracciato="index.php?p=tracciato&amp;codice=<?= urlencode($codice) ?>&amp;prog=<?= $prog ?>"></div>
        </div>
      </div>
    <?php endif; ?>

  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0">Dati del rilievo</h2></div>
      <div class="card-body">
        <dl class="row catageo-dl mb-0">
          <?php
          $voci = [
              'File'         => (string) $rilievo['file'],
              'Formato'      => catageoTipoFile($rilievo),
              'Dimensione'   => Testo::dimensione((int) $rilievo['dimensione']),
              'Tipo'         => (string) $rilievo['tipoRilievo'],
              'Scala'        => (string) $rilievo['scala'],
              'Sistema di riferimento' => (string) $rilievo['sistemaRiferimento'],
              'Data del rilievo'       => (string) $rilievo['dataRilievo'],
              'Strumentazione'         => (string) $rilievo['strumentazione'],
              'Rilevatori'             => (string) $rilievo['rilevatori'],
              'Gruppo'       => (string) $rilievo['gruppoId'] !== ''
                  ? Gruppi::etichettaPerId((string) $rilievo['gruppoId']) : '',
              'Licenza'      => (string) $rilievo['licenza'],
              'Riservatezza' => (string) $rilievo['riservatezza'],
          ];
          foreach ($voci as $etichetta => $valore): ?>
            <dt class="col-sm-5 fw-normal text-body-secondary"><?= Testo::esc($etichetta) ?></dt>
            <dd class="col-sm-7">
              <?= $valore !== ''
                  ? Testo::esc($valore)
                  : '<span class="text-body-tertiary">—</span>' ?>
            </dd>
          <?php endforeach; ?>
        </dl>

        <?php if ((string) $rilievo['descrizione'] !== ''): ?>
          <hr>
          <p class="mb-0"><?= nl2br(Testo::esc((string) $rilievo['descrizione'])) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if (Auth::puo('carica_risorse')): ?>
      <a class="btn btn-outline-secondary mt-3 w-100"
         href="index.php?p=risorse&amp;codice=<?= urlencode($codice) ?>&amp;sez=RI&amp;modifica=<?= $prog ?>">
        <i class="bi bi-pencil"></i> Modifica i dati
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($inMappa): ?>
  <script type="application/json" id="catageoMappaConfig"><?= Testo::escJson(Mappa::perBrowser()) ?></script>
<?php endif; ?>
