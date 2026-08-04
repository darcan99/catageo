<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/gruppi.php
 *  Descrizione ..: Gestione dell'anagrafica dei gruppi speleologici.
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

$azione      = isset($_GET['azione']) ? (string) $_GET['azione'] : 'elenco';
$idRichiesto = isset($_GET['id']) ? (string) $_GET['id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    $operazione = (string) ($_POST['operazione'] ?? '');

    $dati = [
        'sigla'          => (string) ($_POST['sigla'] ?? ''),
        'nome'           => (string) ($_POST['nome'] ?? ''),
        'sedeComune'     => (string) ($_POST['sedeComune'] ?? ''),
        'sedeProvincia'  => (string) ($_POST['sedeProvincia'] ?? ''),
        'indirizzo'      => (string) ($_POST['indirizzo'] ?? ''),
        'email'          => (string) ($_POST['email'] ?? ''),
        'telefono'       => (string) ($_POST['telefono'] ?? ''),
        'sitoWeb'        => (string) ($_POST['sitoWeb'] ?? ''),
        'annoFondazione' => (string) ($_POST['annoFondazione'] ?? ''),
        'affiliazioni'   => (string) ($_POST['affiliazioni'] ?? ''),
        'note'           => (string) ($_POST['note'] ?? ''),
        'attivo'         => !empty($_POST['attivo']),
    ];

    try {
        switch ($operazione) {
            case 'crea':
                $id = Gruppi::crea($dati);
                Log::modifica('crea', '', '', 'gruppi', $id . ' ' . $dati['sigla']);
                Auth::messaggio('successo', 'Gruppo creato.');
                break;

            case 'aggiorna':
                $id = (string) ($_POST['id'] ?? '');
                Gruppi::aggiorna($id, $dati);
                Log::modifica('modifica', '', '', 'gruppi', $id);
                Auth::messaggio('successo', 'Gruppo aggiornato.');
                break;

            case 'elimina':
                $id = (string) ($_POST['id'] ?? '');
                Gruppi::elimina($id);
                Log::modifica('elimina', '', '', 'gruppi', $id);
                Auth::messaggio('successo', 'Gruppo eliminato.');
                break;

            default:
                throw new AnagraficaEccezione('Operazione non riconosciuta.');
        }
        header('Location: index.php?p=gruppi');
        exit;

    } catch (AnagraficaEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
        $ritorno = $operazione === 'aggiorna'
            ? 'index.php?p=gruppi&azione=modifica&id=' . urlencode((string) ($_POST['id'] ?? ''))
            : ($operazione === 'crea' ? 'index.php?p=gruppi&azione=nuovo' : 'index.php?p=gruppi');
        header('Location: ' . $ritorno);
        exit;
    }
}

$elenco     = Gruppi::elenco();
$inModifica = null;
if ($azione === 'modifica' && $idRichiesto !== '') {
    $inModifica = Gruppi::trova($idRichiesto);
    if ($inModifica === null) {
        Auth::messaggio('errore', 'Gruppo non trovato.');
        $azione = 'elenco';
    }
}
?>

<div class="catageo-intestazione">
  <div>
    <h1>Gruppi speleologici</h1>
    <p class="text-body-secondary mb-0">
      <?= count($elenco) ?> gruppo<?= count($elenco) === 1 ? '' : 'i' ?> ·
      <a class="link-secondary" href="index.php?p=anagrafiche">tutte le anagrafiche</a>
    </p>
  </div>
  <?php if ($azione === 'elenco'): ?>
    <a class="btn btn-primary" href="index.php?p=gruppi&amp;azione=nuovo">
      <i class="bi bi-plus-lg"></i> Nuovo gruppo
    </a>
  <?php else: ?>
    <a class="btn btn-outline-secondary" href="index.php?p=gruppi">
      <i class="bi bi-arrow-left"></i> Torna all'elenco
    </a>
  <?php endif; ?>
</div>

