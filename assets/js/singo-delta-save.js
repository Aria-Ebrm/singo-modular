/**
 * Singo Tools - Delta Saving & Partial Update (Dirty Checking Engine)
 *
 * Intercepts Optionino settings form submission to send ONLY modified fields,
 * reducing payload from full-form bloat to a sub-millisecond delta.
 *
 * @package SingoTools
 * @since 4.2.0
 */
(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('save-options-optionino');
    if (!form) {
      return;
    }

    var OPTNNO = document.getElementById('optionino');
    var errorText = form.querySelector('.error-text');
    var successText = form.querySelector('.success-text');
    var saveBox = form.querySelector('.optionino-options-save-box');

    var ignoreNames = ['action', 'security', 'dev_name', 'save-optionino'];
    var initialSnapshot = new Map();
    var dirtyFields = new Set();

    // Helper: Collect repeater values cleanly
    function collectRepeaterValues(repeaterField) {
      var repeaterItems = repeaterField.querySelectorAll('.optionino-repeater-item');
      var mainArray = [];
      repeaterItems.forEach(function(repeaterItem) {
        var subArray = {};
        repeaterItem.querySelectorAll('input, textarea, select').forEach(function(input) {
          subArray[input.name.replace(/_\d+$/, '').replace(/^_/, '')] = input.value;
        });
        mainArray.push(encodeRepeaterValues(subArray));
      });
      return encodeRepeaterValues(mainArray);
    }

    function encodeRepeaterValues(values) {
      var params = new URLSearchParams();
      for (var key in values) {
        if (Object.prototype.hasOwnProperty.call(values, key)) {
          if (Array.isArray(values[key])) {
            params.append(key, JSON.stringify(values[key]));
          } else {
            params.append(key, values[key]);
          }
        }
      }
      return params.toString();
    }

    // Helper: Read accurate field value by name
    function readFieldValue(name) {
      var radios = form.querySelectorAll('input[type="radio"][name="' + name + '"]');
      if (radios.length > 0) {
        var checkedRadio = form.querySelector('input[type="radio"][name="' + name + '"]:checked');
        return checkedRadio ? String(checkedRadio.value) : '';
      }

      var checkbox = form.querySelector('input[type="checkbox"][name="' + name + '"]');
      if (checkbox) {
        return checkbox.checked ? (checkbox.value || 'on') : 'off';
      }

      var repeater = form.querySelector('.optionino-repeater-field[data-repeater-name="' + name + '"]');
      if (repeater) {
        return collectRepeaterValues(repeater);
      }

      var el = form.querySelector('[name="' + name + '"]');
      if (el) {
        return el.value != null ? String(el.value) : '';
      }

      return '';
    }

    // Initialize Snapshot
    function captureInitialSnapshot() {
      initialSnapshot.clear();
      dirtyFields.clear();

      var elements = form.querySelectorAll('input[name], select[name], textarea[name]');
      elements.forEach(function(el) {
        var name = el.name;
        if (!name || ignoreNames.indexOf(name) !== -1 || initialSnapshot.has(name)) {
          return;
        }
        initialSnapshot.set(name, readFieldValue(name));
      });

      var repeaters = form.querySelectorAll('.optionino-repeater-field[data-repeater-name]');
      repeaters.forEach(function(rep) {
        var repName = rep.getAttribute('data-repeater-name');
        if (repName && !initialSnapshot.has(repName)) {
          initialSnapshot.set(repName, collectRepeaterValues(rep));
        }
      });

      updateDeltaBadge();
    }

    // UI Indicator Badge
    var deltaBadge = null;
    if (saveBox) {
      deltaBadge = document.createElement('span');
      deltaBadge.className = 'singo-delta-badge';
      deltaBadge.style.cssText = 'font-size: 12px; margin-inline-end: 15px; color: #64748b; font-weight: normal; transition: all 0.2s ease;';
      saveBox.insertBefore(deltaBadge, saveBox.firstChild);
    }

    function updateDeltaBadge() {
      if (!deltaBadge) return;
      var count = dirtyFields.size;
      if (count === 0) {
        deltaBadge.innerHTML = '<span style="color: #94a3b8;">● تمامی تنظیمات ذخیره هستند</span>';
      } else {
        deltaBadge.innerHTML = '<strong style="color: #ad6141;">● ' + count + ' تنظیم تغییریافته (ذخیره‌نشده)</strong>';
      }
    }

    // Check if field is modified
    function evaluateField(name) {
      if (!name || ignoreNames.indexOf(name) !== -1 || !initialSnapshot.has(name)) {
        return;
      }

      var currentVal = readFieldValue(name);
      var initialVal = initialSnapshot.get(name);

      if (currentVal !== initialVal) {
        dirtyFields.add(name);
      } else {
        dirtyFields.delete(name);
      }

      updateDeltaBadge();
    }

    // Listen to changes across all inputs
    form.addEventListener('input', function(e) {
      if (e.target && e.target.name) {
        evaluateField(e.target.name);
      }
    }, true);

    form.addEventListener('change', function(e) {
      if (e.target && e.target.name) {
        evaluateField(e.target.name);
      }
    }, true);

    // Initial snapshot capture (short delay to ensure Optionino and color pickers ready)
    setTimeout(captureInitialSnapshot, 100);

    // Capture Phase Form Interception (fires BEFORE Optionino's default submit listener)
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      e.stopImmediatePropagation();

      if (errorText) errorText.style.display = 'none';
      if (successText) successText.style.display = 'none';

      // Zero changes: instant feedback, no network request
      if (dirtyFields.size === 0) {
        if (successText) {
          successText.textContent = 'هیچ تغییری اعمال نشده است (تمامی تنظیمات بروز هستند).';
          successText.style.display = 'block';
          setTimeout(function() {
            successText.style.display = 'none';
          }, 3000);
        }
        return;
      }

      if (OPTNNO) {
        OPTNNO.classList.add('loading');
      }

      var formData = new FormData();
      formData.append('action', 'save_optionino_data');

      var devNameInput = form.querySelector('input[name="dev_name"]');
      var devName = devNameInput ? devNameInput.value : 'singo_tools_options';
      formData.append('dev_name', devName);

      var nonceVal = (window.data_optionino && window.data_optionino.nonce) 
        ? window.data_optionino.nonce 
        : (form.querySelector('input[name="security"]')?.value || '');
      formData.append('security', nonceVal);

      formData.append('_delta_save', '1');
      formData.append('_delta_count', String(dirtyFields.size));

      // Append ONLY modified fields
      dirtyFields.forEach(function(fieldName) {
        var value = readFieldValue(fieldName);
        formData.append(fieldName, value);
      });

      var targetUrl = (window.data_optionino && window.data_optionino.ajax_url)
        ? window.data_optionino.ajax_url
        : (window.ajaxurl || 'admin-ajax.php');

      var xhr = new XMLHttpRequest();
      xhr.open('POST', targetUrl, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      xhr.onload = function() {
        if (OPTNNO) OPTNNO.classList.remove('loading');
        var response = {};
        try {
          response = JSON.parse(xhr.responseText || '{}');
        } catch (err) {}

        if (xhr.status === 200 && response.success) {
          // Update snapshot for modified fields
          dirtyFields.forEach(function(fieldName) {
            initialSnapshot.set(fieldName, readFieldValue(fieldName));
          });
          dirtyFields.clear();
          updateDeltaBadge();

          if (successText) {
            successText.textContent = (response.data && response.data.message) || 'تغییرات با موفقیت ذخیره شد.';
            successText.style.display = 'block';
          }
        } else {
          if (errorText) {
            errorText.textContent = (response.data && response.data.message) || 'خطا در ذخیره‌سازی اطلاعات.';
            errorText.style.display = 'block';
          }
        }
      };

      xhr.onerror = function() {
        if (OPTNNO) OPTNNO.classList.remove('loading');
        if (errorText) {
          errorText.textContent = 'خطای شبکه رخ داده است. لطفاً اتصال اینترنت خود را بررسی نمایید.';
          errorText.style.display = 'block';
        }
      };

      xhr.send(formData);
    }, true);
  });
})();
