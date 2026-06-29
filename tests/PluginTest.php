<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase {

	// ── Constants ────────────────────────────────────────────────────────

	public function test_version_constant_defined(): void {
		$this->assertTrue( defined( 'IRIXFSL_VERSION' ) );
		$this->assertSame( '1.0.0', IRIXFSL_VERSION );
	}

	public function test_dir_constant_defined(): void {
		$this->assertTrue( defined( 'IRIXFSL_DIR' ) );
		$this->assertStringEndsWith( '/', IRIXFSL_DIR );
	}

	public function test_url_constant_defined(): void {
		$this->assertTrue( defined( 'IRIXFSL_URL' ) );
		$this->assertStringStartsWith( 'https://', IRIXFSL_URL );
	}

	public function test_plugin_file_constant_defined(): void {
		$this->assertTrue( defined( 'IRIXFSL_PLUGIN_FILE' ) );
	}

	// ── Class existence ─────────────────────────────────────────────────

	public function test_settings_class_exists(): void {
		$this->assertTrue( class_exists( 'IRIXFSL_Settings' ) );
	}

	public function test_tracking_class_exists(): void {
		$this->assertTrue( class_exists( 'IRIXFSL_Tracking' ) );
	}

	public function test_order_statuses_class_exists(): void {
		$this->assertTrue( class_exists( 'IRIXFSL_Order_Statuses' ) );
	}

	public function test_invoice_class_exists(): void {
		$this->assertTrue( class_exists( 'IRIXFSL_Invoice' ) );
	}

	public function test_packing_slip_class_exists(): void {
		$this->assertTrue( class_exists( 'IRIXFSL_Packing_Slip' ) );
	}

	public function test_waybill_class_exists(): void {
		$this->assertTrue( class_exists( 'IRIXFSL_Waybill' ) );
	}

	public function test_customer_portal_class_exists(): void {
		$this->assertTrue( class_exists( 'IRIXFSL_Customer_Portal' ) );
	}

	public function test_admin_class_exists(): void {
		$this->assertTrue( class_exists( 'IRIXFSL_Admin' ) );
	}

	public function test_email_tracking_class_exists(): void {
		$this->assertTrue( class_exists( 'IRIXFSL_Email_Tracking' ) );
	}

	// ── Singleton pattern ───────────────────────────────────────────────

	public function test_settings_singleton_returns_same_instance(): void {
		$a = \IRIXFSL_Settings::instance();
		$b = \IRIXFSL_Settings::instance();
		$this->assertSame( $a, $b );
	}

	public function test_tracking_singleton_returns_same_instance(): void {
		$a = \IRIXFSL_Tracking::instance();
		$b = \IRIXFSL_Tracking::instance();
		$this->assertSame( $a, $b );
	}

	public function test_order_statuses_singleton_returns_same_instance(): void {
		$a = \IRIXFSL_Order_Statuses::instance();
		$b = \IRIXFSL_Order_Statuses::instance();
		$this->assertSame( $a, $b );
	}

	public function test_invoice_singleton_returns_same_instance(): void {
		$a = \IRIXFSL_Invoice::instance();
		$b = \IRIXFSL_Invoice::instance();
		$this->assertSame( $a, $b );
	}

	public function test_packing_slip_singleton_returns_same_instance(): void {
		$a = \IRIXFSL_Packing_Slip::instance();
		$b = \IRIXFSL_Packing_Slip::instance();
		$this->assertSame( $a, $b );
	}

	public function test_waybill_singleton_returns_same_instance(): void {
		$a = \IRIXFSL_Waybill::instance();
		$b = \IRIXFSL_Waybill::instance();
		$this->assertSame( $a, $b );
	}

	public function test_customer_portal_singleton_returns_same_instance(): void {
		$a = \IRIXFSL_Customer_Portal::instance();
		$b = \IRIXFSL_Customer_Portal::instance();
		$this->assertSame( $a, $b );
	}

	public function test_admin_singleton_returns_same_instance(): void {
		$a = \IRIXFSL_Admin::instance();
		$b = \IRIXFSL_Admin::instance();
		$this->assertSame( $a, $b );
	}
}
