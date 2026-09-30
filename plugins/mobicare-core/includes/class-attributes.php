<?php
/**
 * WooCommerce product attributes for cases & glass
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Attributes
 */
class MobiCare_Attributes {

	/**
	 * Attribute definitions (slug => label).
	 *
	 * @return array
	 */
	public static function definitions() {
		return array(
			'color'            => __( 'رنگ', 'mobicare-core' ),
			'material'         => __( 'جنس / متریال', 'mobicare-core' ),
			'protection-level' => __( 'سطح محافظت', 'mobicare-core' ),
			'magsafe'          => __( 'پشتیبانی MagSafe', 'mobicare-core' ),
			'case-type'        => __( 'نوع قاب', 'mobicare-core' ),
			'glass-type'       => __( 'نوع گلس', 'mobicare-core' ),
			'warranty'         => __( 'گارانتی', 'mobicare-core' ),
		);
	}

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_create_attributes' ) );
	}

	/**
	 * Create global attributes once (idempotent).
	 */
	public static function maybe_create_attributes() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( get_option( 'mobicare_attributes_created' ) === MOBICARE_CORE_VERSION ) {
			return;
		}
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return;
		}

		$existing = array();
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( wc_get_attribute_taxonomies() as $tax ) {
				$existing[ $tax->attribute_name ] = true;
			}
		}

		foreach ( self::definitions() as $slug => $label ) {
			if ( isset( $existing[ $slug ] ) ) {
				continue;
			}
			$result = wc_create_attribute( array(
				'name'         => $label,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => true,
			) );
			if ( ! is_wp_error( $result ) && $result ) {
				$taxonomy = wc_attribute_taxonomy_name( $slug );
				if ( ! taxonomy_exists( $taxonomy ) ) {
					register_taxonomy(
						$taxonomy,
						apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy, array( 'product' ) ),
						apply_filters(
							'woocommerce_taxonomy_args_' . $taxonomy,
							array(
								'labels'            => array( 'name' => $label ),
								'hierarchical'      => false,
								'show_ui'           => false,
								'query_var'         => true,
								'rewrite'           => false,
								'show_in_rest'      => true,
							)
						)
					);
				}
			}
		}

		delete_transient( 'wc_attribute_taxonomies' );
		update_option( 'mobicare_attributes_created', MOBICARE_CORE_VERSION );
	}
}
