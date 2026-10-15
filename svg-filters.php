<?php
/**
 * SVG Filter Kit — entry point.
 *
 * Self-contained module: everything lives in this folder.
 *
 *   svg-filters/
 *     svg-filters.php   ← this file, include it from functions.php
 *     inc/loader.php
 *     assets/svg-filters.css
 *     assets/svg-filters.js
 *     filters/*.svg
 *
 * Child theme functions.php:
 *   require_once get_stylesheet_directory() . '/svg-filters/svg-filters.php';
 *
 * All paths resolve relative to this folder, so the module can later be
 * moved into a plugin without changes.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'SVGF_VERSION' ) ) {
	return; // already loaded (e.g. theme and plugin copy both present)
}

define( 'SVGF_VERSION', '1.2.1' );
define( 'SVGF_DIR', __DIR__ );
define( 'SVGF_FILE', __FILE__ );

require_once SVGF_DIR . '/inc/loader.php';
