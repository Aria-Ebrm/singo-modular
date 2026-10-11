<?php
declare(strict_types=1);

namespace SingoTools\Modules\JetPersianCalendar;

/**
 * Singo Tools - Jet Persian Calendar Helper Utilities
 *
 * @package SingoTools\Modules\JetPersianCalendar
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class JetCalendarHelpers {

	/**
	 * Enqueue core calendar engine script and styling.
	 *
	 * @param string $plugin_url Base URL of the module.
	 * @param string $version    Plugin/Module version.
	 * @return void
	 */
	public static function enqueue_core_assets( string $plugin_url, string $version ): void {
		wp_enqueue_script(
			'singo-jet-persian-calendar-core',
			$plugin_url . 'assets/js/persian-calendar.js',
			array( 'jquery' ),
			$version,
			true
		);

		wp_enqueue_style(
			'singo-jet-calendar-gutenberg-styles',
			$plugin_url . 'assets/css/gutenberg-calendar.css',
			array(),
			$version
		);

		wp_enqueue_style(
			'singo-jet-calendar-main-styles',
			$plugin_url . 'assets/css/integrate-jet.css',
			array( 'singo-jet-calendar-gutenberg-styles' ),
			$version
		);
	}

	/**
	 * Append an integration script handle as a dependency to existing registered scripts.
	 *
	 * @param array<int, string> $handles    Registered script handles.
	 * @param string             $our_handle Our integration script handle.
	 * @return void
	 */
	public static function inject_dependency( array $handles, string $our_handle ): void {
		global $wp_scripts;
		if ( ! $wp_scripts ) {
			return;
		}

		foreach ( $handles as $handle ) {
			if ( isset( $wp_scripts->registered[ $handle ] ) ) {
				$deps = &$wp_scripts->registered[ $handle ]->deps;
				if ( ! in_array( $our_handle, $deps, true ) ) {
					$deps[] = $our_handle;
				}
				unset( $deps );
			}
		}
	}

	/**
	 * Check if current REST route matches any of the given fragments.
	 *
	 * @param array<int, string> $needles Lowercase route fragments.
	 * @return bool
	 */
	public static function rest_route_matches( array $needles ): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}

		$route = '';
		if ( isset( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		}

		if ( '' === $route && isset( $_SERVER['REQUEST_URI'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$route = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
		}

		if ( '' === $route ) {
			return false;
		}

		$route = strtolower( $route );
		foreach ( $needles as $needle ) {
			if ( '' !== $needle && str_contains( $route, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether the current request context should skip Jalali conversion.
	 *
	 * Skips machine contexts (REST, WP-CLI, XML-RPC) and general admin screens
	 * except Elementor preview/editor or AJAX.
	 *
	 * @return bool
	 */
	public static function should_skip_conversion(): bool {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return true;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			$is_elementor = ( isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) || isset( $_GET['elementor-preview'] );
			if ( ! $is_elementor ) {
				return true;
			}
		}

		return false;
	}
}
