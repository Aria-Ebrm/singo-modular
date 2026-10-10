<?php
declare(strict_types=1);

/**
 * System Debugger & High Performance Error Logger for Singo Tools (Singo_Tools_Debugger)
 * PHP 8+ Standardized & WPDS Compliant
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Tools_Debugger {

	/**
	 * Single instance of the class.
	 *
	 * @var Singo_Tools_Debugger|null
	 */
	private static ?Singo_Tools_Debugger $instance = null;

	/**
	 * Log file path.
	 *
	 * @var string|null
	 */
	private static ?string $log_file = null;

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
	 * Get log file path.
	 */
	public static function get_log_file_path(): string {
		if ( null === self::$log_file ) {
			$upload_dir       = wp_upload_dir();
			$singo_upload_dir = trailingslashit( $upload_dir['basedir'] ) . 'singo-tools-logs/';

			if ( ! file_exists( $singo_upload_dir ) ) {
				wp_mkdir_p( $singo_upload_dir );
				@file_put_contents( $singo_upload_dir . '.htaccess', 'deny from all' );
				@file_put_contents( $singo_upload_dir . 'index.php', '<?php // Silence' );
			}

			self::$log_file = $singo_upload_dir . 'singo-debug.log';
		}
		return self::$log_file;
	}

	/**
	 * Write log entry.
	 */
	public static function log( mixed $message, string $level = 'INFO' ): void {
		$enabled = singo_tools_get_option( 'enable_system_debug', true );
		if ( ! $enabled ) {
			return;
		}

		$file = self::get_log_file_path();
		$time = wp_date( 'Y-m-d H:i:s' );

		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true );
		}

		$formatted = sprintf( "[%s] [%s]: %s\n", $time, strtoupper( $level ), $message );
		@file_put_contents( $file, $formatted, FILE_APPEND );
	}

	/**
	 * Fast memory-efficient tail log reader with optional level filtering and live search query.
	 */
	public static function get_tail_log( string $file_path, string $filter_level = 'ALL', string $search_query = '', int $max_bytes = 131072 ): string {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return 'فایل لاگ یافت نشد.';
		}

		$size = @filesize( $file_path );
		if ( false === $size || 0 === $size ) {
			return 'فایل لاگ خالی است.';
		}

		$fp = @fopen( $file_path, 'rb' );
		if ( ! $fp ) {
			return 'امکان خواندن فایل لاگ وجود ندارد.';
		}

		if ( $size > $max_bytes ) {
			fseek( $fp, -$max_bytes, SEEK_END );
		}

		$content = fread( $fp, $max_bytes );
		fclose( $fp );

		if ( empty( $content ) ) {
			return 'محتوایی یافت نشد.';
		}

		$lines    = explode( "\n", $content );
		$filtered = array();
		$needle   = ( 'ALL' !== $filter_level && ! empty( $filter_level ) ) ? '[' . strtoupper( $filter_level ) . ']' : '';
		$search   = trim( mb_strtolower( $search_query ) );

		foreach ( $lines as $line ) {
			if ( empty( trim( $line ) ) ) {
				continue;
			}

			// Level filter check
			if ( ! empty( $needle ) ) {
				$has_level = ( false !== strpos( $line, $needle ) ) || ( false !== stripos( $line, $filter_level ) );
				if ( ! $has_level ) {
					continue;
				}
			}

			// Search query filter check
			if ( ! empty( $search ) ) {
				if ( false === mb_strpos( mb_strtolower( $line ), $search ) ) {
					continue;
				}
			}

			$filtered[] = $line;
		}

		if ( empty( $filtered ) ) {
			return 'هیچ لاگی مطابقت نداشت.';
		}

		return implode( "\n", $filtered );
	}

	/**
	 * Get metadata for log file.
	 */
	public static function get_log_metadata( string $log_type = 'singo' ): array {
		$file = ( 'wp' === $log_type ) ? WP_CONTENT_DIR . '/debug.log' : self::get_log_file_path();
		if ( ! file_exists( $file ) ) {
			return array(
				'exists'   => false,
				'size_fmt' => '0 KB',
				'mtime'    => '-',
				'lines'    => 0,
			);
		}

		$size  = (int) @filesize( $file );
		$mtime = @filemtime( $file );

		return array(
			'exists'   => true,
			'size_fmt' => size_format( $size, 2 ),
			'mtime'    => $mtime ? wp_date( 'Y-m-d H:i:s', $mtime ) : '-',
			'lines'    => substr_count( (string) @file_get_contents( $file, false, null, 0, 500000 ), "\n" ),
		);
	}

	/**
	 * Get log contents.
	 */
	public static function get_log_contents( string $filter_level = 'ALL', string $search_query = '' ): string {
		return self::get_tail_log( self::get_log_file_path(), $filter_level, $search_query );
	}

	/**
	 * Clear log file by type (singo vs wp).
	 */
	public static function clear_log( string $log_type = 'singo' ): bool {
		if ( 'wp' === $log_type ) {
			$file = WP_CONTENT_DIR . '/debug.log';
		} else {
			$file = self::get_log_file_path();
		}

		if ( file_exists( $file ) && is_writable( $file ) ) {
			return @file_put_contents( $file, '' ) !== false;
		}
		return true;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_singo_fetch_debug_logs', array( $this, 'ajax_fetch_logs' ) );
		add_action( 'wp_ajax_singo_clear_debug_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_singo_download_debug_log', array( $this, 'ajax_download_log' ) );
		add_action( 'wp_ajax_singo_inspect_db_health', array( $this, 'ajax_inspect_db' ) );
		add_action( 'wp_ajax_singo_repair_db_health', array( $this, 'ajax_repair_db' ) );
		add_action( 'shutdown', array( $this, 'log_shutdown_errors' ) );
	}

	/**
	 * Catch & Log Shutdown Fatal Errors + Throttled Email Alert (strictly checked if system debug enabled).
	 */
	public function log_shutdown_errors(): void {
		$enabled = singo_tools_get_option( 'enable_system_debug', true );
		if ( ! $enabled ) {
			return;
		}

		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			$file_path = $error['file'];
			$is_singo  = function_exists( 'str_contains' ) ? str_contains( $file_path, 'singo-tools' ) || str_contains( $file_path, 'optionino' ) : ( false !== strpos( $file_path, 'singo-tools' ) || false !== strpos( $file_path, 'optionino' ) );

			if ( $is_singo ) {
				$err_msg = sprintf( 'خطای کشنده PHP: %s در فایل %s خط %d', $error['message'], $error['file'], $error['line'] );
				self::log( $err_msg, 'FATAL_ERROR' );

				if ( false === get_transient( 'singo_fatal_email_alert_sent' ) ) {
					set_transient( 'singo_fatal_email_alert_sent', true, 600 );
					$to      = get_option( 'admin_email' );
					$subject = '[هشدار سینگو] خطای مهلک پی‌اچ‌پای در سایت ' . get_bloginfo( 'name' );
					$body    = "سلام مدیر سایت،\n\nیک خطای مهلک در افزونه جعبه ابزار سینگو رخ داده است:\n\n" . $err_msg . "\n\nتاریخ: " . wp_date( 'Y-m-d H:i:s' ) . "\nآدرس سایت: " . site_url() . "\n\nلطفاً بخش دیباگ افزونه را بررسی نمایید.";
					@wp_mail( $to, $subject, $body );
				}
			}
		}
	}

	/**
	 * AJAX Handler: Fetch Logs Lazily with Filtering & Search.
	 */
	public function ajax_fetch_logs(): void {
		check_ajax_referer( 'singo_debug_nonce', 'security' );

		if ( ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی ناکافی است.' ) );
		}

		$log_type     = isset( $_POST['log_type'] ) ? sanitize_text_field( wp_unslash( $_POST['log_type'] ) ) : 'singo';
		$filter_level = isset( $_POST['filter_level'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_level'] ) ) : 'ALL';
		$search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) : '';

		$file = ( 'wp' === $log_type ) ? WP_CONTENT_DIR . '/debug.log' : self::get_log_file_path();
		$content = self::get_tail_log( $file, $filter_level, $search_query );
		$meta    = self::get_log_metadata( $log_type );

		wp_send_json_success( array(
			'content' => $content,
			'meta'    => $meta,
		) );
	}

	/**
	 * AJAX Handler: Download Log File directly by type.
	 */
	public function ajax_download_log(): void {
		check_ajax_referer( 'singo_debug_nonce', 'security' );

		if ( ! singo_tools_current_user_can_manage() ) {
			wp_die( 'سطح دسترسی ناکافی است.' );
		}

		$log_type = isset( $_GET['log_type'] ) ? sanitize_text_field( wp_unslash( $_GET['log_type'] ) ) : 'singo';
		if ( 'wp' === $log_type ) {
			$file     = WP_CONTENT_DIR . '/debug.log';
			$filename = 'wp-debug-' . wp_date( 'Y-m-d' ) . '.log';
		} else {
			$file     = self::get_log_file_path();
			$filename = 'singo-debug-' . wp_date( 'Y-m-d' ) . '.log';
		}

		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			wp_die( 'فایل لاگ وجود ندارد یا قابل خواندن نیست.' );
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $file ) );

		readfile( $file );
		exit;
	}

	/**
	 * AJAX Handler: Clear Debug Logs by type.
	 */
	public function ajax_clear_logs(): void {
		check_ajax_referer( 'singo_debug_nonce', 'security' );

		if ( ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی ناکافی است.' ) );
		}

		$log_type = isset( $_POST['log_type'] ) ? sanitize_text_field( wp_unslash( $_POST['log_type'] ) ) : 'singo';
		$cleared  = self::clear_log( $log_type );

		if ( $cleared ) {
			$meta = self::get_log_metadata( $log_type );
			wp_send_json_success( array(
				'message' => 'فایل لاگ با موفقیت پاکسازی شد.',
				'meta'    => $meta,
			) );
		} else {
			wp_send_json_error( array( 'message' => 'پاکسازی لاگ ناموفق بود. لطفاً دسترسی فایل روی سرور را بررسی کنید.' ) );
		}
	}

	/**
	 * AJAX Handler: Inspect Database & Options Health.
	 */
	public function ajax_inspect_db(): void {
		check_ajax_referer( 'singo_debug_nonce', 'security' );

		if ( ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی ناکافی است.' ) );
		}

		$options        = get_option( 'singo_tools_options', array() );
		$groups         = get_option( 'mu_attribute_groups', array() );
		$groups_map     = get_option( 'mu_attribute_groups_map', array() );
		$default_group  = get_option( 'mu_default_attribute_group', 'سایر مشخصات' );

		$health_status  = 'Healthy';
		$health_issues  = array();

		if ( empty( $options ) || ! is_array( $options ) ) {
			$health_status   = 'Warning';
			$health_issues[] = 'تنظیمات singo_tools_options در دیتابیس خالی یا نامعتبر است.';
		}

		if ( empty( $groups ) || ! is_array( $groups ) ) {
			$health_status   = 'Warning';
			$health_issues[] = 'لیست mu_attribute_groups خالی است و از مقادیر پیش‌فرض استفاده می‌شود.';
		}

		wp_send_json_success( array(
			'status'        => $health_status,
			'issues'        => $health_issues,
			'options'       => $options,
			'groups'        => $groups,
			'default_group' => $default_group,
			'groups_map'    => $groups_map,
		) );
	}

	/**
	 * AJAX Handler: Auto-Repair Database & Options Health.
	 */
	public function ajax_repair_db(): void {
		check_ajax_referer( 'singo_debug_nonce', 'security' );

		if ( ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی ناکافی است.' ) );
		}

		$default_groups = array( 'مشخصات کلی', 'سایر مشخصات' );
		update_option( 'mu_attribute_groups', $default_groups, true );
		update_option( 'mu_default_attribute_group', 'سایر مشخصات', true );

		$options = get_option( 'singo_tools_options', array() );
		if ( empty( $options ) || ! is_array( $options ) ) {
			$options = array(
				'master_switch'          => true,
				'enable_frontend'        => true,
				'enable_admin'           => true,
				'convert_digits'         => true,
				'enable_product_features' => true,
				'mu_groups'              => $default_groups,
				'mu_default_group'       => 'سایر مشخصات',
				'enable_system_debug'    => true,
			);
			update_option( 'singo_tools_options', $options, true );
		}

		singo_tools_get_option( '', '', true );

		wp_send_json_success( array(
			'message' => 'ساختار دیتابیس و گزینه‌های افزونه با موفقیت بازسازی و تعمیر شدند.',
		) );
	}
}
