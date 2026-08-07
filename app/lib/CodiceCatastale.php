<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/CodiceCatastale.php
 *  Descrizione ..: Composizione e assegnazione del codice catastale.
 *
 *                  Dentro un catalogo la codifica non e un singolo prefisso ma
 *                  un elenco ordinato di serie, ciascuna con criteri, padding e
 *                  contatore propri: vince la prima serie i cui criteri sono
 *                  tutti soddisfatti dall'ipogeo.
 *
 *                  Il padding e una SOGLIA MINIMA, non un tetto (D7): un
 *                  progressivo piu lungo delle cifre dichiarate viene scritto
 *                  per intero, senza troncamenti. Nessun limite al progressivo
 *                  oltre l'intero della piattaforma.
 *  Versione .....: 0.3.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.3.0  2026-08-04  D.Candela  Prima stesura (fase 2b).
 * ============================================================================
 */

final class CodiceCatastale
{
    /**
     * Criteri su cui una serie puo discriminare.
     * Un criterio assente vale "qualsiasi"; piu valori si separano con |.
     */
    public const CRITERI = ['natura', 'tipologia', 'sottotipologia', 'stato', 'regione', 'provincia'];

    /** Etichette dei criteri per l'interfaccia. */
    public const ETICHETTE_CRITERI = [
        'natura'         => 'Natura',
        'tipologia'      => 'Tipologia',
        'sottotipologia' => 'Sottotipologia',
        'stato'          => 'Stato',
        'regione'        => 'Regione',
        'provincia'      => 'Provincia',
    ];

    /** Numero massimo di cifre di padding accettato. */
    public const MAX_CIFRE = 12;

    // ------------------------------------------------------------- composizione

    /**
     * Compone un codice dalle sue parti.
     *
     * @param int $cifre soglia minima di cifre; 0 = nessun padding
     */
    public static function componi(string $prefisso, int $progressivo, int $cifre, string $separatore = ''): string
    {
        $numero = (string) $progressivo;

        // str_pad non tronca mai: se il numero e piu lungo della soglia resta
        // intero. E esattamente il comportamento voluto.
        if ($cifre > 0) {
            $numero = str_pad($numero, $cifre, '0', STR_PAD_LEFT);
        }

        return $prefisso . $separatore . $numero;
    }

