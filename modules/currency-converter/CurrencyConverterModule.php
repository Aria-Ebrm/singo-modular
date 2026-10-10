<?php
declare(strict_types=1);

namespace SingoTools\Modules\CurrencyConverter;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - Currency Converter Module (Self-Contained)
 *
 * @package SingoTools\Modules\CurrencyConverter
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class CurrencyConverterModule extends AbstractModule {

	/**
	 * Unique module identifier.
	 */
	public static function get_id(): string {
		return 'currency-converter';
	}

	/**
	 * Human-readable module name.
	 */
	public static function get_name(): string {
		return 'دستیار ارزی ووکامرس';
	}

	/**
	 * Dashicon for Optionino tab.
	 */
	public static function get_icon(): string {
		return 'dashicons-money-alt';
	}

	/**
	 * Check if module is enabled in settings.
	 */
	public static function get_order(): int {
		return 20;
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'enable_currency_converter', false );
	}

	/**
	 * Context-aware check: requires WooCommerce.
	 */
	public static function is_context_valid(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Optionino native tab definition.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_optionino_tab(): ?array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return null;
		}

		return array(
			'id'     => 'currency_settings',
			'title'  => self::get_name(),
			'desc'   => 'مدیریت و تبدیل نرخ ارزهای خارجی (دلار، یورو و یوان) به تومان در ووکامرس.',
			'icon'   => self::get_icon(),
			'fields' => array(
				array(
					'id'      => 'enable_currency_converter',
					'type'    => 'switcher',
					'title'   => 'فعال‌سازی دستیار ارزی ووکامرس',
					'desc'    => 'با خاموش کردن این گزینه، تمام قیمت‌ها به تومان برگردانده شده و دستیار ارزی غیرفعال می‌شود.',
					'default' => false,
				),
				array(
					'id'      => 'rate_usd',
					'type'    => 'number',
					'title'   => 'نرخ دلار (USD) به تومان',
					'default' => '1',
					'require' => array(
						array( 'enable_currency_converter', '==', true ),
					),
				),
				array(
					'id'      => 'rate_eur',
					'type'    => 'number',
					'title'   => 'نرخ یورو (EUR) به تومان',
					'default' => '1',
					'require' => array(
						array( 'enable_currency_converter', '==', true ),
					),
				),
				array(
					'id'      => 'rate_cny',
					'type'    => 'number',
					'title'   => 'نرخ یوان چین (CNY) به تومان',
					'default' => '1',
					'require' => array(
						array( 'enable_currency_converter', '==', true ),
					),
				),
			),
		);
	}

	/**
	 * Initialize module.
	 */
	public function init(): void {
		parent::init();

		$converter_file = $this->get_path( 'class-singo-currency-converter.php' );
		if ( file_exists( $converter_file ) ) {
			require_once $converter_file;
			if ( class_exists( '\Singo_Currency_Converter' ) ) {
				new \Singo_Currency_Converter();
			}
		}
	}
}
