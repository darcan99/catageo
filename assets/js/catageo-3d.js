/* ============================================================================
 *  CATAGEO — Catasto Ipogei
 * ============================================================================
 *  File .........: assets/js/catageo-3d.js
 *  Descrizione ..: Visualizzatore dei modelli tridimensionali dei rilievi:
 *                  PLY, OBJ, STL e GLTF/GLB, con three.js servito in locale.
 *
 *                  E un modulo ES perche i caricatori di three.js lo sono. I
 *                  loro import sono stati riscritti per puntare al file locale:
 *                  una import map avrebbe richiesto uno script inline, che la
 *                  Content-Security-Policy vieta.
 *
 *                  Il modello si carica solo quando lo si chiede, non
 *                  all'apertura della pagina: una nuvola di punti di un rilievo
 *                  puo pesare decine di megabyte, e scaricarla per una scheda
 *                  che magari si sta solo consultando sarebbe uno spreco.
 *  Versione .....: 0.8.1
 *  Sviluppatore .: Dario Candela <darcan99@gmail.com>
 *  Licenza ......: GNU GPL v3.0 — vedi LICENSE
 *  Copyright ....: (c) 2026 Dario Candela
 * ----------------------------------------------------------------------------
 *  CRONOLOGIA
 *  0.8.1  2026-08-05  D.Candela  Nuvole di punti riconosciute dalle facce e
 *                                non dalle normali; vertici traslati invece
 *                                dell'oggetto; vertici e facce dichiarati.
 *  0.8.0  2026-08-05  D.Candela  Prima stesura (fase 6).
 * ============================================================================
 */

import * as THREE from '../vendor/three-r169/three.module.min.js';
import { OrbitControls } from '../vendor/three-r169/jsm/controls/OrbitControls.js';
import { PLYLoader } from '../vendor/three-r169/jsm/loaders/PLYLoader.js';
import { OBJLoader } from '../vendor/three-r169/jsm/loaders/OBJLoader.js';
import { STLLoader } from '../vendor/three-r169/jsm/loaders/STLLoader.js';
import { GLTFLoader } from '../vendor/three-r169/jsm/loaders/GLTFLoader.js';

/** Colore del modello quando il file non porta colori propri. */
const COLORE_MODELLO = 0xb9c6d4;

/** Stato del visualizzatore attivo, uno solo per pagina. */
let vista = null;

/**
 * Prepara scena, luci e comandi dentro un contenitore.
 *
 * Le luci sono due e non una: con una sola sorgente le facce rivolte altrove
 * restano nere, e in un rilievo di cavita quelle facce sono meta del modello.
 */
function creaVista(contenitore) {
    const larghezza = contenitore.clientWidth || 640;
    const altezza   = contenitore.clientHeight || 420;

    const scena = new THREE.Scene();
    scena.background = new THREE.Color(0x11141a);

    const camera = new THREE.PerspectiveCamera(55, larghezza / altezza, 0.01, 100000);

    const disegnatore = new THREE.WebGLRenderer({ antialias: true });
    disegnatore.setSize(larghezza, altezza);
    // Il rapporto pixel si limita a 2: su schermi molto densi disegnare a 3x
    // costa tre volte tanto senza che si veda la differenza.
    disegnatore.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    contenitore.innerHTML = '';
    contenitore.appendChild(disegnatore.domElement);

    scena.add(new THREE.AmbientLight(0xffffff, 1.1));

    const luce = new THREE.DirectionalLight(0xffffff, 1.6);
    luce.position.set(1, 1, 1);
    scena.add(luce);

    const controapporto = new THREE.DirectionalLight(0xffffff, 0.6);
    controapporto.position.set(-1, -0.5, -1);
    scena.add(controapporto);

    const comandi = new OrbitControls(camera, disegnatore.domElement);
    comandi.enableDamping = true;

    const stato = {
        contenitore, scena, camera, disegnatore, comandi,
        oggetto: null, assi: null, animazione: null, filoDiFerro: false
    };

    function disegna() {
        stato.animazione = requestAnimationFrame(disegna);
        comandi.update();
        disegnatore.render(scena, camera);
    }
    disegna();

    // Il contenitore cambia dimensione quando la finestra si ridimensiona o
    // quando si va a schermo intero: senza questo il modello resta deformato.
    stato.osservatore = new ResizeObserver(function () {
        const l = contenitore.clientWidth, a = contenitore.clientHeight;
        if (l > 0 && a > 0) {
            camera.aspect = l / a;
            camera.updateProjectionMatrix();
            disegnatore.setSize(l, a);
        }
    });
    stato.osservatore.observe(contenitore);

    return stato;
}

