<?php
declare(strict_types=1);

namespace SingoTools\Modules\ProductThumbnailFeatures;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Product Thumbnail Features Module (Self-Contained)
 *
 * @package SingoTools\Modules\ProductThumbnailFeatures
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class ProductThumbnailFeaturesModule extends AbstractModule {

	public static function get_id(): string {
		return 'product-thumbnail-features';
	}

	public static function get_name(): string {
		return 'ویژگی بند انگشتی محصولات';
	}

	public static function get_description(): string {
		return 'فعال‌سازی شورت‌کد [product_features_boxes limit="4"] برای نمایش باکس‌های ویژگی بندانگشتی همراه با تصویر در صفحه محصول.';
	}

	public static function get_icon(): string {
		return 'dashicons-grid-view';
	}

	public static function get_placement(): string {
		return 'other_tools';
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'enable_product_thumbnail_features', false );
	}

	public static function is_context_valid(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function init(): void {
		parent::init();

		$file = $this->get_path( 'class-singo-product-thumbnail-features.php' );
		if ( file_exists( $file ) ) {
			require_once $file;
			if ( class_exists( '\Singo_Product_Thumbnail_Features' ) ) {
				\Singo_Product_Thumbnail_Features::get_instance();
			}
		}
	}
}
