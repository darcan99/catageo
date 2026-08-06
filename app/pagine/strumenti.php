<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/strumenti.php
 *  Descrizione ..: Strumenti di manutenzione: ricostruzione indici, verifica
 *                  integrita, backup, verifica dei collegamenti (9.14).
 *
 *                  La verifica di integrita NON corregge: segnala. Un archivio
 *                  dove i dati sono file leggibili a mano si ripara guardando,
 *                  e una correzione automatica che indovina male su un catasto
 *                  di trent'anni fa piu danni del problema. L'unica cosa che si
 *                  offre di rifare e l'indice, che e una cache.
 *  Versione .....: 0.16.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.16.0  2026-08-06  D.Candela  Riquadro dell'importazione da CSV (fase 9b).
 *  0.15.0  2026-08-06  D.Candela  Prima stesura (fase 9).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('strumenti');

$titolo  = 'Strumenti';
$ritorno = 'index.php?p=strumenti';

$azione = isset($_GET['azione']) ? (string) $_GET['azione'] : '';

// ============================================================================
//  OPERAZIONI
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();

    $operazione = (string) ($_POST['operazione'] ?? '');

    try {
        switch ($operazione) {

            case 'ricostruisciIndici':
                $esito = IndiceIpogei::ricostruisci();
                $messaggio = (int) $esito['ipogei'] . ' ipogei indicizzati in '
                    . (int) $esito['cataloghi'] . ' cataloghi.';

                if (($esito['errori'] ?? []) !== []) {
                    // Gli errori si dichiarano tutti: un indice ricostruito
                    // "quasi bene" e peggio di uno non ricostruito, perche
                    // sembra a posto.
                    Auth::messaggio('avviso', $messaggio . ' Con ' . count($esito['errori'])
                        . ' problemi: ' . implode('; ', array_slice($esito['errori'], 0, 5)));
                } else {
                    Auth::messaggio('successo', 'Indici ricostruiti: ' . $messaggio);
                }
                break;

            case 'backup':
                $percorso = Backup::crea((string) ($_POST['catalogo'] ?? ''));
                Auth::messaggio('successo',
                    'Backup creato: ' . basename($percorso) . ' ('
                    . Testo::dimensione((int) filesize($percorso)) . ').');
                break;

            case 'eliminaBackup':
                Backup::elimina((string) ($_POST['nome'] ?? ''));
                Auth::messaggio('successo', 'Backup rimosso.');
                break;

            default:
                Auth::messaggio('errore', 'Operazione non riconosciuta.');
        }
    } catch (BackupEccezione | IpogeoEccezione | CatalogoEccezione | XmlEccezione | CsvEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
    }

    header('Location: ' . $ritorno);
    exit;
}

// ============================================================================
//  VISTE SU RICHIESTA
// ============================================================================

/*
 * Integrita e verifica dei collegamenti sono operazioni lunghe: si eseguono
 * solo se chieste esplicitamente, non a ogni apertura della pagina.
 */
$integrita = $azione === 'integrita' ? Integrita::verifica() : null;

$link = null;
if ($azione === 'link') {
    $link = VerificaLink::esegui((int) ($_GET['salta'] ?? 0));
}

$backup = Backup::elenco();
$cataloghi = Cataloghi::elenco();
?>

<div class="catageo-intestazione">
  <div>
    <h1>Strumenti</h1>
    <p class="text-body-secondary mb-0">
      Manutenzione dell'archivio. Le operazioni lunghe si avviano una per volta.
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="index.php?p=diagnostica">
    <i class="bi bi-activity"></i> Diagnostica ambiente
  </a>
</div>

