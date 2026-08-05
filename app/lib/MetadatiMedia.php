<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/MetadatiMedia.php
 *  Descrizione ..: Lettura della data di scatto e della posizione GPS dai
 *                  metadati incorporati in foto e video.
 *
 *                  Serve perche il dato c'e gia: chi fotografa l'ingresso di
 *                  una cavita con il telefono porta a casa data e coordinate
 *                  dentro il file, e chiedergliele di nuovo a mano significa
 *                  farsi dare un dato peggiore di quello che si ha in archivio.
 *
 *                  I valori letti qui NON sovrascrivono mai quelli indicati
 *                  dall'operatore: riempiono solo i campi lasciati vuoti. Un
 *                  rilievo fatto con il GPS professionale vale piu dell'EXIF di
 *                  un telefono, e l'ordine di precedenza deve dirlo.
 *
 *                  Per i video non esiste l'EXIF: MP4 e MOV sono contenitori
 *                  ISO BMFF, una sequenza di scatole annidate. Se ne leggono due:
 *                  "mvhd" per la data di creazione e "©xyz" per la posizione in
 *                  notazione ISO 6709. E un parser minimo e prudente, perche
 *                  qui un errore di lettura non deve mai impedire il
 *                  caricamento del file.
 *  Versione .....: 0.7.1
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.7.1  2026-08-05  D.Candela  Prima stesura.
 * ============================================================================
 */

final class MetadatiMedia
{
    /**
     * Scarto fra l'epoca di QuickTime/MP4 (1904-01-01) e quella Unix.
     * Sono i secondi di 66 anni, 17 dei quali bisestili.
     */
    private const EPOCA_QUICKTIME = 2082844800;

    /** Non si leggono piu di questi byte alla ricerca delle scatole. */
    private const LIMITE_SCANSIONE = 8388608; // 8 MB

    /**
     * Metadati utili di un file, secondo il tipo di sezione.
     *
     * Non lancia mai: un file senza metadati, o con metadati illeggibili, e la
     * normalita e non un errore.
     *
     * @return array{data:string,latitudine:string,longitudine:string}
     */
    public static function leggi(string $percorso, string $anteprima): array
    {
        $vuoto = ['data' => '', 'latitudine' => '', 'longitudine' => ''];

        if (!is_file($percorso)) {
            return $vuoto;
        }

        try {
            return match ($anteprima) {
                'immagine' => self::daImmagine($percorso),
                'video'    => self::daVideo($percorso),
                default    => $vuoto,
            };
        } catch (Throwable $e) {
            // I metadati sono una comodita: se la lettura fallisce si prosegue
            // senza, e il file viene comunque archiviato.
            Log::errore('Metadati non letti da ' . basename($percorso) . ': ' . $e->getMessage(), 'avviso');
            return $vuoto;
        }
    }

    // ========================================================================
    //  IMMAGINI
    // ========================================================================

    /**
     * Data di scatto e posizione dall'EXIF.
     *
     * @return array{data:string,latitudine:string,longitudine:string}
     */
    public static function daImmagine(string $percorso): array
    {
        $esito = ['data' => '', 'latitudine' => '', 'longitudine' => ''];

        if (!function_exists('exif_read_data')) {
            return $esito;
        }

        // Solo JPEG e TIFF portano EXIF: chiederlo a un PNG produce solo un
        // avviso inutile nel log.
        $tipo = @exif_imagetype($percorso);
        if ($tipo !== IMAGETYPE_JPEG && $tipo !== IMAGETYPE_TIFF_II && $tipo !== IMAGETYPE_TIFF_MM) {
            return $esito;
        }

        $exif = @exif_read_data($percorso, 'ANY_TAG', true);
        if (!is_array($exif)) {
            return $esito;
        }

        $esito['data'] = self::dataExif($exif);

        $gps = self::gpsExif($exif);
        $esito['latitudine']  = $gps['latitudine'];
        $esito['longitudine'] = $gps['longitudine'];

        return $esito;
    }

    /**
     * Data dello scatto, nell'ordine di attendibilita dei tag.
     *
     * DateTimeOriginal e il momento dello scatto; DateTime puo essere quello
     * dell'ultima modifica del file fatta da un programma di ritocco, quindi
     * viene per ultimo.
     *
     * @param array<string,mixed> $exif
     */
    private static function dataExif(array $exif): string
    {
        $candidati = [
            $exif['EXIF']['DateTimeOriginal']  ?? null,
            $exif['EXIF']['DateTimeDigitized'] ?? null,
            $exif['IFD0']['DateTime']          ?? null,
            $exif['EXIF']['DateTime']          ?? null,
        ];

        foreach ($candidati as $valore) {
            if (!is_string($valore) || trim($valore) === '') {
                continue;
            }
            // L'EXIF usa "2026:07:14 09:31:02": i due punti nella data non sono
            // un refuso, sono il formato dello standard.
            if (preg_match('/^(\d{4}):(\d{2}):(\d{2})[ T]/', trim($valore), $p)) {
                $anno = (int) $p[1];
                $mese = (int) $p[2];
                $gior = (int) $p[3];
                if (checkdate($mese, $gior, $anno) && $anno > 1900) {
                    return sprintf('%04d-%02d-%02d', $anno, $mese, $gior);
                }
            }
        }

        return '';
    }

