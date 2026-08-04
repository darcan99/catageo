<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Grandezze.php
 *  Descrizione ..: Vocabolario delle grandezze misurabili in cavita
 *                  (dati/grandezze.xml): due livelli, categoria > grandezza.
 *
 *                  Ogni grandezza porta unita di misura, intervallo di
 *                  plausibilita e numero di decimali. L'intervallo non blocca
 *                  l'inserimento di una lettura: serve a proporre il flag
 *                  "sospetta" quando un valore e fuori scala, che e il modo per
 *                  intercettare gli errori di battitura senza rifiutare misure
 *                  legittimamente anomale.
 *  Versione .....: 0.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

final class Grandezze
{
    /** Cache dell'elenco appiattito, per richiesta. */
    private static ?array $cache = null;

    /** Percorso del file. */
    public static function percorso(): string
    {
        return Percorsi::dati('grandezze.xml');
    }

    /** Percorso dello schema, se presente. */
    private static function xsd(): ?string
    {
        $p = Percorsi::schema('grandezze.xsd');
        return is_file($p) ? $p : null;
    }

    /** Crea il file col vocabolario predefinito se assente. */
    public static function assicuraFile(): void
    {
        $percorso = self::percorso();
        if (is_file($percorso)) {
            return;
        }
        Percorsi::assicuraCartella(dirname($percorso));
        Xml::salva(VocabolariPredefiniti::grandezze(), $percorso, self::xsd());
        self::$cache = null;
    }

    /**
     * Categorie con le rispettive grandezze.
     *
     * @return array<int,array{codice:string,nome:string,attivo:bool,grandezze:array<int,array<string,mixed>>}>
     */
    public static function categorie(bool $soloAttive = false): array
    {
        self::assicuraFile();

        $doc        = Xml::carica(self::percorso());
        $risultato  = [];

        foreach (Xml::elenco($doc, '/grandezze/categoria') as $categoria) {
            $attivaCategoria = $categoria->getAttribute('attivo') !== '0';
            if ($soloAttive && !$attivaCategoria) {
                continue;
            }

            $grandezze = [];
            foreach (Xml::elenco($categoria, 'grandezza') as $grandezza) {
                $voce = self::daNodo($grandezza, $categoria);
                if ($soloAttive && !$voce['attivo']) {
                    continue;
                }
                $grandezze[] = $voce;
            }

            $risultato[] = [
                'codice'    => $categoria->getAttribute('codice'),
                'nome'      => $categoria->getAttribute('nome'),
                'attivo'    => $attivaCategoria,
                'grandezze' => $grandezze,
            ];
        }

        return $risultato;
    }

    /**
     * Elenco appiattito di tutte le grandezze.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(bool $soloAttive = false): array
    {
        if (self::$cache === null) {
            $voci = [];
            foreach (self::categorie(false) as $categoria) {
                foreach ($categoria['grandezze'] as $grandezza) {
                    $voci[] = $grandezza;
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
     * Cerca una grandezza per codice.
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

    /** Etichetta con unita di misura, es. "Temperatura aria (°C)". */
    public static function etichetta(string $codice): string
    {
        $voce = self::trova($codice);
        if ($voce === null) {
            return $codice === '' ? '' : $codice . ' (non trovata)';
        }
        return $voce['unita'] === '' ? $voce['nome'] : $voce['nome'] . ' (' . $voce['unita'] . ')';
    }

    /**
     * Verifica se un valore rientra nell'intervallo di plausibilita.
     *
     * @return array{plausibile:bool,messaggio:string}
     */
    public static function verificaPlausibilita(string $codice, float $valore): array
    {
        $voce = self::trova($codice);
        if ($voce === null) {
            return ['plausibile' => true, 'messaggio' => ''];
        }

        $min = $voce['min'] !== '' ? (float) $voce['min'] : null;
        $max = $voce['max'] !== '' ? (float) $voce['max'] : null;

        if ($min !== null && $valore < $min) {
            return [
                'plausibile' => false,
                'messaggio'  => sprintf('Valore inferiore al minimo atteso (%s %s).', $voce['min'], $voce['unita']),
            ];
        }
        if ($max !== null && $valore > $max) {
            return [
                'plausibile' => false,
                'messaggio'  => sprintf('Valore superiore al massimo atteso (%s %s).', $voce['max'], $voce['unita']),
            ];
        }

        return ['plausibile' => true, 'messaggio' => ''];
    }

