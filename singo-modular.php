<?php
declare(strict_types=1);

/*
Plugin Name: Singo Modular
Plugin URI: https://singo.studio
Description: A modern, high-performance modular WordPress plugin framework built on Optionino with Zero Disk Scanning architecture.
Version: 1.0.0
Requires at least: 6.7
Requires PHP: 8.0
Author: Singo Studio
Text Domain: singo-modular
Domain Path: /languages
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'SINGO_MODULAR_FILE', __FILE__ );
define( 'SINGO_MODULAR_VERSION', '1.0.0' );
define( 'SINGO_MODULAR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SINGO_MODULAR_URL', plugin_dir_url( __FILE__ ) );

// Backwards compatibility constants for module ecosystem
if ( ! defined( 'SINGO_TOOLS_FILE' ) ) {
	define( 'SINGO_TOOLS_FILE', SINGO_MODULAR_FILE );
}
if ( ! defined( 'SINGO_TOOLS_VERSION' ) ) {
	define( 'SINGO_TOOLS_VERSION', SINGO_MODULAR_VERSION );
}
if ( ! defined( 'SINGO_TOOLS_PATH' ) ) {
	define( 'SINGO_TOOLS_PATH', SINGO_MODULAR_PATH );
}
if ( ! defined( 'SINGO_TOOLS_URL' ) ) {
	define( 'SINGO_TOOLS_URL', SINGO_MODULAR_URL );
}
if ( ! defined( 'SDM_VERSION' ) ) {
	define( 'SDM_VERSION', SINGO_MODULAR_VERSION );
}
if ( ! defined( 'SDM_PATH' ) ) {
	define( 'SDM_PATH', SINGO_MODULAR_PATH );
}
if ( ! defined( 'SDM_URL' ) ) {
	define( 'SDM_URL', SINGO_MODULAR_URL );
}

// Prevent Optionino demo config
if ( ! defined( 'OPTNNO_DISABLE_CONFIG' ) ) {
	define( 'OPTNNO_DISABLE_CONFIG', true );
}

// Activation Hook: rebuild manifest strictly once upon plugin activation
register_activation_hook( SINGO_MODULAR_FILE, function (): void {
	if ( file_exists( SINGO_MODULAR_PATH . 'includes/Core/ManifestManager.php' ) ) {
		require_once SINGO_MODULAR_PATH . 'includes/Core/ManifestManager.php';
		\SingoTools\Core\ManifestManager::rebuild_manifest();
	}
	update_option( 'singo_modular_version', SINGO_MODULAR_VERSION );
} );

// Load Core Bootstrapper
if ( file_exists( SINGO_MODULAR_PATH . 'includes/class-singo-modular-core.php' ) ) {
	require_once SINGO_MODULAR_PATH . 'includes/class-singo-modular-core.php';

	function singo_modular_init(): void {
		Singo_Modular_Core::get_instance();
	}
	add_action( 'plugins_loaded', 'singo_modular_init', 0 );
}
