/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-mappa.js
 *  Descrizione ..: Logica cartografica dell'applicativo: marker degli ipogei,
 *                  raggruppamento, legenda, filtri, tracciati dei rilievi.
 *
 *                  Da qui non si chiama piu Leaflet direttamente: si passa per
 *                  l'astrazione di catageo-mappa-api.js (7.1.1), che ha due
 *                  implementazioni interscambiabili. In questo file non deve
 *                  comparire nessun riferimento a "L." ne a "google.maps": se
 *                  ne serve uno, vuol dire che manca una primitiva
 *                  nell'interfaccia, ed e li che va aggiunta.
 *
 *                  Il raggruppamento dei marker resta scritto qui e non
 *                  affidato a una libreria: serve una griglia in coordinate
 *                  schermo, che in un centinaio di righe fa quello che occorre
 *                  ed e identica sui due provider.
 *
 *                  I dati arrivano dal GeoJSON del server, che ha gia applicato
 *                  la riservatezza: qui non si decide cosa mostrare, si mostra
 *                  cio che e stato inviato.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Riscritto sull'astrazione del provider, per
 *                                l'arrivo di Google Maps (fase 4b).
 *  1.1.0  2026-08-07  D.Candela  Ingressi con coordinate proprie sulla mappa
 *                                di scheda, con tre stati di praticabilita.
 *  0.9.0  2026-08-06  D.Candela  Tracciato da dati gia presenti in pagina.
 *  0.8.0  2026-08-05  D.Candela  Tracciati dei rilievi sovrapposti.
 *  0.6.0  2026-08-05  D.Candela  Prima stesura (fase 4).
 * ============================================================================
 */
