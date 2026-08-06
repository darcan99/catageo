<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/ImportIpogei.php
 *  Descrizione ..: Importazione massiva di ipogei da CSV (9.14).
 *
 *                  E l'operazione piu rischiosa dell'applicativo: scrive molte
 *                  schede in una volta, e uno sbaglio non si annulla con un
 *                  tasto. Per questo il percorso e a due passi obbligati —
 *                  ANTEPRIMA, poi conferma — e l'anteprima esegue esattamente
 *                  gli stessi controlli della scrittura, senza scrivere. Una
 *                  simulazione che valida in modo diverso dal caso reale
 *                  darebbe fiducia proprio dove non deve.
 *
 *                  Tre principi che non si negoziano:
 *
 *                  1. NON SI SOVRASCRIVE MAI. Una riga il cui codice esiste
 *                     gia viene saltata e dichiarata. Chi importa lo stesso
 *                     file due volte non deve poter cancellare il lavoro fatto
 *                     nel frattempo su quelle schede.
 *                  2. Ogni riga scartata dice PERCHE e a quale numero di riga
 *                     del file corrisponde: un import che ne perde un terzo in
 *                     silenzio produce un catasto incompleto che nessuno sospetta.
 *                  3. Le colonne si dichiarano, non si indovinano. Le
 *                     intestazioni si riconoscono per nome dove combaciano con
 *                     quelle dell'esportazione, ma restano modificabili.
 *
 *                  Le colonne accettate sono le stesse che l'esportazione
 *                  produce: un CSV esportato da CATAGEO si reimporta senza
 *                  toccarlo, che e il modo piu semplice per travasare dati fra
 *                  due installazioni.
 *  Versione .....: 0.16.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.16.0  2026-08-06  D.Candela  Prima stesura (fase 9b).
 * ============================================================================
 */

final class ImportIpogei
{
    /**
     * Righe importabili in una volta.
     *
     * Il limite e alto perche importare un catasto esistente e proprio il caso
     * d'uso, ma non illimitato: oltre, il tempo di esecuzione di un hosting
     * economico finisce a meta lavoro.
     */
    public const LIMITE = 2000;

    /** Quante righe si mostrano nell'anteprima. */
    public const ANTEPRIMA = 50;

    /**
     * Campi riconosciuti: chiave interna => etichetta e nomi accettati.
     *
     * I nomi alternativi sono quelli dell'esportazione piu le forme piu comuni
     * in un CSV compilato a mano.
     *
     * @var array<string,array{etichetta:string,alias:array<int,string>}>
     */
    public const CAMPI = [
        'codice'       => ['etichetta' => 'Codice',        'alias' => ['codice', 'code', 'sigla']],
        'nome'         => ['etichetta' => 'Nome',          'alias' => ['nome', 'name', 'denominazione']],
        'natura'       => ['etichetta' => 'Natura',        'alias' => ['natura']],
        'tipologia'    => ['etichetta' => 'Tipologia',     'alias' => ['tipologia']],
        'sottotipologia' => ['etichetta' => 'Sottotipologia', 'alias' => ['sottotipologia']],
        'stato'        => ['etichetta' => 'Stato (ISO)',   'alias' => ['stato']],
        'regione'      => ['etichetta' => 'Regione',       'alias' => ['regione']],
        'provincia'    => ['etichetta' => 'Provincia',     'alias' => ['provincia', 'prov']],
        'comune'       => ['etichetta' => 'Comune',        'alias' => ['comune']],
        'localita'     => ['etichetta' => 'Localita',      'alias' => ['localita', 'località', 'localita\'']],
        'lat'          => ['etichetta' => 'Latitudine',    'alias' => ['lat', 'latitudine', 'latitude', 'y']],
        'lon'          => ['etichetta' => 'Longitudine',   'alias' => ['lon', 'longitudine', 'longitude', 'lng', 'x']],
        'quota'        => ['etichetta' => 'Quota',         'alias' => ['quota', 'altitudine', 'z']],
        'sviluppo'     => ['etichetta' => 'Sviluppo',      'alias' => ['sviluppo']],
        'dislivello'   => ['etichetta' => 'Dislivello',    'alias' => ['dislivello']],
        'stato_accesso' => ['etichetta' => 'Stato accesso', 'alias' => ['stato_accesso', 'accesso']],
        'riservatezza' => ['etichetta' => 'Riservatezza',  'alias' => ['riservatezza']],
        'stato_scheda' => ['etichetta' => 'Stato scheda',  'alias' => ['stato_scheda']],
        'data_censimento' => ['etichetta' => 'Data censimento', 'alias' => ['data_censimento', 'data']],
        'sintesi'      => ['etichetta' => 'Sintesi',       'alias' => ['sintesi', 'descrizione']],
    ];

