<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/scientifici.php
 *  Descrizione ..: Dati scientifici di un ipogeo: punti di misura, serie,
 *                  letture, statistiche, grafico e importazione (9.8).
 *
 *                  L'importazione da datalogger avviene in due passi: si carica
 *                  il file, si vede un'anteprima e si dichiara quale colonna e
 *                  la data, quale il valore. Nessun riconoscimento automatico
 *                  puo indovinare come uno strumento ha nominato le colonne, e
 *                  un'importazione che sbaglia in silenzio e peggio di
 *                  un'importazione che chiede.
 *  Versione .....: 0.11.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.11.0  2026-08-06  D.Candela  Prima stesura (fase 7c).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');

$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';
$azione = isset($_GET['azione']) ? (string) $_GET['azione'] : 'elenco';
$prog   = isset($_GET['prog']) ? (int) $_GET['prog'] : 0;

$risoluzione = $codice === '' ? null : Ipogeo::risolvi($codice);
if ($risoluzione === null) {
    Auth::messaggio('errore', 'Ipogeo non indicato o inesistente.');
    header('Location: index.php?p=ipogei');
    exit;
}

$scheda = $risoluzione['scheda'];
$codice = $risoluzione['codiceCorrente'];

if (!Visibilita::schedaVisibile(
    (string) $scheda['ubicazione']['riservatezza'],
    (string) $scheda['catasto']['statoScheda']
)) {
    Auth::messaggio('errore', 'La scheda richiesta non è consultabile con il livello di utenza in uso.');
    header('Location: index.php?p=ipogei');
    exit;
}

$nomeIpogeo   = (string) $scheda['identificazione']['nome'];
$puoCompilare = Auth::puo('compila_sezioni');
$puoImportare = Auth::puo('importa_serie');
$ritorno = 'index.php?p=scientifici&codice=' . urlencode($codice);

/** Chiave di sessione dove sosta l'anteprima fra i due passi dell'import. */
$chiaveImport = 'catageo_import_' . $codice;

