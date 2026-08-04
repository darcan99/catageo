<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Log.php
 *  Descrizione ..: Registrazione degli accessi, delle modifiche all'archivio e
 *                  degli errori applicativi. I log sono CSV nell'archivio, per
 *                  restare leggibili con un foglio di calcolo.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Log
{
    private const INTESTAZIONE_ACCESSI = [
        'data_ora', 'username', 'esito', 'ip', 'user_agent', 'dettaglio',
    ];

    private const INTESTAZIONE_MODIFICHE = [
        'data_ora', 'username', 'azione', 'catalogo', 'codice', 'sezione', 'dettaglio',
    ];

    private const INTESTAZIONE_ERRORI = [
        'data_ora', 'username', 'livello', 'messaggio', 'file', 'riga',
    ];

    /**
     * Registra un tentativo di accesso, riuscito o no.
     *
     * @param string $esito "ok", "password_errata", "utente_inesistente",
     *                      "bloccato", "disattivato", "uscita"
     */
    public static function accesso(string $username, string $esito, string $dettaglio = ''): void
    {
        self::scrivi('accessi.csv', self::INTESTAZIONE_ACCESSI, [
            'data_ora'   => self::adesso(),
            'username'   => $username,
            'esito'      => $esito,
            'ip'         => self::ip(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'dettaglio'  => $dettaglio,
        ]);
    }

    /**
     * Registra una modifica all'archivio.
     *
     * @param string $azione   "crea", "modifica", "elimina", "migra", "rinomina",
     *                         "upload", "import", "configura"
     * @param string $catalogo sigla del catalogo, se pertinente
     * @param string $codice   codice dell'ipogeo, se pertinente
     */
    public static function modifica(
        string $azione,
        string $catalogo = '',
        string $codice = '',
        string $sezione = '',
        string $dettaglio = ''
    ): void {
        self::scrivi('modifiche.csv', self::INTESTAZIONE_MODIFICHE, [
            'data_ora'  => self::adesso(),
            'username'  => Auth::usernameCorrente(),
            'azione'    => $azione,
            'catalogo'  => $catalogo,
            'codice'    => $codice,
            'sezione'   => $sezione,
            'dettaglio' => $dettaglio,
        ]);
    }

    /**
     * Registra un errore applicativo.
     *
     * @param string $livello "avviso" oppure "errore"
     */
    public static function errore(string $messaggio, string $livello = 'errore', string $file = '', int $riga = 0): void
    {
        self::scrivi('errori.csv', self::INTESTAZIONE_ERRORI, [
            'data_ora'  => self::adesso(),
            'username'  => Auth::usernameCorrente(),
            'livello'   => $livello,
            'messaggio' => $messaggio,
            'file'      => $file,
            'riga'      => (string) $riga,
        ]);
    }

    /**
     * Legge le ultime N righe di un log, dalla piu recente.
     *
     * Per semplicita si scorre l'intero file mantenendo una finestra scorrevole:
     * i log dell'applicativo restano piccoli e questo evita la gestione a mano
     * dei buffer dalla fine del file.
     *
     * @return array<int,array<string,string>>
     */
    public static function ultime(string $nomeFile, int $quante = 50): array
    {
        $percorso = Percorsi::log($nomeFile);
        if (!is_file($percorso)) {
            return [];
        }

        $finestra = [];
        Csv::leggi($percorso, static function (array $riga) use (&$finestra, $quante): void {
            $finestra[] = $riga;
            if (count($finestra) > $quante) {
                array_shift($finestra);
            }
        });

        return array_reverse($finestra);
    }

    /**
     * Scrive una riga di log senza mai propagare eccezioni: un log che non si
     * riesce a scrivere non deve impedire l'operazione dell'utente.
     *
     * @param string[]            $intestazione
     * @param array<string,mixed> $riga
     */
    private static function scrivi(string $nomeFile, array $intestazione, array $riga): void
    {
        try {
            $cartella = Percorsi::log();
            if (!is_dir($cartella)) {
                Percorsi::assicuraCartella($cartella);
                Percorsi::proteggiCartella($cartella);
            }
            Csv::accoda(Percorsi::log($nomeFile), $intestazione, $riga);
        } catch (Throwable $e) {
            // Ultimo ripiego: il log di PHP. Nessuna eccezione verso l'alto.
            error_log('CATAGEO: log non scrivibile (' . $nomeFile . '): ' . $e->getMessage());
        }
    }

    /** Data e ora correnti in formato ISO 8601 locale. */
    private static function adesso(): string
    {
        return date('Y-m-d\TH:i:s');
    }

    /**
     * Indirizzo IP del chiamante.
     *
     * Si usa REMOTE_ADDR e non gli header X-Forwarded-For: sono falsificabili
     * dal client e su un hosting condiviso non c'e modo di sapere se ci sia
     * davanti un proxy affidabile.
     */
    private static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
