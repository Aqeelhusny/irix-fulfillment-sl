<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use WC_Order;
use IRIXFSL_Whatsapp;
use IRIXFSL_Settings;

final class WhatsappTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		IRIXFSL_Settings::flush_cache();
		global $_irixfsl_test_options, $_irixfsl_test_logger, $_irixfsl_test_http_response, $_irixfsl_test_http_requests, $_irixfsl_test_user_caps;
		$_irixfsl_test_options       = [];
		$_irixfsl_test_logger        = null;
		$_irixfsl_test_http_response = null;
		$_irixfsl_test_http_requests = [];
		$_irixfsl_test_user_caps     = [ 'manage_woocommerce' => true ];
		$_POST                       = [];
	}

	protected function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	private function instance(): IRIXFSL_Whatsapp {
		return IRIXFSL_Whatsapp::instance();
	}

	private function configureWhatsapp( array $overrides = [] ): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = array_merge( [
			'whatsapp_enabled'              => true,
			'whatsapp_phone_number_id'      => '123456789',
			'whatsapp_access_token'         => 'test-token',
			'whatsapp_template_name'        => 'order_shipped_notification',
			'whatsapp_template_language'    => 'en_US',
			'whatsapp_default_calling_code' => '94',
		], $overrides );
		IRIXFSL_Settings::flush_cache();
	}

	// ── to_whatsapp_number() ─────────────────────────────────────────────

	public function test_to_whatsapp_number_replaces_leading_zero_with_calling_code(): void {
		$this->assertSame( '94771234567', IRIXFSL_Whatsapp::to_whatsapp_number( '0771234567', '94' ) );
	}

	public function test_to_whatsapp_number_keeps_already_international_number(): void {
		$this->assertSame( '94771234567', IRIXFSL_Whatsapp::to_whatsapp_number( '+94771234567', '94' ) );
	}

	public function test_to_whatsapp_number_strips_formatting_characters(): void {
		$this->assertSame( '94771234567', IRIXFSL_Whatsapp::to_whatsapp_number( '077-123-4567', '94' ) );
	}

	public function test_to_whatsapp_number_handles_00_prefix_and_plus_with_trunk_zero(): void {
		$this->assertSame( '94771234567', IRIXFSL_Whatsapp::to_whatsapp_number( '0094771234567', '94' ) );
		$this->assertSame( '94771234567', IRIXFSL_Whatsapp::to_whatsapp_number( '+94 (0) 77 123 4567', '94' ) );
	}

	public function test_send_is_blocked_while_another_send_holds_the_lock(): void {
		global $_irixfsl_test_options, $_irixfsl_test_http_requests;
		$this->configureWhatsapp();
		$_irixfsl_test_options['irixfsl_wa_lock_77'] = time();
		$order = new WC_Order( 77 );
		$order->set_billing_phone( '0771234567' );

		$this->assertFalse( $this->instance()->send_shipped_message( $order ) );
		$this->assertSame( [], $_irixfsl_test_http_requests );
	}

	public function test_to_whatsapp_number_returns_null_for_blank_input(): void {
		$this->assertNull( IRIXFSL_Whatsapp::to_whatsapp_number( '', '94' ) );
		$this->assertNull( IRIXFSL_Whatsapp::to_whatsapp_number( '   ', '94' ) );
	}

	// ── send_shipped_message() ──────────────────────────────────────────

	public function test_send_shipped_message_fails_when_not_configured(): void {
		$this->configureWhatsapp( [ 'whatsapp_access_token' => '' ] );

		$order = new WC_Order( 900 );
		$order->set_billing_phone( '0771234567' );

		$result = $this->instance()->send_shipped_message( $order );

		$this->assertFalse( $result );
		global $_irixfsl_test_http_requests;
		$this->assertEmpty( $_irixfsl_test_http_requests );
	}

	public function test_send_shipped_message_fails_without_billing_phone(): void {
		$this->configureWhatsapp();

		$order = new WC_Order( 901 );
		$order->set_billing_phone( '' );

		$result = $this->instance()->send_shipped_message( $order );

		$this->assertFalse( $result );
		global $_irixfsl_test_http_requests;
		$this->assertEmpty( $_irixfsl_test_http_requests );
	}

	public function test_send_shipped_message_succeeds_and_sets_meta(): void {
		$this->configureWhatsapp();

		$order = new WC_Order( 902 );
		$order->set_billing_phone( '0771234567' );

		global $_irixfsl_test_http_response;
		$_irixfsl_test_http_response = [ 'response' => [ 'code' => 200 ], 'body' => '{"messages":[{"id":"wamid.abc"}]}' ];

		$result = $this->instance()->send_shipped_message( $order );

		$this->assertTrue( $result );
		$this->assertSame( '1', $order->get_meta( IRIXFSL_Whatsapp::META_SENT ) );

		global $_irixfsl_test_http_requests;
		$this->assertCount( 1, $_irixfsl_test_http_requests );
		$request = $_irixfsl_test_http_requests[0];
		$this->assertStringContainsString( '123456789', $request['url'] );
		$this->assertStringContainsString( 'Bearer test-token', $request['args']['headers']['Authorization'] );

		$body = json_decode( $request['args']['body'], true );
		$this->assertSame( '94771234567', $body['to'] );
		$this->assertSame( 'order_shipped_notification', $body['template']['name'] );
		$this->assertSame( 'en_US', $body['template']['language']['code'] );
		$this->assertCount( 4, $body['template']['components'][0]['parameters'] );
		$this->assertSame( (string) $order->get_order_number(), $body['template']['components'][0]['parameters'][1]['text'] );
	}

	public function test_send_shipped_message_fails_on_network_error(): void {
		$this->configureWhatsapp();

		$order = new WC_Order( 903 );
		$order->set_billing_phone( '0771234567' );

		global $_irixfsl_test_http_response;
		$_irixfsl_test_http_response = new \WP_Error( 'http_request_failed', 'Connection timed out' );

		$result = $this->instance()->send_shipped_message( $order );

		$this->assertFalse( $result );
		$this->assertSame( '', $order->get_meta( IRIXFSL_Whatsapp::META_SENT ) );
	}

	public function test_send_shipped_message_fails_on_non_2xx_response(): void {
		$this->configureWhatsapp();

		$order = new WC_Order( 904 );
		$order->set_billing_phone( '0771234567' );

		global $_irixfsl_test_http_response;
		$_irixfsl_test_http_response = [ 'response' => [ 'code' => 401 ], 'body' => '{"error":"Invalid token"}' ];

		$result = $this->instance()->send_shipped_message( $order );

		$this->assertFalse( $result );
		$this->assertSame( '', $order->get_meta( IRIXFSL_Whatsapp::META_SENT ) );
	}

	// ── maybe_send_shipped_message() ────────────────────────────────────

	public function test_maybe_send_skips_when_disabled(): void {
		$this->configureWhatsapp( [ 'whatsapp_enabled' => false ] );

		$order = new WC_Order( 905 );
		$order->set_billing_phone( '0771234567' );
		global $_irixfsl_test_orders;
		$_irixfsl_test_orders = [ 905 => $order ];

		$this->instance()->maybe_send_shipped_message( 905 );

		global $_irixfsl_test_http_requests;
		$this->assertEmpty( $_irixfsl_test_http_requests );
	}

	public function test_maybe_send_skips_when_already_sent(): void {
		$this->configureWhatsapp();

		$order = new WC_Order( 906 );
		$order->set_billing_phone( '0771234567' );
		$order->update_meta_data( IRIXFSL_Whatsapp::META_SENT, '1' );
		global $_irixfsl_test_orders;
		$_irixfsl_test_orders = [ 906 => $order ];

		$this->instance()->maybe_send_shipped_message( 906 );

		global $_irixfsl_test_http_requests;
		$this->assertEmpty( $_irixfsl_test_http_requests );
	}

	public function test_maybe_send_sends_when_eligible(): void {
		$this->configureWhatsapp();

		$order = new WC_Order( 907 );
		$order->set_billing_phone( '0771234567' );
		global $_irixfsl_test_orders;
		$_irixfsl_test_orders = [ 907 => $order ];

		global $_irixfsl_test_http_response;
		$_irixfsl_test_http_response = [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];

		$this->instance()->maybe_send_shipped_message( 907 );

		$this->assertSame( '1', $order->get_meta( IRIXFSL_Whatsapp::META_SENT ) );
	}

	public function test_status_changed_skips_order_reverted_by_tracking_guard(): void {
		$this->configureWhatsapp();
		$order = new WC_Order( 908 );
		$order->set_billing_phone( '0771234567' );
		$order->set_status( 'processing' ); // Guard already reverted shipped -> processing.
		$GLOBALS['_irixfsl_test_async'] = [];

		$this->instance()->on_status_changed( 908, 'processing', 'shipped', $order );

		$this->assertSame( [], $GLOBALS['_irixfsl_test_async'] );
	}

	public function test_status_changed_queues_send_when_order_stays_shipped(): void {
		$order = new WC_Order( 909 );
		$order->set_status( 'shipped' );
		$GLOBALS['_irixfsl_test_async'] = [];

		$this->instance()->on_status_changed( 909, 'processing', 'shipped', $order );

		$this->assertSame( [ [ 'irixfsl_send_whatsapp', [ 909 ] ] ], $GLOBALS['_irixfsl_test_async'] );
	}

	public function test_send_uses_current_graph_api_version(): void {
		$this->configureWhatsapp();
		$order = new WC_Order( 910 );
		$order->set_billing_phone( '0771234567' );
		global $_irixfsl_test_http_response, $_irixfsl_test_http_requests;
		$_irixfsl_test_http_response = [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];

		$this->instance()->send_shipped_message( $order );

		$this->assertStringContainsString( 'graph.facebook.com/v25.0/', $_irixfsl_test_http_requests[0]['url'] ?? '' );
	}

	// ── ajax_resend() ────────────────────────────────────────────────────

	public function test_ajax_resend_sends_message_for_authorized_user(): void {
		$this->configureWhatsapp();

		$order = new WC_Order( 908 );
		$order->set_billing_phone( '0771234567' );
		global $_irixfsl_test_orders;
		$_irixfsl_test_orders = [ 908 => $order ];

		global $_irixfsl_test_http_response;
		$_irixfsl_test_http_response = [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];

		$_POST['order_id'] = 908;

		$this->instance()->ajax_resend();

		$this->assertSame( '1', $order->get_meta( IRIXFSL_Whatsapp::META_SENT ) );
	}

	public function test_ajax_resend_rejects_unauthorized_user(): void {
		global $_irixfsl_test_user_caps;
		$_irixfsl_test_user_caps = [];
		$this->configureWhatsapp();

		$order = new WC_Order( 909 );
		$order->set_billing_phone( '0771234567' );
		global $_irixfsl_test_orders;
		$_irixfsl_test_orders = [ 909 => $order ];

		$_POST['order_id'] = 909;

		$this->instance()->ajax_resend();

		global $_irixfsl_test_http_requests;
		$this->assertEmpty( $_irixfsl_test_http_requests );
	}
}
