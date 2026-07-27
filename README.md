# Unofficial Abilities for Breakdance Elements for Oxygen

MCP ability pack for **Oxygen 6.x + Breakdance Elements for Oxygen**, built on the WP Abilities API and Agent Connector for WP.

**[Download the latest release →](https://github.com/NertiPL/unofficial-abilities-for-breakdance-elements/releases/latest)**

Install the `.zip` from the release page, not the green *Code → Download ZIP* button: that one names the folder `…-main`, so WordPress installs it under a different slug and the next update lands as a second copy instead of replacing the first.

It exists because an agent building client-editable pages in Oxygen keeps hitting the same four walls. Every claim below was measured on a live install (Oxygen 6.2.0-beta.2, Breakdance Elements for Oxygen 1.1.0.beta.1, WordPress 7.0.2, PHP 8.3), not taken from documentation.

## The four walls

**1. MCP cannot see the elements every page is made of.**

```php
count( \Breakdance\Elements\get_elements_for_builder() )  // 162
count( breakdance_mcp_visible_element_classnames() )      // 149
```

The difference is the hardcoded `BREAKDANCE_MCP_HIDDEN_ELEMENTS` constant, and it contains `Section`, `Div`, `Heading`, `Text`, `RichText`, `TextLink`, `WrapperLink`, `Icon`, `Image2` and the code blocks. So `get-element-schemas` returns them as `missing` and `edit-post` rejects them — an agent has to hand-write the element tree through raw PHP instead of using the tools. This is independent of the builder's own `oxygen_builder_hidden_elements` option; unhiding elements in the editor does not change what MCP sees.

`Section` is the expensive one. It is the only element that renders `.section-container`, the wrapper that consumes `--bde-section-width` and the global section padding. An agent that cannot address it builds every layout on `Div`, the global container settings never apply, and each page ends up carrying its own width in a class — the exact outcome the global settings exist to prevent.

The visibility list ends in `bdox_run_filters('breakdance_builder_elements', …)`, which is a plain `apply_filters` run twice, so the pack restores them with two `add_filter` calls at load time. Nothing is patched.

**2. Writes replace instead of merging.** `set-global-settings` persists exactly what it receives, so sending only `colors` wipes global stylesheets, scripts and typography presets. `insert-stylesheet` replaces a selector's whole property map, so changing one property drops every other property and breakpoint that selector had.

**3. There is no way to recompile CSS.** After editing trees or settings the compiled files must be rebuilt, and the compiled cache can legitimately predate a plugin update — which renders the old default CSS and looks exactly like a specificity bug. Diagnosing that from the compiled file alone leads to patching the plugin for a problem that a cache rebuild fixes.

**4. Element defaults are undocumented and some of them outrank your CSS.** `.bde-section .section-container` is `(0,2,0)` and `.bde-text-link > *` is `(0,1,1)`; both beat a flat project class `(0,1,0)` no matter what order the files load in. Without knowing that, an agent writes CSS that silently does nothing.

## Abilities

| Ability | What it does |
|---|---|
| `bdox/element-visibility` | Builder element count vs MCP-visible count, what is hidden, what this pack restored. The answer to "why is my element in `missing`". |
| `bdox/element-defaults` | Live `defaultCss()` per element, parsed into declarations with specificity, plus the measured must-override list: what breaks and the fix that survives an update. |
| `bdox/setup-report` | Versions, breakpoints, which global-settings groups exist, compiled CSS file sizes, and a stale-cache verdict from comparing plugin source against the compiled cache. |
| `bdox/regenerate-css` | `regenerate_post_cache` **and** `generateCacheForPost` in the required order, plus global settings. Returns resulting file sizes so a no-op is visible. |
| `bdox/get-global-settings` | Reads the settings tree with no output schema in the way. The built-in `oxygen/get-global-settings` validates its own result and fails on valid data — a gradient button background returns `output[settings][buttons][primary][background] is not of type string` and you get nothing back. Optional `path` fetches one branch. |
| `bdox/patch-global-settings` | Deep-merges a partial settings tree. Also bypasses the built-in input schema, which omits `background_hover`, `color_hover` and `typography.color_hover` even though the renderer reads them. |
| `bdox/merge-css-selector` | Merges properties into an existing selector per breakpoint instead of replacing its map. |
| `bdox/builder-hidden-elements` | Reads/changes which elements the editor panel offers. Saving an empty list does nothing lasting — the builder repopulates the defaults whenever the option is empty — so unhiding stores a non-empty placeholder. |
| `bdox/set-image` | Makes a media-library image render at a named size, with a `srcset`. Through `edit-post` it cannot: the element resolves `content.image.media.sizes[size].url`, but `media` is schema'd with `additionalProperties: false` and no `sizes`, so the lookup falls through to the 540×540 grey placeholder while the call reports success. Also handles `alt`, which the element reads from the attachment — not from the tree — and which is empty on most uploads. |

## Install

Download the `.zip` from the [latest release](https://github.com/NertiPL/unofficial-abilities-for-breakdance-elements/releases/latest) and upload it under **Plugins → Add New → Upload Plugin**. Requires `agent-connector-for-wp` and `oxygen`.

An agent cannot install this for you: Agent Connector refuses to write `.php` outside its own sandbox, which is the correct behaviour and the one manual step in the whole workflow.

**Updates.** From 0.6.0 onward new releases show up under **Plugins** like any other update, and the one-click update works. The plugin declares an `Update URI` and answers the `update_plugins_github.com` hook WordPress added in 5.8 for self-hosted plugins; it reads the GitHub releases API directly and never touches the shared update transient. A successful lookup is not cached — WordPress already caches the answer and decides when to ask again — so a release you publish now is offered on the next check WordPress makes, not up to twelve hours later. Only a *failed* lookup is cached, for 15 minutes, because that is the case that is also slow. Updating *to* 0.6.0 is still a manual ZIP upload, because the version you have installed does not yet know where to look.

Verify with `bdox/element-visibility`: `mcp_visible_count` should rise by the number of restored elements, and `hidden_from_mcp` should be empty apart from `MissingElement`.

**Building a release ZIP.** Build it from the tag, never by zipping the working folder — the 0.6.1 asset was built that way and shipped `.git/` (history, remote, config) inside the plugin, 140 entries where 12 were the plugin:

```
git archive --format=zip --prefix=unofficial-abilities-for-breakdance-elements/ -o unofficial-abilities-for-breakdance-elements-<version>.zip v<version>
```

The `--prefix` is what keeps the folder name — and therefore the plugin slug — stable across updates.

## Notes for the Agent Connector / Oxygen maintainers

Four of the eight abilities exist only to work around gaps that would be better closed upstream, and this pack should shrink if they are:

- `BREAKDANCE_MCP_HIDDEN_ELEMENTS` hides the elements agents need most, `Section` above all. If the intent is to steer agents toward `html-to-page` for static layout, that is a documentation concern, not a reason to make the elements unaddressable — `edit-post` is still the only way to do surgical edits, and it cannot touch a `Div`. Worse, `html-to-page` builds `OxygenElements\Container`, which never renders `.section-container`, so the recommended path and the global container settings are mutually exclusive today.
- `set-global-settings` and `insert-stylesheet` being replace-only is a sharp edge with no merge counterpart. A `patch` variant of each would remove a whole class of regressions.
- `get-global-settings` fails its own output validation on data the builder itself wrote. A settings reader should never be able to refuse the settings.
- There is no public cache-regeneration ability, yet nearly every direct data edit requires one.

Two renderer-side observations from the same session, offered as bug reports rather than as things a pack can fix:

- `insert-stylesheet` **drops `!important`** from every imported rule, for `class` and `custom` selectors alike. Since `selectors.css` loads before `post-<id>.css`, a flat class can never win against a Design-tab value, and the documented escape hatch does not survive the import.
- `typography.css.twig` prints `text-decoration-line: {{ …decoration.line }}` without a `join` filter, so an array value compiles to the literal `text-decoration-line:Array`. The element-level macro `customTypographyValuesAsCssVariables` does apply `| join(' ')`, so the same value shape is correct in one place and broken in the other.

The element-default annotations in `abilities/defaults.php` are the part most worth upstreaming as documentation; they are the questions an agent asks on every build.

## License

GPL-2.0-or-later.
