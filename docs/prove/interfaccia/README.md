# Verifica della leggibilità dell'interfaccia

I colori dell'interfaccia non sono stati scelti a occhio: sono stati **misurati**
come rapporto di contrasto fra i colori effettivamente calcolati dal browser,
tenendo conto delle trasparenze sovrapposte. È la seconda volta che un problema
di leggibilità viene segnalato dopo il rilascio, quindi da qui in poi si misura.

Il rapporto di contrasto è quello definito da WCAG 2.1: va da 1 (identici) a 21
(nero su bianco). Le soglie usate:

| Elemento | Soglia | Perché |
|---|---|---|
| Testo normale su fondo | **4,5:1** | WCAG AA |
| Testo grande (≥ 24 px, o ≥ 18,7 px in grassetto) | **3:1** | WCAG AA |
| Superfici adiacenti (pagina / scheda / intestazione) | nessuna soglia normata | si cerca la separazione più netta che resti sobria; sotto 1,2 la distinzione non si percepisce |

## Come rifare la misura

Le suite via HTTP non possono farla: il contrasto dipende dai colori *calcolati*,
che esistono solo nel browser. `prova-web.ps1` verifica la premessa strutturale
(nessun `card-header` reso trasparente, variabili definite nei due temi, superfici
neutralizzate in stampa); il resto si misura nel browser.

Nel browser, su ogni pagina e per ciascun tema, si scorrono gli elementi che
contengono testo, si ricostruisce il fondo effettivo risalendo l'albero e
componendo le trasparenze, e si segnalano i casi sotto soglia.

> **Trappola da conoscere: il tema deve arrivare dal server, non essere applicato
> dopo il render.**
>
> Quando `data-bs-theme` cambia *dopo* che la pagina è stata disegnata, leggendo i
> colori si ottiene un misto: il **tema precedente** per il testo e quello
> **nuovo** per i fondi. Risultano contrasti di 1,03 che non esistono. Aspettare
> un giro di eventi non basta e non è deterministico: la stessa tecnica ha dato
> risultati corretti su una pagina e falsi positivi su un'altra nella stessa
> sessione.
>
> Non basta nemmeno scrivere la preferenza in `localStorage` e ricaricare: il
> documento arriva dal server con il tema di `config.xml`, e poi `catageo.js`
> applica la preferenza locale — cioè cambia il tema dopo il render, ricreando
> esattamente la condizione del guasto. È così che una passata in tema scuro ha
> segnalato *sette* problemi inesistenti su ogni pagina.
>
> **Il modo affidabile** è imporre il tema **in `config.xml`**
> (`<sistema><tema>dark</tema>`) e **rimuovere** la preferenza locale
> (`localStorage.removeItem('catageo.tema')`), così il documento nasce già col
> tema giusto e nessuno lo cambia più. Per misurare molte pagine si usa un
> `iframe` fuori schermo che le carica in sequenza: stessa origine, quindi
> `contentDocument` è accessibile, e ogni pagina è un render vero.
>
> Questa trappola ha prodotto falsi positivi in **tre** passate distinte di
> questa verifica. Ogni volta i valori assurdamente bassi (1,03 – 1,5 su elementi
> che a occhio si leggono benissimo) sono stati il segnale che a sbagliare era lo
> strumento. È il motivo per cui la procedura è scritta qui.

## Scala delle superfici — 2026-08-05

I due temi hanno **logiche opposte**, ed è deliberato.

| | tema chiaro | tema scuro |
|---|---|---|
| Fondo pagina | `#ffffff` — **bianco** | `#0c0f12` |
| Fondo scheda | `#e6ddc9` — **più scura** | `#343b43` — più chiara |
| Fondo barre (navbar, piede) | `#ebe3d3` | `#2b3035` |
| Fondo intestazione | tinta scura al 14% | blu chiaro al 21% |
| **pagina / scheda** | **1,35** | **1,70** |
| **scheda / intestazione** | **1,25** | **1,42** |
| **pagina / navbar** | **1,24** | **1,44** |

