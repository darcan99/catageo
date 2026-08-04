<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Coordinate.php
 *  Descrizione ..: Sistemi di riferimento e formati delle coordinate.
 *
 *                  SCELTA DI FONDO: in archivio le coordinate sono SEMPRE
 *                  conservate in gradi decimali WGS84 (EPSG:4326), perche e la
 *                  forma che serve alla mappa, alla ricerca per raggio e
 *                  all'esportazione in KML, e perche avere una sola forma
 *                  canonica evita che due schede diventino inconfrontabili.
 *
 *                  Accanto alla forma canonica si conserva pero anche il dato
 *                  COME E STATO RILEVATO: sistema, formato e valore originale.
 *                  Un catasto storico che ha misurato in UTM ha misurato in UTM,
 *                  e riscrivere solo la conversione perderebbe l'informazione su
 *                  cosa fu effettivamente letto sullo strumento.
 *
 *                  Le conversioni fra UTM e geografiche sono esatte: entrambe
 *                  sul medesimo ellissoide WGS84, quindi si tratta di una
 *                  proiezione e non di un cambio di datum. Per i sistemi con
 *                  datum diverso (Gauss-Boaga/Roma40, UTM ED50) vedi la nota
 *                  in fondo: sono ammessi come dato originale ma NON convertiti
 *                  automaticamente.
 *  Versione .....: 0.5.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.5.0  2026-08-04  D.Candela  Prima stesura (sistemi di riferimento).
 * ============================================================================
 */

class CoordinateEccezione extends RuntimeException {}

final class Coordinate
{
    // ------------------------------------------------------------- ellissoide WGS84

    /** Semiasse maggiore dell'ellissoide WGS84, in metri. */
    private const A = 6378137.0;

    /** Primo eccentricita al quadrato dell'ellissoide WGS84. */
    private const E2 = 0.00669437999014;

    /** Fattore di scala sul meridiano centrale, comune a tutte le zone UTM. */
    private const K0 = 0.9996;

    /** Falso est, in metri. */
    private const FALSO_EST = 500000.0;

    /** Falso nord per l'emisfero sud, in metri. */
    private const FALSO_NORD_SUD = 10000000.0;

    /** Lettere delle fasce di latitudine UTM, dalla piu meridionale. */
    private const FASCE = 'CDEFGHJKLMNPQRSTUVWX';

    // ----------------------------------------------------------------- vocabolari

    /**
     * Formati di inserimento gestiti dall'applicativo.
     *
     * Sono i modi in cui una coordinata puo essere DIGITATA; il sistema di
     * riferimento e cosa diverso e si sceglie a parte.
     */
    public const FORMATI = [
        'decimali' => 'Gradi decimali (41.856231)',
        'gms'      => 'Gradi, minuti, secondi (41°51\'22.4"N)',
        'gm'       => 'Gradi e minuti decimali (41°51.373\'N)',
        'utm'      => 'UTM (fuso, est, nord)',
    ];

    /**
     * Sistemi di riferimento selezionabili.
     *
     * convertibile = l'applicativo sa ricavarne i gradi decimali WGS84 senza
     * approssimazioni; per gli altri il valore viene conservato come dichiarato
     * e le coordinate canoniche vanno inserite a parte.
     */
    public const SISTEMI = [
        'EPSG:4326' => ['nome' => 'WGS84 geografiche', 'convertibile' => true,
                        'nota' => 'Gradi di latitudine e longitudine sull\'ellissoide WGS84. E la forma canonica dell\'archivio.'],
        'EPSG:32632' => ['nome' => 'UTM WGS84 fuso 32N', 'convertibile' => true,
                         'nota' => 'Italia occidentale e centrale, fino a circa 12° est.'],
        'EPSG:32633' => ['nome' => 'UTM WGS84 fuso 33N', 'convertibile' => true,
                         'nota' => 'Italia centrale e meridionale, oltre i 12° est.'],
        'EPSG:32634' => ['nome' => 'UTM WGS84 fuso 34N', 'convertibile' => true,
                         'nota' => 'Estremo sud-est, Salento e Grecia occidentale.'],
        'EPSG:25832' => ['nome' => 'UTM ETRS89 fuso 32N', 'convertibile' => true,
                         'nota' => 'ETRS89 coincide con WGS84 entro pochi centimetri: convertito come tale.'],
        'EPSG:25833' => ['nome' => 'UTM ETRS89 fuso 33N', 'convertibile' => true,
                         'nota' => 'ETRS89 coincide con WGS84 entro pochi centimetri: convertito come tale.'],
        'EPSG:3003'  => ['nome' => 'Gauss-Boaga fuso Ovest (Roma40)', 'convertibile' => false,
                         'nota' => 'Datum Roma40, diverso da WGS84: la conversione richiede una trasformazione di datum e non viene fatta automaticamente.'],
        'EPSG:3004'  => ['nome' => 'Gauss-Boaga fuso Est (Roma40)', 'convertibile' => false,
                         'nota' => 'Datum Roma40, diverso da WGS84: la conversione richiede una trasformazione di datum e non viene fatta automaticamente.'],
        'EPSG:23032' => ['nome' => 'UTM ED50 fuso 32N', 'convertibile' => false,
                         'nota' => 'Datum ED50: scostamento di circa 50-100 m rispetto a WGS84, non convertibile senza parametri locali.'],
        'EPSG:23033' => ['nome' => 'UTM ED50 fuso 33N', 'convertibile' => false,
                         'nota' => 'Datum ED50: scostamento di circa 50-100 m rispetto a WGS84, non convertibile senza parametri locali.'],
        'altro'      => ['nome' => 'Altro / non dichiarato', 'convertibile' => false,
                         'nota' => 'Il valore viene conservato come annotazione.'],
    ];

