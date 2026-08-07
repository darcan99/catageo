<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Mappa.php
 *  Descrizione ..: Configurazione cartografica: centro, zoom, layer di base e
 *                  layer tematici (WMS) letti da config.xml.
 *
 *                  Sta in una classe perche la stessa configurazione serve alla
 *                  pagina mappa e alla mappetta nella scheda: leggere due volte
 *                  lo stesso XML con due interpretazioni diverse e il modo piu
 *                  rapido per ritrovarsi con due mappe che non coincidono.
 *
 *                  Nessun URL viene inventato dal codice: i servizi WMS si
 *                  dichiarano in configurazione, cosi chi installa sa esattamente
 *                  a quali server esterni l'applicativo si collega.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Provider selezionabile, chiave API, elenco
 *                                degli script e origini della CSP (fase 4b).
 *  0.6.0  2026-08-05  D.Candela  Prima stesura (fase 4).
 * ============================================================================
 */

final class Mappa
{
    /** Tipi di layer gestiti dal front-end. */
    public const TIPI = ['tms', 'wms'];

    /** Provider cartografici realizzati (7.1.1, fase 4b). */
    public const PROVIDER = ['osm', 'google'];

    /** Dominio da cui Google serve la propria API JavaScript. */
    public const ORIGINE_GOOGLE = 'https://maps.googleapis.com';

    /**
     * Origini aggiuntive richieste da Google Maps oltre a quella dell'API.
     *
     * I tile, i font e le immagini dei controlli arrivano da domini diversi
     * da quello dello script: senza elencarli la mappa si carica e resta
     * grigia, con errori di Content-Security-Policy in console e nessuna
     * spiegazione in pagina.
     */
    public const ORIGINI_GOOGLE = [
        'https://maps.googleapis.com',
        'https://maps.gstatic.com',
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
        'https://*.googleapis.com',
        'https://*.ggpht.com',
    ];

    /**
     * Provider configurato, ridotto a uno di quelli realizzati.
     *
     * Un valore inventato in configurazione ricade su OpenStreetMap invece
     * di lasciare la pagina senza mappa: e la scelta prudente, ed e anche
     * l'unica che non richiede una chiave.
     */
    public static function provider(): string
    {
        $provider = strtolower(trim(Config::testo('mappa.provider', 'osm')));

        return in_array($provider, self::PROVIDER, true) ? $provider : 'osm';
    }

    /** Chiave API del provider, vuota se non configurata. */
    public static function chiaveApi(): string
    {
        return trim(Config::testo('mappa.chiaveApi', ''));
    }

    /**
     * True se il provider Google e configurato **e** utilizzabile.
     *
     * Senza chiave l'API di Google disegna una mappa in filigrana con un
     * cartello di errore: peggio di nessuna mappa, perche sembra un guasto
     * dell'applicativo. In quel caso si resta su OpenStreetMap e lo si dice.
     */
    public static function googleAttivo(): bool
    {
        return self::provider() === 'google' && self::chiaveApi() !== '';
    }

    /**
     * Indirizzo dello script dell'API di Google Maps.
     *
     * Si chiede solo la libreria di base: "places" e le altre pesano e qui
     * non servono.
     */
    public static function scriptGoogle(): string
    {
        return self::ORIGINE_GOOGLE . '/maps/api/js?v=weekly&language=it&region=IT&key='
            . rawurlencode(self::chiaveApi());
    }

    /**
     * Colore del marker per natura della cavita.
     *
     * Artificiale e naturale sono la distinzione che si legge a colpo d'occhio
     * su una mappa affollata: e la prima informazione che serve, quindi e quella
     * codificata nel colore.
     */
    /*
     * Il colore dice la NATURA, il glifo dice la tipologia (7.2.4). Sono due
     * canali distinti di proposito: chi non distingue bene arancio e verde
     * legge comunque il simbolo, e chi guarda la mappa da lontano vede
     * comunque due famiglie di colore. Affidare tutto al solo colore era il
     * limite dichiarato in docs/prove/interfaccia.
     */
    public const COLORI_NATURA = [
        'ART' => '#c2410c',   // arancio mattone: opera dell'uomo
        'NAT' => '#0f766e',   // verde-acqua: origine naturale
        'MIS' => '#6d28d9',   // viola: naturale e artificiale insieme
        ''    => '#64748b',   // grigio: natura non indicata
    ];

