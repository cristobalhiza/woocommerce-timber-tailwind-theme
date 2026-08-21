<?php

use Timber\Timber;

$contexto         = Timber::context();
$contexto['post'] = Timber::get_post();
$contexto         = il_contexto_portada( $contexto );

Timber::render( 'portada.twig', $contexto );
