<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Miniature.php
 *  Descrizione ..: Generazione delle miniature delle foto, con GD.
 *
 *                  GD e opzionale: se manca, la galleria mostra gli originali
 *                  ridimensionati dal CSS e la diagnostica lo segnala. Meglio
 *                  una galleria pesante che una galleria vuota.
 *
 *                  Il punto delicato non e il ridimensionamento ma la MEMORIA.
 *                  GD lavora sull'immagine decompressa: una foto da 6000x4000,
 *                  che come JPEG occupa 4 MB, in memoria ne chiede quasi cento.
 *                  Su un hosting con memory_limit a 128 MB questo produce una
 *                  pagina bianca senza alcun messaggio, cioe il guasto piu
 *                  difficile da diagnosticare per chi installa. Per questo la
 *                  memoria necessaria viene STIMATA PRIMA di aprire il file, e
 *                  se non basta si rinuncia con un motivo scritto nel log.
 *  Versione .....: 0.7.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.7.0  2026-08-05  D.Candela  Prima stesura (fase 5).
 * ============================================================================
 */

final class Miniature
{
    /** Qualita del JPEG prodotto: buon compromesso fra peso e resa. */
    public const QUALITA = 82;

    /** Larghezza usata se la configurazione non dice altro. */
    public const LARGHEZZA_PREDEFINITA = 400;

    /** Tipi che GD sa leggere e che quindi si possono miniaturizzare. */
    private const TIPI_LEGGIBILI = [
        IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_BMP,
    ];

    /** True se GD e disponibile con il supporto JPEG, che e quello che serve. */
    public static function disponibile(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagejpeg');
    }

    /** Larghezza delle miniature da configurazione. */
    public static function larghezza(): int
    {
        $valore = Config::intero('upload.miniatureLarghezza', self::LARGHEZZA_PREDEFINITA);
        return max(80, min(2000, $valore));
    }

    /**
     * Genera la miniatura di un'immagine.
     *
     * @return bool true se la miniatura e stata scritta; false con il motivo in
     *              $motivo se non era possibile (formato, memoria, GD assente)
     */
    public static function genera(string $origine, string $destinazione, ?string &$motivo = null): bool
    {
        $motivo = '';

        if (!self::disponibile()) {
            $motivo = 'estensione GD non disponibile';
            return false;
        }

        if (!is_file($origine)) {
            $motivo = 'file di origine assente';
            return false;
        }

        $info = @getimagesize($origine);
        if ($info === false) {
            $motivo = 'non è un\'immagine leggibile';
            return false;
        }

        [$larghezzaO, $altezzaO] = $info;
        $tipo = (int) ($info[2] ?? 0);

        if (!in_array($tipo, self::TIPI_LEGGIBILI, true)) {
            $motivo = 'formato non supportato da GD (' . image_type_to_mime_type($tipo) . ')';
            return false;
        }

        if ($larghezzaO < 1 || $altezzaO < 1) {
            $motivo = 'dimensioni dell\'immagine non valide';
            return false;
        }

        if (!self::memoriaSufficiente($larghezzaO, $altezzaO, $info)) {
            $motivo = 'memoria insufficiente per un\'immagine di '
                    . $larghezzaO . 'x' . $altezzaO . ' pixel';
            return false;
        }

        $sorgente = self::apri($origine, $tipo);
        if ($sorgente === null) {
            $motivo = 'apertura non riuscita';
            return false;
        }

        try {
            // L'orientamento EXIF va applicato PRIMA di calcolare le proporzioni:
            // una foto verticale scattata col telefono e memorizzata orizzontale
            // con l'istruzione di ruotarla, e senza questo passaggio la miniatura
            // esce coricata.
            $sorgente = self::raddrizza($sorgente, $origine, $tipo, $larghezzaO, $altezzaO);

            $larghezza = self::larghezza();
            $scala     = min(1.0, $larghezza / $larghezzaO);

            // Non si ingrandisce mai: una miniatura piu grande dell'originale
            // sarebbe solo un file piu pesante e piu sfocato.
            $nuovaL = max(1, (int) round($larghezzaO * $scala));
            $nuovaA = max(1, (int) round($altezzaO * $scala));

            $mini = imagecreatetruecolor($nuovaL, $nuovaA);
            if ($mini === false) {
                $motivo = 'creazione della miniatura non riuscita';
                return false;
            }

            // Fondo bianco: le PNG e le WebP con trasparenza, salvate in JPEG,
            // avrebbero altrimenti le zone trasparenti nere.
            $bianco = imagecolorallocate($mini, 255, 255, 255);
            imagefilledrectangle($mini, 0, 0, $nuovaL, $nuovaA, $bianco);

            imagecopyresampled($mini, $sorgente, 0, 0, 0, 0, $nuovaL, $nuovaA, $larghezzaO, $altezzaO);

            Percorsi::assicuraCartella(dirname($destinazione));

            // Scrittura atomica: una miniatura scritta a meta, se il processo
            // muore, resterebbe come file valido ma corrotto.
            $tmp = $destinazione . '.tmp';
            $esito = imagejpeg($mini, $tmp, self::QUALITA);
            imagedestroy($mini);

            if ($esito === false) {
                @unlink($tmp);
                $motivo = 'scrittura della miniatura non riuscita';
                return false;
            }

            if (!@rename($tmp, $destinazione)) {
                @unlink($tmp);
                $motivo = 'rinomina della miniatura non riuscita';
                return false;
            }

            @chmod($destinazione, 0644);
            return true;
        } finally {
            if (is_resource($sorgente) || $sorgente instanceof GdImage) {
                imagedestroy($sorgente);
            }
        }
    }

