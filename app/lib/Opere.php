<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Opere.php
 *  Descrizione ..: Catalogo generale delle opere, "dati/bibliografia_generale.xml"
 *                  (6.12).
 *
 *                  Esiste per un motivo pratico: in un catasto una monografia
 *                  descrive spesso decine di cavita. Censirla una volta e
 *                  citarla puntualmente evita di riscrivere autori, editore e
 *                  ISBN in quaranta schede, e permette di correggere un dato
 *                  bibliografico in un posto solo.
 *
 *                  L'elenco inverso — quali ipogei citano un'opera — non viene
 *                  memorizzato: si ricava scorrendo gli indici di sezione. Un
 *                  elenco memorizzato sarebbe una seconda verita da tenere
 *                  allineata, e prima o poi non lo sarebbe.
 *  Versione .....: 0.10.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.10.0  2026-08-06  D.Candela  Prima stesura (fase 7b).
 * ============================================================================
 */

final class Opere extends Anagrafica
{
    /**
     * Tipi di opera. Non e un vocabolario modificabile dall'utente: da questo
     * valore dipende come la citazione viene composta e come viene esportata
     * in BibTeX, quindi un valore nuovo sarebbe solo un valore che nessuno sa
     * formattare.
     */
    public const TIPI = [
        'libro'       => 'Libro o monografia',
        'articolo'    => 'Articolo su rivista',
        'atti'        => 'Contributo in atti di convegno',
        'tesi'        => 'Tesi',
        'relazione'   => 'Relazione tecnica',
        'cartografia' => 'Cartografia',
        'archivio'    => 'Documento d\'archivio',
        'web'         => 'Risorsa in rete',
        'altro'       => 'Altro',
    ];

    /**
     * Corrispondenza con i tipi BibTeX.
     *
     * Cartografia e archivio non hanno un tipo BibTeX proprio: diventano
     * "misc", che e il contenitore previsto per cio che non rientra altrove.
     */
    public const TIPI_BIBTEX = [
        'libro'       => 'book',
        'articolo'    => 'article',
        'atti'        => 'inproceedings',
        'tesi'        => 'phdthesis',
        'relazione'   => 'techreport',
        'cartografia' => 'misc',
        'archivio'    => 'misc',
        'web'         => 'online',
        'altro'       => 'misc',
    ];

    protected static function nomeFile(): string     { return 'bibliografia_generale.xml'; }
    protected static function nomeRadice(): string   { return 'opere'; }
    protected static function nomeElemento(): string { return 'opera'; }
    protected static function prefissoId(): string   { return 'OP'; }
    protected static function nomeXsd(): ?string     { return 'bibliografia-generale.xsd'; }

    /**
     * @return array<string,mixed>
     */
    protected static function daNodo(DOMElement $nodo): array
    {
        return [
            'id'           => $nodo->getAttribute('id'),
            'tipoOpera'    => Xml::testo($nodo, 'tipoOpera'),
            'autori'       => Xml::testo($nodo, 'autori'),
            'titolo'       => Xml::testo($nodo, 'titolo'),
            'contenitore'  => Xml::testo($nodo, 'contenitore'),
            'editore'      => Xml::testo($nodo, 'editore'),
            'luogo'        => Xml::testo($nodo, 'luogo'),
            'anno'         => Xml::testo($nodo, 'anno'),
            'volume'       => Xml::testo($nodo, 'volume'),
            'fascicolo'    => Xml::testo($nodo, 'fascicolo'),
            'pagine'       => Xml::testo($nodo, 'pagine'),
            'isbnIssn'     => Xml::testo($nodo, 'isbnIssn'),
            'doi'          => Xml::testo($nodo, 'doi'),
            'url'          => Xml::testo($nodo, 'url'),
            'lingua'       => Xml::testo($nodo, 'lingua'),
            'abstract'     => Xml::testo($nodo, 'abstract'),
            'note'         => Xml::testo($nodo, 'note'),
            'attivo'       => Xml::booleano($nodo, 'attivo', true),
        ];
    }

