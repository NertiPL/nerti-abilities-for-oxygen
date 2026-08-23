<?php
/**
 * Compact map of what an element exposes in its Design / Settings tabs.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Flattens a JSON Schema into "dotted.path => short type" pairs.
 *
 * The point is volume. get-element-schemas on a single element returns 5-15k tokens of
 * JSON Schema, most of it $defs and repeated breakpoint wrappers, and an agent that cannot
 * afford to read it falls back to CSS classes for things the element styles natively.
 * This returns the same information as one line per control.
 *
 * @param mixed                $schema Schema node.
 * @param string               $prefix Dotted path accumulated so far.
 * @param array<string, mixed> $defs   Root $defs, for resolving $ref.
 * @param int                  $depth  Recursion guard.
 * @return array<string, string>
 */
function bdox_pack_flatten_schema( $schema, string $prefix, array $defs, int $depth = 0, bool $expand = false ): array {
	if ( ! is_array( $schema ) || $depth > 12 ) {
		return array();
	}

	if ( ! $expand ) {
		$blob = bdox_pack_collapse_blob( $schema );
		if ( '' !== $blob ) {
			return array( $prefix => $blob );
		}
	}

	if ( isset( $schema['$ref'] ) && is_string( $schema['$ref'] ) ) {
		$name = substr( $schema['$ref'], strlen( '#/$defs/' ) );
		if ( isset( $defs[ $name ] ) ) {
			return bdox_pack_flatten_schema( $defs[ $name ], $prefix, $defs, $depth + 1, $expand );
		}
		return array( $prefix => 'ref:' . $name );
	}

	/* A responsive control: an object keyed by breakpoint names. Collapse it to one line —
	   the breakpoint keys are always the same five and repeating them is pure noise. */
	if ( isset( $schema['propertyNames']['$ref'] )
		&& '#/$defs/breakpointKeys' === $schema['propertyNames']['$ref'] ) {
		$inner = isset( $schema['additionalProperties'] ) ? $schema['additionalProperties'] : array();
		return array( $prefix => bdox_pack_schema_atom( $inner, $defs ) . ' @breakpoint' );
	}

	if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
		$out = array();
		foreach ( $schema['properties'] as $key => $child ) {
			$sciezka = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			$out     = array_merge( $out, bdox_pack_flatten_schema( $child, $sciezka, $defs, $depth + 1, $expand ) );
		}
		return $out;
	}

	return array( $prefix => bdox_pack_schema_atom( $schema, $defs ) );
}

/**
 * One line for a whole control group whose shape is always the same.
 *
 * Two groups make up most of the volume of a Design tab and neither rewards being read
 * property by property: the typography block (fontFamily/fontSize/fontWeight/lineHeight
 * plus the whole `advanced` subtree) and the text-effects block (fill, gradient, the eight
 * image_* keys, stroke, shadow). They repeat verbatim for every styled part of an element,
 * so a WooCommerce element with a separate typography group for the current price and the
 * struck-through old price spends ~70 lines saying the same two things twice.
 *
 * Measured 2026-08-23 on EssentialElements\Wooproductprice: 70 lines collapse to 8.
 * Pass expand=true to design-map when you actually need a leaf inside one of these.
 *
 * @param array $schema Schema node.
 * @return string Summary line, or '' when this node is not a known group.
 */
function bdox_pack_collapse_blob( array $schema ): string {
	$props = isset( $schema['properties'] ) && is_array( $schema['properties'] )
		? $schema['properties']
		: array();

	if ( isset( $props['fontFamily'], $props['fontSize'], $props['fontWeight'] ) ) {
		return 'typography{fontFamily,fontSize,fontWeight,lineHeight'
			. ( isset( $props['advanced'] ) ? ',advanced{letterSpacing,wordSpacing,textTransform,textWrap,fontStyle,fontWidth,textDirection,decoration}' : '' )
			. '} @breakpoint — expand:true to see leaves';
	}

	if ( isset( $props['fill'], $props['stroke_color'] ) ) {
		return 'text-effects{fill,gradient,image,image_size,image_position,image_repeat,'
			. 'image_width,image_height,image_top,image_left,stroke_color,stroke_width,text_shadow}'
			. ' @breakpoint — expand:true to see leaves';
	}

	return '';
}

/**
 * One-line description of a leaf schema node.
 *
 * @param mixed                $schema Schema node.
 * @param array<string, mixed> $defs   Root $defs.
 * @return string
 */
