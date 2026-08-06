<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/opere.php
 *  Descrizione ..: Catalogo generale delle opere: elenco, redazione, citazioni
 *                  ricevute, export BibTeX dell'intero catalogo (9.7).
 *
 *                  Un'opera censita qui puo essere citata da qualunque scheda.
 *                  L'elenco degli ipogei che la citano non e memorizzato: si
 *                  ricava scorrendo le sezioni, ed e lo stesso conteggio che
 *                  impedisce di cancellare un'opera ancora in uso.
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

Auth::esigi('anagrafiche');

$azione      = isset($_GET['azione']) ? (string) $_GET['azione'] : 'elenco';
$idRichiesto = isset($_GET['id']) ? (string) $_GET['id'] : '';

// ============================================================================
//  OPERAZIONI
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    $operazione = (string) ($_POST['operazione'] ?? '');

    $dati = ['attivo' => !empty($_POST['attivo'])];
    foreach (['tipoOpera', 'autori', 'titolo', 'contenitore', 'editore', 'luogo', 'anno',
              'volume', 'fascicolo', 'pagine', 'isbnIssn', 'doi', 'url', 'lingua',
              'abstract', 'note'] as $campo) {
        $dati[$campo] = (string) ($_POST[$campo] ?? '');
    }

    try {
        switch ($operazione) {
            case 'crea':
                $id = Opere::crea($dati);
                Log::modifica('crea', '', '', 'opere', $id . ' ' . $dati['titolo']);
                Auth::messaggio('successo', 'Opera censita con l\'identificativo ' . $id . '.');
                break;

            case 'aggiorna':
                $id = (string) ($_POST['id'] ?? '');
                Opere::aggiorna($id, $dati);
                Log::modifica('modifica', '', '', 'opere', $id);
                Auth::messaggio('successo', 'Opera aggiornata: la correzione vale per tutte le schede che la citano.');
                break;

            case 'elimina':
                $id = (string) ($_POST['id'] ?? '');
                Opere::elimina($id);
                Log::modifica('elimina', '', '', 'opere', $id);
                Auth::messaggio('successo', 'Opera eliminata dal catalogo generale.');
                break;

            default:
                throw new AnagraficaEccezione('Operazione non riconosciuta.');
        }
        header('Location: index.php?p=opere');
        exit;

    } catch (AnagraficaEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
        $ritorno = match ($operazione) {
            'aggiorna' => 'index.php?p=opere&azione=modifica&id=' . urlencode((string) ($_POST['id'] ?? '')),
            'crea'     => 'index.php?p=opere&azione=nuovo',
            default    => 'index.php?p=opere',
        };
        header('Location: ' . $ritorno);
        exit;
    }
}

// ============================================================================
//  VISTA: citazioni ricevute
// ============================================================================

