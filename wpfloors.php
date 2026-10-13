<?php
/**
 * Plugin Name:       Floor Maps
 * Description:       Floor maps of the library. Staff add every label (rooms, collections, restrooms, exits and more) under Floor Maps in wp-admin. Show the maps with the [floor_maps] shortcode.
 * Version:           1.0.0
 * Author:            Madeleine Clark Wallace Library
 * License:           GPL-2.0+
 * Requires at least: 5.6
 * Requires PHP:      7.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WPFLOORS_OPTION', 'wpfloors' );
define( 'WPFLOORS_MAX_LABELS', 300 ); // per floor

/* ------------------------------------------------------------------ *
 *  Floors and label types
 * ------------------------------------------------------------------ */

/** The five floors, in page order. viewBox is "minX minY width height" of the drawing. */
function wpfloors_floors() {
	return array(
		'main'             => array( 'name' => 'Main Level',        'viewBox' => '6 26 840 734' ),
		'periodicals'      => array( 'name' => 'Periodicals Level', 'viewBox' => '0 88 834 735' ),
		'stacks'           => array( 'name' => 'Stacks Level',      'viewBox' => '18 57 1334 1889' ),
		'first-mezzanine'  => array( 'name' => 'First Mezzanine',   'viewBox' => '2 26 430 562' ),
		'second-mezzanine' => array( 'name' => 'Second Mezzanine',  'viewBox' => '488 26 330 552' ),
	);
}

/**
 * Label types. Color never carries meaning: each type has its own marker shape and letter,
 * drawn black on white. Shapes are SVG paths around (0,0) at radius ~1; tx/ty/fs place the letter.
 */
function wpfloors_types() {
	return array(
		'room'       => array( 'label' => 'Room or service',       'group' => 'rooms',       'letter' => 'R',  'tx' => 0,     'ty' => 0.34, 'fs' => 0.95, 'shape' => 'M1 0A1 1 0 1 1-1 0A1 1 0 1 1 1 0Z' ),
		'collection' => array( 'label' => 'Collection',            'group' => 'collections', 'letter' => 'C',  'tx' => 0,     'ty' => 0.34, 'fs' => 0.95, 'shape' => 'M-.9-.9H.9V.9H-.9Z' ),
		'restroom'   => array( 'label' => 'Restroom',              'group' => 'facilities',  'letter' => 'WC', 'tx' => 0,     'ty' => 0.28, 'fs' => 0.78, 'shape' => 'M-.55-.85H.55A.85 .85 0 0 1 .55 .85H-.55A.85 .85 0 0 1-.55-.85Z' ),
		'elevator'   => array( 'label' => 'Elevator',              'group' => 'facilities',  'letter' => 'EL', 'tx' => 0,     'ty' => 0.28, 'fs' => 0.78, 'shape' => 'M1.1 0L.55 .95H-.55L-1.1 0L-.55-.95H.55Z' ),
		'stairs'     => array( 'label' => 'Stairs',                'group' => 'facilities',  'letter' => 'ST', 'tx' => 0,     'ty' => 0.4,  'fs' => 0.7,  'shape' => 'M0-1.25L1.35 .85H-1.35Z' ),
		'exit'       => array( 'label' => 'Exit',                  'group' => 'emergency',   'letter' => 'EX', 'tx' => -0.12, 'ty' => 0.28, 'fs' => 0.78, 'shape' => 'M-1.05-.85H.5L1.2 0L.5 .85H-1.05Z' ),
		'rescue'     => array( 'label' => 'Emergency rescue area', 'group' => 'emergency',   'letter' => 'RA', 'tx' => 0,     'ty' => 0.28, 'fs' => 0.78, 'shape' => 'M-.45-1.05H.45L1.05-.45V.45L.45 1.05H-.45L-1.05 .45V-.45Z' ),
		'kit'        => array( 'label' => 'Emergency kit',         'group' => 'emergency',   'letter' => 'K',  'tx' => 0,     'ty' => 0.34, 'fs' => 0.95, 'shape' => 'M0-1.2L1.2 0L0 1.2L-1.2 0Z' ),
	);
}

/** List headings on the public page, in order. */
function wpfloors_groups() {
	return array(
		'rooms'       => 'Rooms and services',
		'collections' => 'Collections',
		'facilities'  => 'Restrooms, elevators and stairs',
		'emergency'   => 'Emergency: exits, rescue areas and kits',
	);
}

/* ------------------------------------------------------------------ *
 *  Data: one option, keyed by floor. Every write goes through wpfloors_sanitize().
 * ------------------------------------------------------------------ */

add_filter( 'sanitize_option_' . WPFLOORS_OPTION, 'wpfloors_sanitize' );

/** Saved labels for every floor: {floor: [{id, name, type, note, x, y}]}. */
function wpfloors_get() {
	return wpfloors_sanitize( get_option( WPFLOORS_OPTION, array() ) );
}

/** Plain text, no tags, at most $max characters. Stored decoded; always escaped on output. */
function wpfloors_text( $v, $max ) {
	if ( ! is_scalar( $v ) || is_bool( $v ) ) { return ''; }
	$s = wp_specialchars_decode( sanitize_text_field( (string) $v ), ENT_QUOTES );
	// Clip by characters, not bytes, so a multibyte character is never cut in half.
	return preg_match( '/^.{0,' . (int) $max . '}/us', $s, $m ) ? trim( $m[0] ) : '';
}

/** A position as a percentage of the drawing, 0–100, or null when it isn't a number. */
function wpfloors_pct( $v ) {
	if ( is_bool( $v ) || ! is_numeric( $v ) ) { return null; }
	$f = (float) $v;
	if ( is_nan( $f ) ) { return null; }
	return round( max( 0, min( 100, $f ) ), 2 );
}

/** Whitelist and validate incoming data so only clean labels are stored. Drops bad rows. */
function wpfloors_sanitize( $in ) {
	$in    = is_array( $in ) ? $in : array();
	$types = wpfloors_types();
	$out   = array();
	foreach ( wpfloors_floors() as $slug => $floor ) {
		$rows  = isset( $in[ $slug ] ) && is_array( $in[ $slug ] ) ? $in[ $slug ] : array();
		$clean = array();
		$seen  = array();
		foreach ( $rows as $r ) {
			if ( count( $clean ) >= WPFLOORS_MAX_LABELS ) { break; }
			if ( ! is_array( $r ) ) { continue; }
			$name = wpfloors_text( isset( $r['name'] ) ? $r['name'] : '', 100 );
			$type = isset( $r['type'] ) && is_string( $r['type'] ) ? $r['type'] : '';
			$x    = wpfloors_pct( isset( $r['x'] ) ? $r['x'] : null );
			$y    = wpfloors_pct( isset( $r['y'] ) ? $r['y'] : null );
			if ( '' === $name || ! isset( $types[ $type ] ) || null === $x || null === $y ) { continue; }
			$id = isset( $r['id'] ) && is_string( $r['id'] ) ? substr( preg_replace( '/[^a-z0-9-]/', '', strtolower( $r['id'] ) ), 0, 40 ) : '';
			while ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = 'l' . substr( md5( uniqid( '', true ) . mt_rand() ), 0, 12 );
			}
			$seen[ $id ] = true;
			$clean[]     = array(
				'id'   => $id,
				'name' => $name,
				'type' => $type,
				'note' => wpfloors_text( isset( $r['note'] ) ? $r['note'] : '', 300 ),
				'x'    => $x,
				'y'    => $y,
			);
		}
		$out[ $slug ] = $clean;
	}
	return $out;
}

/* ------------------------------------------------------------------ *
 *  REST API   (POST = save; the admin page gets the current data inline)
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', function () {
	register_rest_route( 'wpfloors/v1', '/labels', array(
		'methods'             => 'POST',
		'callback'            => 'wpfloors_rest_save',
		'permission_callback' => function () {
			return current_user_can( 'edit_pages' );
		},
	) );
} );

function wpfloors_rest_save( $req ) {
	$body = $req->get_json_params();
	if ( ! is_array( $body ) || ! isset( $body['floors'] ) || ! is_array( $body['floors'] ) ) {
		return new WP_Error( 'wpfloors_bad_data', 'Invalid floor map data.', array( 'status' => 400 ) );
	}
	update_option( WPFLOORS_OPTION, wpfloors_sanitize( $body['floors'] ), false );
	return rest_ensure_response( array( 'ok' => true, 'floors' => wpfloors_get() ) );
}

/* ------------------------------------------------------------------ *
 *  Drawing the maps (shared by the public page and wp-admin)
 * ------------------------------------------------------------------ */

