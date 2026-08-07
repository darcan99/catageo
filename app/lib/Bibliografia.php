<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Bibliografia.php
 *  Descrizione ..: Bibliografia di un ipogeo,
 *                  "[codice] - Bibliografia/[codice] - Bibliografia.xml" (6.12).
 *
 *                  Tre tipi di voce, perche le fonti di un catasto non sono
 *                  tutte della stessa natura:
 *                  - "riferimento": punta a un'opera del catalogo generale, con
 *                    pagine e tavole di questa cavita. E la forma da preferire:
 *                    correggere l'editore di una monografia deve costare una
 *                    correzione sola, non quaranta;
 *                  - "inline": una fonte che vale solo per questa cavita e che
 *                    non ha senso censire nel catalogo generale;
 *                  - "link": una risorsa in rete, con data di consultazione e
 *                    invito ad archiviarne una copia, perche i link muoiono in
 *                    pochi anni e un catasto vive piu a lungo.
 *
 *                  A differenza delle altre sezioni non ci sono file per voce:
 *                  una voce bibliografica e solo metadato. L'eventuale PDF vive
 *                  fra gli allegati e viene richiamato per codice (AL001).
 *  Versione .....: 0.10.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.10.0  2026-08-06  D.Candela  Prima stesura (fase 7b).
 * ============================================================================
 */

final class Bibliografia
{
    public const VERSIONE_SCHEMA = '1.0';
    public const SIGLA = 'BB';

    public const TIPI = [
        'riferimento' => 'Opera del catalogo generale',
        'inline'      => 'Fonte propria di questo ipogeo',
        'link'        => 'Risorsa in rete',
    ];

    /**
     * Quanto la fonte pesa per questo ipogeo. Serve a distinguere la
     * pubblicazione che ha fatto conoscere la cavita da quella che la nomina
     * di sfuggita: in una bibliografia lunga e la sola cosa che orienta.
     */
    public const RILEVANZE = [
        'primaria'   => 'Primaria',
        'secondaria' => 'Secondaria',
        'citazione'  => 'Semplice citazione',
    ];

    /** Esiti possibili della verifica di un collegamento. */
    public const ESITI_VERIFICA = [
        'raggiungibile'  => 'Raggiungibile',
        'irraggiungibile' => 'Irraggiungibile',
        'spostato'       => 'Spostato',
        'non verificato' => 'Non verificato',
    ];

    /** Campi comuni a tutte le voci, col valore di riposo. */
    public const CAMPI = [
        'tipo' => 'inline', 'rilevanza' => 'secondaria', 'note' => '',
        // riferimento
        'operaId' => '', 'pagine' => '', 'tavole' => '',
        // inline
        'tipoOpera' => 'articolo', 'autori' => '', 'titolo' => '', 'contenitore' => '',
        'editore' => '', 'luogo' => '', 'anno' => '', 'volume' => '', 'fascicolo' => '',
        'isbnIssn' => '', 'doi' => '', 'lingua' => '', 'abstract' => '', 'allegatoRif' => '',
        // link
        'url' => '', 'ente' => '', 'dataConsultazione' => '', 'copiaArchiviata' => '',
        'ultimaVerifica' => '', 'esitoVerifica' => '',
    ];

    // ========================================================================
    //  LETTURA
    // ========================================================================

    /**
     * Voci bibliografiche di un ipogeo, in ordine di progressivo.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(string $codice): array
    {
        return self::leggi($codice)['voci'];
    }

    public static function conta(string $codice): int
    {
        return count(self::elenco($codice));
    }

    /**
     * Una singola voce.
     *
     * @return array<string,mixed>|null
     */
    public static function trova(string $codice, int $progressivo): ?array
    {
        foreach (self::elenco($codice) as $voce) {
            if ((int) $voce['progressivo'] === $progressivo) {
                return $voce;
            }
        }

        return null;
    }

    /**
     * Voce con l'opera del catalogo generale gia risolta.
     *
     * Restituisce sempre la voce, anche se l'opera citata non esiste piu: la
     * chiave "opera" resta null e chi mostra deve dirlo. Nascondere una
     * citazione perche l'opera e sparita significherebbe perdere l'unica
     * traccia che quella fonte era stata registrata.
     *
     * @param  array<string,mixed> $voce
     * @return array<string,mixed>
     */
    public static function risolvi(array $voce): array
    {
        $voce['opera'] = null;

        if ((string) $voce['tipo'] === 'riferimento' && (string) $voce['operaId'] !== '') {
            $voce['opera'] = Opere::trova((string) $voce['operaId']);
        }

        return $voce;
    }

