<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class IRIXFSL_Admin {

	use IRIXFSL_Singleton;

	protected function boot(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices',         [ $this, 'bulk_redirect_notice' ] );
		add_action( 'template_redirect',     [ $this, 'render_bulk_invoices' ], 5 );

		// Documents meta box on single order edit
		add_action( 'add_meta_boxes', [ $this, 'add_documents_meta_box' ] );

		// Documents column on orders list
		// HPOS
		add_filter( 'manage_woocommerce_page_wc-orders_columns',        [ $this, 'add_documents_column' ] );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column',  [ $this, 'render_documents_column_hpos' ], 10, 2 );
		// Classic
		add_filter( 'manage_edit-shop_order_columns',                   [ $this, 'add_documents_column' ] );
		add_action( 'manage_shop_order_posts_custom_column',            [ $this, 'render_documents_column_classic' ], 10, 2 );

		// Bulk actions — WP core (classic) and WC's HPOS list table both fire
		// handle_bulk_actions-{screen} with the action as the 2nd argument.
		add_filter( 'bulk_actions-edit-shop_order',                      [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-shop_order',               [ $this, 'handle_bulk_actions' ], 10, 3 );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders',           [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders',    [ $this, 'handle_bulk_actions' ], 10, 3 );
	}

	// ─── Documents meta box (order edit sidebar) ─────────────────────────────

	public function add_documents_meta_box(): void {
		foreach ( [ 'shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
			add_meta_box(
				'irixfsl-documents',
				__( 'Fulfillment', 'irix-fulfillment-sl' ),
				[ $this, 'render_documents_meta_box' ],
				$screen,
				'side',
				'high'
			);
		}
	}

	public function render_documents_meta_box( $post_or_order ): void {
		$order = IRIXFSL_Helpers::resolve_order( $post_or_order );

		if ( ! $order || ! $order->get_id() ) {
			echo '<p class="irixfsl-doc-unsaved">'
				. esc_html__( 'Save the order first to access documents.', 'irix-fulfillment-sl' )
				. '</p>';
			return;
		}

		$invoice_url  = IRIXFSL_Invoice::invoice_url( $order->get_id() );
		$slip_url     = IRIXFSL_Packing_Slip::packing_slip_url( [ $order->get_id() ] );
		$waybill_ok   = IRIXFSL_Helpers::is_waybill_available( $order );
		$docs = [
			[ 'media-spreadsheet', __( 'Invoice', 'irix-fulfillment-sl' ), $invoice_url ],
			[ 'printer', __( 'Packing slip', 'irix-fulfillment-sl' ), $slip_url ],
			[ 'location-alt', __( 'Waybill', 'irix-fulfillment-sl' ), $waybill_ok ? IRIXFSL_Waybill::waybill_url( $order->get_id() ) : '' ],
		];
		?>
		<ul class="irixfsl-doc-list">
			<?php foreach ( $docs as [ $icon, $label, $url ] ) : ?>
			<li>
				<?php if ( $url ) : ?>
					<a class="irixfsl-doc-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
						<span class="irixfsl-doc-link__label"><?php echo esc_html( $label ); ?></span>
						<span class="dashicons dashicons-external irixfsl-doc-link__go" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'irix-fulfillment-sl' ); ?></span>
					</a>
				<?php else : ?>
					<span class="irixfsl-doc-link is-disabled" aria-disabled="true">
						<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
						<span class="irixfsl-doc-link__label">
							<?php echo esc_html( $label ); ?>
							<small><?php esc_html_e( 'Available once Ready to Ship or a tracking number is saved', 'irix-fulfillment-sl' ); ?></small>
						</span>
					</span>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	// ─── Documents column (orders list) ──────────────────────────────────────

	public function add_documents_column( array $columns ): array {
		// Insert the Documents column just before the Actions column
		$new = [];
		foreach ( $columns as $key => $label ) {
			if ( $key === 'wc_actions' ) {
				$new['irixfsl_documents'] = __( 'Documents', 'irix-fulfillment-sl' );
			}
			$new[ $key ] = $label;
		}
		// Fallback: append if wc_actions not found
		if ( ! isset( $new['irixfsl_documents'] ) ) {
			$new['irixfsl_documents'] = __( 'Documents', 'irix-fulfillment-sl' );
		}
		return $new;
	}

	/** HPOS: receives ($column_name, WC_Order) */
	public function render_documents_column_hpos( string $column, WC_Order $order ): void {
		if ( $column !== 'irixfsl_documents' ) return;
		$this->render_document_links( $order );
	}

	/** Classic: receives ($column_name, $post_id) */
	public function render_documents_column_classic( string $column, int $post_id ): void {
		if ( $column !== 'irixfsl_documents' ) return;
		$order = wc_get_order( $post_id );
		if ( $order ) $this->render_document_links( $order );
	}

	private function render_document_links( WC_Order $order ): void {
		$links = [
			[ 'media-spreadsheet', __( 'Invoice', 'irix-fulfillment-sl' ), IRIXFSL_Invoice::invoice_url( $order->get_id() ) ],
			[ 'printer', __( 'Packing slip', 'irix-fulfillment-sl' ), IRIXFSL_Packing_Slip::packing_slip_url( [ $order->get_id() ] ) ],
		];
		if ( IRIXFSL_Helpers::is_waybill_available( $order ) ) {
			$links[] = [ 'location-alt', __( 'Waybill', 'irix-fulfillment-sl' ), IRIXFSL_Waybill::waybill_url( $order->get_id() ) ];
		}
		echo '<div class="irixfsl-icon-links">';
		foreach ( $links as [ $icon, $label, $url ] ) {
			printf(
				'<a class="irixfsl-icon-link" href="%1$s" target="_blank" rel="noopener noreferrer" title="%2$s"><span class="dashicons dashicons-%3$s" aria-hidden="true"></span><span class="screen-reader-text">%2$s</span></a>',
				esc_url( $url ),
				esc_attr( $label ),
				esc_attr( $icon )
			);
		}
		echo '</div>';
	}

	// ─── Bulk actions ─────────────────────────────────────────────────────────

	public function add_bulk_actions( array $actions ): array {
		$actions['print_invoices']     = __( 'Print Invoices', 'irix-fulfillment-sl' );
		$actions['print_packing_slips'] = __( 'Print Packing Slips', 'irix-fulfillment-sl' );
		return $actions;
	}

	public function handle_bulk_actions( $redirect, $action, $ids ) {
		switch ( $action ) {
			case 'print_invoices':
				return $this->handle_bulk_invoices( (string) $redirect, $action, (array) $ids );
			case 'print_packing_slips':
				return $this->handle_bulk_slips( (string) $redirect, $action, (array) $ids );
		}
		return $redirect;
	}

	public function handle_bulk_invoices( string $redirect, string $action, array $ids ): string {
		$url = add_query_arg( [
			'irixfsl_bulk_invoice' => '1',
			'order_ids'          => implode( ',', array_map( 'absint', $ids ) ),
			'nonce'              => wp_create_nonce( 'irixfsl_bulk_invoice' ),
		], home_url( '/' ) );
		return add_query_arg( [ 'irixfsl_bulk_invoice_redirect' => base64_encode( $url ) ], $redirect );
	}

	public function handle_bulk_slips( string $redirect, string $action, array $ids ): string {
		$url = IRIXFSL_Packing_Slip::packing_slip_url( $ids );
		return add_query_arg( [ 'irixfsl_bulk_slip_redirect' => base64_encode( $url ) ], $redirect );
	}

	public function bulk_redirect_notice(): void {
		foreach ( [ 'irixfsl_bulk_invoice_redirect', 'irixfsl_bulk_slip_redirect' ] as $param ) {
			if ( ! empty( $_GET[ $param ] ) ) { // phpcs:ignore
				$raw = base64_decode( sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) ); // phpcs:ignore
				// wp_validate_redirect() ensures the URL stays on this site,
				// preventing open-redirect abuse via a crafted base64 value.
				$url = wp_validate_redirect( $raw, '' );
				if ( $url ) {
					// Strip the redirect param from the address bar so refreshing
					// the orders list doesn't reopen the print window.
					echo '<script>window.open(' . wp_json_encode( $url ) . ', "_blank");'
						. 'try{var u=new URL(window.location.href);'
						. 'u.searchParams.delete(' . wp_json_encode( $param ) . ');'
						. 'window.history.replaceState({},"",u.toString());}catch(e){}</script>';
				}
			}
		}
	}

	// ─── Bulk invoice print page ──────────────────────────────────────────────

	public function render_bulk_invoices(): void {
		if ( ! isset( $_GET['irixfsl_bulk_invoice'] ) ) return; // phpcs:ignore
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( esc_html__( 'Unauthorized.', 'irix-fulfillment-sl' ) );

		$nonce = sanitize_text_field( $_GET['nonce'] ?? '' ); // phpcs:ignore
		if ( ! wp_verify_nonce( $nonce, 'irixfsl_bulk_invoice' ) ) wp_die( esc_html__( 'Security check failed.', 'irix-fulfillment-sl' ) );

		$ids    = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( $_GET['order_ids'] ?? '' ) ) ) ); // phpcs:ignore
		$orders = array_filter( array_map( 'wc_get_order', $ids ) );

		if ( empty( $orders ) ) wp_die( esc_html__( 'No valid orders found.', 'irix-fulfillment-sl' ) );

		$ctx       = IRIXFSL_Helpers::get_document_context();
		$s         = $ctx['settings'];
		$logo_url  = $ctx['logo_url'];
		$print_url = $ctx['print_url'];
		$bulk      = true;

		nocache_headers(); // Personal data — keep out of page/CDN caches.
		include IRIXFSL_DIR . 'templates/invoice.php';
		exit;
	}

	// ─── Assets ───────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) return;

		$order_screens = [ 'edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders' ];
		if ( ! in_array( $screen->id, $order_screens, true ) ) return;

		wp_enqueue_style( 'irixfsl-admin', IRIXFSL_URL . 'assets/css/admin.css', [], IRIXFSL_VERSION );

		// On the single order edit screen, inject a guard that prevents saving
		// to "Shipped" when no tracking/waybill number has been entered.
		// Only applies to standard orders — pickup and local delivery are exempt.
		if ( in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) {
			// Resolve fulfillment type for the current order (HPOS + classic).
			$current_order_id   = absint( $_GET['id'] ?? $_GET['post'] ?? 0 ); // phpcs:ignore
			$fulfillment_type   = 'standard';
			if ( $current_order_id ) {
				$current_order    = wc_get_order( $current_order_id );
				if ( $current_order ) {
					$fulfillment_type = IRIXFSL_Tracking::get_fulfillment_type( $current_order );
				}
			}

			if ( $fulfillment_type === 'standard' ) {
				wp_add_inline_script(
					'jquery',
					"(function($){
						$(document).on('click', '#publish, button.save_order, [name=\"save\"], [name=\"save_order\"]', function(e){
							var statusSel = $('select[name=\"order_status\"], select#order_status');
							var status    = statusSel.val();
							if ( status !== 'wc-shipped' ) return;
							var trackingNum = $('#irixfsl_tracking_number').val();
							if ( ! trackingNum || ! trackingNum.trim() ) {
								e.preventDefault();
								e.stopImmediatePropagation();
								alert( '" . esc_js( __( 'A waybill / tracking number is required before marking this order as Shipped. Please enter it in the Shipment Tracking box and try again.', 'irix-fulfillment-sl' ) ) . "' );
							}
						});
					})(jQuery);"
				);
			}
		}
	}
}
