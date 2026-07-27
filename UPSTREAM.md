# Upstream proposal

What this pack works around, why, and an open offer to Soflyy (Oxygen / Agent Connector for WP) to take any of it upstream.

Everything below was measured on **Oxygen 6.2.0-beta.2 + Breakdance Elements for Oxygen 1.1.0.beta.1 + Agent Connector for WP 1.26.0, WordPress 7.0.2, PHP 8.2**, on a clean install. These findings are version-bound; re-run `bdox/setup-report` before treating any of them as current.

The code is GPL-2.0-or-later. Take it in whole or in part, with or without credit. If items 1 to 4 land upstream, most of this pack should disappear, which is the outcome we would prefer.

---

**Title:** MCP layer cannot address the elements pages are built from — plus two renderer bugs found while working around it

Hi,

We build client-editable Oxygen sites through the MCP server and hit the same set of walls on every project. We wrote a small ability pack to get past them and we would rather see the fixes upstream than maintain a workaround, so here is what we measured and the code, free to take, modify or ignore.

**1. `BREAKDANCE_MCP_HIDDEN_ELEMENTS` makes the core elements unaddressable.**

On a clean install:

```php
count( \Breakdance\Elements\get_elements_for_builder() )  // 162
count( breakdance_mcp_visible_element_classnames() )      // 149
```

The 13 hidden ones are `Section`, `Div`, `Heading`, `Text`, `RichText`, `TextLink`, `WrapperLink`, `Icon`, `Image2`, `CodeBlock` and the code elements. `get-element-schemas` reports them as `missing` and `edit-post` rejects them, so an agent has to write the element tree through `php-eval` instead of the tools that exist for it.

`Section` is the costly one. It is the only element that renders `.section-container`, the wrapper consuming `--bde-section-width` and the global section padding. An agent that cannot address `Section` builds every layout on `Div`, so the global container settings never apply and each page ends up carrying its own width in a class. Since `html-to-page` produces `OxygenElements\Container` — which also never renders `.section-container` — the recommended build path and the global container settings are currently mutually exclusive.

This is separate from the builder's `oxygen_builder_hidden_elements` option: unhiding elements in the editor does not change what MCP sees.

The list ends in `bdox_run_filters('breakdance_builder_elements', …)`, a plain `apply_filters` run twice, so it can be restored from outside with two `add_filter` calls. That is all our pack does for this; nothing is patched.

**2. Writes replace instead of merging.** `set-global-settings` persists exactly what it receives, so sending only `colors` wipes global stylesheets, scripts and typography presets. `insert-stylesheet` replaces a selector's whole property map, so changing one property drops the selector's other properties and breakpoints. A `patch` variant of each would remove a whole class of regressions.

**3. `get-global-settings` fails its own output validation on data the builder wrote.** A gradient button background returns:

```
output[settings][buttons][primary][background] is not of type string
```

and you get nothing back, so you cannot inspect what you are about to change. A settings reader should not be able to refuse the settings.

**4. There is no public cache-regeneration ability.** Almost every direct data edit needs `regenerate_post_cache()` followed by `generateCacheForPost()`, and without it a correct edit looks like a broken control.

**5. Renderer bug: `insert-stylesheet` drops `!important`.** Imported `.x{display:none !important}` and `body.breakdance .y{display:flex !important}` both compile into `selectors.css` without `!important`, for `class` and `custom` selectors alike. Measured load order on the front end is:

```
post-<id>-defaults.css → global-settings.css → selectors.css → post-<id>.css
```

so `selectors.css` loads before the per-element file. A flat project class `(0,1,0)` therefore loses to a Design-tab value of the same specificity on load order, and the usual escape hatch does not survive the import. The only path that keeps `!important` is `settings.code.stylesheets`, which loads earliest of all.

**5a. Update 2026-07-27 — the strip happens before routing, not inside it.** Issue #80 suggests checking whether `!important` is lost at the same routing step that discards `background-image`. We measured that, and it is not. Probe submitted through `insert-stylesheet`:

```css
.probe-b { box-shadow:0 1px 2px #000 !important;
           mix-blend-mode:multiply !important;
           will-change:transform !important }
```

`will-change` has no typed control, so it fell through to `custom_css` untouched by any typed handler — and still came back as `will-change:transform`, without the `!important`. A property that no typed handler claims cannot be losing `!important` inside the routing step, so the strip precedes it.

The same probe shows the routing decision is made on the parsed **value**, not on the property name: `box-shadow:0 1px 2px #000` went to the typed `effects.box_shadow` slot, while `box-shadow:none` in an adjacent rule fell through to `custom_css`.

Two further observations worth having:

- The `custom_css` block compiles to a **second rule carrying the same selector**, emitted after the typed rule, so at equal specificity it wins on order. That is currently the only lever an importing agent has over the cascade.
- The typed background handler **emits properties that were never submitted**: a lone `background-image:linear-gradient(0deg,#101010,#101010)` compiles to that gradient plus `background-size:auto`, `background-position:0px 0px`, `background-repeat:repeat` and `background-attachment:scroll`.

Measured on Oxygen 6.2.0-beta.2 + Elements 1.1.0.beta.1 + Agent Connector 1.26.0, WP 7.0.2, PHP 8.3.31. The whole compiled `selectors.css` of that production site contained zero occurrences of `!important`.

**6. Renderer bug: `text-decoration-line: Array`.** `global-styles/typography/typography.css.twig` prints

```twig
text-decoration-line: {{ settings.typography.advanced.links.decoration.line }};
```

without a `join` filter, so an array value compiles to the literal `text-decoration-line:Array` and is silently dropped by the browser. The element-level macro `customTypographyValuesAsCssVariables` does apply `| join(' ')`, so the same value shape is correct in one place and broken in the other. A `| join(' ')` in the global template would make them consistent.

**The code.** The pack is at https://github.com/NertiPL/unofficial-abilities-for-breakdance-elements — GPL-2.0-or-later, eight abilities. Take any of it, in whole or in part, with or without credit. If items 1 to 4 land upstream, most of the pack should disappear, which is the outcome we would prefer. The element-default annotations in `abilities/defaults.php` are probably the piece most worth keeping as documentation: they are the questions an agent asks on every build.

Happy to open separate issues per item if that is easier to triage.
