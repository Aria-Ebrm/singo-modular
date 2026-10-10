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
		return array(
			'id'     => 'product_features',
			'title'  => self::get_name(),
			'desc'   => 'دسته‌بندی، اولویت‌بندی و نمایش اختصاصی ویژگی‌های محصولات با شورت‌کد.',
			'icon'   => self::get_icon(),
			'fields' => array(
				array(
					'id'    => 'enable_product_features',
					'type'  => 'switcher',
					'title' => 'فعال‌سازی ماژول گروه‌بندی ویژگی‌ها',
					'desc'  => 'با غیرفعال‌سازی این گزینه، تمام قابلیت‌های گروه‌بندی ویژگی‌ها غیرفعال شده و جدول ویژگی‌ها به حالت عادی ووکامرس برمی‌گردد.',
				),
				array(
					'id'    => 'mu_shortcode_info',
					'type'  => 'text',
					'title' => 'شورت‌کد اختصاصی نمایش ویژگی‌های محصول',
					'desc'  => 'می‌توانید از این شورت‌کد در المنتور، برگه‌سازها، چیدمان محصول یا تب‌های اختصاصی قالب استفاده نمایید.',
				),
				array(
					'id'           => 'mu_attribute_groups',
					'type'         => 'repeater',
					'title'        => 'مدیریت و اولویت‌بندی گروه‌ها',
					'button_title' => 'افزودن گروه',
					'desc'         => 'برای تغییر اولویت گروه‌ها، آن‌ها را با ماوس بکشید و رها کنید (Drag & Drop). یک گروه را به عنوان پیش‌فرض مشخص نمایید.',
					'fields'       => array(
						array(
							'id'    => 'group_title',
							'type'  => 'text',
							'title' => 'عنوان گروه',
						),
						array(
							'id'    => 'is_default',
							'type'  => 'switcher',
							'title' => 'انتخاب به عنوان پیش‌فرض',
						),
					),
				),
			),
		);
	}

	public function init(): void {
		parent::init();

		add_filter( 'option_singo_tools_options', array( $this, 'filter_default_options' ) );

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

	/**
	 * Enqueue module admin scripts and styles using self-contained get_url().
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( $screen->id !== 'toplevel_page_singo-tools' && $screen->id !== 'singo-tools_page_singo-tools-debug' ) ) {
			return;
		}

		$css_path = $this->get_path( 'assets/css/product-features-repeater.css' );
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : SINGO_TOOLS_VERSION;
		wp_enqueue_style(
			'singo-product-features-repeater',
			$this->get_url( 'assets/css/product-features-repeater.css' ),
			array(),
			$css_ver
		);

		$js_path = $this->get_path( 'assets/js/product-features-repeater.js' );
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SINGO_TOOLS_VERSION;
		wp_enqueue_script(
			'singo-product-features-repeater',
			$this->get_url( 'assets/js/product-features-repeater.js' ),
			array( 'jquery' ),
			$js_ver,
			true
		);
	}

	/**
	 * Provide defaults for shortcode and repeater attribute groups if not yet saved in database.
	 *
	 * @param mixed $options
	 * @return mixed
	 */
	public function filter_default_options( mixed $options ): mixed {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( empty( $options['mu_shortcode_info'] ) ) {
			$options['mu_shortcode_info'] = '[dynamic_product_specs]';
		}

		if ( empty( $options['mu_attribute_groups'] ) ) {
			$groups = $options['mu_groups'] ?? get_option( 'mu_attribute_groups', array() );
			if ( empty( $groups ) || ! is_array( $groups ) ) {
				$groups = array( 'مشخصات کلی', 'سایر مشخصات' );
			}
			$default_group = $options['mu_default_group'] ?? get_option( 'mu_default_attribute_group', 'مشخصات کلی' );

			$repeater = array();
			foreach ( $groups as $g ) {
				$title = is_string( $g ) ? trim( $g ) : '';
				if ( ! empty( $title ) ) {
					$repeater[] = array(
						'group_title' => $title,
						'is_default'  => ( $title === $default_group ) ? 'true' : 'false',
					);
				}
			}

			if ( ! empty( $repeater ) && ! in_array( 'true', array_column( $repeater, 'is_default' ), true ) ) {
				$repeater[0]['is_default'] = 'true';
			}

			$options['mu_attribute_groups'] = $repeater;
		}

		return $options;
	}
}
