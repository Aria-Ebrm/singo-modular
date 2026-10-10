<?php
declare(strict_types=1);

/**
 * Fallback Jalali Date Converter Class (Singo_jDateTime)
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_jDateTime {

	/**
	 * Convert Unix timestamp to Jalali date string using PHP date format.
	 */
	public static function date( string $format, mixed $timestamp = false, string $none = '', string $none2 = '', string $timezone = '' ): string {
		if ( false === $timestamp || null === $timestamp || '' === $timestamp ) {
			$timestamp = time();
		} else {
			$timestamp = (int) $timestamp;
		}

		$g_y = (int) gmdate( 'Y', $timestamp );
		$g_m = (int) gmdate( 'n', $timestamp );
		$g_d = (int) gmdate( 'j', $timestamp );

		list( $j_y, $j_m, $j_d ) = self::gregorian_to_jalali( $g_y, $g_m, $g_d );

		$out = '';
		$len = strlen( $format );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $format[ $i ];

			// Handle backslash escaping in PHP date format strings
			if ( $c === '\\' ) {
				if ( isset( $format[ $i + 1 ] ) ) {
					$out .= $format[ $i + 1 ];
					$i++;
				}
				continue;
			}

			switch ( $c ) {
				case 'Y':
					$out .= $j_y;
					break;
				case 'y':
					$out .= substr( (string) $j_y, 2, 2 );
					break;
				case 'm':
					$out .= sprintf( '%02d', $j_m );
					break;
				case 'n':
					$out .= $j_m;
					break;
				case 'd':
					$out .= sprintf( '%02d', $j_d );
					break;
				case 'j':
					$out .= $j_d;
					break;
				case 'F':
					$out .= self::get_month_name( (int) $j_m );
					break;
				case 'l':
					$out .= self::get_day_name( (int) gmdate( 'w', $timestamp ) );
					break;
				case 'D':
					$out .= self::get_short_day_name( (int) gmdate( 'w', $timestamp ) );
					break;
				case 'a':
					$out .= ( gmdate( 'a', $timestamp ) === 'am' ) ? 'قبل‌ازظهر' : 'بعدازظهر';
					break;
				case 'A':
					$out .= ( gmdate( 'A', $timestamp ) === 'AM' ) ? 'قبل‌ازظهر' : 'بعدازظهر';
					break;
				default:
					$out .= gmdate( $c, $timestamp );
					break;
			}
		}

		return $out;
	}

	/**
	 * Gregorian to Jalali conversion algorithm.
	 */
	public static function gregorian_to_jalali( int $g_y, int $g_m, int $g_d ): array {
		$g_days_in_month = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$j_days_in_month = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );

		$gy = $g_y - 1600;
		$gm = $g_m - 1;
		$gd = $g_d - 1;

		$g_day_no = 365 * $gy + floor( ( $gy + 3 ) / 4 ) - floor( ( $gy + 99 ) / 100 ) + floor( ( $gy + 399 ) / 400 );

		for ( $i = 0; $i < $gm; ++$i ) {
			$g_day_no += $g_days_in_month[ $i ];
		}
		if ( $gm > 1 && ( ( $gy % 4 == 0 && $gy % 100 != 0 ) || ( $gy % 400 == 0 ) ) ) {
			$g_day_no++;
		}
		$g_day_no += $gd;

		$j_day_no = $g_day_no - 79;

		$j_np = floor( $j_day_no / 12053 );
		$j_day_no %= 12053;

		$jy = 979 + 33 * $j_np + 4 * floor( $j_day_no / 1461 );
		$j_day_no %= 1461;

		if ( $j_day_no >= 366 ) {
			$jy += floor( ( $j_day_no - 1 ) / 365 );
			$j_day_no = ( $j_day_no - 1 ) % 365;
		}

		for ( $i = 0; $i < 11 && $j_day_no >= $j_days_in_month[ $i ]; ++$i ) {
			$j_day_no -= $j_days_in_month[ $i ];
		}
		$jm = $i + 1;
		$jd = $j_day_no + 1;

		return array( (int) $jy, (int) $jm, (int) $jd );
	}

	public static function get_month_name( int $month ): string {
		$months = array(
			1  => 'فروردین',
			2  => 'اردیبهشت',
			3  => 'خرداد',
			4  => 'تیر',
			5  => 'مرداد',
			6  => 'شهریور',
			7  => 'مهر',
			8  => 'آبان',
			9  => 'آذر',
			10 => 'دی',
			11 => 'بهمن',
			12 => 'اسفند',
		);
		return isset( $months[ $month ] ) ? $months[ $month ] : '';
	}

	public static function get_day_name( int $day_num ): string {
		$days = array(
			0 => 'یکشنبه',
			1 => 'دوشنبه',
			2 => 'سه‌شنبه',
			3 => 'چهارشنبه',
			4 => 'پنجشنبه',
			5 => 'جمعه',
			6 => 'شنبه',
		);
		return isset( $days[ $day_num ] ) ? $days[ $day_num ] : '';
	}

	public static function get_short_day_name( int $day_num ): string {
		$days = array(
			0 => 'یک',
			1 => 'دو',
			2 => 'سه',
			3 => 'چهار',
			4 => 'پنج',
			5 => 'جمعه',
			6 => 'شنبه',
		);
		return isset( $days[ $day_num ] ) ? $days[ $day_num ] : '';
	}
}

if ( ! class_exists( 'SDM_jDateTime' ) ) {
	class_alias( 'Singo_jDateTime', 'SDM_jDateTime' );
}
