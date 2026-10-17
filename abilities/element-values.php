<?php
/**
 * bdox/element-values: every Design value of every element on one post, flattened, plus the risks
 * that a transfer keeps re-introducing (Nerti, 2026-10-17: "never make me remind you about default
 * hovers, selected states and colours again").
 *
 * Flags per element:
 *  - design-colour-on-stateful: a Design colour on an element that CSS changes in a state
 *    (:hover, [aria-selected], [aria-expanded], [aria-current], .is-*, [open]). The Design rule has the
 *    same specificity and loads later, so it freezes the state the measurement happened to see
 *    (DISC tabs kept "selected" colours, 2026-10-17).
 *  - colour-copies-ancestor: the same Design colour as the nearest ancestor that sets one. Redundant at
 *    rest, and it stops the element following the parent's hover colour ("Zajrzyj do środka" went white
 *    on white on hover).
 *  - image-from-url: Image2 in URL mode; the client cannot swap it from the media library.
 *  - not-essential: an OxygenElements\* element (no Design tab for the client).
 * type_defaults lists, once per element type used, the declarations of the type's default CSS that
 * touch colour, text-decoration or hover: the Essential Elements defaults that silently beat a design.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Flatten a nested array into dotted paths, keeping only scalar leaves; `style` wins over number/unit.
 *
 * @param mixed  $value  Value.
 * @param string $prefix Path so far.
 * @param array  $out    Accumulator.
 * @return void
 */
function bdox_pack_ev_flatten( $value, string $prefix, array &$out ): void {
	if ( is_array( $value ) ) {
		if ( isset( $value['style'] ) && is_scalar( $value['style'] ) ) {
			$out[ $prefix ] = (string) $value['style'];
			return;
		}
		foreach ( $value as $k => $v ) {
			bdox_pack_ev_flatten( $v, '' === $prefix ? (string) $k : $prefix . '.' . $k, $out );
		}
		return;
	}
	if ( is_scalar( $value ) && '' !== (string) $value ) {
		$out[ $prefix ] = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value;
	}
}

/**
 * Class names that some rule of the global code changes in a state.
 *
 * @return array<string, true>
 */
function bdox_pack_ev_stateful_classes(): array {
	$g   = function_exists( 'Breakdance\\Data\\get_global_settings_array' ) ? \Breakdance\Data\get_global_settings_array() : array();
	$css = '';
	foreach ( (array) ( $g['settings']['code']['stylesheets'] ?? array() ) as $sheet ) {
		$css .= "\n" . (string) ( $sheet['code'] ?? '' );
	}
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$out = array();
	if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $rule ) {
			if ( ! preg_match( '/(?<![\w-])(color|background|background-color|border-color|text-decoration)\s*:/', $rule[2] ) ) {
				continue;
			}
			foreach ( explode( ',', $rule[1] ) as $sel ) {
				if ( preg_match_all( '/\.([\w-]+)(?:\.[\w-]+)*(?::hover|:focus-visible|\[aria-(?:selected|expanded|current|pressed)|\.is-[\w-]+|\[open\])/', $sel, $mm ) ) {
					foreach ( $mm[1] as $cls ) {
						$out[ $cls ] = true;
					}
				}
			}
		}
	}
	return $out;
}

/**
 * Default CSS declarations of an element type that touch colour, decoration or hover.
 *
 * @param string $type Element class name.
 * @return string[]
 */
function bdox_pack_ev_type_defaults( string $type ): array {
	if ( ! class_exists( $type ) || ! method_exists( $type, 'defaultCss' ) ) {
		return array();
	}
	$css = (string) call_user_func( array( $type, 'defaultCss' ) );
	$out = array();
	if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $rule ) {
			$hover = false !== strpos( $rule[1], ':hover' );
			foreach ( explode( ';', $rule[2] ) as $decl ) {
				$decl = trim( $decl );
				if ( '' !== $decl && ( $hover || preg_match( '/^(color|text-decoration|background(-color)?|border-color)\s*:/', $decl ) ) ) {
					$out[] = trim( $rule[1] ) . ' { ' . $decl . ' }';
				}
			}
		}
	}
	return array_slice( array_values( array_unique( $out ) ), 0, 20 );
}

