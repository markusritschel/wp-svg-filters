<?php
/**
 * Smoke test: runs the loader against minimal WordPress stubs.
 *
 *   php tests/loader-test.php
 *
 * Exits non-zero on the first failed check. CLI only.
 */

PHP_SAPI === 'cli' || exit;

define( 'ABSPATH', __DIR__ );
define( 'WEEK_IN_SECONDS', 604800 );

$GLOBALS['hooks']   = array();
$GLOBALS['filters'] = array();
$GLOBALS['inline']  = '';
$theme              = dirname( __DIR__, 2 ); // module folder sits inside the "child theme"

function add_action( $hook, $cb, $prio = 10 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function do_action( $hook ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $cb ) { $cb(); } }
function add_filter( $hook, $cb ) { $GLOBALS['filters'][ $hook ][] = $cb; }
function apply_filters( $hook, $value ) {
	foreach ( $GLOBALS['filters'][ $hook ] ?? array() as $cb ) {
		$value = $cb( $value );
	}
	return $value;
}
function get_transient( $key ) { return false; }
function set_transient( $key, $value, $ttl ) { return true; }
function delete_transient( $key ) { return true; }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', $p ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $c ); }
function get_stylesheet_directory() { return $GLOBALS['theme']; }
function get_stylesheet_directory_uri() { return 'https://example.test/wp-content/themes/child'; }
function get_template_directory() { return '/nonexistent/parent'; }
function get_template_directory_uri() { return 'https://example.test/wp-content/themes/parent'; }
function plugins_url( $rel, $file ) { return 'https://example.test/wp-content/plugins/x/' . $rel; }
function wp_enqueue_style( ...$a ) {}
function wp_register_style( ...$a ) {}
function wp_enqueue_script( ...$a ) {}
function wp_add_inline_style( $handle, $css ) { $GLOBALS['inline'] .= $css; }

function check( $ok, $msg ) {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $msg . "\n";
	if ( ! $ok ) {
		exit( 1 );
	}
}

require dirname( __DIR__ ) . '/svg-filters.php';

// Filters from other sources (e.g. a plugin's uploads): one new, one colliding with a file.
add_filter( 'svgf_extra_sources', function ( $sources ) {
	$sources[] = '<filter id="fx-extra-test" data-category="custom"><title>Extra</title>'
		. '<feGaussianBlur stdDeviation="3" data-tweak-stdDeviation="[0, 9] Radius"/></filter>';
	$sources[] = '<filter id="fx-blur"><feGaussianBlur stdDeviation="99"/></filter>';
	return $sources;
} );

// Extra defs markup (e.g. a plugin's tuned filter copies).
add_filter( 'svgf_extra_defs', function ( $markup ) {
	return $markup . '<filter id="fx-tuned-copy"><feGaussianBlur stdDeviation="1"/></filter>';
} );

// Expected IDs, parsed independently with a real XML parser.
$expected = array( 'fx-extra-test' );
foreach ( glob( SVGF_DIR . '/filters/*.svg' ) as $file ) {
	$doc = new DOMDocument();
	check( $doc->load( $file ), basename( $file ) . ' is well-formed XML' );
	foreach ( $doc->getElementsByTagName( 'filter' ) as $f ) {
		$expected[] = $f->getAttribute( 'id' );
	}
}
sort( $expected );

$data = svgf_collect();
$ids  = $data['ids'];
sort( $ids );
check( $ids === $expected, count( $ids ) . ' filter IDs collected, matching the XML parse' );
check( in_array( 'fx-blur', $ids, true ) && in_array( 'fx-blur-solid', $ids, true ), 'both blur filters present' );
check( false === strpos( $data['markup'], 'stdDeviation="99"' ), 'extra source with a file ID is skipped (file wins)' );

// Metadata accessor: unstripped metadata per filter, stripped markup for printing.
$meta = svgf_filters();
check( array_keys( $meta ) === $data['ids'], 'svgf_filters() lists every collected ID in order' );
$duo = $meta['fx-duotone'];
check( 'Duotone' === $duo['title'] && 'color' === $duo['category'], 'title and category of fx-duotone' );
check( 0 === strpos( $duo['desc'], 'Converts to grayscale' ), 'desc of fx-duotone' );
check(
	array( 'key' => 'feFlood|flood-color', 'attr' => 'flood-color', 'default' => '#1a1f61', 'hint' => 'Shadow color | Color of the darkest tones' )
		=== $duo['tweaks'][0],
	'first tweak of fx-duotone: key, attr, default, hint'
);
check( array( 'feFlood|flood-color', 'feFlood:2|flood-color' ) === array_column( $duo['tweaks'], 'key' ), 'fx-duotone: two color tweaks' );