    /**
     * Crea una categoria.
     *
     * @throws AnagraficaEccezione
     */
    public static function creaCategoria(string $codice, string $nome): string
    {
        $codice = self::normalizzaCodice($codice);
        $nome   = trim($nome);

        self::validaCodice($codice);
        if ($nome === '') {
            throw new AnagraficaEccezione('Il nome della categoria e obbligatorio.');
        }
        if (self::categoriaEsiste($codice) || self::trova($codice) !== null) {
            throw new AnagraficaEccezione("Il codice \"{$codice}\" e gia usato.");
        }

        Xml::conLock(self::percorso(), static function () use ($codice, $nome): void {
            self::assicuraFile();
            $doc = Xml::carica(self::percorso());
            Xml::aggiungi($doc->documentElement, 'categoria', null, [
                'codice' => $codice,
                'nome'   => $nome,
                'attivo' => '1',
            ]);
            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;

        return $codice;
    }

    /**
     * Crea una grandezza dentro una categoria.
     *
     * @param array<string,mixed> $dati codice, nome, unita, min, max, decimali
     * @throws AnagraficaEccezione
     */
    public static function creaGrandezza(string $categoria, array $dati): string
    {
        $codice = self::normalizzaCodice((string) ($dati['codice'] ?? ''));
        self::validaDati($codice, $dati);

        if (!self::categoriaEsiste($categoria)) {
            throw new AnagraficaEccezione('Categoria non trovata.');
        }
        if (self::trova($codice) !== null || self::categoriaEsiste($codice)) {
            throw new AnagraficaEccezione("Il codice \"{$codice}\" e gia usato.");
        }

        Xml::conLock(self::percorso(), static function () use ($categoria, $codice, $dati): void {
            $doc  = Xml::carica(self::percorso());
            $nodo = self::nodoCategoria($doc, $categoria);
            if ($nodo === null) {
                throw new AnagraficaEccezione('Categoria non trovata.');
            }
            Xml::aggiungi($nodo, 'grandezza', null, [
                'codice'   => $codice,
                'nome'     => trim((string) ($dati['nome'] ?? '')),
                'unita'    => trim((string) ($dati['unita'] ?? '')),
                'min'      => trim((string) ($dati['min'] ?? '')),
                'max'      => trim((string) ($dati['max'] ?? '')),
                'decimali' => (string) max(0, (int) ($dati['decimali'] ?? 2)),
                'attivo'   => '1',
            ]);
            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;

        return $codice;
    }

    /**
     * Aggiorna una grandezza. Il codice non e modificabile: e il riferimento
     * memorizzato nei descrittori delle serie di misure.
     *
     * @param array<string,mixed> $dati
     * @throws AnagraficaEccezione
     */
    public static function aggiornaGrandezza(string $codice, array $dati): void
    {
        self::validaDati($codice, $dati);

        Xml::conLock(self::percorso(), static function () use ($codice, $dati): void {
            $doc  = Xml::carica(self::percorso());
            $nodo = self::nodoGrandezza($doc, $codice);
            if ($nodo === null) {
                throw new AnagraficaEccezione('Grandezza non trovata.');
            }
            $nodo->setAttribute('nome', trim((string) ($dati['nome'] ?? '')));
            $nodo->setAttribute('unita', trim((string) ($dati['unita'] ?? '')));
            $nodo->setAttribute('min', trim((string) ($dati['min'] ?? '')));
            $nodo->setAttribute('max', trim((string) ($dati['max'] ?? '')));
            $nodo->setAttribute('decimali', (string) max(0, (int) ($dati['decimali'] ?? 2)));
            $nodo->setAttribute('attivo', !empty($dati['attivo']) ? '1' : '0');
            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;
    }

    /**
     * Aggiorna una categoria.
     *
     * @throws AnagraficaEccezione
     */
    public static function aggiornaCategoria(string $codice, string $nome, bool $attivo): void
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new AnagraficaEccezione('Il nome della categoria e obbligatorio.');
        }

        Xml::conLock(self::percorso(), static function () use ($codice, $nome, $attivo): void {
            $doc  = Xml::carica(self::percorso());
            $nodo = self::nodoCategoria($doc, $codice);
            if ($nodo === null) {
                throw new AnagraficaEccezione('Categoria non trovata.');
            }
            $nodo->setAttribute('nome', $nome);
            $nodo->setAttribute('attivo', $attivo ? '1' : '0');
            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;
    }

    /**
     * Elimina una grandezza, se nessuna serie di misure la usa.
     *
     * @throws AnagraficaEccezione
     */
    public static function eliminaGrandezza(string $codice): void
    {
        if (self::trova($codice) === null) {
            throw new AnagraficaEccezione('Grandezza non trovata.');
        }

        $usi = self::usi($codice);
        if ($usi > 0) {
            throw new AnagraficaEccezione(
                "Cancellazione rifiutata: {$usi} serie di misure usano questa grandezza. "
                . 'Disattivarla per toglierla dalle scelte conservando le serie esistenti.'
            );
        }

        Xml::conLock(self::percorso(), static function () use ($codice): void {
            $doc  = Xml::carica(self::percorso());
            $nodo = self::nodoGrandezza($doc, $codice);
            if ($nodo === null) {
                throw new AnagraficaEccezione('Grandezza non trovata.');
            }
            Xml::rimuovi($nodo);
            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;
    }

    /**
     * Elimina una categoria vuota.
     *
     * @throws AnagraficaEccezione
     */
    public static function eliminaCategoria(string $codice): void
    {
        foreach (self::categorie(false) as $categoria) {
            if ($categoria['codice'] !== $codice) {
                continue;
            }
            if ($categoria['grandezze'] !== []) {
                throw new AnagraficaEccezione(
                    'Cancellazione rifiutata: la categoria contiene ' . count($categoria['grandezze']) . ' grandezze.'
                );
            }
        }

        Xml::conLock(self::percorso(), static function () use ($codice): void {
            $doc  = Xml::carica(self::percorso());
            $nodo = self::nodoCategoria($doc, $codice);
            if ($nodo === null) {
                throw new AnagraficaEccezione('Categoria non trovata.');
            }
            Xml::rimuovi($nodo);
            Xml::salva($doc, self::percorso(), self::xsd());
        });

        self::$cache = null;
    }

    /**
     * Quante serie di misure usano una grandezza.
     *
     * Le serie vivono nelle cartelle degli ipogei e arrivano in fase 7c: fino
     * ad allora il conteggio e zero, ma la funzione e gia quella definitiva.
     */
    public static function usi(string $codice): int
    {
        $radice = Percorsi::cataloghi();
        if (!is_dir($radice) || $codice === '') {
            return 0;
        }

        $conteggio = 0;
        $descrittori = glob($radice . '/*/ipogei/*/* - Scientifici/* - Scientifici.xml') ?: [];

        foreach ($descrittori as $descrittore) {
            try {
                $doc = Xml::carica($descrittore);
            } catch (Throwable) {
                continue; // un file illeggibile non deve bloccare l'anagrafica
            }
            foreach (Xml::elenco($doc, '/scientifici/serie') as $serie) {
                if (Xml::testo($serie, 'grandezza') === $codice) {
                    $conteggio++;
                }
            }
        }

        return $conteggio;
    }

    /** Numero di grandezze censite. */
    public static function conta(bool $soloAttive = false): int
    {
        return count(self::elenco($soloAttive));
    }

    // --------------------------------------------------------------------------- interni

    /**
     * @return array<string,mixed>
     */
    private static function daNodo(DOMElement $nodo, DOMElement $categoria): array
    {
        return [
            'codice'         => $nodo->getAttribute('codice'),
            'nome'           => $nodo->getAttribute('nome'),
            'unita'          => $nodo->getAttribute('unita'),
            'min'            => $nodo->getAttribute('min'),
            'max'            => $nodo->getAttribute('max'),
            'decimali'       => $nodo->getAttribute('decimali') !== '' ? (int) $nodo->getAttribute('decimali') : 2,
            'attivo'         => $nodo->getAttribute('attivo') !== '0',
            'categoria'      => $categoria->getAttribute('codice'),
            'nomeCategoria'  => $categoria->getAttribute('nome'),
        ];
    }

    private static function nodoCategoria(DOMDocument $doc, string $codice): ?DOMElement
    {
        foreach (Xml::elenco($doc, '/grandezze/categoria') as $nodo) {
            if ($nodo->getAttribute('codice') === $codice) {
                return $nodo;
            }
        }
        return null;
    }

    private static function nodoGrandezza(DOMDocument $doc, string $codice): ?DOMElement
    {
        foreach (Xml::elenco($doc, '/grandezze/categoria/grandezza') as $nodo) {
            if ($nodo->getAttribute('codice') === $codice) {
                return $nodo;
            }
        }
        return null;
    }

    private static function categoriaEsiste(string $codice): bool
    {
        foreach (self::categorie(false) as $categoria) {
            if ($categoria['codice'] === $codice) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws AnagraficaEccezione
     */
    private static function validaCodice(string $codice): void
    {
        if ($codice === '') {
            throw new AnagraficaEccezione('Il codice e obbligatorio.');
        }
        if (!preg_match('/^[A-Z0-9\-]{1,30}$/', $codice)) {
            throw new AnagraficaEccezione(
                'Codice non valido: fino a 30 caratteri fra lettere maiuscole, cifre e trattino (es. T-ARIA).'
            );
        }
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws AnagraficaEccezione
     */
    private static function validaDati(string $codice, array $dati): void
    {
        self::validaCodice($codice);

        if (trim((string) ($dati['nome'] ?? '')) === '') {
            throw new AnagraficaEccezione('Il nome della grandezza e obbligatorio.');
        }

        $min = trim((string) ($dati['min'] ?? ''));
        $max = trim((string) ($dati['max'] ?? ''));

        foreach (['minimo' => $min, 'massimo' => $max] as $etichetta => $valore) {
            if ($valore !== '' && !is_numeric($valore)) {
                throw new AnagraficaEccezione("Il valore {$etichetta} deve essere numerico.");
            }
        }
        if ($min !== '' && $max !== '' && (float) $max <= (float) $min) {
            throw new AnagraficaEccezione('Il valore massimo deve essere maggiore del minimo.');
        }

        $decimali = (int) ($dati['decimali'] ?? 2);
        if ($decimali < 0 || $decimali > 6) {
            throw new AnagraficaEccezione('Il numero di decimali deve essere fra 0 e 6.');
        }
    }

    private static function normalizzaCodice(string $codice): string
    {
        return strtoupper(str_replace(' ', '', trim($codice)));
    }
}
