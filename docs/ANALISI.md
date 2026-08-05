# CATAGEO — Catasto Ipogei

## Documento di Analisi

| | |
|---|---|
| **Progetto** | CATAGEO (CATAsto ipoGEi) |
| **Versione documento** | 1.2.0 |
| **Data** | 2026-08-05 |
| **Autore** | Dario Candela — darcan99@gmail.com |
| **Repository** | github.com/darcan99/catageo |
| **Stato** | Bozza per approvazione |

### Cronologia documento

| Versione | Data | Autore | Modifiche |
|---|---|---|---|
| 1.0.0 | 2026-08-03 | Dario Candela | Prima stesura |
| 1.1.0 | 2026-08-04 | Dario Candela | Cataloghi multipli con serie di codifica a contatori indipendenti e strumento di migrazione tra cataloghi; padding del progressivo a soglia minima e senza tetto; testi senza limiti di lunghezza; nuove sezioni su file dedicati (bibliografia, dati scientifici, biospeleologia, archeologia, geologia); apertura al censimento di ipogei esteri |
| 1.2.0 | 2026-08-05 | Dario Candela | Sistemi di riferimento e formati delle coordinate (D13) e conversione reale fra datum diversi (D14); stato di realizzazione della cartografia dopo la fase 4 (§7.2.1) e attributi dei layer (§7.2.2), con lo scostamento sull'astrazione del provider dichiarato in §7.1.1 |

---

## 1. Obiettivi

CATAGEO è un'applicazione web PHP per la **gestione di un catasto di cavità artificiali e naturali** (ipogei), progettata per essere installabile su un qualsiasi hosting condiviso economico, **senza database**.

Obiettivi guida, in ordine di priorità:

1. **Portabilità estrema** — funziona con solo PHP + filesystem. Nessun DBMS, nessun servizio esterno, nessuna CDN, nessun `composer install` obbligatorio in produzione.
2. **Dati leggibili e durevoli** — l'archivio deve essere navigabile e comprensibile anche con un file manager e un editor di testo, senza l'applicativo. I dati sopravvivono al software.
3. **Standard imposti** — nomenclatura di cartelle, file e codici rigidamente definita e validata dall'applicativo.
4. **Completezza documentale** — per ogni ipogeo: scheda dati, allegati, foto, video, rilievi 2D/3D, diari di esplorazione, bibliografia, dati scientifici, biospeleologia, archeologia, geologia.
5. **Adattabilità a catasti esistenti** — un'installazione ospita **più cataloghi** contemporaneamente, ciascuno con la propria regola di codifica e i propri contatori, così da poter accogliere archivi già in uso senza rinumerarli e da migrare ipogei da un catalogo all'altro conservando la memoria del codice di origine.
6. **Visualizzazione cartografica** — mappa con marker, layer WMS aggiuntivi, tracciati dei rilievi da KML.
7. **Manutenibilità** — codice PHP commentato, versionato, con cronologia in testata di ogni file.

### 1.1 Fuori scopo (versione 1.x)

