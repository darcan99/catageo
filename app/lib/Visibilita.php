<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Visibilita.php
 *  Descrizione ..: Regole di visibilita degli ipogei secondo riservatezza,
 *                  stato della scheda e livello dell'utente (D12).
 *
 *                  Sta in una classe sua perche le stesse regole servono
 *                  all'elenco, alla scheda e alla mappa: tre punti diversi che
 *                  devono decidere allo stesso modo. Una regola di riservatezza
 *                  applicata in due posti su tre e una fuga di dati.
 *  Versione .....: 0.11.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.11.0 2026-08-06  D.Candela  livelloVisibile() per le sezioni con
 *                                riservatezza propria.
 *  0.6.0  2026-08-05  D.Candela  Prima stesura (fase 4).
 * ============================================================================
 */

final class Visibilita
{
    /**
     * True se l'utente corrente puo vedere una scheda con questa riservatezza
     * e questo stato.
     */
    public static function schedaVisibile(string $riservatezza, string $statoScheda): bool
    {
        if ($riservatezza === 'riservata' && !Auth::puo('vedi_riservati')) {
            return false;
        }
        if ($statoScheda === 'bozza' && !Auth::puo('vedi_bozze')) {
            return false;
        }
        return true;
    }

    /**
     * True se l'utente corrente puo vedere qualcosa marcato con questo livello
     * di riservatezza, indipendentemente dalla scheda che lo contiene.
     *
     * Serve alle sezioni che hanno una riservatezza propria e prevalente su
     * quella dell'ipogeo: una serie di monitoraggio o un roost di chirotteri
     * possono essere riservati dentro una cavita pubblica. Il caso contrario
     * non si pone, perche a una scheda non visibile non si arriva.
     */
    public static function livelloVisibile(string $riservatezza): bool
    {
        return $riservatezza !== 'riservata' || Auth::puo('vedi_riservati');
    }

    /**
     * Filtro da passare a IndiceIpogei per escludere cio che l'utente non deve
     * vedere.
     *
     * @return callable(array<string,string>):bool
     */
    public static function filtroIndice(): callable
    {
        return static fn (array $riga): bool => self::schedaVisibile(
            (string) ($riga['riservatezza'] ?? ''),
            (string) ($riga['stato_scheda'] ?? '')
        );
    }

    /**
     * Coordinate da mostrare all'utente corrente, con l'offuscamento previsto
     * dal livello di riservatezza.
     *
     * L'arrotondamento e deterministico: la stessa scheda mostra sempre la
     * stessa posizione approssimata. Un jitter casuale sarebbe peggio che
     * inutile, perche ricaricando la pagina piu volte si potrebbe ricavare il
     * centro della distribuzione, cioe la posizione vera.
     *
     * @return array{lat:string,lon:string,offuscate:bool}
     */
    public static function coordinate(string $latitudine, string $longitudine, string $riservatezza): array
    {
        if ($riservatezza !== 'coordinate_offuscate' || Auth::puo('vedi_riservati')) {
            return ['lat' => $latitudine, 'lon' => $longitudine, 'offuscate' => false];
        }

        if (trim($latitudine) === '' || trim($longitudine) === '') {
            return ['lat' => $latitudine, 'lon' => $longitudine, 'offuscate' => false];
        }

        $griglia = self::griglia((float) $latitudine, (float) $longitudine);

        return [
            'lat'       => $griglia['lat'],
            'lon'       => $griglia['lon'],
            'offuscate' => true,
        ];
    }

    /**
     * Se il livello di riservatezza protegge la posizione.
     *
     * Vale sia per "riservata" sia per "coordinate_offuscate": in entrambi i
     * casi chi ha deciso quel livello non vuole che il punto esatto giri, e
     * mandarlo al server di un ente sarebbe farlo girare. Non dipende dai
     * permessi di chi guarda: l'operatore che le coordinate esatte le vede
     * eccome resta comunque tenuto a scegliere se farle uscire (6.16.2).
     */
    public static function coordinateRiservate(string $riservatezza): bool
    {
        return $riservatezza === 'riservata' || $riservatezza === 'coordinate_offuscate';
    }

    /**
     * Aggancia una coordinata alla griglia di offuscamento configurata.
     *
     * Arrotondamento, non errore casuale. La differenza conta: un errore che
     * cambia a ogni chiamata si annulla facendo la media di tre richieste, e
     * chi volesse la posizione vera dovrebbe solo chiederla tre volte.
     * L'arrotondamento restituisce sempre lo stesso punto, quante volte lo si
     * chieda, e a 1:50.000 non toglie nulla: mille metri sono due millimetri
     * di carta.
     *
     * Niente controllo dei permessi qui dentro: serve anche a chi le
     * coordinate esatte le vede, quando quelle coordinate stanno per uscire
     * verso il server di qualcun altro (6.16.2).
     *
     * @return array{lat:string,lon:string,metri:int}
     */
    public static function griglia(float $latitudine, float $longitudine, ?int $metri = null): array
    {
        $metri = max(100, $metri ?? Config::intero('sicurezza.offuscamentoCoordinate', 1000));
        $passo = $metri / 111000.0;   // gradi di latitudine equivalenti

        return [
            'lat'   => number_format(round($latitudine / $passo) * $passo, 4, '.', ''),
            'lon'   => number_format(round($longitudine / $passo) * $passo, 4, '.', ''),
            'metri' => $metri,
        ];
    }

    /**
     * Come coordinate(), ma partendo da una riga di indice.
     *
     * @param  array<string,string> $riga
     * @return array{lat:string,lon:string,offuscate:bool}
     */
    public static function coordinateDaRiga(array $riga): array
    {
        return self::coordinate(
            (string) ($riga['lat'] ?? ''),
            (string) ($riga['lon'] ?? ''),
            (string) ($riga['riservatezza'] ?? '')
        );
    }
}
