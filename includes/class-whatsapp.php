<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sends a WhatsApp Business Cloud API (Meta) template message to the
 * customer when an order is marked Shipped. Requires a pre-approved
 * Utility-category template — freeform text cannot be sent for
 * business-initiated notifications outside a customer conversation window.
 */
final class IRIXFSL_Whatsapp {

	use IRIXFSL_Singleton;

	const META_SENT = '_irixfsl_whatsapp_sent';

	/** Meta Graph API version — each is supported ~2 years; v25.0 until July 2028. */
	const API_VERSION = 'v25.0';

	protected function boot(): void {
		add_action( 'add_meta_boxes',                   [ $this, 'add_meta_box' ] );
		add_action( 'admin_enqueue_scripts',            [ $this, 'enqueue_assets' ] );
		// Priority 20: after IRIXFSL_Order_Statuses::enforce_tracking_for_shipped (10)
		// has had a chance to revert the order. woocommerce_order_status_shipped
		// fires before that guard, so it would message orders that get reverted.
		add_action( 'woocommerce_order_status_changed', [ $this, 'on_status_changed' ], 20, 4 );
		add_action( 'irixfsl_send_whatsapp',            [ $this, 'maybe_send_shipped_message' ] );
		add_action( 'wp_ajax_irixfsl_resend_whatsapp',  [ $this, 'ajax_resend' ] );
	}

