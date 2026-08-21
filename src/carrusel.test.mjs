/**
 * Comprobación del carrusel. Sin framework: node src/carrusel.test.mjs
 *
 * Cubre la única rama que no se ve mirando el HTML: con avance automático el
 * recorrido es circular —del final al principio y al revés—, y sin él las
 * flechas se quedan en el extremo.
 */

import assert from 'node:assert/strict';
import { registrarCarrusel } from './carrusel.js';

// --- dobles mínimos -------------------------------------------------------

globalThis.window = { matchMedia: () => ({ matches: false }), addEventListener() {} };

// Un intervalo de verdad deja el proceso vivo para siempre: aquí solo importa
// que se registre, no que dispare.
let intervalos = 0;
globalThis.setInterval = () => ++intervalos;

let componente;
const Alpine = { data: (_nombre, fabrica) => { componente = fabrica; } };

registrarCarrusel(Alpine);

/** Carrusel montado sobre una pista falsa que anota a dónde le mandan. */
const carruselEn = (posicion, autoplay = 0) => {
    const llamadas = [];
    const pista = {
        scrollWidth: 3000,
        clientWidth: 1000,
        scrollLeft: posicion,
        scrollTo: (args) => llamadas.push(['scrollTo', args.left]),
        scrollBy: (args) => llamadas.push(['scrollBy', args.left]),
        addEventListener() {},
    };

    const c = Object.assign(componente(autoplay), { $refs: { pista } });
    c.init();

    return [c, llamadas];
};

// --- sin avance automático ------------------------------------------------

{
    const [c, llamadas] = carruselEn(2000);

    assert.equal(c.fin, true, 'en el extremo derecho');
    c.desplazar(1);
    assert.deepEqual(llamadas, [['scrollBy', 800]], 'sin autoplay no vuelve al principio');
}

// --- con avance automático: circular --------------------------------------

{
    const [c, llamadas] = carruselEn(2000, 6000);

    assert.deepEqual(llamadas, [], 'el intervalo no dispara al montar');
    assert.equal(intervalos, 1, 'con autoplay se registra el intervalo');
    c.desplazar(1);
    assert.deepEqual(llamadas, [['scrollTo', 0]], 'del final al principio');
}

{
    const [c, llamadas] = carruselEn(0, 6000);
    assert.equal(c.inicio, true);
    c.desplazar(-1);
    assert.deepEqual(llamadas, [['scrollTo', 3000]], 'del principio al final');
}

// --- en medio, avance normal ----------------------------------------------

{
    const [c, llamadas] = carruselEn(1000, 6000);
    c.desplazar(1);
    assert.deepEqual(llamadas, [['scrollBy', 800]], 'en medio se avanza sin saltar');
}

console.log('carrusel: ok');
