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
		$_POST                 = [];
		$GLOBALS['_irixfsl_test_user_caps'] = [ 'edit_shop_orders' => true, 'manage_woocommerce' => true ];
		$this->resetTrackingSavedFlag();
	}

	protected function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * IRIXFSL_Tracking::$tracking_saved is a private static flag that survives
	 * across tests in the same process (PHPUnit doesn't reload the class) —
	 * reset it via reflection so save_meta() isn't silently
	 * short-circuited by a previous test.
	 */
	private function resetTrackingSavedFlag(): void {
		$ref  = new \ReflectionClass( IRIXFSL_Tracking::class );
		$prop = $ref->getProperty( 'tracking_saved' );
		$prop->setValue( null, false );
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
			'local_delivery_methods' => [
				[ 'name' => 'My Delivery', 'method_id' => 'my_delivery', 'fee' => '' ],
				[ 'name' => 'Local Delivery', 'method_id' => 'local_delivery', 'fee' => '' ],
			],
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
			'local_delivery_methods' => [
				[ 'name' => 'My Delivery', 'method_id' => 'my_delivery', 'fee' => '' ],
			],
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
			'local_delivery_methods' => [
				[ 'name' => 'Custom Local', 'method_id' => 'custom_local', 'fee' => '' ],
			],
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

	// ── maybe_apply_local_delivery_fee() (via save_meta) ────────────

	private function withTrackingNonce(): void {
		$_POST['irixfsl_tracking_nonce'] = wp_create_nonce( 'irixfsl_tracking_save' );
	}

	public function test_applies_configured_fee_for_local_delivery_order(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [
				[ 'name' => 'XYZ Courier', 'method_id' => 'my_delivery', 'fee' => '250' ],
			],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 500 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'my_delivery' ) ] );
		$this->withTrackingNonce();

		IRIXFSL_Tracking::instance()->apply_fee_after_order_save( $order );

		$items = $order->get_items();
		$this->assertCount( 1, $items );
		$this->assertInstanceOf( \WC_Order_Item_Fee::class, $items[0] );
		$this->assertSame( '250', $items[0]->get_amount() );
		$this->assertSame( '250', $items[0]->get_total() );
		$this->assertTrue( $order->was_calculate_totals_called() );
		$this->assertSame( 1, $order->get_save_call_count() );
		$this->assertSame( '1', $order->get_meta( IRIXFSL_Tracking::META_LOCAL_DELIVERY_FEE_APPLIED ) );
	}

	public function test_does_not_apply_fee_once_order_has_shipped(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [
				[ 'name' => 'XYZ Courier', 'method_id' => 'my_delivery', 'fee' => '250' ],
			],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 509 );
		$order->set_status( 'completed' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'my_delivery' ) ] );
		$this->withTrackingNonce();

		IRIXFSL_Tracking::instance()->apply_fee_after_order_save( $order );

		$this->assertSame( [], $order->get_items() );
	}

	public function test_does_not_reapply_fee_when_already_applied(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [
				[ 'name' => 'XYZ Courier', 'method_id' => 'my_delivery', 'fee' => '250' ],
			],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 501 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'my_delivery' ) ] );
		$order->update_meta_data( IRIXFSL_Tracking::META_LOCAL_DELIVERY_FEE_APPLIED, '1' );
		$this->withTrackingNonce();

		IRIXFSL_Tracking::instance()->apply_fee_after_order_save( $order );

		$this->assertSame( [], $order->get_items() );
		$this->assertSame( 0, $order->get_save_call_count() );
	}

	public function test_does_not_apply_fee_when_no_fee_configured(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [
				[ 'name' => 'XYZ Courier', 'method_id' => 'my_delivery', 'fee' => '' ],
			],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 502 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'my_delivery' ) ] );
		$this->withTrackingNonce();

		IRIXFSL_Tracking::instance()->apply_fee_after_order_save( $order );

		$this->assertSame( [], $order->get_items() );
		$this->assertSame( 0, $order->get_save_call_count() );
	}

	public function test_does_not_apply_fee_for_standard_order(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [
				[ 'name' => 'XYZ Courier', 'method_id' => 'my_delivery', 'fee' => '250' ],
			],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 503 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );
		$this->withTrackingNonce();

		IRIXFSL_Tracking::instance()->apply_fee_after_order_save( $order );

		$this->assertSame( [], $order->get_items() );
		$this->assertSame( 0, $order->get_save_call_count() );
	}

	// ── render_meta_box() Carrier dropdown reflects local delivery method ──

	public function test_render_meta_box_preselects_local_delivery_name_in_carrier_dropdown(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [
				[ 'name' => 'XYZ Bike Courier', 'method_id' => 'my_delivery', 'fee' => '250' ],
			],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 700 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'my_delivery' ) ] );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'XYZ Bike Courier', $html );
		// Selected by default since no carrier has been manually saved yet.
		$this->assertMatchesRegularExpression(
			'/value="XYZ Bike Courier"[^>]*selected="selected"/',
			$html
		);
	}

	public function test_render_meta_box_shows_both_local_delivery_and_static_carriers_together(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [
				[ 'name' => 'XYZ Bike Courier', 'method_id' => 'my_delivery', 'fee' => '250' ],
			],
			'carriers' => [
				[ 'name' => 'DHL Sri Lanka', 'url' => 'https://dhl.com/track/{number}' ],
			],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 701 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'my_delivery' ) ] );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();

		// Both the matched local delivery method and the static Shipping
		// Carriers list appear together — the admin picks whichever applies.
		$this->assertStringContainsString( 'XYZ Bike Courier', $html );
		$this->assertStringContainsString( 'DHL Sri Lanka', $html );
		$this->assertStringContainsString( '— Select Carrier —', $html );
	}

	public function test_render_meta_box_preserves_manually_saved_carrier_for_standard_order(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'carriers' => [
				[ 'name' => 'DHL Sri Lanka', 'url' => 'https://dhl.com/track/{number}' ],
			],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 703 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );
		$order->update_meta_data( IRIXFSL_Tracking::META_CARRIER, 'DHL Sri Lanka' );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/value="DHL Sri Lanka"[^>]*selected="selected"/',
			$html
		);
	}

	public function test_render_meta_box_omits_local_delivery_option_for_standard_order(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'local_delivery_methods' => [
				[ 'name' => 'XYZ Bike Courier', 'method_id' => 'my_delivery', 'fee' => '250' ],
			],
		];
		IRIXFSL_Settings::flush_cache();

		$order = new WC_Order( 702 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'XYZ Bike Courier', $html );
	}

	// ── Carrier field is locked outside Processing/Ready to Ship/Shipped ──

	public function test_render_meta_box_carrier_is_editable_select_when_processing(): void {
		$order = new WC_Order( 704 );
		$order->set_status( 'processing' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<select id="irixfsl_carrier"', $html );
	}

	public function test_render_meta_box_carrier_is_editable_select_when_ready_to_ship(): void {
		$order = new WC_Order( 705 );
		$order->set_status( 'ready-to-ship' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<select id="irixfsl_carrier"', $html );
	}

	public function test_render_meta_box_carrier_is_editable_select_when_shipped(): void {
		$order = new WC_Order( 706 );
		$order->set_status( 'shipped' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<select id="irixfsl_carrier"', $html );
	}

	public function test_render_meta_box_carrier_is_readonly_when_completed(): void {
		$order = new WC_Order( 707 );
		$order->set_status( 'completed' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );
		$order->update_meta_data( IRIXFSL_Tracking::META_CARRIER, 'DHL Sri Lanka' );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<select id="irixfsl_carrier"', $html );
		$this->assertStringContainsString( 'DHL Sri Lanka', $html );
		// The saved value must still be preserved for the next form submission.
		$this->assertMatchesRegularExpression(
			'/<input type="hidden" name="irixfsl_carrier" value="DHL Sri Lanka">/',
			$html
		);
	}

	public function test_render_meta_box_carrier_readonly_shows_placeholder_when_never_set(): void {
		$order = new WC_Order( 708 );
		$order->set_status( 'returned' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<select id="irixfsl_carrier"', $html );
		$this->assertStringContainsString( '— none —', $html );
	}

	public function test_readonly_carrier_hidden_input_preserves_value_on_save(): void {
		$order = new WC_Order( 709 );
		$order->set_status( 'refunded' );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );
		$order->update_meta_data( IRIXFSL_Tracking::META_CARRIER, 'FedEx' );

		// Simulate submitting the form exactly as rendered: the hidden input
		// carries the existing value since no <select> was present.
		$this->withTrackingNonce();
		$_POST['irixfsl_carrier'] = 'FedEx';

		IRIXFSL_Tracking::instance()->save_meta( $order );

		$this->assertSame( 'FedEx', $order->get_meta( IRIXFSL_Tracking::META_CARRIER ) );
	}

	public function test_save_meta_ignored_without_edit_capability(): void {
		$GLOBALS['_irixfsl_test_user_caps'] = [];
		$order = new WC_Order( 800 );
		$this->withTrackingNonce();
		$_POST['irixfsl_tracking_number'] = 'ABC123';

		IRIXFSL_Tracking::instance()->save_meta( $order );

		$this->assertSame( '', $order->get_meta( IRIXFSL_Tracking::META_NUMBER ) );
	}

	// ── on_status_changed() → background email ──────────────────────────

	public function test_status_changed_queues_email_when_order_stays_shipped(): void {
		$GLOBALS['_irixfsl_test_async'] = [];
		$order = new WC_Order( 720 );
		$order->set_status( 'shipped' );

		IRIXFSL_Tracking::instance()->on_status_changed( 720, 'processing', 'shipped', $order );

		$this->assertSame( [ [ 'irixfsl_send_tracking_email', [ 720 ] ] ], $GLOBALS['_irixfsl_test_async'] );
	}

	public function test_status_changed_skips_order_reverted_by_tracking_guard(): void {
		$GLOBALS['_irixfsl_test_async'] = [];
		$order = new WC_Order( 721 ); // Guard already reverted shipped -> processing.

		IRIXFSL_Tracking::instance()->on_status_changed( 721, 'processing', 'shipped', $order );

		$this->assertSame( [], $GLOBALS['_irixfsl_test_async'] );
	}

	// ── Status pill ─────────────────────────────────────────────────────

	public function test_status_pill_escapes_and_maps_icon(): void {
		$html = \IRIXFSL_Helpers::status_pill( 'queued', '<b>Queued</b>' );
		$this->assertStringContainsString( 'irixfsl-pill--queued', $html );
		$this->assertStringContainsString( 'irixfsl-dot', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_meta_box_shows_queued_while_email_pending(): void {
		$GLOBALS['_irixfsl_test_async'] = [ [ 'irixfsl_send_tracking_email', [ 730 ] ] ];
		$order = new WC_Order( 730 );
		$order->set_shipping_methods( [ new WC_Order_Item_Shipping( 'flat_rate' ) ] );
		$order->update_meta_data( IRIXFSL_Tracking::META_NUMBER, 'TRK1' );

		ob_start();
		IRIXFSL_Tracking::instance()->render_meta_box( $order );
		$html = ob_get_clean();
		$GLOBALS['_irixfsl_test_async'] = [];

		$this->assertStringContainsString( 'irixfsl-pill--queued', $html );
	}
}
