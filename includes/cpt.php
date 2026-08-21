<?php
/**
 * Tipos de contenido propios.
 *
 * El contenido que se repite como lista se modela como entidad, no como campo
 * repetidor: un CPT nativo regala listado, orden y papelera, que es justo lo
 * caro de construir a mano (design.md — decisión 9).
 *
 * Se registran desde el tema a sabiendas: si el tema se desactiva, el
 * contenido sigue en la base de datos pero deja de verse en el administrador.
 * Es un tema a medida para un solo sitio; si eso cambia, mover este
 * register_post_type a un mu-plugin es media hora.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function (): void {
    register_post_type( 'slide', [
        'labels'       => [
            'name'          => 'Slides',
            'singular_name' => 'Slide',
            'add_new_item'  => 'Añadir slide',
            'edit_item'     => 'Editar slide',
            'not_found'     => 'Todavía no hay slides',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-images-alt2',
        'menu_position'=> 21,
        'has_archive'  => false,
        'rewrite'      => false,
        // 'page-attributes' da el campo de orden nativo: el cliente ordena el
        // hero con el mismo control que ya conoce de las páginas.
        'supports'     => [ 'title', 'thumbnail', 'page-attributes' ],
    ] );
} );

/**
 * En el listado de slides, ordenar por el campo de orden y no por fecha, que
 * es lo que el cliente espera al haberlo rellenado.
 */
add_action( 'pre_get_posts', function ( WP_Query $query ): void {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( 'slide' !== $query->get( 'post_type' ) ) {
        return;
    }

    if ( ! $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'menu_order' );
        $query->set( 'order', 'ASC' );
    }
} );
