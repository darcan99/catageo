/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-mappa-google.js
 *  Descrizione ..: Implementazione del provider cartografico su Google Maps
 *                  (7.1.1, fase 4b). Stessa interfaccia di
 *                  CatageoMappaLeaflet, descritta in catageo-mappa-api.js.
 *
 *                  **Questo file non si carica mai** se il provider
 *                  configurato non e "google": e l'unica dipendenza da un
 *                  dominio terzo dell'intero applicativo (deroga documentata in
 *                  ANALISI 16.1), e chi resta su OpenStreetMap non deve
 *                  scaricarla ne subirne la Content-Security-Policy allargata.
 *
 *                  Alcune corrispondenze non sono ovvie e vale la pena
 *                  dichiararle:
 *
 *                  - **WMS e tile server** diventano un ImageMapType che
 *                    costruisce le GetMap per tile. Google non ha un supporto
 *                    WMS nativo: questa e la via prevista dalla sua API, e i
 *                    calcoli di bbox sono in EPSG:3857 come vuole il servizio.
 *                  - **La proiezione in coordinate schermo**, che serve al
 *                    raggruppamento dei marker, passa da una OverlayView: e il
 *                    solo modo di ottenerla da Google, e richiede che la mappa
 *                    sia gia disegnata. Finche non lo e si ripiega sul calcolo
 *                    di Mercatore, che da lo stesso risultato.
 *                  - **Il controllo dei layer** non esiste in Google: si
 *                    costruisce un pannello proprio, con le stesse voci del
 *                    selettore di Leaflet.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Prima stesura (fase 4b).
 * ============================================================================
 */
