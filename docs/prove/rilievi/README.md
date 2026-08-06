# Verifica dei rilievi (fase 6)

Conversione dei tracciati georiferiti, visualizzatore tridimensionale, documenti
2D e formati topografici specialistici.

## Conversione dei tracciati

`prova-tracciato.php` costruisce **documenti veri** e li converte: KML, KMZ come
archivio zip reale, GPX. I file di rilievo arrivano da programmi di terzi e sono
quasi sempre irregolari — è proprio l'irregolarità che va provata, non il caso
pulito.

| Controllo | Esito |
|---|---|
| KML con namespace: punto, linea e poligono | ✅ |
| **KML senza namespace** e con prefisso inusuale (`k:kml`) | ✅ letti lo stesso |
| `gx:Track`, che usa elementi separati e spazi al posto delle virgole | ✅ |
| Longitudine prima della latitudine, quota conservata | ✅ |
| Nome e descrizione del segnaposto conservati | ✅ |
| KMZ: `doc.kml`, oppure il primo `.kml` se il nome è un altro | ✅ |
| KMZ senza alcun KML | ✅ respinto con messaggio chiaro |
| GPX: tracce, **segmenti separati**, rotte, punti notevoli | ✅ |
| Geometrie degeneri (linea con un vertice, punto a 0,0, coordinate impossibili) | ✅ scartate |
| XML malformato, formato non convertibile, file assente | ✅ respinti |
| File oltre il limite di 12 MB | ✅ respinto dicendo la ragione |
| **Entità esterna** (`<!ENTITY xxe SYSTEM "http://…">`) | ✅ non risolta: conversione in 0,01 s |

Sui segmenti GPX: restano **separati** e non uniti in una linea sola. Le
interruzioni del segnale spezzano la traccia, e ricucirle disegnerebbe una retta
dove il rilevatore non è passato.

Gli **stili del KML non vengono letti**. È deliberato: uno stile tradotto a metà
produce una mappa peggiore di una mappa con uno stile scelto da noi. Il colore
dei tracciati lo decide CATAGEO — magenta, perché non compare quasi mai nella
cartografia di sfondo e resta visibile sia su bosco sia su abitato.

## End-to-end via HTTP

`prova-rilievi.ps1` carica sei rilievi di formati diversi e verifica la catena
completa.

| Controllo | Esito |
|---|---|
| La sezione Rilievi accetta caricamenti | ✅ |
| `ha_kml` e `ha_3d` segnalati nell'indice degli ipogei | ✅ |
| `?p=tracciato` unisce le geometrie di più rilievi | ✅ 5 geometrie |
| Ogni geometria dice **da quale rilievo viene** | ✅ |
| Riquadro e riepilogo per tipo calcolati | ✅ |
| Singolo rilievo richiedibile con `&prog=` | ✅ |
| Rilievo non convertibile → 400 con spiegazione | ✅ |
| Anonimo non ottiene il tracciato | ✅ |
| Esclusione di un tracciato dalla mappa | ✅ scende da 5 a 3 geometrie |
| …ma resta richiedibile singolarmente | ✅ |
| Metadati del rilievo (tipo, scala, data, strumentazione) | ✅ salvati e riletti |

> **Una trappola evitata di misura.** La casella «mostra in mappa» non spuntata
> non arriva affatto nel POST. Senza un campo sentinella, ogni *caricamento* —
> il cui modulo quella casella non ce l'ha — avrebbe spento il tracciato appena
> caricato. Il modulo di modifica invia `conMostraInMappa`, e solo in sua
> presenza l'assenza della casella significa «spento».

## Visualizzatore tridimensionale

three.js r169 servito **in locale**, senza CDN. I moduli sono ES e i loro
`import 'three'` sono stati riscritti per puntare al file locale: un'import map
avrebbe richiesto uno script inline, che la Content-Security-Policy vieta.

Verificato nel browser con un PLY reale (cubo di 8 vertici):

| Controllo | Esito |
|---|---|
| Modulo caricato, nessun errore in console | ✅ |
| Il modello **non** si carica all'apertura della pagina | ✅ pulsante esplicito |
| Tela WebGL creata (980×597) | ✅ |
| Mesh in scena: 8 vertici, 12 facce | ✅ |
| Tre luci (ambiente + principale + controluce) | ✅ |
| Ingombro calcolato e dichiarato | ✅ 1,0 × 1,0 × 1,0 |
| Camera posizionata sul modello, comandi centrati | ✅ distanza 2,77 |
| Filo di ferro: accende, spegne, aggiorna il pulsante | ✅ |
| Assi: aggiunge e rimuove l'helper | ✅ |

Il modello non si carica da solo perché una nuvola di punti può pesare decine di
megabyte, e chi apre la scheda per leggere la scala non deve scaricarla.

L'ingombro è dichiarato in «unità del file» e non in metri: le unità di un PLY
non sono dichiarate da nessuna parte, e scrivere «metri» sarebbe
un'informazione inventata.

## Tracciati sulla mappa

| Controllo | Esito |
|---|---|
| Pagina del rilievo: 1 linea + 1 punto disegnati, zoom 19 | ✅ |
| Riepilogo mostrato: «1 Point · 1 LineString» | ✅ |
| Mappa della scheda: 5 geometrie da più rilievi | ✅ |
| **L'ingresso resta inquadrato** dopo l'estensione al tracciato | ✅ |

L'ultimo punto è quello che rischiava: inquadrare il tracciato sostituendo
l'inquadratura avrebbe fatto uscire dalla vista l'ingresso, che è il dato
principale della scheda. L'inquadratura si *estende*.

## Contrasti

Sette pagine nuove misurate nei due temi con il metodo definitivo (tema imposto
da `config.xml`, preferenza locale rimossa): **nessun elemento sotto soglia**.

Un difetto trovato e corretto: la nota dentro l'intestazione di una scheda
scendeva a **4,25:1**, perché il colore secondario non regge sul fondo tinto
dell'intestazione. Ora lì usa il colore del testo normale, attenuato.

> **Falso positivo noto.** L'audit segnala il pulsante «+» di Leaflet a 1,75:1
> quando la mappa è al massimo ingrandimento: è il suo stato **disabilitato**, e
> i controlli inattivi sono esplicitamente esclusi dai requisiti di contrasto
> WCAG. Non è un difetto; l'audit semplicemente non distingue gli elementi
> inattivi.

## Cosa questi controlli **non** coprono

- **Modelli reali di rilievo.** Il PLY di prova è un cubo di otto vertici. Una
  nuvola da scanner con milioni di punti non è stata provata: né la memoria, né
  la fluidità, né il ridimensionamento automatico dei punti.
- **OBJ, STL e GLTF.** I caricatori sono installati e collegati, ma solo il ramo
  PLY è stato esercitato con un file vero.
- **Materiali e texture** dei GLTF: la liberazione della memoria video le
  gestisce, ma non è stata provata con un modello che ne abbia.
- **KML con sovrapposizioni raster** (`GroundOverlay`): vengono ignorate, non
  disegnate. Un rilievo che le usasse apparirebbe vuoto.
- **File di rilievo enormi ma sotto i 12 MB**: il tetto di 200.000 punti è
  scritto e rispettato nel codice, ma non è stato raggiunto in prova.
- **Schermo intero** del visualizzatore: come per la finestra dei media, il
  browser rifiuta `requestFullscreen` da una finestra non in primo piano.
