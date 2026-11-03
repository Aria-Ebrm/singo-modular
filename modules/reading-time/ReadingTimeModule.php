<?php
declare(strict_types=1);

namespace SingoTools\Modules\ReadingTime;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Reading Time Module
 *
 * Self-contained module calculating and displaying estimated reading time
 * for WordPress posts and custom post types, fully integrated into the
 * "Other Tools" (سایر امکانات) tab in Optionino.
 *
 * @package SingoTools\Modules\ReadingTime
 * @since 4.2.2
 */

defined( 'ABSPATH' ) || exit;

class ReadingTimeModule extends AbstractModule {

	public static function get_id(): string {
		return 'reading-time';
	}

	public static function get_name(): string {
		return 'زمان تخمینی مطالعه';
	}

	public static function get_description(): string {
		return 'محاسبه خودکار و نمایش زمان تخمینی مطالعه نوشته‌ها با پشتیبانی از کلمات فارسی، شورت‌کد و کشینگ هوشمند.';
	}

	public static function get_icon(): string {
		return 'dashicons-clock';
	}

	public static function get_placement(): string {
		return 'other_tools';
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'enable_reading_time', false );
	}

	public static function is_context_valid(): bool {
		return true;
	}

	public function get_optionino_fields(): array {
		return array(
			array(
				'id'      => 'enable_reading_time',
				'type'    => 'switcher',
				'title'   => self::get_name(),
				'desc'    => self::get_description(),
				'default' => false,
			),
			array(
				'id'      => 'reading_time_wpm',
				'type'    => 'number',
				'title'   => 'سرعت مطالعه (کلمه در دقیقه)',
				'desc'    => 'میانگین تعداد کلماتی که یک کاربر در هر دقیقه مطالعه می‌کند (استاندارد وب فارسی: ۲۰۰ کلمه).',
				'default' => '200',
				'require' => array( array( 'enable_reading_time', '==', true ) ),
			),
			array(
				'id'      => 'reading_time_postfix',
				'type'    => 'text',
				'title'   => 'پسوند نمایشی زمان مطالعه',
				'desc'    => 'متنی که پس از عدد زمان مطالعه چاپ می‌شود (مثلاً: «دقیقه» یا «دقیقه مطالعه»). در صورت خالی گذاشتن، صرفاً عدد نمایش می‌یابد.',
				'default' => 'دقیقه',
				'require' => array( array( 'enable_reading_time', '==', true ) ),
			),
			array(
				'id'      => 'reading_time_persian_digits',
				'type'    => 'switcher',
				'title'   => 'نمایش ارقام با اعداد فارسی',
				'desc'    => 'تبدیل خودکار اعداد انگلیسی (0-9) به فارسی (۰-۹) در خروجی زمان مطالعه.',
				'default' => true,
				'require' => array( array( 'enable_reading_time', '==', true ) ),
			),
			array(
				'id'          => 'reading_time_guide',
				'type'        => 'guide',
				'title'       => 'راهنمای استفاده از زمان تخمینی مطالعه',
				'require'     => array( array( 'enable_reading_time', '==', true ) ),
				'guide_title' => 'نحوه استفاده از زمان تخمینی مطالعه در سایت',
				'guide_icon'  => 'dashicons-clock',
				'guide_items' => array(
					'استفاده از شورت‌کد ساده: در هر کجای قالب، المنتور یا متن نوشته، شورت‌کد [reading_time] را قرار دهید تا زمان مطالعه مطابق تنظیمات نمایش داده شود.',
					'دریافت صرفاً عدد خام: با درج شورت‌کد بصورت [reading_time postfix=""] می‌توانید تنها عدد زمان را بدون کلمه دقیقه دریافت کنید.',
					'محاسبه برای یک نوشته مشخص: با قرار دادن شناسه نوشته به شکل [reading_time id="123"] زمان مطالعه آن نوشته خاص محاسبه می‌شود.',
					'استفاده در کد قالب (PHP): با فراخوانی تابع singo_get_reading_time() در فایل‌های قالب یا چایلد تم می‌توانید زمان مطالعه را چاپ نمایید.',
					'کشینگ و کارایی فوق‌العاده: تعداد کلمات هر نوشته در دیتابیس ذخیره شده و فقط هنگام ویرایش و ذخیره مجدد نوشته بازنویسی می‌شود؛ بنابراین هیچ بار اضافی روی سرور ایجاد نخواهد شد.',
				),
			),
		);
	}

	public function init(): void {
		parent::init();

		$class_file = $this->get_path( 'includes/class-singo-reading-time.php' );
		if ( file_exists( $class_file ) ) {
			require_once $class_file;
			Singo_Reading_Time::get_instance();
		}
	}
}
