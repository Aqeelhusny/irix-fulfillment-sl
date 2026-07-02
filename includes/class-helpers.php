<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shared utility functions used across multiple plugin classes.
 */
final class IRIXFSL_Helpers {

	/**
	 * Resolve a WC_Order from a post object, order object, or numeric ID.
	 *
	 * Used by meta-box render/save callbacks that receive either a WP_Post or WC_Order
	 * depending on whether HPOS or the classic post editor is active.
	 */
	public static function resolve_order( $post_or_order ): ?WC_Order {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}
		if ( is_numeric( $post_or_order ) ) {
			$order = wc_get_order( (int) $post_or_order );
			return $order instanceof WC_Order ? $order : null;
		}
		if ( isset( $post_or_order->ID ) ) {
			$order = wc_get_order( $post_or_order->ID );
			return $order instanceof WC_Order ? $order : null;
		}
		return null;
	}

	/**
	 * Return the common variables needed by all printable document templates.
	 *
	 * @return array{ settings: array, logo_url: string, print_url: string }
	 */
	public static function get_document_context(): array {
		$s        = IRIXFSL_Settings::get();
		$logo_url = $s['company_logo_id'] ? wp_get_attachment_image_url( $s['company_logo_id'], 'medium' ) : '';

		return [
			'settings'  => $s,
			'logo_url'  => $logo_url ?: '',
			'print_url' => IRIXFSL_URL . 'assets/css/print.css',
		];
	}

	/**
	 * Determine whether the waybill document is available for an order.
	 *
	 * A waybill is available when the order already has a tracking number saved
	 * OR when its status is "Ready to Ship" (pre-dispatch label).
	 */
	public static function is_waybill_available( WC_Order $order ): bool {
		$tracking = IRIXFSL_Tracking::get_tracking( $order );
		return ! empty( $tracking['number'] ) || $order->has_status( 'ready-to-ship' );
	}

	/**
	 * Get the formatted shipping address for an order, falling back to billing
	 * when no shipping address is present.
	 *
	 * @return array{ name: string, address: string }
	 */
	public static function get_ship_to( WC_Order $order ): array {
		$has_shipping = (bool) $order->get_shipping_address_1();

		$name = $has_shipping
			? $order->get_formatted_shipping_full_name()
			: $order->get_formatted_billing_full_name();

		$fields = $has_shipping
			? [
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
				'city'      => $order->get_shipping_city(),
				'state'     => $order->get_shipping_state(),
				'postcode'  => $order->get_shipping_postcode(),
				'country'   => $order->get_shipping_country(),
			]
			: [
				'address_1' => $order->get_billing_address_1(),
				'address_2' => $order->get_billing_address_2(),
				'city'      => $order->get_billing_city(),
				'state'     => $order->get_billing_state(),
				'postcode'  => $order->get_billing_postcode(),
				'country'   => $order->get_billing_country(),
			];

		$countries = WC()->countries;
		$address   = $countries
			? $countries->get_formatted_address( $fields )
			: implode( ', ', array_filter( $fields ) );

		return [
			'name'    => $name ?: $order->get_formatted_billing_full_name(),
			'address' => $address,
		];
	}

	/**
	 * Render item variation/meta HTML snippet.
	 *
	 * Outputs a `<div class="item-meta">` block for a given order item when
	 * meta data exists. Returns empty string otherwise.
	 */
	public static function render_item_meta( WC_Order_Item_Product $item ): string {
		$meta = $item->get_formatted_meta_data( '_', true );
		if ( ! $meta ) {
			return '';
		}

		$html = '<div class="item-meta">';
		foreach ( $meta as $m ) {
			$html .= esc_html( $m->display_key ) . ': ' . wp_kses_post( $m->display_value ) . '<br>';
		}
		$html .= '</div>';

		return $html;
	}
}
