# SVG Filter Kit for WordPress child themes

Inline SVG filters, applied with CSS classes. Works with any theme that calls
`wp_footer()` (Divi, classic and block themes). The filter definitions are
printed once before `</body>`; `url(#id)` references resolve document-wide.

## Installation

1. Copy the `svg-filters` folder into your child theme:

   ```
   your-child-theme/
     functions.php
     style.css
     svg-filters/
       svg-filters.php
       inc/loader.php
       assets/svg-filters.css
       assets/svg-filters.js
       filters/*.svg
   ```

2. Add to the child theme's `functions.php`, at the top level (not inside a
   function or hook callback):

   ```php
   require_once get_stylesheet_directory() . '/svg-filters/svg-filters.php';
   ```

All paths resolve relative to the module folder, so it also works inside a
parent theme or a plugin without changes.


## Usage

Every `<filter id="fx-…">` found in `filters/` produces two classes:

| Class             | Effect                                                         |
|-------------------|----------------------------------------------------------------|
| `.fx-duotone`     | Filters the whole element including its content                 |
| `.fx-duotone--bg` | Filters only the background, content stays untouched            |

In Divi: *Advanced → CSS ID & Classes → CSS Class*.

`--bg` covers two cases automatically:

- **Normal background image or gradient:** a filtered copy of the element's
  background is placed in `::before`, behind the content.
- **Divi JS parallax ("True Parallax"):** the filter goes on the span that
  holds the image: `.et-pb-parallax-background` in Divi 5,
  `.et_parallax_bg` in Divi 4.

Not supported: Divi **CSS parallax** (`background-attachment: fixed`). A
filter on that element can make Chrome drop the fixed behaviour.

### Included filters

| File | Class | Notes |
|---|---|---|
| blur.svg | `fx-blur` | Soft Gaussian blur, edges fade out; elements, text, icons |
| blur.svg | `fx-blur-solid` | Edges stay crisp and opaque; rectangular images and `--bg` backgrounds only |
| duotone.svg | `fx-duotone` | Adjust `tableValues` for your two colors |
| posterize.svg | `fx-posterize` | |
| threshold.svg | `fx-threshold` | |
| tint.svg | `fx-tint` | Change `flood-color` |
| grain.svg | `fx-grain` | |
| paper.svg | `fx-paper` | |
| wave.svg | `fx-wave` | |
| rough.svg | `fx-rough` | Best on text/icons |
| glitch.svg | `fx-glitch` | |
| goo.svg | `fx-goo` | Apply to the parent of the shapes |
| outline.svg | `fx-outline` | Change `flood-color`; text/icons |
| emboss.svg | `fx-emboss` | |
| bevel.svg | `fx-bevel` | Text/icons |
| inner-shadow.svg | `fx-inner-shadow` | Text/icons |
| marble.svg | `fx-marble` | Text/icons |
| liquid.svg | — | Used by `.glass--liquid` (Chromium only) |
| wave-anim.svg | `fx-wave-anim` | SMIL animation |
| grain-anim.svg | `fx-grain-anim` | SMIL animation |
| hue-cycle.svg | `fx-hue-cycle` | SMIL animation |
| spotlight.svg | `fx-spotlight` | Needs `svg-filters.js` |
| melt.svg | `fx-melt` | Needs `svg-filters.js` |

### Glass

- `.glass` frosted glass, all browsers (plain CSS).
- `.glass .glass--liquid` refraction in Chromium, frosted fallback elsewhere.

Glass processes what lies *behind* the element, so it only makes sense on
semi-transparent boxes placed over an image or colorful content.

## Adding your own filters

Drop a new file into `filters/`:

```xml
<svg xmlns="http://www.w3.org/2000/svg">
  <filter id="fx-myeffect" color-interpolation-filters="sRGB">
    ...
  </filter>
</svg>
```

The classes `.fx-myeffect` and `.fx-myeffect--bg` appear automatically. The
cache invalidates on file changes. One file may contain several filters; IDs
must be unique across all files (the first one wins).

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
- Divi's own *Design → Filters* settings set `filter:` on the module and
  override the class. Leave them at defaults.
- Check that Divi doesn't already use `::before` on an element you give a
  `--bg` class.
- Turbulence, lighting and displacement are expensive on large areas,
  animated ones even more so. Test in Safari.
- `.fx-spotlight` and `.fx-melt` share one filter per page; all elements
  using them react together. Duplicate the file with a new ID for
  independent instances.
- Animations pause under `prefers-reduced-motion` and in hidden tabs.
