<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/ipogei.php
 *  Descrizione ..: Elenco degli ipogei, scheda in consultazione, censimento e
 *                  modifica. Le sezioni delle risorse (foto, rilievi, allegati,
 *                  esplorazioni e le altre) compaiono come tab dichiarati ma non
 *                  ancora compilabili: arrivano nelle fasi successive, e
 *                  nasconderle darebbe l'idea che la scheda sia completa.
 *  Versione .....: 0.4.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.4.0  2026-08-04  D.Candela  Prima stesura (fase 3).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');

/** Ipogei per pagina nell'elenco. */
const IPOGEI_PER_PAGINA = 25;

/** Righe di ingresso libere offerte nel form. */
const RIGHE_INGRESSO_LIBERE = 2;

$azione = isset($_GET['azione']) ? (string) $_GET['azione'] : 'elenco';
$codice = isset($_GET['codice']) ? trim((string) $_GET['codice']) : '';

// ------------------------------------------------------------------ operazioni

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::esigiToken();
    $operazione = (string) ($_POST['operazione'] ?? '');
    $ritorno    = 'index.php?p=ipogei';

    try {
        switch ($operazione) {

            case 'crea':
                Auth::esigi('modifica_scheda');
                $nuovo = Ipogeo::crea((string) ($_POST['catalogo'] ?? ''), datiSchedaDaPost());
                Auth::messaggio('successo', 'Ipogeo censito con codice ' . $nuovo . '.');
                $ritorno = 'index.php?p=ipogei&azione=scheda&codice=' . urlencode($nuovo);
                break;

            case 'aggiorna':
                Auth::esigi('modifica_scheda');
                $daAggiornare = trim((string) ($_POST['codice'] ?? ''));
                Ipogeo::aggiorna($daAggiornare, datiSchedaDaPost());
                Auth::messaggio('successo', 'Scheda aggiornata.');
                $ritorno = 'index.php?p=ipogei&azione=scheda&codice=' . urlencode($daAggiornare);
                break;

            case 'cambia-codice':
                Auth::esigi('modifica_codice');
                $daCambiare = trim((string) ($_POST['codice'] ?? ''));
                $nuovoCodice = trim((string) ($_POST['nuovoCodice'] ?? ''));
                Ipogeo::cambiaCodice($daCambiare, $nuovoCodice, (string) ($_POST['motivo'] ?? 'rinumerazione'));
                Auth::messaggio('successo', 'Codice cambiato da ' . $daCambiare . ' a ' . $nuovoCodice
                    . '. Il codice precedente resta risolvibile.');
                $ritorno = 'index.php?p=ipogei&azione=scheda&codice=' . urlencode($nuovoCodice);
                break;

            case 'elimina':
                Auth::esigi('elimina_ipogeo');
                $daEliminare = trim((string) ($_POST['codice'] ?? ''));
                $destinazione = Ipogeo::elimina($daEliminare);
                Auth::messaggio('successo', 'Ipogeo rimosso dal catalogo. L\'archivio e stato conservato in '
                    . Ipogeo::CARTELLA_ELIMINATI . '/' . basename($destinazione) . '.');
                break;

            default:
                throw new IpogeoEccezione('Operazione non riconosciuta.');
        }
    } catch (IpogeoEccezione | CatalogoEccezione $e) {
        Auth::messaggio('errore', $e->getMessage());
        if ($operazione === 'crea') {
            $ritorno = 'index.php?p=ipogei&azione=nuovo';
        } elseif (in_array($operazione, ['aggiorna', 'cambia-codice'], true)) {
            $ritorno = 'index.php?p=ipogei&azione=modifica&codice=' . urlencode((string) ($_POST['codice'] ?? ''));
        }
    }

    header('Location: ' . $ritorno);
    exit;
}

/**
 * Raccoglie la scheda dal POST.
 *
 * Restituisce solo le sezioni che il form presenta: in modifica la fusione
 * avviene sulla scheda esistente, quindi i campi non presentati non vengono
 * azzerati.
 *
 * @return array<string,mixed>
 */
function datiSchedaDaPost(): array
{
    $sinonimi = array_filter(array_map('trim', explode(',', (string) ($_POST['sinonimi'] ?? ''))),
        static fn (string $v): bool => $v !== '');

    $interesse = array_filter(array_map('trim', explode(',', (string) ($_POST['interesse'] ?? ''))),
        static fn (string $v): bool => $v !== '');

    $ingressi = [];
    foreach ((array) ($_POST['ingressi'] ?? []) as $riga) {
        if (!is_array($riga)) {
            continue;
        }
        $ingressi[] = [
            'descrizione' => (string) ($riga['descrizione'] ?? ''),
            'latitudine'  => (string) ($riga['latitudine'] ?? ''),
            'longitudine' => (string) ($riga['longitudine'] ?? ''),
            'quota'       => (string) ($riga['quota'] ?? ''),
            'dimensioni'  => (string) ($riga['dimensioni'] ?? ''),
            'stato'       => (string) ($riga['stato'] ?? ''),
        ];
    }

    $dati = [
        'identificazione' => [
            'nome'           => (string) ($_POST['nome'] ?? ''),
            'sinonimi'       => array_values($sinonimi),
            'natura'         => (string) ($_POST['natura'] ?? ''),
            'tipologia'      => (string) ($_POST['tipologia'] ?? ''),
            'sottotipologia' => (string) ($_POST['sottotipologia'] ?? ''),
        ],
        'ubicazione' => [
            'stato'     => (string) ($_POST['stato'] ?? 'IT'),
            'statoNome' => (string) ($_POST['statoNome'] ?? ''),
            'regione'   => (string) ($_POST['regione'] ?? ''),
            'provincia' => (string) ($_POST['provincia'] ?? ''),
            'comune'    => (string) ($_POST['comune'] ?? ''),
            'localita'  => (string) ($_POST['localita'] ?? ''),
            'indirizzo' => (string) ($_POST['indirizzo'] ?? ''),
            'coordinate' => [
                'latitudine'      => (string) ($_POST['latitudine'] ?? ''),
                'longitudine'     => (string) ($_POST['longitudine'] ?? ''),
                'quota'           => (string) ($_POST['quota'] ?? ''),
                'precisione'      => (string) ($_POST['precisione'] ?? ''),
                'metodo'          => (string) ($_POST['metodo'] ?? ''),
                'dataRilevamento' => (string) ($_POST['dataRilevamento'] ?? ''),
            ],
            'cartografia' => [
                'tavolettaIGM' => (string) ($_POST['tavolettaIGM'] ?? ''),
                'sezioneCTR'   => (string) ($_POST['sezioneCTR'] ?? ''),
            ],
            'accesso' => [
                'stato'               => (string) ($_POST['statoAccesso'] ?? ''),
                'descrizione'         => (string) ($_POST['descrizioneAccesso'] ?? ''),
                'proprieta'           => (string) ($_POST['proprieta'] ?? ''),
                'permessiNecessari'   => !empty($_POST['permessiNecessari']),
                'riferimentoPermessi' => (string) ($_POST['riferimentoPermessi'] ?? ''),
            ],
            'riservatezza' => (string) ($_POST['riservatezza'] ?? 'pubblica'),
        ],
        'caratteristiche' => [
            'sviluppoPlanimetrico' => (string) ($_POST['sviluppoPlanimetrico'] ?? ''),
            'sviluppoSpaziale'     => (string) ($_POST['sviluppoSpaziale'] ?? ''),
            'dislivelloPositivo'   => (string) ($_POST['dislivelloPositivo'] ?? ''),
            'dislivelloNegativo'   => (string) ($_POST['dislivelloNegativo'] ?? ''),
            'profonditaMassima'    => (string) ($_POST['profonditaMassima'] ?? ''),
            'numeroIngressi'       => (string) ($_POST['numeroIngressi'] ?? ''),
            'ingressi'             => $ingressi,
            'idrologia'            => [
                'presenzaAcqua' => (string) ($_POST['presenzaAcqua'] ?? ''),
                'note'          => (string) ($_POST['noteIdrologia'] ?? ''),
            ],
            'interesse'      => array_values($interesse),
            'percorribilita' => [
                'difficolta'             => (string) ($_POST['difficolta'] ?? ''),
                'attrezzaturaNecessaria' => (string) ($_POST['attrezzaturaNecessaria'] ?? ''),
                'pericoli'               => (string) ($_POST['pericoli'] ?? ''),
                'tempoPercorrenza'       => (string) ($_POST['tempoPercorrenza'] ?? ''),
            ],
        ],
        'descrizione' => [
            'sintesi' => (string) ($_POST['sintesi'] ?? ''),
            'testo'   => (string) ($_POST['testo'] ?? ''),
            'storia'  => (string) ($_POST['storia'] ?? ''),
            'note'    => (string) ($_POST['note'] ?? ''),
        ],
        'catasto' => [
            'dataCensimento' => (string) ($_POST['dataCensimento'] ?? ''),
            'censitoDa'      => (string) ($_POST['censitoDa'] ?? ''),
            'gruppoCensore'  => (string) ($_POST['gruppoCensore'] ?? ''),
            'statoScheda'    => (string) ($_POST['statoScheda'] ?? 'bozza'),
        ],
    ];

    $codiceManuale = trim((string) ($_POST['codiceManuale'] ?? ''));
    if ($codiceManuale !== '') {
        $dati['codiceManuale'] = $codiceManuale;
    }

    return $dati;
}

