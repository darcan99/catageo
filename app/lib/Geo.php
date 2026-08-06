<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Geo.php
 *  Descrizione ..: Distanze e riquadri di inclusione per la ricerca
 *                  geografica (10.5).
 *
 *                  La ricerca per raggio procede in due tempi: prima un
 *                  pre-filtro con un riquadro rettangolare in gradi, che si
 *                  valuta con quattro confronti su una riga di CSV, poi la
 *                  distanza esatta sui soli candidati. Calcolare l'aversine su
 *                  tremila righe per scartarne 2.990 sarebbe lavoro sprecato.
 *
 *                  Il riquadro e volutamente PIU GRANDE del cerchio: e la
 *                  direzione giusta in cui sbagliare, perche un candidato di
 *                  troppo viene poi scartato dalla distanza esatta, mentre uno
 *                  di meno sparirebbe dai risultati senza che nessuno lo sappia.
 *  Versione .....: 0.13.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.13.0  2026-08-06  D.Candela  Prima stesura (fase 8).
 * ============================================================================
 */

final class Geo
{
    /**
     * Raggio medio terrestre in metri (sfera IUGG).
     *
     * Basta e avanza: su distanze da catasto l'errore rispetto all'ellissoide
     * resta sotto lo 0,5%, cioe qualche metro su un chilometro. Dichiararlo qui
     * evita che qualcuno lo scambi per una costante geodetica esatta.
     */
    public const RAGGIO_TERRA = 6371008.8;

    /**
     * Distanza in metri fra due punti, formula dell'emisenoverso.
     *
     * Si usa l'aversine e non la legge sferica dei coseni perche quest'ultima,
     * in aritmetica a virgola mobile, perde precisione proprio sulle distanze
     * brevi — che sono tutte quelle che interessano a un catasto.
     */
    public static function distanza(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $fi1 = deg2rad($lat1);
        $fi2 = deg2rad($lat2);
        $dFi = deg2rad($lat2 - $lat1);
        $dLa = deg2rad($lon2 - $lon1);

        $a = sin($dFi / 2) ** 2 + cos($fi1) * cos($fi2) * sin($dLa / 2) ** 2;

        // min(1, ...) protegge da un arrotondamento che porti l'argomento
        // appena sopra 1: asin() restituirebbe NAN su due punti coincidenti.
        return 2 * self::RAGGIO_TERRA * asin(min(1.0, sqrt($a)));
    }

