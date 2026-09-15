<?php
/**
 * Plugin Name:       Nerti Abilities for Oxygen (Elements, WooCommerce, Backups)
 * Plugin URI:        https://github.com/NertiPL/nerti-abilities-for-oxygen
 * Description:        MCP ability pack for Oxygen 6.x + Breakdance Elements, and on a store also Breakdance WooCommerce. Unhides the core elements the built-in Oxygen MCP tools cannot see (Div, Text, RichText, TextLink, WrapperLink, Image2, code blocks), and adds what the built-in tools lack: the house rules and the Design-tab map that keep a build editable by the site owner, a WooCommerce element atlas, a store plugin toolbox, a restore point before the work starts, CSS cache regeneration, partial global-settings patching, non-destructive selector merging, editor element visibility, a setup/stale-cache report, and a per-element report of the default CSS you must override for a design to survive.
 * Version:           0.16.0
 * Requires Plugins:  agent-connector-for-wp, oxygen
 *
 * One plugin, one install, on every project. Each ability group registers only where
 * its plugin is active: the WooCommerce map only on a store, the backup abilities only
 * where UpdraftPlus is installed. A site with neither simply gets the Oxygen half.
 * Requires PHP:      8.1
 * Author:            Nerti
 * Author URI:        https://github.com/NertiPL
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/NertiPL/nerti-abilities-for-oxygen
 *
 * Agent Connector: Ability Pack
 * Agent Connector Target: breakdance-elements-for-oxygen/plugin.php
 *
 * You write NO permission / auth / domain-lock / audit code. Agent Connector
 * injects all of it for every ability registered here.
 *
 * WHY THIS PACK EXISTS
 * --------------------
 * Re-measured 2026-08-23 on Oxygen 6.2.0-beta.6 + Breakdance Elements for Oxygen
 * 1.1.0.beta.4 + Breakdance WooCommerce for Oxygen 1.1.0.beta.1, WordPress 7.1,
 * PHP 8.1.34:
 *
 *   count( \Breakdance\Elements\get_element_classnames() )     => 188
 *   count( breakdance_mcp_visible_element_classnames() )       => 155  (pack filter removed)
 *   count( breakdance_mcp_visible_element_classnames() )       => 165  (pack active)
 *
 * The 10 it restores on this version are Div, Text, RichText, TextLink,
 * WrapperLink, Image2, CodeBlock, CssCode, HtmlCode, PhpCode — the elements every
 * page is built from. Without them get-element-schemas returns them as `missing`
 * and edit-post rejects the element_type. Section, Heading and Icon stay on the
 * restore list but no longer need it: they are visible on their own here, and the
 * filter skips whatever is already in the list.
 *
 * TWO INDEPENDENT BANS PRODUCE THAT GAP, AND THEY OVERLAP:
 *
 *   a) BREAKDANCE_MCP_HIDDEN_ELEMENTS in mcp/schemas/elements.php — 14 entries
 *      on this version: CssCode, HtmlCode, PhpCode, CodeBlock, FHtmlCode, Div,
 *      WrapperLink, Text, TextLink, Image, Image2, RichText, FSvgIcon, SvgIcon.
 *      Section, Heading and Icon are NO LONGER on it (they were on beta.2).
 *   b) the `oxygen_builder_hidden_elements` WP option, written by
 *      \Breakdance\BreakdanceOxygen\addDefaultHiddenElements() — 10 entries
 *      on a fresh install. On the reference store (2026-08-23) it holds exactly
 *      one, EssentialElements\MissingElement, so there the whole gap comes from
 *      (a). Read the live value with bdox/element-visibility instead of assuming
 *      either shape.
 *
 * (b) IS NOT INDEPENDENT OF MCP — earlier versions of this comment said it was,
 * and that was wrong. admin/settings-page/tabs/elements.php hangs
 * filter_builder_elements() on `breakdance_builder_elements` at priority 10, and
 * mcp/schemas/elements.php runs the MCP list through the SAME filter. Measured:
 * adding EssentialElements\Button to the option dropped oxygen-get-element-slugs
 * by one and made get-element-schemas refuse it. Hiding an element in
 * Oxygen → Settings → Elements therefore hides it from the agent too.
 * Corollary for anyone building with an agent: BUILD FIRST, HIDE LAST.
 *
 * Section matters most: it is the only element that renders `.section-container`,
 * the wrapper that consumes the global content width, so without it every layout
 * an agent builds ignores the global container settings.
 *
 * The visibility list ends in bdox_run_filters( 'breakdance_builder_elements' ),
 * which is a plain apply_filters run twice (breakdance_* and oxygen_*), so the
 * elements can be restored from outside the plugin. That is what this pack does
 * first, before registering anything else, at priority 20 — after the settings
 * page's priority-10 subtraction, which is what makes the restore win.
 *
 * THE PRICE: THIS ALSO UNHIDES THEM IN THE BUILDER'S ADD PANEL.
 * The panel reads the same list — get_elements_for_builder() hands the filtered
 * array to FilteredGets\addPanelRules(), which sets 'alwaysHide' on whatever is
 * missing from it. Measured 2026-08-21: with the pack active, Section, Div and
 * Heading report no alwaysHide; with this filter removed, all three do. So the
 * client sees the 13 too, and a human who hides one of them in the Elements
 * settings screen is silently overruled by the priority-20 restore.
 *
 * We do not try to restore "for MCP only". The two consumers are different
 * functions (get_elements_for_builder for the panel,
 * breakdance_mcp_visible_element_classnames for MCP) but they share this one
 * filter and it carries no caller context, so the only discriminator available
 * is the admin-ajax action name — a string Oxygen may rename, after which the
 * guard silently stops matching. A visible documented cost beats an invisible
 * undocumented one.
 *
 * The lever is bdox_pack_restored_elements below. At handover:
 *
 *   add_filter( 'bdox_pack_restored_elements', '__return_empty_array' );
 *
 * Handover order, which is NOT the build order: build, then hide what the
 * client should not see, THEN narrow this list (or deactivate the pack).
 * Hiding while the pack still restores does nothing.
 *
 * WHEN TO DELETE THE TWO add_filter CALLS BELOW
 * ---------------------------------------------
 * Soflyy's stated intent is that the fundamentals replace these elements. On
 * BEFO 1.1.0.beta.1 the fundamentals DO NOT EXIST: class_exists() is false for
 * EssentialElements\FText, FImage, FRichText, FTextLink, FSvgIcon2, FHtmlCode
 * and EssentialElements\ContainerLink (re-measured 2026-08-23 on BEFO
 * 1.1.0.beta.4: still all false). The native
 * OxygenElements replacements do exist but carry NO design section — measured
 * via oxygen-get-element-schemas: EssentialElements\{Section,Heading,Text,
 * Image2,Button} all expose `design`, while OxygenElements\{Container,Text,
 * Image} expose only `content`/`settings`/`meta`. A client-editable build needs
 * the Design tab, so the filters stay.
 *
 * Re-measure condition, do not guess: after updating BEFO to >= 1.1.0.beta.3 run
 *
 *   wp eval 'var_dump(class_exists("EssentialElements\\FText"));'
 *
 * and, if true, oxygen-get-element-schemas on FText. Only when the fundamentals
 * exist AND their schema has a `design` section do these two filters become dead
 * weight and get deleted.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'BDOX_PACK_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDOX_PACK_FILE', __FILE__ );
define( 'BDOX_PACK_VERSION', '0.16.0' );

/**
 * The elements the built-in Oxygen MCP layer hides from agents.
 *
 * Kept as a filterable list rather than a constant so a site can narrow it.
 *
 * @return string[]
 */
