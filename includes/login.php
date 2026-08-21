<?php
/**
 * Pantalla de acceso con la identidad del sitio.
 *
 * wp-login.php no pasa por las plantillas del tema —es una página propia de
 * WordPress—, así que la personalización se hace por sus ganchos: una hoja de
 * estilos propia (`src/login.css`, entrada aparte de Vite) y los filtros del
 * logo y sus textos. No se sustituye el formulario: el marcado de WordPress es
 * el que trae la comprobación de contraseña, el «recordarme» y los avisos de
 * error, y reescribirlo sería rehacer eso sin ganar nada.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'login_enqueue_scripts', function (): void {
    $manifest = il_manifest();
    $entrada  = 'src/login.css';

    // Mismo criterio que en assets.php: sin build no se encola nada y el fallo
    // queda en el log. El login sigue funcionando, con el aspecto de WordPress.
    if ( ! isset( $manifest[ $entrada ]['file'] ) ) {
        error_log( sprintf(
            '[infinitylove] No se encuentra la entrada "%s" en el manifest de Vite. Ejecuta "npm run build" en %s.',
            $entrada,
            get_stylesheet_directory()
        ) );

        return;
    }

    wp_enqueue_style(
        'infinitylove-login',
        get_theme_file_uri( '/dist/' . $manifest[ $entrada ]['file'] ),
        [],
        null // El hash del nombre ya identifica la versión.
    );

    // El logo del personalizador se pinta como fondo del enlace que WordPress
    // ya trae. Va aquí y no en el CSS porque la URL depende de la instalación.
    $logo = (int) get_theme_mod( 'custom_logo' );
    $src  = $logo ? wp_get_attachment_image_url( $logo, 'medium' ) : '';

    if ( $src ) {
        wp_add_inline_style(
            'infinitylove-login',
            sprintf( '#login h1 a { background-image: url(%s); }', esc_url_raw( $src ) )
        );
    }
} );

// Sin logo subido, el enlace enseña el nombre del sitio en serif en vez de
// esconderlo tras la W de WordPress: la clase la recoge src/login.css.
add_filter( 'login_headerurl', fn(): string => home_url( '/' ) );
add_filter( 'login_headertext', fn(): string => get_bloginfo( 'name' ) );

add_filter( 'login_body_class', function ( array $clases ): array {
    if ( ! get_theme_mod( 'custom_logo' ) ) {
        $clases[] = 'il-login-sin-logo';
    }

    return $clases;
} );

/**
 * Titular sobre el formulario.
 *
 * WordPress no lo trae y sin él la pantalla empieza directamente en «Nombre de
 * usuario»: se nombra el sitio y se dice de qué panel se trata, que es lo que
 * distingue esta pantalla de cualquier otra instalación.
 *
 * Va por el filtro `login_message` y no por la acción `login_form`, que es la
 * que parece: esa se dispara **después** de los campos y el titular caía entre
 * la contraseña y el botón. El filtro imprime justo encima del formulario.
 *
 * El mensaje que ya venga —«Revisa tu correo», los avisos de WordPress— se
 * respeta y se pinta debajo.
 */
add_filter( 'login_message', function ( string $mensaje ): string {
    // Solo en el formulario de acceso: en «recuperar contraseña» o «registro»
    // el titular sobra y el texto no encajaría.
    if ( 'login' !== ( $_REQUEST['action'] ?? 'login' ) ) {
        return $mensaje;
    }

    return sprintf(
        '<p class="il-bienvenida">Panel de %s<span>Accede para administrar la tienda</span></p>',
        esc_html( get_bloginfo( 'name' ) )
    ) . $mensaje;
} );

// El <title> de serie termina en «‹ InfinityLove — WordPress»: delata el gestor
// en la pestaña del navegador y en el historial, que es justo lo que esta
// pantalla quiere dejar de contar.
add_filter( 'login_title', fn(): string => sprintf( 'Acceso · %s', get_bloginfo( 'name' ) ) );

// El sitio es de un solo idioma: el selector que WordPress pinta bajo el
// formulario solo añade ruido a una pantalla que quiere ser corta.
add_filter( 'login_display_language_dropdown', '__return_false' );
