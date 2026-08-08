# Manuale di CATAGEO

Manuale d'uso del catasto degli ipogei. Aggiornato a CATAGEO **1.0.0**.

Per installare, aggiornare o spostare un archivio vedi
[INSTALLAZIONE.md](INSTALLAZIONE.md). Per le scelte di progetto e il modello
dei dati vedi [ANALISI.md](ANALISI.md).

---

## Indice

1. [Cos'è CATAGEO](#1-cosè-catageo)
2. [Accesso e livelli di utenza](#2-accesso-e-livelli-di-utenza)
3. [Cataloghi e codice catastale](#3-cataloghi-e-codice-catastale)
4. [Censire un ipogeo](#4-censire-un-ipogeo)
5. [Coordinate](#5-coordinate)
6. [Riservatezza](#6-riservatezza)
7. [La scheda](#7-la-scheda)
8. [Risorse: allegati, foto, video, rilievi](#8-risorse-allegati-foto-video-rilievi)
9. [Esplorazioni](#9-esplorazioni)
10. [Bibliografia](#10-bibliografia)
11. [Dati scientifici](#11-dati-scientifici)
12. [Biospeleologia](#12-biospeleologia)
13. [Archeologia](#13-archeologia)
14. [Geologia](#14-geologia)
15. [Mappa](#15-mappa)
16. [Ricerca ed esportazioni](#16-ricerca-ed-esportazioni)
17. [Stampa della scheda](#17-stampa-della-scheda)
18. [Migrazione fra cataloghi](#18-migrazione-fra-cataloghi)
19. [Importazione da CSV](#19-importazione-da-csv)
20. [Anagrafiche](#20-anagrafiche)
21. [Strumenti di manutenzione](#21-strumenti-di-manutenzione)
22. [Aspetto](#22-aspetto)
23. [Dove stanno i dati](#23-dove-stanno-i-dati)

---

## 1. Cos'è CATAGEO

CATAGEO è un catasto di cavità artificiali e naturali: schede, coordinate,
foto, rilievi, diari di esplorazione, bibliografia, misure ambientali,
osservazioni biologiche e dati archeologici, tutti agganciati a un codice
catastale.

Ha due caratteristiche che ne spiegano quasi ogni scelta:

**Non usa un database.** Ogni scheda è un file XML dentro una cartella con un
nome leggibile — `LAZ0123 - Grotta del Ceraso/LAZ0123 - Dati.xml` — e gli
indici sono file CSV rigenerabili. Si può aprire tutto con un editor di testo,
copiare l'archivio su una chiavetta e ritrovarlo intatto fra vent'anni, anche
se questo applicativo non esisterà più. È il requisito principale del progetto,
non un ripiego.

**Gira ovunque.** PHP e nient'altro: nessuna libreria da installare, nessun
servizio esterno, nessuna CDN. Le librerie di terze parti (Bootstrap, Leaflet,
three.js) sono nella cartella `assets/vendor` e vengono servite dal tuo sito.

---

## 2. Accesso e livelli di utenza

Ci sono tre livelli, e ciascuno comprende quello sotto.

| Livello | Cosa può fare |
|---|---|
| **USR** — Utente | consultare, cercare, esportare |
| **OPE** — Operatore | tutto quanto sopra, più creare e modificare schede, caricare risorse, compilare tutte le sezioni, gestire le anagrafiche, e **vedere le bozze e i dati riservati** |
| **ADM** — Amministratore | tutto quanto sopra, più eliminare ipogei, cambiare codici, migrare fra cataloghi, gestire cataloghi e utenti, backup e strumenti |

La differenza che conta di più non è cosa si può scrivere ma **cosa si vede**:
un utente USR non vede le schede in bozza, non vede le schede a ubicazione
riservata, e vede le coordinate arrotondate dove la scheda lo prevede. Non è
una limitazione dell'interfaccia ma del dato consegnato: le esportazioni, le
mappe e le stampe applicano la stessa regola.

Dopo cinque tentativi di accesso falliti l'utenza si blocca per un quarto d'ora
(valori configurabili). È una difesa contro chi prova password a raffica, non
contro chi ha dimenticato la propria: passati i quindici minuti si riprova.

---

## 3. Cataloghi e codice catastale

Un **catalogo** è un insieme di ipogei con regole di codifica proprie e un
contatore proprio. Serve perché un catasto reale ne tiene più d'uno: le cavità
naturali del carsismo regionale e le cavità artificiali urbane non si numerano
allo stesso modo e spesso non appartengono nemmeno allo stesso ente.

Ogni catalogo ha una o più **serie di codifica**. Una serie stabilisce come si
compone il codice: un prefisso, eventualmente una parte che dipende dalla
natura o dalla regione, e un progressivo con un numero minimo di cifre.

Dalla pagina **Cataloghi** si può chiedere l'**anteprima del codice** che
verrebbe generato per una data combinazione, prima di censire alcunché. Vale la
pena usarla: le regole di codifica sono la parte del sistema più facile da
sbagliare, e ci si accorge dell'errore quando ci sono già cento schede.

Il progressivo **non si riusa mai**. Se elimini l'ipogeo numero 42, il
prossimo sarà il 43: due schede diverse non devono mai aver portato lo stesso
codice, perché quel codice è finito su una pubblicazione, su un cartello, in un
verbale.

Il padding delle cifre è una **soglia minima, non un tetto**: una serie a tre
cifre arrivata a 999 continua con 1000, non si ferma.

---

## 4. Censire un ipogeo

Da **Ipogei › Nuovo**. I campi obbligatori sono pochi — nome, tipologia,
coordinate — perché una scheda si compila nel tempo, e pretendere tutto subito
significa non far registrare nulla.

La **tipologia** è a due livelli: natura (naturale/artificiale), tipologia e
sottotipologia, prese dal vocabolario delle tipologie. Il vocabolario si
estende dalle anagrafiche; non si inventano codici scrivendoli nella scheda.

Lo **stato della scheda** ha tre valori:

- **bozza** — dati non ancora verificati; la scheda è invisibile agli utenti USR
- **verificata** — controllata da chi ha competenza
- **pubblicata** — consultabile da tutti

Le schede importate da CSV nascono in bozza se il file non dice altrimenti.

Ogni salvataggio incrementa la **revisione** e lascia traccia di chi e quando.
Le versioni precedenti restano nello storico della scheda.

**Eliminare** un ipogeo (solo ADM) non cancella niente dal disco: la cartella si
sposta in `_eliminati`, e il codice resta bruciato. Per far sparire davvero un
ipogeo bisogna andare sul filesystem, deliberatamente.

---

## 5. Coordinate

La forma canonica dell'archivio è **gradi decimali WGS84**: è l'unica su cui
lavorano mappa, ricerca per raggio ed esportazioni.

In inserimento però si può scrivere nel sistema con cui si è misurato:

- gradi decimali (`41.9025`)
- gradi, primi e secondi (`41°54'09" N`)
- gradi e primi decimali (`41°54.15' N`)
- UTM WGS84, con fuso ed emisfero
- Gauss-Boaga (Roma 40), fuso Ovest ed Est
- UTM ED50

CATAGEO converte e conserva **anche il dato originale**: sistema, formato e
valore come sono stati scritti. Un catasto che ha misurato in Gauss-Boaga ha
misurato in Gauss-Boaga, e tenere solo la conversione perderebbe cosa fu letto
sullo strumento — informazione che serve quando, fra dieci anni, qualcuno vorrà
capire perché un punto sta trenta metri più in là.

La scheda mostra la stessa posizione in tutte le notazioni d'uso, mettendo per
prima quella del catalogo: per un catasto abituato all'UTM, l'UTM.

Insieme alle coordinate si registrano **quota**, **precisione** in metri,
**metodo** (GPS, CTR, cartografia, stima) e **data del rilevamento**. La
precisione dichiarata vale più di una cifra decimale in più.

---

## 6. Riservatezza

Tre livelli, sulla singola scheda:

| Livello | Effetto |
|---|---|
| **pubblica** | tutto visibile a chi può consultare |
| **coordinate offuscate** | la posizione viene arrotondata (1000 m di default) per chi non ha il permesso `vedi_riservati`; il resto della scheda si vede |
| **riservata** | la scheda intera è invisibile a chi non ha quel permesso |

L'arrotondamento è **deterministico**: la stessa scheda mostra sempre la stessa
posizione approssimata. Uno spostamento casuale a ogni caricamento sarebbe
peggio che inutile, perché ricaricando la pagina molte volte si ricaverebbe il
centro della distribuzione, cioè la posizione vera.

Alcune sezioni hanno una **riservatezza propria, indipendente da quella
dell'ipogeo e prevalente su di essa**: una serie di misure o una colonia di
chirotteri possono essere riservate dentro una cavità pubblica. È il caso
normale, non l'eccezione: la grotta la conoscono tutti, il sito di svernamento
no.

C'è una deroga deliberata. L'**avviso di periodo critico** delle colonie
compare a chiunque, anche quando la colonia è riservata, ma **redatto**: dice
il periodo e il motivo, non quale colonia né dove. Chi programma un'uscita è
esattamente la persona che deve sapere di non entrare a febbraio, e un avviso
nascosto per riservatezza non protegge i chirotteri: li fa disturbare da
qualcuno che non sapeva.

---

## 7. La scheda

La scheda è divisa in linguette: Dati, Descrizione, e una per ciascuna sezione
che contiene qualcosa.

In cima, prima di tutto il resto, c'è la **barra degli avvisi**: stato di
accesso, permessi necessari, pericoli segnalati, vincoli di tutela, periodi
critici per la fauna, ubicazione riservata, scheda in bozza. È quello che chi
sta programmando un'uscita deve leggere prima di leggere il resto.

Le sezioni disponibili sono nove, ciascuna con la sua sigla:

| Sigla | Sezione |
|---|---|
| AL | Allegati |
| FO | Foto |
| VI | Video |
| RI | Rilievi |
| ES | Esplorazioni |
| BB | Bibliografia |
| SC | Dati scientifici |
| BI | Biospeleologia |
| AR | Archeologia |

Ogni voce di sezione ha un riferimento stabile — `FO003`, `BB012` — con cui la
si cita dalle altre sezioni: un'evidenza archeologica può rimandare alla foto
che la documenta e alla pubblicazione che la descrive.

---

## 8. Risorse: allegati, foto, video, rilievi

Si caricano dalla scheda, anche più file insieme.

**Foto.** Se l'estensione `exif` è presente, data di scatto e coordinate si
leggono dal file. Le miniature si generano se c'è `gd`. Una foto può essere
scelta come **copertina** della scheda.

**Rilievi.** Oltre ai documenti 2D (PDF, DXF, immagini), CATAGEO tratta:

- **KML e KMZ**, che diventano tracciati visibili sulla mappa;
- **PLY, OBJ, STL, GLTF/GLB**, aperti in un visualizzatore 3D nel browser.

I rilievi con un tracciato geografico possono comparire sulla mappa generale.

**Video.** Si caricano e si guardano nella finestra dei media, oppure si
registra il solo collegamento a un video ospitato altrove.

**Allegati.** I **PDF si leggono nella finestra**, senza scaricarli e senza
lasciare la pagina: si apre il lettore del browser, con le sue frecce e la sua
ricerca. Il pulsante per scaricare il file originale resta in alto a destra, e
il tasto per lo schermo intero pure — utile su una tavola grande. Gli altri
formati (DOC, XLS, ZIP…) restano uno scaricamento: nessun browser li mostra, e
fingere che li mostri vorrebbe dire aprire una finestra vuota mentre il file
arriva di nascosto nella cartella dei download.

Lo stesso vale in senso opposto fra le foto: la configurazione ammette il
**TIFF**, che nessun browser disegna. Per quelle immagini il collegamento dice
apertamente che scarica, invece di promettere una vista che non può dare.

Il download passa sempre per l'applicativo, che controlla i permessi: un file
riservato non è raggiungibile scoprendone l'indirizzo.

---

## 9. Esplorazioni

Un diario di uscita: data, tipo (esplorazione, rilievo, ricognizione,
manutenzione…), partecipanti presi dall'anagrafica degli esploratori, gruppi
coinvolti, meteo, obiettivi, risultati.

Dentro un diario ci sono **voci** numerate, ciascuna con la sua descrizione,
eventualmente con coordinate proprie e con un rimando alle foto scattate.

Dalla pagina **Esplorazioni** si vedono tutti i diari dell'archivio, filtrabili
per gruppo, esploratore e periodo: è la vista che serve a un gruppo per
ricostruire la propria attività, non quella di una singola cavità.

---

## 10. Bibliografia

Ci sono due piani, ed è la scelta che rende utile questa sezione.

Il **catalogo generale delle opere** contiene le pubblicazioni una volta sola:
autori, titolo, contenitore, editore, anno, ISBN/DOI. Un articolo che parla di
quaranta cavità si registra una volta.

La **bibliografia dell'ipogeo** cita: rimanda a un'opera del catalogo
aggiungendo pagine e tavole, oppure registra una fonte propria di quella
cavità, oppure un collegamento in rete.

Da ogni bibliografia si esporta in **BibTeX** — il catalogo intero o le voci di
un ipogeo — pronto per un lavoro scritto in LaTeX o per Zotero.

I collegamenti in rete si possono **verificare in blocco** dalla pagina degli
strumenti: l'esito resta scritto nella voce, con la data.

---

## 11. Dati scientifici

Per il monitoraggio ambientale: temperatura, umidità, radon, CO₂, portata,
livello dell'acqua, conducibilità e le altre grandezze del vocabolario.

Si dichiarano prima i **punti di misura** — dove nella cavità, con la
progressiva interna e le coordinate se note — e poi le **serie**, ciascuna con
grandezza, unità, strumento, responsabile, periodo, tipo di acquisizione.

Le letture stanno in un CSV a fianco del descrittore XML: una serie da
datalogger può valere decine di migliaia di righe, e metterle in XML
renderebbe la scheda illeggibile.

L'**importazione da datalogger** legge il CSV dello strumento con
un'anteprima riga per riga. Riconosce la virgola decimale italiana, il punto
decimale anglosassone e tre formati di data, e dichiara quante letture ha
scartato e perché.

Le statistiche e i **grafici** si calcolano al volo. I grafici sono SVG
prodotti dal server: nessuna libreria JavaScript da caricare, e il grafico
resta leggibile anche stampato. Quando i punti sono troppi per un grafico, la
riduzione conserva **il minimo e il massimo di ogni intervallo** invece di fare
una media: su una serie di radon la punta è il dato, e una media la
cancellerebbe.

---

## 12. Biospeleologia

Due cose distinte nella stessa sezione.

Le **osservazioni faunistiche**: taxon, gruppo, zona della cavità, numero di
individui, metodo, rilevatore, con i campi di tutela (specie protetta,
Direttiva Habitat, Lista Rossa IUCN).

Le **colonie di chirotteri**: specie, ruolo del sito (svernamento,
riproduzione, transito, swarming), consistenza stimata, tendenza, e soprattutto
il **periodo critico** in cui la cavità non va disturbata.

Il periodo critico genera un avviso in scheda quando siamo dentro quel periodo.
Il calcolo tiene conto dei periodi **a cavallo dell'anno**: novembre–marzo è un
intervallo valido e viene trattato come tale.

I conteggi di una colonia stanno in un CSV, come le serie di misure.

Le colonie sono **riservate per impostazione predefinita**. Vedi il §6 per come
funziona l'avviso redatto.

---

## 13. Archeologia

Particolarmente rilevante per le cavità artificiali, dove il periodo è chiave
sia di lettura sia di ricerca.

L'**inquadramento** registra il periodo principale, i periodi secondari, la
datazione in anni (negativi per l'avanti Cristo), quanto è stretta quella
datazione e su che base, la funzione originaria e il contesto topografico. Un
cunicolo romano riusato come ricovero antiaereo appartiene a due epoche, e la
scheda lo dice.

Le **evidenze** sono i singoli elementi osservati: strutture murarie, tecniche
costruttive, iscrizioni, graffiti, ceramica, affreschi, sepolture, tracce di
strumenti. Ognuna può rimandare a foto, rilievi e bibliografia.

La **tutela** registra il vincolo, l'ente competente, il provvedimento e le
prescrizioni. Un vincolo presente produce un avviso in cima alla scheda: chi
programma un'uscita deve sapere subito che serve un'autorizzazione, informazione
che oggi vive nella memoria di chi ha fatto le pratiche.

Le **indagini** sono gli interventi svolti: ricognizioni, scavi, rilievi,
datazioni, con soggetto ed esito.

---

## 14. Geologia

Si apre dalla scheda, linguetta **Geologia**, oppure da `index.php?p=geologia`.
Sette riquadri che si salvano uno per uno: la geologia si compila in più
riprese — una parte in cavità, una davanti alla carta — e un modulo unico
farebbe perdere tutto a chi sbaglia un campo in fondo.

**Inquadramento** — litologia, formazione, unità geologica, età, sistema e
serie cronostratigrafici, foglio geologico. Sotto c'è **come si è ottenuto il
dato**: osservazione diretta in cavità, lettura manuale della cartografia,
interrogazione automatica del servizio, bibliografia. Non è un dettaglio
burocratico. Una litologia osservata sul posto e una dedotta da una carta
1:100.000 si leggono uguali sulla scheda e valgono diversamente: la prima
descrive quella cavità, la seconda inquadra la formazione regionale e non
distingue una lente di dieci metri. Dichiararlo è ciò che rende usabile il
resto.

**Genesi e assetto strutturale** — tipo di genesi (carsica, vulcanica,
tettonica, erosiva, glaciale, marina, **antropica**, mista), roccia incassante,
processo, immersione e inclinazione in gradi, grado di fratturazione.

**Morfologie** — concrezionamento, marmitte, scallops, canali di volta, crolli,
riempimenti, forme di corrosione e di erosione, **tracce di scavo**. Su una
cavità artificiale quest'ultima è la morfologia principale, e il vocabolario la
prevede: gli altri catasti la ignorano perché sono pensati per le sole cavità
naturali.

**Idrogeologia** — acquifero, permeabilità, ruolo idrogeologico (assorbimento,
drenaggio, risorgenza). La portata si misura nei dati scientifici e non si
ridigita qui: si indica la serie di misure collegata.

**Rischi** — crollo, allagamento, sinkhole, subsidenza, gas, sismico, ciascuno
con un livello. **Solo medio e alto compaiono nella barra avvisi della scheda**:
un rischio basso segnalato accanto a un vincolo archeologico e a un periodo
critico dei chirotteri abituerebbe a ignorare la barra, e la barra serve proprio
per quando conta. Sul foglio da stampare invece compaiono tutti: su un foglio
che si porta in uscita sapere che c'è anche un rischio basso di gas non costa
nulla.

**Campioni** — data, tipo, chi ha prelevato, zona, finalità, dove è depositato,
esito. Il prelievo in cavità può richiedere un'autorizzazione: registrarla è
parte del dato.

### Compilare dalla cartografia

Se in configurazione ci sono layer WMS interrogabili e la scheda ha coordinate,
in cima all'inquadramento compare **Compila dalla cartografia**. CATAGEO chiede
ai servizi cosa riportano sotto il punto della cavità e **propone** i valori
accanto ai campi, uno per uno, ciascuno con scritto da quale carta viene. Non
scrive niente da solo: si accettano singolarmente. Accettandone uno, la
provenienza del dato si porta da sé a «interrogazione automatica», ma solo se
era ancora vuota — una provenienza già dichiarata da una persona non viene
sovrascritta.

Una carta non ha visto la cavità: i valori vanno confermati.

**Se la cavità ha coordinate riservate** l'applicativo non chiede niente finché
non si è scelto cosa può uscire, perché interrogare un servizio significa
mandare il punto al server dell'ente, che di norma lo registra:

- **punto arrotondato** alla griglia configurata (1000 m predefiniti);
- **punto esatto**, per decisione esplicita;
- **non interrogare**, e nessuna richiesta parte.

L'arrotondamento è sempre allo stesso punto, non un errore casuale: un errore
che cambiasse a ogni richiesta si annullerebbe facendo la media di tre
richieste, e chi volesse la posizione vera dovrebbe solo chiederla tre volte. A
1:100.000 mille metri non cambiano la formazione che si legge.

Ogni interrogazione finisce nel registro delle modifiche, comprese quelle
andate a vuoto, con il modo usato. È l'unico punto in cui una coordinata
dell'archivio esce verso un server di terzi.

---

## 15. Mappa

Fondo OpenStreetMap, marker raggruppati in cluster, popup con codice, nome e
tipologia. Si filtra per catalogo, natura, tipologia e stato della scheda.

Si possono aggiungere **layer WMS** dalla configurazione: cartografia tecnica
regionale, ortofoto, carte geologiche. Basta scriverli in `config.xml`: la
Content-Security-Policy dell'applicativo si adegua da sé alle origini elencate
lì, non serve toccare altro.

### Layer già pronti

Un'installazione nuova esce con **26 layer preconfigurati e spenti**, verificati
uno per uno sul proprio territorio. Si accendono dal pannello dei layer.

| Ambito | Cosa c'è |
|---|---|
| Italia | ISPRA: carta geologica 1:100.000, litologia, classi di permeabilità, inventario dei sinkhole, cave, emissioni gassose, geologica 1:1M · catasto dell'Agenzia delle Entrate · aree archeologiche vincolate del Ministero della Cultura |
| Lazio | geologica 1:25.000, unità idrogeologiche, sorgenti, curve di livello, aree archeologiche PTPR, ortofoto AGEA 2023 |
| Abruzzo | ortofoto AGEA 2022, CTR 1:5.000, IGM 1:25.000 |
| Umbria | cave attive, cave dismesse, CTR 1:10.000, ortofoto 2020 |
| Marche | geologica 1:10.000, CTR 2019, ortofoto AGEA 2022, IGM storico 1892-95 |

Le **emissioni gassose** non sono una curiosità: la CO₂ nei vuoti sotterranei
uccide, e sapere che si scende dentro un'area di emissione cambia la
preparazione dell'uscita. L'**IGM storico** serve alle cavità artificiali:
confrontando una carta di fine Ottocento con l'ortofoto di oggi, quello che
c'era e non c'è più è quasi sempre un ingresso.

Due avvertenze:

- I layer delle **Marche** sono serviti solo in `http`. In locale funzionano; su
  un CATAGEO pubblicato in `https` il browser li blocca come contenuto misto e
  restano bianchi senza spiegare perché. In quel caso vanno tolti.
- Per il **Molise** non c'è nulla: il geoportale regionale non esiste più (il
  dominio non risolve). Restano i layer nazionali, che coprono tutta l'Italia.

Su un'installazione **già fatta** questi layer non arrivano da soli:
`config.xml` viene generato una volta sola all'installazione. Vanno copiati a
mano da `config.xml.dist`.

I rilievi georiferiti (KML/KMZ) si sovrappongono alla mappa.

### I simboli

Ogni cavità è una pastiglia colorata con dentro un simbolo, e le due cose
dicono cose diverse:

- il **colore** è la natura — arancio artificiale, verde naturale, viola mista;
- il **simbolo** è la tipologia — una goccia per le opere idrauliche, un
  carrello per quelle estrattive, uno scudo per le belliche, una fiamma per le
  cavità vulcaniche, e così via;
- il **contorno tratteggiato** dice che l'ingresso non è praticabile, quello
  puntinato che la posizione è approssimata perché le coordinate sono riservate.

I simboli si cambiano dai **vocabolari**, sotto Anagrafiche › Tipologie.
L'elenco ha una colonna **Mappa** con il simbolo che ogni voce usa davvero: le
voci che lo ereditano dalla voce superiore sono in grigio, così si vede a colpo
d'occhio chi ha una scelta propria e chi no.

Aprendo una voce si può cambiarlo. Ci sono due insiemi di simboli:

- i **glifi delle cavità**, disegnati per CATAGEO — ingresso di grotta, abisso,
  inghiottitoio, risorgenza, cunicolo, cisterna, acquedotto, colombario, cava,
  rifugio antiaereo, galleria, tubo lavico, concrezioni, abitato rupestre. Si
  scelgono cliccandoli nella tavolozza sotto il campo;
- tutte le icone di [Bootstrap Icons](https://icons.getbootstrap.com/), scrivendone
  il nome senza il prefisso `bi-`: utili per quello che non è una cavità in sé,
  come la fiamma del vulcanismo o il fiocco di neve del glaciale.

Lasciando il campo vuoto la voce **eredita** il simbolo di quella superiore,
quindi una sottotipologia nuova compare in mappa da subito con il simbolo
giusto: si compila solo per distinguerla dalle sorelle. Nel vocabolario
predefinito lo fanno per esempio l'abisso, la risorgenza e l'inghiottitoio, che
un occhio speleologico distingue da lontano.

La pastiglia è simmetrica di proposito: il simbolo indica il punto con il
proprio centro. Un segnaposto a goccia indicherebbe con la punta, e a colpo
d'occhio si leggerebbe la posizione qualche metro più in là.

Le coordinate mostrate sulla mappa rispettano la riservatezza esattamente come
la scheda: una cavità a coordinate offuscate compare arrotondata, una riservata
non compare affatto.

---

## 16. Ricerca ed esportazioni

Le modalità si combinano in AND:

- **testuale** su nome, sinonimi, località, comune, descrizione, e **sui codici
  storici**: digitando un codice dismesso si arriva alla scheda corrente;
- per **catalogo**, natura, tipologia, regione, provincia, comune;
- per **attributi**: sviluppo, dislivello, profondità, stato di accesso,
  presenza d'acqua, con intervalli;
- **specialistica**: presenza di chirotteri, evidenze archeologiche, periodo
  storico, serie di misure, bibliografia;
- **geografica per raggio**: un punto e una distanza in metri.

I risultati si vedono come tabella o su mappa, e si esportano in **CSV**,
**GeoJSON** e **KML**. L'esportazione applica le stesse regole di riservatezza
della consultazione: quello che non si vede a schermo non finisce nel file.

---

## 17. Stampa della scheda

Dal pulsante **Stampa** della scheda si apre un documento lineare pensato per
la carta, che si stampa o si salva in PDF con la stampa del browser.

Non è la scheda con un foglio di stile diverso: è una pagina a parte. La scheda
a schermo tiene il contenuto in linguette, e una linguetta chiusa è invisibile
anche alla stampante — stampandola si otterrebbe la sola linguetta aperta,
credendo di avere tutto.

Il foglio riporta tutte le sezioni una dopo l'altra, con gli avvisi in cima, le
coordinate in tutte le notazioni, e in fondo **chi ha stampato e quando**. Se
contiene dati riservati che l'utente ha il diritto di vedere, lo dichiara con
un timbro in testa a ogni pagina: un foglio esce dall'applicativo e non ci
torna, e da quel momento nessun permesso lo protegge.

Si può scegliere quali sezioni includere. **Non c'è la mappa**: disegnarla
richiederebbe di scaricare i tile da un server esterno, e una stampa non deve
dipendere dalla rete.

Delle foto se ne stampano al massimo sei, e il foglio dice quante ne ha
lasciate fuori.

Fra le sezioni c'è anche la **geologia**, con inquadramento, provenienza del
dato, genesi e assetto, idrogeologia, morfologie, rischi e campioni. Due
differenze rispetto a quello che si vede a schermo, entrambe volute:

- la **provenienza si stampa sempre**, anche quando non è dichiarata. Su un
  foglio che può finire allegato a una relazione, una litologia senza fonte è
  un'affermazione senza autore, e «non dichiarata» dice più di uno spazio
  bianco, che si legge come una dimenticanza di stampa;
- i **rischi ci sono tutti**, non solo quelli che accendono la barra avvisi.

---

## 18. Migrazione fra cataloghi

Solo ADM. Sposta uno o più ipogei in un altro catalogo, assegnando il codice
dalla serie di destinazione.

Il percorso è a due passi: **anteprima** dei nuovi codici, poi conferma. In
anteprima i codici sono simulati in avanti, così l'elenco mostra codici veri e
distinti e non venti volte lo stesso.

Il **codice di origine continua a risolvere**: digitandolo nella ricerca si
arriva alla scheda corrente, con l'avviso della migrazione avvenuta. È il
motivo per cui la funzione esiste — un codice pubblicato non può diventare un
vicolo cieco — e ogni migrazione lascia traccia in scheda e in
`migrazioni.csv`.

Fai un backup prima. La pagina lo raccomanda ma non lo impone.

---

## 19. Importazione da CSV

Solo ADM, dalla pagina **Strumenti**. Serve a far entrare un elenco di cavità
già compilato altrove.

Due passi obbligati:

1. **Carichi il file e mappi le colonne.** Le intestazioni prodotte
   dall'esportazione di CATAGEO si riconoscono da sole, quindi un CSV uscito da
   qui rientra senza toccarlo. Le altre si associano a mano: non si indovinano.

2. **Guardi l'anteprima**, riga per riga: quali entrano e con quale codice,
   quali no, perché e **a quale riga del file** corrispondono. Solo dopo si
   conferma.

L'anteprima usa la stessa validazione della scrittura. Non è un dettaglio
tecnico: un'anteprima che valida in modo diverso da chi scrive dà fiducia
proprio dove non deve.

Cosa l'importazione **non** fa:

- **non sovrascrive mai**: un codice già in archivio viene saltato e dichiarato;
- **non aggiorna** schede esistenti: crea e basta;
- **importa solo gli ipogei**, non risorse, esplorazioni, bibliografie o serie;
- **non ha annullamento**: un import sbagliato si disfa cancellando le schede
  una per una. Fai il backup prima.

Il file si assume in UTF-8 (il BOM viene riconosciuto). Un CSV salvato in ANSI
da un Excel italiano porterebbe dentro accenti sbagliati senza segnalazione.

---

## 20. Anagrafiche

Quattro registri condivisi da tutto l'archivio:

- **Gruppi speleologici** — sigla, nome, sede, affiliazioni;
- **Esploratori** — con lo storico delle appartenenze ai gruppi, così un diario
  del 1985 mostra il gruppo di allora e non quello di oggi;
- **Opere** — il catalogo bibliografico generale (§10);
- **Vocabolari** — tipologie, grandezze misurabili, periodi storici.

I riferimenti sono per identificativo: rinominare un gruppo aggiorna tutte le
schede che lo citano, perché non c'era una copia del nome in ciascuna. Prima di
cancellare una voce l'applicativo dice chi la sta usando.

---

## 21. Strumenti di manutenzione

Solo ADM.

**Verifica integrità.** Cerca XML non validi, schede mancanti o illeggibili,
riferimenti rotti fra sezioni, codici duplicati fra cataloghi, contatori
disallineati, file orfani del loro indice e viceversa, serie CSV senza
descrittore, cartelle fuori standard.

Segnala e **non corregge**, e per ogni problema dice cosa farne. L'unica cosa
che si offre di rifare è l'indice, che è una cache rigenerabile dai dati. Un
archivio di file leggibili a mano si ripara guardando, e una correzione
automatica che indovina male su un catasto di trent'anni fa più danni del
problema.

**Ricostruzione indici.** Rigenera `ipogei.csv` e `codici.csv` dalle schede. Da
usare dopo un ripristino, dopo un aggiornamento che ha aggiunto colonne, o
quando la verifica segnala disallineamenti.

**Backup.** ZIP dell'archivio o di un singolo catalogo. Vedi
[INSTALLAZIONE.md §6](INSTALLAZIONE.md#6-backup-e-ripristino).

**Verifica collegamenti bibliografici.** A lotti di venti, con l'esito scritto
in scheda. Dove le chiamate in uscita sono bloccate lo strumento lo dichiara e
non fa nulla: segnare tutti i link come irraggiungibili sarebbe un danno,
perché quell'esito finisce scritto nelle schede.

**Diagnostica.** Versione di PHP, interi a 64 bit, estensioni presenti,
permessi di scrittura, limiti di caricamento reali dell'hosting,
disponibilità di chiamate HTTP in uscita.

---

## 22. Aspetto

Dal menu con la mezzaluna, in alto a destra: tema chiaro, scuro o automatico, e
quattro tavolozze per il tema chiaro. La scelta vive nel browser di chi guarda
e vince sul valore predefinito della configurazione, che resta quello per chi
non ha scelto.

---

## 23. Dove stanno i dati

```
dati/
├── cataloghi/
│   └── LAZ - Catasto del Lazio/
│       ├── catalogo.xml              descrittore e serie di codifica
│       └── ipogei/
│           └── LAZ0123 - Grotta del Ceraso/
│               ├── LAZ0123 - Dati.xml            la scheda
│               ├── LAZ0123 - Foto/               file + indice di sezione
│               ├── LAZ0123 - Rilievi/
│               ├── LAZ0123 - Esplorazioni/
│               ├── LAZ0123 - Scientifici/        descrittore XML + CSV letture
│               └── ...
├── _indice/
│   ├── ipogei.csv                    cache per elenchi, mappa e ricerca
│   └── codici.csv                    codici storici → codice corrente
├── gruppi_speleologici.xml
├── esploratori.xml
├── bibliografia_generale.xml
├── tipologie.xml, grandezze.xml, periodi_storici.xml
├── utenti.xml
└── _log/
```

Tutto è testo. Le cartelle in `_indice` sono cache: si buttano e si
ricostruiscono. Tutto il resto è il catasto, e va nei backup.

---

## Segnalazioni

Difetti e proposte: <https://github.com/darcan99/catageo> — oppure
darcan99@gmail.com.
