<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/archeologia.php
 *  Descrizione ..: Inquadramento archeologico, evidenze, tutela e indagini
 *                  (9.9).
 *
 *                  La tutela sta in cima e non in fondo: chi apre questa pagina
 *                  prima di un'uscita deve sapere subito se serve
 *                  un'autorizzazione.
 *  Versione .....: 0.12.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.0  2026-08-06  D.Candela  Prima stesura (fase 7d).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');
require_once CATAGEO_ROOT . '/app/view/parti-avvisi.php';

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
$ritorno = 'index.php?p=archeologia&codice=' . urlencode($codice);

// ============================================================================
//  OPERAZIONI
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    Auth::esigi('compila_sezioni');

    $operazione = (string) ($_POST['operazione'] ?? '');

    try {
        switch ($operazione) {

            case 'salvaInquadramento':
                $dati = [];
                foreach (array_keys(Archeologia::CAMPI_INQUADRAMENTO) as $campo) {
                    $dati[$campo] = (string) ($_POST[$campo] ?? '');
                }
                $dati['periodiSecondari'] = (array) ($_POST['periodiSecondari'] ?? []);

                /** Funzioni successive: righe parallele periodo/testo. */
                $funzioni = [];
                $testi = (array) ($_POST['funzioneTesto'] ?? []);
                $periodi = (array) ($_POST['funzionePeriodo'] ?? []);
                foreach (array_keys($testi) as $i) {
                    if (trim((string) $testi[$i]) === '') {
                        continue;
                    }
                    $funzioni[] = [
                        'periodo' => (string) ($periodi[$i] ?? ''),
                        'testo'   => (string) $testi[$i],
                    ];
                }
                $dati['funzioniSuccessive'] = $funzioni;

                Archeologia::salvaInquadramento($codice, $dati);
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', 'Inquadramento salvato.');
                break;

            case 'salvaTutela':
                $dati = [];
                foreach (array_keys(Archeologia::CAMPI_TUTELA) as $campo) {
                    $dati[$campo] = (string) ($_POST[$campo] ?? '');
                }
                $dati['vincolo'] = !empty($_POST['vincolo']) ? '1' : '0';

                Archeologia::salvaTutela($codice, $dati);
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', 'Tutela salvata.');
                break;

            case 'salvaEvidenza':
                $dati = [];
                foreach (array_keys(Archeologia::CAMPI_EVIDENZA) as $campo) {
                    $dati[$campo] = (string) ($_POST[$campo] ?? '');
                }
                $p = (int) ($_POST['progressivo'] ?? 0);

                if ($p > 0) {
                    Archeologia::aggiornaEvidenza($codice, $p, $dati);
                    Auth::messaggio('successo', 'Evidenza aggiornata.');
                } else {
                    $nuova = Archeologia::aggiungiEvidenza($codice, $dati);
                    Auth::messaggio('successo',
                        'Evidenza ' . Sezioni::riferimento('AR', $nuova) . ' aggiunta.');
                }
                IndiceIpogei::aggiorna($codice);
                break;

            case 'eliminaEvidenza':
                Archeologia::eliminaEvidenza($codice, (int) ($_POST['progressivo'] ?? 0));
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', 'Evidenza rimossa.');
                break;

            case 'aggiungiIndagine':
                Archeologia::aggiungiIndagine($codice, [
                    'tipo'            => (string) ($_POST['tipo'] ?? ''),
                    'data'            => (string) ($_POST['data'] ?? ''),
                    'soggetto'        => (string) ($_POST['soggetto'] ?? ''),
                    'esplorazioneRif' => (string) ($_POST['esplorazioneRif'] ?? ''),
                    'esito'           => (string) ($_POST['esito'] ?? ''),
                    'allegatoRif'     => (string) ($_POST['allegatoRif'] ?? ''),
                ]);
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', 'Indagine registrata.');
                break;

            case 'eliminaIndagine':
                Archeologia::eliminaIndagine($codice, (int) ($_POST['posizione'] ?? -1));
                IndiceIpogei::aggiorna($codice);
                Auth::messaggio('successo', 'Indagine rimossa.');
                break;

            default:
                Auth::messaggio('errore', 'Operazione non riconosciuta.');
        }
    } catch (ArcheologiaEccezione | IpogeoEccezione | XmlEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: ' . $ritorno);
    exit;
}

