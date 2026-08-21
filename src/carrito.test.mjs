/**
 * Comprobación del store de carrito. Sin framework: node src/carrito.test.mjs
 *
 * Cubre lo que no se puede ver mirando el HTML servido: la máquina de estados
 * (cargando nunca es vacío), el reintento único ante nonce caducado y que un
 * fallo deje la interfaz en el último estado confirmado por el servidor.
 */

import assert from 'node:assert/strict';
import { registrarCarrito } from './carrito.js';

// --- dobles mínimos -------------------------------------------------------

globalThis.DOMParser = class {
    parseFromString(html) {
        return { body: { textContent: html.replace(/&quot;/g, '"').replace(/&amp;/g, '&') } };
    }
};

const almacen = {};
const Alpine = {
    store(nombre, valor) {
        if (undefined !== valor) {
            almacen[nombre] = valor;
            return;
        }
        return almacen[nombre];
    },
};

const MONEDA = {
    currency_minor_unit: 0,
    currency_prefix: '$',
    currency_suffix: '',
    currency_thousand_separator: '.',
    currency_decimal_separator: ',',
};

const carritoCon = (items, total) => ({
    items,
    items_count: items.reduce((n, i) => n + i.quantity, 0),
    totals: { ...MONEDA, total_price: total },
});

const ARTICULO = {
    key: 'abc',
    name: 'Vestido Aurora',
    permalink: '/vestido',
    quantity: 2,
    quantity_limits: { maximum: 5 },
    images: [{ thumbnail: '/t.jpg', alt: 'foto' }],
    totals: { ...MONEDA, line_total: 59980 },
};

/** Cola de respuestas que consume el fetch falso. */
let respuestas = [];
let peticiones = [];

globalThis.fetch = async (url, opciones = {}) => {
    peticiones.push({ url, metodo: opciones.method ?? 'GET' });

    const siguiente = respuestas.shift();

    if (!siguiente) {
        throw new Error('sin respuesta preparada');
    }

    if (siguiente.lanzar) {
        throw new Error('red caída');
    }

    return {
        ok: siguiente.estado < 400,
        status: siguiente.estado,
        headers: { get: (n) => ('Nonce' === n ? siguiente.nonce ?? 'n1' : null) },
        json: async () => siguiente.cuerpo,
    };
};

function preparar(...cola) {
    respuestas = cola;
    peticiones = [];
}

const ok = (cuerpo, nonce) => ({ estado: 200, cuerpo, nonce });
const fallo = (estado, cuerpo) => ({ estado, cuerpo });

// --- pruebas --------------------------------------------------------------

registrarCarrito(Alpine);
const carrito = Alpine.store('carrito');

// 1. Cargando no es vacío. Es la garantía del escenario "estado provisional
//    durante la carga": una página cacheada no puede afirmar que no hay nada.
assert.equal(carrito.estado, 'cargando');
assert.equal(carrito.vacio, false, 'un carrito que aún no ha sincronizado no puede declararse vacío');
assert.equal(carrito.cargando, true);

// 2. Bootstrap: una sola petición trae estado y nonce.
preparar(ok(carritoCon([], 0), 'nonce-fresco'));
await carrito.sincronizar({ inicial: true });
assert.equal(peticiones.length, 1, 'el bootstrap es una sola petición');
assert.equal(carrito.nonce, 'nonce-fresco');
assert.equal(carrito.estado, 'listo');
assert.equal(carrito.vacio, true, 'ya sincronizado y sin artículos: ahora sí está vacío');

// 3. Una mutación reemplaza el estado con la respuesta del servidor.
preparar(ok(carritoCon([ARTICULO], 59980)));
await carrito.anadir(14, 2);
assert.equal(carrito.cantidad, 2);
assert.equal(carrito.articulos.length, 1);
assert.equal(carrito.articulos[0].clave, 'abc');
assert.equal(carrito.articulos[0].maximo, 5);
assert.equal(carrito.total, '$59.980', 'el importe se formatea con los metadatos de moneda de la respuesta');
assert.equal(carrito.abierto, false, 'añadir no abre el drawer: el acuse es el contador');

// 4. Nonce caducado: se rehace el bootstrap y se reintenta UNA vez.
preparar(
    fallo(403, { code: 'woocommerce_rest_invalid_nonce' }),
    ok(carritoCon([ARTICULO], 59980), 'nonce-2'),
    ok(carritoCon([ARTICULO, { ...ARTICULO, key: 'def', quantity: 1 }], 72970), 'nonce-2'),
);
const reintento = await carrito.anadir(17, 1);
assert.equal(reintento, true, 'tras renovar el nonce la operación se completa');
assert.equal(peticiones.length, 3, 'mutación fallida + bootstrap + reintento');
assert.equal(carrito.nonce, 'nonce-2');

// 5. Si el 403 persiste, se informa y no se reintenta en bucle.
preparar(
    fallo(403, { code: 'woocommerce_rest_invalid_nonce' }),
    ok(carritoCon([ARTICULO], 59980), 'nonce-3'),
    fallo(403, { message: 'Nonce is invalid.' }),
    ok(carritoCon([ARTICULO], 59980), 'nonce-4'),
);
const rendido = await carrito.anadir(17, 1);
assert.equal(rendido, false);
assert.equal(carrito.mensaje, 'Nonce is invalid.');
assert.equal(carrito.estado, 'listo', 'sigue mostrando el último estado confirmado');

// 6. Error de negocio: mensaje legible y vuelta al estado real del servidor.
preparar(
    fallo(400, { message: 'No puedes añadir &quot;Blusa Lino&quot; porque no hay stock.' }),
    ok(carritoCon([ARTICULO], 59980)),
);
await carrito.anadir(16, 99);
assert.equal(
    carrito.mensaje,
    'No puedes añadir "Blusa Lino" porque no hay stock.',
    'las entidades HTML del mensaje se decodifican antes de pintarlo'
);
assert.equal(carrito.articulos.length, 1, 'el carrito conserva su contenido anterior');

// 7. Fallo de red: se avisa y se vuelve al último estado del servidor.
preparar({ lanzar: true }, ok(carritoCon([ARTICULO], 59980)));
await carrito.cambiarCantidad('abc', 3);
assert.match(carrito.mensaje, /conexión/);
assert.equal(carrito.estado, 'listo');
assert.equal(carrito.cantidad, 2, 'no se aplicó el cambio que nunca llegó al servidor');

// 8. Bajar de 1 elimina la línea en vez de pedir cantidad cero.
preparar(ok(carritoCon([], 0)));
await carrito.cambiarCantidad('abc', 0);
assert.match(peticiones[0].url, /remove-item$/);
assert.equal(carrito.vacio, true);

// 9. Sin respuesta del servidor no se afirma nada.
preparar({ lanzar: true });
await carrito.sincronizar({ inicial: true });
assert.equal(carrito.estado, 'error');
assert.equal(carrito.vacio, false, 'en error tampoco se declara vacío');

console.log('carrito: 9 comprobaciones OK');
