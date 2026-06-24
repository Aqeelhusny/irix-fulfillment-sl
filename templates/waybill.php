<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php printf( esc_html__( 'Waybill — Order #%s', 'irix-fulfillment-sl' ), esc_html( $order->get_order_number() ) ); ?></title>
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
	height: 150mm;
	background: #fff;
	border: 1.5px solid #000;
	display: flex;
	flex-direction: column;
	overflow: hidden;
}

/* ── 1. Header: logo col | sender col ────────────────── */
.wb-header {
	display: grid;
	grid-template-columns: 27mm 1fr;
	border-bottom: 1.5px solid #000;
	height: 28mm;
	flex-shrink: 0;
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
	font-size: 9px;
	line-height: 1.6;
	color: #111;
}
.wb-phone-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 1.5mm;
}
.wb-phone {
	font-size: 9.5px;
	font-weight: 700;
	color: #000;
	display: flex;
	align-items: center;
	gap: 1mm;
}
.wb-phone::before {
	content: none;
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
	height: 22mm;
	flex-shrink: 0;
	overflow: hidden;
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
	flex: 1;
	overflow: hidden;
}
.wb-to-header {
	font-size: 8px;
	margin-bottom: 1.5mm;
}
.wb-to-header strong { font-weight: 800; margin-right: 1mm; }
.wb-to-header em { font-style: normal; color: #555; }
.wb-to-name {
	font-size: 13px;
	font-weight: 800;
	line-height: 1.3;
	color: #000;
	margin-bottom: 1mm;
}
.wb-to-addr {
	font-size: 10px;
	line-height: 1.6;
	color: #222;
}
.wb-to-phone {
	display: flex;
	align-items: center;
	gap: 1mm;
	font-size: 12px;
	font-weight: 700;
	color: #000;
	margin-top: 1.5mm;
}
.wb-to-phone::before {
	content: none;
}

/* ── 4. Product + Sender Account ──────────────────────── */
.wb-product-row {
	display: grid;
	grid-template-columns: 1fr 22mm;
	border-bottom: 1px solid #000;
	height: 20mm;
	flex-shrink: 0;
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
	height: 18mm;
	flex-shrink: 0;
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
/* ── 5b. Meta box count display ───────────────────────── */
.wb-meta-box {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 0.5mm;
}
.wb-meta-box-count {
	font-size: 18px;
	font-weight: 900;
	color: #111;
	line-height: 1;
}
.wb-meta-box-label {
	font-size: 6.5px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #888;
}

/* ── 6. QR strip (inside waybill) ────────────────────── */
.wb-qr-strip {
	display: grid;
	grid-template-columns: 16mm 1fr;
	height: 18mm;
	flex-shrink: 0;
	border-top: 1px solid #000;
}
.wb-qr-box {
	padding: 1.5mm;
	border-right: 1px solid #ddd;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #fff;
}
#wb-qr-code canvas,
#wb-qr-code img {
	display: block;
	max-width: 100%;
	max-height: 100%;
}
.wb-qr-text {
	padding: 2.5mm 3mm;
	display: flex;
	flex-direction: column;
	justify-content: center;
	gap: 1mm;
}
.wb-qr-label {
	font-size: 6px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 1px;
	color: #aaa;
}
.wb-qr-url {
	font-size: 7px;
	color: #333;
	word-break: break-all;
	line-height: 1.4;
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
		size: 100mm 150mm;
		margin: 0;
	}
}
</style>
</head>
<body>

<?php
$s         = IRIXFSL_Settings::get();
$ship_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
if ( ! $ship_name ) {
	$ship_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
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
				<div class="wb-logo-text"><?php echo esc_html( $s['company_name'] ); ?></div>
			<?php endif; ?>
		</div>
		<div class="wb-sender-col">
			<div class="wb-acct-name">
				<?php echo esc_html( $s['company_name'] ); ?>
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
				<span class="wb-barcode-hint"><?php esc_html_e( '(Waybill / Tracking No.)', 'irix-fulfillment-sl' ); ?></span>
			</div>
		<?php else : ?>
			<div style="height:14mm;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;">
				<span style="font-size:7.5px;color:#aaa;letter-spacing:1px;text-transform:uppercase;">
					<?php esc_html_e( 'Tracking number to be assigned', 'irix-fulfillment-sl' ); ?>
				</span>
			</div>
		<?php endif; ?>
	</div>

	<!-- 3. TO / recipient -->
	<div class="wb-to">
		<div class="wb-to-header">
			<strong><?php esc_html_e( 'TO', 'irix-fulfillment-sl' ); ?></strong>
			<em><?php esc_html_e( '— Delivery Address', 'irix-fulfillment-sl' ); ?></em>
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
			<div class="wb-product-tag"><?php esc_html_e( 'Order', 'irix-fulfillment-sl' ); ?></div>
			<div class="wb-product-names">
				<span class="wb-product-label"><?php esc_html_e( 'Order ID', 'irix-fulfillment-sl' ); ?></span>
				#<?php echo esc_html( $order->get_order_number() ); ?>
			</div>
		</div>
		<div class="wb-sender-acct">
			<?php if ( $tracking['carrier'] ) : ?>
				<?php echo esc_html( $tracking['carrier'] ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Sender Account', 'irix-fulfillment-sl' ); ?>
			<?php endif; ?>
		</div>
	</div>

	<!-- 5. Pieces / Weight -->
	<div class="wb-meta-row">
		<div class="wb-meta-left">
			<div><strong><?php esc_html_e( 'PIECES :', 'irix-fulfillment-sl' ); ?></strong>
				<?php echo esc_html( $item_count . ' ' . _n( 'item', 'items', $item_count, 'irix-fulfillment-sl' ) ); ?>
			</div>
			<div><strong><?php esc_html_e( 'WEIGHT :', 'irix-fulfillment-sl' ); ?></strong>
				<?php
				$weight = $order->get_meta( '_order_weight' ) ?: '—';
				echo esc_html( $weight );
				?>
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
			<div id="wb-qr-code"></div>
		</div>
		<div class="wb-qr-text">
			<div class="wb-qr-label"><?php esc_html_e( 'Scan to visit', 'irix-fulfillment-sl' ); ?></div>
			<div class="wb-qr-url"><?php echo esc_html( $scan_url ); ?></div>
		</div>
	</div>
	<?php endif; ?>

</div><!-- .waybill -->

<?php if ( ! empty( $scan_url ) ) : ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
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
			IRIXFSLBarcode.draw( mainCanvas, trackingNum, {
				moduleWidth : 1.5,
				height      : 48,
				quiet       : 8
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
