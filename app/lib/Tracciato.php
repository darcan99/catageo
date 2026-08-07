<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Tracciato.php
 *  Descrizione ..: Conversione dei rilievi georiferiti (KML, KMZ, GPX) in
 *                  GeoJSON, per sovrapporli alla mappa (§7.3).
 *
 *                  La conversione avviene sul server e non nel browser: cosi la
 *                  mappa riceve GeoJSON, che Leaflet consuma nativamente, e non
 *                  serve alcun plugin JavaScript in piu. E anche l'unico modo
 *                  per far valere la riservatezza, visto che il file non e mai
 *                  raggiungibile per URL diretto.
 *
 *                  Si leggono le geometrie e i nomi, non gli stili: uno stile
 *                  KML tradotto a meta produce una mappa peggiore di una mappa
 *                  con uno stile scelto da noi. Il colore lo decide CATAGEO.
 *
 *                  I file di rilievo arrivano da software di terzi e possono
 *                  essere enormi o malformati: si legge con un limite di
 *                  dimensione, senza risolvere entita esterne, e un file
 *                  illeggibile produce un elenco vuoto e non un errore fatale.
 *  Versione .....: 0.12.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.0 2026-08-06  D.Candela  TracciatoEccezione spostata in app/lib/TracciatoEccezione.php:
 *                                l'autoload risolve una classe per file.
 *  0.8.0  2026-08-05  D.Candela  Prima stesura (fase 6).
 * ============================================================================
 */

final class Tracciato
{
    /** Estensioni che si sanno sovrapporre alla mappa. */
    public const FORMATI = ['kml', 'kmz', 'gpx'];

    /**
     * Oltre questa dimensione il file non viene convertito.
     *
     * Un rilievo di poligonale sta in poche centinaia di kilobyte; un file da
     * decine di megabyte e quasi sempre un modello esportato per sbaglio in KML,
     * e caricarlo in memoria su un hosting economico significa una pagina bianca.
     */
    public const LIMITE_BYTE = 12582912; // 12 MB

    /** Tetto ai punti restituiti, per non affogare il browser. */
    public const LIMITE_PUNTI = 200000;

    /** True se l'estensione e fra quelle convertibili. */
    public static function convertibile(string $nomeFile): bool
    {
        $estensione = strtolower((string) pathinfo($nomeFile, PATHINFO_EXTENSION));

        return in_array($estensione, self::FORMATI, true);
    }

    /**
     * Converte un file in una FeatureCollection GeoJSON.
     *
     * @return array<string,mixed>
     * @throws TracciatoEccezione se il file non e leggibile o non e supportato
     */
    public static function aGeoJson(string $percorso): array
    {
        if (!is_file($percorso)) {
            throw new TracciatoEccezione('File del rilievo non trovato.');
        }

        $dimensione = (int) filesize($percorso);
        if ($dimensione > self::LIMITE_BYTE) {
            throw new TracciatoEccezione(
                'Il rilievo pesa ' . Testo::dimensione($dimensione)
                . ' e supera il limite di ' . Testo::dimensione(self::LIMITE_BYTE)
                . ' per la sovrapposizione in mappa. Il file resta scaricabile.'
            );
        }

        $estensione = strtolower((string) pathinfo($percorso, PATHINFO_EXTENSION));

        $xml = match ($estensione) {
            'kml' => (string) file_get_contents($percorso),
            'kmz' => self::estraiDaKmz($percorso),
            'gpx' => (string) file_get_contents($percorso),
            default => throw new TracciatoEccezione('Formato non convertibile: .' . $estensione),
        };

        if (trim($xml) === '') {
            throw new TracciatoEccezione('Il file non contiene dati leggibili.');
        }

        $doc = self::caricaXml($xml);

        $elementi = $estensione === 'gpx'
            ? self::daGpx($doc)
            : self::daKml($doc);

        return [
            'type'     => 'FeatureCollection',
            'features' => $elementi,
        ];
    }

