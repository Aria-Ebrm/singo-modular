<?php
declare(strict_types=1);

/**
 * Shamsi Date Module Core Orchestrator (Singo_Shamsi_Core)
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Shamsi_Core {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		$this->load_hooks();
	}

	private function includes() {
		require_once __DIR__ . '/class-singo-jdatetime.php';
		require_once __DIR__ . '/class-singo-shamsi-converter.php';
	}

	private function load_hooks() {
		// Master switch check for Shamsi Date
		if ( ! singo_tools_get_option( 'master_switch', true ) ) {
			return;
		}

		// Multilingual / Locale Guard (Only proceed if current locale starts with 'fa')
		$current_locale = strtolower( trim( get_locale() ) );
		if ( strpos( $current_locale, 'fa' ) !== 0 ) {
			return;
		}

		// REST API / AJAX Guard
		if ( wp_is_json_request() || wp_doing_ajax() ) {
			if ( ! singo_tools_get_option( 'enable_rest_ajax', false ) ) {
				return;
			}
		}

		// Load Frontend Module
		if ( ! is_admin() && singo_tools_get_option( 'enable_frontend', true ) ) {
			require_once __DIR__ . '/class-singo-shamsi-frontend.php';
			new Singo_Shamsi_Frontend();
		}

		// Load Admin Module
		if ( is_admin() && ! wp_doing_ajax() && singo_tools_get_option( 'enable_admin', true ) ) {
			require_once __DIR__ . '/class-singo-shamsi-admin.php';
			new Singo_Shamsi_Admin();
		}

		// Load WooCommerce Module
		if ( class_exists( 'WooCommerce' ) && singo_tools_get_option( 'enable_woocommerce', true ) ) {
			require_once __DIR__ . '/class-singo-shamsi-woocommerce.php';
			new Singo_Shamsi_WooCommerce();
		}

		// Load Elementor Module
		if ( did_action( 'elementor/loaded' ) && singo_tools_get_option( 'enable_elementor', true ) ) {
			require_once __DIR__ . '/class-singo-shamsi-elementor.php';
			new Singo_Shamsi_Elementor();
		}
	}
}
