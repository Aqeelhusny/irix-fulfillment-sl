<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class WCFSL_Settings {

	private static ?self $instance = null;
	const OPTION_KEY = 'wcfsl_settings';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',             [ $this, 'register_menu' ] );
		add_action( 'admin_post_wcfsl_save',  [ $this, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
		$this->maybe_fix_carrier_urls();
	}

	/**
	 * Migration: esc_url_raw() previously stripped { } from {number} in carrier
	 * URLs. Two failure modes are possible:
	 *   1. bare "number"            → replace with {number} using word boundaries
	 *   2. mid-word {number}        → URL is structurally broken; reset to defaults
	 *
	 * Gated by an option flag so it only runs once and not on every admin load.
	 */
	private function maybe_fix_carrier_urls(): void {
		if ( get_option( 'wcfsl_carrier_urls_migrated' ) ) return;

		$settings = (array) get_option( self::OPTION_KEY, [] );
		if ( empty( $settings['carriers'] ) || ! is_array( $settings['carriers'] ) ) {
			update_option( 'wcfsl_carrier_urls_migrated', '1' );
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

		update_option( 'wcfsl_carrier_urls_migrated', '1' );
	}

	public static function get( string $key = '' ): mixed {
		$defaults = self::defaults();
		$settings = (array) get_option( self::OPTION_KEY, [] );
		$settings = array_merge( $defaults, $settings );

		if ( $key === '' ) return $settings;
		return $settings[ $key ] ?? null;
	}

	public static function defaults(): array {
		return [
			'company_name'            => get_bloginfo( 'name' ),
			'company_address'         => '',
			'company_phone'           => '',
			'company_email'           => get_bloginfo( 'admin_email' ),
			'company_logo_id'         => 0,
			'invoice_footer'          => __( 'Thank you for your business!', 'wc-fulfillment-sl' ),
			'carriers'                => [
				[ 'name' => 'Sri Lanka Post',   'url' => 'https://www.slpost.lk/track/{number}' ],
				[ 'name' => 'DHL Sri Lanka',    'url' => 'https://www.dhl.com/lk-en/home/tracking.html?tracking-id={number}' ],
				[ 'name' => 'FedEx',            'url' => 'https://www.fedex.com/fedextrack/?tracknumbers={number}' ],
			],
			// Shipping method IDs treated as in-house local delivery (no external tracking link).
			'local_delivery_methods'  => [],
		];
	}

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Fulfillment SL', 'wc-fulfillment-sl' ),
			__( 'Fulfillment SL', 'wc-fulfillment-sl' ),
			'manage_woocommerce',
			'wcfsl-settings',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;

		$s        = self::get();
		$carriers = $s['carriers'] ?? [];
		$logo_url = $s['company_logo_id'] ? wp_get_attachment_image_url( $s['company_logo_id'], 'medium' ) : '';
		?>
		<div class="wrap wcfsl-settings-wrap">
			<h1><?php esc_html_e( 'WC Fulfillment SL — Settings', 'wc-fulfillment-sl' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wc-fulfillment-sl' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wcfsl_save', 'wcfsl_nonce' ); ?>
				<input type="hidden" name="action" value="wcfsl_save">

				<h2><?php esc_html_e( 'Company Information', 'wc-fulfillment-sl' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Company Logo', 'wc-fulfillment-sl' ); ?></th>
						<td>
							<div id="wcfsl-logo-wrap">
								<?php if ( $logo_url ) : ?>
									<img src="<?php echo esc_url( $logo_url ); ?>" id="wcfsl-logo-preview" style="max-height:80px;display:block;margin-bottom:8px;">
								<?php else : ?>
									<img src="" id="wcfsl-logo-preview" style="max-height:80px;display:none;margin-bottom:8px;">
								<?php endif; ?>
							</div>
							<input type="hidden" name="wcfsl[company_logo_id]" id="wcfsl-logo-id" value="<?php echo esc_attr( $s['company_logo_id'] ); ?>">
							<button type="button" class="button" id="wcfsl-logo-btn"><?php esc_html_e( 'Select Logo', 'wc-fulfillment-sl' ); ?></button>
							<button type="button" class="button" id="wcfsl-logo-remove" <?php echo $s['company_logo_id'] ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'wc-fulfillment-sl' ); ?></button>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Company Name', 'wc-fulfillment-sl' ); ?></th>
						<td><input type="text" name="wcfsl[company_name]" value="<?php echo esc_attr( $s['company_name'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Address', 'wc-fulfillment-sl' ); ?></th>
						<td><textarea name="wcfsl[company_address]" rows="3" class="regular-text"><?php echo esc_textarea( $s['company_address'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Phone', 'wc-fulfillment-sl' ); ?></th>
						<td><input type="text" name="wcfsl[company_phone]" value="<?php echo esc_attr( $s['company_phone'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Email', 'wc-fulfillment-sl' ); ?></th>
						<td><input type="email" name="wcfsl[company_email]" value="<?php echo esc_attr( $s['company_email'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Invoice Footer Note', 'wc-fulfillment-sl' ); ?></th>
						<td><input type="text" name="wcfsl[invoice_footer]" value="<?php echo esc_attr( $s['invoice_footer'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Thank you for your business!', 'wc-fulfillment-sl' ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Shipping Carriers', 'wc-fulfillment-sl' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Use {number} as placeholder for the tracking number in the URL.', 'wc-fulfillment-sl' ); ?></p>

				<table class="widefat wcfsl-carriers-table" id="wcfsl-carriers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Carrier Name', 'wc-fulfillment-sl' ); ?></th>
							<th><?php esc_html_e( 'Tracking URL (use {number})', 'wc-fulfillment-sl' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $carriers as $i => $carrier ) : ?>
						<tr class="wcfsl-carrier-row">
							<td><input type="text" name="wcfsl[carriers][<?php echo $i; ?>][name]" value="<?php echo esc_attr( $carrier['name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Sri Lanka Post', 'wc-fulfillment-sl' ); ?>"></td>
							<td><input type="text" name="wcfsl[carriers][<?php echo $i; ?>][url]" value="<?php echo esc_attr( $carrier['url'] ); ?>" class="large-text" placeholder="https://track.example.com/{number}"></td>
							<td><button type="button" class="button wcfsl-remove-carrier"><?php esc_html_e( 'Remove', 'wc-fulfillment-sl' ); ?></button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="wcfsl-add-carrier"><?php esc_html_e( '+ Add Carrier', 'wc-fulfillment-sl' ); ?></button></p>

				<h2><?php esc_html_e( 'Fulfillment Exceptions', 'wc-fulfillment-sl' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Store Pickup orders (WooCommerce built-in local pickup) are detected automatically — no tracking is required and no shipping email is sent.', 'wc-fulfillment-sl' ); ?>
				</p>
				<br>
				<h3><?php esc_html_e( 'Local Delivery Method IDs', 'wc-fulfillment-sl' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Enter the WooCommerce shipping method IDs used for your own in-house delivery service (one per line). Orders using these methods can be shipped without a tracking number, and customers will not receive an external tracking link.', 'wc-fulfillment-sl' ); ?>
					<br>
					<em><?php esc_html_e( 'Example IDs: local_delivery, flat_rate, free_shipping. Find yours under WooCommerce → Settings → Shipping.', 'wc-fulfillment-sl' ); ?></em>
				</p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Local Delivery Methods', 'wc-fulfillment-sl' ); ?></th>
						<td>
							<textarea name="wcfsl[local_delivery_methods]" rows="4" class="regular-text" placeholder="local_delivery&#10;flat_rate"><?php
								$ldm = $s['local_delivery_methods'] ?? [];
								echo esc_textarea( implode( "\n", (array) $ldm ) );
							?></textarea>
							<p class="description"><?php esc_html_e( 'One method ID per line.', 'wc-fulfillment-sl' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'wc-fulfillment-sl' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wc-fulfillment-sl' ), 403 );
		}
		check_admin_referer( 'wcfsl_save', 'wcfsl_nonce' );

		$raw  = isset( $_POST['wcfsl'] ) ? (array) $_POST['wcfsl'] : []; // phpcs:ignore
		$data = [];

		$data['company_name']    = sanitize_text_field( $raw['company_name'] ?? '' );
		$data['company_address'] = sanitize_textarea_field( $raw['company_address'] ?? '' );
		$data['company_phone']   = sanitize_text_field( $raw['company_phone'] ?? '' );
		$data['company_email']   = sanitize_email( $raw['company_email'] ?? '' );
		$data['company_logo_id'] = absint( $raw['company_logo_id'] ?? 0 );
		$data['invoice_footer']  = sanitize_text_field( $raw['invoice_footer'] ?? '' );

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

		wp_safe_redirect( add_query_arg( [ 'page' => 'wcfsl-settings', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'woocommerce_page_wcfsl-settings' ) return;

		wp_enqueue_media();
		wp_enqueue_script( 'wcfsl-admin', WCFSL_URL . 'assets/js/admin.js', [ 'jquery', 'media-upload' ], WCFSL_VERSION, true );
		wp_enqueue_style( 'wcfsl-admin', WCFSL_URL . 'assets/css/admin.css', [], WCFSL_VERSION );
	}
}
