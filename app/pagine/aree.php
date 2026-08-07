<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/aree.php
 *  Descrizione ..: Gestione dell'anagrafica delle aree speleologiche (9.17.5).
 *
 *                  L'elenco mostra quanti ipogei sono assegnati a ciascuna
 *                  area, ed e la sola cosa che dice se un'area serve davvero:
 *                  un'anagrafica piena di voci vuote e un'anagrafica che
 *                  nessuno usa.
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
        'regione'     => (string) ($_POST['regione'] ?? ''),
        'provincia'   => (string) ($_POST['provincia'] ?? ''),
        'massiccio'   => (string) ($_POST['massiccio'] ?? ''),
        'litologia'   => (string) ($_POST['litologia'] ?? ''),
        'latitudine'  => (string) ($_POST['latitudine'] ?? ''),
        'longitudine' => (string) ($_POST['longitudine'] ?? ''),
        'descrizione' => (string) ($_POST['descrizione'] ?? ''),
        'note'        => (string) ($_POST['note'] ?? ''),
        'attivo'      => !empty($_POST['attivo']),
    ];

    try {
        switch ($operazione) {
            case 'crea':
                $id = Aree::crea($dati);
                Log::modifica('crea', '', '', 'aree', $id . ' ' . $dati['nome']);
                Auth::messaggio('successo', 'Area creata.');
                break;

            case 'aggiorna':
                $id = (string) ($_POST['id'] ?? '');
                Aree::aggiorna($id, $dati);
                Log::modifica('aggiorna', '', '', 'aree', $id . ' ' . $dati['nome']);
                Auth::messaggio('successo', 'Area aggiornata.');
                break;

            case 'elimina':
                $id = (string) ($_POST['id'] ?? '');
                Aree::elimina($id);
                Log::modifica('elimina', '', '', 'aree', $id);
                Auth::messaggio('successo', 'Area eliminata.');
                break;

            default:
                throw new AnagraficaEccezione('Operazione non riconosciuta.');
        }
        header('Location: index.php?p=aree');
        exit;

    } catch (AnagraficaEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
        $ritorno = $operazione === 'aggiorna'
            ? 'index.php?p=aree&azione=modifica&id=' . urlencode((string) ($_POST['id'] ?? ''))
            : ($operazione === 'crea' ? 'index.php?p=aree&azione=nuovo' : 'index.php?p=aree');
        header('Location: ' . $ritorno);
        exit;
    }
}

$elenco     = Aree::elenco();
$inModifica = null;
if ($azione === 'modifica' && $idRichiesto !== '') {
    $inModifica = Aree::trova($idRichiesto);
    if ($inModifica === null) {
        Auth::messaggio('errore', 'Area non trovata.');
        $azione = 'elenco';
    }
}
?>

