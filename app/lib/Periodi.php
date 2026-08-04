<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Periodi.php
 *  Descrizione ..: Vocabolario dei periodi storici (dati/periodi_storici.xml),
 *                  usato per la datazione della sezione archeologica.
 *
 *                  Ogni periodo porta gli estremi indicativi in anni (negativi
 *                  per le date a.C.): sono quelli che rendono possibile la
 *                  ricerca per intervallo temporale, altrimenti si potrebbe
 *                  cercare solo per etichetta esatta.
 *  Versione .....: 0.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

final class Periodi extends Anagrafica
{
    protected static function nomeFile(): string       { return 'periodi_storici.xml'; }
    protected static function nomeRadice(): string      { return 'periodi'; }
    protected static function nomeElemento(): string    { return 'periodo'; }
    protected static function prefissoId(): string      { return 'P'; }
    protected static function nomeXsd(): ?string        { return 'periodi.xsd'; }

    /** I periodi sono identificati da un codice parlante, non da un progressivo. */
    protected static function nomeAttributoId(): string { return 'codice'; }

    /**
     * A differenza delle altre anagrafiche, questa non nasce vuota: una
     * cronologia da compilare da zero sarebbe un ostacolo, e gli estremi in
     * anni sono la parte noiosa da inserire a mano.
     *
     * La creazione pigra allinea anche le installazioni fatte prima che questa
     * anagrafica esistesse, senza rieseguire l'installer.
     */
    public static function assicuraFile(): void
    {
        $percorso = static::percorso();
        if (is_file($percorso)) {
            return;
        }
        Percorsi::assicuraCartella(dirname($percorso));
        Xml::salva(VocabolariPredefiniti::periodi(), $percorso, static::xsd());
    }

    /**
     * @param array<string,mixed> $dati
     */
    protected static function generaId(DOMDocument $doc, array $dati): string
    {
        return self::normalizzaCodice((string) ($dati['codice'] ?? ''));
    }

    /**
     * @return array<string,mixed>
     */
    protected static function daNodo(DOMElement $nodo): array
    {
        return [
            'id'      => $nodo->getAttribute('codice'),
            'codice'  => $nodo->getAttribute('codice'),
            'nome'    => $nodo->getAttribute('nome'),
            'da'      => $nodo->getAttribute('da'),
            'a'       => $nodo->getAttribute('a'),
            'note'    => Xml::testo($nodo, 'note'),
            'attivo'  => $nodo->getAttribute('attivo') !== '0',
        ];
    }

    /**
     * @param array<string,mixed> $dati
     */
    protected static function scriviNodo(DOMElement $nodo, array $dati): void
    {
        $nodo->setAttribute('nome', trim((string) ($dati['nome'] ?? '')));
        $nodo->setAttribute('da', self::normalizzaAnno($dati['da'] ?? ''));
        $nodo->setAttribute('a', self::normalizzaAnno($dati['a'] ?? ''));
        $nodo->setAttribute('attivo', !empty($dati['attivo']) ? '1' : '0');

        Xml::imposta($nodo, 'note', (string) ($dati['note'] ?? ''), true);
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws AnagraficaEccezione
     */
    protected static function valida(array $dati, ?string $idEsistente): void
    {
        $codice = self::normalizzaCodice((string) ($dati['codice'] ?? ''));
        $nome   = trim((string) ($dati['nome'] ?? ''));

        if ($codice === '') {
            throw new AnagraficaEccezione('Il codice del periodo e obbligatorio.');
        }
        if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $codice)) {
            throw new AnagraficaEccezione(
                'Codice non valido: da 2 a 20 caratteri fra lettere maiuscole, cifre e trattino (es. ROM-IMP).'
            );
        }
        if ($nome === '') {
            throw new AnagraficaEccezione('Il nome del periodo e obbligatorio.');
        }

        // In creazione il codice non deve esistere; in modifica non e cambiabile
        // (e la chiave usata nei riferimenti delle schede).
        if ($idEsistente === null) {
            if (static::trova($codice) !== null) {
                throw new AnagraficaEccezione("Esiste gia un periodo con codice \"{$codice}\".");
            }
        } elseif ($codice !== $idEsistente) {
            throw new AnagraficaEccezione(
                'Il codice di un periodo non e modificabile: e il riferimento usato dalle schede. '
                . 'Creare un nuovo periodo e disattivare quello vecchio.'
            );
        }

        $da = self::normalizzaAnno($dati['da'] ?? '');
        $a  = self::normalizzaAnno($dati['a'] ?? '');

