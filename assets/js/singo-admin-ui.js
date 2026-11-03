/**
 * Singo Tools - Global Admin UI Toolkit Engine
 *
 * Provides shared client-side utilities:
 * - One-click Copy Handler with Clipboard API and fallbacks
 * - Modern Floating Toast Notifications
 *
 * @package SingoTools
 * @since 4.2.0
 */

(function($) {
	'use strict';

	window.SingoUI = window.SingoUI || {

		/**
		 * Display a modern floating toast notification.
		 *
		 * @param {string} message Toast message text.
		 * @param {string} type    Toast type: 'success' | 'warning' | 'danger' | 'info'.
		 * @param {number} duration Duration in milliseconds before dismissing.
		 */
		toast: function(message, type, duration) {
			type = type || 'success';
			duration = duration || 3000;

			var $container = $('.singo-toast-container');
			if (!$container.length) {
				$container = $('<div class="singo-toast-container"></div>').appendTo('body');
			}

			var iconMap = {
				success: 'dashicons-yes-alt',
				warning: 'dashicons-warning',
				danger: 'dashicons-dismiss',
				info: 'dashicons-info'
			};

			var icon = iconMap[type] || 'dashicons-info';

			var $toast = $(
				'<div class="singo-toast singo-toast--' + type + '">' +
					'<span class="dashicons ' + icon + '"></span>' +
					'<span>' + message + '</span>' +
				'</div>'
			).appendTo($container);

			setTimeout(function() {
				$toast.addClass('singo-toast--fadeout');
				setTimeout(function() {
					$toast.remove();
				}, 260);
			}, duration);
		},

		/**
		 * Copy a given text string to the system clipboard.
		 *
		 * @param {string} text Text to copy.
		 * @param {function} callback Optional success callback.
		 */
		copyToClipboard: function(text, callback) {
			if (!text) return;

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(function() {
					if (typeof callback === 'function') callback(true);
				}).catch(function() {
					fallbackCopy(text, callback);
				});
			} else {
				fallbackCopy(text, callback);
			}

			function fallbackCopy(str, cb) {
				var $temp = $('<textarea>')
					.css({
						position: 'fixed',
						left: '-9999px',
						top: '0',
						opacity: 0
					})
					.val(str)
					.appendTo('body');

				$temp.trigger('select');
				try {
					var successful = document.execCommand('copy');
					$temp.remove();
					if (typeof cb === 'function') cb(successful);
				} catch (err) {
					$temp.remove();
					if (typeof cb === 'function') cb(false);
				}
			}
		}
	};

	// Global Event Delegation for all copy triggers across the admin
	$(document).on('click', '.singo-copy-btn, [data-copy]', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var copyText = $btn.attr('data-copy');

		// If no direct data-copy, check data-copy-target (CSS selector)
		if (!copyText) {
			var targetSelector = $btn.attr('data-copy-target');
			if (targetSelector && $(targetSelector).length) {
				var $target = $(targetSelector);
				copyText = $target.is('input, textarea') ? $target.val() : $target.text();
			}
		}

		// Fallback to button's own text if still empty
		if (!copyText) {
			copyText = $btn.text().trim();
		}

		if (!copyText) return;

		window.SingoUI.copyToClipboard(copyText, function(success) {
			if (success) {
				var originalHtml = $btn.data('singo-orig-html');
				if (!originalHtml) {
					originalHtml = $btn.html();
					$btn.data('singo-orig-html', originalHtml);
				}

				$btn.addClass('singo-copied');
				$btn.html('<span class="dashicons dashicons-yes"></span> ' + ($btn.attr('data-copied-label') || 'کپی شد!'));

				var customToastMsg = $btn.attr('data-toast') || 'متن با موفقیت کپی شد.';
				window.SingoUI.toast(customToastMsg, 'success');

				setTimeout(function() {
					$btn.removeClass('singo-copied');
					$btn.html(originalHtml);
				}, 2200);
			} else {
				window.SingoUI.toast('امکان کپی خودکار فراهم نشد. لطفاً دستی کپی نمایید.', 'warning');
			}
		});
	});

	// =========================================================================
	// OPTIONINO SKELETON LOADER TRANSITION (SMOOTH REVEAL)
	// =========================================================================
	function initOptioninoSkeletonTransition() {
		var $skeleton = $('#singo-optionino-skeleton');
		var $optionino = $('#optionino');
		if (!$skeleton.length || !$optionino.length) {
			return;
		}

		var revealed = false;
		function revealRealContent() {
			if (revealed) return;
			revealed = true;

			var $section = $optionino.find('.optionino-section');
			$section.css({
				'display': 'flex',
				'opacity': '0'
			});

			$skeleton.css({
				'opacity': '0',
				'transition': 'opacity 0.22s ease-in-out'
			});

			setTimeout(function() {
				$skeleton.remove();
				$section.css({
					'transition': 'opacity 0.25s ease-in-out',
					'opacity': '1'
				});
			}, 220);
		}

		// Wait until document is ready and Optionino has stabilized
		if (document.readyState === 'complete') {
			setTimeout(revealRealContent, 100);
		} else {
			$(window).on('load', function() {
				setTimeout(revealRealContent, 100);
			});
			// Robust fallback: maximum 650ms so user is never blocked
			setTimeout(revealRealContent, 650);
		}
	}

	// =========================================================================
	// OPTIONINO TABS BULLETPROOF SWITCHING ENGINE
	// =========================================================================
	window.openTabOptnno = function(evt, tabName) {
		if (!tabName) return;

		var $container = $('#optionino');
		var $tabContents = $container.find('.tabcontent');
		var $tabLinks = $container.find('.tablinks');

		// 1. Hide all tabcontents and remove active classes
		$tabContents.hide().css('display', 'none').removeClass('active');
		$tabLinks.removeClass('active');

		// 2. Display the selected tabcontent by ID
		var $target = $('#' + tabName);
		if ($target.length) {
			$target.show().css('display', 'block').addClass('active');
		}

		// 3. Mark the corresponding tablink button as active
		if (evt && evt.currentTarget) {
			$(evt.currentTarget).addClass('active');
		} else {
			$container.find('.tablinks[onclick*="' + tabName + '"]').addClass('active');
		}

		// 4. Synchronize Optionino Save Changes box visibility
		if (tabName === 'price_list_export_tab' || tabName === 'price_list_category_colors_tab' || tabName === 'price_list_download_reports_tab') {
			$('.optionino-options-save-box').stop(true, true).hide();
		} else {
			$('.optionino-options-save-box').stop(true, true).show();
		}
	};

	function initOptioninoTabs() {
		var $container = $('#optionino');
		if (!$container.length) return;

		var $tabLinks = $container.find('.tablinks');
		var $tabContents = $container.find('.tabcontent');
		if (!$tabLinks.length || !$tabContents.length) return;

		var $activeLink = $tabLinks.filter('.active');
		if (!$activeLink.length) {
			// Find tabName from first tablink's onclick attribute
			var onclickAttr = $tabLinks.first().attr('onclick') || '';
			var match = onclickAttr.match(/openTabOptnno\s*\(\s*event\s*,\s*['"]([^'"]+)['"]\s*\)/);
			var firstTabName = match && match[1] ? match[1] : $tabContents.first().attr('id');
			window.openTabOptnno(null, firstTabName);
		} else {
			var activeOnclick = $activeLink.first().attr('onclick') || '';
			var activeMatch = activeOnclick.match(/openTabOptnno\s*\(\s*event\s*,\s*['"]([^'"]+)['"]\s*\)/);
			if (activeMatch && activeMatch[1]) {
				var $target = $('#' + activeMatch[1]);
				if ($target.length && $target.css('display') === 'none') {
					$tabContents.hide().css('display', 'none').removeClass('active');
					$target.show().css('display', 'block').addClass('active');
				}
			}
		}
	}

	$(document).ready(function() {
		initOptioninoSkeletonTransition();
		initOptioninoTabs();
	});

	$(window).on('load', function() {
		initOptioninoTabs();
	});

	setTimeout(initOptioninoTabs, 100);
	setTimeout(initOptioninoTabs, 350);

	// Event delegation to catch clicks even if user clicks inner title/desc/svg
	$(document).on('click', '.optionino-section .tab .tablinks', function(e) {
		var $btn = $(this);
		var onclickAttr = $btn.attr('onclick') || '';
		var match = onclickAttr.match(/openTabOptnno\s*\(\s*event\s*,\s*['"]([^'"]+)['"]\s*\)/);
		if (match && match[1]) {
			e.preventDefault();
			window.openTabOptnno(e, match[1]);
		}
	});

})(jQuery);
