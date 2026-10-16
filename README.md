# SVG Filter Kit for WordPress themes

Inline SVG filters, applied with CSS classes. Works with any theme that calls
`wp_footer()` (Divi, classic and block themes). The filter definitions are
printed once before `</body>`; `url(#id)` references resolve document-wide.

**Live demo of every filter:** <https://markusritschel.github.io/wp-svg-filters/>

## Installation

The module lives in a folder named `svg-filters/` in the root of your child
theme. Pick one of these ways to get it there; run the commands in the child
theme's folder (or adjust the path).

### 1. git subtree (recommended if your theme is a git repo with CI deploy)

```sh
git subtree add --prefix svg-filters https://github.com/markusritschel/wp-svg-filters.git main --squash
```

The files become ordinary files of your theme repository, so clones and CI
deploys need nothing special. Update later with:

```sh
git subtree pull --prefix svg-filters https://github.com/markusritschel/wp-svg-filters.git main --squash
```

`--prefix` is relative to the root of the repository. If your repository is
not the theme folder itself (e.g. it is `wp-content/`), use the full path,
e.g. `--prefix themes/your-child-theme/svg-filters`.

### 2. git submodule

```sh
git submodule add https://github.com/markusritschel/wp-svg-filters.git svg-filters
git commit -m "Add SVG Filter Kit"
```

The theme repository only stores a pointer to a commit of this repository.
Everything that checks out the theme must fetch the submodule too:

- fresh clones: `git clone --recurse-submodules …` or `git submodule update --init`
- GitHub Actions: `actions/checkout` with `submodules: true`, otherwise
  `svg-filters/` is deployed empty (and `require_once` fails)

Update with `git submodule update --remote svg-filters` and commit the new
pointer.

### 3. git clone (only for themes that are not under git)

```sh
git clone https://github.com/markusritschel/wp-svg-filters.git svg-filters
```

Update with `git -C svg-filters pull`. Don't do this inside a theme that is
itself a git repository: `git add` then records `svg-filters/` as an
_embedded repository_ (a bare commit pointer, without `.gitmodules` and
without the files; git warns "adding embedded git repository"). Other clones
and CI deploys get an empty folder. Use a subtree or submodule instead.

### 4. Release zip

Download `svg-filters-vX.Y.Z.zip` from the
[releases](https://github.com/markusritschel/wp-svg-filters/releases) and
unzip it in the child theme root; it contains the `svg-filters/` folder. To
update, replace the folder.

### Load the module

Add to the child theme's `functions.php`, at the top level (not inside a
function or hook callback):

```php
require_once get_stylesheet_directory() . '/svg-filters/svg-filters.php';
```

All paths resolve relative to the module folder, so it also works inside a
parent theme or a plugin without changes. The module guards against being
loaded twice.

## Usage

Every `<filter id="fx-…">` found in `filters/` produces two classes:

| Class             | Effect                                               |
| ----------------- | ---------------------------------------------------- |
| `.fx-duotone`     | Filters the whole element including its content      |
| `.fx-duotone--bg` | Filters only the background, content stays untouched |

`--bg` covers two cases automatically:

- **Normal background image or gradient:** a filtered copy of the element's
  background is placed in `::before`, behind the content.
- **Divi JS parallax ("True Parallax"):** the filter goes on the span that
  holds the image: `.et-pb-parallax-background` in Divi 5,
  `.et_parallax_bg` in Divi 4.

### Divi

- Add the class under _Advanced → CSS ID & Classes → CSS Class_ of a
  section, row or module.
- Divi's own _Design → Filters_ settings write their own `filter:` on the
  module and override the class. Leave them at defaults, or put the class
  on a child element.
- `--bg` uses `::before` on normal backgrounds. Check that Divi or your CSS
  doesn't already style `::before` on that element.
- Divi **CSS parallax** (`background-attachment: fixed`) is not supported: a
  filter on that element can make Chrome drop the fixed behaviour. Use True
  Parallax with `--bg`.
