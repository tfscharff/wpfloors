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
	return array_merge( array( 'slug' => 'main', 'name' => 'Main Level', 'map' => 'main', 'image' => 0 ), $over );
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

$s = wpfloors_sanitize( array( 'main' => array( lbl() ), 'stacks' => array(), 'periodicals' => 'junk' ) );
ok( array( 'main', 'periodicals', 'stacks', 'first-mezzanine', 'second-mezzanine' ) === array_column( $s['floors'], 'slug' ), '1.0 data upgrades to the five built-in floors, in order' );
ok( 'Main Level' === $s['floors'][0]['name'] && 'main' === $s['floors'][0]['map'], 'upgraded floor uses its built-in drawing' );
ok( 1 === count( $s['labels']['main'] ) && array() === $s['labels']['periodicals'], '1.0 labels kept on their floors' );
ok( 5 === count( wpfloors_sanitize( array() )['floors'] ), 'an empty 1.0 option still upgrades' );
ok( wpfloors_sanitize( 'junk' ) === wpfloors_sanitize( array() ), 'junk input treated like an empty old option' );

$s = wpfloors_sanitize( array( 'floors' => array(
	fl( array( 'slug' => '', 'name' => '<b>Third</b> Floor!', 'map' => 'image', 'image' => '7' ) ),
	fl( array( 'slug' => 'third-floor', 'name' => 'Another', 'map' => 'image', 'image' => 7 ) ),
	fl( array( 'slug' => 'Main', 'name' => 'Main', 'map' => 'main', 'image' => 7 ) ),
	fl( array( 'slug' => '', 'name' => '!!!', 'map' => 'bogus', 'image' => -3 ) ),
	fl( array( 'name' => '' ) ),
	fl( array( 'name' => array( 'x' ) ) ),
	'not a floor',
) ) );
$f = $s['floors'];
ok( 4 === count( $f ), 'floors without a name dropped' );
ok( 'third-floor' === $f[0]['slug'] && 'Third Floor!' === $f[0]['name'], 'slug made from the name; tags stripped from the name' );
ok( 'third-floor-2' === $f[1]['slug'], 'duplicate slugs made unique' );
ok( 'main' === $f[2]['slug'] && 0 === $f[2]['image'], 'built-in floors carry no image' );
ok( 7 === $f[0]['image'] && 'image' === $f[0]['map'], 'image floor keeps its attachment ID' );
ok( 'floor' === $f[3]['slug'] && 'image' === $f[3]['map'] && 0 === $f[3]['image'], 'unknown map becomes an image floor with no image' );
ok( array_keys( $s['labels'] ) === array_column( $f, 'slug' ), 'a label list for every floor' );

$many = array();
for ( $i = 0; $i < 40; $i++ ) { $many[] = fl( array( 'slug' => 'f' . $i, 'name' => 'F' . $i ) ); }
ok( WPFLOORS_MAX_FLOORS === count( wpfloors_sanitize( array( 'floors' => $many, 'labels' => array() ) )['floors'] ), 'floor count capped' );
$s = wpfloors_sanitize( array( 'floors' => array( fl() ), 'labels' => array( 'main' => array( lbl() ), 'gone' => array( lbl() ) ) ) );
ok( ! isset( $s['labels']['gone'] ), 'labels for a deleted floor dropped' );

/* ---------------- labels ---------------- */

ok( labs( array( lbl() ) ) === array( array( 'id' => 'a1', 'name' => 'Room', 'type' => 'room', 'note' => '', 'x' => 10.0, 'y' => 20.0 ) ), 'clean label kept as is' );
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
$clean = wpfloors_sanitize( array( 'floors' => array( fl(), fl( array( 'slug' => '', 'name' => 'Third', 'map' => 'image', 'image' => 7 ) ) ), 'labels' => array( 'main' => array( lbl(), lbl( array( 'id' => 'q2', 'name' => 'a < b' ) ) ) ) ) );
ok( wpfloors_sanitize( $clean ) === $clean, 'sanitize is idempotent' );

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
	'floors' => array( fl(), fl( array( 'slug' => 'third', 'name' => 'Third Floor', 'map' => 'image', 'image' => 7 ) ), fl( array( 'slug' => 'fourth', 'name' => 'Fourth', 'map' => 'image', 'image' => 99 ) ) ),
	'labels' => array(
		'main'  => array( lbl( array( 'name' => '<i>Info</i> Desk' ) ), lbl( array( 'id' => 'x2', 'type' => 'nope' ) ) ),
		'third' => array( lbl( array( 'id' => 'e1', 'name' => 'North exit', 'type' => 'exit', 'note' => 'Push bar', 'x' => 50, 'y' => 5 ) ) ),
	),
) ) );
ok( is_array( $r ) && true === $r['ok'], 'save ok' );
ok( 'Info Desk' === $r['labels']->main[0]['name'] && 1 === count( $r['labels']->main ), 'save returns sanitized labels' );
ok( 3 === count( $r['floors'] ) && isset( $r['images']->{7} ) && ! isset( $r['images']->{99} ), 'save returns floors and only the images that exist' );
ok( 1200 === $r['images']->{7}['w'], 'image size returned for the editor' );
update_option( WPFLOORS_OPTION, array( 'floors' => array( fl() ), 'labels' => array( 'main' => array( lbl( array( 'name' => '<b>x</b>' ) ) ) ) ) );
ok( 'x' === $GLOBALS['wpf_options'][ WPFLOORS_OPTION ]['labels']['main'][0]['name'], 'any update_option goes through sanitize' );

