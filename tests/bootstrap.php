<?php
/**
 * PHPUnit bootstrap — defines WP / WC stubs so plugin classes can be loaded
 * and tested in isolation without a full WordPress installation.
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'IRIXFSL_VERSION', '1.1.0' );
define( 'IRIXFSL_DIR', dirname( __DIR__ ) . '/' );
define( 'IRIXFSL_URL', 'https://example.com/wp-content/plugins/irix-fulfillment-sl/' );
define( 'IRIXFSL_PLUGIN_FILE', dirname( __DIR__ ) . '/irix-fulfillment-sl.php' );

require_once __DIR__ . '/../vendor/autoload.php';

// ── WordPress function stubs ──────────────────────────────────────────────

if ( ! function_exists( 'add_action' ) ) {
    function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $GLOBALS['_irixfsl_test_hooks'][ $hook ][ $priority ][] = $callback;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $GLOBALS['_irixfsl_test_hooks'][ $hook ][ $priority ][] = $callback;
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $args, string $url = '' ): string {
        if ( is_array( $args ) ) {
            $url = $url ?: 'https://example.com/';
            return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
        }
        return $url;
    }
}

if ( ! function_exists( 'remove_query_arg' ) ) {
    function remove_query_arg( $keys, string $url = '' ): string {
        $parts = parse_url( $url );
        if ( ! isset( $parts['query'] ) ) return $url;
        parse_str( $parts['query'], $params );
        foreach ( (array) $keys as $key ) {
            unset( $params[ $key ] );
        }
        $base = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? 'example.com' ) . ( $parts['path'] ?? '/' );
        return $params ? $base . '?' . http_build_query( $params ) : $base;
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( string $path = '' ): string {
        return 'https://example.com' . $path;
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( string $path = '' ): string {
        return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( string $action = '' ): string {
        return 'test_nonce_' . md5( $action );
    }
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( string $nonce, string $action = '' ): bool {
        return $nonce === 'test_nonce_' . md5( $action );
    }
}

if ( ! function_exists( 'check_admin_referer' ) ) {
    function check_admin_referer( string $action = '', string $query_arg = '' ): bool { return true; }
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( string $action = '', string $query_arg = '', bool $die = true ): bool { return true; }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '', $title = '', $args = [] ): void {
        throw new \RuntimeException( is_string( $message ) ? $message : 'wp_die called' );
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( string $capability ): bool {
        global $_irixfsl_test_user_caps;
        return isset( $_irixfsl_test_user_caps[ $capability ] ) && $_irixfsl_test_user_caps[ $capability ];
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id(): int { return 1; }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = '' ): string { return esc_html( $text ); }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( string $text, string $domain = '' ): void { echo esc_html( $text ); }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
}

if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( string $text, string $domain = '' ): string { return esc_attr( $text ); }
}

if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( string $text, string $domain = '' ): void { echo esc_attr( $text ); }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( string $url ): string { return filter_var( $url, FILTER_SANITIZE_URL ) ?: ''; }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( string $url ): string { return filter_var( $url, FILTER_SANITIZE_URL ) ?: ''; }
}

if ( ! function_exists( 'esc_js' ) ) {
    function esc_js( string $text ): string { return addslashes( $text ); }
}

if ( ! function_exists( 'esc_textarea' ) ) {
    function esc_textarea( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
}

if ( ! function_exists( '__' ) ) {
    function __( string $text, string $domain = '' ): string { return $text; }
}

if ( ! function_exists( '_x' ) ) {
    function _x( string $text, string $context, string $domain = '' ): string { return $text; }
}

if ( ! function_exists( '_e' ) ) {
    function _e( string $text, string $domain = '' ): void { echo $text; }
}

if ( ! function_exists( '_n' ) ) {
    function _n( string $single, string $plural, int $count, string $domain = '' ): string {
        return $count === 1 ? $single : $plural;
    }
}

if ( ! function_exists( '_n_noop' ) ) {
    function _n_noop( string $single, string $plural, string $domain = '' ): array {
        return [ 'singular' => $single, 'plural' => $plural, 'domain' => $domain ];
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( string $str ): string { return trim( strip_tags( $str ) ); }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( string $str ): string { return trim( strip_tags( $str ) ); }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( string $email ): string { return filter_var( $email, FILTER_SANITIZE_EMAIL ) ?: ''; }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $val ): int { return abs( intval( $val ) ); }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( string $data ): string { return $data; }
}

if ( ! function_exists( 'wc_price' ) ) {
    function wc_price( $price, array $args = [] ): string {
        $currency = $args['currency'] ?? 'LKR';
        return '<span class="woocommerce-Price-amount">' . number_format( (float) $price, 2 ) . ' ' . $currency . '</span>';
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        return is_string( $value ) ? stripslashes( $value ) : $value;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data ): string { return json_encode( $data ); }
}

/**
 * Minimal WP_Error stub, plus a controllable wp_remote_post() so tests can
 * simulate success, a WP_Error (network failure), or a non-2xx API response.
 * Set $_irixfsl_test_http_response before calling code that sends an HTTP
 * request; every call is also recorded to $_irixfsl_test_http_requests.
 */
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private string $message;
        public function __construct( string $code = '', string $message = '' ) { $this->message = $message; }
        public function get_error_message(): string { return $this->message; }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
}

