<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Anagrafica.php
 *  Descrizione ..: Base comune alle anagrafiche a elenco piatto (gruppi
 *                  speleologici, esploratori, periodi storici): un file XML con
 *                  N elementi identificati da un attributo id.
 *
 *                  Qui stanno le parti identiche per tutte: apertura del file,
 *                  lock, scrittura atomica, generazione dell'identificativo,
 *                  integrita referenziale in cancellazione. Le sottoclassi
 *                  descrivono soltanto il proprio contenuto.
 *
 *                  Le anagrafiche gerarchiche (tipologie, grandezze) NON usano
 *                  questa base: la loro struttura ad albero richiede una
 *                  gestione diversa, e forzarla qui avrebbe reso questa classe
 *                  piu complicata di entrambe le implementazioni separate.
 *  Versione .....: 0.12.1
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.1 2026-08-06  D.Candela  Avviso nel log quando lo schema dichiarato
 *                                manca: prima si scriveva senza validazione
 *                                e senza dirlo.
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

abstract class Anagrafica
{
    // ------------------------------------------------------- da definire nelle sottoclassi

    /** Nome del file nell'archivio, es. "gruppi_speleologici.xml". */
    abstract protected static function nomeFile(): string;

    /** Nome dell'elemento radice, es. "gruppi". */
    abstract protected static function nomeRadice(): string;

    /** Nome dell'elemento di voce, es. "gruppo". */
    abstract protected static function nomeElemento(): string;

    /** Prefisso degli identificativi generati, es. "G" per G001. */
    abstract protected static function prefissoId(): string;

    /**
     * Nome dell'attributo che identifica la voce.
     *
     * Quasi tutte le anagrafiche usano "id" con un progressivo generato; quelle
     * a vocabolario controllato (i periodi storici) usano un codice parlante
     * scelto da chi le compila, perche un codice come ROM-IMP e leggibile nel
     * file e nei riferimenti, mentre un P004 non direbbe nulla.
     */
    protected static function nomeAttributoId(): string
    {
        return 'id';
    }

    /**
     * Identificativo da assegnare a una voce nuova.
     * Per default e un progressivo; le anagrafiche a codice parlante lo
     * ricavano dai dati inseriti.
     *
     * @param array<string,mixed> $dati
     */
    protected static function generaId(DOMDocument $doc, array $dati): string
    {
        return static::prossimoId($doc);
    }

    /** Nome dello schema XSD, oppure null se la voce non e validata. */
    abstract protected static function nomeXsd(): ?string;

    /**
     * Converte un nodo XML in array.
     *
     * @return array<string,mixed>
     */
    abstract protected static function daNodo(DOMElement $nodo): array;

    /**
     * Scrive i dati sul nodo XML.
     *
     * @param array<string,mixed> $dati
     */
    abstract protected static function scriviNodo(DOMElement $nodo, array $dati): void;

    /**
     * Verifica i dati prima della scrittura.
     *
     * @param  array<string,mixed> $dati
     * @param  string|null         $idEsistente id in caso di modifica, null in creazione
     * @throws AnagraficaEccezione
     */
    abstract protected static function valida(array $dati, ?string $idEsistente): void;

    /**
     * Etichetta leggibile di una voce, usata negli elenchi e nelle tendine.
     *
     * @param array<string,mixed> $voce
     */
    abstract public static function etichetta(array $voce): string;

    /**
     * Riferimenti alla voce presenti altrove nell'archivio: se non e vuoto la
     * cancellazione viene rifiutata.
     *
     * @return array<string,int> descrizione del riferimento => conteggio
     */
    public static function usi(string $id): array
    {
        return [];
    }

    // --------------------------------------------------------------- interfaccia pubblica

    /** Percorso del file di anagrafica. */
    public static function percorso(): string
    {
        return Percorsi::dati(static::nomeFile());
    }

    /** Crea il file vuoto se assente. */
    public static function assicuraFile(): void
    {
        $percorso = static::percorso();
        if (is_file($percorso)) {
            return;
        }
        Percorsi::assicuraCartella(dirname($percorso));
        Xml::salva(Xml::nuovo(static::nomeRadice(), ['versioneSchema' => '1.0']), $percorso);
    }

