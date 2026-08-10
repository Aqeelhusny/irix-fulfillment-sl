<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Settings {

	use IRIXFSL_Singleton;

	const OPTION_KEY = 'irixfsl_settings';

	protected function boot(): void {
		add_action( 'admin_menu',             [ $this, 'register_menu' ] );
		add_action( 'admin_post_irixfsl_save', [ $this, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init',             [ $this, 'maybe_fix_carrier_urls' ] );
		add_action( 'admin_init',             [ $this, 'maybe_migrate_local_delivery_methods' ] );
	}

	/**
	 * Migration: esc_url_raw() previously stripped { } from {number} in carrier
	 * URLs. Two failure modes are possible:
	 *   1. bare "number"            → replace with {number} using word boundaries
	 *   2. mid-word {number}        → URL is structurally broken; reset to defaults
	 *
	 * Gated by an option flag so it only runs once and not on every admin load.
	 */
	public function maybe_fix_carrier_urls(): void {
		if ( get_option( 'irixfsl_carrier_urls_migrated' ) ) return;

		$settings = (array) get_option( self::OPTION_KEY, [] );
		if ( empty( $settings['carriers'] ) || ! is_array( $settings['carriers'] ) ) {
			update_option( 'irixfsl_carrier_urls_migrated', '1' );
			return;
		}

		// Detect structural corruption: {number} sitting inside a word, e.g. track{number}s
		foreach ( $settings['carriers'] as $carrier ) {
			if ( ! empty( $carrier['url'] ) && preg_match( '/\w\{number\}|\{number\}\w/', $carrier['url'] ) ) {
				// Reset only carriers to defaults; keep all other company settings intact.
				$settings['carriers'] = self::defaults()['carriers'];
				update_option( self::OPTION_KEY, $settings );
				return;
			}
		}

		// Simpler case: bare standalone "number" not yet wrapped in { }
		$changed = false;
		foreach ( $settings['carriers'] as &$carrier ) {
			if ( empty( $carrier['url'] ) ) continue;
			$fixed = preg_replace( '/(?<!\{)\bnumber\b(?!\})/', '{number}', $carrier['url'] );
			if ( $fixed !== $carrier['url'] ) {
				$carrier['url'] = $fixed;
				$changed = true;
			}
		}
		unset( $carrier );

		if ( $changed ) {
			update_option( self::OPTION_KEY, $settings );
		}

		update_option( 'irixfsl_carrier_urls_migrated', '1' );
	}

	/**
	 * Migration: local_delivery_methods used to be stored as a flat array of
	 * plain method-ID strings (one per textarea line). It's now an array of
	 * rows with a name/method_id/fee shape — wrap any old-format entries so
	 * existing installs don't break on the new admin table or on
	 * array_column() lookups in IRIXFSL_Tracking::get_fulfillment_type().
	 *
	 * Gated by an option flag so it only runs once and not on every admin load.
	 */
	public function maybe_migrate_local_delivery_methods(): void {
		if ( get_option( 'irixfsl_local_delivery_methods_migrated' ) ) return;

		$settings = (array) get_option( self::OPTION_KEY, [] );
		$ldm      = $settings['local_delivery_methods'] ?? [];

		if ( is_array( $ldm ) && ! empty( $ldm ) && ! is_array( reset( $ldm ) ) ) {
			$settings['local_delivery_methods'] = array_map(
				static fn( $id ) => [ 'name' => (string) $id, 'method_id' => (string) $id, 'fee' => '' ],
				$ldm
			);
			update_option( self::OPTION_KEY, $settings );
		}

		update_option( 'irixfsl_local_delivery_methods_migrated', '1' );
	}

	/** Request-level cache — avoids repeated get_option() calls per page load. */
	private static array $_cache = [];

	public static function get( string $key = '' ): mixed {
		if ( empty( self::$_cache ) ) {
			self::$_cache = array_merge( self::defaults(), (array) get_option( self::OPTION_KEY, [] ) );
		}

		if ( $key === '' ) return self::$_cache;
		return self::$_cache[ $key ] ?? null;
	}

	public static function flush_cache(): void {
		self::$_cache = [];
	}

	public static function defaults(): array {
		return [
			'company_name'            => get_bloginfo( 'name' ),
			'company_address'         => '',
			'company_phone'           => '',
			'company_email'           => get_bloginfo( 'admin_email' ),
			'company_logo_id'         => 0,
			'invoice_footer'          => __( 'Thank you for your business!', 'irix-fulfillment-sl' ),
			'carriers'                => [
				[ 'name' => 'Sri Lanka Post',   'url' => 'https://www.slpost.lk/track/{number}' ],
				[ 'name' => 'DHL Sri Lanka',    'url' => 'https://www.dhl.com/lk-en/home/tracking.html?tracking-id={number}' ],
				[ 'name' => 'FedEx',            'url' => 'https://www.fedex.com/fedextrack/?tracknumbers={number}' ],
			],
			// Shipping method IDs treated as in-house local delivery (no external tracking link).
			'local_delivery_methods'  => [],
			// URL encoded as a QR code printed below the waybill (e.g. your shop or returns page).
			'waybill_scan_url'        => '',
			// WhatsApp Business Cloud API (Meta) — sends a template message when an order is marked Shipped.
			'whatsapp_enabled'             => false,
			'whatsapp_phone_number_id'     => '',
			'whatsapp_access_token'        => '',
			'whatsapp_template_name'       => '',
			'whatsapp_template_language'   => 'en_US',
			'whatsapp_default_calling_code' => '94',
		];
	}

	/**
	 * Returns the shipping method IDs registered with WooCommerce (core types
	 * like flat_rate/local_pickup/free_shipping, plus any custom method types
	 * registered by other plugins), keyed by ID with a human-readable title —
	 * used to populate the Method ID dropdown so admins don't have to guess
	 * or type an exact ID by hand.
	 */
	public static function get_registered_shipping_methods(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->shipping() ) return [];

		$choices = [];
		foreach ( WC()->shipping()->get_shipping_methods() as $method ) {
			$choices[ $method->id ] = $method->get_method_title() ?: $method->id;
		}
		return $choices;
	}

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Fulfillment SL', 'irix-fulfillment-sl' ),
			__( 'Fulfillment SL', 'irix-fulfillment-sl' ),
			'manage_woocommerce',
			'irixfsl-settings',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;

		$s                        = self::get();
		$carriers                 = $s['carriers'] ?? [];
		$local_delivery_methods   = $s['local_delivery_methods'] ?? [];
		$shipping_method_choices  = self::get_registered_shipping_methods();
		$logo_url                 = $s['company_logo_id'] ? wp_get_attachment_image_url( $s['company_logo_id'], 'medium' ) : '';
		?>
		<div class="wrap irixfsl-settings-wrap">
			<h1><?php esc_html_e( 'IRIX Fulfillment SL — Settings', 'irix-fulfillment-sl' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'irix-fulfillment-sl' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'irixfsl_save', 'irixfsl_nonce' ); ?>
				<input type="hidden" name="action" value="irixfsl_save">

				<h2><?php esc_html_e( 'Company Information', 'irix-fulfillment-sl' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Company Logo', 'irix-fulfillment-sl' ); ?></th>
						<td>
							<div id="irixfsl-logo-wrap">
								<?php if ( $logo_url ) : ?>
									<img src="<?php echo esc_url( $logo_url ); ?>" id="irixfsl-logo-preview" style="max-height:80px;display:block;margin-bottom:8px;">
								<?php else : ?>
									<img src="" id="irixfsl-logo-preview" style="max-height:80px;display:none;margin-bottom:8px;">
								<?php endif; ?>
							</div>
							<input type="hidden" name="irixfsl[company_logo_id]" id="irixfsl-logo-id" value="<?php echo esc_attr( $s['company_logo_id'] ); ?>">
							<button type="button" class="button" id="irixfsl-logo-btn"><?php esc_html_e( 'Select Logo', 'irix-fulfillment-sl' ); ?></button>
							<button type="button" class="button" id="irixfsl-logo-remove" <?php echo $s['company_logo_id'] ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'irix-fulfillment-sl' ); ?></button>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Company Name', 'irix-fulfillment-sl' ); ?></th>
						<td><input type="text" name="irixfsl[company_name]" value="<?php echo esc_attr( $s['company_name'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Address', 'irix-fulfillment-sl' ); ?></th>
						<td><textarea name="irixfsl[company_address]" rows="3" class="regular-text"><?php echo esc_textarea( $s['company_address'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Phone', 'irix-fulfillment-sl' ); ?></th>
						<td><input type="text" name="irixfsl[company_phone]" value="<?php echo esc_attr( $s['company_phone'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Email', 'irix-fulfillment-sl' ); ?></th>
						<td><input type="email" name="irixfsl[company_email]" value="<?php echo esc_attr( $s['company_email'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Invoice Footer Note', 'irix-fulfillment-sl' ); ?></th>
						<td><input type="text" name="irixfsl[invoice_footer]" value="<?php echo esc_attr( $s['invoice_footer'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Thank you for your business!', 'irix-fulfillment-sl' ); ?>"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Waybill Scan URL', 'irix-fulfillment-sl' ); ?></th>
						<td>
							<input type="url" name="irixfsl[waybill_scan_url]" value="<?php echo esc_attr( $s['waybill_scan_url'] ); ?>" class="regular-text" placeholder="https://yourstore.com">
							<p class="description"><?php esc_html_e( 'A QR code for this URL will be printed below every waybill. Leave blank to hide the QR code.', 'irix-fulfillment-sl' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Shipping Carriers', 'irix-fulfillment-sl' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Use {number} as placeholder for the tracking number in the URL.', 'irix-fulfillment-sl' ); ?></p>

				<table class="widefat irixfsl-carriers-table" id="irixfsl-carriers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Carrier Name', 'irix-fulfillment-sl' ); ?></th>
							<th><?php esc_html_e( 'Tracking URL (use {number})', 'irix-fulfillment-sl' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $carriers as $i => $carrier ) : $i = (int) $i; ?>
						<tr class="irixfsl-carrier-row">
							<td><input type="text" name="irixfsl[carriers][<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $carrier['name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Sri Lanka Post', 'irix-fulfillment-sl' ); ?>"></td>
							<td><input type="text" name="irixfsl[carriers][<?php echo esc_attr( $i ); ?>][url]" value="<?php echo esc_attr( $carrier['url'] ); ?>" class="large-text" placeholder="https://track.example.com/{number}"></td>
							<td><button type="button" class="button irixfsl-remove-carrier"><?php esc_html_e( 'Remove', 'irix-fulfillment-sl' ); ?></button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="irixfsl-add-carrier"><?php esc_html_e( '+ Add Carrier', 'irix-fulfillment-sl' ); ?></button></p>

				<h2><?php esc_html_e( 'Fulfillment Exceptions', 'irix-fulfillment-sl' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Store Pickup orders (WooCommerce built-in local pickup) are detected automatically — no tracking is required and no shipping email is sent.', 'irix-fulfillment-sl' ); ?>
				</p>
				<br>
				<h3><?php esc_html_e( 'Local Delivery Methods', 'irix-fulfillment-sl' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Tag your own in-house/local courier services here — give each one any name you like, and pick the WooCommerce shipping method it uses from the dropdown (add it under WooCommerce → Settings → Shipping first if it\'s not listed). Orders using these methods can be shipped without a tracking number, and customers will not receive an external tracking link.', 'irix-fulfillment-sl' ); ?>
				</p>

				<table class="widefat irixfsl-local-delivery-table" id="irixfsl-local-delivery-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'irix-fulfillment-sl' ); ?></th>
							<th><?php esc_html_e( 'Method ID', 'irix-fulfillment-sl' ); ?></th>
							<th><?php esc_html_e( 'Fee (reference only)', 'irix-fulfillment-sl' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $local_delivery_methods as $i => $row ) : $i = (int) $i; $current_id = $row['method_id'] ?? ''; ?>
						<tr class="irixfsl-local-delivery-row">
							<td><input type="text" name="irixfsl[local_delivery_methods][<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $row['name'] ?? '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. In-house Rider', 'irix-fulfillment-sl' ); ?>"></td>
							<td>
								<select name="irixfsl[local_delivery_methods][<?php echo esc_attr( $i ); ?>][method_id]" class="regular-text">
									<option value=""><?php esc_html_e( '— Select shipping method —', 'irix-fulfillment-sl' ); ?></option>
									<?php foreach ( $shipping_method_choices as $method_id => $method_title ) : ?>
										<option value="<?php echo esc_attr( $method_id ); ?>" <?php selected( $current_id, $method_id ); ?>><?php echo esc_html( $method_title ); ?> (<?php echo esc_html( $method_id ); ?>)</option>
									<?php endforeach; ?>
									<?php if ( $current_id !== '' && ! isset( $shipping_method_choices[ $current_id ] ) ) : ?>
										<option value="<?php echo esc_attr( $current_id ); ?>" selected><?php echo esc_html( $current_id ); ?> <?php esc_html_e( '(not currently registered)', 'irix-fulfillment-sl' ); ?></option>
									<?php endif; ?>
								</select>
							</td>
							<td><input type="number" step="0.01" min="0" name="irixfsl[local_delivery_methods][<?php echo esc_attr( $i ); ?>][fee]" value="<?php echo esc_attr( $row['fee'] ?? '' ); ?>" placeholder="0.00"></td>
							<td><button type="button" class="button irixfsl-remove-local-delivery"><?php esc_html_e( 'Remove', 'irix-fulfillment-sl' ); ?></button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="irixfsl-add-local-delivery"><?php esc_html_e( '+ Add Local Delivery Method', 'irix-fulfillment-sl' ); ?></button></p>

				<h2><?php esc_html_e( 'WhatsApp Notifications', 'irix-fulfillment-sl' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Sends a WhatsApp message via the Meta WhatsApp Business Cloud API when an order is marked Shipped. This requires a pre-approved message template — freeform text cannot be sent for system-triggered notifications.', 'irix-fulfillment-sl' ); ?>
				</p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable WhatsApp Notifications', 'irix-fulfillment-sl' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="irixfsl[whatsapp_enabled]" value="1" <?php checked( ! empty( $s['whatsapp_enabled'] ) ); ?>>
								<?php esc_html_e( 'Send a WhatsApp message automatically when an order is marked Shipped', 'irix-fulfillment-sl' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Phone Number ID', 'irix-fulfillment-sl' ); ?></th>
						<td>
							<input type="text" name="irixfsl[whatsapp_phone_number_id]" value="<?php echo esc_attr( $s['whatsapp_phone_number_id'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'From Meta Business Manager → WhatsApp Manager → API Setup.', 'irix-fulfillment-sl' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Access Token', 'irix-fulfillment-sl' ); ?></th>
						<td>
							<input type="password" name="irixfsl[whatsapp_access_token]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $s['whatsapp_access_token'] !== '' ? esc_attr__( '•••••••• (leave blank to keep current token)', 'irix-fulfillment-sl' ) : ''; ?>">
							<p class="description"><?php esc_html_e( 'A permanent System User access token with whatsapp_business_messaging permission. Never displayed once saved — leave blank to keep the existing token.', 'irix-fulfillment-sl' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Template Name', 'irix-fulfillment-sl' ); ?></th>
						<td>
							<input type="text" name="irixfsl[whatsapp_template_name]" value="<?php echo esc_attr( $s['whatsapp_template_name'] ); ?>" class="regular-text" placeholder="order_shipped_notification">
							<p class="description"><?php esc_html_e( 'The exact name of your approved Utility-category template. It must have 4 body variables, in order: customer name, order number, tracking link, store name.', 'irix-fulfillment-sl' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Template Language', 'irix-fulfillment-sl' ); ?></th>
						<td><input type="text" name="irixfsl[whatsapp_template_language]" value="<?php echo esc_attr( $s['whatsapp_template_language'] ); ?>" class="regular-text" placeholder="en_US"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Default Calling Code', 'irix-fulfillment-sl' ); ?></th>
						<td>
							<input type="text" name="irixfsl[whatsapp_default_calling_code]" value="<?php echo esc_attr( $s['whatsapp_default_calling_code'] ); ?>" class="small-text" placeholder="94">
							<p class="description"><?php esc_html_e( 'Used to convert local-format phone numbers (e.g. 0771234567) to international format when the customer\'s phone doesn\'t already include a country code.', 'irix-fulfillment-sl' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'irix-fulfillment-sl' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'irix-fulfillment-sl' ), 403 );
		}
		check_admin_referer( 'irixfsl_save', 'irixfsl_nonce' );

		$raw  = isset( $_POST['irixfsl'] ) ? (array) wp_unslash( $_POST['irixfsl'] ) : []; // phpcs:ignore
		$data = self::prepare_data_for_save( $raw );

		update_option( self::OPTION_KEY, $data );
		self::flush_cache();

		wp_safe_redirect( add_query_arg( [ 'page' => 'irixfsl-settings', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Builds the sanitized options array that handle_save() persists, from the
	 * raw (already wp_unslash()'d) $_POST['irixfsl'] payload. Pulled out of
	 * handle_save() so the POST → sanitized-array wiring is testable without
	 * triggering that method's terminating wp_safe_redirect()/exit.
	 */
	public static function prepare_data_for_save( array $raw ): array {
		$data = [];

		$data['company_name']    = sanitize_text_field( $raw['company_name'] ?? '' );
		$data['company_address'] = sanitize_textarea_field( $raw['company_address'] ?? '' );
		$data['company_phone']   = sanitize_text_field( $raw['company_phone'] ?? '' );
		$data['company_email']   = sanitize_email( $raw['company_email'] ?? '' );
		$data['company_logo_id'] = absint( $raw['company_logo_id'] ?? 0 );
		$data['invoice_footer']    = sanitize_text_field( $raw['invoice_footer'] ?? '' );
		$data['waybill_scan_url']  = esc_url_raw( $raw['waybill_scan_url'] ?? '' );

		$data['carriers'] = [];
		if ( ! empty( $raw['carriers'] ) && is_array( $raw['carriers'] ) ) {
			foreach ( $raw['carriers'] as $carrier ) {
				$name = sanitize_text_field( $carrier['name'] ?? '' );
				// Carrier URL is a template — use sanitize_text_field so {number}
				// placeholder is preserved. esc_url_raw would encode {} to %7B%7D.
				$url  = sanitize_text_field( $carrier['url'] ?? '' );
				if ( $name !== '' ) {
					$data['carriers'][] = [ 'name' => $name, 'url' => $url ];
				}
			}
		}

		$data['local_delivery_methods'] = self::sanitize_local_delivery_methods( $raw['local_delivery_methods'] ?? [] );

		$data['whatsapp_enabled']           = ! empty( $raw['whatsapp_enabled'] );
		$data['whatsapp_phone_number_id']   = sanitize_text_field( $raw['whatsapp_phone_number_id'] ?? '' );
		$data['whatsapp_template_name']     = sanitize_text_field( $raw['whatsapp_template_name'] ?? '' );
		$data['whatsapp_template_language'] = sanitize_text_field( $raw['whatsapp_template_language'] ?? '' ) ?: 'en_US';
		$data['whatsapp_default_calling_code'] = preg_replace( '/\D+/', '', (string) ( $raw['whatsapp_default_calling_code'] ?? '' ) ) ?: '94';

		// The Access Token field is never redisplayed once saved (see render_page()) —
		// a blank submission means "leave it unchanged", not "clear it".
		$posted_token = sanitize_text_field( $raw['whatsapp_access_token'] ?? '' );
		$data['whatsapp_access_token'] = $posted_token !== '' ? $posted_token : (string) self::get( 'whatsapp_access_token' );

		return $data;
	}

	/**
	 * Sanitizes posted local_delivery_methods rows: drops rows with a blank
	 * Method ID (that's the field actually matched against a real WooCommerce
	 * shipping method's get_method_id()), and de-duplicates by Method ID —
	 * last row for a given ID wins. Name is freeform and never required to
	 * match anything; Fee is informational only.
	 */
	public static function sanitize_local_delivery_methods( array $raw_rows ): array {
		$seen = [];
		foreach ( $raw_rows as $row ) {
			$method_id = sanitize_text_field( trim( (string) ( $row['method_id'] ?? '' ) ) );
			if ( $method_id === '' ) continue;

			$seen[ $method_id ] = [
				'name'      => sanitize_text_field( $row['name'] ?? '' ),
				'method_id' => $method_id,
				'fee'       => self::sanitize_optional_number( $row['fee'] ?? '' ),
			];
		}
		return array_values( $seen );
	}

	private static function sanitize_optional_number( $value ): string {
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( $value === '' || ! is_numeric( $value ) ) return '';
		return (string) max( 0, (float) $value );
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'woocommerce_page_irixfsl-settings' ) return;

		wp_enqueue_media();
		wp_enqueue_script( 'irixfsl-admin', IRIXFSL_URL . 'assets/js/admin.js', [ 'jquery', 'media-upload' ], IRIXFSL_VERSION, true );
		wp_localize_script( 'irixfsl-admin', 'irixfslAdmin', [
			'shippingMethods' => self::get_registered_shipping_methods(),
			'i18n' => [
				'selectLogo'          => __( 'Select Company Logo', 'irix-fulfillment-sl' ),
				'useThisImage'        => __( 'Use this image', 'irix-fulfillment-sl' ),
				'remove'              => __( 'Remove', 'irix-fulfillment-sl' ),
				'addCarrier'          => __( 'e.g. My Courier', 'irix-fulfillment-sl' ),
				'selectShippingMethod' => __( '— Select shipping method —', 'irix-fulfillment-sl' ),
			],
		] );
		wp_enqueue_style( 'irixfsl-admin', IRIXFSL_URL . 'assets/css/admin.css', [], IRIXFSL_VERSION );
	}
}
