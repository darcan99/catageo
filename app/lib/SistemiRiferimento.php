<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/SistemiRiferimento.php
 *  Descrizione ..: Vocabolario dei sistemi di riferimento delle coordinate.
 *
 *                  Ogni sistema e descritto da UNA stringa di definizione in
 *                  stile proj4, la stessa che viene passata a proj4js nel
 *                  browser e che alimenta il motore di conversione in PHP.
 *                  Una sola fonte per entrambi: le due implementazioni non
 *                  possono usare parametri diversi senza che si veda.
 *
 *                  Il vocabolario e in dati/sistemi_coordinate.xml, precaricato
 *                  con i sistemi usati in Italia e ampliabile da ADM con
 *                  qualunque codice EPSG: basta incollare la definizione presa
 *                  da epsg.io, senza toccare il codice.
 *  Versione .....: 0.5.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.5.0  2026-08-05  D.Candela  Prima stesura.
 * ============================================================================
 */

final class SistemiRiferimento
{
    /** Codice del sistema canonico dell'archivio. */
    public const CANONICO = 'EPSG:4326';

    /**
     * Sistemi precaricati all'installazione.
     *
     * Le definizioni sono quelle pubblicate su epsg.io, riportate senza
     * modifiche: i parametri di Helmert dei datum storici stanno dentro la
     * definizione stessa e non vanno indovinati.
     *
     * accuratezza = incertezza tipica della conversione verso WGS84, in metri.
     * Zero significa conversione esatta, cioe solo cambio di proiezione sullo
     * stesso ellissoide.
     */
    public const PREDEFINITI = [
        'EPSG:4326' => [
            'nome'        => 'WGS84 geografiche',
            'def'         => '+proj=longlat +datum=WGS84 +no_defs',
            'accuratezza' => 0.0,
            'unita'       => 'gradi',
            'nota'        => 'Il sistema del GPS e di Google Maps. E la forma canonica dell\'archivio.',
        ],
        'EPSG:32632' => [
            'nome'        => 'UTM WGS84 fuso 32N',
            'def'         => '+proj=utm +zone=32 +datum=WGS84 +units=m +no_defs',
            'accuratezza' => 0.0,
            'unita'       => 'metri',
            'nota'        => 'Italia a ovest dei 12 gradi est.',
        ],
        'EPSG:32633' => [
            'nome'        => 'UTM WGS84 fuso 33N',
            'def'         => '+proj=utm +zone=33 +datum=WGS84 +units=m +no_defs',
            'accuratezza' => 0.0,
            'unita'       => 'metri',
            'nota'        => 'Italia centrale e meridionale, a est dei 12 gradi. E il riferimento tipico della speleologia laziale.',
        ],
        'EPSG:32634' => [
            'nome'        => 'UTM WGS84 fuso 34N',
            'def'         => '+proj=utm +zone=34 +datum=WGS84 +units=m +no_defs',
            'accuratezza' => 0.0,
            'unita'       => 'metri',
            'nota'        => 'Salento e estremo sud-est.',
        ],
        'EPSG:25832' => [
            'nome'        => 'UTM ETRS89 fuso 32N',
            'def'         => '+proj=utm +zone=32 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs',
            'accuratezza' => 0.1,
            'unita'       => 'metri',
            'nota'        => 'Sistema di riferimento europeo. Coincide con WGS84 entro pochi centimetri.',
        ],
        'EPSG:25833' => [
            'nome'        => 'UTM ETRS89 fuso 33N',
            'def'         => '+proj=utm +zone=33 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs',
            'accuratezza' => 0.1,
            'unita'       => 'metri',
            'nota'        => 'Sistema di riferimento europeo. Coincide con WGS84 entro pochi centimetri.',
        ],
        'EPSG:3003' => [
            'nome'        => 'Gauss-Boaga fuso Ovest (Roma40)',
            'def'         => '+proj=tmerc +lat_0=0 +lon_0=9 +k=0.9996 +x_0=1500000 +y_0=0 +ellps=intl '
                           . '+towgs84=-104.1,-49.1,-9.9,0.971,-2.917,0.714,-11.68 +units=m +no_defs',
            'accuratezza' => 3.0,
            'unita'       => 'metri',
            'nota'        => 'Datum Roma40 su ellissoide Hayford. Conversione con sette parametri di Helmert: '
                           . 'incertezza dell\'ordine di qualche metro, non decimetrica.',
        ],
        'EPSG:3004' => [
            'nome'        => 'Gauss-Boaga fuso Est (Roma40)',
            'def'         => '+proj=tmerc +lat_0=0 +lon_0=15 +k=0.9996 +x_0=2520000 +y_0=0 +ellps=intl '
                           . '+towgs84=-104.1,-49.1,-9.9,0.971,-2.917,0.714,-11.68 +units=m +no_defs',
            'accuratezza' => 3.0,
            'unita'       => 'metri',
            'nota'        => 'Datum Roma40 su ellissoide Hayford. La falsa origine est vale 2.520.000 metri, '
                           . 'quindi le coordinate del fuso Est cominciano per 2.',
        ],
        'EPSG:23032' => [
            'nome'        => 'UTM ED50 fuso 32N',
            'def'         => '+proj=utm +zone=32 +ellps=intl +towgs84=-87,-98,-121,0,0,0,0 +units=m +no_defs',
            'accuratezza' => 10.0,
            'unita'       => 'metri',
            'nota'        => 'Datum europeo del 1950. Conversione con tre parametri: incertezza di alcuni metri, '
                           . 'variabile da zona a zona.',
        ],
        'EPSG:23033' => [
            'nome'        => 'UTM ED50 fuso 33N',
            'def'         => '+proj=utm +zone=33 +ellps=intl +towgs84=-87,-98,-121,0,0,0,0 +units=m +no_defs',
            'accuratezza' => 10.0,
            'unita'       => 'metri',
            'nota'        => 'Datum europeo del 1950. Conversione con tre parametri: incertezza di alcuni metri, '
                           . 'variabile da zona a zona.',
        ],
    ];

