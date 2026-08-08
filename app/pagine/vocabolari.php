<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/vocabolari.php
 *  Descrizione ..: Gestione dei tre vocabolari controllati: tipologie di
 *                  ipogeo, grandezze misurabili, periodi storici.
 *
 *                  Stanno in un'unica pagina perche condividono la stessa
 *                  forma d'uso: si consultano spesso, si modificano raramente,
 *                  e l'interfaccia utile e la stessa (elenco piu form di
 *                  inserimento in linea). Tre pagine separate avrebbero
 *                  triplicato lo stesso codice.
 *
 *                  In tutti e tre i casi il codice di una voce non e
 *                  modificabile: e il riferimento memorizzato nelle schede.
 *  Versione .....: 1.6.1
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.6.1  2026-08-08  D.Candela  Una colonna «Mappa» vuota su tutte le
 *                                righe adesso dice perche, e offre il
 *                                comando che la riempie.
 *  1.5.0  2026-08-08  D.Candela  Colonna del simbolo risolto e tavolozza
 *                                dei glifi propri.
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('anagrafiche');

/** Vocabolari gestiti da questa pagina. */
$vocabolari = [
    'tipologie'  => ['titolo' => 'Tipologie di ipogeo', 'icona' => 'bi-diagram-3'],
    'grandezze'  => ['titolo' => 'Grandezze misurabili', 'icona' => 'bi-thermometer-half'],
    'periodi'    => ['titolo' => 'Periodi storici', 'icona' => 'bi-hourglass-split'],
];

$voc = isset($_GET['voc']) ? (string) $_GET['voc'] : 'tipologie';
if (!isset($vocabolari[$voc])) {
    $voc = 'tipologie';
}

