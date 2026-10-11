<?php
declare(strict_types=1);

namespace SingoTools\Modules\VariablePriceFormat;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Custom Variable Product Price Format Module
 *
 * @package SingoTools\Modules\VariablePriceFormat
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class VariablePriceFormatModule extends AbstractModule {

	public static function get_id(): string {
		return 'variable-price-format';
	}

	public static function get_name(): string {
		return 'قیمت محصولات متغیر';
	}

	public static function get_description(): string {
		return 'تغییر فرمت نمایش قیمت محصولات متغیر از بازه قیمتی به ساختار «از [کمترین قیمت]».';
	}

	public static function get_icon(): string {
		return 'dashicons-tag';
	}

	public static function get_placement(): string {
		return 'other_tools';
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'custom_variable_price_format', false );
	}

	public static function is_context_valid(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function get_optionino_fields(): array {
		return array(
			array(
				'id'      => 'custom_variable_price_format',
				'type'    => 'switcher',
				'title'   => self::get_name(),
				'desc'    => self::get_description(),
				'default' => false,
			),
			array(
				'id'          => 'variable_price_format_guide',
				'type'        => 'guide',
				'title'       => 'راهنمای ساختار قیمت محصولات متغیر',
				'require'     => array( array( 'custom_variable_price_format', '==', true ) ),
				'guide_title' => 'نحوه عملکرد فرمت اختصاصی قیمت متغیر',
				'guide_icon'  => 'dashicons-tag',
				'guide_items' => array(
					'حذف بازه قیمتی پیچیده: ساختار پیش‌فرض ووکامرس (مانند «۱۰۰,۰۰۰ تومان – ۳۰۰,۰۰۰ تومان») که موجب شلوغی بصری در آرشیوها می‌شود، جای خود را به ساختار مینیمال و استاندارد «از [کمترین قیمت]» می‌دهد.',
					'اعمال سراسری و خودکار: تغییر فرمت قیمت بلافاصله پس از فعال‌سازی، در صفحه اصلی، فروشگاه، آرشیو دسته‌ها، نتایج جستجو و صفحه تکی محصول اعمال می‌گردد.',
					'بدون نیاز به شورت‌کد یا دستکاری قالب: این تغییر از طریق فیلترهای هسته ووکامرس اعمال شده و هیچ وابستگی به قالب یا افزونه‌های جانبی ندارد.',
				),
			),
		);
	}

	public function init(): void {
		parent::init();

		add_filter( 'woocommerce_variable_price_html', array( $this, 'format_variable_product_price' ), 99, 2 );
	}

	/**
	 * Format variable product price display (از ...).
	 *
	 * @param string      $price   Original price HTML.
	 * @param \WC_Product $product Product object.
	 * @return string
	 */
	public function format_variable_product_price( string $price, \WC_Product $product ): string {
		if ( ! method_exists( $product, 'get_variation_price' ) ) {
			return $price;
		}

		// دریافت کمترین و بیشترین قیمت بین متغیرها
		$min_price_regular = $product->get_variation_regular_price( 'min', true );
		$min_price_sale    = $product->get_variation_sale_price( 'min', true );
		$max_price         = $product->get_variation_price( 'max', true );
		$min_price         = $product->get_variation_price( 'min', true );

		// اگر قیمت تمام متغیرها یکسان باشد، بازه قیمتی اعمال نمی‌شود
		if ( $min_price === $max_price ) {
			return $price;
		}

		// بررسی وجود تخفیف روی کمترین قیمت
		if ( ! empty( $min_price_sale ) && $min_price_regular !== $min_price_sale ) {
			$formatted_price = sprintf(
				'<del>%s</del> <ins>%s</ins>',
				wc_price( (float) $min_price_regular ),
				wc_price( (float) $min_price_sale )
			);
		} else {
			$formatted_price = wc_price( (float) $min_price );
		}

		return sprintf( '%s %s', esc_html__( 'از', 'woocommerce' ), $formatted_price );
	}
}