    /**
     * @param array<string,mixed> $dati
     */
    protected static function scriviNodo(DOMElement $nodo, array $dati): void
    {
        foreach (['tipoOpera', 'autori', 'titolo', 'contenitore', 'editore', 'luogo',
                  'anno', 'volume', 'fascicolo', 'pagine', 'isbnIssn', 'doi', 'url', 'lingua'] as $campo) {
            Xml::imposta($nodo, $campo, trim((string) ($dati[$campo] ?? '')));
        }

        // Abstract e note sono testo libero senza limiti (D6): CDATA.
        Xml::imposta($nodo, 'abstract', (string) ($dati['abstract'] ?? ''), true);
        Xml::imposta($nodo, 'note', (string) ($dati['note'] ?? ''), true);
        Xml::imposta($nodo, 'attivo', !empty($dati['attivo']) ? '1' : '0');
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws AnagraficaEccezione
     */
    protected static function valida(array $dati, ?string $idEsistente): void
    {
        $titolo = trim((string) ($dati['titolo'] ?? ''));
        if ($titolo === '') {
            throw new AnagraficaEccezione('Il titolo dell\'opera e obbligatorio.');
        }

        $tipo = trim((string) ($dati['tipoOpera'] ?? ''));
        if (!isset(self::TIPI[$tipo])) {
            throw new AnagraficaEccezione('Tipo di opera non riconosciuto: ' . $tipo);
        }

        $anno = trim((string) ($dati['anno'] ?? ''));
        if ($anno !== '') {
            if (!preg_match('/^[0-9]{4}$/', $anno)) {
                throw new AnagraficaEccezione('L\'anno va indicato con quattro cifre.');
            }
            // Un catasto cita anche fonti antiche, ma non del futuro; il limite
            // inferiore e volutamente largo perche esistono fonti medievali.
            if ((int) $anno > (int) date('Y') + 1) {
                throw new AnagraficaEccezione('Anno di pubblicazione nel futuro.');
            }
        }

        $url = trim((string) ($dati['url'] ?? ''));
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new AnagraficaEccezione('Indirizzo non valido: indicarlo per esteso, con http:// o https://.');
        }

        // Doppione probabile: stesso titolo e stesso anno. Non si rifiuta —
        // due contributi omonimi nello stesso anno esistono — ma non passa
        // inosservato, perche il doppione bibliografico e l'errore piu comune
        // e il piu noioso da correggere dopo.
        foreach (static::elenco() as $voce) {
            if ((string) $voce['id'] === (string) $idEsistente) {
                continue;
            }
            if (strcasecmp((string) $voce['titolo'], $titolo) === 0
                && (string) $voce['anno'] === $anno && $anno !== '') {
                throw new AnagraficaEccezione(
                    'Esiste gia un\'opera con questo titolo e questo anno (' . $voce['id'] . '). '
                    . 'Se e davvero un\'altra opera, distinguila nel titolo o lascia vuoto l\'anno.'
                );
            }
        }
    }

    /**
     * @param array<string,mixed> $voce
     */
    public static function etichetta(array $voce): string
    {
        $pezzi = [];

        $autori = trim((string) ($voce['autori'] ?? ''));
        if ($autori !== '') {
            $pezzi[] = $autori;
        }

        $anno = trim((string) ($voce['anno'] ?? ''));
        if ($anno !== '') {
            $pezzi[] = '(' . $anno . ')';
        }

        $pezzi[] = trim((string) ($voce['titolo'] ?? ''));

        return implode(' ', $pezzi);
    }

