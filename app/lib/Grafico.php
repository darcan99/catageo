<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Grafico.php
 *  Descrizione ..: Grafici SVG generati lato server (6.13).
 *
 *                  SVG scritto in PHP e non una libreria JavaScript di
 *                  charting: il vincolo di zero dipendenze vale anche qui, e
 *                  un grafico costruito nel server ha tre proprieta che una
 *                  libreria non da. Si stampa, perche esiste gia nel documento;
 *                  si vede senza JavaScript; e non aggiunge un megabyte di
 *                  vendor da aggiornare per disegnare una spezzata.
 *
 *                  I colori vengono dalle variabili CSS di Bootstrap, cosi il
 *                  grafico segue tema e tavolozza scelti dall'utente invece di
 *                  restare l'unico riquadro chiaro in una pagina scura.
 *  Versione .....: 0.11.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.11.0  2026-08-06  D.Candela  Prima stesura (fase 7c).
 * ============================================================================
 */

final class Grafico
{
    /** Dimensioni della tela in unita SVG; la resa e poi responsiva via CSS. */
    private const LARGHEZZA = 900;
    private const ALTEZZA   = 320;

    private const MARGINE_SINISTRO = 62;
    private const MARGINE_DESTRO   = 14;
    private const MARGINE_SOPRA    = 14;
    private const MARGINE_SOTTO    = 38;

    /**
     * Oltre questo numero di punti la spezzata viene ridotta.
     *
     * Un datalogger orario produce ottomila punti l'anno: disegnarli tutti
     * genera un SVG da megabyte in cui ogni punto occupa meno di un pixel.
     * La riduzione non e una media — che appiattirebbe proprio i picchi che si
     * cercano — ma conserva minimo e massimo di ogni intervallo.
     */
    private const PUNTI_MASSIMI = 900;

