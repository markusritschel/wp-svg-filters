# Changelog

All notable changes to the SVG Filter Kit. Versions follow
[Semantic Versioning](https://semver.org/); release tags are `vX.Y.Z` and
must match `SVGF_VERSION` in `svg-filters.php`.

## [1.4.0]

### Added

- Hint syntax 2: `[min, max unit] {type} Label | Description` with optional
  unit (`px`, `deg`, `s`, `%`), control type (`pair`, `levels`, `matrix`)
  and a separate description; all filters use short labels now.
- `data-presets` (JSON) on a filter, returned by `svgf_filters()` as
  `presets`; Emboss (Emboss, Sharpen, Find edges, Soften) and Inner shadow
  (Soft, Hard, Wide, Top, Glow) have presets.

### Changed

- Duotone and Threshold blend two `feFlood` colors by brightness instead of
  per-channel tables, so their colors are tunable as colors (same look;
  previous `tableValues` tunings no longer apply).

## [1.3.0]

### Added

- Extension points for plugins: `svgf_extra_sources` (filters from other
  sources, file IDs win), `svgf_extra_defs` (markup inside the printed
  `<defs>`) and `svgf_filters()` (title, desc, category, tuning hints and
  printable markup per filter).
- `data-svgf-spotlight` / `data-svgf-melt` let elements drive their own copy
  of the interactive filters independently.
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
- `assets/svg-filters.js` listens on `document` (delegated), so spotlight and
  melt also work on elements added after page load.

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
