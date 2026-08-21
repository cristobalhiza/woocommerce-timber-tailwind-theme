<?php

use Timber\Timber;

$contexto         = Timber::context();
$contexto['post'] = Timber::get_post();

// Carrito y pago son bloques de WooCommerce y piden todo el ancho: encajarlos
// en la columna de lectura de una página normal les rompe la retícula.
$contexto['ancho_completo'] = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() );

Timber::render( 'page.twig', $contexto );