function bdox_pack_restored_elements(): array {
	return (array) apply_filters(
		'bdox_pack_restored_elements',
		array(
			'EssentialElements\\Section',
			'EssentialElements\\Div',
			'EssentialElements\\Heading',
			'EssentialElements\\Icon',
			'EssentialElements\\Text',
			'EssentialElements\\RichText',
			'EssentialElements\\TextLink',
			'EssentialElements\\WrapperLink',
			'EssentialElements\\Image2',
			'EssentialElements\\CodeBlock',
			'OxygenElements\\CssCode',
			'OxygenElements\\HtmlCode',
			'OxygenElements\\PhpCode',
		)
	);
}

/**
 * Restore the hidden elements to the builder/MCP element list.
 *
 * Only adds elements that are actually registered and available in this mode,
 * so a narrower Oxygen build never gets a slug it cannot resolve.
 *
 * @param mixed $slugs Element classnames collected so far.
 * @return string[]
 */
function bdox_pack_restore_elements( $slugs ): array {
	$slugs = is_array( $slugs ) ? array_values( array_filter( $slugs, 'is_string' ) ) : array();

	foreach ( bdox_pack_restored_elements() as $class ) {
		if ( in_array( $class, $slugs, true ) ) {
			continue;
		}
		if ( ! class_exists( $class ) ) {
			continue;
		}
		$slugs[] = $class;
	}

	return array_values( array_unique( $slugs ) );
}

add_filter( 'breakdance_builder_elements', 'bdox_pack_restore_elements', 20 );
add_filter( 'oxygen_builder_elements', 'bdox_pack_restore_elements', 20 );

// The lightweight registry that collects categories + ability definitions and
// registers them on the correct Abilities API hooks. Shared with the other
// unofficial packs; guarded against double definition on its own side.
require_once BDOX_PACK_DIR . 'bootstrap.php';

require_once BDOX_PACK_DIR . 'src/helpers.php';

// Update checks against GitHub Releases. Loaded unconditionally: it only adds
// filters, and the previous is_admin()/DOING_CRON gate meant WP-CLI (`wp plugin
// list`, `wp plugin update`) and any host-side updater ran wp_update_plugins()
// with the filter unregistered — so the plugin fell out of both update buckets
// and WordPress reported that auto-updates were unavailable for it.
require_once BDOX_PACK_DIR . 'src/updates.php';

foreach ( glob( BDOX_PACK_DIR . 'abilities/*.php' ) ?: array() as $__ability_file ) {
	require_once $__ability_file;
}

ACFW_Pack::boot();
