<?php
declare(strict_types=1);

namespace SingoTools\Modules\ShamsiDate;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Shamsi Date Module (Self-Contained)
 *
 * @package SingoTools\Modules\ShamsiDate
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class ShamsiDateModule extends AbstractModule {

	public static function get_id(): string {
		return 'shamsi-date';
	}

	public static function get_name(): string {
		return 'تبدیل تاریخ شمسی';
	}

	public static function get_icon(): string {
		return 'dashicons-calendar-alt';
	}

	public static function get_order(): int {
		return 10;
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'master_switch', false );
	}

	public static function is_context_valid(): bool {
		return true;
	}

	public function get_optionino_tab(): ?array {
		$shamsi_fields = array(
			array(
				'id'      => 'master_switch',
				'type'    => 'switcher',
				'title'   => 'فعال‌سازی تبدیل تاریخ شمسی',
				'desc'    => 'با خاموش کردن این گزینه، تمام قابلیت‌های تبدیل تاریخ غیرفعال شده و تاریخ‌ها به میلادی برمی‌گردند.',
				'default' => false,
			),
			array(
				'id'      => 'enable_frontend',
				'type'    => 'switcher',
				'title'   => 'تبدیل تاریخ در بخش کاربری (فرانت‌اند)',
				'default' => false,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			),
			array(
				'id'      => 'enable_admin',
				'type'    => 'switcher',
				'title'   => 'تبدیل تاریخ در پیشخوان (ادمین)',
				'default' => false,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			),
			array(
				'id'      => 'convert_digits',
				'type'    => 'switcher',
				'title'   => 'تبدیل اعداد انگلیسی به فارسی',
				'default' => false,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			),
			array(
				'id'      => 'default_format',
				'type'    => 'select',
				'title'   => 'فرمت پیش‌فرض تاریخ',
				'options' => array(
					'Y/m/d'     => '1402/05/24 (Y/m/d)',
					'j F Y'     => '24 مرداد 1402 (j F Y)',
					'l, j F Y'  => 'سه‌شنبه, 24 مرداد 1402 (l, j F Y)',
					'Y/m/d H:i' => '1402/05/24 15:30 (Y/m/d H:i)',
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
				'default' => false,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			);
			$shamsi_fields[] = array(
				'id'      => 'wc_order_emails',
				'type'    => 'switcher',
				'title'   => 'تبدیل تاریخ در ایمیل‌های سفارش ووکامرس',
				'default' => false,
				'require' => array(
					array( 'master_switch', '==', true ),
					array( 'enable_woocommerce', '==', true ),
				),
			);
			$shamsi_fields[] = array(
				'id'      => 'wc_admin_orders',
				'type'    => 'switcher',
				'title'   => 'تبدیل تاریخ در جدول سفارشات (پیشخوان ووکامرس)',
				'default' => false,
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
				'default' => false,
				'require' => array(
					array( 'master_switch', '==', true ),
				),
			);
			$shamsi_fields[] = array(
				'id'      => 'enable_elementor_form_date',
				'type'    => 'switcher',
				'title'   => 'فیلد تاریخ شمسی در فرم‌های المنتور (Datepicker)',
				'desc'    => 'افزودن فیلد تقویم شمسی به ابزارک فرم المنتور پرو همراه با پنجره بازشو جهت انتخاب تاریخ توسط کاربران.',
				'default' => false,
				'require' => array(
					array( 'master_switch', '==', true ),
					array( 'enable_elementor', '==', true ),
				),
			);
		}

		return array(
			'id'     => 'shamsi_settings',
			'title'  => 'تنظیمات تاریخ شمسی',
			'desc'   => 'تبدیل هوشمند تاریخ‌های میلادی به شمسی در پیشخوان، فرانت‌اند و ایمیل‌ها.',
			'icon'   => self::get_icon(),
			'fields' => $shamsi_fields,
		);
	}

	public function init(): void {
		parent::init();

		$file = $this->get_path( 'class-singo-shamsi-core.php' );
		if ( file_exists( $file ) ) {
			require_once $file;
			if ( class_exists( '\Singo_Shamsi_Core' ) ) {
				\Singo_Shamsi_Core::get_instance();
			}
		}
	}
}
