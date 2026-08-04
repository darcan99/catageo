<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: installa.php
 *  Descrizione ..: Installazione guidata: verifica l'ambiente, crea l'albero
 *                  dell'archivio, genera config.xml, il primo utente
 *                  amministratore e un catalogo dimostrativo.
 *
 *                  A installazione conclusa si autodisabilita creando
 *                  installato.txt. Per reinstallare va rimosso a mano: e una
 *                  barriera voluta, perche una seconda esecuzione su un
 *                  archivio popolato sarebbe distruttiva.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

require_once __DIR__ . '/app/bootstrap.php';

$verifiche   = Diagnostica::verifiche(false);
$riepilogo   = Diagnostica::riepilogo($verifiche);
$installabile = Diagnostica::installabile($verifiche);
$giaInstallato = is_file(CATAGEO_INSTALLATO) || (is_file(CATAGEO_CONFIG) && is_file(Percorsi::dati('utenti.xml')));

$errori   = [];
$completato = false;
$valori   = [
    'nomeCatasto' => 'Catasto Ipogei',
    'ente'        => '',
    'email'       => '',
    'username'    => '',
];

// ------------------------------------------------------------------ esecuzione

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$giaInstallato) {

    foreach (['nomeCatasto', 'ente', 'email', 'username'] as $campo) {
        $valori[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $password  = (string) ($_POST['password'] ?? '');
    $ripetuta  = (string) ($_POST['password2'] ?? '');

    if (!$installabile) {
        $errori[] = 'L\'ambiente non soddisfa i requisiti minimi: risolvere prima le verifiche in errore.';
    }
    if ($valori['nomeCatasto'] === '') {
        $errori[] = 'Indicare il nome del catasto.';
    }
    if ($valori['email'] !== '' && !filter_var($valori['email'], FILTER_VALIDATE_EMAIL)) {
        $errori[] = 'Indirizzo email non valido.';
    }
    if ($password !== $ripetuta) {
        $errori[] = 'Le due password non coincidono.';
    }

    try {
        Utenti::validaUsername($valori['username']);
        Utenti::validaPassword($password);
    } catch (UtenteEccezione $e) {
        $errori[] = $e->getMessage();
    }

    if ($errori === []) {
        try {
            // 1. config.xml a partire dal template, con i valori indicati.
            if (!is_file(CATAGEO_ROOT . '/config.xml.dist')) {
                throw new RuntimeException('Manca config.xml.dist: impossibile generare la configurazione.');
            }

            $config = Xml::carica(CATAGEO_ROOT . '/config.xml.dist');
            $radice = $config->documentElement;
            if ($radice === null) {
                throw new RuntimeException('config.xml.dist non ha un elemento radice valido.');
            }

            $catasto = Xml::primo($config, '/catageo/catasto');
            if ($catasto instanceof DOMElement) {
                Xml::imposta($catasto, 'nome', $valori['nomeCatasto']);
                Xml::imposta($catasto, 'ente', $valori['ente']);
                Xml::imposta($catasto, 'email', $valori['email']);
            }
            Xml::salva($config, CATAGEO_CONFIG);

            // Ricarica: da qui in avanti valgono i percorsi configurati.
            Config::carica(CATAGEO_CONFIG);

            // 2. Albero dell'archivio, protetto dall'accesso diretto via HTTP.
            $cartelle = [
                Percorsi::dati(),
                Percorsi::cataloghi(),
                Percorsi::indice(),
                Percorsi::log(),
                Percorsi::tmp(),
            ];
            foreach ($cartelle as $cartella) {
                Percorsi::assicuraCartella($cartella);
            }
            Percorsi::proteggiCartella(Percorsi::dati());

            // 3. Anagrafiche vuote: esistere da subito evita rami "se il file
            //    non c'e" sparsi nel resto dell'applicativo.
            $anagrafiche = [
                'utenti.xml'                => ['utenti', []],
                'gruppi_speleologici.xml'   => ['gruppi', []],
                'esploratori.xml'           => ['esploratori', []],
            ];
            foreach ($anagrafiche as $nomeFile => [$radiceXml, $attributi]) {
                $percorso = Percorsi::dati($nomeFile);
                if (!is_file($percorso)) {
                    Xml::salva(Xml::nuovo($radiceXml, ['versioneSchema' => '1.0'] + $attributi), $percorso);
                }
            }

            // 4. Primo amministratore.
            $idAdmin = Utenti::crea([
                'username'     => $valori['username'],
                'nomeCompleto' => '',
                'email'        => $valori['email'],
                'password'     => $password,
                'livello'      => 'ADM',
                'attivo'       => true,
            ]);

            // 5. Catalogo dimostrativo, con una sola serie senza criteri.
            //    Il catalogo reale si crea dall'interfaccia (fase 2b), cosi
            //    non si deve ripulire un esempio dall'archivio di produzione.
            creaCatalogoDimostrativo();

            // 6. Indice vuoto: la ricerca funziona da subito, senza risultati.
            Csv::scrivi(
                Percorsi::indice('ipogei.csv'),
                ['catalogo', 'codice', 'nome', 'natura', 'tipologia', 'sottotipologia', 'stato',
                 'regione', 'provincia', 'comune', 'localita', 'lat', 'lon', 'quota',
                 'sviluppo', 'dislivello', 'stato_accesso', 'riservatezza', 'stato_scheda',
                 'n_allegati', 'n_foto', 'n_video', 'n_rilievi', 'n_esplorazioni', 'n_biblio',
                 'n_serie_misure', 'ha_kml', 'ha_3d', 'ha_chirotteri', 'ha_archeologia',
                 'periodo_arch', 'data_censimento', 'ultima_modifica', 'cartella'],
                []
            );
            Csv::scrivi(
                Percorsi::indice('codici.csv'),
                ['codice', 'stato_codice', 'codice_corrente', 'catalogo_corrente', 'data_variazione'],
                []
            );

            // 7. Marcatore: l'installer non deve poter essere rieseguito.
            file_put_contents(
                CATAGEO_INSTALLATO,
                "CATAGEO installato il " . date('Y-m-d H:i:s') . "\n"
                . "Versione: " . CATAGEO_VERSIONE . "\n"
                . "Primo amministratore: " . $valori['username'] . " (" . $idAdmin . ")\n\n"
                . "Rimuovere questo file solo per reinstallare da zero:\n"
                . "una nuova installazione sovrascrive config.xml e ricrea gli indici.\n"
            );

            Log::modifica('configura', '', '', 'installazione', 'installazione completata');
            $completato = true;

        } catch (Throwable $e) {
            $errori[] = 'Installazione non completata: ' . $e->getMessage();
        }
    }
}

/**
 * Crea il catalogo dimostrativo con il proprio descrittore.
 *
 * Una sola serie senza criteri: assegna a tutti gli ipogei il prefisso DEMO
 * con padding a 3 cifre. Serve a poter provare il censimento subito dopo
 * l'installazione, senza configurare nulla.
 */
function creaCatalogoDimostrativo(): void
{
    $cartella = Percorsi::cataloghi('DEMO - Catalogo dimostrativo');
    if (is_dir($cartella)) {
        return;
    }

    Percorsi::assicuraCartella($cartella);
    Percorsi::assicuraCartella(Percorsi::unisci($cartella, 'ipogei'));

    $doc    = Xml::nuovo('catalogo', ['versioneSchema' => '1.0']);
    $radice = $doc->documentElement;
    if ($radice === null) {
        throw new RuntimeException('Creazione del catalogo dimostrativo non riuscita.');
    }

    $identita = Xml::aggiungi($radice, 'identita');
    Xml::imposta($identita, 'sigla', 'DEMO');
    Xml::imposta($identita, 'nome', 'Catalogo dimostrativo');
    Xml::imposta($identita, 'ente', '');
    Xml::imposta($identita, 'descrizione', 'Catalogo creato dall\'installazione per provare il censimento. Puo essere eliminato.');
    $ambito = Xml::aggiungi($identita, 'ambito');
    Xml::imposta($ambito, 'stato', 'IT');
    Xml::imposta($ambito, 'regione', '');
    Xml::imposta($identita, 'dataIstituzione', date('Y-m-d'));
    Xml::imposta($identita, 'attivo', '1');

    $codifica = Xml::aggiungi($radice, 'codifica');
    Xml::imposta($codifica, 'separatore', '');
    Xml::imposta($codifica, 'consentiCodiceManuale', '1');
    $serie = Xml::aggiungi($codifica, 'serie');
    Xml::aggiungi($serie, 'serieCodice', null, [
        'prefisso'             => 'DEMO',
        'nome'                 => 'Serie unica',
        'cifre'                => '3',
        'prossimoProgressivo'  => '1',
    ]);

    Xml::aggiungi($radice, 'origine');

    Xml::salva($doc, Percorsi::unisci($cartella, 'catalogo.xml'));
}

/** Classe CSS del pallino di esito. */
function classeEsito(string $esito): string
{
    return 'catageo-esito catageo-esito-' . $esito;
}
?>
<!doctype html>
<html lang="it" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Installazione · CATAGEO</title>
<link rel="stylesheet" href="assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/vendor/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/catageo.css">
</head>
<body class="bg-body-tertiary">

<div class="container py-5" style="max-width:56rem">

  <div class="text-center mb-4">
    <i class="bi bi-geo-alt-fill text-primary" style="font-size:2.5rem" aria-hidden="true"></i>
    <h1 class="h3 mt-2 mb-1">CATAGEO</h1>
    <p class="text-body-secondary mb-0">Installazione guidata · versione <?= htmlspecialchars(CATAGEO_VERSIONE, ENT_QUOTES, 'UTF-8') ?></p>
  </div>

  <?php if ($completato): ?>

    <div class="card border-success-subtle">
      <div class="card-body p-4">
        <h2 class="h5 text-success"><i class="bi bi-check-circle-fill"></i> Installazione completata</h2>
        <p>L'archivio e stato creato e il primo amministratore e attivo.</p>
        <ul class="mb-4">
          <li>Configurazione: <span class="catageo-valore">config.xml</span></li>
          <li>Archivio: <span class="catageo-valore"><?= htmlspecialchars(Percorsi::dati(), ENT_QUOTES, 'UTF-8') ?></span></li>
          <li>Amministratore: <span class="catageo-valore"><?= htmlspecialchars($valori['username'], ENT_QUOTES, 'UTF-8') ?></span></li>
        </ul>

        <div class="alert alert-warning">
          <strong>Da fare adesso:</strong> eliminare <span class="catageo-valore">installa.php</span> dal
          server. Il file si e già autodisabilitato creando
          <span class="catageo-valore">installato.txt</span>, ma rimuoverlo del
          tutto è la scelta più prudente su un'installazione pubblica.
        </div>

        <a class="btn btn-primary" href="index.php?p=login">
          <i class="bi bi-box-arrow-in-right"></i> Vai all'accesso
        </a>
      </div>
    </div>

  <?php elseif ($giaInstallato): ?>

    <div class="card border-warning-subtle">
      <div class="card-body p-4">
        <h2 class="h5"><i class="bi bi-shield-check text-warning"></i> Installazione già effettuata</h2>
        <p class="mb-3">
          Questa installazione risulta già configurata, quindi l'installer è
          disabilitato: rieseguirlo sovrascriverebbe la configurazione e gli
          indici di un archivio che potrebbe contenere dati.
        </p>
        <p class="catageo-nota mb-4">
          Per reinstallare da zero rimuovere il file
          <span class="catageo-valore">installato.txt</span> dalla cartella
          dell'applicativo. L'operazione non cancella l'archivio, ma va fatta
          con cognizione.
        </p>
        <a class="btn btn-primary" href="index.php"><i class="bi bi-house"></i> Vai all'applicativo</a>
      </div>
    </div>

  <?php else: ?>

    <?php if ($errori !== []): ?>
      <div class="alert alert-danger">
        <strong>Installazione non eseguita:</strong>
        <ul class="mb-0 mt-2">
          <?php foreach ($errori as $errore): ?>
            <li><?= htmlspecialchars($errore, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0"><i class="bi bi-1-circle"></i> Verifica dell'ambiente</h2>
        <div class="d-flex gap-2">
          <span class="badge text-bg-success"><?= (int) $riepilogo['ok'] ?></span>
          <span class="badge text-bg-warning"><?= (int) $riepilogo['attenzione'] ?></span>
          <span class="badge text-bg-danger"><?= (int) $riepilogo['errore'] ?></span>
        </div>
      </div>
      <div class="table-responsive" style="max-height:22rem;overflow-y:auto">
        <table class="table table-sm catageo-tabella mb-0">
          <tbody>
            <?php foreach ($verifiche as $voce): ?>
              <tr class="<?= $voce['esito'] === Diagnostica::ERRORE ? 'catageo-riga-errore'
                            : ($voce['esito'] === Diagnostica::ATTENZIONE ? 'catageo-riga-attenzione' : '') ?>">
                <td style="width:2rem"><span class="<?= classeEsito($voce['esito']) ?>"></span></td>
                <td style="width:14rem"><?= htmlspecialchars($voce['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="catageo-valore" style="width:14rem"><?= htmlspecialchars($voce['valore'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="small text-body-secondary"><?= htmlspecialchars($voce['nota'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (!$installabile): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-octagon-fill"></i>
        Ci sono requisiti non soddisfatti: risolverli prima di procedere.
        Le voci in giallo non bloccano l'installazione.
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header bg-transparent">
        <h2 class="h6 mb-0"><i class="bi bi-2-circle"></i> Catasto e primo amministratore</h2>
      </div>
      <div class="card-body">
        <form method="post" action="installa.php" class="needs-validation" novalidate>

          <h3 class="h6 text-body-secondary mb-3">Identità del catasto</h3>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="nomeCatasto" class="form-label">Nome del catasto <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="nomeCatasto" name="nomeCatasto" required maxlength="120"
                     value="<?= htmlspecialchars($valori['nomeCatasto'], ENT_QUOTES, 'UTF-8') ?>">
              <div class="catageo-nota">Compare in navbar, stampe ed esportazioni.</div>
            </div>
            <div class="col-md-6">
              <label for="ente" class="form-label">Ente o gruppo</label>
              <input type="text" class="form-control" id="ente" name="ente" maxlength="150"
                     value="<?= htmlspecialchars($valori['ente'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">Email di riferimento</label>
              <input type="email" class="form-control" id="email" name="email" maxlength="150"
                     value="<?= htmlspecialchars($valori['email'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
          </div>

          <h3 class="h6 text-body-secondary mb-3">Primo amministratore</h3>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="username" name="username" required
                     pattern="[A-Za-z0-9._\-]{3,40}" maxlength="40" spellcheck="false"
                     autocomplete="username"
                     value="<?= htmlspecialchars($valori['username'], ENT_QUOTES, 'UTF-8') ?>">
              <div class="invalid-feedback">Da 3 a 40 caratteri: lettere, cifre, punto, underscore, trattino.</div>
            </div>
            <div class="col-md-3">
              <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="password" name="password" required
                     minlength="<?= Utenti::MIN_PASSWORD ?>" autocomplete="new-password">
              <div class="invalid-feedback">Almeno <?= Utenti::MIN_PASSWORD ?> caratteri.</div>
            </div>
            <div class="col-md-3">
              <label for="password2" class="form-label">Ripeti password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="password2" name="password2" required
                     minlength="<?= Utenti::MIN_PASSWORD ?>" autocomplete="new-password">
            </div>
          </div>

          <div class="alert alert-info mt-4 mb-4 small">
            <i class="bi bi-info-circle"></i>
            La password viene conservata solo come hash BCRYPT: non è recuperabile.
            Se viene perduta, un altro amministratore può reimpostarla; se non ci
            sono altri amministratori l'unico rimedio è modificare a mano
            <span class="catageo-valore">utenti.xml</span>.
          </div>

          <button type="submit" class="btn btn-primary" <?= $installabile ? '' : 'disabled' ?>>
            <i class="bi bi-gear-fill"></i> Installa
          </button>
        </form>
      </div>
    </div>

  <?php endif; ?>

  <p class="text-center catageo-nota mt-4 mb-0">
    CATAGEO <?= htmlspecialchars(CATAGEO_VERSIONE, ENT_QUOTES, 'UTF-8') ?> ·
    Dario Candela · GNU GPL v3.0
  </p>

</div>

<script src="assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/catageo.js"></script>
</body>
</html>
