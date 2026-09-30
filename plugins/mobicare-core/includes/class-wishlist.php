<?php
/**
 * Wishlist system
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Wishlist
 */
class MobiCare_Wishlist {

	const USER_META = '_mobicare_wishlist';
	const COOKIE    = 'mobicare_wishlist';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'wp_ajax_mobicare_wishlist_toggle', array( __CLASS__, 'ajax_toggle' ) );
		add_action( 'wp_ajax_nopriv_mobicare_wishlist_toggle', array( __CLASS__, 'ajax_toggle' ) );
		add_action( 'wp_login', array( __CLASS__, 'merge_cookie_on_login' ), 10, 2 );
	}

	/**
	 * Get wishlist product IDs.
	 *
	 * @return int[]
	 */
	public static function get_ids() {
		$ids = array();
		if ( is_user_logged_in() ) {
			$ids = get_user_meta( get_current_user_id(), self::USER_META, true );
			if ( ! is_array( $ids ) ) {
				$ids = array();
			}
		} else {
			$ids = self::get_cookie_ids();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Cookie IDs.
	 *
	 * @return int[]
	 */
	private static function get_cookie_ids() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return array();
		}
		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		return array_filter( array_map( 'absint', explode( ',', $raw ) ) );
	}

	/**
	 * Persist IDs.
	 *
	 * @param int[] $ids IDs.
	 */
	private static function save_ids( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), self::USER_META, $ids );
		} else {
			$val = implode( ',', $ids );
			// 30 days.
			setcookie( self::COOKIE, $val, time() + MONTH_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			$_COOKIE[ self::COOKIE ] = $val;
		}
	}

	/**
	 * Toggle product.
	 *
	 * @param int $product_id Product ID.
	 * @return array{in_wishlist:bool,count:int}
	 */
	public static function toggle( $product_id ) {
		$product_id = absint( $product_id );
		$ids        = self::get_ids();
		$in         = in_array( $product_id, $ids, true );
		if ( $in ) {
			$ids = array_values( array_diff( $ids, array( $product_id ) ) );
		} else {
			$ids[] = $product_id;
		}
		self::save_ids( $ids );
		return array(
			'in_wishlist' => ! $in,
			'count'       => count( $ids ),
		);
	}

	/**
	 * AJAX handler.
	 */
	public static function ajax_toggle() {
		check_ajax_referer( 'mobicare_nonce', 'nonce' );
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'محصول نامعتبر است.', 'mobicare-core' ) ), 400 );
		}
		$result = self::toggle( $product_id );
		wp_send_json_success( $result );
	}

	/**
	 * Merge cookie wishlist into user on login.
	 *
	 * @param string  $login Login.
	 * @param WP_User $user  User.
	 */
	public static function merge_cookie_on_login( $login, $user ) {
		$cookie = self::get_cookie_ids();
		if ( empty( $cookie ) ) {
			return;
		}
		$existing = get_user_meta( $user->ID, self::USER_META, true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$merged = array_values( array_unique( array_merge( $existing, $cookie ) ) );
		update_user_meta( $user->ID, self::USER_META, $merged );
		setcookie( self::COOKIE, '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	}
}
