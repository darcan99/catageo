/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-geologia.js
 *  Descrizione ..: Compilazione assistita della sezione geologia (6.16.2).
 *
 *                  Chiede al server i valori che i servizi cartografici
 *                  riportano sotto il punto della cavita e li propone accanto
 *                  ai campi, uno per uno, da accettare o ignorare. Non scrive
 *                  mai da solo: la carta non ha visto la cavita, e un campo
 *                  riempito senza che nessuno lo abbia guardato diventa un
 *                  dato falso che sembra vero.
 *
 *                  Se la scheda ha coordinate riservate, prima di chiedere
 *                  qualunque cosa si apre la scelta a tre vie. La decisione
 *                  vera la applica comunque il server: qui si raccoglie solo
 *                  cosa vuole l'utente.
 *  Versione .....: 1.2.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.2.0  2026-08-07  D.Candela  Prima stesura (fase 6b).
 * ==========================================================================*/

(function () {
  'use strict';

  var dati = document.getElementById('catageo-geologia-dati');
  if (!dati) { return; }

  var cfg;
  try { cfg = JSON.parse(dati.textContent); } catch (e) { return; }

  var bottone = document.getElementById('catageoInterroga');
  var pannello = document.getElementById('catageoProposte');
  var scelta = document.getElementById('catageoSceltaCoord');
  if (!bottone || !pannello) { return; }

  /* I campi del modulo che una carta puo proporre. Le chiavi sono le stesse
     che il server usa nell'attributo interroga di config.xml: se qui ne
     mancasse una, la proposta arriverebbe e non avrebbe dove andare. */
  var CAMPI = {
    litologia:      'litologia',
    formazione:     'formazione',
    unitaGeologica: 'unitaGeologica',
    etaFormazione:  'etaFormazione',
    permeabilita:   'permeabilita'
  };

  function testo(el, s) { el.textContent = s; }

  function avviso(classe, messaggio) {
    pannello.innerHTML = '';
    var d = document.createElement('div');
    d.className = 'alert alert-' + classe + ' py-2 mb-0';
    testo(d, messaggio);
    pannello.appendChild(d);
  }

  /* Una proposta per campo: valore, provenienza, e un pulsante per portarla
     nel modulo. Il valore non entra finche non lo si accetta. */
  function riga(chiave, proposta) {
    var campo = document.getElementById(CAMPI[chiave]);
    var riga = document.createElement('div');
    riga.className = 'd-flex align-items-start gap-2 border-top py-2';

    var corpo = document.createElement('div');
    corpo.className = 'flex-grow-1';

    var etichetta = document.createElement('div');
    etichetta.className = 'fw-semibold';
    var nomeCampo = campo && campo.labels && campo.labels[0]
      ? campo.labels[0].textContent.trim()
      : chiave;
    testo(etichetta, nomeCampo);

    var valore = document.createElement('div');
    testo(valore, proposta.valore);

    var fonte = document.createElement('div');
    fonte.className = 'catageo-nota';
    testo(fonte, 'da ' + proposta.layer);

    corpo.appendChild(etichetta);
    corpo.appendChild(valore);
    corpo.appendChild(fonte);

    var pulsante = document.createElement('button');
    pulsante.type = 'button';
    pulsante.className = 'btn btn-sm btn-outline-primary flex-shrink-0';

    if (!campo) {
      /* Puo capitare a chi ha solo la consultazione: il modulo non c'e. */
      pulsante.disabled = true;
      testo(pulsante, 'campo assente');
    } else if (campo.value.trim() !== '' && campo.value.trim() !== proposta.valore) {
      testo(pulsante, 'sostituisci');
      pulsante.className = 'btn btn-sm btn-outline-warning flex-shrink-0';
    } else {
      testo(pulsante, 'accetta');
    }

    pulsante.addEventListener('click', function () {
      if (!campo) { return; }
      /* Anche un <select> accetta assegnando value: se il valore non e fra le
         opzioni resta quello di prima, ed e il comportamento giusto. */
      campo.value = proposta.valore;
      campo.dispatchEvent(new Event('change', { bubbles: true }));
      campo.classList.add('border-primary');
      pulsante.disabled = true;
      testo(pulsante, 'accettato');
      segnalaFonte(proposta);
    });

    riga.appendChild(corpo);
    riga.appendChild(pulsante);
    return riga;
  }

  /* Accettare un valore letto da una carta cambia la provenienza del dato:
     se la modalita e ancora "non dichiarata" la si porta a GetFeatureInfo,
     altrimenti la si lascia com'e — una scelta gia fatta non si sovrascrive. */
  function segnalaFonte(proposta) {
    var modalita = document.getElementById('fonteModalita');
    if (modalita && modalita.value === '') {
      modalita.value = 'GetFeatureInfo';
    }
    var nome = document.getElementById('fonteNome');
    if (nome && nome.value.trim() === '') {
      nome.value = proposta.fonte;
    }
    var data = document.getElementById('fonteData');
    if (data && data.value.trim() === '') {
      var oggi = new Date();
      data.value = oggi.getFullYear() + '-'
        + String(oggi.getMonth() + 1).padStart(2, '0') + '-'
        + String(oggi.getDate()).padStart(2, '0');
    }
  }

  function mostra(esito) {
    pannello.innerHTML = '';

    var intestazione = document.createElement('div');
    intestazione.className = 'catageo-nota mb-1';
    var s = esito.messaggio || '';
    if (esito.coordinate && esito.coordinate.offuscate) {
      s += ' Inviata una coordinata arrotondata a ' + esito.coordinate.metri
         + ' m (' + esito.coordinate.lat + ', ' + esito.coordinate.lon + ').';
    }
    testo(intestazione, s);
    pannello.appendChild(intestazione);

    var chiavi = Object.keys(esito.proposte || {});
    chiavi.forEach(function (k) {
      if (CAMPI[k]) { pannello.appendChild(riga(k, esito.proposte[k])); }
    });

    if (esito.falliti && esito.falliti.length) {
      var giu = document.createElement('div');
      giu.className = 'catageo-nota border-top pt-2 mt-2';
      testo(giu, 'Non hanno risposto: '
        + esito.falliti.map(function (f) { return f.layer; }).join(', ') + '.');
      pannello.appendChild(giu);
    }
  }

  function chiedi(modo) {
    if (scelta) { scelta.hidden = true; }
    bottone.disabled = true;
    avviso('secondary', 'Interrogazione in corso...');

    var corpo = new URLSearchParams();
    corpo.set('_token', cfg.token);
    corpo.set('codice', cfg.codice);
    corpo.set('modo', modo);

    fetch('index.php?p=geo-interroga', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: corpo.toString(),
      credentials: 'same-origin'
    }).then(function (r) {
      return r.json().then(function (j) { return { stato: r.status, corpo: j }; });
    }).then(function (r) {
      bottone.disabled = false;
      if (r.corpo && r.corpo.errore) { avviso('warning', r.corpo.errore); return; }
      if (r.stato !== 200) { avviso('warning', 'Interrogazione non riuscita.'); return; }
      mostra(r.corpo);
    }).catch(function () {
      bottone.disabled = false;
      avviso('warning', 'Interrogazione non riuscita: il server non ha risposto.');
    });
  }

  bottone.addEventListener('click', function () {
    /* Su una scheda a coordinate riservate non si chiede niente finche
       l'utente non ha detto cosa puo uscire da qui. */
    if (cfg.riservate && scelta) {
      scelta.hidden = !scelta.hidden;
      return;
    }
    chiedi('puntuale');
  });

  if (scelta) {
    scelta.querySelectorAll('[data-modo]').forEach(function (b) {
      b.addEventListener('click', function () {
        var modo = b.getAttribute('data-modo');
        if (modo === 'niente') {
          scelta.hidden = true;
          avviso('secondary', 'Nessuna richiesta inviata: la coordinata non ha lasciato questo server.');
          return;
        }
        chiedi(modo);
      });
    });
  }
}());
