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
		$is_singo_page = false;
		if ( isset( $_GET['page'] ) && ( str_starts_with( (string) $_GET['page'], 'singo' ) || str_contains( (string) $_GET['page'], 'singo' ) ) ) {
			$is_singo_page = true;
		} elseif ( $screen && ( str_contains( (string) $screen->id, 'singo' ) || str_contains( (string) $screen->id, 'optionino' ) || str_contains( (string) $screen->id, 'سینگو' ) ) ) {
			$is_singo_page = true;
		}

		if ( ! $is_singo_page ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		$delta_path = SINGO_TOOLS_PATH . 'assets/js/singo-delta-save.js';
		$delta_ver  = file_exists( $delta_path ) ? (string) filemtime( $delta_path ) : SINGO_TOOLS_VERSION;
		wp_enqueue_script(
			'singo-delta-save',
			SINGO_TOOLS_URL . 'assets/js/singo-delta-save.js',
			array( 'jquery' ),
			$delta_ver,
			true
		);

		// Global Singo Admin UI Toolkit (Optionino Harmonized Styles & Interactive Helpers)
		wp_enqueue_style(
			'singo-admin-ui-toolkit',
			SINGO_TOOLS_URL . 'assets/css/singo-admin-ui.css',
			array(),
			SINGO_TOOLS_VERSION
		);
		wp_enqueue_script(
			'singo-admin-ui-toolkit',
			SINGO_TOOLS_URL . 'assets/js/singo-admin-ui.js',
			array( 'jquery' ),
			SINGO_TOOLS_VERSION,
			true
		);

		// Localize educational guide configurations for dynamic Optionino conditional rendering
		$guide_configs = array();
		$modules       = \SingoTools\Core\ModuleManager::get_instance()->get_all_modules();
		foreach ( $modules as $module ) {
			$fields = $module::is_other_tool() ? $module->get_optionino_fields() : ( $module->get_optionino_tab()['fields'] ?? array() );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $f ) {
					if ( isset( $f['type'] ) && $f['type'] === 'guide' && ! empty( $f['id'] ) ) {
						$guide_configs[ $f['id'] ] = array(
							'title' => $f['guide_title'] ?? ( $f['title'] ?? '' ),
							'icon'  => $f['guide_icon'] ?? 'dashicons-info',
							'items' => $f['guide_items'] ?? array(),
						);
					}
				}
			}
		}
		wp_localize_script( 'singo-delta-save', 'singoGuidesData', $guide_configs );

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

			/* Shortcode Field Wrapper & Native Copy Button (Matching Image 2) */
			.singo-shortcode-field-wrapper {
				display: flex !important;
				align-items: center !important;
				gap: 12px !important;
				width: 100% !important;
				max-width: 580px !important;
				box-sizing: border-box !important;
			}
			.singo-shortcode-field-wrapper .optionino-input {
				flex: 1 !important;
				height: 42px !important;
				border: 1px solid #cbd5e1 !important;
				border-radius: 9px !important;
				padding: 0 14px !important;
				font-size: 14px !important;
				font-weight: 600 !important;
				font-family: monospace !important;
				color: #1e293b !important;
				box-sizing: border-box !important;
				background: #f8fafc !important;
				direction: ltr !important;
				text-align: center !important;
				cursor: pointer !important;
				transition: all 0.15s ease !important;
			}
			.singo-shortcode-field-wrapper .optionino-input:focus {
				border-color: #ad6141 !important;
				background: #ffffff !important;
				box-shadow: 0 0 0 2px rgba(173, 97, 65, 0.15) !important;
				outline: none !important;
			}
			.singo-copy-shortcode-btn {
				display: inline-flex !important;
				align-items: center !important;
				justify-content: center !important;
				gap: 8px !important;
				height: 42px !important;
				padding: 0 18px !important;
				background: var(--optionino-main-color, #ad6141) !important;
				color: #ffffff !important;
				border: 1.5px solid transparent !important;
				border-radius: 9px !important;
				font-family: inherit !important;
				font-size: 13px !important;
				font-weight: 600 !important;
				cursor: pointer !important;
				white-space: nowrap !important;
				flex-shrink: 0 !important;
				box-sizing: border-box !important;
				transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
				box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
			}
			.singo-copy-shortcode-btn:hover {
				background: #8c4b30 !important;
				transform: translateY(-1px) !important;
				box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1) !important;
			}
			.singo-copy-shortcode-btn:active {
				transform: translateY(0) !important;
			}
			.singo-copy-shortcode-btn .dashicons {
				font-size: 17px !important;
				width: 17px !important;
				height: 17px !important;
				line-height: 17px !important;
				vertical-align: middle !important;
				color: inherit !important;
			}
			.singo-copy-shortcode-btn.is-copied {
				background: #16a34a !important;
				border-color: #15803d !important;
				color: #ffffff !important;
			}

			/* Educational Guide Cards in Optionino */
			.optionino-box-option:has(.singo-module-guide-card),
			.optionino-box-option[id*=\"_guide-\"] {
				flex-direction: column !important;
				align-items: stretch !important;
				border-top: none !important;
				padding-top: 0px !important;
				margin-top: -8px !important;
				margin-bottom: 24px !important;
			}
			.singo-module-guide-card {
				width: 100% !important;
				background: #faf8f6 !important;
				border: 1px solid #ebd9d0 !important;
				border-right: 4px solid var(--optionino-main-color, #ad6141) !important;
				border-radius: 10px !important;
				padding: 16px 20px !important;
				box-sizing: border-box !important;
				font-family: inherit !important;
				box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03) !important;
			}
			.singo-module-guide-header {
				display: flex !important;
				align-items: center !important;
				gap: 8px !important;
				font-size: 14px !important;
				font-weight: 700 !important;
				color: #7c351b !important;
				margin-bottom: 12px !important;
			}
			.singo-module-guide-header .dashicons {
				font-size: 19px !important;
				width: 19px !important;
				height: 19px !important;
				line-height: 19px !important;
				color: var(--optionino-main-color, #ad6141) !important;
			}
			.singo-module-guide-list {
				list-style: none !important;
				margin: 0 !important;
				padding: 0 !important;
				display: flex !important;
				flex-direction: column !important;
				gap: 9px !important;
			}
			.singo-module-guide-item {
				display: flex !important;
				align-items: flex-start !important;
				gap: 10px !important;
				font-size: 13.5px !important;
				line-height: 1.75 !important;
				color: #374151 !important;
			}
			.singo-module-guide-step-badge {
				display: inline-flex !important;
				align-items: center !important;
				justify-content: center !important;
				min-width: 22px !important;
				height: 22px !important;
				background: rgba(173, 97, 65, 0.12) !important;
				color: var(--optionino-main-color, #ad6141) !important;
				border-radius: 50% !important;
				font-size: 11.5px !important;
				font-weight: 700 !important;
				line-height: 1 !important;
				margin-top: 2px !important;
				flex-shrink: 0 !important;
			}
			.singo-module-guide-step-text {
				flex: 1 !important;
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
