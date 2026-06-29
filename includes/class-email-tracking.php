<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// WC_Email is guaranteed to exist here — this file is only require_once'd
// inside the woocommerce_email_classes filter, which WC fires after its mailer loads.
class IRIXFSL_Email_Tracking extends WC_Email {

	/** 'standard' | 'local_delivery' */
	private string $fulfillment_type = 'standard';

	public function __construct() {
		$this->id             = 'irixfsl_tracking_notification';
		$this->customer_email = true;
		$this->title          = __( 'Shipment Tracking Notification', 'irix-fulfillment-sl' );
		$this->description    = __( 'Sent to the customer when an order is shipped or out for local delivery.', 'irix-fulfillment-sl' );
		$this->heading        = __( 'Your order has been shipped!', 'irix-fulfillment-sl' );
		$this->subject        = __( 'Your {site_title} order #{order_number} has been shipped', 'irix-fulfillment-sl' );
		$this->template_html  = 'emails/irixfsl-tracking-notification.php';
		$this->template_plain = '';
		$this->placeholders   = [
			'{site_title}'   => $this->get_blogname(),
			'{order_number}' => '',
			'{order_date}'   => '',
		];

		parent::__construct();

		$this->template_base = IRIXFSL_DIR . 'templates/';
	}

	public function trigger( int $order_id, ?WC_Order $order = null, string $fulfillment_type = 'standard' ): void {
		$this->setup_locale();
		$this->fulfillment_type = $fulfillment_type;

		if ( $order_id && ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			wc_get_logger()->error(
				sprintf( 'Tracking email trigger failed: invalid order (ID %d).', $order_id ),
				[ 'source' => 'irix-fulfillment-sl' ]
			);
			$this->restore_locale();
			return;
		}

		$this->object    = $order;
		$this->recipient = $order->get_billing_email();
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );

		// Adjust subject and heading for local delivery.
		if ( $fulfillment_type === 'local_delivery' ) {
			$this->heading = __( 'Your order is out for delivery!', 'irix-fulfillment-sl' );
			$this->subject = __( 'Your {site_title} order #{order_number} is on its way', 'irix-fulfillment-sl' );
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_content_html(): string {
		if ( ! $this->object instanceof WC_Order ) {
			wc_get_logger()->error(
				'get_content_html() called without a valid order object.',
				[ 'source' => 'irix-fulfillment-sl' ]
			);
			return '';
		}

		return wc_get_template_html(
			$this->template_html,
			[
				'order'            => $this->object,
				'email_heading'    => $this->get_heading(),
				'email'            => $this,
				'tracking'         => IRIXFSL_Tracking::get_tracking( $this->object ),
				'fulfillment_type' => $this->fulfillment_type,
				'sent_to_admin'    => false,
				'plain_text'       => false,
			],
			'',
			$this->template_base
		);
	}

	public function get_content_plain(): string {
		if ( ! $this->object instanceof WC_Order ) return '';

		$tracking = IRIXFSL_Tracking::get_tracking( $this->object );
		$carrier  = $tracking['carrier'] ?: __( 'N/A', 'irix-fulfillment-sl' );
		$number   = $tracking['number']  ?: '';
		$url      = $tracking['url']     ?: '';

		$content  = $this->get_heading() . "\n\n";
		$content .= sprintf( __( 'Order: #%s', 'irix-fulfillment-sl' ), $this->object->get_order_number() ) . "\n";

		if ( $this->fulfillment_type === 'local_delivery' ) {
			$content .= __( 'Your order is being delivered by our local delivery team.', 'irix-fulfillment-sl' ) . "\n";
			if ( $number ) {
				$content .= sprintf( __( 'Delivery reference: %s', 'irix-fulfillment-sl' ), $number ) . "\n";
			}
		} else {
			if ( $carrier ) $content .= sprintf( __( 'Carrier: %s', 'irix-fulfillment-sl' ), $carrier ) . "\n";
			if ( $number )  $content .= sprintf( __( 'Tracking Number: %s', 'irix-fulfillment-sl' ), $number ) . "\n";
			if ( $url )     $content .= sprintf( __( 'Track here: %s', 'irix-fulfillment-sl' ), $url ) . "\n";
		}

		return $content;
	}
}
