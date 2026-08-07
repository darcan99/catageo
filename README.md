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

**Versione 1.3.1** — tutto quello che l'elenco qui sopra descrive è realizzato e provato, comprese la **sezione geologia** (fase 6b) con 26 layer cartografici preconfigurati per l'Italia centrale e la compilazione assistita dalla cartografia, e il **provider Google Maps** alternativo a OpenStreetMap (fase 4b).

Due limiti dichiarati. La **resa visiva del provider Google** non è stata confermata: serve una chiave API valida, e con una chiave finta l'implementazione gira ma Google non disegna la propria cornice. I **layer dei geoportali** sono di enti pubblici che spostano gli endpoint senza preavviso: sono stati verificati uno per uno il 2026-08-07, e la suite di prova li interroga a ogni giro contandoli separatamente, perché un ente che spegne un servizio non è una regressione dell'applicativo.

| Fase | Contenuto | Stato |
|---|---|---|
| 0–1 | Struttura, core XML/CSV, autenticazione a tre livelli, installer | ✅ fatto |
| 2 | Anagrafiche: gruppi speleologici, esploratori, tipologie, grandezze | ✅ fatto |
| 2b | Cataloghi multipli con serie di codifica e contatori indipendenti | ✅ fatto |
| 3 | Ipogei: scheda standard, censimento, indice, storico dei codici | ✅ fatto |
| — | Coordinate: gradi, sessagesimali, UTM, Gauss-Boaga, ED50, con conversione verificata contro proj4js | ✅ fatto |
| 4 | Mappa Leaflet/OSM, marker, raggruppamento, layer WMS, mappa di scheda | ✅ fatto |
| 4b | Provider Google Maps alternativo, su un'astrazione con due implementazioni | ✅ fatto |
| 5 | Allegati, foto con miniature, video, consegna mediata, metadati EXIF/GPS | ✅ fatto |
| 6 | Rilievi 2D e 3D, KML sulla mappa, viewer three.js | ✅ fatto |
| 7 | Esplorazioni: diari di uscita, partecipanti anche fuori anagrafica, punti georiferiti, cronologia e filtri | ✅ fatto |
| 7b | Bibliografia: catalogo generale delle opere, tre forme di voce, citazioni per sigla, export BibTeX | ✅ fatto |
| 7c | Dati scientifici: punti di misura, serie CSV, import da datalogger, statistiche, grafici SVG lato server | ✅ fatto |
| 7d | Biospeleologia con colonie di chirotteri e avviso di periodo critico; archeologia con evidenze, tutela e indagini | ✅ fatto |
| 8 | Ricerca testuale (inclusi codici storici), per attributi, specialistica e geografica per raggio; tre viste; export CSV/GeoJSON/KML | ✅ fatto |
| 8b | Migrazione fra cataloghi: anteprima dei codici, lotto, tracciato, codici storici sempre risolvibili | ✅ fatto |
| 9 | Strumenti: ricostruzione indici, verifica integrita, backup ZIP per catalogo, verifica dei collegamenti | ✅ fatto |
| 9b | Import CSV massivo, con mappatura delle colonne e anteprima riga per riga | ✅ fatto |
| 10 | Stampa della scheda, manuale utente, guida di installazione, dati di esempio, `v1.0.0` | ✅ fatto |
| 12 | *(post-1.0.0)* Estensioni del modello: stato esplorativo, verifica sul campo, ingressi come scheda, complessi, aree speleologiche con perimetro, percorribilita strutturata, report di completezza | ✅ fatto |
| 11 | *(post-release)* Acquisizione da fonti pubbliche, previa verifica delle licenze | ⏳ da fare |

Il documento di analisi completo è in [docs/ANALISI.md](docs/ANALISI.md): architettura, standard di nomenclatura, modello dati XML, moduli funzionali, sicurezza e piano di sviluppo in fasi. Le verifiche eseguite sono documentate in [docs/prove/](docs/prove/), con l'indicazione esplicita di ciò che **non** coprono.

## Requisiti

- PHP **8.0+** (compatibile 7.4) con `dom`, `libxml`, `simplexml`, `mbstring`, `json`, `fileinfo`, `session`
- `gd` opzionale (generazione miniature, con fallback se assente)
- `exif` opzionale (data e coordinate dalle foto)
- `zip` opzionale (rilievi in formato KMZ; i KML funzionano comunque)
- Nessun database, nessun `mod_rewrite`, nessuna shell

## Installazione

Guida completa in **[docs/INSTALLAZIONE.md](docs/INSTALLAZIONE.md)**. In sintesi: copia della cartella via FTP, poi `installa.php` nel browser, che genera `config.xml`, crea l'archivio, i vocabolari e il primo amministratore.

Per vedere l'applicativo pieno invece che vuoto:

```
php esempi/genera-esempi.php
```

crea un catalogo `ESEMPI` con cinque cavità fittizie che coprono i casi interessanti — scheda completa, cavità artificiale con archeologia, ubicazione a precisione ridotta, scheda riservata, bozza — e si toglie con `--rimuovi`.

## Uso

Il **[manuale utente](docs/MANUALE.md)** copre livelli di utenza, cataloghi e codici, censimento, coordinate, riservatezza, tutte le sezioni della scheda, ricerca, esportazioni, stampa, importazione e strumenti di manutenzione.

## Licenza

[GNU General Public License v3.0](LICENSE) — © 2026 Dario Candela

## Autore

**Dario Candela** — darcan99@gmail.com
