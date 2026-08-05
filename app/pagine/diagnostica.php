<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/diagnostica.php
 *  Descrizione ..: Mostra le verifiche sull'ambiente e gli ultimi eventi dei
 *                  log. E la pagina da guardare per prima quando qualcosa non
 *                  funziona su un hosting nuovo.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

// Seconda barriera contro l'accesso diretto via HTTP: questo file ha senso
// solo se incluso da index.php, che definisce CATAGEO_ROOT. La guardia vale
// anche sui server dove il file .htaccess non viene letto.
defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('strumenti');

$verifiche = Diagnostica::verifiche(true);
$riepilogo = Diagnostica::riepilogo($verifiche);

/** Raggruppa le verifiche per sezione, conservando l'ordine di definizione. */
$gruppi = [];
foreach ($verifiche as $voce) {
    $gruppi[$voce['gruppo']][] = $voce;
}
?>

<div class="catageo-intestazione">
  <div>
    <h1>Diagnostica</h1>
    <p class="text-body-secondary mb-0">Verifiche sull'ambiente di esecuzione e sull'archivio</p>
  </div>
  <div class="d-flex gap-2">
    <span class="badge text-bg-success"><?= (int) $riepilogo['ok'] ?> in ordine</span>
    <span class="badge text-bg-warning"><?= (int) $riepilogo['attenzione'] ?> da valutare</span>
    <span class="badge text-bg-danger"><?= (int) $riepilogo['errore'] ?> da risolvere</span>
  </div>
</div>

<?php if ($riepilogo['errore'] > 0): ?>
  <div class="alert alert-danger d-flex align-items-start gap-2">
    <i class="bi bi-exclamation-octagon-fill mt-1" aria-hidden="true"></i>
    <div>
      Ci sono verifiche in errore: alcune funzioni non saranno disponibili
      finche non vengono risolte. Il dettaglio e nella colonna delle note.
    </div>
  </div>
<?php endif; ?>

<?php foreach ($gruppi as $nomeGruppo => $voci): ?>
  <div class="card mb-3">
    <div class="card-header">
      <h2 class="h6 mb-0"><?= Testo::esc($nomeGruppo) ?></h2>
    </div>
    <div class="table-responsive">
      <table class="table table-sm catageo-tabella mb-0">
        <thead>
          <tr>
            <th scope="col" style="width:2rem"></th>
            <th scope="col" style="width:16rem">Verifica</th>
            <th scope="col" style="width:16rem">Valore</th>
            <th scope="col">Note</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($voci as $voce): ?>
            <tr class="<?= $voce['esito'] === Diagnostica::ERRORE ? 'catageo-riga-errore'
                          : ($voce['esito'] === Diagnostica::ATTENZIONE ? 'catageo-riga-attenzione' : '') ?>">
              <td>
                <span class="catageo-esito catageo-esito-<?= Testo::esc($voce['esito']) ?>"
                      title="<?= Testo::esc($voce['esito']) ?>"></span>
              </td>
              <td><?= Testo::esc($voce['nome']) ?></td>
              <td class="catageo-valore"><?= Testo::esc($voce['valore']) ?></td>
              <td class="small text-body-secondary"><?= Testo::esc($voce['nota']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>

<div class="row g-3">
  <?php
  $log = [
      'accessi.csv'   => ['Ultimi accessi', 'bi-box-arrow-in-right'],
      'modifiche.csv' => ['Ultime modifiche', 'bi-pencil-square'],
      'errori.csv'    => ['Ultimi errori', 'bi-bug'],
  ];
  ?>
  <?php foreach ($log as $file => [$etichetta, $icona]): ?>
    <?php $righe = Log::ultime($file, 10); ?>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <h2 class="h6 mb-0"><i class="bi <?= $icona ?>"></i> <?= Testo::esc($etichetta) ?></h2>
        </div>
        <div class="card-body p-0">
          <?php if ($righe === []): ?>
            <p class="text-body-secondary small p-3 mb-0">Nessuna registrazione.</p>
          <?php else: ?>
            <ul class="list-group list-group-flush small">
              <?php foreach ($righe as $riga): ?>
                <li class="list-group-item">
                  <div class="catageo-valore text-body-secondary">
                    <?= Testo::esc($riga['data_ora'] ?? '') ?>
                  </div>
                  <?php
                  // Ogni log ha colonne diverse: si mostrano quelle utili
                  // senza costruire tre viste separate.
                  $sintesi = array_filter([
                      $riga['username']  ?? '',
                      $riga['esito']     ?? ($riga['azione'] ?? ($riga['livello'] ?? '')),
                      $riga['codice']    ?? '',
                      $riga['messaggio'] ?? ($riga['dettaglio'] ?? ''),
                  ], static fn (string $v): bool => trim($v) !== '');
                  ?>
                  <div><?= Testo::esc(Testo::estratto(implode(' · ', $sintesi), 120)) ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
