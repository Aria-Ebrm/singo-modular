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

		$global_api_key = (string) singo_tools_get_option( 'neshan_map_default_api_key', '' );
		$has_key        = ! empty( $global_api_key ) && 'web.' !== $global_api_key;
		$settings_url   = admin_url( 'admin.php?page=singo-tools' );

		$this->add_control(
			'api_key_notice',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => sprintf(
					'<div style="background: %s; border: 1px solid %s; border-right: 4px solid %s; padding: 12px 14px; border-radius: 6px; font-size: 12.5px; line-height: 1.6; direction: rtl; text-align: right; margin-bottom: 12px;">' .
						'<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">' .
							'<strong style="color: #1e293b; font-size: 13px;">%s</strong>' .
							'<span style="font-size: 11px; font-weight: 700; color: %s; background: %s; padding: 2px 8px; border-radius: 12px;">%s</span>' .
						'</div>' .
						'<p style="color: #64748b; margin: 0 0 10px 0; font-size: 12px;">%s</p>' .
						'<a href="%s" target="_blank" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%%; padding: 7px 12px; background: #ad6141; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 12px; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(173,97,65,0.2);">' .
							'<span class="dashicons dashicons-admin-generic" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>' .
							'%s' .
						'</a>' .
					'</div>',
					$has_key ? '#f0fdf4' : '#fffbeb',
					$has_key ? '#bbf7d0' : '#fde68a',
					$has_key ? '#16a34a' : '#d97706',
					esc_html__( 'کلید وب API نشان', 'singo-tools' ),
					$has_key ? '#16a34a' : '#d97706',
					$has_key ? '#dcfce7' : '#fef3c7',
					$has_key ? esc_html__( 'متصل و سراسری ✓', 'singo-tools' ) : esc_html__( 'تنظیم‌نشده', 'singo-tools' ),
					$has_key
						? esc_html__( 'کلید API به صورت خودکار از تنظیمات جعبه ابزار سینگو خوانده می‌شود.', 'singo-tools' )
						: esc_html__( 'برای نمایش نقشه، ابتدا باید کلید وب API را در تنظیمات جعبه ابزار سینگو وارد نمایید.', 'singo-tools' ),
					esc_url( $settings_url ),
					esc_html__( 'رفتن به تنظیمات افزونه برای تغییر کلید ↗', 'singo-tools' )
				),
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

		// Centralized API key from Singo Tools settings (with fallback to legacy per-widget key if any)
		$api_key = (string) singo_tools_get_option( 'neshan_map_default_api_key', '' );
		if ( empty( $api_key ) && ! empty( $settings['api_key'] ) ) {
			$api_key = trim( (string) $settings['api_key'] );
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
					<?php esc_html_e( 'کلید وب API نشان تنظیم نشده است', 'singo-tools' ); ?>
				</div>
				<div class="singo-neshan-map-placeholder-desc">
					<?php esc_html_e( 'جهت نمایش نقشه، لطفاً کلید وب API رایگان خود را در تنظیمات جعبه ابزار سینگو وارد نمایید.', 'singo-tools' ); ?>
				</div>
				<?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=singo-tools' ) ); ?>" target="_blank" style="margin-top: 12px; display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; background: #ad6141; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 12.5px; font-weight: 700;">
						<span class="dashicons dashicons-admin-generic" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
						<?php esc_html_e( 'ورود به تنظیمات افزونه', 'singo-tools' ); ?>
					</a>
				<?php endif; ?>
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
		$global_api_key = (string) singo_tools_get_option( 'neshan_map_default_api_key', '' );
		$settings_url   = admin_url( 'admin.php?page=singo-tools' );
		?>
		<#
		var globalKey = '<?php echo esc_js( $global_api_key ); ?>';
		var apiKey = globalKey ? globalKey.trim() : (settings.api_key ? settings.api_key.trim() : '');
		if ( ! apiKey || apiKey === 'web.' ) {
			#>
			<div class="singo-neshan-map-placeholder">
				<div class="singo-neshan-map-placeholder-icon">
					<span class="dashicons dashicons-location-alt" style="font-size: 38px; width: 38px; height: 38px;"></span>
				</div>
				<div class="singo-neshan-map-placeholder-title">
					<?php esc_html_e( 'کلید وب API نشان تنظیم نشده است', 'singo-tools' ); ?>
				</div>
				<div class="singo-neshan-map-placeholder-desc">
					<?php esc_html_e( 'لطفاً کلید وب API نشان را در تنظیمات افزونه (جعبه ابزار سینگو) وارد نمایید.', 'singo-tools' ); ?>
				</div>
				<a href="<?php echo esc_url( $settings_url ); ?>" target="_blank" style="margin-top: 12px; display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; background: #ad6141; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 12.5px; font-weight: 700;">
					<span class="dashicons dashicons-admin-generic" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
					<?php esc_html_e( 'رفتن به تنظیمات افزونه', 'singo-tools' ); ?>
				</a>
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
