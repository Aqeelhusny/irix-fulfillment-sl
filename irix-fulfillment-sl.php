<?php
/**
 * Plugin Name: IRIX Fulfillment SL
 * Plugin URI:  https://irixsolutions.net
 * Description: WooCommerce invoicing, packing slips, and order tracking with Sri Lanka carrier support.
 * Version:     1.0.0
 * Author:      Aqeel Husny
 * Text Domain: irix-fulfillment-sl
 * License:     GPLv2 or later
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      9.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'IRIXFSL_VERSION',    '1.0.0' );
define( 'IRIXFSL_DIR',        plugin_dir_path( __FILE__ ) );
define( 'IRIXFSL_URL',        plugin_dir_url( __FILE__ ) );
define( 'IRIXFSL_PLUGIN_FILE', __FILE__ );

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

		load_plugin_textdomain( 'irix-fulfillment-sl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		require_once IRIXFSL_DIR . 'includes/class-settings.php';
		require_once IRIXFSL_DIR . 'includes/class-order-statuses.php';
		require_once IRIXFSL_DIR . 'includes/class-tracking.php';
		require_once IRIXFSL_DIR . 'includes/class-invoice.php';
		require_once IRIXFSL_DIR . 'includes/class-packing-slip.php';
		require_once IRIXFSL_DIR . 'includes/class-waybill.php';
		require_once IRIXFSL_DIR . 'includes/class-customer-portal.php';
		require_once IRIXFSL_DIR . 'includes/class-admin.php';

		IRIXFSL_Settings::instance();
		IRIXFSL_Order_Statuses::instance();
		IRIXFSL_Tracking::instance();
		IRIXFSL_Invoice::instance();
		IRIXFSL_Packing_Slip::instance();
		IRIXFSL_Waybill::instance();
		IRIXFSL_Customer_Portal::instance();
		IRIXFSL_Admin::instance();

		// Load the email class only after WC's mailer initialises WC_Email.
		add_filter( 'woocommerce_email_classes', [ $this, 'register_email_class' ] );
	}

	public function register_email_class( array $emails ): array {
		require_once IRIXFSL_DIR . 'includes/class-email-tracking.php';
		$emails['IRIXFSL_Email_Tracking'] = new IRIXFSL_Email_Tracking();
		return $emails;
	}

	public function notice_wc_missing(): void {
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'IRIX Fulfillment SL requires WooCommerce to be active.', 'irix-fulfillment-sl' )
			. '</p></div>';
	}
}

function IRIXFSL(): WC_Fulfillment_SL {
	return WC_Fulfillment_SL::instance();
}
IRIXFSL();

register_activation_hook( __FILE__, 'irixfsl_activate' );
function irixfsl_activate(): void {
	flush_rewrite_rules();
}

register_uninstall_hook( __FILE__, 'irixfsl_uninstall' );
function irixfsl_uninstall(): void {
	delete_option( 'irixfsl_settings' );
	delete_option( 'irixfsl_carrier_urls_migrated' );
}