    /**
     * Posizione dai tag GPS, convertita in gradi decimali WGS84.
     *
     * @param  array<string,mixed> $exif
     * @return array{latitudine:string,longitudine:string}
     */
    private static function gpsExif(array $exif): array
    {
        $vuoto = ['latitudine' => '', 'longitudine' => ''];

        $gps = $exif['GPS'] ?? null;
        if (!is_array($gps)) {
            return $vuoto;
        }

        $lat = self::gradiDaExif($gps['GPSLatitude'] ?? null, (string) ($gps['GPSLatitudeRef'] ?? ''), 90.0);
        $lon = self::gradiDaExif($gps['GPSLongitude'] ?? null, (string) ($gps['GPSLongitudeRef'] ?? ''), 180.0);

        if ($lat === null || $lon === null) {
            return $vuoto;
        }

        // Molti apparecchi scrivono i tag GPS a zero quando il fix non c'e
        // stato: 0,0 e in mezzo all'Atlantico e non e mai una posizione vera
        // per un ipogeo.
        if (abs($lat) < 0.0001 && abs($lon) < 0.0001) {
            return $vuoto;
        }

        return [
            'latitudine'  => number_format($lat, 6, '.', ''),
            'longitudine' => number_format($lon, 6, '.', ''),
        ];
    }

    /**
     * Converte i tre valori sessagesimali dell'EXIF in gradi decimali.
     *
     * L'EXIF memorizza gradi, minuti e secondi come frazioni "numeratore/
     * denominatore": i secondi arrivano spesso come "5361/100" e non come 53,61.
     *
     * @param mixed $valori
     */
    private static function gradiDaExif($valori, string $riferimento, float $massimo): ?float
    {
        if (!is_array($valori) || count($valori) < 3) {
            return null;
        }

        $parti = [];
        foreach (array_slice($valori, 0, 3) as $frazione) {
            $numero = self::frazione((string) $frazione);
            if ($numero === null) {
                return null;
            }
            $parti[] = $numero;
        }

        $gradi = $parti[0] + $parti[1] / 60 + $parti[2] / 3600;

        $riferimento = strtoupper(trim($riferimento));
        if ($riferimento === 'S' || $riferimento === 'W') {
            $gradi = -$gradi;
        }

        return abs($gradi) <= $massimo ? $gradi : null;
    }

    /** Interpreta "num/den" oppure un numero semplice. */
    private static function frazione(string $valore): ?float
    {
        $valore = trim($valore);
        if ($valore === '') {
            return null;
        }

        if (str_contains($valore, '/')) {
            [$num, $den] = array_pad(explode('/', $valore, 2), 2, '1');
            $den = (float) $den;
            if ($den == 0.0) {
                return null;
            }
            return (float) $num / $den;
        }

        return is_numeric($valore) ? (float) $valore : null;
    }

    // ========================================================================
    //  VIDEO (MP4 / MOV — contenitori ISO BMFF)
    // ========================================================================

