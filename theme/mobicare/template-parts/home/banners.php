<?php
/**
 * Promo banners
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

$banners = array();
for ( $i = 1; $i <= 3; $i++ ) {
	$img = (int) get_theme_mod( "mobicare_banner_{$i}_image", 0 );
	$title = get_theme_mod( "mobicare_banner_{$i}_title", '' );
	$link = get_theme_mod( "mobicare_banner_{$i}_link", '' );
	if ( $img || $title ) {
		$banners[] = compact( 'img', 'title', 'link' );
	}
}

if ( empty( $banners ) ) {
	return;
}
?>
<section class="mc-section mc-banners" aria-label="<?php esc_attr_e( 'پیشنهادها', 'mobicare' ); ?>">
	<div class="mc-container">
		<div class="mc-banners__grid mc-banners__grid--<?php echo esc_attr( count( $banners ) ); ?>">
			<?php foreach ( $banners as $b ) :
				$tag = $b['link'] ? 'a' : 'div';
				$href = $b['link'] ? ' href="' . esc_url( $b['link'] ) . '"' : '';
				?>
				<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="mc-banner"<?php echo $href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php if ( $b['img'] ) : ?>
						<?php echo wp_get_attachment_image( $b['img'], 'mobicare-banner', false, array( 'class' => 'mc-banner__img', 'loading' => 'lazy' ) ); ?>
					<?php endif; ?>
					<?php if ( $b['title'] ) : ?>
						<span class="mc-banner__title"><?php echo esc_html( $b['title'] ); ?></span>
					<?php endif; ?>
				</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php endforeach; ?>
		</div>
	</div>
</section>
