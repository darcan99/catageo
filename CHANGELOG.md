# Cronologia delle modifiche

Tutte le modifiche rilevanti a CATAGEO sono annotate qui, in formato
[Keep a Changelog](https://keepachangelog.com/it/1.1.0/), con versionamento
[semantico](https://semver.org/lang/it/).

## [Non rilasciato]

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
