<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Geoservizi.php
 *  Descrizione ..: Compilazione assistita della sezione geologia: interroga i
 *                  layer WMS configurati con GetFeatureInfo e propone i valori
 *                  trovati (6.16.2, fase 6b).
 *
 *                  Propone, non scrive. Una carta 1:100.000 non distingue una
 *                  lente di dieci metri: il valore che torna e un punto di
 *                  partenza da confermare, e ogni proposta porta con se il
 *                  layer da cui viene, cosi chi la accetta sa cosa sta
 *                  accettando.
 *
 *                  L'interrogazione la fa il server, non il browser. Tre
 *                  motivi, in ordine di importanza: la politica sulle
 *                  coordinate riservate deve stare dove l'utente non la puo
 *                  aggirare; i servizi degli enti non mandano gli header CORS
 *                  e il browser non potrebbe leggere la risposta; la CSP non
 *                  va allargata a connect-src per host che servono immagini.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Prima stesura (fase 6b).
 * ============================================================================
 */

final class Geoservizi
{
    /** Modi ammessi per una cavita a coordinate riservate (6.16.2). */
    public const MODI = ['puntuale', 'offuscata', 'niente'];

    /** Secondi concessi a un singolo servizio. */
    public const TIMEOUT = 12;

    /**
     * Secondi concessi all'intero giro.
     *
     * Sei servizi da dodici secondi farebbero attendere piu di un minuto una
     * pagina che deve rispondere subito: oltre questa soglia si smette di
     * chiedere e si dice quali layer non si sono fatti in tempo.
     */
    public const TIMEOUT_TOTALE = 30;

    /** Byte oltre i quali una risposta si scarta senza leggerla. */
    public const LIMITE_RISPOSTA = 2097152;

    /**
     * Lato del riquadro chiesto al servizio, in gradi.
     *
     * Circa 550 m: abbastanza da non cadere fuori da un poligono per un
     * arrotondamento, abbastanza poco da non prendere la formazione della
     * valle accanto.
     */
    private const MEZZO_LATO = 0.0025;

    /**
     * Interroga i layer configurati e restituisce i valori proposti.
     *
     * @param  string $modo uno di self::MODI
     * @return array{
     *     modo:string,
     *     coordinate:array{lat:string,lon:string,offuscate:bool,metri:int},
     *     proposte:array<string,array{valore:string,layer:string,fonte:string}>,
     *     interrogati:array<int,string>,
     *     falliti:array<int,array{layer:string,motivo:string}>
     * }
     */
    public static function interroga(float $lat, float $lon, string $modo = 'puntuale'): array
    {
        if (!in_array($modo, self::MODI, true)) {
            $modo = 'niente';
        }

        $esito = [
            'modo'        => $modo,
            'coordinate'  => ['lat' => '', 'lon' => '', 'offuscate' => false, 'metri' => 0],
            'proposte'    => [],
            'interrogati' => [],
            'falliti'     => [],
        ];

        if ($modo === 'niente') {
            return $esito;
        }

        if ($modo === 'offuscata') {
            $griglia = Visibilita::griglia($lat, $lon);
            $lat     = (float) $griglia['lat'];
            $lon     = (float) $griglia['lon'];
            $esito['coordinate'] = [
                'lat' => $griglia['lat'], 'lon' => $griglia['lon'],
                'offuscate' => true, 'metri' => $griglia['metri'],
            ];
        } else {
            $esito['coordinate'] = [
                'lat' => number_format($lat, 6, '.', ''),
                'lon' => number_format($lon, 6, '.', ''),
                'offuscate' => false, 'metri' => 0,
            ];
        }

        $scadenza = time() + self::TIMEOUT_TOTALE;

        foreach (Mappa::layerInterrogabili() as $layer) {
            $nome = (string) $layer['nome'];

            if (time() >= $scadenza) {
                $esito['falliti'][] = ['layer' => $nome, 'motivo' => 'tempo scaduto prima di chiedere'];
                continue;
            }

            $campi = self::campiDa($layer, $lat, $lon);
            if ($campi === null) {
                $esito['falliti'][] = ['layer' => $nome, 'motivo' => 'il servizio non ha risposto'];
                continue;
            }

            $esito['interrogati'][] = $nome;

            if ($campi === []) {
                continue;
            }

            /*
             * Vince chi risponde per primo: l'ordine in config.xml e l'ordine
             * di fiducia, e in genere mette la carta regionale di dettaglio
             * prima di quella nazionale. Sovrascrivere significherebbe far
             * decidere l'ultimo layer dell'elenco.
             */
            foreach ((array) $layer['interroga'] as $nostro => $loro) {
                if (isset($esito['proposte'][$nostro])) {
                    continue;
                }
                $valore = self::valore($campi, (string) $loro);
                if ($valore === '') {
                    continue;
                }
                $esito['proposte'][$nostro] = [
                    'valore' => $valore,
                    'layer'  => $nome,
                    'fonte'  => trim((string) ($layer['attribuzione'] ?? '')) ?: $nome,
                ];
            }
        }

        /*
         * La permeabilita del servizio ISPRA arriva come frase descrittiva
         * ("Permeabilita bassa/Porosita-Fratturazione"): va ricondotta al
         * vocabolario, altrimenti finirebbe scartata dal normalizzatore e
         * l'utente vedrebbe una proposta che non si puo accettare.
         */
        if (isset($esito['proposte']['permeabilita'])) {
            $tradotta = self::permeabilita($esito['proposte']['permeabilita']['valore']);
            if ($tradotta === '') {
                unset($esito['proposte']['permeabilita']);
            } else {
                $esito['proposte']['permeabilita']['valore'] = $tradotta;
            }
        }

        return $esito;
    }

