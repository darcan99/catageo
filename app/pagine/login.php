<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/login.php
 *  Descrizione ..: Pagina di accesso. Gestisce il POST delle credenziali e,
 *                  ad accesso riuscito, rimanda alla pagina richiesta prima
 *                  dell'autenticazione.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

$errore   = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    // Il token protegge anche il login: senza di esso un sito terzo potrebbe
    // forzare l'accesso con credenziali note e usare la sessione risultante.
    if (!Auth::verificaToken(isset($_POST['_token']) ? (string) $_POST['_token'] : null)) {
        $errore = 'Sessione non valida o scaduta. Riprovare.';
    } else {
        $esito = Auth::login($username, $password);

        if ($esito['ok']) {
            // Destinazione memorizzata dal front controller, se presente.
            $destinazione = (string) ($_SESSION['catageo_destinazione'] ?? 'home');
            unset($_SESSION['catageo_destinazione']);

            Auth::messaggio('successo', 'Accesso effettuato. Benvenuto in ' . Config::testo('catasto.nome', 'CATAGEO') . '.');
            header('Location: index.php?p=' . urlencode($destinazione));
            exit;
        }

        $errore = $esito['messaggio'];
    }
}

$nomeCatasto = Config::testo('catasto.nome', 'CATAGEO');
$ente        = Config::testo('catasto.ente', '');
?>
<div class="catageo-accesso py-4">

  <div class="text-center mb-4">
    <i class="bi bi-geo-alt-fill text-primary" style="font-size:2.5rem" aria-hidden="true"></i>
    <h1 class="h4 mt-2 mb-1"><?= Testo::esc($nomeCatasto) ?></h1>
    <?php if ($ente !== ''): ?>
      <p class="text-body-secondary mb-0"><?= Testo::esc($ente) ?></p>
    <?php endif; ?>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">

      <?php if ($errore !== ''): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
          <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
          <div><?= Testo::esc($errore) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?p=login" class="needs-validation" novalidate autocomplete="on">
        <?= Auth::campoToken() ?>

        <div class="mb-3">
          <label for="username" class="form-label">Username</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
            <input type="text" class="form-control" id="username" name="username"
                   value="<?= Testo::esc($username) ?>"
                   required autofocus autocomplete="username"
                   maxlength="40" spellcheck="false">
          </div>
          <div class="invalid-feedback">Indicare lo username.</div>
        </div>

        <div class="mb-4">
          <label for="password" class="form-label">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key" aria-hidden="true"></i></span>
            <input type="password" class="form-control" id="password" name="password"
                   required autocomplete="current-password">
          </div>
          <div class="invalid-feedback">Indicare la password.</div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Accedi
        </button>
      </form>

    </div>
  </div>

  <p class="catageo-nota text-center mt-3 mb-0">
    L'accesso e riservato agli utenti censiti. Per una nuova utenza rivolgersi
    a un amministratore del catasto.
  </p>

</div>
