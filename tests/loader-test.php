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

$GLOBALS['hooks']  = array();
$GLOBALS['inline'] = '';
$theme             = dirname( __DIR__, 2 ); // module folder sits inside the "child theme"

function add_action( $hook, $cb, $prio = 10 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function do_action( $hook ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $cb ) { $cb(); } }
function apply_filters( $hook, $value ) { return $value; }
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

// Expected IDs, parsed independently with a real XML parser.
$expected = array();
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

// Defs: footer only, never wp_body_open.
check( empty( $GLOBALS['hooks']['wp_body_open'] ), 'nothing hooked on wp_body_open' );
ob_start();
do_action( 'wp_footer' );
$footer = ob_get_clean();
check( false !== strpos( $footer, '<svg id="svgf-defs"' ), 'defs printed in wp_footer' );
check( substr_count( $footer, '<filter ' ) === count( $expected ), 'every filter printed exactly once' );
ob_start();
do_action( 'wp_footer' );
check( '' === ob_get_clean(), 'defs not printed twice' );

$defs = new DOMDocument();
check( $defs->loadXML( trim( $footer ) ), 'printed defs are well-formed XML (title/desc/data-* included)' );

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
