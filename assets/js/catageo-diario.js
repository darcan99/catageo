/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-diario.js
 *  Descrizione ..: Righe ripetibili del modulo di redazione di un diario:
 *                  voci del diario e partecipanti.
 *
 *                  Le righe nuove si ottengono clonando l'ultima e svuotandola,
 *                  non componendo HTML qui: il modulo lo disegna il PHP, e una
 *                  seconda copia della sua struttura in JavaScript sarebbe la
 *                  prima cosa a divergere quando il modulo cambia.
 *
 *                  Senza JavaScript il modulo resta usabile: si compila la riga
 *                  presente e si salva, poi se ne aggiunge un'altra riaprendo.
 *  Versione .....: 0.9.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.9.0  2026-08-06  D.Candela  Prima stesura (fase 7).
 * ============================================================================
 */
(function () {
    'use strict';

    /**
     * Rinumera i "nome[indice]" di una riga clonata.
     *
     * L'indice deve essere nuovo e non riusato: due righe con lo stesso indice
     * arriverebbero al server come una sola, e il dato scomparirebbe in
     * silenzio invece di dare errore.
     */
    function rinumera(riga, indice) {
        var campi = riga.querySelectorAll('[name]');
        for (var i = 0; i < campi.length; i++) {
            campi[i].name = campi[i].name.replace(/\[\d+\]/, '[' + indice + ']');
        }
    }

    /** Svuota i campi di una riga clonata, lasciando intatta la struttura. */
    function svuota(riga) {
        var campi = riga.querySelectorAll('input, textarea, select');
        for (var i = 0; i < campi.length; i++) {
            var campo = campi[i];
            if (campo.tagName === 'SELECT') {
                if (campo.multiple) {
                    for (var j = 0; j < campo.options.length; j++) {
                        campo.options[j].selected = false;
                    }
                } else {
                    campo.selectedIndex = 0;
                }
            } else if (campo.type === 'checkbox' || campo.type === 'radio') {
                campo.checked = false;
            } else {
                campo.value = '';
            }
        }
    }

    /**
     * Collega un contenitore di righe ripetibili al suo pulsante di aggiunta.
     *
     * L'ultima riga non si puo togliere: un modulo senza righe non darebbe piu
     * modo di aggiungerne, perche non ci sarebbe piu niente da clonare.
     */
    function collega(idContenitore, idAggiungi, selettoreRiga, selettoreTogli) {
        var contenitore = document.getElementById(idContenitore);
        var aggiungi = document.getElementById(idAggiungi);
        if (!contenitore || !aggiungi) {
            return;
        }

        var prossimo = contenitore.querySelectorAll(selettoreRiga).length;

        aggiungi.addEventListener('click', function () {
            var righe = contenitore.querySelectorAll(selettoreRiga);
            if (!righe.length) {
                return;
            }
            var nuova = righe[righe.length - 1].cloneNode(true);
            svuota(nuova);
            rinumera(nuova, prossimo);
            prossimo += 1;
            contenitore.appendChild(nuova);

            var primo = nuova.querySelector('input, textarea, select');
            if (primo) {
                primo.focus();
            }
        });

        contenitore.addEventListener('click', function (evento) {
            var pulsante = evento.target.closest(selettoreTogli);
            if (!pulsante) {
                return;
            }
            var riga = pulsante.closest(selettoreRiga);
            if (!riga) {
                return;
            }
            if (contenitore.querySelectorAll(selettoreRiga).length <= 1) {
                svuota(riga);
                return;
            }
            riga.remove();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        collega('catageoVoci', 'catageoAggiungiVoce',
                '[data-catageo-voce]', '[data-catageo-togli-voce]');
        collega('catageoPartecipanti', 'catageoAggiungiPartecipante',
                '[data-catageo-partecipante]', '[data-catageo-togli-partecipante]');
    });
})();
