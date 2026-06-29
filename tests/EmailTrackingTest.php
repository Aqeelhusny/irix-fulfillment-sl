<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use WC_Order;
use WC_DateTime;
use IRIXFSL_Email_Tracking;
use IRIXFSL_Settings;

final class EmailTrackingTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		IRIXFSL_Settings::flush_cache();
		global $_irixfsl_test_options, $_irixfsl_test_logger;
		$_irixfsl_test_options = [];
		$_irixfsl_test_logger  = null;
	}

	private function make_email(): IRIXFSL_Email_Tracking {
		return new IRIXFSL_Email_Tracking();
	}

	// ── Constructor defaults ────────────────────────────────────────────

	public function test_email_id(): void {
		$email = $this->make_email();
		$this->assertSame( 'irixfsl_tracking_notification', $email->id );
	}

	public function test_email_is_customer_email(): void {
		$email = $this->make_email();
		$this->assertTrue( $email->customer_email );
	}

	public function test_template_html_path(): void {
		$email = $this->make_email();
		$this->assertSame( 'emails/irixfsl-tracking-notification.php', $email->template_html );
	}

	public function test_template_base_is_plugin_templates(): void {
		$email = $this->make_email();
		$this->assertStringContainsString( 'templates/', $email->template_base );
	}

	public function test_placeholders_include_expected_keys(): void {
		$email = $this->make_email();
		$this->assertArrayHasKey( '{site_title}', $email->placeholders );
		$this->assertArrayHasKey( '{order_number}', $email->placeholders );
		$this->assertArrayHasKey( '{order_date}', $email->placeholders );
	}

	// ── get_content_plain() ─────────────────────────────────────────────

	public function test_get_content_plain_returns_empty_without_order(): void {
		$email = $this->make_email();
		$this->assertSame( '', $email->get_content_plain() );
	}

	// ── trigger() ───────────────────────────────────────────────────────

	public function test_trigger_logs_error_for_invalid_order(): void {
		global $_irixfsl_test_orders;
		$_irixfsl_test_orders = [];

		$email = $this->make_email();
		$email->trigger( 99999 );

		$logger = wc_get_logger();
		$this->assertNotEmpty( $logger->logs );
		$this->assertSame( 'error', $logger->logs[0]['level'] );
		$this->assertStringContainsString( '99999', $logger->logs[0]['message'] );
	}

	public function test_trigger_sets_recipient_from_order(): void {
		$order = new WC_Order( 700 );
		$order->set_billing_email( 'test@customer.com' );

		$email = $this->make_email();
		$email->trigger( 700, $order );

		$this->assertSame( 'test@customer.com', $email->get_recipient() );
	}

	public function test_trigger_adjusts_heading_for_local_delivery(): void {
		$order = new WC_Order( 701 );

		$email = $this->make_email();
		$email->trigger( 701, $order, 'local_delivery' );

		$this->assertStringContainsString( 'out for delivery', $email->heading );
	}

	public function test_trigger_standard_heading(): void {
		$order = new WC_Order( 702 );

		$email = $this->make_email();
		$email->trigger( 702, $order, 'standard' );

		$this->assertStringContainsString( 'shipped', $email->heading );
	}

	// ── get_content_html() ──────────────────────────────────────────────

	public function test_get_content_html_returns_empty_without_order(): void {
		$email = $this->make_email();
		$result = $email->get_content_html();

		$this->assertSame( '', $result );
	}
}
