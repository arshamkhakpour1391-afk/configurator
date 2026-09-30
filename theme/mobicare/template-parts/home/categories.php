<?php
/**
 * Featured categories
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

$cats = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'parent'     => 0,
	'number'     => 8,
	'exclude'    => array_filter( array( get_option( 'default_product_cat' ) ) ),
) );

if ( is_wp_error( $cats ) || empty( $cats ) ) {
	return;
}
?>
<section class="mc-section" aria-labelledby="mc-cats-title">
	<div class="mc-container">
		<?php mobicare_section_header( __( 'دسته‌بندی‌ها', 'mobicare' ), wc_get_page_permalink( 'shop' ) ); ?>
		<div class="mc-cats-grid">
			<?php foreach ( $cats as $cat ) :
				$thumb_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
				$link     = get_term_link( $cat );
				?>
				<a href="<?php echo esc_url( $link ); ?>" class="mc-cat-card">
					<div class="mc-cat-card__img">
						<?php if ( $thumb_id ) : ?>
							<?php echo wp_get_attachment_image( (int) $thumb_id, 'mobicare-category', false, array( 'loading' => 'lazy' ) ); ?>
						<?php else : ?>
							<span class="mc-cat-card__placeholder" aria-hidden="true">
								<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
							</span>
						<?php endif; ?>
					</div>
					<div class="mc-cat-card__body">
						<h3 class="mc-cat-card__title"><?php echo esc_html( $cat->name ); ?></h3>
						<span class="mc-cat-card__count">
							<?php
							printf(
								/* translators: %s: product count */
								esc_html( _n( '%s محصول', '%s محصول', $cat->count, 'mobicare' ) ),
								esc_html( function_exists( 'mobicare_persian_digits' ) ? mobicare_persian_digits( $cat->count ) : (string) $cat->count )
							);
							?>
						</span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
