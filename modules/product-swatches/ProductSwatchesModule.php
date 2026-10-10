<?php
declare(strict_types=1);

namespace SingoTools\Modules\ProductSwatches;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Product Swatches Module (Self-Contained)
 *
 * @package SingoTools\Modules\ProductSwatches
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class ProductSwatchesModule extends AbstractModule {

	public static function get_id(): string {
		return 'product-swatches';
	}

	public static function get_name(): string {
		return 'سواچ محصولات رنگی';
	}

	public static function get_description(): string {
		return 'فعال‌سازی سیستم انتخاب سواچ رنگی و دکمه‌ای برای ویژگی‌های محصولات متغیر در ووکامرس.';
	}

	public static function get_icon(): string {
		return 'dashicons-color-picker';
	}

	public static function get_placement(): string {
		return 'other_tools';
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'enable_product_swatches', false );
	}

	public static function is_context_valid(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function init(): void {
		parent::init();

		$swatches_file = $this->get_path( 'class-singo-product-swatches.php' );
		if ( file_exists( $swatches_file ) ) {
			require_once $swatches_file;
			if ( class_exists( '\Singo_Product_Swatches' ) ) {
				\Singo_Product_Swatches::get_instance();
			}
		}
	}
}
