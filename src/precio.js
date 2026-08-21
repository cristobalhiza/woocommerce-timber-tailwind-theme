/**
 * Rango de precio: dos deslizadores sobre la misma pista, atados a los dos
 * campos numéricos.
 *
 * Son dos `<input type="range">` nativos superpuestos —el navegador ya trae el
 * arrastre, el teclado y el soporte táctil— y el CSS deja pasar el puntero solo
 * por los tiradores. Sin librería de sliders.
 *
 * El formulario es un GET normal: lo que se envía son los dos campos numéricos.
 * Los deslizadores solo los mueven.
 */

export function registrarPrecio(Alpine) {
    Alpine.data('rangoPrecio', (piso = 0, techo = 0, min = null, max = null) => ({
        piso,
        techo,
        // Sin filtro puesto, los tiradores empiezan en los extremos.
        min: min ?? piso,
        max: max ?? techo,

        // Un paso de un peso son decenas de miles de posiciones en un recorrido
        // de 90.000: el tirador se vuelve imposible de colocar. Se redondea a
        // una escala que se pueda apuntar con el dedo.
        get paso() {
            return this.techo - this.piso > 20000 ? 1000 : 100;
        },

        /**
         * Los tiradores no se cruzan: el de abajo empuja al de arriba y al
         * revés. Cruzarlos deja un rango invertido que no devuelve nada.
         */
        ordenar(movido) {
            this.min = this.limitar(this.min);
            this.max = this.limitar(this.max);

            if (this.min > this.max) {
                if (movido === 'min') {
                    this.max = this.min;
                } else {
                    this.min = this.max;
                }
            }
        },

        limitar(valor) {
            return Math.min(Math.max(Number(valor) || 0, this.piso), this.techo);
        },

        /** Porcentajes de la barra coloreada entre los dos tiradores. */
        get desde() {
            return this.porcentaje(this.min);
        },

        get hasta() {
            return this.porcentaje(this.max);
        },

        porcentaje(valor) {
            const recorrido = this.techo - this.piso;

            return recorrido > 0 ? ((this.limitar(valor) - this.piso) / recorrido) * 100 : 0;
        },

        moneda(valor) {
            return new Intl.NumberFormat('es-CL', {
                style: 'currency',
                currency: 'CLP',
                maximumFractionDigits: 0,
            }).format(valor);
        },

        /**
         * Antes de enviar, se desactiva el campo que sigue en su extremo.
         *
         * Un campo desactivado no viaja, y así una búsqueda sin tocar el precio
         * no arrastra `min_price` y `max_price` por toda la navegación: la URL
         * queda limpia y la caché del hosting no ve una variante por cada
         * combinación que da lo mismo.
         */
        preparar() {
            if (this.min <= this.piso) {
                this.$refs.min.disabled = true;
            }

            if (this.max >= this.techo) {
                this.$refs.max.disabled = true;
            }
        },
    }));
}
