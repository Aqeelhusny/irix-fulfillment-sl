<?php

namespace IRIXFSL\Tests;

use PHPUnit\Framework\TestCase;
use IRIXFSL_Settings;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		IRIXFSL_Settings::flush_cache();
		global $_irixfsl_test_options;
		$_irixfsl_test_options = [];
	}

	// ── defaults() ──────────────────────────────────────────────────────

	public function test_defaults_returns_expected_keys(): void {
		$defaults = IRIXFSL_Settings::defaults();

		$this->assertArrayHasKey( 'company_name', $defaults );
		$this->assertArrayHasKey( 'company_address', $defaults );
		$this->assertArrayHasKey( 'company_phone', $defaults );
		$this->assertArrayHasKey( 'company_email', $defaults );
		$this->assertArrayHasKey( 'company_logo_id', $defaults );
		$this->assertArrayHasKey( 'invoice_footer', $defaults );
		$this->assertArrayHasKey( 'carriers', $defaults );
		$this->assertArrayHasKey( 'local_delivery_methods', $defaults );
		$this->assertArrayHasKey( 'waybill_scan_url', $defaults );
	}

	public function test_defaults_company_name_from_bloginfo(): void {
		$defaults = IRIXFSL_Settings::defaults();
		$this->assertSame( 'Test Store', $defaults['company_name'] );
	}

	public function test_defaults_company_email_from_bloginfo(): void {
		$defaults = IRIXFSL_Settings::defaults();
		$this->assertSame( 'admin@example.com', $defaults['company_email'] );
	}

	public function test_defaults_includes_three_carriers(): void {
		$defaults = IRIXFSL_Settings::defaults();
		$this->assertCount( 3, $defaults['carriers'] );
	}

	public function test_default_carriers_have_number_placeholder(): void {
		$defaults = IRIXFSL_Settings::defaults();
		foreach ( $defaults['carriers'] as $carrier ) {
			$this->assertArrayHasKey( 'name', $carrier );
			$this->assertArrayHasKey( 'url', $carrier );
			$this->assertStringContainsString( '{number}', $carrier['url'] );
		}
	}

	public function test_defaults_local_delivery_methods_is_empty_array(): void {
		$defaults = IRIXFSL_Settings::defaults();
		$this->assertSame( [], $defaults['local_delivery_methods'] );
	}

	public function test_defaults_waybill_scan_url_is_empty(): void {
		$defaults = IRIXFSL_Settings::defaults();
		$this->assertSame( '', $defaults['waybill_scan_url'] );
	}

	public function test_defaults_logo_id_is_zero(): void {
		$defaults = IRIXFSL_Settings::defaults();
		$this->assertSame( 0, $defaults['company_logo_id'] );
	}

	// ── get() ───────────────────────────────────────────────────────────

	public function test_get_returns_defaults_when_no_options_saved(): void {
		$settings = IRIXFSL_Settings::get();
		$this->assertSame( 'Test Store', $settings['company_name'] );
	}

	public function test_get_merges_saved_options_over_defaults(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'company_name' => 'My Custom Store',
		];
		IRIXFSL_Settings::flush_cache();

		$settings = IRIXFSL_Settings::get();
		$this->assertSame( 'My Custom Store', $settings['company_name'] );
	}

	public function test_get_with_key_returns_specific_value(): void {
		$value = IRIXFSL_Settings::get( 'company_name' );
		$this->assertSame( 'Test Store', $value );
	}

	public function test_get_with_nonexistent_key_returns_null(): void {
		$value = IRIXFSL_Settings::get( 'nonexistent_key' );
		$this->assertNull( $value );
	}

	public function test_get_caches_across_calls(): void {
		// First call loads the cache.
		$first = IRIXFSL_Settings::get( 'company_name' );

		// Change underlying option — cache should NOT reflect it.
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'company_name' => 'Changed After Cache',
		];

		$second = IRIXFSL_Settings::get( 'company_name' );
		$this->assertSame( $first, $second );
	}

	public function test_flush_cache_forces_reload(): void {
		IRIXFSL_Settings::get( 'company_name' );

		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'company_name' => 'After Flush',
		];
		IRIXFSL_Settings::flush_cache();

		$this->assertSame( 'After Flush', IRIXFSL_Settings::get( 'company_name' ) );
	}

	// ── OPTION_KEY constant ──────────────────────────────────────────────

	public function test_option_key_constant(): void {
		$this->assertSame( 'irixfsl_settings', IRIXFSL_Settings::OPTION_KEY );
	}

	// ── Carrier URL migration (maybe_fix_carrier_urls) ────────────────

	public function test_migration_skipped_when_flag_already_set(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options['irixfsl_carrier_urls_migrated'] = '1';
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'carriers' => [
				[ 'name' => 'Test', 'url' => 'https://example.com/track/number' ],
			],
		];

		$instance = IRIXFSL_Settings::instance();
		$instance->maybe_fix_carrier_urls();

		// URL should remain unchanged because migration was skipped.
		$this->assertSame(
			'https://example.com/track/number',
			$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ]['carriers'][0]['url']
		);
	}

	public function test_migration_sets_flag_when_no_carriers(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [];

		$instance = IRIXFSL_Settings::instance();
		$instance->maybe_fix_carrier_urls();

		$this->assertSame( '1', $_irixfsl_test_options['irixfsl_carrier_urls_migrated'] );
	}

	public function test_migration_fixes_bare_number_word(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'carriers' => [
				[ 'name' => 'SL Post', 'url' => 'https://slpost.lk/track/number' ],
			],
		];

		IRIXFSL_Settings::flush_cache();
		$instance = IRIXFSL_Settings::instance();
		$instance->maybe_fix_carrier_urls();

		$this->assertSame(
			'https://slpost.lk/track/{number}',
			$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ]['carriers'][0]['url']
		);
		$this->assertSame( '1', $_irixfsl_test_options['irixfsl_carrier_urls_migrated'] );
	}

	public function test_migration_resets_corrupted_urls(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'company_name' => 'Keep This',
			'carriers' => [
				[ 'name' => 'Corrupt', 'url' => 'https://example.com/track{number}s' ],
			],
		];

		IRIXFSL_Settings::flush_cache();
		$instance = IRIXFSL_Settings::instance();
		$instance->maybe_fix_carrier_urls();

		// Carriers should be reset to defaults but company_name preserved.
		$settings = $_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ];
		$this->assertSame( 'Keep This', $settings['company_name'] );
		$this->assertCount( 3, $settings['carriers'] ); // defaults have 3 carriers
	}

	public function test_migration_does_not_change_already_correct_urls(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'carriers' => [
				[ 'name' => 'DHL', 'url' => 'https://dhl.com/track/{number}' ],
			],
		];

		IRIXFSL_Settings::flush_cache();
		$instance = IRIXFSL_Settings::instance();
		$instance->maybe_fix_carrier_urls();

		$this->assertSame(
			'https://dhl.com/track/{number}',
			$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ]['carriers'][0]['url']
		);
	}

	public function test_migration_handles_empty_carrier_url(): void {
		global $_irixfsl_test_options;
		$_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ] = [
			'carriers' => [
				[ 'name' => 'NoURL', 'url' => '' ],
			],
		];

		IRIXFSL_Settings::flush_cache();
		$instance = IRIXFSL_Settings::instance();
		$instance->maybe_fix_carrier_urls();

		$this->assertSame( '', $_irixfsl_test_options[ IRIXFSL_Settings::OPTION_KEY ]['carriers'][0]['url'] );
		$this->assertSame( '1', $_irixfsl_test_options['irixfsl_carrier_urls_migrated'] );
	}
}
