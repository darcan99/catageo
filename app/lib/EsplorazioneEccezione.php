<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO - Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/EsplorazioneEccezione.php
 *  Descrizione ..: Errore nella gestione di un diario di esplorazione.
 *
 *                  Sta in un file proprio, e non accanto alla classe che la
 *                  solleva, perche l'autoload risolve una classe per file: una
 *                  eccezione dichiarata dentro un altro file non viene trovata
 *                  quando la si solleva da codice che quel file non ha caricato.
 *                  Non e teoria: sollevarla prima di aver usato la sua classe
 *                  produceva un errore fatale al posto del messaggio previsto.
 *  Versione .....: 0.11.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 - vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.11.0  2026-08-06  D.Candela  Estratta dal file della classe che la solleva.
 * ============================================================================
 */

class EsplorazioneEccezione extends RuntimeException
{
}
