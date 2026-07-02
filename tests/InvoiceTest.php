<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use IRIXFSL_Invoice;

final class InvoiceTest extends TestCase {

	// ── invoice_url() ───────────────────────────────────────────────────

	public function test_admin_invoice_url_contains_required_params(): void {
		$url = IRIXFSL_Invoice::invoice_url( 123 );

		$this->assertStringContainsString( 'irixfsl_invoice=1', $url );
		$this->assertStringContainsString( 'order_id=123', $url );
		$this->assertStringContainsString( 'admin=1', $url );
		$this->assertStringContainsString( 'nonce=', $url );
	}

	public function test_admin_invoice_url_defaults_to_admin_true(): void {
		$url = IRIXFSL_Invoice::invoice_url( 456 );

		$this->assertStringContainsString( 'admin=1', $url );
	}

	public function test_customer_invoice_url_contains_order_key(): void {
		$url = IRIXFSL_Invoice::invoice_url( 789, false, 'wc_order_abc123' );

		$this->assertStringContainsString( 'irixfsl_invoice=1', $url );
		$this->assertStringContainsString( 'order_id=789', $url );
		$this->assertStringContainsString( 'order_key=wc_order_abc123', $url );
		$this->assertStringNotContainsString( 'admin=', $url );
		$this->assertStringNotContainsString( 'nonce=', $url );
	}

	public function test_invoice_url_starts_with_home_url(): void {
		$url = IRIXFSL_Invoice::invoice_url( 100 );

		$this->assertStringStartsWith( 'https://example.com', $url );
	}

	public function test_invoice_url_with_different_order_ids(): void {
		$url1 = IRIXFSL_Invoice::invoice_url( 1 );
		$url2 = IRIXFSL_Invoice::invoice_url( 999 );

		$this->assertStringContainsString( 'order_id=1', $url1 );
		$this->assertStringContainsString( 'order_id=999', $url2 );
	}

	public function test_admin_and_customer_urls_are_different(): void {
		$admin    = IRIXFSL_Invoice::invoice_url( 100, true );
		$customer = IRIXFSL_Invoice::invoice_url( 100, false, 'key123' );

		$this->assertNotEquals( $admin, $customer );
	}
}
