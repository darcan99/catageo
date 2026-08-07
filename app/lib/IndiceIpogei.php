<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/IndiceIpogei.php
 *  Descrizione ..: Indice denormalizzato degli ipogei
 *                  (dati/_indice/ipogei.csv).
 *
 *                  L'indice e cache, NON e la fonte di verita: si ricostruisce
 *                  in qualsiasi momento dalle sole schede XML. Serve perche
 *                  leggere migliaia di file XML a ogni ricerca sarebbe
 *                  insostenibile su un hosting economico.
 *
 *                  E unico e globale, con la colonna del catalogo in testa:
 *                  una sola scansione copre tutta l'installazione e il filtro
 *                  per catalogo diventa un confronto di campo.
 *  Versione .....: 1.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.1.0  2026-08-07  D.Candela  Colonne esplorata, prosegue, pos_verificata
 *                                e data_verifica (fase 12).
 *  0.10.0 2026-08-06  D.Candela  n_biblio contava file in una sezione che non
 *                                ne ha, quindi restava sempre a zero: ora
 *                                conta le voci dell'indice.
 *  0.4.0  2026-08-04  D.Candela  Prima stesura (fase 3).
 * ============================================================================
 */

final class IndiceIpogei
{
    /** Intestazione: unica fonte dell'ordine delle colonne. */
    public const INTESTAZIONE = [
        'catalogo', 'codice', 'nome', 'natura', 'tipologia', 'sottotipologia', 'complesso', 'stato',
        'regione', 'provincia', 'comune', 'localita', 'area', 'lat', 'lon', 'quota',
        'sviluppo', 'dislivello', 'stato_accesso', 'riservatezza', 'stato_scheda',
        'n_allegati', 'n_foto', 'n_video', 'n_rilievi', 'n_esplorazioni', 'n_biblio',
        'n_serie_misure', 'ha_kml', 'ha_3d', 'ha_chirotteri', 'ha_archeologia',
        'periodo_arch', 'esplorata', 'prosegue', 'pos_verificata', 'data_verifica',
        'grado', 'grado_idrico', 'armo',
        'data_censimento', 'ultima_modifica', 'cartella',
    ];

    /** Percorso del file. */
    public static function percorso(): string
    {
        return Percorsi::indice('ipogei.csv');
    }

    /** Crea il file con la sola intestazione se assente. */
    public static function assicuraFile(): void
    {
        if (!is_file(self::percorso())) {
            Csv::scrivi(self::percorso(), self::INTESTAZIONE, []);
        }
    }

    /**
     * Cerca la riga di un ipogeo.
     *
     * @return array<string,string>|null
     */
    public static function trova(string $codice): ?array
    {
        $codice = trim($codice);
        if ($codice === '' || !is_file(self::percorso())) {
            return null;
        }

        $trovata = null;
        Csv::leggi(self::percorso(), static function (array $riga) use ($codice, &$trovata): bool {
            if (strcasecmp(trim($riga['codice'] ?? ''), $codice) === 0) {
                $trovata = $riga;
                return false;
            }
            return true;
        });

        return $trovata;
    }

    /**
     * Scorre l'indice riga per riga applicando un filtro opzionale.
     *
     * Si resta in streaming: gli elenchi paginati e le ricerche non devono
     * caricare l'intero indice in memoria.
     *
     * @param  callable(array<string,string>):bool|null $filtro
     * @return array<int,array<string,string>>
     */
    public static function elenco(?callable $filtro = null, int $limite = 0, int $salta = 0): array
    {
        if (!is_file(self::percorso())) {
            return [];
        }

        $righe   = [];
        $saltate = 0;

        Csv::leggi(self::percorso(), static function (array $riga) use ($filtro, $limite, $salta, &$righe, &$saltate): bool {
            if ($filtro !== null && !$filtro($riga)) {
                return true;
            }
            if ($saltate < $salta) {
                $saltate++;
                return true;
            }
            $righe[] = $riga;

            return !($limite > 0 && count($righe) >= $limite);
        });

        return $righe;
    }

    /** Conta le righe che soddisfano un filtro. */
    public static function conta(?callable $filtro = null): int
    {
        if (!is_file(self::percorso())) {
            return 0;
        }

        $conteggio = 0;
        Csv::leggi(self::percorso(), static function (array $riga) use ($filtro, &$conteggio): void {
            if ($filtro === null || $filtro($riga)) {
                $conteggio++;
            }
        });

        return $conteggio;
    }

