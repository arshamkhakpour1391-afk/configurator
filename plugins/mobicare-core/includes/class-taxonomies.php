<?php
/**
 * Product taxonomies: phone brands, phone models, product brands
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Taxonomies
 */
class MobiCare_Taxonomies {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'phone_brand_add_form_fields', array( __CLASS__, 'brand_add_fields' ) );
		add_action( 'phone_brand_edit_form_fields', array( __CLASS__, 'brand_edit_fields' ) );
		add_action( 'created_phone_brand', array( __CLASS__, 'save_brand_meta' ) );
		add_action( 'edited_phone_brand', array( __CLASS__, 'save_brand_meta' ) );

		add_action( 'phone_model_add_form_fields', array( __CLASS__, 'model_add_fields' ) );
		add_action( 'phone_model_edit_form_fields', array( __CLASS__, 'model_edit_fields' ), 10, 2 );
		add_action( 'created_phone_model', array( __CLASS__, 'save_model_meta' ) );
		add_action( 'edited_phone_model', array( __CLASS__, 'save_model_meta' ) );

		add_filter( 'manage_edit-phone_model_columns', array( __CLASS__, 'model_columns' ) );
		add_filter( 'manage_phone_model_custom_column', array( __CLASS__, 'model_column_content' ), 10, 3 );
	}

	/**
	 * Register taxonomies.
	 */
	public static function register() {
		// Product brand (for case/glass manufacturers).
		register_taxonomy(
			'product_brand',
			array( 'product' ),
			array(
				'labels'            => array(
					'name'          => __( 'برند محصول', 'mobicare-core' ),
					'singular_name' => __( 'برند', 'mobicare-core' ),
					'search_items'  => __( 'جستجوی برند', 'mobicare-core' ),
					'all_items'     => __( 'همه برندها', 'mobicare-core' ),
					'edit_item'     => __( 'ویرایش برند', 'mobicare-core' ),
					'update_item'   => __( 'بروزرسانی برند', 'mobicare-core' ),
					'add_new_item'  => __( 'افزودن برند', 'mobicare-core' ),
					'new_item_name' => __( 'نام برند جدید', 'mobicare-core' ),
					'menu_name'     => __( 'برندها', 'mobicare-core' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'brand' ),
			)
		);

		// Phone brand (Apple, Samsung, ...).
		register_taxonomy(
			'phone_brand',
			array( 'product' ),
			array(
				'labels'            => array(
					'name'          => __( 'برند گوشی', 'mobicare-core' ),
					'singular_name' => __( 'برند گوشی', 'mobicare-core' ),
					'search_items'  => __( 'جستجوی برند گوشی', 'mobicare-core' ),
					'all_items'     => __( 'همه برندهای گوشی', 'mobicare-core' ),
					'edit_item'     => __( 'ویرایش برند گوشی', 'mobicare-core' ),
					'update_item'   => __( 'بروزرسانی', 'mobicare-core' ),
					'add_new_item'  => __( 'افزودن برند گوشی', 'mobicare-core' ),
					'new_item_name' => __( 'نام برند', 'mobicare-core' ),
					'menu_name'     => __( 'برند گوشی', 'mobicare-core' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => false,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'phone-brand' ),
			)
		);

		// Phone model (iPhone 16 Pro Max, ...).
		// Hierarchical: optional parent models/series; brand linked via term meta.
		register_taxonomy(
			'phone_model',
			array( 'product' ),
			array(
				'labels'            => array(
					'name'          => __( 'مدل گوشی', 'mobicare-core' ),
					'singular_name' => __( 'مدل گوشی', 'mobicare-core' ),
					'search_items'  => __( 'جستجوی مدل', 'mobicare-core' ),
					'all_items'     => __( 'همه مدل‌ها', 'mobicare-core' ),
					'parent_item'   => __( 'سری والد', 'mobicare-core' ),
					'edit_item'     => __( 'ویرایش مدل', 'mobicare-core' ),
					'update_item'   => __( 'بروزرسانی مدل', 'mobicare-core' ),
					'add_new_item'  => __( 'افزودن مدل گوشی', 'mobicare-core' ),
					'new_item_name' => __( 'نام مدل', 'mobicare-core' ),
					'menu_name'     => __( 'مدل گوشی', 'mobicare-core' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'phone' ),
			)
		);
	}

	/**
	 * Brand add fields.
	 */
	public static function brand_add_fields() {
		?>
		<div class="form-field">
			<label for="phone_brand_order"><?php esc_html_e( 'ترتیب نمایش', 'mobicare-core' ); ?></label>
			<input type="number" name="phone_brand_order" id="phone_brand_order" value="0" min="0" step="1">
			<p><?php esc_html_e( 'عدد کمتر = اولویت بالاتر در انتخابگر مدل.', 'mobicare-core' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Brand edit fields.
	 *
	 * @param WP_Term $term Term.
	 */
	public static function brand_edit_fields( $term ) {
		$order = (int) get_term_meta( $term->term_id, 'phone_brand_order', true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="phone_brand_order"><?php esc_html_e( 'ترتیب نمایش', 'mobicare-core' ); ?></label></th>
			<td>
				<input type="number" name="phone_brand_order" id="phone_brand_order" value="<?php echo esc_attr( $order ); ?>" min="0" step="1">
			</td>
		</tr>
		<?php
	}

	/**
	 * Save brand meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_brand_meta( $term_id ) {
		if ( isset( $_POST['phone_brand_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, 'phone_brand_order', absint( $_POST['phone_brand_order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Model add fields — link to phone brand.
	 */
	public static function model_add_fields() {
		$brands = get_terms( array(
			'taxonomy'   => 'phone_brand',
			'hide_empty' => false,
		) );
		?>
		<div class="form-field mobicare-term-meta">
			<label for="phone_brand_id"><?php esc_html_e( 'برند گوشی', 'mobicare-core' ); ?></label>
			<select name="phone_brand_id" id="phone_brand_id">
				<option value=""><?php esc_html_e( '— انتخاب برند —', 'mobicare-core' ); ?></option>
				<?php if ( ! is_wp_error( $brands ) ) : ?>
					<?php foreach ( $brands as $b ) : ?>
						<option value="<?php echo esc_attr( $b->term_id ); ?>"><?php echo esc_html( $b->name ); ?></option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
			<p><?php esc_html_e( 'مدل را به برند وصل کنید تا در «یافتن مدل گوشی» درست گروه‌بندی شود. برندها را از منوی محصولات → برند گوشی بسازید.', 'mobicare-core' ); ?></p>
		</div>
		<div class="form-field">
			<label for="phone_model_year"><?php esc_html_e( 'سال / نسل (اختیاری)', 'mobicare-core' ); ?></label>
			<input type="text" name="phone_model_year" id="phone_model_year" value="" placeholder="2025">
		</div>
		<?php
	}

	/**
	 * Model edit fields.
	 *
	 * @param WP_Term $term Term.
	 */
	public static function model_edit_fields( $term ) {
		$brand_id = (int) get_term_meta( $term->term_id, 'phone_brand_id', true );
		$year     = get_term_meta( $term->term_id, 'phone_model_year', true );
		$brands   = get_terms( array(
			'taxonomy'   => 'phone_brand',
			'hide_empty' => false,
		) );
		?>
		<tr class="form-field mobicare-term-meta">
			<th scope="row"><label for="phone_brand_id"><?php esc_html_e( 'برند گوشی', 'mobicare-core' ); ?></label></th>
			<td>
				<select name="phone_brand_id" id="phone_brand_id">
					<option value=""><?php esc_html_e( '— انتخاب برند —', 'mobicare-core' ); ?></option>
					<?php if ( ! is_wp_error( $brands ) ) : ?>
						<?php foreach ( $brands as $b ) : ?>
							<option value="<?php echo esc_attr( $b->term_id ); ?>" <?php selected( $brand_id, $b->term_id ); ?>><?php echo esc_html( $b->name ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="phone_model_year"><?php esc_html_e( 'سال / نسل', 'mobicare-core' ); ?></label></th>
			<td>
				<input type="text" name="phone_model_year" id="phone_model_year" value="<?php echo esc_attr( $year ); ?>">
			</td>
		</tr>
		<?php
	}

	/**
	 * Save model meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_model_meta( $term_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['phone_brand_id'] ) ) {
			$bid = absint( $_POST['phone_brand_id'] );
			if ( $bid ) {
				update_term_meta( $term_id, 'phone_brand_id', $bid );
			} else {
				delete_term_meta( $term_id, 'phone_brand_id' );
			}
		}
		if ( isset( $_POST['phone_model_year'] ) ) {
			update_term_meta( $term_id, 'phone_model_year', sanitize_text_field( wp_unslash( $_POST['phone_model_year'] ) ) );
		}
		// phpcs:enable
	}

	/**
	 * Admin columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function model_columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'name' === $k ) {
				$new['phone_brand'] = __( 'برند گوشی', 'mobicare-core' );
				$new['model_year']  = __( 'سال', 'mobicare-core' );
			}
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $content Content.
	 * @param string $column  Column.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public static function model_column_content( $content, $column, $term_id ) {
		if ( 'phone_brand' === $column ) {
			$bid = (int) get_term_meta( $term_id, 'phone_brand_id', true );
			if ( $bid ) {
				$t = get_term( $bid, 'phone_brand' );
				return ( $t && ! is_wp_error( $t ) ) ? esc_html( $t->name ) : '—';
			}
			return '—';
		}
		if ( 'model_year' === $column ) {
			$y = get_term_meta( $term_id, 'phone_model_year', true );
			return $y ? esc_html( $y ) : '—';
		}
		return $content;
	}
}
