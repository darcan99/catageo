<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/cataloghi.php
 *  Descrizione ..: Gestione dei cataloghi e delle loro serie di codifica, con
 *                  l'anteprima del codice che verrebbe assegnato.
 *
 *                  L'anteprima non incrementa nessun contatore: le regole di
 *                  codifica si verificano prima di censire, e una prova non deve
 *                  lasciare buchi nella numerazione.
 *  Versione .....: 0.3.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.3.0  2026-08-04  D.Candela  Prima stesura (fase 2b).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('gestisci_cataloghi');

$azione = isset($_GET['azione']) ? (string) $_GET['azione'] : 'elenco';
$sigla  = isset($_GET['sigla']) ? Cataloghi::normalizzaSigla((string) $_GET['sigla']) : '';

// ------------------------------------------------------------------ operazioni

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    $operazione = (string) ($_POST['operazione'] ?? '');
    $siglaPost  = Cataloghi::normalizzaSigla((string) ($_POST['sigla'] ?? ''));
    $ritorno    = 'index.php?p=cataloghi';

    try {
        switch ($operazione) {

            case 'crea':
                $nuova = Cataloghi::crea([
                    'sigla'                 => (string) ($_POST['sigla'] ?? ''),
                    'nome'                  => (string) ($_POST['nome'] ?? ''),
                    'ente'                  => (string) ($_POST['ente'] ?? ''),
                    'descrizione'           => (string) ($_POST['descrizione'] ?? ''),
                    'stato'                 => (string) ($_POST['stato'] ?? 'IT'),
                    'regione'               => (string) ($_POST['regione'] ?? ''),
                    'responsabile'          => (string) ($_POST['responsabile'] ?? ''),
                    'separatore'            => (string) ($_POST['separatore'] ?? ''),
                    'consentiCodiceManuale' => !empty($_POST['consentiCodiceManuale']),
                    'sistemaPreferito'      => (string) ($_POST['sistemaPreferito'] ?? ''),
                    'prefisso'              => (string) ($_POST['prefisso'] ?? ''),
                    'cifre'                 => (int) ($_POST['cifre'] ?? 3),
                ]);
                Log::modifica('crea', $nuova, '', 'cataloghi', 'catalogo creato');
                Auth::messaggio('successo', 'Catalogo creato con la sua prima serie di codifica.');
                $ritorno = 'index.php?p=cataloghi&azione=serie&sigla=' . urlencode($nuova);
                break;

            case 'aggiorna':
                Cataloghi::aggiorna($siglaPost, [
                    'nome'                  => (string) ($_POST['nome'] ?? ''),
                    'ente'                  => (string) ($_POST['ente'] ?? ''),
                    'descrizione'           => (string) ($_POST['descrizione'] ?? ''),
                    'stato'                 => (string) ($_POST['stato'] ?? 'IT'),
                    'regione'               => (string) ($_POST['regione'] ?? ''),
                    'responsabile'          => (string) ($_POST['responsabile'] ?? ''),
                    'separatore'            => (string) ($_POST['separatore'] ?? ''),
                    'consentiCodiceManuale' => !empty($_POST['consentiCodiceManuale']),
                    'sistemaPreferito'      => (string) ($_POST['sistemaPreferito'] ?? ''),
                    'attivo'                => !empty($_POST['attivo']),
                ]);
                Log::modifica('modifica', $siglaPost, '', 'cataloghi', 'identita aggiornata');
                Auth::messaggio('successo', 'Catalogo aggiornato.');
                break;

            case 'elimina':
                Cataloghi::elimina($siglaPost);
                Log::modifica('elimina', $siglaPost, '', 'cataloghi', 'catalogo eliminato');
                Auth::messaggio('successo', 'Catalogo eliminato.');
                break;

            case 'attiva':
                Cataloghi::impostaAttivo($siglaPost);
                Auth::messaggio('info', 'Catalogo attivo: ' . $siglaPost . '.');
                break;

            case 'serie-aggiungi':
                Cataloghi::aggiungiSerie($siglaPost, datiSerieDaPost());
                Log::modifica('modifica', $siglaPost, '', 'cataloghi', 'serie aggiunta: ' . (string) ($_POST['prefisso'] ?? ''));
                Auth::messaggio('successo', 'Serie aggiunta in coda all\'elenco.');
                $ritorno = 'index.php?p=cataloghi&azione=serie&sigla=' . urlencode($siglaPost);
                break;

            case 'serie-aggiorna':
                Cataloghi::aggiornaSerie($siglaPost, (string) ($_POST['prefisso'] ?? ''), datiSerieDaPost());
                Log::modifica('modifica', $siglaPost, '', 'cataloghi', 'serie aggiornata: ' . (string) ($_POST['prefisso'] ?? ''));
                Auth::messaggio('successo', 'Serie aggiornata.');
                $ritorno = 'index.php?p=cataloghi&azione=serie&sigla=' . urlencode($siglaPost);
                break;

            case 'serie-elimina':
                Cataloghi::eliminaSerie($siglaPost, (string) ($_POST['prefisso'] ?? ''));
                Log::modifica('modifica', $siglaPost, '', 'cataloghi', 'serie eliminata: ' . (string) ($_POST['prefisso'] ?? ''));
                Auth::messaggio('successo', 'Serie eliminata.');
                $ritorno = 'index.php?p=cataloghi&azione=serie&sigla=' . urlencode($siglaPost);
                break;

            case 'serie-sposta':
                Cataloghi::spostaSerie(
                    $siglaPost,
                    (string) ($_POST['prefisso'] ?? ''),
                    ((string) ($_POST['direzione'] ?? '')) === 'su' ? -1 : 1
                );
                $ritorno = 'index.php?p=cataloghi&azione=serie&sigla=' . urlencode($siglaPost);
                break;

            default:
                throw new CatalogoEccezione('Operazione non riconosciuta.');
        }
    } catch (CatalogoEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
        if (in_array($operazione, ['serie-aggiungi', 'serie-aggiorna', 'serie-elimina', 'serie-sposta'], true)) {
            $ritorno = 'index.php?p=cataloghi&azione=serie&sigla=' . urlencode($siglaPost);
        } elseif ($operazione === 'crea') {
            $ritorno = 'index.php?p=cataloghi&azione=nuovo';
        }
    }

    header('Location: ' . $ritorno);
    exit;
}