    /** Sistema canonico dell'archivio. */
    public const CANONICO = 'EPSG:4326';

    /**
     * Messaggio unico per le coordinate mancanti.
     *
     * Sta in una costante perche lo stesso caso si presenta da tre rami diversi
     * (decimali, sessagesimali, UTM non convertibile) e dire la stessa cosa in
     * tre modi diversi confonderebbe chi compila. Spiega anche il perche: un
     * messaggio che dice solo "campo obbligatorio" non aiuta a capire cosa si
     * perde lasciandolo vuoto.
     */
    public const MESSAGGIO_COORDINATE_MANCANTI =
        'Latitudine e longitudine sono obbligatorie: senza posizione l\'ipogeo non comparirebbe '
        . 'ne in mappa ne nelle ricerche per area.';

    // --------------------------------------------------------------- informazioni

    /** True se dal sistema indicato si sanno ricavare i gradi decimali WGS84. */
    public static function convertibile(string $sistema): bool
    {
        return (bool) (self::SISTEMI[$sistema]['convertibile'] ?? false);
    }

    /** Nome leggibile di un sistema. */
    public static function nomeSistema(string $sistema): string
    {
        return (string) (self::SISTEMI[$sistema]['nome'] ?? $sistema);
    }

    /** Nota esplicativa di un sistema. */
    public static function notaSistema(string $sistema): string
    {
        return (string) (self::SISTEMI[$sistema]['nota'] ?? '');
    }

    /**
     * Fuso UTM dichiarato da un codice EPSG, oppure 0 se il codice non e UTM.
     */
    public static function fusoDaSistema(string $sistema): int
    {
        foreach ([32600, 25800, 23000] as $base) {
            if (preg_match('/^EPSG:(\d+)$/', $sistema, $p)) {
                $numero = (int) $p[1];
                if ($numero > $base && $numero < $base + 61) {
                    return $numero - $base;
                }
            }
        }
        return 0;
    }

    /** True se il sistema e una proiezione UTM. */
    public static function eUtm(string $sistema): bool
    {
        return self::fusoDaSistema($sistema) > 0;
    }

    // ----------------------------------------------------------------- geografiche

    /**
     * Fuso UTM che contiene una longitudine.
     */
    public static function fusoPerLongitudine(float $longitudine): int
    {
        $fuso = (int) floor(($longitudine + 180.0) / 6.0) + 1;
        return max(1, min(60, $fuso));
    }

    /** Lettera della fascia UTM per una latitudine. */
    public static function fasciaPerLatitudine(float $latitudine): string
    {
        if ($latitudine < -80.0 || $latitudine > 84.0) {
            return '';   // fuori dalla copertura UTM
        }
        // La fascia X e piu alta delle altre: copre 12 gradi invece di 8.
        if ($latitudine >= 72.0) {
            return 'X';
        }
        $indice = (int) floor(($latitudine + 80.0) / 8.0);
        $indice = max(0, min(strlen(self::FASCE) - 1, $indice));

        return self::FASCE[$indice];
    }

