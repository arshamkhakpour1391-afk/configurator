<?php
/**
 * Shop filters (layered nav without requiring third-party plugins)
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Filters
 */
class MobiCare_Filters {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'woocommerce_product_query', array( __CLASS__, 'apply_query' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
	}

	/**
	 * Public query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'min_price';
		$vars[] = 'max_price';
		$vars[] = 'filter_stock';
		$vars[] = 'filter_sale';
		$vars[] = 'filter_rating';
		$vars[] = 'filter_phone_model';
		$vars[] = 'filter_phone_brand';
		$vars[] = 'filter_brand';
		$vars[] = 'filter_color';
		$vars[] = 'filter_material';
		$vars[] = 'filter_magsafe';
		$vars[] = 'on_sale';
		return $vars;
	}

	/**
	 * Apply filters to main product query.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function apply_query( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}

		$meta_query = (array) $q->get( 'meta_query' );
		$tax_query  = (array) $q->get( 'tax_query' );

		// Price.
		$min = isset( $_GET['min_price'] ) ? floatval( wp_unslash( $_GET['min_price'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$max = isset( $_GET['max_price'] ) ? floatval( wp_unslash( $_GET['max_price'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $min !== '' || $max !== '' ) {
			$price = array( 'key' => '_price', 'type' => 'NUMERIC' );
			if ( $min !== '' && $max !== '' ) {
				$price['value']   = array( $min, $max );
				$price['compare'] = 'BETWEEN';
			} elseif ( $min !== '' ) {
				$price['value']   = $min;
				$price['compare'] = '>=';
			} else {
				$price['value']   = $max;
				$price['compare'] = '<=';
			}
			$meta_query[] = $price;
		}

		// Stock.
		if ( ! empty( $_GET['filter_stock'] ) && 'instock' === $_GET['filter_stock'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$meta_query[] = array(
				'key'     => '_stock_status',
				'value'   => 'instock',
				'compare' => '=',
			);
		}

		// On sale.
		if ( ! empty( $_GET['filter_sale'] ) || ! empty( $_GET['on_sale'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sale_ids = wc_get_product_ids_on_sale();
			$q->set( 'post__in', array_merge( array( 0 ), $sale_ids ) );
		}

		// Rating.
		if ( ! empty( $_GET['filter_rating'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$rating = absint( $_GET['filter_rating'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $rating >= 1 && $rating <= 5 ) {
				$meta_query[] = array(
					'key'     => '_wc_average_rating',
					'value'   => $rating,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}
		}

		// Taxonomies.
		$tax_map = array(
			'filter_phone_model' => 'phone_model',
			'filter_phone_brand' => 'phone_brand',
			'filter_brand'       => 'product_brand',
			'filter_color'       => 'pa_color',
			'filter_material'    => 'pa_material',
			'filter_magsafe'     => 'pa_magsafe',
		);

		foreach ( $tax_map as $get_key => $taxonomy ) {
			if ( empty( $_GET[ $get_key ] ) || ! taxonomy_exists( $taxonomy ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				continue;
			}
			$raw = wp_unslash( $_GET[ $get_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_array( $raw ) ) {
				$slugs = array_filter( array_map( 'sanitize_title', $raw ) );
			} else {
				$slugs = array_filter( array_map( 'sanitize_title', explode( ',', sanitize_text_field( $raw ) ) ) );
			}
			if ( empty( $slugs ) ) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		if ( count( $meta_query ) > 0 ) {
			$q->set( 'meta_query', $meta_query );
		}
		if ( count( $tax_query ) > 0 ) {
			$tax_query['relation'] = 'AND';
			$q->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Render default filter form HTML.
	 */
	public static function render_default_filters() {
		$base_url = ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) )
			? get_permalink( wc_get_page_id( 'shop' ) )
			: home_url( '/' );

		if ( is_product_taxonomy() ) {
			$base_url = get_term_link( get_queried_object() );
			if ( is_wp_error( $base_url ) ) {
				$base_url = wc_get_page_permalink( 'shop' );
			}
		}

		$current = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<form class="mc-filters-form" method="get" action="<?php echo esc_url( $base_url ); ?>">
			<?php
			// Preserve search.
			if ( ! empty( $current['s'] ) ) {
				echo '<input type="hidden" name="s" value="' . esc_attr( sanitize_text_field( wp_unslash( $current['s'] ) ) ) . '">';
			}
			if ( ! empty( $current['post_type'] ) ) {
				echo '<input type="hidden" name="post_type" value="product">';
			}
			?>

			<div class="mc-filter-group">
				<div class="mc-filter-group__title"><?php esc_html_e( 'موجودی', 'mobicare-core' ); ?></div>
				<label>
					<input type="checkbox" name="filter_stock" value="instock" <?php checked( ! empty( $current['filter_stock'] ) && 'instock' === $current['filter_stock'] ); ?>>
					<?php esc_html_e( 'فقط کالاهای موجود', 'mobicare-core' ); ?>
				</label>
				<label>
					<input type="checkbox" name="filter_sale" value="1" <?php checked( ! empty( $current['filter_sale'] ) || ! empty( $current['on_sale'] ) ); ?>>
					<?php esc_html_e( 'فقط تخفیف‌دار', 'mobicare-core' ); ?>
				</label>
			</div>

			<div class="mc-filter-group">
				<div class="mc-filter-group__title"><?php esc_html_e( 'بازه قیمت (تومان)', 'mobicare-core' ); ?></div>
				<div class="mc-price-range">
					<input type="number" name="min_price" min="0" step="1000" placeholder="<?php esc_attr_e( 'از', 'mobicare-core' ); ?>" value="<?php echo isset( $current['min_price'] ) ? esc_attr( $current['min_price'] ) : ''; ?>">
					<input type="number" name="max_price" min="0" step="1000" placeholder="<?php esc_attr_e( 'تا', 'mobicare-core' ); ?>" value="<?php echo isset( $current['max_price'] ) ? esc_attr( $current['max_price'] ) : ''; ?>">
				</div>
			</div>

			<div class="mc-filter-group">
				<div class="mc-filter-group__title"><?php esc_html_e( 'امتیاز', 'mobicare-core' ); ?></div>
				<ul>
					<?php for ( $r = 4; $r >= 1; $r-- ) : ?>
						<li>
							<label>
								<input type="radio" name="filter_rating" value="<?php echo esc_attr( $r ); ?>" <?php checked( isset( $current['filter_rating'] ) && (int) $current['filter_rating'] === $r ); ?>>
								<?php
								printf(
									/* translators: stars */
									esc_html__( '%d ستاره و بالاتر', 'mobicare-core' ),
									(int) $r
								);
								?>
							</label>
						</li>
					<?php endfor; ?>
				</ul>
			</div>

			<?php self::render_term_filter( 'phone_brand', 'filter_phone_brand', __( 'برند گوشی', 'mobicare-core' ), $current ); ?>
			<?php self::render_term_filter( 'phone_model', 'filter_phone_model', __( 'مدل گوشی', 'mobicare-core' ), $current, 30 ); ?>
			<?php self::render_term_filter( 'product_brand', 'filter_brand', __( 'برند محصول', 'mobicare-core' ), $current ); ?>
			<?php self::render_term_filter( 'pa_color', 'filter_color', __( 'رنگ', 'mobicare-core' ), $current ); ?>
			<?php self::render_term_filter( 'pa_material', 'filter_material', __( 'متریال', 'mobicare-core' ), $current ); ?>
			<?php self::render_term_filter( 'pa_magsafe', 'filter_magsafe', __( 'MagSafe', 'mobicare-core' ), $current ); ?>

			<button type="submit" class="mc-btn mc-btn--primary mc-btn--block"><?php esc_html_e( 'اعمال فیلتر', 'mobicare-core' ); ?></button>
			<a class="mc-btn mc-btn--ghost mc-btn--block" href="<?php echo esc_url( $base_url ); ?>" style="margin-top:8px"><?php esc_html_e( 'پاک کردن فیلترها', 'mobicare-core' ); ?></a>
		</form>
		<?php
	}

	/**
	 * Term checklist filter.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     GET name.
	 * @param string $label    Label.
	 * @param array  $current  Current GET.
	 * @param int    $limit    Limit.
	 */
	private static function render_term_filter( $taxonomy, $name, $label, $current, $limit = 20 ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}
		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => $limit,
			'orderby'    => 'count',
			'order'      => 'DESC',
		) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}
		$selected = array();
		if ( ! empty( $current[ $name ] ) ) {
			$raw_sel = wp_unslash( $current[ $name ] );
			if ( is_array( $raw_sel ) ) {
				$selected = array_filter( array_map( 'sanitize_title', $raw_sel ) );
			} else {
				$selected = array_filter( array_map( 'sanitize_title', explode( ',', sanitize_text_field( $raw_sel ) ) ) );
			}
		}
		?>
		<div class="mc-filter-group">
			<div class="mc-filter-group__title"><?php echo esc_html( $label ); ?></div>
			<ul>
				<?php foreach ( $terms as $t ) : ?>
					<li>
						<label>
							<input type="checkbox"
								name="<?php echo esc_attr( $name ); ?>[]"
								value="<?php echo esc_attr( $t->slug ); ?>"
								<?php checked( in_array( $t->slug, $selected, true ) ); ?>>
							<?php echo esc_html( $t->name ); ?>
							<span class="mc-muted">(<?php echo esc_html( (string) $t->count ); ?>)</span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}

/**
 * Theme helper wrapper.
 */
function mobicare_render_default_filters() {
	MobiCare_Filters::render_default_filters();
}
