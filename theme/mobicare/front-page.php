<?php
/**
 * Front page template
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="mc-main mc-home" role="main">

	<?php get_template_part( 'template-parts/home/hero' ); ?>

	<?php get_template_part( 'template-parts/home/features' ); ?>

	<?php get_template_part( 'template-parts/home/model-finder' ); ?>

	<?php get_template_part( 'template-parts/home/categories' ); ?>

	<?php get_template_part( 'template-parts/home/banners' ); ?>

	<?php if ( class_exists( 'WooCommerce' ) ) : ?>

		<?php get_template_part( 'template-parts/home/products', null, array( 'type' => 'featured', 'title' => __( 'محصولات ویژه', 'mobicare' ) ) ); ?>

		<?php get_template_part( 'template-parts/home/products', null, array( 'type' => 'new', 'title' => __( 'جدیدترین‌ها', 'mobicare' ) ) ); ?>

		<?php get_template_part( 'template-parts/home/products', null, array( 'type' => 'sale', 'title' => __( 'تخفیف‌دارها', 'mobicare' ) ) ); ?>

		<?php get_template_part( 'template-parts/home/products', null, array( 'type' => 'bestseller', 'title' => __( 'پرفروش‌ها', 'mobicare' ) ) ); ?>

	<?php else : ?>
		<section class="mc-section">
			<div class="mc-container">
				<div class="mc-notice mc-notice--warn">
					<strong><?php esc_html_e( 'ووکامرس فعال نیست', 'mobicare' ); ?></strong>
					<p><?php esc_html_e( 'برای نمایش محصولات، افزونه WooCommerce را نصب و فعال کنید.', 'mobicare' ); ?></p>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/home/reviews' ); ?>

	<?php get_template_part( 'template-parts/home/faq' ); ?>

	<?php get_template_part( 'template-parts/home/cta' ); ?>

</main>

<?php
get_footer();