    /**
     * Elenco delle voci, ordinato.
     *
     * @param  bool $soloAttive esclude le voci disattivate
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(bool $soloAttive = false): array
    {
        // Anche la sola lettura crea il file se manca: le anagrafiche a
        // vocabolario nascono con un contenuto predefinito (§6.4), che
        // altrimenti non comparirebbe mai finche non si scrive qualcosa.
        // Un archivio non scrivibile non deve pero impedire la consultazione:
        // in quel caso si prosegue con l'elenco vuoto e si annota l'avviso.
        try {
            static::assicuraFile();
        } catch (Throwable $e) {
            Log::errore(
                'Anagrafica non inizializzabile (' . static::nomeFile() . '): ' . $e->getMessage(),
                'avviso'
            );
        }

        $percorso = static::percorso();
        if (!is_file($percorso)) {
            return [];
        }

        $doc     = Xml::carica($percorso);
        $risultato = [];

        foreach (Xml::elenco($doc, '/' . static::nomeRadice() . '/' . static::nomeElemento()) as $nodo) {
            $voce = static::daNodo($nodo);
            if ($soloAttive && array_key_exists('attivo', $voce) && !$voce['attivo']) {
                continue;
            }
            $risultato[] = $voce;
        }

        static::ordina($risultato);

        return $risultato;
    }

    /**
     * Cerca una voce per identificativo.
     *
     * @return array<string,mixed>|null
     */
    public static function trova(string $id): ?array
    {
        foreach (static::elenco() as $voce) {
            if ((string) $voce['id'] === $id) {
                return $voce;
            }
        }
        return null;
    }

    /**
     * Etichetta di una voce dato l'id, con ripiego sull'id stesso se la voce
     * non esiste piu: un riferimento rotto va mostrato, non nascosto.
     */
    public static function etichettaPerId(string $id): string
    {
        if ($id === '') {
            return '';
        }
        $voce = static::trova($id);
        return $voce === null ? $id . ' (non trovato)' : static::etichetta($voce);
    }

    /**
     * Crea una voce.
     *
     * @param  array<string,mixed> $dati
     * @return string identificativo assegnato
     * @throws AnagraficaEccezione
     */
    public static function crea(array $dati): string
    {
        static::valida($dati, null);

        return Xml::conLock(static::percorso(), static function () use ($dati): string {
            $doc    = static::documento();
            $radice = $doc->documentElement;
            if ($radice === null) {
                throw new AnagraficaEccezione('File di anagrafica senza elemento radice.');
            }

            $id   = static::generaId($doc, $dati);
            $nodo = Xml::aggiungi($radice, static::nomeElemento(), null, [static::nomeAttributoId() => $id]);
            static::scriviNodo($nodo, $dati);

            static::salva($doc);

            return $id;
        });
    }

    /**
     * Aggiorna una voce.
     *
     * @param  array<string,mixed> $dati
     * @throws AnagraficaEccezione
     */
    public static function aggiorna(string $id, array $dati): void
    {
        if (static::trova($id) === null) {
            throw new AnagraficaEccezione('Voce non trovata.');
        }
        static::valida($dati, $id);

        Xml::conLock(static::percorso(), static function () use ($id, $dati): void {
            $doc  = static::documento();
            $nodo = static::nodoPerId($doc, $id);
            static::scriviNodo($nodo, $dati);
            static::salva($doc);
        });
    }

    /**
     * Elimina una voce, se non e referenziata da nessuna parte.
     *
     * @throws AnagraficaEccezione
     */
    public static function elimina(string $id): void
    {
        $voce = static::trova($id);
        if ($voce === null) {
            throw new AnagraficaEccezione('Voce non trovata.');
        }

        $usi = static::usi($id);
        if ($usi !== []) {
            $dettaglio = [];
            foreach ($usi as $dove => $quanti) {
                $dettaglio[] = $quanti . ' ' . $dove;
            }
            throw new AnagraficaEccezione(
                'Cancellazione rifiutata: la voce e referenziata da ' . implode(', ', $dettaglio) . '. '
                . 'Se non serve più, disattivarla: resta nei riferimenti storici senza comparire nelle scelte.'
            );
        }

        Xml::conLock(static::percorso(), static function () use ($id): void {
            $doc = static::documento();
            Xml::rimuovi(static::nodoPerId($doc, $id));
            static::salva($doc);
        });
    }

    /**
     * Attiva o disattiva una voce, dove il concetto ha senso.
     *
     * @throws AnagraficaEccezione
     */
    public static function impostaAttivo(string $id, bool $attivo): void
    {
        Xml::conLock(static::percorso(), static function () use ($id, $attivo): void {
            $doc  = static::documento();
            $nodo = static::nodoPerId($doc, $id);
            Xml::imposta($nodo, 'attivo', $attivo ? '1' : '0');
            static::salva($doc);
        });
    }

