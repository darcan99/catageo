<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/IndiceCodici.php
 *  Descrizione ..: Indice di risoluzione dei codici catastali
 *                  (dati/_indice/codici.csv).
 *
 *                  Registra OGNI codice mai assegnato, corrente o storico, e lo
 *                  risolve verso quello attuale. Serve perche in un catasto il
 *                  codice viene citato in pubblicazioni cartacee che non si
 *                  possono aggiornare: dopo una migrazione fra cataloghi un
 *                  vecchio riferimento deve continuare a portare alla scheda.
 *  Versione .....: 0.3.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.3.0  2026-08-04  D.Candela  Prima stesura (fase 2b).
 * ============================================================================
 */

final class IndiceCodici
{
    /** Intestazione del file, unica fonte dell'ordine delle colonne. */
    public const INTESTAZIONE = ['codice', 'stato_codice', 'codice_corrente', 'catalogo_corrente', 'data_variazione'];

    /** Stati possibili di un codice. */
    public const CORRENTE = 'corrente';
    public const STORICO  = 'storico';

    /** Percorso del file. */
    public static function percorso(): string
    {
        return Percorsi::indice('codici.csv');
    }

    /** Crea il file con la sola intestazione se assente. */
    public static function assicuraFile(): void
    {
        if (!is_file(self::percorso())) {
            Csv::scrivi(self::percorso(), self::INTESTAZIONE, []);
        }
    }

    /**
     * Cerca un codice nell'indice.
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
                return false; // trovato: si interrompe la scansione
            }
            return true;
        });

        return $trovata;
    }

    /** True se il codice e presente, corrente o storico. */
    public static function esiste(string $codice): bool
    {
        return self::trova($codice) !== null;
    }

    /**
     * Risolve un codice verso quello corrente.
     *
     * @return array{codice:string,catalogo:string,storico:bool}|null
     */
    public static function risolvi(string $codice): ?array
    {
        $riga = self::trova($codice);
        if ($riga === null) {
            return null;
        }

        $storico = ($riga['stato_codice'] ?? '') === self::STORICO;

        return [
            'codice'   => $storico ? ($riga['codice_corrente'] ?? '') : trim($riga['codice'] ?? ''),
            'catalogo' => $riga['catalogo_corrente'] ?? '',
            'storico'  => $storico,
        ];
    }

    /**
     * Registra un codice come corrente.
     *
     * @throws CatalogoEccezione se il codice risulta gia registrato
     */
    public static function registra(string $codice, string $catalogo): void
    {
        $codice = trim($codice);
        if ($codice === '') {
            throw new CatalogoEccezione('Codice vuoto: non registrabile.');
        }
        if (self::esiste($codice)) {
            throw new CatalogoEccezione("Il codice \"{$codice}\" e gia registrato nell'indice.");
        }

        self::assicuraFile();
        Csv::accoda(self::percorso(), self::INTESTAZIONE, [
            'codice'            => $codice,
            'stato_codice'      => self::CORRENTE,
            'codice_corrente'   => $codice,
            'catalogo_corrente' => $catalogo,
            'data_variazione'   => date('Y-m-d'),
        ], false);
    }

    /**
     * Registra la sostituzione di un codice: quello vecchio diventa storico e
     * punta al nuovo, che viene registrato come corrente.
     *
     * L'operazione riscrive l'intero file, che resta piccolo (una riga per
     * codice mai assegnato) e non giustifica una struttura piu complessa.
     *
     * @throws CatalogoEccezione
     */
    public static function sostituisci(string $codiceVecchio, string $codiceNuovo, string $catalogoNuovo): void
    {
        $codiceVecchio = trim($codiceVecchio);
        $codiceNuovo   = trim($codiceNuovo);

        if ($codiceVecchio === '' || $codiceNuovo === '') {
            throw new CatalogoEccezione('Codici non validi per la sostituzione.');
        }
        if (strcasecmp($codiceVecchio, $codiceNuovo) === 0) {
            return;
        }
        if (self::esiste($codiceNuovo)) {
            throw new CatalogoEccezione("Il codice \"{$codiceNuovo}\" e gia registrato nell'indice.");
        }

        self::assicuraFile();

        Xml::conLock(self::percorso(), static function () use ($codiceVecchio, $codiceNuovo, $catalogoNuovo): void {
            $righe = [];
            $oggi  = date('Y-m-d');

            Csv::leggi(self::percorso(), static function (array $riga) use (&$righe, $codiceVecchio, $codiceNuovo, $catalogoNuovo, $oggi): void {
                // Tutte le righe che puntavano al vecchio codice, comprese le
                // storiche di migrazioni precedenti, vanno ripuntate al nuovo:
                // altrimenti una catena di due migrazioni si spezzerebbe.
                if (strcasecmp(trim($riga['codice_corrente'] ?? ''), $codiceVecchio) === 0) {
                    $riga['codice_corrente']   = $codiceNuovo;
                    $riga['catalogo_corrente'] = $catalogoNuovo;
                    $riga['data_variazione']   = $oggi;
                }
                if (strcasecmp(trim($riga['codice'] ?? ''), $codiceVecchio) === 0) {
                    $riga['stato_codice'] = self::STORICO;
                }
                $righe[] = $riga;
            });

            $righe[] = [
                'codice'            => $codiceNuovo,
                'stato_codice'      => self::CORRENTE,
                'codice_corrente'   => $codiceNuovo,
                'catalogo_corrente' => $catalogoNuovo,
                'data_variazione'   => $oggi,
            ];

            Csv::scrivi(self::percorso(), self::INTESTAZIONE, $righe);
        });
    }

