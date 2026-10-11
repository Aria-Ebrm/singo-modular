<?php
declare(strict_types=1);

/**
 * Singo Tools - Admin Optionino View for Price List PDF Export Tab
 */

defined( 'ABSPATH' ) || exit;

// Fetch all product categories
$categories = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
) );

// Resolve Default Site Logo (Only use site custom logo if configured in WordPress customizer)
$default_logo = '';
$site_logo_id = get_theme_mod( 'custom_logo' );
if ( $site_logo_id ) {
	$default_logo = (string) wp_get_attachment_image_url( (int) $site_logo_id, 'full' );
}

// Resolve Saved Font URL
$saved_font_url = (string) get_option( 'singo_price_list_font_url', '' );

// Resolve Saved Primary Brand Color
$saved_primary_color = class_exists( 'Singo_Price_List' )
	? Singo_Price_List::get_active_primary_color()
	: (string) get_option( 'singo_price_list_primary_color', '#ad6141' );
if ( empty( $saved_primary_color ) ) {
	$saved_primary_color = '#ad6141';
}

$export_nonce = wp_create_nonce( 'singo_export_price_list_nonce' );
$ajax_nonce   = wp_create_nonce( 'singo_price_list_nonce' );
?>

<style>
/* Singo Price List & Optionino Button Styling */
.singo-price-list-admin-wrap button,
.singo-price-list-admin-wrap .button {
	font-family: inherit !important;
}

.singo-price-list-admin-wrap .singo-btn-primary {
	background-color: #ad6141 !important;
	border: 1px solid #ad6141 !important;
	color: #ffffff !important;
	transition: all 0.2s ease !important;
	text-decoration: none !important;
	cursor: pointer !important;
}
.singo-price-list-admin-wrap .singo-btn-primary:hover,
.singo-price-list-admin-wrap .singo-btn-primary:focus {
	background-color: #8e4c31 !important;
	border-color: #8e4c31 !important;
	color: #ffffff !important;
	box-shadow: 0 2px 6px rgba(173,97,65,0.3) !important;
}
.singo-price-list-admin-wrap .singo-btn-primary .dashicons {
	color: #ffffff !important;
	fill: #ffffff !important;
}

.singo-price-list-admin-wrap .singo-btn-secondary {
	background-color: #ffffff !important;
	border: 1px solid #cbd5e1 !important;
	color: #334155 !important;
	transition: all 0.2s ease !important;
	text-decoration: none !important;
	cursor: pointer !important;
}
.singo-price-list-admin-wrap .singo-btn-secondary:hover,
.singo-price-list-admin-wrap .singo-btn-secondary:focus {
	border-color: #ad6141 !important;
	color: #ad6141 !important;
	background-color: #faf5f3 !important;
}
.singo-price-list-admin-wrap .singo-btn-secondary .dashicons {
	color: #ad6141 !important;
	fill: #ad6141 !important;
	transition: color 0.2s ease !important;
}

/* Stacked Discount Pricing in Preview Table */
.singo-preview-price-cell {
	text-align: center !important;
	vertical-align: middle !important;
}
.singo-preview-price-cell .singo-price-sale {
	font-weight: 700 !important;
	font-size: 13px !important;
	line-height: 1.2 !important;
}
.singo-preview-price-cell .singo-price-regular,
.singo-preview-price-cell del {
	display: block !important;
	text-decoration: line-through !important;
	color: #94a3b8 !important;
	font-size: 11px !important;
	font-weight: normal !important;
	margin-top: 2px !important;
	line-height: 1.2 !important;
}

/* Hide Optionino Global Save Changes button when Price List tab is visible */
form#save-options-optionino:has(#price_list_pdf:not([style*="display: none"])) .optionino-options-save-box {
	display: none !important;
}
</style>