    /**
     * Data di creazione e posizione da un video MP4/MOV.
     *
     * @return array{data:string,latitudine:string,longitudine:string}
     */
    public static function daVideo(string $percorso): array
    {
        $esito = ['data' => '', 'latitudine' => '', 'longitudine' => ''];

        $handle = @fopen($percorso, 'rb');
        if ($handle === false) {
            return $esito;
        }

        try {
            $moov = self::trovaScatola($handle, 'moov', 0, (int) filesize($percorso));
            if ($moov === null) {
                return $esito;
            }

            $mvhd = self::trovaScatola($handle, 'mvhd', $moov['inizio'], $moov['fine']);
            if ($mvhd !== null) {
                $esito['data'] = self::dataDaMvhd($handle, $mvhd['inizio']);
            }

            $udta = self::trovaScatola($handle, 'udta', $moov['inizio'], $moov['fine']);
            if ($udta !== null) {
                // "©xyz": la chiocciola e in realta il byte 0xA9, non il
                // carattere UTF-8 della chiocciola commerciale.
                $xyz = self::trovaScatola($handle, "\xA9xyz", $udta['inizio'], $udta['fine']);
                if ($xyz !== null) {
                    $gps = self::gpsDaIso6709(self::leggiTesto($handle, $xyz['inizio'], $xyz['fine']));
                    $esito['latitudine']  = $gps['latitudine'];
                    $esito['longitudine'] = $gps['longitudine'];
                }
            }

            return $esito;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Cerca una scatola per nome fra due posizioni del file.
     *
     * Le scatole ISO BMFF sono "lunghezza (4 byte) + tipo (4 byte) + contenuto".
     * Non si scende in ricorsione: si cerca solo al livello indicato, e i due
     * livelli che servono vengono chiesti in sequenza dal chiamante.
     *
     * @param  resource $handle
     * @return array{inizio:int,fine:int}|null posizioni del CONTENUTO
     */
    private static function trovaScatola($handle, string $tipo, int $da, int $a): ?array
    {
        $posizione = $da;
        $limite    = min($a, $da + self::LIMITE_SCANSIONE);

        while ($posizione + 8 <= $limite) {
            if (fseek($handle, $posizione) !== 0) {
                return null;
            }
            $intestazione = fread($handle, 8);
            if ($intestazione === false || strlen($intestazione) < 8) {
                return null;
            }

            $parti = unpack('Nlunghezza/a4tipo', $intestazione);
            if ($parti === false) {
                return null;
            }

            $lunghezza = (int) $parti['lunghezza'];
            $contenuto = $posizione + 8;

            if ($lunghezza === 1) {
                // Lunghezza a 64 bit: usata dai file oltre i 4 GB.
                $esteso = fread($handle, 8);
                if ($esteso === false || strlen($esteso) < 8) {
                    return null;
                }
                $alte = unpack('Nalte/Nbasse', $esteso);
                if ($alte === false) {
                    return null;
                }
                $lunghezza = ($alte['alte'] << 32) | $alte['basse'];
                $contenuto += 8;
            } elseif ($lunghezza === 0) {
                // Fino alla fine del file.
                $lunghezza = $a - $posizione;
            }

            // Una lunghezza incoerente significa file malformato o non ISO BMFF:
            // meglio fermarsi che rincorrere posizioni a caso.
            if ($lunghezza < 8 || $posizione + $lunghezza > $a) {
                return null;
            }

            if ($parti['tipo'] === $tipo) {
                return ['inizio' => $contenuto, 'fine' => $posizione + $lunghezza];
            }

            $posizione += $lunghezza;
        }

        return null;
    }

    /**
     * Data di creazione dalla scatola mvhd.
     *
     * @param resource $handle
     */
    private static function dataDaMvhd($handle, int $inizio): string
    {
        if (fseek($handle, $inizio) !== 0) {
            return '';
        }

        $testa = fread($handle, 4);
        if ($testa === false || strlen($testa) < 4) {
            return '';
        }

        $versione = ord($testa[0]);

        // Versione 1 usa interi a 64 bit, la 0 a 32.
        $dati = fread($handle, $versione === 1 ? 8 : 4);
        if ($dati === false) {
            return '';
        }

        if ($versione === 1) {
            if (strlen($dati) < 8) {
                return '';
            }
            $parti = unpack('Nalte/Nbasse', $dati);
            $secondi = $parti === false ? 0 : (($parti['alte'] << 32) | $parti['basse']);
        } else {
            if (strlen($dati) < 4) {
                return '';
            }
            $parti = unpack('Nvalore', $dati);
            $secondi = $parti === false ? 0 : (int) $parti['valore'];
        }

        if ($secondi <= 0) {
            return '';
        }

        $unix = $secondi - self::EPOCA_QUICKTIME;

        // Fuori da un intervallo plausibile il dato non e una data: alcuni
        // apparecchi lasciano il campo a zero o scrivono valori privi di senso.
        if ($unix < 0 || $unix > time() + 86400) {
            return '';
        }

        return date('Y-m-d', $unix);
    }

    /** @param resource $handle */
    private static function leggiTesto($handle, int $inizio, int $fine): string
    {
        $lunghezza = max(0, min(256, $fine - $inizio));
        if ($lunghezza === 0 || fseek($handle, $inizio) !== 0) {
            return '';
        }
        $dati = fread($handle, $lunghezza);

        return $dati === false ? '' : $dati;
    }

    /**
     * Posizione in notazione ISO 6709, come "+41.8562+012.5321+230.000/".
     *
     * Il valore e preceduto da quattro byte di lunghezza e lingua che non
     * interessano: si cerca direttamente lo schema dei segni.
     *
     * @return array{latitudine:string,longitudine:string}
     */
    public static function gpsDaIso6709(string $testo): array
    {
        $vuoto = ['latitudine' => '', 'longitudine' => ''];

        if (!preg_match('/([+\-]\d{1,3}(?:\.\d+)?)([+\-]\d{1,3}(?:\.\d+)?)/', $testo, $parti)) {
            return $vuoto;
        }

        $lat = (float) $parti[1];
        $lon = (float) $parti[2];

        if (abs($lat) > 90.0 || abs($lon) > 180.0) {
            return $vuoto;
        }
        if (abs($lat) < 0.0001 && abs($lon) < 0.0001) {
            return $vuoto;
        }

        return [
            'latitudine'  => number_format($lat, 6, '.', ''),
            'longitudine' => number_format($lon, 6, '.', ''),
        ];
    }
}
