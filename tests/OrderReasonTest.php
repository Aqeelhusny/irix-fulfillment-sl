<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use WC_Order;
use IRIXFSL_Order_Reason;

final class OrderReasonTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $_irixfsl_test_transients, $_irixfsl_test_logger, $_irixfsl_test_user_caps;
		$_irixfsl_test_transients = [];
		$_irixfsl_test_logger     = null;
		$_irixfsl_test_user_caps  = [ 'manage_woocommerce' => true ];
		$_POST                    = [];
	}

	protected function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	private function instance(): IRIXFSL_Order_Reason {
		return IRIXFSL_Order_Reason::instance();
	}

	private function postFromEditScreen( int $order_id, string $action = 'editpost' ): void {
		$_POST['action']  = $action;
		$_POST['post_ID'] = $order_id;
	}

	// ── Ignores irrelevant transitions ──────────────────────────────────

	public function test_ignores_transitions_to_other_statuses(): void {
		$order = new WC_Order( 600 );
		$order->set_status( 'processing' );
		$this->postFromEditScreen( 600 );

		$this->instance()->handle_status_changed( 600, 'pending', 'processing', $order );

		$this->assertSame( [], $order->get_notes() );
		global $_irixfsl_test_transients;
		$this->assertEmpty( $_irixfsl_test_transients );
	}

	// ── Ignores transitions outside the order-edit screen ───────────────

	public function test_ignores_transitions_not_from_order_edit_screen(): void {
		// No $_POST['action']/post_ID set — simulates a gateway/API/cron-driven
		// status change (e.g. a payment gateway auto-marking an order refunded).
		$order = new WC_Order( 601 );
		$order->set_status( 'refunded' );

		$this->instance()->handle_status_changed( 601, 'processing', 'refunded', $order );

		$this->assertSame( [], $order->get_notes() );
		$this->assertSame( 'refunded', $order->get_status() );
		global $_irixfsl_test_transients;
		$this->assertEmpty( $_irixfsl_test_transients );
	}

	// ── Reverts when the reason is missing ──────────────────────────────

	public function test_reverts_returned_transition_without_reason(): void {
		$order = new WC_Order( 602 );
		$order->set_status( 'returned' );
		$this->postFromEditScreen( 602 );
		// No irixfsl_status_reason / nonce set.

		$this->instance()->handle_status_changed( 602, 'processing', 'returned', $order );

		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( [], $order->get_notes() );

		global $_irixfsl_test_transients;
		$this->assertSame( 602, $_irixfsl_test_transients['irixfsl_status_reason_missing_1'] );
	}

	public function test_reverts_refunded_transition_with_blank_reason(): void {
		$order = new WC_Order( 603 );
		$order->set_status( 'refunded' );
		$this->postFromEditScreen( 603 );
		$_POST['irixfsl_status_reason_nonce'] = wp_create_nonce( 'irixfsl_status_reason_603' );
		$_POST['irixfsl_status_reason']       = '   ';

		$this->instance()->handle_status_changed( 603, 'processing', 'refunded', $order );

		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( [], $order->get_notes() );
	}

	public function test_reverts_when_nonce_is_invalid(): void {
		$order = new WC_Order( 604 );
		$order->set_status( 'returned' );
		$this->postFromEditScreen( 604 );
		$_POST['irixfsl_status_reason_nonce'] = 'not-a-valid-nonce';
		$_POST['irixfsl_status_reason']       = 'Customer refused delivery.';

		$this->instance()->handle_status_changed( 604, 'processing', 'returned', $order );

		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( [], $order->get_notes() );
	}

	// ── Records the private note when a reason is provided ──────────────

	public function test_adds_private_note_for_returned_with_valid_reason(): void {
		$order = new WC_Order( 605 );
		$order->set_status( 'returned' );
		$this->postFromEditScreen( 605 );
		$_POST['irixfsl_status_reason_nonce'] = wp_create_nonce( 'irixfsl_status_reason_605' );
		$_POST['irixfsl_status_reason']       = 'Customer refused COD delivery.';

		$this->instance()->handle_status_changed( 605, 'processing', 'returned', $order );

		$this->assertSame( 'returned', $order->get_status() );
		$notes = $order->get_notes();
		$this->assertCount( 1, $notes );
		$this->assertStringContainsString( 'Customer refused COD delivery.', $notes[0]['note'] );
		// Must be a private note — not shown to the customer.
		$this->assertSame( 0, $notes[0]['is_customer_note'] );

		global $_irixfsl_test_transients;
		$this->assertEmpty( $_irixfsl_test_transients );
	}

	public function test_adds_private_note_for_refunded_on_hpos_edit_screen(): void {
		$order = new WC_Order( 606 );
		$order->set_status( 'refunded' );
		$this->postFromEditScreen( 606, 'edit_order' );
		$_POST['irixfsl_status_reason_nonce'] = wp_create_nonce( 'irixfsl_status_reason_606' );
		$_POST['irixfsl_status_reason']       = 'Item damaged in transit.';

		$this->instance()->handle_status_changed( 606, 'processing', 'refunded', $order );

		$this->assertSame( 'refunded', $order->get_status() );
		$this->assertCount( 1, $order->get_notes() );
	}

	// ── Unauthorized users are ignored (no revert, no note) ─────────────

	public function test_ignores_unauthorized_user(): void {
		global $_irixfsl_test_user_caps;
		$_irixfsl_test_user_caps = [];

		$order = new WC_Order( 607 );
		$order->set_status( 'returned' );
		$this->postFromEditScreen( 607 );

		$this->instance()->handle_status_changed( 607, 'processing', 'returned', $order );

		$this->assertSame( 'returned', $order->get_status() );
		$this->assertSame( [], $order->get_notes() );
		global $_irixfsl_test_transients;
		$this->assertEmpty( $_irixfsl_test_transients );
	}
}
