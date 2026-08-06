# CATAGEO — Catasto Ipogei

**CATAGEO** (CATAsto ipoGEi) è un'applicazione web per la gestione di un catasto di **cavità artificiali e naturali**: schede catastali, allegati, foto, video, rilievi 2D/3D, diari di esplorazione e visualizzazione cartografica.

È progettata per essere installabile su **qualsiasi hosting PHP economico**, senza database: tutti i dati risiedono sul filesystem in file **XML** e **CSV**, strutturati secondo uno standard preciso e leggibili anche senza l'applicativo.

## Caratteristiche principali

- **Nessun database**: archivio su filesystem, dati umanamente leggibili e durevoli
- **Nessuna dipendenza esterna**: PHP 8 e sole estensioni standard, zero librerie da installare
- **Front-end self-hosted**: Bootstrap 5.3, Leaflet e three.js inclusi, nessuna CDN
- **Cataloghi multipli** in una sola installazione, ciascuno con le proprie regole di codifica e contatori indipendenti: si adatta a catasti già esistenti senza rinumerarli
- **Migrazione fra cataloghi** conservando la memoria del codice di origine, così che i riferimenti pubblicati continuino a risolvere
- **Mappa** degli ipogei con layer WMS aggiuntivi e tracciati dei rilievi da KML
- **Scheda standard** uniforme per tutti gli ipogei, con storicizzazione automatica delle revisioni
- **Rilievi 2D e 3D** con viewer integrato e sovrapposizione su mappa
- **Diari di esplorazione** con gruppi speleologici, esploratori, orari, coordinate e foto
- **Bibliografia** con catalogo delle opere condivise, citazioni puntuali e link esterni
- **Dati scientifici** con serie storiche di temperature, gas, radon, radioattività, idrologia e flussi d'aria: descrittore XML e letture in CSV, importabili da datalogger
- **Biospeleologia** con particolare attenzione alle colonie di chirotteri, conteggi storicizzati e avviso dei periodi critici
- **Archeologia** con inquadramento cronologico, evidenze e regime di tutela
- **Geologia** con inquadramento desunto dalla cartografia pubblica e registro di morfologie e campioni
- **Ricerca** per nome parziale, catalogo, attributi, contenuti specialistici e **area geografica** (punto GPS + raggio)
- **Tre livelli di utenza**: amministratore (ADM), operatore (OPE), utente (USR)
- **Tutela delle ubicazioni sensibili**: riservatezza per ipogeo e per colonia di chirotteri, con offuscamento delle coordinate

## Stato del progetto

🚧 **In sviluppo** — versione corrente **0.11.0**. Le caratteristiche elencate sopra descrivono il progetto completo, non quello che è già installabile.

| Fase | Contenuto | Stato |
|---|---|---|
| 0–1 | Struttura, core XML/CSV, autenticazione a tre livelli, installer | ✅ fatto |
| 2 | Anagrafiche: gruppi speleologici, esploratori, tipologie, grandezze | ✅ fatto |
| 2b | Cataloghi multipli con serie di codifica e contatori indipendenti | ✅ fatto |
| 3 | Ipogei: scheda standard, censimento, indice, storico dei codici | ✅ fatto |
| — | Coordinate: gradi, sessagesimali, UTM, Gauss-Boaga, ED50, con conversione verificata contro proj4js | ✅ fatto |
| 4 | Mappa Leaflet/OSM, marker, raggruppamento, layer WMS, mappa di scheda | ✅ fatto |
| 4b | Provider Google Maps alternativo | ⏳ da fare |
| 5 | Allegati, foto con miniature, video, consegna mediata, metadati EXIF/GPS | ✅ fatto |
| 6 | Rilievi 2D e 3D, KML sulla mappa, viewer three.js | ✅ fatto |
| 7 | Esplorazioni: diari di uscita, partecipanti anche fuori anagrafica, punti georiferiti, cronologia e filtri | ✅ fatto |
| 7b | Bibliografia: catalogo generale delle opere, tre forme di voce, citazioni per sigla, export BibTeX | ✅ fatto |
| 7c | Dati scientifici: punti di misura, serie CSV, import da datalogger, statistiche, grafici SVG lato server | ✅ fatto |
| 7d | Biospeleologia e archeologia | ⏳ da fare |
| 8 | Ricerca testuale, per attributi e geografica; migrazione fra cataloghi | ⏳ da fare |
| 9–10 | Strumenti di manutenzione, rifinitura, manuale, `v1.0.0` | ⏳ da fare |

Il documento di analisi completo è in [docs/ANALISI.md](docs/ANALISI.md): architettura, standard di nomenclatura, modello dati XML, moduli funzionali, sicurezza e piano di sviluppo in fasi. Le verifiche eseguite sono documentate in [docs/prove/](docs/prove/), con l'indicazione esplicita di ciò che **non** coprono.

## Requisiti

- PHP **8.0+** (compatibile 7.4) con `dom`, `libxml`, `simplexml`, `mbstring`, `json`, `fileinfo`, `session`
- `gd` opzionale (generazione miniature, con fallback se assente)
- `exif` opzionale (data e coordinate dalle foto)
- `zip` opzionale (rilievi in formato KMZ; i KML funzionano comunque)
- Nessun database, nessun `mod_rewrite`, nessuna shell

## Installazione

Documentazione in arrivo (`docs/INSTALLAZIONE.md`). In sintesi: copia della cartella via FTP, copia di `config.xml.dist` in `config.xml`, esecuzione di `installa.php` per creare l'archivio e il primo utente amministratore.

## Licenza

[GNU General Public License v3.0](LICENSE) — © 2026 Dario Candela

## Autore

**Dario Candela** — darcan99@gmail.com
