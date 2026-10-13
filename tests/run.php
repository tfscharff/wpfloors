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

/* ---------------- tiny test runner ---------------- */

$fails = 0; $passes = 0;
function ok( $cond, $msg ) {
	global $fails, $passes;
	if ( $cond ) { $passes++; } else { $fails++; echo "FAIL: $msg\n"; }
}
function lbl( $over = array() ) {
	return array_merge( array( 'id' => 'a1', 'name' => 'Room', 'type' => 'room', 'note' => '', 'x' => 10, 'y' => 20 ), $over );
}

/* ---------------- sanitize ---------------- */

$s = wpfloors_sanitize( null );
ok( array_keys( $s ) === array( 'main', 'periodicals', 'stacks', 'first-mezzanine', 'second-mezzanine' ), 'all five floors, in order' );
ok( array() === $s['main'], 'empty floor is an empty list' );
ok( wpfloors_sanitize( 'junk' ) === $s && wpfloors_sanitize( 42 ) === $s, 'junk input gives empty floors' );

$s = wpfloors_sanitize( array( 'main' => array( lbl() ), 'basement' => array( lbl() ) ) );
ok( 1 === count( $s['main'] ) && ! isset( $s['basement'] ), 'unknown floor dropped' );
ok( $s['main'][0] === array( 'id' => 'a1', 'name' => 'Room', 'type' => 'room', 'note' => '', 'x' => 10.0, 'y' => 20.0 ), 'clean label kept as is' );

$s = wpfloors_sanitize( array( 'main' => array(
	lbl( array( 'name' => '<script>alert(1)</script>Reading <b>Room</b>' ) ),
	lbl( array( 'id' => 'a2', 'name' => "  Two\nlines\t here " ) ),
	lbl( array( 'id' => 'a3', 'name' => 'a < b & c', 'note' => '<img src=x onerror=alert(1)>Wait here' ) ),
	lbl( array( 'id' => 'a4', 'name' => '&lt;script&gt;' ) ),
) ) );
ok( 'Reading Room' === $s['main'][0]['name'], 'tags and script stripped from name: ' . $s['main'][0]['name'] );
ok( 'Two lines here' === $s['main'][1]['name'], 'whitespace collapsed' );
ok( 'a < b & c' === $s['main'][2]['name'], 'plain < and & kept as text: ' . $s['main'][2]['name'] );
ok( 'Wait here' === $s['main'][2]['note'], 'tags stripped from note' );
ok( '<script>' === $s['main'][3]['name'], 'typed entities stored as text (escaped on output)' );

$s = wpfloors_sanitize( array( 'main' => array(
	lbl( array( 'id' => 'b1', 'name' => '' ) ),
	lbl( array( 'id' => 'b2', 'name' => '<b></b>' ) ),
	lbl( array( 'id' => 'b3', 'type' => 'toilet' ) ),
	lbl( array( 'id' => 'b4', 'type' => array( 'room' ) ) ),
	lbl( array( 'id' => 'b5', 'x' => 'left' ) ),
	lbl( array( 'id' => 'b6', 'y' => null ) ),
	lbl( array( 'id' => 'b7', 'x' => true ) ),
	lbl( array( 'id' => 'b8', 'name' => array( 'x' ) ) ),
	'not a label',
	array(),
) ) );
ok( array() === $s['main'], 'bad rows dropped (empty name, bad type, bad position, non-arrays)' );

$s = wpfloors_sanitize( array( 'stacks' => array(
	lbl( array( 'x' => -5, 'y' => 250 ) ),
	lbl( array( 'id' => 'c2', 'x' => '33.33333', 'y' => '1e1' ) ),
) ) );
ok( 0.0 === $s['stacks'][0]['x'] && 100.0 === $s['stacks'][0]['y'], 'positions clamped to 0–100' );
ok( 33.33 === $s['stacks'][1]['x'] && 10.0 === $s['stacks'][1]['y'], 'numeric strings rounded to 2 places' );

