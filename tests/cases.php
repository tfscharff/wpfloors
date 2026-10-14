<?php
/** Test cases. Run through tests/run.php, which loads the WordPress stubs and the plugin. */

$fails = 0; $passes = 0;
function ok( $cond, $msg ) {
	global $fails, $passes;
	if ( $cond ) { $passes++; } else { $fails++; echo "FAIL: $msg\n"; }
}
function lbl( $over = array() ) {
	return array_merge( array( 'id' => 'a1', 'name' => 'Room', 'type' => 'room', 'note' => '', 'x' => 10, 'y' => 20 ), $over );
}
function fl( $over = array() ) {
	return array_merge( array( 'slug' => 'main', 'name' => 'Main Level', 'image' => 7 ), $over );
}
/** Sanitize labels on one built-in floor; return that floor's clean labels. */
function labs( $rows ) {
	$s = wpfloors_sanitize( array( 'floors' => array( fl() ), 'labels' => array( 'main' => $rows ) ) );
	return $s['labels']['main'];
}

/* ---------------- floors ---------------- */

$s = wpfloors_sanitize( array( 'floors' => array(), 'labels' => array() ) );
ok( array() === $s['floors'] && array() === $s['labels'], 'new site: no floors' );
$GLOBALS['wpf_options'] = array();
ok( array() === wpfloors_get()['floors'], 'missing option reads as no floors' );

ok( array() === wpfloors_sanitize( array( 'main' => array( lbl() ), 'stacks' => array() ) )['floors'], 'old 1.0 data (labels only) gives no floors' );
ok( array() === wpfloors_sanitize( 'junk' )['floors'] && array() === wpfloors_sanitize( null )['floors'], 'junk input gives no floors' );
$s = wpfloors_sanitize( array( 'floors' => array( array( 'slug' => 'main', 'name' => 'Main Level', 'map' => 'main', 'image' => 0 ) ), 'labels' => array() ) );
ok( array( array( 'slug' => 'main', 'name' => 'Main Level', 'image' => 0 ) ) === $s['floors'], '1.1 built-in floors keep their name but have no map' );

$s = wpfloors_sanitize( array( 'floors' => array(
	fl( array( 'slug' => '', 'name' => '<b>Third</b> Floor!', 'image' => '7' ) ),
	fl( array( 'slug' => 'third-floor', 'name' => 'Another', 'image' => 7 ) ),
	fl( array( 'slug' => 'Main', 'name' => 'Main', 'image' => 'x' ) ),
	fl( array( 'slug' => '', 'name' => '!!!', 'image' => -3 ) ),
	fl( array( 'name' => '' ) ),
	fl( array( 'name' => array( 'x' ) ) ),
	'not a floor',
) ) );
$f = $s['floors'];
ok( 4 === count( $f ), 'floors without a name dropped' );
ok( 'third-floor' === $f[0]['slug'] && 'Third Floor!' === $f[0]['name'], 'slug made from the name; tags stripped from the name' );
ok( 'third-floor-2' === $f[1]['slug'], 'duplicate slugs made unique' );
ok( 'main' === $f[2]['slug'] && 0 === $f[2]['image'], 'slug cleaned; non-numeric image becomes none' );
ok( 7 === $f[0]['image'], 'floor keeps its SVG attachment ID' );
ok( 'floor' === $f[3]['slug'] && 0 === $f[3]['image'], 'unsluggable name gets "floor"; negative image becomes none' );
ok( array( 'slug', 'name', 'image' ) === array_keys( $f[0] ), 'a floor stores only slug, name and image' );
ok( array_keys( $s['labels'] ) === array_column( $f, 'slug' ), 'a label list for every floor' );

$many = array();
for ( $i = 0; $i < 40; $i++ ) { $many[] = fl( array( 'slug' => 'f' . $i, 'name' => 'F' . $i ) ); }
ok( WPFLOORS_MAX_FLOORS === count( wpfloors_sanitize( array( 'floors' => $many, 'labels' => array() ) )['floors'] ), 'floor count capped' );
$s = wpfloors_sanitize( array( 'floors' => array( fl() ), 'labels' => array( 'main' => array( lbl() ), 'gone' => array( lbl() ) ) ) );
ok( ! isset( $s['labels']['gone'] ), 'labels for a deleted floor dropped' );

/* ---------------- labels ---------------- */

ok( labs( array( lbl() ) ) === array( array( 'id' => 'a1', 'name' => 'Room', 'type' => 'room', 'note' => '', 'number' => '', 'show' => true, 'pos' => 'right', 'show_icon' => true, 'x' => 10.0, 'y' => 20.0 ) ), 'clean label kept as is' );

