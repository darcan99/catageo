# Prove della fase 10 — scheda da stampare e dati di esempio

Verifiche eseguite il **2026-08-07** su CATAGEO **1.0.0**, PHP 8.2 (server
integrato) su Windows 11.

| Suite | Cosa esercita |
|---|---|
| `prova-stampa.ps1` | La pagina di stampa via HTTP: 112 controlli |
| `esegui-esempi.ps1` | Il generatore di dati di esempio: 21 controlli |

Entrambe passano per intero.

## La domanda a cui questa suite risponde

Una sola: **cosa finisce sul foglio**. Conta sia ciò che manca — sezioni perse
per strada — sia ciò che non doveva esserci: coordinate riservate, sezioni non
divulgabili. Un foglio esce dall'applicativo e non ci torna, e da quel momento
nessun permesso lo protegge.

## Il difetto che la fase evita

La scheda a schermo tiene il contenuto in linguette Bootstrap, e una linguetta
non attiva è `display:none`. Stampare la scheda avrebbe prodotto **la sola
linguetta aperta**, con l'aspetto di una scheda completa. Nessun errore, nessun
messaggio: un documento che sembra giusto e non lo è, portato in campagna al
posto dell'applicativo.

Per questo la stampa è una pagina a parte, e la suite verifica che in **un solo
documento** compaiano insieme ubicazione, caratteristiche, ingressi,
descrizione, storia, esplorazioni, tutte e tre le forme di voce bibliografica,
punti di misura, serie, osservazioni faunistiche, colonie, sintesi
archeologica, evidenze, tutela e indagini.

## Riservatezza: il grosso della suite

L'archivio di prova contiene apposta quattro casi che si comportano in modo
diverso, e ciascuno viene chiesto da tre utenze — ADM, OPE e USR — perché è la
combinazione che conta, non la scheda da sola.

**Coordinate ridotte.** Un utente USR ottiene il valore arrotondato, mai quello
esatto, la stampa lo dichiara, e **l'UTM non compare**: darlo equivarrebbe a
restituire la posizione al metro attraverso una notazione diversa. Anche le
coordinate dei singoli ingressi restano fuori. Un ADM vede il valore esatto e
il suo foglio porta il timbro di riservatezza.

**Scheda riservata.** USR non ottiene il documento e viene rimandato con un
messaggio; ADM lo ottiene, col timbro e con l'avviso di bozza.

**Sezioni riservate dentro una scheda pubblica.** Verificato che USR non veda
né la serie di misure riservata né la colonia riservata, che veda invece quelle
pubbliche, e che il suo foglio **non** porti il timbro: un timbro che compare
sempre non è un timbro, è una filigrana.

## I codici diventano parole

Guardando il foglio prodotto sono saltati fuori quattro punti in cui la stampa
riportava un identificativo invece di un nome: `E001` per il censore, `G001`
per il gruppo, `T-ARIA` per la grandezza, `2026-08-06T23:56:48` per l'istante
di modifica, `-100 — 200` per una datazione che due righe sopra era già scritta
come «27 a.C. — 476 d.C.».

A schermo si tollerano: c'è un'interfaccia intorno. Su carta no. Ora si
risolvono in anagrafica, e la suite lo verifica — compreso che l'unità non
venga stampata due volte, perché l'etichetta del vocabolario la porta già.

## La foto riservata che sarebbe uscita rotta

Guardando i campi delle risorse è emerso che anche **una singola foto** può
essere riservata, indipendentemente dalla scheda. La stampa non ne teneva
conto: a un utente che non può scaricarla, `scarica.php` avrebbe risposto no e
sul foglio sarebbe rimasto il riquadro dell'immagine rotta.

Due difetti in uno — un'immagine che non doveva essere richiesta, e un foglio
sporcato da un errore che l'utente non può spiegarsi. Ora le foto non visibili
si saltano, mentre **la riga nell'elenco delle risorse resta**: è la stessa
scelta della pagina delle risorse, dove una risorsa riservata si dichiara ma
non si consegna. L'archivio dice cosa contiene.

## Un difetto trovato altrove

Componendo l'avviso di vincolo archeologico, `Archeologia::avvisi()` aggiungeva
un punto in coda a pezzi che spesso ne avevano già uno loro: «Vietata ogni
raccolta di materiale..». Non è un difetto della stampa — compariva in scheda e
in entrambe le pagine di sezione — ma si vede guardando un foglio composto.

