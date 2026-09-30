<?php
/**
 * Page template
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="mc-main" role="main">
	<div class="mc-container mc-page">
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'mc-page-content' ); ?>>
				<header class="mc-page-header">
					<?php if ( function_exists( 'woocommerce_breadcrumb' ) && ! is_front_page() ) : ?>
						<?php woocommerce_breadcrumb(); ?>
					<?php endif; ?>
					<h1 class="mc-page-title"><?php the_title(); ?></h1>
				</header>
				<div class="mc-entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</main>

<?php
get_footer();
