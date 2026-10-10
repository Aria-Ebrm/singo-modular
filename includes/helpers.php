<?php
declare(strict_types=1);

/**
 * Singo Tools - Helper Functions (PHP 8+ Standardized)
 */

defined( 'ABSPATH' ) || exit;

/**
 * High-performance option getter with static memory caching and backwards compatibility.
 *
 * @param string $key Option key.
 * @param mixed  $default Default value.
 * @param bool   $reset_cache Whether to flush static memory cache.
 * @return mixed
 */
function singo_tools_get_option( string $key = '', mixed $default = false, bool $reset_cache = false ): mixed {
	static $options = null;
	static $sanitized_format = null;

	if ( null === $options || $reset_cache ) {
		$options = get_option( 'singo_tools_options', null );
		if ( empty( $options ) || ! is_array( $options ) ) {
			// Fallback to legacy sdm_options if available
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
 * Update an option in singo_tools_options array.
 *
 * @param string $key Option key.
 * @param mixed  $value Option value.
 * @return bool
 */
function singo_tools_update_option( string $key, mixed $value ): bool {
	$options = get_option( 'singo_tools_options', array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}
	$options[ $key ] = $value;

	if ( class_exists( 'Singo_Tools_Debugger' ) ) {
		Singo_Tools_Debugger::log( "بروزرسانی کلید تنظیمات [{$key}] در singo_tools_options انجام شد.", 'UPDATE_OPTION' );
	}

	$updated = update_option( 'singo_tools_options', $options );
	singo_tools_get_option( '', '', true ); // Flush memory cache!

	return $updated;
}

/**
 * Legacy wrapper for sdm_get_option.
 */
if ( ! function_exists( 'sdm_get_option' ) ) {
	function sdm_get_option( string $key = '', mixed $default = false ): mixed {
		return singo_tools_get_option( $key, $default );
	}
}

/**
 * Legacy wrapper for sdm_update_option.
 */
if ( ! function_exists( 'sdm_update_option' ) ) {
	function sdm_update_option( string $key, mixed $value ): bool {
		return singo_tools_update_option( $key, $value );
	}
}

/**
 * Return the primary capability required for accessing Singo Tools admin menus.
 *
 * @return string
 */
function singo_tools_get_admin_capability(): string {
	return 'manage_woocommerce';
}

/**
 * Check whether the current user has permission to manage Singo Tools.
 * Allows both administrators (manage_options) and shop managers (manage_woocommerce / shop_manager role).
 *
 * @return bool
 */
function singo_tools_current_user_can_manage(): bool {
	if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) {
		return true;
	}

	$user = wp_get_current_user();
	if ( $user && $user->exists() && in_array( 'shop_manager', (array) $user->roles, true ) ) {
		return true;
	}

	return false;
}

