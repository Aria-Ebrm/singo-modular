<?php
declare(strict_types=1);

namespace SingoTools\Modules\JetPersianCalendar;

/**
 * Singo Tools - Gutenberg Block Editor Jalali Calendar Integration
 *
 * @package SingoTools\Modules\JetPersianCalendar
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class GutenbergCalendarIntegration {

	private string $module_url;
	private string $version;

	public function __construct( string $module_url, string $version ) {
		$this->module_url = $module_url;
		$this->version    = $version;

		$this->init_hooks();
	}

	private function init_hooks(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ), 20 );
	}

	public function enqueue_assets(): void {
		JetCalendarHelpers::enqueue_core_assets( $this->module_url, $this->version );

		wp_enqueue_script(
			'singo-gutenberg-persian-calendar',
			$this->module_url . 'assets/js/gutenberg.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'singo-jet-persian-calendar-core' ),
			$this->version,
			true
		);
	}
}
