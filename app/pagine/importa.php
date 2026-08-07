<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/importa.php
 *  Descrizione ..: Importazione massiva di ipogei da CSV (9.14).
 *
 *                  Due passi obbligati: si carica il file e si dichiara quale
 *                  colonna e cosa, poi si vede l'anteprima riga per riga, e
 *                  solo allora si conferma. Fino alla conferma non viene
 *                  scritto nulla.
 *
 *                  La pagina insiste su una raccomandazione: fare un backup
 *                  prima. Un import sbagliato non si annulla, e le schede
 *                  create andrebbero cancellate una per una.
 *  Versione .....: 0.16.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.16.0  2026-08-06  D.Candela  Prima stesura (fase 9b).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('strumenti');

$titolo  = 'Importazione da CSV';
$ritorno = 'index.php?p=importa';

/** Dove sosta il file fra il caricamento e la conferma. */
$chiaveSosta = 'catageo_import_ipogei';

// ============================================================================
//  OPERAZIONI
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();

    $operazione = (string) ($_POST['operazione'] ?? '');

    try {
        if ($operazione === 'carica') {
            $caricati = Upload::elenco((array) ($_FILES['file'] ?? []));
            if ($caricati === []) {
                throw new UploadEccezione('Nessun file ricevuto.');
            }
            $verificato = Upload::verifica($caricati[0], 'scientifici');

            $sosta = Percorsi::unisci(
                Percorsi::assicuraCartella(Percorsi::tmp()),
                'importa-' . bin2hex(random_bytes(12)) . '.csv'
            );
            if (!@move_uploaded_file($verificato['tmp'], $sosta)) {
                throw new UploadEccezione('Impossibile mettere in sosta il file caricato.');
            }

            // Una sosta precedente si porta via subito: e l'unico momento in
            // cui si sa con certezza che non serve piu.
            $vecchia = $_SESSION[$chiaveSosta]['percorso'] ?? null;
            if (is_string($vecchia) && is_file($vecchia)) {
                @unlink($vecchia);
            }

            $_SESSION[$chiaveSosta] = [
                'percorso' => $sosta,
                'nome'     => $verificato['nome'],
            ];

            header('Location: index.php?p=importa&azione=mappa');
            exit;
        }

        if ($operazione === 'esegui') {
            $sosta = $_SESSION[$chiaveSosta] ?? null;
            if (!is_array($sosta) || !is_file((string) $sosta['percorso'])) {
                throw new IpogeoEccezione(
                    'Il file caricato non è più disponibile: ricaricarlo e rifare l\'anteprima.');
            }

            /*
             * Si esige che l'anteprima sia stata vista. Come nella migrazione,
             * non e una difesa contro un attacco ma contro il collegamento
             * salvato che salterebbe la schermata fatta apposta per fermarsi.
             */
            if (trim((string) ($_POST['visto'] ?? '')) === '') {
                throw new IpogeoEccezione(
                    'Conferma senza anteprima: rivedere le righe prima di importare.');
            }

            $mappatura = [];
            foreach (array_keys(ImportIpogei::CAMPI) as $campo) {
                $colonna = (string) ($_POST['col_' . $campo] ?? '');
                if ($colonna !== '') {
                    $mappatura[$campo] = (int) $colonna;
                }
            }

            $esito = ImportIpogei::esegui(
                (string) $sosta['percorso'],
                $mappatura,
                (string) ($_POST['separatore'] ?? ';'),
                (string) ($_POST['catalogo'] ?? ''),
                !empty($_POST['codiceDalFile'])
            );

            @unlink((string) $sosta['percorso']);
            unset($_SESSION[$chiaveSosta]);

            if ($esito['creati'] !== []) {
                Auth::messaggio('successo', count($esito['creati']) . ' ipogei importati.');
            }
            if ($esito['saltate'] > 0) {
                Auth::messaggio('info', $esito['saltate']
                    . ' righe saltate perché il codice era già presente: le schede '
                    . 'esistenti non sono state toccate.');
            }
            if ($esito['rifiutate'] > 0) {
                Auth::messaggio('avviso', $esito['rifiutate'] . ' righe rifiutate. '
                    . implode(' · ', array_slice($esito['errori'], 0, 8)));
            }
            if ($esito['creati'] === [] && $esito['saltate'] === 0 && $esito['rifiutate'] === 0) {
                Auth::messaggio('avviso', 'Il file non conteneva righe importabili.');
            }

            header('Location: ' . $ritorno);
            exit;
        }

        Auth::messaggio('errore', 'Operazione non riconosciuta.');
    } catch (UploadEccezione | IpogeoEccezione | CatalogoEccezione | CsvEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: ' . $ritorno);
    exit;
}

