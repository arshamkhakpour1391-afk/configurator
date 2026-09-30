<?php
/**
 * Theme Customizer
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register customizer settings.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function mobicare_customize_register( $wp_customize ) {

	$wp_customize->add_panel( 'mobicare_panel', array(
		'title'    => __( 'تنظیمات MobiCare', 'mobicare' ),
		'priority' => 30,
	) );

	/* ----- General ----- */
	$wp_customize->add_section( 'mobicare_general', array(
		'title' => __( 'عمومی و تماس', 'mobicare' ),
		'panel' => 'mobicare_panel',
	) );

	$fields = array(
		'mobicare_phone'     => array( 'label' => __( 'شماره تماس', 'mobicare' ), 'default' => '' ),
		'mobicare_email'     => array( 'label' => __( 'ایمیل', 'mobicare' ), 'default' => '' ),
		'mobicare_address'   => array( 'label' => __( 'آدرس', 'mobicare' ), 'default' => '', 'type' => 'textarea' ),
		'mobicare_instagram' => array( 'label' => __( 'لینک اینستاگرام', 'mobicare' ), 'default' => '' ),
		'mobicare_telegram'  => array( 'label' => __( 'لینک تلگرام', 'mobicare' ), 'default' => '' ),
		'mobicare_whatsapp'  => array( 'label' => __( 'لینک واتساپ', 'mobicare' ), 'default' => '' ),
	);

	foreach ( $fields as $id => $args ) {
		$wp_customize->add_setting( $id, array(
			'default'           => $args['default'],
			'sanitize_callback' => isset( $args['type'] ) && 'textarea' === $args['type'] ? 'sanitize_textarea_field' : 'sanitize_text_field',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $id, array(
			'label'   => $args['label'],
			'section' => 'mobicare_general',
			'type'    => isset( $args['type'] ) ? $args['type'] : 'text',
		) );
	}

	$wp_customize->add_setting( 'mobicare_persian_digits', array(
		'default'           => true,
		'sanitize_callback' => function( $v ) { return (bool) $v; },
	) );
	$wp_customize->add_control( 'mobicare_persian_digits', array(
		'label'   => __( 'نمایش اعداد فارسی', 'mobicare' ),
		'section' => 'mobicare_general',
		'type'    => 'checkbox',
	) );

	/* ----- Announcement ----- */
	$wp_customize->add_section( 'mobicare_announce', array(
		'title' => __( 'نوار اعلان', 'mobicare' ),
		'panel' => 'mobicare_panel',
	) );

	$wp_customize->add_setting( 'mobicare_announcement_text', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'mobicare_announcement_text', array(
		'label'       => __( 'متن اعلان', 'mobicare' ),
		'description' => __( 'خالی بگذارید تا نوار مخفی شود.', 'mobicare' ),
		'section'     => 'mobicare_announce',
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'mobicare_announcement_link', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'mobicare_announcement_link', array(
		'label'   => __( 'لینک اعلان', 'mobicare' ),
		'section' => 'mobicare_announce',
		'type'    => 'url',
	) );

	/* ----- Hero ----- */
	$wp_customize->add_section( 'mobicare_hero', array(
		'title' => __( 'هیرو صفحه اصلی', 'mobicare' ),
		'panel' => 'mobicare_panel',
	) );

	$hero_fields = array(
		'mobicare_hero_title'    => array( 'label' => __( 'عنوان هیرو', 'mobicare' ), 'default' => 'قاب و گلس مناسب گوشی شما', 'type' => 'text' ),
		'mobicare_hero_subtitle' => array( 'label' => __( 'زیرعنوان', 'mobicare' ), 'default' => 'محافظت حرفه‌ای، طراحی زیبا، ارسال سریع به سراسر ایران', 'type' => 'textarea' ),
		'mobicare_hero_btn_text' => array( 'label' => __( 'متن دکمه', 'mobicare' ), 'default' => 'مشاهده فروشگاه', 'type' => 'text' ),
		'mobicare_hero_btn_link' => array( 'label' => __( 'لینک دکمه', 'mobicare' ), 'default' => '', 'type' => 'url' ),
		'mobicare_hero_btn2_text'=> array( 'label' => __( 'متن دکمه دوم', 'mobicare' ), 'default' => 'یافتن مدل گوشی', 'type' => 'text' ),
	);

	foreach ( $hero_fields as $id => $args ) {
		$sanitize = 'sanitize_text_field';
		if ( 'textarea' === $args['type'] ) {
			$sanitize = 'sanitize_textarea_field';
		} elseif ( 'url' === $args['type'] ) {
			$sanitize = 'esc_url_raw';
		}
		$wp_customize->add_setting( $id, array(
			'default'           => $args['default'],
			'sanitize_callback' => $sanitize,
		) );
		$wp_customize->add_control( $id, array(
			'label'   => $args['label'],
			'section' => 'mobicare_hero',
			'type'    => 'url' === $args['type'] ? 'url' : ( 'textarea' === $args['type'] ? 'textarea' : 'text' ),
		) );
	}

	$wp_customize->add_setting( 'mobicare_hero_image', array(
		'default'           => '',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'mobicare_hero_image', array(
		'label'     => __( 'تصویر هیرو', 'mobicare' ),
		'section'   => 'mobicare_hero',
		'mime_type' => 'image',
	) ) );

	/* ----- Banners ----- */
	$wp_customize->add_section( 'mobicare_banners', array(
		'title' => __( 'بنرهای تبلیغاتی', 'mobicare' ),
		'panel' => 'mobicare_panel',
	) );

	for ( $i = 1; $i <= 3; $i++ ) {
		$wp_customize->add_setting( "mobicare_banner_{$i}_image", array(
			'default'           => '',
			'sanitize_callback' => 'absint',
		) );
		$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, "mobicare_banner_{$i}_image", array(
			'label'     => sprintf( __( 'تصویر بنر %d', 'mobicare' ), $i ),
			'section'   => 'mobicare_banners',
			'mime_type' => 'image',
		) ) );

		$wp_customize->add_setting( "mobicare_banner_{$i}_title", array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "mobicare_banner_{$i}_title", array(
			'label'   => sprintf( __( 'عنوان بنر %d', 'mobicare' ), $i ),
			'section' => 'mobicare_banners',
			'type'    => 'text',
		) );

		$wp_customize->add_setting( "mobicare_banner_{$i}_link", array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		) );
		$wp_customize->add_control( "mobicare_banner_{$i}_link", array(
			'label'   => sprintf( __( 'لینک بنر %d', 'mobicare' ), $i ),
			'section' => 'mobicare_banners',
			'type'    => 'url',
		) );
	}

	/* ----- Shop ----- */
	$wp_customize->add_section( 'mobicare_shop', array(
		'title' => __( 'فروشگاه', 'mobicare' ),
		'panel' => 'mobicare_panel',
	) );

	$wp_customize->add_setting( 'mobicare_products_per_page', array(
		'default'           => 16,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'mobicare_products_per_page', array(
		'label'   => __( 'تعداد محصول در هر صفحه', 'mobicare' ),
		'section' => 'mobicare_shop',
		'type'    => 'number',
		'input_attrs' => array( 'min' => 4, 'max' => 48, 'step' => 4 ),
	) );

	/* ----- FAQ ----- */
	$wp_customize->add_section( 'mobicare_faq', array(
		'title' => __( 'سوالات متداول صفحه اصلی', 'mobicare' ),
		'panel' => 'mobicare_panel',
	) );

	for ( $i = 1; $i <= 6; $i++ ) {
		$wp_customize->add_setting( "mobicare_faq_q_{$i}", array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "mobicare_faq_q_{$i}", array(
			'label'   => sprintf( __( 'سوال %d', 'mobicare' ), $i ),
			'section' => 'mobicare_faq',
			'type'    => 'text',
		) );
		$wp_customize->add_setting( "mobicare_faq_a_{$i}", array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_textarea_field',
		) );
		$wp_customize->add_control( "mobicare_faq_a_{$i}", array(
			'label'   => sprintf( __( 'پاسخ %d', 'mobicare' ), $i ),
			'section' => 'mobicare_faq',
			'type'    => 'textarea',
		) );
	}

	/* ----- Colors ----- */
	$wp_customize->add_section( 'mobicare_colors', array(
		'title' => __( 'رنگ‌ها', 'mobicare' ),
		'panel' => 'mobicare_panel',
	) );

	$wp_customize->add_setting( 'mobicare_primary_color', array(
		'default'           => '#2563eb',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'mobicare_primary_color', array(
		'label'   => __( 'رنگ اصلی', 'mobicare' ),
		'section' => 'mobicare_colors',
	) ) );

	$wp_customize->add_setting( 'mobicare_accent_color', array(
		'default'           => '#0ea5e9',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'mobicare_accent_color', array(
		'label'   => __( 'رنگ تأکیدی', 'mobicare' ),
		'section' => 'mobicare_colors',
	) ) );
}
add_action( 'customize_register', 'mobicare_customize_register' );

/**
 * Output CSS variables from customizer.
 */
function mobicare_customizer_css() {
	$primary = get_theme_mod( 'mobicare_primary_color', '#2563eb' );
	$accent  = get_theme_mod( 'mobicare_accent_color', '#0ea5e9' );
	?>
	<style id="mobicare-customizer-css">
		:root {
			--mc-primary: <?php echo esc_html( $primary ); ?>;
			--mc-accent: <?php echo esc_html( $accent ); ?>;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'mobicare_customizer_css', 20 );
