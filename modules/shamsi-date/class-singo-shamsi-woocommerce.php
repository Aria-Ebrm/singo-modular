<?php
declare(strict_types=1);

/**
 * WooCommerce Integration for Shamsi Date (Singo_Shamsi_WooCommerce)
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Shamsi_WooCommerce {

	public function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		// WooCommerce generic date formatting
		add_filter( 'wc_format_datetime', array( $this, 'filter_wc_format_datetime' ), 20, 2 );

		// WooCommerce order created date
		add_filter( 'woocommerce_admin_order_date_format', array( $this, 'filter_admin_order_date_format' ), 20, 1 );

		// Only convert admin tables if setting is enabled
		if ( is_admin() && ! singo_tools_get_option( 'wc_admin_orders', false ) ) {
			// If admin is disabled, remove wc_format_datetime in admin
			remove_filter( 'wc_format_datetime', array( $this, 'filter_wc_format_datetime' ), 20 );
		}
	}

	public function filter_wc_format_datetime( $formatted_date, $datetime ) {
		if ( ! $datetime instanceof WC_DateTime ) {
			return $formatted_date;
		}

		$timestamp = $datetime->getTimestamp();
		
		// Get standard WC format
		$format = wc_date_format() . ' ' . wc_time_format();

		// Convert to Jalali
		return Singo_Shamsi_Converter::format_date( $timestamp, $format );
	}

	public function filter_admin_order_date_format( $format ) {
		return singo_tools_get_option( 'default_format', 'Y/m/d' );
	}
}

if ( ! class_exists( 'SDM_WooCommerce' ) ) {
	class_alias( 'Singo_Shamsi_WooCommerce', 'SDM_WooCommerce' );
}
