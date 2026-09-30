<?php
/**
 * WooCommerce integration
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove default WC wrappers; theme provides structure.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

/**
 * Theme wrappers.
 */
function mobicare_wc_wrapper_start() {
	echo '<main id="main" class="mc-main mc-wc" role="main"><div class="mc-container">';
}
add_action( 'woocommerce_before_main_content', 'mobicare_wc_wrapper_start', 10 );

function mobicare_wc_wrapper_end() {
	echo '</div></main>';
}
add_action( 'woocommerce_after_main_content', 'mobicare_wc_wrapper_end', 10 );

/**
 * Products per page.
 */
function mobicare_products_per_page() {
	return (int) get_theme_mod( 'mobicare_products_per_page', 16 );
}
add_filter( 'loop_shop_per_page', 'mobicare_products_per_page', 20 );

/**
 * Loop columns.
 */
function mobicare_loop_columns() {
	return 4;
}
add_filter( 'loop_shop_columns', 'mobicare_loop_columns' );

/**
 * Related products args.
 */
function mobicare_related_products_args( $args ) {
	$args['posts_per_page'] = 4;
	$args['columns']        = 4;
	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'mobicare_related_products_args' );

/**
 * Upsell columns.
 */
function mobicare_upsell_columns( $args ) {
	$args['columns'] = 4;
	return $args;
}
add_filter( 'woocommerce_upsell_display_args', 'mobicare_upsell_columns' );

/**
 * Cart fragments for AJAX count.
 */
function mobicare_cart_count_fragment( $fragments ) {
	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	ob_start();
	?>
	<span class="mc-badge" id="mc-cart-count" data-count="<?php echo esc_attr( $count ); ?>" <?php echo $count ? '' : 'hidden'; ?>><?php echo esc_html( $count ); ?></span>
	<?php
	$fragments['#mc-cart-count'] = ob_get_clean();

	ob_start();
	?>
	<span class="mc-badge mc-bottom-bar__badge" id="mc-cart-count-mobile" <?php echo $count ? '' : 'hidden'; ?>><?php echo esc_html( $count ); ?></span>
	<?php
	$fragments['#mc-cart-count-mobile'] = ob_get_clean();

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'mobicare_cart_count_fragment' );

/**
 * Persian checkout fields.
 */
function mobicare_checkout_fields( $fields ) {
	if ( isset( $fields['billing']['billing_phone'] ) ) {
		$fields['billing']['billing_phone']['label']       = __( 'شماره موبایل', 'mobicare' );
		$fields['billing']['billing_phone']['placeholder'] = '0912xxxxxxx';
		$fields['billing']['billing_phone']['priority']    = 25;
		$fields['billing']['billing_phone']['required']    = true;
		$fields['billing']['billing_phone']['class']       = array( 'form-row-wide' );
	}
	if ( isset( $fields['billing']['billing_first_name'] ) ) {
		$fields['billing']['billing_first_name']['label'] = __( 'نام', 'mobicare' );
	}
	if ( isset( $fields['billing']['billing_last_name'] ) ) {
		$fields['billing']['billing_last_name']['label'] = __( 'نام خانوادگی', 'mobicare' );
	}
	if ( isset( $fields['billing']['billing_email'] ) ) {
		$fields['billing']['billing_email']['label'] = __( 'ایمیل', 'mobicare' );
	}
	if ( isset( $fields['billing']['billing_address_1'] ) ) {
		$fields['billing']['billing_address_1']['label']       = __( 'آدرس', 'mobicare' );
		$fields['billing']['billing_address_1']['placeholder'] = __( 'خیابان، کوچه، پلاک، واحد', 'mobicare' );
	}
	if ( isset( $fields['billing']['billing_city'] ) ) {
		$fields['billing']['billing_city']['label'] = __( 'شهر', 'mobicare' );
	}
	if ( isset( $fields['billing']['billing_state'] ) ) {
		$fields['billing']['billing_state']['label'] = __( 'استان', 'mobicare' );
	}
	if ( isset( $fields['billing']['billing_postcode'] ) ) {
		$fields['billing']['billing_postcode']['label']       = __( 'کد پستی', 'mobicare' );
		$fields['billing']['billing_postcode']['placeholder'] = '1234567890';
	}
	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['label']       = __( 'یادداشت سفارش', 'mobicare' );
		$fields['order']['order_comments']['placeholder'] = __( 'توضیحات اضافی برای ارسال...', 'mobicare' );
	}

	// Soften company / address_2 for Iran stores.
	if ( isset( $fields['billing']['billing_company'] ) ) {
		$fields['billing']['billing_company']['required'] = false;
	}
	if ( isset( $fields['billing']['billing_address_2'] ) ) {
		$fields['billing']['billing_address_2']['required'] = false;
	}

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'mobicare_checkout_fields' );