/* "Show name on the map": a yes/no value, or the type's default when missing or unclear. */
$l = labs( array(
	lbl( array( 'id' => 's1', 'type' => 'room' ) ),
	lbl( array( 'id' => 's2', 'type' => 'collection' ) ),
	lbl( array( 'id' => 's3', 'type' => 'restroom' ) ),
	lbl( array( 'id' => 's4', 'type' => 'exit' ) ),
	lbl( array( 'id' => 's5', 'type' => 'room', 'show' => false ) ),
	lbl( array( 'id' => 's6', 'type' => 'kit', 'show' => true ) ),
	lbl( array( 'id' => 's7', 'type' => 'room', 'show' => '0' ) ),
	lbl( array( 'id' => 's8', 'type' => 'restroom', 'show' => 1 ) ),
	lbl( array( 'id' => 's9', 'type' => 'room', 'show' => 'banana' ) ),
	lbl( array( 'id' => 's10', 'type' => 'elevator', 'show' => array( true ) ) ),
) );
ok( array( true, true, false, true, false, true, false, true, true, false ) === array_column( $l, 'show' ), 'show defaults by type (rooms, collections and entrances yes; restrooms no) and accepts yes/no values' );
$l = labs( array(
	lbl( array( 'name' => '<script>alert(1)</script>Reading <b>Room</b>' ) ),
	lbl( array( 'id' => 'a2', 'name' => "  Two\nlines\t here " ) ),
	lbl( array( 'id' => 'a3', 'name' => 'a < b & c', 'note' => '<img src=x onerror=alert(1)>Wait here' ) ),
	lbl( array( 'id' => 'a4', 'name' => '&lt;script&gt;' ) ),
) );
ok( 'Reading Room' === $l[0]['name'], 'tags and script stripped from name' );
ok( 'Two lines here' === $l[1]['name'], 'whitespace collapsed' );
ok( 'a < b & c' === $l[2]['name'], 'plain < and & kept as text' );
ok( 'Wait here' === $l[2]['note'], 'tags stripped from note' );
ok( '<script>' === $l[3]['name'], 'typed entities stored as text (escaped on output)' );
ok( array() === labs( array(
	lbl( array( 'id' => 'b1', 'name' => '' ) ), lbl( array( 'id' => 'b2', 'name' => '<b></b>' ) ),
	lbl( array( 'id' => 'b3', 'type' => 'toilet' ) ), lbl( array( 'id' => 'b4', 'type' => array( 'room' ) ) ),
	lbl( array( 'id' => 'b5', 'x' => 'left' ) ), lbl( array( 'id' => 'b6', 'y' => null ) ),
	lbl( array( 'id' => 'b7', 'x' => true ) ), lbl( array( 'id' => 'b8', 'name' => array( 'x' ) ) ), 'not a label', array(),
) ), 'bad rows dropped (empty name, bad type, bad position, non-arrays)' );
$l = labs( array( lbl( array( 'x' => -5, 'y' => 250 ) ), lbl( array( 'id' => 'c2', 'x' => '33.33333', 'y' => '1e1' ) ) ) );
ok( 0.0 === $l[0]['x'] && 100.0 === $l[0]['y'], 'positions clamped to 0–100' );
ok( 33.33 === $l[1]['x'] && 10.0 === $l[1]['y'], 'numeric strings rounded to 2 places' );
$ids = array_column( labs( array( lbl( array( 'id' => 'Dup!' ) ), lbl( array( 'id' => 'dup' ) ), lbl( array( 'id' => '' ) ), lbl( array( 'id' => 5 ) ), lbl( array( 'id' => str_repeat( 'z', 80 ) ) ) ) ), 'id' );
ok( 'dup' === $ids[0] && 5 === count( array_unique( $ids ) ) && ! in_array( '', $ids, true ) && 40 === strlen( $ids[4] ), 'ids cleaned, unique, never empty, max 40' );
$l = labs( array( lbl( array( 'name' => str_repeat( 'é', 150 ), 'note' => str_repeat( 'n', 400 ) ) ) ) );
ok( 100 === preg_match_all( '/./us', $l[0]['name'] ) && 300 === strlen( $l[0]['note'] ), 'name 100 / note 300 characters max (multibyte safe)' );
$many = array();
for ( $i = 0; $i < 400; $i++ ) { $many[] = lbl( array( 'id' => 'm' . $i ) ); }
ok( WPFLOORS_MAX_LABELS === count( labs( $many ) ), 'label count capped per floor' );
foreach ( array_keys( wpfloors_types() ) as $t ) {
	ok( 1 === count( labs( array( lbl( array( 'type' => $t ) ) ) ) ), "type $t accepted" );
}
$clean = wpfloors_sanitize( array( 'floors' => array( fl(), fl( array( 'slug' => '', 'name' => 'Third', 'image' => 7 ) ) ), 'labels' => array( 'main' => array( lbl(), lbl( array( 'id' => 'q2', 'name' => 'a < b' ) ) ) ) ) );
ok( wpfloors_sanitize( $clean ) === $clean, 'sanitize is idempotent' );

