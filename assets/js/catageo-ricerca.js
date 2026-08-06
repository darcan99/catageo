/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-ricerca.js
 *  Descrizione ..: Riempie latitudine e longitudine della ricerca geografica
 *                  con la posizione del browser.
 *
 *                  Solo questo: i criteri restano tutti in GET e la ricerca
 *                  funziona senza JavaScript. Qui si risparmia a chi e sul
 *                  posto di leggere le proprie coordinate da un'altra app e
 *                  ribatterle.
 *
 *                  La posizione si chiede solo su clic esplicito: chiederla al
 *                  caricamento farebbe comparire il permesso del browser a chi
 *                  sta cercando per nome e non c'entra nulla.
 *  Versione .....: 0.13.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.13.0  2026-08-06  D.Candela  Prima stesura (fase 8).
 * ============================================================================
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var pulsante = document.getElementById('catageoUsaPosizione');
        var lat = document.getElementById('latitudine');
        var lon = document.getElementById('longitudine');
        var raggio = document.getElementById('raggio');

        if (!pulsante || !lat || !lon) {
            return;
        }

        if (!navigator.geolocation) {
            pulsante.disabled = true;
            pulsante.title = 'Il browser non offre la posizione';
            return;
        }

        pulsante.addEventListener('click', function () {
            var testoIniziale = pulsante.innerHTML;
            pulsante.disabled = true;
            pulsante.textContent = 'Attendo la posizione…';

            navigator.geolocation.getCurrentPosition(
                function (posizione) {
                    // Sei decimali: circa dieci centimetri, ben oltre quel che
                    // un GPS di telefono sa davvero, ma non tronca nulla.
                    lat.value = posizione.coords.latitude.toFixed(6);
                    lon.value = posizione.coords.longitude.toFixed(6);

                    // Un raggio serve perche il criterio geografico si attivi:
                    // senza, le coordinate resterebbero inerti e sembrerebbe
                    // che il pulsante non abbia fatto nulla.
                    if (raggio && raggio.value.trim() === '') {
                        raggio.value = raggio.getAttribute('placeholder') || '2000';
                    }

                    pulsante.disabled = false;
                    pulsante.innerHTML = testoIniziale;
                },
                function (errore) {
                    pulsante.disabled = false;
                    pulsante.innerHTML = testoIniziale;
                    // Il messaggio va accanto al campo e non in un alert: un
                    // alert interromperebbe la compilazione del modulo.
                    var nota = document.createElement('div');
                    nota.className = 'catageo-nota text-danger';
                    nota.textContent = 'Posizione non disponibile: ' + errore.message;
                    pulsante.parentNode.appendChild(nota);
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
            );
        });
    });
})();
