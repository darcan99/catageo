<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Tipologie.php
 *  Descrizione ..: Tassonomia degli ipogei (dati/tipologie.xml): tre livelli
 *                  annidati, natura > tipologia > sottotipologia.
 *
 *                  Non estende Anagrafica: quella base gestisce elenchi piatti
 *                  con id progressivo, qui servono un albero e codici parlanti.
 *                  L'albero viene appiattito in memoria una volta per richiesta,
 *                  cosi le tendine e le etichette non ricaricano il file.
 *  Versione .....: 1.5.1
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.5.1  2026-08-08  D.Candela  applicaIconePredefinite(): porta i simboli
 *                                nei vocabolari gia esistenti, che un
 *                                aggiornamento del codice non tocca.
 *  1.4.0  2026-08-08  D.Candela  Attributo «icona» con ereditarieta lungo
 *                                l'albero, per il simbolo in mappa.
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

final class Tipologie
{
    /** Livelli dell'albero, dal piu generale al piu specifico. */
    public const LIVELLI = ['natura', 'tipologia', 'sotto'];

    /** Etichette dei livelli per l'interfaccia. */
    public const ETICHETTE_LIVELLO = [
        'natura'    => 'Natura',
        'tipologia' => 'Tipologia',
        'sotto'     => 'Sottotipologia',
    ];

    /** Cache dell'elenco appiattito, per richiesta. */
    private static ?array $cache = null;

    /** Percorso del file. */
    public static function percorso(): string
    {
        return Percorsi::dati('tipologie.xml');
    }

    /** Percorso dello schema, se presente. */
    private static function xsd(): ?string
    {
        $p = Percorsi::schema('tipologie.xsd');
        return is_file($p) ? $p : null;
    }

    /**
     * Crea il file col vocabolario predefinito se assente.
     *
     * La creazione pigra e voluta: un'installazione fatta prima che questa
     * anagrafica esistesse si allinea da sola al primo accesso, senza bisogno
     * di rieseguire l'installer su un archivio che contiene dati.
     */
    public static function assicuraFile(): void
    {
        $percorso = self::percorso();
        if (is_file($percorso)) {
            return;
        }
        Percorsi::assicuraCartella(dirname($percorso));
        Xml::salva(VocabolariPredefiniti::tipologie(), $percorso, self::xsd());
        self::$cache = null;
    }

    /**
     * Elenco appiattito, in ordine di albero.
     *
     * @return array<int,array{codice:string,nome:string,livello:string,padre:string,percorso:string,attivo:bool,note:string}>
     */
    public static function elenco(bool $soloAttive = false): array
    {
        if (self::$cache === null) {
            self::assicuraFile();

            $doc  = Xml::carica(self::percorso());
            $voci = [];

            foreach (Xml::elenco($doc, '/tipologie/natura') as $natura) {
                self::aggiungiVoce($voci, $natura, 'natura', '', '');
                $percorsoNatura = $natura->getAttribute('nome');

                foreach (Xml::elenco($natura, 'tipologia') as $tipologia) {
                    self::aggiungiVoce($voci, $tipologia, 'tipologia', $natura->getAttribute('codice'), $percorsoNatura);
                    $percorsoTipologia = $percorsoNatura . ' › ' . $tipologia->getAttribute('nome');

                    foreach (Xml::elenco($tipologia, 'sotto') as $sotto) {
                        self::aggiungiVoce($voci, $sotto, 'sotto', $tipologia->getAttribute('codice'), $percorsoTipologia);
                    }
                }
            }

            self::$cache = $voci;
        }

        if (!$soloAttive) {
            return self::$cache;
        }

        return array_values(array_filter(self::$cache, static fn (array $v): bool => $v['attivo']));
    }

    /**
     * Voci di un livello, opzionalmente figlie di un codice padre.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function perLivello(string $livello, string $padre = '', bool $soloAttive = true): array
    {
        return array_values(array_filter(
            self::elenco($soloAttive),
            static fn (array $v): bool => $v['livello'] === $livello && ($padre === '' || $v['padre'] === $padre)
        ));
    }

    /**
     * Cerca una voce per codice.
     *
     * @return array<string,mixed>|null
     */
    public static function trova(string $codice): ?array
    {
        foreach (self::elenco() as $voce) {
            if ($voce['codice'] === $codice) {
                return $voce;
            }
        }
        return null;
    }

    /** Nome della voce, o il codice stesso se il riferimento e rotto. */
    public static function nome(string $codice): string
    {
        if ($codice === '') {
            return '';
        }
        $voce = self::trova($codice);
        return $voce === null ? $codice . ' (non trovato)' : $voce['nome'];
    }

