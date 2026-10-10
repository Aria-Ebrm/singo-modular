<?php
declare(strict_types=1);

/**
 * Core Plugin Orchestrator for Singo Tools (Singo_Tools_Core)
 * PHP 8+ Standardized
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Tools_Core {

	/**
	 * Single instance of the class.
	 *
	 * @var Singo_Tools_Core|null
	 */
	private static ?Singo_Tools_Core $instance = null;

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
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required core files.
	 */
	private function includes(): void {
		require_once SINGO_TOOLS_PATH . 'includes/helpers.php';

		// Load Debugger strictly if enabled or in admin
		if ( file_exists( SINGO_TOOLS_PATH . 'includes/class-singo-tools-debugger.php' ) ) {
			require_once SINGO_TOOLS_PATH . 'includes/class-singo-tools-debugger.php';
			if ( is_admin() || singo_tools_get_option( 'enable_system_debug', true ) ) {
				Singo_Tools_Debugger::get_instance();
			}
		}

		// Load Modular Infrastructure
		require_once SINGO_TOOLS_PATH . 'includes/Core/AbstractModule.php';
		require_once SINGO_TOOLS_PATH . 'includes/Core/ManifestManager.php';
		require_once SINGO_TOOLS_PATH . 'includes/Core/ModuleManager.php';

		// Load Optionino Framework and Settings strictly in Admin / AJAX
		if ( is_admin() || wp_doing_ajax() ) {
			if ( ! class_exists( 'OPTNNO' ) && file_exists( SINGO_TOOLS_PATH . 'optionino/optionino-framework.php' ) ) {
				require_once SINGO_TOOLS_PATH . 'optionino/optionino-framework.php';
			}
			require_once SINGO_TOOLS_PATH . 'includes/class-singo-tools-optionino.php';
			Singo_Tools_Optionino::init();
		}
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'load_modules' ) );
		add_action( 'admin_notices', array( $this, 'check_plugin_conflicts' ) );
		add_action( 'update_option_singo_tools_options', array( $this, 'purge_cache_on_save' ), 10, 2 );
		add_action( 'update_option_sdm_options', array( $this, 'purge_cache_on_save' ), 10, 2 );
		add_filter( 'user_has_cap', array( $this, 'filter_user_capabilities' ), 10, 4 );
	}

	/**
	 * Load conditional modules via ModuleManager.
	 */
	public function load_modules(): void {
		\SingoTools\Core\ModuleManager::get_instance()->init_active_modules();
	}

	/**
	 * Legacy proxy for formatting variable product price display (از ...).
	 */
	public function format_variable_product_price( string $price, \WC_Product $product ): string {
		$module = \SingoTools\Core\ModuleManager::get_instance()->get_module( 'variable-price-format' );
		if ( $module instanceof \SingoTools\Modules\VariablePriceFormat\VariablePriceFormatModule ) {
			return $module->format_variable_product_price( $price, $product );
		}

		return $price;
	}

	/**
	 * Legacy proxy for rendering right-click and selection prevention script and style.
	 */
	public function render_disable_right_click(): void {
		$module = \SingoTools\Core\ModuleManager::get_instance()->get_module( 'disable-right-click' );
		if ( $module instanceof \SingoTools\Modules\DisableRightClick\DisableRightClickModule ) {
			$module->render_script_and_styles();
		}
	}

	/**
	 * Check for known Jalali plugin conflicts and show admin notice.
	 */
	public function check_plugin_conflicts(): void {
		$conflicts = array();

		if ( class_exists( 'WP_Parsidate' ) || defined( 'WPP_VERSION' ) ) {
			$conflicts[] = 'WP-Parsidate';
		}

		if ( function_exists( 'wpp_init' ) || class_exists( 'wp_jalali' ) ) {
			$conflicts[] = 'WP-Jalali';
		}

		if ( ! empty( $conflicts ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>[Singo Tools Warning]</strong> تداخل افزونه‌ای شناسایی شد! افزونه(های) <code>' . esc_html( implode( ', ', $conflicts ) ) . '</code> همزمان فعال هستند. برای جلوگیری از خطاهای محاسباتی، پیشنهاد می‌شود فقط از یک افزونه استفاده نمایید.</p>';
			echo '</div>';
		}
	}

	/**
	 * Purge popular caching plugins when settings are updated and manage currency toggle state.
	 */
	public function purge_cache_on_save( mixed $old_value, mixed $value ): void {
		// Handle Currency Converter Activation/Deactivation Logic
		$old_currency_state = isset( $old_value['enable_currency_converter'] ) ? filter_var( $old_value['enable_currency_converter'], FILTER_VALIDATE_BOOLEAN ) : false;
		$new_currency_state = isset( $value['enable_currency_converter'] ) ? filter_var( $value['enable_currency_converter'], FILTER_VALIDATE_BOOLEAN ) : false;

		if ( $old_currency_state !== $new_currency_state ) {
			if ( ! class_exists( 'Singo_Currency_Converter' ) && file_exists( SINGO_TOOLS_PATH . 'modules/currency-converter/class-singo-currency-converter.php' ) ) {
				require_once SINGO_TOOLS_PATH . 'modules/currency-converter/class-singo-currency-converter.php';
			}
			if ( class_exists( 'Singo_Currency_Converter' ) ) {
				if ( $new_currency_state ) {
					Singo_Currency_Converter::activate();
				} else {
					Singo_Currency_Converter::deactivate();
				}
			}
		}

		// 1. LiteSpeed Cache
		if ( class_exists( 'LiteSpeed\Purge' ) ) {
			try {
				LiteSpeed\Purge::purge_all();
			} catch ( Throwable $e ) {
				// Silent fail
			}
		}

		// 2. WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// 3. W3 Total Cache
		if ( function_exists( 'w3tc_pgcache_flush' ) ) {
			w3tc_pgcache_flush();
		}
		if ( function_exists( 'w3tc_dbcache_flush' ) ) {
			w3tc_dbcache_flush();
		}
		if ( function_exists( 'w3tc_objectcache_flush' ) ) {
			w3tc_objectcache_flush();
		}

		// 4. WP Super Cache
		if ( function_exists( 'wp_cache_clean_cache' ) ) {
			global $file_prefix;
			wp_cache_clean_cache( isset( $file_prefix ) ? $file_prefix : '' );
		}

		// 5. Autoptimize
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
		}

		// 6. Rebuild Singo Tools Modules Manifest
		\SingoTools\Core\ManifestManager::rebuild_manifest();
	}

	/**
	 * Filter user capabilities to grant shop managers access to Singo Tools admin pages and settings.
	 *
	 * @param array<string, bool> $allcaps All capabilities of the user.
	 * @param array<string>       $caps    Capabilities required by the current check.
	 * @param array<mixed>        $args    Capability arguments ($args[0] is requested cap).
	 * @param WP_User             $user    The user object being checked.
	 * @return array<string, bool>
	 */
	public function filter_user_capabilities( array $allcaps, array $caps, array $args, WP_User $user ): array {
		$requested_cap = $args[0] ?? '';

		// If user is Administrator (has manage_options), always grant manage_woocommerce for Singo Tools menus
		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps['manage_woocommerce'] = true;
		}

		// Check if user is shop manager or has manage_woocommerce capability
		$is_shop_manager = ! empty( $allcaps['manage_woocommerce'] ) || in_array( 'shop_manager', (array) $user->roles, true );

		if ( $is_shop_manager ) {
			$allcaps['manage_woocommerce'] = true;

			// Strictly grant manage_options within Singo Tools context (e.g. Optionino AJAX save)
			if ( 'manage_options' === $requested_cap && self::is_singo_tools_context() ) {
				$allcaps['manage_options'] = true;
			}
		}

		return $allcaps;
	}

	/**
	 * Determine if current request execution is within Singo Tools context.
	 *
	 * @return bool
	 */
	public static function is_singo_tools_context(): bool {
		// 1. Admin GET page request
		if ( isset( $_GET['page'] ) && is_string( $_GET['page'] ) ) {
			$page = sanitize_key( $_GET['page'] );
			if ( in_array( $page, array( 'singo-tools', 'singo-tools-debug' ), true ) ) {
				return true;
			}
		}

		// 2. AJAX requests
		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) $_REQUEST['action'] ) : '';

			// Optionino AJAX settings save for singo_tools_options
			if ( 'save_optionino_data' === $action ) {
				$dev_name = isset( $_POST['dev_name'] ) ? sanitize_key( (string) $_POST['dev_name'] ) : '';
				if ( 'singo_tools_options' === $dev_name ) {
					return true;
				}
			}

			// Singo Tools debug and health inspection AJAX endpoints
			if ( str_starts_with( $action, 'singo_' ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! class_exists( 'SDM_Core' ) ) {
	class_alias( 'Singo_Tools_Core', 'SDM_Core' );
}
