<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/VerificaLink.php
 *  Descrizione ..: Verifica dei collegamenti bibliografici (9.14, 6.12).
 *
 *                  I riferimenti in rete si rompono in pochi anni e un catasto
 *                  vive piu a lungo: questo strumento interroga gli URL
 *                  registrati e aggiorna l'esito in scheda, cosi chi consulta
 *                  sa se una fonte e ancora raggiungibile.
 *
 *                  Molti hosting economici bloccano le chiamate in uscita. La
 *                  degradazione e dichiarata e non silenziosa: se non si puo
 *                  uscire, lo strumento lo dice invece di segnare tutti i link
 *                  come irraggiungibili, che sarebbe un danno — l'esito
 *                  finirebbe scritto nelle schede.
 *
 *                  Si verifica un lotto per volta. Duecento richieste HTTP in
 *                  una pagina superano qualunque limite di tempo di esecuzione,
 *                  e un lavoro interrotto a meta senza dirlo lascerebbe meta
 *                  archivio con esiti vecchi e meta con esiti nuovi.
 *  Versione .....: 0.15.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.15.0  2026-08-06  D.Candela  Prima stesura (fase 9).
 * ============================================================================
 */

final class VerificaLink
{
    /** Quanti collegamenti si controllano in una passata. */
    public const LOTTO = 20;

    /** Secondi di attesa per collegamento. */
    public const ATTESA = 8;

    /** Come si presenta lo strumento: un catasto non e un robot anonimo. */
    public const AGENTE = 'CATAGEO/verifica-collegamenti (+https://github.com/darcan99/catageo)';

    /**
     * Verifica un lotto di collegamenti e ne registra l'esito.
     *
     * @param  int $salta quanti collegamenti gia verificati saltare
     * @return array{
     *     verificati:array<int,array{codice:string,progressivo:int,url:string,esito:string,dettaglio:string}>,
     *     totale:int, restanti:int, possibile:bool, messaggio:string
     * }
     */
    public static function esegui(int $salta = 0): array
    {
        $tutti = Bibliografia::tuttiILink();
        $totale = count($tutti);

        if (!self::possibile()) {
            return [
                'verificati' => [], 'totale' => $totale, 'restanti' => $totale,
                'possibile' => false,
                'messaggio' => 'Le chiamate HTTP in uscita non sono disponibili su questo '
                    . 'hosting: la verifica non si puo eseguire. Gli esiti gia registrati '
                    . 'restano invariati — segnarli tutti come irraggiungibili sarebbe '
                    . 'peggio che non verificarli.',
            ];
        }

        $lotto = array_slice($tutti, max(0, $salta), self::LOTTO);
        $verificati = [];

        foreach ($lotto as $voce) {
            $url = (string) $voce['url'];
            [$esito, $dettaglio] = self::interroga($url);

            try {
                Bibliografia::registraVerifica(
                    (string) $voce['codice'], (int) $voce['progressivo'], $esito);
            } catch (Throwable $e) {
                $dettaglio .= ' (esito non registrato: ' . $e->getMessage() . ')';
            }

            $verificati[] = [
                'codice'      => (string) $voce['codice'],
                'progressivo' => (int) $voce['progressivo'],
                'url'         => $url,
                'esito'       => $esito,
                'dettaglio'   => $dettaglio,
            ];
        }

        $fatti = max(0, $salta) + count($verificati);

        return [
            'verificati' => $verificati,
            'totale'     => $totale,
            'restanti'   => max(0, $totale - $fatti),
            'possibile'  => true,
            'messaggio'  => '',
        ];
    }

    /** True se l'ambiente permette chiamate HTTP in uscita. */
    public static function possibile(): bool
    {
        return Diagnostica::reteInUscitaDisponibile();
    }

    /**
     * Interroga un URL e ne ricava l'esito.
     *
     * @return array{0:string,1:string} esito e dettaglio leggibile
     */
    private static function interroga(string $url): array
    {
        if (!preg_match('~^https?://~i', $url)) {
            return ['non verificato', 'Non e un indirizzo http o https.'];
        }

        $contesto = stream_context_create([
            'http' => [
                // HEAD basterebbe, ma troppi server rispondono 405 a una HEAD
                // pur servendo la pagina: si usa GET e si legge il minimo.
                'method'          => 'GET',
                'timeout'         => self::ATTESA,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'ignore_errors'   => true,
                'user_agent'      => self::AGENTE,
                'header'          => "Accept: */*\r\nConnection: close\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $maniglia = @fopen($url, 'r', false, $contesto);
        if ($maniglia === false) {
            return ['irraggiungibile', 'Connessione non riuscita.'];
        }

        $intestazioni = stream_get_meta_data($maniglia)['wrapper_data'] ?? [];
        fclose($maniglia);

        $stato = 0;
        $spostato = false;
        foreach ((array) $intestazioni as $riga) {
            if (preg_match('~^HTTP/\d(?:\.\d)?\s+(\d{3})~', (string) $riga, $p)) {
                $precedente = $stato;
                $stato = (int) $p[1];
                // Un 3xx seguito da un 2xx significa che la pagina si e
                // spostata: raggiungibile, ma l'indirizzo registrato e vecchio
                // e prima o poi smettera di rimandare.
                if ($precedente >= 300 && $precedente < 400) {
                    $spostato = true;
                }
            }
        }

        if ($stato === 0) {
            return ['irraggiungibile', 'Nessuna risposta HTTP leggibile.'];
        }
        if ($stato >= 200 && $stato < 300) {
            return $spostato
                ? ['spostato', 'HTTP ' . $stato . ' dopo un reindirizzamento: aggiornare l\'indirizzo.']
                : ['raggiungibile', 'HTTP ' . $stato . '.'];
        }
        if ($stato >= 300 && $stato < 400) {
            return ['spostato', 'HTTP ' . $stato . ': l\'indirizzo rimanda altrove.'];
        }

        return ['irraggiungibile', 'HTTP ' . $stato . '.'];
    }
}
