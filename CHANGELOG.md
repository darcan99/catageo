# Cronologia delle modifiche

Tutte le modifiche rilevanti a CATAGEO sono annotate qui, in formato
[Keep a Changelog](https://keepachangelog.com/it/1.1.0/), con versionamento
[semantico](https://semver.org/lang/it/).

## [Non rilasciato]

## [1.5.0] — 2026-08-08

### Aggiunto
- **Un set di glifi disegnati per le cavita**, quelli che Bootstrap Icons non
  ha e non puo avere: ingresso di grotta, abisso, inghiottitoio, risorgenza,
  cunicolo drenante, cisterna, acquedotto, colombario, cava ipogea, rifugio
  antiaereo, galleria, tubo lavico, concrezioni, abitato rupestre. Quattordici
  simboli in `assets/icone/catageo-icone.svg`.
- I due insiemi **convivono**: dove Bootstrap ha gia il simbolo adatto — la
  fiamma del vulcanismo, il fiocco del glaciale — si usa quello. Chi compila un
  vocabolario scrive un nome solo: se comincia per `cat-` e nostro.
- **Anteprima nell'elenco dei vocabolari**: una colonna mostra il simbolo che
  ogni voce usa DAVVERO, cioe risolto con l'ereditarieta. Mostrare l'attributo
  grezzo lascerebbe vuote quasi tutte le righe e non risponderebbe alla sola
  domanda per cui si guarda quella colonna. Le voci che ereditano sono in
  grigio.
- **Tavolozza dei glifi** nel modulo di modifica: si scelgono cliccandoli, e
  l'anteprima si aggiorna sul posto. Sono una dozzina e non esistono altrove:
  chiedere di digitarne il nome a memoria avrebbe significato che nessuno li
  usava.
- Glifo proprio anche per alcune **sottotipologie**: un abisso non e una
  grotta, una risorgenza non e un inghiottitoio, e un occhio speleologico le
  distingue da lontano.

### Note di progetto
- **Forme piene, non contorni.** Il glifo occupa una dozzina di pixel dentro la
  pastiglia: a quella misura una linea sottile sparisce. E il motivo per cui
  Bootstrap ha le varianti «-fill», e qui siamo sempre in quel caso.
- **Il vuoto si disegna con il vuoto**: un ipogeo e un'assenza dentro una massa
  di roccia, e `fill-rule="evenodd"` ritaglia la sagoma dalla massa invece di
  contornarla.
- **Lo sprite si include nella pagina.** Il riferimento a un file esterno
  funziona — provato in browser, il `<use>` si risolve e il file arriva con 200
  — ma includendolo `currentColor` eredita davvero il colore del contenitore e
  non c'e una seconda richiesta prima che i simboli compaiano. Si emette solo
  dove servono: altrove sarebbero due kilobyte di niente, e la prova lo
  verifica in entrambe le direzioni.

## [1.4.1] — 2026-08-08

### Corretto
- **«Apri la scheda» nel popup della mappa era azzurro su blu**: contrasto
  misurato **1,1:1**, cioe illeggibile. La causa non e una tinta sbagliata ma
  la specificita CSS: Leaflet colora ogni collegamento dentro la propria mappa
  con `.leaflet-container a`, che vale una classe piu un elemento, mentre
  `.btn-primary` e una classe sola. Difetto preesistente, c'era da quando il
  popup ha un pulsante. Ora e **4,5:1** in entrambi i temi, cioe il valore che
  quel pulsante ha in tutto il resto dell'applicativo.
- La correzione restituisce al pulsante il **proprio** colore
  (`var(--bs-btn-color)`) invece di inchiodarne uno nuovo, cosi vale anche per
  le varianti che non esistono ancora, e per ogni mappa: elenco, scheda,
  ricerca, rilievo.
- `prova-web.ps1` verifica che la regola ci sia. Il contrasto si vede solo nel
  browser, ma la premessa strutturale si controlla, e senza controllo chi
  togliesse quella regola riporterebbe il difetto senza accorgersene.

## [1.4.0] — 2026-08-08

### Aggiunto
- **Simboli in mappa: colore per natura, glifo per tipologia.** Il marker era
  un pallino colorato, e il colore era l'unico canale: con due nature non
  diceva nemmeno cosa fosse quel punto, e chi non distingue bene arancio e
  verde non leggeva neppure quello — era scritto fra i limiti dichiarati in
  docs/prove/interfaccia. Ora la pastiglia porta dentro il simbolo della
  tipologia: goccia per le opere idrauliche, carrello per le estrattive, scudo
  per le belliche, fiamma per le vulcaniche, e cosi via.
- **Il simbolo lo decide il vocabolario, non il codice**: e un attributo
  `icona` della voce di tassonomia, e si **eredita salendo**. Una
  sottotipologia nuova compare in mappa con il simbolo della madre senza che
  chi la crea scelga nulla; si compila solo per distinguerla dalle sorelle. Un
  set cablato sarebbe andato bene per il primo catasto e sbagliato per il
  secondo.
- Campo **Icona in mappa** nei vocabolari, con anteprima del glifo.
- Le **cavita miste** hanno finalmente un colore proprio: cadevano nel grigio
  «natura non indicata».
- La **legenda** mostra il segno con la stessa forma del marker e dichiara la
  regola: senza quella riga il colore si legge come «tutto» e il glifo passa
  per decorazione.

### Note di progetto
- **Nessun file nuovo**: si usa il font di Bootstrap Icons, gia self-hosted.
  Niente immagini da scaricare, niente richieste in piu, simbolo nitido a
  qualunque ingrandimento — e il vincolo «nessuna CDN» resta intatto.
- **Pastiglia e non goccia.** Il simbolo e ancorato dal proprio centro sulla
  coordinata: una goccia con la punta in basso direbbe «la posizione e sotto
  di me». Su un catasto la posizione e il dato. Verificato nel browser che lo
  scarto fra centro del simbolo e punto sia zero.
- Il nome del glifo finisce dentro un attributo `class`: si ripulisce **sia in
  scrittura sia in lettura**. La prima versione lo faceva solo in scrittura, e
  la prova ha mostrato che un valore scritto a mano nell'XML arrivava intatto
  fino alla pagina — i vocabolari sono file che il progetto incoraggia a
  correggere a mano.

## [1.3.2] — 2026-08-08

### Modificato
- **I riferimenti di sezione hanno un colore proprio** (`OS001`, `BB001`,
  `AL003`). In una tabella stanno in una colonna loro e si distinguono da
  soli; in un elenco stanno davanti al titolo, sulla stessa riga, e in
  monospaziato dello stesso colore si leggevano attaccati a cio che segue:
  «BB001 Rossi M.», «OS001 Rhinolophus». Il colore e quello dei collegamenti,
  gia scurito per essere leggibile sul fondo di una scheda: il primario di
  Bootstrap vale 3,35:1 li sopra, sotto la soglia. E lo stesso trattamento che
  il codice catastale ha nell'intestazione, cosi l'interfaccia dice «questo e
  un codice» sempre nello stesso modo. Misurato fra 4,69:1 e 7,00:1 sul fondo
  nelle cinque combinazioni di tema e tavolozza.
- Il riferimento prende una classe propria (`.catageo-riferimento`) invece di
  essere colorato caso per caso: e lo stesso oggetto ovunque compaia, e
  chiamarlo per nome in un punto solo evita che fra sei mesi meta dei
  riferimenti sia colorata e meta no.

### Documentazione
- **Tolta la fase 11** dal piano di sviluppo: l'acquisizione di dati da fonti
  esterne si tratta separatamente. Resta scritto in §15 che i dati dei catasti
  regionali non sono automaticamente riutilizzabili e a cosa serve
  `<licenzaDati>`, perche riguarda l'applicativo.
- **Corretta la tabella di stato della cartografia** (§7.2.1), ferma alla fase
  4: dava per «da fare» i layer geologici preconfigurati (fatti nella 6b) e i
  tracciati KML (fatti nella 6, ma solo sulla mappa della scheda). Le righe
  ancora aperte sono state riscritte dicendo cosa c'e e cosa manca davvero.

## [1.3.1] — 2026-08-08

### Corretto
- **L'etichetta «protetta» usciva dal proprio riquadro** e finiva sopra il
  testo accanto: nella scheda si leggeva «Rinolofo maggioprotetta». La causa
  non era l'etichetta ma la riga che la contiene: una voce di elenco ha un
  rientro sporgente (`text-indent` negativo), `text-indent` si eredita e agisce
  sulla prima riga di ogni contenitore di blocco, e un `.badge` e inline-block,
  quindi lo e. Riguardava anche il livello dei rischi geologici. L'azzeramento
  c'era gia ma per un elenco di classi che non comprendeva i badge: ora vale
  per tutti i discendenti, cosi un riquadro nuovo dentro una voce non nasce
  gia rotto.
- `prova-web.ps1` verifica che **ogni** rientro sporgente del CSS abbia accanto
  l'azzeramento sui discendenti.

## [1.3.0] — 2026-08-07

### Aggiunto
- **I PDF degli allegati si leggono nella finestra**, come le foto e i video,
  invece di scaricarsi. Un allegato che si consulta di continuo — la scheda
  catastale di origine, la relazione di scavo — non deve costringere a un giro
  nella cartella dei download a ogni sguardo. Restano il pulsante per scaricare
  l'originale e quello per lo schermo intero.

### Note di progetto
- **Un `<iframe>` e non un `<object>`**: la CSP dell'applicativo ha
  `object-src 'none'`, quindi un `<object>` verrebbe bloccato e la finestra
  resterebbe vuota senza spiegare perche. Si usa il lettore PDF del browser:
  rifarlo qui vorrebbe dire portarsi dentro una libreria, contro il vincolo
  «zero dipendenze».
- **L'iframe si ferma alla chiusura** come gia faceva il video: un PDF di venti
  megabyte continuerebbe ad arrivare anche a finestra chiusa.
- **Quali file si aprono nella finestra si decide sul FILE, non sulla sezione.**
  L'elenco delle estensioni deve coincidere con i MIME che scarica.php consegna
  in linea: sono due liste in due file, e quando divergono la finestra si apre
  vuota mentre il file si scarica di nascosto. La prova legge entrambe dai
  sorgenti e verifica che si corrispondano.

### Corretto
- **Le foto in formato TIFF aprivano una finestra rotta.** La configurazione
  ammette il TIFF fra le foto, ma nessun browser lo disegna e scarica.php non
  lo consegna in linea: la finestra si apriva su un'immagine spezzata mentre il
  file si scaricava di nascosto. Difetto preesistente, emerso decidendo sul
  file invece che sulla sezione; vale anche per MOV e AVI fra i video. Ora quei
  collegamenti dichiarano che scaricano, invece di promettere una vista che non
  possono dare.

## [1.2.1] — 2026-08-07

### Corretto
- **La riga di intestazione delle tabelle aveva lo stesso fondo delle righe
  dati.** Su un elenco lungo si perdeva subito quale colonna si stava
  leggendo. Ora ha una tinta propria per tavolozza, misurata contro il fondo
  della scheda e non contro il bianco della pagina: 1,26 bastava per la fascia
  in cima a un box, non per una riga in mezzo ai dati. Portata a 1,7 — la
  stessa separazione che il tema scuro usa fra pagina e scheda — con il testo
  dell'intestazione fra 6,6:1 e 9,1:1 in tutte e cinque le combinazioni di
  tema e tavolozza.
- **L'intestazione che resta in cima allo scorrimento** (le letture di una
  serie di misure) non aveva fondo: le righe le passavano sotto e si
  leggevano in trasparenza. Ora e opaca, con lo stesso colore ottenuto
  sovrapponendo la tinta al fondo della scheda.

## [1.2.0] — 2026-08-07

Fasi 4b e 6b: provider cartografico Google Maps, sezione geologica, geoportali
dell'Italia centrale e compilazione assistita dalla cartografia.

### Aggiunto
- **Astrazione del provider cartografico** (`catageo-mappa-api.js`), con due
  implementazioni interscambiabili: Leaflet e Google Maps. In fase 4 non era
  stata scritta, e la ragione era che un'interfaccia con una sola
  implementazione non ha modo di essere sbagliata nel punto giusto. Il
  contratto effettivo **non coincide** con quello ipotizzato allora: mancavano
  la proiezione in coordinate schermo, il riquadro della vista e il test di
  contenimento, senza i quali ogni provider avrebbe dovuto riscrivere il
  raggruppamento dei marker.
- **Google Maps selezionabile** da `mappa.provider` in configurazione, con la
  chiave in `mappa.chiaveApi`. WMS e tile server diventano ImageMapType con
  le GetMap costruite per tile; il pannello dei layer, che Google non ha, e
  ricostruito con le stesse voci del selettore di Leaflet.
- **Sezione geologica** (`GE`), con schema, pagina, indice e ricerca:
  inquadramento litostratigrafico, genesi, assetto strutturale, morfologie,
  idrogeologia, rischi e campioni prelevati. Sette moduli che si salvano uno
  per uno, perche la geologia si compila in piu riprese — una parte in cavita,
  una davanti alla carta — e un modulo unico farebbe perdere tutto a chi
  sbaglia un campo in fondo.
- **26 layer WMS preconfigurati** per l'Italia centrale in `config.xml.dist`,
  tutti spenti: ISPRA (geologica 1:100.000, litologia, permeabilita, sinkhole,
  cave, emissioni gassose), catasto dell'Agenzia delle Entrate, aree
  archeologiche vincolate del Ministero della Cultura, piu Lazio, Abruzzo,
  Umbria e Marche.
- **Compilazione assistita della geologia dalla cartografia** (`Geoservizi`):
  interroga i layer WMS con GetFeatureInfo sul punto della cavita e **propone**
  litologia, formazione, unita geologica, eta e permeabilita, ciascuna con il
  layer da cui viene. Si accettano una per una: un campo riempito senza che
  nessuno lo abbia guardato e un dato falso che sembra vero.
- **Scelta a tre vie sulle coordinate riservate** prima di interrogare un
  servizio esterno: punto arrotondato, punto esatto, oppure nessuna richiesta.
  Ogni interrogazione finisce nel registro delle modifiche, anche quella andata
  a vuoto.
- **Sezione geologia sulla scheda da stampare**, escludibile come le altre.

### Note di progetto
- **Senza chiave si resta su OpenStreetMap.** L'API di Google senza chiave
  disegna una mappa in filigrana con un cartello di errore: peggio di nessuna
  mappa, perche sembra un guasto dell'applicativo.
- **La Content-Security-Policy si allarga solo quando Google e davvero
  attivo.** Allargarla per tutti sarebbe una riduzione di sicurezza che
  nessuno noterebbe. E la deroga documentata al vincolo «nessuna CDN» (16.1),
  e vale per la sola installazione che l'ha scelta.
- **Leaflet si carica sempre**, anche con Google attivo: e il ripiego se
  l'API non arriva, e una pagina senza mappa e peggio di centoquaranta
  kilobyte non usati. Il ripiego viene dichiarato in pagina, non subito in
  silenzio.
- **L'elenco degli script della mappa era ripetuto in cinque pagine**: ora e
  in `Mappa::scriptBrowser()`. Con l'arrivo del secondo provider sarebbero
  diventati cinque posti in cui ricordarsi la stessa condizione, e il quinto
  sarebbe rimasto indietro.
- **Il provider Google non e verificabile fino in fondo senza una chiave
  valida.** Con una chiave finta l'API si carica lo stesso e l'implementazione
  gira — costruisce la mappa, registra lo sfondo, elabora i dati, proietta le
  coordinate — ma Google non disegna la propria cornice, quindi legenda e
  lettura coordinate risultano registrate nei suoi controlli e non innestate
  nel DOM. La resa visiva resta da confermare.

- **L'arrotondamento delle coordinate e deterministico, non casuale.** Era
  stato proposto un errore randomico di 200-300 m: non regge. Un errore che
  cambia a ogni chiamata si annulla facendo la media di tre richieste, quindi
  chi volesse il punto vero dovrebbe solo chiederlo tre volte. `Visibilita::griglia()`
  restituisce sempre lo stesso punto, e a 1:100.000 mille metri non cambiano la
  formazione che si legge.
- **L'interrogazione la fa il server, non il browser.** La politica sulle
  coordinate riservate deve stare dove non si aggira con la console; i servizi
  degli enti non mandano gli header CORS; la CSP non va allargata a
  `connect-src` per host che servono immagini.
- **Solo cinque campi sono compilabili da una carta.** Una carta dice di che
  roccia e fatto il terreno sopra la cavita; non dice se prosegue, se e attiva,
  quanto e fratturata la volta. Ammettere altre chiavi darebbe l'impressione
  che il resto della sezione si compili senza scendere.
- **Solo i rischi medio e alto accendono la barra avvisi** della scheda. Un
  rischio basso segnalato accanto a un vincolo archeologico e a un periodo
  critico dei chirotteri abituerebbe a ignorare la barra. Sul foglio da
  stampare invece compaiono tutti.
- **Il declassamento del modo «puntuale» oggi non puo scattare**, e va detto
  invece di contarlo fra le difese attive: `compila_sezioni` e `vedi_riservati`
  chiedono entrambi OPE. Resta scritto perche la matrice dei permessi e un
  unico array, e una prova ne verifica l'accoppiamento: se un giorno i due
  permessi venissero separati, senza quella riga si aprirebbe una via d'uscita
  silenziosa.
- **Ogni endpoint e stato verificato due volte** prima di finire in
  `config.xml.dist`: un GetCapabilities e poi una vera immagine su un riquadro
  dentro il territorio di competenza. Un layer che risponde ma non disegna e
  peggio di un layer assente, perche chi lo accende non sa se ha sbagliato lui
  o se e giu il server dell'ente. Cio che la verifica ha trovato:
  - il GeoPortale del Lazio pubblica `catasto_delle_cavita_naturali` e
    `PTP_cavita_sotterranee_probabili` — i due layer piu interessanti
    dell'elenco — e **non disegnano**: rispondono con il PNG vuoto di GeoServer
    su qualunque scala. Restano commentati;
  - **il Molise non ha piu un geoportale**: i domini citati dalla
    documentazione ufficiale non risolvono in DNS;
  - **le Marche servono solo in http**, quindi su un CATAGEO in https il
    browser li blocca come contenuto misto;
  - **la carta geologica delle Marche e raster**: un GetFeatureInfo risponde
    con il colore del pixel, non con la formazione;
  - il layer INSPIRE `CP.CadastralParcel` dell'Agenzia delle Entrate rifiuta
    EPSG:3857; si usa `Cartografia_Catastale`;
  - l'Umbria non dichiara EPSG:3857 ma lo serve comunque.

### Corretto
- **Su Google il pannello dei layer non veniva creato** con un solo sfondo, e
  in quel caso i perimetri delle aree sarebbero arrivati in mappa senza un
  modo per spegnerli, mentre su Leaflet il controllo c'e. Due provider che si
  comportano diverso sulla stessa pagina sono un difetto, non una differenza
  di libreria.
- **La linguetta Geologia della scheda mostrava i dati dell'archeologia.**
  Geologia, biospeleologia e archeologia condividono il ramo delle sezioni di
  soli metadati, e la geologia cadeva nel ramo «altrimenti», che e quello
  archeologico: la scheda mostrava periodo e vincoli al posto della litologia.
- **La pagina della sezione geologia andava in errore 500** a ogni apertura:
  la barra avvisi veniva chiamata con il codice dell'ipogeo invece che con
  l'elenco degli avvisi. Non era emerso prima perche la prova a riga di comando
  esercitava la classe, non la pagina.
- **Ortografia del testo dell'interfaccia**: 97 blocchi in 29 pagine
  scrivevano «cavita», «unita», «puo», «piu», «perche», «non e». La correzione
  e automatica ma passa dal tokenizzatore di PHP e tocca solo il testo che
  l'utente legge: le chiavi dei vocabolari (`per porosita`) stanno scritte
  nell'XML delle schede gia salvate, e correggerne l'ortografia orfanerebbe i
  dati.
- **`rocciaEncassante` era scritto male** ed e diventato `rocciaIncassante`.
  Corretto ora che nessun archivio lo contiene: dopo il rilascio sarebbe stata
  una migrazione.

## [1.1.0] — 2026-08-07

Fase 12 (§9.17): estensioni del modello, nate dal confronto con il catasto
delle cavita dell'Umbria della Federazione Speleologica Umbra. Tutto
additivo: **nessuna migrazione**, le schede scritte con la 1.0.0 restano
valide e si leggono come «non si sa» sui campi nuovi.

### Aggiunto
- **Stato esplorativo della cavita**: *esplorazione conclusa* e *prosecuzioni
  note*, piu un campo per dire **dove** si potrebbe proseguire. E la domanda
  per cui il catasto esiste — cosa e stato fatto e cos'altro si puo tentare — e
  finora, quando c'era, stava nel testo libero di un diario, dove nessuna
  ricerca la trovava.
- **Verifica della posizione sul campo**: spunta, data e chi c'e stato,
  distinte dallo stato della scheda. Lo stato scheda dice quanto e affidabile
  la compilazione; questi dicono se qualcuno e andato a controllare quel punto.
  Quando manca, la scheda scrive «mai verificata sul campo» invece di tacere.
- **Ricerca** su entrambi, con il filtro «non verificata da N anni». La query
  che giustifica la fase — *le cavita che proseguono e che non rivede nessuno
  da cinque anni* — ora si scrive in un indirizzo.
- **Complessi**: nuova anagrafica per gli insiemi di cavita che formano un
  sistema unico e hanno un nome proprio — la cosa di cui si parla in
  letteratura, mentre le schede sono il modo in cui il catasto la registra.
  **Nessun codice catastale**: consumare progressivi per un oggetto che non e
  una cavita brucerebbe numeri che poi mancano alle cavita vere. Resta un
  codice proprio facoltativo e libero, per chi — tipicamente sulle
  artificiali — ha gia una numerazione sua. Sviluppo, dislivello e numero di
  cavita si **sommano dalle schede** e non si digitano.
- **Aree speleologiche**: nuova anagrafica per i raggruppamenti geografici
  con un nome proprio, indipendenti dai confini amministrativi. «Alto
  Chiascio» e il modo in cui uno speleologo colloca una cavita, e non
  coincide con regione, provincia o comune, che restano perche servono per
  altro. Assegnabile in scheda, cercabile, e mostrata per nome anche in
  stampa.
- **Ingressi come scheda, e non piu come riga**: nome, tipo (pozzo di
  areazione, finestra, cunicolo di servizio…), stato proprio distinto da
  quello della cavita, e **progressiva** in metri dall'imbocco, che li mette
  in sequenza. Il caso che dimensiona il modello e l'acquedotto e non la
  grotta: gli accessi sono molti, di natura diversa, e un unico "chiuso"
  appiattirebbe un pozzo **tombato** — che resta un possibile accesso — su
  uno **crollato**, che e un'altra cosa. Gli accessi con coordinate proprie
  compaiono sulla mappa di scheda, con tre colori: si passa, e sbarrato,
  non c'e piu.
- **Perimetro delle aree**, da **GeoJSON** o **KML/KMZ**: dove un'area ha
  confini veri — il recinto di una cava — il perimetro si carica e compare
  sulla mappa generale, in un layer spegnibile e sotto i marker. Lo
  shapefile nativo resta fuori: e binario multi-file e QGIS lo converte in
  due clic, e il messaggio di rifiuto lo dice invece di limitarsi a un no.
  Il poligono sta in un file suo, `dati/aree/AS001.geojson`, e non
  nell'anagrafica, che deve restare leggibile a mano.
- **Percorribilita strutturata**, affiancata al testo libero e non al suo
  posto: grado di progressione, difficolta idriche, periodo consigliato,
  necessita di armo e cavita inquinata, da vocabolario e quindi cercabili.
  I quattro campi liberi restano dove sono, e **i testi gia scritti non
  vengono convertiti**: dedurre un grado da una frase in italiano
  sbaglierebbe in silenzio. La scala comprende «T» turistico, che su una
  scala per grotte non esisterebbe ma sulle artificiali e il caso piu
  frequente.
- **Report di completezza** nella pagina Strumenti, distinto dalla verifica di
  integrita: quella dice se l'archivio e **corretto**, questo dice se e
  **finito**. Una colonna per voce — coordinate, posizione verificata, comune,
  tipologia, sviluppo, foto, rilievi, esplorazioni, bibliografia, stato
  esplorativo — le schede ordinate dalla piu incompleta, e lo scarico in CSV.

### Corretto
- **Difficolta e tempo di percorrenza non comparivano nella scheda a
  schermo**: si compilavano nel modulo e si stampavano sul foglio, ma la
  linguetta dei dati non li mostrava. Difetto preesistente, emerso provando
  i campi strutturati.
- **Nessun KML veniva accettato come perimetro.** `Tracciato::aGeoJson()` deduce
  il formato dall'estensione del percorso, e il file temporaneo di PHP non ne
  ha: ogni caricamento finiva in «formato non convertibile». Ora si copia in un
  file con l'estensione giusta prima di convertire, e lo si toglie anche se la
  conversione fallisce.

### Note di progetto
- **Tre stati e non un booleano**: si, no, oppure «non si sa», che e il valore
  predefinito. Su un catasto ricostruito da fonti eterogenee «non lo sappiamo»
  e la risposta piu frequente, e un booleano la scriverebbe come «no».
- **Una cavita mai verificata rientra sempre** nel filtro per anni: e il caso
  piu vecchio di tutti, ed escluderla perche le manca la data sarebbe il
  contrario di quello che serve.
- **Nessuna migrazione**: i nuovi elementi sono opzionali nello schema, quindi
  una scheda scritta prima resta valida e si legge come «non si sa».
- **Il report di completezza non da una percentuale complessiva.** Un «72% di
  completezza» sembra una misura e non lo e: pesa insieme cose incomparabili, e
  chi lo legge non sa cosa fare per alzarlo.
- **Il report rispetta la riservatezza** come la consultazione, e la tabella a
  video si ferma alle 200 schede piu incomplete dichiarandolo; il CSV le
  contiene tutte.
- **Un'area non ha un perimetro**, solo un punto indicativo. I confini di
  un'area speleologica sono d'uso e non di cartografia: un poligono sbagliato
  escluderebbe cavita che tutti considerano dentro.
- **Un'area assegnata a qualche ipogeo non si cancella**, si disattiva: una
  voce cancellata sotto le schede che la citano lascia rimandi rotti.
- **Sugli ingressi tutti i campi restano facoltativi** e la tabella si puo
  ignorare: su una grotta con un ingresso solo la coordinata di scheda basta,
  e il caso normale non deve pagare il costo del caso complesso.
- **Su una scheda a coordinate ridotte nessun accesso finisce sulla mappa**:
  dodici puntini esatti attorno a un cerchio di approssimazione vanificherebbero
  l'offuscamento.
- **Parita fra cavita naturali e artificiali** dichiarata come vincolo di
  progetto (ANALISI 16.2), non come preferenza: e il tratto che distingue
  CATAGEO dai catasti esistenti, e da qui in avanti ogni scelta di modello si
  rilegge una volta pensando a una grotta e una pensando a una cava.

## [1.0.0] — 2026-08-07

Fase 10: rifinitura e **prima release installabile**.

### Aggiunto
- **Scheda da stampare**: un documento lineare, completo e autoconsistente, che
  si manda alla stampante o si salva in PDF con la stampa del browser. Riporta
  tutte le sezioni una dopo l'altra, con gli avvisi in cima, le coordinate in
  tutte le notazioni d'uso e, in fondo, chi ha stampato e quando. Si scelgono
  le sezioni da includere.
- **Manuale utente** (`docs/MANUALE.md`): livelli di utenza, cataloghi e
  codici, censimento, coordinate, riservatezza, tutte le sezioni della scheda,
  ricerca, esportazioni, stampa, importazione, strumenti, e dove stanno i dati.
- **Guida di installazione** (`docs/INSTALLAZIONE.md`): installazione,
  aggiornamento, spostamento di un archivio, backup e ripristino, e i guasti
  che capitano davvero su hosting condiviso.
- **Dati di esempio**: `php esempi/genera-esempi.php` crea un catalogo `ESEMPI`
  con cinque cavita fittizie che coprono i casi interessanti — scheda completa,
  cavita artificiale con archeologia, ubicazione a precisione ridotta, scheda
  riservata, bozza — con esplorazioni, bibliografia, misure, chirotteri e
  vincolo. Si toglie con `--rimuovi`.

### Corretto
- **L'avviso di vincolo archeologico finiva con due punti** quando le
  prescrizioni ne avevano gia uno loro. Compariva cosi in scheda e in entrambe
  le pagine di sezione.
- **Una foto riservata sarebbe finita rotta sul foglio.** La stampa chiedeva
  l'immagine anche a chi non poteva scaricarla, e al posto della foto restava
  il riquadro dell'errore. Ora le foto non visibili si saltano; la riga
  nell'elenco delle risorse resta, come nella pagina delle risorse.

### Note di progetto
- **La stampa e una pagina a parte, non la scheda con un `@media print`**, come
  invece prevedeva l'analisi. La scheda tiene il contenuto in linguette, e una
  linguetta chiusa e `display:none`: alla stampante sarebbe arrivata la sola
  linguetta aperta. Un foglio che sembra la scheda e non lo e, su un documento
  che nasce per essere usato lontano dal computer.
- **Sul foglio non c'e la mappa**: disegnarla richiederebbe di scaricare i tile
  da un server esterno, e una stampa non deve dipendere dalla rete.
- **La riservatezza vale sul foglio come a schermo**, e quando il foglio
  contiene dati riservati lo dichiara con un timbro: una stampa esce
  dall'applicativo e da quel momento nessun permesso la protegge.
- **I dati di esempio sono un generatore e non file versionati**: un archivio
  di esempio congelato diventerebbe, al primo cambio di schema, un archivio non
  valido distribuito insieme all'applicativo che lo rifiuta.
- **Cosa non c'e in 1.0.0**: il provider **Google Maps** alternativo (fase 4b) e
  la sezione **geologia** con i layer cartografici tematici (fase 6b). Sono
  descritti nell'analisi e restano da fare; la cartografia funziona con
  OpenStreetMap e i layer WMS configurabili.

### Verificato
- Nuova suite della stampa: 112 controlli via HTTP, fra cui che tutte le
  sezioni compaiano in un solo documento, che le coordinate ridotte restino
  ridotte, che le sezioni riservate non finiscano sul foglio di chi non puo
  vederle, e che una foto sparita dal disco venga saltata senza rompere la
  pagina.
- Suite del generatore di esempi: 21 controlli, compresa la verifica di
  integrita sull'archivio generato e il comportamento al secondo lancio.
- Regressione completa su tutte le suite precedenti: nessun fallimento.

## [0.16.0] — 2026-08-06

Fase 9b: importazione massiva da CSV. Con questa la **fase 9 e completa**.

### Aggiunto
- **Import di ipogei da file CSV**, in due passi obbligati: caricamento con
  mappatura delle colonne, poi anteprima riga per riga. Riconosce da sole le
  intestazioni prodotte dall'esportazione della fase 8, cosi un CSV uscito da
  CATAGEO si reimporta senza toccarlo; le colonne che non combaciano restano da
  associare a mano, e non si indovinano.
- **Anteprima che dice, per ogni riga, se entra e con quale codice** — oppure
  perche viene scartata e a quale riga del file corrisponde. I codici assegnati
  dalla serie sono simulati in avanti, cosi l'elenco mostra quelli veri e non
  venti volte lo stesso.
- **Nessuna sovrascrittura**: un codice gia presente in archivio viene saltato e
  dichiarato. Anche un codice ripetuto dentro lo stesso file viene saltato alla
  seconda occorrenza, citando la riga in cui era gia comparso.
- **Le schede importate nascono bozza** salvo indicazione contraria nel file, e
  il contatore della serie si riallinea dopo un import con codici manuali.

### Corretto
- **L'anteprima mentiva.** Dichiarava importabile una riga che la scrittura
  avrebbe poi rifiutato: l'import controllava solo il nome, mentre
  `Ipogeo::valida()` esige anche tipologia e coordinate. Chi avesse confermato
  si sarebbe trovato con meno schede di quelle promesse, e senza sapere quali.
  Ora l'anteprima **chiama il validatore vero** invece di ripeterne le regole,
  cosi non puo piu divergere.
- **I numeri di riga erano sbagliati.** Il lettore CSV riusato dai dati
  scientifici scarta le righe vuote e compatta l'elenco: una riga vuota a meta
  file spostava di uno tutte le successive. Per una serie di misure non cambia
  nulla, qui il numero di riga e l'informazione principale del rapporto e un
  numero sbagliato manda a correggere la riga che non c'entra.

### Note di progetto
- **Si importano solo gli ipogei**, non risorse, esplorazioni, bibliografie o
  serie di misure: quelle hanno gia i loro percorsi di caricamento.
- **L'import crea e non aggiorna**, e **non ha annullamento**: un import
  sbagliato si disfa cancellando le schede una per una. La pagina insiste sul
  backup preventivo ma non lo impone, come per la migrazione.
- **La codifica non viene rilevata**: si assume UTF-8, col BOM riconosciuto.

## [0.15.0] — 2026-08-06

Fase 9: strumenti di manutenzione.

### Aggiunto
- **Verifica dell'integrita dell'archivio**: XML non validi, schede mancanti o
  illeggibili, riferimenti rotti fra sezioni, codici duplicati fra cataloghi,
  contatori disallineati rispetto ai codici presenti, file orfani del loro
  indice e viceversa, serie CSV senza descrittore, cartelle fuori standard.
  **Non corregge nulla**: segnala, e per ogni problema dice cosa farne. Un
  archivio di file leggibili a mano si ripara guardando, e una correzione
  automatica che indovina male su un catasto di trent'anni fa piu danni del
  problema.
- **Backup ZIP** dell'intero archivio o di un singolo catalogo, con manifesto
  dentro lo ZIP: versione, data, autore e istruzioni di ripristino. Il backup di
  un catalogo comprende anche anagrafiche e indici, senza i quali le schede
  citerebbero identificativi inesistenti.
- **Verifica dei collegamenti bibliografici** a lotti di venti, con esito
  registrato in scheda. Dove le chiamate in uscita sono bloccate lo strumento
  lo dichiara e non fa nulla: segnare tutti i link come irraggiungibili sarebbe
  un danno, perche quell'esito finisce scritto nelle schede.
- **Ricostruzione degli indici** dalla pagina degli strumenti.

### Corretto
- **Lo strumento che cerca i file rotti si fermava sul primo file rotto.** Un
  XML malformato faceva sollevare un'eccezione a `Ipogeo::trova()` e
  interrompeva l'intera verifica: l'utente vedeva una pagina di errore, senza
  sapere se il resto dell'archivio fosse stato controllato. Ora ogni ipogeo si
  verifica dentro una rete e l'interruzione diventa essa stessa un problema
  segnalato.
- **Due backup creati nello stesso secondo avevano lo stesso nome**, e il
  secondo sovrascriveva il primo in silenzio: si perdeva un backup proprio
  mentre si credeva di averne fatti due.

### Note di progetto
- **L'import CSV massivo non e in questa fase.** Scrivere molte schede da un
  file esterno e l'operazione piu rischiosa dell'applicativo e merita il suo
  giro di prove, non la coda di una fase gia ampia: diventa la 9b. L'export c'e
  dalla fase 8.
- **Il ripristino non e automatizzato**: lo ZIP si estrae a mano dentro `dati/`.
  E deliberato — un ripristino automatico che sovrascrive l'archivio e
  l'operazione piu pericolosa immaginabile — ed e scritto nel manifesto.

## [0.14.0] — 2026-08-06

Fase 8b: migrazione fra cataloghi. Con questa la **fase 8 e completa**.

### Aggiunto
- **Migrazione di uno o piu ipogei** in un altro catalogo: assegnazione del
  codice dalla serie di destinazione, spostamento e rinomina dell'albero,
  traccia storica in scheda, indici aggiornati. Il **codice di origine continua
  a risolvere**: e il motivo per cui l'operazione esiste, perche un codice
  citato in una pubblicazione cartacea non si puo aggiornare.
- **Anteprima obbligatoria** con i codici esatti che verranno assegnati.
  `CodiceCatastale::anteprima()` risponde sempre col prossimo progressivo della
  serie: su cinque ipogei diretti allo stesso catalogo mostrerebbe cinque volte
  lo stesso codice. Qui i contatori si simulano, uno per prefisso, e si saltano
  i codici gia presenti come fa l'assegnazione vera. Dopo la scrittura i codici
  assegnati si confrontano con quelli mostrati, e le differenze si dichiarano.
- **Tracciato** in `dati/_log/migrazioni.csv`, che registra anche i fallimenti
  col motivo: se un lotto si e fermato a meta, il file deve dire dove e perche.
- **Selezione dai risultati di ricerca**, visibile solo a chi ha il permesso.

### Note di progetto
- Un lotto **non e transazionale nel suo insieme**: ogni ipogeo lo e per conto
  suo. Se il terzo di cinque fallisce, i primi due restano migrati e lo si
  dichiara. Annullare tutto richiederebbe di rimettere indietro cartelle gia
  spostate e contatori gia consumati, cioe piu movimento di file proprio nel
  momento in cui qualcosa non funziona.
- L'ordine dei passi non e casuale: si sposta la cartella **prima** di
  rinominarla, cosi un fallimento lascia l'ipogeo intero al suo posto col suo
  codice invece che col codice del catalogo sbagliato.
- **Il backup preventivo dell'albero non c'e ancora** (§5.5 lo prevede): il
  rollback riporta la cartella al suo posto, che copre il caso frequente ma non
  un guasto a meta rinomina. Il backup per catalogo arriva in fase 9, ed e li
  che va costruito una volta sola.

## [0.13.0] — 2026-08-06

Fase 8: la ricerca.

### Aggiunto
- **Ricerca combinata** in AND su tutti i criteri, valutata in tre passate dalla
  piu economica alla piu costosa: l'indice CSV in streaming, poi la distanza
  esatta, poi i criteri specialistici aprendo i file di sezione **dei soli
  sopravvissuti**. La pagina dichiara quante schede ha esaminato e quante ne ha
  aperte: un elenco tagliato in silenzio si scambia per un elenco completo.
- **Testuale** su codice, nome, comune, localita, provincia e regione, insensibile
  a maiuscole e accenti, estesa ai **codici storici**. Digitando un codice — anche
  dismesso da una migrazione — si va dritti alla scheda, che e il caso d'uso di
  chi ha in mano una pubblicazione. Opzione per cercare anche nelle descrizioni,
  che apre le schede una per una e lo dichiara.
- **Per attributi**, presenza di contenuti, intervalli su sviluppo, dislivello,
  quota e date. Un ipogeo senza il dato non compare quando si filtra su quel
  dato: includerlo riempirebbe i risultati di schede di cui non si sa nulla
  proprio sul criterio scelto.
- **Specialistica**: grandezza misurata, specie osservata, periodo archeologico
  anche per intervallo di anni, presenza di vincolo. L'intervallo di anni trova
  anche chi ha dichiarato solo il periodo, usando gli estremi del vocabolario.
- **Geografica** per raggio, con pre-filtro a riquadro e distanza esatta
  (emisenoverso), ordinamento per distanza crescente.
- **Tre viste** — tabella, schede, mappa — e **export CSV, GeoJSON e KML**. Tutto
  in GET: una ricerca e un indirizzo condivisibile e ricaricabile.
- `Geo` per distanze e riquadri; `Esportazione` per la forma delle feature, ora
  condivisa con la mappa invece di esserne una seconda copia.

### Corretto
- **Il riquadro del pre-filtro geografico era tangente al cerchio, non
  contenente.** Un punto proprio sul bordo, a nord o a sud, poteva cadere fuori
  per pochi millimetri di arrotondamento e venire scartato prima che la distanza
  esatta potesse dire la sua: l'unico errore non recuperabile, perche un
  candidato di troppo si scarta dopo, uno di meno sparisce in silenzio. Aggiunto
  un margine di sicurezza.
- La pagina di ricerca dava **errore 500 al primo ingresso**: la condizione
  usava `?? 'tabella'` ma il ramo positivo accedeva alla chiave nuda.
- `Ricerca::risolviCodice()` leggeva una chiave che `IndiceCodici::risolvi()` non
  restituisce, quindi la scorciatoia codice-scheda non scattava mai.

### Aggiunto (lavorazioni parallele)
- Schemi `gruppi.xsd`, `esploratori.xsd` e `periodi.xsd`. Erano dichiarati dalle
  rispettive classi fin dalla fase 2 ma non esistevano: le tre anagrafiche si
  scrivevano **senza validazione**, e senza che nulla lo segnalasse.
- Estratte in file propri le sei eccezioni ancora dichiarate dentro la classe che
  le solleva (`CoordinateEccezione`, `IpogeoEccezione`, `ProiezioneEccezione`,
  `RisorsaEccezione`, `TracciatoEccezione`, `UploadEccezione`): funzionavano solo
  perche chi le cattura aveva quasi sempre gia usato quella classe.

### Corretto (lavorazioni parallele)
- `Anagrafica::xsd()` registra un avviso nel log quando lo schema dichiarato
  manca. La scrittura prosegue comunque (un'installazione a cui manca un file di
  schema deve restare utilizzabile), ma un controllo che si crede attivo e non
  c'e e peggio di un controllo che non c'e e basta.

## [0.12.0] — 2026-08-06

Fase 7d: biospeleologia e archeologia. Con questa la **fase 7 e completa**.

### Aggiunto
- **Osservazioni faunistiche** con taxon, categoria ecologica, stato di
  protezione, direttiva Habitat e Lista Rossa IUCN. Riusano i punti di misura
  dei dati scientifici, cosi due osservazioni nello stesso punto restano
  confrontabili.
- **Colonie di chirotteri** con ruolo della cavita, consistenza, andamento e un
  CSV di conteggi per colonia. Il conteggio ammette il numero esatto **oppure**
  la sola stima minimo-massimo: chi conta in uscita al crepuscolo produce quasi
  sempre un intervallo, e costringere a un numero secco falserebbe il dato.
- **Avviso di periodo critico**, ricorrente ogni anno e scritto `MM-GG` perche
  uno svernamento scavalca il capodanno. Compare nella scheda dell'ipogeo e
  nelle due pagine di sezione.
- **Riservatezza propria della colonia**, indipendente da quella dell'ipogeo e
  prevalente: una cavita pubblica puo ospitare una colonia visibile solo a OPE
  e ADM. Il valore di riposo e "riservata", non "pubblica".
- **Archeologia**: inquadramento per periodo con datazioni anche avanti Cristo,
  periodi secondari e funzioni successive; evidenze con stato di conservazione e
  rimandi a foto, rilievi e bibliografia; tutela con vincolo, ente e
  prescrizioni; indagini collegabili a un diario di esplorazione.
- **Avviso di vincolo**: chi programma un'uscita vede subito che serve
  un'autorizzazione, informazione che prima viveva nella memoria di chi aveva
  fatto le pratiche.
- Barra avvisi unica: i nuovi avvisi entrano in quella gia presente nella
  scheda, prendendoli dalla stessa funzione che li produce nelle pagine di
  sezione. Un avviso che compare in due punti su tre e un avviso che non c'e.
- Schemi `biospeleologia.xsd` e `archeologia.xsd`.

### Modificato
- **L'avviso di periodo critico compare anche a chi non puo vedere la colonia,
  ma oscurato**: dice il periodo e la ragione, tace nome, specie e zona. Alla
  prima prova l'avviso non compariva affatto per le colonie riservate — corretto
  tecnicamente, sbagliato in pratica: chi programma un'uscita e proprio la
  persona che deve sapere di non entrare, ed e quasi sempre chi non ha diritto a
  vedere il roost.

### Corretto
- **Tre colonne dell'indice erano sempre sbagliate**: `ha_chirotteri` e
  `ha_archeologia` sempre a zero e `periodo_arch` sempre vuota, per la stessa
  causa gia trovata con `n_biblio` — il conteggio scorre i file di una cartella,
  e queste sezioni non hanno file per voce. `ha_chirotteri` e ora vero solo se
  esiste davvero una colonia, non una qualunque osservazione.

## [0.11.0] — 2026-08-06

Fase 7c: dati scientifici.

### Aggiunto
- **Punti di misura** stabili nel tempo: due misure "in sala grande" prese a
  cinque anni di distanza restano confrontabili solo se riferite allo stesso
  punto dichiarato. Un punto usato da una serie non si puo togliere.
- **Serie di misure** a due file: un descrittore XML con strumento, taratura,
  responsabile e provenienza; un CSV per le letture, che si accoda senza
  rileggerlo e si apre in un foglio di calcolo. Il CSV ripete strumento, unita
  e provenienza in ogni riga, cosi resta comprensibile anche estratto da solo.
- **Le letture sbagliate non si cancellano, si marcano** (valido, sospetto,
  anomalo, scartato); un valore vuoto e ammesso e significa "lo strumento c'era
  e non ha misurato", cosa diversa dall'assenza di riga.
- **Importazione da datalogger** in due passi, con anteprima del file e
  mappatura delle colonne suggerita per nome. Riconosce il separatore, il BOM,
  le date italiane e la virgola decimale; le righe illeggibili vengono scartate
  **e dichiarate col motivo**, non perse in silenzio.
- **Statistiche** (minimo, massimo, media, mediana, periodo) e **grafico SVG
  generato dal server**: nessuna libreria JS di charting, coerentemente col
  vincolo di zero dipendenze. Il grafico si stampa, si vede senza JavaScript e
  segue tema e tavolozza perche i colori li mette il CSS.
- La riduzione dei punti del grafico **conserva minimo e massimo di ogni
  intervallo** invece di mediare: in una serie ambientale l'informazione sta nei
  picchi, e una media li leviga via. Il taglio e dichiarato sul grafico stesso.
- **Riservatezza propria della serie**, indipendente da quella dell'ipogeo e
  prevalente: una cavita pubblica puo ospitare un monitoraggio che non va
  divulgato. Vale anche per lo scarico del CSV.
- Pannello Dati scientifici nella scheda; schema `scientifici.xsd`.
- `Visibilita::livelloVisibile()`, per le sezioni con riservatezza propria.

### Corretto
- **Errore fatale al posto del messaggio di errore.** Sollevare
  `ScientificiEccezione` in un ramo raggiunto prima di qualunque uso della
  classe `Scientifici` produceva un 500: l'autoload risolve una classe per file,
  e l'eccezione era dichiarata dentro `Scientifici.php`. La convenzione giusta
  era gia documentata in `XmlEccezione.php` ma non era stata seguita: le
  eccezioni di fase 7, 7b e 7c sono ora in file propri.

### Note sulle prove
- Due controlli fallivano per difetti dell'harness che **imitavano** difetti del
  codice: `` `u{FEFF} `` e sintassi di PowerShell 6+ e in 5.1 finiva nel file di
  prova come testo, quindi la prova sul BOM non provava il BOM; e un trattino
  lungo in uno script letto come ANSI non corrisponde a quello della pagina.
  Terza volta che uno strumento di misura viene scambiato per un difetto.

## [0.10.0] — 2026-08-06

Fase 7b: bibliografia.

### Aggiunto
- **Catalogo generale delle opere** (`dati/bibliografia_generale.xml`), fra le
  anagrafiche. Una monografia che descrive quaranta cavita si censisce una volta
  e si cita quaranta volte: correggerne l'editore costa una correzione, non
  quaranta. L'elenco di chi la cita non e memorizzato, si ricava.
- **Bibliografia di sezione** con tre forme di voce: rimando a un'opera del
  catalogo (con pagine e tavole di *questa* cavita), fonte propria dell'ipogeo,
  risorsa in rete. Le voci si presentano raggruppate per rilevanza, che e
  l'ordine in cui si vogliono leggere.
- **Export BibTeX** della bibliografia di un ipogeo e dell'intero catalogo.
  CATAGEO non impone uno stile bibliografico normalizzato: chi deve applicarne
  uno lo fa con lo strumento che gia usa.
- **Verifica dei collegamenti**: l'esito si registra dalla scheda, anche quando
  e negativo. Sapere che un link e rotto vale piu che non sapere nulla, ed e
  cio che spinge ad archiviarne una copia fra gli allegati.
- Pannello Bibliografia nella scheda dell'ipogeo.
- Schemi `bibliografia.xsd` e `bibliografia-generale.xsd`.

### Corretto
- **`n_biblio` nell'indice generale era sempre zero.** Il conteggio scorre i
  file presenti nella cartella di una sezione, escludendo l'XML di indice; la
  bibliografia e l'unica sezione **senza file per voce**, quindi l'unico file
  presente era proprio quello escluso. Ora per `BB` si contano le voci.
- **Una rilevanza non indicata faceva fallire il salvataggio** con un errore di
  validazione XSD invece di prendere il valore di riposo: il modulo manda tutti
  i campi, anche vuoti, e la stringa vuota vinceva sul valore predefinito.

### Note sulle prove
- Tutti i controlli sull'export BibTeX fallivano per un difetto dell'harness,
  non dell'applicativo: con un `Content-Type` che PowerShell non riconosce come
  testo, `Invoke-WebRequest` restituisce i byte grezzi. Il file era corretto
  dall'inizio. Prima di credere a un fallimento, verificare lo strumento.
- Resta noto e documentato che **gli identificativi delle anagrafiche si
  riusano** dopo una cancellazione. In applicativo il danno e chiuso, perche
  un'opera citata non si puo cancellare; un riferimento cartaceo a `OP012` no.

## [0.9.0] — 2026-08-06

Fase 7: i diari di esplorazione.

### Aggiunto
- **Diari di uscita**, uno per file XML nella cartella `[codice] - Esplorazioni`.
  Un diario e un documento che sta in piedi da solo e va letto anche fuori
  dall'applicativo: per questo non finisce dentro `Dati.xml` ma resta un file
  con un nome che dice cosa contiene.
- **Voci di diario** con ora, testo libero, posizione e foto. Le posizioni
  finiscono su una mappa nella pagina del diario; le foto sono **riferimenti**
  alla galleria dell'ipogeo (`FO001`) e non copie, cosi la stessa foto resta un
  file solo. Un riferimento a una foto poi rimossa viene **segnalato in rosso**,
  non nascosto: un buco silenzioso e peggio di un buco visibile.
- **Partecipanti** presi dall'anagrafica oppure scritti a mano: chi viene una
  volta sola non deve costringere a creare una scheda che poi resta li.
- **Vista trasversale** delle esplorazioni di tutto il catasto, con filtri per
  gruppo, esploratore, periodo e catalogo, cronologia raggruppata per anno e
  riepiloghi per tipo e per anno.
- Pannello Esplorazioni nella scheda dell'ipogeo, al posto del segnaposto
  "in arrivo".
- Schemi `esplorazione.xsd` ed `esplorazioni-indice.xsd`.

### Corretto
- **I progressivi delle esplorazioni si riusavano.** Il prossimo numero veniva
  dedotto dai file presenti: rimosso ES001, il diario successivo tornava a
  essere ES001, e ogni "ES001" gia citato in una relazione avrebbe puntato a
  un'altra uscita. Ora l'indice registra `ultimoProgressivo`, e il numero si
  cerca anche fra i file in `_rimossi`, cosi resiste pure alla cancellazione
  dell'indice.
- La scrittura di un diario **pretendeva tutti i campi**: registrare un'uscita
  con solo titolo, tipo e data faceva fallire il salvataggio invece di lasciare
  vuoto il resto.

### Note sulle prove
- Un utente USR **non riceve alcun token CSRF** da nessuna pagina, perche non
  gli viene mai mostrato un modulo. Il suo tentativo di POST viene quindi
  respinto dal controllo del token *prima* di arrivare a quello del permesso: la
  prova lo dichiara apertamente e verifica il controllo del permesso sul codice,
  invece di spacciare per prova sui permessi una prova che parla d'altro.

## [0.8.1] — 2026-08-05

Il riquadro nero del visualizzatore 3D.

### Corretto
- **Ogni nuvola di punti veniva disegnata come superficie**, cioe come una
  manciata di triangoli fra vertici consecutivi: praticamente invisibile. La
  distinzione fra nuvola e mesh guardava anche l'attributo delle normali, ma le
  normali venivano calcolate una riga sopra e quindi c'erano sempre. Ora conta
  solo la presenza di **facce**, e le normali si calcolano dopo la decisione e
  solo se il file non le porta.
- **I modelli in coordinate assolute non arrivavano interi alla scheda grafica.**
  Il modello veniva centrato spostando l'oggetto e lasciando i vertici a valori
  come 4.678.705: la somma vertice + posizione la fa la GPU in virgola mobile a
  32 bit, dove quella grandezza ha una precisione di circa mezzo metro. Un
  rilievo di dieci metri diventava una scalinata, uno di un metro spariva. Ora si
  traslano i **vertici**, cosi i valori scendono vicino a zero.
- La dimensione dei punti si applica a tutte le nuvole dell'oggetto e non solo
  alla radice: un OBJ puo contenerne piu di una.
- L'elenco delle fasi nella pagina iniziale era rimasto fermo alla fase 3.
  Annotato nel codice che le copie sono tre — pagina iniziale, README e piano in
  analisi — e vanno aggiornate insieme.

### Aggiunto
- Sotto il visualizzatore ora si leggono **vertici e facce**: «500 vertici ·
  nuvola di punti, nessuna faccia · ingombro 50,0 x 6,0 x 4,0». Davanti a un
  riquadro nero, «non e arrivato» e «e arrivato ma non si vede» sono due guasti
  diversi che si somigliano, e senza numeri non si sa da dove cominciare.
- Un file valido ma **privo di geometrie** lo dichiara, invece di mostrare il nero.

### Verificato
- Sei PLY costruiti apposta — nuvola ASCII, con normali, binaria, in UTM, mesh
  binaria colorata, mesh in UTM — caricati e ispezionati in scena: prima nessuna
  delle quattro nuvole era riconosciuta come tale, ora tutte.
- Proiettando i vertici con le matrici della camera, **il 100% dei campioni cade
  dentro il tronco di visuale** in tutti e sei i modelli. Che ci sia un oggetto in
  scena non basta: va dimostrato che finisca nell'inquadratura.
- La suite verifica ora le due premesse nel codice, perche sono esattamente cio
  che regredirebbe senza accorgersene.

### Nota
- Il cubo di otto vertici usato nella fase 6 non faceva emergere **nessuno dei
  due** difetti: e una superficie, con facce e coordinate piccole, cioe l'unico
  caso che funzionava. I dati di prova troppo gentili nascondono i guasti.

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
