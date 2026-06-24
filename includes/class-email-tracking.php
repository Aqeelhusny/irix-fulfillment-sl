<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// WC_Email is guaranteed to exist here — this file is only require_once'd
// inside the woocommerce_email_classes filter, which WC fires after its mailer loads.
class WCFSL_Email_Tracking extends WC_Email {

	/** 'standard' | 'local_delivery' */
	private string $fulfillment_type = 'standard';

	public function __construct() {
		$this->id             = 'wcfsl_tracking_notification';
		$this->customer_email = true;
		$this->title          = __( 'Shipment Tracking Notification', 'wc-fulfillment-sl' );
		$this->description    = __( 'Sent to the customer when an order is shipped or out for local delivery.', 'wc-fulfillment-sl' );
		$this->heading        = __( 'Your order has been shipped!', 'wc-fulfillment-sl' );
		$this->subject        = __( 'Your {site_title} order #{order_number} has been shipped', 'wc-fulfillment-sl' );
		$this->template_html  = 'emails/wcfsl-tracking-notification.php';
		$this->template_plain = '';
		$this->placeholders   = [
			'{site_title}'   => $this->get_blogname(),
			'{order_number}' => '',
			'{order_date}'   => '',
		];

		parent::__construct();

		$this->template_base = WCFSL_DIR . 'templates/';
	}

	public function trigger( int $order_id, ?WC_Order $order = null, string $fulfillment_type = 'standard' ): void {
		$this->setup_locale();
		$this->fulfillment_type = $fulfillment_type;

		if ( $order_id && ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( is_a( $order, 'WC_Order' ) ) {
			$this->object    = $order;
			$this->recipient = $order->get_billing_email();
			$this->placeholders['{order_number}'] = $order->get_order_number();
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
		}

		// Adjust subject and heading for local delivery.
		if ( $fulfillment_type === 'local_delivery' ) {
			$this->heading = __( 'Your order is out for delivery!', 'wc-fulfillment-sl' );
			$this->subject = __( 'Your {site_title} order #{order_number} is on its way', 'wc-fulfillment-sl' );
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'            => $this->object,
				'email_heading'    => $this->get_heading(),
				'email'            => $this,
				'tracking'         => WCFSL_Tracking::get_tracking( $this->object ),
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

		$tracking = WCFSL_Tracking::get_tracking( $this->object );
		$carrier  = $tracking['carrier'] ?: __( 'N/A', 'wc-fulfillment-sl' );
		$number   = $tracking['number']  ?: '';
		$url      = $tracking['url']     ?: '';

		$content  = $this->get_heading() . "\n\n";
		$content .= sprintf( __( 'Order: #%s', 'wc-fulfillment-sl' ), $this->object->get_order_number() ) . "\n";

		if ( $this->fulfillment_type === 'local_delivery' ) {
			$content .= __( 'Your order is being delivered by our local delivery team.', 'wc-fulfillment-sl' ) . "\n";
			if ( $number ) {
				$content .= sprintf( __( 'Delivery reference: %s', 'wc-fulfillment-sl' ), $number ) . "\n";
			}
		} else {
			if ( $carrier ) $content .= sprintf( __( 'Carrier: %s', 'wc-fulfillment-sl' ), $carrier ) . "\n";
			if ( $number )  $content .= sprintf( __( 'Tracking Number: %s', 'wc-fulfillment-sl' ), $number ) . "\n";
			if ( $url )     $content .= sprintf( __( 'Track here: %s', 'wc-fulfillment-sl' ), $url ) . "\n";
		}

		return $content;
	}
}
