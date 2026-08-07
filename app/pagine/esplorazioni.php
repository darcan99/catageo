<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/esplorazioni.php
 *  Descrizione ..: Le esplorazioni di tutto il catasto: cronologia, filtri per
 *                  gruppo, esploratore, periodo e catalogo (9.6).
 *
 *                  Un diario appartiene a un ipogeo, ma chi lo ha scritto ne
 *                  ha percorsi molti: e questa la vista che serve per sapere
 *                  cosa ha fatto un gruppo in una stagione, e non esiste in
 *                  nessun altro punto dell'applicativo.
 *  Versione .....: 0.9.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.9.0  2026-08-06  D.Candela  Prima stesura (fase 7), al posto del
 *                                segnaposto "in sviluppo".
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');

$titolo = 'Esplorazioni';

$filtri = [
    'gruppo'      => trim((string) ($_GET['gruppo'] ?? '')),
    'esploratore' => trim((string) ($_GET['esploratore'] ?? '')),
    'dal'         => trim((string) ($_GET['dal'] ?? '')),
    'al'          => trim((string) ($_GET['al'] ?? '')),
    'catalogo'    => trim((string) ($_GET['catalogo'] ?? '')),
];

$diari = Esplorazioni::tutte($filtri);
$filtrato = array_filter($filtri) !== [];

/*
 * Riepilogo per tipo e per anno, calcolato sull'elenco gia filtrato: sono i
 * due tagli che si guardano per primi quando si apre questa pagina, e
 * ricavarli qui costa un giro su dati che sono comunque in memoria.
 */
$perTipo = [];
$perAnno = [];
foreach ($diari as $d) {
    $tipo = (string) $d['tipo'];
    $perTipo[$tipo] = ($perTipo[$tipo] ?? 0) + 1;

    $anno = substr((string) $d['dataInizio'], 0, 4);
    if ($anno !== '') {
        $perAnno[$anno] = ($perAnno[$anno] ?? 0) + 1;
    }
}
krsort($perAnno);
?>

