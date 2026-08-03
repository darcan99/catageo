# CATAGEO — Catasto Ipogei

## Documento di Analisi

| | |
|---|---|
| **Progetto** | CATAGEO (CATAsto ipoGEi) |
| **Versione documento** | 1.0.0 |
| **Data** | 2026-08-03 |
| **Autore** | Dario Candela — darcan99@gmail.com |
| **Repository** | github.com/darcan99/catageo |
| **Stato** | Bozza per approvazione |

### Cronologia documento

| Versione | Data | Autore | Modifiche |
|---|---|---|---|
| 1.0.0 | 2026-08-03 | Dario Candela | Prima stesura |

---

## 1. Obiettivi

CATAGEO è un'applicazione web PHP per la **gestione di un catasto di cavità artificiali e naturali** (ipogei), progettata per essere installabile su un qualsiasi hosting condiviso economico, **senza database**.

Obiettivi guida, in ordine di priorità:

1. **Portabilità estrema** — funziona con solo PHP + filesystem. Nessun DBMS, nessun servizio esterno, nessuna CDN, nessun `composer install` obbligatorio in produzione.
2. **Dati leggibili e durevoli** — l'archivio deve essere navigabile e comprensibile anche con un file manager e un editor di testo, senza l'applicativo. I dati sopravvivono al software.
3. **Standard imposti** — nomenclatura di cartelle, file e codici rigidamente definita e validata dall'applicativo.
4. **Completezza documentale** — per ogni ipogeo: scheda dati, allegati, foto, video, rilievi 2D/3D, diari di esplorazione.
5. **Visualizzazione cartografica** — mappa con marker, layer WMS aggiuntivi, tracciati dei rilievi da KML.
6. **Manutenibilità** — codice PHP commentato, versionato, con cronologia in testata di ogni file.

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
│  Config · Auth · Ipogeo · Risorse · Indice · Ricerca   │
│  Geo · Xml · Csv · Upload · Log · Anagrafiche          │
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

### 3.2 Indici

