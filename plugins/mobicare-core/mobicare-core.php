<?php
/**
 * Plugin Name: MobiCare Core
 * Plugin URI: https://github.com/arshamkhakpour1391-afk/configurator
 * Description: هسته فروشگاه قاب گوشی و گلس — مدل گوشی، برند، علاقه‌مندی، مقایسه، پرسش و پاسخ، فیلترها، بنر و داشبورد مدیریت.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: MobiCare
 * Text Domain: mobicare-core
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

define( 'MOBICARE_CORE_VERSION', '1.0.0' );
define( 'MOBICARE_CORE_FILE', __FILE__ );
define( 'MOBICARE_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOBICARE_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * HPOS / cart compatibility declarations.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MOBICARE_CORE_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', MOBICARE_CORE_FILE, true );
	}
} );

/**
 * Bootstrap.
 */
final class MobiCare_Core {

	/** @var self|null */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		register_activation_hook( MOBICARE_CORE_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( MOBICARE_CORE_FILE, array( __CLASS__, 'deactivate' ) );
	}

	/**
	 * Load includes.
	 */
	private function includes() {
		$files = array(
			'includes/class-taxonomies.php',
			'includes/class-attributes.php',
			'includes/class-wishlist.php',
			'includes/class-compare.php',
			'includes/class-questions.php',
			'includes/class-filters.php',
			'includes/class-product-meta.php',
			'includes/class-promotions.php',
			'includes/class-shortcodes.php',
			'includes/admin/class-admin.php',
			'includes/admin/class-dashboard-widget.php',
			'includes/admin/class-setup-wizard.php',
		);

		foreach ( $files as $file ) {
			$path = MOBICARE_CORE_DIR . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Init modules after plugins loaded.
	 */
	public function init() {
		load_plugin_textdomain( 'mobicare-core', false, dirname( plugin_basename( MOBICARE_CORE_FILE ) ) . '/languages' );

		MobiCare_Taxonomies::init();
		MobiCare_Attributes::init();
		MobiCare_Wishlist::init();
		MobiCare_Compare::init();
		MobiCare_Questions::init();
		MobiCare_Filters::init();
		MobiCare_Product_Meta::init();
		MobiCare_Promotions::init();
		MobiCare_Shortcodes::init();

		if ( is_admin() ) {
			MobiCare_Admin::init();
			MobiCare_Dashboard_Widget::init();
			MobiCare_Setup_Wizard::init();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );

		// WooCommerce account endpoints labels.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ), 20 );

		// Ensure registration is available by default guidance (do not force silently in production without admin consent).
		add_filter( 'woocommerce_registration_error_email_exists', array( $this, 'email_exists_message' ) );
	}

	/**
	 * Frontend CSS for plugin widgets.
	 */
	public function frontend_assets() {
		wp_enqueue_style(
			'mobicare-core',
			MOBICARE_CORE_URL . 'assets/css/frontend.css',
			array(),
			MOBICARE_CORE_VERSION
		);
	}

	/**
	 * Account menu tweak.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public function account_menu_items( $items ) {
		// Labels already localized by WC Persian packs; keep structure.
		return $items;
	}

	/**
	 * Persian email exists message.
	 *
	 * @param string $msg Message.
	 * @return string
	 */
	public function email_exists_message( $msg ) {
		return __( 'این ایمیل قبلاً ثبت شده است. وارد شوید یا بازیابی رمز عبور را امتحان کنید.', 'mobicare-core' );
	}

	/**
	 * Activation: create pages, attributes scaffolding, no products.
	 */
	public static function activate() {
		// Pages.
		$pages = array(
			'wishlist' => array(
				'title'   => 'علاقه‌مندی‌ها',
				'content' => '[mobicare_wishlist]',
			),
			'compare'  => array(
				'title'   => 'مقایسه محصولات',
				'content' => '[mobicare_compare]',
			),
		);

		foreach ( $pages as $slug => $page ) {
			$existing = get_page_by_path( $slug );
			if ( ! $existing ) {
				wp_insert_post( array(
					'post_title'   => $page['title'],
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => $page['content'],
				) );
			}
		}

		// Flag for setup wizard.
		if ( ! get_option( 'mobicare_core_installed' ) ) {
			update_option( 'mobicare_core_installed', time() );
			set_transient( 'mobicare_core_activation_redirect', 1, 60 );
		}

		// Register taxonomies then flush.
		require_once MOBICARE_CORE_DIR . 'includes/class-taxonomies.php';
		MobiCare_Taxonomies::register();

		require_once MOBICARE_CORE_DIR . 'includes/class-attributes.php';
		if ( class_exists( 'WooCommerce' ) ) {
			MobiCare_Attributes::maybe_create_attributes();
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}

MobiCare_Core::instance();

/**
 * Helper: wishlist IDs (theme uses this).
 *
 * @return int[]
 */
function mobicare_wishlist_get_ids() {
	return MobiCare_Wishlist::get_ids();
}
