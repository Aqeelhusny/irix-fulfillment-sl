<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php printf( esc_html__( 'Waybill — Order #%s', 'wc-fulfillment-sl' ), esc_html( $order->get_order_number() ) ); ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
	font-family: Arial, Helvetica, sans-serif;
	background: #ccc;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 20px;
	gap: 16px;
}

/* ── Print button bar ─────────────────────────────────── */
.print-bar {
	background: #1d2327;
	color: #fff;
	padding: 8px 16px;
	border-radius: 4px;
	display: flex;
	align-items: center;
	gap: 12px;
	font-size: 13px;
	width: 100mm;
}
.print-bar span { flex: 1; }
.print-bar button {
	background: #2271b1;
	color: #fff;
	border: none;
	padding: 5px 14px;
	font-size: 12px;
	font-weight: 700;
	border-radius: 3px;
	cursor: pointer;
}

/* ── Label card ───────────────────────────────────────── */
.waybill {
	width: 100mm;
	background: #fff;
	border: 1.5px solid #000;
	display: flex;
	flex-direction: column;
}

/* ── 1. Header: logo col | sender col ────────────────── */
.wb-header {
	display: grid;
	grid-template-columns: 27mm 1fr;
	border-bottom: 1.5px solid #000;
	min-height: 24mm;
}
.wb-logo-col {
	padding: 3mm 2.5mm;
	border-right: 1px solid #000;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 1.5mm;
}
.wb-logo-col img {
	max-width: 22mm;
	max-height: 14mm;
	width: auto;
	height: auto;
	object-fit: contain;
	display: block;
}
.wb-logo-text {
	font-size: 10px;
	font-weight: 900;
	color: #111;
	text-align: center;
	line-height: 1.2;
}
.wb-sender-col {
	padding: 2.5mm 3mm 2mm;
}
.wb-acct-name {
	font-size: 9px;
	font-weight: 800;
	color: #111;
	line-height: 1.3;
	margin-bottom: 1.5mm;
}
.wb-addr-labels {
	font-size: 7px;
	font-weight: 700;
	color: #333;
	margin-bottom: 1mm;
}
.wb-addr-labels em {
	font-style: normal;
	color: #888;
	font-weight: 400;
}
.wb-addr-body {
	font-size: 7.5px;
	line-height: 1.55;
	color: #222;
}
.wb-phone-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 1.5mm;
}
.wb-phone {
	font-size: 7.5px;
	color: #222;
	display: flex;
	align-items: center;
	gap: 1mm;
}
.wb-phone::before {
	content: '✆';
	font-size: 8px;
	color: #555;
}
.wb-city-tag {
	font-size: 7.5px;
	font-weight: 800;
	background: #1a1a1a;
	color: #fff;
	padding: 1px 5px;
	border-radius: 2px;
	letter-spacing: 0.5px;
}

/* ── 2. Main barcode ──────────────────────────────────── */
.wb-barcode {
	padding: 3mm 3mm 2mm;
	border-bottom: 1px solid #000;
	text-align: center;
}
#wb-barcode-canvas {
	display: block;
	margin: 0 auto;
	max-width: 100%;
}
.wb-barcode-ref {
	display: flex;
	align-items: baseline;
	justify-content: center;
	gap: 3mm;
	margin-top: 1mm;
}
.wb-barcode-num {
	font-family: 'Courier New', Courier, monospace;
	font-size: 8.5px;
	font-weight: 700;
	letter-spacing: 1.5px;
	color: #111;
}
.wb-barcode-hint {
	font-size: 7px;
	color: #666;
}