// ============================================================================
//  MODULO: evidenza
// ============================================================================

if ($azione === 'evidenza' && $puoCompilare) {
    $voce = $prog > 0 ? Archeologia::evidenza($codice, $prog) : null;
    if ($prog > 0 && $voce === null) {
        Auth::messaggio('errore', 'Evidenza non trovata.');
        header('Location: ' . $ritorno);
        exit;
    }
    $voce ??= Archeologia::CAMPI_EVIDENZA;

    $titolo = ($prog > 0 ? 'Modifica evidenza' : 'Nuova evidenza') . ' — ' . $codice;
    $v = static fn (string $c): string => Testo::esc((string) ($voce[$c] ?? ''));
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1><?= $prog > 0 ? 'Modifica evidenza' : 'Nuova evidenza archeologica' ?></h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
        </p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
        <i class="bi bi-x-lg"></i> Annulla
      </a>
    </div>

    <form method="post" action="index.php?p=archeologia&amp;codice=<?= urlencode($codice) ?>"
          class="needs-validation" novalidate>
      <?= Auth::campoToken() ?>
      <input type="hidden" name="operazione" value="salvaEvidenza">
      <input type="hidden" name="progressivo" value="<?= $prog ?>">

      <div class="row g-4">
        <div class="col-lg-8">
          <div class="card mb-4">
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                  <select class="form-select" id="tipo" name="tipo">
                    <?php foreach (Archeologia::TIPI_EVIDENZA as $valore => $etichetta): ?>
                      <option value="<?= Testo::esc($valore) ?>"
                        <?= (string) $voce['tipo'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="zonaCavita" class="form-label">Zona della cavità</label>
                  <input type="text" class="form-control" id="zonaCavita" name="zonaCavita"
                         value="<?= $v('zonaCavita') ?>" placeholder="Primo tratto, 0-24 m">
                </div>

                <div class="col-12">
                  <label for="descrizione" class="form-label">
                    Descrizione <span class="text-danger">*</span>
                  </label>
                  <textarea class="form-control" id="descrizione" name="descrizione"
                            rows="5" required><?= $v('descrizione') ?></textarea>
                </div>

                <div class="col-md-6">
                  <label for="periodo" class="form-label">Periodo</label>
                  <select class="form-select" id="periodo" name="periodo">
                    <option value="">— non indicato —</option>
                    <?php foreach (Periodi::elenco(true) as $p): ?>
                      <option value="<?= Testo::esc((string) $p['codice']) ?>"
                        <?= (string) $voce['periodo'] === (string) $p['codice'] ? 'selected' : '' ?>>
                        <?= Testo::esc(Periodi::etichetta($p)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="statoConservazione" class="form-label">Stato di conservazione</label>
                  <select class="form-select" id="statoConservazione" name="statoConservazione">
                    <?php foreach (Archeologia::CONSERVAZIONE as $valore => $etichetta): ?>
                      <option value="<?= Testo::esc($valore) ?>"
                        <?= (string) $voce['statoConservazione'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Documentazione</h2></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="fotoRif" class="form-label">Foto</label>
                <select class="form-select" id="fotoRif" name="fotoRif">
                  <option value="">—</option>
                  <?php foreach (Risorse::elenco($codice, 'FO') as $foto): ?>
                    <?php $rif = Sezioni::riferimento('FO', (int) $foto['progressivo']); ?>
                    <option value="<?= Testo::esc($rif) ?>"
                      <?= (string) $voce['fotoRif'] === $rif ? 'selected' : '' ?>>
                      <?= Testo::esc($rif . ' — ' . (string) $foto['titolo']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="rilievoRif" class="form-label">Rilievo</label>
                <select class="form-select" id="rilievoRif" name="rilievoRif">
                  <option value="">—</option>
                  <?php foreach (Risorse::elenco($codice, 'RI') as $rilievo): ?>
                    <?php $rif = Sezioni::riferimento('RI', (int) $rilievo['progressivo']); ?>
                    <option value="<?= Testo::esc($rif) ?>"
                      <?= (string) $voce['rilievoRif'] === $rif ? 'selected' : '' ?>>
                      <?= Testo::esc($rif . ' — ' . (string) $rilievo['titolo']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="biblioRif" class="form-label">Fonte bibliografica</label>
                <select class="form-select" id="biblioRif" name="biblioRif">
                  <option value="">—</option>
                  <?php foreach (Bibliografia::elenco($codice) as $fonte): ?>
                    <?php $rif = Sezioni::riferimento('BB', (int) $fonte['progressivo']); ?>
                    <option value="<?= Testo::esc($rif) ?>"
                      <?= (string) $voce['biblioRif'] === $rif ? 'selected' : '' ?>>
                      <?= Testo::esc($rif) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i> Salva l'evidenza
          </button>
        </div>
      </div>
    </form>

    <?php
    return;
}

// ============================================================================
//  VISTA PRINCIPALE
// ============================================================================

$inquadramento = Archeologia::inquadramento($codice);
$evidenze      = Archeologia::evidenze($codice);
$tutela        = Archeologia::tutela($codice);
$indagini      = Archeologia::indagini($codice);
$titolo        = 'Archeologia — ' . $codice;

/** Etichetta leggibile di un codice di periodo. */
$periodoLeggibile = static function (string $codice): string {
    if ($codice === '') {
        return '';
    }
    $voce = Periodi::trova($codice);

    return $voce === null ? $codice . ' (non in vocabolario)' : Periodi::etichetta($voce);
};

$secondari = array_values(array_filter(
    preg_split('/\s*,\s*/', (string) $inquadramento['periodiSecondari']) ?: [],
    static fn (string $v): bool => trim($v) !== ''
));
?>

<div class="catageo-intestazione">
  <div>
    <h1>Archeologia</h1>
    <p class="text-body-secondary mb-0">
      <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
      · <?= count($evidenze) ?> evidenz<?= count($evidenze) === 1 ? 'a' : 'e' ?>,
      <?= count($indagini) ?> indagin<?= count($indagini) === 1 ? 'e' : 'i' ?>
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($puoCompilare): ?>
      <a class="btn btn-primary"
         href="index.php?p=archeologia&amp;codice=<?= urlencode($codice) ?>&amp;azione=evidenza">
        <i class="bi bi-plus-lg"></i> Evidenza
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary"
       href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode($codice) ?>">
      <i class="bi bi-arrow-left"></i> Scheda
    </a>
  </div>
</div>

<?php catageoAvvisi(Archeologia::avvisi($codice)); ?>

<div class="row g-4">
  <div class="col-lg-8">

    <!-- ------------------------------------------------ inquadramento -->
    <div class="card mb-4">
      <div class="card-header"><h2 class="h6 mb-0">Inquadramento</h2></div>
      <div class="card-body">
        <?php if ($puoCompilare): ?>
          <form method="post" action="index.php?p=archeologia&amp;codice=<?= urlencode($codice) ?>">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="salvaInquadramento">

            <div class="row g-3">
              <div class="col-md-6">
                <label for="periodoPrincipale" class="form-label">Periodo principale</label>
                <select class="form-select" id="periodoPrincipale" name="periodoPrincipale">
                  <option value="">— non indicato —</option>
                  <?php foreach (Periodi::elenco(true) as $p): ?>
                    <option value="<?= Testo::esc((string) $p['codice']) ?>"
                      <?= (string) $inquadramento['periodoPrincipale'] === (string) $p['codice'] ? 'selected' : '' ?>>
                      <?= Testo::esc(Periodi::etichetta($p)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label for="periodiSecondari" class="form-label">Periodi secondari</label>
                <select class="form-select" id="periodiSecondari" name="periodiSecondari[]"
                        multiple size="4">
                  <?php foreach (Periodi::elenco(true) as $p): ?>
                    <option value="<?= Testo::esc((string) $p['codice']) ?>"
                      <?= in_array((string) $p['codice'], $secondari, true) ? 'selected' : '' ?>>
                      <?= Testo::esc(Periodi::etichetta($p)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="catageo-nota">
                  Un cunicolo romano riusato come ricovero antiaereo appartiene a
                  due epoche.
                </div>
              </div>

              <div class="col-md-2">
                <label for="datazioneDa" class="form-label">Dall'anno</label>
                <input type="text" class="form-control catageo-valore" id="datazioneDa"
                       name="datazioneDa" value="<?= Testo::esc((string) $inquadramento['datazioneDa']) ?>"
                       placeholder="-27">
              </div>
              <div class="col-md-2">
                <label for="datazioneA" class="form-label">All'anno</label>
                <input type="text" class="form-control catageo-valore" id="datazioneA"
                       name="datazioneA" value="<?= Testo::esc((string) $inquadramento['datazioneA']) ?>"
                       placeholder="476">
              </div>
              <div class="col-md-3">
                <label for="datazionePrecisione" class="form-label">Precisione</label>
                <select class="form-select" id="datazionePrecisione" name="datazionePrecisione">
                  <?php foreach (Archeologia::PRECISIONI as $valore => $etichetta): ?>
                    <option value="<?= $valore ?>"
                      <?= (string) $inquadramento['datazionePrecisione'] === $valore ? 'selected' : '' ?>>
                      <?= Testo::esc($etichetta) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-5">
                <label for="datazioneCriterio" class="form-label">Criterio di datazione</label>
                <input type="text" class="form-control" id="datazioneCriterio" name="datazioneCriterio"
                       value="<?= Testo::esc((string) $inquadramento['datazioneCriterio']) ?>"
                       placeholder="tecnica costruttiva">
              </div>
              <div class="col-12">
                <div class="catageo-nota">
                  Gli anni avanti Cristo si scrivono negativi: -27 e il 27 a.C.
                </div>
              </div>

              <div class="col-md-6">
                <label for="funzioneOriginaria" class="form-label">Funzione originaria</label>
                <input type="text" class="form-control" id="funzioneOriginaria" name="funzioneOriginaria"
                       value="<?= Testo::esc((string) $inquadramento['funzioneOriginaria']) ?>">
              </div>
              <div class="col-md-6">
                <label for="contestoTopografico" class="form-label">Contesto topografico</label>
                <input type="text" class="form-control" id="contestoTopografico" name="contestoTopografico"
                       value="<?= Testo::esc((string) $inquadramento['contestoTopografico']) ?>">
              </div>

              <div class="col-12">
                <label class="form-label">Funzioni successive</label>
                <?php
                $funzioni = (array) ($inquadramento['funzioniSuccessive'] ?? []);
                if ($funzioni === []) {
                    $funzioni = [['periodo' => '', 'testo' => '']];
                }
                // Una riga vuota in coda: aggiungerne una senza JavaScript deve
                // restare possibile, e il server scarta comunque le vuote.
                $funzioni[] = ['periodo' => '', 'testo' => ''];
                ?>
                <?php foreach ($funzioni as $i => $funzione): ?>
                  <div class="row g-2 mb-2">
                    <div class="col-md-4">
                      <select class="form-select form-select-sm" name="funzionePeriodo[<?= $i ?>]">
                        <option value="">— periodo —</option>
                        <?php foreach (Periodi::elenco(true) as $p): ?>
                          <option value="<?= Testo::esc((string) $p['codice']) ?>"
                            <?= (string) ($funzione['periodo'] ?? '') === (string) $p['codice'] ? 'selected' : '' ?>>
                            <?= Testo::esc(Periodi::etichetta($p)) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-8">
                      <input type="text" class="form-control form-control-sm"
                             name="funzioneTesto[<?= $i ?>]"
                             value="<?= Testo::esc((string) ($funzione['testo'] ?? '')) ?>"
                             placeholder="Ricovero antiaereo">
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="col-12">
                <label for="sintesi" class="form-label">Sintesi</label>
                <textarea class="form-control" id="sintesi" name="sintesi"
                          rows="5"><?= Testo::esc((string) $inquadramento['sintesi']) ?></textarea>
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check-lg"></i> Salva l'inquadramento
                </button>
              </div>
            </div>
          </form>

        <?php else: ?>
          <dl class="row catageo-dl mb-0">
            <dt class="col-sm-4 fw-normal text-body-secondary">Periodo principale</dt>
            <dd class="col-sm-8">
              <?= Testo::esc($periodoLeggibile((string) $inquadramento['periodoPrincipale'])) ?: '—' ?>
            </dd>

            <?php if ($secondari !== []): ?>
              <dt class="col-sm-4 fw-normal text-body-secondary">Periodi secondari</dt>
              <dd class="col-sm-8">
                <?php foreach ($secondari as $s): ?>
                  <span class="badge text-bg-light border"><?= Testo::esc($periodoLeggibile($s)) ?></span>
                <?php endforeach; ?>
              </dd>
            <?php endif; ?>

            <dt class="col-sm-4 fw-normal text-body-secondary">Datazione</dt>
            <dd class="col-sm-8">
              <?= Testo::esc((string) $inquadramento['datazioneDa']) ?>
              <?= (string) $inquadramento['datazioneA'] !== ''
                  ? '→ ' . Testo::esc((string) $inquadramento['datazioneA']) : '' ?>
              <span class="catageo-nota">
                <?= Testo::esc(Archeologia::PRECISIONI[(string) $inquadramento['datazionePrecisione']] ?? '') ?>
              </span>
            </dd>

            <dt class="col-sm-4 fw-normal text-body-secondary">Funzione originaria</dt>
            <dd class="col-sm-8"><?= Testo::esc((string) $inquadramento['funzioneOriginaria']) ?></dd>
          </dl>

          <?php if ((string) $inquadramento['sintesi'] !== ''): ?>
            <hr>
            <?= nl2br(Testo::esc((string) $inquadramento['sintesi'])) ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- ----------------------------------------------------- evidenze -->
    <div class="card mb-4">
      <div class="card-header"><h2 class="h6 mb-0">Evidenze</h2></div>
      <?php if ($evidenze === []): ?>
        <div class="card-body">
          <p class="text-body-secondary mb-0">Nessuna evidenza registrata.</p>
        </div>
      <?php else: ?>
        <div class="card-body">
          <?php foreach ($evidenze as $evidenza): ?>
            <?php $p = (int) $evidenza['progressivo']; ?>
            <div class="catageo-voce-biblio">
              <div class="d-flex justify-content-between gap-3">
                <div class="flex-grow-1">
                  <div>
                    <span class="catageo-valore catageo-riferimento"><?= Testo::esc(Sezioni::riferimento('AR', $p)) ?></span>
                    <strong><?= Testo::esc(Archeologia::TIPI_EVIDENZA[(string) $evidenza['tipo']] ?? '') ?></strong>
                    <?php if ((string) $evidenza['zonaCavita'] !== ''): ?>
                      <span class="catageo-nota">· <?= Testo::esc((string) $evidenza['zonaCavita']) ?></span>
                    <?php endif; ?>
                  </div>
                  <p class="mb-1"><?= nl2br(Testo::esc((string) $evidenza['descrizione'])) ?></p>

                  <div class="catageo-dati-media">
                    <?php if ((string) $evidenza['periodo'] !== ''): ?>
                      <span><?= Testo::esc($periodoLeggibile((string) $evidenza['periodo'])) ?></span>
                    <?php endif; ?>
                    <?php if ((string) $evidenza['statoConservazione'] !== ''): ?>
                      <span class="catageo-tipo-file">
                        <?= Testo::esc((string) $evidenza['statoConservazione']) ?>
                      </span>
                    <?php endif; ?>
                    <?php foreach (['fotoRif' => 'FO', 'rilievoRif' => 'RI', 'biblioRif' => 'BB'] as $campo => $sigla): ?>
                      <?php if ((string) $evidenza[$campo] !== ''): ?>
                        <span class="catageo-valore"><?= Testo::esc((string) $evidenza[$campo]) ?></span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </div>

                <?php if ($puoCompilare): ?>
                  <div class="d-flex align-items-start gap-1 catageo-non-stampare">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="index.php?p=archeologia&amp;codice=<?= urlencode($codice) ?>&amp;azione=evidenza&amp;prog=<?= $p ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="<?= Testo::esc($ritorno) ?>">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="eliminaEvidenza">
                      <input type="hidden" name="progressivo" value="<?= $p ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit"
                              data-catageo-conferma="Togliere l'evidenza <?= Testo::esc(Sezioni::riferimento('AR', $p)) ?>?">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ----------------------------------------------------- indagini -->
    <div class="card mb-4">
      <div class="card-header"><h2 class="h6 mb-0">Indagini</h2></div>
      <?php if ($indagini !== []): ?>
        <div class="table-responsive">
          <table class="table catageo-tabella mb-0 align-middle">
            <thead>
              <tr>
                <th style="width:7rem">Data</th><th>Tipo</th><th>Soggetto</th>
                <th>Esito</th>
                <?php if ($puoCompilare): ?><th class="text-end"></th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($indagini as $i => $indagine): ?>
                <tr>
                  <td class="catageo-valore"><?= Testo::esc((string) $indagine['data']) ?></td>
                  <td><?= Testo::esc(Archeologia::TIPI_INDAGINE[(string) $indagine['tipo']] ?? '') ?></td>
                  <td>
                    <?= Testo::esc((string) $indagine['soggetto']) ?>
                    <?php if ((string) $indagine['esplorazioneRif'] !== ''): ?>
                      <span class="catageo-valore"><?= Testo::esc((string) $indagine['esplorazioneRif']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="catageo-nota"><?= Testo::esc(Testo::estratto((string) $indagine['esito'], 120)) ?></td>
                  <?php if ($puoCompilare): ?>
                    <td class="text-end">
                      <form method="post" action="<?= Testo::esc($ritorno) ?>">
                        <?= Auth::campoToken() ?>
                        <input type="hidden" name="operazione" value="eliminaIndagine">
                        <input type="hidden" name="posizione" value="<?= $i ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                data-catageo-conferma="Togliere questa indagine?">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($puoCompilare): ?>
        <div class="card-body">
          <form method="post" action="index.php?p=archeologia&amp;codice=<?= urlencode($codice) ?>"
                class="row g-2 align-items-end">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="aggiungiIndagine">

            <div class="col-md-2">
              <label for="tipoIndagine" class="form-label">Tipo</label>
              <select class="form-select form-select-sm" id="tipoIndagine" name="tipo">
                <?php foreach (Archeologia::TIPI_INDAGINE as $valore => $etichetta): ?>
                  <option value="<?= $valore ?>"><?= Testo::esc($etichetta) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label for="dataIndagine" class="form-label">Data</label>
              <input type="date" class="form-control form-control-sm" id="dataIndagine" name="data">
            </div>
            <div class="col-md-3">
              <label for="soggetto" class="form-label">Soggetto</label>
              <input type="text" class="form-control form-control-sm" id="soggetto" name="soggetto">
            </div>
            <div class="col-md-2">
              <label for="esplorazioneRif" class="form-label">Diario</label>
              <select class="form-select form-select-sm" id="esplorazioneRif" name="esplorazioneRif">
                <option value="">—</option>
                <?php foreach (Esplorazioni::elenco($codice) as $diario): ?>
                  <?php $rif = Sezioni::riferimento('ES', (int) $diario['progressivo']); ?>
                  <option value="<?= Testo::esc($rif) ?>"><?= Testo::esc($rif) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label for="esito" class="form-label">Esito</label>
              <input type="text" class="form-control form-control-sm" id="esito" name="esito">
            </div>
            <div class="col-md-1">
              <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-plus-lg"></i>
              </button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- --------------------------------------------------------- tutela -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h2 class="h6 mb-0">
          Tutela
          <?php if ((string) $tutela['vincolo'] === '1'): ?>
            <span class="badge text-bg-warning">vincolata</span>
          <?php endif; ?>
        </h2>
      </div>
      <div class="card-body">
        <?php if ($puoCompilare): ?>
          <form method="post" action="index.php?p=archeologia&amp;codice=<?= urlencode($codice) ?>">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="salvaTutela">

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="vincolo" name="vincolo" value="1"
                     <?= (string) $tutela['vincolo'] === '1' ? 'checked' : '' ?>>
              <label class="form-check-label" for="vincolo">
                Cavità sottoposta a vincolo
              </label>
              <div class="catageo-nota">
                Se spuntato compare un avviso in cima alla scheda.
              </div>
            </div>

            <div class="mb-3">
              <label for="tipoVincolo" class="form-label">Tipo di vincolo</label>
              <input type="text" class="form-control" id="tipoVincolo" name="tipoVincolo"
                     value="<?= Testo::esc((string) $tutela['tipoVincolo']) ?>"
                     placeholder="D.Lgs. 42/2004">
            </div>
            <div class="mb-3">
              <label for="enteCompetente" class="form-label">Ente competente</label>
              <input type="text" class="form-control" id="enteCompetente" name="enteCompetente"
                     value="<?= Testo::esc((string) $tutela['enteCompetente']) ?>">
            </div>
            <div class="row g-2 mb-3">
              <div class="col-7">
                <label for="riferimentoProvvedimento" class="form-label">Provvedimento</label>
                <input type="text" class="form-control" id="riferimentoProvvedimento"
                       name="riferimentoProvvedimento"
                       value="<?= Testo::esc((string) $tutela['riferimentoProvvedimento']) ?>">
              </div>
              <div class="col-5">
                <label for="dataProvvedimento" class="form-label">Data</label>
                <input type="date" class="form-control" id="dataProvvedimento" name="dataProvvedimento"
                       value="<?= Testo::esc((string) $tutela['dataProvvedimento']) ?>">
              </div>
            </div>
            <div class="mb-3">
              <label for="prescrizioni" class="form-label">Prescrizioni</label>
              <textarea class="form-control" id="prescrizioni" name="prescrizioni"
                        rows="4"><?= Testo::esc((string) $tutela['prescrizioni']) ?></textarea>
            </div>
            <div class="mb-3">
              <label for="allegatoRif" class="form-label">Copia del provvedimento</label>
              <select class="form-select" id="allegatoRif" name="allegatoRif">
                <option value="">—</option>
                <?php foreach (Risorse::elenco($codice, 'AL') as $allegato): ?>
                  <?php $rif = Sezioni::riferimento('AL', (int) $allegato['progressivo']); ?>
                  <option value="<?= Testo::esc($rif) ?>"
                    <?= (string) $tutela['allegatoRif'] === $rif ? 'selected' : '' ?>>
                    <?= Testo::esc($rif . ' — ' . (string) $allegato['titolo']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-check-lg"></i> Salva la tutela
            </button>
          </form>

        <?php elseif ((string) $tutela['vincolo'] === '1'): ?>
          <dl class="row catageo-dl mb-0">
            <dt class="col-5 fw-normal text-body-secondary">Tipo</dt>
            <dd class="col-7"><?= Testo::esc((string) $tutela['tipoVincolo']) ?></dd>
            <dt class="col-5 fw-normal text-body-secondary">Ente</dt>
            <dd class="col-7"><?= Testo::esc((string) $tutela['enteCompetente']) ?></dd>
            <dt class="col-5 fw-normal text-body-secondary">Provvedimento</dt>
            <dd class="col-7">
              <?= Testo::esc((string) $tutela['riferimentoProvvedimento']) ?>
              <?= (string) $tutela['dataProvvedimento'] !== ''
                  ? ' del ' . Testo::esc((string) $tutela['dataProvvedimento']) : '' ?>
            </dd>
          </dl>
          <?php if ((string) $tutela['prescrizioni'] !== ''): ?>
            <hr>
            <?= nl2br(Testo::esc((string) $tutela['prescrizioni'])) ?>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-body-secondary mb-0">Nessun vincolo registrato.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
