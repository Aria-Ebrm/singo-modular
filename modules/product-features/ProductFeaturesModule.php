<?php
declare(strict_types=1);

namespace SingoTools\Modules\ProductFeatures;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Product Features Module (Self-Contained)
 *
 * @package SingoTools\Modules\ProductFeatures
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class ProductFeaturesModule extends AbstractModule {

	public static function get_id(): string {
		return 'product-features';
	}

	public static function get_name(): string {
		return 'گروه‌بندی ویژگی‌ها';
	}

	public static function get_icon(): string {
		return 'dashicons-list-view';
	}

	public static function get_order(): int {
		return 30;
	}

	public static function is_enabled(): bool {
		return true;
	}

	public static function is_context_valid(): bool {
		return true;
	}

	public function get_optionino_tab(): ?array {
		$view = $this->get_path( 'views/admin-groups-view.php' );
		if ( ! file_exists( $view ) ) {
			return null;
		}

		return array(
			'id'    => 'product_features',
			'title' => self::get_name(),
			'desc'  => 'دسته‌بندی، اولویت‌بندی و نمایش اختصاصی ویژگی‌های محصولات با شورت‌کد.',
			'icon'  => self::get_icon(),
			'file'  => $view,
		);
	}

	public function init(): void {
		parent::init();

		$legacy_init = $this->get_path( 'product-features.php' );
		if ( file_exists( $legacy_init ) ) {
			require_once $legacy_init;
		}

		$class_file = $this->get_path( 'class-singo-product-features.php' );
		if ( file_exists( $class_file ) ) {
			require_once $class_file;
			if ( class_exists( '\Singo_Product_Features' ) ) {
				\Singo_Product_Features::get_instance();
			}
		}
	}
}