<div class="singo-price-list-admin-wrap" style="direction: rtl; text-align: right; padding-top: 5px;">

	<!-- Top Header Information Banner -->
	<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
		<div style="display: flex; align-items: center; gap: 14px;">
			<div style="background: #ad6141; color: #fff; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 22px;">
				<span class="dashicons dashicons-media-document" style="font-size: 26px; width: 26px; height: 26px; color: #ffffff !important; fill: #ffffff !important;"></span>
			</div>
			<div>
				<h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b;">سیستم تولید و خروجی لیست قیمت PDF</h3>
				<p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">تولید کاتالوگ و لیست قیمت رسمی محصولات ووکامرس در ابعاد استاندارد A4 با تاریخ روز شمسی و لوگو.</p>
			</div>
		</div>
	</div>

	<!-- Configuration Box -->
	<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 22px; margin-bottom: 24px;">
		<h4 style="margin: 0 0 18px 0; font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
			<span class="dashicons dashicons-filter" style="color: #ad6141;"></span>
			تنظیمات فیلترها و مشخصات سند
		</h4>

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px;">
			
			<!-- Field 1: Category Selector -->
			<div>
				<label for="singo-price-category" style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13.5px; color: #334155;">
					انتخاب دسته‌بندی محصولات:
				</label>
				<select id="singo-price-category" class="optionino-select" style="width: 100%; height: 38px; font-family: inherit; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1;">
					<option value="0">همه دسته‌بندی‌ها (تمامی محصولات فروشگاه)</option>
					<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>">
								<?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( (string) $cat->count ); ?> کالا)
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">محصولات دسته‌بندی انتخابی در فایل خروجی درج خواهند شد.</p>
			</div>

			<!-- Field 2: Sort Order -->
			<div>
				<label for="singo-price-orderby" style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13.5px; color: #334155;">
					نحوه مرتب‌سازی:
				</label>
				<select id="singo-price-orderby" class="optionino-select" style="width: 100%; height: 38px; font-family: inherit; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1;">
					<option value="title">نام محصول (الفبایی الف تا ی)</option>
					<option value="price_asc">قیمت (از ارزان‌ترین به گران‌ترین)</option>
					<option value="price_desc">قیمت (از گران‌ترین به ارزان‌ترین)</option>
					<option value="date">جدیدترین محصولات اضافه شده</option>
				</select>
				<p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">ترتیب چیدمان ردیف‌های محصولات در جدول خروجی.</p>
			</div>

			<!-- Field 3: Stock Status -->
			<div>
				<label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13.5px; color: #334155;">
					وضعیت موجودی انبار:
				</label>
				<label style="display: flex; align-items: center; gap: 8px; height: 38px; cursor: pointer; user-select: none; font-size: 13.5px; font-weight: 600; color: #1e293b;">
					<input type="checkbox" id="singo-price-instock" value="1" checked style="width: 18px; height: 18px; cursor: pointer;" />
					<span>فقط کالاهای موجود در انبار (عدم درج کالاهای ناموجود)</span>
				</label>
				<p style="margin: 0; font-size: 12px; color: #64748b;">با غیرفعال کردن این گزینه، کالاهای ناموجود نیز با عنوان تماس بگیرید درج می‌شوند.</p>
			</div>

		</div>

		<!-- Pricing, Discount & Variations Controls -->
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-top: 18px; border-top: 1px dashed #e2e8f0; padding-top: 18px;">
			
			<!-- Field: Global Discount Percentage -->
			<div>
				<label for="singo-price-discount-percent" style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13.5px; color: #334155;">
					درصد تخفیف کلی روی همه محصولات:
				</label>
				<div style="display: flex; align-items: center; gap: 8px;">
					<input type="number" id="singo-price-discount-percent" min="0" max="100" step="1" value="0" class="optionino-input" placeholder="0" style="width: 90px; height: 38px; text-align: center; font-weight: 700; font-size: 14px; border-radius: 6px; border: 1px solid #cbd5e1;" />
					<span style="font-weight: 700; font-size: 14px; color: #64748b;">درصد (٪)</span>
				</div>
				<p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">تخفیف تعیین‌شده به صورت همگانی بر روی کلیه کالاهای لیست اعمال می‌گردد.</p>
			</div>

			<!-- Field: Hide Strikethrough Price Switcher -->
			<div>
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
					<label for="singo-price-hide-strike" style="font-weight: 700; font-size: 13.5px; color: #334155; margin: 0;">
						مخفی‌سازی قیمت خط‌خورده:
					</label>
					<div class="optionino-switch-box" style="transform: scale(0.8); transform-origin: left center;">
						<input type="checkbox" class="optionino-switch-checkbox" id="singo-price-hide-strike" value="1" style="display:none;" />
						<label class="optionino-switch-label" for="singo-price-hide-strike" title="عدم نمایش قیمت خط‌خورده و نمایش فقط قیمت نهایی"></label>
					</div>
				</div>
				<p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">با فعال‌سازی، قیمت خط‌خورده قبلی حذف شده و صرفاً قیمت نهایی فروش درج می‌شود.</p>
			</div>

			<!-- Field: Expand Variations Switcher -->
			<div>
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
					<label for="singo-price-expand-variations" style="font-weight: 700; font-size: 13.5px; color: #334155; margin: 0;">
						تفکیک مدل‌های محصولات متغیر:
					</label>
					<div class="optionino-switch-box" style="transform: scale(0.8); transform-origin: left center;">
						<input type="checkbox" class="optionino-switch-checkbox" id="singo-price-expand-variations" value="1" checked style="display:none;" />
						<label class="optionino-switch-label" for="singo-price-expand-variations" title="نمایش هر مدل/تنوع در یک سطر جداگانه با عکس و مشخصات"></label>
					</div>
				</div>
				<p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">هر مدل محصول همراه با تصویر اختصاصی و ویژگی‌ها در ردیف مجزا چاپ می‌شود.</p>
			</div>

		</div>

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-top: 18px; border-top: 1px dashed #e2e8f0; padding-top: 18px;">
			
			<!-- Field 4: Document Title -->
			<div>
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
					<label for="singo-price-title" style="font-weight: 700; font-size: 13.5px; color: #334155; margin: 0;">
						عنوان سربرگ لیست قیمت:
					</label>
					<div class="optionino-switch-box" style="transform: scale(0.8); transform-origin: left center;">
						<input type="checkbox" class="optionino-switch-checkbox singo-section-toggle" id="singo-price-enable-title" data-target="#singo-title-field-wrap" value="1" checked style="display:none;" />
						<label class="optionino-switch-label" for="singo-price-enable-title" title="نمایش / عدم نمایش عنوان در سربرگ"></label>
					</div>
				</div>
				<div id="singo-title-field-wrap">
					<input type="text" id="singo-price-title" class="optionino-input" value="لیست قیمت رسمی محصولات" placeholder="مثال: لیست قیمت همکار یا کاتالوگ فروردین..." style="width: 100%; height: 38px; font-family: inherit; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px;" />
					<p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">عنوان در مرکز هدر بالای تمامی صفحات PDF درج می‌گردد.</p>
				</div>
			</div>

			<!-- Field 5: Footer Notes -->
			<div>
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
					<label for="singo-price-notes" style="font-weight: 700; font-size: 13.5px; color: #334155; margin: 0;">
						یادداشت و توضیحات پاورقی:
					</label>
					<div class="optionino-switch-box" style="transform: scale(0.8); transform-origin: left center;">
						<input type="checkbox" class="optionino-switch-checkbox singo-section-toggle" id="singo-price-enable-notes" data-target="#singo-notes-field-wrap" value="1" checked style="display:none;" />
						<label class="optionino-switch-label" for="singo-price-enable-notes" title="نمایش / عدم نمایش توضیحات در پاورقی"></label>
					</div>
				</div>
				<div id="singo-notes-field-wrap">
					<input type="text" id="singo-price-notes" class="optionino-input" value="قیمت‌ها به تومان می‌باشد. جهت استعلام و ثبت سفارش قطعی تماس حاصل فرمایید." style="width: 100%; height: 38px; font-family: inherit; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px;" />
					<p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">توضیحات کوتاه در فوتر پایین صفحات نمایش داده می‌شود.</p>
				</div>
			</div>

			<!-- Field 6: Logo Chooser -->
			<div>
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
					<label style="font-weight: 700; font-size: 13.5px; color: #334155; margin: 0;">
						لوگوی سربرگ (سمت راست):
					</label>
					<div class="optionino-switch-box" style="transform: scale(0.8); transform-origin: left center;">
						<input type="checkbox" class="optionino-switch-checkbox singo-section-toggle" id="singo-price-enable-logo" data-target="#singo-logo-field-wrap" value="1" <?php checked( ! empty( $default_logo ) ); ?> style="display:none;" />
						<label class="optionino-switch-label" for="singo-price-enable-logo" title="نمایش / عدم نمایش لوگو در سربرگ"></label>
					</div>
				</div>
				<div id="singo-logo-field-wrap" style="<?php echo empty( $default_logo ) ? 'display: none;' : ''; ?>">
					<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
						<input type="hidden" id="singo-price-logo-url" value="<?php echo esc_url( $default_logo ); ?>" />
						<div id="singo-logo-preview-box" style="width: 50px; height: 38px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
							<?php if ( ! empty( $default_logo ) ) : ?>
								<img id="singo-logo-preview-img" src="<?php echo esc_url( $default_logo ); ?>" alt="لوگو" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
							<?php else : ?>
								<span id="singo-logo-placeholder" style="font-size: 10px; color: #94a3b8;">بدون لوگو</span>
							<?php endif; ?>
						</div>
						<button type="button" id="singo-btn-change-logo" class="singo-btn-secondary" style="height: 38px; font-size: 13px; font-weight: 600; padding: 0 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
							<span class="dashicons dashicons-format-image" style="font-size: 16px; width: 16px; height: 16px; color: #ad6141 !important;"></span>
							انتخاب لوگو
						</button>
						<?php if ( ! empty( $default_logo ) ) : ?>
							<button type="button" id="singo-btn-reset-logo" class="singo-btn-secondary" data-default="<?php echo esc_url( $default_logo ); ?>" style="height: 38px; font-size: 13px; font-weight: 600; padding: 0 10px; border-radius: 6px;">
								پیش‌فرض
							</button>
						<?php endif; ?>
						<button type="button" id="singo-btn-remove-logo" class="singo-btn-secondary" style="height: 38px; font-size: 13px; font-weight: 600; padding: 0 10px; border-radius: 6px;" title="حذف لوگوی سربرگ">
							حذف
						</button>
					</div>
					<p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">لوگو در گوشه بالا سمت راست همه صفحات چاپ خواهد شد.</p>
				</div>
			</div>

		</div>

		<!-- Field 7: Custom Font Chooser & Direct URL -->
		<div style="margin-top: 18px; border-top: 1px dashed #e2e8f0; padding-top: 18px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
				<label style="font-weight: 700; font-size: 13.5px; color: #334155; margin: 0;">
					فایل فونت دلخواه قالب PDF (اختیاری):
				</label>
				<div class="optionino-switch-box" style="transform: scale(0.8); transform-origin: left center;">
					<input type="checkbox" class="optionino-switch-checkbox singo-section-toggle" id="singo-price-enable-font" data-target="#singo-font-field-wrap" value="1" <?php checked( ! empty( $saved_font_url ) ); ?> style="display:none;" />
					<label class="optionino-switch-label" for="singo-price-enable-font" title="فعال / غیرفعال‌سازی فونت سفارشی"></label>
				</div>
			</div>
			<div id="singo-font-field-wrap" style="<?php echo empty( $saved_font_url ) ? 'display: none;' : ''; ?>">
				<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<input type="text" id="singo-price-font-url" class="optionino-input" value="<?php echo esc_attr( $saved_font_url ); ?>" placeholder="آدرس مستقیم فایل فونت در هاست شما (مثلاً https://.../font.woff2) یا دکمه آپلود..." style="flex: 1; min-width: 280px; height: 38px; direction: ltr; text-align: left; font-family: monospace; font-size: 12.5px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px;" />
					
					<button type="button" id="singo-btn-upload-font" class="singo-btn-secondary" style="height: 38px; font-size: 13px; font-weight: 600; padding: 0 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
						<span class="dashicons dashicons-upload" style="font-size: 16px; width: 16px; height: 16px; color: #ad6141 !important;"></span>
						آپلود / انتخاب فونت
					</button>

					<button type="button" id="singo-btn-save-font" class="singo-btn-primary" style="height: 38px; font-size: 13px; font-weight: 600; padding: 0 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;" title="ذخیره آدرس فونت">
						<span class="dashicons dashicons-saved" style="font-size: 16px; width: 16px; height: 16px; color: #ffffff !important; fill: #ffffff !important;"></span>
						ذخیره آدرس
					</button>

					<button type="button" id="singo-btn-reset-font" class="singo-btn-secondary" style="height: 38px; font-size: 13px; font-weight: 600; padding: 0 12px; border-radius: 6px; white-space: nowrap;" title="پاک کردن آدرس و استفاده از فونت قالب یا پیش‌فرض سایت">
						پاک کردن
					</button>

					<span id="singo-font-status" style="font-size: 12.5px; font-weight: 600; display: none;"></span>
				</div>
				<p style="margin: 6px 0 0 0; font-size: 12px; color: #64748b;">
					در صورت تمایل به استفاده از فایل فونت سفارشی، آدرس مستقیم فایل فونت (.woff2 / .woff / .ttf) را در کادر بالا بنویسید یا با کلیک روی «آپلود / انتخاب فونت» آن را انتخاب کنید؛ در غیر این صورت از فونت استاندارد پیش‌فرض سایت استفاده خواهد شد.
				</p>
			</div>
		</div>

		<!-- Field 8: Brand / Primary Color Picker -->
		<div style="margin-top: 18px; border-top: 1px dashed #e2e8f0; padding-top: 18px;">
			<label for="singo-price-primary-color" style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13.5px; color: #334155;">
				رنگ سازمانی و اصلی قالب PDF:
			</label>
			<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
				<input type="text" class="optionino-color-selector" id="singo-price-primary-color" name="singo-price-primary-color" value="<?php echo esc_attr( $saved_primary_color ); ?>" placeholder="#ad6141" pattern="^#?[0-9A-Fa-f]{3,8}$" data-default-color="#ad6141" style="height: 38px; width: 110px; font-family: monospace; font-size: 13px; text-align: left; direction: ltr;" />

				<button type="button" id="singo-btn-save-color" class="singo-btn-primary" style="height: 38px; font-size: 13px; font-weight: 600; padding: 0 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;" title="ذخیره رنگ سازمانی در دیتابیس">
					<span class="dashicons dashicons-saved" style="font-size: 16px; width: 16px; height: 16px; color: #ffffff !important; fill: #ffffff !important;"></span>
					ذخیره رنگ
				</button>

				<span id="singo-color-status" style="font-size: 12.5px; font-weight: 600; display: none;"></span>
			</div>
			<p style="margin: 6px 0 0 0; font-size: 12px; color: #64748b;">
				رنگ خطوط سربرگ، عنوان سند، دکمه‌های ابزار و ارقام قیمت محصولات در فایل خروجی PDF با این رنگ تنظیم می‌شود. با زدن دکمه «ذخیره رنگ»، این انتخاب در دیتابیس ماندگار خواهد شد.
			</p>
		</div>

		<!-- Action Buttons Row -->
		<div style="margin-top: 22px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
			<button type="button" id="singo-btn-trigger-pdf" class="singo-btn-primary" style="height: 38px; padding: 0 20px; font-size: 13.5px; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(173,97,65,0.2);">
				<span class="dashicons dashicons-download" style="font-size: 18px; width: 18px; height: 18px; color: #ffffff !important; fill: #ffffff !important;"></span>
				دانلود مستقیم و چاپ PDF
			</button>

			<button type="button" id="singo-btn-view-template" class="singo-btn-secondary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px; color: #ad6141 !important;"></span>
				مشاهده برگه کامل در تب جدید
			</button>

			<button type="button" id="singo-btn-load-preview" class="singo-btn-primary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; margin-right: auto;">
				<span class="dashicons dashicons-visibility" style="font-size: 16px; width: 16px; height: 16px; color: #ffffff !important;"></span>
				مشاهده پیش‌نمایش جدول (۳۰ محصول)
			</button>

			<button type="button" id="singo-btn-refresh-preview" class="singo-btn-secondary" style="height: 38px; padding: 0 14px; font-size: 13px; font-weight: 600; border-radius: 6px; display: none; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; color: #ad6141 !important;"></span>
				بروزرسانی پیش‌نمایش جدول
			</button>
		</div>

	</div>

	<!-- Live Table Preview Box -->
	<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
		
		<div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
			<div style="display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 14.5px; color: #1e293b;">
				<span class="dashicons dashicons-visibility" style="color: #ad6141;"></span>
				<span>پیش‌نمایش زنده جدول محصولات</span>
				<span style="font-size: 12px; color: #64748b; font-weight: normal;">(قیمت‌ها به تومان - حداکثر ۳۰ قلم برای سرعت بالا)</span>
			</div>
			<div style="font-size: 13px; font-weight: 700; color: #ad6141; background: #fff; border: 1px solid #e2e8f0; padding: 4px 12px; border-radius: 20px;">
				تعداد محصولات: <span id="singo-preview-count">در انتظار مشاهده</span>
			</div>
		</div>

		<div style="overflow-x: auto; max-height: 480px;">
			<table class="widefat striped" style="border: none; width: 100%; border-collapse: collapse; table-layout: fixed;">
				<colgroup>
					<col style="width: 5%;">
					<col style="width: 10%;">
					<col style="width: 60%;">
					<col style="width: 25%;">
				</colgroup>
				<thead>
					<tr style="background: #f1f5f9;">
						<th style="width: 5%; text-align: center; font-weight: 700; padding: 12px 4px; border-bottom: 2px solid #e2e8f0;">ردیف</th>
						<th style="width: 10%; text-align: center; font-weight: 700; padding: 12px 4px; border-bottom: 2px solid #e2e8f0;">عکس</th>
						<th style="width: 60%; text-align: right; font-weight: 700; padding: 12px 14px; border-bottom: 2px solid #e2e8f0;">اسم محصول</th>
						<th style="width: 25%; text-align: center; font-weight: 700; padding: 12px 8px; border-bottom: 2px solid #e2e8f0;">قیمت (تومان)</th>
					</tr>
				</thead>
				<tbody id="singo-preview-tbody">
					<tr>
						<td colspan="4" style="text-align: center; padding: 45px 20px; color: #64748b; background: #fafbfc;">
							<div style="max-width: 440px; margin: 0 auto;">
								<span class="dashicons dashicons-visibility" style="font-size: 32px; width: 32px; height: 32px; color: #ad6141; display: block; margin: 0 auto 10px;"></span>
								<strong style="font-size: 14px; color: #334155; display: block; margin-bottom: 6px;">پیش‌نمایش جدول هنوز بارگذاری نشده است</strong>
								<p style="font-size: 12.5px; color: #64748b; margin: 0 0 14px 0; line-height: 1.6;">جهت افزایش چشمگیر سرعت لود صفحه و جلوگیری از دانلود همزمان صدها تصویر، پیش‌نمایش جدول به صورت خودکار لود نمی‌شود.</p>
								<button type="button" class="singo-btn-primary singo-btn-trigger-load-preview" style="height: 36px; padding: 0 18px; font-size: 12.5px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
									<span class="dashicons dashicons-visibility"></span>
									مشاهده پیش‌نمایش جدول (۳۰ محصول اول)
								</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

	</div>