/** Smonta il visualizzatore e libera la memoria della scheda grafica. */
function distruggi(stato) {
    if (!stato) {
        return;
    }
    if (stato.animazione) {
        cancelAnimationFrame(stato.animazione);
    }
    if (stato.osservatore) {
        stato.osservatore.disconnect();
    }
    // Geometrie e materiali stanno nella memoria della GPU, che il garbage
    // collector di JavaScript non tocca: vanno liberati a mano, altrimenti
    // aprire dieci modelli di seguito esaurisce la scheda video.
    stato.scena.traverse(function (nodo) {
        if (nodo.geometry) {
            nodo.geometry.dispose();
        }
        if (nodo.material) {
            const materiali = Array.isArray(nodo.material) ? nodo.material : [nodo.material];
            materiali.forEach(function (m) {
                Object.keys(m).forEach(function (chiave) {
                    const valore = m[chiave];
                    if (valore && valore.isTexture) {
                        valore.dispose();
                    }
                });
                m.dispose();
            });
        }
    });
    stato.comandi.dispose();
    stato.disegnatore.dispose();
    stato.contenitore.innerHTML = '';
}

/** Sceglie il caricatore adatto all'estensione. */
function caricatore(estensione) {
    switch (estensione) {
        case 'ply':  return new PLYLoader();
        case 'obj':  return new OBJLoader();
        case 'stl':  return new STLLoader();
        case 'gltf':
        case 'glb':  return new GLTFLoader();
        default:     return null;
    }
}

/**
 * Trasforma il risultato del caricatore in un oggetto da mettere in scena.
 *
 * PLY e STL restituiscono una geometria nuda; OBJ e GLTF una gerarchia pronta.
 *
 * La distinzione fra nuvola di punti e mesh si fa SOLO sulla presenza di facce,
 * cioe di un indice. Prima si guardava anche l'attributo delle normali, ma le
 * normali venivano calcolate qui una riga sopra: erano quindi sempre presenti, e
 * ogni nuvola finiva disegnata come mesh senza indice — cioe come una manciata
 * di triangoli fra vertici consecutivi, praticamente invisibile. E il motivo per
 * cui un rilievo a nuvola di punti mostrava un riquadro nero.
 */
function aOggetto(risultato, estensione) {
    if (estensione === 'gltf' || estensione === 'glb') {
        return risultato.scene;
    }

    if (estensione === 'obj') {
        return risultato;
    }

    const geometria = risultato;

    const indice    = geometria.getIndex();
    const conFacce  = indice !== null && indice.count > 0;
    const conColori = !!geometria.getAttribute('color');

    if (!conFacce) {
        // Nuvola di punti. La dimensione definitiva si calcola in inquadra(),
        // quando si conosce l'estensione del modello: un valore fisso sarebbe
        // invisibile su una grotta di due chilometri e un pastello su una sala.
        return new THREE.Points(geometria, new THREE.PointsMaterial({
            size: 1, sizeAttenuation: true,
            vertexColors: conColori, color: conColori ? 0xffffff : COLORE_MODELLO
        }));
    }

    // Le normali servono solo alle superfici, e solo se il file non le porta.
    if (!geometria.getAttribute('normal')) {
        geometria.computeVertexNormals();
    }

    return new THREE.Mesh(geometria, new THREE.MeshStandardMaterial({
        color: conColori ? 0xffffff : COLORE_MODELLO,
        vertexColors: conColori,
        roughness: 0.85, metalness: 0.05,
        side: THREE.DoubleSide   // i rilievi hanno spesso facce orientate a caso
    }));
}

/**
 * Conta vertici e facce di un oggetto, per poterlo dire a chi guarda.
 *
 * Serve a distinguere «il file non e arrivato» da «il file e arrivato ma non si
 * vede»: sono due guasti diversi e con un riquadro nero si somigliano.
 */
