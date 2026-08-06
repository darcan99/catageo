# Prove della fase 7b — bibliografia e catalogo delle opere

Verifiche eseguite il **2026-08-06** su CATAGEO **0.10.0**, PHP 8.2 (server
integrato) su Windows 11.

| Suite | Cosa esercita |
|---|---|
| `prova-bibliografia.php` | Le librerie `Opere` e `Bibliografia` da riga di comando: 67 controlli |
| `prova-bibliografia.ps1` | Le pagine via HTTP: 102 controlli |

Entrambe passano per intero.

## Cosa è stato verificato

### Il catalogo generale esiste per non ripetersi

Una monografia che descrive quaranta cavità si censisce una volta in
`dati/bibliografia_generale.xml` e si cita quaranta volte. Correggerne
l'editore costa una correzione, non quaranta.

L'elenco inverso — quali ipogei citano un'opera — **non è memorizzato**: si
ricava scorrendo le sezioni. Un elenco memorizzato sarebbe una seconda verità da
tenere allineata, e prima o poi non lo sarebbe.

### Tre forme di voce, e il tipo decide cosa si scrive

Riferimento al catalogo, fonte propria dell'ipogeo, risorsa in rete. La prova
verifica sul file che **una voce `link` non porti il fascicolo** e che una voce
`riferimento` non porti gli autori: il file deve restare leggibile a mano, e
venti elementi vuoti sono rumore.

Il tipo è un **attributo**, non un elemento: si vede aprendo il file, senza
scorrerlo fino in fondo.

### L'integrità referenziale regge in entrambe le direzioni

Un'opera citata **non si può cancellare**; una mai citata sì. Una citazione che
punta a un'opera sparita — cosa che può succedere solo intervenendo a mano sui
file — **resta in elenco e lo dichiara** invece di sparire: perdere la citazione
significherebbe perdere l'unica traccia che quella fonte era stata registrata.
L'export BibTeX la salta senza rompersi.

`Opere::citataDa()` **non usa la scorciatoia dell'indice CSV**, che pure sarebbe
disponibile. Da quella ricerca dipende il rifiuto di cancellare un'opera citata,
e un indice rimasto indietro lascerebbe cancellare la fonte che quaranta schede
citano. Il costo resta contenuto perché su un ipogeo senza bibliografia la
lettura si ferma a un `is_file()`. Per lo stesso motivo quella ricerca ignora il
filtro di visibilità: un'opera citata da una scheda riservata deve risultare
citata comunque, altrimenti chi non vede quella scheda potrebbe cancellarle la
fonte sotto i piedi. La vista dei collegamenti, che invece si mostra all'utente,
il filtro lo applica.

### BibTeX

L'export esiste perché CATAGEO **non impone uno stile bibliografico
normalizzato**: chi deve applicarne uno lo fa con lo strumento che già usa.

Verificato: `&`, `%`, `$`, `#`, `_`, `~`, `^` e le graffe sono protetti; gli
autori diventano `and`; un articolo porta `issn` e un libro `isbn`; una tesi
porta `school` e una relazione `institution`, che BibTeX esige e senza cui molti
strumenti rifiutano la voce; i link **non** producono una voce, perché una
scheda di catasto non è una pubblicazione.

Le chiavi che collidono — stesso autore, stesso anno, stessa prima parola —
vengono suffissate: BibTeX rifiuta un file con chiavi duplicate, e meglio
`colli1990grottte`/`colli1990grotteb` che un file che non compila. La prova crea
apposta due opere che collidono e verifica che l'export resti valido.

La chiave salta l'articolo iniziale: «Il cunicolo…» va sotto `cunicolo`, che è
la parola per cui la voce verrà cercata.

### Verifica dei collegamenti

L'esito si registra **anche quando è negativo**: sapere che un link è rotto vale
più che non sapere nulla, ed è ciò che spinge ad archiviarne una copia. La prova
verifica che l'esito negativo compaia in rosso nella pagina e che **sopravviva a
una modifica del testo della voce** — la redazione non deve cancellare una
verifica fatta.

