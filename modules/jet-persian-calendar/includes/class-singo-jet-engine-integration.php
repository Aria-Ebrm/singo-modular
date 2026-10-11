<?php
declare(strict_types=1);

namespace SingoTools\Modules\JetPersianCalendar;

/**
 * Singo Tools - JetEngine Integration for Persian Calendar
 *
 * Handles JetEngine meta fields (date, datetime-local, time),
 * CPT/CCT fields, Listing Grid dynamic fields, and admin meta boxes.
 *
 * @package SingoTools\Modules\JetPersianCalendar
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class JetEngineIntegration {

	private string $module_url;
	private string $version;
	private bool $convert_digits;

	public function __construct( string $module_url, string $version, bool $convert_digits = true ) {
		$this->module_url     = $module_url;
		$this->version        = $version;
		$this->convert_digits = $convert_digits;

		$this->init_hooks();
	}

	private function init_hooks(): void {
		// Asset Enqueues
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );

		// Dependency Injections
		add_action( 'wp_default_scripts', array( $this, 'add_dependencies' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dependencies' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_dependencies' ), 100 );

		// Listing Grid Meta Values
		add_filter( 'jet-engine/listing/data/get-post-meta', array( $this, 'filter_listing_meta' ), 10, 3 );
		add_filter( 'jet-engine/listing/data/get-term-meta', array( $this, 'filter_listing_meta' ), 10, 3 );
		add_filter( 'jet-engine/listing/data/get-user-meta', array( $this, 'filter_listing_meta' ), 10, 3 );
		add_filter( 'jet-engine/listing/data/get-comment-meta', array( $this, 'filter_listing_meta' ), 10, 3 );

		// Listing Grid Dynamic Fields
		add_filter( 'jet-engine/listings/dynamic-field/field-value', array( $this, 'dynamic_field_value' ), 10, 2 );
		add_filter( 'jet-engine/listings/dynamic-field/custom-value', array( $this, 'dynamic_field_custom_value' ), 10, 3 );
	}

	public function enqueue_assets(): void {
		if ( ! class_exists( '\Jet_Engine' ) ) {
			return;
		}

		JetCalendarHelpers::enqueue_core_assets( $this->module_url, $this->version );

		wp_enqueue_style(
			'singo-jet-engine-calendar-styles',
			$this->module_url . 'assets/css/integrate-jet-engine.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'singo-integrate-jet-engine',
			$this->module_url . 'assets/js/integrate-jet-engine.js',
			array( 'jquery', 'singo-jet-persian-calendar-core' ),
			$this->version,
			true
		);
	}

	public function add_dependencies(): void {
		if ( ! class_exists( '\Jet_Engine' ) ) {
			return;
		}

		JetCalendarHelpers::inject_dependency(
			array(
				'jet-engine-meta-boxes',
				'jet-engine-advanced-date-field',
				'jet-engine-cct-list',
				'jet-engine-cct-edit',
				'jet-engine-cct-quick-edit',
				'jet-engine-cct-relations',
				'jet-engine-cct-query-dialog',
			),
			'singo-integrate-jet-engine'
		);
	}

	/**
	 * Retrieve all meta keys registered in JetEngine meta boxes with date or datetime types.
	 *
	 * @return array<int, string>
	 */
	public function get_date_meta_keys(): array {
		static $keys = null;
		if ( null !== $keys ) {
			return $keys;
		}

		$keys = array();

		if ( function_exists( 'jet_engine' ) && ! empty( jet_engine()->meta_boxes ) ) {
			$registered_fields = jet_engine()->meta_boxes->get_registered_fields();

			$process_fields = function( $fields ) use ( &$process_fields, &$keys ) {
				if ( ! is_array( $fields ) ) {
					return;
				}
				foreach ( $fields as $field ) {
					if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
						continue;
					}

					if ( in_array( $field['type'], array( 'date', 'datetime-local', 'datetime' ), true ) ) {
						$keys[] = $field['name'];
					} elseif ( 'repeater' === $field['type'] && ! empty( $field['repeater-fields'] ) ) {
						$has_date = false;
						foreach ( $field['repeater-fields'] as $subfield ) {
							if ( ! empty( $subfield['type'] ) && in_array( $subfield['type'], array( 'date', 'datetime-local', 'datetime' ), true ) ) {
								$has_date = true;
								break;
							}
						}
						if ( $has_date ) {
							$keys[] = $field['name'];
						}
					}
				}
			};

			if ( is_array( $registered_fields ) ) {
				foreach ( $registered_fields as $object_type => $fields ) {
					$process_fields( $fields );
				}
			}
		}

		/**
		 * Filter registered date meta keys for JetEngine.
		 *
		 * @param array<int, string> $keys
		 */
		$keys = apply_filters( 'singo_jet_engine_date_meta_keys', $keys );
		$keys = array_values( array_unique( $keys ) );

		return $keys;
	}

	/**
	 * Format date meta values when queried by JetEngine listing grid.
	 *
	 * @param mixed  $value     Original meta value.
	 * @param string $key       Meta key name.
	 * @param int    $object_id Object ID.
	 * @return mixed
	 */
	public function filter_listing_meta( mixed $value, string $key, int $object_id ): mixed {
		if ( null === $value || '' === $value ) {
			return $value;
		}

		if ( JetCalendarHelpers::should_skip_conversion() ) {
			return $value;
		}

		$date_keys = $this->get_date_meta_keys();
		if ( ! in_array( $key, $date_keys, true ) ) {
			return $value;
		}

		if ( ! apply_filters( 'singo_jet_engine_should_convert_meta', true, $key, $object_id ) ) {
			return $value;
		}

		return $this->convert_meta_value( $value );
	}

	/**
	 * Convert Gregorian date strings to Jalali formatted strings.
	 *
	 * @param mixed $val Input date value.
	 * @return mixed
	 */
	public function convert_meta_value( mixed $val ): mixed {
		if ( is_array( $val ) ) {
			foreach ( $val as $k => $v ) {
				$val[ $k ] = $this->convert_meta_value( $v );
			}
			return $val;
		}

		if ( ! is_string( $val ) ) {
			return $val;
		}

		// Match YYYY-MM-DD
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $val, $matches ) ) {
			$y = (int) $matches[1];
			$m = (int) $matches[2];
			$d = (int) $matches[3];
			if ( checkdate( $m, $d, $y ) ) {
				$jalali = JetCalendarConverter::gregorian_to_jalali( $y, $m, $d );
				$j_date = sprintf( '%04d/%02d/%02d', $jalali['y'], $jalali['m'], $jalali['d'] );
				return $this->maybe_convert_digits( $j_date );
			}
		}

		// Match YYYY-MM-DDTHH:mm:ss or YYYY-MM-DD HH:mm:ss
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/', $val, $matches ) ) {
			$y  = (int) $matches[1];
			$m  = (int) $matches[2];
			$d  = (int) $matches[3];
			$hh = (int) $matches[4];
			$mi = (int) $matches[5];
			$ss = isset( $matches[6] ) ? (int) $matches[6] : null;

			if ( checkdate( $m, $d, $y ) && $hh >= 0 && $hh <= 23 && $mi >= 0 && $mi <= 59 && ( null === $ss || ( $ss >= 0 && $ss <= 59 ) ) ) {
				$jalali = JetCalendarConverter::gregorian_to_jalali( $y, $m, $d );
				if ( null !== $ss ) {
					$j_date = sprintf( '%04d/%02d/%02d %02d:%02d:%02d', $jalali['y'], $jalali['m'], $jalali['d'], $hh, $mi, $ss );
				} else {
					$j_date = sprintf( '%04d/%02d/%02d %02d:%02d', $jalali['y'], $jalali['m'], $jalali['d'], $hh, $mi );
				}
				return $this->maybe_convert_digits( $j_date );
			}
		}

		return $val;
	}

	private function maybe_convert_digits( string $str ): string {
		if ( $this->convert_digits ) {
			return JetCalendarConverter::to_persian_digits( $str );
		}
		return $str;
	}

	/**
	 * Intercept JetEngine listing dynamic field output value.
	 *
	 * @param mixed $value
	 * @param mixed $settings
	 * @return mixed
	 */
	public function dynamic_field_value( mixed $value, mixed $settings = null ): mixed {
		if ( null === $value || '' === $value ) {
			return $value;
		}

		if ( JetCalendarHelpers::should_skip_conversion() ) {
			return $value;
		}

		static $running = false;
		if ( $running ) {
			return $value;
		}

		$running = true;
		try {
			$value = $this->convert_meta_value( $value );
		} finally {
			$running = false;
		}

		return $value;
	}

	/**
	 * Intercept JetEngine custom-value filter to intercept post-fetch values.
	 *
	 * @param mixed $value
	 * @param mixed $settings
	 * @param mixed $widget
	 * @return mixed
	 */
	public function dynamic_field_custom_value( mixed $value, mixed $settings, mixed $widget ): mixed {
		if ( JetCalendarHelpers::should_skip_conversion() ) {
			return $value;
		}

		if ( $this->is_date_callback_active( $settings ) ) {
			return $value;
		}

		static $running = false;
		if ( $running ) {
			return $value;
		}

		$running = true;
		try {
			remove_filter( 'jet-engine/listings/dynamic-field/custom-value', array( $this, 'dynamic_field_custom_value' ), 10 );
			$result = is_object( $widget ) && method_exists( $widget, 'get_field_content' ) ? $widget->get_field_content( $settings ) : $value;
		} catch ( \Throwable $e ) {
			$result = $value;
		} finally {
			add_filter( 'jet-engine/listings/dynamic-field/custom-value', array( $this, 'dynamic_field_custom_value' ), 10, 3 );
			$running = false;
		}

		return $this->convert_meta_value( $result );
	}

	private function is_date_callback_active( mixed $settings ): bool {
		if ( empty( $settings ) || ! is_array( $settings ) ) {
			return false;
		}

		$is_filtered = $settings['dynamic_field_filter'] ?? false;
		$is_filtered = filter_var( $is_filtered, FILTER_VALIDATE_BOOLEAN );

		if ( ! $is_filtered ) {
			return false;
		}

		$callback = $settings['filter_callback'] ?? '';
		if ( 'format_date' === $callback ) {
			return true;
		}

		$callbacks = $settings['filter_callbacks'] ?? array();
		if ( is_array( $callbacks ) ) {
			foreach ( $callbacks as $cb_data ) {
				$cb = $cb_data['filter_callback'] ?? '';
				if ( 'format_date' === $cb ) {
					return true;
				}
			}
		}

		return false;
	}
}
