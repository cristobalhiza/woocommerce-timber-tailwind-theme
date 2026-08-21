<?php
/**
 * Integración Timber–WooCommerce.
 *
 * Se usa el paquete de mindkomm en vez de la vía manual de la guía de Timber:
 * aquella solo cubre archivo y ficha, y mezclarlas produce doble render
 * (design.md — decisión 2).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
    return;
}

Timber\Integrations\WooCommerce\WooCommerce::init();

add_action( 'after_setup_theme', function (): void {
    // La galería y las miniaturas de listado las pinta el tema con Timber, así
    // que se retiran las de WooCommerce para que no se rendericen dos veces
    // (catalog-templates — "contenido renderizado una sola vez").
    Timber\Integrations\WooCommerce\WooCommerce::disable_woocommerce_images();
}, 20 );

/**
 * Se retiran las tres hojas clásicas de WooCommerce.
 *
 * Hasta ahora no se aplicaba ninguna sin que nadie lo notara: van scopeadas bajo
 * `.woocommerce` y la cabecera escribía la clase del <body> a mano, sin
 * body_class(). Al reponer body_class() —que hace falta, porque de ahí cuelgan
 * WordPress y los plugins— se encendieron de golpe y se llevaron por delante el
 * diseño: botones en morado, la ficha en dos columnas flotadas y la lista de
 * opiniones a una palabra por línea.
 *
 * El tema repinta ya todo el marcado de WooCommerce que usa (listados, ficha,
 * avisos, formularios, opiniones), así que la hoja de la tienda es peso muerto
 * y en conflicto. Lo único que se pierde son las estrellas de valoración, que
 * venían de su tipografía de iconos; se dibujan en src/input.css con el carácter
 * ★, sin depender de una fuente que hay que servir aparte.
 *
 * No alcanza a wc-blocks.css: el carrito y el pago son bloques y esa sí se
 * queda (design.md — decisión 4).
 */
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/**
 * Varios valores del mismo filtro se suman, no se cruzan.
 *
 * WooCommerce cruza por defecto: pedir 12 y 36 unidades busca el producto que
 * traiga las dos cosas y devuelve cero siempre. En un panel de casillas lo que
 * el visitante quiere decir es «12 o 36». Entre atributos distintos se sigue
 * cruzando —Prudence *y* 36 unidades—, que ahí sí es lo que espera.
 */
add_filter( 'woocommerce_layered_nav_default_query_type', fn(): string => 'or' );

/**
 * Un campo de precio vacío no es «cero».
 *
 * WooCommerce mira `isset($_GET['min_price'])` y luego hace `floatval('')`, que
 * es 0: enviar el formulario de precio sin rellenar nada pedía «de 0 a 0» y
 * devolvía la tienda vacía. Con JavaScript el formulario ya desactiva el campo
 * que nadie tocó, pero esto pasa igual llegando por un enlace o sin JS, así que
 * el vacío se retira antes de que la consulta principal lo lea.
 */
add_action( 'init', function (): void {
    foreach ( [ 'min_price', 'max_price' ] as $clave ) {
        if ( isset( $_GET[ $clave ] ) && '' === trim( (string) $_GET[ $clave ] ) ) {
            unset( $_GET[ $clave ] );
        }
    }
} );

// El tema no tiene barra lateral. Sin esto, WooCommerce llama a
// get_sidebar('shop') en cada página de tienda y WordPress registra un aviso
// de obsolescencia por petición.
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/**
 * Pinta el botón de añadir al carrito de un producto de listado.
 *
 * woocommerce_template_loop_add_to_cart() lee el $product global. En la tienda
 * y en los archivos lo monta el bucle de WooCommerce, pero en la portada los
 * productos llegan por Timber::get_posts() sobre una lista de IDs y ese global
 * no existe: se monta y se restaura aquí.
 *
 * Se reutiliza la función de WooCommerce en vez de escribir el enlace a mano
 * porque de ella salen gratis la etiqueta correcta por tipo de producto
 * («Añadir al carrito» en los simples, «Seleccionar opciones» en los variables,
 * que enlazan a la ficha) y el data-product_id que intercepta iniciarCarrito()
 * para añadir por la Store API.
 */
function il_boton_carrito( int $id ): void {
    $producto = wc_get_product( $id );

    if ( ! $producto ) {
        return;
    }

    $anterior           = $GLOBALS['product'] ?? null;
    $GLOBALS['product'] = $producto;

    woocommerce_template_loop_add_to_cart();

    $GLOBALS['product'] = $anterior;
}

