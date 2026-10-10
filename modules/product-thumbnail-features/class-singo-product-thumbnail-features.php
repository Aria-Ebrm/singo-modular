<?php
declare(strict_types=1);

/**
 * Singo Tools - Product Thumbnail Features (ویژگی بند انگشتی محصولات)
 * Shortcode: [product_features_boxes]
 * PHP 8+ Standardized
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Product_Thumbnail_Features {

	/**
	 * Singleton instance.
	 *
	 * @var Singo_Product_Thumbnail_Features|null
	 */
	private static ?Singo_Product_Thumbnail_Features $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		add_shortcode( 'product_features_boxes', array( $this, 'render_shortcode' ) );
		add_action( 'woocommerce_update_product', array( $this, 'purge_product_features_cache' ), 10, 1 );
	}

	/**
	 * Render [product_features_boxes] shortcode (Frontend only on single product page).
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( mixed $atts ): string {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return '';
		}

		global $product;

		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product_id = get_the_ID();
			$product    = $product_id ? wc_get_product( $product_id ) : null;
		}

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		$args = shortcode_atts(
			array(
				'limit' => 4,
			),
			(array) $atts,
			'product_features_boxes'
		);

		$limit      = max( 1, (int) $args['limit'] );
		$product_id = $product->get_id();

		// Transient Persistent DB Cache (12 Hours)
		$transient_key = "mu_p_feat_{$product_id}_l_{$limit}";
		$cached_html   = get_transient( $transient_key );

		if ( false !== $cached_html && is_string( $cached_html ) ) {
			return $cached_html;
		}

		$attributes = $product->get_attributes();
		if ( empty( $attributes ) ) {
			return '';
		}

		$count        = 0;
		$output_items = array();

		foreach ( $attributes as $attribute ) {
			if ( $count >= $limit ) {
				break;
			}

			if ( ! method_exists( $attribute, 'get_visible' ) || ! $attribute->get_visible() ) {
				continue;
			}

			$attribute_name  = $attribute->get_name();
			$attribute_label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $attribute_name, $product ) : $attribute_name;

			$values        = array();
			$thumbnail_ids = array();

			if ( method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() ) {
				$term_ids = $attribute->get_options();
				if ( empty( $term_ids ) ) {
					continue;
				}

				foreach ( $term_ids as $term_id ) {
					$term = get_term( (int) $term_id, $attribute_name );
					if ( ! $term || is_wp_error( $term ) ) {
						continue;
					}
					$values[] = $term->name;

					$term_thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
					if ( ! empty( $term_thumb_id ) ) {
						$thumbnail_ids[] = (int) $term_thumb_id;
					}
				}
			} elseif ( method_exists( $attribute, 'get_options' ) ) {
				$values = $attribute->get_options();
			}

			if ( empty( $values ) ) {
				continue;
			}

			$display_value = implode( ', ', $values );
			$image_html    = '';

			if ( ! empty( $thumbnail_ids ) ) {
				$image_html = wp_get_attachment_image(
					$thumbnail_ids[0],
					'thumbnail',
					false,
					array(
						'class'       => 'mu-attribute-image',
						'aria-hidden' => 'true',
						'alt'         => esc_attr( $attribute_label ),
					)
				);
			}

			$item_html  = '<li class="attribute-box">';
			if ( ! empty( $image_html ) ) {
				$item_html .= $image_html;
			}
			$item_html .= '<div class="attribute-details">';
			$item_html .= '<span>' . esc_html( $attribute_label ) . ':</span> ';
			$item_html .= '<strong>' . wp_kses_post( $display_value ) . '</strong>';
			$item_html .= '</div></li>';

			$output_items[] = $item_html;
			$count++;
		}

		if ( empty( $output_items ) ) {
			return '';
		}

		$final_html  = '<ul class="mu-attributes-boxes">';
		$final_html .= implode( '', $output_items );
		$final_html .= '</ul>';

		// ذخیره کش پایدار به مدت ۱۲ ساعت در دیتابیس
		set_transient( $transient_key, $final_html, 12 * HOUR_IN_SECONDS );

		return $final_html;
	}

	/**
	 * پاکسازی خودکار کش محصول هنگام به‌روزرسانی ویژگی‌ها
	 */
	public function purge_product_features_cache( int $product_id ): void {
		for ( $i = 1; $i <= 10; $i++ ) {
			delete_transient( "mu_p_feat_{$product_id}_l_{$i}" );
		}
	}
}