function wpfloors_vb( $slug ) {
	$f = wpfloors_floors();
	return array_map( 'floatval', explode( ' ', $f[ $slug ]['viewBox'] ) );
}

function wpfloors_num( $n ) {
	return rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' );
}

/** One label on the map: its marker shape, letter and name, in marker units (radius 1). */
function wpfloors_marker( $lb, $vb ) {
	$types = wpfloors_types();
	if ( ! isset( $types[ $lb['type'] ] ) ) { return ''; }
	$t     = $types[ $lb['type'] ];
	$r     = max( $vb[2], $vb[3] ) * 0.016;
	$cx    = $vb[0] + $lb['x'] / 100 * $vb[2];
	$cy    = $vb[1] + $lb['y'] / 100 * $vb[3];
	$right = $lb['x'] > 65; // Names near the right edge go on the left of the marker.
	return '<g class="mk" transform="translate(' . wpfloors_num( $cx ) . ' ' . wpfloors_num( $cy ) . ') scale(' . wpfloors_num( $r ) . ')">'
		. '<path d="' . esc_attr( $t['shape'] ) . '"/>'
		. '<text class="lt" x="' . $t['tx'] . '" y="' . $t['ty'] . '" font-size="' . $t['fs'] . '">' . esc_html( $t['letter'] ) . '</text>'
		. '<text class="nm' . ( $right ? ' end' : '' ) . '" x="' . ( $right ? '-1.7' : '1.7' ) . '" y="0.45" font-size="1.25">' . esc_html( $lb['name'] ) . '</text>'
		. '</g>';
}

/** A small marker icon for the key and the lists. Decorative: the text next to it says the type. */
function wpfloors_icon( $type ) {
	$t = wpfloors_types();
	$t = $t[ $type ];
	return '<svg class="wpfloors-svg wpfloors-icon" viewBox="-1.5 -1.5 3 3" aria-hidden="true" focusable="false"><g class="mk">'
		. '<path d="' . esc_attr( $t['shape'] ) . '"/>'
		. '<text class="lt" x="' . $t['tx'] . '" y="' . $t['ty'] . '" font-size="' . $t['fs'] . '">' . esc_html( $t['letter'] ) . '</text></g></svg>';
}

/** A floor's drawing as inline SVG, with its labels. */
function wpfloors_svg( $slug, $labels, $aria_label ) {
	$floors = wpfloors_floors();
	$vb     = wpfloors_vb( $slug );
	$out    = '<svg class="wpfloors-svg" viewBox="' . esc_attr( $floors[ $slug ]['viewBox'] ) . '" role="img" aria-label="' . esc_attr( $aria_label ) . '" focusable="false">';
	$out   .= wpfloors_drawing( $slug );
	// The labels are hidden from screen readers on purpose. The list under each map is the full
	// text version of everything on it, so reading the labels here too would say everything
	// twice. Keep aria-hidden on this group.
	$out .= '<g class="wpfloors-labels" aria-hidden="true">';
	foreach ( $labels as $lb ) {
		$out .= wpfloors_marker( $lb, $vb );
	}
	return $out . '</g></svg>';
}

