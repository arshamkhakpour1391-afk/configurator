<?php
/**
 * Admin menus and notices
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Admin
 */
class MobiCare_Admin {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'product_tab' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Top-level menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'MobiCare', 'mobicare-core' ),
			__( 'MobiCare', 'mobicare-core' ),
			'manage_woocommerce',
			'mobicare',
			array( __CLASS__, 'render_guide' ),
			'dashicons-smartphone',
			56
		);

		add_submenu_page(
			'mobicare',
			__( 'راهنمای مدیریت', 'mobicare-core' ),
			__( 'راهنمای مدیریت', 'mobicare-core' ),
			'manage_woocommerce',
			'mobicare',
			array( __CLASS__, 'render_guide' )
		);

		add_submenu_page(
			'mobicare',
			__( 'تنظیمات فروشگاه', 'mobicare-core' ),
			__( 'تنظیمات', 'mobicare-core' ),
			'manage_options',
			'mobicare-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'mobicare' ) && 'index.php' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'mobicare-core-admin',
			MOBICARE_CORE_URL . 'assets/css/admin.css',
			array(),
			MOBICARE_CORE_VERSION
		);
	}

	/**
	 * Notices.
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'افزونه MobiCare Core به WooCommerce نیاز دارد. لطفاً ووکامرس را نصب و فعال کنید.', 'mobicare-core' );
			echo '</p></div>';
		}
		$theme = wp_get_theme();
		if ( 'mobicare' !== $theme->get_template() && 'MobiCare' !== $theme->get( 'Name' ) ) {
			// Soft notice only on our pages.
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && false !== strpos( (string) $screen->id, 'mobicare' ) ) {
				echo '<div class="notice notice-warning"><p>';
				echo esc_html__( 'برای ظاهر کامل فروشگاه، قالب MobiCare را فعال کنید.', 'mobicare-core' );
				echo '</p></div>';
			}
		}
	}

	/**
	 * Guide page — admin how-to in Persian.
	 */
	public static function render_guide() {
		?>
		<div class="wrap mobicare-admin-wrap" dir="rtl" style="max-width:960px">
			<h1><?php esc_html_e( 'مدیریت فروشگاه MobiCare', 'mobicare-core' ); ?></h1>
			<p><?php esc_html_e( 'این فروشگاه محصول پیش‌فرض ندارد. همه کالاها را خودتان اضافه می‌کنید. از موبایل هم می‌توانید مدیریت کنید (پیشخوان وردپرس موبایل‌فرندلی + اپ رسمی وردپرس).', 'mobicare-core' ); ?></p>

			<div class="mobicare-admin-box">
				<h2>۱) افزودن برند و مدل گوشی</h2>
				<ol>
					<li><?php esc_html_e( 'محصولات → برند گوشی: مثلاً Apple، Samsung، Xiaomi', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'محصولات → مدل گوشی: مثلاً iPhone 16 Pro Max — و در فیلد «برند گوشی» برند مربوط را انتخاب کنید', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'مدل‌های جدید را هر زمان بدون کدنویسی اضافه کنید', 'mobicare-core' ); ?></li>
				</ol>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=phone_brand&post_type=product' ) ); ?>"><?php esc_html_e( 'برند گوشی', 'mobicare-core' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=phone_model&post_type=product' ) ); ?>"><?php esc_html_e( 'مدل گوشی', 'mobicare-core' ); ?></a>
				</p>
			</div>

			<div class="mobicare-admin-box">
				<h2>۲) افزودن محصول (قاب / گلس)</h2>
				<ol>
					<li><?php esc_html_e( 'محصولات → افزودن جدید', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'عنوان فارسی، توضیح کوتاه، توضیح کامل، تصاویر گالری', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'قیمت عادی و قیمت فروش (تخفیف) + زمان‌بندی فروش در صورت نیاز', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'موجودی / SKU / وضعیت انبار', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'دسته‌بندی (قاب، گلس، ...)، برند محصول، مدل گوشی سازگار', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'ویژگی‌ها: رنگ، متریال، MagSafe، نوع قاب/گلس، گارانتی', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'انتشار', 'mobicare-core' ); ?></li>
				</ol>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>"><?php esc_html_e( 'افزودن محصول', 'mobicare-core' ); ?></a></p>
			</div>

			<div class="mobicare-admin-box">
				<h2>۳) سفارش‌ها</h2>
				<p><?php esc_html_e( 'ووکامرس → سفارش‌ها: مشاهده، تغییر وضعیت (در حال انجام، تکمیل‌شده، لغو)، یادداشت، بازپرداخت در صورت پشتیبانی درگاه.', 'mobicare-core' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>"><?php esc_html_e( 'سفارش‌ها', 'mobicare-core' ); ?></a>
				<?php if ( function_exists( 'wc_get_page_screen_id' ) ) : ?>
					<!-- HPOS orders -->
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>"><?php esc_html_e( 'سفارش‌ها (جدید)', 'mobicare-core' ); ?></a>
				<?php endif; ?>
				</p>
			</div>

			<div class="mobicare-admin-box">
				<h2>۴) کوپن، ارسال، درگاه پرداخت</h2>
				<ul>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_coupon' ) ); ?>"><?php esc_html_e( 'کوپن‌ها', 'mobicare-core' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>"><?php esc_html_e( 'ارسال', 'mobicare-core' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>"><?php esc_html_e( 'پرداخت (درگاه واقعی را اینجا وصل کنید)', 'mobicare-core' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ); ?>"><?php esc_html_e( 'عمومی: واحد پول تومان/ریال، کشور ایران', 'mobicare-core' ); ?></a></li>
				</ul>
			</div>

			<div class="mobicare-admin-box">
				<h2>۵) ظاهر فروشگاه</h2>
				<ul>
					<li><a href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>"><?php esc_html_e( 'سفارشی‌سازی: لوگو، هیرو، بنر، اعلان، تماس، FAQ', 'mobicare-core' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'منوها', 'mobicare-core' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mc_promotion' ) ); ?>"><?php esc_html_e( 'پروموشن‌ها', 'mobicare-core' ); ?></a></li>
				</ul>
			</div>

			<div class="mobicare-admin-box">
				<h2>۶) نظرات و پرسش‌ها</h2>
				<ul>
					<li><a href="<?php echo esc_url( admin_url( 'edit-comments.php' ) ); ?>"><?php esc_html_e( 'نظرات / امتیازها', 'mobicare-core' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mc_question' ) ); ?>"><?php esc_html_e( 'پرسش‌های محصول', 'mobicare-core' ); ?></a></li>
				</ul>
			</div>

			<div class="mobicare-admin-box">
				<h2>۷) مدیریت از موبایل</h2>
				<ol>
					<li><?php esc_html_e( 'از مرورگر موبایل به /wp-admin وارد شوید — پیشخوان واکنش‌گرا است', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'یا اپ رسمی WordPress را نصب کنید و سایت را اضافه کنید', 'mobicare-core' ); ?></li>
					<li><?php esc_html_e( 'برای سفارش‌ها و موجودی، ووکامرس موبایل هم قابل استفاده است', 'mobicare-core' ); ?></li>
				</ol>
			</div>

			<div class="mobicare-admin-box">
				<h2>۸) ورود مشتریان فقط با ایمیل + رمز</h2>
				<p><?php esc_html_e( 'ووکامرس → تنظیمات → حساب‌ها و حریم خصوصی: ثبت‌نام در برگه حساب من را فعال کنید. ورود با ایمیل و رمز عبور است — بدون کد یکبارمصرف اجباری. تأیید ایمیل وردپرس را می‌توانید خاموش نگه دارید مگر اینکه افزونه امنیتی جدا بخواهید.', 'mobicare-core' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=account' ) ); ?>"><?php esc_html_e( 'تنظیمات حساب', 'mobicare-core' ); ?></a></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Settings.
	 */
	public static function register_settings() {
		register_setting( 'mobicare_settings', 'mobicare_low_stock_threshold', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 5,
		) );
	}

	/**
	 * Settings page.
	 */
	public static function render_settings() {
		if ( isset( $_POST['mobicare_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mobicare_settings_nonce'] ) ), 'mobicare_settings' ) ) {
			update_option( 'mobicare_low_stock_threshold', isset( $_POST['mobicare_low_stock_threshold'] ) ? absint( $_POST['mobicare_low_stock_threshold'] ) : 5 );
			echo '<div class="updated"><p>' . esc_html__( 'ذخیره شد.', 'mobicare-core' ) . '</p></div>';
		}
		$threshold = (int) get_option( 'mobicare_low_stock_threshold', 5 );
		?>
		<div class="wrap" dir="rtl">
			<h1><?php esc_html_e( 'تنظیمات MobiCare', 'mobicare-core' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'mobicare_settings', 'mobicare_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'آستانه موجودی کم', 'mobicare-core' ); ?></th>
						<td>
							<input type="number" name="mobicare_low_stock_threshold" value="<?php echo esc_attr( $threshold ); ?>" min="0" step="1">
							<p class="description"><?php esc_html_e( 'برای ویجت داشبورد و هشدار موجودی کم.', 'mobicare-core' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'ذخیره', 'mobicare-core' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Extra product data tab label only (fields in product meta).
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function product_tab( $tabs ) {
		return $tabs;
	}
}
