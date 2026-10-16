# Changelog

All notable changes to the SVG Filter Kit. Versions follow
[Semantic Versioning](https://semver.org/); release tags are `vX.Y.Z` and
must match `SVGF_VERSION` in `svg-filters.php`.

## [Unreleased]

### Added

- Tuning hints (`data-tweak-<attribute>`, optional `[min, max]` range) on
  the primitives worth editing; shown on the demo site and as a *Tunable*
  column in the README filter table.
- Filter metadata inside every `<filter>`: `<title>`, `<desc>` and
  `data-category`, `data-support`, `data-cost`, `data-demo` (optional
  `data-note`, `data-demo-text`, `data-demo-bg`). The loader strips the
  `data-*` attributes before printing (about 6 KB less per page); the demo
  site and the README filter table are generated from them.
- Demo site (branch `docs`), built from the filter files and deployed to
  GitHub Pages.
- Release workflow: tags `v*` get a `svg-filters-vX.Y.Z.zip` attached.
- `tests/loader-test.php`: runs the loader against WordPress stubs.
- LICENSE (GPL-2.0-or-later), this changelog.

### Changed

- README: installation via git subtree, submodule, clone or release zip.

## [1.2.1]

First version in this repository; earlier history was not recorded here.
23 filters in 22 files, including `fx-blur` and `fx-blur-solid`
(`blur.svg`). Behaviour that must be preserved (fixes from 1.0.1 and 1.2.1):

- Filter definitions are printed in `wp_footer` only, never in
  `wp_body_open`: Divi fires `wp_body_open` inside discarded output buffers,
  which swallowed the defs.
- `--bg` classes also target Divi's JS parallax spans:
  `.et_parallax_bg` (Divi 4) and `.et-pb-parallax-background` (Divi 5).
- Paths resolve relative to the module folder (`SVGF_DIR`, `svgf_url()`),
  so the module works in a child theme, parent theme or plugin.
