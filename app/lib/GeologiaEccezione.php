<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/GeologiaEccezione.php
 *  Descrizione ..: Errore della sezione geologica (6.16).
 *
 *                  In un file suo, come tutte le altre eccezioni: l'autoload
 *                  cerca una classe nel file omonimo, e un'eccezione sollevata
 *                  prima che la sua classe contenitrice sia stata caricata
 *                  produrrebbe un errore fatale al posto di un messaggio. E
 *                  gia successo in fase 7c, con ScientificiEccezione.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Prima stesura (fase 6b).
 * ============================================================================
 */

final class GeologiaEccezione extends RuntimeException
{
}
