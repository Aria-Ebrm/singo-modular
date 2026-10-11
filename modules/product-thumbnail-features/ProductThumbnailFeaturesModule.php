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

	public function get_optionino_fields(): array {
		return array(
			array(
				'id'      => 'enable_product_thumbnail_features',
				'type'    => 'switcher',
				'title'   => self::get_name(),
				'desc'    => 'نمایش باکس‌های شیک ویژگی‌های بندانگشتی به همراه آیکون و تصویر در صفحه محصول.',
				'default' => false,
			),
			array(
				'id'      => 'product_thumbnail_features_shortcode',
				'type'    => 'text',
				'title'   => 'شورت‌کد اختصاصی ویژگی‌های بندانگشتی محصول',
				'desc'    => 'این شورت‌کد را در برگه، قالب یا ویجت‌های المنتور قرار دهید (با تغییر پارامتر limit="4" می‌توانید تعداد باکس‌ها را تنظیم کنید).',
				'default' => '[product_features_boxes limit="4"]',
				'require' => array( array( 'enable_product_thumbnail_features', '==', true ) ),
			),
		);
	}

	/**
	 * Provide default shortcode value if not yet saved in database.
	 *
	 * @param mixed $options
	 * @return mixed
	 */
	public function filter_default_options( mixed $options ): mixed {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( empty( $options['product_thumbnail_features_shortcode'] ) ) {
			$options['product_thumbnail_features_shortcode'] = '[product_features_boxes limit="4"]';
		}

		return $options;
	}

	public function init(): void {
		parent::init();

		if ( is_admin() ) {
			add_filter( 'option_singo_tools_options', array( $this, 'filter_default_options' ) );
		}

		$file = $this->get_path( 'class-singo-product-thumbnail-features.php' );
		if ( file_exists( $file ) ) {
			require_once $file;
			if ( class_exists( '\Singo_Product_Thumbnail_Features' ) ) {
				\Singo_Product_Thumbnail_Features::get_instance();
			}
		}
	}
}
