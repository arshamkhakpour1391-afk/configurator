<?php
/**
 * Extra product fields: video, warranty text, specs
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Product_Meta
 */
class MobiCare_Product_Meta {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ) );
	}

	/**
	 * Fields in product data.
	 */
	public static function fields() {
		echo '<div class="options_group">';

		woocommerce_wp_text_input( array(
			'id'          => '_mobicare_video_url',
			'label'       => __( 'آدرس ویدیو محصول', 'mobicare-core' ),
			'description' => __( 'لینک YouTube / Aparat / Vimeo (اختیاری)', 'mobicare-core' ),
			'desc_tip'    => true,
			'type'        => 'url',
		) );

		woocommerce_wp_textarea_input( array(
			'id'          => '_mobicare_warranty',
			'label'       => __( 'توضیح گارانتی', 'mobicare-core' ),
			'description' => __( 'متن کوتاه گارانتی برای نمایش در صفحه محصول', 'mobicare-core' ),
			'desc_tip'    => true,
		) );

		woocommerce_wp_textarea_input( array(
			'id'          => '_mobicare_specs',
			'label'       => __( 'مشخصات فنی (هر خط یک مورد)', 'mobicare-core' ),
			'description' => __( 'مثال: ضخامت: ۰.۳۳mm', 'mobicare-core' ),
			'desc_tip'    => true,
		) );

		echo '</div>';
	}

	/**
	 * Save.
	 *
	 * @param int $post_id Product ID.
	 */
	public static function save( $post_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WC handles nonce.
		if ( isset( $_POST['_mobicare_video_url'] ) ) {
			update_post_meta( $post_id, '_mobicare_video_url', esc_url_raw( wp_unslash( $_POST['_mobicare_video_url'] ) ) );
		}
		if ( isset( $_POST['_mobicare_warranty'] ) ) {
			update_post_meta( $post_id, '_mobicare_warranty', sanitize_textarea_field( wp_unslash( $_POST['_mobicare_warranty'] ) ) );
		}
		if ( isset( $_POST['_mobicare_specs'] ) ) {
			update_post_meta( $post_id, '_mobicare_specs', sanitize_textarea_field( wp_unslash( $_POST['_mobicare_specs'] ) ) );
		}
		// phpcs:enable
	}
}
