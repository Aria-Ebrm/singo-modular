<?php
declare(strict_types=1);

/**
 * Singo Tools - Admin View: Price List Download Reports & User Restriction
 *
 * Displays permanent download logs, customer phone numbers (mobile meta / billing_phone),
 * exact frozen snapshot inspections, and user restriction controls.
 *
 * @package SingoTools\Modules\PriceList
 * @since 4.5.2
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
	wp_die( esc_html__( 'شما دسترسی لازم برای مشاهده این برگه را ندارید.', 'singo-tools' ), 403 );
}

$reports_data = class_exists( 'Singo_Price_List' ) ? Singo_Price_List::get_all_admin_download_reports() : array();
$total_downloads    = $reports_data['total_downloads'] ?? 0;
$total_users        = $reports_data['total_users'] ?? 0;
$total_restricted   = $reports_data['total_restricted'] ?? 0;
$users_list         = $reports_data['users'] ?? array();
$ajax_nonce         = wp_create_nonce( 'singo_price_list_nonce' );
$primary_color      = class_exists( 'Singo_Price_List' ) ? Singo_Price_List::get_active_primary_color() : '#ad6141';
?>

<div class="singo-reports-admin-wrap" style="direction: rtl; text-align: right; font-family: inherit !important; padding-top: 5px; width: 100%; max-width: 100%; box-sizing: border-box;">

	<!-- Top Banner Header -->
	<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 22px; margin-bottom: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-sizing: border-box;">
		<div style="display: flex; align-items: center; gap: 14px;">
			<div style="background: <?php echo esc_attr( $primary_color ); ?>; color: #fff; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
				<span class="dashicons dashicons-chart-bar" style="font-size: 24px; width: 24px; height: 24px; color: #ffffff !important;"></span>
			</div>
			<div>
				<h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b;">گزارش دانلودهای لیست قیمت و مدیریت دسترسی کاربران</h3>
				<p style="margin: 4px 0 0 0; font-size: 12.5px; color: #64748b;">
					مشاهده سوابق دائمی دانلودها، شماره تماس کاربران، بررسی فایل‌های دانلودشده و مدیریت دسترسی‌ها.
				</p>
			</div>
		</div>

		<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
			<button type="button" id="singo-btn-clear-all-logs" class="singo-btn-secondary" style="border: 1px solid #fecdd3; color: #e11d48; background: #fff1f2; height: 38px; padding: 0 14px; font-size: 12.5px; font-weight: 600; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px; color: #e11d48 !important;"></span>
				پاکسازی کل گزارشات
			</button>
		</div>
	</div>

	<!-- Statistics Summary Cards -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 22px;">
		
		<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 14px;">
			<div style="background: #eff6ff; color: #2563eb; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
				<span class="dashicons dashicons-download" style="font-size: 22px; width: 22px; height: 22px;"></span>
			</div>
			<div>
				<div style="font-size: 12px; color: #64748b; font-weight: 600;">تعداد کل دفعات دانلود</div>
				<div id="singo-stat-total-downloads" style="font-size: 20px; font-weight: 800; color: #1e293b; margin-top: 2px;">
					<?php echo esc_html( class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $total_downloads ) : (string) $total_downloads ); ?>
				</div>
			</div>
		</div>

		<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 14px;">
			<div style="background: #f0fdf4; color: #16a34a; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
				<span class="dashicons dashicons-admin-users" style="font-size: 22px; width: 22px; height: 22px;"></span>
			</div>
			<div>
				<div style="font-size: 12px; color: #64748b; font-weight: 600;">کاربران دانلودکننده</div>
				<div id="singo-stat-total-users" style="font-size: 20px; font-weight: 800; color: #1e293b; margin-top: 2px;">
					<?php echo esc_html( class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $total_users ) : (string) $total_users ); ?>
				</div>
			</div>
		</div>

		<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 14px;">
			<div style="background: #fff1f2; color: #e11d48; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
				<span class="dashicons dashicons-lock" style="font-size: 22px; width: 22px; height: 22px;"></span>
			</div>
			<div>
				<div style="font-size: 12px; color: #64748b; font-weight: 600;">کاربران محدودشده (مسدود)</div>
				<div id="singo-stat-total-restricted" style="font-size: 20px; font-weight: 800; color: #e11d48; margin-top: 2px;">
					<?php echo esc_html( class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $total_restricted ) : (string) $total_restricted ); ?>
				</div>
			</div>
		</div>

	</div>

	<!-- Controls & Search Toolbar -->
	<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
		
		<div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 280px; max-width: 500px;">
			<div style="position: relative; width: 100%;">
				<input type="text" id="singo-search-users-input" placeholder="جستجو با نام، نام کاربری یا شماره موبایل (مثلاً 0912...)" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 36px 0 12px; font-size: 13px; font-family: inherit;" />
				<span class="dashicons dashicons-search" style="position: absolute; right: 10px; top: 10px; color: #94a3b8; font-size: 18px; width: 18px; height: 18px;"></span>
			</div>
		</div>

		<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
			<div style="display: flex; align-items: center; gap: 6px;">
				<label for="singo-filter-status" style="font-size: 13px; font-weight: 600; color: #475569;">فیلتر وضعیت:</label>
				<select id="singo-filter-status" style="height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px; font-family: inherit; padding: 0 8px;">
					<option value="all">همه کاربران (<?php echo count( $users_list ); ?>)</option>
					<option value="active">فقط کاربران مجاز</option>
					<option value="restricted">فقط کاربران محدودشده (مسدود)</option>
				</select>
			</div>

			<!-- Quick restrict direct by User ID / Phone input -->
			<button type="button" id="singo-btn-quick-restrict-modal" class="singo-btn-secondary" style="height: 38px; padding: 0 14px; font-size: 12.5px; font-weight: 600; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-privacy" style="font-size: 16px; width: 16px; height: 16px; color: #ad6141 !important;"></span>
				محدودسازی دستی کاربر
			</button>
		</div>

	</div>

	<!-- Main Reports Table -->
	<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
		
		<div style="overflow-x: auto; width: 100%;">
			<table id="singo-reports-table" style="border: none; width: 100%; border-collapse: collapse; font-size: 13px;">
				<thead>
					<tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
						<th style="padding: 12px 14px; font-weight: 700; color: #334155; text-align: right; width: 40px;">#</th>
						<th style="padding: 12px 14px; font-weight: 700; color: #334155; text-align: right;">کاربر</th>
						<th style="padding: 12px 14px; font-weight: 700; color: #334155; text-align: center;">شماره تماس</th>
						<th style="padding: 12px 14px; font-weight: 700; color: #334155; text-align: center;">تعداد دانلود</th>
						<th style="padding: 12px 14px; font-weight: 700; color: #334155; text-align: right;">آخرین دسته‌بندی دانلودشده</th>
						<th style="padding: 12px 14px; font-weight: 700; color: #334155; text-align: center;">آخرین تاریخ و ساعت</th>
						<th style="padding: 12px 14px; font-weight: 700; color: #334155; text-align: center;">وضعیت دسترسی</th>
						<th style="padding: 12px 14px; font-weight: 700; color: #334155; text-align: center; width: 180px;">عملیات مدیریت</th>
					</tr>
				</thead>
				<tbody id="singo-reports-tbody">
					<?php if ( empty( $users_list ) ) : ?>
						<tr id="singo-no-reports-row">
							<td colspan="8" style="text-align: center; padding: 40px 20px; color: #64748b;">
								<div style="max-width: 400px; margin: 0 auto;">
									<span class="dashicons dashicons-info" style="font-size: 32px; width: 32px; height: 32px; color: #94a3b8; display: block; margin: 0 auto 10px;"></span>
									<strong style="font-size: 14px; color: #334155; display: block; margin-bottom: 4px;">هنوز هیچ دانلودی توسط کاربران ثبت نشده است</strong>
									<p style="font-size: 12px; color: #64748b; margin: 0;">
										به محض اینکه مشتریان در حساب کاربری خود لیست قیمت را دریافت کنند، اطلاعات کامل و شماره موبایل آن‌ها در این جدول ثبت خواهد شد.
									</p>
								</div>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $users_list as $index => $u ) :
							$user_id         = (int) $u['user_id'];
							$display_name    = (string) $u['display_name'];
							$user_login      = (string) $u['user_login'];
							$phone           = (string) $u['phone'];
							$has_phone       = ! empty( $phone );
							$downloads_count = (int) $u['downloads_count'];
							$last_cat        = (string) $u['last_category'];
							$last_date       = (string) $u['last_date_shamsi'];
							$is_restricted   = (bool) $u['is_restricted'];
							$restrict_reason = (string) ( $u['restrict_reason'] ?? '' );
							$row_num         = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) ( $index + 1 ) ) : (string) ( $index + 1 );
							$count_fa        = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $downloads_count ) : (string) $downloads_count;
							$phone_fa        = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( $phone ) : $phone;
						?>
							<tr class="singo-user-report-row" 
								data-user-id="<?php echo esc_attr( (string) $user_id ); ?>"
								data-name="<?php echo esc_attr( mb_strtolower( $display_name . ' ' . $user_login ) ); ?>"
								data-phone="<?php echo esc_attr( $phone ); ?>"
								data-cat="<?php echo esc_attr( mb_strtolower( $last_cat ) ); ?>"
								data-status="<?php echo $is_restricted ? 'restricted' : 'active'; ?>"
								style="border-bottom: 1px solid #f1f5f9;">
								
								<td style="padding: 12px 14px; text-align: right; color: #64748b; font-weight: 600;">
									<?php echo esc_html( $row_num ); ?>
								</td>

								<td style="padding: 12px 14px; text-align: right;">
									<div style="display: flex; align-items: center; gap: 8px;">
										<div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 14px; font-weight: 700; flex-shrink: 0;">
											<?php echo esc_html( mb_substr( $display_name, 0, 1 ) ); ?>
										</div>
										<div style="min-width: 0;">
											<a href="javascript:void(0);" class="singo-open-user-dossier" data-user-id="<?php echo esc_attr( (string) $user_id ); ?>" style="font-weight: 700; color: #1e293b; text-decoration: none; font-size: 13.5px; display: inline-block;">
												<?php echo esc_html( $display_name ); ?>
											</a>
											<div style="font-size: 11px; color: #64748b; direction: ltr; text-align: right;">
												@<?php echo esc_html( $user_login ); ?> (شناسه: <?php echo esc_html( (string) $user_id ); ?>)
											</div>
										</div>
									</div>
								</td>

								<td style="padding: 12px 14px; text-align: center; direction: ltr;">
									<?php if ( $has_phone ) : ?>
										<span style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 12.5px; color: #0f172a; background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 20px;">
											<span class="dashicons dashicons-phone" style="font-size: 14px; width: 14px; height: 14px; color: <?php echo esc_attr( $primary_color ); ?>;"></span>
											<span><?php echo esc_html( $phone_fa ); ?></span>
										</span>
									<?php else : ?>
										<span style="color: #94a3b8; font-size: 12px;">ثبت نشده</span>
									<?php endif; ?>
								</td>

								<td style="padding: 12px 14px; text-align: center;">
									<span style="background: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12px; padding: 3px 10px; border-radius: 12px; display: inline-block;">
										<?php echo esc_html( $count_fa ); ?> بار
									</span>
								</td>

								<td style="padding: 12px 14px; text-align: right; font-size: 13px; color: #334155;">
									<strong><?php echo esc_html( $last_cat ); ?></strong>
								</td>

								<td style="padding: 12px 14px; text-align: center; font-size: 12px; color: #64748b;">
									<?php echo esc_html( ! empty( $last_date ) ? $last_date : '-' ); ?>
								</td>

								<td style="padding: 12px 14px; text-align: center;">
									<?php if ( $is_restricted ) : ?>
										<span class="singo-status-badge singo-status-badge--restricted" style="background: #ffe4e6; color: #e11d48; font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;">
											<span class="dashicons dashicons-lock" style="font-size: 13px; width: 13px; height: 13px;"></span>
											محدودشده
										</span>
									<?php else : ?>
										<span class="singo-status-badge singo-status-badge--active" style="background: #dcfce7; color: #15803d; font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;">
											<span class="dashicons dashicons-yes" style="font-size: 13px; width: 13px; height: 13px;"></span>
											مجاز
										</span>
									<?php endif; ?>
								</td>

								<td style="padding: 12px 14px; text-align: center;">
									<div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
										
										<button type="button" class="singo-btn-open-dossier singo-open-user-dossier" data-user-id="<?php echo esc_attr( (string) $user_id ); ?>" style="height: 32px; padding: 0 10px; font-size: 12px; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" title="مشاهده پرونده و فایل‌های دانلودشده">
											<span class="dashicons dashicons-visibility" style="font-size: 15px; width: 15px; height: 15px; color: <?php echo esc_attr( $primary_color ); ?>;"></span>
											پرونده
										</button>

										<button type="button" class="singo-btn-toggle-restrict" data-user-id="<?php echo esc_attr( (string) $user_id ); ?>" data-restricted="<?php echo $is_restricted ? '1' : '0'; ?>" style="height: 32px; padding: 0 8px; font-size: 12px; font-weight: 600; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; border: 1px solid <?php echo $is_restricted ? '#bbf7d0' : '#fecdd3'; ?>; background: <?php echo $is_restricted ? '#f0fdf4' : '#fff1f2'; ?>; color: <?php echo $is_restricted ? '#16a34a' : '#e11d48'; ?>;" title="<?php echo $is_restricted ? 'رفع محدودیت کاربر' : 'محدود کردن دسترسی این کاربر'; ?>">
											<span class="dashicons <?php echo $is_restricted ? 'dashicons-unlock' : 'dashicons-lock'; ?>" style="font-size: 15px; width: 15px; height: 15px;"></span>
											<?php echo $is_restricted ? 'آزاد' : 'محدود'; ?>
										</button>

										<button type="button" class="singo-btn-delete-user-logs" data-user-id="<?php echo esc_attr( (string) $user_id ); ?>" style="height: 32px; width: 32px; padding: 0; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; color: #94a3b8; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;" title="حذف گزارشات این کاربر">
											<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
										</button>

									</div>
								</td>

							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

	</div>

</div>

<!-- Modal: User Dossier & Downloaded Files History -->
<div id="singo-user-dossier-modal" style="display: none; position: fixed; inset: 0; z-index: 100050; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(3px); align-items: center; justify-content: center; padding: 20px;">
	
	<div style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 820px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); direction: rtl; text-align: right; font-family: inherit;">
		
		<!-- Modal Header -->
		<div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 16px 22px; display: flex; justify-content: space-between; align-items: center;">
			<div style="display: flex; align-items: center; gap: 10px;">
				<div style="background: <?php echo esc_attr( $primary_color ); ?>; color: #fff; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
					<span class="dashicons dashicons-id-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
				</div>
				<div>
					<h3 id="singo-dossier-user-title" style="margin: 0; font-size: 15.5px; font-weight: 700; color: #1e293b;">پرونده دانلودهای کاربر</h3>
					<div id="singo-dossier-user-subtitle" style="font-size: 12px; color: #64748b; margin-top: 2px;">در حال دریافت اطلاعات...</div>
				</div>
			</div>
			
			<button type="button" id="singo-btn-close-dossier" style="background: transparent; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; padding: 4px 8px; border-radius: 6px; line-height: 1;">
				✕
			</button>
		</div>

		<!-- Modal Body -->
		<div style="padding: 22px; overflow-y: auto; flex: 1;">
			
			<!-- User Info Card & Restriction Switch -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
				
				<div style="display: flex; align-items: center; gap: 14px;">
					<div id="singo-dossier-avatar" style="width: 48px; height: 48px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; color: #475569;">
						-
					</div>
					<div>
						<div style="display: flex; align-items: center; gap: 8px;">
							<strong id="singo-dossier-name" style="font-size: 15px; color: #1e293b;">-</strong>
							<span id="singo-dossier-status-pill" style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; background: #dcfce7; color: #15803d;">مجاز</span>
						</div>
						<div style="font-size: 12.5px; color: #64748b; margin-top: 4px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
							<span>شماره موبایل: <strong id="singo-dossier-phone" style="color: #0f172a; direction: ltr; display: inline-block;">-</strong></span>
							<span>شناسه کاربر: <strong id="singo-dossier-id" style="color: #0f172a;">-</strong></span>
							<span>ایمیل: <strong id="singo-dossier-email" style="color: #0f172a; direction: ltr; display: inline-block;">-</strong></span>
						</div>
					</div>
				</div>

				<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
					<button type="button" id="singo-dossier-toggle-restrict-btn" class="singo-btn-secondary" style="height: 36px; padding: 0 14px; font-size: 12.5px; font-weight: 700; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
						<span class="dashicons dashicons-lock"></span>
						<span id="singo-dossier-restrict-btn-label">محدود کردن دسترسی کاربر</span>
					</button>
				</div>

			</div>

			<!-- Restriction Note Banner (if restricted) -->
			<div id="singo-dossier-restrict-alert" style="display: none; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 12.5px; color: #9f1239;">
				<strong>این کاربر محدود شده است:</strong> کاربر امکان دریافت و دانلود لیست قیمت را نخواهد داشت.
				<div id="singo-dossier-restrict-reason" style="margin-top: 4px; font-size: 12px; color: #be123c;"></div>
			</div>

			<!-- User's Downloaded Files History Table -->
			<div>
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
					<h4 style="margin: 0; font-size: 14.5px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 6px;">
						<span class="dashicons dashicons-media-document" style="color: <?php echo esc_attr( $primary_color ); ?>;"></span>
						لیست تمام فایل‌های دانلودشده توسط این کاربر
					</h4>
					<span id="singo-dossier-files-count" style="font-size: 12px; color: #64748b; font-weight: 600;"></span>
				</div>

				<div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
					<table class="widefat striped" style="border: none; width: 100%; border-collapse: collapse;">
						<thead>
							<tr style="background: #f1f5f9;">
								<th style="padding: 10px 12px; font-size: 12.5px; font-weight: 700; color: #334155; text-align: right; width: 35px;">#</th>
								<th style="padding: 10px 12px; font-size: 12.5px; font-weight: 700; color: #334155; text-align: right;">عنوان سند / دسته‌بندی</th>
								<th style="padding: 10px 12px; font-size: 12.5px; font-weight: 700; color: #334155; text-align: center;">تعداد کالا</th>
								<th style="padding: 10px 12px; font-size: 12.5px; font-weight: 700; color: #334155; text-align: center;">تاریخ و ساعت دقیق</th>
								<th style="padding: 10px 12px; font-size: 12.5px; font-weight: 700; color: #334155; text-align: center; width: 170px;">عملیات</th>
							</tr>
						</thead>
						<tbody id="singo-dossier-files-tbody">
							<tr>
								<td colspan="5" style="text-align: center; padding: 25px; color: #64748b;">
									در حال بارگذاری سوابق...
								</td>
							</tr>
						</tbody>
					</table>
				</div>

			</div>

		</div>

		<!-- Modal Footer -->
		<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 22px; display: flex; justify-content: flex-end;">
			<button type="button" class="singo-btn-secondary" onclick="jQuery('#singo-user-dossier-modal').fadeOut(200);" style="height: 36px; padding: 0 18px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer;">
				بستن
			</button>
		</div>

	</div>

</div>

<!-- Modal: Quick Restrict User by Phone or User ID -->
<div id="singo-quick-restrict-modal" style="display: none; position: fixed; inset: 0; z-index: 100050; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(3px); align-items: center; justify-content: center; padding: 20px;">
	<div style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 480px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); direction: rtl; text-align: right; font-family: inherit;">
		
		<div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
			<strong style="font-size: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-privacy" style="color: <?php echo esc_attr( $primary_color ); ?>;"></span>
				محدودسازی دستی کاربر از دانلود لیست قیمت
			</strong>
			<button type="button" onclick="jQuery('#singo-quick-restrict-modal').fadeOut(200);" style="background: transparent; border: none; font-size: 18px; color: #94a3b8; cursor: pointer;">✕</button>
		</div>

		<div style="padding: 20px;">
			<p style="margin: 0 0 16px 0; font-size: 12.5px; color: #64748b; line-height: 1.5;">
				شناسه کاربری (User ID) یا شماره موبایل کاربر را وارد کنید تا دسترسی او به دانلود لیست قیمت مسدود شود.
			</p>

			<div style="margin-bottom: 14px;">
				<label for="singo-quick-restrict-input" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
					شناسه کاربری یا شماره موبایل:
				</label>
				<input type="text" id="singo-quick-restrict-input" placeholder="مثال: 12 یا 09123456789" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px; font-size: 13px; font-family: inherit; direction: ltr;" />
			</div>

			<div style="margin-bottom: 18px;">
				<label for="singo-quick-restrict-reason" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
					علت محدودیت (اختیاری جهت یادداشت مدیر):
				</label>
				<input type="text" id="singo-quick-restrict-reason" placeholder="مثال: دانلود غیرعادی یا درخواست همکار" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px; font-size: 13px; font-family: inherit;" />
			</div>

			<div style="display: flex; justify-content: flex-end; gap: 10px;">
				<button type="button" onclick="jQuery('#singo-quick-restrict-modal').fadeOut(200);" class="singo-btn-secondary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer;">
					انصراف
				</button>
				<button type="button" id="singo-btn-submit-quick-restrict" class="singo-btn-primary" style="height: 38px; padding: 0 18px; font-size: 13px; font-weight: 700; border-radius: 6px; cursor: pointer; background: #e11d48; border-color: #e11d48; color: #fff;">
					ثبت محدودیت
				</button>
			</div>
		</div>

	</div>
</div>

<script>
jQuery(document).ready(function($) {
	var ajaxUrl   = '<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>';
	var ajaxNonce = '<?php echo esc_js( $ajax_nonce ); ?>';
	var currentDossierUserId = 0;

	// Search and filter table rows
	function filterReportsTable() {
		var query  = ($('#singo-search-users-input').val() || '').toLowerCase().trim();
		var status = $('#singo-filter-status').val();
		var visibleCount = 0;

		$('.singo-user-report-row').each(function() {
			var $row = $(this);
			var name = $row.attr('data-name') || '';
			var phone = $row.attr('data-phone') || '';
			var cat = $row.attr('data-cat') || '';
			var rowStatus = $row.attr('data-status') || '';

			var matchesQuery = !query || name.indexOf(query) !== -1 || phone.indexOf(query) !== -1 || cat.indexOf(query) !== -1;
			var matchesStatus = (status === 'all') || (status === rowStatus);

			if (matchesQuery && matchesStatus) {
				$row.show();
				visibleCount++;
			} else {
				$row.hide();
			}
		});

		if (visibleCount === 0 && $('.singo-user-report-row').length > 0) {
			if (!$('#singo-no-results-row').length) {
				$('#singo-reports-tbody').append('<tr id="singo-no-results-row"><td colspan="8" style="text-align:center; padding:30px; color:#64748b;">هیچ موردی مطابق فیلتر یافت نشد.</td></tr>');
			}
		} else {
			$('#singo-no-results-row').remove();
		}
	}

	$('#singo-search-users-input').on('input', filterReportsTable);
	$('#singo-filter-status').on('change', filterReportsTable);

	// Open Dossier Modal
	$(document).on('click', '.singo-open-user-dossier', function(e) {
		e.preventDefault();
		var userId = $(this).attr('data-user-id');
		if (!userId) return;

		currentDossierUserId = parseInt(userId, 10);
		$('#singo-user-dossier-modal').css('display', 'flex').hide().fadeIn(200);

		$('#singo-dossier-user-subtitle').text('در حال دریافت سوابق کاربر...');
		$('#singo-dossier-files-tbody').html('<tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;">در حال دریافت پرونده...</td></tr>');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'singo_admin_get_user_download_dossier',
				security: ajaxNonce,
				user_id: currentDossierUserId
			},
			success: function(res) {
				if (res.success && res.data) {
					var u = res.data.user;
					var files = res.data.files || [];

					$('#singo-dossier-user-title').text('پرونده دانلودهای ' + u.display_name);
					$('#singo-dossier-user-subtitle').text('نام کاربری: ' + u.user_login + ' | شناسه: ' + u.user_id);
					$('#singo-dossier-avatar').text(u.display_name.charAt(0));
					$('#singo-dossier-name').text(u.display_name);
					$('#singo-dossier-phone').text(u.phone || 'ثبت نشده');
					$('#singo-dossier-id').text(u.user_id);
					$('#singo-dossier-email').text(u.email || '-');

					updateDossierRestrictionUI(u.is_restricted, u.restrict_reason);

					$('#singo-dossier-files-count').text(files.length + ' فایل ثبت‌شده');

					if (files.length === 0) {
						$('#singo-dossier-files-tbody').html('<tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;">هیچ فایل دانلودی برای این کاربر ثبت نشده است.</td></tr>');
					} else {
						var rowsHtml = '';
						for (var i = 0; i < files.length; i++) {
							var f = files[i];
							rowsHtml += '<tr id="singo-dossier-row-' + f.id + '">';
							rowsHtml += '<td style="padding:10px 12px; color:#64748b; font-weight:600;">' + (i + 1) + '</td>';
							rowsHtml += '<td style="padding:10px 12px;"><strong>' + f.category_name + '</strong><div style="font-size:11.5px; color:#64748b;">' + (f.doc_title || '') + '</div></td>';
							rowsHtml += '<td style="padding:10px 12px; text-align:center;"><span style="background:#f1f5f9; padding:2px 8px; border-radius:10px; font-weight:600; font-size:11.5px;">' + f.products_count + ' کالا</span></td>';
							rowsHtml += '<td style="padding:10px 12px; text-align:center; font-size:12px; color:#64748b;">' + f.shamsi_date + '</td>';
							rowsHtml += '<td style="padding:10px 12px; text-align:center;">' +
								'<a href="' + f.preview_url + '" target="_blank" class="singo-btn-secondary" style="height:30px; padding:0 10px; font-size:12px; font-weight:600; border-radius:6px; display:inline-flex; align-items:center; gap:4px; text-decoration:none; margin-left:6px;" title="مشاهده دقیق PDF دانلودشده با همان قیمت‌های لحظه دانلود">' +
									'<span class="dashicons dashicons-external" style="font-size:14px; width:14px; height:14px;"></span> مشاهده' +
								'</a>' +
								'<button type="button" class="singo-btn-delete-log-item" data-user-id="' + currentDossierUserId + '" data-log-id="' + f.id + '" style="height:30px; width:30px; border:1px solid #fecdd3; background:#fff1f2; color:#e11d48; border-radius:6px; cursor:pointer;" title="حذف این رکورد">' +
									'<span class="dashicons dashicons-trash" style="font-size:15px; width:15px; height:15px;"></span>' +
								'</button>' +
							'</td>';
							rowsHtml += '</tr>';
						}
						$('#singo-dossier-files-tbody').html(rowsHtml);
					}
				} else {
					$('#singo-dossier-files-tbody').html('<tr><td colspan="5" style="text-align:center; padding:30px; color:#e11d48;">خطا در دریافت اطلاعات پرونده کاربر.</td></tr>');
				}
			},
			error: function() {
				$('#singo-dossier-files-tbody').html('<tr><td colspan="5" style="text-align:center; padding:30px; color:#e11d48;">خطا در برقراری ارتباط با سرور.</td></tr>');
			}
		});
	});

	function updateDossierRestrictionUI(isRestricted, reason) {
		var $btn = $('#singo-dossier-toggle-restrict-btn');
		var $statusPill = $('#singo-dossier-status-pill');
		var $alert = $('#singo-dossier-restrict-alert');
		var $reasonEl = $('#singo-dossier-restrict-reason');

		if (isRestricted) {
			$statusPill.css({ background: '#ffe4e6', color: '#e11d48' }).text('محدودشده (مسدود)');
			$btn.css({ background: '#f0fdf4', color: '#16a34a', border: '1px solid #bbf7d0' })
				.html('<span class="dashicons dashicons-unlock"></span> رفع محدودیت کاربر');
			$alert.show();
			$reasonEl.text(reason ? ('علت محدودیت: ' + reason) : '');
		} else {
			$statusPill.css({ background: '#dcfce7', color: '#15803d' }).text('مجاز');
			$btn.css({ background: '#fff1f2', color: '#e11d48', border: '1px solid #fecdd3' })
				.html('<span class="dashicons dashicons-lock"></span> مسدودسازی دسترسی کاربر');
			$alert.hide();
		}
		$btn.attr('data-restricted', isRestricted ? '1' : '0');
	}

	// Close Modal
	$('#singo-btn-close-dossier').on('click', function() {
		$('#singo-user-dossier-modal').fadeOut(200);
	});

	// Toggle Restriction (Inside Modal & From Main Table)
	$(document).on('click', '#singo-dossier-toggle-restrict-btn, .singo-btn-toggle-restrict', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var userId = $btn.attr('data-user-id') || currentDossierUserId;
		var currentRestricted = $btn.attr('data-restricted') === '1';
		var newRestricted = !currentRestricted;

		var confirmMsg = newRestricted
			? 'آیا از مسدودسازی دسترسی این کاربر به دریافت لیست قیمت اطمینان دارید؟'
			: 'آیا از رفع محدودیت این کاربر اطمینان دارید؟';

		if (!confirm(confirmMsg)) {
			return;
		}

		var reason = '';
		if (newRestricted) {
			reason = prompt('علت محدودیت (اختیاری):', '') || '';
		}

		$btn.prop('disabled', true);

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'singo_admin_toggle_user_restriction',
				security: ajaxNonce,
				user_id: userId,
				restricted: newRestricted ? 1 : 0,
				reason: reason
			},
			success: function(res) {
				$btn.prop('disabled', false);
				if (res.success) {
					// Update Table Row if exists
					var $row = $('.singo-user-report-row[data-user-id="' + userId + '"]');
					if ($row.length) {
						$row.attr('data-status', newRestricted ? 'restricted' : 'active');
						var $badge = $row.find('.singo-status-badge');
						var $actionBtn = $row.find('.singo-btn-toggle-restrict');

						if (newRestricted) {
							$badge.removeClass('singo-status-badge--active').addClass('singo-status-badge--restricted')
								.css({ background: '#ffe4e6', color: '#e11d48' })
								.html('<span class="dashicons dashicons-lock" style="font-size:13px;width:13px;height:13px;"></span> محدودشده');
							$actionBtn.attr('data-restricted', '1')
								.css({ background: '#f0fdf4', color: '#16a34a', border: '1px solid #bbf7d0' })
								.html('<span class="dashicons dashicons-unlock" style="font-size:15px;width:15px;height:15px;"></span> آزاد');
						} else {
							$badge.removeClass('singo-status-badge--restricted').addClass('singo-status-badge--active')
								.css({ background: '#dcfce7', color: '#15803d' })
								.html('<span class="dashicons dashicons-yes" style="font-size:13px;width:13px;height:13px;"></span> مجاز');
							$actionBtn.attr('data-restricted', '0')
								.css({ background: '#fff1f2', color: '#e11d48', border: '1px solid #fecdd3' })
								.html('<span class="dashicons dashicons-lock" style="font-size:15px;width:15px;height:15px;"></span> محدود');
						}
					}

					// Update Dossier Modal if open
					if (currentDossierUserId === parseInt(userId, 10)) {
						updateDossierRestrictionUI(newRestricted, reason);
					}

					if (res.data && res.data.total_restricted !== undefined) {
						$('#singo-stat-total-restricted').text(res.data.total_restricted);
					}
				} else {
					alert(res.data && res.data.message ? res.data.message : 'خطا در تغییر وضعیت دسترسی کاربر.');
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				alert('خطا در برقراری ارتباط با سرور.');
			}
		});
	});

	// Delete Individual Log Item
	$(document).on('click', '.singo-btn-delete-log-item', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var userId = $btn.attr('data-user-id');
		var logId = $btn.attr('data-log-id');

		if (!confirm('آیا از حذف این فایل از تاریخچه مدیریت اطمینان دارید؟')) {
			return;
		}

		$btn.prop('disabled', true);

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'singo_admin_delete_download_log',
				security: ajaxNonce,
				user_id: userId,
				log_id: logId
			},
			success: function(res) {
				if (res.success) {
					$('#singo-dossier-row-' + logId).fadeOut(200, function() {
						$(this).remove();
					});
				} else {
					alert('خطا در حذف رکورد.');
					$btn.prop('disabled', false);
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور.');
				$btn.prop('disabled', false);
			}
		});
	});

	// Delete All Logs for a User from Table
	$(document).on('click', '.singo-btn-delete-user-logs', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var userId = $btn.attr('data-user-id');

		if (!confirm('آیا از حذف کامل سوابق دانلود این کاربر اطمینان دارید؟')) {
			return;
		}

		$btn.prop('disabled', true);

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'singo_admin_delete_download_log',
				security: ajaxNonce,
				user_id: userId,
				clear_user_all: 1
			},
			success: function(res) {
				if (res.success) {
					$('.singo-user-report-row[data-user-id="' + userId + '"]').fadeOut(250, function() {
						$(this).remove();
						if ($('.singo-user-report-row').length === 0) {
							location.reload();
						}
					});
				} else {
					alert('خطا در حذف سوابق کاربر.');
					$btn.prop('disabled', false);
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور.');
				$btn.prop('disabled', false);
			}
		});
	});

	// Clear All Logs
	$('#singo-btn-clear-all-logs').on('click', function(e) {
		e.preventDefault();
		if (!confirm('هشدار: آیا از پاکسازی تمامی سوابق دانلود کاربران اطمینان دارید؟\nاین عملیات غیرقابل بازگشت است.')) {
			return;
		}
		if (!confirm('جهت تایید نهایی، آیا کاملاً مطمئن هستید؟')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true).text('در حال پاکسازی...');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'singo_admin_clear_all_download_logs',
				security: ajaxNonce
			},
			success: function(res) {
				if (res.success) {
					alert('کلیه گزارشات دانلود با موفقیت پاکسازی شدند.');
					location.reload();
				} else {
					alert('خطا در پاکسازی.');
					$btn.prop('disabled', false).text('پاکسازی کل گزارشات');
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور.');
				$btn.prop('disabled', false).text('پاکسازی کل گزارشات');
			}
		});
	});

	// Quick Restrict Modal
	$('#singo-btn-quick-restrict-modal').on('click', function() {
		$('#singo-quick-restrict-input').val('');
		$('#singo-quick-restrict-reason').val('');
		$('#singo-quick-restrict-modal').css('display', 'flex').hide().fadeIn(200);
	});

	$('#singo-btn-submit-quick-restrict').on('click', function() {
		var input = ($('#singo-quick-restrict-input').val() || '').trim();
		var reason = ($('#singo-quick-restrict-reason').val() || '').trim();
		if (!input) {
			alert('لطفاً شناسه کاربری یا شماره موبایل را وارد نمایید.');
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true).text('در حال ثبت...');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'singo_admin_toggle_user_restriction',
				security: ajaxNonce,
				user_identifier: input,
				restricted: 1,
				reason: reason
			},
			success: function(res) {
				$btn.prop('disabled', false).text('ثبت محدودیت');
				if (res.success) {
					alert(res.data && res.data.message ? res.data.message : 'کاربر با موفقیت محدود شد.');
					location.reload();
				} else {
					alert(res.data && res.data.message ? res.data.message : 'کاربر با این شناسه یا شماره یافت نشد.');
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('ثبت محدودیت');
				alert('خطا در ارتباط با سرور.');
			}
		});
	});

});
</script>
