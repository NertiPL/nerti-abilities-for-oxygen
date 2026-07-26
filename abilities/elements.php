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
		'description'      => 'Compare what the builder can place against what the MCP element tools can see, and report which elements this pack restored. Call this when get-element-schemas returns an element in "missing" or edit-post rejects an element_type: the answer is almost always that the element is on the built-in hidden list, not that it does not exist. Also reports the builder\'s own hidden-elements option, which is a separate mechanism and does not affect MCP visibility.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'builder_count'          => array( 'type' => 'integer' ),
				'mcp_visible_count'      => array( 'type' => 'integer' ),
				'hidden_from_mcp'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'restored_by_this_pack'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'builder_hidden_option'  => array( 'type' => array( 'array', 'string', 'null' ) ),
			),
		),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			$builder = array();
			if ( function_exists( 'Breakdance\\Elements\\get_elements_for_builder' ) ) {
				foreach ( \Breakdance\Elements\get_elements_for_builder() as $key => $element ) {
					$builder[] = is_array( $element ) ? ( $element['slug'] ?? $key ) : $key;
				}
			}

			$visible = function_exists( 'breakdance_mcp_visible_element_classnames' )
				? breakdance_mcp_visible_element_classnames()
				: array();

			$restored = array_values(
				array_filter(
					bdox_pack_restored_elements(),
					static fn( $class ) => class_exists( $class )
				)
			);

			return array(
				'builder_count'         => count( $builder ),
				'mcp_visible_count'     => count( $visible ),
				'hidden_from_mcp'       => array_values( array_diff( $builder, $visible ) ),
				'restored_by_this_pack' => $restored,
				'builder_hidden_option' => get_option( 'oxygen_builder_hidden_elements' ),
			);
		},
	)
);
