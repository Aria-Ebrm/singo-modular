<?php
declare(strict_types=1);

/**
 * Singo Tools - Modern System Debugger & Health Inspector (Optionino Design System)
 *
 * Dedicated submenu view conforming 100% to the Optionino design system,
 * signature colors (#ad6141 / #b16241), card containers, and KPI widgets.
 *
 * @package SingoTools\Modules\Debug
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$debug_enabled = singo_tools_get_option( 'enable_system_debug', false );
$debug_enabled = filter_var( $debug_enabled, FILTER_VALIDATE_BOOLEAN );
$admin_email   = (string) get_option( 'admin_email' );

// Server Environment Metrics
$php_version   = PHP_VERSION;
$wp_version    = get_bloginfo( 'version' );
$mysql_version = $wpdb->db_version();
$mem_limit     = ini_get( 'memory_limit' ) ?: '256M';
$mem_usage_raw = memory_get_usage( true );
$mem_usage_fmt = size_format( $mem_usage_raw, 2 );
$max_exec_time = ( ini_get( 'max_execution_time' ) ?: '30' ) . ' ثانیه';

// Calculate RAM percentage if numerical limit available
$limit_bytes    = wp_convert_hr_to_bytes( $mem_limit );
$mem_percent    = ( $limit_bytes > 0 ) ? min( 100, round( ( $mem_usage_raw / $limit_bytes ) * 100 ) ) : 0;
$ram_fill_color = $mem_percent > 85 ? '#dc2626' : ( $mem_percent > 65 ? '#d97706' : '#16a34a' );

// System Extensions
$curl_active  = function_exists( 'curl_version' );
$mbstring_act = extension_loaded( 'mbstring' );
$json_active  = function_exists( 'json_encode' );
$bcmath_act   = extension_loaded( 'bcmath' );

// WP Constants
$wp_debug         = defined( 'WP_DEBUG' ) && WP_DEBUG;
$wp_debug_log     = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
$wp_debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
$script_debug     = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
$cron_disabled    = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
$is_config_writable = Singo_Tools_Debugger::is_wp_config_writable();

// WooCommerce & HPOS
$wc_active   = class_exists( 'WooCommerce' );
$wc_version  = defined( 'WC_VERSION' ) ? WC_VERSION : '';
$hpos_active = false;
if ( $wc_active && class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
	$hpos_active = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

// Jalali Conflicts Check
$jalali_conflicts = array();
if ( class_exists( 'WP_Parsidate' ) || defined( 'WPP_VERSION' ) ) {
	$jalali_conflicts[] = 'WP-Parsidate';
}
if ( function_exists( 'wpp_init' ) || class_exists( 'wp_jalali' ) ) {
	$jalali_conflicts[] = 'WP-Jalali';
}

$singo_meta = Singo_Tools_Debugger::get_log_metadata( 'singo' );
?>

<!-- Self-Contained Optionino Design System Styles -->
<style>
:root {
	--opt-primary: #ad6141;
	--opt-primary-hover: #8e4c31;
	--opt-primary-light: rgba(173, 97, 65, 0.08);
	--opt-primary-border: rgba(173, 97, 65, 0.25);
	--opt-success: #16a34a;
	--opt-success-bg: #f0fdf4;
	--opt-warning: #d97706;
	--opt-warning-bg: #fffbeb;
	--opt-danger: #dc2626;
	--opt-danger-bg: #fef2f2;
	--opt-info: #0284c7;
	--opt-info-bg: #f0f9ff;
	--opt-gray-50: #f8fafc;
	--opt-gray-100: #f1f5f9;
	--opt-gray-200: #e2e8f0;
	--opt-gray-300: #cbd5e1;
	--opt-gray-500: #64748b;
	--opt-gray-700: #334155;
	--opt-gray-800: #1e293b;
	--opt-gray-900: #0f172a;
}

.singo-debug-wrap,
.singo-debug-wrap * {
	box-sizing: border-box !important;
}

.singo-debug-wrap,
.singo-debug-wrap *:not(.dashicons) {
	font-family: inherit !important;
}

.singo-debug-wrap .dashicons,
.dashicons {
	font-family: dashicons !important;
	font-style: normal !important;
	font-weight: 400 !important;
}

.singo-debug-wrap {
	direction: rtl;
	text-align: right;
	max-width: 1200px;
	margin: 15px auto 40px auto;
}

/* Page Header */
.singo-debug-header {
	background: #ffffff;
	border: 1px solid var(--opt-gray-200);
	border-radius: 12px;
	padding: 18px 24px;
	margin-bottom: 20px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.singo-debug-header-info {
	display: flex;
	align-items: center;
	gap: 16px;
}

.singo-debug-header-title {
	margin: 0 0 4px 0 !important;
	font-size: 20px !important;
	font-weight: 800 !important;
	color: var(--opt-gray-800) !important;
	line-height: 1.3 !important;
}

.singo-debug-header-desc {
	margin: 0 !important;
	font-size: 13px !important;
	color: var(--opt-gray-500) !important;
}

/* Notice Banner */
.singo-debug-notice {
	border-radius: 8px;
	padding: 12px 18px;
	margin-bottom: 20px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	font-size: 13px;
	font-weight: 600;
	border: 1px solid transparent;
	border-right-width: 4px;
}
.singo-debug-notice--info {
	background: var(--opt-info-bg);
	border-color: #bae6fd;
	border-right-color: var(--opt-info);
	color: #0369a1;
}
.singo-debug-notice--warning {
	background: var(--opt-warning-bg);
	border-color: #fde68a;
	border-right-color: var(--opt-warning);
	color: #92400e;
}

/* Card Container */
.singo-debug-card {
	background: #ffffff;
	border: 1px solid var(--opt-gray-200);
	border-radius: 12px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
	margin-bottom: 20px;
	overflow: hidden;
}

.singo-debug-card-header {
	padding: 14px 20px;
	background: var(--opt-gray-50);
	border-bottom: 1px solid var(--opt-gray-200);
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
}

.singo-debug-card-title {
	font-size: 15px;
	font-weight: 700;
	color: var(--opt-gray-800);
	margin: 0;
	display: flex;
	align-items: center;
	gap: 8px;
}

.singo-debug-card-title .dashicons {
	color: var(--opt-primary);
	font-size: 19px;
	width: 19px;
	height: 19px;
	line-height: 19px;
}

.singo-debug-card-body {
	padding: 20px;
}

/* Optionino Switcher */
.optionino-switch-wrapper {
	position: relative;
	display: inline-block;
	width: 50px;
	height: 26px;
	flex-shrink: 0;
}
.optionino-switch-wrapper input {
	opacity: 0;
	width: 0;
	height: 0;
	position: absolute;
}
.optionino-switch-slider {
	position: absolute;
	cursor: pointer;
	top: 0; left: 0; right: 0; bottom: 0;
	background-color: var(--opt-gray-300);
	transition: .25s ease-in-out;
	border-radius: 34px;
}
.optionino-switch-slider:before {
	position: absolute;
	content: "";
	height: 20px;
	width: 20px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: .25s ease-in-out;
	border-radius: 50%;
	box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.optionino-switch-wrapper input:checked + .optionino-switch-slider {
	background-color: var(--opt-primary) !important;
}
.optionino-switch-wrapper input:checked + .optionino-switch-slider:before {
	transform: translateX(24px);
}

/* Badges */
.opt-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 3px 8px;
	border-radius: 5px;
	font-size: 11.5px;
	font-weight: 700;
	line-height: 1.4;
	border: 1px solid transparent;
}
.opt-badge--primary {
	background: var(--opt-primary-light);
	color: var(--opt-primary);
	border-color: var(--opt-primary-border);
}
.opt-badge--success {
	background: var(--opt-success-bg);
	color: var(--opt-success);
	border-color: #bbf7d0;
}
.opt-badge--warning {
	background: var(--opt-warning-bg);
	color: var(--opt-warning);
	border-color: #fde68a;
}
.opt-badge--danger {
	background: var(--opt-danger-bg);
	color: var(--opt-danger);
	border-color: #fecaca;
}
.opt-badge--info {
	background: var(--opt-info-bg);
	color: var(--opt-info);
	border-color: #bae6fd;
}
.opt-badge--neutral {
	background: var(--opt-gray-100);
	color: var(--opt-gray-700);
	border-color: var(--opt-gray-200);
}

/* KPI Cards Grid */
.opt-kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
	gap: 16px;
	margin-bottom: 16px;
}
.opt-kpi-card {
	background: var(--opt-gray-50);
	border: 1px solid var(--opt-gray-200);
	border-radius: 10px;
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 8px;
	transition: all 0.2s ease;
}
.opt-kpi-card:hover {
	border-color: var(--opt-primary-border);
	background: #ffffff;
	box-shadow: 0 4px 12px rgba(173, 97, 65, 0.06);
}
.opt-kpi-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.opt-kpi-label {
	font-size: 12.5px;
	font-weight: 600;
	color: var(--opt-gray-500);
	display: flex;
	align-items: center;
	gap: 6px;
}
.opt-kpi-label .dashicons {
	font-size: 16px;
	width: 16px;
	height: 16px;
	color: var(--opt-primary);
}
.opt-kpi-value {
	font-size: 17px;
	font-weight: 800;
	color: var(--opt-gray-800);
}
.opt-kpi-subtext {
	font-size: 11.5px;
	color: var(--opt-gray-500);
}

