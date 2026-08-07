<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/pagine/anagrafiche.php
 *  Descrizione ..: Indice delle anagrafiche, con il conteggio delle voci di
 *                  ciascuna e l'accesso alle rispettive gestioni.
 *  Versione .....: 1.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.1.0  2026-08-07  D.Candela  Riquadro delle aree speleologiche (fase 12).
 *  0.2.0  2026-08-04  D.Candela  Prima stesura (fase 2).
 * ============================================================================
 */

// Seconda barriera contro l'accesso diretto via HTTP: questo file ha senso
// solo se incluso da index.php, che definisce CATAGEO_ROOT. La guardia vale
// anche sui server dove il file .htaccess non viene letto.
defined('CATAGEO_ROOT') or exit('Accesso diretto non consentito.');

Auth::esigi('anagrafiche');

$schede = [
    [
        'url'         => 'index.php?p=gruppi',
        'titolo'      => 'Gruppi speleologici',
        'icona'       => 'bi-people-fill',
        'descrizione' => 'Gruppi e associazioni che effettuano esplorazioni e censimenti.',
        'conteggio'   => Gruppi::conta(),
        'attive'      => Gruppi::conta(true),
    ],
    [
        'url'         => 'index.php?p=esploratori',
        'titolo'      => 'Esploratori',
        'icona'       => 'bi-person-badge',
        'descrizione' => 'Persone censite, con appartenenza storicizzata ai gruppi.',
        'conteggio'   => Esploratori::conta(),
        'attive'      => Esploratori::conta(true),
    ],
    [
        'url'         => 'index.php?p=aree',
        'titolo'      => 'Aree speleologiche',
        'icona'       => 'bi-bounding-box',
        'descrizione' => 'Raggruppamenti geografici con un nome, indipendenti dai confini amministrativi: e il modo in cui uno speleologo colloca una cavità.',
        'conteggio'   => Aree::conta(),
        'attive'      => Aree::conta(true),
    ],
    [
        'url'         => 'index.php?p=complessi',
        'titolo'      => 'Complessi',
        'icona'       => 'bi-diagram-2',
        'descrizione' => 'Insiemi di cavità che formano un sistema unico e hanno un nome proprio. Sviluppo e dislivello si sommano dalle schede, non si digitano.',
        'conteggio'   => Complessi::conta(),
        'attive'      => Complessi::conta(true),
    ],
    [
        'url'         => 'index.php?p=opere',
        'titolo'      => 'Catalogo delle opere',
        'icona'       => 'bi-journals',
        'descrizione' => 'Opere citabili da più schede: si censiscono una volta e si correggono in un posto solo.',
        'conteggio'   => Opere::conta(),
        'attive'      => Opere::conta(true),
    ],
    [
        'url'         => 'index.php?p=vocabolari&amp;voc=tipologie',
        'titolo'      => 'Tipologie di ipogeo',
        'icona'       => 'bi-diagram-3',
        'descrizione' => 'Tassonomia su tre livelli: natura, tipologia, sottotipologia.',
        'conteggio'   => Tipologie::conta(),
        'attive'      => Tipologie::conta(true),
    ],
    [
        'url'         => 'index.php?p=vocabolari&amp;voc=grandezze',
        'titolo'      => 'Grandezze misurabili',
        'icona'       => 'bi-thermometer-half',
        'descrizione' => 'Cosa si misura in cavità, con unità e intervalli di plausibilità.',
        'conteggio'   => Grandezze::conta(),
        'attive'      => Grandezze::conta(true),
    ],
    [
        'url'         => 'index.php?p=vocabolari&amp;voc=periodi',
        'titolo'      => 'Periodi storici',
        'icona'       => 'bi-hourglass-split',
        'descrizione' => 'Cronologia per la datazione archeologica, con estremi in anni.',
        'conteggio'   => Periodi::conta(),
        'attive'      => Periodi::conta(true),
    ],
];
?>

<div class="catageo-intestazione">
  <div>
    <h1>Anagrafiche</h1>
    <p class="text-body-secondary mb-0">
      Vocabolari e soggetti a cui le schede degli ipogei fanno riferimento
    </p>
  </div>
</div>

<div class="alert alert-info d-flex align-items-start gap-2">
  <i class="bi bi-info-circle-fill mt-1" aria-hidden="true"></i>
  <div>
    Una voce referenziata da qualche parte non si può cancellare, ma si può
    <strong>disattivare</strong>: sparisce dalle scelte per i nuovi inserimenti e
    resta valida nei riferimenti storici. E la via da preferire quasi sempre.
  </div>
</div>

<div class="row g-3">
  <?php foreach ($schede as $scheda): ?>
    <div class="col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-start gap-3 mb-2">
            <i class="bi <?= Testo::esc($scheda['icona']) ?> fs-2 text-primary" aria-hidden="true"></i>
            <div>
              <h2 class="h6 mb-1"><?= Testo::esc($scheda['titolo']) ?></h2>
              <div class="small text-body-secondary">
                <?= (int) $scheda['conteggio'] ?> voc<?= $scheda['conteggio'] === 1 ? 'e' : 'i' ?>
                <?php if ($scheda['attive'] < $scheda['conteggio']): ?>
                  · <?= (int) $scheda['attive'] ?> attiv<?= $scheda['attive'] === 1 ? 'a' : 'e' ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <p class="catageo-nota flex-grow-1"><?= Testo::esc($scheda['descrizione']) ?></p>
          <a class="btn btn-sm btn-outline-primary align-self-start"
             href="<?= $scheda['url'] ?>">
            <i class="bi bi-arrow-right-short"></i> Gestisci
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