// Presets: name => tweak key => value; every key belongs to a tweak of its filter.
check( '0 -1 0 -1 5 -1 0 -1 0' === ( $meta['fx-emboss']['presets']['Sharpen']['feConvolveMatrix|kernelMatrix'] ?? null ), 'presets of fx-emboss' );
check( array() === $duo['presets'], 'no presets: empty array' );
foreach ( $meta as $id => $f ) {
	$keys = array_column( $f['tweaks'], 'key' );
	foreach ( $f['presets'] as $name => $values ) {
		if ( array_diff( array_keys( $values ), $keys ) ) {
			check( false, "preset {$name} of {$id} uses only tweak keys" );
		}
	}
}
check( true, 'every preset key is a tweak key of its filter' );

// Hint syntax 2: "[min, max unit] {type} Label | Description" (range, unit, type, description optional).
foreach ( $meta as $id => $f ) {
	foreach ( $f['tweaks'] as $t ) {
		if ( 0 === strpos( $id, 'fx-extra' ) ) {
			continue;
		}
		if ( ! preg_match( '/^(\[\s*-?[\d.]+\s*,\s*-?[\d.]+(\s+(px|deg|s|%))?\s*\]\s*)?(\{(pair|levels|matrix)\}\s*)?[^|\[{][^|]{0,40}(\s\|\s.+)?$/u', $t['hint'] ) ) {
			check( false, "hint syntax of {$id} {$t['key']}: {$t['hint']}" );
		}
	}
}
check( true, 'every kit hint follows hint syntax 2 with a short label' );
$spot_keys = array_column( $meta['fx-spotlight']['tweaks'], 'key' );
check( in_array( '#fx-spot-light|z', $spot_keys, true ), 'tweak on a primitive with an id is keyed by #id' );
check( in_array( 'feSpecularLighting|lighting-color', $spot_keys, true ), 'hyphenated attribute names kept' );
check( false === strpos( $duo['markup'], 'data-' ) && 0 === strpos( $duo['markup'], '<filter id="fx-duotone"' ),
	'markup per filter is the stripped, printable <filter>' );
check( 'custom' === $meta['fx-extra-test']['category'] && 'Extra' === $meta['fx-extra-test']['title'],
	'metadata of extra sources' );

// Defs: footer only, never wp_body_open.
check( empty( $GLOBALS['hooks']['wp_body_open'] ), 'nothing hooked on wp_body_open' );
ob_start();
do_action( 'wp_footer' );
$footer = ob_get_clean();
check( false !== strpos( $footer, '<svg id="svgf-defs"' ), 'defs printed in wp_footer' );
$printed = substr_count( $footer, '<filter ' );
check( count( $expected ) + 1 === $printed, "every filter printed exactly once, plus the extra defs ({$printed} printed)" );
check( (bool) preg_match( '#<defs>.*<filter id="fx-tuned-copy">.*</defs>#s', $footer ), 'extra defs printed inside the same <defs>' );
ob_start();
do_action( 'wp_footer' );
check( '' === ob_get_clean(), 'defs not printed twice' );

$defs = new DOMDocument();
check( $defs->loadXML( trim( $footer ) ), 'printed defs are well-formed XML' );

// Metadata attributes (data-*) only feed the demo site; they must not reach the page.
check( ! preg_match( '/\sdata-[\w.:-]+\s*=/', $footer ), 'no data-* attributes in the printed defs' );
check( false !== strpos( $footer, 'stdDeviation="8"' ) && false !== strpos( $footer, 'id="fx-melt-map"' ),
	'filter attributes and primitive IDs kept' );
check( substr_count( $footer, '<title>' ) === count( $expected ), '<title> kept for every filter' );

// Generated CSS.
do_action( 'wp_enqueue_scripts' );
$css = $GLOBALS['inline'];
foreach ( $expected as $id ) {
	if ( false === strpos( $css, ".{$id}{filter:url(#{$id})}" )
		|| false === strpos( $css, ".{$id}--bg .et_parallax_bg,.{$id}--bg .et-pb-parallax-background{filter:url(#{$id})}" ) ) {
		check( false, "CSS rules for {$id}" );
	}
}
check( true, 'class + --bg parallax rules (Divi 4 and 5) for every filter' );

// Path resolution relative to the module folder.
check(
	svgf_url( 'assets/svg-filters.css' ) === 'https://example.test/wp-content/themes/child/' . basename( SVGF_DIR ) . '/assets/svg-filters.css',
	'svgf_url() resolves inside the child theme'
);

echo "All checks passed.\n";
