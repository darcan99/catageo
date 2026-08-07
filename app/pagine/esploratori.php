<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/esploratori.php
 *  Descrizione ..: Gestione dell'anagrafica degli esploratori, con le
 *                  appartenenze storicizzate ai gruppi speleologici.
 *
 *                  Le righe di appartenenza sono rese in numero fisso
 *                  (esistenti piu tre vuote) invece di essere aggiunte via
 *                  JavaScript: il form resta funzionante anche senza script e
 *                  il codice non deve ricostruire indici lato client.
 *  Versione .....: 0.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('anagrafiche');

/**
 * Righe di appartenenza vuote offerte in aggiunta a quelle esistenti.
 * Quattro e non una: lo stesso gruppo puo ricorrere e le iscrizioni
 * contemporanee sono la norma, quindi le righe servono davvero.
 */
const RIGHE_APPARTENENZA_LIBERE = 4;

$azione      = isset($_GET['azione']) ? (string) $_GET['azione'] : 'elenco';
$idRichiesto = isset($_GET['id']) ? (string) $_GET['id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    $operazione = (string) ($_POST['operazione'] ?? '');

    $dati = [
        'cognome'    => (string) ($_POST['cognome'] ?? ''),
        'nome'       => (string) ($_POST['nome'] ?? ''),
        'soprannome' => (string) ($_POST['soprannome'] ?? ''),
        'email'      => (string) ($_POST['email'] ?? ''),
        'telefono'   => (string) ($_POST['telefono'] ?? ''),
        'qualifiche' => (string) ($_POST['qualifiche'] ?? ''),
        'note'       => (string) ($_POST['note'] ?? ''),
        'attivo'     => !empty($_POST['attivo']),
        'gruppi'     => is_array($_POST['gruppi'] ?? null) ? $_POST['gruppi'] : [],
    ];

    try {
        switch ($operazione) {
            case 'crea':
                $id = Esploratori::crea($dati);
                Log::modifica('crea', '', '', 'esploratori', $id . ' ' . $dati['cognome']);
                Auth::messaggio('successo', 'Esploratore censito.');
                break;

            case 'aggiorna':
                $id = (string) ($_POST['id'] ?? '');
                Esploratori::aggiorna($id, $dati);
                Log::modifica('modifica', '', '', 'esploratori', $id);
                Auth::messaggio('successo', 'Esploratore aggiornato.');
                break;

            case 'elimina':
                $id = (string) ($_POST['id'] ?? '');
                Esploratori::elimina($id);
                Log::modifica('elimina', '', '', 'esploratori', $id);
                Auth::messaggio('successo', 'Esploratore eliminato.');
                break;

            default:
                throw new AnagraficaEccezione('Operazione non riconosciuta.');
        }
        header('Location: index.php?p=esploratori');
        exit;

    } catch (AnagraficaEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
        $ritorno = $operazione === 'aggiorna'
            ? 'index.php?p=esploratori&azione=modifica&id=' . urlencode((string) ($_POST['id'] ?? ''))
            : ($operazione === 'crea' ? 'index.php?p=esploratori&azione=nuovo' : 'index.php?p=esploratori');
        header('Location: ' . $ritorno);
        exit;
    }
}

$elenco = Esploratori::elenco();
$gruppi = Gruppi::elenco();

$inModifica = null;
if ($azione === 'modifica' && $idRichiesto !== '') {
    $inModifica = Esploratori::trova($idRichiesto);
    if ($inModifica === null) {
        Auth::messaggio('errore', 'Esploratore non trovato.');
        $azione = 'elenco';
    }
}
?>

<div class="catageo-intestazione">
  <div>
    <h1>Esploratori</h1>
    <p class="text-body-secondary mb-0">
      <?= count($elenco) ?> esplorator<?= count($elenco) === 1 ? 'e' : 'i' ?> ·
      <a class="link-secondary" href="index.php?p=anagrafiche">tutte le anagrafiche</a>
    </p>
  </div>
  <?php if ($azione === 'elenco'): ?>
    <a class="btn btn-primary" href="index.php?p=esploratori&amp;azione=nuovo">
      <i class="bi bi-plus-lg"></i> Nuovo esploratore
    </a>
  <?php else: ?>
    <a class="btn btn-outline-secondary" href="index.php?p=esploratori">
      <i class="bi bi-arrow-left"></i> Torna all'elenco
    </a>
  <?php endif; ?>
</div>

