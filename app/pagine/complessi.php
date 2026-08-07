<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/complessi.php
 *  Descrizione ..: Gestione dell'anagrafica dei complessi (9.17.4).
 *
 *                  L'elenco mostra i totali sommati dalle schede — quante
 *                  cavita, quanto sviluppo, quanto dislivello — e non campi
 *                  digitati: un totale scritto a mano diverge dalla somma al
 *                  primo aggiornamento di una delle cavita, e da quel momento
 *                  nessuno sa piu a quale dei due numeri credere.
 *  Versione .....: 1.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.1.0  2026-08-07  D.Candela  Prima stesura (fase 12).
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
        'nome'        => (string) ($_POST['nome'] ?? ''),
        'codice'      => (string) ($_POST['codice'] ?? ''),
        'natura'      => (string) ($_POST['natura'] ?? ''),
        'regione'     => (string) ($_POST['regione'] ?? ''),
        'descrizione' => (string) ($_POST['descrizione'] ?? ''),
        'note'        => (string) ($_POST['note'] ?? ''),
        'attivo'      => !empty($_POST['attivo']),
    ];

    try {
        switch ($operazione) {
            case 'crea':
                $id = Complessi::crea($dati);
                Log::modifica('crea', '', '', 'complessi', $id . ' ' . $dati['nome']);
                Auth::messaggio('successo', 'Complesso creato.');
                break;

            case 'aggiorna':
                $id = (string) ($_POST['id'] ?? '');
                Complessi::aggiorna($id, $dati);
                Log::modifica('aggiorna', '', '', 'complessi', $id . ' ' . $dati['nome']);
                Auth::messaggio('successo', 'Complesso aggiornato.');
                break;

            case 'elimina':
                $id = (string) ($_POST['id'] ?? '');
                Complessi::elimina($id);
                Log::modifica('elimina', '', '', 'complessi', $id);
                Auth::messaggio('successo', 'Complesso eliminato.');
                break;

            default:
                throw new AnagraficaEccezione('Operazione non riconosciuta.');
        }
        header('Location: index.php?p=complessi');
        exit;

    } catch (AnagraficaEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
        $ritorno = $operazione === 'aggiorna'
            ? 'index.php?p=complessi&azione=modifica&id=' . urlencode((string) ($_POST['id'] ?? ''))
            : ($operazione === 'crea' ? 'index.php?p=complessi&azione=nuovo' : 'index.php?p=complessi');
        header('Location: ' . $ritorno);
        exit;
    }
}

$elenco     = Complessi::elenco();
$inModifica = null;
if ($azione === 'modifica' && $idRichiesto !== '') {
    $inModifica = Complessi::trova($idRichiesto);
    if ($inModifica === null) {
        Auth::messaggio('errore', 'Complesso non trovato.');
        $azione = 'elenco';
    }
}

/** Numero con il separatore italiano, o il trattino se non c'e nulla. */
$misura = static function (float $valore): string {
    return $valore > 0 ? number_format($valore, 0, ',', '.') . ' m' : '—';
};
?>

<div class="catageo-intestazione">
  <div>
    <h1>Complessi</h1>
    <p class="text-body-secondary mb-0">
      <?= count($elenco) ?> compless<?= count($elenco) === 1 ? 'o' : 'i' ?> ·
      <a class="link-secondary" href="index.php?p=anagrafiche">tutte le anagrafiche</a>
    </p>
  </div>
  <?php if ($azione === 'elenco'): ?>
    <a class="btn btn-primary" href="index.php?p=complessi&amp;azione=nuovo">
      <i class="bi bi-plus-lg"></i> Nuovo complesso
    </a>
  <?php else: ?>
    <a class="btn btn-outline-secondary" href="index.php?p=complessi">
      <i class="bi bi-arrow-left"></i> Torna all'elenco
    </a>
  <?php endif; ?>
</div>

