<?php
declare(strict_types=1);

namespace SingoTools\Modules\JetPersianCalendar;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Jet Persian Calendar Module (Self-Contained)
 *
 * Dedicated Shamsi calendar integration for Crocoblock JetPlugins
 * (JetEngine, JetBooking, JetAppointments, JetFormBuilder, JetSmartFilters)
 * and the Gutenberg block editor, with ZERO conflict against WordPress core
 * or the general shamsi-date module.
 *
 * @package SingoTools\Modules\JetPersianCalendar
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class JetPersianCalendarModule extends AbstractModule {

	public static function get_id(): string {
		return 'jet-persian-calendar';
	}

	public static function get_name(): string {
		return 'تقویم فارسی افزونه‌های جت';
	}

	public static function get_icon(): string {
		return 'dashicons-calendar-alt';
	}

	public static function get_order(): int {
		return 12;
	}

	public static function get_placement(): string {
		return 'dedicated';
	}

	public static function get_description(): string {
		return 'تقویم و تاریخ خورشیدی یکپارچه با افزونه‌های سری جت';
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'jet_calendar_master', false );
	}

	public static function is_context_valid(): bool {
		return true;
	}

	public function get_optionino_tab(): ?array {
		$fields = array(
			array(
				'id'      => 'jet_calendar_master',
				'type'    => 'switcher',
				'title'   => 'فعال‌سازی تقویم فارسی افزونه‌های جت',
				'desc'    => 'با خاموش کردن این گزینه، تقویم شمسی در تمامی افزونه‌های جت غیرفعال شده و به میلادی برمی‌گردد.',
				'default' => false,
			),
			array(
				'id'      => 'jet_calendar_digits',
				'type'    => 'switcher',
				'title'   => 'تبدیل اعداد تاریخ به فارسی',
				'desc'    => 'نمایش تاریخ‌ها با اعداد فارسی (۰ تا ۹) در تمامی فیلدها و لیستینگ‌های جت.',
				'default' => false,
				'require' => array(
					array( 'jet_calendar_master', '==', true ),
				),
			),
			array(
				'id'      => 'jet_calendar_jet_engine',
				'type'    => 'switcher',
				'title'   => 'یکپارچه‌سازی با JetEngine',
				'desc'    => 'تقویم شمسی در متاباکس‌ها، CPT/CCT، مقادیر فیلدهای تاریخ در شبکه لیستینگ (Listing Grid) و فیلدهای پویا.',
				'default' => false,
				'require' => array(
					array( 'jet_calendar_master', '==', true ),
				),
			),
			array(
				'id'      => 'jet_calendar_jet_form_builder',
				'type'    => 'switcher',
				'title'   => 'یکپارچه‌سازی با JetFormBuilder',
				'desc'    => 'تقویم پاپ‌آپ شمسی در فیلدهای تاریخ و تاریخ/زمان فرم‌های ایجاد شده با فرم‌ساز کروکوبلاک.',
				'default' => false,
				'require' => array(
					array( 'jet_calendar_master', '==', true ),
				),
			),
			array(
				'id'      => 'jet_calendar_jet_booking',
				'type'    => 'switcher',
				'title'   => 'یکپارچه‌سازی با JetBooking (رزرو اقامتگاه)',
				'desc'    => 'تقویم بازه تاریخ شمسی در فرم‌های رزرو سمت کاربر و بخش مدیریت تقویم و تایم‌لاین رزروها.',
				'default' => false,
				'require' => array(
					array( 'jet_calendar_master', '==', true ),
				),
			),
			array(
				'id'      => 'jet_calendar_jet_appointments',
				'type'    => 'switcher',
				'title'   => 'یکپارچه‌سازی با JetAppointments (نوبت‌دهی)',
				'desc'    => 'تقویم شمسی برای انتخاب اسلات‌های زمانی و روزهای نوبت‌دهی در فرم‌ها و پیشخوان مدیریت.',
				'default' => false,
				'require' => array(
					array( 'jet_calendar_master', '==', true ),
				),
			),
			array(
				'id'      => 'jet_calendar_jet_smart_filters',
				'type'    => 'switcher',
				'title'   => 'یکپارچه‌سازی با JetSmartFilters (فیلترها)',
				'desc'    => 'پشتیبانی فیلترهای بازه تاریخی از تقویم شمسی و تبدیل خودکار به میلادی برای اجرای صحیح کوئری دیتابیس.',
				'default' => false,
				'require' => array(
					array( 'jet_calendar_master', '==', true ),
				),
			),
			array(
				'id'      => 'jet_calendar_gutenberg',
				'type'    => 'switcher',
				'title'   => 'تقویم شمسی در ویرایشگر گوتنبرگ',
				'desc'    => 'جایگزینی تقویم میلادی در پنجره بازشوی زمان‌بندی و انتشار نوشته‌ها در گوتنبرگ با تقویم شمسی.',
				'default' => false,
				'require' => array(
					array( 'jet_calendar_master', '==', true ),
				),
			),
		);

		return array(
			'id'     => 'jet_persian_calendar_settings',
			'title'  => self::get_name(),
			'desc'   => self::get_description(),
			'icon'   => self::get_icon(),
			'fields' => $fields,
		);
	}

	public function init(): void {
		parent::init();

		if ( ! is_rtl() ) {
			return;
		}

		$module_path = trailingslashit( $this->get_path() );
		$module_url  = trailingslashit( $this->get_url() );
		$version     = SINGO_TOOLS_VERSION;

		// Load Core Utilities
		require_once $module_path . 'includes/class-singo-jet-calendar-converter.php';
		require_once $module_path . 'includes/class-singo-jet-calendar-helpers.php';

		$convert_digits = (bool) singo_tools_get_option( 'jet_calendar_digits', false );

		// 1. JetEngine Integration
		if ( (bool) singo_tools_get_option( 'jet_calendar_jet_engine', false ) ) {
			require_once $module_path . 'includes/class-singo-jet-engine-integration.php';
			new JetEngineIntegration( $module_url, $version, $convert_digits );
		}

		// 2. JetFormBuilder Integration
		if ( (bool) singo_tools_get_option( 'jet_calendar_jet_form_builder', false ) ) {
			require_once $module_path . 'includes/class-singo-jet-form-builder-integration.php';
			new JetFormBuilderIntegration( $module_url, $version );
		}

		// 3. JetBooking Integration
		if ( (bool) singo_tools_get_option( 'jet_calendar_jet_booking', false ) ) {
			require_once $module_path . 'includes/class-singo-jet-booking-integration.php';
			new JetBookingIntegration( $module_url, $version );
		}

		// 4. JetAppointments Integration
		if ( (bool) singo_tools_get_option( 'jet_calendar_jet_appointments', false ) ) {
			require_once $module_path . 'includes/class-singo-jet-appointments-integration.php';
			new JetAppointmentsIntegration( $module_url, $version );
		}

		// 5. JetSmartFilters Integration
		if ( (bool) singo_tools_get_option( 'jet_calendar_jet_smart_filters', false ) ) {
			require_once $module_path . 'includes/class-singo-jet-smart-filters-integration.php';
			new JetSmartFiltersIntegration( $module_url, $version );
		}

		// 6. Gutenberg Calendar Integration
		if ( (bool) singo_tools_get_option( 'jet_calendar_gutenberg', false ) ) {
			require_once $module_path . 'includes/class-singo-gutenberg-calendar-integration.php';
			new GutenbergCalendarIntegration( $module_url, $version );
		}
	}
}