## Due difetti trovati qui

**Una rilevanza vuota faceva fallire il salvataggio.** Il modulo manda tutti i
campi, compresi quelli che non riguardano il tipo scelto; un `array_merge` con
una stringa vuota vince sul valore predefinito; lo schema ammette solo i tre
valori del vocabolario. Risultato: salvare una voce senza indicare la rilevanza
produceva un errore di validazione XSD al posto di un dato mancante innocuo. Ora
il valore si riporta al riposo in scrittura.

Il difetto era **passato inosservato al primo giro** perché la chiamata di prova
scartava l'esito con `$null = …`. La riga ora asserisce, e asserisce proprio il
caso senza rilevanza.

**`n_biblio` era sempre zero.** L'indice generale conta i file presenti nella
cartella di una sezione, escludendo l'XML di indice. La bibliografia è l'unica
sezione **senza file per voce** — una fonte è solo metadato — quindi l'unico file
presente era proprio quello escluso. Ora per `BB` si contano le voci dentro
l'indice.

Da notare: `Opere::citataDa()` e `Bibliografia::tuttiILink()` erano già stati
scritti senza dipendere da `n_biblio`, e per questo hanno continuato a
funzionare mentre la colonna era sbagliata.

## Un difetto dell'harness, non dell'applicativo

Tutti i controlli sull'export BibTeX fallivano. Causa: con un `Content-Type` che
PowerShell non riconosce come testo — qui `application/x-bibtex` — 
`Invoke-WebRequest` restituisce `Content` come array di byte, e il cast a stringa
produce l'elenco dei valori numerici. Il file era corretto dall'inizio.

È la stessa lezione già annotata per la misura dei contrasti: **prima di credere
a un fallimento, verificare lo strumento**.

## Regressione

Tutte le suite precedenti rieseguite: `prova-web`, `prova-fase2`,
`prova-fase2b`, `prova-fase3`, `prova-appartenenze`, `prova-mappa`,
`prova-risorse`, `prova-rilievi`, `prova-utm-web`, `prova-esplorazioni`, più i
controlli PHP. Zero fallimenti. Serviva davvero: la correzione di `n_biblio`
tocca l'indice generale, che è letto da quasi ogni pagina.

## Cosa questi controlli **non** coprono

- **L'alternanza dei blocchi nel modulo.** `catageo-bibliografia.js` mostra i
  soli campi del tipo scelto; la prova verifica che i blocchi siano marcati e
  che lo script sia caricato, non che l'alternanza avvenga: servirebbe un
  browser. Senza JavaScript il modulo resta usabile — è più lungo, non rotto,
  perché è il server a scrivere solo i campi previsti dal tipo.
- **La verifica automatica dei collegamenti.** L'esito si registra a mano da
  interfaccia. Interrogare davvero gli URL richiede chiamate in uscita, che molti
  hosting economici bloccano: è lavoro da fase 9, con degradazione dichiarata.
- **Un file `.bib` compilato davvero.** L'export è verificato per struttura e per
  protezione dei caratteri, ma non è stato dato in pasto a LaTeX né importato in
  Zotero.
- **Cataloghi grandi.** `Opere::citataDa()` scorre tutti gli ipogei. Provato su
  due: il costo reale su migliaia non è misurato.
- **Gli identificativi delle opere si riusano.** `Anagrafica` deriva il prossimo
  id dal massimo presente, quindi cancellando `OP012` il successivo torna a
  essere `OP012`. In applicativo il danno è chiuso, perché un'opera citata non si
  può cancellare; ma un riferimento cartaceo a `OP012` non sarebbe più
  affidabile. Vale per tutte le anagrafiche, non solo per le opere.
- **ISBN e DOI non sono verificati** nella forma: sono campi liberi. Un ISBN con
  la cifra di controllo sbagliata viene accettato.
