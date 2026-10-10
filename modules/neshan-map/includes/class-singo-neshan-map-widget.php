<?php
declare(strict_types=1);

namespace SingoTools\Modules\NeshanMap\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

defined( 'ABSPATH' ) || exit;

/**
 * Singo Tools - Neshan Map Elementor Widget
 *
 * @package SingoTools\Modules\NeshanMap\Widgets
 * @since 6.1.0
 */
class Singo_Neshan_Map_Widget extends Widget_Base {

	public function get_name(): string {
		return 'singo_neshan_map';
	}

	public function get_title(): string {
		return esc_html__( 'نقشه نشان', 'singo-tools' );
	}

	public function get_icon(): string {
		return 'singo-neshan-map-icon';
	}

	public function get_categories(): array {
		return array( 'general', 'basic' );
	}

	public function get_keywords(): array {
		return array( 'map', 'neshan', 'نقشه', 'نشان', 'مسیریاب', 'سفارشی', 'singo' );
	}

	protected function register_controls(): void {
		// 1. Content Controls
		$this->start_controls_section(
			'section_map_settings',
			array(
				'label' => esc_html__( 'تنظیمات نقشه', 'singo-tools' ),
			)
		);

		$default_api_key = (string) singo_tools_get_option( 'neshan_map_default_api_key', '' );

		$this->add_control(
			'api_key',
			array(
				'label'       => esc_html__( 'کلید وب API نشان', 'singo-tools' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $default_api_key,
				'placeholder' => 'web.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
				'description' => sprintf(
					/* translators: %s: Neshan Platform link */
					esc_html__( 'کلید وب API خود را از %s دریافت و وارد کنید.', 'singo-tools' ),
					'<a href="https://platform.neshan.org/panel/api-key" target="_blank" rel="noopener noreferrer">' . esc_html__( 'پنل توسعه‌دهندگان نشان', 'singo-tools' ) . '</a>'
				),
				'label_block' => true,
			)
		);

		$this->add_control(
			'map_latitude',
			array(
				'label'       => esc_html__( 'عرض جغرافیایی (Latitude)', 'singo-tools' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '35.699789639952414',
				'placeholder' => '35.699789639952414',
				'label_block' => true,
			)
		);

		$this->add_control(
			'map_longitude',
			array(
				'label'       => esc_html__( 'طول جغرافیایی (Longitude)', 'singo-tools' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '51.33748508581425',
				'placeholder' => '51.33748508581425',
				'label_block' => true,
			)
		);

		$this->add_control(
			'map_zoom',
			array(
				'label'   => esc_html__( 'میزان بزرگ‌نمایی (Zoom)', 'singo-tools' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => 15,
				),
				'range'   => array(
					'px' => array(
						'min'  => 2,
						'max'  => 21,
						'step' => 1,
					),
				),
			)
		);

		$this->add_control(
			'map_type',
			array(
				'label'   => esc_html__( 'نوع لایه نقشه', 'singo-tools' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'neshanVector',
				'options' => array(
					'neshanVector'      => esc_html__( 'وکتور روز (سریع‌ترین)', 'singo-tools' ),
					'neshanVectorNight' => esc_html__( 'وکتور شب', 'singo-tools' ),
					'neshanRaster'      => esc_html__( 'استاندارد روز', 'singo-tools' ),
					'neshanRasterNight' => esc_html__( 'استاندارد شب', 'singo-tools' ),
				),
			)
		);

		$this->add_control(
			'show_poi',
			array(
				'label'        => esc_html__( 'نمایش اماکن و کسب‌وکارها (POI)', 'singo-tools' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'بله', 'singo-tools' ),
				'label_off'    => esc_html__( 'خیر', 'singo-tools' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_traffic',
			array(
				'label'        => esc_html__( 'نمایش لایه ترافیک زنده', 'singo-tools' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'بله', 'singo-tools' ),
				'label_off'    => esc_html__( 'خیر', 'singo-tools' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->end_controls_section();

		// 2. Style Controls
		$this->start_controls_section(
			'section_map_style',
			array(
				'label' => esc_html__( 'استایل و ظاهر', 'singo-tools' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'marker_color',
			array(
				'label'     => esc_html__( 'رنگ نشانگر (Marker)', 'singo-tools' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ad6141',
				'selectors' => array(
					'{{WRAPPER}} .singo-neshan-map-container .mapboxgl-marker svg g g[fill]' => 'fill: {{VALUE}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'map_height',
			array(
				'label'      => esc_html__( 'ارتفاع نقشه', 'singo-tools' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh', '%' ),
				'range'      => array(
					'px' => array(
						'min'  => 150,
						'max'  => 1200,
						'step' => 10,
					),
					'vh' => array(
						'min' => 10,
						'max' => 100,
					),
					'%'  => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'default'    => array(
					'size' => 420,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .singo-neshan-map-container' => 'height: {{SIZE}}{{UNIT}} !important; min-height: {{SIZE}}{{UNIT}} !important;',
					'{{WRAPPER}} .singo-neshan-map-placeholder' => 'min-height: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'map_width',
			array(
				'label'      => esc_html__( 'عرض نقشه', 'singo-tools' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vw', '%' ),
				'range'      => array(
					'px' => array(
						'min'  => 200,
						'max'  => 1600,
						'step' => 10,
					),
					'vw' => array(
						'min' => 10,
						'max' => 100,
					),
					'%'  => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'default'    => array(
					'size' => 100,
					'unit' => '%',
				),
				'selectors'  => array(
					'{{WRAPPER}} .singo-neshan-map-container' => 'width: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_control(
			'map_border_radius',
			array(
				'label'      => esc_html__( 'گوشه‌های گرد (Border Radius)', 'singo-tools' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .singo-neshan-map-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'map_border',
				'selector' => '{{WRAPPER}} .singo-neshan-map-container',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'map_box_shadow',
				'selector' => '{{WRAPPER}} .singo-neshan-map-container',
			)
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$api_key = ! empty( $settings['api_key'] ) ? trim( (string) $settings['api_key'] ) : '';
		if ( empty( $api_key ) ) {
			$api_key = (string) singo_tools_get_option( 'neshan_map_default_api_key', '' );
		}

		$latitude     = ! empty( $settings['map_latitude'] ) ? trim( (string) $settings['map_latitude'] ) : '35.699789639952414';
		$longitude    = ! empty( $settings['map_longitude'] ) ? trim( (string) $settings['map_longitude'] ) : '51.33748508581425';
		$marker_color = ! empty( $settings['marker_color'] ) ? sanitize_hex_color( (string) $settings['marker_color'] ) : '#ad6141';
		if ( empty( $marker_color ) ) {
			$marker_color = '#ad6141';
		}

		$map_zoom     = isset( $settings['map_zoom']['size'] ) ? absint( $settings['map_zoom']['size'] ) : 15;
		$map_type     = ! empty( $settings['map_type'] ) ? sanitize_text_field( (string) $settings['map_type'] ) : 'neshanVector';
		$show_poi     = 'yes' === ( $settings['show_poi'] ?? '' );
		$show_traffic = 'yes' === ( $settings['show_traffic'] ?? '' );

		// If API key is missing, render polite visual placeholder
		if ( empty( $api_key ) || 'web.' === $api_key ) {
			?>
			<div class="singo-neshan-map-placeholder">
				<div class="singo-neshan-map-placeholder-icon">
					<span class="dashicons dashicons-location-alt" style="font-size: 38px; width: 38px; height: 38px;"></span>
				</div>
				<div class="singo-neshan-map-placeholder-title">
					<?php esc_html_e( 'کلید وب API نشان وارد نشده است', 'singo-tools' ); ?>
				</div>
				<div class="singo-neshan-map-placeholder-desc">
					<?php
					printf(
						/* translators: %s: Neshan Platform link */
						esc_html__( 'لطفاً کلید وب API رایگان خود را از %s دریافت کرده و در تنظیمات ویجت یا در بخش سایر امکانات وارد نمایید.', 'singo-tools' ),
						'<a href="https://platform.neshan.org/panel/api-key" target="_blank" rel="noopener noreferrer">' . esc_html__( 'پنل توسعه‌دهندگان نشان', 'singo-tools' ) . '</a>'
					);
					?>
				</div>
			</div>
			<?php
			return;
		}

		$widget_id = $this->get_id();
		$unique_id = uniqid( 'singo_neshan_' );
		$map_id    = 'singo_map_' . $widget_id . '_' . $unique_id;

		$this->add_render_attribute(
			'map-wrapper',
			array(
				'class'              => 'singo-neshan-map-container',
				'id'                 => esc_attr( $map_id ),
				'data-widget-id'     => esc_attr( $widget_id ),
				'data-instance-id'   => esc_attr( $unique_id ),
				'data-api-key'       => esc_attr( $api_key ),
				'data-lat'           => esc_attr( $latitude ),
				'data-lng'           => esc_attr( $longitude ),
				'data-marker-color'  => esc_attr( $marker_color ),
				'data-zoom'          => esc_attr( (string) $map_zoom ),
				'data-map-type'      => esc_attr( $map_type ),
				'data-poi'           => $show_poi ? 'true' : 'false',
				'data-traffic'       => $show_traffic ? 'true' : 'false',
			)
		);
		?>
		<div <?php echo $this->get_render_attribute_string( 'map-wrapper' ); ?>></div>

		<script type="text/javascript">
			(function() {
				function initMap_<?php echo esc_js( $unique_id ); ?>() {
					if (typeof nmp_mapboxgl === 'undefined') {
						setTimeout(initMap_<?php echo esc_js( $unique_id ); ?>, 250);
						return;
					}

					var mapEl = document.getElementById('<?php echo esc_js( $map_id ); ?>');
					if (!mapEl || mapEl.singoNeshanMapInitialized) {
						return;
					}

					try {
						var apiKey = mapEl.getAttribute('data-api-key');
						var latitude = parseFloat(mapEl.getAttribute('data-lat'));
						var longitude = parseFloat(mapEl.getAttribute('data-lng'));
						var markerColor = mapEl.getAttribute('data-marker-color') || '#ad6141';
						var mapZoom = parseInt(mapEl.getAttribute('data-zoom') || '15', 10);
						var mapTypeStr = mapEl.getAttribute('data-map-type') || 'neshanVector';
						var poi = mapEl.getAttribute('data-poi') === 'true';
						var traffic = mapEl.getAttribute('data-traffic') === 'true';

						if (isNaN(latitude) || isNaN(longitude)) {
							return;
						}

						var mapCenter = [longitude, latitude];

						var map = new nmp_mapboxgl.Map({
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
					} catch(err) {
						console.error('Neshan Map Init Error:', err);
					}
				}

				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', initMap_<?php echo esc_js( $unique_id ); ?>);
				} else {
					initMap_<?php echo esc_js( $unique_id ); ?>();
				}
			})();
		</script>
		<?php
	}

	protected function content_template(): void {
		?>
		<#
		var apiKey = settings.api_key ? settings.api_key.trim() : '';
		if ( ! apiKey || apiKey === 'web.' ) {
			#>
			<div class="singo-neshan-map-placeholder">
				<div class="singo-neshan-map-placeholder-icon">
					<span class="dashicons dashicons-location-alt" style="font-size: 38px; width: 38px; height: 38px;"></span>
				</div>
				<div class="singo-neshan-map-placeholder-title">
					<?php esc_html_e( 'کلید وب API نشان وارد نشده است', 'singo-tools' ); ?>
				</div>
				<div class="singo-neshan-map-placeholder-desc">
					<?php esc_html_e( 'لطفاً کلید وب API معتبر نشان را در پنل تنظیمات سمت راست وارد نمایید.', 'singo-tools' ); ?>
				</div>
			</div>
			<#
		} else {
			var widgetId = id;
			var uniqueId = 'singo_editor_' + Math.random().toString(36).substring(2, 9);
			var mapId = 'singo_map_preview_' + widgetId + '_' + uniqueId;
			var lat = settings.map_latitude || '35.699789639952414';
			var lng = settings.map_longitude || '51.33748508581425';
			var markerColor = settings.marker_color || '#ad6141';
			var zoom = settings.map_zoom.size || 15;
			var mapType = settings.map_type || 'neshanVector';
			var poi = (settings.show_poi === 'yes');
			var traffic = (settings.show_traffic === 'yes');
			#>
			<div class="singo-neshan-map-container"
				id="{{ mapId }}"
				data-widget-id="{{ widgetId }}"
				data-instance-id="{{ uniqueId }}"
				data-api-key="{{ apiKey }}"
				data-lat="{{ lat }}"
				data-lng="{{ lng }}"
				data-marker-color="{{ markerColor }}"
				data-zoom="{{ zoom }}"
				data-map-type="{{ mapType }}"
				data-poi="{{ poi }}"
				data-traffic="{{ traffic }}">
			</div>
			<#
		}
		#>
		<?php
	}
}
