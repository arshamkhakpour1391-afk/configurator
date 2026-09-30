<?php
/**
 * Bottom CTA
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
?>
<section class="mc-cta" aria-label="<?php esc_attr_e( 'شروع خرید', 'mobicare' ); ?>">
	<div class="mc-container mc-cta__inner">
		<h2 class="mc-cta__title"><?php esc_html_e( 'آماده محافظت از گوشی‌تان هستید؟', 'mobicare' ); ?></h2>
		<p class="mc-cta__text"><?php esc_html_e( 'مدل گوشی را انتخاب کنید و از بین قاب و گلس‌های سازگار بخرید.', 'mobicare' ); ?></p>
		<div class="mc-cta__actions">
			<button type="button" class="mc-btn mc-btn--primary mc-btn--lg" data-mc-open-model-finder>
				<?php esc_html_e( 'انتخاب مدل گوشی', 'mobicare' ); ?>
			</button>
			<a href="<?php echo esc_url( $shop ); ?>" class="mc-btn mc-btn--ghost mc-btn--lg">
				<?php esc_html_e( 'ورود به فروشگاه', 'mobicare' ); ?>
			</a>
		</div>
	</div>
</section>
