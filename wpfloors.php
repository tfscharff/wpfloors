<?php
/**
 * Plugin Name:       Floor Maps
 * Description:       Accessible floor maps for a library or any building. Staff upload an SVG map for each floor and add every label (rooms, collections, restrooms, exits and more) under Floor Maps in wp-admin. Show the maps with the [floor_maps] shortcode.
 * Version:           2.1.1
 * Author:            Madeleine Clark Wallace Library
 * License:           GPL-2.0+
 * Requires at least: 5.6
 * Requires PHP:      7.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WPFLOORS_OPTION', 'wpfloors' );
define( 'WPFLOORS_MAX_FLOORS', 30 );
define( 'WPFLOORS_MAX_LABELS', 300 ); // per floor
define( 'WPFLOORS_MAX_SVG_BYTES', 5 * 1024 * 1024 );

/* ------------------------------------------------------------------ *
 *  Label types
 * ------------------------------------------------------------------ */

/**
 * Label types. Color never carries meaning: each type has its own marker shape and letter,
 * drawn black on white. name_on_map is the default for a label's "Show name on the map"
 * checkbox: the symbol alone says what a restroom or an exit is, but not which room is which. Shapes are SVG paths around (0,0) at radius ~1; tx/ty/fs place the letter.
 */
function wpfloors_types() {
	return array(
		'room'       => array( 'label' => 'Room or service',       'group' => 'rooms', 'name_on_map' => true,        'letter' => 'R',  'tx' => 0,     'ty' => 0.34, 'fs' => 0.95, 'shape' => 'M1 0A1 1 0 1 1-1 0A1 1 0 1 1 1 0Z' ),
		'collection' => array( 'label' => 'Collection',            'group' => 'collections', 'name_on_map' => true,  'letter' => 'C',  'tx' => 0,     'ty' => 0.34, 'fs' => 0.95, 'shape' => 'M-.9-.9H.9V.9H-.9Z' ),
		'restroom'   => array( 'label' => 'Restroom',              'group' => 'facilities', 'name_on_map' => false,  'letter' => 'WC', 'tx' => 0,     'ty' => 0.28, 'fs' => 0.78, 'shape' => 'M-.55-.85H.55A.85 .85 0 0 1 .55 .85H-.55A.85 .85 0 0 1-.55-.85Z' ),
		'elevator'   => array( 'label' => 'Elevator',              'group' => 'facilities', 'name_on_map' => false,  'letter' => 'EL', 'tx' => 0,     'ty' => 0.28, 'fs' => 0.78, 'shape' => 'M1.1 0L.55 .95H-.55L-1.1 0L-.55-.95H.55Z' ),
		'stairs'     => array( 'label' => 'Stairs',                'group' => 'facilities', 'name_on_map' => false,  'letter' => 'ST', 'tx' => 0,     'ty' => 0.4,  'fs' => 0.7,  'shape' => 'M0-1.25L1.35 .85H-1.35Z' ),
		'exit'       => array( 'label' => 'Exit',                  'group' => 'emergency', 'name_on_map' => false,   'letter' => 'EX', 'tx' => -0.12, 'ty' => 0.28, 'fs' => 0.78, 'shape' => 'M-1.05-.85H.5L1.2 0L.5 .85H-1.05Z' ),
		'rescue'     => array( 'label' => 'Emergency rescue area', 'group' => 'emergency', 'name_on_map' => false,   'letter' => 'RA', 'tx' => 0,     'ty' => 0.28, 'fs' => 0.78, 'shape' => 'M-.45-1.05H.45L1.05-.45V.45L.45 1.05H-.45L-1.05 .45V-.45Z' ),
		'kit'        => array( 'label' => 'Emergency kit',         'group' => 'emergency', 'name_on_map' => false,   'letter' => 'K',  'tx' => 0,     'ty' => 0.34, 'fs' => 0.95, 'shape' => 'M0-1.2L1.2 0L0 1.2L-1.2 0Z' ),
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
 *  Data: one option, {floors: [{slug, name, image}], labels: {floor slug: [...]}}.
 *  image is the attachment ID of the floor's SVG map.
 *  Every write goes through wpfloors_sanitize().
 * ------------------------------------------------------------------ */

add_filter( 'sanitize_option_' . WPFLOORS_OPTION, 'wpfloors_sanitize' );

/** The saved floors and labels. A new install starts with no floors. */
function wpfloors_get() {
	return wpfloors_sanitize( get_option( WPFLOORS_OPTION, array( 'floors' => array(), 'labels' => array() ) ) );
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

/** Whether a label's name shows on the map: a yes/no value, or the type's default when unclear. */
function wpfloors_show( $v, $default ) {
	if ( is_bool( $v ) ) { return $v; }
	if ( is_int( $v ) || is_string( $v ) ) {
		$b = filter_var( $v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( null !== $b ) { return $b; }
	}
	return (bool) $default;
}

/** A URL-safe floor slug: lowercase letters, digits and hyphens. */
function wpfloors_slug( $v ) {
	$s = is_string( $v ) ? strtolower( $v ) : '';
	return substr( trim( preg_replace( '/[^a-z0-9]+/', '-', $s ), '-' ), 0, 40 );
}

/** Whitelist and validate incoming data so only clean floors and labels are stored. Drops bad rows. */
function wpfloors_sanitize( $in ) {
	$in     = is_array( $in ) ? $in : array();
	$floors = array();
	$seen   = array();
	foreach ( isset( $in['floors'] ) && is_array( $in['floors'] ) ? $in['floors'] : array() as $f ) {
		if ( count( $floors ) >= WPFLOORS_MAX_FLOORS ) { break; }
		if ( ! is_array( $f ) ) { continue; }
		$name = wpfloors_text( isset( $f['name'] ) ? $f['name'] : '', 60 );
		if ( '' === $name ) { continue; }
		$img  = isset( $f['image'] ) && is_numeric( $f['image'] ) ? max( 0, (int) $f['image'] ) : 0;
		$base = wpfloors_slug( isset( $f['slug'] ) ? $f['slug'] : '' );
		if ( '' === $base ) { $base = wpfloors_slug( $name ); }
		if ( '' === $base ) { $base = 'floor'; }
		$slug = $base;
		for ( $n = 2; isset( $seen[ $slug ] ); $n++ ) { $slug = $base . '-' . $n; }
		$seen[ $slug ] = true;
		$floors[]      = array( 'slug' => $slug, 'name' => $name, 'image' => $img );
	}

	$types  = wpfloors_types();
	$in_lab = isset( $in['labels'] ) && is_array( $in['labels'] ) ? $in['labels'] : array();
	$labels = array();
	foreach ( $floors as $floor ) {
		$slug  = $floor['slug'];
		$rows  = isset( $in_lab[ $slug ] ) && is_array( $in_lab[ $slug ] ) ? $in_lab[ $slug ] : array();
		$clean = array();
		$ids   = array();
		foreach ( $rows as $r ) {
			if ( count( $clean ) >= WPFLOORS_MAX_LABELS ) { break; }
			if ( ! is_array( $r ) ) { continue; }
			$name = wpfloors_text( isset( $r['name'] ) ? $r['name'] : '', 100 );
			$type = isset( $r['type'] ) && is_string( $r['type'] ) ? $r['type'] : '';
			$x    = wpfloors_pct( isset( $r['x'] ) ? $r['x'] : null );
			$y    = wpfloors_pct( isset( $r['y'] ) ? $r['y'] : null );
			if ( '' === $name || ! isset( $types[ $type ] ) || null === $x || null === $y ) { continue; }
			$id = isset( $r['id'] ) && is_string( $r['id'] ) ? substr( preg_replace( '/[^a-z0-9-]/', '', strtolower( $r['id'] ) ), 0, 40 ) : '';
			while ( '' === $id || isset( $ids[ $id ] ) ) {
				$id = 'l' . substr( md5( uniqid( '', true ) . mt_rand() ), 0, 12 );
			}
			$ids[ $id ] = true;
			$clean[]    = array(
				'id'   => $id,
				'name' => $name,
				'type' => $type,
				'note' => wpfloors_text( isset( $r['note'] ) ? $r['note'] : '', 300 ),
				'show' => wpfloors_show( isset( $r['show'] ) ? $r['show'] : null, $types[ $type ]['name_on_map'] ),
				'x'    => $x,
				'y'    => $y,
			);
		}
		$labels[ $slug ] = $clean;
	}
	return array( 'floors' => $floors, 'labels' => $labels );
}

/* ------------------------------------------------------------------ *
 *  SVG maps
 * ------------------------------------------------------------------ */

/**
 * Width and height of an SVG from its root element: the viewBox size, or else numeric width and
 * height. Null when neither is there.
 */
function wpfloors_svg_size( $xml ) {
	if ( ! is_string( $xml ) || ! preg_match( '/<svg\b[^>]*>/i', $xml, $m ) ) { return null; }
	$tag = $m[0];
	$num = '([-+]?[\d.]+(?:e[-+]?\d+)?)';
	if ( preg_match( '/\sviewBox\s*=\s*["\']\s*' . $num . '[\s,]+' . $num . '[\s,]+' . $num . '[\s,]+' . $num . '\s*["\']/i', $tag, $v ) && (float) $v[3] > 0 && (float) $v[4] > 0 ) {
		return array( (float) $v[3], (float) $v[4] );
	}
	if ( preg_match( '/\swidth\s*=\s*["\']\s*([\d.]+)(px)?\s*["\']/i', $tag, $w ) && preg_match( '/\sheight\s*=\s*["\']\s*([\d.]+)(px)?\s*["\']/i', $tag, $h ) && (float) $w[1] > 0 && (float) $h[1] > 0 ) {
		return array( (float) $w[1], (float) $h[1] );
	}
	return null;
}

/**
 * Why an uploaded SVG can't be used, or '' when it's fine. An SVG can carry scripts that run if
 * someone opens the file directly, so anything that could run code or load something from
 * elsewhere is refused. The file itself is never changed; a normal drawing export passes.
 */
function wpfloors_svg_problem( $xml ) {
	if ( ! is_string( $xml ) || '' === trim( $xml ) ) { return 'The file is empty.'; }
	if ( strlen( $xml ) > WPFLOORS_MAX_SVG_BYTES ) { return 'The SVG is larger than 5 MB.'; }
	if ( ! preg_match( '/<svg\b/i', $xml ) ) { return 'The file isn’t an SVG drawing.'; }
	if ( ! wpfloors_svg_size( $xml ) ) { return 'The SVG needs a viewBox (or a width and height) so the map can be sized.'; }
	$rules = array(
		'/<!ENTITY/i'                                                                 => 'entity declarations',
		'/<\s*(script|foreignObject|iframe|embed|object|audio|video|handler|listener)\b/i' => 'scripts or embedded content',
		'/\son[a-z]+\s*=/i'                                                           => 'event handlers (on… attributes)',
		'/(java|vb)script\s*:/i'                                                      => 'script links',
		'/\bhref\s*=\s*["\']\s*(?!#|data:image\/(png|jpe?g|gif|webp);)/i'              => 'links to other files or pages',
		'/@import/i'                                                                  => 'CSS imports',
		'/url\(\s*["\']?\s*(?!#)/i'                                                   => 'CSS that loads other files',
	);
	foreach ( $rules as $re => $what ) {
		if ( preg_match( $re, $xml ) ) {
			return 'For security, map SVGs can’t contain ' . $what . '. Export the drawing again as a plain SVG and upload that.';
		}
	}
	return '';
}

/** A floor's SVG map as [url, w, h], or null when the attachment isn't a usable SVG. */
function wpfloors_image( $id ) {
	if ( ! $id || 'image/svg+xml' !== get_post_mime_type( $id ) ) { return null; }
	$file = get_attached_file( $id );
	if ( ! $file || ! is_readable( $file ) || filesize( $file ) > WPFLOORS_MAX_SVG_BYTES ) { return null; }
	$size = wpfloors_svg_size( (string) file_get_contents( $file ) );
	$url  = wp_get_attachment_url( $id );
	if ( ! $size || ! $url ) { return null; }
	return array( 'url' => $url, 'w' => $size[0], 'h' => $size[1] );
}

/* Let editors upload SVG maps. Every SVG upload is checked by wpfloors_svg_problem() first. */
add_filter( 'upload_mimes', function ( $mimes ) {
	if ( current_user_can( 'edit_pages' ) ) { $mimes['svg'] = 'image/svg+xml'; }
	return $mimes;
} );

add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
	if ( current_user_can( 'edit_pages' ) && preg_match( '/\.svg$/i', $filename ) ) {
		$data['ext']  = 'svg';
		$data['type'] = 'image/svg+xml';
	}
	return $data;
}, 10, 3 );

add_filter( 'wp_handle_upload_prefilter', 'wpfloors_check_upload' );

/** Refuse unsafe or unusable SVG uploads with a message the Media Library shows. */
function wpfloors_check_upload( $file ) {
	if ( ! isset( $file['name'], $file['tmp_name'] ) || ! preg_match( '/\.svgz?$/i', $file['name'] ) ) { return $file; }
	if ( preg_match( '/\.svgz$/i', $file['name'] ) ) {
		$file['error'] = 'Compressed SVG (.svgz) isn’t supported. Upload a plain .svg file.';
		return $file;
	}
	$problem = wpfloors_svg_problem( is_readable( $file['tmp_name'] ) ? (string) file_get_contents( $file['tmp_name'] ) : '' );
	if ( '' !== $problem ) { $file['error'] = $problem; }
	return $file;
}

/** SVG details for every floor's map, keyed by attachment ID (for the admin editor). */
function wpfloors_images( $floors ) {
	$out = array();
	foreach ( $floors as $f ) {
		if ( $img = wpfloors_image( $f['image'] ) ) { $out[ $f['image'] ] = $img; }
	}
	return (object) $out;
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
	if ( ! is_array( $body ) || ! isset( $body['floors'], $body['labels'] ) || ! is_array( $body['floors'] ) || ! is_array( $body['labels'] ) ) {
		return new WP_Error( 'wpfloors_bad_data', 'Invalid floor map data.', array( 'status' => 400 ) );
	}
	update_option( WPFLOORS_OPTION, wpfloors_sanitize( array( 'floors' => $body['floors'], 'labels' => $body['labels'] ) ), false );
	$data = wpfloors_get();
	return rest_ensure_response( array( 'ok' => true, 'floors' => $data['floors'], 'labels' => (object) $data['labels'], 'images' => wpfloors_images( $data['floors'] ) ) );
}

/* ------------------------------------------------------------------ *
 *  Drawing the maps (shared by the public page and wp-admin)
 * ------------------------------------------------------------------ */

function wpfloors_num( $n ) {
	return rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' );
}

/**
 * A floor's map: its viewBox (string and numbers) and the markup that draws it, or null when the
 * floor has no usable map yet. The SVG is drawn with <image>, which never runs its scripts or
 * loads anything it links to.
 */
function wpfloors_map( $floor ) {
	$img = wpfloors_image( $floor['image'] );
	if ( ! $img ) { return null; }
	$w = wpfloors_num( $img['w'] );
	$h = wpfloors_num( $img['h'] );
	return array(
		'viewBox' => '0 0 ' . $w . ' ' . $h,
		'vb'      => array( 0.0, 0.0, $img['w'], $img['h'] ),
		'body'    => '<image href="' . esc_url( $img['url'] ) . '" x="0" y="0" width="' . $w . '" height="' . $h . '"/>',
	);
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
		. ( $lb['show'] ? '<text class="nm' . ( $right ? ' end' : '' ) . '" x="' . ( $right ? '-1.7' : '1.7' ) . '" y="0.45" font-size="1.25">' . esc_html( $lb['name'] ) . '</text>' : '' )
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

/** A map as inline SVG, with its labels. $map comes from wpfloors_map(). */
function wpfloors_svg( $map, $labels, $aria_label ) {
	$out = '<svg class="wpfloors-svg" viewBox="' . esc_attr( $map['viewBox'] ) . '" role="img" aria-label="' . esc_attr( $aria_label ) . '" focusable="false">' . $map['body'];
	// The labels are hidden from screen readers on purpose. The list under each map is the full
	// text version of everything on it, so reading the labels here too would say everything
	// twice. Keep aria-hidden on this group.
	$out .= '<g class="wpfloors-labels" aria-hidden="true">';
	foreach ( $labels as $lb ) {
		$out .= wpfloors_marker( $lb, $map['vb'] );
	}
	return $out . '</g></svg>';
}

/** Shared styles for the maps and markers. */
function wpfloors_svg_css() {
	return <<<'CSS'
.wpfloors-svg{display:block;width:100%;height:auto;max-height:85vh;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
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
	if ( ! $data['floors'] ) {
		return '<p>No floor maps yet.</p>';
	}
	$from = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
	$out  = '<style>' . wpfloors_svg_css() . wpfloors_page_css() . '</style><div class="wpfloors">';
	$out .= '<nav class="wpfloors-nav" aria-label="Floors"><ul>';
	foreach ( $data['floors'] as $f ) {
		$out .= '<li><a href="#wpfloors-' . esc_attr( $f['slug'] ) . '">' . esc_html( $f['name'] ) . '</a></li>';
	}
	$out .= '</ul></nav>';
	foreach ( $data['floors'] as $f ) {
		$full = add_query_arg( array( 'wpfloors_map' => $f['slug'], 'wpfloors_from' => $from ), home_url( '/' ) );
		$out .= '<section class="wpfloors-floor" id="wpfloors-' . esc_attr( $f['slug'] ) . '" aria-labelledby="wpfloors-h-' . esc_attr( $f['slug'] ) . '">';
		$out .= '<h2 id="wpfloors-h-' . esc_attr( $f['slug'] ) . '">' . esc_html( $f['name'] ) . '</h2>';
		$out .= wpfloors_floor_body( $f, $data['labels'][ $f['slug'] ], 3, $full );
		$out .= '</section>';
	}
	return $out . '</div>';
}

/** Map, key, full-size link and list for one floor. $h is the heading level for the list groups. */
function wpfloors_floor_body( $floor, $labels, $h, $full_url ) {
	$types = wpfloors_types();
	$map   = wpfloors_map( $floor );
	if ( $map ) {
		$out = '<div class="wpfloors-map">' . wpfloors_svg( $map, $labels, $floor['name'] . ' floor map. Everything shown is listed below.' ) . '</div>';
	} else {
		$out      = '<p class="wpfloors-nomap">The map for this floor isn&#8217;t available yet.</p>';
		$full_url = '';
	}

	$used = array();
	foreach ( $labels as $lb ) { $used[ $lb['type'] ] = true; }
	if ( $used && $map ) {
		$out .= '<div class="wpfloors-key"><p class="wpfloors-key-h">Key</p><ul>';
		foreach ( $types as $type => $t ) {
			if ( isset( $used[ $type ] ) ) {
				$out .= '<li>' . wpfloors_icon( $type ) . '<span>' . esc_html( $t['label'] ) . '</span></li>';
			}
		}
		$out .= '</ul></div>';
	}
	if ( $full_url ) {
		$out .= '<p class="wpfloors-full-link"><a href="' . esc_url( $full_url ) . '">Open full-size map<span class="wpfloors-sr"> of the ' . esc_html( $floor['name'] ) . '</span></a></p>';
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
  .wpfloors-svg{max-height:5.5in}
  .wpfloors-key{break-inside:avoid;page-break-inside:avoid}
  .wpfloors-map{border:0;padding:0}
  .wpfloors-group{break-inside:avoid;page-break-inside:avoid}
}
CSS;
}

/* ------------------------------------------------------------------ *
 *  "Open full-size map": one floor on its own page, e.g. /?wpfloors_map=main
 * ------------------------------------------------------------------ */

/** The saved floor with this slug, or null. */
function wpfloors_find_floor( $data, $slug ) {
	foreach ( $data['floors'] as $f ) {
		if ( $f['slug'] === $slug ) { return $f; }
	}
	return null;
}

add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['wpfloors_map'] ) ) { return; }
	$slug = wpfloors_slug( wp_unslash( $_GET['wpfloors_map'] ) );
	if ( ! wpfloors_find_floor( wpfloors_get(), $slug ) ) { return; }
	$from = isset( $_GET['wpfloors_from'] ) ? absint( $_GET['wpfloors_from'] ) : 0;
	$back = ( $from && 'publish' === get_post_status( $from ) ) ? get_permalink( $from ) : '';
	header( 'Content-Type: text/html; charset=utf-8' );
	echo wpfloors_full_page( $slug, $back ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );

function wpfloors_full_page( $slug, $back ) {
	$data  = wpfloors_get();
	$floor = wpfloors_find_floor( $data, $slug );
	if ( ! $floor ) { return ''; }
	$out = '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
		. '<title>' . esc_html( $floor['name'] . ' floor map | ' . get_bloginfo( 'name' ) ) . '</title>'
		. '<style>body{margin:0;padding:16px;background:#fff;color:#1a1a1a;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
		. 'a{color:#00539b}h1{font-size:1.6em;margin:.2em 0 .5em}.wpfloors-full .wpfloors-svg{max-height:none}'
		. wpfloors_svg_css() . wpfloors_page_css() . '</style></head><body><main class="wpfloors wpfloors-full">';
	if ( $back ) {
		$out .= '<p><a href="' . esc_url( $back ) . '">Back to all floor maps</a></p>';
	}
	$out .= '<h1>' . esc_html( $floor['name'] ) . '</h1>' . wpfloors_floor_body( $floor, $data['labels'][ $slug ], 2, '' );
	return $out . '</main></body></html>';
}

/* ------------------------------------------------------------------ *
 *  Admin page: wp-admin → Floor Maps
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_menu_page( 'Floor Maps', 'Floor Maps', 'edit_pages', 'wpfloors', 'wpfloors_admin_page', 'dashicons-location-alt', 31 );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_wpfloors' === $hook ) { wp_enqueue_media(); }
} );

function wpfloors_admin_page() {
	if ( ! current_user_can( 'edit_pages' ) ) { return; }
	$data  = wpfloors_get();
	$types = array();
	foreach ( wpfloors_types() as $slug => $t ) {
		$types[] = array_merge( array( 'slug' => $slug ), $t );
	}
	$cfg = array(
		'root'     => esc_url_raw( rest_url( 'wpfloors/v1/labels' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'types'    => $types,
		'floors'   => $data['floors'],
		'labels'   => (object) $data['labels'],
		'images'   => wpfloors_images( $data['floors'] ),
	);

	echo '<div class="wrap"><h1>Floor Maps</h1>';
	echo '<p style="max-width:720px">Add your floors and upload an SVG map for each one, then add labels to each map. Nothing changes on the website until you click <strong>Save</strong>. Show the maps on any page with the shortcode <code>[floor_maps]</code>.</p>';
	echo '<style>' . wpfloors_svg_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo wpfloors_admin_markup(); // phpcs:ignore WordPress.Security.EscapeOutput
	// JSON_HEX_TAG keeps "</script>" or "<!--" in a name from breaking out of this tag.
	echo '<script>window.WPFLOORS_ADMIN=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP ) . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo wpfloors_admin_script(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

function wpfloors_admin_markup() {
	return <<<'HTML'
<style>
  #wpfloors-admin{max-width:960px}
  #wpfloors-admin h2{margin-top:28px}
  #wpfloors-floors td{vertical-align:middle}
  #wpfloors-floors input[type=text]{width:100%;max-width:260px}
  #wpfloors-floors .button{margin:2px 4px 2px 0}
  #wpfloors-floors .thumb{display:inline-block;vertical-align:middle;max-width:60px;max-height:40px;border:1px solid #c3c4c7;margin-right:6px}
  #wpfloors-admin .hint{color:#50575e;max-width:720px}
  #wpfloors-top{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:14px 0 8px}
  #wpfloors-top label{font-weight:600}
  #wpfloors-mode{min-height:1.5em;margin:4px 0 8px;font-weight:600}
  #wpfloors-stage{border:1px solid #c3c4c7;background:#fff;padding:4px;min-height:60px}
  #wpfloors-stage .wpfloors-svg{cursor:crosshair;max-height:75vh}
  #wpfloors-stage .empty{padding:20px;margin:0;color:#50575e}
  #wpfloors-stage .mk.is-pending path{stroke-dasharray:.3 .2}
  #wpfloors-stage .mk.is-active path{stroke-width:.3}
  #wpfloors-form{background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:4px 16px 12px;margin:12px 0}
  #wpfloors-form legend{font-size:1.1em;font-weight:600;padding:8px 0}
  #wpfloors-form .row{display:flex;flex-wrap:wrap;gap:12px 20px;margin:8px 0}
  #wpfloors-form label{display:block;font-weight:600;margin-bottom:3px}
  #wpfloors-form label.inline{display:inline;font-weight:600}
  #wpfloors-form input[type=text]{width:320px;max-width:100%}
  #wpfloors-form input[type=number]{width:90px}
  #wpfloors-error{color:#b32d2e;font-weight:600}
  #wpfloors-rows td{vertical-align:middle}
  #wpfloors-rows .button{margin:2px 4px 2px 0}
  #wpfloors-bottom{display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin:16px 0}
  #wpfloors-status{font-weight:600}
</style>
<div id="wpfloors-admin">
  <h2 id="wpfloors-floors-h">Floors</h2>
  <p class="hint">Floors appear on the page in this order. Each floor needs a map: an SVG file from the Media Library. Use a clean drawing of the floor with <strong>no words on it</strong> and dark walls on a light background (at least 3:1 contrast). Add every room name here as a label instead, so everyone can read the list under the map, including people using screen readers.</p>
  <table class="widefat striped" aria-labelledby="wpfloors-floors-h">
    <thead><tr><th scope="col">Name</th><th scope="col">Map (SVG)</th><th scope="col">Actions</th></tr></thead>
    <tbody id="wpfloors-floors"></tbody>
  </table>
  <p><button type="button" class="button" id="wpfloors-add-floor">Add a floor</button></p>

  <h2>Labels</h2>
  <div id="wpfloors-top">
    <label for="wpfloors-floor">Floor</label>
    <select id="wpfloors-floor"></select>
    <button type="button" class="button" id="wpfloors-add">Add a label by typing its position</button>
    <span class="hint">Or click the map.</span>
  </div>
  <p id="wpfloors-mode" aria-live="polite"></p>
  <div id="wpfloors-stage"></div>
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
        <div><label class="inline"><input id="wpfloors-show" type="checkbox"> Show name on the map</label>
          <p class="hint" style="margin:2px 0 0">Turn off where names would overlap. The name is always in the list under the map.</p></div>
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
  <table class="widefat striped" aria-labelledby="wpfloors-table-h">
    <thead><tr><th scope="col">Name</th><th scope="col">Type</th><th scope="col">Note</th><th scope="col">Name on map</th><th scope="col">Actions</th></tr></thead>
    <tbody id="wpfloors-rows"></tbody>
  </table>
  <div id="wpfloors-bottom">
    <button type="button" class="button button-primary button-hero" id="wpfloors-save">Save</button>
    <span id="wpfloors-status" role="status"></span>
  </div>
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
var floors=C.floors,labels=C.labels||{},images=C.images||{},types={};
var cur=floors.length?floors[0].slug:null,form=null,moving=null,dirty=false,saving=false,svg=null,vb=null;
C.types.forEach(function(t){types[t.slug]=t;});
function $(id){return document.getElementById(id);}
function floorOf(slug){for(var i=0;i<floors.length;i++)if(floors[i].slug===slug)return floors[i];return null;}
function list(){if(!cur)return [];return labels[cur]||(labels[cur]=[]);}
function find(id){var a=list();for(var i=0;i<a.length;i++)if(a[i].id===id)return a[i];return null;}
function uid(){return "l"+Date.now().toString(36)+Math.random().toString(36).slice(2,7);}
function num(n){return String(Math.round(n*100)/100);}
function svgEl(name,attrs){var e=document.createElementNS(NS,name);for(var k in attrs)if(attrs.hasOwnProperty(k))e.setAttribute(k,attrs[k]);return e;}
function slugify(s){return String(s).toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"").slice(0,40)||"floor";}
function uniqueSlug(base,except){var s=base,n=2;while(true){var clash=false;for(var i=0;i<floors.length;i++)if(floors[i]!==except&&floors[i].slug===s)clash=true;if(!clash)return s;s=base+"-"+(n++);}}
function btn(text,label,fn){var b=document.createElement("button");b.type="button";b.className="button";b.textContent=text;if(label)b.setAttribute("aria-label",label);b.onclick=fn;return b;}
function setMode(msg){$("wpfloors-mode").textContent=msg||"";}
function status(msg,bad){var s=$("wpfloors-status");s.textContent=msg;s.style.color=bad?"#b32d2e":(dirty?"#1d2327":"#007017");}
function markDirty(){dirty=true;status("You have unsaved changes. Click Save to put them on the website.",false);}

/* ---------- floors ---------- */
/* Same rules as the PHP wpfloors_svg_size(): the viewBox size, or else numeric width and height. */
function svgSize(t){
  var m=/<svg\b[^>]*>/i.exec(t);if(!m)return null;var tag=m[0],n="([-+]?[\\d.]+(?:e[-+]?\\d+)?)";
  var v=new RegExp("\\sviewBox\\s*=\\s*[\"']\\s*"+n+"[\\s,]+"+n+"[\\s,]+"+n+"[\\s,]+"+n+"\\s*[\"']","i").exec(tag);
  if(v&&+v[3]>0&&+v[4]>0)return [+v[3],+v[4]];
  var w=/\swidth\s*=\s*["']\s*([\d.]+)(px)?\s*["']/i.exec(tag),h=/\sheight\s*=\s*["']\s*([\d.]+)(px)?\s*["']/i.exec(tag);
  if(w&&h&&+w[1]>0&&+h[1]>0)return [+w[1],+h[1]];
  return null;
}
function chooseImage(f,opener){
  if(!window.wp||!wp.media){status("The Media Library isn't available on this page. Reload and try again.",true);return;}
  var frame=wp.media({title:"Choose an SVG map for "+f.name,button:{text:"Use this map"},library:{type:"image/svg+xml"},multiple:false});
  frame.on("select",function(){
    var a=frame.state().get("selection").first().toJSON();
    if(a.mime!=="image/svg+xml"){status("Choose an SVG file. Other image types can't be used as maps.",true);return;}
    status("Reading the SVG\u2026",false);
    var x=new XMLHttpRequest();x.open("GET",a.url);
    x.onload=function(){
      var size=x.status>=200&&x.status<300?svgSize(x.responseText):null;
      if(!size){status("That SVG has no viewBox or size, so it can't be used as a map. Export it again with a viewBox.",true);return;}
      images[a.id]={url:a.url,w:size[0],h:size[1]};f.image=a.id;markDirty();renderFloors();if(f.slug===cur)renderStage();
      setMode("Map set for "+f.name+".");
    };
    x.onerror=function(){status("Couldn't read that SVG. Try again.",true);};
    x.send();
  });
  frame.on("close",function(){if(opener&&document.body.contains(opener))opener.focus();});
  frame.open();
}
function renderFloors(){
  var tb=$("wpfloors-floors");tb.innerHTML="";
  if(!floors.length){var tr=document.createElement("tr"),td=document.createElement("td");td.colSpan=3;td.textContent="No floors yet. Use Add a floor to start.";tr.appendChild(td);tb.appendChild(tr);}
  floors.forEach(function(f,i){
    var tr=document.createElement("tr");
    var t0=document.createElement("td"),inp=document.createElement("input");inp.type="text";inp.maxLength=60;inp.value=f.name;inp.setAttribute("aria-label","Name of floor "+(i+1));
    inp.oninput=function(){f.name=inp.value;if(f.isNew){var old=f.slug;f.slug=uniqueSlug(slugify(inp.value),f);if(old!==f.slug){labels[f.slug]=labels[old]||[];delete labels[old];if(cur===old)cur=f.slug;}}renderFloorSelect();markDirty();};
    t0.appendChild(inp);tr.appendChild(t0);
    var t1=document.createElement("td"),im=images[f.image];
    if(im){var th=document.createElement("img");th.className="thumb";th.src=im.url;th.alt="";t1.appendChild(th);}
    t1.appendChild(btn(im?"Change SVG":"Choose SVG",(im?"Change SVG map for ":"Choose SVG map for ")+f.name,function(e){chooseImage(f,e.currentTarget);}));
    if(!im){var w=document.createElement("span");w.textContent="No map yet.";t1.appendChild(w);}
    tr.appendChild(t1);
    var t2=document.createElement("td");
    var up=btn("Move up","Move "+f.name+" up",function(){move(i,-1,"up");});up.disabled=i===0;t2.appendChild(up);
    var dn=btn("Move down","Move "+f.name+" down",function(){move(i,1,"down");});dn.disabled=i===floors.length-1;t2.appendChild(dn);
    t2.appendChild(btn("Delete","Delete "+f.name,function(){deleteFloor(i);}));
    tr.appendChild(t2);tb.appendChild(tr);
  });
}
function move(i,d,word){
  var j=i+d;if(j<0||j>=floors.length)return;var f=floors[i];floors[i]=floors[j];floors[j]=f;
  markDirty();renderFloors();renderFloorSelect();
  var b=$("wpfloors-floors").querySelectorAll("tr")[j].querySelectorAll("button");
  var target=word==="up"?b[b.length-3]:b[b.length-2];if(target.disabled)target=b[b.length-1];target.focus();
  setMode("Moved "+f.name+" "+word+".");
}
function deleteFloor(i){
  var f=floors[i],n=(labels[f.slug]||[]).length;floors.splice(i,1);delete labels[f.slug];
  if(cur===f.slug){closeForm(false);moving=null;cur=floors.length?floors[0].slug:null;}
  markDirty();renderFloors();renderFloorSelect();renderStage();renderRows();
  setMode("Deleted "+f.name+(n?" and its "+n+" label"+(n===1?"":"s"):"")+". Click Save to make it final, or reload the page to undo.");
  $("wpfloors-add-floor").focus();
}
function addFloor(){
  var f={slug:"",name:"New floor",image:0,isNew:true};f.slug=uniqueSlug("new-floor",f);
  floors.push(f);labels[f.slug]=[];markDirty();renderFloors();renderFloorSelect();
  var rows=$("wpfloors-floors").querySelectorAll("tr"),inp=rows[rows.length-1].querySelector("input");inp.focus();inp.select();
  setMode("Added a floor. Type its name, then choose its SVG map.");
}
function renderFloorSelect(){
  var fs=$("wpfloors-floor");fs.innerHTML="";
  floors.forEach(function(f){var o=document.createElement("option");o.value=f.slug;o.textContent=f.name||"(no name)";if(f.slug===cur)o.selected=true;fs.appendChild(o);});
  fs.disabled=!floors.length;$("wpfloors-add").disabled=!floors.length;
  if(cur&&floorOf(cur))$("wpfloors-table-h").textContent="Labels on the "+floorOf(cur).name;
}

/* ---------- the map for the chosen floor ---------- */
function buildSvg(f){
  var im=images[f.image];if(!im)return null;
  var s=svgEl("svg",{"class":"wpfloors-svg",viewBox:"0 0 "+num(im.w)+" "+num(im.h),role:"img","aria-label":f.name+" floor map. Click to place a label, or type its position in the form.",focusable:"false"});
  s.appendChild(svgEl("image",{href:im.url,x:0,y:0,width:num(im.w),height:num(im.h)}));
  s.appendChild(svgEl("g",{"class":"wpfloors-labels","aria-hidden":"true"}));
  return s;
}
function renderStage(){
  var st=$("wpfloors-stage");st.innerHTML="";svg=null;vb=null;
  var f=cur?floorOf(cur):null,p=document.createElement("p");p.className="empty";
  if(!f){p.textContent="Add a floor above to start.";st.appendChild(p);return;}
  svg=buildSvg(f);
  if(!svg){p.textContent="Choose an SVG map for "+f.name+" above before adding labels.";st.appendChild(p);$("wpfloors-add").disabled=true;return;}
  $("wpfloors-add").disabled=false;
  var b=svg.getAttribute("viewBox").split(/\s+/).map(parseFloat);vb=b;
  svg.addEventListener("click",onMapClick);st.appendChild(svg);drawMarkers();
}

/* Same marker as the PHP wpfloors_marker(). */
function marker(lb,cls){
  var t=types[lb.type];if(!t)return null;
  var r=Math.max(vb[2],vb[3])*0.016,cx=vb[0]+lb.x/100*vb[2],cy=vb[1]+lb.y/100*vb[3],right=lb.x>65;
  var g=svgEl("g",{"class":"mk"+(cls?" "+cls:""),transform:"translate("+num(cx)+" "+num(cy)+") scale("+num(r)+")"});
  g.appendChild(svgEl("path",{d:t.shape}));
  var L=svgEl("text",{"class":"lt",x:t.tx,y:t.ty,"font-size":t.fs});L.textContent=t.letter;g.appendChild(L);
  if(lb.show){var N=svgEl("text",{"class":"nm"+(right?" end":""),x:right?-1.7:1.7,y:0.45,"font-size":1.25});N.textContent=lb.name;g.appendChild(N);}
  return g;
}
function drawMarkers(){
  if(!svg)return;
  var layer=svg.querySelector(".wpfloors-labels");
  while(layer.firstChild)layer.removeChild(layer.firstChild);
  list().forEach(function(lb){
    if(form&&form.id===lb.id)return; // shown as the pending marker instead
    var g=marker(lb,moving===lb.id?"is-active":"");if(g)layer.appendChild(g);
  });
  if(form){var p=pending();if(p){var g=marker(p,"is-pending");if(g)layer.appendChild(g);}}
}
function pending(){
  var x=parseFloat($("wpfloors-x").value),y=parseFloat($("wpfloors-y").value);
  if(isNaN(x)||isNaN(y))return null;
  return {name:$("wpfloors-name").value||"New label",type:$("wpfloors-type").value,show:$("wpfloors-show").checked,x:Math.max(0,Math.min(100,x)),y:Math.max(0,Math.min(100,y))};
}

/* ---------- labels ---------- */
function renderRows(){
  var tb=$("wpfloors-rows");tb.innerHTML="";
  var f=cur?floorOf(cur):null;
  $("wpfloors-table-h").textContent=f?"Labels on the "+f.name:"Labels";
  var a=list();
  if(!a.length){var tr=document.createElement("tr"),td=document.createElement("td");td.colSpan=5;td.textContent=f?"No labels on this floor yet. Click the map to add one.":"No floors yet.";tr.appendChild(td);tb.appendChild(tr);return;}
  a.forEach(function(lb){
    var tr=document.createElement("tr");
    var th=document.createElement("th");th.scope="row";th.textContent=lb.name;tr.appendChild(th);
    var t1=document.createElement("td");t1.textContent=types[lb.type]?types[lb.type].label:lb.type;tr.appendChild(t1);
    var t2=document.createElement("td");t2.textContent=lb.note||"";tr.appendChild(t2);
    var t4=document.createElement("td");t4.textContent=lb.show?"Shown":"Hidden";tr.appendChild(t4);
    var t3=document.createElement("td");
    t3.appendChild(btn("Edit","Edit "+lb.name,function(e){openForm(lb.id,null,null,e.currentTarget);}));
    var mv=btn("Move","Move "+lb.name,function(){startMove(lb.id);});mv.disabled=!svg;t3.appendChild(mv);
    t3.appendChild(btn("Delete","Delete "+lb.name,function(){remove(lb.id);}));
    tr.appendChild(t3);tb.appendChild(tr);
  });
}
function showFloor(slug){closeForm(false);moving=null;setMode("");cur=slug;renderFloorSelect();renderStage();renderRows();}
function openForm(id,x,y,opener){
  if(!svg){status("Choose an SVG map for this floor first.",true);return;}
  moving=null;
  var lb=id?find(id):null;
  form={id:id,opener:opener||null};
  $("wpfloors-form-title").textContent=lb?"Edit label":"Add a label";
  $("wpfloors-ok").textContent=lb?"Update label":"Add label";
  $("wpfloors-name").value=lb?lb.name:"";
  $("wpfloors-type").value=lb?lb.type:"room";
  $("wpfloors-note").value=lb?lb.note:"";
  $("wpfloors-show").checked=lb?!!lb.show:!!types.room.name_on_map;
  form.showTouched=!!lb; // a new label follows its type's default until staff change the checkbox
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
  var vals={name:name,type:$("wpfloors-type").value,note:$("wpfloors-note").value.replace(/^\s+|\s+$/g,""),show:$("wpfloors-show").checked,x:Math.round(x*100)/100,y:Math.round(y*100)/100};
  var lb=form.id?find(form.id):null;
  if(lb){for(var k in vals)if(vals.hasOwnProperty(k))lb[k]=vals[k];}
  else{vals.id=uid();list().push(vals);}
  var msg=(lb?"Updated “":"Added “")+name+"”.";
  closeForm(true);renderRows();markDirty();setMode(msg);
}
function startMove(id){
  closeForm(false);
  var lb=find(id);if(!lb||!svg)return;
  moving=id;drawMarkers();
  setMode("Click the new spot on the map for “"+lb.name+"”. Press Escape to cancel. (To move it without a mouse, use Edit and change Across and Down.)");
}
function remove(id){
  var a=list();
  for(var i=0;i<a.length;i++)if(a[i].id===id){var n=a[i].name;a.splice(i,1);
    if(form&&form.id===id)closeForm(false);if(moving===id)moving=null;
    drawMarkers();renderRows();markDirty();setMode("Deleted “"+n+"”. Click Save to remove it from the website.");$("wpfloors-add").focus();return;}
}
function pct(evt){
  var pt=svg.createSVGPoint();pt.x=evt.clientX;pt.y=evt.clientY;
  var p=pt.matrixTransform(svg.getScreenCTM().inverse());
  return {x:Math.round(Math.max(0,Math.min(100,(p.x-vb[0])/vb[2]*100))*10)/10,y:Math.round(Math.max(0,Math.min(100,(p.y-vb[1])/vb[3]*100))*10)/10};
}
function onMapClick(evt){
  var p=pct(evt);
  if(moving){var lb=find(moving);moving=null;if(lb){lb.x=p.x;lb.y=p.y;markDirty();setMode("Moved “"+lb.name+"”.");}drawMarkers();return;}
  if(form){$("wpfloors-x").value=p.x;$("wpfloors-y").value=p.y;drawMarkers();return;}
  openForm(null,p.x,p.y,null);
}

/* ---------- save ---------- */
function save(){
  if(saving)return;
  if(form){status("Finish or cancel the label you're editing first.",true);return;}
  for(var i=0;i<floors.length;i++)if(!String(floors[i].name).replace(/\s+/g,"")){status("Every floor needs a name.",true);$("wpfloors-floors").querySelectorAll("input")[i].focus();return;}
  saving=true;status("Saving…",false);
  var out=floors.map(function(f){return {slug:f.slug,name:f.name,image:f.image||0};});
  var x=new XMLHttpRequest();
  x.open("POST",C.root);x.setRequestHeader("Content-Type","application/json");x.setRequestHeader("X-WP-Nonce",C.nonce);
  x.onload=function(){
    saving=false;
    var res=null;try{res=JSON.parse(x.responseText);}catch(e){}
    if(x.status>=200&&x.status<300&&res&&res.floors){
      floors=res.floors;labels=res.labels||{};images=res.images||{};dirty=false;moving=null;
      if(!floorOf(cur))cur=floors.length?floors[0].slug:null;
      renderFloors();renderFloorSelect();renderStage();renderRows();
      status("Saved. It's live on the website. If you don't see it there, purge the cache.",false);
    }else{status("Save failed ("+x.status+"). Please try again, or reload the page.",true);}
  };
  x.onerror=function(){saving=false;status("Save failed. Check your connection and try again.",true);};
  x.send(JSON.stringify({floors:out,labels:labels}));
}

var ts=$("wpfloors-type");
C.types.forEach(function(t){var o=document.createElement("option");o.value=t.slug;o.textContent=t.label;ts.appendChild(o);});
$("wpfloors-floor").onchange=function(){showFloor($("wpfloors-floor").value);};
$("wpfloors-add-floor").onclick=addFloor;
$("wpfloors-add").onclick=function(e){openForm(null,null,null,e.currentTarget);};
$("wpfloors-form").onsubmit=submitForm;
$("wpfloors-cancel").onclick=function(){closeForm(true);};
["wpfloors-name","wpfloors-type","wpfloors-x","wpfloors-y","wpfloors-show"].forEach(function(id){$(id).addEventListener("input",drawMarkers);$(id).addEventListener("change",drawMarkers);});
$("wpfloors-show").addEventListener("change",function(){if(form)form.showTouched=true;});
$("wpfloors-type").addEventListener("change",function(){var t=types[$("wpfloors-type").value];if(form&&!form.showTouched&&t){$("wpfloors-show").checked=!!t.name_on_map;drawMarkers();}});
document.addEventListener("keydown",function(e){
  if(e.key!=="Escape"&&e.key!=="Esc")return;
  var mm=document.querySelector(".media-modal");if(mm&&mm.offsetParent)return; // the Media Library handles its own Escape
  if(moving){moving=null;drawMarkers();setMode("Move cancelled.");}
  else if(form){closeForm(true);}
});
$("wpfloors-save").onclick=save;
window.addEventListener("beforeunload",function(e){if(dirty){e.preventDefault();e.returnValue="";}});
renderFloors();renderFloorSelect();renderStage();renderRows();
})();
</script>
HTML;
}
