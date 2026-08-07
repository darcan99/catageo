<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/stampa.php
 *  Descrizione ..: Scheda da stampare: un documento lineare, completo e
 *                  autoconsistente, senza schede a linguette e senza mappa.
 *
 *                  Perche una pagina a parte e non un foglio di stile sulla
 *                  scheda. La scheda a schermo divide il contenuto in dieci
 *                  linguette, e una linguetta non attiva e display:none: chi
 *                  stampasse la scheda otterrebbe la sola linguetta aperta,
 *                  convinto di avere tutto. Un difetto silenzioso, ed e il
 *                  peggior genere di difetto per un documento che poi si porta
 *                  in campagna al posto dell'applicativo.
 *
 *                  Il foglio esce dall'applicativo e non ci torna: da quel
 *                  momento nessun permesso lo protegge. Percio la riservatezza
 *                  si applica qui esattamente come a schermo — coordinate
 *                  offuscate restano offuscate, sezioni riservate restano fuori
 *                  — e cio che di riservato l'utente ha il diritto di vedere
 *                  viene stampato con un timbro che lo dichiara.
 *
 *                  Nessuna mappa: disegnarla richiederebbe di scaricare i tile
 *                  da un server esterno, e una stampa non deve dipendere dalla
 *                  rete. Le coordinate ci sono in tutte le notazioni d'uso.
 *  Versione .....: 1.0.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.0.0 2026-08-06  D.Candela  Prima stesura (fase 10).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('consulta');

require_once CATAGEO_ROOT . '/app/view/parti-avvisi.php';

$codice = trim((string) ($_GET['codice'] ?? ''));

$risoluzione = $codice === '' ? null : Ipogeo::risolvi($codice);
if ($risoluzione === null) {
    Auth::messaggio('errore', 'Nessun ipogeo con codice "' . $codice . '".');
    header('Location: index.php?p=ipogei');
    exit;
}

$scheda = $risoluzione['scheda'];
if (!Visibilita::schedaVisibile(
    (string) $scheda['ubicazione']['riservatezza'],
    (string) $scheda['catasto']['statoScheda']
)) {
    Auth::messaggio('errore', 'La scheda richiesta non e consultabile con il livello di utenza in uso.');
    header('Location: index.php?p=ipogei');
    exit;
}

$codiceCorrente = (string) $risoluzione['codiceCorrente'];

/*
 * Sezioni stampabili. Il valore predefinito e "tutte": chi stampa una scheda
 * di catasto la vuole intera, e chiedere ogni volta quali parti includere
 * sarebbe un passo in piu per il caso normale. Chi vuole il solo estratto
 * toglie le spunte.
 */
$sezioniDisponibili = [
    'dati'          => 'Dati catastali, ubicazione e caratteristiche',
    'descrizione'   => 'Descrizione, storia e note',
    'esplorazioni'  => 'Esplorazioni',
    'bibliografia'  => 'Bibliografia',
    'scientifici'   => 'Dati scientifici',
    'biospeleologia' => 'Biospeleologia',
    'archeologia'   => 'Archeologia',
    'risorse'       => 'Elenco di allegati, foto, video e rilievi',
    'foto'          => 'Foto (immagini in pagina)',
];

// Distinguere "nessuna spunta" da "primo accesso" richiede un campo nascosto:
// senza, un utente che toglie tutte le spunte otterrebbe la scheda intera.
if (isset($_GET['scelte'])) {
    $richieste = $_GET['sez'] ?? [];
    $richieste = is_array($richieste) ? $richieste : [];
    $sezioni   = [];
    foreach ($sezioniDisponibili as $chiave => $etichetta) {
        $sezioni[$chiave] = in_array($chiave, $richieste, true);
    }
} else {
    $sezioni = array_map(static fn (): bool => true, $sezioniDisponibili);
}

// ----------------------------------------------------------------- raccolta dati

$coord = Visibilita::coordinate(
    (string) $scheda['ubicazione']['coordinate']['latitudine'],
    (string) $scheda['ubicazione']['coordinate']['longitudine'],
    (string) $scheda['ubicazione']['riservatezza']
);

$catalogoScheda   = Cataloghi::trova((string) $scheda['catasto']['catalogo']);
$sistemaPreferito = (string) ($catalogoScheda['sistemaPreferito'] ?? '');

$rappresentazioni = null;
if (!$coord['offuscate'] && $coord['lat'] !== '' && $coord['lon'] !== '') {
    try {
        $rappresentazioni = Coordinate::rappresentazioni(
            (float) $coord['lat'], (float) $coord['lon'], $sistemaPreferito
        );
    } catch (Throwable $e) {
        // Un punto fuori dal campo di validita di un sistema proiettato non
        // deve impedire la stampa del resto della scheda.
        $rappresentazioni = null;
    }
}

/*
 * Il foglio contiene dati riservati? Serve a decidere il timbro. Sono tre casi
 * distinti: l'ubicazione riservata della scheda, le coordinate esatte di una
 * scheda offuscata mostrate a chi puo vederle, e le sezioni riservate.
 */
$contieneRiservati = false;
$riservatezzaScheda = (string) $scheda['ubicazione']['riservatezza'];
if ($riservatezzaScheda === 'riservata') {
    $contieneRiservati = true;
}
if ($riservatezzaScheda === 'coordinate_offuscate' && !$coord['offuscate']) {
    $contieneRiservati = true;
}

