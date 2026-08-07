<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/migrazione.php
 *  Descrizione ..: Migrazione di ipogei fra cataloghi, con anteprima
 *                  obbligatoria e tracciato (5.5).
 *
 *                  L'anteprima non e un passaggio di cortesia: mostra i codici
 *                  ESATTI che verranno assegnati, e senza averla vista non si
 *                  puo confermare. Una migrazione non si annulla con un tasto,
 *                  e i codici assegnati non tornano indietro.
 *
 *                  La conferma porta con se l'elenco dei codici gia visti: se
 *                  nel frattempo l'archivio e cambiato, si scrive comunque cio
 *                  che il catalogo assegna in quel momento, e la pagina
 *                  confronta e dichiara le differenze.
 *  Versione .....: 0.14.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.14.0  2026-08-06  D.Candela  Prima stesura (fase 8b).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('migra_catalogo');

$titolo = 'Migrazione fra cataloghi';
$ritorno = 'index.php?p=migrazione';

/*
 * I codici arrivano da tre strade: l'elenco scritto a mano nella textarea, la
 * selezione fatta nella ricerca (codici[] in GET) e il modulo di conferma
 * (codici[] in POST). Si accettano tutte e si fondono.
 */
$codici = [];

foreach ((array) ($_REQUEST['codici'] ?? []) as $voce) {
    $codici[] = trim((string) $voce);
}

foreach (preg_split('/[\s,;]+/', (string) ($_REQUEST['elenco'] ?? '')) ?: [] as $voce) {
    $codici[] = trim((string) $voce);
}

$codici = array_values(array_filter($codici));

/*
 * I codici storici si risolvono subito alla scheda corrente: chi incolla un
 * elenco preso da una pubblicazione non deve scoprire a meta operazione che
 * meta dei codici non esistono piu.
 */
$risolti = [];
foreach ($codici as $voce) {
    $r = Ricerca::risolviCodice($voce);
    $risolti[] = $r === null ? $voce : $r['codice'];
}
$codici = array_values(array_unique($risolti));

$destinazione = trim((string) ($_REQUEST['destinazione'] ?? ''));
$motivo = trim((string) ($_REQUEST['motivo'] ?? '')) ?: 'migrazione catalogo';

$eseguita = null;

// ============================================================================
//  ESECUZIONE
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['operazione'] ?? '') === 'esegui') {
    Auth::esigiToken();

    try {
        /*
         * Si esige che l'anteprima sia stata vista: il modulo di conferma porta
         * i codici previsti in un campo nascosto. Non e una difesa contro un
         * attacco — chi puo migrare puo comporre la richiesta a mano — ma
         * contro il caso in cui si arrivi qui per un collegamento salvato,
         * saltando la schermata che serve proprio a fermarsi un momento.
         */
        if (trim((string) ($_POST['previsti'] ?? '')) === '') {
            throw new IpogeoEccezione(
                'Conferma senza anteprima: rivedere i codici prima di procedere.');
        }

        $previsti = array_values(array_filter(
            array_map('trim', explode(',', (string) $_POST['previsti']))
        ));

        $eseguita = Migrazione::esegui($codici, $destinazione, $motivo);

        // Cio che e stato scritto si confronta con cio che era stato mostrato:
        // se qualcuno ha censito un ipogeo nel frattempo, i codici scalano e
        // chi ha confermato deve saperlo.
        $assegnati = array_column($eseguita['migrati'], 'codice');
        $diversi = array_values(array_diff($assegnati, $previsti));

        if ($eseguita['migrati'] !== []) {
            Auth::messaggio('successo',
                count($eseguita['migrati']) . ' ipogei migrati in ' . $destinazione . '.');
        }
        if ($diversi !== []) {
            Auth::messaggio('avviso',
                'Alcuni codici assegnati differiscono da quelli mostrati in anteprima ('
                . implode(', ', $diversi) . '): l\'archivio e cambiato nel frattempo.');
        }
        foreach ($eseguita['falliti'] as $fallito) {
            Auth::messaggio('errore', $fallito['codice'] . ': ' . $fallito['messaggio']);
        }

        header('Location: ' . $ritorno);
        exit;

    } catch (IpogeoEccezione | CatalogoEccezione | XmlEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
        header('Location: ' . $ritorno);
        exit;
    }
}

