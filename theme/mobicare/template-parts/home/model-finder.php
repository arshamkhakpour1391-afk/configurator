<?php
/**
 * Phone model finder teaser
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="mc-section mc-model-section" aria-labelledby="mc-model-section-title">
	<div class="mc-container">
		<div class="mc-model-card">
			<div class="mc-model-card__content">
				<h2 id="mc-model-section-title" class="mc-model-card__title">
					<?php esc_html_e( 'مدل گوشی‌تان چیست؟', 'mobicare' ); ?>
				</h2>
				<p class="mc-model-card__text">
					<?php esc_html_e( 'برند و مدل را انتخاب کنید تا فقط قاب و گلس سازگار نمایش داده شود.', 'mobicare' ); ?>
				</p>
				<button type="button" class="mc-btn mc-btn--primary" data-mc-open-model-finder>
					<?php esc_html_e( 'شروع انتخاب مدل', 'mobicare' ); ?>
				</button>
			</div>
			<div class="mc-model-card__brands" id="mc-home-brand-chips" aria-hidden="true">
				<span class="mc-chip mc-chip--lg">Apple</span>
				<span class="mc-chip mc-chip--lg">Samsung</span>
				<span class="mc-chip mc-chip--lg">Xiaomi</span>
				<span class="mc-chip mc-chip--lg">Huawei</span>
				<span class="mc-chip mc-chip--lg">Nothing</span>
				<span class="mc-chip mc-chip--lg">Google</span>
			</div>
		</div>
	</div>
</section>
