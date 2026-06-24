<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Tracking {

	private static ?self $instance = null;

	/** Prevents double-save when both Classic and HPOS hooks fire in the same request. */
	private static bool $tracking_saved = false;

	const META_CARRIER  = '_irixfsl_carrier';
	const META_NUMBER   = '_irixfsl_tracking_number';
	const META_URL      = '_irixfsl_tracking_url';
	const META_SENT     = '_irixfsl_tracking_email_sent';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes',                      [ $this, 'add_meta_box' ] );
		add_action( 'admin_enqueue_scripts',               [ $this, 'enqueue_assets' ] );
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save_meta' ] );
		add_action( 'save_post_shop_order',                [ $this, 'save_meta' ] );

		// HPOS save
		add_action( 'woocommerce_after_order_object_save', [ $this, 'hpos_save_meta' ] );

		// Auto-send tracking email when status moves to shipped.
		// WooCommerce fires woocommerce_order_status_{status} with the wc- prefix stripped.
		add_action( 'woocommerce_order_status_shipped', [ $this, 'maybe_send_tracking_email' ] );

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
				'retry'   => __( 'Retry', 'irix-fulfillment-sl' ),
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
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order ) return;

		$carrier          = $order->get_meta( self::META_CARRIER );
		$number           = $order->get_meta( self::META_NUMBER );
		$url              = $order->get_meta( self::META_URL );
		$sent             = $order->get_meta( self::META_SENT );
		$carriers         = IRIXFSL_Settings::get( 'carriers' ) ?: [];
		$fulfillment_type = self::get_fulfillment_type( $order );

		// Store pickup: no tracking needed at all.
		if ( $fulfillment_type === 'pickup' ) {
			echo '<p style="color:#1a7a2a;font-size:12px;display:flex;align-items:center;gap:5px;">'
				. '<span class="dashicons dashicons-store" style="color:#1a7a2a"></span>'
				. '<strong>' . esc_html__( 'Store Pickup', 'irix-fulfillment-sl' ) . '</strong></p>'
				. '<p style="color:#555;font-size:12px">'
				. esc_html__( 'This order is set for in-store collection. No tracking number or shipping notification is required.', 'irix-fulfillment-sl' )
				. '</p>';
			return;
		}

		wp_nonce_field( 'irixfsl_tracking_save', 'irixfsl_tracking_nonce' );

		// Local delivery notice banner.
		if ( $fulfillment_type === 'local_delivery' ) : ?>
			<p style="background:#fff8e1;border-left:3px solid #f0b429;padding:6px 8px;font-size:11px;margin-bottom:8px;color:#7c5500;">
				<span class="dashicons dashicons-car" style="vertical-align:middle;font-size:14px;color:#f0b429"></span>
				<strong><?php esc_html_e( 'Local Delivery', 'irix-fulfillment-sl' ); ?></strong> —
				<?php esc_html_e( 'In-house delivery. Tracking number is optional (used as internal reference only). No external tracking link will be sent to the customer.', 'irix-fulfillment-sl' ); ?>
			</p>
		<?php endif; ?>
		<p>
			<label for="irixfsl_carrier"><strong><?php esc_html_e( 'Carrier', 'irix-fulfillment-sl' ); ?></strong></label><br>
			<select id="irixfsl_carrier" name="irixfsl_carrier" style="width:100%">
				<option value=""><?php esc_html_e( '— Select Carrier —', 'irix-fulfillment-sl' ); ?></option>
				<?php foreach ( $carriers as $c ) : ?>
					<option value="<?php echo esc_attr( $c['name'] ); ?>" data-url="<?php echo esc_attr( $c['url'] ); ?>" <?php selected( $carrier, $c['name'] ); ?>>
						<?php echo esc_html( $c['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="irixfsl_tracking_number">
				<strong><?php esc_html_e( 'Tracking Number', 'irix-fulfillment-sl' ); ?></strong>
				<?php if ( $fulfillment_type === 'local_delivery' ) : ?>
					<span style="font-weight:400;color:#888"> (<?php esc_html_e( 'optional — internal ref', 'irix-fulfillment-sl' ); ?>)</span>
				<?php endif; ?>
			</label><br>
			<input type="text" id="irixfsl_tracking_number" name="irixfsl_tracking_number" value="<?php echo esc_attr( $number ); ?>" style="width:100%">
		</p>
		<?php if ( $fulfillment_type !== 'local_delivery' ) : ?>
		<p>
			<label for="irixfsl_tracking_url"><strong><?php esc_html_e( 'Tracking URL', 'irix-fulfillment-sl' ); ?></strong></label><br>
			<input type="url" id="irixfsl_tracking_url" name="irixfsl_tracking_url" value="<?php echo esc_attr( $url ); ?>" style="width:100%" placeholder="Auto-generated or enter manually">
			<span class="description"><?php esc_html_e( 'Auto-filled from carrier template. Override if needed.', 'irix-fulfillment-sl' ); ?></span>
		</p>
		<?php endif; ?>
		<?php if ( $number ) : ?>
		<p>
			<button type="button" class="button button-secondary" id="irixfsl-resend-tracking" data-order="<?php echo esc_attr( $order->get_id() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'irixfsl_resend_tracking' ) ); ?>">
				<?php $sent ? esc_html_e( 'Resend Tracking Email', 'irix-fulfillment-sl' ) : esc_html_e( 'Send Tracking Email', 'irix-fulfillment-sl' ); ?>
			</button>
			<?php if ( $sent ) : ?>
				<span style="color:#1a7a2a;margin-left:6px">&#10003; <?php esc_html_e( 'Email sent', 'irix-fulfillment-sl' ); ?></span>
			<?php endif; ?>
		</p>
		<?php endif; ?>
		<?php
	}

	public function save_meta( $post_id_or_order ): void {
		if ( self::$tracking_saved ) return;
		if ( ! isset( $_POST['irixfsl_tracking_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['irixfsl_tracking_nonce'] ) ), 'irixfsl_tracking_save' ) ) return;

		$order = $post_id_or_order instanceof WC_Order
			? $post_id_or_order
			: wc_get_order( $post_id_or_order );

		if ( ! $order ) return;

		self::$tracking_saved = true;
		$this->persist_tracking( $order );
	}

	public function hpos_save_meta( WC_Order $order ): void {
		if ( self::$tracking_saved ) return;
		if ( ! isset( $_POST['irixfsl_tracking_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['irixfsl_tracking_nonce'] ) ), 'irixfsl_tracking_save' ) ) return;

		self::$tracking_saved = true;
		$this->persist_tracking( $order );
	}

	private function persist_tracking( WC_Order $order ): void {
		$carrier = sanitize_text_field( $_POST['irixfsl_carrier'] ?? '' ); // phpcs:ignore
		$number  = sanitize_text_field( $_POST['irixfsl_tracking_number'] ?? '' ); // phpcs:ignore

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
		$order->save_meta_data();
	}

	public function maybe_send_tracking_email( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

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

		$this->send_tracking_email( $order, $type );
	}

	public function send_tracking_email( WC_Order $order, string $fulfillment_type = 'standard' ): bool {
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['IRIXFSL_Email_Tracking'] ) ) {
			$emails['IRIXFSL_Email_Tracking']->trigger( $order->get_id(), $order, $fulfillment_type );
			$order->update_meta_data( self::META_SENT, '1' );
			$order->save_meta_data();
			return true;
		}
		wc_get_logger()->error(
			sprintf( 'Tracking email class not found for order #%d', $order->get_id() ),
			[ 'source' => 'irix-fulfillment-sl' ]
		);
		return false;
	}

	public function ajax_resend(): void {
		check_ajax_referer( 'irixfsl_resend_tracking', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( 'Order not found' );
		}

		$sent = $this->send_tracking_email( $order );
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
		// WooCommerce built-in pickup method IDs (core + common extensions).
		$pickup_ids = [ 'local_pickup', 'local_pickup_plus', 'pickup_location', 'pickup' ];

		// Admin-configured in-house delivery method IDs.
		$local_ids  = (array) ( IRIXFSL_Settings::get( 'local_delivery_methods' ) ?: [] );

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