I valori del tema chiaro sono quelli della tavolozza **sabbia**, la predefinita;
le altre tre stanno più sotto e sono equivalenti a meno di qualche centesimo.

Nel tema scuro la scheda è **più chiara** della pagina, come vuole la convenzione
delle superfici sollevate. Nel tema chiaro è **più scura**: il bianco fa da
margine e la scheda da tavolo di lavoro. Sono due modi diversi di dire la stessa
cosa, cioè «qui si lavora».

L'inversione del tema chiaro ha un effetto collaterale utile: nei form i campi
restano bianchi, quindi si staccano dal box che li contiene (1,34). Con la scheda
bianca erano bianco su bianco e si distinguevano solo per il bordo.

Progressione, su richiesta, in tre passi: prima si è staccata l'intestazione, poi
il box, poi si è invertito il tema chiaro.

| | chiaro | scuro |
|---|---|---|
| pagina / scheda, all'origine | 1,13 | 1,20 |
| primo intervento | 1,24 | 1,43 |
| secondo intervento | 1,45 | **1,70** |
| **terzo, con l'inversione** | **1,34** | 1,70 (invariato) |

Il rapporto **scheda / intestazione è tenuto fermo** a 1,26 e 1,42 in tutti i
passaggi: era già stato approvato, e alzarlo insieme al resto avrebbe fatto
perdere la gerarchia fra i due livelli. Ogni volta che il fondo delle schede si è
spostato è stato necessario ricalibrare l'opacità della tinta dell'intestazione
per mantenere lo stesso rapporto.

### Tavolozze del tema chiaro

Si scelgono dal menu **Aspetto** e si confrontano a vista in
[tavolozze.html](tavolozze.html), che usa il CSS reale dell'applicativo.

| Tavolozza | Scheda | bianco / scheda | scheda / intest. | testo / scheda | Nota |
|---|---|---|---|---|---|
| **sabbia** *(predefinita)* | `#e6ddc9` | 1,35 | 1,25 | 11,4 | roccia e carte topografiche |
| verde | `#d5e2d8` | 1,34 | 1,25 | 11,5 | vegetazione delle carte |
| azzurra | `#d1e0f1` | 1,34 | 1,26 | 11,5 | in tinta con il tema scuro |
| neutra | `#d3dae2` | 1,41 | 1,26 | 10,9 | grigio, senza tinte |

Tutte e quattro sono state misurate **separatamente** su otto pagine, imponendo
la tavolozza da `config.xml`: nessun elemento sotto soglia in nessuna. Il testo
sulla scheda resta fra 10,9 e 11,5:1, cioè molto oltre la soglia, quindi la
scelta è di gusto e non di leggibilità.

La neutra è la più staccata dal bianco (1,41) perché è l'unica senza tinta: a
parità di luminanza percepita, un grigio puro si allontana dal bianco più di un
colore. Non è un motivo per preferirla, la differenza è sotto la soglia in cui
conta.

### Chi sceglie, e dove finisce la scelta

| | Dove | Vale per |
|---|---|---|
| Predefinito dell'installazione | `config.xml`, `<sistema><tema>` e `<tavolozza>` | chi non ha ancora scelto |
| Scelta personale | menu **Aspetto**, salvata in `localStorage` | quel browser, finché non si cancella |

La preferenza **non** è legata all'utenza registrata: non è un dato del catasto,
è come uno preferisce vedere lo schermo che ha davanti, e sullo stesso archivio
da un altro computer può preferire altro. Un valore non ammesso in `config.xml`
viene ricondotto al predefinito da `Aspetto`, quindi un errore di battitura non
lascia la pagina senza tavolozza.

Le tavolozze agiscono **solo sul tema chiaro**. Il tema scuro ha una sua scala,
tarata a parte: moltiplicare le combinazioni significherebbe moltiplicare le
misure da rifare a ogni modifica. Il menu lo dichiara, così chi sceglie mentre è
in tema scuro sa perché non vede cambiare nulla — la spunta conferma comunque
che la scelta è stata registrata.

Oltre alla luminanza agiscono due leve che **non** toccano il contrasto del testo,
e sono quelle che danno il colpo d'occhio a parità di leggibilità:

