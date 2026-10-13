<?php
/**
 * Stub harness: mocks the WordPress functions the plugin uses, then tests sanitize, the REST
 * handler and the HTML output. Run: php tests/run.php [preview-dir]
 * With a preview dir, it also writes preview HTML pages (public page, full-size page, admin).
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['wpf_options'] = array();
$GLOBALS['wpf_filters'] = array();
$GLOBALS['wpf_actions'] = array();
$GLOBALS['wpf_routes']  = array();
$GLOBALS['wpf_can']     = true;

function add_action( $hook, $fn ) { $GLOBALS['wpf_actions'][ $hook ][] = $fn; }
function add_filter( $hook, $fn ) { $GLOBALS['wpf_filters'][ $hook ][] = $fn; }
function add_shortcode( $tag, $fn ) { $GLOBALS['wpf_shortcodes'][ $tag ] = $fn; }
function add_menu_page() {}
function register_rest_route( $ns, $route, $args ) { $GLOBALS['wpf_routes'][ $ns . $route ] = $args; }
function get_option( $k, $default = false ) { return array_key_exists( $k, $GLOBALS['wpf_options'] ) ? $GLOBALS['wpf_options'][ $k ] : $default; }
function update_option( $k, $v, $autoload = null ) {
	if ( isset( $GLOBALS['wpf_filters'][ 'sanitize_option_' . $k ] ) ) {
		foreach ( $GLOBALS['wpf_filters'][ 'sanitize_option_' . $k ] as $fn ) { $v = call_user_func( $fn, $v ); }
	}
	$GLOBALS['wpf_options'][ $k ] = $v;
	return true;
}
function current_user_can( $cap ) { return $GLOBALS['wpf_can'] && 'edit_pages' === $cap; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_url_raw( $s ) { return (string) $s; }
function wp_specialchars_decode( $s, $q = ENT_NOQUOTES ) { return htmlspecialchars_decode( $s, ENT_QUOTES ); }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function wp_create_nonce() { return 'nonce123'; }
function rest_url( $p ) { return 'https://example.test/wp-json/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_the_ID() { return 42; }
function get_bloginfo( $k ) { return 'language' === $k ? 'en-US' : 'Wallace Library'; }
$GLOBALS['wpf_images'] = array( 7 => array( 'https://example.test/up/third-floor.png', 1200, 800 ) );
function wp_attachment_is_image( $id ) { return isset( $GLOBALS['wpf_images'][ $id ] ); }
function wp_get_attachment_image_src( $id, $size ) { return isset( $GLOBALS['wpf_images'][ $id ] ) ? $GLOBALS['wpf_images'][ $id ] : false; }
function wp_unslash( $v ) { return $v; }
function wp_enqueue_media() {}
function rest_ensure_response( $v ) { return $v; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
/** Close copy of WordPress's sanitize_text_field(). */
function sanitize_text_field( $s ) {
	$s = (string) $s;
	if ( false !== strpos( $s, '<' ) ) {
		$s = preg_replace_callback( '%<[^>]*?((?=<)|>|$)%', function ( $m ) {
			return false === strpos( $m[0], '>' ) ? esc_html( $m[0] ) : $m[0];
		}, $s );
		$s = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $s );
		$s = strip_tags( $s );
		$s = str_replace( "<\n", "&lt;\n", $s );
	}
	$s = trim( preg_replace( '/[\r\n\t ]+/', ' ', $s ) );
	$found = false;
	while ( preg_match( '/%[a-f0-9]{2}/i', $s, $m ) ) { $s = str_replace( $m[0], '', $s ); $found = true; }
	if ( $found ) { $s = trim( preg_replace( '/ +/', ' ', $s ) ); }
	return $s;
}
class WP_Error {
	public $code; public $data;
	function __construct( $code, $msg, $data ) { $this->code = $code; $this->data = $data; }
}
class WP_REST_Request {
	private $json;
	function __construct( $json ) { $this->json = $json; }
	function get_json_params() { return $this->json; }
}

require dirname( __DIR__ ) . '/wpfloors.php';

require __DIR__ . '/cases.php';