    /** Cache dei sistemi caricati, per richiesta. */
    private static ?array $cache = null;

    // ------------------------------------------------------------------ archivio

    /** Percorso del vocabolario. */
    public static function percorso(): string
    {
        return Percorsi::dati('sistemi_coordinate.xml');
    }

    /** Crea il vocabolario col contenuto predefinito, se assente. */
    public static function assicuraFile(): void
    {
        $percorso = self::percorso();
        if (is_file($percorso)) {
            return;
        }

        Percorsi::assicuraCartella(dirname($percorso));

        $doc    = Xml::nuovo('sistemiCoordinate', ['versioneSchema' => '1.0']);
        $radice = $doc->documentElement;
        if ($radice === null) {
            return;
        }

        foreach (self::PREDEFINITI as $codice => $dati) {
            $nodo = Xml::aggiungi($radice, 'sistema', null, ['codice' => $codice]);
            Xml::imposta($nodo, 'nome', (string) $dati['nome']);
            Xml::imposta($nodo, 'definizione', (string) $dati['def']);
            Xml::imposta($nodo, 'unita', (string) $dati['unita']);
            Xml::imposta($nodo, 'accuratezza', (string) $dati['accuratezza']);
            Xml::imposta($nodo, 'nota', (string) $dati['nota'], true);
            Xml::imposta($nodo, 'attivo', '1');
        }

        Xml::salva($doc, $percorso);
    }

