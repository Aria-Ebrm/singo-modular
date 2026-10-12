<?php
declare(strict_types=1);

/**
 * Elementor Integration for Shamsi Date (Singo_Shamsi_Elementor)
 *
 * Handles Elementor dynamic date tags conversion and native Persian Datepicker
 * form field integration for Elementor Pro and Elementor 4 Atomic forms.
 *
 * @package SingoTools\Modules\ShamsiDate
 * @since 4.3.0
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Shamsi_Elementor {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize Elementor hooks.
	 */
	private function init_hooks(): void {
		// 1. Dynamic date & time tags conversion
		add_filter( 'elementor/dynamic_tags/post_date/value', array( $this, 'filter_elementor_dynamic_date' ), 20, 2 );
		add_filter( 'elementor/dynamic_tags/post_time/value', array( $this, 'filter_elementor_dynamic_date' ), 20, 2 );

		// 2. Elementor Pro Form Field & Atomic Date Picker
		if ( (bool) singo_tools_get_option( 'enable_elementor_form_date', false ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
			add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'register_scripts' ) );
			add_action( 'elementor/preview/enqueue_scripts', array( $this, 'register_scripts' ) );

			// Elementor Pro Classic Form Fields
			add_action( 'elementor_pro/forms/fields/register', array( $this, 'register_form_fields' ) );

			// Elementor 4 Atomic Widgets
			add_action( 'elementor/widgets/register', array( $this, 'register_atomic_widgets' ) );
		}
	}

	/**
	 * Register local calendar scripts for the Persian date picker.
	 */
	public function register_scripts(): void {
		$js_base_url = SINGO_TOOLS_URL . 'modules/shamsi-date/assets/js/';
		$version     = SINGO_TOOLS_VERSION;

		wp_register_script(
			'singo-persian-calendar',
			$js_base_url . 'singo-persian-calendar.js',
			array(),
			$version,
			true
		);

		wp_register_script(
			'singo-datepicker-init',
			$js_base_url . 'singo-datepicker-init.js',
			array( 'singo-persian-calendar' ),
			$version,
			true
		);
	}

	/**
	 * Register Persian Date field in Elementor Pro forms.
	 *
	 * @param mixed $form_fields_registrar
	 */
	public function register_form_fields( mixed $form_fields_registrar ): void {
		$field_file = __DIR__ . '/includes/class-singo-shamsi-elementor-form-field.php';
		if ( file_exists( $field_file ) ) {
			require_once $field_file;
			if ( class_exists( 'Singo_Shamsi_Elementor_Form_Field' ) && is_object( $form_fields_registrar ) && method_exists( $form_fields_registrar, 'register' ) ) {
				$form_fields_registrar->register( new Singo_Shamsi_Elementor_Form_Field() );
			}
		}
	}

	/**
	 * Register Persian Date picker widget in Elementor 4 Atomic Widgets.
	 *
	 * @param mixed $widgets_manager
	 */
	public function register_atomic_widgets( mixed $widgets_manager ): void {
		if ( ! class_exists( 'Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base' ) ) {
			return;
		}

		$atomic_file = __DIR__ . '/includes/class-singo-shamsi-elementor-atomic-date.php';
		if ( file_exists( $atomic_file ) ) {
			require_once $atomic_file;
			if ( class_exists( '\SingoTools\Modules\ShamsiDate\Singo_Persian_Date_Picker' ) && is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register' ) ) {
				$widgets_manager->register( new \SingoTools\Modules\ShamsiDate\Singo_Persian_Date_Picker() );
			}
		}
	}

	/**
	 * Intercept Elementor dynamic tags date value.
	 *
	 * @param mixed $value
	 * @param mixed $tag
	 * @return mixed
	 */
	public function filter_elementor_dynamic_date( mixed $value, mixed $tag ): mixed {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return $value;
		}

		$timestamp = strtotime( $value );
		if ( ! $timestamp ) {
			global $post;
			if ( $post ) {
				$timestamp = get_post_time( 'U', true, $post );
			}
		}

		if ( ! $timestamp ) {
			return $value;
		}

		$format = (string) singo_tools_get_option( 'default_format', 'Y/m/d' );
		if ( is_object( $tag ) && method_exists( $tag, 'get_settings' ) ) {
			$settings = $tag->get_settings();
			if ( is_array( $settings ) ) {
				if ( ! empty( $settings['format'] ) && is_string( $settings['format'] ) ) {
					$format = $settings['format'];
				} elseif ( ! empty( $settings['custom_format'] ) && is_string( $settings['custom_format'] ) ) {
					$format = $settings['custom_format'];
				}
			}
		}

		return Singo_Shamsi_Converter::format_date( (int) $timestamp, $format );
	}
}

if ( ! class_exists( 'SDM_Elementor' ) ) {
	class_alias( 'Singo_Shamsi_Elementor', 'SDM_Elementor' );
}
