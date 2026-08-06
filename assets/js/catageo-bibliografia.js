/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-bibliografia.js
 *  Descrizione ..: Mostra nel modulo di una voce bibliografica i soli blocchi
 *                  che il tipo scelto prevede.
 *
 *                  I blocchi nascosti restano nel DOM e i loro campi restano
 *                  inviati: e il server a scrivere solo i campi previsti dal
 *                  tipo. Disabilitarli qui sposterebbe la regola nel browser,
 *                  dove chiunque puo cambiarla, e senza JavaScript il modulo
 *                  smetterebbe di funzionare invece di essere solo piu lungo.
 *  Versione .....: 0.10.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.10.0  2026-08-06  D.Candela  Prima stesura (fase 7b).
 * ============================================================================
 */
(function () {
    'use strict';

    function applica(tipo) {
        var blocchi = document.querySelectorAll('[data-catageo-blocco]');
        for (var i = 0; i < blocchi.length; i++) {
            var previsti = blocchi[i].getAttribute('data-catageo-blocco').split(' ');
            blocchi[i].hidden = previsti.indexOf(tipo) === -1;
        }
    }

    function tipoScelto() {
        var scelto = document.querySelector('[data-catageo-tipo-voce]:checked');
        return scelto ? scelto.value : '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var radio = document.querySelectorAll('[data-catageo-tipo-voce]');
        if (!radio.length) {
            return;
        }

        for (var i = 0; i < radio.length; i++) {
            radio[i].addEventListener('change', function () {
                applica(tipoScelto());
            });
        }

        applica(tipoScelto());
    });
})();
