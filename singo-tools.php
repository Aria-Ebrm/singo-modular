<?php
declare(strict_types=1);

/*
Plugin Name: Singo Tools (جعبه ابزار سینگو)
Plugin URI: https://singo.studio
Description: جعبه ابزار جامع و یکپارچه برای مدیریت بهینه فروشگاه ووکامرس.
 * Version: 4.5.9
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * Author: Singo Studio
 * Author URI: https://singo.studio
 * Text Domain: singo-tools
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'SINGO_TOOLS_FILE', __FILE__ );
define( 'SINGO_TOOLS_VERSION', '4.5.9' );
define( 'SINGO_TOOLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SINGO_TOOLS_URL', plugin_dir_url( __FILE__ ) );

// Legacy constants for backwards compatibility
if ( ! defined( 'SDM_VERSION' ) ) {
	define( 'SDM_VERSION', SINGO_TOOLS_VERSION );
}
if ( ! defined( 'SDM_PATH' ) ) {
	define( 'SDM_PATH', SINGO_TOOLS_PATH );
}
if ( ! defined( 'SDM_URL' ) ) {
	define( 'SDM_URL', SINGO_TOOLS_URL );
}

// Prevent Optionino demo config
if ( ! defined( 'OPTNNO_DISABLE_CONFIG' ) ) {
	define( 'OPTNNO_DISABLE_CONFIG', true );
}

// Activation Hook: rebuild manifest strictly once upon plugin activation
register_activation_hook( SINGO_TOOLS_FILE, function (): void {
	if ( file_exists( SINGO_TOOLS_PATH . 'includes/Core/ManifestManager.php' ) ) {
		require_once SINGO_TOOLS_PATH . 'includes/Core/ManifestManager.php';
		\SingoTools\Core\ManifestManager::rebuild_manifest();
	}
	update_option( 'singo_tools_version', SINGO_TOOLS_VERSION );
} );

// Load the Core Bootstrapper
if ( file_exists( SINGO_TOOLS_PATH . 'includes/class-singo-tools-core.php' ) ) {
	require_once SINGO_TOOLS_PATH . 'includes/class-singo-tools-core.php';

	// Initialize Core
	function singo_tools_init() {
		Singo_Tools_Core::get_instance();
	}
	add_action( 'plugins_loaded', 'singo_tools_init', 0 );
}
