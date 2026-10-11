<?php
declare(strict_types=1);

namespace SingoTools\Modules\JetPersianCalendar;

/**
 * Singo Tools - JetAppointments Integration for Persian Calendar
 *
 * @package SingoTools\Modules\JetPersianCalendar
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class JetAppointmentsIntegration {

	private string $module_url;
	private string $version;

	public function __construct( string $module_url, string $version ) {
		$this->module_url = $module_url;
		$this->version    = $version;

		$this->init_hooks();
	}

	private function init_hooks(): void {
		// 1. Force 24-hour time format in JetAppointments admin REST endpoints
		add_filter( 'pre_option_time_format', array( $this, 'filter_rest_time_format' ), 10, 2 );

		// 2. Format dates in JetAppointments REST responses
		add_filter( 'rest_post_dispatch', array( $this, 'format_rest_dates' ), 20, 3 );

		// 3. Asset Enqueues
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );

		// 4. Dependency Injections
		add_action( 'wp_default_scripts', array( $this, 'add_dependencies' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dependencies' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_dependencies' ), 100 );
	}

	public static function get_rest_needles(): array {
		return array(
			'jet-apb',
			'jet_apb',
			'jet-appointments-booking',
			'appointment-date-slots',
			'appointment-refresh-date',
			'appointment-service-providers',
			'appointment-provider-services',
			'appointments-list',
			'delete-appointment',
			'update-appointment',
			'appointment-add-appointment',
			'update-appointment-workflows',
			'appointment-meta',
			'get-appointment',
			'external-meta',
		);
	}

	public function filter_rest_time_format( mixed $pre, string $option ): mixed {
		if ( JetCalendarHelpers::rest_route_matches( self::get_rest_needles() ) ) {
			return 'H:i';
		}
		return $pre;
	}

	public function format_rest_dates( mixed $response, mixed $server, mixed $request ): mixed {
		if ( ! JetCalendarHelpers::rest_route_matches( self::get_rest_needles() ) ) {
			return $response;
		}

		if ( ! is_a( $response, '\WP_REST_Response' ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( empty( $data ) || ! is_array( $data ) ) {
			return $response;
		}

		$format_item = static function ( &$item ) {
			if ( ! is_array( $item ) ) {
				return;
			}
			if ( ! empty( $item['date_timestamp'] ) ) {
				$ts = (int) $item['date_timestamp'];
				if ( $ts > 0 ) {
					$gy = (int) gmdate( 'Y', $ts );
					$gm = (int) gmdate( 'm', $ts );
					$gd = (int) gmdate( 'd', $ts );
					$j  = JetCalendarConverter::gregorian_to_jalali( $gy, $gm, $gd );
					$item['date'] = sprintf( '%04d/%02d/%02d', $j['y'], $j['m'], $j['d'] );
				}
			} elseif ( ! empty( $item['date'] ) && is_numeric( $item['date'] ) ) {
				$ts = (int) $item['date'];
				if ( $ts > 100000 ) {
					$item['date_timestamp'] = $ts;
					$gy = (int) gmdate( 'Y', $ts );
					$gm = (int) gmdate( 'm', $ts );
					$gd = (int) gmdate( 'd', $ts );
					$j  = JetCalendarConverter::gregorian_to_jalali( $gy, $gm, $gd );
					$item['date'] = sprintf( '%04d/%02d/%02d', $j['y'], $j['m'], $j['d'] );
				}
			}
		};

		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as &$it ) {
				$format_item( $it );
			}
			unset( $it );
		} elseif ( isset( $data['item'] ) && is_array( $data['item'] ) ) {
			$format_item( $data['item'] );
		} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$is_assoc = ( array_keys( $data['data'] ) !== range( 0, count( $data['data'] ) - 1 ) );
			if ( ! $is_assoc ) {
				foreach ( $data['data'] as &$it ) {
					$format_item( $it );
				}
				unset( $it );
			} else {
				$format_item( $data['data'] );
			}
		}

		$response->set_data( $data );
		return $response;
	}

	public function enqueue_assets(): void {
		if ( ! class_exists( 'JET_APB\\Plugin' ) && ! defined( 'JET_APB_VERSION' ) ) {
			return;
		}

		JetCalendarHelpers::enqueue_core_assets( $this->module_url, $this->version );

		wp_enqueue_style(
			'singo-jet-appointments-calendar-front',
			$this->module_url . 'assets/css/persian-calendar-front.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'singo-integrate-jet-appointments',
			$this->module_url . 'assets/js/integrate-jet-appointments.js',
			array( 'jquery', 'singo-jet-persian-calendar-core' ),
			$this->version,
			true
		);
	}

	public function add_dependencies(): void {
		JetCalendarHelpers::inject_dependency(
			array(
				'vanilla-calendar',
				'flatpickr',
				'jet-ab-front-init',
				'jet-ab-choices',
				'jet-apb-appointments',
				'jet-apb-appointments-range',
				'vue-flatpickr',
				'vuejs-datepicker',
				'jet-apb-set-up',
				'jet-apb-general-settings',
				'jet-apb-working-hours-settings',
				'jet-apb-labels-settings',
				'jet-apb-tools-settings',
				'jet-apb-advanced-settings',
				'jet-apb-layout-settings',
				'jet-apb-integrations',
				'jet-apb-workflows',
				'jet-apb-admin-settings',
				'jet_apb_post_meta_box',
				'jet-app-booking-form-builder-fields',
				'jet-app-booking-form-builder-actions',
				'jet-apb-builder-actions',
				'jet-apb-builder-v2',
				'jet-apb-builder-blocks',
			),
			'singo-integrate-jet-appointments'
		);
	}
}
