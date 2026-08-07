<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/view/parti-avvisi.php
 *  Descrizione ..: Barra degli avvisi di una scheda: periodo critico delle
 *                  colonie di chirotteri e vincoli di tutela (6.14, 6.15).
 *
 *                  Sta in una parte condivisa perche gli stessi avvisi devono
 *                  comparire nella scheda dell'ipogeo, nella pagina di
 *                  biospeleologia e in quella di archeologia. Chi programma
 *                  un'uscita puo arrivare da uno qualunque dei tre, e un avviso
 *                  che compare in due punti su tre e un avviso che non c'e.
 *  Versione .....: 0.12.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.0  2026-08-06  D.Candela  Prima stesura (fase 7d).
 * ============================================================================
 */

defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

/**
 * Tutti gli avvisi di un ipogeo, dal piu urgente.
 *
 * @return array<int,array{livello:string,titolo:string,testo:string}>
 */
function catageoAvvisiDi(string $codice): array
{
    $avvisi = array_merge(
        Biospeleologia::avvisi($codice),
        Archeologia::avvisi($codice),
        // I rischi geologici di livello medio e alto (6.16): chi programma
        // un'uscita deve vedere subito che una cavita crolla o si allaga.
        Geologia::avvisi($codice)
    );

    // "danger" prima di "warning": se una colonia e in letargo e la cavita e
    // anche vincolata, la prima cosa da leggere e quella che impedisce di
    // entrare oggi.
    usort($avvisi, static function (array $a, array $b): int {
        $peso = static fn (string $l): int => $l === 'danger' ? 0 : 1;

        return $peso($a['livello']) <=> $peso($b['livello']);
    });

    return $avvisi;
}

/**
 * Stampa la barra degli avvisi. Non emette nulla se non ce ne sono.
 *
 * @param array<int,array{livello:string,titolo:string,testo:string}> $avvisi
 */
function catageoAvvisi(array $avvisi): void
{
    if ($avvisi === []) {
        return;
    }

    foreach ($avvisi as $avviso) {
        $livello = $avviso['livello'] === 'danger' ? 'danger' : 'warning';
        $icona   = $livello === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-exclamation-triangle-fill';
        ?>
        <div class="alert alert-<?= $livello ?> d-flex align-items-start gap-2" role="alert">
          <i class="bi <?= $icona ?> mt-1 flex-shrink-0" aria-hidden="true"></i>
          <div>
            <div class="fw-semibold"><?= Testo::esc($avviso['titolo']) ?></div>
            <?= nl2br(Testo::esc($avviso['testo'])) ?>
          </div>
        </div>
        <?php
    }
}
