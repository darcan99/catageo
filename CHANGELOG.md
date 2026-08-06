# Cronologia delle modifiche

Tutte le modifiche rilevanti a CATAGEO sono annotate qui, in formato
[Keep a Changelog](https://keepachangelog.com/it/1.1.0/), con versionamento
[semantico](https://semver.org/lang/it/).

## [Non rilasciato]

## [0.8.0] — 2026-08-05

Fase 6: i rilievi.

### Aggiunto
- **La sezione Rilievi accetta caricamenti**, con i metadati previsti in analisi
  6.9: tipo, scala, sistema di riferimento, data, strumentazione, rilevatori.
- **KML, KMZ e GPX diventano tracciati sulla mappa.** La conversione in GeoJSON
  avviene sul server: Leaflet lo consuma nativamente, non serve alcun plugin, e
  soprattutto il file non e mai raggiungibile per URL diretto, quindi la
  riservatezza continua a valere.
- **Tracciato sulla mappa della scheda**: e cio che distingue «dove si entra» da
  «dove va la cavita». L'inquadratura si **estende** al tracciato tenendo dentro
  l'ingresso, invece di sostituirla e perderlo di vista.
- **Pagina propria per ogni rilievo**: un rilievo si guarda a lungo — ci si gira
  intorno, si legge la scala, si confronta con la mappa — e una finestra che
  copre il resto sarebbe d'intralcio.
- **Visualizzatore tridimensionale** per PLY, OBJ, STL e GLTF/GLB, con three.js
  r169 servito in locale: orbita, filo di ferro, assi di riferimento, ingombro,
  schermo intero.
- **PDF nel visualizzatore nativo del browser**: e gia installato ovunque, sa
  cercare e stampare, ed evita di portarsi dietro una libreria di rendering.
- Un tracciato si puo **escludere dalla mappa** senza rimuoverlo: serve quando un
  ipogeo ha piu rilievi dello stesso ramo e mostrarli tutti la renderebbe
  illeggibile. Resta comunque consultabile dalla sua pagina.
- `?p=tracciato` unisce le geometrie di piu rilievi in una raccolta sola, e
  ognuna porta con se **da quale rilievo viene**: sovrapposti devono restare
  distinguibili.

### Scelte dichiarate
- **Gli stili del KML non vengono letti.** Uno stile tradotto a meta produce una
  mappa peggiore di una mappa con uno stile coerente: il colore lo decide
  CATAGEO. Il magenta non e casuale — non compare quasi mai nella cartografia di
  sfondo, quindi un tracciato resta visibile sia su bosco sia su abitato.
- **Il modello 3D non si carica all'apertura della pagina.** Una nuvola di punti
  puo pesare decine di megabyte, e chi apre la scheda per leggere la scala non
  deve scaricarla: c'e un pulsante che dichiara anche quanto pesa.
- L'ingombro del modello e dichiarato in «unita del file» e non in metri: le
  unita di un PLY non sono scritte da nessuna parte, e affermare metri sarebbe
  un'informazione inventata.
- I **formati topografici specialistici** (Therion, Survex, VisualTopo, Compass,
  DXF) restano archiviati e scaricabili, con l'indicazione di cosa esportare per
  vederli qui dentro. Riscrivere in PHP il programma che li ha prodotti non
  sarebbe sostenibile.
- Limite di **12 MB** per la conversione in mappa: oltre, il file resta
  archiviato ma non viene sovrapposto. Un rilievo di poligonale sta in poche
  centinaia di kilobyte.

### Sicurezza
- I file di rilievo arrivano da programmi di terzi: si leggono **senza rete e
  senza entita esterne** (`LIBXML_NONET`), con un tetto di dimensione e uno di
  punti. Un documento che tenti di far scaricare qualcosa al server non ci
  riesce, ed e verificato.
- three.js e i suoi caricatori sono **serviti in locale**. I loro `import 'three'`
  sono stati riscritti per puntare al file locale: un'import map avrebbe richiesto
  uno script inline, che la Content-Security-Policy vieta.

### Corretto
- Nella scheda dell'ipogeo i rilievi non portavano alla loro pagina: il ramo
  esisteva per i video ma non per i rilievi.
- La nota dentro l'intestazione di una scheda scendeva a **4,25:1**: sul fondo
  tinto dell'intestazione il colore secondario non regge.

### Verificato
- `prova-tracciato.php`: 40 controlli su documenti veri, compresi KML senza
  namespace, con prefissi inusuali, `gx:Track`, KMZ come archivio zip reale e
  geometrie degeneri. Sono i file irregolari a dover essere provati, non quelli
  puliti.
- `prova-rilievi.ps1`: 60 controlli end-to-end via HTTP.
- Nel browser: modello PLY reale caricato e ispezionato in scena (8 vertici, 12
  facce, tre luci, camera centrata), comandi verificati, tracciati disegnati
  sulla mappa e ingresso che resta inquadrato.
- Sette pagine nuove misurate nei due temi: nessun elemento sotto soglia.
- `docs/prove/rilievi/README.md` elenca anche cio che **non** e coperto: modelli
  reali di grandi dimensioni, OBJ/STL/GLTF con file veri, GroundOverlay, misura
  sul modello.

## [0.7.1] — 2026-08-05

Media: quello che il file sa gia dire, e come si guarda.

### Aggiunto
- **Data di scatto e coordinate lette dai metadati incorporati.** Chi fotografa
  l'ingresso di una cavita con il telefono porta a casa data e posizione dentro
  il file: chiedergliele di nuovo a mano significa farsi dare un dato peggiore di
  quello che si ha gia in archivio. Si leggono l'EXIF delle foto e le scatole
  `mvhd` e `©xyz` dei contenitori MP4/MOV, per cui l'EXIF non esiste.
- I metadati riempiono **solo i campi lasciati vuoti**: un rilievo fatto con il
  GPS professionale vale piu dell'EXIF di un telefono, e l'ordine di precedenza
  deve dirlo. Nessuna sovrascrittura, mai.
- **Coordinate per singola risorsa** nell'indice di sezione e nello schema: dove
  e stata scattata la foto, che non e detto coincida con l'ingresso registrato
  nella scheda. Correggibili a mano dal modulo, virgola decimale accettata.
- **Finestra per guardare foto e video** senza lasciare la pagina, con schermo
  intero e scaricamento. Aprire ogni immagine in una scheda nuova costringeva a
  tornare indietro dopo ogni sguardo, e in una galleria di venti foto significa
  venti andate e ritorni.
- **Sotto ogni miniatura**: tipo, peso, data e — quando la risorsa ha coordinate —
  un indicatore **GPS cliccabile** che apre il punto su Google Maps. Le
  coordinate di uno scatto servono per andarci, non per leggerle.
- **I video si guardano.** Prima nella scheda comparivano solo come nomi di file
  da scaricare; ora si aprono nella finestra con i comandi di riproduzione.

### Attenzioni
- Alla chiusura della finestra il contenuto viene **rimosso**, non solo messo in
  pausa: un `<video>` lasciato nel documento continua a scaricare e, se era in
  riproduzione, continua a suonare a finestra chiusa.
- Il collegamento resta valido su ogni innesco: senza JavaScript il file si apre
  comunque, semplicemente in una scheda nuova.
- Un GPS a 0,0 viene scartato: molti apparecchi scrivono zero quando il fix non
  c'e stato, e l'Atlantico non e mai la posizione di un ipogeo.
- Il lettore di scatole MP4 si ferma davanti a una lunghezza incoerente invece di
  rincorrere posizioni a caso, e un errore di lettura non impedisce mai il
  caricamento del file.

### Verificato
- 30 prove sui metadati con **file veri costruiti byte per byte**: un JPEG con
  blocco EXIF scritto a mano e un MP4 con le scatole al posto giusto. Un parser
  di formati binari collaudato su dati finti non e collaudato.
- End-to-end via HTTP: foto con EXIF caricata, data e coordinate finite
  nell'indice, e la data indicata a mano che vince sull'EXIF.
- Nel browser: apertura della finestra, immagine decodificata, elemento video con
  i controlli, pulsante mappa solo dove ci sono coordinate, e il video davvero
  rimosso alla chiusura.

## [0.7.0] — 2026-08-05

Fase 5: allegati, foto e video.

### Aggiunto
- **Caricamento di allegati, foto e video** dalla scheda dell'ipogeo, con
  selezione multipla, metadati compilabili e progressivo automatico secondo lo
  standard di nomenclatura (4.1): `LA297-FO001-Ingresso principale.jpg`.
- **Indice di sezione** `[codice] - [Sezione].xml` (D1) come fonte di verita dei
  metadati, con schema di validazione `schemi/risorse.xsd`. Lo schema impone
  l'unicita del progressivo e del nome file: due righe con lo stesso numero
  renderebbero ambiguo ogni riferimento fra sezioni.
- **Miniature** delle foto con GD, rotazione automatica secondo l'orientamento
  EXIF, e rigenerazione su richiesta. Se GD manca la galleria mostra gli
  originali e l'interfaccia lo dichiara: meglio una galleria pesante che vuota.
- **Galleria** nel pannello Foto della scheda e **copertina** in testa alla
  scheda, scelta fra le foto caricate.
- **`scarica.php`**: consegna mediata di ogni file dell'archivio, con verifica di
  sessione, permessi e riservatezza della singola scheda. Supporta le **richieste
  parziali** (`Range`), senza le quali un video non si puo scorrere.
- Una risorsa puo essere **piu riservata della scheda** che la contiene: la foto
  che mostra l'ingresso di una cavita protetta non viene consegnata a chi ha solo
  la consultazione.
- **Rimozione conservativa**: il file finisce in `[codice] - _rimossi` con una
  marca temporale, non viene cancellato. Il progressivo **non** viene mai
  riusato, perche e citato dalle altre sezioni.

### Sicurezza
- Tre barriere indipendenti su ogni file in arrivo: **lista nera** delle
  estensioni eseguibili, **lista bianca** per sezione da `config.xml`, **tipo
  reale** del contenuto letto con `finfo`. Servono tutte e tre: l'estensione la
  sceglie chi carica, il tipo dichiarato dal browser pure, e solo il contenuto
  non mente. Un `.jpg` che contiene codice PHP viene rifiutato.
- Gli **SVG si consegnano sempre come allegato**, mai visualizzati in linea: un
  SVG e un documento XML che puo contenere script, e mostrarlo in linea
  significherebbe eseguirli nell'origine dell'applicativo.
- Il `Content-Type` della consegna si **rilegge dal contenuto** e non dall'indice:
  l'indice e un file dell'archivio, modificabile a mano, e un tipo dichiarato
  male e il primo passo di un XSS.
- Un file rifiutato non lascia tracce: verificato che non finisca ne nell'indice
  ne sul disco.

### Attenzioni per l'hosting economico
- La memoria necessaria a una miniatura viene **stimata prima** di aprire il file:
  una foto da 6000x4000 pesa 4 MB come JPEG ma quasi cento decompressa, e su un
  `memory_limit` da 128 MB produrrebbe una pagina bianca senza spiegazioni. Se non
  basta si rinuncia alla miniatura e il motivo finisce nel log.
- I file si consegnano **a blocchi da 256 KB** e non con `readfile()`: un video da
  centinaia di megabyte letto in un colpo solo supererebbe qualunque limite.

### Verificato
- Suite `prova-risorse.ps1`: 70 controlli con richieste multipart costruite a
  mano, perche il punto delicato di questa fase e cio che accade fra il browser e
  il disco e un test che salta il trasporto non lo esercita.
- Nel browser: copertina e galleria effettivamente visualizzate, miniature
  consegnate alla larghezza configurata, contrasti a norma sulle pagine nuove nei
  due temi.
- `docs/prove/risorse/README.md` elenca anche cio che **non** e coperto: video
  reali, file molto grandi, assenza di GD, caricamenti simultanei.

### Corretto in corsa
- La copertina usava la miniatura da 400 px stirata su tutta la scheda, con un
  risultato sgranato: ora usa l'originale. E una sola immagine per scheda.
- La verifica «un utente USR non puo eliminare» passava per il motivo sbagliato:
  la POST veniva respinta dal controllo CSRF perche priva di token, non dai
  permessi. L'asserzione ora dice il vero e la proprieta e stabilita altrove.

## [0.6.4] — 2026-08-05

L'aspetto lo sceglie chi consulta.

### Aggiunto
- **Menu Aspetto** nella barra in alto: tema (come il sistema / chiaro / scuro) e
  tavolozza in un solo posto. Erano due decisioni della stessa natura — come si
  vuole vedere lo schermo — e due controlli distinti che si somigliavano.
- **La scelta resta nel browser di chi l'ha fatta** e vince sul predefinito
  dell'installazione. Non e legata all'utenza registrata: non e un dato del
  catasto, ed e ragionevole preferire qualcosa di diverso da un altro computer.
- `<tavolozza>` in `config.xml` stabilisce il predefinito dell'installazione, per
  chi non ha ancora scelto. Un valore non ammesso viene ricondotto al predefinito,
  quindi un errore di battitura non lascia la pagina senza tavolozza.
- `Aspetto`: elenchi ammessi, etichette e lettura validata in un punto solo.
  Servono al layout, al menu che li mostra e al JavaScript che li applica, e un
  valore noto a due su tre produce una voce di menu che non fa nulla.

### Cambiato
- **Sabbia e la tavolozza predefinita.** Era azzurra.
- La voce attiva nel menu si segna con una **spunta** e non con il fondo pieno di
  Bootstrap: in un menu dove ogni voce e un colore, una riga blu sopra i nomi
  delle tavolozze direbbe la cosa sbagliata.

### Verificato
- **Tutte e quattro le tavolozze misurate separatamente** su otto pagine,
  imponendo la tavolozza da `config.xml`: nessun elemento sotto soglia in nessuna.
  Il testo sulla scheda resta fra 10,9 e 11,5:1.
- Tema scuro rimisurato su dieci pagine dopo l'aggiunta del menu: pulito.
- Misurato anche il menu **aperto**, che l'audit normalmente salta perche chiuso.
- `prova-web.ps1` verifica che ogni tavolozza sia nota a **PHP, CSS e JavaScript**
  insieme, che il menu esponga tutte le voci, che il predefinito arrivi dal server
  e che un valore non ammesso venga ricondotto.

## [0.6.3] — 2026-08-05

Tema chiaro invertito: pagina bianca, schede piu scure.

### Cambiato
- **Il tema chiaro e ora l'inverso della convenzione**: pagina **bianca** e schede
  **piu scure**. Il bianco fa da margine, la scheda da tavolo di lavoro. I due temi
  hanno percio logiche opposte, e la cosa e deliberata: in tema scuro la superficie
  di lavoro e piu chiara della pagina, in tema chiaro e piu scura. Entrambe dicono
  «qui si lavora».
- **Nei form i campi ora si staccano dal box**: restano bianchi su una scheda che
  non lo e piu (1,34:1). Prima erano bianco su bianco e si distinguevano solo per
  il bordo. E un guadagno che non era stato cercato.
- Il tema **scuro non cambia**: i suoi valori restano quelli approvati.
- **Barra di navigazione e piede** non usano piu l'utility `bg-body-tertiary`: con
  la pagina bianca quel grigio (`#f8f9fa`) le rendeva quasi invisibili. Il loro
  fondo segue la tavolozza e inquadra la pagina in entrambi i temi.

### Aggiunto
- **Quattro tavolozze per il tema chiaro**, selezionabili con l'attributo
  `data-catageo-tavolozza`: azzurra (predefinita, stessa famiglia dell'accento e
  del tema scuro), neutra, sabbia (il colore della roccia e delle carte
  topografiche), verde. In tutte il testo resta fra 10,9 e 11,5:1, quindi la scelta
  e di gusto e non di leggibilita.
- `docs/prove/interfaccia/tavolozze.html`: le quattro tavolozze affiancate con il
  **CSS reale** dell'applicativo, non una simulazione. Si apre direttamente dal
  browser, senza PHP.

### Corretto
- **I collegamenti nelle tabelle dell'elenco erano a 3,35:1** sulla scheda non piu
  bianca. La prima correzione non aveva funzionato perche Bootstrap 5.3 compone il
  colore dei link da `--bs-link-color-rgb` e non da `--bs-link-color`: impostare
  quest'ultima non produce alcun effetto.
- Pulsanti in contorno ritarati di nuovo: stanno sia sulla pagina sia dentro le
  schede, che ora hanno fondi diversi, e il valore deve bastare per il piu scuro
  dei due.
- Testo terziario alzato ancora: sulla scheda ora piu scura era ricaduto a 4,33:1.

### Verificato
- **Dieci pagine, entrambi i temi, nessun elemento sotto soglia WCAG AA.**
- Trovata la causa vera dei falsi positivi che avevano inquinato le passate
  precedenti: **non basta scrivere la preferenza in `localStorage` e ricaricare**,
  perche il documento arriva dal server con il tema di `config.xml` e poi
  `catageo.js` applica la preferenza locale, cioe cambia il tema **dopo** il
  render. E cosi che una passata in tema scuro ha segnalato sette problemi
  inesistenti per pagina. Il metodo definitivo impone il tema in `config.xml` e
  rimuove la preferenza locale, cosi il documento nasce col tema giusto e nessuno
  lo cambia piu. Terza volta che questa trappola produce falsi positivi: ogni volta
  il segnale e stato un valore assurdamente basso su elementi che a occhio si
  leggono benissimo.
- `prova-web.ps1` verifica che navbar e piede non tornino a `bg-body-tertiary`.

## [0.6.2] — 2026-08-05

Leggibilita, secondo passo: stacca anche il box, non solo il titolo.

### Cambiato
- **Separazione fra pagina e scheda quasi raddoppiata** rispetto all'origine:
  da 1,20 a **1,70** in tema scuro e da 1,13 a **1,45** in tema chiaro. Il box
  ora si vede come oggetto, non solo per la sua intestazione.
- Il rapporto fra scheda e intestazione e stato **tenuto fermo** a 1,42 e 1,26:
  era gia approvato, e alzare tutto insieme avrebbe fatto perdere la gerarchia
  fra i due livelli. Alzando il fondo delle schede e stato necessario ricalibrare
  l'opacita della tinta dell'intestazione per mantenere lo stesso rapporto.
- **Bordo e ombra piu marcati**: sono le due leve che aumentano il colpo d'occhio
  senza toccare il contrasto del testo, quindi si e agito prima su quelle e solo
  dopo sulla luminanza.

### Aggiunto
- **Il box su cui si sta lavorando si accende**: nel form di censimento le sezioni
  sono nove, e sapere in quale ci si trova senza dedurlo dalla posizione del
  cursore risparmia un salto di attenzione a ogni campo. Bordo nel colore
  d'accento e alone, solo dentro un form: in consultazione ogni clic su un
  collegamento farebbe lampeggiare un box senza significare nulla. L'evidenza non
  e sul fondo, perche cambiare il fondo sotto le dita mentre si scrive sposta
  l'attenzione invece di orientarla.

### Corretto
- Ritarati i colori che il fondo piu chiaro delle schede aveva riportato sotto
  soglia: asterisco dei campi obbligatori (4,48), pulsanti in contorno (4,19 e
  4,43), linguette delle sezioni (4,43). Piu il pulsante rosso delle operazioni
  distruttive, a **2,50**, che al primo giro non era mai stato misurato su un
  fondo cosi chiaro.
- I pulsanti che stanno **fuori** da una scheda hanno come riferimento il grigio
  della pagina e non il bianco del box: vanno piu scuri di quanto sembri.

### Verificato
- Nove pagine, entrambi i temi, **nessun elemento sotto soglia WCAG AA**.
- Il metodo di misura e stato cambiato perche quello precedente non era
  affidabile: scrivere `data-bs-theme` e misurare subito dopo restituisce i colori
  del tema precedente per il testo e quelli nuovi per i fondi, e aspettare un giro
  di eventi non basta. Nella stessa sessione la stessa tecnica ha dato risultati
  corretti su una pagina e falsi positivi da 1,03:1 su un'altra. Ora la preferenza
  si scrive in `localStorage` e le pagine si caricano in un iframe: ogni misura e
  su un primo render vero. La trappola e documentata: ha prodotto falsi positivi
  in due passate distinte e in entrambe stava per far correggere problemi
  inesistenti.

## [0.6.1] — 2026-08-05

Leggibilita: i box delle schede si vedono.

### Cambiato
- **Superfici a contrasto su tre livelli**: fondo della pagina, box della scheda,
  intestazione del box. Prima un box si riconosceva solo dall'intestazione e il
  contenuto sembrava scritto sulla pagina: in una scheda divisa in dieci sezioni
  non si capiva dove finiva una e iniziava l'altra. Separazione fra pagina e
  scheda da 1,20 a **1,43** in tema scuro, da 1,13 a **1,24** in tema chiaro.
- Nel tema chiaro l'intestazione si scurisce, nel tema scuro si schiarisce, in
  entrambi i casi con una tinta blu. Tingere senza cambiare la luminanza — un blu
  chiaro su bianco — darebbe una distinzione che sparisce per chi non percepisce
  bene quella tinta e che in stampa non c'e affatto.
- Il colore delle intestazioni sta in variabili CSS in un punto solo, non piu
  disseminato come utility `bg-transparent` in 37 punti dei template. Le
  intestazioni a cui una pagina da un colore proprio (un avviso, per esempio)
  vengono **escluse** e non sovrascritte: quel colore sta dicendo qualcosa.

### Corretto
- **Un pulsante dentro l'intestazione di una scheda scendeva a 1,94:1**, cioe
  illeggibile. E il caso peggiorato dalle intestazioni colorate, ed e stato il
  motivo per cui la verifica ha smesso di fidarsi dell'occhio.
- Componenti che usavano il grigio fisso `#6c757d` di Bootstrap, che non si
  adatta al tema: pulsanti in contorno, `link-secondary` del footer e
  intestazione del menu utente stavano fra 2,8 e 3,3:1 in tema scuro. Difetti
  **preesistenti**, emersi dalla misura.
- Il testo terziario dei «—» e dei «non dichiarata» era al 50% di opacita:
  informazione, non decorazione, e non si leggeva.
- L'asterisco dei campi obbligatori era rosso puro: 2,86:1 su fondo scuro.
- Il riquadro dell'installer era diventato indistinguibile dal fondo (1,03:1),
  perche il body aveva `bg-body-tertiary`, finito a un passo dal nuovo colore
  delle schede.
- In stampa le superfici tornano bianche: su carta un'intestazione colorata e
  toner speso, e su una stampante in bianco e nero e testo su grigio.

### Verificato
- `docs/prove/interfaccia/README.md`: rapporti di contrasto misurati sui colori
  calcolati dal browser, trasparenze sovrapposte comprese, su dieci pagine e in
  entrambi i temi. **Nessun elemento sotto soglia WCAG AA.**
- La prima passata della verifica ha prodotto falsi positivi da 1,03:1 perche
  cambiava tema e misurava nello stesso istante, leggendo i colori del tema
  precedente contro i fondi nuovi. La trappola e annotata: stava per far
  correggere problemi inventati.
- `prova-web.ps1` verifica ora la premessa strutturale, che invece e controllabile
  senza browser: nessun `card-header` reso trasparente, variabili definite nei due
  temi, superfici neutralizzate in stampa.

## [0.6.0] — 2026-08-05

Fase 4: gli ipogei sul territorio.

### Aggiunto
- **Mappa generale** (`?p=mappa`) con Leaflet 1.9.4 e OpenStreetMap, entrambi
  serviti dal server dell'installazione: nessuna CDN, nessuna chiave API.
- **Raggruppamento dei marker** con una griglia in coordinate schermo, celle di
  64 px, marker singoli oltre lo zoom 17. Scritto in casa invece di aggiungere
  `Leaflet.markercluster`: sono un centinaio di righe contro una dipendenza in
  piu da aggiornare, e il comportamento resta identico quando in fase 4b
  arrivera il secondo provider.
- **Filtri immediati** per testo, natura, catalogo e stato d'accesso, applicati
  ai dati gia scaricati: nessun ricaricamento mentre si esplora la mappa.
- **Legenda**: colore per natura della cavita, tratteggio per ingresso non
  praticabile, cerchio numerato per i gruppi. Senza legenda i colori sono
  decorazione.
- **Lettura delle coordinate sotto il puntatore**, in gradi decimali e UTM. Il
  fuso si ricava dalla longitudine e non dalla configurazione, cosi chi rileva a
  cavallo di due fusi legge sempre quello giusto. Verificata al metro contro il
  motore PHP su tre fusi e tre fasce.
- **Mappa nella scheda dell'ipogeo**, con la rotella del mouse disattivata
  perche altrimenti scorrere la pagina diventerebbe impossibile. Le schede senza
  coordinate non scaricano Leaflet per non mostrare nulla.
- **`?p=geojson`**: FeatureCollection standard degli ipogei visibili, con gli
  stessi filtri dell'elenco. Serve la mappa e vale come esportazione.
- `Mappa`: configurazione cartografica in un solo punto, con sfondi e layer WMS
  dichiarati in `config.xml`. Gli URL ammessi sono solo `http`/`https` e un WMS
  senza l'attributo `layers` viene scartato invece di disegnare riquadri vuoti.
  Attributi documentati in `ANALISI.md` 7.2.2.
- `Visibilita`: le regole di riservatezza in una classe sola, usata da elenco,
  scheda e mappa. Una regola applicata in due punti su tre e una fuga di dati.
- **Content-Security-Policy** attiva, con `script-src 'self'` e senza
  `unsafe-inline`: i dati per il JavaScript passano da blocchi
  `<script type="application/json">`, che non sono codice. Le origini dei tile
  server si ricavano dai layer configurati, quindi aggiungere un servizio in
  `config.xml` basta e la policy si adegua da se.
- `window.CATAGEO.mappa`: punto d'innesto dichiarato per i rilievi KML della
  fase 6 e i punti dei diari della fase 7, che aggiungeranno layer a questa
  mappa invece di crearne una seconda.
- `docs/prove/mappa/README.md`: verifiche fatte nel browser, con gli esiti e
  l'elenco esplicito di cio che **non** coprono.

### Riservatezza
- Le coordinate offuscate escono dal server **gia arrotondate**: un filtro fatto
  nel browser non e un filtro, perche i dati esatti sono comunque partiti.
- Le schede riservate non entrano nella risposta GeoJSON di chi non puo vederle.
- Sulla scheda, coordinate offuscate significano un **cerchio d'area** e non un
  punto: un puntino sarebbe una bugia precisa. Lo zoom resta limitato a 12.
- L'arrotondamento e deterministico: ricaricare la pagina mostra sempre la stessa
  posizione approssimata. Con un disturbo casuale, piu letture permetterebbero di
  ricavare il centro della distribuzione, cioe la posizione vera.

### Corretto
- **Un CSV salvato da Excel come «CSV UTF-8» rendeva illeggibile la prima
  colonna dell'indice.** Excel scrive il BOM prima dell'apice di apertura, quindi
  `fgetcsv` non riconosceva il primo campo come delimitato e la colonna si
  chiamava `"catalogo"`, apici compresi: ogni lettura per nome falliva. Ora il
  BOM viene saltato prima della lettura, non ripulito dopo. Conta perche
  l'archivio e fatto per essere aperto e corretto a mano, ed Excel e il modo piu
  probabile in cui accadra.
- `CATAGEO_VERSIONE` era rimasta a 0.1.0 dalla prima fase: il footer dichiarava
  una versione che non era quella installata.

### Rinviato con motivazione
- L'astrazione `CatageoMappa` prevista in analisi 7.1.1 **non e stata scritta**:
  un'interfaccia con una sola implementazione si scopre sbagliata solo quando
  arriva la seconda. Il contratto che conta e gia indipendente dal provider e sta
  fra PHP e browser. Da confermare al committente.
- Aggiunta di layer WMS dall'interfaccia, cursore di opacita, cerchio di ricerca
  per raggio, marker distinguibili per catalogo: elencati in analisi 7.2.1 con la
  fase in cui arrivano.

## [0.5.0] — 2026-08-05

Coordinate: conversione reale fra sistemi di riferimento.

### Aggiunto
- **Conversione fra sistemi di riferimento** (D14). `SistemiRiferimento` e un
  vocabolario in cui ogni sistema e descritto da una stringa in stile proj4:
  la stessa che riceve proj4js nel browser e che alimenta il motore in PHP.
  Ampliabile incollando una definizione da epsg.io, senza toccare il codice.
- `Proiezione`: trasversa di Mercatore su ellissoide qualsiasi e trasformazione
  di datum a sette parametri di Helmert, nella convenzione di PROJ.
- **Gauss-Boaga e ED50 ora si convertono davvero**, con l'incertezza dichiarata
  accanto al nome del sistema e ripetuta come avviso al salvataggio. Prima
  venivano solo conservati e i gradi WGS84 andavano inseriti a mano.
- **Anteprima dal vivo** durante l'inserimento: mentre si digita, il punto
  compare in gradi decimali, sessagesimali e UTM. E li che un fuso sbagliato o
  un est e nord invertiti si vedono, invece di scoprirli dopo il salvataggio.
- **Notazione preferita per catalogo**: il catalogo dichiara in che sistema e
  abituato a scrivere le posizioni, e le sue schede la mostrano per prima.
- Il campo del fuso e sparito dal form: e implicito nel sistema scelto, e
  averlo separato rendeva possibile una contraddizione da intercettare.
- Oltre quattro gradi dal meridiano centrale la conversione viene rifiutata con
  un messaggio che suggerisce il fuso corretto: li la serie perde accuratezza, e
  restituire un numero sbagliato sarebbe peggio.

### Verificato
- Verifica incrociata con proj4js su 52 punti e nove sistemi, in
  `docs/prove/coordinate`: concordanza entro **2,56 mm**, Gauss-Boaga ed ED50
  compresi, nessun caso fuori tolleranza. Serve perche un errore nel verso
  delle rotazioni di Helmert lascia coerente il giro completo e intanto sposta
  la posizione di decine di metri: solo un'implementazione indipendente lo vede.
- 84 prove unitarie sul dominio delle coordinate e 38 dall'interfaccia; tutte le
  suite precedenti rieseguite.

### Aggiunto in precedenza
- **Sistemi di riferimento e formati delle coordinate** (D13). L'archivio
  conserva sempre gradi decimali WGS84 come forma canonica, ma accanto registra
  il dato **come e stato rilevato**: sistema, formato e valore originali. Un
  catasto che ha misurato in UTM ha misurato in UTM, e riscrivere solo la
  conversione perderebbe cosa fu letto sullo strumento.
- Inserimento in **gradi decimali, gradi sessagesimali, gradi e minuti decimali
  e UTM**, con selettore di formato e di sistema nel form. La conversione
  UTM/geografiche e esatta perche entrambe stanno sullo stesso ellissoide WGS84:
  e una proiezione, non un cambio di datum.
- I sistemi con **datum diverso** (Gauss-Boaga/Roma40, UTM ED50) si possono
  dichiarare e vengono conservati, ma **non** vengono convertiti: la
  trasformazione richiede parametri locali e sbaglierebbe di decine di metri.
  In quel caso l'applicativo chiede anche i gradi WGS84, invece di produrre una
  posizione plausibile ma sbagliata.
- La scheda mostra la stessa posizione nelle notazioni che si usano in campagna:
  gradi decimali, sessagesimali e UTM WGS84 ricalcolato, piu il valore originale
  se il rilievo era stato fatto in un altro sistema.
- Due controlli sugli errori di digitazione piu frequenti: fuso che contraddice
  il codice EPSG del sistema (rifiutato) ed est/nord invertiti (segnalati).

### Corretto
- **Appartenenze ai gruppi: lo stesso gruppo poteva comparire una volta sola.**
  Le appartenenze venivano deduplicate per identificativo di gruppo, quindi il
  caso reale di chi lascia un gruppo e vi rientra dopo qualche anni era
  impossibile da registrare. Ora lo stesso gruppo puo ricorrere con periodi
  distinti; si scartano soltanto i duplicati esatti (stesso gruppo, stessi due
  anni), che sono un errore di inserimento. Le appartenenze vengono ordinate
  cronologicamente e quelle in corso sono segnalate in elenco.
- Aggiunto il controllo di **sovrapposizione fra periodi dello stesso gruppo**,
  che sarebbe contraddittoria, ammettendo il confine condiviso: uscire ed essere
  riammessi nello stesso anno e plausibile. I periodi di gruppi diversi possono
  sovrapporsi liberamente, perche l'iscrizione simultanea a piu gruppi e la norma.
- `Esploratori::gruppoAllaData()` restituiva un solo gruppo, quindi con due
  iscrizioni contemporanee ne attribuiva arbitrariamente una. Sostituita da
  `gruppiAllaData()`, che restituisce l'elenco, piu `gruppiAttuali()`.

### Rifinito dopo la prova sul campo
- L'etichetta del formato diceva «Gradi decimali, o centesimali»: i gradi
  centesimali sono i gon, cioe un'altra cosa. Ora dice «Gradi decimali» con un
  esempio numerico accanto.
- Le coordinate UTM non hanno piu il separatore delle migliaia
  (`33T 295964 4678705`, non `33T 295.964 4.678.705`): una coordinata si legge,
  si trascrive e si ridigita su un GPS, e i punti fra le cifre sono un ostacolo.

### Da fare
- Schemi XSD per le anagrafiche introdotte nella 0.2.0

## [0.4.0] — 2026-08-04

Fase 3: il catasto comincia a contenere ipogei.

### Aggiunto
- `Sezioni`: sigle, nomi delle sottocartelle e nomi normativi dei file di
  risorsa in un unico punto, cosi lo standard di nomenclatura non puo divergere
  fra le parti che lo applicano.
- `Ipogeo`: censimento con assegnazione del codice dalla serie del catalogo o
  inserito a mano, creazione dell'albero delle undici sottocartelle, lettura e
  scrittura della scheda `[codice] - Dati.xml` con tutte le sezioni sempre
  presenti, storicizzazione automatica con rotazione, rinomina della cartella al
  cambio del nome, **cambio di codice** che rinomina sottocartelle, file di
  risorsa e miniature conservando il codice precedente in scheda, e
  **cancellazione conservativa** che sposta l'albero in `dati/_eliminati` invece
  di rimuoverlo.
- `IndiceIpogei`: `dati/_indice/ipogei.csv` con aggiornamento per singolo ipogeo
  e ricostruzione integrale dalle sole schede. Percorsi scritti relativi, non
  assoluti, cosi un archivio spostato resta valido.
- `schemi/ipogeo.xsd`: validazione della scheda, applicata a ogni salvataggio.
- **Pagina degli ipogei**: elenco paginato con ricerca su codice, nome, comune e
  localita e filtro per catalogo; scheda in consultazione con tab per ogni
  sezione e barra degli avvisi in testa (bozza, accesso chiuso, autorizzazione
  necessaria, pericoli, ubicazione riservata); form di censimento e modifica.
- **Riservatezza applicata in lettura**: le schede `riservata` non compaiono in
  elenco e non sono apribili dal livello USR; con `coordinate_offuscate` la
  posizione viene arrotondata al raggio configurato, in modo deterministico
  perche ricaricare la pagina non deve permettere di triangolare.
- Cambio di codice e rimozione conservativa disponibili dall'interfaccia, con
  conferma esplicita e spiegazione di cosa comportano.

### Verificato

69 verifiche unitarie sul nucleo, su archivio temporaneo e mai su quello reale,
piu 55 verifiche end-to-end dall'interfaccia. Le suite delle fasi 1, 2 e 2b
rieseguite per regressione. Nessun fallimento, nessun errore applicativo.

Lo schema della scheda e verificato in due sensi: la scheda generata deve
risultare valida, e una controprova con un valore fuori enumerazione deve
essere rifiutata. Senza la seconda, il primo controllo non dimostrerebbe che lo
schema discrimini qualcosa.

## [0.3.0] — 2026-08-04

Fase 2b: cataloghi multipli e codifica a serie, prerequisito del censimento.

### Aggiunto

- `Cataloghi`: scoperta dei cataloghi scandendo `dati/cataloghi/*/catalogo.xml`,
  senza registro centrale che possa disallinearsi dai dati. Creazione con
  cartella normativa `[sigla] - [nome]`, modifica con rinomina della cartella,
  cancellazione consentita solo su catalogo vuoto e con la cartella priva di
  file estranei. Catalogo attivo per sessione.
- Serie di codifica per catalogo, con **contatore indipendente** e ordine
  significativo: si aggiungono, si modificano e si riordinano con i pulsanti su
  e giu, perche vince la prima serie i cui criteri combaciano. Cancellazione
  rifiutata se la serie ha gia numerato codici o se e l'unica del catalogo.
- `CodiceCatastale`: risoluzione della serie per criteri (natura, tipologia,
  sottotipologia, stato, regione, provincia; piu valori separati da barra
  verticale), composizione con **padding a soglia minima e nessun tetto** al
  progressivo, scomposizione di un codice dando la precedenza al prefisso piu
  lungo, verifica del codice inserito a mano con allineamento in avanti del
  contatore per importare catasti esistenti.
- **Anteprima del codice** nella gestione delle serie: si compilano i dati come
  li avrebbe un ipogeo e si vede quale serie vince e quale codice ne uscirebbe,
  senza toccare nessun contatore. Accanto alla configurazione della serie
  compaiono gli esempi di numerazione, cosi il comportamento del padding si
  vede invece di doverlo dedurre.
- `IndiceCodici`: `dati/_indice/codici.csv`, che registra ogni codice mai
  assegnato e lo risolve verso quello corrente. Una catena di due migrazioni
  resta risolvibile perche anche le righe storiche vengono ripuntate.
- `schemi/catalogo.xsd`, con unicita del prefisso fra le serie. Il contatore e
  dichiarato come sequenza di cifre e non come intero, coerentemente con
  l'assenza di tetto.

### Verificato

47 verifiche unitarie sulla logica di codifica, fra cui l'intera tabella del
padding del documento di analisi (5.3) e la composizione a `PHP_INT_MAX` senza
perdita di precisione; 40 verifiche end-to-end sulla fase 2b; le suite delle
fasi 1 e 2 rieseguite per regressione. Nessun fallimento, nessun errore
applicativo nel log.

### Nota

Due fallimenti iniziali dei test erano aspettative sbagliate, non difetti: la
serie creata insieme al catalogo nasce **senza criteri** e fa quindi da caso
generale, intercettando tutto prima delle serie successive. E il comportamento
voluto, ed e la ragione per cui esiste il riordino delle serie.

## [0.2.0] — 2026-08-04

Fase 2 del piano di sviluppo: le anagrafiche a cui le schede degli ipogei
faranno riferimento.

### Aggiunto

- `Anagrafica`: base comune alle anagrafiche a elenco piatto (file, lock,
  scrittura atomica, identificativi, integrita referenziale in cancellazione),
  con l'identificativo configurabile fra progressivo generato e codice parlante.
- `Gruppi`: gruppi speleologici, con sigla univoca, affiliazioni e validazione
  dell'anno di fondazione.
- `Esploratori`: persone censite con **appartenenza storicizzata** ai gruppi
  (anno iniziale e finale), cosi che un diario del 1998 resti attribuito al
  gruppo di allora. Funzione `gruppoAllaData()` per risolvere l'attribuzione.
- `Tipologie`: tassonomia su tre livelli (natura, tipologia, sottotipologia) con
  vincoli di gerarchia e percorso leggibile.
- `Grandezze`: grandezze misurabili con unita, intervallo di plausibilita e
  decimali, piu `verificaPlausibilita()` per marcare le letture fuori scala.
- `Periodi`: cronologia con estremi in anni (negativi per le date a.C.),
  ordinamento cronologico e `nellIntervallo()` per la futura ricerca temporale.
- `VocabolariPredefiniti`: contenuto iniziale dei tre vocabolari, unica fonte
  usata sia dall'installazione sia dalla creazione pigra.
- Pagine: indice delle anagrafiche, gestione gruppi, gestione esploratori e
  pagina unica dei tre vocabolari.
- Un file per ogni classe di eccezione.

### Corretto

- `AnagraficaEccezione` era dichiarata dentro `Anagrafica.php`. `Tipologie` e
  `Grandezze` non estendono quella classe, quindi l'autoload non caricava mai
  quel file: ogni validazione fallita moriva con "class not found" invece di
  mostrare il messaggio all'utente. Tutte le classi di eccezione sono state
  estratte in file propri, come prevede la convenzione "una classe per file".
- `Anagrafica::elenco()` non creava il file mancante, quindi i vocabolari con
  contenuto predefinito restavano vuoti fino alla prima scrittura. Ora anche la
  lettura inizializza l'anagrafica, con ripiego sull'elenco vuoto se l'archivio
  non e scrivibile.
- Ripristinato `installa.php`, rimosso per errore dal repository: la cartella di
  progetto e collegata al webroot da una junction, quindi eliminarlo dal server
  lo eliminava anche dal codice. Sul server l'installer resta comunque
  disabilitato dal marcatore `installato.txt`, che e il meccanismo previsto.

### Verificato

48 verifiche automatiche sulla fase 2 e le 60 della fase 1, tutte superate:
creazione pigra dei vocabolari, unicita delle sigle, omonimia degli esploratori,
appartenenze con anni incoerenti, vincoli di gerarchia della tassonomia,
intervalli di plausibilita, conversione delle date a.C., rifiuto della
cancellazione di voci referenziate, permessi dei tre livelli.

## [0.1.0] — 2026-08-04

Prima versione funzionante: fasi 0 e 1 del piano di sviluppo. Non è ancora
possibile censire ipogei; l'infrastruttura su cui poggerà il censimento è
completa e verificata.

### Aggiunto

**Struttura e configurazione (fase 0)**
- Struttura delle cartelle dell'applicativo e dell'archivio.
- `config.xml.dist`: template di configurazione con catasto, cataloghi, percorsi,
  mappa, upload, sicurezza e parametri di sistema.
- `.htaccess` di root con irrobustimento, e generazione automatica del
  `.htaccess` di protezione dentro l'archivio.
- Bootstrap 5.3.8 e Bootstrap Icons 1.13.1 self-hosted in `assets/vendor`,
  senza alcuna CDN.
- Pagina di diagnostica dell'ambiente: versione di PHP, ampiezza degli interi,
  estensioni richieste e opzionali, limiti di upload, disponibilità di chiamate
  HTTP in uscita, stato e scrivibilità dell'archivio, protezione dei dati,
  spazio residuo, presenza delle librerie locali.

**Core (fase 1)**
- `Xml`: caricamento sicuro (nessuna entità esterna, nessun accesso in rete),
  validazione XSD, scrittura atomica con `rename()`, lock esclusivo per le
  sequenze leggi-modifica-scrivi, gestione dei testi in `CDATA`.
- `Csv`: lettura in streaming con interruzione anticipata, scrittura atomica,
  append con lock per le serie di misure, gestione del BOM.
- `Config`: accesso ai parametri con percorsi puntati e validazione delle chiavi.
- `Percorsi`: risoluzione dei percorsi dell'archivio e barriera contro il path
  traversal basata su `realpath()`.
- `Testo`: escaping per l'output, normalizzazione per la ricerca con
  traslitterazione, sanitizzazione dei nomi di file secondo lo standard di
  nomenclatura, estratti calcolati a runtime.
- `Log`: registrazione di accessi, modifiche ed errori su CSV nell'archivio.
- `Utenti`: gestione di `utenti.xml` con hash BCRYPT a costo 12, rehash
  automatico, contatori dei tentativi falliti, blocco temporizzato e tutela
  dell'ultimo amministratore attivo.
- `Auth`: sessione con cookie `HttpOnly`/`SameSite=Strict`, scadenza per
  inattività, token CSRF su ogni POST, matrice dei permessi per livello
  ADM/OPE/USR in un unico punto.
- Front controller `index.php` con whitelist delle pagine: il parametro `?p=`
  non viene mai usato per costruire un percorso.
- Interfaccia Bootstrap con navbar, tema chiaro/scuro, messaggi e stampa.
- Gestione utenti completa e installazione guidata `installa.php`, che verifica
  l'ambiente, crea l'archivio, il primo amministratore e un catalogo
  dimostrativo, poi si autodisabilita.
- `schemi/utenti.xsd`: validazione di `utenti.xml`, con unicità di
  identificativi e username.
- Doppia barriera contro l'accesso diretto via HTTP alla cartella `app/`:
  `app/.htaccess` per Apache e guardia `defined('CATAGEO_ROOT') or exit` in
  testa ai file di pagina e di vista, che vale anche dove `.htaccess` non viene
  letto (nginx, `AllowOverride None`).

### Note tecniche

Tre correzioni emerse dalle prove sull'ambiente reale, annotate perché
riguardano insidie non evidenti:

- Nei commenti XML non possono comparire due trattini consecutivi. I separatori
  nelle testate di `config.xml.dist` e degli XSD usano punti: con la riga di
  trattini i file non erano XML validi.
- Su Windows `dirname()` restituisce il separatore di sistema, quindi
  `dirname('/index.php')` vale `\`. Normalizzando solo l'ingresso e non il
  risultato, il percorso del cookie di sessione diventava `\/`, valore che i
  client scartano: la sessione non persisteva e ogni POST falliva la verifica
  CSRF.
- Alla chiusura della sessione va rigenerato l'identificativo: l'header che
  cancella il cookie, senza un `Set-Cookie` nuovo, lascia il client senza
  cookie e fa perdere i messaggi scritti dopo l'uscita.

### Verificato

Su PHP 8.2.12 a 64 bit (XAMPP, Windows), 60 verifiche automatiche su istanza
usa-e-getta: installazione, creazione dell'archivio, hash delle password,
protezione delle pagine riservate, token CSRF, permessi dei tre livelli,
blocco per tentativi falliti, contenuto dei log, caricamento delle librerie
locali senza alcuna richiesta a domini esterni.
