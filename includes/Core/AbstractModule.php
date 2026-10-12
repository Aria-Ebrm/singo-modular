<?php
declare(strict_types=1);

namespace SingoTools\Core;

/**
 * Singo Tools - Abstract Base Module (PHP 8+ Standardized)
 *
 * All self-contained modules in Singo Tools extend this class.
 *
 * @package SingoTools\Core
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

abstract class AbstractModule {

	/**
	 * Unique module identifier (slug).
	 *
	 * @return string
	 */
	abstract public static function get_id(): string;

	/**
	 * Human-readable module name.
	 *
	 * @return string
	 */
	abstract public static function get_name(): string;

	/**
	 * Dashicon or icon string for Optionino tab/UI.
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return 'dashicons-admin-generic';
	}

	/**
	 * Determine whether module is active based on user settings.
	 *
	 * @return bool
	 */
	abstract public static function is_enabled(): bool;

	/**
	 * Order / Priority for Optionino tab sorting.
	 *
	 * @return int
	 */
	public static function get_order(): int {
		return 100;
	}

	/**
	 * Context-aware validity check (e.g. check if WooCommerce is active or if admin-only).
	 *
	 * @return bool
	 */
	public static function is_context_valid(): bool {
		return true;
	}

	/**
	 * Short description of the module.
	 * Used for field descriptions and tab subtitles.
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return '';
	}

	/**
	 * Placement of the module settings in Optionino.
	 *
	 * Supported values:
	 * - 'dedicated': Creates a dedicated standalone tab in Optionino navigation.
	 * - 'other_tools': Merges settings into the shared "سایر امکانات" tab.
	 * - 'none': Has no admin settings UI.
	 *
	 * @return string
	 */
	public static function get_placement(): string {
		return 'dedicated';
	}

	/**
	 * Whether this module belongs to the shared "Other Tools" (سایر امکانات) tab.
	 *
	 * @return bool
	 */
	public static function is_other_tool(): bool {
		return 'other_tools' === static::get_placement();
	}

	/**
	 * Return Optionino native tab configuration array.
	 *
	 * If null or if placement is 'other_tools', module does not register a dedicated tab.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_optionino_tab(): ?array {
		return null;
	}

	/**
	 * Return optional Optionino fields array to contribute to a shared tab (e.g. 'other_tools').
	 *
	 * If module placement is 'other_tools' and this method is not overridden,
	 * a default switcher field is automatically generated.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_optionino_fields(): array {
		if ( static::is_other_tool() ) {
			$switch_id = 'enable_' . str_replace( '-', '_', static::get_id() );
			return array(
				array(
					'id'      => $switch_id,
					'type'    => 'switcher',
					'title'   => static::get_name(),
					'desc'    => static::get_description(),
					'default' => false,
				),
			);
		}

		return array();
	}

	/**
	 * Get absolute filesystem path to a file inside this module's directory.
	 *
	 * @param string $subpath Optional relative subpath.
	 * @return string
	 */
	public function get_path( string $subpath = '' ): string {
		$reflection = new \ReflectionClass( static::class );
		$dir        = dirname( (string) $reflection->getFileName() );

		if ( '' === $subpath ) {
			return $dir;
		}

		return $dir . '/' . ltrim( $subpath, '/\\' );
	}

	/**
	 * Get absolute HTTP URL to a file inside this module's directory.
	 *
	 * @param string $subpath Optional relative subpath.
	 * @return string
	 */
	public function get_url( string $subpath = '' ): string {
		$path            = $this->get_path( $subpath );
		$normalized_path = wp_normalize_path( $path );
		$root_path       = defined( 'SINGO_MODULAR_PATH' ) ? SINGO_MODULAR_PATH : ( defined( 'SINGO_TOOLS_PATH' ) ? SINGO_TOOLS_PATH : '' );
		$root_url        = defined( 'SINGO_MODULAR_URL' ) ? SINGO_MODULAR_URL : ( defined( 'SINGO_TOOLS_URL' ) ? SINGO_TOOLS_URL : '' );
		$normalized_root = wp_normalize_path( $root_path );

		return str_replace( $normalized_root, $root_url, $normalized_path );
	}

	/**
	 * Initialize module hooks and logic.
	 * Called during WordPress 'init' hook when the module is enabled and context is valid.
	 */
	public function init(): void {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		}
	}

	/**
	 * Enqueue WP Admin scripts and styles for this module.
	 */
	public function enqueue_admin_assets(): void {}

	/**
	 * Enqueue Frontend scripts and styles for this module.
	 */
	public function enqueue_frontend_assets(): void {}
}
