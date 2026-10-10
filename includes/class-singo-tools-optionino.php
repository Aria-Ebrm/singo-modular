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
		// 1. Global Admin Menu Brand Styling for Singo Tools
		$primary_color = '#ad6141';
		$primary_hover = '#984f32';
		$text_hover    = '#ea8a64';

		$global_css = "
			/* Logo Icon: Subtle in normal state, full brightness on active/hover */
			#adminmenu #toplevel_page_singo-tools .wp-menu-image img {
				opacity: 0.8;
				filter: brightness(0) invert(1);
				transition: opacity 0.15s ease-in-out;
			}
			#adminmenu li#toplevel_page_singo-tools:hover .wp-menu-image img,
			#adminmenu li#toplevel_page_singo-tools.opensub .wp-menu-image img,
			#adminmenu li#toplevel_page_singo-tools.wp-has-current-submenu .wp-menu-image img,
			#adminmenu li#toplevel_page_singo-tools.current .wp-menu-image img {
				opacity: 1 !important;
			}

			/* Top-level Menu Item: ONLY on Hover, Focus, Open Submenu, or Current/Active Page */
			#adminmenu li#toplevel_page_singo-tools:hover > a,
			#adminmenu li#toplevel_page_singo-tools:focus > a,
			#adminmenu li#toplevel_page_singo-tools:focus-within > a,
			#adminmenu li#toplevel_page_singo-tools.opensub > a,
			#adminmenu li#toplevel_page_singo-tools.selected > a,
			#adminmenu li#toplevel_page_singo-tools.wp-has-current-submenu > a,
			#adminmenu li#toplevel_page_singo-tools.current > a,
			#adminmenu li#toplevel_page_singo-tools.wp-menu-open > a,
			#adminmenu li#toplevel_page_singo-tools a.menu-top:hover,
			#adminmenu li#toplevel_page_singo-tools a.menu-top:focus {
				background-color: {$primary_color} !important;
				color: #ffffff !important;
			}

			#adminmenu li#toplevel_page_singo-tools:hover > a,
			#adminmenu li#toplevel_page_singo-tools.opensub > a:hover {
				background-color: {$primary_hover} !important;
				color: #ffffff !important;
			}

			#adminmenu li#toplevel_page_singo-tools:hover .wp-menu-name,
			#adminmenu li#toplevel_page_singo-tools:focus .wp-menu-name,
			#adminmenu li#toplevel_page_singo-tools.opensub .wp-menu-name,
			#adminmenu li#toplevel_page_singo-tools.current .wp-menu-name,
			#adminmenu li#toplevel_page_singo-tools.wp-has-current-submenu .wp-menu-name {
				color: #ffffff !important;
			}

			/* Submenu Items: Text Color on Hover, Focus, and Current Page */
			#adminmenu li#toplevel_page_singo-tools .wp-submenu a:hover,
			#adminmenu li#toplevel_page_singo-tools .wp-submenu a:focus,
			#adminmenu li#toplevel_page_singo-tools .wp-submenu li.current a,
			#adminmenu li#toplevel_page_singo-tools .wp-submenu li.current a:hover,
			#adminmenu li#toplevel_page_singo-tools .wp-submenu li.current a:focus {
				color: {$text_hover} !important;
			}

			/* Submenu Items: Native WordPress-style Inset Indicator Bar (box-shadow) */
			.rtl #adminmenu li#toplevel_page_singo-tools .wp-submenu a:hover,
			.rtl #adminmenu li#toplevel_page_singo-tools .wp-submenu a:focus,
			.rtl #adminmenu li#toplevel_page_singo-tools .wp-submenu li.current a {
				box-shadow: inset -4px 0 0 0 {$primary_color} !important;
				transition: box-shadow 0.1s linear, color 0.1s linear;
			}

			body:not(.rtl) #adminmenu li#toplevel_page_singo-tools .wp-submenu a:hover,
			body:not(.rtl) #adminmenu li#toplevel_page_singo-tools .wp-submenu a:focus,
			body:not(.rtl) #adminmenu li#toplevel_page_singo-tools .wp-submenu li.current a {
				box-shadow: inset 4px 0 0 0 {$primary_color} !important;
				transition: box-shadow 0.1s linear, color 0.1s linear;
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

		wp_enqueue_script(
			'singo-delta-save',
			SINGO_TOOLS_URL . 'assets/js/singo-delta-save.js',
			array( 'jquery' ),
			SINGO_TOOLS_VERSION,
			true
		);

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
				align-items: center !important;
				gap: 15px !important;
				margin-top: auto !important;
				padding: 15px 0 0 0 !important;
				width: 100% !important;
				box-sizing: border-box !important;
			}
			.optionino-options-save-box .submit-optionino {
				align-self: center !important;
				height: 40px !important;
				min-height: 40px !important;
				max-height: 40px !important;
				padding: 0 24px !important;
				box-sizing: border-box !important;
				margin: 0 !important;
				display: inline-flex !important;
				align-items: center !important;
				justify-content: center !important;
				line-height: 1 !important;
			}
			.optionino-options-save-box .singo-delta-badge {
				display: inline-flex !important;
				align-items: center !important;
				align-self: center !important;
				height: 40px !important;
				line-height: 1 !important;
				margin: 0 !important;
				padding: 0 !important;
				font-size: 13px !important;
				box-sizing: border-box !important;
				vertical-align: middle !important;
			}
			.optionino-options-save-box .singo-delta-badge > span,
			.optionino-options-save-box .singo-delta-badge > strong {
				display: inline-flex !important;
				align-items: center !important;
				gap: 6px !important;
				line-height: 1 !important;
				margin: 0 !important;
				padding: 0 !important;
			}
			.optionino-options-save-box .error-text,
			.optionino-options-save-box .success-text {
				display: inline-flex !important;
				align-items: center !important;
				align-self: center !important;
				height: 40px !important;
				line-height: 1 !important;
				margin: 0 !important;
			}
			.optionino-options-save-box .warning-text,
			.singo-warning-notice {
				display: inline-flex !important;
				align-items: center !important;
				align-self: center !important;
				height: 40px !important;
				line-height: 1 !important;
				margin: 0 !important;
				padding: 0 16px !important;
				border-radius: 8px !important;
				background-color: #fef3c7 !important;
				border: 1px solid #f59e0b !important;
				color: #92400e !important;
				font-size: 13px !important;
				font-weight: 600 !important;
				box-sizing: border-box !important;
				gap: 6px !important;
			}
			/* Optionino Repeater Modern & Drag-and-Drop Enhancements */
			.optionino-repeater-field {
				margin-top: 10px !important;
			}
			.optionino-repeater-container {
				display: flex !important;
				flex-direction: column !important;
				gap: 12px !important;
				padding: 10px 0 !important;
			}
			.optionino-repeater-item {
				position: relative !important;
				border: 1px solid #e2e8f0 !important;
				background: #ffffff !important;
				border-radius: 12px !important;
				padding: 14px 18px !important;
				display: flex !important;
				align-items: center !important;
				gap: 15px !important;
				transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
			}
			.optionino-repeater-item:hover {
				border-color: #cbd5e1 !important;
			}
			.optionino-repeater-item.ui-sortable-helper {
				background: #ffffff !important;
				border-color: #ad6141 !important;
				box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15) !important;
				cursor: grabbing !important;
			}
			.optionino-repeater-item.ui-sortable-placeholder {
				background: #f8fafc !important;
				border: 2px dashed #cbd5e1 !important;
				visibility: visible !important;
				border-radius: 12px !important;
				min-height: 56px !important;
			}
			.optionino-repeater-item.is-default-row {
				border-color: #ad6141 !important;
				background: #fffbf9 !important;
			}
			.singo-drag-handle {
				cursor: grab !important;
				color: #94a3b8 !important;
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
				padding: 4px !important;
				user-select: none !important;
				transition: color 0.15s ease !important;
			}
			.singo-drag-handle:hover {
				color: #ad6141 !important;
			}
			.singo-priority-badge {
				background: #f1f5f9 !important;
				color: #475569 !important;
				font-size: 12px !important;
				font-weight: 700 !important;
				width: 28px !important;
				height: 28px !important;
				border-radius: 50% !important;
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
				flex-shrink: 0 !important;
				border: 1px solid #e2e8f0 !important;
			}
			.optionino-repeater-item.is-default-row .singo-priority-badge {
				background: #ad6141 !important;
				color: #ffffff !important;
				border-color: #ad6141 !important;
			}
			.optionino-repeater-seperate-subfields {
				flex: 1 !important;
				display: flex !important;
				align-items: center !important;
				gap: 15px !important;
				flex-wrap: wrap !important;
			}
			.optionino-repeater-seperate-subfields .optionino-box-option {
				margin: 0 !important;
				display: flex !important;
				align-items: center !important;
				gap: 10px !important;
			}
			.optionino-repeater-seperate-subfields .optionino-form-label {
				margin: 0 !important;
				white-space: nowrap !important;
				font-size: 13px !important;
			}
			.optionino-repeater-seperate-subfields .optionino-input {
				min-width: 220px !important;
			}
			.optionino-remove-repeater-item {
				border: 1px solid #fee2e2 !important;
				background: #fff5f5 !important;
				color: #ef4444 !important;
				border-radius: 8px !important;
				padding: 6px 12px !important;
				font-size: 12px !important;
				cursor: pointer !important;
				display: inline-flex !important;
				align-items: center !important;
				gap: 4px !important;
				transition: all 0.2s ease !important;
			}
			.optionino-remove-repeater-item:hover {
				background: #ef4444 !important;
				color: #ffffff !important;
				border-color: #ef4444 !important;
			}
			.optionino-add-repeater-item {
				background: #ffffff !important;
				border: 1px dashed #ad6141 !important;
				color: #ad6141 !important;
				border-radius: 10px !important;
				padding: 8px 18px !important;
				font-size: 13px !important;
				font-weight: 600 !important;
				cursor: pointer !important;
				display: inline-flex !important;
				align-items: center !important;
				gap: 6px !important;
				margin-top: 10px !important;
				transition: all 0.2s ease !important;
			}
			.optionino-add-repeater-item:hover {
				background: #ad6141 !important;
				color: #ffffff !important;
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
	 * Set up settings tabs and fields dynamically from self-contained modules.
	 */
	private static function set_tabs(): void {
		$modules = \SingoTools\Core\ModuleManager::get_instance()->get_all_modules();

		$tabs               = array();
		$other_tools_fields = array();

		// Collect dedicated tabs and other_tools fields from discovered modules
		foreach ( $modules as $module ) {
			if ( $module::is_other_tool() ) {
				$fields = $module->get_optionino_fields();
				if ( ! empty( $fields ) && is_array( $fields ) ) {
					foreach ( $fields as $field ) {
						$other_tools_fields[] = $field;
					}
				}
			} else {
				$tab = $module->get_optionino_tab();
				if ( ! empty( $tab ) && is_array( $tab ) ) {
					$order  = $module::get_order();
					$tabs[] = array(
						'order' => $order,
						'tab'   => $tab,
					);
				}
			}
		}

		// Sort dedicated tabs strictly by their designated order
		usort( $tabs, function ( array $a, array $b ): int {
			return $a['order'] <=> $b['order'];
		} );

		// Rule 1 & 2: Other Tools must be strictly LAST, and must be completely omitted if empty
		if ( ! empty( $other_tools_fields ) ) {
			$tabs[] = array(
				'order' => PHP_INT_MAX,
				'tab'   => array(
					'id'     => 'other_tools',
					'title'  => 'سایر امکانات',
					'desc'   => 'فعال‌سازی و مدیریت ابزارها و قابلیت‌های تکمیلی جعبه ابزار سینگو.',
					'icon'   => 'dashicons-admin-generic',
					'fields' => $other_tools_fields,
				),
			);
		}

		// Register tabs in Optionino
		foreach ( $tabs as $item ) {
			OPTNNO::set_tab( 'singo_tools_options', $item['tab'] );
		}
	}
}

if ( ! class_exists( 'SDM_Optionino' ) ) {
	class_alias( 'Singo_Tools_Optionino', 'SDM_Optionino' );
}