// ------------------------------------------------------------------ operazioni

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();

    $operazione = (string) ($_POST['operazione'] ?? '');
    $vocPost    = isset($_POST['voc']) && isset($vocabolari[(string) $_POST['voc']]) ? (string) $_POST['voc'] : $voc;

    try {
        switch ($operazione) {

            // ---------------------------------------------------- tipologie
            case 'tipologia-crea':
                $codice = Tipologie::crea(
                    (string) ($_POST['livello'] ?? ''),
                    (string) ($_POST['padre'] ?? ''),
                    (string) ($_POST['codice'] ?? ''),
                    (string) ($_POST['nome'] ?? ''),
                    (string) ($_POST['note'] ?? ''),
                    (string) ($_POST['icona'] ?? '')
                );
                Log::modifica('crea', '', '', 'tipologie', $codice);
                Auth::messaggio('successo', 'Voce aggiunta alla tassonomia.');
                break;

            case 'tipologia-aggiorna':
                $codice = (string) ($_POST['codice'] ?? '');
                Tipologie::aggiorna(
                    $codice,
                    (string) ($_POST['nome'] ?? ''),
                    (string) ($_POST['note'] ?? ''),
                    !empty($_POST['attivo']),
                    (string) ($_POST['icona'] ?? '')
                );
                Log::modifica('modifica', '', '', 'tipologie', $codice);
                Auth::messaggio('successo', 'Voce aggiornata.');
                break;

            case 'tipologia-elimina':
                $codice = (string) ($_POST['codice'] ?? '');
                Tipologie::elimina($codice);
                Log::modifica('elimina', '', '', 'tipologie', $codice);
                Auth::messaggio('successo', 'Voce eliminata.');
                break;

            // ---------------------------------------------------- grandezze
            case 'categoria-crea':
                $codice = Grandezze::creaCategoria((string) ($_POST['codice'] ?? ''), (string) ($_POST['nome'] ?? ''));
                Log::modifica('crea', '', '', 'grandezze', 'categoria ' . $codice);
                Auth::messaggio('successo', 'Categoria creata.');
                break;

            case 'grandezza-crea':
                $codice = Grandezze::creaGrandezza((string) ($_POST['categoria'] ?? ''), [
                    'codice'   => (string) ($_POST['codice'] ?? ''),
                    'nome'     => (string) ($_POST['nome'] ?? ''),
                    'unita'    => (string) ($_POST['unita'] ?? ''),
                    'min'      => (string) ($_POST['min'] ?? ''),
                    'max'      => (string) ($_POST['max'] ?? ''),
                    'decimali' => (string) ($_POST['decimali'] ?? '2'),
                ]);
                Log::modifica('crea', '', '', 'grandezze', $codice);
                Auth::messaggio('successo', 'Grandezza creata.');
                break;

            case 'grandezza-aggiorna':
                $codice = (string) ($_POST['codice'] ?? '');
                Grandezze::aggiornaGrandezza($codice, [
                    'nome'     => (string) ($_POST['nome'] ?? ''),
                    'unita'    => (string) ($_POST['unita'] ?? ''),
                    'min'      => (string) ($_POST['min'] ?? ''),
                    'max'      => (string) ($_POST['max'] ?? ''),
                    'decimali' => (string) ($_POST['decimali'] ?? '2'),
                    'attivo'   => !empty($_POST['attivo']),
                ]);
                Log::modifica('modifica', '', '', 'grandezze', $codice);
                Auth::messaggio('successo', 'Grandezza aggiornata.');
                break;

            case 'grandezza-elimina':
                $codice = (string) ($_POST['codice'] ?? '');
                Grandezze::eliminaGrandezza($codice);
                Log::modifica('elimina', '', '', 'grandezze', $codice);
                Auth::messaggio('successo', 'Grandezza eliminata.');
                break;

            // ------------------------------------------------------ periodi
            case 'periodo-crea':
                $codice = Periodi::crea([
                    'codice' => (string) ($_POST['codice'] ?? ''),
                    'nome'   => (string) ($_POST['nome'] ?? ''),
                    'da'     => (string) ($_POST['da'] ?? ''),
                    'a'      => (string) ($_POST['a'] ?? ''),
                    'note'   => (string) ($_POST['note'] ?? ''),
                    'attivo' => true,
                ]);
                Log::modifica('crea', '', '', 'periodi', $codice);
                Auth::messaggio('successo', 'Periodo creato.');
                break;

            case 'periodo-aggiorna':
                $codice = (string) ($_POST['codice'] ?? '');
                Periodi::aggiorna($codice, [
                    'codice' => $codice,
                    'nome'   => (string) ($_POST['nome'] ?? ''),
                    'da'     => (string) ($_POST['da'] ?? ''),
                    'a'      => (string) ($_POST['a'] ?? ''),
                    'note'   => (string) ($_POST['note'] ?? ''),
                    'attivo' => !empty($_POST['attivo']),
                ]);
                Log::modifica('modifica', '', '', 'periodi', $codice);
                Auth::messaggio('successo', 'Periodo aggiornato.');
                break;

            case 'periodo-elimina':
                $codice = (string) ($_POST['codice'] ?? '');
                Periodi::elimina($codice);
                Log::modifica('elimina', '', '', 'periodi', $codice);
                Auth::messaggio('successo', 'Periodo eliminato.');
                break;

            default:
                throw new AnagraficaEccezione('Operazione non riconosciuta.');
        }
    } catch (AnagraficaEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: index.php?p=vocabolari&voc=' . urlencode($vocPost));
    exit;
}

/** Voce in modifica, se richiesta. */
$modifica = isset($_GET['modifica']) ? (string) $_GET['modifica'] : '';
?>

<?php
/*
 * Lo sprite dei glifi propri: qui serve alle anteprime dell'elenco e alla
 * scelta nel modulo. Si emette solo sulla tassonomia, che e l'unico vocabolario
 * che ha icone: sulle grandezze e sui periodi sarebbero due kilobyte di niente.
 */
if ($voc === 'tipologie') {
    echo Icone::sprite();
}
?>

<div class="catageo-intestazione">
  <div>
    <h1>Vocabolari</h1>
    <p class="text-body-secondary mb-0">
      <a class="link-secondary" href="index.php?p=anagrafiche">tutte le anagrafiche</a>
    </p>
  </div>
</div>

