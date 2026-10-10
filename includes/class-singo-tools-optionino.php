<?php
declare(strict_types=1);

/**
 * Optionino Framework Integration for Singo Tools (Singo_Tools_Optionino)
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Tools_Optionino {

	/**
	 * Initialize Optionino settings.
	 */
	public static function init(): void {
		if ( ! class_exists( 'OPTNNO' ) ) {
			return;
		}

		self::set_config();
		self::set_tabs();

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_debug_submenu' ), 20 );
	}

	/**
	 * Register Debugger & Logs Submenu Page under Singo Tools main menu.
	 */
	public static function register_debug_submenu(): void {
		add_submenu_page(
			'singo-tools',
			'دیباگ و لاگ‌های سیستم',
			'دیباگ و لاگ‌ها',
			singo_tools_get_admin_capability(),
			'singo-tools-debug',
			array( __CLASS__, 'render_debug_page' )
		);
	}

	/**
	 * Render Standalone Debugger & Logs Submenu Page.
	 */
	public static function render_debug_page(): void {
		if ( ! singo_tools_current_user_can_manage() ) {
			wp_die( 'سطح دسترسی ناکافی است.' );
		}

		if ( class_exists( 'OPTNNO' ) ) {
			$optnno_instance = new OPTNNO();
			$optnno_instance->admin_scripts();
		}
		?>
		<div id="optionino" class="optionino-container" style="direction: rtl; margin-top: 20px; max-width: 1200px;">
			<form id="save-options-optionino" method="post" action="">
				<input type="hidden" name="dev_name" value="singo_tools_options">
				<input type="hidden" name="security" value="<?php echo wp_create_nonce( 'optionino_nonce' ); ?>">
				<?php
				if ( file_exists( SINGO_TOOLS_PATH . 'modules/debug/views/admin-debug-view.php' ) ) {
					require SINGO_TOOLS_PATH . 'modules/debug/views/admin-debug-view.php';
				}
				?>
				<div class="optionino-options-save-box" style="margin-top: 20px; display: flex; justify-content: flex-end; align-items: center; gap: 15px; padding-bottom: 20px;">
					<button type="submit" class="button submit-optionino" style="background-color: #ad6141; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-weight: bold;">
						ذخیره وضعیت سرویس عیب‌یابی
					</button>
					<span class="error-text" style="display:none; color: red;"></span>
					<span class="success-text" style="display:none; color: green;"></span>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue WP Admin styles adhering strictly to WordPress 6.7 standards via wp_add_inline_style.
	 */
	public static function enqueue_admin_styles(): void {
		// 1. Global Admin Menu Icon Styling
		$global_css = "
			#adminmenu #toplevel_page_singo-tools .wp-menu-image img {
				opacity: 1 !important;
				filter: brightness(0) invert(1) !important;
				transition: all 0.2s ease-in-out !important;
			}
			#adminmenu #toplevel_page_singo-tools:hover > a,
			#adminmenu #toplevel_page_singo-tools.wp-has-current-submenu > a,
			#adminmenu #toplevel_page_singo-tools.current > a {
				background-color: #ad6141 !important;
			}
		";

		wp_register_style( 'singo-tools-admin-style', false, array(), SINGO_TOOLS_VERSION );
		wp_enqueue_style( 'singo-tools-admin-style' );
		wp_add_inline_style( 'singo-tools-admin-style', $global_css );

		// 2. Optionino Settings Page Styling & Color Swatch Initialization
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( $screen->id !== 'toplevel_page_singo-tools' && $screen->id !== 'singo-tools_page_singo-tools-debug' && $screen->id !== 'toplevel_page_shamsi-date-master' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		$color_picker_js = "
			jQuery(document).ready(function($) {
				function initOptioninoColorPickers() {
					if ($.fn.wpColorPicker) {
						$('.optionino-color-selector').not('.wp-color-picker').each(function() {
							$(this).wpColorPicker({
								change: function(event, ui) {
									$(this).val(ui.color.toString()).trigger('change');
								},
								clear: function() {
									$(this).trigger('change');
								}
							});
						});
					}
				}
				initOptioninoColorPickers();
				$(document).on('click', '.tablinks', function() {
					setTimeout(initOptioninoColorPickers, 50);
				});
				document.addEventListener('change', function() {
					setTimeout(initOptioninoColorPickers, 50);
				}, true);

				$(document).on('click', '.upload-image-button', function(e) {
					e.preventDefault();
					var button = $(this);
					var fieldId = button.attr('data-image-field');
					if (!fieldId) return;

					if (typeof wp === 'undefined' || !wp.media) {
						return;
					}

					var frame = wp.media({
						title: 'انتخاب یا آپلود تصویر لوگو',
						button: { text: 'استفاده از این تصویر' },
						multiple: false
					});

					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						var url = attachment.url;
						$('#' + fieldId).val(url).trigger('change');
						var \$preview = $('#' + fieldId + '-preview');
						if (\$preview.length) {
							\$preview.attr('src', url).show();
						}
					});

					frame.open();
				});
			});
		";
		wp_add_inline_script( 'wp-color-picker', $color_picker_js );

		$primary_color = '#b16241';
		$page_css      = "
			:root {
				--optionino-main-color: " . esc_attr( $primary_color ) . " !important;
				--optionino-main-font: inherit !important;
			}
			.optionino-container,
			.optionino-container *,
			.optionino-section,
			.optionino-tab-titles-box,
			.optionino-form-label,
			.optionino-input,
			.optionino-select {
				font-family: inherit !important;
			}
			.optionino-section {
				min-height: calc(100vh - 180px) !important;
			}
			.optionino-section .tab-information > img {
				height: 150px !important;
				width: auto !important;
				max-width: 100% !important;
				object-fit: contain !important;
			}
			.optionino-options-save-box {
				display: flex !important;
				justify-content: flex-end !important;
				align-items: flex-end !important;
				margin-top: auto !important;
				padding: 15px 0 0 0 !important;
				width: 100% !important;
				box-sizing: border-box !important;
			}
			.optionino-options-save-box .submit-optionino {
				align-self: flex-end !important;
				height: 40px !important;
				min-height: 40px !important;
				max-height: 40px !important;
				margin: 0 !important;
				display: inline-flex !important;
				align-items: center !important;
				justify-content: center !important;
				line-height: 1 !important;
			}
			#singo-log-console,
			#singo-test-output-box,
			#singo-db-inspect-output {
				max-width: 100% !important;
				overflow-x: auto !important;
				overflow-y: auto !important;
				white-space: pre-wrap !important;
				word-break: break-all !important;
				word-wrap: break-word !important;
				box-sizing: border-box !important;
			}
		";
		wp_add_inline_style( 'singo-tools-admin-style', $page_css );
	}

	/**
	 * Configure the settings page.
	 */
	private static function set_config() {
		OPTNNO::set_config( 'singo_tools_options', array(
			'dev_title'       => 'صفحه تنظیمات جعبه ابزار',
			'menu_title'      => 'جعبه ابزار سینگو',
			'page_title'      => 'صفحه تنظیمات جعبه ابزار',
			'menu_type'       => 'menu',
			'page_slug'       => 'singo-tools',
			'icon_url'        => SINGO_TOOLS_URL . 'assets/SingoLogo.svg',
			'page_capability' => singo_tools_get_admin_capability(),
			'logo_url'        => SINGO_TOOLS_URL . 'assets/SingoLogo.svg',
			'dev_version'     => SINGO_TOOLS_VERSION,
		) );
	}

	/**
	 * Set up settings tabs and fields.
	 */
	private static function set_tabs() {
		// Shamsi Date Settings Tab
		$shamsi_fields = array(
			array(
				'id'      => 'master_switch',
				'type'    => 'switcher',
				'title'   => 'فعال‌سازی تبدیل تاریخ شمسی',
				'desc'    => 'با خاموش کردن این گزینه، تمام قابلیت‌های تبدیل تاریخ غیرفعال شده و تاریخ‌ها به میلادی برمی‌گردند.',
				'default' => true,
			),
			array(
				'id'      => 'enable_frontend',
				'type'    => 'switcher',
				'title'   => 'تبدیل تاریخ در بخش کاربری (فرانت‌اند)',
				'default' => true,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			),
			array(
				'id'      => 'enable_admin',
				'type'    => 'switcher',
				'title'   => 'تبدیل تاریخ در پیشخوان (ادمین)',
				'default' => true,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			),
			array(
				'id'      => 'convert_digits',
				'type'    => 'switcher',
				'title'   => 'تبدیل اعداد انگلیسی به فارسی',
				'default' => true,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			),
			array(
				'id'      => 'default_format',
				'type'    => 'select',
				'title'   => 'فرمت پیش‌فرض تاریخ',
				'options' => array(
					'Y/m/d'      => '1402/05/24 (Y/m/d)',
					'j F Y'      => '24 مرداد 1402 (j F Y)',
					'l, j F Y'   => 'سه‌شنبه, 24 مرداد 1402 (l, j F Y)',
					'Y/m/d H:i'  => '1402/05/24 15:30 (Y/m/d H:i)',
				),
				'default' => 'Y/m/d',
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			),
			array(
				'id'      => 'enable_rest_ajax',
				'type'    => 'switcher',
				'title'   => 'تبدیل تاریخ در پاسخ‌های REST API / AJAX',
				'default' => false,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$shamsi_fields[] = array(
				'id'      => 'enable_woocommerce',
				'type'    => 'switcher',
				'title'   => 'فعال‌سازی تبدیل تاریخ در ووکامرس',
				'default' => true,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			);
			$shamsi_fields[] = array(
				'id'      => 'wc_order_emails',
				'type'    => 'switcher',
				'title'   => 'تبدیل تاریخ در ایمیل‌های سفارش ووکامرس',
				'default' => true,
				'require' => array(
					array( 'master_switch', '==', true ),
					array( 'enable_woocommerce', '==', true ),
				),
			);
			$shamsi_fields[] = array(
				'id'      => 'wc_admin_orders',
				'type'    => 'switcher',
				'title'   => 'تبدیل تاریخ در جدول سفارشات (پیشخوان ووکامرس)',
				'default' => true,
				'require' => array(
					array( 'master_switch', '==', true ),
					array( 'enable_woocommerce', '==', true ),
				),
			);
		}

		if ( did_action( 'elementor/loaded' ) ) {
			$shamsi_fields[] = array(
				'id'      => 'enable_elementor',
				'type'    => 'switcher',
				'title'   => 'فعال‌سازی تبدیل تاریخ در المنتور',
				'default' => true,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			);
		}

		OPTNNO::set_tab( 'singo_tools_options', array(
			'id'     => 'shamsi_settings',
			'title'  => 'تنظیمات تاریخ شمسی',
			'desc'   => 'تبدیل هوشمند تاریخ‌های میلادی به شمسی در پیشخوان، فرانت‌اند و ایمیل‌ها.',
			'icon'   => 'dashicons-calendar-alt',
			'fields' => $shamsi_fields,
		) );

		// Currency Converter Tab
		$currency_fields = array(
			array(
				'id'      => 'enable_currency_converter',
				'type'    => 'switcher',
				'title'   => 'فعال‌سازی دستیار ارزی ووکامرس',
				'desc'    => 'با خاموش کردن این گزینه، تمام قیمت‌ها به تومان برگردانده شده و دستیار ارزی غیرفعال می‌شود.',
				'default' => false,
			),
			array(
				'id'      => 'rate_usd',
				'type'    => 'number',
				'title'   => 'نرخ دلار (USD) به تومان',
				'default' => '1',
				'require' => array(
					array( 'enable_currency_converter', '==', true ),
				),
			),
			array(
				'id'      => 'rate_eur',
				'type'    => 'number',
				'title'   => 'نرخ یورو (EUR) به تومان',
				'default' => '1',
				'require' => array(
					array( 'enable_currency_converter', '==', true ),
				),
			),
			array(
				'id'      => 'rate_cny',
				'type'    => 'number',
				'title'   => 'نرخ یوان چین (CNY) به تومان',
				'default' => '1',
				'require' => array(
					array( 'enable_currency_converter', '==', true ),
				),
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			OPTNNO::set_tab( 'singo_tools_options', array(
				'id'     => 'currency_settings',
				'title'  => 'دستیار ارزی',
				'desc'   => 'مدیریت و تبدیل نرخ ارزهای خارجی (دلار، یورو و یوان) به تومان در ووکامرس.',
				'icon'   => 'dashicons-money-alt',
				'fields' => $currency_fields,
			) );
		}



		// Product Features Custom Interactive Tab
		if ( file_exists( SINGO_TOOLS_PATH . 'modules/product-features/views/admin-groups-view.php' ) ) {
			OPTNNO::set_tab( 'singo_tools_options', array(
				'id'    => 'product_features',
				'title' => 'گروه‌بندی ویژگی‌ها',
				'desc'  => 'دسته‌بندی، اولویت‌بندی و نمایش اختصاصی ویژگی‌های محصولات با شورت‌کد.',
				'icon'  => 'dashicons-list-view',
				'file'  => SINGO_TOOLS_PATH . 'modules/product-features/views/admin-groups-view.php',
			) );
		}

		// Other Tools Tab (سایر امکانات)
		$other_tools_fields = array(
			array(
				'id'      => 'enable_product_priority',
				'type'    => 'switcher',
				'title'   => 'اولویت محصولات برجسته',
				'desc'    => 'امکان امتیازدهی ۱ تا ۵ ستاره به محصولات در جدول مدیریت و صفحه ویرایش محصول، و نمایش اولویت‌دار محصولات در آرشیوها و فروشگاه بر اساس امتیاز.',
				'default' => false,
			),
			array(
				'id'      => 'disable_right_click',
				'type'    => 'switcher',
				'title'   => 'غیرفعال‌سازی کلیک راست و انتخاب متن',
				'desc'    => 'جلوگیری از کلیک راست (Context Menu) و انتخاب متون در فرانت‌اند سایت جهت محافظت از محتوا.',
				'default' => false,
			),
			array(
				'id'      => 'custom_variable_price_format',
				'type'    => 'switcher',
				'title'   => 'قیمت محصولات متغیر',
				'desc'    => 'تغییر فرمت نمایش قیمت محصولات متغیر از بازه قیمتی به ساختار «از [کمترین قیمت]».',
				'default' => false,
			),
			array(
				'id'      => 'enable_product_swatches',
				'type'    => 'switcher',
				'title'   => 'سواچ محصولات رنگی',
				'desc'    => 'فعال‌سازی سیستم انتخاب سواچ رنگی و دکمه‌ای برای ویژگی‌های محصولات متغیر در ووکامرس.',
				'default' => false,
			),
			array(
				'id'      => 'enable_product_thumbnail_features',
				'type'    => 'switcher',
				'title'   => 'ویژگی بند انگشتی محصولات',
				'desc'    => 'فعال‌سازی شورت‌کد [product_features_boxes limit="4"] برای نمایش باکس‌های ویژگی بندانگشتی همراه با تصویر در صفحه محصول.',
				'default' => false,
			),
		);

		OPTNNO::set_tab( 'singo_tools_options', array(
			'id'     => 'other_tools',
			'title'  => 'سایر امکانات',
			'desc'   => 'فعال‌سازی و مدیریت ابزارها و قابلیت‌های تکمیلی جعبه ابزار سینگو.',
			'icon'   => 'dashicons-admin-generic',
			'fields' => $other_tools_fields,
		) );

		// Price List PDF Export Custom Interactive Tab
		if ( class_exists( 'WooCommerce' ) && file_exists( SINGO_TOOLS_PATH . 'modules/price-list/views/admin-price-list-view.php' ) ) {
			OPTNNO::set_tab( 'singo_tools_options', array(
				'id'    => 'price_list_pdf',
				'title' => 'خروجی لیست قیمت (PDF)',
				'desc'  => 'تولید و دانلود مستقیم لیست قیمت محصولات ووکامرس در قالب استاندارد PDF بر اساس دسته‌بندی با تاریخ روز شمسی و لوگو.',
				'icon'  => 'dashicons-media-document',
				'file'  => SINGO_TOOLS_PATH . 'modules/price-list/views/admin-price-list-view.php',
			) );
		}
	}
}

if ( ! class_exists( 'SDM_Optionino' ) ) {
	class_alias( 'Singo_Tools_Optionino', 'SDM_Optionino' );
}
