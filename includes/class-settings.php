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
		];
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

		$s        = self::get();
		$carriers = $s['carriers'] ?? [];
		$logo_url = $s['company_logo_id'] ? wp_get_attachment_image_url( $s['company_logo_id'], 'medium' ) : '';
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
						<?php foreach ( $carriers as $i => $carrier ) : ?>
						<tr class="irixfsl-carrier-row">
							<td><input type="text" name="irixfsl[carriers][<?php echo $i; ?>][name]" value="<?php echo esc_attr( $carrier['name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Sri Lanka Post', 'irix-fulfillment-sl' ); ?>"></td>
							<td><input type="text" name="irixfsl[carriers][<?php echo $i; ?>][url]" value="<?php echo esc_attr( $carrier['url'] ); ?>" class="large-text" placeholder="https://track.example.com/{number}"></td>
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
				<h3><?php esc_html_e( 'Local Delivery Method IDs', 'irix-fulfillment-sl' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Enter the WooCommerce shipping method IDs used for your own in-house delivery service (one per line). Orders using these methods can be shipped without a tracking number, and customers will not receive an external tracking link.', 'irix-fulfillment-sl' ); ?>
					<br>
					<em><?php esc_html_e( 'Example IDs: local_delivery, flat_rate, free_shipping. Find yours under WooCommerce → Settings → Shipping.', 'irix-fulfillment-sl' ); ?></em>
				</p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Local Delivery Methods', 'irix-fulfillment-sl' ); ?></th>
						<td>
							<textarea name="irixfsl[local_delivery_methods]" rows="4" class="regular-text" placeholder="local_delivery&#10;flat_rate"><?php
								$ldm = $s['local_delivery_methods'] ?? [];
								echo esc_textarea( implode( "\n", (array) $ldm ) );
							?></textarea>
							<p class="description"><?php esc_html_e( 'One method ID per line.', 'irix-fulfillment-sl' ); ?></p>
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

		// Local delivery method IDs — one per line, stored as array.
		$ldm_raw             = sanitize_textarea_field( $raw['local_delivery_methods'] ?? '' );
		$data['local_delivery_methods'] = array_values( array_filter(
			array_map( 'trim', explode( "\n", $ldm_raw ) )
		) );

		update_option( self::OPTION_KEY, $data );
		self::flush_cache();

		wp_safe_redirect( add_query_arg( [ 'page' => 'irixfsl-settings', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'woocommerce_page_irixfsl-settings' ) return;

		wp_enqueue_media();
		wp_enqueue_script( 'irixfsl-admin', IRIXFSL_URL . 'assets/js/admin.js', [ 'jquery', 'media-upload' ], IRIXFSL_VERSION, true );
		wp_localize_script( 'irixfsl-admin', 'irixfslAdmin', [
			'i18n' => [
				'selectLogo'   => __( 'Select Company Logo', 'irix-fulfillment-sl' ),
				'useThisImage' => __( 'Use this image', 'irix-fulfillment-sl' ),
				'remove'       => __( 'Remove', 'irix-fulfillment-sl' ),
				'addCarrier'   => __( 'e.g. My Courier', 'irix-fulfillment-sl' ),
			],
		] );
		wp_enqueue_style( 'irixfsl-admin', IRIXFSL_URL . 'assets/css/admin.css', [], IRIXFSL_VERSION );
	}
}
