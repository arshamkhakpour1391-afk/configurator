<?php
/**
 * Product loop card
 *
 * @package MobiCare
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
?>
<li <?php wc_product_class( 'mc-product-card', $product ); ?>>
	<div class="mc-product-card__inner">
		<a href="<?php echo esc_url( get_permalink() ); ?>" class="mc-product-card__media">
			<?php mobicare_product_badges( $product ); ?>
			<?php
			$image_id = $product->get_image_id();
			mobicare_product_image( $image_id, 'woocommerce_thumbnail', array(
				'class'    => 'mc-product-card__img',
				'loading'  => 'lazy',
				'alt'      => $product->get_name(),
			) );
			?>
		</a>

		<div class="mc-product-card__body">
			<?php
			$cats = wc_get_product_category_list( $product->get_id(), '، ', '<span class="mc-product-card__cat">', '</span>' );
			echo $cats; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<h2 class="mc-product-card__title">
				<a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
			</h2>

			<?php if ( $product->get_average_rating() > 0 ) : ?>
				<?php mobicare_star_rating( $product->get_average_rating(), $product->get_review_count() ); ?>
			<?php endif; ?>

			<div class="mc-product-card__price">
				<?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="mc-product-card__footer">
				<?php woocommerce_template_loop_add_to_cart(); ?>
				<?php mobicare_loop_action_buttons(); ?>
			</div>
		</div>
	</div>
</li>
