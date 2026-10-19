<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php
	if ( ! empty( $bulk ) ) {
		esc_html_e( 'Invoices', 'irix-fulfillment-sl' );
	} else {
		printf( esc_html__( 'Invoice #%s', 'irix-fulfillment-sl' ), esc_html( $order->get_order_number() ) );
	}
?></title>
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

	.irixfsl-doc { padding: 18mm 18mm 16mm; }

	.doc-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 36px; }
	.doc-header .company-logo img { max-height: 48px; max-width: 180px; margin-bottom: 10px; }
	.company-details { font-size: 12px; line-height: 1.65; color: #71717a; }
	.company-details strong { display: block; margin-bottom: 2px; font-size: 14px; font-weight: 600; color: #18181b; }
	.doc-header .doc-title { text-align: right; }
	.doc-header .doc-title h1 { font-size: 22px; font-weight: 600; letter-spacing: -.01em; color: #18181b; }
	.doc-header .doc-title .invoice-meta { margin-top: 8px; font-size: 12px; line-height: 1.8; color: #71717a; }
	.doc-header .doc-title .invoice-meta strong { font-weight: 500; color: #3f3f46; }

	.divider { display: none; }
	.address-row { margin-bottom: 32px; }
	.items-table { margin-bottom: 8px; }

	.totals-wrap { display: flex; justify-content: flex-end; }
	.totals-table { width: 280px; border-collapse: collapse; }
	.totals-table td { padding: 6px 0; font-size: 12px; color: #71717a; }
	.totals-table td:last-child { text-align: right; color: #18181b; font-variant-numeric: tabular-nums; }
	.totals-table .grand-total td { padding-top: 12px; border-top: 1px solid #e4e4e7; font-size: 15px; font-weight: 600; color: #18181b; }

	.payment-status {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		margin-top: 6px;
		font-size: 11px;
		font-weight: 500;
		color: #3f3f46;
	}
	.payment-status::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: #a1a1aa; }
	.payment-status.paid::before     { background: #16a34a; }
	.payment-status.pending::before  { background: #d97706; }
	.payment-status.not-paid::before { background: #dc2626; }
</style>
</head>
<body>
<div class="print-bar">
	<button onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'irix-fulfillment-sl' ); ?></button>
</div>

<?php
$render_invoice = function( WC_Order $order ) use ( $s, $logo_url ) {
	$currency   = $order->get_currency();
	$items      = $order->get_items();
	$order_date = wc_format_datetime( $order->get_date_created() );
	$tracking   = IRIXFSL_Tracking::get_tracking( $order );
	$ship       = IRIXFSL_Helpers::get_ship_to( $order );

	// ── Determine payment badge ───────────────────────────────────────
	$payment_method = $order->get_payment_method();   // 'cod', 'bacs', 'paypal', …
	$order_status   = $order->get_status();            // 'on-hold', 'processing', 'completed', …

	if ( $order_status === 'completed' ) {
		// A completed order is always fully settled regardless of payment method.
		$badge_class = 'paid';
		$badge_label = __( 'Paid', 'irix-fulfillment-sl' );
	} elseif ( $payment_method === 'cod' ) {
		// Cash on delivery — money collected at the door, not at checkout.
		$badge_class = 'not-paid';
		$badge_label = __( 'Not Paid', 'irix-fulfillment-sl' );
	} else {
		// All other gateways (bank transfer, card, PayPal, etc.) — trust
		// WooCommerce's is_paid(). BACS stays "Pending" while on-hold and
		// becomes "Paid" once the admin confirms receipt, including after the
		// order moves on to Ready to Ship / Shipped.
		$badge_class = $order->is_paid() ? 'paid' : 'pending';
		$badge_label = ( $badge_class === 'paid' )
			? __( 'Paid', 'irix-fulfillment-sl' )
			: __( 'Pending Payment', 'irix-fulfillment-sl' );
	}
	?>
	<div class="irixfsl-doc">
		<div class="doc-header">
			<div class="company-left">
				<?php if ( $logo_url ) : ?>
					<div class="company-logo"><img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $s['company_name'] ); ?>"></div>
				<?php endif; ?>
				<div class="company-details">
					<strong><?php echo esc_html( $s['company_name'] ); ?></strong>
					<?php if ( $s['company_address'] ) echo nl2br( esc_html( $s['company_address'] ) ); ?>
					<?php if ( $s['company_phone'] ) echo '<br>' . esc_html( $s['company_phone'] ); ?>
					<?php if ( $s['company_email'] ) echo '<br>' . esc_html( $s['company_email'] ); ?>
				</div>
			</div>
			<div class="doc-title">
				<h1><?php esc_html_e( 'Invoice', 'irix-fulfillment-sl' ); ?></h1>
				<div class="invoice-meta">
					<div><strong><?php esc_html_e( 'Invoice #', 'irix-fulfillment-sl' ); ?></strong> <?php echo esc_html( $order->get_order_number() ); ?></div>
					<div><strong><?php esc_html_e( 'Date:', 'irix-fulfillment-sl' ); ?></strong> <?php echo esc_html( $order_date ); ?></div>
					<div><strong><?php esc_html_e( 'Payment:', 'irix-fulfillment-sl' ); ?></strong> <?php echo esc_html( $order->get_payment_method_title() ); ?></div>
					<div>
						<span class="payment-status <?php echo esc_attr( $badge_class ); ?>">
							<?php echo esc_html( $badge_label ); ?>
						</span>
					</div>
				</div>
			</div>
		</div>

		<hr class="divider">

		<div class="address-row">
			<div class="address-box">
				<h4><?php esc_html_e( 'Bill To', 'irix-fulfillment-sl' ); ?></h4>
				<address>
					<strong><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></strong><br>
					<?php
					$bill_addr = WC()->countries->get_formatted_address( [
						'address_1' => $order->get_billing_address_1(),
						'address_2' => $order->get_billing_address_2(),
						'city'      => $order->get_billing_city(),
						'state'     => $order->get_billing_state(),
						'postcode'  => $order->get_billing_postcode(),
						'country'   => $order->get_billing_country(),
					] );
					if ( $bill_addr ) echo wp_kses_post( $bill_addr ) . '<br>';
					?>
					<?php if ( $order->get_billing_phone() ) echo esc_html( $order->get_billing_phone() ) . '<br>'; ?>
					<?php echo esc_html( $order->get_billing_email() ); ?>
				</address>
			</div>
			<div class="address-box">
				<h4><?php esc_html_e( 'Ship To', 'irix-fulfillment-sl' ); ?></h4>
				<address>
					<strong><?php echo esc_html( $ship['name'] ); ?></strong><br>
					<?php if ( $ship['address'] ) echo wp_kses_post( $ship['address'] ); ?>
				</address>
			</div>
			<?php if ( $tracking['carrier'] ) : ?>
			<div class="address-box">
				<h4><?php esc_html_e( 'Tracking', 'irix-fulfillment-sl' ); ?></h4>
				<address>
					<strong><?php echo esc_html( $tracking['carrier'] ); ?></strong><br>
					<?php echo esc_html( $tracking['number'] ); ?>
					<?php if ( $tracking['url'] ) : ?>
						<br><a href="<?php echo esc_url( $tracking['url'] ); ?>"><?php esc_html_e( 'Track shipment', 'irix-fulfillment-sl' ); ?></a>
					<?php endif; ?>
				</address>
			</div>
			<?php endif; ?>
		</div>

		<table class="items-table">
			<thead>
				<tr>
					<th style="width:40%"><?php esc_html_e( 'Product', 'irix-fulfillment-sl' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'irix-fulfillment-sl' ); ?></th>
					<th class="text-right"><?php esc_html_e( 'Unit Price', 'irix-fulfillment-sl' ); ?></th>
					<th class="text-right"><?php esc_html_e( 'Qty', 'irix-fulfillment-sl' ); ?></th>
					<th class="text-right"><?php esc_html_e( 'Total', 'irix-fulfillment-sl' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				// Line items are shown pre-discount, matching WC's own Subtotal row
				// below; the coupon discount is listed separately in the totals.
				$incl_tax = 'incl' === get_option( 'woocommerce_tax_display_cart' );
				foreach ( $items as $item ) :
					/** @var WC_Order_Item_Product $item */
					$product  = $item->get_product();
					$sku      = $product ? $product->get_sku() : '';
					?>
					<tr>
						<td>
							<?php echo esc_html( $item->get_name() ); ?>
							<?php echo IRIXFSL_Helpers::render_item_meta( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
						<td><?php echo esc_html( $sku ?: '—' ); ?></td>
						<td class="text-right"><?php echo wp_kses_post( wc_price( $order->get_item_subtotal( $item, $incl_tax ), [ 'currency' => $currency ] ) ); ?></td>
						<td class="text-right"><?php echo esc_html( $item->get_quantity() ); ?></td>
						<td class="text-right"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="totals-wrap">
			<table class="totals-table">
				<?php
				// WC's canonical totals: subtotal, discount, shipping, every fee
				// (incl. Local Delivery Fee), tax, total and refunds — always adds up.
				$totals = $order->get_order_item_totals();
				unset( $totals['payment_method'] ); // Already shown in the header.
				foreach ( $totals as $key => $row ) :
					?>
					<tr<?php echo 'order_total' === $key ? ' class="grand-total"' : ''; ?>>
						<td><?php echo esc_html( rtrim( wp_strip_all_tags( $row['label'] ), ': ' ) ); ?></td>
						<td><?php echo wp_kses_post( $row['value'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<?php if ( $order->get_customer_note() ) : ?>
		<p style="margin-top:20px;font-size:12px;color:#555">
			<strong><?php esc_html_e( 'Note:', 'irix-fulfillment-sl' ); ?></strong>
			<?php echo esc_html( $order->get_customer_note() ); ?>
		</p>
		<?php endif; ?>

		<div class="doc-footer">
			<?php echo esc_html( $s['invoice_footer'] ); ?>
		</div>
	</div>
	<?php
};

if ( ! empty( $bulk ) && ! empty( $orders ) ) {
	foreach ( $orders as $order ) {
		$render_invoice( $order );
	}
} else {
	$render_invoice( $order );
}
?>

<script>
if ( window.location.search.indexOf('noprint') === -1 ) {
	window.addEventListener('load', function(){ window.print(); });
}
</script>
</body>
</html>