</div>

<!-- Interactive Client-side Script -->
<script>
jQuery(document).ready(function($) {
	var exportBaseUrl = '<?php echo esc_url_raw( admin_url( 'admin-post.php' ) ); ?>';
	var exportAction  = 'singo_export_price_list_pdf';
	var exportNonce   = '<?php echo esc_js( $export_nonce ); ?>';
	var ajaxUrl       = '<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>';
	var ajaxNonce     = '<?php echo esc_js( $ajax_nonce ); ?>';

	// Build full export URL with current user inputs
	function buildExportUrl(autoprint) {
		var catId            = encodeURIComponent($('#singo-price-category').val() || '0');
		var orderby          = encodeURIComponent($('#singo-price-orderby').val() || 'title');
		var inStock          = $('#singo-price-instock').is(':checked') ? '1' : '0';
		var discountPercent  = encodeURIComponent($('#singo-price-discount-percent').val() || '0');
		var hideStrike       = $('#singo-price-hide-strike').is(':checked') ? '1' : '0';
		var expandVariations = $('#singo-price-expand-variations').is(':checked') ? '1' : '0';
		var showTitle        = $('#singo-price-enable-title').is(':checked') ? '1' : '0';
		var showNotes        = $('#singo-price-enable-notes').is(':checked') ? '1' : '0';
		var showLogo         = $('#singo-price-enable-logo').is(':checked') ? '1' : '0';
		var showFont         = $('#singo-price-enable-font').is(':checked') ? '1' : '0';
		var title            = showTitle === '1' ? encodeURIComponent($('#singo-price-title').val() || '') : '';
		var notes            = showNotes === '1' ? encodeURIComponent($('#singo-price-notes').val() || '') : '';
		var logoUrl          = showLogo === '1' ? encodeURIComponent($('#singo-price-logo-url').val() || '') : '';
		var fontUrl          = showFont === '1' ? encodeURIComponent($('#singo-price-font-url').val().trim()) : '';
		var primaryColor     = encodeURIComponent($('#singo-price-primary-color').val().trim() || '#ad6141');

		var url = exportBaseUrl + 
			'?action=' + exportAction + 
			'&security=' + exportNonce + 
			'&category_id=' + catId + 
			'&orderby=' + orderby + 
			'&in_stock=' + inStock + 
			'&discount_percent=' + discountPercent + 
			'&hide_strike=' + hideStrike + 
			'&expand_variations=' + expandVariations + 
			'&show_title=' + showTitle + 
			'&show_notes=' + showNotes + 
			'&show_logo=' + showLogo + 
			'&show_font=' + showFont + 
			'&title=' + title + 
			'&notes=' + notes + 
			'&logo_url=' + logoUrl + 
			'&font_url=' + fontUrl + 
			'&primary_color=' + primaryColor;

		if (autoprint) {
			url += '&autoprint=1';
		}

		return url;
	}

	// Optionino Switchers Toggle Handler
	$('.singo-section-toggle').on('change', function() {
		var $input = $(this);
		var target = $input.data('target');
		if ($input.is(':checked')) {
			$(target).stop(true, true).slideDown(200);
			if ($input.attr('id') === 'singo-price-enable-font') {
				applyDynamicFont($('#singo-price-font-url').val().trim());
			}
		} else {
			$(target).stop(true, true).slideUp(200);
			if ($input.attr('id') === 'singo-price-enable-font') {
				applyDynamicFont('');
			}
		}
	});

	// Trigger Direct Print / Save as PDF
	$('#singo-btn-trigger-pdf').on('click', function(e) {
		e.preventDefault();
		var targetUrl = buildExportUrl(true);
		window.open(targetUrl, '_blank');
	});

	// View Template in New Tab (Without Auto Print)
	$('#singo-btn-view-template').on('click', function(e) {
		e.preventDefault();
		var targetUrl = buildExportUrl(false);
		window.open(targetUrl, '_blank');
	});

	// Logo Media Selector
	$('#singo-btn-change-logo').on('click', function(e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) {
			alert('کتابخانه چندرسانه‌ای وردپرس در دسترس نیست.');
			return;
		}

		var frame = wp.media({
			title: 'انتخاب یا آپلود لوگوی سربرگ لیست قیمت',
			button: { text: 'استفاده به عنوان لوگوی سربرگ' },
			multiple: false
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			if (attachment && attachment.url) {
				$('#singo-price-logo-url').val(attachment.url);
				$('#singo-logo-preview-box').html('<img id="singo-logo-preview-img" src="' + attachment.url + '" alt="لوگو" style="max-width: 100%; max-height: 100%; object-fit: contain;" />');
			}
		});

		frame.open();
	});

	// Reset Logo to Site Default
	$('#singo-btn-reset-logo').on('click', function(e) {
		e.preventDefault();
		var defaultLogo = $(this).data('default') || '';
		$('#singo-price-logo-url').val(defaultLogo);
		if (defaultLogo) {
			$('#singo-logo-preview-box').html('<img id="singo-logo-preview-img" src="' + defaultLogo + '" alt="لوگو" style="max-width: 100%; max-height: 100%; object-fit: contain;" />');
		} else {
			$('#singo-logo-preview-box').html('<span id="singo-logo-placeholder" style="font-size: 10px; color: #94a3b8;">بدون لوگو</span>');
		}
	});

	// Remove Logo Button
	$('#singo-btn-remove-logo').on('click', function(e) {
		e.preventDefault();
		$('#singo-price-logo-url').val('');
		$('#singo-logo-preview-box').html('<span id="singo-logo-placeholder" style="font-size: 10px; color: #94a3b8;">بدون لوگو</span>');
	});

	// Font Media Selector (Upload / Choose Font from Media Library)
	$('#singo-btn-upload-font').on('click', function(e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) {
			alert('کتابخانه چندرسانه‌ای وردپرس در دسترس نیست.');
			return;
		}

		var fontFrame = wp.media({
			title: 'انتخاب یا آپلود فایل فونت (WOFF2, WOFF, TTF, OTF)',
			button: { text: 'استفاده از این فایل فونت' },
			multiple: false
		});

		fontFrame.on('select', function() {
			var attachment = fontFrame.state().get('selection').first().toJSON();
			if (attachment && attachment.url) {
				$('#singo-price-font-url').val(attachment.url);
				saveFontSetting(attachment.url);
				applyDynamicFont(attachment.url);
			}
		});

		fontFrame.open();
	});

	// Save Font Setting via AJAX
	function saveFontSetting(url) {
		var $status = $('#singo-font-status');
		$status.stop().css({ color: '#ad6141', display: 'inline-block' }).text('در حال ذخیره...');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'singo_save_price_list_font_url',
				security: ajaxNonce,
				font_url: url
			},
			success: function(res) {
				if (res.success) {
					$status.css('color', '#16a34a').text('✓ آدرس فونت ذخیره شد');
					setTimeout(function() {
						$status.fadeOut(400);
					}, 3000);
				} else {
					$status.css('color', '#dc2626').text('خطا در ذخیره');
				}
			},
			error: function() {
				$status.css('color', '#dc2626').text('خطا در برقراری ارتباط');
			}
		});
	}

	// Manual Save Font Button
	$('#singo-btn-save-font').on('click', function(e) {
		e.preventDefault();
		var url = $('#singo-price-font-url').val().trim();
		saveFontSetting(url);
		applyDynamicFont(url);
	});

	// Reset / Clear Font URL
	$('#singo-btn-reset-font').on('click', function(e) {
		e.preventDefault();
		$('#singo-price-font-url').val('');
		saveFontSetting('');
		applyDynamicFont('');
	});

	// Dynamic Font Preview Application
	function applyDynamicFont(url) {
		$('#singo-dynamic-preview-font-style').remove();
		if (!url) return;

		var ext = url.split('.').pop().split(/[?#]/)[0].toLowerCase();
		var format = "format('woff2')";
		if (ext === 'woff') format = "format('woff')";
		else if (ext === 'ttf') format = "format('truetype')";
		else if (ext === 'otf') format = "format('opentype')";

		var css = "@font-face { font-family: 'SingoDynamicPreviewFont'; src: url('" + url + "') " + format + "; font-weight: 100 900; font-style: normal; font-display: swap; } " +
				  ".singo-price-list-admin-wrap table, .singo-price-list-admin-wrap table *, .singo-price-list-admin-wrap h3, .singo-price-list-admin-wrap h4, .singo-price-list-admin-wrap strong { font-family: 'SingoDynamicPreviewFont', inherit !important; }";
		$('<style id="singo-dynamic-preview-font-style">' + css + '</style>').appendTo('head');
	}

	// Dynamic preview update when URL typed or modified
	$('#singo-price-font-url').on('input change', function() {
		applyDynamicFont($(this).val().trim());
	});

	// Initial apply
	applyDynamicFont($('#singo-price-font-url').val().trim());

	// Save Primary Brand Color Setting via AJAX
	function savePrimaryColorSetting(color) {
		var $status = $('#singo-color-status');
		$status.stop().css({ color: '#ad6141', display: 'inline-block' }).text('در حال ذخیره...');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'singo_save_price_list_primary_color',
				security: ajaxNonce,
				primary_color: color
			},
			success: function(res) {
				if (res.success) {
					$status.css('color', '#16a34a').text('✓ رنگ سازمانی ذخیره شد');
					setTimeout(function() {
						$status.fadeOut(400);
					}, 3000);
				} else {
					$status.css('color', '#dc2626').text('خطا در ذخیره');
				}
			},
			error: function() {
				$status.css('color', '#dc2626').text('خطا در برقراری ارتباط');
			}
		});
	}

	// Helper: Apply selected brand color to preview table price cells
	function applyPreviewPriceColor(color) {
		$('.singo-preview-price-cell, .singo-preview-price-cell .singo-price-sale').css('color', color);
		$('.singo-preview-price-cell .singo-price-regular, .singo-preview-price-cell del').css('color', '#94a3b8');
	}

	// Manual Save Primary Color Button
	$('#singo-btn-save-color').on('click', function(e) {
		e.preventDefault();
		var color = $('#singo-price-primary-color').val().trim() || '#ad6141';
		savePrimaryColorSetting(color);
		applyPreviewPriceColor(color);
	});

	// Dynamic live color update on input/change
	$('#singo-price-primary-color').on('input change', function() {
		var val = $(this).val().trim();
		if (val) {
			applyPreviewPriceColor(val);
		}
	});

	// Flag to track whether preview was requested by user
	var isPreviewLoaded = false;
	var previewXhr = null;

	// AJAX Load Preview Table with In-Flight Request Cancellation & 30-item limit
	function loadPreviewTable() {
		var $tbody = $('#singo-preview-tbody');
		var $count = $('#singo-preview-count');
		var $loadBtn = $('#singo-btn-load-preview');
		var $refreshBtn = $('#singo-btn-refresh-preview');

		// Abort previous in-flight request to eliminate race conditions and reduce server load
		if (previewXhr && previewXhr.readyState !== 4) {
			previewXhr.abort();
		}

		$tbody.html('<tr><td colspan="4" style="text-align:center; padding:35px; color:#64748b;"><span class="dashicons dashicons-update" style="animation: rotation 1.5s infinite linear; font-size:20px; width:20px; height:20px; display:inline-block; vertical-align:middle; margin-left:6px;"></span> در حال بارگذاری پیش‌نمایش (حداکثر ۳۰ قلم)...</td></tr>');
		$count.text('در حال دریافت...');
		$refreshBtn.prop('disabled', true);
		$loadBtn.prop('disabled', true);

		previewXhr = $.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'singo_get_price_list_preview',
				security: ajaxNonce,
				category_id: $('#singo-price-category').val(),
				orderby: $('#singo-price-orderby').val(),
				in_stock: $('#singo-price-instock').is(':checked') ? 1 : 0,
				discount_percent: $('#singo-price-discount-percent').val() || 0,
				hide_strike: $('#singo-price-hide-strike').is(':checked') ? 1 : 0,
				expand_variations: $('#singo-price-expand-variations').is(':checked') ? 1 : 0
			},
			success: function(res) {
				$refreshBtn.prop('disabled', false);
				$loadBtn.prop('disabled', false);

				if (res.success && res.data) {
					isPreviewLoaded = true;
					$tbody.html(res.data.table_html);
					var countLabel = res.data.is_capped ? (res.data.count_fa + ' کالا (نمونه ۳۰تایی)') : (res.data.count_fa + ' کالا');
					$count.text(countLabel);

					$refreshBtn.css('display', 'inline-flex');
					$loadBtn.html('<span class="dashicons dashicons-yes"></span> پیش‌نمایش فعال شد').removeClass('singo-btn-primary').addClass('singo-btn-secondary');

					var currentColor = $('#singo-price-primary-color').val().trim() || '#ad6141';
					applyPreviewPriceColor(currentColor);
				} else {
					$tbody.html('<tr><td colspan="4" style="text-align:center; padding:30px; color:#ef4444;">خطا در دریافت اطلاعات محصولات.</td></tr>');
					$count.text('۰ کالا');
				}
			},
			error: function(xhr, status) {
				$refreshBtn.prop('disabled', false);
				$loadBtn.prop('disabled', false);

				if (status === 'abort') {
					return;
				}
				$tbody.html('<tr><td colspan="4" style="text-align:center; padding:30px; color:#ef4444;">خطا در برقراری ارتباط با سرور.</td></tr>');
				$count.text('۰ کالا');
			}
		});
	}

	// Trigger preview exclusively on explicit user click
	$(document).on('click', '#singo-btn-load-preview, .singo-btn-trigger-load-preview, #singo-btn-refresh-preview', function(e) {
		e.preventDefault();
		loadPreviewTable();
	});

	// Only re-fetch if preview has already been loaded by the user
	$('#singo-price-category, #singo-price-orderby, #singo-price-instock, #singo-price-discount-percent, #singo-price-hide-strike, #singo-price-expand-variations').on('change input', function() {
		if (isPreviewLoaded) {
			loadPreviewTable();
		}
	});

	// Synchronize Optionino Global Save Changes Button (Hide exclusively on Price List PDF tab)
	function syncSaveButtonVisibility() {
		var $tab = $('#price_list_pdf');
		var isPriceListTab = $tab.length && $tab.is(':visible') && $tab.css('display') !== 'none';
		if (isPriceListTab) {
			$('.optionino-options-save-box').stop(true, true).hide();
		} else {
			$('.optionino-options-save-box').stop(true, true).show();
		}
	}

	$(document).on('click', '.tablinks', function() {
		setTimeout(syncSaveButtonVisibility, 50);
	});
	setTimeout(syncSaveButtonVisibility, 100);
});
</script>
