<?php
/**
 * Theme AJAX endpoints (search suggestions)
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Live search suggestions.
 */
function mobicare_ajax_search() {
	check_ajax_referer( 'mobicare_nonce', 'nonce' );

	$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	if ( strlen( $term ) < 2 ) {
		wp_send_json_success( array( 'items' => array() ) );
	}

	$items = array();

	if ( class_exists( 'WooCommerce' ) ) {
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			's'              => $term,
			'posts_per_page' => 8,
			'no_found_rows'  => true,
		) );

		while ( $q->have_posts() ) {
			$q->the_post();
			$product = wc_get_product( get_the_ID() );
			if ( ! $product ) {
				continue;
			}
			$items[] = array(
				'id'        => $product->get_id(),
				'title'     => $product->get_name(),
				'url'       => get_permalink( $product->get_id() ),
				'price'     => wp_strip_all_tags( $product->get_price_html() ),
				'image'     => get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
				'type'      => 'product',
				'in_stock'  => $product->is_in_stock(),
			);
		}
		wp_reset_postdata();

		// Phone models.
		if ( taxonomy_exists( 'phone_model' ) ) {
			$models = get_terms( array(
				'taxonomy'   => 'phone_model',
				'hide_empty' => true,
				'number'     => 5,
				'name__like' => $term,
			) );
			if ( ! is_wp_error( $models ) ) {
				foreach ( $models as $m ) {
					$items[] = array(
						'id'    => $m->term_id,
						'title' => sprintf( /* translators: model name */ __( 'محصولات %s', 'mobicare' ), $m->name ),
						'url'   => get_term_link( $m ),
						'type'  => 'model',
						'image' => '',
					);
				}
			}
		}

		// Brands.
		if ( taxonomy_exists( 'product_brand' ) ) {
			$brands = get_terms( array(
				'taxonomy'   => 'product_brand',
				'hide_empty' => true,
				'number'     => 3,
				'name__like' => $term,
			) );
			if ( ! is_wp_error( $brands ) ) {
				foreach ( $brands as $b ) {
					$items[] = array(
						'id'    => $b->term_id,
						'title' => $b->name,
						'url'   => get_term_link( $b ),
						'type'  => 'brand',
						'image' => '',
					);
				}
			}
		}

		// SKU exact-ish search.
		$sku_q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 3,
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_sku',
					'value'   => $term,
					'compare' => 'LIKE',
				),
			),
		) );
		while ( $sku_q->have_posts() ) {
			$sku_q->the_post();
			$product = wc_get_product( get_the_ID() );
			if ( ! $product ) {
				continue;
			}
			// Avoid duplicates.
			$exists = false;
			foreach ( $items as $it ) {
				if ( isset( $it['id'] ) && (int) $it['id'] === (int) $product->get_id() && 'product' === $it['type'] ) {
					$exists = true;
					break;
				}
			}
			if ( $exists ) {
				continue;
			}
			$items[] = array(
				'id'       => $product->get_id(),
				'title'    => $product->get_name() . ' (SKU)',
				'url'      => get_permalink( $product->get_id() ),
				'price'    => wp_strip_all_tags( $product->get_price_html() ),
				'image'    => get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
				'type'     => 'product',
				'in_stock' => $product->is_in_stock(),
			);
		}
		wp_reset_postdata();
	}

	wp_send_json_success( array( 'items' => array_slice( $items, 0, 12 ) ) );
}
add_action( 'wp_ajax_mobicare_search', 'mobicare_ajax_search' );
add_action( 'wp_ajax_nopriv_mobicare_search', 'mobicare_ajax_search' );

/**
 * Fetch phone brands & models for model finder.
 */