    /**
     * Campi senza i quali una riga non e importabile.
     *
     * Sono quelli che Ipogeo::valida() esige in creazione. L'elenco serve solo
     * a marcarli nel modulo e a dare un messaggio comprensibile prima ancora di
     * comporre la scheda: la validazione vera resta quella di Ipogeo, chiamata
     * anche dall'anteprima.
     */
    public const OBBLIGATORI = ['nome', 'tipologia', 'lat', 'lon'];

    /**
     * Analizza un CSV e dice cosa succederebbe, senza scrivere nulla.
     *
     * @param  array<string,int> $mappatura campo => indice di colonna
     * @return array{
     *     righe:array<int,array<string,mixed>>,
     *     totale:int, importabili:int, saltate:int, rifiutate:int,
     *     troncata:bool, avvisi:array<int,string>
     * }
     */
    public static function anteprima(
        string $percorso, array $mappatura, string $separatore, string $siglaCatalogo, bool $codiceDalFile
    ): array {
        $lette = self::leggi($percorso, $mappatura, $separatore);

        $righe = [];
        $importabili = 0;
        $saltate = 0;
        $rifiutate = 0;
        $avvisi = [];

        /*
         * Codici gia visti DENTRO il file: un CSV puo contenere due righe con
         * lo stesso codice, e la seconda non deve passare solo perche la prima
         * non e ancora stata scritta. Nell'anteprima non c'e scrittura, quindi
         * senza questo controllo entrambe risulterebbero importabili.
         */
        $vistiNelFile = [];

        /*
         * Contatori simulati per l'assegnazione automatica, come nella
         * migrazione: senza, l'anteprima mostrerebbe lo stesso codice per tutte
         * le righe.
         */
        $consumati = [];
        $catalogo = Cataloghi::trova($siglaCatalogo);

        foreach ($lette as $letta) {
            $numeroRiga = $letta['riga'];
            $valori = $letta['valori'];

            $voce = [
                'riga'    => $numeroRiga,
                'nome'    => trim((string) ($valori['nome'] ?? '')),
                'codice'  => trim((string) ($valori['codice'] ?? '')),
                'esito'   => 'importabile',
                'motivo'  => '',
                'valori'  => $valori,
            ];

            $problema = self::validaRiga($valori, $siglaCatalogo, $codiceDalFile);

            if ($problema !== '') {
                $voce['esito'] = 'rifiutata';
                $voce['motivo'] = $problema;
                $rifiutate++;
                $righe[] = $voce;
                continue;
            }

            // --- codice: dal file oppure dalla serie
            if ($codiceDalFile) {
                $codice = strtoupper($voce['codice']);

                if (isset($vistiNelFile[$codice])) {
                    $voce['esito'] = 'saltata';
                    $voce['motivo'] = 'Codice ripetuto nel file, gia alla riga '
                        . $vistiNelFile[$codice] . '.';
                    $saltate++;
                    $righe[] = $voce;
                    continue;
                }
                if (IndiceCodici::esiste($codice) || CodiceCatastale::cartellaEsistente($codice)) {
                    $voce['esito'] = 'saltata';
                    $voce['motivo'] = 'Codice gia presente in archivio: la scheda esistente '
                        . 'non viene toccata.';
                    $saltate++;
                    $righe[] = $voce;
                    continue;
                }
                $vistiNelFile[$codice] = $numeroRiga;
                $voce['codiceAssegnato'] = $codice;

            } else {
                $assegnato = self::simulaCodice($catalogo, $valori, $consumati);
                if ($assegnato === '') {
                    $voce['esito'] = 'rifiutata';
                    $voce['motivo'] = 'Nessuna serie del catalogo combacia con questi dati.';
                    $rifiutate++;
                    $righe[] = $voce;
                    continue;
                }
                $voce['codiceAssegnato'] = $assegnato;
            }

            $importabili++;
            $righe[] = $voce;
        }

        if (count($lette) >= self::LIMITE) {
            $avvisi[] = 'Il file ha raggiunto il limite di ' . self::LIMITE
                . ' righe: le successive non sono state lette. Dividere il file.';
        }

        $troncata = count($righe) > self::ANTEPRIMA;

        return [
            'righe'       => array_slice($righe, 0, self::ANTEPRIMA),
            'totale'      => count($lette),
            'importabili' => $importabili,
            'saltate'     => $saltate,
            'rifiutate'   => $rifiutate,
            'troncata'    => $troncata,
            'avvisi'      => $avvisi,
        ];
    }

