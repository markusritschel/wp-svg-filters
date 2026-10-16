<?php
/**
 * SVG Filter Kit — loader.
 *
 * Reads every *.svg file in filters/, inlines all <filter> definitions once
 * per page before </body>, and generates CSS classes:
 *
 *   .fx-duotone      { filter: url(#fx-duotone); }        whole element
 *   .fx-duotone--bg  background only, content stays untouched:
 *                    - normal backgrounds: filtered ::before copy
 *                    - Divi JS parallax:  filter on .et_parallax_bg (Divi 4)
 *                      or .et-pb-parallax-background (Divi 5)
 *
 * WordPress filters (hooks):
 *   svgf_directory     (string) absolute path of the folder with the SVG files
 *   svgf_bg_variants   (bool)   generate the --bg classes, default true
 *   svgf_load_assets   (bool)   enqueue glass CSS + interactive JS, default true
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public URL for a file inside the module, whether it lives in the child
 * theme, the parent theme or a plugin.
 *
 * @param string $rel Path relative to the module root, e.g. 'assets/svg-filters.css'.
 */
function svgf_url( $rel ) {
	$dir = wp_normalize_path( SVGF_DIR );
	$rel = ltrim( $rel, '/' );

	foreach ( array(
		array( get_stylesheet_directory(), get_stylesheet_directory_uri() ),
		array( get_template_directory(), get_template_directory_uri() ),
	) as $pair ) {
		$root = trailingslashit( wp_normalize_path( $pair[0] ) );
		if ( 0 === strpos( $dir . '/', $root ) ) {
			return trailingslashit( $pair[1] ) . ltrim( substr( $dir, strlen( $root ) ) . '/' . $rel, '/' );
		}
	}

	return plugins_url( $rel, SVGF_FILE ); // module moved into a plugin
}

/**
 * Collect filter markup and IDs from all SVG files. Cached per request and
 * in a transient keyed on file names + modification times.
 *
 * @return array{markup:string, ids:string[]}
 */
function svgf_collect() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$dir   = apply_filters( 'svgf_directory', SVGF_DIR . '/filters' );
	$files = glob( trailingslashit( $dir ) . '*.svg' ) ?: array();
	sort( $files );

	// 'markup2': bump when the extraction below changes, so cached markup from older code is rebuilt.
	$signature = md5( $dir . '|' . implode( '|', array_map( static function ( $f ) {
		return basename( $f ) . ':' . filemtime( $f );
	}, $files ) ) . '|' . SVGF_VERSION . '|markup2' );

	$stored = get_transient( 'svgf_cache' );
	if ( is_array( $stored ) && ( $stored['sig'] ?? '' ) === $signature ) {
		return $cache = $stored['data'];
	}

	$data = array( 'markup' => '', 'ids' => array() );

	foreach ( $files as $file ) {
		$svg = (string) file_get_contents( $file );

		// Basic hardening: theme files are trusted, but never pass on scripts or event handlers.
		$svg = preg_replace( '#<script\b.*?</script>#is', '', $svg );
		$svg = preg_replace( '#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $svg );

		// data-* attributes are metadata for the demo site and README, not needed on the page.
		$svg = preg_replace( '#\sdata-[\w.:-]+\s*=\s*("[^"]*"|\'[^\']*\')#', '', $svg );

		if ( ! preg_match_all( '#<filter\b[^>]*\bid="([^"]+)"[^>]*>.*?</filter>#s', $svg, $m ) ) {
			continue;
		}
		foreach ( $m[1] as $i => $id ) {
			if ( in_array( $id, $data['ids'], true ) ) {
				continue; // duplicate ID: first file wins
			}
			$data['ids'][]   = $id;
			$data['markup'] .= $m[0][ $i ] . "\n";
		}
	}

	set_transient( 'svgf_cache', array( 'sig' => $signature, 'data' => $data ), WEEK_IN_SECONDS );
	return $cache = $data;
}

/**
 * Print the hidden defs SVG. Must not use display:none (breaks filters in
 * Firefox and Safari).
 *
 * Printed in the footer only: url(#id) references resolve document-wide, and
 * wp_body_open can fire inside discarded output buffers (seen with Divi),
 * which would swallow the defs while the once-per-request guard blocks a
 * reprint.
 */
function svgf_print_defs() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$data = svgf_collect();
	if ( '' === $data['markup'] ) {
		return;
	}
	$printed = true;

	echo "\n" . '<svg id="svgf-defs" aria-hidden="true" focusable="false" width="0" height="0" '
		. 'style="position:absolute;width:0;height:0;overflow:hidden" xmlns="http://www.w3.org/2000/svg">'
		. '<defs>' . $data['markup'] . '</defs></svg>' . "\n";
}
add_action( 'wp_footer', 'svgf_print_defs', 1 );

/**
 * Build the generated CSS.
 */
function svgf_build_css() {
	$ids = svgf_collect()['ids'];
	$bg  = (bool) apply_filters( 'svgf_bg_variants', true );
	$css = '';

	foreach ( $ids as $id ) {
		$c    = sanitize_html_class( $id );
		$css .= ".{$c}{filter:url(#{$c})}";

		if ( ! $bg ) {
			continue;
		}

		// Normal backgrounds: filtered copy of the inherited background behind the content.
		$css .= ".{$c}--bg{position:relative;isolation:isolate;overflow:hidden}"
			. ".{$c}--bg::before{content:'';position:absolute;inset:0;z-index:-1;"
			. "background:inherit;filter:url(#{$c});pointer-events:none}";

		// Divi JS parallax: the image lives in a child span, filter that instead.
		// Divi 4: span.et_parallax_bg — Divi 5: span.et-pb-parallax-background
		$css .= ".{$c}--bg .et_parallax_bg,.{$c}--bg .et-pb-parallax-background{filter:url(#{$c})}";
	}

	// Distortion and soft-blur filters affect edges: enlarge the background copy a little.
	if ( $bg ) {
		foreach ( array( 'fx-wave', 'fx-wave-anim', 'fx-glitch', 'fx-melt', 'fx-rough', 'fx-blur' ) as $id ) {
			if ( in_array( $id, $ids, true ) ) {
				$css .= ".{$id}--bg::before{inset:-5%}";
			}
		}
	}

	return $css;
}

/**
 * Enqueue generated CSS plus optional glass CSS and interactive JS.
 */
function svgf_enqueue() {
	$assets = apply_filters( 'svgf_load_assets', true );
	$css    = SVGF_DIR . '/assets/svg-filters.css';
	$js     = SVGF_DIR . '/assets/svg-filters.js';

	if ( $assets && file_exists( $css ) ) {
		wp_enqueue_style( 'svgf', svgf_url( 'assets/svg-filters.css' ), array(), filemtime( $css ) );
	} else {
		wp_register_style( 'svgf', false, array(), SVGF_VERSION );
		wp_enqueue_style( 'svgf' );
	}
	wp_add_inline_style( 'svgf', svgf_build_css() );

	if ( $assets && file_exists( $js ) ) {
		wp_enqueue_script( 'svgf', svgf_url( 'assets/svg-filters.js' ), array(), filemtime( $js ), array(
			'in_footer' => true,
			'strategy'  => 'defer',
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'svgf_enqueue', 20 );

/**
 * Clear the cache when the theme is switched (file changes are detected
 * automatically through modification times).
 */
add_action( 'after_switch_theme', static function () {
	delete_transient( 'svgf_cache' );
} );