// ============================================================================
//  OPERAZIONI
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    Auth::esigi('compila_sezioni');

    $operazione = (string) ($_POST['operazione'] ?? '');

    try {
        switch ($operazione) {

            case 'salvaPunto':
                $id = Scientifici::salvaPunto($codice, (string) ($_POST['id'] ?? ''), [
                    'nome'        => (string) ($_POST['nome'] ?? ''),
                    'descrizione' => (string) ($_POST['descrizione'] ?? ''),
                    'latitudine'  => (string) ($_POST['latitudine'] ?? ''),
                    'longitudine' => (string) ($_POST['longitudine'] ?? ''),
                    'quota'       => (string) ($_POST['quota'] ?? ''),
                    'progressiva' => (string) ($_POST['progressiva'] ?? ''),
                ]);
                Auth::messaggio('successo', 'Punto di misura ' . $id . ' salvato.');
                break;

            case 'eliminaPunto':
                Scientifici::eliminaPunto($codice, (string) ($_POST['id'] ?? ''));
                Auth::messaggio('successo', 'Punto di misura rimosso.');
                break;

            case 'creaSerie':
            case 'aggiornaSerie':
                $dati = [];
                foreach (array_keys(Scientifici::CAMPI_SERIE) as $campo) {
                    $dati[$campo] = (string) ($_POST[$campo] ?? '');
                }
                // L'unita non si chiede: e quella della grandezza scelta, e
                // lasciarla libera permetterebbe di registrare gradi in una
                // serie di metri senza che nulla lo segnali.
                $grandezza = Grandezze::trova((string) $dati['grandezza']);
                $dati['unita'] = $grandezza === null ? '' : (string) $grandezza['unita'];

                if ($operazione === 'creaSerie') {
                    $nuova = Scientifici::creaSerie($codice, $dati);
                    IndiceIpogei::aggiorna($codice);
                    Auth::messaggio('successo', 'Serie ' . Sezioni::riferimento('SC', $nuova) . ' creata.');
                    header('Location: index.php?p=scientifici&codice=' . urlencode($codice)
                        . '&azione=serie&prog=' . $nuova);
                    exit;
                }

                $p = (int) ($_POST['progressivo'] ?? 0);
                Scientifici::aggiornaSerie($codice, $p, $dati);
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', 'Serie aggiornata.');
                header('Location: index.php?p=scientifici&codice=' . urlencode($codice)
                    . '&azione=serie&prog=' . $p);
                exit;

            case 'eliminaSerie':
                Scientifici::eliminaSerie($codice, (int) ($_POST['progressivo'] ?? 0));
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo',
                    'Serie rimossa. Il CSV è stato spostato in "' . $codice . ' - '
                    . Risorse::CARTELLA_RIMOSSI . '" e resta recuperabile.');
                break;

            case 'aggiungiLettura':
                $p = (int) ($_POST['progressivo'] ?? 0);
                Scientifici::aggiungiLettura($codice, $p, [
                    'data'          => (string) ($_POST['data'] ?? ''),
                    'ora'           => (string) ($_POST['ora'] ?? ''),
                    'valore'        => (string) ($_POST['valore'] ?? ''),
                    'validita'      => (string) ($_POST['validita'] ?? 'valido'),
                    'esploratoreId' => (string) ($_POST['esploratoreId'] ?? ''),
                    'note'          => (string) ($_POST['note'] ?? ''),
                ]);
                Auth::messaggio('successo', 'Lettura registrata.');
                header('Location: index.php?p=scientifici&codice=' . urlencode($codice)
                    . '&azione=serie&prog=' . $p);
                exit;

            case 'anteprimaImport':
                Auth::esigi('importa_serie');
                $p = (int) ($_POST['progressivo'] ?? 0);
                if (Scientifici::trovaSerie($codice, $p) === null) {
                    throw new ScientificiEccezione('Serie non trovata.');
                }

                $caricati = Upload::elenco((array) ($_FILES['file'] ?? []));
                if ($caricati === []) {
                    throw new UploadEccezione('Nessun file ricevuto.');
                }
                $verificato = Upload::verifica($caricati[0], Sezioni::chiaveEstensioni('SC'));

                /*
                 * Il temporaneo di PHP sparisce a fine richiesta, ma la
                 * mappatura delle colonne avviene nella richiesta successiva:
                 * il file va messo in sosta. Sta sotto dati/tmp e non in
                 * sessione, perche un CSV da datalogger pesa quanto la
                 * sessione non deve pesare.
                 */
                $sosta = Percorsi::unisci(
                    Percorsi::assicuraCartella(Percorsi::tmp()),
                    'import-' . bin2hex(random_bytes(12)) . '.csv'
                );
                if (!@move_uploaded_file($verificato['tmp'], $sosta)) {
                    throw new UploadEccezione('Impossibile mettere in sosta il file caricato.');
                }

                // Una sosta precedente rimasta appesa si porta via subito: e
                // l'unico momento in cui si sa con certezza che non serve piu.
                $vecchia = $_SESSION[$chiaveImport]['percorso'] ?? null;
                if (is_string($vecchia) && is_file($vecchia)) {
                    @unlink($vecchia);
                }

                $_SESSION[$chiaveImport] = [
                    'progressivo' => $p,
                    'percorso'    => $sosta,
                    'nome'        => $verificato['nome'],
                ];

                header('Location: index.php?p=scientifici&codice=' . urlencode($codice)
                    . '&azione=import&prog=' . $p);
                exit;

            case 'eseguiImport':
                Auth::esigi('importa_serie');
                $p = (int) ($_POST['progressivo'] ?? 0);
                $sosta = $_SESSION[$chiaveImport] ?? null;

                if (!is_array($sosta) || !is_file((string) $sosta['percorso'])) {
                    throw new ScientificiEccezione(
                        'Il file caricato non è più disponibile: ricaricalo e riprova.');
                }

                $mappatura = [];
                foreach (['data', 'ora', 'valore', 'note'] as $campo) {
                    $colonna = (string) ($_POST['col_' . $campo] ?? '');
                    if ($colonna !== '') {
                        $mappatura[$campo] = (int) $colonna;
                    }
                }
                if (!isset($mappatura['data']) || !isset($mappatura['valore'])) {
                    throw new ScientificiEccezione(
                        'Indicare almeno la colonna della data e quella del valore.');
                }

                $letture = Scientifici::leggiCsvEsterno(
                    (string) $sosta['percorso'], $mappatura, (string) ($_POST['separatore'] ?? ';'));

                $esito = Scientifici::importaLetture($codice, $p, $letture);
                @unlink((string) $sosta['percorso']);
                unset($_SESSION[$chiaveImport]);
                IndiceIpogei::aggiorna($codice);

                $messaggio = $esito['importate'] . ' letture importate';
                if ($esito['scartate'] > 0) {
                    // Le righe scartate si dichiarano sempre: un'importazione
                    // che ne perde un terzo in silenzio produce una serie
                    // sbagliata che nessuno sospetta.
                    $messaggio .= ', ' . $esito['scartate'] . ' scartate ('
                        . implode('; ', $esito['motivi']) . ')';
                    Auth::messaggio('avviso', $messaggio . '.');
                } else {
                    Auth::messaggio('successo', $messaggio . '.');
                }

                header('Location: index.php?p=scientifici&codice=' . urlencode($codice)
                    . '&azione=serie&prog=' . $p);
                exit;

            default:
                Auth::messaggio('errore', 'Operazione non riconosciuta.');
        }
    } catch (ScientificiEccezione | UploadEccezione | IpogeoEccezione | XmlEccezione | CsvEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: ' . $ritorno);
    exit;
}

// ============================================================================
//  VISTA: mappatura dell'importazione
// ============================================================================

