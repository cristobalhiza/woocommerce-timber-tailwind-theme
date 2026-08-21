<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

Timber\Timber::$dirname = [ 'templates', 'views' ];

/**
 * Autoescape declarado explícitamente, sin confiar en el valor por defecto de
 * la versión de Timber instalada (design.md — decisión 3). Es la primera
 * barrera frente al contenido que el cliente guarda desde el editor; el
 * sanitizado al guardar es la segunda, no la única.
 *
 * El contenido de Gutenberg lleva HTML legítimo y es la excepción: se marca
 * con |raw en la plantilla que lo imprime.
 */
add_filter( 'timber/twig/environment/options', function ( array $opciones ): array {
    $opciones['autoescape'] = 'html';

    return $opciones;
} );

/**
 * Filtro `sin_entidades` para los títulos.
 *
 * `post.title` pasa por los filtros de `the_title`, y wptexturize convierte el
 * guion de «100 Condones - Preservativos» en una raya que devuelve codificada
 * (`&#8211;`). Con el autoescape de arriba, ese `&` se vuelve a escapar y el
 * lector acaba viendo el código en pantalla.
 *
 * Decodificar antes de imprimir lo arregla sin renunciar al autoescape: si el
 * título trajera HTML de verdad, Twig lo sigue escapando después. `|raw`, que
 * es la solución habitual, lo dejaría pasar.
 */
add_filter( 'timber/twig/filters', function ( array $filtros ): array {
    $filtros['sin_entidades'] = [
        'callable' => fn( $texto ) => html_entity_decode( (string) $texto, ENT_QUOTES, 'UTF-8' ),
    ];

    return $filtros;
} );