/**
 * Default country IR.
 */
function mobicare_default_checkout_country() {
	return 'IR';
}
add_filter( 'default_checkout_billing_country', 'mobicare_default_checkout_country' );
add_filter( 'default_checkout_shipping_country', 'mobicare_default_checkout_country' );

/**
 * Sale flash override.
 */
function mobicare_custom_sale_flash( $html, $post, $product ) {
	ob_start();
	mobicare_product_badges( $product );
	return ob_get_clean();
}
add_filter( 'woocommerce_sale_flash', 'mobicare_custom_sale_flash', 10, 3 );

/**
 * Empty cart message.
 */
function mobicare_empty_cart_message() {
	return __( 'سبد خرید شما خالی است.', 'mobicare' );
}
add_filter( 'wc_empty_cart_message', 'mobicare_empty_cart_message' );

/**
 * Product tabs labels in Persian (WooCommerce i18n may cover; reinforce).
 */
function mobicare_product_tabs( $tabs ) {
	if ( isset( $tabs['description'] ) ) {
		$tabs['description']['title'] = __( 'توضیحات', 'mobicare' );
	}
	if ( isset( $tabs['additional_information'] ) ) {
		$tabs['additional_information']['title'] = __( 'مشخصات', 'mobicare' );
	}
	if ( isset( $tabs['reviews'] ) ) {
		$tabs['reviews']['title'] = __( 'نظرات', 'mobicare' );
	}
	// Questions tab if plugin registered.
	if ( ! isset( $tabs['questions'] ) && shortcode_exists( 'mobicare_product_questions' ) ) {
		$tabs['questions'] = array(
			'title'    => __( 'پرسش و پاسخ', 'mobicare' ),
			'priority' => 30,
			'callback' => 'mobicare_product_questions_tab',
		);
	}
	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'mobicare_product_tabs' );

/**
 * Questions tab callback.
 *
 * @param string $key Key.
 * @param array  $tab Tab.
 */
function mobicare_product_questions_tab( $key, $tab ) {
	echo do_shortcode( '[mobicare_product_questions]' );
}

/**
 * Shop toolbar: result count + ordering + filter toggle.
 */
function mobicare_shop_toolbar() {
	if ( ! woocommerce_product_loop() && ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}
	?>
	<div class="mc-shop-toolbar">
		<button type="button" class="mc-btn mc-btn--outline mc-btn--sm mc-filter-toggle" id="mc-filter-toggle" aria-expanded="false" aria-controls="mc-shop-filters">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
			<?php esc_html_e( 'فیلترها', 'mobicare' ); ?>
		</button>
		<div class="mc-shop-toolbar__count">
			<?php woocommerce_result_count(); ?>
		</div>
		<div class="mc-shop-toolbar__order">
			<?php woocommerce_catalog_ordering(); ?>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_before_shop_loop', 'mobicare_shop_toolbar', 15 );

/**
 * Catalog orderby options in Persian.
 */
function mobicare_catalog_orderby( $options ) {
	return array(
		'menu_order' => __( 'پیش‌فرض', 'mobicare' ),
		'popularity' => __( 'محبوب‌ترین', 'mobicare' ),
		'rating'     => __( 'بیشترین امتیاز', 'mobicare' ),
		'date'       => __( 'جدیدترین', 'mobicare' ),
		'price'      => __( 'ارزان‌ترین', 'mobicare' ),
		'price-desc' => __( 'گران‌ترین', 'mobicare' ),
	);
}
add_filter( 'woocommerce_catalog_orderby', 'mobicare_catalog_orderby' );

/**
 * Remove default loop link close / add to cart hooks — card template handles layout.
 */
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );

/**
 * Wishlist & compare buttons for product cards (called from content-product.php).
 */
