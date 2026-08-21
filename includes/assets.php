<?php
/**
 * Encolado de assets construidos por Vite.
 *
 * Los ficheros de dist/ llevan hash en el nombre: esa es la invalidación de
 * caché del tema, y no depende de Varnish ni de un parámetro ?ver=
 * (design.md — decisión 1).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const IL_ENTRADA = 'src/main.js';

/**
 * Devuelve el manifest de Vite, o un array vacío si no se puede leer.
 */
function il_manifest(): array {
    static $manifest = null;

    if ( null !== $manifest ) {
        return $manifest;
    }

    // Vite 5+ escribe el manifest en .vite/; versiones anteriores, en la raíz de dist/.
    foreach ( [ '/dist/.vite/manifest.json', '/dist/manifest.json' ] as $ruta ) {
        $fichero = get_theme_file_path( $ruta );

        if ( is_readable( $fichero ) ) {
            $decodificado = json_decode( file_get_contents( $fichero ), true );

            return $manifest = is_array( $decodificado ) ? $decodificado : [];
        }
    }

    return $manifest = [];
}

add_action( 'wp_enqueue_scripts', function (): void {
    $manifest = il_manifest();

    // Sin manifest o sin la entrada, no se encola nada y el fallo queda en el
    // log: una página sin estilos es más difícil de diagnosticar que una línea
    // de error (theme-foundation — "el build ausente falla de forma visible").
    if ( ! isset( $manifest[ IL_ENTRADA ]['file'] ) ) {
        error_log( sprintf(
            '[infinitylove] No se encuentra la entrada "%s" en el manifest de Vite. Ejecuta "npm run build" en %s.',
            IL_ENTRADA,
            get_stylesheet_directory()
        ) );

        return;
    }

    $entrada = $manifest[ IL_ENTRADA ];

    foreach ( $entrada['css'] ?? [] as $indice => $css ) {
        wp_enqueue_style(
            'infinitylove' . ( $indice ? '-' . $indice : '' ),
            get_theme_file_uri( '/dist/' . $css ),
            [],
            null // El hash del nombre ya identifica la versión.
        );
    }

    // La salida de Vite es ESM: se encola como módulo, que es lo que WordPress
    // ofrece de serie desde 6.5.
    wp_enqueue_script_module(
        'infinitylove',
        get_theme_file_uri( '/dist/' . $entrada['file'] ),
        [],
        null
    );
} );