if ($azione === 'import' && $prog > 0 && $puoImportare) {
    $serie = Scientifici::trovaSerie($codice, $prog);
    $sosta = $_SESSION[$chiaveImport] ?? null;

    if ($serie === null || !is_array($sosta) || !is_file((string) $sosta['percorso'])) {
        Auth::messaggio('errore', 'Nessun file in attesa di importazione.');
        header('Location: ' . $ritorno);
        exit;
    }

    $anteprima = Scientifici::anteprimaCsv((string) $sosta['percorso'], 8);
    $titolo = 'Importazione — ' . $codice;

    /** Suggerimento della colonna, per nome: risparmia il caso frequente. */
    $indovina = static function (array $intestazione, array $parole): string {
        foreach ($intestazione as $i => $nome) {
            foreach ($parole as $parola) {
                if (stripos($nome, $parola) !== false) {
                    return (string) $i;
                }
            }
        }
        return '';
    };
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1>Importazione delle letture</h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-valore catageo-riferimento"><?= Testo::esc(Sezioni::riferimento('SC', $prog)) ?></span>
          <?= Testo::esc((string) $serie['titolo']) ?> ·
          file <span class="catageo-valore"><?= Testo::esc((string) $sosta['nome']) ?></span>
        </p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
        <i class="bi bi-x-lg"></i> Annulla
      </a>
    </div>

    <?php if ($anteprima['intestazione'] === []): ?>

      <div class="alert alert-danger">
        Il file non sembra un CSV leggibile: nessuna intestazione riconosciuta.
      </div>

    <?php else: ?>

      <div class="card mb-4">
        <div class="card-header"><h2 class="h6 mb-0">Anteprima del file</h2></div>
        <div class="table-responsive">
          <table class="table table-sm catageo-tabella mb-0">
            <thead>
              <tr>
                <?php foreach ($anteprima['intestazione'] as $i => $nome): ?>
                  <th>
                    <span class="catageo-nota">col. <?= $i ?></span><br>
                    <?= Testo::esc($nome) ?>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($anteprima['righe'] as $riga): ?>
                <tr>
                  <?php foreach ($anteprima['intestazione'] as $i => $nome): ?>
                    <td class="catageo-valore"><?= Testo::esc((string) ($riga[$i] ?? '')) ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body">
          <div class="catageo-nota">
            Separatore riconosciuto:
            <span class="catageo-valore"><?= Testo::esc($anteprima['separatore'] === "\t" ? 'tabulazione' : $anteprima['separatore']) ?></span>.
            Le date si accettano in formato ISO (2026-03-01) o italiano (01/03/2026);
            i valori con la virgola decimale sono ammessi.
          </div>
        </div>
      </div>

      <form method="post" action="index.php?p=scientifici&amp;codice=<?= urlencode($codice) ?>">
        <?= Auth::campoToken() ?>
        <input type="hidden" name="operazione" value="eseguiImport">
        <input type="hidden" name="progressivo" value="<?= $prog ?>">
        <input type="hidden" name="separatore" value="<?= Testo::esc($anteprima['separatore']) ?>">

        <div class="card mb-4">
          <div class="card-header"><h2 class="h6 mb-0">Quale colonna e cosa</h2></div>
          <div class="card-body">
            <div class="row g-3">
              <?php
              $campi = [
                  'data'   => ['Data <span class="text-danger">*</span>', ['data', 'date', 'time', 'stamp', 'giorno']],
                  'ora'    => ['Ora', ['ora', 'time', 'hour', 'stamp']],
                  'valore' => ['Valore <span class="text-danger">*</span>', ['valore', 'value', 'misura', 'reading', 'temp']],
                  'note'   => ['Note', ['nota', 'note', 'comment', 'flag']],
              ];
              foreach ($campi as $campo => [$etichetta, $parole]):
                  $suggerita = $indovina($anteprima['intestazione'], $parole);
              ?>
                <div class="col-md-3">
                  <label for="col_<?= $campo ?>" class="form-label"><?= $etichetta ?></label>
                  <select class="form-select" id="col_<?= $campo ?>" name="col_<?= $campo ?>">
                    <option value="">— nessuna —</option>
                    <?php foreach ($anteprima['intestazione'] as $i => $nome): ?>
                      <option value="<?= $i ?>" <?= $suggerita === (string) $i ? 'selected' : '' ?>>
                        col. <?= $i ?> — <?= Testo::esc($nome) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="catageo-nota mt-3">
              Se data e ora stanno nella stessa colonna, indicala per entrambe:
              l'ora viene estratta dal testo.
              Le altre colonne del file non vengono importate; strumento, unità
              e provenienza li mette la serie.
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="bi bi-download"></i> Importa nella serie
        </button>
      </form>

    <?php endif; ?>

    <?php
    return;
}

// ============================================================================
//  VISTA: una serie
// ============================================================================