if ($azione === 'citazioni' && $idRichiesto !== '') {
    $opera = Opere::trova($idRichiesto);
    if ($opera === null) {
        Auth::messaggio('errore', 'Opera non trovata.');
        header('Location: index.php?p=opere');
        exit;
    }

    $citazioni = Opere::citataDa($idRichiesto);
    $titolo = 'Citazioni di ' . $idRichiesto;
    ?>

    <div class="catageo-intestazione">
      <div>
        <h1>Chi cita quest'opera</h1>
        <p class="text-body-secondary mb-0"><?= Testo::esc(Opere::etichetta($opera)) ?></p>
      </div>
      <a class="btn btn-outline-secondary" href="index.php?p=opere">
        <i class="bi bi-arrow-left"></i> Catalogo
      </a>
    </div>

    <div class="card">
      <div class="card-body">
        <?php if ($citazioni === []): ?>
          <p class="text-body-secondary mb-0">
            Nessuna scheda cita quest'opera. Puo essere eliminata dal catalogo.
          </p>
        <?php else: ?>
          <p class="catageo-nota">
            L'elenco non e memorizzato: viene ricavato ora scorrendo le sezioni
            bibliografiche di tutti gli ipogei.
          </p>
          <ul class="list-unstyled mb-0">
            <?php foreach ($citazioni as $c): ?>
              <li class="mb-1">
                <a href="index.php?p=bibliografia&amp;codice=<?= urlencode($c['codice']) ?>">
                  <span class="catageo-codice"><?= Testo::esc($c['codice']) ?></span>
                </a>
                <?= Testo::esc($c['nome']) ?>
                <span class="catageo-nota"><?= Testo::esc(Sezioni::riferimento('BB', $c['progressivo'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <?php
    return;
}

// ============================================================================
//  ELENCO E MODULO
// ============================================================================

$elenco = Opere::elenco();
$inModifica = null;
if ($azione === 'modifica' && $idRichiesto !== '') {
    $inModifica = Opere::trova($idRichiesto);
    if ($inModifica === null) {
        Auth::messaggio('errore', 'Opera non trovata.');
        $azione = 'elenco';
    }
}

$filtro = trim((string) ($_GET['q'] ?? ''));
if ($filtro !== '') {
    $ago = Testo::normalizzaRicerca($filtro);
    $elenco = array_values(array_filter($elenco, static function (array $o) use ($ago): bool {
        $pagliaio = Testo::normalizzaRicerca(
            (string) $o['autori'] . ' ' . (string) $o['titolo'] . ' '
            . (string) $o['contenitore'] . ' ' . (string) $o['anno'] . ' ' . (string) $o['id']);

        return str_contains($pagliaio, $ago);
    }));
}

$titolo = 'Catalogo delle opere';
?>

<div class="catageo-intestazione">
  <div>
    <h1>Catalogo delle opere</h1>
    <p class="text-body-secondary mb-0">
      <?= count($elenco) ?> oper<?= count($elenco) === 1 ? 'a' : 'e' ?>
      <?= $filtro !== '' ? 'che corrispondono alla ricerca' : 'citabili da qualunque scheda' ?> ·
      <a class="link-secondary" href="index.php?p=anagrafiche">tutte le anagrafiche</a>
    </p>
  </div>
  <?php if ($azione === 'elenco'): ?>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="index.php?p=bibtex&amp;tutto=1">
        <i class="bi bi-download"></i> BibTeX
      </a>
      <a class="btn btn-primary" href="index.php?p=opere&amp;azione=nuovo">
        <i class="bi bi-plus-lg"></i> Nuova opera
      </a>
    </div>
  <?php else: ?>
    <a class="btn btn-outline-secondary" href="index.php?p=opere">
      <i class="bi bi-arrow-left"></i> Torna all'elenco
    </a>
  <?php endif; ?>
</div>

<?php if ($azione === 'nuovo' || $azione === 'modifica'): ?>

  <?php
  $m = $inModifica;
  $v = static fn (string $c): string => Testo::esc((string) ($m[$c] ?? ($_POST[$c] ?? '')));
  ?>

  <form method="post" action="index.php?p=opere" class="needs-validation" novalidate>
    <?= Auth::campoToken() ?>
    <input type="hidden" name="operazione" value="<?= $m !== null ? 'aggiorna' : 'crea' ?>">
    <?php if ($m !== null): ?>
      <input type="hidden" name="id" value="<?= Testo::esc((string) $m['id']) ?>">
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card mb-4">
          <div class="card-header"><h2 class="h6 mb-0">L'opera</h2></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label for="tipoOpera" class="form-label">Tipo <span class="text-danger">*</span></label>
                <select class="form-select" id="tipoOpera" name="tipoOpera">
                  <?php $tipoCorrente = (string) ($m['tipoOpera'] ?? ($_POST['tipoOpera'] ?? 'articolo')); ?>
                  <?php foreach (Opere::TIPI as $valore => $etichetta): ?>
                    <option value="<?= $valore ?>" <?= $tipoCorrente === $valore ? 'selected' : '' ?>>
                      <?= Testo::esc($etichetta) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="catageo-nota">Decide come si compone la citazione e l'export BibTeX.</div>
              </div>

              <div class="col-md-8">
                <label for="autori" class="form-label">Autori</label>
                <input type="text" class="form-control" id="autori" name="autori" value="<?= $v('autori') ?>"
                       placeholder="Rossi M., Bianchi L.">
                <div class="catageo-nota">Separati da virgola, nell'ordine in cui compaiono sull'opera.</div>
              </div>

              <div class="col-12">
                <label for="titolo" class="form-label">Titolo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="titolo" name="titolo" required value="<?= $v('titolo') ?>">
              </div>

              <div class="col-md-6">
                <label for="contenitore" class="form-label">Rivista o volume che la contiene</label>
                <input type="text" class="form-control" id="contenitore" name="contenitore"
                       value="<?= $v('contenitore') ?>" placeholder="Speleologia Romana">
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
              <div class="col-md-4">
                <label for="luogo" class="form-label">Luogo</label>
                <input type="text" class="form-control" id="luogo" name="luogo" value="<?= $v('luogo') ?>">
              </div>
              <div class="col-md-2">
                <label for="anno" class="form-label">Anno</label>
                <input type="text" class="form-control" id="anno" name="anno" value="<?= $v('anno') ?>"
                       inputmode="numeric" pattern="[0-9]{4}" placeholder="1998">
              </div>
              <div class="col-md-2">
                <label for="pagine" class="form-label">Pagine</label>
                <input type="text" class="form-control" id="pagine" name="pagine" value="<?= $v('pagine') ?>"
                       placeholder="12-30">
              </div>

              <div class="col-12">
                <label for="abstract" class="form-label">Abstract</label>
                <textarea class="form-control" id="abstract" name="abstract" rows="4"><?= $v('abstract') ?></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card mb-4">
          <div class="card-header"><h2 class="h6 mb-0">Identificativi</h2></div>
          <div class="card-body">
            <div class="mb-3">
              <label for="isbnIssn" class="form-label">ISBN o ISSN</label>
              <input type="text" class="form-control catageo-valore" id="isbnIssn" name="isbnIssn"
                     value="<?= $v('isbnIssn') ?>">
            </div>
            <div class="mb-3">
              <label for="doi" class="form-label">DOI</label>
              <input type="text" class="form-control catageo-valore" id="doi" name="doi" value="<?= $v('doi') ?>">
            </div>
            <div class="mb-3">
              <label for="url" class="form-label">Indirizzo in rete</label>
              <input type="url" class="form-control" id="url" name="url" value="<?= $v('url') ?>"
                     placeholder="https://">
            </div>
            <div>
              <label for="lingua" class="form-label">Lingua</label>
              <input type="text" class="form-control" id="lingua" name="lingua" value="<?= $v('lingua') ?>"
                     maxlength="10" placeholder="it">
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h2 class="h6 mb-0">Altro</h2></div>
          <div class="card-body">
            <div class="mb-3">
              <label for="note" class="form-label">Note</label>
              <textarea class="form-control" id="note" name="note" rows="3"><?= $v('note') ?></textarea>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="attivo" name="attivo" value="1"
                     <?= ($m === null || !empty($m['attivo'])) ? 'checked' : '' ?>>
              <label class="form-check-label" for="attivo">
                Proponibile nelle nuove citazioni
              </label>
              <div class="catageo-nota">
                Un'opera disattivata resta nelle citazioni gia fatte: si smette di
                proporla, non la si nasconde.
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-check-lg"></i> <?= $m !== null ? 'Salva l\'opera' : 'Censisci l\'opera' ?>
        </button>
      </div>
    </div>
  </form>

<?php else: ?>

  <form method="get" action="index.php" class="mb-3">
    <input type="hidden" name="p" value="opere">
    <div class="input-group">
      <input type="search" class="form-control" name="q" value="<?= Testo::esc($filtro) ?>"
             placeholder="Cerca per autore, titolo, rivista, anno o identificativo">
      <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
      <?php if ($filtro !== ''): ?>
        <a class="btn btn-outline-secondary" href="index.php?p=opere"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($elenco === []): ?>

    <div class="card">
      <div class="card-body d-flex gap-3">
        <i class="bi bi-journals fs-3 text-body-secondary" aria-hidden="true"></i>
        <div>
          <h2 class="h6 mb-1"><?= $filtro !== '' ? 'Nessun risultato' : 'Catalogo vuoto' ?></h2>
          <p class="text-body-secondary mb-0">
            <?php if ($filtro !== ''): ?>
              Nessuna opera corrisponde alla ricerca.
            <?php else: ?>
              Qui si censiscono una volta sola le opere che descrivono piu cavita.
              Una monografia citata da quaranta schede si corregge in un posto solo.
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>

  <?php else: ?>

    <div class="card">
      <div class="table-responsive">
        <table class="table catageo-tabella mb-0 align-middle">
          <thead>
            <tr>
              <th style="width:5rem">Id</th>
              <th>Opera</th>
              <th style="width:9rem">Tipo</th>
              <th style="width:5rem">Anno</th>
              <th class="text-end">Azioni</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($elenco as $o): ?>
              <?php $id = (string) $o['id']; ?>
              <tr<?= empty($o['attivo']) ? ' class="opacity-75"' : '' ?>>
                <td><span class="catageo-valore"><?= Testo::esc($id) ?></span></td>
                <td>
                  <?= Testo::esc(Opere::citazione($o)) ?>
                  <?php if (empty($o['attivo'])): ?>
                    <span class="badge text-bg-secondary">non proposta</span>
                  <?php endif; ?>
                </td>
                <td><?= Testo::esc(Opere::TIPI[(string) $o['tipoOpera']] ?? (string) $o['tipoOpera']) ?></td>
                <td class="catageo-valore"><?= Testo::esc((string) $o['anno']) ?></td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a class="btn btn-sm btn-outline-secondary"
                       title="Chi cita quest'opera"
                       href="index.php?p=opere&amp;azione=citazioni&amp;id=<?= urlencode($id) ?>">
                      <i class="bi bi-link-45deg"></i>
                    </a>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="index.php?p=opere&amp;azione=modifica&amp;id=<?= urlencode($id) ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?p=opere">
                      <?= Auth::campoToken() ?>
                      <input type="hidden" name="operazione" value="elimina">
                      <input type="hidden" name="id" value="<?= Testo::esc($id) ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit"
                              data-catageo-conferma="Eliminare l'opera <?= Testo::esc($id) ?>? Se e citata da qualche scheda l'operazione verra rifiutata.">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?>

<?php endif; ?>
