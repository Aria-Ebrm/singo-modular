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
