<?php
declare(strict_types=1);

namespace SingoTools\Modules\ReadingTime;

/**
 * Singo Tools - Reading Time Engine & Calculator
 *
 * High-performance, memory-cached word counter and reading time calculator
 * optimized for Persian (Farsi), Arabic, and multilingual Unicode content.
 *
 * @package SingoTools\Modules\ReadingTime
 * @since 4.2.2
 */

defined( 'ABSPATH' ) || exit;

class Singo_Reading_Time {

	/**
	 * Single instance.
	 */
	private static ?self $instance = null;

	/**
	 * Runtime memory cache: [ post_id => [ 'words' => int, 'time' => [ wpm => int ] ] ].
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $memory_cache = array();

	/**
	 * Post meta key for storing calculated word count.
	 */
	public const META_WORD_COUNT = '_singo_reading_words';

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register shortcode
		add_shortcode( 'reading_time', array( $this, 'render_shortcode' ) );

		// Flush cache when a post is created or updated
		add_action( 'save_post', array( $this, 'flush_post_cache' ), 10, 1 );
		add_action( 'delete_post', array( $this, 'flush_post_cache' ), 10, 1 );
	}

	/**
	 * Calculate word count of a post with dual caching (RAM + Post Meta).
	 *
	 * @param int $post_id Post ID.
	 * @return int Total word count.
	 */
	public static function count_words( int $post_id ): int {
		if ( $post_id <= 0 ) {
			return 0;
		}

		// 1. Check in-memory RAM cache for this request
		if ( isset( self::$memory_cache[ $post_id ]['words'] ) ) {
			return (int) self::$memory_cache[ $post_id ]['words'];
		}

		// 2. Check persistent Post Meta cache
		$cached_words = get_post_meta( $post_id, self::META_WORD_COUNT, true );
		if ( '' !== $cached_words && false !== $cached_words && is_numeric( $cached_words ) ) {
			$words = absint( $cached_words );
			self::$memory_cache[ $post_id ]['words'] = $words;
			return $words;
		}

		// 3. Fetch raw content
		$content = (string) get_post_field( 'post_content', $post_id );
		if ( '' === trim( $content ) ) {
			self::$memory_cache[ $post_id ]['words'] = 0;
			return 0;
		}

		// 4. Strip shortcodes safely (avoids recursion and heavy shortcode execution)
		$content = strip_shortcodes( $content );

		// 5. Strip script, style, iframe, and noscript blocks
		$content = preg_replace( '/<(script|style|iframe|noscript)[^>]*>.*?<\/\1>/is', ' ', $content );
		if ( null === $content ) {
			$content = '';
		}

		// 6. Strip all remaining HTML tags
		$text = wp_strip_all_tags( $content, true );

		// 7. Decode HTML entities
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// 8. Normalize whitespace
		$text = preg_replace( '/\s+/u', ' ', trim( $text ) );
		if ( null === $text || '' === $text ) {
			self::$memory_cache[ $post_id ]['words'] = 0;
			return 0;
		}

		// 9. Count Unicode words (supports Persian, Arabic, Latin, Numbers, and ZWNJ نیم‌فاصله)
		preg_match_all( '/[\p{L}\p{N}]+(?:[\'’\-\x{200C}][\p{L}\p{N}]+)*/u', $text, $matches );
		$words = isset( $matches[0] ) ? count( $matches[0] ) : 0;

		// 10. Cache result in post meta and memory
		update_post_meta( $post_id, self::META_WORD_COUNT, $words );
		self::$memory_cache[ $post_id ]['words'] = $words;

		return $words;
	}

	/**
	 * Calculate estimated reading time in minutes.
	 *
	 * @param int      $post_id Post ID.
	 * @param int|null $wpm Words per minute (default: from plugin options or 200).
	 * @return int Estimated reading time in minutes (minimum 1 minute).
	 */
	public static function calculate_minutes( int $post_id, ?int $wpm = null ): int {
		if ( null === $wpm || $wpm < 1 ) {
			$wpm = absint( singo_tools_get_option( 'reading_time_wpm', 200 ) );
			if ( $wpm < 1 ) {
				$wpm = 200;
			}
		}

		if ( isset( self::$memory_cache[ $post_id ]['time'][ $wpm ] ) ) {
			return (int) self::$memory_cache[ $post_id ]['time'][ $wpm ];
		}

		$words = self::count_words( $post_id );
		if ( $words <= 0 ) {
			return 1;
		}

		$minutes = (int) max( 1, (int) ceil( $words / $wpm ) );
		self::$memory_cache[ $post_id ]['time'][ $wpm ] = $minutes;

		return $minutes;
	}

	/**
	 * Format and return reading time string with optional postfix and Persian digits.
	 *
	 * @param int         $post_id Post ID.
	 * @param int|null    $wpm Words per minute override.
	 * @param string|null $postfix Custom postfix override.
	 * @param bool|null   $persian_digits Persian digits conversion override.
	 * @return string Formatted reading time string.
	 */
	public static function get_formatted( int $post_id, ?int $wpm = null, ?string $postfix = null, ?bool $persian_digits = null ): string {
		$minutes = self::calculate_minutes( $post_id, $wpm );

		// Determine postfix
		if ( null === $postfix ) {
			$postfix = (string) singo_tools_get_option( 'reading_time_postfix', 'دقیقه' );
		}
		$postfix = trim( $postfix );

		// Determine Persian digits
		if ( null === $persian_digits ) {
			$persian_digits = (bool) singo_tools_get_option( 'reading_time_persian_digits', true );
		}

		$num_str = (string) $minutes;
		if ( $persian_digits ) {
			$persian_numbers = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
			$latin_numbers   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
			$num_str         = str_replace( $latin_numbers, $persian_numbers, $num_str );
		}

		if ( '' === $postfix ) {
			return $num_str;
		}

		return $num_str . ' ' . $postfix;
	}

	/**
	 * Shortcode callback: [reading_time].
	 *
	 * Attributes:
	 *   - id: Post ID (optional; defaults to current post ID)
	 *   - wpm: Words per minute (optional; defaults to settings or 200)
	 *   - postfix: Display suffix (optional; e.g. "دقیقه", or empty "" for pure number)
	 *   - persian_digits: 'yes'|'no'|'true'|'false'
	 *
	 * @param array<string, mixed>|string $atts
	 * @return string
	 */
	public function render_shortcode( mixed $atts ): string {
		$parsed_atts = shortcode_atts(
			array(
				'id'             => 0,
				'wpm'            => 0,
				'postfix'        => null,
				'persian_digits' => null,
			),
			(array) $atts,
			'reading_time'
		);

		$post_id = absint( $parsed_atts['id'] );
		if ( $post_id <= 0 ) {
			$post_id = get_the_ID() ?: 0;
		}

		if ( $post_id <= 0 ) {
			return '';
		}

		$wpm = absint( $parsed_atts['wpm'] );
		$wpm = ( $wpm > 0 ) ? $wpm : null;

		$postfix = null;
		if ( null !== $parsed_atts['postfix'] ) {
			$postfix = (string) $parsed_atts['postfix'];
		}

		$persian_digits = null;
		if ( null !== $parsed_atts['persian_digits'] ) {
			$p = strtolower( trim( (string) $parsed_atts['persian_digits'] ) );
			$persian_digits = in_array( $p, array( '1', 'true', 'yes', 'on' ), true );
		}

		return esc_html( self::get_formatted( $post_id, $wpm, $postfix, $persian_digits ) );
	}

	/**
	 * Flush cached word count for a post when saved or updated.
	 *
	 * @param int $post_id
	 */
	public function flush_post_cache( int $post_id ): void {
		if ( $post_id > 0 ) {
			delete_post_meta( $post_id, self::META_WORD_COUNT );
			unset( self::$memory_cache[ $post_id ] );
		}
	}
}

