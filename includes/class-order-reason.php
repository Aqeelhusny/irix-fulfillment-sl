<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Requires a reason whenever an order is moved to Returned or Refunded, and
 * records it as a private order note. The reason is captured via a popup on
 * the order edit screen (classic and HPOS) that runs before the order status
 * dropdown's value is submitted.
 */
final class IRIXFSL_Order_Reason {

	use IRIXFSL_Singleton;

	/** Order statuses (without the wc- prefix) that require a reason. */
	const STATUSES = [ 'returned', 'refunded' ];

	/** Prevents re-entry when we call update_status() to revert inside the hook. */
	private static bool $reverting = false;

	protected function boot(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'render_reason_field' ] );
		add_action( 'woocommerce_order_status_changed', [ $this, 'handle_status_changed' ], 10, 4 );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	public function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) return;

		wp_enqueue_script( 'irixfsl-order-reason', IRIXFSL_URL . 'assets/js/order-reason.js', [ 'jquery' ], IRIXFSL_VERSION, true );
		wp_localize_script( 'irixfsl-order-reason', 'irixfslOrderReason', [
			'statuses' => array_map( static fn( string $s ) => 'wc-' . $s, self::STATUSES ),
			'i18n'     => [
				'heading'     => __( 'Reason required', 'irix-fulfillment-sl' ),
				'description' => __( 'This will be saved as a private order note (not visible to the customer).', 'irix-fulfillment-sl' ),
				'placeholder' => __( 'e.g. Customer refused COD delivery, item damaged in transit…', 'irix-fulfillment-sl' ),
				'save'        => __( 'Save Reason', 'irix-fulfillment-sl' ),
				'cancel'      => __( 'Cancel', 'irix-fulfillment-sl' ),
				'required'    => __( 'Please enter a reason.', 'irix-fulfillment-sl' ),
			],
		] );
	}

	/**
	 * Renders a hidden field + nonce right after the order status dropdown, so
	 * the reason captured by the popup travels along with the normal order save.
	 * Fires on both the classic and HPOS order edit screens (shared meta box).
	 */
	public function render_reason_field( WC_Order $order ): void {
		wp_nonce_field( 'irixfsl_status_reason_' . $order->get_id(), 'irixfsl_status_reason_nonce' );
		echo '<input type="hidden" id="irixfsl_status_reason" name="irixfsl_status_reason" value="">';
	}

	/**
	 * Enforces that a reason was captured whenever an order is moved (via the
	 * classic or HPOS order-edit screen) to Returned or Refunded, and records
	 * it as a private order note. Transitions from any other origin (payment
	 * gateway refunds, REST API, cron, etc.) are left untouched — we only
	 * enforce inside our own screen's form submission, since those other
	 * flows never had a chance to show the popup in the first place.
	 */
	public function handle_status_changed( int $order_id, string $from, string $to, WC_Order $order ): void {
		if ( ! in_array( $to, self::STATUSES, true ) ) return;
		if ( self::$reverting ) return;
		if ( ! $this->is_order_edit_submission( $order_id ) ) return;
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;

		$nonce       = sanitize_text_field( wp_unslash( $_POST['irixfsl_status_reason_nonce'] ?? '' ) ); // phpcs:ignore
		$valid_nonce = wp_verify_nonce( $nonce, 'irixfsl_status_reason_' . $order_id );
		$reason      = $valid_nonce
			? trim( sanitize_textarea_field( wp_unslash( $_POST['irixfsl_status_reason'] ?? '' ) ) ) // phpcs:ignore
			: '';

		if ( $reason === '' ) {
			$this->revert( $order, $from, $to );
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: new status label, 2: reason text */
				__( 'Order marked as %1$s. Reason: %2$s', 'irix-fulfillment-sl' ),
				wc_get_order_status_name( 'wc-' . $to ),
				$reason
			),
			0,
			false
		);
	}

	/**
	 * True only when this request is the classic ("editpost") or HPOS
	 * ("edit_order") order-edit-screen form submission for this exact order —
	 * not an AJAX refund, REST API call, or programmatic status change.
	 */
	private function is_order_edit_submission( int $order_id ): bool {
		$action = sanitize_text_field( wp_unslash( $_POST['action'] ?? '' ) ); // phpcs:ignore
		if ( ! in_array( $action, [ 'editpost', 'edit_order' ], true ) ) return false;

		return absint( $_POST['post_ID'] ?? 0 ) === $order_id; // phpcs:ignore
	}

	private function revert( WC_Order $order, string $from, string $to ): void {
		self::$reverting = true;
		try {
			$order->update_status(
				$from,
				sprintf(
					/* translators: %s = attempted status label */
					__( 'Reverted: a reason is required before marking this order as %s.', 'irix-fulfillment-sl' ),
					wc_get_order_status_name( 'wc-' . $to )
				)
			);
		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf( 'Failed to revert order #%d from %s to %s: %s', $order->get_id(), $to, $from, $e->getMessage() ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
		}
		self::$reverting = false;

		set_transient( 'irixfsl_status_reason_missing_' . get_current_user_id(), $order->get_id(), 60 );
	}

	public function admin_notices(): void {
		$transient_key = 'irixfsl_status_reason_missing_' . get_current_user_id();
		$order_id      = get_transient( $transient_key );
		if ( ! $order_id ) return;

		delete_transient( $transient_key );
		echo '<div class="notice notice-error is-dismissible"><p>'
			. sprintf(
				/* translators: %d = order ID */
				esc_html__( 'Order #%d could not be updated — a reason is required when moving an order to Returned or Refunded.', 'irix-fulfillment-sl' ),
				absint( $order_id )
			)
			. '</p></div>';
	}
}