function mobicare_ajax_phone_models() {
	check_ajax_referer( 'mobicare_nonce', 'nonce' );

	$brands = array();

	if ( taxonomy_exists( 'phone_brand' ) ) {
		$brand_terms = get_terms( array(
			'taxonomy'   => 'phone_brand',
			'hide_empty' => false,
			'parent'     => 0,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );
		if ( ! is_wp_error( $brand_terms ) ) {
			foreach ( $brand_terms as $b ) {
				$models = get_terms( array(
					'taxonomy'   => 'phone_model',
					'hide_empty' => false,
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => 'phone_brand_id',
							'value' => $b->term_id,
						),
					),
					'orderby'    => 'name',
					'order'      => 'DESC',
				) );
				// Also try child terms of phone_model with parent linked via description or get all and filter.
				if ( is_wp_error( $models ) || empty( $models ) ) {
					// Fallback: models whose slug/name starts with brand or assigned via parent brand taxonomy relation.
					$models = get_terms( array(
						'taxonomy'   => 'phone_model',
						'hide_empty' => false,
						'orderby'    => 'name',
						'order'      => 'DESC',
					) );
					// Filter by brand meta or name containing brand.
					if ( ! is_wp_error( $models ) ) {
						$filtered = array();
						foreach ( $models as $m ) {
							$bid = (int) get_term_meta( $m->term_id, 'phone_brand_id', true );
							if ( $bid === (int) $b->term_id ) {
								$filtered[] = $m;
							}
						}
						// If still empty and brand has linked models via shared parent structure.
						if ( empty( $filtered ) ) {
							// Use phone_model terms that have this brand as parent in hierarchical setup under phone_model.
							$children = get_terms( array(
								'taxonomy'   => 'phone_model',
								'hide_empty' => false,
								'parent'     => 0,
								'name__like' => $b->name,
							) );
						}
						$models = $filtered;
					}
				}

				$model_data = array();
				if ( ! is_wp_error( $models ) ) {
					foreach ( $models as $m ) {
						$model_data[] = array(
							'id'    => $m->term_id,
							'name'  => $m->name,
							'slug'  => $m->slug,
							'url'   => get_term_link( $m ),
							'count' => (int) $m->count,
						);
					}
				}

				$brands[] = array(
					'id'     => $b->term_id,
					'name'   => $b->name,
					'slug'   => $b->slug,
					'models' => $model_data,
				);
			}
		}
	}

	// Fallback: hierarchical phone_model only.
	if ( empty( $brands ) && taxonomy_exists( 'phone_model' ) ) {
		$parents = get_terms( array(
			'taxonomy'   => 'phone_model',
			'hide_empty' => false,
			'parent'     => 0,
			'orderby'    => 'name',
		) );
		if ( ! is_wp_error( $parents ) ) {
			foreach ( $parents as $p ) {
				$children = get_terms( array(
					'taxonomy'   => 'phone_model',
					'hide_empty' => false,
					'parent'     => $p->term_id,
					'orderby'    => 'name',
					'order'      => 'DESC',
				) );
				$model_data = array();
				if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
					foreach ( $children as $m ) {
						$model_data[] = array(
							'id'    => $m->term_id,
							'name'  => $m->name,
							'slug'  => $m->slug,
							'url'   => get_term_link( $m ),
							'count' => (int) $m->count,
						);
					}
					$brands[] = array(
						'id'     => $p->term_id,
						'name'   => $p->name,
						'slug'   => $p->slug,
						'models' => $model_data,
					);
				} else {
					// Parent is itself a model.
					$brands[] = array(
						'id'     => 0,
						'name'   => __( 'سایر', 'mobicare' ),
						'slug'   => 'other',
						'models' => array(
							array(
								'id'    => $p->term_id,
								'name'  => $p->name,
								'slug'  => $p->slug,
								'url'   => get_term_link( $p ),
								'count' => (int) $p->count,
							),
						),
					);
				}
			}
			// Merge "other" brands.
			$merged = array();
			$other_models = array();
			foreach ( $brands as $br ) {
				if ( 'other' === $br['slug'] ) {
					$other_models = array_merge( $other_models, $br['models'] );
				} else {
					$merged[] = $br;
				}
			}
			if ( $other_models ) {
				// Group uncategorized under one brand if only models exist.
				if ( empty( $merged ) ) {
					$merged[] = array(
						'id'     => 0,
						'name'   => __( 'مدل‌ها', 'mobicare' ),
						'slug'   => 'models',
						'models' => $other_models,
					);
				}
			}
			$brands = $merged ? $merged : $brands;
		}
	}

	wp_send_json_success( array( 'brands' => $brands ) );
}
add_action( 'wp_ajax_mobicare_phone_models', 'mobicare_ajax_phone_models' );
add_action( 'wp_ajax_nopriv_mobicare_phone_models', 'mobicare_ajax_phone_models' );
