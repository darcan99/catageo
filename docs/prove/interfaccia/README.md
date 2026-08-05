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

> **Trappola da conoscere.** Cambiando `data-bs-theme` e misurando nello stesso
> tick si leggono i colori del **tema precedente** per il testo e quelli **nuovi**
> per i fondi: risultano contrasti di 1,03 che non esistono. Il tema va cambiato
> in una chiamata e misurato nella successiva. La prima passata di questa verifica
> è caduta esattamente lì e stava per far "correggere" problemi inventati.

## Scala delle superfici — 2026-08-05

| | tema chiaro | tema scuro |
|---|---|---|
| Fondo pagina | `#e2e7ec` | `#101418` |
| Fondo scheda | `#ffffff` | `#2c3238` |
| Fondo intestazione | blu scuro al 12% | blu chiaro al 20% |
| **pagina / scheda** | **1,24** (era 1,13) | **1,43** (era 1,20) |
| **scheda / intestazione** | **1,26** (era 1,10) | **1,43** (era 1,16) |

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

| Elemento | Prima | Dopo | Nota |
|---|---|---|---|
| `btn-outline-secondary` nell'intestazione di una scheda | **1,94** | 6,0 | praticamente illeggibile: è il caso peggiorato dalle intestazioni |
| `btn-outline-primary` (menu utente) | 2,96 | 5,8 | |
| `link-secondary` (footer) | 2,84 | 6,3 | |
| `dropdown-header` (menu utente) | 3,29 | 6,4 | |
| `text-body-tertiary` («—», «non dichiarata») | 3,72 / 3,12 | oltre 4,5 | è informazione, non decorazione |
| `text-danger` (asterisco dei campi obbligatori) | 2,86 / 3,90 | 5,6 | dice che un campo non si può lasciare vuoto: va visto |
| Riquadro dell'installer | **1,03** | 1,43 | il body aveva `bg-body-tertiary`, finito a un passo dal colore della scheda |

## Esito finale

Nessun elemento sotto soglia, in **entrambi i temi**, su: scheda dell'ipogeo,
elenco, form di censimento, mappa, cataloghi, diagnostica, pagina iniziale,
pagina di accesso, pagina di errore permessi e installer.

## Cosa questa verifica **non** copre

- La percezione reale: un rapporto di contrasto è una misura fisica, non un
  giudizio estetico né una prova di usabilità.
- Il daltonismo: la distinzione fra cavità artificiali e naturali sulla mappa è
  affidata al colore. Sulla scheda no, ma sulla mappa sì, e per ora l'unica
  attenuazione è che gli ingressi non praticabili si distinguono per il
  **tratteggio** e non per la tinta.
- Gli stati transitori (`:hover`, `:focus`, `:disabled`), misurati solo per i
  pulsanti a cui sono state cambiate le variabili.
- La resa su stampante reale: le regole `@media print` sono verificate nel CSSOM,
  non su carta.