    /**
     * Importa davvero.
     *
     * @param  array<string,int> $mappatura
     * @return array{creati:array<int,array{riga:int,codice:string,nome:string}>,
     *               saltate:int, rifiutate:int, errori:array<int,string>}
     */
    public static function esegui(
        string $percorso, array $mappatura, string $separatore, string $siglaCatalogo, bool $codiceDalFile
    ): array {
        $lette = self::leggi($percorso, $mappatura, $separatore);

        $creati = [];
        $saltate = 0;
        $rifiutate = 0;
        $errori = [];

        foreach ($lette as $letta) {
            $numeroRiga = $letta['riga'];
            $valori = $letta['valori'];

            $problema = self::validaRiga($valori, $siglaCatalogo, $codiceDalFile);
            if ($problema !== '') {
                $rifiutate++;
                if (count($errori) < 50) {
                    $errori[] = 'riga ' . $numeroRiga . ': ' . $problema;
                }
                continue;
            }

            $codice = strtoupper(trim((string) ($valori['codice'] ?? '')));
            if ($codiceDalFile
                && ($codice === '' || IndiceCodici::esiste($codice)
                    || CodiceCatastale::cartellaEsistente($codice))) {
                // Il controllo si rifa QUI e non ci si fida dell'anteprima: fra
                // l'una e l'altra puo essere passato tempo, e nel frattempo
                // qualcuno puo aver censito proprio quel codice.
                $saltate++;
                continue;
            }

            try {
                $nuovo = Ipogeo::crea($siglaCatalogo, self::componiScheda($valori, $codiceDalFile));
                $creati[] = [
                    'riga'   => $numeroRiga,
                    'codice' => $nuovo,
                    'nome'   => trim((string) ($valori['nome'] ?? '')),
                ];
            } catch (Throwable $e) {
                $rifiutate++;
                if (count($errori) < 50) {
                    $errori[] = 'riga ' . $numeroRiga . ': ' . $e->getMessage();
                }
            }
        }

        Log::modifica('import', $siglaCatalogo, '', 'strumenti',
            count($creati) . ' ipogei importati, ' . $saltate . ' saltati, '
            . $rifiutate . ' rifiutati');

        return ['creati' => $creati, 'saltate' => $saltate,
                'rifiutate' => $rifiutate, 'errori' => $errori];
    }

    /**
     * Mappatura suggerita confrontando le intestazioni con i nomi noti.
     *
     * @param  array<int,string> $intestazione
     * @return array<string,int>
     */
    public static function mappaturaSuggerita(array $intestazione): array
    {
        $mappatura = [];

        foreach ($intestazione as $i => $nome) {
            $normale = Testo::normalizzaRicerca(trim((string) $nome));
            if ($normale === '') {
                continue;
            }

            foreach (self::CAMPI as $campo => $dati) {
                if (isset($mappatura[$campo])) {
                    continue;
                }
                foreach ($dati['alias'] as $alias) {
                    if (Testo::normalizzaRicerca($alias) === $normale) {
                        $mappatura[$campo] = (int) $i;
                        break 2;
                    }
                }
            }
        }

        return $mappatura;
    }

    // ========================================================================
    //  INTERNI
    // ========================================================================

