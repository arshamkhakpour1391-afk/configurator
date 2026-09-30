<?php
/**
 * Compare products
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Compare
 */
class MobiCare_Compare {

	const COOKIE = 'mobicare_compare';
	const MAX    = 4;

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'wp_ajax_mobicare_compare_toggle', array( __CLASS__, 'ajax_toggle' ) );
		add_action( 'wp_ajax_nopriv_mobicare_compare_toggle', array( __CLASS__, 'ajax_toggle' ) );
	}

	/**
	 * Get IDs.
	 *
	 * @return int[]
	 */
	public static function get_ids() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return array();
		}
		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		return array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) ) );
	}

	/**
	 * Save.
	 *
	 * @param int[] $ids IDs.
	 */
	private static function save( $ids ) {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, self::MAX );
		$val = implode( ',', $ids );
		setcookie( self::COOKIE, $val, time() + WEEK_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ self::COOKIE ] = $val;
	}

	/**
	 * Toggle.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public static function toggle( $product_id ) {
		$product_id = absint( $product_id );
		$ids        = self::get_ids();
		$in         = in_array( $product_id, $ids, true );
		if ( $in ) {
			$ids = array_values( array_diff( $ids, array( $product_id ) ) );
		} else {
			if ( count( $ids ) >= self::MAX ) {
				return array(
					'error'   => true,
					'message' => sprintf(
						/* translators: %d max compare */
						__( 'حداکثر %d محصول می‌توانید مقایسه کنید.', 'mobicare-core' ),
						self::MAX
					),
				);
			}
			$ids[] = $product_id;
		}
		self::save( $ids );
		return array(
			'in_compare' => ! $in,
			'count'      => count( $ids ),
			'ids'        => $ids,
		);
	}

	/**
	 * AJAX.
	 */
	public static function ajax_toggle() {
		check_ajax_referer( 'mobicare_nonce', 'nonce' );
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'محصول نامعتبر است.', 'mobicare-core' ) ), 400 );
		}
		$result = self::toggle( $product_id );
		if ( ! empty( $result['error'] ) ) {
			wp_send_json_error( $result, 400 );
		}
		wp_send_json_success( $result );
	}
}
