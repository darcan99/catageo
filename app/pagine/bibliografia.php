<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/bibliografia.php
 *  Descrizione ..: Bibliografia di un ipogeo: elenco, redazione delle tre
 *                  forme di voce, export BibTeX (9.7).
 *
 *                  Il modulo cambia forma secondo il tipo scelto, ma i campi
 *                  restano tutti nel DOM: nasconderli con CSS li lascia
 *                  raggiungibili senza JavaScript, e il server scrive comunque
 *                  solo i campi che il tipo prevede. Chi ha il browser senza
 *                  script vede un modulo lungo, non un modulo rotto.
 *  Versione .....: 0.10.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.10.0  2026-08-06  D.Candela  Prima stesura (fase 7b).
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

$nomeIpogeo = (string) $scheda['identificazione']['nome'];
$puoCompilare = Auth::puo('compila_sezioni');
$ritorno = 'index.php?p=bibliografia&codice=' . urlencode($codice);

// ============================================================================
//  OPERAZIONI
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    Auth::esigi('compila_sezioni');

    $operazione = (string) ($_POST['operazione'] ?? '');

    try {
        if ($operazione === 'elimina') {
            Bibliografia::elimina($codice, (int) ($_POST['progressivo'] ?? 0));
            IndiceIpogei::aggiorna($codice);
            Auth::messaggio('successo', 'Voce bibliografica rimossa.');
            header('Location: ' . $ritorno);
            exit;
        }

        if ($operazione === 'verifica') {
            Bibliografia::registraVerifica(
                $codice,
                (int) ($_POST['progressivo'] ?? 0),
                (string) ($_POST['esito'] ?? 'non verificato')
            );
            Auth::messaggio('successo', 'Esito della verifica registrato.');
            header('Location: ' . $ritorno);
            exit;
        }

        $dati = ['tipo' => (string) ($_POST['tipo'] ?? 'inline')];
        foreach (array_keys(Bibliografia::CAMPI) as $campo) {
            if ($campo === 'tipo' || $campo === 'esitoVerifica' || $campo === 'ultimaVerifica') {
                continue;
            }
            $dati[$campo] = (string) ($_POST[$campo] ?? '');
        }

        if ($operazione === 'aggiungi') {
            $nuovo = Bibliografia::aggiungi($codice, $dati);
            IndiceIpogei::aggiorna($codice);
            Auth::messaggio('successo',
                'Voce ' . Sezioni::riferimento('BB', $nuovo) . ' aggiunta.');
            header('Location: ' . $ritorno);
            exit;
        }

        if ($operazione === 'aggiorna') {
            $p = (int) ($_POST['progressivo'] ?? 0);

            // La verifica gia registrata non deve essere cancellata da una
            // modifica del testo: si ripristina prima di riscrivere.
            $precedente = Bibliografia::trova($codice, $p);
            if ($precedente !== null) {
                $dati['ultimaVerifica'] = (string) $precedente['ultimaVerifica'];
                $dati['esitoVerifica']  = (string) $precedente['esitoVerifica'];
            }

            Bibliografia::aggiorna($codice, $p, $dati);
            IndiceIpogei::aggiorna($codice);
            Auth::messaggio('successo', 'Voce aggiornata.');
            header('Location: ' . $ritorno);
            exit;
        }

        Auth::messaggio('errore', 'Operazione non riconosciuta.');
    } catch (BibliografiaEccezione | IpogeoEccezione | XmlEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: ' . $ritorno);
    exit;
}

// ============================================================================
//  MODULO
// ============================================================================

