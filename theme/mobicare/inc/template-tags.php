<?php
/**
 * Template tags and helpers
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Format number to Persian digits optionally.
 *
 * @param mixed $number Number.
 * @return string
 */
function mobicare_persian_digits( $number ) {
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$use_fa = (bool) get_theme_mod( 'mobicare_persian_digits', true );
	$str = (string) $number;
	return $use_fa ? str_replace( $en, $fa, $str ) : $str;
}

/**
 * Print star rating HTML.
 *
 * @param float $rating Rating 0-5.
 * @param int   $count  Review count.
 */
function mobicare_star_rating( $rating, $count = 0 ) {
	$rating = max( 0, min( 5, (float) $rating ) );
	$full   = (int) floor( $rating );
	$half   = ( $rating - $full ) >= 0.5 ? 1 : 0;
	$empty  = 5 - $full - $half;

	echo '<div class="mc-stars" role="img" aria-label="' . esc_attr( sprintf( __( 'امتیاز %s از ۵', 'mobicare' ), $rating ) ) . '">';
	for ( $i = 0; $i < $full; $i++ ) {
		echo '<span class="mc-stars__star is-full" aria-hidden="true">★</span>';
	}
	if ( $half ) {
		echo '<span class="mc-stars__star is-half" aria-hidden="true">★</span>';
	}
	for ( $i = 0; $i < $empty; $i++ ) {
		echo '<span class="mc-stars__star is-empty" aria-hidden="true">★</span>';
	}
	if ( $count > 0 ) {
		echo '<span class="mc-stars__count">(' . esc_html( mobicare_persian_digits( $count ) ) . ')</span>';
	}
	echo '</div>';
}

/**
 * Product badge (sale / new / out of stock).
 *
 * @param WC_Product $product Product.
 */
function mobicare_product_badges( $product ) {
	if ( ! $product ) {
		return;
	}
	echo '<div class="mc-product-badges">';
	if ( ! $product->is_in_stock() ) {
		echo '<span class="mc-badge-tag mc-badge-tag--oos">' . esc_html__( 'ناموجود', 'mobicare' ) . '</span>';
	} elseif ( $product->is_on_sale() ) {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		if ( $regular > 0 && $sale > 0 ) {
			$pct = round( ( ( $regular - $sale ) / $regular ) * 100 );
			echo '<span class="mc-badge-tag mc-badge-tag--sale">' . esc_html( mobicare_persian_digits( $pct ) . '٪' ) . '</span>';
		} else {
			echo '<span class="mc-badge-tag mc-badge-tag--sale">' . esc_html__( 'تخفیف', 'mobicare' ) . '</span>';
		}
	}
	$created = strtotime( $product->get_date_created() ? $product->get_date_created()->date( 'Y-m-d H:i:s' ) : '' );
	if ( $created && ( time() - $created ) < WEEK_IN_SECONDS * 2 ) {
		echo '<span class="mc-badge-tag mc-badge-tag--new">' . esc_html__( 'جدید', 'mobicare' ) . '</span>';
	}
	echo '</div>';
}

/**
 * Wishlist count for current user/session.
 *
 * @return int
 */
function mobicare_get_wishlist_count() {
	if ( function_exists( 'mobicare_wishlist_get_ids' ) ) {
		return count( mobicare_wishlist_get_ids() );
	}
	$ids = array();
	if ( is_user_logged_in() ) {
		$ids = get_user_meta( get_current_user_id(), '_mobicare_wishlist', true );
	} elseif ( isset( $_COOKIE['mobicare_wishlist'] ) ) {
		$ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_COOKIE['mobicare_wishlist'] ) ) ) ) );
	}
	return is_array( $ids ) ? count( $ids ) : 0;
}

/**
 * Check if product in wishlist.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mobicare_in_wishlist( $product_id ) {
	if ( function_exists( 'mobicare_wishlist_get_ids' ) ) {
		return in_array( (int) $product_id, mobicare_wishlist_get_ids(), true );
	}
	return false;
}

/**
 * Get product attribute display value.
 *
 * @param WC_Product $product Product.
 * @param string     $attr    Attribute taxonomy without pa_.
 * @return string
 */
function mobicare_get_product_attr( $product, $attr ) {
	if ( ! $product ) {
		return '';
	}
	$tax = 'pa_' . $attr;
	$val = $product->get_attribute( $tax );
	if ( ! $val ) {
		$val = $product->get_attribute( $attr );
	}
	return $val;
}

/**
 * Safe image with placeholder.
 *
 * @param int    $attachment_id Attachment.
 * @param string $size          Size.
 * @param array  $attr          Attrs.
 */
function mobicare_product_image( $attachment_id, $size = 'woocommerce_thumbnail', $attr = array() ) {
	if ( $attachment_id ) {
		echo wp_get_attachment_image( $attachment_id, $size, false, $attr );
		return;
	}
	$placeholder = wc_placeholder_img_src( $size );
	$alt = isset( $attr['alt'] ) ? $attr['alt'] : __( 'بدون تصویر', 'mobicare' );
	printf(
		'<img src="%s" alt="%s" class="mc-placeholder-img" loading="lazy" width="400" height="400" />',
		esc_url( $placeholder ),
		esc_attr( $alt )
	);
}

/**
 * Breadcrumb wrapper.
 */
function mobicare_breadcrumbs() {
	if ( function_exists( 'woocommerce_breadcrumb' ) ) {
		woocommerce_breadcrumb( array(
			'delimiter'   => '<span class="mc-bc-sep" aria-hidden="true">/</span>',
			'wrap_before' => '<nav class="mc-breadcrumb" aria-label="' . esc_attr__( 'مسیر صفحه', 'mobicare' ) . '"><ol class="mc-breadcrumb__list">',
			'wrap_after'  => '</ol></nav>',
			'before'      => '<li class="mc-breadcrumb__item">',
			'after'       => '</li>',
			'home'        => __( 'خانه', 'mobicare' ),
		) );
	}
}

/**
 * Section header partial.
 *
 * @param string $title Title.
 * @param string $link  Optional more link.
 * @param string $link_text Link text.
 */
function mobicare_section_header( $title, $link = '', $link_text = '' ) {
	echo '<div class="mc-section__head">';
	echo '<h2 class="mc-section__title">' . esc_html( $title ) . '</h2>';
	if ( $link ) {
		$link_text = $link_text ? $link_text : __( 'مشاهده همه', 'mobicare' );
		echo '<a class="mc-section__more" href="' . esc_url( $link ) . '">' . esc_html( $link_text ) . ' <span aria-hidden="true">←</span></a>';
	}
	echo '</div>';
}
