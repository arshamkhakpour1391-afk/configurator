<?php
/**
 * FAQ accordion
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

$faqs = array();
for ( $i = 1; $i <= 6; $i++ ) {
	$q = get_theme_mod( "mobicare_faq_q_{$i}", '' );
	$a = get_theme_mod( "mobicare_faq_a_{$i}", '' );
	if ( $q && $a ) {
		$faqs[] = array( 'q' => $q, 'a' => $a );
	}
}

// Sensible defaults only if admin hasn't set any — store-related, not lorem.
if ( empty( $faqs ) ) {
	$faqs = array(
		array(
			'q' => 'چطور قاب مناسب گوشی‌ام را پیدا کنم؟',
			'a' => 'از دکمه «یافتن مدل گوشی» برند و مدل را انتخاب کنید تا فقط محصولات سازگار نمایش داده شوند.',
		),
		array(
			'q' => 'گلس‌ها اصل هستند؟',
			'a' => 'بله. محصولات با مشخصات درج‌شده در صفحه کالا عرضه می‌شوند و می‌توانید قبل از خرید مشخصات را بررسی کنید.',
		),
		array(
			'q' => 'هزینه و زمان ارسال چقدر است؟',
			'a' => 'هزینه و روش ارسال در مرحله تسویه‌حساب بر اساس آدرس شما محاسبه و نمایش داده می‌شود.',
		),
		array(
			'q' => 'آیا امکان مرجوعی وجود دارد؟',
			'a' => 'در صورت مغایرت یا مشکل کیفی طبق شرایط مرجوعی فروشگاه اقدام کنید. جزئیات در صفحه شرایط مرجوعی آمده است.',
		),
	);
}
?>
<section class="mc-section mc-faq-section" aria-labelledby="mc-faq-title">
	<div class="mc-container mc-faq-layout">
		<div class="mc-faq-intro">
			<h2 id="mc-faq-title" class="mc-section__title"><?php esc_html_e( 'سوالات متداول', 'mobicare' ); ?></h2>
			<p class="mc-muted"><?php esc_html_e( 'پاسخ پرسش‌های رایج خریداران قاب و گلس.', 'mobicare' ); ?></p>
		</div>
		<div class="mc-faq-list">
			<?php foreach ( $faqs as $i => $faq ) : ?>
				<details class="mc-faq-item" <?php echo 0 === $i ? 'open' : ''; ?>>
					<summary class="mc-faq-item__q"><?php echo esc_html( $faq['q'] ); ?></summary>
					<div class="mc-faq-item__a">
						<p><?php echo esc_html( $faq['a'] ); ?></p>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