    /**
     * Estrae il documento KML da un archivio KMZ.
     *
     * Un KMZ e uno zip: il documento principale e "doc.kml" per convenzione, ma
     * i programmi non la rispettano tutti, quindi si accetta il primo .kml
     * trovato. Le immagini e le sovrapposizioni eventualmente contenute non
     * vengono estratte: qui interessano le geometrie.
     */
    private static function estraiDaKmz(string $percorso): string
    {
        if (!class_exists('ZipArchive')) {
            throw new TracciatoEccezione(
                'I file KMZ richiedono l\'estensione zip di PHP, che non è disponibile. '
                . 'Caricare il rilievo come KML per vederlo in mappa.'
            );
        }

        $zip = new ZipArchive();
        if ($zip->open($percorso) !== true) {
            throw new TracciatoEccezione('Archivio KMZ non apribile.');
        }

        try {
            $nome = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $voce = (string) $zip->getNameIndex($i);

                // Un nome con ".." dentro non deve nemmeno essere considerato:
                // qui non si estrae su disco, ma la prudenza costa una riga.
                if (str_contains($voce, '..')) {
                    continue;
                }
                if (strtolower((string) pathinfo($voce, PATHINFO_EXTENSION)) === 'kml') {
                    $nome = $voce;
                    if (strtolower(basename($voce)) === 'doc.kml') {
                        break; // il nome convenzionale ha la precedenza
                    }
                }
            }

            if ($nome === null) {
                throw new TracciatoEccezione('L\'archivio KMZ non contiene un documento KML.');
            }

            $stat = $zip->statName($nome);
            if (is_array($stat) && (int) ($stat['size'] ?? 0) > self::LIMITE_BYTE) {
                throw new TracciatoEccezione('Il KML dentro il KMZ supera il limite di dimensione.');
            }

            $contenuto = $zip->getFromName($nome);

            return $contenuto === false ? '' : $contenuto;
        } finally {
            $zip->close();
        }
    }

    /**
     * Carica l'XML senza rete e senza entita esterne.
     *
     * Un file di rilievo arriva da fuori: LIBXML_NONET e la difesa contro un
     * documento che tenti di far scaricare qualcosa al server.
     */
    private static function caricaXml(string $xml): DOMDocument
    {
        $precedente = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument();
        $ok  = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_NOCDATA);

        $errori = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);

        if (!$ok) {
            $primo = $errori === [] ? 'XML non valido' : trim($errori[0]->message);
            throw new TracciatoEccezione('Il file non è un XML leggibile: ' . $primo);
        }

        return $doc;
    }

    // ========================================================================
    //  KML
    // ========================================================================

    /**
     * Geometrie di un documento KML.
     *
     * Si scorrono i Placemark, che sono l'unita di contenuto del formato. Le
     * MultiGeometry vengono appiattite: una feature per geometria, cosi ognuna
     * conserva il nome del segnaposto che la contiene.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function daKml(DOMDocument $doc): array
    {
        $elementi = [];
        $punti    = 0;

        // Il KML dichiara un namespace, ma moltissimi file in circolazione lo
        // omettono o ne usano uno diverso: si cercano i nodi per nome locale.
        foreach (self::perNomeLocale($doc, 'Placemark') as $segnaposto) {
            $nome        = self::testoFiglio($segnaposto, 'name');
            $descrizione = self::testoFiglio($segnaposto, 'description');

            foreach (self::geometrieKml($segnaposto, $punti) as $geometria) {
                $elementi[] = [
                    'type'       => 'Feature',
                    'geometry'   => $geometria,
                    'properties' => array_filter([
                        'nome'        => $nome,
                        'descrizione' => $descrizione,
                    ], static fn ($v): bool => $v !== ''),
                ];
            }

            if ($punti >= self::LIMITE_PUNTI) {
                break;
            }
        }

        return $elementi;
    }

    /**
     * Geometrie contenute in un Placemark.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function geometrieKml(DOMNode $segnaposto, int &$punti): array
    {
        $geometrie = [];

        foreach (self::perNomeLocale($segnaposto, 'Point') as $nodo) {
            $c = self::coordinateKml($nodo, $punti);
            if (count($c) >= 1) {
                $geometrie[] = ['type' => 'Point', 'coordinates' => $c[0]];
            }
        }

        foreach (self::perNomeLocale($segnaposto, 'LineString') as $nodo) {
            $c = self::coordinateKml($nodo, $punti);
            // Una linea con un punto solo non e una linea: la si scarta invece
            // di produrre un GeoJSON che i lettori rifiutano.
            if (count($c) >= 2) {
                $geometrie[] = ['type' => 'LineString', 'coordinates' => $c];
            }
        }

        foreach (self::perNomeLocale($segnaposto, 'LinearRing') as $nodo) {
            // Gli anelli dentro un Polygon vengono gia presi da quel ramo: qui
            // si prendono solo quelli isolati, che alcuni programmi producono.
            if (self::haAntenato($nodo, 'Polygon')) {
                continue;
            }
            $c = self::coordinateKml($nodo, $punti);
            if (count($c) >= 4) {
                $geometrie[] = ['type' => 'Polygon', 'coordinates' => [$c]];
            }
        }

        foreach (self::perNomeLocale($segnaposto, 'Polygon') as $poligono) {
            $anelli = [];
            foreach (self::perNomeLocale($poligono, 'LinearRing') as $anello) {
                $c = self::coordinateKml($anello, $punti);
                if (count($c) >= 4) {
                    $anelli[] = $c;
                }
            }
            if ($anelli !== []) {
                $geometrie[] = ['type' => 'Polygon', 'coordinates' => $anelli];
            }
        }

        foreach (self::perNomeLocale($segnaposto, 'Track') as $traccia) {
            // gx:Track: le coordinate stanno in elementi <gx:coord> separati,
            // uno per punto, con gli assi divisi da spazi invece che da virgole.
            $c = [];
            foreach (self::perNomeLocale($traccia, 'coord') as $coord) {
                $parti = preg_split('/\s+/', trim($coord->textContent)) ?: [];
                if (count($parti) >= 2) {
                    $punto = self::punto((float) $parti[0], (float) $parti[1],
                        isset($parti[2]) ? (float) $parti[2] : null);
                    if ($punto !== null) {
                        $c[] = $punto;
                        $punti++;
                    }
                }
            }
            if (count($c) >= 2) {
                $geometrie[] = ['type' => 'LineString', 'coordinates' => $c];
            }
        }

        return $geometrie;
    }

    /**
     * Coordinate di un elemento KML.
     *
     * Il formato e "lon,lat[,quota]" separato da spazi o a capo — nota bene:
     * longitudine PRIMA, come in GeoJSON, al contrario di come si scrivono di
     * solito le coordinate geografiche.
     *
     * @return array<int,array<int,float>>
     */
    private static function coordinateKml(DOMNode $nodo, int &$punti): array
    {
        $elenco = [];

        foreach (self::perNomeLocale($nodo, 'coordinates') as $blocco) {
            $testo = trim($blocco->textContent);
            if ($testo === '') {
                continue;
            }

            foreach (preg_split('/\s+/', $testo) ?: [] as $terna) {
                if ($terna === '') {
                    continue;
                }
                $parti = explode(',', $terna);
                if (count($parti) < 2) {
                    continue;
                }

                $punto = self::punto((float) $parti[0], (float) $parti[1],
                    isset($parti[2]) && $parti[2] !== '' ? (float) $parti[2] : null);

                if ($punto !== null) {
                    $elenco[] = $punto;
                    $punti++;
                    if ($punti >= self::LIMITE_PUNTI) {
                        return $elenco;
                    }
                }
            }
        }

        return $elenco;
    }

    // ========================================================================
    //  GPX
    // ========================================================================

    /**
     * Geometrie di un documento GPX: tracce, rotte e punti notevoli.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function daGpx(DOMDocument $doc): array
    {
        $elementi = [];
        $punti    = 0;

        foreach (self::perNomeLocale($doc, 'trk') as $traccia) {
            $nome = self::testoFiglio($traccia, 'name');

            // Un segmento per volta: le interruzioni del segnale spezzano la
            // traccia, e unirle disegnerebbe una linea retta dove il rilevatore
            // non e passato.
            foreach (self::perNomeLocale($traccia, 'trkseg') as $segmento) {
                $c = self::puntiGpx($segmento, 'trkpt', $punti);
                if (count($c) >= 2) {
                    $elementi[] = self::feature('LineString', $c, $nome);
                }
            }
        }

        foreach (self::perNomeLocale($doc, 'rte') as $rotta) {
            $c = self::puntiGpx($rotta, 'rtept', $punti);
            if (count($c) >= 2) {
                $elementi[] = self::feature('LineString', $c, self::testoFiglio($rotta, 'name'));
            }
        }

        foreach (self::perNomeLocale($doc, 'wpt') as $notevole) {
            $punto = self::daAttributiGpx($notevole);
            if ($punto !== null) {
                $punti++;
                $elementi[] = self::feature('Point', $punto, self::testoFiglio($notevole, 'name'));
            }
        }

        return $elementi;
    }

    /**
     * @return array<int,array<int,float>>
     */
    private static function puntiGpx(DOMNode $contenitore, string $nomePunto, int &$punti): array
    {
        $elenco = [];

        foreach (self::perNomeLocale($contenitore, $nomePunto) as $nodo) {
            $punto = self::daAttributiGpx($nodo);
            if ($punto === null) {
                continue;
            }
            $elenco[] = $punto;
            $punti++;
            if ($punti >= self::LIMITE_PUNTI) {
                break;
            }
        }

        return $elenco;
    }

    /**
     * Coordinate di un punto GPX, che stanno negli attributi lat e lon.
     *
     * @return array<int,float>|null
     */
    private static function daAttributiGpx(DOMNode $nodo): ?array
    {
        if (!$nodo instanceof DOMElement) {
            return null;
        }

        $lat = $nodo->getAttribute('lat');
        $lon = $nodo->getAttribute('lon');
        if ($lat === '' || $lon === '') {
            return null;
        }

        $quota = null;
        foreach (self::perNomeLocale($nodo, 'ele') as $ele) {
            $testo = trim($ele->textContent);
            if ($testo !== '' && is_numeric($testo)) {
                $quota = (float) $testo;
            }
            break;
        }

        return self::punto((float) $lon, (float) $lat, $quota);
    }

    // ========================================================================
    //  UTILITA
    // ========================================================================

    /**
     * Punto GeoJSON, con la quota se c'e.
     *
     * @return array<int,float>|null null se la posizione non e plausibile
     */
    private static function punto(float $lon, float $lat, ?float $quota): ?array
    {
        if (abs($lat) > 90.0 || abs($lon) > 180.0) {
            return null;
        }
        // Il punto nullo e quasi sempre un segnaposto non compilato.
        if (abs($lat) < 0.000001 && abs($lon) < 0.000001) {
            return null;
        }

        $punto = [round($lon, 7), round($lat, 7)];
        if ($quota !== null && abs($quota) < 20000) {
            $punto[] = round($quota, 2);
        }

        return $punto;
    }

    /**
     * @param  array<int,array<int,float>>|array<int,float> $coordinate
     * @return array<string,mixed>
     */
    private static function feature(string $tipo, array $coordinate, string $nome): array
    {
        return [
            'type'       => 'Feature',
            'geometry'   => ['type' => $tipo, 'coordinates' => $coordinate],
            'properties' => $nome === '' ? [] : ['nome' => $nome],
        ];
    }

    /**
     * Nodi discendenti con un dato nome locale, namespace ignorato.
     *
     * getElementsByTagName confronta il nome completo di prefisso: su file che
     * usano "gx:Track" o che omettono del tutto il namespace fallirebbe. Qui si
     * usa il nome locale, che e l'unica cosa su cui i file reali concordano.
     *
     * @return DOMNode[]
     */
    private static function perNomeLocale(DOMNode $contesto, string $nome): array
    {
        $doc = $contesto instanceof DOMDocument ? $contesto : $contesto->ownerDocument;
        if ($doc === null) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $nodi  = $xpath->query('.//*[local-name()="' . $nome . '"]', $contesto);

        return $nodi === false ? [] : iterator_to_array($nodi);
    }

    /** Testo del primo figlio con quel nome locale. */
    private static function testoFiglio(DOMNode $nodo, string $nome): string
    {
        foreach (self::perNomeLocale($nodo, $nome) as $figlio) {
            return Testo::estratto(trim($figlio->textContent), 200);
        }

        return '';
    }

    /** True se il nodo ha un antenato con quel nome locale. */
    private static function haAntenato(DOMNode $nodo, string $nome): bool
    {
        for ($p = $nodo->parentNode; $p !== null; $p = $p->parentNode) {
            if ($p->nodeType === XML_ELEMENT_NODE && $p->localName === $nome) {
                return true;
            }
        }

        return false;
    }

    /**
     * Riquadro che contiene tutte le geometrie: serve alla mappa per inquadrare.
     *
     * @param  array<string,mixed> $geojson
     * @return array{sud:float,ovest:float,nord:float,est:float}|null
     */
    public static function riquadro(array $geojson): ?array
    {
        $sud = 90.0; $nord = -90.0; $ovest = 180.0; $est = -180.0;
        $trovato = false;

        $scorri = static function ($coordinate) use (&$scorri, &$sud, &$nord, &$ovest, &$est, &$trovato): void {
            if ($coordinate === [] || !is_array($coordinate)) {
                return;
            }
            // Una posizione e un array di numeri; tutto il resto e annidamento.
            if (is_numeric($coordinate[0] ?? null)) {
                $lon = (float) $coordinate[0];
                $lat = (float) ($coordinate[1] ?? 0);
                $sud = min($sud, $lat);   $nord = max($nord, $lat);
                $ovest = min($ovest, $lon); $est = max($est, $lon);
                $trovato = true;
                return;
            }
            foreach ($coordinate as $parte) {
                $scorri($parte);
            }
        };

        foreach ($geojson['features'] ?? [] as $elemento) {
            $scorri($elemento['geometry']['coordinates'] ?? []);
        }

        return $trovato
            ? ['sud' => $sud, 'ovest' => $ovest, 'nord' => $nord, 'est' => $est]
            : null;
    }

    /**
     * Conteggio delle geometrie per tipo, per dire cosa contiene un rilievo.
     *
     * @param  array<string,mixed> $geojson
     * @return array<string,int>
     */
    public static function riepilogo(array $geojson): array
    {
        $conteggi = [];
        foreach ($geojson['features'] ?? [] as $elemento) {
            $tipo = (string) ($elemento['geometry']['type'] ?? 'sconosciuto');
            $conteggi[$tipo] = ($conteggi[$tipo] ?? 0) + 1;
        }

        return $conteggi;
    }
}
