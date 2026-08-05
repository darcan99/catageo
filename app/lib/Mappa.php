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
 *  Versione .....: 0.6.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.6.0  2026-08-05  D.Candela  Prima stesura (fase 4).
 * ============================================================================
 */

final class Mappa
{
    /** Tipi di layer gestiti dal front-end. */
    public const TIPI = ['tms', 'wms'];

    /**
     * Colore del marker per natura della cavita.
     *
     * Artificiale e naturale sono la distinzione che si legge a colpo d'occhio
     * su una mappa affollata: e la prima informazione che serve, quindi e quella
     * codificata nel colore.
     */
    public const COLORI_NATURA = [
        'ART' => '#c2410c',   // arancio mattone: opera dell'uomo
        'NAT' => '#0f766e',   // verde-acqua: origine naturale
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
            'overlay'  => self::overlayLayers(),
            'colori'   => self::COLORI_NATURA,
        ];
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