/* ---------------- SVG maps ---------------- */

ok( array( 1200.0, 800.0 ) === wpfloors_svg_size( '<svg viewBox="0 0 1200 800">' ), 'size from viewBox' );
ok( array( 50.5, 20.0 ) === wpfloors_svg_size( "<svg\n  viewBox='10,10, 50.5,20' width=\"9\" height=\"9\">" ), 'viewBox wins over width/height; commas allowed' );
ok( array( 300.0, 150.0 ) === wpfloors_svg_size( '<svg width="300px" height="150">' ), 'size from width and height' );
ok( null === wpfloors_svg_size( '<svg width="100%" height="100%">' ) && null === wpfloors_svg_size( '<svg viewBox="0 0 0 10">' ) && null === wpfloors_svg_size( 'nope' ), 'no usable size gives null' );

$good = '<?xml version="1.0" encoding="UTF-8"?><!-- Generator: Adobe Illustrator --><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 50"><defs><style>.a{fill:#333}</style><linearGradient id="g"/></defs><rect class="a" fill="url(#g)" width="10" height="10"/><use xlink:href="#g"/><image href="data:image/png;base64,AAAA" width="1" height="1"/></svg>';
ok( '' === wpfloors_svg_problem( $good ), 'a normal drawing export passes (doctype, style, internal links, embedded PNG)' );
$bad = array(
	'empty'           => '',
	'not svg'         => '<html><body>hi</body></html>',
	'no size'         => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
	'script'          => '<svg viewBox="0 0 1 1"><script>alert(1)</script></svg>',
	'script spaced'   => '<svg viewBox="0 0 1 1">< SCRIPT >alert(1)</script></svg>',
	'onload'          => '<svg viewBox="0 0 1 1" onload="alert(1)"></svg>',
	'onclick upper'   => '<svg viewBox="0 0 1 1"><rect ONCLICK="x()"/></svg>',
	'foreignObject'   => '<svg viewBox="0 0 1 1"><foreignObject><iframe src="x"/></foreignObject></svg>',
	'js href'         => '<svg viewBox="0 0 1 1"><a href="javascript:alert(1)"><rect/></a></svg>',
	'encoded js href' => '<svg viewBox="0 0 1 1"><a xlink:href="&#106;avascript:alert(1)"><rect/></a></svg>',
	'external href'   => '<svg viewBox="0 0 1 1"><image href="https://evil.test/x.png"/></svg>',
	'html data href'  => '<svg viewBox="0 0 1 1"><a href="data:text/html,<script>alert(1)</script>"><rect/></a></svg>',
	'svg data image'  => '<svg viewBox="0 0 1 1"><image href="data:image/svg+xml;base64,AAAA"/></svg>',
	'entity'          => '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg viewBox="0 0 1 1">&x;</svg>',
	'css import'      => '<svg viewBox="0 0 1 1"><style>@import "https://evil.test/a.css";</style></svg>',
	'css url'         => '<svg viewBox="0 0 1 1"><style>.a{background:url(https://evil.test/t.gif)}</style></svg>',
	'embed'           => '<svg viewBox="0 0 1 1"><embed src="x.swf"/></svg>',
	'too big'         => '<svg viewBox="0 0 1 1">' . str_repeat( ' ', WPFLOORS_MAX_SVG_BYTES ) . '</svg>',
);
foreach ( $bad as $what => $xml ) {
	ok( '' !== wpfloors_svg_problem( $xml ), "unsafe or unusable SVG refused: $what" );
}

$up = function ( $name, $content ) {
	$tmp = $GLOBALS['wpf_dir'] . '/upload.tmp';
	file_put_contents( $tmp, $content );
	return wpfloors_check_upload( array( 'name' => $name, 'tmp_name' => $tmp, 'error' => 0 ) );
};
ok( 0 === $up( 'map.svg', $good )['error'], 'good SVG upload goes through untouched' );
ok( is_string( $up( 'Map.SVG', $bad['onload'] )['error'] ), 'bad SVG upload refused with a message' );
ok( is_string( $up( 'map.svgz', $good )['error'] ), '.svgz refused' );
ok( 0 === $up( 'photo.jpg', 'whatever' )['error'], 'other uploads are not this plugin\'s business' );
ok( array( 'url' => 'https://example.test/up/third.svg', 'w' => 1200.0, 'h' => 800.0 ) === wpfloors_image( 7 ), 'SVG attachment gives URL and size' );
ok( 300.0 === wpfloors_image( 8 )['w'], 'SVG sized by width and height works too' );
ok( null === wpfloors_image( 9 ) && null === wpfloors_image( 10 ) && null === wpfloors_image( 99 ) && null === wpfloors_image( 0 ), 'no map from an unsized SVG, a PNG, a missing file or nothing' );

