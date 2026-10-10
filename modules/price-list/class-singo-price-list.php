<?php
declare(strict_types=1);

/**
 * Singo Tools - Price List PDF Exporter Module (Singo_Price_List)
 * PHP 8+ Standardized & WPDS Compliant
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Price_List {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * In-memory request cache for products query results.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private static array $products_cache = array();

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
		// Admin action to view and print PDF template
		add_action( 'admin_post_singo_export_price_list_pdf', array( $this, 'render_pdf_template' ) );

		// AJAX endpoint for live preview table in Optionino tab
		add_action( 'wp_ajax_singo_get_price_list_preview', array( $this, 'ajax_get_price_list_preview' ) );

		// AJAX endpoint to save custom font URL setting
		add_action( 'wp_ajax_singo_save_price_list_font_url', array( $this, 'ajax_save_font_url' ) );

		// AJAX endpoint to save custom brand/primary color
		add_action( 'wp_ajax_singo_save_price_list_primary_color', array( $this, 'ajax_save_primary_color' ) );

		// Allow font uploads in WordPress media library (Admin only for zero frontend overhead)
		if ( is_admin() ) {
			add_filter( 'upload_mimes', array( $this, 'allow_font_mime_types' ) );
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_font_filetype_check' ), 10, 5 );
		}
	}

	/**
	 * Render the PDF template.
	 */
	public function render_pdf_template(): void {
		// Verify capability first
		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_die( 'سطح دسترسی شما برای دریافت این گزارش کافی نیست.', 'خطای دسترسی', array( 'response' => 403 ) );
		}

		// Verify nonce securely across popups and new tabs without HTTP referer drop failure
		$nonce = isset( $_REQUEST['security'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['security'] ) ) : ( isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['_wpnonce'] ) ) : '' );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'singo_export_price_list_nonce' ) ) {
			wp_die( 'پیوند منقضی شده است یا نشست کاربری نامعتبر است. لطفاً صفحه تنظیمات را بارگذاری مجدد نموده و دوباره تلاش نمایید.', 'پیوند منقضی شده است', array( 'response' => 403 ) );
		}

		$template_path = SINGO_TOOLS_PATH . 'modules/price-list/views/pdf-template.php';
		if ( file_exists( $template_path ) ) {
			require $template_path;
			exit;
		} else {
			wp_die( 'فایل قالب پرینت یافت نشد.' );
		}
	}

	/**
	 * AJAX Handler: Fetch Price List Preview.
	 */
	public function ajax_get_price_list_preview(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );

		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$category_id      = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$in_stock         = ! isset( $_POST['in_stock'] ) || '1' === (string) $_POST['in_stock'] || 'true' === (string) $_POST['in_stock'];
		$orderby          = isset( $_POST['orderby'] ) ? sanitize_key( (string) $_POST['orderby'] ) : 'title';
		$order            = isset( $_POST['order'] ) ? sanitize_key( (string) $_POST['order'] ) : 'ASC';
		$discount_percent = isset( $_POST['discount_percent'] ) ? max( 0.0, min( 100.0, floatval( $_POST['discount_percent'] ) ) ) : 0.0;
		$hide_strike      = isset( $_POST['hide_strike'] ) && ( '1' === (string) $_POST['hide_strike'] || 'true' === (string) $_POST['hide_strike'] );
		$expand_vars      = ! isset( $_POST['expand_variations'] ) || '1' === (string) $_POST['expand_variations'] || 'true' === (string) $_POST['expand_variations'];

		$products = $this->get_products_data( $category_id, $in_stock, $orderby, $order, $discount_percent, $hide_strike, $expand_vars );

		ob_start();
		if ( empty( $products ) ) {
			echo '<tr><td colspan="4" style="text-align:center; padding:30px; color:#64748b;">هیچ محصولی با معیارهای انتخابی یافت نشد.</td></tr>';
		} else {
			foreach ( $products as $index => $prod ) {
				$row_num = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) ( $index + 1 ) ) : (string) ( $index + 1 );
				?>
				<tr>
					<td style="text-align: center; font-weight: bold; color: #64748b; padding: 10px 8px;"><?php echo esc_html( $row_num ); ?></td>
					<td style="text-align: center; padding: 6px;">
						<?php if ( ! empty( $prod['thumb_url'] ) ) : ?>
							<img src="<?php echo esc_url( $prod['thumb_url'] ); ?>" alt="<?php echo esc_attr( $prod['title'] ); ?>" style="width: 44px; height: 44px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; vertical-align: middle;" />
						<?php else : ?>
							<span style="font-size: 11px; color: #94a3b8;">بدون تصویر</span>
						<?php endif; ?>
					</td>
					<td style="text-align: right; padding: 10px 14px;">
						<strong style="color: #0f172a; font-size: 13.5px;"><?php echo esc_html( $prod['title'] ); ?></strong>
						<?php if ( ! empty( $prod['sku'] ) ) : ?>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;">کد: <?php echo esc_html( class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( $prod['sku'] ) : $prod['sku'] ); ?></div>
						<?php endif; ?>
					</td>
					<td class="singo-preview-price-cell" style="text-align: center; padding: 10px; font-weight: bold; color: <?php echo esc_attr( self::get_active_primary_color() ); ?>; white-space: nowrap;">
						<?php echo wp_kses_post( $prod['price_html'] ); ?>
					</td>
				</tr>
				<?php
			}
		}
		$html = ob_get_clean();

		wp_send_json_success( array(
			'count'      => count( $products ),
			'count_fa'   => class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) count( $products ) ) : (string) count( $products ),
			'table_html' => $html,
		) );
	}

	/**
	 * AJAX Handler: Save Custom Font URL.
	 */
	public function ajax_save_font_url(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );

		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$font_url = isset( $_POST['font_url'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['font_url'] ) ) : '';
		if ( ! empty( $font_url ) ) {
			$font_url = esc_url_raw( $font_url );
		}

		update_option( 'singo_price_list_font_url', $font_url );

		wp_send_json_success( array(
			'message'  => 'آدرس فونت با موفقیت ذخیره شد.',
			'font_url' => $font_url,
		) );
	}

	/**
	 * AJAX Handler: Save Custom Brand/Primary Color.
	 */
	public function ajax_save_primary_color(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );

		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$raw_color   = isset( $_POST['primary_color'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['primary_color'] ) ) : '';
		$clean_color = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $raw_color ) : null;
		if ( empty( $clean_color ) ) {
			$clean_color = '#ad6141';
		}

		update_option( 'singo_price_list_primary_color', $clean_color );

		wp_send_json_success( array(
			'message'       => 'رنگ سازمانی با موفقیت ذخیره شد.',
			'primary_color' => $clean_color,
		) );
	}

	/**
	 * Allow font mime types in WordPress media uploader.
	 *
	 * @param array<string, string> $mimes Allowed mime types.
	 * @return array<string, string>
	 */
	public function allow_font_mime_types( array $mimes ): array {
		if ( ! isset( $mimes['woff2'] ) ) {
			$mimes['woff2'] = 'font/woff2';
		}
		if ( ! isset( $mimes['woff'] ) ) {
			$mimes['woff'] = 'font/woff';
		}
		if ( ! isset( $mimes['ttf'] ) ) {
			$mimes['ttf'] = 'font/ttf';
		}
		if ( ! isset( $mimes['otf'] ) ) {
			$mimes['otf'] = 'font/otf';
		}
		if ( ! isset( $mimes['eot'] ) ) {
			$mimes['eot'] = 'application/vnd.ms-fontobject';
		}
		return $mimes;
	}

	/**
	 * Correct filetype check for font files in WordPress.
	 *
	 * @param array<string, mixed> $data      File data.
	 * @param string               $file      Full path to file.
	 * @param string               $filename  Filename.
	 * @param array<string, string>|null $mimes Mime types array.
	 * @param string|null          $real_mime Real mime type.
	 * @return array<string, mixed>
	 */
	public function fix_font_filetype_check( array $data, string $file, string $filename, ?array $mimes, ?string $real_mime = null ): array {
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'woff2' === $ext ) {
			$data['ext']  = 'woff2';
			$data['type'] = 'font/woff2';
		} elseif ( 'woff' === $ext ) {
			$data['ext']  = 'woff';
			$data['type'] = 'font/woff';
		} elseif ( 'ttf' === $ext ) {
			$data['ext']  = 'ttf';
			$data['type'] = 'font/ttf';
		} elseif ( 'otf' === $ext ) {
			$data['ext']  = 'otf';
			$data['type'] = 'font/otf';
		}

		return $data;
	}

	/**
	 * Get the active font URL configured by the user.
	 *
	 * @return string Font URL or empty string if not configured.
	 */
	public static function get_active_font_url(): string {
		$saved = (string) get_option( 'singo_price_list_font_url', '' );
		if ( ! empty( $saved ) ) {
			if ( str_starts_with( $saved, '/' ) && function_exists( 'home_url' ) ) {
				return home_url( $saved );
			}
			return $saved;
		}
		return '';
	}

	/**
	 * Get the active brand/primary color for the PDF export.
	 *
	 * @return string Hex color code.
	 */
	public static function get_active_primary_color(): string {
		$saved = (string) get_option( 'singo_price_list_primary_color', '#ad6141' );
		if ( function_exists( 'sanitize_hex_color' ) ) {
			$clean = sanitize_hex_color( $saved );
			if ( ! empty( $clean ) ) {
				return $clean;
			}
		}
		return ! empty( $saved ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $saved ) ? $saved : '#ad6141';
	}

	/**
	 * Query WooCommerce products and format price/image data.
	 *
	 * @param int    $category_id      Category ID or 0 for all.
	 * @param bool   $in_stock         Only in-stock products.
	 * @param string $orderby          Orderby field.
	 * @param string $order            ASC or DESC.
	 * @param float  $discount_percent Global discount percentage (0 to 100).
	 * @param bool   $hide_strike      Whether to hide strikethrough original price.
	 * @param bool   $expand_variations Whether to expand variable products into separate rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_products_data(
		int $category_id = 0,
		bool $in_stock = true,
		string $orderby = 'title',
		string $order = 'ASC',
		float $discount_percent = 0.0,
		bool $hide_strike = false,
		bool $expand_variations = true
	): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		// Fast return from in-memory request cache if query was already processed
		$cache_key = md5( $category_id . '_' . ( $in_stock ? '1' : '0' ) . '_' . $orderby . '_' . $order . '_' . $discount_percent . '_' . ( $hide_strike ? '1' : '0' ) . '_' . ( $expand_variations ? '1' : '0' ) );
		if ( isset( self::$products_cache[ $cache_key ] ) ) {
			return self::$products_cache[ $cache_key ];
		}

		$query_args = array(
			'status'        => 'publish',
			'limit'         => 500, // Safe ceiling for price lists
			'paginate'      => false,
			'no_found_rows' => true, // Lightweight: Bypasses SQL_CALC_FOUND_ROWS in database
		);

		if ( $category_id > 0 ) {
			$term = get_term( $category_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$query_args['category'] = array( $term->slug );
			}
		}

		if ( $in_stock ) {
			$query_args['stock_status'] = 'instock';
		}

		// Ordering
		switch ( $orderby ) {
			case 'price':
			case 'price_asc':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_price';
				$query_args['order']    = 'ASC';
				break;
			case 'price_desc':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_price';
				$query_args['order']    = 'DESC';
				break;
			case 'date':
				$query_args['orderby'] = 'date';
				$query_args['order']   = 'DESC';
				break;
			case 'title':
			default:
				$query_args['orderby'] = 'title';
				$query_args['order']   = $order;
				break;
		}

		$products = wc_get_products( $query_args );
		$results  = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			// Format parent thumbnail
			$parent_thumb_id  = $product->get_image_id();
			$parent_thumb_url = '';
			if ( $parent_thumb_id ) {
				$parent_thumb_src = wp_get_attachment_image_src( (int) $parent_thumb_id, 'woocommerce_thumbnail' );
				if ( ! empty( $parent_thumb_src[0] ) ) {
					$parent_thumb_url = $parent_thumb_src[0];
				}
			}
			if ( empty( $parent_thumb_url ) && function_exists( 'wc_placeholder_img_src' ) ) {
				$parent_thumb_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
			}

			// If expanding variable product variations into separate rows
			if ( $expand_variations && $product->is_type( 'variable' ) ) {
				$children_ids = $product->get_children();
				if ( ! empty( $children_ids ) ) {
					foreach ( $children_ids as $child_id ) {
						$variation = wc_get_product( $child_id );
						if ( ! $variation instanceof WC_Product ) {
							continue;
						}

						if ( $in_stock && method_exists( $variation, 'is_in_stock' ) && ! $variation->is_in_stock() ) {
							continue;
						}

						// Variation Image: Use variation thumbnail if set, otherwise parent
						$var_thumb_id  = $variation->get_image_id();
						$var_thumb_url = '';
						if ( $var_thumb_id ) {
							$var_thumb_src = wp_get_attachment_image_src( (int) $var_thumb_id, 'woocommerce_thumbnail' );
							if ( ! empty( $var_thumb_src[0] ) ) {
								$var_thumb_url = $var_thumb_src[0];
							}
						}
						if ( empty( $var_thumb_url ) ) {
							$var_thumb_url = $parent_thumb_url;
						}

						// Variation title in same cell with attribute details
						$attr_desc     = $this->get_formatted_variation_attributes( $variation, $product );
						$display_title = $product->get_name();
						if ( ! empty( $attr_desc ) ) {
							$display_title .= ' (' . $attr_desc . ')';
						}

						$var_sku = $variation->get_sku();
						if ( empty( $var_sku ) ) {
							$var_sku = $product->get_sku();
						}

						$var_price_html = $this->format_toman_price_for_display( $variation, $discount_percent, $hide_strike );

						$results[] = array(
							'id'         => $variation->get_id(),
							'title'      => $display_title,
							'sku'        => $var_sku,
							'thumb_url'  => $var_thumb_url,
							'price_html' => $var_price_html,
						);
					}
					continue;
				}
			}

			// Simple product or variable product without children / expansion disabled
			$price_html = $this->format_toman_price_for_display( $product, $discount_percent, $hide_strike );

			$results[] = array(
				'id'         => $product->get_id(),
				'title'      => $product->get_name(),
				'sku'        => $product->get_sku(),
				'thumb_url'  => $parent_thumb_url,
				'price_html' => $price_html,
			);
		}

		// Store in request cache
		self::$products_cache[ $cache_key ] = $results;

		return $results;
	}

	/**
	 * Format attributes of a variation into a clean Persian label.
	 * Decodes URL-encoded slugs (%d8%...), strips taxonomy prefixes (pa_...),
	 * and queries official term names from WordPress taxonomy.
	 *
	 * @param WC_Product $variation Variation object.
	 * @param WC_Product $parent    Parent product object.
	 * @return string Formatted attributes string (e.g. "رنگ: سفید • مدل: شیری").
	 */
	public function get_formatted_variation_attributes( WC_Product $variation, WC_Product $parent ): string {
		if ( ! method_exists( $variation, 'get_attributes' ) ) {
			return '';
		}

		$attributes = $variation->get_attributes();
		if ( empty( $attributes ) ) {
			return '';
		}

		$formatted_parts = array();
		foreach ( $attributes as $taxonomy_or_key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}

			// 1. Clean and Resolve Attribute Key / Taxonomy
			$raw_key = (string) $taxonomy_or_key;
			if ( str_starts_with( $raw_key, 'attribute_' ) ) {
				$raw_key = substr( $raw_key, 10 );
			}

			// Determine best matching taxonomy (e.g. pa_color, pa_رنگ)
			$taxonomy = '';
			if ( taxonomy_exists( $raw_key ) ) {
				$taxonomy = $raw_key;
			} elseif ( taxonomy_exists( urldecode( $raw_key ) ) ) {
				$taxonomy = urldecode( $raw_key );
			} elseif ( ! str_starts_with( $raw_key, 'pa_' ) && taxonomy_exists( 'pa_' . $raw_key ) ) {
				$taxonomy = 'pa_' . $raw_key;
			} elseif ( ! str_starts_with( $raw_key, 'pa_' ) && taxonomy_exists( 'pa_' . urldecode( $raw_key ) ) ) {
				$taxonomy = 'pa_' . urldecode( $raw_key );
			}

			// Resolve Attribute Label
			$label = '';
			if ( ! empty( $taxonomy ) && function_exists( 'wc_attribute_label' ) ) {
				$label = wc_attribute_label( $taxonomy, $parent );
			} elseif ( function_exists( 'wc_attribute_label' ) ) {
				$label = wc_attribute_label( $raw_key, $parent );
			}

			if ( empty( $label ) || $label === $raw_key || $label === $taxonomy ) {
				$label = preg_replace( '/^pa_/iu', '', urldecode( $raw_key ) );
			}
			$label = urldecode( (string) $label );
			$label = preg_replace( '/^pa_/iu', '', $label );
			$label = trim( $label );

			// 2. Decode & Resolve Attribute Value
			$raw_val = (string) $value;

			// Fully URL-decode the value
			$decoded_val = urldecode( $raw_val );
			while ( str_contains( $decoded_val, '%' ) && urldecode( $decoded_val ) !== $decoded_val ) {
				$decoded_val = urldecode( $decoded_val );
			}

			// Strip any leading pa_...- or pa_..._ prefix (e.g. pa_رنگ-سفید -> سفید)
			$stripped_val = preg_replace( '/^pa_[^\-_]+[\-_]/u', '', $decoded_val );
			$stripped_val = trim( $stripped_val );

			// Try to find official Term Name in WooCommerce taxonomy
			$term = null;
			$target_tax = ! empty( $taxonomy ) ? $taxonomy : $raw_key;

			if ( taxonomy_exists( $target_tax ) ) {
				// Candidate slugs to test in get_term_by
				$candidates = array_unique( array_filter( array(
					$stripped_val,
					$decoded_val,
					$raw_val,
					urldecode( $raw_val ),
					function_exists( 'sanitize_title' ) ? sanitize_title( $stripped_val ) : $stripped_val,
				) ) );

				foreach ( $candidates as $candidate ) {
					$found_term = get_term_by( 'slug', $candidate, $target_tax );
					if ( ! $found_term || is_wp_error( $found_term ) ) {
						$found_term = get_term_by( 'name', $candidate, $target_tax );
					}
					if ( $found_term && ! is_wp_error( $found_term ) ) {
						$term = $found_term;
						break;
					}
				}
			}

			if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) {
				$final_val = (string) $term->name;
			} else {
				$final_val = ! empty( $stripped_val ) ? $stripped_val : $decoded_val;
			}

			// Clean up any remaining URL encoding or unwanted chars
			$final_val = urldecode( $final_val );
			$final_val = trim( $final_val );

			if ( ! empty( $label ) && ! empty( $final_val ) ) {
				$formatted_parts[] = esc_html( $label ) . ': ' . esc_html( $final_val );
			} elseif ( ! empty( $final_val ) ) {
				$formatted_parts[] = esc_html( $final_val );
			}
		}

		return implode( ' • ', $formatted_parts );
	}

	/**
	 * Format product price in Tomans.
	 * Displays sale price on top, and crossed-out regular price cleanly beneath it.
	 * Supports global discount percentage and toggle to hide strikethrough price.
	 *
	 * @param WC_Product $product          Product object.
	 * @param float      $discount_percent Global discount percentage (0 to 100).
	 * @param bool       $hide_strike      Whether to hide strikethrough regular price.
	 * @return string Formatted price string.
	 */
	public function format_toman_price_for_display( WC_Product $product, float $discount_percent = 0.0, bool $hide_strike = false ): string {
		if ( $product->is_type( 'variable' ) ) {
			$min_price = $product->get_variation_price( 'min', true );
			$max_price = $product->get_variation_price( 'max', true );

			if ( empty( $min_price ) && 0 !== $min_price && '0' !== $min_price ) {
				return '<span style="color:#64748b; font-size:11px;">تماس بگیرید</span>';
			}

			// Apply global discount if set
			if ( $discount_percent > 0.0 ) {
				$discount_factor = max( 0.0, 1.0 - ( $discount_percent / 100.0 ) );
				$disc_min        = round( floatval( $min_price ) * $discount_factor );
				$sale_str        = ( $min_price === $max_price ) ? $this->format_number_to_toman( $disc_min ) : 'از ' . $this->format_number_to_toman( $disc_min );
				$reg_str         = ( $min_price === $max_price ) ? $this->format_number_to_toman( $min_price ) : 'از ' . $this->format_number_to_toman( $min_price );

				if ( $hide_strike ) {
					return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $sale_str . '</div>';
				}

				return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $sale_str . '</div>' .
				       '<del class="singo-price-regular" style="display: block; color: #94a3b8; text-decoration: line-through; font-size: 11px; font-weight: normal; margin-top: 2px; line-height: 1.2;">' . $reg_str . '</del>';
			}

			// Variable product on sale
			if ( $product->is_on_sale() && method_exists( $product, 'get_variation_regular_price' ) ) {
				$min_regular = $product->get_variation_regular_price( 'min', true );
				if ( ! empty( $min_regular ) && floatval( $min_regular ) > floatval( $min_price ) ) {
					$sale_str    = ( $min_price === $max_price ) ? $this->format_number_to_toman( $min_price ) : 'از ' . $this->format_number_to_toman( $min_price );
					$regular_str = ( $min_price === $max_price ) ? $this->format_number_to_toman( $min_regular ) : 'از ' . $this->format_number_to_toman( $min_regular );

					if ( $hide_strike ) {
						return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $sale_str . '</div>';
					}

					return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $sale_str . '</div>' .
					       '<del class="singo-price-regular" style="display: block; color: #94a3b8; text-decoration: line-through; font-size: 11px; font-weight: normal; margin-top: 2px; line-height: 1.2;">' . $regular_str . '</del>';
				}
			}

			if ( $min_price === $max_price ) {
				return $this->format_number_to_toman( $min_price );
			}

			return 'از ' . $this->format_number_to_toman( $min_price );
		}

		$price = $product->get_price();
		if ( empty( $price ) && 0 !== $price && '0' !== $price ) {
			return '<span style="color:#64748b; font-size:11px;">تماس بگیرید</span>';
		}

		$current_num = floatval( $price );

		// Apply global discount if set
		if ( $discount_percent > 0.0 ) {
			$discount_factor  = max( 0.0, 1.0 - ( $discount_percent / 100.0 ) );
			$discounted_price = round( $current_num * $discount_factor );

			if ( $hide_strike ) {
				return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $this->format_number_to_toman( $discounted_price ) . '</div>';
			}

			return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $this->format_number_to_toman( $discounted_price ) . '</div>' .
			       '<del class="singo-price-regular" style="display: block; color: #94a3b8; text-decoration: line-through; font-size: 11px; font-weight: normal; margin-top: 2px; line-height: 1.2;">' . $this->format_number_to_toman( $current_num ) . '</del>';
		}

		$regular = $product->get_regular_price();
		if ( $product->is_on_sale() && ! empty( $regular ) && floatval( $regular ) > $current_num ) {
			if ( $hide_strike ) {
				return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $this->format_number_to_toman( $price ) . '</div>';
			}

			return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $this->format_number_to_toman( $price ) . '</div>' .
			       '<del class="singo-price-regular" style="display: block; color: #94a3b8; text-decoration: line-through; font-size: 11px; font-weight: normal; margin-top: 2px; line-height: 1.2;">' . $this->format_number_to_toman( $regular ) . '</del>';
		}

		return $this->format_number_to_toman( $price );
	}

	/**
	 * Helper: Format numeric price into Persian formatted string with 'تومان'.
	 *
	 * @param mixed $price Raw price.
	 * @return string
	 */
	private function format_number_to_toman( mixed $price ): string {
		$formatted = number_format( (float) $price, 0, '.', ',' );
		if ( class_exists( 'Singo_Shamsi_Converter' ) ) {
			$formatted = Singo_Shamsi_Converter::convert_to_persian_digits( $formatted );
		}
		return $formatted . ' تومان';
	}
}

if ( ! class_exists( 'SDM_Price_List' ) ) {
	class_alias( 'Singo_Price_List', 'SDM_Price_List' );
}
