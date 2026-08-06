# Prove della fase 8b — migrazione fra cataloghi

Verifiche eseguite il **2026-08-06** su CATAGEO **0.14.0**, PHP 8.2 (server
integrato) su Windows 11.

| Suite | Cosa esercita |
|---|---|
| `prova-migrazione.php` | La libreria su un'istanza usa-e-getta: 68 controlli |
| `prova-migrazione.ps1` | Le pagine via HTTP: 64 controlli |

Entrambe passano per intero. La suite di libreria **crea la propria istanza** e
non gira sull'archivio dimostrativo: la migrazione sposta cartelle e consuma
contatori, e lascerebbe residui che le altre prove poi trovano.

## Perché la migrazione esiste

Un codice catastale viene citato in pubblicazioni cartacee, che non si possono
aggiornare. Spostare un ipogeo in un altro catalogo gli cambia il codice, e il
punto dell'intera operazione è che **il vecchio codice continui a risolvere**.

Verificato in tre modi: aprendo la scheda col codice dismesso, cercandolo (la
ricerca porta alla scheda e **avvisa** che il codice è cambiato), e leggendo la
traccia storica scritta in scheda con catalogo di origine, date e motivo.

Verificato anche che il vecchio codice **non venga riassegnato**: un nuovo
censimento nel catalogo di partenza riceve un codice diverso.

## L'anteprima deve mostrare quello che succederà

`CodiceCatastale::anteprima()` risponde sempre col prossimo progressivo della
serie: su cinque ipogei diretti allo stesso catalogo mostrerebbe **cinque volte
lo stesso codice**, e chi conferma crederebbe di aver visto ciò che accadrà.

`Migrazione::anteprima()` simula i contatori, uno per prefisso di serie, e salta
i codici che risulterebbero già presenti — come fa l'assegnazione vera,
altrimenti l'anteprima mostrerebbe un codice e ne verrebbe scritto un altro.

La prova verifica che tre ipogei diano **tre codici diversi e consecutivi**, e
che rifare l'anteprima non consumi nulla.

**Senza anteprima non si conferma**: il modulo di conferma porta i codici
previsti in un campo nascosto, e il POST li esige. Non è una difesa contro un
attacco — chi può migrare può comporre la richiesta a mano — ma contro il
collegamento salvato che salterebbe la schermata fatta apposta per fermarsi un
momento. Dopo la scrittura, i codici assegnati vengono **confrontati** con quelli
mostrati: se l'archivio è cambiato nel frattempo, la differenza è dichiarata.

## Cosa è stato verificato sull'operazione

**L'ordine dei passi.** Si sposta la cartella *prima* di rinominarla: se lo
spostamento fallisce, l'ipogeo resta intero al suo posto col suo codice.
Rinominare prima lascerebbe, in caso di errore, un ipogeo col codice del
catalogo sbagliato. Da lì in poi un errore riporta la cartella indietro e
rilancia, così lo stato intermedio non sopravvive.

**Il filesystem**: cartella spostata sotto il catalogo di destinazione,
rinominata col nuovo codice, sottocartelle di sezione rinominate, e un allegato
interno rinominato **col contenuto intatto**.

**Un lotto non è transazionale nel suo insieme**, ogni ipogeo lo è per conto
suo. La prova usa un lotto misto — uno valido e uno inesistente — e verifica che
il valido passi e il non valido sia dichiarato. Annullare tutto richiederebbe di
rimettere indietro cartelle già spostate e contatori già consumati, cioè più
movimento di file proprio quando qualcosa non funziona.

**Il tracciato** in `dati/_log/migrazioni.csv` registra anche i **fallimenti**,
col motivo: se un lotto si è fermato a metà, il file deve dire dove e perché. Un
tracciato non scrivibile non fa fallire una migrazione già avvenuta.

**Il limite di cento per lotto** non è tecnico ma di prudenza: un elenco più
lungo non lo si controlla davvero prima di confermare. Cento copie dello *stesso*
codice non lo fanno scattare, perché i duplicati collassano in una sola
operazione — provato entrambi i casi.

**Selezione dai risultati di ricerca**: caselle in tabella e pulsante «Migra i
selezionati», visibili solo a chi ha il permesso. Un OPE cerca e trova, ma non
vede né caselle né pulsante né la pagina.

## Tre trappole dell'harness

Nessuna era un difetto del prodotto; tutte lo imitavano.

1. **PowerShell 5.1 serializza un array dentro `-Body` come `System.Object[]`.**
   Il campo `codici[]` arrivava come la stringa letterale «System.Object[]», e
   l'applicativo rispondeva correttamente «Ipogeo non trovato: System.Object[]».
   Per i campi multivalore serve un corpo già codificato a mano.
2. **La pagina di migrazione senza anteprima non emette alcun token CSRF** —
   giustamente, non contiene moduli POST. La prova sul guardiano dell'anteprima
   falliva quindi sul token, provando una cosa diversa da quella voluta: ora il
   token si prende da una pagina che ne emette uno.
3. **Un filtro `ARC001 - *` sulle cartelle** pescava anche le dodici sottocartelle
   di sezione, che portano lo stesso prefisso. La cartella dell'ipogeo si
   riconosce perché contiene il file dei dati.

## Regressione

Tutte le suite precedenti rieseguite — quattordici via HTTP e undici PHP — zero
fallimenti. Serviva: `Ipogeo::migra()` sta nello stesso file di `crea`,
`aggiorna` ed `elimina`, e la ricerca è stata modificata per aggiungere la
selezione.

## Cosa questi controlli **non** coprono

- **Nessun backup preventivo dell'albero.** L'analisi (§5.5) lo prevede; qui non
  c'è. Il rollback in caso di errore riporta la cartella al suo posto, il che
  copre il caso frequente, ma non un guasto a metà rinomina dei file interni. Il
  backup per catalogo è previsto in fase 9 e quello è il posto giusto per
  costruirlo una volta sola.
- **Lotti grandi.** Provato fino a due ipogei per volta; il limite è cento. Tempi
  e comportamento su un lotto reale non sono misurati.
- **Nessuna riscrittura dei riferimenti interni** fra ipogei: `<collegamenti>`
  cita altri ipogei per codice, e dopo una migrazione quei riferimenti puntano a
  un codice storico. Continuano a risolvere — è proprio ciò che `codici.csv`
  garantisce — ma restano scritti col vecchio valore. È coerente con l'analisi,
  che dichiara l'impatto minimo perché i riferimenti interni sono per sigla, ma
  i collegamenti fra ipogei sono l'eccezione.
- **Concorrenza.** Due migrazioni simultanee sullo stesso ipogeo non sono state
  provate; i contatori delle serie sono sotto lock, lo spostamento no.
- **Le azioni massive diverse dalla migrazione** (stampa di una selezione) non
  ci sono: la selezione in ricerca porta solo qui.
