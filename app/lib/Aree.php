<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Aree.php
 *  Descrizione ..: Anagrafica delle aree speleologiche (dati/aree.xml, 9.17.5).
 *
 *                  Un'area e un raggruppamento geografico con un nome proprio,
 *                  indipendente dai confini amministrativi. «Alto Chiascio» e
 *                  il modo in cui uno speleologo colloca una cavita, e non
 *                  coincide con regione, provincia o comune — che restano,
 *                  perche servono per altro: la provincia serve a chi scrive a
 *                  un ente, l'area serve a chi programma un'uscita.
 *
 *                  L'area non ha una geometria. Disegnarne il perimetro
 *                  sembrerebbe piu preciso e sarebbe una precisione finta: i
 *                  confini di un'area speleologica sono di uso e non di
 *                  cartografia, cambiano con la conoscenza del carsismo, e un
 *                  poligono sbagliato escluderebbe cavita che tutti
 *                  considerano dentro. Si registra invece un punto indicativo,
 *                  che serve solo a inquadrare la mappa.
 *
 *                  L'appartenenza e dichiarata sulla scheda dell'ipogeo, non
 *                  qui: e un dato della cavita, e tenerlo in due posti
 *                  significherebbe doverlo tenere allineato.
 *  Versione .....: 1.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.1.0  2026-08-07  D.Candela  Prima stesura (fase 12).
 * ============================================================================
 */

final class Aree extends Anagrafica
{
    protected static function nomeFile(): string     { return 'aree.xml'; }
    protected static function nomeRadice(): string   { return 'aree'; }
    protected static function nomeElemento(): string { return 'area'; }
    protected static function prefissoId(): string   { return 'AS'; }
    protected static function nomeXsd(): ?string     { return 'aree.xsd'; }

    /** @return array<string,mixed> */
    protected static function daNodo(DOMElement $nodo): array
    {
        return [
            'id'          => $nodo->getAttribute('id'),
            'nome'        => Xml::testo($nodo, 'nome'),
            'regione'     => Xml::testo($nodo, 'regione'),
            'provincia'   => Xml::testo($nodo, 'provincia'),
            'massiccio'   => Xml::testo($nodo, 'massiccio'),
            'litologia'   => Xml::testo($nodo, 'litologia'),
            'latitudine'  => Xml::testo($nodo, 'latitudine'),
            'longitudine' => Xml::testo($nodo, 'longitudine'),
            'descrizione' => Xml::testo($nodo, 'descrizione'),
            'note'        => Xml::testo($nodo, 'note'),
            'attivo'      => Xml::booleano($nodo, 'attivo', true),
        ];
    }

    /** @param array<string,mixed> $dati */
    protected static function scriviNodo(DOMElement $nodo, array $dati): void
    {
        Xml::imposta($nodo, 'nome', trim((string) ($dati['nome'] ?? '')));
        Xml::imposta($nodo, 'regione', trim((string) ($dati['regione'] ?? '')));
        Xml::imposta($nodo, 'provincia', strtoupper(trim((string) ($dati['provincia'] ?? ''))));
        Xml::imposta($nodo, 'massiccio', trim((string) ($dati['massiccio'] ?? '')));
        Xml::imposta($nodo, 'litologia', trim((string) ($dati['litologia'] ?? '')));
        Xml::imposta($nodo, 'latitudine', trim((string) ($dati['latitudine'] ?? '')));
        Xml::imposta($nodo, 'longitudine', trim((string) ($dati['longitudine'] ?? '')));
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
            throw new AnagraficaEccezione('Il nome dell\'area e obbligatorio.');
        }

        /*
         * Il nome deve essere unico: e la sola cosa che identifica un'area in
         * un elenco, e due «Alto Chiascio» renderebbero impossibile capire a
         * quale delle due una scheda appartiene.
         */
        foreach (self::elenco() as $area) {
            if ((string) $area['id'] === (string) $idEsistente) {
                continue;
            }
            if (strcasecmp(trim((string) $area['nome']), $nome) === 0) {
                throw new AnagraficaEccezione(
                    'Esiste gia un\'area con questo nome: ' . (string) $area['nome'] . '.'
                );
            }
        }

        // Le coordinate sono facoltative, ma se ci sono devono stare al mondo.
        foreach (['latitudine' => 90.0, 'longitudine' => 180.0] as $campo => $massimo) {
            $valore = trim(str_replace(',', '.', (string) ($dati[$campo] ?? '')));
            if ($valore === '') {
                continue;
            }
            if (!is_numeric($valore) || abs((float) $valore) > $massimo) {
                throw new AnagraficaEccezione(
                    ucfirst($campo) . ' fuori intervallo: attesa fra -' . $massimo . ' e ' . $massimo . '.'
                );
            }
        }
    }

    /** @param array<string,mixed> $voce */
    public static function etichetta(array $voce): string
    {
        $nome    = trim((string) ($voce['nome'] ?? ''));
        $regione = trim((string) ($voce['regione'] ?? ''));

        return $regione !== '' ? $nome . ' (' . $regione . ')' : $nome;
    }

    /**
     * Quanti ipogei sono assegnati all'area.
     *
     * Si conta sull'indice e non aprendo le schede: e un conteggio che serve
     * anche solo per mostrare l'elenco delle aree, e mille XML aperti per
     * disegnare una pagina sono mille di troppo.
     *
     * @return array<string,int>
     */
    public static function usi(string $id): array
    {
        $ipogei = self::usiNellIndice('area', $id);

        return $ipogei > 0 ? ['ipogei assegnati' => $ipogei] : [];
    }
}