if (($azione === 'nuova' || ($azione === 'modifica' && $prog > 0)) && $puoCompilare) {
    $modifica = $azione === 'modifica';
    $voce = $modifica ? Bibliografia::trova($codice, $prog) : null;

    if ($modifica && $voce === null) {
        Auth::messaggio('errore', 'Voce non trovata.');
        header('Location: ' . $ritorno);
        exit;
    }

    if ($voce === null) {
        $voce = Bibliografia::CAMPI;
        $voce['tipo'] = (string) ($_GET['tipo'] ?? 'riferimento');
        if (!isset(Bibliografia::TIPI[$voce['tipo']])) {
            $voce['tipo'] = 'riferimento';
        }
    }

    $opereDisponibili = array_values(array_filter(
        Opere::elenco(),
        // Un'opera disattivata non si propone piu, ma se e gia citata da questa
        // voce deve restare selezionabile: altrimenti salvare la voce la
        // perderebbe senza dirlo.
        static fn (array $o): bool => !empty($o['attivo']) || (string) $o['id'] === (string) $voce['operaId']
    ));

    $titolo = ($modifica ? 'Modifica voce' : 'Nuova voce bibliografica') . ' — ' . $codice;
    $jsPagina = ['assets/js/catageo-bibliografia.js'];
    $v = static fn (string $c): string => Testo::esc((string) ($voce[$c] ?? ''));
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1><?= $modifica ? 'Modifica voce' : 'Nuova voce bibliografica' ?></h1>
        <p class="text-body-secondary mb-0">
          <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
        </p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Testo::esc($ritorno) ?>">
        <i class="bi bi-x-lg"></i> Annulla
      </a>
    </div>

    <form method="post" action="index.php?p=bibliografia&amp;codice=<?= urlencode($codice) ?>"
          class="needs-validation" novalidate>
      <?= Auth::campoToken() ?>
      <input type="hidden" name="operazione" value="<?= $modifica ? 'aggiorna' : 'aggiungi' ?>">
      <?php if ($modifica): ?>
        <input type="hidden" name="progressivo" value="<?= $prog ?>">
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-lg-8">

          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Che genere di fonte</h2></div>
            <div class="card-body">
              <?php foreach (Bibliografia::TIPI as $valore => $etichetta): ?>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="tipo" id="tipo<?= $valore ?>"
                         value="<?= $valore ?>" data-catageo-tipo-voce
                         <?= (string) $voce['tipo'] === $valore ? 'checked' : '' ?>>
                  <label class="form-check-label" for="tipo<?= $valore ?>">
                    <?= Testo::esc($etichetta) ?>
                    <?php if ($valore === 'riferimento'): ?>
                      <span class="catageo-nota">
                        — da preferire: correggere l'opera vale per tutte le schede che la citano
                      </span>
                    <?php endif; ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- ---------------------------------------------- riferimento -->
          <div class="card mb-4" data-catageo-blocco="riferimento">
            <div class="card-header"><h2 class="h6 mb-0">Opera del catalogo generale</h2></div>
            <div class="card-body">
              <?php if ($opereDisponibili === []): ?>
                <p class="text-body-secondary mb-0">
                  Il catalogo generale e vuoto.
                  <a href="index.php?p=opere&amp;azione=nuovo">Censisci un'opera</a>
                  oppure scegli un altro genere di fonte.
                </p>
              <?php else: ?>
                <div class="row g-3">
                  <div class="col-12">
                    <label for="operaId" class="form-label">Opera</label>
                    <select class="form-select" id="operaId" name="operaId">
                      <option value="">— scegli —</option>
                      <?php foreach ($opereDisponibili as $o): ?>
                        <option value="<?= Testo::esc((string) $o['id']) ?>"
                          <?= (string) $voce['operaId'] === (string) $o['id'] ? 'selected' : '' ?>>
                          <?= Testo::esc((string) $o['id']) ?> — <?= Testo::esc(Opere::citazione($o)) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="pagineRif" class="form-label">Pagine di questa cavità</label>
                    <input type="text" class="form-control" id="pagineRif" name="pagine"
                           value="<?= $v('pagine') ?>" placeholder="112-130">
                    <div class="catageo-nota">Le pagine che parlano di questo ipogeo, non l'estensione dell'opera.</div>
                  </div>
                  <div class="col-md-6">
                    <label for="tavole" class="form-label">Tavole</label>
                    <input type="text" class="form-control" id="tavole" name="tavole"
                           value="<?= $v('tavole') ?>" placeholder="XIV-XVI">
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- ---------------------------------------------------- inline -->
          <div class="card mb-4" data-catageo-blocco="inline">
            <div class="card-header"><h2 class="h6 mb-0">Fonte propria di questo ipogeo</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="tipoOpera" class="form-label">Tipo</label>
                  <select class="form-select" id="tipoOpera" name="tipoOpera">
                    <?php foreach (Opere::TIPI as $valore => $etichetta): ?>
                      <option value="<?= $valore ?>" <?= (string) $voce['tipoOpera'] === $valore ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-8">
                  <label for="autori" class="form-label">Autori</label>
                  <input type="text" class="form-control" id="autori" name="autori" value="<?= $v('autori') ?>"
                         placeholder="Rossi M., Bianchi L.">
                </div>
                <div class="col-md-6">
                  <label for="contenitore" class="form-label">Rivista o volume</label>
                  <input type="text" class="form-control" id="contenitore" name="contenitore"
                         value="<?= $v('contenitore') ?>">
                </div>
                <div class="col-md-3">
                  <label for="volume" class="form-label">Volume</label>
                  <input type="text" class="form-control" id="volume" name="volume" value="<?= $v('volume') ?>">
                </div>
                <div class="col-md-3">
                  <label for="fascicolo" class="form-label">Fascicolo</label>
                  <input type="text" class="form-control" id="fascicolo" name="fascicolo" value="<?= $v('fascicolo') ?>">
                </div>
                <div class="col-md-4">
                  <label for="editore" class="form-label">Editore</label>
                  <input type="text" class="form-control" id="editore" name="editore" value="<?= $v('editore') ?>">
                </div>
                <div class="col-md-3">
                  <label for="luogo" class="form-label">Luogo</label>
                  <input type="text" class="form-control" id="luogo" name="luogo" value="<?= $v('luogo') ?>">
                </div>
                <div class="col-md-2">
                  <label for="anno" class="form-label">Anno</label>
                  <input type="text" class="form-control" id="anno" name="anno" value="<?= $v('anno') ?>"
                         inputmode="numeric" pattern="[0-9]{4}">
                </div>
                <div class="col-md-3">
                  <label for="isbnIssn" class="form-label">ISBN o ISSN</label>
                  <input type="text" class="form-control catageo-valore" id="isbnIssn" name="isbnIssn"
                         value="<?= $v('isbnIssn') ?>">
                </div>
                <div class="col-md-6">
                  <label for="doi" class="form-label">DOI</label>
                  <input type="text" class="form-control catageo-valore" id="doi" name="doi" value="<?= $v('doi') ?>">
                </div>
                <div class="col-md-2">
                  <label for="lingua" class="form-label">Lingua</label>
                  <input type="text" class="form-control" id="lingua" name="lingua" value="<?= $v('lingua') ?>"
                         maxlength="10">
                </div>
                <div class="col-md-4">
                  <label for="allegatoRif" class="form-label">PDF fra gli allegati</label>
                  <select class="form-select" id="allegatoRif" name="allegatoRif">
                    <option value="">— nessuno —</option>
                    <?php foreach (Risorse::elenco($codice, 'AL') as $allegato): ?>
                      <?php $rif = Sezioni::riferimento('AL', (int) $allegato['progressivo']); ?>
                      <option value="<?= Testo::esc($rif) ?>"
                        <?= (string) $voce['allegatoRif'] === $rif ? 'selected' : '' ?>>
                        <?= Testo::esc($rif) ?> — <?= Testo::esc((string) $allegato['titolo']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label for="abstract" class="form-label">Abstract</label>
                  <textarea class="form-control" id="abstract" name="abstract" rows="3"><?= $v('abstract') ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <!-- ------------------------------------------------------ link -->
          <div class="card mb-4" data-catageo-blocco="link">
            <div class="card-header"><h2 class="h6 mb-0">Risorsa in rete</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-12">
                  <label for="url" class="form-label">Indirizzo</label>
                  <input type="url" class="form-control" id="url" name="url" value="<?= $v('url') ?>"
                         placeholder="https://esempio.it/catasto/…">
                  <div class="catageo-nota">Sono ammessi solo http e https.</div>
                </div>
                <div class="col-md-7">
                  <label for="ente" class="form-label">Ente che la pubblica</label>
                  <input type="text" class="form-control" id="ente" name="ente" value="<?= $v('ente') ?>">
                </div>
                <div class="col-md-5">
                  <label for="dataConsultazione" class="form-label">Data di consultazione</label>
                  <input type="date" class="form-control" id="dataConsultazione" name="dataConsultazione"
                         value="<?= $v('dataConsultazione') ?>">
                </div>
                <div class="col-12">
                  <label for="copiaArchiviata" class="form-label">Copia archiviata fra gli allegati</label>
                  <select class="form-select" id="copiaArchiviata" name="copiaArchiviata">
                    <option value="">— nessuna —</option>
                    <?php foreach (Risorse::elenco($codice, 'AL') as $allegato): ?>
                      <?php $rif = Sezioni::riferimento('AL', (int) $allegato['progressivo']); ?>
                      <option value="<?= Testo::esc($rif) ?>"
                        <?= (string) $voce['copiaArchiviata'] === $rif ? 'selected' : '' ?>>
                        <?= Testo::esc($rif) ?> — <?= Testo::esc((string) $allegato['titolo']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="catageo-nota">
                    I collegamenti si rompono in pochi anni e un catasto vive più a lungo:
                    di ciò che conta conviene archiviare una copia.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">Comune a ogni fonte</h2></div>
            <div class="card-body">
              <div class="mb-3" data-catageo-blocco="inline link">
                <label for="titolo" class="form-label">Titolo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="titolo" name="titolo" value="<?= $v('titolo') ?>">
              </div>

              <div class="mb-3">
                <label for="rilevanza" class="form-label">Rilevanza per questo ipogeo</label>
                <select class="form-select" id="rilevanza" name="rilevanza">
                  <?php foreach (Bibliografia::RILEVANZE as $valore => $etichetta): ?>
                    <option value="<?= $valore ?>" <?= (string) $voce['rilevanza'] === $valore ? 'selected' : '' ?>>
                      <?= Testo::esc($etichetta) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="catageo-nota">
                  Distingue la pubblicazione che ha fatto conoscere la cavità da
                  quella che la nomina di sfuggita.
                </div>
              </div>

              <div>
                <label for="note" class="form-label">Note</label>
                <textarea class="form-control" id="note" name="note" rows="4"><?= $v('note') ?></textarea>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i> <?= $modifica ? 'Salva la voce' : 'Aggiungi la voce' ?>
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

$voci = array_map([Bibliografia::class, 'risolvi'], Bibliografia::elenco($codice));
$titolo = 'Bibliografia — ' . $codice;

/** Le voci si presentano per rilevanza: e l'ordine in cui si vogliono leggere. */
$perRilevanza = ['primaria' => [], 'secondaria' => [], 'citazione' => []];
foreach ($voci as $voce) {
    $r = (string) $voce['rilevanza'];
    $perRilevanza[isset($perRilevanza[$r]) ? $r : 'secondaria'][] = $voce;
}
?>

<div class="catageo-intestazione">
  <div>
    <h1>Bibliografia</h1>
    <p class="text-body-secondary mb-0">
      <span class="catageo-codice"><?= Testo::esc($codice) ?></span> <?= Testo::esc($nomeIpogeo) ?>
      · <?= count($voci) ?> voc<?= count($voci) === 1 ? 'e' : 'i' ?>
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($voci !== []): ?>
      <a class="btn btn-outline-secondary" href="index.php?p=bibtex&amp;codice=<?= urlencode($codice) ?>">
        <i class="bi bi-download"></i> BibTeX
      </a>
    <?php endif; ?>
    <?php if ($puoCompilare): ?>
      <a class="btn btn-primary"
         href="index.php?p=bibliografia&amp;codice=<?= urlencode($codice) ?>&amp;azione=nuova">
        <i class="bi bi-plus-lg"></i> Nuova voce
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary"
       href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode($codice) ?>">
      <i class="bi bi-arrow-left"></i> Scheda
    </a>
  </div>
</div>

<?php if ($voci === []): ?>

  <div class="card">
    <div class="card-body d-flex gap-3">
      <i class="bi bi-book fs-3 text-body-secondary" aria-hidden="true"></i>
      <div>
        <h2 class="h6 mb-1">Nessuna fonte registrata</h2>
        <p class="text-body-secondary mb-0">
          Le fonti si registrano in tre forme: un rimando a un'opera del
          <a href="index.php?p=opere">catalogo generale</a>, una fonte propria di
          questo ipogeo, oppure una risorsa in rete.
        </p>
      </div>
    </div>
  </div>

<?php else: ?>

  <?php foreach (Bibliografia::RILEVANZE as $chiave => $etichetta): ?>
    <?php if ($perRilevanza[$chiave] === []) { continue; } ?>

    <div class="card mb-4">
      <div class="card-header">
        <h2 class="h6 mb-0">
          <?= Testo::esc($etichetta) ?>
          <span class="catageo-nota">· <?= count($perRilevanza[$chiave]) ?></span>
        </h2>
      </div>
      <div class="card-body">
        <?php foreach ($perRilevanza[$chiave] as $voce): ?>
          <?php $p = (int) $voce['progressivo']; ?>
          <div class="catageo-voce-biblio">
            <div class="d-flex justify-content-between gap-3">
              <div class="flex-grow-1">
                <div>
                  <span class="catageo-valore catageo-riferimento"><?= Testo::esc(Sezioni::riferimento('BB', $p)) ?></span>
                  <?= Testo::esc(Bibliografia::citazione($voce)) ?>
                </div>

                <div class="catageo-dati-media mt-1">
                  <span class="catageo-tipo-file"><?= Testo::esc((string) $voce['tipo']) ?></span>

                  <?php if ((string) $voce['tipo'] === 'riferimento' && is_array($voce['opera'])): ?>
                    <a href="index.php?p=opere&amp;azione=citazioni&amp;id=<?= urlencode((string) $voce['operaId']) ?>">
                      <?= Testo::esc((string) $voce['operaId']) ?>
                    </a>
                    <?php if ((string) $voce['tavole'] !== ''): ?>
                      <span>tavv. <?= Testo::esc((string) $voce['tavole']) ?></span>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if ((string) $voce['tipo'] === 'link'): ?>
                    <a href="<?= Testo::esc((string) $voce['url']) ?>" target="_blank" rel="noopener noreferrer">
                      <i class="bi bi-box-arrow-up-right"></i> apri
                    </a>
                    <?php if ((string) $voce['esitoVerifica'] !== ''): ?>
                      <span class="<?= (string) $voce['esitoVerifica'] === 'raggiungibile' ? 'text-success' : 'text-danger' ?>">
                        <i class="bi <?= (string) $voce['esitoVerifica'] === 'raggiungibile' ? 'bi-check-circle' : 'bi-exclamation-triangle' ?>"></i>
                        <?= Testo::esc(Bibliografia::ESITI_VERIFICA[(string) $voce['esitoVerifica']] ?? '') ?>
                        il <?= Testo::esc((string) $voce['ultimaVerifica']) ?>
                      </span>
                    <?php endif; ?>
                    <?php if ((string) $voce['copiaArchiviata'] !== ''): ?>
                      <span title="Copia locale archiviata">
                        <i class="bi bi-archive"></i> <?= Testo::esc((string) $voce['copiaArchiviata']) ?>
                      </span>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if ((string) $voce['allegatoRif'] !== ''): ?>
                    <?php
                    $parti = Sezioni::scomponiRiferimento((string) $voce['allegatoRif']);
                    $file  = $parti === null ? null
                        : Risorse::trova($codice, $parti['sigla'], $parti['progressivo']);
                    ?>
                    <?php if ($file !== null): ?>
                      <a href="scarica.php?codice=<?= urlencode($codice) ?>&amp;sez=AL&amp;prog=<?= (int) $file['progressivo'] ?>&amp;inline=1">
                        <i class="bi bi-file-earmark-text"></i> <?= Testo::esc((string) $voce['allegatoRif']) ?>
                      </a>
                    <?php else: ?>
                      <span class="text-danger" title="L'allegato citato non c'è più">
                        <i class="bi bi-exclamation-triangle"></i> <?= Testo::esc((string) $voce['allegatoRif']) ?>
                      </span>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>

                <?php if ((string) $voce['abstract'] !== ''): ?>
                  <p class="catageo-nota mt-2 mb-0"><?= nl2br(Testo::esc((string) $voce['abstract'])) ?></p>
                <?php endif; ?>
                <?php if ((string) $voce['note'] !== ''): ?>
                  <p class="mt-1 mb-0"><em><?= nl2br(Testo::esc((string) $voce['note'])) ?></em></p>
                <?php endif; ?>
              </div>

              <?php if ($puoCompilare): ?>
                <div class="d-flex align-items-start gap-1 catageo-non-stampare">
                  <?php if ((string) $voce['tipo'] === 'link'): ?>
                    <div class="dropdown">
                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                              data-bs-toggle="dropdown" title="Registra l'esito di una verifica">
                        <i class="bi bi-link-45deg"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end p-2">
                        <?php foreach (Bibliografia::ESITI_VERIFICA as $valore => $etichettaEsito): ?>
                          <form method="post" action="<?= Testo::esc($ritorno) ?>">
                            <?= Auth::campoToken() ?>
                            <input type="hidden" name="operazione" value="verifica">
                            <input type="hidden" name="progressivo" value="<?= $p ?>">
                            <input type="hidden" name="esito" value="<?= Testo::esc($valore) ?>">
                            <button class="dropdown-item" type="submit"><?= Testo::esc($etichettaEsito) ?></button>
                          </form>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <a class="btn btn-sm btn-outline-secondary"
                     href="index.php?p=bibliografia&amp;codice=<?= urlencode($codice) ?>&amp;azione=modifica&amp;prog=<?= $p ?>">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="post" action="<?= Testo::esc($ritorno) ?>">
                    <?= Auth::campoToken() ?>
                    <input type="hidden" name="operazione" value="elimina">
                    <input type="hidden" name="progressivo" value="<?= $p ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit"
                            data-catageo-conferma="Togliere la voce <?= Testo::esc(Sezioni::riferimento('BB', $p)) ?>?">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

<?php endif; ?>