    /** Codice EPSG della proiezione UTM WGS84 per un fuso, emisfero nord. */
    public static function epsgUtmNord(int $fuso): string
    {
        return 'EPSG:' . (32600 + $fuso);
    }

    // ------------------------------------------------------- geografiche -> UTM

    /**
     * Converte gradi decimali WGS84 in coordinate UTM.
     *
     * Formule di Snyder per la proiezione trasversa di Mercatore, sviluppate
     * fino al sesto ordine: entro un fuso l'errore e millimetrico, quindi non
     * introduce incertezza rispetto al dato di campagna.
     *
     * @param  int|null $fusoForzato per restare nel fuso di un catasto anche
     *                               quando il punto sta appena oltre il confine
     * @return array{fuso:int,fascia:string,est:float,nord:float,emisfero:string,epsg:string}
     * @throws CoordinateEccezione
     */
    public static function aUtm(float $latitudine, float $longitudine, ?int $fusoForzato = null): array
    {
        self::esigiGradiValidi($latitudine, $longitudine);

        $fuso = $fusoForzato ?? self::fusoPerLongitudine($longitudine);
        if ($fuso < 1 || $fuso > 60) {
            throw new CoordinateEccezione('Fuso UTM non valido: ' . $fuso);
        }

        $meridianoCentrale = ($fuso - 1) * 6 - 180 + 3;

        $phi    = deg2rad($latitudine);
        $lambda = deg2rad($longitudine);
        $lambda0 = deg2rad((float) $meridianoCentrale);

        $e2  = self::E2;
        $ep2 = $e2 / (1.0 - $e2);

        $sinPhi = sin($phi);
        $cosPhi = cos($phi);
        $tanPhi = tan($phi);

        $n = self::A / sqrt(1.0 - $e2 * $sinPhi * $sinPhi);
        $t = $tanPhi * $tanPhi;
        $c = $ep2 * $cosPhi * $cosPhi;
        $a = $cosPhi * ($lambda - $lambda0);

        $m = self::arcoMeridiano($phi);

        $est = self::FALSO_EST + self::K0 * $n * (
            $a
            + (1.0 - $t + $c) * pow($a, 3) / 6.0
            + (5.0 - 18.0 * $t + $t * $t + 72.0 * $c - 58.0 * $ep2) * pow($a, 5) / 120.0
        );

        $nord = self::K0 * (
            $m + $n * $tanPhi * (
                $a * $a / 2.0
                + (5.0 - $t + 9.0 * $c + 4.0 * $c * $c) * pow($a, 4) / 24.0
                + (61.0 - 58.0 * $t + $t * $t + 600.0 * $c - 330.0 * $ep2) * pow($a, 6) / 720.0
            )
        );

        $emisfero = $latitudine >= 0.0 ? 'N' : 'S';
        if ($emisfero === 'S') {
            $nord += self::FALSO_NORD_SUD;
        }

        return [
            'fuso'     => $fuso,
            'fascia'   => self::fasciaPerLatitudine($latitudine),
            'est'      => round($est, 2),
            'nord'     => round($nord, 2),
            'emisfero' => $emisfero,
            'epsg'     => 'EPSG:' . (($emisfero === 'N' ? 32600 : 32700) + $fuso),
        ];
    }

    // ------------------------------------------------------- UTM -> geografiche