<?php if ($azione === 'nuovo' || $inModifica !== null): ?>
  <?php
  $m = $inModifica;
  $v = static fn (string $c): string => Testo::esc((string) ($m[$c] ?? ($_POST[$c] ?? '')));

  // Righe di appartenenza: quelle esistenti piu alcune vuote.
  $appartenenze = $m !== null ? $m['gruppi'] : [];
  for ($i = 0; $i < RIGHE_APPARTENENZA_LIBERE; $i++) {
      $appartenenze[] = ['id' => '', 'dal' => '', 'al' => ''];
  }
  ?>

  <div class="row">
    <div class="col-lg-9">
      <div class="card">
        <div class="card-header">
          <h2 class="h6 mb-0">
            <?= $m !== null ? 'Modifica ' . Testo::esc(Esploratori::etichetta($m)) : 'Nuovo esploratore' ?>
          </h2>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?p=esploratori" class="needs-validation" novalidate>
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="<?= $m !== null ? 'aggiorna' : 'crea' ?>">
            <?php if ($m !== null): ?>
              <input type="hidden" name="id" value="<?= Testo::esc((string) $m['id']) ?>">
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-md-4">
                <label for="cognome" class="form-label">Cognome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="cognome" name="cognome" required
                       maxlength="80" value="<?= $v('cognome') ?>">
                <div class="invalid-feedback">Il cognome e obbligatorio.</div>
              </div>
              <div class="col-md-4">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome" required
                       maxlength="80" value="<?= $v('nome') ?>">
                <div class="invalid-feedback">Il nome e obbligatorio.</div>
              </div>
              <div class="col-md-4">
                <label for="soprannome" class="form-label">Soprannome</label>
                <input type="text" class="form-control" id="soprannome" name="soprannome"
                       maxlength="80" value="<?= $v('soprannome') ?>">
                <div class="catageo-nota">Serve a distinguere gli omonimi negli elenchi.</div>
              </div>

              <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email"
                       maxlength="150" value="<?= $v('email') ?>">
                <div class="invalid-feedback">Indirizzo email non valido.</div>
              </div>
              <div class="col-md-6">
                <label for="telefono" class="form-label">Telefono</label>
                <input type="text" class="form-control" id="telefono" name="telefono"
                       maxlength="40" value="<?= $v('telefono') ?>">
              </div>

              <div class="col-12">
                <label for="qualifiche" class="form-label">Qualifiche</label>
                <input type="text" class="form-control" id="qualifiche" name="qualifiche" maxlength="250"
                       value="<?= Testo::esc($m !== null ? implode(', ', $m['qualifiche']) : (string) ($_POST['qualifiche'] ?? '')) ?>">
                <div class="catageo-nota">Separate da virgola, es. istruttore, rilevatore, fotografo.</div>
              </div>
            </div>

            <hr class="my-4">

            <h3 class="h6">Appartenenza ai gruppi</h3>
            <p class="catageo-nota mb-3">
              Gli anni rendono l'appartenenza storicizzata: un diario del 1998
              resta attribuito al gruppo di allora anche se la persona ne ha
              cambiato. Lasciare vuoto l'anno finale se l'appartenenza e in corso.
            </p>
            <p class="catageo-nota mb-3">
              <strong>Lo stesso gruppo può comparire più volte</strong>, con periodi
              diversi: chi lascia un gruppo e vi rientra dopo qualche anno ha due
              periodi distinti. Ed e normale essere iscritti a più gruppi
              contemporaneamente, quindi i periodi di gruppi diversi possono
              sovrapporsi liberamente. L'unico caso rifiutato e l'accavallamento
              di due periodi dello <em>stesso</em> gruppo, che sarebbe
              contraddittorio.
            </p>

            <?php if ($gruppi === []): ?>
              <div class="alert alert-warning">
                Nessun gruppo censito: <a href="index.php?p=gruppi&amp;azione=nuovo">crea prima un gruppo</a>
                per poter registrare le appartenenze.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm catageo-tabella">
                  <thead>
                    <tr>
                      <th scope="col">Gruppo</th>
                      <th scope="col" style="width:8rem">Dal (anno)</th>
                      <th scope="col" style="width:8rem">Al (anno)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($appartenenze as $i => $appartenenza): ?>
                      <tr>
                        <td>
                          <select class="form-select form-select-sm" name="gruppi[<?= $i ?>][id]">
                            <option value="">— nessuno —</option>
                            <?php foreach ($gruppi as $gruppo): ?>
                              <option value="<?= Testo::esc((string) $gruppo['id']) ?>"
                                <?= ((string) $appartenenza['id'] === (string) $gruppo['id']) ? 'selected' : '' ?>>
                                <?= Testo::esc(Gruppi::etichetta($gruppo)) ?><?= $gruppo['attivo'] ? '' : ' (disattivato)' ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td>
                          <input type="text" class="form-control form-control-sm catageo-valore"
                                 name="gruppi[<?= $i ?>][dal]" maxlength="4" pattern="[0-9]{4}"
                                 value="<?= Testo::esc((string) $appartenenza['dal']) ?>">
                        </td>
                        <td>
                          <input type="text" class="form-control form-control-sm catageo-valore"
                                 name="gruppi[<?= $i ?>][al]" maxlength="4" pattern="[0-9]{4}"
                                 value="<?= Testo::esc((string) $appartenenza['al']) ?>">
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>

            <hr class="my-4">

            <div class="mb-3">
              <label for="note" class="form-label">Note</label>
              <textarea class="form-control" id="note" name="note" rows="3"><?= Testo::esc($m !== null ? (string) $m['note'] : (string) ($_POST['note'] ?? '')) ?></textarea>
            </div>

            <div class="form-check form-switch mb-4">
              <input class="form-check-input" type="checkbox" role="switch" id="attivo" name="attivo" value="1"
                     <?= ($m === null || $m['attivo']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="attivo">Esploratore attivo</label>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> <?= $m !== null ? 'Salva modifiche' : 'Censisci esploratore' ?>
              </button>
              <a class="btn btn-outline-secondary" href="index.php?p=esploratori">Annulla</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($elenco === []): ?>

  <div class="card">
    <div class="card-body text-center py-5">
      <i class="bi bi-person-badge fs-1 text-body-tertiary" aria-hidden="true"></i>
      <p class="mt-3 mb-3 text-body-secondary">Nessun esploratore censito.</p>
      <a class="btn btn-primary" href="index.php?p=esploratori&amp;azione=nuovo">
        <i class="bi bi-plus-lg"></i> Censisci il primo esploratore
      </a>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th scope="col">Esploratore</th>
            <th scope="col">Gruppi</th>
            <th scope="col">Qualifiche</th>
            <th scope="col">Contatti</th>
            <th scope="col">Stato</th>
            <th scope="col" class="text-end">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($elenco as $esploratore): ?>
            <tr>
              <td>
                <span class="fw-semibold"><?= Testo::esc(Esploratori::etichetta($esploratore)) ?></span>
                <div class="small text-body-secondary catageo-valore"><?= Testo::esc((string) $esploratore['id']) ?></div>
              </td>
              <td class="small">
                <?php if ($esploratore['gruppi'] === []): ?>
                  <span class="text-body-tertiary">—</span>
                <?php else: ?>
                  <?php foreach ($esploratore['gruppi'] as $appartenenza): ?>
                    <?php
                    $gruppo   = Gruppi::trova((string) $appartenenza['id']);
                    $inCorso  = Esploratori::appartenenzaInCorso($appartenenza);
                    $dal      = (string) $appartenenza['dal'];
                    $al       = (string) $appartenenza['al'];
                    $periodo  = $dal === '' && $al === '' ? '' : ($dal !== '' ? $dal : '?') . '–' . ($al !== '' ? $al : '');
                    ?>
                    <div>
                      <span class="<?= $inCorso ? 'fw-semibold' : '' ?>">
                        <?= Testo::esc($gruppo !== null ? (string) $gruppo['sigla'] : (string) $appartenenza['id'] . ' (non trovato)') ?>
                      </span>
                      <?php if ($periodo !== ''): ?>
                        <span class="text-body-secondary catageo-valore"><?= Testo::esc($periodo) ?></span>
                      <?php endif; ?>
                      <?php if ($inCorso): ?>
                        <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">in corso</span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td class="small"><?= Testo::esc(implode(', ', $esploratore['qualifiche'])) ?></td>
              <td class="small"><?= Testo::esc((string) $esploratore['email']) ?></td>
              <td>
                <?php if ($esploratore['attivo']): ?>
                  <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                <?php else: ?>
                  <span class="text-body-secondary" title="disattivato"><i class="bi bi-slash-circle"></i></span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary"
                   href="index.php?p=esploratori&amp;azione=modifica&amp;id=<?= urlencode((string) $esploratore['id']) ?>">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" action="index.php?p=esploratori" class="d-inline">
                  <?= Auth::campoToken() ?>
                  <input type="hidden" name="operazione" value="elimina">
                  <input type="hidden" name="id" value="<?= Testo::esc((string) $esploratore['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"
                          data-catageo-conferma="Eliminare &quot;<?= Testo::esc(Esploratori::etichetta($esploratore)) ?>&quot;? Se e referenziato l'operazione verra rifiutata: in quel caso disattivalo.">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>
