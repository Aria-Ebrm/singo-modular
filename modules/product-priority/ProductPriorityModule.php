<?php
declare(strict_types=1);

namespace SingoTools\Modules\ProductPriority;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Product Priority Module (Self-Contained)
 *
 * @package SingoTools\Modules\ProductPriority
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class ProductPriorityModule extends AbstractModule {

	public static function get_id(): string {
		return 'product-priority';
	}

	public static function get_name(): string {
		return 'اولویت محصولات برجسته';
	}

	public static function get_description(): string {
		return 'امکان امتیازدهی ۱ تا ۵ ستاره به محصولات در جدول مدیریت و صفحه ویرایش محصول، و نمایش اولویت‌دار محصولات در آرشیوها و فروشگاه بر اساس امتیاز.';
	}

	public static function get_icon(): string {
		return 'dashicons-star-filled';
	}

	public static function get_placement(): string {
		return 'other_tools';
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'enable_product_priority', false );
	}

	public static function is_context_valid(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function init(): void {
		parent::init();

		$priority_file = $this->get_path( 'class-singo-product-priority.php' );
		if ( file_exists( $priority_file ) ) {
			require_once $priority_file;
			if ( class_exists( '\Singo_Product_Priority' ) ) {
				\Singo_Product_Priority::get_instance();
			}
		}
	}
}