function bdox_pack_schema_atom( $schema, array $defs ): string {
	if ( ! is_array( $schema ) ) {
		return 'any';
	}

	if ( isset( $schema['$ref'] ) && is_string( $schema['$ref'] ) ) {
		$name = substr( $schema['$ref'], strlen( '#/$defs/' ) );
		if ( 'unit' === $name ) {
			return 'unit{number,unit,style}';
		}
		if ( isset( $defs[ $name ] ) ) {
			return bdox_pack_schema_atom( $defs[ $name ], $defs );
		}
		return 'ref:' . $name;
	}

	if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
		$wartosci = array_map(
			static function ( $v ) {
				return null === $v ? 'null' : (string) $v;
			},
			$schema['enum']
		);
		return 'enum: ' . implode( '|', $wartosci );
	}

	if ( isset( $schema['properties']['number'] ) && isset( $schema['properties']['unit'] ) ) {
		return 'unit{number,unit,style}';
	}

	$typ = isset( $schema['type'] ) ? $schema['type'] : 'any';
	if ( is_array( $typ ) ) {
		$typ = implode( '|', $typ );
	}
	return (string) $typ;
}

bdox_pack_ability(
	array(
		'name'             => 'bdox/design-map',
		'label'            => 'Design Tab Map',
		'description'      => 'Compact map of every control an element exposes, one line per control, as "dotted.path => type". Use this INSTEAD of get-element-schemas whenever the question is "can I style this natively, and under which property path" - the full schema for one element runs 5-15k tokens and is mostly $defs and repeated breakpoint wrappers, which is why agents give up and reach for a CSS class for something the element already styles itself. Responsive controls collapse to one line marked "@breakpoint" (the five breakpoint keys are always the same). Filter with "prefix" to get just one tab, e.g. "design" or "design.typography". Read the result as: a path listed here can be written with edit-post and compiles to the element own generated CSS, so it needs no class and no selector; a path NOT listed means the element genuinely has no native control and a class is the honest answer. Read the paths verbatim: several are NOT named the way you would guess, and guessing is exactly how an agent concludes an element has no Design tab and reaches for a class instead. On EssentialElements\Div the flex/grid layout lives under design.layout_v2 (not design.layout), and padding, borders, radius and shadow live under design.container (not design.borders) - a get-element-schemas call filtered by the guessed names returns empty and looks like proof that the controls do not exist.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'slugs'  => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Element slugs, e.g. ["EssentialElements\\\\Text"]. Discover them with get-element-slugs.',
				),
				'prefix' => array(
					'type'        => 'string',
					'description' => 'Only return paths starting with this, e.g. "design" or "design.typography". Empty returns everything.',
				),
				'expand' => array(
					'type'        => 'boolean',
					'description' => 'Expand the typography and text-effects groups into their individual leaves. Default false, which prints each of them as one summary line — that is what keeps a WooCommerce element readable. Set true only when you need a specific leaf, e.g. letterSpacing.',
				),
			),
			'required'             => array( 'slugs' ),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'elements' => array( 'type' => 'object' ),
				'missing'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			if ( ! function_exists( 'breakdance_get_element_schema_for_slug' ) ) {
				return new WP_Error(
					'bdox_brak_oxygena',
					'Nie widze generatora schematow Oxygena (breakdance_get_element_schema_for_slug). Czy Oxygen 6.x jest aktywny?'
				);
			}

			$prefix   = isset( $input['prefix'] ) ? (string) $input['prefix'] : '';
			$expand   = ! empty( $input['expand'] );
			$elementy = array();
			$brakuje  = array();

			foreach ( (array) $input['slugs'] as $slug ) {
				$slug   = (string) $slug;
				$schema = breakdance_get_element_schema_for_slug( $slug );
				if ( ! is_array( $schema ) ) {
					$brakuje[] = $slug;
					continue;
				}

				$defs   = isset( $schema['$defs'] ) && is_array( $schema['$defs'] ) ? $schema['$defs'] : array();
				$plaski = bdox_pack_flatten_schema(
					array( 'properties' => isset( $schema['properties'] ) ? $schema['properties'] : array() ),
					'',
					$defs,
					0,
					$expand
				);

				if ( '' !== $prefix ) {
					$plaski = array_filter(
						$plaski,
						static function ( $klucz ) use ( $prefix ) {
							return 0 === strpos( $klucz, $prefix );
						},
						ARRAY_FILTER_USE_KEY
					);
				}

				ksort( $plaski );
				$elementy[ $slug ] = $plaski;
			}

			return array(
				'elements' => $elementy,
				'missing'  => $brakuje,
			);
		},
	)
);