    /** Centro predefinito della mappa. @return array{lat:float,lon:float} */
    public static function centro(): array
    {
        return [
            'lat' => (float) str_replace(',', '.', Config::attributo('mappa.centro', 'lat', '41.9028')),
            'lon' => (float) str_replace(',', '.', Config::attributo('mappa.centro', 'lon', '12.4964')),
        ];
    }

    /** Zoom iniziale della pagina mappa. */
    public static function zoom(): int
    {
        return self::limita(Config::intero('mappa.zoom', 6), 1, 19);
    }

    /** Zoom della mappetta nella scheda, dove si guarda un singolo ingresso. */
    public static function zoomScheda(): int
    {
        return self::limita(Config::intero('mappa.zoomScheda', 16), 1, 19);
    }

    /** True se i marker vicini vanno raggruppati. */
    public static function cluster(): bool
    {
        return Config::booleano('mappa.clusterMarker', true);
    }

    /**
     * Layer di base (sfondi cartografici), nell'ordine dichiarato.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function baseLayers(): array
    {
        $layer = self::layerDa('mappa.baseLayers');

        // Senza almeno uno sfondo la mappa sarebbe una pagina grigia: se la
        // configurazione e vuota o tutta disattivata si torna a OpenStreetMap.
        if ($layer === [] || !self::almenoUnoAttivo($layer)) {
            $layer[] = self::openStreetMap();
        }

        return $layer;
    }

    /**
     * Layer tematici sovrapponibili (WMS del territorio, catasti, geologia).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function overlayLayers(): array
    {
        return self::layerDa('mappa.overlayLayers');
    }

    /**
     * Configurazione completa da passare al JavaScript.
     *
     * @return array<string,mixed>
     */
    public static function perBrowser(): array
    {
        return [
            'centro'   => self::centro(),
            'zoom'     => self::zoom(),
            'zoomScheda' => self::zoomScheda(),
            'cluster'  => self::cluster(),
            'base'     => self::baseLayers(),
            // La mappatura dei campi non serve al browser: la compilazione
            // assistita la fa il server (6.16.2). Toglierla evita di ripetere
            // in ogni pagina un blocco JSON che nessuno legge.
            'overlay'  => array_map(
                static function (array $l): array {
                    unset($l['interroga']);

                    return $l;
                },
                self::overlayLayers()
            ),
            'colori'   => self::COLORI_NATURA,
            // Il browser sceglie l'implementazione da questo valore: se la
            // chiave manca resta 'osm', cosi il ripiego e gia deciso qui e
            // non nel JavaScript, che non saprebbe perche.
            'provider' => self::googleAttivo() ? 'google' : 'osm',
        ];
    }

    /**
     * Script da caricare, nell'ordine, per avere la mappa in pagina.
     *
     * Sta qui e non nelle pagine perche l'elenco era ripetuto in cinque
     * punti: con l'arrivo del secondo provider sarebbero diventati cinque
     * posti in cui ricordarsi la stessa condizione, e il quinto sarebbe
     * rimasto indietro.
     *
     * Leaflet si carica **sempre**, anche con Google attivo: e il ripiego
     * se l'API di Google non arriva, e una pagina senza mappa e peggio di
     * centoquaranta kilobyte non usati.
     *
     * @return string[]
     */
    public static function scriptBrowser(): array
    {
        $script = [];

        if (self::googleAttivo()) {
            // Prima di tutto e senza async: le implementazioni si scelgono
            // al DOMContentLoaded, e a quel punto google.maps deve esserci.
            $script[] = self::scriptGoogle();
        }

        $script[] = 'assets/vendor/leaflet-1.9.4/leaflet.js';
        $script[] = 'assets/js/catageo-mappa-api.js';

        if (self::googleAttivo()) {
            $script[] = 'assets/js/catageo-mappa-google.js';
        }

        $script[] = 'assets/js/catageo-mappa.js';

        return $script;
    }

