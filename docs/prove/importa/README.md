# Prove della fase 9b — importazione massiva da CSV

Verifiche eseguite il **2026-08-06** su CATAGEO **0.16.0**, PHP 8.2 (server
integrato) su Windows 11.

| Suite | Cosa esercita |
|---|---|
| `prova-importa.php` | `ImportIpogei` su un'istanza usa-e-getta: 61 controlli |
| `prova-importa.ps1` | Le pagine via HTTP: 53 controlli |

Entrambe passano per intero.

## Il file di prova è volutamente sporco

Un CSV pulito proverebbe soltanto il caso che non serve verificare. Il file
usato contiene, una per riga e **isolando un difetto per volta**: righe valide
con virgola decimale e con punto, una riga senza nome, una con tipologia
inventata, una con latitudine 910, una con data illeggibile, un codice ripetuto
dentro il file, un codice già presente in archivio, una riga senza coordinate, e
una riga interamente vuota.

Ogni riga scartata deve dire **perché** e **a quale riga del file**
corrisponde — verificato che nessuna resti senza motivo.

## Il difetto che valeva la fase

**L'anteprima mentiva.** Dichiarava importabile una riga che la scrittura
avrebbe poi rifiutato: `ImportIpogei` controllava solo il nome, mentre
`Ipogeo::valida()` esige anche tipologia e coordinate. Chi avesse confermato si
sarebbe trovato con meno schede di quelle promesse.

È esattamente il difetto che il progetto dichiarava di voler evitare — «una
simulazione che valida in modo diverso dal caso reale dà fiducia proprio dove
non deve» — scritto in cima al file mentre il codice faceva il contrario.

La correzione non è stata duplicare le regole ma **chiamare il validatore vero**
dentro l'anteprima. Così non può più divergere: se un giorno `Ipogeo::valida()`
cambia, l'anteprima cambia con lui.

I controlli propri di `ImportIpogei` restano, ma solo per dare messaggi
migliori: parlano di **colonne del CSV** («manca il campo obbligatorio
Latitudine»), non di campi della scheda, perché chi guarda ha davanti il file.
Verificato con un difetto che *solo* il validatore conosce — un codice di stato
di tre lettere — che la chiamata avvenga davvero.

## Il secondo difetto: numeri di riga sbagliati

Il primo tentativo riusava `Scientifici::leggiCsvEsterno()`, che scarta le righe
vuote e restituisce un elenco compatto. Il numero d'ordine non corrisponde più
alla riga del file: **una riga vuota a metà sposta tutte quelle successive**.

Per una serie di misure non cambia nulla. Qui il numero di riga è
l'informazione principale del rapporto — dice all'utente dove guardare — e un
numero sbagliato lo manderebbe a correggere la riga che non c'entra. Ora
`ImportIpogei` legge per conto suo conservando il numero vero.

## Cosa è stato verificato

**Non si sovrascrive mai.** Una riga il cui codice esiste già viene saltata e
dichiarata. Verificato reimportando lo stesso file: nessun doppione, nessuna
scheda toccata, tutte le righe segnate come saltate.

**Un codice ripetuto dentro il file** viene saltato alla seconda occorrenza,
indicando a quale riga era già comparso. Senza questo controllo entrambe le
righe risulterebbero importabili nell'anteprima, dove non c'è scrittura a
fermarle.

**L'anteprima non scrive.** Eseguita tre volte di fila, il conteggio delle
schede non cambia e l'esito è sempre lo stesso.

**I codici simulati sono consecutivi e distinti** quando li assegna la serie —
stesso problema già risolto nella migrazione, stessa soluzione.

**Le schede importate nascono bozza** se il file non dice altrimenti: sono dati
che nessuno ha ancora guardato, e pubblicarli d'ufficio li mescolerebbe a quelli
verificati. Se il file dichiara `pubblicata`, si rispetta.

**Il contatore della serie si allinea** dopo un import con codici manuali,
altrimenti il censimento successivo tenterebbe un codice già usato.

**Le colonne non si indovinano a caso**: un'intestazione `pippo;pluto;paperino`
non produce alcuna mappatura suggerita. Quelle che combaciano coi nomi
dell'esportazione sì, così un CSV esportato da CATAGEO si reimporta senza
toccarlo.

**Il percorso è a due passi obbligati**: senza anteprima il POST viene rifiutato
e nulla viene scritto — verificato che l'indice resti vuoto dopo il tentativo.

## Regressione

Tutte le suite precedenti rieseguite — sedici via HTTP e undici PHP — zero
fallimenti. L'archivio resta integro dopo l'import, verificato con lo strumento
della fase 9.

## Cosa questi controlli **non** coprono

- **File grandi.** Il limite è 2000 righe; le prove ne usano nove. Tempo di
  esecuzione e memoria su un catasto reale da importare non sono misurati, ed è
  proprio il caso d'uso per cui la funzione esiste.
- **Solo gli ipogei.** Non si importano risorse, esplorazioni, bibliografie o
  serie di misure: quelle hanno già i loro percorsi di caricamento. Un import
  completo di un catasto altrui richiederebbe molto di più.
- **Nessun aggiornamento di schede esistenti.** L'import crea, non modifica: è
  deliberato, ma significa che correggere in massa dati già inseriti resta un
  lavoro manuale.
- **La codifica del file non viene rilevata**: si assume UTF-8, col BOM
  riconosciuto. Un CSV salvato in ANSI da un Excel italiano porterebbe dentro
  accenti sbagliati senza che nulla lo segnali.
- **Nessun annullamento.** Un import sbagliato si disfa cancellando le schede
  una per una. La pagina insiste sul backup preventivo, ma non lo impone.