    /**
     * Legge il CSV applicando la mappatura, conservando il NUMERO DI RIGA vero.
     *
     * Non si riusa Scientifici::leggiCsvEsterno(), che pure fa quasi la stessa
     * cosa: quello scarta le righe vuote e restituisce un elenco compatto, e
     * cosi il numero d'ordine non corrisponde piu alla riga del file. Per una
     * serie di misure non cambia nulla; qui il numero di riga e l'informazione
     * principale del rapporto — dice all'utente dove guardare — e una riga
     * vuota a meta file lo sposterebbe per tutte quelle successive.
     *
     * @param  array<string,int> $mappatura
     * @return array<int,array{riga:int,valori:array<string,string>}>
     */
    private static function leggi(string $percorso, array $mappatura, string $separatore): array
    {
        $esiti = [];

        $maniglia = @fopen($percorso, 'r');
        if ($maniglia === false) {
            return $esiti;
        }

        // Il BOM di un "CSV UTF-8" salvato da Excel finirebbe dentro il primo
        // valore della prima colonna.
        $inizio = fread($maniglia, 3);
        if ($inizio !== "\xEF\xBB\xBF") {
            rewind($maniglia);
        }

        fgetcsv($maniglia, 0, $separatore); // intestazione
        $numeroRiga = 1;

        while (count($esiti) < self::LIMITE
               && ($riga = fgetcsv($maniglia, 0, $separatore)) !== false) {
            $numeroRiga++;

            if ($riga === [null]) {
                continue;
            }

            $valori = [];
            foreach ($mappatura as $campo => $colonna) {
                $valori[$campo] = isset($riga[$colonna]) ? trim((string) $riga[$colonna]) : '';
            }

            // Una riga tutta vuota si salta, ma il contatore e gia avanzato:
            // le righe successive conservano il loro numero vero.
            if (implode('', $valori) === '') {
                continue;
            }

            $esiti[] = ['riga' => $numeroRiga, 'valori' => $valori];
        }

        fclose($maniglia);

        return $esiti;
    }