// Botones − y + alrededor del campo de cantidad. Se usan los ganchos que la
// propia plantilla quantity-input.php expone, en vez de copiarla al tema: así
// no hay que reseguir sus cambios en cada actualización de WooCommerce.
// El <input> sigue siendo el control real y la única fuente del valor; por eso
// los botones quedan fuera del orden de tabulación, igual que los swatches: el
// campo numérico ya se maneja con las flechas del teclado y dos paradas más no
// añadirían nada.
add_action( 'woocommerce_before_quantity_input_field', function (): void {
    echo '<button type="button" class="il-paso" data-paso="-1" aria-label="Quitar una unidad" tabindex="-1">&minus;</button>';
} );

add_action( 'woocommerce_after_quantity_input_field', function (): void {
    echo '<button type="button" class="il-paso" data-paso="1" aria-label="Añadir una unidad" tabindex="-1">+</button>';
} );

/**
 * Filtros de atributo activos en la URL, como [taxonomía => slugs].
 */
function il_filtros_activos(): array {
    $filtros = [];

    foreach ( $_GET as $clave => $valor ) {
        if ( ! is_string( $clave ) || ! is_string( $valor ) || ! str_starts_with( $clave, 'filter_' ) ) {
            continue;
        }

        $taxonomia = wc_attribute_taxonomy_name( wc_sanitize_taxonomy_name( substr( $clave, 7 ) ) );

        if ( ! taxonomy_exists( $taxonomia ) ) {
            continue;
        }

        $slugs = array_filter( array_map( 'sanitize_title', explode( ',', wp_unslash( $valor ) ) ) );

        if ( $slugs ) {
            $filtros[ $taxonomia ] = $slugs;
        }
    }

    return $filtros;
}

/**
 * Términos de un atributo presentes en la categoría que se está viendo, con su
 * recuento ahí.
 *
 * El `count` del término es global: en Condones el panel ofrecía «Starsex 16»
 * —dieciséis lubricantes y retardantes, ni un solo condón— y el enlace llevaba
 * a una rejilla vacía. Se cuenta contra los productos de la categoría actual,
 * que es lo que el visitante va a obtener al pulsar.
 *
 * El recuento ignora el término ya elegido a propósito: si se contara con él
 * puesto, el panel se quedaría con una sola marca y no habría forma de cambiar
 * de una a otra sin quitarla antes. Los elegidos se añaden al final aunque no
 * queden productos, porque si no, quien llegue a una combinación vacía por un
 * enlace viejo no tiene dónde pulsar para deshacerla.
 *
 * ponytail: una consulta de IDs y el recuento en PHP. Con 138 productos sobra;
 * si el catálogo llegara a miles, la salida es la tabla de búsqueda de
 * atributos de WooCommerce (Filterer::get_filtered_term_product_counts).
 */
/**
 * IDs de los productos del contexto: la categoría que se está viendo y los
 * filtros de atributo puestos, menos el que se vaya a contar.
 *
 * ponytail: el rango de precio no entra. Es el único filtro que puede seguir
 * prometiendo de más en los recuentos; si molesta, se añade con la consulta de
 * meta sobre `_price`.
 */
function il_productos_del_contexto( int $categoria, string $sin_taxonomia = '' ): array {
    $consulta = [
        [
            'taxonomy' => 'product_visibility',
            'field'    => 'term_taxonomy_id',
            'terms'    => [ wc_get_product_visibility_term_ids()['exclude-from-catalog'] ],
            'operator' => 'NOT IN',
        ],
    ];

    if ( $categoria ) {
        $consulta[] = [
            'taxonomy' => get_queried_object()->taxonomy,
            'field'    => 'term_id',
            'terms'    => $categoria,
        ];
    }

    // Los demás filtros puestos también son contexto: con Prudence marcado, el
    // panel de unidades tiene que decir cuántos Prudence hay de cada tamaño, no
    // cuántos hay en total. Contar sin ellos vuelve a producir el número que no
    // se cumple al pulsar.
    foreach ( il_filtros_activos() as $otra => $slugs ) {
        if ( $otra !== $sin_taxonomia ) {
            $consulta[] = [ 'taxonomy' => $otra, 'field' => 'slug', 'terms' => $slugs ];
        }
    }

    return get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => $consulta,
    ] );
}

/**
 * Precio más bajo y más alto de los productos del contexto, para los extremos
 * del deslizador. Sin productos, o con uno solo, devuelve null: un deslizador
 * con los dos extremos en el mismo sitio no se puede arrastrar.
 */
