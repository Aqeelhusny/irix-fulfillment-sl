<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class WCFSL_Customer_Portal {

	private static ?self $instance = null;
	const ENDPOINT_TRACK = 'track-order';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init',                               [ $this, 'add_endpoints' ] );
		add_filter( 'query_vars',                         [ $this, 'add_query_vars' ] );
		add_filter( 'woocommerce_account_menu_items',     [ $this, 'add_menu_item' ] );
		add_action( 'woocommerce_account_' . self::ENDPOINT_TRACK . '_endpoint', [ $this, 'render_tracking_page' ] );

		// Add Invoice + Track buttons to My Account > Orders table
		add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'add_order_actions' ], 10, 2 );
	}

	public function add_endpoints(): void {
		add_rewrite_endpoint( self::ENDPOINT_TRACK, EP_ROOT | EP_PAGES );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = self::ENDPOINT_TRACK;
		return $vars;
	}

	public function add_menu_item( array $items ): array {
		// Insert after "Orders"
		$new = [];
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'orders' ) {
				$new[ self::ENDPOINT_TRACK ] = __( 'Track Order', 'wc-fulfillment-sl' );
			}
		}
		return $new;
	}

	public function render_tracking_page(): void {
		$order_id = absint( get_query_var( self::ENDPOINT_TRACK ) );

		if ( ! $order_id ) {
			// Show a form to enter order ID + order key
			$this->render_tracking_lookup();
			return;
		}

		$order_key = sanitize_text_field( $_GET['order_key'] ?? '' ); // phpcs:ignore
		$order     = wc_get_order( $order_id );

		if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			echo '<p>' . esc_html__( 'Order not found or access denied.', 'wc-fulfillment-sl' ) . '</p>';
			return;
		}

		$tracking = WCFSL_Tracking::get_tracking( $order );
		$this->render_tracking_info( $order, $tracking );
	}

	private function render_tracking_lookup(): void {
		?>
		<p><?php esc_html_e( 'Enter your order details to track your shipment.', 'wc-fulfillment-sl' ); ?></p>
		<form method="get" action="<?php echo esc_url( wc_get_account_endpoint_url( self::ENDPOINT_TRACK ) ); ?>">
			<p>
				<label><?php esc_html_e( 'Order ID', 'wc-fulfillment-sl' ); ?><br>
				<input type="number" name="wcfsl_oid" min="1" required style="width:100%;max-width:300px"></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Order Key (from your email)', 'wc-fulfillment-sl' ); ?><br>
				<input type="text" name="order_key" required style="width:100%;max-width:300px"></label>
			</p>
			<p><button type="submit" class="button"><?php esc_html_e( 'Track Order', 'wc-fulfillment-sl' ); ?></button></p>
		</form>
		<?php
		// Handle lookup submit
		if ( isset( $_GET['wcfsl_oid'] ) ) { // phpcs:ignore
			$oid = absint( $_GET['wcfsl_oid'] ); // phpcs:ignore
			$key = sanitize_text_field( $_GET['order_key'] ?? '' ); // phpcs:ignore
			$o   = wc_get_order( $oid );
			if ( $o && hash_equals( $o->get_order_key(), $key ) ) {
				$this->render_tracking_info( $o, WCFSL_Tracking::get_tracking( $o ) );
			} else {
				echo '<p style="color:red">' . esc_html__( 'Order not found. Please check your details.', 'wc-fulfillment-sl' ) . '</p>';
			}
		}
	}

	private function render_tracking_info( WC_Order $order, array $tracking ): void {
		$status_label = wc_get_order_statuses()[ 'wc-' . $order->get_status() ] ?? ucfirst( $order->get_status() );
		?>
		<div class="wcfsl-tracking-info" style="max-width:600px">
			<h3><?php printf( esc_html__( 'Order #%s', 'wc-fulfillment-sl' ), esc_html( $order->get_order_number() ) ); ?></h3>
			<table class="wcfsl-tracking-table" style="width:100%;border-collapse:collapse">
				<tr>
					<th style="text-align:left;padding:8px 12px;background:#f6f6f6"><?php esc_html_e( 'Order Status', 'wc-fulfillment-sl' ); ?></th>
					<td style="padding:8px 12px"><?php echo esc_html( $status_label ); ?></td>
				</tr>
				<tr>
					<th style="text-align:left;padding:8px 12px;background:#f6f6f6"><?php esc_html_e( 'Order Date', 'wc-fulfillment-sl' ); ?></th>
					<td style="padding:8px 12px"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
				</tr>
				<?php if ( $tracking['carrier'] ) : ?>
				<tr>
					<th style="text-align:left;padding:8px 12px;background:#f6f6f6"><?php esc_html_e( 'Carrier', 'wc-fulfillment-sl' ); ?></th>
					<td style="padding:8px 12px"><?php echo esc_html( $tracking['carrier'] ); ?></td>
				</tr>
				<tr>
					<th style="text-align:left;padding:8px 12px;background:#f6f6f6"><?php esc_html_e( 'Tracking Number', 'wc-fulfillment-sl' ); ?></th>
					<td style="padding:8px 12px">
						<?php if ( $tracking['url'] ) : ?>
							<a href="<?php echo esc_url( $tracking['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $tracking['number'] ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $tracking['number'] ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $tracking['url'] ) : ?>
				<tr>
					<th style="text-align:left;padding:8px 12px;background:#f6f6f6"><?php esc_html_e( 'Track Shipment', 'wc-fulfillment-sl' ); ?></th>
					<td style="padding:8px 12px">
						<a href="<?php echo esc_url( $tracking['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button">
							<?php esc_html_e( 'Track on Carrier Website', 'wc-fulfillment-sl' ); ?>
						</a>
					</td>
				</tr>
				<?php endif; ?>
				<?php else : ?>
				<tr>
					<th style="text-align:left;padding:8px 12px;background:#f6f6f6"><?php esc_html_e( 'Tracking', 'wc-fulfillment-sl' ); ?></th>
					<td style="padding:8px 12px"><?php esc_html_e( 'Tracking information will appear here once your order is shipped.', 'wc-fulfillment-sl' ); ?></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>
		<?php
	}

	public function add_order_actions( array $actions, WC_Order $order ): array {
		// Invoice download
		$actions['wcfsl_invoice'] = [
			'url'  => WCFSL_Invoice::invoice_url( $order->get_id(), false, $order->get_order_key() ),
			'name' => __( 'Invoice', 'wc-fulfillment-sl' ),
		];

		// Track order — only show if tracking exists
		$tracking = WCFSL_Tracking::get_tracking( $order );
		if ( $tracking['number'] ) {
			$actions['wcfsl_track'] = [
				'url'  => $tracking['url'] ?: wc_get_account_endpoint_url( self::ENDPOINT_TRACK ),
				'name' => __( 'Track Order', 'wc-fulfillment-sl' ),
			];
		}

		return $actions;
	}
}
