/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-mappa.js
 *  Descrizione ..: Cartografia con Leaflet: sfondi, layer WMS, marker degli
 *                  ipogei, raggruppamento, legenda e filtri.
 *
 *                  Il raggruppamento dei marker e scritto qui e non affidato a
 *                  markercluster: serve una griglia in coordinate schermo, che
 *                  in un centinaio di righe fa quello che ci occorre, senza
 *                  aggiungere una dipendenza da mantenere e da aggiornare.
 *
 *                  I dati arrivano dal GeoJSON del server, che ha gia applicato
 *                  la riservatezza: qui non si decide cosa mostrare, si mostra
 *                  cio che e stato inviato.
 *  Versione .....: 0.8.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.8.0  2026-08-05  D.Candela  Tracciati dei rilievi sovrapposti.
 *  0.6.0  2026-08-05  D.Candela  Prima stesura (fase 4).
 * ============================================================================
 */
(function () {
    'use strict';

    if (typeof L === 'undefined') {
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

    // ------------------------------------------------------------------ layer

    /** Costruisce un layer Leaflet da una voce di configurazione. */
    function creaLayer(voce) {
        var comuni = {
            attribution: voce.attribuzione || '',
            maxZoom: voce.maxZoom || 19,
            minZoom: voce.minZoom || 0,
            opacity: typeof voce.opacita === 'number' ? voce.opacita : 1
        };

        if (voce.tipo === 'wms') {
            return L.tileLayer.wms(voce.url, L.extend(comuni, {
                layers: voce.layers,
                format: voce.formato || 'image/png',
                transparent: voce.trasparente !== false,
                version: voce.versione || '1.3.0'
            }));
        }

        return L.tileLayer(voce.url, L.extend(comuni, {
            subdomains: voce.sottodomini || 'abc'
        }));
    }

    /**
     * Crea la mappa con sfondi e layer tematici, e il relativo selettore.
     * Restituisce la mappa pronta all'uso.
     */
    function creaMappa(contenitore, cfg, opzioni) {
        var mappa = L.map(contenitore, {
            center: [cfg.centro.lat, cfg.centro.lon],
            zoom: opzioni.zoom,
            zoomControl: true,
            scrollWheelZoom: opzioni.scrollWheelZoom !== false
        });

        var sfondi = {};
        var acceso = false;

        (cfg.base || []).forEach(function (voce) {
            var layer = creaLayer(voce);
            sfondi[voce.nome] = layer;
            // Il primo sfondo attivo va in mappa; se nessuno lo e, il primo in
            // elenco, perche una mappa senza sfondo non e utilizzabile.
            if (voce.attivo && !acceso) {
                layer.addTo(mappa);
                acceso = true;
            }
        });
        if (!acceso) {
            var nomi = Object.keys(sfondi);
            if (nomi.length) {
                sfondi[nomi[0]].addTo(mappa);
            }
        }

        var tematici = {};
        (cfg.overlay || []).forEach(function (voce) {
            var layer = creaLayer(voce);
            tematici[voce.nome] = layer;
            if (voce.attivo) {
                layer.addTo(mappa);
            }
        });

        if (Object.keys(sfondi).length > 1 || Object.keys(tematici).length > 0) {
            L.control.layers(sfondi, tematici, { collapsed: true }).addTo(mappa);
        }

        L.control.scale({ imperial: false, metric: true }).addTo(mappa);

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

    /** Marker circolare di un singolo ipogeo. */
    function creaMarker(cfg, elemento) {
        var prop = elemento.prop;
        var nonAperto = chiuso(prop);

        return L.circleMarker(elemento.latlng, {
            radius: 6,
            color: '#ffffff',
            weight: 2,
            opacity: 0.95,
            fillColor: colore(cfg, prop.natura),
            fillOpacity: nonAperto ? 0.25 : 0.9,
            dashArray: nonAperto ? '2,2' : null
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

    /** Marker di un gruppo di ipogei sovrapposti alla scala corrente. */
    function creaCluster(gruppo, centro) {
        var n = gruppo.length;
        var classe = n < 10 ? 'catageo-cluster-p' : (n < 100 ? 'catageo-cluster-m' : 'catageo-cluster-g');
        var lato = n < 10 ? 30 : (n < 100 ? 36 : 44);

        return L.marker(centro, {
            icon: L.divIcon({
                className: 'catageo-cluster ' + classe,
                html: '<div>' + n + '</div>',
                iconSize: [lato, lato],
                iconAnchor: [lato / 2, lato / 2]
            }),
            title: n + ' ipogei in quest\'area'
        });
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
        var controllo = L.control({ position: 'bottomright' });

        controllo.onAdd = function () {
            var div = L.DomUtil.create('div', 'catageo-legenda');
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
            L.DomEvent.disableClickPropagation(div);
            return div;
        };

        controllo.addTo(mappa);
    }

    /**
     * Lettura continua delle coordinate sotto il puntatore.
     *
     * Il fuso UTM si ricava dalla longitudine invece di essere fissato in
     * configurazione: chi rileva in una zona di confine fra due fusi vede
     * sempre quello giusto, senza doverlo cambiare a mano.
     */
    function aggiungiCoordinate(mappa) {
        var controllo = L.control({ position: 'bottomleft' });
        var div = null;

        controllo.onAdd = function () {
            div = L.DomUtil.create('div', 'catageo-coordinate-puntatore');
            div.textContent = 'Muovere il puntatore sulla mappa';
            return div;
        };

        controllo.addTo(mappa);

        mappa.on('mousemove', function (evento) {
            if (!div) { return; }
            var lat = evento.latlng.lat;
            var lon = evento.latlng.lng;
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

        mappa.on('mouseout', function () {
            if (div) { div.textContent = 'Muovere il puntatore sulla mappa'; }
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
        var mappa = creaMappa(contenitore, cfg, { zoom: cfg.zoom });
        var stato = document.getElementById('catageoTracciatoStato');

        aggiungiCoordinate(mappa);

        if (stato) { stato.textContent = 'Caricamento del tracciato…'; }

        aggiungiTracciato(mappa, urlTracciato, function (errore, strato, meta) {
            if (errore) {
                if (stato) { stato.textContent = 'Tracciato non caricato: ' + errore.message; }
                return;
            }

            var limiti = strato.getBounds();
            if (limiti.isValid()) {
                mappa.fitBounds(limiti, { padding: [30, 30], maxZoom: 19 });
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

    function avviaMappaElenco(contenitore, cfg) {
        var urlDati = contenitore.getAttribute('data-catageo-geojson');
        var mappa = creaMappa(contenitore, cfg, { zoom: cfg.zoom });
        var strato = L.layerGroup().addTo(mappa);

        var elementi = [];
        var senzaCoordinate = 0;
        var troncato = false;

        var stato = document.getElementById('catageoMappaStato');
        var visibiliInfo = document.getElementById('catageoMappaVisibili');

        var nature = leggiJson('catageoMappaNature', {});
        aggiungiLegenda(mappa, cfg, nature);
        aggiungiCoordinate(mappa);

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
            strato.clearLayers();

            var zoom = mappa.getZoom();
            var limiti = mappa.getBounds().pad(0.25);
            var selezionati = 0;
            var visibili = [];

            elementi.forEach(function (e) {
                if (!e.visibile) { return; }
                selezionati++;
                if (limiti.contains(e.latlng)) { visibili.push(e); }
            });

            troncato = false;

            if (!cfg.cluster || zoom >= ZOOM_SENZA_CLUSTER) {
                if (visibili.length > MAX_MARKER) {
                    visibili = visibili.slice(0, MAX_MARKER);
                    troncato = true;
                }
                visibili.forEach(function (e) {
                    creaMarker(cfg, e).bindPopup(popupIpogeo(e.prop)).addTo(strato);
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
                var p = mappa.project(e.latlng, zoom);
                var chiave = Math.floor(p.x / CELLA) + ':' + Math.floor(p.y / CELLA);
                if (!celle[chiave]) { celle[chiave] = []; }
                celle[chiave].push(e);
            });

            Object.keys(celle).forEach(function (chiave) {
                var gruppo = celle[chiave];

                if (gruppo.length === 1) {
                    creaMarker(cfg, gruppo[0]).bindPopup(popupIpogeo(gruppo[0].prop)).addTo(strato);
                    return;
                }

                var somma = gruppo.reduce(function (acc, e) {
                    return [acc[0] + e.latlng.lat, acc[1] + e.latlng.lng];
                }, [0, 0]);
                var centro = L.latLng(somma[0] / gruppo.length, somma[1] / gruppo.length);

                var limitiGruppo = L.latLngBounds(gruppo.map(function (e) { return e.latlng; }));
                var marker = creaCluster(gruppo, centro);

                marker.on('click', function () {
                    // Ipogei con le stesse coordinate non si separano zoomando:
                    // in quel caso si mostra l'elenco, altrimenti si stringe.
                    if (limitiGruppo.getNorthEast().equals(limitiGruppo.getSouthWest())) {
                        marker.bindPopup(popupElenco(gruppo)).openPopup();
                        return;
                    }
                    mappa.fitBounds(limitiGruppo, { padding: [40, 40], maxZoom: ZOOM_SENZA_CLUSTER });
                });

                marker.addTo(strato);
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
                                    .map(function (e) { return e.latlng; });
                if (punti.length === 0) { return; }
                mappa.fitBounds(L.latLngBounds(punti), { padding: [30, 30], maxZoom: ZOOM_SENZA_CLUSTER });
            });
        }

        var btnPosizione = document.getElementById('mappaPosizione');
        if (btnPosizione) {
            btnPosizione.addEventListener('click', function () {
                mappa.locate({ setView: true, maxZoom: 16 });
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
        mappa.on('moveend zoomend', ridisegna);

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
                        latlng: L.latLng(c[1], c[0]),
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
                                    .map(function (e) { return e.latlng; });
                if (punti.length > 0) {
                    mappa.fitBounds(L.latLngBounds(punti), {
                        padding: [30, 30],
                        maxZoom: ZOOM_SENZA_CLUSTER
                    });
                }
            })
            .catch(function (errore) {
                if (stato) {
                    stato.className = 'alert alert-danger';
                    stato.textContent = 'Impossibile caricare i dati cartografici: ' + errore.message;
                }
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

                var strato = L.geoJSON(dati, {
                    style: function () { return STILE_TRACCIATO; },
                    pointToLayer: function (elemento, posizione) {
                        return L.circleMarker(posizione, {
                            radius: 4, color: '#ffffff', weight: 1.5,
                            fillColor: STILE_TRACCIATO.color, fillOpacity: 0.9
                        });
                    },
                    onEachFeature: function (elemento, strato) {
                        var contenuto = popupTracciato(elemento.properties || {});
                        if (contenuto) { strato.bindPopup(contenuto); }
                    }
                });

                strato.addTo(mappa);
                alFatto(null, strato, dati.catageo || {});
            })
            .catch(function (errore) {
                alFatto(errore, null, null);
            });
    }

    // --------------------------------------------------------- mappa di scheda

    function avviaMappaScheda(contenitore, cfg) {
        var punto = leggiJson('catageoMappaPunto', null);
        if (!punto || punto.lat === '' || punto.lon === '') {
            return;
        }

        var lat = parseFloat(punto.lat);
        var lon = parseFloat(punto.lon);
        if (isNaN(lat) || isNaN(lon)) {
            return;
        }

        var mappa = creaMappa(contenitore, cfg, {
            zoom: punto.offuscate ? Math.min(cfg.zoomScheda, 12) : cfg.zoomScheda,
            // Sulla scheda la rotella scorre la pagina: se zoomasse la mappa,
            // scorrere il documento diventerebbe impossibile.
            scrollWheelZoom: false
        });
        mappa.setView([lat, lon], mappa.getZoom());

        var latlng = L.latLng(lat, lon);

        if (punto.offuscate) {
            // Con coordinate offuscate un puntino sarebbe una bugia precisa: si
            // disegna l'area entro cui l'ingresso si trova.
            L.circle(latlng, {
                radius: punto.raggio || 1000,
                color: colore(cfg, punto.natura),
                weight: 2,
                fillOpacity: 0.12,
                dashArray: '5,4'
            }).addTo(mappa).bindPopup('Posizione approssimata: le coordinate esatte sono riservate.');
        } else {
            L.circleMarker(latlng, {
                radius: 8,
                color: '#ffffff',
                weight: 2,
                fillColor: colore(cfg, punto.natura),
                fillOpacity: 0.9
            }).addTo(mappa).bindPopup(
                '<div class="catageo-popup"><div class="catageo-popup-titolo">'
                + esc(punto.nome || '') + '</div><div class="catageo-popup-codice">'
                + esc(punto.codice || '') + '</div></div>'
            );
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
                var limiti = strato.getBounds();
                if (limiti.isValid()) {
                    // Si estende l'inquadratura al tracciato tenendo dentro
                    // l'ingresso: inquadrare solo il tracciato lo perderebbe.
                    limiti.extend(latlng);
                    mappa.fitBounds(limiti, { padding: [25, 25], maxZoom: cfg.zoomScheda });
                }
                window.CATAGEO.tracciati = meta;
            });
        }

        window.CATAGEO = window.CATAGEO || {};
        window.CATAGEO.mappa = mappa;
        window.CATAGEO.punto = latlng;
    }

    // ------------------------------------------------------------------ avvio

    document.addEventListener('DOMContentLoaded', function () {
        var cfg = leggiJson('catageoMappaConfig', null);
        if (!cfg) {
            return;
        }

        var elenco = document.getElementById('catageoMappa');
        if (elenco) {
            // Lo stesso contenitore serve due usi: l'elenco degli ipogei e la
            // vista di un singolo tracciato. Li distingue l'attributo presente.
            var tracciato = elenco.getAttribute('data-catageo-tracciato');
            if (tracciato) {
                avviaMappaTracciato(elenco, cfg, tracciato);
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
