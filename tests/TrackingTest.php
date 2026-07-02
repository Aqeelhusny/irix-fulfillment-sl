<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use WC_Order;
use WC_Order_Item_Shipping;
use IRIXFSL_Tracking;
use IRIXFSL_Settings;

final class TrackingTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset settings cache between tests.
		IRIXFSL_Settings::flush_cache();
		global $_irixfsl_test_options, $_irixfsl_test_logger;
		$_irixfsl_test_options = [];
		$_irixfsl_test_logger  = null;
	}

	// ── get_fulfillment_type() ──────────────────────────────────────────

	public function test_standard_order_returns_standard(): void {
		$order = new WC_Order( 100 );
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'flat_rate' ),
		] );

		$this->assertSame( 'standard', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	public function test_order_with_no_shipping_methods_returns_standard(): void {
		$order = new WC_Order( 101 );
		$order->set_shipping_methods( [] );

		$this->assertSame( 'standard', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	public function test_local_pickup_returns_pickup(): void {
		$order = new WC_Order( 102 );
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'local_pickup' ),
		] );

		$this->assertSame( 'pickup', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	public function test_local_pickup_plus_returns_pickup(): void {
		$order = new WC_Order( 103 );
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'local_pickup_plus' ),
		] );

		$this->assertSame( 'pickup', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	public function test_pickup_location_returns_pickup(): void {
		$order = new WC_Order( 104 );
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'pickup_location' ),
		] );

		$this->assertSame( 'pickup', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	public function test_pickup_method_returns_pickup(): void {
		$order = new WC_Order( 105 );
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'pickup' ),
		] );

		$this->assertSame( 'pickup', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	public function test_configured_local_delivery_returns_local_delivery(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [ 'my_delivery', 'local_delivery' ],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 106 );
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'my_delivery' ),
		] );

		$this->assertSame( 'local_delivery', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	public function test_pickup_takes_priority_over_local_delivery(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [ 'my_delivery' ],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 107 );
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'local_pickup' ),
			new WC_Order_Item_Shipping( 'my_delivery' ),
		] );

		// Pickup should be detected first.
		$this->assertSame( 'pickup', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	public function test_unknown_method_returns_standard(): void {
		$order = new WC_Order( 108 );
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'dhl_express' ),
		] );

		$this->assertSame( 'standard', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	// ── get_tracking() ──────────────────────────────────────────────────

	public function test_get_tracking_returns_meta_values(): void {
		$order = new WC_Order( 200 );
		$order->update_meta_data( '_irixfsl_carrier', 'DHL Sri Lanka' );
		$order->update_meta_data( '_irixfsl_tracking_number', 'DHL123456' );
		$order->update_meta_data( '_irixfsl_tracking_url', 'https://dhl.com/track/DHL123456' );

		$tracking = IRIXFSL_Tracking::get_tracking( $order );

		$this->assertSame( 'DHL Sri Lanka', $tracking['carrier'] );
		$this->assertSame( 'DHL123456', $tracking['number'] );
		$this->assertSame( 'https://dhl.com/track/DHL123456', $tracking['url'] );
	}

	public function test_get_tracking_returns_empty_strings_when_no_meta(): void {
		$order    = new WC_Order( 201 );
		$tracking = IRIXFSL_Tracking::get_tracking( $order );

		$this->assertSame( '', $tracking['carrier'] );
		$this->assertSame( '', $tracking['number'] );
		$this->assertSame( '', $tracking['url'] );
	}

	public function test_get_tracking_returns_array_with_expected_keys(): void {
		$order    = new WC_Order( 202 );
		$tracking = IRIXFSL_Tracking::get_tracking( $order );

		$this->assertArrayHasKey( 'carrier', $tracking );
		$this->assertArrayHasKey( 'number', $tracking );
		$this->assertArrayHasKey( 'url', $tracking );
		$this->assertCount( 3, $tracking );
	}

	// ── Meta constants ──────────────────────────────────────────────────

	public function test_meta_key_constants_are_defined(): void {
		$this->assertSame( '_irixfsl_carrier', IRIXFSL_Tracking::META_CARRIER );
		$this->assertSame( '_irixfsl_tracking_number', IRIXFSL_Tracking::META_NUMBER );
		$this->assertSame( '_irixfsl_tracking_url', IRIXFSL_Tracking::META_URL );
		$this->assertSame( '_irixfsl_tracking_email_sent', IRIXFSL_Tracking::META_SENT );
	}

	// ── Multiple shipping methods ────────────────────────────────────────

	public function test_first_matching_method_wins(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [ 'custom_local' ],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 109 );
		// First is local delivery, second is standard — local delivery should win.
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'custom_local' ),
			new WC_Order_Item_Shipping( 'flat_rate' ),
		] );

		$this->assertSame( 'local_delivery', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}

	public function test_empty_local_delivery_settings_yields_standard(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 110 );
		$order->set_shipping_methods( [
			new WC_Order_Item_Shipping( 'flat_rate' ),
		] );

		$this->assertSame( 'standard', IRIXFSL_Tracking::get_fulfillment_type( $order ) );
	}
}
