<?php
declare(strict_types=1);

/**
 * Dynamic Product Features Attribute Grouping & Reordering Module
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetch ordered list of attribute groups.
 *
 * @return array Map of group_name => priority
 */
function mu_get_attribute_groups(): array {
	static $cached_groups = null;

	if ( null !== $cached_groups ) {
		return $cached_groups;
	}

	// 1. Try native Optionino repeater groups
	$repeater_groups = singo_tools_get_option( 'mu_attribute_groups', null );
	if ( is_array( $repeater_groups ) && ! empty( $repeater_groups ) ) {
		$result   = array();
		$priority = 1;
		foreach ( $repeater_groups as $item ) {
			if ( is_array( $item ) && ! empty( $item['group_title'] ) ) {
				$clean = trim( (string) $item['group_title'] );
				if ( ! empty( $clean ) && ! array_key_exists( $clean, $result ) ) {
					$result[ $clean ] = $priority++;
				}
			} elseif ( is_string( $item ) && ! empty( $item ) ) {
				$clean = trim( $item );
				if ( ! empty( $clean ) && ! array_key_exists( $clean, $result ) ) {
					$result[ $clean ] = $priority++;
				}
			}
		}
		if ( ! empty( $result ) ) {
			$cached_groups = $result;
			return $cached_groups;
		}
	}

	// 2. Fallback to legacy mu_groups or dedicated option
	$groups = singo_tools_get_option( 'mu_groups', null );

	if ( ! is_array( $groups ) || empty( $groups ) ) {
		$groups = get_option( 'mu_attribute_groups', null );
	}

	if ( ! is_array( $groups ) || empty( $groups ) ) {
		if ( class_exists( 'Singo_Product_Features' ) ) {
			$groups = Singo_Product_Features::get_default_groups_list();
		} else {
			$groups = array(
				"مشخصات کلی",
				"سایر مشخصات",
			);
		}
	}

	$result   = array();
	$priority = 1;
	foreach ( $groups as $group ) {
		$clean = is_string( $group ) ? trim( $group ) : '';
		if ( ! empty( $clean ) && ! array_key_exists( $clean, $result ) ) {
			$result[ $clean ] = $priority++;
		}
	}

	$cached_groups = $result;
	return $cached_groups;
}

/**
 * Fetch default fallback attribute group.
 *
 * @return string
 */
function mu_get_default_attribute_group(): string {
	static $cached_default = null;

	if ( null !== $cached_default ) {
		return $cached_default;
	}

	// 1. Try native Optionino repeater groups
	$repeater_groups = singo_tools_get_option( 'mu_attribute_groups', null );
	if ( is_array( $repeater_groups ) && ! empty( $repeater_groups ) ) {
		foreach ( $repeater_groups as $item ) {
			if ( is_array( $item ) && ! empty( $item['group_title'] ) ) {
				$is_def = isset( $item['is_default'] ) && ( $item['is_default'] === 'true' || $item['is_default'] === 'on' || $item['is_default'] === '1' );
				if ( $is_def ) {
					$cached_default = trim( (string) $item['group_title'] );
					return $cached_default;
				}
			}
		}
	}

	// 2. Fallback to legacy options
	$default = singo_tools_get_option( 'mu_default_group', '' );
	if ( empty( $default ) ) {
		$default = get_option( 'mu_default_attribute_group', 'سایر مشخصات' );
	}

	$all_groups = mu_get_attribute_groups();

	if ( ! array_key_exists( $default, $all_groups ) ) {
		$keys    = array_keys( $all_groups );
		$default = ! empty( $keys ) ? $keys[0] : 'سایر مشخصات';
	}

	$cached_default = $default;
	return $cached_default;
}

/**
 * Filter WooCommerce attribute taxonomies and assign them to custom groups.
 */
add_filter( 'singo_group_product_attributes', 'mu_group_product_attributes', 10, 2 );
function mu_group_product_attributes( $product_attributes, $product ) {
	$enabled = singo_tools_get_option( 'enable_product_features', false );
	if ( ! $enabled ) {
		return $product_attributes;
	}

	if ( empty( $product_attributes ) ) {
		return $product_attributes;
	}

	$groups_map = get_option( 'mu_attribute_groups_map', array() );
	$all_groups = mu_get_attribute_groups();
	$default_g  = mu_get_default_attribute_group();

	$grouped_attributes = array();

	foreach ( $product_attributes as $key => $attribute ) {
		$possible_keys = array(
			$key,
			str_replace( 'attribute_', '', $key ),
			'pa_' . str_replace( array( 'attribute_', 'pa_' ), '', $key ),
		);

		$group_title = $default_g;
		foreach ( $possible_keys as $possible_key ) {
			if ( isset( $groups_map[ $possible_key ] ) ) {
				$group_title = $groups_map[ $possible_key ];
				break;
			}
		}

		if ( ! isset( $all_groups[ $group_title ] ) ) {
			$group_title = $default_g;
		}

		if ( ! isset( $grouped_attributes[ $group_title ] ) ) {
			$grouped_attributes[ $group_title ] = array();
		}
		$grouped_attributes[ $group_title ][ $key ] = $attribute;
	}

	// Sort groups based on configured priority
	uksort( $grouped_attributes, function( $a, $b ) use ( $all_groups ) {
		$pos_a = isset( $all_groups[ $a ] ) ? $all_groups[ $a ] : 999;
		$pos_b = isset( $all_groups[ $b ] ) ? $all_groups[ $b ] : 999;
		return $pos_a <=> $pos_b;
	} );

	return $grouped_attributes;
}

