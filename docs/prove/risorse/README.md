# Verifica delle risorse (fase 5)

Caricamento di allegati, foto e video, miniature, consegna mediata da
`scarica.php`, riservatezza e rimozione conservativa.

La suite `prova-risorse.ps1` costruisce file veri — immagini generate con GD, un
PDF minimo ma valido, un file di testo — e li carica **via HTTP con richieste
multipart costruite a mano**, non chiamando le classi PHP direttamente. È una
scelta precisa: il punto delicato di questa fase è ciò che accade *fra* il
browser e il disco, e un test che salta il trasporto non lo esercita.

## Cosa viene verificato

### Le tre barriere sui file in arrivo

Sono indipendenti e vengono provate una per una, con file costruiti apposta:

| Barriera | File di prova | Esito atteso |
|---|---|---|
| Lista nera delle estensioni | `script.php` | rifiutato sempre |
| Lista bianca della sezione | `Relazione tecnica.pdf` fra le **foto** | rifiutato |
| Tipo reale del contenuto | `finta-foto.jpg` contenente codice PHP | rifiutato |

Per ciascuno si verifica **anche** che il file non sia finito né nell'indice né
sul disco: un rifiuto che lascia il file nell'archivio non è un rifiuto.

### Il caricamento parziale

Caricando insieme un file valido e uno non valido, il valido passa e l'altro
viene segnalato. Chi carica venti foto e ne ha una sbagliata vuole le
diciannove buone, non ricominciare da capo.

### Il progressivo

- indipendente per sezione (foto e allegati hanno numerazioni separate);
- **mai riusato** dopo una rimozione: rimossa la FO002, il caricamento
  successivo prende la FO004 e non la 002. Serve perché il riferimento `FO002`
  può essere citato da un diario o da una scheda, e riassegnarlo farebbe puntare
  un riferimento vecchio a un contenuto nuovo.

### La consegna

- `Content-Type` corretto, `Content-Disposition` con il nome originale;
- 404 su risorsa inesistente, 400 su sezione non valida, 403 all'anonimo;
- **richieste parziali**: `bytes=0-99` → 206 con `Content-Range` e lunghezza
  esatta, `bytes=100-` → fino alla fine, intervallo oltre la fine → 416 con la
  dimensione totale. È ciò che permette di scorrere un video invece di
  riscaricarlo dall'inizio.

### La riservatezza

Una singola risorsa può essere più riservata della scheda che la contiene — la
foto che mostra l'ingresso di una cavità protetta, per esempio. Verificato che
un utente di sola consultazione scarichi la foto pubblica e **non** quella
riservata (403), mentre l'amministratore le ottiene entrambe.

> **Una trappola in cui il test era caduto.** La verifica «un utente USR non può
> eliminare» inviava una POST *senza token CSRF*: veniva respinta, ma dal
> controllo CSRF, non dai permessi. Passava senza dimostrare nulla. Ora
> l'asserzione dice il vero («POST senza token respinta») e la proprietà che
> conta è stabilita da due fatti verificati separatamente: `carica_risorse`
> richiede almeno OPE, e la pagina lo esige nel ramo POST prima di eseguire
> qualunque operazione.

### La rimozione

Conservativa: il file viene spostato in `[codice] - _rimossi` con una marca
temporale nel nome, non cancellato. Si verifica che sia effettivamente
recuperabile da lì, che la riga sparisca dall'indice, che la miniatura venga
tolta e che `ultimoProgressivo` **non** torni indietro.

### Gli indici

Entrambi gli indici di sezione prodotti vengono validati contro
`schemi/risorse.xsd`. Lo schema impone l'unicità del progressivo e del nome
file: due righe con lo stesso numero renderebbero ambiguo ogni riferimento, due
righe sullo stesso file farebbero sparire il file dell'una rimuovendo l'altra.

## Verifiche nel browser — 2026-08-05

Le suite HTTP non vedono se un'immagine si vede davvero.

| Controllo | Esito |
|---|---|
| Copertina in testa alla scheda, immagine effettivamente decodificata | ✅ |
| Galleria: nessun riquadro rotto | ✅ |
| Miniature consegnate a 400×300, cioè la larghezza configurata | ✅ |
| Contrasti su galleria, elenco, form di modifica e scheda, nei due temi | ✅ nessun elemento sotto soglia |

> **La copertina usa l'originale, non la miniatura.** Il primo tentativo la
> mostrava con `mini=1`: un'immagine larga 400 px stirata su tutta la scheda,
> visibilmente sgranata. È una sola immagine per scheda, quindi il peso in più è
> accettabile; le gallerie, dove le immagini sono decine, continuano a usare le
> miniature.

