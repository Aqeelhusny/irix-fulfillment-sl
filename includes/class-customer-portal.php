<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Customer_Portal {

	use IRIXFSL_Singleton;

	const ENDPOINT_TRACK = 'track-order';

	protected function boot(): void {
		add_action( 'init',                               [ $this, 'add_endpoints' ] );
		add_filter( 'query_vars',                         [ $this, 'add_query_vars' ] );
		add_filter( 'woocommerce_account_menu_items',     [ $this, 'add_menu_item' ] );
		add_action( 'woocommerce_account_' . self::ENDPOINT_TRACK . '_endpoint', [ $this, 'render_tracking_page' ] );

		// Add Invoice + Track buttons to My Account > Orders table
		add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'add_order_actions' ], 10, 2 );
	}

	public function add_endpoints(): void {
		add_rewrite_endpoint( self::ENDPOINT_TRACK, EP_PAGES );
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
				$new[ self::ENDPOINT_TRACK ] = __( 'Track Order', 'irix-fulfillment-sl' );
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
			wc_print_notice( __( 'Order not found or access denied.', 'irix-fulfillment-sl' ), 'error' );
			return;
		}

		$tracking = IRIXFSL_Tracking::get_tracking( $order );
		$this->render_tracking_info( $order, $tracking );
	}

	private function render_tracking_lookup(): void {
		// Handle lookup submit first so the result (or error) sits above the form.
		if ( isset( $_GET['irixfsl_oid'] ) ) { // phpcs:ignore
			$oid = absint( $_GET['irixfsl_oid'] ); // phpcs:ignore
			$key = sanitize_text_field( $_GET['order_key'] ?? '' ); // phpcs:ignore
			$o   = wc_get_order( $oid );
			if ( $o && hash_equals( $o->get_order_key(), $key ) ) {
				$this->render_tracking_info( $o, IRIXFSL_Tracking::get_tracking( $o ) );
				return;
			}
			wc_print_notice( __( 'Order not found. Please check your details.', 'irix-fulfillment-sl' ), 'error' );
		}
		?>
		<p><?php esc_html_e( 'Enter your order details to track your shipment.', 'irix-fulfillment-sl' ); ?></p>
		<form method="get" class="woocommerce-form irixfsl-track-form" action="<?php echo esc_url( wc_get_account_endpoint_url( self::ENDPOINT_TRACK ) ); ?>">
			<p class="woocommerce-form-row form-row form-row-first">
				<label for="irixfsl_oid"><?php esc_html_e( 'Order ID', 'irix-fulfillment-sl' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<input type="number" class="woocommerce-Input input-text" name="irixfsl_oid" id="irixfsl_oid" min="1" required inputmode="numeric">
			</p>
			<p class="woocommerce-form-row form-row form-row-last">
				<label for="irixfsl_order_key"><?php esc_html_e( 'Order Key (from your email)', 'irix-fulfillment-sl' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="woocommerce-Input input-text" name="order_key" id="irixfsl_order_key" required autocomplete="off">
			</p>
			<div class="clear"></div>
			<p><button type="submit" class="woocommerce-button button"><?php esc_html_e( 'Track Order', 'irix-fulfillment-sl' ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Progress steps for the customer view, wording adapted to how the order
	 * is fulfilled. Returns [ steps, current index ] — index -1 means the order
	 * left the normal flow (cancelled, refunded, returned, failed): no tracker.
	 */
	public static function progress_steps( WC_Order $order ): array {
		$type  = IRIXFSL_Tracking::get_fulfillment_type( $order );
		$steps = [
			__( 'Order placed', 'irix-fulfillment-sl' ),
			__( 'Processing', 'irix-fulfillment-sl' ),
			__( 'Packed', 'irix-fulfillment-sl' ),
			[
				'pickup'         => __( 'Ready for pickup', 'irix-fulfillment-sl' ),
				'local_delivery' => __( 'Out for delivery', 'irix-fulfillment-sl' ),
			][ $type ] ?? __( 'Shipped', 'irix-fulfillment-sl' ),
			$type === 'pickup' ? __( 'Collected', 'irix-fulfillment-sl' ) : __( 'Delivered', 'irix-fulfillment-sl' ),
		];
		$index = [
			'pending'       => 0,
			'on-hold'       => 0,
			'processing'    => 1,
			'ready-to-ship' => 2,
			'shipped'       => 3,
			'completed'     => 4,
		][ $order->get_status() ] ?? -1;

		return [ $steps, $index ];
	}

	private function render_tracking_info( WC_Order $order, array $tracking ): void {
		wp_enqueue_style( 'irixfsl-portal', IRIXFSL_URL . 'assets/css/portal.css', [], IRIXFSL_VERSION );

		$status_label     = wc_get_order_status_name( $order->get_status() );
		[ $steps, $current ] = self::progress_steps( $order );
		?>
		<section class="irixfsl-tracking-info">
			<h2 class="irixfsl-tracking-title">
				<?php printf( esc_html__( 'Order #%s', 'irix-fulfillment-sl' ), esc_html( $order->get_order_number() ) ); ?>
				<mark class="order-status status-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( $status_label ); ?></mark>
			</h2>

			<?php if ( $current >= 0 ) : ?>
			<ol class="irixfsl-progress" aria-label="<?php esc_attr_e( 'Order progress', 'irix-fulfillment-sl' ); ?>">
				<?php foreach ( $steps as $i => $step ) :
					$state = $i < $current ? 'done' : ( $i === $current ? 'current' : 'todo' ); ?>
					<li class="irixfsl-progress__step is-<?php echo esc_attr( $state ); ?>"<?php echo $state === 'current' ? ' aria-current="step"' : ''; ?>>
						<span class="irixfsl-progress__dot" aria-hidden="true"></span>
						<span class="irixfsl-progress__label"><?php echo esc_html( $step ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php endif; ?>

			<table class="woocommerce-table shop_table irixfsl-tracking-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Order Date', 'irix-fulfillment-sl' ); ?></th>
						<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
					</tr>
					<?php if ( $tracking['carrier'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Carrier', 'irix-fulfillment-sl' ); ?></th>
						<td><?php echo esc_html( $tracking['carrier'] ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( $tracking['number'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tracking Number', 'irix-fulfillment-sl' ); ?></th>
						<td><code class="irixfsl-tracking-number"><?php echo esc_html( $tracking['number'] ); ?></code></td>
					</tr>
					<?php endif; ?>
					<?php if ( ! $tracking['carrier'] && ! $tracking['number'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tracking', 'irix-fulfillment-sl' ); ?></th>
						<td><?php esc_html_e( 'Tracking information will appear here once your order is shipped.', 'irix-fulfillment-sl' ); ?></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $tracking['url'] ) : ?>
			<p>
				<a href="<?php echo esc_url( $tracking['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="woocommerce-button button">
					<?php esc_html_e( 'Track on Carrier Website', 'irix-fulfillment-sl' ); ?>
				</a>
			</p>
			<?php endif; ?>
		</section>
		<?php
	}

	public function add_order_actions( array $actions, WC_Order $order ): array {
		// Invoice download
		$actions['irixfsl_invoice'] = [
			'url'  => IRIXFSL_Invoice::invoice_url( $order->get_id(), false, $order->get_order_key() ),
			'name' => __( 'Invoice', 'irix-fulfillment-sl' ),
		];

		// Track order — only show if tracking exists
		$tracking = IRIXFSL_Tracking::get_tracking( $order );
		if ( $tracking['number'] ) {
			$actions['irixfsl_track'] = [
				// No carrier URL: open this order's tracking page directly — the
				// lookup form asks for an order key customers never see.
				'url'  => $tracking['url'] ?: add_query_arg(
					[ 'order_key' => $order->get_order_key() ],
					wc_get_endpoint_url( self::ENDPOINT_TRACK, (string) $order->get_id(), wc_get_page_permalink( 'myaccount' ) )
				),
				'name' => __( 'Track Order', 'irix-fulfillment-sl' ),
			];
		}

		return $actions;
	}
}
