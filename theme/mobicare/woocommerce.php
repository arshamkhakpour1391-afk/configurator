<?php
/**
 * WooCommerce fallback template
 *
 * Prefer more specific templates (archive-product, single-product).
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>

<main id="main" class="mc-main mc-wc" role="main">
	<div class="mc-container" style="padding-top:24px;padding-bottom:48px">
		<?php woocommerce_content(); ?>
	</div>
</main>

<?php
get_footer( 'shop' );
