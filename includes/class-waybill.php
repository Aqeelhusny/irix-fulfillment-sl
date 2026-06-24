<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Waybill {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', [ $this, 'maybe_render' ], 5 );
	}

	public function maybe_render(): void {
		if ( ! isset( $_GET['irixfsl_waybill'] ) ) return; // phpcs:ignore

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'irix-fulfillment-sl' ) );
		}

		$order_id = absint( $_GET['order_id'] ?? 0 ); // phpcs:ignore
		$nonce    = sanitize_text_field( $_GET['nonce'] ?? '' ); // phpcs:ignore

		if ( ! wp_verify_nonce( $nonce, 'irixfsl_waybill_' . $order_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'irix-fulfillment-sl' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'irix-fulfillment-sl' ) );
		}

		$tracking = IRIXFSL_Tracking::get_tracking( $order );

		// Waybill is available for Ready to Ship orders (no tracking number yet)
		// and for any order that already has a tracking number saved.
		$has_tracking  = ! empty( $tracking['number'] );
		$is_ready      = $order->has_status( 'ready-to-ship' );

		if ( ! $has_tracking && ! $is_ready ) {
			wp_die( esc_html__( 'Waybill is only available once the order is marked Ready to Ship or a tracking number has been saved.', 'irix-fulfillment-sl' ) );
		}

		$s              = IRIXFSL_Settings::get();
		$logo_url       = $s['company_logo_id'] ? wp_get_attachment_image_url( $s['company_logo_id'], 'medium' ) : '';
		$barcode_js_url = IRIXFSL_URL . 'assets/js/barcode.js';
		$print_url      = IRIXFSL_URL . 'assets/css/print.css';
		$scan_url       = $s['waybill_scan_url'] ?? '';

		include IRIXFSL_DIR . 'templates/waybill.php';
		exit;
	}

	public static function waybill_url( int $order_id ): string {
		return add_query_arg( [
			'irixfsl_waybill' => '1',
			'order_id'      => $order_id,
			'nonce'         => wp_create_nonce( 'irixfsl_waybill_' . $order_id ),
		], home_url( '/' ) );
	}
}