<div class="row g-4">

  <!-- ==================================================== indici -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h2 class="h6 mb-0">Indici</h2></div>
      <div class="card-body">
        <p class="text-body-secondary">
          Gli indici sono una <strong>cache</strong>: si rigenerano interamente
          dagli XML, che restano la sola fonte di verita. Ricostruirli e
          l'operazione da fare dopo un ripristino da backup, o dopo aver
          modificato dei file a mano.
        </p>
        <form method="post" action="<?= Testo::esc($ritorno) ?>">
          <?= Auth::campoToken() ?>
          <input type="hidden" name="operazione" value="ricostruisciIndici">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-arrow-repeat"></i> Ricostruisci gli indici
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ================================================= integrita -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h2 class="h6 mb-0">Integrita dell'archivio</h2></div>
      <div class="card-body">
        <p class="text-body-secondary">
          Cerca cio che l'uso normale non fa emergere: XML non validi,
          riferimenti rotti, codici duplicati, contatori disallineati, file
          orfani. <strong>Non corregge nulla</strong>: segnala, e dice cosa fare.
        </p>
        <a class="btn btn-primary" href="index.php?p=strumenti&amp;azione=integrita">
          <i class="bi bi-clipboard-check"></i> Verifica l'archivio
        </a>
      </div>
    </div>
  </div>

  <!-- ==================================================== backup -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">
        <h2 class="h6 mb-0">
          Backup
          <?php if ($backup !== []): ?>
            <span class="catageo-nota">
              · <?= count($backup) ?>, <?= Testo::esc(Testo::dimensione(Backup::spazioOccupato())) ?>
            </span>
          <?php endif; ?>
        </h2>
      </div>
      <div class="card-body">
        <?php if (!extension_loaded('zip')): ?>
          <div class="alert alert-warning mb-3">
            L'estensione <span class="catageo-valore">zip</span> di PHP non e
            disponibile: il backup non si puo creare da qui. Copiare a mano la
            cartella dell'archivio.
          </div>
        <?php else: ?>
          <form method="post" action="<?= Testo::esc($ritorno) ?>" class="row g-2 align-items-end mb-3">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="operazione" value="backup">
            <div class="col-md-7">
              <label for="catalogo" class="form-label">Cosa salvare</label>
              <select class="form-select" id="catalogo" name="catalogo">
                <option value="">Archivio completo</option>
                <?php foreach ($cataloghi as $c): ?>
                  <option value="<?= Testo::esc((string) $c['sigla']) ?>">
                    Solo <?= Testo::esc((string) $c['sigla'] . ' — ' . (string) $c['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-5">
              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-file-earmark-zip"></i> Crea il backup
              </button>
            </div>
            <div class="col-12">
              <div class="catageo-nota">
                Il backup di un solo catalogo comprende anche anagrafiche e
                indici: senza, le schede citerebbero gruppi ed esploratori
                inesistenti.
              </div>
            </div>
          </form>
        <?php endif; ?>

        <?php if ($backup === []): ?>
          <p class="text-body-secondary mb-0">Nessun backup presente.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm catageo-tabella mb-0 align-middle">
              <thead>
                <tr><th>File</th><th class="text-end">Dimensione</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($backup as $voce): ?>
                  <tr>
                    <td>
                      <a href="index.php?p=scarica-backup&amp;nome=<?= urlencode($voce['nome']) ?>">
                        <span class="catageo-valore"><?= Testo::esc($voce['nome']) ?></span>
                      </a>
                      <div class="catageo-nota"><?= date('Y-m-d H:i', $voce['data']) ?></div>
                    </td>
                    <td class="text-end catageo-valore">
                      <?= Testo::esc(Testo::dimensione($voce['dimensione'])) ?>
                    </td>
                    <td class="text-end">
                      <form method="post" action="<?= Testo::esc($ritorno) ?>">
                        <?= Auth::campoToken() ?>
                        <input type="hidden" name="operazione" value="eliminaBackup">
                        <input type="hidden" name="nome" value="<?= Testo::esc($voce['nome']) ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                data-catageo-conferma="Rimuovere <?= Testo::esc($voce['nome']) ?>? Un backup rimosso non va in _eliminati: sparisce.">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="catageo-nota mt-2">
            I backup stanno dentro l'archivio e non entrano nei backup
            successivi. Scaricarli altrove: un backup sullo stesso disco non
            protegge dal guasto del disco.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ================================================= import -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h2 class="h6 mb-0">Importazione da CSV</h2></div>
      <div class="card-body">
        <p class="text-body-secondary">
          Crea molte schede in una volta da un file esterno, con mappatura
          delle colonne e anteprima riga per riga. Una scheda gia presente
          <strong>non viene mai sovrascritta</strong>.
        </p>
        <a class="btn btn-primary" href="index.php?p=importa">
          <i class="bi bi-database-add"></i> Importa da CSV
        </a>
        <div class="catageo-nota mt-2">Fare un backup prima.</div>
      </div>
    </div>
  </div>

  <!-- ============================================ collegamenti -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h2 class="h6 mb-0">Collegamenti bibliografici</h2></div>
      <div class="card-body">
        <p class="text-body-secondary">
          Interroga gli indirizzi registrati nelle bibliografie e ne aggiorna
          l'esito in scheda. Si procede a lotti di
          <?= VerificaLink::LOTTO ?>: molte richieste in una sola pagina
          supererebbero il tempo massimo di esecuzione.
        </p>
        <?php if (!VerificaLink::possibile()): ?>
          <div class="alert alert-warning mb-0">
            Le chiamate HTTP in uscita non sono disponibili su questo hosting:
            la verifica non si puo eseguire. Gli esiti gia registrati restano
            invariati.
          </div>
        <?php else: ?>
          <a class="btn btn-primary" href="index.php?p=strumenti&amp;azione=link">
            <i class="bi bi-link-45deg"></i> Verifica i collegamenti
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php // ==================================================== esito integrita ?>
<?php if ($integrita !== null): ?>

  <div class="card mt-4">
    <div class="card-header">
      <h2 class="h6 mb-0">
        Esito della verifica
        <span class="catageo-nota">
          · <?= (int) $integrita['esaminati']['ipogei'] ?> ipogei,
          <?= (int) $integrita['esaminati']['sezioni'] ?> sezioni,
          <?= (int) $integrita['esaminati']['file'] ?> file esaminati
        </span>
      </h2>
    </div>

    <div class="card-body">
      <?php if ($integrita['problemi'] === []): ?>
        <div class="alert alert-success mb-0 d-flex align-items-start gap-2">
          <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
          <div>
            <div class="fw-semibold">Nessun problema rilevato</div>
            Schede valide secondo gli schemi, riferimenti risolti, indici
            allineati, contatori coerenti con i codici presenti.
          </div>
        </div>
      <?php else: ?>
        <p class="mb-3">
          <span class="badge text-bg-danger"><?= (int) $integrita['conteggi'][Integrita::ERRORE] ?> errori</span>
          <span class="badge text-bg-warning"><?= (int) $integrita['conteggi'][Integrita::ATTENZIONE] ?> avvertenze</span>
          <span class="catageo-nota ms-2">
            Un errore impedisce a qualcosa di funzionare; un'avvertenza segnala
            un'incoerenza che non blocca nulla.
          </span>
        </p>

        <?php if ($integrita['troncate'] !== []): ?>
          <div class="alert alert-warning">
            Elenco troncato a <?= Integrita::LIMITE_PER_CATEGORIA ?> voci per le
            categorie: <?= Testo::esc(implode(', ', $integrita['troncate'])) ?>.
            Risolti i primi, rieseguire la verifica per vedere i successivi.
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if ($integrita['problemi'] !== []): ?>
      <div class="table-responsive">
        <table class="table catageo-tabella mb-0 align-middle">
          <thead>
            <tr>
              <th style="width:6rem">Gravita</th>
              <th style="width:9rem">Categoria</th>
              <th>Oggetto</th>
              <th>Problema</th>
              <th>Cosa fare</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($integrita['problemi'] as $p): ?>
              <tr>
                <td>
                  <span class="badge text-bg-<?= $p['gravita'] === Integrita::ERRORE ? 'danger' : 'warning' ?>">
                    <?= $p['gravita'] === Integrita::ERRORE ? 'errore' : 'attenzione' ?>
                  </span>
                </td>
                <td class="catageo-nota"><?= Testo::esc($p['categoria']) ?></td>
                <td class="catageo-valore"><?= Testo::esc($p['oggetto']) ?></td>
                <td><?= Testo::esc($p['descrizione']) ?></td>
                <td class="catageo-nota"><?= Testo::esc($p['rimedio']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php // ================================================== esito collegamenti ?>
<?php if ($link !== null): ?>

  <div class="card mt-4">
    <div class="card-header">
      <h2 class="h6 mb-0">
        Collegamenti verificati
        <span class="catageo-nota">
          · <?= count($link['verificati']) ?> di <?= (int) $link['totale'] ?>
        </span>
      </h2>
    </div>
    <div class="card-body">
      <?php if (!$link['possibile']): ?>
        <div class="alert alert-warning mb-0"><?= Testo::esc($link['messaggio']) ?></div>
      <?php elseif ($link['totale'] === 0): ?>
        <p class="text-body-secondary mb-0">
          Nessun collegamento registrato nelle bibliografie.
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm catageo-tabella mb-0 align-middle">
            <thead>
              <tr><th>Ipogeo</th><th>Indirizzo</th><th>Esito</th></tr>
            </thead>
            <tbody>
              <?php foreach ($link['verificati'] as $v): ?>
                <tr>
                  <td>
                    <a href="index.php?p=bibliografia&amp;codice=<?= urlencode($v['codice']) ?>">
                      <span class="catageo-codice"><?= Testo::esc($v['codice']) ?></span>
                    </a>
                    <span class="catageo-nota"><?= Testo::esc(Sezioni::riferimento('BB', $v['progressivo'])) ?></span>
                  </td>
                  <td class="catageo-nota" style="word-break:break-all">
                    <?= Testo::esc(Testo::estratto($v['url'], 90)) ?>
                  </td>
                  <td>
                    <span class="badge text-bg-<?= $v['esito'] === 'raggiungibile' ? 'success'
                        : ($v['esito'] === 'spostato' ? 'warning' : 'danger') ?>">
                      <?= Testo::esc($v['esito']) ?>
                    </span>
                    <div class="catageo-nota"><?= Testo::esc($v['dettaglio']) ?></div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ((int) $link['restanti'] > 0): ?>
          <a class="btn btn-outline-secondary mt-3"
             href="index.php?p=strumenti&amp;azione=link&amp;salta=<?= (int) ($_GET['salta'] ?? 0) + VerificaLink::LOTTO ?>">
            <i class="bi bi-arrow-right"></i>
            Verifica i prossimi <?= min(VerificaLink::LOTTO, (int) $link['restanti']) ?>
            (<?= (int) $link['restanti'] ?> restanti)
          </a>
        <?php else: ?>
          <p class="catageo-nota mt-3 mb-0">Tutti i collegamenti sono stati verificati.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>
