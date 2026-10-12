<?php
declare(strict_types=1);

namespace SingoTools\Core;

/**
 * Singo Tools - Module Manager (PHP 8+ Standardized)
 *
 * Orchestrates module lifecycle, loading, and Optionino registration.
 *
 * @package SingoTools\Core
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class ModuleManager {

	/**
	 * Singleton instance.
	 *
	 * @var ModuleManager|null
	 */
	private static ?ModuleManager $instance = null;

	/**
	 * Map of instantiated modules: [ slug => AbstractModule instance ].
	 *
	 * @var array<string, AbstractModule>
	 */
	private array $modules = array();

	/**
	 * Whether modules have already been loaded into memory.
	 */
	private bool $is_loaded = false;

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
	 * Private constructor.
	 */
	private function __construct() {
		$this->load_modules();
	}

	/**
	 * Load and instantiate modules from cached manifest.
	 *
	 * @param bool $force_reload Whether to force reload modules from manifest.
	 */
	public function load_modules( bool $force_reload = false ): void {
		if ( $this->is_loaded && ! $force_reload ) {
			return;
		}

		if ( $force_reload ) {
			$this->modules = array();
		}

		$manifest = ManifestManager::get_manifest( $force_reload );

		foreach ( $manifest as $slug => $meta ) {
			$file  = $meta['file'] ?? '';
			$class = $meta['class'] ?? '';

			if ( empty( $file ) || empty( $class ) || ! file_exists( $file ) ) {
				continue;
			}

			require_once $file;

			if ( class_exists( $class ) && is_subclass_of( $class, AbstractModule::class ) ) {
				try {
					$module = new $class();
					$this->modules[ $slug ] = $module;
				} catch ( \Throwable $e ) {
					if ( class_exists( '\Singo_Tools_Debugger' ) ) {
						\Singo_Tools_Debugger::log( "خطا در بارگذاری ماژول [{$slug}]: " . $e->getMessage(), 'MODULE_LOAD_ERROR' );
					}
				}
			}
		}

		$this->is_loaded = true;
	}

	/**
	 * Force reload all modules from fresh manifest.
	 */
	public function reload_modules(): void {
		$this->is_loaded = false;
		$this->load_modules( true );
	}

	/**
	 * Initialize active modules whose context conditions are met.
	 */
	public function init_active_modules(): void {
		foreach ( $this->modules as $slug => $module ) {
			$class = get_class( $module );
			if ( $class::is_enabled() && $class::is_context_valid() ) {
				try {
					$module->init();
				} catch ( \Throwable $e ) {
					if ( class_exists( '\Singo_Tools_Debugger' ) ) {
						\Singo_Tools_Debugger::log( "خطا در اجرای init ماژول [{$slug}]: " . $e->getMessage(), 'MODULE_INIT_ERROR' );
					}
				}
			}
		}
	}

	/**
	 * Get all discovered and instantiated module objects.
	 *
	 * @return array<string, AbstractModule>
	 */
	public function get_all_modules(): array {
		if ( ! $this->is_loaded ) {
			$this->load_modules();
		}
		return $this->modules;
	}

	/**
	 * Get a specific module instance by slug.
	 *
	 * @param string $slug
	 * @return AbstractModule|null
	 */
	public function get_module( string $slug ): ?AbstractModule {
		return $this->modules[ $slug ] ?? null;
	}
}
