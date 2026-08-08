# Installazione di CATAGEO

Guida all'installazione, all'aggiornamento e allo spostamento di un archivio.
Aggiornata a CATAGEO **1.7.0**.

CATAGEO gira su un hosting condiviso da pochi euro l'anno. Non serve un
database, non serve una shell, non serve `mod_rewrite`, non serve Composer:
tutto quello che occorre è una cartella scrivibile e PHP.

---

## 1. Cosa serve

| | |
|---|---|
| **PHP** | 8.0 o superiore (funziona anche su 7.4) |
| **Estensioni obbligatorie** | `dom`, `libxml`, `simplexml`, `mbstring`, `json`, `fileinfo`, `session` |
| **Estensioni opzionali** | `gd` (miniature), `exif` (data e coordinate dalle foto), `zip` (backup e rilievi KMZ) |
| **Spazio** | l'applicativo occupa circa 15 MB; l'archivio cresce con foto e rilievi |
| **Permessi** | scrittura sulla cartella dell'applicativo, o almeno su `dati/` |

Le estensioni opzionali mancanti non impediscono l'installazione: le funzioni
che le usano si degradano e lo dicono. Senza `zip` non si fanno backup dalla
pagina degli strumenti, senza `gd` le foto si mostrano a dimensione piena.

**Connessioni in uscita.** Servono a due cose sole, entrambe facoltative: la
verifica dei collegamenti bibliografici e la compilazione assistita della
sezione geologia, che interroga i servizi cartografici. Richiedono
`allow_url_fopen` attivo e che l'hosting lasci uscire le richieste HTTPS —
diversi piani economici le bloccano. Se non passano, le due funzioni lo dicono
e tutto il resto continua a funzionare: i layer WMS in mappa **non** ne hanno
bisogno, perché li scarica il browser, non il server.

La pagina **Diagnostica** (menu utente, solo amministratori) elenca tutto
quello che è presente e tutto quello che manca, con i limiti di caricamento
effettivi dell'hosting. È la prima pagina da guardare quando qualcosa non va.

---

## 2. Installazione

1. **Copia i file.** L'intera cartella del progetto va nello spazio web, via
   FTP o dal pannello dell'hosting. Non serve estrarre nulla in posti diversi.

2. **Apri `installa.php`** nel browser: `https://iltuosito/catageo/installa.php`.

   L'installatore chiede il nome del catasto, l'ente, un indirizzo email di
   riferimento e le credenziali del primo amministratore.

3. **Conferma.** L'installatore, in un colpo solo:
   - genera `config.xml` a partire da `config.xml.dist`;
   - crea l'archivio `dati/` con le sottocartelle e i file `.htaccess` di
     protezione;
   - popola i vocabolari predefiniti (tipologie, grandezze, periodi storici);
   - crea gli indici vuoti;
   - crea il catalogo dimostrativo `DEMO`;
   - crea il primo utente amministratore;
   - scrive `installato.txt`.

4. **Cancella o rinomina `installa.php`.** Non è obbligatorio — `installato.txt`
   impedisce comunque una seconda installazione — ma è buona norma.

### Se l'archivio non è scrivibile

L'installatore lo dice invece di fallire a metà. Sui pannelli tipo cPanel
bastano i permessi `755` sulla cartella; su hosting più rigidi può servire
`775`. Non usare mai `777`.

### Se `dati/` deve stare fuori dallo spazio web

È la sistemazione più sicura, perché nessuna richiesta HTTP può arrivare ai
file del catasto. Prima di lanciare l'installatore, copia `config.xml.dist` in
`config.xml` e cambia:

```xml
<dati>/percorso/assoluto/fuori/dal/web/catageo-dati</dati>
```

L'installatore rispetta un `config.xml` già presente.

---

## 3. Dopo l'installazione

### Primo accesso

`index.php` chiede le credenziali scelte durante l'installazione. Il primo
utente è amministratore: da **Gestione utenti** si creano gli altri.

### Impostazioni da rivedere subito

Si modificano in `config.xml`, con un editor di testo:

| Chiave | Cosa fa | Perché guardarla subito |
|---|---|---|
| `sicurezza.riservatezzaPredefinita` | riservatezza delle schede nuove | `pubblica` va bene per un catasto di cavità artificiali urbane, molto meno per un catasto carsico |
| `sicurezza.offuscamentoCoordinate` | metri di arrotondamento per le schede a precisione ridotta | il valore predefinito è 1000 m |
| `sicurezza.accessoAnonimo` | se si può consultare senza accedere | predefinito **spento** |
| `sicurezza.durataSessioneMinuti` | durata della sessione | 120 minuti |
| `upload.dimensioneMax` | tetto in MB per file caricato | non può superare quello dell'hosting, che la diagnostica mostra |
| `mappa.provider` | fondo cartografico | `osm` è l'unico attivo in questa versione |
| `sistema.fusoOrario` | fuso per le date scritte nelle schede | `Europe/Rome` |

Il file è commentato: ogni chiave dice cosa fa e cosa succede a cambiarla.

### Crea il tuo catalogo

