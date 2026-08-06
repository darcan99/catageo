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
 *  Versione .....: 0.8.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.8.0  2026-08-05  D.Candela  Tracciati dei rilievi sulla mappetta e
 *                                collegamento alla pagina del rilievo.
 *  0.7.1  2026-08-05  D.Candela  Finestra dei media anche nella scheda; i
 *                                video si guardano invece di scaricarsi.
 *  0.7.0  2026-08-05  D.Candela  Contenuti delle sezioni nei rispettivi
 *                                pannelli, copertina in testa alla scheda.
 *  0.6.0  2026-08-05  D.Candela  Mappa nella scheda; regole di riservatezza
 *                                delegate a Visibilita.
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
                segnalaAvvisiCoordinate();
                Auth::messaggio('successo', 'Ipogeo censito con codice ' . $nuovo . '.');
                $ritorno = 'index.php?p=ipogei&azione=scheda&codice=' . urlencode($nuovo);
                break;

            case 'aggiorna':
                Auth::esigi('modifica_scheda');
                $daAggiornare = trim((string) ($_POST['codice'] ?? ''));
                Ipogeo::aggiorna($daAggiornare, datiSchedaDaPost());
                segnalaAvvisiCoordinate();
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
            'coordinate' => coordinateDaPost(),
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
 * Avvisi prodotti dall'interpretazione delle coordinate, da mostrare dopo il
 * salvataggio. Non sono errori: il dato viene accettato, ma vale la pena
 * segnalare cosa e stato dedotto o cosa insospettisce.
 *
 * @var string[]
 */
$GLOBALS['catageoAvvisiCoordinate'] = [];

/**
 * Interpreta le coordinate inserite nel formato e nel sistema dichiarati,
 * restituendo la forma canonica in gradi decimali WGS84 piu la memoria di come
 * il dato era stato rilevato.
 *
 * @return array<string,string>
 * @throws IpogeoEccezione
 */
function coordinateDaPost(): array
{
    try {
        $esito = Coordinate::interpreta([
            'formato'     => (string) ($_POST['formatoCoordinate'] ?? 'decimali'),
            'sistema'     => (string) ($_POST['sistemaCoordinate'] ?? Coordinate::CANONICO),
            'latitudine'  => (string) ($_POST['latitudine'] ?? ''),
            'longitudine' => (string) ($_POST['longitudine'] ?? ''),
            'est'         => (string) ($_POST['utmEst'] ?? ''),
            'nord'        => (string) ($_POST['utmNord'] ?? ''),
        ]);
    } catch (CoordinateEccezione $e) {
        // Si rilancia come errore di scheda: per chi compila e un problema
        // della scheda, non di una libreria di conversione.
        throw new IpogeoEccezione($e->getMessage(), 0, $e);
    }

    $GLOBALS['catageoAvvisiCoordinate'] = $esito['avvisi'];

    return [
        'latitudine'       => $esito['latitudine'],
        'longitudine'      => $esito['longitudine'],
        'quota'            => (string) ($_POST['quota'] ?? ''),
        'precisione'       => (string) ($_POST['precisione'] ?? ''),
        'metodo'           => (string) ($_POST['metodo'] ?? ''),
        'dataRilevamento'  => (string) ($_POST['dataRilevamento'] ?? ''),
        'sistemaOriginale' => $esito['sistemaOriginale'],
        'formatoOriginale' => $esito['formatoOriginale'],
        'valoreOriginale'  => $esito['valoreOriginale'],
    ];
}

/**
 * Accoda come messaggi gli avvisi maturati sulle coordinate.
 *
 * Legge con ripiego perche viene chiamata durante la gestione del POST, che nel
 * file precede l'inizializzazione della variabile.
 */
function segnalaAvvisiCoordinate(): void
{
    foreach (($GLOBALS['catageoAvvisiCoordinate'] ?? []) as $avviso) {
        Auth::messaggio('avviso', (string) $avviso);
    }
    $GLOBALS['catageoAvvisiCoordinate'] = [];
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
    // Le regole stanno in Visibilita: elenco, scheda e mappa devono decidere
    // allo stesso modo, e una regola di riservatezza applicata in due punti su
    // tre e una fuga di dati.
    return Visibilita::coordinate(
        (string) $scheda['ubicazione']['coordinate']['latitudine'],
        (string) $scheda['ubicazione']['coordinate']['longitudine'],
        (string) $scheda['ubicazione']['riservatezza']
    );
}

