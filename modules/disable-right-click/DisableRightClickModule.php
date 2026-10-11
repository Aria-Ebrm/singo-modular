<?php
declare(strict_types=1);

namespace SingoTools\Modules\DisableRightClick;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Disable Right Click & Text Selection Module
 *
 * @package SingoTools\Modules\DisableRightClick
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class DisableRightClickModule extends AbstractModule {

	public static function get_id(): string {
		return 'disable-right-click';
	}

	public static function get_name(): string {
		return 'غیرفعال‌سازی کلیک راست و انتخاب متن';
	}

	public static function get_description(): string {
		return 'جلوگیری از کلیک راست (Context Menu) و انتخاب متون در فرانت‌اند سایت جهت محافظت از محتوا.';
	}

	public static function get_icon(): string {
		return 'dashicons-hidden';
	}

	public static function get_placement(): string {
		return 'other_tools';
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'disable_right_click', false );
	}

	public static function is_context_valid(): bool {
		return ! is_admin();
	}

	public function get_optionino_fields(): array {
		return array(
			array(
				'id'      => 'disable_right_click',
				'type'    => 'switcher',
				'title'   => self::get_name(),
				'desc'    => self::get_description(),
				'default' => false,
			),
			array(
				'id'          => 'disable_right_click_guide',
				'type'        => 'guide',
				'title'       => 'راهنمای محافظت از محتوا و کلیک راست',
				'require'     => array( array( 'disable_right_click', '==', true ) ),
				'guide_title' => 'سازوکار محافظت از محتوا و تصاویر سایت',
				'guide_icon'  => 'dashicons-hidden',
				'guide_items' => array(
					'مسدودسازی کلیک راست و انتخاب متن: منوی کلیک راست (Context Menu)، درگ کردن تصاویر و انتخاب متن با ماوس در سراسر فرانت‌اند سایت برای بازدیدکنندگان غیرفعال می‌شود.',
					'جلوگیری از کلیدهای میانبر کپی: کلیدهای پرکاربرد مانند Ctrl+C (کپی)، Ctrl+U (مشاهده سورس کد صفحه)، و کلیدهای باز کردن ابزار توسعه مرورگر مسدود می‌گردند.',
					'معافیت خودکار مدیران سایت: به منظور سهولت در ویرایش و مدیریت فروشگاه، این محدودیت‌ها برای مدیران لاگین‌شده وردپرس اعمال نمی‌شود.',
				),
			),
		);
	}

	public function init(): void {
		parent::init();

		add_action( 'wp_footer', array( $this, 'render_script_and_styles' ), 99 );
	}

	/**
	 * Output inline style and script to disable text selection and context menu for visitors,
	 * while completely exempting administrators and store managers.
	 */
	public function render_script_and_styles(): void {
		// Completely exempt administrators and store managers
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
			return;
		}

		// Exempt customizer preview and page builders
		if ( is_customize_preview() ) {
			return;
		}

		if ( class_exists( '\Elementor\Plugin' ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
				return;
			}
		}
		?>
		<style id="singo-disable-right-click-styles">
			body, html {
				-webkit-user-select: none !important;
				-moz-user-select: none !important;
				-ms-user-select: none !important;
				user-select: none !important;
			}
			input, textarea, select, [contenteditable="true"] {
				-webkit-user-select: auto !important;
				-moz-user-select: auto !important;
				-ms-user-select: auto !important;
				user-select: auto !important;
			}
		</style>
		<script id="singo-disable-right-click-script">
			(function() {
				// Prevent right click (context menu)
				document.addEventListener('contextmenu', function(e) {
					e.preventDefault();
					return false;
				}, false);

				// Prevent image drag & drop
				document.addEventListener('dragstart', function(e) {
					if (e.target && e.target.nodeName === 'IMG') {
						e.preventDefault();
						return false;
					}
				}, false);

				// Prevent copy hotkeys (Ctrl+C, Ctrl+U, Ctrl+S, F12)
				document.addEventListener('keydown', function(e) {
					var key = e.key || '';
					var keyCode = e.keyCode || e.which;

					// F12 DevTools
					if (keyCode === 123 || key === 'F12') {
						e.preventDefault();
						return false;
					}

					// Ctrl / Cmd combinations
					if (e.ctrlKey || e.metaKey) {
						var lowerKey = key.toLowerCase();
						// Ctrl+C, Ctrl+U, Ctrl+S, Ctrl+Shift+I, Ctrl+Shift+J
						if (lowerKey === 'c' || lowerKey === 'u' || lowerKey === 's' || (e.shiftKey && (lowerKey === 'i' || lowerKey === 'j'))) {
							var activeEl = document.activeElement;
							if (lowerKey === 'c' && activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
								return true;
							}
							e.preventDefault();
							return false;
						}
					}
				}, false);
			})();
		</script>
		<?php
	}
}
