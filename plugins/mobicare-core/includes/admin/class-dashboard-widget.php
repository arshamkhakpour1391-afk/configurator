<?php
/**
 * Real store stats dashboard widget
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Dashboard_Widget
 */
class MobiCare_Dashboard_Widget {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register widget.
	 */
	public static function register() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'mobicare_store_stats',
			__( 'آمار فروشگاه MobiCare', 'mobicare-core' ),
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render real stats.
	 */
	public static function render() {
		$today_orders  = self::count_orders_today();
		$today_revenue = self::revenue_today();
		$pending       = self::count_orders_by_status( array( 'wc-processing', 'wc-on-hold', 'wc-pending' ) );
		$low_stock     = self::count_low_stock();
		$reviews       = (int) get_comments( array(
			'status'    => 'hold',
			'post_type' => 'product',
			'count'     => true,
		) );
		$questions     = self::count_pending_questions();

		?>
		<div class="mobicare-dash-stats" dir="rtl">
			<div class="mobicare-dash-stat">
				<strong><?php echo esc_html( (string) $today_orders ); ?></strong>
				<span><?php esc_html_e( 'سفارش امروز', 'mobicare-core' ); ?></span>
			</div>
			<div class="mobicare-dash-stat">
				<strong><?php echo wp_kses_post( wc_price( $today_revenue ) ); ?></strong>
				<span><?php esc_html_e( 'فروش امروز', 'mobicare-core' ); ?></span>
			</div>
			<div class="mobicare-dash-stat">
				<strong><?php echo esc_html( (string) $pending ); ?></strong>
				<span><?php esc_html_e( 'سفارش در انتظار', 'mobicare-core' ); ?></span>
			</div>
			<div class="mobicare-dash-stat">
				<strong><?php echo esc_html( (string) $low_stock ); ?></strong>
				<span><?php esc_html_e( 'موجودی کم', 'mobicare-core' ); ?></span>
			</div>
			<div class="mobicare-dash-stat">
				<strong><?php echo esc_html( (string) $reviews ); ?></strong>
				<span><?php esc_html_e( 'نظر در انتظار تأیید', 'mobicare-core' ); ?></span>
			</div>
			<div class="mobicare-dash-stat">
				<strong><?php echo esc_html( (string) $questions ); ?></strong>
				<span><?php esc_html_e( 'پرسش بدون پاسخ', 'mobicare-core' ); ?></span>
			</div>
		</div>
		<p style="margin-top:12px">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>"><?php esc_html_e( 'سفارش‌ها', 'mobicare-core' ); ?></a>
			|
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><?php esc_html_e( 'محصولات', 'mobicare-core' ); ?></a>
			|
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mobicare' ) ); ?>"><?php esc_html_e( 'راهنما', 'mobicare-core' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Orders today count.
	 *
	 * @return int
	 */
	private static function count_orders_today() {
		$args = array(
			'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending' ),
			'date_created' => '>=' . gmdate( 'Y-m-d 00:00:00' ),
			'return'       => 'ids',
			'limit'        => -1,
		);
		$orders = wc_get_orders( $args );
		return is_array( $orders ) ? count( $orders ) : 0;
	}

	/**
	 * Revenue today.
	 *
	 * @return float
	 */
	private static function revenue_today() {
		$orders = wc_get_orders( array(
			'status'       => array( 'wc-processing', 'wc-completed' ),
			'date_created' => '>=' . gmdate( 'Y-m-d 00:00:00' ),
			'limit'        => -1,
			'return'       => 'objects',
		) );
		$total = 0.0;
		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				$total += (float) $order->get_total();
			}
		}
		return $total;
	}

	/**
	 * Count by statuses.
	 *
	 * @param array $statuses Statuses.
	 * @return int
	 */
	private static function count_orders_by_status( $statuses ) {
		$orders = wc_get_orders( array(
			'status' => $statuses,
			'limit'  => -1,
			'return' => 'ids',
		) );
		return is_array( $orders ) ? count( $orders ) : 0;
	}

	/**
	 * Low stock count.
	 *
	 * @return int
	 */
	private static function count_low_stock() {
		$threshold = (int) get_option( 'mobicare_low_stock_threshold', 5 );
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => '_manage_stock',
					'value'   => 'yes',
				),
				array(
					'key'     => '_stock',
					'value'   => $threshold,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => '_stock',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		) );
		return (int) $q->found_posts;
	}

	/**
	 * Pending questions.
	 *
	 * @return int
	 */
	private static function count_pending_questions() {
		$q = new WP_Query( array(
			'post_type'      => 'mc_question',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_q_status',
					'value' => 'pending',
				),
			),
		) );
		return (int) $q->found_posts;
	}
}
