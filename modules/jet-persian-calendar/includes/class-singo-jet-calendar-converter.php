<?php
declare(strict_types=1);

namespace SingoTools\Modules\JetPersianCalendar;

/**
 * Singo Tools - Jet Persian Calendar Date Converter (Stateless & High-Performance)
 *
 * Provides accurate Gregorian to Jalali and Jalali to Gregorian conversions
 * without any external dependencies or global date hook interference.
 *
 * @package SingoTools\Modules\JetPersianCalendar
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class JetCalendarConverter {

	/**
	 * Persian month names in full format.
	 *
	 * @var array<int, string>
	 */
	private static array $months_fa = array(
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

	/**
	 * Persian weekday names in full format.
	 *
	 * @var array<int, string>
	 */
	private static array $weekdays_fa = array(
		6 => 'شنبه',
		0 => 'یکشنبه',
		1 => 'دوشنبه',
		2 => 'سه‌شنبه',
		3 => 'چهارشنبه',
		4 => 'پنجشنبه',
		5 => 'جمعه',
	);

	/**
	 * Convert Gregorian year, month, day to Jalali.
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month.
	 * @param int $gd Gregorian day.
	 * @return array{y: int, m: int, d: int}
	 */
	public static function gregorian_to_jalali( int $gy, int $gm, int $gd ): array {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * (int) ( $days / 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy   += (int) ( ( $days - 1 ) / 365 );
			$days  = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array(
			'y' => (int) $jy,
			'm' => (int) $jm,
			'd' => (int) $jd,
		);
	}

	/**
	 * Convert Jalali year, month, day to Gregorian.
	 *
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month.
	 * @param int $jd Jalali day.
	 * @return array{y: int, m: int, d: int}
	 */
	public static function jalali_to_gregorian( int $jy, int $jm, int $jd ): array {
		$jy   += 1595;
		$days  = -355668 + ( 365 * $jy ) + ( (int) ( $jy / 33 ) * 8 ) + (int) ( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd + ( ( $jm < 7 ) ? ( ( $jm - 1 ) * 31 ) : ( ( ( $jm - 7 ) * 30 ) + 186 ) );
		$gy    = 400 * (int) ( $days / 146097 );
		$days %= 146097;

		if ( $days > 36524 ) {
			$gy   += 100 * (int) ( --$days / 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				$days++;
			}
		}

		$gy   += 4 * (int) ( $days / 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$gy   += (int) ( ( $days - 1 ) / 365 );
			$days  = ( $days - 1 ) % 365;
		}

		$gd = $days + 1;
		$sal_a = array( 0, 31, ( ( ( $gy % 4 === 0 && $gy % 100 !== 0 ) || ( $gy % 400 === 0 ) ) ? 29 : 28 ), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$gm = 0;

		while ( $gm < 13 && $gd > $sal_a[ $gm ] ) {
			$gd -= $sal_a[ $gm ];
			$gm++;
		}

		return array(
			'y' => (int) $gy,
			'm' => (int) $gm,
			'd' => (int) $gd,
		);
	}

	/**
	 * Validate a Jalali date.
	 *
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month.
	 * @param int $jd Jalali day.
	 * @return bool
	 */
	public static function is_valid_jalali( int $jy, int $jm, int $jd ): bool {
		if ( $jy < 1 || $jy > 3200 || $jm < 1 || $jm > 12 || $jd < 1 ) {
			return false;
		}

		if ( $jm <= 6 && $jd > 31 ) {
			return false;
		}

		if ( $jm >= 7 && $jm <= 11 && $jd > 30 ) {
			return false;
		}

		if ( $jm === 12 ) {
			$is_leap = self::is_leap_jalali( $jy );
			if ( ( $is_leap && $jd > 30 ) || ( ! $is_leap && $jd > 29 ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if a Jalali year is a leap year.
	 *
	 * @param int $jy Jalali year.
	 * @return bool
	 */
	public static function is_leap_jalali( int $jy ): bool {
		$r = ( $jy + 38 ) * 31 % 128;
		return $r <= 30;
	}

	/**
	 * Convert English digits to Persian digits.
	 *
	 * @param string $str Input string.
	 * @return string String with Persian digits.
	 */
	public static function to_persian_digits( string $str ): string {
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		return str_replace( $en, $fa, $str );
	}

	/**
	 * Convert Persian digits to English digits.
	 *
	 * @param string $str Input string.
	 * @return string String with English digits.
	 */
	public static function to_english_digits( string $str ): string {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $ar, $en, str_replace( $fa, $en, $str ) );
	}

	/**
	 * Get Persian month name by month number (1-12).
	 *
	 * @param int $month Month index.
	 * @return string Month name.
	 */
	public static function get_month_name( int $month ): string {
		return self::$months_fa[ $month ] ?? '';
	}

	/**
	 * Get Persian weekday name by PHP weekday index (0=Sunday, 6=Saturday).
	 *
	 * @param int $w Day of week (0-6).
	 * @return string Weekday name.
	 */
	public static function get_weekday_name( int $w ): string {
		return self::$weekdays_fa[ $w ] ?? '';
	}
}
