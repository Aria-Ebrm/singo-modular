<?php
declare(strict_types=1);

/**
 * Admin Area Hooks for Shamsi Date (Singo_Shamsi_Admin)
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Shamsi_Admin {

	public function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		add_filter( 'wp_date', array( $this, 'filter_wp_date' ), 20, 4 );
		add_filter( 'get_the_date', array( $this, 'filter_get_the_date' ), 20, 3 );
		add_filter( 'get_the_time', array( $this, 'filter_get_the_time' ), 20, 3 );
		add_filter( 'get_comment_date', array( $this, 'filter_comment_date' ), 20, 3 );
		add_filter( 'date_i18n', array( $this, 'filter_date_i18n' ), 20, 4 );
		add_action( 'admin_head', array( $this, 'inject_menu_icon_styles' ) );
	}

	public function filter_wp_date( $date, $format, $timestamp, $timezone ) {
		if ( empty( $timestamp ) ) {
			$timestamp = time();
		}
		return Singo_Shamsi_Converter::format_date( $timestamp, $format, $timezone ? $timezone->getName() : '' );
	}

	public function filter_get_the_date( $the_date, $format, $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_date;
		}
		$timestamp = get_post_time( 'U', true, $post );
		if ( ! $timestamp ) {
			return $the_date;
		}
		if ( empty( $format ) ) {
			$format = get_option( 'date_format', 'Y/m/d' );
		}
		return Singo_Shamsi_Converter::format_date( $timestamp, $format );
	}

	public function filter_get_the_time( $the_time, $format, $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_time;
		}
		$timestamp = get_post_time( 'U', true, $post );
		if ( ! $timestamp ) {
			return $the_time;
		}
		if ( empty( $format ) ) {
			$format = get_option( 'time_format', 'g:i a' );
		}
		return Singo_Shamsi_Converter::format_date( $timestamp, $format );
	}

	public function filter_comment_date( $date, $format, $comment ) {
		$comment_obj = get_comment( $comment );
		if ( ! $comment_obj ) {
			return $date;
		}
		$timestamp = strtotime( $comment_obj->comment_date_gmt . ' +0000' );
		if ( ! $timestamp ) {
			$timestamp = strtotime( $comment_obj->comment_date );
		}
		return Singo_Shamsi_Converter::format_date( $timestamp, $format );
	}

	public function filter_date_i18n( $j, $req_format, $i, $gmt ) {
		$timestamp = $i ? $i : time();
		return Singo_Shamsi_Converter::format_date( $timestamp, $req_format );
	}

	/**
	 * Inject custom CSS to fix dashboard menu icon sizing.
	 */
	public function inject_menu_icon_styles() {
		echo '<style>
			#adminmenu .toplevel_page_singo-tools .wp-menu-image,
			#adminmenu .toplevel_page_shamsi-date-master .wp-menu-image {
				display: flex !important;
				justify-content: center !important;
				align-items: center !important;
				background-size: 20px !important;
				background-position: center !important;
			}
			#adminmenu .toplevel_page_singo-tools .wp-menu-image img,
			#adminmenu .toplevel_page_shamsi-date-master .wp-menu-image img {
				max-width: 20px !important;
				max-height: 20px !important;
				width: 20px !important;
				height: auto !important;
				padding: 0 !important;
				opacity: 0.6;
			}
			#adminmenu .toplevel_page_singo-tools:hover .wp-menu-image img,
			#adminmenu .toplevel_page_singo-tools.wp-has-current-submenu .wp-menu-image img,
			#adminmenu .toplevel_page_shamsi-date-master:hover .wp-menu-image img,
			#adminmenu .toplevel_page_shamsi-date-master.wp-has-current-submenu .wp-menu-image img {
				opacity: 1;
			}
		</style>';
	}
}

if ( ! class_exists( 'SDM_Admin' ) ) {
	class_alias( 'Singo_Shamsi_Admin', 'SDM_Admin' );
}