    /**
     * Converte coordinate UTM in gradi decimali WGS84.
     *
     * @return array{latitudine:float,longitudine:float}
     * @throws CoordinateEccezione
     */
    public static function daUtm(int $fuso, float $est, float $nord, string $emisfero = 'N'): array
    {
        if ($fuso < 1 || $fuso > 60) {
            throw new CoordinateEccezione('Il fuso UTM deve stare fra 1 e 60.');
        }
        $emisfero = strtoupper(trim($emisfero)) === 'S' ? 'S' : 'N';

        // Limiti larghi, solo per intercettare valori evidentemente sbagliati:
        // un est di 5 cifre o un nord negativo sono errori di digitazione.
        if ($est < 100000.0 || $est > 900000.0) {
            throw new CoordinateEccezione('Coordinata est fuori intervallo: attesi valori fra 100.000 e 900.000 metri.');
        }
        if ($nord < 0.0 || $nord > self::FALSO_NORD_SUD) {
            throw new CoordinateEccezione('Coordinata nord fuori intervallo: attesi valori fra 0 e 10.000.000 metri.');
        }

        $e2  = self::E2;
        $ep2 = $e2 / (1.0 - $e2);

        $x = $est - self::FALSO_EST;
        $y = $emisfero === 'S' ? $nord - self::FALSO_NORD_SUD : $nord;

        $m  = $y / self::K0;
        $mu = $m / (self::A * (1.0 - $e2 / 4.0 - 3.0 * $e2 * $e2 / 64.0 - 5.0 * pow($e2, 3) / 256.0));

        $e1 = (1.0 - sqrt(1.0 - $e2)) / (1.0 + sqrt(1.0 - $e2));

        $phi1 = $mu
            + (3.0 * $e1 / 2.0 - 27.0 * pow($e1, 3) / 32.0) * sin(2.0 * $mu)
            + (21.0 * $e1 * $e1 / 16.0 - 55.0 * pow($e1, 4) / 32.0) * sin(4.0 * $mu)
            + (151.0 * pow($e1, 3) / 96.0) * sin(6.0 * $mu)
            + (1097.0 * pow($e1, 4) / 512.0) * sin(8.0 * $mu);

        $sinPhi1 = sin($phi1);
        $cosPhi1 = cos($phi1);
        $tanPhi1 = tan($phi1);

        $c1 = $ep2 * $cosPhi1 * $cosPhi1;
        $t1 = $tanPhi1 * $tanPhi1;
        $n1 = self::A / sqrt(1.0 - $e2 * $sinPhi1 * $sinPhi1);
        $r1 = self::A * (1.0 - $e2) / pow(1.0 - $e2 * $sinPhi1 * $sinPhi1, 1.5);
        $d  = $x / ($n1 * self::K0);

        $phi = $phi1 - ($n1 * $tanPhi1 / $r1) * (
            $d * $d / 2.0
            - (5.0 + 3.0 * $t1 + 10.0 * $c1 - 4.0 * $c1 * $c1 - 9.0 * $ep2) * pow($d, 4) / 24.0
            + (61.0 + 90.0 * $t1 + 298.0 * $c1 + 45.0 * $t1 * $t1 - 252.0 * $ep2 - 3.0 * $c1 * $c1) * pow($d, 6) / 720.0
        );

        $lambda = (
            $d
            - (1.0 + 2.0 * $t1 + $c1) * pow($d, 3) / 6.0
            + (5.0 - 2.0 * $c1 + 28.0 * $t1 - 3.0 * $c1 * $c1 + 8.0 * $ep2 + 24.0 * $t1 * $t1) * pow($d, 5) / 120.0
        ) / $cosPhi1;

        $meridianoCentrale = ($fuso - 1) * 6 - 180 + 3;

        return [
            'latitudine'  => round(rad2deg($phi), 8),
            'longitudine' => round((float) $meridianoCentrale + rad2deg($lambda), 8),
        ];
    }

    // ------------------------------------------------------------ gradi sessagesimali

    /**
     * Interpreta una coordinata scritta in gradi sessagesimali o in gradi e
     * minuti decimali, restituendo i gradi decimali.
     *
     * Accetta le notazioni che si trovano davvero sui documenti e sugli
     * strumenti: 41°51'22.4"N, 41 51 22.4 N, N41°51.373', -41°51'22.4".
     *
     * @throws CoordinateEccezione
     */
    public static function daSessagesimali(string $valore): float
    {
        $testo = trim($valore);
        if ($testo === '') {
            throw new CoordinateEccezione('Coordinata sessagesimale vuota.');
        }

        // Segno: dal prefisso, dal suffisso cardinale o dal meno.
        $segno = 1.0;
        if (preg_match('/[SWsw]/', $testo)) {
            $segno = -1.0;
        }
        if (str_contains($testo, '-')) {
            $segno = -1.0;
        }

        // Si tengono solo i numeri: gradi, minuti, secondi nell'ordine.
        $normalizzato = str_replace(',', '.', $testo);
        if (!preg_match_all('/\d+(?:\.\d+)?/', $normalizzato, $numeri)) {
            throw new CoordinateEccezione('Coordinata sessagesimale non riconosciuta: ' . $valore);
        }

        $parti = array_map('floatval', $numeri[0]);
        if (count($parti) > 3) {
            throw new CoordinateEccezione('Coordinata sessagesimale con troppi valori: ' . $valore);
        }

        $gradi   = $parti[0];
        $minuti  = $parti[1] ?? 0.0;
        $secondi = $parti[2] ?? 0.0;

        if ($minuti >= 60.0 || $secondi >= 60.0) {
            throw new CoordinateEccezione('Minuti e secondi devono essere inferiori a 60: ' . $valore);
        }

        return $segno * ($gradi + $minuti / 60.0 + $secondi / 3600.0);
    }

