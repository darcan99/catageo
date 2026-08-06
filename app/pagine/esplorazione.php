<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/esplorazione.php
 *  Descrizione ..: Diari di esplorazione di un ipogeo: elenco, lettura,
 *                  redazione (9.6).
 *
 *                  Le foto del diario si scelgono fra quelle gia in galleria e
 *                  non si ricaricano: una foto sola su disco, richiamata dove
 *                  serve. Chi non e in anagrafica si registra col solo nome,
 *                  perche un ospite di una sola uscita non deve costringere a
 *                  creare una scheda che poi resta li.
 *  Versione .....: 0.9.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.9.0  2026-08-05  D.Candela  Prima stesura (fase 7).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');
require_once CATAGEO_ROOT . '/app/view/parti-media.php';

$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';
$azione = isset($_GET['azione']) ? (string) $_GET['azione'] : 'elenco';
$prog   = isset($_GET['prog']) ? (int) $_GET['prog'] : 0;

$risoluzione = $codice === '' ? null : Ipogeo::risolvi($codice);
if ($risoluzione === null) {
    Auth::messaggio('errore', 'Ipogeo non indicato o inesistente.');
    header('Location: index.php?p=ipogei');
    exit;
}

$scheda = $risoluzione['scheda'];
$codice = $risoluzione['codiceCorrente'];

if (!Visibilita::schedaVisibile(
    (string) $scheda['ubicazione']['riservatezza'],
    (string) $scheda['catasto']['statoScheda']
)) {
    Auth::messaggio('errore', 'La scheda richiesta non e consultabile con il livello di utenza in uso.');
    header('Location: index.php?p=ipogei');
    exit;
}

$nomeIpogeo = (string) $scheda['identificazione']['nome'];
$puoRedigere = Auth::puo('redigi_esplorazioni');
$ritorno = 'index.php?p=esplorazione&codice=' . urlencode($codice);

// ============================================================================
//  OPERAZIONI
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    Auth::esigi('redigi_esplorazioni');

    $operazione = (string) ($_POST['operazione'] ?? '');

    try {
        if ($operazione === 'elimina') {
            Esplorazioni::elimina($codice, (int) ($_POST['progressivo'] ?? 0));
            IndiceIpogei::aggiorna($codice);
            Auth::messaggio('successo',
                'Diario rimosso. Il file e stato spostato in "' . $codice . ' - '
                . Risorse::CARTELLA_RIMOSSI . '" e resta recuperabile.');
            header('Location: ' . $ritorno);
            exit;
        }

        // --- raccolta dei dati dal modulo ---------------------------------

        /** Partecipanti: righe con esploratore in anagrafica oppure solo nome. */
        $partecipanti = [];
        $idPartecipanti  = (array) ($_POST['partecipanteId'] ?? []);
        $nomiPartecipanti = (array) ($_POST['partecipanteNome'] ?? []);
        $ruoliPartecipanti = (array) ($_POST['partecipanteRuolo'] ?? []);

        foreach (array_keys($idPartecipanti + $nomiPartecipanti) as $i) {
            $id   = trim((string) ($idPartecipanti[$i] ?? ''));
            $nome = trim((string) ($nomiPartecipanti[$i] ?? ''));
            if ($id === '' && $nome === '') {
                continue;
            }
            $partecipanti[] = [
                'esploratoreId' => $id,
                // Il nome libero serve solo a chi non e in anagrafica: se
                // l'esploratore e scelto dall'elenco, il nome verrebbe
                // duplicato e potrebbe poi divergere da quello dell'anagrafica.
                'nome'          => $id === '' ? $nome : '',
                'ruolo'         => trim((string) ($ruoliPartecipanti[$i] ?? '')),
            ];
        }

        /** Voci del diario, nell'ordine in cui compaiono nel modulo. */
        $voci = [];
        $ore   = (array) ($_POST['voceOra'] ?? []);
        $testi = (array) ($_POST['voceTesto'] ?? []);
        $lat   = (array) ($_POST['voceLat'] ?? []);
        $lon   = (array) ($_POST['voceLon'] ?? []);
        $quote = (array) ($_POST['voceQuota'] ?? []);
        $foto  = (array) ($_POST['voceFoto'] ?? []);

        foreach (array_keys($testi) as $i) {
            $voci[] = [
                'ora'         => trim((string) ($ore[$i] ?? '')),
                'testo'       => (string) ($testi[$i] ?? ''),
                'latitudine'  => catageoGradiVoce((string) ($lat[$i] ?? ''), 90.0),
                'longitudine' => catageoGradiVoce((string) ($lon[$i] ?? ''), 180.0),
                'quota'       => trim((string) ($quote[$i] ?? '')),
                'foto'        => array_filter(array_map('trim', (array) ($foto[$i] ?? []))),
            ];
        }

        $dati = [
            'titolo'       => (string) ($_POST['titolo'] ?? ''),
            'tipo'         => (string) ($_POST['tipo'] ?? 'esplorazione'),
            'dataInizio'   => (string) ($_POST['dataInizio'] ?? ''),
            'oraInizio'    => (string) ($_POST['oraInizio'] ?? ''),
            'dataFine'     => (string) ($_POST['dataFine'] ?? ''),
            'oraFine'      => (string) ($_POST['oraFine'] ?? ''),
            'meteo'        => (string) ($_POST['meteo'] ?? ''),
            'obiettivi'    => (string) ($_POST['obiettivi'] ?? ''),
            'risultati'    => (string) ($_POST['risultati'] ?? ''),
            'note'         => (string) ($_POST['note'] ?? ''),
            'traccia'      => (string) ($_POST['traccia'] ?? ''),
            'gruppi'       => (array) ($_POST['gruppi'] ?? []),
            'partecipanti' => $partecipanti,
            'voci'         => $voci,
            'materiale'    => (array) ($_POST['materiale'] ?? []),
        ];

        if ($operazione === 'crea') {
            $nuovo = Esplorazioni::crea($codice, $dati);
            IndiceIpogei::aggiorna($codice);
            Auth::messaggio('successo', 'Diario creato.');
            header('Location: index.php?p=esplorazione&codice=' . urlencode($codice)
                . '&azione=vedi&prog=' . $nuovo);
            exit;
        }

        if ($operazione === 'aggiorna') {
            $p = (int) ($_POST['progressivo'] ?? 0);
            Esplorazioni::aggiorna($codice, $p, $dati);
            IndiceIpogei::aggiorna($codice);
            Auth::messaggio('successo', 'Diario aggiornato.');
            header('Location: index.php?p=esplorazione&codice=' . urlencode($codice)
                . '&azione=vedi&prog=' . $p);
            exit;
        }

        Auth::messaggio('errore', 'Operazione non riconosciuta.');
    } catch (EsplorazioneEccezione | IpogeoEccezione | XmlEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: ' . $ritorno);
    exit;
}