    /**
     * Aggiorna (o inserisce) la riga di un ipogeo leggendone la scheda.
     *
     * @throws IpogeoEccezione se l'ipogeo non e leggibile
     */
    public static function aggiorna(string $codice): void
    {
        $scheda = Ipogeo::trova($codice);
        if ($scheda === null) {
            throw new IpogeoEccezione('Ipogeo non trovato, indice non aggiornato: ' . $codice);
        }

        $riga = self::rigaDaScheda($scheda);
        self::assicuraFile();

        Xml::conLock(self::percorso(), static function () use ($codice, $riga): void {
            $righe     = [];
            $sostituita = false;

            Csv::leggi(self::percorso(), static function (array $esistente) use ($codice, $riga, &$righe, &$sostituita): void {
                if (strcasecmp(trim($esistente['codice'] ?? ''), $codice) === 0) {
                    if (!$sostituita) {
                        $righe[]    = $riga;
                        $sostituita = true;
                    }
                    return; // eventuali duplicati vengono scartati
                }
                $righe[] = $esistente;
            });

            if (!$sostituita) {
                $righe[] = $riga;
            }

            self::ordina($righe);
            Csv::scrivi(self::percorso(), self::INTESTAZIONE, $righe);
        });
    }

    /** Rimuove la riga di un ipogeo. */
    public static function rimuovi(string $codice): void
    {
        if (!is_file(self::percorso())) {
            return;
        }

        Xml::conLock(self::percorso(), static function () use ($codice): void {
            $righe = [];
            Csv::leggi(self::percorso(), static function (array $riga) use ($codice, &$righe): void {
                if (strcasecmp(trim($riga['codice'] ?? ''), $codice) !== 0) {
                    $righe[] = $riga;
                }
            });
            Csv::scrivi(self::percorso(), self::INTESTAZIONE, $righe);
        });
    }

    /**
     * Ricostruisce l'intero indice dalle schede presenti nell'archivio.
     *
     * E l'operazione che rende l'indice una cache e non un dato: se si perde o
     * si disallinea, si rigenera. Restituisce il riepilogo per la diagnostica.
     *
     * @return array{ipogei:int,cataloghi:int,errori:array<int,string>}
     */
    public static function ricostruisci(): array
    {
        $righe     = [];
        $errori    = [];
        $cataloghi = 0;

        foreach (Cataloghi::elenco() as $catalogo) {
            $cataloghi++;
            $cartellaIpogei = Percorsi::unisci(Percorsi::cataloghi((string) $catalogo['cartella']), 'ipogei');
            if (!is_dir($cartellaIpogei)) {
                continue;
            }

            foreach (scandir($cartellaIpogei) ?: [] as $voce) {
                if ($voce === '.' || $voce === '..') {
                    continue;
                }
                $cartella = Percorsi::unisci($cartellaIpogei, $voce);
                if (!is_dir($cartella)) {
                    continue;
                }

                $codice = Ipogeo::codiceDaNomeCartella($voce);
                if ($codice === '') {
                    $errori[] = 'Cartella non conforme allo standard: ' . $voce;
                    continue;
                }

                $file = Percorsi::unisci($cartella, $codice . ' - Dati.xml');
                if (!is_file($file)) {
                    $errori[] = 'Scheda mancante in: ' . $voce;
                    continue;
                }

                try {
                    $scheda = Ipogeo::trova($codice);
                    if ($scheda === null) {
                        $errori[] = 'Scheda non leggibile: ' . $codice;
                        continue;
                    }
                    $righe[] = self::rigaDaScheda($scheda);
                } catch (Throwable $e) {
                    $errori[] = $codice . ': ' . $e->getMessage();
                }
            }
        }

        self::ordina($righe);
        Csv::scrivi(self::percorso(), self::INTESTAZIONE, $righe);

        return ['ipogei' => count($righe), 'cataloghi' => $cataloghi, 'errori' => $errori];
    }