    /**
     * Scrive gradi decimali in gradi, minuti e secondi.
     *
     * @param string $asse 'lat' oppure 'lon', per la lettera cardinale
     */
    public static function aSessagesimali(float $gradiDecimali, string $asse = 'lat', int $decimaliSecondi = 2): string
    {
        $cardinale = $asse === 'lon'
            ? ($gradiDecimali >= 0 ? 'E' : 'W')
            : ($gradiDecimali >= 0 ? 'N' : 'S');

        $assoluto = abs($gradiDecimali);
        $gradi    = (int) floor($assoluto);
        $restoMin = ($assoluto - $gradi) * 60.0;
        $minuti   = (int) floor($restoMin);
        $secondi  = ($restoMin - $minuti) * 60.0;

        // L'arrotondamento dei secondi puo produrre 60: si riporta sui minuti,
        // altrimenti si scriverebbero coordinate come 41°51'60.00".
        $secondiTondi = round($secondi, $decimaliSecondi);
        if ($secondiTondi >= 60.0) {
            $secondiTondi = 0.0;
            $minuti++;
        }
        if ($minuti >= 60) {
            $minuti = 0;
            $gradi++;
        }

        return sprintf('%d°%02d\'%0' . ($decimaliSecondi > 0 ? $decimaliSecondi + 3 : 2) . '.' . $decimaliSecondi . 'f"%s',
            $gradi, $minuti, $secondiTondi, $cardinale);
    }

    /** Scrive gradi decimali in gradi e minuti decimali. */
    public static function aGradiMinuti(float $gradiDecimali, string $asse = 'lat', int $decimaliMinuti = 3): string
    {
        $cardinale = $asse === 'lon'
            ? ($gradiDecimali >= 0 ? 'E' : 'W')
            : ($gradiDecimali >= 0 ? 'N' : 'S');

        $assoluto = abs($gradiDecimali);
        $gradi    = (int) floor($assoluto);
        $minuti   = round(($assoluto - $gradi) * 60.0, $decimaliMinuti);

        if ($minuti >= 60.0) {
            $minuti = 0.0;
            $gradi++;
        }

        return sprintf('%d°%0' . ($decimaliMinuti + 3) . '.' . $decimaliMinuti . 'f\'%s', $gradi, $minuti, $cardinale);
    }

    /** Notazione UTM compatta, quella che si scrive su un rilievo. */
    public static function utmLeggibile(array $utm): string
    {
        return sprintf('%d%s %s %s',
            (int) $utm['fuso'],
            (string) $utm['fascia'],
            number_format((float) $utm['est'], 0, ',', '.'),
            number_format((float) $utm['nord'], 0, ',', '.')
        );
    }

    // -------------------------------------------------------------- interpretazione