    /**
     * Citazione discorsiva di una voce, qualunque sia il suo tipo.
     *
     * @param array<string,mixed> $voce voce gia passata per risolvi()
     */
    public static function citazione(array $voce): string
    {
        $tipo = (string) $voce['tipo'];

        if ($tipo === 'riferimento') {
            $opera = $voce['opera'] ?? null;
            if (!is_array($opera)) {
                return 'Opera ' . (string) $voce['operaId'] . ' non più presente nel catalogo generale.';
            }

            return Opere::citazione($opera, (string) $voce['pagine']);
        }

        if ($tipo === 'link') {
            $testo = '"' . (string) $voce['titolo'] . '"';
            if ((string) $voce['ente'] !== '') {
                $testo .= ', ' . (string) $voce['ente'];
            }
            $testo .= ', ' . (string) $voce['url'];
            if ((string) $voce['dataConsultazione'] !== '') {
                $testo .= ' (consultato il ' . (string) $voce['dataConsultazione'] . ')';
            }

            return $testo . '.';
        }

        // Una voce inline ha gli stessi campi di un'opera: si riusa la stessa
        // composizione, cosi le due forme non divergono nel tempo.
        return Opere::citazione($voce, (string) $voce['pagine']);
    }

    /**
     * Bibliografia dell'ipogeo in BibTeX.
     *
     * I link non producono una voce: BibTeX ha "@online", ma una scheda di
     * catasto non e una pubblicazione e citarla come tale sarebbe fuorviante.
     * Chi esporta cerca le fonti a stampa; i collegamenti restano nella scheda.
     */
    public static function bibtex(string $codice): string
    {
        $righe = [];
        $chiaviUsate = [];

        foreach (self::elenco($codice) as $voce) {
            $voce = self::risolvi($voce);
            $tipo = (string) $voce['tipo'];

            if ($tipo === 'link') {
                continue;
            }

            $opera = $tipo === 'riferimento' ? ($voce['opera'] ?? null) : $voce;
            if (!is_array($opera)) {
                continue;
            }

            // Due opere possono produrre la stessa chiave (stesso autore, anno
            // e prima parola). BibTeX rifiuta le chiavi duplicate, quindi si
            // suffissa: meglio "rossi1998cunicolob" che un file che non compila.
            $chiave = Opere::chiaveDistinta($opera, $chiaviUsate);
            $righe[] = str_replace(
                '{' . Opere::chiaveBibtex($opera) . ',',
                '{' . $chiave . ',',
                Opere::bibtex($opera)
            );
        }

        if ($righe === []) {
            return "% Nessuna fonte a stampa registrata per " . $codice . ".\n";
        }

        return "% Bibliografia di " . $codice . " — esportata da CATAGEO\n\n"
             . implode("\n\n", $righe) . "\n";
    }

    // ========================================================================
    //  PERCORSI
    // ========================================================================

    public static function cartella(string $codice): ?string
    {
        $cartellaIpogeo = Ipogeo::cartella($codice);

        return $cartellaIpogeo === null
            ? null
            : Percorsi::unisci($cartellaIpogeo, Sezioni::nomeCartella($codice, self::SIGLA));
    }

    public static function percorso(string $codice): ?string
    {
        $cartella = self::cartella($codice);

        return $cartella === null
            ? null
            : Percorsi::unisci($cartella, Sezioni::nomeIndice($codice, self::SIGLA));
    }

    // ========================================================================
    //  SCRITTURA
    // ========================================================================