// ============================================================================
//  ANTEPRIMA
// ============================================================================

$anteprima = [];
$praticabili = [];

if ($codici !== [] && $destinazione !== '') {
    $anteprima = Migrazione::anteprima($codici, $destinazione);
    $praticabili = array_values(array_filter($anteprima, static fn (array $v): bool => $v['ok']));
}

$cataloghi = Cataloghi::elenco();
$tracciato = Migrazione::tracciato(30);
?>

<div class="catageo-intestazione">
  <div>
    <h1>Migrazione fra cataloghi</h1>
    <p class="text-body-secondary mb-0">
      Sposta uno o più ipogei in un altro catalogo assegnando i codici della sua
      serie. Il codice di origine resta risolvibile.
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="index.php?p=ricerca">
    <i class="bi bi-search"></i> Scegli dalla ricerca
  </a>
</div>

<div class="alert alert-warning d-flex align-items-start gap-2">
  <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
  <div>
    <div class="fw-semibold">Operazione non annullabile</div>
    Le cartelle vengono spostate e rinominate, e i codici assegnati non tornano
    indietro. Il codice di origine continua a risolvere verso la scheda, ma
    riportare un ipogeo nel catalogo di partenza richiede una seconda migrazione,
    che gli assegnerebbe un codice nuovo e non quello di prima.
  </div>
</div>

<!-- ======================================================== selezione -->
<form method="get" action="index.php" class="card mb-4">
  <input type="hidden" name="p" value="migrazione">

  <div class="card-header"><h2 class="h6 mb-0">Cosa spostare, e dove</h2></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-7">
        <label for="elenco" class="form-label">Codici degli ipogei</label>
        <textarea class="form-control catageo-valore" id="elenco" name="elenco" rows="4"
                  placeholder="Un codice per riga, oppure separati da virgola"><?= Testo::esc(implode("\n", $codici)) ?></textarea>
        <div class="catageo-nota">
          Si accettano anche i codici storici: vengono risolti alla scheda corrente.
        </div>
      </div>

      <div class="col-md-5">
        <label for="destinazione" class="form-label">Catalogo di destinazione</label>
        <select class="form-select" id="destinazione" name="destinazione">
          <option value="">— scegli —</option>
          <?php foreach ($cataloghi as $c): ?>
            <option value="<?= Testo::esc((string) $c['sigla']) ?>"
              <?= $destinazione === (string) $c['sigla'] ? 'selected' : '' ?>
              <?= empty($c['attivo']) ? 'disabled' : '' ?>>
              <?= Testo::esc((string) $c['sigla'] . ' — ' . (string) $c['nome']) ?>
              <?= empty($c['attivo']) ? '(disattivato)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="motivo" class="form-label mt-3">Motivo</label>
        <input type="text" class="form-control" id="motivo" name="motivo"
               value="<?= Testo::esc($motivo) ?>">
        <div class="catageo-nota">Finisce nella traccia storica della scheda.</div>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-eye"></i> Vedi l'anteprima
        </button>
      </div>
    </div>
  </div>
</form>