    /** Percorso completo, es. "Cavita artificiale › Opere idrauliche › Cunicolo drenante". */
    public static function percorsoLeggibile(string $codice): string
    {
        $voce = self::trova($codice);
        if ($voce === null) {
            return $codice === '' ? '' : $codice . ' (non trovato)';
        }
        return $voce['percorso'] === '' ? $voce['nome'] : $voce['percorso'] . ' › ' . $voce['nome'];
    }

    /**
     * Aggiunge una voce all'albero.
     *
     * @throws AnagraficaEccezione
     */
    public static function crea(string $livello, string $padre, string $codice, string $nome, string $note = '', string $icona = ''): string
    {
        $codice = self::normalizzaCodice($codice);
        $nome   = trim($nome);

        self::valida($livello, $padre, $codice, $nome, null);

        $icona = self::normalizzaIcona($icona);

        Xml::conLock(self::percorso(), static function () use ($livello, $padre, $codice, $nome, $note, $icona): void {
            self::assicuraFile();
            $doc = Xml::carica(self::percorso());

            if ($livello === 'natura') {
                $contenitore = $doc->documentElement;
            } else {
                $contenitore = self::nodoPerCodice($doc, $padre);
            }
            if ($contenitore === null) {
                throw new AnagraficaEccezione('Voce superiore non trovata.');
            }

            $attributi = ['codice' => $codice, 'nome' => $nome, 'attivo' => '1'];
            if ($icona !== '') {
                $attributi['icona'] = $icona;
            }
            $nodo = Xml::aggiungi($contenitore, $livello, null, $attributi);
            if ($note !== '') {
                Xml::imposta($nodo, 'note', $note, true);
            }

            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;

        return $codice;
    }

    /**
     * Aggiorna nome, note e stato di una voce. Il codice non e modificabile:
     * e il riferimento memorizzato nelle schede degli ipogei.
     *
     * @throws AnagraficaEccezione
     */
    public static function aggiorna(string $codice, string $nome, string $note = '', bool $attivo = true, string $icona = ''): void
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new AnagraficaEccezione('Il nome è obbligatorio.');
        }

        $icona = self::normalizzaIcona($icona);

        Xml::conLock(self::percorso(), static function () use ($codice, $nome, $note, $attivo, $icona): void {
            $doc  = Xml::carica(self::percorso());
            $nodo = self::nodoPerCodice($doc, $codice);
            if ($nodo === null) {
                throw new AnagraficaEccezione('Voce non trovata.');
            }

            $nodo->setAttribute('nome', $nome);
            $nodo->setAttribute('attivo', $attivo ? '1' : '0');
            if ($icona === '') {
                $nodo->removeAttribute('icona');
            } else {
                $nodo->setAttribute('icona', $icona);
            }
            Xml::imposta($nodo, 'note', $note, true);

            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;
    }

    /**
     * Elimina una voce, se non ha figli e non e usata da nessuna scheda.
     *
     * @throws AnagraficaEccezione
     */
    public static function elimina(string $codice): void
    {
        $voce = self::trova($codice);
        if ($voce === null) {
            throw new AnagraficaEccezione('Voce non trovata.');
        }

        $figli = self::figli($codice);
        if ($figli !== []) {
            throw new AnagraficaEccezione(
                'Cancellazione rifiutata: la voce ha ' . count($figli) . ' voci subordinate. '
                . 'Eliminarle prima, oppure disattivare questa voce.'
            );
        }

        $usi = self::usi($codice);
        if ($usi !== []) {
            $dettaglio = [];
            foreach ($usi as $dove => $quanti) {
                $dettaglio[] = $quanti . ' ' . $dove;
            }
            throw new AnagraficaEccezione(
                'Cancellazione rifiutata: la voce e usata da ' . implode(', ', $dettaglio)
                . '. Disattivarla per togliergliela dalle scelte senza perdere le classificazioni esistenti.'
            );
        }

        Xml::conLock(self::percorso(), static function () use ($codice): void {
            $doc  = Xml::carica(self::percorso());
            $nodo = self::nodoPerCodice($doc, $codice);
            if ($nodo === null) {
                throw new AnagraficaEccezione('Voce non trovata.');
            }
            Xml::rimuovi($nodo);
            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;
    }

    /**
     * Voci direttamente subordinate a un codice.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function figli(string $codice): array
    {
        return array_values(array_filter(
            self::elenco(),
            static fn (array $v): bool => $v['padre'] === $codice
        ));
    }

    /**
     * Icona della voce, ereditata dall'alto se la voce non ne ha una.
     *
     * L'ereditarieta e il punto: chi crea una sottotipologia nuova — «Cisterna
     * a campana» sotto le opere idrauliche — non deve scegliere un'icona
     * perche la sua cavita compaia in mappa con il simbolo giusto. La sceglie
     * solo se vuole distinguerla dalle sorelle.
     *
     * Si sale di livello, non si scende: una tipologia senza icona prende
     * quella della natura, e la natura ha sempre la sua nel vocabolario
     * predefinito. Se anche quella manca decide il chiamante, che sa se sta
     * disegnando un marker o una legenda.
     */
    public static function icona(string $codice): string
    {
        $voci = [];
        foreach (self::elenco() as $v) {
            $voci[$v['codice']] = $v;
        }

        $corrente = $codice;
        // Il limite di giri non e paranoia: il file e modificabile a mano, e un
        // padre che punta a un figlio farebbe girare questo ciclo per sempre.
        for ($giri = 0; $giri < count(self::LIVELLI) + 1; $giri++) {
            if (!isset($voci[$corrente])) {
                return '';
            }
            if (($voci[$corrente]['icona'] ?? '') !== '') {
                return (string) $voci[$corrente]['icona'];
            }
            $corrente = (string) $voci[$corrente]['padre'];
            if ($corrente === '') {
                return '';
            }
        }

        return '';
    }

    /**
     * Completa le icone mancanti confrontando con la tassonomia predefinita.
     *
     * Serve alle installazioni che esistevano prima delle icone. Il vocabolario
     * si crea una volta sola, alla prima richiesta, e da quel momento e roba di
     * chi lo usa: un aggiornamento del codice non lo tocca, ed e giusto cosi —
     * altrimenti ogni aggiornamento riscriverebbe le scelte di chi l'ha
     * personalizzato. Il prezzo e che le novita non arrivano da sole, e questa
     * e la strada per farle arrivare quando si vuole.
     *
     * NON sovrascrive nulla: tocca solo le voci che l'icona non ce l'hanno, e
     * solo quelle il cui codice esiste nella tassonomia predefinita. Una voce
     * inventata dall'utente resta senza, ed erediterà dalla madre.
     *
     * @return array{aggiornate:int,gia:int,ignote:int}
     */
    public static function applicaIconePredefinite(): array
    {
        // La tassonomia predefinita si legge come documento e non si duplica:
        // e la stessa fonte che usa l'installazione nuova, quindi le due non
        // possono divergere.
        $modello = VocabolariPredefiniti::tipologie();
        $attese  = [];
        foreach (['natura', 'tipologia', 'sotto'] as $livello) {
            foreach (Xml::elenco($modello, '//' . $livello) as $nodo) {
                $icona = trim($nodo->getAttribute('icona'));
                if ($icona !== '') {
                    $attese[$nodo->getAttribute('codice')] = $icona;
                }
            }
        }

        $esito = ['aggiornate' => 0, 'gia' => 0, 'ignote' => 0];

        Xml::conLock(self::percorso(), static function () use ($attese, &$esito): void {
            self::assicuraFile();
            $doc = Xml::carica(self::percorso());

            foreach (self::LIVELLI as $livello) {
                foreach (Xml::elenco($doc, '//' . $livello) as $nodo) {
                    $codice = $nodo->getAttribute('codice');

                    if (trim($nodo->getAttribute('icona')) !== '') {
                        $esito['gia']++;
                        continue;
                    }
                    if (!isset($attese[$codice])) {
                        $esito['ignote']++;
                        continue;
                    }

                    $nodo->setAttribute('icona', $attese[$codice]);
                    $esito['aggiornate']++;
                }
            }

            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;

        return $esito;
    }

    /**
     * Ripulisce un nome di icona.
     *
     * Si accetta il solo nome di Bootstrap Icons, senza il prefisso «bi-» e
     * senza spazi: quel nome finisce dentro un attributo class in pagina, e
     * lasciarlo passare libero significherebbe permettere di scriverci altre
     * classi. Non e un'ipotesi di scuola — chi compila i vocabolari e un
     * amministratore, ma l'XML si puo anche modificare a mano.
     */
    private static function normalizzaIcona(string $icona): string
    {
        $icona = strtolower(trim($icona));
        $icona = preg_replace('/^bi-/', '', $icona) ?? $icona;

        return preg_match('/^[a-z0-9-]{1,40}$/', $icona) === 1 ? $icona : '';
    }

    /**
     * Riferimenti alla voce nelle schede degli ipogei.
     *
     * @return array<string,int>
     */
    public static function usi(string $codice): array
    {
        $usi      = [];
        $percorso = Percorsi::indice('ipogei.csv');

        if (!is_file($percorso)) {
            return $usi;
        }

        $conteggio = 0;
        Csv::leggi($percorso, static function (array $riga) use ($codice, &$conteggio): void {
            foreach (['natura', 'tipologia', 'sottotipologia'] as $colonna) {
                if (($riga[$colonna] ?? '') === $codice) {
                    $conteggio++;
                    return;
                }
            }
        });

        if ($conteggio > 0) {
            $usi['schede di ipogei'] = $conteggio;
        }

        return $usi;
    }

    /** Numero di voci nella tassonomia. */
    public static function conta(bool $soloAttive = false): int
    {
        return count(self::elenco($soloAttive));
    }

    // --------------------------------------------------------------------------- interni

    /**
     * @param array<int,array<string,mixed>> $voci
     */
    private static function aggiungiVoce(array &$voci, DOMElement $nodo, string $livello, string $padre, string $percorso): void
    {
        $voci[] = [
            'codice'   => $nodo->getAttribute('codice'),
            'nome'     => $nodo->getAttribute('nome'),
            'livello'  => $livello,
            'padre'    => $padre,
            'percorso' => $percorso,
            'attivo'   => $nodo->getAttribute('attivo') !== '0',
            /*
             * Ripulita in LETTURA e non solo in scrittura. La ripulitura sul
             * salvataggio protegge chi usa l'interfaccia, ma i vocabolari sono
             * file XML che si modificano anche a mano — e il progetto lo
             * incoraggia, e il senso di un archivio leggibile. Un valore
             * scritto a mano arriverebbe intatto fin dentro l'attributo class
             * del marker: la prova lo ha dimostrato, non e un'ipotesi.
             */
            'icona'    => self::normalizzaIcona($nodo->getAttribute('icona')),
            'note'     => Xml::testo($nodo, 'note'),
        ];
    }

    /** Nodo con il codice indicato, a qualunque livello. */
    private static function nodoPerCodice(DOMDocument $doc, string $codice): ?DOMElement
    {
        foreach (self::LIVELLI as $livello) {
            foreach (Xml::elenco($doc, '//' . $livello) as $nodo) {
                if ($nodo->getAttribute('codice') === $codice) {
                    return $nodo;
                }
            }
        }
        return null;
    }

    /**
     * @throws AnagraficaEccezione
     */
    private static function valida(string $livello, string $padre, string $codice, string $nome, ?string $idEsistente): void
    {
        if (!in_array($livello, self::LIVELLI, true)) {
            throw new AnagraficaEccezione('Livello non valido.');
        }
        if ($codice === '') {
            throw new AnagraficaEccezione('Il codice è obbligatorio.');
        }
        if (!preg_match('/^[A-Z0-9\-]{2,30}$/', $codice)) {
            throw new AnagraficaEccezione(
                'Codice non valido: da 2 a 30 caratteri fra lettere maiuscole, cifre e trattino (es. ART-IDR-CUN).'
            );
        }
        if ($nome === '') {
            throw new AnagraficaEccezione('Il nome è obbligatorio.');
        }
        if (self::trova($codice) !== null) {
            throw new AnagraficaEccezione("Il codice \"{$codice}\" è già usato nella tassonomia.");
        }

        if ($livello === 'natura') {
            if ($padre !== '') {
                throw new AnagraficaEccezione('Una natura non ha voce superiore.');
            }
            return;
        }

        if ($padre === '') {
            throw new AnagraficaEccezione('Indicare la voce superiore.');
        }

        $vocePadre = self::trova($padre);
        if ($vocePadre === null) {
            throw new AnagraficaEccezione('Voce superiore non trovata.');
        }

        $livelloAtteso = $livello === 'tipologia' ? 'natura' : 'tipologia';
        if ($vocePadre['livello'] !== $livelloAtteso) {
            throw new AnagraficaEccezione(
                'La voce superiore di una ' . self::ETICHETTE_LIVELLO[$livello]
                . ' deve essere una ' . self::ETICHETTE_LIVELLO[$livelloAtteso] . '.'
            );
        }
    }

    /** Normalizza un codice: maiuscole, senza spazi. */
    private static function normalizzaCodice(string $codice): string
    {
        return strtoupper(str_replace(' ', '', trim($codice)));
    }
}