/**
 * Coordinata di una voce di diario, o stringa vuota se non e una posizione.
 *
 * Si accetta la virgola decimale, che e quella della tastiera italiana.
 */
function catageoGradiVoce(string $valore, float $massimo): string
{
    $valore = trim(str_replace(',', '.', $valore));
    if ($valore === '' || !is_numeric($valore)) {
        return '';
    }
    $numero = (float) $valore;

    return abs($numero) <= $massimo ? number_format($numero, 6, '.', '') : '';
}

// ============================================================================
//  VISTA: singolo diario
// ============================================================================

if ($azione === 'vedi' && $prog > 0) {
    $diario = Esplorazioni::trova($codice, $prog);
    if ($diario === null) {
        Auth::messaggio('errore', 'Diario non trovato.');
        header('Location: ' . $ritorno);
        exit;
    }

    $titolo  = (string) $diario['titolo'] . ' — ' . $codice;
    $durata  = Esplorazioni::durataOre($diario);
    $conCoordinate = array_filter($diario['voci'], static fn (array $v): bool => $v['latitudine'] !== '');

    if ($conCoordinate !== []) {
        $cssPagina = ['assets/vendor/leaflet-1.9.4/leaflet.css', 'assets/css/catageo-mappa.css'];
        $jsPagina  = ['assets/vendor/leaflet-1.9.4/leaflet.js', 'assets/js/catageo-mappa.js'];
    }
    $jsPagina[] = 'assets/js/catageo-media.js';
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1><?= Testo::esc((string) $diario['titolo']) ?></h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-valore"><?= Testo::esc(Sezioni::riferimento('ES', $prog)) ?></span>
          · <span class="catageo-codice"><?= Testo::esc($codice) ?></span>
          <?= Testo::esc($nomeIpogeo) ?>
        </p>
      </div>
      <div class="d-flex gap-2">
        <?php if ($puoRedigere): ?>
          <a class="btn btn-outline-secondary"
             href="index.php?p=esplorazione&amp;codice=<?= urlencode($codice) ?>&amp;azione=modifica&amp;prog=<?= $prog ?>">
            <i class="bi bi-pencil"></i> Modifica
          </a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
          <i class="bi bi-arrow-left"></i> Diari
        </a>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">

        <div class="card mb-4">
          <div class="card-header"><h2 class="h6 mb-0">Diario</h2></div>
          <div class="card-body">
            <?php if ($diario['voci'] === []): ?>
              <p class="text-body-secondary mb-0">Nessuna voce nel diario.</p>
            <?php else: ?>
              <?php foreach ($diario['voci'] as $voce): ?>
                <div class="catageo-voce">
                  <div class="catageo-voce-ora">
                    <?= $voce['ora'] !== '' ? Testo::esc($voce['ora']) : '·' ?>
                  </div>
                  <div class="catageo-voce-corpo">
                    <?php if ($voce['testo'] !== ''): ?>
                      <p class="mb-1"><?= nl2br(Testo::esc($voce['testo'])) ?></p>
                    <?php endif; ?>

                    <?php if ($voce['latitudine'] !== ''): ?>
                      <div class="catageo-dati-media">
                        <a class="catageo-geotag" target="_blank" rel="noopener"
                           href="<?= Testo::esc('https://www.google.com/maps?q='
                               . rawurlencode($voce['latitudine'] . ',' . $voce['longitudine'])) ?>">
                          <i class="bi bi-geo-alt-fill"></i>
                          <?= Testo::esc($voce['latitudine']) ?>, <?= Testo::esc($voce['longitudine']) ?>
                        </a>
                        <?php if ($voce['quota'] !== ''): ?>
                          <span><?= Testo::esc($voce['quota']) ?> m</span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>

                    <?php
                    // Le foto sono riferimenti: si risolvono ora contro la
                    // galleria. Un riferimento a una foto rimossa non deve
                    // rompere il diario, ma nemmeno sparire in silenzio.
                    ?>
                    <?php if ($voce['foto'] !== []): ?>
                      <div class="row g-2 mt-1">
                        <?php foreach ($voce['foto'] as $riferimento): ?>
                          <?php
                          $parti = Sezioni::scomponiRiferimento($riferimento);
                          $foto  = $parti === null ? null
                              : Risorse::trova($codice, $parti['sigla'], $parti['progressivo']);
                          ?>
                          <?php if ($foto === null): ?>
                            <div class="col-auto">
                              <span class="badge text-bg-light border text-danger"
                                    title="Riferimento a una foto non piu presente">
                                <i class="bi bi-exclamation-triangle"></i> <?= Testo::esc($riferimento) ?>
                              </span>
                            </div>
                          <?php else: ?>
                            <div class="col-6 col-md-4 col-xl-3">
                              <a href="<?= Testo::esc(catageoUrlRisorsa($codice, 'FO', (int) $foto['progressivo'], false, true)) ?>"
                                 <?= catageoAttributiMedia($foto, $codice, 'FO') ?>>
                                <img class="catageo-miniatura rounded" loading="lazy"
                                     src="<?= Testo::esc(catageoUrlRisorsa($codice, 'FO', (int) $foto['progressivo'], true, true)) ?>"
                                     alt="<?= Testo::esc((string) $foto['titolo']) ?>">
                              </a>
                            </div>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($conCoordinate !== []): ?>
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Punti del diario</h2></div>
            <div class="card-body">
              <div id="catageoMappa" class="catageo-mappa catageo-mappa-scheda"
                   data-catageo-tracciato-json="catageoDiarioPunti"></div>
              <div class="catageo-nota mt-2">
                <?= count($conCoordinate) ?> vo<?= count($conCoordinate) === 1 ? 'ce' : 'ci' ?>
                del diario con posizione.
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php foreach (['obiettivi' => 'Obiettivi', 'risultati' => 'Risultati', 'note' => 'Note'] as $campo => $etichetta): ?>
          <?php if ((string) $diario[$campo] !== ''): ?>
            <div class="card mb-4">
              <div class="card-header"><h2 class="h6 mb-0"><?= $etichetta ?></h2></div>
              <div class="card-body"><?= nl2br(Testo::esc((string) $diario[$campo])) ?></div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header"><h2 class="h6 mb-0">L'uscita</h2></div>
          <div class="card-body">
            <dl class="row catageo-dl mb-0">
              <dt class="col-sm-5 fw-normal text-body-secondary">Tipo</dt>
              <dd class="col-sm-7"><?= Testo::esc(Esplorazioni::TIPI[(string) $diario['tipo']] ?? (string) $diario['tipo']) ?></dd>

              <dt class="col-sm-5 fw-normal text-body-secondary">Data</dt>
              <dd class="col-sm-7">
                <?= Testo::esc((string) $diario['dataInizio']) ?>
                <?php if ((string) $diario['dataFine'] !== '' && $diario['dataFine'] !== $diario['dataInizio']): ?>
                  → <?= Testo::esc((string) $diario['dataFine']) ?>
                <?php endif; ?>
              </dd>

              <dt class="col-sm-5 fw-normal text-body-secondary">Orario</dt>
              <dd class="col-sm-7">
                <?php if ((string) $diario['oraInizio'] !== ''): ?>
                  <?= Testo::esc((string) $diario['oraInizio']) ?>
                  <?= (string) $diario['oraFine'] !== '' ? ' – ' . Testo::esc((string) $diario['oraFine']) : '' ?>
                  <?php if ($durata !== null): ?>
                    <span class="catageo-nota">(<?= Testo::esc(number_format($durata, 2, ',', '')) ?> ore)</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-body-tertiary">—</span>
                <?php endif; ?>
              </dd>

              <dt class="col-sm-5 fw-normal text-body-secondary">Meteo</dt>
              <dd class="col-sm-7">
                <?= (string) $diario['meteo'] !== ''
                    ? Testo::esc((string) $diario['meteo'])
                    : '<span class="text-body-tertiary">—</span>' ?>
              </dd>
            </dl>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header"><h2 class="h6 mb-0">Chi c'era</h2></div>
          <div class="card-body">
            <?php if ($diario['gruppi'] !== []): ?>
              <div class="mb-2">
                <?php foreach ($diario['gruppi'] as $idGruppo): ?>
                  <span class="badge text-bg-light border">
                    <i class="bi bi-people"></i> <?= Testo::esc(Gruppi::etichettaPerId($idGruppo)) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($diario['partecipanti'] === []): ?>
              <p class="text-body-secondary mb-0">Nessun partecipante registrato.</p>
            <?php else: ?>
              <ul class="list-unstyled mb-0">
                <?php foreach ($diario['partecipanti'] as $p): ?>
                  <li class="d-flex justify-content-between gap-2">
                    <span>
                      <?php if ((string) $p['esploratoreId'] !== ''): ?>
                        <a href="index.php?p=esplorazioni&amp;esploratore=<?= urlencode((string) $p['esploratoreId']) ?>"
                           title="Tutte le uscite di questo esploratore">
                          <?= Testo::esc(Esploratori::etichettaPerId((string) $p['esploratoreId'])) ?>
                        </a>
                      <?php else: ?>
                        <?= Testo::esc((string) $p['nome']) ?>
                        <span class="catageo-nota">(non in anagrafica)</span>
                      <?php endif; ?>
                    </span>
                    <?php if ((string) $p['ruolo'] !== ''): ?>
                      <span class="catageo-nota"><?= Testo::esc(Esplorazioni::RUOLI[(string) $p['ruolo']] ?? (string) $p['ruolo']) ?></span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($diario['materiale'] !== [] || (string) $diario['traccia'] !== ''): ?>
          <div class="card mb-3">
            <div class="card-header"><h2 class="h6 mb-0">Materiale prodotto</h2></div>
            <div class="card-body">
              <ul class="list-unstyled mb-0">
                <?php foreach ($diario['materiale'] as $voce): ?>
                  <?php
                  $parti = Sezioni::scomponiRiferimento((string) $voce['riferimento']);
                  $risorsa = $parti === null ? null
                      : Risorse::trova($codice, $parti['sigla'], $parti['progressivo']);
                  ?>
                  <li>
                    <span class="catageo-valore"><?= Testo::esc((string) $voce['riferimento']) ?></span>
                    <?php if ($risorsa === null): ?>
                      <span class="text-danger catageo-nota">non piu presente</span>
                    <?php elseif ($voce['sigla'] === 'RI'): ?>
                      <a href="index.php?p=rilievo&amp;codice=<?= urlencode($codice) ?>&amp;prog=<?= (int) $risorsa['progressivo'] ?>">
                        <?= Testo::esc((string) $risorsa['titolo']) ?>
                      </a>
                    <?php else: ?>
                      <a href="<?= Testo::esc(catageoUrlRisorsa($codice, (string) $voce['sigla'], (int) $risorsa['progressivo'])) ?>">
                        <?= Testo::esc((string) $risorsa['titolo']) ?>
                      </a>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
                <?php if ((string) $diario['traccia'] !== ''): ?>
                  <li><span class="catageo-nota">Traccia: </span>
                    <span class="catageo-valore"><?= Testo::esc((string) $diario['traccia']) ?></span></li>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>

        <div class="catageo-nota">
          Redatto da <?= Testo::esc((string) $diario['redattoDa'] ?: '—') ?>
          <?php if ((string) $diario['redattoIl'] !== ''): ?>
            il <?= Testo::esc(substr((string) $diario['redattoIl'], 0, 10)) ?>
          <?php endif; ?>
          · file <span class="catageo-valore"><?= Testo::esc((string) $diario['file']) ?></span>
        </div>
      </div>
    </div>

    <?php require CATAGEO_ROOT . '/app/view/modale-media.php'; ?>
    <?php if ($conCoordinate !== []): ?>
      <?php
      /*
       * I punti del diario come GeoJSON, nella pagina che li elenca.
       * Non c'e un endpoint dedicato perche il dato e gia stato letto qui:
       * un secondo giro in rete rileggerebbe lo stesso file per nulla.
       */
      $punti = ['type' => 'FeatureCollection', 'features' => []];
      foreach ($conCoordinate as $voce) {
          $punti['features'][] = [
              'type' => 'Feature',
              'geometry' => [
                  'type' => 'Point',
                  'coordinates' => $voce['quota'] !== ''
                      ? [(float) $voce['longitudine'], (float) $voce['latitudine'], (float) $voce['quota']]
                      : [(float) $voce['longitudine'], (float) $voce['latitudine']],
              ],
              'properties' => [
                  'nome'        => $voce['ora'] !== '' ? 'Ore ' . $voce['ora'] : 'Voce di diario',
                  'descrizione' => Testo::estratto((string) $voce['testo'], 220),
              ],
          ];
      }
      ?>
      <script type="application/json" id="catageoDiarioPunti"><?= Testo::escJson($punti) ?></script>
      <script type="application/json" id="catageoMappaConfig"><?= Testo::escJson(Mappa::perBrowser()) ?></script>
    <?php endif; ?>
    <?php
    return;
}