> **Attenzione misurando le miniature nel browser.** Hanno `loading="lazy"`, e in
> una finestra che non sta componendo i fotogrammi il browser non le scarica
> affatto: risultano `complete: false` con `naturalWidth` 0, che somiglia molto a
> un riquadro rotto. La verifica corretta è togliere l'attributo a una di esse e
> controllare che allora arrivi.

## Metadati incorporati — 2026-08-05

Data di scatto e coordinate vengono lette dal file quando ci sono. La verifica
costruisce **file veri con i byte al posto giusto**, perché un parser di formati
binari collaudato su dati finti non è collaudato: un JPEG con un blocco EXIF
scritto a mano (IFD0, sotto-IFD EXIF e GPS, valori razionali) e un contenitore
MP4 con le scatole `ftyp`, `moov`, `mvhd` e `©xyz`.

| Controllo | Esito |
|---|---|
| Gradi, minuti e secondi come frazioni EXIF → gradi decimali | ✅ 41,856231 |
| Emisfero sud e longitudine ovest negativi | ✅ |
| Latitudine oltre 90°, terna incompleta, denominatore zero | ✅ rifiutati |
| GPS a 0,0 (fix mai agganciato) | ✅ scartato: non è una posizione |
| `DateTimeOriginal` preferito a `DateTime` | ✅ |
| PNG senza EXIF | ✅ nessun dato, nessun errore |
| Data da `mvhd` (epoca 1904) e posizione da `©xyz` ISO 6709 | ✅ |
| File che non è ISO BMFF, o con lunghezza di scatola incoerente | ✅ si ferma senza rincorrere posizioni |
| End-to-end: foto caricata via HTTP → data e coordinate nell'indice | ✅ |
| **Il dato indicato a mano vince sull'EXIF** | ✅ verificato caricando con data esplicita |

L'ordine di precedenza non è un dettaglio: un rilievo fatto col GPS
professionale vale più dell'EXIF di un telefono, quindi i metadati riempiono
**solo** i campi lasciati vuoti e non sovrascrivono mai.

## Finestra dei media — 2026-08-05

| Controllo | Esito |
|---|---|
| Clic su una miniatura apre la finestra, non una scheda nuova | ✅ |
| Immagine decodificata dentro la finestra (1200 px) | ✅ |
| Titolo, riferimento, data, tipo e peso nell'intestazione | ✅ |
| Pulsante mappa presente **solo** se la risorsa ha coordinate | ✅ |
| Video: viene creato un `<video>` con i controlli | ✅ |
| **Alla chiusura il video viene rimosso**, non solo messo in pausa | ✅ corpo svuotato |
| Collegamento di scaricamento all'originale | ✅ |

L'ultimo punto è quello che rischiava di più: un `<video>` lasciato nel
documento continua a scaricare e, se era in riproduzione, continua a suonare
anche a finestra chiusa. Il corpo della finestra viene svuotato e la sorgente
tolta.

Il collegamento `href` resta valido su ogni innesco: senza JavaScript il file si
apre comunque, semplicemente in una scheda nuova.

## Cosa questi controlli **non** coprono

- **Video riproducibili.** L'MP4 usato nelle prove ha le scatole dei metadati ma
  **non contiene tracce**: serve a verificare la lettura di data e posizione e
  l'apertura del player, non la riproduzione. Che un filmato vero scorra davvero
  nel `<video>` va provato con un file reale. Il supporto `Range`, che è la parte
  rischiosa del seek, è invece verificato — su un'immagine, ma il codice non
  distingue per tipo.
- **Schermo intero.** Il pulsante chiama `requestFullscreen`, che in una finestra
  non in primo piano il browser rifiuta: la logica è scritta ma non è stata vista
  funzionare. Va provata a mano.
- **Metadati di altri formati.** HEIC dei telefoni Apple e MOV con scatole in
  ordine inusuale non sono stati provati. Se la lettura fallisce il file viene
  comunque archiviato, con un avviso nel log.
- **File grandi.** Il limite di dimensione è verificato solo nella logica, non
  caricando davvero un file da decine di megabyte, e nemmeno superando
  `post_max_size` (dove PHP svuota `$_FILES` e il messaggio d'errore arriva da
  un ramo diverso).
- **Assenza di GD.** Il ramo che rinuncia alle miniature e mostra l'avviso non è
  stato provato: qui GD c'è. Su un hosting senza GD andrebbe verificato che la
  galleria resti utilizzabile.
- **Il limite di memoria delle miniature.** La stima che rinuncia prima di far
  morire il processo non è stata provata con un'immagine abbastanza grande da
  superare i 512 MB di questa macchina.
- **Concorrenza.** Due caricamenti simultanei sulla stessa sezione: il lock
  sull'indice c'è, ma non è stato esercitato con richieste realmente parallele.
