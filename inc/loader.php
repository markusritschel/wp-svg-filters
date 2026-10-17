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
 *   svgf_extra_sources (string[]) more SVG markup with <filter> elements, e.g. from
 *                      a plugin's database; collected after the files (file IDs win)
 *   svgf_extra_defs    (string) markup appended inside the printed <defs>, e.g. tuned
 *                      filter copies; not sanitized, callers pass trusted markup
 *
 * svgf_filters() returns per-filter metadata (title, desc, category, tuning
 * hints) read from the unstripped markup.
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
 * Collect filter markup and IDs from all SVG files and extra sources. Cached
 * per request and in a transient keyed on file names + modification times
 * and the extra sources.
 *
 * @return array{markup:string, ids:string[], raw:array<string,string>}
 *         raw: per ID the <filter> with its data-* metadata, for svgf_filters().
 */
function svgf_collect() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$dir    = apply_filters( 'svgf_directory', SVGF_DIR . '/filters' );
	$files  = glob( trailingslashit( $dir ) . '*.svg' ) ?: array();
	$extras = array_values( array_filter( (array) apply_filters( 'svgf_extra_sources', array() ), 'is_string' ) );
	sort( $files );

	// 'markup3': bump when the extraction below changes, so cached markup from older code is rebuilt.
	$signature = md5( $dir . '|' . implode( '|', array_map( static function ( $f ) {
		return basename( $f ) . ':' . filemtime( $f );
	}, $files ) ) . '|' . md5( implode( "\0", $extras ) ) . '|' . SVGF_VERSION . '|markup3' );

	$stored = get_transient( 'svgf_cache' );
	if ( is_array( $stored ) && ( $stored['sig'] ?? '' ) === $signature ) {
		return $cache = $stored['data'];
	}

	$data    = array( 'markup' => '', 'ids' => array(), 'raw' => array() );
	$sources = array_merge( array_map( static function ( $f ) {
		return (string) file_get_contents( $f );
	}, $files ), $extras );

	foreach ( $sources as $svg ) {
		// Basic hardening: theme files are trusted, but never pass on scripts or event handlers.
		$svg = preg_replace( '#<script\b.*?</script>#is', '', $svg );
		$svg = preg_replace( '#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $svg );

		// \sid, not \bid: data-* attributes are still present here (data-id would match \bid).
		if ( ! preg_match_all( '#<filter\b[^>]*\sid="([^"]+)"[^>]*>.*?</filter>#s', $svg, $m ) ) {
			continue;
		}
		foreach ( $m[1] as $i => $id ) {
			if ( in_array( $id, $data['ids'], true ) ) {
				continue; // duplicate ID: first source wins (files before extras)
			}
			$data['ids'][]     = $id;
			$data['raw'][ $id ] = $m[0][ $i ];

			// data-* attributes are metadata for the demo site, README and svgf_filters(), not needed on the page.
			$data['markup'] .= preg_replace( '#\sdata-[\w.:-]+\s*=\s*("[^"]*"|\'[^\']*\')#', '', $m[0][ $i ] ) . "\n";
		}
	}

	set_transient( 'svgf_cache', array( 'sig' => $signature, 'data' => $data ), WEEK_IN_SECONDS );
	return $cache = $data;
}

/**
 * Metadata of every collected filter, in collection order.
 *
 * Tuning hints (data-tweak-<attribute>) are keyed '<primitive>|<attribute>',
 * where <primitive> is '#<id>' for primitives with an id, otherwise the tag
 * name, with ':<n>' from its second occurrence on (feComposite:2).
 * Presets come from the filter's data-presets JSON (name => tweak key => value).
 *
 * @return array<string, array{id:string, title:string, desc:string, category:string,
 *         tweaks:array<int, array{key:string, attr:string, default:string, hint:string}>,
 *         presets:array<string, array<string, string>>, markup:string}>
 *         markup: the printable <filter> without data-* attributes.
 */
function svgf_filters() {
	static $filters = null;
	if ( null !== $filters ) {
		return $filters;
	}

	$data    = svgf_collect();
	$filters = array();

	foreach ( $data['ids'] as $id ) {
		$raw  = $data['raw'][ $id ] ?? '';
		$meta = array(
			'id'       => $id,
			'title'    => '',
			'desc'     => '',
			'category' => '',
			'tweaks'   => array(),
			'presets'  => array(),
			'markup'   => preg_replace( '#\sdata-[\w.:-]+\s*=\s*("[^"]*"|\'[^\']*\')#', '', $raw ),
		);

		$doc  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $doc->loadXML( '<svg xmlns="http://www.w3.org/2000/svg">' . $raw . '</svg>', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$filter = $ok ? $doc->getElementsByTagName( 'filter' )->item( 0 ) : null;
		if ( $filter ) {
			foreach ( array( 'title', 'desc' ) as $tag ) {
				$node         = $filter->getElementsByTagName( $tag )->item( 0 );
				$meta[ $tag ] = $node ? trim( $node->textContent ) : '';
			}
			$meta['category'] = $filter->getAttribute( 'data-category' );

			// Presets: keep names with string values only; a broken attribute gives no presets.
			$presets = json_decode( $filter->getAttribute( 'data-presets' ), true );
			foreach ( is_array( $presets ) ? $presets : array() as $name => $values ) {
				$clean = array();
				foreach ( is_array( $values ) ? $values : array() as $key => $value ) {
					if ( is_string( $key ) && is_scalar( $value ) ) {
						$clean[ $key ] = (string) $value;
					}
				}
				if ( is_string( $name ) && $clean ) {
					$meta['presets'][ $name ] = $clean;
				}
			}

			$seen = array();
			foreach ( $filter->getElementsByTagName( '*' ) as $el ) {
				$tag          = $el->localName;
				$seen[ $tag ] = ( $seen[ $tag ] ?? 0 ) + 1;
				$primitive    = $el->hasAttribute( 'id' ) ? '#' . $el->getAttribute( 'id' )
					: ( 1 === $seen[ $tag ] ? $tag : $tag . ':' . $seen[ $tag ] );

				foreach ( $el->attributes as $attr ) {
					if ( 0 !== strpos( $attr->name, 'data-tweak-' ) ) {
						continue;
					}
					$name             = substr( $attr->name, strlen( 'data-tweak-' ) );
					$meta['tweaks'][] = array(
						'key'     => $primitive . '|' . $name,
						'attr'    => $name,
						'default' => $el->getAttribute( $name ),
						'hint'    => $attr->value,
					);
				}
			}
		}

		$filters[ $id ] = $meta;
	}

	return $filters;
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
	$data  = svgf_collect();
	$extra = (string) apply_filters( 'svgf_extra_defs', '' );
	if ( '' === $data['markup'] && '' === $extra ) {
		return;
	}
	$printed = true;

	echo "\n" . '<svg id="svgf-defs" aria-hidden="true" focusable="false" width="0" height="0" '
		. 'style="position:absolute;width:0;height:0;overflow:hidden" xmlns="http://www.w3.org/2000/svg">'
		. '<defs>' . $data['markup'] . $extra . '</defs></svg>' . "\n";
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
