<?php
declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// Fetch all product categories with hierarchical structure (cached via transient)
$hierarchical_cats = get_transient( 'singo_price_list_admin_cats' );
if ( false === $hierarchical_cats || ! is_array( $hierarchical_cats ) ) {
	$raw_categories = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'pad_counts' => true,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	$hierarchical_cats = array();
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
		$walk_terms( $raw_categories, 0, 0, $walk_terms, $hierarchical_cats, $visited );

		if ( count( $hierarchical_cats ) < count( $raw_categories ) ) {
			$existing_ids = array_map( static fn( $t ) => (int) $t->term_id, $hierarchical_cats );
			foreach ( $raw_categories as $term ) {
				if ( ! in_array( (int) $term->term_id, $existing_ids, true ) ) {
					$term->display_depth = 0;
					$hierarchical_cats[] = $term;
				}
			}
		}
	}
	set_transient( 'singo_price_list_admin_cats', $hierarchical_cats, DAY_IN_SECONDS );
}

// Current Saved Settings
$pattern_enabled     = class_exists( 'Singo_Price_List' ) ? (bool) Singo_Price_List::get_setting( 'enable_page_pattern', true ) : true;
$pattern_icon_circle = class_exists( 'Singo_Price_List' ) ? (bool) Singo_Price_List::get_setting( 'pattern_icon_circle', false ) : false;
$default_color       = class_exists( 'Singo_Price_List' ) ? (string) Singo_Price_List::get_setting( 'pattern_default_color', Singo_Price_List::get_active_primary_color() ) : '#ad6141';
if ( empty( $default_color ) ) {
	$default_color = '#ad6141';
}
$default_icon = class_exists( 'Singo_Price_List' ) ? trim( (string) Singo_Price_List::get_setting( 'pattern_default_icon', '' ) ) : '';
$global_cover = class_exists( 'Singo_Price_List' ) ? trim( (string) Singo_Price_List::get_setting( 'pdf_cover_image', '' ) ) : '';

$saved_colors = class_exists( 'Singo_Price_List' ) ? (array) Singo_Price_List::get_setting( 'category_colors', array() ) : array();
$saved_icons  = class_exists( 'Singo_Price_List' ) ? (array) Singo_Price_List::get_setting( 'category_pattern_icons', array() ) : array();
$saved_covers = class_exists( 'Singo_Price_List' ) ? (array) Singo_Price_List::get_setting( 'category_cover_images', array() ) : array();
$ajax_nonce   = wp_create_nonce( 'singo_price_list_nonce' );
?>

