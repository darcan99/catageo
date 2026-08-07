<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/biospeleologia.php
 *  Descrizione ..: Osservazioni faunistiche e colonie di chirotteri (9.9).
 *
 *                  Le colonie stanno prima delle osservazioni, e non per
 *                  ordine alfabetico: sono il dato che determina se si puo
 *                  entrare, e chi apre questa pagina prima di un'uscita deve
 *                  vederlo per primo.
 *  Versione .....: 0.12.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.0  2026-08-06  D.Candela  Prima stesura (fase 7d).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');
require_once CATAGEO_ROOT . '/app/view/parti-avvisi.php';

$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';
$azione = isset($_GET['azione']) ? (string) $_GET['azione'] : 'elenco';
$id     = isset($_GET['id']) ? trim((string) $_GET['id']) : '';

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
    Auth::messaggio('errore', 'La scheda richiesta non è consultabile con il livello di utenza in uso.');
    header('Location: index.php?p=ipogei');
    exit;
}

$nomeIpogeo   = (string) $scheda['identificazione']['nome'];
$puoCompilare = Auth::puo('compila_sezioni');
$ritorno = 'index.php?p=biospeleologia&codice=' . urlencode($codice);

// ============================================================================
//  OPERAZIONI
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    Auth::esigi('compila_sezioni');

    $operazione = (string) ($_POST['operazione'] ?? '');

    try {
        switch ($operazione) {

            case 'salvaOsservazione':
                $dati = [];
                foreach (array_keys(Biospeleologia::CAMPI_OSSERVAZIONE) as $campo) {
                    $dati[$campo] = (string) ($_POST[$campo] ?? '');
                }
                // Le caselle non spuntate non arrivano nel POST: senza questa
                // normalizzazione togliere la spunta non avrebbe effetto.
                $dati['endemismo']      = !empty($_POST['endemismo']) ? '1' : '0';
                $dati['specieProtetta'] = !empty($_POST['specieProtetta']) ? '1' : '0';

                $nuovo = Biospeleologia::salvaOsservazione($codice, (string) ($_POST['id'] ?? ''), $dati);
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', 'Osservazione ' . $nuovo . ' salvata.');
                break;

            case 'eliminaOsservazione':
                Biospeleologia::eliminaOsservazione($codice, (string) ($_POST['id'] ?? ''));
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', 'Osservazione rimossa.');
                break;

            case 'salvaColonia':
                $dati = [];
                foreach (array_keys(Biospeleologia::CAMPI_COLONIA) as $campo) {
                    $dati[$campo] = (string) ($_POST[$campo] ?? '');
                }
                $dati['accessoSconsigliato'] = !empty($_POST['accessoSconsigliato']) ? '1' : '0';

                $nuovo = Biospeleologia::salvaColonia($codice, (string) ($_POST['id'] ?? ''), $dati);
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', 'Colonia ' . $nuovo . ' salvata.');
                break;

            case 'eliminaColonia':
                Biospeleologia::eliminaColonia($codice, (string) ($_POST['id'] ?? ''));
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo',
                    'Colonia rimossa. Il file dei conteggi è stato spostato in "'
                    . $codice . ' - ' . Risorse::CARTELLA_RIMOSSI . '".');
                break;

            case 'aggiungiConteggio':
                Biospeleologia::aggiungiConteggio($codice, (string) ($_POST['id'] ?? ''), [
                    'data'        => (string) ($_POST['data'] ?? ''),
                    'ora'         => (string) ($_POST['ora'] ?? ''),
                    'specie'      => (string) ($_POST['specie'] ?? ''),
                    'metodo'      => (string) ($_POST['metodo'] ?? ''),
                    'numero'      => (string) ($_POST['numero'] ?? ''),
                    'stima_min'   => (string) ($_POST['stima_min'] ?? ''),
                    'stima_max'   => (string) ($_POST['stima_max'] ?? ''),
                    'fase'        => (string) ($_POST['fase'] ?? ''),
                    'temperatura' => (string) ($_POST['temperatura'] ?? ''),
                    'rilevatore'  => (string) ($_POST['rilevatore'] ?? ''),
                    'note'        => (string) ($_POST['note'] ?? ''),
                ]);
                Auth::messaggio('successo', 'Conteggio registrato.');
                header('Location: index.php?p=biospeleologia&codice=' . urlencode($codice)
                    . '&azione=colonia&id=' . urlencode((string) ($_POST['id'] ?? '')));
                exit;

            default:
                Auth::messaggio('errore', 'Operazione non riconosciuta.');
        }
    } catch (BiospeleologiaEccezione | IpogeoEccezione | XmlEccezione | CsvEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: ' . $ritorno);
    exit;
}

