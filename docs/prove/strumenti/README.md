# Prove della fase 9 — strumenti di manutenzione

Verifiche eseguite il **2026-08-06** su CATAGEO **0.15.0**, PHP 8.2 (server
integrato) su Windows 11.

| Suite | Cosa esercita |
|---|---|
| `prova-strumenti.php` | `Integrita` e `Backup` su un'istanza usa-e-getta: 49 controlli |
| `prova-strumenti.ps1` | Le pagine via HTTP: 57 controlli |

Entrambe passano per intero. Le suite creano la propria istanza: qui si
cancellano file e si sporcano indici di proposito.

## L'integrità si prova rompendo l'archivio

Una verifica eseguita su un archivio sano dimostra soltanto che non trova nulla
— che è esattamente ciò che farebbe anche se fosse scritta male. Le prove
introducono **un guasto per volta**, controllano che venga segnalato, e poi
ripristinano:

- scheda XML ben formata ma **non valida** secondo lo schema (elemento
  obbligatorio tolto: il caso di chi corregge a mano e sbaglia);
- scheda **troncata**, non più leggibile come XML;
- scheda **mancante**;
- riga di indice per un ipogeo che non esiste sul disco;
- **contatore di serie riportato indietro**, cioè il caso in cui il prossimo
  censimento tenterebbe un codice già usato;
- risorsa **citata nell'indice ma assente** sul disco, e il caso opposto;
- **cartella fuori standard** nella cartella degli ipogei.

Verificato anche che, sistemato tutto, l'archivio torni a dichiararsi sano — un
controllo che senza questo passaggio potrebbe segnalare sempre qualcosa.

E che **nessun problema resti senza indicazione di cosa fare**: un elenco di
anomalie senza rimedi è solo un motivo di preoccupazione.

## Il difetto che valeva la fase

Lo strumento che deve trovare i file rotti **si fermava sul primo file rotto**.

`Integrita::verificaIpogeo()` chiamava `Ipogeo::trova()`, che su un XML
malformato solleva un'eccezione: un solo file troncato interrompeva l'intera
scansione. L'utente avrebbe visto una pagina di errore, senza sapere se il resto
dell'archivio fosse stato controllato — o peggio, avrebbe potuto concludere che
fosse a posto.

Ora ogni ipogeo si verifica dentro una rete, e l'interruzione diventa essa stessa
un problema segnalato, con il resto della scansione che prosegue. La prova
verifica esplicitamente che, con un XML troncato, il conteggio degli ipogei
esaminati resti completo.

## Un secondo difetto: due backup nello stesso secondo

Il nome porta data e ora **al secondo**. Due backup creati entro lo stesso
secondo avevano lo stesso nome, e `ZipArchive::OVERWRITE` sostituiva il primo
senza dire nulla: si perdeva un backup proprio mentre si credeva di averne fatti
due. Ora, se il nome è occupato, si aggiunge un contatore.

Trovato perché la prova creava tre backup di fila e ne contava due.

## Cosa è stato verificato sul backup

**Non si emette in streaming al browser** ma si scrive su file. Un archivio di
qualche gigabyte prodotto in streaming, su un hosting con un limite di tempo di
esecuzione, si interromperebbe a metà lasciando uno ZIP corrotto che *sembra*
buono. Scritto su disco, un file incompleto lo nota chi guarda l'elenco, non chi
tenta di ripristinarlo.

**Un backup non contiene i backup precedenti** — senza quell'esclusione il
secondo peserebbe il doppio del primo. Verificato sul contenuto dello ZIP.

**Il backup di un solo catalogo comprende anche anagrafiche e indici**: un
catalogo ripristinato senza i gruppi, gli esploratori e i vocabolari che le sue
schede citano sarebbe pieno di riferimenti a identificativi inesistenti.

**Dentro lo ZIP c'è un manifesto** con versione, data, autore e istruzioni di
ripristino — compreso il promemoria di ricostruire gli indici e la nota che i
dati restano leggibili con un editor di testo anche senza CATAGEO. Chi lo apre
fra cinque anni non deve doverlo dedurre.

Lo scarico esige il permesso di **manutenzione**, non quello di esportazione: un
backup contiene tutto il catasto, ubicazioni riservate comprese. Verificato che
un OPE non lo ottenga, e che un percorso con risalita venga rifiutato.

## Verifica dei collegamenti

Procede **a lotti di venti**: duecento richieste HTTP in una pagina superano
qualunque limite di tempo, e un lavoro interrotto a metà senza dirlo lascerebbe
mezzo archivio con esiti vecchi e mezzo con esiti nuovi.

Dove le chiamate in uscita non sono disponibili — molti hosting economici le
bloccano — **lo strumento lo dichiara e non fa nulla**. Segnare tutti i link
come irraggiungibili sarebbe un danno vero, perché quell'esito finisce scritto
nelle schede.

Si usa `GET` e non `HEAD`: troppi server rispondono 405 a una HEAD pur servendo
la pagina. Un 3xx seguito da un 2xx viene registrato come *spostato*, non come
raggiungibile: l'indirizzo salvato è vecchio e prima o poi smetterà di rimandare.

## Regressione

Tutte le suite precedenti rieseguite — quindici via HTTP e undici PHP — zero
fallimenti.

## Cosa questi controlli **non** coprono

- **L'import CSV massivo non è stato realizzato.** L'analisi (§9.14) lo elenca
  fra gli strumenti; scrivere molte schede da un file esterno è l'operazione più
  rischiosa dell'applicativo e merita il suo giro di prove, non la coda di una
  fase già ampia. L'export c'è dalla fase 8.
- **Archivi grandi.** Le prove girano su due ipogei. La verifica apre ogni
  scheda e ogni indice di sezione: su migliaia di ipogei costerà, e il tempo non
  è misurato. Il tetto di duecento problemi per categoria c'è ed è dichiarato,
  ma non è mai stato raggiunto in prova.
- **Backup di grandi dimensioni.** Il più grande prodotto qui è di pochi
  kilobyte. Tempo di esecuzione e memoria su un archivio con migliaia di foto
  non sono stati misurati — ed è proprio il caso in cui la scrittura su file
  invece che in streaming dovrebbe pagare.
- **Il ripristino non è automatizzato**: si estrae lo ZIP a mano dentro `dati/`.
  È deliberato — un ripristino automatico che sovrascrive l'archivio è
  l'operazione più pericolosa immaginabile — ma significa che il percorso di
  ripristino non è coperto da prove.
- **La verifica dei collegamenti non è stata esercitata contro URL veri**:
  l'istanza di prova non ha bibliografie con link, e la prova verifica solo che
  la pagina risponda e dichiari il caso vuoto o l'assenza di rete.
- **Nessuna verifica dei permessi di scrittura** sulle singole cartelle
  dell'archivio: quella sta nella diagnostica ambiente, che è cosa distinta.
