<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/risorse.php
 *  Descrizione ..: Gestione dei file di una sezione dell'ipogeo: caricamento,
 *                  metadati, copertina, rimozione.
 *
 *                  Sta in una pagina propria e non dentro la scheda perche la
 *                  scheda serve a leggere e questa a lavorare: mettere insieme
 *                  le due cose avrebbe aggiunto altre cinquecento righe a un
 *                  file che ne ha gia millesettecento, e chi consulta non ha
 *                  bisogno di vedere i moduli di caricamento.
 *  Versione .....: 0.8.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.8.0  2026-08-05  D.Candela  Sezione dei rilievi con i suoi campi.
 *  0.7.1  2026-08-05  D.Candela  Finestra dei media, dati sotto le miniature,
 *                                coordinate correggibili a mano.
 *  0.7.0  2026-08-05  D.Candela  Prima stesura (fase 5).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');

$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';
$sigla  = isset($_GET['sez']) ? strtoupper(trim((string) $_GET['sez'])) : '';

if ($codice === '' || !Sezioni::valida($sigla)) {
    Auth::messaggio('errore', 'Sezione o ipogeo non indicati.');
    header('Location: index.php?p=ipogei');
    exit;
}

$risoluzione = Ipogeo::risolvi($codice);
if ($risoluzione === null) {
    Auth::messaggio('errore', 'Nessun ipogeo con codice "' . $codice . '".');
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

$nomeIpogeo = (string) $scheda['identificazione']['nome'];
$etichetta  = Sezioni::etichetta($sigla);
$anteprima  = Sezioni::anteprima($sigla);
$puoGestire = Auth::puo('carica_risorse');
$ritorno    = 'index.php?p=risorse&codice=' . urlencode($codice) . '&sez=' . $sigla;

/**
 * Coordinata scritta a mano, ricondotta a gradi decimali o alla stringa vuota.
 *
 * Si accetta anche la virgola come separatore, perche su tastiera italiana e
 * quello che esce naturalmente. Un valore fuori intervallo si scarta invece di
 * salvarlo: una latitudine di 412 gradi non e una posizione.
 */
function catageoGradi(string $valore, float $massimo): string
{
    $valore = trim(str_replace(',', '.', $valore));
    if ($valore === '' || !is_numeric($valore)) {
        return '';
    }
    $numero = (float) $valore;

    return abs($numero) <= $massimo ? number_format($numero, 6, '.', '') : '';
}

// ============================================================================
//  OPERAZIONI
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    Auth::esigi('carica_risorse');

    $operazione = (string) ($_POST['operazione'] ?? '');

    /** Metadati comuni, presi dal modulo. */
    $metadati = static function (): array {
        return [
            'titolo'            => (string) ($_POST['titolo'] ?? ''),
            'descrizione'       => (string) ($_POST['descrizione'] ?? ''),
            'data'              => (string) ($_POST['data'] ?? ''),
            'autoreId'          => (string) ($_POST['autoreId'] ?? ''),
            'gruppoId'          => (string) ($_POST['gruppoId'] ?? ''),
            'licenza'           => (string) ($_POST['licenza'] ?? ''),
            'riservatezza'      => (string) ($_POST['riservatezza'] ?? 'pubblica'),
            'categoriaAllegato' => (string) ($_POST['categoriaAllegato'] ?? ''),
            'urlEsterno'        => (string) ($_POST['urlEsterno'] ?? ''),
            'latitudine'        => catageoGradi((string) ($_POST['latitudine'] ?? ''), 90.0),
            'longitudine'       => catageoGradi((string) ($_POST['longitudine'] ?? ''), 180.0),

            'tipoRilievo'        => (string) ($_POST['tipoRilievo'] ?? ''),
            'scala'              => (string) ($_POST['scala'] ?? ''),
            'sistemaRiferimento' => (string) ($_POST['sistemaRiferimento'] ?? ''),
            'dataRilievo'        => (string) ($_POST['dataRilievo'] ?? ''),
            'strumentazione'     => (string) ($_POST['strumentazione'] ?? ''),
            'rilevatori'         => (string) ($_POST['rilevatori'] ?? ''),
            // Una casella non spuntata non arriva affatto nel POST: senza il
            // campo sentinella ogni caricamento spegnerebbe il tracciato, perche
            // il modulo di caricamento la casella non ce l'ha.
            'mostraInMappa'      => isset($_POST['conMostraInMappa'])
                ? (empty($_POST['mostraInMappa']) ? '0' : '1')
                : '1',
        ];
    };

    try {
        switch ($operazione) {

            case 'carica':
                if (!Sezioni::caricabile($sigla)) {
                    throw new RisorsaEccezione(
                        'La sezione ' . $etichetta . ' non accetta ancora caricamenti dall\'interfaccia.'
                    );
                }

                $file = Upload::elenco($_FILES['file'] ?? []);
                if ($file === []) {
                    throw new RisorsaEccezione('Nessun file selezionato.');
                }

                $comuni  = $metadati();
                $caricati = 0;
                $errori   = [];

                foreach ($file as $indice => $singolo) {
                    try {
                        $verificato = Upload::verifica($singolo, Sezioni::chiaveEstensioni($sigla));

                        // Con piu file insieme il titolo scritto nel modulo
                        // sarebbe lo stesso per tutti, e sarebbe sbagliato: si
                        // applica solo quando il file e uno.
                        $propri = $comuni;
                        if (count($file) > 1) {
                            $propri['titolo'] = '';
                        }

                        $risorsa = Risorse::aggiungi($codice, $sigla, $verificato, $propri);
                        $caricati++;

                        if (Sezioni::anteprima($sigla) === 'immagine') {
                            Miniature::perRisorsa($codice, $sigla, $risorsa);
                        }
                    } catch (UploadEccezione | RisorsaEccezione $e) {
                        // Un file rifiutato non deve annullare gli altri: chi
                        // carica venti foto e una sbagliata vuole le diciannove
                        // buone, non ricominciare da capo.
                        $errori[] = $e->getMessage();
                    }
                }

                if ($caricati > 0) {
                    IndiceIpogei::aggiorna($codice);
                    Auth::messaggio('successo', $caricati === 1
                        ? 'File caricato.'
                        : $caricati . ' file caricati.');
                }
                foreach ($errori as $errore) {
                    Auth::messaggio('errore', $errore);
                }
                if ($caricati === 0 && $errori === []) {
                    Auth::messaggio('avviso', 'Nessun file caricato.');
                }
                break;

            case 'aggiorna':
                $prog = (int) ($_POST['progressivo'] ?? 0);
                Risorse::aggiorna($codice, $sigla, $prog, $metadati());
                Auth::messaggio('successo', 'Scheda della risorsa aggiornata.');
                break;

            case 'copertina':
                $prog = (int) ($_POST['progressivo'] ?? 0);
                Risorse::impostaCopertina($codice, $prog);
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', $prog > 0
                    ? 'Foto di copertina scelta.'
                    : 'Copertina rimossa.');
                break;

            case 'rigenera':
                $rifatte = 0;
                foreach (Risorse::elenco($codice, $sigla) as $risorsa) {
                    if (Miniature::perRisorsa($codice, $sigla, $risorsa)) {
                        $rifatte++;
                    }
                }
                Auth::messaggio(
                    $rifatte > 0 ? 'successo' : 'avviso',
                    $rifatte > 0
                        ? $rifatte . ' miniature rigenerate.'
                        : 'Nessuna miniatura rigenerata: vedere il log per il motivo.'
                );
                break;

            case 'elimina':
                $prog = (int) ($_POST['progressivo'] ?? 0);
                Risorse::elimina($codice, $sigla, $prog);
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo',
                    'Risorsa rimossa dalla sezione. Il file è stato spostato in "'
                    . $codice . ' - ' . Risorse::CARTELLA_RIMOSSI . '" e resta recuperabile.');
                break;

            default:
                Auth::messaggio('errore', 'Operazione non riconosciuta.');
        }
    } catch (UploadEccezione | RisorsaEccezione | IpogeoEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: ' . $ritorno);
    exit;
}