<?php if ($azione === 'nuovo' || $inModifica !== null): ?>
  <?php $m = $inModifica; $v = static fn (string $c): string => Testo::esc((string) ($m[$c] ?? ($_POST[$c] ?? ''))); ?>

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-transparent">
          <h2 class="h6 mb-0">
            <?= $m !== null ? 'Modifica gruppo ' . Testo::esc((string) $m['sigla']) : 'Nuovo gruppo' ?>
          </h2>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?p=gruppi" class="needs-validation" novalidate>
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="<?= $m !== null ? 'aggiorna' : 'crea' ?>">
            <?php if ($m !== null): ?>
              <input type="hidden" name="id" value="<?= Testo::esc((string) $m['id']) ?>">
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-md-3">
                <label for="sigla" class="form-label">Sigla <span class="text-danger">*</span></label>
                <input type="text" class="form-control catageo-valore" id="sigla" name="sigla" required
                       maxlength="20" pattern="[A-Za-z0-9.\-]{1,20}" value="<?= $v('sigla') ?>">
                <div class="invalid-feedback">Fino a 20 caratteri: lettere, cifre, punto, trattino.</div>
              </div>
              <div class="col-md-9">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome" required
                       maxlength="150" value="<?= $v('nome') ?>">
                <div class="invalid-feedback">Il nome e obbligatorio.</div>
              </div>

              <div class="col-md-5">
                <label for="sedeComune" class="form-label">Comune della sede</label>
                <input type="text" class="form-control" id="sedeComune" name="sedeComune"
                       maxlength="100" value="<?= $v('sedeComune') ?>">
              </div>
              <div class="col-md-2">
                <label for="sedeProvincia" class="form-label">Provincia</label>
                <input type="text" class="form-control catageo-valore" id="sedeProvincia" name="sedeProvincia"
                       maxlength="2" pattern="[A-Za-z]{2}" placeholder="RM" value="<?= $v('sedeProvincia') ?>">
                <div class="invalid-feedback">Sigla di due lettere.</div>
              </div>
              <div class="col-md-3">
                <label for="annoFondazione" class="form-label">Anno di fondazione</label>
                <input type="text" class="form-control catageo-valore" id="annoFondazione" name="annoFondazione"
                       maxlength="4" pattern="[0-9]{4}" placeholder="1957" value="<?= $v('annoFondazione') ?>">
                <div class="invalid-feedback">Quattro cifre.</div>
              </div>
              <div class="col-md-2">
                <label for="telefono" class="form-label">Telefono</label>
                <input type="text" class="form-control" id="telefono" name="telefono"
                       maxlength="40" value="<?= $v('telefono') ?>">
              </div>

              <div class="col-md-12">
                <label for="indirizzo" class="form-label">Indirizzo</label>
                <input type="text" class="form-control" id="indirizzo" name="indirizzo"
                       maxlength="200" value="<?= $v('indirizzo') ?>">
              </div>

              <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email"
                       maxlength="150" value="<?= $v('email') ?>">
                <div class="invalid-feedback">Indirizzo email non valido.</div>
              </div>
              <div class="col-md-6">
                <label for="sitoWeb" class="form-label">Sito web</label>
                <input type="url" class="form-control" id="sitoWeb" name="sitoWeb"
                       maxlength="200" placeholder="https://" value="<?= $v('sitoWeb') ?>">
                <div class="invalid-feedback">Indicare l'indirizzo per esteso, con https://</div>
              </div>

              <div class="col-md-12">
                <label for="affiliazioni" class="form-label">Affiliazioni</label>
                <input type="text" class="form-control" id="affiliazioni" name="affiliazioni"
                       list="affiliazioniSuggerite" maxlength="200"
                       value="<?= Testo::esc($m !== null ? implode(', ', $m['affiliazioni']) : (string) ($_POST['affiliazioni'] ?? '')) ?>">
                <datalist id="affiliazioniSuggerite">
                  <?php foreach (Gruppi::AFFILIAZIONI_SUGGERITE as $suggerita): ?>
                    <option value="<?= Testo::esc($suggerita) ?>"></option>
                  <?php endforeach; ?>
                </datalist>
                <div class="catageo-nota">Separate da virgola. Il campo e libero: i suggerimenti sono solo le piu comuni.</div>
              </div>

              <div class="col-12">
                <label for="note" class="form-label">Note</label>
                <textarea class="form-control" id="note" name="note" rows="4"><?= Testo::esc($m !== null ? (string) $m['note'] : (string) ($_POST['note'] ?? '')) ?></textarea>
                <div class="catageo-nota">Nessun limite di lunghezza.</div>
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="attivo" name="attivo" value="1"
                         <?= ($m === null || $m['attivo']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="attivo">Gruppo attivo</label>
                </div>
              </div>
            </div>

            <hr class="my-4">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> <?= $m !== null ? 'Salva modifiche' : 'Crea gruppo' ?>
              </button>
              <a class="btn btn-outline-secondary" href="index.php?p=gruppi">Annulla</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($elenco === []): ?>

  <div class="card">
    <div class="card-body text-center py-5">
      <i class="bi bi-people fs-1 text-body-tertiary" aria-hidden="true"></i>
      <p class="mt-3 mb-3 text-body-secondary">Nessun gruppo censito.</p>
      <a class="btn btn-primary" href="index.php?p=gruppi&amp;azione=nuovo">
        <i class="bi bi-plus-lg"></i> Censisci il primo gruppo
      </a>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th scope="col">Sigla</th>
            <th scope="col">Nome</th>
            <th scope="col">Sede</th>
            <th scope="col">Fondazione</th>
            <th scope="col">Affiliazioni</th>
            <th scope="col">Stato</th>
            <th scope="col" class="text-end">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($elenco as $gruppo): ?>
            <tr>
              <td class="catageo-valore fw-semibold"><?= Testo::esc((string) $gruppo['sigla']) ?></td>
              <td>
                <?= Testo::esc((string) $gruppo['nome']) ?>
                <?php if ((string) $gruppo['email'] !== '' || (string) $gruppo['sitoWeb'] !== ''): ?>
                  <div class="small text-body-secondary">
                    <?= Testo::esc((string) $gruppo['email']) ?>
                    <?php if ((string) $gruppo['sitoWeb'] !== ''): ?>
                      <a href="<?= Testo::esc((string) $gruppo['sitoWeb']) ?>" rel="noopener noreferrer" target="_blank">sito</a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?= Testo::esc((string) $gruppo['sedeComune']) ?>
                <?php if ((string) $gruppo['sedeProvincia'] !== ''): ?>
                  <span class="text-body-secondary">(<?= Testo::esc((string) $gruppo['sedeProvincia']) ?>)</span>
                <?php endif; ?>
              </td>
              <td class="catageo-valore"><?= Testo::esc((string) $gruppo['annoFondazione'] !== '' ? (string) $gruppo['annoFondazione'] : '—') ?></td>
              <td class="small"><?= Testo::esc(implode(', ', $gruppo['affiliazioni'])) ?></td>
              <td>
                <?php if ($gruppo['attivo']): ?>
                  <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                <?php else: ?>
                  <span class="text-body-secondary" title="disattivato"><i class="bi bi-slash-circle"></i></span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary"
                   href="index.php?p=gruppi&amp;azione=modifica&amp;id=<?= urlencode((string) $gruppo['id']) ?>">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" action="index.php?p=gruppi" class="d-inline">
                  <?= Auth::campoToken() ?>
                  <input type="hidden" name="operazione" value="elimina">
                  <input type="hidden" name="id" value="<?= Testo::esc((string) $gruppo['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"
                          data-catageo-conferma="Eliminare il gruppo &quot;<?= Testo::esc((string) $gruppo['sigla']) ?>&quot;? Se e referenziato da qualche parte l'operazione verra rifiutata: in quel caso disattivalo.">
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
