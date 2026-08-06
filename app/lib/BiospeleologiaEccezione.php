<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO - Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/BiospeleologiaEccezione.php
 *  Descrizione ..: Errore nella gestione delle osservazioni faunistiche o delle colonie di chirotteri.
 *
 *                  Sta in un file proprio, e non accanto alla classe che la
 *                  solleva, perche l'autoload risolve una classe per file: una
 *                  eccezione dichiarata dentro un altro file non viene trovata
 *                  quando la si solleva da codice che quel file non ha caricato.
 *  Versione .....: 0.12.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 - vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.12.0  2026-08-06  D.Candela  Prima stesura (fase 7d).
 * ============================================================================
 */

class BiospeleologiaEccezione extends RuntimeException
{
}
