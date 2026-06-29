<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Order_Statuses {

	use IRIXFSL_Singleton;

	/** Prevents re-entry when we call update_status() to revert inside the hook. */
	private static bool $reverting = false;

	protected function boot(): void {
		add_action( 'init',                        [ $this, 'register_statuses' ] );
		add_filter( 'wc_order_statuses',           [ $this, 'add_to_wc_statuses' ] );
		add_filter( 'woocommerce_order_is_paid_statuses', [ $this, 'add_to_paid_statuses' ] );

		// Bulk actions on orders list (Classic)
		add_filter( 'bulk_actions-edit-shop_order',               [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_action-edit-shop_order-mark_ready_to_ship', [ $this, 'handle_bulk_ready' ], 10, 3 );
		add_filter( 'handle_bulk_action-edit-shop_order-mark_shipped',       [ $this, 'handle_bulk_shipped' ], 10, 3 );

		// HPOS bulk actions
		add_filter( 'bulk_actions-woocommerce_page_wc-orders',               [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_action-woocommerce_page_wc-orders-mark_ready_to_ship', [ $this, 'handle_bulk_ready' ], 10, 3 );
		add_filter( 'handle_bulk_action-woocommerce_page_wc-orders-mark_shipped',       [ $this, 'handle_bulk_shipped' ], 10, 3 );

		// Server-side guard: revert individual "Shipped" saves without a tracking number.
		add_action( 'woocommerce_order_status_changed', [ $this, 'enforce_tracking_for_shipped' ], 10, 4 );

		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	public function register_statuses(): void {
		$registered = get_post_stati();

		if ( ! isset( $registered['wc-ready-to-ship'] ) ) {
			register_post_status( 'wc-ready-to-ship', [
				'label'                     => _x( 'Ready to Ship', 'Order status', 'irix-fulfillment-sl' ),
				'public'                    => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'exclude_from_search'       => false,
				/* translators: %s = count */
				'label_count'               => _n_noop( 'Ready to Ship <span class="count">(%s)</span>', 'Ready to Ship <span class="count">(%s)</span>', 'irix-fulfillment-sl' ),
			] );
		}

		if ( ! isset( $registered['wc-shipped'] ) ) {
			register_post_status( 'wc-shipped', [
				'label'                     => _x( 'Shipped', 'Order status', 'irix-fulfillment-sl' ),
				'public'                    => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'exclude_from_search'       => false,
				/* translators: %s = count */
				'label_count'               => _n_noop( 'Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>', 'irix-fulfillment-sl' ),
			] );
		}
	}

	public function add_to_wc_statuses( array $statuses ): array {
		$new = [];
		foreach ( $statuses as $key => $label ) {
			$new[ $key ] = $label;
			// Insert our statuses after "processing" only if not already present.
			if ( $key === 'wc-processing' ) {
				if ( ! isset( $statuses['wc-ready-to-ship'] ) ) {
					$new['wc-ready-to-ship'] = _x( 'Ready to Ship', 'Order status', 'irix-fulfillment-sl' );
				}
				if ( ! isset( $statuses['wc-shipped'] ) ) {
					$new['wc-shipped'] = _x( 'Shipped', 'Order status', 'irix-fulfillment-sl' );
				}
			}
		}
		return $new;
	}

	public function add_to_paid_statuses( array $statuses ): array {
		$statuses[] = 'ready-to-ship';
		$statuses[] = 'shipped';
		return $statuses;
	}

	public function add_bulk_actions( array $actions ): array {
		$actions['mark_ready_to_ship'] = __( 'Change status to Ready to Ship', 'irix-fulfillment-sl' );
		$actions['mark_shipped']       = __( 'Change status to Shipped', 'irix-fulfillment-sl' );
		return $actions;
	}

	public function handle_bulk_ready( string $redirect, string $action, array $ids ): string {
		return $this->bulk_change_status( $redirect, $ids, 'ready-to-ship' );
	}

	public function handle_bulk_shipped( string $redirect, string $action, array $ids ): string {
		return $this->bulk_change_status( $redirect, $ids, 'shipped' );
	}

	private function bulk_change_status( string $redirect, array $ids, string $status ): string {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect;
		}

		$changed = 0;
		$skipped = 0;

		foreach ( $ids as $id ) {
			$order = wc_get_order( absint( $id ) );
			if ( ! $order ) continue;

			// Shipped requires a waybill/tracking number — unless it's a
			// store pickup or configured local delivery method.
			if ( $status === 'shipped' ) {
				$type = IRIXFSL_Tracking::get_fulfillment_type( $order );
				if ( $type === 'standard' ) {
					$tracking = IRIXFSL_Tracking::get_tracking( $order );
					if ( empty( $tracking['number'] ) ) {
						$skipped++;
						continue;
					}
				}
			}

			try {
				$result = $order->update_status( $status, __( 'Status changed via bulk action.', 'irix-fulfillment-sl' ) );
				if ( $result ) {
					$changed++;
				} else {
					$skipped++;
				}
			} catch ( \Exception $e ) {
				wc_get_logger()->error(
					sprintf( 'Bulk status change failed for order #%d: %s', $order->get_id(), $e->getMessage() ),
					[ 'source' => 'irix-fulfillment-sl' ]
				);
				$skipped++;
			}
		}

		$args = [ 'irixfsl_bulk_changed' => $changed ];
		if ( $skipped ) {
			$args['irixfsl_bulk_skipped'] = $skipped;
		}
		return add_query_arg( $args, remove_query_arg( [ 'irixfsl_bulk_changed', 'irixfsl_bulk_skipped' ], $redirect ) );
	}

	/**
	 * Server-side fallback: if an individual order is saved as "Shipped" without
	 * a tracking number, revert it to its previous status and set a transient so
	 * admin_notices can surface an error.
	 */
	public function enforce_tracking_for_shipped( int $order_id, string $from, string $to, WC_Order $order ): void {
		if ( $to !== 'shipped' ) return;

		// Prevent re-entry: if we're already in the middle of reverting, bail out.
		if ( self::$reverting ) return;

		// Pickup and local delivery orders don't need a tracking number.
		$type = IRIXFSL_Tracking::get_fulfillment_type( $order );
		if ( $type !== 'standard' ) return;

		$tracking = IRIXFSL_Tracking::get_tracking( $order );
		if ( ! empty( $tracking['number'] ) ) return;

		self::$reverting = true;
		$reverted = false;
		try {
			$reverted = $order->update_status(
				$from,
				__( 'Reverted: a waybill / tracking number is required to mark this order as Shipped.', 'irix-fulfillment-sl' )
			);
		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf( 'Failed to revert order #%d from Shipped to %s: %s', $order_id, $from, $e->getMessage() ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
		}
		self::$reverting = false;

		if ( ! $reverted ) {
			wc_get_logger()->error(
				sprintf( 'Order #%d could not be reverted from Shipped — it may remain in an incorrect status.', $order_id ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
		}

		set_transient( 'irixfsl_shipped_no_tracking_' . get_current_user_id(), $order_id, 60 );
	}

	public function admin_notices(): void {
		// Bulk action result: changed count.
		if ( ! empty( $_GET['irixfsl_bulk_changed'] ) ) { // phpcs:ignore
			$count = absint( $_GET['irixfsl_bulk_changed'] ); // phpcs:ignore
			echo '<div class="notice notice-success is-dismissible"><p>'
				. sprintf(
					/* translators: %d = number of orders updated */
					esc_html( _n( '%d order status updated.', '%d order statuses updated.', $count, 'irix-fulfillment-sl' ) ),
					$count
				)
				. '</p></div>';
		}

		// Bulk action result: skipped (no tracking number).
		if ( ! empty( $_GET['irixfsl_bulk_skipped'] ) ) { // phpcs:ignore
			$skipped = absint( $_GET['irixfsl_bulk_skipped'] ); // phpcs:ignore
			echo '<div class="notice notice-warning is-dismissible"><p>'
				. sprintf(
					/* translators: %d = number of orders skipped */
					esc_html( _n(
						'%d order was skipped — a waybill / tracking number is required before marking an order as Shipped.',
						'%d orders were skipped — a waybill / tracking number is required before marking orders as Shipped.',
						$skipped,
						'irix-fulfillment-sl'
					) ),
					$skipped
				)
				. '</p></div>';
		}

		// Single-order revert notice (set by enforce_tracking_for_shipped).
		$transient_key = 'irixfsl_shipped_no_tracking_' . get_current_user_id();
		$order_id      = get_transient( $transient_key );
		if ( $order_id ) {
			delete_transient( $transient_key );
			echo '<div class="notice notice-error is-dismissible"><p>'
				. sprintf(
					/* translators: %d = order ID */
					esc_html__( 'Order #%d could not be moved to Shipped — please enter a waybill / tracking number in the Shipment Tracking panel first.', 'irix-fulfillment-sl' ),
					absint( $order_id )
				)
				. '</p></div>';
		}
	}

}
