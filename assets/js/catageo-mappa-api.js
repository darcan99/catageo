/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-mappa-api.js
 *  Descrizione ..: Astrazione del provider cartografico (7.1.1) e sua
 *                  implementazione su Leaflet.
 *
 *                  In fase 4 l'astrazione non era stata scritta, e la ragione
 *                  era dichiarata: un'interfaccia con una sola implementazione
 *                  non ha modo di essere sbagliata nel punto giusto, e si
 *                  scopre quale sia il confine utile solo scrivendo la seconda.
 *                  Adesso la seconda esiste, e il confine e questo file.
 *
 *                  Cosa e finito nell'interfaccia: **solo le primitive che i
 *                  due provider realizzano in modo diverso** — creare la mappa,
 *                  i layer, i simboli, i riquadri, la proiezione in coordinate
 *                  schermo, i controlli d'angolo, gli eventi. Tutto il resto —
 *                  quando raggruppare, come filtrare, cosa scrivere nel popup —
 *                  e logica dell'applicativo e resta in catageo-mappa.js, dove
 *                  vale per entrambi i provider.
 *
 *                  La proiezione in coordinate schermo e nell'interfaccia
 *                  perche il raggruppamento dei marker lavora su una griglia di
 *                  pixel, non di gradi: senza, ogni provider dovrebbe riscrivere
 *                  il clustering, che e la parte piu delicata.
 *
 *                  **Nessuna di queste funzioni decide cosa mostrare.** La
 *                  riservatezza e gia stata applicata dal server: qui si
 *                  disegna cio che e arrivato.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Prima stesura (fase 4b).
 * ============================================================================
 *
 *  IL CONTRATTO
 *
 *  Un provider e un oggetto costruito da CatageoMappa.crea(contenitore, cfg,
 *  opzioni) che espone:
 *
 *    punto(lat, lon)                 -> Punto opaco del provider
 *    latDi(punto) / lonDi(punto)     -> numeri
 *    riquadro(punti)                 -> Riquadro opaco
 *    riquadroValido(r)               -> bool
 *    riquadroDegenere(r)             -> bool: tutti i punti coincidono
 *    estendiRiquadro(r, punto)       -> Riquadro
 *    adattaVista(riquadro, opzioni)  -> void   opzioni: {margine, zoomMassimo}
 *    centra(punto, zoom)             -> void
 *    zoomCorrente()                  -> numero
 *    proietta(punto, zoom)           -> {x, y} in pixel
 *    riquadroVista(margine)          -> Riquadro della vista, allargato
 *    contiene(riquadro, punto)       -> bool
 *    localizza(zoomMassimo)          -> void
 *
 *    gruppo()                        -> Gruppo con aggiungi(oggetto) e svuota()
 *    cerchio(punto, stile)           -> Oggetto disegnabile
 *    cerchioMetri(punto, raggio, stile)
 *    simbolo(punto, html, classe, dimensione)
 *    geoJson(dati, opzioni)          -> Strato con riquadro() e aggiungiA(mappa)
 *    popup(oggetto, html)            -> void
 *    apriPopup(oggetto, html)        -> void
 *    alClick(oggetto, callback)      -> void
 *
 *    aggiungiOverlay(nome, strato, acceso) -> void
 *    su(evento, callback)            -> void   ('mousemove'|'mouseout'|'vista')
 *    controlloAngolo(posizione, elemento)  -> void  ('bassoSinistra'|'bassoDestra')
 *    nativa()                        -> l'oggetto mappa del provider
 * ============================================================================
 */
