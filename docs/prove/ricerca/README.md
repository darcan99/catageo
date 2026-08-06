# Prove della fase 8 — ricerca ed esportazione

Verifiche eseguite il **2026-08-06** su CATAGEO **0.13.0**, PHP 8.2 (server
integrato) su Windows 11.

| Suite | Cosa esercita |
|---|---|
| `prova-geo.php` | Distanze e riquadri: 34 controlli |
| `prova-ricerca.ps1` | Ricerca, viste ed export via HTTP: 94 controlli |

Entrambe passano per intero.

## Il calcolo geografico, verificato contro qualcosa di indipendente

I valori attesi non vengono dalla stessa formula che si sta provando — quello
dimostrerebbe solo che il codice è coerente con sé stesso — ma da **distanze
note** fra località reali e da **proprietà geometriche** verificabili
altrimenti:

- Colosseo–Vaticano ≈ 3,4 km; Roma–Milano ≈ 477 km;
- un grado di latitudine vale ≈ 111,2 km ovunque;
- un grado di longitudine si accorcia col **coseno della latitudine**: a 41° il
  valore calcolato coincide con `111195 × cos(41°)` entro mezzo chilometro su
  ottantaquattro.

Più i casi che rompono l'aritmetica: due punti coincidenti devono dare zero e
non `NaN` (l'`asin` di un argomento appena sopra 1 lo produrrebbe), e la
distanza dev'essere simmetrica.

## Un difetto reale: il riquadro era tangente, non contenente

La ricerca per raggio fa un pre-filtro con un riquadro rettangolare — quattro
confronti su una riga di CSV — e solo poi calcola la distanza esatta sui
candidati. **Il pre-filtro non deve mai escludere qualcosa che il calcolo esatto
includerebbe**: un candidato di troppo viene poi scartato, uno di meno sparisce
in silenzio.

La prova campiona il cerchio in 120 direzioni, a quattro latitudini e tre raggi,
e verifica che nessun punto sfugga al riquadro. Falliva in **due punti su 120**,
sempre gli stessi: nord e sud esatti.

Il riquadro risultava **esattamente tangente** al cerchio, non più grande come
il commento dichiarava. Bastavano tre millimetri di scarto — dovuti al fatto che
la prova costruiva i punti con un raggio terrestre diverso di due parti per
milione — perché un punto sul bordo cadesse fuori. In una ricerca vera lo stesso
succederebbe a una cavità proprio sul limite del raggio, o a coordinate calcolate
con un altro modello di ellissoide.

Corretto aggiungendo un margine di sicurezza al raggio prima di ogni conversione
(un millesimo più un metro): irrilevante per il costo del filtro, sufficiente
contro qualunque discrepanza.

Provati anche i casi limite in cui il riquadro non è più un intervallo: vicino ai
poli e a cavallo dell'antimeridiano si rinuncia al filtro in longitudine invece
di applicarne uno sbagliato.

## Cosa è stato verificato sulla ricerca

**Tre passate, dalla più economica alla più costosa.** Indice CSV in streaming
(testo, catalogo, attributi, presenze, intervalli, pre-filtro a riquadro); poi la
distanza esatta; poi i criteri specialistici — grandezza misurata, specie,
periodo, vincolo — aprendo i file di sezione **dei soli sopravvissuti**. La
pagina dichiara quante schede ha esaminato e quante ne ha aperte.

**Il codice porta alla scheda.** È il caso d'uso più frequente: chi ha in mano
una pubblicazione digita un codice. Funziona anche con un codice **dismesso da
una migrazione**, e la scorciatoia si disattiva se ci sono altri criteri —
portare altrove chi sta costruendo una query gli farebbe perdere il lavoro.

**Un ipogeo senza il dato non compare** quando si filtra su quel dato. È una
scelta: includerlo riempirebbe i risultati di schede di cui non si sa nulla
proprio sul criterio scelto. La prova lo verifica con una cavità senza misure.

**La virgola decimale** della tastiera italiana è accettata negli intervalli.

**Tre viste** — tabella, schede, mappa — con la stessa query. La mappa riusa la
vista di elenco già esistente, alimentata dall'endpoint di export: il percorso
"tracciato" avrebbe disegnato pallini senza informazioni.

**Export CSV, GeoJSON e KML.** Il CSV porta il BOM, senza il quale Excel apre un
UTF-8 come ANSI e gli accenti arrivano illeggibili. Il taglio a 2000 risultati è
dichiarato **dentro il file**, non solo nella pagina: chi lo apre domani non ha
più davanti l'avviso dell'interfaccia.

### Riservatezza

Verificato che un USR non trovi schede riservate né bozze, che il suo export non
le contenga, e che le **coordinate offuscate escano offuscate anche dal CSV** —
un export non deve essere la via di servizio con cui si ottengono le posizioni
esatte. Il KML lo scrive nel segnaposto: chi apre il file in Google Earth deve
sapere che il punto non è dove sembra, altrimenti concluderà che il catasto è
impreciso.

Una ricerca per raggio su coordinate offuscate **dichiara** che la distanza
mostrata è approssimata.

La ricerca per specie **non considera le colonie non consultabili**: comparire
fra i risultati rivelerebbe l'esistenza di un roost che l'utente non ha diritto
di conoscere.

## Un difetto nel codice della pagina

`Undefined array key "vista"`: la condizione usava `?? 'tabella'` ma il ramo
positivo accedeva alla chiave nuda. Con la chiave assente — cioè al primo
ingresso nella pagina — la condizione era vera e l'accesso falliva. La pagina
dava 500 a chiunque.

E uno nel motore: `Ricerca::risolviCodice()` leggeva `codice_corrente`, chiave
che esiste nella riga grezza del CSV ma **non** in ciò che `IndiceCodici::risolvi()`
restituisce — quel metodo mette già il codice corrente in `codice`. Effetto: la
scorciatoia codice→scheda non scattava mai.

## Tre trappole dell'harness

Vale la pena registrarle: nessuna delle tre era un difetto del prodotto, e tutte
e tre lo imitavano.

1. **Due sessioni parallele sulla stessa istanza.** Altri lavori in background
   usavano la stessa cartella di prova e la stessa porta 8123, azzerando i dati a
   metà suite: trentacinque fallimenti che non dicevano nulla sul codice. Da qui
   `esegui-suite-isolata.ps1`, che usa porta e istanza dedicate, e le suite che
   leggono `CATAGEO_BASE`/`CATAGEO_ISTANZA` dall'ambiente.
2. **`-MaximumRedirection 0` resta appiccicato alla sessione.** In PowerShell 5.1
   quel parametro viene memorizzato nella `WebRequestSession`: da quel momento
   *ogni* POST che risponde con un reindirizzamento fallisce. Le operazioni
   riuscivano davvero — il filtro trovava la colonia creata — ma la risposta
   seguita non arrivava mai. Ora si seguono sempre i reindirizzamenti e si
   verifica **dove** si è finiti, che è anche ciò che conta per chi usa il sito.
3. **Nomi di campo indovinati.** La prova inviava `sviluppo` e `dislivello`,
   mentre il modulo vuole `sviluppoPlanimetrico` e `dislivelloNegativo`: i filtri
   sugli intervalli fallivano perché il dato non era mai stato salvato.

## Regressione

Tutte le suite precedenti rieseguite — tredici via HTTP e undici PHP — sul
codice che comprende anche le due lavorazioni parallele concluse in questa
sessione (estrazione delle eccezioni residue e XSD delle anagrafiche). Zero
fallimenti.

## Cosa questi controlli **non** coprono

- **Archivi grandi.** Le prove girano su sette ipogei. La scansione è in
  streaming e non carica l'indice in memoria, ma tempi e memoria su migliaia di
  schede non sono misurati — in particolare per «cerca anche nelle descrizioni»,
  che apre un XML per scheda.
- **Il costo dei criteri specialistici** è dichiarato in pagina ma non misurato:
  su un archivio grande, un filtro per specie combinato con criteri poco
  selettivi aprirebbe molti file.
- **La mappa dei risultati in un browser.** L'endpoint è verificato, il disegno
  no.
- **Il pulsante «usa la mia posizione»**: richiede una geolocalizzazione vera e
  un contesto sicuro, non provabile qui. Senza JavaScript la ricerca resta
  completa: si scrivono le coordinate a mano.
- **Nessuna paginazione.** Oltre 2000 risultati il taglio è dichiarato ma non c'è
  modo di vedere i successivi se non restringendo i criteri.
- **L'ordinamento non è cliccabile dalle intestazioni** della tabella: si sceglie
  dal modulo. Funziona, ma non è dove una persona lo cerca.
- **Nessuna azione massiva sulla selezione** (migrazione, stampa): è fase 8b.
