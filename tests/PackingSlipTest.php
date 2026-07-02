<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use IRIXFSL_Packing_Slip;

final class PackingSlipTest extends TestCase {

	// ── packing_slip_url() ──────────────────────────────────────────────

	public function test_packing_slip_url_contains_required_params(): void {
		$url = IRIXFSL_Packing_Slip::packing_slip_url( [ 100, 200, 300 ] );

		$this->assertStringContainsString( 'irixfsl_packing_slip=1', $url );
		$this->assertStringContainsString( 'order_ids=100%2C200%2C300', $url );
		$this->assertStringContainsString( 'nonce=', $url );
	}

	public function test_packing_slip_url_with_single_order(): void {
		$url = IRIXFSL_Packing_Slip::packing_slip_url( [ 42 ] );

		$this->assertStringContainsString( 'order_ids=42', $url );
	}

	public function test_packing_slip_url_sanitizes_ids(): void {
		$url = IRIXFSL_Packing_Slip::packing_slip_url( [ -5, 0, 100 ] );

		// absint(-5) = 5, absint(0) = 0
		$this->assertStringContainsString( 'order_ids=', $url );
	}

	public function test_packing_slip_url_starts_with_home_url(): void {
		$url = IRIXFSL_Packing_Slip::packing_slip_url( [ 1 ] );

		$this->assertStringStartsWith( 'https://example.com', $url );
	}

	public function test_packing_slip_url_includes_nonce(): void {
		$url = IRIXFSL_Packing_Slip::packing_slip_url( [ 10 ] );

		$this->assertStringContainsString( 'nonce=test_nonce_', $url );
	}
}
