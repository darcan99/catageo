<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: esempi/genera-esempi.php
 *  Descrizione ..: Popola l'archivio con un catalogo di esempio, per vedere
 *                  l'applicativo pieno invece che vuoto.
 *
 *                  Perche un generatore e non una cartella di file pronti.
 *                  Un archivio di esempio versionato invecchia: al primo
 *                  cambio di schema diventa un archivio non valido distribuito
 *                  insieme all'applicativo che lo rifiuta. Questo script scrive
 *                  passando dalle stesse classi che usa l'interfaccia, quindi
 *                  o produce dati validi per la versione corrente o fallisce
 *                  subito, dicendolo.
 *
 *                  Gira solo da riga di comando. Un generatore di dati
 *                  raggiungibile via HTTP e un modo per riempire l'archivio di
 *                  qualcun altro.
 *
 *                  Scrive in un catalogo suo, ESEMPI, e si rifiuta di
 *                  procedere se esiste gia: i dati veri non si toccano nemmeno
 *                  per sbaglio.
 *  Versione .....: 1.0.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.0.0 2026-08-07  D.Candela  Prima stesura (fase 10).
 * ============================================================================
 *
 * Uso:
 *   php esempi/genera-esempi.php            crea il catalogo ESEMPI
 *   php esempi/genera-esempi.php --rimuovi  lo elimina, con i suoi ipogei
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Questo script si esegue da riga di comando.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

const SIGLA_ESEMPI = 'ESEMPI';

$rimuovi = in_array('--rimuovi', $argv, true);

function dice(string $testo): void { echo $testo . "\n"; }

// ============================================================================
//  RIMOZIONE
// ============================================================================

if ($rimuovi) {
    $catalogo = Cataloghi::trova(SIGLA_ESEMPI);
    if ($catalogo === null) {
        dice('Nessun catalogo ' . SIGLA_ESEMPI . ' da rimuovere.');
        exit(0);
    }

    $deiEsempi = static fn (array $riga): bool =>
        (string) ($riga['catalogo'] ?? '') === SIGLA_ESEMPI;

    foreach (IndiceIpogei::elenco($deiEsempi) as $riga) {
        Ipogeo::elimina((string) $riga['codice']);
        dice('  eliminato ' . (string) $riga['codice']);
    }

    // Le schede finiscono in _eliminati e non spariscono dal disco: e la regola
    // dell'archivio e vale anche per gli esempi. La cartella del catalogo resta
    // li, vuota di ipogei, e si toglie a mano quando si e sicuri.
    dice('');
    dice('Ipogei di esempio rimossi. Le schede sono in _eliminati, non cancellate.');
    dice('La cartella del catalogo ' . SIGLA_ESEMPI . ' resta: toglila a mano se non serve.');
    IndiceIpogei::ricostruisci();
    exit(0);
}

// ============================================================================
//  CREAZIONE
// ============================================================================

if (Cataloghi::trova(SIGLA_ESEMPI) !== null) {
    dice('Il catalogo ' . SIGLA_ESEMPI . ' esiste gia: non tocco nulla.');
    dice('Per rifare gli esempi da zero: php esempi/genera-esempi.php --rimuovi');
    exit(1);
}

dice('== Catalogo ==');
Cataloghi::crea([
    'sigla'       => SIGLA_ESEMPI,
    'nome'        => 'Catalogo di esempio',
    'descrizione' => 'Dati fittizi per prendere confidenza con CATAGEO. '
        . 'Le cavita non esistono e le coordinate cadono in aperta campagna.',
    'ente'        => 'Gruppo Speleologico di Esempio',
    'stato'       => 'IT',
    'regione'     => 'Lazio',
    // La serie iniziale la crea Cataloghi::crea: prefisso e cifre bastano.
    'prefisso'    => 'ES',
    'cifre'       => 3,
]);
dice('  ' . SIGLA_ESEMPI . ' creato');

dice('== Anagrafiche ==');
$gruppo = Gruppi::crea([
    'sigla' => 'GSE', 'nome' => 'Gruppo Speleologico di Esempio',
    'sedeComune' => 'Roma', 'sedeProvincia' => 'RM',
    'annoFondazione' => '1974', 'attivo' => true,
]);
$esploratori = [];
foreach ([['Rossi', 'Marco'], ['Bianchi', 'Lucia'], ['Neri', 'Paolo']] as [$cognome, $nome]) {
    $esploratori[] = Esploratori::crea([
        'cognome' => $cognome, 'nome' => $nome, 'attivo' => true,
        'gruppi' => [['gruppoId' => $gruppo, 'dal' => '1998-01-01', 'al' => '']],
    ]);
}
$opera = Opere::crea([
    'tipoOpera' => 'articolo', 'autori' => 'Rossi M., Bianchi L.',
    'titolo' => 'Note sul carsismo di esempio', 'contenitore' => 'Bollettino di Esempio',
    'anno' => '2004', 'volume' => '7',
]);
dice('  un gruppo, tre esploratori, un\'opera');