    /**
     * Aggiorna il catalogo corrente di un codice, senza cambiare il codice.
     * Usato dalla migrazione quando la serie di destinazione riassegna lo
     * stesso codice.
     */
    public static function aggiornaCatalogo(string $codice, string $catalogo): void
    {
        if (!self::esiste($codice)) {
            return;
        }

        Xml::conLock(self::percorso(), static function () use ($codice, $catalogo): void {
            $righe = [];
            Csv::leggi(self::percorso(), static function (array $riga) use (&$righe, $codice, $catalogo): void {
                if (strcasecmp(trim($riga['codice_corrente'] ?? ''), $codice) === 0) {
                    $riga['catalogo_corrente'] = $catalogo;
                }
                $righe[] = $riga;
            });
            Csv::scrivi(self::percorso(), self::INTESTAZIONE, $righe);
        });
    }

    /**
     * Rimuove dall'indice tutte le righe che puntano a un codice.
     * Usata quando un ipogeo viene eliminato del tutto.
     */
    public static function rimuovi(string $codice): void
    {
        if (!is_file(self::percorso())) {
            return;
        }

        Xml::conLock(self::percorso(), static function () use ($codice): void {
            $righe = [];
            Csv::leggi(self::percorso(), static function (array $riga) use (&$righe, $codice): void {
                if (strcasecmp(trim($riga['codice_corrente'] ?? ''), $codice) === 0) {
                    return; // scartata
                }
                $righe[] = $riga;
            });
            Csv::scrivi(self::percorso(), self::INTESTAZIONE, $righe);
        });
    }

    /**
     * Quanti codici iniziano con un dato prefisso: serve a impedire la
     * cancellazione di una serie che ha gia numerato qualcosa.
     */
    public static function contaConPrefisso(string $prefisso): int
    {
        if ($prefisso === '' || !is_file(self::percorso())) {
            return 0;
        }

        $conteggio = 0;
        Csv::leggi(self::percorso(), static function (array $riga) use ($prefisso, &$conteggio): void {
            if (stripos(trim($riga['codice'] ?? ''), $prefisso) === 0) {
                $conteggio++;
            }
        });

        return $conteggio;
    }

    /** Numero di codici registrati, per stato. */
    public static function conta(?string $stato = null): int
    {
        if (!is_file(self::percorso())) {
            return 0;
        }

        $conteggio = 0;
        Csv::leggi(self::percorso(), static function (array $riga) use ($stato, &$conteggio): void {
            if ($stato === null || ($riga['stato_codice'] ?? '') === $stato) {
                $conteggio++;
            }
        });

        return $conteggio;
    }

    /**
     * Codici storici che puntano a un codice corrente.
     *
     * @return string[]
     */
    public static function storiciDi(string $codiceCorrente): array
    {
        if (!is_file(self::percorso())) {
            return [];
        }

        $storici = [];
        Csv::leggi(self::percorso(), static function (array $riga) use ($codiceCorrente, &$storici): void {
            if (($riga['stato_codice'] ?? '') === self::STORICO
                && strcasecmp(trim($riga['codice_corrente'] ?? ''), $codiceCorrente) === 0) {
                $storici[] = trim($riga['codice'] ?? '');
            }
        });

        return $storici;
    }
}