<?php if ($azione === 'nuovo' || $inModifica !== null): ?>
  <?php $m = $inModifica; $v = static fn (string $c): string => Testo::esc((string) ($m[$c] ?? ($_POST[$c] ?? ''))); ?>

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h2 class="h6 mb-0"><?= $m !== null ? 'Modifica complesso' : 'Nuovo complesso' ?></h2>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?p=complessi" class="needs-validation" novalidate>
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="<?= $m !== null ? 'aggiorna' : 'crea' ?>">
            <?php if ($m !== null): ?>
              <input type="hidden" name="id" value="<?= Testo::esc((string) $m['id']) ?>">
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-md-7">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome" required
                       maxlength="150" placeholder="Complesso del Monte Cucco" value="<?= $v('nome') ?>">
                <div class="invalid-feedback">Il nome e obbligatorio.</div>
                <div class="catageo-nota">
                  Il nome con cui il complesso e chiamato in letteratura. Deve essere unico.
                </div>
              </div>
              <div class="col-md-5">
                <label for="codice" class="form-label">Codice proprio</label>
                <input type="text" class="form-control catageo-valore" id="codice" name="codice"
                       maxlength="40" placeholder="ACQ/07" value="<?= $v('codice') ?>">
                <?php
                /*
                 * Non e un codice catastale e non consuma progressivi: e una
                 * convenzione di chi cataloga. Utile soprattutto sulle cavita
                 * artificiali, dove spesso una numerazione dei complessi esiste
                 * gia — per gli acquedotti, per i sistemi di cave — e non
                 * poterla scrivere costringerebbe a tenerla altrove.
                 */
                ?>
                <div class="catageo-nota">
                  Facoltativo e libero: <strong>non</strong> e un codice catastale e non
                  consuma progressivi del catalogo. Serve a chi ha gia una propria
                  numerazione dei complessi.
                </div>
              </div>

              <div class="col-md-4">
                <label for="natura" class="form-label">Natura</label>
                <select class="form-select" id="natura" name="natura">
                  <?php $naturaCorrente = (string) ($m['natura'] ?? ($_POST['natura'] ?? '')); ?>
                  <?php foreach (Complessi::NATURE as $valore => $etichetta): ?>
                    <option value="<?= Testo::esc((string) $valore) ?>"
                            <?= $naturaCorrente === (string) $valore ? 'selected' : '' ?>>
                      <?= Testo::esc($etichetta) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="catageo-nota">
                  «Misto» non e un ripiego: una cava che intercetta un sistema
                  carsico e il caso in cui le due nature coesistono davvero.
                </div>
              </div>
              <div class="col-md-4">
                <label for="regione" class="form-label">Regione</label>
                <input type="text" class="form-control" id="regione" name="regione"
                       maxlength="60" value="<?= $v('regione') ?>">
              </div>

              <div class="col-12">
                <label for="descrizione" class="form-label">Descrizione</label>
                <textarea class="form-control" id="descrizione" name="descrizione" rows="4"><?= Testo::esc($m !== null ? (string) $m['descrizione'] : (string) ($_POST['descrizione'] ?? '')) ?></textarea>
              </div>

              <div class="col-12">
                <label for="note" class="form-label">Note</label>
                <textarea class="form-control" id="note" name="note" rows="3"><?= Testo::esc($m !== null ? (string) $m['note'] : (string) ($_POST['note'] ?? '')) ?></textarea>
              </div>

              <div class="col-12">
                <div class="alert alert-light border mb-0 catageo-nota">
                  <strong>Sviluppo e dislivello non si scrivono qui</strong>: si sommano
                  dalle cavita che fanno parte del complesso. Un totale digitato a mano
                  divergerebbe dalla somma al primo aggiornamento di una scheda, e da
                  quel momento non si saprebbe piu a quale numero credere.
                  L'appartenenza si dichiara sulla scheda della cavita.
                </div>
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="attivo" name="attivo" value="1"
                         <?= ($m === null || $m['attivo']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="attivo">Complesso attivo</label>
                </div>
              </div>
            </div>

            <hr class="my-4">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> <?= $m !== null ? 'Salva modifiche' : 'Crea complesso' ?>
              </button>
              <a class="btn btn-outline-secondary" href="index.php?p=complessi">Annulla</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($elenco === []): ?>

  <div class="card">
    <div class="card-body text-center py-5">
      <i class="bi bi-diagram-2 fs-1 text-body-tertiary" aria-hidden="true"></i>
      <p class="mt-3 mb-1 text-body-secondary">Nessun complesso censito.</p>
      <p class="catageo-nota mb-3">
        Un complesso raggruppa le cavita che formano un sistema unico e ha un nome
        proprio: e la cosa di cui si parla in letteratura, mentre le schede sono
        il modo in cui il catasto la registra.
      </p>
      <a class="btn btn-primary" href="index.php?p=complessi&amp;azione=nuovo">
        <i class="bi bi-plus-lg"></i> Censisci il primo complesso
      </a>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th scope="col">Codice</th>
            <th scope="col">Nome</th>
            <th scope="col">Natura</th>
            <th scope="col">Cavita</th>
            <th scope="col">Sviluppo</th>
            <th scope="col">Dislivello</th>
            <th scope="col">Stato</th>
            <th scope="col" class="text-end">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($elenco as $complesso): ?>
            <?php $totali = Complessi::totali((string) $complesso['id']); ?>
            <tr>
              <td class="catageo-valore"><?= Testo::esc((string) $complesso['codice']) ?></td>
              <td class="fw-semibold">
                <?= Testo::esc((string) $complesso['nome']) ?>
                <?php if ((string) $complesso['descrizione'] !== ''): ?>
                  <div class="small text-body-secondary">
                    <?= Testo::esc(Testo::estratto((string) $complesso['descrizione'], 100)) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?= Testo::esc(Complessi::NATURE[(string) $complesso['natura']] ?? '') ?></td>
              <td>
                <?php if ($totali['ipogei'] > 0): ?>
                  <a href="index.php?p=ricerca&amp;complesso=<?= urlencode((string) $complesso['id']) ?>">
                    <?= (int) $totali['ipogei'] ?>
                  </a>
                <?php else: ?>
                  <span class="text-body-tertiary">0</span>
                <?php endif; ?>
              </td>
              <td class="catageo-valore"><?= $misura($totali['sviluppo']) ?></td>
              <td class="catageo-valore"><?= $misura($totali['dislivello']) ?></td>
              <td>
                <?php if ($complesso['attivo']): ?>
                  <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                <?php else: ?>
                  <span class="text-body-secondary" title="disattivato"><i class="bi bi-slash-circle"></i></span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary"
                   href="index.php?p=complessi&amp;azione=modifica&amp;id=<?= urlencode((string) $complesso['id']) ?>">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" action="index.php?p=complessi" class="d-inline">
                  <?= Auth::campoToken() ?>
                  <input type="hidden" name="operazione" value="elimina">
                  <input type="hidden" name="id" value="<?= Testo::esc((string) $complesso['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"
                          data-catageo-conferma="Eliminare il complesso &quot;<?= Testo::esc((string) $complesso['nome']) ?>&quot;? Se qualche ipogeo ne fa parte l'operazione verra rifiutata: in quel caso disattivalo.">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer catageo-nota">
      Sviluppo e dislivello sono <strong>sommati dalle schede visibili</strong>, non
      digitati. Un utente che non vede le cavita riservate vede totali piu bassi:
      e voluto, perche la differenza direbbe quante ne esistono che non puo vedere.
    </div>
  </div>

<?php endif; ?>
