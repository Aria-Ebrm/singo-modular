/**
 * Singo Tools - Price List Off-Canvas Drawer Controller
 * Handles menu trigger binding, off-canvas interaction, category selection,
 * and Otino OTP login interception with auto-download on return.
 */
(function() {
	'use strict';

	function initSingoPriceListOffcanvas() {
		var config = window.singoOffcanvasData || {};
		var drawer   = document.getElementById('singo-price-list-offcanvas');
		var backdrop = document.getElementById('singo-price-list-backdrop');
		if (!drawer || !backdrop) {
			return;
		}

		var closeBtn     = drawer.querySelector('.singo-offcanvas-close-btn');
		var submitBtn    = document.getElementById('singo-offcanvas-submit-btn');
		var catHidden    = document.getElementById('singo-offcanvas-cat-id');
		var catTrigger   = document.getElementById('singo-offcanvas-cat-trigger');
		var catDropdown  = document.getElementById('singo-offcanvas-cat-dropdown');
		var catSearch    = document.getElementById('singo-offcanvas-cat-search');
		var catOptions   = drawer.querySelectorAll('.singo-offcanvas-cat-item');
		var catNoResult  = document.getElementById('singo-offcanvas-cat-no-result');
		var toastEl      = document.getElementById('singo-offcanvas-toast');
		var selectedText = document.getElementById('singo-offcanvas-selected-text');
		var selectedDot  = document.getElementById('singo-offcanvas-selected-dot');
		var selectedBadge= document.getElementById('singo-offcanvas-selected-badge');

		// Toast helper
		function showToast(message, duration) {
			if (!toastEl) return;
			toastEl.textContent = message;
			toastEl.style.display = 'block';
			setTimeout(function() {
				toastEl.style.display = 'none';
			}, duration || 4000);
		}

		// Open / Close Drawer
		function openDrawer() {
			var delayedLink = document.getElementById('singo-price-list-offcanvas-css');
			if (delayedLink && delayedLink.getAttribute('data-pmdelayedstyle') && !delayedLink.getAttribute('href')) {
				delayedLink.setAttribute('href', delayedLink.getAttribute('data-pmdelayedstyle'));
			}
			drawer.classList.add('singo-active');
			backdrop.classList.add('singo-active');
			document.body.classList.add('singo-offcanvas-open');
			drawer.setAttribute('aria-hidden', 'false');
		}

		function closeDrawer() {
			drawer.classList.remove('singo-active');
			backdrop.classList.remove('singo-active');
			document.body.classList.remove('singo-offcanvas-open');
			drawer.setAttribute('aria-hidden', 'true');
			if (catDropdown) {
				catDropdown.style.display = 'none';
			}
			if (catTrigger) {
				catTrigger.classList.remove('singo-is-open');
			}
		}

		window.singoOpenPriceListOffcanvas = openDrawer;
		window.singoClosePriceListOffcanvas = closeDrawer;

		if (closeBtn) {
			closeBtn.addEventListener('click', closeDrawer);
		}
		backdrop.addEventListener('click', closeDrawer);

		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && drawer.classList.contains('singo-active')) {
				closeDrawer();
			}
		});

		// 1. Menu and Trigger Binding
		function bindTriggers() {
			var selectors = [
				'.menu-item-4883 a',
				'a[href="#price-list"]',
				'a[href="#singo-price-list"]',
				'a[href="#pricelist"]',
				'.singo-price-list-trigger',
				'.singo-open-price-list'
			];

			if (config.custom_selector && typeof config.custom_selector === 'string' && config.custom_selector.trim()) {
				selectors.push(config.custom_selector.trim());
			}

			var combinedSelector = selectors.join(', ');
			var triggerElements = document.querySelectorAll(combinedSelector);
			triggerElements.forEach(function(el) {
				// Guard: Never hijack My Account navigation or dashboard elements
				if (el.closest && el.closest('.woocommerce-MyAccount-navigation, .woocommerce-MyAccount-content')) {
					return;
				}
				if (!el.getAttribute('data-singo-bound')) {
					el.setAttribute('data-singo-bound', '1');
					el.addEventListener('click', function(e) {
						e.preventDefault();
						e.stopPropagation();
						openDrawer();
					});
				}
			});

			// Fallback: Scan menu links with exact text "لیست قیمت" (strictly header / main nav only, never My Account)
			var allMenuLinks = document.querySelectorAll('.menu-item a, .elementor-item, header a, nav.main-navigation a, nav.primary-menu a');
			allMenuLinks.forEach(function(link) {
				// Guard: Never hijack WooCommerce My Account links
				if (link.closest && link.closest('.woocommerce-MyAccount-navigation, .woocommerce-MyAccount-content')) {
					return;
				}

				var text = (link.textContent || '').trim();
				var href = (link.getAttribute('href') || '').trim();

				// Never hijack real navigation links pointing to /my-account/ or actual pages
				if (href.indexOf('my-account') !== -1 || href.indexOf('/my-account/price-list') !== -1) {
					return;
				}

				if ((text === 'لیست قیمت' || text.indexOf('لیست قیمت') !== -1) && (href === '#' || href === '' || href.startsWith('#'))) {
					if (!link.getAttribute('data-singo-bound')) {
						link.setAttribute('data-singo-bound', '1');
						link.addEventListener('click', function(e) {
							e.preventDefault();
							e.stopPropagation();
							openDrawer();
						});
					}
				}
			});
		}

		bindTriggers();
		// Re-bind in case Elementor or menu scripts dynamically hydrate
		setTimeout(bindTriggers, 1000);
		setTimeout(bindTriggers, 2500);

		// 2. Category Picker Combobox Interaction
		if (catTrigger && catDropdown) {
			catTrigger.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var isOpen = catDropdown.style.display === 'flex';
				if (isOpen) {
					catDropdown.style.display = 'none';
					catTrigger.classList.remove('singo-is-open');
				} else {
					catDropdown.style.display = 'flex';
					catTrigger.classList.add('singo-is-open');
					if (catSearch) {
						catSearch.value = '';
						filterCategories('');
						setTimeout(function() { catSearch.focus(); }, 50);
					}
				}
			});

			// Filter categories
			function filterCategories(query) {
				var q = (query || '').trim().toLowerCase();
				var visibleCount = 0;
				catOptions.forEach(function(opt) {
					var name = (opt.getAttribute('data-name') || '').toLowerCase();
					if (!q || name.indexOf(q) !== -1) {
						opt.style.display = 'flex';
						visibleCount++;
					} else {
						opt.style.display = 'none';
					}
				});
				if (catNoResult) {
					catNoResult.style.display = (visibleCount === 0) ? 'block' : 'none';
				}
			}

			if (catSearch) {
				catSearch.addEventListener('input', function() {
					filterCategories(this.value);
				});
			}

			// Select Category Option
			catOptions.forEach(function(opt) {
				opt.addEventListener('click', function() {
					var id    = this.getAttribute('data-id') || '0';
					var name  = this.getAttribute('data-name') || 'تمامی محصولات';
					var count = this.getAttribute('data-count') || '';
					var color = this.getAttribute('data-color') || config.primary_color || '#ad6141';

					if (catHidden) catHidden.value = id;
					if (selectedText) selectedText.textContent = name;
					if (selectedDot) selectedDot.style.backgroundColor = color;
					if (selectedBadge) selectedBadge.textContent = count ? (count + ' کالا') : '';

					catOptions.forEach(function(o) { o.classList.remove('singo-cat-item--active'); });
					this.classList.add('singo-cat-item--active');

					catDropdown.style.display = 'none';
					catTrigger.classList.remove('singo-is-open');
				});
			});

			// Close dropdown on outside click
			document.addEventListener('click', function(e) {
				if (!drawer.contains(e.target)) return;
				if (!catTrigger.contains(e.target) && !catDropdown.contains(e.target)) {
					catDropdown.style.display = 'none';
					catTrigger.classList.remove('singo-is-open');
				}
			});
		}

		// 3. Trigger PDF Download Helper
		function triggerDownload(catId) {
			if (!config.download_base_url) return;

			var url;
			try {
				url = new URL(config.download_base_url, window.location.origin);
			} catch(e) {
				url = new URL(window.location.origin);
			}

			url.searchParams.set('singo_download_pdf', '1');
			url.searchParams.set('action', 'singo_export_price_list_pdf');
			url.searchParams.set('customer_export', '1');
			url.searchParams.set('autoprint', '1');
			url.searchParams.set('security', config.security || '');

			if (config.primary_color) {
				url.searchParams.set('primary_color', config.primary_color);
			}

			if (catId && String(catId) !== '0') {
				url.searchParams.set('category_id', String(catId));
			} else {
				url.searchParams.delete('category_id');
			}

			// Open printable PDF preview in a new tab
			window.open(url.toString(), '_blank');

			closeDrawer();
			showToast('در حال آماده‌سازی و بازگشایی پیش‌نمایش چاپ لیست قیمت...', 4000);
		}

		// 4. Print / Download Button Handler
		if (submitBtn) {
			submitBtn.addEventListener('click', function(e) {
				e.preventDefault();

				// Check if user is restricted
				if (config.is_restricted) {
					showToast('دسترسی حساب کاربری شما جهت دانلود کاتالوگ محدود شده است.', 5000);
					return;
				}

				var catId = catHidden ? (catHidden.value || '0') : '0';

				// A) If Logged In -> Direct Download
				if (config.is_logged_in) {
					submitBtn.classList.add('singo-loading');
					triggerDownload(catId);
					setTimeout(function() {
						submitBtn.classList.remove('singo-loading');
					}, 2500);
					return;
				}

				// B) If Guest -> Store in sessionStorage & Cookie and Redirect via Otino to Price List tab
				var accountPriceListUrl = config.price_list_account_url || (config.download_base_url + 'my-account/price-list/');
				var redirectTarget = accountPriceListUrl + (accountPriceListUrl.indexOf('?') === -1 ? '?' : '&') + 'singo_auto_download=1&cat_id=' + encodeURIComponent(catId);

				try {
					sessionStorage.setItem('singo_pending_pdf_download', JSON.stringify({
						category_id: catId,
						timestamp: Date.now()
					}));
					document.cookie = 'singo_pending_cat=' + encodeURIComponent(catId) + '; path=/; max-age=1800; SameSite=Lax';
				} catch(err) {
					console.warn('Singo storage error:', err);
				}

				// Inject redirect into any existing Otino or login forms on page
				var loginForms = document.querySelectorAll('form[class*="otino"], #otino-login-popup form, form.login');
				loginForms.forEach(function(f) {
					var r = f.querySelector('input[name="redirect"]');
					if (!r) {
						r = document.createElement('input');
						r.type = 'hidden';
						r.name = 'redirect';
						f.appendChild(r);
					}
					r.value = redirectTarget;
				});

				// Trigger Otino OTP Popup if available
				var otinoOpened = false;
				if (typeof window.otinoOpenPopup === 'function') {
					window.otinoOpenPopup();
					otinoOpened = true;
				} else {
					var otinoLink = document.querySelector('a[href="#login-popup"], a[href="#otino-popup"]');
					if (otinoLink) {
						otinoLink.click();
						otinoOpened = true;
					} else {
						var otinoPopupEl = document.getElementById('otino-login-popup');
						if (otinoPopupEl) {
							otinoPopupEl.style.display = 'flex';
							otinoOpened = true;
						}
					}
				}

				if (otinoOpened) {
					closeDrawer();
				} else {
					// Fallback: redirect directly to login page with redirect_to parameter
					var loginUrl = config.login_url || (config.download_base_url + 'my-account/');
					var sep = loginUrl.indexOf('?') === -1 ? '?' : '&';
					window.location.href = loginUrl + sep + 'redirect_to=' + encodeURIComponent(redirectTarget);
				}
			});
		}

		// 5. Check Pending Download After Otino Login (Resume on Page Reload)
		try {
			var pendingRaw = sessionStorage.getItem('singo_pending_pdf_download');
			var urlParams = new URLSearchParams(window.location.search);
			var autoFromUrl = urlParams.get('singo_auto_download') || urlParams.get('auto_download');
			var catFromUrl = urlParams.get('cat_id') || urlParams.get('category_id');

			if (autoFromUrl && catFromUrl !== null && config.is_logged_in) {
				// Clean URL without refresh
				try {
					var cleanUrl = new URL(window.location.href);
					cleanUrl.searchParams.delete('singo_auto_download');
					cleanUrl.searchParams.delete('auto_download');
					cleanUrl.searchParams.delete('cat_id');
					cleanUrl.searchParams.delete('category_id');
					window.history.replaceState({}, document.title, cleanUrl.toString());
				} catch(e) {}
				setTimeout(function() {
					triggerDownload(catFromUrl);
				}, 600);
			} else if (pendingRaw && config.is_logged_in) {
				var pendingData = JSON.parse(pendingRaw);
				var now = Date.now();
				// Valid within 30 minutes
				if (pendingData && (now - pendingData.timestamp < 1800000)) {
					sessionStorage.removeItem('singo_pending_pdf_download');
					document.cookie = 'singo_pending_cat=; path=/; max-age=0;';
					var savedCatId = pendingData.category_id || 0;
					setTimeout(function() {
						triggerDownload(savedCatId);
					}, 600);
				} else {
					sessionStorage.removeItem('singo_pending_pdf_download');
					document.cookie = 'singo_pending_cat=; path=/; max-age=0;';
				}
			}
		} catch(err) {
			try {
				sessionStorage.removeItem('singo_pending_pdf_download');
				document.cookie = 'singo_pending_cat=; path=/; max-age=0;';
			} catch(e) {}
		}

		// 6. Ensure any login/Otino form on the current page has the redirect input set if redirect_to or pending download exists
		(function syncLoginFormRedirects() {
			function updateAllForms() {
				var urlParams = new URLSearchParams(window.location.search);
				var targetRedirect = urlParams.get('redirect_to') || urlParams.get('redirect');

				if (!targetRedirect) {
					try {
						var pending = sessionStorage.getItem('singo_pending_pdf_download');
						if (pending) {
							var pd = JSON.parse(pending);
							if (pd && pd.category_id !== undefined) {
								var baseUrl = config.price_list_account_url || (config.download_base_url + 'my-account/price-list/');
								targetRedirect = baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + 'singo_auto_download=1&cat_id=' + encodeURIComponent(pd.category_id);
							}
						}
					} catch(e) {}
				}

				if (!targetRedirect) {
					var match = document.cookie.match(/(?:^|;\s*)singo_pending_cat=([^;]+)/);
					if (match) {
						var baseUrl = config.price_list_account_url || (config.download_base_url + 'my-account/price-list/');
						targetRedirect = baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + 'singo_auto_download=1&cat_id=' + encodeURIComponent(decodeURIComponent(match[1]));
					}
				}

				if (targetRedirect && urlParams.get('cat_id') && targetRedirect.indexOf('cat_id') === -1) {
					targetRedirect += (targetRedirect.indexOf('?') === -1 ? '?' : '&') + 'cat_id=' + encodeURIComponent(urlParams.get('cat_id'));
				}

				if (!targetRedirect && (window.location.href.indexOf('price-list') !== -1 || window.location.href.indexOf('singo_auto_download') !== -1)) {
					targetRedirect = window.location.href;
				}

				if (targetRedirect && (targetRedirect.indexOf('price-list') !== -1 || targetRedirect.indexOf('singo_auto_download') !== -1)) {
					var otinoForms = document.querySelectorAll('form[class*="otino"], form.login, #otino-login-popup form');
					otinoForms.forEach(function(f) {
						var r = f.querySelector('input[name="redirect"]');
						if (!r) {
							r = document.createElement('input');
							r.type = 'hidden';
							r.name = 'redirect';
							f.appendChild(r);
						}
						r.value = targetRedirect;
					});
				}
			}

			updateAllForms();
			document.addEventListener('click', updateAllForms, true);
			document.addEventListener('input', updateAllForms, true);
		})();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSingoPriceListOffcanvas);
	} else {
		initSingoPriceListOffcanvas();
	}
})();
