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

      // Enable Drag & Drop with jQuery UI Sortable
      if ($.fn.sortable) {
        $container.sortable({
          handle: '.singo-drag-handle',
          items: '> .optionino-repeater-item',
          axis: 'y',
          containment: 'parent', // STRICT: Cannot escape or overlap other tabs
          cursor: 'grabbing',
          opacity: 0.92,
          tolerance: 'pointer',
          forcePlaceholderSize: true,
          forceHelperSize: true,
          placeholder: 'optionino-repeater-item singo-sortable-placeholder',
          helper: function(e, item) {
            var $clone = item.clone();
            $clone.css({
              'width': item.outerWidth() + 'px',
              'box-sizing': 'border-box',
              'z-index': 99999
            });
            return $clone;
          },
          start: function(e, ui) {
            ui.placeholder.css({
              'height': ui.item.outerHeight() + 'px',
              'width': ui.item.outerWidth() + 'px',
              'box-sizing': 'border-box',
              'visibility': 'visible'
            });
          },
          stop: function() {
            reindexRepeater($container);
            triggerRepeaterChange($field[0]);
          }
        });
      }
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

  // Initialize on DOM Ready and on Tab switches
  $(document).ready(function() {
    initProductFeaturesRepeater();
    $(document).on('click', '.tablinks', function() {
      setTimeout(initProductFeaturesRepeater, 50);
    });
  });

})(jQuery);
