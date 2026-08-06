<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Sezioni.php
 *  Descrizione ..: Sezioni della scheda ipogeo: sigla, nome della sottocartella
 *                  e natura del contenuto.
 *
 *                  Sta in un file suo perche le sigle compaiono nei nomi dei
 *                  file, nei riferimenti incrociati fra sezioni e negli indici:
 *                  averle definite in un unico punto e la garanzia che lo
 *                  standard di nomenclatura non divergga fra le parti che lo
 *                  applicano.
 *  Versione .....: 0.8.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.8.0  2026-08-05  D.Candela  I rilievi accettano caricamenti (fase 6).
 *  0.7.0  2026-08-05  D.Candela  Estensioni ammesse, tipo di anteprima e
 *                                sezioni caricabili (fase 5).
 *  0.4.0  2026-08-04  D.Candela  Prima stesura (fase 3).
 * ============================================================================
 */

final class Sezioni
{
    /** Sottocartella dello storico delle revisioni della scheda. */
    public const STORICO = 'Storico';

    /**
     * Sezioni della scheda, nell'ordine in cui compaiono in interfaccia.
     *
     * sigla      = prefisso dei file e degli identificativi interni
     * cartella   = nome della sottocartella, secondo lo standard 4.1
     * etichetta  = nome mostrato in interfaccia
     * conFile    = la sezione contiene file caricati dall'utente
     * indice     = la sezione ha un XML di indice "[codice] - [cartella].xml"
     * estensioni = chiave di <upload><estensioni sezione="…"> in config.xml,
     *              oppure '' se la sezione non riceve caricamenti
     * anteprima  = come si mostra il contenuto: immagine, video, rilievo,
     *              documento o ''
     * caricabile = la sezione accetta caricamenti dall'interfaccia (fase 5:
     *              allegati, foto e video; le altre arrivano dopo)
     */
    public const ELENCO = [
        'AL' => ['cartella' => 'Allegati',       'etichetta' => 'Allegati',         'conFile' => true,  'indice' => true,
                 'estensioni' => 'allegati',     'anteprima' => 'documento', 'caricabile' => true],
        'FO' => ['cartella' => 'Foto',           'etichetta' => 'Foto',             'conFile' => true,  'indice' => true,
                 'estensioni' => 'foto',         'anteprima' => 'immagine',  'caricabile' => true],
        'VI' => ['cartella' => 'Video',          'etichetta' => 'Video',            'conFile' => true,  'indice' => true,
                 'estensioni' => 'video',        'anteprima' => 'video',     'caricabile' => true],
        'RI' => ['cartella' => 'Rilievi',        'etichetta' => 'Rilievi',          'conFile' => true,  'indice' => true,
                 'estensioni' => 'rilievi',      'anteprima' => 'rilievo',   'caricabile' => true],
        'ES' => ['cartella' => 'Esplorazioni',   'etichetta' => 'Esplorazioni',     'conFile' => true,  'indice' => true,
                 'estensioni' => '',             'anteprima' => '',          'caricabile' => false],
        'BB' => ['cartella' => 'Bibliografia',   'etichetta' => 'Bibliografia',     'conFile' => false, 'indice' => true,
                 'estensioni' => '',             'anteprima' => '',          'caricabile' => false],
        'SC' => ['cartella' => 'Scientifici',    'etichetta' => 'Dati scientifici', 'conFile' => true,  'indice' => true,
                 'estensioni' => 'scientifici',  'anteprima' => 'documento', 'caricabile' => false],
        'BI' => ['cartella' => 'Biospeleologia', 'etichetta' => 'Biospeleologia',   'conFile' => true,  'indice' => true,
                 'estensioni' => 'scientifici',  'anteprima' => 'documento', 'caricabile' => false],
        'AR' => ['cartella' => 'Archeologia',    'etichetta' => 'Archeologia',      'conFile' => false, 'indice' => true,
                 'estensioni' => '',             'anteprima' => '',          'caricabile' => false],
        'GE' => ['cartella' => 'Geologia',       'etichetta' => 'Geologia',         'conFile' => false, 'indice' => true,
                 'estensioni' => '',             'anteprima' => '',          'caricabile' => false],
    ];

    /** Nome della cartella delle miniature, dentro la cartella di sezione. */
    public const MINIATURE = '_mini';

    /**
     * Sigle valide.
     *
     * @return string[]
     */
    public static function sigle(): array
    {
        return array_keys(self::ELENCO);
    }

