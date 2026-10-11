<?php
declare(strict_types=1);

/**
 * Singo Tools - Elementor 4 Atomic Persian Date Picker Widget
 *
 * Implements Elementor v4 Atomic Widgets standard for Persian Date Picker.
 *
 * @package SingoTools\Modules\ShamsiDate
 * @since 4.3.0
 */

namespace SingoTools\Modules\ShamsiDate;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base' ) ) {
	return;
}

use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Elements\Base\Has_Template;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_Definition;
use Elementor\Modules\AtomicWidgets\Styles\Style_Variant;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Color_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_States;
use Elementor\Modules\Components\PropTypes\Overridable_Prop_Type;

class Singo_Persian_Date_Picker extends Atomic_Widget_Base {
	use Has_Template;

	public static $widget_description = 'انتخاب تاریخ شمسی بازشو برای فرم‌های مدرن المنتور.';

	public static function get_element_type(): string {
		return 'e-form-persian-date-picker';
	}

	public function get_title(): string {
		return esc_html__( 'تاریخ شمسی', 'singo-tools' );
	}

	public function get_icon(): string {
		return 'eicon-atomic-date-picker';
	}

	public function get_categories(): array {
		return array( 'atomic-form' );
	}

	public function get_keywords(): array {
		return array( 'atomic', 'form', 'date', 'picker', 'persian', 'jalali', 'shamsi', 'singo' );
	}

	public function __construct( array $data = array(), ?array $args = null ) {
		parent::__construct( $data, $args );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_assets' ) );
		add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

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

	public function enqueue_assets(): void {
		$this->register_assets();
		wp_enqueue_script( 'singo-persian-calendar' );
		wp_enqueue_script( 'singo-datepicker-init' );
	}

	protected static function define_props_schema(): array {
		if ( class_exists( 'ElementorPro\Modules\AtomicForm\Default_Id_Provider' ) ) {
			$cssid_prop = \ElementorPro\Modules\AtomicForm\Default_Id_Provider::get_default_id_prop( self::get_element_type() );
		} else {
			$cssid_prop = String_Prop_Type::make()->default( '' );
		}

		return array(
			'classes'    => Classes_Prop_Type::make()->default( array() ),
			'placeholder' => String_Prop_Type::make()->default( esc_html__( 'انتخاب تاریخ', 'singo-tools' ) ),
			'required'   => Boolean_Prop_Type::make()->default( false ),
			'attributes' => Attributes_Prop_Type::make()->meta( Overridable_Prop_Type::ignore() ),
			'_cssid'     => $cssid_prop,
		);
	}

	protected function define_atomic_controls(): array {
		return array(
			Section::make()
				->set_label( esc_html__( 'محتوا', 'singo-tools' ) )
				->set_items( array(
					Text_Control::bind_to( 'placeholder' )
						->set_label( esc_html__( 'متن راهنما (Placeholder)', 'singo-tools' ) ),
					Switch_Control::bind_to( 'required' )
						->set_label( esc_html__( 'ضروری', 'singo-tools' ) ),
				) ),
			Section::make()
				->set_label( esc_html__( 'تنظیمات', 'singo-tools' ) )
				->set_id( 'settings' )
				->set_items( $this->get_settings_controls() ),
		);
	}

	protected function get_settings_controls(): array {
		return array(
			Text_Control::bind_to( '_cssid' )
				->set_label( esc_html__( 'شناسه فیلد', 'singo-tools' ) )
				->set_meta( $this->get_css_id_control_meta() ),
		);
	}

	protected function get_templates(): array {
		return array(
			'persian-date-picker' => __DIR__ . '/templates/persian-date-picker.html.twig',
		);
	}

	protected function define_base_styles(): array {
		$border_radius_value = Size_Prop_Type::generate( array(
			'size' => 4,
			'unit' => 'px',
		) );

		$height_value = Size_Prop_Type::generate( array(
			'size' => 38,
			'unit' => 'px',
		) );

		$border_color_value = Color_Prop_Type::generate( '#D6D5D5' );

		return array(
			'base' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_props( array(
							'border-radius' => $border_radius_value,
							'height'        => $height_value,
							'border-color'  => $border_color_value,
							'border-width'  => Size_Prop_Type::generate( array(
								'size' => 1,
								'unit' => 'px',
							) ),
							'border-style'  => String_Prop_Type::generate( 'solid' ),
							'font-size'     => Size_Prop_Type::generate( array(
								'size' => 14,
								'unit' => 'px',
							) ),
							'color'         => Color_Prop_Type::generate( '#1e1e1e' ),
							'width'         => Size_Prop_Type::generate( array(
								'size' => 100,
								'unit' => '%',
							) ),
						) )
				)
				->add_variant(
					Style_Variant::make()
						->set_state( Style_States::FOCUS )
						->add_props( array(
							'border-color'  => Color_Prop_Type::generate( '#ad6141' ),
							'outline-style' => String_Prop_Type::generate( 'none' ),
						) )
				),
		);
	}

	protected function get_css_id_control_meta(): array {
		return array(
			'layout'     => 'two-columns',
			'topDivider' => false,
		);
	}
}
