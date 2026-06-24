<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Packing_Slip {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', [ $this, 'maybe_render' ] );
	}

	public function maybe_render(): void {
		if ( ! isset( $_GET['irixfsl_packing_slip'] ) ) return; // phpcs:ignore
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( esc_html__( 'Unauthorized.', 'irix-fulfillment-sl' ) );

		$order_ids_raw = sanitize_text_field( $_GET['order_ids'] ?? '' ); // phpcs:ignore
		$nonce         = sanitize_text_field( $_GET['nonce'] ?? '' ); // phpcs:ignore

		if ( ! wp_verify_nonce( $nonce, 'irixfsl_packing_slip' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'irix-fulfillment-sl' ) );
		}

		$ids    = array_filter( array_map( 'absint', explode( ',', $order_ids_raw ) ) );
		$orders = array_filter( array_map( 'wc_get_order', $ids ) );

		if ( empty( $orders ) ) wp_die( esc_html__( 'No valid orders found.', 'irix-fulfillment-sl' ) );

		$s         = IRIXFSL_Settings::get();
		$logo_url  = $s['company_logo_id'] ? wp_get_attachment_image_url( $s['company_logo_id'], 'medium' ) : '';
		$print_url = IRIXFSL_URL . 'assets/css/print.css';

		include IRIXFSL_DIR . 'templates/packing-slip.php';
		exit;
	}

	public static function packing_slip_url( array $order_ids ): string {
		return add_query_arg( [
			'irixfsl_packing_slip' => '1',
			'order_ids'          => implode( ',', array_map( 'absint', $order_ids ) ),
			'nonce'              => wp_create_nonce( 'irixfsl_packing_slip' ),
		], home_url( '/' ) );
	}
}
