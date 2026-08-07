<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Esportazione.php
 *  Descrizione ..: Esportazione di un insieme di ipogei in GeoJSON, KML e CSV
 *                  (10).
 *
 *                  Sta in una classe sua perche gli stessi ipogei si esportano
 *                  da tre punti — la mappa, i risultati di ricerca, una
 *                  selezione — e la forma di una "feature" scritta in tre posti
 *                  e la prima cosa che diverge: il giorno che si aggiunge una
 *                  proprieta, due dei tre export non ce l'hanno.
 *
 *                  Le coordinate passano SEMPRE per Visibilita::coordinate():
 *                  un export non deve essere la via di servizio con cui si
 *                  ottengono le posizioni esatte che l'interfaccia offusca.
 *  Versione .....: 0.13.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.13.0  2026-08-06  D.Candela  Prima stesura (fase 8).
 * ============================================================================
 */

final class Esportazione
{
    /** Colonne del CSV esportato, in ordine. */
    public const COLONNE_CSV = [
        'catalogo', 'codice', 'nome', 'natura', 'tipologia', 'sottotipologia',
        'stato', 'regione', 'provincia', 'comune', 'localita',
        'lat', 'lon', 'quota', 'sviluppo', 'dislivello',
        'stato_accesso', 'riservatezza', 'stato_scheda',
        'n_foto', 'n_rilievi', 'n_esplorazioni', 'n_biblio', 'n_serie_misure',
        'data_censimento',
    ];

    /**
     * Una feature GeoJSON da una riga di indice, o null se manca la posizione.
     *
     * @param  array<string,string> $riga
     * @return array<string,mixed>|null
     */
    public static function feature(array $riga): ?array
    {
        $coord = Visibilita::coordinateDaRiga($riga);

        if (trim($coord['lat']) === '' || trim($coord['lon']) === '') {
            return null;
        }

        $proprieta = [
            'codice'        => (string) ($riga['codice'] ?? ''),
            'nome'          => (string) ($riga['nome'] ?? ''),
            'catalogo'      => (string) ($riga['catalogo'] ?? ''),
            'natura'        => (string) ($riga['natura'] ?? ''),
            'tipologia'     => (string) ($riga['tipologia'] ?? ''),
            'tipologiaNome' => Tipologie::nome((string) ($riga['tipologia'] ?? '')),
            /*
             * Il nome del glifo, non il glifo: chi disegna e il browser, che ha
             * gia il font delle icone. Si risolve qui e non nel JavaScript
             * perche l'ereditarieta lungo la tassonomia la conosce il
             * vocabolario, e mandarla al browser vorrebbe dire spedire tutto
             * l'albero delle tipologie a ogni richiesta della mappa.
             */
            'icona'         => Tipologie::icona((string) ($riga['tipologia'] ?? '')),
            'comune'        => (string) ($riga['comune'] ?? ''),
            'localita'      => (string) ($riga['localita'] ?? ''),
            'quota'         => (string) ($riga['quota'] ?? ''),
            'sviluppo'      => (string) ($riga['sviluppo'] ?? ''),
            'dislivello'    => (string) ($riga['dislivello'] ?? ''),
            'statoAccesso'  => (string) ($riga['stato_accesso'] ?? ''),
            'statoScheda'   => (string) ($riga['stato_scheda'] ?? ''),
            'riservatezza'  => (string) ($riga['riservatezza'] ?? ''),
            'offuscate'     => $coord['offuscate'],
            'nFoto'         => (int) ($riga['n_foto'] ?? 0),
            'nRilievi'      => (int) ($riga['n_rilievi'] ?? 0),
            'nEsplorazioni' => (int) ($riga['n_esplorazioni'] ?? 0),
            'haKml'         => ($riga['ha_kml'] ?? '') === '1',
            'url'           => 'index.php?p=ipogei&azione=scheda&codice='
                             . urlencode((string) ($riga['codice'] ?? '')),
        ];

        // La distanza c'e solo nei risultati di una ricerca per raggio: si
        // include quando c'e, cosi la mappa puo mostrarla senza ricalcolarla.
        if (isset($riga['distanza'])) {
            $proprieta['distanza'] = round((float) $riga['distanza']);
        }

        return [
            'type'     => 'Feature',
            'geometry' => [
                'type' => 'Point',
                // GeoJSON vuole longitudine prima della latitudine: e la
                // sorgente piu frequente di marker finiti in mezzo al mare.
                'coordinates' => [(float) $coord['lon'], (float) $coord['lat']],
            ],
            'properties' => $proprieta,
        ];
    }

