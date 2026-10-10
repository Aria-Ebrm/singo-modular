<?php
declare(strict_types=1);

/**
 * Interactive Admin View for Product Attribute Groups Management inside Optionino Framework
 */

defined( 'ABSPATH' ) || exit;

// Fetch options
$master_switch = singo_tools_get_option( 'enable_product_features', true );
$master_switch = filter_var( $master_switch, FILTER_VALIDATE_BOOLEAN );
$groups        = function_exists('mu_get_attribute_groups') ? mu_get_attribute_groups() : array();
$group_names   = array_keys( $groups );
$default_group = function_exists('mu_get_default_attribute_group') ? mu_get_default_attribute_group() : '';
?>

<!-- Field 1: Master Switch (Matches Shamsi & Currency tabs) -->
<div class="optionino-box-option">
	<label class="optionino-form-label" for="enable_product_features">فعال‌سازی ماژول گروه‌بندی ویژگی‌ها</label>
	<div class="optionino-switch-box flex">
		<input type="hidden" name="enable_product_features" value="off">
		<input type="checkbox" class="optionino-switch-checkbox optionino-radio" id="enable_product_features" name="enable_product_features" value="on" <?php checked( $master_switch, true ); ?> style="display:none !important;">
		<label class="optionino-switch-label" for="enable_product_features"></label>
	</div>
	<p>با غیرفعال‌سازی این گزینه، تمام قابلیت‌های گروه‌بندی ویژگی‌ها غیرفعال شده و جدول ویژگی‌ها به حالت عادی ووکامرس برمی‌گردد.</p>
</div>

<!-- Option Body -->
<div id="mu-product-features-options-body" style="<?php echo $master_switch ? '' : 'display: none;'; ?>">
	
	<!-- Field 2: Shortcode -->
	<div class="optionino-box-option">
		<label class="optionino-form-label">شورت‌کد اختصاصی نمایش ویژگی‌های محصول</label>
		<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 5px;">
			<input type="text" id="mu-shortcode-code" class="optionino-input" value="[dynamic_product_specs]" readonly style="width: auto; max-width: 260px; font-family: monospace; text-align: center; font-weight: bold; background: #fafafa; cursor: pointer;">
			<button type="button" id="mu-copy-shortcode-btn" class="upload-image-button" style="margin: 0;">
				<span id="mu-copy-btn-text">کپی شورت‌کد</span>
			</button>
		</div>
		<p>می‌توانید از این شورت‌کد در برگه‌سازها، چیدمان محصول یا بخش‌های دلخواه استفاده نمایید.</p>
	</div>

	<!-- Field 3: Groups Repeater List -->
	<div class="optionino-box-option">
		<label class="optionino-form-label">مدیریت و اولویت‌بندی گروه‌ها</label>
		<div class="optionino-repeater-container" style="padding-top: 10px;">
			<ul id="mu-sortable-groups" style="list-style: none; margin: 0; padding: 0;">
				<?php foreach ( $group_names as $index => $name ) : 
					$is_default = ( $name === $default_group );
				?>
					<li class="optionino-repeater-item mu-group-item-row <?php echo $is_default ? 'is-default-group' : ''; ?>" draggable="true" style="margin-bottom: 12px; align-items: center; background: #fff;">
						
						<!-- Drag Handle -->
						<span class="mu-drag-handle" title="برای جابجایی بکشید" style="cursor: grab; color: #aaa; font-size: 18px; padding: 4px; display: flex; align-items: center;">
							<span class="dashicons dashicons-menu"></span>
						</span>

						<!-- Up / Down Reorder Buttons -->
						<div class="mu-btn-group" style="display: flex; flex-direction: column; gap: 2px;">
							<button type="button" class="mu-move-btn mu-move-up" title="انتقال به بالا" style="background: #fff; border: 1px solid #ccc; border-radius: 3px; width: 22px; height: 18px; cursor: pointer; font-size: 9px; padding: 0; color: #555;">▲</button>
							<button type="button" class="mu-move-btn mu-move-down" title="انتقال به پایین" style="background: #fff; border: 1px solid #ccc; border-radius: 3px; width: 22px; height: 18px; cursor: pointer; font-size: 9px; padding: 0; color: #555;">▼</button>
						</div>

						<!-- Priority Badge -->
						<span class="mu-priority-badge" style="background: var(--optionino-main-color, #b16241); color: #fff; font-size: 12px; font-weight: bold; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
							<?php echo ( $index + 1 ); ?>
						</span>

						<!-- Title Input & Original Title Tracking -->
						<div class="mu-input-wrapper" style="flex: 1;">
							<input type="hidden" name="mu_groups_old[]" value="<?php echo esc_attr( $name ); ?>">
							<input type="text" name="mu_groups[]" class="optionino-input mu-group-title-input" value="<?php echo esc_attr( $name ); ?>" placeholder="عنوان گروه...">
						</div>

						<!-- Fallback Radio Selector -->
						<label class="mu-fallback-radio-container" style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 6px 12px; background: #fafafa; border-radius: 6px; border: 1px solid #e5e5e5; user-select: none;">
							<input type="radio" name="mu_default_group" class="mu-fallback-radio" value="<?php echo esc_attr( $name ); ?>" <?php checked( $is_default, true ); ?>>
							<span class="mu-fallback-text" style="font-size: 12px; font-weight: 600; color: #555;">
								<?php echo $is_default ? '✔ دسته پیش‌فرض' : 'انتخاب به عنوان پیش‌فرض'; ?>
							</span>
						</label>

						<!-- Remove Button -->
						<button type="button" class="optionino-remove-repeater-item mu-remove-group-btn" style="padding: 6px 12px; font-size: 13px;">
							<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
							حذف
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<!-- Add Group Footer -->
			<div style="display: flex; gap: 10px; margin-top: 14px; align-items: center;">
				<input type="text" id="mu-new-group-input" class="optionino-input" placeholder="عنوان گروه جدید را وارد کنید..." style="flex: 1;">
				<button type="button" id="mu-add-new-group-btn" class="optionino-add-repeater-item" style="padding: 8px 18px; margin-right: 0;">
					<span class="dashicons dashicons-plus-alt2" style="margin-left: 4px;"></span>
					افزودن گروه جدید
				</button>
			</div>
		</div>
		<p>با کشیدن آیتم‌ها یا دکمه‌های بالا/پایین، اولویت گروه‌ها را تغییر دهید (از بالا به پایین = اولویت بالاتر). دسته‌ای که تیک خورده باشد، دسته پیش‌فرض (فال‌بک) خواهد بود.</p>
	</div>