if ( ! function_exists( 'wp_remote_post' ) ) {
    function wp_remote_post( string $url, array $args = [] ) {
        global $_irixfsl_test_http_response, $_irixfsl_test_http_requests;
        $_irixfsl_test_http_requests[] = [ 'url' => $url, 'args' => $args ];
        if ( $_irixfsl_test_http_response instanceof WP_Error ) return $_irixfsl_test_http_response;
        return $_irixfsl_test_http_response ?? [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];
    }
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) {
        return $response['response']['code'] ?? 0;
    }
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        return $response['body'] ?? '';
    }
}

if ( ! function_exists( 'wp_validate_redirect' ) ) {
    function wp_validate_redirect( string $url, string $default = '' ): string { return $url ?: $default; }
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
    function wp_safe_redirect( string $url, int $status = 302 ): void {}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null ): void {}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null ): void {}
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( string $option, $default = false ) {
        global $_irixfsl_test_options;
        return $_irixfsl_test_options[ $option ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( string $option, $value ): bool {
        global $_irixfsl_test_options;
        $_irixfsl_test_options[ $option ] = $value;
        return true;
    }
}

if ( ! function_exists( 'add_option' ) ) {
    function add_option( string $option, $value = '', $deprecated = '', $autoload = null ): bool {
        global $_irixfsl_test_options;
        if ( array_key_exists( $option, $_irixfsl_test_options ) ) return false;
        $_irixfsl_test_options[ $option ] = $value;
        return true;
    }
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( string $option ): bool {
        global $_irixfsl_test_options;
        unset( $_irixfsl_test_options[ $option ] );
        return true;
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( string $key ) {
        global $_irixfsl_test_transients;
        return $_irixfsl_test_transients[ $key ] ?? false;
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( string $key, $value, int $expiration = 0 ): bool {
        global $_irixfsl_test_transients;
        $_irixfsl_test_transients[ $key ] = $value;
        return true;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( string $key ): bool {
        global $_irixfsl_test_transients;
        unset( $_irixfsl_test_transients[ $key ] );
        return true;
    }
}

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( string $show = '' ): string {
        return match( $show ) {
            'name'        => 'Test Store',
            'admin_email' => 'admin@example.com',
            default       => '',
        };
    }
}

if ( ! function_exists( 'get_post_stati' ) ) {
    function get_post_stati(): array {
        global $_irixfsl_test_post_stati;
        return $_irixfsl_test_post_stati ?? [];
    }
}

if ( ! function_exists( 'register_post_status' ) ) {
    function register_post_status( string $status, array $args = [] ): void {
        global $_irixfsl_test_post_stati;
        if ( ! is_array( $_irixfsl_test_post_stati ) ) $_irixfsl_test_post_stati = [];
        $_irixfsl_test_post_stati[ $status ] = $args;
    }
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    function load_plugin_textdomain( string $domain, $deprecated = false, $path = '' ): bool { return true; }
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( string $file ): string { return dirname( $file ) . '/'; }
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( string $file ): string { return IRIXFSL_URL; }
}

if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename( string $file ): string { return basename( dirname( $file ) ) . '/' . basename( $file ); }
}

if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( string $file, $callback ): void {}
}

if ( ! function_exists( 'register_uninstall_hook' ) ) {
    function register_uninstall_hook( string $file, $callback ): void {}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
    function flush_rewrite_rules( bool $hard = true ): void {}
}

if ( ! function_exists( 'add_meta_box' ) ) {
    function add_meta_box( string $id, string $title, $callback, $screen = null, $context = '', $priority = '' ): void {}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
    function add_submenu_page( $parent, $page_title, $menu_title, $cap, $slug, $callback ): void {}
}

if ( ! function_exists( 'add_rewrite_endpoint' ) ) {
    function add_rewrite_endpoint( string $name, int $places ): void {}
}

if ( ! function_exists( 'get_query_var' ) ) {
    function get_query_var( string $var, $default = '' ) {
        global $_irixfsl_test_query_vars;
        return $_irixfsl_test_query_vars[ $var ] ?? $default;
    }
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( string $action, string $name, bool $referer = true, bool $echo = true ): string { return ''; }
}

if ( ! function_exists( 'selected' ) ) {
    function selected( $selected, $current = true, bool $echo = true ): string {
        $result = $selected == $current ? ' selected="selected"' : '';
        if ( $echo ) echo $result;
        return $result;
    }
}

if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, bool $echo = true ): string {
        $result = $checked == $current ? ' checked="checked"' : '';
        if ( $echo ) echo $result;
        return $result;
    }
}

