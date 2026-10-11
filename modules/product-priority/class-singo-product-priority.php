<?php
declare(strict_types=1);

/**
 * Singo Tools - Product Priority (اولویت محصولات برجسته)
 * PHP 8+ Standardized
 */

defined( 'ABSPATH' ) || exit;

#[\AllowDynamicProperties]
class Singo_Product_Priority {

	/**
	 * Singleton instance.
	 *
	 * @var Singo_Product_Priority|null
	 */
	private static ?Singo_Product_Priority $instance = null;

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
	 * Constructor: register hooks only if module is enabled.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		// 1. Admin product list columns, submitbox, and AJAX (Strictly Admin / AJAX)
		if ( is_admin() || wp_doing_ajax() ) {
			add_filter( 'manage_edit-product_columns', array( $this, 'add_product_priority_column' ), 20 );
			add_filter( 'manage_edit-product_sortable_columns', array( $this, 'make_product_star_column_sortable' ) );
			add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_priority_column' ), 10, 2 );

			add_action( 'post_submitbox_misc_actions', array( $this, 'render_star_priority_in_submitbox' ) );
			add_action( 'save_post_product', array( $this, 'save_submitbox_star_rank' ), 10, 2 );

			add_action( 'admin_head', array( $this, 'product_star_column_style' ) );
			add_action( 'admin_footer', array( $this, 'star_priority_ajax_script' ) );

			add_action( 'wp_ajax_save_product_star_rank', array( $this, 'save_star_rank_callback' ) );
		}

		// 2. Query ordering in Frontend & JetWooBuilder
		add_filter( 'jet-woo-builder/shortcodes/jet-woo-products/query-args', array( $this, 'jet_woo_star_priority_query_args' ), 20, 2 );
		add_filter( 'jet-woo-builder/shortcodes/jet-woo-products/final-query-args', array( $this, 'jet_woo_star_priority_query_args' ), 20, 2 );
		add_filter( 'jet-woo-builder/shortcodes/jet-woo-products-list/query-args', array( $this, 'jet_woo_star_priority_query_args' ), 20, 2 );
		add_filter( 'jet-woo-builder/shortcodes/jet-woo-products-list/final-query-args', array( $this, 'jet_woo_star_priority_query_args' ), 20, 2 );

