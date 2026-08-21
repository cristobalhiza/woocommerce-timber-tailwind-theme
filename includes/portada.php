<?php
/**
 * Portada.
 *
 * Ninguna sección de producto lleva campos propios: cada una cae en un
 * mecanismo nativo distinto de WooCommerce, y es eso lo que impide que se
 * solapen (design.md — decisión 11).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mínimo de elementos por sección. Por debajo, la sección no se pinta.
 *
 * Son la fila completa de cada carrusel en escritorio: con menos, la sección
 * se ve rota en lugar de escasa. Pendiente de confirmar con el cliente.
 */
const IL_MINIMOS = [
    'productos'  => 4,
    'categorias' => 3,
    // Las noticias van a dos por fila cuando hay dos, así que con dos la
    // sección se ve completa igual: exigir tres escondía entera una sección
    // que ya tenía contenido publicado.
    'noticias'   => 2,
    'hero'       => 1,
];

/** Títulos por defecto, para que una sección sin título no salga descabezada. */
const IL_TITULOS = [
    'titulo_categorias'      => 'Categorías',
    'titulo_mas_vendidos'    => 'Lo más vendido',
    'titulo_recomendados'    => 'Recomendados',
    'titulo_ofertas'         => 'Ofertas',
    'titulo_noticias'        => 'Novedades',
    'titulo_mejor_valorados' => 'Mejor valorados',
    'titulo_empresa'         => 'Quiénes somos',
];

/**
 * Consulta de productos para la portada.
 *
 * Devuelve posts de Timber. Se pide 'return' => 'ids' y se dejan pasar por
 * Timber para que la integración les asigne la clase de producto.
 */
function il_productos( array $args, ?callable $filtro = null ): array {
    $consulta = array_merge( [
        'status'     => 'publish',
        'visibility' => 'catalog',
        'limit'      => 12,
        'return'     => 'ids',
    ], $args );

    // wc_get_products no aplica por su cuenta el ajuste «ocultar productos sin
    // existencias» del catálogo, así que se traslada aquí para que la portada
    // no anuncie lo que la tienda esconde.
    if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
        $consulta['stock_status'] = 'instock';
    }

    $ids = wc_get_products( $consulta );

    if ( $filtro ) {
        $ids = array_values( array_filter( $ids, $filtro ) );
    }

    return $ids ? Timber\Timber::get_posts( $ids )->to_array() : [];
}

/**
 * Contexto de la portada.
 */
function il_contexto_portada( array $contexto ): array {
    $post = $contexto['post'] ?? null;

    // Títulos de sección: campo de la página de inicio, o el de por defecto.
    $titulos = [];
    foreach ( IL_TITULOS as $clave => $defecto ) {
        $valor            = $post ? (string) $post->meta( $clave ) : '';
        $titulos[ $clave ] = '' !== $valor ? $valor : $defecto;
    }
    $contexto['titulos'] = $titulos;

    $contexto['slides'] = Timber\Timber::get_posts( [
        'post_type'      => 'slide',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ] )->to_array();

    $contexto['categorias'] = get_terms( [
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'menu_order',
    ] );

    if ( is_wp_error( $contexto['categorias'] ) ) {
        $contexto['categorias'] = [];
    }

    $contexto['mas_vendidos'] = il_productos( [ 'orderby' => 'popularity' ] );
    $contexto['recomendados'] = il_productos( [ 'featured' => true ] );

    // Se ordena por el meta de la media y no por `orderby => 'rating'`: ese
    // atajo solo funciona en la consulta principal del catálogo, donde
    // WooCommerce engancha su propio ordenamiento; en wc_get_products() se
    // ignora en silencio y devuelve el catálogo por fecha —doce productos sin
    // una sola reseña—, así que el filtro los descartaba todos y la sección no
    // se pintaba nunca.
    //
    // El filtro se mantiene por encima: es lo que impide que entre un producto
    // sin valorar si la consulta devuelve de menos.
    $contexto['mejor_valorados'] = il_productos(
        [
            'orderby'  => 'meta_value_num',
            'meta_key' => '_wc_average_rating',
            'order'    => 'DESC',
        ],
        static fn( int $id ): bool => (float) get_post_meta( $id, '_wc_average_rating', true ) > 0
    );

    // Ofertas: los IDs en rebaja vigente que mantiene WooCommerce. Sin ninguno,
    // se deja vacío en lugar de consultar sin filtro, que devolvería el catálogo
    // entero.
    $en_oferta               = wc_get_product_ids_on_sale();
    $contexto['ofertas']     = $en_oferta ? il_productos( [ 'include' => $en_oferta ] ) : [];

    $contexto['noticias'] = Timber\Timber::get_posts( [
        'post_type'      => 'post',
        'posts_per_page' => 3,
    ] )->to_array();

    $contexto['minimos'] = IL_MINIMOS;

    return $contexto;
}

/**
 * Miniatura de una categoría, o null si no tiene: la plantilla decide,
 * pero nunca pinta una imagen rota.
 */
function il_imagen_categoria( WP_Term $categoria ): ?Timber\Image {
    $id = (int) get_term_meta( $categoria->term_id, 'thumbnail_id', true );

    return $id ? Timber\Timber::get_image( $id ) : null;
}
