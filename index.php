<?php
use Timber\Timber;

$context = Timber::context();
Timber::render('index.twig', $context);
