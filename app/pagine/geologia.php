<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/geologia.php
 *  Descrizione ..: Sezione geologica di un ipogeo (6.16, fase 6b).
 *
 *                  La pagina e divisa in moduli indipendenti — inquadramento,
 *                  genesi, assetto, morfologie, idrogeologia, rischi, campioni
 *                  — e ciascuno si salva per conto suo. Un modulo unico da
 *                  sette sezioni farebbe perdere tutto a chi sbaglia un campo
 *                  in fondo, e la geologia si compila in piu riprese: una parte
 *                  in cavita, una davanti alla carta.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Prima stesura (fase 6b).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');
require_once CATAGEO_ROOT . '/app/view/parti-avvisi.php';

$codice = trim((string) ($_GET['codice'] ?? ''));

$risoluzione = $codice === '' ? null : Ipogeo::risolvi($codice);
if ($risoluzione === null) {
    Auth::messaggio('errore', 'Nessun ipogeo con codice "' . $codice . '".');
    header('Location: index.php?p=ipogei');
    exit;
}

$scheda = $risoluzione['scheda'];
if (!Visibilita::schedaVisibile(
    (string) $scheda['ubicazione']['riservatezza'],
    (string) $scheda['catasto']['statoScheda']
)) {
    Auth::messaggio('errore', 'La scheda richiesta non è consultabile con il livello di utenza in uso.');
    header('Location: index.php?p=ipogei');
    exit;
}

$codice   = (string) $risoluzione['codiceCorrente'];
$ritorno  = 'index.php?p=geologia&codice=' . urlencode($codice);
$puoScrivere = Auth::puo('compila_sezioni');

// ============================================================================
//  SCRITTURA
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    Auth::esigi('compila_sezioni');

    $operazione = (string) ($_POST['operazione'] ?? '');

    /**
     * Righe di un elenco ripetibile, ripulite dalle vuote.
     *
     * Il modulo offre sempre qualche riga libera in coda: senza questo filtro
     * ogni salvataggio scriverebbe voci vuote, e dopo tre salvataggi l'elenco
     * sarebbe pieno di righe che nessuno ha compilato.
     *
     * @return array<int,array<string,string>>
     */
    $righe = static function (string $campo): array {
        $fuori = [];
        foreach ((array) ($_POST[$campo] ?? []) as $riga) {
            if (!is_array($riga)) {
                continue;
            }
            $valori = array_map(
                static fn ($v): string => is_scalar($v) ? trim((string) $v) : '',
                $riga
            );
            if (implode('', $valori) === '') {
                continue;
            }
            $fuori[] = $valori;
        }

        return $fuori;
    };

    try {
        switch ($operazione) {
            case 'inquadramento':
                Geologia::salvaInquadramento($codice, [
                    'litologia'       => (string) ($_POST['litologia'] ?? ''),
                    'formazione'      => (string) ($_POST['formazione'] ?? ''),
                    'unitaGeologica'  => (string) ($_POST['unitaGeologica'] ?? ''),
                    'etaFormazione'   => (string) ($_POST['etaFormazione'] ?? ''),
                    'sistemaCrono'    => (string) ($_POST['sistemaCrono'] ?? ''),
                    'serieCrono'      => (string) ($_POST['serieCrono'] ?? ''),
                    'foglioGeologico' => (string) ($_POST['foglioGeologico'] ?? ''),
                    'fonteTipo'       => (string) ($_POST['fonteTipo'] ?? ''),
                    'fonteNome'       => (string) ($_POST['fonteNome'] ?? ''),
                    'fonteData'       => (string) ($_POST['fonteData'] ?? ''),
                    'fonteModalita'   => (string) ($_POST['fonteModalita'] ?? ''),
                ]);
                Auth::messaggio('successo', 'Inquadramento salvato.');
                break;

            case 'genesi':
                Geologia::salvaGenesi($codice, [
                    'tipoGenesi'       => (string) ($_POST['tipoGenesi'] ?? ''),
                    'processo'         => (string) ($_POST['processo'] ?? ''),
                    'rocciaIncassante' => (string) ($_POST['rocciaIncassante'] ?? ''),
                ]);
                Auth::messaggio('successo', 'Genesi salvata.');
                break;

            case 'assetto':
                Geologia::salvaAssetto($codice, [
                    'immersione'    => (string) ($_POST['immersione'] ?? ''),
                    'inclinazione'  => (string) ($_POST['inclinazione'] ?? ''),
                    'fratturazione' => (string) ($_POST['fratturazione'] ?? ''),
                    'note'          => (string) ($_POST['noteAssetto'] ?? ''),
                ]);
                Auth::messaggio('successo', 'Assetto strutturale salvato.');
                break;

            case 'idrogeologia':
                Geologia::salvaIdrogeologia($codice, [
                    'acquifero'          => (string) ($_POST['acquifero'] ?? ''),
                    'permeabilita'       => (string) ($_POST['permeabilita'] ?? ''),
                    'ruoloIdrogeologico' => (string) ($_POST['ruoloIdrogeologico'] ?? ''),
                    'serieMisureRif'     => (string) ($_POST['serieMisureRif'] ?? ''),
                    'note'               => (string) ($_POST['noteIdro'] ?? ''),
                ]);
                Auth::messaggio('successo', 'Idrogeologia salvata.');
                break;

            case 'morfologie':
                Geologia::salvaElenco($codice, 'morfologie', $righe('morfologie'));
                Auth::messaggio('successo', 'Morfologie salvate.');
                break;

            case 'rischi':
                Geologia::salvaElenco($codice, 'rischi', $righe('rischi'));
                Auth::messaggio('successo', 'Rischi salvati.');
                break;

            case 'campioni':
                Geologia::salvaElenco($codice, 'campioni', $righe('campioni'));
                Auth::messaggio('successo', 'Campioni salvati.');
                break;

            default:
                throw new GeologiaEccezione('Operazione non riconosciuta.');
        }
    } catch (GeologiaEccezione | XmlEccezione | IpogeoEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: ' . $ritorno);
    exit;
}