    /**
     * Genera la miniatura di una risorsa e annota nel log se non ci riesce.
     *
     * Non lancia eccezioni: il caricamento di una foto non deve fallire perche
     * la miniatura non si e potuta fare. La foto c'e, ed e il dato che conta.
     *
     * @param array<string,mixed> $risorsa
     */
    public static function perRisorsa(string $codice, string $sigla, array $risorsa): bool
    {
        if (Sezioni::anteprima($sigla) !== 'immagine') {
            return false;
        }

        $origine = Risorse::percorsoFile($codice, $sigla, (int) $risorsa['progressivo']);
        $cartella = Risorse::cartellaMiniature($codice, $sigla);
        if ($origine === null || $cartella === null) {
            return false;
        }

        $destinazione = Percorsi::unisci($cartella, Risorse::nomeMiniatura($risorsa));

        $motivo = '';
        if (self::genera($origine, $destinazione, $motivo)) {
            return true;
        }

        Log::errore(
            'Miniatura non generata per ' . $codice . ' ' . $sigla . $risorsa['progressivo']
            . ': ' . $motivo,
            'avviso'
        );
        return false;
    }

    /**
     * Stima se la memoria basta per decomprimere l'immagine.
     *
     * La formula e quella comunemente usata: pixel x canali x profondita, con un
     * margine perche GD alloca anche la copia di destinazione e le strutture
     * interne. Meglio rinunciare a una miniatura che far morire il processo.
     *
     * @param array<int|string,mixed> $info esito di getimagesize
     */
    private static function memoriaSufficiente(int $larghezza, int $altezza, array $info): bool
    {
        $limite = Testo::aByte((string) ini_get('memory_limit'));
        if ($limite <= 0) {
            return true; // nessun limite dichiarato
        }

        $canali = (int) ($info['channels'] ?? 4);
        $bit    = (int) ($info['bits'] ?? 8);
        $canali = $canali > 0 ? $canali : 4;
        $bit    = $bit > 0 ? $bit : 8;

        // x2 perche esistono contemporaneamente originale e destinazione,
        // x1.7 di margine per le strutture interne di GD.
        $necessaria = (int) ($larghezza * $altezza * $canali * ($bit / 8) * 2 * 1.7);
        $disponibile = $limite - memory_get_usage(true);

        return $necessaria < $disponibile;
    }

    /** Apre l'immagine con la funzione giusta per il suo tipo. */
    private static function apri(string $percorso, int $tipo): mixed
    {
        $immagine = match ($tipo) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($percorso) : false,
            IMAGETYPE_PNG  => function_exists('imagecreatefrompng')  ? @imagecreatefrompng($percorso)  : false,
            IMAGETYPE_GIF  => function_exists('imagecreatefromgif')  ? @imagecreatefromgif($percorso)  : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($percorso) : false,
            IMAGETYPE_BMP  => function_exists('imagecreatefrombmp')  ? @imagecreatefrombmp($percorso)  : false,
            default        => false,
        };

        return $immagine === false ? null : $immagine;
    }

    /**
     * Applica l'orientamento dichiarato nell'EXIF, se c'e.
     *
     * Solo per i JPEG e solo se l'estensione exif e presente: senza, la foto
     * resta come e memorizzata, che e il comportamento di prima e non un guasto.
     */
    private static function raddrizza(mixed $immagine, string $percorso, int $tipo, int &$larghezza, int &$altezza): mixed
    {
        if ($tipo !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $immagine;
        }

        $exif = @exif_read_data($percorso);
        $orientamento = (int) ($exif['Orientation'] ?? 0);
        if ($orientamento < 2 || $orientamento > 8) {
            return $immagine;
        }

        // I valori 2, 4, 5 e 7 comportano anche uno specchiamento: senza,
        // le foto scattate con certi telefoni escono riflesse.
        $rotazione = match ($orientamento) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        $specchia = in_array($orientamento, [2, 4, 5, 7], true);

        if ($rotazione !== 0 && function_exists('imagerotate')) {
            $ruotata = @imagerotate($immagine, $rotazione, 0);
            if ($ruotata !== false) {
                imagedestroy($immagine);
                $immagine = $ruotata;
            }
        }

        if ($specchia && function_exists('imageflip')) {
            @imageflip($immagine, IMG_FLIP_HORIZONTAL);
        }

        // Dopo una rotazione di un quarto le dimensioni sono scambiate: vanno
        // rilette, altrimenti imagecopyresampled campiona un'area sbagliata.
        $larghezza = (int) imagesx($immagine);
        $altezza   = (int) imagesy($immagine);

        return $immagine;
    }
}