if ( ! function_exists( 'submit_button' ) ) {
    function submit_button( string $text = '' ): void { echo '<input type="submit" value="' . esc_attr( $text ) . '">'; }
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( ...$args ): void {}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( ...$args ): void {}
}

if ( ! function_exists( 'wp_enqueue_media' ) ) {
    function wp_enqueue_media(): void {}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( ...$args ): void {}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
    function wp_add_inline_script( ...$args ): void {}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
    function wp_get_attachment_image_url( int $id, string $size = '' ): string {
        return $id ? 'https://example.com/logo-' . $id . '.png' : '';
    }
}

if ( ! function_exists( 'get_current_screen' ) ) {
    function get_current_screen(): ?object {
        global $_irixfsl_test_screen;
        return $_irixfsl_test_screen ?? null;
    }
}

if ( ! defined( 'EP_PAGES' ) ) {
    define( 'EP_PAGES', 4096 );
}

// ── WooCommerce stubs ─────────────────────────────────────────────────────

if ( ! function_exists( 'wc_get_order' ) ) {
    function wc_get_order( int $id ) {
        global $_irixfsl_test_orders;
        return $_irixfsl_test_orders[ $id ] ?? false;
    }
}

if ( ! function_exists( 'wc_get_order_statuses' ) ) {
    function wc_get_order_statuses(): array {
        return [
            'wc-pending'       => 'Pending payment',
            'wc-processing'    => 'Processing',
            'wc-on-hold'       => 'On hold',
            'wc-completed'     => 'Completed',
            'wc-cancelled'     => 'Cancelled',
            'wc-refunded'      => 'Refunded',
            'wc-failed'        => 'Failed',
            'wc-ready-to-ship' => 'Ready to Ship',
            'wc-shipped'       => 'Shipped',
            'wc-returned'      => 'Returned',
        ];
    }
}

if ( ! function_exists( 'wc_get_order_status_name' ) ) {
    function wc_get_order_status_name( string $status ): string {
        $statuses = wc_get_order_statuses();
        return $statuses[ $status ] ?? ucfirst( str_replace( [ 'wc-', '-' ], [ '', ' ' ], $status ) );
    }
}

