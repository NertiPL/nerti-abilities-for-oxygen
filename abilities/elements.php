<?php
/**
 * Element-visibility introspection.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

ACFW_Pack::category(
	'bdox',
	array(
		'label'       => 'Breakdance Elements for Oxygen',
		'description' => 'Element visibility, CSS cache and non-destructive settings/selector editing for Oxygen 6.x + Breakdance Elements.',
	)
);

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/element-visibility',
		'label'            => 'Element Visibility Report',
		'description'      => 'Why an element is invisible to MCP, split by which of the three bans is responsible. Call this when get-element-schemas returns an element in "missing" or edit-post rejects an element_type. THREE SEPARATE LISTS, and they are not the same mechanism: (1) banned_by_oxygen — the hardcoded BREAKDANCE_MCP_HIDDEN_ELEMENTS constant in Oxygen\'s mcp/schemas/elements.php, which no site setting can change; (2) hidden_in_elements_panel — the oxygen_builder_hidden_elements WP option a human edits under Oxygen → Settings → Elements, which Oxygen pre-fills with ten Essential Elements on install; (3) restored_by_this_pack — what this pack adds back on the breakdance_builder_elements filter at priority 20. CRITICAL AND NOT OBVIOUS: list (2) DOES affect MCP. The settings page subtracts its list on the same breakdance_builder_elements filter that Oxygen runs the MCP element list through, so an element a human hides in the Elements panel disappears from get-element-slugs and get-element-schemas too. Measured 2026-08-21 on Oxygen 6.2.0-beta.5: adding EssentialElements\\Button to the option took get-element-slugs from 164 to 163. So on an agent-built site: BUILD FIRST, HIDE LAST — hiding elements before the build is finished cuts the agent off from them.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'registered_count'         => array( 'type' => 'integer' ),
				'mcp_visible_count'        => array( 'type' => 'integer' ),
				'banned_by_oxygen'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'hidden_in_elements_panel' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'restored_by_this_pack'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'still_hidden_from_mcp'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'warning'                  => array( 'type' => 'string' ),
			),
		),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			$registered = function_exists( 'Breakdance\\Elements\\get_element_classnames' )
				? \Breakdance\Elements\get_element_classnames()
				: array();

			$visible = function_exists( 'breakdance_mcp_visible_element_classnames' )
				? breakdance_mcp_visible_element_classnames()
				: array();

			$panel = get_option( 'oxygen_builder_hidden_elements' );
			$panel = is_string( $panel ) ? json_decode( $panel, true ) : $panel;
			$panel = is_array( $panel ) ? array_values( array_filter( $panel, 'is_string' ) ) : array();

			$restored = array_values(
				array_filter(
					bdox_pack_restored_elements(),
					static fn( $class ) => class_exists( $class )
				)
			);

			return array(
				'registered_count'         => count( $registered ),
				'mcp_visible_count'        => count( $visible ),
				'banned_by_oxygen'         => defined( 'BREAKDANCE_MCP_HIDDEN_ELEMENTS' )
					? array_values( (array) constant( 'BREAKDANCE_MCP_HIDDEN_ELEMENTS' ) )
					: array(),
				'hidden_in_elements_panel' => $panel,
				'restored_by_this_pack'    => $restored,
				'still_hidden_from_mcp'    => array_values( array_diff( $registered, $visible ) ),
				'warning'                  => 'hidden_in_elements_panel is subtracted from the MCP element list too (same breakdance_builder_elements filter). Build first, hide last.',
			);
		},
	)
);
