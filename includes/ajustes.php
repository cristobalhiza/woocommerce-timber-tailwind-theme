<?php
/**
 * Ajustes globales del sitio.
 *
 * Todo va en una única opción con un array asociativo: una fila en wp_options,
 * un register_setting y un solo sanitize_callback que recorre la misma
 * definición que pinta el formulario (design.md — decisión 10).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const IL_OPCION = 'infinitylove_ajustes';

const IL_AJUSTES = [
    'cinta' => [
        'titulo' => 'Cinta informativa superior',
        'campos' => [
            'cinta_activa'  => [ 'label' => 'Mostrar la cinta', 'type' => 'checkbox' ],
            'cinta_texto'   => [ 'label' => 'Texto', 'type' => 'text' ],
            // Dos mensajes como mucho: con el segundo relleno se turnan solos
            // en la cinta. Vacío, la cinta enseña solo el primero.
            'cinta_texto_2' => [ 'label' => 'Segundo mensaje (opcional, se alterna con el primero)', 'type' => 'text' ],
            'cinta_enlace'  => [ 'label' => 'Enlace (opcional)', 'type' => 'url' ],
        ],
    ],
    'empresa' => [
        'titulo' => 'Datos de empresa',
        'campos' => [
            'empresa_descripcion' => [ 'label' => 'Quiénes somos', 'type' => 'textarea' ],
            'empresa_direccion'   => [ 'label' => 'Dirección', 'type' => 'text' ],
            'empresa_telefono'    => [ 'label' => 'Teléfono', 'type' => 'text' ],
            'empresa_email'       => [ 'label' => 'Correo de contacto', 'type' => 'text' ],
            'empresa_horario'     => [ 'label' => 'Horario', 'type' => 'text' ],
            'empresa_mapa'        => [ 'label' => 'Enlace al mapa', 'type' => 'url' ],
        ],
    ],
    'redes' => [
        'titulo' => 'Redes y contacto directo',
        'campos' => [
            'whatsapp'  => [ 'label' => 'WhatsApp (solo números, con prefijo)', 'type' => 'text' ],
            'instagram' => [ 'label' => 'Instagram', 'type' => 'url' ],
            'facebook'  => [ 'label' => 'Facebook', 'type' => 'url' ],
        ],
    ],
];

/**
 * Devuelve un ajuste global, o cadena vacía si no está definido.
 */
function il_ajuste( string $clave ): string|int {
    $ajustes = get_option( IL_OPCION, [] );

    return $ajustes[ $clave ] ?? '';
}

/**
 * Recorre la definición y sanea cada valor por su tipo. Las claves que no
 * estén declaradas se descartan: lo que llega del formulario no decide qué se
 * guarda, lo decide el array de arriba.
 */
function il_sanear_ajustes( mixed $entrada ): array {
    $entrada = is_array( $entrada ) ? $entrada : [];
    $limpio  = [];

    foreach ( IL_AJUSTES as $seccion ) {
        foreach ( $seccion['campos'] as $clave => $campo ) {
            $valor = il_sanear_campo( $campo['type'], $entrada[ $clave ] ?? '' );

            if ( '' === $valor || 0 === $valor ) {
                continue;
            }

            $limpio[ $clave ] = $valor;
        }
    }

    return $limpio;
}

add_action( 'admin_init', function (): void {
    register_setting( 'il_ajustes', IL_OPCION, [
        'type'              => 'array',
        'sanitize_callback' => 'il_sanear_ajustes',
        'default'           => [],
    ] );

    foreach ( IL_AJUSTES as $id_seccion => $seccion ) {
        add_settings_section( "il_{$id_seccion}", $seccion['titulo'], '__return_false', 'il_ajustes' );

        foreach ( $seccion['campos'] as $clave => $campo ) {
            add_settings_field(
                $clave,
                $campo['label'],
                'il_pintar_ajuste',
                'il_ajustes',
                "il_{$id_seccion}",
                [ 'clave' => $clave, 'campo' => $campo, 'label_for' => $clave ]
            );
        }
    }
} );

function il_pintar_ajuste( array $args ): void {
    $clave  = $args['clave'];
    $campo  = $args['campo'];
    $valor  = il_ajuste( $clave );
    $nombre = sprintf( '%s[%s]', IL_OPCION, $clave );

    switch ( $campo['type'] ) {
        case 'checkbox':
            printf(
                '<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s>',
                esc_attr( $clave ),
                esc_attr( $nombre ),
                checked( $valor, 1, false )
            );
            break;

        case 'textarea':
            printf(
                '<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>',
                esc_attr( $clave ),
                esc_attr( $nombre ),
                esc_textarea( (string) $valor )
            );
            break;

        default:
            printf(
                '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text">',
                esc_attr( $clave ),
                esc_attr( $nombre ),
                esc_attr( (string) $valor )
            );
    }
}

add_action( 'admin_menu', function (): void {
    add_options_page(
        'InfinityLove',
        'InfinityLove',
        'manage_options',
        'il-ajustes',
        function (): void {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            echo '<div class="wrap"><h1>InfinityLove</h1><form action="options.php" method="post">';
            settings_fields( 'il_ajustes' );
            do_settings_sections( 'il_ajustes' );
            submit_button();
            echo '</form></div>';
        }
    );
} );

/**
 * Los ajustes globales se exponen a Twig una sola vez, para que las plantillas
 * no tengan que llamar a una función de PHP por dato.
 */
add_filter( 'timber/context', function ( array $contexto ): array {
    $contexto['ajustes'] = get_option( IL_OPCION, [] );

    return $contexto;
} );
