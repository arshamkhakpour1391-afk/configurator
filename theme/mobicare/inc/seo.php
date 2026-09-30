<?php
/**
 * SEO helpers
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Open Graph + basic meta.
 */
function mobicare_og_tags() {
	if ( is_admin() ) {
		return;
	}

	$title = wp_get_document_title();
	$desc  = get_bloginfo( 'description' );
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );
	$image = '';

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post && ! empty( $post->post_excerpt ) ) {
			$desc = wp_strip_all_tags( $post->post_excerpt );
		} elseif ( $post && ! empty( $post->post_content ) ) {
			$desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		}
		if ( has_post_thumbnail() ) {
			$image = get_the_post_thumbnail_url( null, 'large' );
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_the_ID() );
			if ( $product ) {
				$desc = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
				$desc = wp_trim_words( $desc, 30 );
			}
		}
	}

	if ( ! $image ) {
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$image = wp_get_attachment_image_url( $logo_id, 'full' );
		}
	}

	$desc = mb_substr( $desc, 0, 160 );
	?>
	<meta name="description" content="<?php echo esc_attr( $desc ); ?>">
	<link rel="canonical" href="<?php echo esc_url( is_singular() ? get_permalink() : home_url( '/' ) ); ?>">
	<meta property="og:locale" content="fa_IR">
	<meta property="og:type" content="<?php echo is_singular( 'product' ) ? 'product' : ( is_singular() ? 'article' : 'website' ); ?>">
	<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $desc ); ?>">
	<meta property="og:url" content="<?php echo esc_url( is_singular() ? get_permalink() : home_url( '/' ) ); ?>">
	<meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
	<?php if ( $image ) : ?>
	<meta property="og:image" content="<?php echo esc_url( $image ); ?>">
	<?php endif; ?>
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>">
	<meta name="twitter:description" content="<?php echo esc_attr( $desc ); ?>">
	<?php if ( $image ) : ?>
	<meta name="twitter:image" content="<?php echo esc_url( $image ); ?>">
	<?php endif; ?>
	<?php
}
add_action( 'wp_head', 'mobicare_og_tags', 1 );

/**
 * Product JSON-LD (works alongside WooCommerce structured data).
 */
function mobicare_product_schema() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	// WooCommerce already outputs Product schema via woocommerce_structured_data.
	// Add Organization on home.
}
add_action( 'wp_footer', 'mobicare_product_schema' );

/**
 * Organization schema on front page.
 */
function mobicare_organization_schema() {
	if ( ! is_front_page() ) {
		return;
	}
	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
	);
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$data['logo'] = wp_get_attachment_image_url( $logo_id, 'full' );
	}
	$phone = get_theme_mod( 'mobicare_phone', '' );
	if ( $phone ) {
		$data['telephone'] = $phone;
	}
	$email = get_theme_mod( 'mobicare_email', '' );
	if ( $email ) {
		$data['email'] = $email;
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
}
add_action( 'wp_head', 'mobicare_organization_schema', 5 );

/**
 * WebSite + SearchAction schema.
 */
function mobicare_website_schema() {
	if ( ! is_front_page() ) {
		return;
	}
	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'WebSite',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}&post_type=product' ),
			'query-input' => 'required name=search_term_string',
		),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
}
add_action( 'wp_head', 'mobicare_website_schema', 6 );
