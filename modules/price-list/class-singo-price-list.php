<?php
declare(strict_types=1);

/**
 * Singo Tools - Price List PDF Exporter Module (Singo_Price_List)
 * PHP 8+ Standardized
 *
 * Dedicated Submenu powered by Optionino Framework with:
 * - Real-time A4 PDF Price List Generator
 * - Custom Cover Page / First Page (A4 Graphic/Image or Corporate Layout)
 * - Footer Contact Details (Phone & Address with Vector Icons)
 * - Customer Download Portal in WooCommerce My Account
 *
 * @package SingoTools\Modules\PriceList
 * @since 4.1.0
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Price_List {

	/**
	 * Option key for Price List dedicated Optionino settings.
	 */
	public const OPTION_KEY = 'singo_price_list_options';

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * In-memory request cache for products query results.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private static array $products_cache = array();

	/**
	 * In-memory request cache for brand detection results.
	 *
	 * @var array<int, array{name: string, logo_url: string}>
	 */
	private static array $brand_cache = array();

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
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		// 1. Setup Optionino Submenu and Settings
		if ( is_admin() || wp_doing_ajax() ) {
			$this->setup_optionino_submenu();
			add_action( 'admin_menu', array( $this, 'ensure_submenu_registered' ), 20 );
		}

		// 2. Action to view and print PDF template (Admins, Shop Managers, and Logged-in Customers)
		add_action( 'admin_post_singo_export_price_list_pdf', array( $this, 'render_pdf_template' ) );
		add_action( 'admin_post_nopriv_singo_export_price_list_pdf', array( $this, 'render_pdf_template' ) );

		// 3. AJAX endpoint for live preview table in Optionino tab
		add_action( 'wp_ajax_singo_get_price_list_preview', array( $this, 'ajax_get_price_list_preview' ) );

		// 4. AJAX endpoints to save custom font URL, primary color and category colors
		add_action( 'wp_ajax_singo_save_price_list_font_url', array( $this, 'ajax_save_font_url' ) );
		add_action( 'wp_ajax_singo_save_price_list_primary_color', array( $this, 'ajax_save_primary_color' ) );
		add_action( 'wp_ajax_singo_save_category_colors', array( $this, 'ajax_save_category_colors' ) );

		// 5. Invalidate cached categories on taxonomy modification
		add_action( 'create_product_cat', array( __CLASS__, 'purge_categories_cache' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'purge_categories_cache' ) );
		add_action( 'delete_product_cat', array( __CLASS__, 'purge_categories_cache' ) );

		// 6. Invalidate cached settings & flush rewrite rules on Optionino save
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'on_options_updated' ), 10, 2 );

		// 7. Allow font & vector icon uploads in WordPress media library (Admin only)
		if ( is_admin() ) {
			add_filter( 'upload_mimes', array( $this, 'allow_font_mime_types' ) );
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_font_filetype_check' ), 10, 5 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_media_scripts' ) );
		}

		// 8. WooCommerce My Account Integration (Immediate & Self-Healing)
		if ( did_action( 'init' ) ) {
			$this->register_account_endpoints();
		} else {
			add_action( 'init', array( $this, 'register_account_endpoints' ), 5 );
		}
		add_filter( 'query_vars', array( $this, 'add_account_query_vars' ), 0 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_account_query_vars' ), 0 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_items' ), 40 );
		add_filter( 'woocommerce_endpoint_price-list_title', array( $this, 'get_endpoint_title' ), 10, 2 );
		add_action( 'woocommerce_account_price-list_endpoint', array( $this, 'render_my_account_content' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_customer_pdf' ), 0 );
		add_filter( 'woocommerce_login_redirect', array( $this, 'filter_login_redirect' ), 999, 2 );

		// 9. Customer Download History AJAX Handlers and Scheduled Purge Cron
		add_action( 'wp_ajax_singo_get_customer_download_history', array( $this, 'ajax_get_customer_download_history' ) );
		add_action( 'wp_ajax_singo_delete_customer_history_item', array( $this, 'ajax_delete_customer_history_item' ) );
		add_action( 'singo_daily_price_list_history_cleanup', array( __CLASS__, 'cron_purge_all_expired_histories' ) );
		if ( ! wp_next_scheduled( 'singo_daily_price_list_history_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'singo_daily_price_list_history_cleanup' );
		}

		// 10. Admin Download Reports and User Restriction AJAX Handlers
		add_action( 'wp_ajax_singo_admin_toggle_user_restriction', array( $this, 'ajax_admin_toggle_user_restriction' ) );
		add_action( 'wp_ajax_singo_admin_delete_download_log', array( $this, 'ajax_admin_delete_download_log' ) );
		add_action( 'wp_ajax_singo_admin_clear_all_download_logs', array( $this, 'ajax_admin_clear_all_download_logs' ) );
		add_action( 'wp_ajax_singo_admin_get_user_download_dossier', array( $this, 'ajax_admin_get_user_download_dossier' ) );

		// 11. Frontend Off-Canvas Price List Drawer
		add_action( 'wp_footer', array( $this, 'render_frontend_offcanvas' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_offcanvas_assets' ) );
	}

	/**
	 * Setup Optionino Submenu and Multi-Tab Settings Configuration.
	 */
	public function setup_optionino_submenu(): void {
		if ( ! class_exists( 'OPTNNO' ) ) {
			return;
		}

		// Configure dedicated Submenu under Singo Tools
		OPTNNO::set_config( self::OPTION_KEY, array(
			'dev_title'       => 'تنظیمات و خروجی لیست قیمت',
			'menu_title'      => 'خروجی لیست قیمت',
			'page_title'      => 'تنظیمات و خروجی لیست قیمت PDF',
			'menu_type'       => 'submenu',
			'page_parent'     => 'singo-tools',
			'page_slug'       => 'singo-price-list',
			'page_capability' => function_exists( 'singo_tools_get_admin_capability' ) ? singo_tools_get_admin_capability() : 'manage_woocommerce',
			'logo_url'        => SINGO_TOOLS_URL . 'assets/SingoLogo.svg',
			'dev_version'     => SINGO_TOOLS_VERSION,
		) );

		// Tab 1: میز کار و صدور فوری PDF
		$view = SINGO_TOOLS_PATH . 'modules/price-list/views/admin-price-list-view.php';
		if ( file_exists( $view ) ) {
			OPTNNO::set_tab( self::OPTION_KEY, array(
				'id'    => 'price_list_export_tab',
				'title' => 'میز کار و صدور فوری PDF',
				'desc'  => 'فیلتر دسته‌بندی‌ها، اعمال تخفیف، تنظیم عنوان سند و تولید فوری فایل چاپی و خروجی PDF.',
				'icon'  => 'dashicons-media-document',
				'file'  => $view,
			) );
		}

		// Tab 2: قالب، کاور و ظاهر کلی PDF
		OPTNNO::set_tab( self::OPTION_KEY, array(
			'id'     => 'price_list_contact_tab',
			'title'  => 'قالب، کاور و ظاهر کلی PDF',
			'desc'   => 'تنظیمات لوگوی سربرگ، رنگ سازمانی، فونت، صفحه کاور تمام‌صفحه A4، ستون برند و اطلاعات تماس فوتر.',
			'icon'   => 'dashicons-admin-appearance',
			'fields' => array(
				// بخش ۱: هویت بصری و سربرگ
				array(
					'id'      => 'pdf_logo_url',
					'type'    => 'image',
					'title'   => 'لوگوی اختصاصی سربرگ',
					'desc'    => 'لوگوی شرکت یا فروشگاه جهت چاپ در سربرگ صفحات (پیش‌فرض: لوگوی سایت در سفارشی‌ساز).',
					'default' => '',
				),
				array(
					'id'      => 'pdf_primary_color',
					'type'    => 'color',
					'title'   => 'رنگ سازمانی و شاخص سند',
					'desc'    => 'رنگ هدر، خطوط و کادرهای جدول در فایل خروجی PDF.',
					'default' => '#ad6141',
				),
				array(
					'id'      => 'pdf_font_url',
					'type'    => 'text',
					'title'   => 'آدرس فایل فونت سفارشی (اختیاری)',
					'desc'    => 'نشانی URL فایل فونت وب (WOFF2 یا TTF) جهت استفاده در سند.',
					'default' => '',
				),
				array(
					'id'      => 'pdf_background_image',
					'type'    => 'image',
					'title'   => 'تصویر پس‌زمینه صفحات سند (به جز کاور)',
					'desc'    => 'تصویر پس‌زمینه (واترمارک) برای تمامی صفحات لیست قیمت، به جز صفحه اول کاور. در صورت خالی بودن، از تصویر پیش‌فرض لوکال استفاده می‌شود.',
					'default' => SINGO_TOOLS_URL . 'assets/images/BGP.webp',
				),

				// بخش ۲: صفحه کاور سراسری (صفحه اول)
				array(
					'id'      => 'enable_pdf_cover',
					'type'    => 'switcher',
					'title'   => 'فعال‌سازی صفحه کاور اختصاصی',
					'desc'    => 'با فعال‌سازی این گزینه، یک صفحه اول اختصاصی (کاور A4) قبل از جدول قیمت‌ها اضافه می‌شود.',
					'default' => false,
				),
				array(
					'id'      => 'pdf_cover_image',
					'type'    => 'image',
					'title'   => 'تصویر کامل کاور سراسری (A4 عمودی)',
					'desc'    => 'تصویر با کیفیت در قطع عمودی A4 (ترجیحاً فرمت PNG یا JPG با ابعاد استاندارد). صفحه اول کاتالوگ به صورت تمام‌صفحه با این تصویر پوشانده می‌شود.',
					'require' => array( array( 'enable_pdf_cover', '==', true ) ),
				),

				// بخش ۳: ستون برند محصولات
				array(
					'id'      => 'pdf_show_brand',
					'type'    => 'switcher',
					'title'   => 'نمایش ستون برند و لوگوی برند',
					'desc'    => 'افزودن ستون اختصاصی برند محصول و لوگوی برند بین ستون مشخصات و قیمت در سند PDF و پیش‌نمایش.',
					'default' => true,
				),
				array(
					'id'      => 'pdf_brand_taxonomy',
					'type'    => 'text',
					'title'   => 'نامک طبقه‌بندی برند (اختیاری)',
					'desc'    => 'به صورت پیش‌فرض سیستم هوشمند برند را خودکار از افزونه‌های معتبر (مانند Perfect Brands, WooCommerce Brands, YITH، قالب وودمارت و ویژگی‌های سراسری) تشخیص می‌دهد. در صورت تمایل می‌توانید نام تاکسونومی را به صورت دستی وارد کنید (مثلاً pwb-brand یا product_brand یا pa_brand).',
					'default' => '',
					'require' => array( array( 'pdf_show_brand', '==', true ) ),
				),

				// بخش ۴: اطلاعات تماس و پاورقی (فوتر)
				array(
					'id'      => 'pdf_footer_phone',
					'type'    => 'text',
					'title'   => 'شماره تماس فروشگاه / شرکت',
					'desc'    => 'شماره تماس ثابت یا همراه جهت چاپ در پایین تمامی صفحات PDF در کنار آیکون تلفن.',
					'default' => '',
				),
				array(
					'id'      => 'pdf_footer_address',
					'type'    => 'text',
					'title'   => 'آدرس فیزیکی فروشگاه / دفتر',
					'desc'    => 'نشانی پستی جهت چاپ در پایین تمامی صفحات PDF در کنار آیکون لوکیشن.',
					'default' => '',
				),
				array(
					'id'      => 'pdf_footer_notes',
					'type'    => 'textarea',
					'title'   => 'متن سلب مسئولیت / توضیحات فوتر',
					'desc'    => 'متن توضیحات تکمیلی یا شرایط اعتبار قیمت‌ها در پاورقی صفحات.',
					'default' => '',
				),
			),
		) );

		// Tab 3: پترن، آیکن و کاور دسته‌بندی‌ها
		$cat_colors_view = SINGO_TOOLS_PATH . 'modules/price-list/views/admin-category-colors-view.php';
		if ( file_exists( $cat_colors_view ) ) {
			OPTNNO::set_tab( self::OPTION_KEY, array(
				'id'    => 'price_list_category_colors_tab',
				'title' => 'پترن، آیکن و کاور دسته‌بندی‌ها',
				'desc'  => 'تنظیم رنگ پترن، آیکن روی پترن و کاور صفحه اول برای دسته‌بندی‌های مادر و فرزند با قابلیت ارث‌بری.',
				'icon'  => 'dashicons-art',
				'file'  => $cat_colors_view,
			) );
		}

		// Tab 4: حساب کاربری و تاریخچه مشتری
		OPTNNO::set_tab( self::OPTION_KEY, array(
			'id'     => 'price_list_my_account_tab',
			'title'  => 'حساب کاربری و تاریخچه مشتری',
			'desc'   => 'فعال‌سازی تب و امکان دانلود مستقیم لیست قیمت برای مشتریان در پیشخوان حساب کاربری (My Account) و تنظیم مدت نگهداری تاریخچه.',
			'icon'   => 'dashicons-admin-users',
			'fields' => array(
				array(
					'id'      => 'enable_my_account_price_list',
					'type'    => 'switcher',
					'title'   => 'نمایش تب لیست قیمت در حساب کاربری',
					'desc'    => 'با فعال‌سازی این گزینه، تب «لیست قیمت» به منوی حساب کاربری مشتریان در ووکامرس اضافه می‌شود.',
					'default' => false,
				),
				array(
					'id'      => 'my_account_price_list_title',
					'type'    => 'text',
					'title'   => 'عنوان تب در حساب کاربری',
					'desc'    => 'نام تب در منوی حساب کاربری (مثلاً: لیست قیمت محصولات).',
					'default' => 'لیست قیمت محصولات',
					'require' => array( array( 'enable_my_account_price_list', '==', true ) ),
				),
				array(
					'id'      => 'my_account_price_list_desc',
					'type'    => 'textarea',
					'title'   => 'توضیحات راهنما در برگه حساب کاربری',
					'desc'    => 'متن راهنما که در بالای دکمه دانلود به مشتری نمایش داده می‌شود.',
					'default' => 'از این بخش می‌توانید جدیدترین لیست قیمت رسمی و کاتالوگ محصولات را با یک کلیک به صورت فایل PDF دریافت و دانلود نمایید.',
					'require' => array( array( 'enable_my_account_price_list', '==', true ) ),
				),
				array(
					'id'      => 'my_account_primary_color',
					'type'    => 'color',
					'title'   => 'رنگ شاخص دکمه و آیکون حساب کاربری',
					'desc'    => 'رنگ دکمه دانلود، آیکون بالای کارت و سایه‌ها در برگه حساب کاربری مشتریان (پیش‌فرض: رنگ سازمانی افزونه).',
					'default' => '#ad6141',
					'require' => array( array( 'enable_my_account_price_list', '==', true ) ),
				),
				array(
					'id'      => 'enable_my_account_history',
					'type'    => 'switcher',
					'title'   => 'فعال‌سازی تاریخچه دانلودهای کاربر',
					'desc'    => 'ذخیره خودکار تاریخچه لیست‌های قیمت دانلودشده توسط هر مشتری در حساب کاربری با حفظ قیمت‌های همان لحظه، جهت امکان دانلود مجدد.',
					'default' => true,
					'require' => array( array( 'enable_my_account_price_list', '==', true ) ),
				),
				array(
					'id'      => 'my_account_history_retention_days',
					'type'    => 'number',
					'title'   => 'مدت زمان نگهداری تاریخچه (بر حسب روز)',
					'desc'    => 'تعداد روزهایی که تاریخچه دانلود و قیمت‌های ذخیره‌شده برای کاربر باقی می‌ماند. پس از این مدت، رکوردها به طور خودکار از دیتابیس پاک می‌شوند (پیش‌فرض: ۷ روز).',
					'default' => 7,
					'require' => array(
						array( 'enable_my_account_price_list', '==', true ),
						array( 'enable_my_account_history', '==', true ),
					),
				),
				array(
					'id'      => 'my_account_history_max_items',
					'type'    => 'number',
					'title'   => 'حداکثر تعداد رکوردهای تاریخچه هر کاربر',
					'desc'    => 'سقف مجاز تعداد فایل‌های ذخیره‌شده در تاریخچه هر کاربر جهت حفظ بهینگی دیتابیس (پیش‌فرض: ۱۰ رکورد).',
					'default' => 10,
					'require' => array(
						array( 'enable_my_account_price_list', '==', true ),
						array( 'enable_my_account_history', '==', true ),
					),
				),
				array(
					'id'      => 'enable_offcanvas_price_list',
					'type'    => 'switcher',
					'title'   => 'فعال‌سازی سایدبار کشویی لیست قیمت (Off-Canvas)',
					'desc'    => 'با فعال‌بودن این گزینه، کلیک روی دکمه «لیست قیمت» در منوی دسکتاپ و سراسر سایت، پنجره کشویی مدرن دریافت و چاپ کاتالوگ را باز می‌کند.',
					'default' => true,
				),
				array(
					'id'      => 'offcanvas_title',
					'type'    => 'text',
					'title'   => 'عنوان سایدبار کشویی لیست قیمت',
					'desc'    => 'عنوانی که در بالای پنجره کشویی نمایش داده می‌شود.',
					'default' => 'دریافت و چاپ لیست قیمت',
					'require' => array( array( 'enable_offcanvas_price_list', '==', true ) ),
				),
				array(
					'id'      => 'offcanvas_desc',
					'type'    => 'textarea',
					'title'   => 'توضیحات راهنما در سایدبار کشویی',
					'desc'    => 'متن راهنما که در پنجره کشویی به کاربر نمایش داده می‌شود.',
					'default' => 'جهت دریافت جدیدترین کاتالوگ و لیست قیمت رسمی، دسته‌بندی مورد نظر خود را انتخاب کرده و دکمه چاپ را بزنید.',
					'require' => array( array( 'enable_offcanvas_price_list', '==', true ) ),
				),
				array(
					'id'      => 'offcanvas_trigger_selector',
					'type'    => 'text',
					'title'   => 'سلکتور سفارشی دکمه منو (اختیاری)',
					'desc'    => 'کلاس یا سلکتور سفارشی دکمه‌های بازکننده پنجره کشویی (پیش‌فرض: به صورت خودکار به دکمه منوی لیست قیمت، پیوندهای #price-list و کلاس singo-price-list-trigger متصل است).',
					'default' => '.menu-item-4883 a, .singo-price-list-trigger, a[href="#price-list"]',
					'require' => array( array( 'enable_offcanvas_price_list', '==', true ) ),
				),
			),
		) );

		// Tab 5: گزارش دانلودها و محدودسازی کاربران
		$reports_view = SINGO_TOOLS_PATH . 'modules/price-list/views/admin-download-reports-view.php';
		if ( file_exists( $reports_view ) ) {
			OPTNNO::set_tab( self::OPTION_KEY, array(
				'id'    => 'price_list_download_reports_tab',
				'title' => 'گزارش دانلودها و کاربران',
				'desc'  => 'گزارشات آماری دانلودهای انجام‌شده، شماره تماس کاربران و مدیریت دسترسی آن‌ها.',
				'icon'  => 'dashicons-chart-bar',
				'file'  => $reports_view,
			) );
		}
	}

	/**
	 * Ensure WordPress Media Library scripts are enqueued on the Price List settings page.
	 */
	public function enqueue_admin_media_scripts(): void {
		if ( isset( $_GET['page'] ) && 'singo-price-list' === sanitize_key( (string) $_GET['page'] ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Retrieve a setting from singo_price_list_options with Static RAM Cache.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @param bool   $reset_cache Flush memory cache.
	 * @return mixed
	 */
	public static function get_setting( string $key, mixed $default = '', bool $reset_cache = false ): mixed {
		static $options = null;

		if ( null === $options || $reset_cache ) {
			$options = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $options ) ) {
				$options = array();
			}
		}

		if ( empty( $key ) && $reset_cache ) {
			return $options;
		}

		if ( ! array_key_exists( $key, $options ) || '' === $options[ $key ] || null === $options[ $key ] ) {
			// Fallbacks for legacy standalone options
			if ( 'pdf_primary_color' === $key ) {
				$legacy = (string) get_option( 'singo_price_list_primary_color', '' );
				if ( ! empty( $legacy ) ) {
					return $legacy;
				}
			} elseif ( 'pdf_font_url' === $key ) {
				$legacy = (string) get_option( 'singo_price_list_font_url', '' );
				if ( ! empty( $legacy ) ) {
					return $legacy;
				}
			}
			return $default;
		}

		$val = $options[ $key ];
		if ( is_string( $val ) ) {
			$lower = strtolower( trim( $val ) );
			if ( 'true' === $lower || '1' === $lower || 'on' === $lower ) {
				return true;
			}
			if ( 'false' === $lower || '0' === $lower || 'off' === $lower ) {
				return false;
			}
		}

		return $val;
	}

	/**
	 * Purge memory cache of settings.
	 */
	public static function purge_settings_cache(): void {
		self::get_setting( '', '', true );
	}

	/**
	 * Invalidate settings RAM cache and flush rewrite rules on settings update.
	 *
	 * @param mixed $old_value
	 * @param mixed $value
	 */
	public function on_options_updated( mixed $old_value, mixed $value ): void {
		self::purge_settings_cache();
		flush_rewrite_rules( false );
	}

	/**
	 * Check if WooCommerce My Account tab is enabled.
	 *
	 * @return bool
	 */
	public static function is_my_account_enabled(): bool {
		return (bool) self::get_setting( 'enable_my_account_price_list', false );
	}

	/**
	 * Check if Customer Download History in My Account is enabled.
	 *
	 * @return bool
	 */
	public static function is_customer_history_enabled(): bool {
		return self::is_my_account_enabled() && (bool) self::get_setting( 'enable_my_account_history', true );
	}

	/**
	 * Check if the Off-Canvas Price List Drawer is enabled.
	 *
	 * @return bool
	 */
	public static function is_offcanvas_enabled(): bool {
		return (bool) self::get_setting( 'enable_offcanvas_price_list', true );
	}

	/**
	 * Filter post-login redirect to ensure Price List requests land on the customer price list tab.
	 *
	 * @param string $redirect Default redirect URL.
	 * @param mixed  $user     User object.
	 * @return string
	 */
	public function filter_login_redirect( string $redirect, mixed $user ): string {
		$target = '';
		if ( isset( $_REQUEST['redirect'] ) && ! empty( $_REQUEST['redirect'] ) ) {
			$target = esc_url_raw( wp_unslash( (string) $_REQUEST['redirect'] ) );
		} elseif ( isset( $_REQUEST['redirect_to'] ) && ! empty( $_REQUEST['redirect_to'] ) ) {
			$target = esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) );
		}

		if ( ! empty( $target ) && ( str_contains( $target, 'singo_auto_download' ) || str_contains( $target, 'price-list' ) ) ) {
			return $target;
		}

		return $redirect;
	}

	/**
	 * Get history retention period in days.
	 *
	 * @return int
	 */
	public static function get_history_retention_days(): int {
		$days = (int) self::get_setting( 'my_account_history_retention_days', 7 );
		return $days > 0 ? $days : 7;
	}

	/**
	 * Get history maximum saved items per user.
	 *
	 * @return int
	 */
	public static function get_history_max_items(): int {
		$max = (int) self::get_setting( 'my_account_history_max_items', 10 );
		return $max > 0 ? $max : 10;
	}

	/**
	 * Register WooCommerce My Account rewrite endpoint with self-healing rewrite rules.
	 */
	public function register_account_endpoints(): void {
		add_rewrite_endpoint( 'price-list', EP_ROOT | EP_PAGES );

		global $wp;
		if ( $wp instanceof \WP ) {
			$wp->add_query_var( 'price-list' );
		}

		// Self-healing: if My Account tab is enabled, ensure rewrite rules contain price-list
		if ( self::is_my_account_enabled() ) {
			$rules = get_option( 'rewrite_rules' );
			if ( is_array( $rules ) && ! self::has_price_list_rewrite_rule( $rules ) ) {
				flush_rewrite_rules( false );
			}
		}
	}

	/**
	 * Check if rewrite rules array contains the price-list endpoint.
	 *
	 * @param array<string, string> $rules
	 * @return bool
	 */
	private static function has_price_list_rewrite_rule( array $rules ): bool {
		foreach ( array_keys( $rules ) as $rule ) {
			if ( str_contains( (string) $rule, 'price-list' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add price-list to WooCommerce and WordPress query vars.
	 *
	 * @param array<mixed> $vars
	 * @return array<mixed>
	 */
	public function add_account_query_vars( array $vars ): array {
		$vars['price-list'] = 'price-list';
		$vars[] = 'price-list';
		return $vars;
	}

	/**
	 * Filter page title for price-list endpoint in WooCommerce.
	 *
	 * @param string $title
	 * @param string $endpoint
	 * @return string
	 */
	public function get_endpoint_title( string $title, string $endpoint ): string {
		if ( 'price-list' === $endpoint ) {
			$custom_title = (string) self::get_setting( 'my_account_price_list_title', 'لیست قیمت محصولات' );
			return ! empty( $custom_title ) ? $custom_title : 'لیست قیمت محصولات';
		}
		return $title;
	}

	/**
	 * Insert 'price-list' tab into WooCommerce My Account menu.
	 *
	 * @param array<string, string> $items Menu items array.
	 * @return array<string, string>
	 */
	public function add_account_menu_items( array $items ): array {
		if ( ! self::is_my_account_enabled() ) {
			return $items;
		}

		$title = (string) self::get_setting( 'my_account_price_list_title', 'لیست قیمت محصولات' );
		if ( empty( $title ) ) {
			$title = 'لیست قیمت محصولات';
		}

		$new_items = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new_items['price-list'] = $title;
			}
			$new_items[ $key ] = $label;
		}

		if ( ! isset( $new_items['price-list'] ) ) {
			$new_items['price-list'] = $title;
		}

		return $new_items;
	}

	/**
	 * Catch frontend customer PDF download request before template is rendered.
	 */
	public function maybe_render_customer_pdf(): void {
		if ( isset( $_GET['singo_download_pdf'] ) && '1' === (string) $_GET['singo_download_pdf'] ) {
			$this->render_pdf_template();
			exit;
		}
	}

	/**
	 * Build a hierarchical tree of product categories for the customer dashboard selector.
	 *
	 * @return array<int, array{id: int, name: string, count: int, parent_id: int, level: int, path: string, color: string, icon: string}>
	 */
	public static function get_hierarchical_category_tree(): array {
		$raw_cats = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );
		if ( empty( $raw_cats ) || is_wp_error( $raw_cats ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $raw_cats as $cat ) {
			$by_parent[ (int) $cat->parent ][] = $cat;
		}

		$flattened = array();
		$build_branch = static function( int $parent_id, int $level, string $parent_path ) use ( &$build_branch, &$flattened, &$by_parent ): void {
			if ( empty( $by_parent[ $parent_id ] ) ) {
				return;
			}
			foreach ( $by_parent[ $parent_id ] as $child ) {
				$child_id = (int) $child->term_id;
				$path = '' === $parent_path ? (string) $child->name : ( $parent_path . ' ← ' . $child->name );
				$flattened[] = array(
					'id'        => $child_id,
					'name'      => (string) $child->name,
					'count'     => (int) $child->count,
					'parent_id' => $parent_id,
					'level'     => $level,
					'path'      => $path,
					'color'     => self::get_resolved_category_color( $child_id ),
					'icon'      => self::get_resolved_category_pattern_icon( $child_id ),
				);
				$build_branch( $child_id, $level + 1, $path );
			}
		};

		$build_branch( 0, 0, '' );

		// Fallback if some categories have missing parents in hide_empty
		if ( empty( $flattened ) ) {
			foreach ( $raw_cats as $cat ) {
				$cat_id = (int) $cat->term_id;
				$flattened[] = array(
					'id'        => $cat_id,
					'name'      => (string) $cat->name,
					'count'     => (int) $cat->count,
					'parent_id' => (int) $cat->parent,
					'level'     => 0,
					'path'      => (string) $cat->name,
					'color'     => self::get_resolved_category_color( $cat_id ),
					'icon'      => self::get_resolved_category_pattern_icon( $cat_id ),
				);
			}
		}

		return $flattened;
	}

	/**
	 * Retrieve customer download history from usermeta with automatic expiration purging.
	 *
	 * @param int $user_id WordPress User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_user_download_history( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		$raw = get_user_meta( $user_id, '_singo_price_list_history', true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return array();
		}

		$now         = time();
		$cleaned     = array();
		$has_expired = false;

		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || empty( $item['expires_at'] ) ) {
				$has_expired = true;
				continue;
			}
			if ( (int) $item['expires_at'] > $now ) {
				$cleaned[] = $item;
			} else {
				$has_expired = true;
			}
		}

		// Automatic Zero-Bloat database garbage collection
		if ( $has_expired ) {
			if ( ! empty( $cleaned ) ) {
				update_user_meta( $user_id, '_singo_price_list_history', $cleaned );
			} else {
				delete_user_meta( $user_id, '_singo_price_list_history' );
			}
		}

		return $cleaned;
	}

	/**
	 * Record a frozen price snapshot of a downloaded price list for future re-download.
	 *
	 * @param int                          $user_id       User ID.
	 * @param int                          $category_id   Category ID.
	 * @param string                       $category_name Category Name.
	 * @param string                       $doc_title     Document title.
	 * @param string                       $shamsi_date   Formatted Shamsi date.
	 * @param array<int, array<string, mixed>> $products_data Products data array.
	 * @return string History record ID.
	 */
	public static function record_user_download_snapshot( int $user_id, int $category_id, string $category_name, string $doc_title, string $shamsi_date, array $products_data ): string {
		if ( $user_id <= 0 || ! self::is_customer_history_enabled() || empty( $products_data ) ) {
			return '';
		}

		$history = self::get_user_download_history( $user_id );
		$now     = time();
		$days    = self::get_history_retention_days();
		$max     = self::get_history_max_items();

		// Compact products snapshot: retain essential display attributes only
		$compact_products = array_map( static function( array $prod ): array {
			return array(
				'id'         => (int) ( $prod['id'] ?? 0 ),
				'title'      => (string) ( $prod['title'] ?? '' ),
				'short_desc' => (string) ( $prod['short_desc'] ?? '' ),
				'sku'        => (string) ( $prod['sku'] ?? '' ),
				'thumb_url'  => (string) ( $prod['thumb_url'] ?? '' ),
				'brand_name' => (string) ( $prod['brand_name'] ?? '' ),
				'brand_logo' => (string) ( $prod['brand_logo'] ?? '' ),
				'price_html' => (string) ( $prod['price_html'] ?? '' ),
			);
		}, $products_data );

		$json          = wp_json_encode( $compact_products );
		$snapshot_data = '';
		if ( false !== $json ) {
			if ( function_exists( 'gzcompress' ) ) {
				$compressed = gzcompress( $json, 6 );
				if ( false !== $compressed ) {
					$snapshot_data = 'gz:' . base64_encode( $compressed );
				}
			}
			if ( empty( $snapshot_data ) ) {
				$snapshot_data = 'raw:' . base64_encode( $json );
			}
		}

		if ( empty( $snapshot_data ) ) {
			return '';
		}

		// Avoid duplicate if same user generated for same category in the last 60 seconds
		if ( ! empty( $history[0] ) && (int) ( $history[0]['category_id'] ?? -1 ) === $category_id && ( $now - (int) $history[0]['created_at'] ) < 60 ) {
			$history[0]['created_at']     = $now;
			$history[0]['expires_at']     = $now + ( $days * DAY_IN_SECONDS );
			$history[0]['shamsi_date']    = $shamsi_date;
			$history[0]['doc_title']      = $doc_title;
			$history[0]['products_count'] = count( $products_data );
			$history[0]['snapshot_data']  = $snapshot_data;
			update_user_meta( $user_id, '_singo_price_list_history', $history );

			self::record_admin_download_log( $user_id, $category_id, $category_name, $doc_title, $shamsi_date, $products_data, $snapshot_data, (string) $history[0]['id'] );

			return (string) $history[0]['id'];
		}

		$history_id = 'spl_' . substr( md5( uniqid( (string) $user_id, true ) ), 0, 12 );
		$new_entry  = array(
			'id'             => $history_id,
			'created_at'     => $now,
			'expires_at'     => $now + ( $days * DAY_IN_SECONDS ),
			'shamsi_date'    => $shamsi_date,
			'category_id'    => $category_id,
			'category_name'  => $category_name,
			'category_color' => self::get_resolved_category_color( $category_id ),
			'category_icon'  => self::get_resolved_category_pattern_icon( $category_id ),
			'doc_title'      => $doc_title,
			'products_count' => count( $products_data ),
			'snapshot_data'  => $snapshot_data,
		);

		array_unshift( $history, $new_entry );
		if ( count( $history ) > $max ) {
			$history = array_slice( $history, 0, $max );
		}

		update_user_meta( $user_id, '_singo_price_list_history', $history );

		// Record in permanent administrator download audit log
		self::record_admin_download_log( $user_id, $category_id, $category_name, $doc_title, $shamsi_date, $products_data, $snapshot_data, $history_id );

		return $history_id;
	}

	/**
	 * Retrieve a specific snapshot from customer download history by ID.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $history_id History item ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_user_history_snapshot( int $user_id, string $history_id ): ?array {
		if ( $user_id <= 0 || empty( $history_id ) ) {
			return null;
		}

		$history = self::get_user_download_history( $user_id );
		foreach ( $history as $entry ) {
			if ( isset( $entry['id'] ) && $entry['id'] === $history_id ) {
				if ( (int) $entry['expires_at'] <= time() ) {
					return null;
				}

				$raw_snapshot = (string) ( $entry['snapshot_data'] ?? '' );
				$products     = array();

				if ( str_starts_with( $raw_snapshot, 'gz:' ) ) {
					$b64        = substr( $raw_snapshot, 3 );
					$compressed = base64_decode( $b64, true );
					if ( false !== $compressed && function_exists( 'gzuncompress' ) ) {
						$json = gzuncompress( $compressed );
						if ( false !== $json ) {
							$products = json_decode( $json, true ) ?: array();
						}
					}
				} elseif ( str_starts_with( $raw_snapshot, 'raw:' ) ) {
					$b64  = substr( $raw_snapshot, 4 );
					$json = base64_decode( $b64, true );
					if ( false !== $json ) {
						$products = json_decode( $json, true ) ?: array();
					}
				}

				if ( ! empty( $products ) && is_array( $products ) ) {
					$entry['products_data'] = $products;
					return $entry;
				}
			}
		}

		return null;
	}

	/**
	 * Delete an individual record from customer download history.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $history_id History ID.
	 * @return bool True if deleted.
	 */
	public static function delete_user_history_entry( int $user_id, string $history_id ): bool {
		if ( $user_id <= 0 || empty( $history_id ) ) {
			return false;
		}
		$history     = self::get_user_download_history( $user_id );
		$new_history = array();
		$found       = false;
		foreach ( $history as $item ) {
			if ( isset( $item['id'] ) && $item['id'] === $history_id ) {
				$found = true;
				continue;
			}
			$new_history[] = $item;
		}
		if ( $found ) {
			if ( ! empty( $new_history ) ) {
				update_user_meta( $user_id, '_singo_price_list_history', $new_history );
			} else {
				delete_user_meta( $user_id, '_singo_price_list_history' );
			}
			return true;
		}
		return false;
	}

	/**
	 * Scheduled Cron Handler: Purge all expired history entries across all users in wp_usermeta.
	 */
	public static function cron_purge_all_expired_histories(): void {
		global $wpdb;
		$meta_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s LIMIT 300",
				'_singo_price_list_history'
			)
		);
		if ( empty( $meta_rows ) || ! is_array( $meta_rows ) ) {
			return;
		}

		$now = time();
		foreach ( $meta_rows as $row ) {
			$user_id = (int) $row->user_id;
			$history = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $history ) ) {
				delete_user_meta( $user_id, '_singo_price_list_history' );
				continue;
			}

			$cleaned     = array();
			$has_expired = false;
			foreach ( $history as $item ) {
				if ( is_array( $item ) && ! empty( $item['expires_at'] ) && (int) $item['expires_at'] > $now ) {
					$cleaned[] = $item;
				} else {
					$has_expired = true;
				}
			}

			if ( $has_expired ) {
				if ( ! empty( $cleaned ) ) {
					update_user_meta( $user_id, '_singo_price_list_history', $cleaned );
				} else {
					delete_user_meta( $user_id, '_singo_price_list_history' );
				}
			}
		}
	}

	/**
	 * Retrieve and normalize user phone number from mobile meta (mobile_otino) or WooCommerce meta.
	 *
	 * @param int $user_id WordPress User ID.
	 * @return string Formatted mobile number e.g. 09123456789.
	 */
	public static function get_user_phone_number( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		// Priority 1: User mobile meta
		$phone = trim( (string) get_user_meta( $user_id, 'mobile_otino', true ) );

		// Priority 2: WooCommerce billing & shipping phone
		if ( empty( $phone ) ) {
			$phone = trim( (string) get_user_meta( $user_id, 'billing_phone', true ) );
		}
		if ( empty( $phone ) ) {
			$phone = trim( (string) get_user_meta( $user_id, 'shipping_phone', true ) );
		}

		// Priority 3: Common SMS plugins meta keys (Digits, etc.)
		if ( empty( $phone ) ) {
			$phone = trim( (string) get_user_meta( $user_id, 'digits_phone', true ) );
		}
		if ( empty( $phone ) ) {
			$phone = trim( (string) get_user_meta( $user_id, 'phone_number', true ) );
		}
		if ( empty( $phone ) ) {
			$phone = trim( (string) get_user_meta( $user_id, 'mobile', true ) );
		}

		// Priority 4: Check if user_login itself is a mobile number
		if ( empty( $phone ) ) {
			$u = get_userdata( $user_id );
			if ( $u && preg_match( '/^(\+?98|0)?9\d{9}$/', $u->user_login ) ) {
				$phone = $u->user_login;
			}
		}

		if ( empty( $phone ) ) {
			return '';
		}

		// Normalize to standard 11-digit Iranian mobile: 09XXXXXXXXX
		$clean = preg_replace( '/[^\d]/', '', $phone );
		if ( str_starts_with( $clean, '0098' ) ) {
			$clean = substr( $clean, 4 );
		} elseif ( str_starts_with( $clean, '98' ) && strlen( $clean ) === 12 ) {
			$clean = substr( $clean, 2 );
		}

		if ( strlen( $clean ) === 10 && str_starts_with( $clean, '9' ) ) {
			$clean = '0' . $clean;
		}

		return $clean;
	}

	/**
	 * Retrieve full display name for a user.
	 *
	 * @param int $user_id WordPress User ID.
	 * @return string Full name or username.
	 */
	public static function get_user_full_name( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return 'کاربر مهمان';
		}

		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return 'کاربر #' . $user_id;
		}

		$first = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
		$last  = trim( (string) get_user_meta( $user_id, 'last_name', true ) );
		if ( ! empty( $first ) || ! empty( $last ) ) {
			return trim( $first . ' ' . $last );
		}

		$b_first = trim( (string) get_user_meta( $user_id, 'billing_first_name', true ) );
		$b_last  = trim( (string) get_user_meta( $user_id, 'billing_last_name', true ) );
		if ( ! empty( $b_first ) || ! empty( $b_last ) ) {
			return trim( $b_first . ' ' . $b_last );
		}

		if ( ! empty( $u->display_name ) ) {
			return (string) $u->display_name;
		}

		return (string) $u->user_login;
	}

	/**
	 * Check if a user is restricted from downloading price lists.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if restricted.
	 */
	public static function is_user_restricted( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		return (bool) get_user_meta( $user_id, '_singo_price_list_restricted', true );
	}

	/**
	 * Set or clear price list restriction for a user.
	 *
	 * @param int    $user_id    User ID.
	 * @param bool   $restricted True to restrict, false to unrestrict.
	 * @param string $reason     Optional reason for audit note.
	 */
	public static function set_user_restriction( int $user_id, bool $restricted, string $reason = '' ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		if ( $restricted ) {
			update_user_meta( $user_id, '_singo_price_list_restricted', 1 );
			if ( ! empty( $reason ) ) {
				update_user_meta( $user_id, '_singo_price_list_restricted_reason', sanitize_text_field( $reason ) );
			}
			update_user_meta( $user_id, '_singo_price_list_restricted_time', time() );
		} else {
			delete_user_meta( $user_id, '_singo_price_list_restricted' );
			delete_user_meta( $user_id, '_singo_price_list_restricted_reason' );
			delete_user_meta( $user_id, '_singo_price_list_restricted_time' );
		}
	}

	/**
	 * Get detailed restriction information for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array{is_restricted: bool, reason: string, updated_at: int}
	 */
	public static function get_user_restriction_info( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array( 'is_restricted' => false, 'reason' => '', 'updated_at' => 0 );
		}
		$restricted = (bool) get_user_meta( $user_id, '_singo_price_list_restricted', true );
		$reason     = (string) get_user_meta( $user_id, '_singo_price_list_restricted_reason', true );
		$time       = (int) get_user_meta( $user_id, '_singo_price_list_restricted_time', true );

		return array(
			'is_restricted' => $restricted,
			'reason'        => $reason,
			'updated_at'    => $time,
		);
	}

	/**
	 * Record a permanent download log entry for the administrator report.
	 * NEVER automatically purged by cron; only deleted manually by administrator.
	 *
	 * @param int                          $user_id       User ID.
	 * @param int                          $category_id   Category ID.
	 * @param string                       $category_name Category Name.
	 * @param string                       $doc_title     Document title.
	 * @param string                       $shamsi_date   Formatted Shamsi date.
	 * @param array<int, array<string, mixed>> $products_data       Products data array.
	 * @param string                           $snapshot_data       Compressed snapshot data string (optional).
	 * @param string                           $customer_history_id Corresponding customer history item ID (optional).
	 * @return string Log ID.
	 */
	public static function record_admin_download_log( int $user_id, int $category_id, string $category_name, string $doc_title, string $shamsi_date, array $products_data, string $snapshot_data = '', string $customer_history_id = '' ): string {
		if ( $user_id <= 0 || empty( $products_data ) ) {
			return '';
		}

		$now  = time();
		$logs = get_user_meta( $user_id, '_singo_price_list_admin_logs', true );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		// Prepare snapshot if not already compressed
		if ( empty( $snapshot_data ) ) {
			$compact_products = array_map( static function( array $prod ): array {
				return array(
					'id'         => (int) ( $prod['id'] ?? 0 ),
					'title'      => (string) ( $prod['title'] ?? '' ),
					'short_desc' => (string) ( $prod['short_desc'] ?? '' ),
					'sku'        => (string) ( $prod['sku'] ?? '' ),
					'thumb_url'  => (string) ( $prod['thumb_url'] ?? '' ),
					'brand_name' => (string) ( $prod['brand_name'] ?? '' ),
					'brand_logo' => (string) ( $prod['brand_logo'] ?? '' ),
					'price_html' => (string) ( $prod['price_html'] ?? '' ),
				);
			}, $products_data );

			$json = wp_json_encode( $compact_products );
			if ( false !== $json ) {
				if ( function_exists( 'gzcompress' ) ) {
					$compressed = gzcompress( $json, 6 );
					if ( false !== $compressed ) {
						$snapshot_data = 'gz:' . base64_encode( $compressed );
					}
				}
				if ( empty( $snapshot_data ) ) {
					$snapshot_data = 'raw:' . base64_encode( $json );
				}
			}
		}

		// Avoid duplicate spam within 30 seconds for same category
		if ( ! empty( $logs[0] ) && (int) ( $logs[0]['category_id'] ?? -1 ) === $category_id && ( $now - (int) $logs[0]['created_at'] ) < 30 ) {
			$logs[0]['created_at']     = $now;
			$logs[0]['shamsi_date']    = $shamsi_date;
			$logs[0]['doc_title']      = $doc_title;
			$logs[0]['products_count'] = count( $products_data );
			if ( ! empty( $snapshot_data ) ) {
				$logs[0]['snapshot_data'] = $snapshot_data;
			}
			if ( ! empty( $customer_history_id ) ) {
				$logs[0]['customer_history_id'] = $customer_history_id;
			}
			update_user_meta( $user_id, '_singo_price_list_admin_logs', $logs );
			return (string) $logs[0]['id'];
		}

		$log_id    = 'adm_' . substr( md5( uniqid( (string) $user_id, true ) ), 0, 12 );
		$new_entry = array(
			'id'                  => $log_id,
			'created_at'          => $now,
			'shamsi_date'         => $shamsi_date,
			'category_id'         => $category_id,
			'category_name'       => $category_name,
			'doc_title'           => $doc_title,
			'products_count'      => count( $products_data ),
			'snapshot_data'       => $snapshot_data,
			'customer_history_id' => $customer_history_id,
		);

		array_unshift( $logs, $new_entry );

		// Retain up to 100 historical logs per user
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, 0, 100 );
		}

		update_user_meta( $user_id, '_singo_price_list_admin_logs', $logs );
		return $log_id;
	}

	/**
	 * Backfill any pre-existing customer download histories into the permanent admin logs once.
	 */
	public static function maybe_backfill_existing_customer_histories(): void {
		if ( get_option( 'singo_price_list_admin_backfilled', false ) ) {
			return;
		}

		global $wpdb;
		$meta_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s LIMIT 200",
				'_singo_price_list_history'
			)
		);

		if ( ! empty( $meta_rows ) && is_array( $meta_rows ) ) {
			foreach ( $meta_rows as $row ) {
				$user_id = (int) $row->user_id;
				$existing_admin_logs = get_user_meta( $user_id, '_singo_price_list_admin_logs', true );
				if ( ! empty( $existing_admin_logs ) && is_array( $existing_admin_logs ) ) {
					continue;
				}

				$customer_history = maybe_unserialize( $row->meta_value );
				if ( ! is_array( $customer_history ) || empty( $customer_history ) ) {
					continue;
				}

				$admin_logs = array();
				foreach ( $customer_history as $item ) {
					if ( ! is_array( $item ) || empty( $item['id'] ) ) {
						continue;
					}
					$admin_logs[] = array(
						'id'                  => 'adm_' . substr( (string) $item['id'], 4 ),
						'created_at'          => (int) ( $item['created_at'] ?? time() ),
						'shamsi_date'         => (string) ( $item['shamsi_date'] ?? '' ),
						'category_id'         => (int) ( $item['category_id'] ?? 0 ),
						'category_name'       => (string) ( $item['category_name'] ?? 'همه محصولات' ),
						'doc_title'           => (string) ( $item['doc_title'] ?? '' ),
						'products_count'      => (int) ( $item['products_count'] ?? 0 ),
						'snapshot_data'       => (string) ( $item['snapshot_data'] ?? '' ),
						'customer_history_id' => (string) ( $item['id'] ?? '' ),
					);
				}

				if ( ! empty( $admin_logs ) ) {
					update_user_meta( $user_id, '_singo_price_list_admin_logs', $admin_logs );
				}
			}
		}

		update_option( 'singo_price_list_admin_backfilled', true, false );
	}

	/**
	 * Fetch compiled reports of all users with download history or restrictions.
	 *
	 * @return array{total_downloads: int, total_users: int, total_restricted: int, users: array<int, array<string, mixed>>}
	 */
	public static function get_all_admin_download_reports(): array {
		global $wpdb;

		self::maybe_backfill_existing_customer_histories();

		// Find all user IDs with admin download logs OR restrictions
		$query = "
			SELECT DISTINCT user_id 
			FROM {$wpdb->usermeta} 
			WHERE meta_key IN ('_singo_price_list_admin_logs', '_singo_price_list_restricted')
			LIMIT 500
		";
		$user_ids = $wpdb->get_col( $query );

		$total_downloads  = 0;
		$total_restricted = 0;
		$users_data       = array();

		if ( ! empty( $user_ids ) ) {
			foreach ( $user_ids as $uid_raw ) {
				$uid = (int) $uid_raw;
				if ( $uid <= 0 ) {
					continue;
				}

				$u = get_userdata( $uid );
				if ( ! $u ) {
					continue;
				}

				$logs          = get_user_meta( $uid, '_singo_price_list_admin_logs', true );
				$count         = is_array( $logs ) ? count( $logs ) : 0;
				$total_downloads += $count;

				$is_restricted   = self::is_user_restricted( $uid );
				if ( $is_restricted ) {
					$total_restricted++;
				}
				$restrict_info   = self::get_user_restriction_info( $uid );

				$last_entry      = ! empty( $logs[0] ) && is_array( $logs[0] ) ? $logs[0] : array();
				$last_cat        = (string) ( $last_entry['category_name'] ?? ( $count > 0 ? 'همه محصولات' : '-' ) );
				$last_date       = (string) ( $last_entry['shamsi_date'] ?? '' );
				$last_timestamp  = (int) ( $last_entry['created_at'] ?? 0 );

				$users_data[] = array(
					'user_id'          => $uid,
					'display_name'     => self::get_user_full_name( $uid ),
					'user_login'       => $u->user_login,
					'email'            => $u->user_email,
					'phone'            => self::get_user_phone_number( $uid ),
					'downloads_count'  => $count,
					'last_category'    => $last_cat,
					'last_date_shamsi' => $last_date,
					'last_timestamp'   => $last_timestamp,
					'is_restricted'    => $is_restricted,
					'restrict_reason'  => $restrict_info['reason'],
				);
			}

			// Sort by latest download or restriction
			usort( $users_data, static function( array $a, array $b ): int {
				return $b['last_timestamp'] <=> $a['last_timestamp'];
			} );
		}

		return array(
			'total_downloads'  => $total_downloads,
			'total_users'      => count( $users_data ),
			'total_restricted' => $total_restricted,
			'users'            => $users_data,
		);
	}

	/**
	 * Retrieve frozen snapshot data from admin logs for administrator review.
	 *
	 * @param int    $user_id User ID.
	 * @param string $log_id  Log ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_admin_log_snapshot( int $user_id, string $log_id ): ?array {
		if ( $user_id <= 0 || empty( $log_id ) ) {
			return null;
		}

		$logs = get_user_meta( $user_id, '_singo_price_list_admin_logs', true );
		if ( ! is_array( $logs ) ) {
			return null;
		}

		foreach ( $logs as $entry ) {
			if ( isset( $entry['id'] ) && $entry['id'] === $log_id ) {
				$raw_snapshot = (string) ( $entry['snapshot_data'] ?? '' );
				$products     = array();

				if ( str_starts_with( $raw_snapshot, 'gz:' ) ) {
					$b64        = substr( $raw_snapshot, 3 );
					$compressed = base64_decode( $b64, true );
					if ( false !== $compressed && function_exists( 'gzuncompress' ) ) {
						$json = gzuncompress( $compressed );
						if ( false !== $json ) {
							$products = json_decode( $json, true ) ?: array();
						}
					}
				} elseif ( str_starts_with( $raw_snapshot, 'raw:' ) ) {
					$b64  = substr( $raw_snapshot, 4 );
					$json = base64_decode( $b64, true );
					if ( false !== $json ) {
						$products = json_decode( $json, true ) ?: array();
					}
				}

				if ( ! empty( $products ) && is_array( $products ) ) {
					$entry['products_data'] = $products;
					return $entry;
				}
			}
		}

		return null;
	}

	/**
	 * Delete a single log entry from admin logs and synchronously purge it from customer history.
	 *
	 * @param int    $user_id User ID.
	 * @param string $log_id  Log ID.
	 * @return bool
	 */
	public static function delete_admin_log_entry( int $user_id, string $log_id ): bool {
		if ( $user_id <= 0 || empty( $log_id ) ) {
			return false;
		}

		$logs = get_user_meta( $user_id, '_singo_price_list_admin_logs', true );
		if ( ! is_array( $logs ) ) {
			return false;
		}

		$new_logs     = array();
		$found        = false;
		$deleted_item = null;

		foreach ( $logs as $item ) {
			if ( isset( $item['id'] ) && $item['id'] === $log_id ) {
				$found        = true;
				$deleted_item = $item;
				continue;
			}
			$new_logs[] = $item;
		}

		if ( $found ) {
			if ( ! empty( $new_logs ) ) {
				update_user_meta( $user_id, '_singo_price_list_admin_logs', $new_logs );
			} else {
				delete_user_meta( $user_id, '_singo_price_list_admin_logs' );
			}

			// Synchronously purge matching record from customer's My Account history
			if ( is_array( $deleted_item ) ) {
				$cust_hist_id = (string) ( $deleted_item['customer_history_id'] ?? '' );
				$cat_id       = (int) ( $deleted_item['category_id'] ?? 0 );
				$created_at   = (int) ( $deleted_item['created_at'] ?? 0 );

				$customer_history = get_user_meta( $user_id, '_singo_price_list_history', true );
				if ( is_array( $customer_history ) && ! empty( $customer_history ) ) {
					$new_cust_history = array();
					$cust_changed     = false;

					foreach ( $customer_history as $c_item ) {
						if ( ! is_array( $c_item ) ) {
							continue;
						}
						$c_id = (string) ( $c_item['id'] ?? '' );

						// Match by explicit customer_history_id, or matching suffix, or matching category & timestamp
						$is_match = false;
						if ( ! empty( $cust_hist_id ) && $c_id === $cust_hist_id ) {
							$is_match = true;
						} elseif ( str_starts_with( $log_id, 'adm_' ) && str_starts_with( $c_id, 'spl_' ) && substr( $log_id, 4 ) === substr( $c_id, 4 ) ) {
							$is_match = true;
						} elseif ( (int) ( $c_item['category_id'] ?? -1 ) === $cat_id && abs( (int) ( $c_item['created_at'] ?? 0 ) - $created_at ) <= 60 ) {
							$is_match = true;
						}

						if ( $is_match ) {
							$cust_changed = true;
							continue;
						}
						$new_cust_history[] = $c_item;
					}

					if ( $cust_changed ) {
						if ( ! empty( $new_cust_history ) ) {
							update_user_meta( $user_id, '_singo_price_list_history', $new_cust_history );
						} else {
							delete_user_meta( $user_id, '_singo_price_list_history' );
						}
					}
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Clear all admin logs for a specific user and completely purge that user's customer history.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function clear_user_admin_logs( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		delete_user_meta( $user_id, '_singo_price_list_admin_logs' );
		delete_user_meta( $user_id, '_singo_price_list_history' );
		return true;
	}

	/**
	 * Clear all admin download logs and customer histories across all users.
	 */
	public static function clear_all_admin_logs(): void {
		global $wpdb;
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => '_singo_price_list_admin_logs' ), array( '%s' ) );
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => '_singo_price_list_history' ), array( '%s' ) );
	}

	/**
	 * AJAX Handler: Toggle User Price List Restriction.
	 */
	public function ajax_admin_toggle_user_restriction(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );
		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$user_id    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$identifier = isset( $_POST['user_identifier'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['user_identifier'] ) ) : '';
		$restricted = ! empty( $_POST['restricted'] );
		$reason     = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['reason'] ) ) : '';

		if ( $user_id <= 0 && ! empty( $identifier ) ) {
			if ( is_numeric( $identifier ) && (int) $identifier < 1000000 ) {
				$user_id = (int) $identifier;
			} else {
				// Search by phone or username
				$clean_phone = preg_replace( '/[^\d]/', '', $identifier );
				$short_phone = ( str_starts_with( $clean_phone, '0' ) && strlen( $clean_phone ) === 11 ) ? substr( $clean_phone, 1 ) : $clean_phone;

				global $wpdb;
				$found_uid = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT user_id FROM {$wpdb->usermeta} WHERE (meta_key = 'mobile_otino' AND (meta_value = %s OR meta_value = %s)) OR (meta_key = 'billing_phone' AND (meta_value = %s OR meta_value = %s)) LIMIT 1",
					$clean_phone, $short_phone, $clean_phone, $short_phone
				) );

				if ( $found_uid > 0 ) {
					$user_id = $found_uid;
				} else {
					$by_user = get_user_by( 'login', $identifier ) ?: get_user_by( 'email', $identifier );
					if ( $by_user ) {
						$user_id = (int) $by_user->ID;
					}
				}
			}
		}

		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'کاربری با این شناسه یا شماره موبایل یافت نشد.' ) );
		}

		// Prevent restricting Administrators
		if ( user_can( $user_id, 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'امکان محدودسازی مدیران ارشد سایت وجود ندارد.' ) );
		}

		self::set_user_restriction( $user_id, $restricted, $reason );

		$reports = self::get_all_admin_download_reports();
		$msg     = $restricted ? 'دسترسی کاربر به دریافت لیست قیمت مسدود گردید.' : 'محدودیت کاربر با موفقیت لغو شد.';

		wp_send_json_success( array(
			'message'          => $msg,
			'user_id'          => $user_id,
			'is_restricted'    => $restricted,
			'total_restricted' => $reports['total_restricted'],
		) );
	}

	/**
	 * AJAX Handler: Delete Admin Download Log Entry or User Logs.
	 */
	public function ajax_admin_delete_download_log(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );
		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$user_id   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$log_id    = isset( $_POST['log_id'] ) ? sanitize_key( (string) $_POST['log_id'] ) : '';
		$clear_all = ! empty( $_POST['clear_user_all'] );

		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'شناسه کاربر نامعتبر است.' ) );
		}

		if ( $clear_all ) {
			self::clear_user_admin_logs( $user_id );
			wp_send_json_success( array( 'message' => 'سوابق کاربر با موفقیت حذف شد.' ) );
		}

		if ( empty( $log_id ) ) {
			wp_send_json_error( array( 'message' => 'شناسه لاگ نامعتبر است.' ) );
		}

		$deleted = self::delete_admin_log_entry( $user_id, $log_id );
		if ( $deleted ) {
			wp_send_json_success( array( 'message' => 'رکورد با موفقیت حذف گردید.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'رکورد یافت نشد یا قبلاً حذف شده است.' ) );
		}
	}

	/**
	 * AJAX Handler: Clear All Download Logs for All Users.
	 */
	public function ajax_admin_clear_all_download_logs(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );
		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		self::clear_all_admin_logs();
		wp_send_json_success( array( 'message' => 'کلیه گزارشات دانلود با موفقیت پاکسازی شدند.' ) );
	}

	/**
	 * AJAX Handler: Fetch Detailed Dossier of a User's Downloads.
	 */
	public function ajax_admin_get_user_download_dossier(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );
		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'شناسه کاربر نامعتبر است.' ) );
		}

		$u = get_userdata( $user_id );
		if ( ! $u ) {
			wp_send_json_error( array( 'message' => 'کاربر در وردپرس یافت نشد.' ) );
		}

		$logs          = get_user_meta( $user_id, '_singo_price_list_admin_logs', true );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		$restrict_info = self::get_user_restriction_info( $user_id );

		$files = array();
		foreach ( $logs as $entry ) {
			$preview_url = add_query_arg( array(
				'action'       => 'singo_export_price_list_pdf',
				'security'     => wp_create_nonce( 'singo_price_list_nonce' ),
				'admin_log_id' => $entry['id'] ?? '',
				'log_user_id'  => $user_id,
			), admin_url( 'admin-post.php' ) );

			$count = (int) ( $entry['products_count'] ?? 0 );
			$count_fa = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $count ) : (string) $count;

			$files[] = array(
				'id'             => $entry['id'] ?? '',
				'category_name'  => (string) ( $entry['category_name'] ?? 'همه محصولات' ),
				'doc_title'      => (string) ( $entry['doc_title'] ?? '' ),
				'products_count' => $count_fa,
				'shamsi_date'    => (string) ( $entry['shamsi_date'] ?? '' ),
				'preview_url'    => $preview_url,
			);
		}

		wp_send_json_success( array(
			'user' => array(
				'user_id'         => $user_id,
				'display_name'    => self::get_user_full_name( $user_id ),
				'user_login'      => $u->user_login,
				'email'           => $u->user_email,
				'phone'           => self::get_user_phone_number( $user_id ),
				'is_restricted'   => $restrict_info['is_restricted'],
				'restrict_reason' => $restrict_info['reason'],
			),
			'files' => $files,
		) );
	}

	/**
	 * Render HTML items for customer download history table/cards.
	 *
	 * @param array<int, array<string, mixed>> $history      History records.
	 * @param string                           $endpoint_url Endpoint URL.
	 * @param string                           $nonce        Security nonce.
	 */
	public static function render_history_items_html( array $history, string $endpoint_url, string $nonce ): void {
		if ( empty( $history ) ) : ?>
			<div class="singo-history-empty" style="text-align: center; padding: 28px 16px; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 10px; color: #64748b; font-size: 13px;">
				<div style="width: 44px; height: 44px; margin: 0 auto 10px auto; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
						<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
					</svg>
				</div>
				هنوز هیچ فایلی توسط شما دانلود نشده است. پس از اولین دریافت، سوابق دانلود جهت دسترسی سریع‌تر در اینجا نمایش داده می‌شوند.
			</div>
			<?php
			return;
		endif;
		?>
		<div class="singo-history-list" style="display: flex; flex-direction: column; gap: 10px;">
			<?php
			$now = time();
			foreach ( $history as $item ) :
				$history_id   = (string) ( $item['id'] ?? '' );
				$cat_name     = (string) ( $item['category_name'] ?? 'همه محصولات' );
				$cat_color    = (string) ( $item['category_color'] ?? '#ad6141' );
				$count        = (int) ( $item['products_count'] ?? 0 );
				$shamsi_date  = (string) ( $item['shamsi_date'] ?? '' );
				$expires_at   = (int) ( $item['expires_at'] ?? 0 );
				$rem_sec      = max( 0, $expires_at - $now );
				$rem_days     = (int) ceil( $rem_sec / DAY_IN_SECONDS );

				$count_fa     = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $count ) : (string) $count;
				$rem_days_fa  = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $rem_days ) : (string) $rem_days;

				$download_url = add_query_arg( array(
					'singo_download_pdf' => '1',
					'action'             => 'singo_export_price_list_pdf',
					'security'           => $nonce,
					'customer_export'    => '1',
					'autoprint'          => '1',
					'history_id'         => $history_id,
					'category_id'        => (int) ( $item['category_id'] ?? 0 ),
				), $endpoint_url );
				?>
				<div class="singo-history-item-row" id="singo-history-item-<?php echo esc_attr( $history_id ); ?>">
					<div class="singo-history-item-info">
						<span class="singo-history-item-dot" style="background: <?php echo esc_attr( $cat_color ); ?>;"></span>
						<div style="min-width: 0; flex: 1;">
							<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
								<strong style="color: #1e293b; font-size: 13.5px;"><?php echo esc_html( $cat_name ); ?></strong>
								<span style="font-size: 11px; background: #f1f5f9; color: #475569; padding: 1px 7px; border-radius: 4px; font-weight: 600;">
									<?php echo esc_html( $count_fa ); ?> کالا
								</span>
							</div>
							<div style="font-size: 11.5px; color: #64748b; margin-top: 4px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
								<span style="display: inline-flex; align-items: center; gap: 4px;">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; display: inline-block;">
										<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
										<line x1="16" y1="2" x2="16" y2="6"></line>
										<line x1="8" y1="2" x2="8" y2="6"></line>
										<line x1="3" y1="10" x2="21" y2="10"></line>
									</svg>
									<span><?php echo esc_html( $shamsi_date ); ?></span>
								</span>
								<span style="color: #059669; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; display: inline-block;">
										<circle cx="12" cy="12" r="10"></circle>
										<polyline points="12 6 12 12 16 14"></polyline>
									</svg>
									<span>معتبر تا <?php echo esc_html( $rem_days_fa ); ?> روز دیگر</span>
								</span>
							</div>
						</div>
					</div>

					<div class="singo-history-item-actions">
						<a href="<?php echo esc_url( $download_url ); ?>" target="_blank" class="singo-history-redownload-btn">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; display: inline-block;">
								<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
								<polyline points="7 10 12 15 17 10"></polyline>
								<line x1="12" y1="15" x2="12" y2="3"></line>
							</svg>
							<span>دانلود مجدد</span>
						</a>
						<button type="button" class="singo-history-delete-btn" data-id="<?php echo esc_attr( $history_id ); ?>" title="حذف این مورد از تاریخچه">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px !important; height: 15px !important; min-width: 15px !important; display: block !important; flex-shrink: 0 !important;">
								<polyline points="3 6 5 6 21 6"></polyline>
								<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
								<line x1="10" y1="11" x2="10" y2="17"></line>
								<line x1="14" y1="11" x2="14" y2="17"></line>
							</svg>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * AJAX Handler: Fetch customer download history markup.
	 */
	public function ajax_get_customer_download_history(): void {
		check_ajax_referer( 'singo_customer_price_list_nonce', 'security' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده است.' ) );
		}

		$user_id      = get_current_user_id();
		$history      = self::get_user_download_history( $user_id );
		$endpoint_url = function_exists( 'wc_get_account_endpoint_url' )
			? wc_get_account_endpoint_url( 'price-list' )
			: ( home_url( '/my-account/price-list/' ) );
		$nonce        = wp_create_nonce( 'singo_customer_price_list_nonce' );

		ob_start();
		self::render_history_items_html( $history, $endpoint_url, $nonce );
		$html = ob_get_clean();

		wp_send_json_success( array(
			'count' => count( $history ),
			'html'  => $html,
		) );
	}

	/**
	 * AJAX Handler: Delete customer download history entry.
	 */
	public function ajax_delete_customer_history_item(): void {
		check_ajax_referer( 'singo_customer_price_list_nonce', 'security' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده است.' ) );
		}

		$history_id = isset( $_POST['history_id'] ) ? sanitize_key( (string) $_POST['history_id'] ) : '';
		if ( empty( $history_id ) ) {
			wp_send_json_error( array( 'message' => 'شناسه رکورد نامعتبر است.' ) );
		}

		$deleted = self::delete_user_history_entry( get_current_user_id(), $history_id );
		if ( $deleted ) {
			wp_send_json_success( array( 'message' => 'رکورد با موفقیت حذف گردید.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'رکورد یافت نشد یا قبلاً حذف شده است.' ) );
		}
	}

	/**
	 * Render WooCommerce My Account price list tab content.
	 */
	public function render_my_account_content(): void {
		if ( is_user_logged_in() && self::is_user_restricted( (int) get_current_user_id() ) ) {
			$restrict_info = self::get_user_restriction_info( (int) get_current_user_id() );
			?>
			<div class="singo-price-list-wrapper" style="direction: rtl; text-align: right; max-width: 800px; margin: 0 auto; font-family: inherit !important;">
				<div style="background: #fff1f2; border: 1.5px solid #fecdd3; border-radius: 12px; padding: 28px 24px; text-align: center; box-shadow: 0 4px 12px rgba(225,29,72,0.06);">
					<div style="width: 56px; height: 56px; margin: 0 auto 14px auto; border-radius: 50%; background: #ffe4e6; display: flex; align-items: center; justify-content: center; color: #e11d48;">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
							<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
						</svg>
					</div>
					<h3 style="margin: 0 0 8px 0; font-size: 17px; font-weight: 800; color: #9f1239;">دسترسی شما به دریافت لیست قیمت محدود شده است</h3>
					<p style="margin: 0; font-size: 13.5px; color: #be123c; line-height: 1.6;">
						با تصمیم مدیریت فروشگاه، امکان دریافت و دانلود لیست قیمت برای حساب کاربری شما غیرفعال شده است. در صورت نیاز به دریافت کاتالوگ و قیمت‌های رسمی، لطفاً با واحد پشتیبانی و فروش تماس حاصل فرمایید.
					</p>
					<?php if ( ! empty( $restrict_info['reason'] ) ) : ?>
						<div style="margin-top: 14px; display: inline-block; background: #ffffff; border: 1px dashed #f43f5e; padding: 6px 14px; border-radius: 6px; font-size: 12px; color: #9f1239; font-weight: 600;">
							علت اعلام‌شده: <?php echo esc_html( $restrict_info['reason'] ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return;
		}

		$title           = (string) self::get_setting( 'my_account_price_list_title', 'لیست قیمت محصولات' );
		$desc            = (string) self::get_setting( 'my_account_price_list_desc', 'از این بخش می‌توانید جدیدترین لیست قیمت رسمی و کاتالوگ محصولات را با یک کلیک به صورت فایل PDF دریافت و دانلود نمایید.' );
		$account_color   = (string) self::get_setting( 'my_account_primary_color', '' );
		$primary_color   = ! empty( $account_color ) ? $account_color : self::get_active_primary_color();
		if ( function_exists( 'sanitize_hex_color' ) ) {
			$clean_color = sanitize_hex_color( $primary_color );
			if ( ! empty( $clean_color ) ) {
				$primary_color = $clean_color;
			}
		}
		$icon_bg_tint    = self::hex_to_rgba( $primary_color, 0.12 );
		$btn_shadow_tint = self::hex_to_rgba( $primary_color, 0.28 );
		$focus_ring_tint = self::hex_to_rgba( $primary_color, 0.18 );
		$nonce           = wp_create_nonce( 'singo_customer_price_list_nonce' );

		// Use the frontend endpoint URL so non-admin customers never hit wp-admin blocks
		$endpoint_url = function_exists( 'wc_get_account_endpoint_url' )
			? wc_get_account_endpoint_url( 'price-list' )
			: ( home_url( '/my-account/price-list/' ) );

		// Build hierarchical category tree for custom picker
		$categories_tree = self::get_hierarchical_category_tree();
		$total_products  = 0;
		foreach ( $categories_tree as $c ) {
			if ( 0 === $c['level'] ) {
				$total_products += $c['count'];
			}
		}
		if ( $total_products <= 0 ) {
			$counts = wp_count_posts( 'product' );
			$total_products = (int) ( $counts->publish ?? 0 );
		}
		$total_products_fa = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $total_products ) : (string) $total_products;

		$shamsi_date = wp_date( 'Y/m/d' );
		if ( class_exists( 'Singo_Shamsi_Converter' ) ) {
			$shamsi_date = Singo_Shamsi_Converter::format_date( time(), 'l، j F Y' );
		}

		$user_id         = get_current_user_id();
		$history_enabled = self::is_customer_history_enabled();
		$history_items   = $history_enabled ? self::get_user_download_history( $user_id ) : array();
		$retention_days  = self::get_history_retention_days();
		$retention_days_fa = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $retention_days ) : (string) $retention_days;
		?>
		<div class="singo-account-price-list-card" 
		     id="singo-customer-card"
		     data-endpoint="<?php echo esc_url( $endpoint_url ); ?>"
		     data-security="<?php echo esc_attr( $nonce ); ?>"
		     data-primary="<?php echo esc_attr( $primary_color ); ?>"
		     data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		     style="direction: rtl; text-align: right; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); font-family: inherit !important;">
			
			<!-- Card Header -->
			<div style="display: flex; align-items: center; gap: 14px; margin-bottom: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px;">
				<div style="width: 48px; height: 48px; border-radius: 10px; background: <?php echo esc_attr( $icon_bg_tint ); ?>; color: <?php echo esc_attr( $primary_color ); ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.2s ease;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr( $primary_color ); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
						<polyline points="14 2 14 8 20 8"></polyline>
						<line x1="16" y1="13" x2="8" y2="13"></line>
						<line x1="16" y1="17" x2="8" y2="17"></line>
						<polyline points="10 9 9 9 8 9"></polyline>
					</svg>
				</div>
				<div>
					<h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #1e293b;"><?php echo esc_html( $title ); ?></h3>
					<span style="font-size: 12px; color: #64748b; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; display: inline-block;">
							<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
							<line x1="16" y1="2" x2="16" y2="6"></line>
							<line x1="8" y1="2" x2="8" y2="6"></line>
							<line x1="3" y1="10" x2="21" y2="10"></line>
						</svg>
						<span>تاریخ به‌روزرسانی: <?php echo esc_html( $shamsi_date ); ?></span>
					</span>
				</div>
			</div>

			<p style="font-size: 14px; line-height: 1.8; color: #475569; margin: 0 0 22px 0;">
				<?php echo nl2br( esc_html( $desc ) ); ?>
			</p>

			<!-- Modern Hierarchical Category Picker Section -->
			<div class="singo-cat-picker-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
				<label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 13.5px; color: #334155; margin-bottom: 10px;">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr( $primary_color ); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; display: inline-block;">
						<polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
						<polyline points="2 17 12 22 22 17"></polyline>
						<polyline points="2 12 12 17 22 12"></polyline>
					</svg>
					<span>انتخاب دسته‌بندی مدنظر:</span>
				</label>

				<!-- Hidden Native Input holding selected category ID -->
				<input type="hidden" id="singo-customer-cat-select" value="0" />

				<!-- Custom Combobox Trigger -->
				<div class="singo-cat-picker-container" style="position: relative;">
					<button type="button" id="singo-cat-picker-trigger" class="singo-cat-picker-trigger" aria-haspopup="listbox" aria-expanded="false" style="width: 100%; min-height: 46px; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 8px 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; cursor: pointer; transition: all 0.2s ease; font-family: inherit; text-align: right; margin: 0 !important; line-height: 1.4 !important; box-shadow: none !important;">
						<div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;">
							<span id="singo-cat-selected-dot" style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo esc_attr( $primary_color ); ?>; display: inline-block; flex-shrink: 0; box-shadow: 0 0 0 2px rgba(0,0,0,0.06);"></span>
							<span id="singo-cat-selected-text" style="font-size: 13.5px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
								تمامی محصولات و دسته‌بندی‌ها
							</span>
							<span id="singo-cat-selected-badge" style="font-size: 11px; background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 12px; font-weight: 600; flex-shrink: 0;">
								<?php echo esc_html( $total_products_fa ); ?> کالا
							</span>
						</div>
						<svg id="singo-cat-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; transition: transform 0.2s ease;">
							<polyline points="6 9 12 15 18 9"></polyline>
						</svg>
					</button>

					<!-- Custom Combobox Dropdown Popover -->
					<div id="singo-cat-picker-dropdown" class="singo-cat-picker-dropdown" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08); z-index: 999; max-height: min(340px, 60dvh); overflow: hidden; flex-direction: column;">
						
						<!-- Live Search Input -->
						<div style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9; background: #fafafa; display: flex; align-items: center; gap: 8px;">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
								<circle cx="11" cy="11" r="8"></circle>
								<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
							</svg>
							<input type="text" id="singo-cat-search-input" placeholder="جستجوی سریع در دسته‌بندی‌ها..." autocomplete="off" style="width: 100%; border: none; background: transparent; font-size: 12.5px; font-family: inherit; outline: none; padding: 4px 0; color: #1e293b;" />
							<button type="button" id="singo-cat-search-clear" style="display: none; background: none; border: none; padding: 0 4px !important; margin: 0 !important; cursor: pointer; color: #94a3b8; line-height: 1;" title="پاک کردن جستجو">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
									<line x1="18" y1="6" x2="6" y2="18"></line>
									<line x1="6" y1="6" x2="18" y2="18"></line>
								</svg>
							</button>
						</div>

						<!-- Options Scrollable List -->
						<div id="singo-cat-options-list" style="overflow-y: auto; max-height: 280px; padding: 6px;">
							
							<!-- Option 0: All Products -->
							<div class="singo-cat-option singo-cat-option--active" data-id="0" data-name="تمامی محصولات و دسته‌بندی‌ها" data-count="<?php echo esc_attr( $total_products_fa ); ?>" data-color="<?php echo esc_attr( $primary_color ); ?>" style="display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 12px; border-radius: 6px; cursor: pointer; transition: background 0.15s ease; background: rgba(173, 97, 65, 0.08);">
								<div style="display: flex; align-items: center; gap: 10px;">
									<span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo esc_attr( $primary_color ); ?>; display: inline-block;"></span>
									<span style="font-size: 13px; font-weight: 700; color: #0f172a;">تمامی محصولات و دسته‌بندی‌ها</span>
								</div>
								<span style="font-size: 11px; color: #64748b; background: #ffffff; padding: 2px 7px; border-radius: 10px; font-weight: 600; border: 1px solid #e2e8f0;">
									<?php echo esc_html( $total_products_fa ); ?> کالا
								</span>
							</div>

							<!-- Tree Options -->
							<?php foreach ( $categories_tree as $cat ) :
								$cat_id       = (int) $cat['id'];
								$cat_name     = (string) $cat['name'];
								$cat_path     = (string) $cat['path'];
								$cat_level    = (int) $cat['level'];
								$cat_count    = (int) $cat['count'];
								$cat_color    = (string) $cat['color'];
								$cat_count_fa = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $cat_count ) : (string) $cat_count;
								$indent_px    = ( $cat_level * 18 );
								$is_parent    = ( 0 === $cat_level );
								?>
								<div class="singo-cat-option" 
								     data-id="<?php echo esc_attr( (string) $cat_id ); ?>" 
								     data-name="<?php echo esc_attr( $cat_name ); ?>" 
								     data-path="<?php echo esc_attr( $cat_path ); ?>" 
								     data-count="<?php echo esc_attr( $cat_count_fa ); ?>" 
								     data-color="<?php echo esc_attr( $cat_color ); ?>"
								     data-level="<?php echo esc_attr( (string) $cat_level ); ?>"
								     style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; padding-right: <?php echo esc_attr( (string) ( 12 + $indent_px ) ); ?>px; border-radius: 6px; cursor: pointer; transition: background 0.15s ease;">
									<div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
										<?php if ( $cat_level > 0 ) : ?>
											<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; transform: scaleX(-1); display: inline-block;">
												<polyline points="9 10 4 15 9 20"></polyline>
												<path d="M20 4v7a4 4 0 0 1-4 4H4"></path>
											</svg>
										<?php endif; ?>
										<span style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo esc_attr( $cat_color ); ?>; display: inline-block; flex-shrink: 0;"></span>
										<span style="font-size: 13px; color: <?php echo $is_parent ? '#0f172a' : '#334155'; ?>; font-weight: <?php echo $is_parent ? '700' : '500'; ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
											<?php echo esc_html( $cat_name ); ?>
										</span>
									</div>
									<span style="font-size: 11px; color: #64748b; background: #f8fafc; padding: 2px 7px; border-radius: 10px; font-weight: 600; border: 1px solid #f1f5f9; flex-shrink: 0;">
										<?php echo esc_html( $cat_count_fa ); ?> کالا
									</span>
								</div>
							<?php endforeach; ?>

							<!-- Empty Search Results -->
							<div id="singo-cat-no-results" style="display: none; text-align: center; padding: 20px 10px; color: #94a3b8; font-size: 12.5px;">
								دسته‌بندی با این عنوان یافت نشد.
							</div>

						</div>
					</div>
				</div>
			</div>

			<!-- Download Action Button -->
			<div class="singo-customer-download-wrap" style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: <?php echo $history_enabled ? '32px' : '0'; ?>;">
				<button type="button" id="singo-customer-download-btn" style="display: inline-flex; align-items: center; justify-content: center; gap: 10px; background: <?php echo esc_attr( $primary_color ); ?>; color: #ffffff; border: none; font-size: 14.5px; font-weight: 700; padding: 12px 28px; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px <?php echo esc_attr( $btn_shadow_tint ); ?>; font-family: inherit !important; margin: 0 !important; line-height: 1.4 !important;">
					<svg id="singo-customer-btn-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
						<polyline points="7 10 12 15 17 10"></polyline>
						<line x1="12" y1="15" x2="12" y2="3"></line>
					</svg>
					<span id="singo-customer-btn-text">مشاهده و دریافت لیست قیمت (PDF)</span>
				</button>
			</div>

			<!-- Customer Download History Section -->
			<?php if ( $history_enabled ) : ?>
				<div class="singo-customer-history-section" style="border-top: 1px solid #f1f5f9; padding-top: 24px; margin-top: 24px;">
					<div class="singo-history-header-wrap" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; flex-wrap: wrap;">
						<div style="display: flex; align-items: center; gap: 8px;">
							<span style="width: 28px; height: 28px; border-radius: 7px; background: <?php echo esc_attr( $icon_bg_tint ); ?>; color: <?php echo esc_attr( $primary_color ); ?>; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr( $primary_color ); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
									<circle cx="12" cy="12" r="10"></circle>
									<polyline points="12 6 12 12 16 14"></polyline>
								</svg>
							</span>
							<h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #1e293b;">تاریخچه دانلودهای اخیر شما</h4>
						</div>
						<span style="font-size: 11.5px; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; display: inline-block;">
								<circle cx="12" cy="12" r="10"></circle>
								<line x1="12" y1="16" x2="12" y2="12"></line>
								<line x1="12" y1="8" x2="12.01" y2="8"></line>
							</svg>
							<span>فایل‌ها تا <?php echo esc_html( $retention_days_fa ); ?> روز پس از دریافت با همان قیمت‌های ثبت‌شده قابل دانلود هستند.</span>
						</span>
					</div>

					<div id="singo-customer-history-container">
						<?php self::render_history_items_html( $history_items, $endpoint_url, $nonce ); ?>
					</div>
				</div>
			<?php endif; ?>

			<style>
				#singo-customer-card #singo-customer-download-btn:hover {
					filter: brightness(0.92);
					transform: translateY(-1px);
					box-shadow: 0 6px 16px <?php echo esc_attr( $btn_shadow_tint ); ?> !important;
				}
				.singo-cat-picker-trigger:hover,
				.singo-cat-picker-trigger:focus {
					border-color: <?php echo esc_attr( $primary_color ); ?> !important;
					outline: none !important;
					box-shadow: 0 0 0 3px <?php echo esc_attr( $focus_ring_tint ); ?> !important;
				}
				.singo-cat-option:hover {
					background-color: #f1f5f9 !important;
				}

				/* History Rows */
				.singo-history-item-row {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 12px;
					background: #ffffff;
					border: 1px solid #e2e8f0;
					border-radius: 8px;
					padding: 12px 16px;
					transition: all 0.15s ease;
				}
				.singo-history-item-row:hover {
					border-color: #cbd5e1 !important;
					box-shadow: 0 2px 6px rgba(0,0,0,0.04);
				}
				.singo-history-item-info {
					display: flex;
					align-items: center;
					gap: 12px;
					flex: 1;
					min-width: 0;
				}
				.singo-history-item-dot {
					width: 10px;
					height: 10px;
					border-radius: 50%;
					display: inline-block;
					flex-shrink: 0;
					box-shadow: 0 0 0 2px rgba(0,0,0,0.06);
				}
				.singo-history-item-actions {
					display: flex;
					align-items: center;
					gap: 8px;
					flex-shrink: 0;
				}
				.singo-history-redownload-btn {
					display: inline-flex !important;
					align-items: center !important;
					gap: 6px !important;
					background: #f0fdf4 !important;
					color: #166534 !important;
					border: 1px solid #bbf7d0 !important;
					padding: 6px 14px !important;
					border-radius: 6px !important;
					font-size: 12px !important;
					font-weight: 700 !important;
					text-decoration: none !important;
					transition: all 0.15s ease !important;
					line-height: 1.4 !important;
				}
				.singo-history-redownload-btn:hover {
					background: #dcfce7 !important;
					color: #15803d !important;
					transform: translateY(-1px);
				}
				.singo-history-delete-btn {
					padding: 0 !important;
					margin: 0 !important;
					width: 32px !important;
					height: 32px !important;
					min-width: 32px !important;
					border-radius: 6px !important;
					background: #fff1f2 !important;
					border: 1px solid #fecdd3 !important;
					color: #e11d48 !important;
					display: inline-flex !important;
					align-items: center !important;
					justify-content: center !important;
					cursor: pointer !important;
					transition: all 0.15s ease !important;
					box-shadow: none !important;
					line-height: 1 !important;
				}
				.singo-history-delete-btn:hover {
					background: #ffe4e6 !important;
					border-color: #fda4af !important;
					color: #be123c !important;
					transform: scale(1.05);
				}

				/* Mobile Responsiveness */
				@media (max-width: 640px) {
					#singo-customer-card {
						padding: 16px 14px !important;
						border-radius: 10px !important;
					}
					.singo-cat-picker-box {
						padding: 14px 12px !important;
						margin-bottom: 18px !important;
					}
					#singo-customer-download-btn {
						width: 100% !important;
						justify-content: center !important;
						padding: 12px 16px !important;
						font-size: 13.5px !important;
					}
					.singo-history-header-wrap {
						flex-direction: column !important;
						align-items: stretch !important;
						gap: 10px !important;
					}
					.singo-history-item-row {
						flex-direction: column !important;
						align-items: stretch !important;
						gap: 12px !important;
						padding: 14px 12px !important;
					}
					.singo-history-item-actions {
						width: 100% !important;
						border-top: 1px dashed #f1f5f9;
						padding-top: 10px;
					}
					.singo-history-redownload-btn {
						flex: 1 !important;
						justify-content: center !important;
						min-height: 36px !important;
						font-size: 12.5px !important;
					}
					.singo-history-delete-btn {
						width: 36px !important;
						height: 36px !important;
						min-width: 36px !important;
					}
					.singo-cat-picker-dropdown {
						max-height: min(300px, 55dvh) !important;
					}
				}
			</style>

			<script>
				(function() {
					var card        = document.getElementById('singo-customer-card');
					var hiddenInput = document.getElementById('singo-customer-cat-select');
					var btn         = document.getElementById('singo-customer-download-btn');
					var trigger     = document.getElementById('singo-cat-picker-trigger');
					var dropdown    = document.getElementById('singo-cat-picker-dropdown');
					var searchInput = document.getElementById('singo-cat-search-input');
					var searchClear = document.getElementById('singo-cat-search-clear');
					var optionsList = document.getElementById('singo-cat-options-list');
					var chevron     = document.getElementById('singo-cat-chevron');
					var selectedDot = document.getElementById('singo-cat-selected-dot');
					var selectedTxt = document.getElementById('singo-cat-selected-text');
					var selectedBdg = document.getElementById('singo-cat-selected-badge');
					var noResults   = document.getElementById('singo-cat-no-results');
					var historyBox  = document.getElementById('singo-customer-history-container');

					if (!card || !btn || !trigger || !dropdown) return;

					var endpointUrl = card.getAttribute('data-endpoint') || '';
					var security    = card.getAttribute('data-security') || '';
					var ajaxUrl     = card.getAttribute('data-ajax-url') || '';
					var primaryColor = card.getAttribute('data-primary') || '';

					// Toggle Custom Dropdown
					function openDropdown() {
						dropdown.style.display = 'flex';
						trigger.setAttribute('aria-expanded', 'true');
						if (chevron) chevron.style.transform = 'rotate(180deg)';
						if (searchInput) {
							searchInput.focus();
						}
					}

					function closeDropdown() {
						dropdown.style.display = 'none';
						trigger.setAttribute('aria-expanded', 'false');
						if (chevron) chevron.style.transform = 'rotate(0deg)';
					}

					trigger.addEventListener('click', function(e) {
						e.stopPropagation();
						if (dropdown.style.display === 'flex') {
							closeDropdown();
						} else {
							openDropdown();
						}
					});

					// Close on outside click
					document.addEventListener('click', function(e) {
						if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
							closeDropdown();
						}
					});

					// Escape key close
					document.addEventListener('keydown', function(e) {
						if (e.key === 'Escape') {
							closeDropdown();
						}
					});

					// Live Search Filter
					if (searchInput) {
						searchInput.addEventListener('input', function() {
							var query = this.value.trim().toLowerCase();
							var options = optionsList.querySelectorAll('.singo-cat-option');
							var visibleCount = 0;

							if (searchClear) {
								searchClear.style.display = query ? 'inline-block' : 'none';
							}

							options.forEach(function(opt) {
								var name = (opt.getAttribute('data-name') || '').toLowerCase();
								var path = (opt.getAttribute('data-path') || '').toLowerCase();
								if (!query || name.indexOf(query) !== -1 || path.indexOf(query) !== -1) {
									opt.style.display = 'flex';
									visibleCount++;
								} else {
									opt.style.display = 'none';
								}
							});

							if (noResults) {
								noResults.style.display = (visibleCount === 0) ? 'block' : 'none';
							}
						});

						if (searchClear) {
							searchClear.addEventListener('click', function() {
								searchInput.value = '';
								searchInput.dispatchEvent(new Event('input'));
								searchInput.focus();
							});
						}
					}

					// Option Selection
					optionsList.addEventListener('click', function(e) {
						var opt = e.target.closest('.singo-cat-option');
						if (!opt) return;

						var id    = opt.getAttribute('data-id') || '0';
						var name  = opt.getAttribute('data-name') || '';
						var count = opt.getAttribute('data-count') || '';
						var color = opt.getAttribute('data-color') || primaryColor;

						hiddenInput.value = id;
						selectedTxt.textContent = name;
						selectedBdg.textContent = count + ' کالا';
						selectedDot.style.background = color;

						optionsList.querySelectorAll('.singo-cat-option').forEach(function(el) {
							el.classList.remove('singo-cat-option--active');
							el.style.backgroundColor = '';
						});
						opt.classList.add('singo-cat-option--active');
						opt.style.backgroundColor = 'rgba(173, 97, 65, 0.08)';

						closeDropdown();
					});

					// Refresh History via AJAX
					function refreshHistory() {
						if (!historyBox || !ajaxUrl) return;
						var formData = new FormData();
						formData.append('action', 'singo_get_customer_download_history');
						formData.append('security', security);

						fetch(ajaxUrl, {
							method: 'POST',
							body: formData,
						})
						.then(function(res) { return res.json(); })
						.then(function(resp) {
							if (resp && resp.success && resp.data && resp.data.html) {
								historyBox.innerHTML = resp.data.html;
								attachDeleteHandlers();
							}
						})
						.catch(function(err) {});
					}

					// Delete individual history item
					function attachDeleteHandlers() {
						if (!historyBox) return;
						var delBtns = historyBox.querySelectorAll('.singo-history-delete-btn');
						delBtns.forEach(function(btn) {
							btn.onclick = function(e) {
								e.preventDefault();
								var histId = this.getAttribute('data-id');
								if (!histId || !confirm('آیا از حذف این مورد از تاریخچه اطمینان دارید؟')) return;

								var row = document.getElementById('singo-history-item-' + histId);
								if (row) {
									row.style.opacity = '0.5';
									row.style.pointerEvents = 'none';
								}

								var formData = new FormData();
								formData.append('action', 'singo_delete_customer_history_item');
								formData.append('security', security);
								formData.append('history_id', histId);

								fetch(ajaxUrl, {
									method: 'POST',
									body: formData,
								})
								.then(function(res) { return res.json(); })
								.then(function(resp) {
									if (resp && resp.success) {
										refreshHistory();
									} else {
										alert(resp && resp.data && resp.data.message ? resp.data.message : 'خطا در حذف رکورد.');
										if (row) {
											row.style.opacity = '1';
											row.style.pointerEvents = 'auto';
										}
									}
								})
								.catch(function() {
									if (row) {
										row.style.opacity = '1';
										row.style.pointerEvents = 'auto';
									}
								});
							};
						});
					}
					attachDeleteHandlers();

					// Main Download Button Click
					btn.addEventListener('click', function(e) {
						e.preventDefault();

						var catId = hiddenInput ? hiddenInput.value : '0';

						// Build URL using native URL object to guarantee zero HTML entities
						var targetUrl;
						try {
							targetUrl = new URL(endpointUrl, window.location.href);
						} catch (err) {
							targetUrl = new URL(window.location.href);
						}

						targetUrl.searchParams.set('singo_download_pdf', '1');
						targetUrl.searchParams.set('action', 'singo_export_price_list_pdf');
						targetUrl.searchParams.set('security', security);
						targetUrl.searchParams.set('customer_export', '1');
						targetUrl.searchParams.set('autoprint', '1');

						if (primaryColor) {
							targetUrl.searchParams.set('primary_color', primaryColor);
						}

						if (catId && catId !== '0') {
							targetUrl.searchParams.set('category_id', catId);
						} else {
							targetUrl.searchParams.delete('category_id');
						}

						window.open(targetUrl.toString(), '_blank');

						// Auto-refresh history list after 1.5s
						setTimeout(refreshHistory, 1600);
					});

					// Auto-download handler if redirected from Off-Canvas after login
					(function checkAutoDownload() {
						var urlParams = new URLSearchParams(window.location.search);
						var autoDown = urlParams.get('singo_auto_download') || urlParams.get('auto_download');
						var catId = urlParams.get('cat_id') || urlParams.get('category_id');

						if (!catId) {
							try {
								var p = sessionStorage.getItem('singo_pending_pdf_download');
								if (p) {
									var pd = JSON.parse(p);
									if (pd && pd.category_id !== undefined) {
										catId = pd.category_id;
										autoDown = '1';
									}
								}
							} catch(e) {}
						}

						if (!catId) {
							var match = document.cookie.match(/(?:^|;\s*)singo_pending_cat=([^;]+)/);
							if (match) {
								catId = decodeURIComponent(match[1]);
								autoDown = '1';
							}
						}

						if (autoDown && catId !== null && catId !== undefined) {
							// Clear storages so it never repeats
							try { sessionStorage.removeItem('singo_pending_pdf_download'); } catch(e) {}
							document.cookie = 'singo_pending_cat=; path=/; max-age=0;';

							// Clean URL params cleanly without reloading
							try {
								var cleanUrl = new URL(window.location.href);
								cleanUrl.searchParams.delete('singo_auto_download');
								cleanUrl.searchParams.delete('auto_download');
								cleanUrl.searchParams.delete('cat_id');
								cleanUrl.searchParams.delete('category_id');
								window.history.replaceState({}, document.title, cleanUrl.toString());
							} catch(e) {}

							// Select the category in UI
							var targetOpt = optionsList ? optionsList.querySelector('.singo-cat-option[data-id="' + catId + '"]') : null;
							if (targetOpt) {
								targetOpt.click();
							} else if (hiddenInput) {
								hiddenInput.value = catId;
							}

							// Trigger the download automatically
							setTimeout(function() {
								if (btn) {
									btn.click();
								}
							}, 500);
						}
					})();
				})();
			</script>
		</div>
		<?php
	}

	/**
	 * Adjust hex color brightness for hover states.
	 */
	public static function adjust_hex_brightness( string $hex, int $steps ): string {
		$steps = max( -255, min( 255, $steps ) );
		$clean_hex = ltrim( trim( $hex ), '#' );
		if ( 3 === strlen( $clean_hex ) ) {
			$clean_hex = $clean_hex[0] . $clean_hex[0] . $clean_hex[1] . $clean_hex[1] . $clean_hex[2] . $clean_hex[2];
		}
		if ( 6 !== strlen( $clean_hex ) || ! ctype_xdigit( $clean_hex ) ) {
			return '#8f4b2f';
		}
		$r = hexdec( substr( $clean_hex, 0, 2 ) );
		$g = hexdec( substr( $clean_hex, 2, 2 ) );
		$b = hexdec( substr( $clean_hex, 4, 2 ) );

		$r = max( 0, min( 255, $r + $steps ) );
		$g = max( 0, min( 255, $g + $steps ) );
		$b = max( 0, min( 255, $b + $steps ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Enqueue Frontend Off-Canvas drawer assets.
	 */
	public function enqueue_frontend_offcanvas_assets(): void {
		if ( is_admin() || ! self::is_offcanvas_enabled() ) {
			return;
		}

		$primary_color = self::get_active_primary_color();
		if ( function_exists( 'sanitize_hex_color' ) ) {
			$clean = sanitize_hex_color( $primary_color );
			if ( ! empty( $clean ) ) {
				$primary_color = $clean;
			}
		}

		wp_enqueue_style(
			'singo-price-list-offcanvas',
			SINGO_TOOLS_URL . 'assets/css/singo-price-list-offcanvas.css',
			array(),
			SINGO_TOOLS_VERSION
		);

		// Dynamic CSS variable injection for brand color and critical layout
		$inline_css = "
			:root {
				--singo-offcanvas-primary: " . esc_attr( $primary_color ) . " !important;
				--singo-offcanvas-primary-hover: " . esc_attr( self::adjust_hex_brightness( $primary_color, -20 ) ) . " !important;
				--singo-offcanvas-bg-tint: " . esc_attr( self::hex_to_rgba( $primary_color, 0.12 ) ) . " !important;
			}
			#singo-price-list-backdrop {
				position: fixed !important;
				inset: 0 !important;
				background: rgba(15, 23, 42, 0.6) !important;
				backdrop-filter: blur(4px) !important;
				-webkit-backdrop-filter: blur(4px) !important;
				z-index: 999998 !important;
				opacity: 0 !important;
				visibility: hidden !important;
				transition: opacity 0.25s ease, visibility 0.25s ease !important;
			}
			#singo-price-list-backdrop.singo-active {
				opacity: 1 !important;
				visibility: visible !important;
			}
			#singo-price-list-offcanvas {
				position: fixed !important;
				top: 0 !important;
				bottom: 0 !important;
				right: 0 !important;
				width: 440px !important;
				max-width: 92vw !important;
				height: 100% !important;
				background: #ffffff !important;
				box-shadow: -10px 0 30px rgba(0, 0, 0, 0.18) !important;
				z-index: 999999 !important;
				display: flex !important;
				flex-direction: column !important;
				transform: translateX(100%) !important;
				transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
				visibility: hidden !important;
				direction: rtl !important;
				font-family: inherit !important;
			}
			#singo-price-list-offcanvas.singo-active {
				transform: translateX(0) !important;
				visibility: visible !important;
			}
			body.singo-offcanvas-open {
				overflow: hidden !important;
			}
			.singo-offcanvas-cat-trigger {
				background: " . esc_attr( $primary_color ) . " !important;
				color: #ffffff !important;
			}
			.singo-offcanvas-cat-label {
				color: #ffffff !important;
			}
			.singo-offcanvas-cat-count-badge {
				background: #ffffff !important;
				color: " . esc_attr( $primary_color ) . " !important;
			}
		";
		wp_add_inline_style( 'singo-price-list-offcanvas', $inline_css );

		wp_enqueue_script(
			'singo-price-list-offcanvas',
			SINGO_TOOLS_URL . 'assets/js/singo-price-list-offcanvas.js',
			array(),
			SINGO_TOOLS_VERSION,
			true
		);

		$user_id       = (int) get_current_user_id();
		$is_restricted = $user_id > 0 && self::is_user_restricted( $user_id );
		$restrict_info = $is_restricted ? self::get_user_restriction_info( $user_id ) : array();

		wp_localize_script( 'singo-price-list-offcanvas', 'singoOffcanvasData', array(
			'ajax_url'               => admin_url( 'admin-ajax.php' ),
			'security'               => wp_create_nonce( 'singo_customer_price_list_nonce' ),
			'is_logged_in'           => is_user_logged_in(),
			'is_restricted'          => $is_restricted,
			'restriction_reason'     => $restrict_info['reason'] ?? '',
			'download_base_url'      => home_url( '/' ),
			'login_url'              => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url(),
			'price_list_account_url' => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'price-list' ) : home_url( '/my-account/price-list/' ),
			'primary_color'          => $primary_color,
			'custom_selector'        => (string) self::get_setting( 'offcanvas_trigger_selector', '' ),
		) );
	}

	/**
	 * Render Frontend Off-Canvas Price List Drawer in footer.
	 */
	public function render_frontend_offcanvas(): void {
		if ( is_admin() || ! self::is_offcanvas_enabled() ) {
			return;
		}

		$primary_color = self::get_active_primary_color();
		if ( function_exists( 'sanitize_hex_color' ) ) {
			$clean = sanitize_hex_color( $primary_color );
			if ( ! empty( $clean ) ) {
				$primary_color = $clean;
			}
		}

		$title = (string) self::get_setting( 'offcanvas_title', 'دریافت و چاپ لیست قیمت' );
		if ( empty( $title ) ) {
			$title = 'دریافت و چاپ لیست قیمت';
		}
		$desc = (string) self::get_setting( 'offcanvas_desc', 'جهت دریافت جدیدترین کاتالوگ و لیست قیمت رسمی، دسته‌بندی مورد نظر خود را انتخاب کرده و دکمه چاپ را بزنید.' );

		$categories_tree = self::get_hierarchical_category_tree();
		$total_products  = 0;
		foreach ( $categories_tree as $c ) {
			if ( 0 === $c['level'] ) {
				$total_products += $c['count'];
			}
		}
		if ( $total_products <= 0 ) {
			$counts         = wp_count_posts( 'product' );
			$total_products = (int) ( $counts->publish ?? 0 );
		}
		$total_products_fa = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $total_products ) : (string) $total_products;

		$shamsi_date = wp_date( 'Y/m/d' );
		if ( class_exists( 'Singo_Shamsi_Converter' ) ) {
			$shamsi_date = Singo_Shamsi_Converter::format_date( time(), 'l، j F Y' );
		}

		$is_logged_in  = is_user_logged_in();
		$current_user  = wp_get_current_user();
		$user_id       = (int) ( $current_user->ID ?? 0 );
		$is_restricted = $user_id > 0 && self::is_user_restricted( $user_id );
		$restrict_info = $is_restricted ? self::get_user_restriction_info( $user_id ) : array();
		$user_phone    = $user_id > 0 ? self::get_user_phone_number( $user_id ) : '';
		$user_display  = $user_id > 0 ? ( ! empty( $current_user->display_name ) ? $current_user->display_name : $current_user->user_login ) : '';

		?>
		<!-- Singo Tools Price List Off-Canvas Backdrop -->
		<div id="singo-price-list-backdrop" aria-hidden="true"></div>

		<!-- Singo Tools Price List Off-Canvas Drawer -->
		<div id="singo-price-list-offcanvas" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="singo-offcanvas-title">
			<!-- Header -->
			<div class="singo-offcanvas-header">
				<div class="singo-offcanvas-title-wrap">
					<div class="singo-offcanvas-title-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
							<polyline points="14 2 14 8 20 8"></polyline>
							<line x1="16" y1="13" x2="8" y2="13"></line>
							<line x1="16" y1="17" x2="8" y2="17"></line>
							<polyline points="10 9 9 9 8 9"></polyline>
						</svg>
					</div>
					<div class="singo-offcanvas-title-texts">
						<h3 id="singo-offcanvas-title"><?php echo esc_html( $title ); ?></h3>
						<span>
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
								<line x1="16" y1="2" x2="16" y2="6"></line>
								<line x1="8" y1="2" x2="8" y2="6"></line>
								<line x1="3" y1="10" x2="21" y2="10"></line>
							</svg>
							<?php echo esc_html( $shamsi_date ); ?>
						</span>
					</div>
				</div>
				<button type="button" class="singo-offcanvas-close-btn" aria-label="بستن پنجره">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>

			<!-- Body -->
			<div class="singo-offcanvas-body">
				<!-- Description -->
				<?php if ( ! empty( $desc ) ) : ?>
					<div class="singo-offcanvas-intro-box">
						<?php echo nl2br( esc_html( $desc ) ); ?>
					</div>
				<?php endif; ?>

				<!-- User Restriction Banner (Blocked accounts only) -->
				<?php if ( $is_restricted ) : ?>
					<div class="singo-offcanvas-status-banner singo-restricted">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
							<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
						</svg>
						<div>
							<strong>دسترسی دریافت لیست قیمت محدود شده است</strong>
							<p style="margin: 4px 0 0 0; font-size: 11.5px; opacity: 0.9;">
								امکان دریافت کاتالوگ و لیست قیمت برای حساب کاربری شما محدود می‌باشد. در صورت نیاز با پشتیبانی تماس حاصل فرمایید.
								<?php if ( ! empty( $restrict_info['reason'] ) ) : ?>
									<br><span style="display:inline-block; margin-top:4px; font-weight:700;">دلیل: <?php echo esc_html( $restrict_info['reason'] ); ?></span>
								<?php endif; ?>
							</p>
						</div>
					</div>
				<?php endif; ?>

				<!-- Category Picker -->
				<div class="singo-offcanvas-cat-section">
					<div class="singo-offcanvas-section-title">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
							<polyline points="2 17 12 22 22 17"></polyline>
							<polyline points="2 12 12 17 22 12"></polyline>
						</svg>
						<span>انتخاب دسته‌بندی محصولات:</span>
					</div>

					<div class="singo-offcanvas-cat-wrap">
						<input type="hidden" id="singo-offcanvas-cat-id" value="0" />

						<button type="button" id="singo-offcanvas-cat-trigger" class="singo-offcanvas-cat-trigger" aria-haspopup="listbox" aria-expanded="false">
							<div class="singo-offcanvas-cat-trigger-content">
								<span id="singo-offcanvas-selected-dot" class="singo-offcanvas-cat-dot" style="background: <?php echo esc_attr( $primary_color ); ?>;"></span>
								<span id="singo-offcanvas-selected-text" class="singo-offcanvas-cat-label">تمامی محصولات و دسته‌بندی‌ها</span>
								<span id="singo-offcanvas-selected-badge" class="singo-offcanvas-cat-count-badge"><?php echo esc_html( $total_products_fa ); ?> کالا</span>
							</div>
							<svg class="singo-offcanvas-cat-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="6 9 12 15 18 9"></polyline>
							</svg>
						</button>

						<div id="singo-offcanvas-cat-dropdown" class="singo-offcanvas-cat-dropdown">
							<div class="singo-offcanvas-cat-search-box">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="11" cy="11" r="8"></circle>
									<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
								</svg>
								<input type="text" id="singo-offcanvas-cat-search" placeholder="جستجوی دسته‌بندی..." autocomplete="off" />
							</div>

							<div class="singo-offcanvas-cat-list">
								<!-- All Products Option -->
								<div class="singo-offcanvas-cat-item singo-cat-item--active" data-id="0" data-name="تمامی محصولات و دسته‌بندی‌ها" data-count="<?php echo esc_attr( $total_products_fa ); ?>" data-color="<?php echo esc_attr( $primary_color ); ?>">
									<div style="display: flex; align-items: center; gap: 8px;">
										<span style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo esc_attr( $primary_color ); ?>; display: inline-block;"></span>
										<span style="font-size: 13px; font-weight: 700; color: #0f172a;">تمامی محصولات و دسته‌بندی‌ها</span>
									</div>
									<span style="font-size: 11px; color: #64748b; background: #f8fafc; padding: 2px 7px; border-radius: 10px; font-weight: 600; border: 1px solid #f1f5f9;">
										<?php echo esc_html( $total_products_fa ); ?> کالا
									</span>
								</div>

								<!-- Tree Options -->
								<?php foreach ( $categories_tree as $cat ) :
									$cat_id       = (int) $cat['id'];
									$cat_name     = (string) $cat['name'];
									$cat_level    = (int) $cat['level'];
									$cat_count    = (int) $cat['count'];
									$cat_color    = (string) $cat['color'];
									$cat_count_fa = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) $cat_count ) : (string) $cat_count;
									$indent_px    = ( $cat_level * 16 );
									$is_parent    = ( 0 === $cat_level );
									?>
									<div class="singo-offcanvas-cat-item"
									     data-id="<?php echo esc_attr( (string) $cat_id ); ?>"
									     data-name="<?php echo esc_attr( $cat_name ); ?>"
									     data-count="<?php echo esc_attr( $cat_count_fa ); ?>"
									     data-color="<?php echo esc_attr( $cat_color ); ?>"
									     style="padding-right: <?php echo esc_attr( (string) ( 12 + $indent_px ) ); ?>px;">
										<div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
											<?php if ( $cat_level > 0 ) : ?>
												<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; transform: scaleX(-1);">
													<polyline points="9 10 4 15 9 20"></polyline>
													<path d="M20 4v7a4 4 0 0 1-4 4H4"></path>
												</svg>
											<?php endif; ?>
											<span style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo esc_attr( $cat_color ); ?>; display: inline-block; flex-shrink: 0;"></span>
											<span style="font-size: 13px; color: <?php echo $is_parent ? '#0f172a' : '#334155'; ?>; font-weight: <?php echo $is_parent ? '700' : '500'; ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
												<?php echo esc_html( $cat_name ); ?>
											</span>
										</div>
										<span style="font-size: 11px; color: #64748b; background: #f8fafc; padding: 2px 7px; border-radius: 10px; font-weight: 600; border: 1px solid #f1f5f9; flex-shrink: 0;">
											<?php echo esc_html( $cat_count_fa ); ?> کالا
										</span>
									</div>
								<?php endforeach; ?>

								<div id="singo-offcanvas-cat-no-result" style="display: none; text-align: center; padding: 16px 10px; color: #94a3b8; font-size: 12.5px;">
									دسته‌بندی با این عنوان یافت نشد.
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Toast Notification inside drawer -->
				<div id="singo-offcanvas-toast" class="singo-offcanvas-toast"></div>
			</div>

			<!-- Footer Action -->
			<div class="singo-offcanvas-footer">
				<button type="button" id="singo-offcanvas-submit-btn" class="singo-offcanvas-submit-btn" <?php echo $is_restricted ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
					<span class="singo-offcanvas-spinner"></span>
					<svg class="singo-offcanvas-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="6 9 6 2 18 2 18 9"></polyline>
						<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
						<rect x="6" y="14" width="12" height="8"></rect>
					</svg>
					<span>چاپ و دریافت لیست قیمت (PDF)</span>
				</button>
			</div>
		</div>

		<!-- Instant click handler immune to Perfmatters script delay -->
		<script id="singo-offcanvas-instant-init">
		(function() {
			function instantOpen(e) {
				// Guard: Never hijack WooCommerce My Account dashboard or navigation links
				if (e.target && e.target.closest && e.target.closest('.woocommerce-MyAccount-navigation, .woocommerce-MyAccount-content')) {
					return;
				}

				var t = e.target && e.target.closest ? e.target.closest('.menu-item-4883 a, .singo-price-list-trigger, a[href="#price-list"], a[href="#singo-price-list"]') : null;
				if (!t) return;
				e.preventDefault();
				e.stopPropagation();

				// Trigger Perfmatters script load if delayed
				if (window.perfmatters && typeof window.perfmatters.loadAll === 'function') {
					try { window.perfmatters.loadAll(); } catch(err) {}
				}

				// Un-delay stylesheet if delayed
				var l = document.getElementById('singo-price-list-offcanvas-css');
				if (l && l.getAttribute('data-pmdelayedstyle') && !l.getAttribute('href')) {
					l.setAttribute('href', l.getAttribute('data-pmdelayedstyle'));
				}

				var d = document.getElementById('singo-price-list-offcanvas');
				var b = document.getElementById('singo-price-list-backdrop');
				if (d) {
					d.classList.add('singo-active');
					d.setAttribute('aria-hidden', 'false');
				}
				if (b) {
					b.classList.add('singo-active');
				}
				document.body.classList.add('singo-offcanvas-open');
			}
			document.addEventListener('click', instantOpen, true);
		})();
		</script>
		<?php
	}

	/**
	 * Render the PDF template.
	 */
	public function render_pdf_template(): void {
		// Suppress caching and third-party output buffer mutators (WP Rocket, Perfmatters, WordPress native lazy-load)
		if ( ! defined( 'DONOTROCKETOPTIMIZE' ) ) {
			define( 'DONOTROCKETOPTIMIZE', true );
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		add_filter( 'perfmatters_allow_buffer', '__return_false', 9999 );
		add_filter( 'perfmatters_lazyload', '__return_false', 9999 );
		add_filter( 'do_rocket_lazyload', '__return_false', 9999 );
		add_filter( 'wp_lazy_loading_enabled', '__return_false', 9999 );
		remove_all_filters( 'perfmatters_output_buffer' );

		$can_manage          = function_exists( 'singo_tools_current_user_can_manage' ) && singo_tools_current_user_can_manage();
		$is_customer_allowed = ( self::is_my_account_enabled() || self::is_offcanvas_enabled() ) && is_user_logged_in();

		// Check permission
		$preview_token       = isset( $_REQUEST['preview_token'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['preview_token'] ) ) : '';
		$is_preview          = ( 'singo_preview_2026' === $preview_token );

		if ( ! $can_manage && ! $is_customer_allowed && ! $is_preview ) {
			wp_die( 'برای دریافت لیست قیمت، لطفاً ابتدا وارد حساب کاربری خود شوید.', 'خطای دسترسی', array( 'response' => 403 ) );
		}

		// User Restriction Check: Block restricted customer accounts
		if ( ! $can_manage && is_user_logged_in() && self::is_user_restricted( (int) get_current_user_id() ) ) {
			wp_die( 'دسترسی حساب کاربری شما برای دریافت و دانلود لیست قیمت مسدود شده است. لطفاً با پشتیبانی فروشگاه تماس حاصل فرمایید.', 'دسترسی مسدود شده', array( 'response' => 403 ) );
		}

		// Verify nonce securely across popups, iframes, and new tabs without HTTP referer drop failure
		$nonce = '';
		if ( isset( $_REQUEST['security'] ) ) {
			$nonce = (string) $_REQUEST['security'];
		} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = (string) $_REQUEST['_wpnonce'];
		} elseif ( isset( $_REQUEST['#038;security'] ) ) {
			$nonce = (string) $_REQUEST['#038;security'];
		} elseif ( isset( $_REQUEST['amp;security'] ) ) {
			$nonce = (string) $_REQUEST['amp;security'];
		}
		$nonce = sanitize_text_field( wp_unslash( $nonce ) );

		$valid_nonce = false;
		if ( ! empty( $nonce ) ) {
			$valid_nonce = wp_verify_nonce( $nonce, 'singo_customer_price_list_nonce' )
			            || wp_verify_nonce( $nonce, 'singo_export_price_list_nonce' )
			            || wp_verify_nonce( $nonce, 'singo_price_list_nonce' );

			// Self-healing fallback: If user is logged in but page was cached for a guest (UID 0), verify against UID 0
			if ( ! $valid_nonce && is_user_logged_in() ) {
				$curr_uid = (int) get_current_user_id();
				wp_set_current_user( 0 );
				$valid_guest_nonce = wp_verify_nonce( $nonce, 'singo_customer_price_list_nonce' )
				                  || wp_verify_nonce( $nonce, 'singo_export_price_list_nonce' )
				                  || wp_verify_nonce( $nonce, 'singo_price_list_nonce' );
				wp_set_current_user( $curr_uid );
				if ( $valid_guest_nonce ) {
					$valid_nonce = true;
				}
			}
		}

		if ( ! $is_preview && ( empty( $nonce ) || ! $valid_nonce ) ) {
			wp_die( 'پیوند منقضی شده است یا نشست کاربری نامعتبر است. لطفاً صفحه را بارگذاری مجدد نموده و دوباره تلاش نمایید.', 'پیوند منقضی شده است', array( 'response' => 403 ) );
		}

		$template_path = SINGO_TOOLS_PATH . 'modules/price-list/views/pdf-template.php';
		if ( file_exists( $template_path ) ) {
			require $template_path;
			exit;
		} else {
			wp_die( 'فایل قالب پرینت یافت نشد.' );
		}
	}

	/**
	 * Safety-net fallback hook ensuring singo-price-list submenu is always registered in WP Admin.
	 */
	public function ensure_submenu_registered(): void {
		global $submenu;

		$is_registered = false;
		if ( isset( $submenu['singo-tools'] ) && is_array( $submenu['singo-tools'] ) ) {
			foreach ( $submenu['singo-tools'] as $item ) {
				if ( isset( $item[2] ) && 'singo-price-list' === $item[2] ) {
					$is_registered = true;
					break;
				}
			}
		}

		if ( ! $is_registered ) {
			$cap = function_exists( 'singo_tools_get_admin_capability' ) ? singo_tools_get_admin_capability() : 'manage_woocommerce';
			add_submenu_page(
				'singo-tools',
				'تنظیمات و خروجی لیست قیمت',
				'خروجی لیست قیمت',
				$cap,
				'singo-price-list',
				array( $this, 'render_admin_page' ),
				50
			);
		}
	}

	/**
	 * Direct render handler for singo-price-list Optionino page if opened via safety-net hook.
	 */
	public function render_admin_page(): void {
		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای مشاهده این برگه را ندارید.', 'singo-tools' ), 403 );
		}

		$this->setup_optionino_submenu();

		if ( class_exists( 'OPTNNO' ) ) {
			$config = \OPTNNO::$settings[ self::OPTION_KEY ] ?? array();
			if ( ! empty( $config ) ) {
				$optnno = new \OPTNNO();
				$optnno->page_content( $config );
				return;
			}
		}

		wp_die( esc_html__( 'خطا در بارگذاری فریم‌ورک تنظیمات.', 'singo-tools' ) );
	}

	/**
	 * AJAX Handler: Fetch Price List Preview.
	 */
	public function ajax_get_price_list_preview(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );

		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$category_id      = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$in_stock         = ! isset( $_POST['in_stock'] ) || '1' === (string) $_POST['in_stock'] || 'true' === (string) $_POST['in_stock'];
		$orderby          = isset( $_POST['orderby'] ) ? sanitize_key( (string) $_POST['orderby'] ) : 'title';
		$order            = isset( $_POST['order'] ) ? sanitize_key( (string) $_POST['order'] ) : 'ASC';
		$discount_percent = isset( $_POST['discount_percent'] ) ? max( 0.0, min( 100.0, floatval( $_POST['discount_percent'] ) ) ) : 0.0;
		$hide_strike      = isset( $_POST['hide_strike'] ) && ( '1' === (string) $_POST['hide_strike'] || 'true' === (string) $_POST['hide_strike'] );
		$expand_vars      = ! isset( $_POST['expand_variations'] ) || '1' === (string) $_POST['expand_variations'] || 'true' === (string) $_POST['expand_variations'];

		$products = $this->get_products_data( $category_id, $in_stock, $orderby, $order, $discount_percent, $hide_strike, $expand_vars, 30 );
		if ( count( $products ) > 30 ) {
			$products = array_slice( $products, 0, 30 );
		}

		ob_start();
		if ( empty( $products ) ) {
			echo '<tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;">هیچ محصولی با معیارهای انتخابی یافت نشد.</td></tr>';
		} else {
			foreach ( $products as $index => $prod ) {
				$row_num = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) ( $index + 1 ) ) : (string) ( $index + 1 );
				?>
				<tr>
					<td style="text-align: center; font-weight: bold; color: #64748b; padding: 10px 8px;"><?php echo esc_html( $row_num ); ?></td>
					<td style="text-align: center; padding: 6px;">
						<?php if ( ! empty( $prod['thumb_url'] ) ) : ?>
							<img src="<?php echo esc_url( $prod['thumb_url'] ); ?>" alt="<?php echo esc_attr( $prod['title'] ); ?>" loading="lazy" decoding="async" style="width: 44px; height: 44px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; vertical-align: middle;" />
						<?php else : ?>
							<span style="font-size: 11px; color: #94a3b8;">بدون تصویر</span>
						<?php endif; ?>
					</td>
					<td style="text-align: right; padding: 10px 14px;">
						<strong style="color: #0f172a; font-size: 13.5px;"><?php echo esc_html( $prod['title'] ); ?></strong>
						<?php if ( ! empty( $prod['short_desc'] ) ) : ?>
							<div style="font-size: 11px; color: #64748b; margin-top: 3px; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; max-height: 4.35em;">
								<?php echo esc_html( $prod['short_desc'] ); ?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $prod['sku'] ) ) : ?>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;">کد: <?php echo esc_html( class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( $prod['sku'] ) : $prod['sku'] ); ?></div>
						<?php endif; ?>
					</td>
					<td style="text-align: center; padding: 6px 8px; vertical-align: middle;">
						<?php if ( ! empty( $prod['brand_logo'] ) ) : ?>
							<img src="<?php echo esc_url( $prod['brand_logo'] ); ?>" alt="<?php echo esc_attr( $prod['brand_name'] ?? 'برند' ); ?>" style="max-width: 60px; max-height: 36px; object-fit: contain; vertical-align: middle; display: inline-block;" />
						<?php elseif ( ! empty( $prod['brand_name'] ) ) : ?>
							<span style="font-size: 11px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-block;">
								<?php echo esc_html( $prod['brand_name'] ); ?>
							</span>
						<?php else : ?>
							<span style="color: #cbd5e1; font-weight: bold;">-</span>
						<?php endif; ?>
					</td>
					<td class="singo-preview-price-cell" style="text-align: center; padding: 10px; font-weight: bold; color: <?php echo esc_attr( self::get_active_primary_color() ); ?>; white-space: nowrap;">
						<?php echo wp_kses_post( $prod['price_html'] ); ?>
					</td>
				</tr>
				<?php
			}
		}
		$html = ob_get_clean();

		wp_send_json_success( array(
			'count'      => count( $products ),
			'count_fa'   => class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) count( $products ) ) : (string) count( $products ),
			'is_capped'  => count( $products ) >= 30,
			'table_html' => $html,
		) );
	}

	/**
	 * AJAX Handler: Save Custom Font URL (Backwards compatibility).
	 */
	public function ajax_save_font_url(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );

		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$font_url = isset( $_POST['font_url'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['font_url'] ) ) : '';
		if ( ! empty( $font_url ) ) {
			$font_url = esc_url_raw( $font_url );
		}

		update_option( 'singo_price_list_font_url', $font_url );

		// Update in dedicated option array as well
		$opts = get_option( self::OPTION_KEY, array() );
		if ( is_array( $opts ) ) {
			$opts['pdf_font_url'] = $font_url;
			update_option( self::OPTION_KEY, $opts );
		}
		self::purge_settings_cache();

		wp_send_json_success( array(
			'message'  => 'آدرس فونت با موفقیت ذخیره شد.',
			'font_url' => $font_url,
		) );
	}

	/**
	 * AJAX Handler: Save Custom Brand/Primary Color (Backwards compatibility).
	 */
	public function ajax_save_primary_color(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );

		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$raw_color   = isset( $_POST['primary_color'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['primary_color'] ) ) : '';
		$clean_color = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $raw_color ) : null;
		if ( empty( $clean_color ) ) {
			$clean_color = '#ad6141';
		}

		update_option( 'singo_price_list_primary_color', $clean_color );

		// Update in dedicated option array as well
		$opts = get_option( self::OPTION_KEY, array() );
		if ( is_array( $opts ) ) {
			$opts['pdf_primary_color'] = $clean_color;
			update_option( self::OPTION_KEY, $opts );
		}
		self::purge_settings_cache();

		wp_send_json_success( array(
			'message'       => 'رنگ سازمانی با موفقیت ذخیره شد.',
			'primary_color' => $clean_color,
		) );
	}

	/**
	 * AJAX Handler: Save Category Colors, Pattern Icons, and Cover Images Settings.
	 */
	public function ajax_save_category_colors(): void {
		check_ajax_referer( 'singo_price_list_nonce', 'security' );

		if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'سطح دسترسی نامعتبر است.' ) );
		}

		$raw_options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw_options ) ) {
			$raw_options = array();
		}

		// Update enable_page_pattern
		if ( isset( $_POST['enable_page_pattern'] ) ) {
			$raw_options['enable_page_pattern'] = '1' === (string) $_POST['enable_page_pattern'] || 'true' === (string) $_POST['enable_page_pattern'];
		}

		// Update pattern_icon_circle
		if ( isset( $_POST['pattern_icon_circle'] ) ) {
			$raw_options['pattern_icon_circle'] = '1' === (string) $_POST['pattern_icon_circle'] || 'true' === (string) $_POST['pattern_icon_circle'];
		}

		// Update pattern_default_color
		if ( isset( $_POST['pattern_default_color'] ) ) {
			$def_color = sanitize_hex_color( wp_unslash( (string) $_POST['pattern_default_color'] ) );
			if ( ! empty( $def_color ) ) {
				$raw_options['pattern_default_color'] = $def_color;
			}
		}

		// Update pattern_default_icon
		if ( isset( $_POST['pattern_default_icon'] ) ) {
			$def_icon = esc_url_raw( wp_unslash( trim( (string) $_POST['pattern_default_icon'] ) ) );
			$raw_options['pattern_default_icon'] = $def_icon;
		}

		// Update category_colors
		$cleaned_colors = array();
		if ( isset( $_POST['category_colors'] ) && is_array( $_POST['category_colors'] ) ) {
			foreach ( $_POST['category_colors'] as $cat_id => $hex ) {
				$cat_id = absint( $cat_id );
				$hex    = sanitize_hex_color( wp_unslash( (string) $hex ) );
				if ( $cat_id > 0 && ! empty( $hex ) ) {
					$cleaned_colors[ $cat_id ] = $hex;
				}
			}
		}
		$raw_options['category_colors'] = $cleaned_colors;

		// Update category_pattern_icons
		$cleaned_icons = array();
		if ( isset( $_POST['category_pattern_icons'] ) && is_array( $_POST['category_pattern_icons'] ) ) {
			foreach ( $_POST['category_pattern_icons'] as $cat_id => $icon_url ) {
				$cat_id   = absint( $cat_id );
				$icon_url = esc_url_raw( wp_unslash( trim( (string) $icon_url ) ) );
				if ( $cat_id > 0 && ! empty( $icon_url ) ) {
					$cleaned_icons[ $cat_id ] = $icon_url;
				}
			}
		}
		$raw_options['category_pattern_icons'] = $cleaned_icons;

		// Update category_cover_images
		$cleaned_covers = array();
		if ( isset( $_POST['category_cover_images'] ) && is_array( $_POST['category_cover_images'] ) ) {
			foreach ( $_POST['category_cover_images'] as $cat_id => $cover_url ) {
				$cat_id    = absint( $cat_id );
				$cover_url = esc_url_raw( wp_unslash( trim( (string) $cover_url ) ) );
				if ( $cat_id > 0 && ! empty( $cover_url ) ) {
					$cleaned_covers[ $cat_id ] = $cover_url;
				}
			}
		}
		$raw_options['category_cover_images'] = $cleaned_covers;

		update_option( self::OPTION_KEY, $raw_options );
		self::purge_settings_cache();

		wp_send_json_success( array(
			'message' => 'تنظیمات رنگ پترن، آیکن پترن و کاور دسته‌بندی‌ها با موفقیت ذخیره شد.',
		) );
	}

	/**
	 * Get the resolved pattern color for a category (with hierarchical parent inheritance).
	 *
	 * @param int $category_id Category term ID.
	 * @return string Hex color code.
	 */
	public static function get_resolved_category_color( int $category_id ): string {
		$default_color = (string) self::get_setting( 'pattern_default_color', '' );
		if ( empty( $default_color ) ) {
			$default_color = self::get_active_primary_color();
		}
		if ( empty( $default_color ) ) {
			$default_color = '#ad6141';
		}

		if ( $category_id <= 0 ) {
			return $default_color;
		}

		$category_colors = (array) self::get_setting( 'category_colors', array() );

		// 1. Direct color for this category
		if ( ! empty( $category_colors[ $category_id ] ) ) {
			$direct = sanitize_hex_color( (string) $category_colors[ $category_id ] );
			if ( ! empty( $direct ) ) {
				return $direct;
			}
		}

		// 2. Check parent / ancestors
		$ancestor_ids = get_ancestors( $category_id, 'product_cat', 'taxonomy' );
		if ( ! empty( $ancestor_ids ) && is_array( $ancestor_ids ) ) {
			foreach ( $ancestor_ids as $ancestor_id ) {
				if ( ! empty( $category_colors[ $ancestor_id ] ) ) {
					$inherited = sanitize_hex_color( (string) $category_colors[ $ancestor_id ] );
					if ( ! empty( $inherited ) ) {
						return $inherited;
					}
				}
			}
		}

		return $default_color;
	}

	/**
	 * Get the resolved pattern icon URL for a category (with hierarchical parent inheritance).
	 *
	 * @param int $category_id Category term ID.
	 * @return string Icon URL or empty string.
	 */
	public static function get_resolved_category_pattern_icon( int $category_id ): string {
		$default_icon = trim( (string) self::get_setting( 'pattern_default_icon', '' ) );

		if ( $category_id <= 0 ) {
			return $default_icon;
		}

		$category_icons = (array) self::get_setting( 'category_pattern_icons', array() );

		// 1. Direct icon for this category
		if ( ! empty( $category_icons[ $category_id ] ) ) {
			$direct = esc_url_raw( trim( (string) $category_icons[ $category_id ] ) );
			if ( ! empty( $direct ) ) {
				return $direct;
			}
		}

		// 2. Check parent / ancestors
		$ancestor_ids = get_ancestors( $category_id, 'product_cat', 'taxonomy' );
		if ( ! empty( $ancestor_ids ) && is_array( $ancestor_ids ) ) {
			foreach ( $ancestor_ids as $ancestor_id ) {
				if ( ! empty( $category_icons[ $ancestor_id ] ) ) {
					$inherited = esc_url_raw( trim( (string) $category_icons[ $ancestor_id ] ) );
					if ( ! empty( $inherited ) ) {
						return $inherited;
					}
				}
			}
		}

		return $default_icon;
	}

	/**
	 * Get the resolved A4 cover image URL for a category (with hierarchical parent inheritance).
	 *
	 * @param int $category_id Category term ID.
	 * @return string Cover image URL or empty string.
	 */
	public static function get_resolved_category_cover_image( int $category_id ): string {
		$global_cover = trim( (string) self::get_setting( 'pdf_cover_image', '' ) );

		if ( $category_id <= 0 ) {
			return $global_cover;
		}

		$category_covers = (array) self::get_setting( 'category_cover_images', array() );

		// 1. Direct cover image for this category
		if ( ! empty( $category_covers[ $category_id ] ) ) {
			$direct = esc_url_raw( trim( (string) $category_covers[ $category_id ] ) );
			if ( ! empty( $direct ) ) {
				return $direct;
			}
		}

		// 2. Check parent / ancestors
		$ancestor_ids = get_ancestors( $category_id, 'product_cat', 'taxonomy' );
		if ( ! empty( $ancestor_ids ) && is_array( $ancestor_ids ) ) {
			foreach ( $ancestor_ids as $ancestor_id ) {
				if ( ! empty( $category_covers[ $ancestor_id ] ) ) {
					$inherited = esc_url_raw( trim( (string) $category_covers[ $ancestor_id ] ) );
					if ( ! empty( $inherited ) ) {
						return $inherited;
					}
				}
			}
		}

		return $global_cover;
	}

	/**
	 * Resolve a WordPress URL to a local filesystem path if it belongs to this site.
	 *
	 * @param string $url Asset URL.
	 * @return string Local filesystem path or empty string.
	 */
	public static function resolve_local_file_path( string $url ): string {
		$url = trim( $url );
		if ( empty( $url ) || str_starts_with( $url, 'data:' ) ) {
			return '';
		}

		$local_path = '';
		$uploads    = wp_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) && str_starts_with( $url, $uploads['baseurl'] ) ) {
			$rel        = substr( $url, strlen( $uploads['baseurl'] ) );
			$rel        = strtok( (string) $rel, '?' );
			$local_path = wp_normalize_path( $uploads['basedir'] . $rel );
		} elseif ( defined( 'SINGO_TOOLS_URL' ) && defined( 'SINGO_TOOLS_PATH' ) && str_starts_with( $url, SINGO_TOOLS_URL ) ) {
			$rel        = substr( $url, strlen( SINGO_TOOLS_URL ) );
			$rel        = strtok( (string) $rel, '?' );
			$local_path = wp_normalize_path( SINGO_TOOLS_PATH . $rel );
		} else {
			$site_url = site_url( '/' );
			if ( str_starts_with( $url, $site_url ) ) {
				$rel        = substr( $url, strlen( $site_url ) );
				$rel        = strtok( (string) $rel, '?' );
				$local_path = wp_normalize_path( ABSPATH . $rel );
			}
		}

		if ( ! empty( $local_path ) && file_exists( $local_path ) && is_readable( $local_path ) ) {
			return $local_path;
		}

		return '';
	}

	/**
	 * Convert a local WordPress image/SVG URL into a Base64 Data URI so it renders inside SVG background-image.
	 *
	 * @param string $url Image or SVG URL.
	 * @return string Base64 Data URI or original URL if external.
	 */
	public static function image_url_to_data_uri( string $url ): string {
		$url = trim( $url );
		if ( empty( $url ) || str_starts_with( $url, 'data:' ) ) {
			return $url;
		}

		$local_path = self::resolve_local_file_path( $url );
		if ( ! empty( $local_path ) ) {
			$lines = file( $local_path );
			$raw   = is_array( $lines ) ? implode( '', $lines ) : '';
			if ( '' !== $raw ) {
				$ext  = strtolower( pathinfo( $local_path, PATHINFO_EXTENSION ) );
				$mime = match ( $ext ) {
					'svg'          => 'image/svg+xml',
					'png'          => 'image/png',
					'webp'         => 'image/webp',
					'gif'          => 'image/gif',
					'jpg', 'jpeg'  => 'image/jpeg',
					default        => 'image/png',
				};
				return 'data:' . $mime . ';base64,' . base64_encode( $raw );
			}
		}

		return $url;
	}

	/**
	 * Generate a dual-page 594mm vector SVG Data URI with alternating corner patterns and category badge icon.
	 * Uses a 20x high-precision coordinate system (viewBox 0 0 4200 11880 = 508 DPI) and inlines vector SVG icons
	 * directly as <symbol>/<use> geometry so both pattern and icon remain 100% razor-sharp in print/PDF.
	 *
	 * @param string $color            Hex color code.
	 * @param string $icon_url         Optional category pattern icon URL.
	 * @param bool   $show_icon_circle Whether to draw a circular border around the icon.
	 * @return string Data URI string.
	 */
	public static function get_dual_page_pattern_svg( string $color, string $icon_url = '', bool $show_icon_circle = false ): string {
		$safe_color = sanitize_hex_color( $color );
		if ( empty( $safe_color ) ) {
			$safe_color = '#ad6141';
		}

		// Read the path from pattern.svg if available
		static $path_d = '';
		if ( empty( $path_d ) ) {
			$svg_file = SINGO_TOOLS_PATH . 'assets/images/pattern.svg';
			if ( file_exists( $svg_file ) ) {
				$lines = file( $svg_file );
				$raw   = is_array( $lines ) ? implode( '', $lines ) : '';
				if ( preg_match( '/<path[^>]+d="([^"]+)"/i', $raw, $m ) ) {
					$path_d = $m[1];
				}
			}
		}

		if ( empty( $path_d ) ) {
			return '';
		}

		$icon_symbol_def = '';
		$icon_layer_p1   = '';
		$icon_layer_p2   = '';

		if ( ! empty( $icon_url ) ) {
			$local_icon_path = self::resolve_local_file_path( $icon_url );
			$is_inline_svg   = false;

			// If the icon is a local .svg file, inline its vector geometry directly into <symbol> (zero rasterization!)
			if ( ! empty( $local_icon_path ) && 'svg' === strtolower( pathinfo( $local_icon_path, PATHINFO_EXTENSION ) ) ) {
				$svg_lines = file( $local_icon_path );
				$raw_svg   = is_array( $svg_lines ) ? implode( '', $svg_lines ) : '';
				if ( '' !== $raw_svg && preg_match( '/<svg\b([^>]*)>(.*)<\/svg>/is', $raw_svg, $svg_matches ) ) {
					$svg_attrs = $svg_matches[1];
					$svg_inner = trim( $svg_matches[2] );
					$view_box  = '0 0 512 512';

					if ( preg_match( '/viewBox\s*=\s*["\']([^"\']+)["\']/i', $svg_attrs, $vb_m ) ) {
						$view_box = trim( $vb_m[1] );
					} elseif (
						preg_match( '/\bwidth\s*=\s*["\']([0-9.]+)/i', $svg_attrs, $w_m ) &&
						preg_match( '/\bheight\s*=\s*["\']([0-9.]+)/i', $svg_attrs, $h_m )
					) {
						$view_box = '0 0 ' . $w_m[1] . ' ' . $h_m[1];
					}

					if ( '' !== $svg_inner ) {
						$is_inline_svg   = true;
						$icon_symbol_def = '<symbol id="singo-cat-vector-icon" viewBox="' . esc_attr( $view_box ) . '" preserveAspectRatio="xMidYMid meet">'
							. $svg_inner
							. '</symbol>';
					}
				}
			}

			// Coordinates in 20x space (4200 x 11880 for 210mm x 594mm)
			if ( $show_icon_circle ) {
				$circle_p1 = '<circle cx="3855" cy="325" r="205" fill="none" stroke="#4d4d4d" stroke-width="11"/>';
				$circle_p2 = '<circle cx="345" cy="6265" r="205" fill="none" stroke="#4d4d4d" stroke-width="11"/>';
				$x1 = '3700';
				$y1 = '170';
				$x2 = '190';
				$y2 = '6110';
				$sz = '310';
			} else {
				$circle_p1 = '';
				$circle_p2 = '';
				$x1 = '3650';
				$y1 = '120';
				$x2 = '140';
				$y2 = '6060';
				$sz = '410';
			}

			if ( $is_inline_svg ) {
				$icon_layer_p1 = $circle_p1 . '<use href="#singo-cat-vector-icon" x="' . $x1 . '" y="' . $y1 . '" width="' . $sz . '" height="' . $sz . '"/>';
				$icon_layer_p2 = $circle_p2 . '<use href="#singo-cat-vector-icon" x="' . $x2 . '" y="' . $y2 . '" width="' . $sz . '" height="' . $sz . '"/>';
			} else {
				$icon_data_uri = self::image_url_to_data_uri( $icon_url );
				if ( ! empty( $icon_data_uri ) ) {
					$safe_href     = htmlspecialchars( $icon_data_uri, ENT_QUOTES, 'UTF-8' );
					$icon_layer_p1 = $circle_p1 . '<image href="' . $safe_href . '" x="' . $x1 . '" y="' . $y1 . '" width="' . $sz . '" height="' . $sz . '" preserveAspectRatio="xMidYMid meet" image-rendering="optimizeQuality"/>';
					$icon_layer_p2 = $circle_p2 . '<image href="' . $safe_href . '" x="' . $x2 . '" y="' . $y2 . '" width="' . $sz . '" height="' . $sz . '" preserveAspectRatio="xMidYMid meet" image-rendering="optimizeQuality"/>';
				}
			}
		}

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4200 11880" width="210mm" height="594mm" shape-rendering="geometricPrecision" image-rendering="optimizeQuality">'
			. '<defs>'
			. '<style>'
			. ':root { --primery-color: ' . $safe_color . '; --primary-color: ' . $safe_color . '; }'
			. '.pattern-color { fill: var(--primery-color, ' . $safe_color . '); }'
			. '</style>'
			. '<g id="singo-corner-path">'
			. '<path d="' . $path_d . '" class="pattern-color"/>'
			. '</g>'
			. $icon_symbol_def
			. '</defs>'
			// Page 1 (0 to 5940 units = 0 to 297mm): Top-Right corner.
			. '<use href="#singo-corner-path" transform="translate(3726.12, 0) scale(6.189774)"/>'
			. $icon_layer_p1
			// Page 2 (5940 to 11880 units = 297mm to 594mm): Top-Left corner (mirrored horizontally).
			. '<use href="#singo-corner-path" transform="translate(473.88, 5940) scale(-6.189774, 6.189774)"/>'
			. $icon_layer_p2
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Generate a standalone inline DOM <svg> markup for a single page corner ('right' for odd pages, 'left' for even pages).
	 * Rendering as a real DOM <svg> element instead of a CSS background-image prevents Chromium/SkPDFDevice from
	 * rasterizing Page 2+ tiles into low-DPI bitmaps across page breaks.
	 *
	 * @param string $color            Hex color code for the corner pattern.
	 * @param string $icon_url         Optional category pattern icon URL.
	 * @param bool   $show_icon_circle Whether to draw a circular border around the icon.
	 * @param string $side             Corner side: 'right' (odd pages) or 'left' (even pages).
	 * @return string Raw inline <svg> HTML markup.
	 */
	public static function get_inline_page_pattern_svg_markup( string $color, string $icon_url = '', bool $show_icon_circle = false, string $side = 'right' ): string {
		$safe_color = sanitize_hex_color( $color );
		if ( empty( $safe_color ) ) {
			$safe_color = '#ad6141';
		}

		$side = ( 'left' === strtolower( trim( $side ) ) ) ? 'left' : 'right';

		static $path_d = '';
		if ( empty( $path_d ) ) {
			$svg_file = SINGO_TOOLS_PATH . 'assets/images/pattern.svg';
			if ( file_exists( $svg_file ) ) {
				$lines = file( $svg_file );
				$raw   = is_array( $lines ) ? implode( '', $lines ) : '';
				if ( preg_match( '/<path[^>]+d="([^"]+)"/i', $raw, $m ) ) {
					$path_d = $m[1];
				}
			}
		}

		if ( empty( $path_d ) ) {
			return '';
		}

		// Local 20x coordinate box: 600 x 1920 units = 30mm x 96mm
		if ( 'right' === $side ) {
			$pattern_transform = 'translate(126.12, 0) scale(6.189774)';
			$circle_cx         = '255';
			$icon_x            = $show_icon_circle ? '100' : '50';
		} else {
			$pattern_transform = 'translate(473.88, 0) scale(-6.189774, 6.189774)';
			$circle_cx         = '345';
			$icon_x            = $show_icon_circle ? '190' : '140';
		}

		$circle_cy = '325';
		$icon_y    = $show_icon_circle ? '170' : '120';
		$icon_sz   = $show_icon_circle ? '310' : '410';

		$circle_markup = $show_icon_circle
			? '<circle cx="' . $circle_cx . '" cy="' . $circle_cy . '" r="205" fill="none" stroke="#4d4d4d" stroke-width="11"/>'
			: '';

		$icon_markup = '';
		if ( ! empty( $icon_url ) ) {
			$local_icon_path = self::resolve_local_file_path( $icon_url );
			$is_inline_svg   = false;

			if ( ! empty( $local_icon_path ) && 'svg' === strtolower( pathinfo( $local_icon_path, PATHINFO_EXTENSION ) ) ) {
				$svg_lines = file( $local_icon_path );
				$raw_svg   = is_array( $svg_lines ) ? implode( '', $svg_lines ) : '';
				if ( '' !== $raw_svg && preg_match( '/<svg\b([^>]*)>(.*)<\/svg>/is', $raw_svg, $svg_matches ) ) {
					$svg_attrs = $svg_matches[1];
					$svg_inner = trim( $svg_matches[2] );
					$view_box  = '0 0 512 512';

					if ( preg_match( '/viewBox\s*=\s*["\']([^"\']+)["\']/i', $svg_attrs, $vb_m ) ) {
						$view_box = trim( $vb_m[1] );
					} elseif (
						preg_match( '/\bwidth\s*=\s*["\']([0-9.]+)/i', $svg_attrs, $w_m ) &&
						preg_match( '/\bheight\s*=\s*["\']([0-9.]+)/i', $svg_attrs, $h_m )
					) {
						$view_box = '0 0 ' . $w_m[1] . ' ' . $h_m[1];
					}

					if ( '' !== $svg_inner ) {
						// Scope internal CSS classes (e.g. .cls-1) so multiple inline SVGs never collide
						$scope_prefix = 'sngic_' . substr( md5( $local_icon_path . '_' . $side ), 0, 6 ) . '_';
						$svg_inner    = (string) preg_replace_callback(
							'/\bclass\s*=\s*["\']([^"\']+)["\']/i',
							static function ( array $m ) use ( $scope_prefix ): string {
								$classes = preg_split( '/\s+/', trim( $m[1] ) ) ?: array();
								$scoped  = array_map(
									static fn( string $c ): string => '' !== $c ? $scope_prefix . $c : '',
									$classes
								);
								return 'class="' . implode( ' ', array_filter( $scoped ) ) . '"';
							},
							$svg_inner
						);
						$svg_inner = (string) preg_replace_callback(
							'/<style\b[^>]*>(.*?)<\/style>/is',
							static function ( array $m ) use ( $scope_prefix ): string {
								$css = (string) preg_replace( '/\.([a-zA-Z_][a-zA-Z0-9_-]*)/', '.' . $scope_prefix . '$1', $m[1] );
								return '<style>' . $css . '</style>';
							},
							$svg_inner
						);

						$is_inline_svg = true;
						$icon_markup   = $circle_markup
							. '<svg x="' . $icon_x . '" y="' . $icon_y . '" width="' . $icon_sz . '" height="' . $icon_sz . '" viewBox="' . esc_attr( $view_box ) . '" preserveAspectRatio="xMidYMid meet" overflow="visible">'
							. $svg_inner
							. '</svg>';
					}
				}
			}

			if ( ! $is_inline_svg ) {
				$icon_data_uri = self::image_url_to_data_uri( $icon_url );
				if ( ! empty( $icon_data_uri ) ) {
					$safe_href   = htmlspecialchars( $icon_data_uri, ENT_QUOTES, 'UTF-8' );
					$icon_markup = $circle_markup
						. '<image href="' . $safe_href . '" x="' . $icon_x . '" y="' . $icon_y . '" width="' . $icon_sz . '" height="' . $icon_sz . '" preserveAspectRatio="xMidYMid meet" image-rendering="optimizeQuality"/>';
				}
			}
		}

		return '<svg class="singo-inline-corner-svg singo-inline-corner-svg--' . esc_attr( $side ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 1920" width="30mm" height="96mm" shape-rendering="geometricPrecision" image-rendering="optimizeQuality" style="display:block;width:30mm;height:96mm;overflow:visible;--primery-color:' . esc_attr( $safe_color ) . ';--primary-color:' . esc_attr( $safe_color ) . ';">'
			. '<g transform="' . $pattern_transform . '">'
			. '<path d="' . $path_d . '" fill="' . esc_attr( $safe_color ) . '" style="fill:var(--primery-color, ' . esc_attr( $safe_color ) . ');"/>'
			. '</g>'
			. $icon_markup
			. '</svg>';
	}

	/**
	 * Allow font and vector icon mime types in WordPress media uploader.
	 *
	 * @param array<string, string> $mimes Allowed mime types.
	 * @return array<string, string>
	 */
	public function allow_font_mime_types( array $mimes ): array {
		if ( ! isset( $mimes['woff2'] ) ) {
			$mimes['woff2'] = 'font/woff2';
		}
		if ( ! isset( $mimes['woff'] ) ) {
			$mimes['woff'] = 'font/woff';
		}
		if ( ! isset( $mimes['ttf'] ) ) {
			$mimes['ttf'] = 'font/ttf';
		}
		if ( ! isset( $mimes['otf'] ) ) {
			$mimes['otf'] = 'font/otf';
		}
		if ( ! isset( $mimes['eot'] ) ) {
			$mimes['eot'] = 'application/vnd.ms-fontobject';
		}
		if ( ! isset( $mimes['svg'] ) && function_exists( 'singo_tools_current_user_can_manage' ) && singo_tools_current_user_can_manage() ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * Correct filetype check for font and SVG files in WordPress.
	 *
	 * @param array<string, mixed>      $data      File data.
	 * @param string                    $file      Full path to file.
	 * @param string                    $filename  Filename.
	 * @param array<string, string>|null $mimes    Mime types array.
	 * @param string|null               $real_mime Real mime type.
	 * @return array<string, mixed>
	 */
	public function fix_font_filetype_check( array $data, string $file, string $filename, ?array $mimes, ?string $real_mime = null ): array {
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'woff2' === $ext ) {
			$data['ext']  = 'woff2';
			$data['type'] = 'font/woff2';
		} elseif ( 'woff' === $ext ) {
			$data['ext']  = 'woff';
			$data['type'] = 'font/woff';
		} elseif ( 'ttf' === $ext ) {
			$data['ext']  = 'ttf';
			$data['type'] = 'font/ttf';
		} elseif ( 'otf' === $ext ) {
			$data['ext']  = 'otf';
			$data['type'] = 'font/otf';
		} elseif ( 'svg' === $ext && function_exists( 'singo_tools_current_user_can_manage' ) && singo_tools_current_user_can_manage() ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	}

	/**
	 * Get the active font URL configured by the user.
	 *
	 * @return string Font URL or empty string if not configured.
	 */
	public static function get_active_font_url(): string {
		$saved = (string) self::get_setting( 'pdf_font_url', '' );
		if ( empty( $saved ) ) {
			$saved = (string) get_option( 'singo_price_list_font_url', '' );
		}
		if ( ! empty( $saved ) ) {
			if ( str_starts_with( $saved, '/' ) && function_exists( 'home_url' ) ) {
				return home_url( $saved );
			}
			return $saved;
		}
		return '';
	}

	/**
	 * Convert HEX color code to RGBA string with custom opacity.
	 *
	 * @param string $hex   HEX color code (3 or 6 chars, with or without #).
	 * @param float  $alpha Opacity value from 0.0 to 1.0.
	 * @return string RGBA color string.
	 */
	public static function hex_to_rgba( string $hex, float $alpha = 1.0 ): string {
		$clean_hex = ltrim( trim( $hex ), '#' );
		if ( 3 === strlen( $clean_hex ) ) {
			$clean_hex = $clean_hex[0] . $clean_hex[0] . $clean_hex[1] . $clean_hex[1] . $clean_hex[2] . $clean_hex[2];
		}
		if ( 6 !== strlen( $clean_hex ) || ! ctype_xdigit( $clean_hex ) ) {
			return 'rgba(173, 97, 65, ' . $alpha . ')';
		}
		$r = hexdec( substr( $clean_hex, 0, 2 ) );
		$g = hexdec( substr( $clean_hex, 2, 2 ) );
		$b = hexdec( substr( $clean_hex, 4, 2 ) );
		return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $alpha . ')';
	}

	/**
	 * Get the active brand/primary color for the PDF export.
	 *
	 * @return string Hex color code.
	 */
	public static function get_active_primary_color(): string {
		$saved = (string) self::get_setting( 'pdf_primary_color', '' );
		if ( empty( $saved ) ) {
			$saved = (string) get_option( 'singo_price_list_primary_color', '#ad6141' );
		}
		if ( function_exists( 'sanitize_hex_color' ) ) {
			$clean = sanitize_hex_color( $saved );
			if ( ! empty( $clean ) ) {
				return $clean;
			}
		}
		return ! empty( $saved ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $saved ) ? $saved : '#ad6141';
	}

	/**
	 * Query WooCommerce products and format price/image data.
	 *
	 * @param int    $category_id       Category ID or 0 for all.
	 * @param bool   $in_stock          Only in-stock products.
	 * @param string $orderby           Orderby field.
	 * @param string $order             ASC or DESC.
	 * @param float  $discount_percent  Global discount percentage (0 to 100).
	 * @param bool   $hide_strike       Whether to hide strikethrough original price.
	 * @param bool   $expand_variations Whether to expand variable products into separate rows.
	 * @param int    $limit              Maximum number of products to query.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_products_data(
		int $category_id = 0,
		bool $in_stock = true,
		string $orderby = 'title',
		string $order = 'ASC',
		float $discount_percent = 0.0,
		bool $hide_strike = false,
		bool $expand_variations = true,
		int $limit = 500
	): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		// Fast return from in-memory request cache if query was already processed
		$cache_key = md5( $category_id . '_' . ( $in_stock ? '1' : '0' ) . '_' . $orderby . '_' . $order . '_' . $discount_percent . '_' . ( $hide_strike ? '1' : '0' ) . '_' . ( $expand_variations ? '1' : '0' ) . '_' . $limit );
		if ( isset( self::$products_cache[ $cache_key ] ) ) {
			return self::$products_cache[ $cache_key ];
		}

		$query_args = array(
			'status'        => 'publish',
			'limit'         => $limit,
			'paginate'      => false,
			'no_found_rows' => true,
		);

		if ( $category_id > 0 ) {
			$term = get_term( $category_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$query_args['category'] = array( $term->slug );
			}
		}

		if ( $in_stock ) {
			$query_args['stock_status'] = 'instock';
		}

		switch ( $orderby ) {
			case 'price':
			case 'price_asc':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_price';
				$query_args['order']    = 'ASC';
				break;
			case 'price_desc':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_price';
				$query_args['order']    = 'DESC';
				break;
			case 'date':
				$query_args['orderby'] = 'date';
				$query_args['order']   = 'DESC';
				break;
			case 'title':
			default:
				$query_args['orderby'] = 'title';
				$query_args['order']   = $order;
				break;
		}

		$products = wc_get_products( $query_args );
		$results  = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			// Format parent thumbnail
			$parent_thumb_id  = $product->get_image_id();
			$parent_thumb_url = '';
			if ( $parent_thumb_id ) {
				$parent_thumb_src = wp_get_attachment_image_src( (int) $parent_thumb_id, 'woocommerce_thumbnail' );
				if ( ! empty( $parent_thumb_src[0] ) ) {
					$parent_thumb_url = $parent_thumb_src[0];
				}
			}
			if ( empty( $parent_thumb_url ) && function_exists( 'wc_placeholder_img_src' ) ) {
				$parent_thumb_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
			}

			// Clean parent short description
			$parent_short_desc = trim( wp_strip_all_tags( strip_shortcodes( (string) $product->get_short_description() ) ) );
			$parent_short_desc = (string) preg_replace( '/\s+/u', ' ', $parent_short_desc );

			// Detect brand data for parent product
			$parent_brand_data = $this->get_product_brand_data( $product );

			// If expanding variable product variations into separate rows
			if ( $expand_variations && $product->is_type( 'variable' ) ) {
				$children_ids = $product->get_children();
				if ( ! empty( $children_ids ) ) {
					foreach ( $children_ids as $child_id ) {
						$variation = wc_get_product( $child_id );
						if ( ! $variation instanceof WC_Product ) {
							continue;
						}

						if ( $in_stock && method_exists( $variation, 'is_in_stock' ) && ! $variation->is_in_stock() ) {
							continue;
						}

						$var_thumb_id  = $variation->get_image_id();
						$var_thumb_url = '';
						if ( $var_thumb_id ) {
							$var_thumb_src = wp_get_attachment_image_src( (int) $var_thumb_id, 'woocommerce_thumbnail' );
							if ( ! empty( $var_thumb_src[0] ) ) {
								$var_thumb_url = $var_thumb_src[0];
							}
						}
						if ( empty( $var_thumb_url ) ) {
							$var_thumb_url = $parent_thumb_url;
						}

						$attr_desc     = $this->get_formatted_variation_attributes( $variation, $product );
						$display_title = $product->get_name();
						if ( ! empty( $attr_desc ) ) {
							$display_title .= ' (' . $attr_desc . ')';
						}

						$var_sku = $variation->get_sku();
						if ( empty( $var_sku ) ) {
							$var_sku = $product->get_sku();
						}

						$var_desc = trim( wp_strip_all_tags( strip_shortcodes( (string) $variation->get_description() ) ) );
						$var_desc = (string) preg_replace( '/\s+/u', ' ', $var_desc );
						$short_desc = ! empty( $var_desc ) ? $var_desc : $parent_short_desc;

						$var_price_html = $this->format_toman_price_for_display( $variation, $discount_percent, $hide_strike );

						$results[] = array(
							'id'         => $variation->get_id(),
							'title'      => $display_title,
							'sku'        => $var_sku,
							'short_desc' => $short_desc,
							'brand_name' => $parent_brand_data['name'],
							'brand_logo' => $parent_brand_data['logo_url'],
							'thumb_url'  => $var_thumb_url,
							'price_html' => $var_price_html,
						);
					}
					continue;
				}
			}

			// Simple product
			$price_html = $this->format_toman_price_for_display( $product, $discount_percent, $hide_strike );

			$results[] = array(
				'id'         => $product->get_id(),
				'title'      => $product->get_name(),
				'sku'        => $product->get_sku(),
				'short_desc' => $parent_short_desc,
				'brand_name' => $parent_brand_data['name'],
				'brand_logo' => $parent_brand_data['logo_url'],
				'thumb_url'  => $parent_thumb_url,
				'price_html' => $price_html,
			);
		}

		self::$products_cache[ $cache_key ] = $results;

		return $results;
	}

	/**
	 * Detect and retrieve brand data (name and logo URL) for a product.
	 * Supports WooCommerce Brands, Perfect Brands for WooCommerce, YITH Brands,
	 * Woodmart Theme Brand system, and Global Product Attributes.
	 *
	 * @param WC_Product $product Product or variation object.
	 * @return array{name: string, logo_url: string}
	 */
	public function get_product_brand_data( WC_Product $product ): array {
		$product_id = $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : (int) $product->get_id();

		if ( isset( self::$brand_cache[ $product_id ] ) ) {
			return self::$brand_cache[ $product_id ];
		}

		$brand_data = array(
			'name'     => '',
			'logo_url' => '',
		);

		// Check user-configured custom taxonomy first
		$custom_tax = sanitize_key( (string) self::get_setting( 'pdf_brand_taxonomy', '' ) );
		$candidate_taxonomies = array();
		if ( ! empty( $custom_tax ) ) {
			$candidate_taxonomies[] = $custom_tax;
		}

		// Built-in standard brand taxonomies
		$standard_taxonomies = array(
			'product_brand',       // WooCommerce Brands, Woodmart Theme, WC 9.4+ Core Brands
			'pwb-brand',            // Perfect Brands for WooCommerce
			'yith_product_brand',   // YITH WooCommerce Brands
			'brand',                // Generic Custom Taxonomy
			'pa_brand',             // Global Product Attribute: Brand
			'pa_berand',            // Persian transliterated attribute
		);

		foreach ( $standard_taxonomies as $tax ) {
			if ( ! in_array( $tax, $candidate_taxonomies, true ) ) {
				$candidate_taxonomies[] = $tax;
			}
		}

		$brand_term = null;

		// 1. Check known candidate taxonomies
		foreach ( $candidate_taxonomies as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$terms = get_the_terms( $product_id, $taxonomy );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$brand_term = reset( $terms );
					break;
				}
			}
		}

		// 2. Scan all product taxonomies if not found
		if ( ! $brand_term ) {
			$product_taxonomies = get_object_taxonomies( 'product', 'names' );
			if ( is_array( $product_taxonomies ) ) {
				foreach ( $product_taxonomies as $tax_name ) {
					if ( str_contains( strtolower( (string) $tax_name ), 'brand' ) ) {
						$terms = get_the_terms( $product_id, $tax_name );
						if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
							$brand_term = reset( $terms );
							break;
						}
					}
				}
			}
		}

		// 3. Check Product Attributes
		if ( ! $brand_term ) {
			$parent_product = $product->is_type( 'variation' ) ? wc_get_product( $product_id ) : $product;
			if ( $parent_product instanceof WC_Product ) {
				$attributes = $parent_product->get_attributes();
				foreach ( $attributes as $attr_key => $attribute ) {
					$key_lower = strtolower( (string) $attr_key );
					if ( str_contains( $key_lower, 'brand' ) || str_contains( $key_lower, 'berand' ) ) {
						if ( method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() ) {
							$terms = wc_get_product_terms( $product_id, $attribute->get_name(), array( 'fields' => 'all' ) );
							if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
								$brand_term = reset( $terms );
								break;
							}
						} elseif ( method_exists( $attribute, 'get_options' ) ) {
							$options = $attribute->get_options();
							if ( ! empty( $options ) ) {
								$brand_data['name'] = trim( (string) reset( $options ) );
							}
						}
					}
				}
			}
		}

		// If a term was found, extract name and logo image
		if ( $brand_term instanceof \WP_Term ) {
			$brand_data['name'] = (string) $brand_term->name;

			$image_id = 0;
			$direct_url = '';

			// Check all known meta keys used across plugins and themes
			$meta_keys = array(
				'thumbnail_id',
				'pwb_brand_image',
				'image',
				'brand_image',
				'logo',
				'term_image',
				'product_attribute_image',
			);

			foreach ( $meta_keys as $meta_key ) {
				$meta_val = get_term_meta( (int) $brand_term->term_id, $meta_key, true );
				if ( ! empty( $meta_val ) ) {
					if ( is_numeric( $meta_val ) ) {
						$image_id = (int) $meta_val;
						break;
					} elseif ( is_string( $meta_val ) && filter_var( $meta_val, FILTER_VALIDATE_URL ) ) {
						$direct_url = esc_url_raw( $meta_val );
						break;
					}
				}
			}

			if ( ! empty( $direct_url ) ) {
				$brand_data['logo_url'] = $direct_url;
			} elseif ( $image_id > 0 ) {
				// Use full uncropped image for brands to preserve true landscape/wide aspect ratio
				$full_src = wp_get_attachment_image_src( $image_id, 'full' );
				if ( ! empty( $full_src[0] ) ) {
					$brand_data['logo_url'] = $full_src[0];
				} else {
					$large_src = wp_get_attachment_image_src( $image_id, 'large' );
					if ( ! empty( $large_src[0] ) ) {
						$brand_data['logo_url'] = $large_src[0];
					} else {
						$thumb_src = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );
						if ( ! empty( $thumb_src[0] ) ) {
							$brand_data['logo_url'] = $thumb_src[0];
						}
					}
				}
			}

			// Perfect Brands helper function support
			if ( empty( $brand_data['logo_url'] ) && function_exists( 'pwb_get_brand_image_src' ) ) {
				$pwb_src = pwb_get_brand_image_src( (int) $brand_term->term_id, 'full' );
				if ( ! empty( $pwb_src ) ) {
					$brand_data['logo_url'] = $pwb_src;
				} else {
					$pwb_thumb = pwb_get_brand_image_src( (int) $brand_term->term_id, 'woocommerce_thumbnail' );
					if ( ! empty( $pwb_thumb ) ) {
						$brand_data['logo_url'] = $pwb_thumb;
					}
				}
			}
		}

		self::$brand_cache[ $product_id ] = $brand_data;

		return $brand_data;
	}

	/**
	 * Format attributes of a variation into a clean Persian label.
	 *
	 * @param WC_Product $variation Variation object.
	 * @param WC_Product $parent    Parent product object.
	 * @return string Formatted attributes string.
	 */
	public function get_formatted_variation_attributes( WC_Product $variation, WC_Product $parent ): string {
		if ( ! method_exists( $variation, 'get_attributes' ) ) {
			return '';
		}

		$attributes = $variation->get_attributes();
		if ( empty( $attributes ) ) {
			return '';
		}

		$formatted_parts = array();
		foreach ( $attributes as $taxonomy_or_key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}

			$raw_key = (string) $taxonomy_or_key;
			if ( str_starts_with( $raw_key, 'attribute_' ) ) {
				$raw_key = substr( $raw_key, 10 );
			}

			$taxonomy = '';
			if ( taxonomy_exists( $raw_key ) ) {
				$taxonomy = $raw_key;
			} elseif ( taxonomy_exists( urldecode( $raw_key ) ) ) {
				$taxonomy = urldecode( $raw_key );
			} elseif ( ! str_starts_with( $raw_key, 'pa_' ) && taxonomy_exists( 'pa_' . $raw_key ) ) {
				$taxonomy = 'pa_' . $raw_key;
			} elseif ( ! str_starts_with( $raw_key, 'pa_' ) && taxonomy_exists( 'pa_' . urldecode( $raw_key ) ) ) {
				$taxonomy = 'pa_' . urldecode( $raw_key );
			}

			$label = '';
			if ( ! empty( $taxonomy ) && function_exists( 'wc_attribute_label' ) ) {
				$label = wc_attribute_label( $taxonomy, $parent );
			} elseif ( function_exists( 'wc_attribute_label' ) ) {
				$label = wc_attribute_label( $raw_key, $parent );
			}

			if ( empty( $label ) || $label === $raw_key || $label === $taxonomy ) {
				$label = preg_replace( '/^pa_/iu', '', urldecode( $raw_key ) );
			}
			$label = urldecode( (string) $label );
			$label = preg_replace( '/^pa_/iu', '', $label );
			$label = trim( $label );

			$raw_val = (string) $value;
			$decoded_val = urldecode( $raw_val );
			while ( str_contains( $decoded_val, '%' ) && urldecode( $decoded_val ) !== $decoded_val ) {
				$decoded_val = urldecode( $decoded_val );
			}

			$stripped_val = preg_replace( '/^pa_[^\-_]+[\-_]/u', '', $decoded_val );
			$stripped_val = trim( $stripped_val );

			$term = null;
			$target_tax = ! empty( $taxonomy ) ? $taxonomy : $raw_key;

			if ( taxonomy_exists( $target_tax ) ) {
				$candidates = array_unique( array_filter( array(
					$stripped_val,
					$decoded_val,
					$raw_val,
					urldecode( $raw_val ),
					function_exists( 'sanitize_title' ) ? sanitize_title( $stripped_val ) : $stripped_val,
				) ) );

				foreach ( $candidates as $candidate ) {
					$found_term = get_term_by( 'slug', $candidate, $target_tax );
					if ( ! $found_term || is_wp_error( $found_term ) ) {
						$found_term = get_term_by( 'name', $candidate, $target_tax );
					}
					if ( $found_term && ! is_wp_error( $found_term ) ) {
						$term = $found_term;
						break;
					}
				}
			}

			if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) {
				$final_val = (string) $term->name;
			} else {
				$final_val = ! empty( $stripped_val ) ? $stripped_val : $decoded_val;
			}

			$final_val = urldecode( $final_val );
			$final_val = trim( $final_val );

			if ( ! empty( $label ) && ! empty( $final_val ) ) {
				$formatted_parts[] = esc_html( $label ) . ': ' . esc_html( $final_val );
			} elseif ( ! empty( $final_val ) ) {
				$formatted_parts[] = esc_html( $final_val );
			}
		}

		return implode( ' • ', $formatted_parts );
	}

	/**
	 * Format product price in Tomans.
	 *
	 * @param WC_Product $product          Product object.
	 * @param float      $discount_percent Global discount percentage (0 to 100).
	 * @param bool       $hide_strike      Whether to hide strikethrough regular price.
	 * @return string Formatted price string.
	 */
	public function format_toman_price_for_display( WC_Product $product, float $discount_percent = 0.0, bool $hide_strike = false ): string {
		if ( $product->is_type( 'variable' ) ) {
			$min_price = $product->get_variation_price( 'min', true );
			$max_price = $product->get_variation_price( 'max', true );

			if ( empty( $min_price ) && 0 !== $min_price && '0' !== $min_price ) {
				return '<span style="color:#64748b; font-size:11px;">تماس بگیرید</span>';
			}

			if ( $discount_percent > 0.0 ) {
				$discount_factor = max( 0.0, 1.0 - ( $discount_percent / 100.0 ) );
				$disc_min        = round( floatval( $min_price ) * $discount_factor );
				$sale_str        = ( $min_price === $max_price ) ? $this->format_number_to_toman( $disc_min ) : 'از ' . $this->format_number_to_toman( $disc_min );
				$reg_str         = ( $min_price === $max_price ) ? $this->format_number_to_toman( $min_price ) : 'از ' . $this->format_number_to_toman( $min_price );

				if ( $hide_strike ) {
					return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $sale_str . '</div>';
				}

				return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $sale_str . '</div>' .
				       '<del class="singo-price-regular" style="display: block; color: #94a3b8; text-decoration: line-through; font-size: 11px; font-weight: normal; margin-top: 2px; line-height: 1.2;">' . $reg_str . '</del>';
			}

			if ( $product->is_on_sale() && method_exists( $product, 'get_variation_regular_price' ) ) {
				$min_regular = $product->get_variation_regular_price( 'min', true );
				if ( ! empty( $min_regular ) && floatval( $min_regular ) > floatval( $min_price ) ) {
					$sale_str    = ( $min_price === $max_price ) ? $this->format_number_to_toman( $min_price ) : 'از ' . $this->format_number_to_toman( $min_price );
					$regular_str = ( $min_price === $max_price ) ? $this->format_number_to_toman( $min_regular ) : 'از ' . $this->format_number_to_toman( $min_regular );

					if ( $hide_strike ) {
						return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $sale_str . '</div>';
					}

					return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $sale_str . '</div>' .
					       '<del class="singo-price-regular" style="display: block; color: #94a3b8; text-decoration: line-through; font-size: 11px; font-weight: normal; margin-top: 2px; line-height: 1.2;">' . $regular_str . '</del>';
				}
			}

			if ( $min_price === $max_price ) {
				return $this->format_number_to_toman( $min_price );
			}

			return 'از ' . $this->format_number_to_toman( $min_price );
		}

		$price = $product->get_price();
		if ( empty( $price ) && 0 !== $price && '0' !== $price ) {
			return '<span style="color:#64748b; font-size:11px;">تماس بگیرید</span>';
		}

		$current_num = floatval( $price );

		if ( $discount_percent > 0.0 ) {
			$discount_factor  = max( 0.0, 1.0 - ( $discount_percent / 100.0 ) );
			$discounted_price = round( $current_num * $discount_factor );

			if ( $hide_strike ) {
				return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $this->format_number_to_toman( $discounted_price ) . '</div>';
			}

			return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $this->format_number_to_toman( $discounted_price ) . '</div>' .
			       '<del class="singo-price-regular" style="display: block; color: #94a3b8; text-decoration: line-through; font-size: 11px; font-weight: normal; margin-top: 2px; line-height: 1.2;">' . $this->format_number_to_toman( $current_num ) . '</del>';
		}

		$regular = $product->get_regular_price();
		if ( $product->is_on_sale() && ! empty( $regular ) && floatval( $regular ) > $current_num ) {
			if ( $hide_strike ) {
				return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $this->format_number_to_toman( $price ) . '</div>';
			}

			return '<div class="singo-price-sale" style="font-weight: 700; font-size: 13px; line-height: 1.2;">' . $this->format_number_to_toman( $price ) . '</div>' .
			       '<del class="singo-price-regular" style="display: block; color: #94a3b8; text-decoration: line-through; font-size: 11px; font-weight: normal; margin-top: 2px; line-height: 1.2;">' . $this->format_number_to_toman( $regular ) . '</del>';
		}

		return $this->format_number_to_toman( $price );
	}

	/**
	 * Helper: Format numeric price into Persian formatted string without 'تومان' (since column header specifies unit).
	 *
	 * @param mixed $price Raw price.
	 * @return string
	 */
	private function format_number_to_toman( mixed $price ): string {
		$formatted = number_format( (float) $price, 0, '.', ',' );
		if ( class_exists( 'Singo_Shamsi_Converter' ) ) {
			$formatted = Singo_Shamsi_Converter::convert_to_persian_digits( $formatted );
		}
		return $formatted;
	}

	/**
	 * Purge transient cache of product categories.
	 */
	public static function purge_categories_cache(): void {
		delete_transient( 'singo_price_list_admin_cats' );
	}
}

if ( ! class_exists( 'SDM_Price_List' ) ) {
	class_alias( 'Singo_Price_List', 'SDM_Price_List' );
}
