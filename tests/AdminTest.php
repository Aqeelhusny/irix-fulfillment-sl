<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use IRIXFSL_Admin;

final class AdminTest extends TestCase {

	private function instance(): IRIXFSL_Admin {
		return IRIXFSL_Admin::instance();
	}

	// ── add_documents_column() ──────────────────────────────────────────

	public function test_add_documents_column_inserts_before_wc_actions(): void {
		$columns = [
			'cb'          => '<input type="checkbox">',
			'order_number' => 'Order',
			'order_date'   => 'Date',
			'wc_actions'   => 'Actions',
		];

		$result = $this->instance()->add_documents_column( $columns );
		$keys   = array_keys( $result );

		$docIdx    = array_search( 'irixfsl_documents', $keys );
		$actionIdx = array_search( 'wc_actions', $keys );

		$this->assertNotFalse( $docIdx );
		$this->assertLessThan( $actionIdx, $docIdx );
	}

	public function test_add_documents_column_appends_if_no_wc_actions(): void {
		$columns = [
			'cb'          => '<input type="checkbox">',
			'order_number' => 'Order',
		];

		$result = $this->instance()->add_documents_column( $columns );
		$keys   = array_keys( $result );

		$this->assertContains( 'irixfsl_documents', $keys );
		// Should be the last key.
		$this->assertSame( 'irixfsl_documents', end( $keys ) );
	}

	public function test_add_documents_column_preserves_existing_columns(): void {
		$columns = [
			'cb'          => '<input type="checkbox">',
			'order_number' => 'Order',
			'wc_actions'   => 'Actions',
		];

		$result = $this->instance()->add_documents_column( $columns );

		$this->assertArrayHasKey( 'cb', $result );
		$this->assertArrayHasKey( 'order_number', $result );
		$this->assertArrayHasKey( 'wc_actions', $result );
		$this->assertArrayHasKey( 'irixfsl_documents', $result );
	}

	public function test_add_documents_column_label_is_fulfillment(): void {
		$columns = [ 'wc_actions' => 'Actions' ];

		$result = $this->instance()->add_documents_column( $columns );

		$this->assertSame( 'Fulfillment', $result['irixfsl_documents'] );
	}

	// ── add_bulk_actions() ──────────────────────────────────────────────

	public function test_add_bulk_actions_adds_print_invoices(): void {
		$actions = [];
		$result  = $this->instance()->add_bulk_actions( $actions );

		$this->assertArrayHasKey( 'print_invoices', $result );
		$this->assertArrayHasKey( 'print_packing_slips', $result );
	}

	public function test_add_bulk_actions_preserves_existing(): void {
		$actions = [ 'trash' => 'Move to Trash' ];
		$result  = $this->instance()->add_bulk_actions( $actions );

		$this->assertArrayHasKey( 'trash', $result );
		$this->assertCount( 3, $result );
	}

	// ── handle_bulk_invoices() ──────────────────────────────────────────

	public function test_handle_bulk_invoices_returns_redirect_with_encoded_url(): void {
		$result = $this->instance()->handle_bulk_invoices(
			'https://example.com/wp-admin/edit.php',
			'print_invoices',
			[ 100, 200 ]
		);

		$this->assertStringContainsString( 'irixfsl_bulk_invoice_redirect=', $result );
	}

	// ── handle_bulk_slips() ─────────────────────────────────────────────

	public function test_handle_bulk_slips_returns_redirect_with_encoded_url(): void {
		$result = $this->instance()->handle_bulk_slips(
			'https://example.com/wp-admin/edit.php',
			'print_packing_slips',
			[ 100, 200 ]
		);

		$this->assertStringContainsString( 'irixfsl_bulk_slip_redirect=', $result );
	}
}
