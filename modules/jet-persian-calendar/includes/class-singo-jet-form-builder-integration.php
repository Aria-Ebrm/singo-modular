<?php
declare(strict_types=1);

namespace SingoTools\Modules\JetPersianCalendar;

/**
 * Singo Tools - JetFormBuilder Integration for Persian Calendar
 *
 * @package SingoTools\Modules\JetPersianCalendar
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class JetFormBuilderIntegration {

	private string $module_url;
	private string $version;

	public function __construct( string $module_url, string $version ) {
		$this->module_url = $module_url;
		$this->version    = $version;

		$this->init_hooks();
	}

	private function init_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );

		add_action( 'wp_default_scripts', array( $this, 'add_dependencies' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dependencies' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_dependencies' ), 100 );
	}

	public function enqueue_assets(): void {
		if ( ! function_exists( 'jet_form_builder' ) && ! class_exists( '\Jet_Form_Builder\Plugin' ) ) {
			return;
		}

		JetCalendarHelpers::enqueue_core_assets( $this->module_url, $this->version );

		wp_enqueue_script(
			'singo-integrate-jet-form-builder',
			$this->module_url . 'assets/js/integrate-jet-form-builder.js',
			array( 'jquery', 'singo-jet-persian-calendar-core' ),
			$this->version,
			true
		);
	}

	public function add_dependencies(): void {
		JetCalendarHelpers::inject_dependency(
			array(
				'jet-form-builder-frontend-forms',
				'jfb-records',
				'jfb-records-single',
				'jfb-payments',
				'jfb-payment',
			),
			'singo-integrate-jet-form-builder'
		);
	}
}