function conteggia(oggetto) {
    let vertici = 0, facce = 0, nuvole = 0, superfici = 0;

    oggetto.traverse(function (nodo) {
        if (!nodo.geometry) {
            return;
        }
        const posizione = nodo.geometry.getAttribute('position');
        if (posizione) {
            vertici += posizione.count;
        }
        const indice = nodo.geometry.getIndex();
        if (indice) {
            facce += indice.count / 3;
        }
        if (nodo.isPoints) { nuvole++; }
        if (nodo.isMesh)   { superfici++; }
    });

    return { vertici, facce, nuvole, superfici };
}

/**
 * Centra il modello e sistema la camera perche lo inquadri tutto.
 *
 * I rilievi arrivano con coordinate assolute — a volte metri UTM, cioe numeri
 * a sette cifre — e senza ricentrare l'oggetto finirebbe lontanissimo dalla
 * camera, cioe invisibile.
 */
function inquadra(stato, oggetto) {
    const riquadro = new THREE.Box3().setFromObject(oggetto);
    if (riquadro.isEmpty()) {
        return { larghezza: 0, altezza: 0, profondita: 0 };
    }

    const centro = riquadro.getCenter(new THREE.Vector3());
    const misura = riquadro.getSize(new THREE.Vector3());

    // Si traslano i VERTICI, non l'oggetto.
    //
    // Spostare l'oggetto lascerebbe i vertici alle loro coordinate assolute, e
    // la somma vertice+posizione la fa la scheda grafica in virgola mobile a 32
    // bit: a un nord UTM di 4.678.705 quella precisione vale circa mezzo metro,
    // quindi un rilievo di dieci metri diventerebbe una scalinata e uno di un
    // metro sparirebbe. Sottraendo qui, i valori scendono vicino a zero e la
    // precisione torna piena. Le geometrie condivise si traslano una volta sola.
    const gia = new Set();
    oggetto.traverse(function (nodo) {
        if (nodo.geometry && !gia.has(nodo.geometry)) {
            gia.add(nodo.geometry);
            nodo.geometry.translate(-centro.x, -centro.y, -centro.z);
        }
    });

    const massimo = Math.max(misura.x, misura.y, misura.z) || 1;
    const distanza = massimo * 1.8;

    stato.camera.position.set(distanza, distanza * 0.6, distanza);
    stato.camera.near = massimo / 1000;
    stato.camera.far  = massimo * 100;
    stato.camera.updateProjectionMatrix();
    stato.camera.lookAt(0, 0, 0);

    stato.comandi.target.set(0, 0, 0);
    stato.comandi.update();

    // La dimensione dei punti va rapportata al modello: due centimetri vanno
    // bene per una saletta, non per una grotta di due chilometri. Si applica a
    // tutte le nuvole dell'oggetto, non solo alla radice, perche un OBJ puo
    // contenerne piu di una.
    oggetto.traverse(function (nodo) {
        if (nodo.isPoints && nodo.material) {
            nodo.material.size = massimo / 400;
        }
    });

    return { larghezza: misura.x, altezza: misura.y, profondita: misura.z };
}

/** Assi di riferimento, per capire l'orientamento del modello. */
function creaAssi(dimensione) {
    return new THREE.AxesHelper(dimensione * 0.6);
}

// ============================================================================
//  AVVIO
// ============================================================================

