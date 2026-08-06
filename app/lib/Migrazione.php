<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Migrazione.php
 *  Descrizione ..: Migrazione di ipogei fra cataloghi: anteprima, esecuzione
 *                  in lotto e tracciato (5.5).
 *
 *                  Lo spostamento vero e in Ipogeo::migra(), dove vivono i
 *                  passaggi di rinomina. Qui sta cio che riguarda il LOTTO:
 *                  l'anteprima dei codici che verrebbero assegnati, l'ordine in
 *                  cui procedere, e il tracciato di cio che e stato fatto.
 *
 *                  L'anteprima e la parte delicata. CodiceCatastale::anteprima()
 *                  risponde sempre col prossimo progressivo della serie: su
 *                  cinque ipogei diretti allo stesso catalogo mostrerebbe cinque
 *                  volte lo stesso codice, e chi conferma crederebbe di aver
 *                  visto quello che succedera. Qui i contatori si simulano.
 *
 *                  Un lotto NON e transazionale nel suo insieme: ogni ipogeo lo
 *                  e per conto suo. Se il terzo di cinque fallisce, i primi due
 *                  restano migrati e lo si dichiara. L'alternativa — annullare
 *                  tutto — richiederebbe di rimettere indietro cartelle gia
 *                  spostate e contatori gia consumati, cioe piu movimento di
 *                  file proprio nel momento in cui qualcosa non funziona.
 *  Versione .....: 0.14.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.14.0  2026-08-06  D.Candela  Prima stesura (fase 8b).
 * ============================================================================
 */

final class Migrazione
{
    /** Tracciato delle migrazioni eseguite. */
    public const FILE_TRACCIATO = 'migrazioni.csv';

    public const COLONNE = [
        'data', 'utente', 'codice_precedente', 'catalogo_precedente',
        'codice_nuovo', 'catalogo_nuovo', 'nome', 'motivo', 'esito', 'dettaglio',
    ];

    /**
     * Quanti ipogei si possono spostare in un colpo solo.
     *
     * Non e un limite tecnico ma di prudenza: una migrazione e irreversibile
     * senza lavoro manuale, e un elenco lungo non lo si controlla davvero prima
     * di confermare.
     */
    public const LIMITE_LOTTO = 100;

    /**
     * Codici che verrebbero assegnati, senza consumare nulla.
     *
     * @param  array<int,string> $codici
     * @return array<int,array{
     *     codice:string, nome:string, catalogo:string,
     *     nuovoCodice:string, serie:string, ok:bool, messaggio:string
     * }>
     */
    public static function anteprima(array $codici, string $siglaDestinazione): array
    {
        $esiti = [];
        $destinazione = Cataloghi::trova($siglaDestinazione);

        /*
         * Contatori simulati, uno per prefisso di serie. Partono dal valore
         * reale e avanzano man mano, cosi l'anteprima mostra cinque codici
         * consecutivi e non cinque volte lo stesso.
         */
        $consumati = [];

        foreach ($codici as $codice) {
            $codice = trim((string) $codice);
            if ($codice === '') {
                continue;
            }

            $voce = [
                'codice' => $codice, 'nome' => '', 'catalogo' => '',
                'nuovoCodice' => '', 'serie' => '', 'ok' => false, 'messaggio' => '',
            ];

            $scheda = Ipogeo::trova($codice);
            if ($scheda === null) {
                $voce['messaggio'] = 'Ipogeo non trovato.';
                $esiti[] = $voce;
                continue;
            }

            $voce['nome']     = (string) $scheda['identificazione']['nome'];
            $voce['catalogo'] = (string) $scheda['catasto']['catalogo'];

            if (!Visibilita::schedaVisibile(
                (string) $scheda['ubicazione']['riservatezza'],
                (string) $scheda['catasto']['statoScheda']
            )) {
                // Non si migra cio che non si puo nemmeno vedere: l'anteprima
                // stessa rivelerebbe nome e catalogo di una scheda riservata.
                $voce['nome'] = '';
                $voce['messaggio'] = 'Scheda non consultabile con il livello di utenza in uso.';
                $esiti[] = $voce;
                continue;
            }

            if ($destinazione === null) {
                $voce['messaggio'] = 'Catalogo di destinazione non trovato.';
                $esiti[] = $voce;
                continue;
            }
            if (!$destinazione['attivo']) {
                $voce['messaggio'] = 'Il catalogo di destinazione e disattivato.';
                $esiti[] = $voce;
                continue;
            }
            if (strcasecmp($voce['catalogo'], (string) $destinazione['sigla']) === 0) {
                $voce['messaggio'] = 'Gia in questo catalogo.';
                $esiti[] = $voce;
                continue;
            }

            $serie = CodiceCatastale::risolviSerie($destinazione, Ipogeo::attributiPerSerie($scheda));
            if ($serie === null) {
                $voce['messaggio'] = 'Nessuna serie del catalogo di destinazione combacia con questo ipogeo.';
                $esiti[] = $voce;
                continue;
            }

            $prefisso = (string) $serie['prefisso'];
            $consumati[$prefisso] ??= (int) $serie['prossimoProgressivo'];

            // Si salta cio che risulterebbe gia presente, come fa l'assegnazione
            // vera: altrimenti l'anteprima mostrerebbe un codice e ne verrebbe
            // scritto un altro.
            $tentativi = 0;
            do {
                $candidato = CodiceCatastale::componi(
                    $prefisso, $consumati[$prefisso], (int) $serie['cifre'],
                    (string) $destinazione['separatore']
                );
                $consumati[$prefisso]++;
                $tentativi++;
            } while ($tentativi < 1000
                     && (IndiceCodici::esiste($candidato) || CodiceCatastale::cartellaEsistente($candidato)));

            $voce['nuovoCodice'] = $candidato;
            $voce['serie']       = $prefisso;
            $voce['ok']          = true;
            $esiti[] = $voce;
        }

        return $esiti;
    }

