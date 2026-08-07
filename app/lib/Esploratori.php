<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Esploratori.php
 *  Descrizione ..: Anagrafica degli esploratori (dati/esploratori.xml), con
 *                  appartenenza storicizzata ai gruppi speleologici: la stessa
 *                  persona puo aver militato in gruppi diversi in periodi
 *                  diversi, e un diario del 1998 deve restare attribuito al
 *                  gruppo di allora.
 *  Versione .....: 0.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

final class Esploratori extends Anagrafica
{
    protected static function nomeFile(): string     { return 'esploratori.xml'; }
    protected static function nomeRadice(): string    { return 'esploratori'; }
    protected static function nomeElemento(): string  { return 'esploratore'; }
    protected static function prefissoId(): string    { return 'E'; }
    protected static function nomeXsd(): ?string      { return 'esploratori.xsd'; }

    /**
     * @return array<string,mixed>
     */
    protected static function daNodo(DOMElement $nodo): array
    {
        $gruppi = [];
        foreach (Xml::elenco($nodo, 'gruppi/gruppo') as $g) {
            $gruppi[] = [
                'id'  => $g->getAttribute('id'),
                'dal' => $g->getAttribute('dal'),
                'al'  => $g->getAttribute('al'),
            ];
        }

        $qualifiche = [];
        foreach (Xml::elenco($nodo, 'qualifiche/qualifica') as $q) {
            $valore = trim($q->textContent);
            if ($valore !== '') {
                $qualifiche[] = $valore;
            }
        }

        return [
            'id'         => $nodo->getAttribute('id'),
            'cognome'    => Xml::testo($nodo, 'cognome'),
            'nome'       => Xml::testo($nodo, 'nome'),
            'soprannome' => Xml::testo($nodo, 'soprannome'),
            'gruppi'     => $gruppi,
            'email'      => Xml::testo($nodo, 'email'),
            'telefono'   => Xml::testo($nodo, 'telefono'),
            'qualifiche' => $qualifiche,
            'note'       => Xml::testo($nodo, 'note'),
            'attivo'     => Xml::booleano($nodo, 'attivo', true),
        ];
    }