    /**
     * Interpreta i dati di inserimento e restituisce la forma canonica piu la
     * memoria di come il dato era stato dichiarato.
     *
     * @param  array<string,mixed> $dati formato, sistema, latitudine, longitudine,
     *                                   fuso, est, nord, emisfero
     * @return array{
     *   latitudine:string, longitudine:string,
     *   sistemaOriginale:string, formatoOriginale:string, valoreOriginale:string,
     *   avvisi:array<int,string>
     * }
     * @throws CoordinateEccezione
     */
    public static function interpreta(array $dati): array
    {
        $formato = (string) ($dati['formato'] ?? 'decimali');
        $sistema = (string) ($dati['sistema'] ?? self::CANONICO);
        $avvisi  = [];

        if (!isset(self::FORMATI[$formato])) {
            throw new CoordinateEccezione('Formato di inserimento non riconosciuto.');
        }
        if (!isset(self::SISTEMI[$sistema])) {
            throw new CoordinateEccezione('Sistema di riferimento non riconosciuto.');
        }

        // -------------------------------------------------------------- UTM
        if ($formato === 'utm') {
            $fusoDichiarato = (int) ($dati['fuso'] ?? 0);
            $est            = self::numero($dati['est'] ?? '');
            $nord           = self::numero($dati['nord'] ?? '');
            $emisfero       = (string) ($dati['emisfero'] ?? 'N');

            if ($est === null || $nord === null) {
                throw new CoordinateEccezione('Per il formato UTM servono est e nord in metri.');
            }

            // Il fuso puo arrivare dal campo o dal codice EPSG del sistema.
            $fuso = $fusoDichiarato > 0 ? $fusoDichiarato : self::fusoDaSistema($sistema);
            if ($fuso <= 0) {
                throw new CoordinateEccezione('Indicare il fuso UTM, oppure scegliere un sistema che lo dichiari.');
            }

            // Contraddizione fra il fuso scritto nel campo e quello dichiarato
            // dal codice EPSG del sistema: uno dei due e sbagliato, e la
            // differenza sul terreno sarebbe di centinaia di chilometri.
            //
            // NOTA: non ha senso ricalcolare il fuso dalle coordinate ottenute
            // per confrontarlo con quello dichiarato. Una coppia est/nord e
            // valida in OGNI fuso, e indica semplicemente un luogo diverso:
            // il ricalcolo restituirebbe sempre il fuso dichiarato e il
            // controllo non scatterebbe mai. L'unico confronto che porta
            // informazione e questo, fra due dichiarazioni indipendenti.
            $fusoDelSistema = self::fusoDaSistema($sistema);
            if ($fusoDichiarato > 0 && $fusoDelSistema > 0 && $fusoDichiarato !== $fusoDelSistema) {
                throw new CoordinateEccezione(sprintf(
                    'Il fuso indicato (%d) contraddice il sistema scelto, che dichiara il fuso %d. '
                    . 'Correggere uno dei due: la differenza sul terreno sarebbe di centinaia di chilometri.',
                    $fusoDichiarato, $fusoDelSistema
                ));
            }

            // Est e nord scambiati e l'errore di digitazione piu frequente.
            // Nell'emisfero nord, sopra i mille chilometri dall'equatore, il
            // nord supera sempre il massimo ammesso per l'est: se il valore
            // messo come est ha la magnitudine di un nord, i due sono invertiti.
            if ($est > 1000000.0 || ($nord < 900000.0 && $est > $nord && $nord > 100000.0)) {
                $avvisi[] = 'Est e nord sembrano invertiti: verificare quale dei due valori e la coordinata est.';
            }

            $valoreOriginale = sprintf('%d %s %s %s', $fuso,
                number_format($est, 2, '.', ''), number_format($nord, 2, '.', ''), strtoupper($emisfero));

            if (!self::convertibile($sistema)) {
                // Datum diverso da WGS84: si conserva il dato ma non si finge
                // di poterlo convertire. Le coordinate canoniche vanno inserite
                // a parte, altrimenti si scriverebbe una posizione sbagliata di
                // decine di metri spacciandola per esatta.
                $lat = self::numero($dati['latitudine'] ?? '');
                $lon = self::numero($dati['longitudine'] ?? '');
                if ($lat === null || $lon === null) {
                    throw new CoordinateEccezione(
                        'Il sistema "' . self::nomeSistema($sistema) . '" ha un datum diverso da WGS84 e non viene '
                        . 'convertito automaticamente: indicare anche latitudine e longitudine WGS84 in gradi decimali.'
                    );
                }
                $avvisi[] = 'Il valore ' . self::nomeSistema($sistema) . ' e conservato come dichiarato; '
                          . 'le coordinate WGS84 sono quelle inserite a mano.';

                return self::esito($lat, $lon, $sistema, $formato, $valoreOriginale, $avvisi);
            }

            $geo = self::daUtm($fuso, $est, $nord, $emisfero);

            return self::esito($geo['latitudine'], $geo['longitudine'], $sistema, $formato, $valoreOriginale, $avvisi);
        }

        // ------------------------------------------------- gradi sessagesimali
        if ($formato === 'gms' || $formato === 'gm') {
            $latTesto = trim((string) ($dati['latitudine'] ?? ''));
            $lonTesto = trim((string) ($dati['longitudine'] ?? ''));

            if ($latTesto === '' || $lonTesto === '') {
                throw new CoordinateEccezione(self::MESSAGGIO_COORDINATE_MANCANTI);
            }

            $lat = self::daSessagesimali($latTesto);
            $lon = self::daSessagesimali($lonTesto);

            // Il segno per la longitudine non si deduce da S/W se manca: si
            // assume est, che copre l'Italia e la quasi totalita dei casi.
            $valoreOriginale = $latTesto . ' ' . $lonTesto;

            return self::esito($lat, $lon, $sistema, $formato, $valoreOriginale, $avvisi);
        }

        // ----------------------------------------------------- gradi decimali
        $lat = self::numero($dati['latitudine'] ?? '');
        $lon = self::numero($dati['longitudine'] ?? '');

        if ($lat === null || $lon === null) {
            throw new CoordinateEccezione(self::MESSAGGIO_COORDINATE_MANCANTI);
        }

        // In gradi decimali il sistema e per definizione quello canonico: un
        // codice UTM con valori in gradi sarebbe una contraddizione.
        if (self::eUtm($sistema)) {
            $avvisi[] = 'Formato in gradi decimali con un sistema UTM: il sistema e stato riportato a WGS84 geografiche.';
            $sistema  = self::CANONICO;
        }

        return self::esito($lat, $lon, $sistema, $formato, '', $avvisi);
    }