function il_rango_de_precios( int $categoria ): ?array {
    global $wpdb;

    $productos = il_productos_del_contexto( $categoria );

    if ( ! $productos ) {
        return null;
    }

    // La tabla de búsqueda de WooCommerce ya tiene el precio resuelto por
    // producto —variaciones y rebajas incluidas—, que es justo lo que hace
    // falta y lo que no da un meta suelto.
    $ids = implode( ',', array_map( 'intval', $productos ) );

    $rango = $wpdb->get_row( "SELECT MIN(min_price) AS piso, MAX(max_price) AS techo FROM {$wpdb->wc_product_meta_lookup} WHERE product_id IN ({$ids})" );

    if ( ! $rango || null === $rango->piso ) {
        return null;
    }

    $piso  = (int) floor( $rango->piso );
    $techo = (int) ceil( $rango->techo );

    return $techo > $piso ? [ 'piso' => $piso, 'techo' => $techo ] : null;
}

function il_terminos_del_contexto( string $taxonomia, int $categoria, array $elegidas, int $limite ): array {
    if ( ! taxonomy_exists( $taxonomia ) ) {
        return [];
    }

    $productos = il_productos_del_contexto( $categoria, $taxonomia );

    $terminos = [];

    // `all_with_object_id` devuelve una fila por asignación, no una por término:
    // la repetición es justo lo que se cuenta.
    $asignados = $productos ? wp_get_object_terms( $productos, $taxonomia, [ 'fields' => 'all_with_object_id' ] ) : [];

    foreach ( is_wp_error( $asignados ) ? [] : $asignados as $termino ) {
        $terminos[ $termino->slug ] ??= [ 'slug' => $termino->slug, 'nombre' => $termino->name, 'total' => 0 ];
        $terminos[ $termino->slug ]['total']++;
    }

    uasort( $terminos, fn( array $a, array $b ): int => $b['total'] <=> $a['total'] );

    // El corte es por popularidad, así que las marcas de cola quedan fuera: son
    // 39 y la columna dejaría de leerse. Los tamaños de envase caben todos.
    $terminos = array_slice( $terminos, 0, $limite, true );

    foreach ( $elegidas as $slug ) {
        if ( ! isset( $terminos[ $slug ] ) ) {
            $termino = get_term_by( 'slug', $slug, $taxonomia );

            if ( $termino ) {
                $terminos[ $slug ] = [ 'slug' => $slug, 'nombre' => $termino->name, 'total' => 0 ];
            }
        }
    }

    return array_values( $terminos );
}

/**
 * La URL de la página actual sin el tramo de paginación.
 *
 * Los enlaces de los filtros se construyen sobre la URL en curso, y en
 * `/condones/page/3/` eso deja la página tres pegada al filtro nuevo: si lo
 * filtrado cabe en una página, el visitante aterriza en un 404. Cambiar de
 * filtro siempre vuelve al principio del listado.
 */
function il_url_sin_pagina(): string {
    return preg_replace( '#/page/\d+/?#', '/', remove_query_arg( 'paged' ) );
}

/**
 * Filtros puestos, para las fichas que van sobre la rejilla.
 *
 * Cada una lleva la URL que la quita. La categoría no entra: es la página en la
 * que se está, y ya la dicen las migas y el titular.
 */
function il_filtros_puestos(): array {
    $fichas = [];

    foreach ( il_filtros_activos() as $taxonomia => $slugs ) {
        $parametro = 'filter_' . str_replace( 'pa_', '', $taxonomia );

        foreach ( $slugs as $slug ) {
            $termino = get_term_by( 'slug', $slug, $taxonomia );

            if ( ! $termino ) {
                continue;
            }

            $restantes = array_diff( $slugs, [ $slug ] );

            $fichas[] = [
                'etiqueta' => $termino->name . ( 'pa_unidades' === $taxonomia ? ' u.' : '' ),
                'quitar'   => $restantes
                    ? add_query_arg( $parametro, implode( ',', $restantes ), il_url_sin_pagina() )
                    : remove_query_arg( $parametro, il_url_sin_pagina() ),
            ];
        }
    }

    $min = isset( $_GET['min_price'] ) ? (int) $_GET['min_price'] : null;
    $max = isset( $_GET['max_price'] ) ? (int) $_GET['max_price'] : null;

    if ( null !== $min || null !== $max ) {
        // wc_price() devuelve el símbolo como entidad (`&#36;`) dentro de su
        // marcado; sin decodificarlo, el autoescape de Twig lo imprime tal cual.
        $precio = static fn( int $valor ): string => html_entity_decode( wp_strip_all_tags( wc_price( $valor ) ), ENT_QUOTES, 'UTF-8' );

        $fichas[] = [
            'etiqueta' => match ( true ) {
                null === $max => 'Desde ' . $precio( $min ),
                null === $min => 'Hasta ' . $precio( $max ),
                default       => $precio( $min ) . ' – ' . $precio( $max ),
            },
            'quitar'   => remove_query_arg( [ 'min_price', 'max_price' ], il_url_sin_pagina() ),
        ];
    }

    return $fichas;
}