$s = wpfloors_sanitize( array( 'main' => array(
	lbl( array( 'id' => 'Dup!' ) ), lbl( array( 'id' => 'dup' ) ), lbl( array( 'id' => '' ) ), lbl( array( 'id' => 5 ) ), lbl( array( 'id' => str_repeat( 'z', 80 ) ) ),
) ) );
$ids = array_column( $s['main'], 'id' );
ok( 'dup' === $ids[0], 'id cleaned to [a-z0-9-]' );
ok( 5 === count( array_unique( $ids ) ) && ! in_array( '', $ids, true ), 'ids unique and never empty' );
ok( 40 === strlen( $ids[4] ), 'long id trimmed to 40' );

$s = wpfloors_sanitize( array( 'main' => array( lbl( array( 'name' => str_repeat( 'é', 150 ), 'note' => str_repeat( 'n', 400 ) ) ) ) ) );
ok( 100 === preg_match_all( '/./us', $s['main'][0]['name'] ) && 300 === strlen( $s['main'][0]['note'] ), 'name 100 / note 300 characters max (multibyte safe)' );

$many = array();
for ( $i = 0; $i < 400; $i++ ) { $many[] = lbl( array( 'id' => 'm' . $i ) ); }
$s = wpfloors_sanitize( array( 'main' => $many ) );
ok( WPFLOORS_MAX_LABELS === count( $s['main'] ), 'label count capped per floor' );

foreach ( array_keys( wpfloors_types() ) as $t ) {
	$s = wpfloors_sanitize( array( 'main' => array( lbl( array( 'type' => $t ) ) ) ) );
	ok( 1 === count( $s['main'] ), "type $t accepted" );
}
$clean = wpfloors_sanitize( array( 'main' => array( lbl(), lbl( array( 'id' => 'q2', 'name' => 'a < b' ) ) ) ) );
ok( wpfloors_sanitize( $clean ) === $clean, 'sanitize is idempotent' );

/* ---------------- REST ---------------- */

$route = $GLOBALS['wpf_routes']['wpfloors/v1/labels'] ?? null;
foreach ( $GLOBALS['wpf_actions']['rest_api_init'] as $fn ) { $fn(); }
$route = $GLOBALS['wpf_routes']['wpfloors/v1/labels'];
ok( 'POST' === $route['methods'], 'route is POST' );
$GLOBALS['wpf_can'] = false;
ok( false === call_user_func( $route['permission_callback'] ), 'needs edit_pages' );
$GLOBALS['wpf_can'] = true;
ok( true === call_user_func( $route['permission_callback'] ), 'editor allowed' );

foreach ( array( null, 'x', array(), array( 'floors' => 'x' ), array( 'main' => array() ) ) as $bad ) {
	$r = wpfloors_rest_save( new WP_REST_Request( $bad ) );
	ok( $r instanceof WP_Error && 400 === $r->data['status'], 'bad body rejected with 400' );
}
$GLOBALS['wpf_options'] = array();
$r = wpfloors_rest_save( new WP_REST_Request( array( 'floors' => array(
	'main'   => array( lbl( array( 'name' => '<i>Info</i> Desk', 'type' => 'room' ) ), lbl( array( 'id' => 'x2', 'type' => 'nope' ) ) ),
	'stacks' => array( lbl( array( 'id' => 'e1', 'name' => 'North exit', 'type' => 'exit', 'note' => 'Push bar', 'x' => 50, 'y' => 5 ) ) ),
) ) ) );
ok( is_array( $r ) && true === $r['ok'], 'save ok' );
ok( 1 === count( $r['floors']['main'] ) && 'Info Desk' === $r['floors']['main'][0]['name'], 'save returns sanitized data' );
ok( $GLOBALS['wpf_options'][ WPFLOORS_OPTION ] === $r['floors'], 'stored option equals returned data' );
update_option( WPFLOORS_OPTION, array( 'main' => array( lbl( array( 'name' => '<b>x</b>' ) ) ) ) );
ok( 'x' === $GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['main'][0]['name'], 'any update_option goes through sanitize' );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ] = 'corrupt';
ok( array() === wpfloors_get()['main'], 'corrupt option reads as empty floors' );

