<?php
/**
 * Single product
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>

<main id="main" class="mc-main mc-wc mc-single-product" role="main">
	<div class="mc-container">
		<?php mobicare_breadcrumbs(); ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<?php wc_get_template_part( 'content', 'single-product' ); ?>
		<?php endwhile; ?>
	</div>
</main>

<?php
get_footer( 'shop' );