    /** True se la sigla e una sezione conosciuta. */
    public static function valida(string $sigla): bool
    {
        return isset(self::ELENCO[strtoupper($sigla)]);
    }

    /** Sigle delle sezioni che accettano caricamenti dall'interfaccia. */
    public static function caricabili(): array
    {
        return array_keys(array_filter(
            self::ELENCO,
            static fn (array $s): bool => $s['caricabile']
        ));
    }

    /** True se la sezione accetta caricamenti dall'interfaccia. */
    public static function caricabile(string $sigla): bool
    {
        return self::valida($sigla) && self::dati($sigla)['caricabile'];
    }

    /** Chiave di configurazione delle estensioni ammesse per la sezione. */
    public static function chiaveEstensioni(string $sigla): string
    {
        return self::dati($sigla)['estensioni'];
    }

    /** Come va mostrato il contenuto: immagine, video, documento o ''. */
    public static function anteprima(string $sigla): string
    {
        return self::dati($sigla)['anteprima'];
    }

    /**
     * Dati di una sezione.
     *
     * @return array{cartella:string,etichetta:string,conFile:bool,indice:bool,estensioni:string,anteprima:string,caricabile:bool}
     * @throws InvalidArgumentException
     */
    public static function dati(string $sigla): array
    {
        $sigla = strtoupper($sigla);
        if (!isset(self::ELENCO[$sigla])) {
            throw new InvalidArgumentException("Sezione non riconosciuta: {$sigla}");
        }
        return self::ELENCO[$sigla];
    }

    /** Nome della sottocartella di una sezione. */
    public static function cartella(string $sigla): string
    {
        return self::dati($sigla)['cartella'];
    }

    /** Etichetta per l'interfaccia. */
    public static function etichetta(string $sigla): string
    {
        return self::dati($sigla)['etichetta'];
    }

    /**
     * Nome della sottocartella di una sezione per un dato ipogeo:
     * "[codice] - [cartella]".
     */
    public static function nomeCartella(string $codice, string $sigla): string
    {
        return $codice . ' - ' . self::cartella($sigla);
    }

    /**
     * Nome del file di indice di una sezione:
     * "[codice] - [cartella].xml".
     */
    public static function nomeIndice(string $codice, string $sigla): string
    {
        return $codice . ' - ' . self::cartella($sigla) . '.xml';
    }

    /**
     * Nome normativo di un file di risorsa:
     * "[codice]-[SIGLA][NNN]-[nome originale].[ext]".
     *
     * Il progressivo ha un padding minimo di 3 cifre che non e un tetto:
     * oltre i 999 elementi la numerazione continua a 4 cifre, come per i codici
     * catastali (D7).
     */
    public static function nomeFile(string $codice, string $sigla, int $progressivo, string $nomeOriginale, bool $ascii = false): string
    {
        $sigla   = strtoupper($sigla);
        $numero  = str_pad((string) $progressivo, 3, '0', STR_PAD_LEFT);
        $pulito  = Testo::nomeFileSicuro($nomeOriginale, $ascii);

        return $codice . '-' . $sigla . $numero . '-' . $pulito;
    }

    /**
     * Identificativo interno di una risorsa, citabile dalle altre sezioni:
     * "FO001", "RI002".
     */
    public static function riferimento(string $sigla, int $progressivo): string
    {
        return strtoupper($sigla) . str_pad((string) $progressivo, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Scompone un riferimento interno nelle sue parti.
     *
     * @return array{sigla:string,progressivo:int}|null
     */
    public static function scomponiRiferimento(string $riferimento): ?array
    {
        if (!preg_match('/^([A-Z]{2})([0-9]+)$/', strtoupper(trim($riferimento)), $parti)) {
            return null;
        }
        if (!self::valida($parti[1])) {
            return null;
        }
        return ['sigla' => $parti[1], 'progressivo' => (int) $parti[2]];
    }

    /**
     * Tutte le sottocartelle previste per un ipogeo, storico compreso.
     *
     * @return string[] nomi di cartella
     */
    public static function cartelleDiIpogeo(string $codice): array
    {
        $cartelle = [];
        foreach (self::sigle() as $sigla) {
            $cartelle[] = self::nomeCartella($codice, $sigla);
        }
        $cartelle[] = $codice . ' - ' . self::STORICO;

        return $cartelle;
    }
}
