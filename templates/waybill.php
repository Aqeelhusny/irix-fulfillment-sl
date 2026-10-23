<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php printf( esc_html__( 'Waybill — Order #%s', 'irix-fulfillment-sl' ), esc_html( $order->get_order_number() ) ); ?></title>
<style>
/*
 * Waybill — 4 × 6 in (101.6 × 152.4 mm) label stock.
 *
 * The label itself is 98 × 148 mm (inside the 100 × 150 mm print area) and
 * is centred on the page, leaving ~2 mm for printer edge tolerance. Screen
 * and print use the SAME size so the preview is exactly what prints.
 *
 * Height budget (148 mm, border-box):
 *   header 26 · barcode 20 · recipient (flex, ≈52) · payment 10 ·
 *   order/carrier 12 · pieces/weight 12 · QR 15 (optional)
 * Every fixed section clips its own overflow and long text is line-clamped,
 * so no data can push the label past 148 mm.
 */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
	--wb-w: 98mm;
	--wb-h: 148mm;
	--wb-line: 0.3mm solid #000;
}

body {
	font-family: Arial, Helvetica, sans-serif;
	color: #000;
	background: #d4d4d8;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12px;
	padding: 20px;
}

/* Clamp helper: N lines then ellipsis. */
.wb-clamp {
	display: -webkit-box;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

/* ── Print button bar (screen only) ───────────────────── */
.print-bar {
	display: flex;
	align-items: center;
	gap: 12px;
	width: var(--wb-w);
	padding: 8px 12px;
	background: #18181b;
	color: #fff;
	border-radius: 6px;
	font-size: 13px;
}
.print-bar span { flex: 1; }
.print-bar button {
	padding: 5px 14px;
	background: #fff;
	color: #18181b;
	border: 0;
	border-radius: 4px;
	font-size: 12px;
	font-weight: 700;
	cursor: pointer;
}

/* ── Label ────────────────────────────────────────────── */
.waybill {
	display: flex;
	flex-direction: column;
	width: var(--wb-w);
	height: var(--wb-h);
	overflow: hidden;
	background: #fff;
	border: var(--wb-line);
}
.waybill > * { flex-shrink: 0; overflow: hidden; border-bottom: var(--wb-line); }
.waybill > *:last-child { border-bottom: 0; }

/* ── 1. Header: logo | return address (26 mm) ─────────── */
/* Narrow logo column so the return address gets the width and stays legible. */
.wb-header {
	display: grid;
	grid-template-columns: 20mm 1fr;
	height: 26mm;
}
.wb-logo-col {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 2mm;
	border-right: var(--wb-line);
	overflow: hidden;
}
.wb-logo-col img {
	display: block;
	max-width: 16mm;
	max-height: 20mm;
	object-fit: contain;
}
.wb-logo-text {
	font-size: 9px;
	font-weight: 900;
	line-height: 1.2;
	text-align: center;
	-webkit-line-clamp: 4;
}
.wb-sender-col {
	display: flex;
	flex-direction: column;
	min-width: 0;
	padding: 1.8mm 2.5mm;
	overflow: hidden;
}
.wb-acct-name {
	font-size: 11px;
	font-weight: 800;
	line-height: 1.25;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.wb-addr-labels {
	margin: 0.5mm 0 0.3mm;
	font-size: 7px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .4px;
	color: #444;
}
.wb-addr-labels em { font-style: normal; font-weight: 400; color: #777; }
.wb-addr-body {
	font-size: 10px;
	line-height: 1.3;
	color: #000;
	-webkit-line-clamp: 3;
}
.wb-phone {
	margin-top: auto;
	font-size: 10.5px;
	font-weight: 700;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* ── 2. Barcode (20 mm) ───────────────────────────────── */
.wb-barcode {
	display: flex;
	flex-direction: column;
	justify-content: center;
	height: 20mm;
	padding: 1.5mm 3mm 1.2mm;
}
/* Drawn at high resolution and stretched to the full label width: bars scale
   uniformly, so any tracking-number length stays a valid, wide barcode. */
.wb-barcode-canvas {
	display: block;
	width: 100%;
	height: 12.5mm;
	image-rendering: pixelated;
}
.wb-barcode-ref {
	display: flex;
	align-items: baseline;
	justify-content: center;
	gap: 2mm;
	min-width: 0;
	margin-top: 1mm;
	white-space: nowrap;
}
.wb-barcode-num {
	font-family: 'Courier New', Courier, monospace;
	font-size: 9px;
	font-weight: 700;
	letter-spacing: 1px;
	overflow: hidden;
	text-overflow: ellipsis;
}
.wb-barcode-hint { flex-shrink: 1; min-width: 0; overflow: hidden; font-size: 6.5px; color: #666; }
.wb-barcode-empty {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	border: 0.3mm dashed #bbb;
	font-size: 7px;
	letter-spacing: 1px;
	text-transform: uppercase;
	color: #999;
}

/* ── 3. Recipient (fills the remaining ≈52 mm) ────────── */
.wb-to {
	flex: 1 1 auto;
	min-height: 0;
	padding: 2.2mm 3mm;
}
.wb-to-header {
	display: flex;
	align-items: baseline;
	gap: 1mm;
	margin-bottom: 1.2mm;
	font-size: 8.5px;
}
.wb-to-header strong { font-weight: 800; }
.wb-to-header em { font-style: normal; color: #555; white-space: nowrap; }
/* Phone in the header row: it can never be clipped by a long address. */
.wb-to-phone {
	margin-left: auto;
	font-size: 12px;
	font-weight: 800;
	white-space: nowrap;
}
.wb-to-name {
	margin-bottom: 1mm;
	font-size: 14px;
	font-weight: 800;
	line-height: 1.2;
	-webkit-line-clamp: 2;
}
/* 9 lines fit the ≈52 mm recipient area even with a two-line name, so a
   long address keeps its postcode; anything beyond is clipped, never spilled. */
.wb-to-addr {
	font-size: 11px;
	line-height: 1.3;
	color: #111;
	-webkit-line-clamp: 9;
}

/* ── 3b. Payment strip (10 mm) ────────────────────────── */
.wb-payment {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 2mm;
	height: 10mm;
	padding: 0 3mm;
}
.wb-payment-label {
	font-size: 8.5px;
	font-weight: 900;
	letter-spacing: .5px;
	text-transform: uppercase;
	white-space: nowrap;
}
.wb-payment-amount {
	font-size: 16px;
	font-weight: 900;
	white-space: nowrap;
}
.wb-payment--cod {
	background: #000;
	color: #fff;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}
.wb-payment--prepaid { justify-content: center; }
.wb-payment--prepaid .wb-payment-label { font-size: 13px; letter-spacing: 2px; }

/* ── 4. Order ID | carrier (12 mm) ────────────────────── */
.wb-product-row {
	display: grid;
	grid-template-columns: 1fr 26mm;
	height: 12mm;
}
.wb-product-left {
	display: flex;
	flex-direction: column;
	justify-content: center;
	min-width: 0;
	padding: 0 3mm;
	border-right: var(--wb-line);
}
.wb-product-label {
	font-size: 6.5px;
	font-weight: 700;
	letter-spacing: 1px;
	text-transform: uppercase;
	color: #777;
}
.wb-order-no {
	font-size: 13px;
	font-weight: 800;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.wb-sender-acct {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 1mm 2mm;
	background: #000;
	color: #fff;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
	overflow: hidden;
}
.wb-sender-acct span {
	font-size: 8px;
	font-weight: 800;
	line-height: 1.25;
	text-align: center;
	text-transform: uppercase;
	letter-spacing: .4px;
	-webkit-line-clamp: 3;
}

/* ── 5. Pieces / weight (12 mm) ───────────────────────── */
.wb-meta-row {
	display: grid;
	grid-template-columns: 1fr 26mm;
	height: 12mm;
}
.wb-meta-left {
	display: flex;
	flex-direction: column;
	justify-content: center;
	gap: .6mm;
	padding: 0 3mm;
	border-right: var(--wb-line);
	font-size: 8.5px;
	white-space: nowrap;
}
.wb-meta-left strong { font-weight: 700; }
.wb-meta-box {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}
.wb-meta-box-count { font-size: 17px; font-weight: 900; line-height: 1; }
.wb-meta-box-label { margin-top: .5mm; font-size: 6.5px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #777; }

/* ── 6. QR strip (15 mm, optional) ────────────────────── */
.wb-qr-strip {
	display: grid;
	grid-template-columns: 15mm 1fr;
	height: 15mm;
}
.wb-qr-box {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 1.2mm;
	border-right: var(--wb-line);
}
.wb-qr { width: 12.5mm; height: 12.5mm; overflow: hidden; }
.wb-qr canvas,
.wb-qr img { width: 100% !important; height: 100% !important; }
.wb-qr-text {
	display: flex;
	flex-direction: column;
	justify-content: center;
	gap: .6mm;
	min-width: 0;
	padding: 0 3mm;
}
.wb-qr-label { font-size: 6.5px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #777; }
.wb-qr-url { font-size: 8px; line-height: 1.3; word-break: break-all; -webkit-line-clamp: 2; }

/* ── Print: 4 × 6 in page, label centred inside it ───── */
@page {
	size: 4in 6in;
	margin: 0;
}
@media print {
	html, body { width: 4in; background: #fff; }
	body { display: block; padding: 0; }
	.print-bar { display: none !important; }
	.waybill {
		/* (101.6 − 98) / 2 = 1.8 mm sides, (152.4 − 148) / 2 = 2.2 mm top. */
		margin: 2.2mm auto 0;
		break-inside: avoid;
	}
}
</style>
</head>
<body>

<?php
$s         = IRIXFSL_Settings::get();
$ship_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(), " \n\r\t\v\0" );
if ( ! $ship_name ) {
	$ship_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), " \n\r\t\v\0" );
}

// Build address without name fields to avoid the name appearing twice.
$shipping_fields = $order->get_address( 'shipping' );
if ( array_filter( array_intersect_key( $shipping_fields, array_flip( [ 'address_1', 'city', 'postcode', 'country' ] ) ) ) ) {
	$addr_fields = $shipping_fields;
} else {
	$addr_fields = $order->get_address( 'billing' );
}
unset( $addr_fields['first_name'], $addr_fields['last_name'] );
$ship_addr = WC()->countries->get_formatted_address( $addr_fields );
$phone = $order->get_billing_phone();

// Payment strip on every label: COD shows the cash to collect; everything
// else just says "Prepaid" (no amount, so nobody is tempted to collect it). COD orders sit in Processing (which WC counts as "paid"),
// so is_paid() can't be used here.
$is_cod       = 'cod' === $order->get_payment_method();
$amount_due   = wc_price(
	max( 0, (float) $order->get_total() - (float) $order->get_total_refunded() ),
	[ 'currency' => $order->get_currency() ]
);

$items      = $order->get_items();
$item_count = $order->get_item_count();

// Carrier box: the saved carrier, else how the order is being fulfilled.
$carrier_label = $tracking['carrier'] ?: [
	'pickup'         => __( 'Store pickup', 'irix-fulfillment-sl' ),
	'local_delivery' => __( 'Local delivery', 'irix-fulfillment-sl' ),
][ IRIXFSL_Tracking::get_fulfillment_type( $order ) ] ?? __( 'Courier', 'irix-fulfillment-sl' );

// Total order weight from product weights (kg etc. per the store's weight unit).
$order_weight = 0;
foreach ( $items as $item ) {
	$product = $item->get_product();
	if ( $product && $product->get_weight() ) {
		$order_weight += (float) $product->get_weight() * $item->get_quantity();
	}
}
?>

<div class="print-bar">
	<span><?php printf( esc_html__( 'Order #%s', 'irix-fulfillment-sl' ), esc_html( $order->get_order_number() ) ); ?></span>
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
				<div class="wb-logo-text wb-clamp"><?php echo esc_html( $s['company_name'] ); ?></div>
			<?php endif; ?>
		</div>
		<div class="wb-sender-col">
			<div class="wb-acct-name">
				<?php echo esc_html( $s['company_name'] ); ?>
			</div>
			<div class="wb-addr-labels">
				<?php esc_html_e( 'Return address', 'irix-fulfillment-sl' ); ?>
			</div>
			<div class="wb-addr-body wb-clamp">
				<?php if ( $s['company_address'] ) echo nl2br( esc_html( $s['company_address'] ) ); ?>
			</div>
			<?php if ( $s['company_phone'] ) : ?>
				<div class="wb-phone"><?php echo esc_html( $s['company_phone'] ); ?></div>
			<?php endif; ?>
		</div>
	</div>

	<!-- 2. Main barcode -->
	<div class="wb-barcode">
		<?php if ( $tracking['number'] ) : ?>
			<canvas id="wb-barcode-canvas" class="wb-barcode-canvas"
			        aria-label="<?php echo esc_attr( $tracking['number'] ); ?>"></canvas>
			<div class="wb-barcode-ref">
				<span class="wb-barcode-num"><?php echo esc_html( $tracking['number'] ); ?></span>
				<span class="wb-barcode-hint"><?php esc_html_e( '(Waybill / Tracking No.)', 'irix-fulfillment-sl' ); ?></span>
			</div>
		<?php else : ?>
			<div class="wb-barcode-empty"><?php esc_html_e( 'Tracking number to be assigned', 'irix-fulfillment-sl' ); ?></div>
		<?php endif; ?>
	</div>

	<!-- 3. TO / recipient -->
	<div class="wb-to">
		<div class="wb-to-header">
			<strong><?php esc_html_e( 'TO', 'irix-fulfillment-sl' ); ?></strong>
			<em><?php esc_html_e( '— Delivery Address', 'irix-fulfillment-sl' ); ?></em>
			<?php if ( $phone ) : ?>
				<span class="wb-to-phone"><?php echo esc_html( $phone ); ?></span>
			<?php endif; ?>
		</div>
		<div class="wb-to-name wb-clamp"><?php echo esc_html( $ship_name ); ?></div>
		<?php if ( $ship_addr ) : ?>
			<div class="wb-to-addr wb-clamp"><?php echo wp_kses_post( $ship_addr ); ?></div>
		<?php endif; ?>
	</div>

	<!-- 3b. Payment / amount to collect -->
	<div class="wb-payment <?php echo $is_cod ? 'wb-payment--cod' : 'wb-payment--prepaid'; ?>">
		<div class="wb-payment-label">
			<?php $is_cod
				? esc_html_e( 'Cash on Delivery — Collect', 'irix-fulfillment-sl' )
				: esc_html_e( 'Prepaid', 'irix-fulfillment-sl' ); ?>
		</div>
		<?php if ( $is_cod ) : ?>
			<div class="wb-payment-amount"><?php echo wp_kses_post( $amount_due ); ?></div>
		<?php endif; ?>
	</div>

	<!-- 4. Product names | Sender Account -->
	<div class="wb-product-row">
		<div class="wb-product-left">
			<span class="wb-product-label"><?php esc_html_e( 'Order ID', 'irix-fulfillment-sl' ); ?></span>
			<span class="wb-order-no">#<?php echo esc_html( $order->get_order_number() ); ?></span>
		</div>
		<div class="wb-sender-acct">
			<span class="wb-clamp"><?php echo esc_html( $carrier_label ); ?></span>
		</div>
	</div>

	<!-- 5. Pieces / Weight -->
	<div class="wb-meta-row">
		<div class="wb-meta-left">
			<div><strong><?php esc_html_e( 'PIECES :', 'irix-fulfillment-sl' ); ?></strong>
				<?php echo esc_html( $item_count . ' ' . _n( 'item', 'items', $item_count, 'irix-fulfillment-sl' ) ); ?>
			</div>
			<div><strong><?php esc_html_e( 'WEIGHT :', 'irix-fulfillment-sl' ); ?></strong>
				<?php echo esc_html( $order_weight > 0 ? wc_format_weight( $order_weight ) : '—' ); ?>
			</div>
		</div>
		<div class="wb-meta-box">
			<div class="wb-meta-box-count"><?php echo esc_html( $item_count ); ?></div>
			<div class="wb-meta-box-label"><?php echo esc_html( _n( 'Item', 'Items', $item_count, 'irix-fulfillment-sl' ) ); ?></div>
		</div>
	</div>

	<?php if ( ! empty( $scan_url ) ) : ?>
	<!-- 6. QR strip -->
	<div class="wb-qr-strip">
		<div class="wb-qr-box">
			<div id="wb-qr-code" class="wb-qr"></div>
		</div>
		<div class="wb-qr-text">
			<div class="wb-qr-label"><?php esc_html_e( 'Scan to visit', 'irix-fulfillment-sl' ); ?></div>
			<div class="wb-qr-url wb-clamp"><?php echo esc_html( $scan_url ); ?></div>
		</div>
	</div>
	<?php endif; ?>

</div><!-- .waybill -->

<?php if ( ! empty( $scan_url ) ) : ?>
<script src="<?php echo esc_url( $qrcode_js_url ); ?>"></script>
<?php endif; ?>
<script src="<?php echo esc_url( $barcode_js_url ); ?>"></script>
<script>
(function () {
	var trackingNum = <?php echo wp_json_encode( $tracking['number'] ); ?>;

	function renderBarcodes() {
		if ( ! window.IRIXFSLBarcode ) return;

		// Main tracking barcode
		var mainCanvas = document.getElementById('wb-barcode-canvas');
		if ( mainCanvas && trackingNum ) {
			// High-res bitmap, CSS stretches it to the label width; quiet
			// zone = 10 modules either side, as Code 128 requires.
			IRIXFSLBarcode.draw( mainCanvas, trackingNum, {
				moduleWidth : 3,
				height      : 60,
				quiet       : 30
			} );
		}

	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			renderBarcodes();
			renderQR();
			window.print();
		} );
	} else {
		renderBarcodes();
		renderQR();
		window.print();
	}
}());

<?php if ( ! empty( $scan_url ) ) : ?>
var IRIXFSLScanUrl = <?php echo wp_json_encode( $scan_url ); ?>;
function renderQR() {
	var el = document.getElementById('wb-qr-code');
	if ( ! el || ! IRIXFSLScanUrl ) return;
	if ( typeof QRCode !== 'undefined' ) {
		new QRCode( el, {
			text        : IRIXFSLScanUrl,
			width       : 80,
			height      : 80,
			colorDark   : '#000000',
			colorLight  : '#ffffff',
			correctLevel: QRCode.CorrectLevel.M
		} );
	}
}
<?php else : ?>
function renderQR() {}
<?php endif; ?>

</script>
</body>
</html>