/** True se l'utente corrente puo vedere una scheda con la riservatezza data. */
function schedaVisibile(string $riservatezza, string $statoScheda): bool
{
    return Visibilita::schedaVisibile($riservatezza, $statoScheda);
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

    require_once CATAGEO_ROOT . '/app/view/parti-media.php';

    // La finestra dei media serve sempre nella scheda: foto e video si guardano
    // anche in sola consultazione.
    $jsPagina = ['assets/js/catageo-media.js'];

    // La cartografia si carica solo se c'e qualcosa da inquadrare: una scheda
    // senza coordinate non deve scaricare Leaflet per non mostrare nulla.
    $mappaScheda = $coord['lat'] !== '' && $coord['lon'] !== '';
    if ($mappaScheda) {
        $cssPagina = [
            'assets/vendor/leaflet-1.9.4/leaflet.css',
            'assets/css/catageo-mappa.css',
        ];
        $jsPagina = array_merge([
            'assets/vendor/leaflet-1.9.4/leaflet.js',
            'assets/vendor/proj4-2.21.0/proj4.min.js',
            'assets/js/catageo-mappa.js',
        ], $jsPagina);
    }
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
        <?php
        // Copertina: la prima cosa che si guarda aprendo una scheda, quando c'e.
        // Sta fuori dalla griglia e a tutta larghezza perche una foto stretta in
        // mezza colonna non fa vedere niente di una cavita.
        $copertina = Risorse::copertina($codiceCorrente);
        ?>
        <?php if ($copertina !== null): ?>
          <?php $pCop = (int) $copertina['progressivo']; ?>
          <div class="card mb-4">
            <?php
            // La copertina usa l'ORIGINALE e non la miniatura: la miniatura e
            // larga 400 px e qui verrebbe stirata su tutta la scheda, con un
            // risultato visibilmente sgranato. E una sola immagine per scheda,
            // quindi il peso in piu e accettabile; le gallerie, dove le immagini
            // sono decine, continuano a usare le miniature.
            ?>
            <a href="<?= Testo::esc(catageoUrlRisorsa($codiceCorrente, 'FO', $pCop, false, true)) ?>"
               <?= catageoAttributiMedia($copertina, $codiceCorrente, 'FO') ?>
               title="Guarda l'immagine">
              <img class="catageo-copertina"
                   src="<?= Testo::esc(catageoUrlRisorsa($codiceCorrente, 'FO', $pCop, false, true)) ?>"
                   alt="<?= Testo::esc((string) $copertina['titolo']) ?>">
            </a>
            <div class="card-body py-2 d-flex flex-wrap justify-content-between gap-2">
              <span class="text-body-secondary"><?= Testo::esc((string) $copertina['titolo']) ?></span>
              <?= catageoDatiMedia($copertina, false) ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="row g-4">
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header"><h2 class="h6 mb-0">Identificazione</h2></div>
              <div class="card-body">
                <dl class="row catageo-dl">
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
              <div class="card-header"><h2 class="h6 mb-0">Ubicazione</h2></div>
              <div class="card-body">
                <dl class="row catageo-dl">
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

                  <?php
                  // La stessa posizione nelle notazioni che si usano in campagna.
                  // Su coordinate offuscate si mostrano solo i gradi: dare anche
                  // l'UTM equivarrebbe a restituire la posizione al metro.
                  $rappresentazioni = null;
                  // La notazione con cui il catalogo e abituato a lavorare va
                  // per prima: per il Lazio l'UTM, non i gradi.
                  $catalogoScheda   = Cataloghi::trova((string) $scheda['catasto']['catalogo']);
                  $sistemaPreferito = (string) ($catalogoScheda['sistemaPreferito'] ?? '');

                  if (!$coord['offuscate'] && $coord['lat'] !== '' && $coord['lon'] !== '') {
                      try {
                          $rappresentazioni = Coordinate::rappresentazioni(
                              (float) $coord['lat'], (float) $coord['lon'], $sistemaPreferito
                          );
                      } catch (Throwable $e) {
                          $rappresentazioni = null;
                      }
                  }
                  ?>

                  <?php if ($rappresentazioni !== null && isset($rappresentazioni['preferito'])): ?>
                    <dt class="col-sm-5 fw-normal text-body-secondary">
                      <?= Testo::esc((string) $rappresentazioni['preferitoNome']) ?>
                    </dt>
                    <dd class="col-sm-7">
                      <span class="catageo-valore fw-semibold"><?= Testo::esc((string) $rappresentazioni['preferito']) ?></span>
                      <div class="catageo-nota">
                        Notazione del catalogo · <?= Testo::esc((string) $rappresentazioni['preferitoEpsg']) ?>
                      </div>
                    </dd>
                  <?php endif; ?>

                  <dt class="col-sm-5 fw-normal text-body-secondary">Coordinate WGS84</dt>
                  <dd class="col-sm-7">
                    <span class="catageo-valore"><?= Testo::esc($coord['lat']) ?>, <?= Testo::esc($coord['lon']) ?></span>
                    <?php if ($coord['offuscate']): ?>
                      <span class="badge text-bg-warning" title="Posizione approssimata per riservatezza">approssimata</span>
                    <?php endif; ?>
                  </dd>

                  <?php if ($rappresentazioni !== null): ?>
                    <dt class="col-sm-5 fw-normal text-body-secondary">Gradi sessagesimali</dt>
                    <dd class="col-sm-7"><span class="catageo-valore"><?= Testo::esc($rappresentazioni['gms']) ?></span></dd>

                    <?php if (!isset($rappresentazioni['preferito'])): ?>
                      <dt class="col-sm-5 fw-normal text-body-secondary">UTM WGS84</dt>
                      <dd class="col-sm-7">
                        <span class="catageo-valore"><?= Testo::esc($rappresentazioni['utm']) ?></span>
                        <div class="catageo-nota"><?= Testo::esc($rappresentazioni['utmEpsg']) ?>, calcolato dai gradi.</div>
                      </dd>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php
                  // Se il dato era stato rilevato in un altro sistema, va mostrato
                  // come dichiarato: e quello che fu letto sullo strumento.
                  $sistemaOrig = (string) $scheda['ubicazione']['coordinate']['sistemaOriginale'];
                  $valoreOrig  = (string) $scheda['ubicazione']['coordinate']['valoreOriginale'];
                  ?>
                  <?php if ($valoreOrig !== '' && !$coord['offuscate']): ?>
                    <dt class="col-sm-5 fw-normal text-body-secondary">Rilevato come</dt>
                    <dd class="col-sm-7">
                      <span class="catageo-valore"><?= Testo::esc($valoreOrig) ?></span>
                      <div class="catageo-nota">
                        <?= Testo::esc(Coordinate::nomeSistema($sistemaOrig)) ?>
                        <?php if (!Coordinate::convertibile($sistemaOrig)): ?>
                          — datum diverso da WGS84, conservato come dichiarato e non convertito
                        <?php endif; ?>
                      </div>
                    </dd>
                  <?php endif; ?>

                  <dt class="col-sm-5 fw-normal text-body-secondary">Quota</dt>
                  <dd class="col-sm-7">
                    <?= (string) $scheda['ubicazione']['coordinate']['quota'] !== ''
                        ? Testo::esc((string) $scheda['ubicazione']['coordinate']['quota']) . ' m s.l.m.'
                        : '<span class="text-body-tertiary">—</span>' ?>
                  </dd>

                  <?php
                  // Precisione e metodo dicono quanto fidarsi del punto: senza di
                  // loro una coordinata a sei decimali sembra esatta anche quando
                  // e stata dedotta da una descrizione.
                  $precisione = (string) $scheda['ubicazione']['coordinate']['precisione'];
                  $metodo     = (string) $scheda['ubicazione']['coordinate']['metodo'];
                  ?>
                  <dt class="col-sm-5 fw-normal text-body-secondary">Precisione</dt>
                  <dd class="col-sm-7">
                    <?php if ($precisione !== ''): ?>
                      &plusmn; <?= Testo::esc($precisione) ?> m
                      <div class="catageo-nota">Raggio entro cui cercare l'ingresso.</div>
                    <?php else: ?>
                      <span class="text-body-tertiary">non dichiarata</span>
                    <?php endif; ?>
                  </dd>

                  <dt class="col-sm-5 fw-normal text-body-secondary">Rilevamento</dt>
                  <dd class="col-sm-7">
                    <?= $metodo !== '' ? Testo::esc($metodo) : '<span class="text-body-tertiary">—</span>' ?>
                    <?php if ((string) $scheda['ubicazione']['coordinate']['dataRilevamento'] !== ''): ?>
                      <span class="text-body-secondary">
                        · <?= Testo::esc((string) $scheda['ubicazione']['coordinate']['dataRilevamento']) ?>
                      </span>
                    <?php endif; ?>
                  </dd>

                  <dt class="col-sm-5 fw-normal text-body-secondary">Riservatezza</dt>
                  <dd class="col-sm-7"><?= Testo::esc((string) $scheda['ubicazione']['riservatezza']) ?></dd>
                </dl>
              </div>
            </div>
          </div>

          <?php if ($mappaScheda): ?>
            <div class="col-lg-6">
              <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h2 class="h6 mb-0">Posizione</h2>
                  <a class="btn btn-sm btn-outline-secondary catageo-non-stampare"
                     href="index.php?p=mappa&amp;catalogo=<?= urlencode((string) $scheda['catasto']['catalogo']) ?>">
                    <i class="bi bi-map"></i> Mappa del catalogo
                  </a>
                </div>
                <div class="card-body">
                  <?php
                  // Se l'ipogeo ha rilievi georiferiti, la mappetta li sovrappone:
                  // e cio che distingue "dove si entra" da "dove va la cavita".
                  $conTracciati = Risorse::tracciati($codiceCorrente) !== [];
                  ?>
                  <div id="catageoMappaSchedaBox" class="catageo-mappa catageo-mappa-scheda"
                       <?= $conTracciati
                           ? 'data-catageo-tracciati="index.php?p=tracciato&amp;codice=' . urlencode($codiceCorrente) . '"'
                           : '' ?>></div>
                  <?php if ($conTracciati): ?>
                    <div class="catageo-nota mt-2">
                      <i class="bi bi-bezier2"></i>
                      Il tracciato in magenta viene dai rilievi georiferiti della scheda.
                    </div>
                  <?php endif; ?>
                  <?php if ($coord['offuscate']): ?>
                    <div class="catageo-nota mt-2">
                      Il cerchio indica l'area entro cui si trova l'ingresso: le coordinate
                      esatte sono riservate e non vengono inviate al browser.
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header"><h2 class="h6 mb-0">Caratteristiche</h2></div>
              <div class="card-body">
                <dl class="row catageo-dl">
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
              <div class="card-header"><h2 class="h6 mb-0">Dati di catasto</h2></div>
              <div class="card-body">
                <dl class="row catageo-dl">
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
            <div class="card-header"><h2 class="h6 mb-0">Ingressi</h2></div>
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
            <div class="card-header"><h2 class="h6 mb-0"><?= $etichetta ?></h2></div>
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

      <!-- ------------------------------------------------------- sezioni -->
      <?php foreach (Sezioni::sigle() as $sigla): ?>

        <?php
        /*
         * Le esplorazioni non sono file caricati ma documenti redatti, e il loro
         * indice ha una forma tutta sua: la sezione si presenta da se, con la
         * pagina dei diari al posto della gestione delle risorse.
         */
        if ($sigla === 'ES') {
            $diari = Esplorazioni::elenco($codiceCorrente);
            ?>
            <div class="tab-pane fade" id="tabSezioneES">
              <div class="catageo-intestazione mb-3">
                <div>
                  <h2 class="h5 mb-0">Esplorazioni</h2>
                  <p class="text-body-secondary mb-0">
                    <?= count($diari) ?> uscit<?= count($diari) === 1 ? 'a' : 'e' ?>
                  </p>
                </div>
                <a class="btn btn-sm <?= $diari === [] ? 'btn-primary' : 'btn-outline-secondary' ?> catageo-non-stampare"
                   href="index.php?p=esplorazione&amp;codice=<?= urlencode($codiceCorrente) ?>">
                  <i class="bi bi-journal-text"></i>
                  <?= $diari === [] ? 'Scrivi il primo diario' : 'Vedi i diari' ?>
                </a>
              </div>

              <?php if ($diari === []): ?>
                <div class="card">
                  <div class="card-body d-flex gap-3">
                    <i class="bi bi-journal-text fs-3 text-body-secondary" aria-hidden="true"></i>
                    <div>
                      <h3 class="h6 mb-1">Nessun diario</h3>
                      <p class="text-body-secondary mb-0">
                        Ogni uscita diventa un documento autonomo nella cartella
                        <span class="catageo-valore"><?= Testo::esc(Sezioni::nomeCartella($codiceCorrente, 'ES')) ?></span>.
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
                          <th style="width:5rem">Rif.</th>
                          <th style="width:7rem">Data</th>
                          <th>Titolo</th>
                          <th>Tipo</th>
                          <th class="text-end">Voci</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($diari as $d): ?>
                          <?php $pd = (int) $d['progressivo']; ?>
                          <tr>
                            <td><span class="catageo-valore"><?= Testo::esc(Sezioni::riferimento('ES', $pd)) ?></span></td>
                            <td><?= Testo::esc((string) $d['dataInizio']) ?></td>
                            <td>
                              <a href="index.php?p=esplorazione&amp;codice=<?= urlencode($codiceCorrente) ?>&amp;azione=vedi&amp;prog=<?= $pd ?>">
                                <?= Testo::esc((string) $d['titolo']) ?>
                              </a>
                            </td>
                            <td><?= Testo::esc(Esplorazioni::TIPI[(string) $d['tipo']] ?? (string) $d['tipo']) ?></td>
                            <td class="text-end catageo-valore"><?= (int) $d['voci'] ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            <?php
            continue;
        }
        ?>

        <?php
        $contenuti  = Risorse::elenco($codiceCorrente, $sigla);
        $caricabile = Sezioni::caricabile($sigla);
        $vista      = Sezioni::anteprima($sigla);
        $urlSezione = 'index.php?p=risorse&amp;codice=' . urlencode($codiceCorrente) . '&amp;sez=' . $sigla;

        /** Indirizzo di consegna, uguale a quello della pagina di gestione. */
        $urlFile = static function (int $prog, bool $mini = false, bool $inline = false) use ($codiceCorrente, $sigla): string {
            return 'scarica.php?codice=' . urlencode($codiceCorrente) . '&sez=' . $sigla . '&prog=' . $prog
                . ($mini ? '&mini=1' : '') . ($inline ? '&inline=1' : '');
        };
        ?>
        <div class="tab-pane fade" id="tabSezione<?= $sigla ?>">

          <div class="catageo-intestazione mb-3">
            <div>
              <h2 class="h5 mb-0"><?= Testo::esc(Sezioni::etichetta($sigla)) ?></h2>
              <p class="text-body-secondary mb-0">
                <?= count($contenuti) ?> element<?= count($contenuti) === 1 ? 'o' : 'i' ?>
              </p>
            </div>
            <?php if ($caricabile && Auth::puo('carica_risorse')): ?>
              <a class="btn btn-sm btn-primary catageo-non-stampare" href="<?= $urlSezione ?>">
                <i class="bi bi-upload"></i> Gestisci e carica
              </a>
            <?php elseif ($contenuti !== []): ?>
              <a class="btn btn-sm btn-outline-secondary catageo-non-stampare" href="<?= $urlSezione ?>">
                <i class="bi bi-list-ul"></i> Vedi tutto
              </a>
            <?php endif; ?>
          </div>

          <?php if ($contenuti === []): ?>

            <div class="card">
              <div class="card-body d-flex gap-3">
                <i class="bi <?= $caricabile ? 'bi-folder2-open text-body-secondary' : 'bi-cone-striped text-warning' ?> fs-3"
                   aria-hidden="true"></i>
                <div>
                  <?php if ($caricabile): ?>
                    <h3 class="h6 mb-1">Nessun contenuto</h3>
                    <p class="text-body-secondary mb-0">
                      La cartella
                      <span class="catageo-valore"><?= Testo::esc(Sezioni::nomeCartella($codiceCorrente, $sigla)) ?></span>
                      e pronta e attende il primo caricamento.
                    </p>
                  <?php else: ?>
                    <h3 class="h6 mb-1">In arrivo</h3>
                    <p class="text-body-secondary mb-2">
                      La cartella
                      <span class="catageo-valore"><?= Testo::esc(Sezioni::nomeCartella($codiceCorrente, $sigla)) ?></span>
                      esiste gia nell'archivio: la gestione dei contenuti di questa
                      sezione arriva in una fase successiva del piano.
                    </p>
                    <p class="catageo-nota mb-0">
                      Nel frattempo i file si possono depositare a mano nella cartella
                      rispettando lo standard di nomenclatura
                      <span class="catageo-valore"><?= Testo::esc($codiceCorrente) ?>-<?= $sigla ?>001-nome.est</span>:
                      verranno riconosciuti e conteggiati dall'indice.
                    </p>
                  <?php endif; ?>
                </div>
              </div>
            </div>

          <?php elseif ($vista === 'immagine'): ?>

            <div class="row g-3">
              <?php foreach ($contenuti as $foto): ?>
                <?php $p = (int) $foto['progressivo']; ?>
                <div class="col-6 col-md-4 col-xl-3">
                  <div class="card h-100">
                    <a href="<?= Testo::esc($urlFile($p, false, true)) ?>"
                       <?= catageoAttributiMedia($foto, $codiceCorrente, $sigla) ?>
                       title="Guarda l'immagine">
                      <img src="<?= Testo::esc($urlFile($p, true, true)) ?>"
                           alt="<?= Testo::esc((string) $foto['titolo']) ?>"
                           class="card-img-top catageo-miniatura" loading="lazy">
                    </a>
                    <div class="card-body py-2">
                      <div class="text-truncate" title="<?= Testo::esc((string) $foto['titolo']) ?>">
                        <?= Testo::esc((string) $foto['titolo']) ?>
                        <?php if (!empty($foto['copertina'])): ?>
                          <i class="bi bi-star-fill text-primary" title="copertina"></i>
                        <?php endif; ?>
                      </div>
                      <?= catageoDatiMedia($foto, true, $sigla) ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

          <?php else: ?>

            <div class="card">
              <div class="table-responsive">
                <table class="table catageo-tabella mb-0 align-middle">
                  <thead>
                    <tr>
                      <th style="width:5rem">Rif.</th>
                      <th>Titolo</th>
                      <th>File</th>
                      <th class="text-end">Dimensione</th>
                      <th>Data</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($contenuti as $risorsa): ?>
                      <?php $p = (int) $risorsa['progressivo']; ?>
                      <tr>
                        <td><span class="catageo-valore"><?= Testo::esc(Sezioni::riferimento($sigla, $p)) ?></span></td>
                        <td>
                          <?= Testo::esc((string) $risorsa['titolo']) ?>
                          <?php if ((string) $risorsa['categoriaAllegato'] !== ''): ?>
                            <span class="badge text-bg-light border"><?= Testo::esc((string) $risorsa['categoriaAllegato']) ?></span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if (Risorse::percorsoFile($codiceCorrente, $sigla, $p) !== null): ?>
                            <?php if ($vista === 'rilievo'): ?>
                              <?php // Il rilievo ha una pagina propria: modello
                                    // 3D, documento o tracciato sulla mappa. ?>
                              <a href="index.php?p=rilievo&amp;codice=<?= urlencode($codiceCorrente) ?>&amp;prog=<?= $p ?>">
                                <i class="bi bi-<?= Risorse::tridimensionale($risorsa) ? 'badge-3d' : 'file-earmark-ruled' ?>"></i>
                                <?= Testo::esc((string) $risorsa['file']) ?>
                              </a>
                            <?php elseif ($vista === 'video'): ?>
                              <?php // Il video si guarda nella finestra: prima
                                    // c'era solo il nome del file da scaricare. ?>
                              <a href="<?= Testo::esc($urlFile($p, false, true)) ?>"
                                 <?= catageoAttributiMedia($risorsa, $codiceCorrente, $sigla) ?>>
                                <i class="bi bi-play-circle"></i>
                                <?= Testo::esc((string) $risorsa['file']) ?>
                              </a>
                            <?php else: ?>
                              <a href="<?= Testo::esc($urlFile($p)) ?>"><?= Testo::esc((string) $risorsa['file']) ?></a>
                            <?php endif; ?>
                            <?= catageoDatiMedia($risorsa, false) ?>
                          <?php else: ?>
                            <span class="text-danger">
                              <i class="bi bi-exclamation-triangle-fill"></i>
                              <?= Testo::esc((string) $risorsa['file']) ?> — file mancante
                            </span>
                          <?php endif; ?>
                        </td>
                        <td class="text-end catageo-valore"><?= Testo::esc(Testo::dimensione((int) $risorsa['dimensione'])) ?></td>
                        <td><?= Testo::esc((string) $risorsa['data']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <!-- --------------------------------------------------------- Storico -->
      <div class="tab-pane fade" id="tabStorico">
        <div class="card">
          <div class="card-header">
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
            <div class="card-header"><h2 class="h6 mb-0">Operazioni riservate</h2></div>
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

    <?php if ($mappaScheda): ?>
      <?php
      // Il raggio del cerchio di offuscamento e lo stesso passo usato per
      // arrotondare: dichiarare un'area piu piccola di quella reale sarebbe
      // fuorviante, piu grande renderebbe il dato inutile.
      $puntoMappa = [
          'lat'       => $coord['lat'],
          'lon'       => $coord['lon'],
          'codice'    => $codiceCorrente,
          'nome'      => (string) $scheda['identificazione']['nome'],
          'natura'    => (string) $scheda['identificazione']['natura'],
          'offuscate' => $coord['offuscate'],
          'raggio'    => max(100, Config::intero('sicurezza.offuscamentoCoordinate', 1000)),
      ];
      ?>
      <script type="application/json" id="catageoMappaConfig"><?= Testo::escJson(Mappa::perBrowser()) ?></script>
      <script type="application/json" id="catageoMappaPunto"><?= Testo::escJson($puntoMappa) ?></script>
    <?php endif; ?>

    <?php require CATAGEO_ROOT . '/app/view/modale-media.php'; ?>

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
            <div class="card-header"><h2 class="h6 mb-0">Identificazione</h2></div>
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
            <div class="card-header"><h2 class="h6 mb-0">Ubicazione</h2></div>
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

                <div class="col-12"><hr class="my-1"></div>

                <?php
                // Formato e sistema in cui le coordinate vengono DIGITATE. In
                // archivio finiscono sempre in gradi decimali WGS84, ma il modo
                // in cui erano state rilevate viene conservato accanto.
                $formatoSel = (string) ($_POST['formatoCoordinate'] ?? ($s['ubicazione']['coordinate']['formatoOriginale'] ?: 'decimali'));
                $sistemaSel = (string) ($_POST['sistemaCoordinate'] ?? ($s['ubicazione']['coordinate']['sistemaOriginale'] ?: Coordinate::CANONICO));
                ?>

                <div class="col-md-6">
                  <label for="formatoCoordinate" class="form-label">Formato di inserimento</label>
                  <select class="form-select" id="formatoCoordinate" name="formatoCoordinate"
                          data-catageo-formato-coordinate>
                    <?php foreach (Coordinate::FORMATI as $codice => $etichetta): ?>
                      <option value="<?= Testo::esc($codice) ?>" <?= $formatoSel === $codice ? 'selected' : '' ?>>
                        <?= Testo::esc($etichetta) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-6">
                  <label for="sistemaCoordinate" class="form-label">Sistema di riferimento</label>
                  <select class="form-select" id="sistemaCoordinate" name="sistemaCoordinate"
                          data-catageo-sistema-coordinate>
                    <?php foreach (Coordinate::sistemi(true) as $codice => $dati): ?>
                      <?php $incertezza = (float) $dati['accuratezza']; ?>
                      <option value="<?= Testo::esc($codice) ?>" <?= $sistemaSel === $codice ? 'selected' : '' ?>
                              data-accuratezza="<?= Testo::esc((string) $incertezza) ?>">
                        <?= Testo::esc((string) $dati['nome']) ?><?= $incertezza >= 1.0
                            ? ' — conversione ±' . (int) $incertezza . ' m' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="catageo-nota">
                    I sistemi con datum diverso da WGS84 (Gauss-Boaga/Roma40, ED50)
                    vengono convertiti con i sette parametri di Helmert:
                    l'incertezza dichiarata accanto al nome si somma a quella del
                    rilievo. Il fuso e implicito nel sistema scelto.
                  </div>
                </div>

                <!-- Gradi, in decimali o sessagesimali -->
                <div class="col-md-4" data-catageo-formato="decimali gms gm">
                  <label for="latitudine" class="form-label">Latitudine <span class="text-danger">*</span></label>
                  <input type="text" class="form-control catageo-valore" id="latitudine" name="latitudine"
                         placeholder="41.856231 oppure 41&deg;51'22.4&quot;N"
                         value="<?= $v('latitudine', $s['ubicazione']['coordinate']['latitudine']) ?>">
                </div>
                <div class="col-md-4" data-catageo-formato="decimali gms gm">
                  <label for="longitudine" class="form-label">Longitudine <span class="text-danger">*</span></label>
                  <input type="text" class="form-control catageo-valore" id="longitudine" name="longitudine"
                         placeholder="12.532104 oppure 12&deg;29'31.9&quot;E"
                         value="<?= $v('longitudine', $s['ubicazione']['coordinate']['longitudine']) ?>">
                </div>

                <!-- Coordinate proiettate: il fuso e implicito nel sistema -->
                <div class="col-md-6" data-catageo-formato="proiettate">
                  <label for="utmEst" class="form-label">Est (m)</label>
                  <input type="text" class="form-control catageo-valore" id="utmEst" name="utmEst"
                         placeholder="295964"
                         value="<?= Testo::esc((string) ($_POST['utmEst'] ?? '')) ?>">
                </div>
                <div class="col-md-6" data-catageo-formato="proiettate">
                  <label for="utmNord" class="form-label">Nord (m)</label>
                  <input type="text" class="form-control catageo-valore" id="utmNord" name="utmNord"
                         placeholder="4678705"
                         value="<?= Testo::esc((string) ($_POST['utmNord'] ?? '')) ?>">
                </div>

                <!-- Anteprima dal vivo: la posizione convertita mentre si digita -->
                <div class="col-12">
                  <div id="catageoAnteprimaCoordinate" class="border rounded p-2 bg-body-tertiary" hidden>
                    <div class="catageo-nota mb-1">Posizione convertita</div>
                    <div class="row g-2 small" id="catageoAnteprimaValori"></div>
                  </div>
                </div>
                <div class="col-md-2">
                  <label for="quota" class="form-label">Quota (m)</label>
                  <input type="text" class="form-control catageo-valore" id="quota" name="quota"
                         value="<?= $v('quota', $s['ubicazione']['coordinate']['quota']) ?>">
                  <div class="catageo-nota">Sul livello del mare.</div>
                </div>
                <div class="col-md-2">
                  <label for="precisione" class="form-label">Precisione (m)</label>
                  <input type="text" class="form-control catageo-valore" id="precisione" name="precisione"
                         placeholder="5"
                         value="<?= $v('precisione', $s['ubicazione']['coordinate']['precisione']) ?>">
                  <div class="catageo-nota">
                    Incertezza della posizione: quanto raggio dovra battere chi va
                    a cercarla. GPS in bosco 5-10, punto su carta 1:25.000 circa 25,
                    posizione dedotta da una descrizione 100 o piu.
                  </div>
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
            <div class="card-header"><h2 class="h6 mb-0">Accesso e percorribilita</h2></div>
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
            <div class="card-header"><h2 class="h6 mb-0">Misure e caratteristiche</h2></div>
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
            <div class="card-header"><h2 class="h6 mb-0">Ingressi</h2></div>
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
            <div class="card-header"><h2 class="h6 mb-0">Descrizione</h2></div>
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
            <div class="card-header"><h2 class="h6 mb-0">Dati di catasto</h2></div>
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
    // Le definizioni dei sistemi passano al browser come dato, non come codice:
    // sono le stesse stringhe usate dal motore in PHP, prese dal vocabolario.
    // Una sola fonte per entrambe le implementazioni.
    $definizioniCrs = [];
    foreach (Coordinate::sistemi(true) as $codice => $dati) {
        $definizioniCrs[$codice] = [
            'def'         => (string) $dati['def'],
            'nome'        => (string) $dati['nome'],
            'accuratezza' => (float) $dati['accuratezza'],
        ];
    }
    ?>
    <script type="application/json" id="catageoDefinizioniCrs"><?= Testo::escJson($definizioniCrs) ?></script>
    <script src="assets/vendor/proj4-2.21.0/proj4.min.js"></script>
    <script src="assets/js/catageo-coordinate.js"></script>

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
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary"
       href="index.php?p=mappa<?= $filtroCatalogo !== '' ? '&amp;catalogo=' . urlencode($filtroCatalogo) : '' ?>">
      <i class="bi bi-map"></i> Vedi sulla mappa
    </a>
    <?php if (Auth::puo('modifica_scheda')): ?>
      <a class="btn btn-primary" href="index.php?p=ipogei&amp;azione=nuovo">
        <i class="bi bi-plus-lg"></i> Nuovo ipogeo
      </a>
    <?php endif; ?>
  </div>
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