- The defs are printed in `wp_footer` only. Divi fires `wp_body_open` inside
  discarded output buffers, which would swallow them.

### Included filters

Generated from the metadata in the SVG files (see below). The
[demo site](https://markusritschel.github.io/wp-svg-filters/) shows each one
live.

<!-- filters:start -->
| File | Class | Category | Description | Notes |
|---|---|---|---|---|
| duotone.svg | `fx-duotone` | color | Converts to grayscale, then maps shadows and highlights to two brand colors with table lookups. | Set the two colors in the `tableValues` of feFuncR/G/B. |
| posterize.svg | `fx-posterize` | color | Discrete steps per channel flatten gradients into screen-print bands. |  |
| threshold.svg | `fx-threshold` | color | Cuts luminance into two ink tones for stencil and photocopy looks. |  |
| tint.svg | `fx-tint` | color | Keeps luminance and replaces hue and saturation with a flood color through the `color` blend mode. | Change `flood-color`. |
| grain.svg | `fx-grain` | texture | Blends high-frequency mono noise with `overlay` and mixes it back at about half strength. |  |
| paper.svg | `fx-paper` | texture | Lights low-frequency noise as a height map and multiplies it over the image like a print on cotton paper. |  |
| blur.svg | `fx-blur` | distortion | Gaussian blur whose edges fade out, for elements, text, icons and shapes. |  |
| blur.svg | `fx-blur-solid` | distortion | Gaussian blur with duplicated edges and forced opacity, so rectangular images and backgrounds keep a crisp border. | Makes transparent areas opaque: not for text or cut-out shapes. Pair it with the `--bg` class. |
| glitch.svg | `fx-glitch` | distortion | Splits the channels, offsets red and blue, recombines them with `screen` and slices the result with horizontal band noise. |  |
| rough.svg | `fx-rough` | distortion | Small, busy displacement gives type and icons a hand-cut, ink-bleed edge. | Best on text and icons. |
| wave.svg | `fx-wave` | distortion | Anisotropic noise (low x, higher y frequency) displaces the image into horizontal ripples like a reflection. |  |
| goo.svg | `fx-goo` | shape | Blurs, then snaps the halo back to a hard edge with a steep alpha curve, so nearby shapes fuse like liquid. | Apply it to the parent of the moving shapes. |
| outline.svg | `fx-outline` | shape | Dilates the alpha, subtracts the original and fills the remaining ring with a flood color. | Change `flood-color`. Works on text, icons and any shape. |
| bevel.svg | `fx-bevel` | light | Blurred alpha becomes a height map whose specular highlight is added back onto the source as a glossy rim. | Text and icons. |
| emboss.svg | `fx-emboss` | light | A 3×3 convolution kernel; swap the kernel for sharpen, edge detect or box blur. |  |
| inner-shadow.svg | `fx-inner-shadow` | light | Inverts the alpha, blurs and offsets it and clips it to the shape, which CSS `box-shadow: inset` cannot do on text. | Text and icons. |
| marble.svg | `fx-marble` | fill | Stretched noise, recolored with a matrix and clipped to the source alpha, so text stays real, selectable text. | Text and icons. |
| liquid.svg | `fx-liquid` | glass | Displaces the backdrop through a smooth noise lens so content behind the pane bends; other browsers get a frosted fallback. | Chromium only. Use it through `.glass .glass--liquid` (backdrop-filter), not as an `.fx-` class. |
| grain-anim.svg | `fx-grain-anim` | motion | Steps the noise `seed` discretely at about 12 fps for a projected-film flicker. |  |
| hue-cycle.svg | `fx-hue-cycle` | motion | Rotates the hue through 360° with a single animated attribute, the cheapest animated filter there is. |  |
| melt.svg | `fx-melt` | motion | The displacement `scale` eases from 0 to 45 on hover, or on tap on touch screens. | Needs `assets/svg-filters.js`. Duplicate the file with a new ID for independent instances. |
| spotlight.svg | `fx-spotlight` | motion | JavaScript moves an `fePointLight` to the pointer over a noise surface. | Needs `assets/svg-filters.js`. All `.fx-spotlight` elements share one light. |
| wave-anim.svg | `fx-wave-anim` | motion | SMIL animates `baseFrequency`, so the distortion field breathes. |  |
<!-- filters:end -->

### Glass

- `.glass` frosted glass, all browsers (plain CSS).
- `.glass .glass--liquid` refraction in Chromium, frosted fallback elsewhere.

Glass processes what lies _behind_ the element, so it only makes sense on
semi-transparent boxes placed over an image or colorful content.

## Adding your own filters

Drop a new file into `filters/`:

```xml
<svg xmlns="http://www.w3.org/2000/svg">
  <filter id="fx-myeffect" color-interpolation-filters="sRGB"
          data-category="color" data-support="all" data-cost="low" data-demo="scene">
    <title>My effect</title>
    <desc>One sentence on what it does.</desc>
    ...
  </filter>
</svg>
```

The classes `.fx-myeffect` and `.fx-myeffect--bg` appear automatically. The
cache invalidates on file changes. One file may contain several filters; IDs
must be unique across all files (the first one wins).

`<title>`, `<desc>` and the `data-*` attributes are optional for the loader
(it prints them along, where they are inert). The demo site build requires
them:

| Attribute        | Values                                                                        |
| ---------------- | ----------------------------------------------------------------------------- |
| `data-category`  | `color`, `texture`, `distortion`, `shape`, `light`, `fill`, `glass`, `motion` |
| `data-support`   | `all`, `caveats`, `chromium`                                                  |
| `data-cost`      | `low`, `medium`, `high`                                                       |
| `data-demo`      | `scene`, `type`, `goo`, `glass`, `spot`, `melt`                               |
| `data-note`      | optional usage tip; `` `code` `` in backticks                                 |
| `data-demo-text` | optional word shown by the `type` demo                                        |
| `data-demo-bg`   | optional `dark` background for the `type` demo                                |

Don't use `>` inside attribute values: the loader extracts filters with a
regular expression.

## Options (WordPress filters)

```php
// Different folder for the SVG files
add_filter( 'svgf_directory', fn() => get_stylesheet_directory() . '/my-filters' );

// No --bg variants
add_filter( 'svgf_bg_variants', '__return_false' );

// No glass CSS / interactive JS
add_filter( 'svgf_load_assets', '__return_false' );
```

## Pitfalls

- Use `color-interpolation-filters="sRGB"` or colors look washed out.
- Widen `x/y/width/height` on the filter for outlines, glows and offsets.
- Turbulence, lighting and displacement are expensive on large areas,
  animated ones even more so. Test in Safari.
- `.fx-spotlight` and `.fx-melt` share one filter per page; all elements
  using them react together. Duplicate the file with a new ID for
  independent instances.
- Animations pause under `prefers-reduced-motion` and in hidden tabs.

## Development

```sh
php tests/loader-test.php                    # loader against WordPress stubs
for f in filters/*.svg; do xmllint --noout "$f"; done

# Demo site (branch docs), built against this checkout:
git worktree add ../svg-filters-docs docs
node ../svg-filters-docs/build.mjs --main "$PWD"            # -> ../svg-filters-docs/dist/
node ../svg-filters-docs/build.mjs --main "$PWD" --readme   # regenerate the filter table above
```

Pushes to `main` or `docs` rebuild and deploy the demo site
(`.github/workflows/pages.yml`, identical on both branches). To release, bump
`SVGF_VERSION` in `svg-filters.php`, update `CHANGELOG.md` and push a tag
`vX.Y.Z`; `.github/workflows/release.yml` attaches `svg-filters-vX.Y.Z.zip`
to the GitHub release. `.github/` and `tests/` are not part of the zip.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