    /**
     * Elenco dei sistemi disponibili.
     *
     * @return array<string,array<string,mixed>> indicizzato per codice
     */
    public static function elenco(bool $soloAttivi = false): array
    {
        if (self::$cache === null) {
            $sistemi = [];

            // Tutto l'accesso all'archivio sta dentro un solo try: il
            // vocabolario deve funzionare anche quando l'archivio non c'e
            // ancora o non e scrivibile, perche serve gia alla diagnostica e
            // all'installazione, prima che esista una configurazione.
            try {
                self::assicuraFile();

                if (is_file(self::percorso())) {
                    $doc = Xml::carica(self::percorso());
                    foreach (Xml::elenco($doc, '/sistemiCoordinate/sistema') as $nodo) {
                        $codice = trim($nodo->getAttribute('codice'));
                        if ($codice === '') {
                            continue;
                        }
                        $sistemi[$codice] = [
                            'codice'      => $codice,
                            'nome'        => Xml::testo($nodo, 'nome', $codice),
                            'def'         => Xml::testo($nodo, 'definizione'),
                            'unita'       => Xml::testo($nodo, 'unita', 'metri'),
                            'accuratezza' => (float) Xml::testo($nodo, 'accuratezza', '0'),
                            'nota'        => Xml::testo($nodo, 'nota'),
                            'attivo'      => Xml::booleano($nodo, 'attivo', true),
                        ];
                    }
                }
            } catch (Throwable $e) {
                $sistemi = [];
                if (class_exists('Log', false) && Config::caricata()) {
                    Log::errore('Vocabolario dei sistemi non disponibile: ' . $e->getMessage(), 'avviso');
                }
            }

            // Ripiego sui predefiniti se il file manca o e vuoto.
            if ($sistemi === []) {
                foreach (self::PREDEFINITI as $codice => $dati) {
                    $sistemi[$codice] = [
                        'codice'      => $codice,
                        'nome'        => $dati['nome'],
                        'def'         => $dati['def'],
                        'unita'       => $dati['unita'],
                        'accuratezza' => $dati['accuratezza'],
                        'nota'        => $dati['nota'],
                        'attivo'      => true,
                    ];
                }
            }

            self::$cache = $sistemi;
        }

        if (!$soloAttivi) {
            return self::$cache;
        }

        return array_filter(self::$cache, static fn (array $s): bool => (bool) $s['attivo']);
    }

    /** Svuota la cache: da chiamare dopo una modifica al vocabolario. */
    public static function invalidaCache(): void
    {
        self::$cache = null;
    }

    /**
     * Dati di un sistema.
     *
     * @return array<string,mixed>|null
     */
    public static function trova(string $codice): ?array
    {
        return self::elenco()[trim($codice)] ?? null;
    }

    /** Nome leggibile di un sistema, con ripiego sul codice. */
    public static function nome(string $codice): string
    {
        return (string) (self::trova($codice)['nome'] ?? $codice);
    }

    /** Nota esplicativa di un sistema. */
    public static function nota(string $codice): string
    {
        return (string) (self::trova($codice)['nota'] ?? '');
    }

    /** Definizione in stile proj4, quella passata anche a proj4js. */
    public static function definizione(string $codice): string
    {
        return (string) (self::trova($codice)['def'] ?? '');
    }

    /** Incertezza tipica della conversione verso WGS84, in metri. */
    public static function accuratezza(string $codice): float
    {
        return (float) (self::trova($codice)['accuratezza'] ?? 0.0);
    }

    /** True se il sistema esprime coordinate in gradi anziche in metri. */
    public static function inGradi(string $codice): bool
    {
        $sistema = self::trova($codice);
        if ($sistema === null) {
            return false;
        }
        return ($sistema['unita'] ?? 'metri') === 'gradi'
            || str_contains((string) $sistema['def'], '+proj=longlat');
    }

    /**
     * True se la conversione verso WGS84 comporta un cambio di datum, quindi
     * un'incertezza da dichiarare.
     */
    public static function cambiaDatum(string $codice): bool
    {
        $params = self::parametri($codice);
        return $params !== null && Proiezione::datumDaTrasformare($params);
    }

    /**
     * Fuso UTM dichiarato dalla definizione, oppure 0.
     */
    public static function fuso(string $codice): int
    {
        $params = self::parametri($codice);
        if ($params === null) {
            return 0;
        }
        if (isset($params['zone'])) {
            return (int) $params['zone'];
        }
        // Un tmerc generico non ha fuso: Gauss-Boaga usa "Ovest" ed "Est".
        return 0;
    }