// ============================================================================
//  LETTURA
// ============================================================================

$titolo        = 'Geologia — ' . $codice;
$inquadramento = Geologia::inquadramento($codice);
$genesi        = Geologia::genesi($codice);
$assetto       = Geologia::assetto($codice);
$morfologie    = Geologia::morfologie($codice);
$idro          = Geologia::idrogeologia($codice);
$rischi        = Geologia::rischi($codice);
$campioni      = Geologia::campioni($codice);

/*
 * Compilazione assistita (6.16.2). Il pulsante compare solo se c'e davvero
 * qualcosa da interrogare e un punto da cui partire: offrirlo su una scheda
 * senza coordinate, o su un'installazione senza layer interrogabili,
 * significherebbe far scoprire il limite con un messaggio di errore.
 */
$interrogabili       = Mappa::layerInterrogabili();
$haCoordinate        = trim((string) $scheda['ubicazione']['coordinate']['latitudine']) !== ''
                    && trim((string) $scheda['ubicazione']['coordinate']['longitudine']) !== '';
$coordinateRiservate = Visibilita::coordinateRiservate((string) $scheda['ubicazione']['riservatezza']);

/** Righe libere offerte in coda a ogni elenco ripetibile. */
const RIGHE_LIBERE_GEO = 3;

/** Menu a tendina da un vocabolario, con il valore corrente selezionato. */
$scelta = static function (string $nome, array $voci, string $corrente, string $classe = 'form-select'): void {
    echo '<select class="' . $classe . '" name="' . Testo::esc($nome) . '" id="' . Testo::esc($nome) . '">';
    foreach ($voci as $valore => $etichetta) {
        printf('<option value="%s"%s>%s</option>',
            Testo::esc((string) $valore),
            $corrente === (string) $valore ? ' selected' : '',
            Testo::esc($etichetta));
    }
    echo '</select>';
};
?>

<div class="catageo-intestazione">
  <div>
    <h1>Geologia</h1>
    <p class="text-body-secondary mb-0">
      <span class="catageo-codice"><?= Testo::esc($codice) ?></span>
      <?= Testo::esc((string) $scheda['identificazione']['nome']) ?>
    </p>
  </div>
  <a class="btn btn-outline-secondary"
     href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode($codice) ?>">
    <i class="bi bi-arrow-left"></i> Torna alla scheda
  </a>
</div>

<?php catageoAvvisi(Geologia::avvisi($codice)); ?>

<?php if (!$puoScrivere): ?>
  <div class="alert alert-secondary py-2 catageo-nota">
    Sola consultazione: per compilare serve il livello operatore.
  </div>
<?php endif; ?>