if ( ! function_exists( 'wc_format_datetime' ) ) {
    function wc_format_datetime( $date ): string {
        return $date instanceof \WC_DateTime ? $date->date( 'Y-m-d' ) : (string) $date;
    }
}

if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
    function wc_get_account_endpoint_url( string $endpoint ): string {
        return 'https://example.com/my-account/' . $endpoint . '/';
    }
}

if ( ! function_exists( 'wc_get_template_html' ) ) {
    function wc_get_template_html( string $template, array $args = [], string $template_path = '', string $default_path = '' ): string {
        return '<div class="test-template">' . $template . '</div>';
    }
}

/**
 * Minimal WC_DateTime stub.
 */
if ( ! class_exists( 'WC_DateTime' ) ) {
    class WC_DateTime extends \DateTime {
        public function date( string $format ): string {
            return $this->format( $format );
        }
    }
}

/**
 * Minimal WC_Order_Item_Shipping stub.
 */
if ( ! class_exists( 'WC_Order_Item_Shipping' ) ) {
    class WC_Order_Item_Shipping {
        private string $method_id;

        public function __construct( string $method_id = '' ) {
            $this->method_id = $method_id;
        }

        public function get_method_id(): string {
            return $this->method_id;
        }
    }
}

/**
 * Minimal WC_Order_Item_Product stub.
 */
if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
    class WC_Order_Item_Product {
        private string $name;
        private int $quantity;
        private array $meta = [];

        public function __construct( string $name = '', int $quantity = 1 ) {
            $this->name     = $name;
            $this->quantity = $quantity;
        }

        public function get_name(): string { return $this->name; }
        public function get_quantity(): int { return $this->quantity; }

        public function get_formatted_meta_data( string $hide_prefix = '_', bool $include_all = false ): array {
            return $this->meta;
        }

        public function set_meta( array $meta ): void { $this->meta = $meta; }
    }
}

/**
 * Minimal WC_Order_Item_Fee stub.
 */
if ( ! class_exists( 'WC_Order_Item_Fee' ) ) {
    class WC_Order_Item_Fee {
        private string $name = '';
        private string $amount = '';
        private string $total = '';
        private string $tax_status = '';

        public function set_name( string $name ): void { $this->name = $name; }
        public function get_name(): string { return $this->name; }

        public function set_amount( string $amount ): void { $this->amount = $amount; }
        public function get_amount(): string { return $this->amount; }

        public function set_total( string $total ): void { $this->total = $total; }
        public function get_total(): string { return $this->total; }

        public function set_tax_status( string $status ): void { $this->tax_status = $status; }
        public function get_tax_status(): string { return $this->tax_status; }
    }
}

/**
 * Minimal WC_Order stub that supports meta, status, and shipping methods.
 */
