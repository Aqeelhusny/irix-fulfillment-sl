<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use WC_Order;
use IRIXFSL_Customer_Portal;
use IRIXFSL_Invoice;
use IRIXFSL_Tracking;

final class CustomerPortalTest extends TestCase {

	private function instance(): IRIXFSL_Customer_Portal {
		return IRIXFSL_Customer_Portal::instance();
	}

	// ── ENDPOINT_TRACK constant ─────────────────────────────────────────

	public function test_endpoint_constant_is_track_order(): void {
		$this->assertSame( 'track-order', IRIXFSL_Customer_Portal::ENDPOINT_TRACK );
	}

	// ── add_query_vars() ────────────────────────────────────────────────

	public function test_add_query_vars_adds_track_order(): void {
		$vars = [ 'existing_var' ];

		$result = $this->instance()->add_query_vars( $vars );

		$this->assertContains( 'track-order', $result );
		$this->assertContains( 'existing_var', $result );
	}

	public function test_add_query_vars_preserves_existing(): void {
		$vars = [ 'a', 'b', 'c' ];

		$result = $this->instance()->add_query_vars( $vars );

		$this->assertCount( 4, $result );
	}

	// ── add_menu_item() ─────────────────────────────────────────────────

	public function test_add_menu_item_inserts_after_orders(): void {
		$items = [
			'dashboard' => 'Dashboard',
			'orders'    => 'Orders',
			'downloads' => 'Downloads',
			'edit-account' => 'Account details',
		];

		$result = $this->instance()->add_menu_item( $items );
		$keys   = array_keys( $result );

		$ordersIdx = array_search( 'orders', $keys );
		$trackIdx  = array_search( 'track-order', $keys );

		$this->assertNotFalse( $trackIdx );
		$this->assertSame( $ordersIdx + 1, $trackIdx );
	}

	public function test_add_menu_item_label_is_track_order(): void {
		$items = [ 'orders' => 'Orders' ];

		$result = $this->instance()->add_menu_item( $items );

		$this->assertSame( 'Track Order', $result['track-order'] );
	}

	public function test_add_menu_item_without_orders_key_does_not_add(): void {
		$items = [ 'dashboard' => 'Dashboard' ];

		$result = $this->instance()->add_menu_item( $items );

		// Track order is only added after 'orders' key.
		$this->assertArrayNotHasKey( 'track-order', $result );
	}

	// ── add_order_actions() ─────────────────────────────────────────────

	public function test_add_order_actions_always_adds_invoice(): void {
		$order = new WC_Order( 600 );
		$actions = [];

		$result = $this->instance()->add_order_actions( $actions, $order );

		$this->assertArrayHasKey( 'irixfsl_invoice', $result );
		$this->assertSame( 'Invoice', $result['irixfsl_invoice']['name'] );
		$this->assertNotEmpty( $result['irixfsl_invoice']['url'] );
	}

	public function test_add_order_actions_adds_track_when_tracking_exists(): void {
		$order = new WC_Order( 601 );
		$order->update_meta_data( '_irixfsl_tracking_number', 'TRK123' );
		$order->update_meta_data( '_irixfsl_tracking_url', 'https://track.example.com/TRK123' );
		$actions = [];

		$result = $this->instance()->add_order_actions( $actions, $order );

		$this->assertArrayHasKey( 'irixfsl_track', $result );
		$this->assertSame( 'Track Order', $result['irixfsl_track']['name'] );
		$this->assertSame( 'https://track.example.com/TRK123', $result['irixfsl_track']['url'] );
	}

	public function test_add_order_actions_no_track_when_no_tracking(): void {
		$order = new WC_Order( 602 );
		$actions = [];

		$result = $this->instance()->add_order_actions( $actions, $order );

		$this->assertArrayNotHasKey( 'irixfsl_track', $result );
	}

	public function test_add_order_actions_track_falls_back_to_endpoint_url(): void {
		$order = new WC_Order( 603 );
		$order->update_meta_data( '_irixfsl_tracking_number', 'TRK999' );
		// No tracking URL — should fall back to account endpoint.
		$actions = [];

		$result = $this->instance()->add_order_actions( $actions, $order );

		$this->assertArrayHasKey( 'irixfsl_track', $result );
		$this->assertStringContainsString( 'track-order', $result['irixfsl_track']['url'] );
	}

	public function test_add_order_actions_preserves_existing_actions(): void {
		$order   = new WC_Order( 604 );
		$actions = [
			'view' => [ 'url' => '#', 'name' => 'View' ],
		];

		$result = $this->instance()->add_order_actions( $actions, $order );

		$this->assertArrayHasKey( 'view', $result );
		$this->assertArrayHasKey( 'irixfsl_invoice', $result );
	}
}