/* Progress Bar */
.opt-progress-bar {
	width: 100%;
	height: 7px;
	background: var(--opt-gray-200);
	border-radius: 99px;
	overflow: hidden;
	margin-top: 2px;
}
.opt-progress-fill {
	height: 100%;
	border-radius: 99px;
	transition: width 0.3s ease;
}

/* Action Buttons */
.opt-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	height: 36px;
	padding: 0 16px;
	border-radius: 6px;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.15s ease;
	text-decoration: none;
	border: 1px solid transparent;
}
.opt-btn .dashicons {
	font-size: 16px;
	width: 16px;
	height: 16px;
	line-height: 16px;
}
.opt-btn--primary {
	background: var(--opt-primary);
	color: #ffffff;
	border-color: var(--opt-primary);
}
.opt-btn--primary:hover {
	background: var(--opt-primary-hover);
	color: #ffffff;
}
.opt-btn--secondary {
	background: #ffffff;
	color: var(--opt-gray-700);
	border-color: var(--opt-gray-300);
}
.opt-btn--secondary:hover {
	background: var(--opt-gray-50);
	color: var(--opt-gray-900);
	border-color: var(--opt-primary);
}
.opt-btn--danger {
	background: #ffffff;
	color: var(--opt-danger);
	border-color: #fecaca;
}
.opt-btn--danger:hover {
	background: var(--opt-danger-bg);
	border-color: var(--opt-danger);
}

/* Copy Button Widget */
.singo-copy-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	padding: 5px 12px;
	background: #ffffff;
	color: var(--opt-primary);
	border: 1px solid var(--opt-primary-border);
	border-radius: 6px;
	font-size: 12px;
	font-weight: 700;
	cursor: pointer;
	transition: all 0.15s ease;
}
.singo-copy-btn:hover {
	background: var(--opt-primary-light);
	border-color: var(--opt-primary);
}
.singo-copy-btn .dashicons {
	font-size: 14px;
	width: 14px;
	height: 14px;
	line-height: 14px;
}
.singo-copy-btn.singo-copied {
	background: var(--opt-success-bg);
	color: var(--opt-success);
	border-color: #bbf7d0;
}

