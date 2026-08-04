<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/VocabolariPredefiniti.php
 *  Descrizione ..: Contenuto iniziale dei vocabolari controllati: tassonomia
 *                  degli ipogei, grandezze misurabili, periodi storici.
 *
 *                  Sono qui e non in file XML versionati per un motivo: cosi
 *                  l'installer e la creazione pigra usano la stessa unica
 *                  fonte, e non possono divergere. Tutti i valori sono
 *                  modificabili dall'interfaccia dopo l'installazione: questo e
 *                  un punto di partenza, non un vincolo.
 *  Versione .....: 0.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

final class VocabolariPredefiniti
{
    /**
     * Tassonomia iniziale: natura > tipologia > sottotipologia.
     *
     * La parte sulle cavita artificiali segue le categorie funzionali in uso
     * nella letteratura speleologica italiana; quella sulle naturali distingue
     * per processo genetico.
     */
    public static function tipologie(): DOMDocument
    {
        $albero = [
            'ART' => [
                'nome' => 'Cavita artificiale',
                'figli' => [
                    'ART-IDR' => ['nome' => 'Opere idrauliche', 'figli' => [
                        'ART-IDR-CUN' => 'Cunicolo drenante',
                        'ART-IDR-ACQ' => 'Acquedotto',
                        'ART-IDR-CIS' => 'Cisterna',
                        'ART-IDR-POZ' => 'Pozzo',
                        'ART-IDR-FOG' => 'Fognatura',
                        'ART-IDR-EMI' => 'Emissario',
                    ]],
                    'ART-EST' => ['nome' => 'Opere estrattive', 'figli' => [
                        'ART-EST-CAV' => 'Cava ipogea',
                        'ART-EST-MIN' => 'Miniera',
                        'ART-EST-POZ' => 'Pozzo di estrazione',
                    ]],
                    'ART-CUL' => ['nome' => 'Insediamenti e opere di culto', 'figli' => [
                        'ART-CUL-CAT' => 'Catacomba',
                        'ART-CUL-CHR' => 'Chiesa rupestre',
                        'ART-CUL-IPO' => 'Ipogeo funerario',
                        'ART-CUL-MIT' => 'Mitreo',
                        'ART-CUL-ERE' => 'Eremo',
                    ]],
                    'ART-ABI' => ['nome' => 'Insediamenti civili', 'figli' => [
                        'ART-ABI-RUP' => 'Abitato rupestre',
                        'ART-ABI-CAN' => 'Cantina o magazzino',
                        'ART-ABI-NEV' => 'Neviera o ghiacciaia',
                        'ART-ABI-BUT' => 'Butto o pozzo di scarico',
                    ]],
                    'ART-BEL' => ['nome' => 'Opere belliche', 'figli' => [
                        'ART-BEL-RIC' => 'Ricovero antiaereo',
                        'ART-BEL-GAL' => 'Galleria militare',
                        'ART-BEL-POS' => 'Postazione fortificata',
                        'ART-BEL-DEP' => 'Deposito munizioni',
                    ]],
                    'ART-TRA' => ['nome' => 'Opere di transito', 'figli' => [
                        'ART-TRA-GAL' => 'Galleria stradale o ferroviaria',
                        'ART-TRA-PAS' => 'Passaggio o cunicolo di collegamento',
                    ]],
                    'ART-ALT' => ['nome' => 'Altro o non determinato', 'figli' => []],
                ],
            ],
            'NAT' => [
                'nome' => 'Cavita naturale',
                'figli' => [
                    'NAT-CAR' => ['nome' => 'Carsica', 'figli' => [
                        'NAT-CAR-GRO' => 'Grotta di dissoluzione',
                        'NAT-CAR-ABI' => 'Abisso o pozzo carsico',
                        'NAT-CAR-RIS' => 'Risorgenza',
                        'NAT-CAR-ING' => 'Inghiottitoio',
                    ]],
                    'NAT-VUL' => ['nome' => 'Vulcanica', 'figli' => [
                        'NAT-VUL-TUB' => 'Tubo di scorrimento lavico',
                        'NAT-VUL-CAM' => 'Camera di degassamento',
                    ]],
                    'NAT-MAR' => ['nome' => 'Marina o di abrasione', 'figli' => []],
                    'NAT-TET' => ['nome' => 'Tettonica', 'figli' => []],
                    'NAT-GLA' => ['nome' => 'Glaciale o nivale', 'figli' => []],
                    'NAT-ERO' => ['nome' => 'Di erosione o interstrato', 'figli' => []],
                    'NAT-ALT' => ['nome' => 'Altro o non determinato', 'figli' => []],
                ],
            ],
            'MIS' => [
                'nome' => 'Cavita mista',
                'figli' => [
                    'MIS-NAT' => ['nome' => 'Naturale adattata dall\'uomo', 'figli' => []],
                    'MIS-ART' => ['nome' => 'Artificiale che intercetta vuoti naturali', 'figli' => []],
                ],
            ],
        ];

        $doc    = Xml::nuovo('tipologie', ['versioneSchema' => '1.0']);
        $radice = $doc->documentElement;

        foreach ($albero as $codiceNatura => $natura) {
            $nodoNatura = Xml::aggiungi($radice, 'natura', null, [
                'codice' => $codiceNatura,
                'nome'   => $natura['nome'],
                'attivo' => '1',
            ]);

            foreach ($natura['figli'] as $codiceTipologia => $tipologia) {
                $nodoTipologia = Xml::aggiungi($nodoNatura, 'tipologia', null, [
                    'codice' => $codiceTipologia,
                    'nome'   => $tipologia['nome'],
                    'attivo' => '1',
                ]);

                foreach ($tipologia['figli'] as $codiceSotto => $nomeSotto) {
                    Xml::aggiungi($nodoTipologia, 'sotto', null, [
                        'codice' => $codiceSotto,
                        'nome'   => $nomeSotto,
                        'attivo' => '1',
                    ]);
                }
            }
        }

        return $doc;
    }

