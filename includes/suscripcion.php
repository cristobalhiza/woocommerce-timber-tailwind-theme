<?php
/**
 * Suscripción al correo de novedades.
 *
 * Los correos se guardan en el propio WordPress como entradas de un tipo
 * privado: un CPT nativo regala listado, búsqueda, papelera y exportación desde
 * el administrador, que es lo caro de construir a mano (design.md — decisión 9).
 * No se añade un plugin de boletines ni se conecta ningún servicio: cuando el
 * cliente elija uno (Mailchimp, Brevo…), esta lista se exporta y se sube.
 *
 * El formulario envía a admin-post.php, que es el punto de entrada de WordPress
 * para formularios propios: trae la comprobación de nonce y no obliga a montar
 * una ruta ni a interceptar `init`.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function (): void {
    register_post_type( 'suscriptor', [
        'labels'       => [
            'name'          => 'Suscriptores',
            'singular_name' => 'Suscriptor',
            'add_new_item'  => 'Añadir suscriptor',
            'edit_item'     => 'Editar suscriptor',
            'search_items'  => 'Buscar suscriptores',
            'not_found'     => 'Todavía no hay suscriptores',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => false,
        'menu_icon'    => 'dashicons-email-alt',
        'menu_position'=> 22,
        'has_archive'  => false,
        'rewrite'      => false,
        'supports'     => [ 'title' ],
    ] );
} );

// El título es el correo: nombrar la columna evita que el listado diga «Título»
// sobre una lista de direcciones.
add_filter( 'manage_suscriptor_posts_columns', function ( array $columnas ): array {
    $columnas['title'] = 'Correo';

    return $columnas;
} );

/**
 * Alta desde el formulario del pie.
 *
 * Se atiende con y sin sesión iniciada: la mayoría de quienes se suscriben no
 * tienen cuenta, pero el administrador que prueba el formulario sí.
 */
function il_procesar_suscripcion(): void {
    $volver = wp_get_referer() ?: home_url( '/' );

    $terminar = function ( string $estado ) use ( $volver ): void {
        wp_safe_redirect( add_query_arg( 'suscrito', $estado, $volver ) . '#suscripcion' );
        exit;
    };

    if ( ! isset( $_POST['il_suscribir_nonce'] ) || ! wp_verify_nonce( $_POST['il_suscribir_nonce'], 'il_suscribir' ) ) {
        $terminar( 'error' );
    }

    // Trampa para robots: un campo que la persona no ve y no rellena. Si viene
    // con algo, se responde como si todo hubiera ido bien y no se guarda nada.
    if ( ! empty( $_POST['il_web'] ) ) {
        $terminar( 'ok' );
    }

    $correo = sanitize_email( wp_unslash( $_POST['correo'] ?? '' ) );

    if ( ! is_email( $correo ) ) {
        $terminar( 'invalido' );
    }

    // Ya suscrito: se responde igual que un alta nueva. Decir «ya estabas» no
    // aporta y convierte el formulario en una forma de comprobar si una
    // dirección está en la lista.
    $existente = get_posts( [
        'post_type'   => 'suscriptor',
        'post_status' => 'any',
        'numberposts' => 1,
        'fields'      => 'ids',
        'title'       => $correo,
    ] );

    if ( ! $existente ) {
        wp_insert_post( [
            'post_type'   => 'suscriptor',
            'post_status' => 'publish',
            'post_title'  => $correo,
        ] );
    }

    $terminar( 'ok' );
}

add_action( 'admin_post_nopriv_il_suscribir', 'il_procesar_suscripcion' );
add_action( 'admin_post_il_suscribir', 'il_procesar_suscripcion' );

/**
 * Mensaje que corresponde mostrar tras el envío, o null si no se viene de uno.
 *
 * Lo lee la plantilla del pie. Devuelve el texto y si es un fallo, para que la
 * plantilla decida el color sin repetir la lista de estados.
 */
function il_mensaje_suscripcion(): ?array {
    $estado = isset( $_GET['suscrito'] ) ? sanitize_key( $_GET['suscrito'] ) : '';

    return match ( $estado ) {
        'ok'       => [ 'texto' => '¡Listo! Te avisaremos de las novedades.', 'error' => false ],
        'invalido' => [ 'texto' => 'Revisa el correo: esa dirección no parece válida.', 'error' => true ],
        'error'    => [ 'texto' => 'No pudimos procesar la suscripción. Inténtalo de nuevo.', 'error' => true ],
        default    => null,
    };
}
