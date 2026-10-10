<?php
declare(strict_types=1);

/**
 * Singo Tools - Product Swatches (سواچ محصولات متغیر)
 * PHP 8+ Standardized
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Product_Swatches {

	/**
	 * Singleton instance.
	 *
	 * @var Singo_Product_Swatches|null
	 */
	private static ?Singo_Product_Swatches $instance = null;

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
		// 1. تضمین ارسال همیشگی HTML قیمت و درصد تخفیف در آبجکت جاوا اسکریپت ووکامرس
		add_filter( 'woocommerce_available_variation', array( $this, 'enforce_variation_data_payload' ), 10, 3 );

		// 2. پنل ادمین: تنظیمات ویژگی‌ها (سواچ رنگی)
		add_action( 'woocommerce_after_add_attribute_fields', array( $this, 'add_attribute_field' ) );
		add_action( 'woocommerce_after_edit_attribute_fields', array( $this, 'edit_attribute_field' ) );
		add_action( 'woocommerce_attribute_added', array( $this, 'save_attribute_type' ) );
		add_action( 'woocommerce_attribute_updated', array( $this, 'save_attribute_type' ) );

		// 3. مدیریت فیلدهای آیتم‌های ویژگی (Terms)
		add_action( 'admin_init', array( $this, 'register_term_hooks' ) );

		// 4. لود استایل و کالرپیکر در پنل ادمین
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 99 );

		// 5. رندر ساختار سواچ در فرانت‌اند
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'render_swatch_dropdown_html' ), 10, 2 );

		// 6. اسکریپت تعاملی سواچ در فوتر فرانت‌اند
		if ( ! is_admin() ) {
			add_action( 'wp_footer', array( $this, 'render_frontend_scripts' ), 99 );
		}
	}

	/**
	 * تضمین ارسال همیشگی HTML قیمت و درصد تخفیف در آبجکت جاوا اسکریپت ووکامرس
	 */
	public function enforce_variation_data_payload( array $data, \WC_Product $product, \WC_Product_Variation $variation ): array {
		if ( empty( $data['price_html'] ) ) {
			$data['price_html'] = '<span class="price">' . $variation->get_price_html() . '</span>';
		}

		$regular_price = (float) $variation->get_regular_price();
		$sale_price    = (float) $variation->get_sale_price();

		if ( $regular_price > 0 && $sale_price > 0 && $sale_price < $regular_price ) {
			$data['discount_percent'] = round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 );
		} else {
			$data['discount_percent'] = 0;
		}

		return $data;
	}

	/**
	 * افزودن فیلد سوئیچ سواچ رنگی هنگام ایجاد ویژگی جدید در ووکامرس
	 */
	public function add_attribute_field(): void {
		?>
		<div class="form-field">
			<label for="attribute_is_color_swatch" style="display:block; margin-bottom: 8px;"><?php esc_html_e( 'سواچ رنگی', 'woocommerce' ); ?></label>
			<label class="sig-swatch-toggle">
				<input type="checkbox" name="attribute_is_color_swatch" id="attribute_is_color_swatch" value="yes" />
				<span class="sig-swatch-slider"></span>
			</label>
			<p class="description"><?php esc_html_e( 'در صورت فعال‌سازی، می‌توانید برای هر آیتم این ویژگی یک رنگ انتخاب کنید.', 'woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * نمایش فیلد سوئیچ سواچ رنگی هنگام ویرایش ویژگی موجود در ووکامرس
	 */
	public function edit_attribute_field(): void {
		$id       = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$is_color = get_option( "wc_attribute_swatch_type_{$id}", '' ) === 'color';
		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="attribute_is_color_swatch"><?php esc_html_e( 'سواچ رنگی', 'woocommerce' ); ?></label>
			</th>
			<td>
				<label class="sig-swatch-toggle">
					<input type="checkbox" name="attribute_is_color_swatch" id="attribute_is_color_swatch" value="yes" <?php checked( $is_color ); ?> />
					<span class="sig-swatch-slider"></span>
				</label>
				<p class="description"><?php esc_html_e( 'در صورت فعال‌سازی، می‌توانید برای هر آیتم این ویژگی یک رنگ انتخاب کنید.', 'woocommerce' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * ذخیره نوع سواچ ویژگی
	 */
	public function save_attribute_type( int $id ): void {
		$value = isset( $_POST['attribute_is_color_swatch'] ) ? 'color' : '';
		update_option( "wc_attribute_swatch_type_{$id}", $value );
	}

	/**
	 * ثبت هوک‌های فیلد رنگ برای ترم‌های ویژگی‌ها
	 */
	public function register_term_hooks(): void {
		global $pagenow;
		if ( ! in_array( $pagenow, array( 'edit-tags.php', 'term.php', 'admin.php', 'admin-ajax.php' ), true ) ) {
			return;
		}
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}

		$attribute_taxonomies = wc_get_attribute_taxonomies();
		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy    = wc_attribute_taxonomy_name( $tax->attribute_name );
			$swatch_type = get_option( "wc_attribute_swatch_type_{$tax->attribute_id}", '' );

			if ( 'color' === $swatch_type ) {
				add_action( "{$taxonomy}_add_form_fields", array( $this, 'add_term_fields' ) );
				add_action( "{$taxonomy}_edit_form_fields", array( $this, 'edit_term_fields' ), 10, 2 );
				add_action( "created_{$taxonomy}", array( $this, 'save_term_data' ) );
				add_action( "edited_{$taxonomy}", array( $this, 'save_term_data' ) );
			}
		}
	}

	/**
	 * فرم افزودن رنگ در ترم ویژگی
	 */
	public function add_term_fields( string $taxonomy ): void {
		?>
		<div class="form-field">
			<label for="swatch_color"><?php esc_html_e( 'رنگ', 'woocommerce' ); ?></label>
			<input type="text" name="swatch_color" id="swatch_color" class="swatch-color-picker" value="" />
		</div>
		<?php
	}

	/**
	 * فرم ویرایش رنگ در ترم ویژگی
	 */
	public function edit_term_fields( \WP_Term $term, string $taxonomy ): void {
		$color = get_term_meta( $term->term_id, 'swatch_color', true );
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="swatch_color"><?php esc_html_e( 'رنگ', 'woocommerce' ); ?></label></th>
			<td>
				<input type="text" name="swatch_color" id="swatch_color" class="swatch-color-picker" value="<?php echo esc_attr( (string) $color ); ?>" />
			</td>
		</tr>
		<?php
	}

	/**
	 * ذخیره رنگ ترم ویژگی
	 */
	public function save_term_data( int $term_id ): void {
		if ( isset( $_POST['swatch_color'] ) ) {
			$sanitized_color = sanitize_hex_color( wp_unslash( (string) $_POST['swatch_color'] ) );
			update_term_meta( $term_id, 'swatch_color', $sanitized_color );
		}
	}

	/**
	 * بارگذاری اسکریپت و استایل ادمین برای کالرپیکر و سوئیچ سواچ
	 */
	public function enqueue_admin_scripts(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_attr_page     = ( str_contains( $screen->id, 'product_attribute' ) );
		$is_taxonomy_page = ( str_contains( $screen->id, 'edit-pa_' ) || str_contains( $screen->id, 'pa_' ) );

		if ( $is_attr_page || $is_taxonomy_page ) {
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_style( 'wp-color-picker' );
			?>
			<style>
				.sig-swatch-toggle { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; }
				.sig-swatch-toggle input { opacity: 0; width: 0; height: 0; }
				.sig-swatch-slider { height: 24px; position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
				.sig-swatch-slider:before { position: absolute; content: ""; height: 18px; width: 18px; right: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
				.sig-swatch-toggle input:checked + .sig-swatch-slider { background-color: #4cd964; }
				.sig-swatch-toggle input:checked + .sig-swatch-slider:before { transform: translateX(-20px); }
			</style>
			<?php
			$js = "
				jQuery(document).ready(function($) {
					function sigInitColorPicker() {
						$('.swatch-color-picker').each(function() {
							if (!$(this).next().hasClass('wp-picker-container')) {
								$(this).wpColorPicker();
							}
						});
					}
					sigInitColorPicker();
					$(document).ajaxComplete(function(event, xhr, settings) {
						if (settings.data && settings.data.indexOf('action=add-tag') !== -1) {
							setTimeout(sigInitColorPicker, 200);
						}
					});
				});
			";
			wp_add_inline_script( 'wp-color-picker', $js );
		}
	}

	/**
	 * تشخیص سواچ رنگ از روی نام تاکسونومی (با کش ایستا)
	 */
	public static function get_type_by_taxonomy( string $taxonomy ): string {
		static $cache = array();
		if ( isset( $cache[ $taxonomy ] ) ) {
			return $cache[ $taxonomy ];
		}
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return '';
		}

		$attr_name            = str_replace( 'pa_', '', $taxonomy );
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		foreach ( $attribute_taxonomies as $a ) {
			if ( $a->attribute_name === $attr_name ) {
				$cache[ $taxonomy ] = (string) get_option( "wc_attribute_swatch_type_{$a->attribute_id}", '' );
				return $cache[ $taxonomy ];
			}
		}
		$cache[ $taxonomy ] = '';
		return '';
	}

	/**
	 * رندر ساختار سواچ در فرانت‌اند
	 */
	public function render_swatch_dropdown_html( string $html, array $args ): string {
		if ( empty( $args['attribute'] ) || empty( $args['product'] ) ) {
			return $html;
		}

		$attribute = (string) $args['attribute'];
		$product   = $args['product'];

		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return $html;
		}

		$terms = wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $html;
		}

		$is_color_attr = ( self::get_type_by_taxonomy( $attribute ) === 'color' );
		$attr_label    = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $attribute, $product ) : $attribute;

		ob_start();
		?>
		<div class="sig-attr-swatches" 
			 data-id="<?php echo esc_attr( $attribute ); ?>"
			 role="radiogroup"
			 aria-label="<?php echo esc_attr( $attr_label ); ?>">
			 
			<?php foreach ( $terms as $term ) : ?>
				<?php if ( $is_color_attr ) : ?>
					<?php $color = get_term_meta( $term->term_id, 'swatch_color', true ); ?>
					<span class="sig-attr-item sig-color-item" 
						  role="radio"
						  tabindex="0"
						  aria-checked="false"
						  style="background-color:<?php echo esc_attr( $color ? (string) $color : '#eee' ); ?>;" 
						  data-term-slug="<?php echo esc_attr( $term->slug ); ?>"
						  title="<?php echo esc_attr( $term->name ); ?>"
						  aria-label="<?php echo esc_attr( $term->name ); ?>">
					</span>
				<?php else : ?>
					<span class="sig-attr-item sig-text-item" 
						  role="radio"
						  tabindex="0"
						  aria-checked="false"
						  aria-label="<?php echo esc_attr( $term->name ); ?>"
						  data-term-slug="<?php echo esc_attr( $term->slug ); ?>">
						<?php echo esc_html( $term->name ); ?>
					</span>
				<?php endif; ?>
			<?php endforeach; ?>    
		</div>
		<?php
		$html .= ob_get_clean();
		return $html;
	}

	/**
	 * اسکریپت تعاملی سواچ در فوتر فرانت‌اند
	 */
	public function render_frontend_scripts(): void {
		if ( ! is_product() && ! has_block( 'woocommerce/product-template' ) && ! is_shop() && ! is_product_taxonomy() ) {
			return;
		}
		?>
		<script>
		(function() {
			function runWhenReady() {
				initVariationSwatches();
				initPriceSync();
				initSwatchAccessibility();
				initDiscountBadge();
			}
			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", runWhenReady);
			} else {
				runWhenReady();
			}

			// مدیریت سواچ‌ها و همگام‌سازی مستقیم با ووکامرس
			function initVariationSwatches() {
				const variationSelects = document.querySelectorAll(".variations td select");
				const allSwatches = document.querySelectorAll(".sig-attr-item");
				const resetBtns = document.querySelectorAll(".reset_variations");

				if (variationSelects.length === 0) return;

				variationSelects.forEach((select) => {
					const selectName = select.name;
					const siblingContainer = select.nextElementSibling;
					if (!siblingContainer) return;

					const swatches = [...siblingContainer.querySelectorAll(".sig-attr-item")];

					swatches.forEach((swatch) => {
						if (swatch.getAttribute("data-term-slug") === select.value) {
							swatch.classList.add("active");
							updateSelectedLabel(swatch);
						}

						swatch.addEventListener("click", () => {
							if (swatch.classList.contains("disabled")) return;

							const swatchValue = swatch.getAttribute("data-term-slug");
							const allSameSelects = document.querySelectorAll(`select[name="${selectName}"]`);

							allSameSelects.forEach((sameSelect) => {
								sameSelect.value = swatchValue;

								// [نکته حیاتی] تریگر نیتیو + تریگر جی‌کوئری برای اجبار ووکامرس به محاسبه
								sameSelect.dispatchEvent(new Event("change", { bubbles: true }));
								if (typeof jQuery !== 'undefined') {
									jQuery(sameSelect).trigger('change');
								}

								const parentContainer = sameSelect.nextElementSibling;
								if (!parentContainer) return;

								const localSwatches = [...parentContainer.querySelectorAll(".sig-attr-item")];
								localSwatches.forEach((s) => {
									s.classList.remove("active");
									if (s.getAttribute("data-term-slug") === swatchValue) {
										s.classList.add("active");
										updateSelectedLabel(s);
									}
								});
							});

							swatch.classList.add("active");
							checkOtherSelects(variationSelects, allSwatches);
						});
					});
				});

				if (typeof jQuery !== 'undefined') {
					jQuery(document).on('woocommerce_update_variation_values', () => {
						checkOtherSelects(variationSelects, allSwatches);
					});
				}
				checkOtherSelects(variationSelects, allSwatches);

				let isResetting = false;
				resetBtns.forEach((reset) => {
					reset.addEventListener("click", (e) => {
						if (isResetting) return;
						isResetting = true;

						resetBtns.forEach((r) => {
							if (r !== e.currentTarget) r.click();
						});

						allSwatches.forEach((swatch) => {
							swatch.classList.remove("active", "disabled");
							removeSelectedLabel(swatch);
						});

						setTimeout(() => { isResetting = false; }, 50);
					});
				});
			}

			function updateSelectedLabel(swatch) {
				const parentWrapper = swatch.closest(".sig-attr-swatches");
				if (!parentWrapper) return;
				const labelContainer = parentWrapper.parentElement.previousElementSibling;
				if (!labelContainer) return;

				const existingSpan = labelContainer.querySelector("span.sig-selected");
				if (existingSpan) existingSpan.remove();

				const swatchColor = swatch.style.backgroundColor;
				const labelText = swatch.getAttribute("aria-label") || "";

				if (swatchColor) {
					labelContainer.insertAdjacentHTML(
						'beforeend',
						`<span class="sig-selected">${labelText}<span class="sig-selected-color" style="background-color: ${swatchColor};"></span></span>`
					);
				} else {
					labelContainer.insertAdjacentHTML(
						'beforeend',
						`<span class="sig-selected">${labelText}</span>`
					);
				}
			}

			function removeSelectedLabel(swatch) {
				const parentWrapper = swatch.closest(".sig-attr-swatches");
				if (!parentWrapper) return;
				const labelContainer = parentWrapper.parentElement.previousElementSibling;
				if (!labelContainer) return;

				const existingSpan = labelContainer.querySelector("span.sig-selected");
				if (existingSpan) existingSpan.remove();
			}

			function checkOtherSelects(variationSelects, allSwatches) {
				allSwatches.forEach((s) => s.classList.remove("disabled"));
				variationSelects.forEach((select) => {
					const options = select.querySelectorAll("option");
					const dataVariation = [];
					options.forEach((option) => dataVariation.push(option.value));

					const siblingContainer = select.nextElementSibling;
					if (!siblingContainer) return;

					const swatches = [...siblingContainer.querySelectorAll(".sig-attr-item")];
					swatches.forEach((swatch) => {
						if (!dataVariation.includes(swatch.getAttribute("data-term-slug"))) {
							swatch.classList.add("disabled");
						}
					});
				});
			}

			// همگام‌سازی پایدار و قطعی قیمت
			function initPriceSync() {
				const form = document.querySelector(".variations_form");
				const priceBoxes = document.querySelectorAll(
					".sig-price .price, .sig-price > p, .sig-price, .elementor-widget-woocommerce-product-price .price"
				);

				if (!form || priceBoxes.length === 0) return;

				const initialPriceHtml = priceBoxes[0].innerHTML;

				// تابع کمکی برای اعمال انیمیشن فید نرم
				const fadeUpdatePrice = (newHtml) => {
					if (!newHtml) return;

					priceBoxes.forEach((box) => {
						box.classList.add("sig-price-fading");
					});

					setTimeout(() => {
						priceBoxes.forEach((box) => {
							box.innerHTML = newHtml;
							box.classList.remove("sig-price-fading");
						});
					}, 200); // زمان هماهنگ با تایم ترنزیشن در CSS (0.2s)
				};

				if (typeof jQuery !== 'undefined') {
					const $form = jQuery(form);

					// هنگام پیدا شدن و انتخاب متغیر
					$form.on("found_variation", (event, variation) => {
						if (variation && variation.price_html && variation.price_html.trim() !== "") {
							fadeUpdatePrice(variation.price_html);
						} else {
							const singlePriceContainer = document.querySelector(".woocommerce-variation-price .price");
							if (singlePriceContainer) {
								fadeUpdatePrice(singlePriceContainer.innerHTML);
							}
						}
					});

					// هنگام لغو انتخاب یا ریست متغیرها
					$form.on("reset_data", () => {
						fadeUpdatePrice(initialPriceHtml);
					});
				}
			}

			function initSwatchAccessibility() {
				document.addEventListener("keydown", function (event) {
					if (event.target.classList.contains("sig-attr-item")) {
						if (event.key === "Enter" || event.key === " " || event.code === "Space") {
							event.preventDefault();
							event.target.click();
						}
					}
				});

				document.addEventListener("click", function (event) {
					const swatch = event.target.closest(".sig-attr-item");
					if (swatch) {
						const swatchGroup = swatch.closest(".sig-attr-swatches");
						if (swatchGroup) {
							const allItems = swatchGroup.querySelectorAll(".sig-attr-item");
							allItems.forEach((item) => item.setAttribute("aria-checked", "false"));
							swatch.setAttribute("aria-checked", "true");
						}
					}
				});
			}

			function initDiscountBadge() {
				const form = document.querySelector(".variations_form");
				const badge = document.querySelector(".sig-discount-percentage");

				if (!form || !badge) return;

				const badgeValue = badge.querySelector(".elementor-heading-title");
				if (!badgeValue) return;

				const originalBadgeText = badgeValue.textContent;

				if (typeof jQuery !== 'undefined') {
					jQuery(form).on("show_variation", (event, variation) => {
						const discount = variation.discount_percent;

						if (discount && discount > 0) {
							badgeValue.textContent = `%${discount}`;
							badge.classList.remove("hidden");
						} else {
							badge.classList.add("hidden");
						}
					});

					jQuery(form).on("hide_variation", () => {
						badgeValue.textContent = originalBadgeText;
						badge.classList.remove("hidden");
					});
				}
			}
		})();
		</script>
		<?php
	}
}