    /**
     * Aggiunge una voce e ne restituisce il progressivo.
     *
     * @param array<string,mixed> $dati
     */
    public static function aggiungi(string $codice, array $dati): int
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new BibliografiaEccezione('Ipogeo non trovato: ' . $codice);
        }
        Percorsi::assicuraCartella((string) self::cartella($codice));

        self::valida($dati);

        return Xml::conLock($percorso, static function () use ($codice, $dati, $percorso): int {
            $stato = self::leggi($codice);

            $progressivo = $stato['ultimoProgressivo'] + 1;
            $dati['progressivo'] = $progressivo;
            $stato['voci'][] = array_merge(self::CAMPI, $dati);
            $stato['ultimoProgressivo'] = $progressivo;

            self::scrivi($codice, $stato, $percorso);

            Log::modifica('biblio_aggiunta', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo) . ' ' . self::etichettaBreve($dati));

            return $progressivo;
        });
    }

    /**
     * @param array<string,mixed> $dati
     */
    public static function aggiorna(string $codice, int $progressivo, array $dati): void
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new BibliografiaEccezione('Ipogeo non trovato: ' . $codice);
        }

        self::valida($dati);

        Xml::conLock($percorso, static function () use ($codice, $progressivo, $dati, $percorso): void {
            $stato = self::leggi($codice);

            $trovata = false;
            foreach ($stato['voci'] as $i => $voce) {
                if ((int) $voce['progressivo'] !== $progressivo) {
                    continue;
                }
                $dati['progressivo'] = $progressivo;
                $stato['voci'][$i] = array_merge(self::CAMPI, $dati);
                $trovata = true;
            }

            if (!$trovata) {
                throw new BibliografiaEccezione(
                    'Voce non trovata: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
            }

            self::scrivi($codice, $stato, $percorso);

            Log::modifica('biblio_aggiornata', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo) . ' ' . self::etichettaBreve($dati));
        });
    }

    /**
     * Toglie una voce.
     *
     * Il progressivo resta speso: "BB002" citato in una relazione non deve mai
     * indicare una fonte diversa da quella che indicava.
     */
    public static function elimina(string $codice, int $progressivo): void
    {
        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new BibliografiaEccezione('Ipogeo non trovato: ' . $codice);
        }

        Xml::conLock($percorso, static function () use ($codice, $progressivo, $percorso): void {
            $stato = self::leggi($codice);

            $rimaste = [];
            $tolta = null;
            foreach ($stato['voci'] as $voce) {
                if ((int) $voce['progressivo'] === $progressivo) {
                    $tolta = $voce;
                    continue;
                }
                $rimaste[] = $voce;
            }

            if ($tolta === null) {
                throw new BibliografiaEccezione(
                    'Voce non trovata: ' . Sezioni::riferimento(self::SIGLA, $progressivo));
            }

            $stato['voci'] = $rimaste;
            self::scrivi($codice, $stato, $percorso);

            Log::modifica('biblio_rimossa', self::catalogoDi($codice), $codice, self::SIGLA,
                Sezioni::riferimento(self::SIGLA, $progressivo) . ' ' . self::etichettaBreve($tolta));
        });
    }

    /**
     * Registra l'esito di una verifica di un collegamento.
     *
     * Si scrive anche l'esito negativo: sapere che un link e rotto vale piu che
     * non sapere nulla, ed e cio che spinge ad archiviarne una copia.
     */
    public static function registraVerifica(string $codice, int $progressivo, string $esito, string $data = ''): void
    {
        if (!isset(self::ESITI_VERIFICA[$esito])) {
            throw new BibliografiaEccezione('Esito di verifica non riconosciuto: ' . $esito);
        }

        $percorso = self::percorso($codice);
        if ($percorso === null) {
            throw new BibliografiaEccezione('Ipogeo non trovato: ' . $codice);
        }

        $data = $data !== '' ? $data : date('Y-m-d');

        Xml::conLock($percorso, static function () use ($codice, $progressivo, $esito, $data, $percorso): void {
            $stato = self::leggi($codice);

            foreach ($stato['voci'] as $i => $voce) {
                if ((int) $voce['progressivo'] === $progressivo) {
                    $stato['voci'][$i]['ultimaVerifica'] = $data;
                    $stato['voci'][$i]['esitoVerifica']  = $esito;
                }
            }

            self::scrivi($codice, $stato, $percorso);
        });
    }

    // ========================================================================
    //  VISTE TRASVERSALI
    // ========================================================================

    /**
     * Tutti i collegamenti registrati nell'archivio, per la verifica dei link.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function tuttiILink(): array
    {
        $esiti = [];

        // Qui il filtro di visibilita si applica, al contrario di
        // Opere::citataDa(): questa e una vista che si mostra all'utente, non
        // un controllo di integrita, e non deve rivelare schede riservate.
        foreach (IndiceIpogei::elenco(Visibilita::filtroIndice()) as $riga) {
            $codice = (string) $riga['codice'];
            foreach (self::elenco($codice) as $voce) {
                if ((string) $voce['tipo'] !== 'link' || (string) $voce['url'] === '') {
                    continue;
                }
                $voce['codice']     = $codice;
                $voce['nomeIpogeo'] = (string) $riga['nome'];
                $esiti[] = $voce;
            }
        }

        return $esiti;
    }

    // ========================================================================
    //  INTERNI
    // ========================================================================

    /**
     * @return array{voci:array<int,array<string,mixed>>,ultimoProgressivo:int}
     */
    private static function leggi(string $codice): array
    {
        $vuoto = ['voci' => [], 'ultimoProgressivo' => 0];

        $percorso = self::percorso($codice);
        if ($percorso === null || !is_file($percorso)) {
            return $vuoto;
        }

        try {
            $doc = Xml::carica($percorso);
        } catch (Throwable $e) {
            // Un indice illeggibile non deve far sparire la scheda: si annota e
            // si mostra la sezione vuota, che e visibilmente sbagliata e quindi
            // porta a indagare, invece di una pagina di errore.
            Log::errore('Bibliografia illeggibile: ' . $percorso . ' — ' . $e->getMessage());
            return $vuoto;
        }

        $radice = $doc->documentElement;
        if ($radice === null) {
            return $vuoto;
        }

        $voci = [];
        foreach (Xml::elenco($doc, '/bibliografia/voce') as $nodo) {
            $voce = ['progressivo' => (int) $nodo->getAttribute('progressivo')];

            foreach (array_keys(self::CAMPI) as $campo) {
                $voce[$campo] = Xml::testo($nodo, $campo);
            }

            // Il tipo e un attributo, non un elemento: e cio che decide come
            // leggere il resto, quindi si vede aprendo il file senza scorrerlo.
            $voce['tipo'] = $nodo->getAttribute('tipo') !== ''
                ? $nodo->getAttribute('tipo')
                : 'inline';

            $verifica = Xml::primo($nodo, 'ultimaVerifica');
            $voce['esitoVerifica'] = $verifica instanceof DOMElement
                ? $verifica->getAttribute('esito')
                : '';

            $voci[] = $voce;
        }

        usort($voci, static fn (array $a, array $b): int => $a['progressivo'] <=> $b['progressivo']);

        $ultimo = (int) $radice->getAttribute('ultimoProgressivo');
        foreach ($voci as $voce) {
            // Difesa contro un attributo abbassato a mano: il massimo presente
            // e comunque gia stato assegnato.
            $ultimo = max($ultimo, (int) $voce['progressivo']);
        }

        return ['voci' => $voci, 'ultimoProgressivo' => $ultimo];
    }

    /**
     * @param array{voci:array<int,array<string,mixed>>,ultimoProgressivo:int} $stato
     */
    private static function scrivi(string $codice, array $stato, string $percorso): void
    {
        $doc = Xml::nuovo('bibliografia', [
            'versioneSchema'    => self::VERSIONE_SCHEMA,
            'codiceIpogeo'      => $codice,
            'ultimoProgressivo' => (string) $stato['ultimoProgressivo'],
        ]);
        $radice = $doc->documentElement;

        foreach ($stato['voci'] as $voce) {
            $voce = array_merge(self::CAMPI, $voce);
            $tipo = (string) $voce['tipo'];

            $nodo = Xml::aggiungi($radice, 'voce', null, [
                'progressivo' => (string) $voce['progressivo'],
                'sigla'       => self::SIGLA,
                'tipo'        => $tipo,
            ]);

            // Si scrivono solo i campi che il tipo prevede: una voce "link" con
            // dentro fascicolo e ISBN vuoti sarebbe rumore in un file che deve
            // restare leggibile a mano.
            foreach (self::campiDi($tipo) as $campo) {
                if ($campo === 'abstract' || $campo === 'note') {
                    continue;
                }
                Xml::imposta($nodo, $campo, trim((string) $voce[$campo]));
            }

            if ($tipo === 'link' && (string) $voce['ultimaVerifica'] !== '') {
                $elemento = Xml::imposta($nodo, 'ultimaVerifica', (string) $voce['ultimaVerifica']);
                $esito = (string) $voce['esitoVerifica'];
                $elemento->setAttribute('esito', isset(self::ESITI_VERIFICA[$esito]) ? $esito : 'non verificato');
            }

            /*
             * La rilevanza si riporta al valore di riposo se arriva vuota.
             * Non e una cortesia: il modulo manda tutti i campi, compresi
             * quelli che non riguardano il tipo scelto, e un array_merge con
             * una stringa vuota vince sul valore predefinito. Lo schema pero
             * ammette solo i tre valori del vocabolario, quindi una rilevanza
             * vuota faceva fallire il salvataggio con un errore di
             * validazione al posto di un dato mancante innocuo.
             */
            $rilevanza = (string) $voce['rilevanza'];
            Xml::imposta($nodo, 'rilevanza',
                isset(self::RILEVANZE[$rilevanza]) ? $rilevanza : 'secondaria');

            if ($tipo === 'inline') {
                Xml::imposta($nodo, 'abstract', (string) $voce['abstract'], true);
            }
            Xml::imposta($nodo, 'note', (string) $voce['note'], true);
        }

        Xml::salva($doc, $percorso, Percorsi::schema('bibliografia.xsd'));
    }

    /**
     * Campi che hanno senso per un dato tipo di voce.
     *
     * @return array<int,string>
     */
    public static function campiDi(string $tipo): array
    {
        return match ($tipo) {
            'riferimento' => ['operaId', 'pagine', 'tavole'],
            'link'        => ['titolo', 'url', 'ente', 'dataConsultazione', 'copiaArchiviata'],
            default       => ['tipoOpera', 'autori', 'titolo', 'contenitore', 'editore', 'luogo',
                              'anno', 'volume', 'fascicolo', 'pagine', 'isbnIssn', 'doi',
                              'lingua', 'allegatoRif'],
        };
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws BibliografiaEccezione
     */
    private static function valida(array $dati): void
    {
        $tipo = trim((string) ($dati['tipo'] ?? ''));
        if (!isset(self::TIPI[$tipo])) {
            throw new BibliografiaEccezione('Tipo di voce non riconosciuto: ' . $tipo);
        }

        $rilevanza = trim((string) ($dati['rilevanza'] ?? 'secondaria'));
        if ($rilevanza !== '' && !isset(self::RILEVANZE[$rilevanza])) {
            throw new BibliografiaEccezione('Rilevanza non riconosciuta: ' . $rilevanza);
        }

        if ($tipo === 'riferimento') {
            $operaId = trim((string) ($dati['operaId'] ?? ''));
            if ($operaId === '') {
                throw new BibliografiaEccezione('Scegliere l\'opera del catalogo generale da citare.');
            }
            if (Opere::trova($operaId) === null) {
                throw new BibliografiaEccezione('L\'opera ' . $operaId . ' non esiste nel catalogo generale.');
            }

            return;
        }

        if (trim((string) ($dati['titolo'] ?? '')) === '') {
            throw new BibliografiaEccezione('Il titolo è obbligatorio.');
        }

        if ($tipo === 'link') {
            $url = trim((string) ($dati['url'] ?? ''));
            if ($url === '') {
                throw new BibliografiaEccezione('L\'indirizzo del collegamento è obbligatorio.');
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new BibliografiaEccezione('Indirizzo non valido: indicarlo per esteso, con http:// o https://.');
            }
            if (!preg_match('~^https?://~i', $url)) {
                // Un "file://" o un "javascript:" passerebbero FILTER_VALIDATE_URL
                // ma non sono collegamenti che ha senso pubblicare in una scheda.
                throw new BibliografiaEccezione('Sono ammessi solo collegamenti http e https.');
            }

            return;
        }

        $tipoOpera = trim((string) ($dati['tipoOpera'] ?? ''));
        if (!isset(Opere::TIPI[$tipoOpera])) {
            throw new BibliografiaEccezione('Tipo di opera non riconosciuto: ' . $tipoOpera);
        }

        $anno = trim((string) ($dati['anno'] ?? ''));
        if ($anno !== '' && !preg_match('/^[0-9]{4}$/', $anno)) {
            throw new BibliografiaEccezione('L\'anno va indicato con quattro cifre.');
        }
    }

    /** @param array<string,mixed> $voce */
    private static function etichettaBreve(array $voce): string
    {
        $titolo = trim((string) ($voce['titolo'] ?? ''));
        if ($titolo !== '') {
            return $titolo;
        }

        return trim((string) ($voce['operaId'] ?? '')) ?: '(senza titolo)';
    }

    private static function catalogoDi(string $codice): string
    {
        $riga = IndiceIpogei::trova($codice);

        return $riga === null ? '' : (string) ($riga['catalogo'] ?? '');
    }
}