<div class="catageo-intestazione">
  <div>
    <h1>Esplorazioni</h1>
    <p class="text-body-secondary mb-0">
      <?= count($diari) ?> uscit<?= count($diari) === 1 ? 'a' : 'e' ?>
      <?= $filtrato ? 'che corrispondono ai filtri' : 'registrate nel catasto' ?>
    </p>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><h2 class="h6 mb-0">Filtri</h2></div>
  <div class="card-body">
    <form method="get" action="index.php" class="row g-3 align-items-end">
      <input type="hidden" name="p" value="esplorazioni">

      <div class="col-md-3">
        <label for="gruppo" class="form-label">Gruppo</label>
        <select class="form-select" id="gruppo" name="gruppo">
          <option value="">Tutti</option>
          <?php foreach (Gruppi::elenco() as $g): ?>
            <option value="<?= Testo::esc((string) $g['id']) ?>"
              <?= $filtri['gruppo'] === (string) $g['id'] ? 'selected' : '' ?>>
              <?= Testo::esc(Gruppi::etichetta($g)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label for="esploratore" class="form-label">Esploratore</label>
        <select class="form-select" id="esploratore" name="esploratore">
          <option value="">Tutti</option>
          <?php foreach (Esploratori::elenco() as $e): ?>
            <option value="<?= Testo::esc((string) $e['id']) ?>"
              <?= $filtri['esploratore'] === (string) $e['id'] ? 'selected' : '' ?>>
              <?= Testo::esc(Esploratori::etichetta($e)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="catageo-nota">Richiede di aprire i diari: più lento degli altri filtri.</div>
      </div>

      <div class="col-md-2">
        <label for="dal" class="form-label">Dal</label>
        <input type="date" class="form-control" id="dal" name="dal"
               value="<?= Testo::esc($filtri['dal']) ?>">
      </div>

      <div class="col-md-2">
        <label for="al" class="form-label">Al</label>
        <input type="date" class="form-control" id="al" name="al"
               value="<?= Testo::esc($filtri['al']) ?>">
      </div>

      <?php $cataloghi = Cataloghi::elenco(); ?>
      <?php if (count($cataloghi) > 1): ?>
        <div class="col-md-2">
          <label for="catalogo" class="form-label">Catalogo</label>
          <select class="form-select" id="catalogo" name="catalogo">
            <option value="">Tutti</option>
            <?php foreach ($cataloghi as $c): ?>
              <option value="<?= Testo::esc((string) $c['sigla']) ?>"
                <?= strcasecmp($filtri['catalogo'], (string) $c['sigla']) === 0 ? 'selected' : '' ?>>
                <?= Testo::esc((string) $c['sigla']) ?> — <?= Testo::esc((string) $c['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-funnel"></i> Filtra
        </button>
        <?php if ($filtrato): ?>
          <a class="btn btn-outline-secondary" href="index.php?p=esplorazioni">
            <i class="bi bi-x-lg"></i> Azzera
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($diari === []): ?>

  <div class="card">
    <div class="card-body d-flex gap-3">
      <i class="bi bi-compass fs-3 text-body-secondary" aria-hidden="true"></i>
      <div>
        <h2 class="h6 mb-1">Nessuna uscita</h2>
        <p class="text-body-secondary mb-0">
          <?php if ($filtrato): ?>
            Nessun diario corrisponde ai filtri impostati.
          <?php else: ?>
            I diari si scrivono dalla scheda di un ipogeo, nella sezione Esplorazioni.
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="row g-4">
    <div class="col-lg-9">
      <div class="card">
        <div class="card-header"><h2 class="h6 mb-0">Cronologia</h2></div>
        <div class="table-responsive">
          <table class="table catageo-tabella mb-0 align-middle">
            <thead>
              <tr>
                <th style="width:7rem">Data</th>
                <th>Uscita</th>
                <th>Ipogeo</th>
                <th>Tipo</th>
                <th>Gruppi</th>
                <th class="text-end">Voci</th>
              </tr>
            </thead>
            <tbody>
              <?php $annoPrecedente = null; ?>
              <?php foreach ($diari as $d): ?>
                <?php $anno = substr((string) $d['dataInizio'], 0, 4); ?>
                <?php if ($anno !== $annoPrecedente): ?>
                  <?php $annoPrecedente = $anno; ?>
                  <tr class="table-active">
                    <th colspan="6" class="catageo-valore">
                      <?= $anno !== '' ? Testo::esc($anno) : 'senza data' ?>
                    </th>
                  </tr>
                <?php endif; ?>
                <tr>
                  <td><?= Testo::esc((string) $d['dataInizio']) ?></td>
                  <td>
                    <a href="index.php?p=esplorazione&amp;codice=<?= urlencode((string) $d['codice']) ?>&amp;azione=vedi&amp;prog=<?= (int) $d['progressivo'] ?>">
                      <?= Testo::esc((string) $d['titolo']) ?>
                    </a>
                  </td>
                  <td>
                    <a href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode((string) $d['codice']) ?>">
                      <span class="catageo-codice"><?= Testo::esc((string) $d['codice']) ?></span>
                    </a>
                    <span class="catageo-nota"><?= Testo::esc((string) $d['nomeIpogeo']) ?></span>
                  </td>
                  <td><?= Testo::esc(Esplorazioni::TIPI[(string) $d['tipo']] ?? (string) $d['tipo']) ?></td>
                  <td>
                    <?php foreach ($d['gruppi'] as $idGruppo): ?>
                      <a class="badge text-bg-light border text-decoration-none"
                         href="index.php?p=esplorazioni&amp;gruppo=<?= urlencode($idGruppo) ?>">
                        <?= Testo::esc(Gruppi::etichettaPerId($idGruppo)) ?>
                      </a>
                    <?php endforeach; ?>
                  </td>
                  <td class="text-end catageo-valore"><?= (int) $d['voci'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-3">
      <div class="card mb-3">
        <div class="card-header"><h2 class="h6 mb-0">Per tipo</h2></div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <?php foreach (Esplorazioni::TIPI as $valore => $etichetta): ?>
              <?php if (!isset($perTipo[$valore])) { continue; } ?>
              <li class="d-flex justify-content-between">
                <span><?= Testo::esc($etichetta) ?></span>
                <span class="catageo-valore"><?= $perTipo[$valore] ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="h6 mb-0">Per anno</h2></div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <?php foreach ($perAnno as $anno => $quante): ?>
              <li class="d-flex justify-content-between">
                <span><?= Testo::esc((string) $anno) ?></span>
                <span class="catageo-valore"><?= $quante ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>

<?php endif; ?>