/* ---------------- REST ---------------- */

foreach ( $GLOBALS['wpf_actions']['rest_api_init'] as $fn ) { $fn(); }
$route = $GLOBALS['wpf_routes']['wpfloors/v1/labels'];
ok( 'POST' === $route['methods'], 'route is POST' );
$GLOBALS['wpf_can'] = false;
ok( false === call_user_func( $route['permission_callback'] ), 'needs edit_pages' );
$GLOBALS['wpf_can'] = true;
ok( true === call_user_func( $route['permission_callback'] ), 'editor allowed' );
foreach ( array( null, 'x', array(), array( 'floors' => 'x', 'labels' => array() ), array( 'floors' => array() ), array( 'main' => array() ) ) as $bad ) {
	$r = wpfloors_rest_save( new WP_REST_Request( $bad ) );
	ok( $r instanceof WP_Error && 400 === $r->data['status'], 'bad body rejected with 400' );
}
$GLOBALS['wpf_options'] = array();
$r = wpfloors_rest_save( new WP_REST_Request( array(
	'floors' => array( fl( array( 'image' => 0 ) ), fl( array( 'slug' => 'third', 'name' => 'Third Floor', 'image' => 7 ) ), fl( array( 'slug' => 'fourth', 'name' => 'Fourth', 'image' => 10 ) ) ),
	'labels' => array(
		'main'  => array( lbl( array( 'name' => '<i>Info</i> Desk' ) ), lbl( array( 'id' => 'x2', 'type' => 'nope' ) ) ),
		'third' => array( lbl( array( 'id' => 'e1', 'name' => 'North exit', 'type' => 'exit', 'note' => 'Push bar', 'x' => 50, 'y' => 5 ) ) ),
	),
) ) );
ok( is_array( $r ) && true === $r['ok'], 'save ok' );
ok( 'Info Desk' === $r['labels']->main[0]['name'] && 1 === count( $r['labels']->main ), 'save returns sanitized labels' );
ok( 3 === count( $r['floors'] ) && isset( $r['images']->{7} ) && ! isset( $r['images']->{10} ), 'save returns floors and only usable SVG maps' );
ok( 1200.0 === $r['images']->{7}['w'], 'map size returned for the editor' );
update_option( WPFLOORS_OPTION, array( 'floors' => array( fl() ), 'labels' => array( 'main' => array( lbl( array( 'name' => '<b>x</b>' ) ) ) ) ) );
ok( 'x' === $GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['main'][0]['name'], 'any update_option goes through sanitize' );

/* ---------------- shortcode output ---------------- */

$GLOBALS['wpf_options'] = array();
ok( '<p>No floor maps yet.</p>' === wpfloors_shortcode(), 'a new site says there are no floor maps yet' );

$GLOBALS['wpf_options'][ WPFLOORS_OPTION ] = array( 'main' => array(), 'stacks' => array() ); // 1.0 data, as on the live site
ok( '<p>No floor maps yet.</p>' === wpfloors_shortcode(), 'old 1.0 data shows no floors' );

$GLOBALS['wpf_options'][ WPFLOORS_OPTION ] = array( 'floors' => array( fl(), fl( array( 'slug' => 'b', 'name' => 'Basement', 'image' => 8 ) ), fl( array( 'slug' => 'c', 'name' => 'Attic' ) ) ), 'labels' => array() );
$html = wpfloors_shortcode();
ok( 3 === substr_count( $html, 'Nothing listed on this floor yet.' ), 'empty floors say nothing listed' );
ok( 3 === substr_count( $html, '<h2 ' ) && 3 === substr_count( $html, 'role="img"' ) && 3 === substr_count( $html, 'Everything shown is listed below.' ), 'an H2 and a role=img map per floor' );
ok( 3 === substr_count( $html, 'Open full-size map' ), 'full-size link per floor' );
ok( false === strpos( $html, '<script' ) && false === strpos( $html, '<!--' ), 'no JavaScript, no comments' );
ok( false === strpos( $html, '<nav' ) && false === strpos( $html, 'href="#wpfloors-' ), 'no floor links at the top' );
ok( false === stripos( $html, 'tabindex' ) && false === strpos( $html, '<button' ) && false === strpos( $html, '<input' ), 'nothing interactive on the map' );
ok( false === strpos( $html, '<text' ) && false === strpos( $html, 'class="wpfloors-key"' ), 'no text and no key without labels' );
preg_match_all( '/\sid="([^"]+)"/', $html, $m );
ok( count( $m[1] ) === count( array_unique( $m[1] ) ), 'no duplicate ids' );
ok( strpos( $html, '>Main Level</h2>' ) < strpos( $html, '>Basement</h2>' ) && strpos( $html, '>Basement</h2>' ) < strpos( $html, '>Attic</h2>' ), 'floors in their saved order' );
ok( false !== strpos( $html, 'viewBox="0 0 300 150"' ) && false !== strpos( $html, '<image href="https://example.test/up/sized.svg" x="0" y="0" width="300" height="150"/>' ), 'SVG drawn with <image> at its own size' );

