<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Testo.php
 *  Descrizione ..: Funzioni sui testi: escaping per l'output, normalizzazione
 *                  per la ricerca, sanitizzazione dei nomi di file secondo lo
 *                  standard di nomenclatura, estratti a runtime.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Testo
{
    /** Lunghezza massima di un nome di file, estensione esclusa. */
    public const MAX_NOME_FILE = 120;

    /**
     * Caratteri vietati nei nomi di file su Windows e problematici su Linux.
     * Il carattere di percorso e incluso: un nome non deve mai poter navigare.
     */
    private const VIETATI_FILE = ['\\', '/', ':', '*', '?', '"', '<', '>', '|'];

    /**
     * Escaping per l'output HTML. Da usare SEMPRE sui dati provenienti
     * dall'archivio: un titolo di foto o un nome di ipogeo sono testo libero.
     */
    public static function esc(?string $valore): string
    {
        return htmlspecialchars((string) $valore, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escaping per un attributo usato in un contesto JavaScript inline.
     */
    public static function escJson(mixed $valore): string
    {
        $json = json_encode(
            $valore,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        return $json === false ? 'null' : $json;
    }

    /**
     * Normalizza un testo per la ricerca: minuscole, accenti traslitterati,
     * spazi collassati. Serve perche "Grotta dei Ràgni" deve essere trovata
     * cercando "ragni".
     */
    public static function normalizzaRicerca(string $valore): string
    {
        $valore = self::traslittera($valore);
        $valore = mb_strtolower($valore, 'UTF-8');
        $valore = preg_replace('/\s+/u', ' ', $valore) ?? $valore;
        return trim($valore);
    }

    /**
     * Traslittera i caratteri accentati nei corrispondenti ASCII.
     *
     * Non si usa iconv(): su alcune installazioni PHP restituisce '?' invece
     * della lettera base, il che renderebbe le ricerche inaffidabili. La
     * tabella e esplicita e copre quanto serve all'italiano e alle lingue
     * europee piu comuni nei nomi di localita.
     */
    public static function traslittera(string $valore): string
    {
        static $tabella = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
            'š' => 's', 'ž' => 'z', 'č' => 'c', 'ć' => 'c', 'đ' => 'd', 'ł' => 'l',
            'ń' => 'n', 'ő' => 'o', 'ř' => 'r', 'ś' => 's', 'ť' => 't', 'ů' => 'u',
            'ź' => 'z', 'ż' => 'z', 'ğ' => 'g', 'ı' => 'i', 'ş' => 's',
        ];

        $minuscolo = mb_strtolower($valore, 'UTF-8');
        $sostituito = strtr($minuscolo, $tabella);

        // Se l'originale non era minuscolo, la traslitterazione serve comunque
        // solo per confronti: si restituisce la versione normalizzata.
        return $sostituito;
    }

    /**
     * Rende sicuro un nome di file conservandone la leggibilita, secondo le
     * regole di nomenclatura dell'archivio (ANALISI.md 4.1).
     *
     * Gli accenti sono conservati per default; con $ascii = true vengono
     * traslitterati, utile su hosting con filesystem non UTF-8.
     */
    public static function nomeFileSicuro(string $nome, bool $ascii = false, int $max = self::MAX_NOME_FILE): string
    {
        // Via l'eventuale percorso: si tiene solo il nome finale.
        // L'ordine conta: prima si normalizzano i separatori e si prende il
        // basename, poi si sanifica. Sostituendo i separatori con spazi prima
        // di basename(), un "../../etc/passwd" diventerebbe un unico nome
        // sgangherato invece del semplice "passwd".
        $nome = str_replace('\\', '/', $nome);
        $nome = rtrim($nome, '/');
        $nome = basename($nome);

        $estensione = '';
        $punto      = strrpos($nome, '.');
        if ($punto !== false && $punto > 0) {
            $estensione = strtolower(substr($nome, $punto + 1));
            $estensione = preg_replace('/[^a-z0-9]/', '', $estensione) ?? '';
            $nome       = substr($nome, 0, $punto);
        }

        if ($ascii) {
            $nome = self::traslittera($nome);
            $nome = preg_replace('/[^A-Za-z0-9 _.\-()\[\]]/u', '', $nome) ?? $nome;
        } else {
            $nome = str_replace(self::VIETATI_FILE, '', $nome);
            // Caratteri di controllo: invisibili e pericolosi in un nome file.
            $nome = preg_replace('/[\x00-\x1F\x7F]/u', '', $nome) ?? $nome;
        }

        $nome = preg_replace('/\s+/u', ' ', $nome) ?? $nome;
        $nome = trim($nome, " .\t\n\r\0\x0B");

        if (mb_strlen($nome, 'UTF-8') > $max) {
            $nome = trim(mb_substr($nome, 0, $max, 'UTF-8'));
        }

        if ($nome === '') {
            $nome = 'senza-nome';
        }

        // Nomi riservati da Windows: aggiungere un underscore li rende validi
        // senza stravolgere il nome scelto dall'utente.
        $riservati = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4',
                      'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2',
                      'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        if (in_array(strtoupper($nome), $riservati, true)) {
            $nome .= '_';
        }

        return $estensione === '' ? $nome : $nome . '.' . $estensione;
    }

    /**
     * Estratto di un testo lungo, calcolato a runtime.
     *
     * I testi non hanno limiti di lunghezza (D6) e non vengono mai troncati su
     * disco: gli elenchi mostrano questo estratto, il file conserva l'integrale.
     */
    public static function estratto(?string $valore, int $lunghezza = 180): string
    {
        $testo = trim(preg_replace('/\s+/u', ' ', (string) $valore) ?? '');
        if ($testo === '' || mb_strlen($testo, 'UTF-8') <= $lunghezza) {
            return $testo;
        }

        $tagliato = mb_substr($testo, 0, $lunghezza, 'UTF-8');
        $spazio   = mb_strrpos($tagliato, ' ', 0, 'UTF-8');
        if ($spazio !== false && $spazio > (int) ($lunghezza * 0.6)) {
            $tagliato = mb_substr($tagliato, 0, $spazio, 'UTF-8');
        }

        return $tagliato . '…';
    }

    /**
     * Formatta una dimensione in byte in forma leggibile.
     */
    public static function dimensione(int $byte): string
    {
        $unita = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        $v     = (float) $byte;
        while ($v >= 1024 && $i < count($unita) - 1) {
            $v /= 1024;
            $i++;
        }
        return ($i === 0 ? (string) (int) $v : number_format($v, 1, ',', '.')) . ' ' . $unita[$i];
    }

    /**
     * Converte una dimensione in stile php.ini ("32M", "1G") in byte.
     * Restituisce -1 per i valori illimitati.
     */
    public static function aByte(string $valore): int
    {
        $valore = trim($valore);
        if ($valore === '' ) {
            return 0;
        }
        if ($valore === '-1') {
            return -1;
        }

        $unita  = strtolower(substr($valore, -1));
        $numero = (int) $valore;

        return match ($unita) {
            'g'     => $numero * 1024 * 1024 * 1024,
            'm'     => $numero * 1024 * 1024,
            'k'     => $numero * 1024,
            default => $numero,
        };
    }
}