if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {
        private int $id;
        private string $status;
        private array $meta = [];
        private array $shipping_methods = [];
        private string $order_key;
        private string $billing_email;
        private string $currency = 'LKR';
        private ?WC_DateTime $date_created = null;
        private array $items = [];
        private string $payment_method = '';
        private string $billing_first_name = 'John';
        private string $billing_last_name  = 'Doe';
        private string $billing_address_1  = '123 Main St';
        private string $billing_address_2  = '';
        private string $billing_city       = 'Colombo';
        private string $billing_state      = 'WP';
        private string $billing_postcode   = '00100';
        private string $billing_country    = 'LK';
        private string $billing_phone      = '';
        private string $shipping_first_name = '';
        private string $shipping_last_name  = '';
        private string $shipping_address_1  = '';
        private string $shipping_address_2  = '';
        private string $shipping_city       = '';
        private string $shipping_state      = '';
        private string $shipping_postcode   = '';
        private string $shipping_country    = '';

        public function __construct( int $id = 0 ) {
            $this->id        = $id;
            $this->status    = 'processing';
            $this->order_key = 'wc_order_' . md5( (string) $id );
            $this->billing_email = 'customer@example.com';
            $this->date_created  = new WC_DateTime( '2025-01-15' );
        }

        public function get_id(): int { return $this->id; }

        public function get_status(): string { return $this->status; }

        public function has_status( $status ): bool {
            if ( is_array( $status ) ) return in_array( $this->status, $status, true );
            return $this->status === $status;
        }

        public function set_status( string $status ): void { $this->status = $status; }

        public function update_status( string $status, string $note = '' ): bool {
            $old = $this->status;
            $this->status = $status;
            return true;
        }

        public function get_meta( string $key, bool $single = true ) {
            return $this->meta[ $key ] ?? '';
        }

        public function update_meta_data( string $key, $value ): void {
            $this->meta[ $key ] = $value;
        }

        public function delete_meta_data( string $key ): void { unset( $this->meta[ $key ] ); }

        public function save_meta_data(): void {}

        public function set_shipping_methods( array $methods ): void {
            $this->shipping_methods = $methods;
        }

        public function get_shipping_methods(): array { return $this->shipping_methods; }

        public function get_order_key(): string { return $this->order_key; }
        public function set_order_key( string $key ): void { $this->order_key = $key; }

        public function get_billing_email(): string { return $this->billing_email; }
        public function set_billing_email( string $email ): void { $this->billing_email = $email; }

        public function get_order_number(): string { return (string) $this->id; }

        public function get_currency(): string { return $this->currency; }

        public function get_date_created(): ?WC_DateTime { return $this->date_created; }

        public function get_items(): array { return $this->items; }
        public function set_items( array $items ): void { $this->items = $items; }

        public function get_payment_method(): string { return $this->payment_method; }
        public function set_payment_method( string $method ): void { $this->payment_method = $method; }

        public function get_billing_first_name(): string { return $this->billing_first_name; }
        public function get_billing_last_name(): string { return $this->billing_last_name; }
        public function get_billing_address_1(): string { return $this->billing_address_1; }
        public function get_billing_address_2(): string { return $this->billing_address_2; }
        public function get_billing_city(): string { return $this->billing_city; }
        public function get_billing_state(): string { return $this->billing_state; }
        public function get_billing_postcode(): string { return $this->billing_postcode; }
        public function get_billing_country(): string { return $this->billing_country; }
        public function get_billing_phone(): string { return $this->billing_phone; }
        public function set_billing_phone( string $phone ): void { $this->billing_phone = $phone; }

        public function get_shipping_first_name(): string { return $this->shipping_first_name; }
        public function get_shipping_last_name(): string { return $this->shipping_last_name; }
        public function get_shipping_address_1(): string { return $this->shipping_address_1; }
        public function get_shipping_address_2(): string { return $this->shipping_address_2; }
        public function get_shipping_city(): string { return $this->shipping_city; }
        public function get_shipping_state(): string { return $this->shipping_state; }
        public function get_shipping_postcode(): string { return $this->shipping_postcode; }
        public function get_shipping_country(): string { return $this->shipping_country; }

        public function get_formatted_billing_full_name(): string {
            return trim( $this->billing_first_name . ' ' . $this->billing_last_name );
        }

        public function get_formatted_shipping_full_name(): string {
            return trim( $this->shipping_first_name . ' ' . $this->shipping_last_name );
        }

        private array $notes = [];

        public function add_order_note( string $note, int $is_customer_note = 0, bool $added_by_user = false ): int {
            $this->notes[] = [
                'note'             => $note,
                'is_customer_note' => $is_customer_note,
                'added_by_user'    => $added_by_user,
            ];
            return count( $this->notes );
        }

        /** Test-only helper — not part of the real WC_Order API. */
        public function get_notes(): array { return $this->notes; }

        private int $save_call_count = 0;
        private bool $calculate_totals_called = false;

        public function add_item( $item ): void { $this->items[] = $item; }

        public function calculate_totals( bool $and_taxes = true ): void {
            $this->calculate_totals_called = true;
        }

        public function save() {
            $this->save_call_count++;
            return $this->id;
        }

        /** Test-only helpers — not part of the real WC_Order API. */
        public function get_save_call_count(): int { return $this->save_call_count; }
        public function was_calculate_totals_called(): bool { return $this->calculate_totals_called; }
    }
}