dice('== Ipogei ==');

/** Crea un ipogeo e ne restituisce il codice, annunciandolo. */
$censisci = static function (array $dati, string $nota): string {
    $codice = Ipogeo::crea(SIGLA_ESEMPI, $dati);
    dice('  ' . $codice . '  ' . $nota);
    return $codice;
};

// --- 1. Cavita naturale completa, il caso da guardare per primo.
$grotta = $censisci([
    'identificazione' => [
        'nome' => 'Grotta del Fontanile', 'sinonimi' => ['Buco dell\'Acqua'],
        'natura' => 'NAT', 'tipologia' => 'NAT-CAR', 'sottotipologia' => 'NAT-CAR-GRO',
    ],
    'ubicazione' => [
        'stato' => 'IT', 'statoNome' => 'Italia', 'regione' => 'Lazio',
        'provincia' => 'RM', 'comune' => 'Subiaco', 'localita' => 'Valle di Esempio',
        'riservatezza' => 'pubblica',
        'coordinate' => [
            'latitudine' => '41.925500', 'longitudine' => '13.101200',
            'quota' => '640', 'precisione' => '5', 'metodo' => 'GPS',
            'dataRilevamento' => '2019-06-08',
        ],
        'cartografia' => ['tavolettaIGM' => '151 II NO'],
        'accesso' => [
            'stato' => 'aperto',
            'descrizione' => "Dal tornante quota 610 si segue la traccia verso valle.\n"
                . "L'ingresso si apre alla base della paretina, dietro un ginepro.",
            'proprieta' => 'Demanio regionale',
        ],
    ],
    'caratteristiche' => [
        'sviluppoPlanimetrico' => '812', 'sviluppoSpaziale' => '905',
        'dislivelloNegativo' => '84', 'profonditaMassima' => '84', 'numeroIngressi' => '1',
        'idrologia' => ['presenzaAcqua' => 'stagionale', 'note' => 'Attiva dopo le piogge autunnali.'],
        'interesse' => ['concrezionamento', 'idrologico'],
        'percorribilita' => [
            'difficolta' => 'EEA', 'attrezzaturaNecessaria' => 'Corda 30 m, casco.',
            'pericoli' => 'Il sifone terminale si chiude con la piena.',
            'tempoPercorrenza' => '3 ore',
        ],
    ],
    'descrizione' => [
        'sintesi' => 'Cavita carsica su due livelli con tratto attivo stagionale.',
        'testo' => "Il primo tratto e una galleria di interstrato larga circa due metri, "
            . "con volta a mensole e pavimento di ciottoli.\n\n"
            . "Dopo il bivio si scende il pozzo da dodici metri, che immette nella sala terminale.",
        'storia' => 'Segnalata nel 1963, esplorata sistematicamente dal 1978.',
    ],
    'catasto' => [
        'dataCensimento' => '2019-06-20', 'censitoDa' => $esploratori[0],
        'gruppoCensore' => $gruppo, 'statoScheda' => 'pubblicata',
    ],
], 'cavita naturale, scheda completa');