    /** Numero di voci presenti. */
    public static function conta(bool $soloAttive = false): int
    {
        return count(static::elenco($soloAttive));
    }

    // --------------------------------------------------------------------------- interni

    /**
     * Ordinamento predefinito: per etichetta, con le voci disattivate in fondo.
     *
     * @param array<int,array<string,mixed>> $elenco
     */
    protected static function ordina(array &$elenco): void
    {
        usort($elenco, static function (array $a, array $b): int {
            $aAttivo = !array_key_exists('attivo', $a) || $a['attivo'];
            $bAttivo = !array_key_exists('attivo', $b) || $b['attivo'];
            if ($aAttivo !== $bAttivo) {
                return $aAttivo ? -1 : 1;
            }
            return strcasecmp(static::etichetta($a), static::etichetta($b));
        });
    }

    /** Schemi gia segnalati come mancanti, per non ripetere l'avviso. */
    private static array $schemiMancantiSegnalati = [];

    /**
     * Percorso dello schema XSD, se presente sul disco.
     *
     * Uno schema dichiarato ma assente non blocca la scrittura: i dati valgono
     * piu della validazione, e un'installazione a cui manca un file di schema
     * deve restare utilizzabile. Il caso viene pero annotato nel log, perche
     * altrimenti l'applicativo sembrerebbe validare senza farlo, ed e la
     * situazione peggiore: si scrive con la fiducia di un controllo che non c'e.
     *
     * L'avviso e registrato una volta sola per schema e per richiesta: xsd() e
     * chiamata a ogni salvataggio e il log si riempirebbe di righe identiche.
     */
    protected static function xsd(): ?string
    {
        $nome = static::nomeXsd();
        if ($nome === null) {
            return null; // anagrafica dichiaratamente non validata
        }

        $percorso = Percorsi::schema($nome);
        if (is_file($percorso)) {
            return $percorso;
        }

        if (!isset(self::$schemiMancantiSegnalati[$nome])) {
            self::$schemiMancantiSegnalati[$nome] = true;
            Log::errore(
                'Schema XSD dichiarato ma assente: ' . $nome . ' (' . static::nomeFile() . '). '
                . 'I dati vengono scritti senza validazione.',
                'avviso'
            );
        }

        return null;
    }

    /** Carica il documento, creandolo se assente. */
    protected static function documento(): DOMDocument
    {
        static::assicuraFile();
        return Xml::carica(static::percorso());
    }

    /** Salva il documento con validazione. */
    protected static function salva(DOMDocument $doc): void
    {
        Xml::salva($doc, static::percorso(), static::xsd());
    }

    /**
     * Nodo con l'identificativo indicato.
     *
     * @throws AnagraficaEccezione
     */
    protected static function nodoPerId(DOMDocument $doc, string $id): DOMElement
    {
        foreach (Xml::elenco($doc, '/' . static::nomeRadice() . '/' . static::nomeElemento()) as $nodo) {
            if ($nodo->getAttribute(static::nomeAttributoId()) === $id) {
                return $nodo;
            }
        }
        throw new AnagraficaEccezione('Voce non trovata nell\'anagrafica.');
    }

    /**
     * Prossimo identificativo disponibile.
     *
     * Il padding a 3 cifre e una soglia minima, non un tetto: oltre la
     * novecentonovantanovesima voce la numerazione continua a 4 cifre, come per
     * i codici catastali (D7).
     */
    protected static function prossimoId(DOMDocument $doc): string
    {
        $massimo = 0;
        foreach (Xml::elenco($doc, '/' . static::nomeRadice() . '/' . static::nomeElemento()) as $nodo) {
            $numero  = (int) preg_replace('/\D/', '', $nodo->getAttribute(static::nomeAttributoId()));
            $massimo = max($massimo, $numero);
        }
        return static::prefissoId() . str_pad((string) ($massimo + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Conta quante righe dell'indice degli ipogei hanno un dato valore in una
     * colonna. Serve per l'integrita referenziale verso gli ipogei, che
     * arriveranno in fase 3: finche l'indice non esiste il conteggio e zero.
     */
    protected static function usiNellIndice(string $colonna, string $valore): int
    {
        $percorso = Percorsi::indice('ipogei.csv');
        if (!is_file($percorso) || $valore === '') {
            return 0;
        }

        $conteggio = 0;
        Csv::leggi($percorso, static function (array $riga) use ($colonna, $valore, &$conteggio): void {
            if (($riga[$colonna] ?? '') === $valore) {
                $conteggio++;
            }
        });

        return $conteggio;
    }
}
