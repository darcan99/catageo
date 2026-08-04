<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Gruppi.php
 *  Descrizione ..: Anagrafica dei gruppi speleologici
 *                  (dati/gruppi_speleologici.xml).
 *  Versione .....: 0.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

final class Gruppi extends Anagrafica
{
    /** Affiliazioni proposte in interfaccia; il campo resta libero. */
    public const AFFILIAZIONI_SUGGERITE = ['SSI', 'CNSAS', 'FSL', 'UIS', 'CAI'];

    protected static function nomeFile(): string    { return 'gruppi_speleologici.xml'; }
    protected static function nomeRadice(): string  { return 'gruppi'; }
    protected static function nomeElemento(): string { return 'gruppo'; }
    protected static function prefissoId(): string  { return 'G'; }
    protected static function nomeXsd(): ?string    { return 'gruppi.xsd'; }

    /**
     * @return array<string,mixed>
     */
    protected static function daNodo(DOMElement $nodo): array
    {
        $affiliazioni = [];
        foreach (Xml::elenco($nodo, 'affiliazioni/affiliazione') as $af) {
            $valore = trim($af->textContent);
            if ($valore !== '') {
                $affiliazioni[] = $valore;
            }
        }

        return [
            'id'             => $nodo->getAttribute('id'),
            'sigla'          => Xml::testo($nodo, 'sigla'),
            'nome'           => Xml::testo($nodo, 'nome'),
            'sedeComune'     => Xml::testo($nodo, 'sedeComune'),
            'sedeProvincia'  => Xml::testo($nodo, 'sedeProvincia'),
            'indirizzo'      => Xml::testo($nodo, 'indirizzo'),
            'email'          => Xml::testo($nodo, 'email'),
            'telefono'       => Xml::testo($nodo, 'telefono'),
            'sitoWeb'        => Xml::testo($nodo, 'sitoWeb'),
            'annoFondazione' => Xml::testo($nodo, 'annoFondazione'),
            'affiliazioni'   => $affiliazioni,
            'note'           => Xml::testo($nodo, 'note'),
            'attivo'         => Xml::booleano($nodo, 'attivo', true),
        ];
    }

    /**
     * @param array<string,mixed> $dati
     */
    protected static function scriviNodo(DOMElement $nodo, array $dati): void
    {
        Xml::imposta($nodo, 'sigla', strtoupper(trim((string) ($dati['sigla'] ?? ''))));
        Xml::imposta($nodo, 'nome', trim((string) ($dati['nome'] ?? '')));
        Xml::imposta($nodo, 'sedeComune', trim((string) ($dati['sedeComune'] ?? '')));
        Xml::imposta($nodo, 'sedeProvincia', strtoupper(trim((string) ($dati['sedeProvincia'] ?? ''))));
        Xml::imposta($nodo, 'indirizzo', trim((string) ($dati['indirizzo'] ?? '')));
        Xml::imposta($nodo, 'email', trim((string) ($dati['email'] ?? '')));
        Xml::imposta($nodo, 'telefono', trim((string) ($dati['telefono'] ?? '')));
        Xml::imposta($nodo, 'sitoWeb', trim((string) ($dati['sitoWeb'] ?? '')));
        Xml::imposta($nodo, 'annoFondazione', trim((string) ($dati['annoFondazione'] ?? '')));

        // L'elenco delle affiliazioni viene riscritto per intero: si sostituisce
        // il contenitore invece di aggiornare voce per voce.
        $contenitore = Xml::imposta($nodo, 'affiliazioni', null);
        $affiliazioni = $dati['affiliazioni'] ?? [];
        if (is_string($affiliazioni)) {
            $affiliazioni = array_map('trim', explode(',', $affiliazioni));
        }
        foreach ((array) $affiliazioni as $affiliazione) {
            $valore = trim((string) $affiliazione);
            if ($valore !== '') {
                Xml::aggiungi($contenitore, 'affiliazione', $valore);
            }
        }

        // Le note sono testo libero senza limiti di lunghezza (D6): CDATA.
        Xml::imposta($nodo, 'note', (string) ($dati['note'] ?? ''), true);
        Xml::imposta($nodo, 'attivo', !empty($dati['attivo']) ? '1' : '0');
    }

