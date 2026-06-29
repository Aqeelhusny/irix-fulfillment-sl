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

		// Bulk actions
		add_filter( 'bulk_actions-edit-shop_order',                                           [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_action-edit-shop_order-print_invoices',                      [ $this, 'handle_bulk_invoices' ], 10, 3 );
		add_filter( 'handle_bulk_action-edit-shop_order-print_packing_slips',                 [ $this, 'handle_bulk_slips' ], 10, 3 );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders',                                [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_action-woocommerce_page_wc-orders-print_invoices',           [ $this, 'handle_bulk_invoices' ], 10, 3 );
		add_filter( 'handle_bulk_action-woocommerce_page_wc-orders-print_packing_slips',      [ $this, 'handle_bulk_slips' ], 10, 3 );
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
		?>
		<div class="irixfsl-doc-buttons">
			<a href="<?php echo esc_url( $invoice_url ); ?>"
			   target="_blank"
			   rel="noopener noreferrer"
			   class="button button-primary irixfsl-doc-btn">
				<span class="dashicons dashicons-media-spreadsheet"></span>
				<?php esc_html_e( 'Download Invoice', 'irix-fulfillment-sl' ); ?>
			</a>
			<a href="<?php echo esc_url( $slip_url ); ?>"
			   target="_blank"
			   rel="noopener noreferrer"
			   class="button irixfsl-doc-btn">
				<span class="dashicons dashicons-printer"></span>
				<?php esc_html_e( 'Print Packing Slip', 'irix-fulfillment-sl' ); ?>
			</a>
			<?php if ( $waybill_ok ) : ?>
				<a href="<?php echo esc_url( IRIXFSL_Waybill::waybill_url( $order->get_id() ) ); ?>"
				   target="_blank"
				   rel="noopener noreferrer"
				   class="button irixfsl-doc-btn irixfsl-doc-btn--waybill">
					<span class="dashicons dashicons-location-alt"></span>
					<?php esc_html_e( 'Print Waybill', 'irix-fulfillment-sl' ); ?>
				</a>
			<?php else : ?>
				<button type="button"
				        class="button irixfsl-doc-btn irixfsl-doc-btn--waybill-disabled"
				        disabled
				        title="<?php esc_attr_e( 'Mark order as Ready to Ship or save a tracking number to enable the waybill.', 'irix-fulfillment-sl' ); ?>">
					<span class="dashicons dashicons-location-alt"></span>
					<?php esc_html_e( 'Print Waybill', 'irix-fulfillment-sl' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}

	// ─── Documents column (orders list) ──────────────────────────────────────

	public function add_documents_column( array $columns ): array {
		// Insert the Documents column just before the Actions column
		$new = [];
		foreach ( $columns as $key => $label ) {
			if ( $key === 'wc_actions' ) {
				$new['irixfsl_documents'] = __( 'Fulfillment', 'irix-fulfillment-sl' );
			}
			$new[ $key ] = $label;
		}
		// Fallback: append if wc_actions not found
		if ( ! isset( $new['irixfsl_documents'] ) ) {
			$new['irixfsl_documents'] = __( 'Fulfillment', 'irix-fulfillment-sl' );
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
		$invoice_url  = IRIXFSL_Invoice::invoice_url( $order->get_id() );
		$slip_url     = IRIXFSL_Packing_Slip::packing_slip_url( [ $order->get_id() ] );
		$waybill_ok   = IRIXFSL_Helpers::is_waybill_available( $order );
		?>
		<div class="irixfsl-list-doc-links">
			<a href="<?php echo esc_url( $invoice_url ); ?>"
			   target="_blank"
			   rel="noopener noreferrer"
			   class="irixfsl-list-link irixfsl-list-link--invoice"
			   title="<?php esc_attr_e( 'Download Invoice', 'irix-fulfillment-sl' ); ?>">
				<span class="dashicons dashicons-media-spreadsheet"></span>
				<?php esc_html_e( 'Invoice', 'irix-fulfillment-sl' ); ?>
			</a>
			<a href="<?php echo esc_url( $slip_url ); ?>"
			   target="_blank"
			   rel="noopener noreferrer"
			   class="irixfsl-list-link irixfsl-list-link--slip"
			   title="<?php esc_attr_e( 'Print Packing Slip', 'irix-fulfillment-sl' ); ?>">
				<span class="dashicons dashicons-printer"></span>
				<?php esc_html_e( 'Packing Slip', 'irix-fulfillment-sl' ); ?>
			</a>
			<?php if ( $waybill_ok ) : ?>
				<a href="<?php echo esc_url( IRIXFSL_Waybill::waybill_url( $order->get_id() ) ); ?>"
				   target="_blank"
				   rel="noopener noreferrer"
				   class="irixfsl-list-link irixfsl-list-link--waybill"
				   title="<?php esc_attr_e( 'Print Waybill', 'irix-fulfillment-sl' ); ?>">
					<span class="dashicons dashicons-location-alt"></span>
					<?php esc_html_e( 'Waybill', 'irix-fulfillment-sl' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	// ─── Bulk actions ─────────────────────────────────────────────────────────

	public function add_bulk_actions( array $actions ): array {
		$actions['print_invoices']     = __( 'Print Invoices', 'irix-fulfillment-sl' );
		$actions['print_packing_slips'] = __( 'Print Packing Slips', 'irix-fulfillment-sl' );
		return $actions;
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
					echo '<script>window.open(' . wp_json_encode( $url ) . ', "_blank");</script>';
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
						$(document).on('click', '#publish, [name=\"save_order\"]', function(e){
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
