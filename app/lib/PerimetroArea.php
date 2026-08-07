<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/PerimetroArea.php
 *  Descrizione ..: Perimetro geografico di un'area speleologica (9.17.5, 9.17.9).
 *
 *                  Nasce da una richiesta precisa: per il carsismo il confine
 *                  di un'area e d'uso e non di cartografia, ma **per una cava
 *                  il perimetro esiste davvero** ed e un dato interessante da
 *                  vedere in mappa. E un caso in cui la parita fra naturali e
 *                  artificiali (§16.2) cambia la risposta.
 *
 *                  Formati accettati: **GeoJSON** e **KML/KMZ**. Lo shapefile
 *                  nativo resta fuori per decisione del committente: e un
 *                  formato binario multi-file che richiederebbe un parser
 *                  scritto a mano piu la riproiezione dal grid dichiarato nel
 *                  .prj, per qualcosa che QGIS converte in due clic.
 *
 *                  **Il perimetro non sta in aree.xml ma in un file suo.** Un
 *                  poligono di una cava puo valere migliaia di coordinate, e
 *                  infilarle nell'anagrafica la renderebbe illeggibile a mano —
 *                  che e il vincolo su cui questo progetto e costruito. Ogni
 *                  perimetro e un GeoJSON in dati/aree/, apribile da solo con
 *                  qualunque strumento cartografico.
 *  Versione .....: 1.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.1.0  2026-08-07  D.Candela  Prima stesura (fase 12).
 * ============================================================================
 */

final class PerimetroArea
{
    /** Cartella dei perimetri, dentro l'archivio. */
    public const CARTELLA = 'aree';

    /** Estensioni accettate in caricamento. */
    public const FORMATI = ['geojson', 'json', 'kml', 'kmz'];

    /**
     * Tetto al file caricato.
     *
     * Piu basso di quello dei rilievi: un perimetro e un poligono, non un
     * rilievo topografico, e un file di otto megabyte qui vuol dire quasi
     * sempre che si sta caricando la cosa sbagliata.
     */
    public const LIMITE_BYTE = 4194304; // 4 MB

    /**
     * Tetto ai vertici conservati.
     *
     * Un perimetro con centomila vertici non si disegna e non serve a nessuno:
     * la mappa ci mette secondi e il risultato e indistinguibile da uno con
     * mille. Si rifiuta invece di semplificare in silenzio, perche una
     * semplificazione automatica sposterebbe un confine che magari e catastale.
     */
    public const LIMITE_VERTICI = 50000;

    /** Percorso del file di perimetro di un'area, esista o no. */
    public static function percorso(string $idArea): string
    {
        $id = strtoupper(trim($idArea));
        if (!preg_match('/^AS[0-9]+$/', $id)) {
            throw new AnagraficaEccezione('Identificativo di area non valido: ' . $idArea);
        }

        return Percorsi::unisci(Percorsi::dati(self::CARTELLA), $id . '.geojson');
    }

    /** True se l'area ha un perimetro registrato. */
    public static function esiste(string $idArea): bool
    {
        try {
            return is_file(self::percorso($idArea));
        } catch (AnagraficaEccezione $e) {
            return false;
        }
    }

    /**
     * Il perimetro come FeatureCollection, o null se non c'e.
     *
     * @return array<string,mixed>|null
     */
    public static function leggi(string $idArea): ?array
    {
        if (!self::esiste($idArea)) {
            return null;
        }

        $contenuto = @file_get_contents(self::percorso($idArea));
        if ($contenuto === false) {
            return null;
        }

        $dati = json_decode($contenuto, true);

        return is_array($dati) ? $dati : null;
    }

