<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Proiezione.php
 *  Descrizione ..: Motore di conversione fra sistemi di riferimento: proiezione
 *                  trasversa di Mercatore su ellissoide qualsiasi e
 *                  trasformazione di datum a sette parametri di Helmert.
 *
 *                  Lavora sui parametri estratti da una definizione in stile
 *                  proj4 (+proj=tmerc +lon_0=9 +ellps=intl +towgs84=...), la
 *                  stessa stringa che viene passata a proj4js nel browser: una
 *                  sola definizione per sistema, usata da entrambe le parti,
 *                  cosi le due implementazioni non possono divergere.
 *
 *                  CONVENZIONE DI ROTAZIONE: si segue quella di PROJ, cioe
 *                  Position Vector, con rotazioni in secondi d'arco e fattore
 *                  di scala in parti per milione. Sbagliare il verso delle
 *                  rotazioni produce errori di decine di metri che sembrano
 *                  plausibili: per questo l'implementazione viene confrontata
 *                  punto per punto con proj4js, che segue la stessa convenzione.
 *  Versione .....: 0.12.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.0 2026-08-06  D.Candela  ProiezioneEccezione spostata in app/lib/ProiezioneEccezione.php:
 *                                l'autoload risolve una classe per file.
 *  0.5.0  2026-08-05  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Proiezione
{
    /**
     * Ellissoidi riconosciuti nelle definizioni, per nome breve.
     * a = semiasse maggiore in metri, rf = schiacciamento inverso.
     */
    public const ELLISSOIDI = [
        'WGS84'  => ['a' => 6378137.0,   'rf' => 298.257223563],
        'GRS80'  => ['a' => 6378137.0,   'rf' => 298.257222101],
        'intl'   => ['a' => 6378388.0,   'rf' => 297.0],            // International 1924, detto Hayford
        'clrk66' => ['a' => 6378206.4,   'rf' => 294.9786982],
        'bessel' => ['a' => 6377397.155, 'rf' => 299.1528128],
        'airy'   => ['a' => 6377563.396, 'rf' => 299.3249646],
    ];

    /** Semiasse maggiore del WGS84, riferimento di arrivo di ogni conversione. */
    private const WGS84_A = 6378137.0;

    /** Schiacciamento inverso del WGS84. */
    private const WGS84_RF = 298.257223563;

    /**
     * Scostamento massimo dal meridiano centrale, in gradi.
     *
     * La serie di Snyder al sesto ordine e millimetrica entro questo limite e
     * degrada rapidamente oltre: a quattordici gradi lo scarto rispetto a
     * proj4js arriva a due metri. Il limite non e restrittivo nell'uso reale,
     * perche un fuso UTM e largo tre gradi per lato e il fuso Ovest di
     * Gauss-Boaga, anche nella sua estensione, non supera i tre e mezzo.
     * Oltre questa soglia si rifiuta la conversione invece di restituire un
     * numero plausibile ma sbagliato: quasi sempre significa che il fuso
     * indicato non e quello giusto per il punto.
     */
    public const LIMITE_SCOSTAMENTO_GRADI = 4.0;

    // ------------------------------------------------------- interfaccia pubblica

    /**
     * Converte coordinate proiettate (o geografiche) di un sistema qualsiasi in
     * gradi decimali WGS84.
     *
     * @param  array<string,mixed> $params parametri della definizione
     * @param  float               $x      est in metri, oppure longitudine se longlat
     * @param  float               $y      nord in metri, oppure latitudine se longlat
     * @return array{latitudine:float,longitudine:float}
     * @throws ProiezioneEccezione
     */
    public static function versoWgs84(array $params, float $x, float $y): array
    {
        $ellissoide = self::ellissoide($params);

        // 1. Dalla proiezione alle geografiche, sul datum di partenza.
        if (($params['proj'] ?? '') === 'longlat') {
            $lon = $x;
            $lat = $y;
        } else {
            [$lon, $lat] = self::tmercInversa($params, $ellissoide, $x, $y);
            // Il controllo si fa dopo, perche partendo dalle proiettate lo
            // scostamento non e noto prima di aver convertito.
            self::esigiDentroIlFuso($params, $lon);
        }

        // 2. Dal datum di partenza a WGS84, se serve.
        if (self::datumDaTrasformare($params)) {
            [$lon, $lat] = self::trasformaDatum($params, $ellissoide, $lon, $lat, false);
        }

        return ['latitudine' => $lat, 'longitudine' => $lon];
    }

    /**
     * Converte gradi decimali WGS84 nelle coordinate di un sistema qualsiasi.
     *
     * @param  array<string,mixed> $params
     * @return array{x:float,y:float}
     * @throws ProiezioneEccezione
     */
    public static function daWgs84(array $params, float $latitudine, float $longitudine): array
    {
        $ellissoide = self::ellissoide($params);

        $lon = $longitudine;
        $lat = $latitudine;

        // 1. Da WGS84 al datum di destinazione, se serve.
        if (self::datumDaTrasformare($params)) {
            [$lon, $lat] = self::trasformaDatum($params, $ellissoide, $lon, $lat, true);
        }

        // 2. Dalle geografiche alla proiezione.
        if (($params['proj'] ?? '') === 'longlat') {
            return ['x' => $lon, 'y' => $lat];
        }

        self::esigiDentroIlFuso($params, $lon);

        [$x, $y] = self::tmercDiretta($params, $ellissoide, $lon, $lat);

        return ['x' => $x, 'y' => $y];
    }

    /**
     * Ellissoide della definizione, come coppia semiasse/schiacciamento.
     *
     * @param  array<string,mixed> $params
     * @return array{a:float,f:float,e2:float}
     * @throws ProiezioneEccezione
     */
    public static function ellissoide(array $params): array
    {
        if (isset($params['a'])) {
            $a = (float) $params['a'];
            if (isset($params['rf']) && (float) $params['rf'] != 0.0) {
                $f = 1.0 / (float) $params['rf'];
            } elseif (isset($params['b'])) {
                $f = ($a - (float) $params['b']) / $a;
            } else {
                $f = 0.0;
            }
        } else {
            $nome = (string) ($params['ellps'] ?? ($params['datum'] ?? 'WGS84'));
            // I nomi di datum piu comuni portano con se il proprio ellissoide.
            $nome = match ($nome) {
                'WGS84'  => 'WGS84',
                'GGRS87' => 'GRS80',
                'NAD83'  => 'GRS80',
                'ED50'   => 'intl',
                default  => $nome,
            };
            if (!isset(self::ELLISSOIDI[$nome])) {
                throw new ProiezioneEccezione('Ellissoide non riconosciuto nella definizione: ' . $nome);
            }
            $a = self::ELLISSOIDI[$nome]['a'];
            $f = 1.0 / self::ELLISSOIDI[$nome]['rf'];
        }

        return ['a' => $a, 'f' => $f, 'e2' => 2.0 * $f - $f * $f];
    }

    // ------------------------------------------------- trasversa di Mercatore

    /**
     * Geografiche -> proiettate, serie di Snyder al sesto ordine.
     *
     * Entro i tre gradi e mezzo dal meridiano centrale, che e l'ampiezza di un
     * fuso UTM o Gauss-Boaga, l'errore della serie e millimetrico.
     *
     * @param  array<string,mixed>            $params
     * @param  array{a:float,f:float,e2:float} $ellissoide
     * @return array{0:float,1:float} est, nord
     */
    private static function tmercDiretta(array $params, array $ellissoide, float $longitudine, float $latitudine): array
    {
        $a  = $ellissoide['a'];
        $e2 = $ellissoide['e2'];
        $ep2 = $e2 / (1.0 - $e2);

        $k0 = (float) ($params['k'] ?? $params['k_0'] ?? 1.0);
        $x0 = (float) ($params['x_0'] ?? 0.0);
        $y0 = (float) ($params['y_0'] ?? 0.0);
        $lon0 = deg2rad((float) ($params['lon_0'] ?? 0.0));
        $lat0 = deg2rad((float) ($params['lat_0'] ?? 0.0));

        $phi = deg2rad($latitudine);
        $lam = deg2rad($longitudine);

        $sinPhi = sin($phi);
        $cosPhi = cos($phi);
        $tanPhi = tan($phi);

        $n = $a / sqrt(1.0 - $e2 * $sinPhi * $sinPhi);
        $t = $tanPhi * $tanPhi;
        $c = $ep2 * $cosPhi * $cosPhi;
        $aa = $cosPhi * self::normalizzaLongitudine($lam - $lon0);

        $m  = self::arcoMeridiano($a, $e2, $phi);
        $m0 = self::arcoMeridiano($a, $e2, $lat0);

        $est = $x0 + $k0 * $n * (
            $aa
            + (1.0 - $t + $c) * pow($aa, 3) / 6.0
            + (5.0 - 18.0 * $t + $t * $t + 72.0 * $c - 58.0 * $ep2) * pow($aa, 5) / 120.0
        );

        $nord = $y0 + $k0 * (
            $m - $m0 + $n * $tanPhi * (
                $aa * $aa / 2.0
                + (5.0 - $t + 9.0 * $c + 4.0 * $c * $c) * pow($aa, 4) / 24.0
                + (61.0 - 58.0 * $t + $t * $t + 600.0 * $c - 330.0 * $ep2) * pow($aa, 6) / 720.0
            )
        );

        return [$est, $nord];
    }

    /**
     * Proiettate -> geografiche.
     *
     * @param  array<string,mixed>            $params
     * @param  array{a:float,f:float,e2:float} $ellissoide
     * @return array{0:float,1:float} longitudine, latitudine
     */
    private static function tmercInversa(array $params, array $ellissoide, float $est, float $nord): array
    {
        $a  = $ellissoide['a'];
        $e2 = $ellissoide['e2'];
        $ep2 = $e2 / (1.0 - $e2);

        $k0 = (float) ($params['k'] ?? $params['k_0'] ?? 1.0);
        $x0 = (float) ($params['x_0'] ?? 0.0);
        $y0 = (float) ($params['y_0'] ?? 0.0);
        $lon0 = deg2rad((float) ($params['lon_0'] ?? 0.0));
        $lat0 = deg2rad((float) ($params['lat_0'] ?? 0.0));

        $x = $est - $x0;
        $y = $nord - $y0;

        $m0 = self::arcoMeridiano($a, $e2, $lat0);
        $m  = $m0 + $y / $k0;

        $mu = $m / ($a * (1.0 - $e2 / 4.0 - 3.0 * $e2 * $e2 / 64.0 - 5.0 * pow($e2, 3) / 256.0));
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
        $n1 = $a / sqrt(1.0 - $e2 * $sinPhi1 * $sinPhi1);
        $r1 = $a * (1.0 - $e2) / pow(1.0 - $e2 * $sinPhi1 * $sinPhi1, 1.5);
        $d  = $x / ($n1 * $k0);

        $phi = $phi1 - ($n1 * $tanPhi1 / $r1) * (
            $d * $d / 2.0
            - (5.0 + 3.0 * $t1 + 10.0 * $c1 - 4.0 * $c1 * $c1 - 9.0 * $ep2) * pow($d, 4) / 24.0
            + (61.0 + 90.0 * $t1 + 298.0 * $c1 + 45.0 * $t1 * $t1 - 252.0 * $ep2 - 3.0 * $c1 * $c1) * pow($d, 6) / 720.0
        );

        $lam = $lon0 + (
            $d
            - (1.0 + 2.0 * $t1 + $c1) * pow($d, 3) / 6.0
            + (5.0 - 2.0 * $c1 + 28.0 * $t1 - 3.0 * $c1 * $c1 + 8.0 * $ep2 + 24.0 * $t1 * $t1) * pow($d, 5) / 120.0
        ) / $cosPhi1;

        return [rad2deg(self::normalizzaLongitudine($lam)), rad2deg($phi)];
    }

    // ------------------------------------------------------ trasformazione di datum

    /**
     * True se la definizione richiede una trasformazione verso WGS84.
     *
     * @param array<string,mixed> $params
     */
    public static function datumDaTrasformare(array $params): bool
    {
        $towgs84 = $params['towgs84'] ?? null;
        if ($towgs84 === null) {
            return false;
        }
        foreach ((array) $towgs84 as $valore) {
            if ((float) $valore != 0.0) {
                return true;
            }
        }
        // Tutti zero: e il caso di ETRS89, che coincide con WGS84 entro pochi
        // centimetri. Nessuna trasformazione da applicare.
        return false;
    }

    /**
     * Applica la trasformazione di datum passando per le coordinate geocentriche.
     *
     * @param  array<string,mixed>             $params
     * @param  array{a:float,f:float,e2:float} $ellissoide ellissoide del sistema locale
     * @param  bool                            $inversa    true per andare DA WGS84 al sistema locale
     * @return array{0:float,1:float} longitudine, latitudine
     */
    private static function trasformaDatum(array $params, array $ellissoide, float $longitudine, float $latitudine, bool $inversa): array
    {
        $p = array_map('floatval', (array) $params['towgs84']);
        $dx = $p[0] ?? 0.0;
        $dy = $p[1] ?? 0.0;
        $dz = $p[2] ?? 0.0;

        // Rotazioni in secondi d'arco, scala in parti per milione.
        $secondiInRadianti = M_PI / 180.0 / 3600.0;
        $rx = ($p[3] ?? 0.0) * $secondiInRadianti;
        $ry = ($p[4] ?? 0.0) * $secondiInRadianti;
        $rz = ($p[5] ?? 0.0) * $secondiInRadianti;
        $s  = 1.0 + ($p[6] ?? 0.0) / 1000000.0;

        $wgs84 = ['a' => self::WGS84_A, 'f' => 1.0 / self::WGS84_RF,
                  'e2' => 2.0 / self::WGS84_RF - 1.0 / (self::WGS84_RF * self::WGS84_RF)];

        if (!$inversa) {
            // Locale -> WGS84.
            [$x, $y, $z] = self::aGeocentriche($ellissoide, $latitudine, $longitudine);

            $xo = $s * ($x - $rz * $y + $ry * $z) + $dx;
            $yo = $s * ($rz * $x + $y - $rx * $z) + $dy;
            $zo = $s * (-$ry * $x + $rx * $y + $z) + $dz;

            return self::daGeocentriche($wgs84, $xo, $yo, $zo);
        }

        // WGS84 -> locale: si inverte la stessa trasformazione.
        [$x, $y, $z] = self::aGeocentriche($wgs84, $latitudine, $longitudine);

        $x -= $dx;
        $y -= $dy;
        $z -= $dz;

        $xo = ($x + $rz * $y - $ry * $z) / $s;
        $yo = (-$rz * $x + $y + $rx * $z) / $s;
        $zo = ($ry * $x - $rx * $y + $z) / $s;

        return self::daGeocentriche($ellissoide, $xo, $yo, $zo);
    }

    /**
     * Geografiche -> geocentriche cartesiane, quota trascurata.
     *
     * La quota si assume zero: per un catasto e la scelta giusta, perche la
     * quota degli ingressi e nota con incertezza metrica e il suo effetto sulla
     * trasformazione di datum e comunque inferiore al centimetro.
     *
     * @param  array{a:float,f:float,e2:float} $ellissoide
     * @return array{0:float,1:float,2:float}
     */
    private static function aGeocentriche(array $ellissoide, float $latitudine, float $longitudine): array
    {
        $phi = deg2rad($latitudine);
        $lam = deg2rad($longitudine);

        $a  = $ellissoide['a'];
        $e2 = $ellissoide['e2'];

        $n = $a / sqrt(1.0 - $e2 * sin($phi) * sin($phi));

        return [
            $n * cos($phi) * cos($lam),
            $n * cos($phi) * sin($lam),
            $n * (1.0 - $e2) * sin($phi),
        ];
    }

    /**
     * Geocentriche cartesiane -> geografiche, con il metodo iterativo.
     *
     * Si itera invece di usare una formula chiusa perche l'iterazione converge
     * in tre o quattro passi al decimo di millimetro ed e piu semplice da
     * verificare di una approssimazione chiusa.
     *
     * @param  array{a:float,f:float,e2:float} $ellissoide
     * @return array{0:float,1:float} longitudine, latitudine
     */
    private static function daGeocentriche(array $ellissoide, float $x, float $y, float $z): array
    {
        $a  = $ellissoide['a'];
        $e2 = $ellissoide['e2'];

        $lam = atan2($y, $x);
        $p   = sqrt($x * $x + $y * $y);

        if ($p < 1e-9) {
            // Sui poli la longitudine e indeterminata: si restituisce zero.
            return [0.0, $z >= 0 ? 90.0 : -90.0];
        }

        $phi = atan2($z, $p * (1.0 - $e2));

        for ($i = 0; $i < 8; $i++) {
            $sinPhi = sin($phi);
            $n = $a / sqrt(1.0 - $e2 * $sinPhi * $sinPhi);
            $h = $p / cos($phi) - $n;
            $nuovo = atan2($z, $p * (1.0 - $e2 * $n / ($n + $h)));

            if (abs($nuovo - $phi) < 1e-13) {
                $phi = $nuovo;
                break;
            }
            $phi = $nuovo;
        }

        return [rad2deg(self::normalizzaLongitudine($lam)), rad2deg($phi)];
    }

    // -------------------------------------------------------------------- interni

    /**
     * Verifica che il punto stia entro il campo di validita della serie.
     *
     * @param  array<string,mixed> $params
     * @throws ProiezioneEccezione
     */
    private static function esigiDentroIlFuso(array $params, float $longitudine): void
    {
        $lon0 = (float) ($params['lon_0'] ?? 0.0);
        $scostamento = abs(rad2deg(self::normalizzaLongitudine(deg2rad($longitudine - $lon0))));

        // La tolleranza evita che un punto esattamente sul limite venga
        // rifiutato per un errore di virgola mobile, con un messaggio che
        // direbbe "dista 4.0 gradi, oltre il limite di 4 gradi".
        if ($scostamento <= self::LIMITE_SCOSTAMENTO_GRADI + 1e-9) {
            return;
        }

        $suggerimento = '';
        if (isset($params['zone'])) {
            $fusoGiusto = (int) floor(($longitudine + 180.0) / 6.0) + 1;
            if ($fusoGiusto >= 1 && $fusoGiusto <= 60) {
                $suggerimento = sprintf(' Per questa longitudine il fuso corretto e il %d.', $fusoGiusto);
            }
        }

        throw new ProiezioneEccezione(sprintf(
            'Il punto dista %.1f gradi dal meridiano centrale del sistema, oltre il limite di %.0f gradi entro cui '
            . 'la conversione resta accurata. Quasi sempre significa che il fuso indicato non è quello del punto.%s',
            $scostamento, self::LIMITE_SCOSTAMENTO_GRADI, $suggerimento
        ));
    }

    /** Arco di meridiano dall'equatore alla latitudine data, in metri. */
    private static function arcoMeridiano(float $a, float $e2, float $phi): float
    {
        return $a * (
            (1.0 - $e2 / 4.0 - 3.0 * $e2 * $e2 / 64.0 - 5.0 * pow($e2, 3) / 256.0) * $phi
            - (3.0 * $e2 / 8.0 + 3.0 * $e2 * $e2 / 32.0 + 45.0 * pow($e2, 3) / 1024.0) * sin(2.0 * $phi)
            + (15.0 * $e2 * $e2 / 256.0 + 45.0 * pow($e2, 3) / 1024.0) * sin(4.0 * $phi)
            - (35.0 * pow($e2, 3) / 3072.0) * sin(6.0 * $phi)
        );
    }

    /** Riporta un angolo in radianti nell'intervallo -pi greco, +pi greco. */
    private static function normalizzaLongitudine(float $radianti): float
    {
        while ($radianti > M_PI) {
            $radianti -= 2.0 * M_PI;
        }
        while ($radianti < -M_PI) {
            $radianti += 2.0 * M_PI;
        }
        return $radianti;
    }
}
