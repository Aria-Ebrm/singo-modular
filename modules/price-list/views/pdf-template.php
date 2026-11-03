<?php
declare(strict_types=1);

/**
 * Singo Tools - High-DPI A4 PDF / Printable Template for WooCommerce Price List
 * Layout: Multi-Page A4 with Cover Page, Repeating Header, and Footer Contact Info
 */

defined( 'ABSPATH' ) || exit;

// Security check: allow Administrators/Shop Managers OR Logged-in Customers if enabled
$can_manage          = function_exists( 'singo_tools_current_user_can_manage' ) && singo_tools_current_user_can_manage();
$is_customer_allowed = class_exists( 'Singo_Price_List' ) && Singo_Price_List::is_my_account_enabled() && is_user_logged_in();

$preview_token       = isset( $_GET['preview_token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['preview_token'] ) ) : '';
$is_preview          = ( 'singo_preview_2026' === $preview_token );

if ( ! $can_manage && ! $is_customer_allowed && ! $is_preview ) {
	wp_die( 'سطح دسترسی نامعتبر است. لطفاً ابتدا وارد حساب کاربری خود شوید.', 'خطای دسترسی', array( 'response' => 403 ) );
}

// User Restriction Check: Block restricted customer accounts
if ( ! $can_manage && is_user_logged_in() && class_exists( 'Singo_Price_List' ) && Singo_Price_List::is_user_restricted( (int) get_current_user_id() ) ) {
	wp_die( 'دسترسی حساب کاربری شما به دریافت و دانلود لیست قیمت مسدود شده است. لطفاً با پشتیبانی فروشگاه تماس حاصل فرمایید.', 'دسترسی مسدود شده', array( 'response' => 403 ) );
}

$category_id  = isset( $_GET['category_id'] ) ? absint( $_GET['category_id'] ) : 0;
$in_stock     = ! isset( $_GET['in_stock'] ) || '1' === (string) $_GET['in_stock'] || 'yes' === (string) $_GET['in_stock'];
$orderby      = isset( $_GET['orderby'] ) ? sanitize_key( (string) $_GET['orderby'] ) : 'title';
$order        = isset( $_GET['order'] ) ? sanitize_key( (string) $_GET['order'] ) : 'ASC';
$custom_title = isset( $_GET['title'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['title'] ) ) : '';
$custom_notes = isset( $_GET['notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_GET['notes'] ) ) : '';
$custom_logo  = isset( $_GET['logo_url'] ) ? esc_url_raw( wp_unslash( (string) $_GET['logo_url'] ) ) : '';
$autoprint    = isset( $_GET['autoprint'] ) && '1' === (string) $_GET['autoprint'];

// Switcher Toggles (1 = show, 0 = hide)
$show_title       = ! isset( $_GET['show_title'] ) || '1' === (string) $_GET['show_title'];
$show_notes       = ! isset( $_GET['show_notes'] ) || '1' === (string) $_GET['show_notes'];
$show_logo        = ! isset( $_GET['show_logo'] ) || '1' === (string) $_GET['show_logo'];
$show_font        = ! isset( $_GET['show_font'] ) || '1' === (string) $_GET['show_font'];
$discount_percent = isset( $_GET['discount_percent'] ) ? max( 0.0, min( 100.0, floatval( $_GET['discount_percent'] ) ) ) : 0.0;
$hide_strike      = isset( $_GET['hide_strike'] ) && ( '1' === (string) $_GET['hide_strike'] || 'true' === (string) $_GET['hide_strike'] );
$expand_vars      = ! isset( $_GET['expand_variations'] ) || '1' === (string) $_GET['expand_variations'] || 'true' === (string) $_GET['expand_variations'];
$show_brand       = ! isset( $_GET['show_brand'] ) ? ( class_exists( 'Singo_Price_List' ) ? (bool) Singo_Price_List::get_setting( 'pdf_show_brand', true ) : true ) : ( '1' === (string) $_GET['show_brand'] || 'true' === (string) $_GET['show_brand'] );
$total_cols       = $show_brand ? 5 : 4;

// Check for Customer Frozen History Re-Download or Administrator Log Review
$is_history_download = false;
$products_data       = array();
$category_name       = 'همه محصولات';
$doc_title           = '';
$shamsi_date         = '';

if ( isset( $_GET['admin_log_id'] ) && ! empty( $_GET['admin_log_id'] ) && $can_manage ) {
	$log_user_id      = isset( $_GET['log_user_id'] ) ? absint( $_GET['log_user_id'] ) : 0;
	$admin_log_id     = sanitize_key( (string) $_GET['admin_log_id'] );
	$history_snapshot = class_exists( 'Singo_Price_List' ) ? Singo_Price_List::get_admin_log_snapshot( $log_user_id, $admin_log_id ) : null;
	if ( empty( $history_snapshot ) || empty( $history_snapshot['products_data'] ) ) {
		wp_die( 'این فایل دانلود در گزارشات مدیریت یافت نشد یا حذف شده است.', 'گزارش یافت نشد', array( 'response' => 404 ) );
	}

	$is_history_download = true;
	$category_id         = (int) ( $history_snapshot['category_id'] ?? 0 );
	$category_name       = (string) ( $history_snapshot['category_name'] ?? 'همه محصولات' );
	$doc_title           = (string) ( $history_snapshot['doc_title'] ?? '' );
	$shamsi_date         = (string) ( $history_snapshot['shamsi_date'] ?? '' );
	$products_data       = (array) $history_snapshot['products_data'];
} elseif ( isset( $_GET['history_id'] ) && ! empty( $_GET['history_id'] ) ) {
	if ( ! is_user_logged_in() ) {
		wp_die( 'برای دریافت فایل از تاریخچه، لطفاً ابتدا وارد حساب کاربری خود شوید.', 'خطای دسترسی', array( 'response' => 403 ) );
	}
	$history_id       = sanitize_key( (string) $_GET['history_id'] );
	$history_snapshot = class_exists( 'Singo_Price_List' ) ? Singo_Price_List::get_user_history_snapshot( (int) get_current_user_id(), $history_id ) : null;
	if ( empty( $history_snapshot ) || empty( $history_snapshot['products_data'] ) ) {
		wp_die( 'این فایل دانلود تاریخچه منقضی شده است یا وجود ندارد.', 'تاریخچه منقضی شده', array( 'response' => 404 ) );
	}

	$is_history_download = true;
	$category_id         = (int) ( $history_snapshot['category_id'] ?? 0 );
	$category_name       = (string) ( $history_snapshot['category_name'] ?? 'همه محصولات' );
	$doc_title           = (string) ( $history_snapshot['doc_title'] ?? '' );
	$shamsi_date         = (string) ( $history_snapshot['shamsi_date'] ?? '' );
	$products_data       = (array) $history_snapshot['products_data'];
} else {
	// Resolve Category Name
	if ( $category_id > 0 ) {
		$term = get_term( $category_id, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$category_name = $term->name;
		}
	}

	// Resolve Document Title
	$doc_title = ! empty( $custom_title ) ? $custom_title : ( 'لیست قیمت (' . $category_name . ')' );
}

if ( empty( $doc_title ) ) {
	$doc_title = 'لیست قیمت (' . $category_name . ')';
}

// Normalize: remove redundant "محصولات" from title if present
$doc_title = str_replace( 'لیست قیمت محصولات', 'لیست قیمت', $doc_title );

// Resolve Logo URL (User settings -> theme custom logo)
if ( empty( $custom_logo ) && class_exists( 'Singo_Price_List' ) ) {
	$saved_logo = (string) Singo_Price_List::get_setting( 'pdf_logo_url', '' );
	if ( ! empty( $saved_logo ) ) {
		$custom_logo = $saved_logo;
	}
}
if ( empty( $custom_logo ) ) {
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$custom_logo = (string) wp_get_attachment_image_url( (int) $custom_logo_id, 'full' );
	}
}

// Resolve Footer Contact Information
$footer_phone   = class_exists( 'Singo_Price_List' ) ? (string) Singo_Price_List::get_setting( 'pdf_footer_phone', '' ) : '';
$footer_address = class_exists( 'Singo_Price_List' ) ? (string) Singo_Price_List::get_setting( 'pdf_footer_address', '' ) : '';
if ( empty( $custom_notes ) && class_exists( 'Singo_Price_List' ) ) {
	$custom_notes = (string) Singo_Price_List::get_setting( 'pdf_footer_notes', '' );
}

// Resolve Cover Page Information (Full A4 Graphic Cover with Category & Parent Inheritance)
$global_show_cover = class_exists( 'Singo_Price_List' ) && (bool) Singo_Price_List::get_setting( 'enable_pdf_cover', false );
$cat_covers        = class_exists( 'Singo_Price_List' ) ? (array) Singo_Price_List::get_setting( 'category_cover_images', array() ) : array();
$cover_image       = class_exists( 'Singo_Price_List' ) ? Singo_Price_List::get_resolved_category_cover_image( $category_id ) : '';
$has_cat_cover     = ( $category_id > 0 && ! empty( $cover_image ) && ! empty( $cat_covers ) );
$show_cover        = ( $global_show_cover || $has_cat_cover ) && ! empty( $cover_image );

// Resolve Page Background Image (All pages except Cover)
$page_bg_url = SINGO_TOOLS_URL . 'assets/images/BGP.webp';
if ( class_exists( 'Singo_Price_List' ) ) {
	$saved_bg = (string) Singo_Price_List::get_setting( 'pdf_background_image', '' );
	if ( ! empty( $saved_bg ) ) {
		$page_bg_url = $saved_bg;
	}
}

// Resolve Page Corner Pattern, Category Color & Category Pattern Icon (Alternating Left & Right)
$pattern_enabled     = class_exists( 'Singo_Price_List' ) ? (bool) Singo_Price_List::get_setting( 'enable_page_pattern', true ) : true;
$pattern_icon_circle = class_exists( 'Singo_Price_List' ) ? (bool) Singo_Price_List::get_setting( 'pattern_icon_circle', false ) : false;
$pattern_color       = class_exists( 'Singo_Price_List' ) ? Singo_Price_List::get_resolved_category_color( $category_id ) : '#ad6141';
$pattern_icon        = class_exists( 'Singo_Price_List' ) ? Singo_Price_List::get_resolved_category_pattern_icon( $category_id ) : '';
$pattern_svg_right   = ( $pattern_enabled && class_exists( 'Singo_Price_List' ) ) ? Singo_Price_List::get_inline_page_pattern_svg_markup( $pattern_color, $pattern_icon, $pattern_icon_circle, 'right' ) : '';
$pattern_svg_left    = ( $pattern_enabled && class_exists( 'Singo_Price_List' ) ) ? Singo_Price_List::get_inline_page_pattern_svg_markup( $pattern_color, $pattern_icon, $pattern_icon_circle, 'left' ) : '';
$has_inline_pattern  = ( $pattern_enabled && ! empty( $pattern_svg_right ) && ! empty( $pattern_svg_left ) );

// Resolve Clean Site Domain (without http://, https://, or www.)
$site_domain = '';
$raw_host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! empty( $raw_host ) ) {
	$site_domain = strtolower( preg_replace( '/^www\./i', '', $raw_host ) );
} else {
	$site_domain = strtolower( preg_replace( '#^https?://(www\.)?#i', '', home_url() ) );
	$site_domain = rtrim( explode( '/', $site_domain )[0], '/' );
}

// Resolve Shamsi Date
if ( empty( $shamsi_date ) ) {
	$shamsi_date = wp_date( 'Y/m/d' );
	if ( class_exists( 'Singo_Shamsi_Converter' ) ) {
		$shamsi_date = Singo_Shamsi_Converter::format_date( time(), 'l، j F Y' );
	}
}

// Fetch Products (if not already loaded from frozen history snapshot)
if ( ! $is_history_download ) {
	$price_list_instance = class_exists( 'Singo_Price_List' ) ? Singo_Price_List::get_instance() : null;
	$products_data       = $price_list_instance ? $price_list_instance->get_products_data( $category_id, $in_stock, $orderby, $order, $discount_percent, $hide_strike, $expand_vars ) : array();

	// Auto-record user download snapshot & permanent admin report log
	if (
		isset( $_GET['customer_export'] )
		&& '1' === (string) $_GET['customer_export']
		&& is_user_logged_in()
		&& ! empty( $products_data )
		&& class_exists( 'Singo_Price_List' )
	) {
		$curr_uid = (int) get_current_user_id();
		if ( Singo_Price_List::is_customer_history_enabled() ) {
			Singo_Price_List::record_user_download_snapshot(
				$curr_uid,
				$category_id,
				$category_name,
				$doc_title,
				$shamsi_date,
				$products_data
			);
		} else {
			Singo_Price_List::record_admin_download_log(
				$curr_uid,
				$category_id,
				$category_name,
				$doc_title,
				$shamsi_date,
				$products_data
			);
		}
	}
}

// Resolve Font URL
$font_face_css = '';
if ( $show_font ) {
	$font_url = isset( $_GET['font_url'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['font_url'] ) ) : '';
	if ( ! empty( $font_url ) ) {
		$font_url = esc_url_raw( $font_url );
	} elseif ( class_exists( 'Singo_Price_List' ) ) {
		$font_url = Singo_Price_List::get_active_font_url();
	}

	if ( ! empty( $font_url ) && str_starts_with( $font_url, '/' ) && function_exists( 'home_url' ) ) {
		$font_url = home_url( $font_url );
	}

	if ( ! empty( $font_url ) ) {
		$font_path   = (string) ( parse_url( $font_url, PHP_URL_PATH ) ?? '' );
		$font_ext    = strtolower( pathinfo( $font_path, PATHINFO_EXTENSION ) );
		$font_format = match ( $font_ext ) {
			'woff'  => "format('woff')",
			'ttf'   => "format('truetype')",
			'otf'   => "format('opentype')",
			'eot'   => "format('embedded-opentype')",
			default => "format('woff2')",
		};
		$font_face_css = "
			@font-face {
				font-family: 'SingoCustomFont';
				src: url('" . esc_url( $font_url ) . "') " . $font_format . ";
				font-weight: 100 900;
				font-style: normal;
				font-display: swap;
			}
		";
	}
}

// Resolve Brand / Primary Color
$primary_color = isset( $_GET['primary_color'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['primary_color'] ) ) : '';
if ( function_exists( 'sanitize_hex_color' ) ) {
	$primary_color = sanitize_hex_color( $primary_color );
}
if ( empty( $primary_color ) && class_exists( 'Singo_Price_List' ) ) {
	$primary_color = Singo_Price_List::get_active_primary_color();
}
if ( empty( $primary_color ) || ! preg_match( '/^#[0-9a-fA-F]{3,6}$/', $primary_color ) ) {
	$primary_color = '#ad6141';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=794, initial-scale=0.45, minimum-scale=0.2, maximum-scale=3.0, user-scalable=yes">
	<title><?php echo esc_html( $doc_title ); ?> - <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<style>
		<?php echo $font_face_css; ?>

		:root {
			--primery-color: <?php echo esc_attr( $pattern_color ); ?>;
			--primary-color: <?php echo esc_attr( $pattern_color ); ?>;
		}

		* {
			box-sizing: border-box;
			-webkit-print-color-adjust: exact !important;
			print-color-adjust: exact !important;
		}

		body {
			font-family: <?php echo ! empty( $font_face_css ) ? "'SingoCustomFont', " : ''; ?>system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Tahoma, 'Vazirmatn', sans-serif !important;
			direction: rtl;
			text-align: right;
			margin: 0;
			padding: 0;
			background-color: #f1f5f9;
			color: #1e293b;
			font-size: 13px;
			line-height: 1.5;
		}

		/* Screen Toolbar (Hidden during Print) */
		.singo-screen-toolbar {
			position: sticky;
			top: 0;
			z-index: 9999;
			background: #ffffff;
			border-bottom: 2px solid <?php echo esc_attr( $primary_color ); ?>;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
			padding: 12px 24px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			flex-wrap: wrap;
			gap: 12px;
		}

		.singo-toolbar-title {
			font-size: 15px;
			font-weight: 800;
			color: #0f172a;
			display: flex;
			align-items: center;
			gap: 8px;
		}

		.singo-toolbar-actions {
			display: flex;
			align-items: center;
			gap: 10px;
		}

		.singo-btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 9px 20px;
			border-radius: 6px;
			font-size: 13.5px;
			font-weight: 700;
			cursor: pointer;
			text-decoration: none;
			border: none;
			transition: all 0.15s ease;
			font-family: inherit !important;
		}

		.singo-btn-primary {
			background: <?php echo esc_attr( $primary_color ); ?>;
			color: #ffffff;
			box-shadow: 0 2px 4px rgba(173, 97, 65, 0.25);
		}

		.singo-btn-primary:hover {
			opacity: 0.92;
			transform: translateY(-1px);
		}

		.singo-btn-secondary {
			background: #e2e8f0;
			color: #334155;
		}

		.singo-btn-secondary:hover {
			background: #cbd5e1;
		}

		/* A4 Page Containers */
		.singo-print-wrapper {
			width: 210mm;
			max-width: 210mm;
			margin: 20px auto;
			background: #ffffff;
			box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
			border-radius: 4px;
			box-sizing: border-box;
		}

		.singo-print-wrapper--content {
			position: relative;
			overflow: hidden;
			padding: <?php echo $has_inline_pattern ? '0 29.5mm 14mm 29.5mm' : '0 12mm 14mm 12mm'; ?>;
			background-image: url('<?php echo esc_url( $page_bg_url ); ?>');
			background-size: 210mm 297mm;
			background-repeat: repeat-y;
			background-position: top center;
		}

		.singo-page-patterns-layer {
			position: static;
			pointer-events: none;
		}

		.singo-page-corner-item {
			position: absolute;
			width: 30mm;
			height: 96mm;
			pointer-events: none;
			z-index: 1;
		}

		.singo-page-corner-item--right {
			right: 0;
		}

		.singo-page-corner-item--left {
			left: 0;
		}

		.singo-price-table {
			position: relative;
			z-index: 2;
		}

		.singo-print-wrapper--cover {
			width: 210mm;
			max-width: 210mm;
			height: 296mm;
			max-height: 297mm;
			margin: 20px auto;
			padding: 0;
			overflow: hidden;
			background-color: #ffffff;
			background-image: none !important;
			box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
			page-break-inside: avoid;
			break-inside: avoid;
			page-break-after: always;
			break-after: page;
			box-sizing: border-box;
		}

		.singo-pdf-cover-page--image-only {
			width: 100%;
			height: 100%;
			margin: 0;
			padding: 0;
			overflow: hidden;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.singo-pdf-cover-full-image {
			width: 100%;
			height: 100%;
			max-width: 100%;
			max-height: 100%;
			object-fit: cover;
			display: block;
			margin: 0;
			padding: 0;
		}

		/* Main Table */
		table.singo-price-table {
			width: 100%;
			border-collapse: collapse;
			table-layout: fixed;
		}

		/* Repeating Header in CSS Paged Media */
		thead {
			display: table-header-group;
		}

		tfoot {
			display: table-footer-group;
		}

		tr {
			page-break-inside: avoid !important;
		}

		/* Page Header Block */
		.page-header-cell {
			padding: 24px 0 15px 0 !important;
			border-bottom: 2px solid <?php echo esc_attr( $primary_color ); ?>;
			position: relative;
			z-index: 2;
		}

		.page-header-grid {
			display: flex;
			justify-content: space-between;
			align-items: center;
			width: 100%;
		}

		.header-right {
			display: flex;
			align-items: center;
			gap: 12px;
			width: 22%;
			justify-content: flex-start;
		}

		.header-logo-img {
			max-height: 52px;
			max-width: 130px;
			object-fit: contain;
			display: block;
		}

		.header-center {
			text-align: center;
			width: 56%;
			padding: 0 4px;
		}

		.header-doc-title {
			font-size: 16.5px;
			font-weight: 900;
			color: <?php echo esc_attr( $primary_color ); ?>;
			margin: 0 0 4px 0;
			line-height: 1.3;
			white-space: nowrap !important;
			overflow: hidden !important;
			text-overflow: ellipsis !important;
			display: block;
			text-align: center;
		}

		.header-category-badge {
			display: inline-block;
			font-size: 11.5px;
			color: #475569;
			background: #f1f5f9;
			padding: 2px 10px;
			border-radius: 12px;
			font-weight: 600;
		}

		.header-left {
			text-align: left;
			width: 22%;
			direction: ltr;
			font-size: 12px;
			color: #64748b;
			display: flex;
			flex-direction: column;
			align-items: flex-end;
			gap: 3px;
		}

		.header-meta-item {
			display: flex;
			align-items: center;
			gap: 6px;
			direction: rtl;
		}

		.meta-label {
			color: #94a3b8;
			font-size: 11px;
		}

		.meta-val {
			font-weight: 700;
			color: #1e293b;
		}

		/* Table Columns Header */
		.table-columns-header th {
			background-color: #f8fafc;
			color: #334155;
			padding: 10px 6px;
			font-size: 12px;
			font-weight: 800;
			border: 1px solid #cbd5e1;
			border-top: none;
			text-align: center;
		}

		/* Exact Proportional Columns */
		<?php if ( $show_brand ) : ?>
		.th-index, .td-index {
			width: 5% !important;
			max-width: 45px !important;
			text-align: center !important;
			padding: 8px 4px !important;
		}
		.th-image, .td-image {
			width: 9% !important;
			max-width: 70px !important;
			text-align: center !important;
			padding: 4px !important;
		}
		.th-name, .td-name {
			width: 48% !important;
			text-align: right !important;
			padding: 8px 12px !important;
		}
		.th-brand, .td-brand {
			width: 15% !important;
			text-align: center !important;
			vertical-align: middle !important;
			padding: 6px 8px !important;
		}
		.th-price, .td-price {
			width: 23% !important;
			text-align: center !important;
			padding: 8px 6px !important;
		}
		<?php else : ?>
		.th-index, .td-index {
			width: 5% !important;
			max-width: 45px !important;
			text-align: center !important;
			padding: 8px 4px !important;
		}
		.th-image, .td-image {
			width: 10% !important;
			max-width: 75px !important;
			text-align: center !important;
			padding: 4px !important;
		}
		.th-name, .td-name {
			width: 60% !important;
			text-align: right !important;
			padding: 8px 14px !important;
		}
		.th-price, .td-price {
			width: 25% !important;
			text-align: center !important;
			padding: 8px 6px !important;
		}
		<?php endif; ?>

		/* Table Body Rows */
		.singo-price-table tbody tr {
			border-bottom: 1px solid #e2e8f0;
		}

		.singo-price-table tbody tr:nth-child(even) {
			background-color: rgba(248, 250, 252, 0.45);
		}

		.singo-price-table tbody td {
			padding: 8px 6px;
			vertical-align: middle;
			border: 1px solid #e2e8f0;
			font-size: 12.5px;
		}

		.td-index {
			font-weight: 700;
			color: #64748b;
		}

		.product-thumb-img {
			width: 44px;
			height: 44px;
			object-fit: cover;
			border-radius: 4px;
			border: 1px solid #e2e8f0;
			display: inline-block;
			vertical-align: middle;
		}

		.product-title-text {
			font-weight: 700;
			color: #0f172a;
			font-size: 13px;
			display: block;
			line-height: 1.4;
			direction: rtl !important;
			unicode-bidi: isolate !important;
			text-align: right !important;
		}

		.product-short-desc {
			display: -webkit-box;
			-webkit-line-clamp: 3;
			-webkit-box-orient: vertical;
			overflow: hidden;
			text-overflow: ellipsis;
			line-height: 1.45;
			max-height: 4.35em;
			font-size: 10.5px;
			font-weight: 400;
			color: #64748b;
			margin-top: 3px;
			margin-bottom: 2px;
			direction: rtl !important;
			unicode-bidi: isolate !important;
			text-align: right !important;
			word-break: break-word;
		}

		.product-sku-badge {
			font-size: 10.5px;
			color: #64748b;
			margin-top: 2px;
			display: block;
			direction: rtl !important;
			unicode-bidi: isolate !important;
			text-align: right !important;
		}

		.th-brand, .td-brand {
			text-align: center;
			vertical-align: middle;
			padding: 6px 4px !important;
		}

		.brand-logo-img {
			max-width: 100%;
			max-height: 38px;
			width: auto;
			height: auto;
			object-fit: contain;
			display: inline-block;
			vertical-align: middle;
			filter: drop-shadow(0 1px 1px rgba(0,0,0,0.05));
		}

		.brand-name-badge {
			display: inline-block;
			font-size: 10.5px;
			font-weight: 700;
			color: #334155;
			background: rgba(241, 245, 249, 0.85);
			padding: 3px 6px;
			border-radius: 4px;
			border: 1px solid #e2e8f0;
			line-height: 1.3;
			max-width: 100%;
			word-break: break-word;
			direction: ltr;
			unicode-bidi: plaintext;
		}

		.brand-empty-dash {
			color: #cbd5e1;
			font-size: 14px;
			font-weight: bold;
		}

		.td-price {
			white-space: nowrap;
			font-weight: 700;
			font-size: 13px;
			color: <?php echo esc_attr( $primary_color ); ?>;
			text-align: center;
			vertical-align: middle;
		}
		.td-price .singo-price-sale {
			color: <?php echo esc_attr( $primary_color ); ?> !important;
			font-weight: 700 !important;
			font-size: 13px !important;
			line-height: 1.2 !important;
		}
		<?php if ( $hide_strike ) : ?>
		.td-price .singo-price-regular,
		.td-price del {
			display: none !important;
		}
		<?php else : ?>
		.td-price .singo-price-regular,
		.td-price del {
			display: block !important;
			text-decoration: line-through !important;
			color: #94a3b8 !important;
			font-size: 11px !important;
			font-weight: normal !important;
			margin-top: 2px !important;
			line-height: 1.2 !important;
		}
		<?php endif; ?>

		/* Footer Styles with Vector Icons */
		.page-footer-cell {
			padding: 16px 0 0 0;
			border-top: 1px solid #cbd5e1;
		}

		.footer-content-flex {
			display: flex;
			justify-content: space-between;
			align-items: center;
			font-size: 11.5px;
			color: #64748b;
			flex-wrap: wrap;
			gap: 14px;
		}

		.footer-contacts-group {
			display: flex;
			align-items: center;
			gap: 18px;
			flex-wrap: wrap;
		}

		.footer-contact-item {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			font-size: 11.5px;
			color: #334155;
			font-weight: 600;
		}

		.footer-icon {
			width: 14px;
			height: 14px;
			color: <?php echo esc_attr( $primary_color ); ?>;
			stroke: <?php echo esc_attr( $primary_color ); ?>;
			vertical-align: middle;
			flex-shrink: 0;
		}

		.footer-notes {
			font-weight: 600;
			color: #475569;
			flex: 1;
			text-align: center;
			padding: 0 10px;
		}

		.footer-domain {
			font-weight: 700;
			color: #64748b;
			direction: ltr;
			unicode-bidi: embed;
		}

		/* Print Media Rules (100% Margin-Zero suppressing Browser URL/Date/Header/Footer) */
		@media print {
			@page {
				size: A4 portrait;
				margin: 0 !important;
			}

			body {
				background: #ffffff !important;
				color: #000000 !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			.singo-screen-toolbar {
				display: none !important;
			}

			.singo-print-wrapper {
				max-width: 210mm !important;
				margin: 0 auto !important;
				box-shadow: none !important;
				border-radius: 0 !important;
			}

			.singo-print-wrapper--cover {
				width: 100% !important;
				max-width: 210mm !important;
				height: 296mm !important;
				max-height: 99.5vh !important;
				margin: 0 auto !important;
				padding: 0 !important;
				overflow: hidden !important;
				page-break-inside: avoid !important;
				break-inside: avoid !important;
				page-break-after: always !important;
				break-after: page !important;
				page-break-before: auto !important;
				break-before: auto !important;
				box-sizing: border-box !important;
				background-color: #ffffff !important;
				background-image: none !important;
			}

			.singo-pdf-cover-page--image-only {
				width: 100% !important;
				max-width: 210mm !important;
				height: 296mm !important;
				max-height: 99.5vh !important;
				margin: 0 auto !important;
				padding: 0 !important;
				overflow: hidden !important;
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
			}

			.singo-pdf-cover-full-image {
				width: 100% !important;
				height: 100% !important;
				max-width: 100% !important;
				max-height: 100% !important;
				object-fit: cover !important;
				display: block !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			.singo-print-wrapper--content {
				position: relative !important;
				overflow: visible !important;
				width: 210mm !important;
				max-width: 210mm !important;
				margin: 0 auto !important;
				padding: <?php echo $has_inline_pattern ? '0 29.5mm 14mm 29.5mm !important' : '0 10mm 14mm 10mm !important'; ?>;
				box-sizing: border-box !important;
				<?php if ( $show_cover && ! empty( $cover_image ) ) : ?>
				page-break-before: always !important;
				break-before: page !important;
				<?php else : ?>
				page-break-before: auto !important;
				break-before: auto !important;
				<?php endif; ?>
				background-image: url('<?php echo esc_url( $page_bg_url ); ?>') !important;
				background-size: 210mm 297mm !important;
				background-repeat: repeat-y !important;
				background-position: top center !important;
				-webkit-print-color-adjust: exact !important;
				print-color-adjust: exact !important;
			}

			.singo-page-corner-item {
				position: absolute !important;
				width: 30mm !important;
				height: 96mm !important;
				pointer-events: none !important;
				z-index: 1 !important;
			}

			.singo-page-corner-item--right {
				right: 0 !important;
			}

			.singo-page-corner-item--left {
				left: 0 !important;
			}

			.singo-price-table {
				position: relative !important;
				z-index: 2 !important;
				width: 100% !important;
				background: transparent !important;
			}

			.singo-price-table tbody tr {
				background: transparent !important;
			}

			.singo-price-table tbody tr:nth-child(even) {
				background-color: rgba(248, 250, 252, 0.45) !important;
			}

			.page-header-cell {
				padding: 24px 0 15px 0 !important;
				border-bottom: 2px solid <?php echo esc_attr( $primary_color ); ?> !important;
			}

			.header-doc-title {
				color: <?php echo esc_attr( $primary_color ); ?> !important;
				white-space: nowrap !important;
			}
			}

			.td-price {
				color: <?php echo esc_attr( $primary_color ); ?> !important;
			}
			.td-price .singo-price-sale {
				color: <?php echo esc_attr( $primary_color ); ?> !important;
			}
		}
	</style>
</head>
<body>

	<!-- Screen Floating Actions Toolbar -->
	<div class="singo-screen-toolbar no-print">
		<div class="singo-toolbar-title" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
			<span style="font-size: 20px;">📄</span>
			<strong>پیش‌نمایش چاپ و خروجی PDF لیست قیمت</strong>
			<span style="font-size: 12px; color: #64748b; font-weight: normal;">(تعداد: <?php echo esc_html( (string) count( $products_data ) ); ?> کالا)</span>
			<span style="font-size: 12px; color: #0369a1; background: #f0f9ff; border: 1px solid #bae6fd; padding: 4px 12px; border-radius: 6px; margin-right: 15px; font-weight: 600;">
				💡 در کادر چاپ، مقصد را روی <strong>«ذخیره به صورت PDF» (Save as PDF)</strong> قرار دهید.
			</span>
		</div>
		<div class="singo-toolbar-actions">
			<button type="button" class="singo-btn singo-btn-primary" onclick="if (typeof window.triggerSingoPrint === 'function') { window.triggerSingoPrint(); } else { window.print(); }">
				<span style="font-size: 16px;">🖨️</span>
				ذخیره به عنوان فایل PDF / چاپ
			</button>
			<button type="button" class="singo-btn singo-btn-secondary" onclick="window.close();">
				بستن برگه
			</button>
		</div>
	</div>

	<div id="singo-pdf-export-container">
		<!-- Custom Cover Page (Page 1: Full-Page A4 Graphic) -->
		<?php if ( $show_cover && ! empty( $cover_image ) ) : ?>
			<div class="singo-print-wrapper singo-print-wrapper--cover">
				<div class="singo-pdf-cover-page singo-pdf-cover-page--image-only">
					<img src="<?php echo esc_url( $cover_image ); ?>" alt="کاور لیست قیمت" class="singo-pdf-cover-full-image no-lazy skip-lazy" loading="eager" decoding="sync" fetchpriority="high" data-no-lazy="1" data-perfmatters-preload="1" />
				</div>
			</div>
		<?php endif; ?>

		<!-- Printable A4 Document Sheet -->
		<div class="singo-print-wrapper singo-print-wrapper--content">
		<?php if ( $has_inline_pattern ) : ?>
			<div id="singo-page-patterns-layer" class="singo-page-patterns-layer" aria-hidden="true">
				<div class="singo-page-corner-item singo-page-corner-item--right" style="top: 0mm;">
					<?php echo $pattern_svg_right; ?>
				</div>
				<?php if ( count( $products_data ) > 8 ) : ?>
					<div class="singo-page-corner-item singo-page-corner-item--left" style="top: 297mm;">
						<?php echo $pattern_svg_left; ?>
					</div>
				<?php endif; ?>
			</div>
			<template id="singo-tpl-pattern-right"><?php echo $pattern_svg_right; ?></template>
			<template id="singo-tpl-pattern-left"><?php echo $pattern_svg_left; ?></template>
		<?php endif; ?>
		<table class="singo-price-table">
			<colgroup>
				<?php if ( $show_brand ) : ?>
					<col style="width: 5%;">
					<col style="width: 9%;">
					<col style="width: 48%;">
					<col style="width: 15%;">
					<col style="width: 23%;">
				<?php else : ?>
					<col style="width: 5%;">
					<col style="width: 10%;">
					<col style="width: 60%;">
					<col style="width: 25%;">
				<?php endif; ?>
			</colgroup>
			
			<!-- Repeating Header on Every Page -->
			<thead>
				<tr>
					<th colspan="<?php echo esc_attr( (string) $total_cols ); ?>" class="page-header-cell">
						<div class="page-header-grid">
							
							<!-- Top-Right: Shop Logo -->
							<div class="header-right">
								<?php if ( $show_logo && ! empty( $custom_logo ) ) : ?>
									<img src="<?php echo esc_url( $custom_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="header-logo-img no-lazy skip-lazy" loading="eager" decoding="sync" fetchpriority="high" data-no-lazy="1" data-perfmatters-preload="1" />
								<?php endif; ?>
							</div>

							<!-- Center: Document Title -->
							<div class="header-center">
								<?php if ( $show_title && ! empty( $doc_title ) ) : ?>
									<h1 class="header-doc-title"><?php echo esc_html( $doc_title ); ?></h1>
									<p class="header-doc-subtitle"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
								<?php endif; ?>
							</div>

							<!-- Top-Left: Today's Shamsi Date -->
							<div class="header-left">
								<div class="header-date-badge">
									تاریخ: <?php echo esc_html( $shamsi_date ); ?>
								</div>
							</div>

						</div>
					</th>
				</tr>

				<!-- Table Column Headers -->
				<tr class="table-columns-header">
					<th class="th-index">ردیف</th>
					<th class="th-image">عکس</th>
					<th class="th-name">اسم محصول</th>
					<?php if ( $show_brand ) : ?>
						<th class="th-brand">برند</th>
					<?php endif; ?>
					<th class="th-price">قیمت (تومان)</th>
				</tr>
			</thead>

			<!-- Table Body with Products -->
			<tbody>
				<?php if ( empty( $products_data ) ) : ?>
					<tr>
						<td colspan="<?php echo esc_attr( (string) $total_cols ); ?>" style="text-align: center; padding: 40px; color: #64748b;">
							هیچ محصولی با معیارهای انتخابی در این دسته‌بندی یافت نشد.
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $products_data as $index => $prod ) : 
						$row_num = class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( (string) ( $index + 1 ) ) : (string) ( $index + 1 );
					?>
						<tr>
							<td class="td-index"><?php echo esc_html( $row_num ); ?></td>
							<td class="td-image">
								<?php if ( ! empty( $prod['thumb_url'] ) ) : ?>
									<img src="<?php echo esc_url( $prod['thumb_url'] ); ?>" alt="<?php echo esc_attr( $prod['title'] ); ?>" class="product-thumb-img no-lazy skip-lazy" loading="eager" decoding="sync" fetchpriority="high" data-no-lazy="1" data-perfmatters-preload="1" />
								<?php else : ?>
									<span style="font-size: 10px; color: #cbd5e1;">بدون تصویر</span>
								<?php endif; ?>
							</td>
							<td class="td-name">
								<span class="product-title-text"><?php echo esc_html( $prod['title'] ); ?></span>
								<?php if ( ! empty( $prod['short_desc'] ) ) : ?>
									<div class="product-short-desc"><?php echo esc_html( $prod['short_desc'] ); ?></div>
								<?php endif; ?>
								<?php if ( ! empty( $prod['sku'] ) ) : ?>
									<span class="product-sku-badge">کد کالا (SKU): <?php echo esc_html( class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( $prod['sku'] ) : $prod['sku'] ); ?></span>
								<?php endif; ?>
							</td>
							<?php if ( $show_brand ) : ?>
								<td class="td-brand">
									<?php if ( ! empty( $prod['brand_logo'] ) ) : ?>
										<img src="<?php echo esc_url( $prod['brand_logo'] ); ?>" alt="<?php echo esc_attr( $prod['brand_name'] ?? 'برند' ); ?>" class="brand-logo-img no-lazy skip-lazy" loading="eager" decoding="sync" fetchpriority="high" data-no-lazy="1" data-perfmatters-preload="1" />
									<?php elseif ( ! empty( $prod['brand_name'] ) ) : ?>
										<span class="brand-name-badge"><?php echo esc_html( $prod['brand_name'] ); ?></span>
									<?php else : ?>
										<span class="brand-empty-dash">-</span>
									<?php endif; ?>
								</td>
							<?php endif; ?>
							<td class="td-price">
								<?php echo wp_kses_post( $prod['price_html'] ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>

			<!-- Repeating Footer on Every Page with Phone & Address Icons -->
			<tfoot>
				<tr>
					<td colspan="<?php echo esc_attr( (string) $total_cols ); ?>" class="page-footer-cell">
						<div class="footer-content-flex">
							
							<!-- Right: Contact Group (Phone & Address with Vector Icons) -->
							<div class="footer-contacts-group">
								<?php if ( ! empty( $footer_phone ) ) : ?>
									<span class="footer-contact-item">
										<svg class="footer-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr( $primary_color ); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; min-width: 15px; vertical-align: middle; display: inline-block;">
											<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
										</svg>
										<span><?php echo esc_html( class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( $footer_phone ) : $footer_phone ); ?></span>
									</span>
								<?php endif; ?>

								<?php if ( ! empty( $footer_address ) ) : ?>
									<span class="footer-contact-item">
										<svg class="footer-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr( $primary_color ); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px; min-width: 15px; vertical-align: middle; display: inline-block;">
											<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
											<circle cx="12" cy="10" r="3"></circle>
										</svg>
										<span><?php echo esc_html( $footer_address ); ?></span>
									</span>
								<?php endif; ?>
							</div>

							<!-- Center: Notes / Disclaimer -->
							<div class="footer-notes">
								<?php if ( $show_notes && ! empty( $custom_notes ) ) : ?>
									<?php echo esc_html( $custom_notes ); ?>
								<?php endif; ?>
							</div>

							<!-- Left: Domain -->
							<div class="footer-domain" dir="ltr">
								<?php echo esc_html( $site_domain ); ?>
							</div>

						</div>
					</td>
				</tr>
			</tfoot>

		</table>
		</div>
	</div>

	<script>
		(function() {
			function fitHeaderDocTitle() {
				var titles = document.querySelectorAll('.header-doc-title');
				titles.forEach(function(title) {
					var parent = title.parentElement;
					if (!parent) return;
					var maxW = (parent.clientWidth || 340) - 8;
					var currentFs = 16.5;
					title.style.fontSize = currentFs + 'px';
					while (title.scrollWidth > maxW && currentFs > 10.5) {
						currentFs -= 0.5;
						title.style.fontSize = currentFs + 'px';
					}
				});
			}

			function ensureAllImagesLoaded(callback) {
				var imgs = Array.from(document.querySelectorAll('img'));
				imgs.forEach(function(img) {
					var dataSrc = img.getAttribute('data-src');
					if (dataSrc && (!img.src || img.src.indexOf('data:image/svg') !== -1)) {
						img.src = dataSrc;
					}
					var dataSrcset = img.getAttribute('data-srcset');
					if (dataSrcset && !img.srcset) {
						img.srcset = dataSrcset;
					}
				});

				var pending = imgs.filter(function(img) {
					return !img.complete || img.naturalWidth === 0;
				});

				if (pending.length === 0) {
					if (typeof callback === 'function') callback();
					return;
				}

				var remaining = pending.length;
				var timer = setTimeout(function() {
					remaining = 0;
					if (typeof callback === 'function') callback();
				}, 3000);

				pending.forEach(function(img) {
					function done() {
						remaining--;
						if (remaining <= 0) {
							clearTimeout(timer);
							if (typeof callback === 'function') callback();
						}
					}
					if (img.decode) {
						img.decode().then(done).catch(done);
					} else {
						img.onload = done;
						img.onerror = done;
					}
				});
			}

			function syncSingoPageCornerPatterns() {
				var layer = document.getElementById('singo-page-patterns-layer');
				var wrapper = document.querySelector('.singo-print-wrapper--content');
				var table = document.querySelector('.singo-price-table');
				var tplRight = document.getElementById('singo-tpl-pattern-right');
				var tplLeft = document.getElementById('singo-tpl-pattern-left');
				if (!layer || !wrapper || !table || !tplRight || !tplLeft) {
					return;
				}

				var probe = document.createElement('div');
				probe.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:297mm;visibility:hidden;pointer-events:none;';
				wrapper.appendChild(probe);
				var pageHeightPx = probe.getBoundingClientRect().height || 1122.52;
				wrapper.removeChild(probe);

				var thead = table.querySelector('thead');
				var tfoot = table.querySelector('tfoot');
				var tbody = table.querySelector('tbody');
				var theadH = thead ? thead.getBoundingClientRect().height : 0;
				var tfootH = tfoot ? tfoot.getBoundingClientRect().height : 0;

				var availableBodyH = Math.max(180, pageHeightPx - theadH - tfootH - 6);
				var totalPages = 1;

				if (tbody) {
					var rows = tbody.querySelectorAll('tr');
					var currentUsedH = 0;
					var rowsOnPage = 0;
					for (var i = 0; i < rows.length; i++) {
						var rH = rows[i].getBoundingClientRect().height || 0;
						if (rowsOnPage > 0 && (currentUsedH + rH) > availableBodyH) {
							totalPages++;
							currentUsedH = rH;
							rowsOnPage = 1;
						} else {
							currentUsedH += rH;
							rowsOnPage++;
						}
					}
				}

				var html = '';
				for (var p = 1; p <= totalPages; p++) {
					var isOdd = (p % 2 === 1);
					var sideClass = isOdd ? 'singo-page-corner-item--right' : 'singo-page-corner-item--left';
					var topMm = (p - 1) * 297;
					var svgMarkup = isOdd ? tplRight.innerHTML : tplLeft.innerHTML;
					html += '<div class="singo-page-corner-item ' + sideClass + '" style="top:' + topMm + 'mm;">' + svgMarkup + '</div>';
				}
				layer.innerHTML = html;
			}

			function triggerSingoPrint() {
				fitHeaderDocTitle();
				syncSingoPageCornerPatterns();
				ensureAllImagesLoaded(function() {
					fitHeaderDocTitle();
					syncSingoPageCornerPatterns();
					window.print();
				});
			}

			window.triggerSingoPrint = triggerSingoPrint;
			window.fitHeaderDocTitle = fitHeaderDocTitle;
			window.syncSingoPageCornerPatterns = syncSingoPageCornerPatterns;
			window.ensureAllImagesLoaded = ensureAllImagesLoaded;

			document.addEventListener('DOMContentLoaded', function() {
				fitHeaderDocTitle();
				syncSingoPageCornerPatterns();
				ensureAllImagesLoaded();
			});

			window.addEventListener('load', function() {
				fitHeaderDocTitle();
				syncSingoPageCornerPatterns();
				ensureAllImagesLoaded();
			});

			window.addEventListener('resize', fitHeaderDocTitle);
			window.addEventListener('beforeprint', function() {
				fitHeaderDocTitle();
				syncSingoPageCornerPatterns();
			});

			if (document.fonts && document.fonts.ready) {
				document.fonts.ready.then(function() {
					fitHeaderDocTitle();
					syncSingoPageCornerPatterns();
				});
			}
		})();
	</script>

	<?php if ( $autoprint && ! empty( $products_data ) ) : ?>
		<script>
			window.addEventListener('load', function() {
				if (typeof window.triggerSingoPrint === 'function') {
					setTimeout(window.triggerSingoPrint, 350);
				} else {
					window.print();
				}
			});
		</script>
	<?php endif; ?>

</body>
</html>
