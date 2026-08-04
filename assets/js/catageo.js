/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo.js
 *  Descrizione ..: Comportamenti comuni dell'interfaccia: alternanza del tema
 *                  chiaro/scuro e conferme sulle azioni distruttive.
 *                  Nessuna dipendenza oltre a Bootstrap, gia caricato.
 *  Versione .....: 0.1.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.1.0  2026-08-04  D.Candela  Prima stesura.
 * ============================================================================
 */
(function () {
    'use strict';

    var CHIAVE_TEMA = 'catageo.tema';

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

    /** Tema scelto dall'utente, o quello previsto dalla configurazione. */
    function temaCorrente() {
        var salvato = null;
        try {
            salvato = window.localStorage.getItem(CHIAVE_TEMA);
        } catch (e) {
            // localStorage non disponibile (navigazione privata su alcuni
            // browser): si resta sul tema di configurazione, senza errori.
            salvato = null;
        }
        return salvato || document.documentElement.getAttribute('data-catageo-tema') || 'auto';
    }

    function salvaTema(tema) {
        try {
            window.localStorage.setItem(CHIAVE_TEMA, tema);
        } catch (e) {
            // La preferenza vale solo per la sessione corrente.
        }
    }

    // Applicazione immediata, prima che l'utente veda la pagina renderizzata.
    applicaTema(temaCorrente());

    document.addEventListener('DOMContentLoaded', function () {

        // ------------------------------------------------ alternanza del tema
        var pulsante = document.getElementById('catageoTema');
        if (pulsante) {
            pulsante.addEventListener('click', function () {
                var attuale = document.documentElement.getAttribute('data-bs-theme');
                var nuovo = attuale === 'dark' ? 'light' : 'dark';
                applicaTema(nuovo);
                salvaTema(nuovo);
            });
        }

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