if ($azione === 'serie' && $prog > 0) {
    $serie = Scientifici::trovaSerie($codice, $prog);
    if ($serie === null) {
        Auth::messaggio('errore', 'Serie non trovata.');
        header('Location: ' . $ritorno);
        exit;
    }

    if (!Visibilita::livelloVisibile((string) $serie['riservatezza'])) {
        Auth::messaggio('errore', 'Serie riservata: non consultabile con il livello di utenza in uso.');
        header('Location: ' . $ritorno);
        exit;
    }

    $dati  = Scientifici::letture($codice, $serie);
    $stat  = Scientifici::statistiche($dati['letture']);
    $svg   = Grafico::serieTemporale($dati['letture'], [
        'etichetta' => (string) $serie['titolo'],
        'unita'     => (string) $serie['unita'],
    ]);
    $punto = Scientifici::puntoMisura($codice, (string) $serie['puntoMisura']);

    $titolo = (string) $serie['titolo'] . ' — ' . $codice;

    /** Numero mostrato con i decimali dichiarati dalla grandezza. */
    $grandezza = Grandezze::trova((string) $serie['grandezza']);
    $decimali  = $grandezza === null ? 2 : (int) $grandezza['decimali'];
    $mostra = static fn (?float $v): string =>
        $v === null ? '—' : number_format($v, $decimali, ',', '.');
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1><?= Testo::esc((string) $serie['titolo']) ?></h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-valore catageo-riferimento"><?= Testo::esc(Sezioni::riferimento('SC', $prog)) ?></span>
          · <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
          <?php if ((string) $serie['riservatezza'] === 'riservata'): ?>
            <span class="badge text-bg-warning"><i class="bi bi-eye-slash"></i> riservata</span>
          <?php endif; ?>
        </p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary"
           href="index.php?p=serie-csv&amp;codice=<?= urlencode($codice) ?>&amp;prog=<?= $prog ?>">
          <i class="bi bi-filetype-csv"></i> Scarica il CSV
        </a>
        <?php if ($puoCompilare): ?>
          <a class="btn btn-outline-secondary"
             href="index.php?p=scientifici&amp;codice=<?= urlencode($codice) ?>&amp;azione=modificaSerie&amp;prog=<?= $prog ?>">
            <i class="bi bi-pencil"></i> Modifica
          </a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
          <i class="bi bi-arrow-left"></i> Serie
        </a>
      </div>
    </div>

    <?php if ($dati['troncato']): ?>
      <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        La serie ha <?= (int) $dati['totale'] ?> letture: ne sono state caricate
        le prime <?= count($dati['letture']) ?>. Statistiche e grafico si
        riferiscono solo a queste. Il CSV completo resta scaricabile.
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-8">

        <?php if ($svg !== ''): ?>
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Andamento</h2></div>
            <div class="card-body">
              <?= $svg ?>
            </div>
          </div>
        <?php elseif ($stat['conteggio'] > 0): ?>
          <div class="card mb-4">
            <div class="card-body catageo-nota">
              Una sola lettura utilizzabile: per una spezzata ne servono almeno due.
            </div>
          </div>
        <?php endif; ?>

        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Letture</h2>
            <span class="catageo-nota"><?= (int) $dati['totale'] ?> righe</span>
          </div>
          <?php if ($dati['letture'] === []): ?>
            <div class="card-body">
              <p class="text-body-secondary mb-0">Nessuna lettura registrata.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive" style="max-height:26rem;overflow-y:auto">
              <table class="table table-sm catageo-tabella mb-0">
                <thead class="sticky-top">
                  <tr>
                    <th>Data</th><th>Ora</th><th class="text-end">Valore</th>
                    <th>Validità</th><th>Note</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($dati['letture'], -300) as $riga): ?>
                    <?php $val = (string) ($riga['validita'] ?? 'valido'); ?>
                    <tr<?= in_array($val, Scientifici::VALIDITA_UTILI, true) ? '' : ' class="opacity-75"' ?>>
                      <td class="catageo-valore"><?= Testo::esc((string) ($riga['data'] ?? '')) ?></td>
                      <td class="catageo-valore"><?= Testo::esc((string) ($riga['ora'] ?? '')) ?></td>
                      <td class="text-end catageo-valore">
                        <?= (string) ($riga['valore'] ?? '') !== ''
                            ? Testo::esc((string) $riga['valore'])
                            : '<span class="text-body-tertiary">non misurato</span>' ?>
                      </td>
                      <td>
                        <?php if ($val !== 'valido'): ?>
                          <span class="badge text-bg-<?= $val === 'sospetto' ? 'warning' : 'secondary' ?>">
                            <?= Testo::esc(Scientifici::VALIDITA[$val] ?? $val) ?>
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="catageo-nota"><?= Testo::esc((string) ($riga['note'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if (count($dati['letture']) > 300): ?>
              <div class="card-body catageo-nota">
                In tabella le ultime 300 letture delle <?= count($dati['letture']) ?> caricate.
                Il grafico e le statistiche le considerano tutte.
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <?php if ($puoCompilare): ?>
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Registra una lettura</h2></div>
            <div class="card-body">
              <form method="post" action="index.php?p=scientifici&amp;codice=<?= urlencode($codice) ?>"
                    class="row g-2 align-items-end">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="operazione" value="aggiungiLettura">
                <input type="hidden" name="progressivo" value="<?= $prog ?>">

                <div class="col-6 col-md-2">
                  <label for="data" class="form-label">Data <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="data" name="data" required
                         value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-2">
                  <label for="ora" class="form-label">Ora</label>
                  <input type="time" class="form-control" id="ora" name="ora">
                </div>
                <div class="col-6 col-md-2">
                  <label for="valore" class="form-label">
                    Valore<?= (string) $serie['unita'] !== '' ? ' (' . Testo::esc((string) $serie['unita']) . ')' : '' ?>
                  </label>
                  <input type="text" class="form-control catageo-valore" id="valore" name="valore"
                         inputmode="decimal">
                  <div class="catageo-nota">Vuoto = non misurato.</div>
                </div>
                <div class="col-6 col-md-2">
                  <label for="validita" class="form-label">Validità</label>
                  <select class="form-select" id="validita" name="validita">
                    <?php foreach (Scientifici::VALIDITA as $valore => $etichetta): ?>
                      <option value="<?= $valore ?>"><?= Testo::esc($etichetta) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="note" class="form-label">Note</label>
                  <input type="text" class="form-control" id="note" name="note">
                </div>
                <div class="col-md-1">
                  <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-plus-lg"></i>
                  </button>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($puoImportare): ?>
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Importa da datalogger</h2></div>
            <div class="card-body">
              <form method="post" enctype="multipart/form-data"
                    action="index.php?p=scientifici&amp;codice=<?= urlencode($codice) ?>"
                    class="row g-2 align-items-end">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="operazione" value="anteprimaImport">
                <input type="hidden" name="progressivo" value="<?= $prog ?>">
                <div class="col-md-8">
                  <label for="file" class="form-label">File CSV dello strumento</label>
                  <input type="file" class="form-control" id="file" name="file" accept=".csv,.txt" required>
                </div>
                <div class="col-md-4">
                  <button type="submit" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-upload"></i> Carica e mappa le colonne
                  </button>
                </div>
              </form>
              <div class="catageo-nota mt-2">
                Le letture si accodano a quelle già presenti: l'importazione non
                sostituisce la serie.
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header"><h2 class="h6 mb-0">Riepilogo</h2></div>
          <div class="card-body">
            <dl class="row catageo-dl mb-0">
              <dt class="col-6 fw-normal text-body-secondary">Letture utili</dt>
              <dd class="col-6"><?= (int) $stat['conteggio'] ?></dd>

              <dt class="col-6 fw-normal text-body-secondary">Minimo</dt>
              <dd class="col-6"><?= Testo::esc($mostra($stat['min'])) ?></dd>

              <dt class="col-6 fw-normal text-body-secondary">Massimo</dt>
              <dd class="col-6"><?= Testo::esc($mostra($stat['max'])) ?></dd>

              <dt class="col-6 fw-normal text-body-secondary">Media</dt>
              <dd class="col-6"><?= Testo::esc($mostra($stat['media'])) ?></dd>

              <dt class="col-6 fw-normal text-body-secondary">Mediana</dt>
              <dd class="col-6"><?= Testo::esc($mostra($stat['mediana'])) ?></dd>

              <dt class="col-6 fw-normal text-body-secondary">Periodo</dt>
              <dd class="col-6">
                <?= Testo::esc((string) $stat['dal']) ?>
                <?= (string) $stat['al'] !== (string) $stat['dal'] ? '→ ' . Testo::esc((string) $stat['al']) : '' ?>
              </dd>
            </dl>

            <?php if ($stat['esclusePerValidita'] > 0 || $stat['senzaValore'] > 0): ?>
              <hr>
              <div class="catageo-nota">
                <?php if ($stat['esclusePerValidita'] > 0): ?>
                  <?= (int) $stat['esclusePerValidita'] ?> letture escluse perché
                  marcate anomale o scartate.<br>
                <?php endif; ?>
                <?php if ($stat['senzaValore'] > 0): ?>
                  <?= (int) $stat['senzaValore'] ?> letture senza valore: lo
                  strumento c'era e non ha misurato.
                <?php endif; ?>
                Restano nel file, non entrano nel calcolo.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header"><h2 class="h6 mb-0">La serie</h2></div>
          <div class="card-body">
            <dl class="row catageo-dl mb-0">
              <dt class="col-5 fw-normal text-body-secondary">Grandezza</dt>
              <dd class="col-7"><?= Testo::esc(Grandezze::etichetta((string) $serie['grandezza'])) ?></dd>

              <dt class="col-5 fw-normal text-body-secondary">Punto</dt>
              <dd class="col-7">
                <?= $punto !== null
                    ? Testo::esc((string) $punto['id'] . ' — ' . (string) $punto['nome'])
                    : '<span class="text-body-tertiary">—</span>' ?>
              </dd>

              <dt class="col-5 fw-normal text-body-secondary">Acquisizione</dt>
              <dd class="col-7">
                <?= Testo::esc(Scientifici::ACQUISIZIONI[(string) $serie['tipoAcquisizione']] ?? '') ?>
                <?= (string) $serie['passoTemporale'] !== ''
                    ? '<span class="catageo-nota">' . Testo::esc((string) $serie['passoTemporale']) . '</span>' : '' ?>
              </dd>

              <dt class="col-5 fw-normal text-body-secondary">Strumento</dt>
              <dd class="col-7">
                <?= Testo::esc((string) $serie['strumentoModello']) ?>
                <?php if ((string) $serie['strumentoMatricola'] !== ''): ?>
                  <span class="catageo-nota"><?= Testo::esc((string) $serie['strumentoMatricola']) ?></span>
                <?php endif; ?>
                <?php if ((string) $serie['strumentoTaratura'] !== ''): ?>
                  <div class="catageo-nota">
                    taratura <?= Testo::esc((string) $serie['strumentoTaratura']) ?>
                    <?= (string) $serie['strumentoIncertezza'] !== ''
                        ? ' · ' . Testo::esc((string) $serie['strumentoIncertezza']) : '' ?>
                  </div>
                <?php endif; ?>
              </dd>

              <dt class="col-5 fw-normal text-body-secondary">Responsabile</dt>
              <dd class="col-7">
                <?= (string) $serie['responsabile'] !== ''
                    ? Testo::esc(Esploratori::etichettaPerId((string) $serie['responsabile']))
                    : '<span class="text-body-tertiary">—</span>' ?>
              </dd>

              <dt class="col-5 fw-normal text-body-secondary">Provenienza</dt>
              <dd class="col-7">
                <?= Testo::esc(Scientifici::PROVENIENZE[(string) $serie['provenienzaTipo']] ?? '') ?>
                <?php if ((string) $serie['provenienza'] !== ''): ?>
                  <div class="catageo-nota"><?= Testo::esc((string) $serie['provenienza']) ?></div>
                <?php endif; ?>
              </dd>

              <dt class="col-5 fw-normal text-body-secondary">File</dt>
              <dd class="col-7"><span class="catageo-valore"><?= Testo::esc((string) $serie['file']) ?></span></dd>
            </dl>

            <?php if ((string) $serie['note'] !== ''): ?>
              <hr>
              <?= nl2br(Testo::esc((string) $serie['note'])) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php
    return;
}

// ============================================================================
//  MODULO: serie
// ============================================================================

if (($azione === 'nuovaSerie' || ($azione === 'modificaSerie' && $prog > 0)) && $puoCompilare) {
    $modifica = $azione === 'modificaSerie';
    $serie = $modifica ? Scientifici::trovaSerie($codice, $prog) : null;

    if ($modifica && $serie === null) {
        Auth::messaggio('errore', 'Serie non trovata.');
        header('Location: ' . $ritorno);
        exit;
    }
    if ($serie === null) {
        $serie = Scientifici::CAMPI_SERIE;
    }

    $titolo = ($modifica ? 'Modifica serie' : 'Nuova serie') . ' — ' . $codice;
    $v = static fn (string $c): string => Testo::esc((string) ($serie[$c] ?? ''));
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1><?= $modifica ? 'Modifica serie' : 'Nuova serie di misure' ?></h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
        </p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
        <i class="bi bi-x-lg"></i> Annulla
      </a>
    </div>

    <form method="post" action="index.php?p=scientifici&amp;codice=<?= urlencode($codice) ?>"
          class="needs-validation" novalidate>
      <?= Auth::campoToken() ?>
      <input type="hidden" name="operazione" value="<?= $modifica ? 'aggiornaSerie' : 'creaSerie' ?>">
      <?php if ($modifica): ?>
        <input type="hidden" name="progressivo" value="<?= $prog ?>">
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Cosa si misura</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-7">
                  <label for="titolo" class="form-label">Titolo <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="titolo" name="titolo" required
                         value="<?= $v('titolo') ?>" placeholder="Radon sala grande">
                  <?php if ($modifica): ?>
                    <div class="catageo-nota">
                      Il nome del file non cambia: contiene dati e potrebbe essere
                      già stato scaricato o citato.
                    </div>
                  <?php else: ?>
                    <div class="catageo-nota">Compare nel nome del file CSV.</div>
                  <?php endif; ?>
                </div>

                <div class="col-md-5">
                  <label for="grandezza" class="form-label">Grandezza <span class="text-danger">*</span></label>
                  <select class="form-select" id="grandezza" name="grandezza" required>
                    <option value="">— scegli —</option>
                    <?php foreach (Grandezze::categorie(true) as $categoria): ?>
                      <optgroup label="<?= Testo::esc((string) $categoria['nome']) ?>">
                        <?php foreach ($categoria['grandezze'] as $g): ?>
                          <option value="<?= Testo::esc((string) $g['codice']) ?>"
                            <?= (string) $serie['grandezza'] === (string) $g['codice'] ? 'selected' : '' ?>>
                            <?= Testo::esc((string) $g['nome']) ?>
                            <?= (string) $g['unita'] !== '' ? '(' . Testo::esc((string) $g['unita']) . ')' : '' ?>
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                  <div class="catageo-nota">L'unità viene dalla grandezza scelta.</div>
                </div>

                <div class="col-md-5">
                  <label for="puntoMisura" class="form-label">Punto di misura</label>
                  <select class="form-select" id="puntoMisura" name="puntoMisura">
                    <option value="">— non specificato —</option>
                    <?php foreach (Scientifici::puntiMisura($codice) as $punto): ?>
                      <option value="<?= Testo::esc((string) $punto['id']) ?>"
                        <?= (string) $serie['puntoMisura'] === (string) $punto['id'] ? 'selected' : '' ?>>
                        <?= Testo::esc((string) $punto['id'] . ' — ' . (string) $punto['nome']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-4">
                  <label for="tipoAcquisizione" class="form-label">Acquisizione</label>
                  <select class="form-select" id="tipoAcquisizione" name="tipoAcquisizione">
                    <?php foreach (Scientifici::ACQUISIZIONI as $valore => $etichetta): ?>
                      <option value="<?= $valore ?>"
                        <?= (string) $serie['tipoAcquisizione'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-3">
                  <label for="passoTemporale" class="form-label">Passo</label>
                  <input type="text" class="form-control catageo-valore" id="passoTemporale"
                         name="passoTemporale" value="<?= $v('passoTemporale') ?>" placeholder="PT1H">
                  <div class="catageo-nota">ISO 8601; vuoto se irregolare.</div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Strumento</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="strumentoModello" class="form-label">Modello</label>
                  <input type="text" class="form-control" id="strumentoModello" name="strumentoModello"
                         value="<?= $v('strumentoModello') ?>">
                </div>
                <div class="col-md-6">
                  <label for="strumentoMatricola" class="form-label">Matricola</label>
                  <input type="text" class="form-control catageo-valore" id="strumentoMatricola"
                         name="strumentoMatricola" value="<?= $v('strumentoMatricola') ?>">
                </div>
                <div class="col-md-6">
                  <label for="strumentoTaratura" class="form-label">Ultima taratura</label>
                  <input type="date" class="form-control" id="strumentoTaratura" name="strumentoTaratura"
                         value="<?= $v('strumentoTaratura') ?>">
                </div>
                <div class="col-md-6">
                  <label for="strumentoIncertezza" class="form-label">Incertezza dichiarata</label>
                  <input type="text" class="form-control" id="strumentoIncertezza" name="strumentoIncertezza"
                         value="<?= $v('strumentoIncertezza') ?>" placeholder="&plusmn;10%">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Chi e da dove</h2></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="responsabile" class="form-label">Responsabile</label>
                <select class="form-select" id="responsabile" name="responsabile">
                  <option value="">— non indicato —</option>
                  <?php foreach (Esploratori::elenco(true) as $e): ?>
                    <option value="<?= Testo::esc((string) $e['id']) ?>"
                      <?= (string) $serie['responsabile'] === (string) $e['id'] ? 'selected' : '' ?>>
                      <?= Testo::esc(Esploratori::etichetta($e)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label for="gruppo" class="form-label">Gruppo</label>
                <select class="form-select" id="gruppo" name="gruppo">
                  <option value="">— non indicato —</option>
                  <?php foreach (Gruppi::elenco(true) as $g): ?>
                    <option value="<?= Testo::esc((string) $g['id']) ?>"
                      <?= (string) $serie['gruppo'] === (string) $g['id'] ? 'selected' : '' ?>>
                      <?= Testo::esc(Gruppi::etichetta($g)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label for="provenienzaTipo" class="form-label">Provenienza del dato</label>
                <select class="form-select" id="provenienzaTipo" name="provenienzaTipo">
                  <?php foreach (Scientifici::PROVENIENZE as $valore => $etichetta): ?>
                    <option value="<?= $valore ?>"
                      <?= (string) $serie['provenienzaTipo'] === $valore ? 'selected' : '' ?>>
                      <?= Testo::esc($etichetta) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label for="provenienza" class="form-label">Chi ha prodotto il dato</label>
                <input type="text" class="form-control" id="provenienza" name="provenienza"
                       value="<?= $v('provenienza') ?>" placeholder="Nome dell'ente o del gruppo">
              </div>

              <div>
                <label for="riservatezza" class="form-label">Riservatezza</label>
                <select class="form-select" id="riservatezza" name="riservatezza">
                  <option value="pubblica" <?= (string) $serie['riservatezza'] === 'pubblica' ? 'selected' : '' ?>>
                    Pubblica
                  </option>
                  <option value="riservata" <?= (string) $serie['riservatezza'] === 'riservata' ? 'selected' : '' ?>>
                    Riservata
                  </option>
                </select>
                <div class="catageo-nota">
                  Indipendente da quella dell'ipogeo: una cavità pubblica può
                  ospitare un monitoraggio che non va divulgato.
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Note</h2></div>
            <div class="card-body">
              <textarea class="form-control" name="note" rows="4"><?= $v('note') ?></textarea>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i> <?= $modifica ? 'Salva la serie' : 'Crea la serie' ?>
          </button>
        </div>
      </div>
    </form>

    <?php
    return;
}

// ============================================================================
//  ELENCO
// ============================================================================

$serie  = Scientifici::serieVisibili($codice);
$punti  = Scientifici::puntiMisura($codice);
$titolo = 'Dati scientifici — ' . $codice;
?>

<div class="catageo-intestazione">
  <div>
    <h1>Dati scientifici</h1>
    <p class="text-body-secondary mb-0">
      <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
      · <?= count($serie) ?> seri<?= count($serie) === 1 ? 'e' : 'e' ?>,
      <?= count($punti) ?> punt<?= count($punti) === 1 ? 'o' : 'i' ?> di misura
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($puoCompilare): ?>
      <a class="btn btn-primary"
         href="index.php?p=scientifici&amp;codice=<?= urlencode($codice) ?>&amp;azione=nuovaSerie">
        <i class="bi bi-plus-lg"></i> Nuova serie
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary"
       href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode($codice) ?>">
      <i class="bi bi-arrow-left"></i> Scheda
    </a>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0">Serie di misure</h2></div>
      <?php if ($serie === []): ?>
        <div class="card-body d-flex gap-3">
          <i class="bi bi-graph-up fs-3 text-body-secondary" aria-hidden="true"></i>
          <div>
            <h3 class="h6 mb-1">Nessuna serie</h3>
            <p class="text-body-secondary mb-0">
              Una serie e un CSV di letture con il suo descrittore: si accoda nel
              tempo e si apre in un foglio di calcolo. Conviene definire prima i
              punti di misura, così due misure prese a distanza di anni restano
              confrontabili.
            </p>
          </div>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table catageo-tabella mb-0 align-middle">
            <thead>
              <tr>
                <th style="width:5rem">Rif.</th>
                <th>Serie</th>
                <th>Grandezza</th>
                <th>Punto</th>
                <th class="text-end">Letture</th>
                <?php if ($puoCompilare): ?><th class="text-end">Azioni</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($serie as $s): ?>
                <?php $p = (int) $s['progressivo']; ?>
                <tr>
                  <td><span class="catageo-valore catageo-riferimento"><?= Testo::esc(Sezioni::riferimento('SC', $p)) ?></span></td>
                  <td>
                    <a href="index.php?p=scientifici&amp;codice=<?= urlencode($codice) ?>&amp;azione=serie&amp;prog=<?= $p ?>">
                      <?= Testo::esc((string) $s['titolo']) ?>
                    </a>
                    <?php if ((string) $s['riservatezza'] === 'riservata'): ?>
                      <i class="bi bi-eye-slash text-warning" title="Serie riservata"></i>
                    <?php endif; ?>
                    <?php if ((string) $s['periodoDal'] !== ''): ?>
                      <div class="catageo-nota">
                        <?= Testo::esc((string) $s['periodoDal']) ?>
                        <?= (string) $s['periodoAl'] !== (string) $s['periodoDal']
                            ? '→ ' . Testo::esc((string) $s['periodoAl']) : '' ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td><?= Testo::esc(Grandezze::etichetta((string) $s['grandezza'])) ?></td>
                  <td class="catageo-valore"><?= Testo::esc((string) $s['puntoMisura']) ?></td>
                  <td class="text-end catageo-valore"><?= (int) ($s['numeroLetture'] ?: 0) ?></td>
                  <?php if ($puoCompilare): ?>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <a class="btn btn-sm btn-outline-secondary"
                           href="index.php?p=scientifici&amp;codice=<?= urlencode($codice) ?>&amp;azione=modificaSerie&amp;prog=<?= $p ?>">
                          <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" action="<?= Testo::esc($ritorno) ?>">
                          <?= Auth::campoToken() ?>
                          <input type="hidden" name="operazione" value="eliminaSerie">
                          <input type="hidden" name="progressivo" value="<?= $p ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit"
                                  data-catageo-conferma="Rimuovere la serie? Il CSV viene spostato in _rimossi e resta recuperabile.">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0">Punti di misura</h2></div>
      <div class="card-body">
        <?php if ($punti === []): ?>
          <p class="text-body-secondary">
            Nessun punto definito. Un punto e un luogo stabile nel tempo: serve a
            rendere confrontabili misure prese ad anni di distanza.
          </p>
        <?php else: ?>
          <ul class="list-unstyled">
            <?php foreach ($punti as $punto): ?>
              <li class="mb-2">
                <div class="d-flex justify-content-between gap-2">
                  <div>
                    <span class="catageo-valore catageo-riferimento"><?= Testo::esc((string) $punto['id']) ?></span>
                    <?= Testo::esc((string) $punto['nome']) ?>
                    <?php if ((string) $punto['descrizione'] !== ''): ?>
                      <div class="catageo-nota"><?= Testo::esc((string) $punto['descrizione']) ?></div>
                    <?php endif; ?>
                    <?php if ((string) $punto['progressiva'] !== ''): ?>
                      <div class="catageo-nota">
                        progressiva interna <?= Testo::esc((string) $punto['progressiva']) ?> m
                      </div>
                    <?php endif; ?>
                  </div>
                  <?php if ($puoCompilare): ?>
                    <form method="post" action="<?= Testo::esc($ritorno) ?>">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="eliminaPunto">
                      <input type="hidden" name="id" value="<?= Testo::esc((string) $punto['id']) ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit"
                              data-catageo-conferma="Togliere il punto <?= Testo::esc((string) $punto['id']) ?>?">
                        <i class="bi bi-x"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if ($puoCompilare): ?>
          <hr>
          <form method="post" action="<?= Testo::esc($ritorno) ?>" class="row g-2">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="salvaPunto">
            <input type="hidden" name="id" value="">

            <div class="col-12">
              <label for="nomePunto" class="form-label">Nuovo punto</label>
              <input type="text" class="form-control form-control-sm" id="nomePunto" name="nome"
                     placeholder="Sala grande" required>
            </div>
            <div class="col-12">
              <input type="text" class="form-control form-control-sm" name="descrizione"
                     placeholder="Dove esattamente">
            </div>
            <div class="col-6">
              <input type="text" class="form-control form-control-sm catageo-valore" name="latitudine"
                     placeholder="latitudine">
            </div>
            <div class="col-6">
              <input type="text" class="form-control form-control-sm catageo-valore" name="longitudine"
                     placeholder="longitudine">
            </div>
            <div class="col-6">
              <input type="text" class="form-control form-control-sm" name="quota" placeholder="quota m">
            </div>
            <div class="col-6">
              <input type="text" class="form-control form-control-sm" name="progressiva"
                     placeholder="progressiva m">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-plus-lg"></i> Aggiungi il punto
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