/**
 * Coordinate da mostrare all'utente corrente, applicando l'offuscamento
 * previsto dal livello di riservatezza (D12).
 *
 * @param  array<string,mixed> $scheda
 * @return array{lat:string,lon:string,offuscate:bool}
 */
function coordinateVisibili(array $scheda): array
{
    $lat = (string) $scheda['ubicazione']['coordinate']['latitudine'];
    $lon = (string) $scheda['ubicazione']['coordinate']['longitudine'];

    $riservatezza = (string) $scheda['ubicazione']['riservatezza'];
    if ($riservatezza !== 'coordinate_offuscate' || Auth::puo('vedi_riservati')) {
        return ['lat' => $lat, 'lon' => $lon, 'offuscate' => false];
    }

    // Arrotondamento deterministico: la stessa scheda mostra sempre la stessa
    // posizione approssimata, cosi non si puo triangolare ricaricando la pagina.
    $metri = max(100, Config::intero('sicurezza.offuscamentoCoordinate', 1000));
    $passo = $metri / 111000.0;   // gradi di latitudine equivalenti

    return [
        'lat'       => number_format(round((float) $lat / $passo) * $passo, 4, '.', ''),
        'lon'       => number_format(round((float) $lon / $passo) * $passo, 4, '.', ''),
        'offuscate' => true,
    ];
}

/** True se l'utente corrente puo vedere una scheda con la riservatezza data. */
function schedaVisibile(string $riservatezza, string $statoScheda): bool
{
    if ($riservatezza === 'riservata' && !Auth::puo('vedi_riservati')) {
        return false;
    }
    if ($statoScheda === 'bozza' && !Auth::puo('vedi_bozze')) {
        return false;
    }
    return true;
}

$cataloghi = Cataloghi::elenco();