(function () {
    'use strict';

    if (typeof window.CatageoMappa === 'undefined') {
        return;
    }

    /** Lato in pixel della cella di raggruppamento. */
    var CELLA = 64;

    /** Oltre questo zoom i marker si mostrano singoli: si e abbastanza vicini. */
    var ZOOM_SENZA_CLUSTER = 17;

    /** Tetto ai marker disegnati contemporaneamente senza raggruppamento. */
    var MAX_MARKER = 3000;

    /** Stati d'accesso che indicano un ingresso non praticabile. */
    var ACCESSI_CHIUSI = ['chiuso', 'interrato', 'distrutto', 'non_localizzato'];

    // ---------------------------------------------------------------- utilita

    /** Legge un blocco <script type="application/json">. */
    function leggiJson(id, difetto) {
        var nodo = document.getElementById(id);
        if (!nodo) {
            return difetto;
        }
        try {
            return JSON.parse(nodo.textContent);
        } catch (e) {
            return difetto;
        }
    }

    /** Neutralizza il testo destinato all'HTML dei popup. */
    function esc(valore) {
        return String(valore === null || valore === undefined ? '' : valore)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Fuso e fascia UTM.
     *
     * Ripetuti qui invece di condividerli con catageo-coordinate.js: quello si
     * carica solo sul form di censimento, e la mappa non deve dipendere da un
     * file che sulle sue pagine non c'e.
     */
    function fusoPerLongitudine(lon) {
        return Math.max(1, Math.min(60, Math.floor((lon + 180) / 6) + 1));
    }

    function fasciaPerLatitudine(lat) {
        if (lat < -80 || lat > 84) { return ''; }
        if (lat >= 72) { return 'X'; }
        return 'CDEFGHJKLMNPQRSTUVWX'.charAt(Math.floor((lat + 80) / 8));
    }

    /**
     * Crea la mappa e, se si e dovuto ripiegare su un provider diverso da
     * quello configurato, lo dichiara in pagina.
     *
     * Il ripiego silenzioso sarebbe peggio del guasto: chi ha configurato
     * Google e vede OpenStreetMap penserebbe a un errore dei dati, non a una
     * chiave scaduta.
     */
    function apriMappa(contenitore, cfg, opzioni) {
        var mappa = window.CatageoMappa.crea(contenitore, cfg, opzioni);
        if (!mappa) { return null; }

        if (window.CatageoMappa.ripiego) {
            var stato = document.getElementById('catageoMappaStato');
            if (stato && !stato.hidden) {
                stato.className = 'alert alert-warning py-2';
                stato.textContent = 'Il provider cartografico configurato non e disponibile: '
                    + 'la mappa usa OpenStreetMap. Controllare la chiave API in configurazione.';
            }
        }

        return mappa;
    }

    // ----------------------------------------------------------------- marker

    /** Colore del marker secondo la natura della cavita. */
    function colore(cfg, natura) {
        var colori = cfg.colori || {};
        return colori[natura] || colori[''] || '#64748b';
    }

    /** True se l'ingresso risulta non praticabile. */
    function chiuso(prop) {
        return ACCESSI_CHIUSI.indexOf(prop.statoAccesso) !== -1;
    }

    /** Simbolo di un singolo ipogeo. */
    function creaMarker(mappa, cfg, elemento) {
        var prop = elemento.prop;
        var nonAperto = chiuso(prop);

        return mappa.cerchio(elemento.punto, {
            raggio: 6,
            bordo: '#ffffff',
            spessore: 2,
            opacitaBordo: 0.95,
            riempimento: colore(cfg, prop.natura),
            opacita: nonAperto ? 0.25 : 0.9,
            tratteggio: nonAperto ? '2,2' : null
        });
    }

    /** Contenuto del popup di un ipogeo. */
    function popupIpogeo(prop) {
        var righe = '';
        var aggiungi = function (etichetta, valore) {
            if (valore === '' || valore === null || valore === undefined) { return; }
            righe += '<dt>' + esc(etichetta) + '</dt><dd>' + esc(valore) + '</dd>';
        };

        aggiungi('Tipologia', prop.tipologiaNome || prop.tipologia);
        aggiungi('Comune', prop.comune);
        aggiungi('Localita', prop.localita);
        aggiungi('Quota', prop.quota === '' ? '' : prop.quota + ' m');
        aggiungi('Sviluppo', prop.sviluppo === '' ? '' : prop.sviluppo + ' m');
        aggiungi('Dislivello', prop.dislivello === '' ? '' : prop.dislivello + ' m');
        aggiungi('Accesso', prop.statoAccesso ? prop.statoAccesso.replace(/_/g, ' ') : '');

        var avviso = prop.offuscate
            ? '<span class="catageo-popup-avviso">Posizione approssimata: '
              + 'le coordinate esatte sono riservate.</span>'
            : '';

        return '<div class="catageo-popup">'
            + '<div class="catageo-popup-titolo">' + esc(prop.nome || '(senza nome)') + '</div>'
            + '<div class="catageo-popup-codice">' + esc(prop.codice) + '</div>'
            + '<dl>' + righe + '</dl>'
            + avviso
            + '<a class="btn btn-sm btn-primary" href="' + esc(prop.url) + '">Apri la scheda</a>'
            + '</div>';
    }

    /** Simbolo di un gruppo di ipogei sovrapposti alla scala corrente. */
    function creaCluster(mappa, gruppo, centro) {
        var n = gruppo.length;
        var classe = n < 10 ? 'catageo-cluster-p' : (n < 100 ? 'catageo-cluster-m' : 'catageo-cluster-g');
        var lato = n < 10 ? 30 : (n < 100 ? 36 : 44);

        return mappa.simbolo(centro, '<div>' + n + '</div>', 'catageo-cluster ' + classe, lato);
    }

    /** Popup con l'elenco degli ipogei di un gruppo che non si puo separare. */
    function popupElenco(gruppo) {
        var voci = gruppo.slice(0, 30).map(function (e) {
            return '<li><a href="' + esc(e.prop.url) + '">'
                + '<span class="catageo-popup-codice">' + esc(e.prop.codice) + '</span> '
                + esc(e.prop.nome || '(senza nome)') + '</a></li>';
        }).join('');

        var resto = gruppo.length > 30
            ? '<span class="catageo-popup-avviso">e altri ' + (gruppo.length - 30) + '.</span>'
            : '';

        return '<div class="catageo-popup">'
            + '<div class="catageo-popup-titolo">' + gruppo.length + ' ipogei nello stesso punto</div>'
            + '<ul class="ps-3 mb-1">' + voci + '</ul>' + resto + '</div>';
    }

    // -------------------------------------------------------------- controlli

    /** Legenda: spiega colori e simboli, altrimenti restano decorazioni. */
    function aggiungiLegenda(mappa, cfg, nature) {
        var div = document.createElement('div');
        div.className = 'catageo-legenda';

        var voci = '';
        Object.keys(nature).forEach(function (codice) {
            voci += '<li><span class="catageo-legenda-segno" style="background-color:'
                + esc(colore(cfg, codice)) + '"></span>' + esc(nature[codice]) + '</li>';
        });
        voci += '<li><span class="catageo-legenda-segno catageo-legenda-segno-chiuso"></span>'
            + 'Ingresso non praticabile</li>';
        voci += '<li><span class="catageo-legenda-segno" style="background-color:rgba(13,110,253,.85)">'
            + '</span>Gruppo di ipogei</li>';

        div.innerHTML = '<h2>Legenda</h2><ul>' + voci + '</ul>';
        mappa.controlloAngolo('bassoDestra', div);
    }

    /**
     * Lettura continua delle coordinate sotto il puntatore.
     *
     * Il fuso UTM si ricava dalla longitudine invece di essere fissato in
     * configurazione: chi rileva in una zona di confine fra due fusi vede
     * sempre quello giusto, senza doverlo cambiare a mano.
     */
    function aggiungiCoordinate(mappa) {
        var div = document.createElement('div');
        div.className = 'catageo-coordinate-puntatore';
        div.textContent = 'Muovere il puntatore sulla mappa';

        mappa.controlloAngolo('bassoSinistra', div);

        mappa.su('mousemove', function (lat, lon) {
            var testo = lat.toFixed(6) + ', ' + lon.toFixed(6) + ' WGS84';

            if (typeof proj4 !== 'undefined') {
                var fuso = fusoPerLongitudine(lon);
                var emisfero = lat < 0 ? ' +south' : '';
                try {
                    var p = proj4(
                        '+proj=longlat +datum=WGS84 +no_defs',
                        '+proj=utm +zone=' + fuso + emisfero + ' +datum=WGS84 +units=m +no_defs',
                        [lon, lat]
                    );
                    // Niente separatore delle migliaia: una coordinata si legge e
                    // si ridigita su un GPS.
                    testo += '  |  ' + fuso + fasciaPerLatitudine(lat) + ' '
                        + Math.round(p[0]) + ' ' + Math.round(p[1]);
                } catch (e) {
                    // Fuori dal dominio della proiezione: bastano i gradi.
                }
            }

            div.textContent = testo;
        });

        mappa.su('mouseout', function () {
            div.textContent = 'Muovere il puntatore sulla mappa';
        });
    }

    // ------------------------------------------------------------- tracciati

    /**
     * Stile dei rilievi sovrapposti alla mappa.
     *
     * Il colore lo decide CATAGEO e non il file: gli stili KML tradotti a meta
     * producono una mappa peggiore di una mappa con uno stile coerente. Il
     * magenta non e casuale — non compare quasi mai nella cartografia di sfondo,
     * quindi un tracciato resta visibile sia su bosco sia su abitato.
     */
    var STILE_TRACCIATO = {
        color: '#d6208f',
        weight: 3,
        opacity: 0.95,
        fillColor: '#d6208f',
        fillOpacity: 0.15
    };

    /** Contenuto del popup di una geometria di rilievo. */
    function popupTracciato(proprieta) {
        var righe = '';
        if (proprieta.nome) {
            righe += '<div class="catageo-popup-titolo">' + esc(proprieta.nome) + '</div>';
        }
        if (proprieta.rilievo) {
            righe += '<div class="catageo-popup-codice">' + esc(proprieta.rilievo)
                  + (proprieta.rilievoTitolo ? ' · ' + esc(proprieta.rilievoTitolo) : '') + '</div>';
        }
        if (proprieta.descrizione) {
            righe += '<div class="mt-1">' + esc(proprieta.descrizione) + '</div>';
        }
        return righe === '' ? null : '<div class="catageo-popup">' + righe + '</div>';
    }

    /**
     * Strato da una raccolta GeoJSON gia in memoria.
     *
     * E separato dallo scaricamento perche non tutti i tracciati arrivano dalla
     * rete: i punti di un diario sono gia nella pagina che li elenca, e farne
     * una seconda richiesta significherebbe rileggere lo stesso file due volte.
     */
    function costruisciTracciato(mappa, dati) {
        return mappa.geoJson(dati, {
            stile: STILE_TRACCIATO,
            perPunto: function (elemento, posizione) {
                return mappa.cerchio(posizione, {
                    raggio: 4, bordo: '#ffffff', spessore: 1.5,
                    riempimento: STILE_TRACCIATO.color, opacita: 0.9
                });
            },
            perElemento: function (elemento, strato) {
                var contenuto = popupTracciato(elemento.properties || {});
                if (contenuto) { strato.bindPopup(contenuto); }
            }
        });
    }

    /**
     * Scarica un tracciato GeoJSON e lo sovrappone alla mappa.
     *
     * Restituisce il layer creato, cosi chi chiama puo inquadrarlo insieme al
     * resto invece di due inquadrature che si sovrascrivono.
     */
    function aggiungiTracciato(mappa, url, alFatto) {
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (risposta) {
                if (!risposta.ok) { throw new Error('HTTP ' + risposta.status); }
                return risposta.json();
            })
            .then(function (dati) {
                if (dati.errore) { throw new Error(dati.errore); }

                var strato = costruisciTracciato(mappa, dati);
                strato.aggiungiA();
                alFatto(null, strato, dati.catageo || {});
            })
            .catch(function (errore) {
                alFatto(errore, null, null);
            });
    }

    // ------------------------------------------------------- mappa principale

    /**
     * Mappa che mostra soltanto un tracciato di rilievo.
     *
     * E il caso della pagina del singolo rilievo: non ci sono marker di ipogei
     * da raggruppare, c'e una geometria da guardare.
     */
    function avviaMappaTracciato(contenitore, cfg, urlTracciato) {
        var mappa = apriMappa(contenitore, cfg, { zoom: cfg.zoom });
        if (!mappa) { return; }

        var stato = document.getElementById('catageoTracciatoStato');

        aggiungiCoordinate(mappa);

        if (stato) { stato.textContent = 'Caricamento del tracciato…'; }

        aggiungiTracciato(mappa, urlTracciato, function (errore, strato, meta) {
            if (errore) {
                if (stato) { stato.textContent = 'Tracciato non caricato: ' + errore.message; }
                return;
            }

            var limiti = strato.riquadro();
            if (mappa.riquadroValido(limiti)) {
                mappa.adattaVista(limiti, { margine: 30, zoomMassimo: 19 });
            }

            if (stato) {
                var pezzi = [];
                Object.keys(meta.riepilogo || {}).forEach(function (tipo) {
                    pezzi.push(meta.riepilogo[tipo] + ' ' + tipo);
                });
                stato.textContent = pezzi.length ? pezzi.join(' · ') : 'Nessuna geometria nel file';

                // Gli avvisi non si nascondono: un rilievo che non si e potuto
                // convertire deve dirlo, altrimenti sembra solo che manchi.
                if (meta.avvisi && meta.avvisi.length) {
                    stato.textContent += ' — ' + meta.avvisi.join(' ');
                }
            }
        });

        window.CATAGEO = window.CATAGEO || {};
        window.CATAGEO.mappa = mappa;
    }

    /** Tracciato i cui dati sono gia nella pagina (punti di un diario). */
    function avviaMappaTracciatoInPagina(contenitore, cfg, dati) {
        var mappa = apriMappa(contenitore, cfg, { zoom: cfg.zoom });
        if (!mappa) { return; }

        aggiungiCoordinate(mappa);

        window.CATAGEO = window.CATAGEO || {};
        window.CATAGEO.mappa = mappa;

        if (!dati || !dati.features || !dati.features.length) {
            return;
        }

        var strato = costruisciTracciato(mappa, dati);
        strato.aggiungiA();

        var limiti = strato.riquadro();
        if (mappa.riquadroValido(limiti)) {
            mappa.adattaVista(limiti, { margine: 30, zoomMassimo: cfg.zoomScheda || 17 });
        }
    }

    function avviaMappaElenco(contenitore, cfg) {
        var urlDati = contenitore.getAttribute('data-catageo-geojson');
        var mappa = apriMappa(contenitore, cfg, {
            zoom: cfg.zoom,
            // I perimetri delle aree arrivano dopo, via fetch: il controllo
            // dei layer deve esistere gia per poterli accogliere.
            overlayDifferiti: !!contenitore.getAttribute('data-catageo-perimetri')
        });
        if (!mappa) { return; }

        var gruppoMarker = mappa.gruppo();

        var elementi = [];
        var senzaCoordinate = 0;
        var troncato = false;

        var stato = document.getElementById('catageoMappaStato');
        var visibiliInfo = document.getElementById('catageoMappaVisibili');

        var nature = leggiJson('catageoMappaNature', {});
        aggiungiLegenda(mappa, cfg, nature);
        aggiungiCoordinate(mappa);

        /*
         * Perimetri delle aree speleologiche, dove esistono. Vanno sotto i
         * marker — si aggiungono per primi — perche sono uno sfondo: se
         * coprissero i puntini nasconderebbero proprio le cavita che l'area
         * serve a raggruppare.
         *
         * Il layer si aggiunge al selettore invece di essere sempre acceso:
         * su un catasto carsico i perimetri non ci sono quasi mai, e un
         * controllo che accende un layer vuoto e solo rumore.
         */
        var urlPerimetri = contenitore.getAttribute('data-catageo-perimetri');
        if (urlPerimetri) {
            fetch(urlPerimetri, { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (dati) {
                    if (!dati || !dati.features || !dati.features.length) {
                        return;
                    }
                    var strato = mappa.geoJson(dati, {
                        stile: {
                            color: '#7c3aed',
                            weight: 2,
                            fillOpacity: 0.07,
                            dashArray: '6,4'
                        },
                        perElemento: function (feature, layer) {
                            var prop = (feature && feature.properties) || {};
                            if (prop.areaNome) {
                                layer.bindPopup(
                                    '<div class="catageo-popup"><div class="catageo-popup-titolo">'
                                    + esc(prop.areaNome) + '</div><div>Area speleologica</div></div>'
                                );
                            }
                        }
                    });
                    mappa.aggiungiOverlay('Perimetri delle aree', strato, true);
                })
                .catch(function () {
                    // Una mappa senza perimetri resta una mappa valida: il
                    // guasto non deve togliere anche i marker.
                });
        }

        // ------------------------------------------------------ filtri client
        var campoTesto = document.getElementById('mappaFiltroTesto');
        var campoNatura = document.getElementById('mappaFiltroNatura');
        var campoCatalogo = document.getElementById('mappaFiltroCatalogo');
        var campoAccesso = document.getElementById('mappaFiltroAccesso');

        /**
         * I filtri lavorano sui dati gia scaricati: nessun viaggio al server,
         * quindi la risposta e immediata mentre si esplora la mappa.
         */
        function applicaFiltri() {
            var testo = (campoTesto && campoTesto.value ? campoTesto.value : '')
                .trim().toLowerCase();
            var natura = campoNatura ? campoNatura.value : '';
            var catalogo = campoCatalogo ? campoCatalogo.value : '';
            var accesso = campoAccesso ? campoAccesso.value : '';

            elementi.forEach(function (e) {
                var p = e.prop;
                var ok = true;

                if (natura && p.natura !== natura) { ok = false; }
                if (ok && catalogo && p.catalogo !== catalogo) { ok = false; }
                if (ok && accesso === 'aperto' && chiuso(p)) { ok = false; }
                if (ok && accesso === 'chiuso' && !chiuso(p)) { ok = false; }
                if (ok && testo && e.testo.indexOf(testo) === -1) { ok = false; }

                e.visibile = ok;
            });

            ridisegna();
        }

        // -------------------------------------------------------- disegno
        function ridisegna() {
            gruppoMarker.svuota();

            var zoom = mappa.zoomCorrente();
            var limiti = mappa.riquadroVista(0.25);
            var selezionati = 0;
            var visibili = [];

            elementi.forEach(function (e) {
                if (!e.visibile) { return; }
                selezionati++;
                if (mappa.contiene(limiti, e.punto)) { visibili.push(e); }
            });

            troncato = false;

            if (!cfg.cluster || zoom >= ZOOM_SENZA_CLUSTER) {
                if (visibili.length > MAX_MARKER) {
                    visibili = visibili.slice(0, MAX_MARKER);
                    troncato = true;
                }
                visibili.forEach(function (e) {
                    var marker = creaMarker(mappa, cfg, e);
                    mappa.popup(marker, popupIpogeo(e.prop));
                    gruppoMarker.aggiungi(marker);
                });
            } else {
                disegnaRaggruppati(visibili, zoom);
            }

            aggiornaStato(selezionati, visibili.length);
        }

        /** Griglia in coordinate schermo: una cella, un simbolo. */
        function disegnaRaggruppati(visibili, zoom) {
            var celle = {};

            visibili.forEach(function (e) {
                var p = mappa.proietta(e.punto, zoom);
                var chiave = Math.floor(p.x / CELLA) + ':' + Math.floor(p.y / CELLA);
                if (!celle[chiave]) { celle[chiave] = []; }
                celle[chiave].push(e);
            });

            Object.keys(celle).forEach(function (chiave) {
                var gruppo = celle[chiave];

                if (gruppo.length === 1) {
                    var singolo = creaMarker(mappa, cfg, gruppo[0]);
                    mappa.popup(singolo, popupIpogeo(gruppo[0].prop));
                    gruppoMarker.aggiungi(singolo);
                    return;
                }

                var somma = gruppo.reduce(function (acc, e) {
                    return [acc[0] + mappa.latDi(e.punto), acc[1] + mappa.lonDi(e.punto)];
                }, [0, 0]);
                var centro = mappa.punto(somma[0] / gruppo.length, somma[1] / gruppo.length);

                var limitiGruppo = mappa.riquadro(gruppo.map(function (e) { return e.punto; }));
                var marker = creaCluster(mappa, gruppo, centro);

                mappa.alClick(marker, function () {
                    // Ipogei con le stesse coordinate non si separano zoomando:
                    // in quel caso si mostra l'elenco, altrimenti si stringe.
                    if (mappa.riquadroDegenere(limitiGruppo)) {
                        mappa.apriPopup(marker, popupElenco(gruppo));
                        return;
                    }
                    mappa.adattaVista(limitiGruppo, { margine: 40, zoomMassimo: ZOOM_SENZA_CLUSTER });
                });

                gruppoMarker.aggiungi(marker);
            });
        }

        function aggiornaStato(selezionati, disegnati) {
            if (visibiliInfo) {
                var testo = selezionati + ' su ' + elementi.length + ' ipogei georeferenziati';
                if (senzaCoordinate > 0) {
                    testo += ' · ' + senzaCoordinate + ' senza coordinate';
                }
                if (troncato) {
                    testo += ' · disegnati i primi ' + MAX_MARKER
                          + ' della vista: ingrandire per vederli tutti';
                }
                visibiliInfo.textContent = testo;
            }
        }

        // ---------------------------------------------------------- pulsanti
        var btnAdatta = document.getElementById('mappaAdatta');
        if (btnAdatta) {
            btnAdatta.addEventListener('click', function () {
                var punti = elementi.filter(function (e) { return e.visibile; })
                                    .map(function (e) { return e.punto; });
                if (punti.length === 0) { return; }
                mappa.adattaVista(mappa.riquadro(punti),
                    { margine: 30, zoomMassimo: ZOOM_SENZA_CLUSTER });
            });
        }

        var btnPosizione = document.getElementById('mappaPosizione');
        if (btnPosizione) {
            btnPosizione.addEventListener('click', function () {
                mappa.localizza(16);
            });
        }

        // Istanza esposta: e il punto di innesto per i rilievi KML della fase 6
        // e per qualunque modulo che debba aggiungere layer alla stessa mappa,
        // invece di crearne una seconda. Serve anche a verificarla dall'esterno.
        window.CATAGEO = window.CATAGEO || {};
        window.CATAGEO.mappa = mappa;
        window.CATAGEO.ipogei = elementi;
        window.CATAGEO.ridisegna = ridisegna;

        // ------------------------------------------------------- caricamento
        mappa.su('vista', ridisegna);

        [campoTesto, campoNatura, campoCatalogo, campoAccesso].forEach(function (campo) {
            if (!campo) { return; }
            campo.addEventListener(campo.tagName === 'SELECT' ? 'change' : 'input', applicaFiltri);
        });

        if (!urlDati) {
            return;
        }

        fetch(urlDati, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (risposta) {
                if (!risposta.ok) { throw new Error('HTTP ' + risposta.status); }
                return risposta.json();
            })
            .then(function (dati) {
                senzaCoordinate = (dati.catageo && dati.catageo.senzaCoordinate) || 0;

                (dati.features || []).forEach(function (f) {
                    var c = f.geometry && f.geometry.coordinates;
                    if (!c || c.length < 2) { return; }
                    var prop = f.properties || {};
                    elementi.push({
                        punto: mappa.punto(c[1], c[0]),
                        prop: prop,
                        visibile: true,
                        // Indice di ricerca precalcolato: filtrare migliaia di
                        // elementi a ogni tasto premuto deve restare istantaneo.
                        testo: [prop.codice, prop.nome, prop.comune, prop.localita]
                                .join(' ').toLowerCase()
                    });
                });

                if (stato) { stato.hidden = true; }

                // I filtri si applicano subito: se la pagina e stata aperta con
                // un catalogo preselezionato, l'inquadratura deve tenerne conto.
                applicaFiltri();

                var punti = elementi.filter(function (e) { return e.visibile; })
                                    .map(function (e) { return e.punto; });
                if (punti.length > 0) {
                    mappa.adattaVista(mappa.riquadro(punti),
                        { margine: 30, zoomMassimo: ZOOM_SENZA_CLUSTER });
                }
            })
            .catch(function (errore) {
                if (stato) {
                    stato.className = 'alert alert-danger';
                    stato.textContent = 'Impossibile caricare i dati cartografici: ' + errore.message;
                }
            });
    }

    // --------------------------------------------------------- mappa di scheda

    function avviaMappaScheda(contenitore, cfg) {
        var dati = leggiJson('catageoMappaPunto', null);
        if (!dati || dati.lat === '' || dati.lon === '') {
            return;
        }

        var lat = parseFloat(dati.lat);
        var lon = parseFloat(dati.lon);
        if (isNaN(lat) || isNaN(lon)) {
            return;
        }

        var mappa = apriMappa(contenitore, cfg, {
            zoom: dati.offuscate ? Math.min(cfg.zoomScheda, 12) : cfg.zoomScheda,
            // Sulla scheda la rotella scorre la pagina: se zoomasse la mappa,
            // scorrere il documento diventerebbe impossibile.
            scrollWheelZoom: false
        });
        if (!mappa) { return; }

        var punto = mappa.punto(lat, lon);
        mappa.centra(punto, mappa.zoomCorrente());

        if (dati.offuscate) {
            // Con coordinate offuscate un puntino sarebbe una bugia precisa: si
            // disegna l'area entro cui l'ingresso si trova.
            var cerchio = mappa.cerchioMetri(punto, dati.raggio || 1000, {
                bordo: colore(cfg, dati.natura),
                spessore: 2,
                opacita: 0.12,
                tratteggio: '5,4'
            });
            mappa.gruppo().aggiungi(cerchio);
            mappa.popup(cerchio, 'Posizione approssimata: le coordinate esatte sono riservate.');
        } else {
            var segno = mappa.cerchio(punto, {
                raggio: 8, bordo: '#ffffff', spessore: 2,
                riempimento: colore(cfg, dati.natura), opacita: 0.9
            });
            mappa.gruppo().aggiungi(segno);
            mappa.popup(segno,
                '<div class="catageo-popup"><div class="catageo-popup-titolo">'
                + esc(dati.nome || '') + '</div><div class="catageo-popup-codice">'
                + esc(dati.codice || '') + '</div></div>');
        }

        /*
         * Ingressi con coordinate proprie. Su una grotta di solito non ce ne
         * sono e questo blocco non fa nulla; su un acquedotto sono la cosa che
         * si cerca, e un solo puntino non direbbe dove stanno i pozzi.
         *
         * Il colore distingue tre situazioni, non due: verde dove si passa,
         * giallo dove l'accesso c'e ma e chiuso — murato, tombato, allagato —
         * e rosso dove non c'e piu. Un pozzo tombato non e un pozzo perduto, e
         * schiacciare le due cose toglierebbe proprio il dato per cui e stato
         * censito.
         */
        var ingressi = dati.ingressi || [];
        var gruppoIngressi = mappa.gruppo();
        for (var i = 0; i < ingressi.length; i++) {
            var ing = ingressi[i];
            var ilat = parseFloat(ing.lat);
            var ilon = parseFloat(ing.lon);
            if (isNaN(ilat) || isNaN(ilon)) {
                continue;
            }

            var perduto = ['crollato', 'interrato', 'non_localizzato'].indexOf(ing.statoCodice) !== -1;
            var sbarrato = ['chiuso', 'murato', 'tombato', 'allagato'].indexOf(ing.statoCodice) !== -1;

            var righe = [];
            if (ing.tipo) { righe.push(esc(ing.tipo)); }
            if (ing.stato) { righe.push(esc(ing.stato)); }
            if (ing.progressiva) { righe.push('progressiva ' + esc(ing.progressiva) + ' m'); }

            var segnoIngresso = mappa.cerchio(mappa.punto(ilat, ilon), {
                raggio: 5, bordo: '#ffffff', spessore: 2,
                riempimento: perduto ? '#dc2626' : (sbarrato ? '#d97706' : '#16a34a'),
                opacita: 0.9
            });
            mappa.popup(segnoIngresso,
                '<div class="catageo-popup"><div class="catageo-popup-titolo">'
                + esc(ing.nome || 'Ingresso') + '</div><div>'
                + righe.join(' · ') + '</div></div>');
            gruppoIngressi.aggiungi(segnoIngresso);
        }

        aggiungiCoordinate(mappa);

        // Rilievi georiferiti dell'ipogeo, se ce ne sono: e la sovrapposizione
        // che rende la mappa di scheda utile a chi cerca l'ingresso, perche
        // mostra dove va la cavita e non solo dove si entra.
        var urlTracciati = contenitore.getAttribute('data-catageo-tracciati');
        if (urlTracciati) {
            aggiungiTracciato(mappa, urlTracciati, function (errore, strato, meta) {
                if (errore || !strato) {
                    return; // la mappa col solo ingresso resta valida
                }
                var limiti = strato.riquadro();
                if (mappa.riquadroValido(limiti)) {
                    // Si estende l'inquadratura al tracciato tenendo dentro
                    // l'ingresso: inquadrare solo il tracciato lo perderebbe.
                    mappa.adattaVista(mappa.estendiRiquadro(limiti, punto),
                        { margine: 25, zoomMassimo: cfg.zoomScheda });
                }
                window.CATAGEO.tracciati = meta;
            });
        }

        window.CATAGEO = window.CATAGEO || {};
        window.CATAGEO.mappa = mappa;
        window.CATAGEO.punto = punto;
    }

    // ------------------------------------------------------------------ avvio

    document.addEventListener('DOMContentLoaded', function () {
        var cfg = leggiJson('catageoMappaConfig', null);
        if (!cfg) {
            return;
        }

        var elenco = document.getElementById('catageoMappa');
        if (elenco) {
            // Lo stesso contenitore serve tre usi: l'elenco degli ipogei, un
            // tracciato da scaricare e un tracciato gia presente in pagina.
            // Li distingue l'attributo presente.
            var tracciato = elenco.getAttribute('data-catageo-tracciato');
            var inPagina  = elenco.getAttribute('data-catageo-tracciato-json');
            if (tracciato) {
                avviaMappaTracciato(elenco, cfg, tracciato);
            } else if (inPagina) {
                avviaMappaTracciatoInPagina(elenco, cfg, leggiJson(inPagina, null));
            } else {
                avviaMappaElenco(elenco, cfg);
            }
        }

        var scheda = document.getElementById('catageoMappaSchedaBox');
        if (scheda) {
            avviaMappaScheda(scheda, cfg);
        }
    });
})();
