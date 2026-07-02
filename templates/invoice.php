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
	* { box-sizing: border-box; margin: 0; padding: 0; }
	body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #f4f4f4; }
	.irixfsl-doc { background: #fff; width: 210mm; margin: 10px auto; padding: 20mm 18mm; page-break-after: always; }
	.irixfsl-doc:last-child { page-break-after: auto; }

	/* Header */
	.doc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
	.doc-header .company-logo img { max-height: 70px; max-width: 200px; }
	.doc-header .doc-title { text-align: right; }
	.doc-header .doc-title h1 { font-size: 28px; font-weight: 700; letter-spacing: 2px; color: #111; text-transform: uppercase; }
	.doc-header .doc-title .invoice-meta { margin-top: 8px; color: #555; font-size: 12px; line-height: 1.7; }
	.doc-header .doc-title .invoice-meta strong { color: #111; }

	.company-details { font-size: 12px; color: #444; line-height: 1.7; margin-top: 6px; }
	.company-details strong { display: block; font-size: 14px; color: #111; margin-bottom: 2px; }

	/* Address row */
	.address-row { display: flex; gap: 30px; margin-bottom: 24px; }
	.address-box { flex: 1; }
	.address-box h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; border-bottom: 1px solid #e5e5e5; padding-bottom: 4px; }
	.address-box address { font-style: normal; font-size: 12px; line-height: 1.7; color: #333; }

	/* Divider */
	.divider { border: none; border-top: 2px solid #111; margin: 20px 0; }

	/* Items table */
	.items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
	.items-table thead th { background: #111; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 9px 10px; text-align: left; }
	.items-table thead th.text-right { text-align: right; }
	.items-table tbody tr:nth-child(even) td { background: #fafafa; }
	.items-table tbody td { padding: 9px 10px; font-size: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
	.items-table tbody td.text-right { text-align: right; }
	.items-table tfoot td { padding: 6px 10px; font-size: 12px; }
	.items-table tfoot tr.total-row td { font-weight: 700; font-size: 13px; border-top: 2px solid #111; padding-top: 10px; }
	.items-table .item-meta { font-size: 11px; color: #888; margin-top: 3px; }

	/* Totals */
	.totals-wrap { display: flex; justify-content: flex-end; }
	.totals-table { width: 260px; }
	.totals-table td { padding: 5px 10px; font-size: 12px; }
	.totals-table td:last-child { text-align: right; }
	.totals-table .grand-total td { font-weight: 700; font-size: 14px; border-top: 2px solid #111; padding-top: 8px; }

	/* Status badge */
	.payment-status { display: inline-block; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
	.payment-status.paid    { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
	.payment-status.pending { background: #fff8e1; color: #7c5500; border: 1px solid #ffe082; }
	.payment-status.not-paid { background: #fdecea; color: #7f0000; border: 1px solid #ef9a9a; }

	/* Footer */
	.doc-footer { margin-top: 30px; border-top: 1px solid #e5e5e5; padding-top: 14px; text-align: center; font-size: 11px; color: #888; }

	/* Print button (screen only) */
	.print-bar { background: #1a1a1a; color: #fff; text-align: center; padding: 12px; position: sticky; top: 0; z-index: 999; }
	.print-bar button { background: #fff; color: #1a1a1a; border: none; padding: 8px 24px; font-size: 14px; font-weight: 600; cursor: pointer; border-radius: 3px; }
	.print-bar button:hover { background: #e0e0e0; }
	@media print {
		body { background: white; }
		.print-bar { display: none; }
		.irixfsl-doc { margin: 0; padding: 15mm 12mm; box-shadow: none; width: 100%; }
	}
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
				<?php foreach ( $items as $item ) :
					/** @var WC_Order_Item_Product $item */
					$product  = $item->get_product();
					$sku      = $product ? $product->get_sku() : '';
					$qty   = $item->get_quantity();
					$total = $item->get_total();
					// Derive the unit price from the discounted line total so that
					// unit × qty always equals the Total column, coupons included.
					$unit  = $qty > 0 ? $total / $qty : 0;
					?>
					<tr>
						<td>
							<?php echo esc_html( $item->get_name() ); ?>
							<?php echo IRIXFSL_Helpers::render_item_meta( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
						<td><?php echo esc_html( $sku ?: '—' ); ?></td>
						<td class="text-right"><?php echo wp_kses_post( wc_price( $unit, [ 'currency' => $currency ] ) ); ?></td>
						<td class="text-right"><?php echo esc_html( $qty ); ?></td>
						<td class="text-right"><?php echo wp_kses_post( wc_price( $total, [ 'currency' => $currency ] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="totals-wrap">
			<table class="totals-table">
				<?php
				$subtotal    = $order->get_subtotal();
				$shipping    = $order->get_shipping_total();
				$discount    = $order->get_discount_total();
				$tax         = $order->get_total_tax();
				$grand_total = $order->get_total();
				?>
				<tr>
					<td><?php esc_html_e( 'Subtotal', 'irix-fulfillment-sl' ); ?></td>
					<td><?php echo wp_kses_post( wc_price( $subtotal, [ 'currency' => $currency ] ) ); ?></td>
				</tr>
				<?php if ( $shipping > 0 ) : ?>
				<tr>
					<td><?php esc_html_e( 'Shipping', 'irix-fulfillment-sl' ); ?></td>
					<td><?php echo wp_kses_post( wc_price( $shipping, [ 'currency' => $currency ] ) ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $discount > 0 ) : ?>
				<tr>
					<td><?php esc_html_e( 'Discount', 'irix-fulfillment-sl' ); ?></td>
					<td>-<?php echo wp_kses_post( wc_price( $discount, [ 'currency' => $currency ] ) ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $tax > 0 ) : ?>
				<tr>
					<td><?php esc_html_e( 'Tax', 'irix-fulfillment-sl' ); ?></td>
					<td><?php echo wp_kses_post( wc_price( $tax, [ 'currency' => $currency ] ) ); ?></td>
				</tr>
				<?php endif; ?>
				<tr class="grand-total">
					<td><?php esc_html_e( 'Total', 'irix-fulfillment-sl' ); ?></td>
					<td><?php echo wp_kses_post( wc_price( $grand_total, [ 'currency' => $currency ] ) ); ?></td>
				</tr>
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