Leggere 3.000 file XML per ogni ricerca è insostenibile. Si introduce un livello di **indice denormalizzato in CSV**, rigenerabile in qualsiasi momento dai soli XML (l'indice è cache, **non** è la fonte di verità):

- `dati/_indice/ipogei.csv` — una riga per ipogeo con i campi di ricerca e mappa.
- `dati/_indice/esplorazioni.csv` — una riga per esplorazione.
- `dati/_indice/risorse.csv` — conteggi allegati/foto/video/rilievi per ipogeo.

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
│   ├── config.xsd  utenti.xsd  ipogeo.xsd
│   ├── esplorazione.xsd  risorse.xsd
│   └── gruppi.xsd  esploratori.xsd
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
    ├── tipologie.xml                   tassonomia categorie
    ├── _indice/
    │   ├── ipogei.csv
    │   ├── esplorazioni.csv
    │   └── risorse.csv
    ├── _log/
    │   ├── accessi.csv
    │   └── modifiche.csv
    ├── _tmp/                            upload temporanei
    └── ipogei/
        ├── LA297 - Grotta dei Ragni/
        │   ├── LA297 - Dati.xml
        │   ├── LA297 - Allegati/
        │   │   ├── LA297 - Allegati.xml
        │   │   └── LA297-AL001-Relazione tecnica 1998.pdf
        │   ├── LA297 - Foto/
        │   │   ├── LA297 - Foto.xml
        │   │   ├── LA297-FO001-Ingresso principale.jpg
        │   │   └── _mini/LA297-FO001-Ingresso principale.jpg
        │   ├── LA297 - Video/
        │   │   ├── LA297 - Video.xml
        │   │   └── LA297-VI001-Discesa pozzo.mp4
        │   ├── LA297 - Rilievi/
        │   │   ├── LA297 - Rilievi.xml
        │   │   ├── LA297-RI001-Pianta generale.pdf
        │   │   ├── LA297-RI002-Poligonale.kml
        │   │   └── LA297-RI003-Modello 3D.ply
        │   ├── LA297 - Esplorazioni/
        │   │   ├── LA297 - Esplorazioni.xml
        │   │   └── LA297-ES001-Prima ricognizione.xml
        │   └── LA297 - Storico/
        │       └── LA297 - Dati.20260803-181200.xml
        └── LA298 - Cunicolo di Via Latina/
```

### 4.1 Regole di nomenclatura (normative)

| Elemento | Formato | Esempio |
|---|---|---|
| Cartella ipogeo | `[codice] - [nome ipogeo]` | `LA297 - Grotta dei Ragni` |
| Scheda dati | `[codice] - Dati.xml` | `LA297 - Dati.xml` |
| Sottocartella risorse | `[codice] - [Sezione]` | `LA297 - Foto` |
| Indice di sezione | `[codice] - [Sezione].xml` | `LA297 - Foto.xml` |
| File risorsa | `[codice]-[SG][NNN]-[nome originale].[ext]` | `LA297-FO001-Ingresso.jpg` |
| Esplorazione | `[codice]-ES[NNN]-[titolo].xml` | `LA297-ES001-Prima ricognizione.xml` |
| Storico scheda | `[codice] - Dati.[YYYYMMDD-HHMMSS].xml` | `LA297 - Dati.20260803-181200.xml` |

**Sigle di sezione (`SG`)**: `AL` Allegati · `FO` Foto · `VI` Video · `RI` Rilievi · `ES` Esplorazioni.

**Progressivo (`NNN`)**: 3 cifre con zero-padding, per sezione e per ipogeo, **mai riutilizzato** anche a seguito di cancellazioni (il progressivo massimo mai assegnato è memorizzato nell'indice di sezione). Oltre 999 elementi si passa a 4 cifre.

**Sanitizzazione del nome file originale**: mantenuto leggibile, ma normalizzato — rimozione dei caratteri vietati da Windows/Linux (`\ / : * ? " < > |`), collasso degli spazi multipli, rimozione dei punti finali, lunghezza massima 120 caratteri, estensione conservata in minuscolo. Gli accenti **sono conservati** (UTF-8) per leggibilità; se `config.xml` imposta `<nomiFileAscii>1</nomiFileAscii>` vengono traslitterati (utile su hosting con filesystem non UTF-8).

**Rinomina dell'ipogeo**: se cambia il nome, la cartella viene rinominata; se cambia il codice, vengono rinominati cartella, sottocartelle e **tutti** i file contenuti (operazione riservata ADM, tracciata nel log, con backup dell'intero albero).

---

## 5. Codice catastale

Il codice è l'identificatore univoco e immutabile (salvo intervento ADM) assegnato al censimento.

**Formato**: `[PREFISSO][PROGRESSIVO]`, entrambi definiti in `config.xml`:

```xml
<codifica>
  <prefisso>LA</prefisso>          <!-- suffisso/sigla del catasto -->
  <cifre>3</cifre>                 <!-- ampiezza minima del progressivo -->
  <separatore></separatore>        <!-- opzionale, es. "-" -> LA-297 -->
  <prossimoProgressivo>299</prossimoProgressivo>
  <consentiCodiceManuale>1</consentiCodiceManuale>
</codifica>
```

- Progressivo assegnato automaticamente al salvataggio della nuova scheda (con lock sul contatore), oppure inserito manualmente dall'operatore se consentito.
- Unicità verificata sia sull'indice sia sull'esistenza della cartella.
- **Codici esterni**: la scheda può riportare N codici di altri catasti (SSI, catasto regionale, catalogo comunale) in `<codiciEsterni>`, senza interferire con il codice interno.
- Il codice compare in ogni nome di file: un file estratto dall'archivio resta sempre riconducibile al proprio ipogeo.

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
  <codifica> ... </codifica>              <!-- vedi §5 -->
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

### 6.2 `utenti.xml`

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

### 6.3 `gruppi_speleologici.xml`

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

### 6.4 `esploratori.xml`

