<?php
declare(strict_types=1);

/**
 * Singo Tools - Admin Optionino View for Price List PDF Export Tab
 */

defined( 'ABSPATH' ) || exit;

// Fetch all product categories with padded counts (cached via transient for fast settings page load)
$categories = get_transient( 'singo_price_list_admin_cats' );
if ( false === $categories || ! is_array( $categories ) ) {
	$raw_categories = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'pad_counts' => true,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	// Organize categories hierarchically so children appear under parents
	$categories = array();
	if ( ! empty( $raw_categories ) && ! is_wp_error( $raw_categories ) ) {
		$visited    = array();
		$walk_terms = static function ( array $terms, int $parent_id, int $depth, callable $fn, array &$result, array &$visited ): void {
			if ( $depth > 20 ) {
				return;
			}
			foreach ( $terms as $term ) {
				$t_id = (int) $term->term_id;
				if ( isset( $visited[ $t_id ] ) ) {
					continue;
				}
				if ( (int) $term->parent === $parent_id ) {
					$visited[ $t_id ]    = true;
					$term->display_depth = $depth;
					$result[]            = $term;
					$fn( $terms, $t_id, $depth + 1, $fn, $result, $visited );
				}
			}
		};
		$walk_terms( $raw_categories, 0, 0, $walk_terms, $categories, $visited );

		// Append any leftover terms if hierarchy is detached
		if ( count( $categories ) < count( $raw_categories ) ) {
			$existing_ids = array_map( static fn( $t ) => (int) $t->term_id, $categories );
			foreach ( $raw_categories as $term ) {
				if ( ! in_array( (int) $term->term_id, $existing_ids, true ) ) {
					$term->display_depth = 0;
					$categories[]        = $term;
				}
			}
		}
	}
	set_transient( 'singo_price_list_admin_cats', $categories, DAY_IN_SECONDS );
}

// Resolve Saved Primary Brand Color (for table preview pricing display)
$saved_primary_color = class_exists( 'Singo_Price_List' )
	? Singo_Price_List::get_active_primary_color()
	: (string) get_option( 'singo_price_list_primary_color', '#ad6141' );
if ( empty( $saved_primary_color ) ) {
	$saved_primary_color = '#ad6141';
}

$export_nonce = wp_create_nonce( 'singo_export_price_list_nonce' );
$ajax_nonce   = wp_create_nonce( 'singo_price_list_nonce' );
?>