<ul class="nav nav-tabs mb-4">
  <?php foreach ($vocabolari as $chiave => $meta): ?>
    <li class="nav-item">
      <a class="nav-link<?= $voc === $chiave ? ' active' : '' ?>"
         href="index.php?p=vocabolari&amp;voc=<?= Testo::esc($chiave) ?>">
        <i class="bi <?= Testo::esc($meta['icona']) ?>"></i> <?= Testo::esc($meta['titolo']) ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if ($voc === 'tipologie'): ?>

  <?php
  $voci    = Tipologie::elenco();
  $nature  = Tipologie::perLivello('natura', '', false);
  $tipol   = Tipologie::perLivello('tipologia', '', false);
  $inMod   = $modifica !== '' ? Tipologie::trova($modifica) : null;

  /*
   * Un archivio nato prima dei glifi non ha nemmeno un'icona, e la colonna
   * «Mappa» resta vuota su tutte le righe. Una colonna vuota senza spiegazione
   * e un vicolo cieco: chi la guarda non sa se ha sbagliato lui, se il
   * simbolo non si vede o se non c'e. Lo si dice, e si dice cosa fare.
   */
  $conIcona = 0;
  foreach ($voci as $v) {
      if (($v['icona'] ?? '') !== '') {
          $conIcona++;
      }
  }
  ?>

  <?php if ($conIcona === 0 && $voci !== []): ?>
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-3">
      <div class="flex-grow-1">
        <strong>Nessuna voce ha ancora un simbolo per la mappa.</strong>
        Questo archivio e nato prima dei glifi, e il vocabolario non viene
        toccato dagli aggiornamenti: le tue voci e le tue scelte restano dove
        sono. I simboli predefiniti si portano dentro con un comando, che
        completa solo le voci che ne sono prive.
      </div>
      <?php if (Auth::puo('strumenti')): ?>
        <form method="post" action="index.php?p=strumenti" class="m-0">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="iconePredefinite">
          <input type="hidden" name="ritorno" value="vocabolari">
          <button type="submit" class="btn btn-primary text-nowrap">
            <i class="bi bi-palette"></i> Completa i simboli mancanti
          </button>
        </form>
      <?php else: ?>
        <span class="text-nowrap small">Lo puo fare un amministratore da Strumenti.</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">Tassonomia — <?= count($voci) ?> voci</h2>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover catageo-tabella mb-0 align-middle">
            <thead>
              <tr>
                <th scope="col" class="text-center" style="width:3rem">Mappa</th>
                <th scope="col">Codice</th>
                <th scope="col">Voce</th>
                <th scope="col">Livello</th>
                <th scope="col" class="text-end">Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($voci as $v): ?>
                <?php $rientro = $v['livello'] === 'natura' ? 0 : ($v['livello'] === 'tipologia' ? 1 : 2); ?>
                <tr<?= $v['attivo'] ? '' : ' class="text-body-secondary"' ?>>
                  <?php
                  /*
                   * Il simbolo che la voce usa DAVVERO, cioe quello risolto con
                   * l'ereditarieta: mostrare solo l'attributo proprio lascerebbe
                   * vuote quasi tutte le righe e non direbbe cosa si vede in
                   * mappa, che e l'unica domanda per cui si guarda questa
                   * colonna. Le voci che lo ereditano si segnano in grigio.
                   */
                  $iconaVoce   = Tipologie::icona($v['codice']);
                  $iconaPropria = ($v['icona'] ?? '') !== '';
                  ?>
                  <td class="text-center">
                    <?php if ($iconaVoce !== ''): ?>
                      <span class="catageo-anteprima-icona<?= $iconaPropria ? '' : ' text-body-tertiary' ?>"
                            title="<?= Testo::esc($iconaPropria ? $iconaVoce : $iconaVoce . ' (ereditata)') ?>">
                        <?= Icone::html($iconaVoce) ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="catageo-valore"><?= Testo::esc($v['codice']) ?></td>
                  <td style="padding-left:<?= 0.75 + $rientro * 1.5 ?>rem">
                    <?php if ($rientro > 0): ?><i class="bi bi-arrow-return-right text-body-tertiary"></i> <?php endif; ?>
                    <span class="<?= $v['livello'] === 'natura' ? 'fw-semibold' : '' ?>"><?= Testo::esc($v['nome']) ?></span>
                    <?php if (!$v['attivo']): ?>
                      <span class="badge text-bg-secondary ms-1">disattivata</span>
                    <?php endif; ?>
                  </td>
                  <td class="small"><?= Testo::esc(Tipologie::ETICHETTE_LIVELLO[$v['livello']]) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="index.php?p=vocabolari&amp;voc=tipologie&amp;modifica=<?= urlencode($v['codice']) ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?p=vocabolari" class="d-inline">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="voc" value="tipologie">
                      <input type="hidden" name="operazione" value="tipologia-elimina">
                      <input type="hidden" name="codice" value="<?= Testo::esc($v['codice']) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger"
                              data-catageo-conferma="Eliminare &quot;<?= Testo::esc($v['nome']) ?>&quot;? Verra rifiutato se ha voci subordinate o se e usata da qualche scheda.">
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
    </div>

    <div class="col-lg-5">
      <?php if ($inMod !== null): ?>
        <div class="card">
          <div class="card-header">
            <h2 class="h6 mb-0">Modifica <span class="catageo-valore"><?= Testo::esc($inMod['codice']) ?></span></h2>
          </div>
          <div class="card-body">
            <form method="post" action="index.php?p=vocabolari" class="needs-validation" novalidate>
              <?= Auth::campoToken() ?>
              <input type="hidden" name="voc" value="tipologie">
              <input type="hidden" name="operazione" value="tipologia-aggiorna">
              <input type="hidden" name="codice" value="<?= Testo::esc($inMod['codice']) ?>">

              <div class="mb-3">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome" required
                       maxlength="120" value="<?= Testo::esc($inMod['nome']) ?>">
              </div>
              <div class="mb-3">
                <label for="icona" class="form-label">Icona in mappa</label>
                <div class="input-group">
                  <span class="input-group-text catageo-anteprima-icona">
                    <?= Icone::html($inMod['icona'] !== '' ? $inMod['icona'] : Tipologie::icona($inMod['codice'])) ?>
                  </span>
                  <?php
                  /*
                   * Il segnaposto diceva «droplet-fill», che e un nome di icona
                   * valido: su un campo vuoto si legge come un valore gia
                   * impostato, e chi guarda crede di avere un simbolo che non
                   * ha. Un segnaposto deve dire cosa succede se non si scrive
                   * niente, non fingere un contenuto.
                   */
                  $padreMod = (string) ($inMod['padre'] ?? '');
                  $segnaposto = $padreMod !== ''
                      ? 'vuoto: eredita da ' . $padreMod
                      : 'vuoto: nessun simbolo';
                  ?>
                  <input type="text" class="form-control catageo-valore" id="icona" name="icona"
                         maxlength="40" placeholder="<?= Testo::esc($segnaposto) ?>"
                         value="<?= Testo::esc($inMod['icona']) ?>">
                </div>
                <div class="catageo-nota">
                  Nome di <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icons</a>
                  senza il prefisso <span class="catageo-valore">bi-</span>, oppure uno dei glifi
                  propri delle cavita qui sotto. Lasciandolo vuoto la voce eredita l'icona di
                  quella superiore: si compila solo per distinguere una voce dalle sorelle.
                </div>
                <?php
                /*
                 * I glifi propri si scelgono cliccandoli. Sono una dozzina e non
                 * esistono altrove: chiedere di digitarne il nome a memoria
                 * significherebbe che nessuno li userebbe, e resterebbero un
                 * insieme di simboli che c'e ma non si vede.
                 */
                ?>
                <div class="catageo-scelta-icone mt-2">
                  <?php foreach (Icone::elenco() as $glifo): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary catageo-glifo"
                            data-catageo-icona="<?= Testo::esc($glifo) ?>"
                            title="<?= Testo::esc($glifo) ?>">
                      <?= Icone::html($glifo) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="mb-3">
                <label for="note" class="form-label">Note</label>
                <textarea class="form-control" id="note" name="note" rows="3"><?= Testo::esc($inMod['note']) ?></textarea>
              </div>
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="attivo" name="attivo" value="1"
                       <?= $inMod['attivo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="attivo">Voce attiva</label>
              </div>
              <div class="catageo-nota mb-3">
                Il codice non è modificabile: e il riferimento memorizzato nelle schede.
              </div>
              <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Salva</button>
              <a class="btn btn-outline-secondary btn-sm" href="index.php?p=vocabolari&amp;voc=tipologie">Annulla</a>
            </form>
          </div>
        </div>
      <?php else: ?>
        <div class="card">
          <div class="card-header">
            <h2 class="h6 mb-0">Aggiungi una voce</h2>
          </div>
          <div class="card-body">
            <form method="post" action="index.php?p=vocabolari" class="needs-validation" novalidate>
              <?= Auth::campoToken() ?>
              <input type="hidden" name="voc" value="tipologie">
              <input type="hidden" name="operazione" value="tipologia-crea">

              <div class="mb-3">
                <label for="livello" class="form-label">Livello <span class="text-danger">*</span></label>
                <select class="form-select" id="livello" name="livello" required>
                  <?php foreach (Tipologie::ETICHETTE_LIVELLO as $chiave => $etichetta): ?>
                    <option value="<?= Testo::esc($chiave) ?>"><?= Testo::esc($etichetta) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label for="padre" class="form-label">Voce superiore</label>
                <select class="form-select" id="padre" name="padre">
                  <option value="">— nessuna (solo per le nature) —</option>
                  <optgroup label="Nature (per una tipologia)">
                    <?php foreach ($nature as $n): ?>
                      <option value="<?= Testo::esc($n['codice']) ?>"><?= Testo::esc($n['codice'] . ' — ' . $n['nome']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                  <optgroup label="Tipologie (per una sottotipologia)">
                    <?php foreach ($tipol as $t): ?>
                      <option value="<?= Testo::esc($t['codice']) ?>"><?= Testo::esc($t['codice'] . ' — ' . $t['nome']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                </select>
              </div>

              <div class="mb-3">
                <label for="codiceNuovo" class="form-label">Codice <span class="text-danger">*</span></label>
                <input type="text" class="form-control catageo-valore" id="codiceNuovo" name="codice" required
                       maxlength="30" placeholder="ART-IDR-CUN">
                <div class="catageo-nota">Maiuscole, cifre e trattino. Conviene mantenere la gerarchia nel codice.</div>
              </div>

              <div class="mb-3">
                <label for="nomeNuovo" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nomeNuovo" name="nome" required maxlength="120">
              </div>

              <div class="mb-3">
                <label for="iconaNuovo" class="form-label">Icona in mappa</label>
                <input type="text" class="form-control catageo-valore" id="iconaNuovo" name="icona"
                       maxlength="40" placeholder="lascia vuoto per ereditarla">
              </div>

              <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Aggiungi</button>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($voc === 'grandezze'): ?>

  <?php
  $categorie = Grandezze::categorie(false);
  $inMod     = $modifica !== '' ? Grandezze::trova($modifica) : null;
  ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <?php foreach ($categorie as $categoria): ?>
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">
              <span class="catageo-valore text-body-secondary"><?= Testo::esc($categoria['codice']) ?></span>
              <?= Testo::esc($categoria['nome']) ?>
            </h2>
            <span class="badge text-bg-light border"><?= count($categoria['grandezze']) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table table-sm catageo-tabella mb-0 align-middle">
              <thead>
                <tr>
                  <th scope="col">Codice</th>
                  <th scope="col">Grandezza</th>
                  <th scope="col">Unità</th>
                  <th scope="col">Intervallo atteso</th>
                  <th scope="col">Dec.</th>
                  <th scope="col" class="text-end">Azioni</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($categoria['grandezze'] as $g): ?>
                  <tr<?= $g['attivo'] ? '' : ' class="text-body-secondary"' ?>>
                    <td class="catageo-valore"><?= Testo::esc($g['codice']) ?></td>
                    <td>
                      <?= Testo::esc($g['nome']) ?>
                      <?php if (!$g['attivo']): ?><span class="badge text-bg-secondary ms-1">disattivata</span><?php endif; ?>
                    </td>
                    <td class="catageo-valore"><?= Testo::esc($g['unita']) ?></td>
                    <td class="small catageo-valore">
                      <?= Testo::esc(($g['min'] !== '' ? $g['min'] : '?') . ' … ' . ($g['max'] !== '' ? $g['max'] : '?')) ?>
                    </td>
                    <td class="catageo-valore"><?= (int) $g['decimali'] ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-secondary"
                         href="index.php?p=vocabolari&amp;voc=grandezze&amp;modifica=<?= urlencode($g['codice']) ?>">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <form method="post" action="index.php?p=vocabolari" class="d-inline">
                        <?= Auth::campoToken() ?>
                        <input type="hidden" name="voc" value="grandezze">
                        <input type="hidden" name="operazione" value="grandezza-elimina">
                        <input type="hidden" name="codice" value="<?= Testo::esc($g['codice']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-catageo-conferma="Eliminare &quot;<?= Testo::esc($g['nome']) ?>&quot;? Verra rifiutato se qualche serie di misure la usa.">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($categoria['grandezze'] === []): ?>
                  <tr><td colspan="6" class="text-body-secondary small">Nessuna grandezza in questa categoria.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="col-lg-4">
      <?php if ($inMod !== null): ?>
        <div class="card mb-3">
          <div class="card-header">
            <h2 class="h6 mb-0">Modifica <span class="catageo-valore"><?= Testo::esc($inMod['codice']) ?></span></h2>
          </div>
          <div class="card-body">
            <form method="post" action="index.php?p=vocabolari" class="needs-validation" novalidate>
              <?= Auth::campoToken() ?>
              <input type="hidden" name="voc" value="grandezze">
              <input type="hidden" name="operazione" value="grandezza-aggiorna">
              <input type="hidden" name="codice" value="<?= Testo::esc($inMod['codice']) ?>">

              <div class="mb-3">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome" required maxlength="120"
                       value="<?= Testo::esc($inMod['nome']) ?>">
              </div>
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label for="unita" class="form-label">Unità</label>
                  <input type="text" class="form-control catageo-valore" id="unita" name="unita" maxlength="20"
                         value="<?= Testo::esc($inMod['unita']) ?>">
                </div>
                <div class="col-6">
                  <label for="decimali" class="form-label">Decimali</label>
                  <input type="number" class="form-control" id="decimali" name="decimali" min="0" max="6"
                         value="<?= (int) $inMod['decimali'] ?>">
                </div>
                <div class="col-6">
                  <label for="min" class="form-label">Minimo atteso</label>
                  <input type="text" class="form-control catageo-valore" id="min" name="min"
                         value="<?= Testo::esc($inMod['min']) ?>">
                </div>
                <div class="col-6">
                  <label for="max" class="form-label">Massimo atteso</label>
                  <input type="text" class="form-control catageo-valore" id="max" name="max"
                         value="<?= Testo::esc($inMod['max']) ?>">
                </div>
              </div>
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="attivo" name="attivo" value="1"
                       <?= $inMod['attivo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="attivo">Grandezza attiva</label>
              </div>
              <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Salva</button>
              <a class="btn btn-outline-secondary btn-sm" href="index.php?p=vocabolari&amp;voc=grandezze">Annulla</a>
            </form>
          </div>
        </div>
      <?php else: ?>
        <div class="card mb-3">
          <div class="card-header"><h2 class="h6 mb-0">Nuova grandezza</h2></div>
          <div class="card-body">
            <form method="post" action="index.php?p=vocabolari" class="needs-validation" novalidate>
              <?= Auth::campoToken() ?>
              <input type="hidden" name="voc" value="grandezze">
              <input type="hidden" name="operazione" value="grandezza-crea">

              <div class="mb-3">
                <label for="categoria" class="form-label">Categoria <span class="text-danger">*</span></label>
                <select class="form-select" id="categoria" name="categoria" required>
                  <?php foreach ($categorie as $c): ?>
                    <option value="<?= Testo::esc($c['codice']) ?>"><?= Testo::esc($c['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="codiceG" class="form-label">Codice <span class="text-danger">*</span></label>
                <input type="text" class="form-control catageo-valore" id="codiceG" name="codice" required
                       maxlength="30" placeholder="T-ARIA">
              </div>
              <div class="mb-3">
                <label for="nomeG" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nomeG" name="nome" required maxlength="120">
              </div>
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label for="unitaG" class="form-label">Unità</label>
                  <input type="text" class="form-control catageo-valore" id="unitaG" name="unita" maxlength="20">
                </div>
                <div class="col-6">
                  <label for="decimaliG" class="form-label">Decimali</label>
                  <input type="number" class="form-control" id="decimaliG" name="decimali" min="0" max="6" value="2">
                </div>
                <div class="col-6">
                  <label for="minG" class="form-label">Minimo</label>
                  <input type="text" class="form-control catageo-valore" id="minG" name="min">
                </div>
                <div class="col-6">
                  <label for="maxG" class="form-label">Massimo</label>
                  <input type="text" class="form-control catageo-valore" id="maxG" name="max">
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Aggiungi</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h2 class="h6 mb-0">Nuova categoria</h2></div>
          <div class="card-body">
            <form method="post" action="index.php?p=vocabolari" class="needs-validation" novalidate>
              <?= Auth::campoToken() ?>
              <input type="hidden" name="voc" value="grandezze">
              <input type="hidden" name="operazione" value="categoria-crea">
              <div class="row g-2">
                <div class="col-5">
                  <input type="text" class="form-control form-control-sm catageo-valore" name="codice"
                         required maxlength="30" placeholder="Codice">
                </div>
                <div class="col-7">
                  <input type="text" class="form-control form-control-sm" name="nome"
                         required maxlength="120" placeholder="Nome categoria">
                </div>
              </div>
              <button type="submit" class="btn btn-outline-primary btn-sm mt-2">
                <i class="bi bi-plus-lg"></i> Aggiungi categoria
              </button>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>

  <?php
  $elenco = Periodi::elenco();
  $inMod  = $modifica !== '' ? Periodi::trova($modifica) : null;
  ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h2 class="h6 mb-0">Cronologia — <?= count($elenco) ?> periodi, in ordine cronologico</h2>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover catageo-tabella mb-0 align-middle">
            <thead>
              <tr>
                <th scope="col">Codice</th>
                <th scope="col">Periodo</th>
                <th scope="col">Estremi indicativi</th>
                <th scope="col" class="text-end">Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($elenco as $p): ?>
                <tr<?= $p['attivo'] ? '' : ' class="text-body-secondary"' ?>>
                  <td class="catageo-valore"><?= Testo::esc($p['codice']) ?></td>
                  <td>
                    <?= Testo::esc($p['nome']) ?>
                    <?php if (!$p['attivo']): ?><span class="badge text-bg-secondary ms-1">disattivato</span><?php endif; ?>
                  </td>
                  <td class="small"><?= Testo::esc(Periodi::estremiLeggibili($p) !== '' ? Periodi::estremiLeggibili($p) : '—') ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="index.php?p=vocabolari&amp;voc=periodi&amp;modifica=<?= urlencode($p['codice']) ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?p=vocabolari" class="d-inline">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="voc" value="periodi">
                      <input type="hidden" name="operazione" value="periodo-elimina">
                      <input type="hidden" name="codice" value="<?= Testo::esc($p['codice']) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger"
                              data-catageo-conferma="Eliminare il periodo &quot;<?= Testo::esc($p['nome']) ?>&quot;? Verra rifiutato se e usato da qualche scheda.">
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
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h2 class="h6 mb-0"><?= $inMod !== null ? 'Modifica periodo' : 'Nuovo periodo' ?></h2>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?p=vocabolari" class="needs-validation" novalidate>
            <?= Auth::campoToken() ?>
            <input type="hidden" name="voc" value="periodi">
            <input type="hidden" name="operazione" value="<?= $inMod !== null ? 'periodo-aggiorna' : 'periodo-crea' ?>">

            <div class="mb-3">
              <label for="codiceP" class="form-label">Codice <span class="text-danger">*</span></label>
              <input type="text" class="form-control catageo-valore" id="codiceP" name="codice" required
                     maxlength="20" placeholder="ROM-IMP"
                     value="<?= Testo::esc($inMod !== null ? $inMod['codice'] : '') ?>"
                     <?= $inMod !== null ? 'readonly' : '' ?>>
              <?php if ($inMod !== null): ?>
                <div class="catageo-nota">Non modificabile: e il riferimento usato dalle schede.</div>
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label for="nomeP" class="form-label">Nome <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="nomeP" name="nome" required maxlength="120"
                     value="<?= Testo::esc($inMod !== null ? $inMod['nome'] : '') ?>">
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label for="da" class="form-label">Dall'anno</label>
                <input type="text" class="form-control catageo-valore" id="da" name="da"
                       placeholder="-27" value="<?= Testo::esc($inMod !== null ? $inMod['da'] : '') ?>">
              </div>
              <div class="col-6">
                <label for="a" class="form-label">All'anno</label>
                <input type="text" class="form-control catageo-valore" id="a" name="a"
                       placeholder="476" value="<?= Testo::esc($inMod !== null ? $inMod['a'] : '') ?>">
              </div>
              <div class="col-12">
                <div class="catageo-nota">
                  Anni negativi per le date a.C. Si accetta anche la forma
                  <span class="catageo-valore">27 a.C.</span>, che viene convertita.
                  Sono gli estremi a rendere possibile la ricerca per intervallo.
                </div>
              </div>
            </div>

            <?php if ($inMod !== null): ?>
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="attivoP" name="attivo" value="1"
                       <?= $inMod['attivo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="attivoP">Periodo attivo</label>
              </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-check-lg"></i> <?= $inMod !== null ? 'Salva' : 'Aggiungi' ?>
            </button>
            <?php if ($inMod !== null): ?>
              <a class="btn btn-outline-secondary btn-sm" href="index.php?p=vocabolari&amp;voc=periodi">Annulla</a>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php endif; ?>
