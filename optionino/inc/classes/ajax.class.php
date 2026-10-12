<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;
/**
 * AJAX Class for Optionino Framework
 *
 * @version 1.0.1
 * @since 1.0.0
 */

if ( ! function_exists( 'optionino_normalize_hex_color' ) ) {
	/**
	 * Normalize & sanitize a hex color for storage.
	 * Returns '' (empty string) for invalid/empty input. Never returns null.
	 *
	 * @param mixed $raw
	 * @return string
	 */
	function optionino_normalize_hex_color( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}

		$raw = trim( wp_unslash( $raw ) );

		if ( $raw === '' ) {
			return '';
		}

		if ( $raw[0] !== '#' ) {
			$raw = '#' . $raw;
		}

		$hex = sanitize_hex_color( $raw );
		if ( $hex ) {
			return $hex;
		}

		if ( preg_match( '/^#([0-9A-Fa-f]{8})$/', $raw ) ) {
			return strtoupper( $raw );
		}

		return '';
	}
}

if ( ! function_exists( 'optionino_recursive_sanitize' ) ) {
	/**
	 * Recursively sanitize arrays and scalar values safely without throwing TypeErrors.
	 *
	 * @param mixed $val
	 * @return mixed
	 */
	function optionino_recursive_sanitize( $val ) {
		if ( is_array( $val ) ) {
			$clean = array();
			foreach ( $val as $k => $v ) {
				$clean_key           = is_string( $k ) ? sanitize_key( $k ) : intval( $k );
				$clean[ $clean_key ] = optionino_recursive_sanitize( $v );
			}
			return $clean;
		}
		if ( is_scalar( $val ) ) {
			return sanitize_text_field( wp_unslash( (string) $val ) );
		}
		return '';
	}
}

if ( ! class_exists( 'OPTNNO_Ajax_Handler', false ) ) {
	#[\AllowDynamicProperties]
	class OPTNNO_Ajax_Handler {

		public function __construct() {
			add_action( 'wp_ajax_save_optionino_data', array( $this, 'save_optionino_data' ) );
		}

		/**
		 * Handle AJAX save for Optionino.
		 */
		public static function save_optionino_data() {
			if ( ! check_ajax_referer( 'optionino_nonce', 'security', false ) ) {
				wp_send_json_error( array( 'message' => __( 'تأییدیه امنیتی منقضی شده است، لطفاً صفحه را تازه‌سازی کنید.', OPTNNO_TEXTDOMAIN ) ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'سطح دسترسی شما کافی نیست.', OPTNNO_TEXTDOMAIN ) ) );
			}

			$dev_name = isset( $_POST['dev_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dev_name'] ) ) : '';
			$data     = $_POST;

			if ( empty( $dev_name ) || empty( $data ) || ! is_array( $data ) ) {
				wp_send_json_error( array( 'message' => __( 'اطلاعات درخواستی نامعتبر است!', OPTNNO_TEXTDOMAIN ) ) );
			}

			$field_ids    = self::get_field_ids_for_dev_name( $dev_name );
			$data_to_save = array();
			$ignore_keys  = array( 'action', 'security', 'dev_name', 'save-optionino' );

			// 1. Process fields registered via Optionino tab fields
			if ( is_array( $field_ids ) ) {
				foreach ( $field_ids as $field_id => $field_type ) {
					if ( ! isset( $data[ $field_id ] ) ) {
						continue;
					}

					$sanitized_value = '';

					switch ( $field_type ) {
						case 'repeater':
							$field_value_posts = array();
							$raw_string        = is_string( $data[ $field_id ] ) ? $data[ $field_id ] : '';
							if ( ! empty( $raw_string ) ) {
								parse_str( $raw_string, $field_value_post );

								if ( is_array( $field_value_post ) ) {
									foreach ( $field_value_post as $key => $subarray ) {
										$subarray_parsed = array();
										if ( is_string( $subarray ) ) {
											parse_str( $subarray, $subarray_parsed );
										} elseif ( is_array( $subarray ) ) {
											$subarray_parsed = $subarray;
										}
										$field_value_posts[ $key ] = $subarray_parsed;
									}
								}
							}
							$sanitized_value = $field_value_posts;
							break;

						case 'number':
							$sanitized_value = (int) sanitize_text_field( wp_unslash( $data[ $field_id ] ) );
							break;

						case 'switcher':
							$flag            = is_string( $data[ $field_id ] ) ? $data[ $field_id ] : '';
							$sanitized_value = ( $flag === 'off' ) ? 'false' : 'true';
							break;

						case 'tinymce':
							$sanitized_value = is_string( $data[ $field_id ] ) ? wp_kses_post( wp_unslash( $data[ $field_id ] ) ) : '';
							break;

						case 'color':
							$sanitized_value = optionino_normalize_hex_color( $data[ $field_id ] );
							break;

						default:
							$sanitized_value = optionino_recursive_sanitize( $data[ $field_id ] );
							break;
					}

					if ( $sanitized_value === null ) {
						$sanitized_value = '';
					}

					$data_to_save[ $field_id ] = $sanitized_value;
				}
			}

			// 2. Process all custom view fields sent in form submit
			foreach ( $data as $key => $val ) {
				if ( in_array( $key, $ignore_keys, true ) || isset( $data_to_save[ $key ] ) ) {
					continue;
				}

				$data_to_save[ $key ] = optionino_recursive_sanitize( $val );
			}

			self::save_data( $dev_name, $data_to_save );

			wp_send_json_success( array( 'message' => __( 'اطلاعات با موفقیت ذخیره شد.', OPTNNO_TEXTDOMAIN ) ) );
		}

		/**
		 * Build a map of field_id => type for a given dev name.
		 *
		 * @param string $dev_name
		 * @return array|false
		 */
		public static function get_field_ids_for_dev_name( $dev_name ) {
			$tabs     = isset( OPTNNO::$tabs ) ? OPTNNO::$tabs : array();
			$fieldIds = array();

			if ( isset( $tabs[ $dev_name ] ) && is_array( $tabs[ $dev_name ] ) ) {
				foreach ( $tabs[ $dev_name ] as $tab ) {
					if ( isset( $tab['fields'] ) && is_array( $tab['fields'] ) ) {
						foreach ( $tab['fields'] as $field ) {
							if ( isset( $field['id'] ) ) {
								$type                     = isset( $field['type'] ) ? $field['type'] : 'text';
								$fieldIds[ $field['id'] ] = $type;
							}
						}
					}
				}
				return $fieldIds;
			}
			return false;
		}

		/**
		 * Save defaults directly.
		 *
		 * @param string $dev_name
		 * @param array  $defaults
		 * @return void
		 */
		public static function defaults( $dev_name, $defaults ) {
			self::save_data( $dev_name, $defaults );
		}

		/**
		 * Merge and persist data.
		 *
		 * @param string $dev_name
		 * @param array  $data_to_save
		 * @return void
		 */
		public static function save_data( $dev_name, $data_to_save ) {
			$saved_data = get_option( $dev_name, array() );

			if ( ! is_array( $saved_data ) ) {
				$saved_data = array();
			}

			foreach ( $data_to_save as $field_id => $sanitized_value ) {
				$saved_data[ $field_id ] = $sanitized_value;
			}

			update_option( $dev_name, $saved_data );
		}
	}

	new OPTNNO_Ajax_Handler();
}