    /**
     * Grandezze misurabili in cavita, con unita e intervalli di plausibilita.
     *
     * Gli intervalli non bloccano l'inserimento: servono a intercettare gli
     * errori di battitura proponendo di marcare la lettura come sospetta.
     */
    public static function grandezze(): DOMDocument
    {
        $categorie = [
            'CLIMA' => ['nome' => 'Clima ipogeo', 'grandezze' => [
                ['T-ARIA',  'Temperatura aria',       '°C',    '-20',  '60',     '2'],
                ['T-ACQUA', 'Temperatura acqua',      '°C',    '-2',   '40',     '2'],
                ['T-ROCCIA','Temperatura roccia',     '°C',    '-20',  '60',     '2'],
                ['UR',      'Umidita relativa',       '%',     '0',    '100',    '1'],
                ['P-BAR',   'Pressione barometrica',  'hPa',   '800',  '1100',   '1'],
                ['V-ARIA',  'Velocita aria',          'm/s',   '0',    '30',     '2'],
                ['Q-ARIA',  'Portata d\'aria',        'm3/s',  '0',    '500',    '2'],
            ]],
            'GAS' => ['nome' => 'Gas e qualita dell\'aria', 'grandezze' => [
                ['CO2', 'Anidride carbonica',      'ppm', '0', '100000', '0'],
                ['O2',  'Ossigeno',                '%',   '0', '25',     '2'],
                ['CH4', 'Metano',                  'ppm', '0', '50000',  '0'],
                ['H2S', 'Acido solfidrico',        'ppm', '0', '1000',   '1'],
                ['CO',  'Monossido di carbonio',   'ppm', '0', '1000',   '1'],
            ]],
            'RAD' => ['nome' => 'Radioattivita', 'grandezze' => [
                ['RADON',    'Concentrazione radon',        'Bq/m3', '0', '200000', '0'],
                ['DOSE',     'Rateo di dose ambientale',    'uSv/h', '0', '1000',   '3'],
                ['DOSE-CUM', 'Dose cumulata',               'mSv',   '0', '10000',  '3'],
            ]],
            'ACQUA' => ['nome' => 'Idrologia', 'grandezze' => [
                ['Q-ACQUA', 'Portata',            'l/s',   '0',    '100000', '2'],
                ['H-ACQUA', 'Livello idrico',     'm',     '-100', '500',    '3'],
                ['PH',      'pH',                 '',      '0',    '14',     '2'],
                ['COND',    'Conducibilita',      'uS/cm', '0',    '100000', '0'],
                ['DUR',     'Durezza',            '°f',    '0',    '200',    '1'],
                ['TORB',    'Torbidita',          'NTU',   '0',    '10000',  '1'],
            ]],
            'ALTRO' => ['nome' => 'Altre misure', 'grandezze' => [
                ['LUX',   'Illuminamento',            'lux', '0', '150000', '0'],
                ['DB',    'Livello sonoro',           'dB',  '0', '160',    '1'],
            ]],
        ];

        $doc    = Xml::nuovo('grandezze', ['versioneSchema' => '1.0']);
        $radice = $doc->documentElement;

        foreach ($categorie as $codiceCategoria => $categoria) {
            $nodoCategoria = Xml::aggiungi($radice, 'categoria', null, [
                'codice' => $codiceCategoria,
                'nome'   => $categoria['nome'],
                'attivo' => '1',
            ]);

            foreach ($categoria['grandezze'] as [$codice, $nome, $unita, $min, $max, $decimali]) {
                Xml::aggiungi($nodoCategoria, 'grandezza', null, [
                    'codice'   => $codice,
                    'nome'     => $nome,
                    'unita'    => $unita,
                    'min'      => $min,
                    'max'      => $max,
                    'decimali' => $decimali,
                    'attivo'   => '1',
                ]);
            }
        }

        return $doc;
    }

