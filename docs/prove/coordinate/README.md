# Verifica incrociata delle conversioni di coordinate

Confronta il motore di conversione in PHP (`app/lib/Proiezione.php`) con
**proj4js**, usando le stesse definizioni dei sistemi e gli stessi punti.

## Perché serve

Le formule di proiezione si possono verificare da sole con un giro completo
andata-ritorno: se il punto torna al suo posto, andata e ritorno sono coerenti
*fra loro*. Ma un giro completo non dice nulla su una **trasformazione di
datum**: se il verso delle rotazioni di Helmert è sbagliato, andata e ritorno
restano coerenti e il punto torna al suo posto, mentre la posizione in WGS84 è
spostata di decine di metri. Un errore così sembra del tutto plausibile e non lo
si scopre guardando i numeri.

L'unico modo per accorgersene è confrontare con un'implementazione indipendente.
proj4js è la trasposizione JavaScript di PROJ, segue la stessa convenzione
(Position Vector) e riceve **le stesse identiche stringhe di definizione**: se le
due concordano al millimetro su decine di punti, i parametri e le convenzioni
sono giusti.

## Come si esegue

Dalla cartella dell'applicativo:

```bash
php docs/prove/coordinate/genera-dati.php
```

Poi si serve l'applicativo e si apre la pagina:

```bash
php -S 127.0.0.1:8140 -t .
```

e si visita `http://127.0.0.1:8140/docs/prove/coordinate/index.html`.

La pagina è verde se ogni conversione concorda entro un centimetro, rossa
altrimenti, con l'elenco dei casi fuori tolleranza.

## Esito dell'ultima esecuzione

52 punti fra capoluoghi, casi reali e una griglia sull'Italia, su nove sistemi.

| Sistema | Scarto max andata | Scarto max ritorno |
|---|---|---|
| UTM WGS84 32N / 33N / 34N | 0,30 / 0,32 / 0,15 mm | 1,00 / 1,35 / 0,23 mm |
| UTM ETRS89 32N / 33N | 0,30 / 0,32 mm | 1,00 / 1,35 mm |
| Gauss-Boaga Ovest / Est | 0,30 / 0,36 mm | 1,00 / 2,56 mm |
| UTM ED50 32N / 33N | 0,30 / 0,36 mm | 1,00 / 2,56 mm |

Nessuna conversione fuori tolleranza. Le 229 conversioni escluse sono quelle che
PHP **rifiuta di proposito** perché il punto cade oltre quattro gradi dal
meridiano centrale del sistema: lì la serie di Snyder degrada, e restituire un
numero sbagliato sarebbe peggio che rifiutare.

## Nota sui file generati

`dati.json` è prodotto dallo script e non è versionato: si rigenera in un
secondo. Il confronto va rifatto ogni volta che si tocca `Proiezione.php`,
`SistemiRiferimento.php` o si aggiunge un sistema al vocabolario.
