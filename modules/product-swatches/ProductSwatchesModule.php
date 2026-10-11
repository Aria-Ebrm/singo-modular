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

	public function get_optionino_fields(): array {
		return array(
			array(
				'id'      => 'enable_product_swatches',
				'type'    => 'switcher',
				'title'   => self::get_name(),
				'desc'    => 'فعال‌سازی سیستم انتخاب سواچ رنگی و دکمه‌ای برای ویژگی‌های محصولات متغیر در ووکامرس.',
				'default' => false,
			),
			array(
				'id'          => 'product_swatches_guide',
				'type'        => 'guide',
				'title'       => 'راهنمای پیکربندی سواچ محصولات متغیر',
				'require'     => array( array( 'enable_product_swatches', '==', true ) ),
				'guide_title' => 'مراحل راه‌اندازی و استفاده از سواچ‌های رنگی و دکمه‌ای',
				'guide_icon'  => 'dashicons-color-picker',
				'guide_items' => array(
					'تنظیم نوع سواچ ویژگی: به منوی «محصولات > ویژگی‌ها» در پیشخوان وردپرس مراجعه کنید و هنگام ایجاد یا ویرایش ویژگی، نوع نمایش را روی «رنگ» یا «دکمه» قرار دهید.',
					'انتخاب رنگ یا تصویر برای هر مقدار: وارد بخش پیکربندی مقادیر (Terms) همان ویژگی شوید و با استفاده از انتخاب‌گر رنگ، کد رنگ اختصاصی هر گزینه (مانند مشکی، سفید، قرمز و...) را تعیین کنید.',
					'نمایش خودکار و مدرن در صفحه محصول: در صفحات محصولات متغیر، منوهای کشویی سنتی به صورت کاملاً خودکار به دایره‌های رنگی و برچسب‌های دکمه‌ای واکنش‌گرا و جذاب تبدیل می‌شوند.',
				),
			),
		);
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
