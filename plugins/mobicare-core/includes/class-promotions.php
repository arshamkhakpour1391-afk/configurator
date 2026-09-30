<?php
/**
 * Promotions CPT for banners/campaigns manageable from admin
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Promotions
 */
class MobiCare_Promotions {

	const CPT = 'mc_promotion';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save' ) );
	}

	/**
	 * Register CPT.
	 */
	public static function register() {
		register_post_type(
			self::CPT,
			array(
				'labels'       => array(
					'name'          => __( 'پروموشن‌ها', 'mobicare-core' ),
					'singular_name' => __( 'پروموشن', 'mobicare-core' ),
					'add_new_item'  => __( 'افزودن پروموشن', 'mobicare-core' ),
					'edit_item'     => __( 'ویرایش پروموشن', 'mobicare-core' ),
					'menu_name'     => __( 'پروموشن‌ها', 'mobicare-core' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-megaphone',
				'supports'     => array( 'title', 'thumbnail', 'page-attributes' ),
			)
		);
	}

	/**
	 * Meta boxes.
	 */
	public static function meta_boxes() {
		add_meta_box( 'mc_promo_meta', __( 'تنظیمات پروموشن', 'mobicare-core' ), array( __CLASS__, 'render_meta' ), self::CPT, 'normal', 'high' );
	}

	/**
	 * Meta HTML.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta( $post ) {
		wp_nonce_field( 'mc_promo_meta', 'mc_promo_meta_nonce' );
		$link     = get_post_meta( $post->ID, '_promo_link', true );
		$type     = get_post_meta( $post->ID, '_promo_type', true ) ?: 'banner';
		$active   = get_post_meta( $post->ID, '_promo_active', true );
		$start    = get_post_meta( $post->ID, '_promo_start', true );
		$end      = get_post_meta( $post->ID, '_promo_end', true );
		$subtitle = get_post_meta( $post->ID, '_promo_subtitle', true );
		if ( '' === $active ) {
			$active = '1';
		}
		?>
		<p>
			<label><strong><?php esc_html_e( 'نوع', 'mobicare-core' ); ?></strong></label><br>
			<select name="mc_promo_type" class="widefat">
				<option value="banner" <?php selected( $type, 'banner' ); ?>><?php esc_html_e( 'بنر', 'mobicare-core' ); ?></option>
				<option value="announce" <?php selected( $type, 'announce' ); ?>><?php esc_html_e( 'نوار اعلان', 'mobicare-core' ); ?></option>
				<option value="home" <?php selected( $type, 'home' ); ?>><?php esc_html_e( 'بخش صفحه اصلی', 'mobicare-core' ); ?></option>
			</select>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'زیرعنوان / متن', 'mobicare-core' ); ?></strong></label>
			<textarea name="mc_promo_subtitle" class="widefat" rows="3"><?php echo esc_textarea( $subtitle ); ?></textarea>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'لینک', 'mobicare-core' ); ?></strong></label>
			<input type="url" name="mc_promo_link" class="widefat" value="<?php echo esc_attr( $link ); ?>">
		</p>
		<p>
			<label>
				<input type="checkbox" name="mc_promo_active" value="1" <?php checked( $active, '1' ); ?>>
				<?php esc_html_e( 'فعال', 'mobicare-core' ); ?>
			</label>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'شروع (YYYY-MM-DD اختیاری)', 'mobicare-core' ); ?></strong></label>
			<input type="date" name="mc_promo_start" value="<?php echo esc_attr( $start ); ?>">
		</p>
		<p>
			<label><strong><?php esc_html_e( 'پایان (YYYY-MM-DD اختیاری)', 'mobicare-core' ); ?></strong></label>
			<input type="date" name="mc_promo_end" value="<?php echo esc_attr( $end ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'تصویر شاخص را به‌عنوان تصویر بنر تنظیم کنید. ترتیب با «ترتیب» نوشته کنترل می‌شود. کوپن‌ها را از ووکامرس → کوپن‌ها بسازید.', 'mobicare-core' ); ?></p>
		<?php
	}

	/**
	 * Save.
	 *
	 * @param int $post_id ID.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST['mc_promo_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mc_promo_meta_nonce'] ) ), 'mc_promo_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_promo_link', isset( $_POST['mc_promo_link'] ) ? esc_url_raw( wp_unslash( $_POST['mc_promo_link'] ) ) : '' );
		update_post_meta( $post_id, '_promo_type', isset( $_POST['mc_promo_type'] ) ? sanitize_key( wp_unslash( $_POST['mc_promo_type'] ) ) : 'banner' );
		update_post_meta( $post_id, '_promo_active', isset( $_POST['mc_promo_active'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_promo_start', isset( $_POST['mc_promo_start'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_promo_start'] ) ) : '' );
		update_post_meta( $post_id, '_promo_end', isset( $_POST['mc_promo_end'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_promo_end'] ) ) : '' );
		update_post_meta( $post_id, '_promo_subtitle', isset( $_POST['mc_promo_subtitle'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mc_promo_subtitle'] ) ) : '' );
	}

	/**
	 * Get active promotions by type.
	 *
	 * @param string $type Type.
	 * @return WP_Post[]
	 */
	public static function get_active( $type = 'banner' ) {
		$q = new WP_Query( array(
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_promo_active',
					'value' => '1',
				),
				array(
					'key'   => '_promo_type',
					'value' => $type,
				),
			),
		) );
		$today = gmdate( 'Y-m-d' );
		$out   = array();
		foreach ( $q->posts as $p ) {
			$start = get_post_meta( $p->ID, '_promo_start', true );
			$end   = get_post_meta( $p->ID, '_promo_end', true );
			if ( $start && $today < $start ) {
				continue;
			}
			if ( $end && $today > $end ) {
				continue;
			}
			$out[] = $p;
		}
		return $out;
	}
}