- **bordo** più marcato (`#b3bfcc` chiaro, `#4a535d` scuro);
- **ombra** più evidente (`0 2px 5px` chiaro, `0 2px 6px` scuro).

## Il box su cui si sta lavorando

Nel form di censimento le sezioni sono nove. Il box che contiene il campo attivo
si accende: bordo nel colore d'accento e alone di 0,18 rem. Si accende **solo
dentro un form** (`form .card:focus-within`), perché in consultazione ogni clic su
un collegamento farebbe lampeggiare un box senza che questo significhi nulla.

L'evidenza è su bordo e alone, **non sul fondo**: cambiare il fondo sotto le dita
mentre si scrive sposta l'attenzione invece di orientarla, e cambierebbe il
contrasto del testo proprio nel punto in cui si sta leggendo.

Nel tema chiaro l'intestazione si **scurisce**, nel tema scuro si **schiarisce**:
in entrambi i casi con una tinta blu. Tingere senza cambiare la luminanza — un
blu chiaro su bianco, per esempio — produce una distinzione che sparisce per chi
non percepisce bene quella tinta, e che non si vede affatto in stampa in bianco
e nero.

## Testo — dopo le correzioni

Misurato sulla scheda di un ipogeo, che è la pagina più densa:

| Testo | chiaro | scuro |
|---|---|---|
| Titolo del box sull'intestazione | 16,7 | 9,1 |
| Etichetta del dato sulla scheda | 15,4 | 10,0 |
| Valore del dato sulla scheda | 21,0 | 13,0 |

## Difetti trovati e corretti

Tutti emersi dalla misura, non dall'occhio. I primi quattro erano **preesistenti**
e riguardavano componenti grigi di Bootstrap che usano il colore fisso `#6c757d`,
non adattato al tema; l'ultimo è stato **peggiorato** dalle intestazioni colorate.

| Elemento | Prima | Nota |
|---|---|---|
| `btn-outline-secondary` nell'intestazione di una scheda | **1,94** | praticamente illeggibile: è il caso peggiorato dalle intestazioni |
| `btn-outline-primary` (menu utente) | 2,96 | |
| `link-secondary` (footer) | 2,84 | |
| `dropdown-header` (menu utente) | 3,29 | |
| `text-body-tertiary` («—», «non dichiarata») | 3,72 / 3,12 | è informazione, non decorazione |
| `text-danger` (asterisco dei campi obbligatori) | 2,86 / 3,90 | dice che un campo non si può lasciare vuoto: va visto |
| Riquadro dell'installer | **1,03** | il body aveva `bg-body-tertiary`, finito a un passo dal colore della scheda |
| `btn-outline-danger` («Rimuovi dal catalogo») | 2,50 | emerso solo dopo aver schiarito le schede |
| Collegamenti nelle tabelle dell'elenco | 3,35 | il blu `#0d6efd` su una scheda non più bianca. Bootstrap 5.3 compone il colore dei link da `--bs-link-color-**rgb**`: impostare `--bs-link-color` non ha alcun effetto, e la prima correzione era quindi inefficace |
| Barra di navigazione e piede | 1,15 | con la pagina bianca il grigio `#f8f9fa` di `bg-body-tertiary` le rendeva quasi invisibili: il loro fondo ora viene dalla tavolozza |

**Ogni volta che si sposta una superficie, i colori che vi stanno sopra vanno
rimisurati.** Alzando il fondo delle schede al secondo giro sono ricaduti sotto
soglia quattro elementi che erano stati appena corretti — l'asterisco a 4,48, i
pulsanti in contorno a 4,19 e 4,43, le linguette a 4,43 — più il pulsante rosso
delle operazioni distruttive, che al primo giro non era mai stato sopra un fondo
così chiaro. Sono stati tutti ritarati: i pulsanti che stanno **fuori** da una
scheda hanno come riferimento il grigio della pagina, non il bianco del box, e
vanno più scuri di quanto sembri necessario.

## Difetti di impaginazione

Il contrasto è una misura di **colore**: non vede la geometria. Questo è il
primo difetto trovato qui che non riguarda i colori, ed è arrivato da una
segnalazione, non dalla misura.

