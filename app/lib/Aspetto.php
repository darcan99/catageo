<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Aspetto.php
 *  Descrizione ..: Tema (chiaro/scuro) e tavolozza del tema chiaro: valori
 *                  ammessi, etichette e lettura validata dalla configurazione.
 *
 *                  Sta in una classe perche gli stessi elenchi servono al
 *                  layout, al menu che li mostra e al JavaScript che li applica.
 *                  Un valore ammesso in un punto e ignoto in un altro produce un
 *                  menu con una voce che non fa nulla.
 *
 *                  La configurazione stabilisce il valore PREDEFINITO
 *                  dell'installazione; la preferenza di chi guarda vive nel suo
 *                  browser e vince su quella. Non e legata all'utenza perche non
 *                  e un dato del catasto: e come uno preferisce vedere lo schermo
 *                  che ha davanti, e su un altro computer puo preferire altro.
 *  Versione .....: 0.6.4
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.6.4  2026-08-05  D.Candela  Prima stesura.
 * ============================================================================
 */

final class Aspetto
{
    /** Tema predefinito se la configurazione non dice altro. */
    public const TEMA_PREDEFINITO = 'auto';

    /** Tavolozza predefinita se la configurazione non dice altro. */
    public const TAVOLOZZA_PREDEFINITA = 'sabbia';

    /**
     * Temi ammessi.
     *
     * "auto" segue l'impostazione del sistema operativo: e il piu sensato come
     * predefinito, perche chi lavora di notte ha di solito gia il sistema in
     * scuro e non deve dirlo due volte.
     */
    public const TEMI = [
        'auto'  => 'Come il sistema',
        'light' => 'Chiaro',
        'dark'  => 'Scuro',
    ];

    /**
     * Tavolozze del tema chiaro.
     *
     * Agiscono SOLO sul tema chiaro: il tema scuro ha una sua scala, tarata a
     * parte, e moltiplicare le combinazioni significherebbe moltiplicare le
     * misure di contrasto da rifare a ogni modifica. Il menu lo dichiara, cosi
     * chi sceglie in tema scuro sa perche non vede cambiare nulla.
     */
    public const TAVOLOZZE = [
        'sabbia'  => 'Sabbia',
        'verde'   => 'Verde',
        'azzurra' => 'Azzurra',
        'neutra'  => 'Neutra',
    ];

    /** Descrizioni brevi, mostrate accanto al nome nel menu. */
    public const DESCRIZIONI_TAVOLOZZE = [
        'sabbia'  => 'roccia e carte topografiche',
        'verde'   => 'vegetazione delle carte',
        'azzurra' => 'in tinta con il tema scuro',
        'neutra'  => 'grigio, senza tinte',
    ];

    /** Tema dichiarato in configurazione, ricondotto a un valore ammesso. */
    public static function tema(): string
    {
        return self::valida(
            Config::caricata() ? Config::testo('sistema.tema', self::TEMA_PREDEFINITO) : self::TEMA_PREDEFINITO,
            self::TEMI,
            self::TEMA_PREDEFINITO
        );
    }

    /** Tavolozza dichiarata in configurazione, ricondotta a un valore ammesso. */
    public static function tavolozza(): string
    {
        return self::valida(
            Config::caricata() ? Config::testo('sistema.tavolozza', self::TAVOLOZZA_PREDEFINITA) : self::TAVOLOZZA_PREDEFINITA,
            self::TAVOLOZZE,
            self::TAVOLOZZA_PREDEFINITA
        );
    }

    /**
     * Tema da scrivere nell'attributo data-bs-theme al primo disegno.
     *
     * Con "auto" il server non puo sapere cosa preferisce il sistema di chi
     * guarda, quindi sceglie il chiaro e il JavaScript corregge subito. Va
     * scritto un valore concreto e non "auto": Bootstrap non conosce quel
     * valore e la pagina resterebbe senza tema.
     */
    public static function temaIniziale(): string
    {
        $tema = self::tema();
        return $tema === 'auto' ? 'light' : $tema;
    }

    /**
     * Elenco per il menu: chiave, etichetta e descrizione.
     *
     * @return array<int,array{valore:string,nome:string,nota:string}>
     */
    public static function elencoTavolozze(): array
    {
        $elenco = [];
        foreach (self::TAVOLOZZE as $valore => $nome) {
            $elenco[] = [
                'valore' => $valore,
                'nome'   => $nome,
                'nota'   => self::DESCRIZIONI_TAVOLOZZE[$valore] ?? '',
            ];
        }
        return $elenco;
    }

    /**
     * @param array<string,string> $ammessi
     */
    private static function valida(string $valore, array $ammessi, string $difetto): string
    {
        $valore = strtolower(trim($valore));
        return isset($ammessi[$valore]) ? $valore : $difetto;
    }
}