/**
 * Shortcode to render grouped product attributes: [dynamic_product_specs]
 */
add_shortcode( 'dynamic_product_specs', 'mu_render_dynamic_product_specs_shortcode' );
function mu_render_dynamic_product_specs_shortcode( $atts = array() ) {
	$enabled = singo_tools_get_option( 'enable_product_features', false );
	if ( ! $enabled ) {
		return '';
	}

	global $product;

	if ( ! is_a( $product, 'WC_Product' ) ) {
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			global $post;
			if ( $post && isset( $post->ID ) ) {
				$product_id = $post->ID;
			}
		}
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
		}
	}

	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}

	$attributes = $product->get_attributes();
	if ( empty( $attributes ) ) {
		return '';
	}

	// Prepare raw display array
	$display_attributes = array();
	foreach ( $attributes as $attribute ) {
		if ( ! $attribute->get_visible() ) {
			continue;
		}

		$name   = $attribute->get_name();
		$label  = wc_attribute_label( $name );
		$values = array();

		if ( $attribute->is_taxonomy() ) {
			$terms = $attribute->get_terms();
			foreach ( $terms as $term ) {
				$values[] = esc_html( $term->name );
			}
		} else {
			$raw_options = $attribute->get_options();
			if ( is_array( $raw_options ) ) {
				$values = array_map( 'trim', array_map( 'strval', $raw_options ) );
			} elseif ( is_string( $raw_options ) ) {
				$values = array_map( 'trim', explode( '|', $raw_options ) );
			}
		}

		if ( ! empty( $values ) ) {
			$display_attributes[ $name ] = array(
				'label' => $label,
				'value' => implode( '، ', $values ),
			);
		}
	}

	$grouped = apply_filters( 'singo_group_product_attributes', $display_attributes, $product );

	if ( empty( $grouped ) || ! is_array( $grouped ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="mu-product-specs-container" style="direction: rtl; font-family: inherit; margin-top: 20px;">
		<?php foreach ( $grouped as $group_title => $items ) : 
			if ( ! is_array( $items ) ) {
				continue;
			}
		?>
			<div class="mu-spec-group-box" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;">
				<h3 class="mu-spec-group-title" style="margin: 0; background: #f8fafc; padding: 12px 16px; font-size: 16px; font-weight: 700; color: #1e293b; border-bottom: 1px solid #e2e8f0;">
					<?php echo esc_html( $group_title ); ?>
				</h3>
				<table class="mu-spec-group-table" style="width: 100%; border-collapse: collapse; text-align: right;">
					<tbody>
						<?php foreach ( $items as $item ) : 
							if ( ! is_array( $item ) || ! isset( $item['label'] ) || ! isset( $item['value'] ) ) {
								continue;
							}
						?>
							<tr style="border-bottom: 1px dashed #f1f5f9;">
								<th style="padding: 10px 16px; width: 35%; color: #475569; font-weight: 600; font-size: 14px; background: #fafafa;">
									<?php echo esc_html( $item['label'] ); ?>
								</th>
								<td style="padding: 10px 16px; color: #0f172a; font-size: 14px;">
									<?php echo esc_html( $item['value'] ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Override the default WooCommerce Additional Information tab to show grouped features.
 */
add_filter( 'woocommerce_product_tabs', 'mu_override_woocommerce_attributes_tab', 98 );
function mu_override_woocommerce_attributes_tab( $tabs ) {
	$enabled = singo_tools_get_option( 'enable_product_features', false );
	if ( ! $enabled ) {
		return $tabs;
	}

	if ( isset( $tabs['additional_information'] ) ) {
		$tabs['additional_information']['callback'] = 'mu_render_woocommerce_attributes_tab_content';
	}
	return $tabs;
}

function mu_render_woocommerce_attributes_tab_content() {
	echo mu_render_dynamic_product_specs_shortcode( array() );
}