/** URL de la misma página sin ningún filtro puesto. */
function il_url_sin_filtros(): string {
    $parametros = array_merge(
        [ 'min_price', 'max_price' ],
        array_map( fn( string $taxonomia ): string => 'filter_' . str_replace( 'pa_', '', $taxonomia ), array_keys( il_filtros_activos() ) )
    );

    return remove_query_arg( $parametros, il_url_sin_pagina() );
}

/**
 * Bloque de filtro por atributo: los términos del contexto, ya con su enlace.
 *
 * El parámetro `filter_<atributo>` lo resuelve el núcleo de WooCommerce
 * (WC_Query::get_layered_nav_chosen_attributes), así que aquí solo se pintan
 * los enlaces: marcado, el enlace lo quita; sin marcar, lo añade a los que ya
 * haya. El mismo control sirve para poner y para sacar el filtro.
 */
function il_faceta( string $atributo, int $categoria, bool $numerico = false, int $limite = 12 ): array {
    $parametro = 'filter_' . $atributo;
    $taxonomia = wc_attribute_taxonomy_name( $atributo );

    $elegidos = il_filtros_activos()[ $taxonomia ] ?? [];

    $terminos = il_terminos_del_contexto( $taxonomia, $categoria, $elegidos, $limite );

    if ( $numerico ) {
        usort( $terminos, fn( array $a, array $b ): int => (int) $a['nombre'] <=> (int) $b['nombre'] );
    }

    return array_map( function ( array $termino ) use ( $elegidos, $parametro ): array {
        $activo = in_array( $termino['slug'], $elegidos, true );

        $nuevos = $activo
            ? array_diff( $elegidos, [ $termino['slug'] ] )
            : array_merge( $elegidos, [ $termino['slug'] ] );

        return [
            'nombre' => $termino['nombre'],
            'total'  => $termino['total'],
            'activa' => $activo,
            'enlace' => $nuevos
                ? add_query_arg( $parametro, implode( ',', $nuevos ), il_url_sin_pagina() )
                : remove_query_arg( $parametro, il_url_sin_pagina() ),
        ];
    }, $terminos );
}

/**
 * Columna de filtros de la tienda y de los archivos de categoría.
 *
 * El archivo lo pinta WooCommerce con sus plantillas PHP —el tema solo sustituye
 * el <li> de cada producto—, así que la columna no puede colgarse de una
 * plantilla Twig de página: se abre y se cierra alrededor del bucle con los
 * ganchos que WooCommerce ya expone. Dentro, el marcado sí es Twig.
 *
 * Los tres filtros usan parámetros que resuelve el núcleo de WooCommerce
 * (WC_Query): el enlace de categoría es el archivo de siempre, `filter_marca`
 * lo aplica get_layered_nav_chosen_attributes() y `min_price`/`max_price`,
 * price_filter_post_clauses(). No hace falta el widget de navegación por capas.
 */