    /**
     * Costruisce la riga di indice da una scheda.
     *
     * I conteggi delle risorse vengono ricavati dal filesystem: le sezioni
     * arriveranno nelle fasi successive e finche le cartelle sono vuote i
     * conteggi sono zero, senza che l'indice debba cambiare forma.
     *
     * @param  array<string,mixed> $scheda
     * @return array<string,string>
     */
    public static function rigaDaScheda(array $scheda): array
    {
        $codice   = (string) $scheda['identificazione']['codice'];
        $cartella = (string) ($scheda['_percorso'] ?? '');

        $conteggi = self::conteggiaRisorse($cartella, $codice);

        // Il dislivello complessivo e la somma dei due versi: e il valore che
        // si usa in ricerca e in elenco, quello per cui una grotta "fa 120 m".
        $piu  = (float) str_replace(',', '.', (string) $scheda['caratteristiche']['dislivelloPositivo']);
        $meno = (float) str_replace(',', '.', (string) $scheda['caratteristiche']['dislivelloNegativo']);
        $dislivello = ($piu !== 0.0 || $meno !== 0.0) ? (string) ($piu + abs($meno)) : '';

        $relativa = $cartella === '' ? '' : self::cartellaRelativa($cartella);

        return [
            'catalogo'        => (string) $scheda['catasto']['catalogo'],
            'codice'          => $codice,
            'nome'            => (string) $scheda['identificazione']['nome'],
            'natura'          => (string) $scheda['identificazione']['natura'],
            'tipologia'       => (string) $scheda['identificazione']['tipologia'],
            'sottotipologia'  => (string) $scheda['identificazione']['sottotipologia'],
            'complesso'       => (string) ($scheda['identificazione']['complesso'] ?? ''),
            'stato'           => (string) $scheda['ubicazione']['stato'],
            'regione'         => (string) $scheda['ubicazione']['regione'],
            'provincia'       => (string) $scheda['ubicazione']['provincia'],
            'comune'          => (string) $scheda['ubicazione']['comune'],
            'localita'        => (string) $scheda['ubicazione']['localita'],
            'area'            => (string) ($scheda['ubicazione']['area'] ?? ''),
            'lat'             => (string) $scheda['ubicazione']['coordinate']['latitudine'],
            'lon'             => (string) $scheda['ubicazione']['coordinate']['longitudine'],
            'quota'           => (string) $scheda['ubicazione']['coordinate']['quota'],
            'sviluppo'        => (string) $scheda['caratteristiche']['sviluppoPlanimetrico'],
            'dislivello'      => $dislivello,
            'stato_accesso'   => (string) $scheda['ubicazione']['accesso']['stato'],
            'riservatezza'    => (string) $scheda['ubicazione']['riservatezza'],
            'stato_scheda'    => (string) $scheda['catasto']['statoScheda'],
            'n_allegati'      => (string) $conteggi['AL'],
            'n_foto'          => (string) $conteggi['FO'],
            'n_video'         => (string) $conteggi['VI'],
            'n_rilievi'       => (string) $conteggi['RI'],
            'n_esplorazioni'  => (string) $conteggi['ES'],
            'n_biblio'        => (string) $conteggi['BB'],
            'n_serie_misure'  => (string) $conteggi['SC'],
            'ha_kml'          => $conteggi['kml'] ? '1' : '0',
            'ha_3d'           => $conteggi['3d'] ? '1' : '0',
            // "ha_chirotteri" e vero solo se esiste davvero una colonia: la
            // sezione puo contenere solo osservazioni di invertebrati, e una
            // ricerca per chirotteri non deve restituirla.
            'ha_chirotteri'   => Biospeleologia::colonie($codice) !== [] ? '1' : '0',
            'ha_archeologia'  => $conteggi['AR'] > 0 ? '1' : '0',
            'periodo_arch'    => Archeologia::periodoPrincipale($codice),
            // Stato esplorativo e verifica sul campo (9.17): stanno
            // nell'indice perche sono criteri di ricerca, ed e la ricerca
            // il motivo per cui esistono. Tre stati, quindi si scrive il
            // valore e non un flag: '' vuol dire non lo sappiamo.
            'esplorata'       => (string) ($scheda['caratteristiche']['statoEsplorativo']['esplorata'] ?? ''),
            'prosegue'        => (string) ($scheda['caratteristiche']['statoEsplorativo']['prosegue'] ?? ''),
            // Percorribilita strutturata (9.17.7): solo i tre campi che si
            // cercano davvero. Periodo consigliato e inquinamento restano in
            // scheda: un indice non e un archivio di comodo, ogni colonna in
            // piu e byte letti a ogni ricerca su ogni riga.
            'grado'           => (string) ($scheda['caratteristiche']['percorribilita']['gradoProgressione'] ?? ''),
            'grado_idrico'    => (string) ($scheda['caratteristiche']['percorribilita']['gradoIdrico'] ?? ''),
            'armo'            => (string) ($scheda['caratteristiche']['percorribilita']['necessitaArmo'] ?? ''),
            'pos_verificata'  => !empty($scheda['ubicazione']['coordinate']['posizioneVerificata']) ? '1' : '0',
            'data_verifica'   => (string) ($scheda['ubicazione']['coordinate']['dataUltimaVerifica'] ?? ''),
            'data_censimento' => (string) $scheda['catasto']['dataCensimento'],
            'ultima_modifica' => (string) $scheda['catasto']['modificaData'],
            'cartella'        => $relativa,
        ];
    }