function mobicare_loop_action_buttons() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$pid = $product->get_id();
	$in_wish = function_exists( 'mobicare_in_wishlist' ) && mobicare_in_wishlist( $pid );
	?>
	<div class="mc-product-card__actions">
		<button type="button"
			class="mc-icon-btn mc-wishlist-btn <?php echo $in_wish ? 'is-active' : ''; ?>"
			data-product-id="<?php echo esc_attr( $pid ); ?>"
			aria-label="<?php esc_attr_e( 'افزودن به علاقه‌مندی‌ها', 'mobicare' ); ?>"
			aria-pressed="<?php echo $in_wish ? 'true' : 'false'; ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $in_wish ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
		</button>
		<button type="button"
			class="mc-icon-btn mc-compare-btn"
			data-product-id="<?php echo esc_attr( $pid ); ?>"
			aria-label="<?php esc_attr_e( 'افزودن به مقایسه', 'mobicare' ); ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 3v18M15 3v18M3 9h6M15 15h6"/></svg>
		</button>
	</div>
	<?php
}

/**
 * Single product: wishlist near add to cart.
 */
function mobicare_single_wishlist_button() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$pid = $product->get_id();
	$in_wish = function_exists( 'mobicare_in_wishlist' ) && mobicare_in_wishlist( $pid );
	?>
	<button type="button"
		class="mc-btn mc-btn--ghost mc-wishlist-btn <?php echo $in_wish ? 'is-active' : ''; ?>"
		data-product-id="<?php echo esc_attr( $pid ); ?>"
		aria-pressed="<?php echo $in_wish ? 'true' : 'false'; ?>">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $in_wish ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
		<span><?php echo $in_wish ? esc_html__( 'حذف از علاقه‌مندی', 'mobicare' ) : esc_html__( 'علاقه‌مندی', 'mobicare' ); ?></span>
	</button>
	<?php
}
add_action( 'woocommerce_after_add_to_cart_button', 'mobicare_single_wishlist_button', 20 );

/**
 * Show brand / model meta on single product.
 */
function mobicare_single_product_meta_extra() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$brand = '';
	$models = array();

	if ( taxonomy_exists( 'product_brand' ) ) {
		$terms = get_the_terms( $product->get_id(), 'product_brand' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$brand = join( '، ', wp_list_pluck( $terms, 'name' ) );
		}
	}
	if ( taxonomy_exists( 'phone_model' ) ) {
		$terms = get_the_terms( $product->get_id(), 'phone_model' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$models = wp_list_pluck( $terms, 'name' );
		}
	}

	if ( ! $brand && ! $models ) {
		// Fallback attributes.
		$brand  = $product->get_attribute( 'pa_brand' );
		$model  = $product->get_attribute( 'pa_phone-model' );
		if ( $model ) {
			$models = array_map( 'trim', explode( ',', $model ) );
		}
	}

	if ( $brand || $models ) {
		echo '<div class="mc-product-compat">';
		if ( $brand ) {
			echo '<div class="mc-product-compat__row"><span class="mc-product-compat__label">' . esc_html__( 'برند', 'mobicare' ) . '</span><span>' . esc_html( $brand ) . '</span></div>';
		}
		if ( $models ) {
			echo '<div class="mc-product-compat__row"><span class="mc-product-compat__label">' . esc_html__( 'سازگار با', 'mobicare' ) . '</span><span class="mc-product-compat__models">';
			foreach ( $models as $m ) {
				echo '<span class="mc-chip">' . esc_html( $m ) . '</span>';
			}
			echo '</span></div>';
		}
		echo '</div>';
	}
}
add_action( 'woocommerce_single_product_summary', 'mobicare_single_product_meta_extra', 25 );

/**
 * Gallery video support via product meta _mobicare_video_url.
 */
function mobicare_product_video_tab() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$url = get_post_meta( $product->get_id(), '_mobicare_video_url', true );
	if ( ! $url ) {
		return;
	}
	echo '<div class="mc-product-video">';
	echo wp_oembed_get( esc_url( $url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';
}

/**
 * Ensure account registration enabled message.
 */
function mobicare_account_registration_note() {
	if ( 'yes' !== get_option( 'woocommerce_enable_myaccount_registration' ) ) {
		return;
	}
}
