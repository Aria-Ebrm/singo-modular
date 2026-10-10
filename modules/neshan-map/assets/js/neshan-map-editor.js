/**
 * Singo Tools - Neshan Map Elementor Editor & Frontend Integration
 */
(function($) {
	'use strict';

	function initNeshanMap($scope) {
		const $container = $scope.find('.singo-neshan-map-container');
		if ($container.length === 0) {
			return;
		}

		const mapEl = $container[0];
		if (mapEl.singoNeshanMapInitialized) {
			return;
		}

		if (typeof nmp_mapboxgl === 'undefined') {
			setTimeout(function() {
				initNeshanMap($scope);
			}, 250);
			return;
		}

		try {
			const apiKey = mapEl.getAttribute('data-api-key');
			if (!apiKey || apiKey === 'web.') {
				return;
			}

			const latitude = parseFloat(mapEl.getAttribute('data-lat'));
			const longitude = parseFloat(mapEl.getAttribute('data-lng'));
			const markerColor = mapEl.getAttribute('data-marker-color') || '#ad6141';
			const mapZoom = parseInt(mapEl.getAttribute('data-zoom') || '15', 10);
			const mapTypeStr = mapEl.getAttribute('data-map-type') || 'neshanVector';
			const poi = mapEl.getAttribute('data-poi') === 'true';
			const traffic = mapEl.getAttribute('data-traffic') === 'true';

			if (isNaN(latitude) || isNaN(longitude)) {
				return;
			}

			const mapCenter = [longitude, latitude];

			const map = new nmp_mapboxgl.Map({
				container: mapEl,
				mapKey: apiKey,
				mapType: (nmp_mapboxgl.Map.mapTypes && nmp_mapboxgl.Map.mapTypes[mapTypeStr]) ? nmp_mapboxgl.Map.mapTypes[mapTypeStr] : nmp_mapboxgl.Map.mapTypes.neshanVector,
				zoom: mapZoom,
				pitch: 0,
				center: mapCenter,
				minZoom: 2,
				maxZoom: 21,
				trackResize: true,
				poi: poi,
				traffic: traffic,
				mapTypeControllerOptions: {
					show: true,
					position: 'bottom-right'
				}
			});

			new nmp_mapboxgl.Marker({
				color: markerColor,
				draggable: false
			})
			.setLngLat(mapCenter)
			.addTo(map);

			mapEl.singoNeshanMapInitialized = true;
			mapEl.setAttribute('data-initialized', 'true');

			if (window.elementor) {
				$(window).on('resize', function() {
					setTimeout(function() {
						map.resize();
					}, 200);
				});
			}
		} catch (err) {
			console.error('Error initializing Singo Neshan Map:', err);
		}
	}

	$(window).on('elementor/frontend/init', function() {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/singo_neshan_map.default', function($scope) {
				initNeshanMap($scope);
			});
		}
	});

	$(document).ready(function() {
		$('.singo-neshan-map-container').each(function() {
			const $el = $(this);
			if (!$el.data('initialized')) {
				initNeshanMap($el.parent());
			}
		});
	});

})(jQuery);