    /**
     * Raccolta GeoJSON.
     *
     * @param  array<int,array<string,string>> $righe
     * @return array<string,mixed>
     */
    public static function geojson(array $righe): array
    {
        $elementi = [];
        $senzaCoordinate = 0;

        foreach ($righe as $riga) {
            $feature = self::feature($riga);
            if ($feature === null) {
                $senzaCoordinate++;
                continue;
            }
            $elementi[] = $feature;
        }

        return [
            'type'     => 'FeatureCollection',
            'features' => $elementi,
            // Metadati fuori dallo standard ma dentro l'oggetto: comodi per
            // l'interfaccia e ignorati da qualunque lettore conforme.
            'catageo'  => [
                'totale'          => count($elementi),
                'senzaCoordinate' => $senzaCoordinate,
                'generato'        => date('c'),
            ],
        ];
    }

    /**
     * KML, per Google Earth e per i navigatori.
     *
     * @param array<int,array<string,string>> $righe
     */
    public static function kml(array $righe, string $titolo = 'CATAGEO'): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $kml = $doc->createElementNS('http://www.opengis.net/kml/2.2', 'kml');
        $doc->appendChild($kml);

        $documento = $doc->createElement('Document');
        $kml->appendChild($documento);
        $documento->appendChild($doc->createElement('name', htmlspecialchars($titolo, ENT_XML1)));

        foreach ($righe as $riga) {
            $coord = Visibilita::coordinateDaRiga($riga);
            if (trim($coord['lat']) === '' || trim($coord['lon']) === '') {
                continue;
            }

            $segnaposto = $doc->createElement('Placemark');
            $documento->appendChild($segnaposto);

            $nome = trim((string) ($riga['codice'] ?? '') . ' ' . (string) ($riga['nome'] ?? ''));
            $segnaposto->appendChild($doc->createElement('name', htmlspecialchars($nome, ENT_XML1)));

            $descrizione = self::descrizioneTestuale($riga, $coord['offuscate']);
            $nodo = $doc->createElement('description');
            // CDATA: la descrizione contiene testo dell'utente, che puo avere
            // caratteri che in XML andrebbero protetti uno per uno.
            $nodo->appendChild($doc->createCDATASection($descrizione));
            $segnaposto->appendChild($nodo);

            $punto = $doc->createElement('Point');
            $segnaposto->appendChild($punto);

            // KML vuole longitudine,latitudine[,quota] — lo stesso ordine di
            // GeoJSON, e la stessa trappola.
            $quota = trim((string) ($riga['quota'] ?? ''));
            $punto->appendChild($doc->createElement(
                'coordinates',
                $coord['lon'] . ',' . $coord['lat'] . ($quota !== '' && is_numeric($quota) ? ',' . $quota : '')
            ));
        }

        return (string) $doc->saveXML();
    }

    /**
     * Righe CSV pronte per Csv::scrivi(), con l'intestazione in COLONNE_CSV.
     *
     * @param  array<int,array<string,string>> $righe
     * @return array<int,array<string,string>>
     */
    public static function csv(array $righe): array
    {
        $esiti = [];

        foreach ($righe as $riga) {
            $coord = Visibilita::coordinateDaRiga($riga);

            $voce = [];
            foreach (self::COLONNE_CSV as $colonna) {
                $voce[$colonna] = (string) ($riga[$colonna] ?? '');
            }

            // Le coordinate si sovrascrivono con quelle filtrate: la riga di
            // indice porta quelle vere, e un CSV scaricato le renderebbe
            // permanenti sul disco di chi lo scarica.
            $voce['lat'] = $coord['lat'];
            $voce['lon'] = $coord['lon'];

            $esiti[] = $voce;
        }

        return $esiti;
    }

    /** @param array<string,string> $riga */
    private static function descrizioneTestuale(array $riga, bool $offuscate): string
    {
        $pezzi = [];

        $tipologia = Tipologie::nome((string) ($riga['tipologia'] ?? ''));
        if ($tipologia !== '') {
            $pezzi[] = $tipologia;
        }

        $luogo = trim(implode(', ', array_filter([
            (string) ($riga['localita'] ?? ''),
            (string) ($riga['comune'] ?? ''),
            (string) ($riga['provincia'] ?? ''),
        ])));
        if ($luogo !== '') {
            $pezzi[] = $luogo;
        }

        foreach (['sviluppo' => 'sviluppo', 'dislivello' => 'dislivello', 'quota' => 'quota'] as $colonna => $etichetta) {
            $valore = trim((string) ($riga[$colonna] ?? ''));
            if ($valore !== '') {
                $pezzi[] = $etichetta . ' ' . $valore . ' m';
            }
        }

        if ($offuscate) {
            // Chi apre il KML in Google Earth deve sapere che il segnaposto non
            // e dove sembra: altrimenti andra a cercare l'ingresso nel punto
            // sbagliato e concludera che il catasto e impreciso.
            $pezzi[] = 'POSIZIONE APPROSSIMATA: le coordinate esatte non sono '
                     . 'divulgabili con il livello di utenza in uso.';
        }

        return implode("\n", $pezzi);
    }
}
