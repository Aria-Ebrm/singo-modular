<?php
declare(strict_types=1);

namespace SingoTools\Core;

/**
 * Singo Tools - Delta Manager (PHP 8+ Standardized)
 *
 * Implements server-side Delta Saving & Partial Update logic.
 * Detects modified keys, eliminates redundant database writes,
 * and ensures safe option merging without touching Optionino core.
 *
 * @package SingoTools\Core
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class DeltaManager {

	/**
	 * Singleton instance.
	 *
	 * @var DeltaManager|null
	 */
	private static ?DeltaManager $instance = null;

	/**
	 * List of keys changed during the current save request.
	 *
	 * @var array<string>
	 */
	private array $last_changed_keys = array();

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
		// Attach to pre_update_option filter for singo_modular_options and singo_tools_options
		add_filter( 'pre_update_option_singo_modular_options', array( $this, 'filter_option_save' ), 20, 2 );
		add_filter( 'pre_update_option_singo_tools_options', array( $this, 'filter_option_save' ), 20, 2 );
	}

	/**
	 * Compute differences between new and old options arrays.
	 *
	 * @param array<string, mixed> $new_data
	 * @param array<string, mixed> $old_data
	 * @return array<string, array{old: mixed, new: mixed}>
	 */
	public static function compute_delta( array $new_data, array $old_data ): array {
		$delta = array();

		// Check added or modified keys
		foreach ( $new_data as $key => $val ) {
			if ( ! array_key_exists( $key, $old_data ) ) {
				$delta[ $key ] = array(
					'old' => null,
					'new' => $val,
				);
			} elseif ( is_array( $val ) && is_array( $old_data[ $key ] ) ) {
				if ( wp_json_encode( $old_data[ $key ] ) !== wp_json_encode( $val ) ) {
					$delta[ $key ] = array(
						'old' => $old_data[ $key ],
						'new' => $val,
					);
				}
			} elseif ( $old_data[ $key ] !== $val ) {
				$delta[ $key ] = array(
					'old' => $old_data[ $key ],
					'new' => $val,
				);
			}
		}

		return $delta;
	}

	/**
	 * Filter option values before writing to the database.
	 *
	 * If no values actually changed, returns the exact $old_value so WordPress
	 * aborts the redundant DB UPDATE query and avoids triggering update_option action.
	 *
	 * @param mixed $new_value
	 * @param mixed $old_value
	 * @return mixed
	 */
	public function filter_option_save( mixed $new_value, mixed $old_value ): mixed {
		if ( ! is_array( $new_value ) || ! is_array( $old_value ) ) {
			return $new_value;
		}

		$delta                    = self::compute_delta( $new_value, $old_value );
		$this->last_changed_keys = array_keys( $delta );

		if ( empty( $delta ) ) {
			if ( class_exists( '\Singo_Tools_Debugger' ) ) {
				\Singo_Tools_Debugger::log( 'ذخیره‌سازی تفاضلی (Delta): هیچ فیلدی تغییر نکرده است. کوئری دیتابیس لغو شد.', 'DELTA_SAVE_NOOP' );
			}
			// Returning exact $old_value instructs WordPress update_option() to return false and skip DB query
			return $old_value;
		}

		if ( class_exists( '\Singo_Tools_Debugger' ) ) {
			$keys_list = implode( ', ', $this->last_changed_keys );
			\Singo_Tools_Debugger::log( "ذخیره‌سازی تفاضلی (Delta): فیلدهای تغییریافته [{$keys_list}] با موفقیت ادغام شدند.", 'DELTA_SAVE_SUCCESS' );
		}

		return $new_value;
	}

	/**
	 * Get the keys that changed in the last processed save operation.
	 *
	 * @return array<string>
	 */
	public function get_last_changed_keys(): array {
		return $this->last_changed_keys;
	}

	/**
	 * Check whether any options were modified in the current request.
	 *
	 * @return bool
	 */
	public function has_changes(): bool {
		return ! empty( $this->last_changed_keys );
	}
}
