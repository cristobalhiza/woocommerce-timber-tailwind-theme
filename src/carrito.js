/**
 * Carrito sobre la Store API de WooCommerce.
 *
 * Un único store alimenta el widget de cabecera y el drawer. El servidor es la
 * fuente de verdad: cada mutación reemplaza el estado completo con el carrito
 * que devuelve la respuesta, sin calcular nada en el cliente
 * (design.md — decisión 4).
 *
 * El estado inicial y el nonce se piden en tiempo de ejecución, nunca del HTML:
 * el spike confirmó que las páginas cacheables se comparten entre visitantes
 * anónimos y envejecen sin renovarse.
 */

const BASE = '/wp-json/wc/store/v1';

/** La Store API ha usado dos nombres para la cabecera del nonce. */
const CABECERAS_NONCE = ['Nonce', 'X-WC-Store-API-Nonce'];

/** Eventos que emiten los bloques de carrito y pago al mutar el carrito. */
const EVENTOS_BLOQUES = [
    'wc-blocks_added_to_cart',
    'wc-blocks_removed_from_cart',
    'wc-blocks_cart_set_quantity',
];

/**
 * Formatea un importe usando los metadatos de moneda que la propia respuesta
 * trae, en vez de suponer el formato local.
 */
function formatear(importe, moneda) {
    if (importe === undefined || importe === null || !moneda) {
        return '';
    }

    const unidades = Number(moneda.currency_minor_unit ?? 0);
    const valor = Number(importe) / 10 ** unidades;
    const [entero, decimal] = valor.toFixed(unidades).split('.');
    const conMiles = entero.replace(/\B(?=(\d{3})+(?!\d))/g, moneda.currency_thousand_separator ?? '');
    const numero = decimal ? conMiles + (moneda.currency_decimal_separator ?? '.') + decimal : conMiles;

    return (moneda.currency_prefix ?? '') + numero + (moneda.currency_suffix ?? '');
}

/**
 * Los mensajes de la Store API vienen con entidades HTML («&quot;»). El drawer
 * los pinta con x-text, así que hay que decodificarlas o se leerían literales.
 */
function textoPlano(html) {
    if (!html) {
        return '';
    }

    return new DOMParser().parseFromString(html, 'text/html').body.textContent || '';
}

