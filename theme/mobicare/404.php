<?php
/**
 * 404 template
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="mc-main" role="main">
	<div class="mc-container">
		<div class="mc-empty mc-empty--404">
			<p class="mc-empty__code" aria-hidden="true">۴۰۴</p>
			<h1 class="mc-empty__title"><?php esc_html_e( 'صفحه پیدا نشد', 'mobicare' ); ?></h1>
			<p class="mc-empty__text"><?php esc_html_e( 'آدرس وارد شده اشتباه است یا صفحه حذف شده است.', 'mobicare' ); ?></p>
			<div class="mc-empty__actions">
				<a class="mc-btn mc-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php esc_html_e( 'بازگشت به خانه', 'mobicare' ); ?>
				</a>
				<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
					<a class="mc-btn mc-btn--outline" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
						<?php esc_html_e( 'مشاهده فروشگاه', 'mobicare' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</main>

<?php
get_footer();