    /**
     * Campi del primo elemento trovato sotto il punto, o null se il servizio
     * non ha risposto. Un array vuoto significa "risposto, ma li non c'e nulla".
     *
     * @param  array<string,mixed> $layer
     * @return array<string,string>|null
     */
    private static function campiDa(array $layer, float $lat, float $lon): ?array
    {
        $url  = (string) $layer['url'];
        $lato = self::MEZZO_LATO;

        /*
         * In WMS 1.3.0 con EPSG:4326 l'ordine degli assi e latitudine,
         * longitudine — l'inverso di 1.1.1. Invertirli non da errore: da le
         * rocce di un punto in mezzo al mare, e nessuno se ne accorge.
         */
        $parametri = [
            'service'       => 'WMS',
            'version'       => '1.3.0',
            'request'       => 'GetFeatureInfo',
            'layers'        => (string) $layer['layers'],
            'query_layers'  => (string) $layer['layers'],
            'styles'        => '',
            'crs'           => 'EPSG:4326',
            'bbox'          => sprintf('%F,%F,%F,%F', $lat - $lato, $lon - $lato, $lat + $lato, $lon + $lato),
            'width'         => 256,
            'height'        => 256,
            'i'             => 128,
            'j'             => 128,
            'info_format'   => 'application/json',
            'feature_count' => 1,
        ];

        $risposta = self::scarica($url . (str_contains($url, '?') ? '&' : '?') . http_build_query($parametri));
        if ($risposta === null) {
            return null;
        }

        $json = json_decode($risposta, true);
        if (!is_array($json) || !isset($json['features']) || !is_array($json['features'])) {
            // Molti servizi rispondono con un ServiceException XML: e una
            // risposta, ma non contiene dati utilizzabili.
            return [];
        }
        if ($json['features'] === [] || !isset($json['features'][0]['properties'])) {
            return [];
        }

        $campi = [];
        foreach ((array) $json['features'][0]['properties'] as $chiave => $valore) {
            if (is_scalar($valore)) {
                $campi[(string) $chiave] = trim((string) $valore);
            }
        }

        return $campi;
    }

    /**
     * Legge un campo tollerando le differenze di maiuscole.
     *
     * I nomi dei campi dei servizi non seguono nessuna regola: nello stesso
     * GeoServer convivono nome_ulf, ETAINF e Shape_Area. Chi scrive il config
     * non deve indovinare anche le maiuscole.
     *
     * @param array<string,string> $campi
     */
    private static function valore(array $campi, string $cercato): string
    {
        if (isset($campi[$cercato])) {
            return self::ripulisci($campi[$cercato]);
        }
        foreach ($campi as $chiave => $valore) {
            if (strcasecmp($chiave, $cercato) === 0) {
                return self::ripulisci($valore);
            }
        }

        return '';
    }

    /** Scarta i segnaposto che i servizi usano al posto del vuoto. */
    private static function ripulisci(string $valore): string
    {
        $valore = trim(preg_replace('/\s+/u', ' ', $valore) ?? '');
        $vuoti  = ['', '-', '--', 'n.d.', 'nd', 'null', 'nessuno', '<null>', '0'];

        return in_array(mb_strtolower($valore), $vuoti, true) ? '' : $valore;
    }

    /**
     * Riconduce la descrizione di permeabilita del servizio al vocabolario.
     *
     * Il servizio ISPRA descrive il meccanismo prima del grado
     * ("Permeabilita bassa/Porosita-Fratturazione"): e il meccanismo, non il
     * grado, a corrispondere al nostro campo.
     */
    private static function permeabilita(string $descrizione): string
    {
        $d = mb_strtolower($descrizione);

        if (str_contains($d, 'carsi')) {
            return 'per carsismo';
        }
        if (str_contains($d, 'fessur') || str_contains($d, 'frattur')) {
            return 'per fessurazione';
        }
        if (str_contains($d, 'porosit')) {
            return 'per porosita';
        }
        /*
         * «molto bassa» e non «permeabilita molto bassa»: il servizio scrive
         * «Permeabilità» con l'accento, e un confronto che lo ignora non
         * incontra mai la stringa vera. Le sottostringhe qui sopra funzionano
         * per lo stesso motivo — si fermano tutte prima della vocale accentata.
         */
        if (str_contains($d, 'impermeab') || str_contains($d, 'molto bassa')) {
            return 'impermeabile';
        }

        return '';
    }

    /**
     * Scarica una risorsa esterna, o null se non arriva.
     *
     * Niente eccezioni: un ente che non risponde non e un errore
     * dell'applicativo, e non deve interrompere l'interrogazione degli altri.
     */
    private static function scarica(string $url): ?string
    {
        $contesto = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => self::TIMEOUT,
                'ignore_errors' => true,
                'follow_location' => 1,
                'max_redirects' => 3,
                'header'        => "Accept: application/json,*/*\r\n"
                    . 'User-Agent: CATAGEO/' . CATAGEO_VERSIONE . " (catasto ipogei)\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $flusso = @fopen($url, 'rb', false, $contesto);
        if ($flusso === false) {
            return null;
        }

        // stream_get_contents con un limite: un servizio che risponde con
        // duecento megabyte di GML non deve poter esaurire la memoria.
        $corpo = @stream_get_contents($flusso, self::LIMITE_RISPOSTA);
        @fclose($flusso);

        return is_string($corpo) && $corpo !== '' ? $corpo : null;
    }
}