// ============================================================================
//  VISTA
// ============================================================================

require_once CATAGEO_ROOT . '/app/view/parti-media.php';

$risorse   = Risorse::elenco($codice, $sigla);

/*
 * La finestra dei media serve solo dove c'e qualcosa da guardare, e a
 * deciderlo non basta la sezione: fra gli allegati un PDF si legge nella
 * finestra e un DOCX accanto a lui si puo solo scaricare. Si guarda quindi
 * cosa c'e davvero dentro la sezione.
 *
 * Sta DOPO il require: catageoFinestraPer() la definisce parti-media.php, e
 * chiamarla prima faceva morire la pagina con «funzione non definita».
 */
$jsPagina = [];
foreach ($risorse as $unaRisorsa) {
    if (catageoFinestraPer($unaRisorsa) !== '') {
        $jsPagina = ['assets/js/catageo-media.js'];
        break;
    }
}
$titolo    = $etichetta . ' — ' . $codice;
$ammesse   = Sezioni::chiaveEstensioni($sigla) !== ''
    ? Config::estensioniAmmesse(Sezioni::chiaveEstensioni($sigla))
    : [];
$maxUpload = Config::dimensioneMaxUpload();


/** Indirizzo di consegna di una risorsa. */
$url = static fn (int $prog, bool $mini = false, bool $inline = false): string
    => catageoUrlRisorsa($codice, $sigla, $prog, $mini, $inline);
