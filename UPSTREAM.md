# Upstream proposal

What this pack works around, why, and an open offer to Soflyy (Oxygen / Agent Connector for WP) to take any of it upstream.

**Re-measured 2026-08-21 on Oxygen 6.2.0-beta.5 + Breakdance Elements for Oxygen 1.1.0.beta.1 + Agent Connector for WP 1.26.2, WordPress 7.1, PHP 8.2.29**, plus one confirmation on a production site running 6.2.0-beta.2 / PHP 8.3.33. The previous revision of this document was measured on 6.2.0-beta.2; the sections below say explicitly what changed between the two. These findings are version-bound — re-run `bdox/setup-report` before treating any of them as current.

The code is GPL-2.0-or-later. Take it in whole or in part, with or without credit.

---

## Fixed in 6.2.0 — thank you, and please leave these fixed

**`!important` now survives `insert-stylesheet`.** `mcp/design/css-to-properties.php::build_selector_props()` diverts every declaration with `getIsImportant()` into `custom_css` with the flag appended, before the typed converters run. Verified end to end: importing

```css
.probe { color: #ff0000 !important; padding: 8px; }
```

stores `spacing.spacing.padding` as a typed property and `custom_css.custom_css` as `:selector {\n  color: #f00 !important;\n}`. On beta.2 the flag was stripped before routing and the whole compiled `selectors.css` of a production site contained zero occurrences of `!important`. Items 5 and 5a of the previous revision are closed.

**`set-global-settings` deep-merges.** `breakdance_mcp_deep_merge_settings()` merges assoc arrays key by key, replaces lists and scalars. Verified: after writing `colors.palette` and `typography.body_font`, a second call carrying only `typography.base_font_size` left both intact. Item 2 (the settings half) is closed.

**`get-global-settings` no longer fails on an empty install.** `breakdance_ability_get_global_settings()` normalises a missing or empty tree to `{"settings": {}}`. Item 3 is half closed — see below for the half that is not.

---

## Still open

### 1. `BREAKDANCE_MCP_HIDDEN_ELEMENTS` still makes the core elements unaddressable

```php
count( \Breakdance\Elements\get_element_classnames() )   // 186
count( breakdance_mcp_visible_element_classnames() )     // 151   without our filter
```

`Section`, `Heading` and `Icon` came off the constant since beta.2 — thank you. The constant now holds 14 entries: `CssCode`, `HtmlCode`, `PhpCode`, `CodeBlock`, `FHtmlCode`, `Div`, `WrapperLink`, `Text`, `TextLink`, `Image`, `Image2`, `RichText`, `FSvgIcon`, `SvgIcon`.

The comment in the source says the legacy elements are banned so the AI uses the fundamentals instead. On **BEFO 1.1.0.beta.1**, which is the version the Oxygen updater currently offers on our licence, the fundamentals **do not exist**:

```php
class_exists('EssentialElements\FText')          // false
class_exists('EssentialElements\FImage')         // false
class_exists('EssentialElements\FRichText')      // false
class_exists('EssentialElements\FTextLink')      // false
class_exists('EssentialElements\FSvgIcon2')      // false
class_exists('EssentialElements\FHtmlCode')      // false
class_exists('EssentialElements\ContainerLink')  // false
```

So the ban points at replacements that are not installed, and the fallbacks that *are* installed have no Design tab. Measured with `oxygen-get-element-schemas`: `EssentialElements\{Section,Heading,Text,Image2,Button}` all expose a `design` section; `OxygenElements\{Container,Text,Image}` expose only `content`/`settings`/`meta`. A client-editable site is exactly the case where the Design tab is the point, so an agent steered onto `OxygenElements` produces a page the client cannot restyle.

Suggestion: gate the fundamentals half of the constant on `class_exists()` for the fundamental it names, so a site without them keeps the legacy element addressable.

### 1a. NEW — the Elements settings page and the MCP element list share a filter