/**
 * Minimal WC_Email stub.
 */
if ( ! class_exists( 'WC_Email' ) ) {
    class WC_Email {
        public string $id = '';
        public bool $customer_email = false;
        public string $title = '';
        public string $description = '';
        public string $heading = '';
        public string $subject = '';
        public string $template_html = '';
        public string $template_plain = '';
        public string $template_base = '';
        public array $placeholders = [];
        protected $object = null;
        protected string $recipient = '';
        private bool $enabled = true;

        public function __construct() {}

        public function get_blogname(): string { return get_bloginfo( 'name' ); }

        public function get_heading(): string {
            return strtr( $this->heading, $this->placeholders );
        }

        public function get_subject(): string {
            return strtr( $this->subject, $this->placeholders );
        }

        public function get_recipient(): string { return $this->recipient; }
        public function is_enabled(): bool { return $this->enabled; }
        public function set_enabled( bool $enabled ): void { $this->enabled = $enabled; }

        public function get_content(): string { return $this->get_content_html(); }
        public function get_content_html(): string { return ''; }
        public function get_content_plain(): string { return ''; }
        public function get_headers(): string { return ''; }
        public function get_attachments(): array { return []; }

        public function send( string $to, string $subject, string $message, string $headers = '', array $attachments = [] ): bool {
            return true;
        }

        protected function setup_locale(): void {}
        protected function restore_locale(): void {}
    }
}

/**
 * WC_Logger stub.
 */
if ( ! class_exists( 'WC_Logger' ) ) {
    class WC_Logger {
        public array $logs = [];

        public function error( string $message, array $context = [] ): void {
            $this->logs[] = [ 'level' => 'error', 'message' => $message, 'context' => $context ];
        }

        public function warning( string $message, array $context = [] ): void {
            $this->logs[] = [ 'level' => 'warning', 'message' => $message, 'context' => $context ];
        }

        public function info( string $message, array $context = [] ): void {
            $this->logs[] = [ 'level' => 'info', 'message' => $message, 'context' => $context ];
        }
    }
}

if ( ! function_exists( 'wc_get_logger' ) ) {
    function wc_get_logger(): WC_Logger {
        global $_irixfsl_test_logger;
        if ( ! $_irixfsl_test_logger ) $_irixfsl_test_logger = new WC_Logger();
        return $_irixfsl_test_logger;
    }
}

/**
 * Minimal WC_Countries stub.
 */
if ( ! class_exists( 'WC_Countries' ) ) {
    class WC_Countries {
        public function get_formatted_address( array $args = [] ): string {
            $parts = array_filter( [
                $args['address_1'] ?? '',
                $args['address_2'] ?? '',
                $args['city'] ?? '',
                $args['state'] ?? '',
                $args['postcode'] ?? '',
                $args['country'] ?? '',
            ] );
            return implode( ', ', $parts );
        }
    }
}

/**
 * Minimal stand-in for a registered WC_Shipping_Method type (e.g. flat_rate,
 * local_pickup) as returned by WC()->shipping()->get_shipping_methods().
 */
if ( ! class_exists( 'WC_Shipping_Method_Stub' ) ) {
    class WC_Shipping_Method_Stub {
        public string $id;
        public string $method_title;

        public function __construct( string $id, string $method_title ) {
            $this->id           = $id;
            $this->method_title = $method_title;
        }

        public function get_method_title(): string { return $this->method_title; }
    }
}

if ( ! class_exists( 'WC_Shipping' ) ) {
    class WC_Shipping {
        public function get_shipping_methods(): array {
            global $_irixfsl_test_shipping_methods;
            return $_irixfsl_test_shipping_methods ?? [];
        }
    }
}

