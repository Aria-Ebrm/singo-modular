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
		);
	}

	public function init(): void {
		parent::init();

		add_action( 'wp_footer', array( $this, 'render_script_and_styles' ), 99 );
	}

	/**
	 * Output inline style and script to disable text selection and context menu.
	 */
	public function render_script_and_styles(): void {
		?>
		<style>
			body, html {
				-webkit-user-select: none !important;
				-ms-user-select: none !important;
				user-select: none !important;
			}
		</style>
		<script>
			document.addEventListener('contextmenu', function(e) {
				e.preventDefault();
			});
		</script>
		<?php
	}
}