    /**
     * Origini esterne da cui la mappa scarica immagini, in forma utilizzabile
     * dentro una Content-Security-Policy.
     *
     * Si ricavano dai layer configurati e non da un elenco fisso: aggiungere un
     * WMS in config.xml deve bastare, senza dover ricordarsi di toccare anche la
     * policy. Il segnaposto {s} dei tile server diventa un carattere jolly,
     * perche i sottodomini sono equivalenti.
     *
     * @return string[]
     */
    public static function originiEsterne(): array
    {
        $origini = [];

        /*
         * Con il provider Google i domini non si ricavano dai layer: la
         * mappa la disegna la loro API, che scarica tile, font e immagini
         * dei controlli da host suoi. E la deroga documentata al vincolo
         * "nessuna CDN" (16.1), e vale solo quando quel provider e attivo:
         * chi resta su OpenStreetMap conserva la policy stretta.
         */
        if (self::googleAttivo()) {
            foreach (self::ORIGINI_GOOGLE as $origine) {
                $origini[$origine] = true;
            }
        }

        foreach (array_merge(self::baseLayers(), self::overlayLayers()) as $layer) {
            $url = str_replace('{s}.', '*.', (string) $layer['url']);
            // Delimitatore ~ e non #: il cancelletto compare dentro la classe di
            // caratteri, e come delimitatore chiuderebbe l'espressione a meta.
            if (!preg_match('~^(https?)://([^/?\#]+)~i', $url, $parti)) {
                continue;
            }
            // Si tiene solo host e porta: il percorso non fa parte di una
            // sorgente CSP e includerlo la renderebbe inefficace.
            $origine = strtolower($parti[1]) . '://' . $parti[2];
            $origini[$origine] = true;
        }

        return array_keys($origini);
    }

    /**
     * Campi della sezione geologia compilabili da una carta.
     *
     * Sono pochi di proposito. Una carta geologica dice di che roccia e fatto
     * il terreno sopra la cavita; non dice se la cavita e attiva, se prosegue
     * o quanto e fratturata la volta. Ammettere qui altre chiavi darebbe
     * l'impressione che il resto della sezione si possa compilare senza
     * scendere.
     */
    public const CAMPI_INTERROGABILI = [
        'litologia', 'formazione', 'unitaGeologica', 'etaFormazione', 'permeabilita',
    ];

    /**
     * Layer che sanno rispondere alla compilazione assistita (6.16.2).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function layerInterrogabili(): array
    {
        $risultato = [];
        foreach (self::overlayLayers() as $layer) {
            if (($layer['tipo'] ?? '') === 'wms' && ($layer['interroga'] ?? []) !== []) {
                $risultato[] = $layer;
            }
        }

        return $risultato;
    }

    /**
     * Interpreta l'attributo interroga: "litologia:campo,formazione:altro".
     *
     * Le chiavi fuori elenco si scartano in silenzio invece di far fallire la
     * lettura della configurazione: un refuso in un attributo facoltativo non
     * puo lasciare l'installazione senza mappa.
     *
     * @return array<string,string>
     */
    private static function mappaturaCampi(string $grezzo): array
    {
        $grezzo = trim($grezzo);
        if ($grezzo === '') {
            return [];
        }

        $mappa = [];
        foreach (explode(',', $grezzo) as $coppia) {
            $pezzi = explode(':', $coppia, 2);
            if (count($pezzi) !== 2) {
                continue;
            }
            $nostro = trim($pezzi[0]);
            $loro   = trim($pezzi[1]);
            if ($loro === '' || !in_array($nostro, self::CAMPI_INTERROGABILI, true)) {
                continue;
            }
            $mappa[$nostro] = $loro;
        }

        return $mappa;
    }