### L'etichetta che usciva dal proprio riquadro — 2026-08-08

Nella scheda si leggeva `Rinolofo maggioprotetta`: la scritta dell'etichetta
verde «protetta» stava **18 px a sinistra** del proprio fondo colorato, sopra il
testo accanto.

La causa non è l'etichetta ma la riga che la contiene. Una voce di elenco
(`.catageo-voce-biblio`) ha un **rientro sporgente** — `padding-left: 1.6rem` con
`text-indent: -1.6rem` — che è la convenzione tipografica con cui si contano le
voci a colpo d'occhio in un elenco lungo. Il punto è che:

> `text-indent` **si eredita**, e agisce sulla prima riga di **ogni contenitore
> di blocco** che lo riceve. Un `.badge` di Bootstrap è `inline-block`, quindi è
> un contenitore di blocco: si prendeva il rientro negativo e lo applicava alla
> propria prima riga, cioè alla sua unica parola.

Il CSS aveva già l'azzeramento, ma per un **elenco di classi** (`p`,
`.catageo-dati-media`) che non comprendeva i badge — e l'elenco andava allungato
a ogni riquadro nuovo, con il difetto visibile solo guardando la pagina. Ora
l'azzeramento vale per tutti i discendenti: su un elemento in linea puro
`text-indent` non ha effetto, quindi azzerarlo non costa nulla.

Riguardava due punti: l'etichetta **«protetta»** delle specie in biospeleologia e
il **livello dei rischi** geologici. Nella scheda da stampare lo stesso rientro
c'è (`.stampa-avvisi li`) ma dentro quelle righe va solo testo, quindi lì il
difetto non esisteva.

**Come si misura.** Si confronta il rettangolo dell'elemento con quello del suo
contenuto, preso con un `Range`: se il testo comincia prima del bordo sinistro
della propria scatola, sta uscendo.

```js
var scatola = badge.getBoundingClientRect();
var r = document.createRange(); r.selectNodeContents(badge);
var testo = r.getBoundingClientRect();
// prima: scostamento -18 px    dopo: +8 px, cioè il padding
```

`prova-web.ps1` verifica ora la premessa strutturale: **ogni** regola con un
`text-indent` negativo deve avere accanto l'azzeramento sui discendenti. Non
sostituisce lo sguardo, ma impedisce che un rientro nuovo nasca già rotto.

## Esito finale

Nessun elemento sotto soglia, in **entrambi i temi**, su **dieci pagine** misurate
con il metodo definitivo (tema imposto da `config.xml`, preferenza locale rimossa,
pagine caricate in iframe): scheda dell'ipogeo, elenco, form di censimento, mappa,
cataloghi, vocabolari, diagnostica, pagina iniziale, pagina di accesso e
installer. Verificata a parte anche la pagina di errore permessi.

## Cosa questa verifica **non** copre

- **L'impaginazione.** Il contrasto misura i colori, non le posizioni: un
  elemento può avere un contrasto perfetto e stare sopra un altro. Il caso
  dell'etichetta uscita dal proprio riquadro, qui sopra, è arrivato da una
  segnalazione dopo il rilascio, non dalla misura. Non esiste una passata
  automatica equivalente per la geometria; ci sono controlli strutturali mirati
  sui casi già visti.
- La percezione reale: un rapporto di contrasto è una misura fisica, non un
  giudizio estetico né una prova di usabilità.
- Il daltonismo: la distinzione fra cavità artificiali e naturali sulla mappa è
  affidata al colore. Sulla scheda no, ma sulla mappa sì, e per ora l'unica
  attenuazione è che gli ingressi non praticabili si distinguono per il
  **tratteggio** e non per la tinta.
- Gli stati transitori (`:hover`, `:focus`, `:disabled`), misurati solo per i
  pulsanti a cui sono state cambiate le variabili. Del box attivo è stato
  verificato che bordo e alone cambino davvero al fuoco, non il loro contrasto.
- La resa su stampante reale: le regole `@media print` sono verificate nel CSSOM,
  non su carta.
