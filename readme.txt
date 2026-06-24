=== IRIX Fulfillment SL ===
Contributors: irix
Tags: woocommerce, fulfillment, shipping, tracking, invoice, packing slip, waybill, sri lanka
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fulfillment and shipment tracking plugin for WooCommerce — built for Sri Lankan e-commerce operations.

== Description ==

IRIX Fulfillment SL adds a complete order fulfilment workflow on top of WooCommerce. It introduces two custom order statuses, shipment tracking, printable documents, and smart dispatch logic for store pickup, local delivery, and standard courier orders.

**Custom Order Statuses**

* **Ready to Ship** — marks an order as packed and awaiting dispatch.
* **Shipped** — marks an order as dispatched. Requires a waybill or tracking number for standard courier orders.

Both statuses are treated as paid and appear in the WooCommerce orders list with colour-coded badges.

**Shipment Tracking**

Save carrier, tracking number, and tracking URL directly on the order edit screen. Carrier tracking URL templates support the `{number}` placeholder — the URL is auto-generated as you type the tracking number.

**Automatic Tracking Email**

When an order moves to Shipped, a branded email is sent to the customer with their tracking details. The email is sent only once. A manual resend button is available on the order edit screen.

**Fulfillment Types**

The plugin automatically detects the fulfilment type for each order:

* **Store Pickup** — WooCommerce built-in local pickup methods are detected automatically. No tracking number required; no shipping email sent.
* **Local Delivery** — Configure your own in-house delivery method IDs in Settings. No external tracking link is sent; the email reflects your in-house delivery instead.
* **Standard** — All other orders. A waybill / tracking number is required before the order can be marked as Shipped.

**Printable Documents**

* **Invoice** — Itemised tax invoice with your company branding.
* **Packing Slip** — Picking list for warehouse staff.
* **Waybill** — 100 × 150 mm courier label with barcode, recipient address, order ID, carrier, piece count, and an optional QR code linking to a custom URL (configurable per site).

All documents are accessible from the order edit screen sidebar and from the orders list column. Invoices and packing slips support bulk printing via WooCommerce bulk actions.

**Settings**

Configure company name, address, phone, email, and logo. Manage carrier list with tracking URL templates. Set local delivery method IDs. Set the waybill scan URL for the QR code.

== Installation ==

1. Upload the `irix-fulfillment-sl` folder to the `/wp-content/plugins/` directory, or install via the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **WooCommerce → Fulfillment SL** to configure your company details, carriers, and delivery exceptions.

== Frequently Asked Questions ==

= Can I use this without WooCommerce? =

No. IRIX Fulfillment SL requires WooCommerce 7.0 or later.

= Does it work with WooCommerce HPOS (High-Performance Order Storage)? =

Yes. The plugin is fully compatible with HPOS. Order meta is read and written using the WooCommerce order object API.

= Why can't I mark an order as Shipped without a tracking number? =

For standard courier orders, a waybill or tracking number is required before dispatch so the customer can be given accurate tracking information. Store Pickup and Local Delivery orders are exempt from this requirement.

= How do I set up Local Delivery? =

Go to **WooCommerce → Fulfillment SL → Fulfillment Exceptions** and enter your WooCommerce shipping method IDs (one per line) in the Local Delivery Methods field. You can find your method IDs under **WooCommerce → Settings → Shipping**.

= Can the tracking email be customised? =

The email uses WooCommerce's standard email template system. You can override the HTML template by copying `templates/emails/irixfsl-tracking-notification.php` to `your-theme/irix-fulfillment-sl/emails/irixfsl-tracking-notification.php`.

= What size is the waybill? =

100 mm × 150 mm. The `@page` CSS rule is set to this size so it prints correctly on thermal and desktop printers without additional scaling.

= How does the waybill QR code work? =

Enter a URL in **Settings → Waybill Scan URL**. A QR code for that URL is printed in the bottom strip of every waybill. This can be your shop URL, a returns portal, or any other page. Leave the field blank to hide the QR strip.

== Screenshots ==

1. Order edit screen — Fulfillment sidebar with document buttons.
2. Order edit screen — Shipment Tracking panel with carrier and tracking number fields.
3. Orders list — Fulfillment column with Invoice, Packing Slip, and Waybill links.
4. Waybill — 100 × 150 mm printed label.
5. Tracking notification email — sent to the customer when an order is shipped.
6. Plugin settings page — company details, carriers, and fulfillment exceptions.

== Changelog ==

= 1.0.0 =
* Initial release.
* Custom order statuses: Ready to Ship, Shipped.
* Shipment tracking meta box with carrier dropdown and URL auto-generation.
* Automatic tracking email on dispatch (sent once, with manual resend).
* Store Pickup auto-detection — no tracking required, no email sent.
* Local Delivery configuration — ships without external tracking link.
* Tracking number enforced for standard orders (JS guard + server-side revert).
* Printable Invoice, Packing Slip, and Waybill documents.
* Waybill redesigned to 100 × 150 mm with barcode, QR strip, and inverted recipient flag.
* Bulk status actions: Mark Ready to Ship, Mark Shipped.
* Bulk print: Invoices, Packing Slips.
* Plugin renamed from wc-fulfillment-sl to irix-fulfillment-sl.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.