</div>

<style>
.mu-group-item-row.is-default-group {
	background: #f0fdf4 !important;
	border-color: #86efac !important;
}
.mu-group-item-row.dragging {
	opacity: 0.4;
	border: 2px dashed var(--optionino-main-color, #b16241) !important;
}
.mu-group-item-row.drag-over {
	border-color: var(--optionino-main-color, #b16241) !important;
	background: #fff7ed !important;
}
</style>

<script>
jQuery(document).ready(function($) {
	var $list = $('#mu-sortable-groups');
	var dragSrcEl = null;

	// Toggle Options Panel Visibility on Master Switch Change
	$(document).on('change', '#enable_product_features', function() {
		if ($(this).is(':checked')) {
			$('#mu-product-features-options-body').slideDown(200);
		} else {
			$('#mu-product-features-options-body').slideUp(200);
		}
	});

	// Native Drag and Drop Logic
	$list.on('dragstart', '.mu-group-item-row', function(e) {
		dragSrcEl = this;
		$(this).addClass('dragging');
		e.originalEvent.dataTransfer.effectAllowed = 'move';
		e.originalEvent.dataTransfer.setData('text/plain', '');
	});

	$list.on('dragover', '.mu-group-item-row', function(e) {
		e.preventDefault();
		e.originalEvent.dataTransfer.dropEffect = 'move';
		var target = this;
		if (target && target !== dragSrcEl) {
			var rect = target.getBoundingClientRect();
			var next = (e.originalEvent.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
			if (next) {
				target.parentNode.insertBefore(dragSrcEl, target.nextSibling);
			} else {
				target.parentNode.insertBefore(dragSrcEl, target);
			}
			updatePriorities();
		}
		return false;
	});

	$list.on('dragend', '.mu-group-item-row', function() {
		$(this).removeClass('dragging');
		updatePriorities();
	});

	// Update priority badge numbers
	function updatePriorities() {
		$list.children('li').each(function(index) {
			$(this).find('.mu-priority-badge').text(index + 1);
		});
	}

	// Sync radio value with title input change
	$list.on('input change', '.mu-group-title-input', function() {
		var val = $(this).val().trim();
		var $radio = $(this).closest('li').find('.mu-fallback-radio');
		$radio.val(val);
	});

	// Handle Fallback Radio Selection Change
	$list.on('change', '.mu-fallback-radio', function() {
		$list.children('li').removeClass('is-default-group').css({
			'background': '#ffffff',
			'border-color': '#eeeeee'
		});
		$list.find('.mu-fallback-text').text('انتخاب به عنوان پیش‌فرض').css('color', '#555555');

		var $parent = $(this).closest('li');
		$parent.addClass('is-default-group').css({
			'background': '#f0fdf4',
			'border-color': '#86efac'
		});
		$parent.find('.mu-fallback-text').text('✔ دسته پیش‌فرض').css('color', '#15803d');
	});

	// Move Up Button
	$list.on('click', '.mu-move-up', function() {
		var $row = $(this).closest('li');
		var $prev = $row.prev('li');
		if ($prev.length > 0) {
			$row.insertBefore($prev);
			updatePriorities();
		}
	});

	// Move Down Button
	$list.on('click', '.mu-move-down', function() {
		var $row = $(this).closest('li');
		var $next = $row.next('li');
		if ($next.length > 0) {
			$row.insertAfter($next);
			updatePriorities();
		}
	});

	// Remove Group Button
	$list.on('click', '.mu-remove-group-btn', function() {
		if ($list.children('li').length <= 1) {
			alert('حداقل وجود یک گروه در سیستم الزامی است.');
			return;
		}

		var $row = $(this).closest('li');
		var isDefault = $row.find('.mu-fallback-radio').is(':checked');

		if (confirm('آیا از حذف این گروه اطمینان دارید؟')) {
			$row.fadeOut(200, function() {
				$(this).remove();
				updatePriorities();

				// If deleted group was default, set the first item as default
				if (isDefault || !$list.find('.mu-fallback-radio:checked').length) {
					var $firstRadio = $list.children('li').first().find('.mu-fallback-radio');
					$firstRadio.prop('checked', true).trigger('change');
				}
			});
		}
	});

	// Add New Group Button
	$('#mu-add-new-group-btn').on('click', function() {
		var $input = $('#mu-new-group-input');
		var title = $input.val().trim();

		if (!title) {
			alert('لطفا عنوان گروه را وارد کنید.');
			$input.focus();
			return;
		}

		// Check duplicate title
		var duplicate = false;
		$list.find('.mu-group-title-input').each(function() {
			if ($(this).val().trim() === title) {
				duplicate = true;
			}
		});

		if (duplicate) {
			alert('گروهی با این عنوان قبلاً اضافه شده است.');
			return;
		}

		var count      = $list.children('li').length + 1;
		var cleanTitle = $('<div>').text(title).html().replace(/"/g, '&quot;').replace(/'/g, '&#039;');
		var newItemHtml = 
			'<li class="optionino-repeater-item mu-group-item-row" draggable="true" style="margin-bottom: 12px; align-items: center; background: #fff;">' +
				'<span class="mu-drag-handle" title="برای جابجایی بکشید" style="cursor: grab; color: #999; font-size: 18px; padding: 4px; display: flex; align-items: center;"><span class="dashicons dashicons-menu"></span></span>' +
				'<div class="mu-btn-group" style="display: flex; flex-direction: column; gap: 2px;">' +
					'<button type="button" class="mu-move-btn mu-move-up" title="انتقال به بالا" style="background: #fff; border: 1px solid #ccc; border-radius: 3px; width: 22px; height: 18px; cursor: pointer; font-size: 9px; padding: 0; color: #555;">▲</button>' +
					'<button type="button" class="mu-move-btn mu-move-down" title="انتقال به پایین" style="background: #fff; border: 1px solid #ccc; border-radius: 3px; width: 22px; height: 18px; cursor: pointer; font-size: 9px; padding: 0; color: #555;">▼</button>' +
				'</div>' +
				'<span class="mu-priority-badge" style="background: var(--optionino-main-color, #b16241); color: #fff; font-size: 12px; font-weight: bold; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">' + count + '</span>' +
				'<div class="mu-input-wrapper" style="flex: 1;">' +
					'<input type="hidden" name="mu_groups_old[]" value="">' +
					'<input type="text" name="mu_groups[]" class="optionino-input mu-group-title-input" value="' + cleanTitle + '" placeholder="عنوان گروه...">' +
				'</div>' +
				'<label class="mu-fallback-radio-container" style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 6px 12px; background: #fafafa; border-radius: 6px; border: 1px solid #e5e5e5; user-select: none;">' +
					'<input type="radio" name="mu_default_group" class="mu-fallback-radio" value="' + cleanTitle + '">' +
					'<span class="mu-fallback-text" style="font-size: 12px; font-weight: 600; color: #555;">انتخاب به عنوان پیش‌فرض</span>' +
				'</label>' +
				'<button type="button" class="optionino-remove-repeater-item mu-remove-group-btn" style="padding: 6px 12px; font-size: 13px;"><span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span> حذف</button>' +
			'</li>';

		var $newItem = $(newItemHtml);
		$list.append($newItem.hide().fadeIn(200));
		$input.val('');
		updatePriorities();
	});

	// Shortcode Copy Button Handler
	$('#mu-copy-shortcode-btn').on('click', function(e) {
		e.preventDefault();
		var shortcode = $('#mu-shortcode-code').val().trim();
		var $btnText = $('#mu-copy-btn-text');

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(shortcode).then(function() {
				$btnText.text('کپی شد! ✓');
				setTimeout(function() {
					$btnText.text('کپی شورت‌کد');
				}, 2000);
			}).catch(function() {
				fallbackCopy(shortcode, $btnText);
			});
		} else {
			fallbackCopy(shortcode, $btnText);
		}
	});

	function fallbackCopy(text, $btnText) {
		var $temp = $('<input>');
		$('body').append($temp);
		$temp.val(text).select();
		try {
			document.execCommand('copy');
			$btnText.text('کپی شد! ✓');
		} catch (err) {
			$btnText.text('خطا در کپی');
		}
		$temp.remove();
		setTimeout(function() {
			$btnText.text('کپی شورت‌کد');
		}, 2000);
	}
});
</script>
