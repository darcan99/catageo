/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-coordinate.js
 *  Descrizione ..: Anteprima dal vivo delle coordinate durante l'inserimento.
 *
 *                  Mentre si digita, il punto viene convertito e mostrato in
 *                  tutte le notazioni d'uso: e li che un fuso sbagliato o un
 *                  est e nord invertiti si vedono a colpo d'occhio, invece di
 *                  scoprirli dopo il salvataggio.
 *
 *                  Le conversioni usano proj4js con LE STESSE definizioni che
 *                  usa il server: arrivano dalla pagina in un blocco JSON,
 *                  generato dal vocabolario dei sistemi. Il valore che finisce
 *                  in archivio resta pero quello calcolato dal server, che fa
 *                  fede: questa e assistenza all'inserimento, non la fonte del
 *                  dato.
 *  Versione .....: 0.5.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.5.0  2026-08-05  D.Candela  Prima stesura.
 * ============================================================================
 */
(function () {
    'use strict';

    var contenitore = document.getElementById('catageoAnteprimaCoordinate');
    var valori      = document.getElementById('catageoAnteprimaValori');
    var blocco      = document.getElementById('catageoDefinizioniCrs');

    if (!contenitore || !valori || !blocco || typeof proj4 === 'undefined') {
        return;
    }

    var definizioni = {};
    try {
        definizioni = JSON.parse(blocco.textContent || '{}');
    } catch (e) {
        return;
    }

    Object.keys(definizioni).forEach(function (codice) {
        try {
            proj4.defs(codice, definizioni[codice].def);
        } catch (e) {
            // Una definizione malformata non deve impedire le altre.
        }
    });

    var campoFormato = document.getElementById('formatoCoordinate');
    var campoSistema = document.getElementById('sistemaCoordinate');
    var campoLat     = document.getElementById('latitudine');
    var campoLon     = document.getElementById('longitudine');
    var campoEst     = document.getElementById('utmEst');
    var campoNord    = document.getElementById('utmNord');

    /** Interpreta gradi sessagesimali o decimali scritti a mano. */
    function gradiDaTesto(testo) {
        if (!testo) { return null; }
        var pulito = String(testo).trim().replace(/,/g, '.');
        var segno = /[SWsw]/.test(pulito) || pulito.indexOf('-') !== -1 ? -1 : 1;
        var numeri = pulito.match(/\d+(?:\.\d+)?/g);
        if (!numeri || numeri.length > 3) { return null; }

        var g = parseFloat(numeri[0]);
        var m = numeri.length > 1 ? parseFloat(numeri[1]) : 0;
        var s = numeri.length > 2 ? parseFloat(numeri[2]) : 0;
        if (m >= 60 || s >= 60) { return null; }

        return segno * (g + m / 60 + s / 3600);
    }

    /** Gradi decimali in gradi, minuti e secondi. */
    function inSessagesimali(gradi, asse) {
        var cardinale = asse === 'lon' ? (gradi >= 0 ? 'E' : 'W') : (gradi >= 0 ? 'N' : 'S');
        var a = Math.abs(gradi);
        var g = Math.floor(a);
        var restoMin = (a - g) * 60;
        var m = Math.floor(restoMin);
        var s = (restoMin - m) * 60;
        if (Math.round(s * 100) / 100 >= 60) { s = 0; m += 1; }
        if (m >= 60) { m = 0; g += 1; }
        return g + '°' + String(m).padStart(2, '0') + '\'' + s.toFixed(2).padStart(5, '0') + '"' + cardinale;
    }

    /** Fuso UTM che contiene una longitudine. */
    function fusoPerLongitudine(lon) {
        return Math.max(1, Math.min(60, Math.floor((lon + 180) / 6) + 1));
    }

    /** Lettera della fascia UTM. */
    function fasciaPerLatitudine(lat) {
        if (lat < -80 || lat > 84) { return ''; }
        if (lat >= 72) { return 'X'; }
        return 'CDEFGHJKLMNPQRSTUVWX'.charAt(Math.floor((lat + 80) / 8));
    }

    function riga(etichetta, valore, classe) {
        return '<div class="col-md-6"><span class="text-body-secondary">' + etichetta + ':</span> '
            + '<span class="' + (classe || 'catageo-valore') + '">' + valore + '</span></div>';
    }

    /** Ricalcola e mostra l'anteprima. */
    function aggiorna() {
        var formato = campoFormato ? campoFormato.value : 'decimali';
        var sistema = campoSistema ? campoSistema.value : 'EPSG:4326';
        var lat = null;
        var lon = null;
        var errore = '';

        try {
            if (formato === 'proiettate') {
                var est  = parseFloat(String(campoEst ? campoEst.value : '').replace(',', '.'));
                var nord = parseFloat(String(campoNord ? campoNord.value : '').replace(',', '.'));
                if (isFinite(est) && isFinite(nord) && definizioni[sistema]) {
                    var g = proj4(sistema, 'EPSG:4326', [est, nord]);
                    lon = g[0];
                    lat = g[1];
                }
            } else {
                lat = gradiDaTesto(campoLat ? campoLat.value : '');
                lon = gradiDaTesto(campoLon ? campoLon.value : '');
            }
        } catch (e) {
            errore = 'Conversione non riuscita: verificare i valori e il sistema.';
        }

        if (errore === '' && (lat === null || lon === null || !isFinite(lat) || !isFinite(lon))) {
            contenitore.hidden = true;
            return;
        }

        if (errore === '' && (lat < -90 || lat > 90 || lon < -180 || lon > 180)) {
            errore = 'Posizione fuori dai limiti terrestri: verificare i valori.';
        }

        contenitore.hidden = false;

        if (errore !== '') {
            valori.innerHTML = '<div class="col-12 text-danger">' + errore + '</div>';
            return;
        }

        var fuso = fusoPerLongitudine(lon);
        var codiceUtm = 'EPSG:' + (32600 + fuso);
        var utmTesto = '—';
        if (definizioni[codiceUtm]) {
            var p = proj4('EPSG:4326', codiceUtm, [lon, lat]);
            // Senza separatore delle migliaia: una coordinata si ridigita su un
            // GPS, e i punti fra le cifre sono solo un ostacolo.
            utmTesto = fuso + fasciaPerLatitudine(lat) + ' '
                + Math.round(p[0]) + ' ' + Math.round(p[1]);
        }

        var html = '';
        html += riga('Gradi decimali', lat.toFixed(6) + ', ' + lon.toFixed(6));
        html += riga('Sessagesimali', inSessagesimali(lat, 'lat') + ' ' + inSessagesimali(lon, 'lon'));
        html += riga('UTM WGS84', utmTesto);

        // L'incertezza della trasformazione di datum va detta subito, non dopo
        // il salvataggio: cambia il significato del numero che si sta leggendo.
        var opzione = campoSistema ? campoSistema.selectedOptions[0] : null;
        var accuratezza = opzione ? parseFloat(opzione.getAttribute('data-accuratezza') || '0') : 0;
        if (formato === 'proiettate' && accuratezza >= 1) {
            html += '<div class="col-12 text-warning-emphasis">Trasformazione di datum: incertezza di circa '
                + Math.round(accuratezza) + ' m, che si somma a quella del rilievo.</div>';
        }

        html += '<div class="col-12 catageo-nota">Anteprima calcolata nel browser. '
             + 'Il valore memorizzato e quello ricalcolato dal server al salvataggio.</div>';

        valori.innerHTML = html;
    }

    [campoFormato, campoSistema, campoLat, campoLon, campoEst, campoNord].forEach(function (campo) {
        if (campo) {
            campo.addEventListener('input', aggiorna);
            campo.addEventListener('change', aggiorna);
        }
    });

    aggiorna();
})();
