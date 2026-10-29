# wp-svg-filters (SVG Filter Kit)

A self-contained WordPress module (theme or plugin) that inlines a library
of SVG filters (`filters/*.svg`) once per page and generates `.fx-<name>`
and `.fx-<name>--bg` classes, plus glass CSS and a small script for the
interactive filters. Its metadata (titles, categories, tuning hints,
presets) also feeds the demo site (branch `docs`, GitHub Pages) and the
Divi 5 plugin [divi5-svg-filters](https://github.com/markusritschel/divi5-svg-filters),
which bundles this repo as a git subtree in `kit/`. User and author docs:
`README.md` (installation, hint syntax, presets, options, pitfalls).

**Read inside another project's `kit/` folder?** That is a subtree copy:
don't edit it there. Change this repo, release a tag, and pull it into the
project with `git subtree pull --prefix=kit <this repo> vX.Y.Z --squash`.

## Commands

```sh
php tests/loader-test.php                    # loader + every filter's metadata, against WordPress stubs
for f in filters/*.svg; do xmllint --noout "$f"; done

# Demo site and the README filter table (branch docs, built against this checkout)
git worktree add ../svg-filters-docs docs
node ../svg-filters-docs/build.mjs --main "$PWD"            # -> ../svg-filters-docs/dist/
node ../svg-filters-docs/build.mjs --main "$PWD" --readme   # regenerate the table in README.md
```

Releases: bump `SVGF_VERSION` in `svg-filters.php`, add a `CHANGELOG.md`
entry, tag `vX.Y.Z` and push the tag; `.github/workflows/release.yml`
attaches `svg-filters-vX.Y.Z.zip` (files marked `export-ignore` in
`.gitattributes` stay out). Pushes to `main` or `docs` redeploy the demo
site (`pages.yml`, identical on both branches). A release tagged on a PR
branch only reaches `main`'s history with "Create a merge commit"; merge
the kit PR before the plugin PR that pulls it.

## Architecture

`svg-filters.php` defines `SVGF_VERSION`/`SVGF_DIR` (a second copy returns
early, so theme and plugin copies don't clash) and requires
`inc/loader.php`. `svgf_collect()` reads every `<filter>` from the files
in `filters/` (folder via `svgf_directory`) by regular expression, then
the markup from `svgf_extra_sources` (file IDs win), and caches the
result in the transient `svgf_cache`, keyed on file names and modification
times. `svgf_filters()` returns per filter its title, description,
category, tuning hints (`tweaks`: key, attr, default, hint) and presets
from the unstripped markup, plus the printable markup with every `data-*`
removed. On `wp_footer` `svgf_print_defs()` prints all filters plus
`svgf_extra_defs` in one hidden `<svg id="svgf-defs">`; `svgf_build_css()`
adds the `.fx-*` and `.fx-*--bg` classes inline (the `--bg` variant
filters a `::before` copy of the background and Divi's parallax layers),
and `svgf_enqueue()` loads `assets/svg-filters.css` (glass) and
`assets/svg-filters.js` (pauses SMIL under reduced motion and in hidden
tabs, marks Chromium with `html.svgf-backdrop-url` for refractive glass,
drives `fx-spotlight` and `fx-melt` per instance via `data-svgf-*`).

## Conventions for filters

- Metadata as documented in `README.md` ("Adding your own filters"):
  `data-category` and the other `data-*` on `<filter>`, hints as
  `data-tweak-<attr>="[min, max unit] {type} Label | Description"`,
  presets as `data-presets` JSON. `tests/loader-test.php` checks the hint
  syntax and that every preset key is a tweak key.
- Tweak keys (`<primitive>|<attr>`, `:2`, `:3` … for repeated primitives,
  `#id` for primitives with an ID) are saved data in the Divi plugin:
  renaming, reordering or removing tuned primitives breaks saved tunings.
  Changing a label or description is safe.
- Labels say what higher values do ("Grain fineness", not "Grain size"
  when higher means finer); colors are hex defaults so they get a picker.
- Filters with `feDisplacementMap` end with the 3×3 smoothing step
  `<feConvolveMatrix order="3" kernelMatrix="0 1 0 1 4 1 0 1 0" divisor="8" preserveAlpha="false" edgeMode="none"/>`
  (Chromium samples displacement without anti-aliasing); Hover melt is the
  exception. The loader test enforces it.
- Look changes are measured, not eyeballed: compare old and new renders
  pixel by pixel in a browser and state the difference in the PR.
- After adding or changing a filter, regenerate the README table (see
  Commands).

## Known Limitations & Improvements

- [medium] Filters are extracted by regular expression: no `>` inside
  attribute values, and the first `<filter>` with an ID wins.
- [low] Refractive glass (and any SVG `backdrop-filter`) renders in
  Chromium only; elsewhere `.glass--liquid` falls back to a frosted blur.
- [low] `fx-spotlight`/`fx-melt` share one filter per page unless each
  element names its own copy (`data-svgf-spotlight`/`data-svgf-melt`).
- [low] Hover melt's hover strength (45) is a constant in
  `assets/svg-filters.js`, not a tuning hint.
- [low] `svgf_extra_defs` is printed unsanitized; callers must pass
  trusted markup.