    /**
     * @param  array<string,mixed> $dati
     * @throws AnagraficaEccezione
     */
    protected static function valida(array $dati, ?string $idEsistente): void
    {
        $nome  = trim((string) ($dati['nome'] ?? ''));
        $sigla = strtoupper(trim((string) ($dati['sigla'] ?? '')));

        if ($nome === '') {
            throw new AnagraficaEccezione('Il nome del gruppo e obbligatorio.');
        }
        if ($sigla === '') {
            throw new AnagraficaEccezione('La sigla del gruppo e obbligatoria: e quella che compare negli elenchi compatti.');
        }
        if (!preg_match('/^[A-Z0-9.\-]{1,20}$/', $sigla)) {
            throw new AnagraficaEccezione('Sigla non valida: fino a 20 caratteri fra lettere, cifre, punto e trattino.');
        }

        $email = trim((string) ($dati['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AnagraficaEccezione('Indirizzo email non valido.');
        }

        $sito = trim((string) ($dati['sitoWeb'] ?? ''));
        if ($sito !== '' && !filter_var($sito, FILTER_VALIDATE_URL)) {
            throw new AnagraficaEccezione('Indirizzo del sito web non valido: indicarlo per esteso, con http:// o https://.');
        }

        $anno = trim((string) ($dati['annoFondazione'] ?? ''));
        if ($anno !== '') {
            if (!preg_match('/^[0-9]{4}$/', $anno)) {
                throw new AnagraficaEccezione('L\'anno di fondazione va indicato con quattro cifre.');
            }
            // Il primo gruppo speleologico italiano nasce nell'Ottocento: un
            // anno precedente e con certezza un errore di battitura.
            if ((int) $anno < 1800 || (int) $anno > (int) date('Y')) {
                throw new AnagraficaEccezione('Anno di fondazione fuori intervallo plausibile (1800 - ' . date('Y') . ').');
            }
        }

        $provincia = strtoupper(trim((string) ($dati['sedeProvincia'] ?? '')));
        if ($provincia !== '' && !preg_match('/^[A-Z]{2}$/', $provincia)) {
            throw new AnagraficaEccezione('La provincia va indicata con la sigla di due lettere.');
        }

        // Sigla univoca: e usata come riferimento breve negli elenchi, due
        // gruppi con la stessa sigla renderebbero gli elenchi ambigui.
        foreach (static::elenco() as $voce) {
            if ((string) $voce['id'] === (string) $idEsistente) {
                continue;
            }
            if (strcasecmp((string) $voce['sigla'], $sigla) === 0) {
                throw new AnagraficaEccezione("La sigla \"{$sigla}\" e gia usata dal gruppo \"{$voce['nome']}\".");
            }
        }
    }

    /**
     * @param array<string,mixed> $voce
     */
    public static function etichetta(array $voce): string
    {
        $sigla = (string) ($voce['sigla'] ?? '');
        $nome  = (string) ($voce['nome'] ?? '');
        return $sigla !== '' ? $sigla . ' — ' . $nome : $nome;
    }

    /**
     * @return array<string,int>
     */
    public static function usi(string $id): array
    {
        $usi = [];

        // Appartenenze registrate nell'anagrafica degli esploratori.
        $esploratori = 0;
        foreach (Esploratori::elenco() as $esploratore) {
            foreach ($esploratore['gruppi'] as $appartenenza) {
                if (($appartenenza['id'] ?? '') === $id) {
                    $esploratori++;
                    break;
                }
            }
        }
        if ($esploratori > 0) {
            $usi['appartenenze di esploratori'] = $esploratori;
        }

        return $usi;
    }
}