<div class="singo-cat-colors-wrapper" style="direction: rtl; font-family: inherit; margin-top: 10px;">

	<!-- Header Banner -->
	<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
			<div>
				<h2 style="margin: 0 0 6px 0; font-size: 17.5px; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 10px;">
					<span class="dashicons dashicons-art" style="font-size: 22px; width: 22px; height: 22px; color: <?php echo esc_attr( $default_color ); ?>;"></span>
					رنگ پترن، آیکن روی پترن و کاور صفحه اول دسته‌بندی‌ها
				</h2>
				<p style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.6;">
					برای هر دسته‌بندی (مادر یا فرزند) می‌توانید <strong>رنگ پترن</strong>، <strong>آیکن دایره‌ای روی پترن</strong> و <strong>تصویر کاور صفحه اول (A4)</strong> اختصاصی تعیین کنید. در صورتی که برای دسته‌بندی فرزند مقداری وارد نشود، به صورت خودکار از <strong>دسته‌بندی مادر</strong> ارث‌بری خواهد کرد.
				</p>
			</div>

			<button type="button" class="singo-btn-save-cat-colors" style="background: <?php echo esc_attr( $default_color ); ?>; color: #ffffff; border: none; border-radius: 8px; padding: 10px 24px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); font-family: inherit !important; transition: all 0.2s ease;">
				<span class="dashicons dashicons-saved" style="font-size: 18px; width: 18px; height: 18px;"></span>
				<span class="singo-save-text">ذخیره تنظیمات دسته‌بندی‌ها</span>
			</button>
		</div>

		<!-- Feedback Alert -->
		<div id="singo-cat-colors-notice" style="display: none; margin-top: 16px; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;"></div>
	</div>

	<!-- General Pattern Controls Box -->
	<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
		<h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: #334155; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
			تنظیمات سراسری پترن و آیکن
		</h3>

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">

			<!-- Toggle Pattern -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">
				<label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
					<input type="checkbox" id="singo-enable-page-pattern" style="width: 18px; height: 18px; accent-color: <?php echo esc_attr( $default_color ); ?>;" <?php checked( $pattern_enabled, true ); ?> />
					<div>
						<strong style="font-size: 13px; color: #1e293b; display: block;">فعال‌سازی پترن گوشه‌های صفحات</strong>
						<span style="font-size: 11.5px; color: #64748b; display: block; margin-top: 2px;">چاپ وکتور در صفحات PDF به صورت یک‌صفحه‌درمیان (بالا-راست و بالا-چپ)</span>
					</div>
				</label>
			</div>

			<!-- Toggle Circle Ring Around Icon -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">
				<label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
					<input type="checkbox" id="singo-pattern-icon-circle" style="width: 18px; height: 18px; accent-color: <?php echo esc_attr( $default_color ); ?>;" <?php checked( $pattern_icon_circle, true ); ?> />
					<div>
						<strong style="font-size: 13px; color: #1e293b; display: block;">ترسیم خودکار حلقه دایره‌ای دور آیکن</strong>
						<span style="font-size: 11.5px; color: #64748b; display: block; margin-top: 2px;">اگر فایل آیکن شما خودش کادر دایره‌ای دارد، این تیک را بردارید</span>
					</div>
				</label>
			</div>

			<!-- Default Pattern Color -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">
				<label for="singo-pattern-default-color" style="display: block; font-size: 12.5px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
					رنگ پیش‌فرض پترن (سراسری):
				</label>
				<div style="display: flex; align-items: center; gap: 8px;">
					<input type="color" id="singo-pattern-default-color" value="<?php echo esc_attr( $default_color ); ?>" style="width: 40px; height: 36px; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; padding: 2px; background: #ffffff;" />
					<input type="text" id="singo-pattern-default-color-hex" value="<?php echo esc_attr( $default_color ); ?>" dir="ltr" style="width: 96px; height: 36px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; font-size: 12.5px; text-align: center;" />
				</div>
			</div>

			<!-- Default Pattern Icon -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">
				<label style="display: block; font-size: 12.5px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
					آیکن پیش‌فرض روی پترن (سراسری):
				</label>
				<div style="display: flex; align-items: center; gap: 8px;">
					<div id="singo-default-icon-preview" style="width: 42px; height: 42px; padding: 3px; box-sizing: border-box; border-radius: 50%; border: 1px dashed #cbd5e1; background: #ffffff; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
						<?php if ( ! empty( $default_icon ) ) : ?>
							<img src="<?php echo esc_url( $default_icon ); ?>" alt="" style="width: 100%; height: 100%; object-fit: contain;" />
						<?php else : ?>
							<span class="dashicons dashicons-format-image" style="color: #94a3b8; font-size: 16px; width: 16px; height: 16px;"></span>
						<?php endif; ?>
					</div>
					<input type="hidden" id="singo-pattern-default-icon" value="<?php echo esc_attr( $default_icon ); ?>" />
					<button type="button" id="singo-btn-select-default-icon" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; height: 34px; padding: 0 10px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; font-family: inherit !important;">
						انتخاب آیکن
					</button>
					<button type="button" id="singo-btn-clear-default-icon" style="background: transparent; border: 1px solid #e2e8f0; border-radius: 6px; height: 34px; padding: 0 8px; font-size: 11px; color: #ef4444; cursor: pointer; font-family: inherit !important; <?php echo ! empty( $default_icon ) ? '' : 'display: none;'; ?>">
						حذف
					</button>
				</div>
			</div>

		</div>
	</div>

	<!-- Categories Hierarchical Table -->
	<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
		<div style="padding: 16px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
			<h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #334155;">
				فهرست سلسله‌مراتبی دسته‌بندی‌ها (رنگ پترن، آیکن روی پترن و کاور صفحه اول)
			</h3>
			<span style="font-size: 12px; color: #64748b;">
				مجموع: <?php echo esc_html( (string) count( $hierarchical_cats ) ); ?> دسته‌بندی
			</span>
		</div>

		<div style="overflow-x: auto;">
			<table style="width: 100%; border-collapse: collapse; text-align: right; font-size: 13px;">
				<thead>
					<tr style="background: #f1f5f9; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 700;">
						<th style="padding: 12px 16px; width: 26%;">نام دسته‌بندی</th>
						<th style="padding: 12px 12px; width: 22%;">رنگ پترن</th>
						<th style="padding: 12px 12px; width: 20%;">آیکن روی پترن</th>
						<th style="padding: 12px 12px; width: 20%;">کاور صفحه اول (A4)</th>
						<th style="padding: 12px 12px; width: 12%; text-align: center;">وضعیت ارث‌بری</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $hierarchical_cats ) ) : ?>
						<tr>
							<td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">
								هیچ دسته‌بندی محصولی در ووکامرس یافت نشد.
							</td>
						</tr>
					<?php else : ?>
						<?php
						// Pre-calculate parent values for inheritance previews
						$resolved_parent_colors = array();
						$resolved_parent_icons  = array();
						$resolved_parent_covers = array();

						foreach ( $hierarchical_cats as $cat ) :
							$depth     = isset( $cat->display_depth ) ? (int) $cat->display_depth : 0;
							$term_id   = (int) $cat->term_id;
							$parent_id = (int) $cat->parent;
							$is_parent = ( 0 === $depth );

							// 1. Color inheritance
							$has_custom_color = ! empty( $saved_colors[ $term_id ] );
							$custom_color_val = $has_custom_color ? (string) $saved_colors[ $term_id ] : '';
							$inherited_color  = ( $parent_id > 0 && isset( $resolved_parent_colors[ $parent_id ] ) ) ? $resolved_parent_colors[ $parent_id ] : $default_color;
							$active_color     = $has_custom_color ? $custom_color_val : $inherited_color;
							$resolved_parent_colors[ $term_id ] = $active_color;

							// 2. Pattern Icon inheritance
							$has_custom_icon = ! empty( $saved_icons[ $term_id ] );
							$custom_icon_val = $has_custom_icon ? (string) $saved_icons[ $term_id ] : '';
							$inherited_icon  = ( $parent_id > 0 && isset( $resolved_parent_icons[ $parent_id ] ) ) ? $resolved_parent_icons[ $parent_id ] : $default_icon;
							$active_icon     = $has_custom_icon ? $custom_icon_val : $inherited_icon;
							$resolved_parent_icons[ $term_id ] = $active_icon;

							// 3. Cover Image inheritance
							$has_custom_cover = ! empty( $saved_covers[ $term_id ] );
							$custom_cover_val = $has_custom_cover ? (string) $saved_covers[ $term_id ] : '';
							$inherited_cover  = ( $parent_id > 0 && isset( $resolved_parent_covers[ $parent_id ] ) ) ? $resolved_parent_covers[ $parent_id ] : $global_cover;
							$active_cover     = $has_custom_cover ? $custom_cover_val : $inherited_cover;
							$resolved_parent_covers[ $term_id ] = $active_cover;
						?>
							<tr class="singo-cat-row <?php echo $is_parent ? 'singo-cat-parent-row' : 'singo-cat-child-row'; ?>"
								data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
								data-parent-id="<?php echo esc_attr( (string) $parent_id ); ?>"
								data-depth="<?php echo esc_attr( (string) $depth ); ?>"
								style="border-bottom: 1px solid #f1f5f9; <?php echo $is_parent ? 'background: #ffffff; font-weight: 600;' : 'background: #fafbfc;'; ?>">

								<!-- 1. Category Title & Count -->
								<td style="padding: 12px 16px;">
									<div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; <?php echo $depth > 0 ? ( 'padding-right: ' . ( $depth * 20 ) . 'px;' ) : ''; ?>">
										<div style="display: flex; align-items: center; gap: 6px;">
											<?php if ( $depth > 0 ) : ?>
												<span style="color: #94a3b8; font-size: 14px;">↳</span>
											<?php else : ?>
												<span class="dashicons dashicons-category" style="color: #64748b; font-size: 16px; width: 16px; height: 16px;"></span>
											<?php endif; ?>
											<strong style="color: <?php echo $is_parent ? '#0f172a' : '#334155'; ?>; font-size: <?php echo $is_parent ? '13.5px' : '12.5px'; ?>;">
												<?php echo esc_html( $cat->name ); ?>
											</strong>
										</div>
										<span style="font-size: 11px; color: #64748b; background: #f1f5f9; padding: 2px 7px; border-radius: 10px; white-space: nowrap;">
											<?php echo esc_html( (string) $cat->count ); ?> کالا
										</span>
									</div>
								</td>

								<!-- 2. Pattern Color Picker -->
								<td style="padding: 12px 12px;">
									<div style="display: flex; align-items: center; gap: 6px;">
										<input type="color"
											   class="singo-cat-color-picker"
											   data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
											   value="<?php echo esc_attr( $active_color ); ?>"
											   style="width: 34px; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; padding: 2px; background: #ffffff; flex-shrink: 0;" />

										<input type="text"
											   class="singo-cat-color-hex"
											   data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
											   value="<?php echo esc_attr( $custom_color_val ); ?>"
											   placeholder="<?php echo esc_attr( $inherited_color ); ?>"
											   dir="ltr"
											   style="width: 80px; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; font-size: 12px; text-align: center;" />

										<button type="button"
												class="singo-btn-reset-cat-color"
												data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
												title="حذف رنگ اختصاصی و ارث‌بری از مادر"
												style="background: transparent; border: 1px solid #e2e8f0; border-radius: 6px; height: 32px; padding: 0 7px; font-size: 11px; color: #64748b; cursor: pointer; font-family: inherit !important; <?php echo $has_custom_color ? '' : 'opacity: 0.45;'; ?>">
											↺
										</button>
									</div>
								</td>

								<!-- 3. Pattern Icon Selector -->
								<td style="padding: 12px 12px;">
									<div style="display: flex; align-items: center; gap: 8px;">
										<div class="singo-cat-icon-preview"
											 data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
											 title="<?php echo $has_custom_icon ? 'آیکن اختصاصی این دسته' : ( ! empty( $active_icon ) ? 'به ارث رسیده از دسته مادر / پیش‌فرض' : 'بدون آیکن' ); ?>"
											 style="width: 42px; height: 42px; padding: 3px; box-sizing: border-box; border-radius: 50%; border: <?php echo $has_custom_icon ? '2px solid #0284c7' : '1px dashed #cbd5e1'; ?>; background: #ffffff; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; <?php echo ( ! $has_custom_icon && ! empty( $active_icon ) ) ? 'opacity: 0.6;' : ''; ?>">
											<?php if ( ! empty( $active_icon ) ) : ?>
												<img src="<?php echo esc_url( $active_icon ); ?>" alt="" style="width: 100%; height: 100%; object-fit: contain;" />
											<?php else : ?>
												<span class="dashicons dashicons-plus-alt2" style="color: #cbd5e1; font-size: 15px; width: 15px; height: 15px;"></span>
											<?php endif; ?>
										</div>

										<input type="hidden"
											   class="singo-cat-icon-input"
											   data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
											   value="<?php echo esc_attr( $custom_icon_val ); ?>" />

										<button type="button"
												class="singo-btn-select-cat-icon"
												data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
												style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; height: 32px; padding: 0 9px; font-size: 11.5px; font-weight: 600; color: #334155; cursor: pointer; font-family: inherit !important; white-space: nowrap;">
											انتخاب آیکن
										</button>

										<button type="button"
												class="singo-btn-reset-cat-icon"
												data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
												title="حذف آیکن اختصاصی و ارث‌بری از مادر"
												style="background: transparent; border: 1px solid #e2e8f0; border-radius: 6px; height: 32px; padding: 0 7px; font-size: 11px; color: #64748b; cursor: pointer; font-family: inherit !important; <?php echo $has_custom_icon ? '' : 'opacity: 0.45;'; ?>">
											↺
										</button>
									</div>
								</td>

								<!-- 4. Category Cover Page (A4) Selector -->
								<td style="padding: 12px 12px;">
									<div style="display: flex; align-items: center; gap: 8px;">
										<div class="singo-cat-cover-preview"
											 data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
											 title="<?php echo $has_custom_cover ? 'کاور اختصاصی این دسته' : ( ! empty( $active_cover ) ? 'به ارث رسیده از دسته مادر / سراسری' : 'بدون کاور' ); ?>"
											 style="width: 28px; height: 38px; border-radius: 4px; border: <?php echo $has_custom_cover ? '2px solid #0284c7' : '1px dashed #cbd5e1'; ?>; background: #ffffff; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; <?php echo ( ! $has_custom_cover && ! empty( $active_cover ) ) ? 'opacity: 0.6;' : ''; ?>">
											<?php if ( ! empty( $active_cover ) ) : ?>
												<img src="<?php echo esc_url( $active_cover ); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;" />
											<?php else : ?>
												<span class="dashicons dashicons-media-document" style="color: #cbd5e1; font-size: 15px; width: 15px; height: 15px;"></span>
											<?php endif; ?>
										</div>

										<input type="hidden"
											   class="singo-cat-cover-input"
											   data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
											   value="<?php echo esc_attr( $custom_cover_val ); ?>" />

										<button type="button"
												class="singo-btn-select-cat-cover"
												data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
												style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; height: 32px; padding: 0 9px; font-size: 11.5px; font-weight: 600; color: #334155; cursor: pointer; font-family: inherit !important; white-space: nowrap;">
											انتخاب کاور
										</button>

										<button type="button"
												class="singo-btn-reset-cat-cover"
												data-cat-id="<?php echo esc_attr( (string) $term_id ); ?>"
												title="حذف کاور اختصاصی و ارث‌بری از مادر"
												style="background: transparent; border: 1px solid #e2e8f0; border-radius: 6px; height: 32px; padding: 0 7px; font-size: 11px; color: #64748b; cursor: pointer; font-family: inherit !important; <?php echo $has_custom_cover ? '' : 'opacity: 0.45;'; ?>">
											↺
										</button>
									</div>
								</td>

								<!-- 5. Inheritance Status Badges -->
								<td style="padding: 12px 12px; text-align: center;">
									<?php
									$any_custom = $has_custom_color || $has_custom_icon || $has_custom_cover;
									$custom_parts = array();
									if ( $has_custom_color ) {
										$custom_parts[] = 'رنگ';
									}
									if ( $has_custom_icon ) {
										$custom_parts[] = 'آیکن';
									}
									if ( $has_custom_cover ) {
										$custom_parts[] = 'کاور';
									}
									$badge_label = $any_custom
										? ( 'اختصاصی (' . implode( '، ', $custom_parts ) . ')' )
										: ( $is_parent ? 'پیش‌فرض سراسری' : 'ارث‌بری از مادر' );
									?>
									<span class="singo-inheritance-badge singo-badge-<?php echo esc_attr( (string) $term_id ); ?>"
										  style="display: inline-block; font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: 600; white-space: nowrap; <?php echo $any_custom ? 'background: #e0f2fe; color: #0369a1;' : 'background: #f1f5f9; color: #64748b;'; ?>">
										<?php echo esc_html( $badge_label ); ?>
									</span>
								</td>

							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Footer Action Row -->
		<div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
			<button type="button" class="singo-btn-save-cat-colors" style="background: <?php echo esc_attr( $default_color ); ?>; color: #ffffff; border: none; border-radius: 8px; padding: 10px 26px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); font-family: inherit !important; transition: all 0.2s ease;">
				<span class="dashicons dashicons-saved" style="font-size: 18px; width: 18px; height: 18px;"></span>
				<span class="singo-save-text">ذخیره تنظیمات دسته‌بندی‌ها</span>
			</button>
		</div>

	</div>

