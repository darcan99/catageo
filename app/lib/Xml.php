<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Xml.php
 *  Descrizione ..: Accesso ai file XML dell'archivio. Nessun'altra parte
 *                  dell'applicativo apre o scrive XML direttamente: qui stanno
 *                  il caricamento sicuro (senza entita esterne), la validazione
 *                  XSD, la scrittura atomica e il lock esclusivo.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Xml
{
    /**
     * Opzioni di libxml usate per ogni caricamento.
     *
     * LIBXML_NONET  vieta il recupero di risorse via rete.
     * LIBXML_NOENT  non sostituisce le entita.
     * LIBXML_NOCDATA NON viene usata: i testi lunghi sono in CDATA (D6) e
     *                vanno conservati come tali in scrittura.
     *
     * Nota: libxml_disable_entity_loader() non viene chiamata perche deprecata
     * da PHP 8.0; dalla versione 2.9 di libxml il caricamento di entita esterne
     * e disattivato per default e LIBXML_NONET rende esplicita la scelta.
     */
    private const OPZIONI = LIBXML_NONET | LIBXML_NOENT | LIBXML_COMPACT;

    /**
     * Carica un file XML, opzionalmente validandolo contro uno schema.
     *
     * @param  string      $percorso  percorso assoluto del file
     * @param  string|null $xsd       percorso dello schema, o null
     * @throws XmlEccezione se il file manca, e malformato o non valido
     */
    public static function carica(string $percorso, ?string $xsd = null): DOMDocument
    {
        if (!is_file($percorso)) {
            throw new XmlEccezione("File XML non trovato: {$percorso}");
        }

        $contenuto = file_get_contents($percorso);
        if ($contenuto === false) {
            throw new XmlEccezione("File XML non leggibile: {$percorso}");
        }

        return self::daStringa($contenuto, $xsd, $percorso);
    }

    /**
     * Costruisce un DOMDocument da una stringa XML.
     *
     * @param string      $xml
     * @param string|null $xsd
     * @param string      $origine  solo per i messaggi d'errore
     */
    public static function daStringa(string $xml, ?string $xsd = null, string $origine = '(stringa)'): DOMDocument
    {
        $precedente = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = true;

        $ok = $doc->loadXML($xml, self::OPZIONI);
        if (!$ok) {
            $errori = self::raccogliErrori();
            libxml_use_internal_errors($precedente);
            throw new XmlEccezione("XML malformato in {$origine}: " . implode('; ', $errori));
        }

        libxml_use_internal_errors($precedente);

        if ($xsd !== null) {
            $errori = self::valida($doc, $xsd);
            if ($errori !== []) {
                throw new XmlEccezione("XML non valido in {$origine}: " . implode('; ', $errori));
            }
        }

        return $doc;
    }

    /**
     * Crea un documento nuovo con l'elemento radice indicato.
     *
     * @param string               $radice
     * @param array<string,string> $attributi
     */
    public static function nuovo(string $radice, array $attributi = []): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = true;

        $el = $doc->createElement($radice);
        foreach ($attributi as $nome => $valore) {
            $el->setAttribute($nome, $valore);
        }
        $doc->appendChild($el);

        return $doc;
    }

    /**
     * Valida un documento contro uno schema XSD.
     *
     * @return string[] elenco degli errori; vuoto se il documento e valido
     */
    public static function valida(DOMDocument $doc, string $xsd): array
    {
        if (!is_file($xsd)) {
            // Uno schema mancante non deve bloccare la scrittura dei dati:
            // viene segnalato come errore di configurazione, non di contenuto.
            return ["schema XSD non trovato: {$xsd}"];
        }

        $precedente = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $ok     = $doc->schemaValidate($xsd);
        $errori = $ok ? [] : self::raccogliErrori();

        libxml_use_internal_errors($precedente);

        return $errori;
    }

    /**
     * Scrive il documento in modo atomico: file temporaneo nella stessa
     * directory, poi rename(). Un'interruzione a meta non lascia mai un XML
     * troncato al posto di quello buono.
     *
     * @param  string|null $xsd  se indicato, il documento viene validato prima
     * @throws XmlEccezione
     */
    public static function salva(DOMDocument $doc, string $percorso, ?string $xsd = null): void
    {
        if ($xsd !== null) {
            $errori = self::valida($doc, $xsd);
            if ($errori !== []) {
                throw new XmlEccezione('Salvataggio annullato, XML non valido: ' . implode('; ', $errori));
            }
        }

        $cartella = dirname($percorso);
        if (!is_dir($cartella) && !@mkdir($cartella, 0775, true) && !is_dir($cartella)) {
            throw new XmlEccezione("Impossibile creare la cartella: {$cartella}");
        }
        if (!is_writable($cartella)) {
            throw new XmlEccezione("Cartella non scrivibile: {$cartella}");
        }

        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new XmlEccezione("Serializzazione XML fallita per {$percorso}");
        }

        $temporaneo = $percorso . '.' . getmypid() . '.tmp';
        if (file_put_contents($temporaneo, $xml, LOCK_EX) === false) {
            throw new XmlEccezione("Scrittura del temporaneo fallita: {$temporaneo}");
        }

        // PHP su Windows sostituisce il file esistente; su alcuni filesystem
        // di rete il rename puo comunque fallire, quindi si tenta la rimozione
        // preventiva soltanto come ripiego.
        if (!@rename($temporaneo, $percorso)) {
            @unlink($percorso);
            if (!@rename($temporaneo, $percorso)) {
                @unlink($temporaneo);
                throw new XmlEccezione("Sostituzione atomica fallita per {$percorso}");
            }
        }
    }

    /**
     * Esegue una funzione tenendo un lock esclusivo su un file di lock
     * affiancato al percorso indicato. Serve per le sequenze
     * leggi-modifica-scrivi (per esempio l'incremento di un contatore).
     *
     * @template T
     * @param  callable():T $azione
     * @return T
     * @throws XmlEccezione se il lock non si ottiene
     */
    public static function conLock(string $percorso, callable $azione)
    {
        $cartella = dirname($percorso);
        if (!is_dir($cartella) && !@mkdir($cartella, 0775, true) && !is_dir($cartella)) {
            throw new XmlEccezione("Impossibile creare la cartella per il lock: {$cartella}");
        }

        $fileLock = $percorso . '.lock';
        $handle   = @fopen($fileLock, 'c');
        if ($handle === false) {
            throw new XmlEccezione("Impossibile aprire il file di lock: {$fileLock}");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new XmlEccezione("Lock esclusivo non ottenuto su {$fileLock}");
            }
            return $azione();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    // ------------------------------------------------------------------ lettura

    /** Restituisce il testo del primo nodo che soddisfa l'espressione XPath. */
    public static function testo(DOMNode $contesto, string $xpath, string $default = ''): string
    {
        $nodo = self::primo($contesto, $xpath);
        return $nodo === null ? $default : trim($nodo->textContent);
    }

    /** Come testo(), ma restituisce un intero. */
    public static function intero(DOMNode $contesto, string $xpath, int $default = 0): int
    {
        $valore = self::testo($contesto, $xpath, '');
        return $valore === '' ? $default : (int) $valore;
    }

    /** Come testo(), ma interpreta 1/0, true/false, si/no. */
    public static function booleano(DOMNode $contesto, string $xpath, bool $default = false): bool
    {
        $valore = strtolower(self::testo($contesto, $xpath, ''));
        if ($valore === '') {
            return $default;
        }
        return in_array($valore, ['1', 'true', 'si', 'sì', 'yes'], true);
    }

    /** Primo nodo che soddisfa l'espressione, o null. */
    public static function primo(DOMNode $contesto, string $xpath): ?DOMNode
    {
        $lista = self::interroga($contesto, $xpath);
        return $lista->length > 0 ? $lista->item(0) : null;
    }

    /**
     * Elenco di elementi che soddisfano l'espressione.
     *
     * @return DOMElement[]
     */
    public static function elenco(DOMNode $contesto, string $xpath): array
    {
        $risultato = [];
        foreach (self::interroga($contesto, $xpath) as $nodo) {
            if ($nodo instanceof DOMElement) {
                $risultato[] = $nodo;
            }
        }
        return $risultato;
    }

    /** Esegue l'espressione XPath nel contesto dato. */
    private static function interroga(DOMNode $contesto, string $xpath): DOMNodeList
    {
        $doc = $contesto instanceof DOMDocument ? $contesto : $contesto->ownerDocument;
        if ($doc === null) {
            throw new XmlEccezione('Nodo senza documento di appartenenza');
        }

        $motore = new DOMXPath($doc);
        $lista  = $motore->query($xpath, $contesto instanceof DOMDocument ? null : $contesto);
        if ($lista === false) {
            throw new XmlEccezione("Espressione XPath non valida: {$xpath}");
        }
        return $lista;
    }

    // ----------------------------------------------------------------- scrittura

    /**
     * Imposta (creando se assente) un elemento figlio con il valore dato.
     *
     * @param bool $cdata racchiude il valore in CDATA: da usare per i testi
     *                    lunghi e liberi, che non hanno limiti di lunghezza (D6)
     */
    public static function imposta(DOMElement $padre, string $nome, ?string $valore, bool $cdata = false): DOMElement
    {
        $doc = $padre->ownerDocument;
        if ($doc === null) {
            throw new XmlEccezione('Elemento senza documento di appartenenza');
        }

        $elemento = null;
        foreach ($padre->childNodes as $figlio) {
            if ($figlio instanceof DOMElement && $figlio->nodeName === $nome) {
                $elemento = $figlio;
                break;
            }
        }

        if ($elemento === null) {
            $elemento = $doc->createElement($nome);
            $padre->appendChild($elemento);
        }

        while ($elemento->firstChild !== null) {
            $elemento->removeChild($elemento->firstChild);
        }

        if ($valore !== null && $valore !== '') {
            $elemento->appendChild($cdata
                ? $doc->createCDATASection($valore)
                : $doc->createTextNode($valore));
        }

        return $elemento;
    }

    /**
     * Aggiunge un nuovo elemento figlio (senza sostituire gli omonimi).
     *
     * @param array<string,string> $attributi
     */
    public static function aggiungi(DOMElement $padre, string $nome, ?string $valore = null, array $attributi = [], bool $cdata = false): DOMElement
    {
        $doc = $padre->ownerDocument;
        if ($doc === null) {
            throw new XmlEccezione('Elemento senza documento di appartenenza');
        }

        $elemento = $doc->createElement($nome);
        foreach ($attributi as $chiave => $val) {
            $elemento->setAttribute($chiave, $val);
        }
        if ($valore !== null && $valore !== '') {
            $elemento->appendChild($cdata
                ? $doc->createCDATASection($valore)
                : $doc->createTextNode($valore));
        }
        $padre->appendChild($elemento);

        return $elemento;
    }

    /** Rimuove un elemento dal proprio genitore. */
    public static function rimuovi(DOMElement $elemento): void
    {
        $elemento->parentNode?->removeChild($elemento);
    }

    /**
     * Raccoglie e formatta gli errori accumulati da libxml.
     *
     * @return string[]
     */
    private static function raccogliErrori(): array
    {
        $messaggi = [];
        foreach (libxml_get_errors() as $errore) {
            $messaggi[] = sprintf('riga %d: %s', $errore->line, trim($errore->message));
        }
        libxml_clear_errors();
        return $messaggi === [] ? ['errore non specificato da libxml'] : $messaggi;
    }
}
