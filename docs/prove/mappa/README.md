# Verifica della cartografia (fase 4)

Le suite di prova via HTTP verificano quello che il **server** produce: pagina,
GeoJSON, riservatezza, intestazioni. Non possono verificare quello che fa il
**browser**, cioè se la mappa si disegna davvero, se i marker finiscono dove
devono e se il raggruppamento scatta. Questo file registra i controlli fatti
nel browser e come rifarli, perché una mappa "che risponde 200" può essere
comunque una mappa vuota.

## Come rifare i controlli

1. Avviare un'istanza usa-e-getta e popolarla eseguendo `prova-mappa.ps1`
   (crea quattro ipogei con riservatezza diversa in tre comuni).
2. Aprire `http://127.0.0.1:8123/index.php?p=mappa` e accedere.
3. La mappa espone la propria istanza in `window.CATAGEO`:

   ```js
   window.CATAGEO.mappa      // istanza Leaflet
   window.CATAGEO.ipogei     // elementi caricati: {latlng, prop, visibile, testo}
   window.CATAGEO.ridisegna  // forza il ridisegno
   ```

   Non è un espediente per le prove: è il punto d'innesto con cui i rilievi KML
   (fase 6) e i punti dei diari (fase 7) aggiungeranno layer a **questa** mappa
   invece di crearne una seconda.

## Esiti registrati — 2026-08-05

Istanza di prova con 3 ipogei georeferenziati (2 naturali, 1 artificiale, uno
dei quali con coordinate offuscate) e 1 privo di coordinate.

| Controllo | Esito |
|---|---|
| Leaflet e proj4 caricati dal server locale, nessuna CDN | ✅ |
| 18 tile scaricate, 0 fallite, attribuzione OSM visibile | ✅ |
| Nessun errore in console, nessuna violazione di CSP | ✅ |
| `fitBounds` iniziale sui dati: zoom 9, centro 42.150 / 12.600 | ✅ |
| 3 marker, colori per natura: 2 × `#0f766e` (naturale), 1 × `#c2410c` (artificiale) | ✅ |
| Legenda con 5 voci (3 nature + non praticabile + gruppo) | ✅ |
| Controllo di scala metrica presente | ✅ |
| Riga di stato: «3 su 3 ipogei georeferenziati · 1 senza coordinate» | ✅ |
| Raggruppamento: zoom 9 → 3 marker; zoom 7 → 3 marker; zoom 5 → 1 cluster «3» | ✅ |
| Clic sul cluster → inquadratura del gruppo, torna a zoom 9 e 3 marker | ✅ |
| Filtro natura = artificiale → 1 marker; ripristino → 3 | ✅ |
| Filtro testuale «offusc» → 1 marker; «zzzz» → 0 marker | ✅ |
| Filtro catalogo = DEMO → 3 marker | ✅ |
| Popup: nome, codice, tipologia risolta, stato d'accesso, link alla scheda | ✅ |
| Mappa di scheda: zoom 16, centro esattamente sul punto, rotella disabilitata | ✅ |
| Scheda con coordinate offuscate (utente USR): zoom limitato a 12, cerchio da 1000 m, **nessun marker puntuale**, coordinate vere assenti dall'HTML | ✅ |
| Scheda senza coordinate: nessuna mappetta e Leaflet non viene scaricato | ✅ |
| Controlli Leaflet leggibili in tema chiaro e scuro | ✅ |

## Lettura delle coordinate sotto il puntatore

Confrontata con il motore PHP già verificato contro proj4js, sugli stessi punti
e alla stessa risoluzione. Concordanza **al metro** su tre fusi e tre fasce:

| Punto WGS84 | PHP `Coordinate::rappresentazioni` | Lettura nel browser |
|---|---|---|
| 42.2338, 12.5273 | `33T 295962 4678696` | `33T 295962 4678696` |
| 45.5, 9.2 | `32T 515625 5038516` | `32T 515625 5038516` |
| 37.5, 15.1 | `33S 508839 4150346` | `33S 508839 4150346` |

Il fuso non è preso dalla configurazione ma ricavato dalla longitudine del
puntatore: chi rileva a cavallo di due fusi legge sempre quello giusto senza
cambiare impostazioni. Le due implementazioni sono indipendenti (PHP usa
`Proiezione`, il browser usa proj4js), quindi la concordanza è una verifica e
non una tautologia.

## Cosa questi controlli **non** coprono

- Il comportamento con migliaia di punti. Il tetto di 3000 marker disegnati
  senza raggruppamento è dichiarato nella riga di stato quando scatta, ma non è
  stato provato su un archivio reale di quelle dimensioni.
- I layer WMS: nessun servizio è configurato di serie (vedi `config.xml.dist`),
  quindi il ramo `L.tileLayer.wms` non è mai stato esercitato con un server
  vero. Va verificato in fase 6b, quando gli endpoint saranno stati scelti.
- Il comportamento su dispositivo mobile reale: verificato solo il layout
  a viewport ridotto, non l'uso in campagna.