    /**
     * Percorso della cartella dell'ipogeo relativo a dati/cataloghi.
     * Nell'indice si scrive il percorso relativo, non quello assoluto: un
     * archivio spostato o ripristinato altrove resta valido.
     */
    private static function cartellaRelativa(string $assoluto): string
    {
        $radice   = rtrim(str_replace('\\', '/', Percorsi::cataloghi()), '/') . '/';
        $assoluto = str_replace('\\', '/', $assoluto);

        if (stripos($assoluto, $radice) === 0) {
            return substr($assoluto, strlen($radice));
        }
        return basename($assoluto);
    }

    /**
     * Conta i file presenti nelle sezioni di un ipogeo.
     *
     * @return array<string,int|bool>
     */
    private static function conteggiaRisorse(string $cartella, string $codice): array
    {
        $conteggi = ['kml' => false, '3d' => false];
        foreach (Sezioni::sigle() as $sigla) {
            $conteggi[$sigla] = 0;
        }

        if ($cartella === '' || !is_dir($cartella)) {
            return $conteggi;
        }

        $estensioniMappa = ['kml', 'kmz', 'gpx'];
        $estensioni3d    = ['ply', 'obj', 'stl', 'gltf', 'glb'];

        foreach (Sezioni::sigle() as $sigla) {
            $sotto = Percorsi::unisci($cartella, Sezioni::nomeCartella($codice, $sigla));
            if (!is_dir($sotto)) {
                continue;
            }

            /*
             * La bibliografia e l'unica sezione senza file per voce: una fonte
             * e solo metadato, e l'eventuale PDF vive fra gli allegati.
             * Contando i file si otteneva sempre zero, perche l'unico file
             * presente e proprio l'indice, che viene escluso. Qui si contano
             * quindi le voci dentro l'indice.
             */
            if ($sigla === 'BB') {
                $conteggi['BB'] = Bibliografia::conta($codice);
                continue;
            }

            // Biospeleologia e archeologia hanno lo stesso problema: le voci
            // stanno dentro l'indice, non sono file. Con il conteggio a file
            // "ha_chirotteri" e "ha_archeologia" restavano sempre a zero, e una
            // ricerca per quelle colonne non avrebbe trovato mai nulla.
            if ($sigla === 'BI') {
                $conteggi['BI'] = Biospeleologia::conta($codice);
                continue;
            }
            if ($sigla === 'AR') {
                $conteggi['AR'] = Archeologia::conta($codice);
                continue;
            }

            foreach (scandir($sotto) ?: [] as $voce) {
                if ($voce === '.' || $voce === '..' || is_dir(Percorsi::unisci($sotto, $voce))) {
                    continue;
                }
                // L'XML di indice della sezione non e una risorsa.
                if (strcasecmp($voce, Sezioni::nomeIndice($codice, $sigla)) === 0) {
                    continue;
                }
                if (str_ends_with($voce, '.tmp') || str_ends_with($voce, '.lock')) {
                    continue;
                }

                $conteggi[$sigla]++;

                $estensione = strtolower((string) pathinfo($voce, PATHINFO_EXTENSION));
                if ($sigla === 'RI' && in_array($estensione, $estensioniMappa, true)) {
                    $conteggi['kml'] = true;
                }
                if ($sigla === 'RI' && in_array($estensione, $estensioni3d, true)) {
                    $conteggi['3d'] = true;
                }
            }
        }

        return $conteggi;
    }

    /**
     * Ordina l'indice per catalogo e codice: un CSV aperto a mano deve essere
     * leggibile, e l'ordine stabile rende i diff fra backup significativi.
     *
     * @param array<int,array<string,string>> $righe
     */
    private static function ordina(array &$righe): void
    {
        usort($righe, static function (array $a, array $b): int {
            $perCatalogo = strcasecmp((string) ($a['catalogo'] ?? ''), (string) ($b['catalogo'] ?? ''));
            if ($perCatalogo !== 0) {
                return $perCatalogo;
            }
            return strnatcasecmp((string) ($a['codice'] ?? ''), (string) ($b['codice'] ?? ''));
        });
    }
}
