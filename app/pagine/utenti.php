<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/utenti.php
 *  Descrizione ..: Gestione degli utenti: elenco, creazione, modifica,
 *                  attivazione, cambio password ed eliminazione.
 *                  Riservata al livello ADM.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

// Seconda barriera contro l'accesso diretto via HTTP: questo file ha senso
// solo se incluso da index.php, che definisce CATAGEO_ROOT. La guardia vale
// anche sui server dove il file .htaccess non viene letto.
defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('gestisci_utenti');

$azione     = isset($_GET['azione']) ? (string) $_GET['azione'] : 'elenco';
$idRichiesto = isset($_GET['id']) ? (string) $_GET['id'] : '';

// ------------------------------------------------------------------ operazioni

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();

    $operazione = isset($_POST['operazione']) ? (string) $_POST['operazione'] : '';

    try {
        switch ($operazione) {

            case 'crea':
                $id = Utenti::crea([
                    'username'      => (string) ($_POST['username'] ?? ''),
                    'nomeCompleto'  => (string) ($_POST['nomeCompleto'] ?? ''),
                    'email'         => (string) ($_POST['email'] ?? ''),
                    'password'      => (string) ($_POST['password'] ?? ''),
                    'livello'       => (string) ($_POST['livello'] ?? 'USR'),
                    'esploratoreId' => (string) ($_POST['esploratoreId'] ?? ''),
                    'attivo'        => !empty($_POST['attivo']),
                ]);
                Log::modifica('crea', '', '', 'utenti', 'utente ' . $id . ' (' . (string) ($_POST['username'] ?? '') . ')');
                Auth::messaggio('successo', 'Utente creato.');
                header('Location: index.php?p=utenti');
                exit;

            case 'aggiorna':
                $id = (string) ($_POST['id'] ?? '');
                Utenti::aggiorna($id, [
                    'nomeCompleto'  => (string) ($_POST['nomeCompleto'] ?? ''),
                    'email'         => (string) ($_POST['email'] ?? ''),
                    'password'      => (string) ($_POST['password'] ?? ''),
                    'livello'       => (string) ($_POST['livello'] ?? 'USR'),
                    'esploratoreId' => (string) ($_POST['esploratoreId'] ?? ''),
                    'attivo'        => !empty($_POST['attivo']),
                ]);
                Log::modifica('modifica', '', '', 'utenti', 'utente ' . $id);
                Auth::messaggio('successo', 'Utente aggiornato.');
                header('Location: index.php?p=utenti');
                exit;

            case 'elimina':
                $id = (string) ($_POST['id'] ?? '');

                // Un amministratore non deve poter eliminare la propria utenza
                // mentre la sta usando: resterebbe fuori a meta operazione.
                if ($id === (string) (Auth::utente()['id'] ?? '')) {
                    throw new UtenteEccezione('Non e possibile eliminare l\'utenza con cui si e effettuato l\'accesso.');
                }

                Utenti::elimina($id);
                Log::modifica('elimina', '', '', 'utenti', 'utente ' . $id);
                Auth::messaggio('successo', 'Utente eliminato.');
                header('Location: index.php?p=utenti');
                exit;

            default:
                throw new RuntimeException('Operazione non riconosciuta.');
        }
    } catch (UtenteEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
        header('Location: index.php?p=utenti' . ($operazione === 'aggiorna' ? '&azione=modifica&id=' . urlencode((string) ($_POST['id'] ?? '')) : '&azione=nuovo'));
        exit;
    }
}

// ---------------------------------------------------------------------- viste

$elenco = Utenti::elenco();

/** Utente in modifica, se richiesto. */
$inModifica = null;
if ($azione === 'modifica' && $idRichiesto !== '') {
    $inModifica = Utenti::trovaPerId($idRichiesto);
    if ($inModifica === null) {
        Auth::messaggio('errore', 'Utente non trovato.');
        $azione = 'elenco';
    }
}

$idCorrente = (string) (Auth::utente()['id'] ?? '');
?>

