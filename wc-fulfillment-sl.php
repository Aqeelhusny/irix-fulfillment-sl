<?php
/**
 * Plugin Name: WC Fulfillment SL
 * Plugin URI:  https://irixsolutions.net
 * Description: WooCommerce invoicing, packing slips, and order tracking with Sri Lanka carrier support.
 * Version:     1.0.0
 * Author:      Aqeel Husny
 * Text Domain: wc-fulfillment-sl
 * License:     GPLv2 or later
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      9.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WCFSL_VERSION',    '1.0.0' );
define( 'WCFSL_DIR',        plugin_dir_path( __FILE__ ) );
define( 'WCFSL_URL',        plugin_dir_url( __FILE__ ) );
define( 'WCFSL_PLUGIN_FILE', __FILE__ );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

final class WC_Fulfillment_SL {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	public function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'notice_wc_missing' ] );
			return;
		}

		load_plugin_textdomain( 'wc-fulfillment-sl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		require_once WCFSL_DIR . 'includes/class-settings.php';
		require_once WCFSL_DIR . 'includes/class-order-statuses.php';
		require_once WCFSL_DIR . 'includes/class-tracking.php';
		require_once WCFSL_DIR . 'includes/class-invoice.php';
		require_once WCFSL_DIR . 'includes/class-packing-slip.php';
		require_once WCFSL_DIR . 'includes/class-waybill.php';
		require_once WCFSL_DIR . 'includes/class-customer-portal.php';
		require_once WCFSL_DIR . 'includes/class-admin.php';

		WCFSL_Settings::instance();
		WCFSL_Order_Statuses::instance();
		WCFSL_Tracking::instance();
		WCFSL_Invoice::instance();
		WCFSL_Packing_Slip::instance();
		WCFSL_Waybill::instance();
		WCFSL_Customer_Portal::instance();
		WCFSL_Admin::instance();

		// Load the email class only after WC's mailer initialises WC_Email.
		add_filter( 'woocommerce_email_classes', [ $this, 'register_email_class' ] );
	}

	public function register_email_class( array $emails ): array {
		require_once WCFSL_DIR . 'includes/class-email-tracking.php';
		$emails['WCFSL_Email_Tracking'] = new WCFSL_Email_Tracking();
		return $emails;
	}

	public function notice_wc_missing(): void {
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'WC Fulfillment SL requires WooCommerce to be active.', 'wc-fulfillment-sl' )
			. '</p></div>';
	}
}

function wcfsl(): WC_Fulfillment_SL {
	return WC_Fulfillment_SL::instance();
}
wcfsl();

register_activation_hook( __FILE__, 'wcfsl_activate' );
function wcfsl_activate(): void {
	flush_rewrite_rules();
}

register_uninstall_hook( __FILE__, 'wcfsl_uninstall' );
function wcfsl_uninstall(): void {
	delete_option( 'wcfsl_settings' );
	delete_option( 'wcfsl_carrier_urls_migrated' );
}
