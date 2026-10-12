<?php
declare(strict_types=1);

/**
 * Singo Tools - Elementor Pro Form Persian Date Field
 *
 * Extends Elementor Pro Field_Base to provide a native Persian Datepicker
 * field in Elementor Forms.
 *
 * @package SingoTools\Modules\ShamsiDate
 * @since 4.3.0
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use ElementorPro\Modules\Forms\Fields\Field_Base;

#[\AllowDynamicProperties]
class Singo_Shamsi_Elementor_Form_Field extends Field_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'elementor/preview/init', array( $this, 'editor_preview_footer' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_assets' ) );
		add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register local calendar scripts for the Persian date field.
	 */
	public function register_assets(): void {
		$js_base_url = SINGO_TOOLS_URL . 'modules/shamsi-date/assets/js/';
		$version     = SINGO_TOOLS_VERSION;

		wp_register_script(
			'singo-persian-calendar',
			$js_base_url . 'singo-persian-calendar.js',
			array(),
			$version,
			true
		);

		wp_register_script(
			'singo-datepicker-init',
			$js_base_url . 'singo-datepicker-init.js',
			array( 'singo-persian-calendar' ),
			$version,
			true
		);
	}

	/**
	 * Enqueue scripts for Elementor editor and preview contexts.
	 */
	public function enqueue_assets(): void {
		$this->register_assets();
		wp_enqueue_script( 'singo-persian-calendar' );
		wp_enqueue_script( 'singo-datepicker-init' );
	}

	/**
	 * Register preview footer hooks.
	 */
	public function editor_preview_footer(): void {
		add_action( 'wp_footer', array( $this, 'content_template_script' ) );
	}

	/**
	 * Template filter for real-time live preview in Elementor editor.
	 */
	public function content_template_script(): void {
		?>
		<script>
			jQuery(document).ready(function() {
				if (typeof elementor === 'undefined' || !elementor.hooks) {
					return;
				}

				elementor.hooks.addFilter(
					'elementor_pro/forms/content_template/field/<?php echo esc_js( $this->get_type() ); ?>',
					function(inputField, item, i) {
						var fieldType = 'text';
						var fieldId = 'form_field_' + i;
						var baseCssClasses = 'elementor-field-textual elementor-field persian-date-input';
						var additionalClasses = (item.css_classes || '').replace(/[<>"']/g, '');
						var fieldClass = (baseCssClasses + ' ' + additionalClasses).trim();
						var placeholder = (item['persian-date-placeholder'] || 'انتخاب تاریخ').replace(/[<>"']/g, '');

						// Auto initialize calendar after element insertion
						setTimeout(function() {
							if (typeof window.initPersianDateField === 'function') {
								window.initPersianDateField(fieldId);
							}
						}, 300);

						var input = document.createElement('input');
						input.type = fieldType;
						input.id = fieldId;
						input.className = fieldClass;
						input.setAttribute('data-persian-date', '');
						input.readOnly = true;
						input.placeholder = placeholder;

						return input.outerHTML;
					},
					10,
					3
				);
			});
		</script>
		<?php
	}

	/**
	 * Field unique identifier.
	 */
	public function get_type(): string {
		return 'persian_date';
	}

	/**
	 * Human-readable field title in Elementor editor dropdown.
	 */
	public function get_name(): string {
		return esc_html__( 'تاریخ شمسی', 'singo-tools' );
	}

	/**
	 * Renders the Persian Date field on the frontend.
	 *
	 * @param array<string, mixed> $item
	 * @param int|string           $item_index
	 * @param mixed                $form
	 */
	public function render( $item, $item_index, $form ): void {
		wp_enqueue_script( 'singo-persian-calendar' );
		wp_enqueue_script( 'singo-datepicker-init' );

		$placeholder = ! empty( $item['persian-date-placeholder'] ) ?
			sanitize_text_field( (string) $item['persian-date-placeholder'] ) :
			esc_attr__( 'انتخاب تاریخ', 'singo-tools' );

		$form->add_render_attribute(
			'input' . $item_index,
			array(
				'class'             => 'elementor-field-textual elementor-field persian-date-input',
				'name'              => $form->get_attribute_name( $item ),
				'type'              => 'text',
				'data-persian-date' => '',
				'readonly'          => 'readonly',
				'placeholder'       => $placeholder,
			)
		);

		echo '<input ' . $form->get_render_attribute_string( 'input' . $item_index ) . '>';
	}

	/**
	 * Inject custom controls in Elementor editor form fields panel.
	 *
	 * @param \Elementor\Widget_Base $widget
	 */
	public function update_controls( $widget ): void {
		$elementor = $widget->get_controls( 'field_type' );
		if ( isset( $elementor['options'] ) && is_array( $elementor['options'] ) ) {
			$elementor['options']['persian_date'] = esc_html__( 'تاریخ شمسی', 'singo-tools' );
			$widget->update_control( 'field_type', $elementor );
		}

		if ( ! class_exists( '\ElementorPro\Plugin' ) ) {
			return;
		}

		$control_data = \ElementorPro\Plugin::elementor()->controls_manager->get_control_from_stack(
			$widget->get_unique_name(),
			'form_fields'
		);

		if ( is_wp_error( $control_data ) || ! isset( $control_data['fields'] ) ) {
			return;
		}

		$field_controls = array(
			'persian-date-placeholder' => array(
				'name'         => 'persian-date-placeholder',
				'label'        => esc_html__( 'متن راهنما (Placeholder)', 'singo-tools' ),
				'type'         => Controls_Manager::TEXT,
				'default'      => esc_html__( 'انتخاب تاریخ', 'singo-tools' ),
				'dynamic'      => array(
					'active' => true,
				),
				'condition'    => array(
					'field_type' => $this->get_type(),
				),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_content_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			),
		);

		$control_data['fields'] = $this->inject_field_controls( $control_data['fields'], $field_controls );
		$widget->update_control( 'form_fields', $control_data );
	}

	/**
	 * Server-side validation upon form submission.
	 *
	 * @param array<string, mixed> $field
	 * @param mixed                $record
	 * @param mixed                $ajax_handler
	 */
	public function validation( $field, $record, $ajax_handler ): void {
		// 1. Required field check
		if ( empty( $field['value'] ) && ! empty( $field['required'] ) ) {
			$ajax_handler->add_error(
				$field['id'],
				esc_html__( 'تکمیل این فیلد الزامی است.', 'singo-tools' )
			);
			return;
		}

		// 2. Format and calendar bounds check
		if ( ! empty( $field['value'] ) ) {
			$date_value = sanitize_text_field( (string) $field['value'] );

			// Basic Persian date format validation (YYYY/MM/DD)
			if ( ! preg_match( '/^\d{4}\/\d{1,2}\/\d{1,2}$/', $date_value ) ) {
				$ajax_handler->add_error(
					$field['id'],
					esc_html__( 'فرمت تاریخ شمسی وارد شده معتبر نمی‌باشد (فرمت صحیح: 1403/05/20).', 'singo-tools' )
				);
				return;
			}

			$parts = explode( '/', $date_value );
			if ( 3 === count( $parts ) ) {
				$year  = (int) $parts[0];
				$month = (int) $parts[1];
				$day   = (int) $parts[2];

				if ( $year < 1300 || $year > 1500 || $month < 1 || $month > 12 || $day < 1 || $day > 31 ) {
					$ajax_handler->add_error(
						$field['id'],
						esc_html__( 'تاریخ وارد شده در محدوده معتبر تقویم هجری شمسی نیست.', 'singo-tools' )
					);
				}
			}
		}
	}
}
