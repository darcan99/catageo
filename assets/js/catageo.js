/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo.js
 *  Descrizione ..: Comportamenti comuni dell'interfaccia: alternanza del tema
 *                  chiaro/scuro e conferme sulle azioni distruttive.
 *                  Nessuna dipendenza oltre a Bootstrap, gia caricato.
 *  Versione .....: 0.6.4
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.6.4  2026-08-05  D.Candela  Tema e tavolozza dal menu Aspetto, con la
 *                                scelta ricordata nel browser.
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */
(function () {
    'use strict';

    var CHIAVE_TEMA = 'catageo.tema';
    var CHIAVE_TAVOLOZZA = 'catageo.tavolozza';

    var TEMI = ['auto', 'light', 'dark'];
    var TAVOLOZZE = ['sabbia', 'verde', 'azzurra', 'neutra'];

    /** Legge una preferenza dal browser, tollerando localStorage non disponibile. */
    function preferenza(chiave, ammessi) {
        var valore = null;
        try {
            valore = window.localStorage.getItem(chiave);
        } catch (e) {
            // Navigazione privata su alcuni browser: si resta sul valore che
            // arriva dalla configurazione, senza errori.
            valore = null;
        }
        return ammessi.indexOf(valore) !== -1 ? valore : null;
    }

    function salvaPreferenza(chiave, valore) {
        try {
            window.localStorage.setItem(chiave, valore);
        } catch (e) {
            // La scelta vale solo per la pagina corrente.
        }
    }

    /**
     * Applica un tema all'elemento radice.
     * Con "auto" si segue la preferenza del sistema operativo.
     */
    function applicaTema(tema) {
        var effettivo = tema;
        if (tema === 'auto') {
            effettivo = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-bs-theme', effettivo);
    }

    /** Applica una tavolozza. Ha effetto visibile solo sul tema chiaro. */
    function applicaTavolozza(tavolozza) {
        document.documentElement.setAttribute('data-catageo-tavolozza', tavolozza);
    }

    /** Tema scelto da chi guarda, o quello previsto dalla configurazione. */
    function temaCorrente() {
        return preferenza(CHIAVE_TEMA, TEMI)
            || document.documentElement.getAttribute('data-catageo-tema')
            || 'auto';
    }

    /** Tavolozza scelta da chi guarda, o quella prevista dalla configurazione. */
    function tavolozzaCorrente() {
        return preferenza(CHIAVE_TAVOLOZZA, TAVOLOZZE)
            || document.documentElement.getAttribute('data-catageo-tavolozza-predefinita')
            || 'sabbia';
    }

    /** Segna con la spunta le voci di menu corrispondenti alle scelte attive. */
    function aggiornaSpunte() {
        var tema = temaCorrente();
        var tavolozza = tavolozzaCorrente();

        document.querySelectorAll('[data-catageo-scegli-tema]').forEach(function (voce) {
            voce.classList.toggle('active', voce.getAttribute('data-catageo-scegli-tema') === tema);
        });
        document.querySelectorAll('[data-catageo-scegli-tavolozza]').forEach(function (voce) {
            voce.classList.toggle('active', voce.getAttribute('data-catageo-scegli-tavolozza') === tavolozza);
        });
    }

    // Applicazione immediata, prima che la pagina venga mostrata: se avvenisse
    // dopo si vedrebbe un lampo del tema sbagliato.
    applicaTema(temaCorrente());
    applicaTavolozza(tavolozzaCorrente());

    document.addEventListener('DOMContentLoaded', function () {

        // ------------------------------------------------ menu Aspetto
        document.querySelectorAll('[data-catageo-scegli-tema]').forEach(function (voce) {
            voce.addEventListener('click', function () {
                var scelto = voce.getAttribute('data-catageo-scegli-tema');
                applicaTema(scelto);
                salvaPreferenza(CHIAVE_TEMA, scelto);
                aggiornaSpunte();
            });
        });

        document.querySelectorAll('[data-catageo-scegli-tavolozza]').forEach(function (voce) {
            voce.addEventListener('click', function () {
                var scelta = voce.getAttribute('data-catageo-scegli-tavolozza');
                applicaTavolozza(scelta);
                salvaPreferenza(CHIAVE_TAVOLOZZA, scelta);
                aggiornaSpunte();

                // In tema scuro la scelta e stata registrata ma non si vede: il
                // menu lo dichiara gia, e la spunta conferma che e stata presa.
            });
        });

        aggiornaSpunte();

        // Se l'utente non ha espresso una preferenza, si segue il sistema
        // anche quando cambia durante la navigazione.
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                if (temaCorrente() === 'auto') {
                    applicaTema('auto');
                }
            });
        }

        // -------------------------------------- conferma sulle azioni gravi
        // Qualunque elemento con data-catageo-conferma chiede conferma prima
        // di procedere: evita di ripetere onclick sparsi nelle viste.
        document.querySelectorAll('[data-catageo-conferma]').forEach(function (elemento) {
            elemento.addEventListener('click', function (evento) {
                var messaggio = elemento.getAttribute('data-catageo-conferma');
                if (!window.confirm(messaggio || 'Confermare l\'operazione?')) {
                    evento.preventDefault();
                    evento.stopPropagation();
                }
            });
        });

        // ------------------------------------ campi dipendenti dal formato coordinate
        // I blocchi marcati con data-catageo-formato elencano i formati per cui
        // hanno senso: si mostrano solo per quello selezionato. I campi nascosti
        // vengono anche disabilitati, perche display:none NON impedisce l'invio
        // e un valore rimasto in un campo invisibile arriverebbe al salvataggio.
        var selettoreFormato = document.querySelector('[data-catageo-formato-coordinate]');
        if (selettoreFormato) {
            var blocchi = document.querySelectorAll('[data-catageo-formato]');

            var applicaFormato = function () {
                var scelto = selettoreFormato.value;
                blocchi.forEach(function (blocco) {
                    var previsti = (blocco.getAttribute('data-catageo-formato') || '').split(/\s+/);
                    var visibile = previsti.indexOf(scelto) !== -1;
                    blocco.hidden = !visibile;
                    blocco.querySelectorAll('input, select, textarea').forEach(function (campo) {
                        campo.disabled = !visibile;
                    });
                });
            };

            selettoreFormato.addEventListener('change', applicaFormato);
            applicaFormato();
        }

        // ------------------------------------------ validazione dei form Bootstrap
        document.querySelectorAll('form.needs-validation').forEach(function (form) {
            form.addEventListener('submit', function (evento) {
                if (!form.checkValidity()) {
                    evento.preventDefault();
                    evento.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    });
})();
