<?php
declare(strict_types=1);

namespace SingoTools\Core;

/**
 * Singo Tools - Manifest Manager (PHP 8+ Standardized)
 *
 * Handles module auto-discovery and zero-disk-scanning caching in wp_options.
 *
 * @package SingoTools\Core
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

class ManifestManager {

	/**
	 * Option key for caching the module manifest.
	 */
	public const OPTION_KEY = 'singo_modular_modules_manifest';

	/**
	 * In-memory RAM cache for manifest.
	 *
	 * @var array<string, array<string, string>>|null
	 */
	private static ?array $cached_manifest = null;

	/**
	 * Get the module manifest with zero disk scanning at runtime.
	 *
	 * Zero disk scanning is strictly preserved: it loads directly from wp_options
	 * and memory cache. Rebuilding occurs strictly on plugin upgrade/activation
	 * or explicit settings save.
	 *
	 * @param bool $force_refresh Whether to force a disk re-scan and update the cache.
	 * @return array<string, array<string, string>> Array of discovered modules metadata.
	 */
	public static function get_manifest( bool $force_refresh = false ): array {
		if ( null !== self::$cached_manifest && ! $force_refresh ) {
			return self::$cached_manifest;
		}

		if ( ! $force_refresh ) {
			$saved = get_option( self::OPTION_KEY, null );
			if ( is_array( $saved ) && ! empty( $saved ) ) {
				self::$cached_manifest = $saved;
				return self::$cached_manifest;
			}
		}

		self::$cached_manifest = self::rebuild_manifest();
		return self::$cached_manifest;
	}

	/**
	 * Scan the modules directory and rebuild the cached manifest in wp_options.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function rebuild_manifest(): array {
		$manifest    = array();
		$base_path   = defined( 'SINGO_MODULAR_PATH' ) ? SINGO_MODULAR_PATH : ( defined( 'SINGO_TOOLS_PATH' ) ? SINGO_TOOLS_PATH : '' );
		$modules_dir = $base_path . 'modules';

		if ( ! is_dir( $modules_dir ) ) {
			return $manifest;
		}

		$entries = scandir( $modules_dir );
		if ( false === $entries ) {
			return $manifest;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$module_path = $modules_dir . '/' . $entry;
			if ( ! is_dir( $module_path ) ) {
				continue;
			}

			$slug = sanitize_key( $entry );

			// Look for standard entry files
			$candidate_files = array(
				$module_path . '/' . self::slug_to_studly( $slug ) . 'Module.php',
				$module_path . '/class-singo-' . $slug . '.php',
				$module_path . '/class-' . $slug . '-module.php',
				$module_path . '/module.php',
			);

			foreach ( $candidate_files as $file ) {
				if ( ! file_exists( $file ) ) {
					continue;
				}

				// Inspect file tokens for class name extending AbstractModule
				$class_info = self::detect_module_class( $file );
				if ( null !== $class_info ) {
					$manifest[ $slug ] = array(
						'slug'  => $slug,
						'class' => $class_info,
						'file'  => wp_normalize_path( $file ),
					);
					break;
				}
			}
		}

		update_option( self::OPTION_KEY, $manifest );
		self::$cached_manifest = $manifest;

		return $manifest;
	}

	/**
	 * Convert kebab-case slug to StudlyCaps (e.g. 'currency-converter' -> 'CurrencyConverter').
	 *
	 * @param string $slug
	 * @return string
	 */
	private static function slug_to_studly( string $slug ): string {
		$words = explode( '-', $slug );
		$studly = array_map( 'ucfirst', $words );
		return implode( '', $studly );
	}

	/**
	 * Detect if a PHP file contains a class extending AbstractModule.
	 *
	 * @param string $file Absolute path to PHP file.
	 * @return string|null Fully-qualified class name if found, null otherwise.
	 */
	private static function detect_module_class( string $file ): ?string {
		$content = file_get_contents( $file );
		if ( false === $content ) {
			return null;
		}

		if ( ! str_contains( $content, 'extends AbstractModule' ) && ! str_contains( $content, 'extends \SingoTools\Core\AbstractModule' ) ) {
			return null;
		}

		$tokens    = token_get_all( $content );
		$namespace = '';
		$class     = '';
		$count     = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( is_array( $token ) && T_NAMESPACE === $token[0] ) {
				$ns_parts = array();
				for ( $j = $i + 1; $j < $count; $j++ ) {
					if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_STRING, T_NAME_QUALIFIED ), true ) ) {
						$ns_parts[] = $tokens[ $j ][1];
					} elseif ( ';' === $tokens[ $j ] || '{' === $tokens[ $j ] ) {
						break;
					}
				}
				$namespace = implode( '\\', $ns_parts );
			}

			if ( is_array( $token ) && T_CLASS === $token[0] ) {
				// Find next string token which is the class name
				for ( $j = $i + 1; $j < $count; $j++ ) {
					if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
						$class = $tokens[ $j ][1];
						break;
					}
				}
				if ( ! empty( $class ) ) {
					break;
				}
			}
		}

		if ( empty( $class ) ) {
			return null;
		}

		return ! empty( $namespace ) ? $namespace . '\\' . $class : $class;
	}
}
