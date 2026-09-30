<?php
/**
 * Lightweight setup checklist (no sample products)
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Setup_Wizard
 */
class MobiCare_Setup_Wizard {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_menu', array( __CLASS__, 'submenu' ) );
	}

	/**
	 * Redirect once after activation.
	 */
	public static function maybe_redirect() {
		if ( ! get_transient( 'mobicare_core_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'mobicare_core_activation_redirect' );
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=mobicare-setup' ) );
		exit;
	}

	/**
	 * Submenu.
	 */
	public static function submenu() {
		add_submenu_page(
			'mobicare',
			__( 'راه‌اندازی', 'mobicare-core' ),
			__( 'راه‌اندازی', 'mobicare-core' ),
			'manage_options',
			'mobicare-setup',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Checklist UI.
	 */
	public static function render() {
		$checks = array(
			array(
				'ok'   => class_exists( 'WooCommerce' ),
				'label'=> __( 'ووکامرس نصب و فعال است', 'mobicare-core' ),
				'link' => admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ),
			),
			array(
				'ok'   => ( 'mobicare' === get_template() ),
				'label'=> __( 'قالب MobiCare فعال است', 'mobicare-core' ),
				'link' => admin_url( 'themes.php' ),
			),
			array(
				'ok'   => (bool) wc_get_page_id( 'shop' ) && wc_get_page_id( 'shop' ) > 0,
				'label'=> __( 'برگه‌های ووکامرس (فروشگاه، سبد، تسویه، حساب) ساخته شده‌اند', 'mobicare-core' ),
				'link' => admin_url( 'admin.php?page=wc-settings&tab=advanced' ),
			),
			array(
				'ok'   => (bool) get_option( 'woocommerce_default_country' ),
				'label'=> __( 'کشور فروشگاه تنظیم شده (پیشنهاد: ایران)', 'mobicare-core' ),
				'link' => admin_url( 'admin.php?page=wc-settings&tab=general' ),
			),
			array(
				'ok'   => ! is_wp_error( get_terms( array( 'taxonomy' => 'phone_brand', 'hide_empty' => false, 'number' => 1 ) ) ) && count( get_terms( array( 'taxonomy' => 'phone_brand', 'hide_empty' => false, 'number' => 1 ) ) ) > 0,
				'label'=> __( 'حداقل یک برند گوشی تعریف شده', 'mobicare-core' ),
				'link' => admin_url( 'edit-tags.php?taxonomy=phone_brand&post_type=product' ),
			),
			array(
				'ok'   => ! is_wp_error( get_terms( array( 'taxonomy' => 'phone_model', 'hide_empty' => false, 'number' => 1 ) ) ) && count( get_terms( array( 'taxonomy' => 'phone_model', 'hide_empty' => false, 'number' => 1 ) ) ) > 0,
				'label'=> __( 'حداقل یک مدل گوشی تعریف شده', 'mobicare-core' ),
				'link' => admin_url( 'edit-tags.php?taxonomy=phone_model&post_type=product' ),
			),
			array(
				'ok'   => (int) wp_count_posts( 'product' )->publish > 0,
				'label'=> __( 'حداقل یک محصول منتشر شده (خودتان اضافه کنید — نمونه پیش‌فرض نداریم)', 'mobicare-core' ),
				'link' => admin_url( 'post-new.php?post_type=product' ),
			),
			array(
				'ok'   => (bool) has_nav_menu( 'primary' ),
				'label'=> __( 'منوی اصلی اختصاص داده شده', 'mobicare-core' ),
				'link' => admin_url( 'nav-menus.php' ),
			),
		);

		// Fix terms count when taxonomy missing.
		if ( ! taxonomy_exists( 'phone_brand' ) ) {
			$checks[4]['ok'] = false;
		}
		if ( ! taxonomy_exists( 'phone_model' ) ) {
			$checks[5]['ok'] = false;
		}
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			$checks[2]['ok'] = false;
			$checks[3]['ok'] = false;
		}

		?>
		<div class="wrap" dir="rtl" style="max-width:800px">
			<h1><?php esc_html_e( 'راه‌اندازی فروشگاه', 'mobicare-core' ); ?></h1>
			<p><?php esc_html_e( 'این چک‌لیست کمک می‌کند فروشگاه را بدون محصول نمونه و آماده کار واقعی کنید.', 'mobicare-core' ); ?></p>
			<table class="widefat striped">
				<tbody>
				<?php foreach ( $checks as $c ) : ?>
					<tr>
						<td style="width:40px;font-size:18px"><?php echo $c['ok'] ? '✅' : '⬜'; ?></td>
						<td><?php echo esc_html( $c['label'] ); ?></td>
						<td style="width:120px">
							<?php if ( ! $c['ok'] ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $c['link'] ); ?>"><?php esc_html_e( 'انجام', 'mobicare-core' ); ?></a>
							<?php else : ?>
								<span style="color:green"><?php esc_html_e( 'انجام شد', 'mobicare-core' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top:16px">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=mobicare' ) ); ?>"><?php esc_html_e( 'راهنمای کامل مدیریت', 'mobicare-core' ); ?></a>
			</p>
		</div>
		<?php
	}
}
