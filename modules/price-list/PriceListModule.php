<?php
declare(strict_types=1);

namespace SingoTools\Modules\PriceList;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Price List PDF Module (Self-Contained)
 *
 * @package SingoTools\Modules\PriceList
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class PriceListModule extends AbstractModule {

	public static function get_id(): string {
		return 'price-list';
	}

	public static function get_name(): string {
		return 'خروجی لیست قیمت (PDF)';
	}

	public static function get_icon(): string {
		return 'dashicons-media-document';
	}

	public static function get_order(): int {
		return 50;
	}

	public static function is_enabled(): bool {
		return true;
	}

	public static function is_context_valid(): bool {
		return class_exists( 'WooCommerce' ) && ( is_admin() || wp_doing_ajax() );
	}

	public function get_optionino_tab(): ?array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return null;
		}

		$view = $this->get_path( 'views/admin-price-list-view.php' );
		if ( ! file_exists( $view ) ) {
			return null;
		}

		return array(
			'id'    => 'price_list_pdf',
			'title' => self::get_name(),
			'desc'  => 'تولید و دانلود مستقیم لیست قیمت محصولات ووکامرس در قالب استاندارد PDF بر اساس دسته‌بندی با تاریخ روز شمسی و لوگو.',
			'icon'  => self::get_icon(),
			'file'  => $view,
		);
	}

	public function init(): void {
		parent::init();

		$file = $this->get_path( 'class-singo-price-list.php' );
		if ( file_exists( $file ) ) {
			require_once $file;
			if ( class_exists( '\Singo_Price_List' ) ) {
				\Singo_Price_List::get_instance();
			}
		}
	}
}