// ============================================================================
//  PASSO 2: mappatura e anteprima
// ============================================================================

$azione = isset($_GET['azione']) ? (string) $_GET['azione'] : '';
$sosta = $_SESSION[$chiaveSosta] ?? null;

if ($azione === 'mappa' && is_array($sosta) && is_file((string) $sosta['percorso'])) {

    $anteprimaFile = Scientifici::anteprimaCsv((string) $sosta['percorso'], 5);
    $separatore = (string) ($_GET['separatore'] ?? $anteprimaFile['separatore']);
    if ($separatore !== $anteprimaFile['separatore']) {
        $anteprimaFile = Scientifici::anteprimaCsv((string) $sosta['percorso'], 5);
    }

    $mappatura = [];
    foreach (array_keys(ImportIpogei::CAMPI) as $campo) {
        if (isset($_GET['col_' . $campo]) && (string) $_GET['col_' . $campo] !== '') {
            $mappatura[$campo] = (int) $_GET['col_' . $campo];
        }
    }
    if ($mappatura === []) {
        $mappatura = ImportIpogei::mappaturaSuggerita($anteprimaFile['intestazione']);
    }

    $catalogo = (string) ($_GET['catalogo'] ?? Cataloghi::siglaAttiva());
    $codiceDalFile = isset($_GET['codiceDalFile'])
        ? !empty($_GET['codiceDalFile'])
        : isset($mappatura['codice']);

    $anteprima = null;
    if (!empty($_GET['anteprima'])) {
        $anteprima = ImportIpogei::anteprima(
            (string) $sosta['percorso'], $mappatura, $separatore, $catalogo, $codiceDalFile);
    }
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1>Importazione: mappatura</h1>
        <p class="text-body-secondary mb-0">
          File <span class="catageo-valore"><?= Testo::esc((string) $sosta['nome']) ?></span>
        </p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
        <i class="bi bi-x-lg"></i> Annulla
      </a>
    </div>

    <?php if ($anteprimaFile['intestazione'] === []): ?>

      <div class="alert alert-danger">
        Il file non sembra un CSV leggibile: nessuna intestazione riconosciuta.
      </div>

    <?php else: ?>

      <div class="card mb-4">
        <div class="card-header"><h2 class="h6 mb-0">Prime righe del file</h2></div>
        <div class="table-responsive">
          <table class="table table-sm catageo-tabella mb-0">
            <thead>
              <tr>
                <?php foreach ($anteprimaFile['intestazione'] as $i => $nome): ?>
                  <th>
                    <span class="catageo-nota">col. <?= $i ?></span><br>
                    <?= Testo::esc($nome) ?>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($anteprimaFile['righe'] as $riga): ?>
                <tr>
                  <?php foreach (array_keys($anteprimaFile['intestazione']) as $i): ?>
                    <td class="catageo-valore"><?= Testo::esc((string) ($riga[$i] ?? '')) ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body catageo-nota">
          Separatore riconosciuto:
          <span class="catageo-valore"><?= Testo::esc($separatore === "\t" ? 'tabulazione' : $separatore) ?></span>.
          Un CSV esportato da CATAGEO si reimporta senza toccarlo.
        </div>
      </div>

      <form method="get" action="index.php" class="card mb-4">
        <input type="hidden" name="p" value="importa">
        <input type="hidden" name="azione" value="mappa">
        <input type="hidden" name="anteprima" value="1">
        <input type="hidden" name="separatore" value="<?= Testo::esc($separatore) ?>">

        <div class="card-header"><h2 class="h6 mb-0">Quale colonna e cosa</h2></div>
        <div class="card-body">
          <div class="row g-3">
            <?php foreach (ImportIpogei::CAMPI as $campo => $dati): ?>
              <div class="col-md-3">
                <label for="col_<?= $campo ?>" class="form-label">
                  <?= Testo::esc($dati['etichetta']) ?>
                  <?php if (in_array($campo, ImportIpogei::OBBLIGATORI, true)): ?>
                    <span class="text-danger">*</span>
                  <?php endif; ?>
                </label>
                <select class="form-select form-select-sm" id="col_<?= $campo ?>" name="col_<?= $campo ?>">
                  <option value="">—</option>
                  <?php foreach ($anteprimaFile['intestazione'] as $i => $nome): ?>
                    <option value="<?= $i ?>"
                      <?= (isset($mappatura[$campo]) && $mappatura[$campo] === (int) $i) ? 'selected' : '' ?>>
                      col. <?= $i ?> — <?= Testo::esc($nome) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endforeach; ?>
          </div>

          <hr>

          <div class="row g-3">
            <div class="col-md-5">
              <label for="catalogo" class="form-label">Catalogo di destinazione</label>
              <select class="form-select" id="catalogo" name="catalogo">
                <?php foreach (Cataloghi::elenco() as $c): ?>
                  <option value="<?= Testo::esc((string) $c['sigla']) ?>"
                    <?= $catalogo === (string) $c['sigla'] ? 'selected' : '' ?>
                    <?= empty($c['attivo']) ? 'disabled' : '' ?>>
                    <?= Testo::esc((string) $c['sigla'] . ' — ' . (string) $c['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-7 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="codiceDalFile"
                       name="codiceDalFile" value="1" <?= $codiceDalFile ? 'checked' : '' ?>>
                <label class="form-check-label" for="codiceDalFile">
                  Usa i codici che stanno nel file
                  <div class="catageo-nota">
                    Serve a importare un catasto esistente conservandone la
                    numerazione. Senza la spunta i codici li assegna la serie del
                    catalogo. In entrambi i casi una scheda già presente
                    <strong>non viene mai sovrascritta</strong>.
                  </div>
                </label>
              </div>
            </div>

            <div class="col-12">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-eye"></i> Vedi l'anteprima
              </button>
            </div>
          </div>
        </div>
      </form>

      <?php if ($anteprima !== null): ?>

        <div class="card">
          <div class="card-header">
            <h2 class="h6 mb-0">
              Anteprima
              <span class="catageo-nota">
                · <?= (int) $anteprima['totale'] ?> righe lette:
                <?= (int) $anteprima['importabili'] ?> importabili,
                <?= (int) $anteprima['saltate'] ?> saltate,
                <?= (int) $anteprima['rifiutate'] ?> rifiutate
              </span>
            </h2>
          </div>

          <?php foreach ($anteprima['avvisi'] as $avviso): ?>
            <div class="card-body pb-0">
              <div class="alert alert-warning mb-0"><?= Testo::esc($avviso) ?></div>
            </div>
          <?php endforeach; ?>

          <div class="table-responsive">
            <table class="table table-sm catageo-tabella mb-0 align-middle">
              <thead>
                <tr>
                  <th style="width:4rem">Riga</th>
                  <th>Nome</th>
                  <th>Codice</th>
                  <th>Esito</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($anteprima['righe'] as $voce): ?>
                  <tr<?= $voce['esito'] === 'importabile' ? '' : ' class="opacity-75"' ?>>
                    <td class="catageo-valore"><?= (int) $voce['riga'] ?></td>
                    <td><?= Testo::esc((string) $voce['nome']) ?: '<span class="text-body-tertiary">—</span>' ?></td>
                    <td class="catageo-valore">
                      <?= Testo::esc((string) ($voce['codiceAssegnato'] ?? $voce['codice'])) ?>
                    </td>
                    <td>
                      <?php if ($voce['esito'] === 'importabile'): ?>
                        <span class="badge text-bg-success">si crea</span>
                      <?php elseif ($voce['esito'] === 'saltata'): ?>
                        <span class="badge text-bg-secondary">si salta</span>
                        <div class="catageo-nota"><?= Testo::esc((string) $voce['motivo']) ?></div>
                      <?php else: ?>
                        <span class="badge text-bg-danger">si rifiuta</span>
                        <div class="catageo-nota"><?= Testo::esc((string) $voce['motivo']) ?></div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="card-body">
            <?php if ($anteprima['troncata']): ?>
              <div class="catageo-nota mb-3">
                In tabella le prime <?= ImportIpogei::ANTEPRIMA ?> righe delle
                <?= (int) $anteprima['totale'] ?> lette. I conteggi qui sopra si
                riferiscono a tutte.
              </div>
            <?php endif; ?>

            <?php if ((int) $anteprima['importabili'] === 0): ?>
              <p class="text-body-secondary mb-0">
                Nessuna riga importabile: correggere il file o la mappatura.
              </p>
            <?php else: ?>
              <div class="alert alert-warning d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                <div>
                  <div class="fw-semibold">Prima di procedere, fare un backup</div>
                  L'importazione non si annulla: le schede create andrebbero
                  cancellate una per una.
                  <a href="index.php?p=strumenti">Vai agli strumenti</a>.
                </div>
              </div>

              <form method="post" action="index.php?p=importa">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="operazione" value="esegui">
                <input type="hidden" name="visto" value="1">
                <input type="hidden" name="separatore" value="<?= Testo::esc($separatore) ?>">
                <input type="hidden" name="catalogo" value="<?= Testo::esc($catalogo) ?>">
                <?php if ($codiceDalFile): ?>
                  <input type="hidden" name="codiceDalFile" value="1">
                <?php endif; ?>
                <?php foreach ($mappatura as $campo => $colonna): ?>
                  <input type="hidden" name="col_<?= Testo::esc($campo) ?>" value="<?= (int) $colonna ?>">
                <?php endforeach; ?>

                <button type="submit" class="btn btn-danger"
                        data-catageo-conferma="Creare <?= (int) $anteprima['importabili'] ?> schede in <?= Testo::esc($catalogo) ?>? L'operazione non si annulla.">
                  <i class="bi bi-database-add"></i>
                  Importa <?= (int) $anteprima['importabili'] ?> ipogei in <?= Testo::esc($catalogo) ?>
                </button>
                <div class="catageo-nota mt-2">
                  Le schede importate nascono <strong>bozza</strong> se il file non
                  dice altrimenti: sono dati che nessuno ha ancora guardato, e
                  pubblicarli d'ufficio li mescolerebbe a quelli verificati.
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>

      <?php endif; ?>

    <?php endif; ?>

    <?php
    return;
}

// ============================================================================
//  PASSO 1: caricamento
// ============================================================================
?>

<div class="catageo-intestazione">
  <div>
    <h1>Importazione da CSV</h1>
    <p class="text-body-secondary mb-0">
      Crea molte schede in una volta da un file esterno.
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="index.php?p=strumenti">
    <i class="bi bi-tools"></i> Strumenti
  </a>
</div>

<div class="alert alert-warning d-flex align-items-start gap-2">
  <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
  <div>
    <div class="fw-semibold">Fare un backup prima di importare</div>
    L'importazione non si annulla. Le schede create andrebbero poi cancellate una
    per una, e i codici consumati non tornano indietro.
    <a href="index.php?p=strumenti">Il backup si fa dagli strumenti</a>.
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0">Carica il file</h2></div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" action="index.php?p=importa">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="carica">

          <div class="mb-3">
            <label for="file" class="form-label">File CSV</label>
            <input type="file" class="form-control" id="file" name="file" accept=".csv,.txt" required>
            <div class="catageo-nota">
              Prima riga di intestazione. Separatore, BOM e virgola decimale
              vengono riconosciuti.
            </div>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="bi bi-upload"></i> Carica e prosegui
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0">Come deve essere il file</h2></div>
      <div class="card-body">
        <p class="text-body-secondary">
          Servono <strong>nome</strong>, <strong>tipologia</strong> e le
          <strong>coordinate</strong>: sono gli stessi campi che il censimento
          a mano esige, e senza posizione una scheda non comparirebbe ne in
          mappa ne nelle ricerche per area. Tutto il resto e facoltativo e si
          completa dopo.
        </p>
        <p class="catageo-nota">Colonne riconosciute per nome:</p>
        <p class="catageo-valore" style="font-size:.85em">
          <?= Testo::esc(implode(', ', array_map(
              static fn (array $d): string => $d['alias'][0], ImportIpogei::CAMPI))) ?>
        </p>
        <div class="catageo-nota">
          Un CSV <a href="index.php?p=ricerca">esportato dalla ricerca</a> si
          reimporta senza modifiche: e il modo più semplice per travasare dati
          fra due installazioni.
        </div>
      </div>
    </div>
  </div>
</div>
