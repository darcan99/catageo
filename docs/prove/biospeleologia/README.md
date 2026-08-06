# Prove della fase 7d — biospeleologia e archeologia

Verifiche eseguite il **2026-08-06** su CATAGEO **0.12.0**, PHP 8.2 (server
integrato) su Windows 11.

| Suite | Cosa esercita |
|---|---|
| `prova-biospeleo.php` | Le librerie `Biospeleologia` e `Archeologia`: 101 controlli |
| `prova-biospeleo.ps1` | Le pagine via HTTP e la barra avvisi: 92 controlli |

Entrambe passano per intero.

## Il caso che vale la fase: il periodo che scavalca il capodanno

Uno svernamento va **dal 1 novembre al 31 marzo**. Trattato come un intervallo
ordinato darebbe un insieme vuoto, e l'avviso non comparirebbe mai proprio nei
mesi in cui serve. Il periodo si scrive `MM-GG`, senza anno, perché è
**ricorrente**: quando l'inizio è maggiore della fine, si è dentro se la data è
*dopo l'inizio oppure prima della fine* — non «e».

La prova esercita nove date: 1 novembre, 15 novembre, 31 dicembre, 1 gennaio,
14 febbraio, 31 marzo (dentro); 1 aprile, 15 giugno, 31 ottobre (fuori). Più un
intervallo che *non* scavalca (riproduzione, 15 maggio – 15 agosto) con gli
estremi inclusi e il giorno prima escluso, e il **29 febbraio**, che resta un
giorno legittimo perché la validazione usa un anno bisestile come riferimento.

## L'avviso oscurato: una decisione presa provando

Alla prima esecuzione l'avviso di periodo critico **non compariva**: la colonia
era riservata e il codice, per rispettare la riservatezza, la escludeva dal
calcolo degli avvisi.

Tecnicamente corretto, praticamente sbagliato. Chi programma un'uscita è proprio
la persona che deve sapere di non entrare, ed è quasi sempre chi non ha diritto
a vedere il roost. Tacere l'avviso è il contrario di ciò per cui questi dati si
raccolgono.

La soluzione non è né tacere né rivelare: **l'avviso compare a tutti, oscurato**.
Dice il periodo e la ragione — «La cavità ospita fauna protetta in periodo
critico, dal 1 novembre al 31 marzo» — e tace nome, specie e zona. È la stessa
informazione di un cartello di chiusura temporanea, che non dice dove sia la
colonia.

La prova verifica entrambe le forme: un utente che vede la colonia legge nome,
specie e prescrizioni; un utente che non la vede legge la versione oscurata e
la dichiarazione esplicita che il dettaglio non è consultabile.

## Altro che è stato verificato

### I conteggi ammettono la stima

Chi conta pipistrelli in uscita al crepuscolo produce quasi sempre un
intervallo. Il CSV accetta il numero esatto **oppure** `stima_min`/`stima_max`;
almeno uno dei due è obbligatorio, perché un conteggio senza consistenza non
dice nulla. Una stima con minimo maggiore del massimo viene rifiutata.

Il grafico della consistenza riusa quello dei dati scientifici, prendendo il
**centro della stima** dove manca il numero — e la pagina lo dichiara, invece di
far credere che sia un conteggio esatto.

La specie si ripete in ogni riga del CSV: una colonia mista cambia composizione
nel tempo, e il file deve dire quale specie è stata contata *quel giorno*.

### Le caselle non spuntate

`specieProtetta` ed `endemismo` non arrivano nel POST quando sono deselezionate.
Senza normalizzazione, togliere la spunta non avrebbe avuto effetto. La prova lo
verifica **sul file**, non sulla pagina.

### La riservatezza nasce prudente

Una colonia salvata senza indicare la riservatezza nasce **riservata**, non
pubblica: l'ubicazione di un roost è un dato sensibile, e il caso in cui
l'utente non sceglie dev'essere quello che protegge.

