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

## Il riquadro nero — due difetti trovati con un PLY reale

Un rilievo vero caricato dopo il rilascio mostrava un riquadro nero. Il cubo di
otto vertici della suite non li faceva emergere **nessuno dei due**, e ora si
capisce perché: un cubo è una superficie con facce e coordinate piccole, cioè
l'unico caso che funzionava.

### 1. Ogni nuvola di punti veniva disegnata come superficie

Il codice distingueva nuvola e mesh così:

```js
geometria.computeVertexNormals();                       // ← una riga sopra
const conFacce = !!geometria.getIndex() || geometria.getAttribute('normal');
```

Le normali le calcolava lui stesso la riga prima, quindi `conFacce` era **sempre
vero**: ogni nuvola diventava una `Mesh` senza indice, cioè una manciata di
triangoli fra vertici consecutivi. Praticamente invisibile.

Ora la distinzione guarda **solo le facce** (`indice.count > 0`), e le normali si
calcolano dopo la decisione e solo se il file non le porta.

### 2. Le coordinate assolute non arrivano intere alla scheda grafica

I rilievi escono spesso in coordinate assolute — est 295.964, nord 4.678.705. Il
modello veniva centrato spostando l'**oggetto**, lasciando i vertici a quei
valori: la somma vertice + posizione la fa la GPU in virgola mobile a 32 bit, e
a 4.678.705 quella precisione vale **circa mezzo metro**. Un rilievo di dieci
metri diventava una scalinata; uno di un metro spariva.

Ora si traslano i **vertici** (`geometry.translate`), così i valori scendono
vicino a zero e la precisione torna piena. Le geometrie condivise si traslano una
volta sola.

### Come sono stati verificati

Sei PLY costruiti apposta — nuvola ASCII, nuvola con normali, nuvola binaria,
nuvola in UTM, mesh binaria colorata, mesh in UTM — caricati e ispezionati in
scena:

| | prima | dopo |
|---|---|---|
| Nuvole riconosciute come tali | 0 su 4 (tutte `Mesh` senza indice) | 4 su 4 `Points` |
| Primo vertice di un modello UTM | `[295964, 4678705, 230]` | `[-25, 0, 2]` |
| Dimensione dei punti | fissa a 0,02 | 0,125, proporzionata al modello |

E poi la verifica che conta davvero: proiettando i vertici con le matrici della
camera, **il 100% dei campioni cade dentro il tronco di visuale** in tutti e sei
i modelli. «C'è un oggetto in scena» non basta: va dimostrato che finisce
nell'inquadratura.

### Cosa è stato aggiunto perché non ricapiti muto

La riga sotto il visualizzatore ora dichiara **vertici e facce**: «500 vertici ·
nuvola di punti, nessuna faccia · ingombro 50,0 × 6,0 × 4,0». Con un riquadro
nero, «il file non è arrivato» e «il file è arrivato ma non si vede» sono due
guasti diversi che si somigliano, e senza numeri non si sa da che parte
cominciare. Un file valido ma **senza geometrie** lo dice esplicitamente invece
di mostrare il nero.

La suite verifica ora le due premesse nel codice — distinzione per facce,
traslazione dei vertici — perché sono esattamente ciò che regredirebbe senza che
nessuno se ne accorga.

## Cosa questi controlli **non** coprono

- **Modelli reali di grandi dimensioni.** I PLY di prova arrivano a 500 punti.
  Una nuvola da scanner con milioni di punti non è stata provata: né la memoria,
  né la fluidità.
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