## Cosa è stato verificato, oltre a questo

**Il documento è autonomo**: niente Bootstrap, niente barra di navigazione,
niente JavaScript, niente Leaflet. Una stampa non deve dipendere dalla rete.

**Le foto**: sette caricate contro un tetto di sei, perché un tetto mai
raggiunto è un tetto di cui non si sa nulla. Ne stampa sei, dichiara quante ne
ha lasciate fuori, e l'elenco delle risorse le conta tutte e sette. Cancellando
dal disco il file della prima, l'immagine viene saltata senza rompere la
pagina, e l'elenco continua a dichiararla: il file manca, la risorsa esiste.

**La scelta delle sezioni** funziona in entrambi i sensi: con la sola sezione
dati il resto sparisce; togliendo tutte le spunte resta la testata con gli
avvisi; **senza parametri si stampa tutto**. Quest'ultimo caso ha richiesto un
campo nascosto: senza, «nessuna spunta» e «primo accesso» sono indistinguibili,
e chi arriva dal pulsante della scheda otterrebbe un foglio vuoto.

**Codice storico**: la stampa di un codice dismesso porta alla scheda corrente
e riporta il codice corrente, non quello vecchio.

**Casi degeneri**: codice inesistente e nessun codice non producono errori
fatali, ma un messaggio e un rimando. Senza sessione non si stampa nulla.

## Il generatore di dati di esempio

Verificato che produca cinque ipogei con le sezioni compilate, che le pagine li
mostrino, che la **verifica di integrità non trovi problemi** sull'archivio
generato, che un secondo lancio si rifiuti di rifare qualcosa, e che la
rimozione svuoti l'indice lasciando le schede in `_eliminati` — la regola
dell'archivio vale anche per gli esempi.

Il controllo sull'integrità è scritto **in positivo**: cerca «Nessun problema
rilevato», non l'assenza di errori. Un `-not match` sarebbe passato anche se la
verifica non fosse mai partita, ed è proprio il caso che rende inutile un
controllo.

## Difetti della prova, non del prodotto

Tre, e vale la pena elencarli perché somigliavano a difetti veri:

- `-match` in PowerShell è **insensibile alle maiuscole**: cercare `Warning`
  per escludere le diagnostiche di PHP combaciava con la classe CSS
  `alert-warning` del messaggio di rimando. Serve `-cmatch` e la forma con i
  due punti.
- Un **trattino lungo** scritto in un `.ps1` letto come ANSI da PowerShell 5.1
  non combacia mai con quello UTF-8 della pagina.
- Un'asserzione inchiodata alla **serie 0.x** (`CATAGEO 0\.\d+\.\d+`) è fallita
  al primo rilascio 1.0.0, senza che nulla si fosse rotto.

## Regressione

Tutte le suite precedenti rieseguite: **nessun fallimento**. Il riepilogo si
ottiene con `esegui-regressione.ps1`, che va tenuto per una ragione imparata
qui: le suite non sono tutte uguali — alcune sono inchiodate alla porta 8123,
altre leggono le variabili d'ambiente, altre hanno un orchestratore proprio, e
i marcatori di esito sono di tre stili diversi. Ignorarlo produce trentacinque
fallimenti che non dicono nulla sul codice, ed è esattamente quello che è
successo al primo tentativo.

## Cosa questi controlli **non** coprono

- **L'impaginazione vera.** Le prove leggono l'HTML, non guardano il PDF. Salti
  di pagina, righe orfane e titoli isolati a fondo foglio sono affidati alle
  regole CSS (`break-inside`, `break-after`) e a un controllo a occhio, non a
  una verifica automatica.
- **Le stampanti.** Provato su carta A4 attraverso la stampa del browser; non
  su formati diversi, né su stampanti fisiche di marche diverse.
- **I browser.** Il motore di stampa cambia fra Chromium, Firefox e Safari, e
  le proprietà `break-*` sono supportate in modo disomogeneo.
- **Schede enormi.** Nessuna misura del tempo di generazione per una scheda con
  centinaia di voci in ogni sezione.
- **I dati di esempio non contengono media**: nessuna foto, nessun rilievo,
  nessun file caricato. Generare immagini richiederebbe `gd`, che è opzionale, e
  un generatore che fallisce dove l'estensione manca sarebbe peggio di uno che
  produce meno.
