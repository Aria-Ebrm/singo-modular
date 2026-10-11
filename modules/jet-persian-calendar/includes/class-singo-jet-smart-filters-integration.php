<?php
declare(strict_types=1);

namespace SingoTools\Modules\JetPersianCalendar;

/**
 * Singo Tools - JetSmartFilters Integration for Persian Calendar
 *
 * @package SingoTools\Modules\JetPersianCalendar
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class JetSmartFiltersIntegration {

	private string $module_url;
	private string $version;

	public function __construct( string $module_url, string $version ) {
		$this->module_url = $module_url;
		$this->version    = $version;

		$this->init_hooks();
	}

	private function init_hooks(): void {
		// Frontend only enqueues
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );

		add_action( 'wp_default_scripts', array( $this, 'add_dependencies' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_dependencies' ), 100 );

		// Query request normalization (Jalali -> Gregorian)
		add_filter( 'jet-smart-filters/query/request', array( $this, 'normalize_query_request' ), 10, 2 );
	}

	public function enqueue_assets(): void {
		if ( ! class_exists( 'Jet_Smart_Filters' ) ) {
			return;
		}

		JetCalendarHelpers::enqueue_core_assets( $this->module_url, $this->version );

		wp_enqueue_script(
			'singo-integrate-jet-smart-filters',
			$this->module_url . 'assets/js/integrate-jet-smart-filters.js',
			array( 'jquery', 'singo-jet-persian-calendar-core', 'jquery-ui-datepicker' ),
			$this->version,
			true
		);
	}

	public function add_dependencies(): void {
		JetCalendarHelpers::inject_dependency(
			array(
				'jet-smart-filters',
			),
			'singo-integrate-jet-smart-filters'
		);
	}

	/**
	 * Convert Jalali dates in filter request parameters back to Gregorian
	 * before JetSmartFilters builds the SQL query.
	 *
	 * @param mixed $request
	 * @param mixed $query_manager
	 * @return mixed
	 */
	public function normalize_query_request( mixed $request, mixed $query_manager = null ): mixed {
		if ( empty( $request ) ) {
			return $request;
		}

		return $this->convert_jalali_to_gregorian( $request );
	}

	private function convert_jalali_to_gregorian( mixed $val ): mixed {
		if ( is_array( $val ) ) {
			foreach ( $val as $k => $v ) {
				$val[ $k ] = $this->convert_jalali_to_gregorian( $v );
			}
			return $val;
		}

		if ( ! is_string( $val ) ) {
			return $val;
		}

		// Handle date range: "1403.04.27-1403.05.03", "1403/04/27-1403/05/03", or "1403-04-27-1403-05-03"
		if ( preg_match( '/^(\d{4}[.\/\-]\d{1,2}[.\/\-]\d{1,2})-(\d{4}[.\/\-]\d{1,2}[.\/\-]\d{1,2})$/', trim( $val ), $matches ) ) {
			$from = $this->convert_single_jalali_to_gregorian( $matches[1] );
			$to   = $this->convert_single_jalali_to_gregorian( $matches[2] );
			return $from . '-' . $to;
		}

		return $this->convert_single_jalali_to_gregorian( $val );
	}

	private function convert_single_jalali_to_gregorian( string $date_str ): string {
		if ( preg_match( '/^(\d{4})([.\/\-])(\d{1,2})\2(\d{1,2})$/', trim( $date_str ), $matches ) ) {
			$jy        = (int) $matches[1];
			$separator = $matches[2];
			$jm        = (int) $matches[3];
			$jd        = (int) $matches[4];

			if ( $jy >= 1300 && $jy < 1700 && JetCalendarConverter::is_valid_jalali( $jy, $jm, $jd ) ) {
				$g = JetCalendarConverter::jalali_to_gregorian( $jy, $jm, $jd );
				return sprintf( '%04d%s%02d%s%02d', $g['y'], $separator, $g['m'], $separator, $g['d'] );
			}
		}
		return $date_str;
	}
}
