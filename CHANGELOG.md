# Changelog

Versioning is [semver](https://semver.org/). While the pack is `0.x`, a minor bump may change an ability's output shape; patch bumps never do.

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
