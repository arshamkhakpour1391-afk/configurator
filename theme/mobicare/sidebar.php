<?php
/**
 * Sidebar
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_active_sidebar( 'shop-sidebar' ) ) {
	return;
}
?>
<aside id="secondary" class="mc-sidebar" role="complementary" aria-label="<?php esc_attr_e( 'سایدبار', 'mobicare' ); ?>">
	<?php dynamic_sidebar( 'shop-sidebar' ); ?>
</aside>
