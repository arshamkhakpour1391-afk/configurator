<?php
/**
 * Product carousel/grid section
 *
 * @package MobiCare
 * @var array $args type, title
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

$type  = isset( $args['type'] ) ? $args['type'] : 'new';
$title = isset( $args['title'] ) ? $args['title'] : __( 'محصولات', 'mobicare' );

$query_args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => 8,
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
);

$more_link = wc_get_page_permalink( 'shop' );

switch ( $type ) {
	case 'featured':
		$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'featured',
			),
		);
		break;
	case 'sale':
		$query_args['post__in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
		$more_link = add_query_arg( 'on_sale', '1', $more_link );
		break;
	case 'bestseller':
		$query_args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query_args['orderby']  = 'meta_value_num';
		$query_args['order']    = 'DESC';
		break;
	case 'new':
	default:
		$query_args['orderby'] = 'date';
		$query_args['order']   = 'DESC';
		break;
}

$q = new WP_Query( $query_args );

// Hide section entirely if no products (store starts empty by design).
if ( ! $q->have_posts() ) {
	return;
}
?>
<section class="mc-section mc-products-section" aria-label="<?php echo esc_attr( $title ); ?>">
	<div class="mc-container">
		<?php mobicare_section_header( $title, $more_link ); ?>
		<div class="mc-products-grid" data-columns="4">
			<?php
			while ( $q->have_posts() ) :
				$q->the_post();
				wc_get_template_part( 'content', 'product' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