/* ---------------- shortcode output ---------------- */

$GLOBALS['wpf_options'] = array();
$html = wpfloors_shortcode();
ok( 5 === substr_count( $html, 'Nothing listed on this floor yet.' ), 'all five floors empty at launch' );
ok( 5 === substr_count( $html, '<h2 ' ), 'an H2 per floor' );
ok( 5 === substr_count( $html, 'role="img"' ) && 5 === substr_count( $html, 'Everything shown is listed below.' ), 'each drawing is role=img with a short label' );
ok( 5 === substr_count( $html, 'Open full-size map' ), 'full-size link per floor' );
ok( false === strpos( $html, '<script' ), 'public page has no JavaScript' );
ok( false === strpos( $html, '<!--' ), 'source comments stripped' );
ok( false === stripos( $html, 'tabindex' ) && false === strpos( $html, '<button' ) && false === strpos( $html, '<input' ), 'nothing interactive on the map' );
ok( false === strpos( $html, '<text' ), 'drawings have no text when there are no labels' );
ok( false === strpos( $html, 'class="wpfloors-key"' ), 'no key when a floor has no labels' );
$ids = array();
preg_match_all( '/\sid="([^"]+)"/', $html, $m );
ok( count( $m[1] ) === count( array_unique( $m[1] ) ), 'no duplicate ids' );
$order = array();
foreach ( array( 'Main Level', 'Periodicals Level', 'Stacks Level', 'First Mezzanine', 'Second Mezzanine' ) as $n ) { $order[] = strpos( $html, '>' . $n . '</h2>' ); }
$sorted = $order; sort( $sorted );
ok( $order === $sorted && ! in_array( false, $order, true ), 'floors in the agreed order' );

$evil = '"><script>alert(1)</script>';
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ] = array( 'main' => array(
	array( 'id' => 'r1', 'name' => 'Reference Desk', 'type' => 'room', 'note' => 'Ask here', 'x' => 40, 'y' => 30 ),
	array( 'id' => 'r2', 'name' => 'a < b "q"', 'type' => 'collection', 'note' => '', 'x' => 90, 'y' => 30 ),
	array( 'id' => 'r3', 'name' => 'East stairs', 'type' => 'stairs', 'note' => '', 'x' => 70, 'y' => 30 ),
	array( 'id' => 'r4', 'name' => 'Elevator', 'type' => 'elevator', 'note' => '', 'x' => 72, 'y' => 60 ),
	array( 'id' => 'r5', 'name' => 'Rescue area', 'type' => 'rescue', 'note' => "Wait here for help if you can't use the stairs.", 'x' => 20, 'y' => 80 ),
	array( 'id' => 'r6', 'name' => '&lt;script&gt;', 'type' => 'kit', 'note' => $evil, 'x' => 5, 'y' => 5 ),
	array( 'id' => 'r7', 'name' => 'Archives', 'type' => 'collection', 'note' => '', 'x' => 10, 'y' => 30 ),
) );
$html = wpfloors_shortcode();
ok( 4 === substr_count( $html, 'Nothing listed on this floor yet.' ), 'only the empty floors say nothing listed' );
ok( false === strpos( $html, '<script' ), 'stray HTML never becomes markup' );
ok( false !== strpos( $html, '&lt;script&gt;' ), 'stray HTML shown as text' );
ok( false !== strpos( $html, 'a &lt; b &quot;q&quot;' ), 'quotes and < escaped' );
ok( 1 === substr_count( $html, '<h3>Rooms and services</h3>' ) && 1 === substr_count( $html, '<h3>Collections</h3>' )
	&& 1 === substr_count( $html, '<h3>Restrooms, elevators and stairs</h3>' ) && 1 === substr_count( $html, '<h3>Emergency: exits, rescue areas and kits</h3>' ), 'list grouped under the four headings' );
