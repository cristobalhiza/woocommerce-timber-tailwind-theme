<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

Timber\Timber::init();

require_once __DIR__ . '/includes/timber.php';
require_once __DIR__ . '/includes/setup.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/login.php';
require_once __DIR__ . '/includes/woocommerce.php';
require_once __DIR__ . '/includes/campos.php';
require_once __DIR__ . '/includes/ajustes.php';
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/suscripcion.php';
require_once __DIR__ . '/includes/portada.php';