// ============================================================================
//  SCHEDA
// ============================================================================
if ($azione === 'scheda' && $codice !== '') {

    $risoluzione = Ipogeo::risolvi($codice);

    if ($risoluzione === null) {
        Auth::messaggio('errore', 'Nessun ipogeo con codice "' . $codice . '".');
        header('Location: index.php?p=ipogei');
        exit;
    }

    $scheda = $risoluzione['scheda'];
    if (!schedaVisibile((string) $scheda['ubicazione']['riservatezza'], (string) $scheda['catasto']['statoScheda'])) {
        Auth::messaggio('errore', 'La scheda richiesta non e consultabile con il livello di utenza in uso.');
        header('Location: index.php?p=ipogei');
        exit;
    }

    $codiceCorrente = $risoluzione['codiceCorrente'];
    $coord   = coordinateVisibili($scheda);
    $storico = Ipogeo::storico($codiceCorrente);
    $riga    = IndiceIpogei::trova($codiceCorrente);
    $titolo  = $codiceCorrente . ' — ' . (string) $scheda['identificazione']['nome'];
    ?>

    <?php if ($risoluzione['eraStorico']): ?>
      <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-signpost-split-fill mt-1" aria-hidden="true"></i>
        <div>
          Il codice <span class="catageo-codice"><?= Testo::esc($codice) ?></span> non e piu quello corrente:
          questo ipogeo e ora <span class="catageo-codice"><?= Testo::esc($codiceCorrente) ?></span>.
          I riferimenti al vecchio codice continuano a funzionare.
        </div>
      </div>
    <?php endif; ?>

    <div class="catageo-intestazione">
      <div>
        <h1>
          <span class="catageo-codice text-primary"><?= Testo::esc($codiceCorrente) ?></span>
          <?= Testo::esc((string) $scheda['identificazione']['nome']) ?>
        </h1>
        <p class="text-body-secondary mb-0">
          <?= Testo::esc(Tipologie::percorsoLeggibile((string) $scheda['identificazione']['sottotipologia'] !== ''
              ? (string) $scheda['identificazione']['sottotipologia']
              : (string) $scheda['identificazione']['tipologia'])) ?>
          · catalogo <span class="catageo-codice"><?= Testo::esc((string) $scheda['catasto']['catalogo']) ?></span>
          · revisione <?= (int) $scheda['catasto']['revisione'] ?>
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2 catageo-non-stampare">
        <?php if (Auth::puo('modifica_scheda')): ?>
          <a class="btn btn-primary" href="index.php?p=ipogei&amp;azione=modifica&amp;codice=<?= urlencode($codiceCorrente) ?>">
            <i class="bi bi-pencil"></i> Modifica
          </a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="index.php?p=ipogei"><i class="bi bi-list"></i> Elenco</a>
      </div>
    </div>

    <?php
    // Barra degli avvisi: cio che chi programma un'uscita deve sapere prima di
    // leggere il resto. Le sezioni archeologia e biospeleologia arriveranno in
    // fase 7d e aggiungeranno qui le proprie voci.
    $avvisi = [];
    if ((string) $scheda['catasto']['statoScheda'] === 'bozza') {
        $avvisi[] = ['warning', 'bi-pencil-fill', 'Scheda in bozza: i dati non sono ancora verificati.'];
    }
    $statoAccesso = (string) $scheda['ubicazione']['accesso']['stato'];
    if (in_array($statoAccesso, ['chiuso', 'interrato', 'distrutto', 'non_localizzato'], true)) {
        $avvisi[] = ['warning', 'bi-slash-circle-fill', 'Stato di accesso: ' . str_replace('_', ' ', $statoAccesso) . '.'];
    }
    if (!empty($scheda['ubicazione']['accesso']['permessiNecessari'])) {
        $avvisi[] = ['danger', 'bi-key-fill', 'Accesso subordinato ad autorizzazione.'
            . ((string) $scheda['ubicazione']['accesso']['riferimentoPermessi'] !== ''
                ? ' ' . (string) $scheda['ubicazione']['accesso']['riferimentoPermessi'] : '')];
    }
    if ((string) $scheda['caratteristiche']['percorribilita']['pericoli'] !== '') {
        $avvisi[] = ['danger', 'bi-exclamation-triangle-fill',
            'Pericoli segnalati: ' . Testo::estratto((string) $scheda['caratteristiche']['percorribilita']['pericoli'], 160)];
    }
    if ((string) $scheda['ubicazione']['riservatezza'] === 'riservata') {
        $avvisi[] = ['secondary', 'bi-shield-lock-fill', 'Ubicazione riservata: non divulgare.'];
    }
    ?>
    <?php if ($avvisi !== []): ?>
      <div class="mb-4">
        <?php foreach ($avvisi as [$tipo, $icona, $testo]): ?>
          <div class="alert alert-<?= $tipo ?> py-2 mb-2 d-flex align-items-start gap-2">
            <i class="bi <?= $icona ?> mt-1" aria-hidden="true"></i>
            <div><?= Testo::esc($testo) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3 catageo-non-stampare" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDati" type="button">Dati</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDescrizione" type="button">Descrizione</button></li>
      <?php foreach (Sezioni::sigle() as $sigla): ?>
        <?php $conteggio = (int) ($riga['n_' . strtolower(match ($sigla) {
            'AL' => 'allegati', 'FO' => 'foto', 'VI' => 'video', 'RI' => 'rilievi',
            'ES' => 'esplorazioni', 'BB' => 'biblio', 'SC' => 'serie_misure', default => 'x',
        })] ?? 0); ?>
        <li class="nav-item">
          <button class="nav-link text-body-secondary" data-bs-toggle="tab" data-bs-target="#tabSezione<?= $sigla ?>" type="button">
            <?= Testo::esc(Sezioni::etichetta($sigla)) ?>
            <?php if ($conteggio > 0): ?><span class="badge text-bg-secondary"><?= $conteggio ?></span><?php endif; ?>
          </button>
        </li>
      <?php endforeach; ?>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabStorico" type="button">
        Storico <?php if ($storico !== []): ?><span class="badge text-bg-secondary"><?= count($storico) ?></span><?php endif; ?>
      </button></li>
    </ul>

    <div class="tab-content">

      <!-- ------------------------------------------------------------ Dati -->
      <div class="tab-pane fade show active" id="tabDati">
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header bg-transparent"><h2 class="h6 mb-0">Identificazione</h2></div>
              <div class="card-body">
                <dl class="row mb-0">
                  <?php
                  $identificazione = [
                      'Codice'         => '<span class="catageo-codice">' . Testo::esc($codiceCorrente) . '</span>',
                      'Nome'           => Testo::esc((string) $scheda['identificazione']['nome']),
                      'Sinonimi'       => Testo::esc(implode(', ', (array) $scheda['identificazione']['sinonimi'])),
                      'Natura'         => Testo::esc(Tipologie::nome((string) $scheda['identificazione']['natura'])),
                      'Tipologia'      => Testo::esc(Tipologie::nome((string) $scheda['identificazione']['tipologia'])),
                      'Sottotipologia' => Testo::esc(Tipologie::nome((string) $scheda['identificazione']['sottotipologia'])),
                  ];
                  foreach ($identificazione as $etichetta => $valore): ?>
                    <dt class="col-sm-5 fw-normal text-body-secondary"><?= $etichetta ?></dt>
                    <dd class="col-sm-7"><?= $valore !== '' ? $valore : '<span class="text-body-tertiary">—</span>' ?></dd>
                  <?php endforeach; ?>
                </dl>

                <?php if ((array) $scheda['identificazione']['codiciStorici'] !== []): ?>
                  <hr>
                  <div class="catageo-nota mb-1">Codici precedenti</div>
                  <?php foreach ((array) $scheda['identificazione']['codiciStorici'] as $storicoCodice): ?>
                    <div class="small">
                      <span class="catageo-codice"><?= Testo::esc((string) $storicoCodice['codice']) ?></span>
                      <span class="text-body-secondary">
                        fino al <?= Testo::esc((string) $storicoCodice['al']) ?>
                        <?php if ((string) $storicoCodice['motivo'] !== ''): ?>
                          (<?= Testo::esc((string) $storicoCodice['motivo']) ?>)
                        <?php endif; ?>
                      </span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header bg-transparent"><h2 class="h6 mb-0">Ubicazione</h2></div>
              <div class="card-body">
                <dl class="row mb-0">
                  <?php
                  $ubicazione = [
                      'Stato'     => (string) $scheda['ubicazione']['statoNome'] !== ''
                          ? (string) $scheda['ubicazione']['statoNome'] : (string) $scheda['ubicazione']['stato'],
                      'Regione'   => (string) $scheda['ubicazione']['regione'],
                      'Provincia' => (string) $scheda['ubicazione']['provincia'],
                      'Comune'    => (string) $scheda['ubicazione']['comune'],
                      'Localita'  => (string) $scheda['ubicazione']['localita'],
                  ];
                  foreach ($ubicazione as $etichetta => $valore): ?>
                    <dt class="col-sm-5 fw-normal text-body-secondary"><?= $etichetta ?></dt>
                    <dd class="col-sm-7"><?= $valore !== '' ? Testo::esc($valore) : '<span class="text-body-tertiary">—</span>' ?></dd>
                  <?php endforeach; ?>

                  <dt class="col-sm-5 fw-normal text-body-secondary">Coordinate</dt>
                  <dd class="col-sm-7">
                    <span class="catageo-valore"><?= Testo::esc($coord['lat']) ?>, <?= Testo::esc($coord['lon']) ?></span>
                    <?php if ($coord['offuscate']): ?>
                      <span class="badge text-bg-warning" title="Posizione approssimata per riservatezza">approssimata</span>
                    <?php endif; ?>
                  </dd>

                  <dt class="col-sm-5 fw-normal text-body-secondary">Quota</dt>
                  <dd class="col-sm-7">
                    <?= (string) $scheda['ubicazione']['coordinate']['quota'] !== ''
                        ? Testo::esc((string) $scheda['ubicazione']['coordinate']['quota']) . ' m'
                        : '<span class="text-body-tertiary">—</span>' ?>
                  </dd>

                  <dt class="col-sm-5 fw-normal text-body-secondary">Riservatezza</dt>
                  <dd class="col-sm-7"><?= Testo::esc((string) $scheda['ubicazione']['riservatezza']) ?></dd>
                </dl>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header bg-transparent"><h2 class="h6 mb-0">Caratteristiche</h2></div>
              <div class="card-body">
                <dl class="row mb-0">
                  <?php
                  $misure = [
                      'Sviluppo planimetrico' => (string) $scheda['caratteristiche']['sviluppoPlanimetrico'],
                      'Sviluppo spaziale'     => (string) $scheda['caratteristiche']['sviluppoSpaziale'],
                      'Dislivello positivo'   => (string) $scheda['caratteristiche']['dislivelloPositivo'],
                      'Dislivello negativo'   => (string) $scheda['caratteristiche']['dislivelloNegativo'],
                      'Profondita massima'    => (string) $scheda['caratteristiche']['profonditaMassima'],
                  ];
                  foreach ($misure as $etichetta => $valore): ?>
                    <dt class="col-sm-7 fw-normal text-body-secondary"><?= $etichetta ?></dt>
                    <dd class="col-sm-5 catageo-valore">
                      <?= $valore !== '' ? Testo::esc($valore) . ' m' : '<span class="text-body-tertiary">—</span>' ?>
                    </dd>
                  <?php endforeach; ?>
                  <dt class="col-sm-7 fw-normal text-body-secondary">Ingressi</dt>
                  <dd class="col-sm-5 catageo-valore">
                    <?= (string) $scheda['caratteristiche']['numeroIngressi'] !== ''
                        ? Testo::esc((string) $scheda['caratteristiche']['numeroIngressi'])
                        : (count((array) $scheda['caratteristiche']['ingressi']) ?: '<span class="text-body-tertiary">—</span>') ?>
                  </dd>
                  <dt class="col-sm-7 fw-normal text-body-secondary">Presenza d'acqua</dt>
                  <dd class="col-sm-5"><?= Testo::esc((string) $scheda['caratteristiche']['idrologia']['presenzaAcqua'] ?: '—') ?></dd>
                </dl>

                <?php if ((array) $scheda['caratteristiche']['interesse'] !== []): ?>
                  <hr>
                  <?php foreach ((array) $scheda['caratteristiche']['interesse'] as $voce): ?>
                    <span class="badge text-bg-light border"><?= Testo::esc((string) $voce) ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header bg-transparent"><h2 class="h6 mb-0">Dati di catasto</h2></div>
              <div class="card-body">
                <dl class="row mb-0">
                  <?php
                  $catasto = [
                      'Catalogo'        => (string) $scheda['catasto']['nomeCatalogo'] . ' (' . (string) $scheda['catasto']['catalogo'] . ')',
                      'Serie'           => (string) $scheda['catasto']['serieCodice'],
                      'Data censimento' => (string) $scheda['catasto']['dataCensimento'],
                      'Censito da'      => (string) $scheda['catasto']['censitoDa'] !== ''
                          ? Esploratori::etichettaPerId((string) $scheda['catasto']['censitoDa']) : '',
                      'Gruppo'          => (string) $scheda['catasto']['gruppoCensore'] !== ''
                          ? Gruppi::etichettaPerId((string) $scheda['catasto']['gruppoCensore']) : '',
                      'Stato scheda'    => (string) $scheda['catasto']['statoScheda'],
                      'Creata'          => (string) $scheda['catasto']['creazioneData'] . ' · ' . (string) $scheda['catasto']['creazioneUtente'],
                      'Ultima modifica' => (string) $scheda['catasto']['modificaData'] . ' · ' . (string) $scheda['catasto']['modificaUtente'],
                  ];
                  foreach ($catasto as $etichetta => $valore): ?>
                    <dt class="col-sm-5 fw-normal text-body-secondary"><?= $etichetta ?></dt>
                    <dd class="col-sm-7"><?= trim($valore, ' ·') !== '' ? Testo::esc($valore) : '<span class="text-body-tertiary">—</span>' ?></dd>
                  <?php endforeach; ?>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <?php if ((array) $scheda['caratteristiche']['ingressi'] !== []): ?>
          <div class="card mt-4">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">Ingressi</h2></div>
            <div class="table-responsive">
              <table class="table table-sm catageo-tabella mb-0">
                <thead><tr><th>#</th><th>Descrizione</th><th>Coordinate</th><th>Quota</th><th>Dimensioni</th><th>Stato</th></tr></thead>
                <tbody>
                  <?php foreach ((array) $scheda['caratteristiche']['ingressi'] as $i => $ingresso): ?>
                    <tr>
                      <td><?= $i + 1 ?></td>
                      <td><?= Testo::esc((string) $ingresso['descrizione']) ?></td>
                      <td class="catageo-valore">
                        <?= $coord['offuscate'] ? '<span class="text-body-tertiary">approssimate</span>'
                            : Testo::esc(trim((string) $ingresso['latitudine'] . ' ' . (string) $ingresso['longitudine'])) ?>
                      </td>
                      <td class="catageo-valore"><?= Testo::esc((string) $ingresso['quota']) ?></td>
                      <td><?= Testo::esc((string) $ingresso['dimensioni']) ?></td>
                      <td><?= Testo::esc((string) $ingresso['stato']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- ----------------------------------------------------- Descrizione -->
      <div class="tab-pane fade" id="tabDescrizione">
        <?php
        $testi = [
            'Sintesi'      => (string) $scheda['descrizione']['sintesi'],
            'Descrizione'  => (string) $scheda['descrizione']['testo'],
            'Storia'       => (string) $scheda['descrizione']['storia'],
            'Note'         => (string) $scheda['descrizione']['note'],
            'Accesso'      => (string) $scheda['ubicazione']['accesso']['descrizione'],
            'Attrezzatura' => (string) $scheda['caratteristiche']['percorribilita']['attrezzaturaNecessaria'],
            'Pericoli'     => (string) $scheda['caratteristiche']['percorribilita']['pericoli'],
        ];
        $qualcosa = false;
        foreach ($testi as $etichetta => $valore):
            if (trim($valore) === '') { continue; }
            $qualcosa = true; ?>
          <div class="card mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0"><?= $etichetta ?></h2></div>
            <div class="card-body">
              <?php // I testi non hanno limiti di lunghezza (D6): si mostrano integrali. ?>
              <div style="white-space:pre-wrap"><?= Testo::esc($valore) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$qualcosa): ?>
          <p class="text-body-secondary">Nessun testo descrittivo compilato.</p>
        <?php endif; ?>
      </div>

      <!-- --------------------------------------------- sezioni in arrivo -->
      <?php foreach (Sezioni::sigle() as $sigla): ?>
        <div class="tab-pane fade" id="tabSezione<?= $sigla ?>">
          <div class="card">
            <div class="card-body d-flex gap-3">
              <i class="bi bi-cone-striped fs-3 text-warning" aria-hidden="true"></i>
              <div>
                <h2 class="h6 mb-1"><?= Testo::esc(Sezioni::etichetta($sigla)) ?></h2>
                <p class="text-body-secondary mb-2">
                  La cartella
                  <span class="catageo-valore"><?= Testo::esc(Sezioni::nomeCartella($codiceCorrente, $sigla)) ?></span>
                  esiste gia nell'archivio ed e pronta: la gestione dei contenuti
                  arriva nelle fasi successive del piano di sviluppo.
                </p>
                <p class="catageo-nota mb-0">
                  Nel frattempo i file si possono depositare a mano nella cartella
                  rispettando lo standard di nomenclatura
                  <span class="catageo-valore"><?= Testo::esc($codiceCorrente) ?>-<?= $sigla ?>001-nome.est</span>:
                  verranno riconosciuti e conteggiati dall'indice.
                </p>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- --------------------------------------------------------- Storico -->
      <div class="tab-pane fade" id="tabStorico">
        <div class="card">
          <div class="card-header bg-transparent">
            <h2 class="h6 mb-0">Revisioni conservate</h2>
          </div>
          <?php if ($storico === []): ?>
            <div class="card-body">
              <p class="text-body-secondary mb-0">
                Nessuna revisione precedente: la scheda non e ancora stata modificata
                dopo il censimento.
              </p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm catageo-tabella mb-0">
                <thead><tr><th>Data e ora</th><th>File</th><th>Dimensione</th></tr></thead>
                <tbody>
                  <?php foreach ($storico as $revisione): ?>
                    <tr>
                      <td class="catageo-valore"><?= Testo::esc($revisione['data']) ?></td>
                      <td class="catageo-valore small"><?= Testo::esc($revisione['file']) ?></td>
                      <td class="catageo-valore"><?= Testo::esc(Testo::dimensione($revisione['dimensione'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <?php if (Auth::puo('modifica_codice') || Auth::puo('elimina_ipogeo')): ?>
          <div class="card mt-4 border-danger-subtle catageo-non-stampare">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">Operazioni riservate</h2></div>
            <div class="card-body">

              <?php if (Auth::puo('modifica_codice')): ?>
                <form method="post" action="index.php?p=ipogei" class="row g-2 align-items-end mb-4">
                  <?= Auth::campoToken() ?>
                  <input type="hidden" name="operazione" value="cambia-codice">
                  <input type="hidden" name="codice" value="<?= Testo::esc($codiceCorrente) ?>">
                  <div class="col-md-3">
                    <label for="nuovoCodice" class="form-label">Nuovo codice</label>
                    <input type="text" class="form-control catageo-codice" id="nuovoCodice" name="nuovoCodice"
                           maxlength="40" required>
                  </div>
                  <div class="col-md-5">
                    <label for="motivo" class="form-label">Motivo</label>
                    <input type="text" class="form-control" id="motivo" name="motivo" maxlength="120"
                           value="rinumerazione">
                  </div>
                  <div class="col-md-4">
                    <button type="submit" class="btn btn-outline-danger"
                            data-catageo-conferma="Cambiare il codice rinomina cartella, sottocartelle e tutti i file. Il vecchio codice restera risolvibile. Procedere?">
                      <i class="bi bi-123"></i> Cambia codice
                    </button>
                  </div>
                  <div class="col-12">
                    <div class="catageo-nota">
                      Rinomina cartella, sottocartelle e tutti i file contenuti. Il
                      codice precedente viene conservato in scheda e continua a
                      risolvere verso questa: i codici citati in pubblicazioni
                      cartacee non si possono aggiornare.
                    </div>
                  </div>
                </form>
              <?php endif; ?>

              <?php if (Auth::puo('elimina_ipogeo')): ?>
                <form method="post" action="index.php?p=ipogei">
                  <?= Auth::campoToken() ?>
                  <input type="hidden" name="operazione" value="elimina">
                  <input type="hidden" name="codice" value="<?= Testo::esc($codiceCorrente) ?>">
                  <button type="submit" class="btn btn-outline-danger"
                          data-catageo-conferma="Rimuovere l'ipogeo dal catalogo? L'archivio non viene cancellato: viene spostato in _eliminati e resta recuperabile.">
                    <i class="bi bi-trash"></i> Rimuovi dal catalogo
                  </button>
                  <div class="catageo-nota mt-1">
                    Nessuna cancellazione: l'albero viene spostato in
                    <span class="catageo-valore"><?= Ipogeo::CARTELLA_ELIMINATI ?></span>
                    e resta recuperabile a mano. Il codice non verra mai riassegnato.
                  </div>
                </form>
              <?php endif; ?>

            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <?php
    return; // scheda mostrata
}

// ============================================================================
//  FORM: censimento e modifica
// ============================================================================
if ($azione === 'nuovo' || ($azione === 'modifica' && $codice !== '')) {

    Auth::esigi('modifica_scheda');

    $inModifica = null;
    if ($azione === 'modifica') {
        $inModifica = Ipogeo::trova($codice);
        if ($inModifica === null) {
            Auth::messaggio('errore', 'Ipogeo non trovato: ' . $codice);
            header('Location: index.php?p=ipogei');
            exit;
        }
    }

    $s = $inModifica ?? Ipogeo::template();
    $titolo = $inModifica !== null ? 'Modifica ' . $codice : 'Nuovo ipogeo';

    /** Valore di un campo, dando la precedenza a quanto rinviato dal POST. */
    $v = static function (string $nome, mixed $valore): string {
        return Testo::esc((string) ($_POST[$nome] ?? $valore));
    };

    $siglaAttiva = Cataloghi::siglaAttiva();
    ?>

    <div class="catageo-intestazione">
      <h1><?= Testo::esc($titolo) ?></h1>
      <a class="btn btn-outline-secondary" href="<?= $inModifica !== null
          ? 'index.php?p=ipogei&amp;azione=scheda&amp;codice=' . urlencode($codice)
          : 'index.php?p=ipogei' ?>">
        <i class="bi bi-x-lg"></i> Annulla
      </a>
    </div>

    <form method="post" action="index.php?p=ipogei" class="needs-validation" novalidate>
      <?= Auth::campoToken() ?>
      <input type="hidden" name="operazione" value="<?= $inModifica !== null ? 'aggiorna' : 'crea' ?>">
      <?php if ($inModifica !== null): ?>
        <input type="hidden" name="codice" value="<?= Testo::esc($codice) ?>">
      <?php endif; ?>

      <div class="row g-4">

        <!-- ------------------------------------------------ identificazione -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">Identificazione</h2></div>
            <div class="card-body">
              <div class="row g-3">

                <?php if ($inModifica === null): ?>
                  <div class="col-md-6">
                    <label for="catalogo" class="form-label">Catalogo <span class="text-danger">*</span></label>
                    <select class="form-select" id="catalogo" name="catalogo" required>
                      <?php foreach ($cataloghi as $c): ?>
                        <?php if (!$c['attivo']) { continue; } ?>
                        <option value="<?= Testo::esc((string) $c['sigla']) ?>"
                          <?= (string) $c['sigla'] === $siglaAttiva ? 'selected' : '' ?>>
                          <?= Testo::esc((string) $c['sigla'] . ' — ' . (string) $c['nome']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="catageo-nota">Il codice viene assegnato dalla serie del catalogo.</div>
                  </div>
                  <div class="col-md-6">
                    <label for="codiceManuale" class="form-label">Codice manuale</label>
                    <input type="text" class="form-control catageo-codice" id="codiceManuale" name="codiceManuale"
                           maxlength="40" value="<?= Testo::esc((string) ($_POST['codiceManuale'] ?? '')) ?>">
                    <div class="catageo-nota">
                      Lasciare vuoto per l'assegnazione automatica. Da usare per
                      importare un catasto esistente conservandone la numerazione,
                      se il catalogo lo consente.
                    </div>
                  </div>
                <?php else: ?>
                  <div class="col-md-6">
                    <label class="form-label">Codice</label>
                    <input type="text" class="form-control catageo-codice"
                           value="<?= Testo::esc($codice) ?>" disabled>
                    <div class="catageo-nota">Si cambia dalle operazioni riservate, nel tab Storico.</div>
                  </div>
                <?php endif; ?>

                <div class="col-12">
                  <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="nome" name="nome" required maxlength="150"
                         value="<?= $v('nome', $s['identificazione']['nome']) ?>">
                  <div class="invalid-feedback">Il nome e obbligatorio.</div>
                </div>

                <div class="col-12">
                  <label for="sinonimi" class="form-label">Sinonimi</label>
                  <input type="text" class="form-control" id="sinonimi" name="sinonimi"
                         value="<?= $v('sinonimi', implode(', ', (array) $s['identificazione']['sinonimi'])) ?>">
                  <div class="catageo-nota">Separati da virgola. Vengono cercati insieme al nome.</div>
                </div>

                <div class="col-md-4">
                  <label for="natura" class="form-label">Natura <span class="text-danger">*</span></label>
                  <select class="form-select" id="natura" name="natura" required>
                    <option value="">—</option>
                    <?php foreach (Tipologie::perLivello('natura', '', true) as $n): ?>
                      <option value="<?= Testo::esc($n['codice']) ?>"
                        <?= (string) ($_POST['natura'] ?? $s['identificazione']['natura']) === $n['codice'] ? 'selected' : '' ?>>
                        <?= Testo::esc($n['nome']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="invalid-feedback">Obbligatoria.</div>
                </div>

                <div class="col-md-4">
                  <label for="tipologia" class="form-label">Tipologia <span class="text-danger">*</span></label>
                  <select class="form-select" id="tipologia" name="tipologia" required>
                    <option value="">—</option>
                    <?php foreach (Tipologie::perLivello('tipologia', '', true) as $t): ?>
                      <option value="<?= Testo::esc($t['codice']) ?>"
                        <?= (string) ($_POST['tipologia'] ?? $s['identificazione']['tipologia']) === $t['codice'] ? 'selected' : '' ?>>
                        <?= Testo::esc($t['codice'] . ' — ' . $t['nome']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="invalid-feedback">Obbligatoria.</div>
                </div>

                <div class="col-md-4">
                  <label for="sottotipologia" class="form-label">Sottotipologia</label>
                  <select class="form-select" id="sottotipologia" name="sottotipologia">
                    <option value="">—</option>
                    <?php foreach (Tipologie::perLivello('sotto', '', true) as $t): ?>
                      <option value="<?= Testo::esc($t['codice']) ?>"
                        <?= (string) ($_POST['sottotipologia'] ?? $s['identificazione']['sottotipologia']) === $t['codice'] ? 'selected' : '' ?>>
                        <?= Testo::esc($t['codice'] . ' — ' . $t['nome']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="catageo-nota">
                    La tipologia determina anche quale serie di codifica assegna il codice.
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- ----------------------------------------------------- ubicazione -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">Ubicazione</h2></div>
            <div class="card-body">
              <div class="row g-3">

                <div class="col-md-3">
                  <label for="stato" class="form-label">Stato</label>
                  <input type="text" class="form-control catageo-valore" id="stato" name="stato" maxlength="2"
                         value="<?= $v('stato', $s['ubicazione']['stato']) ?>">
                  <div class="catageo-nota">ISO, 2 lettere.</div>
                </div>
                <div class="col-md-9">
                  <label for="statoNome" class="form-label">Nome dello stato</label>
                  <input type="text" class="form-control" id="statoNome" name="statoNome" maxlength="60"
                         value="<?= $v('statoNome', $s['ubicazione']['statoNome']) ?>">
                  <div class="catageo-nota">
                    Fuori dall'Italia regione e provincia valgono come divisioni
                    amministrative locali.
                  </div>
                </div>

                <div class="col-md-6">
                  <label for="regione" class="form-label">Regione</label>
                  <input type="text" class="form-control" id="regione" name="regione" maxlength="60"
                         value="<?= $v('regione', $s['ubicazione']['regione']) ?>">
                </div>
                <div class="col-md-2">
                  <label for="provincia" class="form-label">Prov.</label>
                  <input type="text" class="form-control catageo-valore" id="provincia" name="provincia" maxlength="4"
                         value="<?= $v('provincia', $s['ubicazione']['provincia']) ?>">
                </div>
                <div class="col-md-4">
                  <label for="comune" class="form-label">Comune</label>
                  <input type="text" class="form-control" id="comune" name="comune" maxlength="80"
                         value="<?= $v('comune', $s['ubicazione']['comune']) ?>">
                </div>

                <div class="col-md-6">
                  <label for="localita" class="form-label">Localita</label>
                  <input type="text" class="form-control" id="localita" name="localita" maxlength="120"
                         value="<?= $v('localita', $s['ubicazione']['localita']) ?>">
                </div>
                <div class="col-md-6">
                  <label for="indirizzo" class="form-label">Indirizzo</label>
                  <input type="text" class="form-control" id="indirizzo" name="indirizzo" maxlength="150"
                         value="<?= $v('indirizzo', $s['ubicazione']['indirizzo']) ?>">
                </div>

                <div class="col-md-4">
                  <label for="latitudine" class="form-label">Latitudine <span class="text-danger">*</span></label>
                  <input type="text" class="form-control catageo-valore" id="latitudine" name="latitudine" required
                         placeholder="41.856231"
                         value="<?= $v('latitudine', $s['ubicazione']['coordinate']['latitudine']) ?>">
                  <div class="invalid-feedback">Obbligatoria.</div>
                </div>
                <div class="col-md-4">
                  <label for="longitudine" class="form-label">Longitudine <span class="text-danger">*</span></label>
                  <input type="text" class="form-control catageo-valore" id="longitudine" name="longitudine" required
                         placeholder="12.532104"
                         value="<?= $v('longitudine', $s['ubicazione']['coordinate']['longitudine']) ?>">
                  <div class="invalid-feedback">Obbligatoria.</div>
                </div>
                <div class="col-md-2">
                  <label for="quota" class="form-label">Quota</label>
                  <input type="text" class="form-control catageo-valore" id="quota" name="quota"
                         value="<?= $v('quota', $s['ubicazione']['coordinate']['quota']) ?>">
                </div>
                <div class="col-md-2">
                  <label for="precisione" class="form-label">Prec. m</label>
                  <input type="text" class="form-control catageo-valore" id="precisione" name="precisione"
                         value="<?= $v('precisione', $s['ubicazione']['coordinate']['precisione']) ?>">
                </div>

                <div class="col-md-4">
                  <label for="metodo" class="form-label">Metodo di rilevamento</label>
                  <select class="form-select" id="metodo" name="metodo">
                    <option value="">—</option>
                    <?php foreach (Ipogeo::METODI_COORDINATE as $m): ?>
                      <option value="<?= Testo::esc($m) ?>"
                        <?= (string) ($_POST['metodo'] ?? $s['ubicazione']['coordinate']['metodo']) === $m ? 'selected' : '' ?>>
                        <?= Testo::esc($m) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="dataRilevamento" class="form-label">Data rilevamento</label>
                  <input type="date" class="form-control" id="dataRilevamento" name="dataRilevamento"
                         value="<?= $v('dataRilevamento', $s['ubicazione']['coordinate']['dataRilevamento']) ?>">
                </div>
                <div class="col-md-4">
                  <label for="riservatezza" class="form-label">Riservatezza</label>
                  <select class="form-select" id="riservatezza" name="riservatezza">
                    <?php foreach (Ipogeo::RISERVATEZZE as $r): ?>
                      <option value="<?= Testo::esc($r) ?>"
                        <?= (string) ($_POST['riservatezza'] ?? $s['ubicazione']['riservatezza']) === $r ? 'selected' : '' ?>>
                        <?= Testo::esc(str_replace('_', ' ', $r)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- ------------------------------------------------------- accesso -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">Accesso e percorribilita</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="statoAccesso" class="form-label">Stato dell'accesso</label>
                  <select class="form-select" id="statoAccesso" name="statoAccesso">
                    <?php foreach (Ipogeo::STATI_ACCESSO as $st): ?>
                      <option value="<?= Testo::esc($st) ?>"
                        <?= (string) ($_POST['statoAccesso'] ?? $s['ubicazione']['accesso']['stato']) === $st ? 'selected' : '' ?>>
                        <?= Testo::esc(str_replace('_', ' ', $st)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="proprieta" class="form-label">Proprieta</label>
                  <input type="text" class="form-control" id="proprieta" name="proprieta" maxlength="120"
                         value="<?= $v('proprieta', $s['ubicazione']['accesso']['proprieta']) ?>">
                </div>
                <div class="col-12">
                  <label for="descrizioneAccesso" class="form-label">Come si raggiunge</label>
                  <textarea class="form-control" id="descrizioneAccesso" name="descrizioneAccesso" rows="3"><?= Testo::esc((string) ($_POST['descrizioneAccesso'] ?? $s['ubicazione']['accesso']['descrizione'])) ?></textarea>
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="permessiNecessari"
                           name="permessiNecessari" value="1"
                           <?= !empty($_POST['permessiNecessari']) || (!isset($_POST['operazione']) && !empty($s['ubicazione']['accesso']['permessiNecessari'])) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="permessiNecessari">Serve un'autorizzazione</label>
                  </div>
                </div>
                <div class="col-12">
                  <label for="riferimentoPermessi" class="form-label">Riferimento per i permessi</label>
                  <input type="text" class="form-control" id="riferimentoPermessi" name="riferimentoPermessi" maxlength="200"
                         value="<?= $v('riferimentoPermessi', $s['ubicazione']['accesso']['riferimentoPermessi']) ?>">
                </div>
                <div class="col-md-6">
                  <label for="difficolta" class="form-label">Difficolta</label>
                  <input type="text" class="form-control" id="difficolta" name="difficolta" maxlength="60"
                         value="<?= $v('difficolta', $s['caratteristiche']['percorribilita']['difficolta']) ?>">
                </div>
                <div class="col-md-6">
                  <label for="tempoPercorrenza" class="form-label">Tempo di percorrenza</label>
                  <input type="text" class="form-control" id="tempoPercorrenza" name="tempoPercorrenza" maxlength="60"
                         value="<?= $v('tempoPercorrenza', $s['caratteristiche']['percorribilita']['tempoPercorrenza']) ?>">
                </div>
                <div class="col-12">
                  <label for="attrezzaturaNecessaria" class="form-label">Attrezzatura necessaria</label>
                  <textarea class="form-control" id="attrezzaturaNecessaria" name="attrezzaturaNecessaria" rows="2"><?= Testo::esc((string) ($_POST['attrezzaturaNecessaria'] ?? $s['caratteristiche']['percorribilita']['attrezzaturaNecessaria'])) ?></textarea>
                </div>
                <div class="col-12">
                  <label for="pericoli" class="form-label">Pericoli</label>
                  <textarea class="form-control" id="pericoli" name="pericoli" rows="2"><?= Testo::esc((string) ($_POST['pericoli'] ?? $s['caratteristiche']['percorribilita']['pericoli'])) ?></textarea>
                  <div class="catageo-nota">Se compilato, compare come avviso in evidenza sulla scheda.</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ------------------------------------------------------- misure -->
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">Misure e caratteristiche</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <?php
                $campiMisura = [
                    'sviluppoPlanimetrico' => 'Sviluppo planimetrico (m)',
                    'sviluppoSpaziale'     => 'Sviluppo spaziale (m)',
                    'dislivelloPositivo'   => 'Dislivello positivo (m)',
                    'dislivelloNegativo'   => 'Dislivello negativo (m)',
                    'profonditaMassima'    => 'Profondita massima (m)',
                    'numeroIngressi'       => 'Numero di ingressi',
                ];
                foreach ($campiMisura as $campo => $etichetta): ?>
                  <div class="col-md-6">
                    <label for="<?= $campo ?>" class="form-label"><?= $etichetta ?></label>
                    <input type="text" class="form-control catageo-valore" id="<?= $campo ?>" name="<?= $campo ?>"
                           value="<?= $v($campo, $s['caratteristiche'][$campo]) ?>">
                  </div>
                <?php endforeach; ?>

                <div class="col-md-6">
                  <label for="presenzaAcqua" class="form-label">Presenza d'acqua</label>
                  <select class="form-select" id="presenzaAcqua" name="presenzaAcqua">
                    <option value="">—</option>
                    <?php foreach (Ipogeo::PRESENZA_ACQUA as $a): ?>
                      <option value="<?= Testo::esc($a) ?>"
                        <?= (string) ($_POST['presenzaAcqua'] ?? $s['caratteristiche']['idrologia']['presenzaAcqua']) === $a ? 'selected' : '' ?>>
                        <?= Testo::esc($a) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="interesse" class="form-label">Interesse</label>
                  <input type="text" class="form-control" id="interesse" name="interesse"
                         value="<?= $v('interesse', implode(', ', (array) $s['caratteristiche']['interesse'])) ?>">
                  <div class="catageo-nota">Separato da virgola: archeologico, storico, biologico…</div>
                </div>
                <div class="col-12">
                  <label for="noteIdrologia" class="form-label">Note idrologiche</label>
                  <textarea class="form-control" id="noteIdrologia" name="noteIdrologia" rows="2"><?= Testo::esc((string) ($_POST['noteIdrologia'] ?? $s['caratteristiche']['idrologia']['note'])) ?></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ------------------------------------------------------ ingressi -->
        <div class="col-12">
          <div class="card">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">Ingressi</h2></div>
            <div class="card-body">
              <p class="catageo-nota">
                Le coordinate del singolo ingresso servono quando sono piu di uno:
                in mappa verranno mostrati distinti.
              </p>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th style="width:30%">Descrizione</th><th>Latitudine</th><th>Longitudine</th>
                      <th>Quota</th><th>Dimensioni</th><th>Stato</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $ingressiForm = (array) ($_POST['ingressi'] ?? $s['caratteristiche']['ingressi']);
                    for ($i = 0; $i < count($ingressiForm) + RIGHE_INGRESSO_LIBERE; $i++):
                        $ing = $ingressiForm[$i] ?? ['descrizione' => '', 'latitudine' => '', 'longitudine' => '',
                                                     'quota' => '', 'dimensioni' => '', 'stato' => ''];
                    ?>
                      <tr>
                        <td><input type="text" class="form-control form-control-sm" name="ingressi[<?= $i ?>][descrizione]"
                                   value="<?= Testo::esc((string) ($ing['descrizione'] ?? '')) ?>"></td>
                        <td><input type="text" class="form-control form-control-sm catageo-valore" name="ingressi[<?= $i ?>][latitudine]"
                                   value="<?= Testo::esc((string) ($ing['latitudine'] ?? '')) ?>"></td>
                        <td><input type="text" class="form-control form-control-sm catageo-valore" name="ingressi[<?= $i ?>][longitudine]"
                                   value="<?= Testo::esc((string) ($ing['longitudine'] ?? '')) ?>"></td>
                        <td><input type="text" class="form-control form-control-sm catageo-valore" name="ingressi[<?= $i ?>][quota]"
                                   value="<?= Testo::esc((string) ($ing['quota'] ?? '')) ?>"></td>
                        <td><input type="text" class="form-control form-control-sm" name="ingressi[<?= $i ?>][dimensioni]"
                                   value="<?= Testo::esc((string) ($ing['dimensioni'] ?? '')) ?>"></td>
                        <td>
                          <select class="form-select form-select-sm" name="ingressi[<?= $i ?>][stato]">
                            <option value="">—</option>
                            <?php foreach (Ipogeo::STATI_ACCESSO as $st): ?>
                              <option value="<?= Testo::esc($st) ?>" <?= (string) ($ing['stato'] ?? '') === $st ? 'selected' : '' ?>>
                                <?= Testo::esc(str_replace('_', ' ', $st)) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                      </tr>
                    <?php endfor; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- --------------------------------------------------- descrizione -->
        <div class="col-12">
          <div class="card">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">Descrizione</h2></div>
            <div class="card-body">
              <p class="catageo-nota">
                Questi campi non hanno limiti di lunghezza: il testo viene
                conservato integralmente, e negli elenchi si mostra un estratto
                calcolato al momento.
              </p>
              <div class="row g-3">
                <div class="col-12">
                  <label for="sintesi" class="form-label">Sintesi</label>
                  <textarea class="form-control" id="sintesi" name="sintesi" rows="2"><?= Testo::esc((string) ($_POST['sintesi'] ?? $s['descrizione']['sintesi'])) ?></textarea>
                </div>
                <div class="col-12">
                  <label for="testo" class="form-label">Descrizione estesa</label>
                  <textarea class="form-control" id="testo" name="testo" rows="8"><?= Testo::esc((string) ($_POST['testo'] ?? $s['descrizione']['testo'])) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label for="storia" class="form-label">Storia</label>
                  <textarea class="form-control" id="storia" name="storia" rows="4"><?= Testo::esc((string) ($_POST['storia'] ?? $s['descrizione']['storia'])) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label for="note" class="form-label">Note</label>
                  <textarea class="form-control" id="note" name="note" rows="4"><?= Testo::esc((string) ($_POST['note'] ?? $s['descrizione']['note'])) ?></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ------------------------------------------------------- catasto -->
        <div class="col-12">
          <div class="card">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">Dati di catasto</h2></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label for="dataCensimento" class="form-label">Data del censimento</label>
                  <input type="date" class="form-control" id="dataCensimento" name="dataCensimento"
                         value="<?= $v('dataCensimento', $s['catasto']['dataCensimento'] !== '' ? $s['catasto']['dataCensimento'] : date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                  <label for="censitoDa" class="form-label">Censito da</label>
                  <select class="form-select" id="censitoDa" name="censitoDa">
                    <option value="">—</option>
                    <?php foreach (Esploratori::elenco(true) as $e): ?>
                      <option value="<?= Testo::esc((string) $e['id']) ?>"
                        <?= (string) ($_POST['censitoDa'] ?? $s['catasto']['censitoDa']) === (string) $e['id'] ? 'selected' : '' ?>>
                        <?= Testo::esc(Esploratori::etichetta($e)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="gruppoCensore" class="form-label">Gruppo</label>
                  <select class="form-select" id="gruppoCensore" name="gruppoCensore">
                    <option value="">—</option>
                    <?php foreach (Gruppi::elenco(true) as $g): ?>
                      <option value="<?= Testo::esc((string) $g['id']) ?>"
                        <?= (string) ($_POST['gruppoCensore'] ?? $s['catasto']['gruppoCensore']) === (string) $g['id'] ? 'selected' : '' ?>>
                        <?= Testo::esc(Gruppi::etichetta($g)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="statoScheda" class="form-label">Stato della scheda</label>
                  <select class="form-select" id="statoScheda" name="statoScheda">
                    <?php foreach (Ipogeo::STATI_SCHEDA as $st): ?>
                      <option value="<?= Testo::esc($st) ?>"
                        <?= (string) ($_POST['statoScheda'] ?? $s['catasto']['statoScheda']) === $st ? 'selected' : '' ?>>
                        <?= Testo::esc($st) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="catageo-nota">Le bozze non sono visibili al livello utente.</div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="d-flex gap-2 my-4">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg"></i> <?= $inModifica !== null ? 'Salva scheda' : 'Censisci ipogeo' ?>
        </button>
        <a class="btn btn-outline-secondary" href="<?= $inModifica !== null
            ? 'index.php?p=ipogei&amp;azione=scheda&amp;codice=' . urlencode($codice)
            : 'index.php?p=ipogei' ?>">Annulla</a>
      </div>
    </form>

    <?php
    return;
}

// ============================================================================
//  ELENCO
// ============================================================================

$cerca         = isset($_GET['cerca']) ? trim((string) $_GET['cerca']) : '';
$filtroCatalogo = isset($_GET['catalogo']) ? Cataloghi::normalizzaSigla((string) $_GET['catalogo']) : '';
$pagina        = max(1, (int) ($_GET['pagina'] ?? 1));

$cercaNorm = Testo::normalizzaRicerca($cerca);

/** Filtro applicato in streaming sull'indice. */
$filtro = static function (array $riga) use ($cercaNorm, $filtroCatalogo): bool {
    if (!schedaVisibile((string) ($riga['riservatezza'] ?? ''), (string) ($riga['stato_scheda'] ?? ''))) {
        return false;
    }
    if ($filtroCatalogo !== '' && strcasecmp((string) ($riga['catalogo'] ?? ''), $filtroCatalogo) !== 0) {
        return false;
    }
    if ($cercaNorm === '') {
        return true;
    }
    foreach (['codice', 'nome', 'comune', 'localita'] as $campo) {
        if (str_contains(Testo::normalizzaRicerca((string) ($riga[$campo] ?? '')), $cercaNorm)) {
            return true;
        }
    }
    return false;
};

$totale = IndiceIpogei::conta($filtro);
$pagine = max(1, (int) ceil($totale / IPOGEI_PER_PAGINA));
$pagina = min($pagina, $pagine);
$righe  = IndiceIpogei::elenco($filtro, IPOGEI_PER_PAGINA, ($pagina - 1) * IPOGEI_PER_PAGINA);
?>

<div class="catageo-intestazione">
  <div>
    <h1>Ipogei</h1>
    <p class="text-body-secondary mb-0">
      <?= $totale ?> ipogeo<?= $totale === 1 ? '' : 'i' ?>
      <?php if ($cerca !== '' || $filtroCatalogo !== ''): ?>
        su <?= IndiceIpogei::conta() ?> in archivio
      <?php endif; ?>
    </p>
  </div>
  <?php if (Auth::puo('modifica_scheda')): ?>
    <a class="btn btn-primary" href="index.php?p=ipogei&amp;azione=nuovo">
      <i class="bi bi-plus-lg"></i> Nuovo ipogeo
    </a>
  <?php endif; ?>
</div>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <input type="hidden" name="p" value="ipogei">
  <div class="col-md-5">
    <label for="cerca" class="form-label">Cerca</label>
    <input type="search" class="form-control" id="cerca" name="cerca"
           placeholder="codice, nome, comune, localita" value="<?= Testo::esc($cerca) ?>">
  </div>
  <div class="col-md-4">
    <label for="filtroCatalogo" class="form-label">Catalogo</label>
    <select class="form-select" id="filtroCatalogo" name="catalogo">
      <option value="">tutti i cataloghi</option>
      <?php foreach ($cataloghi as $c): ?>
        <option value="<?= Testo::esc((string) $c['sigla']) ?>"
          <?= $filtroCatalogo === (string) $c['sigla'] ? 'selected' : '' ?>>
          <?= Testo::esc((string) $c['sigla'] . ' — ' . (string) $c['nome']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtra</button>
  </div>
</form>

<?php if ($righe === []): ?>
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="bi bi-safe fs-1 text-body-tertiary" aria-hidden="true"></i>
      <p class="mt-3 mb-3 text-body-secondary">
        <?= $cerca !== '' || $filtroCatalogo !== ''
            ? 'Nessun ipogeo corrisponde ai filtri.'
            : 'Nessun ipogeo censito.' ?>
      </p>
      <?php if (Auth::puo('modifica_scheda') && $cerca === '' && $filtroCatalogo === ''): ?>
        <?php if (Cataloghi::conta(true) === 0): ?>
          <p class="catageo-nota">Serve prima un catalogo attivo con le sue serie di codifica.</p>
          <?php if (Auth::puo('gestisci_cataloghi')): ?>
            <a class="btn btn-primary" href="index.php?p=cataloghi&amp;azione=nuovo">
              <i class="bi bi-collection"></i> Crea un catalogo
            </a>
          <?php endif; ?>
        <?php else: ?>
          <a class="btn btn-primary" href="index.php?p=ipogei&amp;azione=nuovo">
            <i class="bi bi-plus-lg"></i> Censisci il primo ipogeo
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover catageo-tabella mb-0 align-middle">
        <thead>
          <tr>
            <th scope="col">Codice</th>
            <th scope="col">Nome</th>
            <th scope="col">Tipologia</th>
            <th scope="col">Comune</th>
            <th scope="col" class="text-end">Sviluppo</th>
            <th scope="col">Risorse</th>
            <th scope="col">Stato</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($righe as $riga): ?>
            <tr>
              <td>
                <a class="catageo-codice text-decoration-none"
                   href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode((string) $riga['codice']) ?>">
                  <?= Testo::esc((string) $riga['codice']) ?>
                </a>
                <div class="small text-body-secondary"><?= Testo::esc((string) $riga['catalogo']) ?></div>
              </td>
              <td>
                <a class="text-decoration-none"
                   href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode((string) $riga['codice']) ?>">
                  <?= Testo::esc((string) $riga['nome']) ?>
                </a>
                <?php if ((string) $riga['localita'] !== ''): ?>
                  <div class="small text-body-secondary"><?= Testo::esc((string) $riga['localita']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small"><?= Testo::esc(Tipologie::nome((string) $riga['tipologia'])) ?></td>
              <td class="small"><?= Testo::esc((string) $riga['comune']) ?></td>
              <td class="text-end catageo-valore">
                <?= (string) $riga['sviluppo'] !== '' ? Testo::esc((string) $riga['sviluppo']) . ' m' : '—' ?>
              </td>
              <td class="small text-nowrap">
                <?php
                $risorse = [
                    'FO' => ['bi-camera', (int) $riga['n_foto']],
                    'RI' => ['bi-rulers', (int) $riga['n_rilievi']],
                    'AL' => ['bi-paperclip', (int) $riga['n_allegati']],
                    'ES' => ['bi-journal-text', (int) $riga['n_esplorazioni']],
                ];
                $qualcosa = false;
                foreach ($risorse as $sigla => [$icona, $quante]):
                    if ($quante <= 0) { continue; }
                    $qualcosa = true; ?>
                  <span class="text-body-secondary me-2" title="<?= Testo::esc(Sezioni::etichetta($sigla)) ?>">
                    <i class="bi <?= $icona ?>"></i> <?= $quante ?>
                  </span>
                <?php endforeach; ?>
                <?php if (!$qualcosa): ?><span class="text-body-tertiary">—</span><?php endif; ?>
              </td>
              <td>
                <?php
                $classeStato = match ((string) $riga['stato_scheda']) {
                    'pubblicata' => 'text-bg-success',
                    'verificata' => 'text-bg-info',
                    default      => 'text-bg-secondary',
                };
                ?>
                <span class="badge <?= $classeStato ?>"><?= Testo::esc((string) $riga['stato_scheda']) ?></span>
                <?php if ((string) $riga['riservatezza'] !== 'pubblica'): ?>
                  <i class="bi bi-shield-lock text-warning" title="<?= Testo::esc((string) $riga['riservatezza']) ?>"></i>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($pagine > 1): ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm mb-0">
        <?php for ($i = 1; $i <= $pagine; $i++): ?>
          <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
            <a class="page-link" href="index.php?p=ipogei&amp;pagina=<?= $i ?>&amp;cerca=<?= urlencode($cerca) ?>&amp;catalogo=<?= urlencode($filtroCatalogo) ?>">
              <?= $i ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
<?php endif; ?>
