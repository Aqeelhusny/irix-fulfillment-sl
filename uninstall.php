<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Order meta (_irixfsl_*) is intentionally kept: it is the historical shipping record.
delete_option( 'irixfsl_settings' );
delete_option( 'irixfsl_carrier_urls_migrated' );
delete_option( 'irixfsl_local_delivery_methods_migrated' );

global $wpdb;
$wpdb->query( $wpdb->prepare(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
	$wpdb->esc_like( '_transient_irixfsl_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_irixfsl_' ) . '%',
	$wpdb->esc_like( 'irixfsl_wa_lock_' ) . '%'
) );