if ( ! class_exists( 'WooCommerce' ) ) {
    class WooCommerce {
        private static ?self $instance = null;
        private ?object $mailer_instance = null;
        private ?WC_Shipping $shipping_instance = null;
        public ?WC_Countries $countries = null;

        public function __construct() {
            $this->countries = new WC_Countries();
        }

        public static function instance(): self {
            if ( null === self::$instance ) self::$instance = new self();
            return self::$instance;
        }

        public function mailer(): object {
            if ( ! $this->mailer_instance ) {
                $this->mailer_instance = new class {
                    private array $emails = [];

                    public function get_emails(): array { return $this->emails; }
                    public function set_emails( array $emails ): void { $this->emails = $emails; }
                };
            }
            return $this->mailer_instance;
        }

        public function shipping(): WC_Shipping {
            if ( ! $this->shipping_instance ) $this->shipping_instance = new WC_Shipping();
            return $this->shipping_instance;
        }
    }
}

if ( ! function_exists( 'WC' ) ) {
    function WC(): WooCommerce {
        return WooCommerce::instance();
    }
}

// ── Load plugin classes ───────────────────────────────────────────────────

require_once IRIXFSL_DIR . 'includes/trait-singleton.php';
require_once IRIXFSL_DIR . 'includes/class-helpers.php';
require_once IRIXFSL_DIR . 'includes/class-settings.php';
require_once IRIXFSL_DIR . 'includes/class-tracking.php';
require_once IRIXFSL_DIR . 'includes/class-whatsapp.php';
require_once IRIXFSL_DIR . 'includes/class-order-statuses.php';
require_once IRIXFSL_DIR . 'includes/class-order-reason.php';
require_once IRIXFSL_DIR . 'includes/class-invoice.php';
require_once IRIXFSL_DIR . 'includes/class-packing-slip.php';
require_once IRIXFSL_DIR . 'includes/class-waybill.php';
require_once IRIXFSL_DIR . 'includes/class-customer-portal.php';
require_once IRIXFSL_DIR . 'includes/class-admin.php';
require_once IRIXFSL_DIR . 'includes/class-email-tracking.php';

// ── Stubs added for 1.2.0 ───────────────────────────────────────────────
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( string $hook, $value, ...$args ) { return $value; }
}
if ( ! function_exists( 'nocache_headers' ) ) {
    function nocache_headers(): void {}
}
if ( ! function_exists( 'as_enqueue_async_action' ) ) {
    /** Records enqueued actions in $GLOBALS['_irixfsl_test_async'] instead of running them. */
    function as_enqueue_async_action( string $hook, array $args = [], string $group = '', bool $unique = false ): int {
        $GLOBALS['_irixfsl_test_async'][] = [ $hook, $args ];
        return 1;
    }
}
if ( ! function_exists( 'wc_get_page_permalink' ) ) {
    function wc_get_page_permalink( string $page ): string {
        return 'https://example.com/my-account/';
    }
}
if ( ! function_exists( 'wc_get_endpoint_url' ) ) {
    function wc_get_endpoint_url( string $endpoint, string $value = '', string $permalink = '' ): string {
        return $permalink . $endpoint . '/' . ( $value !== '' ? $value . '/' : '' );
    }
}
if ( ! function_exists( 'as_next_scheduled_action' ) ) {
    function as_next_scheduled_action( string $hook, ?array $args = null, string $group = '' ) {
        foreach ( $GLOBALS['_irixfsl_test_async'] ?? [] as [ $h, $a ] ) {
            if ( $h === $hook && ( $args === null || $a === $args ) ) return true;
        }
        return false;
    }
}
if ( ! function_exists( 'wc_print_notice' ) ) {
    function wc_print_notice( string $message, string $type = 'success' ): void {
        echo '<div class="woocommerce-' . $type . '">' . $message . '</div>';
    }
}
if ( ! function_exists( 'disabled' ) ) {
    function disabled( $disabled, $current = true, bool $display = true ): string {
        $r = (string) $disabled === (string) $current ? ' disabled="disabled"' : '';
        if ( $display ) echo $r;
        return $r;
    }
}