(function () {
    'use strict';

    var LATO_TILE = 256;

    function CatageoMappaGoogle(contenitore, cfg, opzioni) {
        this.cfg = cfg;
        this.gm = window.google.maps;

        this.mappa = new this.gm.Map(contenitore, {
            center: { lat: cfg.centro.lat, lng: cfg.centro.lon },
            zoom: opzioni.zoom,
            scrollwheel: opzioni.scrollWheelZoom !== false,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true
        });

        this._overlay = new this.gm.OverlayView();
        this._overlay.draw = function () {};
        this._overlay.setMap(this.mappa);

        this._infoWindow = new this.gm.InfoWindow();
        this._preparaLayer(cfg, opzioni || {});
    }

    /**
     * True se l'API di Google e stata caricata.
     *
     * Si controlla la presenza dell'oggetto e non della chiave: una chiave
     * scritta male produce comunque google.maps, con un cartello di errore
     * dentro la mappa. Il ripiego serve al caso in cui lo script non e proprio
     * arrivato — rete assente, dominio bloccato, chiave mancante in
     * configurazione — che e quello in cui una pagina senza mappa sarebbe la
     * sola alternativa.
     */
    CatageoMappaGoogle.disponibile = function () {
        return typeof window.google !== 'undefined'
            && typeof window.google.maps !== 'undefined'
            && typeof window.google.maps.Map === 'function';
    };

    // ------------------------------------------------------------------ layer

    /** Estremi del tile in EPSG:3857, per la GetMap di un WMS. */
    function bboxMercatore(coordinate, zoom) {
        var lato = 20037508.342789244;
        var passo = (lato * 2) / Math.pow(2, zoom);
        var minX = -lato + coordinate.x * passo;
        var maxX = minX + passo;
        var maxY = lato - coordinate.y * passo;
        var minY = maxY - passo;

        return [minX, minY, maxX, maxY].join(',');
    }

    CatageoMappaGoogle.prototype._layerDa = function (voce) {
        var gm = this.gm;

        if (voce.tipo === 'wms') {
            return new gm.ImageMapType({
                name: voce.nome,
                tileSize: new gm.Size(LATO_TILE, LATO_TILE),
                minZoom: voce.minZoom || 0,
                maxZoom: voce.maxZoom || 19,
                opacity: typeof voce.opacita === 'number' ? voce.opacita : 1,
                getTileUrl: function (coordinate, zoom) {
                    var versione = voce.versione || '1.3.0';
                    // In WMS 1.3.0 l'asse si chiama CRS, in 1.1.1 SRS: usare
                    // il nome sbagliato fa rispondere al servizio con un
                    // XML di errore al posto dell'immagine.
                    var asse = versione === '1.1.1' ? 'SRS' : 'CRS';
                    return voce.url
                        + (voce.url.indexOf('?') === -1 ? '?' : '&')
                        + 'SERVICE=WMS&REQUEST=GetMap&VERSION=' + encodeURIComponent(versione)
                        + '&LAYERS=' + encodeURIComponent(voce.layers || '')
                        + '&FORMAT=' + encodeURIComponent(voce.formato || 'image/png')
                        + '&TRANSPARENT=' + (voce.trasparente !== false ? 'TRUE' : 'FALSE')
                        + '&' + asse + '=EPSG:3857'
                        + '&WIDTH=' + LATO_TILE + '&HEIGHT=' + LATO_TILE
                        + '&BBOX=' + bboxMercatore(coordinate, zoom);
                }
            });
        }

        return new gm.ImageMapType({
            name: voce.nome,
            tileSize: new gm.Size(LATO_TILE, LATO_TILE),
            minZoom: voce.minZoom || 0,
            maxZoom: voce.maxZoom || 19,
            opacity: typeof voce.opacita === 'number' ? voce.opacita : 1,
            getTileUrl: function (coordinate, zoom) {
                var sottodomini = voce.sottodomini || 'abc';
                var s = sottodomini.charAt(Math.abs(coordinate.x + coordinate.y) % sottodomini.length);
                return voce.url
                    .replace('{s}', s)
                    .replace('{z}', zoom)
                    .replace('{x}', coordinate.x)
                    .replace('{y}', coordinate.y);
            }
        });
    };

    CatageoMappaGoogle.prototype._preparaLayer = function (cfg, opzioni) {
        var self = this;
        this._sfondi = [];
        this._tematici = [];

        (cfg.base || []).forEach(function (voce) {
            self._sfondi.push({ nome: voce.nome, layer: self._layerDa(voce), attivo: !!voce.attivo });
        });
        if (this._sfondi.length && !this._sfondi.some(function (v) { return v.attivo; })) {
            this._sfondi[0].attivo = true;
        }
        this._accendiSfondo(this._sfondi.findIndex(function (v) { return v.attivo; }));

        (cfg.overlay || []).forEach(function (voce) {
            var layer = self._layerDa(voce);
            self._tematici.push({ nome: voce.nome, layer: layer });
            if (voce.attivo) { self.mappa.overlayMapTypes.push(layer); }
        });

        this._costruisciPannello(opzioni || {});
    };

    CatageoMappaGoogle.prototype._accendiSfondo = function (indice) {
        if (indice < 0 || !this._sfondi[indice]) { return; }
        // mapTypeId "none" e uno sfondo vuoto: senza, sotto i tile resterebbe
        // la cartografia di Google, che si pagherebbe due volte e si vedrebbe
        // attraverso i layer trasparenti.
        this.mappa.setOptions({ mapTypeId: 'catageo' });
        this.mappa.mapTypes.set('catageo', this._sfondi[indice].layer);
        this._sfondoAcceso = indice;
    };

    /**
     * Pannello dei layer.
     *
     * Google non ha un equivalente di L.control.layers: si costruisce, con le
     * stesse voci, perche chi passa da un provider all'altro non deve trovarsi
     * un'interfaccia diversa.
     */
    CatageoMappaGoogle.prototype._costruisciPannello = function (opzioni) {
        /*
         * Il pannello si crea anche con un solo sfondo e nessun tematico, se
         * la pagina puo aggiungere overlay a caricamento avvenuto: senza,
         * i perimetri delle aree arriverebbero in mappa senza un modo per
         * spegnerli, mentre su Leaflet ce l'hanno. Due provider che si
         * comportano diverso sulla stessa pagina sono un difetto, non una
         * differenza di libreria.
         */
        if (this._sfondi.length <= 1 && this._tematici.length === 0
            && !(opzioni && opzioni.overlayDifferiti)) {
            this._pannello = null;
            return;
        }

        var self = this;
        var div = document.createElement('div');
        div.className = 'catageo-pannello-layer';

        this._sfondi.forEach(function (voce, i) {
            var riga = document.createElement('label');
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'catageoSfondo';
            radio.checked = i === self._sfondoAcceso;
            radio.addEventListener('change', function () { self._accendiSfondo(i); });
            riga.appendChild(radio);
            riga.appendChild(document.createTextNode(' ' + voce.nome));
            div.appendChild(riga);
        });

        this._divPannello = div;
        this._aggiungiVociTematiche();

        this.mappa.controls[this.gm.ControlPosition.TOP_RIGHT].push(div);
        this._pannello = div;
    };

    CatageoMappaGoogle.prototype._aggiungiVociTematiche = function () {
        var self = this;
        this._tematici.forEach(function (voce) {
            if (voce.riga) { return; }
            var riga = document.createElement('label');
            var casella = document.createElement('input');
            casella.type = 'checkbox';
            casella.checked = self.mappa.overlayMapTypes.getArray().indexOf(voce.layer) !== -1;
            casella.addEventListener('change', function () {
                var indice = self.mappa.overlayMapTypes.getArray().indexOf(voce.layer);
                if (casella.checked && indice === -1) {
                    self.mappa.overlayMapTypes.push(voce.layer);
                } else if (!casella.checked && indice !== -1) {
                    self.mappa.overlayMapTypes.removeAt(indice);
                }
            });
            riga.appendChild(casella);
            riga.appendChild(document.createTextNode(' ' + voce.nome));
            voce.riga = riga;
            if (self._divPannello) { self._divPannello.appendChild(riga); }
        });
    };

    // ------------------------------------------------------------- geometria

    CatageoMappaGoogle.prototype.punto = function (lat, lon) {
        return new this.gm.LatLng(lat, lon);
    };
    CatageoMappaGoogle.prototype.latDi = function (p) { return p.lat(); };
    CatageoMappaGoogle.prototype.lonDi = function (p) { return p.lng(); };

    CatageoMappaGoogle.prototype.riquadro = function (punti) {
        var r = new this.gm.LatLngBounds();
        (punti || []).forEach(function (p) { r.extend(p); });
        return r;
    };
    CatageoMappaGoogle.prototype.riquadroValido = function (r) { return !!r && !r.isEmpty(); };
    CatageoMappaGoogle.prototype.riquadroDegenere = function (r) {
        return r.getNorthEast().equals(r.getSouthWest());
    };
    CatageoMappaGoogle.prototype.estendiRiquadro = function (r, punto) {
        r.extend(punto);
        return r;
    };

    CatageoMappaGoogle.prototype.adattaVista = function (riquadro, opzioni) {
        opzioni = opzioni || {};
        var margine = typeof opzioni.margine === 'number' ? opzioni.margine : 30;
        this.mappa.fitBounds(riquadro, margine);

        // fitBounds su un riquadro piccolo porta Google a uno zoom altissimo:
        // si ricorregge dopo, perche il tetto non e un'opzione della chiamata.
        var self = this;
        var zoomMassimo = opzioni.zoomMassimo || 17;
        this.gm.event.addListenerOnce(this.mappa, 'idle', function () {
            if (self.mappa.getZoom() > zoomMassimo) { self.mappa.setZoom(zoomMassimo); }
        });
    };

    CatageoMappaGoogle.prototype.centra = function (punto, zoom) {
        this.mappa.setCenter(punto);
        if (typeof zoom === 'number') { this.mappa.setZoom(zoom); }
    };

    CatageoMappaGoogle.prototype.zoomCorrente = function () { return this.mappa.getZoom(); };

    /**
     * Coordinate schermo a uno zoom dato, per il raggruppamento.
     *
     * La proiezione della OverlayView esiste solo a mappa disegnata: finche non
     * lo e si calcola Mercatore a mano, che da lo stesso risultato. Senza il
     * ripiego il primo disegno — quello che avviene subito dopo il caricamento
     * dei dati — non raggrupperebbe nulla.
     */
    CatageoMappaGoogle.prototype.proietta = function (punto, zoom) {
        var scala = Math.pow(2, zoom);
        var proiezione = this._overlay.getProjection && this._overlay.getProjection();

        if (proiezione && proiezione.fromLatLngToPoint) {
            var p = proiezione.fromLatLngToPoint(punto);
            return { x: p.x * scala, y: p.y * scala };
        }

        var lat = punto.lat();
        var lon = punto.lng();
        var seno = Math.sin(lat * Math.PI / 180);
        return {
            x: LATO_TILE * (0.5 + lon / 360) * scala,
            y: LATO_TILE * (0.5 - Math.log((1 + seno) / (1 - seno)) / (4 * Math.PI)) * scala
        };
    };

    /**
     * Riquadro della vista, allargato.
     *
     * Google non ha un equivalente di pad(): si allarga a mano in gradi.
     * E un'approssimazione — un grado di longitudine non e un grado di
     * latitudine — ma qui serve solo a tenere qualche marker oltre il
     * bordo, e sbagliare per eccesso non fa danni.
     */
    CatageoMappaGoogle.prototype.riquadroVista = function (margine) {
        var quota = typeof margine === 'number' ? margine : 0.25;
        var r = this.mappa.getBounds();
        if (!r) { return new this.gm.LatLngBounds(); }

        var ne = r.getNorthEast();
        var sw = r.getSouthWest();
        var dLat = (ne.lat() - sw.lat()) * quota;
        var dLon = (ne.lng() - sw.lng()) * quota;

        return new this.gm.LatLngBounds(
            new this.gm.LatLng(Math.max(-90, sw.lat() - dLat), sw.lng() - dLon),
            new this.gm.LatLng(Math.min(90, ne.lat() + dLat), ne.lng() + dLon)
        );
    };

    CatageoMappaGoogle.prototype.contiene = function (riquadro, punto) {
        return riquadro.contains(punto);
    };

    CatageoMappaGoogle.prototype.localizza = function (zoomMassimo) {
        var self = this;
        if (!navigator.geolocation) { return; }
        navigator.geolocation.getCurrentPosition(function (posizione) {
            self.mappa.setCenter(new self.gm.LatLng(
                posizione.coords.latitude, posizione.coords.longitude));
            self.mappa.setZoom(zoomMassimo || 16);
        });
    };

    // --------------------------------------------------------------- disegno

    CatageoMappaGoogle.prototype.gruppo = function () {
        var oggetti = [];
        var mappa = this.mappa;
        return {
            aggiungi: function (oggetto) {
                oggetto.setMap(mappa);
                oggetti.push(oggetto);
            },
            svuota: function () {
                oggetti.forEach(function (o) { o.setMap(null); });
                oggetti = [];
            }
        };
    };

    CatageoMappaGoogle.prototype.cerchio = function (punto, stile) {
        return new this.gm.Marker({
            position: punto,
            icon: {
                path: this.gm.SymbolPath.CIRCLE,
                scale: stile.raggio || 6,
                fillColor: stile.riempimento,
                fillOpacity: typeof stile.opacita === 'number' ? stile.opacita : 0.9,
                strokeColor: stile.bordo || '#ffffff',
                strokeWeight: stile.spessore || 2
            }
        });
    };

    CatageoMappaGoogle.prototype.cerchioMetri = function (punto, raggio, stile) {
        return new this.gm.Circle({
            center: punto,
            radius: raggio,
            strokeColor: stile.bordo,
            strokeWeight: stile.spessore || 2,
            fillOpacity: typeof stile.opacita === 'number' ? stile.opacita : 0.12
        });
    };

    /**
     * Simbolo con HTML proprio, per il segno di raggruppamento.
     *
     * Google non ha un equivalente di divIcon: si disegna lo stesso cerchio con
     * il numero dentro come SVG in un data URI, cosi il segno resta identico ai
     * due provider senza dipendere da un font o da un foglio di stile.
     */
    CatageoMappaGoogle.prototype.simbolo = function (punto, html, classe, dimensione) {
        var testo = String(html).replace(/<[^>]*>/g, '').trim();
        var meta = dimensione / 2;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + dimensione
            + '" height="' + dimensione + '">'
            + '<circle cx="' + meta + '" cy="' + meta + '" r="' + (meta - 2)
            + '" fill="rgba(13,110,253,.85)" stroke="#ffffff" stroke-width="2"/>'
            + '<text x="' + meta + '" y="' + (meta + 4)
            + '" text-anchor="middle" font-family="sans-serif" font-size="12"'
            + ' fill="#ffffff">' + testo + '</text></svg>';

        return new this.gm.Marker({
            position: punto,
            icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                scaledSize: new this.gm.Size(dimensione, dimensione),
                anchor: new this.gm.Point(meta, meta)
            }
        });
    };

    CatageoMappaGoogle.prototype.geoJson = function (dati, opzioni) {
        opzioni = opzioni || {};
        var gm = this.gm;
        var strato = new gm.Data();
        strato.addGeoJson(dati);

        if (opzioni.stile) {
            var s = opzioni.stile;
            strato.setStyle({
                strokeColor: s.color,
                strokeWeight: s.weight,
                strokeOpacity: typeof s.opacity === 'number' ? s.opacity : 1,
                fillOpacity: typeof s.fillOpacity === 'number' ? s.fillOpacity : 0.1
            });
        }

        if (opzioni.perElemento) {
            strato.forEach(function (elemento) {
                var proprieta = {};
                elemento.forEachProperty(function (valore, nome) { proprieta[nome] = valore; });
                opzioni.perElemento({ properties: proprieta }, {
                    // Il popup su Google si apre dalla mappa, non dal
                    // singolo elemento: si registra qui e lo apre il click.
                    bindPopup: function (html) { elemento.setProperty('_popup', html); }
                });
            });
        }

        var self = this;
        strato.addListener('click', function (evento) {
            var html = evento.feature.getProperty('_popup');
            if (!html) { return; }
            self._infoWindow.setContent(html);
            self._infoWindow.setPosition(evento.latLng);
            self._infoWindow.open(self.mappa);
        });

        return {
            nativo: strato,
            aggiungiA: function () { strato.setMap(self.mappa); },
            riquadro: function () {
                var r = new gm.LatLngBounds();
                strato.forEach(function (elemento) {
                    elemento.getGeometry().forEachLatLng(function (p) { r.extend(p); });
                });
                return r;
            }
        };
    };

    CatageoMappaGoogle.prototype.popup = function (oggetto, html) {
        var self = this;
        oggetto.addListener('click', function () {
            self._infoWindow.setContent(html);
            self._infoWindow.open(self.mappa, oggetto);
        });
    };

    CatageoMappaGoogle.prototype.apriPopup = function (oggetto, html) {
        this._infoWindow.setContent(html);
        this._infoWindow.open(this.mappa, oggetto);
    };

    CatageoMappaGoogle.prototype.alClick = function (oggetto, callback) {
        oggetto.addListener('click', callback);
    };

    // -------------------------------------------------------------- contorno

    CatageoMappaGoogle.prototype.aggiungiOverlay = function (nome, strato, acceso) {
        var layer = strato.nativo || strato;
        if (acceso !== false && layer.setMap) { layer.setMap(this.mappa); }

        // Un gm.Data non e un ImageMapType: nel pannello si mette una casella
        // che lo accende e lo spegne, com'e per i layer tematici.
        if (this._divPannello && layer.setMap) {
            var self = this;
            var riga = document.createElement('label');
            var casella = document.createElement('input');
            casella.type = 'checkbox';
            casella.checked = acceso !== false;
            casella.addEventListener('change', function () {
                layer.setMap(casella.checked ? self.mappa : null);
            });
            riga.appendChild(casella);
            riga.appendChild(document.createTextNode(' ' + nome));
            this._divPannello.appendChild(riga);
        }
    };

    CatageoMappaGoogle.prototype.su = function (evento, callback) {
        var self = this;
        if (evento === 'mousemove') {
            this.mappa.addListener('mousemove', function (e) {
                callback(e.latLng.lat(), e.latLng.lng());
            });
            return;
        }
        if (evento === 'mouseout') {
            this.mappa.addListener('mouseout', function () { callback(); });
            return;
        }
        if (evento === 'vista') {
            this.mappa.addListener('idle', function () { callback(); });
        }
    };

    CatageoMappaGoogle.prototype.controlloAngolo = function (posizione, elemento) {
        var dove = posizione === 'bassoDestra'
            ? this.gm.ControlPosition.RIGHT_BOTTOM
            : this.gm.ControlPosition.LEFT_BOTTOM;
        this.mappa.controls[dove].push(elemento);
    };

    CatageoMappaGoogle.prototype.nativa = function () { return this.mappa; };

    window.CatageoMappaGoogle = CatageoMappaGoogle;
})();
