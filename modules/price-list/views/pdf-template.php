<?php
declare(strict_types=1);

/**
 * Singo Tools - High-DPI A4 PDF / Printable Template for WooCommerce Price List
 * Layout: Multi-Page A4 with Repeating Header (Logo Right, Date Left)
 */

defined( 'ABSPATH' ) || exit;

// Security check
if ( ! function_exists( 'singo_tools_current_user_can_manage' ) || ! singo_tools_current_user_can_manage() ) {
	wp_die( 'سطح دسترسی نامعتبر است.' );
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

// Resolve Category Name
$category_name = 'همه محصولات';
if ( $category_id > 0 ) {
	$term = get_term( $category_id, 'product_cat' );
	if ( $term && ! is_wp_error( $term ) ) {
		$category_name = $term->name;
	}
}

// Resolve Document Title
$doc_title = ! empty( $custom_title ) ? $custom_title : ( 'لیست قیمت محصولات (' . $category_name . ')' );

// Resolve Logo URL (Only use customizer logo if set, never fallback to plugin logo)
if ( empty( $custom_logo ) ) {
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$custom_logo = (string) wp_get_attachment_image_url( (int) $custom_logo_id, 'full' );
	}
}

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
$shamsi_date = wp_date( 'Y/m/d' );
if ( class_exists( 'Singo_Shamsi_Converter' ) ) {
	$shamsi_date = Singo_Shamsi_Converter::format_date( time(), 'l، j F Y' );
}

// Fetch Products
$price_list_instance = class_exists( 'Singo_Price_List' ) ? Singo_Price_List::get_instance() : null;
$products_data       = $price_list_instance ? $price_list_instance->get_products_data( $category_id, $in_stock, $orderby, $order, $discount_percent, $hide_strike, $expand_vars ) : array();

// Resolve Font URL (Custom from URL parameter, or Saved Option)
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