?>

<div class="catageo-intestazione">
  <div>
    <h1><?= Testo::esc($etichetta) ?></h1>
    <p class="text-body-secondary mb-0">
      <span class="catageo-codice"><?= Testo::esc($codice) ?></span>
      <?= Testo::esc($nomeIpogeo) ?> ·
      <?= count($risorse) ?> element<?= count($risorse) === 1 ? 'o' : 'i' ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary"
       href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode($codice) ?>">
      <i class="bi bi-arrow-left"></i> Torna alla scheda
    </a>
  </div>
</div>

<?php if (!Sezioni::caricabile($sigla)): ?>
  <div class="alert alert-info d-flex align-items-start gap-2">
    <i class="bi bi-info-circle-fill mt-1" aria-hidden="true"></i>
    <div>
      La gestione dei contenuti di <strong><?= Testo::esc($etichetta) ?></strong> arriva
      in una fase successiva del piano. I file già presenti nella cartella
      <span class="catageo-valore"><?= Testo::esc(Sezioni::nomeCartella($codice, $sigla)) ?></span>
      vengono comunque elencati qui sotto se sono registrati nell'indice di sezione.
    </div>
  </div>
<?php endif; ?>

<?php if ($puoGestire && Sezioni::caricabile($sigla)): ?>
  <div class="card mb-4">
    <div class="card-header"><h2 class="h6 mb-0">Carica</h2></div>
    <div class="card-body">
      <form method="post" action="index.php?p=risorse&amp;codice=<?= urlencode($codice) ?>&amp;sez=<?= $sigla ?>"
            enctype="multipart/form-data" class="row g-3">
        <?= Auth::campoToken() ?>
        <input type="hidden" name="operazione" value="carica">

        <div class="col-lg-6">
          <label for="file" class="form-label">File <span class="text-danger">*</span></label>
          <input type="file" class="form-control" id="file" name="file[]" multiple required
                 <?= $ammesse !== [] ? 'accept=".' . Testo::esc(implode(',.', $ammesse)) . '"' : '' ?>>
          <div class="catageo-nota">
            <?php if ($ammesse !== []): ?>
              Ammessi: <?= Testo::esc(implode(', ', $ammesse)) ?>.
            <?php endif; ?>
            Dimensione massima per file: <?= Testo::esc(Testo::dimensione($maxUpload)) ?>.
            Si possono selezionare più file insieme.
          </div>
        </div>

        <div class="col-lg-6">
          <label for="titolo" class="form-label">Titolo</label>
          <input type="text" class="form-control" id="titolo" name="titolo" maxlength="200">
          <div class="catageo-nota">
            Se vuoto viene usato il nome del file. Caricando più file insieme
            viene ignorato: ognuno prende il proprio nome.
          </div>
        </div>

        <div class="col-md-4">
          <label for="data" class="form-label">Data</label>
          <input type="date" class="form-control" id="data" name="data">
        </div>

        <div class="col-md-4">
          <label for="autoreId" class="form-label">Autore</label>
          <select class="form-select" id="autoreId" name="autoreId">
            <option value="">—</option>
            <?php foreach (Esploratori::elenco(true) as $e): ?>
              <option value="<?= Testo::esc((string) $e['id']) ?>">
                <?= Testo::esc(Esploratori::etichetta($e)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label for="riservatezza" class="form-label">Riservatezza</label>
          <select class="form-select" id="riservatezza" name="riservatezza">
            <option value="pubblica">pubblica</option>
            <option value="riservata">riservata</option>
          </select>
          <div class="catageo-nota">Una risorsa riservata non viene consegnata agli utenti di sola consultazione.</div>
        </div>

        <?php if ($sigla === 'AL'): ?>
          <div class="col-md-4">
            <label for="categoriaAllegato" class="form-label">Categoria</label>
            <select class="form-select" id="categoriaAllegato" name="categoriaAllegato">
              <option value="">—</option>
              <?php foreach (['relazione', 'autorizzazione', 'articolo', 'cartografia', 'corrispondenza', 'altro'] as $c): ?>
                <option value="<?= $c ?>"><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-upload"></i> Carica
          </button>
          <?php if ($anteprima === 'immagine' && Miniature::disponibile()): ?>
            <button type="submit" class="btn btn-outline-secondary"
                    name="operazione" value="rigenera"
                    formnovalidate>
              <i class="bi bi-arrow-repeat"></i> Rigenera le miniature
            </button>
          <?php endif; ?>
        </div>
      </form>

      <?php if ($anteprima === 'immagine' && !Miniature::disponibile()): ?>
        <div class="alert alert-warning mt-3 mb-0 py-2">
          <i class="bi bi-exclamation-triangle-fill"></i>
          L'estensione <span class="catageo-valore">gd</span> non è disponibile: le miniature
          non vengono generate e la galleria mostra le immagini originali rimpicciolite dal
          browser. Le foto restano archiviate correttamente.
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($risorse === []): ?>

  <div class="card">
    <div class="card-body d-flex gap-3">
      <i class="bi bi-folder2-open fs-3 text-body-secondary" aria-hidden="true"></i>
      <div>
        <h2 class="h6 mb-1">Nessun contenuto</h2>
        <p class="text-body-secondary mb-0">
          La cartella <span class="catageo-valore"><?= Testo::esc(Sezioni::nomeCartella($codice, $sigla)) ?></span>
          e pronta nell'archivio ma non contiene ancora nulla di registrato.
        </p>
      </div>
    </div>
  </div>

<?php elseif ($anteprima === 'immagine'): ?>

  <?php // Galleria: per le foto conta vedere, non leggere una tabella. ?>
  <div class="row g-3">
    <?php foreach ($risorse as $foto): ?>
      <?php $prog = (int) $foto['progressivo']; ?>
      <div class="col-6 col-md-4 col-xl-3">
        <div class="card h-100">
          <?php
          /* href resta valido: senza JavaScript il file si apre comunque.
             Con inline=1 solo se il server lo consegna davvero in linea:
             la configurazione ammette il TIFF fra le foto, e per quello
             «inline» diventa uno scaricamento travestito da «guarda». */
          $inLinea = catageoFinestraPer($foto) !== '';
          ?>
          <a href="<?= Testo::esc($url($prog, false, $inLinea)) ?>"
             <?= catageoAttributiMedia($foto, $codice, $sigla) ?>
             title="<?= $inLinea ? 'Guarda l&#39;immagine' : 'Scarica il file: il browser non sa mostrare questo formato' ?>">
            <img src="<?= Testo::esc($url($prog, true, true)) ?>"
                 alt="<?= Testo::esc((string) $foto['titolo']) ?>"
                 class="card-img-top catageo-miniatura" loading="lazy">
          </a>
          <div class="card-body py-2">
            <div class="fw-semibold text-truncate" title="<?= Testo::esc((string) $foto['titolo']) ?>">
              <?= Testo::esc((string) $foto['titolo']) ?>
            </div>
            <?= catageoDatiMedia($foto, true, $sigla) ?>
            <?php if (!empty($foto['copertina'])): ?>
              <span class="badge text-bg-primary mt-1"><i class="bi bi-star-fill"></i> copertina</span>
            <?php endif; ?>
            <?php if ((string) $foto['riservatezza'] === 'riservata'): ?>
              <span class="badge text-bg-warning mt-1"><i class="bi bi-shield-lock-fill"></i> riservata</span>
            <?php endif; ?>
          </div>
          <?php if ($puoGestire): ?>
            <div class="card-footer d-flex flex-wrap gap-1 py-2">
              <?php if (empty($foto['copertina'])): ?>
                <form method="post" action="<?= Testo::esc($ritorno) ?>">
                  <?= Auth::campoToken() ?>
                  <input type="hidden" name="operazione" value="copertina">
                  <input type="hidden" name="progressivo" value="<?= $prog ?>">
                  <button class="btn btn-sm btn-outline-secondary" type="submit" title="Usa come copertina">
                    <i class="bi bi-star"></i>
                  </button>
                </form>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-secondary"
                 href="index.php?p=risorse&amp;codice=<?= urlencode($codice) ?>&amp;sez=<?= $sigla ?>&amp;modifica=<?= $prog ?>"
                 title="Modifica i dati">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="post" action="<?= Testo::esc($ritorno) ?>">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="operazione" value="elimina">
                <input type="hidden" name="progressivo" value="<?= $prog ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit" title="Rimuovi"
                        data-catageo-conferma="Rimuovere questa risorsa dalla sezione? Il file viene spostato in _rimossi e resta recuperabile.">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php else: ?>

  <?php // Allegati, video e resto: elenco, perche conta il nome e non l'aspetto. ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th style="width:5rem">Rif.</th>
            <th>Titolo</th>
            <th>File</th>
            <th class="text-end">Dimensione</th>
            <th>Data</th>
            <?php if ($puoGestire): ?><th class="text-end">Azioni</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($risorse as $risorsa): ?>
            <?php
            $prog = (int) $risorsa['progressivo'];
            $presente = Risorse::percorsoFile($codice, $sigla, $prog) !== null;
            ?>
            <tr>
              <td><span class="catageo-valore"><?= Testo::esc(Sezioni::riferimento($sigla, $prog)) ?></span></td>
              <td>
                <?= Testo::esc((string) $risorsa['titolo']) ?>
                <?php if ((string) $risorsa['riservatezza'] === 'riservata'): ?>
                  <span class="badge text-bg-warning"><i class="bi bi-shield-lock-fill"></i> riservata</span>
                <?php endif; ?>
                <?php if ((string) $risorsa['categoriaAllegato'] !== ''): ?>
                  <span class="badge text-bg-light border"><?= Testo::esc((string) $risorsa['categoriaAllegato']) ?></span>
                <?php endif; ?>
                <?php if ((string) $risorsa['descrizione'] !== ''): ?>
                  <div class="catageo-nota"><?= Testo::esc(Testo::estratto((string) $risorsa['descrizione'], 120)) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($presente): ?>
                  <?php if ($anteprima === 'rilievo'): ?>
                    <?php // Il rilievo ha una pagina propria: si guarda a lungo. ?>
                    <a href="index.php?p=rilievo&amp;codice=<?= urlencode($codice) ?>&amp;prog=<?= $prog ?>">
                      <i class="bi bi-<?= Risorse::tridimensionale($risorsa) ? 'badge-3d' : 'file-earmark-ruled' ?>"></i>
                      <?= Testo::esc((string) $risorsa['file']) ?>
                    </a>
                  <?php elseif (($modo = catageoFinestraPer($risorsa)) !== ''): ?>
                    <?php
                    /* L'icona dice cosa succede al clic: un documento si
                       legge, un video si guarda. Un'icona sola per entrambi
                       prometterebbe la cosa sbagliata a meta degli allegati. */
                    $icona = ['documento' => 'file-earmark-text', 'video' => 'play-circle'][$modo] ?? 'image';
                    ?>
                    <a href="<?= Testo::esc($url($prog, false, true)) ?>"
                       <?= catageoAttributiMedia($risorsa, $codice, $sigla) ?>>
                      <i class="bi bi-<?= $icona ?>"></i>
                      <?= Testo::esc((string) $risorsa['file']) ?>
                    </a>
                  <?php else: ?>
                    <a href="<?= Testo::esc($url($prog)) ?>"><?= Testo::esc((string) $risorsa['file']) ?></a>
                  <?php endif; ?>
                  <?= catageoDatiMedia($risorsa, false) ?>

                  <?php if ($anteprima === 'rilievo'): ?>
                    <div class="catageo-dati-media">
                      <?php if (Risorse::tridimensionale($risorsa)): ?>
                        <span class="badge text-bg-light border"><i class="bi bi-badge-3d"></i> modello 3D</span>
                      <?php endif; ?>
                      <?php if (Tracciato::convertibile((string) $risorsa['file'])): ?>
                        <?php if (Risorse::mappabile($risorsa)): ?>
                          <span class="badge text-bg-light border"><i class="bi bi-bezier2"></i> in mappa</span>
                        <?php else: ?>
                          <span class="badge text-bg-light border text-body-secondary"
                                title="Tracciato convertibile ma escluso dalla mappa">
                            <i class="bi bi-eye-slash"></i> escluso dalla mappa
                          </span>
                        <?php endif; ?>
                      <?php endif; ?>
                      <?php if ((string) $risorsa['tipoRilievo'] !== ''): ?>
                        <span><?= Testo::esc((string) $risorsa['tipoRilievo']) ?></span>
                      <?php endif; ?>
                      <?php if ((string) $risorsa['scala'] !== ''): ?>
                        <span>scala <?= Testo::esc((string) $risorsa['scala']) ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-danger" title="Registrato nell'indice ma assente dalla cartella">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= Testo::esc((string) $risorsa['file']) ?> — file mancante
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-end catageo-valore"><?= Testo::esc(Testo::dimensione((int) $risorsa['dimensione'])) ?></td>
              <td><?= Testo::esc((string) $risorsa['data']) ?></td>
              <?php if ($puoGestire): ?>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="index.php?p=risorse&amp;codice=<?= urlencode($codice) ?>&amp;sez=<?= $sigla ?>&amp;modifica=<?= $prog ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="<?= Testo::esc($ritorno) ?>">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="elimina">
                      <input type="hidden" name="progressivo" value="<?= $prog ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit"
                              data-catageo-conferma="Rimuovere questa risorsa dalla sezione? Il file viene spostato in _rimossi e resta recuperabile.">
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
  </div>

<?php endif; ?>

<?php
// ----------------------------------------------------------------- modifica
$daModificare = isset($_GET['modifica']) ? (int) $_GET['modifica'] : 0;
$inModifica   = $daModificare > 0 && $puoGestire
    ? Risorse::trova($codice, $sigla, $daModificare)
    : null;
?>
<?php if ($inModifica !== null): ?>
  <div class="card mt-4">
    <div class="card-header">
      <h2 class="h6 mb-0">
        Dati di <span class="catageo-valore"><?= Testo::esc(Sezioni::riferimento($sigla, $daModificare)) ?></span>
      </h2>
    </div>
    <div class="card-body">
      <form method="post" action="<?= Testo::esc($ritorno) ?>" class="row g-3">
        <?= Auth::campoToken() ?>
        <input type="hidden" name="operazione" value="aggiorna">
        <input type="hidden" name="progressivo" value="<?= $daModificare ?>">

        <div class="col-md-6">
          <label for="mTitolo" class="form-label">Titolo</label>
          <input type="text" class="form-control" id="mTitolo" name="titolo" maxlength="200"
                 value="<?= Testo::esc((string) $inModifica['titolo']) ?>">
        </div>

        <div class="col-md-3">
          <label for="mData" class="form-label">Data</label>
          <input type="date" class="form-control" id="mData" name="data"
                 value="<?= Testo::esc((string) $inModifica['data']) ?>">
        </div>

        <div class="col-md-3">
          <label for="mRiservatezza" class="form-label">Riservatezza</label>
          <select class="form-select" id="mRiservatezza" name="riservatezza">
            <?php foreach (['pubblica', 'riservata'] as $r): ?>
              <option value="<?= $r ?>" <?= (string) $inModifica['riservatezza'] === $r ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label for="mAutore" class="form-label">Autore</label>
          <select class="form-select" id="mAutore" name="autoreId">
            <option value="">—</option>
            <?php foreach (Esploratori::elenco(true) as $e): ?>
              <option value="<?= Testo::esc((string) $e['id']) ?>"
                <?= (string) $inModifica['autoreId'] === (string) $e['id'] ? 'selected' : '' ?>>
                <?= Testo::esc(Esploratori::etichetta($e)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label for="mGruppo" class="form-label">Gruppo</label>
          <select class="form-select" id="mGruppo" name="gruppoId">
            <option value="">—</option>
            <?php foreach (Gruppi::elenco(true) as $g): ?>
              <option value="<?= Testo::esc((string) $g['id']) ?>"
                <?= (string) $inModifica['gruppoId'] === (string) $g['id'] ? 'selected' : '' ?>>
                <?= Testo::esc(Gruppi::etichetta($g)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label for="mLicenza" class="form-label">Licenza</label>
          <input type="text" class="form-control" id="mLicenza" name="licenza" maxlength="80"
                 value="<?= Testo::esc((string) $inModifica['licenza']) ?>"
                 placeholder="CC BY-NC-SA">
        </div>

        <?php if ($sigla === 'AL'): ?>
          <div class="col-md-4">
            <label for="mCategoria" class="form-label">Categoria</label>
            <select class="form-select" id="mCategoria" name="categoriaAllegato">
              <option value="">—</option>
              <?php foreach (['relazione', 'autorizzazione', 'articolo', 'cartografia', 'corrispondenza', 'altro'] as $c): ?>
                <option value="<?= $c ?>" <?= (string) $inModifica['categoriaAllegato'] === $c ? 'selected' : '' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <?php if ($sigla === 'VI'): ?>
          <div class="col-md-8">
            <label for="mUrl" class="form-label">Video esterno</label>
            <input type="url" class="form-control" id="mUrl" name="urlEsterno" maxlength="500"
                   value="<?= Testo::esc((string) $inModifica['urlEsterno']) ?>">
            <div class="catageo-nota">
              Per i filmati ospitati altrove, così da non consumare lo spazio dell'hosting.
            </div>
          </div>
        <?php endif; ?>

        <?php if (in_array($anteprima, ['immagine', 'video'], true)): ?>
          <?php $mappaEsterna = catageoUrlMappaEsterna($inModifica); ?>
          <div class="col-md-4">
            <label for="mLat" class="form-label">Latitudine</label>
            <input type="text" class="form-control catageo-valore" id="mLat" name="latitudine"
                   value="<?= Testo::esc((string) $inModifica['latitudine']) ?>"
                   placeholder="41.856231">
          </div>
          <div class="col-md-4">
            <label for="mLon" class="form-label">Longitudine</label>
            <input type="text" class="form-control catageo-valore" id="mLon" name="longitudine"
                   value="<?= Testo::esc((string) $inModifica['longitudine']) ?>"
                   placeholder="12.532104">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <div class="catageo-nota">
              Gradi decimali WGS84, dove e stato ripreso il file. Vengono letti dai
              metadati incorporati quando ci sono; se il dato manca o e sbagliato si
              corregge qui.
              <?php if ($mappaEsterna !== ''): ?>
                <br>
                <a href="<?= Testo::esc($mappaEsterna) ?>" target="_blank" rel="noopener">
                  <i class="bi bi-geo-alt-fill"></i> Vedi il punto su Google Maps
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($anteprima === 'rilievo'): ?>
          <input type="hidden" name="conMostraInMappa" value="1">

          <div class="col-md-4">
            <label for="mTipoRilievo" class="form-label">Tipo di rilievo</label>
            <select class="form-select" id="mTipoRilievo" name="tipoRilievo">
              <option value="">—</option>
              <?php foreach (['pianta', 'sezione', 'spaccato', 'poligonale', 'modello 3D'] as $t): ?>
                <option value="<?= $t ?>" <?= (string) $inModifica['tipoRilievo'] === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label for="mScala" class="form-label">Scala</label>
            <input type="text" class="form-control" id="mScala" name="scala" maxlength="40"
                   value="<?= Testo::esc((string) $inModifica['scala']) ?>" placeholder="1:100">
          </div>

          <div class="col-md-4">
            <label for="mDataRilievo" class="form-label">Data del rilievo</label>
            <input type="date" class="form-control" id="mDataRilievo" name="dataRilievo"
                   value="<?= Testo::esc((string) $inModifica['dataRilievo']) ?>">
          </div>

          <div class="col-md-4">
            <label for="mSistema" class="form-label">Sistema di riferimento</label>
            <select class="form-select" id="mSistema" name="sistemaRiferimento">
              <option value="">—</option>
              <?php foreach (Coordinate::sistemi() as $cod => $sis): ?>
                <option value="<?= Testo::esc((string) $cod) ?>"
                  <?= (string) $inModifica['sistemaRiferimento'] === (string) $cod ? 'selected' : '' ?>>
                  <?= Testo::esc((string) $sis['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="catageo-nota">In che sistema sono le coordinate del rilievo.</div>
          </div>

          <div class="col-md-4">
            <label for="mStrumentazione" class="form-label">Strumentazione</label>
            <input type="text" class="form-control" id="mStrumentazione" name="strumentazione" maxlength="200"
                   value="<?= Testo::esc((string) $inModifica['strumentazione']) ?>"
                   placeholder="DistoX2, bussola e clinometro">
          </div>

          <div class="col-md-4">
            <label for="mRilevatori" class="form-label">Rilevatori</label>
            <input type="text" class="form-control" id="mRilevatori" name="rilevatori" maxlength="300"
                   value="<?= Testo::esc((string) $inModifica['rilevatori']) ?>"
                   placeholder="Separati da virgola">
          </div>

          <?php if (Tracciato::convertibile((string) $inModifica['file'])): ?>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="mMostraInMappa" name="mostraInMappa"
                       value="1" <?= (string) $inModifica['mostraInMappa'] !== '0' ? 'checked' : '' ?>>
                <label class="form-check-label" for="mMostraInMappa">
                  Sovrapponi questo tracciato alla mappa della scheda
                </label>
              </div>
              <div class="catageo-nota">
                Da spegnere quando un ipogeo ha più rilievi dello stesso ramo e mostrarli
                tutti insieme renderebbe la mappa illeggibile.
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="col-12">
          <label for="mDescrizione" class="form-label">Descrizione</label>
          <textarea class="form-control" id="mDescrizione" name="descrizione" rows="4"><?= Testo::esc((string) $inModifica['descrizione']) ?></textarea>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salva</button>
          <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">Annulla</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($jsPagina !== []): ?>
  <?php require CATAGEO_ROOT . '/app/view/modale-media.php'; ?>
<?php endif; ?>