ok( strpos( $html, '<strong>a &lt; b' ) < strpos( $html, '<strong>Archives' ), 'groups sorted by name' );
ok( false !== strpos( $html, "Wait here for help if you can&#039;t use the stairs." ), 'note in the list' );
ok( false !== strpos( $html, 'Stairs: <strong>East stairs</strong>' ), 'mixed groups say the type' );
ok( false !== strpos( $html, '<strong>Reference Desk</strong>. Ask here' ), 'single-type groups just give name and note' );
ok( 1 === substr_count( $html, 'class="wpfloors-labels" aria-hidden="true"><g class="mk"' ), 'labels drawn in an aria-hidden group' );
ok( 7 === substr_count( $html, '<g class="mk" transform' ), 'seven markers drawn' );
ok( 3 === substr_count( $html, 'class="nm end"' ) && false !== strpos( $html, '>a &lt; b &quot;q&quot;</text>' ), 'names near the right edge are anchored at the end' );
ok( 1 === substr_count( $html, 'class="wpfloors-key"' ), 'key under the floor with labels' );
ok( 1 === preg_match( '/wpfloors_map=main&amp;wpfloors_from=42|wpfloors_map=main&wpfloors_from=42/', $html ), 'full-size link points at the floor' );

/* ---------------- full-size page ---------------- */

$full = wpfloors_full_page( 'main', 'https://example.test/about/floor-maps/' );
ok( 0 === strpos( $full, '<!doctype html><html lang="en-US">' ), 'full page is a document with a language' );
ok( false !== strpos( $full, '<title>Main Level floor map | Wallace Library</title>' ), 'full page title' );
ok( 1 === substr_count( $full, '<h1>Main Level</h1>' ) && 4 === substr_count( $full, '<h2>' ), 'full page H1, groups as H2' );
ok( false !== strpos( $full, 'Back to all floor maps' ) && false === strpos( $full, 'Open full-size map' ), 'back link, no self link' );
ok( false === strpos( $full, '<script' ), 'full page has no JavaScript' );

/* ---------------- admin ---------------- */

ob_start(); wpfloors_admin_page(); $admin = ob_get_clean();
ok( false !== strpos( $admin, 'window.WPFLOORS_ADMIN=' ), 'admin config inline' );
ok( false === strpos( $admin, '</script>alert' ) && false === strpos( substr( $admin, strpos( $admin, 'WPFLOORS_ADMIN=' ), 4000 ), '<script>alert' ), 'config JSON cannot close the script tag' );
ok( 5 === substr_count( $admin, 'class="wpfloors-drawing"' ), 'admin has every drawing' );
$GLOBALS['wpf_can'] = false;
ob_start(); wpfloors_admin_page(); ok( '' === ob_get_clean(), 'admin page empty without edit_pages' );
$GLOBALS['wpf_can'] = true;

/* ---------------- previews ---------------- */

if ( isset( $argv[1] ) ) {
	$dir = rtrim( $argv[1], '/\\' );
	$wrap = function ( $title, $body, $extra = '' ) {
		return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . '</title>'
			. '<style>body{margin:0;padding:16px;font:16px/1.5 Georgia,serif;color:#222;background:#fff}.page{max-width:760px;margin:0 auto}' . $extra . '</style></head><body><main class="page"><h1>Floor Maps</h1>' . $body . '</main></body></html>';
	};
	file_put_contents( "$dir/preview-labels.html", $wrap( 'Floor Maps preview', $html ) );
	$GLOBALS['wpf_options'] = array();
	file_put_contents( "$dir/preview-empty.html", $wrap( 'Floor Maps preview', wpfloors_shortcode() ) );
	$GLOBALS['wpf_options'][ WPFLOORS_OPTION ] = array( 'stacks' => array( array( 'id' => 's1', 'name' => 'Quartos', 'type' => 'collection', 'note' => '', 'x' => 72, 'y' => 6 ) ) );
	file_put_contents( "$dir/preview-full.html", wpfloors_full_page( 'stacks', 'https://example.test/' ) );
	echo "previews written to $dir\n";
}

echo "$passes passed, $fails failed\n";
exit( $fails ? 1 : 0 );