/* Floating Toast */
.singo-toast-container {
	position: fixed;
	bottom: 24px;
	left: 24px;
	z-index: 999999;
	display: flex;
	flex-direction: column;
	gap: 10px;
	pointer-events: none;
}
.singo-toast {
	pointer-events: auto;
	min-width: 260px;
	background: #ffffff;
	color: var(--opt-gray-800);
	border-radius: 8px;
	padding: 12px 16px;
	box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
	border-right: 4px solid var(--opt-primary);
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 13.5px;
	font-weight: 600;
	direction: rtl;
	animation: toastIn 0.25s ease-out;
}
.singo-toast--success { border-right-color: var(--opt-success); }
.singo-toast--warning { border-right-color: var(--opt-warning); }
.singo-toast--danger  { border-right-color: var(--opt-danger); }
.singo-toast--info    { border-right-color: var(--opt-info); }
@keyframes toastIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

/* Terminal Window */
.singo-terminal {
	border-radius: 8px;
	overflow: hidden;
	border: 1px solid var(--opt-gray-800);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}
.singo-terminal-header {
	background: var(--opt-gray-800);
	color: #f8fafc;
	padding: 10px 16px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	border-bottom: 1px solid #334155;
}
.singo-terminal-body {
	background: var(--opt-gray-900);
	color: #38bdf8;
	padding: 16px;
	font-family: 'Courier New', Courier, monospace;
	font-size: 13px;
	line-height: 1.65;
	height: 380px;
	overflow-y: auto;
	white-space: pre-wrap;
	word-break: break-all;
	margin: 0;
	direction: ltr;
	text-align: left;
}
</style>

