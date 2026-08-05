<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/view/layout.php
 *  Descrizione ..: Struttura comune delle pagine: intestazione HTML, navbar,
 *                  messaggi, contenuto e footer. Riceve dalle pagine le
 *                  variabili $titolo e $contenuto.
 *  Versione .....: 0.6.3
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.6.3  2026-08-05  D.Candela  Fondo di navbar e piede dalla tavolozza:
 *                                bg-body-tertiary sparirebbe sul bianco.
 *  0.6.0  2026-08-05  D.Candela  CSS e JS specifici della pagina, cosi le
 *                                librerie cartografiche si caricano solo
 *                                dove servono.
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 *
 * @var string $titolo     titolo della pagina
 * @var string $contenuto  HTML gia prodotto dalla pagina
 * @var string $paginaAttiva  identificativo per evidenziare la voce di menu
 * @var string[] $cssPagina  fogli di stile aggiuntivi richiesti dalla pagina
 * @var string[] $jsPagina   script aggiuntivi richiesti dalla pagina
 */

// Seconda barriera contro l'accesso diretto via HTTP: questo file ha senso
// solo se incluso da index.php, che definisce CATAGEO_ROOT. La guardia vale
// anche sui server dove il file .htaccess non viene letto.
defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

$nomeCatasto = Config::caricata() ? Config::testo('catasto.nome', 'CATAGEO') : 'CATAGEO';
$titolo      = $titolo ?? $nomeCatasto;
$contenuto   = $contenuto ?? '';
$paginaAttiva = $paginaAttiva ?? '';
$utente      = Auth::utente();
$tema        = Config::caricata() ? Config::testo('sistema.tema', 'auto') : 'auto';

// Risorse aggiuntive dichiarate dalla pagina. Le librerie pesanti (Leaflet,
// proj4) si caricano solo dove servono: la pagina di accesso non deve scaricare
// mezzo megabyte di cartografia.
$cssPagina = $cssPagina ?? [];
$jsPagina  = $jsPagina ?? [];

/** Voci di menu: etichetta, pagina, icona, permesso richiesto. */
$voci = [
    ['Mappa',        'mappa',       'bi-map',            'consulta'],
    ['Ipogei',       'ipogei',      'bi-safe',           'consulta'],
    ['Ricerca',      'ricerca',     'bi-search',         'ricerca'],
    ['Esplorazioni', 'esplorazioni','bi-journal-text',   'consulta'],
    ['Anagrafiche',  'anagrafiche', 'bi-people',         'anagrafiche'],
    ['Cataloghi',    'cataloghi',   'bi-collection',     'gestisci_cataloghi'],
    ['Strumenti',    'strumenti',   'bi-tools',          'strumenti'],
];
?>
<!doctype html>
<html lang="it" data-bs-theme="<?= Testo::esc($tema === 'auto' ? 'light' : $tema) ?>" data-catageo-tema="<?= Testo::esc($tema) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= Testo::esc($titolo) ?> · <?= Testo::esc($nomeCatasto) ?></title>
<link rel="stylesheet" href="assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/vendor/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/catageo.css">
<?php foreach ($cssPagina as $foglio): ?>
<link rel="stylesheet" href="<?= Testo::esc($foglio) ?>">
<?php endforeach; ?>
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <i class="bi bi-geo-alt-fill text-primary" aria-hidden="true"></i>
      <span class="fw-semibold"><?= Testo::esc($nomeCatasto) ?></span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#navPrincipale" aria-controls="navPrincipale"
            aria-expanded="false" aria-label="Apri il menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navPrincipale">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php foreach ($voci as [$etichetta, $pagina, $icona, $permesso]): ?>
          <?php if (!Auth::puo($permesso)) { continue; } ?>
          <li class="nav-item">
            <a class="nav-link<?= $paginaAttiva === $pagina ? ' active fw-semibold' : '' ?>"
               href="index.php?p=<?= Testo::esc($pagina) ?>">
              <i class="bi <?= Testo::esc($icona) ?>" aria-hidden="true"></i>
              <?= Testo::esc($etichetta) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary" type="button"
                id="catageoTema" title="Alterna tema chiaro e scuro">
          <i class="bi bi-circle-half" aria-hidden="true"></i>
        </button>

        <?php if ($utente !== null): ?>
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle" aria-hidden="true"></i>
              <?= Testo::esc((string) ($utente['username'] ?? '')) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li class="dropdown-header">
                <?= Testo::esc((string) ($utente['nomeCompleto'] ?: $utente['username'])) ?><br>
                <span class="badge text-bg-secondary">
                  <?= Testo::esc(Utenti::ETICHETTE_LIVELLO[$utente['livello']] ?? (string) $utente['livello']) ?>
                </span>
              </li>
              <li><hr class="dropdown-divider"></li>
              <?php if (Auth::puo('gestisci_utenti')): ?>
                <li><a class="dropdown-item" href="index.php?p=utenti"><i class="bi bi-people-fill"></i> Gestione utenti</a></li>
              <?php endif; ?>
              <?php if (Auth::puo('strumenti')): ?>
                <li><a class="dropdown-item" href="index.php?p=diagnostica"><i class="bi bi-activity"></i> Diagnostica</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" href="index.php?p=esci"><i class="bi bi-box-arrow-right"></i> Esci</a></li>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<main class="container-fluid py-4 flex-grow-1">
  <?php foreach (Auth::messaggi() as $messaggio): ?>
    <?php
      $classe = match ($messaggio['tipo']) {
          'successo' => 'alert-success',
          'errore'   => 'alert-danger',
          'avviso'   => 'alert-warning',
          default    => 'alert-info',
      };
      $icona = match ($messaggio['tipo']) {
          'successo' => 'bi-check-circle-fill',
          'errore'   => 'bi-exclamation-octagon-fill',
          'avviso'   => 'bi-exclamation-triangle-fill',
          default    => 'bi-info-circle-fill',
      };
    ?>
    <div class="alert <?= $classe ?> alert-dismissible d-flex align-items-start gap-2" role="alert">
      <i class="bi <?= $icona ?> mt-1" aria-hidden="true"></i>
      <div><?= Testo::esc($messaggio['testo']) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
    </div>
  <?php endforeach; ?>

  <?= $contenuto ?>
</main>

<footer class="border-top py-3 mt-auto">
  <div class="container-fluid d-flex flex-wrap justify-content-between gap-2 small text-body-secondary">
    <span>
      <?= Testo::esc($nomeCatasto) ?>
      <?php if (Config::caricata() && Config::testo('catasto.ente') !== ''): ?>
        — <?= Testo::esc(Config::testo('catasto.ente')) ?>
      <?php endif; ?>
    </span>
    <span>
      CATAGEO <?= Testo::esc(CATAGEO_VERSIONE) ?> ·
      <a class="link-secondary" href="https://github.com/darcan99/catageo" rel="noopener">GPL-3.0</a> ·
      Dario Candela
    </span>
  </div>
</footer>

<script src="assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/catageo.js"></script>
<?php foreach ($jsPagina as $script): ?>
<script src="<?= Testo::esc($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
