<?php
/**
 * Plugin Name:       Unofficial Abilities for Breakdance Elements for Oxygen
 * Plugin URI:        https://github.com/NertiPL
 * Description:        MCP ability pack for Oxygen 6.x + Breakdance Elements. Unhides the core elements the built-in Oxygen MCP tools cannot see (Section, Div, Heading, Text, RichText, TextLink, WrapperLink, Icon, Image2, code blocks), and adds what the built-in tools lack: CSS cache regeneration, partial global-settings patching, non-destructive selector merging, editor element visibility, a setup/stale-cache report, and a per-element report of the default CSS you must override for a design to survive.
 * Version:           0.5.0
 * Requires Plugins:  agent-connector-for-wp, oxygen
 * Requires PHP:      8.1
 * Author:            Nerti
 * Author URI:        https://github.com/NertiPL
 * License:           GPL-2.0-or-later
 *
 * Agent Connector: Ability Pack
 * Agent Connector Target: breakdance-elements-for-oxygen/plugin.php
 *
 * You write NO permission / auth / domain-lock / audit code. Agent Connector
 * injects all of it for every ability registered here.
 *
 * WHY THIS PACK EXISTS
 * --------------------
 * Measured on Oxygen 6.2.0-beta.2 + Breakdance Elements for Oxygen 1.1.0.beta.1:
 *
 *   count( \Breakdance\Elements\get_elements_for_builder() )  => 162
 *   count( breakdance_mcp_visible_element_classnames() )      => 149
 *
 * The 13-element difference is the hardcoded BREAKDANCE_MCP_HIDDEN_ELEMENTS
 * constant, and it contains exactly the elements every page is built from.
 * An agent therefore cannot use get-element-schemas or edit-post for Section,
 * Div, Heading, Text, RichText, TextLink, WrapperLink, Icon or Image2 — it has
 * to hand-write the element tree through php-eval instead. Section matters most:
 * it is the only element that renders `.section-container`, the wrapper that
 * consumes the global content width, so without it every layout an agent builds
 * ignores the global container settings. That constant is independent of the builder's
 * own `oxygen_builder_hidden_elements` option: unhiding elements in the editor
 * does not make them visible to MCP.
 *
 * The visibility list ends in bdox_run_filters( 'breakdance_builder_elements' ),
 * which is a plain apply_filters run twice (breakdance_* and oxygen_*), so the
 * elements can be restored from outside the plugin. That is what this pack does
 * first, before registering anything else.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'BDOX_PACK_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDOX_PACK_VERSION', '0.5.0' );

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

foreach ( glob( BDOX_PACK_DIR . 'abilities/*.php' ) ?: array() as $__ability_file ) {
	require_once $__ability_file;
}

ACFW_Pack::boot();