$esplorazioni = $sezioni['esplorazioni'] ? Esplorazioni::elenco($codiceCorrente) : [];
$biblio       = [];
if ($sezioni['bibliografia']) {
    foreach (Bibliografia::elenco($codiceCorrente) as $voce) {
        $biblio[] = Bibliografia::risolvi($voce);
    }
}

$serie = $sezioni['scientifici'] ? Scientifici::serieVisibili($codiceCorrente) : [];
$punti = $sezioni['scientifici'] ? Scientifici::puntiMisura($codiceCorrente) : [];
foreach ($serie as $s) {
    if ((string) $s['riservatezza'] === 'riservata') {
        $contieneRiservati = true;
    }
}

$osservazioni = $sezioni['biospeleologia'] ? Biospeleologia::osservazioni($codiceCorrente) : [];
$colonie      = $sezioni['biospeleologia'] ? Biospeleologia::colonieVisibili($codiceCorrente) : [];
foreach ($colonie as $c) {
    if ((string) $c['riservatezza'] === 'riservata') {
        $contieneRiservati = true;
    }
}

$inquadramento = $sezioni['archeologia'] ? Archeologia::inquadramento($codiceCorrente) : [];
$evidenze      = $sezioni['archeologia'] ? Archeologia::evidenze($codiceCorrente) : [];
$tutela        = $sezioni['archeologia'] ? Archeologia::tutela($codiceCorrente) : [];
$indagini      = $sezioni['archeologia'] ? Archeologia::indagini($codiceCorrente) : [];

/*
 * Foto in pagina: un tetto basso e voluto. Una scheda con ottanta foto
 * genererebbe quaranta fogli di immagini, e chi stampa una scheda vuole la
 * scheda. Le altre restano nell'elenco delle risorse, con il loro numero.
 */
const CATAGEO_STAMPA_MAX_FOTO = 6;

$foto = [];
if ($sezioni['foto']) {
    $tutte = Risorse::elenco($codiceCorrente, 'FO');
    // La copertina per prima: e la foto che rappresenta la cavita.
    usort($tutte, static fn (array $a, array $b): int =>
        (int) !empty($b['copertina']) <=> (int) !empty($a['copertina']));
    foreach ($tutte as $immagine) {
        if (count($foto) >= CATAGEO_STAMPA_MAX_FOTO) {
            break;
        }
        /*
         * Una foto riservata non si stampa a chi non la puo scaricare. Non e
         * solo una questione di riservatezza: scarica.php rifiuterebbe la
         * richiesta e sul foglio resterebbe il riquadro dell'immagine rotta.
         * La riga nell'elenco delle risorse resta, come nella pagina delle
         * risorse: l'archivio dichiara cosa contiene, non lo consegna.
         */
        if (!Visibilita::livelloVisibile((string) ($immagine['riservatezza'] ?? 'pubblica'))) {
            continue;
        }
        if (Risorse::percorsoFile($codiceCorrente, 'FO', (int) $immagine['progressivo']) === null) {
            continue;   // file sparito dal disco: non si stampa un riquadro rotto
        }
        $foto[] = $immagine;
    }
    $fotoTotali = count($tutte);
} else {
    $fotoTotali = 0;
}

/** Avvisi: gli stessi della scheda a schermo, dalla stessa funzione. */
$avvisi = [];
if ((string) $scheda['catasto']['statoScheda'] === 'bozza') {
    $avvisi[] = ['grave' => false, 'testo' => 'Scheda in bozza: i dati non sono ancora verificati.'];
}
$statoAccesso = (string) $scheda['ubicazione']['accesso']['stato'];
if (in_array($statoAccesso, ['chiuso', 'interrato', 'distrutto', 'non_localizzato'], true)) {
    $avvisi[] = ['grave' => false, 'testo' => 'Stato di accesso: ' . str_replace('_', ' ', $statoAccesso) . '.'];
}
if (!empty($scheda['ubicazione']['accesso']['permessiNecessari'])) {
    $avvisi[] = ['grave' => true, 'testo' => 'Accesso subordinato ad autorizzazione.'
        . ((string) $scheda['ubicazione']['accesso']['riferimentoPermessi'] !== ''
            ? ' ' . (string) $scheda['ubicazione']['accesso']['riferimentoPermessi'] : '')];
}
if ((string) $scheda['caratteristiche']['percorribilita']['pericoli'] !== '') {
    $avvisi[] = ['grave' => true, 'testo' => 'Pericoli segnalati: '
        . (string) $scheda['caratteristiche']['percorribilita']['pericoli']];
}
if ($riservatezzaScheda === 'riservata') {
    $avvisi[] = ['grave' => false, 'testo' => 'Ubicazione riservata: non divulgare.'];
}
foreach (catageoAvvisiDi($codiceCorrente) as $avviso) {
    $avvisi[] = [
        'grave' => $avviso['livello'] === 'danger',
        'testo' => $avviso['titolo'] . ' — ' . $avviso['testo'],
    ];
}

// ------------------------------------------------------------------- funzioni

/** Valore di campo, con il trattino di "non compilato" al posto del vuoto. */
function catageoStampaValore(?string $valore): string
{
    $valore = trim((string) $valore);

    return $valore === ''
        ? '<span class="stampa-vuoto">—</span>'
        : Testo::esc($valore);
}

/**
 * Tabella di campi etichetta/valore.
 *
 * @param array<string,string> $campi
 * @param bool $tuttiIValori false per omettere le righe vuote: negli elenchi
 *                           lunghi e poco compilati mezza pagina di trattini
 *                           non aiuta nessuno
 */
