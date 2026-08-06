# Prove della fase 7c — dati scientifici

Verifiche eseguite il **2026-08-06** su CATAGEO **0.11.0**, PHP 8.2 (server
integrato) su Windows 11.

| Suite | Cosa esercita |
|---|---|
| `prova-scientifici.php` | Le librerie `Scientifici` e `Grafico`: 88 controlli |
| `prova-scientifici.ps1` | Le pagine via HTTP, incluso l'upload: 90 controlli |

Entrambe passano per intero. La suite di libreria è stata eseguita **due volte
di fila** per verificare che non dipenda da un archivio vergine.

## Cosa è stato verificato

### Due file per sezione, e perché

Un XML descrive punti di misura, strumenti e serie; un CSV per serie contiene le
letture. Un datalogger orario produce ottomila righe l'anno: dentro un XML
sarebbero illeggibili e costose da riscrivere a ogni aggiunta, mentre un CSV si
accoda senza rileggerlo e si apre in un foglio di calcolo.

Il CSV **ripete in ogni riga** strumento, unità e provenienza. È
denormalizzazione voluta: un CSV estratto dall'archivio e aperto da solo deve
restare comprensibile senza il suo XML. La prova lo verifica sul file.

### Le letture sbagliate non si cancellano

Si marcano: `valido`, `sospetto`, `anomalo`, `scartato`. La prova registra una
lettura anomala con la nota «batteria scarica» e verifica che **resti nel file**
pur non entrando nelle statistiche. In un monitoraggio pluriennale la
cancellazione silenziosa di un dato scomodo è il modo più rapido per rendere
inutilizzabile una serie.

Un **valore vuoto è ammesso** ed è un'informazione diversa dall'assenza di riga:
lo strumento c'era e non ha misurato. La pagina lo scrive «non misurato» invece
di lasciare una cella vuota.

### Riconoscimento delle date — il caso che conta

`03/04/2026` viene letto come **3 aprile**, non 4 marzo. Non si usa
`strtotime()`, che sceglierebbe il mese all'americana e sbaglierebbe undici
giorni su dodici in un archivio italiano. Provati nove formati, compresi anni a
due cifre (finestra 70–99 → Novecento) e date impossibili come `31/02/2026`, che
vengono rifiutate invece di scivolare al 3 marzo.

### Numeri con la virgola

`412,5` → 412.5. `1.234,5` → 1234.5. `1,234.5` → 1234.5. La distinzione si fa
guardando **quale separatore viene per ultimo**: scambiarli cambierebbe il
valore di mille volte.

### Importazione da datalogger

Due passi: si carica il file, si vede un'anteprima, si dichiara quale colonna è
cosa. Nessun riconoscimento automatico può indovinare come uno strumento ha
nominato le colonne, e un'importazione che sbaglia in silenzio è peggio di una
che chiede. Le colonne vengono comunque **suggerite** per nome, così il caso
frequente costa una conferma.

Provati due file veri per forma:

- **anglosassone**: virgola come separatore, BOM iniziale, timestamp unico per
  data e ora. Il BOM non finisce nel nome della prima colonna (era il difetto
  già trovato in fase 2 sugli indici CSV, qui verificato di nuovo su un percorso
  diverso); l'ora viene estratta dal timestamp;
- **italiano**: punto e virgola, date `gg/mm/aaaa`, virgola decimale, più due
  righe illeggibili. Le due righe vengono **scartate e dichiarate col motivo**,
  non perse in silenzio.

Il separatore si deduce contando le occorrenze: sbagliarlo produrrebbe una sola
colonna con dentro tutto.

### Grafico SVG generato dal server

Nessuna libreria JavaScript di charting: il vincolo di zero dipendenze vale
anche qui. Un grafico costruito nel server si stampa (esiste già nel documento),
si vede senza JavaScript, e non aggiunge un megabyte di vendor da aggiornare per
disegnare una spezzata.

Verificato: SVG **XML ben formato**, `aria-label` presente, **nessun colore
fisso** nel markup — li mette il CSS, così il grafico segue tema e tavolozza
invece di restare l'unico riquadro chiaro in una pagina scura.

Casi limite provati: serie vuota e serie con un solo punto non producono
grafico; una **serie piatta** (minimo uguale al massimo) lo produce senza
coordinate `NaN`, perché la scala riceve un margine artificiale.

**La riduzione conserva i picchi.** Cinquemila punti diventano novecento, ma non
per media: ogni intervallo contribuisce con il proprio minimo e massimo,
nell'ordine in cui compaiono. Una media mobile leviga proprio ciò che in una
serie ambientale porta l'informazione — la piena che allaga il cunicolo, il
massimo di radon in estate. La prova costruisce una serie di tremila valori
costanti con **un solo picco isolato** e verifica che il picco sopravviva. Il
taglio è dichiarato sul grafico stesso: «5000 letture ridotte a 900 punti».

