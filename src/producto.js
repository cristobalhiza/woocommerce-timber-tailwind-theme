/**
 * Galería, swatches y contador de cantidad de la ficha de producto.
 *
 * Ninguno de los dos mantiene estado propio de disponibilidad: el <select> de
 * variaciones de WooCommerce sigue en el DOM y es la única fuente de verdad
 * (design.md — decisión 14). Los swatches lo reflejan y le devuelven los
 * clics; la galería reacciona a los eventos que emite su script.
 */

const eventosDeVariacion = 'found_variation reset_data woocommerce_update_variation_values';

export function registrarGaleria(Alpine) {
    Alpine.data('galeria', (imagenes) => ({
        imagenes,
        indice: 0,

        get actual() {
            return this.imagenes[this.indice] ?? null;
        },

        init() {
            const jq = window.jQuery;
            const formulario = this.$el.closest('.product')?.querySelector('.variations_form');

            if (!jq || !formulario) {
                return;
            }

            jq(formulario).on('found_variation', (evento, variacion) => {
                const src = variacion?.image?.src;

                if (!src) {
                    return;
                }

                const existente = this.imagenes.findIndex((imagen) => imagen.id === variacion.image_id);

                if (existente >= 0) {
                    this.indice = existente;
                    return;
                }

                // Imagen de variación que no estaba en la galería del producto.
                this.imagenes.push({
                    id: variacion.image_id,
                    src,
                    miniatura: variacion.image.thumb_src || src,
                    alt: variacion.image.alt || '',
                });
                this.indice = this.imagenes.length - 1;
            });

            jq(formulario).on('reset_data', () => {
                this.indice = 0;
            });
        },
    }));
}

/**
 * Da vida a los botones − y + que includes/woocommerce.php inyecta dentro de
 * .quantity. El escuchador es delegado: WooCommerce redibuja el formulario al
 * cambiar de variación, así que enlazar botón a botón dejaría muertos los que
 * llegan después.
 *
 * El clamp a min/max/step lo hace stepUp()/stepDown() del navegador; el evento
 * change es el que espera WooCommerce para revalidar el formulario.
 *
 * ponytail: asume el input numérico de serie. Si algún día se filtra
 * woocommerce_quantity_input_type a "text", stepUp deja de existir y hay que
 * pasar a aritmética leyendo min/max/step a mano.
 */
export function iniciarContadores() {
    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.il-paso');

        if (!boton) {
            return;
        }

        const campo = boton.parentElement.querySelector('input.qty');

        if (!campo || typeof campo.stepUp !== 'function') {
            return;
        }

        if (boton.dataset.paso === '-1') {
            campo.stepDown();
        } else {
            campo.stepUp();
        }

        campo.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

export function iniciarSwatches() {
    const jq = window.jQuery;

    if (!jq) {
        return;
    }

    document.querySelectorAll('.variations_form').forEach((formulario) => {
        formulario.querySelectorAll('.variations select').forEach((select) => {
            const lista = document.createElement('div');
            lista.className = 'il-swatches flex flex-wrap gap-2 mt-2';
            select.after(lista);

            // El select se oculta a la vista pero permanece en el DOM y sigue
            // siendo el control accesible: los swatches son presentación y por
            // eso quedan fuera del orden de tabulación.
            select.classList.add('sr-only');

            const pintar = () => {
                lista.replaceChildren();

                Array.from(select.options).forEach((opcion) => {
                    if (!opcion.value) {
                        return;
                    }

                    const boton = document.createElement('button');
                    boton.type = 'button';
                    boton.textContent = opcion.textContent;
                    boton.disabled = opcion.disabled;
                    boton.tabIndex = -1;
                    boton.setAttribute('aria-hidden', 'true');
                    boton.className = [
                        'min-w-11 h-11 px-3 rounded border text-sm transition',
                        opcion.disabled
                            ? 'border-gray-200 text-gray-300 line-through cursor-not-allowed'
                            : 'border-gray-300 hover:border-gray-900 cursor-pointer',
                        opcion.value === select.value && !opcion.disabled
                            ? 'border-gray-900 ring-2 ring-gray-900'
                            : '',
                    ].join(' ');

                    boton.addEventListener('click', () => {
                        select.value = opcion.value;
                        jq(select).trigger('change');
                    });

                    lista.append(boton);
                });
            };

            pintar();

            // WooCommerce reescribe las <option> al cambiar otro atributo; se
            // repinta después de que lo haya hecho, no antes.
            jq(formulario).on(eventosDeVariacion, () => window.setTimeout(pintar, 0));
            select.addEventListener('change', pintar);
        });
    });
}
