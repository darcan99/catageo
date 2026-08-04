<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Config.php
 *  Descrizione ..: Lettura di config.xml e accesso ai parametri di
 *                  configurazione tramite percorsi puntati ("mappa.provider").
 *                  Il file viene letto una sola volta per richiesta.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Config
{
    private static ?DOMDocument $doc = null;
    private static string $percorso = '';

    /** Valori gia risolti, per non rifare XPath sulle chiavi piu usate. */
    private static array $cache = [];

    /**
     * Carica config.xml. Va chiamata una sola volta, da bootstrap.php.
     *
     * @throws XmlEccezione se il file manca o e malformato
     */
    public static function carica(string $percorso): void
    {
        self::$percorso = $percorso;
        self::$doc      = Xml::carica($percorso);
        self::$cache    = [];
    }

    /** True se la configurazione e stata caricata. */
    public static function caricata(): bool
    {
        return self::$doc !== null;
    }

    /** Percorso del file di configurazione in uso. */
    public static function percorso(): string
    {
        return self::$percorso;
    }

    /**
     * Legge un valore testuale.
     *
     * @param string $chiave percorso puntato a partire dalla radice,
     *                       es. "sistema.fusoOrario" oppure "mappa.provider"
     */
    public static function testo(string $chiave, string $default = ''): string
    {
        if (array_key_exists($chiave, self::$cache)) {
            return (string) self::$cache[$chiave];
        }

        $doc = self::documento();
        $valore = Xml::testo($doc, self::xpath($chiave), $default);
        self::$cache[$chiave] = $valore;

        return $valore;
    }

    /** Legge un valore intero. */
    public static function intero(string $chiave, int $default = 0): int
    {
        $valore = self::testo($chiave, '');
        return $valore === '' ? $default : (int) $valore;
    }

    /** Legge un valore in virgola mobile (coordinate, opacita). */
    public static function decimale(string $chiave, float $default = 0.0): float
    {
        $valore = self::testo($chiave, '');
        return $valore === '' ? $default : (float) str_replace(',', '.', $valore);
    }

    /** Legge un valore booleano (1/0, true/false, si/no). */
    public static function booleano(string $chiave, bool $default = false): bool
    {
        $valore = strtolower(self::testo($chiave, ''));
        if ($valore === '') {
            return $default;
        }
        return in_array($valore, ['1', 'true', 'si', 'sì', 'yes'], true);
    }

    /**
     * Legge un attributo di un elemento.
     *
     * @param string $chiave percorso puntato dell'elemento
     */
    public static function attributo(string $chiave, string $attributo, string $default = ''): string
    {
        $nodo = Xml::primo(self::documento(), self::xpath($chiave));
        if (!$nodo instanceof DOMElement) {
            return $default;
        }
        $valore = $nodo->getAttribute($attributo);
        return $valore === '' ? $default : $valore;
    }

    /**
     * Elenco di elementi sotto una chiave, come array di DOMElement.
     *
     * @return DOMElement[]
     */
    public static function elementi(string $chiave): array
    {
        return Xml::elenco(self::documento(), self::xpath($chiave));
    }

    /**
     * Estensioni ammesse per una sezione di upload.
     *
     * @return string[] estensioni in minuscolo, senza punto
     */
    public static function estensioniAmmesse(string $sezione): array
    {
        $nodi = Xml::elenco(self::documento(), "/catageo/upload/estensioni[@sezione='" . self::escapaXpath($sezione) . "']");
        if ($nodi === []) {
            return [];
        }

        $elenco = array_map(
            static fn (string $v): string => strtolower(trim($v)),
            explode(',', $nodi[0]->textContent)
        );

        return array_values(array_filter($elenco, static fn (string $v): bool => $v !== ''));
    }

    /**
     * Dimensione massima di upload ammessa, in byte: il minimo fra quanto
     * dichiarato in configurazione e i limiti effettivi di PHP. Dichiarare
     * 32 MB con un post_max_size da 8 MB produrrebbe solo upload che falliscono
     * senza spiegazione.
     */
    public static function dimensioneMaxUpload(): int
    {
        $configurata = self::intero('upload.dimensioneMax', 32) * 1024 * 1024;

        $limiti = [$configurata];
        foreach (['upload_max_filesize', 'post_max_size'] as $direttiva) {
            $byte = Testo::aByte((string) ini_get($direttiva));
            if ($byte > 0) {
                $limiti[] = $byte;
            }
        }

        return min($limiti);
    }

    /** Documento caricato, con errore esplicito se manca l'inizializzazione. */
    private static function documento(): DOMDocument
    {
        if (self::$doc === null) {
            throw new RuntimeException('Configurazione non caricata: chiamare Config::carica() da bootstrap.php');
        }
        return self::$doc;
    }

    /**
     * Traduce un percorso puntato in espressione XPath assoluta.
     * Le chiavi provengono dal codice, non dall'utente, ma la validazione
     * resta comunque rigida per non lasciare aperta un'iniezione XPath.
     */
    private static function xpath(string $chiave): string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.]*$/', $chiave)) {
            throw new InvalidArgumentException("Chiave di configurazione non valida: {$chiave}");
        }
        return '/catageo/' . str_replace('.', '/', $chiave);
    }

    /** Neutralizza gli apici in un valore usato dentro un'espressione XPath. */
    private static function escapaXpath(string $valore): string
    {
        return str_replace("'", '', $valore);
    }
}
