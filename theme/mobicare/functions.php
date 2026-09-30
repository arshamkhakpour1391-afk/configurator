<?php
/**
 * MobiCare Theme Functions
 *
 * @package MobiCare
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

define( 'MOBICARE_VERSION', '1.0.0' );
define( 'MOBICARE_DIR', get_template_directory() );
define( 'MOBICARE_URI', get_template_directory_uri() );

/**
 * Theme setup.
 */
function mobicare_setup() {
	load_theme_textdomain( 'mobicare', MOBICARE_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 240,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );

	// WooCommerce.
	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width' => 400,
		'single_image_width'    => 800,
		'product_grid'          => array(
			'default_rows'    => 4,
			'min_rows'        => 1,
			'max_rows'        => 8,
			'default_columns' => 4,
			'min_columns'     => 2,
			'max_columns'     => 5,
		),
	) );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus( array(
		'primary'   => __( 'منوی اصلی', 'mobicare' ),
		'footer'    => __( 'منوی فوتر', 'mobicare' ),
		'mobile'    => __( 'منوی موبایل', 'mobicare' ),
		'account'   => __( 'منوی حساب کاربری', 'mobicare' ),
	) );

	add_image_size( 'mobicare-product', 600, 600, true );
	add_image_size( 'mobicare-product-thumb', 300, 300, true );
	add_image_size( 'mobicare-banner', 1400, 500, true );
	add_image_size( 'mobicare-category', 400, 400, true );
}
add_action( 'after_setup_theme', 'mobicare_setup' );

/**
 * Content width.
 */
function mobicare_content_width() {
	$GLOBALS['content_width'] = 1200;
}
add_action( 'after_setup_theme', 'mobicare_content_width', 0 );

/**
 * Register widget areas.
 */
function mobicare_widgets_init() {
	$sidebars = array(
		'shop-sidebar'   => __( 'سایدبار فروشگاه', 'mobicare' ),
		'footer-1'       => __( 'فوتر ستون ۱', 'mobicare' ),
		'footer-2'       => __( 'فوتر ستون ۲', 'mobicare' ),
		'footer-3'       => __( 'فوتر ستون ۳', 'mobicare' ),
		'footer-4'       => __( 'فوتر ستون ۴', 'mobicare' ),
		'product-sidebar'=> __( 'سایدبار محصول', 'mobicare' ),
	);

	foreach ( $sidebars as $id => $name ) {
		register_sidebar( array(
			'name'          => $name,
			'id'            => $id,
			'description'   => '',
			'before_widget' => '<div id="%1$s" class="widget mc-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title mc-widget__title">',
			'after_title'   => '</h3>',
		) );
	}
}
add_action( 'widgets_init', 'mobicare_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function mobicare_scripts() {
	// Vazirmatn font (self-hosted subset via Google Fonts CDN with display=swap).
	wp_enqueue_style(
		'mobicare-fonts',
		'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'mobicare-main',
		MOBICARE_URI . '/assets/css/main.css',
		array( 'mobicare-fonts' ),
		MOBICARE_VERSION
	);

	wp_enqueue_style(
		'mobicare-woocommerce',
		MOBICARE_URI . '/assets/css/woocommerce.css',
		array( 'mobicare-main' ),
		MOBICARE_VERSION
	);

	wp_enqueue_style(
		'mobicare-dark',
		MOBICARE_URI . '/assets/css/dark-mode.css',
		array( 'mobicare-main' ),
		MOBICARE_VERSION
	);

	wp_enqueue_script(
		'mobicare-main',
		MOBICARE_URI . '/assets/js/main.js',
		array(),
		MOBICARE_VERSION,
		array( 'strategy' => 'defer', 'in_footer' => true )
	);

	wp_enqueue_script(
		'mobicare-shop',
		MOBICARE_URI . '/assets/js/shop.js',
		array( 'mobicare-main' ),
		MOBICARE_VERSION,
		array( 'strategy' => 'defer', 'in_footer' => true )
	);

	$cart_count = 0;
	if ( function_exists( 'WC' ) && WC()->cart ) {
		$cart_count = WC()->cart->get_cart_contents_count();
	}

	wp_localize_script( 'mobicare-main', 'mobicareData', array(
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'restUrl'      => esc_url_raw( rest_url( 'mobicare/v1/' ) ),
		'nonce'        => wp_create_nonce( 'mobicare_nonce' ),
		'wcNonce'      => wp_create_nonce( 'wc_store_api' ),
		'cartUrl'      => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
		'checkoutUrl'  => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
		'shopUrl'      => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
		'accountUrl'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ),
		'wishlistUrl'  => home_url( '/wishlist/' ),
		'cartCount'    => $cart_count,
		'isLoggedIn'   => is_user_logged_in(),
		'currency'     => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'تومان',
		'i18n'         => array(
			'addedToCart'     => __( 'به سبد خرید اضافه شد', 'mobicare' ),
			'addedToWishlist' => __( 'به علاقه‌مندی‌ها اضافه شد', 'mobicare' ),
			'removedWishlist' => __( 'از علاقه‌مندی‌ها حذف شد', 'mobicare' ),
			'error'           => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'mobicare' ),
			'loading'         => __( 'در حال بارگذاری...', 'mobicare' ),
			'noResults'       => __( 'نتیجه‌ای یافت نشد', 'mobicare' ),
			'searchPlaceholder' => __( 'جستجوی قاب، گلس، مدل گوشی...', 'mobicare' ),
			'outOfStock'      => __( 'ناموجود', 'mobicare' ),
			'inStock'         => __( 'موجود', 'mobicare' ),
			'viewCart'        => __( 'مشاهده سبد', 'mobicare' ),
			'continue'        => __( 'ادامه خرید', 'mobicare' ),
			'selectModel'     => __( 'مدل گوشی خود را انتخاب کنید', 'mobicare' ),
			'close'           => __( 'بستن', 'mobicare' ),
			'filter'          => __( 'فیلتر', 'mobicare' ),
			'sort'            => __( 'مرتب‌سازی', 'mobicare' ),
			'apply'           => __( 'اعمال', 'mobicare' ),
			'clear'           => __( 'پاک کردن', 'mobicare' ),
			'darkMode'        => __( 'حالت تاریک', 'mobicare' ),
			'lightMode'       => __( 'حالت روشن', 'mobicare' ),
			'required'        => __( 'این فیلد الزامی است', 'mobicare' ),
			'invalidEmail'    => __( 'ایمیل معتبر نیست', 'mobicare' ),
			'passwordMismatch'=> __( 'رمز عبور و تکرار آن یکسان نیستند', 'mobicare' ),
		),
	) );
}
add_action( 'wp_enqueue_scripts', 'mobicare_scripts' );

