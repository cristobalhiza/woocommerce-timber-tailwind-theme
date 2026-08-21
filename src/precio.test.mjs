/**
 * Comprobación del rango de precio. Sin framework: node src/precio.test.mjs
 *
 * Cubre lo que no se ve leyendo el HTML: que los tiradores no se crucen y que
 * el campo que se queda en su extremo no viaje en el envío.
 */

import assert from 'node:assert/strict';
import { registrarPrecio } from './precio.js';

let componente;
registrarPrecio({ data: (_nombre, fabrica) => { componente = fabrica; } });

/** Rango montado con dos campos falsos que anotan si quedaron desactivados. */
const rango = (piso, techo, min = null, max = null) => Object.assign(
    componente(piso, techo, min, max),
    { $refs: { min: { disabled: false }, max: { disabled: false } } }
);

// --- los tiradores no se cruzan ------------------------------------------

const empuja = rango(1000, 90000, 1000, 20000);
empuja.min = 50000;
empuja.ordenar('min');
assert.equal(empuja.max, 50000, 'el mínimo por encima del máximo empuja al máximo');

const empujaAlReves = rango(1000, 90000, 30000, 90000);
empujaAlReves.max = 10000;
empujaAlReves.ordenar('max');
assert.equal(empujaAlReves.min, 10000, 'el máximo por debajo del mínimo empuja al mínimo');

const fuera = rango(1000, 90000, 1000, 90000);
fuera.min = -5000;
fuera.max = 999999;
fuera.ordenar('min');
assert.equal(fuera.min, 1000, 'el mínimo no baja del piso');
assert.equal(fuera.max, 90000, 'el máximo no sube del techo');

// --- solo viaja lo que se tocó -------------------------------------------

const sinTocar = rango(1000, 90000);
sinTocar.preparar();
assert.ok(sinTocar.$refs.min.disabled && sinTocar.$refs.max.disabled,
    'sin tocar nada, ningún campo de precio se envía');

const soloMinimo = rango(1000, 90000, 20000, 90000);
soloMinimo.preparar();
assert.equal(soloMinimo.$refs.min.disabled, false, 'el mínimo movido sí se envía');
assert.ok(soloMinimo.$refs.max.disabled, 'el máximo intacto no se envía');

// --- la barra coloreada --------------------------------------------------

const mitad = rango(0, 100, 25, 75);
assert.equal(mitad.desde, 25);
assert.equal(mitad.hasta, 75);

const plano = rango(5000, 5000, 5000, 5000);
assert.equal(plano.desde, 0, 'sin recorrido no se divide por cero');

console.log('precio: ok');