    /**
     * Definizioni da consegnare a proj4js, come mappa codice => stringa.
     * E il punto in cui le due implementazioni ricevono gli stessi parametri.
     *
     * @return array<string,string>
     */
    public static function definizioniPerBrowser(): array
    {
        $mappa = [];
        foreach (self::elenco(true) as $codice => $dati) {
            $def = (string) $dati['def'];
            if ($def !== '') {
                $mappa[$codice] = $def;
            }
        }
        return $mappa;
    }

    // -------------------------------------------------------------- definizione

    /**
     * Parametri estratti dalla definizione di un sistema.
     *
     * @return array<string,mixed>|null
     */
    public static function parametri(string $codice): ?array
    {
        $def = self::definizione($codice);
        return $def === '' ? null : self::analizzaDefinizione($def);
    }

    /**
     * Analizza una stringa di definizione in stile proj4.
     *
     * Si riconosce il sottoinsieme che serve al catasto: proiezioni longlat,
     * tmerc e utm, ellissoide, falsa origine, fattore di scala e parametri di
     * datum. Le chiavi non riconosciute vengono conservate cosi come sono, per
     * poter essere passate a proj4js anche quando PHP non le usa.
     *
     * @return array<string,mixed>
     */
    public static function analizzaDefinizione(string $definizione): array
    {
        $params = [];

        foreach (preg_split('/\s+/', trim($definizione)) ?: [] as $pezzo) {
            if ($pezzo === '' || $pezzo[0] !== '+') {
                continue;
            }
            $pezzo = substr($pezzo, 1);

            if (!str_contains($pezzo, '=')) {
                $params[$pezzo] = true;   // opzioni senza valore, es. no_defs
                continue;
            }

            [$chiave, $valore] = explode('=', $pezzo, 2);

            if ($chiave === 'towgs84') {
                $params['towgs84'] = array_map('floatval', explode(',', $valore));
                continue;
            }

            $params[$chiave] = is_numeric($valore) ? (float) $valore : $valore;
        }

        // UTM e una trasversa di Mercatore con parametri fissati: si espande
        // qui, cosi il motore di proiezione conosce un solo caso.
        if (($params['proj'] ?? '') === 'utm' && isset($params['zone'])) {
            $fuso = (int) $params['zone'];
            $params['proj']  = 'tmerc';
            $params['lat_0'] = 0.0;
            $params['lon_0'] = (float) (($fuso - 1) * 6 - 180 + 3);
            $params['k']     = 0.9996;
            $params['x_0']   = 500000.0;
            $params['y_0']   = isset($params['south']) ? 10000000.0 : 0.0;
            $params['zone']  = $fuso;
        }

        // +datum=WGS84 equivale all'ellissoide WGS84 senza spostamento.
        if (($params['datum'] ?? '') === 'WGS84' && !isset($params['ellps'])) {
            $params['ellps'] = 'WGS84';
        }

        return $params;
    }

    // -------------------------------------------------------------- conversioni

    /**
     * Converte da un sistema qualsiasi a gradi decimali WGS84.
     *
     * @return array{latitudine:float,longitudine:float}
     * @throws ProiezioneEccezione
     */
    public static function versoWgs84(string $codice, float $x, float $y): array
    {
        $params = self::parametri($codice);
        if ($params === null) {
            throw new ProiezioneEccezione('Sistema di riferimento sconosciuto: ' . $codice);
        }
        return Proiezione::versoWgs84($params, $x, $y);
    }

    /**
     * Converte da gradi decimali WGS84 a un sistema qualsiasi.
     *
     * @return array{x:float,y:float}
     * @throws ProiezioneEccezione
     */
    public static function daWgs84(string $codice, float $latitudine, float $longitudine): array
    {
        $params = self::parametri($codice);
        if ($params === null) {
            throw new ProiezioneEccezione('Sistema di riferimento sconosciuto: ' . $codice);
        }
        return Proiezione::daWgs84($params, $latitudine, $longitudine);
    }
}
