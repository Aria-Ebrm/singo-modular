/**
 * Singo Tools - Product Features Repeater Enhancements (Self-Contained)
 * 
 * Provides:
 * - Drag & Drop reordering via jQuery UI Sortable with parent containment (zero glitch)
 * - Dynamic priority badges & re-indexing
 * - Single-choice "Default" selector (انتخاب به عنوان پیش‌فرض)
 * - Add / Remove repeater items
 * - Event dispatching for Singo Delta Saving
 * 
 * @package SingoTools\Modules\ProductFeatures
 * @since 4.3.0
 */

(function($) {
  'use strict';

  function initShortcodeCopy() {
    var $input = $('#mu_shortcode_info');
    if (!$input.length || $input.data('singo-copy-ready') || $input.attr('data-singo-copy-ready') || $input.parent().hasClass('singo-shortcode-field-wrapper')) {
      return;
    }
    $input.data('singo-copy-ready', true);

    // Make input readonly and easy to select
    $input.attr('readonly', 'readonly');

    // Check if wrapper already exists
    if (!$input.parent().hasClass('singo-shortcode-field-wrapper')) {
      var $wrapper = $('<div class="singo-shortcode-field-wrapper"></div>');
      $input.wrap($wrapper);

      var $copyBtn = $(
        '<button type="button" class="singo-copy-shortcode-btn" title="کپی کردن شورت‌کد">' +
          '<span class="dashicons dashicons-admin-page"></span>' +
          '<span class="singo-copy-btn-text">کپی شورت‌کد</span>' +
        '</button>'
      );

      $input.after($copyBtn);

      $copyBtn.on('click', function(e) {
        e.preventDefault();
        var shortcodeVal = $input.val() || '[dynamic_product_specs]';

        function handleCopiedState() {
          $copyBtn.addClass('is-copied');
          $copyBtn.html('<span class="dashicons dashicons-yes"></span><span class="singo-copy-btn-text">کپی شد!</span>');
          setTimeout(function() {
            $copyBtn.removeClass('is-copied');
            $copyBtn.html('<span class="dashicons dashicons-admin-page"></span><span class="singo-copy-btn-text">کپی شورت‌کد</span>');
          }, 2000);
        }

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(shortcodeVal).then(handleCopiedState).catch(function() {
            fallbackCopy();
          });
        } else {
          fallbackCopy();
        }

        function fallbackCopy() {
          $input.focus().select();
          try {
            document.execCommand('copy');
            handleCopiedState();
          } catch (err) {}
        }
      });

      $input.on('click', function() {
        $(this).select();
      });
    }
  }

  function initProductFeaturesRepeater() {
    $('.optionino-repeater-field[data-repeater-name="mu_attribute_groups"]').each(function() {
      var $field = $(this);
      var $container = $field.find('.optionino-repeater-container');

      if (!$container.length || $field.data('singo-repeater-ready')) {
        return;
      }
      $field.data('singo-repeater-ready', true);

      // Enhance existing items
      setupRepeaterItems($container);

      // Setup Add Button label and icon
      setupAddButtonText($field);

      // Enable 100% Native HTML5 Drag & Drop (Zero Glitch, Zero RTL jumping, Native Compositor)
      initHTML5DragAndDrop($container, $field);
    });
  }

  function setupAddButtonText($field) {
    var $addBtn = $field.find('.optionino-add-repeater-item');
    if ($addBtn.length && !$addBtn.find('.singo-add-btn-text').length) {
      $addBtn.append('<span class="singo-add-btn-text">افزودن گروه</span>');
    }
  }

  function initHTML5DragAndDrop($container, $field) {
    var draggedItem = null;

    // Enable draggable ONLY when mouse is pressed on drag handle
    $container.on('mousedown', '.singo-drag-handle', function() {
      var $item = $(this).closest('.optionino-repeater-item');
      $item.attr('draggable', 'true');
    });

    $container.on('mouseup mouseleave', '.singo-drag-handle', function() {
      var $item = $(this).closest('.optionino-repeater-item');
      if (!$item.hasClass('is-dragging')) {
        $item.removeAttr('draggable');
      }
    });

    $container.on('dragstart', '.optionino-repeater-item', function(e) {
      draggedItem = this;
      var dt = e.originalEvent.dataTransfer;
      if (dt) {
        dt.effectAllowed = 'move';
        dt.setData('text/plain', ''); // Required for Firefox compatibility
      }

      var self = this;
      setTimeout(function() {
        $(self).addClass('is-dragging');
      }, 0);
    });

    // Handle dragover on container to allow drop
    $container.on('dragover', function(e) {
      e.preventDefault();
      if (e.originalEvent.dataTransfer) {
        e.originalEvent.dataTransfer.dropEffect = 'move';
      }
    });

    // Real-time slot displacement on item hover
    $container.on('dragover', '.optionino-repeater-item', function(e) {
      e.preventDefault();
      if (e.originalEvent.dataTransfer) {
        e.originalEvent.dataTransfer.dropEffect = 'move';
      }

      if (!draggedItem || draggedItem === this) {
        return;
      }

      var rect = this.getBoundingClientRect();
      var midY = rect.top + rect.height / 2;
      var clientY = e.originalEvent.clientY;

      if (clientY < midY) {
        if (this.previousElementSibling !== draggedItem) {
          this.parentNode.insertBefore(draggedItem, this);
        }
      } else {
        if (this.nextElementSibling !== draggedItem) {
          this.parentNode.insertBefore(draggedItem, this.nextSibling);
        }
      }
    });

    $container.on('dragend', '.optionino-repeater-item', function() {
      $(this).removeAttr('draggable').removeClass('is-dragging');
      draggedItem = null;

      reindexRepeater($container);
      triggerRepeaterChange($field[0]);
    });
  }

  function setupRepeaterItems($container) {
    var $items = $container.children('.optionino-repeater-item');

    $items.each(function(index) {
      var $item = $(this);

      // Set placeholder on title input
      var $titleInput = $item.find('input[type="text"][name*="group_title"]');
      if ($titleInput.length && !$titleInput.attr('placeholder')) {
        $titleInput.attr('placeholder', 'عنوان گروه را وارد کنید...');
      }

      // Inject Drag Handle if missing
      if (!$item.find('.singo-drag-handle').length) {
        var $dragHandle = $('<span class="singo-drag-handle" title="برای تغییر اولویت بکشید"><span class="dashicons dashicons-menu"></span></span>');
        $item.prepend($dragHandle);
      }

      // Inject Priority Badge if missing
      var $badge = $item.find('.singo-priority-badge');
      if (!$badge.length) {
        $badge = $('<span class="singo-priority-badge">' + (index + 1) + '</span>');
        $item.find('.singo-drag-handle').after($badge);
      } else {
        $badge.text(index + 1);
      }

      // Check default switcher state
      var $defaultCheck = $item.find('input[type="checkbox"][name*="is_default"]');
      if ($defaultCheck.length && $defaultCheck.prop('checked')) {
        $item.addClass('is-default-row');
      } else {
        $item.removeClass('is-default-row');
      }
    });

    // Ensure at least one item is marked default if is_default switchers exist
    var $defaultChecks = $container.find('input[type="checkbox"][name*="is_default"]');
    if ($defaultChecks.length) {
      var hasDefault = $defaultChecks.filter(':checked').length > 0;
      if (!hasDefault && $defaultChecks.first().length) {
        $defaultChecks.first().prop('checked', true).trigger('change');
        $defaultChecks.first().closest('.optionino-repeater-item').addClass('is-default-row');
      }
    }
  }

  function reindexRepeater($container) {
    var $items = $container.children('.optionino-repeater-item');

    $items.each(function(newIdx) {
      var $item = $(this);
      $item.attr('data-item-index', newIdx);

      // Update badge
      $item.find('.singo-priority-badge').text(newIdx + 1);

      // Re-index all input names and IDs
      $item.find('input, textarea, select').each(function() {
        var el = this;
        if (el.name) {
          el.name = el.name.replace(/_(\d+)(_filled)?$/, function(match, oldIdx, filledSuffix) {
            return '_' + newIdx + (filledSuffix || '');
          });
        }
        if (el.id) {
          el.id = el.id.replace(/_(\d+)$/, '_' + newIdx);
          var $label = $item.find('label[for="' + el.id.replace(/_\d+$/, '') + '_' + newIdx + '"]');
          if ($label.length) {
            $label.attr('for', el.id);
          }
        }
      });
    });
  }

  function triggerRepeaterChange(repeaterEl) {
    if (!repeaterEl) return;
    document.dispatchEvent(new CustomEvent('singo:repeater-changed', {
      detail: { repeater: repeaterEl }
    }));
  }

  // Handle Add Item Click
  $(document).on('click', '.optionino-repeater-field[data-repeater-name="mu_attribute_groups"] .optionino-add-repeater-item', function(e) {
    e.preventDefault();
    var $field = $(this).closest('.optionino-repeater-field');
    var $container = $field.find('.optionino-repeater-container');
    var $existing = $container.children('.optionino-repeater-item');

    if (!$existing.length) {
      return;
    }

    var $lastItem = $existing.last();
    var $newItem = $lastItem.clone();

    // Reset values in cloned item
    $newItem.removeClass('is-default-row');
    $newItem.find('input[type="text"], input[type="number"], textarea').val('');
    $newItem.find('input[type="checkbox"]').prop('checked', false);
    $newItem.find('input[type="radio"]').prop('checked', false);

    // Append new item
    $container.append($newItem);

    // Re-index and setup
    reindexRepeater($container);
    setupRepeaterItems($container);

    // Focus input of new row
    $newItem.find('input[type="text"]').first().focus();

    // Trigger Delta Saving event
    triggerRepeaterChange($field[0]);
  });

  // Handle Remove Item Click
  $(document).on('click', '.optionino-repeater-field[data-repeater-name="mu_attribute_groups"] .optionino-remove-repeater-item', function(e) {
    e.preventDefault();
    var $item = $(this).closest('.optionino-repeater-item');
    var $container = $item.closest('.optionino-repeater-container');
    var $field = $container.closest('.optionino-repeater-field');
    var $remaining = $container.children('.optionino-repeater-item');

    if ($remaining.length <= 1) {
      alert('حداقل یک گروه باید در جدول باقی بماند.');
      return;
    }

    var wasDefault = $item.find('input[type="checkbox"][name*="is_default"]').prop('checked');

    $item.fadeOut(150, function() {
      $item.remove();
      reindexRepeater($container);

      // If removed item was default, reassign default to the first remaining item
      if (wasDefault) {
        var $firstItem = $container.children('.optionino-repeater-item').first();
        var $firstCheck = $firstItem.find('input[type="checkbox"][name*="is_default"]');
        if ($firstCheck.length) {
          $firstCheck.prop('checked', true).trigger('change');
          $firstItem.addClass('is-default-row');
        }
      }

      setupRepeaterItems($container);
      triggerRepeaterChange($field[0]);
    });
  });

  // Handle "Default" Switcher Exclusivity (Single-Choice Radio Behavior)
  $(document).on('change', '.optionino-repeater-field[data-repeater-name="mu_attribute_groups"] .optionino-repeater-item input[type="checkbox"][name*="is_default"]', function(e) {
    var $currentCheck = $(this);
    var $currentItem = $currentCheck.closest('.optionino-repeater-item');
    var $container = $currentItem.closest('.optionino-repeater-container');
    var $field = $container.closest('.optionino-repeater-field');

    if ($currentCheck.prop('checked')) {
      // Uncheck all other default switches in this container
      $container.find('.optionino-repeater-item').not($currentItem).each(function() {
        var $otherItem = $(this);
        $otherItem.removeClass('is-default-row');
        var $otherCheck = $otherItem.find('input[type="checkbox"][name*="is_default"]');
        $otherCheck.prop('checked', false);
      });
      $currentItem.addClass('is-default-row');
    } else {
      // User tried to uncheck the active default: keep at least 1 default active
      var otherChecked = $container.find('.optionino-repeater-item input[type="checkbox"][name*="is_default"]:checked').length;
      if (otherChecked === 0) {
        $currentCheck.prop('checked', true);
        $currentItem.addClass('is-default-row');
      } else {
        $currentItem.removeClass('is-default-row');
      }
    }

    triggerRepeaterChange($field[0]);
  });

  // Initialize on DOM Ready, Tab switches, and Switcher Toggles
  $(document).ready(function() {
    initShortcodeCopy();
    initProductFeaturesRepeater();
    $(document).on('click', '.tablinks', function() {
      setTimeout(function() {
        initShortcodeCopy();
        initProductFeaturesRepeater();
      }, 50);
    });
    $(document).on('change', '#enable_product_features', function() {
      setTimeout(function() {
        initShortcodeCopy();
        initProductFeaturesRepeater();
      }, 50);
    });
  });

})(jQuery);
