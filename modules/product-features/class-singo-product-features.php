<?php
declare(strict_types=1);

/**
 * Product Features Module for Singo Tools (Singo_Product_Features)
 * PHP 8+ Standardized
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Product_Features {

	/**
	 * Single instance of the class.
	 *
	 * @var Singo_Product_Features|null
	 */
	private static ?Singo_Product_Features $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Default groups list.
	 *
	 * @return array
	 */
	public static function get_default_groups_list(): array {
		return array(
			"مشخصات کلی",
			"سایر مشخصات",
		);
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			add_filter( 'pre_update_option_singo_tools_options', array( $this, 'intercept_option_save' ), 10, 2 );
			add_action( 'update_option_singo_tools_options', array( $this, 'purge_cache_on_save' ) );
		}

		$enabled = singo_tools_get_option( 'enable_product_features', true );
		if ( ! $enabled ) {
			return;
		}

		// WooCommerce Attribute UI Integration (Strictly Admin Only)
		if ( is_admin() ) {
			add_action( 'woocommerce_after_add_attribute_fields', array( $this, 'add_attribute_group_field' ) );
			add_action( 'woocommerce_after_edit_attribute_fields', array( $this, 'edit_attribute_group_field' ) );
			add_action( 'woocommerce_attribute_added', array( $this, 'save_attribute_group' ), 10, 2 );
			add_action( 'woocommerce_attribute_updated', array( $this, 'save_attribute_group' ), 10, 2 );
			add_action( 'woocommerce_attribute_deleted', array( $this, 'delete_attribute_group' ), 10, 3 );

			// Fallback for themes/plugins that override the admin template and remove the hooks
			add_action( 'admin_footer', array( $this, 'admin_footer_js_fallback' ) );
		}
	}

	/**
	 * Intercept Optionino save to process groups.
	 */
	public function intercept_option_save( mixed $new_value, mixed $old_value ): mixed {
		if ( ! is_array( $new_value ) ) {
			return $new_value;
		}

		// Process native Optionino repeater groups if posted
		if ( isset( $new_value['mu_attribute_groups'] ) && is_array( $new_value['mu_attribute_groups'] ) ) {
			$repeater_names   = array();
			$repeater_default = '';

			foreach ( $new_value['mu_attribute_groups'] as $item ) {
				if ( is_array( $item ) && ! empty( $item['group_title'] ) ) {
					$title = sanitize_text_field( wp_unslash( $item['group_title'] ) );
					if ( ! empty( $title ) && ! in_array( $title, $repeater_names, true ) ) {
						$repeater_names[] = $title;
						$is_def = isset( $item['is_default'] ) && ( $item['is_default'] === 'true' || $item['is_default'] === 'on' || $item['is_default'] === '1' );
						if ( $is_def && empty( $repeater_default ) ) {
							$repeater_default = $title;
						}
					}
				}
			}

			if ( ! empty( $repeater_names ) ) {
				$new_value['mu_groups'] = $repeater_names;
				if ( ! empty( $repeater_default ) ) {
					$new_value['mu_default_group'] = $repeater_default;
				}
			}
		}

		// Safeguard: Preserve existing groups if mu_groups is missing or empty
		if ( ! isset( $new_value['mu_groups'] ) || ! is_array( $new_value['mu_groups'] ) || empty( $new_value['mu_groups'] ) ) {
			if ( isset( $old_value['mu_groups'] ) && is_array( $old_value['mu_groups'] ) && ! empty( $old_value['mu_groups'] ) ) {
				$new_value['mu_groups'] = $old_value['mu_groups'];
			} else {
				$saved = get_option( 'mu_attribute_groups', array() );
				$new_value['mu_groups'] = ( is_array( $saved ) && ! empty( $saved ) ) ? $saved : self::get_default_groups_list();
			}
		}

		// Safeguard: Preserve default group if missing or empty
		if ( ! isset( $new_value['mu_default_group'] ) || empty( $new_value['mu_default_group'] ) ) {
			if ( isset( $old_value['mu_default_group'] ) && ! empty( $old_value['mu_default_group'] ) ) {
				$new_value['mu_default_group'] = $old_value['mu_default_group'];
			} else {
				$new_value['mu_default_group'] = get_option( 'mu_default_attribute_group', 'سایر مشخصات' );
			}
		}

		$raw_groups = $new_value['mu_groups'];
		$old_posted = isset( $new_value['mu_groups_old'] ) && is_array( $new_value['mu_groups_old'] ) ? $new_value['mu_groups_old'] : array();

		$old_groups = get_option( 'mu_attribute_groups', self::get_default_groups_list() );
		if ( ! is_array( $old_groups ) ) {
			$old_groups = self::get_default_groups_list();
		}

		$sanitized_groups = array();
		$rename_map       = array();

		foreach ( $raw_groups as $i => $group_name ) {
			$clean_name = sanitize_text_field( wp_unslash( $group_name ) );
			if ( ! empty( $clean_name ) && ! in_array( $clean_name, $sanitized_groups, true ) ) {
				$sanitized_groups[] = $clean_name;

				// Track explicit rename per row from posted old_group input
				if ( isset( $old_posted[ $i ] ) ) {
					$clean_old = sanitize_text_field( wp_unslash( $old_posted[ $i ] ) );
					if ( ! empty( $clean_old ) && $clean_old !== $clean_name ) {
						$rename_map[ $clean_old ] = $clean_name;
					}
				}
			}
		}

		if ( empty( $sanitized_groups ) ) {
			$sanitized_groups = self::get_default_groups_list();
		}

		// Fallback detection if mu_groups_old was not passed (e.g. legacy form save)
		if ( empty( $rename_map ) ) {
			$removed_old_names = array_diff( $old_groups, $sanitized_groups );
			$added_new_names   = array_diff( $sanitized_groups, $old_groups );

			if ( 1 === count( $removed_old_names ) && 1 === count( $added_new_names ) ) {
				$old_name                = reset( $removed_old_names );
				$new_name                = reset( $added_new_names );
				$rename_map[ $old_name ] = $new_name;
			}
		}

		$new_value['mu_groups'] = $sanitized_groups;

		// Default group handling
		$default_group = isset( $new_value['mu_default_group'] ) ? sanitize_text_field( wp_unslash( $new_value['mu_default_group'] ) ) : 'سایر مشخصات';
		if ( isset( $rename_map[ $default_group ] ) ) {
			$default_group = $rename_map[ $default_group ];
		}
		if ( ! in_array( $default_group, $sanitized_groups, true ) ) {
			$default_group = reset( $sanitized_groups );
		}

		$new_value['mu_default_group'] = $default_group;

		// Rebuild synchronized native repeater structure
		$synced_repeater = array();
		foreach ( $sanitized_groups as $g_name ) {
			$synced_repeater[] = array(
				'group_title' => $g_name,
				'is_default'  => ( $g_name === $default_group ) ? 'true' : 'false',
			);
		}
		$new_value['mu_attribute_groups'] = $synced_repeater;

		// Persist dedicated options as permanent backup
		update_option( 'mu_attribute_groups', $sanitized_groups, true );
		update_option( 'mu_default_attribute_group', $default_group, true );

		// Update attribute group map for renamed or deleted groups
		$attr_map = get_option( 'mu_attribute_groups_map', array() );
		if ( is_array( $attr_map ) && ! empty( $attr_map ) ) {
			$updated_map = false;
			foreach ( $attr_map as $attr_slug => $group_title ) {
				if ( isset( $rename_map[ $group_title ] ) ) {
					$attr_map[ $attr_slug ] = $rename_map[ $group_title ];
					$updated_map           = true;
				} elseif ( ! in_array( $group_title, $sanitized_groups, true ) ) {
					$attr_map[ $attr_slug ] = $default_group;
					$updated_map           = true;
				}
			}
			if ( $updated_map ) {
				update_option( 'mu_attribute_groups_map', $attr_map, false );
			}
		}

		return $new_value;
	}

	/**
	 * Purge product specs cache when Optionino settings are saved.
	 */
	public function purge_cache_on_save(): void {
		$options = get_option( 'singo_tools_options', array() );

		if ( is_array( $options ) ) {
			if ( isset( $options['mu_groups'] ) && is_array( $options['mu_groups'] ) ) {
				update_option( 'mu_attribute_groups', $options['mu_groups'] );
			}
			if ( isset( $options['mu_default_group'] ) && ! empty( $options['mu_default_group'] ) ) {
				update_option( 'mu_default_attribute_group', sanitize_text_field( $options['mu_default_group'] ) );
			}
		}

		wp_cache_delete( 'mu_global_attr_map_cache' );
	}

	/**
	 * Output select field on Add Attribute page.
	 */
	public function add_attribute_group_field(): void {
		if ( ! singo_tools_get_option( 'enable_product_features', true ) ) {
			return;
		}

		$groups  = mu_get_attribute_groups();
		$default = mu_get_default_attribute_group();
		?>
		<div class="form-field">
			<label for="mu_attribute_group">گروه ویژگی (افزونه Singo Tools)</label>
			<select name="mu_attribute_group" id="mu_attribute_group">
				<?php foreach ( $groups as $group_name => $priority ) : ?>
					<option value="<?php echo esc_attr( $group_name ); ?>" <?php selected( $group_name, $default ); ?>>
						<?php echo esc_html( $group_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description">گروه نمایشی این ویژگی را در بخش ویژگی‌های محصول انتخاب کنید.</p>
		</div>
		<?php
	}

	/**
	 * Output select field on Edit Attribute page.
	 */
	public function edit_attribute_group_field(): void {
		if ( ! singo_tools_get_option( 'enable_product_features', true ) ) {
			return;
		}

		if ( ! isset( $_GET['edit'] ) ) {
			return;
		}

		$attribute_id = absint( $_GET['edit'] );
		$attribute    = wc_get_attribute( $attribute_id );
		if ( ! $attribute ) {
			return;
		}

		$taxonomy      = $attribute->slug;
		$groups_map    = get_option( 'mu_attribute_groups_map', array() );
		$groups        = mu_get_attribute_groups();
		$default       = mu_get_default_attribute_group();
		$current_group = isset( $groups_map[ $taxonomy ] ) ? $groups_map[ $taxonomy ] : $default;
		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="mu_attribute_group">گروه ویژگی (Singo Tools)</label>
			</th>
			<td>
				<select name="mu_attribute_group" id="mu_attribute_group">
					<?php foreach ( $groups as $group_name => $priority ) : ?>
						<option value="<?php echo esc_attr( $group_name ); ?>" <?php selected( $group_name, $current_group ); ?>>
							<?php echo esc_html( $group_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">گروه نمایشی این ویژگی را در جدول مشخصات محصول تغییر دهید.</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the assigned group when an attribute is created or updated.
	 */
	public function save_attribute_group( mixed $id, mixed $data ): void {
		if ( isset( $_POST['mu_attribute_group'] ) ) {
			$taxonomy   = wc_attribute_taxonomy_name( $data['attribute_name'] );
			$group_name = sanitize_text_field( wp_unslash( $_POST['mu_attribute_group'] ) );
			
			$groups_map = get_option( 'mu_attribute_groups_map', array() );
			if ( ! is_array( $groups_map ) ) {
				$groups_map = array();
			}
			
			$groups_map[ $taxonomy ] = $group_name;
			update_option( 'mu_attribute_groups_map', $groups_map );
			wp_cache_delete( 'mu_global_attr_map_cache' );

			if ( class_exists( 'Singo_Tools_Core' ) ) {
				Singo_Tools_Core::get_instance()->purge_cache_on_save( array(), array() );
			}
		}
	}

	/**
	 * Remove from map when attribute is deleted.
	 */
	public function delete_attribute_group( mixed $id, mixed $name, mixed $taxonomy ): void {
		$groups_map = get_option( 'mu_attribute_groups_map', array() );
		if ( isset( $groups_map[ $taxonomy ] ) ) {
			unset( $groups_map[ $taxonomy ] );
			update_option( 'mu_attribute_groups_map', $groups_map );
			wp_cache_delete( 'mu_global_attr_map_cache' );

			if ( class_exists( 'Singo_Tools_Core' ) ) {
				Singo_Tools_Core::get_instance()->purge_cache_on_save( array(), array() );
			}
		}
	}

	/**
	 * JS Fallback if WooCommerce hooks are missing due to template overrides.
	 */
	public function admin_footer_js_fallback(): void {
		if ( ! singo_tools_get_option( 'enable_product_features', true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'product_page_product_attributes' ) {
			return;
		}

		// Prepare HTML for Add New Attribute form
		ob_start();
		$this->add_attribute_group_field();
		$add_html = ob_get_clean();

		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Inject into Add Form if not exists
			if (!document.getElementById('mu_attribute_group')) {
				var addForm = document.querySelector('form[action="edit.php?post_type=product&page=product_attributes"]');
				if (addForm) {
					var submitBtn = addForm.querySelector('.submit');
					if (submitBtn) {
						var wrapper = document.createElement('div');
						wrapper.innerHTML = <?php echo wp_json_encode( $add_html ); ?>;
						submitBtn.parentNode.insertBefore(wrapper.firstElementChild, submitBtn);
					}
				}
			}
		});
		</script>
		<?php
	}
}
