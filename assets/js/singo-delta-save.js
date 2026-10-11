/**
 * Singo Tools - Delta Saving & Partial Update (Dirty Checking Engine)
 *
 * Intercepts Optionino settings form submission to send ONLY modified fields,
 * reducing payload from full-form bloat to a sub-millisecond delta.
 * Includes full support for Optionino repeaters and yellow warning notices.
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
          var name = input.name;
          if (!name || name.endsWith('_filled')) {
            return;
          }
          var cleanKey = name.replace(/_\d+$/, '').replace(/^_/, '');
          if (input.type === 'checkbox') {
            subArray[cleanKey] = input.checked ? (input.value || 'true') : 'false';
          } else if (input.type === 'radio') {
            if (input.checked) {
              subArray[cleanKey] = input.value;
            }
          } else {
            subArray[cleanKey] = input.value != null ? String(input.value) : '';
          }
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
        // If element is inside a repeater, don't record sub-inputs directly
        if (el.closest('.optionino-repeater-field')) {
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
      deltaBadge.style.cssText = 'display: inline-flex; align-items: center; align-self: center; height: 40px; font-size: 13px; font-family: inherit; line-height: 1;';
      saveBox.insertBefore(deltaBadge, saveBox.firstChild);
    }

    function updateDeltaBadge() {
      if (!deltaBadge) return;
      var count = dirtyFields.size;
      if (count === 0) {
        deltaBadge.innerHTML = '<span style="color: #94a3b8; display: inline-flex; align-items: center; gap: 6px; line-height: 1;">● تمامی تنظیمات ذخیره هستند</span>';
      } else {
        deltaBadge.innerHTML = '<strong style="color: #ad6141; display: inline-flex; align-items: center; gap: 6px; line-height: 1;">● ' + count + ' تنظیم تغییریافته (ذخیره‌نشده)</strong>';
      }
    }

    // Dedicated Yellow Warning Notification function (100% Optionino Native System, Yellow Palette)
    function showWarning(msg) {
      if (errorText) {
        errorText.style.display = 'none';
        errorText.style.cssText = '';
        errorText.textContent = '';
      }

      if (successText) {
        successText.textContent = msg;
        successText.style.cssText = 'display: block !important; color: #854d0e !important; background: #fef9c3 !important; border: 1px solid #fde047 !important; padding: 8px 10px !important; text-align: center !important; font-weight: 600 !important; font-size: 15px !important; border-radius: 8px !important; margin: 0 0 15px 0 !important;';
        setTimeout(function() {
          successText.style.display = 'none';
          successText.style.cssText = '';
          successText.textContent = '';
        }, 3500);
      }
    }

    // Dedicated Green Success Notification function (100% Optionino Native System)
    function showSuccess(msg) {
      if (errorText) {
        errorText.style.display = 'none';
        errorText.style.cssText = '';
        errorText.textContent = '';
      }

      if (successText) {
        successText.textContent = msg;
        successText.style.cssText = 'display: block !important; color: #427e1b !important; background: #dff8d7 !important; border: 1px solid #d1f5c6 !important; padding: 8px 10px !important; text-align: center !important; font-weight: 600 !important; font-size: 15px !important; border-radius: 8px !important; margin: 0 0 15px 0 !important;';
        setTimeout(function() {
          successText.style.display = 'none';
          successText.style.cssText = '';
          successText.textContent = '';
        }, 3000);
      }
    }

    // Dedicated Red Error Notification function (100% Optionino Native System)
    function showError(msg) {
      if (successText) {
        successText.style.display = 'none';
        successText.style.cssText = '';
        successText.textContent = '';
      }

      if (errorText) {
        errorText.textContent = msg;
        errorText.style.cssText = 'display: block !important; color: #b52230 !important; background: #f8d7da !important; border: 1px solid #f5c2c7 !important; padding: 8px 10px !important; text-align: center !important; font-weight: 600 !important; font-size: 15px !important; border-radius: 8px !important; margin: 0 0 15px 0 !important;';
        setTimeout(function() {
          errorText.style.display = 'none';
          errorText.style.cssText = '';
          errorText.textContent = '';
        }, 4000);
      }
    }

    // Evaluate repeater modifications
    function evaluateRepeater(repeaterEl) {
      if (!repeaterEl) return;
      var repName = repeaterEl.getAttribute('data-repeater-name');
      if (!repName) return;

      var currentVal = collectRepeaterValues(repeaterEl);
      var initialVal = initialSnapshot.get(repName);

      if (currentVal !== initialVal) {
        dirtyFields.add(repName);
      } else {
        dirtyFields.delete(repName);
      }

      updateDeltaBadge();
    }

    // Check if field is modified
    function evaluateField(name, targetEl) {
      if (!name || ignoreNames.indexOf(name) !== -1) {
        return;
      }

      // If target element is inside a repeater, evaluate parent repeater
      var parentRepeater = targetEl ? targetEl.closest('.optionino-repeater-field') : null;
      if (!parentRepeater && name) {
        var foundEl = form.querySelector('[name="' + name + '"]');
        if (foundEl) {
          parentRepeater = foundEl.closest('.optionino-repeater-field');
        }
      }

      if (parentRepeater) {
        evaluateRepeater(parentRepeater);
        return;
      }

      if (!initialSnapshot.has(name)) {
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

    // Listen to custom repeater change event dispatched by product-features-repeater.js
    document.addEventListener('singo:repeater-changed', function(e) {
      if (e.detail && e.detail.repeater) {
        evaluateRepeater(e.detail.repeater);
      }
    });

    // Listen to changes across all inputs
    form.addEventListener('input', function(e) {
      if (e.target && e.target.name) {
        evaluateField(e.target.name, e.target);
      }
    }, true);

    form.addEventListener('change', function(e) {
      if (e.target && e.target.name) {
        evaluateField(e.target.name, e.target);
      }
    }, true);

    // Initialize Shortcode Copy Buttons across all shortcode fields (Image 2 design)
    function initShortcodeCopyWidgets() {
      var shortcodeInputs = document.querySelectorAll('#mu_shortcode_info, #product_thumbnail_features_shortcode, input[data-singo-shortcode="true"]');
      shortcodeInputs.forEach(function(input) {
        if (input.getAttribute('data-singo-copy-ready')) return;
        input.setAttribute('data-singo-copy-ready', 'true');
        input.setAttribute('readonly', 'readonly');

        // Ensure default shortcode value is populated if empty
        if (!input.value) {
          if (input.id === 'product_thumbnail_features_shortcode') {
            input.value = '[product_features_boxes limit="4"]';
          } else if (input.id === 'mu_shortcode_info') {
            input.value = '[dynamic_product_specs]';
          }
        }

        var parentWrapper = input.parentElement;
        if (!parentWrapper || !parentWrapper.classList.contains('singo-shortcode-field-wrapper')) {
          var wrapper = document.createElement('div');
          wrapper.className = 'singo-shortcode-field-wrapper';
          input.parentNode.insertBefore(wrapper, input);
          wrapper.appendChild(input);

          var copyBtn = document.createElement('button');
          copyBtn.type = 'button';
          copyBtn.className = 'singo-copy-shortcode-btn';
          copyBtn.title = 'کپی کردن شورت‌کد';
          copyBtn.innerHTML = '<span class="dashicons dashicons-admin-page"></span><span class="singo-copy-btn-text">کپی شورت‌کد</span>';
          wrapper.appendChild(copyBtn);

          copyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var shortcodeVal = input.value || '';

            function setCopied() {
              copyBtn.classList.add('is-copied');
              copyBtn.innerHTML = '<span class="dashicons dashicons-yes"></span><span class="singo-copy-btn-text">کپی شد!</span>';
              setTimeout(function() {
                copyBtn.classList.remove('is-copied');
                copyBtn.innerHTML = '<span class="dashicons dashicons-admin-page"></span><span class="singo-copy-btn-text">کپی شورت‌کد</span>';
              }, 2000);
            }

            if (navigator.clipboard && window.isSecureContext) {
              navigator.clipboard.writeText(shortcodeVal).then(setCopied).catch(function() {
                input.focus();
                input.select();
                try {
                  document.execCommand('copy');
                  setCopied();
                } catch(err) {}
              });
            } else {
              input.focus();
              input.select();
              try {
                document.execCommand('copy');
                setCopied();
              } catch(err) {}
            }
          });
        }
      });
    }

    // Initialize Educational Guide Cards inside Optionino conditional containers
    function initGuideCards() {
      if (!window.singoGuidesData) return;
      Object.keys(window.singoGuidesData).forEach(function(fieldId) {
        var container = document.getElementById('container-' + fieldId + '-0');
        if (!container || container.querySelector('.singo-module-guide-card')) return;

        var guide = window.singoGuidesData[fieldId];
        var card = document.createElement('div');
        card.className = 'singo-module-guide-card';

        var headerHtml = '';
        if (guide.title) {
          headerHtml = '<div class="singo-module-guide-header">' +
            '<span class="dashicons ' + (guide.icon || 'dashicons-info') + '"></span>' +
            '<span>' + guide.title + '</span>' +
          '</div>';
        }

        var itemsHtml = '';
        if (Array.isArray(guide.items) && guide.items.length) {
          itemsHtml = '<ul class="singo-module-guide-list">';
          guide.items.forEach(function(itemText, idx) {
            var stepNum = idx + 1;
            itemsHtml += '<li class="singo-module-guide-item">' +
              '<span class="singo-module-guide-step-badge">' + stepNum + '</span>' +
              '<span class="singo-module-guide-step-text">' + itemText + '</span>' +
            '</li>';
          });
          itemsHtml += '</ul>';
        }

        card.innerHTML = headerHtml + itemsHtml;
        container.appendChild(card);
      });
    }

    // Initialize widgets immediately and on tab switches
    initShortcodeCopyWidgets();
    initGuideCards();
    document.addEventListener('click', function(e) {
      if (e.target && e.target.closest && e.target.closest('.tablinks')) {
        setTimeout(function() {
          initShortcodeCopyWidgets();
          initGuideCards();
        }, 60);
      }
    });

    // Initial snapshot capture (short delay to ensure Optionino and color pickers ready)
    setTimeout(function() {
      initShortcodeCopyWidgets();
      initGuideCards();
      captureInitialSnapshot();
    }, 150);

    // Capture Phase Form Interception (fires BEFORE Optionino's default submit listener)
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      e.stopImmediatePropagation();

      if (errorText) {
        errorText.style.display = 'none';
        errorText.style.cssText = '';
        errorText.textContent = '';
      }
      if (successText) {
        successText.style.display = 'none';
        successText.style.cssText = '';
        successText.textContent = '';
      }

      // Zero changes: instant feedback with Yellow Warning Palette, no network request
      if (dirtyFields.size === 0) {
        showWarning('هیچ تغییری اعمال نشده است (تمامی تنظیمات بروز هستند).');
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

          showSuccess((response.data && response.data.message) || 'تنظیمات با موفقیت ذخیره شد.');
        } else {
          showError((response.data && response.data.message) || 'خطا در ذخیره‌سازی اطلاعات.');
        }
      };

      xhr.onerror = function() {
        if (OPTNNO) OPTNNO.classList.remove('loading');
        showError('خطای شبکه رخ داده است. لطفاً اتصال اینترنت خود را بررسی نمایید.');
      };

      xhr.send(formData);
    }, true);
  });
})();