    /**
     * Cronologia di riferimento per la datazione archeologica.
     *
     * Gli estremi in anni sono indicativi e servono alla ricerca per intervallo:
     * gli anni negativi sono a.C. Prima e seconda guerra mondiale sono periodi
     * distinti perche nelle cavita artificiali datano opere diverse.
     */
    public static function periodi(): DOMDocument
    {
        $periodi = [
            ['PREIST',   'Preistoria',                 '-1000000', '-3500'],
            ['PROTOST',  'Protostoria',                '-3500',    '-750'],
            ['ETRUSCO',  'Eta etrusca',                '-900',     '-100'],
            ['GRECO',    'Eta greca e magnogreca',     '-800',     '-200'],
            ['ROM-REP',  'Eta romana repubblicana',    '-509',     '-27'],
            ['ROM-IMP',  'Eta romana imperiale',       '-27',      '476'],
            ['TARDOANT', 'Tardo antico',               '284',      '600'],
            ['ALTOMED',  'Alto medioevo',              '600',      '1000'],
            ['BASSOMED', 'Basso medioevo',             '1000',     '1492'],
            ['MODERNA',  'Eta moderna',                '1492',     '1789'],
            ['CONTEMP',  'Eta contemporanea',          '1789',     '1914'],
            ['WWI',      'Prima guerra mondiale',      '1914',     '1918'],
            ['INTERB',   'Periodo interbellico',       '1919',     '1939'],
            ['WWII',     'Seconda guerra mondiale',    '1940',     '1945'],
            ['DOPOG',    'Secondo dopoguerra e oltre', '1946',     '2100'],
            ['INDET',    'Non determinato',            '',         ''],
        ];

        $doc    = Xml::nuovo('periodi', ['versioneSchema' => '1.0']);
        $radice = $doc->documentElement;

        foreach ($periodi as [$codice, $nome, $da, $a]) {
            Xml::aggiungi($radice, 'periodo', null, [
                'codice' => $codice,
                'nome'   => $nome,
                'da'     => $da,
                'a'      => $a,
                'attivo' => '1',
            ]);
        }

        return $doc;
    }
}