/** Shared styles for the drawings and markers. Walls are dark on a light floor (well over 3:1). */
function wpfloors_svg_css() {
	return <<<'CSS'
.wpfloors-svg{display:block;width:100%;height:auto;max-height:85vh;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
.wpfloors-svg .fp{fill:#f6f4ee}
.wpfloors-svg .w,.wpfloors-svg .i,.wpfloors-svg .t{fill:none;stroke:#2b2b2b;stroke-linecap:square}
.wpfloors-svg .w{stroke-width:8}.wpfloors-svg .i{stroke-width:5}.wpfloors-svg .t{stroke-width:3}
.wpfloors-svg .s{fill:#2b2b2b}
.wpfloors-svg .d{fill:none;stroke:#555;stroke-width:1.5}
.wpfloors-svg .st{fill:none;stroke:#2b2b2b;stroke-width:1.5}
.wpfloors-svg .el{fill:none;stroke:#2b2b2b;stroke-width:2}
.wpfloors-svg .mk path{fill:#fff;stroke:#000;stroke-width:.14;stroke-linejoin:round}
.wpfloors-svg .mk .lt{fill:#000;font-weight:700;text-anchor:middle}
.wpfloors-svg .mk .nm{fill:#000;font-weight:600;stroke:#fff;stroke-width:.4;stroke-linejoin:round;paint-order:stroke}
.wpfloors-svg .mk .nm.end{text-anchor:end}
.wpfloors-svg.wpfloors-icon{display:inline-block;width:1.8em;height:1.8em;max-height:none;vertical-align:middle;flex:0 0 auto}
CSS;
}

/* ------------------------------------------------------------------ *
 *  Public page: [floor_maps]   (rendered on the server; no JavaScript)
 * ------------------------------------------------------------------ */

add_shortcode( 'floor_maps', 'wpfloors_shortcode' );

function wpfloors_shortcode() {
	$data = wpfloors_get();
	$from = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
	$out  = '<style>' . wpfloors_svg_css() . wpfloors_page_css() . '</style><div class="wpfloors">';
	$out .= '<nav class="wpfloors-nav" aria-label="Floors"><ul>';
	foreach ( wpfloors_floors() as $slug => $f ) {
		$out .= '<li><a href="#wpfloors-' . esc_attr( $slug ) . '">' . esc_html( $f['name'] ) . '</a></li>';
	}
	$out .= '</ul></nav>';
	foreach ( wpfloors_floors() as $slug => $f ) {
		$full = add_query_arg( array( 'wpfloors_map' => $slug, 'wpfloors_from' => $from ), home_url( '/' ) );
		$out .= '<section class="wpfloors-floor" id="wpfloors-' . esc_attr( $slug ) . '" aria-labelledby="wpfloors-' . esc_attr( $slug ) . '-h">';
		$out .= '<h2 id="wpfloors-' . esc_attr( $slug ) . '-h">' . esc_html( $f['name'] ) . '</h2>';
		$out .= wpfloors_floor_body( $slug, $data[ $slug ], 3, $full );
		$out .= '</section>';
	}
	return $out . '</div>';
}

/** Drawing, key, full-size link and list for one floor. $h is the heading level for the list groups. */
function wpfloors_floor_body( $slug, $labels, $h, $full_url ) {
	$floors = wpfloors_floors();
	$types  = wpfloors_types();
	$name   = $floors[ $slug ]['name'];
	$out    = '<div class="wpfloors-map">' . wpfloors_svg( $slug, $labels, $name . ' floor map. Everything shown is listed below.' ) . '</div>';

	$used = array();
	foreach ( $labels as $lb ) { $used[ $lb['type'] ] = true; }
	if ( $used ) {
		$out .= '<div class="wpfloors-key"><p class="wpfloors-key-h">Key</p><ul>';
		foreach ( $types as $type => $t ) {
			if ( isset( $used[ $type ] ) ) {
				$out .= '<li>' . wpfloors_icon( $type ) . '<span>' . esc_html( $t['label'] ) . '</span></li>';
			}
		}
		$out .= '</ul></div>';
	}
	if ( $full_url ) {
		$out .= '<p class="wpfloors-full-link"><a href="' . esc_url( $full_url ) . '">Open full-size map<span class="wpfloors-sr"> of the ' . esc_html( $name ) . '</span></a></p>';
	}
	if ( ! $labels ) {
		return $out . '<p class="wpfloors-empty">Nothing listed on this floor yet.</p>';
	}

	foreach ( wpfloors_groups() as $group => $heading ) {
		$items = array();
		$kinds = array();
		foreach ( $labels as $lb ) {
			if ( $types[ $lb['type'] ]['group'] === $group ) { $items[] = $lb; $kinds[ $lb['type'] ] = true; }
		}
		if ( ! $items ) { continue; }
		usort( $items, function ( $a, $b ) { return strnatcasecmp( $a['name'], $b['name'] ); } );
		$out .= '<div class="wpfloors-group"><h' . $h . '>' . esc_html( $heading ) . '</h' . $h . '><ul>';
		foreach ( $items as $lb ) {
			$out .= '<li>' . wpfloors_icon( $lb['type'] ) . '<span>';
			if ( count( $kinds ) > 1 ) { // Say the type when a group mixes types, e.g. "Stairs: East stairs".
				$out .= esc_html( $types[ $lb['type'] ]['label'] ) . ': ';
			}
			$out .= '<strong>' . esc_html( $lb['name'] ) . '</strong>';
			if ( '' !== $lb['note'] ) { $out .= '. ' . esc_html( $lb['note'] ); }
			$out .= '</span></li>';
		}
		$out .= '</ul></div>';
	}
	return $out;
}

function wpfloors_page_css() {
	return <<<'CSS'
.wpfloors{overflow-wrap:anywhere}
.wpfloors-nav ul{display:flex;flex-wrap:wrap;gap:.4em 1.4em;list-style:none;margin:0 0 1em;padding:0}
.wpfloors-floor{margin:0 0 2.5em}
.wpfloors-map{border:1px solid #767676;background:#fff;padding:4px}
.wpfloors-key{margin:.6em 0}
.wpfloors-key-h{font-weight:700;margin:0 0 .2em}
.wpfloors-key ul,.wpfloors-group ul{list-style:none;margin:0;padding:0}
.wpfloors-key ul{display:flex;flex-wrap:wrap;gap:.3em 1.2em}
.wpfloors-key li,.wpfloors-group li{display:flex;align-items:flex-start;gap:.4em;margin:.25em 0}
.wpfloors-group li .wpfloors-icon{margin-top:-.15em}
.wpfloors-sr{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media print{
  .wpfloors-nav,.wpfloors-full-link{display:none}
  .wpfloors-floor{break-before:page;page-break-before:always;margin:0}
  .wpfloors-floor:first-of-type{break-before:auto;page-break-before:auto}
  .wpfloors-svg{max-height:6.5in}
  .wpfloors-map{border:0;padding:0}
  .wpfloors-group{break-inside:avoid;page-break-inside:avoid}
}
CSS;
}

/* ------------------------------------------------------------------ *
 *  "Open full-size map": one floor on its own page, e.g. /?wpfloors_map=main
 * ------------------------------------------------------------------ */

add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['wpfloors_map'] ) ) { return; }
	$slug   = sanitize_key( wp_unslash( $_GET['wpfloors_map'] ) );
	$floors = wpfloors_floors();
	if ( ! isset( $floors[ $slug ] ) ) { return; }
	$from = isset( $_GET['wpfloors_from'] ) ? absint( $_GET['wpfloors_from'] ) : 0;
	$back = ( $from && 'publish' === get_post_status( $from ) ) ? get_permalink( $from ) : '';
	header( 'Content-Type: text/html; charset=utf-8' );
	echo wpfloors_full_page( $slug, $back ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );

function wpfloors_full_page( $slug, $back ) {
	$floors = wpfloors_floors();
	$data   = wpfloors_get();
	$name   = $floors[ $slug ]['name'];
	$out    = '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
		. '<title>' . esc_html( $name . ' floor map | ' . get_bloginfo( 'name' ) ) . '</title>'
		. '<style>body{margin:0;padding:16px;background:#fff;color:#1a1a1a;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
		. 'a{color:#00539b}h1{font-size:1.6em;margin:.2em 0 .5em}.wpfloors-full .wpfloors-svg{max-height:none}'
		. wpfloors_svg_css() . wpfloors_page_css() . '</style></head><body><main class="wpfloors wpfloors-full">';
	if ( $back ) {
		$out .= '<p><a href="' . esc_url( $back ) . '">Back to all floor maps</a></p>';
	}
	$out .= '<h1>' . esc_html( $name ) . '</h1>' . wpfloors_floor_body( $slug, $data[ $slug ], 2, '' );
	return $out . '</main></body></html>';
}

/* ------------------------------------------------------------------ *
 *  Admin page: wp-admin → Floor Maps
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_menu_page( 'Floor Maps', 'Floor Maps', 'edit_pages', 'wpfloors', 'wpfloors_admin_page', 'dashicons-location-alt', 31 );
} );

function wpfloors_admin_page() {
	if ( ! current_user_can( 'edit_pages' ) ) { return; }
	$floors = array();
	foreach ( wpfloors_floors() as $slug => $f ) {
		$floors[] = array( 'slug' => $slug, 'name' => $f['name'], 'vb' => wpfloors_vb( $slug ) );
	}
	$types = array();
	foreach ( wpfloors_types() as $slug => $t ) {
		$types[] = array_merge( array( 'slug' => $slug ), $t );
	}
	$cfg = array(
		'root'   => esc_url_raw( rest_url( 'wpfloors/v1/labels' ) ),
		'nonce'  => wp_create_nonce( 'wp_rest' ),
		'floors' => $floors,
		'types'  => $types,
		'data'   => wpfloors_get(),
	);

	echo '<div class="wrap"><h1>Floor Maps</h1>';
	echo '<p style="max-width:720px">Choose a floor, then click the map where a label should go. You can also use <strong>Add a label</strong> and type the position. Nothing changes on the website until you click <strong>Save</strong>. Show the maps on any page with the shortcode <code>[floor_maps]</code>.</p>';
	echo '<style>' . wpfloors_svg_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo wpfloors_admin_markup(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="wpfloors-stage">';
	foreach ( wpfloors_floors() as $slug => $f ) {
		echo '<div class="wpfloors-drawing" data-floor="' . esc_attr( $slug ) . '" hidden>'
			. wpfloors_svg( $slug, array(), $f['name'] . ' floor map. Click to place a label, or type its position in the form.' ) // phpcs:ignore WordPress.Security.EscapeOutput
			. '</div>';
	}
	echo '</div>';
	echo wpfloors_admin_table_markup(); // phpcs:ignore WordPress.Security.EscapeOutput
	// JSON_HEX_TAG keeps "</script>" or "<!--" in a label name from breaking out of this tag.
	echo '<script>window.WPFLOORS_ADMIN=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP ) . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo wpfloors_admin_script(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

function wpfloors_admin_markup() {
	return <<<'HTML'
<style>
  #wpfloors-top,#wpfloors-form,#wpfloors-bottom{max-width:960px}
  #wpfloors-top{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:14px 0 8px}
  #wpfloors-top label{font-weight:600}
  #wpfloors-mode{min-height:1.5em;margin:4px 0 8px;font-weight:600}
  .wpfloors-stage{max-width:960px;border:1px solid #c3c4c7;background:#fff;padding:4px}
  .wpfloors-stage .wpfloors-svg{cursor:crosshair;max-height:75vh}
  .wpfloors-stage .mk.is-pending path{stroke-dasharray:.3 .2}
  .wpfloors-stage .mk.is-active path{stroke-width:.3}
  #wpfloors-form{background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:4px 16px 12px;margin:12px 0}
  #wpfloors-form legend{font-size:1.1em;font-weight:600;padding:8px 0}
  #wpfloors-form .row{display:flex;flex-wrap:wrap;gap:12px 20px;margin:8px 0}
  #wpfloors-form label{display:block;font-weight:600;margin-bottom:3px}
  #wpfloors-form input[type=text]{width:320px;max-width:100%}
  #wpfloors-form input[type=number]{width:90px}
  #wpfloors-form .hint{color:#50575e;margin:4px 0}
  #wpfloors-error{color:#b32d2e;font-weight:600}
  #wpfloors-rows td{vertical-align:middle}
  #wpfloors-rows .button{margin:2px 4px 2px 0}
  #wpfloors-bottom{display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin:16px 0}
  #wpfloors-status{font-weight:600}
</style>
<div id="wpfloors-top">
  <label for="wpfloors-floor">Floor</label>
  <select id="wpfloors-floor"></select>
  <button type="button" class="button" id="wpfloors-add">Add a label</button>
</div>
<p id="wpfloors-mode" aria-live="polite"></p>
HTML;
}

function wpfloors_admin_table_markup() {
	return <<<'HTML'
<form id="wpfloors-form" hidden novalidate>
  <fieldset>
    <legend id="wpfloors-form-title">Add a label</legend>
    <div class="row">
      <div><label for="wpfloors-name">Name</label><input id="wpfloors-name" type="text" maxlength="100" autocomplete="off"></div>
      <div><label for="wpfloors-type">Type</label><select id="wpfloors-type"></select></div>
    </div>
    <div class="row">
      <div><label for="wpfloors-note">Note (optional)</label><input id="wpfloors-note" type="text" maxlength="300" autocomplete="off"></div>
    </div>
    <div class="row">
      <div><label for="wpfloors-x">Across (% from left)</label><input id="wpfloors-x" type="number" min="0" max="100" step="0.1"></div>
      <div><label for="wpfloors-y">Down (% from top)</label><input id="wpfloors-y" type="number" min="0" max="100" step="0.1"></div>
    </div>
    <p class="hint">Click the map to set the position, or type it in Across and Down. The dashed marker shows where the label will go.</p>
    <p id="wpfloors-error" role="alert"></p>
    <button type="submit" class="button button-primary" id="wpfloors-ok">Add label</button>
    <button type="button" class="button" id="wpfloors-cancel">Cancel</button>
  </fieldset>
</form>
<h2 id="wpfloors-table-h">Labels on this floor</h2>
<table class="widefat striped" style="max-width:960px" aria-labelledby="wpfloors-table-h">
  <thead><tr><th scope="col">Name</th><th scope="col">Type</th><th scope="col">Note</th><th scope="col">Actions</th></tr></thead>
  <tbody id="wpfloors-rows"></tbody>
</table>
<div id="wpfloors-bottom">
  <button type="button" class="button button-primary button-hero" id="wpfloors-save">Save</button>
  <span id="wpfloors-status" role="status"></span>
</div>
HTML;
}

/** The admin editor. Nowdoc => no PHP interpolation of $ in JS. */
function wpfloors_admin_script() {
	return <<<'HTML'
<script>
(function(){
"use strict";
var C=window.WPFLOORS_ADMIN;if(!C)return;
var NS="http://www.w3.org/2000/svg";
var data=C.data,types={},cur=C.floors[0].slug,form=null,moving=null,dirty=false,saving=false;
C.types.forEach(function(t){types[t.slug]=t;});
function $(id){return document.getElementById(id);}
function floorOf(slug){for(var i=0;i<C.floors.length;i++)if(C.floors[i].slug===slug)return C.floors[i];return null;}
function labels(){return data[cur]||(data[cur]=[]);}
function find(id){var a=labels();for(var i=0;i<a.length;i++)if(a[i].id===id)return a[i];return null;}
function uid(){return "l"+Date.now().toString(36)+Math.random().toString(36).slice(2,7);}
function num(n){return String(Math.round(n*100)/100);}
function svgEl(name,attrs){var e=document.createElementNS(NS,name);for(var k in attrs)if(attrs.hasOwnProperty(k))e.setAttribute(k,attrs[k]);return e;}
function drawing(){return document.querySelector('.wpfloors-drawing[data-floor="'+cur+'"]');}

/* Same marker as the PHP wpfloors_marker(). */
function marker(lb,vb,cls){
  var t=types[lb.type];if(!t)return null;
  var r=Math.max(vb[2],vb[3])*0.016,cx=vb[0]+lb.x/100*vb[2],cy=vb[1]+lb.y/100*vb[3],right=lb.x>65;
  var g=svgEl("g",{"class":"mk"+(cls?" "+cls:""),transform:"translate("+num(cx)+" "+num(cy)+") scale("+num(r)+")"});
  g.appendChild(svgEl("path",{d:t.shape}));
  var L=svgEl("text",{"class":"lt",x:t.tx,y:t.ty,"font-size":t.fs});L.textContent=t.letter;g.appendChild(L);
  var N=svgEl("text",{"class":"nm"+(right?" end":""),x:right?-1.7:1.7,y:0.45,"font-size":1.25});N.textContent=lb.name;g.appendChild(N);
  return g;
}
function drawMarkers(){
  var d=drawing();if(!d)return;
  var layer=d.querySelector(".wpfloors-labels"),vb=floorOf(cur).vb;
  while(layer.firstChild)layer.removeChild(layer.firstChild);
  labels().forEach(function(lb){
    if(form&&form.id===lb.id)return; // shown as the pending marker instead
    var g=marker(lb,vb,moving===lb.id?"is-active":"");if(g)layer.appendChild(g);
  });
  if(form){
    var p=pending();
    if(p){var g=marker(p,vb,"is-pending");if(g)layer.appendChild(g);}
  }
}
function pending(){
  var x=parseFloat($("wpfloors-x").value),y=parseFloat($("wpfloors-y").value);
  if(isNaN(x)||isNaN(y))return null;
  return {name:$("wpfloors-name").value||"New label",type:$("wpfloors-type").value,x:Math.max(0,Math.min(100,x)),y:Math.max(0,Math.min(100,y))};
}
function btn(text,label,fn){var b=document.createElement("button");b.type="button";b.className="button";b.textContent=text;b.setAttribute("aria-label",label);b.onclick=fn;return b;}
function renderRows(){
  var tb=$("wpfloors-rows");tb.innerHTML="";
  $("wpfloors-table-h").textContent="Labels on the "+floorOf(cur).name;
  var a=labels();
  if(!a.length){var tr=document.createElement("tr"),td=document.createElement("td");td.colSpan=4;td.textContent="No labels on this floor yet. Click the map, or use Add a label.";tr.appendChild(td);tb.appendChild(tr);return;}
  a.forEach(function(lb){
    var tr=document.createElement("tr");
    var th=document.createElement("th");th.scope="row";th.textContent=lb.name;tr.appendChild(th);
    var t1=document.createElement("td");t1.textContent=types[lb.type]?types[lb.type].label:lb.type;tr.appendChild(t1);
    var t2=document.createElement("td");t2.textContent=lb.note||"";tr.appendChild(t2);
    var t3=document.createElement("td");
    t3.appendChild(btn("Edit","Edit "+lb.name,function(e){openForm(lb.id,null,null,e.currentTarget);}));
    t3.appendChild(btn("Move","Move "+lb.name,function(){startMove(lb.id);}));
    t3.appendChild(btn("Delete","Delete "+lb.name,function(){remove(lb.id);}));
    tr.appendChild(t3);tb.appendChild(tr);
  });
}
function setMode(msg){$("wpfloors-mode").textContent=msg||"";}
function markDirty(){dirty=true;status("You have unsaved changes. Click Save to put them on the website.",false);}
function status(msg,bad){var s=$("wpfloors-status");s.textContent=msg;s.style.color=bad?"#b32d2e":(dirty?"#1d2327":"#007017");}
function showFloor(slug){
  closeForm(false);moving=null;setMode("");cur=slug;
  Array.prototype.forEach.call(document.querySelectorAll(".wpfloors-drawing"),function(d){d.hidden=d.getAttribute("data-floor")!==slug;});
  drawMarkers();renderRows();
}
function openForm(id,x,y,opener){
  moving=null;
  var lb=id?find(id):null;
  form={id:id,opener:opener||null};
  $("wpfloors-form-title").textContent=lb?"Edit label":"Add a label";
  $("wpfloors-ok").textContent=lb?"Update label":"Add label";
  $("wpfloors-name").value=lb?lb.name:"";
  $("wpfloors-type").value=lb?lb.type:"room";
  $("wpfloors-note").value=lb?lb.note:"";
  $("wpfloors-x").value=lb?lb.x:(x===null?50:x);
  $("wpfloors-y").value=lb?lb.y:(y===null?50:y);
  $("wpfloors-error").textContent="";
  $("wpfloors-form").hidden=false;
  setMode(lb?"Editing “"+lb.name+"”. Click the map to change its position.":"Adding a label. Click the map to change its position.");
  drawMarkers();
  $("wpfloors-name").focus();
}
function closeForm(restoreFocus){
  if(!form)return;
  var opener=form.opener;form=null;
  $("wpfloors-form").hidden=true;setMode("");drawMarkers();
  if(restoreFocus){if(opener&&document.body.contains(opener))opener.focus();else $("wpfloors-add").focus();}
}
function submitForm(e){
  e.preventDefault();
  var name=$("wpfloors-name").value.replace(/^\s+|\s+$/g,""),x=parseFloat($("wpfloors-x").value),y=parseFloat($("wpfloors-y").value);
  if(!name){$("wpfloors-error").textContent="Enter a name.";$("wpfloors-name").focus();return;}
  if(isNaN(x)||x<0||x>100){$("wpfloors-error").textContent="Across must be a number from 0 to 100.";$("wpfloors-x").focus();return;}
  if(isNaN(y)||y<0||y>100){$("wpfloors-error").textContent="Down must be a number from 0 to 100.";$("wpfloors-y").focus();return;}
  var vals={name:name,type:$("wpfloors-type").value,note:$("wpfloors-note").value.replace(/^\s+|\s+$/g,""),x:Math.round(x*100)/100,y:Math.round(y*100)/100};
  var lb=form.id?find(form.id):null;
  if(lb){for(var k in vals)if(vals.hasOwnProperty(k))lb[k]=vals[k];}
  else{vals.id=uid();labels().push(vals);}
  var msg=(lb?"Updated “":"Added “")+name+"”.";
  closeForm(true);renderRows();markDirty();setMode(msg);
}
function startMove(id){
  closeForm(false);
  var lb=find(id);if(!lb)return;
  moving=id;drawMarkers();
  setMode("Click the new spot on the map for “"+lb.name+"”. Press Escape to cancel. (To move it without a mouse, use Edit and change Across and Down.)");
}
function remove(id){
  var a=labels();
  for(var i=0;i<a.length;i++)if(a[i].id===id){var n=a[i].name;a.splice(i,1);
    if(form&&form.id===id)closeForm(false);if(moving===id)moving=null;
    drawMarkers();renderRows();markDirty();setMode("Deleted “"+n+"”. Click Save to remove it from the website.");$("wpfloors-add").focus();return;}
}
function pct(evt){
  var svg=drawing().querySelector("svg"),pt=svg.createSVGPoint();pt.x=evt.clientX;pt.y=evt.clientY;
  var p=pt.matrixTransform(svg.getScreenCTM().inverse()),vb=floorOf(cur).vb;
  return {x:Math.round(Math.max(0,Math.min(100,(p.x-vb[0])/vb[2]*100))*10)/10,y:Math.round(Math.max(0,Math.min(100,(p.y-vb[1])/vb[3]*100))*10)/10};
}
function onMapClick(evt){
  var p=pct(evt);
  if(moving){var lb=find(moving);moving=null;if(lb){lb.x=p.x;lb.y=p.y;markDirty();setMode("Moved “"+lb.name+"”.");}drawMarkers();return;}
  if(form){$("wpfloors-x").value=p.x;$("wpfloors-y").value=p.y;drawMarkers();return;}
  openForm(null,p.x,p.y,null);
}
function save(){
  if(saving)return;
  if(form){status("Finish or cancel the label you're editing first.",true);return;}
  saving=true;status("Saving…",false);
  var x=new XMLHttpRequest();
  x.open("POST",C.root);x.setRequestHeader("Content-Type","application/json");x.setRequestHeader("X-WP-Nonce",C.nonce);
  x.onload=function(){
    saving=false;
    var res=null;try{res=JSON.parse(x.responseText);}catch(e){}
    if(x.status>=200&&x.status<300&&res&&res.floors){data=res.floors;dirty=false;moving=null;drawMarkers();renderRows();status("Saved. It's live on the website. If you don't see it there, purge the cache.",false);}
    else{status("Save failed ("+x.status+"). Please try again, or reload the page.",true);}
  };
  x.onerror=function(){saving=false;status("Save failed. Check your connection and try again.",true);};
  x.send(JSON.stringify({floors:data}));
}

var fs=$("wpfloors-floor");
C.floors.forEach(function(f){var o=document.createElement("option");o.value=f.slug;o.textContent=f.name;fs.appendChild(o);});
fs.onchange=function(){showFloor(fs.value);};
var ts=$("wpfloors-type");
C.types.forEach(function(t){var o=document.createElement("option");o.value=t.slug;o.textContent=t.label;ts.appendChild(o);});
$("wpfloors-add").onclick=function(e){openForm(null,null,null,e.currentTarget);};
$("wpfloors-form").onsubmit=submitForm;
$("wpfloors-cancel").onclick=function(){closeForm(true);};
["wpfloors-name","wpfloors-type","wpfloors-x","wpfloors-y"].forEach(function(id){$(id).addEventListener("input",drawMarkers);$(id).addEventListener("change",drawMarkers);});
Array.prototype.forEach.call(document.querySelectorAll(".wpfloors-drawing svg"),function(s){s.addEventListener("click",onMapClick);});
document.addEventListener("keydown",function(e){
  if(e.key!=="Escape"&&e.key!=="Esc")return;
  if(moving){moving=null;drawMarkers();setMode("Move cancelled.");}
  else if(form){closeForm(true);}
});
$("wpfloors-save").onclick=save;
window.addEventListener("beforeunload",function(e){if(dirty){e.preventDefault();e.returnValue="";}});
showFloor(cur);
})();
</script>
HTML;
}

/* ------------------------------------------------------------------ *
 *  The drawings. Hand-traced from the August 2022 floor plan deck photos,
 *  straightened by eye. No text on purpose: every label comes from staff.
 *  Classes: fp floor, w outer wall, i inner wall, t thin partition, s solid
 *  (pillars, columns), d door swing, st stairs, el elevator.
 * ------------------------------------------------------------------ */

function wpfloors_drawing( $slug ) {
	switch ( $slug ) {
		case 'main':             $svg = wpfloors_drawing_main(); break;
		case 'periodicals':      $svg = wpfloors_drawing_periodicals(); break;
		case 'stacks':           $svg = wpfloors_drawing_stacks(); break;
		case 'first-mezzanine':  $svg = wpfloors_drawing_first_mezzanine(); break;
		case 'second-mezzanine': $svg = wpfloors_drawing_second_mezzanine(); break;
		default:                 return '';
	}
	// Drop the source comments and line breaks from the page output.
	return preg_replace( array( '/<!--.*?-->/s', '/\s*\n\s*/' ), array( '', '' ), $svg );
}

function wpfloors_drawing_main() {
	return <<<'SVG'
<path class="fp" d="M31 210H397V94H336V46H462V94H706V222H704V462H825V558H691V742H597V692H384V742H290V566H31Z"/>
<!-- exterior -->
<path class="w" d="M47 210H390M31 225V566H290V742H388V692H404M404 692H470"/>
<path class="w" d="M336 94V46H400M420 46H462V94H540M560 94H706V222M704 222V462H825V558H691V742H597V692H505"/>
<path class="w" d="M336 94H397V97"/>
<path class="w" d="M397 118V210"/>
<!-- restroom + elevator, top -->
<path class="i" d="M397 46V58M397 78V94"/>
<rect class="el" x="424" y="50" width="34" height="40"/><path class="el" d="M424 50L458 90M458 50L424 90"/>
<path class="i" d="M424 46V60M424 82V94"/>
<!-- stair tower -->
<path class="i" d="M400 120H430L455 148V198"/>
<rect class="s" x="393" y="198" width="25" height="18"/>
<rect class="s" x="432" y="198" width="36" height="18"/>
<rect class="s" x="512" y="198" width="28" height="18"/>
<rect class="s" x="555" y="198" width="155" height="22"/>
<path class="d" d="M548 198A16 16 0 0 0 532 214"/>
<rect class="st" x="462" y="98" width="72" height="24"/><path class="st" d="M470 98V122M478 98V122M486 98V122M494 98V122M502 98V122M510 98V122M518 98V122M526 98V122M462 122L486 98"/>
<rect class="st" x="566" y="98" width="128" height="98"/><rect class="st" x="572" y="126" width="116" height="66"/>
<path class="st" d="M574 98V126M582 98V126M590 98V126M626 98V126M634 98V126M642 98V126M650 98V126M658 98V126M666 98V126M682 98V126M690 98V126"/>
<!-- reading / reference room -->
<path class="d" d="M47 210A16 16 0 0 0 31 226"/>
<path class="d" d="M325 210A15 15 0 0 1 340 225"/>
<path class="t" d="M325 205V214M340 205V214"/>
<rect class="st" x="56" y="214" width="38" height="40"/><path class="st" d="M62 214V254M68 214V254M74 214V254M80 214V254M86 214V254M56 230H94M56 236H94"/>
<path class="w" d="M34 258H140"/><rect class="s" x="125" y="213" width="13" height="32"/>
<path class="d" d="M138 245A12 12 0 0 1 126 257"/>
<rect class="s" x="245" y="330" width="8" height="9"/>
<path class="w" d="M386 210V330M386 358V432"/>
<path class="d" d="M390 330A14 14 0 0 1 404 344M390 358A14 14 0 0 0 404 344"/>
<rect class="s" x="345" y="432" width="47" height="20"/>
<path class="i" d="M388 452V488H420"/>
<path class="i" d="M100 446H318M318 446V440"/>
<path class="d" d="M320 446A20 20 0 0 1 340 466"/>
<!-- small rooms, lower left -->
<path class="t" d="M104 446V472M104 480V498H150"/>
<path class="d" d="M108 452A12 12 0 0 0 120 464"/>
<path class="i" d="M150 446V566"/>
<path class="i" d="M34 500H80M78 500V566"/>
<path class="d" d="M76 506A16 16 0 0 1 60 522"/>
<!-- Woolley classroom east wall + closet -->
<path class="i" d="M316 446V496L304 510V528"/>
<path class="d" d="M304 528A16 16 0 0 1 320 544"/>
<path class="t" d="M298 536H318V562M298 536V548"/>
<path class="d" d="M322 548A14 14 0 0 1 336 562"/>
<path class="w" d="M290 566H318M337 566H470"/>
<!-- circulation office -->
<path class="i" d="M388 566V650"/>
<path class="d" d="M392 690A18 18 0 0 1 406 672"/>
<path class="i" d="M388 692V700"/>
<!-- circulation desk / entrance -->
<path class="i" d="M404 570V630"/>
<path class="i" d="M400 647H470V692M404 647V665"/>
<path class="d" d="M472 692A16 16 0 0 1 488 676M505 692A16 16 0 0 0 489 676"/>
<path class="i" d="M508 692V647H590"/>
<path class="w" d="M510 566H590V548"/>
<path class="d" d="M592 568A20 20 0 0 1 612 548"/>
<!-- stairs, south-east -->
<path class="i" d="M610 566H691M691 566V650M610 646H691M612 566V600"/>
<rect class="st" x="616" y="576" width="50" height="66"/><rect class="st" x="626" y="604" width="44" height="22"/>
<path class="st" d="M622 576V604M628 576V604M634 576V604M640 576V604M646 576V604M652 576V604M658 576V604M634 626V642M640 626V642M646 626V642M652 626V642M658 626V642M616 604L666 576"/>
<path class="d" d="M596 646A20 20 0 0 1 616 666"/>
<path class="i" d="M597 650V692"/>
<!-- atrium core -->
<path class="i" d="M593 232V385"/>
<path class="d" d="M598 380A10 10 0 0 1 608 390"/>
<rect class="s" x="585" y="405" width="15" height="85"/>
<path class="d" d="M600 412A12 12 0 0 1 612 424M600 452A12 12 0 0 1 612 464"/>
<rect class="s" x="512" y="480" width="88" height="10"/>
<path class="t" d="M555 490V560M592 490V560"/>
<rect class="el" x="558" y="497" width="32" height="33"/><path class="el" d="M558 497L590 530M590 497L558 530"/>
<!-- student employment / research librarian office -->
<path class="t" d="M598 228H704"/>
<path class="d" d="M600 230A14 14 0 0 1 614 244"/>
<path class="t" d="M598 283H628M642 283H704M642 283V272"/>
<path class="d" d="M642 272A12 12 0 0 0 630 284"/>
<path class="t" d="M648 406H704M648 406V506"/>
<path class="d" d="M650 412A14 14 0 0 1 664 426"/>
<path class="t" d="M616 430H648"/>
<!-- greenaway room + small partitions -->
<path class="t" d="M648 462H704"/>
<path class="t" d="M618 491H660M620 491V506M646 491V506M660 482V508"/>
<path class="t" d="M620 532H662V546M620 532V546"/>
<path class="i" d="M652 506V540"/>
<!-- atrium columns -->
<rect class="s" x="448" y="268" width="7" height="7"/><rect class="s" x="520" y="268" width="7" height="7"/>
<rect class="s" x="448" y="322" width="7" height="7"/><rect class="s" x="520" y="322" width="7" height="7"/>
<rect class="s" x="448" y="378" width="7" height="7"/><rect class="s" x="520" y="378" width="7" height="7"/>
<rect class="s" x="448" y="431" width="7" height="7"/><rect class="s" x="520" y="431" width="7" height="7"/>
SVG;
}

function wpfloors_drawing_periodicals() {
	return <<<'SVG'
<path class="fp" d="M13 272H115V240H242V102H445V153H816V632H674V801H578V758H380V801H277V632H13Z"/>
<!-- exterior -->
<path class="w" d="M42 272H115V240H242V185M242 160V102H300M316 102H445V153H525M545 153H816V632H674V801H578V758H380V801H277V632H13V285"/>
<path class="d" d="M42 272A20 20 0 0 0 22 252M242 160A20 20 0 0 0 222 180M525 153A18 18 0 0 1 543 135"/>
<!-- north-west: interlibrary services, stair, small rooms -->
<rect class="st" x="46" y="280" width="36" height="40"/><path class="st" d="M52 280V320M58 280V320M64 280V320M70 280V320M76 280V320M46 300H82M46 305H82"/>
<path class="i" d="M13 324H95M115 272H300M118 276V335"/>
<path class="d" d="M118 335A18 18 0 0 0 100 317"/>
<path class="t" d="M182 276V335M135 335H225"/>
<path class="d" d="M228 335A14 14 0 0 1 238 320"/>
<rect class="st" x="128" y="244" width="40" height="26"/><path class="st" d="M134 244V270M140 244V270M146 244V270M152 244V270M158 244V270M128 270L152 244"/>
<path class="i" d="M242 185V322"/>
<path class="i" d="M242 157H300"/>
<rect class="st" x="264" y="160" width="20" height="24"/><path class="st" d="M268 160V184M272 160V184M276 160V184M280 160V184"/>
<path class="t" d="M242 186H286M245 198H265M245 221H265M245 244H265M286 205V270M286 205H300"/>
<!-- restroom, newspapers -->
<path class="i" d="M319 102V157H365M381 157V102"/>
<path class="t" d="M319 183H376M319 205H376M319 183V205"/>
<path class="w" d="M319 270H376"/>
<path class="i" d="M382 180V272"/>
<path class="t" d="M369 278V395"/>
<path class="w" d="M377 276V520"/>
<path class="i" d="M400 272H460"/>
<!-- elevator + computer connection -->
<rect class="el" x="404" y="106" width="33" height="43"/><path class="el" d="M404 106L437 149M437 106L404 149"/>
<path class="i" d="M441 102V153"/>
<path class="t" d="M438 157V205L455 225M478 157V225M500 225L520 202V157M466 210V225M490 210V225"/>
<path class="d" d="M455 225A12 12 0 0 1 466 212M500 225A12 12 0 0 0 489 212M507 230A18 18 0 0 1 520 250"/>
<!-- stair tower -->
<rect class="st" x="548" y="160" width="92" height="24"/><path class="st" d="M586 160V184M592 160V184M598 160V184M604 160V184M610 160V184M616 160V184M622 160V184M628 160V184M634 160V184M550 184L566 160"/>
<path class="t" d="M548 186H640"/>
<rect class="st" x="668" y="160" width="50" height="22"/><path class="st" d="M674 160V182M680 160V182M686 160V182M692 160V182M698 160V182M704 160V182M710 160V182M664 186V262M669 186V262M669 186H720V214H669"/>
<rect class="st" x="740" y="160" width="46" height="22"/><path class="st" d="M746 160V182M752 160V182M758 160V182M764 160V182M770 160V182M776 160V182M740 182L746 210L760 230V262M746 182L752 208L767 228V262"/>
<rect class="s" x="505" y="258" width="311" height="24"/>
<!-- east side: staff lounge, seminar room, closed storage -->
<rect class="st" x="535" y="285" width="20" height="20"/><path class="st" d="M540 285V305M545 285V305M550 285V305"/>
<path class="i" d="M693 282V325M650 323H816M768 282V325"/>
<rect class="st" x="675" y="295" width="15" height="22"/><path class="st" d="M680 295V317M685 295V317"/>
<path class="d" d="M618 282A20 20 0 0 1 638 302M655 297A16 16 0 0 1 671 313"/>
<path class="w" d="M577 305V545"/>
<path class="t" d="M585 325H602M585 330V460M585 360H612M585 410H612M585 455H612M615 345V375"/>
<path class="i" d="M615 455V520M630 420H690M690 325V520M640 520H816M636 520V568"/>
<path class="d" d="M615 400A16 16 0 0 1 631 416M615 520A20 20 0 0 1 595 500"/>
<!-- south-east: elevator, stairs, restroom -->
<rect class="el" x="553" y="556" width="24" height="29"/><path class="el" d="M553 556L577 585M577 556L553 585"/>
<path class="t" d="M515 551H550V590H582"/>
<path class="d" d="M582 556A20 20 0 0 1 600 574"/>
<rect class="st" x="525" y="600" width="45" height="25"/><path class="st" d="M531 600V625M537 600V625M543 600V625M549 600V625M555 600V625M561 600V625"/>
<rect class="st" x="572" y="600" width="32" height="25"/><path class="st" d="M578 600V625M584 600V625M590 600V625M596 600V625"/>
<rect class="t" x="640" y="605" width="20" height="20"/>
<path class="i" d="M277 632H315M337 632H465M495 632H540M560 632H674M536 632V652"/>
<path class="d" d="M315 632A18 18 0 0 1 333 614"/>
<rect class="s" x="367" y="635" width="16" height="50"/>
<rect class="st" x="600" y="638" width="52" height="64"/><path class="st" d="M600 646H652M600 654H652M600 662H652M600 670H652M600 678H652M600 686H652M610 638L652 702"/>
<path class="t" d="M600 704H674M600 716H640V730M618 716V730"/>
<!-- south rooms -->
<path class="i" d="M380 707H392M405 707H466M480 707H520M452 707V752M534 680V752M534 682H556M580 700V752"/>
<path class="d" d="M392 707A13 13 0 0 1 405 694M520 707A14 14 0 0 1 534 693M556 702A16 16 0 0 1 572 718"/>
<path class="t" d="M552 716H576V740M502 728V745"/>
<path class="w" d="M380 758H578"/>
<path class="i" d="M580 750H674"/>
<path class="d" d="M580 752A20 20 0 0 1 560 732"/>
<rect class="s" x="419" y="796" width="13" height="13"/><rect class="s" x="453" y="796" width="13" height="13"/><rect class="s" x="487" y="796" width="13" height="13"/><rect class="s" x="522" y="796" width="13" height="13"/>
<!-- west: reading room, offices, cataloging -->
<path class="t" d="M13 392H104"/>
<path class="i" d="M128 392V510M13 450H128"/>
<path class="d" d="M104 392A16 16 0 0 1 120 408"/>
<path class="t" d="M73 452V510M13 510H52M88 510H128"/>
<path class="d" d="M52 510A16 16 0 0 1 68 494M88 510A14 14 0 0 0 76 497"/>
<path class="i" d="M240 392V510M258 394H377M258 510H377"/>
<path class="d" d="M242 398A16 16 0 0 1 258 414M242 506A16 16 0 0 0 258 490"/>
<path class="d" d="M380 518A18 18 0 0 1 398 536"/>
<rect class="t" x="371" y="533" width="14" height="18"/>
<path class="i" d="M380 552H408V632"/>
<path class="t" d="M136 570H230M182 570V632"/>
<path class="i" d="M124 582V632M242 582V632"/>
<path class="d" d="M126 572A14 14 0 0 1 140 586M240 572A14 14 0 0 0 226 586"/>
<!-- current periodicals columns -->
<rect class="s" x="434" y="327" width="7" height="7"/><rect class="s" x="507" y="327" width="7" height="7"/>
<rect class="s" x="434" y="384" width="7" height="7"/><rect class="s" x="507" y="384" width="7" height="7"/>
<rect class="s" x="434" y="438" width="7" height="7"/><rect class="s" x="507" y="438" width="7" height="7"/>
<rect class="s" x="434" y="493" width="7" height="7"/><rect class="s" x="507" y="493" width="7" height="7"/>
SVG;
}

/* The Stacks Level is drawn on two boards. The archives piece is joined to the rest at the
   "MATCH LINE" by the translate() below. */
function wpfloors_drawing_stacks() {
	return <<<'SVG'
<g transform="translate(160 831)"><path class="fp" d="M295 664H403V622H523V642H590V1095H255V998H295Z"/></g>
<path class="fp" d="M744 290H951V102H1018V77H1243V640H1332V1492H748V1345H744ZM38 1283H290V1228H744V1345H717V1372H655V1400L687 1440V1453H562V1402H38Z"/>
<!-- stacks: exterior -->
<path class="w" d="M744 290H951V102M975 102H1018V77H1243V640H1332V1492H1213M1185 1492H900M872 1492H748V1345M744 1300V290M744 1325V1345"/>
<path class="d" d="M872 1492A18 18 0 0 1 890 1474M1213 1492A18 18 0 0 0 1195 1474M744 1300A20 20 0 0 1 764 1320"/>
<!-- quartos / folios / stacks P-Z -->
<path class="i" d="M1130 196V188H1088V230H1130V222"/>
<path class="d" d="M1130 196A18 18 0 0 1 1146 210"/>
<rect class="s" x="1002" y="337" width="14" height="14"/>
<path class="i" d="M1006 515V525H1119V600M1013 525V582H1119M1119 515H1240"/>
<rect class="st" x="1055" y="530" width="45" height="48"/><path class="st" d="M1062 530V578M1069 530V578M1076 530V578M1083 530V578M1090 530V578M1035 578L1095 520"/>
<path class="d" d="M1013 560A18 18 0 0 0 995 578M1013 600A16 16 0 0 1 1029 616"/>
<path class="t" d="M1013 612V634M1122 612V634"/>
<path class="w" d="M744 634H853M878 634H1240"/>
<!-- study rooms, listening and viewing rooms -->
<path class="i" d="M744 1342H858M920 1342H1012M1060 1342H1155"/>
<rect class="s" x="888" y="1334" width="10" height="12"/><rect class="s" x="1032" y="1334" width="10" height="12"/><rect class="s" x="1172" y="1334" width="10" height="12"/>
<path class="d" d="M858 1345A20 20 0 0 1 878 1365M1012 1345A18 18 0 0 1 1030 1363M1155 1345A16 16 0 0 1 1171 1361"/>
<path class="i" d="M893 1340V1460M967 1342V1460M1037 1340V1460M1112 1342V1460M1180 1340V1460M893 1400H1182M1004 1400V1460M1077 1400V1460M1150 1400V1460"/>
<path class="i" d="M893 1460H948M1027 1460H1055M1100 1460H1127M1175 1460H1182"/>
<path class="d" d="M948 1460A18 18 0 0 1 966 1442M1004 1460A20 20 0 0 1 984 1440M1037 1460A16 16 0 0 1 1053 1444M1077 1460A20 20 0 0 1 1057 1440M1112 1460A14 14 0 0 1 1126 1446M1150 1460A22 22 0 0 1 1128 1438"/>
<!-- music wing -->
<path class="w" d="M744 1228H290V1283H38V1402H562V1453M562 1345V1370"/>
<path class="w" d="M717 1345V1372H655V1400L687 1440V1453"/>
<path class="i" d="M362 1343H400M417 1343H744M622 1343V1402H562"/>
<path class="i" d="M386 1232V1343M559 1232V1343"/>
<path class="d" d="M342 1343A18 18 0 0 1 360 1325M400 1343A17 17 0 0 0 417 1326"/>
<path class="i" d="M290 1286H342V1343"/>
<rect class="el" x="252" y="1290" width="33" height="46"/><path class="el" d="M252 1290L285 1336M285 1290L252 1336"/>
<path class="i" d="M245 1342H318M287 1287V1336"/>
<path class="t" d="M249 1290V1310"/>
<path class="i" d="M139 1285V1347H215V1310"/>
<path class="d" d="M215 1290A18 18 0 0 1 230 1308"/>
<path class="t" d="M139 1347V1352H158M197 1352H216M139 1365V1402M215 1365V1402"/>
<path class="d" d="M158 1352A16 16 0 0 1 142 1368M197 1352A16 16 0 0 0 213 1368"/>
<rect class="st" x="580" y="1346" width="38" height="28"/><path class="st" d="M586 1346V1374M592 1346V1374M598 1346V1374M604 1346V1374M610 1346V1374M580 1374L606 1346"/>
<rect class="st" x="660" y="1346" width="55" height="25"/><path class="st" d="M667 1346V1371M674 1346V1371M681 1346V1371M688 1346V1371M695 1346V1371M702 1346V1371M709 1346V1371"/>
<!-- archives (drawn on its own board; joined here at the match line) -->
<g transform="translate(160 831)">
<path class="w" d="M523 622V642H590V1095H410V1092H255V998H295V664H403V622"/>
<path class="i" d="M403 645H455V664M403 664H455"/>
<path class="d" d="M455 645A18 18 0 0 1 473 663"/>
<path class="i" d="M476 642V735H545M580 735H590"/>
<rect class="s" x="528" y="645" width="10" height="14"/>
<path class="d" d="M545 735A18 18 0 0 1 563 717"/>
<path class="i" d="M435 664V732M436 765V962H582"/>
<path class="d" d="M437 732A16 16 0 0 1 453 748"/>
<path class="t" d="M298 692H360M362 670V692M387 670V695M298 780H408M390 730V760M392 742H420"/>
<path class="d" d="M408 780A18 18 0 0 1 426 798M392 730A14 14 0 0 1 406 744"/>
<path class="i" d="M370 960H418M372 960V1015M298 1003H370M302 998V1045"/>
<path class="d" d="M372 1015A18 18 0 0 1 390 1033M302 1045A16 16 0 0 1 318 1061"/>
<rect class="el" x="264" y="1006" width="33" height="34"/><path class="el" d="M264 1006L297 1040M297 1006L264 1040"/>
<path class="t" d="M373 1035V1088M305 1065V1090"/>
<rect class="st" x="315" y="1065" width="40" height="24"/><path class="st" d="M335 1065V1089M340 1065V1089M345 1065V1089M350 1065V1089M310 1092L335 1065"/>
</g>
SVG;
}

function wpfloors_drawing_first_mezzanine() {
	return <<<'SVG'
<path class="fp" d="M50 42H145V76H243V168H328V350H416V430H318V563H18V432H92V76H50Z"/>
<!-- exterior -->
<path class="w" d="M92 70H50V42H145V76H243V168H328V350H416V430H318V563H240M220 563H95M55 563H18V432H92V76"/>
<path class="d" d="M95 563A20 20 0 0 0 75 543M220 563A20 20 0 0 1 240 543"/>
<!-- restroom, elevator, stair tower -->
<path class="i" d="M50 70H80M92 42V58"/>
<path class="d" d="M80 70A12 12 0 0 1 92 58"/>
<rect class="el" x="114" y="46" width="26" height="28"/><path class="el" d="M114 46L140 74M140 46L114 74"/>
<path class="i" d="M110 42V56M110 82V90H143V160"/>
<rect class="st" x="99" y="95" width="13" height="52"/><path class="st" d="M99 103H112M99 111H112M99 119H112M99 127H112M99 135H112M99 143H112"/>
<rect class="st" x="148" y="78" width="16" height="20"/><path class="st" d="M152 78V98M156 78V98M160 78V98"/>
<rect class="st" x="170" y="78" width="26" height="20"/><path class="st" d="M175 78V98M180 78V98M185 78V98M190 78V98"/>
<path class="t" d="M146 92H215V150M220 100V150"/>
<path class="st" d="M220 80H240M220 86H240M220 92H240"/>
<rect class="s" x="88" y="150" width="12" height="16"/><rect class="s" x="133" y="150" width="12" height="16"/><rect class="s" x="185" y="150" width="58" height="16"/>
<!-- fine arts hall -->
<path class="i" d="M243 168V340"/>
<rect class="s" x="134" y="202" width="7" height="7"/><rect class="s" x="189" y="202" width="7" height="7"/>
<rect class="s" x="134" y="243" width="7" height="7"/><rect class="s" x="189" y="243" width="7" height="7"/>
<rect class="s" x="134" y="287" width="7" height="7"/><rect class="s" x="189" y="287" width="7" height="7"/>
<rect class="s" x="134" y="331" width="7" height="7"/><rect class="s" x="189" y="331" width="7" height="7"/>
<!-- east offices -->
<rect class="st" x="243" y="175" width="16" height="20"/><path class="st" d="M247 175V195M251 175V195M255 175V195"/>
<path class="t" d="M281 172V192M283 205H325M281 200V214M283 243H325M281 238V250M283 275H325M281 268V282M283 305H325M281 300V312"/>
<rect class="s" x="264" y="207" width="5" height="5"/><rect class="s" x="264" y="243" width="5" height="5"/><rect class="s" x="264" y="273" width="5" height="5"/><rect class="s" x="264" y="303" width="5" height="5"/>
<!-- band of rooms under the hall -->
<path class="w" d="M92 374H150V432"/>
<path class="i" d="M150 374H243V340"/>
<rect class="st" x="243" y="340" width="14" height="30"/><path class="st" d="M243 346H257M243 352H257M243 358H257M243 364H257"/>
<path class="t" d="M165 374V398H185M120 402V432"/>
<path class="d" d="M120 402A16 16 0 0 1 136 418"/>
<rect class="st" x="186" y="376" width="24" height="22"/><path class="st" d="M186 382H210M186 388H210M186 394H210"/>
<rect class="el" x="215" y="378" width="23" height="20"/><path class="el" d="M215 378L238 398M238 378L215 398"/>
<path class="i" d="M213 400V432M243 374V400"/>
<path class="i" d="M283 340V430"/>
<path class="d" d="M286 392A16 16 0 0 1 302 408"/>
<rect class="t" x="265" y="400" width="15" height="11"/>
<!-- clark room -->
<path class="i" d="M18 432H45M57 432H122M135 432H197M210 432H220"/>
<path class="t" d="M55 522H195M57 522V563M92 522V563"/>
<rect class="st" x="60" y="530" width="20" height="28"/><path class="st" d="M60 536H80M60 542H80M60 548H80M60 554H80"/>
<!-- stairs and college learning center -->
<path class="i" d="M220 432H275M222 435V492M318 432V500"/>
<path class="t" d="M222 445H240V470H222"/>
<rect class="st" x="265" y="445" width="30" height="30"/>
<rect class="st" x="295" y="448" width="18" height="26"/><path class="st" d="M295 454H313M295 460H313M295 466H313"/>
<path class="i" d="M220 497H318M222 497V563"/>
<path class="d" d="M242 497A16 16 0 0 0 226 513"/>
<circle class="s" cx="98" cy="568" r="5"/><circle class="s" cx="124" cy="568" r="5"/><circle class="s" cx="149" cy="568" r="5"/><circle class="s" cx="174" cy="568" r="5"/><circle class="s" cx="200" cy="568" r="5"/>
SVG;
}

function wpfloors_drawing_second_mezzanine() {
	return <<<'SVG'
<path class="fp" d="M532 42H628V78H729V432H802V562H504V432H582V78H532Z"/>
<!-- exterior -->
<path class="w" d="M582 72H532V42H628V78H729V432H802V562H504V432H582V72"/>
<!-- restroom, elevator, stair, upper room -->
<path class="i" d="M532 70H565M578 42V58"/>
<path class="d" d="M565 70A13 13 0 0 1 578 57"/>
<rect class="el" x="598" y="46" width="26" height="28"/><path class="el" d="M598 46L624 74M624 46L598 74"/>
<path class="i" d="M594 42V56M594 82V90H624"/>
<rect class="st" x="598" y="97" width="13" height="46"/><path class="st" d="M598 105H611M598 113H611M598 121H611M598 129H611M598 137H611"/>
<path class="i" d="M622 92V165M582 152H620"/>
<rect class="s" x="618" y="155" width="16" height="15"/><rect class="s" x="670" y="155" width="59" height="15"/>
<!-- offices and fine arts stacks -->
<path class="t" d="M584 202H610V174M697 174V202H727"/>
<path class="d" d="M610 186A12 12 0 0 1 598 174M697 186A12 12 0 0 0 709 174"/>
<rect class="s" x="621" y="205" width="7" height="7"/><rect class="s" x="677" y="205" width="7" height="7"/>
<rect class="s" x="621" y="247" width="7" height="7"/><rect class="s" x="677" y="247" width="7" height="7"/>
<rect class="s" x="621" y="290" width="7" height="7"/><rect class="s" x="677" y="290" width="7" height="7"/>
<rect class="s" x="621" y="330" width="7" height="7"/><rect class="s" x="677" y="330" width="7" height="7"/>
<path class="i" d="M582 374H612M630 374H650M660 374H675M700 374H729"/>
<path class="i" d="M604 374V432M640 374V432M668 374V432"/>
<path class="t" d="M640 397H668"/>
<rect class="st" x="683" y="377" width="16" height="25"/><path class="st" d="M683 383H699M683 389H699M683 395H699"/>
<rect class="el" x="703" y="380" width="22" height="22"/><path class="el" d="M703 380L725 402M725 380L703 402"/>
<path class="i" d="M700 405H729"/>
<!-- south wing -->
<path class="i" d="M712 432V526M504 525H712"/>
<path class="t" d="M720 437H796V497H720"/>
<path class="i" d="M722 500V562"/>
<path class="i" d="M564 525V562"/>
<rect class="st" x="566" y="538" width="12" height="22"/><path class="st" d="M566 544H578M566 550H578M566 556H578"/>
SVG;
}
