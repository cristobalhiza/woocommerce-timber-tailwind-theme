<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'after_setup_theme', function (): void {
    add_theme_support( 'post-thumbnails' );

    // El <title> lo imprime wp_head(); la plantilla no lleva etiqueta propia.
    add_theme_support( 'title-tag' );

    // El logo va por el mecanismo nativo —se sube desde el personalizador— y
    // la cabecera cae al nombre del sitio si no hay ninguno. Flexible en las
    // dos dimensiones: el logo de marca es circular, no una franja.
    add_theme_support( 'custom-logo', [
        'height'      => 112,
        'width'       => 112,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    // La galería de producto es propia (Fase 2), así que no se declaran
    // 'wc-product-gallery-slider' ni 'wc-product-gallery-zoom': declararlos
    // encolaría los scripts de galería de WooCommerce sobre un marcado que no
    // es el suyo.
    add_theme_support( 'woocommerce' );

    // Imágenes de producto: cuadradas y recortadas al generarlas.
    //
    // Las tarjetas y la ficha las pintan en contenedores `aspect-square` con
    // `object-cover`, y las fotos que llegan desde MercadoLibre vienen en
    // proporciones dispares (500x500, 213x500, 500x377...). Sin recorte propio
    // el navegador descarga píxeles que después tapa.
    //
    // Medidas tomadas del ancho real de cada hueco con el contenedor a 1536px:
    //   tarjeta de tienda   358px (4 columnas)   ─┐
    //   tarjeta de portada  364px (4 columnas)    ├─ il-producto        600
    //   ítem de carrusel    256px (w-64)         ─┘
    //   imagen de ficha     736px (md:grid-cols-2) ─ il-producto-grande 1000
    //
    // Van al ~1,7x del hueco, para pantallas densas. No más: el origen de
    // MercadoLibre llega a 1024–1200px y WordPress no amplía, así que pedir
    // 1400 solo dejaría copias del original con otro nombre.
    add_image_size( 'il-producto', 600, 600, true );
    add_image_size( 'il-producto-grande', 1000, 1000, true );

    register_nav_menus( [
        'primary' => 'Menú Principal',
    ] );
} );

/**
 * Retira los tamaños que ninguna plantilla pide.
 *
 * El tema usa `thumbnail` (miniaturas de galería, que WordPress ya recorta a
 * 150), `medium` (logo y noticias en 16:9), `large` (hero y slides) y los dos
 * `il-producto`. `medium_large`, `1536x1536` y `2048x2048` no aparecen en
 * ninguna plantilla, y las imágenes se pintan con `src` a secas —sin
 * `srcset`—, así que tampoco los usa el marcado responsivo.
 *
 * Son tres archivos por imagen: sobre las ~490 fotos del catálogo, unos 1.500
 * archivos que nadie sirve. Conviene desactivarlos antes de la primera
 * importación, porque hacerlo después obliga a regenerar las miniaturas de
 * todo el catálogo y en el hosting no hay SSH para lanzarlo cómodo.
 */
add_filter( 'intermediate_image_sizes_advanced', function ( array $tamanos ): array {
    unset( $tamanos['medium_large'], $tamanos['1536x1536'], $tamanos['2048x2048'] );

    return $tamanos;
} );