    /**
     * Legge un gruppo di layer da configurazione.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function layerDa(string $chiave): array
    {
        $risultato = [];
        $progr     = 0;

        foreach (Config::elementi($chiave . '.layer') as $nodo) {
            $url = trim($nodo->getAttribute('url'));
            if ($url === '' || !self::urlAmmesso($url)) {
                continue;
            }

            $tipo = strtolower(trim($nodo->getAttribute('tipo')));
            if (!in_array($tipo, self::TIPI, true)) {
                $tipo = 'tms';
            }

            $progr++;
            $id = trim($nodo->getAttribute('id'));

            $voce = [
                'id'           => $id !== '' ? $id : 'layer' . $progr,
                'nome'         => trim($nodo->getAttribute('nome')) !== ''
                                    ? trim($nodo->getAttribute('nome'))
                                    : 'Layer ' . $progr,
                'tipo'         => $tipo,
                'url'          => $url,
                'attribuzione' => trim($nodo->getAttribute('attribuzione')),
                'maxZoom'      => self::limita((int) ($nodo->getAttribute('maxZoom') ?: '19'), 1, 24),
                'minZoom'      => self::limita((int) ($nodo->getAttribute('minZoom') ?: '0'), 0, 24),
                'attivo'       => in_array(strtolower($nodo->getAttribute('attivo')), ['1', 'true', 'si'], true),
                'opacita'      => self::opacita($nodo->getAttribute('opacita')),
            ];

            if ($tipo === 'wms') {
                // I nomi dei layer sono obbligatori per un WMS: senza di essi il
                // server risponde con un errore XML e Leaflet mostra riquadri vuoti.
                $voce['layers']      = trim($nodo->getAttribute('layers'));
                $voce['formato']     = trim($nodo->getAttribute('formato')) ?: 'image/png';
                $voce['versione']    = trim($nodo->getAttribute('versione')) ?: '1.3.0';
                $voce['trasparente'] = strtolower($nodo->getAttribute('trasparente')) !== '0';
                $voce['interroga']   = self::mappaturaCampi($nodo->getAttribute('interroga'));
                if ($voce['layers'] === '') {
                    continue;
                }
            } else {
                $voce['sottodomini'] = trim($nodo->getAttribute('sottodomini')) ?: 'abc';
            }

            $risultato[] = $voce;
        }

        return $risultato;
    }

    /**
     * Accetta solo http e https.
     *
     * Gli URL arrivano da config.xml, quindi da chi amministra il server, ma
     * finiscono dentro una pagina: uno schema come javascript: trasformerebbe un
     * errore di configurazione in un problema di sicurezza.
     */
    private static function urlAmmesso(string $url): bool
    {
        return (bool) preg_match('~^https?://~i', $url);
    }

    /** Opacita fra 0 e 1, con default 1 (opaco). */
    private static function opacita(string $valore): float
    {
        $valore = trim(str_replace(',', '.', $valore));
        if ($valore === '' || !is_numeric($valore)) {
            return 1.0;
        }
        return max(0.0, min(1.0, (float) $valore));
    }

    /** @param array<int,array<string,mixed>> $layer */
    private static function almenoUnoAttivo(array $layer): bool
    {
        foreach ($layer as $voce) {
            if (!empty($voce['attivo'])) {
                return true;
            }
        }
        return false;
    }

    /** Sfondo di riserva, identico a quello di config.xml.dist. */
    private static function openStreetMap(): array
    {
        return [
            'id'           => 'osm',
            'nome'         => 'OpenStreetMap',
            'tipo'         => 'tms',
            'url'          => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribuzione' => '&copy; OpenStreetMap contributors',
            'maxZoom'      => 19,
            'minZoom'      => 0,
            'attivo'       => true,
            'opacita'      => 1.0,
            'sottodomini'  => 'abc',
        ];
    }

    private static function limita(int $valore, int $min, int $max): int
    {
        return max($min, min($max, $valore));
    }
}
