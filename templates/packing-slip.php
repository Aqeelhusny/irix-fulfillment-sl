<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
<title><?php esc_html_e( 'Packing Slips', 'wc-fulfillment-sl' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( $print_url ); ?>">
<style>
	* { box-sizing: border-box; margin: 0; padding: 0; }
	body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #f4f4f4; }
	.wcfsl-doc { background: #fff; width: 210mm; margin: 10px auto; padding: 14mm 14mm; page-break-after: always; }
	.wcfsl-doc:last-child { page-break-after: auto; }

	.doc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
	.company-logo img { max-height: 55px; }
	.doc-title h1 { font-size: 22px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #111; }
	.doc-title .slip-meta { font-size: 12px; color: #555; margin-top: 6px; line-height: 1.7; }
	.doc-title .slip-meta strong { color: #111; }
	.company-info { font-size: 11px; color: #555; line-height: 1.6; margin-top: 4px; }
	.company-info strong { color: #111; }

	hr { border: none; border-top: 2px solid #111; margin: 12px 0; }

	.address-row { display: flex; gap: 24px; margin-bottom: 16px; }
	.address-box { flex: 1; }
	.address-box h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 4px; }
	.address-box address { font-style: normal; font-size: 12px; line-height: 1.7; }

	.items-table { width: 100%; border-collapse: collapse; }
	.items-table thead th { background: #111; color: #fff; font-size: 11px; padding: 8px 10px; text-align: left; }
	.items-table thead th.text-right { text-align: right; }
	.items-table tbody tr:nth-child(even) td { background: #fafafa; }
	.items-table tbody td { padding: 8px 10px; font-size: 12px; border-bottom: 1px solid #eee; }
	.items-table tbody td.text-right { text-align: right; font-weight: 700; }
	.item-meta { font-size: 11px; color: #888; margin-top: 2px; }

	.tracking-row { margin-top: 14px; padding: 10px 12px; background: #f0f4ff; border-left: 3px solid #2271b1; font-size: 12px; }
	.tracking-row strong { display: block; margin-bottom: 4px; }

	.doc-footer { margin-top: 16px; text-align: center; font-size: 11px; color: #aaa; }

	.print-bar { background: #1a1a1a; color: #fff; text-align: center; padding: 12px; position: sticky; top: 0; z-index: 999; }
	.print-bar button { background: #fff; color: #1a1a1a; border: none; padding: 8px 24px; font-size: 14px; font-weight: 600; cursor: pointer; border-radius: 3px; }
	@media print {
		body { background: white; }
		.print-bar { display: none; }
		.wcfsl-doc { margin: 0; padding: 12mm; box-shadow: none; width: 100%; }
	}
</style>
</head>
<body>
<div class="print-bar">
	<button onclick="window.print()"><?php esc_html_e( 'Print Packing Slips', 'wc-fulfillment-sl' ); ?></button>
</div>

<?php foreach ( $orders as $order ) :
	$items    = $order->get_items();
	$tracking = WCFSL_Tracking::get_tracking( $order );
	$has_ship = $order->get_shipping_address_1();
	$ship_to  = WC()->countries->get_formatted_address( $has_ship ? [
		'address_1' => $order->get_shipping_address_1(),
		'address_2' => $order->get_shipping_address_2(),
		'city'      => $order->get_shipping_city(),
		'state'     => $order->get_shipping_state(),
		'postcode'  => $order->get_shipping_postcode(),
		'country'   => $order->get_shipping_country(),
	] : [
		'address_1' => $order->get_billing_address_1(),
		'address_2' => $order->get_billing_address_2(),
		'city'      => $order->get_billing_city(),
		'state'     => $order->get_billing_state(),
		'postcode'  => $order->get_billing_postcode(),
		'country'   => $order->get_billing_country(),
	] );
	?>
	<div class="wcfsl-doc">
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
				<h1><?php esc_html_e( 'Packing Slip', 'wc-fulfillment-sl' ); ?></h1>
				<div class="slip-meta">
					<div><strong><?php esc_html_e( 'Order #', 'wc-fulfillment-sl' ); ?></strong><?php echo esc_html( $order->get_order_number() ); ?></div>
					<div><strong><?php esc_html_e( 'Date:', 'wc-fulfillment-sl' ); ?></strong><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></div>
					<div><strong><?php esc_html_e( 'Items:', 'wc-fulfillment-sl' ); ?></strong><?php echo esc_html( $order->get_item_count() ); ?></div>
				</div>
			</div>
		</div>

		<hr>

		<div class="address-row">
			<div class="address-box">
				<h4><?php esc_html_e( 'Ship To', 'wc-fulfillment-sl' ); ?></h4>
				<address>
					<strong><?php echo esc_html( $order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name() ); ?></strong><br>
					<?php echo wp_kses_post( $ship_to ); ?>
					<?php if ( $order->get_billing_phone() ) echo '<br>' . esc_html( $order->get_billing_phone() ); ?>
				</address>
			</div>
			<div class="address-box">
				<h4><?php esc_html_e( 'Ship From', 'wc-fulfillment-sl' ); ?></h4>
				<address>
					<strong><?php echo esc_html( $s['company_name'] ); ?></strong><br>
					<?php echo nl2br( esc_html( $s['company_address'] ) ); ?>
				</address>
			</div>
		</div>

		<table class="items-table">
			<thead>
				<tr>
					<th style="width:50%"><?php esc_html_e( 'Product', 'wc-fulfillment-sl' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'wc-fulfillment-sl' ); ?></th>
					<th class="text-right"><?php esc_html_e( 'Qty', 'wc-fulfillment-sl' ); ?></th>
					<th><?php esc_html_e( 'Picked', 'wc-fulfillment-sl' ); ?></th>
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
							<?php
							$meta = $item->get_formatted_meta_data( '_', true );
							if ( $meta ) :
								echo '<div class="item-meta">';
								foreach ( $meta as $m ) {
									echo esc_html( $m->display_key ) . ': ' . wp_kses_post( $m->display_value ) . '<br>';
								}
								echo '</div>';
							endif;
							?>
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
			<strong><?php esc_html_e( 'Tracking Information', 'wc-fulfillment-sl' ); ?></strong>
			<?php esc_html_e( 'Carrier:', 'wc-fulfillment-sl' ); ?> <?php echo esc_html( $tracking['carrier'] ); ?> &nbsp;|&nbsp;
			<?php esc_html_e( 'Tracking #:', 'wc-fulfillment-sl' ); ?> <?php echo esc_html( $tracking['number'] ); ?>
		</div>
		<?php endif; ?>

		<?php if ( $order->get_customer_note() ) : ?>
		<p style="margin-top:12px;font-size:12px;padding:8px 10px;background:#fffbe6;border-left:3px solid #f0b429">
			<strong><?php esc_html_e( 'Customer Note:', 'wc-fulfillment-sl' ); ?></strong>
			<?php echo esc_html( $order->get_customer_note() ); ?>
		</p>
		<?php endif; ?>

		<div class="doc-footer"><?php echo esc_html( $s['company_name'] ); ?> &mdash; <?php esc_html_e( 'Thank you!', 'wc-fulfillment-sl' ); ?></div>
	</div>
<?php endforeach; ?>

<script>
window.addEventListener('load', function(){ window.print(); });
</script>
</body>
</html>
