# Nerti Abilities for Oxygen

Jedna wtyczka, jedna instalacja, komplet abilities dla agenta pracującego w Oxygenie. Grupy włączają się same, zależnie od tego, co zastaną na witrynie.

MCP ability pack for **Oxygen 6.x + Breakdance Elements for Oxygen**, built on the WP Abilities API and Agent Connector for WP.

**[Download the latest release →](https://github.com/NertiPL/nerti-abilities-for-oxygen/releases/latest)**

Install the `.zip` from the release page, not the green *Code → Download ZIP* button: that one names the folder `…-main`, so WordPress installs it under a different slug and the next update lands as a second copy instead of replacing the first.

It exists because an agent building client-editable pages in Oxygen keeps hitting the same walls. Every claim below was **re-measured on 2026-08-21 on a live install**: Oxygen **6.2.0-beta.5**, Breakdance Elements for Oxygen **1.1.0.beta.1**, WordPress **7.1**, PHP **8.2.29**. Nothing here comes from documentation, and two claims in the 0.7.0 README were wrong by this version — the element counts, and "the hidden-elements option does not affect MCP". Both are corrected below.

## The walls, as measured on 6.2.0-beta.6

### 1. MCP cannot see the elements every page is made of — and two separate bans cause it

```php
count( \Breakdance\Elements\get_element_classnames() )   // 186
count( breakdance_mcp_visible_element_classnames() )     // 151   pack filter removed
count( breakdance_mcp_visible_element_classnames() )     // 164   pack active
```

The 13 the pack restores: `Section`, `Div`, `Heading`, `Icon`, `Text`, `RichText`, `TextLink`, `WrapperLink`, `Image2`, `CodeBlock`, `CssCode`, `HtmlCode`, `PhpCode`. Without them `get-element-schemas` returns them as `missing` and `edit-post` rejects the `element_type`.

- **Ban A — `BREAKDANCE_MCP_HIDDEN_ELEMENTS`** in `mcp/schemas/elements.php`. 14 entries on this version: `CssCode`, `HtmlCode`, `PhpCode`, `CodeBlock`, `FHtmlCode`, `Div`, `WrapperLink`, `Text`, `TextLink`, `Image`, `Image2`, `RichText`, `FSvgIcon`, `SvgIcon`. `Section`, `Heading` and `Icon` came **off** this list since beta.2. No site setting can change it.
- **Ban B — the `oxygen_builder_hidden_elements` option**, pre-filled by `addDefaultHiddenElements()` with 10 entries and **still populated on a current install**: `Section`, `Div`, `Heading`, `Text`, `TextLink`, `WrapperLink`, `Icon`, `RichText`, `CodeBlock`, `Image2`. Updating Oxygen does not clear it.

The **Oxygen → Settings → Elements** screen and `oxygen_builder_hidden_elements` are the same thing, not two views of it: `admin/settings-page/tabs/elements.php` reads and writes `get_global_option('builder_hidden_elements')`, and `Breakdance\Data\get_global_option()` (`plugin/data/api/options.php`) is `get_option( __bdox('meta_prefix') . $field )` with `meta_prefix` = `oxygen_`.

**Ban B is not editor-only.** That same file hangs `filter_builder_elements()` on the `breakdance_builder_elements` filter at priority 10, and `mcp/schemas/elements.php` runs the MCP element list through **that same filter**. Two independent gates, and either one alone is enough to make an element unaddressable. Measured two ways:

- Removing the pack's filter in-process took `breakdance_mcp_visible_element_classnames()` from **164 to 151**, with all ten of Ban B's elements flipping to hidden.
- Adding `EssentialElements\Button` to the option dropped `oxygen-get-element-slugs` from 164 to 163 and made `get-element-schemas` refuse it; removing it restored 164.

> **Build first, hide last.** Hiding elements in Oxygen → Settings → Elements before the agent has finished building cuts the agent off from them.

The pack restores on the same filter at priority **20**, i.e. after the settings page's priority-10 subtraction — which is what makes the restore win.

**Ten-second check that the ten are still hidden**, because "Oxygen updated, surely it cleared that by now" is a tempting and wrong assumption: open **Oxygen → Settings → Elements** and type `Section` into *Filter elements*. The list is alphabetical, so scrolling from the top stops at `Blockquote` long before `Section` and tells you nothing. From the shell: `wp option get oxygen_builder_hidden_elements --format=json`.

#### The price of the restore, stated plainly

Restoring on `breakdance_builder_elements` is not agent-only. The builder's Add panel is driven by the same list: `get_elements_for_builder()` passes the filtered array to `FilteredGets\addPanelRules()`, which sets `alwaysHide` on everything **missing** from it. Put the elements back and the flag never gets set. Measured on the lab install:

| element | pack active | pack filter removed |
|---|---|---|
| `EssentialElements\Section` | visible in Add panel | `alwaysHide` |
| `EssentialElements\Div` | visible in Add panel | `alwaysHide` |
| `EssentialElements\Heading` | visible in Add panel | `alwaysHide` |

So while the pack is active, **the client sees those 13 elements too**, and a human who hides one of the 13 in Oxygen → Settings → Elements is silently overruled — the pack's priority-20 restore runs after the settings page's priority-10 subtraction. That is the exact simplification Oxygen was going for, and the pack removes it.

*Could it restore for MCP only?* Only by sniffing the request. The two consumers are different functions (`get_elements_for_builder()` for the panel, `breakdance_mcp_visible_element_classnames()` for MCP) but they share one filter, and the filter receives no caller information. The discriminator would have to be the admin-ajax action name (`breakdance_load_builder_elements`, `breakdance_load_document`) — a string Oxygen is free to rename, at which point the guard stops matching and silently reverts to today's behaviour. We chose not to trade a visible, documented cost for an invisible, undocumented one.

*The lever instead.* `bdox_pack_restored_elements` is a filter. At handover, drop it to the code elements only — or to nothing — in a one-line mu-plugin, and the Add panel goes back to Oxygen's default while the pack's other eight abilities keep working:

```php
add_filter( 'bdox_pack_restored_elements', '__return_empty_array' );
```

**Handover order, and it is not the same as the build order:** build → hide what the client should not see → *then* narrow `bdox_pack_restored_elements` (or deactivate the pack). Hiding while the pack still restores does nothing.

`Section` is the expensive one. It is the only element that renders `.section-container`, the wrapper that consumes `--bde-section-width` and the global section padding. An agent that cannot address it builds every layout on `Div`, the global container settings never apply, and each page ends up carrying its own width in a class — the exact outcome the global settings exist to prevent.

**Why not the fundamentals, or the native Oxygen elements?** On BEFO 1.1.0.beta.1 the fundamentals **do not exist**: `class_exists()` is `false` for `EssentialElements\FText`, `FImage`, `FRichText`, `FTextLink`, `FSvgIcon2`, `FHtmlCode` and `EssentialElements\ContainerLink`. The native replacements do exist but carry **no Design tab** — measured through `oxygen-get-element-schemas`:

| slug | schema sections |
|---|---|
| `EssentialElements\Section` | `design`, `settings`, `meta` |
| `EssentialElements\Heading` | `content`, `design`, `settings`, `meta` |
| `EssentialElements\Text` | `content`, `design`, `settings`, `meta` |
| `EssentialElements\Image2` | `content`, `design`, `settings`, `meta` |
| `EssentialElements\Button` | `content`, `design`, `settings`, `meta` |
| `OxygenElements\Container` | `settings`, `meta` — **no design** |
| `OxygenElements\Text` | `content`, `settings`, `meta` — **no design** |
| `OxygenElements\Image` | `content`, `settings`, `meta` — **no design** |

A client-editable build needs the Design tab, so the two `add_filter` calls stay. The condition for deleting them is written into the plugin header: after updating BEFO to ≥ 1.1.0.beta.3, check `class_exists('EssentialElements\FText')` and whether its schema has a `design` section. Only if both hold do the filters become dead weight.

### 2. Selector writes replace instead of merging

Still true. `.probe{margin:4px}` imported over a selector already holding `padding` plus a `custom_css` block left **only** the margin. Global settings, on the other hand, were fixed — see wall 5.

### 3. There is still no cache-regeneration tool

None of the 49 tools the 6.2.0-beta.5 MCP server exposes rebuilds CSS. Oxygen's own save paths do (`data/save.php` calls `generateCacheForPost()` on a document save and `generateCacheForGlobalSettings()` on a settings or selector save), so this matters for data changed outside those paths, and for a cache that predates a plugin update — which renders the old default CSS and looks exactly like a specificity bug.

### 4. Element defaults are undocumented and some of them outrank your CSS

`.bde-section .section-container` is `(0,2,0)` and `.bde-text-link > *` is `(0,1,1)`; both beat a flat project class `(0,1,0)` no matter what order the files load in. Without knowing that, an agent writes CSS that silently does nothing.

### 5. What Soflyy fixed in 6.2.0 — stop working around it

- **`!important` survives `insert-stylesheet`.** `mcp/design/css-to-properties.php` now diverts every `!important` declaration into `custom_css` with the flag intact, *before* the typed converters run. Measured end to end: `.probe{color:#ff0000 !important;padding:8px}` stored `padding` as a typed spacing property and `custom_css` as `:selector {\n  color: #f00 !important;\n}`. `bdox/merge-css-selector` is **no longer** the only route to `!important`.
- **`set-global-settings` deep-merges.** `breakdance_mcp_deep_merge_settings()` merges assoc arrays key by key; lists and scalars still replace wholesale. Measured: sending only `typography.base_font_size` left `colors.palette` and `typography.body_font` intact.
- **`get-global-settings` no longer fails on an empty install.** It normalises the empty tree to `{"settings":{}}`.

### 6. `get-global-settings` still refuses real settings

Measured against a production site, then reproduced on the lab install by storing the same value:

```
output[settings][buttons][primary][corner_radius][unit] is not of type object,null
```

You get **nothing** back, not even the parts that validate. The cause is inside Oxygen: the output schema declares `buttons.primary.corner_radius` as a breakpoint-keyed map, while the builder stores it flat as `{number, unit, style}` — and `buttons.button_presets[].styles.corner_radius` **is** breakpoint-keyed, so the same control is persisted two different ways.

### 7. `set-global-settings` still rejects keys the renderer reads

`buttons.primary.background_hover` goes through (that object is open), but `buttons.primary.typography.color_hover` is rejected with `color_hover is not a valid property of object`, because that sub-object is `additionalProperties: false`. `elements/macros/atom-v1-button-css.twig` reads exactly that key for the button hover text colour.

## Abilities

| Ability | What it does |
|---|---|
| `bdox/house-rules` | The build rules served at the MCP boundary instead of from a skill file a foreign agent never sees: Design tab before any CSS class, the selector budget a real store runs on, the control paths nobody guesses, Text vs RichText, the four failures that look like broken JavaScript, and the three levels a WooCommerce store is styled from. Call it right after `oxygen/get-instructions`. |
| `bdox/design-map` | Every control an element exposes, one line each, instead of the 5–15k tokens `get-element-schemas` returns for one element. Typography and text-effects groups collapse to a single line (`expand: true` opens them), which is what makes a WooCommerce element readable at all: `Wooproductprice` goes from ~70 lines to 8. |
| `bdox/woo-map` | Atlas of the store elements — what each one renders, when to reach for it, and its design groups read live from the schema. Registers only when Breakdance WooCommerce for Oxygen is active. Complements `oxygen/get-ecommerce-instructions`, which covers building a store; this one covers styling it. |
| `updraft/backup-now` + `updraft/list-backups` | A restore point before the work starts, and an honest answer to whether it exists. `backup-now` returns when the job is QUEUED, not when the backup exists — it schedules the UpdraftPlus hook through WP-Cron, because firing it synchronously blew past the 120-second MCP timeout mid-backup on a store with media. Poll `list-backups` with `newer_than` until an entry appears. Registers only where UpdraftPlus is active. |
| `bdox/store-toolbox` | Which plugins a store build needs, what each is for, which are already active here, and the two that must not be switched on while an agent works: a security plugin whose firewall blocks the REST API, and a page cache that serves the previous version of every page. |
| `bdox/element-visibility` | The three bans, reported separately: what Oxygen hardcodes, what a human hid in the Elements panel, what this pack restored. The answer to "why is my element in `missing`" — and the warning that the Elements panel is not editor-only. |
| `bdox/element-defaults` | Live `defaultCss()` per element, parsed into declarations with specificity, plus the measured must-override list: what breaks and the fix that survives an update. |
| `bdox/setup-report` | Versions, breakpoints, which global-settings groups exist, compiled CSS file sizes, and a stale-cache verdict from comparing plugin source against the compiled cache. |
| `bdox/regenerate-css` | `regenerate_post_cache` **and** `generateCacheForPost` in the required order, plus global settings. Returns resulting file sizes so a no-op is visible. |
| `bdox/get-global-settings` | Reads the settings tree with no output schema in the way, because the built-in reader still fails on real data (wall 6). Optional `path` fetches one branch. |
| `bdox/patch-global-settings` | Writes the keys the built-in input schema rejects while the renderer reads them (wall 7). **No longer needed for partial updates** — the built-in tool deep-merges since beta.5. |
| `bdox/audit-compiled-css` | Compares stored selector properties against the compiled `selectors.css` and reports values that were saved but never emitted — including border colours on state selectors and the global-settings button fields the compiler accepts and ignores. Run it first whenever a value "does not work" although the editor shows it set. |
| `bdox/merge-css-selector` | Merges properties into an existing selector per breakpoint instead of replacing its map (wall 2), and writes `custom_css` verbatim — the only route that preserves a camelCase custom-property name, which the importer lowercases (`--myVar` is stored as `--myvar` while `var(--myVar)` keeps its case, so the reference dies silently and the import still reports success). |
| `bdox/builder-hidden-elements` | Reads/changes `oxygen_builder_hidden_elements` and reports what MCP can see as a result. Unhiding stores a non-empty placeholder: an empty list survives normal requests but `addDefaultHiddenElements()` re-fills it on the next Oxygen update. |
| `bdox/set-image` | Makes a media-library image render at a named size, with a `srcset`. Through `edit-post` it cannot: the element resolves `content.image.media.sizes[size].url`, but `media` is schema'd with `additionalProperties: false` and no `sizes`, so the lookup falls through to the 540×540 grey placeholder while the call reports success. Also handles `alt`, which the element reads from the attachment — not from the tree — and which is empty on most uploads. |
| `nerti/set-seo` + `nerti/get-seo` | Document title and meta description without an SEO plugin. Oxygen has no field for either, so a 1:1 transfer that matches element by element still ships `<title>site name</title>` and no description (measured 16.09.2026, eight pages). Stored in post meta, printed through `pre_get_document_title` and `wp_head`; when Yoast, Rank Math, SEOPress or AIOSEO is active the pack stays silent and only stores. Empty string clears. Read the front-end HTML after writing. |

## Install

**One plugin, one install, on every project.** Each ability group registers only where its plugin is active: the WooCommerce map only on a store, the backup abilities only where UpdraftPlus is installed. A site with neither gets the Oxygen half and nothing breaks.

Install order on a new build:

1. `agent-connector-for-wp` — the host. Owns authentication, permissions, the audit trail, and the PHP sandbox. Without it this plugin registers nothing at all (by design: no ability here carries its own `permission_callback`, because a second gate is a second thing that can drift out of step with the first).
2. `oxygen` + `breakdance-elements-for-oxygen` — the builder and the elements with real Design tabs.
3. `breakdance-woocommerce-for-oxygen` + `woocommerce` — only on a store.
4. `updraftplus` — before the first writing change on a site you did not build.
5. **this plugin.**

### Handover: what comes off before the client gets the site

The agent half is scaffolding, not product. At handover, in this order:

1. Finish building. **Then** hide elements from the client (`bdox/builder-hidden-elements`) — not before: the same option hides them from the agent too.
2. `bdox/regenerate-css` one last time.
3. Turn page caching on — not earlier, or every change you made would have read as a change that failed.
4. Deactivate and delete: this plugin, `agent-connector-for-wp`, and any dummy-content plugin.
5. `nerti-cookies` stays. It is the client's, not the agent's.


Download the `.zip` from the [latest release](https://github.com/NertiPL/nerti-abilities-for-oxygen/releases/latest) and upload it under **Plugins → Add New → Upload Plugin**. Requires `agent-connector-for-wp` and `oxygen`.

An agent cannot install this for you: Agent Connector refuses to write `.php` outside its own sandbox, which is the correct behaviour and the one manual step in the whole workflow.

**Updates.** From 0.6.0 onward new releases show up under **Plugins** like any other update, and the one-click update works. The plugin declares an `Update URI` and answers the `update_plugins_github.com` hook WordPress added in 5.8 for self-hosted plugins; it reads the GitHub releases API directly and never touches the shared update transient. A successful lookup is not cached — WordPress already caches the answer and decides when to ask again — so a release you publish now is offered on the next check WordPress makes, not up to twelve hours later. Only a *failed* lookup is cached, for 15 minutes, because that is the case that is also slow.

Verify with `bdox/element-visibility`: on Oxygen 6.2.0-beta.6 + BEFO 1.1.0.beta.4 + BWFO 1.1.0.beta.1 (measured 2026-08-23) `registered_count` is 188 and `mcp_visible_count` is 165 — 155 without the pack. `restored_by_this_pack` still lists 13 entries; 10 of them are doing work on this version, Section, Heading and Icon having dropped off Oxygen's hidden constant on their own.

**Building a release ZIP.** Build it from the tag, never by zipping the working folder — the 0.6.1 asset was built that way and shipped `.git/` (history, remote, config) inside the plugin, 140 entries where 12 were the plugin:

```
git archive --format=zip --prefix=unofficial-abilities-for-breakdance-elements/ -o unofficial-abilities-for-breakdance-elements-<version>.zip v<version>
```

The `--prefix` is what keeps the folder name — and therefore the plugin slug — stable across updates.

## Notes for the Agent Connector / Oxygen maintainers

See [UPSTREAM.md](UPSTREAM.md). Short version, 2026-08-21: `!important`, the deep merge and the empty-settings read are **fixed** — thank you. Still open: the `corner_radius` shape mismatch that makes `get-global-settings` refuse real data, the `additionalProperties: false` on button typography that blocks `color_hover`, the replace-only selector importer, the lowercased custom-property names, the missing cache-regeneration tool, `text-decoration-line: Array` in `typography.css.twig`, and the new one — the Elements settings page and the MCP element list share a filter, so a site setting silently controls what an agent can address.

## License

GPL-2.0-or-later.
