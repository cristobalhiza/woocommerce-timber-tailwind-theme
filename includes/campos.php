<?php
/**
 * Campos editables por el cliente desde el editor.
 *
 * La definición vive aquí, en el código, no en la base de datos: viaja por FTP
 * con el resto del tema y no deja estado que sincronizar entre entornos
 * (design.md — decisión 8). Añadir un campo es una línea en este array.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Definición de campos por tipo de contenido.
 *
 * Tipos admitidos: 'text', 'textarea', 'image'. La etiqueta describe dónde
 * aparece el contenido, porque en el editor de bloques el metabox va al panel
 * inferior y el editor necesita saber qué está tocando sin verlo.
 */
const IL_CAMPOS = [
    'page'  => [
        'hero_titulo'            => [ 'label' => 'Titular principal', 'type' => 'text' ],
        'hero_bajada'            => [ 'label' => 'Texto bajo el titular', 'type' => 'textarea' ],
        'hero_imagen'            => [ 'label' => 'Imagen de cabecera', 'type' => 'image' ],
        'titulo_categorias'      => [ 'label' => 'Portada: título de la sección de categorías', 'type' => 'text' ],
        'titulo_mas_vendidos'    => [ 'label' => 'Portada: título de la sección de más vendidos', 'type' => 'text' ],
        'titulo_recomendados'    => [ 'label' => 'Portada: título de la sección de recomendados', 'type' => 'text' ],
        'titulo_ofertas'         => [ 'label' => 'Portada: título de la sección de ofertas', 'type' => 'text' ],
        'titulo_noticias'        => [ 'label' => 'Portada: título de la sección de noticias', 'type' => 'text' ],
        'titulo_mejor_valorados' => [ 'label' => 'Portada: título de la sección de mejor valorados', 'type' => 'text' ],
        'titulo_empresa'         => [ 'label' => 'Portada: título de la sección de datos de empresa', 'type' => 'text' ],
    ],
    'slide' => [
        'slide_bajada' => [ 'label' => 'Texto bajo el título', 'type' => 'textarea' ],
        'slide_enlace' => [ 'label' => 'Destino del enlace (dejar vacío para que no enlace)', 'type' => 'text' ],
    ],
    // Una noticia puede ser propia o una publicación de un medio. En el segundo
    // caso el sitio no reproduce el artículo —no es suyo—: guarda un resumen y
    // manda al original, y para eso hacen falta el enlace y el nombre del medio.
    'post'  => [
        'noticia_enlace' => [ 'label' => 'Publicación original: enlace (solo si la noticia es de un medio externo)', 'type' => 'url' ],
        'noticia_fuente' => [ 'label' => 'Publicación original: nombre del medio (por ejemplo, CNN Chile)', 'type' => 'text' ],
    ],
];

/**
 * Sanea un valor según el tipo declarado del campo.
 */
function il_sanear_campo( string $tipo, mixed $valor ): string|int {
    return match ( $tipo ) {
        'image'    => absint( $valor ),
        'checkbox' => $valor ? 1 : 0,
        'url'      => esc_url_raw( (string) wp_unslash( $valor ) ),
        'textarea' => il_texto_plano( sanitize_textarea_field( (string) wp_unslash( $valor ) ) ),
        default    => il_texto_plano( sanitize_text_field( (string) wp_unslash( $valor ) ) ),
    };
}

/**
 * Deshace el escapado que sanitize_text_field() aplica a los `<` sueltos.
 *
 * Sin esto, un texto tan normal como «compras < 30.000» se almacena como
 * «compras &lt; 30.000» y Twig, que escapa en el render (decisión 3), lo
 * vuelve a escapar: el cliente acaba viendo «&lt;» en la página.
 *
 * Se guarda texto plano y se escapa una sola vez, al imprimir. El saneado ya
 * ha retirado cualquier etiqueta antes de llegar aquí.
 */
function il_texto_plano( string $valor ): string {
    return wp_specialchars_decode( $valor, ENT_QUOTES );
}

add_action( 'init', function (): void {
    foreach ( IL_CAMPOS as $tipo_contenido => $campos ) {
        foreach ( $campos as $clave => $campo ) {
            $es_imagen = 'image' === $campo['type'];

            register_post_meta( $tipo_contenido, $clave, [
                'single'            => true,
                'type'              => $es_imagen ? 'integer' : 'string',
                'default'           => $es_imagen ? 0 : '',
                'show_in_rest'      => true,
                'sanitize_callback' => static fn( $valor ) => il_sanear_campo( $campo['type'], $valor ),
                'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
            ] );
        }
    }
} );