/* ---------------- shortcode output ---------------- */

$GLOBALS['wpf_options'] = array();
ok( '<p>No floor maps yet.</p>' === wpfloors_shortcode(), 'a new site says there are no floor maps yet' );

$GLOBALS['wpf_options'][ WPFLOORS_OPTION ] = array( 'main' => array(), 'stacks' => array() ); // 1.0 data, as on the live site
$html = wpfloors_shortcode();
ok( 5 === substr_count( $html, 'Nothing listed on this floor yet.' ), 'upgraded site: all five floors, empty' );
ok( 5 === substr_count( $html, '<h2 ' ) && 5 === substr_count( $html, 'role="img"' ) && 5 === substr_count( $html, 'Everything shown is listed below.' ), 'an H2 and a role=img map per floor' );
ok( 5 === substr_count( $html, 'Open full-size map' ), 'full-size link per floor' );
ok( false === strpos( $html, '<script' ) && false === strpos( $html, '<!--' ), 'no JavaScript, no source comments' );
ok( false === stripos( $html, 'tabindex' ) && false === strpos( $html, '<button' ) && false === strpos( $html, '<input' ), 'nothing interactive on the map' );
ok( false === strpos( $html, '<text' ) && false === strpos( $html, 'class="wpfloors-key"' ), 'no text and no key without labels' );
preg_match_all( '/\sid="([^"]+)"/', $html, $m );
ok( count( $m[1] ) === count( array_unique( $m[1] ) ), 'no duplicate ids' );
$order = array();
foreach ( array( 'Main Level', 'Periodicals Level', 'Stacks Level', 'First Mezzanine', 'Second Mezzanine' ) as $n ) { $order[] = strpos( $html, '>' . $n . '</h2>' ); }
$sorted = $order; sort( $sorted );
ok( $order === $sorted && ! in_array( false, $order, true ), 'floors in the agreed order' );

$evil = '"><script>alert(1)</script>';
$GLOBALS['wpf_options'][ WPFLOORS_OPTION ] = array(
	'floors' => array(
		fl(),
		fl( array( 'slug' => 'third', 'name' => 'Third "Floor" & Co', 'map' => 'image', 'image' => 7 ) ),
		fl( array( 'slug' => 'gone', 'name' => 'Annex', 'map' => 'image', 'image' => 99 ) ),
		fl( array( 'slug' => 'main-h', 'name' => 'Main H', 'map' => 'stacks' ) ),
	),
	'labels' => array(
		'main'  => array(
			array( 'id' => 'r1', 'name' => 'Reference Desk', 'type' => 'room', 'note' => 'Ask here', 'x' => 40, 'y' => 30 ),
			array( 'id' => 'r2', 'name' => 'a < b "q"', 'type' => 'collection', 'note' => '', 'x' => 90, 'y' => 30 ),
			array( 'id' => 'r3', 'name' => 'East stairs', 'type' => 'stairs', 'note' => '', 'x' => 70, 'y' => 30 ),
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
ok( false !== strpos( $html, '<h3>Rooms and services</h3>' ) && false !== strpos( $html, '<h3>Collections</h3>' ) && false !== strpos( $html, '<h3>Restrooms, elevators and stairs</h3>' ) && false !== strpos( $html, '<h3>Emergency: exits, rescue areas and kits</h3>' ), 'list grouped under the four headings' );
ok( strpos( $html, '<strong>a &lt; b' ) < strpos( $html, '<strong>Archives' ), 'groups sorted by name' );
ok( false !== strpos( $html, "Wait here for help if you can&#039;t use the stairs." ), 'note in the list' );
ok( false !== strpos( $html, 'Stairs: <strong>East stairs</strong>' ) && false !== strpos( $html, '<strong>Reference Desk</strong>. Ask here' ), 'type said only in mixed groups' );
ok( 3 === substr_count( $html, 'class="nm end"' ), 'names near the right edge are anchored at the end' );
ok( false !== strpos( $html, '<image href="https://example.test/up/third-floor.png" x="0" y="0" width="1200" height="800"/>' ) && false !== strpos( $html, 'viewBox="0 0 1200 800"' ), 'image floor draws its image at its own size' );
ok( false !== strpos( $html, 'translate(600 400) scale(19.2)' ), 'markers placed by percent on image floors' );
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
ok( false !== strpos( wpfloors_full_page( 'third', '' ), '<h1>Third &quot;Floor&quot; &amp; Co</h1>' ), 'full page for an image floor' );
ok( '' === wpfloors_full_page( 'nope', '' ), 'unknown floor gives nothing' );

/* ---------------- admin ---------------- */

ob_start(); wpfloors_admin_page(); $admin = ob_get_clean();
ok( false !== strpos( $admin, 'window.WPFLOORS_ADMIN=' ), 'admin config inline' );
ok( false === strpos( substr( $admin, strpos( $admin, 'WPFLOORS_ADMIN=' ), 6000 ), '<script>alert' ), 'config JSON cannot close the script tag' );
ok( 5 === substr_count( $admin, 'class="wpfloors-tpl"' ), 'admin has every built-in drawing as a template' );
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
