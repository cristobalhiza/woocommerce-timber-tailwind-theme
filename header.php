<?php
/**
 * WooCommerce llama a get_header('shop') desde sus plantillas. Este fichero
 * existe para que esa llamada renderice la misma cabecera que base.twig.
 */

use Timber\Timber;

Timber::render( 'header.twig', Timber::context() );