<div class="catageo-intestazione">
  <div>
    <h1>Gestione utenti</h1>
    <p class="text-body-secondary mb-0">
      <?= count($elenco) ?> utenz<?= count($elenco) === 1 ? 'a' : 'e' ?> ·
      <?= Utenti::contaAmministratoriAttivi() ?> amministrator<?= Utenti::contaAmministratoriAttivi() === 1 ? 'e' : 'i' ?> attiv<?= Utenti::contaAmministratoriAttivi() === 1 ? 'o' : 'i' ?>
    </p>
  </div>
  <?php if ($azione === 'elenco'): ?>
    <a class="btn btn-primary" href="index.php?p=utenti&amp;azione=nuovo">
      <i class="bi bi-person-plus"></i> Nuovo utente
    </a>
  <?php else: ?>
    <a class="btn btn-outline-secondary" href="index.php?p=utenti">
      <i class="bi bi-arrow-left"></i> Torna all'elenco
    </a>
  <?php endif; ?>
</div>

<?php if ($azione === 'nuovo' || $inModifica !== null): ?>

  <?php $modifica = $inModifica !== null; ?>
  <div class="row">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">
          <h2 class="h6 mb-0">
            <i class="bi <?= $modifica ? 'bi-pencil-square' : 'bi-person-plus' ?>"></i>
            <?= $modifica ? 'Modifica utente ' . Testo::esc((string) $inModifica['username']) : 'Nuovo utente' ?>
          </h2>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?p=utenti" class="needs-validation" novalidate>
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="<?= $modifica ? 'aggiorna' : 'crea' ?>">
            <?php if ($modifica): ?>
              <input type="hidden" name="id" value="<?= Testo::esc((string) $inModifica['id']) ?>">
            <?php endif; ?>

            <div class="row g-3">

              <div class="col-md-6">
                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                <?php if ($modifica): ?>
                  <input type="text" class="form-control" id="username"
                         value="<?= Testo::esc((string) $inModifica['username']) ?>" disabled>
                  <div class="catageo-nota">
                    Lo username non e modificabile: comparirebbe nei log storici
                    con due valori diversi per la stessa persona.
                  </div>
                <?php else: ?>
                  <input type="text" class="form-control" id="username" name="username" required
                         pattern="[A-Za-z0-9._\-]{3,40}" maxlength="40" spellcheck="false"
                         value="<?= Testo::esc((string) ($_POST['username'] ?? '')) ?>">
                  <div class="invalid-feedback">
                    Da 3 a 40 caratteri: lettere, cifre, punto, underscore, trattino.
                  </div>
                <?php endif; ?>
              </div>

              <div class="col-md-6">
                <label for="livello" class="form-label">Livello <span class="text-danger">*</span></label>
                <select class="form-select" id="livello" name="livello" required>
                  <?php foreach (Utenti::LIVELLI as $livello): ?>
                    <option value="<?= Testo::esc($livello) ?>"
                      <?= ($modifica && $inModifica['livello'] === $livello) || (!$modifica && $livello === 'USR') ? 'selected' : '' ?>>
                      <?= Testo::esc($livello) ?> — <?= Testo::esc(Utenti::ETICHETTE_LIVELLO[$livello]) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label for="nomeCompleto" class="form-label">Nome e cognome</label>
                <input type="text" class="form-control" id="nomeCompleto" name="nomeCompleto" maxlength="120"
                       value="<?= Testo::esc((string) ($modifica ? $inModifica['nomeCompleto'] : ($_POST['nomeCompleto'] ?? ''))) ?>">
              </div>

              <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="150"
                       value="<?= Testo::esc((string) ($modifica ? $inModifica['email'] : ($_POST['email'] ?? ''))) ?>">
                <div class="invalid-feedback">Indirizzo email non valido.</div>
              </div>

              <div class="col-md-6">
                <label for="password" class="form-label">
                  Password <?= $modifica ? '' : '<span class="text-danger">*</span>' ?>
                </label>
                <input type="password" class="form-control" id="password" name="password"
                       <?= $modifica ? '' : 'required' ?>
                       minlength="<?= Utenti::MIN_PASSWORD ?>" autocomplete="new-password">
                <div class="catageo-nota">
                  Almeno <?= Utenti::MIN_PASSWORD ?> caratteri.
                  <?= $modifica ? 'Lasciare vuoto per non cambiarla.' : '' ?>
                </div>
              </div>

              <div class="col-md-6">
                <label for="esploratoreId" class="form-label">Esploratore collegato</label>
                <input type="text" class="form-control catageo-valore" id="esploratoreId" name="esploratoreId"
                       maxlength="10" placeholder="es. E001"
                       value="<?= Testo::esc((string) ($modifica ? $inModifica['esploratoreId'] : ($_POST['esploratoreId'] ?? ''))) ?>">
                <div class="catageo-nota">
                  Identificativo in <span class="catageo-valore">esploratori.xml</span>.
                  L'anagrafica arriva in fase 2: per ora e un campo libero.
                </div>
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="attivo" name="attivo" value="1"
                         <?= (!$modifica || $inModifica['attivo']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="attivo">Utenza attiva</label>
                </div>
                <div class="catageo-nota">
                  Un'utenza disattivata non puo accedere ma resta nei log e nei
                  riferimenti storici: e preferibile alla cancellazione.
                </div>
              </div>

            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> <?= $modifica ? 'Salva modifiche' : 'Crea utente' ?>
              </button>
              <a class="btn btn-outline-secondary" href="index.php?p=utenti">Annulla</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th scope="col">Username</th>
            <th scope="col">Nome</th>
            <th scope="col">Livello</th>
            <th scope="col">Stato</th>
            <th scope="col">Ultimo accesso</th>
            <th scope="col" class="text-end">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($elenco as $utente): ?>
            <tr>
              <td>
                <span class="catageo-valore fw-semibold"><?= Testo::esc((string) $utente['username']) ?></span>
                <?php if ((string) $utente['id'] === $idCorrente): ?>
                  <span class="badge text-bg-info ms-1">tu</span>
                <?php endif; ?>
                <?php if (Utenti::eBloccato($utente)): ?>
                  <span class="badge text-bg-danger ms-1" title="Bloccato fino a <?= Testo::esc((string) $utente['bloccatoFino']) ?>">
                    bloccato
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?= Testo::esc((string) $utente['nomeCompleto']) ?>
                <?php if ((string) $utente['email'] !== ''): ?>
                  <div class="small text-body-secondary"><?= Testo::esc((string) $utente['email']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $utente['livello'] === 'ADM' ? 'text-bg-primary' : ($utente['livello'] === 'OPE' ? 'text-bg-secondary' : 'text-bg-light border') ?>">
                  <?= Testo::esc((string) $utente['livello']) ?>
                </span>
              </td>
              <td>
                <?php if ($utente['attivo']): ?>
                  <span class="text-success"><i class="bi bi-check-circle-fill"></i> attiva</span>
                <?php else: ?>
                  <span class="text-body-secondary"><i class="bi bi-slash-circle"></i> disattivata</span>
                <?php endif; ?>
              </td>
              <td class="catageo-valore small">
                <?= Testo::esc((string) $utente['ultimoAccesso'] !== '' ? (string) $utente['ultimoAccesso'] : '—') ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary"
                   href="index.php?p=utenti&amp;azione=modifica&amp;id=<?= urlencode((string) $utente['id']) ?>">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if ((string) $utente['id'] !== $idCorrente): ?>
                  <form method="post" action="index.php?p=utenti" class="d-inline">
                    <?= Auth::campoToken() ?>
                    <input type="hidden" name="operazione" value="elimina">
                    <input type="hidden" name="id" value="<?= Testo::esc((string) $utente['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            data-catageo-conferma="Eliminare definitivamente l'utenza &quot;<?= Testo::esc((string) $utente['username']) ?>&quot;? Valutare la disattivazione, che conserva i riferimenti storici.">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>