/* ── 3. TO / recipient ────────────────────────────────── */
.wb-to {
	padding: 2.5mm 3mm;
	border-bottom: 1px solid #000;
}
.wb-to-header {
	font-size: 8px;
	margin-bottom: 1.5mm;
}
.wb-to-header strong { font-weight: 800; margin-right: 1mm; }
.wb-to-header em { font-style: normal; color: #555; }
.wb-to-name {
	font-size: 11px;
	font-weight: 800;
	line-height: 1.3;
	color: #000;
	margin-bottom: 1mm;
}
.wb-to-addr {
	font-size: 8px;
	line-height: 1.6;
	color: #222;
}
.wb-to-phone {
	display: flex;
	align-items: center;
	gap: 1mm;
	font-size: 8.5px;
	font-weight: 700;
	color: #000;
	margin-top: 1.5mm;
}
.wb-to-phone::before {
	content: '✆';
	font-size: 9px;
	color: #555;
	font-weight: 400;
}

/* ── 4. Product + Sender Account ──────────────────────── */
.wb-product-row {
	display: grid;
	grid-template-columns: 1fr 22mm;
	border-bottom: 1px solid #000;
	min-height: 18mm;
}
.wb-product-left {
	display: flex;
	border-right: 1px solid #000;
}
.wb-product-tag {
	writing-mode: vertical-rl;
	transform: rotate(180deg);
	font-size: 6px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 1.5px;
	color: #888;
	padding: 2mm 1.5mm;
	border-right: 1px solid #ddd;
	flex-shrink: 0;
	white-space: nowrap;
}
.wb-product-names {
	padding: 2mm 2.5mm;
	font-size: 8px;
	line-height: 1.55;
	color: #222;
	flex: 1;
}
.wb-product-names .wb-product-label {
	font-size: 7px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 1px;
	color: #aaa;
	display: block;
	margin-bottom: 1mm;
}
.wb-sender-acct {
	display: flex;
	align-items: center;
	justify-content: center;
	background: #1a1a1a;
	color: #fff;
	font-size: 8px;
	font-weight: 800;
	text-transform: uppercase;
	text-align: center;
	letter-spacing: 0.5px;
	line-height: 1.5;
	padding: 2mm 2mm;
}

/* ── 5. Pieces / Weight ───────────────────────────────── */
.wb-meta-row {
	display: grid;
	grid-template-columns: 1fr 22mm;
	border-bottom: 1px solid #000;
	min-height: 14mm;
}
.wb-meta-left {
	padding: 2.5mm 3mm;
	border-right: 1px solid #000;
	font-size: 8px;
	line-height: 2.1;
	color: #222;
}
.wb-meta-left strong {
	font-weight: 700;
	color: #111;
}
.wb-meta-box {
	border: 1px solid #ccc;
	margin: 3mm 3mm;
	border-radius: 1px;
}

/* ── 6. Reference ─────────────────────────────────────── */
.wb-ref-row {
	display: grid;
	grid-template-columns: 34mm 1fr;
	min-height: 16mm;
}
.wb-ref-left {
	padding: 2mm 3mm 1.5mm;
	border-right: 1px solid #000;
}
.wb-ref-label {
	font-size: 7px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #2271b1;
	margin-bottom: 1mm;
}
#wb-ref-canvas {
	display: block;
}
.wb-ref-num {
	font-family: 'Courier New', Courier, monospace;
	font-size: 6.5px;
	font-weight: 700;
	letter-spacing: 1px;
	color: #111;
	margin-top: 0.5mm;
	text-align: center;
}
.wb-ref-right {
	padding: 2mm 3mm;
	display: flex;
	align-items: flex-end;
	justify-content: flex-end;
}
.wb-order-stamp {
	font-size: 7.5px;
	font-weight: 700;
	color: #aaa;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

/* ── Print overrides ──────────────────────────────────── */
@media print {
	body    { background: white; padding: 0; }
	.print-bar { display: none !important; }
	.waybill {
		border: 1.5px solid #000;
		margin: 0;
		page-break-after: always;
	}
	@page {
		size: 100mm auto;
		margin: 0;
	}
}
</style>
</head>
<body>

<?php
$s         = WCFSL_Settings::get();
$ship_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
if ( ! $ship_name ) {
	$ship_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
}
$ship_addr = $order->get_formatted_shipping_address();
if ( ! $ship_addr ) {
	$ship_addr = $order->get_formatted_billing_address();
}
$phone = $order->get_billing_phone();

// Build product names list (max 4 items)
$items       = $order->get_items();
$item_names  = [];
foreach ( $items as $item ) {
	$item_names[] = $item->get_name() . ' × ' . $item->get_quantity();
}
$item_count  = array_sum( array_column( iterator_to_array( $items ), 'quantity' ) );
$item_count  = $order->get_item_count();
$product_str = implode( "\n", array_slice( $item_names, 0, 4 ) );
if ( count( $item_names ) > 4 ) {
	$product_str .= "\n+ " . ( count( $item_names ) - 4 ) . ' more…';
}
?>

<div class="print-bar">
	<span><?php printf( esc_html__( 'Order #%s', 'wc-fulfillment-sl' ), esc_html( $order->get_order_number() ) ); ?></span>
	<button onclick="window.print()">Print</button>
</div>

<div class="waybill">

	<!-- 1. Header: logo | sender address -->
	<div class="wb-header">
		<div class="wb-logo-col">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>"
				     alt="<?php echo esc_attr( $s['company_name'] ); ?>">
			<?php else : ?>
				<div class="wb-logo-text"><?php echo esc_html( $s['company_name'] ); ?></div>
			<?php endif; ?>
		</div>
		<div class="wb-sender-col">
			<div class="wb-acct-name">
				<?php echo esc_html( $s['company_name'] ); ?>
				<span style="color:#666;font-weight:400"> — #<?php echo esc_html( $order->get_order_number() ); ?></span>
			</div>
			<div class="wb-addr-labels">
				Return Address <em>— Seller Address</em>
			</div>
			<div class="wb-addr-body">
				<?php if ( $s['company_address'] ) echo nl2br( esc_html( $s['company_address'] ) ); ?>
			</div>
			<?php if ( $s['company_phone'] ) : ?>
			<div class="wb-phone-row">
				<div class="wb-phone"><?php echo esc_html( $s['company_phone'] ); ?></div>
				<?php if ( $tracking['carrier'] ) : ?>
					<div class="wb-city-tag"><?php echo esc_html( strtoupper( substr( $tracking['carrier'], 0, 3 ) ) ); ?></div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- 2. Main barcode -->
	<div class="wb-barcode">
		<?php if ( $tracking['number'] ) : ?>
			<canvas id="wb-barcode-canvas"
			        aria-label="<?php echo esc_attr( $tracking['number'] ); ?>"></canvas>
			<div class="wb-barcode-ref">
				<span class="wb-barcode-num"><?php echo esc_html( $tracking['number'] ); ?></span>
				<span class="wb-barcode-hint"><?php esc_html_e( '(Waybill / Tracking No.)', 'wc-fulfillment-sl' ); ?></span>
			</div>
		<?php else : ?>
			<div style="height:14mm;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;">
				<span style="font-size:7.5px;color:#aaa;letter-spacing:1px;text-transform:uppercase;">
					<?php esc_html_e( 'Tracking number to be assigned', 'wc-fulfillment-sl' ); ?>
				</span>
			</div>
		<?php endif; ?>
	</div>

	<!-- 3. TO / recipient -->
	<div class="wb-to">
		<div class="wb-to-header">
			<strong><?php esc_html_e( 'TO', 'wc-fulfillment-sl' ); ?></strong>
			<em><?php esc_html_e( '— Delivery Address', 'wc-fulfillment-sl' ); ?></em>
		</div>
		<div class="wb-to-name"><?php echo esc_html( $ship_name ); ?></div>
		<?php if ( $ship_addr ) : ?>
			<div class="wb-to-addr"><?php echo wp_kses_post( $ship_addr ); ?></div>
		<?php endif; ?>
		<?php if ( $phone ) : ?>
			<div class="wb-to-phone"><?php echo esc_html( $phone ); ?></div>
		<?php endif; ?>
	</div>

	<!-- 4. Product names | Sender Account -->
	<div class="wb-product-row">
		<div class="wb-product-left">
			<div class="wb-product-tag"><?php esc_html_e( 'Product', 'wc-fulfillment-sl' ); ?></div>
			<div class="wb-product-names">
				<span class="wb-product-label"><?php esc_html_e( 'Product name', 'wc-fulfillment-sl' ); ?></span>
				<?php echo nl2br( esc_html( $product_str ) ); ?>
			</div>
		</div>
		<div class="wb-sender-acct">
			<?php if ( $tracking['carrier'] ) : ?>
				<?php echo esc_html( $tracking['carrier'] ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Sender Account', 'wc-fulfillment-sl' ); ?>
			<?php endif; ?>
		</div>
	</div>

	<!-- 5. Pieces / Weight -->
	<div class="wb-meta-row">
		<div class="wb-meta-left">
			<div><strong><?php esc_html_e( 'PIECES :', 'wc-fulfillment-sl' ); ?></strong>
				<?php echo esc_html( $item_count . ' ' . _n( 'item', 'items', $item_count, 'wc-fulfillment-sl' ) ); ?>
			</div>
			<div><strong><?php esc_html_e( 'WEIGHT :', 'wc-fulfillment-sl' ); ?></strong>
				<?php
				$weight = $order->get_meta( '_order_weight' ) ?: '—';
				echo esc_html( $weight );
				?>
			</div>
		</div>
		<div class="wb-meta-box"></div>
	</div>

	<!-- 6. Reference -->
	<div class="wb-ref-row">
		<div class="wb-ref-left">
			<div class="wb-ref-label"><?php esc_html_e( 'Reference', 'wc-fulfillment-sl' ); ?></div>
			<canvas id="wb-ref-canvas"
			        aria-label="<?php echo esc_attr( $order->get_order_number() ); ?>"></canvas>
			<div class="wb-ref-num">#<?php echo esc_html( $order->get_order_number() ); ?></div>
		</div>
		<div class="wb-ref-right">
			<span class="wb-order-stamp"><?php esc_html_e( 'Order', 'wc-fulfillment-sl' ); ?></span>
		</div>
	</div>

</div><!-- .waybill -->

<script src="<?php echo esc_url( $barcode_js_url ); ?>"></script>
<script>
(function () {
	var trackingNum = <?php echo wp_json_encode( $tracking['number'] ); ?>;
	var orderRef    = <?php echo wp_json_encode( $order->get_order_number() ); ?>;

	function renderBarcodes() {
		if ( ! window.WCFSLBarcode ) return;

		// Main tracking barcode
		var mainCanvas = document.getElementById('wb-barcode-canvas');
		if ( mainCanvas && trackingNum ) {
			WCFSLBarcode.draw( mainCanvas, trackingNum, {
				moduleWidth : 1.5,
				height      : 48,
				quiet       : 8
			} );
		}

		// Reference (order number) barcode — smaller
		var refCanvas = document.getElementById('wb-ref-canvas');
		if ( refCanvas && orderRef ) {
			WCFSLBarcode.draw( refCanvas, orderRef, {
				moduleWidth : 1.2,
				height      : 28,
				quiet       : 4
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			renderBarcodes();
			window.print();
		} );
	} else {
		renderBarcodes();
		window.print();
	}
}());
</script>
</body>
</html>