// ──────────────────────────────────────────
// Global Helper Functions
// ──────────────────────────────────────────

if ( ! function_exists( 'singo_get_reading_time' ) ) {
	/**
	 * Get formatted reading time for a post.
	 *
	 * @param int|null    $post_id Post ID (defaults to current post).
	 * @param int|null    $wpm Words per minute override.
	 * @param string|null $postfix Suffix (e.g. "دقیقه", or empty string for number only).
	 * @param bool|null   $persian_digits Whether to convert digits to Persian.
	 * @return string Formatted reading time string.
	 */
	function singo_get_reading_time( ?int $post_id = null, ?int $wpm = null, ?string $postfix = null, ?bool $persian_digits = null ): string {
		$target_id = $post_id ?: ( get_the_ID() ?: 0 );
		if ( $target_id <= 0 ) {
			return '';
		}
		return Singo_Reading_Time::get_formatted( $target_id, $wpm, $postfix, $persian_digits );
	}
}

if ( ! function_exists( 'singo_get_post_word_count' ) ) {
	/**
	 * Get total word count for a post.
	 *
	 * @param int|null $post_id Post ID (defaults to current post).
	 * @return int Total word count.
	 */
	function singo_get_post_word_count( ?int $post_id = null ): int {
		$target_id = $post_id ?: ( get_the_ID() ?: 0 );
		if ( $target_id <= 0 ) {
			return 0;
		}
		return Singo_Reading_Time::count_words( $target_id );
	}
}
