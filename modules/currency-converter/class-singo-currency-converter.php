<?php
declare(strict_types=1);

/**
 * Singo Tools - Currency Converter Module (Singo_Currency_Converter)
 * Full support for Simple, External, and Variable Products (PHP 8+ Standardized)
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Currency_Converter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Parent / Simple Product Fields
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_currency_selection_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_currency_meta' ) );

		// Variable Product Variation Fields
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'add_variation_currency_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_currency_meta' ), 10, 2 );
		add_action( 'woocommerce_admin_process_variation_object', array( $this, 'process_variation_object_currency_meta' ), 10, 2 );

		// Dynamic price filters for Simple Products
		add_filter( 'woocommerce_product_get_price', array( $this, 'get_dynamic_converted_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'get_dynamic_converted_regular_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'get_dynamic_converted_sale_price' ), 99, 2 );

		// Dynamic price filters for Product Variations
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'get_dynamic_converted_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'get_dynamic_converted_regular_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'get_dynamic_converted_sale_price' ), 99, 2 );

		// Dynamic price filters for Variable Product Price Ranges
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'get_dynamic_converted_price' ), 99, 2 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( $this, 'get_dynamic_converted_regular_price' ), 99, 2 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( $this, 'get_dynamic_converted_sale_price' ), 99, 2 );

		// Variation price caching hash invalidation
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'filter_variation_prices_hash' ), 99, 3 );
	}

	/**
	 * Output currency selection radio on parent product General data tab.
	 * Visible for Simple, External, and Variable products.
	 */
	public function add_currency_selection_fields(): void {
		global $post;
		if ( ! $post ) {
			return;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}

		$selected_currency = $product->get_meta( '_input_currency_type' );
		if ( empty( $selected_currency ) ) {
			$selected_currency = 'irr';
		}

		$raw_regular = $product->get_meta( '_raw_input_regular_price' );
		$raw_sale    = $product->get_meta( '_raw_input_sale_price' );

		if ( $selected_currency !== 'irr' && ( ! empty( $raw_regular ) || ! empty( $raw_sale ) ) ) {
			echo '<script>
				document.addEventListener("DOMContentLoaded", function() {
					var regPriceInput = document.getElementById("_regular_price");
					var salePriceInput = document.getElementById("_sale_price");
					if(regPriceInput && "' . esc_js( (string) $raw_regular ) . '" !== "") regPriceInput.value = "' . esc_js( (string) $raw_regular ) . '";
					if(salePriceInput && "' . esc_js( (string) $raw_sale ) . '" !== "") salePriceInput.value = "' . esc_js( (string) $raw_sale ) . '";
				});
			</script>';
		}

		echo '<div class="options_group show_if_simple show_if_external show_if_variable">';
		wp_nonce_field( 'wc_currency_converter_save', 'wc_currency_converter_nonce' );

		woocommerce_wp_radio( array(
			'id'          => '_input_currency_type',
			'label'       => 'واحد ارز قیمت محصول:',
			'description' => 'ارزی که قیمت این محصول (یا پیش‌فرض متغیرها) بر اساس آن محاسبه می‌شود را تعیین کنید.',
			'desc_tip'    => true,
			'options'     => array(
				'irr' => 'تومان (بدون تبدیل)',
				'usd' => 'دلار (USD)',
				'eur' => 'یورو (EUR)',
				'cny' => 'یوان (CNY)',
			),
			'value'       => $selected_currency,
		) );
		echo '</div>';
	}

	/**
	 * Save currency meta on parent product.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function save_product_currency_meta( WC_Product $product ): void {
		if ( ! isset( $_POST['wc_currency_converter_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wc_currency_converter_nonce'] ), 'wc_currency_converter_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}

		$currency = isset( $_POST['_input_currency_type'] ) ? sanitize_text_field( wp_unslash( $_POST['_input_currency_type'] ) ) : 'irr';
		$product->update_meta_data( '_input_currency_type', $currency );

		if ( isset( $_POST['_regular_price'] ) ) {
			$product->update_meta_data( '_raw_input_regular_price', sanitize_text_field( wp_unslash( $_POST['_regular_price'] ) ) );
		}
		if ( isset( $_POST['_sale_price'] ) ) {
			$product->update_meta_data( '_raw_input_sale_price', sanitize_text_field( wp_unslash( $_POST['_sale_price'] ) ) );
		}

		// Invalidate variable price cache if variable product
		if ( $product->is_type( 'variable' ) ) {
			delete_transient( 'wc_var_prices_' . $product->get_id() );
		}
	}

	/**
	 * Add currency selection field to each variation row in the WooCommerce admin.
	 *
	 * @param int                $loop           Variation loop index.
	 * @param array<string,mixed> $variation_data Array of variation data.
	 * @param mixed              $variation      Variation post or object.
	 */
	public function add_variation_currency_fields( int $loop, array $variation_data, mixed $variation ): void {
		$variation_id = 0;
		if ( $variation instanceof WC_Product ) {
			$variation_id = $variation->get_id();
		} elseif ( is_object( $variation ) && isset( $variation->ID ) ) {
			$variation_id = (int) $variation->ID;
		} elseif ( is_numeric( $variation ) ) {
			$variation_id = (int) $variation;
		}

		if ( ! $variation_id ) {
			return;
		}

		$selected_currency = get_post_meta( $variation_id, '_input_currency_type', true );
		if ( empty( $selected_currency ) ) {
			$selected_currency = 'parent';
		}

		// Resolve parent product currency for the inheritance label
		$parent_id       = wp_get_post_parent_id( $variation_id );
		$parent_currency = $parent_id ? get_post_meta( $parent_id, '_input_currency_type', true ) : 'irr';
		if ( empty( $parent_currency ) || 'parent' === $parent_currency ) {
			$parent_currency = 'irr';
		}

		$currency_labels = array(
			'irr' => 'تومان (بدون تبدیل)',
			'usd' => 'دلار (USD)',
			'eur' => 'یورو (EUR)',
			'cny' => 'یوان (CNY)',
		);

		$parent_label = $currency_labels[ $parent_currency ] ?? 'تومان';

		$options = array(
			'parent' => sprintf( 'پیروی از محصول اصلی (%s)', $parent_label ),
			'irr'    => 'تومان (بدون تبدیل)',
			'usd'    => 'دلار (USD)',
			'eur'    => 'یورو (EUR)',
			'cny'    => 'یوان (CNY)',
		);

		echo '<div class="singo-variation-currency-box" style="margin: 8px 0; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">';
		woocommerce_wp_select( array(
			'id'            => '_variable_input_currency_type_' . $loop,
			'name'          => 'variable_input_currency_type[' . $loop . ']',
			'value'         => $selected_currency,
			'label'         => 'واحد ارز این متغیر:',
			'description'   => 'ارزی که قیمت این متغیر بر اساس آن وارد شده را تعیین کنید (پیش‌فرض: پیروی از محصول اصلی).',
			'desc_tip'      => true,
			'options'       => $options,
			'wrapper_class' => 'form-row-full',
		) );
		echo '</div>';
	}

	/**
	 * Save currency meta for a single variation via woocommerce_save_product_variation hook.
	 *
	 * @param int $variation_id Variation post ID.
	 * @param int $i            Variation loop index.
	 */
	public function save_variation_currency_meta( int $variation_id, int $i ): void {
		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}

		if ( isset( $_POST['variable_input_currency_type'][ $i ] ) ) {
			$currency = sanitize_text_field( wp_unslash( $_POST['variable_input_currency_type'][ $i ] ) );
			update_post_meta( $variation_id, '_input_currency_type', $currency );
		}

		if ( isset( $_POST['variable_regular_price'][ $i ] ) ) {
			$reg_price = sanitize_text_field( wp_unslash( $_POST['variable_regular_price'][ $i ] ) );
			update_post_meta( $variation_id, '_raw_input_regular_price', $reg_price );
		}

		if ( isset( $_POST['variable_sale_price'][ $i ] ) ) {
			$sale_price = sanitize_text_field( wp_unslash( $_POST['variable_sale_price'][ $i ] ) );
			update_post_meta( $variation_id, '_raw_input_sale_price', $sale_price );
		}

		// Clear cached variation prices on the parent product
		$parent_id = wp_get_post_parent_id( $variation_id );
		if ( $parent_id ) {
			delete_transient( 'wc_var_prices_' . $parent_id );
		}
	}

	/**
	 * Process variation object currency meta for HPOS and modern WooCommerce compatibility.
	 *
	 * @param WC_Product_Variation $variation Variation object.
	 * @param int                  $i         Loop index.
	 */
	public function process_variation_object_currency_meta( WC_Product_Variation $variation, int $i ): void {
		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}

		if ( isset( $_POST['variable_input_currency_type'][ $i ] ) ) {
			$currency = sanitize_text_field( wp_unslash( $_POST['variable_input_currency_type'][ $i ] ) );
			$variation->update_meta_data( '_input_currency_type', $currency );
		}

		if ( isset( $_POST['variable_regular_price'][ $i ] ) ) {
			$reg_price = sanitize_text_field( wp_unslash( $_POST['variable_regular_price'][ $i ] ) );
			$variation->update_meta_data( '_raw_input_regular_price', $reg_price );
		}

		if ( isset( $_POST['variable_sale_price'][ $i ] ) ) {
			$sale_price = sanitize_text_field( wp_unslash( $_POST['variable_sale_price'][ $i ] ) );
			$variation->update_meta_data( '_raw_input_sale_price', $sale_price );
		}
	}

	/**
	 * Invalidate variation prices transient cache when rates are updated or checked.
	 *
	 * @param array<mixed> $hash        Transient hash array.
	 * @param mixed        $product     Variable product object.
	 * @param bool         $for_display Whether hash is for display.
	 * @return array<mixed>
	 */
	public function filter_variation_prices_hash( array $hash, mixed $product, mixed $for_display ): array {
		$rate_usd = absint( singo_tools_get_option( 'rate_usd', 1 ) );
		$rate_eur = absint( singo_tools_get_option( 'rate_eur', 1 ) );
		$rate_cny = absint( singo_tools_get_option( 'rate_cny', 1 ) );

		$hash[] = "singo_{$rate_usd}_{$rate_eur}_{$rate_cny}";
		return $hash;
	}

	/**
	 * Resolve effective currency for a product or variation.
	 *
	 * @param WC_Product $product Product or variation object.
	 * @return string Currency slug ('irr', 'usd', 'eur', 'cny').
	 */
	public static function get_product_currency( WC_Product $product ): string {
		$currency = (string) $product->get_meta( '_input_currency_type' );

		// If variation is configured to follow parent or has no currency set
		if ( ( empty( $currency ) || 'parent' === $currency ) && $product->is_type( 'variation' ) ) {
			$parent_id = $product->get_parent_id();
			if ( $parent_id ) {
				$parent = wc_get_product( $parent_id );
				if ( $parent ) {
					$parent_currency = (string) $parent->get_meta( '_input_currency_type' );
					if ( ! empty( $parent_currency ) && 'parent' !== $parent_currency ) {
						return $parent_currency;
					}
				}
			}
		}

		if ( empty( $currency ) || 'parent' === $currency ) {
			return 'irr';
		}

		return $currency;
	}

	/**
	 * Check whether the current request is rendering the admin product or variation edit form.
	 *
	 * @return bool
	 */
	public static function is_admin_edit_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) $_REQUEST['action'] ) : '';
			$admin_edit_actions = array(
				'woocommerce_load_variations',
				'woocommerce_add_variation',
				'woocommerce_save_variations',
			);
			return in_array( $action, $admin_edit_actions, true );
		}

		global $pagenow;
		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Calculate dynamic converted price according to configured currency rate.
	 *
	 * @param mixed $price   Raw price.
	 * @param mixed $product Product or variation object.
	 * @return mixed
	 */
	private function calculate_dynamic_price( mixed $price, mixed $product ): mixed {
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			return $price;
		}

		if ( ! $product instanceof WC_Product ) {
			return $price;
		}

		// Avoid converting inside admin edit inputs so merchant edits raw value
		if ( self::is_admin_edit_screen() ) {
			$raw_reg = $product->get_meta( '_raw_input_regular_price' );
			if ( is_numeric( $raw_reg ) && '' !== $raw_reg ) {
				return floatval( $raw_reg );
			}
			return $price;
		}

		$currency = self::get_product_currency( $product );
		if ( empty( $currency ) || 'irr' === $currency ) {
			return $price;
		}

		$rate = match ( $currency ) {
			'usd'   => absint( singo_tools_get_option( 'rate_usd', 1 ) ),
			'eur'   => absint( singo_tools_get_option( 'rate_eur', 1 ) ),
			'cny'   => absint( singo_tools_get_option( 'rate_cny', 1 ) ),
			default => 1,
		};

		if ( $rate <= 0 ) {
			$rate = 1;
		}

		return floatval( $price ) * $rate;
	}

	/**
	 * Filter active dynamic price.
	 */
	public function get_dynamic_converted_price( mixed $price, mixed $product ): mixed {
		return $this->calculate_dynamic_price( $price, $product );
	}

	/**
	 * Filter regular dynamic price.
	 */
	public function get_dynamic_converted_regular_price( mixed $price, mixed $product ): mixed {
		if ( self::is_admin_edit_screen() && $product instanceof WC_Product ) {
			$raw_reg = $product->get_meta( '_raw_input_regular_price' );
			if ( is_numeric( $raw_reg ) && '' !== $raw_reg ) {
				return floatval( $raw_reg );
			}
			return $price;
		}
		return $this->calculate_dynamic_price( $price, $product );
	}

	/**
	 * Filter sale dynamic price.
	 */
	public function get_dynamic_converted_sale_price( mixed $price, mixed $product ): mixed {
		if ( self::is_admin_edit_screen() && $product instanceof WC_Product ) {
			$raw_sale = $product->get_meta( '_raw_input_sale_price' );
			if ( is_numeric( $raw_sale ) && '' !== $raw_sale ) {
				return floatval( $raw_sale );
			}
			return $price;
		}
		return $this->calculate_dynamic_price( $price, $product );
	}

	/**
	 * Handle deactivation: permanently convert all raw prices to Tomans in the database.
	 */
	public static function deactivate(): void {
		global $wpdb;
		$rate_usd = absint( singo_tools_get_option( 'rate_usd', 1 ) );
		$rate_eur = absint( singo_tools_get_option( 'rate_eur', 1 ) );
		$rate_cny = absint( singo_tools_get_option( 'rate_cny', 1 ) );

		$rates = array(
			'usd' => $rate_usd,
			'eur' => $rate_eur,
			'cny' => $rate_cny,
		);

		foreach ( $rates as $curr => $rate ) {
			if ( $rate <= 0 ) {
				$rate = 1;
			}

			// 1. Fetch directly matching products and variations
			$product_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = %s 
				AND meta_value = %s
			", '_input_currency_type', $curr ) );

			// 2. Fetch variations inheriting parent product currency
			$inherited_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.post_parent = pm.post_id
				LEFT JOIN {$wpdb->postmeta} pm_var ON p.ID = pm_var.post_id AND pm_var.meta_key = '_input_currency_type'
				WHERE p.post_type = 'product_variation'
				AND pm.meta_key = '_input_currency_type'
				AND pm.meta_value = %s
				AND (pm_var.meta_value IS NULL OR pm_var.meta_value = '' OR pm_var.meta_value = 'parent')
			", $curr ) );

			$all_ids = array_unique( array_merge( (array) $product_ids, (array) $inherited_ids ) );

			if ( empty( $all_ids ) ) {
				continue;
			}

			foreach ( $all_ids as $pid ) {
				$pid      = (int) $pid;
				$raw_reg  = get_post_meta( $pid, '_raw_input_regular_price', true );
				$raw_sale = get_post_meta( $pid, '_raw_input_sale_price', true );

				if ( is_numeric( $raw_reg ) && '' !== $raw_reg ) {
					update_post_meta( $pid, '_regular_price', floatval( $raw_reg ) * $rate );
				}
				if ( is_numeric( $raw_sale ) && floatval( $raw_sale ) > 0 ) {
					update_post_meta( $pid, '_sale_price', floatval( $raw_sale ) * $rate );
					update_post_meta( $pid, '_price', floatval( $raw_sale ) * $rate );
				} elseif ( is_numeric( $raw_reg ) && '' !== $raw_reg ) {
					update_post_meta( $pid, '_price', floatval( $raw_reg ) * $rate );
				}

				delete_transient( 'wc_product_children_' . $pid );
				delete_transient( 'wc_var_prices_' . $pid );

				$parent_id = wp_get_post_parent_id( $pid );
				if ( $parent_id ) {
					delete_transient( 'wc_product_children_' . $parent_id );
					delete_transient( 'wc_var_prices_' . $parent_id );
				}
			}
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}
	}

	/**
	 * Handle activation: restore raw currency prices for dynamic conversion.
	 */
	public static function activate(): void {
		global $wpdb;

		// 1. Directly configured products and variations
		$product_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = %s 
			AND meta_value != %s 
			AND meta_value != ''
			AND meta_value != 'parent'
		", '_input_currency_type', 'irr' ) );

		// 2. Variations inheriting parent currency
		$inherited_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.post_parent = pm.post_id
			LEFT JOIN {$wpdb->postmeta} pm_var ON p.ID = pm_var.post_id AND pm_var.meta_key = '_input_currency_type'
			WHERE p.post_type = 'product_variation'
			AND pm.meta_key = '_input_currency_type'
			AND pm.meta_value != %s
			AND pm.meta_value != ''
			AND (pm_var.meta_value IS NULL OR pm_var.meta_value = '' OR pm_var.meta_value = 'parent')
		", 'irr' ) );

		$all_ids = array_unique( array_merge( (array) $product_ids, (array) $inherited_ids ) );

		if ( empty( $all_ids ) ) {
			return;
		}

		foreach ( $all_ids as $pid ) {
			$pid      = (int) $pid;
			$raw_reg  = get_post_meta( $pid, '_raw_input_regular_price', true );
			$raw_sale = get_post_meta( $pid, '_raw_input_sale_price', true );

			if ( is_numeric( $raw_reg ) && '' !== $raw_reg ) {
				update_post_meta( $pid, '_regular_price', floatval( $raw_reg ) );
			}
			if ( is_numeric( $raw_sale ) && floatval( $raw_sale ) > 0 ) {
				update_post_meta( $pid, '_sale_price', floatval( $raw_sale ) );
				update_post_meta( $pid, '_price', floatval( $raw_sale ) );
			} elseif ( is_numeric( $raw_reg ) && '' !== $raw_reg ) {
				update_post_meta( $pid, '_price', floatval( $raw_reg ) );
			}

			delete_transient( 'wc_product_children_' . $pid );
			delete_transient( 'wc_var_prices_' . $pid );

			$parent_id = wp_get_post_parent_id( $pid );
			if ( $parent_id ) {
				delete_transient( 'wc_product_children_' . $parent_id );
				delete_transient( 'wc_var_prices_' . $parent_id );
			}
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}
	}
}

if ( ! class_exists( 'WC_Singo_Currency_Converter' ) ) {
	class_alias( 'Singo_Currency_Converter', 'WC_Singo_Currency_Converter' );
}
