<?php
/**
 * Footer template
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

$phone   = get_theme_mod( 'mobicare_phone', '' );
$email   = get_theme_mod( 'mobicare_email', '' );
$address = get_theme_mod( 'mobicare_address', '' );
$instagram = get_theme_mod( 'mobicare_instagram', '' );
$telegram  = get_theme_mod( 'mobicare_telegram', '' );
$whatsapp  = get_theme_mod( 'mobicare_whatsapp', '' );
?>

<footer class="mc-footer" role="contentinfo">
	<div class="mc-footer__main">
		<div class="mc-container mc-footer__grid">
			<div class="mc-footer__col mc-footer__about">
				<div class="mc-footer__brand">
					<?php if ( has_custom_logo() ) : ?>
						<?php the_custom_logo(); ?>
					<?php else : ?>
						<span class="mc-logo__text">
							<span class="mc-logo__mark">MC</span>
							<span class="mc-logo__name"><?php bloginfo( 'name' ); ?></span>
						</span>
					<?php endif; ?>
				</div>
				<p class="mc-footer__desc">
					<?php
					$desc = get_bloginfo( 'description' );
					echo $desc
						? esc_html( $desc )
						: esc_html__( 'فروشگاه تخصصی قاب گوشی و گلس محافظ صفحه — کیفیت، اصالت و ارسال سریع.', 'mobicare' );
					?>
				</p>
				<div class="mc-footer__social">
					<?php if ( $instagram ) : ?>
						<a href="<?php echo esc_url( $instagram ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.5 1 .4.4.7.9 1 1.5.2.4.4 1 .4 2.2.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-1 1.5-.4.4-.9.7-1.5 1-.4.2-1 .4-2.2.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.5-1-.4-.4-.7-.9-1-1.5-.2-.4-.4-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.2-1.8.4-2.2.2-.6.5-1 1-1.5.4-.4.9-.7 1.5-1 .4-.2 1-.4 2.2-.4C8.4 2.2 8.8 2.2 12 2.2m0-2.2C8.7 0 8.3 0 7 0 5.7.1 4.8.3 4 .6c-.9.3-1.6.8-2.3 1.5C1 2.8.6 3.5.3 4.4.1 5.2 0 6.1 0 7.4 0 8.7 0 9.1 0 12s0 3.3.1 4.6c.1 1.3.3 2.2.6 3 .3.9.8 1.6 1.5 2.3.7.7 1.4 1.1 2.3 1.5.8.3 1.7.5 3 .6 1.3.1 1.7.1 4.6.1s3.3 0 4.6-.1c1.3-.1 2.2-.3 3-.6.9-.3 1.6-.8 2.3-1.5.7-.7 1.1-1.4 1.5-2.3.3-.8.5-1.7.6-3 .1-1.3.1-1.7.1-4.6s0-3.3-.1-4.6c-.1-1.3-.3-2.2-.6-3-.3-.9-.8-1.6-1.5-2.3C21.2.9 20.5.5 19.6.3 18.8.1 17.9 0 16.6 0 15.3 0 14.9 0 12 0z"/><path d="M12 5.8A6.2 6.2 0 105.8 12 6.2 6.2 0 0012 5.8m0 10.2A4 4 0 1116 12a4 4 0 01-4 4.1M18.4 4.2a1.4 1.4 0 11-1.4 1.4 1.4 1.4 0 011.4-1.4"/></svg>
						</a>
					<?php endif; ?>
					<?php if ( $telegram ) : ?>
						<a href="<?php echo esc_url( $telegram ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Telegram">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.8 15.4l-.4 5.1c.5 0 .8-.2 1.1-.5l2.6-2.5 5.4 4c1 .5 1.7.3 2-.9L22.9 3.8c.3-1.3-.5-1.9-1.5-1.6L2.2 9.4C.9 9.9.9 10.6 2 11l5 1.6L18.2 6c.6-.4 1.2-.2.7.2L9.8 15.4z"/></svg>
						</a>
					<?php endif; ?>
					<?php if ( $whatsapp ) : ?>
						<a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.6-.8-1.8-.9-.3-.1-.4-.1-.6.1s-.7.9-.8 1c-.2.2-.3.2-.6.1-1.6-.8-2.6-1.4-3.7-3.2-.3-.5.3-.4.8-1.4.1-.2 0-.3-.1-.5l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.3.3-.9.9-.9 2.1s1 2.4 1.1 2.6c.1.2 1.9 2.9 4.6 4.1 1.7.7 2.1.8 2.9.6.4-.1 1.4-.6 1.6-1.1.2-.6.2-1 .1-1.1-.1 0-.3-.1-.6-.2zM12 2a10 10 0 00-8.6 15l-1.1 4 4.1-1.1A10 10 0 1012 2z"/></svg>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="mc-footer__col">
				<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
					<?php dynamic_sidebar( 'footer-1' ); ?>
				<?php else : ?>
					<h3 class="mc-footer__title"><?php esc_html_e( 'دسترسی سریع', 'mobicare' ); ?></h3>
					<?php
					wp_nav_menu( array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'mc-footer__menu',
						'fallback_cb'    => 'mobicare_fallback_menu',
						'depth'          => 1,
					) );
					?>
				<?php endif; ?>
			</div>

			<div class="mc-footer__col">
				<?php if ( is_active_sidebar( 'footer-2' ) ) : ?>
					<?php dynamic_sidebar( 'footer-2' ); ?>
				<?php else : ?>
					<h3 class="mc-footer__title"><?php esc_html_e( 'خدمات مشتریان', 'mobicare' ); ?></h3>
					<ul class="mc-footer__menu">
						<li><a href="<?php echo esc_url( home_url( '/shipping/' ) ); ?>"><?php esc_html_e( 'روش‌های ارسال', 'mobicare' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/returns/' ) ); ?>"><?php esc_html_e( 'شرایط مرجوعی', 'mobicare' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'سوالات متداول', 'mobicare' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'تماس با ما', 'mobicare' ); ?></a></li>
					</ul>
				<?php endif; ?>
			</div>

			<div class="mc-footer__col">
				<?php if ( is_active_sidebar( 'footer-3' ) ) : ?>
					<?php dynamic_sidebar( 'footer-3' ); ?>
				<?php else : ?>
					<h3 class="mc-footer__title"><?php esc_html_e( 'تماس با ما', 'mobicare' ); ?></h3>
					<ul class="mc-footer__contact">
						<?php if ( $phone ) : ?>
							<li>
								<span class="mc-footer__contact-label"><?php esc_html_e( 'تلفن', 'mobicare' ); ?></span>
								<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
							</li>
						<?php endif; ?>
						<?php if ( $email ) : ?>
							<li>
								<span class="mc-footer__contact-label"><?php esc_html_e( 'ایمیل', 'mobicare' ); ?></span>
								<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
							</li>
						<?php endif; ?>
						<?php if ( $address ) : ?>
							<li>
								<span class="mc-footer__contact-label"><?php esc_html_e( 'آدرس', 'mobicare' ); ?></span>
								<span><?php echo esc_html( $address ); ?></span>
							</li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="mc-footer__bottom">
		<div class="mc-container mc-footer__bottom-inner">
			<p class="mc-footer__copy">
				&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
				<?php bloginfo( 'name' ); ?> —
				<?php esc_html_e( 'تمامی حقوق محفوظ است.', 'mobicare' ); ?>
			</p>
			<div class="mc-footer__trust">
				<span><?php esc_html_e( 'پرداخت امن', 'mobicare' ); ?></span>
				<span><?php esc_html_e( 'ارسال سریع', 'mobicare' ); ?></span>
				<span><?php esc_html_e( 'گارانتی اصالت', 'mobicare' ); ?></span>
			</div>
		</div>
	</div>
</footer>

<!-- Mobile bottom bar -->
<nav class="mc-bottom-bar" aria-label="<?php esc_attr_e( 'ناوبری سریع موبایل', 'mobicare' ); ?>">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mc-bottom-bar__item <?php echo is_front_page() ? 'is-active' : ''; ?>">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 10.5L12 3l9 7.5V21a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1v-10.5z"/></svg>
		<span><?php esc_html_e( 'خانه', 'mobicare' ); ?></span>
	</a>
	<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>" class="mc-bottom-bar__item">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
		<span><?php esc_html_e( 'فروشگاه', 'mobicare' ); ?></span>
	</a>
	<button type="button" class="mc-bottom-bar__item" data-mc-open-model-finder>
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
		<span><?php esc_html_e( 'مدل گوشی', 'mobicare' ); ?></span>
	</button>
	<a href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>" class="mc-bottom-bar__item">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M6 6L5 2H2"/></svg>
		<span><?php esc_html_e( 'سبد', 'mobicare' ); ?></span>
		<span class="mc-badge mc-bottom-bar__badge" id="mc-cart-count-mobile" <?php echo ( isset( $cart_count ) && $cart_count ) ? '' : 'hidden'; ?>><?php echo isset( $cart_count ) ? esc_html( $cart_count ) : '0'; ?></span>
	</a>
	<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url() ); ?>" class="mc-bottom-bar__item">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
		<span><?php esc_html_e( 'حساب', 'mobicare' ); ?></span>
	</a>
</nav>

<?php wp_footer(); ?>
</body>
</html>