$evil = '"><script>alert(1)</script>';
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ] = array(
	'floors' => array(
		fl(),
		fl( array( 'slug' => 'third', 'name' => 'Third "Floor" & Co', 'image' => 7 ) ),
		fl( array( 'slug' => 'gone', 'name' => 'Annex', 'image' => 99 ) ),
		fl( array( 'slug' => 'main-h', 'name' => 'Main H', 'image' => 8 ) ),
	),
	'labels' => array(
		'main'  => array(
			array( 'id' => 'r1', 'name' => 'Reference Desk', 'type' => 'room', 'note' => 'Ask here', 'x' => 40, 'y' => 30 ),
			array( 'id' => 'r2', 'name' => 'a < b "q"', 'type' => 'collection', 'note' => '', 'x' => 90, 'y' => 30 ),
			array( 'id' => 'r3', 'name' => 'East restroom', 'type' => 'restroom', 'note' => '', 'x' => 70, 'y' => 30 ),
			array( 'id' => 'r4', 'name' => 'Elevator', 'type' => 'elevator', 'note' => '', 'x' => 72, 'y' => 60 ),
			array( 'id' => 'r5', 'name' => 'Rescue area', 'type' => 'rescue', 'note' => "Wait here for help if you can't use the stairs.", 'x' => 20, 'y' => 80 ),
			array( 'id' => 'r6', 'name' => '&lt;script&gt;', 'type' => 'kit', 'note' => $evil, 'x' => 5, 'y' => 5 ),
			array( 'id' => 'r7', 'name' => 'Archives', 'type' => 'collection', 'note' => '', 'x' => 10, 'y' => 30 ),
		),
		'third' => array( array( 'id' => 't1', 'name' => 'Study room', 'type' => 'room', 'note' => '', 'x' => 50, 'y' => 50 ) ),
		'gone'  => array( array( 'id' => 'g1', 'name' => 'Annex desk', 'type' => 'room', 'note' => '', 'x' => 50, 'y' => 50 ) ),
	),
);
$html = wpfloors_shortcode();
ok( false === strpos( $html, '<script' ) && false !== strpos( $html, '&lt;script&gt;' ), 'stray HTML shown as text, never markup' );
ok( false !== strpos( $html, 'a &lt; b &quot;q&quot;' ) && false !== strpos( $html, 'Third &quot;Floor&quot; &amp; Co' ), 'names escaped' );
ok( false !== strpos( $html, '<h3>Rooms and services</h3>' ) && false !== strpos( $html, '<h3>Collections</h3>' ) && false !== strpos( $html, '<h3>Restrooms, elevators and entrances</h3>' ) && false !== strpos( $html, '<h3>Emergency: exits, rescue areas and kits</h3>' ), 'list grouped under the four headings' );
ok( strpos( $html, '<strong>a &lt; b' ) < strpos( $html, '<strong>Archives' ), 'groups sorted by name' );
ok( false !== strpos( $html, "Wait here for help if you can&#039;t use the stairs." ), 'note in the list' );
ok( false !== strpos( $html, '<span class="wpfloors-sr">Restroom: </span><strong>East restroom</strong>' ) && false !== strpos( $html, '<strong>Reference Desk</strong>. Ask here' ), 'type said only in mixed groups' );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't2', 'name' => 'Help Desk', 'type' => 'service', 'note' => '', 'x' => 20, 'y' => 20 );
$h2 = wpfloors_shortcode();
ok( false !== strpos( $h2, '<span class="wpfloors-sr">Room: </span><strong>Study room</strong>' ) && false !== strpos( $h2, '<span class="wpfloors-sr">Service: </span><strong>Help Desk</strong>' ), 'rooms and services share a group and say which is which' );
ok( false !== strpos( $h2, '>Help Desk</text>' ), 'a service shows its name on the map' );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't3', 'name' => 'Color printer', 'type' => 'printer', 'note' => 'Pay at the desk', 'x' => 30, 'y' => 70 );
$h3 = wpfloors_shortcode();
ok( false !== strpos( $h3, '<span class="wpfloors-sr">Printer: </span><strong>Color printer</strong>. Pay at the desk' ) && false === strpos( $h3, '>Color printer</text>' ), 'a printer is listed under rooms and services, symbol only on the map by default' );
ok( false !== strpos( $h3, '<span>Printer</span>' ), 'printer is in the key' );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't4', 'name' => 'Podcast Studio', 'type' => 'studio', 'note' => '', 'x' => 40, 'y' => 40 );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't5', 'name' => 'Self Checkout', 'type' => 'checkout', 'note' => '', 'x' => 50, 'y' => 20 );
$h4 = wpfloors_shortcode();
ok( false !== strpos( $h4, '<span class="wpfloors-sr">Checkout: </span><strong>Self Checkout</strong>' ) && false === strpos( $h4, '>Self Checkout</text>' ) && false !== strpos( $h4, '<span>Checkout</span>' ), 'checkout: listed, symbol only on the map, in the key' );
ok( array() === labs( array( lbl( array( 'type' => 'text' ) ) ) ), 'the removed Text only type is no longer accepted' );
ok( array() === labs( array( lbl( array( 'type' => 'stairs' ) ) ) ), 'the removed Stairs type is no longer accepted' );
ok( '2M-20' === labs( array( lbl( array( 'type' => 'office', 'number' => ' 2M-201<b>9' ) ) ) )[0]['number'], 'office number keeps letters, digits and hyphens, at most 5' );
ok( 'ND-NK' === labs( array( lbl( array( 'type' => 'collection', 'number' => 'ND-NK' ) ) ) )[0]['number'], 'a collection range keeps its hyphen' );
ok( '' === labs( array( lbl( array( 'type' => 'room', 'number' => '214' ) ) ) )[0]['number'], 'only offices and collections have characters' );
ok( 'ND' === labs( array( lbl( array( 'type' => 'collection', 'number' => 'N.D.' ) ) ) )[0]['number'], 'a collection keeps its characters' );
ok( '' === labs( array( lbl( array( 'type' => 'office', 'number' => array( 1 ) ) ) ) )[0]['number'], 'junk office number is dropped' );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't9', 'name' => "Dean's office", 'type' => 'office', 'number' => '2M201', 'note' => '', 'x' => 30, 'y' => 30 );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't10', 'name' => 'Office 7', 'type' => 'office', 'number' => '7', 'note' => '', 'x' => 35, 'y' => 30 );
$h7 = wpfloors_shortcode();
ok( false !== strpos( $h7, '<text class="no" x="0" y="0.18" font-size="0.5">2M201</text>' ), 'a 5-character number shrinks to fit' );
ok( false !== strpos( $h7, '<text class="no" x="0" y="0.36" font-size="1">7</text>' ), 'a short number is full size' );
ok( false !== strpos( $h7, '<span class="wpfloors-sr">Office 2M201: </span><strong>Dean&#039;s office</strong>' ), 'screen readers hear the office number' );
ok( false !== strpos( $h7, '<text class="no" x="0" y="0.36" font-size="1">#</text></g></svg><span>Office</span>' ), 'office: in the key as #' );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't11', 'name' => 'Fine arts', 'type' => 'collection', 'number' => 'ND', 'note' => '', 'x' => 40, 'y' => 60 );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't12', 'name' => 'New books', 'type' => 'collection', 'note' => '', 'x' => 45, 'y' => 60 );
$h8  = wpfloors_shortcode();
$nd  = substr( $h8, strpos( $h8, '<span class="wpfloors-sr">Collection ND: </span><strong>Fine arts</strong>' ) - 300, 300 );
$nb  = substr( $h8, 0, strpos( $h8, '<strong>New books</strong>' ) );
$nb  = substr( $nb, strrpos( $nb, '<li>' ) );
ok( false !== strpos( $nd, '>ND</text>' ) && false === strpos( $nd, 'class="ic"' ), 'a collection with characters shows them in place of the book, and screen readers hear them' );
ok( false !== strpos( $nb, 'class="ic"' ) && false === strpos( $nb, 'class="no"' ), 'a collection without characters keeps the book icon' );
ok( false !== strpos( $h8, '>ND</text><text class="nm"' ), 'on the map too, the characters replace the icon' );
ok( false === strpos( $h7, 'class="sh"' ) && false !== strpos( $h7, 'stroke-width="102.4"' ), 'no marker shapes; icons get a white outline' );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't6', 'name' => 'Main Entrance', 'type' => 'exit', 'note' => '', 'x' => 60, 'y' => 90 );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't7', 'name' => 'North stairwell door', 'type' => 'emergency-exit', 'note' => 'Alarmed', 'x' => 10, 'y' => 10 );
$h5 = wpfloors_shortcode();
ok( false !== strpos( $h5, '<h3>Restrooms, elevators and entrances</h3><ul><li>' ) && false !== strpos( $h5, '<strong>Main Entrance</strong>' ) && false !== strpos( $h5, '>Main Entrance</text>' ), 'entrances are listed with restrooms and elevators, named on the map' );
ok( false !== strpos( $h5, '<h3>Emergency: exits, rescue areas and kits</h3>' ) && false !== strpos( $h5, '<strong>North stairwell door</strong>. Alarmed' ) && false === strpos( $h5, '>North stairwell door</text>' ), 'emergency exits are listed under Emergency, symbol only on the map' );
ok( false !== strpos( $h5, '<span>Entrance and exit</span>' ) && false !== strpos( $h5, '<span>Emergency exit</span>' ), 'both are in the key' );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'][] = array( 'id' => 't8', 'name' => 'Public computers', 'type' => 'computers', 'note' => '', 'x' => 70, 'y' => 70 );
$h6 = wpfloors_shortcode();
ok( false !== strpos( $h6, '<span class="wpfloors-sr">Computers: </span><strong>Public computers</strong>' ) && false === strpos( $h6, '>Public computers</text>' ) && false !== strpos( $h6, '<span>Computers</span>' ), 'computers: listed, symbol only on the map, in the key' );
ok( false !== strpos( $h4, '<span class="wpfloors-sr">Studio: </span><strong>Podcast Studio</strong>' ) && false !== strpos( $h4, '>Podcast Studio</text>' ), 'studio: listed, named on the map' );

