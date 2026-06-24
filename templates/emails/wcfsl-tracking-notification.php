<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var WC_Order $order */
/** @var array $tracking */
/** @var WC_Email $email */
/** @var string $fulfillment_type  'standard' | 'local_delivery' */

$fulfillment_type = $fulfillment_type ?? 'standard';

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php printf( esc_html__( 'Hi %s,', 'wc-fulfillment-sl' ), esc_html( $order->get_billing_first_name() ) ); ?></p>

<?php if ( $fulfillment_type === 'local_delivery' ) : ?>
	<p><?php esc_html_e( 'Great news! Your order is out for delivery and our team will deliver it to you shortly.', 'wc-fulfillment-sl' ); ?></p>
	<?php if ( $tracking['number'] ) : ?>
	<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;margin:16px 0">
		<thead>
			<tr>
				<th colspan="2" style="text-align:left;padding:12px;background:#f0f0f0;font-size:14px;border:1px solid #e5e5e5">
					<?php esc_html_e( 'Delivery Information', 'wc-fulfillment-sl' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td style="padding:10px 12px;border:1px solid #e5e5e5;width:40%;background:#fafafa"><strong><?php esc_html_e( 'Delivery Reference', 'wc-fulfillment-sl' ); ?></strong></td>
				<td style="padding:10px 12px;border:1px solid #e5e5e5"><?php echo esc_html( $tracking['number'] ); ?></td>
			</tr>
		</tbody>
	</table>
	<?php endif; ?>
<?php else : ?>
	<p><?php esc_html_e( 'Great news! Your order has been shipped and is on its way to you.', 'wc-fulfillment-sl' ); ?></p>
	<?php if ( $tracking['carrier'] || $tracking['number'] ) : ?>
	<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;margin:16px 0">
		<thead>
			<tr>
				<th colspan="2" style="text-align:left;padding:12px;background:#f0f0f0;font-size:14px;border:1px solid #e5e5e5">
					<?php esc_html_e( 'Tracking Information', 'wc-fulfillment-sl' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( $tracking['carrier'] ) : ?>
			<tr>
				<td style="padding:10px 12px;border:1px solid #e5e5e5;width:40%;background:#fafafa"><strong><?php esc_html_e( 'Carrier', 'wc-fulfillment-sl' ); ?></strong></td>
				<td style="padding:10px 12px;border:1px solid #e5e5e5"><?php echo esc_html( $tracking['carrier'] ); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ( $tracking['number'] ) : ?>
			<tr>
				<td style="padding:10px 12px;border:1px solid #e5e5e5;background:#fafafa"><strong><?php esc_html_e( 'Tracking Number', 'wc-fulfillment-sl' ); ?></strong></td>
				<td style="padding:10px 12px;border:1px solid #e5e5e5"><?php echo esc_html( $tracking['number'] ); ?></td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php if ( $tracking['url'] ) : ?>
	<p style="text-align:center;margin:24px 0">
		<a href="<?php echo esc_url( $tracking['url'] ); ?>" style="display:inline-block;background:#2271b1;color:#ffffff;padding:12px 28px;text-decoration:none;border-radius:4px;font-weight:bold;font-size:15px">
			<?php esc_html_e( 'Track Your Shipment', 'wc-fulfillment-sl' ); ?>
		</a>
	</p>
	<?php endif; ?>
	<?php endif; ?>
<?php endif; ?>

<p><?php esc_html_e( 'Your order summary:', 'wc-fulfillment-sl' ); ?></p>

<?php do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email ); ?>

<?php do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email ); ?>

<p><?php esc_html_e( 'Thank you for shopping with us!', 'wc-fulfillment-sl' ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