        foreach (['da' => $da, 'a' => $a] as $campo => $anno) {
            if ($anno !== '' && !preg_match('/^-?[0-9]{1,7}$/', $anno)) {
                throw new AnagraficaEccezione("Anno \"{$campo}\" non valido: usare un intero, negativo per le date a.C.");
            }
        }
        if ($da !== '' && $a !== '' && (int) $a < (int) $da) {
            throw new AnagraficaEccezione('L\'anno finale non puo precedere quello iniziale.');
        }
    }

    /**
     * @param array<string,mixed> $voce
     */
    public static function etichetta(array $voce): string
    {
        $nome     = (string) ($voce['nome'] ?? '');
        $estremi  = self::estremiLeggibili($voce);
        return $estremi === '' ? $nome : $nome . ' (' . $estremi . ')';
    }

    /**
     * Estremi cronologici in forma leggibile, con a.C. e d.C. espliciti.
     *
     * @param array<string,mixed> $voce
     */
    public static function estremiLeggibili(array $voce): string
    {
        $da = (string) ($voce['da'] ?? '');
        $a  = (string) ($voce['a'] ?? '');

        if ($da === '' && $a === '') {
            return '';
        }

        $formatta = static function (string $anno): string {
            if ($anno === '') {
                return '?';
            }
            $n = (int) $anno;
            return $n < 0 ? abs($n) . ' a.C.' : $n . ' d.C.';
        };

        return $formatta($da) . ' — ' . $formatta($a);
    }

    /**
     * Ordinamento cronologico: e l'unico ordine sensato per una cronologia,
     * l'alfabetico metterebbe il Novecento prima della preistoria.
     *
     * @param array<int,array<string,mixed>> $elenco
     */
    protected static function ordina(array &$elenco): void
    {
        usort($elenco, static function (array $a, array $b): int {
            // I periodi senza estremi (es. "non determinato") vanno in fondo.
            $aVuoto = ($a['da'] === '' && $a['a'] === '');
            $bVuoto = ($b['da'] === '' && $b['a'] === '');
            if ($aVuoto !== $bVuoto) {
                return $aVuoto ? 1 : -1;
            }
            $inizioA = $a['da'] !== '' ? (int) $a['da'] : PHP_INT_MAX;
            $inizioB = $b['da'] !== '' ? (int) $b['da'] : PHP_INT_MAX;
            if ($inizioA !== $inizioB) {
                return $inizioA <=> $inizioB;
            }
            return strcasecmp((string) $a['nome'], (string) $b['nome']);
        });
    }

    /**
     * @return array<string,int>
     */
    public static function usi(string $id): array
    {
        $usi = [];

        // Gli ipogei riportano il periodo principale nell'indice: il conteggio
        // funziona da fase 3, quando l'indice inizia a popolarsi.
        $ipogei = static::usiNellIndice('periodo_arch', $id);
        if ($ipogei > 0) {
            $usi['schede di ipogei'] = $ipogei;
        }

        return $usi;
    }

    /**
     * Periodi che intersecano un intervallo di anni: e la funzione su cui si
     * appoggera la ricerca archeologica per intervallo temporale.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function nellIntervallo(?int $da, ?int $a): array
    {
        $risultato = [];

        foreach (static::elenco(true) as $periodo) {
            $inizio = $periodo['da'] !== '' ? (int) $periodo['da'] : null;
            $fine   = $periodo['a'] !== '' ? (int) $periodo['a'] : null;

            if ($inizio === null && $fine === null) {
                continue; // periodo senza estremi: non collocabile
            }
            if ($da !== null && $fine !== null && $fine < $da) {
                continue;
            }
            if ($a !== null && $inizio !== null && $inizio > $a) {
                continue;
            }
            $risultato[] = $periodo;
        }

        return $risultato;
    }

    /** Normalizza un codice: maiuscole, senza spazi. */
    private static function normalizzaCodice(string $codice): string
    {
        return strtoupper(str_replace(' ', '', trim($codice)));
    }

    /** Normalizza un anno: intero come stringa, oppure stringa vuota. */
    private static function normalizzaAnno(mixed $valore): string
    {
        $testo = trim((string) $valore);
        if ($testo === '') {
            return '';
        }
        // Si accetta anche la forma "500 a.C." digitata a mano.
        if (preg_match('/^(\d+)\s*a\.?\s*c\.?$/iu', $testo, $m) === 1) {
            return '-' . $m[1];
        }
        if (preg_match('/^(\d+)\s*d\.?\s*c\.?$/iu', $testo, $m) === 1) {
            return $m[1];
        }
        return $testo;
    }
}
