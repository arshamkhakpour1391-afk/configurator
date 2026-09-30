<?php
/**
 * Product archive
 *
 * @package MobiCare
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>

<main id="main" class="mc-main mc-wc mc-shop-archive" role="main">
	<div class="mc-container">
		<header class="mc-shop-header">
			<?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
				<h1 class="mc-page-title woocommerce-products-header__title page-title"><?php woocommerce_page_title(); ?></h1>
			<?php endif; ?>
			<?php
			/**
			 * Hook: woocommerce_archive_description.
			 */
			do_action( 'woocommerce_archive_description' );
			?>
			<?php mobicare_breadcrumbs(); ?>
		</header>

		<div class="mc-shop-layout">
			<aside id="mc-shop-filters" class="mc-shop-filters" aria-label="<?php esc_attr_e( 'فیلتر محصولات', 'mobicare' ); ?>">
				<div class="mc-shop-filters__head">
					<strong><?php esc_html_e( 'فیلترها', 'mobicare' ); ?></strong>
					<button type="button" class="mc-shop-filters__close" id="mc-filters-close" aria-label="<?php esc_attr_e( 'بستن فیلترها', 'mobicare' ); ?>">×</button>
				</div>
				<div class="mc-shop-filters__body">
					<?php if ( is_active_sidebar( 'shop-sidebar' ) ) : ?>
						<?php dynamic_sidebar( 'shop-sidebar' ); ?>
					<?php else : ?>
						<?php if ( function_exists( 'mobicare_render_default_filters' ) ) : ?>
							<?php mobicare_render_default_filters(); ?>
						<?php else : ?>
							<p class="mc-muted"><?php esc_html_e( 'از پیشخوان وردپرس، ویجت‌های فیلتر را به «سایدبار فروشگاه» اضافه کنید. یا افزونه MobiCare Core را فعال نگه دارید تا فیلترهای پیش‌فرض نمایش داده شوند.', 'mobicare' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<div class="mc-shop-filters__foot">
					<button type="button" class="mc-btn mc-btn--primary mc-btn--block" id="mc-filters-apply"><?php esc_html_e( 'اعمال فیلتر', 'mobicare' ); ?></button>
				</div>
			</aside>

			<div class="mc-shop-content">
				<?php
				if ( woocommerce_product_loop() ) {
					/**
					 * Hook: woocommerce_before_shop_loop.
					 */
					do_action( 'woocommerce_before_shop_loop' );

					woocommerce_product_loop_start();

					if ( wc_get_loop_prop( 'total' ) ) {
						while ( have_posts() ) {
							the_post();
							/**
							 * Hook: woocommerce_shop_loop.
							 */
							do_action( 'woocommerce_shop_loop' );
							wc_get_template_part( 'content', 'product' );
						}
					}

					woocommerce_product_loop_end();

					/**
					 * Hook: woocommerce_after_shop_loop.
					 */
					do_action( 'woocommerce_after_shop_loop' );
				} else {
					/**
					 * Hook: woocommerce_no_products_found.
					 */
					do_action( 'woocommerce_no_products_found' );
				}
				?>
			</div>
		</div>
	</div>
</main>

<?php
get_footer( 'shop' );
