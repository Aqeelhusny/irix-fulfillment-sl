<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use IRIXFSL_Waybill;

final class WaybillTest extends TestCase {

	// ── waybill_url() ───────────────────────────────────────────────────

	public function test_waybill_url_contains_required_params(): void {
		$url = IRIXFSL_Waybill::waybill_url( 500 );

		$this->assertStringContainsString( 'irixfsl_waybill=1', $url );
		$this->assertStringContainsString( 'order_id=500', $url );
		$this->assertStringContainsString( 'nonce=', $url );
	}

	public function test_waybill_url_nonce_is_order_specific(): void {
		$url1 = IRIXFSL_Waybill::waybill_url( 100 );
		$url2 = IRIXFSL_Waybill::waybill_url( 200 );

		// Nonces should differ because they include the order ID.
		$this->assertNotEquals( $url1, $url2 );
	}

	public function test_waybill_url_starts_with_home_url(): void {
		$url = IRIXFSL_Waybill::waybill_url( 1 );

		$this->assertStringStartsWith( 'https://example.com', $url );
	}

	public function test_waybill_url_with_zero_order_id(): void {
		$url = IRIXFSL_Waybill::waybill_url( 0 );

		$this->assertStringContainsString( 'order_id=0', $url );
	}
}
