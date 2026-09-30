<?php
/**
 * Trust features
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

$features = array(
	array(
		'icon'  => 'shield',
		'title' => __( 'ضمانت اصالت کالا', 'mobicare' ),
		'text'  => __( 'تمام محصولات با ضمانت کیفیت عرضه می‌شوند.', 'mobicare' ),
	),
	array(
		'icon'  => 'truck',
		'title' => __( 'ارسال سریع', 'mobicare' ),
		'text'  => __( 'پردازش سفارش در کوتاه‌ترین زمان ممکن.', 'mobicare' ),
	),
	array(
		'icon'  => 'return',
		'title' => __( 'مرجوعی آسان', 'mobicare' ),
		'text'  => __( 'در صورت مغایرت، امکان مرجوعی طبق قوانین فروشگاه.', 'mobicare' ),
	),
	array(
		'icon'  => 'support',
		'title' => __( 'پشتیبانی خرید', 'mobicare' ),
		'text'  => __( 'راهنمایی برای انتخاب قاب و گلس مناسب مدل شما.', 'mobicare' ),
	),
);
?>
<section class="mc-features" aria-label="<?php esc_attr_e( 'مزایا', 'mobicare' ); ?>">
	<div class="mc-container">
		<div class="mc-features__grid">
			<?php foreach ( $features as $f ) : ?>
				<div class="mc-feature">
					<div class="mc-feature__icon" aria-hidden="true">
						<?php if ( 'shield' === $f['icon'] ) : ?>
							<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/></svg>
						<?php elseif ( 'truck' === $f['icon'] ) : ?>
							<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
						<?php elseif ( 'return' === $f['icon'] ) : ?>
							<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 101-4.5M3 3v6h6"/></svg>
						<?php else : ?>
							<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4z"/></svg>
						<?php endif; ?>
					</div>
					<h3 class="mc-feature__title"><?php echo esc_html( $f['title'] ); ?></h3>
					<p class="mc-feature__text"><?php echo esc_html( $f['text'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
