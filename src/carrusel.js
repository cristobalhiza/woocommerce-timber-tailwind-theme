/**
 * Carrusel.
 *
 * El desplazamiento lo hace el navegador con scroll-snap: aquí solo viven el
 * estado de las flechas y el avance solo (design.md — decisión 13). Sin
 * librería.
 *
 * `autoplay` son los milisegundos entre avances; 0 lo apaga. Con avance solo,
 * el carrusel es circular —al final vuelve al principio— y las flechas no se
 * deshabilitan en los extremos, porque el recorrido no tiene extremos.
 */

export function registrarCarrusel(Alpine) {
    Alpine.data('carrusel', (autoplay = 0) => ({
        inicio: true,
        fin: false,
        desborde: false,
        pausado: false,
        autoplay,

        init() {
            const pista = this.$refs.pista;

            if (!pista) {
                return;
            }

            this.actualizar();

            pista.addEventListener('scroll', () => this.actualizar(), { passive: true });

            // Al cambiar el ancho, un carrusel que desbordaba puede dejar de
            // hacerlo: las flechas tienen que desaparecer con él.
            if ('ResizeObserver' in window) {
                new ResizeObserver(() => this.actualizar()).observe(pista);
            } else {
                window.addEventListener('resize', () => this.actualizar());
            }

            // Un movimiento que nadie pidió y no se puede parar es justo lo que
            // desactiva quien marca «reducir movimiento»: ahí no se avanza solo
            // y quedan las flechas.
            if (this.autoplay && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                setInterval(() => {
                    if (!this.pausado && this.desborde) {
                        this.desplazar(1);
                    }
                }, this.autoplay);
            }
        },

        actualizar() {
            const pista = this.$refs.pista;
            const margen = 1; // Tolerancia para anchos fraccionarios.

            this.desborde = pista.scrollWidth > pista.clientWidth + margen;
            this.inicio = pista.scrollLeft <= margen;
            this.fin = pista.scrollLeft + pista.clientWidth >= pista.scrollWidth - margen;
        },

        desplazar(direccion) {
            const pista = this.$refs.pista;

            // Circular solo con avance automático: sin él, llegar al extremo y
            // saltar al otro lado sorprende a quien pulsa la flecha.
            if (this.autoplay) {
                if (direccion > 0 && this.fin) {
                    pista.scrollTo({ left: 0, behavior: 'smooth' });
                    return;
                }

                if (direccion < 0 && this.inicio) {
                    pista.scrollTo({ left: pista.scrollWidth, behavior: 'smooth' });
                    return;
                }
            }

            pista.scrollBy({ left: direccion * pista.clientWidth * 0.8, behavior: 'smooth' });
        },
    }));
}