<div class="singo-debug-wrap">

	<!-- Branded Page Header -->
	<div class="singo-debug-header">
		<div class="singo-debug-header-info">
			<img src="<?php echo esc_url( SINGO_TOOLS_URL . 'assets/SingoLogo.svg' ); ?>" alt="Singo Tools" style="height: 38px; width: auto;">
			<div>
				<h1 class="singo-debug-header-title">عیب‌یابی، سلامت و پایش هوشمند سیستم</h1>
				<p class="singo-debug-header-desc">نظارت لحظه‌ای بر سلامت سرور، ثبت خطاهای مهلک، بازرسی پایگاه‌داده و مدیریت لاگ‌ها</p>
			</div>
		</div>
		<div>
			<span class="opt-badge opt-badge--primary">جعبه ابزار سینگو <?php echo esc_html( SINGO_TOOLS_VERSION ); ?></span>
		</div>
	</div>

	<!-- WP_DEBUG Notice Container -->
	<div id="singo-wp-debug-banner-wrap">
		<?php if ( $wp_debug ) : ?>
			<div class="singo-debug-notice singo-debug-notice--info" id="singo-wp-debug-notice">
				<div style="display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-info" style="color: var(--opt-info);"></span>
					<span>ثابت <strong>WP_DEBUG</strong> روی هاست در وضعیت <strong style="color:var(--opt-info);">فعال (ON)</strong> قرار دارد. خطاهای هسته وردپرس در فایل لاگ ثبت می‌شوند.</span>
				</div>
				<div style="display: flex; align-items: center; gap: 8px;">
					<span class="opt-badge opt-badge--info">Debug Mode: ON</span>
					<?php if ( $is_config_writable ) : ?>
						<button type="button" class="opt-btn opt-btn--secondary singo-toggle-wp-config-action" data-action="disable" style="padding: 4px 10px; font-size: 11.5px; gap: 4px;">
							<span class="dashicons dashicons-no-alt"></span> غیرفعال‌سازی در wp-config
						</button>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="singo-debug-notice singo-debug-notice--warning" id="singo-wp-debug-notice">
				<div style="display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-warning" style="color: var(--opt-warning);"></span>
					<span>ثابت <code>WP_DEBUG</code> در فایل <code>wp-config.php</code> غیرفعال است. برای ثبت کامل خطاهای هسته وردپرس می‌توانید آن را فعال نمایید.</span>
				</div>
				<div style="display: flex; align-items: center; gap: 8px;">
					<?php if ( $is_config_writable ) : ?>
						<button type="button" class="opt-btn opt-btn--primary singo-toggle-wp-config-action" data-action="enable" style="gap: 6px; padding: 6px 14px; font-size: 12px; box-shadow: 0 2px 6px rgba(173,97,65,0.2);">
							<span class="dashicons dashicons-admin-generic"></span> فعال‌سازی خودکار در wp-config.php
						</button>
					<?php endif; ?>
					<button type="button" class="singo-copy-btn" data-copy="define('WP_DEBUG', true); define('WP_DEBUG_LOG', true); define('WP_DEBUG_DISPLAY', false);" data-toast="کد فعال‌سازی دیباگ کپی شد!">
						<span class="dashicons dashicons-clipboard"></span> کپی دستی کد
					</button>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Card 1: Master Service Switcher -->
	<div class="singo-debug-card">
		<div class="singo-debug-card-header">
			<h2 class="singo-debug-card-title">
				<span class="dashicons dashicons-shield"></span>
				سرویس پایش و ثبت خطاهای مهلک
			</h2>
			<span id="singo-status-badge" class="opt-badge <?php echo $debug_enabled ? 'opt-badge--success' : 'opt-badge--neutral'; ?>">
				<?php echo $debug_enabled ? 'پایش فعال ✓' : 'پایش غیرفعال'; ?>
			</span>
		</div>
		<div class="singo-debug-card-body">
			<div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
				<div style="flex: 1; min-width: 280px;">
					<strong style="font-size: 14.5px; color: var(--opt-gray-800); display: block; margin-bottom: 6px;">
						وضعیت سرویس پایش و ثبت خطاهای سیستم
					</strong>
					<p style="margin: 0; font-size: 13px; color: var(--opt-gray-500); line-height: 1.7;">
						با فعال بودن این بخش، تمامی رخدادهای سیستمی در لاگ ثبت شده و در صورت وقوع خطای کشنده (Fatal Error)، یک اعلان ایمیلی فوری به مدیر سایت (<code><?php echo esc_html( $admin_email ); ?></code>) ارسال می‌گردد.
					</p>
				</div>
				<div class="optionino-switch-wrapper">
					<input type="hidden" name="enable_system_debug" value="off">
					<input type="checkbox" id="enable_system_debug" name="enable_system_debug" value="on" <?php checked( $debug_enabled, true ); ?>>
					<label class="optionino-switch-slider" for="enable_system_debug"></label>
				</div>
			</div>
		</div>
	</div>

	<!-- Debug Details Body -->
	<div id="singo-debug-body" style="<?php echo $debug_enabled ? '' : 'display: none;'; ?>">

		<!-- Card 2: Server & Environment Metrics (KPI Grid) -->
		<div class="singo-debug-card">
			<div class="singo-debug-card-header">
				<h2 class="singo-debug-card-title">
					<span class="dashicons dashicons-performance"></span>
					مشخصات محیط سرور، وردپرس و ووکامرس
				</h2>
				<span class="opt-badge opt-badge--primary">وردپرس <?php echo esc_html( $wp_version ); ?></span>
			</div>
			<div class="singo-debug-card-body">
				
				<!-- KPI Cards Grid -->
				<div class="opt-kpi-grid">
					
					<!-- KPI 1: RAM Memory -->
					<div class="opt-kpi-card">
						<div class="opt-kpi-header">
							<span class="opt-kpi-label"><span class="dashicons dashicons-dashboard"></span> حافظه رم (RAM)</span>
							<span class="opt-badge opt-badge--neutral"><?php echo (int) $mem_percent; ?>% مصرف</span>
						</div>
						<div class="opt-kpi-value"><?php echo esc_html( $mem_usage_fmt ); ?></div>
						<div class="opt-progress-bar">
							<div class="opt-progress-fill" style="width: <?php echo (int) $mem_percent; ?>%; background: <?php echo esc_attr( $ram_fill_color ); ?>;"></div>
						</div>
						<div class="opt-kpi-subtext">سقف مجاز: <?php echo esc_html( $mem_limit ); ?> | زمان اجرا: <?php echo esc_html( $max_exec_time ); ?></div>
					</div>

					<!-- KPI 2: PHP & MySQL -->
					<div class="opt-kpi-card">
						<div class="opt-kpi-header">
							<span class="opt-kpi-label"><span class="dashicons dashicons-admin-generic"></span> زبان و دیتابیس</span>
							<span class="opt-badge opt-badge--success">سازگار (PHP 8+) ✓</span>
						</div>
						<div class="opt-kpi-value">PHP <?php echo esc_html( $php_version ); ?></div>
						<div class="opt-kpi-subtext">پایگاه‌داده MySQL: <strong><?php echo esc_html( $mysql_version ); ?></strong></div>
					</div>

					<!-- KPI 3: WooCommerce & HPOS -->
					<div class="opt-kpi-card">
						<div class="opt-kpi-header">
							<span class="opt-kpi-label"><span class="dashicons dashicons-cart"></span> فروشگاه ووکامرس</span>
							<?php if ( $wc_active ) : ?>
								<span class="opt-badge opt-badge--success">فعال ✓</span>
							<?php else : ?>
								<span class="opt-badge opt-badge--neutral">نصب نیست</span>
							<?php endif; ?>
						</div>
						<div class="opt-kpi-value"><?php echo $wc_active ? 'نسخه ' . esc_html( $wc_version ) : 'غیرفعال'; ?></div>
						<div class="opt-kpi-subtext">
							دیتابیس جدید HPOS: <?php echo $hpos_active ? '<strong style="color:var(--opt-success);">فعال (سفارشات پرسرعت) ✓</strong>' : '<span style="color:var(--opt-gray-500);">حالت سنتی</span>'; ?>
						</div>
					</div>

					<!-- KPI 4: WP Debug Constants -->
					<div class="opt-kpi-card">
						<div class="opt-kpi-header">
							<span class="opt-kpi-label"><span class="dashicons dashicons-admin-tools"></span> ثابت‌های دیباگ</span>
							<span id="singo-kpi-debug-badge" class="opt-badge <?php echo $wp_debug ? 'opt-badge--info' : 'opt-badge--neutral'; ?>">
								<?php echo $wp_debug ? 'Debug ON' : 'Debug OFF'; ?>
							</span>
						</div>
						<div style="display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px;">
							<span id="singo-kpi-log-badge" class="opt-badge <?php echo $wp_debug_log ? 'opt-badge--success' : 'opt-badge--neutral'; ?>">Log: <?php echo $wp_debug_log ? 'On' : 'Off'; ?></span>
							<span class="opt-badge <?php echo $wp_debug_display ? 'opt-badge--warning' : 'opt-badge--neutral'; ?>">Display: <?php echo $wp_debug_display ? 'On' : 'Off'; ?></span>
							<span class="opt-badge <?php echo $script_debug ? 'opt-badge--info' : 'opt-badge--neutral'; ?>">Script: <?php echo $script_debug ? 'On' : 'Off'; ?></span>
						</div>
						<div class="opt-kpi-subtext">کرون خودکار وردپرس: <?php echo $cron_disabled ? '<span style="color:var(--opt-warning);">غیرفعال (سرور اختصاصی)</span>' : 'فعال (پیش‌فرض)'; ?></div>
					</div>

				</div>

				<!-- Extensions Summary Ribbon -->
				<div style="background: var(--opt-gray-50); border: 1px solid var(--opt-gray-200); padding: 12px 16px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
					<div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
						<strong style="color: var(--opt-gray-700);">ماژول‌های ضروری PHP:</strong>
						<span>cURL: <?php echo $curl_active ? '<strong style="color:var(--opt-success);">✓</strong>' : '<strong style="color:var(--opt-danger);">✗</strong>'; ?></span>
						<span>mbstring: <?php echo $mbstring_act ? '<strong style="color:var(--opt-success);">✓</strong>' : '<strong style="color:var(--opt-danger);">✗</strong>'; ?></span>
						<span>BCMath: <?php echo $bcmath_act ? '<strong style="color:var(--opt-success);">✓</strong>' : '<strong style="color:var(--opt-danger);">✗</strong>'; ?></span>
						<span>JSON: <?php echo $json_active ? '<strong style="color:var(--opt-success);">✓</strong>' : '<strong style="color:var(--opt-danger);">✗</strong>'; ?></span>
					</div>
					<div>
						<strong style="color: var(--opt-gray-700);">تداخل پلاگین‌های تقویم:</strong>
						<?php if ( ! empty( $jalali_conflicts ) ) : ?>
							<span class="opt-badge opt-badge--warning"><?php echo esc_html( implode( ', ', $jalali_conflicts ) ); ?> فعال است</span>
						<?php else : ?>
							<span class="opt-badge opt-badge--success">بدون تداخل ✓</span>
						<?php endif; ?>
					</div>
				</div>

			</div>
		</div>

		<!-- Card 3: Diagnostic Tests & Database Health Inspector -->
		<div class="singo-debug-card">
			<div class="singo-debug-card-header">
				<h2 class="singo-debug-card-title">
					<span class="dashicons dashicons-database"></span>
					بازرس سلامت دیتابیس و تست‌های تشخیصی (Health Inspector)
				</h2>
			</div>
			<div class="singo-debug-card-body">
				
				<!-- Action Buttons Toolbar -->
				<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px;">
					<button type="button" id="singo-inspect-db-btn" class="opt-btn opt-btn--secondary">
						<span class="dashicons dashicons-search"></span> بررسی زنده سلامت دیتابیس
					</button>
					<button type="button" id="singo-repair-db-btn" class="opt-btn opt-btn--primary">
						<span class="dashicons dashicons-admin-tools"></span> بازسازی و تعمیر خودکار گزینه‌ها
					</button>
					<button type="button" id="singo-test-log-btn" class="opt-btn opt-btn--secondary">
						<span class="dashicons dashicons-edit"></span> ثبت لاگ آزمایشی (Test Log)
					</button>
					<button type="button" id="singo-test-email-btn" class="opt-btn opt-btn--secondary">
						<span class="dashicons dashicons-email-alt"></span> تست ارسال ایمیل هشدار
					</button>
				</div>

				<!-- Structured Checks Checklist Container -->
				<div id="singo-db-checks-container" style="display: flex; flex-direction: column; gap: 8px;"></div>

				<!-- Collapsible Raw Data Accordion -->
				<div id="singo-raw-data-box" style="display: none; margin-top: 15px;">
					<details style="background: var(--opt-gray-50); border: 1px solid var(--opt-gray-200); border-radius: 8px; padding: 12px 16px;">
						<summary style="cursor: pointer; font-weight: 700; font-size: 13px; color: var(--opt-gray-700);">
							مشاهده داده‌های خام فنی دیتابیس (JSON Data)
						</summary>
						<pre id="singo-db-raw-json" style="margin-top: 10px; background: var(--opt-gray-900); color: #38bdf8; padding: 14px; border-radius: 6px; font-family: monospace; font-size: 12px; line-height: 1.6; max-height: 250px; overflow-y: auto; direction: ltr; text-align: left;"></pre>
					</details>
				</div>

			</div>
		</div>

		<!-- Card 4: Advanced Live Interactive Log Terminal -->
		<div class="singo-debug-card">
			<div class="singo-debug-card-header">
				<h2 class="singo-debug-card-title">
					<span class="dashicons dashicons-editor-code"></span>
					کنسول زنده لاگ‌های سیستم
				</h2>

				<!-- Real-time Polling Toggle -->
				<label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--opt-gray-700); cursor: pointer; user-select: none;">
					<input type="checkbox" id="singo-auto-refresh-toggle" style="margin: 0;">
					<span>به‌روزرسانی خودکار (۵ ثانیه)</span>
					<span id="singo-poll-indicator" style="display: none; width: 8px; height: 8px; background: var(--opt-success); border-radius: 50%; box-shadow: 0 0 6px var(--opt-success);"></span>
				</label>
			</div>
			<div class="singo-debug-card-body">
				
				<!-- Log Toolbar Filters -->
				<div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; background: var(--opt-gray-50); padding: 12px 16px; border-radius: 8px; border: 1px solid var(--opt-gray-200); margin-bottom: 14px;">
					
					<!-- Source Selector -->
					<div style="display: flex; align-items: center; gap: 12px;">
						<strong style="font-size: 13px; color: var(--opt-gray-800);">منبع لاگ:</strong>
						<label style="cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 4px;">
							<input type="radio" name="singo_log_source" value="singo" checked style="margin: 0;">
							لاگ سینگو (<code>singo-debug.log</code>)
						</label>
						<label style="cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 4px;">
							<input type="radio" name="singo_log_source" value="wp" style="margin: 0;">
							لاگ وردپرس (<code>debug.log</code>)
						</label>
					</div>

					<!-- Level Dropdown -->
					<div style="display: flex; align-items: center; gap: 6px;">
						<strong style="font-size: 13px; color: var(--opt-gray-800);">سطح:</strong>
						<select id="singo-log-level-filter" style="height: 34px; font-size: 13px; border-radius: 6px; border-color: var(--opt-gray-300);">
							<option value="ALL">همه سطوح (All)</option>
							<option value="FATAL_ERROR">خطای مهلک (Fatal Error)</option>
							<option value="WARNING">هشدارها (Warning)</option>
							<option value="NOTICE">نکات (Notice)</option>
							<option value="DEPRECATED">منسوخ‌شده (Deprecated)</option>
						</select>
					</div>

					<!-- Search Box -->
					<div style="display: flex; align-items: center; gap: 6px; flex: 1; min-width: 200px;">
						<input type="text" id="singo-log-search-query" placeholder="جستجوی متنی لحظه‌ای در لاگ‌ها..." style="height: 34px; width: 100%; font-size: 13px; border-radius: 6px; border: 1px solid var(--opt-gray-300); padding: 0 10px;">
					</div>

					<!-- Manual Refresh Button -->
					<button type="button" id="singo-fetch-logs-btn" class="opt-btn opt-btn--secondary" style="height: 34px;">
						<span class="dashicons dashicons-update"></span> به‌روزرسانی
					</button>

				</div>

				<!-- Metadata Status Chips Bar -->
				<div style="display: flex; gap: 16px; font-size: 12.5px; color: var(--opt-gray-500); margin-bottom: 12px; background: var(--opt-gray-100); padding: 8px 14px; border-radius: 6px; border: 1px solid var(--opt-gray-200); flex-wrap: wrap;">
					<div>حجم فایل: <strong id="singo-meta-size" style="color:var(--opt-gray-800);"><?php echo esc_html( $singo_meta['size_fmt'] ); ?></strong></div>
					<div>تعداد خطوط: <strong id="singo-meta-lines" style="color:var(--opt-gray-800);"><?php echo esc_html( (string) $singo_meta['lines'] ); ?></strong></div>
					<div>آخرین تغییر: <strong id="singo-meta-mtime" style="color:var(--opt-gray-800);"><?php echo esc_html( $singo_meta['mtime'] ); ?></strong></div>
				</div>

				<!-- Terminal Console Box -->
				<div class="singo-terminal">
					<div class="singo-terminal-header">
						<div style="font-family: monospace; font-size: 12px; color: #94a3b8; display: flex; align-items: center; gap: 8px; direction: ltr;">
							<span class="dashicons dashicons-terminal" style="color: #38bdf8; font-size: 16px; width: 16px; height: 16px; line-height: 16px; margin: 0;"></span>
							terminal://singo-debug-tail
						</div>
						
						<!-- High Visibility One-Click Copy Button using Global UI Toolkit -->
						<button type="button" class="singo-copy-btn" data-copy-target="#singo-log-console" data-toast="متن کامل لاگ با موفقیت در کلیپ‌بورد کپی شد!">
							<span class="dashicons dashicons-clipboard"></span> کپی متن لاگ
						</button>
					</div>

					<pre id="singo-log-console" class="singo-terminal-body">در حال بارگذاری لاگ‌ها...</pre>
				</div>

				<!-- Bottom Action Buttons -->
				<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; flex-wrap: wrap; gap: 10px;">
					<button type="button" id="singo-download-log-btn" class="opt-btn opt-btn--secondary">
						<span class="dashicons dashicons-download"></span> دانلود فایل لاگ
					</button>

					<button type="button" id="singo-clear-debug-logs-btn" class="opt-btn opt-btn--danger">
						<span class="dashicons dashicons-trash"></span> پاکسازی کامل فایل لاگ
					</button>
				</div>

			</div>
		</div>

	</div>

