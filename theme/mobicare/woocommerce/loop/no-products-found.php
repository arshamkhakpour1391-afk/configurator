<?php
/**
 * No products found
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="mc-empty">
	<div class="mc-empty__icon" aria-hidden="true">📦</div>
	<h2 class="mc-empty__title"><?php esc_html_e( 'محصولی یافت نشد', 'mobicare' ); ?></h2>
	<p class="mc-empty__text">
		<?php esc_html_e( 'با فیلترهای فعلی نتیجه‌ای نیست. فیلترها را پاک کنید یا مدل گوشی دیگری را امتحان کنید.', 'mobicare' ); ?>
	</p>
	<div class="mc-empty__actions">
		<a class="mc-btn mc-btn--primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
			<?php esc_html_e( 'مشاهده همه محصولات', 'mobicare' ); ?>
		</a>
		<button type="button" class="mc-btn mc-btn--outline" data-mc-open-model-finder>
			<?php esc_html_e( 'یافتن مدل گوشی', 'mobicare' ); ?>
		</button>
	</div>
</div>
