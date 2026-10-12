<?php
declare(strict_types=1);

/**
 * Singo Modular - Helper Functions (PHP 8+ Standardized)
 */

defined( 'ABSPATH' ) || exit;

/**
 * High-performance option getter with static in-memory caching.
 *
 * @param string $key Option key.
 * @param mixed  $default Default value.
 * @param bool   $reset_cache Whether to flush static memory cache.
 * @return mixed
 */
function singo_modular_get_option( string $key = '', mixed $default = false, bool $reset_cache = false ): mixed {
	static $options = null;
	static $sanitized_format = null;

	if ( null === $options || $reset_cache ) {
		$options = get_option( 'singo_modular_options', null );
		if ( empty( $options ) || ! is_array( $options ) ) {
			// Fallback to legacy singo_tools_options if available
			$options = get_option( 'singo_tools_options', null );
		}
		if ( empty( $options ) || ! is_array( $options ) ) {
			$options = get_option( 'sdm_options', array() );
		}
		if ( ! is_array( $options ) ) {
			$options = array();
		}
	}

	if ( empty( $key ) && $reset_cache ) {
		return $options;
	}

	$value = $options[ $key ] ?? '';

	if ( null === $value || '' === $value ) {
		return $default;
	}

	// Normalize boolean or array/object values
	if ( is_bool( $value ) || is_array( $value ) || is_object( $value ) ) {
		return $value;
	}

	$str_val = strtolower( trim( (string) $value ) );
	$value   = match ( $str_val ) {
		'false', '0', 'off' => false,
		'true', '1', 'on'   => true,
		default            => $value,
	};

	// Sanitize default_format string
	if ( 'default_format' === $key && is_string( $value ) ) {
		if ( null === $sanitized_format || $reset_cache ) {
			$sanitized_format = preg_replace( '/[^YymdDFjlNnwzWMtLaABgGhHisueIOPTZcrU\s\/:\-,\.]/', '', $value );
		}
		return $sanitized_format;
	}

	return $value;
}

/**
 * Update an option in singo_modular_options array.
 *
 * @param string $key Option key.
 * @param mixed  $value Option value.
 * @return bool
 */
function singo_modular_update_option( string $key, mixed $value ): bool {
	$options = get_option( 'singo_modular_options', array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}
	$options[ $key ] = $value;

	$updated = update_option( 'singo_modular_options', $options );
	singo_modular_get_option( '', '', true ); // Flush memory cache

	return $updated;
}

/**
 * Backwards compatibility aliases for Singo Tools ecosystem.
 */
if ( ! function_exists( 'singo_tools_get_option' ) ) {
	function singo_tools_get_option( string $key = '', mixed $default = false, bool $reset_cache = false ): mixed {
		return singo_modular_get_option( $key, $default, $reset_cache );
	}
}

if ( ! function_exists( 'singo_tools_update_option' ) ) {
	function singo_tools_update_option( string $key, mixed $value ): bool {
		return singo_modular_update_option( $key, $value );
	}
}

if ( ! function_exists( 'sdm_get_option' ) ) {
	function sdm_get_option( string $key = '', mixed $default = false ): mixed {
		return singo_modular_get_option( $key, $default );
	}
}

if ( ! function_exists( 'sdm_update_option' ) ) {
	function sdm_update_option( string $key, mixed $value ): bool {
		return singo_modular_update_option( $key, $value );
	}
}

/**
 * Get required capability to access Singo Modular settings page.
 *
 * @return string
 */
function singo_modular_get_admin_capability(): string {
	if ( class_exists( 'WooCommerce' ) ) {
		return 'manage_woocommerce';
	}
	return 'manage_options';
}

if ( ! function_exists( 'singo_tools_get_admin_capability' ) ) {
	function singo_tools_get_admin_capability(): string {
		return singo_modular_get_admin_capability();
	}
}

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function singo_modular_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

if ( ! function_exists( 'singo_tools_is_woocommerce_active' ) ) {
	function singo_tools_is_woocommerce_active(): bool {
		return singo_modular_is_woocommerce_active();
	}
}

/**
 * Check if Elementor is active.
 *
 * @return bool
 */
function singo_modular_is_elementor_active(): bool {
	return did_action( 'elementor/loaded' ) > 0 || class_exists( '\Elementor\Plugin' );
}

if ( ! function_exists( 'singo_tools_is_elementor_active' ) ) {
	function singo_tools_is_elementor_active(): bool {
		return singo_modular_is_elementor_active();
	}
}