### Archeologia

Datazioni **negative** per le date avanti Cristo (`-27` è il 27 a.C.), rifiutate
se invertite o discorsive. Periodi secondari e funzioni successive multipli,
perché un cunicolo romano riusato come ricovero antiaereo appartiene a due
epoche. Le righe vuote del modulo delle funzioni non producono voci.

Il **vincolo** alimenta un avviso che compare sia nella pagina di archeologia
sia nella scheda dell'ipogeo. Un vincolo dichiarato ma non dettagliato avvisa lo
stesso, dicendo che il dettaglio manca: meglio un avviso incompleto che nessun
avviso.

### Una sola sorgente per gli avvisi

La scheda dell'ipogeo aveva già una barra avvisi propria. Invece di affiancarne
una seconda, i nuovi avvisi entrano in quella esistente prendendoli da
`catageoAvvisiDi()` — la stessa funzione che li produce nelle due pagine di
sezione. Un avviso che compare in due punti su tre è un avviso che non c'è.

Gli avvisi `danger` precedono i `warning`: se una colonia è in letargo e la
cavità è anche vincolata, la prima cosa da leggere è quella che impedisce di
entrare oggi.

## Un difetto corretto: tre colonne d'indice sempre sbagliate

`ha_chirotteri`, `ha_archeologia` e `periodo_arch` erano rispettivamente sempre
`0`, sempre `0` e sempre vuota — stessa causa già trovata per `n_biblio` in fase
7b: il conteggio scorre i **file** della cartella, e queste sezioni non hanno
file per voce.

`ha_chirotteri` è ora vero solo se esiste davvero una **colonia**: la sezione può
contenere solo osservazioni di invertebrati, e una ricerca per chirotteri non
deve restituirla.

## Prove dell'harness, di nuovo

Tre asserzioni fallivano per difetti miei, non del codice: due cercavano un
testo che il markup manda a capo (`>VU<`, `non consultabil`), la terza cercava
il nome della specie **in tutta la pagina** invece che dentro l'avviso — e lo
trovava in un'osservazione faunistica, che non ha riservatezza propria ed è
legittimamente pubblica.

## Regressione

Tutte le suite precedenti rieseguite (dodici via HTTP più dieci controlli PHP):
zero fallimenti. Serviva: la correzione delle colonne tocca l'indice generale, e
la barra avvisi tocca la scheda.

## Cosa questi controlli **non** coprono

- **Le osservazioni non hanno riservatezza propria.** Un'osservazione di
  *Rhinolophus ferrumequinum* resta visibile a tutti anche quando la colonia di
  quella specie è riservata. È coerente con l'analisi (§6.14 dà riservatezza
  alle sole colonie) e difendibile — osservare una specie non è localizzare un
  roost — ma va saputo.
- **Gli identificativi delle osservazioni si riusano.** `OS001` cancellata, la
  prossima torna `OS001`. Le colonie no, perché il loro numero nomina un file.
  Nessuna sezione cita un'osservazione per codice, quindi oggi è innocuo.
- **Il periodo critico non genera avvisi nei risultati di ricerca**: l'analisi
  lo prevede, ma la ricerca è fase 8. Oggi l'avviso c'è nella scheda e nelle due
  pagine di sezione.
- **Nessuna verifica incrociata fra osservazioni e colonie**: si può registrare
  una colonia di una specie mai osservata, e viceversa. È voluto — i due dati
  hanno origini diverse — ma nessun controllo lo segnala.
- **Determinazione tassonomica libera.** Nessun elenco chiuso di specie, come da
  punto aperto §17.4 dell'analisi: un nome scientifico scritto male viene
  accettato.
- **Conteggi molto lunghi.** La prova arriva a due conteggi per colonia; una
  serie ventennale di monitoraggio non è stata provata.
- **Il grafico in un browser**: verificato come struttura, non guardato.