// --- 2. Cavita artificiale: l'altra meta del catasto.
$cunicolo = $censisci([
    'identificazione' => [
        'nome' => 'Cunicolo di drenaggio del Colle',
        'natura' => 'ART', 'tipologia' => 'ART-IDR', 'sottotipologia' => 'ART-IDR-CUN',
    ],
    'ubicazione' => [
        'stato' => 'IT', 'regione' => 'Lazio', 'provincia' => 'RM', 'comune' => 'Tivoli',
        'riservatezza' => 'pubblica',
        'coordinate' => ['latitudine' => '41.963200', 'longitudine' => '12.799400',
            'quota' => '220', 'precisione' => '10', 'metodo' => 'CTR'],
        'accesso' => [
            'stato' => 'chiuso', 'descrizione' => 'Chiuso da griglia metallica.',
            'proprieta' => 'Comune', 'permessiNecessari' => true,
            'riferimentoPermessi' => 'Richiesta all\'ufficio tecnico comunale.',
        ],
    ],
    'caratteristiche' => [
        'sviluppoPlanimetrico' => '145', 'dislivelloNegativo' => '4',
        'numeroIngressi' => '2',
        'idrologia' => ['presenzaAcqua' => 'permanente'],
        'percorribilita' => ['difficolta' => 'T', 'attrezzaturaNecessaria' => 'Stivali, casco.'],
    ],
    'descrizione' => [
        'sintesi' => 'Cunicolo di drenaggio a sezione trapezoidale, di eta romana.',
        'testo' => 'Sezione trapezoidale con tracce di lavorazione a subbia sulle pareti. '
            . 'Pozzi di areazione a intervalli regolari.',
    ],
    'catasto' => [
        'dataCensimento' => '2021-03-14', 'censitoDa' => $esploratori[1],
        'gruppoCensore' => $gruppo, 'statoScheda' => 'pubblicata',
    ],
], 'cavita artificiale, con archeologia');

// --- 3. Coordinate ridotte: il caso che spiega la riservatezza.
$censisci([
    'identificazione' => [
        'nome' => 'Abisso dei Tre Camini', 'natura' => 'NAT',
        'tipologia' => 'NAT-CAR', 'sottotipologia' => 'NAT-CAR-ABI',
    ],
    'ubicazione' => [
        'stato' => 'IT', 'regione' => 'Lazio', 'provincia' => 'FR', 'comune' => 'Sora',
        'riservatezza' => 'coordinate_offuscate',
        'coordinate' => ['latitudine' => '41.716400', 'longitudine' => '13.614900',
            'quota' => '1180', 'precisione' => '5', 'metodo' => 'GPS'],
        'accesso' => ['stato' => 'aperto'],
    ],
    'caratteristiche' => [
        'dislivelloNegativo' => '312', 'profonditaMassima' => '312',
        'percorribilita' => ['difficolta' => 'EEA',
            'attrezzaturaNecessaria' => 'Armo completo, tre corde da 60 m.',
            'pericoli' => 'Pozzo iniziale soggetto a caduta sassi.'],
    ],
    'descrizione' => ['sintesi' => 'Sistema di pozzi in successione su frattura.'],
    'catasto' => ['dataCensimento' => '2016-09-02', 'censitoDa' => $esploratori[2],
        'gruppoCensore' => $gruppo, 'statoScheda' => 'pubblicata'],
], 'ubicazione a precisione ridotta');

// --- 4. Riservata: invisibile a chi consulta e basta.
$censisci([
    'identificazione' => [
        'nome' => 'Grotta del Rifugio', 'natura' => 'NAT', 'tipologia' => 'NAT-CAR',
    ],
    'ubicazione' => [
        'stato' => 'IT', 'regione' => 'Lazio', 'provincia' => 'RI', 'comune' => 'Leonessa',
        'riservatezza' => 'riservata',
        'coordinate' => ['latitudine' => '42.564300', 'longitudine' => '12.964100',
            'quota' => '980', 'precisione' => '5', 'metodo' => 'GPS'],
        'accesso' => ['stato' => 'aperto'],
    ],
    'descrizione' => ['sintesi' => 'Sito di svernamento di chirotteri: ubicazione non divulgabile.'],
    'catasto' => ['dataCensimento' => '2022-11-30', 'censitoDa' => $esploratori[0],
        'gruppoCensore' => $gruppo, 'statoScheda' => 'pubblicata'],
], 'ubicazione riservata');

// --- 5. Bozza: nell'elenco di chi puo vederla, e con l'avviso in scheda.
$censisci([
    'identificazione' => [
        'nome' => 'Inghiottitoio senza nome', 'natura' => 'NAT', 'tipologia' => 'NAT-CAR',
    ],
    'ubicazione' => [
        'stato' => 'IT', 'regione' => 'Lazio', 'provincia' => 'RM', 'comune' => 'Camerata Nuova',
        'riservatezza' => 'pubblica',
        'coordinate' => ['latitudine' => '42.021700', 'longitudine' => '13.113500',
            'precisione' => '50', 'metodo' => 'stima'],
        'accesso' => ['stato' => 'non_localizzato',
            'descrizione' => 'Segnalato da un pastore, non ancora ritrovato.'],
    ],
    'descrizione' => ['note' => 'Da verificare in campagna.'],
    'catasto' => ['dataCensimento' => '2026-04-11', 'censitoDa' => $esploratori[1],
        'gruppoCensore' => $gruppo, 'statoScheda' => 'bozza'],
], 'scheda in bozza, non localizzata');

