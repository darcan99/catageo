# Prove della fase 7 — diari di esplorazione

Verifiche eseguite il **2026-08-06** su CATAGEO **0.9.0**, PHP 8.2 (server
integrato) su Windows 11.

Due suite, che guardano cose diverse:

| Suite | Cosa esercita |
|---|---|
| `prova-esplorazioni.php` | La libreria `Esplorazioni` da riga di comando: 37 controlli |
| `prova-esplorazioni.ps1` | Le pagine via HTTP, come le usa chi compila: 101 controlli |

Entrambe passano per intero. La seconda non è una ripetizione della prima: una
libreria corretta chiamata da un modulo con i campi sbagliati produce comunque
un archivio vuoto, e questo si vede solo passando dalle pagine.

## Cosa è stato verificato

### Il diario come documento

Un diario è un file XML autonomo, `DEMO001-ES001-titolo.xml`, leggibile a occhio
e apribile senza l'applicativo. La prova controlla che il titolo con apostrofo e
barra (`Ricognizione all'ingresso alto / prima parte`) non finisca nel nome del
file, e che gli a capo negli obiettivi sopravvivano al giro XML → HTML.

### Le voci

Ora, testo, posizione e quota. La virgola decimale della tastiera italiana viene
accettata (`41,856231` → `41.856231`). Una voce senza ora e senza posizione è
legittima e resta tale. Una latitudine di `910` **non viene scritta**: meglio
nessuna posizione che una posizione sbagliata, che sulla mappa non si distingue
da una giusta.

I punti georiferiti finiscono in un GeoJSON **dentro la pagina** e non dietro un
secondo endpoint: il dato era già stato letto per disegnare il diario, e una
seconda richiesta rileggerebbe lo stesso file per nulla. La prova verifica che
sia GeoJSON valido, che l'ordine sia longitudine-latitudine come vuole lo
standard, e che la quota sia la terza coordinata.

### Le foto non si duplicano

Una foto citata da una voce è un riferimento (`FO001`) alla galleria
dell'ipogeo. La prova salva un riferimento a `FO007`, che non esiste, e verifica
che la pagina **lo mostri segnalandolo** invece di ometterlo: un riferimento che
sparisce in silenzio fa credere che la voce non avesse foto.

### I partecipanti

Dall'anagrafica oppure col solo nome. Le righe completamente vuote del modulo
vengono scartate senza errore. Chi è in anagrafica viene collegato alla propria
cronologia; chi non c'è è marcato «non in anagrafica», così la differenza si
vede.

### L'aggiornamento sostituisce, non accoda

Salvare un diario modificato manda lo stato intero del modulo. Voci, gruppi e
partecipanti omessi vengono **tolti**, non lasciati com'erano — ed è quello che
deve succedere, altrimenti una voce cancellata non si potrebbe cancellare. La
prova lo verifica esplicitamente, perché è il genere di comportamento che
sembra un difetto quando lo si incontra per caso.

Il file segue il nuovo titolo e il vecchio non resta accanto al nuovo.

### I progressivi non si riusano — difetto trovato qui

`prossimoProgressivo()` deduceva il numero dai file presenti. Rimosso ES001, il
diario successivo tornava a chiamarsi ES001. Sul disco sono comparsi davvero due
file `-ES001-` con contenuti diversi, ed è così che il difetto si è visto.

Un progressivo riusato rompe una promessa che non è tecnica: «ES003» citato in
una relazione del 2026 deve indicare la stessa uscita anche nel 2030.

La correzione cerca il massimo in tre posti, perché nessuno basta da solo:
`ultimoProgressivo` scritto nell'indice, i file presenti, e i file finiti in
`_rimossi`. La prova verifica che il numero sopravviva sia alla rimozione di un
diario sia alla **cancellazione dell'indice** — l'indice è una cache, e
ricostruirlo non deve far rinascere un numero già speso.

### Un secondo difetto: i campi obbligatori di troppo

`scriviDiario()` leggeva `$dati['dataFine']` senza valore di riposo. Registrare
un'uscita minima — titolo, tipo, data — faceva fallire il salvataggio con un
errore su una chiave mancante. Emerso al primo giro della suite di libreria,
prima ancora di passare dalle pagine.

### Permessi — e cosa la prova *non* dimostra

Un utente USR legge i diari e non vede i pulsanti di redazione. Il modulo non
compare nemmeno chiamando l'indirizzo diretto.

Sul POST, però, va detto com'è: **un USR non riceve alcun token CSRF da nessuna
pagina**, perché non gli viene mai mostrato un modulo, e il token nasce solo
quando un modulo lo chiede. Il suo tentativo di POST viene quindi respinto dal
controllo del token *prima* di arrivare a quello del permesso. La suite lo
dichiara apertamente e verifica il controllo del permesso **sul codice**
(`Auth::esigiToken()` immediatamente seguito da `Auth::esigi('redigi_esplorazioni')`),
invece di far passare per prova sui permessi una prova che parla d'altro.

Nella prima stesura tre asserzioni sui permessi passavano perché l'accesso USR
era fallito e la sessione non era autenticata: la pagina di accesso non contiene
nessuno dei pulsanti cercati, quindi tutte le negazioni erano vere per il motivo
sbagliato. Ora le negazioni sono subordinate a una verifica che l'USR sia
davvero dentro e davvero su quella pagina.

### Integrità

Nessun errore di validazione XSD in diagnostica, nessuna riga di errore nel log
applicativo, conteggio `n_esplorazioni` corretto nell'indice generale.

## Regressione

Tutte le suite precedenti rieseguite dopo le modifiche: `prova-web`,
`prova-fase2`, `prova-fase2b`, `prova-fase3`, `prova-appartenenze`,
`prova-mappa`, `prova-risorse`, `prova-rilievi`, `prova-utm-web`, più i
controlli PHP di `core`, `codice`, `ipogeo`, `coordinate`, `metadati`,
`tracciato`, `xsd`. Zero fallimenti. Serviva: la fase ha toccato
`catageo-mappa.js` e la scheda dell'ipogeo, che sono usati altrove.

## Cosa questi controlli **non** coprono

- **La mappa dei punti nel browser.** Il GeoJSON è verificato nella pagina, ma
  che Leaflet lo disegni davvero è stato controllato solo per costruzione: il
  ramo `avviaMappaTracciatoInPagina` riusa `costruisciTracciato`, già esercitato
  dalla fase 6, ma non è stato aperto in un browser vero.
- **Le righe ripetibili di `catageo-diario.js`.** Aggiunta e rimozione di voci e
  partecipanti non sono state provate: servirebbe un browser. Il modulo però
  resta usabile senza JavaScript — si compila la riga presente e si salva.
- **Diari con molte voci.** Le prove arrivano a tre voci. Un diario di
  cinquanta voci non è stato provato né per prestazioni né per usabilità del
  modulo.
- **Il filtro per esploratore su un archivio grande.** È l'unico filtro che apre
  i diari uno per uno, e su migliaia di ipogei costerà. Provato su un ipogeo
  solo: il costo reale non è misurato.
- **Uscite su più giorni oltre le 24 ore.** La durata è calcolata e provata a
  cavallo della mezzanotte (16,75 ore); una permanenza di tre giorni non è stata
  verificata.
- **Il campo traccia GPS** accetta un nome di file ma non verifica che quel file
  esista fra i rilievi: è un testo libero, non un riferimento controllato.
- **Concorrenza.** Due redazioni simultanee dello stesso diario non sono state
  provate; il lock c'è sull'indice, non sul singolo diario.