		add_filter( 'posts_clauses', array( $this, 'apply_five_star_priority_ordering' ), 99, 2 );
	}

	/**
	 * ۱. افزودن ستون امتیازدهی ۱ تا ۵ ستاره در جدول محصولات مدیریت
	 */
	public function add_product_priority_column( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'featured' === $key ) {
				$new_columns['product_star_rank'] = esc_html__( 'اولویت برجسته', 'woocommerce' );
			}
		}
		if ( ! isset( $new_columns['product_star_rank'] ) ) {
			$new_columns['product_star_rank'] = esc_html__( 'اولویت برجسته', 'woocommerce' );
		}
		return $new_columns;
	}

	/**
	 * افزودن قابلیت مرتب‌سازی ستون اولویت در مدیریت
	 */
	public function make_product_star_column_sortable( array $columns ): array {
		$columns['product_star_rank'] = 'product_star_rank';
		return $columns;
	}

	/**
	 * ۲. رندر کردن ستاره‌ها و دکمه ضربدر در جدول لیست محصولات
	 */
	public function render_product_priority_column( string $column, int $post_id ): void {
		if ( 'product_star_rank' === $column ) {
			$current_rating = (int) get_post_meta( $post_id, '_product_star_rank', true );

			echo '<div class="star-rank-wrapper" data-post-id="' . esc_attr( (string) $post_id ) . '" style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; width:100px; padding:4px 0; margin:auto; user-select:none;">';
				echo '<div class="star-items-row" style="display:flex; direction:ltr; line-height:1; font-size:16px; cursor:pointer;">';
				for ( $i = 1; $i <= 5; $i++ ) {
					$color = ( $i <= $current_rating ) ? '#f0ad4e' : '#ccc';
					echo '<span class="star-item" data-value="' . esc_attr( (string) $i ) . '" style="color:' . esc_attr( $color ) . '; padding:0 1px; transition:transform 0.1s ease;">★</span>';
				}
				echo '</div>';
				echo '<button type="button" class="clear-star" title="' . esc_attr__( 'حذف اولویت', 'woocommerce' ) . '" style="background:none; border:none; padding:2px 8px; margin:0; cursor:pointer; color:#d9534f; font-size:12px; line-height:1; border-radius:3px; opacity:0.8;">✕ ' . esc_html__( 'حذف', 'woocommerce' ) . '</button>';
			echo '</div>';
		}
	}

	/**
	 * ۳. افزودن بخش اولویت ستاره‌ای در جعبه انتشار (Publish Box) زیر قابلیت مشاهده کاتالوگ
	 */
	public function render_star_priority_in_submitbox( \WP_Post $post ): void {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		$post_id        = $post->ID;
		$current_rating = (int) get_post_meta( $post_id, '_product_star_rank', true );
		wp_nonce_field( 'save_product_star_rank_submitbox', 'star_rank_submitbox_nonce' );
		?>
		<div class="misc-pub-section misc-pub-product-star-rank" style="border-top:1px solid #f0f0f1; padding:10px 12px;">
			<span class="dashicons dashicons-star-filled" style="color:#82878c; vertical-align:middle; margin-left:4px;"></span>
			<span style="font-weight:600;"><?php esc_html_e( 'اولویت نمایش:', 'woocommerce' ); ?></span>

			<div class="star-rank-wrapper" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" style="display:inline-flex; align-items:center; gap:8px; margin-top:6px; direction:ltr; user-select:none;">
				<div class="star-items-row" style="display:flex; line-height:1; font-size:18px; cursor:pointer;">
					<?php for ( $i = 1; $i <= 5; $i++ ) :
						$color = ( $i <= $current_rating ) ? '#f0ad4e' : '#ccc'; ?>
						<span class="star-item" data-value="<?php echo esc_attr( (string) $i ); ?>" style="color:<?php echo esc_attr( $color ); ?>; padding:0 2px;">★</span>
					<?php endfor; ?>
				</div>
				<button type="button" class="clear-star" title="<?php esc_attr_e( 'حذف اولویت', 'woocommerce' ); ?>" style="background:none; border:none; color:#d9534f; cursor:pointer; font-size:13px; font-weight:bold; padding:0 4px;">✕</button>
				<input type="hidden" name="_product_star_rank_input" id="_product_star_rank_input" value="<?php echo esc_attr( (string) $current_rating ); ?>">
			</div>
		</div>
		<?php
	}

	/**
	 * ذخیره همگام با دکمه به‌روزرسانی در فرم اصلی صفحه محصول
	 */
	public function save_submitbox_star_rank( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['star_rank_submitbox_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['star_rank_submitbox_nonce'] ), 'save_product_star_rank_submitbox' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_product_star_rank_input'] ) ) {
			$rating = min( 5, max( 0, intval( $_POST['_product_star_rank_input'] ) ) );
			if ( 0 === $rating ) {
				delete_post_meta( $post_id, '_product_star_rank' );
			} else {
				update_post_meta( $post_id, '_product_star_rank', $rating );
			}
			$this->purge_product_caches( $post_id );
		}
	}

	/**
	 * ۴. استایل‌های تکمیلی برای مدیریت
	 */
	public function product_star_column_style(): void {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'edit-product', 'product' ), true ) ) {
			echo '<style>
				.column-product_star_rank { width: 110px !important; text-align: center !important; }
				.star-rank-wrapper .star-item:hover { transform: scale(1.2); }
				.star-rank-wrapper .clear-star:hover { background-color: #fee2e2 !important; opacity: 1 !important; }
			</style>';
		}
	}

	/**
	 * ۵. اسکریپت AJAX دوطرفه (هم جدول و هم داخل صفحه محصول)
	 */
	public function star_priority_ajax_script(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-product', 'product' ), true ) ) {
			return;
		}
		?>
		<script>
		jQuery(document).ready(function($) {
			$(document).on('click', '.star-rank-wrapper .star-item', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var $wrapper = $(this).closest('.star-rank-wrapper');
				var postId   = $wrapper.data('post-id');
				var rating   = parseInt($(this).data('value'), 10);

				saveRating(postId, rating, $wrapper);
			});

			$(document).on('click', '.star-rank-wrapper .clear-star', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var $wrapper = $(this).closest('.star-rank-wrapper');
				var postId   = $wrapper.data('post-id');

				saveRating(postId, 0, $wrapper);
			});

			function saveRating(postId, rating, $wrapper) {
				$wrapper.css('opacity', '0.3');
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'save_product_star_rank',
						post_id: postId,
						rating: rating,
						nonce: '<?php echo esc_js( wp_create_nonce( 'star_rank_nonce' ) ); ?>'
					},
					success: function(response) {
						$wrapper.css('opacity', '1');
						if ( response && response.success ) {
							$wrapper.find('.star-item').each(function() {
								var val = parseInt($(this).data('value'), 10);
								$(this).css('color', (val <= rating && rating > 0) ? '#f0ad4e' : '#ccc');
							});
							$wrapper.find('#_product_star_rank_input').val(rating);
						}
					},
					error: function() {
						$wrapper.css('opacity', '1');
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * ۶. دریافت و ذخیره متادیتا از طریق AJAX + پاکسازی کش
	 */
	public function save_star_rank_callback(): void {
		check_ajax_referer( 'star_rank_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز است.' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$rating  = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : -1;

		if ( $post_id > 0 && 'product' === get_post_type( $post_id ) && $rating >= 0 && $rating <= 5 ) {
			if ( 0 === $rating ) {
				delete_post_meta( $post_id, '_product_star_rank' );
			} else {
				update_post_meta( $post_id, '_product_star_rank', $rating );
			}

			// پاکسازی کش صفحات مربوطه جهت نمایش آنی تغییرات
			$this->purge_product_caches( $post_id );

			wp_send_json_success( array( 'post_id' => $post_id, 'rating' => $rating ) );
		}

		wp_send_json_error( array( 'message' => 'داده‌های ارسالی نامعتبر است.' ), 400 );
	}

	/**
	 * پاکسازی کش WP Rocket و Object Cache هنگام تغییر رتبه
	 */
	public function purge_product_caches( int $post_id = 0 ): void {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( $post_id > 0 && function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}
		if ( $post_id > 0 && function_exists( 'rocket_clean_term' ) ) {
			$cats = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
				foreach ( $cats as $cid ) {
					rocket_clean_term( (int) $cid, 'product_cat' );
				}
			}
			$tags = wp_get_post_terms( $post_id, 'product_tag', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
				foreach ( $tags as $tid ) {
					rocket_clean_term( (int) $tid, 'product_tag' );
				}
			}
		}
		$shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		if ( $shop_id > 0 && function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $shop_id );
		}
		if ( $post_id > 0 ) {
			clean_post_cache( $post_id );
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $post_id );
			}
		}
	}

	/**
	 * ۷. اطمینان از تنظیم post_type در ویجت‌های JetWooBuilder
	 */
	public function jet_woo_star_priority_query_args( array $query_args, mixed $shortcode = null ): array {
		if ( empty( $query_args['post_type'] ) ) {
			$query_args['post_type'] = 'product';
		}
		$query_args['jet_woo_star_priority'] = true;
		return $query_args;
	}

	/**
	 * تشخیص دقیق کوئری‌های آرشیو و کاتالوگ محصولات
	 */
	public function is_product_catalog_query( \WP_Query $query ): bool {
		// در مدیریت وردپرس، فقط در درخواست‌های ایجکس مجاز باشیم
		if ( is_admin() ) {
			if ( ! wp_doing_ajax() ) {
				// مرتب‌سازی در جدول محصولات ادمین در صورت کلیک روی هدر ستون
				return ( $query->get( 'orderby' ) === 'product_star_rank' );
			}
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( in_array( $action, array( 'woocommerce_json_search_products_and_variations', 'save_product_star_rank', 'editpost', 'heartbeat' ), true ) ) {
				return false;
			}
		}

		// بایپس اختیاری
		if ( $query->get( 'suppress_star_priority' ) ) {
			return false;
		}

		// کوئری صفحه تک محصول نباشد
		if ( $query->is_singular() ) {
			return false;
		}

		// فلگ اختصاصی JetWooBuilder یا use_current_query
		if ( $query->get( 'jet_woo_star_priority' ) || 'yes' === $query->get( 'jet_use_current_query' ) ) {
			return true;
		}

		// فلگ ووکامرس برای کوئری محصول
		if ( 'product_query' === $query->get( 'wc_query' ) ) {
			return true;
		}

		// نوع نوشته product باشد
		$post_types = (array) $query->get( 'post_type' );
		if ( in_array( 'product', $post_types, true ) ) {
			return true;
		}

		// کوئری تاکسونومی محصول (product_cat یا product_tag) حتی اگر post_type خالی باشد
		if ( ! empty( $query->get( 'product_cat' ) ) || ! empty( $query->get( 'product_tag' ) ) || $query->is_tax( 'product_cat' ) || $query->is_tax( 'product_tag' ) ) {
			return true;
		}

		// بررسی tax_query برای تاکسونومی‌های محصول
		$tax_query = $query->get( 'tax_query' );
		if ( is_array( $tax_query ) ) {
			foreach ( $tax_query as $clause ) {
				if ( is_array( $clause ) && isset( $clause['taxonomy'] ) && in_array( $clause['taxonomy'], array( 'product_cat', 'product_tag', 'product_visibility' ), true ) ) {
					return true;
				}
			}
		}

		// کوئری اصلی در صفحات آرشیو فروشگاه و دسته‌بندی
		if ( $query->is_main_query() && (
			( function_exists( 'is_shop' ) && is_shop() ) ||
			( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) ||
			( function_exists( 'is_product_category' ) && is_product_category() ) ||
			( function_exists( 'is_product_tag' ) && is_product_tag() )
		) ) {
			return true;
		}

		return false;
	}

	/**
	 * ۸. اعمال اولویت‌بندی ستاره‌ای در تمام کوئری‌های کاتالوگ و آرشیو محصولات
	 */
	public function apply_five_star_priority_ordering( array $clauses, \WP_Query $query ): array {
		if ( ! $this->is_product_catalog_query( $query ) ) {
			return $clauses;
		}

		global $wpdb;

		// اتصال جدول postmeta جهت خواندن اولویت ستاره‌ای
		if ( false === strpos( $clauses['join'] ?? '', 'star_meta' ) ) {
			$clauses['join'] = ( $clauses['join'] ?? '' ) . " LEFT JOIN {$wpdb->postmeta} AS star_meta ON ({$wpdb->posts}.ID = star_meta.post_id AND star_meta.meta_key = '_product_star_rank') ";
		}

		// جلوگیری از ردیف‌های تکراری با اطمینان از GROUP BY
		if ( empty( $clauses['groupby'] ) ) {
			$clauses['groupby'] = "{$wpdb->posts}.ID";
		} elseif ( false === strpos( $clauses['groupby'], "{$wpdb->posts}.ID" ) ) {
			$clauses['groupby'] .= ", {$wpdb->posts}.ID";
		}

		// مرتب‌سازی در پنل مدیریت در صورت کلیک روی ستون
		if ( is_admin() && $query->get( 'orderby' ) === 'product_star_rank' ) {
			$order              = strtoupper( (string) $query->get( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';
			$clauses['orderby'] = "CAST(COALESCE(star_meta.meta_value, 0) AS UNSIGNED) {$order}, {$wpdb->posts}.ID DESC";
			return $clauses;
		}

		$priority_order = " CAST(COALESCE(star_meta.meta_value, 0) AS UNSIGNED) DESC ";

		// قرار دادن اولویت ستاره‌ای در ابتدای عبارت ORDER BY
		if ( false === strpos( $clauses['orderby'] ?? '', 'star_meta' ) ) {
			if ( empty( trim( $clauses['orderby'] ?? '' ) ) ) {
				$clauses['orderby'] = $priority_order;
			} else {
				$clauses['orderby'] = $priority_order . ', ' . $clauses['orderby'];
			}
		}

		return $clauses;
	}
}