### Riservatezza propria della serie

Indipendente da quella dell'ipogeo e prevalente: una cavità pubblica può
ospitare un monitoraggio che non va divulgato. Verificato che un utente USR non
veda la serie riservata in elenco, non ne apra la pagina, e riceva **403** sul
CSV, mentre ADM lo scarica.

### Integrità referenziale

Un punto di misura **usato da una serie non si può togliere**: la serie
riferirebbe un luogo che non esiste più, e una misura senza luogo non è
confrontabile con nulla. Una serie rimossa porta il CSV in `_rimossi`, mai
cancellato: un monitoraggio pluriennale è un dato che non si rifà.

## Un difetto reale: errore fatale al posto del messaggio

`throw new ScientificiEccezione(...)` in un ramo che si raggiunge **prima di
qualunque uso della classe `Scientifici`** produceva un errore 500. L'autoload
risolve una classe per file: l'eccezione era dichiarata dentro `Scientifici.php`,
PHP cercava `ScientificiEccezione.php`, non lo trovava, e il risultato era un
fatale invece del messaggio previsto.

La convenzione giusta era **già stabilita e documentata** in
`app/lib/XmlEccezione.php` («estratta dal file della classe che la solleva»), ma
non era stata seguita per le eccezioni introdotte nelle fasi 7, 7b e 7c. Le tre
sono ora in file propri.

Restano nella stessa condizione sei eccezioni preesistenti
(`CoordinateEccezione`, `IpogeoEccezione`, `ProiezioneEccezione`,
`RisorsaEccezione`, `TracciatoEccezione`, `UploadEccezione`). Oggi funzionano
solo perché chi le cattura ha quasi sempre già usato la classe che le contiene:
è un invariante fragile, non una garanzia.

## Due difetti dell'harness, non dell'applicativo

Vale la pena registrarli perché entrambi **imitavano** un difetto del codice:

- `` `u{FEFF} `` è sintassi di PowerShell **6+**. In 5.1 finiva nel file di prova
  come i cinque caratteri letterali `u{FEFF}`, quindi la prova sul BOM non
  provava il BOM. Ora il file si scrive con `UTF8Encoding($true)`.
- Un trattino lungo scritto in un `.ps1` letto come ANSI non corrisponde a
  quello della pagina. Le asserzioni evitano ora i caratteri non ASCII.

È la terza volta in questo progetto che uno strumento di misura viene scambiato
per un difetto del prodotto. La regola resta: **prima di credere a un
fallimento, verificare lo strumento**.

## Regressione

Tutte le suite precedenti rieseguite: `prova-web`, `prova-fase2`,
`prova-fase2b`, `prova-fase3`, `prova-appartenenze`, `prova-mappa`,
`prova-risorse`, `prova-rilievi`, `prova-utm-web`, `prova-esplorazioni`,
`prova-bibliografia`, più i controlli PHP. Zero fallimenti. Serviva: sono state
spostate tre classi di eccezione, cosa che tocca l'autoload di tutto
l'applicativo.

## Cosa questi controlli **non** coprono

- **Serie davvero grandi.** Il tetto di lettura è 200.000 righe ed è dichiarato
  in pagina quando scatta, ma la prova arriva a 51 letture nel web e 5.000 nel
  grafico. Memoria e tempi su un datalogger pluriennale non sono misurati.
- **Il grafico in un browser.** È verificato come XML e per struttura, non
  guardato: che le proporzioni siano leggibili su schermo stretto non è stato
  controllato.
- **Concorrenza sull'accodamento.** Due importazioni simultanee sulla stessa
  serie non sono state provate; il lock c'è sull'XML del descrittore, non sul
  CSV, che viene solo accodato.
- **Formati di datalogger reali.** Provati due file costruiti a mano su forme
  tipiche. Uno strumento vero può usare intestazioni multiriga, unità nella
  seconda riga, o separatori decimali diversi nella stessa colonna.
- **Il passo temporale non viene verificato.** `PT1H` è testo libero: nessuno
  controlla che le letture lo rispettino davvero.
- **File di sosta dell'import.** Vengono rimossi dopo l'importazione e alla
  sosta successiva, ma un utente che carica un file e poi abbandona la pagina ne
  lascia uno in `dati/tmp` fino al caricamento seguente. Non c'è ancora una
  pulizia periodica: è lavoro da fase 9.
- **Statistiche per periodo.** Il riepilogo è sull'intera serie; il taglio per
  intervallo di date previsto in analisi non è ancora offerto.