/**
 * Admin styles for mobile-friendly product editing hints.
 */
function mobicare_admin_assets( $hook ) {
	wp_enqueue_style(
		'mobicare-admin',
		MOBICARE_URI . '/assets/css/admin.css',
		array(),
		MOBICARE_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'mobicare_admin_assets' );

/**
 * Body classes.
 */
function mobicare_body_classes( $classes ) {
	$classes[] = 'mobicare-theme';
	$classes[] = 'rtl';

	if ( is_singular( 'product' ) ) {
		$classes[] = 'mc-single-product';
	}
	if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
		$classes[] = 'mc-shop-archive';
	}
	if ( is_front_page() ) {
		$classes[] = 'mc-home';
	}

	return $classes;
}
add_filter( 'body_class', 'mobicare_body_classes' );

/**
 * Force RTL for frontend.
 */
function mobicare_force_rtl() {
	global $wp_locale;
	if ( ! is_admin() ) {
		$wp_locale->text_direction = 'rtl';
	}
}
add_action( 'init', 'mobicare_force_rtl' );

/**
 * Include theme modules.
 */
$mobicare_includes = array(
	'/inc/template-tags.php',
	'/inc/woocommerce.php',
	'/inc/customizer.php',
	'/inc/seo.php',
	'/inc/performance.php',
	'/inc/ajax.php',
);

foreach ( $mobicare_includes as $file ) {
	$path = MOBICARE_DIR . $file;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

/**
 * Fallback menu if no menu assigned.
 */
function mobicare_fallback_menu() {
	echo '<ul class="mc-nav__list">';
	echo '<li class="mc-nav__item"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'خانه', 'mobicare' ) . '</a></li>';
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		echo '<li class="mc-nav__item"><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'فروشگاه', 'mobicare' ) . '</a></li>';
	}
	echo '<li class="mc-nav__item"><a href="' . esc_url( home_url( '/about/' ) ) . '">' . esc_html__( 'درباره ما', 'mobicare' ) . '</a></li>';
	echo '<li class="mc-nav__item"><a href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html__( 'تماس با ما', 'mobicare' ) . '</a></li>';
	echo '</ul>';
}

/**
 * Excerpt length.
 */
function mobicare_excerpt_length( $length ) {
	return 20;
}
add_filter( 'excerpt_length', 'mobicare_excerpt_length' );

/**
 * Theme activation: set defaults (no sample products).
 */
function mobicare_theme_activation() {
	// Create wishlist page if missing.
	$wishlist = get_page_by_path( 'wishlist' );
	if ( ! $wishlist ) {
		wp_insert_post( array(
			'post_title'   => 'علاقه‌مندی‌ها',
			'post_name'    => 'wishlist',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '[mobicare_wishlist]',
		) );
	}

	// Create compare page if missing.
	$compare = get_page_by_path( 'compare' );
	if ( ! $compare ) {
		wp_insert_post( array(
			'post_title'   => 'مقایسه محصولات',
			'post_name'    => 'compare',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '[mobicare_compare]',
		) );
	}

	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'mobicare_theme_activation' );

/**
 * Disable WooCommerce default styles (we ship our own).
 */
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/**
 * Change currency position and Persian number helpers.
 */
function mobicare_format_price_html( $price, $product = null ) {
	return $price;
}
add_filter( 'woocommerce_get_price_html', 'mobicare_format_price_html', 10, 2 );

/**
 * Login with email only (no confirmation code email required for basic WP auth).
 * WordPress already supports email login via username_or_email field.
 */
function mobicare_allow_email_login( $user, $username, $password ) {
	if ( is_a( $user, 'WP_User' ) ) {
		return $user;
	}
	if ( ! empty( $username ) && is_email( $username ) ) {
		$user_obj = get_user_by( 'email', $username );
		if ( $user_obj ) {
			$username = $user_obj->user_login;
		}
	}
	return wp_authenticate_username_password( null, $username, $password );
}
remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
add_filter( 'authenticate', 'mobicare_allow_email_login', 20, 3 );
