<?php
declare(strict_types=1);

/**
 * Advanced WordPress Design System (WPDS) Admin Debug & System Health View for Singo Tools
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$debug_enabled = singo_tools_get_option( 'enable_system_debug', true );
$debug_enabled = filter_var( $debug_enabled, FILTER_VALIDATE_BOOLEAN );
$admin_email   = get_option( 'admin_email' );

// Server Environment Metrics
$php_version   = PHP_VERSION;
$wp_version    = get_bloginfo( 'version' );
$mysql_version = $wpdb->db_version();
$mem_limit     = ini_get( 'memory_limit' );
$mem_usage     = size_format( memory_get_usage( true ), 2 );
$max_exec_time = ini_get( 'max_execution_time' ) . 's';

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

// WooCommerce & HPOS
$wc_active = class_exists( 'WooCommerce' );
$hpos_active = false;
if ( $wc_active && class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
	$hpos_active = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

// Plugin Conflict Checks
$jalali_conflicts = array();
if ( class_exists( 'WP_Parsidate' ) || defined( 'WPP_VERSION' ) ) {
	$jalali_conflicts[] = 'WP-Parsidate';
}
if ( function_exists( 'wpp_init' ) || class_exists( 'wp_jalali' ) ) {
	$jalali_conflicts[] = 'WP-Jalali';
}

$singo_meta = Singo_Tools_Debugger::get_log_metadata( 'singo' );
$wp_meta    = Singo_Tools_Debugger::get_log_metadata( 'wp' );
?>

<div class="wrap singo-debug-wrap" style="direction: rtl; text-align: right; margin-top: 10px;">
	
	<!-- Header Title -->
	<h1 class="wp-heading-inline" style="font-size: 20px; font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
		<span class="dashicons dashicons-bug" style="font-size: 26px; width: 26px; height: 26px; color: #ad6141;"></span>
		سیستم عیب‌یابی و پایش پیشرفته (WPDS Debug Console)
	</h1>
	<hr class="wp-header-end">

	<!-- Global Debug Status Notice -->
	<?php if ( $wp_debug ) : ?>
		<div class="notice notice-info inline" style="margin: 15px 0;">
			<p>
				<span class="dashicons dashicons-info" style="color: #0284c7;"></span>
				حالت <strong>WP_DEBUG</strong> سرور در وضعیت <span class="badge" style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:4px; font-weight:bold;">فعال (On)</span> قرار دارد. لاگ‌های سیستم در فایل‌های `debug.log` ثبت می‌شوند.
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-warning inline" style="margin: 15px 0;">
			<p>
				<span class="dashicons dashicons-warning" style="color: #d97706;"></span>
				ثابت <code>WP_DEBUG</code> در فایل <code>wp-config.php</code> غیرفعال است. برای ثبت کامل خطاها پیشنهاد می‌شود <code>define('WP_DEBUG', true);</code> تنظیم گردد.
			</p>
		</div>
	<?php endif; ?>

	<!-- Postbox 1: Master Debug Switcher -->
	<div class="postbox" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
		<div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; background: #fafafa;">
			<h2 class="hndle" style="font-size: 15px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-shield" style="color: #ad6141;"></span>
				تنظیمات ثبت لاگ و پایش هوشمند
			</h2>
		</div>
		<div class="inside" style="padding: 16px;">
			<label class="optionino-form-label" for="enable_system_debug" style="font-weight: bold; font-size: 14px; margin-bottom: 8px; display: block;">
				وضعیت سرویس عیب‌یاب پایش سینگو
			</label>
			<div class="optionino-switch-box flex" style="margin-bottom: 10px;">
				<input type="hidden" name="enable_system_debug" value="off">
				<input type="checkbox" class="optionino-switch-checkbox optionino-radio" id="enable_system_debug" name="enable_system_debug" value="on" <?php checked( $debug_enabled, true ); ?> style="display:none !important;">
				<label class="optionino-switch-label" for="enable_system_debug"></label>
			</div>
			<p class="description" style="color: #64748b;">با فعال ماندن این سرویس، خطاهای احتمالی شناسایی شده و در صورت وقوع خطای کشنده (Fatal Error)، به ایمیل مدیر سیستم (<code><?php echo esc_html( $admin_email ); ?></code>) هشدار ارسال می‌گردد.</p>
		</div>
	</div>

	<!-- Debug Details Body -->
	<div id="singo-debug-options-body" style="<?php echo $debug_enabled ? '' : 'display: none;'; ?>">

		<!-- Postbox 2: System & Server Environment Inspector -->
		<div class="postbox" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
			<div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; background: #fafafa;">
				<h2 class="hndle" style="font-size: 15px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 6px;">
					<span class="dashicons dashicons-admin-generic" style="color: #ad6141;"></span>
					مشخصات محیط سرور، وردپرس و ووکامرس
				</h2>
			</div>
			<div class="inside" style="padding: 16px;">
				<table class="widefat striped fixed" style="border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0; font-size: 13px;">
					<thead>
						<tr>
							<th style="font-weight: bold;">مجموعه سنجه‌ها</th>
							<th style="font-weight: bold;">مقدار فعی</th>
							<th style="font-weight: bold;">وضعیت و سازگاری</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>نسخه PHP / MySQL</td>
							<td><code><?php echo PHP_VERSION; ?></code> / <code><?php echo esc_html( $mysql_version ); ?></code></td>
							<td><span class="badge" style="background:#dcfce7; color:#15803d; padding:2px 8px; border-radius:4px;">سازگار (PHP 8+) ✓</span></td>
						</tr>
						<tr>
							<td>حافظه اختصاص‌یافته (RAM Usage / Limit)</td>
							<td><code><?php echo $mem_usage; ?></code> / <code><?php echo esc_html( $mem_limit ); ?></code></td>
							<td>سقف زمان اجرا: <code><?php echo esc_html( $max_exec_time ); ?></code></td>
						</tr>
						<tr>
							<td>وضعیت ووکامرس & HPOS</td>
							<td>
								<strong>ووکامرس:</strong> <?php echo $wc_active ? '<span style="color:#16a34a;">فعال (' . esc_html( WC_VERSION ) . ') ✓</span>' : '<span style="color:#dc2626;">غیرفعال ❌</span>'; ?>
							</td>
							<td>
								<strong>دیتا‌بیس جدید HPOS:</strong> <?php echo $hpos_active ? '<span style="color:#16a34a;">فعال ✓</span>' : '<span style="color:#64748b;">حالت استاندارد</span>'; ?>
							</td>
						</tr>
						<tr>
							<td>ثابت‌های دیباگ (WP Constants)</td>
							<td colspan="2">
								<code>WP_DEBUG: <?php echo $wp_debug ? 'true' : 'false'; ?></code> | 
								<code>WP_DEBUG_LOG: <?php echo $wp_debug_log ? 'true' : 'false'; ?></code> | 
								<code>WP_DEBUG_DISPLAY: <?php echo $wp_debug_display ? 'true' : 'false'; ?></code> | 
								<code>SCRIPT_DEBUG: <?php echo $script_debug ? 'true' : 'false'; ?></code> | 
								<code>DISABLE_WP_CRON: <?php echo $cron_disabled ? 'true' : 'false'; ?></code>
							</td>
						</tr>
						<tr>
							<td>ماژول‌های ضروری PHP</td>
							<td colspan="2">
								cURL: <strong><?php echo $curl_active ? '<span style="color:#16a34a;">✓</span>' : '<span style="color:#dc2626;">❌</span>'; ?></strong> | 
								mbstring: <strong><?php echo $mbstring_act ? '<span style="color:#16a34a;">✓</span>' : '<span style="color:#dc2626;">❌</span>'; ?></strong> | 
								BCMath: <strong><?php echo $bcmath_act ? '<span style="color:#16a34a;">✓</span>' : '<span style="color:#dc2626;">❌</span>'; ?></strong> | 
								JSON: <strong><?php echo $json_active ? '<span style="color:#16a34a;">✓</span>' : '<span style="color:#dc2626;">❌</span>'; ?></strong> | 
								تداخل‌های شمسی: <strong><?php echo ! empty( $jalali_conflicts ) ? '<span style="color:#d97706;">' . esc_html( implode( ', ', $jalali_conflicts ) ) . '</span>' : '<span style="color:#16a34a;">بدون تداخل ✓</span>'; ?></strong>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Postbox 3: Database Inspector & Auto-Repair -->
		<div class="postbox" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
			<div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; background: #fafafa;">
				<h2 class="hndle" style="font-size: 15px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 6px;">
					<span class="dashicons dashicons-database" style="color: #ad6141;"></span>
					بازرس سلامت دیتابیس و ابزار خودکار بازسازی (Database Health & Auto-Repair)
				</h2>
			</div>
			<div class="inside" style="padding: 16px;">
				<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 12px;">
					<button type="button" id="singo-inspect-db-btn" class="button button-secondary singo-btn" style="display: inline-flex; align-items: center; gap: 6px; height: 34px;">
						<span class="dashicons dashicons-search" style="margin: 0; font-size: 16px; width: 16px; height: 16px;"></span>
						بررسی زنده و مشاهده داده‌های خام دیتابیس
					</button>
					<button type="button" id="singo-repair-db-btn" class="button button-primary singo-btn" style="background-color: #ad6141; border-color: #ad6141; display: inline-flex; align-items: center; gap: 6px; height: 34px;">
						<span class="dashicons dashicons-build" style="margin: 0; font-size: 16px; width: 16px; height: 16px;"></span>
						بازسازی و تعمیر خودکار گزینه‌های دیتابیس
					</button>
				</div>
				<div id="singo-db-inspect-output" style="display: none; background: #0f172a; color: #38bdf8; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 13px; line-height: 1.6; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;"></div>
			</div>
		</div>

		<!-- Postbox 4: Advanced Live Interactive Log Console -->
		<div class="postbox" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
			<div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; background: #fafafa; display: flex; justify-content: space-between; align-items: center;">
				<h2 class="hndle" style="font-size: 15px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 6px;">
					<span class="dashicons dashicons-format-status" style="color: #ad6141;"></span>
					کنسول زنده لاگ‌ها، فیلتر لایه و جستجوی لحظه‌ای
				</h2>
			</div>
			<div class="inside" style="padding: 16px;">
				
				<!-- Toolbar Controls -->
				<div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; background: #f8fafc; padding: 12px 16px; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 15px;">
					
					<!-- Log Source Selector -->
					<div style="display: flex; align-items: center; gap: 10px;">
						<strong style="font-size: 13px;">منبع لاگ:</strong>
						<label style="cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 4px;">
							<input type="radio" name="singo_log_source" value="singo" checked style="margin: 0;">
							لاگ سینگو (<code>singo-debug.log</code>)
						</label>
						<label style="cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 4px;">
							<input type="radio" name="singo_log_source" value="wp" style="margin: 0;">
							لاگ عمومی وردپرس (<code>debug.log</code>)
						</label>
					</div>

					<!-- Log Level Filter -->
					<div style="display: flex; align-items: center; gap: 6px;">
						<strong style="font-size: 13px;">سطح:</strong>
						<select id="singo-log-level-filter" class="postform" style="font-size: 13px; height: 32px; padding: 0 8px;">
							<option value="ALL">همه سطوح (All)</option>
							<option value="FATAL_ERROR">خطای مهلک (Fatal)</option>
							<option value="WARNING">هشدار (Warning)</option>
							<option value="NOTICE">نکات (Notice)</option>
							<option value="DEPRECATED">دپریکیت (Deprecated)</option>
						</select>
					</div>

					<!-- Live Search Keyword -->
					<div style="display: flex; align-items: center; gap: 6px; flex: 1; min-width: 200px;">
						<input type="text" id="singo-log-search-query" class="regular-text" placeholder="جستجوی متنی لحظه‌ای در لاگ‌ها..." style="height: 32px; width: 100%; font-size: 13px; margin: 0;">
					</div>

					<!-- Fetch Button -->
					<button type="button" id="singo-fetch-logs-btn" class="button button-secondary singo-btn" style="height: 32px; display: inline-flex; align-items: center; gap: 6px; padding: 0 14px;">
						<span class="dashicons dashicons-update" style="margin: 0; font-size: 16px; width: 16px; height: 16px;"></span>
						روزرسانی
					</button>

				</div>

				<!-- Log Metadata Status Bar -->
				<div id="singo-log-meta-bar" style="display: flex; gap: 20px; font-size: 12px; color: #64748b; margin-bottom: 15px; background: #f1f5f9; padding: 8px 14px; border-radius: 6px; border: 1px solid #e2e8f0;">
					<div>حجم فایل: <strong id="singo-meta-size" style="color:#1e293b;"><?php echo esc_html( $singo_meta['size_fmt'] ); ?></strong></div>
					<div>تعداد سطرها: <strong id="singo-meta-lines" style="color:#1e293b;"><?php echo esc_html( (string) $singo_meta['lines'] ); ?></strong></div>
					<div>آخرین تغییر: <strong id="singo-meta-mtime" style="color:#1e293b;"><?php echo esc_html( $singo_meta['mtime'] ); ?></strong></div>
				</div>

				<!-- Dedicated Console Terminal Box Header & Output Window -->
				<div class="singo-console-wrapper" style="border-radius: 8px; overflow: hidden; border: 1px solid #1e293b; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
					
					<!-- Console Header Bar -->
					<div style="background: #1e293b; color: #f8fafc; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155;">
						<div style="font-family: monospace; font-size: 12px; color: #94a3b8; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-editor-code" style="color: #38bdf8; font-size: 16px; width: 16px; height: 16px; margin: 0;"></span>
							کنسول متنی دیباگ (System Terminal Console)
						</div>
						
						<!-- High Visibility Copy Button -->
						<button type="button" id="singo-copy-log-btn" class="button button-secondary singo-btn" style="background: #334155 !important; color: #ffffff !important; border: 1px solid #475569 !important; font-size: 12px !important; height: 28px !important; padding: 0 12px !important; display: inline-flex !important; align-items: center !important; gap: 6px !important; font-weight: bold !important; cursor: pointer;">
							<span class="dashicons dashicons-clipboard" style="margin: 0; font-size: 14px; width: 14px; height: 14px; color: #38bdf8;"></span>
							کپی متن کامل لاگ
						</button>
					</div>

					<!-- Console Terminal Output Window -->
					<pre id="singo-log-console" style="background: #0f172a; color: #38bdf8; padding: 16px; font-family: monospace; font-size: 13px; line-height: 1.6; height: 350px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin: 0;">در حال بارگذاری لاگ‌ها...</pre>

				</div>

				<!-- Action Buttons -->
				<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
					<button type="button" id="singo-download-log-btn" class="button button-secondary singo-btn" style="display: inline-flex; align-items: center; gap: 6px; height: 34px;">
						<span class="dashicons dashicons-download" style="margin: 0; font-size: 16px; width: 16px; height: 16px;"></span>
						دانلود فایل لاگ
					</button>

					<button type="button" id="singo-clear-debug-logs-btn" class="button button-link-delete singo-btn" style="color: #dc2626; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; height: 34px;">
						<span class="dashicons dashicons-trash" style="margin: 0; font-size: 16px; width: 16px; height: 16px;"></span>
						پاکسازی کامل فایل لاگ
					</button>
				</div>

			</div>
		</div>

	</div>

</div>

<script>
jQuery(document).ready(function($) {
	var nonce = '<?php echo wp_create_nonce( 'singo_debug_nonce' ); ?>';

	// Toggle Debug Options Body
	$(document).on('change', '#enable_system_debug', function() {
		if ($(this).is(':checked')) {
			$('#singo-debug-options-body').slideDown(200);
		} else {
			$('#singo-debug-options-body').slideUp(200);
		}
	});

	// Load & Filter Logs
	function loadLogs() {
		var $console = $('#singo-log-console');
		var source   = $('input[name="singo_log_source"]:checked').val();
		var level    = $('#singo-log-level-filter').val();
		var search   = $('#singo-log-search-query').val();

		$console.text('در حال بارگذاری لاگ‌ها...');

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
					$console.text(res.data.content || 'هیچ لاگی ثبت نشده است.');
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

	// Initial load
	loadLogs();

	// Fetch logs on filter/source change & refresh button
	$('#singo-fetch-logs-btn').on('click', loadLogs);
	$(document).on('change', 'input[name="singo_log_source"], #singo-log-level-filter', loadLogs);
	
	// Live search debounce
	var searchTimer;
	$('#singo-log-search-query').on('input', function() {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(loadLogs, 300);
	});

	// Copy log text to clipboard
	$('#singo-copy-log-btn').on('click', function() {
		var text = $('#singo-log-console').text();
		navigator.clipboard.writeText(text).then(function() {
			alert('متن لاگ‌ها با موفقیت در حافظه کپی شد.');
		});
	});

	// Download Log File Direct Navigation
	$('#singo-download-log-btn').on('click', function() {
		var source = $('input[name="singo_log_source"]:checked').val();
		window.location.href = ajaxurl + '?action=singo_download_debug_log&security=' + nonce + '&log_type=' + source;
	});

	// Inspect DB & Options Health
	$('#singo-inspect-db-btn').on('click', function() {
		var $btn = $(this);
		var $out = $('#singo-db-inspect-output');
		$btn.prop('disabled', true);
		$out.show().text('در حال بررسی زنده دیتابیس...');

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
					var text = '✅ وضعیت سلامت دیتابیس: ' + res.data.status + '\n';
					if (res.data.issues && res.data.issues.length > 0) {
						text += '⚠️ موارد شناسایی‌شده:\n - ' + res.data.issues.join('\n - ') + '\n\n';
					} else {
						text += 'هیچ مشکلی در ساختار دیتابیس یافت نشد.\n\n';
					}
					text += '--- داده‌های خام دیتابیس ---\n';
					text += 'singo_tools_options: ' + JSON.stringify(res.data.options, null, 2) + '\n\n';
					text += 'mu_attribute_groups: ' + JSON.stringify(res.data.groups, null, 2) + '\n\n';
					text += 'mu_default_attribute_group: ' + res.data.default_group + '\n\n';
					text += 'mu_attribute_groups_map: ' + JSON.stringify(res.data.groups_map, null, 2);
					$out.text(text);
				} else {
					$out.text('خطا در دریافت اطلاعات دیتابیس.');
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				$out.text('خطا در ارتباط با سرور.');
			}
		});
	});

	// Auto-Repair DB & Options Health
	$('#singo-repair-db-btn').on('click', function() {
		if (!confirm('آیا از بازسازی و تعمیر خودکار ساختار دیتابیس مطمئن هستید؟')) return;
		var $btn = $(this);
		var $out = $('#singo-db-inspect-output');
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
				alert(res.data.message || 'دیتابیس تعمیر شد.');
				$('#singo-inspect-db-btn').trigger('click');
			},
			error: function() {
				$btn.prop('disabled', false);
				alert('خطا در فرایند تعمیر دیتابیس.');
			}
		});
	});

	// Clear logs
	$('#singo-clear-debug-logs-btn').on('click', function() {
		var source = $('input[name="singo_log_source"]:checked').val();
		var label  = source === 'wp' ? 'وردپرس (debug.log)' : 'سینگو (singo-debug.log)';
		if (!confirm('آیا از پاکسازی فایل لاگ ' + label + ' اطمینان دارید؟')) return;
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
				alert(res.data.message || 'پاکسازی شد.');
				$btn.prop('disabled', false);
				loadLogs();
			},
			error: function() {
				alert('خطا در پاکسازی لاگ‌ها.');
				$btn.prop('disabled', false);
			}
		});
	});
});
</script>
