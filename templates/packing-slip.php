<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
<title><?php esc_html_e( 'Packing Slips', 'irix-fulfillment-sl' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( $print_url ); ?>">
<style>
	/* Minimal document style: system font, hairlines, one dark accent. */
	* { box-sizing: border-box; margin: 0; padding: 0; }
	body {
		font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
		font-size: 13px;
		line-height: 1.5;
		color: #18181b;
		background: #f4f4f5;
		-webkit-font-smoothing: antialiased;
	}
	.irixfsl-doc { background: #fff; width: 210mm; margin: 24px auto; page-break-after: always; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
	.irixfsl-doc:last-child { page-break-after: auto; }

	/* Floating print button (screen only) */
	.print-bar { position: fixed; top: 16px; right: 16px; z-index: 999; }
	.print-bar button {
		padding: 9px 18px;
		background: #18181b;
		color: #fff;
		border: 0;
		border-radius: 8px;
		font: 500 13px/1 inherit;
		font-family: inherit;
		cursor: pointer;
		box-shadow: 0 4px 12px rgba(0,0,0,.15);
	}
	.print-bar button:hover { background: #3f3f46; }

	/* Shared pieces */
	.address-row { display: flex; gap: 32px; }
	.address-box { flex: 1; }
	.address-box h4 { margin-bottom: 6px; font-size: 10px; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; color: #a1a1aa; }
	.address-box address { font-style: normal; font-size: 12px; line-height: 1.65; color: #3f3f46; }
	.address-box address strong { color: #18181b; font-weight: 600; }

	.items-table { width: 100%; border-collapse: collapse; }
	.items-table thead th {
		padding: 0 10px 8px;
		border-bottom: 1px solid #e4e4e7;
		background: none;
		font-size: 10px;
		font-weight: 500;
		letter-spacing: .08em;
		text-transform: uppercase;
		text-align: left;
		color: #a1a1aa;
	}
	.items-table thead th:first-child,
	.items-table tbody td:first-child { padding-left: 0; }
	.items-table thead th:last-child,
	.items-table tbody td:last-child { padding-right: 0; }
	.items-table thead th.text-right { text-align: right; }
	.items-table tbody td { padding: 11px 10px; font-size: 12px; border-bottom: 1px solid #f4f4f5; vertical-align: top; background: none; }
	.items-table tbody td.text-right { text-align: right; font-variant-numeric: tabular-nums; }
	.item-meta, .items-table .item-meta { margin-top: 3px; font-size: 11px; color: #a1a1aa; }

	.doc-footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #f4f4f5; text-align: center; font-size: 11px; color: #a1a1aa; }

	@media print {
		body { background: #fff; }
		.print-bar { display: none; }
		.irixfsl-doc { margin: 0; width: 100%; border-radius: 0; box-shadow: none; }
	}

	.irixfsl-doc { padding: 14mm; }

	.doc-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 28px; }
	.company-logo img { max-height: 44px; margin-bottom: 8px; }
	.company-info { font-size: 11px; line-height: 1.6; color: #71717a; }
	.company-info strong { color: #18181b; font-weight: 600; }
	.doc-title { text-align: right; }
	.doc-title h1 { font-size: 20px; font-weight: 600; letter-spacing: -.01em; color: #18181b; }
	.doc-title .slip-meta { margin-top: 6px; font-size: 12px; line-height: 1.8; color: #71717a; }
	.doc-title .slip-meta strong { font-weight: 500; color: #3f3f46; }

	hr { display: none; }
	.address-row { margin-bottom: 24px; }
	.items-table tbody td.text-right { font-weight: 600; font-size: 13px; }

	.tracking-row { margin-top: 20px; padding: 12px 14px; background: #fafafa; border-radius: 8px; font-size: 12px; color: #3f3f46; }
	.tracking-row strong { display: block; margin-bottom: 4px; font-size: 10px; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; color: #a1a1aa; }
</style>
</head>
<body>
<div class="print-bar">
	<button onclick="window.print()"><?php esc_html_e( 'Print Packing Slips', 'irix-fulfillment-sl' ); ?></button>
</div>

<?php foreach ( $orders as $order ) :
	$items    = $order->get_items();
	$tracking = IRIXFSL_Tracking::get_tracking( $order );
	$ship     = IRIXFSL_Helpers::get_ship_to( $order );
	$ship_to  = $ship['address'];
	?>
	<div class="irixfsl-doc">
		<div class="doc-header">
			<div>
				<?php if ( $logo_url ) : ?>
					<div class="company-logo"><img src="<?php echo esc_url( $logo_url ); ?>" alt=""></div>
				<?php endif; ?>
				<div class="company-info">
					<strong><?php echo esc_html( $s['company_name'] ); ?></strong>
					<?php if ( $s['company_address'] ) echo '<br>' . nl2br( esc_html( $s['company_address'] ) ); ?>
				</div>
			</div>
			<div class="doc-title" style="text-align:right">
				<h1><?php esc_html_e( 'Packing Slip', 'irix-fulfillment-sl' ); ?></h1>
				<div class="slip-meta">
					<div><strong><?php esc_html_e( 'Order #', 'irix-fulfillment-sl' ); ?></strong><?php echo esc_html( $order->get_order_number() ); ?></div>
					<div><strong><?php esc_html_e( 'Date:', 'irix-fulfillment-sl' ); ?></strong><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></div>
					<div><strong><?php esc_html_e( 'Items:', 'irix-fulfillment-sl' ); ?></strong><?php echo esc_html( $order->get_item_count() ); ?></div>
				</div>
			</div>
		</div>

		<hr>

		<div class="address-row">
			<div class="address-box">
				<h4><?php esc_html_e( 'Ship To', 'irix-fulfillment-sl' ); ?></h4>
				<address>
					<strong><?php echo esc_html( $ship['name'] ); ?></strong><br>
					<?php echo wp_kses_post( $ship_to ); ?>
					<?php if ( $order->get_billing_phone() ) echo '<br>' . esc_html( $order->get_billing_phone() ); ?>
				</address>
			</div>
			<div class="address-box">
				<h4><?php esc_html_e( 'Ship From', 'irix-fulfillment-sl' ); ?></h4>
				<address>
					<strong><?php echo esc_html( $s['company_name'] ); ?></strong><br>
					<?php echo nl2br( esc_html( $s['company_address'] ) ); ?>
				</address>
			</div>
		</div>

		<table class="items-table">
			<thead>
				<tr>
					<th style="width:50%"><?php esc_html_e( 'Product', 'irix-fulfillment-sl' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'irix-fulfillment-sl' ); ?></th>
					<th class="text-right"><?php esc_html_e( 'Qty', 'irix-fulfillment-sl' ); ?></th>
					<th><?php esc_html_e( 'Picked', 'irix-fulfillment-sl' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) :
					$product = $item->get_product();
					$sku     = $product ? $product->get_sku() : '';
					?>
					<tr>
						<td>
							<?php echo esc_html( $item->get_name() ); ?>
							<?php echo IRIXFSL_Helpers::render_item_meta( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
						<td><?php echo esc_html( $sku ?: '—' ); ?></td>
						<td class="text-right"><?php echo esc_html( $item->get_quantity() ); ?></td>
						<td style="width:60px"><span style="display:inline-block;width:20px;height:20px;border:2px solid #999;border-radius:3px"></span></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $tracking['number'] ) : ?>
		<div class="tracking-row">
			<strong><?php esc_html_e( 'Tracking Information', 'irix-fulfillment-sl' ); ?></strong>
			<?php esc_html_e( 'Carrier:', 'irix-fulfillment-sl' ); ?> <?php echo esc_html( $tracking['carrier'] ); ?> &nbsp;|&nbsp;
			<?php esc_html_e( 'Tracking #:', 'irix-fulfillment-sl' ); ?> <?php echo esc_html( $tracking['number'] ); ?>
		</div>
		<?php endif; ?>

		<?php if ( $order->get_customer_note() ) : ?>
		<p style="margin-top:12px;font-size:12px;padding:8px 10px;background:#fffbe6;border-left:3px solid #f0b429">
			<strong><?php esc_html_e( 'Customer Note:', 'irix-fulfillment-sl' ); ?></strong>
			<?php echo esc_html( $order->get_customer_note() ); ?>
		</p>
		<?php endif; ?>

		<div class="doc-footer"><?php echo esc_html( $s['company_name'] ); ?> &mdash; <?php esc_html_e( 'Thank you!', 'irix-fulfillment-sl' ); ?></div>
	</div>
<?php endforeach; ?>

<script>
window.addEventListener('load', function(){ window.print(); });
</script>
</body>
</html>