    /**
     * @param array<string,mixed> $dati
     */
    protected static function scriviNodo(DOMElement $nodo, array $dati): void
    {
        Xml::imposta($nodo, 'cognome', trim((string) ($dati['cognome'] ?? '')));
        Xml::imposta($nodo, 'nome', trim((string) ($dati['nome'] ?? '')));
        Xml::imposta($nodo, 'soprannome', trim((string) ($dati['soprannome'] ?? '')));

        $contenitore = Xml::imposta($nodo, 'gruppi', null);
        foreach (self::normalizzaAppartenenze($dati['gruppi'] ?? []) as $appartenenza) {
            Xml::aggiungi($contenitore, 'gruppo', null, [
                'id'  => $appartenenza['id'],
                'dal' => $appartenenza['dal'],
                'al'  => $appartenenza['al'],
            ]);
        }

        Xml::imposta($nodo, 'email', trim((string) ($dati['email'] ?? '')));
        Xml::imposta($nodo, 'telefono', trim((string) ($dati['telefono'] ?? '')));

        $qualifiche = $dati['qualifiche'] ?? [];
        if (is_string($qualifiche)) {
            $qualifiche = explode(',', $qualifiche);
        }
        $contenitoreQ = Xml::imposta($nodo, 'qualifiche', null);
        foreach ((array) $qualifiche as $qualifica) {
            $valore = trim((string) $qualifica);
            if ($valore !== '') {
                Xml::aggiungi($contenitoreQ, 'qualifica', $valore);
            }
        }

        Xml::imposta($nodo, 'note', (string) ($dati['note'] ?? ''), true);
        Xml::imposta($nodo, 'attivo', !empty($dati['attivo']) ? '1' : '0');
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws AnagraficaEccezione
     */
    protected static function valida(array $dati, ?string $idEsistente): void
    {
        $cognome = trim((string) ($dati['cognome'] ?? ''));
        $nome    = trim((string) ($dati['nome'] ?? ''));

        if ($cognome === '') {
            throw new AnagraficaEccezione('Il cognome è obbligatorio.');
        }
        if ($nome === '') {
            throw new AnagraficaEccezione('Il nome è obbligatorio.');
        }

        $email = trim((string) ($dati['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AnagraficaEccezione('Indirizzo email non valido.');
        }

        // Omonimia: non si vieta, perche due omonimi possono esistere davvero.
        // Si segnala pero se manca il soprannome, che e il modo con cui i gruppi
        // distinguono di fatto le persone.
        $stessoNome = false;
        foreach (static::elenco() as $voce) {
            if ((string) $voce['id'] === (string) $idEsistente) {
                continue;
            }
            if (strcasecmp((string) $voce['cognome'], $cognome) === 0
                && strcasecmp((string) $voce['nome'], $nome) === 0) {
                $stessoNome = true;
                break;
            }
        }
        if ($stessoNome && trim((string) ($dati['soprannome'] ?? '')) === '') {
            throw new AnagraficaEccezione(
                "Esiste già un esploratore con nome \"{$nome} {$cognome}\". "
                . 'Se sono due persone diverse, indicare un soprannome per distinguerle negli elenchi.'
            );
        }

        foreach (self::normalizzaAppartenenze($dati['gruppi'] ?? []) as $appartenenza) {
            if (Gruppi::trova($appartenenza['id']) === null) {
                throw new AnagraficaEccezione("Gruppo non trovato: {$appartenenza['id']}.");
            }
            foreach (['dal' => 'iniziale', 'al' => 'finale'] as $campo => $etichetta) {
                $anno = $appartenenza[$campo];
                if ($anno !== '' && (!preg_match('/^[0-9]{4}$/', $anno) || (int) $anno < 1800 || (int) $anno > (int) date('Y') + 1)) {
                    throw new AnagraficaEccezione("Anno {$etichetta} di appartenenza non valido: {$anno}.");
                }
            }
            if ($appartenenza['dal'] !== '' && $appartenenza['al'] !== ''
                && (int) $appartenenza['al'] < (int) $appartenenza['dal']) {
                throw new AnagraficaEccezione('In un\'appartenenza l\'anno finale non può precedere quello iniziale.');
            }
        }

        self::validaSovrapposizioni(self::normalizzaAppartenenze($dati['gruppi'] ?? []));
    }

    /**
     * Verifica che due periodi dello STESSO gruppo non si sovrappongano.
     *
     * Lo stesso gruppo puo ricorrere piu volte (si lascia e si rientra) e
     * gruppi diversi possono essere contemporanei: l'iscrizione simultanea a
     * piu gruppi e la norma. Due periodi dello stesso gruppo che si accavallano
     * sono invece contraddittori, e quasi sempre un anno digitato male.
     *
     * Il confine condiviso e ammesso: uscire nel 2020 e rientrare nel 2020 e
     * plausibile, quindi 2018-2020 e 2020-2022 non sono in conflitto.
     *
     * @param  array<int,array{id:string,dal:string,al:string}> $appartenenze
     * @throws AnagraficaEccezione
     */
    private static function validaSovrapposizioni(array $appartenenze): void
    {
        $perGruppo = [];
        foreach ($appartenenze as $appartenenza) {
            $perGruppo[$appartenenza['id']][] = $appartenenza;
        }

        foreach ($perGruppo as $idGruppo => $periodi) {
            $totale = count($periodi);
            for ($i = 0; $i < $totale; $i++) {
                for ($j = $i + 1; $j < $totale; $j++) {
                    $inizioA = $periodi[$i]['dal'] !== '' ? (int) $periodi[$i]['dal'] : 0;
                    $fineA   = $periodi[$i]['al'] !== '' ? (int) $periodi[$i]['al'] : PHP_INT_MAX;
                    $inizioB = $periodi[$j]['dal'] !== '' ? (int) $periodi[$j]['dal'] : 0;
                    $fineB   = $periodi[$j]['al'] !== '' ? (int) $periodi[$j]['al'] : PHP_INT_MAX;

                    if ($inizioA < $fineB && $inizioB < $fineA) {
                        $gruppo    = Gruppi::trova((string) $idGruppo);
                        $etichetta = $gruppo !== null ? (string) $gruppo['sigla'] : (string) $idGruppo;

                        throw new AnagraficaEccezione(
                            "Due periodi di appartenenza al gruppo {$etichetta} si sovrappongono ("
                            . self::periodoLeggibile($periodi[$i]) . ' e ' . self::periodoLeggibile($periodi[$j])
                            . '). Lo stesso gruppo può ricorrere più volte, ma i periodi devono essere distinti.'
                        );
                    }
                }
            }
        }
    }

    /**
     * Periodo di un'appartenenza in forma leggibile.
     *
     * @param array{id:string,dal:string,al:string} $appartenenza
     */
    public static function periodoLeggibile(array $appartenenza): string
    {
        $dal = $appartenenza['dal'] !== '' ? $appartenenza['dal'] : '?';
        $al  = $appartenenza['al'] !== '' ? $appartenenza['al'] : 'oggi';

        return $dal === '?' && $al === 'oggi' ? 'periodo non indicato' : 'dal ' . $dal . ' al ' . $al;
    }

    /** True se l'appartenenza e ancora in corso (anno finale non indicato). */
    public static function appartenenzaInCorso(array $appartenenza): bool
    {
        return trim((string) ($appartenenza['al'] ?? '')) === '';
    }

    /**
     * @param array<string,mixed> $voce
     */
    public static function etichetta(array $voce): string
    {
        $etichetta = trim(((string) ($voce['cognome'] ?? '')) . ' ' . ((string) ($voce['nome'] ?? '')));
        $soprannome = trim((string) ($voce['soprannome'] ?? ''));
        return $soprannome !== '' ? $etichetta . ' (' . $soprannome . ')' : $etichetta;
    }

    /**
     * Ordinamento per cognome e nome, con i disattivati in fondo.
     *
     * @param array<int,array<string,mixed>> $elenco
     */
    protected static function ordina(array &$elenco): void
    {
        usort($elenco, static function (array $a, array $b): int {
            if ($a['attivo'] !== $b['attivo']) {
                return $a['attivo'] ? -1 : 1;
            }
            $c = strcasecmp((string) $a['cognome'], (string) $b['cognome']);
            return $c !== 0 ? $c : strcasecmp((string) $a['nome'], (string) $b['nome']);
        });
    }

    /**
     * @return array<string,int>
     */
    public static function usi(string $id): array
    {
        $usi = [];

        // Utenti dell'applicativo collegati a questo esploratore.
        $utenti = 0;
        foreach (Utenti::elenco() as $utente) {
            if ((string) $utente['esploratoreId'] === $id) {
                $utenti++;
            }
        }
        if ($utenti > 0) {
            $usi['utenze collegate'] = $utenti;
        }

        return $usi;
    }

    /**
     * Gruppi di appartenenza a una certa data, per attribuire correttamente
     * un'esplorazione storica.
     *
     * Restituisce un elenco e non un singolo gruppo: l'iscrizione simultanea a
     * piu gruppi e la norma fra gli speleologi, e scegliere arbitrariamente il
     * primo attribuirebbe l'esplorazione al gruppo sbagliato.
     *
     * @return string[] id dei gruppi attivi a quella data, in ordine cronologico
     */
    public static function gruppiAllaData(string $idEsploratore, string $data): array
    {
        $esploratore = static::trova($idEsploratore);
        if ($esploratore === null) {
            return [];
        }

        $anno = (int) substr($data, 0, 4);
        if ($anno <= 0) {
            return [];
        }

        $gruppi = [];
        foreach ($esploratore['gruppi'] as $appartenenza) {
            $dal = $appartenenza['dal'] !== '' ? (int) $appartenenza['dal'] : null;
            $al  = $appartenenza['al'] !== '' ? (int) $appartenenza['al'] : null;

            if (($dal === null || $anno >= $dal) && ($al === null || $anno <= $al)) {
                if (!in_array($appartenenza['id'], $gruppi, true)) {
                    $gruppi[] = $appartenenza['id'];
                }
            }
        }

        return $gruppi;
    }

    /**
     * Gruppi a cui l'esploratore risulta iscritto adesso.
     *
     * @return string[]
     */
    public static function gruppiAttuali(string $idEsploratore): array
    {
        return self::gruppiAllaData($idEsploratore, date('Y-m-d'));
    }

    /**
     * Normalizza l'elenco delle appartenenze proveniente dal form.
     *
     * Lo stesso gruppo puo comparire piu volte con periodi diversi: uno
     * speleologo lascia un gruppo e dopo qualche anno vi rientra, e la storia
     * va conservata per intero. Si scartano soltanto le righe vuote e i
     * duplicati esatti, cioe stesso gruppo con gli stessi due anni, che sono
     * un errore di inserimento e non un'informazione.
     *
     * @param  mixed $valore
     * @return array<int,array{id:string,dal:string,al:string}>
     */
    private static function normalizzaAppartenenze(mixed $valore): array
    {
        if (!is_array($valore)) {
            return [];
        }

        $risultato = [];
        $visti     = [];

        foreach ($valore as $riga) {
            if (!is_array($riga)) {
                continue;
            }
            $id = trim((string) ($riga['id'] ?? ''));
            if ($id === '') {
                continue; // riga vuota del form
            }

            $dal = trim((string) ($riga['dal'] ?? ''));
            $al  = trim((string) ($riga['al'] ?? ''));

            $chiave = $id . '|' . $dal . '|' . $al;
            if (isset($visti[$chiave])) {
                continue; // duplicato esatto
            }
            $visti[$chiave] = true;

            $risultato[] = ['id' => $id, 'dal' => $dal, 'al' => $al];
        }

        return self::ordinaAppartenenze($risultato);
    }

    /**
     * Ordina le appartenenze cronologicamente, dalla piu vecchia.
     *
     * Le appartenenze in corso (anno finale vuoto) restano in fondo entro il
     * proprio anno di inizio: sono quelle che interessano di piu ed e comodo
     * trovarle sempre nello stesso posto.
     *
     * @param  array<int,array{id:string,dal:string,al:string}> $appartenenze
     * @return array<int,array{id:string,dal:string,al:string}>
     */
    private static function ordinaAppartenenze(array $appartenenze): array
    {
        usort($appartenenze, static function (array $a, array $b): int {
            // Anno iniziale ignoto: si considera il piu antico possibile,
            // altrimenti finirebbe in coda pur essendo probabilmente il primo.
            $inizioA = $a['dal'] !== '' ? (int) $a['dal'] : 0;
            $inizioB = $b['dal'] !== '' ? (int) $b['dal'] : 0;
            if ($inizioA !== $inizioB) {
                return $inizioA <=> $inizioB;
            }

            $fineA = $a['al'] !== '' ? (int) $a['al'] : PHP_INT_MAX;
            $fineB = $b['al'] !== '' ? (int) $b['al'] : PHP_INT_MAX;
            if ($fineA !== $fineB) {
                return $fineA <=> $fineB;
            }

            return strcmp($a['id'], $b['id']);
        });

        return $appartenenze;
    }
}