    /**
     * Riquadro che contiene il cerchio di raggio dato attorno a un punto.
     *
     * @return array{latMin:float,latMax:float,lonMin:float,lonMax:float,tuttoIlGiro:bool}
     */
    public static function riquadro(float $latitudine, float $longitudine, float $raggioMetri): array
    {
        /*
         * Margine di sicurezza sul raggio, prima di ogni conversione.
         *
         * Senza, il riquadro risulta ESATTAMENTE tangente al cerchio a nord e a
         * sud: un punto proprio sul bordo puo cadere fuori per un arrotondamento
         * di pochi millimetri, o perche chi ha calcolato quella coordinata usava
         * un raggio terrestre leggermente diverso. Il pre-filtro lo scarterebbe
         * prima che la distanza esatta possa dire la sua, ed e l'unico errore
         * che qui non e recuperabile: un candidato di troppo viene poi scartato,
         * uno di meno sparisce in silenzio.
         *
         * Un millesimo piu un metro: irrilevante per il costo del filtro,
         * sufficiente contro qualunque discrepanza di modello.
         */
        $raggioMetri = max(0.0, $raggioMetri);
        $raggioMetri = $raggioMetri * 1.001 + 1.0;

        $deltaLat = rad2deg($raggioMetri / self::RAGGIO_TERRA);

        $latMin = $latitudine - $deltaLat;
        $latMax = $latitudine + $deltaLat;

        /*
         * Il delta in longitudine dipende dalla latitudine: a Roma un grado di
         * longitudine vale circa 83 km, al circolo polare meno della meta.
         * Si usa il coseno della latitudine PIU VICINA al polo fra le due del
         * riquadro, perche e li che il grado e piu corto e quindi il delta piu
         * ampio: il riquadro deve contenere il cerchio, non tagliarlo.
         */
        $latEstrema = max(abs($latMin), abs($latMax));

        if ($latEstrema >= 89.9 || $latMax >= 90.0 || $latMin <= -90.0) {
            // Il cerchio contiene un polo: in longitudine non c'e piu un
            // intervallo, si gira tutto attorno. Caso improbabile in un catasto
            // speleologico, ma un confronto sbagliato qui escluderebbe in
            // silenzio meta dei risultati.
            return [
                'latMin' => max(-90.0, $latMin),
                'latMax' => min(90.0, $latMax),
                'lonMin' => -180.0,
                'lonMax' => 180.0,
                'tuttoIlGiro' => true,
            ];
        }

        $coseno = cos(deg2rad($latEstrema));
        $deltaLon = $coseno < 1e-9 ? 180.0 : rad2deg($raggioMetri / (self::RAGGIO_TERRA * $coseno));

        if ($deltaLon >= 180.0) {
            return ['latMin' => $latMin, 'latMax' => $latMax,
                    'lonMin' => -180.0, 'lonMax' => 180.0, 'tuttoIlGiro' => true];
        }

        return [
            'latMin' => $latMin,
            'latMax' => $latMax,
            'lonMin' => $longitudine - $deltaLon,
            'lonMax' => $longitudine + $deltaLon,
            // Se il riquadro scavalca l'antimeridiano gli estremi si invertono,
            // e il confronto "lon >= min && lon <= max" non varrebbe piu. Si
            // rinuncia al pre-filtro in longitudine invece di sbagliarlo.
            'tuttoIlGiro' => ($longitudine - $deltaLon) < -180.0
                          || ($longitudine + $deltaLon) > 180.0,
        ];
    }

    /**
     * True se il punto cade nel riquadro.
     *
     * @param array{latMin:float,latMax:float,lonMin:float,lonMax:float,tuttoIlGiro:bool} $riquadro
     */
    public static function nelRiquadro(array $riquadro, float $latitudine, float $longitudine): bool
    {
        if ($latitudine < $riquadro['latMin'] || $latitudine > $riquadro['latMax']) {
            return false;
        }
        if ($riquadro['tuttoIlGiro']) {
            return true;
        }

        return $longitudine >= $riquadro['lonMin'] && $longitudine <= $riquadro['lonMax'];
    }

    /** Distanza leggibile: metri sotto il chilometro, poi chilometri. */
    public static function distanzaLeggibile(float $metri): string
    {
        if ($metri < 1000) {
            return number_format($metri, 0, ',', '.') . ' m';
        }

        return number_format($metri / 1000, $metri < 10000 ? 2 : 1, ',', '.') . ' km';
    }

    /**
     * Riquadro che contiene tutti i punti dati, o null se non ce n'e nessuno.
     *
     * @param  array<int,array{0:float,1:float}> $punti coppie [lat, lon]
     * @return array{latMin:float,latMax:float,lonMin:float,lonMax:float}|null
     */
    public static function riquadroDiPunti(array $punti): ?array
    {
        if ($punti === []) {
            return null;
        }

        $latMin = $latMax = $punti[0][0];
        $lonMin = $lonMax = $punti[0][1];

        foreach ($punti as [$lat, $lon]) {
            $latMin = min($latMin, $lat);
            $latMax = max($latMax, $lat);
            $lonMin = min($lonMin, $lon);
            $lonMax = max($lonMax, $lon);
        }

        return ['latMin' => $latMin, 'latMax' => $latMax, 'lonMin' => $lonMin, 'lonMax' => $lonMax];
    }
}