- Multiutenza concorrente intensiva (l'archivio su file è ottimizzato per pochi redattori simultanei).
- App mobile nativa (l'interfaccia sarà responsive).
- Elaborazioni topografiche (CATAGEO archivia e visualizza i rilievi, non li calcola).
- Sincronizzazione multi-sede / replica.

---

## 2. Requisiti non funzionali e vincoli

| Vincolo | Scelta |
|---|---|
| Linguaggio | PHP **8.0+** (compatibilità mantenuta con 7.4) |
| Estensioni PHP | `dom`, `libxml`, `simplexml`, `mbstring`, `json`, `fileinfo`, `session` — tutte standard. `gd` **opzionale** (thumbnail; con fallback se assente) |
| Persistenza | Solo filesystem: XML per dati strutturati, CSV per indici, file binari per media |
| Database | Nessuno |
| Front-end | Bootstrap **5.3** self-hosted (nessuna CDN) |
| Cartografia | Leaflet self-hosted + tile OpenStreetMap (default) **e** Google Maps opzionale (vedi §7 e §16.1) |
| Viewer 3D | three.js self-hosted (vedi §9.4) |
| URL routing | Front controller con querystring (`index.php?p=…`) — **nessun mod_rewrite richiesto** |
| Dipendenze runtime | Zero librerie PHP esterne |
| Encoding | UTF-8 senza BOM per XML; UTF-8 **con** BOM per i CSV esportati (compatibilità Excel) |
| Lingua interfaccia | Italiano, con file di label centralizzato per future traduzioni |

---

## 3. Architettura logica

Architettura a tre strati, senza framework:

```
┌────────────────────────────────────────────────────────┐
│  VISTE (view/)  — template PHP + Bootstrap 5           │
├────────────────────────────────────────────────────────┤
│  CONTROLLER (pagine/) — un file per pagina funzionale  │
├────────────────────────────────────────────────────────┤
│  LIBRERIE (lib/) — dominio + accesso dati              │
│  Config · Catalogo · Auth · Ipogeo · Risorse · Indice  │
│  Ricerca · Geo · Xml · Csv · Upload · Log · Anagrafiche│
│  Misure · Bio · Archeo · Geologia · Biblio · Migrazione│
├────────────────────────────────────────────────────────┤
│  ARCHIVIO (dati/) — XML, CSV, media                    │
└────────────────────────────────────────────────────────┘
```

### 3.1 Principi di accesso ai dati

- **Nessuna scrittura diretta di XML a mano nel codice applicativo**: tutte le letture/scritture passano dalle classi `Xml` (wrapper su `DOMDocument`) e `Ipogeo`.
- **Scrittura atomica**: ogni salvataggio scrive su file temporaneo `*.tmp` nella stessa directory, poi `rename()` (atomico su stesso filesystem).
- **Lock**: `flock()` esclusivo su un lockfile per ogni operazione di scrittura sull'indice; lock sul singolo `Dati.xml` per la scheda.
- **Backup automatico pre-modifica**: copia del file in `[codice] - Storico/[codice] - Dati.[YYYYMMDD-HHMMSS].xml` (rotazione configurabile, default ultime 20 versioni). Questo dà *versioning gratuito* delle schede.
- **Validazione**: ogni XML di dominio ha uno schema XSD in `schemi/`; validazione in scrittura (bloccante) e comando di verifica integrità dell'intero archivio.
- **Testi senza limiti di lunghezza** (D6): descrizioni, storia, note, obiettivi, risultati e voci di diario non hanno alcun `maxlength`, né in interfaccia né negli XSD. I testi lunghi sono racchiusi in `CDATA` per non dover fare escaping dei caratteri di markup e restare leggibili in un editor. Conseguenze tecniche gestite: nessun `TRUNCATE` in salvataggio, `post_max_size` verificato dalla diagnostica e segnalato se troppo basso, elenchi e anteprime che mostrano un estratto calcolato a runtime (il testo integrale non viene mai accorciato su disco).
- **Serie temporali in CSV, non in XML** (D8): le misure scientifiche ripetute nel tempo vivono in file CSV appendibili, con l'XML a fare da descrittore. Un XML con 20.000 letture di datalogger sarebbe illeggibile e lento da riscrivere; un CSV si apre in Excel, si accoda in append senza rileggere il file e si grafica direttamente.

### 3.2 Indici

Leggere 3.000 file XML per ogni ricerca è insostenibile. Si introduce un livello di **indice denormalizzato in CSV**, rigenerabile in qualsiasi momento dai soli XML (l'indice è cache, **non** è la fonte di verità):

- `dati/_indice/ipogei.csv` — una riga per ipogeo con i campi di ricerca e mappa, **con la colonna `catalogo`**: l'indice è unico e globale, così la ricerca attraversa tutti i cataloghi in una sola scansione.
- `dati/_indice/esplorazioni.csv` — una riga per esplorazione.
- `dati/_indice/risorse.csv` — conteggi allegati/foto/video/rilievi per ipogeo.
- `dati/_indice/codici.csv` — mappa di **tutti** i codici mai assegnati (compresi quelli storici dopo una migrazione) verso il codice corrente e la cartella: garantisce che un riferimento cartaceo o un vecchio link continui a risolvere. Vedi §5.5.

L'indice viene aggiornato in modo incrementale ad ogni salvataggio e ricostruibile integralmente da `Strumenti → Ricostruisci indici` (funzione riservata ADM). Se l'indice manca, l'applicativo lo rigenera al primo accesso.

---

## 4. Struttura del filesystem

```
catageo/                                (root applicativo, webroot)
├── index.php                           front controller
├── .htaccess                           hardening (vedi §12)
├── LICENSE
├── README.md
├── CHANGELOG.md
├── app/
│   ├── bootstrap.php                   inizializzazione, autoload, sessione
│   ├── lib/                            classi di dominio
│   ├── pagine/                         controller di pagina
│   ├── view/                           template (layout, componenti, form)
│   └── lang/it.php                     etichette
├── assets/
│   ├── css/catageo.css
│   ├── js/catageo.js
│   └── vendor/                         librerie self-hosted
│       ├── bootstrap-5.3.x/
│       ├── bootstrap-icons-1.x/
│       ├── leaflet-1.9.x/
│       └── three-r1xx/
├── schemi/                             XSD di validazione
│   ├── config.xsd  catalogo.xsd  utenti.xsd  ipogeo.xsd
│   ├── esplorazione.xsd  risorse.xsd  bibliografia.xsd
│   ├── scientifici.xsd  biospeleologia.xsd
│   ├── archeologia.xsd  geologia.xsd
│   └── gruppi.xsd  esploratori.xsd  tipologie.xsd
├── docs/
│   ├── ANALISI.md                      (questo documento)
│   ├── STANDARD-DATI.md                specifica formale dei formati
│   ├── INSTALLAZIONE.md
│   └── MANUALE-UTENTE.md
├── config.xml.dist                     template di configurazione
├── config.xml                          config reale (NON versionata)
└── dati/                               ARCHIVIO (NON versionato)
    ├── utenti.xml
    ├── gruppi_speleologici.xml
    ├── esploratori.xml
    ├── tipologie.xml                   tassonomia natura/tipologia/sottotipologia
    ├── grandezze.xml                   grandezze misurabili e unità (§6.4)
    ├── periodi_storici.xml             cronologia per la sezione archeologica (§6.4)
    ├── bibliografia_generale.xml       opere citate da più ipogei (§6.12)
    ├── _indice/
    │   ├── ipogei.csv
    │   ├── esplorazioni.csv
    │   ├── risorse.csv
    │   └── codici.csv                  tutti i codici mai assegnati → codice corrente
    ├── _log/
    │   ├── accessi.csv
    │   ├── modifiche.csv
    │   └── migrazioni.csv              tracciato delle migrazioni tra cataloghi
    ├── _tmp/                           upload temporanei
    └── cataloghi/
        ├── LA - Catasto Ipogei del Lazio/
        │   ├── catalogo.xml            identità, serie di codifica, contatori
        │   └── ipogei/
        │       ├── LA297 - Grotta dei Ragni/
        │       │   ├── LA297 - Dati.xml
        │       │   ├── LA297 - Allegati/
        │       │   │   ├── LA297 - Allegati.xml
        │       │   │   └── LA297-AL001-Relazione tecnica 1998.pdf
        │       │   ├── LA297 - Foto/
        │       │   │   ├── LA297 - Foto.xml
        │       │   │   ├── LA297-FO001-Ingresso principale.jpg
        │       │   │   └── _mini/LA297-FO001-Ingresso principale.jpg
        │       │   ├── LA297 - Video/
        │       │   │   ├── LA297 - Video.xml
        │       │   │   └── LA297-VI001-Discesa pozzo.mp4
        │       │   ├── LA297 - Rilievi/
        │       │   │   ├── LA297 - Rilievi.xml
        │       │   │   ├── LA297-RI001-Pianta generale.pdf
        │       │   │   ├── LA297-RI002-Poligonale.kml
        │       │   │   └── LA297-RI003-Modello 3D.ply
        │       │   ├── LA297 - Esplorazioni/
        │       │   │   ├── LA297 - Esplorazioni.xml
        │       │   │   └── LA297-ES001-Prima ricognizione.xml
        │       │   ├── LA297 - Bibliografia/
        │       │   │   └── LA297 - Bibliografia.xml
        │       │   ├── LA297 - Scientifici/
        │       │   │   ├── LA297 - Scientifici.xml    descrittore serie e punti
        │       │   │   ├── LA297-SC001-Temperatura aria ingresso.csv
        │       │   │   ├── LA297-SC002-Radon sala grande.csv
        │       │   │   └── LA297-SC003-Portata sorgente interna.csv
        │       │   ├── LA297 - Biospeleologia/
        │       │   │   ├── LA297 - Biospeleologia.xml
        │       │   │   └── LA297-BI001-Conteggi chirotteri.csv
        │       │   ├── LA297 - Archeologia/
        │       │   │   └── LA297 - Archeologia.xml
        │       │   ├── LA297 - Geologia/
        │       │   │   └── LA297 - Geologia.xml
        │       │   └── LA297 - Storico/
        │       │       └── LA297 - Dati.20260804-181200.xml
        │       └── LA298 - Cunicolo di Via Latina/
        ├── RM-AC - Cavità Artificiali di Roma/
        │   ├── catalogo.xml
        │   └── ipogei/
        └── EST - Spedizioni all'estero/
            ├── catalogo.xml
            └── ipogei/
```

**Perché i cataloghi sono cartelle di primo livello** (D5): ogni catalogo diventa un'unità autonoma e trasportabile — si archivia, si consegna a un altro gruppo o si esclude da un backup semplicemente spostando una cartella. Il `catalogo.xml` interno lo rende **autodescrittivo**: non esiste un registro centrale dei cataloghi da tenere sincronizzato, l'applicativo li scopre scandendo `dati/cataloghi/*/catalogo.xml`. I contatori vivono dentro il catalogo, quindi due cataloghi non si contendono mai lo stesso lock in scrittura.

### 4.1 Regole di nomenclatura (normative)

| Elemento | Formato | Esempio |
|---|---|---|
| Cartella catalogo | `[sigla catalogo] - [nome catalogo]` | `LA - Catasto Ipogei del Lazio` |
| Descrittore catalogo | `catalogo.xml` (nome fisso) | `catalogo.xml` |
| Cartella ipogeo | `[codice] - [nome ipogeo]` | `LA297 - Grotta dei Ragni` |
| Scheda dati | `[codice] - Dati.xml` | `LA297 - Dati.xml` |
| Sottocartella di sezione | `[codice] - [Sezione]` | `LA297 - Foto` |
| Indice / contenuto di sezione | `[codice] - [Sezione].xml` | `LA297 - Foto.xml` |
| File risorsa | `[codice]-[SG][NNN]-[nome originale].[ext]` | `LA297-FO001-Ingresso.jpg` |
| Esplorazione | `[codice]-ES[NNN]-[titolo].xml` | `LA297-ES001-Prima ricognizione.xml` |
| Serie di misure | `[codice]-SC[NNN]-[nome serie].csv` | `LA297-SC002-Radon sala grande.csv` |
| Serie biospeleologica | `[codice]-BI[NNN]-[nome serie].csv` | `LA297-BI001-Conteggi chirotteri.csv` |
| Storico scheda | `[codice] - Dati.[YYYYMMDD-HHMMSS].xml` | `LA297 - Dati.20260804-181200.xml` |

**Sigle di sezione (`SG`)**, usate sia nei nomi dei file sia come identificatori interni citabili da altre sezioni:

| Sigla | Sezione | Contenuto |
|---|---|---|
| `AL` | Allegati | documenti, relazioni, autorizzazioni |
| `FO` | Foto | immagini |
| `VI` | Video | filmati o riferimenti a video esterni |
| `RI` | Rilievi | rilievi 2D/3D, KML, modelli |
| `ES` | Esplorazioni | diari esplorativi (un XML per esplorazione) |
| `BB` | Bibliografia | voci bibliografiche (id interni, nessun file per voce) |
| `SC` | Scientifici | serie di misure (un CSV per serie) |
| `BI` | Biospeleologia | osservazioni e conteggi (un CSV per serie di conteggi) |
| `AR` | Archeologia | evidenze archeologiche (id interni) |
| `GE` | Geologia | osservazioni e campioni geologici (id interni) |

**Progressivo (`NNN`)**: zero-padding a 3 cifre, indipendente per sezione e per ipogeo, **mai riutilizzato** anche a seguito di cancellazioni (il progressivo massimo mai assegnato è memorizzato nell'indice di sezione). Come per il codice catastale (§5.3) il padding è una **soglia minima, non un tetto**: superati i 999 elementi la numerazione continua a 4 e più cifre senza alcuna riconfigurazione (`…-FO1000-…`).

**Sanitizzazione del nome file originale**: mantenuto leggibile, ma normalizzato — rimozione dei caratteri vietati da Windows/Linux (`\ / : * ? " < > |`), collasso degli spazi multipli, rimozione dei punti finali, lunghezza massima 120 caratteri, estensione conservata in minuscolo. Gli accenti **sono conservati** (UTF-8) per leggibilità; se `config.xml` imposta `<nomiFileAscii>1</nomiFileAscii>` vengono traslitterati (utile su hosting con filesystem non UTF-8).

**Rinomina dell'ipogeo**: se cambia il nome, la cartella viene rinominata; se cambia il codice, vengono rinominati cartella, sottocartelle e **tutti** i file contenuti (operazione riservata ADM, tracciata nel log, con backup dell'intero albero). Il cambio di codice non cancella la memoria del precedente: viene registrato in `<codiciStorici>` nella scheda e in `dati/_indice/codici.csv` (§5.5).

---

## 5. Cataloghi e codice catastale

Il codice è l'identificatore dell'ipogeo: univoco **su tutta l'installazione**, non solo dentro il proprio catalogo, perché compare nei nomi dei file e un file estratto dall'archivio deve restare riconducibile a un solo ipogeo senza ambiguità.

### 5.1 Cataloghi multipli

Un'installazione ospita **più cataloghi** (D5). Un catalogo è un archivio con una propria identità, una propria regola di codifica e propri contatori: tipicamente un catasto regionale, un catalogo tematico (cavità artificiali di una città) o le spedizioni all'estero.

Ogni catalogo è una cartella `dati/cataloghi/[sigla] - [nome]/` con il proprio `catalogo.xml` (§6.2). L'utente lavora sempre in un **catalogo attivo**, selezionabile dalla navbar; ricerca e mappa possono operare sul catalogo attivo o su **tutti** i cataloghi insieme (l'indice è unico e ha la colonna `catalogo`).

La sigla del catalogo è **indipendente dai prefissi dei codici** che contiene: il catalogo `LA` può assegnare sia codici `LA…` che `LA-AC…`.

### 5.2 Serie di codifica a contatori indipendenti

Dentro un catalogo la codifica non è un singolo prefisso, ma un elenco ordinato di **serie**. Ogni serie ha il proprio prefisso, il proprio padding e — punto essenziale — il **proprio contatore indipendente**. L'assegnazione avviene per **criteri**: alla prima serie i cui criteri sono tutti soddisfatti dall'ipogeo.

Criteri disponibili: `natura`, `tipologia`, `sottotipologia`, `stato`, `regione`, `provincia`. Un criterio assente vale "qualsiasi"; una serie senza criteri fa da default e va messa per ultima. I criteri accettano più valori separati da `|`.

```xml
<serie>
  <!-- cavità artificiali del Lazio: contatore proprio, padding a 4 cifre -->
  <serieCodice prefisso="LA-AC" nome="Lazio — cavità artificiali"
               natura="ART" regione="Lazio"
               cifre="4" prossimoProgressivo="12"/>

  <!-- cavità naturali del Lazio: contatore proprio, padding a 3 cifre -->
  <serieCodice prefisso="LA" nome="Lazio — cavità naturali"
               natura="NAT" regione="Lazio"
               cifre="3" prossimoProgressivo="298"/>

  <!-- fuori regione, qualunque natura: nessun padding -->
  <serieCodice prefisso="LA-X" nome="Fuori regione"
               cifre="0" prossimoProgressivo="1"/>
</serie>
```

Con questa configurazione un nuovo cunicolo artificiale a Roma diventa `LA-AC0012`, una grotta naturale nel Lazio `LA298`, un ipogeo censito in Puglia dal gruppo laziale `LA-X1`.

**La sigla non ha limiti di lunghezza né di caratteri** oltre a quelli imposti dai nomi di file (`\ / : * ? " < > |` vietati) e all'unicità: `LA`, `RM-AC`, `PUGLIA`, `Lazio.Art` sono tutti validi. Il `<separatore>` di catalogo, se valorizzato, viene interposto tra prefisso e progressivo (`LA-297`).

### 5.3 Padding del progressivo: soglia minima, non tetto

`cifre` indica il **numero minimo di cifre da esporre**, ottenuto con zero-padding a sinistra. Non è un limite superiore: un numero più lungo del padding viene scritto per intero, senza troncamenti e senza errori.

| `cifre` | Progressivo 2 | Progressivo 297 | Progressivo 15234 |
|:--:|---|---|---|
| `5` | `LA00002` | `LA00297` | `LA15234` |
| `3` | `LA002` | `LA297` | `LA15234` |
| `2` | `LA02` | `LA297` | `LA15234` |
| `0` | `LA2` | `LA297` | `LA15234` |

**Nessun tetto al progressivo** (D7) oltre il limite intero della piattaforma: PHP a 64 bit gestisce fino a `PHP_INT_MAX` = 9.223.372.036.854.775.807. Il contatore è conservato come stringa numerica nell'XML e incrementato in intero, quindi non è soggetto ad arrotondamenti in virgola mobile. La diagnostica segnala se l'installazione girasse su un PHP a 32 bit (tetto a 2.147.483.647), caso che comunque non ha alcun impatto pratico su un catasto.

### 5.4 Assegnazione del codice

- **Automatica** al salvataggio della nuova scheda: si risolve la serie dai criteri, si legge e incrementa il contatore sotto `flock()` esclusivo sul `catalogo.xml`, si compone il codice.
- **Manuale**, se `<consentiCodiceManuale>1</consentiCodiceManuale>`: indispensabile per **importare catasti esistenti** conservando la numerazione storica. In questo caso il codice viene validato per forma e unicità, e se il progressivo estratto supera il contatore della serie il contatore viene allineato in avanti (mai indietro).
- **Unicità** verificata su tre livelli: indice `codici.csv`, indice `ipogei.csv`, esistenza fisica della cartella in tutti i cataloghi. Le tre verifiche sono deliberatamente ridondanti: un archivio ripristinato a mano da backup può avere indici disallineati, e il codice è la cosa che non deve mai duplicarsi.
- **Codici esterni**: indipendentemente dal codice interno, la scheda può riportare N codici di altri catasti (SSI, catasto regionale, catalogo comunale) in `<codiciEsterni>` — vedi §6.8.

### 5.5 Migrazione di un ipogeo tra cataloghi

Strumento richiesto esplicitamente e previsto in v1 (D9). Sposta un ipogeo da un catalogo all'altro **conservando la memoria del codice di origine**.

Sequenza dell'operazione (riservata ADM, transazionale, con backup preventivo dell'albero dell'ipogeo):

1. Risoluzione della serie di destinazione e assegnazione del nuovo codice nel catalogo di arrivo.
2. Spostamento della cartella dell'ipogeo sotto `dati/cataloghi/[destinazione]/ipogei/`.
3. Rinomina della cartella, delle sottocartelle e di **tutti** i file contenuti col nuovo codice.
4. Riscrittura dei riferimenti interni negli XML di sezione (i riferimenti sono relativi e per sigla, quindi l'impatto è minimo per costruzione).
5. Scrittura in scheda della traccia storica:

```xml
<codiciStorici>
  <codiceStorico codice="LA297" catalogo="LA" siglaCatalogo="LA"
                 nomeCatalogo="Catasto Ipogei del Lazio"
                 dal="2024-06-01" al="2026-08-04"
                 motivo="migrazione catalogo"
                 utente="darcan99"/>
</codiciStorici>
```

6. Aggiornamento di `dati/_indice/codici.csv` con la riga `LA297 → RM-AC0007`, così che **ogni riferimento al vecchio codice continui a risolvere**: se un utente cerca `LA297` o apre un vecchio link, l'applicativo lo porta alla scheda corrente segnalando la migrazione. Questo conta molto in un catasto, dove il codice viene citato in pubblicazioni cartacee che non si possono aggiornare.
7. Riga di tracciato in `dati/_log/migrazioni.csv` e voce nello storico della scheda.

È prevista anche la **migrazione multipla** (selezione da ricerca → sposta N ipogei), con anteprima dei codici che verrebbero assegnati e conferma esplicita prima di scrivere.

---

## 6. Modello dati

Specifica di dettaglio in `docs/STANDARD-DATI.md`; qui la struttura essenziale.

### 6.1 `config.xml`

```xml
<catageo versioneSchema="1.0">
  <catasto>
    <nome>Catasto degli Ipogei del Lazio</nome>
    <ente>Gruppo Speleologico ...</ente>
    <descrizione/>
    <logo>assets/img/logo.png</logo>
    <email>catasto@esempio.it</email>
  </catasto>
  <cataloghi>
    <!-- I cataloghi sono autodescrittivi: l'applicativo li scopre scandendo
         dati/cataloghi/*/catalogo.xml (§6.2). Qui stanno solo le preferenze,
         non l'elenco: nessun registro centrale da tenere sincronizzato. -->
    <predefinito>LA</predefinito>         <!-- sigla del catalogo attivo all'accesso -->
    <ricercaMulticatalogo>1</ricercaMulticatalogo>
    <consentiMigrazione>1</consentiMigrazione>
  </cataloghi>
  <percorsi>
    <dati>dati</dati>                     <!-- ridefinibile fuori webroot -->
  </percorsi>
  <mappa>
    <provider>osm</provider>              <!-- osm | google | custom -->
    <chiaveApi/>                          <!-- solo se provider=google -->
    <centro lat="41.9028" lon="12.4964"/>
    <zoom>9</zoom>
    <zoomScheda>16</zoomScheda>
    <clusterMarker>1</clusterMarker>
    <baseLayers>
      <layer id="osm" nome="OpenStreetMap" tipo="tms"
             url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
             attribuzione="© OpenStreetMap contributors" maxZoom="19" attivo="1"/>
      <layer id="topo" nome="OpenTopoMap" tipo="tms" url="..." attivo="0"/>
    </baseLayers>
    <overlayLayers>
      <layer id="igm" nome="IGM 25.000" tipo="wms"
             url="https://.../wms" layers="CB.IGM25000" formato="image/png"
             trasparente="1" versione="1.3.0" opacita="0.7" attivo="0"/>
    </overlayLayers>
  </mappa>
  <upload>
    <dimensioneMax unita="MB">32</dimensioneMax>
    <estensioni sezione="allegati">pdf,doc,docx,odt,txt,rtf,xls,xlsx,ods,zip</estensioni>
    <estensioni sezione="foto">jpg,jpeg,png,webp,tif,tiff</estensioni>
    <estensioni sezione="video">mp4,webm,ogg,mov,avi</estensioni>
    <estensioni sezione="rilievi">pdf,dxf,dwg,svg,png,jpg,kml,kmz,gpx,ply,obj,stl,gltf,glb,th,th2,3d,tro,plt</estensioni>
    <estensioni sezione="scientifici">csv,txt,xls,xlsx,ods</estensioni>
    <miniatureLarghezza>400</miniatureLarghezza>
  </upload>
  <sicurezza>
    <riservatezzaPredefinita>pubblica</riservatezzaPredefinita>
    <offuscamentoCoordinate unita="m">1000</offuscamentoCoordinate>
    <tentativiLogin>5</tentativiLogin>
    <bloccoMinuti>15</bloccoMinuti>
    <durataSessioneMinuti>120</durataSessioneMinuti>
    <accessoAnonimo>0</accessoAnonimo>   <!-- D2: sempre 0 in v1, login obbligatorio -->
  </sicurezza>
  <sistema>
    <versioneApp>1.0.0</versioneApp>
    <fusoOrario>Europe/Rome</fusoOrario>
    <lingua>it</lingua>
    <versioniStorico>20</versioniStorico>
    <debug>0</debug>
  </sistema>
</catageo>
```

### 6.2 `catalogo.xml` — descrittore di catalogo

Un file per catalogo, dentro la cartella del catalogo. È l'unico posto dove vivono i contatori: il lock in scrittura per l'assegnazione di un codice riguarda solo questo file, quindi cataloghi diversi non si bloccano a vicenda.

```xml
<catalogo versioneSchema="1.0">

  <identita>
    <sigla>LA</sigla>                     <!-- univoca; nessun limite di lunghezza -->
    <nome>Catasto Ipogei del Lazio</nome>
    <ente>Gruppo Speleologico Romano</ente>
    <descrizione>Catasto delle cavità naturali e artificiali del Lazio</descrizione>
    <responsabile esploratoreId="E001"/>
    <ambito>
      <stato>IT</stato>                   <!-- ISO 3166-1 alpha-2; vuoto = nessun vincolo -->
      <regione>Lazio</regione>            <!-- indicativo, non vincolante -->
    </ambito>
    <dataIstituzione>1998-01-01</dataIstituzione>
    <attivo>1</attivo>                    <!-- 0 = in sola consultazione -->
  </identita>

  <codifica>
    <separatore></separatore>             <!-- interposto fra prefisso e progressivo -->
    <consentiCodiceManuale>1</consentiCodiceManuale>
    <serie>
      <serieCodice prefisso="LA-AC" nome="Lazio — cavità artificiali"
                   natura="ART" regione="Lazio"
                   cifre="4" prossimoProgressivo="12"/>
      <serieCodice prefisso="LA" nome="Lazio — cavità naturali"
                   natura="NAT" regione="Lazio"
                   cifre="3" prossimoProgressivo="298"/>
      <serieCodice prefisso="LA-X" nome="Fuori regione"
                   cifre="0" prossimoProgressivo="1"/>
    </serie>
  </codifica>

  <!-- Compilato quando il catalogo recepisce un archivio preesistente:
       serve a sapere da dove vengono i dati e con quale licenza. -->
  <origine>
    <catastoOrigine>Catasto Grotte del Lazio</catastoOrigine>
    <riferimento>Federazione Speleologica del Lazio</riferimento>
    <dataImportazione>2026-09-01</dataImportazione>
    <licenzaDati>Concessione scritta del 2026-08-20, uso interno</licenzaDati>
    <note/>
  </origine>

</catalogo>
```

### 6.3 `utenti.xml`

```xml
<utenti versioneSchema="1.0">
  <utente id="U001">
    <username>darcan99</username>
    <nomeCompleto>Dario Candela</nomeCompleto>
    <email>darcan99@gmail.com</email>
    <password>$2y$12$…</password>          <!-- password_hash(), BCRYPT -->
    <livello>ADM</livello>                  <!-- ADM | OPE | USR -->
    <esploratoreId>E001</esploratoreId>     <!-- collegamento opzionale -->
    <attivo>1</attivo>
    <dataCreazione>2026-08-03</dataCreazione>
    <ultimoAccesso>2026-08-03T18:12:00</ultimoAccesso>
    <tentativiFalliti>0</tentativiFalliti>
    <bloccatoFino/>
  </utente>
</utenti>
```

### 6.4 `grandezze.xml` e `periodi_storici.xml` — anagrafiche di supporto

Due tabelle di dominio, editabili da ADM, che alimentano le tendine delle nuove sezioni. Sono precaricate all'installazione ma completamente modificabili: non si impone un vocabolario chiuso a chi ha già le proprie convenzioni.

`grandezze.xml` — cosa si può misurare in una cavità, con unità e intervalli di plausibilità per intercettare gli errori di battitura:

```xml
<grandezze versioneSchema="1.0">
  <categoria codice="CLIMA" nome="Clima ipogeo">
    <grandezza codice="T-ARIA"  nome="Temperatura aria"      unita="°C"    min="-20"  max="60"  decimali="2"/>
    <grandezza codice="T-ACQUA" nome="Temperatura acqua"     unita="°C"    min="-2"   max="40"  decimali="2"/>
    <grandezza codice="UR"      nome="Umidità relativa"      unita="%"     min="0"    max="100" decimali="1"/>
    <grandezza codice="P-BAR"   nome="Pressione barometrica" unita="hPa"   min="800"  max="1100" decimali="1"/>
    <grandezza codice="V-ARIA"  nome="Velocità aria"         unita="m/s"   min="0"    max="30"  decimali="2"/>
    <grandezza codice="Q-ARIA"  nome="Portata d'aria"        unita="m³/s"  min="0"    max="500" decimali="2"/>
  </categoria>
  <categoria codice="GAS" nome="Gas e qualità dell'aria">
    <grandezza codice="CO2" nome="Anidride carbonica" unita="ppm" min="0" max="100000" decimali="0"/>
    <grandezza codice="O2"  nome="Ossigeno"           unita="%"   min="0" max="25"     decimali="2"/>
    <grandezza codice="CH4" nome="Metano"             unita="ppm" min="0" max="50000"  decimali="0"/>
    <grandezza codice="H2S" nome="Acido solfidrico"   unita="ppm" min="0" max="1000"   decimali="1"/>
    <grandezza codice="CO"  nome="Monossido di carbonio" unita="ppm" min="0" max="1000" decimali="1"/>
  </categoria>
  <categoria codice="RAD" nome="Radioattività">
    <grandezza codice="RADON"    nome="Concentrazione radon"    unita="Bq/m³" min="0" max="200000" decimali="0"/>
    <grandezza codice="DOSE"     nome="Rateo di dose ambientale" unita="µSv/h" min="0" max="1000"  decimali="3"/>
    <grandezza codice="DOSE-CUM" nome="Dose cumulata"            unita="mSv"   min="0" max="10000" decimali="3"/>
  </categoria>
  <categoria codice="ACQUA" nome="Idrologia">
    <grandezza codice="Q-ACQUA" nome="Portata"        unita="l/s"    min="0"  max="100000" decimali="2"/>
    <grandezza codice="H-ACQUA" nome="Livello idrico" unita="m"      min="-100" max="500"  decimali="3"/>
    <grandezza codice="PH"      nome="pH"             unita=""       min="0"  max="14"     decimali="2"/>
    <grandezza codice="COND"    nome="Conducibilità"  unita="µS/cm"  min="0"  max="100000" decimali="0"/>
    <grandezza codice="DUR"     nome="Durezza"        unita="°f"     min="0"  max="200"    decimali="1"/>
    <grandezza codice="TORB"    nome="Torbidità"      unita="NTU"    min="0"  max="10000"  decimali="1"/>
  </categoria>
</grandezze>
```

`periodi_storici.xml` — cronologia di riferimento per la datazione archeologica, con estremi indicativi in anni (negativi = a.C.) per consentire ricerche per intervallo temporale:

```xml
<periodi versioneSchema="1.0">
  <periodo codice="PREIST"    nome="Preistoria"                 da="-1000000" a="-3500"/>
  <periodo codice="PROTOST"   nome="Protostoria"                da="-3500"    a="-750"/>
  <periodo codice="ETRUSCO"   nome="Età etrusca"                da="-900"     a="-100"/>
  <periodo codice="ROM-REP"   nome="Età romana repubblicana"    da="-509"     a="-27"/>
  <periodo codice="ROM-IMP"   nome="Età romana imperiale"       da="-27"      a="476"/>
  <periodo codice="TARDOANT"  nome="Tardo antico"               da="284"      a="600"/>
  <periodo codice="ALTOMED"   nome="Alto medioevo"              da="600"      a="1000"/>
  <periodo codice="BASSOMED"  nome="Basso medioevo"             da="1000"     a="1492"/>
  <periodo codice="MODERNA"   nome="Età moderna"                da="1492"     a="1789"/>
  <periodo codice="CONTEMP"   nome="Età contemporanea"          da="1789"     a="1914"/>
  <periodo codice="WWI"       nome="Prima guerra mondiale"      da="1914"     a="1918"/>
  <periodo codice="INTERB"    nome="Periodo interbellico"       da="1919"     a="1939"/>
  <periodo codice="WWII"      nome="Seconda guerra mondiale"    da="1940"     a="1945"/>
  <periodo codice="DOPOG"     nome="Secondo dopoguerra e oltre" da="1946"     a="2100"/>
  <periodo codice="INDET"     nome="Non determinato"            da=""         a=""/>
</periodi>
```

### 6.5 `gruppi_speleologici.xml`

```xml
<gruppi versioneSchema="1.0">
  <gruppo id="G001">
    <sigla>GSR</sigla>
    <nome>Gruppo Speleologico Romano</nome>
    <sedeComune>Roma</sedeComune>
    <sedeProvincia>RM</sedeProvincia>
    <indirizzo/> <email/> <telefono/> <sitoWeb/>
    <annoFondazione>1957</annoFondazione>
    <affiliazioni><affiliazione>SSI</affiliazione></affiliazioni>
    <note/>
    <attivo>1</attivo>
  </gruppo>
</gruppi>
```

### 6.6 `esploratori.xml`

```xml
<esploratori versioneSchema="1.0">
  <esploratore id="E001">
    <cognome>Candela</cognome>
    <nome>Dario</nome>
    <soprannome/>
    <!-- Appartenenza storicizzata. Lo STESSO gruppo puo comparire piu volte
         con periodi distinti: chi lascia un gruppo e vi rientra dopo qualche
         anno ha due periodi, e la storia va conservata per intero. Periodi di
         gruppi DIVERSI possono sovrapporsi liberamente, perche l'iscrizione
         simultanea a piu gruppi e la norma. L'unico caso rifiutato e
         l'accavallamento di due periodi dello stesso gruppo. -->
    <gruppi>
      <gruppo id="G001" dal="2018" al="2020"/>
      <gruppo id="G002" dal="2021" al="2025"/>
      <gruppo id="G001" dal="2023" al=""/>   <!-- rientro, ancora in corso -->
    </gruppi>
    <email/> <telefono/>
    <qualifiche><qualifica>Istruttore SSI</qualifica></qualifiche>
    <note/>
    <attivo>1</attivo>
  </esploratore>
</esploratori>
```

### 6.7 `tipologie.xml` — tassonomia

Classificazione a due livelli, editabile da ADM, con distinzione **natura** (naturale / artificiale / mista):

```xml
<tipologie versioneSchema="1.0">
  <natura codice="ART" nome="Cavità artificiale">
    <tipologia codice="ART-IDR" nome="Opere idrauliche">
      <sotto codice="ART-IDR-CUN" nome="Cunicolo drenante"/>
      <sotto codice="ART-IDR-ACQ" nome="Acquedotto"/>
      <sotto codice="ART-IDR-CIS" nome="Cisterna"/>
      <sotto codice="ART-IDR-POZ" nome="Pozzo"/>
    </tipologia>
    <tipologia codice="ART-EST" nome="Opere estrattive">
      <sotto codice="ART-EST-CAV" nome="Cava ipogea"/>
      <sotto codice="ART-EST-MIN" nome="Miniera"/>
    </tipologia>
    <tipologia codice="ART-CUL" nome="Insediamenti e opere di culto">
      <sotto codice="ART-CUL-CAT" nome="Catacomba"/>
      <sotto codice="ART-CUL-CHR" nome="Chiesa rupestre"/>
      <sotto codice="ART-CUL-IPO" nome="Ipogeo funerario"/>
    </tipologia>
    <tipologia codice="ART-ABI" nome="Insediamenti civili"/>
    <tipologia codice="ART-BEL" nome="Opere belliche"/>
    <tipologia codice="ART-TRA" nome="Opere di transito"/>
    <tipologia codice="ART-ALT" nome="Altro / non determinato"/>
  </natura>
  <natura codice="NAT" nome="Cavità naturale">
    <tipologia codice="NAT-CAR" nome="Carsica"/>
    <tipologia codice="NAT-VUL" nome="Vulcanica"/>
    <tipologia codice="NAT-MAR" nome="Marina / di abrasione"/>
    <tipologia codice="NAT-TET" nome="Tettonica"/>
    <tipologia codice="NAT-GLA" nome="Glaciale"/>
    <tipologia codice="NAT-ALT" nome="Altro / non determinato"/>
  </natura>
</tipologie>
```

### 6.8 `[codice] - Dati.xml` — template standard della scheda

Il template è **unico per tutti gli ipogei**: la scheda contiene sempre tutte le sezioni, anche vuote, così che l'archivio sia omogeneo e diffabile.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<ipogeo versioneSchema="1.1" catalogo="LA">

  <identificazione>
    <codice>LA297</codice>
    <nome>Grotta dei Ragni</nome>
    <sinonimi><sinonimo>Grotta del Diavolo</sinonimo></sinonimi>
    <natura>ART</natura>
    <tipologia>ART-IDR</tipologia>
    <sottotipologia>ART-IDR-CUN</sottotipologia>
    <!-- Codici assegnati da ALTRI catasti, non gestiti da questa installazione -->
    <codiciEsterni>
      <codiceEsterno ente="SSI" catasto="Catasto Grotte Lazio">La 1234</codiceEsterno>
    </codiciEsterni>
    <!-- Codici INTERNI precedenti, dopo rinumerazione o migrazione di catalogo (§5.5) -->
    <codiciStorici>
      <codiceStorico codice="RM-AC0007" catalogo="RM-AC"
                     nomeCatalogo="Cavità Artificiali di Roma"
                     dal="2020-03-11" al="2024-06-01"
                     motivo="migrazione catalogo" utente="darcan99"/>
    </codiciStorici>
  </identificazione>

  <ubicazione>
    <!-- Stato ISO 3166-1 alpha-2 (D11). Con stato diverso da IT le tendine di
         regione e provincia diventano campi liberi e valgono come divisioni
         amministrative locali, senza cambiare la struttura della scheda. -->
    <stato codice="IT">Italia</stato>
    <regione>Lazio</regione>
    <provincia>RM</provincia>
    <comune>Roma</comune>
    <localita>Quarto Miglio</localita>
    <indirizzo/>
    <!-- Latitudine e longitudine sono SEMPRE in gradi decimali WGS84: e la
         forma che serve a mappa, ricerca per raggio ed export, e una sola forma
         canonica evita che due schede diventino inconfrontabili.
         Accanto si conserva pero il dato COME E STATO RILEVATO (D13): un
         catasto che ha misurato in UTM ha misurato in UTM, e riscrivere solo la
         conversione perderebbe cosa fu letto sullo strumento. -->
    <coordinate sistema="EPSG:4326">
      <latitudine>41.856231</latitudine>
      <longitudine>12.532104</longitudine>
      <quota unita="m">62</quota>
      <precisione unita="m">5</precisione>
      <metodo>GPS</metodo>            <!-- GPS | CTR | cartografia | Google | stima -->
      <dataRilevamento>2024-05-12</dataRilevamento>
      <sistemaOriginale>EPSG:32633</sistemaOriginale>   <!-- UTM WGS84 fuso 33N -->
      <formatoOriginale>utm</formatoOriginale>          <!-- decimali|gms|gm|utm -->
      <valoreOriginale>33 291952.00 4640623.00 N</valoreOriginale>
    </coordinate>
    <cartografia>
      <tavolettaIGM/>
      <sezioneCTR/>
    </cartografia>
    <accesso>
      <stato>aperto</stato>           <!-- aperto|chiuso|interrato|distrutto|non_localizzato -->
      <descrizione>…</descrizione>
      <proprieta>privata</proprieta>
      <permessiNecessari>1</permessiNecessari>
      <riferimentoPermessi>…</riferimentoPermessi>
    </accesso>
    <riservatezza>pubblica</riservatezza>  <!-- pubblica | coordinate_offuscate | riservata -->
  </ubicazione>

  <caratteristiche>
    <sviluppoPlanimetrico unita="m">245</sviluppoPlanimetrico>
    <sviluppoSpaziale unita="m">260</sviluppoSpaziale>
    <dislivelloPositivo unita="m">3</dislivelloPositivo>
    <dislivelloNegativo unita="m">-18</dislivelloNegativo>
    <profonditaMassima unita="m">21</profonditaMassima>
    <numeroIngressi>2</numeroIngressi>
    <ingressi>
      <ingresso n="1">
        <descrizione>Pozzo verticale in muratura</descrizione>
        <latitudine>41.856231</latitudine>
        <longitudine>12.532104</longitudine>
        <quota unita="m">62</quota>
        <dimensioni>1,2 × 1,2 m</dimensioni>
        <stato>aperto</stato>
      </ingresso>
    </ingressi>
    <!-- SINTESI di riepilogo. Il dettaglio vive nelle sezioni dedicate
         (§6.13 dati scientifici, §6.14 biospeleologia, §6.15 archeologia,
         §6.16 geologia): qui restano solo i campi che servono a ricerca,
         elenchi, marker di mappa e stampa della scheda. Sono ricalcolati
         dall'applicativo quando si modifica la sezione corrispondente. -->
    <sintesiGeologia>
      <litologia>Tufo litoide</litologia>
      <formazione>Unità di Villa Senni</formazione>
      <eta>Pleistocene medio</eta>
    </sintesiGeologia>
    <idrologia>
      <presenzaAcqua>stagionale</presenzaAcqua>  <!-- assente|stagionale|permanente|allagato -->
      <note/>
    </idrologia>
    <sintesiBiospeleologia>
      <presenzaChirotteri>1</presenzaChirotteri>
      <sitoRilevanteChirotteri>1</sitoRilevanteChirotteri>
      <numeroSpecieCensite>7</numeroSpecieCensite>
      <note/>
    </sintesiBiospeleologia>
    <sintesiArcheologia>
      <presenzaEvidenze>1</presenzaEvidenze>
      <periodoPrincipale>ROM-IMP</periodoPrincipale>
      <vincolo>1</vincolo>
      <note/>
    </sintesiArcheologia>
    <sintesiScientifici>
      <serieAttive>3</serieAttive>
      <monitoraggioInCorso>1</monitoraggioInCorso>
      <ultimaMisura>2026-07-28</ultimaMisura>
    </sintesiScientifici>
    <interesse>
      <voce>archeologico</voce>
      <voce>storico</voce>
    </interesse>
    <percorribilita>
      <difficolta>media</difficolta>
      <attrezzaturaNecessaria>Corda 30 m, imbrago</attrezzaturaNecessaria>
      <pericoli>Gas, crolli localizzati</pericoli>
      <tempoPercorrenza>2 h</tempoPercorrenza>
    </percorribilita>
  </caratteristiche>

  <!-- NESSUN limite di lunghezza su questi campi (D6): testo in CDATA, nessun
       maxlength in interfaccia, nessun troncamento in salvataggio. Gli estratti
       negli elenchi sono calcolati a runtime, il testo su disco resta integrale. -->
  <descrizione>
    <sintesi><![CDATA[Cunicolo drenante di età romana …]]></sintesi>
    <testo><![CDATA[…testo esteso, di lunghezza illimitata…]]></testo>
    <storia><![CDATA[…]]></storia>
    <note><![CDATA[]]></note>
  </descrizione>

  <!-- La bibliografia NON sta più qui: ha una sezione dedicata su file proprio
       (§6.12), così una voce può essere citata da più punti della scheda e
       condivisa fra ipogei diversi. -->

  <collegamenti>
    <ipogeoCorrelato codice="LA298" relazione="collegato"/>
  </collegamenti>

  <catasto>
    <catalogo sigla="LA" nome="Catasto Ipogei del Lazio"/>
    <serieCodice prefisso="LA"/>            <!-- serie che ha generato il codice -->
    <dataCensimento>2024-06-01</dataCensimento>
    <censitoDa esploratoreId="E001"/>
    <gruppoCensore id="G001"/>
    <statoScheda>pubblicata</statoScheda>   <!-- bozza | verificata | pubblicata -->
    <creazione utente="darcan99">2024-06-01T10:00:00</creazione>
    <ultimaModifica utente="darcan99">2026-08-03T18:12:00</ultimaModifica>
    <revisione>7</revisione>
  </catasto>

</ipogeo>
```

### 6.9 Indici di sezione — `[codice] - Foto.xml` (idem Allegati / Video / Rilievi)

```xml
<risorse versioneSchema="1.0" sezione="foto" codiceIpogeo="LA297" ultimoProgressivo="3">
  <risorsa progressivo="1" sigla="FO">
    <file>LA297-FO001-Ingresso principale.jpg</file>
    <titolo>Ingresso principale</titolo>
    <descrizione>Vista da sud dopo il disgaggio</descrizione>
    <data>2024-05-12</data>
    <autore esploratoreId="E001">Dario Candela</autore>
    <gruppo id="G001"/>
    <esplorazioneRif>ES001</esplorazioneRif>       <!-- opzionale -->
    <tag><voce>ingresso</voce></tag>
    <coordinate>
      <latitudine>41.856231</latitudine>
      <longitudine>12.532104</longitudine>
    </coordinate>
    <licenza>CC BY-NC-SA</licenza>
    <riservatezza>pubblica</riservatezza>
    <copertina>1</copertina>                        <!-- foto di copertina scheda -->
    <mime>image/jpeg</mime>
    <dimensione>2458112</dimensione>
    <hash>sha256:…</hash>
    <caricato utente="darcan99">2026-08-03T18:12:00</caricato>
  </risorsa>
</risorse>
```

Estensioni specifiche per sezione:

- **Rilievi**: `<tipoRilievo>` (pianta / sezione / spaccato / poligonale / modello3D), `<formato>` (pdf, dxf, kml, ply, obj…), `<dimensione2D3D>` (2D|3D), `<scala>`, `<sistemaRiferimento>`, `<visualizzaInMappa>` (1 se KML/KMZ/GPX da sovrapporre), `<visualizzaIn3D>` (1 se PLY/OBJ/GLTF/STL), `<rilevatori>` (lista `esploratoreId`), `<dataRilievo>`, `<strumentazione>`.
- **Video**: `<durata>`, `<risoluzione>`, `<urlEsterno>` (per video ospitati altrove, così da non consumare spazio hosting).
- **Allegati**: `<categoriaAllegato>` (relazione, autorizzazione, articolo, cartografia, corrispondenza, altro).

### 6.10 Esplorazioni — `[codice]-ES001-[titolo].xml`

Un file per esplorazione, così che il diario resti un documento autonomo e leggibile.

```xml
<esplorazione versioneSchema="1.0" progressivo="1" codiceIpogeo="LA297">
  <titolo>Prima ricognizione del ramo nord</titolo>
  <tipo>esplorazione</tipo>          <!-- ricognizione|esplorazione|rilievo|documentazione|disostruzione|monitoraggio -->
  <dataInizio>2024-05-12</dataInizio>
  <oraInizio>09:30</oraInizio>
  <dataFine>2024-05-12</dataFine>
  <oraFine>15:45</oraFine>
  <durataOre>6.25</durataOre>
  <gruppi>
    <gruppo id="G001"/>
    <gruppo id="G004"/>
  </gruppi>
  <partecipanti>
    <partecipante esploratoreId="E001" ruolo="capogita"/>
    <partecipante esploratoreId="E007" ruolo="rilevatore"/>
    <partecipante nome="Mario Bianchi" ruolo="ospite"/>   <!-- non censito -->
  </partecipanti>
  <meteo>Sereno, 22 °C</meteo>
  <obiettivi>Verifica prosecuzione oltre la frana</obiettivi>
  <diario>
    <voce>
      <ora>10:15</ora>
      <coordinate>
        <latitudine>41.856231</latitudine>
        <longitudine>12.532104</longitudine>
        <quota unita="m">62</quota>
      </coordinate>
      <testo>Ingresso nel pozzo, armo su fix esistenti.</testo>
      <fotoRif>FO001</fotoRif>
      <fotoRif>FO002</fotoRif>
    </voce>
    <voce>
      <ora>12:40</ora>
      <coordinate><latitudine>41.856702</latitudine><longitudine>12.532890</longitudine></coordinate>
      <testo>Raggiunto il termine del ramo nord…</testo>
    </voce>
  </diario>
  <risultati>Individuati 40 m di nuovo sviluppo</risultati>
  <materialeProdotto>
    <rilievoRif>RI002</rilievoRif>
    <allegatoRif>AL003</allegatoRif>
  </materialeProdotto>
  <traccia>LA297-ES001-Traccia.gpx</traccia>       <!-- opzionale, in Rilievi o Esplorazioni -->
  <note/>
  <redatto utente="darcan99">2024-05-14T21:00:00</redatto>
</esplorazione>
```

**Nota di progetto**: le foto **non** vengono duplicate nella cartella Esplorazioni. Restano in `[codice] - Foto` e vengono richiamate per codice (`FO001`). Una singola foto può così comparire nella galleria dell'ipogeo e nel diario, con un solo file su disco.

`[codice] - Esplorazioni.xml` fa da indice leggero (progressivo, titolo, date, gruppi, file) per non aprire tutti i diari.

### 6.11 Formato degli indici CSV

`dati/_indice/ipogei.csv` — separatore `;`, delimitatore `"`, prima riga di intestazione. Indice **unico e globale**: la prima colonna è il catalogo, così una sola scansione copre tutta l'installazione e il filtro per catalogo è un semplice confronto di campo.

```
catalogo;codice;nome;natura;tipologia;sottotipologia;stato;regione;provincia;comune;localita;lat;lon;quota;sviluppo;dislivello;stato_accesso;riservatezza;stato_scheda;n_allegati;n_foto;n_video;n_rilievi;n_esplorazioni;n_biblio;n_serie_misure;ha_kml;ha_3d;ha_chirotteri;ha_archeologia;periodo_arch;data_censimento;ultima_modifica;cartella
LA;LA297;Grotta dei Ragni;ART;ART-IDR;ART-IDR-CUN;IT;Lazio;RM;Roma;Quarto Miglio;41.856231;12.532104;62;245;-18;aperto;pubblica;pubblicata;1;3;1;3;1;4;3;1;1;1;1;ROM-IMP;2024-06-01;2026-08-04T18:12:00;LA - Catasto Ipogei del Lazio/ipogei/LA297 - Grotta dei Ragni
```

`dati/_indice/codici.csv` — risoluzione di **ogni** codice mai assegnato, corrente o storico (§5.5). Permette di far risolvere un riferimento cartaceo anche dopo una migrazione:

```
codice;stato_codice;codice_corrente;catalogo_corrente;data_variazione
LA297;corrente;LA297;LA;2024-06-01
RM-AC0007;storico;LA297;LA;2024-06-01
```

---

### 6.12 Bibliografia — `[codice] - Bibliografia/[codice] - Bibliografia.xml`

Sezione su file dedicato (D10), con tre tipi di voce: **riferimento** a un'opera del catalogo generale, voce **inline** autonoma, **link** esterno.

Il catalogo generale `dati/bibliografia_generale.xml` esiste per un motivo pratico: in un catasto una singola monografia descrive spesso decine di cavità. Censire l'opera una volta e citarla puntualmente evita di riscrivere autori, editore e ISBN in 40 schede — e permette di correggere un dato bibliografico in un solo posto. L'elenco inverso ("quali ipogei citano quest'opera") è **derivato** dall'indice, non memorizzato.

```xml
<bibliografia versioneSchema="1.0" codiceIpogeo="LA297" ultimoProgressivo="3">

  <!-- Tipo 1: riferimento puntuale a un'opera del catalogo generale -->
  <voce progressivo="1" sigla="BB" tipo="riferimento">
    <operaId>OP012</operaId>               <!-- da dati/bibliografia_generale.xml -->
    <pagine>112-130</pagine>
    <tavole>XIV-XVI</tavole>
    <rilevanza>primaria</rilevanza>        <!-- primaria | secondaria | citazione -->
    <note><![CDATA[Prima descrizione pubblicata del ramo nord]]></note>
  </voce>

  <!-- Tipo 2: voce autonoma, non presente nel catalogo generale -->
  <voce progressivo="2" sigla="BB" tipo="inline">
    <tipoOpera>articolo</tipoOpera>
    <!-- libro|articolo|atti|tesi|relazione|cartografia|archivio|web|altro -->
    <autori>Rossi M., Bianchi L.</autori>
    <titolo>Il cunicolo drenante del Quarto Miglio</titolo>
    <contenitore>Speleologia Romana</contenitore>
    <editore/> <luogo>Roma</luogo> <anno>1998</anno>
    <volume>12</volume> <fascicolo>2</fascicolo> <pagine>12-30</pagine>
    <isbnIssn>0393-1234</isbnIssn> <doi/> <lingua>it</lingua>
    <rilevanza>primaria</rilevanza>
    <abstract><![CDATA[…senza limiti di lunghezza…]]></abstract>
    <allegatoRif>AL001</allegatoRif>       <!-- PDF dell'articolo, se archiviato -->
  </voce>

  <!-- Tipo 3: riferimento esterno online -->
  <voce progressivo="3" sigla="BB" tipo="link">
    <titolo>Scheda sul catasto regionale</titolo>
    <url>https://esempio.it/catasto/la297</url>
    <ente>Federazione Speleologica del Lazio</ente>
    <dataConsultazione>2026-07-15</dataConsultazione>
    <copiaArchiviata>AL005</copiaArchiviata>  <!-- copia locale, i link muoiono -->
    <ultimaVerifica esito="raggiungibile">2026-08-01</ultimaVerifica>
    <note/>
  </voce>

</bibliografia>
```

`dati/bibliografia_generale.xml` contiene le stesse informazioni bibliografiche di una voce `inline`, con `id="OP012"`. Fra gli strumenti ADM è prevista una **verifica dei link** che interroga gli URL e aggiorna `<ultimaVerifica>`, con invito ad archiviare una copia locale delle pagine importanti: i riferimenti web si rompono in pochi anni e un catasto ha vita più lunga.

### 6.13 Dati scientifici — `[codice] - Scientifici/`

Sezione a **due file** (D8): un XML che descrive punti di misura, strumenti e serie; uno o più **CSV** che contengono le letture. Ogni serie ha storicità completa e i campi richiesti — misura, unità di misura, data, strumento, esploratore e/o provenienza del dato.

```xml
<scientifici versioneSchema="1.0" codiceIpogeo="LA297" ultimoProgressivo="3">

  <!-- Punti di misura stabili nel tempo, richiamati dalle serie e dalle
       osservazioni biologiche: così due misure "in sala grande" prese a
       cinque anni di distanza restano confrontabili. -->
  <puntiMisura>
    <punto id="PM1" nome="Ingresso principale">
      <descrizione>1 m oltre la soglia, a 1,5 m dal suolo</descrizione>
      <latitudine>41.856231</latitudine>
      <longitudine>12.532104</longitudine>
      <quota unita="m">62</quota>
      <progressivaInterna unita="m">0</progressivaInterna>
    </punto>
    <punto id="PM2" nome="Sala grande">
      <descrizione>Volta, settore nord</descrizione>
      <progressivaInterna unita="m">148</progressivaInterna>
    </punto>
  </puntiMisura>

  <serie progressivo="2" sigla="SC">
    <file>LA297-SC002-Radon sala grande.csv</file>
    <grandezza>RADON</grandezza>            <!-- da grandezze.xml, §6.4 -->
    <unita>Bq/m³</unita>
    <puntoMisura>PM2</puntoMisura>
    <tipoAcquisizione>datalogger</tipoAcquisizione>  <!-- puntuale|datalogger|campagna -->
    <passoTemporale>PT1H</passoTemporale>   <!-- ISO 8601; vuoto se irregolare -->
    <strumento>
      <modello>Radon Scout Plus</modello>
      <matricola>RS-88213</matricola>
      <ultimaTaratura>2026-01-15</ultimaTaratura>
      <incertezza>±10%</incertezza>
    </strumento>
    <responsabile esploratoreId="E001"/>
    <gruppo id="G001"/>
    <provenienza tipo="rilevamento_proprio">Gruppo Speleologico Romano</provenienza>
    <!-- tipo: rilevamento_proprio | ente_esterno | pubblicazione | stima -->
    <periodo dal="2026-03-01" al="2026-07-28"/>
    <numeroLetture>3624</numeroLetture>
    <riservatezza>pubblica</riservatezza>
    <note><![CDATA[Sonda lasciata in posto per l'intera stagione]]></note>
  </serie>

</scientifici>
```

Il CSV della serie — `LA297-SC002-Radon sala grande.csv`:

```
data;ora;valore;unita;grandezza;punto_misura;strumento;matricola;esploratore_id;provenienza;validita;note
2026-03-01;08:00;412;Bq/m³;RADON;PM2;Radon Scout Plus;RS-88213;E001;rilevamento_proprio;valido;
2026-03-01;09:00;438;Bq/m³;RADON;PM2;Radon Scout Plus;RS-88213;E001;rilevamento_proprio;valido;
2026-03-01;10:00;;Bq/m³;RADON;PM2;Radon Scout Plus;RS-88213;E001;rilevamento_proprio;anomalo;batteria scarica
```

Tre scelte da motivare:

- **Ripetizione di strumento, unità e provenienza in ogni riga**: è denormalizzazione voluta. Un CSV estratto dall'archivio e aperto da solo deve restare comprensibile senza il suo XML, ed è esattamente il requisito di leggibilità autonoma dei dati.
- **Colonna `validita`** (`valido|sospetto|anomalo|scartato`): una lettura errata non si cancella mai, si marca. In un monitoraggio pluriennale la cancellazione silenziosa di un dato scomodo è il modo più rapido per rendere la serie inutilizzabile.
- **Valore vuoto ammesso**: una lettura mancante è un'informazione (lo strumento c'era e non ha misurato), diversa dall'assenza di riga.

Funzioni previste: inserimento manuale di una lettura singola, **importazione di CSV da datalogger** con mappatura interattiva delle colonne e riconoscimento dei formati di data, riepiloghi statistici (min/max/media/mediana per periodo), **grafici SVG generati lato server in PHP** — nessuna libreria JS di charting da includere, coerentemente col vincolo di zero dipendenze — ed esportazione della serie.

### 6.14 Biospeleologia — `[codice] - Biospeleologia/`

Osservazioni faunistiche generali più un blocco dedicato alle **colonie di chirotteri**, che per rilevanza conservazionistica e normativa merita trattamento a sé.

```xml
<biospeleologia versioneSchema="1.0" codiceIpogeo="LA297" ultimoProgressivo="1">

  <osservazioni>
    <osservazione id="OS001">
      <data>2025-06-14</data> <ora>22:10</ora>
      <taxon>
        <nomeScientifico>Rhinolophus ferrumequinum</nomeScientifico>
        <nomeComune>Rinolofo maggiore</nomeComune>
        <gruppoTassonomico>chirotteri</gruppoTassonomico>
        <!-- chirotteri|invertebrati|anfibi|rettili|altri vertebrati|flora|microbiologia -->
        <classe>Mammalia</classe> <ordine>Chiroptera</ordine>
        <famiglia>Rhinolophidae</famiglia>
        <categoriaEcologica>troglofilo</categoriaEcologica>
        <!-- troglobio|troglofilo|trogloxeno|accidentale -->
        <endemismo>0</endemismo>
        <specieProtetta>1</specieProtetta>
        <direttivaHabitat>All. II e IV</direttivaHabitat>
        <listaRossaIucn>VU</listaRossaIucn>
      </taxon>
      <zonaCavita>Sala grande</zonaCavita>
      <puntoMisura>PM2</puntoMisura>          <!-- riuso dei punti di §6.13 -->
      <numeroIndividui>34</numeroIndividui>
      <metodo>conteggio visivo</metodo>
      <rilevatore esploratoreId="E007"/>
      <determinatore>Determinazione confermata da specialista esterno</determinatore>
      <provenienza tipo="rilevamento_proprio"/>
      <fotoRif>FO014</fotoRif>
      <note><![CDATA[…]]></note>
    </osservazione>
  </osservazioni>

  <colonieChirotteri>
    <colonia id="CH1">
      <nome>Colonia della sala grande</nome>
      <specie>Rhinolophus ferrumequinum</specie>
      <specieAggiuntive><specie>Myotis myotis</specie></specieAggiuntive>
      <ruolo>svernamento</ruolo>
      <!-- svernamento|riproduzione|transito|swarming|rifugio temporaneo -->
      <zonaCavita>Sala grande, volta settore nord</zonaCavita>
      <serieConteggi>LA297-BI001-Conteggi chirotteri.csv</serieConteggi>
      <consistenzaStimata>30-60</consistenzaStimata>
      <trend>stabile</trend>                 <!-- crescita|stabile|calo|estinta|ignoto -->
      <riservatezza>riservata</riservatezza>
      <disturbo>
        <periodoCritico dal="11-01" al="03-31"/>   <!-- ricorrente ogni anno -->
        <accessoSconsigliato>1</accessoSconsigliato>
        <prescrizioni><![CDATA[Nessuna visita durante lo svernamento;
          evitare illuminazione diretta e soste sotto la colonia.]]></prescrizioni>
      </disturbo>
      <riferimentoNormativo>Dir. 92/43/CEE; accordo EUROBATS; L. 157/1992</riferimentoNormativo>
      <biblioRif>BB002</biblioRif>
    </colonia>
  </colonieChirotteri>

</biospeleologia>
```

Il CSV dei conteggi — `LA297-BI001-Conteggi chirotteri.csv`:

```
data;ora;colonia;specie;metodo;numero;stima_min;stima_max;fase;temperatura;rilevatore_id;provenienza;validita;note
2025-01-18;11:30;CH1;Rhinolophus ferrumequinum;conteggio in letargo;41;38;45;svernamento;11,2;E007;rilevamento_proprio;valido;
2025-07-05;21:40;CH1;Rhinolophus ferrumequinum;conteggio in uscita;;55;70;riproduzione;18,4;E007;rilevamento_proprio;valido;conteggio al crepuscolo
```

Il conteggio ammette sia il valore esatto sia il solo intervallo `stima_min`/`stima_max`: chi conta pipistrelli in uscita al crepuscolo produce quasi sempre una stima, e costringere a un numero secco falserebbe il dato.

**Due funzioni conseguenti, non ovvie ma importanti:**

1. **Avviso di periodo critico**: se un ipogeo ha una colonia con `<periodoCritico>` in corso, la scheda e i risultati di ricerca lo segnalano in evidenza. Serve a evitare che un'uscita programmata disturbi uno svernamento — è il caso d'uso per cui questi dati vengono raccolti.
2. **Riservatezza rafforzata**: l'ubicazione di un roost di chirotteri è un dato sensibile per la conservazione. La `<riservatezza>` della colonia è **indipendente** da quella dell'ipogeo e prevale su di essa: una cavità pubblica può avere una colonia visibile solo a OPE e ADM.

### 6.15 Archeologia — `[codice] - Archeologia/[codice] - Archeologia.xml`

Sezione particolarmente rilevante per le cavità artificiali, con **periodo di riferimento** come chiave sia di lettura sia di ricerca.

```xml
<archeologia versioneSchema="1.0" codiceIpogeo="LA297" ultimoProgressivo="1">

  <inquadramento>
    <periodoPrincipale>ROM-IMP</periodoPrincipale>       <!-- da periodi_storici.xml -->
    <periodiSecondari>
      <periodo>TARDOANT</periodo>
      <periodo>WWII</periodo>
    </periodiSecondari>
    <datazione da="-27" a="476" precisione="secolo" criterio="tecnica costruttiva"/>
    <!-- precisione: anno|decennio|secolo|periodo|ignota -->
    <funzioneOriginaria>Cunicolo drenante di bonifica agraria</funzioneOriginaria>
    <funzioniSuccessive>
      <funzione periodo="WWII">Ricovero antiaereo</funzione>
      <funzione periodo="DOPOG">Deposito agricolo</funzione>
    </funzioniSuccessive>
    <contestoTopografico>Suburbio meridionale di Roma, lungo la via Latina</contestoTopografico>
    <sintesi><![CDATA[…senza limiti di lunghezza…]]></sintesi>
  </inquadramento>

  <evidenze>
    <evidenza progressivo="1" sigla="AR">
      <tipo>tecnica costruttiva</tipo>
      <!-- struttura muraria|tecnica costruttiva|iscrizione|graffito|ceramica|
           affresco|mosaico|sepoltura|impianto idraulico|traccia di strumenti|
           materiale di reimpiego|altro -->
      <descrizione><![CDATA[Tratto in opus reticulatum con cubilia di tufo…]]></descrizione>
      <zonaCavita>Primo tratto, 0-24 m</zonaCavita>
      <periodo>ROM-IMP</periodo>
      <statoConservazione>buono</statoConservazione>
      <!-- ottimo|buono|discreto|degradato|in pericolo|perduto -->
      <fotoRif>FO008</fotoRif>
      <rilievoRif>RI001</rilievoRif>
      <biblioRif>BB001</biblioRif>
    </evidenza>
  </evidenze>

  <tutela>
    <vincolo>1</vincolo>
    <tipoVincolo>D.Lgs. 42/2004</tipoVincolo>
    <enteCompetente>Soprintendenza territorialmente competente</enteCompetente>
    <riferimentoProvvedimento/>
    <dataProvvedimento/>
    <prescrizioni><![CDATA[Accesso subordinato ad autorizzazione…]]></prescrizioni>
    <allegatoRif>AL002</allegatoRif>     <!-- copia del provvedimento -->
  </tutela>

  <indagini>
    <indagine>
      <tipo>ricognizione</tipo>          <!-- ricognizione|scavo|documentazione|rilievo|datazione -->
      <data>2024-05-12</data>
      <soggetto>Ente competente con il gruppo speleologico</soggetto>
      <esplorazioneRif>ES001</esplorazioneRif>
      <esito><![CDATA[…]]></esito>
      <allegatoRif>AL003</allegatoRif>
    </indagine>
  </indagini>

</archeologia>
```

La presenza di `<vincolo>` e `<prescrizioni>` alimenta un avviso in scheda: chi programma un'uscita vede subito che serve un'autorizzazione, informazione che oggi vive nella memoria di chi ha fatto le pratiche.

### 6.16 Geologia — `[codice] - Geologia/[codice] - Geologia.xml`

Su questa sezione la tua nota era *"sarebbe utile ma non so che dati potremmo registrare e dove reperirli"*. Proposta concreta su entrambi i fronti.

**Cosa registrare** — campi che uno speleologo può compilare in cavità o desumere dalla cartografia pubblica, senza bisogno di analisi di laboratorio:

```xml
<geologia versioneSchema="1.0" codiceIpogeo="LA297" ultimoProgressivo="1">

  <inquadramento>
    <litologia>Tufo litoide</litologia>
    <formazione>Unità di Villa Senni</formazione>
    <unitaGeologica>Distretto vulcanico dei Colli Albani</unitaGeologica>
    <etaFormazione>Pleistocene medio</etaFormazione>
    <cronostratigrafia sistema="Quaternario" serie="Pleistocene"/>
    <foglioGeologico>Foglio 374 — Roma</foglioGeologico>
    <fonte tipo="wms" nome="Carta Geologica d'Italia 1:50.000"
           dataConsultazione="2026-08-04" modalita="GetFeatureInfo"/>
    <!-- modalita: manuale | GetFeatureInfo | bibliografia -->
  </inquadramento>

  <genesi>
    <tipoGenesi>antropica</tipoGenesi>
    <!-- carsica|vulcanica|tettonica|erosiva|glaciale|marina|antropica|mista -->
    <processo><![CDATA[Scavo in tufo con successivo allargamento per crollo…]]></processo>
    <rocciaEncassante>Tufo litoide a matrice cineritica</rocciaEncassante>
  </genesi>

  <assettoStrutturale>
    <giacitura immersione="120" inclinazione="8" unita="gradi"/>
    <fratturazione>media</fratturazione>   <!-- assente|debole|media|intensa -->
    <sistemiDiscontinuita><sistema direzione="N40E" tipo="frattura"/></sistemiDiscontinuita>
    <note/>
  </assettoStrutturale>

  <morfologie>
    <morfologia>
      <tipo>concrezionamento</tipo>
      <!-- concrezioni|marmitte|scallops|canali di volta|crolli|riempimenti|
           forme di corrosione|forme di erosione|tracce di scavo -->
      <descrizione><![CDATA[Colate calcitiche lungo la volta…]]></descrizione>
      <zonaCavita>Sala grande</zonaCavita>
      <fotoRif>FO011</fotoRif>
    </morfologia>
  </morfologie>

  <idrogeologia>
    <acquifero>Acquifero vulcanico dei Colli Albani</acquifero>
    <permeabilita>per fessurazione</permeabilita>
    <ruoloIdrogeologico>drenaggio</ruoloIdrogeologico>
    <!-- assorbimento|drenaggio|risorgenza|nessuno -->
    <sorgentiCollegate><sorgente nome="" distanza="" unita="m"/></sorgentiCollegate>
    <tracciamenti><tracciamento data="" tracciante="" esito=""/></tracciamenti>
    <serieMisureRif>SC003</serieMisureRif>   <!-- collegamento a §6.13 -->
  </idrogeologia>

  <rischi>
    <rischio tipo="crollo" livello="medio">
      <![CDATA[Distacchi localizzati in corrispondenza delle fratture…]]>
    </rischio>
    <!-- crollo|allagamento|sinkhole|subsidenza|gas|sismico -->
  </rischi>

  <campioni>
    <campione progressivo="1" sigla="GE">
      <tipo>concrezione</tipo>
      <data>2025-09-03</data>
      <prelevatoDa esploratoreId="E001"/>
      <zonaCavita>Sala grande</zonaCavita>
      <finalita>datazione radiometrica</finalita>
      <depositatoPresso>Dipartimento universitario di afferenza</depositatoPresso>
      <esitoAnalisi><![CDATA[…]]></esitoAnalisi>
      <allegatoRif>AL004</allegatoRif>
      <autorizzazione>Prelievo autorizzato dall'ente competente</autorizzazione>
    </campione>
  </campioni>

</geologia>
```

#### 6.16.1 Dove reperire i dati geologici

Il dato geologico di inquadramento **esiste già come cartografia pubblica**: non va inventato, va letto nel punto dove sta l'ipogeo. Fonti italiane utilizzabili:

| Fonte | Cosa fornisce | Come si usa in CATAGEO |
|---|---|---|
| **ISPRA — Servizio Geologico d'Italia**, Carta Geologica d'Italia 1:50.000 (Progetto CARG) e 1:100.000 | litologia, formazione, età, contatti stratigrafici | **layer WMS** sovrapposto alla mappa (§7.2) |
| **ISPRA** — carta idrogeologica, inventario dei fenomeni franosi, censimento dei sinkhole | acquiferi, permeabilità, rischi geologici | layer WMS + compilazione di `<rischi>` e `<idrogeologia>` |
| **Note illustrative dei fogli geologici** (PDF) | descrizione discorsiva delle formazioni | allegato all'ipogeo o opera in `bibliografia_generale.xml` |
| **Geoportali regionali** (cartografia geologica di dettaglio, CTR) | maggior dettaglio del 1:50.000 | layer WMS regionale aggiunto da ADM |
| **Bibliografia specialistica** | genesi, morfologie, datazioni | sezione bibliografia (§6.12) |

`config.xml.dist` verrà distribuito con questi layer **già preconfigurati e disattivati**, pronti da attivare con un clic. Gli endpoint esatti dei servizi vanno verificati in fase 4: non li fisso ora nel documento perché gli URL dei servizi WMS pubblici cambiano nel tempo e preferisco scriverli dopo averli interrogati, non a memoria.

**Compilazione assistita via `GetFeatureInfo`** (fase 6b, opzionale): il protocollo WMS prevede l'operazione `GetFeatureInfo`, che restituisce gli attributi del poligono in un punto. CATAGEO può quindi, dalle coordinate dell'ipogeo, interrogare lato server il servizio geologico e **proporre** litologia, formazione ed età da confermare o correggere, tracciando in `<fonte modalita="GetFeatureInfo">` che il dato è di provenienza cartografica e non di osservazione diretta.

Due avvertenze che ne condizionano la realizzazione, per cui la funzione è opzionale e degradante:

- Richiede chiamate HTTP in uscita dal server (`curl` o `allow_url_fopen`), che diversi hosting economici bloccano. Se non disponibili, la funzione si disattiva da sé e resta la compilazione manuale con il layer WMS a video.
- Il dato così ottenuto ha la precisione della scala di origine: il 1:50.000 inquadra correttamente la formazione regionale, non distingue una lente di materiale diverso di dieci metri. Va trattato come inquadramento, non come rilevamento in cavità — ed è la ragione per cui `<fonte>` è un campo obbligatorio e non un dettaglio.

---

## 7. Cartografia

### 7.1 Scelta del provider — raccomandazione

| | OpenStreetMap + Leaflet | Google Maps |
|---|---|---|
| Libreria JS self-hosted | ✅ Leaflet (~140 KB, si scarica e si versiona) | ❌ obbligatorio caricare `maps.googleapis.com` |
| Chiave API / carta di credito | ❌ non serve | ✅ richiesta, con quota a consumo |
| Vincolo "nessuna CDN" | rispettato (solo le *tile* raster vengono dal tile server) | violato |
| Layer WMS | nativo | tramite override |
| KML | conversione server-side (§7.3) | nativo |

**Decisione D4: entrambi i provider sono implementati in v1**, con OpenStreetMap come default di installazione e Google Maps selezionabile in configurazione (vedi §16.1 per la deroga al vincolo no-CDN). Nota: anche con OSM le *immagini* delle tile arrivano da un server esterno; è previsto un provider `custom` per puntare a un tile server proprio o a tile locali per installazioni completamente offline.

#### 7.1.1 Astrazione del provider

Per non duplicare la logica applicativa, la mappa è pilotata da un'interfaccia JavaScript unica, `CatageoMappa`, con due implementazioni interscambiabili (`CatageoMappaLeaflet`, `CatageoMappaGoogle`). Metodi previsti:

```
inizializza(contenitore, opzioni)      aggiungiMarker(punti)        pulisciMarker()
impostaBaseLayer(id)                   aggiungiOverlayWms(cfg)      rimuoviOverlay(id)
aggiungiGeoJson(id, geojson, stile)    disegnaCerchio(lat,lon,r)    adattaVista(bounds)
onClickMappa(callback)                 onSpostaCerchio(callback)
```

Il PHP produce sempre gli stessi dati (JSON dei punti, GeoJSON dei tracciati, elenco layer da `config.xml`): il provider cambia solo il rendering. Il clustering usa un'implementazione propria basata su griglia server-side per zoom bassi, così da non dipendere né da `Leaflet.markercluster` né da `MarkerClusterer` di Google e restare identica sui due provider. I layer **WMS** su Google sono realizzati con un `ImageMapType` che costruisce le `GetMap` per tile; su Leaflet con `L.tileLayer.wms` nativo.

> **Scostamento in fase 4 — l'astrazione non è stata scritta.** `catageo-mappa.js` usa Leaflet direttamente. Un'interfaccia con una sola implementazione non ha modo di essere sbagliata nel punto giusto: si scopre quale sia il confine utile solo scrivendo la seconda implementazione, e fino a quel momento si paga un livello di indirezione per un beneficio ipotetico. Il contratto che conta è già indipendente dal provider e sta dove serve, cioè **fra PHP e browser**: `Mappa::perBrowser()` descrive layer, centro, zoom e colori senza nominare Leaflet, e `?p=geojson` è GeoJSON standard. Tutto il codice che conosce Leaflet sta in un unico file, quindi in fase 4b l'astrazione si estrae da un'implementazione funzionante invece di essere indovinata ora. **Decisione da confermare al committente.**

### 7.2 Funzionalità mappa

- Mappa generale con marker di tutti gli ipogei visibili all'utente, **clustering** lato client per gestire migliaia di punti.
- **Filtro per catalogo**: catalogo attivo, selezione di più cataloghi o tutti. Marker distinguibili per catalogo, così un'installazione multi-catalogo resta leggibile a colpo d'occhio.
- Marker colorati/iconizzati per natura e tipologia; legenda.
- Popup con codice, nome, miniatura di copertina, link alla scheda.
- Selettore base layer + pannello overlay con i layer WMS di `config.xml`, con opacità regolabile.
- **Aggiunta layer WMS a runtime** da parte di ADM (URL, layers, formato, versione) con persistenza in `config.xml`; per OPE aggiunta temporanea di sessione.
- **Layer tematici preconfigurati** in `config.xml.dist`, disattivati per default e attivabili con un clic: cartografia geologica, idrogeologica e dei rischi (§6.16.1). Sulla mappa diventano lo strumento con cui si compila la sezione geologica.
- Filtri applicati alla mappa provenienti dalla ricerca (§10): la mappa mostra il risultato della query.
- Disegno del cerchio di ricerca per raggio, con trascinamento del centro.
- Mappa nella scheda del singolo ipogeo, con ingressi multipli, tracciato KML dei rilievi e punti del diario di esplorazione.
- Esportazione dei risultati in KML e GeoJSON.

### 7.2.1 Stato di realizzazione (fase 4)

| Funzione di §7.2 | Stato | Dove |
|---|---|---|
| Mappa generale, marker degli ipogei visibili | fatto | `app/pagine/mappa.php` |
| Clustering a griglia in coordinate schermo | fatto | `catageo-mappa.js`, cella 64 px, singoli oltre lo zoom 17 |
| Filtro per catalogo, natura, stato d'accesso, testo | fatto, **lato client** sui dati già scaricati | filtri immediati, nessun ricaricamento |
| Marker per natura, legenda | fatto | colore = natura, tratteggio = ingresso non praticabile |
| Popup con codice, nome, dati sintetici, link alla scheda | fatto | miniatura di copertina rinviata alla fase 5, quando esisteranno le foto |
| Selettore base layer + pannello overlay WMS | fatto | da `config.xml`, opacità per layer rispettata |
| Mappa nella scheda | fatto | punto singolo, o cerchio d'area se le coordinate sono offuscate |
| Esportazione GeoJSON | fatto | `?p=geojson`, con gli stessi filtri dell'elenco |
| Lettura delle coordinate sotto il puntatore | fatto, **non previsto in analisi** | gradi decimali e UTM, con fuso ricavato dalla longitudine |
| Aggiunta layer WMS a runtime dall'interfaccia | **da fare** | oggi si dichiarano in `config.xml`; l'interfaccia di gestione va con la fase 9 (strumenti ADM) |
| Cursore opacità per layer | **da fare** | l'attributo `opacita` è già letto e applicato, manca il comando |
| Cerchio di ricerca per raggio, trascinabile | **da fare** | fase 8, insieme alla ricerca geografica |
| Tracciato KML dei rilievi, punti dei diari | **da fare** | fasi 6 e 7. `window.CATAGEO.mappa` è il punto d'innesto già predisposto |
| Layer tematici geologici preconfigurati | **da fare** | fase 6b: gli URL dei servizi vanno verificati uno per uno prima di scriverli in `config.xml.dist` (§6.16.1) |
| Marker distinguibili per catalogo | **da fare** | oggi il catalogo si filtra ma non si distingue nel simbolo; il colore è impegnato dalla natura, servirà una seconda variabile visiva |

#### 7.2.2 Configurazione dei layer

Ogni layer è un elemento `<layer>` sotto `<baseLayers>` (sfondi, mutuamente esclusivi) oppure `<overlayLayers>` (sovrapposizioni). Attributi letti da `Mappa`:

| Attributo | Vale per | Significato |
|---|---|---|
| `id` | tutti | identificativo; se assente viene generato |
| `nome` | tutti | etichetta nel selettore |
| `tipo` | tutti | `tms` (tile XYZ) o `wms`; qualunque altro valore ricade su `tms` |
| `url` | tutti | **solo `http`/`https`**: uno schema diverso viene scartato, così un errore di configurazione non diventa un problema di sicurezza |
| `attribuzione` | tutti | testo obbligatorio per rispettare la licenza della cartografia |
| `minZoom`, `maxZoom` | tutti | limiti di scala |
| `attivo` | tutti | `1` per accenderlo all'apertura |
| `opacita` | tutti | `0`–`1`; fuori intervallo viene riportato dentro |
| `sottodomini` | `tms` | lettere per il segnaposto `{s}`, default `abc` |
| `layers` | `wms` | nomi dei layer richiesti al servizio: **obbligatorio**, senza di essi il layer viene scartato invece di mostrare riquadri vuoti |
| `formato` | `wms` | default `image/png` |
| `versione` | `wms` | default `1.3.0` |
| `trasparente` | `wms` | `0` per un WMS opaco; altrimenti trasparente |

Le origini dei layer alimentano la **Content-Security-Policy** emessa da `bootstrap.php`: aggiungere un servizio in `config.xml` è sufficiente, la policy si adegua da sé. Il segnaposto `{s}` diventa un carattere jolly di sottodominio. Se la lettura della configurazione cartografica fallisce, la policy resta quella restrittiva e il guasto viene registrato nel log: il sintomo altrimenti sarebbe soltanto una mappa senza sfondo.

### 7.3 KML sui rilievi

Un rilievo con `<visualizzaInMappa>1</visualizzaInMappa>` e formato KML/KMZ/GPX viene sovrapposto alla mappa 2D. Implementazione: **conversione server-side in GeoJSON** (`kml2geojson.php` basato su `DOMDocument`), che consuma Leaflet nativamente — nessun plugin JS aggiuntivo, KMZ decompresso con `ZipArchive` (con fallback se assente). Le geometrie supportate: `Point`, `LineString`, `Polygon`, `MultiGeometry`, con stili di base da `<Style>`.

---

## 8. Ruoli e permessi

| Funzione | ADM | OPE | USR |
|---|:--:|:--:|:--:|
| Consultazione schede pubbliche | ✅ | ✅ | ✅ |
| Consultazione coordinate esatte di ipogei riservati | ✅ | ✅ | ❌ |
| Consultazione schede in bozza | ✅ | ✅ | ❌ |
| Consultazione ubicazione delle colonie di chirotteri riservate | ✅ | ✅ | ❌ |
| Ricerca ed esportazione risultati | ✅ | ✅ | ✅ (solo pubblici) |
| Creazione / modifica scheda | ✅ | ✅ | ❌ |
| Caricamento allegati/foto/video/rilievi | ✅ | ✅ | ❌ |
| Redazione esplorazioni | ✅ | ✅ | ❌ |
| Compilazione bibliografia, archeologia, geologia | ✅ | ✅ | ❌ |
| Inserimento misure scientifiche e osservazioni biologiche | ✅ | ✅ | ❌ |
| Importazione massiva di serie da datalogger | ✅ | ✅ | ❌ |
| Cancellazione risorse | ✅ | ✅ (solo proprie) | ❌ |
| Cancellazione ipogeo | ✅ | ❌ | ❌ |
| Modifica codice catastale | ✅ | ❌ | ❌ |
| **Migrazione di un ipogeo tra cataloghi** | ✅ | ❌ | ❌ |
| **Creazione e configurazione di un catalogo** (serie, contatori) | ✅ | ❌ | ❌ |
| Anagrafiche gruppi / esploratori | ✅ | ✅ (crea, non cancella) | ❌ |
| Anagrafiche grandezze, periodi storici, bibliografia generale | ✅ | ✅ (crea, non cancella) | ❌ |
| Gestione utenti | ✅ | ❌ | ❌ |
| Configurazione, layer WMS permanenti, tipologie | ✅ | ❌ | ❌ |
| Strumenti: ricostruzione indici, verifica integrità, backup | ✅ | ❌ | ❌ |

**Decisione D2 — login sempre obbligatorio**: in v1 nessuna pagina è raggiungibile senza sessione autenticata, incluse mappa, ricerca e `scarica.php`. Il parametro `<accessoAnonimo>` è predisposto in `config.xml` per una eventuale apertura pubblica futura, ma resta a `0` e non viene esposto in interfaccia.

---

## 9. Moduli funzionali

### 9.1 Scheda ipogeo

Vista a schede (tab Bootstrap): **Dati · Descrizione · Mappa · Foto · Rilievi · Allegati · Video · Esplorazioni · Bibliografia · Dati scientifici · Biospeleologia · Archeologia · Geologia · Storico**. I tab delle sezioni vuote restano visibili ma segnalati come non compilati: la scheda dichiara sempre cosa manca, invece di nascondere le lacune.

Editing con form generato dal template standard, campi obbligatori minimi: catalogo, nome, natura, tipologia, coordinate (il codice è assegnato dal sistema o inserito a mano se il catalogo lo consente). Salvataggio con validazione XSD, ricalcolo delle sintesi di §6.8, aggiornamento indice, log della modifica. Stampa/PDF della scheda tramite CSS `@media print` (nessuna libreria PDF, che sarebbe pesante su hosting economico), con selezione delle sezioni da includere.

In testa alla scheda una **barra di avvisi** raccoglie ciò che chi programma un'uscita deve sapere prima di leggere il resto: vincolo archeologico con autorizzazione necessaria (§6.15), periodo critico per i chirotteri in corso (§6.14), rischi geologici rilevanti (§6.16), stato di accesso chiuso o interrato, permessi di proprietà.

### 9.2 Allegati

Upload multiplo con progressivo automatico, metadati compilabili, controllo MIME reale (`finfo`) oltre all'estensione, download **sempre mediato** da `scarica.php?codice=…&sez=AL&prog=1` che verifica i permessi e forza `Content-Disposition`.

### 9.3 Foto

Galleria con miniature (`_mini/`), lightbox, riordino, scelta della copertina, geotag opzionale (letto da EXIF se presente, in fase 2), rotazione automatica da EXIF `Orientation`. Se `gd` non è disponibile si servono le immagini originali con dimensionamento CSS e avviso in diagnostica.

### 9.4 Rilievi 2D / 3D

- **2D**: PDF (viewer nativo del browser in `<iframe>`), immagini raster/SVG con zoom-pan, DXF/DWG solo come download.
- **Sulla mappa**: KML/KMZ/GPX come da §7.3.
- **3D**: viewer three.js self-hosted per **PLY, OBJ, STL, GLTF/GLB** (loader inclusi), con orbit control, wireframe, assi, misura approssimativa della bounding box. Formati topografici specialistici (Therion `.th/.th2`, Survex `.3d`, VisualTopo `.tro`, Compass `.plt`) sono **archiviati e scaricabili**, ma la visualizzazione richiede l'esportazione da parte dell'operatore in KML (per la mappa) e/o PLY/OBJ (per il 3D). Questa è una scelta deliberata: implementare parser di quei formati in PHP/JS non è sostenibile in v1.

### 9.5 Video

Player HTML5 per MP4/WebM/OGG. Per file grandi, campo `<urlEsterno>` per riferire video ospitati esternamente senza consumare lo spazio dell'hosting. Streaming con supporto `Range` in `scarica.php` per permettere il seek.

### 9.6 Esplorazioni

Editor del diario con voci ordinabili, ciascuna con ora, coordinate (selezionabili da mappa) e foto richiamate dalla galleria. Selezione gruppi ed esploratori da anagrafica con autocomplete e possibilità di censire un nuovo esploratore in linea. Viste trasversali: *diario per gruppo*, *diario per esploratore*, *cronologia esplorativa* di tutto il catasto.

### 9.7 Anagrafiche

CRUD di gruppi speleologici, esploratori, tipologie. Cancellazione impedita se l'elemento è referenziato (integrità referenziale verificata sugli indici); disponibile la disattivazione (`<attivo>0</attivo>`).

### 9.8 Bibliografia

Gestione delle voci nei tre tipi di §6.12, con ricerca nel catalogo generale durante l'inserimento per non creare duplicati. Citazioni richiamabili per sigla (`BB001`) dalle altre sezioni. Esportazione della bibliografia di un ipogeo o di una selezione in formato testuale e **BibTeX**, utile a chi scrive articoli. Strumento di verifica dei link con esito storicizzato.

### 9.9 Dati scientifici

Elenco delle serie con periodo, numero di letture e ultima misura. Inserimento di una lettura singola o **importazione di un CSV da datalogger** con mappatura guidata delle colonne, anteprima delle prime righe, riconoscimento del formato di data e controllo dei valori contro i limiti di plausibilità di `grandezze.xml` (fuori intervallo non blocca: propone il flag `sospetto`). Riepiloghi statistici e **grafici SVG generati in PHP**, con confronto fra serie della stessa grandezza in punti diversi. Esportazione della serie o dell'intera sezione.

### 9.10 Biospeleologia

Registro delle osservazioni con ricerca per taxon e gestione separata delle colonie di chirotteri, con serie storica dei conteggi e grafico dell'andamento. Evidenza automatica del periodo critico e applicazione della riservatezza di colonia indipendente da quella dell'ipogeo. Vista trasversale *"colonie del catasto"* con stato e trend, che è il dato che un ente di tutela chiede più spesso.

### 9.11 Archeologia

Inquadramento cronologico con tendina da `periodi_storici.xml`, elenco delle evidenze con collegamento a foto, rilievi e bibliografia, blocco tutela con allegato del provvedimento, registro delle indagini collegate alle esplorazioni. Vista trasversale *"ipogei per periodo storico"*, che su un catalogo di cavità artificiali diventa il principale criterio di lettura dell'archivio.

### 9.12 Geologia

Compilazione dell'inquadramento con i layer cartografici a video e, dove l'hosting lo consente, **proposta automatica via `GetFeatureInfo`** (§6.16.1) con tracciamento della fonte. Registro delle morfologie osservate con foto, blocco idrogeologico collegato alle serie di misure, elenco dei campioni prelevati con finalità ed esito.

### 9.13 Cataloghi e migrazione

Elenco dei cataloghi con numero di ipogei, serie di codifica e stato dei contatori. Creazione di un catalogo con configurazione guidata delle serie e **anteprima del codice** che verrebbe generato per una data combinazione di natura, tipologia e regione: le regole di codifica sono la parte più facile da sbagliare, quindi si verificano prima di censire.

**Migrazione** (§5.5) singola o multipla, con anteprima dei nuovi codici, conferma esplicita, backup preventivo e tracciato in `migrazioni.csv`. Ricerca per **codice storico**: digitando un codice dismesso si arriva alla scheda corrente con l'avviso della migrazione avvenuta.

### 9.14 Strumenti (ADM)

Ricostruzione indici · verifica integrità archivio (XML non validi, file orfani, riferimenti rotti, codici duplicati fra cataloghi, contatori disallineati rispetto ai codici presenti, serie CSV orfane del loro descrittore, cartelle non conformi allo standard) · backup ZIP dell'archivio, per intero o per singolo catalogo · import/export CSV massivo · verifica dei link bibliografici · diagnostica ambiente (versione PHP, interi a 64 bit, estensioni, permessi di scrittura, limiti di upload e `post_max_size`, disponibilità di chiamate HTTP in uscita).

---

## 10. Ricerca

Modalità combinabili in AND, tutte eseguite sull'indice CSV con scansione in streaming (`fgetcsv`), senza caricare l'intero indice in memoria:

1. **Testuale** — nome parziale case/accent-insensitive (normalizzazione con `mb_strtolower` + traslitterazione), su nome, sinonimi, codice, comune, località. Include i **codici storici** e i codici esterni: chi cerca `LA297` dopo una migrazione trova comunque la scheda. Opzione "cerca anche nelle descrizioni" che estende la ricerca ai `Dati.xml` (più lenta, con avviso).
2. **Per catalogo** — catalogo attivo, selezione multipla o tutti i cataloghi.
3. **Per attributi** — natura, tipologia, sottotipologia, stato, regione/provincia/comune, stato accesso, stato scheda, presenza di risorse (ha foto / ha rilievi / ha KML / ha 3D / ha bibliografia), intervalli numerici su sviluppo, dislivello, quota, intervallo di date di censimento.
4. **Per contenuti scientifici e specialistici** — presenza di serie di misure per una data grandezza, presenza di colonie di chirotteri, specie osservata, **periodo storico** archeologico (con ricerca per intervallo di anni grazie agli estremi di `periodi_storici.xml`), presenza di vincolo, tipo di genesi e litologia.
5. **Geografica** — punto (lat/lon inseriti, scelti su mappa, o presi dalla posizione del browser) + raggio in metri/km. Algoritmo: pre-filtro con **bounding box** sui campi `lat`/`lon` dell'indice (rapidissimo), poi distanza esatta **haversine** sui candidati, ordinamento per distanza crescente. Raggio predefinito e massimo configurabili.

I criteri di cui al punto 4 che non sono coperti da una colonna dell'indice vengono risolti in seconda passata sui soli candidati sopravvissuti ai filtri precedenti, aprendo il file di sezione interessato. È il compromesso che tiene l'indice di dimensioni ragionevoli senza rinunciare alla ricerca specialistica: un filtro per specie su 3.000 ipogei apre solo i file di biospeleologia dei pochi che hanno superato gli altri criteri.

Risultati presentati in tre viste commutabili — **tabella** (ordinabile, paginata), **schede/card**, **mappa** — con esportazione CSV, KML, GeoJSON, e possibilità di applicare alla selezione un'azione massiva (migrazione tra cataloghi, esportazione, stampa).

---

## 11. Interfaccia utente

Bootstrap 5.3 + Bootstrap Icons, entrambi self-hosted in `assets/vendor/`. Nessun build step, nessun npm in produzione: si usano i file distribuiti `bootstrap.min.css` e `bootstrap.bundle.min.js`.

Layout: navbar superiore (Mappa · Ipogei · Ricerca · Esplorazioni · Anagrafiche · Strumenti · Utente), contenuto in container fluido, footer con nome catasto e versione. Tema chiaro/scuro tramite l'attributo nativo `data-bs-theme`. Responsive: la mappa occupa l'altezza disponibile su desktop e un blocco a proporzione fissa su mobile. Personalizzazioni in `assets/css/catageo.css`, senza ricompilare Sass.

---

## 12. Sicurezza

| Rischio | Contromisura |
|---|---|
| Accesso diretto ai file dell'archivio via HTTP | `dati/.htaccess` con `Require all denied` + `index.html` vuoto; download solo via `scarica.php`. **Raccomandato**: spostare `dati/` fuori dal webroot e puntarlo da `<percorsi><dati>`, dove l'hosting lo consente |
| Path traversal nei parametri | whitelist rigorosa: codice validato con regex del formato, sezione da enum, progressivo numerico; `realpath()` con verifica che il risultato sia sotto la root dell'archivio |
| Upload di file eseguibili | doppia verifica estensione + MIME reale, blacklist `php php3 phtml phar cgi pl py sh htaccess`, `.htaccess` con `php_flag engine off` nelle cartelle dell'archivio, nomi file sanitizzati, nessun permesso di esecuzione |
| Password | `password_hash()` con BCRYPT cost 12; nessuna password in chiaro o reversibile in `utenti.xml` |
| Brute force | contatore tentativi per utente + blocco temporizzato, ritardo progressivo, log degli accessi |
| Session hijacking | `session_regenerate_id()` al login, cookie `HttpOnly` + `SameSite=Strict` (+ `Secure` se HTTPS), scadenza per inattività |
| CSRF | token per sessione su tutte le richieste POST |
| XSS | escaping sistematico in output (`htmlspecialchars` con `ENT_QUOTES`); nei campi di testo esteso whitelist di tag ristretta |
| XXE / XML bomb | `libxml_disable_entity_loader` / `LIBXML_NONET | LIBXML_NOENT`, nessuna risoluzione di entità esterne |
| Divulgazione di ubicazioni sensibili | livello `<riservatezza>` per ipogeo: `coordinate_offuscate` arrotonda la posizione con jitter deterministico entro il raggio configurato per gli utenti USR; `riservata` nasconde completamente la scheda ai USR |
| Divulgazione di roost di chirotteri | riservatezza **di colonia**, indipendente dall'ipogeo e prevalente su di esso (§6.14): un ipogeo pubblico può avere colonia invisibile ai USR. Motivazione conservazionistica, non solo di privacy |
| Testi e serie senza limiti usati per saturare lo spazio | i testi illimitati (D6) sono ammessi solo a utenti autenticati OPE/ADM, quindi non sono una superficie anonima. Restano attivi i limiti di `post_max_size` e la quota complessiva dell'hosting; la diagnostica riporta lo spazio residuo dell'archivio e gli strumenti segnalano le serie CSV più grandi |
| Perdita dati | storico automatico delle schede, backup ZIP on demand (per intero o per singolo catalogo), istruzioni di backup nella documentazione |

---

## 13. Convenzioni di codice

Testata obbligatoria in **ogni** file PHP:

```php
<?php
/**
 * ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: app/lib/Ipogeo.php
 *  Descrizione ..: Gestione della scheda ipogeo: lettura, validazione,
 *                  scrittura atomica, storicizzazione e rinomina.
 *  Versione .....: 1.0.0
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: © 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  1.0.0  2026-08-03  D.Candela  Prima stesura.
 * ============================================================================
 */
```

Altre convenzioni:

- Classi in `StudlyCase`, metodi in `camelCase`, costanti in `UPPER_SNAKE`, file di classe omonimi della classe.
- Commenti e identificatori di dominio **in italiano** (ipogeo, esplorazione, rilievo); termini tecnici standard in inglese dove consolidato.
- Docblock su ogni classe e metodo pubblico, con `@param`/`@return`/`@throws`.
- `declare(strict_types=1)` in tutti i file.
- Nessun `require` sparso: autoload semplice in `bootstrap.php`.
- Versionamento **semver** dell'applicativo in `config.xml` e in `CHANGELOG.md`; la versione dei singoli file segue la propria cronologia.
- Ogni XML di dominio porta `versioneSchema`, per permettere migrazioni future.

---

## 14. Repository GitHub

- Repo: `darcan99/catageo`, **pubblico**, branch principale `main`, licenza **GNU GPL-3.0** (`LICENSE` con testo integrale, intestazione di licenza in ogni file PHP).
- `.gitignore`: `config.xml`, `dati/`, `*.tmp`, `*.lock`, `_mini/`, `_log/`, `installato.txt`.
- `.gitattributes` con `* text=auto eol=lf`: il codice viene distribuito su hosting Linux, il repository conserva sempre LF anche sviluppando da Windows.
- Versionati: codice, `config.xml.dist`, XSD, documentazione, vocabolari precaricati (`grandezze.xml`, `periodi_storici.xml`, `tipologie.xml` di esempio), `dati_esempio/` con 2–3 ipogei dimostrativi in due cataloghi distinti, così che l'installazione dimostri subito il multi-catalogo.
- `CHANGELOG.md` in formato *Keep a Changelog*; tag `v1.0.0`, `v1.1.0`, …
- Installazione = copia FTP della cartella + copia di `config.xml.dist` in `config.xml` + `installa.php` guidato (crea l'albero di `dati/`, verifica permessi ed estensioni, crea il primo utente ADM, poi si autodisabilita).

---

## 15. Piano di sviluppo

| Fase | Contenuto | Esito verificabile |
|---|---|---|
| **0** | Repo, struttura cartelle, docs, vendor (Bootstrap/Leaflet/three), `config.xml.dist`, XSD | Il repo si clona ed espone una pagina di diagnostica ambiente |
| **1** | Core: `Config`, `Xml`, `Csv`, `Log`, `Auth`, layout Bootstrap, login, gestione utenti, `installa.php` | Login funzionante con i tre livelli |
| **2** | Anagrafiche: gruppi, esploratori, tipologie, grandezze, periodi storici | CRUD completo con integrità referenziale |
| **2b** | Cataloghi: `catalogo.xml`, scoperta automatica, serie di codifica, anteprima del codice, selettore di catalogo attivo | Due cataloghi coesistenti con contatori indipendenti |
| **3** | Ipogei: template scheda, CRUD, assegnazione codice dalle serie, indice CSV, `codici.csv`, storico | Censimento di un ipogeo end-to-end nel catalogo scelto |
| **4** | Mappa: astrazione `CatageoMappa`, implementazione Leaflet/OSM, marker, cluster, base layer, overlay WMS, mappa di scheda | Tutti gli ipogei visibili e filtrabili su mappa |
| **4b** | Implementazione provider Google Maps sulla stessa interfaccia + selettore in configurazione | Commutazione del provider senza altre modifiche |
| **5** | Risorse: allegati, foto (+miniature), video, download mediato | Upload e consultazione dei media |
| **6** | Rilievi: 2D, KML→GeoJSON su mappa, viewer 3D three.js | Un rilievo KML visibile su mappa e un PLY nel viewer |
| **6b** | Geologia: sezione dedicata, layer cartografici preconfigurati, compilazione assistita `GetFeatureInfo` con degradazione se l'hosting blocca le chiamate in uscita | Inquadramento geologico compilato dalla mappa |
| **7** | Esplorazioni: diari, partecipanti, voci con coordinate e foto, viste per gruppo/esploratore | Diario completo pubblicato su scheda |
| **7b** | Bibliografia: catalogo generale, tre tipi di voce, citazioni per sigla, export BibTeX, verifica link | Voce condivisa fra due ipogei e citata da una evidenza |
| **7c** | Dati scientifici: punti di misura, serie CSV, import da datalogger, statistiche, grafici SVG server-side | Serie da datalogger importata e graficata |
| **7d** | Biospeleologia e archeologia: osservazioni, colonie di chirotteri con conteggi e avviso periodo critico, evidenze, tutela, viste per periodo storico | Barra avvisi della scheda completa e funzionante |
| **8** | Ricerca: testuale (inclusi codici storici), per catalogo, per attributi, specialistica, geografica per raggio, esportazioni | Tutte le modalità combinabili, con export |
| **8b** | Migrazione tra cataloghi: singola e multipla, anteprima codici, risoluzione dei codici storici, tracciato | Ipogeo migrato con vecchio codice ancora risolvibile |
| **9** | Strumenti ADM: ricostruzione indici, verifica integrità, backup per catalogo, import/export CSV | Archivio verificabile e ripristinabile |
| **10** | Rifinitura: stampa scheda, tema scuro, manuale utente, dati di esempio, tag `v1.0.0` | Release installabile |
| **11** | *(post-sviluppo)* Acquisizione dati da fonti pubbliche: censimento delle fonti attendibili, verifica delle licenze, importatori dedicati | Un catalogo popolato da fonte esterna, con `<origine>` tracciata |

Al termine di ogni fase: commit, aggiornamento `CHANGELOG.md`, incremento delle versioni dei file toccati.

**Nota sulla fase 11**: è deliberatamente successiva al rilascio e ha un prerequisito non tecnico. I dati dei catasti speleologici regionali e delle banche dati di enti pubblici **non sono automaticamente riutilizzabili**: molti sono coperti da licenze restrittive o da accordi fra federazioni, e le ubicazioni delle cavità sono spesso riservate proprio per scelta di tutela. Prima di scrivere qualunque importatore va verificata la licenza di ciascuna fonte e, dove serve, ottenuta l'autorizzazione: il campo `<licenzaDati>` in `catalogo.xml` (§6.2) esiste per registrare questa verifica. L'alternativa — scaricare dati altrui perché tecnicamente accessibili — esporrebbe il progetto e chi lo installa, e per un catasto pubblico su GitHub è un rischio da non correre.

---

## 16. Decisioni assunte

Decisioni approvate dal committente il 2026-08-03 (D1–D4) e il 2026-08-04 (D5–D12):

| # | Tema | Decisione | Impatto |
|---|---|---|---|
| D1 | Metadati delle risorse | **Un XML di indice per ogni sottocartella** (`[codice] - Foto.xml`, `- Allegati.xml`, `- Video.xml`, `- Rilievi.xml`, `- Esplorazioni.xml`) | Struttura di §6.9 confermata. Cartelle autodescrittive, `Dati.xml` snello, scritture non conflittuali |
| D2 | Accesso | **Login sempre obbligatorio**, nessun accesso anonimo | `<accessoAnonimo>` resta in `config.xml` con default `0` ma non viene esposto in interfaccia in v1. Ogni pagina, incluse mappa e `scarica.php`, richiede sessione autenticata |
| D3 | Repository e licenza | **Pubblico**, licenza **GPL-3.0** | `LICENSE` con testo GPL-3.0; testata di ogni file PHP con la clausola di licenza breve; `README.md` con badge e note di copyright © 2026 Dario Candela |
| D4 | Cartografia | **OpenStreetMap + Google Maps**, entrambi implementati in v1 | Si introduce un livello di astrazione `MapProvider` (§7.1.1). Google richiede chiave API e caricamento dello script dal dominio Google: deroga esplicita e documentata al vincolo "nessuna CDN", attiva solo se il provider Google è selezionato |
| D5 | Cataloghi multipli | Un'installazione ospita **più cataloghi**, ciascuno cartella autonoma con `catalogo.xml` e contatori propri | Riscritta la struttura dell'archivio (§4) e la codifica (§5). Indice unico con colonna `catalogo`. Fase 2b nel piano |
| D6 | Testi | **Nessun limite di lunghezza** su descrizioni, diari, note, abstract | Nessun `maxlength` in interfaccia né negli XSD, testi in `CDATA`, nessun troncamento in salvataggio, estratti calcolati a runtime (§3.1) |
| D7 | Progressivo del codice | Padding come **soglia minima e non tetto**; nessun limite numerico oltre `PHP_INT_MAX` | Tabella di comportamento in §5.3. Contatore gestito come stringa numerica, mai in virgola mobile |
| D8 | Serie di misure | Descrittore in **XML**, letture in **CSV** appendibile, una serie per file | §6.13. Regge le migliaia di righe di un datalogger, si apre in Excel, si accoda senza rileggere il file |
| D9 | Migrazione tra cataloghi | Strumento **previsto in v1**, con memoria del codice di origine | §5.5. `<codiciStorici>` in scheda, `codici.csv` per far risolvere i vecchi codici, log dedicato. Fase 8b |
| D10 | Nuove sezioni di scheda | **Bibliografia, dati scientifici, biospeleologia, archeologia, geologia**, ognuna su file dedicati nella cartella dell'ipogeo | §6.12–6.16. In `Dati.xml` restano solo sintesi ricalcolate, utili a ricerca, mappa e stampa |
| D11 | Ambito geografico | **Italia con apertura all'estero** | `<stato>` ISO 3166-1 in scheda; liste italiane precompilate per regione e provincia, che diventano campi liberi con stato diverso da IT. Un catalogo può essere dedicato alle spedizioni estere |
| D12 | Riservatezza | Confermato il meccanismo a tre livelli, **esteso alle colonie di chirotteri** con riservatezza indipendente e prevalente | §6.14 e §12 |
| D14 | Conversione fra sistemi | **Gauss-Boaga e ED50 vengono convertiti** con i sette parametri di Helmert, dichiarando l'incertezza (~3 m su Roma40, ~10 m su ED50). Ogni sistema è descritto da **una sola definizione in stile proj4**, usata sia dal motore PHP sia da proj4js nel browser, e ampliabile da ADM senza toccare il codice. Il server resta autorevole; proj4js serve all'anteprima dal vivo durante l'inserimento. La correttezza è verificata per confronto incrociato fra le due implementazioni, non per auto-coerenza | `SistemiRiferimento`, `Proiezione`, `docs/prove/coordinate` |
| D13 | Sistemi di riferimento delle coordinate | Forma canonica **sempre** in gradi decimali WGS84, con **memoria del sistema, formato e valore originali**. Inserimento ammesso in gradi decimali, gradi sessagesimali, gradi e minuti decimali e **UTM**; conversione UTM↔geografiche esatta perché sul medesimo ellissoide. I sistemi con datum diverso (Gauss-Boaga/Roma40, UTM ED50) sono ammessi come dato originale ma **non convertiti**: richiedono parametri locali e sbaglierebbero di decine di metri | §6.8 e classe `Coordinate` |

### 16.1 Deroga documentata al vincolo "nessuna CDN"

Il vincolo resta valido per tutte le librerie dell'applicativo (Bootstrap, Bootstrap Icons, Leaflet, three.js: **tutte self-hosted**). L'unica eccezione ammessa è l'API JavaScript di Google Maps, tecnicamente non self-hostabile per licenza. Conseguenze accettate e da riportare nella documentazione utente:

- Con provider `osm` l'applicativo funziona senza alcuna dipendenza da domini terzi, eccetto le immagini delle tile (sostituibili con un tile server proprio tramite provider `custom`).
- Con provider `google` occorrono chiave API, account di fatturazione Google Cloud e connessione al dominio Google; l'assenza di connettività degrada la sola mappa, non il resto dell'applicativo.
- Il provider è selezionabile da ADM in configurazione, con avviso in interfaccia sulle implicazioni.

## 17. Punti ancora aperti

Nessuno bloccante per l'avvio degli sviluppi. Si procede con i default indicati e si corregge in configurazione.

1. **Catalogo di partenza in `config.xml.dist`**: quale sigla, nome ed ente per il catalogo dimostrativo? *Default: un catalogo `DEMO — Catalogo dimostrativo` con una sola serie senza criteri, prefisso `DEMO`, 3 cifre. Così l'installazione parte pulita e il primo catalogo reale lo crei tu dall'interfaccia, senza dover ripulire un esempio.*
2. **Serie di codifica reali del catasto del Lazio**: quante serie servono e con quali criteri e prefissi? Si può definire anche dopo, dall'interfaccia di configurazione del catalogo, prima di censire il primo ipogeo.
3. **Vocabolari precaricati**: `grandezze.xml` e `periodi_storici.xml` sono compilati con la mia proposta (§6.4). Vanno riletti da te e da chi si occupa di monitoraggi: sono modificabili in ogni momento, ma partire con un vocabolario corretto evita di dover riclassificare dati già inseriti.
4. **Determinazione tassonomica in biospeleologia**: serve un elenco chiuso di specie precaricato (almeno per i chirotteri italiani) o si lascia il nome scientifico libero? *Default: campo libero con suggerimento dai valori già inseriti nell'archivio, che si auto-arricchisce senza imporre una lista da manutenere.*
5. **Fase 11**: le fonti pubbliche da cui importare dati vanno individuate insieme, con verifica delle licenze prima di scrivere codice (vedi nota in §15).

---

## 18. Note operative sull'ambiente di sviluppo

- Sulla postazione attuale **PHP non è installato** e **GitHub CLI (`gh`) non è presente**; `git` è disponibile. Per provare l'applicativo in locale serve un PHP (XAMPP/Laragon o `winget install PHP.PHP`) — sufficiente il server integrato `php -S localhost:8000`. In alternativa si sviluppa e si prova direttamente sull'hosting via FTP.
- La creazione del repository su GitHub richiede o l'installazione di `gh`, oppure la creazione manuale del repo vuoto dalla web UI (già autenticata come `darcan99`) e il successivo `git remote add` + `push`.
