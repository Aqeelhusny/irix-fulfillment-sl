<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Invoice {

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
		if ( ! isset( $_GET['irixfsl_invoice'] ) ) return; // phpcs:ignore

		$order_id  = absint( $_GET['order_id'] ?? 0 ); // phpcs:ignore
		$order_key = sanitize_text_field( $_GET['order_key'] ?? '' ); // phpcs:ignore
		$is_admin  = isset( $_GET['admin'] ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore

		if ( $is_admin && isset( $_GET['nonce'] ) ) { // phpcs:ignore
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'irixfsl_invoice_' . $order_id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'irix-fulfillment-sl' ) );
			}
		} elseif ( ! $is_admin ) {
			if ( ! $order_key ) wp_die( esc_html__( 'Invalid request.', 'irix-fulfillment-sl' ) );
		} else {
			wp_die( esc_html__( 'Unauthorized.', 'irix-fulfillment-sl' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) wp_die( esc_html__( 'Order not found.', 'irix-fulfillment-sl' ) );

		if ( ! $is_admin && ! hash_equals( $order->get_order_key(), $order_key ) ) {
			wp_die( esc_html__( 'Access denied.', 'irix-fulfillment-sl' ) );
		}

		$this->render_invoice( $order );
		exit;
	}

	public function render_invoice( WC_Order $order ): void {
		$s          = IRIXFSL_Settings::get();
		$currency   = $order->get_currency();
		$logo_url   = $s['company_logo_id'] ? wp_get_attachment_image_url( $s['company_logo_id'], 'medium' ) : '';
		$items      = $order->get_items();
		$print_url  = IRIXFSL_URL . 'assets/css/print.css';

		include IRIXFSL_DIR . 'templates/invoice.php';
	}

	public static function invoice_url( int $order_id, bool $admin = true, string $order_key = '' ): string {
		if ( $admin ) {
			return add_query_arg( [
				'irixfsl_invoice' => '1',
				'order_id'      => $order_id,
				'admin'         => '1',
				'nonce'         => wp_create_nonce( 'irixfsl_invoice_' . $order_id ),
			], home_url( '/' ) );
		}
		return add_query_arg( [
			'irixfsl_invoice' => '1',
			'order_id'      => $order_id,
			'order_key'     => $order_key,
		], home_url( '/' ) );
	}
}