ok( 1 === substr_count( $html, 'class="nm end"' ), 'names near the right edge are anchored at the end' );
ok( array( 'right', 'left', 'top', 'bottom', 'left', 'right' ) === array_column( labs( array(
	lbl( array( 'id' => 'p1' ) ),
	lbl( array( 'id' => 'p2', 'pos' => 'left' ) ),
	lbl( array( 'id' => 'p3', 'pos' => 'top' ) ),
	lbl( array( 'id' => 'p4', 'pos' => 'bottom' ) ),
	lbl( array( 'id' => 'p5', 'x' => 80 ) ),
	lbl( array( 'id' => 'p6', 'pos' => 'middle<script>' ) ),
) ), 'pos' ), 'name position: a valid choice, or right (left near the right edge) by default' );
$m = wpfloors_marker( array( 'type' => 'room', 'name' => 'Top', 'number' => '', 'show' => true, 'pos' => 'top', 'x' => 50, 'y' => 50 ), array( 0, 0, 100, 100 ) );
ok( false !== strpos( $m, '<text class="nm mid" x="0" y="-1.15" font-size="1">Top</text>' ), 'a name on top is centered above the icon' );
$m = wpfloors_marker( array( 'type' => 'room', 'name' => 'Bottom', 'number' => '', 'show' => true, 'pos' => 'bottom', 'x' => 50, 'y' => 50 ), array( 0, 0, 100, 100 ) );
ok( false !== strpos( $m, '<text class="nm mid" x="0" y="1.8" font-size="1">Bottom</text>' ), 'a name below is centered under the icon' );
ok( array( true, false, false, true ) === array_column( labs( array( lbl( array( 'id' => 'i1' ) ), lbl( array( 'id' => 'i2', 'show_icon' => false ) ), lbl( array( 'id' => 'i3', 'show_icon' => '0' ) ), lbl( array( 'id' => 'i4', 'show_icon' => 'banana' ) ) ) ), 'show_icon' ), 'show icon: on by default, accepts yes/no values' );
$m = wpfloors_marker( array( 'type' => 'room', 'name' => 'Atrium', 'number' => '', 'show' => true, 'show_icon' => false, 'pos' => 'top', 'x' => 50, 'y' => 50 ), array( 0, 0, 100, 100 ) );
ok( false === strpos( $m, 'class="ic"' ) && false !== strpos( $m, '<text class="nm mid" x="0" y="0.36" font-size="1">Atrium</text>' ), 'without its icon, the name is centered on the spot' );
$m = wpfloors_marker( array( 'type' => 'office', 'name' => 'Dean', 'number' => '214', 'show' => false, 'show_icon' => false, 'pos' => 'right', 'x' => 50, 'y' => 50 ), array( 0, 0, 100, 100 ) );
ok( false === strpos( $m, '<text' ) && false === strpos( $m, '<path' ), 'with both off, nothing is drawn on the map' );
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['third'] = array( array( 'id' => 'k1', 'name' => 'Lift', 'type' => 'elevator', 'note' => '', 'x' => 50, 'y' => 50, 'show_icon' => false ), array( 'id' => 'k2', 'name' => 'Desk', 'type' => 'service', 'note' => '', 'x' => 40, 'y' => 50 ) );
$h9 = wpfloors_shortcode();
$h9 = substr( $h9, strpos( $h9, 'id="wpfloors-third"' ) );
$h9 = substr( $h9, 0, strpos( $h9, '</section>' ) );
ok( false === strpos( $h9, '<span>Elevator</span>' ) && false !== strpos( $h9, '<span>Service</span>' ) && false !== strpos( $h9, '<strong>Lift</strong>' ), 'the key lists only icons shown on the map; the list still has every label' );
ok( false === strpos( $html, '>East restroom</text>' ) && false === strpos( $html, '>Elevator</text>' ) && false !== strpos( $html, '>Reference Desk</text>' ), 'restrooms and elevators show only their symbol by default; rooms show their name' );
ok( false !== strpos( $html, '<span class="wpfloors-sr">Restroom: </span><strong>East restroom</strong>' ) && false !== strpos( $html, '<span class="wpfloors-sr">Elevator: </span><strong>Elevator</strong>' ), 'hidden names still appear in the list' );
ok( false !== strpos( $html, '<image href="https://example.test/up/third.svg" x="0" y="0" width="1200" height="800"/>' ), 'each floor draws its own SVG' );
ok( false !== strpos( $html, 'translate(600 400) scale(24)' ) && false !== strpos( $html, 'translate(480 240) scale(24)' ), 'markers placed by percent of the map' );
ok( 1 === substr_count( $html, 'The map for this floor isn&#8217;t available yet.' ) && false !== strpos( $html, '<strong>Annex desk</strong>' ), 'floor with a missing image still lists its labels' );
ok( 3 === substr_count( $html, 'Open full-size map' ), 'no full-size link without a map' );
preg_match_all( '/\sid="([^"]+)"/', $html, $m );
ok( count( $m[1] ) === count( array_unique( $m[1] ) ), 'no duplicate ids (slug main-h next to main)' );
ok( 1 === preg_match( '/wpfloors_map=third(&amp;|&)wpfloors_from=42/', $html ), 'full-size link points at the floor' );