function il_lateral_tienda(): array {
    $actual = is_product_taxonomy() ? (int) get_queried_object_id() : 0;

    // El término activo y su madre marcan la rama abierta: dentro de una
    // subcategoría se sigue viendo dónde está.
    $madre_activa = $actual ? (int) ( get_term( $actual )->parent ?? 0 ) : 0;

    $raices = get_terms( [
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'menu_order',
    ] );

    $categorias = [];

    foreach ( is_wp_error( $raices ) ? [] : $raices as $raiz ) {
        $hijas = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => $raiz->term_id,
            'hide_empty' => true,
            'orderby'    => 'menu_order',
        ] );

        $categorias[] = [
            'nombre' => $raiz->name,
            'enlace' => get_term_link( $raiz ),
            'total'  => $raiz->count,
            'activa' => $actual === $raiz->term_id,
            // Solo se despliegan las hijas de la rama en la que se está: el
            // panel entero abierto son 17 enlaces y deja de leerse.
            'hijas'  => ( $actual === $raiz->term_id || $madre_activa === $raiz->term_id )
                ? array_map( fn( $hija ) => [
                    'nombre' => $hija->name,
                    'enlace' => get_term_link( $hija ),
                    'total'  => $hija->count,
                    'activa' => $actual === $hija->term_id,
                ], is_wp_error( $hijas ) ? [] : $hijas )
                : [],
        ];
    }

    // Lo que ya está filtrado viaja oculto en el formulario de precio para que
    // aplicar un rango no borre la marca, las unidades ni el orden elegidos.
    $ocultos = array_filter( [
        'filter_marca'    => isset( $_GET['filter_marca'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_marca'] ) ) : '',
        'filter_unidades' => isset( $_GET['filter_unidades'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_unidades'] ) ) : '',
        'orderby'         => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '',
        's'               => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
        'post_type'       => isset( $_GET['s'] ) ? 'product' : '',
    ] );

    return [
        'categorias' => $categorias,
        'marcas'     => il_faceta( 'marca', $actual ),
        // Por tamaño de pack, de menor a mayor: aquí el orden natural es el
        // número, no cuántos productos haya de cada uno.
        'unidades'   => il_faceta( 'unidades', $actual, true, 30 ),
        'ocultos'    => $ocultos,
        'precio'     => [
            'min' => isset( $_GET['min_price'] ) ? (int) $_GET['min_price'] : '',
            'max' => isset( $_GET['max_price'] ) ? (int) $_GET['max_price'] : '',
        ] + ( il_rango_de_precios( $actual ) ?? [] ),
    ];
}

function il_abrir_tienda(): void {
    echo '<div class="il-tienda">';

    Timber\Timber::render( 'partes/tienda-lateral.twig', il_lateral_tienda() );

    echo '<div class="il-tienda__lista">';

    Timber\Timber::render( 'partes/filtros-activos.twig', [
        'fichas' => il_filtros_puestos(),
        'limpiar' => il_url_sin_filtros(),
    ] );
}

function il_cerrar_tienda(): void {
    echo '</div></div>';
}

add_action( 'woocommerce_before_shop_loop', 'il_abrir_tienda', 5 );

// Cierra por detrás de la paginación, que WooCommerce engancha en la 10.
add_action( 'woocommerce_after_shop_loop', 'il_cerrar_tienda', 100 );

// Sin resultados, archive-product.php no dispara los ganchos del bucle: salta a
// `woocommerce_no_products_found` y ahí no había columna, así que un filtro que
// no casaba se llevaba por delante la página entera —filtros incluidos— y solo
// dejaba el aviso. Se abre y se cierra igual alrededor del aviso, que WooCommerce
// imprime en la 10, para que el visitante pueda deshacer el filtro donde lo puso.
add_action( 'woocommerce_no_products_found', 'il_abrir_tienda', 5 );
add_action( 'woocommerce_no_products_found', 'il_cerrar_tienda', 100 );

/**
 * Árbol de categorías para el mega menú de la cabecera.
 *
 * Devuelve las categorías raíz con todas sus hijas —no solo la rama abierta,
 * como el lateral de la tienda— más un producto para el hueco destacado del
 * panel. Se resuelve en PHP y no en la plantilla porque son dos consultas
 * anidadas por categoría y en Twig quedan ilegibles.
 *
 * El destacado es el último producto publicado que tenga foto: el panel enseña
 * novedad sin que nadie tenga que mantener un campo más.
 */
function il_menu_tienda(): array {
    $raices = get_terms( [
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'menu_order',
        'number'     => 6,
    ] );

    $categorias = [];

    foreach ( is_wp_error( $raices ) ? [] : $raices as $raiz ) {
        $hijas = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => $raiz->term_id,
            'hide_empty' => true,
            'orderby'    => 'menu_order',
        ] );

        $categorias[] = [
            'nombre' => $raiz->name,
            'enlace' => get_term_link( $raiz ),
            'hijas'  => array_map( fn( $hija ) => [
                'nombre' => $hija->name,
                'enlace' => get_term_link( $hija ),
            ], is_wp_error( $hijas ) ? [] : $hijas ),
        ];
    }

    $novedades = wc_get_products( [
        'limit'      => 5,
        'status'     => 'publish',
        'visibility' => 'catalog',
        'orderby'    => 'date',
        'order'      => 'DESC',
        'return'     => 'ids',
    ] );

    $destacado = null;

    foreach ( $novedades as $id ) {
        $producto = wc_get_product( $id );

        if ( $producto && $producto->get_image_id() ) {
            $destacado = [
                'nombre' => $producto->get_name(),
                'enlace' => $producto->get_permalink(),
                'imagen' => wp_get_attachment_image_url( $producto->get_image_id(), 'il-producto' ),
            ];
            break;
        }
    }

    return [ 'categorias' => $categorias, 'destacado' => $destacado ];
}