This one is not in the previous revision because we had it backwards there: we wrote that `oxygen_builder_hidden_elements` was independent of MCP. It is not.

`admin/settings-page/tabs/elements.php` registers

```php
add_filter('breakdance_builder_elements', '…\ElementsTab\filter_builder_elements', 10, 1);
```

and `mcp/schemas/elements.php::breakdance_mcp_visible_element_classnames()` ends with

```php
$visible = bdox_run_filters('breakdance_builder_elements', array_values($non_experimental));
```

so the same list a human curates in **Oxygen → Settings → Elements** is subtracted from what an agent can address. Measured: adding `EssentialElements\Button` to the option took `oxygen-get-element-slugs` from 164 to 163 and made `get-element-schemas` refuse it; removing it restored 164.

Two consequences worth deciding on deliberately:

- A stock Oxygen install ships that option pre-filled with ten elements by `\Breakdance\BreakdanceOxygen\addDefaultHiddenElements()`, so **`Section`, `Heading` and `Icon` are still invisible to MCP on a default install** even though you took them off the constant. The two mechanisms overlap, and the fix to one is undone by the other.
- Anyone who tidies the Add panel before handing a site over silently removes elements from the agent that maintains it. Our workaround is a note in the docs — *build first, hide last* — which is a poor substitute for the two lists being separate.

If editor curation is meant to be a UI preference, MCP should read the pre-filter list. If it is meant to be policy, it should say so, because right now it is discoverable only by measuring.

**And the coupling costs us too, in the other direction.** Our restore has to go on that same filter, so it also clears `alwaysHide` in the builder's Add panel — `get_elements_for_builder()` hands the filtered array to `FilteredGets\addPanelRules()`, which sets the flag on whatever is missing from it. Measured: with the pack active, `Section`, `Div` and `Heading` report no `alwaysHide`; with its filter removed, all three do. So a pack that only wants to make elements addressable by an agent cannot avoid un-simplifying the panel for the human, because the filter carries no caller context. A second filter — or a `$context` argument on this one — would let both of us do the right thing.

### 1c. `oxygen_builder_hidden_elements` is not visible where you would look for it

Related to 1a and cheap to fix: the Elements settings screen lists elements alphabetically with no indication that ten are hidden by default, so a maintainer scrolling the list sees `Blockquote` and concludes nothing is hidden. A count ("10 elements hidden") above the list, or sorting the hidden ones first, would save a lot of wrong diagnoses.

### 1b. `addDefaultHiddenElements()` re-hides on every version bump

The guard is `if (is_array($current) && count($current) > 0) return;`, and the caller is `handleVersionChange()`, which runs whenever `__BREAKDANCE_VERSION !== last_migrated_version`. So a site that deliberately unhid everything gets all ten re-hidden by the next Oxygen update. Measured: an empty list *does* persist across normal requests, so the only trigger is the migration (and the setup wizard). The source already carries `// TODO: Run this only once` at that call site.

### 2. `insert-stylesheet` is still replace-only

`.probe{margin:4px}` imported over a selector already holding `padding` plus a `custom_css` block leaves only the margin: `merge_breakpoint_properties()` replaces the whole map for the breakpoint it touches. Changing one property therefore drops the rest, which is the most common regression when an agent edits an existing design system. A `patch` variant, or a documented read-modify-write, would close it.

### 2a. `insert-stylesheet` lowercases custom-property names

```css
.probe { --myVar: 4px; padding: var(--myVar); }
```

is stored as

```css
:selector {
  --myvar: 4px;
  padding: var(--myVar);
}
```

The declaration is lowercased, the reference is not, so every `var(--myVar)` dies silently while the import reports success. Custom property names are case-sensitive per spec.

### 3. `get-global-settings` still fails its own output validation on data the builder wrote

The empty case is fixed. A real site is not:

```
output[settings][buttons][primary][corner_radius][unit] is not of type object,null
```