dice('== Sezioni ==');

Esplorazioni::crea($grotta, [
    'titolo' => 'Ricognizione del ramo nord', 'tipo' => 'esplorazione',
    'dataInizio' => '2019-06-08', 'dataFine' => '2019-06-08',
    'obiettivi' => 'Verificare la prosecuzione oltre la strettoia.',
    'risultati' => 'Superata la strettoia, guadagnati una quarantina di metri.',
]);
Esplorazioni::crea($grotta, [
    'titolo' => 'Rilievo topografico', 'tipo' => 'rilievo',
    'dataInizio' => '2019-10-19', 'dataFine' => '2019-10-20',
    'risultati' => 'Rilevati 812 m di sviluppo planimetrico.',
]);
dice('  due diari di esplorazione');

Bibliografia::aggiungi($grotta, ['tipo' => 'riferimento', 'operaId' => $opera,
    'pagine' => '45-52', 'rilevanza' => 'primaria']);
Bibliografia::aggiungi($grotta, ['tipo' => 'link', 'titolo' => 'Scheda regionale',
    'ente' => 'Regione di esempio', 'url' => 'https://example.invalid/scheda/1',
    'dataConsultazione' => '2026-01-15']);
dice('  due voci bibliografiche');

Scientifici::salvaPunto($grotta, '', [
    'nome' => 'Sala terminale', 'descrizione' => 'Su blocco al centro della sala',
    'progressiva' => '410',
]);
Scientifici::creaSerie($grotta, [
    'titolo' => 'Temperatura della sala terminale', 'grandezza' => 'T-ARIA',
    'unita' => '°C', 'tipoAcquisizione' => 'datalogger', 'passoTemporale' => '1h',
    'responsabile' => $esploratori[2], 'gruppo' => $gruppo, 'riservatezza' => 'pubblica',
]);
dice('  un punto di misura e una serie');

Biospeleologia::salvaOsservazione($grotta, '', [
    'data' => '2019-06-08', 'nomeScientifico' => 'Dolichopoda geniculata',
    'nomeComune' => 'grillo cavernicolo', 'gruppoTassonomico' => 'invertebrati',
    'zonaCavita' => 'ingresso', 'numeroIndividui' => '30',
    'rilevatore' => $esploratori[0],
]);
Biospeleologia::salvaColonia($grotta, '', [
    'nome' => 'Colonia della sala alta', 'specie' => 'Rhinolophus ferrumequinum',
    'ruolo' => 'svernamento', 'consistenzaStimata' => '120', 'trend' => 'stabile',
    'riservatezza' => 'pubblica',
    'periodoCriticoDal' => '11-01', 'periodoCriticoAl' => '03-31',
    'prescrizioni' => 'Nessuna visita fra novembre e marzo.',
]);
dice('  un\'osservazione e una colonia, con periodo critico');

Archeologia::salvaInquadramento($cunicolo, [
    'periodoPrincipale' => 'ROM-IMP', 'datazioneDa' => '-50', 'datazioneA' => '150',
    'datazionePrecisione' => 'secolo', 'datazioneCriterio' => 'tecnica costruttiva',
    'funzioneOriginaria' => 'drenaggio agricolo',
    'contestoTopografico' => 'versante terrazzato sopra il fondovalle',
    'sintesi' => 'Cunicolo di drenaggio riferibile alla sistemazione agraria di eta imperiale.',
]);
Archeologia::aggiungiEvidenza($cunicolo, [
    'tipo' => 'traccia di strumenti',
    'descrizione' => 'Tracce di subbia sulle pareti, orientate verso valle: '
        . 'indicano il senso di scavo.',
    'zonaCavita' => 'galleria principale', 'statoConservazione' => 'buono',
]);
Archeologia::salvaTutela($cunicolo, [
    'vincolo' => '1', 'tipoVincolo' => 'archeologico indiretto',
    'enteCompetente' => 'Soprintendenza di esempio',
    'prescrizioni' => 'Vietata ogni asportazione di materiale',
]);
dice('  inquadramento, un\'evidenza e un vincolo');

IndiceIpogei::ricostruisci();

dice('');
dice('Fatto. Cinque ipogei nel catalogo ' . SIGLA_ESEMPI . '.');
dice('Per toglierli: php esempi/genera-esempi.php --rimuovi');
