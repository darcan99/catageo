<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Upload.php
 *  Descrizione ..: Controllo dei file in arrivo: errori di trasferimento,
 *                  dimensione, estensione ammessa, tipo reale del contenuto.
 *
 *                  Sta in una classe separata da Risorse perche e una
 *                  preoccupazione diversa: Risorse decide dove va un file e come
 *                  si chiama, Upload decide se quel file puo entrare. La
 *                  distinzione conta perche questa e la superficie da cui un
 *                  archivio su hosting condiviso viene compromesso.
 *
 *                  Tre barriere indipendenti, nell'ordine: lista nera delle
 *                  estensioni eseguibili, lista bianca per sezione da
 *                  config.xml, tipo reale letto dal contenuto con finfo. Servono
 *                  tutte e tre: l'estensione la sceglie chi carica, il tipo
 *                  dichiarato dal browser pure, e solo il contenuto non mente.
 *  Versione .....: 0.7.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.7.0  2026-08-05  D.Candela  Prima stesura (fase 5).
 * ============================================================================
 */

class UploadEccezione extends RuntimeException
{
}

final class Upload
{
    /**
     * Estensioni sempre rifiutate, qualunque cosa dica la configurazione.
     *
     * E una lista nera, che di solito e una difesa debole: qui vale perche NON
     * e l'unica barriera ma un ultimo sbarramento, per il caso in cui qualcuno
     * scriva "php" fra le estensioni ammesse in config.xml. Un file eseguibile
     * dentro il webroot e la fine dell'installazione.
     */
    public const VIETATE = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar',
        'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'com', 'bat', 'cmd', 'msi',
        'dll', 'so', 'jar', 'jsp', 'asp', 'aspx', 'htaccess', 'htpasswd',
    ];

    /**
     * Tipi che non devono mai entrare, comunque si chiami il file.
     *
     * Un .jpg il cui contenuto e codice PHP e il caso classico: l'estensione
     * passa la lista bianca, il contenuto no.
     */
    public const MIME_VIETATI = [
        'application/x-httpd-php', 'text/x-php', 'application/x-php',
        'application/x-executable', 'application/x-dosexec',
        'application/x-msdownload', 'application/x-sharedlib',
        'text/html', 'application/xhtml+xml', 'application/javascript',
        'text/javascript', 'application/x-shellscript',
    ];

    /**
     * Tipi attesi per estensione.
     *
     * Serve a intercettare la contraddizione fra nome e contenuto. Le estensioni
     * assenti da questa tabella (formati topografici specialistici come .th2 o
     * .tro) non vengono confrontate: non hanno un tipo registrato e finfo le
     * riporta come octet-stream. Restano protette dalle altre due barriere.
     *
     * application/octet-stream e ammesso ovunque perche molti sistemi lo
     * restituiscono per i binari che non riconoscono, e rifiutarlo bloccherebbe
     * file legittimi.
     */
    public const MIME_ATTESI = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
        'tif'  => ['image/tiff'],
        'tiff' => ['image/tiff'],
        'svg'  => ['image/svg+xml', 'text/xml', 'text/plain'],
        'pdf'  => ['application/pdf'],
        'zip'  => ['application/zip'],
        'kmz'  => ['application/zip', 'application/vnd.google-earth.kmz'],
        'kml'  => ['application/vnd.google-earth.kml+xml', 'text/xml', 'application/xml', 'text/plain'],
        'gpx'  => ['application/gpx+xml', 'text/xml', 'application/xml', 'text/plain'],
        'csv'  => ['text/csv', 'text/plain'],
        'txt'  => ['text/plain'],
        'rtf'  => ['application/rtf', 'text/rtf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'mp4'  => ['video/mp4'],
        'webm' => ['video/webm'],
        'ogg'  => ['video/ogg', 'audio/ogg', 'application/ogg'],
        'mov'  => ['video/quicktime'],
        'avi'  => ['video/x-msvideo', 'video/avi'],
    ];

    /** Tipo generico ammesso accanto a quelli attesi. */
    private const OCTET = 'application/octet-stream';

    /**
     * Verifica un file appena caricato e restituisce i suoi dati accertati.
     *
     * NON sposta il file: la collocazione e il nome sono decisi da Risorse, che
     * conosce lo standard di nomenclatura. Qui si stabilisce solo se il file e
     * accettabile e cosa contiene davvero.
     *
     * @param  array<string,mixed> $file una voce di $_FILES
     * @param  string $sezioneConfig chiave di <upload><estensioni sezione="…">
     * @return array{nome:string,tmp:string,dimensione:int,estensione:string,mime:string}
     * @throws UploadEccezione con un messaggio mostrabile all'utente
     */
    public static function verifica(array $file, string $sezioneConfig): array
    {
        self::esigiTrasferimentoRiuscito($file);

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            // Non e un file arrivato da HTTP: potrebbe essere un tentativo di
            // far leggere all'applicativo un percorso arbitrario del server.
            throw new UploadEccezione('Il file non risulta caricato via HTTP.');
        }

        $nome = trim((string) ($file['name'] ?? ''));
        if ($nome === '') {
            throw new UploadEccezione('Il file non ha un nome.');
        }

        $dimensione = (int) ($file['size'] ?? 0);
        if ($dimensione <= 0) {
            throw new UploadEccezione('Il file "' . $nome . '" e vuoto.');
        }

        $massimo = Config::dimensioneMaxUpload();
        if ($dimensione > $massimo) {
            throw new UploadEccezione(
                'Il file "' . $nome . '" pesa ' . Testo::dimensione($dimensione)
                . ' e supera il limite di ' . Testo::dimensione($massimo) . '.'
            );
        }

        $estensione = strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));
        if ($estensione === '') {
            throw new UploadEccezione('Il file "' . $nome . '" non ha estensione.');
        }

        // 1) lista nera
        if (in_array($estensione, self::VIETATE, true)) {
            throw new UploadEccezione(
                'I file con estensione .' . $estensione . ' non sono ammessi in nessun caso.'
            );
        }

        // 2) lista bianca della sezione
        $ammesse = Config::estensioniAmmesse($sezioneConfig);
        if ($ammesse !== [] && !in_array($estensione, $ammesse, true)) {
            throw new UploadEccezione(
                'Per questa sezione sono ammessi solo: ' . implode(', ', $ammesse)
                . '. Il file "' . $nome . '" e .' . $estensione . '.'
            );
        }

        // 3) tipo reale del contenuto
        $mime = self::tipoReale($tmp);
        self::esigiTipoCoerente($nome, $estensione, $mime);

        return [
            'nome'       => $nome,
            'tmp'        => $tmp,
            'dimensione' => $dimensione,
            'estensione' => $estensione,
            'mime'       => $mime,
        ];
    }

    /**
     * Normalizza $_FILES quando il campo e un input multiplo.
     *
     * PHP consegna gli input multipli come array di colonne
     * (name[], tmp_name[], …) invece che come elenco di file: senza questa
     * trasposizione ogni chiamante dovrebbe rifarla, e sbagliarla.
     *
     * @param  array<string,mixed> $voce
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(array $voce): array
    {
        if (!isset($voce['name'])) {
            return [];
        }

        if (!is_array($voce['name'])) {
            return [$voce];
        }

        $file = [];
        foreach (array_keys($voce['name']) as $i) {
            // I campi vuoti del form multiplo si scartano subito.
            if ((int) ($voce['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file[] = [
                'name'     => $voce['name'][$i] ?? '',
                'type'     => $voce['type'][$i] ?? '',
                'tmp_name' => $voce['tmp_name'][$i] ?? '',
                'error'    => $voce['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $voce['size'][$i] ?? 0,
            ];
        }

        return $file;
    }

    /** Tipo reale del contenuto, o octet-stream se finfo non e disponibile. */
    public static function tipoReale(string $percorso): string
    {
        if (!function_exists('finfo_open')) {
            return self::OCTET;
        }
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return self::OCTET;
        }
        $mime = @finfo_file($finfo, $percorso);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? strtolower($mime) : self::OCTET;
    }

    /** Traduce il codice di errore di PHP in un messaggio comprensibile. */
    private static function esigiTrasferimentoRiuscito(array $file): void
    {
        $errore = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errore === UPLOAD_ERR_OK) {
            return;
        }

        $nome = trim((string) ($file['name'] ?? 'senza nome'));

        // Il limite di PHP e quello che conta davvero: dirlo esplicitamente
        // evita la caccia al colpevole quando l'hosting e piu restrittivo della
        // configurazione dell'applicativo.
        $messaggio = match ($errore) {
            UPLOAD_ERR_INI_SIZE   => 'supera il limite di PHP (upload_max_filesize = '
                                     . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE  => 'supera il limite dichiarato dal modulo',
            UPLOAD_ERR_PARTIAL    => 'e arrivato solo in parte: trasferimento interrotto',
            UPLOAD_ERR_NO_FILE    => 'non e stato selezionato',
            UPLOAD_ERR_NO_TMP_DIR => 'non ha una cartella temporanea sul server',
            UPLOAD_ERR_CANT_WRITE => 'non e stato scritto su disco dal server',
            UPLOAD_ERR_EXTENSION  => 'e stato bloccato da un modulo di PHP',
            default               => 'non e stato caricato (errore ' . $errore . ')',
        };

        throw new UploadEccezione('Il file "' . $nome . '" ' . $messaggio . '.');
    }

    /**
     * Rifiuta i file il cui contenuto contraddice il nome.
     */
    private static function esigiTipoCoerente(string $nome, string $estensione, string $mime): void
    {
        if (in_array($mime, self::MIME_VIETATI, true)) {
            throw new UploadEccezione(
                'Il contenuto di "' . $nome . '" e di tipo ' . $mime
                . ', che non e ammesso a prescindere dall\'estensione.'
            );
        }

        if (!isset(self::MIME_ATTESI[$estensione])) {
            return; // formato senza tipo registrato: restano le altre barriere
        }

        $attesi = self::MIME_ATTESI[$estensione];
        if ($mime === self::OCTET || in_array($mime, $attesi, true)) {
            return;
        }

        throw new UploadEccezione(
            'Il file "' . $nome . '" si presenta come .' . $estensione
            . ' ma il suo contenuto e di tipo ' . $mime . '. Il file non e quello che dichiara di essere.'
        );
    }
}
