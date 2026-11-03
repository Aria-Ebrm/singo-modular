<?php
declare(strict_types=1);

/**
 * Date Conversion Engine (Singo_Shamsi_Converter)
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Shamsi_Converter {

	/**
	 * In-memory cache to prevent double processing in the same request.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Main method to convert timestamp to Jalali string.
	 *
	 * @param mixed  $timestamp Unix timestamp.
	 * @param string $format    Date format.
	 * @param string $timezone  Timezone string (optional).
	 * @return string Formatted date string.
	 */
	public static function format_date( mixed $timestamp, string $format = '', string $timezone = '' ): string {
		if ( is_numeric( $timestamp ) ) {
			$timestamp = (int) $timestamp;
		} elseif ( is_string( $timestamp ) && ! is_numeric( $timestamp ) ) {
			$parsed    = strtotime( $timestamp );
			$timestamp = false !== $parsed ? $parsed : time();
		} elseif ( $timestamp instanceof \DateTimeInterface ) {
			$timestamp = $timestamp->getTimestamp();
		} else {
			$timestamp = time();
		}

		if ( empty( $format ) ) {
			$format = singo_tools_get_option( 'default_format', 'Y/m/d' );
		}

		if ( empty( $timezone ) ) {
			$timezone = wp_timezone_string();
		}

		// Generate a unique cache key based on params
		$cache_key = md5( $timestamp . '_' . $format . '_' . $timezone );

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$formatted = '';

		// 1. Try IntlDateFormatter (Fastest, Native PHP)
		if ( class_exists( 'IntlDateFormatter' ) ) {
			try {
				$icu_format = self::php_format_to_icu( $format );

				$formatter = new IntlDateFormatter(
					'fa_IR@calendar=persian',
					IntlDateFormatter::FULL,
					IntlDateFormatter::FULL,
					$timezone,
					IntlDateFormatter::TRADITIONAL,
					$icu_format
				);

				$formatted_intl = $formatter->format( $timestamp );
				if ( $formatted_intl ) {
					$formatted = $formatted_intl;
				} else {
					$formatted = self::use_jdatetime( $timestamp, $format, $timezone );
				}
			} catch ( Exception $e ) {
				$formatted = self::use_jdatetime( $timestamp, $format, $timezone );
			}
		} else {
			// 2. Fallback to Singo_jDateTime
			$formatted = self::use_jdatetime( $timestamp, $format, $timezone );
		}

		// Convert digits and AM/PM if configured
		if ( singo_tools_get_option( 'convert_digits', false ) ) {
			$formatted = self::convert_to_persian_digits( $formatted );
		}

		self::$cache[ $cache_key ] = $formatted;

		return $formatted;
	}

	/**
	 * Convert PHP date format string to ICU pattern string safely.
	 */
	private static function php_format_to_icu( $format ) {
		$map = array(
			'Y' => 'yyyy',
			'y' => 'yy',
			'F' => 'MMMM',
			'm' => 'MM',
			'n' => 'M',
			'd' => 'dd',
			'j' => 'd',
			'l' => 'EEEE',
			'D' => 'EEE',
			'H' => 'HH',
			'G' => 'H',
			'h' => 'hh',
			'g' => 'h',
			'i' => 'mm',
			's' => 'ss',
			'a' => 'a',
			'A' => 'a',
		);

		$icu = '';
		$len = strlen( $format );
		$in_quote = false;

		for ( $i = 0; $i < $len; $i++ ) {
			$c = $format[ $i ];

			if ( $c === '\\' ) {
				if ( isset( $format[ $i + 1 ] ) ) {
					$next = $format[ $i + 1 ];
					if ( ! $in_quote ) {
						$icu .= "'";
						$in_quote = true;
					}
					$icu .= ( $next === "'" ) ? "''" : $next;
					$i++;
				}
				continue;
			}

			if ( isset( $map[ $c ] ) ) {
				if ( $in_quote ) {
					$icu .= "'";
					$in_quote = false;
				}
				$icu .= $map[ $c ];
			} else {
				if ( preg_match( '/[a-zA-Z]/', $c ) ) {
					if ( ! $in_quote ) {
						$icu .= "'";
						$in_quote = true;
					}
					$icu .= ( $c === "'" ) ? "''" : $c;
				} else {
					if ( $in_quote ) {
						$icu .= "'";
						$in_quote = false;
					}
					$icu .= $c;
				}
			}
		}

		if ( $in_quote ) {
			$icu .= "'";
		}

		return $icu;
	}

	/**
	 * Fallback date formatter using Singo_jDateTime.
	 */
	private static function use_jdatetime( $timestamp, $format, $timezone ) {
		return Singo_jDateTime::date( $format, $timestamp, '', '', $timezone );
	}

	/**
	 * Convert English digits and AM/PM strings to Persian.
	 */
	public static function convert_to_persian_digits( $string ) {
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'pm', 'am', 'PM', 'AM' );
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', 'بعدازظهر', 'قبل‌ازظهر', 'بعدازظهر', 'قبل‌ازظهر' );
		return str_replace( $en, $fa, (string) $string );
	}
}

if ( ! class_exists( 'SDM_Converter' ) ) {
	class_alias( 'Singo_Shamsi_Converter', 'SDM_Converter' );
}
