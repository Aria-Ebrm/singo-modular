<?php
declare(strict_types=1);

/**
 * Core Plugin Orchestrator for Singo Modular (Singo_Modular_Core)
 * PHP 8+ Standardized
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Modular_Core {

	/**
	 * Single instance of the class.
	 *
	 * @var Singo_Modular_Core|null
	 */
	private static ?Singo_Modular_Core $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required core files.
	 */
	private function includes(): void {
		require_once SINGO_MODULAR_PATH . 'includes/helpers.php';

		// Load Modular Infrastructure
		require_once SINGO_MODULAR_PATH . 'includes/Core/AbstractModule.php';
		require_once SINGO_MODULAR_PATH . 'includes/Core/ManifestManager.php';
		require_once SINGO_MODULAR_PATH . 'includes/Core/ModuleManager.php';
		require_once SINGO_MODULAR_PATH . 'includes/Core/DeltaManager.php';
		\SingoTools\Core\DeltaManager::get_instance();

		// Load Optionino Framework and Settings strictly in Admin / AJAX
		if ( is_admin() || wp_doing_ajax() ) {
			if ( ! class_exists( 'OPTNNO' ) && file_exists( SINGO_MODULAR_PATH . 'optionino/optionino-framework.php' ) ) {
				require_once SINGO_MODULAR_PATH . 'optionino/optionino-framework.php';
			}
			require_once SINGO_MODULAR_PATH . 'includes/class-singo-modular-optionino.php';
			Singo_Modular_Optionino::init();
		}
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'load_modules' ) );
		add_action( 'update_option_singo_modular_options', array( $this, 'purge_cache_on_save' ), 10, 2 );

		// Rebuild manifest strictly ONCE when plugin is upgraded/replaced in WP Admin
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrade_complete' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'check_version_upgrade' ) );
	}

	/**
	 * Run manifest rebuild when WordPress finishes an upgrader action.
	 *
	 * @param mixed                $upgrader
	 * @param array<string, mixed> $hook_extra
	 */
	public function on_upgrade_complete( mixed $upgrader, array $hook_extra ): void {
		if ( ! isset( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}

		$plugin_slug = defined( 'SINGO_MODULAR_FILE' ) ? plugin_basename( SINGO_MODULAR_FILE ) : 'singo-modular-plugin/singo-modular.php';

		$is_modular_upgrade = false;
		if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$is_modular_upgrade = in_array( $plugin_slug, $hook_extra['plugins'], true );
		} elseif ( isset( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
			$is_modular_upgrade = ( $hook_extra['plugin'] === $plugin_slug );
		}

		if ( $is_modular_upgrade || empty( $hook_extra['plugins'] ) ) {
			\SingoTools\Core\ManifestManager::rebuild_manifest();
			update_option( 'singo_modular_version', SINGO_MODULAR_VERSION );
		}
	}

	/**
	 * Run manifest rebuild once if plugin version in DB differs from code version.
	 */
	public function check_version_upgrade(): void {
		$db_version = (string) get_option( 'singo_modular_version', '' );
		if ( $db_version !== SINGO_MODULAR_VERSION ) {
			\SingoTools\Core\ManifestManager::rebuild_manifest();
			update_option( 'singo_modular_version', SINGO_MODULAR_VERSION );
		}
	}

	/**
	 * Load conditional modules via ModuleManager.
	 */
	public function load_modules(): void {
		\SingoTools\Core\ModuleManager::get_instance()->init_active_modules();
	}

	/**
	 * Purge popular caching plugins when settings are updated.
	 */
	public function purge_cache_on_save( mixed $old_value, mixed $value ): void {
		if ( $old_value === $value ) {
			return;
		}

		// 1. LiteSpeed Cache
		if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
			\LiteSpeed_Cache_API::purge_all();
		} elseif ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		// 2. WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// 3. W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// 4. WP Super Cache
		if ( function_exists( 'wp_cache_clean_cache' ) ) {
			global $file_prefix;
			wp_cache_clean_cache( $file_prefix, true );
		}

		// 5. Autoptimize
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}

		// 6. Native WordPress Object Cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
}

// Backwards compatibility alias
if ( ! class_exists( 'Singo_Tools_Core' ) ) {
	class_alias( 'Singo_Modular_Core', 'Singo_Tools_Core' );
}