    /**
     * Controlli su una riga. Stringa vuota se va bene.
     *
     * @param array<string,string> $valori
     */
    private static function validaRiga(array $valori, string $siglaCatalogo, bool $codiceDalFile): string
    {
        foreach (self::OBBLIGATORI as $campo) {
            if (trim((string) ($valori[$campo] ?? '')) === '') {
                return 'Manca il campo obbligatorio "' . self::CAMPI[$campo]['etichetta'] . '".';
            }
        }

        if (Cataloghi::trova($siglaCatalogo) === null) {
            return 'Catalogo di destinazione non trovato.';
        }

        if ($codiceDalFile) {
            $codice = trim((string) ($valori['codice'] ?? ''));
            if ($codice === '') {
                return 'Il codice e vuoto, ma si e scelto di prenderlo dal file.';
            }
            if (!CodiceCatastale::formaValida($codice)) {
                return 'Codice con caratteri non ammessi: ' . $codice;
            }
        }

        // La tipologia si controlla contro il vocabolario: importare una
        // tipologia inventata riempirebbe l'archivio di schede che nessun
        // filtro trova piu.
        $tipologia = trim((string) ($valori['tipologia'] ?? ''));
        if ($tipologia !== '' && Tipologie::trova($tipologia) === null) {
            return 'Tipologia non presente nel vocabolario: ' . $tipologia;
        }

        $natura = trim((string) ($valori['natura'] ?? ''));
        if ($natura !== '' && Tipologie::trova($natura) === null) {
            return 'Natura non presente nel vocabolario: ' . $natura;
        }

        foreach (['lat' => 90.0, 'lon' => 180.0] as $campo => $massimo) {
            $grezzo = trim(str_replace(',', '.', (string) ($valori[$campo] ?? '')));
            if ($grezzo === '') {
                continue;
            }
            if (!is_numeric($grezzo)) {
                return 'Coordinata non numerica: ' . $campo . ' = ' . $grezzo;
            }
            if (abs((float) $grezzo) > $massimo) {
                return 'Coordinata fuori intervallo: ' . $campo . ' = ' . $grezzo;
            }
        }

        $data = trim((string) ($valori['data_censimento'] ?? ''));
        if ($data !== '' && Scientifici::normalizzaData($data) === '') {
            return 'Data di censimento non riconosciuta: ' . $data;
        }

        /*
         * Ultimo controllo: la scheda composta passa per il validatore VERO,
         * quello che usera la scrittura. I controlli qui sopra danno messaggi
         * piu comprensibili — parlano di colonne del CSV, non di campi della
         * scheda — ma non sono l'autorita.
         *
         * Senza questa chiamata l'anteprima dichiarava importabili righe che la
         * scrittura poi rifiutava: una simulazione che valida in modo diverso
         * dal caso reale da fiducia proprio dove non deve.
         */
        try {
            Ipogeo::valida(self::componiScheda($valori, $codiceDalFile), true);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return '';
    }

    /**
     * Scheda pronta per Ipogeo::crea().
     *
     * @param  array<string,string> $valori
     * @return array<string,mixed>
     */
    private static function componiScheda(array $valori, bool $codiceDalFile): array
    {
        $prendi = static fn (string $c): string => trim((string) ($valori[$c] ?? ''));

        $numero = static function (string $c) use ($valori): string {
            $v = trim(str_replace(',', '.', (string) ($valori[$c] ?? '')));

            return $v !== '' && is_numeric($v) ? $v : '';
        };

        $riservatezza = $prendi('riservatezza');
        $statoScheda  = $prendi('stato_scheda');
        $statoAccesso = $prendi('stato_accesso');

        $scheda = [
            'identificazione' => [
                'nome'           => $prendi('nome'),
                'natura'         => $prendi('natura'),
                'tipologia'      => $prendi('tipologia'),
                'sottotipologia' => $prendi('sottotipologia'),
            ],
            'ubicazione' => [
                'stato'     => $prendi('stato') !== '' ? $prendi('stato') : 'IT',
                'regione'   => $prendi('regione'),
                'provincia' => strtoupper($prendi('provincia')),
                'comune'    => $prendi('comune'),
                'localita'  => $prendi('localita'),
                'coordinate' => [
                    'latitudine'  => $numero('lat'),
                    'longitudine' => $numero('lon'),
                    'quota'       => $numero('quota'),
                ],
                'accesso' => [
                    // Valori fuori vocabolario si riportano al riposo invece di
                    // far fallire la riga: sono dati accessori, e perdere una
                    // scheda intera per un "aperto?" scritto male sarebbe
                    // sproporzionato. Il valore scartato non viene inventato.
                    'stato' => in_array($statoAccesso, ['aperto', 'chiuso', 'interrato',
                        'distrutto', 'non_localizzato'], true) ? $statoAccesso : 'aperto',
                ],
                'riservatezza' => in_array($riservatezza,
                    ['pubblica', 'coordinate_offuscate', 'riservata'], true)
                        ? $riservatezza : 'pubblica',
            ],
            'caratteristiche' => [
                'sviluppoPlanimetrico' => $numero('sviluppo'),
                'dislivelloNegativo'   => $numero('dislivello'),
            ],
            'descrizione' => ['sintesi' => $prendi('sintesi')],
            'catasto' => [
                // Le schede importate nascono BOZZA se non e detto altrimenti:
                // un import massivo porta dentro dati che nessuno ha ancora
                // guardato, e pubblicarli d'ufficio li mescolerebbe a quelli
                // verificati.
                'statoScheda'    => in_array($statoScheda, ['bozza', 'pubblicata', 'verificata'], true)
                    ? $statoScheda : 'bozza',
                'dataCensimento' => Scientifici::normalizzaData($prendi('data_censimento')),
            ],
        ];

        if ($codiceDalFile) {
            $scheda['codiceManuale'] = strtoupper($prendi('codice'));
        }

        return $scheda;
    }

    /**
     * Prossimo codice che la serie assegnerebbe, con contatori simulati.
     *
     * @param  array<string,mixed>|null $catalogo
     * @param  array<string,string>     $valori
     * @param  array<string,int>        $consumati  modificato per riferimento
     */
    private static function simulaCodice(?array $catalogo, array $valori, array &$consumati): string
    {
        if ($catalogo === null) {
            return '';
        }

        /*
         * Gli attributi si ricavano dalla stessa funzione che usera la
         * scrittura: comporre qui un array a mano significherebbe che
         * l'anteprima puo scegliere una serie diversa da quella reale se un
         * giorno i criteri cambiano.
         */
        $serie = CodiceCatastale::risolviSerie(
            $catalogo,
            Ipogeo::attributiPerSerie(self::componiScheda($valori, false))
        );

        if ($serie === null) {
            return '';
        }

        $prefisso = (string) $serie['prefisso'];
        $consumati[$prefisso] ??= (int) $serie['prossimoProgressivo'];

        $tentativi = 0;
        do {
            $candidato = CodiceCatastale::componi(
                $prefisso, $consumati[$prefisso], (int) $serie['cifre'],
                (string) $catalogo['separatore']
            );
            $consumati[$prefisso]++;
            $tentativi++;
        } while ($tentativi < 1000
                 && (IndiceCodici::esiste($candidato) || CodiceCatastale::cartellaEsistente($candidato)));

        return $candidato;
    }
}
