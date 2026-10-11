<?php
declare(strict_types=1);

namespace SingoTools\Modules\JetPersianCalendar;

/**
 * Singo Tools - JetBooking Integration for Persian Calendar
 *
 * @package SingoTools\Modules\JetPersianCalendar
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class JetBookingIntegration {

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
		if ( ! class_exists( 'JET_ABAF\\Plugin' ) && ! defined( 'JET_ABAF_VERSION' ) ) {
			return;
		}

		JetCalendarHelpers::enqueue_core_assets( $this->module_url, $this->version );

		wp_enqueue_style(
			'singo-jet-booking-calendar-front',
			$this->module_url . 'assets/css/persian-calendar-front.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'singo-integrate-jet-booking',
			$this->module_url . 'assets/js/integrate-jet-booking.js',
			array( 'jquery', 'singo-jet-persian-calendar-core' ),
			$this->version,
			true
		);
	}

	public function add_dependencies(): void {
		JetCalendarHelpers::inject_dependency(
			array(
				'jquery-date-range-picker',
				'jquery-date-range-picker-js',
				'jet-booking-init',
				'jet-abaf-booking-init',
				'jet-abaf-bookings-page',
				'jet-abaf-bookings',
				'jet-abaf-settings-page',
				'jet-abaf-schedule-manager',
				'jet-abaf-meta-extras',
				'jet-abaf-post-meta-price',
				'jet-abaf-post-meta-configuration',
				'jet-abaf-calendars-page',
			),
			'singo-integrate-jet-booking'
		);
	}
}