/**
 * Metabox: un solo panel por tipo de contenido con todos sus campos.
 */
add_action( 'add_meta_boxes', function ( string $tipo_contenido ): void {
    if ( ! isset( IL_CAMPOS[ $tipo_contenido ] ) ) {
        return;
    }

    add_meta_box(
        'il-campos',
        'Contenido de la página',
        'il_pintar_metabox',
        $tipo_contenido
    );
} );

function il_pintar_metabox( WP_Post $post ): void {
    $campos = IL_CAMPOS[ $post->post_type ] ?? [];

    wp_nonce_field( 'il_campos', 'il_campos_nonce' );

    foreach ( $campos as $clave => $campo ) {
        $valor = get_post_meta( $post->ID, $clave, true );

        printf(
            '<p><label for="%1$s"><strong>%2$s</strong></label><br>',
            esc_attr( $clave ),
            esc_html( $campo['label'] )
        );

        switch ( $campo['type'] ) {
            case 'textarea':
                printf(
                    '<textarea id="%1$s" name="%1$s" rows="3" class="widefat">%2$s</textarea>',
                    esc_attr( $clave ),
                    esc_textarea( (string) $valor )
                );
                break;

            case 'image':
                $url = $valor ? wp_get_attachment_image_url( (int) $valor, 'medium' ) : '';

                printf(
                    '<span class="il-imagen">
                        <img src="%2$s" alt="" style="max-width:160px;height:auto;display:%3$s">
                        <br>
                        <button type="button" class="button il-imagen-elegir">Elegir imagen</button>
                        <button type="button" class="button il-imagen-quitar" style="display:%3$s">Quitar</button>
                        <input type="hidden" name="%1$s" value="%4$s">
                    </span>',
                    esc_attr( $clave ),
                    esc_url( $url ?: '' ),
                    $url ? 'block' : 'none',
                    esc_attr( (string) $valor )
                );
                break;

            default:
                printf(
                    '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="widefat">',
                    esc_attr( $clave ),
                    esc_attr( (string) $valor )
                );
        }

        echo '</p>';
    }
}

/**
 * Guardado.
 *
 * Nonce, autoguardado y capacidad son obligatorios: los campos propios asumen
 * el saneado que un plugin daría hecho, y es la parte donde no se simplifica.
 */
add_action( 'save_post', function ( int $post_id, WP_Post $post ): void {
    $campos = IL_CAMPOS[ $post->post_type ] ?? [];

    if ( ! $campos ) {
        return;
    }

    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    $nonce = isset( $_POST['il_campos_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['il_campos_nonce'] ) )
        : '';

    if ( ! wp_verify_nonce( $nonce, 'il_campos' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    foreach ( $campos as $clave => $campo ) {
        $valor = il_sanear_campo( $campo['type'], $_POST[ $clave ] ?? '' );

        // Un campo vacío borra el meta en lugar de guardar cadena vacía: así
        // el |default() de la plantilla se aplica de verdad.
        if ( '' === $valor || 0 === $valor ) {
            delete_post_meta( $post_id, $clave );
            continue;
        }

        update_post_meta( $post_id, $clave, $valor );
    }
}, 10, 2 );

/**
 * Selector de imagen sobre el modal que WordPress ya trae. Sin bundle propio
 * para el administrador: son quince líneas colgadas de un script que ya está
 * encolado.
 */
add_action( 'admin_enqueue_scripts', function ( string $hook ): void {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        return;
    }

    if ( ! isset( IL_CAMPOS[ get_post_type() ] ) ) {
        return;
    }

    wp_enqueue_media();
    wp_add_inline_script( 'media-editor', <<<'JS'
jQuery(document).on('click', '.il-imagen-elegir', function () {
    const caja = jQuery(this).closest('.il-imagen');
    const marco = wp.media({ title: 'Elegir imagen', multiple: false });

    marco.on('select', function () {
        const imagen = marco.state().get('selection').first().toJSON();
        const url = (imagen.sizes && imagen.sizes.medium ? imagen.sizes.medium.url : imagen.url);

        caja.find('input').val(imagen.id);
        caja.find('img').attr('src', url).show();
        caja.find('.il-imagen-quitar').show();
    });

    marco.open();
});

jQuery(document).on('click', '.il-imagen-quitar', function () {
    const caja = jQuery(this).closest('.il-imagen');

    caja.find('input').val('');
    caja.find('img').hide();
    jQuery(this).hide();
});
JS );
} );
