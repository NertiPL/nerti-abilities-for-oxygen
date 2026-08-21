# Changelog

Versioning is [semver](https://semver.org/). While the pack is `0.x`, a minor bump may change an ability's output shape; patch bumps never do.

## 0.8.1 — 2026-08-21

### Added

- **`bdox/setup-report` now reports typography as the browser resolves it**, read from the compiled `global-settings.css` instead of the stored settings tree, with a verdict. Two things fail silently when written and neither shows a symptom in the builder:
  - A Google Font slug is `strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $family))` (`plugin/fonts/integrations/google-fonts/google-fonts.php:110`) — every non-alphanumeric character is **dropped, not hyphenated**. `gfont-bricolage-grotesque` is therefore accepted, stored, and emitted verbatim as the `font-family`, so the font never loads and every heading falls back to the generic. Measured 2026-08-21 on 6.2.0-beta.5: `--bde-heading-font-family:gfont-bricolage-grotesque` in the compiled file while `set-global-settings` reported success. The correct slug is `gfont-bricolagegrotesque`. A single-word family such as `gfont-inter` resolves fine, which is exactly why the mistake survives — it only bites on multi-word names.
  - `typography.base_size` takes effect only in the flat `{number, unit, style}` shape; the breakpoint map the built-in schema demands validates and does nothing, because the twig reads `.style`.

  The verdict says `unresolved_font_slug in heading_font` (naming which keys) or `ok`. `testy/sprawdz-typografie.php` covers both branches plus later-declaration-wins and the missing-file case — `wp eval-file`, six assertions, no framework.

### Changed

- **`bdox/patch-global-settings` description names `typography.base_size`** as the second confirmed key the built-in tool cannot write usefully, and warns about the follow-on: once `base_size` is stored flat, later `oxygen/set-global-settings` calls fail their **output** validation (`base_size[number] is not of type object,null`) although the write itself went through. Read state back with `bdox/get-global-settings`. Same root cause as `buttons.primary.corner_radius` — the builder stores flat, the MCP schema demands a breakpoint map.

## 0.8.0 — 2026-08-21

Nothing added, nothing removed. Every ability description, the plugin header, the README and UPSTREAM.md were re-measured against **Oxygen 6.2.0-beta.5 + Breakdance Elements for Oxygen 1.1.0.beta.1, WordPress 7.1, PHP 8.2.29** and corrected. Three of the pack's stated reasons for existing turned out to be fixed upstream, one was flatly wrong, and one new coupling was found.

### Fixed

- **The pack claimed `oxygen_builder_hidden_elements` does not affect MCP. It does.** The plugin header, the README and `bdox/element-visibility`'s description all said the option controls the editor panel only. `admin/settings-page/tabs/elements.php` hangs `filter_builder_elements()` on the `breakdance_builder_elements` filter at priority 10, and `mcp/schemas/elements.php` runs the MCP element list through that same filter. Measured: adding `EssentialElements\Button` to the option took `oxygen-get-element-slugs` from 164 to 163 and made `get-element-schemas` refuse it; removing it restored 164. Rule that follows, now stated in three places: **build first, hide last**.
- **`bdox/builder-hidden-elements` reported a number that never moves.** It returned `builder_panel` from `count(get_elements_for_builder())`, which maps over every registered classname and only feeds the filtered list to `addPanelRules` — measured 165 both before and after hiding an element. Replaced with `mcp_visible_count` and `hidden_from_mcp`, so the ability now reports both sources of truth: the stored option and what MCP can actually address.
- **The "empty list is repopulated on the next request" claim was wrong.** Measured: an empty `builder_hidden_elements` persists across requests. `addDefaultHiddenElements()` is called from `handleVersionChange()` and the setup wizard, so the re-fill happens on the next *Oxygen update*, not the next request. The non-empty placeholder is still correct — the reason changed, not the behaviour.
- **The element counts in the header and README were from beta.2** (162 / 149). Re-measured: 186 registered, 151 MCP-visible without the pack, 164 with it. `BREAKDANCE_MCP_HIDDEN_ELEMENTS` now holds 14 entries and no longer bans `Section`, `Heading` or `Icon` — those three are hidden purely by the WP option.

### Changed

- **`bdox/merge-css-selector` no longer claims to be the only route to `!important`.** Oxygen fixed that in 6.2.0: `css-to-properties.php` diverts every `!important` declaration into `custom_css` with the flag intact, before the typed converters run. Measured on beta.5 — `.probe{color:#ff0000 !important;padding:8px}` stored padding as a typed property and `custom_css` as `:selector {\n  color: #f00 !important;\n}`. The ability stays for the two things still broken: the importer replaces a selector's whole property map per breakpoint (measured — a `margin`-only import wiped both `padding` and `custom_css`), and it lowercases custom-property **names** (`--myVar` stored as `--myvar` while `var(--myVar)` keeps its case, so the reference dies and the import reports success).
- **`bdox/patch-global-settings` lost its main reason and kept a smaller one.** `set-global-settings` deep-merges since beta.5 (`breakdance_mcp_deep_merge_settings`) — measured, a `typography.base_font_size`-only call left `colors.palette` and `typography.body_font` intact. What it still cannot do is write a key on an object closed with `additionalProperties: false`: `buttons.primary.background_hover` goes through, `buttons.primary.typography.color_hover` is rejected — and `elements/macros/atom-v1-button-css.twig` reads exactly that key. The description now says so instead of advertising the merge.
- **`bdox/get-global-settings` stays, with the real failure.** Soflyy fixed the empty-settings case (`{"settings":{}}` instead of an error), so on a blank site the built-in reader works. On a real one it still returns nothing: `output[settings][buttons][primary][corner_radius][unit] is not of type object,null`. Observed on a production site and reproduced on the beta.5 lab install with the same stored value. The output schema wants `corner_radius` breakpoint-keyed; the builder stores it flat as `{number, unit, style}` — and stores it breakpoint-keyed under `button_presets[].styles`, so the same control is persisted two different ways.
- **`bdox/regenerate-css` narrowed its claim.** Still no cache-regeneration tool among the 49 the beta.5 server exposes, but Oxygen's own save paths do regenerate (`data/save.php:121` calls `generateCacheForPost()`; `save_global_settings()` and `save_selectors()` call `generateCacheForGlobalSettings()`). The ability is for data changed outside those paths, and for a stale compiled cache.
- **`bdox/element-visibility` was rewritten to report three lists, not two counts.** `banned_by_oxygen` (the constant), `hidden_in_elements_panel` (the WP option), `restored_by_this_pack`, plus `still_hidden_from_mcp` and an explicit warning about the shared filter. It also stopped comparing element slugs against classnames, which made the old `hidden_from_mcp` meaningless.
- **The two `add_filter` calls stay, with the deletion condition written into the code.** They exist only because the fundamentals are supposed to replace these elements — and on BEFO 1.1.0.beta.1 the fundamentals do not exist: `class_exists()` is `false` for `FText`, `FImage`, `FRichText`, `FTextLink`, `FSvgIcon2`, `FHtmlCode` and `EssentialElements\ContainerLink`. The native `OxygenElements` fallbacks exist but have no Design tab — measured via `oxygen-get-element-schemas`: `EssentialElements\{Section,Heading,Text,Image2,Button}` expose `design`, `OxygenElements\{Container,Text,Image}` do not. The header now names the re-measure condition: after BEFO ≥ 1.1.0.beta.3, check `class_exists('EssentialElements\FText')` and whether its schema has a `design` section; only if both hold do the filters get deleted.
- **`bdox/set-image` re-measured, unchanged.** The `wpmedia` control still schemas `media` as `{id, url, alt}` with `additionalProperties: false`, and both `OxygenElements\Image` and `EssentialElements\Image2` still resolve `src` as `content.image.media.sizes[size].url`.
- **`typography.css.twig` lines 114 and 126 still lack `| join(' ')`**, so `text-decoration-line` still compiles to the literal `Array`. Recorded in UPSTREAM.md as still open.

### The cost of the restore is now written down

Previously unstated: restoring on `breakdance_builder_elements` also unhides those 13 elements in the builder's **Add panel**, because `get_elements_for_builder()` hands the same filtered array to `FilteredGets\addPanelRules()`, which sets `alwaysHide` on whatever is missing from it. Measured — pack active: `Section`, `Div`, `Heading` report no `alwaysHide`; pack filter removed: all three do. Two consequences, now documented in the plugin header, the README and `bdox/builder-hidden-elements`:

- While the pack is active the client sees those elements too, which removes exactly the simplification Oxygen was going for.
- A human who hides one of the 13 in Oxygen → Settings → Elements is silently overruled: the pack restores at priority 20, after the settings page subtracts at priority 10.

Restoring "for MCP only" was considered and rejected. The panel and MCP go through different functions but share one filter, and the filter carries no caller context, so the only discriminator is the admin-ajax action name — a string Oxygen may rename, after which the guard silently stops matching. Instead, the existing `bdox_pack_restored_elements` filter is documented as the lever: `add_filter( 'bdox_pack_restored_elements', '__return_empty_array' )` at handover. **Handover order, which is not the build order: build → hide → narrow (or deactivate).** Hiding while the pack still restores does nothing.

## 0.7.0 — 2026-07-27

### Added

- **`bdox/set-image`** — point an image element at a media-library attachment so it actually renders, at a named size and with a `srcset`. The built-in path cannot do this. Measured on Oxygen 6.2.0-beta.2: an `OxygenElements\Image` with `from: media_library` and `size: "large"` renders the builder's 540×540 grey placeholder while `edit-post` reports success and reads the data back exactly as written. The element resolves its `src` as `content.image.media.sizes[size].url`, but the MCP schema declares `media` with only `id`, `url`, `alt` and `additionalProperties: false`, so `sizes` can never be written and the lookup falls through to `|default()`. `attributes.srcset` is unreachable the same way, so an API-created image emits no `srcset` at all. This ability fills both maps from WordPress's own attachment metadata, writes them into the tree and recompiles the post CSS.

  Measured end to end on a probe page: before, `src="data:image/svg+xml…"` and one placeholder on the page; after, `src="…-1024x640.png"` with a five-candidate `srcset` and zero placeholders.

  Passing a size the attachment does not have is the one remaining way to get a placeholder, so unknown sizes are rejected with the list of real ones rather than written blindly.

  **`alt` needed its own handling.** The element does *not* render `media.alt` from the tree — its template reads `get_attachment_alt(content.image.media.id)` in `from_media_library` mode, and `content.image.custom_alt` in `custom` mode. An attachment with no `_wp_attachment_image_alt` therefore renders an `<img>` with **no `alt` attribute at all**, which is what the first version of this ability produced. Passing `alt` now switches the element to custom mode, and the response's `alt_mode` says outright when the attachment's own alt is empty.

  Upstream report: [soflyy/agent-connector-for-wp#79](https://github.com/soflyy/agent-connector-for-wp/issues/79).

### Changed

- Two new helpers, `bdox_pack_get_tree()` / `bdox_pack_save_tree()`, plus `bdox_pack_map_node()`. The tree is not `_oxygen_data` — that meta is an object whose single key `tree_json_string` holds the tree as an **encoded string**, so decoding one level and subscripting `['root']` yields null. `bdox_pack_map_node()` is deliberately copy-based rather than a reference finder: a `function &find` helper makes every write land through an alias, and a caller that inserts into the returned handle duplicates whole subtrees — measured on a production site where seventeen pages grew a second copy of a section.

## 0.6.4 — 2026-07-27

### Changed

- **The 12-hour cache on a successful release lookup is gone.** It was a second cache stacked on top of WordPress's own: core already stores the answer in the `update_plugins` transient and only calls this code when *it* decides to refresh — at most hourly on the plugins screen, once a minute on the updates screen. Ours bought nothing on top of that and cost the only thing that matters here. Measured: 0.6.3 was published 14 minutes after a check had run, and the site kept answering `no_update: 0.6.2` from a transient holding a stale release for another 12 hours, on an install that had already asked. A release now shows up on the next check WordPress makes.
- A *failed* lookup is still cached, for 15 minutes. Failure is the case that is also slow — unreachable repo, spent rate limit, DNS — and without the marker every refresh would block a page load on the same timeout. `force-check` ignores the marker, which is the button someone reaches for in exactly that situation.

## 0.6.3 — 2026-07-27

### Fixed

- **"View details" opened on an error.** The details thickbox — and the *View version x.y.z details* link that sits right next to the update button — goes to the wordpress.org API, which has never heard of this plugin, so it answered `An unexpected error occurred`. That is the worst possible moment for it: the reader is deciding whether to trust the update. The pack now answers `plugins_api` for its own slug with the plugin header data and the release notes from the payload it already cached, so the popup costs no extra request. The notes go through a deliberately tiny Markdown pass (headings, bold, code, list items) applied *after* escaping, so anything unexpected from the API shows up as text instead of markup.
- **`upgrader_source_selection` matched by substring.** The filter runs for every plugin and theme upgrade on the site, and `str_contains( $plugin, 'unofficial-abilities-for-breakdance-elements' )` would also claim an upgrade of any plugin whose slug contains this one's. It now compares `plugin_basename()` exactly, against the new `BDOX_PACK_FILE` constant.

## 0.6.2 — 2026-07-27

### Fixed

- **The updater was invisible outside wp-admin and cron.** `src/updates.php` loaded behind `is_admin() || DOING_CRON`, so any other context that runs `wp_update_plugins()` — WP-CLI (`wp plugin list`, `wp plugin update`), a host-side updater, a REST-driven flow — refreshed the update transient with `update_plugins_github.com` unregistered. The plugin then fell out of *both* buckets and WordPress reported that auto-updates were unavailable for it. Measured on a live install: in a REST request `has_filter('update_plugins_github.com')` returned false while the same request could reach the GitHub API fine. The file now always loads; it only adds filters.
- **A version bump left the plugins screen stale for up to an hour.** The update runs inside the *old* version's process, so the `update_plugins` transient it writes comes from the code being replaced, and WordPress then throttles the next check. Measured on 0.6.0 → 0.6.1: with the fixed version installed, the plugin was in neither `response` nor `no_update`, so the screen still claimed auto-updates were unavailable. The pack now stores its own installed version and forces exactly one fresh check when that value changes.

### Changed

- **Release ZIPs are built with `git archive` from the tag.** The 0.6.1 asset was built by zipping the working folder and shipped `.git/` — history, remote and config — inside the plugin: 140 entries of which 12 were the plugin. The command is in the README.

## 0.6.1 — 2026-07-27

### Fixed

- **"Check again" ignored the cache.** The 12-hour cache meant a freshly published release stayed invisible for up to half a day and the button looked broken. `force-check` now bypasses the cache, which is also what makes a release testable the moment it goes out.
- **The plugins screen showed no "Enable auto-updates" control.** `update_plugins_github.com` returned `false` when the installed version was already current, which keeps the plugin out of *both* the `response` and the `no_update` bucket — and WordPress only offers the auto-update toggle for plugins it has in one of them. The result read as "updates are not wired up" on a site where they were. The filter now always returns the payload and lets WordPress decide which bucket it belongs in. Visible check: with this version installed, the plugin row on **Plugins** gains an **Enable auto-updates** link, which also proves the whole chain end to end — header read, filter fired, GitHub reached, release parsed.

## 0.6.0 — 2026-07-27

### Added

- **Updates now appear in the WordPress plugins list.** The plugin declares an `Update URI` on github.com and answers the `update_plugins_github.com` hook that WordPress 5.8 added for exactly this case. No update-checker library, no cron job, no settings screen, and nothing filtering the global `site_transient_update_plugins` — the usual way a self-hosted updater breaks unrelated plugins. Releases are read from the GitHub API and cached for 12 hours; a failed lookup is cached for 15 minutes so an unreachable repo does not mean an API call on every admin page load. `upgrader_source_selection` normalises the extracted folder name, without which WordPress installs the update as a separate plugin and deactivates the one being updated. The updater file only loads in admin and cron.

### Fixed

- **`bdox/element-defaults` told you to set `--defaultTransition`, which cannot work.** Measured 2026-07-27 on Oxygen 6.2.0-beta.2: `insert-stylesheet` lowercases custom property *names*, so `--defaultTransition` is stored as `--defaulttransition` and every `var(--defaultTransition)` reference resolves to nothing. The import returns `success: true` with no warning, which is what made the advice expensive. The `.bde-container-link` entry now points at the transition shorthand instead, and names the one route that does preserve case.

### Changed

- **`bdox/merge-css-selector` now documents that it is the only way to get `!important` into a selector.** `insert-stylesheet` strips `!important` from every declaration *before* it routes them. Measured with `will-change: transform !important` — an untyped property that falls through to `custom_css` untouched by any typed handler and still loses the `!important`, which places the strip before routing rather than inside it. The compiled `selectors.css` of an entire production site contained zero occurrences. Writing the `custom_css` slot through this ability bypasses that parser and both `!important` and capitalised custom property names survive into the compiled CSS. The description also records that a `custom_css` block compiles to a second rule carrying the same selector, emitted after the typed one, so at equal specificity it wins on order too.

## 0.5.0 — 2026-07-26

### Added

- **`bdox/get-global-settings`** — reads the settings tree straight from storage with no output schema in the way. The built-in `oxygen/get-global-settings` validates its own result and refuses perfectly valid data: a gradient button background returns `output[settings][buttons][primary][background] is not of type string` and you get nothing, so you cannot inspect what you are about to patch. Measured again this session on a fresh install. Optional `path` argument (dot notation, e.g. `colors.palette.colors`, `typography.global_typography.typography_presets`) returns one branch instead of the whole tree. Until now the only way through was `php-eval`, which is a sledgehammer for a read.

## 0.4.0 — 2026-07-26

### Fixed

- **`Section`, `Heading` and `Icon` were still invisible to MCP.** Measured on a fresh Local install with 0.3.0 active: `bdox/element-visibility` returned `builder_count: 162`, `mcp_visible_count: 159`, `hidden_from_mcp: ["EssentialElements\\Icon", "EssentialElements\\Section", "EssentialElements\\Heading"]`. The restore list covered seven Essential Elements and the three code blocks but skipped these three. `Section` is the expensive one: it is the only element rendering `.section-container`, the wrapper that consumes `containers.sections.container_width`, so an agent that cannot address it builds every layout on `Div` and the global container settings are dead. All three are now restored; `hidden_from_mcp` comes back empty.

### Changed

- `Plugin URI` and `Author URI` now point at https://github.com/NertiPL, so the author is a link in the plugins list and on the plugin detail screen instead of plain text.

## 0.3.0 — 2026-07-26

First version verified against a live install rather than reasoned about. Every fix below came from running the abilities and building a real page with them, not from review.

### Fixed

- **`bdox/setup-report` reported a healthy site as `STALE`.** The stale-cache gate counted every occurrence of `.breakdance ` in the compiled CSS, including compound selectors such as `.breakdance .bde-header-builder--sticky .bde-header-builder__container` that are deliberate and always present. It now counts only the blanket prefix on a bare element class (`.breakdance .bde-thing {`), which is the actual regression signature, and it measures both plugin sources instead of one. A false stale verdict is worse than no check: it points at a cache rebuild when the real problem is elsewhere.
- **`bdox_pack_builder_post_ids()` returned revisions and trashed posts.** Builder data is copied onto every revision, so on an eight-page site the helper returned 70 IDs of which 58 were revisions and 2 were trashed. `bdox/regenerate-css` with no `post_ids` would write `post-<revision-id>.css` files that nothing loads. Now excludes revisions, trash and auto-drafts, with an `include_all` escape hatch.
- **`bdox/element-defaults` claimed `.bde-text-link > *` is `(0,1,1)` and must be worked around.** The computed specificity in the same ability's own output proved otherwise: the universal selector contributes nothing, so it is `(0,1,0)` and ties with a flat class, which then wins on load order. Downgraded from must-override to informational and the explanation corrected.

### Added

- `design.text_colors.link` on a Div is now documented in the link-colour rule as the per-section fix. It emits `<div-selector> a { color: … }` into the per-element stylesheet and is a Design-tab control, so the client can change link colour per section without touching CSS.
- Two schema traps found while building the test page: `RichText` splits typography per role (`design.typography.default.color`, `.h2.color`, `.link.color`) and rejects the flat `design.typography.color` that `Text` and `Heading` accept; `Text` exposes no tag control at all, even though trees written directly carry a `tags` key that does render.

### Changed

- Author is now Nerti.

## 0.2.0 — 2026-07-26

### Added

- `bdox/element-defaults` — live `defaultCss()` per element parsed into declarations with computed specificity, plus the measured must-override list.
- `bdox/setup-report` — versions, breakpoints, which global-settings groups exist, compiled CSS sizes, stale-cache verdict.
- `bdox/builder-hidden-elements` — read or change the editor's hidden-element list, with the guard that an empty list silently repopulates with the defaults.

## 0.1.0 — 2026-07-26

Initial release.

- Restores the elements `BREAKDANCE_MCP_HIDDEN_ELEMENTS` hides from MCP (`Div`, `Text`, `RichText`, `TextLink`, `WrapperLink`, `Image2`, code blocks) through the `oxygen_builder_elements` filter, so `get-element-schemas` and `edit-post` can address them.
- `bdox/element-visibility`, `bdox/regenerate-css`, `bdox/patch-global-settings`, `bdox/merge-css-selector`.