    /**
     * Spezzata temporale di una serie di letture.
     *
     * @param  array<int,array<string,string>> $letture righe del CSV
     * @param  array<string,mixed>             $opzioni etichetta, unita
     * @return string SVG completo, o stringa vuota se non c'e nulla da disegnare
     */
    public static function serieTemporale(array $letture, array $opzioni = []): string
    {
        $punti = self::estraiPunti($letture);
        if (count($punti) < 2) {
            return '';
        }

        $etichetta = (string) ($opzioni['etichetta'] ?? '');
        $unita     = (string) ($opzioni['unita'] ?? '');

        $ridotti = self::riduci($punti, self::PUNTI_MASSIMI);

        $valori = array_map(static fn (array $p): float => $p['valore'], $ridotti);
        $minimo = min($valori);
        $massimo = max($valori);

        // Una serie piatta ha minimo uguale al massimo: senza margine la scala
        // avrebbe altezza zero e ogni punto finirebbe sullo stesso pixel.
        if ($massimo - $minimo < 1e-9) {
            $minimo -= 0.5;
            $massimo += 0.5;
        }
        [$minimo, $massimo, $tacche] = self::scala($minimo, $massimo);

        $x0 = self::MARGINE_SINISTRO;
        $x1 = self::LARGHEZZA - self::MARGINE_DESTRO;
        $y0 = self::MARGINE_SOPRA;
        $y1 = self::ALTEZZA - self::MARGINE_SOTTO;

        $ultimo = count($ridotti) - 1;
        $perX = static fn (int $i): float => $ultimo === 0
            ? $x0 : $x0 + ($x1 - $x0) * $i / $ultimo;
        $perY = static fn (float $v): float =>
            $y1 - ($y1 - $y0) * ($v - $minimo) / ($massimo - $minimo);

        $svg = [];
        $svg[] = '<svg class="catageo-grafico" viewBox="0 0 ' . self::LARGHEZZA . ' ' . self::ALTEZZA . '"'
               . ' role="img" preserveAspectRatio="none"'
               . ' aria-label="' . Testo::esc('Andamento di ' . ($etichetta !== '' ? $etichetta : 'la serie')) . '">';

        // --- griglia e scala verticale
        foreach ($tacche as $valore) {
            $y = round($perY($valore), 2);
            $svg[] = '<line class="catageo-grafico-griglia" x1="' . $x0 . '" y1="' . $y
                   . '" x2="' . $x1 . '" y2="' . $y . '"/>';
            $svg[] = '<text class="catageo-grafico-scala" x="' . ($x0 - 8) . '" y="' . ($y + 4)
                   . '" text-anchor="end">' . Testo::esc(self::numero($valore)) . '</text>';
        }

        // --- spezzata
        $tratti = [];
        foreach ($ridotti as $i => $punto) {
            $tratti[] = ($i === 0 ? 'M' : 'L') . round($perX($i), 2) . ' ' . round($perY($punto['valore']), 2);
        }
        $svg[] = '<path class="catageo-grafico-linea" d="' . implode(' ', $tratti) . '"/>';

        // --- estremi dell'asse temporale
        $svg[] = '<line class="catageo-grafico-asse" x1="' . $x0 . '" y1="' . $y1
               . '" x2="' . $x1 . '" y2="' . $y1 . '"/>';
        $svg[] = '<text class="catageo-grafico-scala" x="' . $x0 . '" y="' . ($y1 + 18) . '">'
               . Testo::esc($ridotti[0]['data']) . '</text>';
        $svg[] = '<text class="catageo-grafico-scala" x="' . $x1 . '" y="' . ($y1 + 18)
               . '" text-anchor="end">' . Testo::esc($ridotti[$ultimo]['data']) . '</text>';

        if ($unita !== '') {
            $svg[] = '<text class="catageo-grafico-scala" x="' . $x0 . '" y="' . ($y0 + 2) . '">'
                   . Testo::esc($unita) . '</text>';
        }

        // Il taglio si dichiara sul grafico stesso: chi lo guarda deve sapere
        // che sta vedendo una riduzione e non tutti i dati.
        if (count($ridotti) < count($punti)) {
            $svg[] = '<text class="catageo-grafico-scala" x="' . $x1 . '" y="' . ($y0 + 2)
                   . '" text-anchor="end">'
                   . Testo::esc(count($punti) . ' letture ridotte a ' . count($ridotti) . ' punti')
                   . '</text>';
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * Punti (data, valore) utilizzabili, in ordine cronologico.
     *
     * Le letture scartate e quelle senza valore non entrano: una spezzata che
     * passa per un dato marcato anomalo racconterebbe una cosa falsa.
     *
     * @param  array<int,array<string,string>> $letture
     * @return array<int,array{data:string,valore:float}>
     */
    private static function estraiPunti(array $letture): array
    {
        $punti = [];

        foreach ($letture as $riga) {
            $validita = trim((string) ($riga['validita'] ?? 'valido'));
            if ($validita !== '' && !in_array($validita, Scientifici::VALIDITA_UTILI, true)) {
                continue;
            }

            $valore = Scientifici::aNumero((string) ($riga['valore'] ?? ''));
            if ($valore === null) {
                continue;
            }

            $data = trim((string) ($riga['data'] ?? ''));
            $ora  = trim((string) ($riga['ora'] ?? ''));

            $punti[] = ['data' => trim($data . ' ' . $ora), 'valore' => $valore, 'ordine' => $data . ' ' . $ora];
        }

        usort($punti, static fn (array $a, array $b): int => strcmp($a['ordine'], $b['ordine']));

        return array_map(
            static fn (array $p): array => ['data' => $p['data'], 'valore' => $p['valore']],
            $punti
        );
    }

    /**
     * Riduce i punti conservando minimo e massimo di ogni intervallo.
     *
     * Una media mobile leviga i picchi, ed e proprio nei picchi che sta
     * l'informazione di una serie ambientale: la piena che allaga il cunicolo,
     * il massimo di radon in estate. Qui ogni intervallo contribuisce con i
     * suoi due estremi, nell'ordine in cui compaiono.
     *
     * @param  array<int,array{data:string,valore:float}> $punti
     * @return array<int,array{data:string,valore:float}>
     */
    private static function riduci(array $punti, int $massimo): array
    {
        $totale = count($punti);
        if ($totale <= $massimo) {
            return $punti;
        }

        $intervalli = max(1, intdiv($massimo, 2));
        $ampiezza   = $totale / $intervalli;
        $ridotti    = [];

        for ($i = 0; $i < $intervalli; $i++) {
            $da = (int) floor($i * $ampiezza);
            $a  = min($totale, (int) floor(($i + 1) * $ampiezza));
            if ($a <= $da) {
                continue;
            }

            $fetta = array_slice($punti, $da, $a - $da);
            $iMin = 0;
            $iMax = 0;
            foreach ($fetta as $k => $p) {
                if ($p['valore'] < $fetta[$iMin]['valore']) { $iMin = $k; }
                if ($p['valore'] > $fetta[$iMax]['valore']) { $iMax = $k; }
            }

            if ($iMin === $iMax) {
                $ridotti[] = $fetta[$iMin];
                continue;
            }
            // Si mantiene l'ordine temporale fra i due estremi, altrimenti la
            // spezzata farebbe zigzag all'indietro.
            $ridotti[] = $iMin < $iMax ? $fetta[$iMin] : $fetta[$iMax];
            $ridotti[] = $iMin < $iMax ? $fetta[$iMax] : $fetta[$iMin];
        }

        return $ridotti;
    }

    /**
     * Estremi arrotondati e tacche "gradevoli" per l'asse dei valori.
     *
     * @return array{0:float,1:float,2:array<int,float>}
     */
    private static function scala(float $minimo, float $massimo): array
    {
        $intervallo = $massimo - $minimo;
        $passoGrezzo = $intervallo / 4;

        $magnitudine = 10 ** floor(log10($passoGrezzo));
        $normalizzato = $passoGrezzo / $magnitudine;

        // Passi accettabili: 1, 2, 5, 10 per magnitudine. Sono quelli che
        // producono etichette che una persona legge senza contare gli zeri.
        $passo = ($normalizzato <= 1 ? 1 : ($normalizzato <= 2 ? 2 : ($normalizzato <= 5 ? 5 : 10)))
               * $magnitudine;

        $basso = floor($minimo / $passo) * $passo;
        $alto  = ceil($massimo / $passo) * $passo;

        $tacche = [];
        for ($v = $basso; $v <= $alto + $passo / 1000; $v += $passo) {
            $tacche[] = $v;
            if (count($tacche) > 12) {
                break;
            }
        }

        return [$basso, $alto, $tacche];
    }

    /** Numero leggibile: senza decimali inutili, con la virgola italiana. */
    private static function numero(float $valore): string
    {
        $decimali = abs($valore) >= 100 ? 0 : (abs($valore) >= 1 ? 1 : 3);
        $testo = number_format($valore, $decimali, ',', '');

        return str_contains($testo, ',') ? rtrim(rtrim($testo, '0'), ',') : $testo;
    }
}
