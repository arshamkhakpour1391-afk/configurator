<?php
/**
 * Shortcodes
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Shortcodes
 */
class MobiCare_Shortcodes {

	/**
	 * Init.
	 */
	public static function init() {
		add_shortcode( 'mobicare_wishlist', array( __CLASS__, 'wishlist' ) );
		add_shortcode( 'mobicare_compare', array( __CLASS__, 'compare' ) );
		add_shortcode( 'mobicare_product_questions', array( __CLASS__, 'questions' ) );
		add_shortcode( 'mobicare_model_finder', array( __CLASS__, 'model_finder' ) );
	}

	/**
	 * Wishlist page.
	 *
	 * @return string
	 */
	public static function wishlist() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '<p>' . esc_html__( 'ووکامرس لازم است.', 'mobicare-core' ) . '</p>';
		}
		$ids = MobiCare_Wishlist::get_ids();
		ob_start();
		if ( empty( $ids ) ) {
			echo '<div class="mc-empty"><h2 class="mc-empty__title">' . esc_html__( 'لیست علاقه‌مندی خالی است', 'mobicare-core' ) . '</h2>';
			echo '<p class="mc-empty__text">' . esc_html__( 'محصولات مورد علاقه را با ضربان قلب ذخیره کنید.', 'mobicare-core' ) . '</p>';
			echo '<a class="mc-btn mc-btn--primary" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'مشاهده فروشگاه', 'mobicare-core' ) . '</a></div>';
			return ob_get_clean();
		}
		echo '<ul class="mc-products-grid mc-wishlist-grid products">';
		foreach ( $ids as $id ) {
			$post_object = get_post( $id );
			if ( ! $post_object ) {
				continue;
			}
			setup_postdata( $GLOBALS['post'] = $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wc_get_template_part( 'content', 'product' );
		}
		wp_reset_postdata();
		echo '</ul>';
		return ob_get_clean();
	}

	/**
	 * Compare table.
	 *
	 * @return string
	 */
	public static function compare() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}
		$ids = MobiCare_Compare::get_ids();
		ob_start();
		if ( count( $ids ) < 1 ) {
			echo '<div class="mc-empty"><h2 class="mc-empty__title">' . esc_html__( 'محصولی برای مقایسه نیست', 'mobicare-core' ) . '</h2>';
			echo '<a class="mc-btn mc-btn--primary" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'بازگشت به فروشگاه', 'mobicare-core' ) . '</a></div>';
			return ob_get_clean();
		}
		$products = array();
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p ) {
				$products[] = $p;
			}
		}
		$rows = array(
			'image'  => __( 'تصویر', 'mobicare-core' ),
			'name'   => __( 'نام', 'mobicare-core' ),
			'price'  => __( 'قیمت', 'mobicare-core' ),
			'stock'  => __( 'موجودی', 'mobicare-core' ),
			'rating' => __( 'امتیاز', 'mobicare-core' ),
			'sku'    => __( 'کد کالا', 'mobicare-core' ),
		);
		echo '<div style="overflow:auto"><table class="mc-compare-table"><tbody>';
		foreach ( $rows as $key => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th>';
			foreach ( $products as $p ) {
				echo '<td>';
				switch ( $key ) {
					case 'image':
						echo $p->get_image( 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						break;
					case 'name':
						echo '<a href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( $p->get_name() ) . '</a>';
						break;
					case 'price':
						echo $p->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						break;
					case 'stock':
						echo $p->is_in_stock() ? esc_html__( 'موجود', 'mobicare-core' ) : esc_html__( 'ناموجود', 'mobicare-core' );
						break;
					case 'rating':
						echo esc_html( (string) $p->get_average_rating() );
						break;
					case 'sku':
						echo esc_html( $p->get_sku() ?: '—' );
						break;
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		return ob_get_clean();
	}

	/**
	 * Questions.
	 *
	 * @return string
	 */
	public static function questions() {
		ob_start();
		MobiCare_Questions::render( get_the_ID() );
		return ob_get_clean();
	}

	/**
	 * Model finder button.
	 *
	 * @return string
	 */
	public static function model_finder() {
		return '<button type="button" class="mc-btn mc-btn--primary" data-mc-open-model-finder>' . esc_html__( 'یافتن مدل گوشی', 'mobicare-core' ) . '</button>';
	}
}
