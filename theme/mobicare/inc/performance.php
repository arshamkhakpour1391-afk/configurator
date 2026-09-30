<?php
/**
 * Performance optimizations
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove emoji scripts.
 */
function mobicare_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'mobicare_disable_emojis' );

/**
 * Defer non-critical scripts.
 *
 * @param string $tag    Tag.
 * @param string $handle Handle.
 * @param string $src    Src.
 * @return string
 */
function mobicare_defer_scripts( $tag, $handle, $src ) {
	$defer = array( 'mobicare-main', 'mobicare-shop' );
	if ( in_array( $handle, $defer, true ) ) {
		if ( false === strpos( $tag, 'defer' ) ) {
			$tag = str_replace( ' src', ' defer src', $tag );
		}
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'mobicare_defer_scripts', 10, 3 );

/**
 * Preconnect fonts.
 */
function mobicare_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href'        => 'https://fonts.googleapis.com',
			'crossorigin' => 'anonymous',
		);
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'mobicare_resource_hints', 10, 2 );

/**
 * Lazy load images by default (WP 5.5+).
 */
add_filter( 'wp_lazy_loading_enabled', '__return_true' );

/**
 * Disable WooCommerce heavy scripts on non-shop pages.
 */
function mobicare_wc_script_control() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
		// Keep cart fragments for header count on all pages.
		wp_enqueue_script( 'wc-cart-fragments' );
	}
}
add_action( 'wp_enqueue_scripts', 'mobicare_wc_script_control', 99 );

/**
 * Remove query strings from static resources in production-like envs (optional mild).
 * Kept versioned via MOBICARE_VERSION for cache busting — do not strip.
 */

/**
 * Limit post revisions for products via filter if needed — leave WP default.
 */

/**
 * Disable heartbeat on frontend.
 */
function mobicare_disable_frontend_heartbeat() {
	if ( ! is_admin() ) {
		wp_deregister_script( 'heartbeat' );
	}
}
add_action( 'init', 'mobicare_disable_frontend_heartbeat', 1 );