```xml
<esploratori versioneSchema="1.0">
  <esploratore id="E001">
    <cognome>Candela</cognome>
    <nome>Dario</nome>
    <soprannome/>
    <gruppi>
      <gruppo id="G001" dal="1998" al=""/>   <!-- appartenenza storicizzata -->
    </gruppi>
    <email/> <telefono/>
    <qualifiche><qualifica>Istruttore SSI</qualifica></qualifiche>
    <note/>
    <attivo>1</attivo>
  </esploratore>
</esploratori>
```

### 6.5 `tipologie.xml` — tassonomia

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

### 6.6 `[codice] - Dati.xml` — template standard della scheda

Il template è **unico per tutti gli ipogei**: la scheda contiene sempre tutte le sezioni, anche vuote, così che l'archivio sia omogeneo e diffabile.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<ipogeo versioneSchema="1.0">

  <identificazione>
    <codice>LA297</codice>
    <nome>Grotta dei Ragni</nome>
    <sinonimi><sinonimo>Grotta del Diavolo</sinonimo></sinonimi>
    <natura>ART</natura>
    <tipologia>ART-IDR</tipologia>
    <sottotipologia>ART-IDR-CUN</sottotipologia>
    <codiciEsterni>
      <codiceEsterno ente="SSI" catasto="Catasto Grotte Lazio">La 1234</codiceEsterno>
    </codiciEsterni>
  </identificazione>

  <ubicazione>
    <regione>Lazio</regione>
    <provincia>RM</provincia>
    <comune>Roma</comune>
    <localita>Quarto Miglio</localita>
    <indirizzo/>
    <coordinate sistema="EPSG:4326">
      <latitudine>41.856231</latitudine>
      <longitudine>12.532104</longitudine>
      <quota unita="m">62</quota>
      <precisione unita="m">5</precisione>
      <metodo>GPS</metodo>            <!-- GPS | CTR | Google | Stima -->
      <dataRilevamento>2024-05-12</dataRilevamento>
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
    <geologia>
      <litologia>Tufo litoide</litologia>
      <formazione>Unità di Villa Senni</formazione>
      <eta>Pleistocene medio</eta>
    </geologia>
    <idrologia>
      <presenzaAcqua>stagionale</presenzaAcqua>  <!-- assente|stagionale|permanente|allagato -->
      <note/>
    </idrologia>
    <biologia><note/><specie><voce/></specie></biologia>
    <archeologia><note/></archeologia>
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

  <descrizione>
    <sintesi>Cunicolo drenante di età romana …</sintesi>
    <testo>…testo esteso, HTML limitato…</testo>
    <storia>…</storia>
    <note/>
  </descrizione>

  <bibliografia>
    <voce autori="Rossi M." anno="1998" titolo="…" fonte="…" pagine="12-30"/>
  </bibliografia>

  <collegamenti>
    <ipogeoCorrelato codice="LA298" relazione="collegato"/>
  </collegamenti>

  <catasto>
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

### 6.7 Indici di sezione — `[codice] - Foto.xml` (idem Allegati / Video / Rilievi)

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

### 6.8 Esplorazioni — `[codice]-ES001-[titolo].xml`

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

### 6.9 Formato degli indici CSV

`dati/_indice/ipogei.csv` — separatore `;`, delimitatore `"`, prima riga di intestazione:

```
codice;nome;natura;tipologia;sottotipologia;regione;provincia;comune;localita;lat;lon;quota;sviluppo;dislivello;stato_accesso;riservatezza;stato_scheda;n_allegati;n_foto;n_video;n_rilievi;n_esplorazioni;ha_kml;ha_3d;data_censimento;ultima_modifica;cartella
LA297;Grotta dei Ragni;ART;ART-IDR;ART-IDR-CUN;Lazio;RM;Roma;Quarto Miglio;41.856231;12.532104;62;245;-18;aperto;pubblica;pubblicata;1;3;1;3;1;1;1;2024-06-01;2026-08-03T18:12:00;LA297 - Grotta dei Ragni
```

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