	public function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) return;

		wp_enqueue_script( 'irixfsl-whatsapp', IRIXFSL_URL . 'assets/js/whatsapp.js', [ 'jquery' ], IRIXFSL_VERSION, true );
		wp_localize_script( 'irixfsl-whatsapp', 'irixfslWhatsapp', [
			'i18n' => [
				'sending' => __( 'Sending…', 'irix-fulfillment-sl' ),
				'sent'    => __( 'Message Sent!', 'irix-fulfillment-sl' ),
				'retry'    => __( 'Retry', 'irix-fulfillment-sl' ),
				'sentPill' => __( 'WhatsApp sent', 'irix-fulfillment-sl' ),
			],
		] );
	}

	public function add_meta_box(): void {
		$screens = [ 'shop_order', 'woocommerce_page_wc-orders' ];
		foreach ( $screens as $screen ) {
			add_meta_box(
				'irixfsl-whatsapp',
				__( 'WhatsApp Notification', 'irix-fulfillment-sl' ),
				[ $this, 'render_meta_box' ],
				$screen,
				'side',
				'default'
			);
		}
	}

	public function render_meta_box( $post_or_order ): void {
		$order = IRIXFSL_Helpers::resolve_order( $post_or_order );
		if ( ! $order ) return;

		$settings   = IRIXFSL_Settings::get();
		$configured = ! empty( $settings['whatsapp_enabled'] )
			&& $settings['whatsapp_phone_number_id'] !== ''
			&& $settings['whatsapp_access_token'] !== ''
			&& $settings['whatsapp_template_name'] !== '';

		if ( ! $configured ) {
			echo '<div class="irixfsl-callout irixfsl-callout--muted">'
				. '<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>'
				. '<div><p>' . esc_html__( 'WhatsApp notifications are not configured yet.', 'irix-fulfillment-sl' )
				. ' <a href="' . esc_url( admin_url( 'admin.php?page=irixfsl-settings#whatsapp' ) ) . '">'
				. esc_html__( 'Set it up in Settings', 'irix-fulfillment-sl' )
				. '</a></p></div></div>';
			return;
		}

		$sent  = $order->get_meta( self::META_SENT );
		$state = $sent ? 'sent' : ( as_next_scheduled_action( 'irixfsl_send_whatsapp', [ $order->get_id() ], 'irix-fulfillment-sl' ) ? 'queued' : 'none' );
		$label = [
			'sent'   => __( 'WhatsApp sent', 'irix-fulfillment-sl' ),
			'queued' => __( 'Queued — sending shortly', 'irix-fulfillment-sl' ),
			'none'   => __( 'Not sent yet', 'irix-fulfillment-sl' ),
		][ $state ];
		$phone = $order->get_billing_phone();
		?>
		<div class="irixfsl-status-row">
			<?php echo IRIXFSL_Helpers::status_pill( $state, $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper ?>
		</div>
		<p class="irixfsl-meta-line">
			<span class="dashicons dashicons-phone" aria-hidden="true"></span>
			<?php echo $phone !== '' ? esc_html( $phone ) : esc_html__( 'No billing phone on this order', 'irix-fulfillment-sl' ); ?>
		</p>
		<div class="irixfsl-actions">
			<button type="button" class="button" id="irixfsl-resend-whatsapp" data-order="<?php echo esc_attr( $order->get_id() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'irixfsl_resend_whatsapp' ) ); ?>" <?php disabled( $phone === '' ); ?>>
				<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
				<span class="irixfsl-btn-label"><?php $sent ? esc_html_e( 'Resend WhatsApp', 'irix-fulfillment-sl' ) : esc_html_e( 'Send WhatsApp', 'irix-fulfillment-sl' ); ?></span>
			</button>
		</div>
		<?php
	}

	public function on_status_changed( int $order_id, string $from, string $to, WC_Order $order ): void {
		// has_status() reflects a revert made earlier in this same hook.
		if ( $to !== 'shipped' || ! $order->has_status( 'shipped' ) ) return;
		// Sent in the background so a slow Meta API never stalls the order save.
		as_enqueue_async_action( 'irixfsl_send_whatsapp', [ $order_id ], 'irix-fulfillment-sl', true );
	}

	/**
	 * Action Scheduler callback for a settled Shipped order — sends once, skipping
	 * silently if the feature isn't enabled/configured or already sent.
	 */
	public function maybe_send_shipped_message( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		if ( $order->get_meta( self::META_SENT ) ) return;

		$settings = IRIXFSL_Settings::get();
		if ( empty( $settings['whatsapp_enabled'] ) ) return;

		$this->send_shipped_message( $order );
	}

	/**
	 * Sends the "order shipped" template message for the given order via the
	 * Meta WhatsApp Business Cloud API. Returns true only on a confirmed
	 * successful send — callers should not mark anything else as done unless
	 * this returns true.
	 */
	public function send_shipped_message( WC_Order $order ): bool {
		$settings         = IRIXFSL_Settings::get();
		$phone_number_id  = $settings['whatsapp_phone_number_id'] ?? '';
		$access_token     = $settings['whatsapp_access_token'] ?? '';
		$template_name    = $settings['whatsapp_template_name'] ?? '';
		$template_lang    = $settings['whatsapp_template_language'] ?: 'en_US';
		$calling_code     = $settings['whatsapp_default_calling_code'] ?: '94';

		if ( $phone_number_id === '' || $access_token === '' || $template_name === '' ) {
			wc_get_logger()->warning(
				sprintf( 'WhatsApp message not sent for order #%d — WhatsApp is not fully configured.', $order->get_id() ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
			return false;
		}

		$to = self::to_whatsapp_number( $order->get_billing_phone(), $calling_code );
		if ( $to === null ) {
			wc_get_logger()->warning(
				sprintf( 'WhatsApp message not sent for order #%d — no usable billing phone number.', $order->get_id() ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
			return false;
		}

		$tracking         = IRIXFSL_Tracking::get_tracking( $order );
		$tracking_display = $tracking['url'] ?: ( $tracking['carrier'] ?: __( 'N/A', 'irix-fulfillment-sl' ) );
		$customer_name    = $order->get_formatted_billing_full_name() ?: __( 'Customer', 'irix-fulfillment-sl' );
		$store_name       = $settings['company_name'] ?: get_bloginfo( 'name' );

		$body = [
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'template',
			'template'          => [
				'name'     => $template_name,
				'language' => [ 'code' => $template_lang ],
				'components' => [
					[
						'type'       => 'body',
						'parameters' => [
							[ 'type' => 'text', 'text' => $customer_name ],
							[ 'type' => 'text', 'text' => (string) $order->get_order_number() ],
							[ 'type' => 'text', 'text' => $tracking_display ],
							[ 'type' => 'text', 'text' => $store_name ],
						],
					],
				],
			],
		];

		$response = wp_remote_post(
			sprintf(
				'https://graph.facebook.com/%s/%s/messages',
				apply_filters( 'irixfsl_whatsapp_api_version', self::API_VERSION ),
				rawurlencode( $phone_number_id )
			),
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			wc_get_logger()->error(
				sprintf( 'WhatsApp send failed for order #%d: %s', $order->get_id(), $response->get_error_message() ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			wc_get_logger()->error(
				sprintf( 'WhatsApp API returned HTTP %d for order #%d: %s', $code, $order->get_id(), wp_remote_retrieve_body( $response ) ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
			return false;
		}

		$order->update_meta_data( self::META_SENT, '1' );
		$order->save_meta_data();

		return true;
	}

	public function ajax_resend(): void {
		check_ajax_referer( 'irixfsl_resend_whatsapp', 'nonce' );

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

		$sent = $this->send_shipped_message( $order );
		$sent ? wp_send_json_success() : wp_send_json_error( 'Failed to send' );
	}

	/**
	 * Converts a phone number to the digits-only, country-code-prefixed
	 * format the WhatsApp API expects for its "to" field. Local trunk-prefix
	 * numbers (starting with "0", e.g. "0771234567") have the leading 0
	 * replaced with the store's default calling code. Numbers that already
	 * include a country code (with or without a leading "+") are used as-is.
	 * Returns null if no digits remain.
	 */
	public static function to_whatsapp_number( string $raw, string $default_calling_code ): ?string {
		$trimmed = trim( $raw );
		if ( $trimmed === '' ) return null;

		$digits = preg_replace( '/\D+/', '', $trimmed );
		if ( $digits === '' || $digits === null ) return null;

		if ( $trimmed[0] === '0' ) {
			$digits = $default_calling_code . substr( $digits, 1 );
		}

		return $digits;
	}
}
