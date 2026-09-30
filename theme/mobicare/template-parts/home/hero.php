<?php
/**
 * Hero section
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

$title    = get_theme_mod( 'mobicare_hero_title', 'قاب و گلس مناسب گوشی شما' );
$subtitle = get_theme_mod( 'mobicare_hero_subtitle', 'محافظت حرفه‌ای، طراحی زیبا، ارسال سریع به سراسر ایران' );
$btn_text = get_theme_mod( 'mobicare_hero_btn_text', 'مشاهده فروشگاه' );
$btn_link = get_theme_mod( 'mobicare_hero_btn_link', '' );
$btn2     = get_theme_mod( 'mobicare_hero_btn2_text', 'یافتن مدل گوشی' );
$image_id = (int) get_theme_mod( 'mobicare_hero_image', 0 );

if ( ! $btn_link && function_exists( 'wc_get_page_permalink' ) ) {
	$btn_link = wc_get_page_permalink( 'shop' );
}
if ( ! $btn_link ) {
	$btn_link = home_url( '/shop/' );
}
?>
<section class="mc-hero" aria-label="<?php esc_attr_e( 'معرفی', 'mobicare' ); ?>">
	<div class="mc-container mc-hero__inner">
		<div class="mc-hero__content">
			<p class="mc-hero__eyebrow"><?php bloginfo( 'name' ); ?></p>
			<h1 class="mc-hero__title"><?php echo esc_html( $title ); ?></h1>
			<p class="mc-hero__subtitle"><?php echo esc_html( $subtitle ); ?></p>
			<div class="mc-hero__actions">
				<a class="mc-btn mc-btn--primary mc-btn--lg" href="<?php echo esc_url( $btn_link ); ?>">
					<?php echo esc_html( $btn_text ); ?>
				</a>
				<button type="button" class="mc-btn mc-btn--outline mc-btn--lg" data-mc-open-model-finder>
					<?php echo esc_html( $btn2 ); ?>
				</button>
			</div>
			<div class="mc-hero__trust">
				<span><?php esc_html_e( 'ضمانت اصالت', 'mobicare' ); ?></span>
				<span><?php esc_html_e( 'ارسال سریع', 'mobicare' ); ?></span>
				<span><?php esc_html_e( 'پشتیبانی واقعی', 'mobicare' ); ?></span>
			</div>
		</div>
		<div class="mc-hero__visual" aria-hidden="true">
			<?php if ( $image_id ) : ?>
				<?php echo wp_get_attachment_image( $image_id, 'mobicare-banner', false, array( 'class' => 'mc-hero__img' ) ); ?>
			<?php else : ?>
				<div class="mc-hero__placeholder">
					<div class="mc-hero__phone">
						<div class="mc-hero__phone-screen"></div>
					</div>
					<div class="mc-hero__float mc-hero__float--1"><?php esc_html_e( 'گلس', 'mobicare' ); ?></div>
					<div class="mc-hero__float mc-hero__float--2"><?php esc_html_e( 'قاب', 'mobicare' ); ?></div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