/**
 * Raccoglie i dati di una serie dal POST.
 *
 * Definita come funzione locale perche serve a tre rami dello switch e
 * ripeterla porterebbe a divergenze fra creazione e modifica.
 *
 * @return array<string,mixed>
 */
function datiSerieDaPost(): array
{
    $dati = [
        'prefisso'            => (string) ($_POST['prefisso'] ?? ''),
        'nome'                => (string) ($_POST['nome_serie'] ?? ''),
        'cifre'               => (int) ($_POST['cifre'] ?? 3),
        'prossimoProgressivo' => (string) ($_POST['prossimoProgressivo'] ?? '1'),
    ];
    foreach (CodiceCatastale::CRITERI as $criterio) {
        $dati[$criterio] = (string) ($_POST['criterio_' . $criterio] ?? '');
    }
    return $dati;
}

$elenco      = Cataloghi::elenco();
$siglaAttiva = Cataloghi::siglaAttiva();
?>

<?php if ($azione === 'serie' && $sigla !== '' && Cataloghi::trova($sigla) !== null): ?>

  <?php
  $catalogo   = Cataloghi::trova($sigla);
  $prefissoMod = isset($_GET['modifica']) ? (string) $_GET['modifica'] : '';
  $serieMod    = null;
  foreach ($catalogo['serie'] as $s) {
      if (strcasecmp((string) $s['prefisso'], $prefissoMod) === 0) {
          $serieMod = $s;
      }
  }

  // Dati per l'anteprima: presi dalla querystring, cosi il risultato e
  // condivisibile e ricaricabile.
  $prova = [];
  foreach (CodiceCatastale::CRITERI as $criterio) {
      $prova[$criterio] = isset($_GET['prova_' . $criterio]) ? (string) $_GET['prova_' . $criterio] : '';
  }
  $anteprima = array_filter($prova) !== [] || isset($_GET['anteprima'])
      ? CodiceCatastale::anteprima($sigla, $prova)
      : null;
  ?>

  <div class="catageo-intestazione">
    <div>
      <h1>Serie di codifica</h1>
      <p class="text-body-secondary mb-0">
        Catalogo <span class="catageo-codice"><?= Testo::esc((string) $catalogo['sigla']) ?></span>
        — <?= Testo::esc((string) $catalogo['nome']) ?>
      </p>
    </div>
    <a class="btn btn-outline-secondary" href="index.php?p=cataloghi">
      <i class="bi bi-arrow-left"></i> Torna ai cataloghi
    </a>
  </div>

  <div class="alert alert-info d-flex align-items-start gap-2">
    <i class="bi bi-info-circle-fill mt-1" aria-hidden="true"></i>
    <div>
      <strong>L'ordine conta</strong>: vince la prima serie i cui criteri sono tutti
      soddisfatti dall'ipogeo. Le serie specifiche vanno quindi sopra quelle
      generiche, e in fondo conviene tenerne una <em>senza criteri</em>, che faccia
      da caso generale: senza di essa un ipogeo che non rientra in nessuna serie
      non potrebbe essere censito.
    </div>
  </div>

  <div class="row g-4">
    <div class="col-xl-7">
      <div class="card">
        <div class="card-header">
          <h2 class="h6 mb-0">Serie del catalogo — <?= count($catalogo['serie']) ?></h2>
        </div>
        <div class="table-responsive">
          <table class="table table-sm catageo-tabella mb-0 align-middle">
            <thead>
              <tr>
                <th scope="col" style="width:3rem">#</th>
                <th scope="col">Prefisso</th>
                <th scope="col">Criteri</th>
                <th scope="col">Cifre</th>
                <th scope="col">Prossimo</th>
                <th scope="col">Esempio</th>
                <th scope="col" class="text-end">Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($catalogo['serie'] as $i => $s): ?>
                <tr>
                  <td class="text-body-secondary"><?= $i + 1 ?></td>
                  <td>
                    <span class="catageo-codice"><?= Testo::esc((string) $s['prefisso']) ?></span>
                    <?php if ((string) $s['nome'] !== ''): ?>
                      <div class="small text-body-secondary"><?= Testo::esc((string) $s['nome']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="small">
                    <?php if ($s['criteri'] === []): ?>
                      <span class="badge text-bg-secondary">caso generale</span>
                    <?php else: ?>
                      <?php foreach ($s['criteri'] as $criterio => $valore): ?>
                        <div>
                          <span class="text-body-secondary"><?= Testo::esc(CodiceCatastale::ETICHETTE_CRITERI[$criterio] ?? $criterio) ?>:</span>
                          <span class="catageo-valore"><?= Testo::esc((string) $valore) ?></span>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </td>
                  <td class="catageo-valore"><?= (int) $s['cifre'] ?></td>
                  <td class="catageo-valore"><?= (int) $s['prossimoProgressivo'] ?></td>
                  <td class="catageo-codice">
                    <?= Testo::esc(CodiceCatastale::componi(
                        (string) $s['prefisso'],
                        (int) $s['prossimoProgressivo'],
                        (int) $s['cifre'],
                        (string) $catalogo['separatore']
                    )) ?>
                  </td>
                  <td class="text-end text-nowrap">
                    <form method="post" action="index.php?p=cataloghi" class="d-inline">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="serie-sposta">
                      <input type="hidden" name="sigla" value="<?= Testo::esc((string) $catalogo['sigla']) ?>">
                      <input type="hidden" name="prefisso" value="<?= Testo::esc((string) $s['prefisso']) ?>">
                      <input type="hidden" name="direzione" value="su">
                      <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sposta su"
                              <?= $i === 0 ? 'disabled' : '' ?>><i class="bi bi-arrow-up"></i></button>
                    </form>
                    <form method="post" action="index.php?p=cataloghi" class="d-inline">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="serie-sposta">
                      <input type="hidden" name="sigla" value="<?= Testo::esc((string) $catalogo['sigla']) ?>">
                      <input type="hidden" name="prefisso" value="<?= Testo::esc((string) $s['prefisso']) ?>">
                      <input type="hidden" name="direzione" value="giu">
                      <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sposta giu"
                              <?= $i === count($catalogo['serie']) - 1 ? 'disabled' : '' ?>><i class="bi bi-arrow-down"></i></button>
                    </form>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="index.php?p=cataloghi&amp;azione=serie&amp;sigla=<?= urlencode((string) $catalogo['sigla']) ?>&amp;modifica=<?= urlencode((string) $s['prefisso']) ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?p=cataloghi" class="d-inline">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="serie-elimina">
                      <input type="hidden" name="sigla" value="<?= Testo::esc((string) $catalogo['sigla']) ?>">
                      <input type="hidden" name="prefisso" value="<?= Testo::esc((string) $s['prefisso']) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger"
                              data-catageo-conferma="Eliminare la serie <?= Testo::esc((string) $s['prefisso']) ?>? Verra rifiutato se ha gia numerato dei codici.">
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

      <div class="card mt-4">
        <div class="card-header">
          <h2 class="h6 mb-0">
            <i class="bi bi-magic"></i> Anteprima del codice
          </h2>
        </div>
        <div class="card-body">
          <p class="catageo-nota">
            Compila i dati come li avrebbe un ipogeo e verifica quale serie vince
            e quale codice ne uscirebbe. <strong>Nessun contatore viene toccato.</strong>
          </p>

          <form method="get" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="p" value="cataloghi">
            <input type="hidden" name="azione" value="serie">
            <input type="hidden" name="sigla" value="<?= Testo::esc((string) $catalogo['sigla']) ?>">
            <input type="hidden" name="anteprima" value="1">

            <div class="col-md-4">
              <label for="prova_natura" class="form-label">Natura</label>
              <select class="form-select form-select-sm" id="prova_natura" name="prova_natura">
                <option value="">—</option>
                <?php foreach (Tipologie::perLivello('natura', '', true) as $n): ?>
                  <option value="<?= Testo::esc($n['codice']) ?>" <?= $prova['natura'] === $n['codice'] ? 'selected' : '' ?>>
                    <?= Testo::esc($n['codice'] . ' — ' . $n['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label for="prova_tipologia" class="form-label">Tipologia</label>
              <select class="form-select form-select-sm" id="prova_tipologia" name="prova_tipologia">
                <option value="">—</option>
                <?php foreach (Tipologie::perLivello('tipologia', '', true) as $t): ?>
                  <option value="<?= Testo::esc($t['codice']) ?>" <?= $prova['tipologia'] === $t['codice'] ? 'selected' : '' ?>>
                    <?= Testo::esc($t['codice'] . ' — ' . $t['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label for="prova_sottotipologia" class="form-label">Sottotipologia</label>
              <select class="form-select form-select-sm" id="prova_sottotipologia" name="prova_sottotipologia">
                <option value="">—</option>
                <?php foreach (Tipologie::perLivello('sotto', '', true) as $t): ?>
                  <option value="<?= Testo::esc($t['codice']) ?>" <?= $prova['sottotipologia'] === $t['codice'] ? 'selected' : '' ?>>
                    <?= Testo::esc($t['codice']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label for="prova_stato" class="form-label">Stato</label>
              <input type="text" class="form-control form-control-sm catageo-valore" id="prova_stato"
                     name="prova_stato" maxlength="2" placeholder="IT" value="<?= Testo::esc($prova['stato']) ?>">
            </div>
            <div class="col-md-4">
              <label for="prova_regione" class="form-label">Regione</label>
              <input type="text" class="form-control form-control-sm" id="prova_regione"
                     name="prova_regione" maxlength="60" value="<?= Testo::esc($prova['regione']) ?>">
            </div>
            <div class="col-md-2">
              <label for="prova_provincia" class="form-label">Provincia</label>
              <input type="text" class="form-control form-control-sm catageo-valore" id="prova_provincia"
                     name="prova_provincia" maxlength="2" value="<?= Testo::esc($prova['provincia']) ?>">
            </div>
            <div class="col-md-3">
              <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-play-fill"></i> Calcola
              </button>
            </div>
          </form>

          <?php if ($anteprima !== null): ?>
            <hr>
            <?php if ($anteprima['ok']): ?>
              <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                  <div class="catageo-nota">Serie applicata</div>
                  <div class="catageo-codice fs-6"><?= Testo::esc((string) $anteprima['serie']['prefisso']) ?></div>
                  <div class="small text-body-secondary"><?= Testo::esc((string) $anteprima['serie']['nome']) ?></div>
                </div>
                <i class="bi bi-arrow-right text-body-tertiary fs-4" aria-hidden="true"></i>
                <div>
                  <div class="catageo-nota">Codice che verrebbe assegnato</div>
                  <div class="catageo-codice fs-4 text-primary"><?= Testo::esc($anteprima['codice']) ?></div>
                </div>
              </div>
              <?php if ($anteprima['messaggio'] !== ''): ?>
                <div class="alert alert-warning mt-3 mb-0 py-2 small">
                  <i class="bi bi-exclamation-triangle-fill"></i> <?= Testo::esc($anteprima['messaggio']) ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="alert alert-danger mb-0 py-2">
                <i class="bi bi-x-octagon-fill"></i> <?= Testo::esc($anteprima['messaggio']) ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card">
        <div class="card-header">
          <h2 class="h6 mb-0">
            <?= $serieMod !== null ? 'Modifica serie ' . Testo::esc((string) $serieMod['prefisso']) : 'Nuova serie' ?>
          </h2>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?p=cataloghi" class="needs-validation" novalidate>
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="<?= $serieMod !== null ? 'serie-aggiorna' : 'serie-aggiungi' ?>">
            <input type="hidden" name="sigla" value="<?= Testo::esc((string) $catalogo['sigla']) ?>">

            <div class="row g-3">
              <div class="col-7">
                <label for="prefisso" class="form-label">Prefisso <span class="text-danger">*</span></label>
                <input type="text" class="form-control catageo-codice" id="prefisso" name="prefisso" required
                       maxlength="30" placeholder="LA-AC"
                       value="<?= Testo::esc($serieMod !== null ? (string) $serieMod['prefisso'] : '') ?>"
                       <?= $serieMod !== null ? 'readonly' : '' ?>>
                <?php if ($serieMod !== null): ?>
                  <div class="catageo-nota">Non modificabile: compare nei codici già assegnati.</div>
                <?php endif; ?>
              </div>
              <div class="col-5">
                <label for="cifre" class="form-label">Cifre <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="cifre" name="cifre" required
                       min="0" max="<?= CodiceCatastale::MAX_CIFRE ?>"
                       value="<?= $serieMod !== null ? (int) $serieMod['cifre'] : 3 ?>">
              </div>

              <div class="col-12">
                <label for="nome_serie" class="form-label">Descrizione della serie</label>
                <input type="text" class="form-control" id="nome_serie" name="nome_serie" maxlength="120"
                       placeholder="Lazio — cavità artificiali"
                       value="<?= Testo::esc($serieMod !== null ? (string) $serieMod['nome'] : '') ?>">
              </div>

              <div class="col-12">
                <label for="prossimoProgressivo" class="form-label">Prossimo progressivo <span class="text-danger">*</span></label>
                <input type="text" class="form-control catageo-valore" id="prossimoProgressivo"
                       name="prossimoProgressivo" required pattern="[0-9]+"
                       value="<?= $serieMod !== null ? (int) $serieMod['prossimoProgressivo'] : 1 ?>">
                <div class="catageo-nota">
                  Nessun tetto oltre l'intero della piattaforma
                  (<?= number_format(PHP_INT_MAX, 0, ',', '.') ?>).
                </div>
              </div>

              <div class="col-12">
                <div class="border rounded p-2 bg-body-tertiary">
                  <div class="catageo-nota mb-1">
                    Il numero di cifre e una <strong>soglia minima</strong>, non un tetto:
                  </div>
                  <?php
                  $cifreEsempio = $serieMod !== null ? (int) $serieMod['cifre'] : 3;
                  $prefEsempio  = $serieMod !== null ? (string) $serieMod['prefisso'] : 'XX';
                  ?>
                  <div class="d-flex flex-wrap gap-3">
                    <?php foreach (CodiceCatastale::esempiPadding($prefEsempio, $cifreEsempio, (string) $catalogo['separatore']) as $esempio): ?>
                      <div class="small">
                        <span class="text-body-secondary"><?= (int) $esempio['progressivo'] ?> →</span>
                        <span class="catageo-codice"><?= Testo::esc($esempio['codice']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>

            <hr class="my-3">
            <h3 class="h6">Criteri</h3>
            <p class="catageo-nota">
              Lasciare vuoto un criterio significa "qualsiasi". Più valori si
              separano con la barra verticale, es. <span class="catageo-valore">Lazio|Umbria</span>.
              Una serie senza criteri combacia sempre.
            </p>

            <div class="row g-2">
              <?php foreach (CodiceCatastale::CRITERI as $criterio): ?>
                <div class="col-md-6">
                  <label for="criterio_<?= $criterio ?>" class="form-label small">
                    <?= Testo::esc(CodiceCatastale::ETICHETTE_CRITERI[$criterio]) ?>
                  </label>
                  <input type="text" class="form-control form-control-sm catageo-valore"
                         id="criterio_<?= $criterio ?>" name="criterio_<?= $criterio ?>" maxlength="120"
                         value="<?= Testo::esc($serieMod !== null ? (string) ($serieMod['criteri'][$criterio] ?? '') : '') ?>">
                </div>
              <?php endforeach; ?>
            </div>

            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg"></i> <?= $serieMod !== null ? 'Salva serie' : 'Aggiungi serie' ?>
              </button>
              <?php if ($serieMod !== null): ?>
                <a class="btn btn-outline-secondary btn-sm"
                   href="index.php?p=cataloghi&amp;azione=serie&amp;sigla=<?= urlencode((string) $catalogo['sigla']) ?>">Annulla</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($azione === 'nuovo' || ($azione === 'modifica' && Cataloghi::trova($sigla) !== null)): ?>

  <?php
  $m = $azione === 'modifica' ? Cataloghi::trova($sigla) : null;
  $v = static fn (string $c, string $default = ''): string => Testo::esc((string) ($m[$c] ?? ($_POST[$c] ?? $default)));
  ?>

  <div class="catageo-intestazione">
    <h1><?= $m !== null ? 'Modifica catalogo ' . Testo::esc((string) $m['sigla']) : 'Nuovo catalogo' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php?p=cataloghi">
      <i class="bi bi-arrow-left"></i> Torna ai cataloghi
    </a>
  </div>

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body">
          <form method="post" action="index.php?p=cataloghi" class="needs-validation" novalidate>
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="<?= $m !== null ? 'aggiorna' : 'crea' ?>">
            <?php if ($m !== null): ?>
              <input type="hidden" name="sigla" value="<?= Testo::esc((string) $m['sigla']) ?>">
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-md-3">
                <label for="siglaCampo" class="form-label">Sigla <span class="text-danger">*</span></label>
                <?php if ($m !== null): ?>
                  <input type="text" class="form-control catageo-codice" id="siglaCampo"
                         value="<?= Testo::esc((string) $m['sigla']) ?>" disabled>
                  <div class="catageo-nota">Non modificabile: e nel nome della cartella e nei codici.</div>
                <?php else: ?>
                  <input type="text" class="form-control catageo-codice" id="siglaCampo" name="sigla" required
                         maxlength="40" placeholder="LA" value="<?= Testo::esc((string) ($_POST['sigla'] ?? '')) ?>">
                  <div class="invalid-feedback">Obbligatoria.</div>
                <?php endif; ?>
              </div>
              <div class="col-md-9">
                <label for="nome" class="form-label">Nome del catalogo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome" required maxlength="120"
                       placeholder="Catasto Ipogei del Lazio" value="<?= $v('nome') ?>">
                <div class="invalid-feedback">Obbligatorio.</div>
              </div>

              <div class="col-md-6">
                <label for="ente" class="form-label">Ente o gruppo titolare</label>
                <input type="text" class="form-control" id="ente" name="ente" maxlength="150" value="<?= $v('ente') ?>">
              </div>
              <div class="col-md-6">
                <label for="responsabile" class="form-label">Responsabile</label>
                <select class="form-select" id="responsabile" name="responsabile">
                  <option value="">—</option>
                  <?php foreach (Esploratori::elenco(true) as $e): ?>
                    <option value="<?= Testo::esc((string) $e['id']) ?>"
                      <?= ($m !== null && (string) $m['responsabile'] === (string) $e['id']) ? 'selected' : '' ?>>
                      <?= Testo::esc(Esploratori::etichetta($e)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-2">
                <label for="stato" class="form-label">Stato</label>
                <input type="text" class="form-control catageo-valore" id="stato" name="stato" maxlength="2"
                       value="<?= $v('stato', 'IT') ?>">
                <div class="catageo-nota">ISO, 2 lettere.</div>
              </div>
              <div class="col-md-4">
                <label for="regione" class="form-label">Regione di riferimento</label>
                <input type="text" class="form-control" id="regione" name="regione" maxlength="60" value="<?= $v('regione') ?>">
                <div class="catageo-nota">Indicativa, non vincolante.</div>
              </div>
              <div class="col-md-2">
                <label for="separatore" class="form-label">Separatore</label>
                <input type="text" class="form-control catageo-valore" id="separatore" name="separatore" maxlength="3"
                       value="<?= $v('separatore') ?>">
                <div class="catageo-nota">Fra prefisso e numero.</div>
              </div>

              <div class="col-md-6">
                <label for="sistemaPreferito" class="form-label">Notazione preferita per le posizioni</label>
                <select class="form-select" id="sistemaPreferito" name="sistemaPreferito">
                  <option value="">Gradi decimali e UTM del fuso del punto</option>
                  <?php foreach (Coordinate::sistemi(true) as $codiceSis => $datiSis): ?>
                    <?php if (Coordinate::inGradi($codiceSis)) { continue; } ?>
                    <option value="<?= Testo::esc($codiceSis) ?>"
                      <?= (string) ($m['sistemaPreferito'] ?? ($_POST['sistemaPreferito'] ?? '')) === $codiceSis ? 'selected' : '' ?>>
                      <?= Testo::esc((string) $datiSis['nome']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="catageo-nota">
                  La notazione con cui questo catasto e abituato a scrivere le
                  posizioni: compare per prima nelle schede e nelle stampe. Il
                  catasto del Lazio lavora in UTM WGS84 33N.
                </div>
              </div>

              <div class="col-12">
                <label for="descrizione" class="form-label">Descrizione</label>
                <textarea class="form-control" id="descrizione" name="descrizione" rows="3"><?= Testo::esc((string) ($m['descrizione'] ?? ($_POST['descrizione'] ?? ''))) ?></textarea>
              </div>

              <?php if ($m === null): ?>
                <div class="col-12"><hr class="my-2"></div>
                <div class="col-12">
                  <h3 class="h6">Prima serie di codifica</h3>
                  <p class="catageo-nota">
                    Un catalogo senza serie non potrebbe assegnare codici, quindi
                    ne viene creata una subito, senza criteri. Le altre si
                    aggiungono dopo, con l'anteprima per verificarle.
                  </p>
                </div>
                <div class="col-md-4">
                  <label for="prefisso" class="form-label">Prefisso <span class="text-danger">*</span></label>
                  <input type="text" class="form-control catageo-codice" id="prefisso" name="prefisso" required
                         maxlength="30" placeholder="LA" value="<?= Testo::esc((string) ($_POST['prefisso'] ?? '')) ?>">
                </div>
                <div class="col-md-3">
                  <label for="cifre" class="form-label">Cifre <span class="text-danger">*</span></label>
                  <input type="number" class="form-control" id="cifre" name="cifre" required min="0"
                         max="<?= CodiceCatastale::MAX_CIFRE ?>" value="<?= (int) ($_POST['cifre'] ?? 3) ?>">
                </div>
              <?php endif; ?>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="consentiCodiceManuale"
                         name="consentiCodiceManuale" value="1"
                         <?= ($m !== null && $m['consentiCodiceManuale']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="consentiCodiceManuale">
                    Consenti l'inserimento manuale del codice
                  </label>
                </div>
                <div class="catageo-nota">
                  Indispensabile per importare un catasto esistente conservandone
                  la numerazione. Il contatore della serie viene allineato in
                  avanti, mai indietro.
                </div>
              </div>

              <?php if ($m !== null): ?>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="attivo" name="attivo" value="1"
                           <?= $m['attivo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="attivo">Catalogo attivo</label>
                  </div>
                  <div class="catageo-nota">Un catalogo disattivato resta consultabile ma non accetta nuovi censimenti.</div>
                </div>
              <?php endif; ?>
            </div>

            <hr class="my-4">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> <?= $m !== null ? 'Salva catalogo' : 'Crea catalogo' ?>
              </button>
              <a class="btn btn-outline-secondary" href="index.php?p=cataloghi">Annulla</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="catageo-intestazione">
    <div>
      <h1>Cataloghi</h1>
      <p class="text-body-secondary mb-0">
        <?= count($elenco) ?> catalogh<?= count($elenco) === 1 ? 'o' : 'i' ?> ·
        codici registrati: <?= IndiceCodici::conta() ?>
        <?php if (IndiceCodici::conta(IndiceCodici::STORICO) > 0): ?>
          (<?= IndiceCodici::conta(IndiceCodici::STORICO) ?> storici)
        <?php endif; ?>
      </p>
    </div>
    <a class="btn btn-primary" href="index.php?p=cataloghi&amp;azione=nuovo">
      <i class="bi bi-plus-lg"></i> Nuovo catalogo
    </a>
  </div>

  <?php if ($elenco === []): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="bi bi-collection fs-1 text-body-tertiary" aria-hidden="true"></i>
        <p class="mt-3 mb-3 text-body-secondary">Nessun catalogo nell'archivio.</p>
        <a class="btn btn-primary" href="index.php?p=cataloghi&amp;azione=nuovo">
          <i class="bi bi-plus-lg"></i> Crea il primo catalogo
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($elenco as $c): ?>
        <div class="col-lg-6">
          <div class="card h-100 <?= (string) $c['sigla'] === $siglaAttiva ? 'border-primary' : '' ?>">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <span class="catageo-codice fs-5"><?= Testo::esc((string) $c['sigla']) ?></span>
                  <?php if ((string) $c['sigla'] === $siglaAttiva): ?>
                    <span class="badge text-bg-primary ms-1">attivo</span>
                  <?php endif; ?>
                  <?php if (!$c['attivo']): ?>
                    <span class="badge text-bg-secondary ms-1">disattivato</span>
                  <?php endif; ?>
                  <div class="fw-semibold"><?= Testo::esc((string) $c['nome']) ?></div>
                  <?php if ((string) $c['ente'] !== ''): ?>
                    <div class="small text-body-secondary"><?= Testo::esc((string) $c['ente']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="text-end small text-body-secondary">
                  <div><?= (int) $c['numeroIpogei'] ?> ipogei</div>
                  <div><?= count($c['serie']) ?> serie</div>
                </div>
              </div>

              <div class="small text-body-secondary mb-2">
                <i class="bi bi-folder2"></i>
                <span class="catageo-valore"><?= Testo::esc((string) $c['cartella']) ?></span>
              </div>

              <div class="mb-3">
                <?php foreach ($c['serie'] as $s): ?>
                  <span class="badge text-bg-light border catageo-codice" title="<?= Testo::esc((string) $s['nome']) ?>">
                    <?= Testo::esc((string) $s['prefisso']) ?><?= $s['criteri'] === [] ? ' *' : '' ?>
                  </span>
                <?php endforeach; ?>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-primary"
                   href="index.php?p=cataloghi&amp;azione=serie&amp;sigla=<?= urlencode((string) $c['sigla']) ?>">
                  <i class="bi bi-123"></i> Serie e anteprima
                </a>
                <a class="btn btn-sm btn-outline-secondary"
                   href="index.php?p=cataloghi&amp;azione=modifica&amp;sigla=<?= urlencode((string) $c['sigla']) ?>">
                  <i class="bi bi-pencil"></i> Modifica
                </a>
                <?php if ((string) $c['sigla'] !== $siglaAttiva): ?>
                  <form method="post" action="index.php?p=cataloghi" class="d-inline">
                    <?= Auth::campoToken() ?>
                    <input type="hidden" name="operazione" value="attiva">
                    <input type="hidden" name="sigla" value="<?= Testo::esc((string) $c['sigla']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-check2-circle"></i> Rendi attivo
                    </button>
                  </form>
                <?php endif; ?>
                <form method="post" action="index.php?p=cataloghi" class="d-inline">
                  <?= Auth::campoToken() ?>
                  <input type="hidden" name="operazione" value="elimina">
                  <input type="hidden" name="sigla" value="<?= Testo::esc((string) $c['sigla']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"
                          data-catageo-conferma="Eliminare il catalogo <?= Testo::esc((string) $c['sigla']) ?>? Verra rifiutato se contiene ipogei.">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="catageo-nota mt-3">
      L'asterisco accanto a un prefisso indica la serie senza criteri, quella che
      fa da caso generale.
    </p>
  <?php endif; ?>

<?php endif; ?>