    /**
     * Rappresentazioni alternative di un punto, per la scheda: la stessa
     * posizione scritta nei modi in cui viene usata in campagna.
     *
     * @return array<string,string>
     */
    public static function rappresentazioni(float $latitudine, float $longitudine): array
    {
        $utm = self::aUtm($latitudine, $longitudine);

        return [
            'decimali' => number_format($latitudine, 6, '.', '') . ', ' . number_format($longitudine, 6, '.', ''),
            'gms'      => self::aSessagesimali($latitudine, 'lat') . ' ' . self::aSessagesimali($longitudine, 'lon'),
            'gm'       => self::aGradiMinuti($latitudine, 'lat') . ' ' . self::aGradiMinuti($longitudine, 'lon'),
            'utm'      => self::utmLeggibile($utm),
            'utmEpsg'  => (string) $utm['epsg'],
        ];
    }

    // -------------------------------------------------------------------- interni

    /**
     * Arco di meridiano dall'equatore alla latitudine data, in metri.
     */
    private static function arcoMeridiano(float $phi): float
    {
        $e2 = self::E2;

        return self::A * (
            (1.0 - $e2 / 4.0 - 3.0 * $e2 * $e2 / 64.0 - 5.0 * pow($e2, 3) / 256.0) * $phi
            - (3.0 * $e2 / 8.0 + 3.0 * $e2 * $e2 / 32.0 + 45.0 * pow($e2, 3) / 1024.0) * sin(2.0 * $phi)
            + (15.0 * $e2 * $e2 / 256.0 + 45.0 * pow($e2, 3) / 1024.0) * sin(4.0 * $phi)
            - (35.0 * pow($e2, 3) / 3072.0) * sin(6.0 * $phi)
        );
    }

    /**
     * @param  string[] $avvisi
     * @return array<string,mixed>
     */
    private static function esito(float $lat, float $lon, string $sistema, string $formato, string $valoreOriginale, array $avvisi): array
    {
        self::esigiGradiValidi($lat, $lon);

        return [
            'latitudine'       => number_format($lat, 6, '.', ''),
            'longitudine'      => number_format($lon, 6, '.', ''),
            'sistemaOriginale' => $sistema,
            'formatoOriginale' => $formato,
            'valoreOriginale'  => $valoreOriginale,
            'avvisi'           => $avvisi,
        ];
    }

    /**
     * @throws CoordinateEccezione
     */
    private static function esigiGradiValidi(float $latitudine, float $longitudine): void
    {
        if ($latitudine < -90.0 || $latitudine > 90.0) {
            throw new CoordinateEccezione('Latitudine fuori intervallo: deve stare fra -90 e 90.');
        }
        if ($longitudine < -180.0 || $longitudine > 180.0) {
            throw new CoordinateEccezione('Longitudine fuori intervallo: deve stare fra -180 e 180.');
        }
    }

    /** Converte in float accettando la virgola decimale; null se non numerico. */
    private static function numero(mixed $valore): ?float
    {
        $testo = str_replace([' ', "\u{00A0}"], '', trim((string) $valore));
        if ($testo === '') {
            return null;
        }
        $testo = str_replace(',', '.', $testo);

        return is_numeric($testo) ? (float) $testo : null;
    }
}