### 7.2 Funzionalità mappa

- Mappa generale con marker di tutti gli ipogei visibili all'utente, **clustering** lato client per gestire migliaia di punti.
- Marker colorati/iconizzati per natura e tipologia; legenda.
- Popup con codice, nome, miniatura di copertina, link alla scheda.
- Selettore base layer + pannello overlay con i layer WMS di `config.xml`, con opacità regolabile.
- **Aggiunta layer WMS a runtime** da parte di ADM (URL, layers, formato, versione) con persistenza in `config.xml`; per OPE aggiunta temporanea di sessione.
- Filtri applicati alla mappa provenienti dalla ricerca (§10): la mappa mostra il risultato della query.
- Disegno del cerchio di ricerca per raggio, con trascinamento del centro.
- Mappa nella scheda del singolo ipogeo, con ingressi multipli, tracciato KML dei rilievi e punti del diario di esplorazione.
- Esportazione dei risultati in KML e GeoJSON.

### 7.3 KML sui rilievi

Un rilievo con `<visualizzaInMappa>1</visualizzaInMappa>` e formato KML/KMZ/GPX viene sovrapposto alla mappa 2D. Implementazione: **conversione server-side in GeoJSON** (`kml2geojson.php` basato su `DOMDocument`), che consuma Leaflet nativamente — nessun plugin JS aggiuntivo, KMZ decompresso con `ZipArchive` (con fallback se assente). Le geometrie supportate: `Point`, `LineString`, `Polygon`, `MultiGeometry`, con stili di base da `<Style>`.

---

## 8. Ruoli e permessi

| Funzione | ADM | OPE | USR |
|---|:--:|:--:|:--:|
| Consultazione schede pubbliche | ✅ | ✅ | ✅ |
| Consultazione coordinate esatte di ipogei riservati | ✅ | ✅ | ❌ |
| Consultazione schede in bozza | ✅ | ✅ | ❌ |
| Ricerca ed esportazione risultati | ✅ | ✅ | ✅ (solo pubblici) |
| Creazione / modifica scheda | ✅ | ✅ | ❌ |
| Caricamento allegati/foto/video/rilievi | ✅ | ✅ | ❌ |
| Redazione esplorazioni | ✅ | ✅ | ❌ |
| Cancellazione risorse | ✅ | ✅ (solo proprie) | ❌ |
| Cancellazione ipogeo | ✅ | ❌ | ❌ |
| Modifica codice catastale | ✅ | ❌ | ❌ |
| Anagrafiche gruppi / esploratori | ✅ | ✅ (crea, non cancella) | ❌ |
| Gestione utenti | ✅ | ❌ | ❌ |
| Configurazione, layer WMS permanenti, tipologie | ✅ | ❌ | ❌ |
| Strumenti: ricostruzione indici, verifica integrità, backup | ✅ | ❌ | ❌ |

**Decisione D2 — login sempre obbligatorio**: in v1 nessuna pagina è raggiungibile senza sessione autenticata, incluse mappa, ricerca e `scarica.php`. Il parametro `<accessoAnonimo>` è predisposto in `config.xml` per una eventuale apertura pubblica futura, ma resta a `0` e non viene esposto in interfaccia.

---

## 9. Moduli funzionali

### 9.1 Scheda ipogeo

Vista a schede (tab Bootstrap): **Dati · Descrizione · Mappa · Foto · Rilievi · Allegati · Video · Esplorazioni · Storico**.
Editing con form generato dal template standard, campi obbligatori minimi: codice, nome, natura, tipologia, coordinate. Salvataggio con validazione XSD, aggiornamento indice, log della modifica. Stampa/PDF della scheda tramite CSS `@media print` (nessuna libreria PDF, che sarebbe pesante su hosting economico).

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

### 9.8 Strumenti (ADM)

Ricostruzione indici · verifica integrità archivio (XML non validi, file orfani, riferimenti rotti, codici duplicati, cartelle non conformi allo standard) · backup ZIP dell'archivio · import/export CSV massivo · diagnostica ambiente (versione PHP, estensioni, permessi di scrittura, limiti di upload).