// Resolve Brand / Primary Color (Custom from URL parameter, Saved Option, or Fallback #ad6141)
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
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $doc_title ); ?> - <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<style>
		<?php echo $font_face_css; ?>

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
			border-bottom: 2px solid #e2e8f0;
			padding: 12px 24px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
		}

		.singo-toolbar-title {
			font-size: 15px;
			font-weight: 700;
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
			font-family: <?php echo ! empty( $font_face_css ) ? "'SingoCustomFont', " : ''; ?>system-ui, -apple-system, Tahoma, sans-serif !important;
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 8px 18px;
			border-radius: 6px;
			font-size: 13px;
			font-weight: 700;
			cursor: pointer;
			border: none;
			transition: all 0.2s ease-in-out;
			text-decoration: none;
		}

		.singo-btn-primary {
			background: <?php echo esc_attr( $primary_color ); ?>;
			color: #ffffff;
		}

		.singo-btn-primary:hover {
			opacity: 0.9;
		}

		.singo-btn-secondary {
			background: #e2e8f0;
			color: #334155;
		}

		.singo-btn-secondary:hover {
			background: #cbd5e1;
		}

		/* A4 Page Container */
		.singo-print-wrapper {
			max-width: 210mm;
			margin: 20px auto;
			background: #ffffff;
			padding: 15mm 12mm;
			box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
			border-radius: 4px;
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
			padding: 0 0 15px 0;
			border-bottom: 2px solid <?php echo esc_attr( $primary_color ); ?>;
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
			width: 30%;
			justify-content: flex-start;
		}

		.header-logo-img {
			max-height: 52px;
			max-width: 130px;
			object-fit: contain;
			display: block;
		}

		.header-center {
			width: 40%;
			text-align: center;
		}

		.header-doc-title {
			margin: 0;
			font-size: 18px;
			font-weight: 800;
			color: <?php echo esc_attr( $primary_color ); ?>;
			letter-spacing: -0.5px;
		}

		.header-doc-subtitle {
			margin: 4px 0 0 0;
			font-size: 12px;
			color: #64748b;
			font-weight: 600;
		}

		.header-left {
			width: 30%;
			text-align: left;
			font-size: 12px;
			color: #475569;
		}

		.header-date-badge {
			display: inline-block;
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			padding: 4px 10px;
			border-radius: 4px;
			font-weight: 600;
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

		/* Exact Proportional Columns (Compact Index 5%, Spacious Name 60%) */
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

		/* Table Body Rows */
		.singo-price-table tbody tr {
			border-bottom: 1px solid #e2e8f0;
		}

		.singo-price-table tbody tr:nth-child(even) {
			background-color: #fbfcfe;
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
		}

		.product-sku-badge {
			font-size: 10.5px;
			color: #64748b;
			margin-top: 2px;
			display: block;
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

		/* Footer */
		.page-footer-cell {
			padding: 16px 0 0 0;
			border-top: 1px solid #cbd5e1;
		}

		.footer-content-flex {
			display: flex;
			justify-content: space-between;
			align-items: center;
			font-size: 11px;
			color: #64748b;
		}

		.footer-notes {
			font-weight: 600;
			color: #475569;
		}

		.footer-domain {
			font-weight: 700;
			color: #64748b;
			direction: ltr;
			unicode-bidi: embed;
		}

		/* Print Media Rules */
		@media print {
			@page {
				size: A4 portrait;
				margin: 12mm 10mm 14mm 10mm;
			}

			body {
				background: #ffffff !important;
				color: #000000 !important;
			}

			.singo-screen-toolbar {
				display: none !important;
			}

			.singo-print-wrapper {
				max-width: 100% !important;
				margin: 0 !important;
				padding: 0 !important;
				box-shadow: none !important;
				border-radius: 0 !important;
			}

			.singo-price-table {
				width: 100% !important;
			}

			.singo-price-table tbody tr:nth-child(even) {
				background-color: #f8fafc !important;
			}

			.page-header-cell {
				border-bottom: 2px solid <?php echo esc_attr( $primary_color ); ?> !important;
			}

			.header-doc-title {
				color: <?php echo esc_attr( $primary_color ); ?> !important;
			}

			.td-price {
				color: <?php echo esc_attr( $primary_color ); ?> !important;
			}
			.td-price .singo-price-sale {
				color: <?php echo esc_attr( $primary_color ); ?> !important;
			}
			<?php if ( $hide_strike ) : ?>
			.td-price .singo-price-regular,
			.td-price del {
				display: none !important;
			}
			<?php else : ?>
			.td-price .singo-price-regular,
			.td-price del {
				color: #94a3b8 !important;
				text-decoration: line-through !important;
			}
			<?php endif; ?>
		}
	</style>
</head>
<body>

	<!-- Screen Floating Actions Toolbar -->
	<div class="singo-screen-toolbar no-print">
		<div class="singo-toolbar-title">
			<span style="font-size: 20px;">📄</span>
			<span>پیش‌نمایش چاپ و خروجی PDF لیست قیمت</span>
			<span style="font-size: 12px; color: #64748b; font-weight: normal;">(تعداد: <?php echo esc_html( (string) count( $products_data ) ); ?> کالا)</span>
		</div>
		<div class="singo-toolbar-actions">
			<button type="button" class="singo-btn singo-btn-primary" onclick="window.print();">
				<span style="font-size: 16px;">🖨️</span>
				چاپ / ذخیره به عنوان فایل PDF
			</button>
			<button type="button" class="singo-btn singo-btn-secondary" onclick="window.close();">
				بستن برگه
			</button>
		</div>
	</div>

	<!-- Printable A4 Document Sheet -->
	<div class="singo-print-wrapper">
		<table class="singo-price-table">
			<colgroup>
				<col style="width: 5%;">
				<col style="width: 10%;">
				<col style="width: 60%;">
				<col style="width: 25%;">
			</colgroup>
			
			<!-- Repeating Header on Every Page -->
			<thead>
				<tr>
					<th colspan="4" class="page-header-cell">
						<div class="page-header-grid">
							
							<!-- Top-Right: Shop Logo -->
							<div class="header-right">
								<?php if ( $show_logo && ! empty( $custom_logo ) ) : ?>
									<img src="<?php echo esc_url( $custom_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="header-logo-img" />
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
					<th class="th-price">قیمت (تومان)</th>
				</tr>
			</thead>

			<!-- Table Body with Products -->
			<tbody>
				<?php if ( empty( $products_data ) ) : ?>
					<tr>
						<td colspan="4" style="text-align: center; padding: 40px; color: #64748b;">
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
									<img src="<?php echo esc_url( $prod['thumb_url'] ); ?>" alt="<?php echo esc_attr( $prod['title'] ); ?>" class="product-thumb-img" />
								<?php else : ?>
									<span style="font-size: 10px; color: #cbd5e1;">بدون تصویر</span>
								<?php endif; ?>
							</td>
							<td class="td-name">
								<span class="product-title-text"><?php echo esc_html( $prod['title'] ); ?></span>
								<?php if ( ! empty( $prod['sku'] ) ) : ?>
									<span class="product-sku-badge">کد کالا (SKU): <?php echo esc_html( class_exists( 'Singo_Shamsi_Converter' ) ? Singo_Shamsi_Converter::convert_to_persian_digits( $prod['sku'] ) : $prod['sku'] ); ?></span>
								<?php endif; ?>
							</td>
							<td class="td-price">
								<?php echo wp_kses_post( $prod['price_html'] ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>

			<!-- Repeating Footer on Every Page -->
			<tfoot>
				<tr>
					<td colspan="4" class="page-footer-cell">
						<div class="footer-content-flex">
							<div class="footer-notes">
								<?php if ( $show_notes && ! empty( $custom_notes ) ) : ?>
									<?php echo esc_html( $custom_notes ); ?>
								<?php endif; ?>
							</div>
							<div class="footer-domain" dir="ltr">
								<?php echo esc_html( $site_domain ); ?>
							</div>
						</div>
					</td>
				</tr>
			</tfoot>

		</table>
	</div>

	<?php if ( $autoprint && ! empty( $products_data ) ) : ?>
		<script>
			window.addEventListener('DOMContentLoaded', function() {
				setTimeout(function() {
					window.print();
				}, 600);
			});
		</script>
	<?php endif; ?>

</body>
</html>
