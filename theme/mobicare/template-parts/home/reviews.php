<?php
/**
 * Recent approved product reviews
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

$comments = get_comments( array(
	'status'     => 'approve',
	'post_type'  => 'product',
	'number'     => 6,
	'parent'     => 0,
	'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		array(
			'key'     => 'rating',
			'value'   => 0,
			'compare' => '>',
			'type'    => 'NUMERIC',
		),
	),
) );

if ( empty( $comments ) ) {
	return;
}
?>
<section class="mc-section mc-reviews-section" aria-labelledby="mc-reviews-title">
	<div class="mc-container">
		<?php mobicare_section_header( __( 'نظر مشتریان', 'mobicare' ) ); ?>
		<div class="mc-reviews-grid">
			<?php foreach ( $comments as $c ) :
				$rating = (int) get_comment_meta( $c->comment_ID, 'rating', true );
				$product_title = get_the_title( $c->comment_post_ID );
				?>
				<blockquote class="mc-review-card">
					<?php if ( $rating ) : ?>
						<?php mobicare_star_rating( $rating ); ?>
					<?php endif; ?>
					<p class="mc-review-card__text"><?php echo esc_html( wp_trim_words( $c->comment_content, 40 ) ); ?></p>
					<footer class="mc-review-card__foot">
						<strong class="mc-review-card__author"><?php echo esc_html( $c->comment_author ); ?></strong>
						<?php if ( $product_title ) : ?>
							<span class="mc-review-card__product"><?php echo esc_html( $product_title ); ?></span>
						<?php endif; ?>
					</footer>
				</blockquote>
			<?php endforeach; ?>
		</div>
	</div>
</section>