---

## 10. Ricerca

Tre modalità combinabili in AND, tutte eseguite sull'indice CSV con scansione in streaming (`fgetcsv`), senza caricare l'intero indice in memoria:

1. **Testuale** — nome parziale case/accent-insensitive (normalizzazione con `mb_strtolower` + traslitterazione), su nome, sinonimi, codice, comune, località. Opzione "cerca anche nelle descrizioni" che estende la ricerca ai `Dati.xml` (più lenta, con avviso).
2. **Per attributi** — natura, tipologia, sottotipologia, regione/provincia/comune, stato accesso, stato scheda, presenza di risorse (ha foto / ha rilievi / ha KML / ha 3D), intervalli numerici su sviluppo, dislivello, quota, intervallo di date di censimento.
3. **Geografica** — punto (lat/lon inseriti, scelti su mappa, o presi dalla posizione del browser) + raggio in metri/km. Algoritmo: pre-filtro con **bounding box** sui campi `lat`/`lon` dell'indice (rapidissimo), poi distanza esatta **haversine** sui candidati, ordinamento per distanza crescente. Raggio predefinito e massimo configurabili.

Risultati presentati in tre viste commutabili — **tabella** (ordinabile, paginata), **schede/card**, **mappa** — con esportazione CSV, KML, GeoJSON.

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
| Perdita dati | storico automatico delle schede, backup ZIP on demand, istruzioni di backup nella documentazione |

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
- `.gitignore`: `config.xml`, `dati/`, `*.tmp`, `_mini/`, `_log/`.
- Versionati: codice, `config.xml.dist`, XSD, documentazione, `dati_esempio/` con 2–3 ipogei dimostrativi.
- `CHANGELOG.md` in formato *Keep a Changelog*; tag `v1.0.0`, `v1.1.0`, …
- Installazione = copia FTP della cartella + copia di `config.xml.dist` in `config.xml` + `installa.php` guidato (crea l'albero di `dati/`, verifica permessi ed estensioni, crea il primo utente ADM, poi si autodisabilita).

---

## 15. Piano di sviluppo

| Fase | Contenuto | Esito verificabile |
|---|---|---|
| **0** | Repo, struttura cartelle, docs, vendor (Bootstrap/Leaflet/three), `config.xml.dist`, XSD | Il repo si clona ed espone una pagina di diagnostica ambiente |
| **1** | Core: `Config`, `Xml`, `Csv`, `Log`, `Auth`, layout Bootstrap, login, gestione utenti, `installa.php` | Login funzionante con i tre livelli |
| **2** | Anagrafiche: gruppi, esploratori, tipologie | CRUD completo con integrità referenziale |
| **3** | Ipogei: template scheda, CRUD, assegnazione codice, indice CSV, storico | Censimento di un ipogeo end-to-end |
| **4** | Mappa: astrazione `CatageoMappa`, implementazione Leaflet/OSM, marker, cluster, base layer, overlay WMS, mappa di scheda | Tutti gli ipogei visibili e filtrabili su mappa |
| **4b** | Implementazione provider Google Maps sulla stessa interfaccia + selettore in configurazione | Commutazione del provider senza altre modifiche |
| **5** | Risorse: allegati, foto (+miniature), video, download mediato | Upload e consultazione dei media |
| **6** | Rilievi: 2D, KML→GeoJSON su mappa, viewer 3D three.js | Un rilievo KML visibile su mappa e un PLY nel viewer |
| **7** | Esplorazioni: diari, partecipanti, voci con coordinate e foto, viste per gruppo/esploratore | Diario completo pubblicato su scheda |
| **8** | Ricerca: testuale, per attributi, geografica per raggio, esportazioni | Le tre modalità combinabili, con export |
| **9** | Strumenti ADM: ricostruzione indici, verifica integrità, backup, import/export CSV | Archivio verificabile e ripristinabile |
| **10** | Rifinitura: stampa scheda, tema scuro, manuale utente, dati di esempio, tag `v1.0.0` | Release installabile |

Al termine di ogni fase: commit, aggiornamento `CHANGELOG.md`, incremento delle versioni dei file toccati.

---

## 16. Decisioni assunte

Decisioni approvate dal committente il 2026-08-03:

| # | Tema | Decisione | Impatto |
|---|---|---|---|
| D1 | Metadati delle risorse | **Un XML di indice per ogni sottocartella** (`[codice] - Foto.xml`, `- Allegati.xml`, `- Video.xml`, `- Rilievi.xml`, `- Esplorazioni.xml`) | Struttura di §6.7 confermata. Cartelle autodescrittive, `Dati.xml` snello, scritture non conflittuali |
| D2 | Accesso | **Login sempre obbligatorio**, nessun accesso anonimo | `<accessoAnonimo>` resta in `config.xml` con default `0` ma non viene esposto in interfaccia in v1. Ogni pagina, incluse mappa e `scarica.php`, richiede sessione autenticata |
| D3 | Repository e licenza | **Pubblico**, licenza **GPL-3.0** | `LICENSE` con testo GPL-3.0; testata di ogni file PHP con la clausola di licenza breve; `README.md` con badge e note di copyright © 2026 Dario Candela |
| D4 | Cartografia | **OpenStreetMap + Google Maps**, entrambi implementati in v1 | Si introduce un livello di astrazione `MapProvider` (§7.1.1). Google richiede chiave API e caricamento dello script dal dominio Google: deroga esplicita e documentata al vincolo "nessuna CDN", attiva solo se il provider Google è selezionato |

### 16.1 Deroga documentata al vincolo "nessuna CDN"

Il vincolo resta valido per tutte le librerie dell'applicativo (Bootstrap, Bootstrap Icons, Leaflet, three.js: **tutte self-hosted**). L'unica eccezione ammessa è l'API JavaScript di Google Maps, tecnicamente non self-hostabile per licenza. Conseguenze accettate e da riportare nella documentazione utente:

- Con provider `osm` l'applicativo funziona senza alcuna dipendenza da domini terzi, eccetto le immagini delle tile (sostituibili con un tile server proprio tramite provider `custom`).
- Con provider `google` occorrono chiave API, account di fatturazione Google Cloud e connessione al dominio Google; l'assenza di connettività degrada la sola mappa, non il resto dell'applicativo.
- Il provider è selezionabile da ADM in configurazione, con avviso in interfaccia sulle implicazioni.

## 17. Punti ancora aperti

1. **Prefisso del codice catastale**: `LA` è l'esempio o è il valore reale da inserire in `config.xml.dist`? Servono anche il progressivo iniziale e l'ampiezza (3 o 4 cifre). *Nessun blocco: si parte con `LA`, 3 cifre, progressivo 1, tutto modificabile in configurazione.*
2. **Riservatezza delle ubicazioni**: si conferma il meccanismo a tre livelli (`pubblica` / `coordinate_offuscate` / `riservata`) con offuscamento per il livello USR? *Si procede assumendo il sì; disattivabile impostando la riservatezza predefinita e non usando gli altri livelli.*
3. **Ambito geografico**: solo Italia, con liste precompilate di regioni e province, oppure internazionale con campi liberi? *Si procede con liste italiane precompilate ma campi non vincolati, così l'uso fuori Italia resta possibile.*

---

## 18. Note operative sull'ambiente di sviluppo

- Sulla postazione attuale **PHP non è installato** e **GitHub CLI (`gh`) non è presente**; `git` è disponibile. Per provare l'applicativo in locale serve un PHP (XAMPP/Laragon o `winget install PHP.PHP`) — sufficiente il server integrato `php -S localhost:8000`. In alternativa si sviluppa e si prova direttamente sull'hosting via FTP.
- La creazione del repository su GitHub richiede o l'installazione di `gh`, oppure la creazione manuale del repo vuoto dalla web UI (già autenticata come `darcan99`) e il successivo `git remote add` + `push`.