Il catalogo `DEMO` serve a far girare l'applicativo appena installato e non ha
codifica adatta a un catasto vero. Da **Cataloghi** si crea il proprio, con le
serie di codifica volute, e si prova l'anteprima del codice prima di censire
qualcosa: le regole di codifica sono la parte più facile da sbagliare, e un
codice sbagliato assegnato a cento schede si corregge una scheda per volta.

---

## 4. Aggiornamento

L'archivio sta in `dati/` e la configurazione in `config.xml`: **nessuno dei
due viene toccato da un aggiornamento**, che riguarda solo il codice.

1. Fai un backup dalla pagina **Strumenti** e scaricalo.
2. Sovrascrivi i file dell'applicativo con quelli della versione nuova,
   **lasciando fuori** `config.xml`, `installato.txt` e la cartella `dati/`.
3. Apri l'applicativo e guarda **Strumenti › Verifica integrità**.
4. Se una versione nuova ha aggiunto colonne agli indici, **Ricostruisci gli
   indici** dalla stessa pagina: gli indici sono cache rigenerabile dai dati,
   e ricostruirli non perde nulla.

Confronta `config.xml.dist` con il tuo `config.xml`: le chiavi nuove non
compaiono da sole, e l'applicativo usa il valore predefinito finché non le
aggiungi.

Lo stesso vale per i **simboli delle tipologie**: dalla 1.5.0 le voci della
tassonomia hanno un'icona per la mappa, ma il vocabolario di un archivio già in
uso non viene toccato. Il comando **Strumenti › Simboli delle tipologie**
completa le voci che ne sono prive, senza sovrascrivere le scelte già fatte.

Lo stesso vale per i **layer cartografici**. Dalla 1.2.0 `config.xml.dist`
contiene 26 layer WMS già pronti (geologia ISPRA, catasto, vincoli
archeologici, geoportali di Lazio, Abruzzo, Umbria e Marche), ma
un'installazione esistente non li riceve: vanno copiati a mano dentro
`<overlayLayers>` del proprio `config.xml`. Sono tutti `attivo="0"`, quindi
incollarli non cambia l'aspetto della mappa finché non se ne accende uno.

---

## 5. Spostare o duplicare un archivio

L'archivio è fatto di file e cartelle con nomi leggibili: si sposta copiandolo.

1. Copia `dati/` e `config.xml` sulla destinazione.
2. Copia i file dell'applicativo (o installali di nuovo).
3. Se il percorso dell'archivio cambia, aggiorna `percorsi.dati` in
   `config.xml`.
4. Ricostruisci gli indici e lancia la verifica di integrità.

Non serve alcuna esportazione: **i dati sono già nel formato definitivo**. È il
motivo per cui il progetto non usa un database.

---

## 6. Backup e ripristino

Dalla pagina **Strumenti** si produce uno ZIP dell'intero archivio o di un
singolo catalogo. Il backup di un catalogo comprende anche anagrafiche e
indici, senza i quali le schede citerebbero identificativi inesistenti.

Dentro lo ZIP c'è un manifesto con versione, data, autore e istruzioni.

**Il ripristino è manuale**, ed è una scelta: si estrae lo ZIP dentro `dati/` e
si ricostruiscono gli indici. Un ripristino automatico che sovrascrive
l'archivio è l'operazione più pericolosa immaginabile, e non viene offerta
dall'interfaccia.

Il backup dell'applicativo si somma a quello dell'hosting, non lo sostituisce:
uno ZIP che resta sullo stesso disco dei dati non protegge dal guasto del
disco. Scaricalo.

---

## 7. Problemi frequenti

**«Pagina bianca appena aperto il sito.»** Quasi sempre una versione di PHP
troppo vecchia o un'estensione obbligatoria assente. Apri `diagnostica.php`, o
guarda il log degli errori dell'hosting.

**«La sessione non dura, ogni operazione mi rimanda all'accesso.»** Verifica
che la cartella delle sessioni di PHP sia scrivibile. Su alcuni hosting
condivisi va indicata nel `php.ini` dello spazio utente.

**«Il caricamento di un file grande fallisce senza messaggio.»** Il file supera
`post_max_size` dell'hosting: PHP scarta la richiesta prima che l'applicativo
la veda, e nessun messaggio può arrivare da dentro. La diagnostica mostra il
limite vero; `upload.dimensioneMax` in `config.xml` non può superarlo.

**«La mappa è grigia.»** L'hosting blocca le chiamate in uscita, oppure il
browser sta bloccando i tile. La Content-Security-Policy dell'applicativo
elenca esplicitamente le origini dei tile server ricavandole dai layer
configurati: un layer aggiunto a mano con un dominio nuovo va inserito nella
configurazione, non solo nella pagina.

**«Ho perso la password dell'amministratore.»** Non c'è recupero via email:
sarebbe una porta aperta su un applicativo senza posta configurata. Si apre
`dati/utenti.xml` con un editor di testo, si sostituisce il contenuto di
`<password>` dell'utente con un hash BCRYPT generato altrove, e si rientra.

---

## 8. Disinstallazione

Cancella la cartella. Non c'è nulla nel database, nel registro di sistema o
altrove: **conserva `dati/`**, che è il catasto, e il resto è software.