export function registrarCarrito(Alpine) {
    Alpine.store('carrito', {
        // 'cargando' es un estado propio, distinto de 'vacío': mientras no haya
        // respuesta del servidor la interfaz no puede afirmar que no hay nada.
        estado: 'cargando',
        articulos: [],
        cantidad: 0,
        total: '',
        mensaje: '',
        abierto: false,
        ocupado: false,
        nonce: '',

        get vacio() {
            return this.estado === 'listo' && this.articulos.length === 0;
        },

        get cargando() {
            return this.estado === 'cargando';
        },

        /**
         * La línea del carrito de un producto, o null si no está.
         *
         * La usan las tarjetas de listado para decidir si pintan el botón de
         * añadir o el contador de cantidad.
         */
        linea(id) {
            return this.articulos.find((articulo) => articulo.id === Number(id)) ?? null;
        },

        abrir() {
            this.abierto = true;
        },

        cerrar() {
            this.abierto = false;
            this.mensaje = '';
        },

        guardarNonce(respuesta) {
            for (const nombre of CABECERAS_NONCE) {
                const valor = respuesta.headers.get(nombre);

                if (valor) {
                    this.nonce = valor;
                    return;
                }
            }
        },

        aplicar(carrito) {
            const moneda = carrito.totals ?? {};

            this.articulos = (carrito.items ?? []).map((articulo) => ({
                clave: articulo.key,
                // El id del producto (o de la variación) es lo que permite a un
                // listado reconocer sus propias líneas: ver linea().
                id: articulo.id,
                nombre: articulo.name,
                enlace: articulo.permalink,
                cantidad: articulo.quantity,
                maximo: articulo.quantity_limits?.maximum ?? null,
                imagen: articulo.images?.[0]?.thumbnail ?? '',
                alt: articulo.images?.[0]?.alt ?? '',
                total: formatear(articulo.totals?.line_total, articulo.totals ?? moneda),
            }));

            this.cantidad = carrito.items_count ?? 0;
            this.total = formatear(moneda.total_price, moneda);
            this.estado = 'listo';
        },

        /**
         * Bootstrap: una sola petición que trae estado inicial y nonce fresco.
         */
        async sincronizar({ inicial = false } = {}) {
            if (inicial) {
                this.estado = 'cargando';
            }

            try {
                const respuesta = await fetch(`${BASE}/cart`, { credentials: 'same-origin' });

                this.guardarNonce(respuesta);

                if (!respuesta.ok) {
                    throw new Error('respuesta no válida');
                }

                this.aplicar(await respuesta.json());

                return true;
            } catch {
                // Sin estado del servidor no se afirma nada sobre el carrito.
                this.estado = 'error';
                this.mensaje = 'No pudimos cargar tu carrito. Recarga la página para intentarlo de nuevo.';

                return false;
            }
        },

        /**
         * Mutación. La respuesta del servidor sustituye el estado completo.
         *
         * Ante 403 se rehace el bootstrap para obtener un nonce fresco y se
         * reintenta una vez; si vuelve a fallar se informa y se conserva el
         * último estado confirmado.
         */
        async mutar(ruta, cuerpo, reintentar = true) {
            this.ocupado = true;
            this.mensaje = '';

            try {
                const respuesta = await fetch(`${BASE}/cart/${ruta}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Nonce: this.nonce },
                    body: JSON.stringify(cuerpo),
                });

                this.guardarNonce(respuesta);

                if (403 === respuesta.status && reintentar) {
                    await this.sincronizar();

                    return this.mutar(ruta, cuerpo, false);
                }

                const datos = await respuesta.json().catch(() => null);

                if (!respuesta.ok) {
                    this.mensaje = textoPlano(datos?.message) || 'No pudimos completar la operación.';
                    // El servidor rechazó el cambio: se vuelve a su estado real
                    // para no quedar mostrando algo que no ocurrió.
                    await this.sincronizar();

                    return false;
                }

                this.aplicar(datos);

                return true;
            } catch {
                this.mensaje = 'Se perdió la conexión. Comprobamos el estado de tu carrito.';
                await this.sincronizar();

                return false;
            } finally {
                this.ocupado = false;
            }
        },

        // Añadir no abre el panel: el contador de la cabecera es el acuse de
        // recibo y quien quiera ver el carrito lo abre desde ahí.
        anadir(id, cantidad = 1, variacion = []) {
            return this.mutar('add-item', { id: Number(id), quantity: Number(cantidad), variation: variacion });
        },

        cambiarCantidad(clave, cantidad) {
            if (cantidad < 1) {
                return this.eliminar(clave);
            }

            return this.mutar('update-item', { key: clave, quantity: Number(cantidad) });
        },

        eliminar(clave) {
            return this.mutar('remove-item', { key: clave });
        },
    });
}

/**
 * Conecta el store con el resto de la página: arranque, formularios de añadir
 * al carrito y mutaciones hechas desde los bloques de WooCommerce.
 */
export function iniciarCarrito(Alpine) {
    const carrito = Alpine.store('carrito');

    carrito.sincronizar({ inicial: true });

    // Botones de añadir de los listados.
    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.add_to_cart_button[data-product_id]');

        if (!boton || boton.classList.contains('product_type_variable')) {
            return;
        }

        evento.preventDefault();
        carrito.anadir(boton.dataset.product_id, boton.dataset.quantity || 1);
    });

    // Formulario de la ficha de producto, simple y variable.
    document.addEventListener('submit', (evento) => {
        const formulario = evento.target.closest('form.cart');

        if (!formulario) {
            return;
        }

        const variacion = formulario.querySelector('input[name="variation_id"]');
        const id = variacion?.value && '0' !== variacion.value
            ? variacion.value
            : formulario.querySelector('[name="add-to-cart"]')?.value;

        if (!id) {
            return;
        }

        evento.preventDefault();

        const atributos = Array.from(formulario.querySelectorAll('[name^="attribute_"]'))
            .filter((campo) => campo.value)
            .map((campo) => ({ attribute: campo.name, value: campo.value }));

        carrito.anadir(id, formulario.querySelector('[name="quantity"]')?.value || 1, atributos);
    });

    // La página de carrito son bloques de WooCommerce y no consumen este store.
    // El punto de sincronía es el servidor: cuando los bloques mutan, se vuelve
    // a preguntar por el estado real.
    EVENTOS_BLOQUES.forEach((evento) => {
        document.body.addEventListener(evento, () => carrito.sincronizar());
    });

    // Cerrar el drawer con Escape.
    document.addEventListener('keydown', (evento) => {
        if ('Escape' === evento.key && carrito.abierto) {
            carrito.cerrar();
        }
    });
}
