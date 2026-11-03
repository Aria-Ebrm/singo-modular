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
		$enabled = singo_tools_get_option( 'enable_system_debug', false );
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
		add_action( 'wp_ajax_singo_trigger_test_log', array( $this, 'ajax_trigger_test_log' ) );
		add_action( 'wp_ajax_singo_test_debug_email', array( $this, 'ajax_test_email' ) );
		add_action( 'wp_ajax_singo_toggle_wp_config_debug', array( $this, 'ajax_toggle_wp_config_debug' ) );
		add_action( 'wp_ajax_singo_toggle_system_debug', array( $this, 'ajax_toggle_system_debug' ) );
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

		$checks = array();

		// 1. Options check
		if ( ! empty( $options ) && is_array( $options ) ) {
			$checks[] = array(
				'title'   => 'پایگاه تنظیمات اصلی (singo_tools_options)',
				'status'  => 'ok',
				'desc'    => 'جدول گزینه‌ها کاملاً معتبر است و حاوی ' . count( $options ) . ' کلید فعال می‌باشد.',
			);
		} else {
			$checks[] = array(
				'title'   => 'پایگاه تنظیمات اصلی (singo_tools_options)',
				'status'  => 'warning',
				'desc'    => 'تنظیمات خالی یا نامعتبر است و ممکن است نیاز به بازسازی داشته باشد.',
			);
		}

		// 2. Groups check
		if ( ! empty( $groups ) && is_array( $groups ) ) {
			$checks[] = array(
				'title'   => 'گروه‌بندی مشخصات فنی (mu_attribute_groups)',
				'status'  => 'ok',
				'desc'    => 'تعداد ' . count( $groups ) . ' گروه ویژگی در پایگاه داده ثبت شده است.',
			);
		} else {
			$checks[] = array(
				'title'   => 'گروه‌بندی مشخصات فنی (mu_attribute_groups)',
				'status'  => 'warning',
				'desc'    => 'گروه‌بندی مشخصات خالی است و مقادیر پیش‌فرض جایگزین خواهند شد.',
			);
		}

		// 3. Manifest cache check
		$manifest = get_option( \SingoTools\Core\ManifestManager::OPTION_KEY, null );
		if ( ! empty( $manifest ) && is_array( $manifest ) ) {
			$checks[] = array(
				'title'   => 'کش مانیفست ماژول‌ها (Zero-Disk-Scanning)',
				'status'  => 'ok',
				'desc'    => 'تعداد ' . count( $manifest ) . ' ماژول با موفقیت در کش رم و دیتابیس ثبت شده است.',
			);
		} else {
			$checks[] = array(
				'title'   => 'کش مانیفست ماژول‌ها (Zero-Disk-Scanning)',
				'status'  => 'warning',
				'desc'    => 'کش مانیفست هنوز ساخته نشده و در بارگذاری اول خودکار ایجاد می‌شود.',
			);
		}

		// 4. Log File Write Permissions
		$log_file = self::get_log_file_path();
		$writable = is_writable( dirname( $log_file ) );
		$checks[] = array(
			'title'   => 'دسترسی نوشتن فایل لاگ اختصاصی سینگو',
			'status'  => $writable ? 'ok' : 'error',
			'desc'    => $writable ? 'پوشه wp-content/uploads/singo-tools-logs/ کاملاً باز و قابل نوشتن است.' : 'خطا در دسترسی پوشه لاگ! امکان نوشتن در uploads وجود ندارد.',
		);

		wp_send_json_success( array(
			'checks'        => $checks,
			'options'       => $options,
			'groups'        => $groups,
			'default_group' => $default_group,
			'groups_map'    => $groups_map,
		) );
	}

	/**
	 * AJAX Handler: Trigger a test log entry.
	 */
	public function ajax_trigger_test_log(): void {
		check_ajax_referer( 'singo_debug_nonce', 'security' );

		if ( ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی ناکافی است.' ) );
		}

		$current_user = wp_get_current_user();
		$user_login   = $current_user->exists() ? $current_user->user_login : 'admin';
		$test_message = sprintf( 'پیام لاگ آزمایشی با موفقیت توسط کاربر [%s] ثبت شد. سیستم لاگینگ سینگو فعال و سالم است.', $user_login );

		self::log( $test_message, 'INFO' );
		$meta = self::get_log_metadata( 'singo' );

		wp_send_json_success( array(
			'message' => 'پیام آزمایشی با موفقیت در فایل لاگ ثبت شد.',
			'meta'    => $meta,
		) );
	}

	/**
	 * AJAX Handler: Send a test debug alert email to site admin.
	 */
	public function ajax_test_email(): void {
		check_ajax_referer( 'singo_debug_nonce', 'security' );

		if ( ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی ناکافی است.' ) );
		}

		$to      = (string) get_option( 'admin_email' );
		$subject = '[تست سیستم دیباگ] ارسال موفق پیام آزمایشی در ' . get_bloginfo( 'name' );
		$body    = "سلام مدیر محترم،\n\nاین یک ایمیل آزمایشی از ماژول عیب‌یاب و پایش هوشمند جعبه ابزار سینگو است.\nارسال این ایمیل تایید می‌کند که سیستم ارسال اعلان‌های خطای مهلک (Fatal Errors) با موفقیت روی سرور شما کار می‌کند.\n\nزمان ارسال: " . wp_date( 'Y-m-d H:i:s' ) . "\nآدرس سایت: " . site_url() . "\n\nبا احترام,\nجعبه ابزار سینگو (Singo Tools)";

		$sent = @wp_mail( $to, $subject, $body );

		if ( $sent ) {
			wp_send_json_success( array(
				'message' => sprintf( 'ایمیل آزمایشی با موفقیت به آدرس %s ارسال شد.', $to ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => sprintf( 'ارسال ایمیل به %s ناموفق بود. لطفاً تنظیمات ارسال ایمیل (SMTP) هاست خود را بررسی کنید.', $to ),
			) );
		}
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

	/**
	 * Locate wp-config.php file path.
	 */
	public static function get_wp_config_file_path(): ?string {
		$config_file = ABSPATH . 'wp-config.php';
		if ( file_exists( $config_file ) ) {
			return $config_file;
		}

		$parent_config = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $parent_config ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $parent_config;
		}

		return null;
	}

	/**
	 * Check if wp-config.php is writable.
	 */
	public static function is_wp_config_writable(): bool {
		$path = self::get_wp_config_file_path();
		return $path !== null && is_writable( $path );
	}

	/**
	 * Safely toggle WP_DEBUG, WP_DEBUG_LOG, and WP_DEBUG_DISPLAY in wp-config.php.
	 *
	 * @param bool $enable Whether to enable or disable WP_DEBUG.
	 * @return array{success: bool, message: string}
	 */
	public static function modify_wp_config_debug( bool $enable ): array {
		$file_path = self::get_wp_config_file_path();
		if ( null === $file_path || ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'message' => 'فایل wp-config.php یافت نشد.',
			);
		}

		if ( ! is_writable( $file_path ) ) {
			return array(
				'success' => false,
				'message' => 'فایل wp-config.php به دلیل تنظیمات دسترسی هاست در حالت فقط‌خواندنی (Read-Only) قرار دارد و قابل ویرایش نیست.',
			);
		}

		$content = @file_get_contents( $file_path );
		if ( false === $content || empty( $content ) ) {
			return array(
				'success' => false,
				'message' => 'امکان خواندن محتوای فایل wp-config.php وجود ندارد.',
			);
		}

		// Create a safe, non-public backup in the secure logs directory before touching wp-config
		$upload_dir = wp_upload_dir();
		$backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'singo-tools-logs/';
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
			@file_put_contents( $backup_dir . '.htaccess', 'deny from all' );
			@file_put_contents( $backup_dir . 'index.php', '<?php // Silence' );
		}
		@file_put_contents( $backup_dir . 'wp-config-backup.txt', $content );

		$debug_val = $enable ? 'true' : 'false';
		$log_val   = $enable ? 'true' : 'false';
		$disp_val  = 'false'; // Always keep false to protect storefront

		$has_debug   = (bool) preg_match( '/define\s*\(\s*[\'"]WP_DEBUG[\'"]\s*,/i', $content );
		$has_log     = (bool) preg_match( '/define\s*\(\s*[\'"]WP_DEBUG_LOG[\'"]\s*,/i', $content );
		$has_display = (bool) preg_match( '/define\s*\(\s*[\'"]WP_DEBUG_DISPLAY[\'"]\s*,/i', $content );

		// 1. Update WP_DEBUG if exists
		if ( $has_debug ) {
			$content = preg_replace(
				'/define\s*\(\s*([\'"])WP_DEBUG\1\s*,\s*[^)]+\s*\)\s*;/i',
				"define( 'WP_DEBUG', {$debug_val} );",
				$content
			);
		}

		// 2. Update WP_DEBUG_LOG if exists
		if ( $has_log ) {
			$content = preg_replace(
				'/define\s*\(\s*([\'"])WP_DEBUG_LOG\1\s*,\s*[^)]+\s*\)\s*;/i',
				"define( 'WP_DEBUG_LOG', {$log_val} );",
				$content
			);
		}

		// 3. Update WP_DEBUG_DISPLAY if exists
		if ( $has_display ) {
			$content = preg_replace(
				'/define\s*\(\s*([\'"])WP_DEBUG_DISPLAY\1\s*,\s*[^)]+\s*\)\s*;/i',
				"define( 'WP_DEBUG_DISPLAY', {$disp_val} );",
				$content
			);
		}

		// If enabling and some constants were missing, inject them cleanly
		if ( $enable ) {
			$additions = array();
			if ( ! $has_debug ) {
				$additions[] = "define( 'WP_DEBUG', true );";
			}
			if ( ! $has_log ) {
				$additions[] = "define( 'WP_DEBUG_LOG', true );";
			}
			if ( ! $has_display ) {
				$additions[] = "define( 'WP_DEBUG_DISPLAY', false );";
			}

			if ( ! empty( $additions ) ) {
				if ( $has_debug ) {
					$extra_lines = array();
					if ( ! $has_log ) {
						$extra_lines[] = "define( 'WP_DEBUG_LOG', true );";
					}
					if ( ! $has_display ) {
						$extra_lines[] = "define( 'WP_DEBUG_DISPLAY', false );";
					}
					if ( ! empty( $extra_lines ) ) {
						$content = preg_replace(
							'/(define\s*\(\s*([\'"])WP_DEBUG\2\s*,\s*[^)]+\s*\)\s*;)/i',
							"$1\n" . implode( "\n", $extra_lines ),
							$content
						);
					}
				} else {
					$block = implode( "\n", $additions ) . "\n";
					if ( preg_match( '/(\/\*\s*That\'s all[^*]*\*\/)/i', $content ) ) {
						$content = preg_replace( '/(\/\*\s*That\'s all[^*]*\*\/)/i', $block . "$1", $content, 1 );
					} elseif ( preg_match( '/(require_once.*wp-settings\.php)/i', $content ) ) {
						$content = preg_replace( '/(require_once.*wp-settings\.php)/i', $block . "$1", $content, 1 );
					} else {
						$content .= "\n" . $block;
					}
				}
			}
		}

		$written = @file_put_contents( $file_path, $content );
		if ( false === $written ) {
			return array(
				'success' => false,
				'message' => 'خطا در ذخیره‌سازی تغییرات در فایل wp-config.php.',
			);
		}

		self::log(
			sprintf( 'تنظیمات WP_DEBUG در wp-config.php با موفقیت به وضعیت [%s] تغییر یافت.', $enable ? 'ON' : 'OFF' ),
			'CONFIG_UPDATE'
		);

		return array(
			'success' => true,
			'message' => $enable
				? 'ثابت‌های WP_DEBUG و WP_DEBUG_LOG با موفقیت در فایل wp-config.php فعال شدند.'
				: 'ثابت‌های WP_DEBUG با موفقیت در فایل wp-config.php غیرفعال شدند.',
		);
	}

	/**
	 * AJAX Handler: Safely toggle WP_DEBUG in wp-config.php.
	 */
	public function ajax_toggle_wp_config_debug(): void {
		check_ajax_referer( 'singo_debug_nonce', 'security' );

		if ( ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی ناکافی است.' ) );
		}

		$enable = isset( $_POST['enable'] ) && filter_var( wp_unslash( $_POST['enable'] ), FILTER_VALIDATE_BOOLEAN );
		$result = self::modify_wp_config_debug( $enable );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => $result['message'],
				'status'  => $enable,
			) );
		} else {
			wp_send_json_error( array(
				'message' => $result['message'],
			) );
		}
	}

	/**
	 * AJAX Handler: Instantly toggle Singo system debug option.
	 */
	public function ajax_toggle_system_debug(): void {
		check_ajax_referer( 'singo_debug_nonce', 'security' );

		if ( ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی ناکافی است.' ) );
		}

		$enable  = isset( $_POST['enable'] ) && filter_var( wp_unslash( $_POST['enable'] ), FILTER_VALIDATE_BOOLEAN );
		$updated = singo_tools_update_option( 'enable_system_debug', $enable );

		wp_send_json_success( array(
			'message' => $enable ? 'سرویس پایش سیستم فعال شد.' : 'سرویس پایش سیستم غیرفعال شد.',
			'status'  => $enable,
		) );
	}
}
