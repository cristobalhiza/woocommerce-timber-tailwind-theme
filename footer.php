<?php
/**
 * Contraparte de header.php para get_footer('shop').
 */

use Timber\Timber;

Timber::render( 'footer.twig', Timber::context() );
