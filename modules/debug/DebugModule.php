<?php
declare(strict_types=1);

namespace SingoTools\Modules\Debug;

use SingoTools\Core\AbstractModule;

/**
 * Singo Tools - System Debugger & Health Inspector Module (Self-Contained)
 *
 * Dedicated error logging, fatal error monitoring, database inspection,
 * and wp-config.php debugging tools for Singo Tools.
 *
 * @package SingoTools\Modules\Debug
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || exit;

class DebugModule extends AbstractModule {

	/**
	 * Unique module identifier (slug).
	 */
	public static function get_id(): string {
		return 'debug';
	}

	/**
	 * Human-readable module name.
	 */
	public static function get_name(): string {
		return 'عیب‌یابی، سلامت و پایش سیستم';
	}

	/**
	 * Dashicon for UI.
	 */
	public static function get_icon(): string {
		return 'dashicons-admin-tools';
	}

	/**
	 * Order / Priority.
	 */
	public static function get_order(): int {
		return 99;
	}

	/**
	 * Placement in Optionino.
	 *
	 * Set to 'none' because this module operates as a dedicated admin submenu
	 * rather than cluttering the main Optionino settings tabs.
	 */
	public static function get_placement(): string {
		return 'none';
	}

	/**
	 * Module description.
	 */
	public static function get_description(): string {
		return 'عیب‌یابی و پایش لحظه‌ای سلامت سرور، دیتابیس و ثبت خطاهای مهلک سیستم';
	}

	/**
	 * Whether module is active. Always enabled to allow log viewing and server diagnostics.
	 */
	public static function is_enabled(): bool {
		return true;
	}

	/**
	 * Context validity.
	 */
	public static function is_context_valid(): bool {
		return true;
	}

	/**
	 * Initialize module hooks and load internal classes.
	 */
	public function init(): void {
		parent::init();

		// Load self-contained debugger class
		$debugger_class_path = $this->get_path( 'classes/class-singo-tools-debugger.php' );
		if ( file_exists( $debugger_class_path ) ) {
			require_once $debugger_class_path;
		}

		// Initialize debugger engine singleton
		if ( class_exists( '\Singo_Tools_Debugger' ) ) {
			\Singo_Tools_Debugger::get_instance();
		}

		// Register dedicated admin submenu page
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_debug_submenu' ), 20 );
		}
	}

	/**
	 * Register Debugger Submenu under Singo Tools menu.
	 */
	public function register_debug_submenu(): void {
		add_submenu_page(
			'singo-tools',
			'عیب‌یابی و پایش سیستم',
			'عیب‌یابی و لاگ‌ها',
			'manage_woocommerce',
			'singo-tools-debug',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the Debugger view page.
	 */
	public function render_admin_page(): void {
		if ( ! singo_tools_current_user_can_manage() ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای مشاهده این بخش را ندارید.', 'singo-tools' ) );
		}

		if ( class_exists( 'OPTNNO' ) ) {
			$optnno_instance = new \OPTNNO();
			$optnno_instance->admin_scripts();
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'singo-admin-ui-toolkit',
			SINGO_TOOLS_URL . 'assets/css/singo-admin-ui.css',
			array( 'dashicons' ),
			SINGO_TOOLS_VERSION
		);
		wp_enqueue_script(
			'singo-admin-ui-toolkit',
			SINGO_TOOLS_URL . 'assets/js/singo-admin-ui.js',
			array( 'jquery' ),
			SINGO_TOOLS_VERSION,
			true
		);

		?>
		<div id="optionino" class="optionino-container" style="direction: rtl; margin-top: 20px; max-width: 1200px;">
			<form id="save-options-optionino" method="post" action="">
				<input type="hidden" name="dev_name" value="singo_tools_options">
				<input type="hidden" name="security" value="<?php echo wp_create_nonce( 'optionino_nonce' ); ?>">
				<?php
				$view_path = $this->get_path( 'views/admin-debug-view.php' );
				if ( file_exists( $view_path ) ) {
					require $view_path;
				}
				?>
				<div class="optionino-options-save-box" style="margin-top: 20px; display: flex; justify-content: flex-end; align-items: center; gap: 15px; padding-bottom: 20px;">
					<button type="submit" class="button submit-optionino opt-btn opt-btn--primary" style="background-color: #ad6141 !important; color: #ffffff !important; border: 1px solid #ad6141 !important; height: 42px !important; padding: 0 26px !important; border-radius: 6px !important; cursor: pointer !important; font-weight: 700 !important; font-size: 13.5px !important; box-shadow: 0 2px 8px rgba(173,97,65,0.25) !important;">
						ذخیره وضعیت سرویس عیب‌یابی
					</button>
					<span class="error-text" style="display:none; color: #dc2626; font-weight: bold;"></span>
					<span class="success-text" style="display:none; color: #16a34a; font-weight: bold;"></span>
				</div>
			</form>
		</div>
		<?php
	}
}