</div>

<script>
jQuery(document).ready(function($) {
	var nonce = '<?php echo wp_create_nonce( 'singo_debug_nonce' ); ?>';

	var isWpDebugActive = <?php echo $wp_debug ? 'true' : 'false'; ?>;
	var isConfigWritable = <?php echo $is_config_writable ? 'true' : 'false'; ?>;

	// Toggle Debug Body & Status Badge + Instant AJAX Save & WP_DEBUG Prompt
	$(document).on('change', '#enable_system_debug', function() {
		var isChecked = $(this).is(':checked');
		var $badge = $('#singo-status-badge');
		if (isChecked) {
			$('#singo-debug-body').slideDown(200);
			$badge.removeClass('opt-badge--neutral').addClass('opt-badge--success').text('پایش فعال ✓');
		} else {
			$('#singo-debug-body').slideUp(200);
			$badge.removeClass('opt-badge--success').addClass('opt-badge--neutral').text('پایش غیرفعال');
		}

		// Instant AJAX persistence for the service switch
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'singo_toggle_system_debug',
				security: nonce,
				enable: isChecked
			},
			success: function(res) {
				if (res.success) {
					showToast(res.data.message, 'success');
					// If user just enabled monitoring and WP_DEBUG is not yet active, offer auto-setup
					if (isChecked && !isWpDebugActive && isConfigWritable) {
						setTimeout(function() {
							if (confirm('سرویس پایش خطاهای سینگو فعال شد.\n\nآیا مایلید تنظیمات WP_DEBUG نیز به صورت خودکار در فایل wp-config.php فعال شود تا خطاهای هسته وردپرس نیز کامل ثبت گردند؟')) {
								triggerWpConfigToggle(true);
							}
						}, 350);
					}
				}
			}
		});
	});

	// Trigger WP_DEBUG toggle in wp-config.php via AJAX
	function triggerWpConfigToggle(enable) {
		showToast(enable ? 'در حال فعال‌سازی WP_DEBUG در wp-config.php...' : 'در حال غیرفعال‌سازی WP_DEBUG...', 'info');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'singo_toggle_wp_config_debug',
				security: nonce,
				enable: enable
			},
			success: function(res) {
				if (res.success) {
					showToast(res.data.message, 'success');
					isWpDebugActive = enable;
					updateWpDebugBanner(enable);
				} else {
					showToast(res.data.message || 'خطا در ویرایش فایل wp-config.php.', 'danger');
				}
			},
			error: function() {
				showToast('خطا در برقراری ارتباط با سرور برای ویرایش wp-config.php.', 'danger');
			}
		});
	}

	// Update WP_DEBUG notice banner dynamically without page reload
	function updateWpDebugBanner(enable) {
		var $wrap = $('#singo-wp-debug-banner-wrap');
		if (enable) {
			var html = '<div class="singo-debug-notice singo-debug-notice--info" id="singo-wp-debug-notice">' +
				'<div style="display: flex; align-items: center; gap: 8px;">' +
					'<span class="dashicons dashicons-info" style="color: var(--opt-info);"></span>' +
					'<span>ثابت <strong>WP_DEBUG</strong> روی هاست در وضعیت <strong style="color:var(--opt-info);">فعال (ON)</strong> قرار دارد. خطاهای هسته وردپرس در فایل لاگ ثبت می‌شوند.</span>' +
				'</div>' +
				'<div style="display: flex; align-items: center; gap: 8px;">' +
					'<span class="opt-badge opt-badge--info">Debug Mode: ON</span>' +
					(isConfigWritable ? '<button type="button" class="opt-btn opt-btn--secondary singo-toggle-wp-config-action" data-action="disable" style="padding: 4px 10px; font-size: 11.5px; gap: 4px;"><span class="dashicons dashicons-no-alt"></span> غیرفعال‌سازی در wp-config</button>' : '') +
				'</div>' +
			'</div>';
			$wrap.html(html);
			$('#singo-kpi-debug-badge').removeClass('opt-badge--neutral').addClass('opt-badge--info').text('Debug ON');
			$('#singo-kpi-log-badge').removeClass('opt-badge--neutral').addClass('opt-badge--success').text('Log: On');
		} else {
			var html = '<div class="singo-debug-notice singo-debug-notice--warning" id="singo-wp-debug-notice">' +
				'<div style="display: flex; align-items: center; gap: 8px;">' +
					'<span class="dashicons dashicons-warning" style="color: var(--opt-warning);"></span>' +
					'<span>ثابت <code>WP_DEBUG</code> در فایل <code>wp-config.php</code> غیرفعال است. برای ثبت کامل خطاهای هسته وردپرس می‌توانید آن را فعال نمایید.</span>' +
				'</div>' +
				'<div style="display: flex; align-items: center; gap: 8px;">' +
					(isConfigWritable ? '<button type="button" class="opt-btn opt-btn--primary singo-toggle-wp-config-action" data-action="enable" style="gap: 6px; padding: 6px 14px; font-size: 12px; box-shadow: 0 2px 6px rgba(173,97,65,0.2);"><span class="dashicons dashicons-admin-generic"></span> فعال‌سازی خودکار در wp-config.php</button>' : '') +
					'<button type="button" class="singo-copy-btn" data-copy="define(\'WP_DEBUG\', true); define(\'WP_DEBUG_LOG\', true); define(\'WP_DEBUG_DISPLAY\', false);" data-toast="کد فعال‌سازی دیباگ کپی شد!"><span class="dashicons dashicons-clipboard"></span> کپی دستی کد</button>' +
				'</div>' +
			'</div>';
			$wrap.html(html);
			$('#singo-kpi-debug-badge').removeClass('opt-badge--info').addClass('opt-badge--neutral').text('Debug OFF');
			$('#singo-kpi-log-badge').removeClass('opt-badge--success').addClass('opt-badge--neutral').text('Log: Off');
		}
	}

	// 1-Click Banner Action Button Click Event
	$(document).on('click', '.singo-toggle-wp-config-action', function(e) {
		e.preventDefault();
		var action = $(this).data('action');
		var enable = action === 'enable';
		if (!enable) {
			if (!confirm('آیا مایلید ثابت‌های WP_DEBUG در فایل wp-config.php غیرفعال شوند؟')) {
				return;
			}
		}
		triggerWpConfigToggle(enable);
	});

	// Toast Notification Helper
	function showToast(msg, type) {
		if (window.SingoUI && typeof window.SingoUI.toast === 'function') {
			window.SingoUI.toast(msg, type);
			return;
		}
		var $container = $('.singo-toast-container');
		if (!$container.length) {
			$container = $('<div class="singo-toast-container"></div>').appendTo('body');
		}
		var $t = $('<div class="singo-toast singo-toast--' + (type || 'success') + '"><span>' + msg + '</span></div>').appendTo($container);
		setTimeout(function() {
			$t.fadeOut(200, function() { $t.remove(); });
		}, 3000);
	}

	// Load Logs
	function loadLogs() {
		var $console = $('#singo-log-console');
		var source   = $('input[name="singo_log_source"]:checked').val();
		var level    = $('#singo-log-level-filter').val();
		var search   = $('#singo-log-search-query').val();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'singo_fetch_debug_logs',
				security: nonce,
				log_type: source,
				filter_level: level,
				search_query: search
			},
			success: function(res) {
				if (res.success && res.data) {
					var raw = res.data.content || 'هیچ لاگی در این سطح ثبت نشده است.';
					$console.text(raw);

					if (res.data.meta) {
						$('#singo-meta-size').text(res.data.meta.size_fmt || '-');
						$('#singo-meta-lines').text(res.data.meta.lines || 0);
						$('#singo-meta-mtime').text(res.data.meta.mtime || '-');
					}
				} else {
					$console.text('خطا در دریافت لاگ‌ها.');
				}
			},
			error: function() {
				$console.text('خطا در برقراری ارتباط با سرور.');
			}
		});
	}

	// Initial Load
	loadLogs();

	// Manual Refresh & Filter Changes
	$('#singo-fetch-logs-btn').on('click', loadLogs);
	$(document).on('change', 'input[name="singo_log_source"], #singo-log-level-filter', loadLogs);

	// Debounced Search
	var searchTimer;
	$('#singo-log-search-query').on('input', function() {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(loadLogs, 300);
	});

	// Live Auto-Refresh Polling
	var pollInterval = null;
	$('#singo-auto-refresh-toggle').on('change', function() {
		var $dot = $('#singo-poll-indicator');
		if ($(this).is(':checked')) {
			$dot.show();
			pollInterval = setInterval(loadLogs, 5000);
			showToast('به‌روزرسانی خودکار فعال شد (هر ۵ ثانیه)', 'info');
		} else {
			$dot.hide();
			if (pollInterval) clearInterval(pollInterval);
			showToast('به‌روزرسانی خودکار متوقف شد.', 'neutral');
		}
	});

	// Copy Log
	$(document).on('click', '.singo-copy-btn', function() {
		var targetSelector = $(this).attr('data-copy-target');
		var text = targetSelector ? $(targetSelector).text() : $(this).attr('data-copy');
		var $btn = $(this);
		if (text && navigator.clipboard) {
			navigator.clipboard.writeText(text).then(function() {
				$btn.addClass('singo-copied');
				var orig = $btn.html();
				$btn.html('<span class="dashicons dashicons-yes"></span> کپی شد!');
				showToast('متن با موفقیت در کلیپ‌بورد کپی شد.', 'success');
				setTimeout(function() {
					$btn.removeClass('singo-copied').html(orig);
				}, 2200);
			});
		}
	});

	// Download Log File
	$('#singo-download-log-btn').on('click', function() {
		var source = $('input[name="singo_log_source"]:checked').val();
		window.location.href = ajaxurl + '?action=singo_download_debug_log&security=' + nonce + '&log_type=' + source;
	});

	// Clear Logs
	$('#singo-clear-debug-logs-btn').on('click', function() {
		var source = $('input[name="singo_log_source"]:checked').val();
		var label  = source === 'wp' ? 'وردپرس (debug.log)' : 'سینگو (singo-debug.log)';
		if (!confirm('آیا از پاکسازی کامل فایل لاگ ' + label + ' اطمینان دارید؟')) return;

		var $btn = $(this);
		$btn.prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'singo_clear_debug_logs',
				security: nonce,
				log_type: source
			},
			success: function(res) {
				$btn.prop('disabled', false);
				showToast(res.data.message || 'فایل لاگ پاکسازی شد.', 'success');
				loadLogs();
			},
			error: function() {
				$btn.prop('disabled', false);
				showToast('خطا در پاکسازی لاگ‌ها.', 'danger');
			}
		});
	});

	// Trigger Test Log
	$('#singo-test-log-btn').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'singo_trigger_test_log',
				security: nonce
			},
			success: function(res) {
				$btn.prop('disabled', false);
				showToast(res.data.message || 'لاگ آزمایشی ثبت شد.', 'success');
				$('input[name="singo_log_source"][value="singo"]').prop('checked', true);
				loadLogs();
			},
			error: function() {
				$btn.prop('disabled', false);
				showToast('خطا در ثبت لاگ آزمایشی.', 'danger');
			}
		});
	});

	// Test Email Alert
	$('#singo-test-email-btn').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true);
		showToast('در حال ارسال ایمیل آزمایشی...', 'info');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'singo_test_debug_email',
				security: nonce
			},
			success: function(res) {
				$btn.prop('disabled', false);
				showToast(res.data.message, res.success ? 'success' : 'warning');
			},
			error: function() {
				$btn.prop('disabled', false);
				showToast('خطا در ارتباط با سرور برای ارسال ایمیل.', 'danger');
			}
		});
	});

	// Inspect Database Health (Visual Checklist)
	$('#singo-inspect-db-btn').on('click', function() {
		var $btn = $(this);
		var $container = $('#singo-db-checks-container');
		$btn.prop('disabled', true);
		$container.html('<div style="color:var(--opt-primary); font-size:13px; font-weight:bold; padding:10px;"><span class="dashicons dashicons-update"></span> در حال بررسی سلامت پایگاه‌داده...</div>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'singo_inspect_db_health',
				security: nonce
			},
			success: function(res) {
				$btn.prop('disabled', false);
				if (res.success && res.data) {
					$container.empty();
					var checks = res.data.checks || [];

					checks.forEach(function(item) {
						var badgeClass = item.status === 'ok' ? 'opt-badge--success' : (item.status === 'warning' ? 'opt-badge--warning' : 'opt-badge--danger');
						var icon = item.status === 'ok' ? 'dashicons-yes' : (item.status === 'warning' ? 'dashicons-warning' : 'dashicons-dismiss');
						var iconColor = item.status === 'ok' ? 'var(--opt-success)' : (item.status === 'warning' ? 'var(--opt-warning)' : 'var(--opt-danger)');
						var label = item.status === 'ok' ? 'سالم ✓' : (item.status === 'warning' ? 'هشدار' : 'خطا');

						var cardHtml = 
							'<div style="background:var(--opt-gray-50); border:1px solid var(--opt-gray-200); border-radius:8px; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; gap:12px;">' +
								'<div style="display:flex; align-items:center; gap:10px;">' +
									'<span class="dashicons ' + icon + '" style="color:' + iconColor + '; font-size:18px; width:18px; height:18px;"></span>' +
									'<div>' +
										'<strong style="font-size:13px; color:var(--opt-gray-800); display:block; margin-bottom:2px;">' + item.title + '</strong>' +
										'<span style="font-size:12px; color:var(--opt-gray-500);">' + item.desc + '</span>' +
									'</div>' +
								'</div>' +
								'<span class="opt-badge ' + badgeClass + '">' + label + '</span>' +
							'</div>';

						$container.append(cardHtml);
					});

					// Populate raw JSON
					$('#singo-db-raw-json').text(JSON.stringify(res.data, null, 2));
					$('#singo-raw-data-box').slideDown(200);
					showToast('بررسی سلامت دیتابیس با موفقیت تکمیل شد.', 'success');
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				$container.html('<div style="color:var(--opt-danger); font-size:13px; padding:10px;">خطا در دریافت اطلاعات دیتابیس.</div>');
			}
		});
	});

	// Auto-Repair DB
	$('#singo-repair-db-btn').on('click', function() {
		if (!confirm('آیا از بازسازی و تعمیر خودکار ساختار دیتابیس مطمئن هستید؟')) return;
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'singo_repair_db_health',
				security: nonce
			},
			success: function(res) {
				$btn.prop('disabled', false);
				showToast(res.data.message || 'دیتابیس تعمیر شد.', 'success');
				$('#singo-inspect-db-btn').trigger('click');
			},
			error: function() {
				$btn.prop('disabled', false);
				showToast('خطا در تعمیر دیتابیس.', 'danger');
			}
		});
	});
});
</script>
