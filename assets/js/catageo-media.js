/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-media.js
 *  Descrizione ..: Apertura di foto e video nella finestra condivisa, con
 *                  schermo intero e scaricamento.
 *
 *                  Il contenuto viene creato al momento dell'apertura e
 *                  distrutto alla chiusura. Per i video non e un dettaglio: un
 *                  <video> lasciato nel documento continua a scaricare e, se era
 *                  in riproduzione, continua a suonare anche a finestra chiusa.
 *  Versione .....: 0.7.1
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.7.1  2026-08-05  D.Candela  Prima stesura.
 * ============================================================================
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        var finestra = document.getElementById('catageoMedia');
        if (!finestra || typeof bootstrap === 'undefined') {
            // Senza la finestra nella pagina i collegamenti restano quelli
            // normali: si apre il file, semplicemente non in una finestra.
            return;
        }

        var corpo        = document.getElementById('catageo-media-corpo');
        var titolo       = document.getElementById('catageoMediaTitolo');
        var sottotitolo  = document.getElementById('catageoMediaSottotitolo');
        var piede        = document.getElementById('catageoMediaPiede');
        var scarica      = document.getElementById('catageoMediaScarica');
        var mappa        = document.getElementById('catageoMediaMappa');
        var schermo      = document.getElementById('catageoMediaSchermo');
        var modale       = bootstrap.Modal.getOrCreateInstance(finestra);

        /** Svuota il corpo, fermando quello che stava suonando. */
        function svuota() {
            var video = corpo.querySelector('video');
            if (video) {
                video.pause();
                // Togliere il src e chiamare load() interrompe davvero il
                // trasferimento: il solo pause() lascia il download in corso.
                video.removeAttribute('src');
                video.load();
            }
            corpo.innerHTML = '';
        }

        function apri(dati) {
            svuota();

            titolo.textContent      = dati.titolo || '(senza titolo)';
            sottotitolo.textContent = dati.sottotitolo || '';
            piede.textContent       = dati.piede || '';
            scarica.setAttribute('href', dati.scarica || dati.url);

            if (dati.mappa) {
                mappa.setAttribute('href', dati.mappa);
                mappa.hidden = false;
            } else {
                mappa.hidden = true;
                mappa.removeAttribute('href');
            }

            if (dati.tipo === 'video') {
                var video = document.createElement('video');
                video.setAttribute('controls', '');
                video.setAttribute('preload', 'metadata');
                video.setAttribute('playsinline', '');
                video.className = 'catageo-media-contenuto';
                video.src = dati.url;
                corpo.appendChild(video);
            } else {
                var img = document.createElement('img');
                img.className = 'catageo-media-contenuto';
                img.alt = dati.titolo || '';
                img.src = dati.url;
                corpo.appendChild(img);
            }

            modale.show();
        }

        // Un solo ascoltatore sul documento invece di uno per miniatura: le
        // gallerie possono avere centinaia di elementi, e cosi funziona anche
        // per quelli che comparissero dopo.
        document.addEventListener('click', function (evento) {
            var innesco = evento.target.closest('[data-catageo-media]');
            if (!innesco) {
                return;
            }

            evento.preventDefault();
            apri({
                tipo:        innesco.getAttribute('data-media-tipo') || 'immagine',
                url:         innesco.getAttribute('data-media-url') || innesco.getAttribute('href'),
                scarica:     innesco.getAttribute('data-media-scarica') || '',
                titolo:      innesco.getAttribute('data-media-titolo') || '',
                sottotitolo: innesco.getAttribute('data-media-sottotitolo') || '',
                piede:       innesco.getAttribute('data-media-piede') || '',
                mappa:       innesco.getAttribute('data-media-mappa') || ''
            });
        });

        // ------------------------------------------------------ schermo intero
        schermo.addEventListener('click', function () {
            var contenuto = corpo.querySelector('.catageo-media-contenuto');
            if (!contenuto) {
                return;
            }

            if (document.fullscreenElement) {
                document.exitFullscreen();
                return;
            }

            // Il video va a schermo intero da solo, cosi restano i suoi comandi;
            // per un'immagine si usa il corpo della finestra, perche un <img> a
            // schermo intero su fondo bianco e sgradevole.
            var bersaglio = contenuto.tagName === 'VIDEO' ? contenuto : corpo;
            var richiesta = bersaglio.requestFullscreen
                         || bersaglio.webkitRequestFullscreen
                         || bersaglio.msRequestFullscreen;

            if (richiesta) {
                richiesta.call(bersaglio);
            }
        });

        // Uscendo dallo schermo intero con Esc, il browser NON chiude la
        // finestra: si evita che il tasto faccia due cose insieme.
        finestra.addEventListener('hide.bs.modal', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            }
        });

        finestra.addEventListener('hidden.bs.modal', svuota);
    });
})();