bdox_pack_ability(
	array(
		'name'             => 'bdox/element-values',
		'label'            => 'Element values and risks',
		'description'      => 'Every Design value of every element on one post, flattened to dotted paths, plus flags: design-colour-on-stateful, colour-copies-ancestor, image-from-url, not-essential; and type_defaults (Essential Elements default CSS touching colour/decoration/hover). Run before and after every transfer step. Read-only.',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id'      => array( 'type' => 'integer', 'minimum' => 1 ),
				'only_flagged' => array( 'type' => 'boolean', 'description' => 'Return only elements with at least one flag (default true).' ),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		),
		'output_schema'    => array( 'type' => 'object' ),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true, 'destructive' => false ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
			$post_id = (int) ( $input['post_id'] ?? 0 );
			$tree    = bdox_pack_get_tree( $post_id );
			if ( null === $tree || ! isset( $tree['root'] ) ) {
				return new WP_Error( 'bdox_no_tree', 'No builder tree on this post.', array( 'status' => 404 ) );
			}
			$only = ! isset( $input['only_flagged'] ) || (bool) $input['only_flagged'];

			$names = array();
			if ( function_exists( 'Breakdance\\BreakdanceOxygen\\Selectors\\getOxySelectors' ) ) {
				foreach ( (array) \Breakdance\BreakdanceOxygen\Selectors\getOxySelectors() as $s ) {
					$names[ (string) ( $s['id'] ?? '' ) ] = (string) ( $s['name'] ?? '' );
				}
			}
			$stateful = bdox_pack_ev_stateful_classes();
			$types    = array();
			$elements = array();
			$counts   = array();

			$walk = static function ( array $n, ?string $parent_colour, bool $in_stateful ) use ( &$walk, &$elements, &$types, &$counts, $names, $stateful, $only ) {
				$type    = (string) ( $n['data']['type'] ?? '' );
				$p       = (array) ( $n['data']['properties'] ?? array() );
				$classes = array_values( array_filter( array_map( static fn( $c ) => $names[ (string) $c ] ?? '', (array) ( $p['meta']['classes'] ?? array() ) ) ) );
				$own_state = (bool) array_intersect_key( array_flip( $classes ), $stateful );
				$colour    = $p['design']['typography']['color']['breakpoint_base'] ?? null;
				$flags     = array();
				if ( null !== $colour && ( $own_state || $in_stateful ) ) {
					$flags[] = 'design-colour-on-stateful';
				}
				if ( null !== $colour && null !== $parent_colour && $colour === $parent_colour ) {
					$flags[] = 'colour-copies-ancestor';
				}
				if ( 'EssentialElements\\Image2' === $type && 'url' === ( $p['content']['image']['from'] ?? '' ) ) {
					$flags[] = 'image-from-url';
				}
				if ( 0 === strpos( $type, 'OxygenElements\\' ) && 'OxygenElements\\Component' !== $type ) {
					$flags[] = 'not-essential';
				}
				if ( '' !== $type && 'root' !== $type ) {
					$types[ $type ] = true;
				}
				foreach ( $flags as $f ) {
					$counts[ $f ] = ( $counts[ $f ] ?? 0 ) + 1;
				}
				if ( ! $only || $flags ) {
					$design = array();
					bdox_pack_ev_flatten( $p['design'] ?? array(), '', $design );
					$text = $p['content']['content']['text'] ?? '';
					$elements[] = array(
						'id'      => (int) ( $n['id'] ?? 0 ),
						'type'    => $type,
						'classes' => $classes,
						'text'    => is_string( $text ) ? mb_substr( wp_strip_all_tags( $text ), 0, 60 ) : '',
						'flags'   => $flags,
						'design'  => $design,
					);
				}
				foreach ( (array) ( $n['children'] ?? array() ) as $c ) {
					$walk( (array) $c, null !== $colour ? (string) $colour : $parent_colour, $in_stateful || $own_state );
				}
			};
			$walk( $tree['root'], null, false );

			$defaults = array();
			foreach ( array_keys( $types ) as $t ) {
				$d = bdox_pack_ev_type_defaults( $t );
				if ( $d ) {
					$defaults[ $t ] = $d;
				}
			}

			return array(
				'post_id'       => $post_id,
				'flag_counts'   => $counts,
				'elements'      => $elements,
				'type_defaults' => $defaults,
				'how_to_fix'    => array(
					'design-colour-on-stateful' => 'Remove the Design colour; let the class and its state rule own colour. Verify every state (hover, selected, open) against the design.',
					'colour-copies-ancestor'    => 'Remove the Design colour (it is inherited anyway), then compare rest-state colours page-wide: an element whose own class sets another colour needs the Design value back.',
					'image-from-url'            => 'Switch to media library: content.image = {from: media_library, media: prepareMedia(attachment id), size: full, alt: custom|decorative, custom_alt}.',
					'not-essential'             => 'Keep only where Essential Elements has no equivalent (HtmlCode for inputs, SvgIcon2 for animated sprite icons).',
				),
			);
		},
	)
);