<div class="catageo-intestazione">
  <div>
    <h1>Aree speleologiche</h1>
    <p class="text-body-secondary mb-0">
      <?= count($elenco) ?> area<?= count($elenco) === 1 ? '' : 'e' ?> ·
      <a class="link-secondary" href="index.php?p=anagrafiche">tutte le anagrafiche</a>
    </p>
  </div>
  <?php if ($azione === 'elenco'): ?>
    <a class="btn btn-primary" href="index.php?p=aree&amp;azione=nuovo">
      <i class="bi bi-plus-lg"></i> Nuova area
    </a>
  <?php else: ?>
    <a class="btn btn-outline-secondary" href="index.php?p=aree">
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
          <h2 class="h6 mb-0"><?= $m !== null ? 'Modifica area' : 'Nuova area' ?></h2>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?p=aree" class="needs-validation" novalidate>
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="<?= $m !== null ? 'aggiorna' : 'crea' ?>">
            <?php if ($m !== null): ?>
              <input type="hidden" name="id" value="<?= Testo::esc((string) $m['id']) ?>">
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-md-8">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome" required
                       maxlength="120" placeholder="Alto Chiascio" value="<?= $v('nome') ?>">
                <div class="invalid-feedback">Il nome e obbligatorio.</div>
                <div class="catageo-nota">
                  Il nome d'uso, quello con cui l'area si nomina fra speleologi.
                  Deve essere unico.
                </div>
              </div>
              <div class="col-md-2">
                <label for="provincia" class="form-label">Provincia</label>
                <input type="text" class="form-control catageo-valore" id="provincia" name="provincia"
                       maxlength="2" pattern="[A-Za-z]{0,2}" placeholder="PG" value="<?= $v('provincia') ?>">
                <div class="invalid-feedback">Due lettere.</div>
              </div>
              <div class="col-md-2">
                <label for="regione" class="form-label">Regione</label>
                <input type="text" class="form-control" id="regione" name="regione"
                       maxlength="60" value="<?= $v('regione') ?>">
              </div>

              <div class="col-md-6">
                <label for="massiccio" class="form-label">Massiccio</label>
                <input type="text" class="form-control" id="massiccio" name="massiccio"
                       maxlength="120" value="<?= $v('massiccio') ?>">
                <div class="catageo-nota">Campo libero: la nomenclatura dei massicci non e normalizzata.</div>
              </div>
              <div class="col-md-6">
                <label for="litologia" class="form-label">Litologia prevalente</label>
                <input type="text" class="form-control" id="litologia" name="litologia"
                       maxlength="120" value="<?= $v('litologia') ?>">
              </div>

              <?php
              /*
               * Un punto e non un perimetro. Disegnare i confini sembrerebbe
               * piu preciso e sarebbe una precisione finta: i confini di
               * un'area speleologica sono di uso e non di cartografia, e un
               * poligono sbagliato escluderebbe cavita che tutti considerano
               * dentro. Il punto serve solo a inquadrare la mappa.
               */
              ?>
              <div class="col-md-3">
                <label for="latitudine" class="form-label">Latitudine indicativa</label>
                <input type="text" class="form-control catageo-valore" id="latitudine" name="latitudine"
                       maxlength="20" placeholder="43.3" value="<?= $v('latitudine') ?>">
              </div>
              <div class="col-md-3">
                <label for="longitudine" class="form-label">Longitudine indicativa</label>
                <input type="text" class="form-control catageo-valore" id="longitudine" name="longitudine"
                       maxlength="20" placeholder="12.6" value="<?= $v('longitudine') ?>">
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <div class="catageo-nota">
                  Facoltative, e volutamente approssimative: servono a inquadrare
                  la mappa, non a delimitare l'area. L'appartenenza di una cavita
                  si dichiara sulla sua scheda, non qui.
                </div>
              </div>

              <div class="col-12">
                <label for="descrizione" class="form-label">Descrizione</label>
                <textarea class="form-control" id="descrizione" name="descrizione" rows="4"><?= Testo::esc($m !== null ? (string) $m['descrizione'] : (string) ($_POST['descrizione'] ?? '')) ?></textarea>
              </div>

              <div class="col-12">
                <label for="note" class="form-label">Note</label>
                <textarea class="form-control" id="note" name="note" rows="3"><?= Testo::esc($m !== null ? (string) $m['note'] : (string) ($_POST['note'] ?? '')) ?></textarea>
                <div class="catageo-nota">Nessun limite di lunghezza.</div>
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="attivo" name="attivo" value="1"
                         <?= ($m === null || $m['attivo']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="attivo">Area attiva</label>
                </div>
                <div class="catageo-nota">
                  Un'area disattivata non compare piu fra le scelte in scheda, ma
                  resta leggibile su quelle che la citano gia.
                </div>
              </div>
            </div>

            <hr class="my-4">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> <?= $m !== null ? 'Salva modifiche' : 'Crea area' ?>
              </button>
              <a class="btn btn-outline-secondary" href="index.php?p=aree">Annulla</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($elenco === []): ?>

  <div class="card">
    <div class="card-body text-center py-5">
      <i class="bi bi-bounding-box fs-1 text-body-tertiary" aria-hidden="true"></i>
      <p class="mt-3 mb-1 text-body-secondary">Nessuna area censita.</p>
      <p class="catageo-nota mb-3">
        Un'area raggruppa le cavita come le raggruppa chi le esplora, e non come
        le raggruppano i confini amministrativi.
      </p>
      <a class="btn btn-primary" href="index.php?p=aree&amp;azione=nuovo">
        <i class="bi bi-plus-lg"></i> Censisci la prima area
      </a>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th scope="col">Nome</th>
            <th scope="col">Regione</th>
            <th scope="col">Massiccio</th>
            <th scope="col">Litologia</th>
            <th scope="col">Ipogei</th>
            <th scope="col">Stato</th>
            <th scope="col" class="text-end">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($elenco as $area): ?>
            <?php $assegnati = (int) (Aree::usi((string) $area['id'])['ipogei assegnati'] ?? 0); ?>
            <tr>
              <td class="fw-semibold">
                <?= Testo::esc((string) $area['nome']) ?>
                <?php if ((string) $area['descrizione'] !== ''): ?>
                  <div class="small text-body-secondary">
                    <?= Testo::esc(Testo::estratto((string) $area['descrizione'], 100)) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?= Testo::esc((string) $area['regione']) ?>
                <?php if ((string) $area['provincia'] !== ''): ?>
                  <span class="text-body-secondary">(<?= Testo::esc((string) $area['provincia']) ?>)</span>
                <?php endif; ?>
              </td>
              <td><?= Testo::esc((string) $area['massiccio']) ?></td>
              <td class="small"><?= Testo::esc((string) $area['litologia']) ?></td>
              <td>
                <?php if ($assegnati > 0): ?>
                  <a href="index.php?p=ricerca&amp;area=<?= urlencode((string) $area['id']) ?>">
                    <?= $assegnati ?>
                  </a>
                <?php else: ?>
                  <span class="text-body-tertiary">0</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($area['attivo']): ?>
                  <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                <?php else: ?>
                  <span class="text-body-secondary" title="disattivata"><i class="bi bi-slash-circle"></i></span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary"
                   href="index.php?p=aree&amp;azione=modifica&amp;id=<?= urlencode((string) $area['id']) ?>">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" action="index.php?p=aree" class="d-inline">
                  <?= Auth::campoToken() ?>
                  <input type="hidden" name="operazione" value="elimina">
                  <input type="hidden" name="id" value="<?= Testo::esc((string) $area['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"
                          data-catageo-conferma="Eliminare l'area &quot;<?= Testo::esc((string) $area['nome']) ?>&quot;? Se e assegnata a qualche ipogeo l'operazione verra rifiutata: in quel caso disattivala.">
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
