<?php
/**
 * Search results
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

get_header();

$is_product_search = isset( $_GET['post_type'] ) && 'product' === $_GET['post_type']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<main id="main" class="mc-main" role="main">
	<div class="mc-container">
		<header class="mc-page-header">
			<h1 class="mc-page-title">
				<?php
				printf(
					/* translators: %s: search query */
					esc_html__( 'نتایج جستجو برای: %s', 'mobicare' ),
					'<span>' . esc_html( get_search_query() ) . '</span>'
				);
				?>
			</h1>
		</header>

		<?php if ( $is_product_search && class_exists( 'WooCommerce' ) ) : ?>
			<?php
			// Redirect product searches to shop with s param handled by WC.
			?>
			<div class="mc-shop-layout">
				<?php if ( have_posts() ) : ?>
					<div class="mc-products-grid" data-columns="4">
						<?php
						while ( have_posts() ) :
							the_post();
							if ( 'product' === get_post_type() ) {
								wc_get_template_part( 'content', 'product' );
							}
						endwhile;
						?>
					</div>
					<?php the_posts_pagination( array(
						'prev_text' => '‹',
						'next_text' => '›',
					) ); ?>
				<?php else : ?>
					<div class="mc-empty">
						<div class="mc-empty__icon" aria-hidden="true">🔍</div>
						<h2 class="mc-empty__title"><?php esc_html_e( 'محصولی یافت نشد', 'mobicare' ); ?></h2>
						<p class="mc-empty__text"><?php esc_html_e( 'عبارت دیگری را امتحان کنید یا از فیلتر مدل گوشی استفاده کنید.', 'mobicare' ); ?></p>
						<button type="button" class="mc-btn mc-btn--primary" data-mc-open-model-finder>
							<?php esc_html_e( 'یافتن مدل گوشی', 'mobicare' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php if ( have_posts() ) : ?>
				<div class="mc-posts">
					<?php while ( have_posts() ) : the_post(); ?>
						<article <?php post_class( 'mc-post-card' ); ?>>
							<div class="mc-post-card__body">
								<h2 class="mc-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
								<div class="mc-post-card__excerpt"><?php the_excerpt(); ?></div>
							</div>
						</article>
					<?php endwhile; ?>
				</div>
			<?php else : ?>
				<div class="mc-empty">
					<h2 class="mc-empty__title"><?php esc_html_e( 'نتیجه‌ای یافت نشد', 'mobicare' ); ?></h2>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