(function () {
    'use strict';

    var CatageoMappa = {};

    /**
     * Sceglie l'implementazione secondo la configurazione.
     *
     * Se il provider richiesto non e disponibile — tipicamente Google senza
     * chiave, o con la rete che non risponde — si ricade su Leaflet invece di
     * lasciare la pagina senza mappa. Il ripiego viene dichiarato in console e
     * segnalato in pagina da chi chiama: una mappa diversa da quella
     * configurata, e in silenzio, farebbe sospettare un guasto dei dati.
     */
    CatageoMappa.crea = function (contenitore, cfg, opzioni) {
        var richiesto = (cfg && cfg.provider) || 'osm';

        if (richiesto === 'google') {
            if (typeof window.CatageoMappaGoogle === 'function'
                && window.CatageoMappaGoogle.disponibile()) {
                return new window.CatageoMappaGoogle(contenitore, cfg, opzioni || {});
            }
            if (window.console && window.console.warn) {
                window.console.warn('CATAGEO: provider Google non disponibile, si usa Leaflet.');
            }
            CatageoMappa.ripiego = true;
        }

        if (typeof L === 'undefined') {
            return null;
        }

        return new CatageoMappaLeaflet(contenitore, cfg, opzioni || {});
    };

    /** True se si e dovuto ripiegare su un provider diverso da quello chiesto. */
    CatageoMappa.ripiego = false;

    // ========================================================================
    //  LEAFLET
    // ========================================================================

    function CatageoMappaLeaflet(contenitore, cfg, opzioni) {
        this.cfg = cfg;

        this.mappa = L.map(contenitore, {
            center: [cfg.centro.lat, cfg.centro.lon],
            zoom: opzioni.zoom,
            zoomControl: true,
            scrollWheelZoom: opzioni.scrollWheelZoom !== false
        });

        this._preparaLayer(cfg, opzioni);

        L.control.scale({ imperial: false, metric: true }).addTo(this.mappa);
    }

    /** Costruisce un layer da una voce di configurazione. */
    CatageoMappaLeaflet.prototype._layerDa = function (voce) {
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
    };

    CatageoMappaLeaflet.prototype._preparaLayer = function (cfg, opzioni) {
        var sfondi = {};
        var acceso = false;
        var self = this;

        (cfg.base || []).forEach(function (voce) {
            var layer = self._layerDa(voce);
            sfondi[voce.nome] = layer;
            // Il primo sfondo attivo va in mappa; se nessuno lo e, il primo in
            // elenco, perche una mappa senza sfondo non e utilizzabile.
            if (voce.attivo && !acceso) {
                layer.addTo(self.mappa);
                acceso = true;
            }
        });
        if (!acceso) {
            var nomi = Object.keys(sfondi);
            if (nomi.length) { sfondi[nomi[0]].addTo(self.mappa); }
        }

        var tematici = {};
        (cfg.overlay || []).forEach(function (voce) {
            var layer = self._layerDa(voce);
            tematici[voce.nome] = layer;
            if (voce.attivo) { layer.addTo(self.mappa); }
        });

        if (Object.keys(sfondi).length > 1 || Object.keys(tematici).length > 0
            || opzioni.overlayDifferiti) {
            this.controllo = L.control.layers(sfondi, tematici, { collapsed: true }).addTo(this.mappa);
        }
    };

    // ------------------------------------------------------------ geometria

    CatageoMappaLeaflet.prototype.punto = function (lat, lon) { return L.latLng(lat, lon); };
    CatageoMappaLeaflet.prototype.latDi = function (p) { return p.lat; };
    CatageoMappaLeaflet.prototype.lonDi = function (p) { return p.lng; };
    CatageoMappaLeaflet.prototype.riquadro = function (punti) { return L.latLngBounds(punti); };
    CatageoMappaLeaflet.prototype.riquadroValido = function (r) { return !!r && r.isValid(); };

    CatageoMappaLeaflet.prototype.riquadroDegenere = function (r) {
        return r.getNorthEast().equals(r.getSouthWest());
    };

    CatageoMappaLeaflet.prototype.estendiRiquadro = function (r, punto) {
        r.extend(punto);
        return r;
    };

    CatageoMappaLeaflet.prototype.adattaVista = function (riquadro, opzioni) {
        opzioni = opzioni || {};
        var margine = typeof opzioni.margine === 'number' ? opzioni.margine : 30;
        this.mappa.fitBounds(riquadro, {
            padding: [margine, margine],
            maxZoom: opzioni.zoomMassimo || 17
        });
    };

    CatageoMappaLeaflet.prototype.centra = function (punto, zoom) {
        this.mappa.setView(punto, zoom);
    };

    CatageoMappaLeaflet.prototype.zoomCorrente = function () { return this.mappa.getZoom(); };
    CatageoMappaLeaflet.prototype.proietta = function (punto, zoom) {
        return this.mappa.project(punto, zoom);
    };

    /**
     * Riquadro della vista corrente, allargato di una frazione.
     *
     * Il margine serve al disegno: si tengono i marker poco fuori schermo,
     * cosi trascinando la mappa non compaiono a scatti sul bordo.
     */
    CatageoMappaLeaflet.prototype.riquadroVista = function (margine) {
        return this.mappa.getBounds().pad(typeof margine === 'number' ? margine : 0.25);
    };

    CatageoMappaLeaflet.prototype.contiene = function (riquadro, punto) {
        return riquadro.contains(punto);
    };

    CatageoMappaLeaflet.prototype.localizza = function (zoomMassimo) {
        this.mappa.locate({ setView: true, maxZoom: zoomMassimo || 16 });
    };

    // -------------------------------------------------------------- disegno

    CatageoMappaLeaflet.prototype.gruppo = function () {
        var strato = L.layerGroup().addTo(this.mappa);
        return {
            aggiungi: function (oggetto) { oggetto.addTo(strato); },
            svuota: function () { strato.clearLayers(); }
        };
    };

    CatageoMappaLeaflet.prototype.cerchio = function (punto, stile) {
        return L.circleMarker(punto, {
            radius: stile.raggio || 6,
            color: stile.bordo || '#ffffff',
            weight: stile.spessore || 2,
            opacity: typeof stile.opacitaBordo === 'number' ? stile.opacitaBordo : 0.95,
            fillColor: stile.riempimento,
            fillOpacity: typeof stile.opacita === 'number' ? stile.opacita : 0.9,
            dashArray: stile.tratteggio || null
        });
    };

    CatageoMappaLeaflet.prototype.cerchioMetri = function (punto, raggio, stile) {
        return L.circle(punto, {
            radius: raggio,
            color: stile.bordo,
            weight: stile.spessore || 2,
            fillOpacity: typeof stile.opacita === 'number' ? stile.opacita : 0.12,
            dashArray: stile.tratteggio || null
        });
    };

    CatageoMappaLeaflet.prototype.simbolo = function (punto, html, classe, dimensione) {
        return L.marker(punto, {
            icon: L.divIcon({
                html: html,
                className: classe,
                iconSize: [dimensione, dimensione]
            })
        });
    };

    CatageoMappaLeaflet.prototype.geoJson = function (dati, opzioni) {
        opzioni = opzioni || {};
        var strato = L.geoJSON(dati, {
            style: opzioni.stile,
            pointToLayer: opzioni.perPunto,
            onEachFeature: opzioni.perElemento
        });
        var self = this;

        return {
            nativo: strato,
            aggiungiA: function () { strato.addTo(self.mappa); },
            riquadro: function () { return strato.getBounds(); }
        };
    };

    CatageoMappaLeaflet.prototype.popup = function (oggetto, html) { oggetto.bindPopup(html); };
    CatageoMappaLeaflet.prototype.apriPopup = function (oggetto, html) {
        oggetto.bindPopup(html).openPopup();
    };
    CatageoMappaLeaflet.prototype.alClick = function (oggetto, callback) {
        oggetto.on('click', callback);
    };

    // ------------------------------------------------------------ contorno

    CatageoMappaLeaflet.prototype.aggiungiOverlay = function (nome, strato, acceso) {
        var layer = strato.nativo || strato;
        if (acceso !== false) { layer.addTo(this.mappa); }
        if (this.controllo) { this.controllo.addOverlay(layer, nome); }
    };

    CatageoMappaLeaflet.prototype.su = function (evento, callback) {
        if (evento === 'mousemove') {
            this.mappa.on('mousemove', function (e) { callback(e.latlng.lat, e.latlng.lng); });
            return;
        }
        if (evento === 'mouseout') {
            this.mappa.on('mouseout', function () { callback(); });
            return;
        }
        if (evento === 'vista') {
            this.mappa.on('moveend zoomend', function () { callback(); });
        }
    };

    CatageoMappaLeaflet.prototype.controlloAngolo = function (posizione, elemento) {
        var controllo = L.control({
            position: posizione === 'bassoDestra' ? 'bottomright' : 'bottomleft'
        });
        controllo.onAdd = function () {
            L.DomEvent.disableClickPropagation(elemento);
            return elemento;
        };
        controllo.addTo(this.mappa);
    };

    CatageoMappaLeaflet.prototype.nativa = function () { return this.mappa; };

    window.CatageoMappa = CatageoMappa;
    window.CatageoMappaLeaflet = CatageoMappaLeaflet;
})();