function catageoStampaCampi(array $campi, bool $tuttiIValori = true): void
{
    $righe = $tuttiIValori
        ? $campi
        : array_filter($campi, static fn (string $v): bool => trim($v) !== '');

    if ($righe === []) {
        return;
    }

    echo '<table class="stampa-campi">';
    foreach ($righe as $etichetta => $valore) {
        echo '<tr><th>' . Testo::esc((string) $etichetta) . '</th>'
            . '<td>' . catageoStampaValore($valore) . '</td></tr>';
    }
    echo '</table>';
}

/**
 * Etichetta leggibile di un codice di periodo storico.
 *
 * Un codice non piu in vocabolario si stampa comunque, dichiarandolo: la
 * scheda dice cio che c'e scritto, non cio che il vocabolario di oggi ammette.
 */
function catageoStampaPeriodo(string $codice): string
{
    if (trim($codice) === '') {
        return '';
    }
    $voce = Periodi::trova($codice);

    return $voce === null ? $codice . ' (non in vocabolario)' : Periodi::etichetta($voce);
}

/**
 * Nome leggibile di un esploratore, dal suo identificativo.
 *
 * In scheda il censore e un riferimento anagrafico: stampare "E003" su un
 * foglio che finisce in un fascicolo cartaceo non dice niente a nessuno.
 */
function catageoStampaEsploratore(string $id): string
{
    if (trim($id) === '') {
        return '';
    }
    $voce = Esploratori::trova($id);

    return $voce === null ? $id . ' (non in anagrafica)' : Esploratori::etichetta($voce);
}

/** Nome del complesso, dal suo identificativo. */
function catageoStampaComplesso(string $id): string
{
    if (trim($id) === '') {
        return '';
    }
    $voce = Complessi::trova($id);

    return $voce === null ? $id . ' (non in anagrafica)' : Complessi::etichetta($voce);
}

/** Nome dell'area speleologica, dal suo identificativo. */
function catageoStampaArea(string $id): string
{
    if (trim($id) === '') {
        return '';
    }
    $voce = Aree::trova($id);

    return $voce === null ? $id . ' (non in anagrafica)' : Aree::etichetta($voce);
}

/** Nome leggibile di un gruppo speleologico, dal suo identificativo. */
function catageoStampaGruppo(string $id): string
{
    if (trim($id) === '') {
        return '';
    }
    $voce = Gruppi::trova($id);

    return $voce === null ? $id . ' (non in anagrafica)' : Gruppi::etichetta($voce);
}

/**
 * Data e ora in forma leggibile, da un istante scritto in ISO 8601.
 *
 * Sul foglio "2026-08-06T23:56:48" e un timbro di macchina. La scheda a schermo
 * puo permetterselo, un documento cartaceo no.
 */
function catageoStampaIstante(string $iso): string
{
    $iso = trim($iso);
    if ($iso === '') {
        return '';
    }

    // Nessun strtotime: si legge la forma canonica e, se non combacia, si
    // restituisce il valore com'e invece di indovinare.
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/', $iso, $p) !== 1) {
        return $iso;
    }

    $data = $p[3] . '/' . $p[2] . '/' . $p[1];

    return isset($p[4]) ? $data . ' ' . $p[4] . ':' . $p[5] : $data;
}

/**
 * Grandezza misurata in forma leggibile.
 *
 * L'etichetta del vocabolario porta gia l'unita fra parentesi. L'unita scritta
 * nella serie si aggiunge solo se e diversa: ripeterla darebbe "Temperatura
 * aria (°C) (°C)", e ometterla nasconderebbe il caso — che capita — di una
 * serie registrata in un'unita non canonica.
 */
function catageoStampaGrandezza(string $codice, string $unita): string
{
    $etichetta = Grandezze::etichetta($codice);
    $unita     = trim($unita);

    if ($unita === '' || $etichetta === '' || str_contains($etichetta, '(' . $unita . ')')) {
        return $etichetta;
    }

    return $etichetta . ' — misurata in ' . $unita;
}

/** Blocco di testo lungo, con gli a capo conservati. */
function catageoStampaTesto(string $testo): void
{
    $testo = trim($testo);
    if ($testo === '') {
        return;
    }

    foreach (preg_split('/\R{2,}/', $testo) ?: [$testo] as $paragrafo) {
        echo '<p class="stampa-testo">' . nl2br(Testo::esc(trim($paragrafo))) . '</p>';
    }
}

$nomeCatasto = Config::testo('catasto.nome', 'CATAGEO');
$ente        = Config::testo('catasto.ente', '');
$utente      = Auth::utente();
$nomeIpogeo  = (string) $scheda['identificazione']['nome'];
$momento     = date('d/m/Y H:i');

// La stampa non e una pagina dell'applicativo: nessuna navbar, nessun piede,
// nessun JavaScript. Il documento si scrive per intero qui.
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= Testo::esc($codiceCorrente . ' — ' . $nomeIpogeo) ?> · stampa</title>
<link rel="stylesheet" href="assets/css/catageo-stampa.css">
</head>
<body>

<div class="stampa-comandi">
  <a href="index.php?p=ipogei&amp;azione=scheda&amp;codice=<?= urlencode($codiceCorrente) ?>">&larr; Torna alla scheda</a>
  <span class="stampa-nota">
    Per stampare o salvare in PDF usa la stampa del browser (Ctrl+P).
    Questa pagina non contiene la mappa: una stampa non deve dipendere dalla rete.
  </span>