<div class="row g-4">

  <!-- ============================================== inquadramento -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0">Inquadramento</h2>
        <?php if ($puoScrivere && $interrogabili !== [] && $haCoordinate): ?>
          <button type="button" class="btn btn-sm btn-outline-primary catageo-non-stampare"
                  id="catageoInterroga">
            <i class="bi bi-crosshair"></i> Compila dalla cartografia
          </button>
        <?php endif; ?>
      </div>
      <div class="card-body">

        <?php if ($puoScrivere && $interrogabili !== [] && $haCoordinate): ?>
          <?php if ($coordinateRiservate): ?>
            <?php
            /*
             * La scelta a tre vie. Interrogare un servizio significa mandare la
             * coordinata al server di qualcun altro, che quasi sempre la
             * registra: su una cavita riservata la decisione non puo essere
             * implicita nel clic di un pulsante.
             */
            $passo = Config::intero('sicurezza.offuscamentoCoordinate', 1000);
            ?>
            <div class="alert alert-warning py-2" id="catageoSceltaCoord" hidden>
              <div class="fw-semibold mb-1">Questa cavità ha coordinate riservate.</div>
              <p class="mb-2">
                Interrogare la cartografia manda il punto al server dell'ente, che
                di norma lo registra nei propri log. Scegliere cosa può uscire da qui.
              </p>
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-modo="offuscata">
                  Manda il punto arrotondato a <?= (int) $passo ?> m
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-modo="puntuale">
                  Manda il punto esatto
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-modo="niente">
                  Non interrogare
                </button>
              </div>
              <div class="catageo-nota mt-2">
                L'arrotondamento è sempre allo stesso punto, non un errore casuale:
                un errore che cambia a ogni richiesta si annulla facendo la media di
                tre richieste. A 1:100.000 <?= (int) $passo ?> m non cambiano la
                formazione che si legge.
              </div>
            </div>
          <?php endif; ?>

          <div id="catageoProposte" class="mb-3 catageo-non-stampare"></div>

          <script type="application/json" id="catageo-geologia-dati"><?= json_encode([
              'codice'     => $codice,
              'token'      => Auth::token(),
              'riservate'  => $coordinateRiservate,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
          <script src="assets/js/catageo-geologia.js?v=<?= Testo::esc(CATAGEO_VERSIONE) ?>"></script>
        <?php endif; ?>

        <form method="post" action="<?= Testo::esc($ritorno) ?>">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="inquadramento">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="litologia" class="form-label">Litologia</label>
              <input type="text" class="form-control" id="litologia" name="litologia"
                     maxlength="120" placeholder="Tufo litoide"
                     value="<?= Testo::esc((string) $inquadramento['litologia']) ?>">
            </div>
            <div class="col-md-6">
              <label for="formazione" class="form-label">Formazione</label>
              <input type="text" class="form-control" id="formazione" name="formazione"
                     maxlength="150" value="<?= Testo::esc((string) $inquadramento['formazione']) ?>">
            </div>
            <div class="col-md-6">
              <label for="unitaGeologica" class="form-label">Unità geologica</label>
              <input type="text" class="form-control" id="unitaGeologica" name="unitaGeologica"
                     maxlength="150" value="<?= Testo::esc((string) $inquadramento['unitaGeologica']) ?>">
            </div>
            <div class="col-md-6">
              <label for="etaFormazione" class="form-label">Età</label>
              <input type="text" class="form-control" id="etaFormazione" name="etaFormazione"
                     maxlength="100" value="<?= Testo::esc((string) $inquadramento['etaFormazione']) ?>">
            </div>
            <div class="col-md-4">
              <label for="sistemaCrono" class="form-label">Sistema</label>
              <input type="text" class="form-control" id="sistemaCrono" name="sistemaCrono"
                     maxlength="60" value="<?= Testo::esc((string) $inquadramento['sistemaCrono']) ?>">
            </div>
            <div class="col-md-4">
              <label for="serieCrono" class="form-label">Serie</label>
              <input type="text" class="form-control" id="serieCrono" name="serieCrono"
                     maxlength="60" value="<?= Testo::esc((string) $inquadramento['serieCrono']) ?>">
            </div>
            <div class="col-md-4">
              <label for="foglioGeologico" class="form-label">Foglio geologico</label>
              <input type="text" class="form-control" id="foglioGeologico" name="foglioGeologico"
                     maxlength="100" value="<?= Testo::esc((string) $inquadramento['foglioGeologico']) ?>">
            </div>

            <?php
            /*
             * La fonte e la cosa piu importante di questo riquadro. Una
             * litologia osservata sul posto e una dedotta da una carta
             * 1:50.000 hanno lo stesso aspetto e valore diverso: senza
             * dichiararlo, chi legge non puo sapere quanto fidarsi.
             */
            ?>
            <div class="col-12"><hr class="my-1"></div>
            <div class="col-md-5">
              <label for="fonteModalita" class="form-label">Come si è ottenuto il dato</label>
              <?php $scelta('fonteModalita', Geologia::MODALITA_FONTE,
                  (string) $inquadramento['fonteModalita']); ?>
              <div class="catageo-nota">
                Un dato letto su una carta inquadra la formazione regionale e non
                distingue una lente di dieci metri. Dichiararlo è ciò che rende
                usabile il resto.
              </div>
            </div>
            <div class="col-md-4">
              <label for="fonteNome" class="form-label">Fonte</label>
              <input type="text" class="form-control" id="fonteNome" name="fonteNome"
                     maxlength="150" placeholder="Carta Geologica d'Italia 1:50.000"
                     value="<?= Testo::esc((string) $inquadramento['fonteNome']) ?>">
              <input type="hidden" name="fonteTipo"
                     value="<?= Testo::esc((string) $inquadramento['fonteTipo']) ?>">
            </div>
            <div class="col-md-3">
              <label for="fonteData" class="form-label">Consultata il</label>
              <input type="date" class="form-control" id="fonteData" name="fonteData"
                     value="<?= Testo::esc((string) $inquadramento['fonteData']) ?>">
            </div>
          </div>
          <?php if ($puoScrivere): ?>
            <button type="submit" class="btn btn-primary mt-3">
              <i class="bi bi-check-lg"></i> Salva inquadramento
            </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- ============================================== genesi e assetto -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h2 class="h6 mb-0">Genesi e assetto strutturale</h2></div>
      <div class="card-body">
        <form method="post" action="<?= Testo::esc($ritorno) ?>" class="mb-4">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="genesi">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="tipoGenesi" class="form-label">Tipo di genesi</label>
              <?php $scelta('tipoGenesi', Geologia::TIPI_GENESI, (string) $genesi['tipoGenesi']); ?>
            </div>
            <div class="col-md-6">
              <label for="rocciaIncassante" class="form-label">Roccia incassante</label>
              <input type="text" class="form-control" id="rocciaIncassante" name="rocciaIncassante"
                     maxlength="150" value="<?= Testo::esc((string) $genesi['rocciaIncassante']) ?>">
            </div>
            <div class="col-12">
              <label for="processo" class="form-label">Processo</label>
              <textarea class="form-control" id="processo" name="processo" rows="3"><?= Testo::esc((string) $genesi['processo']) ?></textarea>
            </div>
          </div>
          <?php if ($puoScrivere): ?>
            <button type="submit" class="btn btn-primary mt-3">
              <i class="bi bi-check-lg"></i> Salva genesi
            </button>
          <?php endif; ?>
        </form>

        <hr>

        <form method="post" action="<?= Testo::esc($ritorno) ?>">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="assetto">
          <div class="row g-3">
            <div class="col-md-4">
              <label for="immersione" class="form-label">Immersione (gradi)</label>
              <input type="text" class="form-control catageo-valore" id="immersione" name="immersione"
                     maxlength="10" value="<?= Testo::esc((string) $assetto['immersione']) ?>">
            </div>
            <div class="col-md-4">
              <label for="inclinazione" class="form-label">Inclinazione (gradi)</label>
              <input type="text" class="form-control catageo-valore" id="inclinazione" name="inclinazione"
                     maxlength="10" value="<?= Testo::esc((string) $assetto['inclinazione']) ?>">
            </div>
            <div class="col-md-4">
              <label for="fratturazione" class="form-label">Fratturazione</label>
              <?php $scelta('fratturazione', Geologia::FRATTURAZIONE, (string) $assetto['fratturazione']); ?>
            </div>
            <div class="col-12">
              <label for="noteAssetto" class="form-label">Note</label>
              <textarea class="form-control" id="noteAssetto" name="noteAssetto" rows="2"><?= Testo::esc((string) $assetto['note']) ?></textarea>
            </div>
          </div>
          <?php if ($puoScrivere): ?>
            <button type="submit" class="btn btn-primary mt-3">
              <i class="bi bi-check-lg"></i> Salva assetto
            </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- ============================================== morfologie -->
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h2 class="h6 mb-0">Morfologie <span class="catageo-nota">· <?= count($morfologie) ?></span></h2>
      </div>
      <div class="card-body">
        <form method="post" action="<?= Testo::esc($ritorno) ?>">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="morfologie">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr>
                <th style="width:18%">Tipo</th><th>Descrizione</th>
                <th style="width:18%">Zona</th><th style="width:10%">Foto</th>
              </tr></thead>
              <tbody>
                <?php for ($i = 0; $i < count($morfologie) + RIGHE_LIBERE_GEO; $i++):
                    $m = array_merge(Geologia::CAMPI_MORFOLOGIA, (array) ($morfologie[$i] ?? [])); ?>
                  <tr>
                    <td><?php $scelta("morfologie[$i][tipo]", Geologia::TIPI_MORFOLOGIA,
                        (string) $m['tipo'], 'form-select form-select-sm'); ?></td>
                    <td><input type="text" class="form-control form-control-sm" name="morfologie[<?= $i ?>][descrizione]"
                               value="<?= Testo::esc((string) $m['descrizione']) ?>"></td>
                    <td><input type="text" class="form-control form-control-sm" name="morfologie[<?= $i ?>][zonaCavita]"
                               value="<?= Testo::esc((string) $m['zonaCavita']) ?>"></td>
                    <td><input type="text" class="form-control form-control-sm catageo-valore" name="morfologie[<?= $i ?>][fotoRif]"
                               placeholder="FO003" value="<?= Testo::esc((string) $m['fotoRif']) ?>"></td>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
          <div class="catageo-nota">
            Fra i tipi c'è <strong>tracce di scavo</strong>: su una cavità
            artificiale è la morfologia principale.
          </div>
          <?php if ($puoScrivere): ?>
            <button type="submit" class="btn btn-primary mt-2">
              <i class="bi bi-check-lg"></i> Salva morfologie
            </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- ============================================== idrogeologia -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h2 class="h6 mb-0">Idrogeologia</h2></div>
      <div class="card-body">
        <form method="post" action="<?= Testo::esc($ritorno) ?>">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="idrogeologia">
          <div class="row g-3">
            <div class="col-md-12">
              <label for="acquifero" class="form-label">Acquifero</label>
              <input type="text" class="form-control" id="acquifero" name="acquifero"
                     maxlength="150" value="<?= Testo::esc((string) $idro['acquifero']) ?>">
            </div>
            <div class="col-md-6">
              <label for="permeabilita" class="form-label">Permeabilità</label>
              <?php $scelta('permeabilita', Geologia::PERMEABILITA, (string) $idro['permeabilita']); ?>
            </div>
            <div class="col-md-6">
              <label for="ruoloIdrogeologico" class="form-label">Ruolo idrogeologico</label>
              <?php $scelta('ruoloIdrogeologico', Geologia::RUOLI_IDRO, (string) $idro['ruoloIdrogeologico']); ?>
            </div>
            <div class="col-md-6">
              <label for="serieMisureRif" class="form-label">Serie di misure collegata</label>
              <input type="text" class="form-control catageo-valore" id="serieMisureRif" name="serieMisureRif"
                     maxlength="10" placeholder="SC003" value="<?= Testo::esc((string) $idro['serieMisureRif']) ?>">
              <div class="catageo-nota">
                La portata si misura nei dati scientifici, non si ridigita qui.
              </div>
            </div>
            <div class="col-12">
              <label for="noteIdro" class="form-label">Note</label>
              <textarea class="form-control" id="noteIdro" name="noteIdro" rows="2"><?= Testo::esc((string) $idro['note']) ?></textarea>
            </div>
          </div>
          <?php if ($puoScrivere): ?>
            <button type="submit" class="btn btn-primary mt-3">
              <i class="bi bi-check-lg"></i> Salva idrogeologia
            </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- ============================================== rischi -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">
        <h2 class="h6 mb-0">Rischi <span class="catageo-nota">· <?= count($rischi) ?></span></h2>
      </div>
      <div class="card-body">
        <form method="post" action="<?= Testo::esc($ritorno) ?>">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="rischi">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th style="width:26%">Tipo</th><th style="width:20%">Livello</th><th>Descrizione</th></tr></thead>
              <tbody>
                <?php for ($i = 0; $i < count($rischi) + RIGHE_LIBERE_GEO; $i++):
                    $r = array_merge(['tipo' => '', 'livello' => '', 'descrizione' => ''],
                        (array) ($rischi[$i] ?? [])); ?>
                  <tr>
                    <td><?php $scelta("rischi[$i][tipo]", array_merge(['' => '—'], Geologia::TIPI_RISCHIO),
                        (string) $r['tipo'], 'form-select form-select-sm'); ?></td>
                    <td><?php $scelta("rischi[$i][livello]", array_merge(['' => '—'], Geologia::LIVELLI_RISCHIO),
                        (string) $r['livello'], 'form-select form-select-sm'); ?></td>
                    <td><input type="text" class="form-control form-control-sm" name="rischi[<?= $i ?>][descrizione]"
                               value="<?= Testo::esc((string) $r['descrizione']) ?>"></td>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
          <div class="catageo-nota">
            Solo <strong>medio</strong> e <strong>alto</strong> compaiono nella barra
            avvisi della scheda: un rischio basso segnalato accanto a un vincolo e a
            un periodo critico abituerebbe a ignorare la barra.
          </div>
          <?php if ($puoScrivere): ?>
            <button type="submit" class="btn btn-primary mt-2">
              <i class="bi bi-check-lg"></i> Salva rischi
            </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- ============================================== campioni -->
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h2 class="h6 mb-0">Campioni <span class="catageo-nota">· <?= count($campioni) ?></span></h2>
      </div>
      <div class="card-body">
        <form method="post" action="<?= Testo::esc($ritorno) ?>">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="campioni">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr>
                <th style="width:14%">Tipo</th><th style="width:10%">Data</th>
                <th style="width:14%">Prelevato da</th><th style="width:12%">Zona</th>
                <th>Finalità</th><th style="width:16%">Depositato presso</th>
                <th style="width:14%">Autorizzazione</th>
              </tr></thead>
              <tbody>
                <?php for ($i = 0; $i < count($campioni) + RIGHE_LIBERE_GEO; $i++):
                    $c = array_merge(Geologia::CAMPI_CAMPIONE, (array) ($campioni[$i] ?? [])); ?>
                  <tr>
                    <td><input type="text" class="form-control form-control-sm" name="campioni[<?= $i ?>][tipo]"
                               value="<?= Testo::esc((string) $c['tipo']) ?>"></td>
                    <td><input type="date" class="form-control form-control-sm" name="campioni[<?= $i ?>][data]"
                               value="<?= Testo::esc((string) $c['data']) ?>"></td>
                    <td>
                      <select class="form-select form-select-sm" name="campioni[<?= $i ?>][prelevatoDa]">
                        <option value="">—</option>
                        <?php foreach (Esploratori::elenco(true) as $e): ?>
                          <option value="<?= Testo::esc((string) $e['id']) ?>"
                                  <?= (string) $c['prelevatoDa'] === (string) $e['id'] ? 'selected' : '' ?>>
                            <?= Testo::esc(Esploratori::etichetta($e)) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td><input type="text" class="form-control form-control-sm" name="campioni[<?= $i ?>][zonaCavita]"
                               value="<?= Testo::esc((string) $c['zonaCavita']) ?>"></td>
                    <td><input type="text" class="form-control form-control-sm" name="campioni[<?= $i ?>][finalita]"
                               value="<?= Testo::esc((string) $c['finalita']) ?>"></td>
                    <td><input type="text" class="form-control form-control-sm" name="campioni[<?= $i ?>][depositatoPresso]"
                               value="<?= Testo::esc((string) $c['depositatoPresso']) ?>"></td>
                    <td><input type="text" class="form-control form-control-sm" name="campioni[<?= $i ?>][autorizzazione]"
                               value="<?= Testo::esc((string) $c['autorizzazione']) ?>"></td>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
          <div class="catageo-nota">
            Il prelievo in cavità può richiedere un'autorizzazione: registrarla è
            parte del dato, non un di più.
          </div>
          <?php if ($puoScrivere): ?>
            <button type="submit" class="btn btn-primary mt-2">
              <i class="bi bi-check-lg"></i> Salva campioni
            </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

</div>