    /**
     * Citazione discorsiva, nel formato che si usa in una bibliografia stampata.
     *
     * Non e uno stile normalizzato (APA, Chicago...): e la forma compatta in uso
     * nella letteratura speleologica italiana. Adottarne uno vero significherebbe
     * imporre a chi compila di conoscerlo, per un guadagno che in un catasto non
     * c'e: l'export BibTeX serve proprio a chi quello stile deve applicarlo.
     *
     * @param array<string,mixed> $opera
     */
    public static function citazione(array $opera, string $pagine = ''): string
    {
        $t = static fn (string $c): string => trim((string) ($opera[$c] ?? ''));

        $testo = '';
        if ($t('autori') !== '') {
            $testo .= $t('autori') . ', ';
        }
        $testo .= '"' . $t('titolo') . '"';

        if ($t('contenitore') !== '') {
            $testo .= ', in ' . $t('contenitore');
            if ($t('volume') !== '') {
                $testo .= ' ' . $t('volume');
            }
            if ($t('fascicolo') !== '') {
                $testo .= '(' . $t('fascicolo') . ')';
            }
        }

        foreach (['editore', 'luogo', 'anno'] as $campo) {
            if ($t($campo) !== '') {
                $testo .= ', ' . $t($campo);
            }
        }

        // Le pagine della citazione puntuale hanno la precedenza su quelle
        // dell'opera: "pp. 112-130" di questa cavita, non l'estensione totale.
        $pp = $pagine !== '' ? $pagine : $t('pagine');
        if ($pp !== '') {
            $testo .= ', pp. ' . $pp;
        }

        return $testo . '.';
    }

    /**
     * Voce BibTeX di un'opera.
     *
     * @param array<string,mixed> $opera
     */
    public static function bibtex(array $opera): string
    {
        $t = static fn (string $c): string => trim((string) ($opera[$c] ?? ''));

        $tipo   = self::TIPI_BIBTEX[$t('tipoOpera')] ?? 'misc';
        $chiave = self::chiaveBibtex($opera);

        $campi = [];
        if ($t('autori') !== '') {
            // BibTeX separa gli autori con "and"; il campo di CATAGEO e libero
            // e in uso si scrive "Rossi M., Bianchi L.". La virgola in BibTeX
            // separa cognome e nome, quindi tradurla sarebbe peggio che
            // lasciarla: si converte solo il separatore fra autori.
            $campi['author'] = str_replace(', ', ' and ', $t('autori'));
        }
        $campi['title'] = $t('titolo');

        if ($t('contenitore') !== '') {
            $campi[$tipo === 'inproceedings' ? 'booktitle' : 'journal'] = $t('contenitore');
        }
        foreach (['editore' => 'publisher', 'luogo' => 'address', 'anno' => 'year',
                  'volume' => 'volume', 'fascicolo' => 'number', 'pagine' => 'pages',
                  'doi' => 'doi', 'url' => 'url', 'lingua' => 'language'] as $nostro => $loro) {
            if ($t($nostro) !== '') {
                $campi[$loro] = $t($nostro);
            }
        }
        if ($t('isbnIssn') !== '') {
            $campi[$t('tipoOpera') === 'articolo' ? 'issn' : 'isbn'] = $t('isbnIssn');
        }
        if ($tipo === 'techreport' || $tipo === 'phdthesis') {
            // Questi due tipi esigono "institution" / "school": senza, molti
            // strumenti rifiutano la voce. Meglio un segnaposto esplicito che
            // una voce che l'utente scopre rotta solo importandola.
            $chiaveIst = $tipo === 'techreport' ? 'institution' : 'school';
            $campi[$chiaveIst] = $t('editore') !== '' ? $t('editore') : 'n.d.';
        }

        $righe = ['@' . $tipo . '{' . $chiave . ','];
        foreach ($campi as $nome => $valore) {
            $righe[] = sprintf('  %-12s = {%s},', $nome, self::proteggiBibtex($valore));
        }
        $righe[] = '}';

        return implode("\n", $righe);
    }

    /**
     * Chiave di citazione: primo autore, anno, prima parola del titolo.
     *
     * Deve essere stabile e priva di caratteri che BibTeX interpreta, altrimenti
     * il file esportato non si compila.
     *
     * @param array<string,mixed> $opera
     */
    public static function chiaveBibtex(array $opera): string
    {
        $autori = trim((string) ($opera['autori'] ?? ''));
        $primo  = $autori === '' ? 'anon' : (string) preg_split('/[,;]/', $autori)[0];
        $primo  = (string) preg_split('/\s+/', trim($primo))[0];

        $titolo = trim((string) ($opera['titolo'] ?? ''));
        $parole = preg_split('/\s+/', $titolo) ?: [];
        $prima  = '';
        foreach ($parole as $parola) {
            // Si salta l'articolo iniziale: "Il cunicolo..." va sotto "cunicolo",
            // che e la parola per cui la voce verra cercata.
            if (mb_strlen($parola) > 3) {
                $prima = $parola;
                break;
            }
        }

        $chiave = Testo::traslittera($primo . ($opera['anno'] ?? '') . $prima);
        $chiave = (string) preg_replace('/[^A-Za-z0-9]/', '', $chiave);

        return $chiave !== '' ? strtolower($chiave) : 'opera' . ($opera['id'] ?? '');
    }

