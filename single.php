<?php

use Timber\Timber;

$contexto         = Timber::context();
$contexto['post'] = Timber::get_post();

Timber::render( 'single.twig', $contexto );