/* ---------------- full-size page ---------------- */

$full = wpfloors_full_page( 'main', 'https://example.test/about/floor-maps/' );
ok( 0 === strpos( $full, '<!doctype html><html lang="en-US">' ), 'full page is a document with a language' );
ok( false !== strpos( $full, '<title>Main Level floor map | Wallace Library</title>' ), 'full page title' );
ok( 1 === substr_count( $full, '<h1>Main Level</h1>' ) && 4 === substr_count( $full, '<h2>' ), 'full page H1, groups as H2' );
ok( false !== strpos( $full, 'Back to all floor maps' ) && false === strpos( $full, 'Open full-size map' ) && false === strpos( $full, '<script' ), 'back link, no self link, no JS' );
ok( false !== strpos( wpfloors_full_page( 'third', '' ), '<h1>Third &quot;Floor&quot; &amp; Co</h1>' ), 'full page for another floor' );
ok( '' === wpfloors_full_page( 'nope', '' ), 'unknown floor gives nothing' );

/* ---------------- admin ---------------- */

ob_start(); wpfloors_admin_page(); $admin = ob_get_clean();
ok( false !== strpos( $admin, 'window.WPFLOORS_ADMIN=' ), 'admin config inline' );
ok( false === strpos( substr( $admin, strpos( $admin, 'WPFLOORS_ADMIN=' ), 6000 ), '<script>alert' ), 'config JSON cannot close the script tag' );
ok( false === strpos( $admin, 'wpfloors-tpl' ) && false !== strpos( $admin, 'Map (SVG)' ), 'admin has no built-in drawings' );
$GLOBALS['wpf_can'] = false;
ob_start(); wpfloors_admin_page(); ok( '' === ob_get_clean(), 'admin page empty without edit_pages' );
$GLOBALS['wpf_can'] = true;

/* ---------------- previews ---------------- */

if ( isset( $argv[1] ) ) {
	$dir  = rtrim( $argv[1], '/\\' );
	$wrap = function ( $title, $body ) {
		return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . '</title>'
			. '<style>body{margin:0;padding:16px;font:16px/1.5 Georgia,serif;color:#222;background:#fff}.page{max-width:760px;margin:0 auto}</style></head><body><main class="page"><h1>Floor Maps</h1>' . $body . '</main></body></html>';
	};
	file_put_contents( "$dir/preview-labels.html", $wrap( 'Floor Maps preview', $html ) );
	file_put_contents( "$dir/preview-admin.html", $admin );
	echo "previews written to $dir\n";
}

echo "$passes passed, $fails failed\n";
exit( $fails ? 1 : 0 );
