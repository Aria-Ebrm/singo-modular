<?php
declare(strict_types=1);

/**
 * Elementor Integration for Shamsi Date (Singo_Shamsi_Elementor)
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Shamsi_Elementor {

	public function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		add_filter( 'elementor/dynamic_tags/post_date/value', array( $this, 'filter_elementor_dynamic_date' ), 20, 2 );
		add_filter( 'elementor/dynamic_tags/post_time/value', array( $this, 'filter_elementor_dynamic_date' ), 20, 2 );
	}

	/**
	 * Intercept Elementor dynamic tags date value.
	 */
	public function filter_elementor_dynamic_date( $value, $tag ) {
		if ( empty( $value ) ) {
			return $value;
		}

		$timestamp = strtotime( $value );
		if ( ! $timestamp ) {
			global $post;
			if ( $post ) {
				$timestamp = get_post_time( 'U', true, $post );
			}
		}

		if ( ! $timestamp ) {
			return $value;
		}

		$format = singo_tools_get_option( 'default_format', 'Y/m/d' );
		if ( method_exists( $tag, 'get_settings' ) ) {
			$settings = $tag->get_settings();
			if ( ! empty( $settings['format'] ) ) {
				$format = $settings['format'];
			} elseif ( ! empty( $settings['custom_format'] ) ) {
				$format = $settings['custom_format'];
			}
		}

		return Singo_Shamsi_Converter::format_date( $timestamp, $format );
	}
}

if ( ! class_exists( 'SDM_Elementor' ) ) {
	class_alias( 'Singo_Shamsi_Elementor', 'SDM_Elementor' );
}