    /** Neutralizza i caratteri che in BibTeX hanno significato. */
    private static function proteggiBibtex(string $valore): string
    {
        $valore = str_replace(['\\', '{', '}'], ['\\textbackslash{}', '\\{', '\\}'], $valore);

        return str_replace(['&', '%', '$', '#', '_', '~', '^'],
                           ['\\&', '\\%', '\\$', '\\#', '\\_', '\\textasciitilde{}', '\\textasciicircum{}'],
                           $valore);
    }

    /**
     * L'intero catalogo in BibTeX.
     *
     * Le chiavi vengono rese distinte con un suffisso quando collidono: due
     * opere dello stesso autore, stesso anno e stessa prima parola producono
     * la stessa chiave, e BibTeX rifiuta un file con chiavi duplicate.
     */
    public static function bibtexCatalogo(): string
    {
        $righe = [];
        $usate = [];

        foreach (self::elenco() as $opera) {
            $chiave = self::chiaveDistinta($opera, $usate);
            $righe[] = str_replace('{' . self::chiaveBibtex($opera) . ',', '{' . $chiave . ',',
                                   self::bibtex($opera));
        }

        if ($righe === []) {
            return "% Il catalogo generale delle opere e vuoto.\n";
        }

        return "% Catalogo generale delle opere — esportato da CATAGEO\n\n"
             . implode("\n\n", $righe) . "\n";
    }

    /**
     * Chiave di citazione resa unica rispetto a quelle gia usate.
     *
     * @param array<string,mixed> $opera
     * @param array<string,bool>  $usate  modificato per riferimento
     */
    public static function chiaveDistinta(array $opera, array &$usate): string
    {
        $base = self::chiaveBibtex($opera);
        $chiave = $base;
        $suffisso = 'b';

        while (isset($usate[$chiave])) {
            $chiave = $base . $suffisso;
            $suffisso = chr(ord($suffisso) + 1);
        }
        $usate[$chiave] = true;

        return $chiave;
    }

    /**
     * Ipogei che citano l'opera: si ricava dagli indici, non e memorizzato.
     *
     * @return array<int,array{codice:string,nome:string,progressivo:int}>
     */
    public static function citataDa(string $id): array
    {
        $esiti = [];

        /*
         * Qui NON si usa la scorciatoia "n_biblio == 0 -> salta", che pure
         * l'indice offrirebbe: da questa ricerca dipende anche il rifiuto di
         * cancellare un'opera citata, e un indice CSV rimasto indietro
         * lascerebbe cancellare l'opera che quaranta schede citano. Il costo e
         * comunque contenuto, perche Bibliografia::elenco() su un ipogeo senza
         * bibliografia si ferma a un is_file().
         *
         * L'elenco si scorre senza filtro di visibilita: se un'opera e citata
         * da una scheda riservata deve risultare citata lo stesso, altrimenti
         * chi non vede quella scheda potrebbe cancellarle la fonte sotto i piedi.
         */
        foreach (IndiceIpogei::elenco() as $riga) {
            $codice = (string) $riga['codice'];
            foreach (Bibliografia::elenco($codice) as $voce) {
                if ((string) $voce['tipo'] === 'riferimento' && (string) $voce['operaId'] === $id) {
                    $esiti[] = [
                        'codice'      => $codice,
                        'nome'        => (string) $riga['nome'],
                        'progressivo' => (int) $voce['progressivo'],
                    ];
                }
            }
        }

        return $esiti;
    }

    /**
     * @return array<string,int>
     */
    public static function usi(string $id): array
    {
        $citazioni = count(self::citataDa($id));

        return $citazioni > 0 ? ['citazioni in schede di ipogei' => $citazioni] : [];
    }
}