</div>

<form class="stampa-comandi stampa-solo-schermo" method="get" action="index.php">
  <input type="hidden" name="p" value="stampa">
  <input type="hidden" name="codice" value="<?= Testo::esc($codiceCorrente) ?>">
  <input type="hidden" name="scelte" value="1">
  <?php foreach ($sezioniDisponibili as $chiave => $etichetta): ?>
    <label>
      <input type="checkbox" name="sez[]" value="<?= Testo::esc($chiave) ?>"
             <?= $sezioni[$chiave] ? 'checked' : '' ?>>
      <?= Testo::esc($etichetta) ?>
    </label>
  <?php endforeach; ?>
  <button type="submit">Aggiorna</button>
</form>

<?php if ($contieneRiservati): ?>
  <div class="stampa-riservato">
    Contiene dati riservati — copia non divulgabile
  </div>
<?php endif; ?>

<div class="stampa-testata">
  <div class="stampa-ente">
    <?= Testo::esc($nomeCatasto) ?><?= $ente !== '' ? ' — ' . Testo::esc($ente) : '' ?>
  </div>
  <h1>
    <span class="stampa-codice"><?= Testo::esc($codiceCorrente) ?></span><?= Testo::esc($nomeIpogeo) ?>
  </h1>
  <p class="stampa-sottotitolo">
    <?= Testo::esc(Tipologie::percorsoLeggibile(
        (string) $scheda['identificazione']['sottotipologia'] !== ''
            ? (string) $scheda['identificazione']['sottotipologia']
            : (string) $scheda['identificazione']['tipologia']
    )) ?>
    · catalogo <?= Testo::esc((string) $scheda['catasto']['catalogo']) ?>
    · scheda <?= Testo::esc((string) $scheda['catasto']['statoScheda']) ?>
    · revisione <?= (int) $scheda['catasto']['revisione'] ?>
  </p>
</div>