<?php if ($anteprima !== []): ?>

  <div class="card mb-4">
    <div class="card-header">
      <h2 class="h6 mb-0">
        Anteprima
        <span class="catageo-nota">
          · <?= count($praticabili) ?> su <?= count($anteprima) ?> spostabili
        </span>
      </h2>
    </div>
    <div class="table-responsive">
      <table class="table catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th>Ipogeo</th>
            <th>Catalogo attuale</th>
            <th>Codice nuovo</th>
            <th>Serie</th>
            <th>Esito previsto</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($anteprima as $voce): ?>
            <tr<?= $voce['ok'] ? '' : ' class="opacity-75"' ?>>
              <td>
                <span class="catageo-codice"><?= Testo::esc($voce['codice']) ?></span>
                <?= Testo::esc($voce['nome']) ?>
              </td>
              <td><?= Testo::esc($voce['catalogo']) ?></td>
              <td>
                <?php if ($voce['ok']): ?>
                  <span class="catageo-valore fw-semibold"><?= Testo::esc($voce['nuovoCodice']) ?></span>
                <?php else: ?>
                  <span class="text-body-tertiary">—</span>
                <?php endif; ?>
              </td>
              <td class="catageo-valore"><?= Testo::esc($voce['serie']) ?></td>
              <td>
                <?php if ($voce['ok']): ?>
                  <span class="badge text-bg-success">si sposta</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">si salta</span>
                  <div class="catageo-nota"><?= Testo::esc($voce['messaggio']) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card-body">
      <?php if ($praticabili === []): ?>
        <p class="text-body-secondary mb-0">
          Nessuno degli ipogei indicati e spostabile in questo catalogo.
        </p>
      <?php else: ?>
        <form method="post" action="index.php?p=migrazione">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="esegui">
          <input type="hidden" name="destinazione" value="<?= Testo::esc($destinazione) ?>">
          <input type="hidden" name="motivo" value="<?= Testo::esc($motivo) ?>">
          <?php foreach ($praticabili as $voce): ?>
            <input type="hidden" name="codici[]" value="<?= Testo::esc($voce['codice']) ?>">
          <?php endforeach; ?>
          <input type="hidden" name="previsti"
                 value="<?= Testo::esc(implode(',', array_column($praticabili, 'nuovoCodice'))) ?>">

          <button type="submit" class="btn btn-danger"
                  data-catageo-conferma="Spostare <?= count($praticabili) ?> ipogei in <?= Testo::esc($destinazione) ?>? L'operazione non si annulla.">
            <i class="bi bi-arrow-left-right"></i>
            Sposta <?= count($praticabili) ?> ipogei in <?= Testo::esc($destinazione) ?>
          </button>
          <div class="catageo-nota mt-2">
            Gli ipogei che si saltano restano dove sono: non serve toglierli
            dall'elenco.
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>

<!-- ======================================================== tracciato -->
<div class="card">
  <div class="card-header">
    <h2 class="h6 mb-0">Migrazioni già eseguite</h2>
  </div>
  <?php if ($tracciato === []): ?>
    <div class="card-body">
      <p class="text-body-secondary mb-0">
        Nessuna migrazione registrata. Il tracciato si trova in
        <span class="catageo-valore">dati/_log/<?= Testo::esc(Migrazione::FILE_TRACCIATO) ?></span>.
      </p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm catageo-tabella mb-0">
        <thead>
          <tr>
            <th style="width:11rem">Quando</th>
            <th>Da</th>
            <th>A</th>
            <th>Ipogeo</th>
            <th>Utente</th>
            <th>Esito</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tracciato as $riga): ?>
            <tr>
              <td class="catageo-valore"><?= Testo::esc(str_replace('T', ' ', (string) ($riga['data'] ?? ''))) ?></td>
              <td>
                <span class="catageo-codice"><?= Testo::esc((string) ($riga['codice_precedente'] ?? '')) ?></span>
                <span class="catageo-nota"><?= Testo::esc((string) ($riga['catalogo_precedente'] ?? '')) ?></span>
              </td>
              <td>
                <?php if ((string) ($riga['codice_nuovo'] ?? '') !== ''): ?>
                  <a href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode((string) $riga['codice_nuovo']) ?>">
                    <span class="catageo-codice"><?= Testo::esc((string) $riga['codice_nuovo']) ?></span>
                  </a>
                <?php else: ?>
                  <span class="text-body-tertiary">—</span>
                <?php endif; ?>
                <span class="catageo-nota"><?= Testo::esc((string) ($riga['catalogo_nuovo'] ?? '')) ?></span>
              </td>
              <td><?= Testo::esc((string) ($riga['nome'] ?? '')) ?></td>
              <td class="catageo-nota"><?= Testo::esc((string) ($riga['utente'] ?? '')) ?></td>
              <td>
                <?php if ((string) ($riga['esito'] ?? '') === 'riuscita'): ?>
                  <span class="badge text-bg-success">riuscita</span>
                <?php else: ?>
                  <span class="badge text-bg-danger">fallita</span>
                  <div class="catageo-nota"><?= Testo::esc((string) ($riga['dettaglio'] ?? '')) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