</div>

<!-- JavaScript Logic for Live Pickers, Media Uploaders, Inheritance & AJAX Save -->
<script>
jQuery(document).ready(function($) {
	var ajaxUrl     = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce       = <?php echo wp_json_encode( $ajax_nonce ); ?>;
	var globalCover = <?php echo wp_json_encode( $global_cover ); ?>;

	// Sync Default Color Inputs
	$('#singo-pattern-default-color').on('input change', function() {
		var val = $(this).val();
		$('#singo-pattern-default-color-hex').val(val);
		updateInheritedPlaceholders();
	});
	$('#singo-pattern-default-color-hex').on('input change', function() {
		var val = $(this).val();
		if (/^#[0-9a-fA-F]{6}$/.test(val)) {
			$('#singo-pattern-default-color').val(val);
			updateInheritedPlaceholders();
		}
	});

	// WordPress Media Uploader Helper
	function openMediaUploader(title, buttonText, onSelect) {
		if (typeof wp === 'undefined' || !wp.media) {
			alert('کتابخانه رسانه وردپرس بارگذاری نشده است. لطفاً صفحه را رفرش کنید.');
			return;
		}
		var frame = wp.media({
			title: title,
			button: { text: buttonText },
			multiple: false
		});
		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			if (attachment && attachment.url) {
				onSelect(attachment.url);
			}
		});
		frame.open();
	}

	// Default Pattern Icon Uploader & Clear
	$('#singo-btn-select-default-icon').on('click', function(e) {
		e.preventDefault();
		openMediaUploader('انتخاب آیکن پیش‌فرض روی پترن', 'استفاده از این آیکن', function(url) {
			$('#singo-pattern-default-icon').val(url);
			$('#singo-default-icon-preview').html('<img src="' + url + '" alt="" style="width:100%;height:100%;object-fit:contain;" />');
			$('#singo-btn-clear-default-icon').show();
			updateInheritedPlaceholders();
		});
	});

	$('#singo-btn-clear-default-icon').on('click', function(e) {
		e.preventDefault();
		$('#singo-pattern-default-icon').val('');
		$('#singo-default-icon-preview').html('<span class="dashicons dashicons-format-image" style="color:#94a3b8;font-size:16px;width:16px;height:16px;"></span>');
		$(this).hide();
		updateInheritedPlaceholders();
	});

	// Sync Row Color Pickers
	$('.singo-cat-color-picker').on('input change', function() {
		var catId = $(this).data('cat-id');
		var val = $(this).val();
		$('.singo-cat-color-hex[data-cat-id="' + catId + '"]').val(val);
		updateInheritedPlaceholders();
	});

	$('.singo-cat-color-hex').on('input change', function() {
		var catId = $(this).data('cat-id');
		var val = $(this).val().trim();
		var $picker = $('.singo-cat-color-picker[data-cat-id="' + catId + '"]');

		if (val === '' || /^#[0-9a-fA-F]{6}$/.test(val)) {
			if (val !== '') {
				$picker.val(val);
			}
			updateInheritedPlaceholders();
		}
	});

	// Reset Category Color
	$('.singo-btn-reset-cat-color').on('click', function(e) {
		e.preventDefault();
		var catId = $(this).data('cat-id');
		$('.singo-cat-color-hex[data-cat-id="' + catId + '"]').val('');
		updateInheritedPlaceholders();
	});

	// Select Category Pattern Icon
	$('.singo-btn-select-cat-icon').on('click', function(e) {
		e.preventDefault();
		var catId = $(this).data('cat-id');
		openMediaUploader('انتخاب آیکن روی پترن برای این دسته‌بندی', 'انتخاب آیکن', function(url) {
			$('.singo-cat-icon-input[data-cat-id="' + catId + '"]').val(url);
			updateInheritedPlaceholders();
		});
	});

	// Reset Category Pattern Icon
	$('.singo-btn-reset-cat-icon').on('click', function(e) {
		e.preventDefault();
		var catId = $(this).data('cat-id');
		$('.singo-cat-icon-input[data-cat-id="' + catId + '"]').val('');
		updateInheritedPlaceholders();
	});

	// Select Category Cover Image (A4)
	$('.singo-btn-select-cat-cover').on('click', function(e) {
		e.preventDefault();
		var catId = $(this).data('cat-id');
		openMediaUploader('انتخاب تصویر کاور صفحه اول (A4) برای این دسته‌بندی', 'انتخاب کاور A4', function(url) {
			$('.singo-cat-cover-input[data-cat-id="' + catId + '"]').val(url);
			updateInheritedPlaceholders();
		});
	});

	// Reset Category Cover Image
	$('.singo-btn-reset-cat-cover').on('click', function(e) {
		e.preventDefault();
		var catId = $(this).data('cat-id');
		$('.singo-cat-cover-input[data-cat-id="' + catId + '"]').val('');
		updateInheritedPlaceholders();
	});

	// Recalculate Hierarchical Inheritance for Color, Icon & Cover across all rows
	function updateInheritedPlaceholders() {
		var defaultColor = $('#singo-pattern-default-color').val();
		var defaultIcon  = $('#singo-pattern-default-icon').val().trim();

		var resolvedColors = {};
		var resolvedIcons  = {};
		var resolvedCovers = {};

		$('.singo-cat-row').each(function() {
			var $row     = $(this);
			var catId    = $row.data('cat-id');
			var parentId = $row.data('parent-id');
			var isParent = $row.hasClass('singo-cat-parent-row');

			// 1. Color
			var $hex      = $row.find('.singo-cat-color-hex');
			var $picker   = $row.find('.singo-cat-color-picker');
			var customHex = $hex.val().trim();
			var hasColor  = (customHex !== '' && /^#[0-9a-fA-F]{6}$/.test(customHex));

			var inheritedColor = (parentId && resolvedColors[parentId]) ? resolvedColors[parentId] : defaultColor;
			$hex.attr('placeholder', inheritedColor);
			var effectiveColor = hasColor ? customHex : inheritedColor;
			resolvedColors[catId] = effectiveColor;
			$picker.val(effectiveColor);
			$row.find('.singo-btn-reset-cat-color').css('opacity', hasColor ? '1' : '0.45');

			// 2. Pattern Icon
			var $iconInput   = $row.find('.singo-cat-icon-input');
			var $iconPreview = $row.find('.singo-cat-icon-preview');
			var customIcon   = $iconInput.val().trim();
			var hasIcon      = (customIcon !== '');

			var inheritedIcon = (parentId && typeof resolvedIcons[parentId] !== 'undefined') ? resolvedIcons[parentId] : defaultIcon;
			var effectiveIcon = hasIcon ? customIcon : inheritedIcon;
			resolvedIcons[catId] = effectiveIcon;

			if (effectiveIcon) {
				$iconPreview.html('<img src="' + effectiveIcon + '" alt="" style="width:100%;height:100%;object-fit:contain;" />');
			} else {
				$iconPreview.html('<span class="dashicons dashicons-plus-alt2" style="color:#cbd5e1;font-size:15px;width:15px;height:15px;"></span>');
			}
			$iconPreview.css({
				'border': hasIcon ? '2px solid #0284c7' : '1px dashed #cbd5e1',
				'opacity': (!hasIcon && effectiveIcon) ? '0.6' : '1'
			});
			$row.find('.singo-btn-reset-cat-icon').css('opacity', hasIcon ? '1' : '0.45');

			// 3. Cover Image (A4)
			var $coverInput   = $row.find('.singo-cat-cover-input');
			var $coverPreview = $row.find('.singo-cat-cover-preview');
			var customCover   = $coverInput.val().trim();
			var hasCover      = (customCover !== '');

			var inheritedCover = (parentId && typeof resolvedCovers[parentId] !== 'undefined') ? resolvedCovers[parentId] : globalCover;
			var effectiveCover = hasCover ? customCover : inheritedCover;
			resolvedCovers[catId] = effectiveCover;

			if (effectiveCover) {
				$coverPreview.html('<img src="' + effectiveCover + '" alt="" style="width:100%;height:100%;object-fit:cover;" />');
			} else {
				$coverPreview.html('<span class="dashicons dashicons-media-document" style="color:#cbd5e1;font-size:15px;width:15px;height:15px;"></span>');
			}
			$coverPreview.css({
				'border': hasCover ? '2px solid #0284c7' : '1px dashed #cbd5e1',
				'opacity': (!hasCover && effectiveCover) ? '0.6' : '1'
			});
			$row.find('.singo-btn-reset-cat-cover').css('opacity', hasCover ? '1' : '0.45');

			// 4. Status Badge
			var parts = [];
			if (hasColor) parts.push('رنگ');
			if (hasIcon)  parts.push('آیکن');
			if (hasCover) parts.push('کاور');

			var $badge = $('.singo-badge-' + catId);
			if (parts.length > 0) {
				$badge.text('اختصاصی (' + parts.join('، ') + ')').css({ 'background': '#e0f2fe', 'color': '#0369a1' });
			} else {
				$badge.text(isParent ? 'پیش‌فرض سراسری' : 'ارث‌بری از مادر').css({ 'background': '#f1f5f9', 'color': '#64748b' });
			}
		});
	}

	// AJAX Save
	$('.singo-btn-save-cat-colors').on('click', function(e) {
		e.preventDefault();

		var $allBtns = $('.singo-btn-save-cat-colors');
		var $notice  = $('#singo-cat-colors-notice');

		var enablePattern = $('#singo-enable-page-pattern').is(':checked') ? '1' : '0';
		var iconCircle    = $('#singo-pattern-icon-circle').is(':checked') ? '1' : '0';
		var defaultColor  = $('#singo-pattern-default-color').val();
		var defaultIcon   = $('#singo-pattern-default-icon').val().trim();

		var categoryColors = {};
		$('.singo-cat-color-hex').each(function() {
			var catId = $(this).data('cat-id');
			var val   = $(this).val().trim();
			if (val !== '' && /^#[0-9a-fA-F]{6}$/.test(val)) {
				categoryColors[catId] = val;
			}
		});

		var categoryIcons = {};
		$('.singo-cat-icon-input').each(function() {
			var catId = $(this).data('cat-id');
			var val   = $(this).val().trim();
			if (val !== '') {
				categoryIcons[catId] = val;
			}
		});

		var categoryCovers = {};
		$('.singo-cat-cover-input').each(function() {
			var catId = $(this).data('cat-id');
			var val   = $(this).val().trim();
			if (val !== '') {
				categoryCovers[catId] = val;
			}
		});

		$allBtns.prop('disabled', true).css('opacity', '0.7');
		$allBtns.find('.singo-save-text').text('در حال ذخیره‌سازی...');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'singo_save_category_colors',
				security: nonce,
				enable_page_pattern: enablePattern,
				pattern_icon_circle: iconCircle,
				pattern_default_color: defaultColor,
				pattern_default_icon: defaultIcon,
				category_colors: categoryColors,
				category_pattern_icons: categoryIcons,
				category_cover_images: categoryCovers
			},
			success: function(res) {
				$allBtns.prop('disabled', false).css('opacity', '1');
				$allBtns.find('.singo-save-text').text('ذخیره تنظیمات دسته‌بندی‌ها');

				if (res.success) {
					$notice.css({ 'display': 'block', 'background': '#f0fdf4', 'color': '#166534', 'border': '1px solid #bbf7d0' })
						   .html('<span class="dashicons dashicons-yes" style="vertical-align: middle; margin-left: 6px;"></span>' + (res.data.message || 'تنظیمات با موفقیت ذخیره شد.'));
					setTimeout(function() {
						$notice.slideUp();
					}, 4000);
				} else {
					$notice.css({ 'display': 'block', 'background': '#fef2f2', 'color': '#991b1b', 'border': '1px solid #fecaca' })
						   .html('<span class="dashicons dashicons-warning" style="vertical-align: middle; margin-left: 6px;"></span>' + (res.data ? res.data.message : 'خطایی رخ داد.'));
				}
			},
			error: function() {
				$allBtns.prop('disabled', false).css('opacity', '1');
				$allBtns.find('.singo-save-text').text('ذخیره تنظیمات دسته‌بندی‌ها');
				$notice.css({ 'display': 'block', 'background': '#fef2f2', 'color': '#991b1b', 'border': '1px solid #fecaca' })
					   .text('خطای ارتباط با سرور.');
			}
		});
	});
});
</script>