    /**
     * Registra il perimetro di un'area a partire da un file caricato.
     *
     * Il file di origine non si conserva: si conserva la conversione, che e
     * l'unica forma che l'applicativo sa poi disegnare. Il KML originale
     * resterebbe un secondo file da tenere allineato, e nessuno lo rileggerebbe.
     *
     * @param  array{nome:string,tmp:string,dimensione:int} $file file verificato da Upload
     * @return array{vertici:int,riquadro:array<string,float>|null}
     * @throws AnagraficaEccezione
     */
    public static function salva(string $idArea, array $file): array
    {
        $percorso = self::percorso($idArea);

        $nome       = (string) ($file['nome'] ?? '');
        $temporaneo = (string) ($file['tmp'] ?? '');
        $estensione = strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));

        if (!in_array($estensione, self::FORMATI, true)) {
            throw new AnagraficaEccezione(
                'Formato non accettato: ' . ($estensione !== '' ? $estensione : 'sconosciuto')
                . '. Si accettano GeoJSON e KML/KMZ. Uno shapefile si converte in '
                . 'GeoJSON da QGIS con "Esporta - Salva gli elementi come".'
            );
        }

        if ((int) ($file['dimensione'] ?? 0) > self::LIMITE_BYTE) {
            throw new AnagraficaEccezione(
                'Il file pesa ' . Testo::dimensione((int) $file['dimensione'])
                . ' e supera il limite di ' . Testo::dimensione(self::LIMITE_BYTE)
                . ' per un perimetro.'
            );
        }

        if (!is_readable($temporaneo)) {
            throw new AnagraficaEccezione('File caricato non leggibile.');
        }

        // --- conversione al formato canonico
        if ($estensione === 'kml' || $estensione === 'kmz') {
            /*
             * Tracciato::aGeoJson deduce il formato dall'**estensione del
             * percorso**, e il file temporaneo di PHP non ne ha: passandoglielo
             * cosi rifiuterebbe ogni KML con "formato non convertibile".
             * Si copia in un file con l'estensione giusta, e lo si toglie
             * subito dopo — anche se la conversione fallisce, altrimenti la
             * cartella temporanea si riempie di KML di tentativi andati male.
             */
            $conEstensione = Percorsi::unisci(
                Percorsi::tmp(),
                'perimetro-' . bin2hex(random_bytes(8)) . '.' . $estensione
            );
            if (!@copy($temporaneo, $conEstensione)) {
                throw new AnagraficaEccezione('Impossibile preparare il file per la conversione.');
            }

            try {
                $geojson = Tracciato::aGeoJson($conEstensione);
            } catch (TracciatoEccezione $e) {
                throw new AnagraficaEccezione('KML non convertibile: ' . $e->getMessage());
            } finally {
                @unlink($conEstensione);
            }
        } else {
            $contenuto = (string) @file_get_contents($temporaneo);
            $dati = json_decode($contenuto, true);
            if (!is_array($dati)) {
                throw new AnagraficaEccezione(
                    'Il file non contiene GeoJSON valido: ' . (json_last_error_msg() ?: 'formato non riconosciuto') . '.'
                );
            }
            $geojson = self::normalizza($dati);
        }

        $vertici = self::contaVertici($geojson);
        if ($vertici === 0) {
            throw new AnagraficaEccezione(
                'Il file non contiene nessuna geometria: controlla di aver esportato '
                . 'il livello giusto.'
            );
        }
        if ($vertici > self::LIMITE_VERTICI) {
            throw new AnagraficaEccezione(
                'Il perimetro ha ' . number_format($vertici, 0, ',', '.') . ' vertici e supera il limite di '
                . number_format(self::LIMITE_VERTICI, 0, ',', '.') . '. Semplificalo prima di caricarlo: '
                . 'non lo faccio io, perché una semplificazione automatica sposterebbe '
                . 'un confine che potrebbe essere catastale.'
            );
        }

        $riquadro = Tracciato::riquadro($geojson);
        if ($riquadro === null) {
            throw new AnagraficaEccezione('Il perimetro non ha coordinate utilizzabili.');
        }

        Percorsi::assicuraCartella(Percorsi::dati(self::CARTELLA));

        // Scrittura atomica come per il resto dell'archivio: un file mezzo
        // scritto e un perimetro sbagliato che sembra giusto.
        $temporaneoDest = $percorso . '.tmp';
        $json = json_encode($geojson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || @file_put_contents($temporaneoDest, $json) === false) {
            throw new AnagraficaEccezione('Impossibile scrivere il perimetro in ' . dirname($percorso) . '.');
        }
        if (!@rename($temporaneoDest, $percorso)) {
            @unlink($temporaneoDest);
            throw new AnagraficaEccezione('Impossibile completare la scrittura del perimetro.');
        }
        @chmod($percorso, 0644);

        return ['vertici' => $vertici, 'riquadro' => $riquadro];
    }

    /**
     * Rimuove il perimetro di un'area.
     *
     * Cancella davvero, e qui e giusto: non e un dato del catasto ma una
     * sovrapposizione cartografica, rifacibile ricaricando il file da cui e
     * venuta. La regola conservativa vale per le schede, non per le cache.
     */
    public static function rimuovi(string $idArea): void
    {
        $percorso = self::percorso($idArea);
        if (is_file($percorso) && !@unlink($percorso)) {
            throw new AnagraficaEccezione('Impossibile rimuovere il perimetro.');
        }
    }

    /**
     * Riporta un GeoJSON qualunque a una FeatureCollection.
     *
     * QGIS esporta FeatureCollection, ma un file scritto a mano o prodotto da
     * un altro strumento puo contenere una singola Feature o una geometria
     * nuda: accettarle costa tre righe ed evita un rifiuto che sembrerebbe
     * arbitrario a chi ha in mano un file perfettamente valido.
     *
     * @param  array<string,mixed> $dati
     * @return array<string,mixed>
     */
    private static function normalizza(array $dati): array
    {
        $tipo = (string) ($dati['type'] ?? '');

        if ($tipo === 'FeatureCollection') {
            return $dati;
        }
        if ($tipo === 'Feature') {
            return ['type' => 'FeatureCollection', 'features' => [$dati]];
        }
        if ($tipo !== '') {
            return [
                'type'     => 'FeatureCollection',
                'features' => [['type' => 'Feature', 'properties' => [], 'geometry' => $dati]],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => []];
    }

    /**
     * Numero di posizioni presenti nelle geometrie.
     *
     * @param array<string,mixed> $geojson
     */
    private static function contaVertici(array $geojson): int
    {
        $totale = 0;

        $scorri = static function ($coordinate) use (&$scorri, &$totale): void {
            if (!is_array($coordinate) || $coordinate === []) {
                return;
            }
            if (is_numeric($coordinate[0] ?? null)) {
                $totale++;
                return;
            }
            foreach ($coordinate as $parte) {
                $scorri($parte);
            }
        };

        foreach ((array) ($geojson['features'] ?? []) as $feature) {
            $geometria = $feature['geometry'] ?? null;
            if (is_array($geometria)) {
                $scorri($geometria['coordinates'] ?? []);
            }
        }

        return $totale;
    }
}