<script>
(function() {
	window.openTabOptnno = function(evt, tabName) {
		var targetId = tabName;
		if (!targetId) return;

		var tabcontent = document.getElementsByClassName('tabcontent');
		for (var i = 0; i < tabcontent.length; i++) {
			tabcontent[i].style.display = 'none';
			tabcontent[i].classList.remove('active');
		}

		var tablinks = document.getElementsByClassName('tablinks');
		for (var j = 0; j < tablinks.length; j++) {
			tablinks[j].classList.remove('active');
		}

		var targetEl = document.getElementById(targetId);
		if (targetEl) {
			targetEl.style.display = 'block';
			targetEl.classList.add('active');
		}

		var activeBtn = (evt && evt.currentTarget) ? evt.currentTarget : document.querySelector('.tablinks[onclick*="' + targetId + '"]');
		if (activeBtn) {
			activeBtn.classList.add('active');
		}

		var saveBoxes = document.getElementsByClassName('optionino-options-save-box');
		var hideSave = (targetId === 'price_list_export_tab' || targetId === 'price_list_category_colors_tab' || targetId === 'price_list_download_reports_tab');
		for (var k = 0; k < saveBoxes.length; k++) {
			saveBoxes[k].style.display = hideSave ? 'none' : '';
		}
	};

	var firstTab = document.getElementById('price_list_export_tab');
	if (firstTab) {
		firstTab.style.display = 'block';
	}
	var links = document.querySelectorAll('.tablinks');
	if (links.length && !document.querySelector('.tablinks.active')) {
		links[0].classList.add('active');
	}
})();
</script>

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
@keyframes singoSpin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}
.singo-spin {
	animation: singoSpin 0.8s linear infinite !important;
}
form#save-options-optionino:has(#price_list_pdf:not([style*="display: none"])) .optionino-options-save-box,
form#save-options-optionino:has(#price_list_export_tab:not([style*="display: none"])) .optionino-options-save-box,
form#save-options-optionino:has(#price_list_category_colors_tab:not([style*="display: none"])) .optionino-options-save-box,
form#save-options-optionino:has(#price_list_download_reports_tab:not([style*="display: none"])) .optionino-options-save-box {
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
						<?php foreach ( $categories as $cat ) :
							$depth_prefix = isset( $cat->display_depth ) && (int) $cat->display_depth > 0 ? str_repeat( '— ', (int) $cat->display_depth ) : '';
						?>
							<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>">
								<?php echo esc_html( $depth_prefix . $cat->name ); ?> (<?php echo esc_html( (string) $cat->count ); ?> کالا)
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

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 18px; border-top: 1px dashed #e2e8f0; padding-top: 18px; align-items: center;">
			
			<!-- Field 4: Document Title (Per-Export Quick Override) -->
			<div>
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
					<label for="singo-price-title" style="font-weight: 700; font-size: 13.5px; color: #334155; margin: 0;">
						عنوان سربرگ این خروجی (اختیاری):
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

			<!-- Notice & Quick Jump to Appearance Tab -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px;">
				<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
					<span class="dashicons dashicons-admin-appearance" style="color: #ad6141; font-size: 18px; width: 18px; height: 18px;"></span>
					<strong style="font-size: 13px; color: #1e293b;">تنظیمات هویت بصری، قالب و فوتر</strong>
				</div>
				<p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b; line-height: 1.5;">
					لوگوی سربرگ، رنگ سازمانی، فونت، کاور A4 سراسری و اطلاعات تماس فوتر از <strong>تب «قالب، کاور و ظاهر کلی PDF»</strong> خوانده می‌شوند.
				</p>
				<a href="javascript:void(0);" onclick="var t=document.querySelector('.tablinks[onclick*=\'price_list_contact_tab\']'); if(t){t.click();} else {jQuery('.tablinks').eq(1).click();}" style="font-size: 12.5px; font-weight: 700; color: #ad6141; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
					تنظیمات قالب، کاور و ظاهر PDF &larr;
				</a>
			</div>

		</div>

		<!-- Action Buttons Row -->
		<div style="margin-top: 22px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
			<button type="button" id="singo-btn-trigger-pdf" class="singo-btn-primary" style="height: 38px; padding: 0 20px; font-size: 13.5px; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(173,97,65,0.2);">
				<span class="dashicons dashicons-printer" style="font-size: 18px; width: 18px; height: 18px; color: #ffffff !important; fill: #ffffff !important;"></span>
				ذخیره به عنوان فایل PDF / چاپ
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
					<col style="width: 9%;">
					<col style="width: 48%;">
					<col style="width: 15%;">
					<col style="width: 23%;">
				</colgroup>
				<thead>
					<tr style="background: #f1f5f9;">
						<th style="width: 5%; text-align: center; font-weight: 700; padding: 12px 4px; border-bottom: 2px solid #e2e8f0;">ردیف</th>
						<th style="width: 9%; text-align: center; font-weight: 700; padding: 12px 4px; border-bottom: 2px solid #e2e8f0;">عکس</th>
						<th style="width: 48%; text-align: right; font-weight: 700; padding: 12px 14px; border-bottom: 2px solid #e2e8f0;">اسم محصول</th>
						<th style="width: 15%; text-align: center; font-weight: 700; padding: 12px 6px; border-bottom: 2px solid #e2e8f0;">برند</th>
						<th style="width: 23%; text-align: center; font-weight: 700; padding: 12px 8px; border-bottom: 2px solid #e2e8f0;">قیمت (تومان)</th>
					</tr>
				</thead>
				<tbody id="singo-preview-tbody">
					<tr>
						<td colspan="5" style="text-align: center; padding: 45px 20px; color: #64748b; background: #fafbfc;">
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
		var title            = showTitle === '1' ? encodeURIComponent($('#singo-price-title').val() || '') : '';

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
			'&title=' + title;

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
		} else {
			$(target).stop(true, true).slideUp(200);
		}
	});

	// Trigger Print / Save as PDF in New Tab
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

	// Helper: Apply brand color to preview table price cells
	function applyPreviewPriceColor(color) {
		$('.singo-preview-price-cell, .singo-preview-price-cell .singo-price-sale').css('color', color);
		$('.singo-preview-price-cell .singo-price-regular, .singo-preview-price-cell del').css('color', '#94a3b8');
	}

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

		$tbody.html('<tr><td colspan="5" style="text-align:center; padding:35px; color:#64748b;"><span class="dashicons dashicons-update" style="animation: rotation 1.5s infinite linear; font-size:20px; width:20px; height:20px; display:inline-block; vertical-align:middle; margin-left:6px;"></span> در حال بارگذاری پیش‌نمایش (حداکثر ۳۰ قلم)...</td></tr>');
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
					var countDisplay = res.data.total_count_fa || res.data.count_fa;
					var countLabel = res.data.is_capped ? (countDisplay + ' کالا (نمونه ۳۰ تایی)') : (countDisplay + ' کالا');
					$count.text(countLabel);

					$refreshBtn.css('display', 'inline-flex');
					$loadBtn.html('<span class="dashicons dashicons-yes"></span> پیش‌نمایش فعال شد').removeClass('singo-btn-primary').addClass('singo-btn-secondary');

					var currentColor = '<?php echo esc_js( $saved_primary_color ); ?>';
					applyPreviewPriceColor(currentColor);
				} else {
					$tbody.html('<tr><td colspan="5" style="text-align:center; padding:30px; color:#ef4444;">خطا در دریافت اطلاعات محصولات.</td></tr>');
					$count.text('۰ کالا');
				}
			},
			error: function(xhr, status) {
				$refreshBtn.prop('disabled', false);
				$loadBtn.prop('disabled', false);

				if (status === 'abort') {
					return;
				}
				$tbody.html('<tr><td colspan="5" style="text-align:center; padding:30px; color:#ef4444;">خطا در برقراری ارتباط با سرور.</td></tr>');
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

	// Synchronize Optionino Global Save Changes Button (Hide on Custom Action tabs)
	function syncSaveButtonVisibility() {
		var isCustomTab = $('#price_list_export_tab:visible, #price_list_category_colors_tab:visible, #price_list_download_reports_tab:visible').length > 0;
		if (isCustomTab) {
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