document.addEventListener('DOMContentLoaded', function () {

    const contenitore = document.getElementById('catageo3d');
    if (!contenitore) {
        return;
    }

    const stato3d   = { pronto: false };
    const bottone   = document.getElementById('catageo3dCarica');
    const messaggio = document.getElementById('catageo3dMessaggio');
    const dati      = document.getElementById('catageo3dDati');
    const filo      = document.getElementById('catageo3dFilo');
    const assi      = document.getElementById('catageo3dAssi');
    const schermo   = document.getElementById('catageo3dSchermo');

    function avvisa(testo, classe) {
        if (!messaggio) {
            return;
        }
        messaggio.textContent = testo;
        messaggio.className = 'alert py-2 ' + (classe || 'alert-secondary');
        messaggio.hidden = testo === '';
    }

    function carica() {
        const url         = contenitore.getAttribute('data-modello');
        const estensione  = (contenitore.getAttribute('data-formato') || '').toLowerCase();
        const lettore     = caricatore(estensione);

        if (!url || !lettore) {
            avvisa('Formato non apribile nel visualizzatore: ' + estensione, 'alert-warning');
            return;
        }

        avvisa('Caricamento del modello…', 'alert-secondary');
        if (bottone) {
            bottone.disabled = true;
        }

        distruggi(vista);
        vista = creaVista(contenitore);

        lettore.load(
            url,
            function (risultato) {
                try {
                    const oggetto = aOggetto(risultato, estensione);
                    vista.scena.add(oggetto);
                    vista.oggetto = oggetto;

                    const misura = inquadra(vista, oggetto);
                    vista.dimensione = Math.max(misura.larghezza, misura.altezza, misura.profondita);

                    const conto = conteggia(oggetto);

                    if (dati) {
                        // Vertici e facce servono a distinguere «non e arrivato»
                        // da «e arrivato ma non si vede»: con un riquadro nero i
                        // due guasti si somigliano, e senza numeri non si sa
                        // nemmeno da che parte cominciare a guardare.
                        const pezzi = [conto.vertici.toLocaleString('it-IT') + ' vertici'];
                        if (conto.facce > 0) {
                            pezzi.push(Math.round(conto.facce).toLocaleString('it-IT') + ' facce');
                        } else {
                            pezzi.push('nuvola di punti, nessuna faccia');
                        }
                        // Le unita del file non sono dichiarate da nessuna parte:
                        // si scrive "unita" e non "metri", perche affermare metri
                        // sarebbe un'informazione inventata.
                        pezzi.push('ingombro '
                            + misura.larghezza.toFixed(1) + ' x '
                            + misura.altezza.toFixed(1) + ' x '
                            + misura.profondita.toFixed(1) + ' unita del file');

                        dati.textContent = pezzi.join(' · ');
                    }

                    // Un file valido ma senza geometrie darebbe un riquadro nero
                    // senza spiegazione: meglio dirlo.
                    if (conto.vertici === 0) {
                        avvisa('Il file e stato letto ma non contiene geometrie da mostrare.',
                            'alert-warning');
                        return;
                    }

                    stato3d.pronto = true;
                    avvisa('', '');

                    // Come per la mappa, l'istanza resta raggiungibile: serve a
                    // verificare cosa c'e davvero in scena, e sara il punto
                    // d'innesto per chi vorra aggiungere strati al modello.
                    window.CATAGEO = window.CATAGEO || {};
                    window.CATAGEO.vista3d = vista;
                } catch (e) {
                    avvisa('Modello caricato ma non visualizzabile: ' + e.message, 'alert-danger');
                }
                if (bottone) {
                    bottone.disabled = false;
                    bottone.hidden = true;
                }
            },
            function (avanzamento) {
                if (avanzamento && avanzamento.lengthComputable && avanzamento.total > 0) {
                    const quota = Math.round((avanzamento.loaded / avanzamento.total) * 100);
                    avvisa('Caricamento del modello… ' + quota + '%', 'alert-secondary');
                }
            },
            function (errore) {
                avvisa('Modello non caricato: ' + (errore && errore.message ? errore.message : 'errore di rete'),
                    'alert-danger');
                if (bottone) {
                    bottone.disabled = false;
                }
            }
        );
    }

    if (bottone) {
        bottone.addEventListener('click', carica);
    }

    if (filo) {
        filo.addEventListener('click', function () {
            if (!vista || !vista.oggetto) {
                return;
            }
            vista.filoDiFerro = !vista.filoDiFerro;
            vista.oggetto.traverse(function (nodo) {
                if (nodo.material && 'wireframe' in nodo.material) {
                    nodo.material.wireframe = vista.filoDiFerro;
                }
            });
            filo.classList.toggle('active', vista.filoDiFerro);
        });
    }

    if (assi) {
        assi.addEventListener('click', function () {
            if (!vista) {
                return;
            }
            if (vista.assi) {
                vista.scena.remove(vista.assi);
                vista.assi.dispose();
                vista.assi = null;
            } else {
                vista.assi = creaAssi(vista.dimensione || 1);
                vista.scena.add(vista.assi);
            }
            assi.classList.toggle('active', !!vista.assi);
        });
    }

    if (schermo) {
        schermo.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (contenitore.requestFullscreen) {
                contenitore.requestFullscreen();
            }
        });
    }

    // Lasciando la pagina si libera la memoria della scheda grafica: senza,
    // navigando fra dieci schede con modello il browser rallenta e basta.
    window.addEventListener('pagehide', function () {
        distruggi(vista);
        vista = null;
    });
});
