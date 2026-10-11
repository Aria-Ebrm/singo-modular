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

})(jQuery);