<?php if ($avvisi !== []): ?>
  <ul class="stampa-avvisi">
    <?php foreach ($avvisi as $avviso): ?>
      <li<?= $avviso['grave'] ? ' class="stampa-grave"' : '' ?>><?= Testo::esc($avviso['testo']) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($sezioni['dati']): ?>

  <div class="stampa-sezione">
    <h2>Identificazione</h2>
    <div class="stampa-doppia">
      <div>
        <?php
        $sinonimi = $scheda['identificazione']['sinonimi'] ?? [];
        catageoStampaCampi([
            'Nome'          => $nomeIpogeo,
            'Altri nomi'    => implode('; ', array_map('strval', is_array($sinonimi) ? $sinonimi : [])),
            'Natura'        => Tipologie::nome((string) $scheda['identificazione']['natura']),
        ]);
        ?>
      </div>
      <div>
        <?php
        $esterni = [];
        foreach (($scheda['identificazione']['codiciEsterni'] ?? []) as $voce) {
            $esterni[] = trim((string) ($voce['ente'] ?? '') . ' ' . (string) ($voce['catasto'] ?? '')
                . ' ' . (string) ($voce['codice'] ?? ''));
        }
        $storici = [];
        foreach (($scheda['identificazione']['codiciStorici'] ?? []) as $voce) {
            $storici[] = (string) ($voce['codice'] ?? '')
                . ((string) ($voce['al'] ?? '') !== '' ? ' (fino al ' . (string) $voce['al'] . ')' : '');
        }
        catageoStampaCampi([
            'Codici esterni' => implode('; ', $esterni),
            'Codici storici' => implode('; ', $storici),
            'Tipologia'      => Tipologie::percorsoLeggibile((string) $scheda['identificazione']['tipologia']),
            'Complesso'      => catageoStampaComplesso((string) ($scheda['identificazione']['complesso'] ?? '')),
        ]);
        ?>
      </div>
    </div>
  </div>

  <div class="stampa-sezione">
    <h2>Ubicazione</h2>
    <div class="stampa-doppia">
      <div>
        <?php catageoStampaCampi([
            'Stato'     => (string) $scheda['ubicazione']['statoNome'] !== ''
                ? (string) $scheda['ubicazione']['statoNome'] : (string) $scheda['ubicazione']['stato'],
            'Regione'   => (string) $scheda['ubicazione']['regione'],
            'Provincia' => (string) $scheda['ubicazione']['provincia'],
            'Comune'    => (string) $scheda['ubicazione']['comune'],
            'Localita'  => (string) $scheda['ubicazione']['localita'],
            'Area speleologica' => catageoStampaArea((string) ($scheda['ubicazione']['area'] ?? '')),
            'Indirizzo' => (string) $scheda['ubicazione']['indirizzo'],
        ]); ?>
      </div>
      <div>
        <?php catageoStampaCampi([
            'Tavoletta IGM' => (string) $scheda['ubicazione']['cartografia']['tavolettaIGM'],
            'Sezione CTR'   => (string) $scheda['ubicazione']['cartografia']['sezioneCTR'],
            'Riservatezza'  => str_replace('_', ' ', $riservatezzaScheda),
        ]); ?>
      </div>
    </div>

    <h3>Coordinate</h3>
    <?php if ($coord['offuscate']): ?>
      <p class="stampa-testo">
        <strong>Coordinate approssimate.</strong> La posizione esatta di questa cavita
        e riservata: i valori qui sotto sono arrotondati e servono solo a inquadrare
        la zona. Non sono utilizzabili per raggiungere l'ingresso.
      </p>
    <?php endif; ?>
    <div class="stampa-doppia">
      <div>
        <?php
        $campiCoordinate = [
            'WGS84 gradi' => $coord['lat'] !== '' && $coord['lon'] !== ''
                ? $coord['lat'] . ', ' . $coord['lon'] : '',
        ];
        if ($rappresentazioni !== null) {
            if (isset($rappresentazioni['preferito'])) {
                $campiCoordinate[(string) $rappresentazioni['preferitoNome']] =
                    (string) $rappresentazioni['preferito'];
            }
            $campiCoordinate['Gradi, primi, secondi'] = (string) $rappresentazioni['gms'];
            $campiCoordinate['UTM WGS84']             = (string) $rappresentazioni['utm'];
        }
        catageoStampaCampi($campiCoordinate);
        ?>
      </div>
      <div>
        <?php catageoStampaCampi([
            'Quota'            => (string) $scheda['ubicazione']['coordinate']['quota'] !== ''
                ? (string) $scheda['ubicazione']['coordinate']['quota'] . ' m' : '',
            'Precisione'       => (string) $scheda['ubicazione']['coordinate']['precisione'] !== ''
                ? '± ' . (string) $scheda['ubicazione']['coordinate']['precisione'] . ' m' : '',
            'Metodo'           => (string) $scheda['ubicazione']['coordinate']['metodo'],
            'Data rilevamento' => (string) $scheda['ubicazione']['coordinate']['dataRilevamento'],
            'Dato originale'   => trim((string) $scheda['ubicazione']['coordinate']['valoreOriginale']
                . ' ' . (string) $scheda['ubicazione']['coordinate']['sistemaOriginale']),
        ]); ?>
      </div>
    </div>

    <h3>Accesso</h3>
    <?php catageoStampaCampi([
        'Stato'      => str_replace('_', ' ', $statoAccesso),
        'Proprieta'  => (string) $scheda['ubicazione']['accesso']['proprieta'],
        'Permessi'   => !empty($scheda['ubicazione']['accesso']['permessiNecessari'])
            ? 'necessari — ' . (string) $scheda['ubicazione']['accesso']['riferimentoPermessi']
            : 'non necessari',
    ]); ?>
    <?php catageoStampaTesto((string) $scheda['ubicazione']['accesso']['descrizione']); ?>
  </div>

  <div class="stampa-sezione">
    <h2>Caratteristiche</h2>
    <div class="stampa-doppia">
      <div>
        <?php catageoStampaCampi([
            'Sviluppo planimetrico' => (string) $scheda['caratteristiche']['sviluppoPlanimetrico'],
            'Sviluppo spaziale'     => (string) $scheda['caratteristiche']['sviluppoSpaziale'],
            'Dislivello positivo'   => (string) $scheda['caratteristiche']['dislivelloPositivo'],
            'Dislivello negativo'   => (string) $scheda['caratteristiche']['dislivelloNegativo'],
            'Profondita massima'    => (string) $scheda['caratteristiche']['profonditaMassima'],
            'Numero di ingressi'    => (string) $scheda['caratteristiche']['numeroIngressi'],
        ]); ?>
      </div>
      <div>
        <?php
        $interesse = $scheda['caratteristiche']['interesse'] ?? [];
        catageoStampaCampi([
            'Presenza acqua'   => (string) $scheda['caratteristiche']['idrologia']['presenzaAcqua'],
            'Interesse'        => implode(', ', array_map('strval', is_array($interesse) ? $interesse : [])),
            // Prima i gradi, che sono confrontabili fra cavita, poi il testo
            // libero, che dice il resto.
            'Grado di progressione' => Ipogeo::GRADI_PROGRESSIONE[(string) ($scheda['caratteristiche']['percorribilita']['gradoProgressione'] ?? '')] ?? '',
            'Difficolta idriche' => Ipogeo::GRADI_IDRICI[(string) ($scheda['caratteristiche']['percorribilita']['gradoIdrico'] ?? '')] ?? '',
            'Periodo consigliato' => Ipogeo::PERIODI_CONSIGLIATI[(string) ($scheda['caratteristiche']['percorribilita']['periodoConsigliato'] ?? '')] ?? '',
            'Necessita armo'  => (string) ($scheda['caratteristiche']['percorribilita']['necessitaArmo'] ?? ''),
            'Inquinata'       => (string) ($scheda['caratteristiche']['percorribilita']['inquinata'] ?? ''),
            'Difficolta'       => (string) $scheda['caratteristiche']['percorribilita']['difficolta'],
            'Attrezzatura'     => (string) $scheda['caratteristiche']['percorribilita']['attrezzaturaNecessaria'],
            'Tempo percorrenza' => (string) $scheda['caratteristiche']['percorribilita']['tempoPercorrenza'],
        ]);
        ?>
      </div>
    </div>

    <?php if ((string) $scheda['caratteristiche']['percorribilita']['pericoli'] !== ''): ?>
      <h3>Pericoli</h3>
      <?php catageoStampaTesto((string) $scheda['caratteristiche']['percorribilita']['pericoli']); ?>
    <?php endif; ?>

    <?php $ingressi = $scheda['caratteristiche']['ingressi'] ?? []; ?>
    <?php if (is_array($ingressi) && $ingressi !== []): ?>
      <h3>Ingressi e accessi</h3>
      <table class="stampa-elenco">
        <thead><tr><th>Nome</th><th>Tipo</th><th>Stato</th><th>Progr.</th><th>Quota</th><th>Dimensioni</th><th>Descrizione</th></tr></thead>
        <tbody>
          <?php foreach ($ingressi as $ingresso): ?>
            <tr>
              <td><?= catageoStampaValore((string) ($ingresso['nome'] ?? '')) ?></td>
              <td><?= catageoStampaValore(Ipogeo::TIPI_INGRESSO[(string) ($ingresso['tipo'] ?? '')] ?? '') ?></td>
              <td><?= catageoStampaValore(Ipogeo::STATI_INGRESSO[(string) ($ingresso['stato'] ?? '')] ?? '') ?></td>
              <td><?= catageoStampaValore((string) ($ingresso['progressiva'] ?? '')) ?></td>
              <td><?= catageoStampaValore((string) ($ingresso['quota'] ?? '')) ?></td>
              <td><?= catageoStampaValore((string) ($ingresso['dimensioni'] ?? '')) ?></td>
              <td><?= catageoStampaValore((string) ($ingresso['descrizione'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($coord['offuscate']): ?>
        <p class="stampa-testo stampa-vuoto">
          Le coordinate dei singoli ingressi sono omesse: la scheda ha ubicazione
          a precisione ridotta.
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="stampa-sezione">
    <h2>Dati catastali</h2>
    <div class="stampa-doppia">
      <div>
        <?php catageoStampaCampi([
            'Catalogo'        => (string) $scheda['catasto']['nomeCatalogo'] !== ''
                ? (string) $scheda['catasto']['nomeCatalogo'] . ' (' . (string) $scheda['catasto']['catalogo'] . ')'
                : (string) $scheda['catasto']['catalogo'],
            'Serie'           => (string) $scheda['catasto']['serieCodice'],
            'Data censimento' => (string) $scheda['catasto']['dataCensimento'],
            'Censito da'      => catageoStampaEsploratore((string) $scheda['catasto']['censitoDa']),
        ]); ?>
      </div>
      <div>
        <?php catageoStampaCampi([
            'Gruppo censore' => catageoStampaGruppo((string) $scheda['catasto']['gruppoCensore']),
            'Stato scheda'   => (string) $scheda['catasto']['statoScheda'],
            'Ultima modifica' => trim(catageoStampaIstante((string) $scheda['catasto']['modificaData'])
                . ' ' . (string) $scheda['catasto']['modificaUtente']),
            'Revisione'      => (string) $scheda['catasto']['revisione'],
        ]); ?>
      </div>
    </div>

    <?php $collegamenti = $scheda['collegamenti'] ?? []; ?>
    <?php if (is_array($collegamenti) && $collegamenti !== []): ?>
      <h3>Collegamenti ad altri ipogei</h3>
      <table class="stampa-elenco">
        <thead><tr><th>Codice</th><th>Relazione</th></tr></thead>
        <tbody>
          <?php foreach ($collegamenti as $legame): ?>
            <tr>
              <td class="stampa-valore-mono"><?= catageoStampaValore((string) ($legame['codice'] ?? '')) ?></td>
              <td><?= catageoStampaValore((string) ($legame['relazione'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php if ($sezioni['descrizione']): ?>
  <?php
  $testi = [
      'Sintesi'    => (string) $scheda['descrizione']['sintesi'],
      'Descrizione' => (string) $scheda['descrizione']['testo'],
      'Storia'     => (string) $scheda['descrizione']['storia'],
      'Note'       => (string) $scheda['descrizione']['note'],
  ];
  $testi = array_filter($testi, static fn (string $v): bool => trim($v) !== '');
  ?>
  <?php if ($testi !== []): ?>
    <div class="stampa-sezione">
      <h2>Descrizione</h2>
      <?php foreach ($testi as $etichetta => $testo): ?>
        <h3><?= Testo::esc((string) $etichetta) ?></h3>
        <?php catageoStampaTesto($testo); ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($sezioni['esplorazioni'] && $esplorazioni !== []): ?>
  <div class="stampa-sezione">
    <h2>Esplorazioni (<?= count($esplorazioni) ?>)</h2>
    <table class="stampa-elenco">
      <thead>
        <tr><th>Data</th><th>Titolo</th><th>Tipo</th><th>Partecipanti</th><th>Voci</th></tr>
      </thead>
      <tbody>
        <?php foreach ($esplorazioni as $diario): ?>
          <tr>
            <td><?= catageoStampaValore((string) $diario['dataInizio']) ?></td>
            <td><?= catageoStampaValore((string) $diario['titolo']) ?></td>
            <td><?= catageoStampaValore((string) $diario['tipo']) ?></td>
            <td><?= (int) $diario['partecipanti'] ?></td>
            <td><?= (int) $diario['voci'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="stampa-testo stampa-vuoto">
      I diari completi non sono riportati: si stampano dalla pagina del singolo diario.
    </p>
  </div>
<?php endif; ?>

<?php if ($sezioni['bibliografia'] && $biblio !== []): ?>
  <div class="stampa-sezione">
    <h2>Bibliografia (<?= count($biblio) ?>)</h2>
    <table class="stampa-elenco">
      <thead><tr><th style="width:16mm">Sigla</th><th>Citazione</th></tr></thead>
      <tbody>
        <?php foreach ($biblio as $voce): ?>
          <tr>
            <td class="stampa-valore-mono">BB<?= str_pad((string) (int) $voce['progressivo'], 3, '0', STR_PAD_LEFT) ?></td>
            <td><?= Testo::esc(Bibliografia::citazione($voce)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if ($sezioni['scientifici'] && ($serie !== [] || $punti !== [])): ?>
  <div class="stampa-sezione">
    <h2>Dati scientifici</h2>

    <?php if ($punti !== []): ?>
      <h3>Punti di misura</h3>
      <table class="stampa-elenco">
        <thead><tr><th>Codice</th><th>Nome</th><th>Progressiva</th><th>Descrizione</th></tr></thead>
        <tbody>
          <?php foreach ($punti as $punto): ?>
            <tr>
              <td class="stampa-valore-mono"><?= catageoStampaValore((string) $punto['id']) ?></td>
              <td><?= catageoStampaValore((string) $punto['nome']) ?></td>
              <td><?= catageoStampaValore((string) $punto['progressiva']) ?></td>
              <td><?= catageoStampaValore((string) $punto['descrizione']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($serie !== []): ?>
      <h3>Serie di misure</h3>
      <table class="stampa-elenco">
        <thead>
          <tr><th>Sigla</th><th>Titolo</th><th>Grandezza</th><th>Periodo</th><th>Letture</th></tr>
        </thead>
        <tbody>
          <?php foreach ($serie as $s): ?>
            <tr>
              <td class="stampa-valore-mono">SC<?= str_pad((string) (int) $s['progressivo'], 3, '0', STR_PAD_LEFT) ?></td>
              <td><?= catageoStampaValore((string) $s['titolo']) ?></td>
              <td>
                <?= catageoStampaValore(catageoStampaGrandezza(
                    (string) $s['grandezza'], (string) $s['unita'])) ?>
              </td>
              <td>
                <?= catageoStampaValore(trim((string) $s['periodoDal'] . ' — ' . (string) $s['periodoAl'], ' —')) ?>
              </td>
              <td><?= catageoStampaValore((string) $s['numeroLetture']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="stampa-testo stampa-vuoto">
        Le letture non si stampano: una serie da datalogger vale decine di migliaia
        di righe. Si esportano in CSV dalla pagina dei dati scientifici.
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($sezioni['biospeleologia'] && ($osservazioni !== [] || $colonie !== [])): ?>
  <div class="stampa-sezione">
    <h2>Biospeleologia</h2>

    <?php if ($colonie !== []): ?>
      <h3>Colonie di chirotteri</h3>
      <table class="stampa-elenco">
        <thead>
          <tr><th>Nome</th><th>Specie</th><th>Ruolo</th><th>Consistenza</th><th>Tendenza</th></tr>
        </thead>
        <tbody>
          <?php foreach ($colonie as $colonia): ?>
            <tr>
              <td><?= catageoStampaValore((string) $colonia['nome']) ?></td>
              <td><em><?= catageoStampaValore((string) $colonia['specie']) ?></em></td>
              <td><?= catageoStampaValore((string) $colonia['ruolo']) ?></td>
              <td><?= catageoStampaValore((string) $colonia['consistenzaStimata']) ?></td>
              <td><?= catageoStampaValore((string) $colonia['trend']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($osservazioni !== []): ?>
      <h3>Osservazioni faunistiche (<?= count($osservazioni) ?>)</h3>
      <table class="stampa-elenco">
        <thead>
          <tr><th>Data</th><th>Taxon</th><th>Zona</th><th>Individui</th><th>Protetta</th></tr>
        </thead>
        <tbody>
          <?php foreach ($osservazioni as $voce): ?>
            <tr>
              <td><?= catageoStampaValore((string) $voce['data']) ?></td>
              <td>
                <em><?= catageoStampaValore((string) $voce['nomeScientifico']) ?></em>
                <?= (string) $voce['nomeComune'] !== '' ? ' (' . Testo::esc((string) $voce['nomeComune']) . ')' : '' ?>
              </td>
              <td><?= catageoStampaValore((string) $voce['zonaCavita']) ?></td>
              <td><?= catageoStampaValore((string) $voce['numeroIndividui']) ?></td>
              <td><?= (string) $voce['specieProtetta'] === '1' ? 'si' : 'no' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
$haArcheologia = $sezioni['archeologia'] && (
    trim((string) ($inquadramento['sintesi'] ?? '')) !== ''
    || trim((string) ($inquadramento['periodoPrincipale'] ?? '')) !== ''
    || $evidenze !== [] || $indagini !== []
    || (string) ($tutela['vincolo'] ?? '0') === '1'
);
?>
<?php if ($haArcheologia): ?>
  <div class="stampa-sezione">
    <h2>Archeologia</h2>

    <?php catageoStampaCampi([
        'Periodo principale'  => catageoStampaPeriodo((string) ($inquadramento['periodoPrincipale'] ?? '')),
        'Periodi secondari'   => (string) ($inquadramento['periodiSecondari'] ?? ''),
        // Gli anni si scrivono negativi per l'avanti Cristo: sul foglio devono
        // leggersi come si leggono, altrimenti la riga sotto l'etichetta del
        // periodo dice la stessa cosa in due lingue diverse.
        'Datazione'           => Periodi::estremiLeggibili([
            'da' => (string) ($inquadramento['datazioneDa'] ?? ''),
            'a'  => (string) ($inquadramento['datazioneA'] ?? ''),
        ]),
        'Funzione originaria' => (string) ($inquadramento['funzioneOriginaria'] ?? ''),
        'Contesto'            => (string) ($inquadramento['contestoTopografico'] ?? ''),
    ], false); ?>

    <?php catageoStampaTesto((string) ($inquadramento['sintesi'] ?? '')); ?>

    <?php if ((string) ($tutela['vincolo'] ?? '0') === '1'): ?>
      <h3>Tutela</h3>
      <?php catageoStampaCampi([
          'Vincolo'        => (string) ($tutela['tipoVincolo'] ?? '') !== ''
              ? (string) $tutela['tipoVincolo'] : 'presente',
          'Ente competente' => (string) ($tutela['enteCompetente'] ?? ''),
          'Provvedimento'  => trim((string) ($tutela['riferimentoProvvedimento'] ?? '')
              . ' ' . (string) ($tutela['dataProvvedimento'] ?? '')),
      ], false); ?>
      <?php catageoStampaTesto((string) ($tutela['prescrizioni'] ?? '')); ?>
    <?php endif; ?>

    <?php if ($evidenze !== []): ?>
      <h3>Evidenze (<?= count($evidenze) ?>)</h3>
      <table class="stampa-elenco">
        <thead><tr><th>Tipo</th><th>Descrizione</th><th>Zona</th><th>Conservazione</th></tr></thead>
        <tbody>
          <?php foreach ($evidenze as $evidenza): ?>
            <tr>
              <td><?= catageoStampaValore((string) $evidenza['tipo']) ?></td>
              <td><?= catageoStampaValore((string) $evidenza['descrizione']) ?></td>
              <td><?= catageoStampaValore((string) $evidenza['zonaCavita']) ?></td>
              <td><?= catageoStampaValore((string) $evidenza['statoConservazione']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($indagini !== []): ?>
      <h3>Indagini (<?= count($indagini) ?>)</h3>
      <table class="stampa-elenco">
        <thead><tr><th>Data</th><th>Tipo</th><th>Soggetto</th><th>Esito</th></tr></thead>
        <tbody>
          <?php foreach ($indagini as $indagine): ?>
            <tr>
              <td><?= catageoStampaValore((string) $indagine['data']) ?></td>
              <td><?= catageoStampaValore((string) $indagine['tipo']) ?></td>
              <td><?= catageoStampaValore((string) $indagine['soggetto']) ?></td>
              <td><?= catageoStampaValore((string) $indagine['esito']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($sezioni['risorse']): ?>
  <?php
  $sezioniMedia = ['AL' => 'Allegati', 'FO' => 'Foto', 'VI' => 'Video', 'RI' => 'Rilievi'];
  $conteggi = [];
  foreach ($sezioniMedia as $sigla => $etichetta) {
      $conteggi[$sigla] = Risorse::elenco($codiceCorrente, $sigla);
  }
  $totaleRisorse = array_sum(array_map('count', $conteggi));
  ?>
  <?php if ($totaleRisorse > 0): ?>
    <div class="stampa-sezione">
      <h2>Risorse allegate (<?= $totaleRisorse ?>)</h2>
      <table class="stampa-elenco">
        <thead><tr><th style="width:16mm">Sigla</th><th>Titolo</th><th>File</th><th>Data</th></tr></thead>
        <tbody>
          <?php foreach ($sezioniMedia as $sigla => $etichetta): ?>
            <?php foreach ($conteggi[$sigla] as $risorsa): ?>
              <tr>
                <td class="stampa-valore-mono">
                  <?= Testo::esc(Sezioni::riferimento($sigla, (int) $risorsa['progressivo'])) ?>
                </td>
                <td><?= catageoStampaValore((string) ($risorsa['titolo'] ?? '')) ?></td>
                <td><?= catageoStampaValore((string) ($risorsa['nomeOriginale'] ?? $risorsa['file'] ?? '')) ?></td>
                <td><?= catageoStampaValore((string) ($risorsa['data'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($foto !== []): ?>
  <div class="stampa-sezione stampa-nuova-pagina">
    <h2>Foto</h2>
    <div class="stampa-immagini">
      <?php foreach ($foto as $immagine): ?>
        <figure class="stampa-immagine">
          <img src="scarica.php?codice=<?= urlencode($codiceCorrente) ?>&amp;sez=FO&amp;prog=<?= (int) $immagine['progressivo'] ?>&amp;inline=1"
               alt="<?= Testo::esc((string) ($immagine['titolo'] ?? 'Foto')) ?>">
          <figcaption>
            <?= Testo::esc(Sezioni::riferimento('FO', (int) $immagine['progressivo'])) ?>
            <?= (string) ($immagine['titolo'] ?? '') !== '' ? ' — ' . Testo::esc((string) $immagine['titolo']) : '' ?>
            <?= (string) ($immagine['autore'] ?? '') !== '' ? ' (' . Testo::esc((string) $immagine['autore']) . ')' : '' ?>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
    <?php if ($fotoTotali > count($foto)): ?>
      <p class="stampa-testo stampa-vuoto">
        Stampate <?= count($foto) ?> foto delle <?= $fotoTotali ?> presenti in scheda.
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="stampa-piede">
  <span>
    <?= Testo::esc($codiceCorrente) ?> — <?= Testo::esc($nomeIpogeo) ?>
    · <?= Testo::esc($nomeCatasto) ?>
  </span>
  <span>
    Stampato il <?= Testo::esc($momento) ?>
    <?= $utente !== null ? ' da ' . Testo::esc((string) $utente['username']) : '' ?>
    · CATAGEO <?= Testo::esc(CATAGEO_VERSIONE) ?>
  </span>
</div>

</body>
</html>