// ============================================================================
//  VISTA: una colonia
// ============================================================================

if ($azione === 'colonia' && $id !== '') {
    $colonia = Biospeleologia::colonia($codice, $id);

    if ($colonia === null) {
        Auth::messaggio('errore', 'Colonia non trovata.');
        header('Location: ' . $ritorno);
        exit;
    }

    // Il dettaglio di una colonia riservata non si mostra: l'avviso di periodo
    // critico resta comunque visibile in scheda, oscurato.
    if (!Visibilita::livelloVisibile((string) $colonia['riservatezza'])) {
        Auth::messaggio('errore',
            'Colonia riservata: il dettaglio non è consultabile con il livello di utenza in uso.');
        header('Location: ' . $ritorno);
        exit;
    }

    $conteggi = Biospeleologia::conteggi($codice, $colonia);
    $titolo   = (string) $colonia['nome'] . ' — ' . $codice;

    /** I conteggi diventano una spezzata riusando il grafico dei dati scientifici. */
    $perGrafico = [];
    foreach ($conteggi as $riga) {
        $valore = Biospeleologia::consistenza($riga);
        if ($valore === null) {
            continue;
        }
        $perGrafico[] = [
            'data'     => (string) ($riga['data'] ?? ''),
            'ora'      => (string) ($riga['ora'] ?? ''),
            'valore'   => (string) $valore,
            'validita' => (string) ($riga['validita'] ?? 'valido'),
        ];
    }
    $svg = Grafico::serieTemporale($perGrafico, ['etichetta' => (string) $colonia['nome'],
                                                 'unita' => 'individui']);
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1><?= Testo::esc((string) $colonia['nome']) ?></h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-valore"><?= Testo::esc($id) ?></span>
          · <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
          <?php if ((string) $colonia['riservatezza'] === 'riservata'): ?>
            <span class="badge text-bg-warning"><i class="bi bi-eye-slash"></i> riservata</span>
          <?php endif; ?>
        </p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
        <i class="bi bi-arrow-left"></i> Biospeleologia
      </a>
    </div>

    <?php catageoAvvisi(Biospeleologia::avvisi($codice)); ?>

    <div class="row g-4">
      <div class="col-lg-8">
        <?php if ($svg !== ''): ?>
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Andamento della consistenza</h2></div>
            <div class="card-body">
              <?= $svg ?>
              <div class="catageo-nota mt-2">
                Dove manca il numero esatto si usa il centro della stima: chi conta
                in uscita al crepuscolo produce quasi sempre un intervallo.
              </div>
            </div>
          </div>
        <?php endif; ?>

        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Conteggi</h2>
            <span class="catageo-nota"><?= count($conteggi) ?> visite registrate</span>
          </div>
          <?php if ($conteggi === []): ?>
            <div class="card-body">
              <p class="text-body-secondary mb-0">Nessun conteggio registrato.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm catageo-tabella mb-0">
                <thead>
                  <tr>
                    <th>Data</th><th>Specie</th><th>Metodo</th>
                    <th class="text-end">Numero</th><th class="text-end">Stima</th>
                    <th>Fase</th><th>Note</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($conteggi as $riga): ?>
                    <tr>
                      <td class="catageo-valore"><?= Testo::esc((string) ($riga['data'] ?? '')) ?></td>
                      <td><em><?= Testo::esc((string) ($riga['specie'] ?? '')) ?></em></td>
                      <td><?= Testo::esc((string) ($riga['metodo'] ?? '')) ?></td>
                      <td class="text-end catageo-valore">
                        <?= (string) ($riga['numero'] ?? '') !== ''
                            ? Testo::esc((string) $riga['numero'])
                            : '<span class="text-body-tertiary">—</span>' ?>
                      </td>
                      <td class="text-end catageo-valore">
                        <?php if ((string) ($riga['stima_min'] ?? '') !== '' || (string) ($riga['stima_max'] ?? '') !== ''): ?>
                          <?= Testo::esc((string) ($riga['stima_min'] ?? '')) ?>–<?= Testo::esc((string) ($riga['stima_max'] ?? '')) ?>
                        <?php else: ?>
                          <span class="text-body-tertiary">—</span>
                        <?php endif; ?>
                      </td>
                      <td><?= Testo::esc((string) ($riga['fase'] ?? '')) ?></td>
                      <td class="catageo-nota"><?= Testo::esc((string) ($riga['note'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($puoCompilare): ?>
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Registra un conteggio</h2></div>
            <div class="card-body">
              <form method="post" action="index.php?p=biospeleologia&amp;codice=<?= urlencode($codice) ?>"
                    class="row g-2 align-items-end">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="operazione" value="aggiungiConteggio">
                <input type="hidden" name="id" value="<?= Testo::esc($id) ?>">

                <div class="col-6 col-md-2">
                  <label for="data" class="form-label">Data <span class="text-danger">*</span></label>
                  <input type="date" class="form-control form-control-sm" id="data" name="data" required
                         value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-1">
                  <label for="ora" class="form-label">Ora</label>
                  <input type="time" class="form-control form-control-sm" id="ora" name="ora">
                </div>
                <div class="col-md-3">
                  <label for="specie" class="form-label">Specie contata</label>
                  <input type="text" class="form-control form-control-sm" id="specie" name="specie"
                         value="<?= Testo::esc((string) $colonia['specie']) ?>">
                </div>
                <div class="col-md-3">
                  <label for="metodo" class="form-label">Metodo</label>
                  <input type="text" class="form-control form-control-sm" id="metodo" name="metodo"
                         placeholder="conteggio in uscita">
                </div>
                <div class="col-4 col-md-1">
                  <label for="numero" class="form-label">Numero</label>
                  <input type="text" class="form-control form-control-sm catageo-valore"
                         id="numero" name="numero" inputmode="numeric">
                </div>
                <div class="col-4 col-md-1">
                  <label for="stima_min" class="form-label">Min</label>
                  <input type="text" class="form-control form-control-sm catageo-valore"
                         id="stima_min" name="stima_min" inputmode="numeric">
                </div>
                <div class="col-4 col-md-1">
                  <label for="stima_max" class="form-label">Max</label>
                  <input type="text" class="form-control form-control-sm catageo-valore"
                         id="stima_max" name="stima_max" inputmode="numeric">
                </div>

                <div class="col-md-3">
                  <label for="fase" class="form-label">Fase</label>
                  <select class="form-select form-select-sm" id="fase" name="fase">
                    <?php foreach (Biospeleologia::RUOLI_COLONIA as $valore => $etichetta): ?>
                      <option value="<?= $valore ?>"
                        <?= (string) $colonia['ruolo'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label for="temperatura" class="form-label">Temperatura</label>
                  <input type="text" class="form-control form-control-sm" id="temperatura"
                         name="temperatura" inputmode="decimal">
                </div>
                <div class="col-md-3">
                  <label for="rilevatore" class="form-label">Rilevatore</label>
                  <select class="form-select form-select-sm" id="rilevatore" name="rilevatore">
                    <option value="">—</option>
                    <?php foreach (Esploratori::elenco(true) as $e): ?>
                      <option value="<?= Testo::esc((string) $e['id']) ?>">
                        <?= Testo::esc(Esploratori::etichetta($e)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="noteConteggio" class="form-label">Note</label>
                  <input type="text" class="form-control form-control-sm" id="noteConteggio" name="note">
                </div>
                <div class="col-md-1">
                  <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-plus-lg"></i>
                  </button>
                </div>

                <div class="col-12">
                  <div class="catageo-nota">
                    Indicare il numero esatto oppure la stima minima e massima:
                    almeno uno dei due e obbligatorio.
                  </div>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header"><h2 class="h6 mb-0">La colonia</h2></div>
          <div class="card-body">
            <dl class="row catageo-dl mb-0">
              <dt class="col-5 fw-normal text-body-secondary">Specie</dt>
              <dd class="col-7"><em><?= Testo::esc((string) $colonia['specie']) ?></em></dd>

              <?php if ((string) $colonia['specieAggiuntive'] !== ''): ?>
                <dt class="col-5 fw-normal text-body-secondary">Altre specie</dt>
                <dd class="col-7"><em><?= Testo::esc((string) $colonia['specieAggiuntive']) ?></em></dd>
              <?php endif; ?>

              <dt class="col-5 fw-normal text-body-secondary">Ruolo</dt>
              <dd class="col-7">
                <?= Testo::esc(Biospeleologia::RUOLI_COLONIA[(string) $colonia['ruolo']] ?? '') ?>
              </dd>

              <dt class="col-5 fw-normal text-body-secondary">Zona</dt>
              <dd class="col-7"><?= Testo::esc((string) $colonia['zonaCavita']) ?></dd>

              <dt class="col-5 fw-normal text-body-secondary">Consistenza</dt>
              <dd class="col-7"><?= Testo::esc((string) $colonia['consistenzaStimata']) ?></dd>

              <dt class="col-5 fw-normal text-body-secondary">Andamento</dt>
              <dd class="col-7"><?= Testo::esc(Biospeleologia::TREND[(string) $colonia['trend']] ?? '') ?></dd>

              <dt class="col-5 fw-normal text-body-secondary">Periodo critico</dt>
              <dd class="col-7">
                <?php if ((string) $colonia['periodoCriticoDal'] !== ''): ?>
                  <span class="catageo-valore"><?= Testo::esc((string) $colonia['periodoCriticoDal']) ?></span>
                  → <span class="catageo-valore"><?= Testo::esc((string) $colonia['periodoCriticoAl']) ?></span>
                  <div class="catageo-nota">ricorrente ogni anno</div>
                <?php else: ?>
                  <span class="text-body-tertiary">non indicato</span>
                <?php endif; ?>
              </dd>
            </dl>

            <?php if ((string) $colonia['prescrizioni'] !== ''): ?>
              <hr>
              <div class="fw-semibold">Prescrizioni</div>
              <?= nl2br(Testo::esc((string) $colonia['prescrizioni'])) ?>
            <?php endif; ?>

            <?php if ((string) $colonia['riferimentoNormativo'] !== ''): ?>
              <hr>
              <div class="catageo-nota">
                <?= Testo::esc((string) $colonia['riferimentoNormativo']) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="catageo-nota">
          File dei conteggi:
          <span class="catageo-valore"><?= Testo::esc((string) $colonia['serieConteggi']) ?></span>
        </div>
      </div>
    </div>

    <?php
    return;
}

// ============================================================================
//  MODULI
// ============================================================================

if ($azione === 'osservazione' && $puoCompilare) {
    $voce = $id !== '' ? Biospeleologia::osservazione($codice, $id) : null;
    if ($id !== '' && $voce === null) {
        Auth::messaggio('errore', 'Osservazione non trovata.');
        header('Location: ' . $ritorno);
        exit;
    }
    $voce ??= Biospeleologia::CAMPI_OSSERVAZIONE;

    $titolo = ($id !== '' ? 'Modifica osservazione' : 'Nuova osservazione') . ' — ' . $codice;
    $v = static fn (string $c): string => Testo::esc((string) ($voce[$c] ?? ''));
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1><?= $id !== '' ? 'Modifica osservazione' : 'Nuova osservazione' ?></h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
        </p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
        <i class="bi bi-x-lg"></i> Annulla
      </a>
    </div>

    <form method="post" action="index.php?p=biospeleologia&amp;codice=<?= urlencode($codice) ?>"
          class="needs-validation" novalidate>
      <?= Auth::campoToken() ?>
      <input type="hidden" name="operazione" value="salvaOsservazione">
      <input type="hidden" name="id" value="<?= Testo::esc($id) ?>">

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Cosa e stato osservato</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="nomeScientifico" class="form-label">Nome scientifico</label>
                  <input type="text" class="form-control fst-italic" id="nomeScientifico"
                         name="nomeScientifico" value="<?= $v('nomeScientifico') ?>"
                         placeholder="Rhinolophus ferrumequinum">
                </div>
                <div class="col-md-6">
                  <label for="nomeComune" class="form-label">Nome comune</label>
                  <input type="text" class="form-control" id="nomeComune" name="nomeComune"
                         value="<?= $v('nomeComune') ?>">
                  <div class="catageo-nota">Almeno uno dei due nomi e obbligatorio.</div>
                </div>

                <div class="col-md-4">
                  <label for="gruppoTassonomico" class="form-label">Gruppo</label>
                  <select class="form-select" id="gruppoTassonomico" name="gruppoTassonomico">
                    <?php foreach (Biospeleologia::GRUPPI_TASSONOMICI as $valore => $etichetta): ?>
                      <option value="<?= Testo::esc($valore) ?>"
                        <?= (string) $voce['gruppoTassonomico'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-8">
                  <label for="categoriaEcologica" class="form-label">Categoria ecologica</label>
                  <select class="form-select" id="categoriaEcologica" name="categoriaEcologica">
                    <?php foreach (Biospeleologia::CATEGORIE_ECOLOGICHE as $valore => $etichetta): ?>
                      <option value="<?= Testo::esc($valore) ?>"
                        <?= (string) $voce['categoriaEcologica'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-4">
                  <label for="classe" class="form-label">Classe</label>
                  <input type="text" class="form-control" id="classe" name="classe" value="<?= $v('classe') ?>">
                </div>
                <div class="col-md-4">
                  <label for="ordine" class="form-label">Ordine</label>
                  <input type="text" class="form-control" id="ordine" name="ordine" value="<?= $v('ordine') ?>">
                </div>
                <div class="col-md-4">
                  <label for="famiglia" class="form-label">Famiglia</label>
                  <input type="text" class="form-control" id="famiglia" name="famiglia" value="<?= $v('famiglia') ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Dove e come</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label for="data" class="form-label">Data</label>
                  <input type="date" class="form-control" id="data" name="data" value="<?= $v('data') ?>">
                </div>
                <div class="col-md-2">
                  <label for="ora" class="form-label">Ora</label>
                  <input type="time" class="form-control" id="ora" name="ora" value="<?= $v('ora') ?>">
                </div>
                <div class="col-md-4">
                  <label for="zonaCavita" class="form-label">Zona della cavità</label>
                  <input type="text" class="form-control" id="zonaCavita" name="zonaCavita"
                         value="<?= $v('zonaCavita') ?>">
                </div>
                <div class="col-md-3">
                  <label for="puntoMisura" class="form-label">Punto di misura</label>
                  <select class="form-select" id="puntoMisura" name="puntoMisura">
                    <option value="">—</option>
                    <?php foreach (Scientifici::puntiMisura($codice) as $punto): ?>
                      <option value="<?= Testo::esc((string) $punto['id']) ?>"
                        <?= (string) $voce['puntoMisura'] === (string) $punto['id'] ? 'selected' : '' ?>>
                        <?= Testo::esc((string) $punto['id'] . ' ' . (string) $punto['nome']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="catageo-nota">Gli stessi punti dei dati scientifici.</div>
                </div>

                <div class="col-md-3">
                  <label for="numeroIndividui" class="form-label">Individui</label>
                  <input type="text" class="form-control catageo-valore" id="numeroIndividui"
                         name="numeroIndividui" value="<?= $v('numeroIndividui') ?>" inputmode="numeric">
                </div>
                <div class="col-md-4">
                  <label for="metodo" class="form-label">Metodo</label>
                  <input type="text" class="form-control" id="metodo" name="metodo" value="<?= $v('metodo') ?>">
                </div>
                <div class="col-md-5">
                  <label for="rilevatore" class="form-label">Rilevatore</label>
                  <select class="form-select" id="rilevatore" name="rilevatore">
                    <option value="">—</option>
                    <?php foreach (Esploratori::elenco(true) as $e): ?>
                      <option value="<?= Testo::esc((string) $e['id']) ?>"
                        <?= (string) $voce['rilevatore'] === (string) $e['id'] ? 'selected' : '' ?>>
                        <?= Testo::esc(Esploratori::etichetta($e)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12">
                  <label for="determinatore" class="form-label">Determinazione</label>
                  <input type="text" class="form-control" id="determinatore" name="determinatore"
                         value="<?= $v('determinatore') ?>"
                         placeholder="Chi ha confermato l'identificazione">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Tutela</h2></div>
            <div class="card-body">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="specieProtetta" name="specieProtetta"
                       value="1" <?= (string) $voce['specieProtetta'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="specieProtetta">Specie protetta</label>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="endemismo" name="endemismo"
                       value="1" <?= (string) $voce['endemismo'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="endemismo">Endemismo</label>
              </div>

              <div class="mb-3">
                <label for="direttivaHabitat" class="form-label">Direttiva Habitat</label>
                <input type="text" class="form-control" id="direttivaHabitat" name="direttivaHabitat"
                       value="<?= $v('direttivaHabitat') ?>" placeholder="All. II e IV">
              </div>

              <div>
                <label for="listaRossaIucn" class="form-label">Lista Rossa IUCN</label>
                <select class="form-select" id="listaRossaIucn" name="listaRossaIucn">
                  <?php foreach (Biospeleologia::IUCN as $valore => $etichetta): ?>
                    <option value="<?= Testo::esc($valore) ?>"
                      <?= (string) $voce['listaRossaIucn'] === $valore ? 'selected' : '' ?>>
                      <?= Testo::esc($etichetta) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Altro</h2></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="fotoRif" class="form-label">Foto</label>
                <select class="form-select" id="fotoRif" name="fotoRif">
                  <option value="">— nessuna —</option>
                  <?php foreach (Risorse::elenco($codice, 'FO') as $foto): ?>
                    <?php $rif = Sezioni::riferimento('FO', (int) $foto['progressivo']); ?>
                    <option value="<?= Testo::esc($rif) ?>"
                      <?= (string) $voce['fotoRif'] === $rif ? 'selected' : '' ?>>
                      <?= Testo::esc($rif . ' — ' . (string) $foto['titolo']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="note" class="form-label">Note</label>
                <textarea class="form-control" id="note" name="note" rows="4"><?= $v('note') ?></textarea>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i> Salva l'osservazione
          </button>
        </div>
      </div>
    </form>

    <?php
    return;
}

if ($azione === 'modificaColonia' && $puoCompilare) {
    $voce = $id !== '' ? Biospeleologia::colonia($codice, $id) : null;
    if ($id !== '' && $voce === null) {
        Auth::messaggio('errore', 'Colonia non trovata.');
        header('Location: ' . $ritorno);
        exit;
    }
    $voce ??= Biospeleologia::CAMPI_COLONIA;

    $titolo = ($id !== '' ? 'Modifica colonia' : 'Nuova colonia') . ' — ' . $codice;
    $v = static fn (string $c): string => Testo::esc((string) ($voce[$c] ?? ''));
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1><?= $id !== '' ? 'Modifica colonia' : 'Nuova colonia di chirotteri' ?></h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
        </p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
        <i class="bi bi-x-lg"></i> Annulla
      </a>
    </div>

    <form method="post" action="index.php?p=biospeleologia&amp;codice=<?= urlencode($codice) ?>"
          class="needs-validation" novalidate>
      <?= Auth::campoToken() ?>
      <input type="hidden" name="operazione" value="salvaColonia">
      <input type="hidden" name="id" value="<?= Testo::esc($id) ?>">

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">La colonia</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-7">
                  <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="nome" name="nome" required
                         value="<?= $v('nome') ?>" placeholder="Colonia della sala grande">
                </div>
                <div class="col-md-5">
                  <label for="ruolo" class="form-label">Ruolo della cavità</label>
                  <select class="form-select" id="ruolo" name="ruolo">
                    <?php foreach (Biospeleologia::RUOLI_COLONIA as $valore => $etichetta): ?>
                      <option value="<?= Testo::esc($valore) ?>"
                        <?= (string) $voce['ruolo'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-6">
                  <label for="specie" class="form-label">Specie principale</label>
                  <input type="text" class="form-control fst-italic" id="specie" name="specie"
                         value="<?= $v('specie') ?>">
                </div>
                <div class="col-md-6">
                  <label for="specieAggiuntive" class="form-label">Altre specie</label>
                  <input type="text" class="form-control fst-italic" id="specieAggiuntive"
                         name="specieAggiuntive" value="<?= $v('specieAggiuntive') ?>"
                         placeholder="separate da virgola">
                </div>

                <div class="col-md-6">
                  <label for="zonaCavita" class="form-label">Zona della cavità</label>
                  <input type="text" class="form-control" id="zonaCavita" name="zonaCavita"
                         value="<?= $v('zonaCavita') ?>">
                </div>
                <div class="col-md-3">
                  <label for="consistenzaStimata" class="form-label">Consistenza</label>
                  <input type="text" class="form-control" id="consistenzaStimata"
                         name="consistenzaStimata" value="<?= $v('consistenzaStimata') ?>"
                         placeholder="30-60">
                </div>
                <div class="col-md-3">
                  <label for="trend" class="form-label">Andamento</label>
                  <select class="form-select" id="trend" name="trend">
                    <?php foreach (Biospeleologia::TREND as $valore => $etichetta): ?>
                      <option value="<?= $valore ?>" <?= (string) $voce['trend'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Disturbo e periodo critico</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label for="periodoCriticoDal" class="form-label">Dal (MM-GG)</label>
                  <input type="text" class="form-control catageo-valore" id="periodoCriticoDal"
                         name="periodoCriticoDal" value="<?= $v('periodoCriticoDal') ?>"
                         pattern="[0-9]{2}-[0-9]{2}" placeholder="11-01">
                </div>
                <div class="col-md-3">
                  <label for="periodoCriticoAl" class="form-label">Al (MM-GG)</label>
                  <input type="text" class="form-control catageo-valore" id="periodoCriticoAl"
                         name="periodoCriticoAl" value="<?= $v('periodoCriticoAl') ?>"
                         pattern="[0-9]{2}-[0-9]{2}" placeholder="03-31">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                  <div class="catageo-nota">
                    Senza anno, perché il periodo si ripete ogni stagione. Può
                    scavalcare il capodanno: 11-01 → 03-31 e uno svernamento.
                  </div>
                </div>

                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="accessoSconsigliato"
                           name="accessoSconsigliato" value="1"
                           <?= (string) $voce['accessoSconsigliato'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="accessoSconsigliato">
                      Accesso sconsigliato durante il periodo critico
                    </label>
                  </div>
                </div>

                <div class="col-12">
                  <label for="prescrizioni" class="form-label">Prescrizioni</label>
                  <textarea class="form-control" id="prescrizioni" name="prescrizioni"
                            rows="3"><?= $v('prescrizioni') ?></textarea>
                  <div class="catageo-nota">Compaiono nell'avviso in cima alla scheda.</div>
                </div>

                <div class="col-12">
                  <label for="riferimentoNormativo" class="form-label">Riferimento normativo</label>
                  <input type="text" class="form-control" id="riferimentoNormativo"
                         name="riferimentoNormativo" value="<?= $v('riferimentoNormativo') ?>"
                         placeholder="Dir. 92/43/CEE; EUROBATS; L. 157/1992">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Riservatezza</h2></div>
            <div class="card-body">
              <select class="form-select" id="riservatezza" name="riservatezza">
                <option value="riservata" <?= (string) $voce['riservatezza'] !== 'pubblica' ? 'selected' : '' ?>>
                  Riservata
                </option>
                <option value="pubblica" <?= (string) $voce['riservatezza'] === 'pubblica' ? 'selected' : '' ?>>
                  Pubblica
                </option>
              </select>
              <div class="catageo-nota mt-2">
                Indipendente da quella dell'ipogeo e prevalente su di essa: una
                cavità pubblica può ospitare una colonia visibile solo a OPE e ADM.
                L'avviso di periodo critico compare comunque a tutti, ma senza
                nome, specie e zona.
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Altro</h2></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="biblioRif" class="form-label">Fonte bibliografica</label>
                <select class="form-select" id="biblioRif" name="biblioRif">
                  <option value="">— nessuna —</option>
                  <?php foreach (Bibliografia::elenco($codice) as $fonte): ?>
                    <?php $rif = Sezioni::riferimento('BB', (int) $fonte['progressivo']); ?>
                    <option value="<?= Testo::esc($rif) ?>"
                      <?= (string) $voce['biblioRif'] === $rif ? 'selected' : '' ?>>
                      <?= Testo::esc($rif) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="noteColonia" class="form-label">Note</label>
                <textarea class="form-control" id="noteColonia" name="note" rows="4"><?= $v('note') ?></textarea>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i> Salva la colonia
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

$colonie      = Biospeleologia::colonieVisibili($codice);
$nascoste     = count(Biospeleologia::colonie($codice)) - count($colonie);
$osservazioni = Biospeleologia::osservazioni($codice);
$titolo       = 'Biospeleologia — ' . $codice;
?>

<div class="catageo-intestazione">
  <div>
    <h1>Biospeleologia</h1>
    <p class="text-body-secondary mb-0">
      <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
      · <?= count($colonie) ?> coloni<?= count($colonie) === 1 ? 'a' : 'e' ?>,
      <?= count($osservazioni) ?> osservazion<?= count($osservazioni) === 1 ? 'e' : 'i' ?>
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($puoCompilare): ?>
      <a class="btn btn-outline-secondary"
         href="index.php?p=biospeleologia&amp;codice=<?= urlencode($codice) ?>&amp;azione=modificaColonia">
        <i class="bi bi-plus-lg"></i> Colonia
      </a>
      <a class="btn btn-primary"
         href="index.php?p=biospeleologia&amp;codice=<?= urlencode($codice) ?>&amp;azione=osservazione">
        <i class="bi bi-plus-lg"></i> Osservazione
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary"
       href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode($codice) ?>">
      <i class="bi bi-arrow-left"></i> Scheda
    </a>
  </div>
</div>

<?php catageoAvvisi(Biospeleologia::avvisi($codice)); ?>

<div class="card mb-4">
  <div class="card-header"><h2 class="h6 mb-0">Colonie di chirotteri</h2></div>
  <?php if ($colonie === []): ?>
    <div class="card-body">
      <p class="text-body-secondary mb-0">
        <?php if ($nascoste > 0): ?>
          Nessuna colonia consultabile con il livello di utenza in uso.
        <?php else: ?>
          Nessuna colonia registrata. Una colonia porta con se il periodo critico,
          da cui dipende l'avviso in cima alla scheda.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th style="width:4rem">Id</th>
            <th>Colonia</th>
            <th>Specie</th>
            <th>Ruolo</th>
            <th>Periodo critico</th>
            <th>Andamento</th>
            <?php if ($puoCompilare): ?><th class="text-end">Azioni</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($colonie as $colonia): ?>
            <?php $critico = Biospeleologia::inPeriodoCritico($colonia); ?>
            <tr>
              <td><span class="catageo-valore catageo-riferimento"><?= Testo::esc((string) $colonia['id']) ?></span></td>
              <td>
                <a href="index.php?p=biospeleologia&amp;codice=<?= urlencode($codice) ?>&amp;azione=colonia&amp;id=<?= urlencode((string) $colonia['id']) ?>">
                  <?= Testo::esc((string) $colonia['nome']) ?>
                </a>
                <?php if ((string) $colonia['riservatezza'] === 'riservata'): ?>
                  <i class="bi bi-eye-slash text-warning" title="Colonia riservata"></i>
                <?php endif; ?>
              </td>
              <td><em><?= Testo::esc((string) $colonia['specie']) ?></em></td>
              <td><?= Testo::esc(Biospeleologia::RUOLI_COLONIA[(string) $colonia['ruolo']] ?? '') ?></td>
              <td>
                <?php if ((string) $colonia['periodoCriticoDal'] !== ''): ?>
                  <span class="catageo-valore"><?= Testo::esc((string) $colonia['periodoCriticoDal']) ?></span>
                  → <span class="catageo-valore"><?= Testo::esc((string) $colonia['periodoCriticoAl']) ?></span>
                  <?php if ($critico): ?>
                    <span class="badge text-bg-danger">in corso</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-body-tertiary">—</span>
                <?php endif; ?>
              </td>
              <td><?= Testo::esc(Biospeleologia::TREND[(string) $colonia['trend']] ?? '') ?></td>
              <?php if ($puoCompilare): ?>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="index.php?p=biospeleologia&amp;codice=<?= urlencode($codice) ?>&amp;azione=modificaColonia&amp;id=<?= urlencode((string) $colonia['id']) ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="<?= Testo::esc($ritorno) ?>">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="eliminaColonia">
                      <input type="hidden" name="id" value="<?= Testo::esc((string) $colonia['id']) ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit"
                              data-catageo-conferma="Togliere la colonia? Il file dei conteggi viene spostato in _rimossi e resta recuperabile.">
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
  <?php endif; ?>

  <?php if ($nascoste > 0): ?>
    <div class="card-body catageo-nota">
      <?= $nascoste ?> coloni<?= $nascoste === 1 ? 'a' : 'e' ?> non
      consultabil<?= $nascoste === 1 ? 'e' : 'i' ?> con il livello di utenza in uso.
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2 class="h6 mb-0">Osservazioni faunistiche</h2></div>
  <?php if ($osservazioni === []): ?>
    <div class="card-body">
      <p class="text-body-secondary mb-0">Nessuna osservazione registrata.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th style="width:5rem">Id</th>
            <th style="width:7rem">Data</th>
            <th>Taxon</th>
            <th>Gruppo</th>
            <th>Zona</th>
            <th class="text-end">Individui</th>
            <th>Tutela</th>
            <?php if ($puoCompilare): ?><th class="text-end">Azioni</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($osservazioni as $voce): ?>
            <tr>
              <td><span class="catageo-valore catageo-riferimento"><?= Testo::esc((string) $voce['id']) ?></span></td>
              <td><?= Testo::esc((string) $voce['data']) ?></td>
              <td>
                <?php if ((string) $voce['nomeScientifico'] !== ''): ?>
                  <em><?= Testo::esc((string) $voce['nomeScientifico']) ?></em>
                <?php endif; ?>
                <?php if ((string) $voce['nomeComune'] !== ''): ?>
                  <div class="catageo-nota"><?= Testo::esc((string) $voce['nomeComune']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?= Testo::esc(Biospeleologia::GRUPPI_TASSONOMICI[(string) $voce['gruppoTassonomico']] ?? '') ?>
                <?php if ((string) $voce['categoriaEcologica'] !== ''): ?>
                  <div class="catageo-nota"><?= Testo::esc((string) $voce['categoriaEcologica']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= Testo::esc((string) $voce['zonaCavita']) ?></td>
              <td class="text-end catageo-valore"><?= Testo::esc((string) $voce['numeroIndividui']) ?></td>
              <td>
                <?php if ((string) $voce['specieProtetta'] === '1'): ?>
                  <span class="badge text-bg-success">protetta</span>
                <?php endif; ?>
                <?php if ((string) $voce['endemismo'] === '1'): ?>
                  <span class="badge text-bg-info">endemismo</span>
                <?php endif; ?>
                <?php if ((string) $voce['listaRossaIucn'] !== ''): ?>
                  <span class="badge text-bg-light border" title="Lista Rossa IUCN">
                    <?= Testo::esc((string) $voce['listaRossaIucn']) ?>
                  </span>
                <?php endif; ?>
              </td>
              <?php if ($puoCompilare): ?>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="index.php?p=biospeleologia&amp;codice=<?= urlencode($codice) ?>&amp;azione=osservazione&amp;id=<?= urlencode((string) $voce['id']) ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="<?= Testo::esc($ritorno) ?>">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="eliminaOsservazione">
                      <input type="hidden" name="id" value="<?= Testo::esc((string) $voce['id']) ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit"
                              data-catageo-conferma="Togliere l'osservazione <?= Testo::esc((string) $voce['id']) ?>?">
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
  <?php endif; ?>
</div>