    /**
     * Esegue la migrazione di piu ipogei.
     *
     * @param  array<int,string> $codici
     * @return array{
     *     migrati:array<int,array<string,string>>,
     *     falliti:array<int,array{codice:string,messaggio:string}>
     * }
     * @throws IpogeoEccezione
     */
    public static function esegui(array $codici, string $siglaDestinazione, string $motivo = 'migrazione catalogo'): array
    {
        $codici = array_values(array_unique(array_filter(array_map('trim', $codici))));

        if ($codici === []) {
            throw new IpogeoEccezione('Nessun ipogeo indicato.');
        }
        if (count($codici) > self::LIMITE_LOTTO) {
            throw new IpogeoEccezione(
                'Si possono spostare al massimo ' . self::LIMITE_LOTTO . ' ipogei per volta: '
                . 'un elenco piu lungo non lo si controlla davvero prima di confermare.');
        }

        $migrati = [];
        $falliti = [];

        foreach ($codici as $codice) {
            $scheda = Ipogeo::trova($codice);

            if ($scheda !== null && !Visibilita::schedaVisibile(
                (string) $scheda['ubicazione']['riservatezza'],
                (string) $scheda['catasto']['statoScheda']
            )) {
                $falliti[] = ['codice' => $codice,
                              'messaggio' => 'Scheda non consultabile con il livello di utenza in uso.'];
                continue;
            }

            $nome = $scheda === null ? '' : (string) $scheda['identificazione']['nome'];

            try {
                $esito = Ipogeo::migra($codice, $siglaDestinazione, $motivo);

                self::traccia([
                    'codice_precedente'   => $esito['codicePrecedente'],
                    'catalogo_precedente' => $esito['catalogoPrecedente'],
                    'codice_nuovo'        => $esito['codice'],
                    'catalogo_nuovo'      => $siglaDestinazione,
                    'nome'                => $nome,
                    'motivo'              => $motivo,
                    'esito'               => 'riuscita',
                    'dettaglio'           => '',
                ]);

                $migrati[] = [
                    'codicePrecedente' => $esito['codicePrecedente'],
                    'codice'           => $esito['codice'],
                    'nome'             => $nome,
                ];
            } catch (Throwable $e) {
                // Anche il fallimento si traccia: se un lotto si e fermato a
                // meta, il file deve dire dove e perche.
                self::traccia([
                    'codice_precedente'   => $codice,
                    'catalogo_precedente' => $scheda === null ? '' : (string) $scheda['catasto']['catalogo'],
                    'codice_nuovo'        => '',
                    'catalogo_nuovo'      => $siglaDestinazione,
                    'nome'                => $nome,
                    'motivo'              => $motivo,
                    'esito'               => 'fallita',
                    'dettaglio'           => $e->getMessage(),
                ]);

                $falliti[] = ['codice' => $codice, 'messaggio' => $e->getMessage()];
            }
        }

        return ['migrati' => $migrati, 'falliti' => $falliti];
    }

    /**
     * Righe del tracciato, dalla piu recente.
     *
     * @return array<int,array<string,string>>
     */
    public static function tracciato(int $limite = 200): array
    {
        $percorso = self::percorsoTracciato();
        if (!is_file($percorso)) {
            return [];
        }

        $righe = [];
        Csv::leggi($percorso, static function (array $riga) use (&$righe): bool {
            $righe[] = $riga;

            return true;
        });

        $righe = array_reverse($righe);

        return $limite > 0 ? array_slice($righe, 0, $limite) : $righe;
    }

    public static function percorsoTracciato(): string
    {
        return Percorsi::log(self::FILE_TRACCIATO);
    }

    /** @param array<string,string> $dati */
    private static function traccia(array $dati): void
    {
        $riga = ['data' => date('Y-m-d\TH:i:s'), 'utente' => Auth::usernameCorrente()];
        foreach (self::COLONNE as $colonna) {
            $riga[$colonna] ??= (string) ($dati[$colonna] ?? '');
        }

        try {
            Csv::accoda(self::percorsoTracciato(), self::COLONNE, $riga);
        } catch (Throwable $e) {
            // Un tracciato non scrivibile non deve far fallire una migrazione
            // gia avvenuta: si annota altrove e si prosegue.
            Log::errore('Tracciato delle migrazioni non scrivibile: ' . $e->getMessage());
        }
    }
}