    /**
     * Scompone un codice nelle sue parti, dato l'elenco dei prefissi noti.
     *
     * Si prova il prefisso piu lungo per primo: senza questo, un codice LA-AC12
     * verrebbe attribuito alla serie LA con progressivo non numerico.
     *
     * @param  string[] $prefissiNoti
     * @return array{prefisso:string,progressivo:int}|null
     */
    public static function scomponi(string $codice, array $prefissiNoti, string $separatore = ''): ?array
    {
        $codice = trim($codice);

        usort($prefissiNoti, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($prefissiNoti as $prefisso) {
            $atteso = $prefisso . $separatore;
            if ($atteso === '' || stripos($codice, $atteso) !== 0) {
                continue;
            }
            $resto = substr($codice, strlen($atteso));
            if ($resto !== '' && preg_match('/^[0-9]+$/', $resto) === 1) {
                return ['prefisso' => $prefisso, 'progressivo' => (int) $resto];
            }
        }

        return null;
    }

    // -------------------------------------------------------- risoluzione serie

    /**
     * Risolve la serie applicabile a un ipogeo con i dati indicati.
     *
     * @param  array<string,mixed> $catalogo   come restituito da Cataloghi::trova()
     * @param  array<string,mixed> $attributi  natura, tipologia, sottotipologia, stato, regione, provincia
     * @return array<string,mixed>|null la serie vincente, o null se nessuna combacia
     */
    public static function risolviSerie(array $catalogo, array $attributi): ?array
    {
        foreach ($catalogo['serie'] as $serie) {
            if (self::serieCombacia($serie, $attributi)) {
                return $serie;
            }
        }
        return null;
    }

    /**
     * Verifica se i criteri di una serie sono soddisfatti.
     *
     * @param array<string,mixed> $serie
     * @param array<string,mixed> $attributi
     */
    public static function serieCombacia(array $serie, array $attributi): bool
    {
        foreach ($serie['criteri'] as $criterio => $atteso) {
            $valore = trim((string) ($attributi[$criterio] ?? ''));

            // Criterio richiesto ma attributo non valorizzato: non combacia.
            if ($valore === '') {
                return false;
            }

            $ammessi = array_map('trim', explode('|', (string) $atteso));
            $trovato = false;
            foreach ($ammessi as $ammesso) {
                if ($ammesso !== '' && strcasecmp($ammesso, $valore) === 0) {
                    $trovato = true;
                    break;
                }
            }
            if (!$trovato) {
                return false;
            }
        }

        return true;
    }

    /**
     * Anteprima del codice che verrebbe assegnato, SENZA incrementare nulla.
     *
     * E la funzione su cui si appoggia la verifica delle regole di codifica
     * prima di censire: le serie sono la parte piu facile da sbagliare, e un
     * contatore consumato per una prova sarebbe un buco nella numerazione.
     *
     * @param  array<string,mixed> $attributi
     * @return array{ok:bool,codice:string,serie:array<string,mixed>|null,messaggio:string}
     */
    public static function anteprima(string $sigla, array $attributi): array
    {
        $catalogo = Cataloghi::trova($sigla);
        if ($catalogo === null) {
            return ['ok' => false, 'codice' => '', 'serie' => null, 'messaggio' => 'Catalogo non trovato.'];
        }
        if ($catalogo['serie'] === []) {
            return ['ok' => false, 'codice' => '', 'serie' => null, 'messaggio' => 'Il catalogo non ha nessuna serie di codifica.'];
        }

        $serie = self::risolviSerie($catalogo, $attributi);
        if ($serie === null) {
            return [
                'ok'        => false,
                'codice'    => '',
                'serie'     => null,
                'messaggio' => 'Nessuna serie combacia con questi dati. Aggiungere una serie senza criteri '
                             . 'in fondo all\'elenco, che faccia da caso generale.',
            ];
        }

        $codice = self::componi(
            (string) $serie['prefisso'],
            (int) $serie['prossimoProgressivo'],
            (int) $serie['cifre'],
            (string) $catalogo['separatore']
        );

        $messaggio = '';
        if (IndiceCodici::esiste($codice)) {
            $messaggio = 'Attenzione: il codice risultante è già presente in archivio. '
                       . 'Il contatore della serie va allineato.';
        }

        return ['ok' => true, 'codice' => $codice, 'serie' => $serie, 'messaggio' => $messaggio];
    }

    /**
     * Assegna un codice nuovo, incrementando il contatore della serie.
     *
     * @param  array<string,mixed> $attributi
     * @return array{codice:string,prefisso:string,progressivo:int}
     * @throws CatalogoEccezione
     */
    public static function assegna(string $sigla, array $attributi): array
    {
        $catalogo = Cataloghi::trova($sigla);
        if ($catalogo === null) {
            throw new CatalogoEccezione('Catalogo non trovato.');
        }

        $serie = self::risolviSerie($catalogo, $attributi);
        if ($serie === null) {
            throw new CatalogoEccezione(
                'Nessuna serie di codifica combacia con i dati dell\'ipogeo. '
                . 'Verificare le serie del catalogo: serve una serie senza criteri come caso generale.'
            );
        }

        $prefisso   = (string) $serie['prefisso'];
        $separatore = (string) $catalogo['separatore'];
        $cifre      = (int) $serie['cifre'];

        // Si cicla perche il codice composto potrebbe risultare gia presente
        // (archivio importato a mano, contatore disallineato): in quel caso si
        // consuma il progressivo e si riprova, invece di fallire.
        for ($tentativi = 0; $tentativi < 1000; $tentativi++) {
            $progressivo = Cataloghi::prelevaProgressivo($sigla, $prefisso);
            $codice      = self::componi($prefisso, $progressivo, $cifre, $separatore);

            if (!IndiceCodici::esiste($codice) && !self::cartellaEsistente($codice)) {
                return ['codice' => $codice, 'prefisso' => $prefisso, 'progressivo' => $progressivo];
            }
        }

        throw new CatalogoEccezione(
            'Assegnazione del codice non riuscita dopo 1000 tentativi: il contatore della serie '
            . $prefisso . ' e molto disallineato rispetto ai codici presenti. Allinearlo a mano.'
        );
    }

    /**
     * Verifica un codice inserito a mano e, se valido, allinea il contatore.
     *
     * Serve per importare un catasto esistente conservandone la numerazione.
     *
     * @return array{ok:bool,messaggio:string,prefisso:string,progressivo:int}
     */
    public static function verificaManuale(string $sigla, string $codice): array
    {
        $esito = ['ok' => false, 'messaggio' => '', 'prefisso' => '', 'progressivo' => 0];

        $codice = trim($codice);
        if ($codice === '') {
            $esito['messaggio'] = 'Indicare il codice.';
            return $esito;
        }

        $catalogo = Cataloghi::trova($sigla);
        if ($catalogo === null) {
            $esito['messaggio'] = 'Catalogo non trovato.';
            return $esito;
        }
        if (!$catalogo['consentiCodiceManuale']) {
            $esito['messaggio'] = 'Questo catalogo non consente l\'inserimento manuale del codice.';
            return $esito;
        }
        if (!self::formaValida($codice)) {
            $esito['messaggio'] = 'Il codice contiene caratteri non ammessi: sono consentiti lettere, '
                                . 'cifre, punto, trattino e underscore.';
            return $esito;
        }
        if (IndiceCodici::esiste($codice) || self::cartellaEsistente($codice)) {
            $esito['messaggio'] = 'Il codice è già presente in archivio.';
            return $esito;
        }

        $prefissi = array_map(static fn (array $s): string => (string) $s['prefisso'], $catalogo['serie']);
        $parti    = self::scomponi($codice, $prefissi, (string) $catalogo['separatore']);

        if ($parti === null) {
            // Il codice e accettabile ma non riconducibile a una serie: si
            // avvisa senza rifiutare, perche un catasto storico puo avere
            // codici che non seguono le serie attuali.
            $esito['ok'] = true;
            $esito['messaggio'] = 'Codice accettato, ma non riconducibile a nessuna serie del catalogo: '
                                . 'il contatore non verra allineato.';
            return $esito;
        }

        $esito['ok']          = true;
        $esito['prefisso']    = $parti['prefisso'];
        $esito['progressivo'] = $parti['progressivo'];

        return $esito;
    }

    /**
     * Registra l'uso di un codice inserito a mano allineando il contatore.
     */
    public static function allineaDopoManuale(string $sigla, string $prefisso, int $progressivo): void
    {
        if ($prefisso === '' || $progressivo < 1) {
            return;
        }
        Cataloghi::allineaProgressivo($sigla, $prefisso, $progressivo + 1);
    }

    // ------------------------------------------------------------- validazioni

    /** Forma ammessa per un codice catastale. */
    public static function formaValida(string $codice): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._\-]{1,40}$/', $codice);
    }

    /**
     * @throws CatalogoEccezione
     */
    public static function validaPrefisso(string $prefisso): void
    {
        if ($prefisso === '') {
            throw new CatalogoEccezione('Il prefisso della serie è obbligatorio.');
        }
        if (!preg_match('/^[A-Z0-9.\-_]{1,30}$/', $prefisso)) {
            throw new CatalogoEccezione(
                'Prefisso non valido: fino a 30 caratteri fra lettere maiuscole, cifre, punto, trattino e underscore.'
            );
        }
    }

    /**
     * @throws CatalogoEccezione
     */
    public static function validaCifre(int $cifre): void
    {
        if ($cifre < 0 || $cifre > self::MAX_CIFRE) {
            throw new CatalogoEccezione('Il numero di cifre deve essere fra 0 e ' . self::MAX_CIFRE . '. Zero significa nessun riempimento.');
        }
    }

    /**
     * Esempi di come una serie numera, mostrati accanto alla configurazione:
     * vedere il comportamento del padding evita di doverlo dedurre.
     *
     * @return array<int,array{progressivo:int,codice:string}>
     */
    public static function esempiPadding(string $prefisso, int $cifre, string $separatore = ''): array
    {
        $esempi = [];
        foreach ([1, 2, 297, 15234] as $progressivo) {
            $esempi[] = [
                'progressivo' => $progressivo,
                'codice'      => self::componi($prefisso, $progressivo, $cifre, $separatore),
            ];
        }
        return $esempi;
    }

    /**
     * Verifica se esiste gia una cartella di ipogeo con questo codice, in
     * qualunque catalogo.
     *
     * E la terza verifica di unicita, ridondante per scelta: un archivio
     * ripristinato a mano da backup puo avere indici disallineati, e il codice
     * e la cosa che non deve mai duplicarsi.
     */
    public static function cartellaEsistente(string $codice): bool
    {
        foreach (Cataloghi::elenco() as $catalogo) {
            $cartella = Percorsi::unisci(Percorsi::cataloghi((string) $catalogo['cartella']), 'ipogei');
            if (!is_dir($cartella)) {
                continue;
            }
            foreach (scandir($cartella) ?: [] as $voce) {
                if ($voce === '.' || $voce === '..') {
                    continue;
                }
                // Le cartelle si chiamano "[codice] - [nome]".
                $separatore = strpos($voce, ' - ');
                $codiceVoce = $separatore === false ? $voce : substr($voce, 0, $separatore);
                if (strcasecmp(trim($codiceVoce), $codice) === 0) {
                    return true;
                }
            }
        }
        return false;
    }
}
