<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Waybill {

	use IRIXFSL_Singleton;

	protected function boot(): void {
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

		if ( ! IRIXFSL_Helpers::is_waybill_available( $order ) ) {
			wp_die( esc_html__( 'Waybill is only available once the order is marked Ready to Ship or a tracking number has been saved.', 'irix-fulfillment-sl' ) );
		}

		$ctx            = IRIXFSL_Helpers::get_document_context();
		$s              = $ctx['settings'];
		$logo_url       = $ctx['logo_url'];
		$print_url      = $ctx['print_url'];
		$tracking       = IRIXFSL_Tracking::get_tracking( $order );
		$barcode_js_url = IRIXFSL_URL . 'assets/js/barcode.js';
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