// ============================================================================
//  FORM: nuovo e modifica
// ============================================================================

if (($azione === 'nuovo' || ($azione === 'modifica' && $prog > 0)) && $puoRedigere) {
    $modifica = $azione === 'modifica';
    $diario = $modifica ? Esplorazioni::trova($codice, $prog) : null;

    if ($modifica && $diario === null) {
        Auth::messaggio('errore', 'Diario non trovato.');
        header('Location: ' . $ritorno);
        exit;
    }

    if ($diario === null) {
        $diario = array_merge(Esplorazioni::CAMPI, [
            'gruppi' => [], 'partecipanti' => [], 'voci' => [], 'materiale' => [],
            'dataInizio' => date('Y-m-d'),
        ]);
    }

    $titolo = ($modifica ? 'Modifica diario' : 'Nuovo diario') . ' — ' . $codice;
    $jsPagina = ['assets/js/catageo-diario.js'];

    $galleria = Risorse::elenco($codice, 'FO');
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1><?= $modifica ? 'Modifica diario' : 'Nuovo diario' ?></h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
        </p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
        <i class="bi bi-x-lg"></i> Annulla
      </a>
    </div>

    <form method="post" action="index.php?p=esplorazione&amp;codice=<?= urlencode($codice) ?>"
          class="needs-validation" novalidate>
      <?= Auth::campoToken() ?>
      <input type="hidden" name="operazione" value="<?= $modifica ? 'aggiorna' : 'crea' ?>">
      <?php if ($modifica): ?>
        <input type="hidden" name="progressivo" value="<?= $prog ?>">
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-lg-7">

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">L'uscita</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-8">
                  <label for="titolo" class="form-label">Titolo <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="titolo" name="titolo" required maxlength="200"
                         value="<?= Testo::esc((string) $diario['titolo']) ?>">
                  <div class="catageo-nota">Compare nel nome del file del diario.</div>
                </div>

                <div class="col-md-4">
                  <label for="tipo" class="form-label">Tipo</label>
                  <select class="form-select" id="tipo" name="tipo">
                    <?php foreach (Esplorazioni::TIPI as $valore => $etichetta): ?>
                      <option value="<?= $valore ?>" <?= (string) $diario['tipo'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-3">
                  <label for="dataInizio" class="form-label">Data <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="dataInizio" name="dataInizio" required
                         value="<?= Testo::esc((string) $diario['dataInizio']) ?>">
                </div>
                <div class="col-md-3">
                  <label for="oraInizio" class="form-label">Ora di entrata</label>
                  <input type="time" class="form-control" id="oraInizio" name="oraInizio"
                         value="<?= Testo::esc((string) $diario['oraInizio']) ?>">
                </div>
                <div class="col-md-3">
                  <label for="dataFine" class="form-label">Data di fine</label>
                  <input type="date" class="form-control" id="dataFine" name="dataFine"
                         value="<?= Testo::esc((string) $diario['dataFine']) ?>">
                  <div class="catageo-nota">Solo se l'uscita e durata piu giorni.</div>
                </div>
                <div class="col-md-3">
                  <label for="oraFine" class="form-label">Ora di uscita</label>
                  <input type="time" class="form-control" id="oraFine" name="oraFine"
                         value="<?= Testo::esc((string) $diario['oraFine']) ?>">
                </div>

                <div class="col-md-6">
                  <label for="meteo" class="form-label">Meteo</label>
                  <input type="text" class="form-control" id="meteo" name="meteo" maxlength="200"
                         value="<?= Testo::esc((string) $diario['meteo']) ?>"
                         placeholder="Sereno, 22 °C">
                </div>

                <div class="col-md-6">
                  <label for="traccia" class="form-label">Traccia GPS</label>
                  <input type="text" class="form-control" id="traccia" name="traccia" maxlength="200"
                         value="<?= Testo::esc((string) $diario['traccia']) ?>"
                         placeholder="nome del file caricato fra i rilievi">
                </div>

                <div class="col-12">
                  <label for="obiettivi" class="form-label">Obiettivi</label>
                  <textarea class="form-control" id="obiettivi" name="obiettivi" rows="2"><?= Testo::esc((string) $diario['obiettivi']) ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h2 class="h6 mb-0">Diario</h2>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="catageoAggiungiVoce">
                <i class="bi bi-plus-lg"></i> Aggiungi voce
              </button>
            </div>
            <div class="card-body">
              <div id="catageoVoci">
                <?php
                $vociDaMostrare = $diario['voci'] !== [] ? $diario['voci'] : [[
                    'ora' => '', 'testo' => '', 'latitudine' => '', 'longitudine' => '', 'quota' => '', 'foto' => [],
                ]];
                foreach ($vociDaMostrare as $i => $voce):
                ?>
                  <div class="catageo-voce-modulo" data-catageo-voce>
                    <div class="row g-2">
                      <div class="col-4 col-md-2">
                        <label class="form-label catageo-nota mb-1">Ora</label>
                        <input type="time" class="form-control form-control-sm" name="voceOra[<?= $i ?>]"
                               value="<?= Testo::esc((string) $voce['ora']) ?>">
                      </div>
                      <div class="col-8 col-md-3">
                        <label class="form-label catageo-nota mb-1">Latitudine</label>
                        <input type="text" class="form-control form-control-sm catageo-valore" name="voceLat[<?= $i ?>]"
                               value="<?= Testo::esc((string) $voce['latitudine']) ?>" placeholder="41.856231">
                      </div>
                      <div class="col-8 col-md-3">
                        <label class="form-label catageo-nota mb-1">Longitudine</label>
                        <input type="text" class="form-control form-control-sm catageo-valore" name="voceLon[<?= $i ?>]"
                               value="<?= Testo::esc((string) $voce['longitudine']) ?>" placeholder="12.532104">
                      </div>
                      <div class="col-4 col-md-2">
                        <label class="form-label catageo-nota mb-1">Quota (m)</label>
                        <input type="text" class="form-control form-control-sm" name="voceQuota[<?= $i ?>]"
                               value="<?= Testo::esc((string) $voce['quota']) ?>">
                      </div>
                      <div class="col-12 col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100"
                                data-catageo-togli-voce title="Togli questa voce">
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>

                      <div class="col-12">
                        <textarea class="form-control" name="voceTesto[<?= $i ?>]" rows="2"
                                  placeholder="Cosa e successo"><?= Testo::esc((string) $voce['testo']) ?></textarea>
                      </div>

                      <?php if ($galleria !== []): ?>
                        <div class="col-12">
                          <label class="form-label catageo-nota mb-1">Foto della galleria</label>
                          <select class="form-select form-select-sm" name="voceFoto[<?= $i ?>][]" multiple size="3">
                            <?php foreach ($galleria as $foto): ?>
                              <?php $rif = Sezioni::riferimento('FO', (int) $foto['progressivo']); ?>
                              <option value="<?= Testo::esc($rif) ?>"
                                <?= in_array($rif, (array) $voce['foto'], true) ? 'selected' : '' ?>>
                                <?= Testo::esc($rif) ?> — <?= Testo::esc((string) $foto['titolo']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <div class="catageo-nota">
                            Le foto restano nella galleria: qui si richiamano, non si duplicano.
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <?php if ($galleria === []): ?>
                <div class="catageo-nota">
                  Nessuna foto in galleria: caricandone si potranno richiamare nelle voci del diario.
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Esito</h2></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="risultati" class="form-label">Risultati</label>
                <textarea class="form-control" id="risultati" name="risultati" rows="3"><?= Testo::esc((string) $diario['risultati']) ?></textarea>
              </div>
              <div>
                <label for="note" class="form-label">Note</label>
                <textarea class="form-control" id="note" name="note" rows="2"><?= Testo::esc((string) $diario['note']) ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Gruppi</h2></div>
            <div class="card-body">
              <?php $tuttiGruppi = Gruppi::elenco(true); ?>
              <?php if ($tuttiGruppi === []): ?>
                <p class="text-body-secondary mb-0">
                  Nessun gruppo in anagrafica.
                  <a href="index.php?p=gruppi">Censiscine uno</a>.
                </p>
              <?php else: ?>
                <?php foreach ($tuttiGruppi as $g): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="gruppi[]"
                           id="gruppo<?= Testo::esc((string) $g['id']) ?>"
                           value="<?= Testo::esc((string) $g['id']) ?>"
                           <?= in_array((string) $g['id'], $diario['gruppi'], true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="gruppo<?= Testo::esc((string) $g['id']) ?>">
                      <?= Testo::esc(Gruppi::etichetta($g)) ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h2 class="h6 mb-0">Partecipanti</h2>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="catageoAggiungiPartecipante">
                <i class="bi bi-plus-lg"></i> Aggiungi
              </button>
            </div>
            <div class="card-body">
              <div id="catageoPartecipanti">
                <?php
                $daMostrare = $diario['partecipanti'] !== [] ? $diario['partecipanti']
                    : [['esploratoreId' => '', 'nome' => '', 'ruolo' => '']];
                foreach ($daMostrare as $i => $p):
                ?>
                  <div class="row g-2 mb-2" data-catageo-partecipante>
                    <div class="col-5">
                      <select class="form-select form-select-sm" name="partecipanteId[<?= $i ?>]">
                        <option value="">— dall'anagrafica —</option>
                        <?php foreach (Esploratori::elenco(true) as $e): ?>
                          <option value="<?= Testo::esc((string) $e['id']) ?>"
                            <?= (string) $p['esploratoreId'] === (string) $e['id'] ? 'selected' : '' ?>>
                            <?= Testo::esc(Esploratori::etichetta($e)) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-4">
                      <input type="text" class="form-control form-control-sm" name="partecipanteNome[<?= $i ?>]"
                             value="<?= Testo::esc((string) $p['nome']) ?>" placeholder="oppure nome">
                    </div>
                    <div class="col-2">
                      <select class="form-select form-select-sm" name="partecipanteRuolo[<?= $i ?>]">
                        <?php foreach (Esplorazioni::RUOLI as $valore => $etichetta): ?>
                          <option value="<?= $valore ?>" <?= (string) $p['ruolo'] === $valore ? 'selected' : '' ?>>
                            <?= Testo::esc($etichetta) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-1">
                      <button type="button" class="btn btn-sm btn-outline-danger w-100"
                              data-catageo-togli-partecipante title="Togli">
                        <i class="bi bi-x"></i>
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="catageo-nota">
                Chi non e in anagrafica si registra scrivendo il nome: un ospite di
                una sola uscita non deve costringere a creare una scheda.
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Materiale prodotto</h2></div>
            <div class="card-body">
              <?php
              $candidati = [];
              foreach (['RI', 'AL'] as $sigla) {
                  foreach (Risorse::elenco($codice, $sigla) as $risorsa) {
                      $candidati[] = [
                          'rif'   => Sezioni::riferimento($sigla, (int) $risorsa['progressivo']),
                          'nome'  => (string) $risorsa['titolo'],
                          'sez'   => Sezioni::etichetta($sigla),
                      ];
                  }
              }
              $scelti = array_map(static fn (array $m): string => (string) $m['riferimento'], $diario['materiale']);
              ?>
              <?php if ($candidati === []): ?>
                <p class="text-body-secondary mb-0">
                  Nessun rilievo o allegato ancora caricato per questo ipogeo.
                </p>
              <?php else: ?>
                <select class="form-select" name="materiale[]" multiple size="<?= min(8, count($candidati) + 1) ?>">
                  <?php foreach ($candidati as $c): ?>
                    <option value="<?= Testo::esc($c['rif']) ?>"
                      <?= in_array($c['rif'], $scelti, true) ? 'selected' : '' ?>>
                      <?= Testo::esc($c['rif']) ?> — <?= Testo::esc($c['nome']) ?> (<?= Testo::esc($c['sez']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="catageo-nota">Cio che questa uscita ha prodotto, gia caricato nelle sezioni.</div>
              <?php endif; ?>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i> <?= $modifica ? 'Salva il diario' : 'Crea il diario' ?>
          </button>
        </div>
      </div>
    </form>

    <?php
    return;
}

// ============================================================================
//  ELENCO
// ============================================================================

$diari  = Esplorazioni::elenco($codice);
$titolo = 'Esplorazioni — ' . $codice;
?>

<div class="catageo-intestazione">
  <div>
    <h1>Esplorazioni</h1>
    <p class="text-body-secondary mb-0">
      <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
      · <?= count($diari) ?> uscit<?= count($diari) === 1 ? 'a' : 'e' ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <?php if ($puoRedigere): ?>
      <a class="btn btn-primary"
         href="index.php?p=esplorazione&amp;codice=<?= urlencode($codice) ?>&amp;azione=nuovo">
        <i class="bi bi-plus-lg"></i> Nuovo diario
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary"
       href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode($codice) ?>">
      <i class="bi bi-arrow-left"></i> Torna alla scheda
    </a>
  </div>
</div>

<?php if ($diari === []): ?>

  <div class="card">
    <div class="card-body d-flex gap-3">
      <i class="bi bi-journal-text fs-3 text-body-secondary" aria-hidden="true"></i>
      <div>
        <h2 class="h6 mb-1">Nessun diario</h2>
        <p class="text-body-secondary mb-0">
          Le uscite si registrano una per una: ognuna diventa un documento autonomo
          nella cartella
          <span class="catageo-valore"><?= Testo::esc(Sezioni::nomeCartella($codice, 'ES')) ?></span>.
        </p>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th style="width:5rem">Rif.</th>
            <th style="width:7rem">Data</th>
            <th>Titolo</th>
            <th>Tipo</th>
            <th>Gruppi</th>
            <th class="text-end">Voci</th>
            <?php if ($puoRedigere): ?><th class="text-end">Azioni</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($diari as $d): ?>
            <?php $p = (int) $d['progressivo']; ?>
            <tr>
              <td><span class="catageo-valore"><?= Testo::esc(Sezioni::riferimento('ES', $p)) ?></span></td>
              <td><?= Testo::esc((string) $d['dataInizio']) ?></td>
              <td>
                <a href="index.php?p=esplorazione&amp;codice=<?= urlencode($codice) ?>&amp;azione=vedi&amp;prog=<?= $p ?>">
                  <?= Testo::esc((string) $d['titolo']) ?>
                </a>
              </td>
              <td><?= Testo::esc(Esplorazioni::TIPI[(string) $d['tipo']] ?? (string) $d['tipo']) ?></td>
              <td>
                <?php foreach ($d['gruppi'] as $idGruppo): ?>
                  <span class="badge text-bg-light border"><?= Testo::esc(Gruppi::etichettaPerId($idGruppo)) ?></span>
                <?php endforeach; ?>
              </td>
              <td class="text-end catageo-valore"><?= (int) $d['voci'] ?></td>
              <?php if ($puoRedigere): ?>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="index.php?p=esplorazione&amp;codice=<?= urlencode($codice) ?>&amp;azione=modifica&amp;prog=<?= $p ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="<?= Testo::esc($ritorno) ?>">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="elimina">
                      <input type="hidden" name="progressivo" value="<?= $p ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit"
                              data-catageo-conferma="Rimuovere questo diario? Il file viene spostato in _rimossi e resta recuperabile.">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>
