<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Tracking {

	use IRIXFSL_Singleton;

	/** Prevents a double save if woocommerce_process_shop_order_meta fires twice in one request. */
	private static bool $tracking_saved = false;

	const META_CARRIER  = '_irixfsl_carrier';
	const META_NUMBER   = '_irixfsl_tracking_number';
	const META_URL      = '_irixfsl_tracking_url';
	const META_SENT     = '_irixfsl_tracking_email_sent';
	const META_LOCAL_DELIVERY_FEE_APPLIED = '_irixfsl_local_delivery_fee_applied';

	/** WooCommerce built-in pickup method IDs (core + Blocks + common extensions). */
	const PICKUP_METHOD_IDS = [ 'local_pickup', 'local_pickup_plus', 'pickup_location', 'pickup' ];

	protected function boot(): void {
		add_action( 'add_meta_boxes',                      [ $this, 'add_meta_box' ] );
		add_action( 'admin_enqueue_scripts',               [ $this, 'enqueue_assets' ] );
		// Fires on both the classic and HPOS order edit screens, at priority 10 —
		// before WC_Meta_Box_Order_Data::save (40) applies the status change, so
		// the tracking number is in place when the Shipped guard checks for it.
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save_meta' ] );

		// Priority 20: after the Shipped guard (10) may have reverted the order.
		// The email itself goes out in the background via Action Scheduler.
		add_action( 'woocommerce_order_status_changed', [ $this, 'on_status_changed' ], 20, 4 );
		add_action( 'irixfsl_send_tracking_email',      [ $this, 'maybe_send_tracking_email' ] );

		// AJAX: manual resend
		add_action( 'wp_ajax_irixfsl_resend_tracking', [ $this, 'ajax_resend' ] );
	}

	public function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) return;

		$carriers    = IRIXFSL_Settings::get( 'carriers' ) ?: [];
		$carrier_map = array_column( $carriers, 'url', 'name' );

		wp_enqueue_script( 'irixfsl-tracking', IRIXFSL_URL . 'assets/js/tracking.js', [ 'jquery' ], IRIXFSL_VERSION, true );
		wp_localize_script( 'irixfsl-tracking', 'irixfslTracking', [
			'carriers' => $carrier_map,
			'i18n'     => [
				'sending' => __( 'Sending…', 'irix-fulfillment-sl' ),
				'sent'    => __( 'Email Sent!', 'irix-fulfillment-sl' ),
				'retry'    => __( 'Retry', 'irix-fulfillment-sl' ),
				'sentPill' => __( 'Email sent', 'irix-fulfillment-sl' ),
			],
		] );
	}

	public function add_meta_box(): void {
		$screens = [ 'shop_order', 'woocommerce_page_wc-orders' ];
		foreach ( $screens as $screen ) {
			add_meta_box(
				'irixfsl-tracking',
				__( 'Shipment Tracking', 'irix-fulfillment-sl' ),
				[ $this, 'render_meta_box' ],
				$screen,
				'side',
				'high'
			);
		}
	}

	public function render_meta_box( $post_or_order ): void {
		$order = IRIXFSL_Helpers::resolve_order( $post_or_order );
		if ( ! $order ) return;

		$carrier          = $order->get_meta( self::META_CARRIER );
		$number           = $order->get_meta( self::META_NUMBER );
		$url              = $order->get_meta( self::META_URL );
		$sent             = $order->get_meta( self::META_SENT );
		$carriers         = IRIXFSL_Settings::get( 'carriers' ) ?: [];
		$fulfillment_type = self::get_fulfillment_type( $order );
		$local_delivery_row = $fulfillment_type === 'local_delivery' ? $this->find_matching_local_delivery_row( $order ) : null;
		$local_delivery_name = $local_delivery_row['name'] ?? '';
		// Default the Carrier field to the matched local delivery method's
		// name, but never override a value the admin already saved for this order.
		$effective_carrier = $carrier !== '' ? $carrier : $local_delivery_name;
		// The Carrier field is only editable while the order is actively being
		// fulfilled — afterward it's shown read-only so the historical record
		// (which carrier actually shipped it) can't be silently changed.
		$carrier_editable_statuses = [ 'processing', 'ready-to-ship', 'shipped' ];
		$carrier_is_editable       = in_array( $order->get_status(), $carrier_editable_statuses, true );

		// Store pickup: no tracking needed at all.
		if ( $fulfillment_type === 'pickup' ) {
			echo '<div class="irixfsl-callout irixfsl-callout--success">'
				. '<span class="dashicons dashicons-store" aria-hidden="true"></span>'
				. '<div><strong>' . esc_html__( 'Store Pickup', 'irix-fulfillment-sl' ) . '</strong>'
				. '<p>' . esc_html__( 'This order is set for in-store collection. No tracking number or shipping notification is required.', 'irix-fulfillment-sl' ) . '</p></div>'
				. '</div>';
			return;
		}

		wp_nonce_field( 'irixfsl_tracking_save', 'irixfsl_tracking_nonce' );

		if ( $fulfillment_type === 'local_delivery' ) : ?>
			<div class="irixfsl-callout irixfsl-callout--warning">
				<span class="dashicons dashicons-car" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Local Delivery', 'irix-fulfillment-sl' ); ?></strong>
					<p><?php esc_html_e( 'In-house delivery. Tracking number is optional (used as internal reference only). No external tracking link will be sent to the customer.', 'irix-fulfillment-sl' ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<div class="irixfsl-field">
			<label for="irixfsl_carrier"><?php esc_html_e( 'Carrier', 'irix-fulfillment-sl' ); ?></label>
			<?php if ( $carrier_is_editable ) : ?>
				<select id="irixfsl_carrier" name="irixfsl_carrier">
					<option value=""><?php esc_html_e( '— Select Carrier —', 'irix-fulfillment-sl' ); ?></option>
					<?php if ( $local_delivery_name !== '' ) : ?>
						<option value="<?php echo esc_attr( $local_delivery_name ); ?>" <?php selected( $effective_carrier, $local_delivery_name ); ?>>
							<?php echo esc_html( $local_delivery_name ); ?> (<?php esc_html_e( 'Local Delivery', 'irix-fulfillment-sl' ); ?>)
						</option>
					<?php endif; ?>
					<?php foreach ( $carriers as $c ) : ?>
						<option value="<?php echo esc_attr( $c['name'] ); ?>" data-url="<?php echo esc_attr( $c['url'] ); ?>" <?php selected( $effective_carrier, $c['name'] ); ?>>
							<?php echo esc_html( $c['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<span class="irixfsl-readonly"><?php echo esc_html( $carrier !== '' ? $carrier : __( '— none —', 'irix-fulfillment-sl' ) ); ?></span>
				<span class="description"><?php esc_html_e( 'Carrier can only be changed while the order is Processing, Ready to Ship, or Shipped.', 'irix-fulfillment-sl' ); ?></span>
				<?php // Preserve the saved value on submit — this field isn't rendered as an editable control outside the allowed statuses. ?>
				<input type="hidden" name="irixfsl_carrier" value="<?php echo esc_attr( $carrier ); ?>">
			<?php endif; ?>
		</div>

		<div class="irixfsl-field">
			<label for="irixfsl_tracking_number">
				<?php esc_html_e( 'Tracking Number', 'irix-fulfillment-sl' ); ?>
				<?php if ( $fulfillment_type === 'local_delivery' ) : ?>
					<span class="irixfsl-optional"><?php esc_html_e( 'optional — internal ref', 'irix-fulfillment-sl' ); ?></span>
				<?php endif; ?>
			</label>
			<input type="text" id="irixfsl_tracking_number" name="irixfsl_tracking_number" value="<?php echo esc_attr( $number ); ?>" autocomplete="off" spellcheck="false">
		</div>

		<?php if ( $fulfillment_type !== 'local_delivery' ) : ?>
		<div class="irixfsl-field">
			<label for="irixfsl_tracking_url"><?php esc_html_e( 'Tracking URL', 'irix-fulfillment-sl' ); ?></label>
			<input type="url" id="irixfsl_tracking_url" name="irixfsl_tracking_url" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'Auto-generated or enter manually', 'irix-fulfillment-sl' ); ?>">
			<span class="description"><?php esc_html_e( 'Auto-filled from carrier template. Override if needed.', 'irix-fulfillment-sl' ); ?></span>
		</div>
		<?php endif; ?>

		<?php if ( $number ) :
			$email_state = $sent ? 'sent' : ( as_next_scheduled_action( 'irixfsl_send_tracking_email', [ $order->get_id() ], 'irix-fulfillment-sl' ) ? 'queued' : 'none' );
			$email_label = [
				'sent'   => __( 'Email sent', 'irix-fulfillment-sl' ),
				'queued' => __( 'Email queued — sending shortly', 'irix-fulfillment-sl' ),
				'none'   => __( 'Email not sent yet', 'irix-fulfillment-sl' ),
			][ $email_state ];
			?>
		<div class="irixfsl-status-row">
			<?php echo IRIXFSL_Helpers::status_pill( $email_state, $email_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper ?>
		</div>
		<div class="irixfsl-actions">
			<button type="button" class="button" id="irixfsl-resend-tracking" data-order="<?php echo esc_attr( $order->get_id() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'irixfsl_resend_tracking' ) ); ?>">
				<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
				<span class="irixfsl-btn-label"><?php $sent ? esc_html_e( 'Resend Email', 'irix-fulfillment-sl' ) : esc_html_e( 'Send Email', 'irix-fulfillment-sl' ); ?></span>
			</button>
			<?php if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="button" id="irixfsl-check-tracking">
					<span class="dashicons dashicons-external" aria-hidden="true"></span>
					<?php esc_html_e( 'Check Tracking', 'irix-fulfillment-sl' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<?php
	}

	public function save_meta( $post_id_or_order ): void {
		if ( self::$tracking_saved ) return;
		if ( ! isset( $_POST['irixfsl_tracking_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['irixfsl_tracking_nonce'] ) ), 'irixfsl_tracking_save' ) ) return;

		$order = IRIXFSL_Helpers::resolve_order( $post_id_or_order );
		if ( ! $order ) return;

		self::$tracking_saved = true;
		$this->persist_tracking( $order );
	}

	private function persist_tracking( WC_Order $order ): void {
		$carrier = sanitize_text_field( wp_unslash( $_POST['irixfsl_carrier'] ?? '' ) ); // phpcs:ignore
		$number  = sanitize_text_field( wp_unslash( $_POST['irixfsl_tracking_number'] ?? '' ) ); // phpcs:ignore

		// Use sanitize_text_field (not esc_url_raw) so the {number} placeholder
		// survives long enough for us to substitute it below.
		$raw_url = sanitize_text_field( wp_unslash( $_POST['irixfsl_tracking_url'] ?? '' ) ); // phpcs:ignore

		// Replace {number} placeholder wherever it appears in the URL field.
		if ( $number && str_contains( $raw_url, '{number}' ) ) {
			$raw_url = str_replace( '{number}', rawurlencode( $number ), $raw_url );
		}

		$url = $raw_url ? esc_url_raw( $raw_url ) : '';

		// Auto-generate from carrier template when the field was left blank.
		if ( ! $url && $carrier && $number ) {
			$carriers = IRIXFSL_Settings::get( 'carriers' ) ?: [];
			foreach ( $carriers as $c ) {
				if ( $c['name'] === $carrier && ! empty( $c['url'] ) ) {
					$url = esc_url_raw( str_replace( '{number}', rawurlencode( $number ), $c['url'] ) );
					break;
				}
			}
		}

		$order->update_meta_data( self::META_CARRIER, $carrier );
		$order->update_meta_data( self::META_NUMBER, $number );
		$order->update_meta_data( self::META_URL, $url );

		try {
			$order->save_meta_data();
		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf( 'Failed to save tracking meta for order #%d: %s', $order->get_id(), $e->getMessage() ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
		}

		$this->maybe_apply_local_delivery_fee( $order );
	}

	/**
	 * When an order uses a shipping method configured (in Settings → Local
	 * Delivery Methods) with a fee, adds that fee to the order as a "Local
	 * Delivery Fee" line item and recalculates totals — once only, ever, per
	 * order, tracked via order meta so re-saving the order never duplicates it.
	 *
	 * Runs on every admin save of a local-delivery order (see persist_tracking()
	 * callers); the META_LOCAL_DELIVERY_FEE_APPLIED flag is set *before* the
	 * order is re-saved below, so the resulting recursive
	 * save never re-applies it.
	 */
	private function maybe_apply_local_delivery_fee( WC_Order $order ): void {
		if ( $order->get_meta( self::META_LOCAL_DELIVERY_FEE_APPLIED ) ) return;
		// Only while the order is still open — never re-price an order that has
		// already shipped, completed, or been cancelled/refunded/returned.
		if ( ! $order->has_status( [ 'pending', 'on-hold', 'processing', 'ready-to-ship' ] ) ) return;
		if ( self::get_fulfillment_type( $order ) !== 'local_delivery' ) return;

		$fee = $this->find_matching_local_delivery_fee( $order );
		if ( $fee === null || (float) $fee <= 0 ) return;

		$order->update_meta_data( self::META_LOCAL_DELIVERY_FEE_APPLIED, '1' );

		try {
			$fee_item = new WC_Order_Item_Fee();
			$fee_item->set_name( __( 'Local Delivery Fee', 'irix-fulfillment-sl' ) );
			$fee_item->set_amount( $fee );
			$fee_item->set_total( $fee );
			$fee_item->set_tax_status( 'none' );

			$order->add_item( $fee_item );
			$order->calculate_totals();
			$order->save();
		} catch ( \Exception $e ) {
			wc_get_logger()->error(
				sprintf( 'Failed to apply local delivery fee for order #%d: %s', $order->get_id(), $e->getMessage() ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
		}
	}

	/**
	 * Finds the configured fee for whichever of the order's shipping methods
	 * matches a configured local_delivery_methods row, or null if none match
	 * or the matching row has no fee configured.
	 */
	private function find_matching_local_delivery_fee( WC_Order $order ): ?string {
		$row = $this->find_matching_local_delivery_row( $order );
		$fee = $row['fee'] ?? '';
		return $fee !== '' ? $fee : null;
	}

	/**
	 * Finds the configured local_delivery_methods row (name/method_id/fee)
	 * matching whichever of the order's shipping methods was used, or null
	 * if none match.
	 */
	private function find_matching_local_delivery_row( WC_Order $order ): ?array {
		$rows_by_method_id = [];
		foreach ( (array) ( IRIXFSL_Settings::get( 'local_delivery_methods' ) ?: [] ) as $row ) {
			if ( ! empty( $row['method_id'] ) ) {
				$rows_by_method_id[ $row['method_id'] ] = $row;
			}
		}

		foreach ( $order->get_shipping_methods() as $method ) {
			$id = $method->get_method_id();
			if ( isset( $rows_by_method_id[ $id ] ) ) {
				return $rows_by_method_id[ $id ];
			}
		}

		return null;
	}

	public function on_status_changed( int $order_id, string $from, string $to, WC_Order $order ): void {
		// has_status() reflects a revert made earlier in this same hook.
		if ( $to !== 'shipped' || ! $order->has_status( 'shipped' ) ) return;
		as_enqueue_async_action( 'irixfsl_send_tracking_email', [ $order_id ], 'irix-fulfillment-sl', true );
	}

	/** Action Scheduler callback — also safe to call directly. */
	public function maybe_send_tracking_email( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_get_logger()->warning(
				sprintf( 'Tracking email skipped: order #%d not found.', $order_id ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
			return;
		}

		// Only send once — skip if already sent.
		if ( $order->get_meta( self::META_SENT ) ) return;

		$type = self::get_fulfillment_type( $order );

		// Store pickup orders: customer collects in person — no shipping email.
		if ( $type === 'pickup' ) return;

		// Local delivery: send a notification without external tracking link.
		// Standard: require a tracking number before sending.
		if ( $type === 'standard' ) {
			$number = $order->get_meta( self::META_NUMBER );
			if ( ! $number ) return;
		}

		if ( ! $this->send_tracking_email( $order, $type ) ) {
			wc_get_logger()->error(
				sprintf( 'Tracking email failed for order #%d (type: %s).', $order->get_id(), $type ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
		}
	}

	public function send_tracking_email( WC_Order $order, string $fulfillment_type = 'standard' ): bool {
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['IRIXFSL_Email_Tracking'] ) ) {
			$sent = $emails['IRIXFSL_Email_Tracking']->trigger( $order->get_id(), $order, $fulfillment_type );
			// Only record the flag when the email actually went out — trigger()
			// returns false when the email is disabled or the order has no recipient.
			if ( $sent ) {
				$order->update_meta_data( self::META_SENT, '1' );
				$order->save_meta_data();
			}
			return $sent;
		}
		return false;
	}

	public function ajax_resend(): void {
		check_ajax_referer( 'irixfsl_resend_tracking', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
			return;
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( 'Order not found' );
			return;
		}

		$sent = $this->send_tracking_email( $order, self::get_fulfillment_type( $order ) );
		$sent ? wp_send_json_success() : wp_send_json_error( 'Failed to send' );
	}

	public static function get_tracking( WC_Order $order ): array {
		return [
			'carrier' => $order->get_meta( self::META_CARRIER ),
			'number'  => $order->get_meta( self::META_NUMBER ),
			'url'     => $order->get_meta( self::META_URL ),
		];
	}

	/**
	 * Returns the fulfillment type for an order:
	 *   'pickup'         — WooCommerce local pickup (auto-detected)
	 *   'local_delivery' — in-house delivery (configured in settings)
	 *   'standard'       — normal order requiring external tracking
	 */
	public static function get_fulfillment_type( WC_Order $order ): string {
		$pickup_ids = self::PICKUP_METHOD_IDS;

		// Admin-configured in-house delivery method IDs.
		$local_ids  = array_column( (array) ( IRIXFSL_Settings::get( 'local_delivery_methods' ) ?: [] ), 'method_id' );

		foreach ( $order->get_shipping_methods() as $method ) {
			$id = $method->get_method_id();
			if ( in_array( $id, $pickup_ids, true ) ) {
				return 'pickup';
			}
			if ( in_array( $id, $local_ids, true ) ) {
				return 'local_delivery';
			}
		}

		return 'standard';
	}
}
