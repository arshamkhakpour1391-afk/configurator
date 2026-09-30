<?php
/**
 * Header template
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

$announcement = get_theme_mod( 'mobicare_announcement_text', '' );
$announcement_link = get_theme_mod( 'mobicare_announcement_link', '' );
$phone = get_theme_mod( 'mobicare_phone', '' );
$cart_count = 0;
$wishlist_count = 0;

if ( function_exists( 'WC' ) && WC()->cart ) {
	$cart_count = WC()->cart->get_cart_contents_count();
}
if ( function_exists( 'mobicare_get_wishlist_count' ) ) {
	$wishlist_count = mobicare_get_wishlist_count();
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
	<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
	<script>
		(function(){try{var t=localStorage.getItem('mc-theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.setAttribute('data-theme','dark');document.documentElement.classList.add('mc-dark');}}catch(e){}})();
	</script>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="mc-skip-link" href="#main"><?php esc_html_e( 'پرش به محتوا', 'mobicare' ); ?></a>

<?php if ( $announcement ) : ?>
<div class="mc-announce" role="region" aria-label="<?php esc_attr_e( 'اعلان', 'mobicare' ); ?>">
	<div class="mc-container mc-announce__inner">
		<?php if ( $announcement_link ) : ?>
			<a href="<?php echo esc_url( $announcement_link ); ?>" class="mc-announce__text"><?php echo esc_html( $announcement ); ?></a>
		<?php else : ?>
			<span class="mc-announce__text"><?php echo esc_html( $announcement ); ?></span>
		<?php endif; ?>
		<button type="button" class="mc-announce__close" aria-label="<?php esc_attr_e( 'بستن اعلان', 'mobicare' ); ?>" data-mc-dismiss-announce>×</button>
	</div>
</div>
<?php endif; ?>

<header class="mc-header" id="mc-header" role="banner">
	<div class="mc-header__top">
		<div class="mc-container mc-header__top-inner">
			<?php if ( $phone ) : ?>
				<a class="mc-header__phone" href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
					<span><?php echo esc_html( $phone ); ?></span>
				</a>
			<?php endif; ?>
			<div class="mc-header__top-actions">
				<button type="button" class="mc-theme-toggle" id="mc-theme-toggle" aria-label="<?php esc_attr_e( 'تغییر حالت روشن/تاریک', 'mobicare' ); ?>">
					<span class="mc-theme-toggle__sun" aria-hidden="true">☀</span>
					<span class="mc-theme-toggle__moon" aria-hidden="true">☾</span>
				</button>
			</div>
		</div>
	</div>

	<div class="mc-header__main">
		<div class="mc-container mc-header__main-inner">
			<button type="button" class="mc-nav-toggle" id="mc-nav-toggle" aria-expanded="false" aria-controls="mc-mobile-nav" aria-label="<?php esc_attr_e( 'باز کردن منو', 'mobicare' ); ?>">
				<span class="mc-nav-toggle__bar"></span>
				<span class="mc-nav-toggle__bar"></span>
				<span class="mc-nav-toggle__bar"></span>
			</button>

			<div class="mc-logo">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mc-logo__text" rel="home">
						<span class="mc-logo__mark">MC</span>
						<span class="mc-logo__name"><?php bloginfo( 'name' ); ?></span>
					</a>
				<?php endif; ?>
			</div>

			<div class="mc-search" role="search">
				<form class="mc-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" id="mc-search-form">
					<label class="screen-reader-text" for="mc-search-input"><?php esc_html_e( 'جستجو', 'mobicare' ); ?></label>
					<input
						type="search"
						id="mc-search-input"
						class="mc-search__input"
						name="s"
						placeholder="<?php esc_attr_e( 'جستجوی قاب، گلس، مدل گوشی...', 'mobicare' ); ?>"
						value="<?php echo esc_attr( get_search_query() ); ?>"
						autocomplete="off"
						aria-autocomplete="list"
						aria-controls="mc-search-suggest"
						aria-expanded="false"
					>
					<input type="hidden" name="post_type" value="product">
					<button type="submit" class="mc-search__btn" aria-label="<?php esc_attr_e( 'جستجو', 'mobicare' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
					</button>
				</form>
				<div id="mc-search-suggest" class="mc-search__suggest" hidden role="listbox" aria-label="<?php esc_attr_e( 'پیشنهادات جستجو', 'mobicare' ); ?>"></div>
			</div>

			<div class="mc-header__actions">
				<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url() ); ?>" class="mc-header__action" aria-label="<?php esc_attr_e( 'حساب کاربری', 'mobicare' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
					<span class="mc-header__action-label"><?php echo is_user_logged_in() ? esc_html__( 'حساب من', 'mobicare' ) : esc_html__( 'ورود', 'mobicare' ); ?></span>
				</a>

				<a href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>" class="mc-header__action" aria-label="<?php esc_attr_e( 'علاقه‌مندی‌ها', 'mobicare' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
					<span class="mc-badge" id="mc-wishlist-count" data-count="<?php echo esc_attr( $wishlist_count ); ?>" <?php echo $wishlist_count ? '' : 'hidden'; ?>><?php echo esc_html( $wishlist_count ); ?></span>
					<span class="mc-header__action-label"><?php esc_html_e( 'علاقه‌مندی', 'mobicare' ); ?></span>
				</a>

				<a href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>" class="mc-header__action mc-header__cart" aria-label="<?php esc_attr_e( 'سبد خرید', 'mobicare' ); ?>" id="mc-cart-link">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M6 6L5 2H2"/></svg>
					<span class="mc-badge" id="mc-cart-count" data-count="<?php echo esc_attr( $cart_count ); ?>" <?php echo $cart_count ? '' : 'hidden'; ?>><?php echo esc_html( $cart_count ); ?></span>
					<span class="mc-header__action-label"><?php esc_html_e( 'سبد', 'mobicare' ); ?></span>
				</a>
			</div>
		</div>
	</div>

	<nav class="mc-nav" id="mc-primary-nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'mobicare' ); ?>">
		<div class="mc-container">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'mc-nav__list',
				'fallback_cb'    => 'mobicare_fallback_menu',
				'depth'          => 3,
			) );
			?>
		</div>
	</nav>
</header>

<!-- Mobile drawer -->
<div class="mc-drawer" id="mc-mobile-nav" hidden aria-hidden="true">
	<div class="mc-drawer__overlay" data-mc-close-drawer></div>
	<div class="mc-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'منوی موبایل', 'mobicare' ); ?>">
		<div class="mc-drawer__head">
			<span class="mc-drawer__title"><?php bloginfo( 'name' ); ?></span>
			<button type="button" class="mc-drawer__close" data-mc-close-drawer aria-label="<?php esc_attr_e( 'بستن', 'mobicare' ); ?>">×</button>
		</div>
		<div class="mc-drawer__body">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'mobile',
				'container'      => false,
				'menu_class'     => 'mc-drawer__menu',
				'fallback_cb'    => 'mobicare_fallback_menu',
				'depth'          => 2,
			) );
			?>
			<div class="mc-drawer__quick">
				<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>" class="mc-btn mc-btn--primary mc-btn--block">
					<?php esc_html_e( 'مشاهده فروشگاه', 'mobicare' ); ?>
				</a>
				<button type="button" class="mc-btn mc-btn--outline mc-btn--block" data-mc-open-model-finder>
					<?php esc_html_e( 'یافتن مدل گوشی', 'mobicare' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Toast container -->
<div id="mc-toasts" class="mc-toasts" aria-live="polite" aria-atomic="true"></div>

<!-- Model finder modal shell -->
<div id="mc-model-finder" class="mc-modal" hidden aria-hidden="true">
	<div class="mc-modal__overlay" data-mc-close-modal></div>
	<div class="mc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mc-model-finder-title">
		<div class="mc-modal__head">
			<h2 id="mc-model-finder-title" class="mc-modal__title"><?php esc_html_e( 'یافتن محصولات سازگار با گوشی شما', 'mobicare' ); ?></h2>
			<button type="button" class="mc-modal__close" data-mc-close-modal aria-label="<?php esc_attr_e( 'بستن', 'mobicare' ); ?>">×</button>
		</div>
		<div class="mc-modal__body" id="mc-model-finder-body">
			<p class="mc-muted"><?php esc_html_e( 'برند گوشی را انتخاب کنید، سپس مدل را برگزینید.', 'mobicare' ); ?></p>
			<div class="mc-model-finder__brands" id="mc-model-brands"></div>
			<div class="mc-model-finder__models" id="mc-model-models" hidden></div>
		</div>
	</div>
</div>
