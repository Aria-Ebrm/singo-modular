<?php
declare(strict_types=1);

namespace SingoTools\Modules\NeshanMap;

use SingoTools\Core\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Singo Tools - Native Neshan Map Module for Elementor
 *
 * Self-contained module providing a fast, native Neshan Map widget
 * with vector map tiles, live traffic, and POI markers.
 *
 * @package SingoTools\Modules\NeshanMap
 * @since 6.1.0
 */
class NeshanMapModule extends AbstractModule {

	public static function get_id(): string {
		return 'neshan-map';
	}

	public static function get_name(): string {
		return 'ویجت نقشه بومی نشان (المنتور)';
	}

	public static function get_description(): string {
		return 'افزودن ویجت بهینه و بومی نقشه نشان به ویرایشگر المنتور با پشتیبانی از لایه‌های وکتور، ترافیک زنده و اماکن مهم (POI).';
	}

	public static function get_icon(): string {
		return 'dashicons-location-alt';
	}

	public static function get_placement(): string {
		return 'other_tools';
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'enable_neshan_map', false );
	}

	public static function is_context_valid(): bool {
		return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
	}

	public function get_optionino_fields(): array {
		return array(
			array(
				'id'      => 'enable_neshan_map',
				'type'    => 'switcher',
				'title'   => self::get_name(),
				'desc'    => self::get_description(),
				'default' => false,
			),
			array(
				'id'      => 'neshan_map_default_api_key',
				'type'    => 'text',
				'title'   => 'کلید وب API پیش‌فرض نشان',
				'desc'    => 'در صورت تمایل، کلید وب API دریافتی از پنل توسعه‌دهندگان نشان (platform.neshan.org) را وارد کنید تا در تمام ویجت‌ها به صورت پیش‌فرض درج شود.',
				'default' => '',
				'require' => array( array( 'enable_neshan_map', '==', true ) ),
			),
		);
	}

	public function init(): void {
		parent::init();

		// Register Elementor Widget
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Register and enqueue Neshan Map assets
		add_action( 'wp_enqueue_scripts', array( $this, 'register_map_assets' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'register_map_assets' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'register_map_assets' ) );

		// Enqueue in Editor and Preview contexts
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'enqueue_editor_styles' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_editor_styles' ) );
	}

	/**
	 * Register Neshan Map widget with Elementor Widgets Manager.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public function register_widgets( $widgets_manager ): void {
		$widget_file = $this->get_path( 'includes/class-singo-neshan-map-widget.php' );
		if ( file_exists( $widget_file ) ) {
			require_once $widget_file;

			if ( class_exists( 'SingoTools\Modules\NeshanMap\Widgets\Singo_Neshan_Map_Widget' ) ) {
				$widgets_manager->register( new Widgets\Singo_Neshan_Map_Widget() );
			}
		}
	}

	/**
	 * Register Neshan Map SDK and custom editor scripts.
	 */
	public function register_map_assets(): void {
		// Register official Neshan Map SDK
		wp_register_style(
			'neshan-map-sdk',
			'https://static.neshan.org/sdk/mapboxgl/v1.13.2/neshan-sdk/v1.1.5/index.css',
			array(),
			'1.1.5'
		);

		wp_register_script(
			'neshan-map-sdk',
			'https://static.neshan.org/sdk/mapboxgl/v1.13.2/neshan-sdk/v1.1.5/index.js',
			array(),
			'1.1.5',
			true
		);

		// Local styling
		wp_register_style(
			'singo-neshan-map-style',
			$this->get_url( 'assets/css/neshan-map.css' ),
			array( 'neshan-map-sdk' ),
			SINGO_TOOLS_VERSION
		);

		// Local editor integration script
		wp_register_script(
			'singo-neshan-map-editor',
			$this->get_url( 'assets/js/neshan-map-editor.js' ),
			array( 'jquery', 'neshan-map-sdk' ),
			SINGO_TOOLS_VERSION,
			true
		);
	}

	/**
	 * Enqueue scripts in Elementor Editor and Preview.
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_script( 'neshan-map-sdk' );
		wp_enqueue_script( 'singo-neshan-map-editor' );
	}

	/**
	 * Enqueue styles in Elementor Editor and Preview.
	 */
	public function enqueue_editor_styles(): void {
		wp_enqueue_style( 'neshan-map-sdk' );
		wp_enqueue_style( 'singo-neshan-map-style' );
	}

	/**
	 * Automatically enqueue assets on the frontend when widget is used.
	 */
	public function enqueue_frontend_assets(): void {
		wp_enqueue_style( 'neshan-map-sdk' );
		wp_enqueue_style( 'singo-neshan-map-style' );
		wp_enqueue_script( 'neshan-map-sdk' );
		wp_enqueue_script( 'singo-neshan-map-editor' );
	}
}
