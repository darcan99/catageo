<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Complessi.php
 *  Descrizione ..: Anagrafica dei complessi (dati/complessi.xml, 9.17.4).
 *
 *                  Un complesso e un insieme di cavita che formano un sistema
 *                  unico: il Complesso X ha un nome proprio, una bibliografia
 *                  sua ed e la cosa di cui si parla in letteratura, mentre le
 *                  schede che lo compongono sono il modo in cui il catasto lo
 *                  registra. Le relazioni fra ipogei (<collegamenti>) restano e
 *                  sono un'altra cosa: dicono che due cavita comunicano, non
 *                  che insieme sono un oggetto con un nome.
 *
 *                  **Nessun codice assegnato da una serie di codifica.** Il
 *                  committente e stato esplicito: un complesso non ha un codice
 *                  catastale, e un nome che raggruppa. Consumare progressivi
 *                  del catasto per un oggetto che non e una cavita
 *                  significherebbe bruciare numeri che poi mancano alle cavita
 *                  vere. Resta un **codice proprio facoltativo**, campo libero,
 *                  per chi — tipicamente su cavita artificiali — vuole
 *                  numerare i propri complessi con una convenzione sua.
 *
 *                  I totali non si digitano: si sommano dalle schede. Uno
 *                  sviluppo complessivo scritto a mano diverge dalla somma al
 *                  primo aggiornamento di una delle cavita, e poi nessuno sa
 *                  piu quale dei due numeri credere.
 *  Versione .....: 1.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.1.0  2026-08-07  D.Candela  Prima stesura (fase 12).
 * ============================================================================
 */

final class Complessi extends Anagrafica
{
    /**
     * Natura del complesso.
     *
     * "Misto" non e un riempitivo: una cava che intercetta un sistema carsico
     * e il caso in cui le due categorie coesistono davvero, ed e frequente
     * proprio dove il catasto serve di piu.
     */
    public const NATURE = [
        ''       => 'non specificata',
        'carsico' => 'Carsico',
        'artificiale' => 'Artificiale',
        'misto'  => 'Misto',
    ];

    protected static function nomeFile(): string     { return 'complessi.xml'; }
    protected static function nomeRadice(): string   { return 'complessi'; }
    protected static function nomeElemento(): string { return 'complesso'; }
    protected static function prefissoId(): string   { return 'CX'; }
    protected static function nomeXsd(): ?string     { return 'complessi.xsd'; }

    /** @return array<string,mixed> */
    protected static function daNodo(DOMElement $nodo): array
    {
        return [
            'id'          => $nodo->getAttribute('id'),
            'nome'        => Xml::testo($nodo, 'nome'),
            'codice'      => Xml::testo($nodo, 'codice'),
            'natura'      => Xml::testo($nodo, 'natura'),
            'regione'     => Xml::testo($nodo, 'regione'),
            'descrizione' => Xml::testo($nodo, 'descrizione'),
            'note'        => Xml::testo($nodo, 'note'),
            'attivo'      => Xml::booleano($nodo, 'attivo', true),
        ];
    }

    /** @param array<string,mixed> $dati */
    protected static function scriviNodo(DOMElement $nodo, array $dati): void
    {
        Xml::imposta($nodo, 'nome', trim((string) ($dati['nome'] ?? '')));
        Xml::imposta($nodo, 'codice', trim((string) ($dati['codice'] ?? '')));

        $natura = strtolower(trim((string) ($dati['natura'] ?? '')));
        Xml::imposta($nodo, 'natura', isset(self::NATURE[$natura]) ? $natura : '');

        Xml::imposta($nodo, 'regione', trim((string) ($dati['regione'] ?? '')));
        Xml::imposta($nodo, 'descrizione', (string) ($dati['descrizione'] ?? ''), true);
        Xml::imposta($nodo, 'note', (string) ($dati['note'] ?? ''), true);
        Xml::imposta($nodo, 'attivo', !empty($dati['attivo']) ? '1' : '0');
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws AnagraficaEccezione
     */
    protected static function valida(array $dati, ?string $idEsistente): void
    {
        $nome = trim((string) ($dati['nome'] ?? ''));
        if ($nome === '') {
            throw new AnagraficaEccezione('Il nome del complesso e obbligatorio.');
        }

        $codice = trim((string) ($dati['codice'] ?? ''));
        if ($codice !== '' && !preg_match('/^[A-Za-z0-9._\-\/ ]{1,40}$/', $codice)) {
            throw new AnagraficaEccezione(
                'Codice proprio non valido: fino a 40 caratteri fra lettere, cifre, '
                . 'punto, trattino, barra e spazio.'
            );
        }

        // Nome e codice proprio devono essere unici, per lo stesso motivo: sono
        // le due cose con cui si nomina un complesso, e un doppione renderebbe
        // ambiguo il riferimento in scheda e in bibliografia.
        foreach (self::elenco() as $voce) {
            if ((string) $voce['id'] === (string) $idEsistente) {
                continue;
            }
            if (strcasecmp(trim((string) $voce['nome']), $nome) === 0) {
                throw new AnagraficaEccezione(
                    'Esiste gia un complesso con questo nome: ' . (string) $voce['nome'] . '.'
                );
            }
            if ($codice !== '' && strcasecmp(trim((string) $voce['codice']), $codice) === 0) {
                throw new AnagraficaEccezione(
                    'Il codice "' . $codice . '" e gia usato dal complesso '
                    . (string) $voce['nome'] . '.'
                );
            }
        }
    }

    /** @param array<string,mixed> $voce */
    public static function etichetta(array $voce): string
    {
        $nome   = trim((string) ($voce['nome'] ?? ''));
        $codice = trim((string) ($voce['codice'] ?? ''));

        return $codice !== '' ? $codice . ' — ' . $nome : $nome;
    }

    /**
     * Totali del complesso, sommati dalle schede che vi appartengono.
     *
     * Si legge l'indice in streaming e non le schede: e un conteggio che serve
     * anche solo per disegnare l'elenco dei complessi, e aprire un XML per
     * ciascuna cavita renderebbe la pagina inutilizzabile su un catasto vero.
     *
     * Il filtro di visibilita e quello della consultazione: un totale che
     * comprendesse le cavita riservate direbbe, per differenza, quante ne
     * esistono che non si possono vedere.
     *
     * @return array{ipogei:int,sviluppo:float,dislivello:float}
     */
    public static function totali(string $id): array
    {
        $totali = ['ipogei' => 0, 'sviluppo' => 0.0, 'dislivello' => 0.0];
        if (trim($id) === '') {
            return $totali;
        }

        $percorso = Percorsi::indice('ipogei.csv');
        if (!is_file($percorso)) {
            return $totali;
        }

        $visibile = Visibilita::filtroIndice();
        $numero   = static fn (string $v): float => (float) str_replace(',', '.', trim($v));

        Csv::leggi($percorso, static function (array $riga) use ($id, $visibile, $numero, &$totali): void {
            if (trim((string) ($riga['complesso'] ?? '')) !== $id || !$visibile($riga)) {
                return;
            }
            $totali['ipogei']++;
            $totali['sviluppo']   += $numero((string) ($riga['sviluppo'] ?? ''));
            $totali['dislivello'] += $numero((string) ($riga['dislivello'] ?? ''));
        });

        return $totali;
    }

    /** @return array<string,int> */
    public static function usi(string $id): array
    {
        $ipogei = self::usiNellIndice('complesso', $id);

        return $ipogei > 0 ? ['ipogei che ne fanno parte' => $ipogei] : [];
    }
}