and you get nothing back — not even the branches that validate. The cause is a shape mismatch inside Oxygen itself: the output schema declares `buttons.primary.corner_radius` as a breakpoint-keyed map (`propertyNames` restricted to the breakpoint enum, `additionalProperties` the unit object), while the builder stores it flat:

```json
"corner_radius": {"number": null, "unit": "custom", "style": "var(--radius-s)"}
```

Note that `buttons.button_presets[].styles.corner_radius` in the same stored tree **is** breakpoint-keyed, so the same control is persisted two different ways depending on where it sits. Observed on a production site (6.2.0-beta.2) and reproduced on a clean 6.2.0-beta.5 install by storing that exact value.

A settings reader should not be able to refuse the settings. If validation must stay, please degrade to returning the tree with a warning rather than an error.

### 3a. `set-global-settings` rejects keys the renderer reads

`buttons.primary.background_hover` is accepted and persisted — that object is open. `buttons.primary.typography.color_hover` is rejected:

```
input[settings][buttons][primary][typography][color_hover] — color_hover is not a valid property of object
```

because that sub-object carries `additionalProperties: false`. But `elements/macros/atom-v1-button-css.twig` line 34 reads exactly that key:

```twig
--bde-button-{{buttonStyle}}-text-color-hover: {{ styles.typography.color_hover|default(styles.typography.color)|… }}
```

So the schema forbids a value the renderer consumes. (`buttons.primary.typography.color` also has to be breakpoint-keyed on input while several sibling colours are plain strings — same family of inconsistency.)

### 4. There is still no cache-regeneration ability

The 6.2.0-beta.5 server exposes 49 tools and none of them rebuilds CSS. Oxygen's own save paths do call it — `data/save.php:121` runs `generateCacheForPost($id)` on a document save, and `save_global_settings()` / `save_selectors()` run `generateCacheForGlobalSettings()` — so the gap is narrower than we wrote in the previous revision. It still bites for data changed outside those paths, and for a compiled cache that predates a plugin update, which renders the old default CSS and looks exactly like a specificity bug.

### 5. Media images are unreachable through `edit-post`

Unchanged from the previous revision, re-measured on beta.5. The `wpmedia` control schema is

```php
'properties' => ['id' => …, 'url' => …, 'alt' => …]   // + additionalProperties: false
```

while both `OxygenElements\Image` and `EssentialElements\Image2` resolve their `src` as `content.image.media.sizes[content.image.size].url` and their `srcset` as `content.image.media.attributes.srcset`. Neither `sizes` nor `attributes` is in the schema, so an image set through `edit-post` with a named size renders the 540×540 grey placeholder while the call reports success. Upstream report: [soflyy/agent-connector-for-wp#79](https://github.com/soflyy/agent-connector-for-wp/issues/79).

### 6. Renderer bug: `text-decoration-line: Array`

Unchanged. `global-styles/typography/typography.css.twig`, **lines 114 and 126**:

```twig
text-decoration-line: {{ settings.typography.advanced.links.decoration.line }};
text-decoration-line: {{ settings.typography.advanced.links.decoration.line_hover }};
```

`decoration.line` is declared as an array in the settings schema (`items` enum `underline`/`overline`/`line-through`/`none`), so it compiles to the literal `text-decoration-line:Array` and the browser drops it. The element-level macro `customTypographyValuesAsCssVariables` does apply `| join(' ')`, so the same value shape is correct in one place and broken in the other. A `| join(' ')` on both lines makes them consistent.

---

**The code.** The pack is at https://github.com/NertiPL/unofficial-abilities-for-breakdance-elements — GPL-2.0-or-later, nine abilities. Take any of it, in whole or in part, with or without credit. Every ability here is a workaround we would rather delete; three of them shrank in 0.8.0 because of your 6.2.0 fixes. The element-default annotations in `abilities/defaults.php` are the piece most worth keeping as documentation: they are the questions an agent asks on every build.

Happy to open separate issues per item if that is easier to triage.
