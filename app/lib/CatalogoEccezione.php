<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO - Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/CatalogoEccezione.php
 *  Descrizione ..: Errore nella gestione dei cataloghi o nell'assegnazione di
 *                  un codice catastale: sigla duplicata, serie non risolvibile,
 *                  codice gia esistente, catalogo non vuoto.
 *  Versione .....: 0.3.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 - vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.3.0  2026-08-04  D.Candela  Prima stesura (fase 2b).
 * ============================================================================
 */

class CatalogoEccezione extends RuntimeException
{
}
