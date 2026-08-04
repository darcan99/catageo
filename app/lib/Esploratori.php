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
            throw new AnagraficaEccezione('Il cognome e obbligatorio.');
        }
        if ($nome === '') {
            throw new AnagraficaEccezione('Il nome e obbligatorio.');
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
                "Esiste gia un esploratore con nome \"{$nome} {$cognome}\". "
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
                throw new AnagraficaEccezione('In un\'appartenenza l\'anno finale non puo precedere quello iniziale.');
            }
        }
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
     * Gruppo di appartenenza a una certa data, utile per attribuire
     * correttamente un'esplorazione storica.
     *
     * @return string|null id del gruppo, o null se non determinabile
     */
    public static function gruppoAllaData(string $idEsploratore, string $data): ?string
    {
        $esploratore = static::trova($idEsploratore);
        if ($esploratore === null) {
            return null;
        }

        $anno = (int) substr($data, 0, 4);
        if ($anno <= 0) {
            return null;
        }

        foreach ($esploratore['gruppi'] as $appartenenza) {
            $dal = $appartenenza['dal'] !== '' ? (int) $appartenenza['dal'] : null;
            $al  = $appartenenza['al'] !== '' ? (int) $appartenenza['al'] : null;

            if (($dal === null || $anno >= $dal) && ($al === null || $anno <= $al)) {
                return $appartenenza['id'];
            }
        }

        return null;
    }

    /**
     * Normalizza l'elenco delle appartenenze proveniente dal form.
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
            if ($id === '' || isset($visti[$id])) {
                continue; // riga vuota o gruppo ripetuto
            }
            $visti[$id]  = true;
            $risultato[] = [
                'id'  => $id,
                'dal' => trim((string) ($riga['dal'] ?? '')),
                'al'  => trim((string) ($riga['al'] ?? '')),
            ];
        }

        return $risultato;
    }
}
