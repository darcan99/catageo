<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Icone.php
 *  Descrizione ..: I due insiemi di simboli dell'applicativo: Bootstrap Icons,
 *                  gia self-hosted, e i glifi propri delle cavita.
 *
 *                  Servono entrambi. Bootstrap ha il fuoco per il vulcanismo e
 *                  la neve per il glaciale, e rifarli sarebbe lavoro sprecato;
 *                  non ha ne puo avere l'ingresso di una grotta, un abisso, una
 *                  risorgenza o un colombario, che sono il mestiere di questo
 *                  archivio.
 *
 *                  Chi compila un vocabolario scrive un nome solo: se comincia
 *                  per «cat-» e nostro, altrimenti e di Bootstrap. Un nome, due
 *                  fonti, nessuna scelta da spiegare all'utente.
 *  Versione .....: 1.5.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.5.0  2026-08-08  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Icone
{
    /** Prefisso che distingue i nostri glifi da quelli di Bootstrap. */
    public const PREFISSO = 'cat-';

    /** Sprite con i simboli propri. */
    private const FILE = 'assets/icone/catageo-icone.svg';

    /** @var string[]|null */
    private static ?array $nostre = null;

    /** True se il nome indica un glifo di CATAGEO e non di Bootstrap. */
    public static function nostra(string $nome): bool
    {
        return str_starts_with($nome, self::PREFISSO);
    }

    /**
     * Nomi dei glifi propri, letti dallo sprite.
     *
     * Si leggono dal file invece di tenerne un elenco qui: l'elenco sarebbe
     * una seconda copia, e chi aggiunge un simbolo allo sprite si
     * dimenticherebbe di aggiornarla — poi il simbolo esisterebbe ma non
     * comparirebbe fra quelli proponibili, che e il genere di guasto che non
     * si spiega guardando il codice.
     *
     * @return string[]
     */
    public static function elenco(): array
    {
        if (self::$nostre !== null) {
            return self::$nostre;
        }

        $percorso = Percorsi::app(self::FILE);
        if (!is_file($percorso)) {
            return self::$nostre = [];
        }

        preg_match_all(
            '/<symbol\s+id="(' . preg_quote(self::PREFISSO, '/') . '[a-z0-9-]+)"/i',
            (string) file_get_contents($percorso),
            $trovati
        );

        return self::$nostre = $trovati[1];
    }

    /** True se il nome corrisponde a un glifo che esiste davvero fra i nostri. */
    public static function esiste(string $nome): bool
    {
        return in_array($nome, self::elenco(), true);
    }

    /**
     * Lo sprite da mettere in pagina.
     *
     * Incluso nel documento e non richiamato come file esterno. Il riferimento
     * esterno funziona — provato — ma inserendolo qui si ottengono due cose che
     * contano: currentColor eredita davvero il colore del contenitore, e non
     * c'e una seconda richiesta prima che i simboli compaiano. Su una mappa con
     * trecento marker la differenza si vede.
     *
     * Va emesso una sola volta per pagina, e solo dove i simboli servono: sono
     * un paio di kilobyte, ma su una pagina che non li usa sarebbero due
     * kilobyte di niente.
     */
    public static function sprite(): string
    {
        $percorso = Percorsi::app(self::FILE);

        return is_file($percorso) ? (string) file_get_contents($percorso) : '';
    }

    /**
     * HTML di un simbolo, qualunque sia la sua provenienza.
     *
     * Il nome non viene ripulito qui: arriva gia normalizzato da
     * Tipologie::normalizzaIcona(), che e il punto in cui entra
     * nell'applicativo. Ripulirlo due volte darebbe l'impressione che una
     * delle due possa mancare.
     */
    public static function html(string $nome, string $classe = ''): string
    {
        if ($nome === '') {
            return '';
        }

        $classe = trim('catageo-icona ' . $classe);

        if (self::nostra($nome)) {
            return '<svg class="' . Testo::esc($classe) . '" aria-hidden="true">'
                 . '<use href="#' . Testo::esc($nome) . '"></use></svg>';
        }

        return '<i class="bi bi-' . Testo::esc($nome) . ' ' . Testo::esc($classe) . '" aria-hidden="true"></i>';
    }
}
