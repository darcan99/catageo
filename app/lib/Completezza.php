<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Completezza.php
 *  Descrizione ..: Report di completezza delle schede (9.17.6).
 *
 *                  Non e la verifica di integrita, ed e importante non
 *                  confonderle: **l'integrita dice se l'archivio e corretto, la
 *                  completezza dice se e finito**. Un archivio puo essere
 *                  impeccabile — XML validi, riferimenti risolti, indici
 *                  allineati — e fatto di duecento schede con il solo nome.
 *                  Sono due domande diverse e le confonde solo chi non ha mai
 *                  curato un catasto.
 *
 *                  Tutto si legge dall'indice CSV, in streaming: il report deve
 *                  poter girare su migliaia di schede su un hosting da pochi
 *                  euro, e aprire ogni scheda per contare le foto costerebbe
 *                  migliaia di letture di XML per un dato gia calcolato.
 *
 *                  Nessun punteggio complessivo in percentuale. Un "72% di
 *                  completezza" sembra una misura e non lo e: pesa insieme cose
 *                  incomparabili, e chi lo legge non sa cosa fare per alzarlo.
 *                  Si contano le voci mancanti, una colonna per voce, e chi
 *                  cura decide cosa gli manca davvero.
 *  Versione .....: 1.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.1.0  2026-08-07  D.Candela  Prima stesura (fase 12).
 * ============================================================================
 */

final class Completezza
{
    /**
     * Le voci di cui si verifica la presenza, nell'ordine delle colonne.
     *
     * Chiave => [etichetta, come si verifica sulla riga di indice].
     * Il predicato lavora sulla riga dell'indice e non sulla scheda: e il
     * motivo per cui il report costa una lettura di CSV e non mille di XML.
     *
     * @var array<string,array{0:string,1:callable(array<string,string>):bool}>
     */
    public static function voci(): array
    {
        $pieno = static fn (string $chiave): callable =>
            static fn (array $r): bool => trim((string) ($r[$chiave] ?? '')) !== '';

        $conta = static fn (string $chiave): callable =>
            static fn (array $r): bool => (int) ($r[$chiave] ?? 0) > 0;

        return [
            'coordinate'  => ['Coordinate',    static fn (array $r): bool =>
                trim((string) ($r['lat'] ?? '')) !== '' && trim((string) ($r['lon'] ?? '')) !== ''],
            'verificata'  => ['Posizione verificata', static fn (array $r): bool =>
                trim((string) ($r['pos_verificata'] ?? '0')) === '1'],
            'comune'      => ['Comune',        $pieno('comune')],
            'tipologia'   => ['Tipologia',     $pieno('tipologia')],
            'sviluppo'    => ['Sviluppo',      $pieno('sviluppo')],
            'foto'        => ['Foto',          $conta('n_foto')],
            'rilievi'     => ['Rilievi',       $conta('n_rilievi')],
            'esplorazioni' => ['Esplorazioni', $conta('n_esplorazioni')],
            'biblio'      => ['Bibliografia',  $conta('n_biblio')],
            // Lo stato esplorativo conta come compilato solo se qualcuno ha
            // risposto: "non si sa" e una non-risposta, ed e proprio quella
            // che questo report deve far emergere.
            'esplorativo' => ['Stato esplorativo', static fn (array $r): bool =>
                trim((string) ($r['prosegue'] ?? '')) !== ''],
        ];
    }

    /**
     * Report completo.
     *
     * @param  string $catalogo sigla, oppure vuoto per tutti
     * @return array{
     *     righe: array<int,array<string,mixed>>,
     *     conteggi: array<string,int>,
     *     totale: int,
     *     etichette: array<string,string>
     * }
     */
    public static function report(string $catalogo = ''): array
    {
        $voci      = self::voci();
        $etichette = array_map(static fn (array $v): string => $v[0], $voci);
        $conteggi  = array_fill_keys(array_keys($voci), 0);

        $catalogo = strtoupper(trim($catalogo));
        $righe    = [];
        $totale   = 0;

        // Il filtro di visibilita e lo stesso della consultazione: un report
        // che mostrasse le schede riservate a chi non le puo vedere sarebbe
        // una fuga di dati con l'aspetto di uno strumento di manutenzione.
        $visibile = Visibilita::filtroIndice();

        Csv::leggi(IndiceIpogei::percorso(), static function (array $riga) use (
            $voci, $catalogo, $visibile, &$righe, &$conteggi, &$totale
        ): void {
            if (!$visibile($riga)) {
                return;
            }
            if ($catalogo !== '' && strtoupper(trim((string) ($riga['catalogo'] ?? ''))) !== $catalogo) {
                return;
            }

            $totale++;
            $mancanti = [];
            $stato    = [];

            foreach ($voci as $chiave => [$etichetta, $presente]) {
                $ok = $presente($riga);
                $stato[$chiave] = $ok;
                if (!$ok) {
                    $mancanti[] = $chiave;
                    $conteggi[$chiave]++;
                }
            }

            $righe[] = [
                'codice'          => (string) ($riga['codice'] ?? ''),
                'nome'            => (string) ($riga['nome'] ?? ''),
                'catalogo'        => (string) ($riga['catalogo'] ?? ''),
                'stato_scheda'    => (string) ($riga['stato_scheda'] ?? ''),
                'ultima_modifica' => (string) ($riga['ultima_modifica'] ?? ''),
                'stato'           => $stato,
                'mancanti'        => count($mancanti),
            ];
        });

        // Prima le schede piu incomplete: e l'ordine in cui si lavora. A parita
        // di mancanze, il codice, cosi due esecuzioni danno lo stesso elenco.
        usort($righe, static function (array $a, array $b): int {
            $perMancanti = $b['mancanti'] <=> $a['mancanti'];

            return $perMancanti !== 0 ? $perMancanti : strcmp($a['codice'], $b['codice']);
        });

        return [
            'righe'     => $righe,
            'conteggi'  => $conteggi,
            'totale'    => $totale,
            'etichette' => $etichette,
        ];
    }

    /**
     * Il report in CSV, per lavorarci fuori dall'applicativo.
     *
     * Si scrive "si"/"no" e non "1"/"0": il file finisce in un foglio di
     * calcolo, dove una colonna di 1 e 0 si legge come un numero e si somma
     * per sbaglio.
     *
     * @param array<string,mixed> $report esito di report()
     */
    public static function csv(array $report): string
    {
        $intestazione = array_merge(
            ['codice', 'nome', 'catalogo', 'stato_scheda', 'ultima_modifica', 'voci_mancanti'],
            array_keys($report['etichette'])
        );

        $flusso = fopen('php://temp', 'r+');
        if ($flusso === false) {
            return '';
        }

        fputcsv($flusso, $intestazione, ';');
        foreach ($report['righe'] as $riga) {
            $valori = [
                $riga['codice'], $riga['nome'], $riga['catalogo'],
                $riga['stato_scheda'], $riga['ultima_modifica'], (string) $riga['mancanti'],
            ];
            foreach (array_keys($report['etichette']) as $chiave) {
                $valori[] = $riga['stato'][$chiave] ? 'si' : 'no';
            }
            fputcsv($flusso, $valori, ';');
        }

        rewind($flusso);
        $contenuto = (string) stream_get_contents($flusso);
        fclose($flusso);

        return $contenuto;
    }
}
