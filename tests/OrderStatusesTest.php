<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use WC_Order;
use WC_Order_Item_Shipping;
use IRIXFSL_Order_Statuses;
use IRIXFSL_Settings;

final class OrderStatusesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		IRIXFSL_Settings::flush_cache();
		global $_irixfsl_test_options, $_irixfsl_test_post_stati, $_irixfsl_test_transients, $_irixfsl_test_logger;
		$_irixfsl_test_options    = [];
		$_irixfsl_test_post_stati = [];
		$_irixfsl_test_transients = [];
		$_irixfsl_test_logger     = null;
	}

	private function instance(): IRIXFSL_Order_Statuses {
		return IRIXFSL_Order_Statuses::instance();
	}

	// ── register_statuses() ─────────────────────────────────────────────

	public function test_register_statuses_creates_ready_to_ship(): void {
		$this->instance()->register_statuses();

		global $_irixfsl_test_post_stati;
		$this->assertArrayHasKey( 'wc-ready-to-ship', $_irixfsl_test_post_stati );
	}

	public function test_register_statuses_creates_shipped(): void {
		$this->instance()->register_statuses();

		global $_irixfsl_test_post_stati;
		$this->assertArrayHasKey( 'wc-shipped', $_irixfsl_test_post_stati );
	}

	public function test_register_statuses_skips_if_already_registered(): void {
		global $_irixfsl_test_post_stati;
		$_irixfsl_test_post_stati['wc-ready-to-ship'] = [ 'label' => 'Existing' ];
		$_irixfsl_test_post_stati['wc-shipped']        = [ 'label' => 'Existing' ];

		$this->instance()->register_statuses();

		// Should keep the existing definitions, not overwrite.
		$this->assertSame( 'Existing', $_irixfsl_test_post_stati['wc-ready-to-ship']['label'] );
		$this->assertSame( 'Existing', $_irixfsl_test_post_stati['wc-shipped']['label'] );
	}

	// ── add_to_wc_statuses() ────────────────────────────────────────────

	public function test_add_to_wc_statuses_inserts_after_processing(): void {
		$statuses = [
			'wc-pending'    => 'Pending payment',
			'wc-processing' => 'Processing',
			'wc-on-hold'    => 'On hold',
			'wc-completed'  => 'Completed',
		];

		$result = $this->instance()->add_to_wc_statuses( $statuses );

		$keys = array_keys( $result );
		$processingIdx    = array_search( 'wc-processing', $keys );
		$readyToShipIdx   = array_search( 'wc-ready-to-ship', $keys );
		$shippedIdx       = array_search( 'wc-shipped', $keys );
		$onHoldIdx        = array_search( 'wc-on-hold', $keys );

		$this->assertNotFalse( $readyToShipIdx );
		$this->assertNotFalse( $shippedIdx );
		$this->assertGreaterThan( $processingIdx, $readyToShipIdx );
		$this->assertGreaterThan( $readyToShipIdx, $shippedIdx );
		$this->assertGreaterThan( $shippedIdx, $onHoldIdx );
	}

	public function test_add_to_wc_statuses_does_not_duplicate(): void {
		$statuses = [
			'wc-pending'        => 'Pending payment',
			'wc-processing'     => 'Processing',
			'wc-ready-to-ship'  => 'Ready to Ship',
			'wc-shipped'        => 'Shipped',
			'wc-completed'      => 'Completed',
		];

		$result = $this->instance()->add_to_wc_statuses( $statuses );

		$count = array_count_values( array_keys( $result ) );
		$this->assertSame( 1, $count['wc-ready-to-ship'] );
		$this->assertSame( 1, $count['wc-shipped'] );
	}

	// ── add_to_paid_statuses() ──────────────────────────────────────────

	public function test_add_to_paid_statuses_adds_both(): void {
		$statuses = [ 'processing', 'completed' ];

		$result = $this->instance()->add_to_paid_statuses( $statuses );

		$this->assertContains( 'ready-to-ship', $result );
		$this->assertContains( 'shipped', $result );
		$this->assertContains( 'processing', $result );
		$this->assertContains( 'completed', $result );
	}

	public function test_add_to_paid_statuses_preserves_existing(): void {
		$statuses = [ 'processing', 'completed' ];

		$result = $this->instance()->add_to_paid_statuses( $statuses );

		$this->assertCount( 4, $result );
	}

	// ── add_bulk_actions() ──────────────────────────────────────────────

	public function test_add_bulk_actions_adds_mark_ready_and_shipped(): void {
		$actions = [ 'trash' => 'Move to Trash' ];

		$result = $this->instance()->add_bulk_actions( $actions );

		$this->assertArrayHasKey( 'mark_ready_to_ship', $result );
		$this->assertArrayHasKey( 'mark_shipped', $result );
		$this->assertArrayHasKey( 'trash', $result );
	}

	// ── enforce_tracking_for_shipped() ──────────────────────────────────

	public function test_enforce_tracking_ignores_non_shipped_transitions(): void {
		$order = new WC_Order( 300 );
		$order->set_status( 'processing' );

		// Should not throw or set transient for non-shipped transitions.
		$this->instance()->enforce_tracking_for_shipped( 300, 'pending', 'processing', $order );

		global $_irixfsl_test_transients;
		$this->assertEmpty( $_irixfsl_test_transients );
	}

	public function test_enforce_tracking_allows_shipped_with_tracking(): void {
		$order = new WC_Order( 301 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );
		$order->update_meta_data( '_irixfsl_tracking_number', 'TRK12345' );
		$order->set_status( 'shipped' );

		$this->instance()->enforce_tracking_for_shipped( 301, 'processing', 'shipped', $order );

		// Should not revert — order keeps shipped status.
		global $_irixfsl_test_transients;
		$this->assertEmpty( $_irixfsl_test_transients );
	}

	public function test_enforce_tracking_reverts_shipped_without_tracking(): void {
		$order = new WC_Order( 302 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );
		// No tracking number set.
		$order->set_status( 'shipped' );

		$this->instance()->enforce_tracking_for_shipped( 302, 'processing', 'shipped', $order );

		// Should set a transient to show error.
		global $_irixfsl_test_transients;
		$this->assertSame( 302, $_irixfsl_test_transients['irixfsl_shipped_no_tracking_1'] );
		// Order should be reverted.
		$this->assertSame( 'processing', $order->get_status() );
	}

	public function test_enforce_tracking_allows_pickup_without_tracking(): void {
		$order = new WC_Order( 303 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'local_pickup' ) ] );
		$order->set_status( 'shipped' );

		$this->instance()->enforce_tracking_for_shipped( 303, 'processing', 'shipped', $order );

		// Pickup should be exempt — no transient set.
		global $_irixfsl_test_transients;
		$this->assertEmpty( $_irixfsl_test_transients );
	}

	public function test_enforce_tracking_allows_local_delivery_without_tracking(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [ 'my_delivery' ],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 304 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'my_delivery' ) ] );
		$order->set_status( 'shipped' );

		$this->instance()->enforce_tracking_for_shipped( 304, 'processing', 'shipped', $order );

		global $_irixfsl_test_transients;
		$this->assertEmpty( $_irixfsl_test_transients );
	}

	// ── handle_bulk_ready / handle_bulk_shipped ─────────────────────────

	public function test_handle_bulk_ready_returns_modified_redirect(): void {
		global $_irixfsl_test_user_caps;
		$_irixfsl_test_user_caps = [ 'manage_woocommerce' => true ];

		global $_irixfsl_test_orders;
		$order = new WC_Order( 400 );
		$order->set_status( 'processing' );
		$_irixfsl_test_orders = [ 400 => $order ];

		$result = $this->instance()->handle_bulk_ready( 'https://example.com/admin', 'mark_ready_to_ship', [ 400 ] );

		$this->assertStringContainsString( 'irixfsl_bulk_changed=1', $result );
	}

	public function test_handle_bulk_shipped_skips_standard_without_tracking(): void {
		global $_irixfsl_test_user_caps;
		$_irixfsl_test_user_caps = [ 'manage_woocommerce' => true ];

		$order = new WC_Order( 401 );
		$order->set_status( 'processing' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );

		global $_irixfsl_test_orders;
		$_irixfsl_test_orders = [ 401 => $order ];

		$result = $this->instance()->handle_bulk_shipped( 'https://example.com/admin', 'mark_shipped', [ 401 ] );

		$this->assertStringContainsString( 'irixfsl_bulk_skipped=1', $result );
	}

	public function test_handle_bulk_shipped_succeeds_with_tracking(): void {
		global $_irixfsl_test_user_caps;
		$_irixfsl_test_user_caps = [ 'manage_woocommerce' => true ];

		$order = new WC_Order( 402 );
		$order->set_status( 'processing' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );
		$order->update_meta_data( '_irixfsl_tracking_number', 'TRK999' );

		global $_irixfsl_test_orders;
		$_irixfsl_test_orders = [ 402 => $order ];

		$result = $this->instance()->handle_bulk_shipped( 'https://example.com/admin', 'mark_shipped', [ 402 ] );

		$this->assertStringContainsString( 'irixfsl_bulk_changed=1', $result );
	}

	public function test_handle_bulk_shipped_allows_pickup_without_tracking(): void {
		global $_irixfsl_test_user_caps;
		$_irixfsl_test_user_caps = [ 'manage_woocommerce' => true ];

		$order = new WC_Order( 403 );
		$order->set_status( 'processing' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'local_pickup' ) ] );

		global $_irixfsl_test_orders;
		$_irixfsl_test_orders = [ 403 => $order ];

		$result = $this->instance()->handle_bulk_shipped( 'https://example.com/admin', 'mark_shipped', [ 403 ] );

		$this->assertStringContainsString( 'irixfsl_bulk_changed=1', $result );
	}

	public function test_handle_bulk_unauthorized_returns_redirect_unchanged(): void {
		global $_irixfsl_test_user_caps;
		$_irixfsl_test_user_caps = []; // No capabilities.

		$result = $this->instance()->handle_bulk_ready( 'https://example.com/admin', 'mark_ready_to_ship', [ 500 ] );

		$this->assertSame( 'https://example.com/admin', $result );
	}
}
