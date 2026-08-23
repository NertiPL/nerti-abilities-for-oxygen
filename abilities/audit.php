<?php
/**
 * Audit of what the builder actually compiled.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Typed selector properties that map to exactly one CSS declaration.
 *
 * Kept deliberately conservative: only properties whose emitted declaration name is
 * unambiguous. A false "missing" report is worse than a gap, because it sends people
 * looking for a bug that is not there.
 *
 * @return array<string, string> dotted property path => CSS declaration name
 */
function bdox_pack_audit_property_map(): array {
	return array(
		'typography.font_size'                 => 'font-size',
		'typography.font_weight'               => 'font-weight',
		'typography.font_family'               => 'font-family',
		'typography.line_height'               => 'line-height',
		'typography.letter_spacing'            => 'letter-spacing',
		'typography.color'                     => 'color',
		'typography.text_transform'            => 'text-transform',
		'typography.text_align'                => 'text-align',
		'background.background_color'          => 'background-color',
		'spacing.spacing.margin.top'           => 'margin-top',
		'spacing.spacing.margin.right'         => 'margin-right',
		'spacing.spacing.margin.bottom'        => 'margin-bottom',
		'spacing.spacing.margin.left'          => 'margin-left',
		'spacing.spacing.padding.top'          => 'padding-top',
		'spacing.spacing.padding.right'        => 'padding-right',
		'spacing.spacing.padding.bottom'       => 'padding-bottom',
		'spacing.spacing.padding.left'         => 'padding-left',
	);
}

/**
 * Value at a dotted path, or null.
 *
 * @param array<string, mixed> $tree Tree.
 * @param string               $path Dotted path.
 * @return mixed|null
 */
function bdox_pack_audit_dig( array $tree, string $path ) {
	$node = $tree;
	foreach ( explode( '.', $path ) as $key ) {
		if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
			return null;
		}
		$node = $node[ $key ];
	}
	return $node;
}

/**
 * Strip every at-rule block (@media, @supports, @layer) so only top-level rules remain.
 *
 * Breakpoint rules live inside @media. Comparing a base-breakpoint property against text
 * that includes @media blocks would silently pass whenever the value exists at ANY width —
 * which is exactly the case this audit has to catch.
 *
 * @param string $css CSS.
 * @return string
 */
function bdox_pack_audit_strip_at_rules( string $css ): string {
	$out   = '';
	$len   = strlen( $css );
	$i     = 0;
	while ( $i < $len ) {
		if ( '@' === $css[ $i ] ) {
			$brace = strpos( $css, '{', $i );
			if ( false === $brace ) {
				break;
			}
			$depth = 1;
			$j     = $brace + 1;
			while ( $j < $len && $depth > 0 ) {
				if ( '{' === $css[ $j ] ) {
					$depth++;
				} elseif ( '}' === $css[ $j ] ) {
					$depth--;
				}
				$j++;
			}
			$i = $j;
			continue;
		}
		$out .= $css[ $i ];
		$i++;
	}
	return $out;
}

/**
 * Every declaration block whose selector list contains exactly this class.
 *
 * A selector compiles to more than one rule: the typed properties in one, custom_css in
 * another. Both count, so they are concatenated.
 *
 * @param string $css   CSS with at-rules already stripped.
 * @param string $class Class name without the leading dot.
 * @return string
 */
function bdox_pack_audit_rules_for( string $css, string $class ): string {
	$found = '';
	$len   = strlen( $css );
	$start = 0;
	while ( $start < $len ) {
		$brace = strpos( $css, '{', $start );
		if ( false === $brace ) {
			break;
		}
		$selector = trim( substr( $css, $start, $brace - $start ) );
		$close    = strpos( $css, '}', $brace );
		if ( false === $close ) {
			break;
		}
		$body = substr( $css, $brace + 1, $close - $brace - 1 );
		foreach ( explode( ',', $selector ) as $one ) {
			if ( '.' . $class === trim( $one ) ) {
				$found .= $body . ';';
				break;
			}
		}
		$start = $close + 1;
	}
	return $found;
}

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/audit-compiled-css',
		'label'            => 'Audit Compiled CSS',
		'description'      => 'Finds design values that were SAVED but never COMPILED — the builder\'s most expensive failure mode, because every tool reports success and the render simply ignores the value. Measured three separate times in one site transfer: the secondary button outline colour, the font family and weight of every button, and every border colour set on a :hover state. Each looked correct in the editor, each returned success from the write tool, and none of them reached the stylesheet. This ability compares the stored selector properties against the compiled selectors.css and reports what is missing, plus the known global-settings fields the compiler accepts and ignores. Run it after any batch of design changes, and FIRST whenever a value "does not work" even though the editor shows it set. Limitations, stated so the output can be trusted: only the base breakpoint is checked (rules inside @media are excluded on purpose), only properties whose emitted declaration name is unambiguous are mapped, and custom_css blocks count as present. A clean report means "nothing from the mapped set went missing", not "everything is perfect".',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'names' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Class names to check, without the leading dot. Omit to check every class selector.',
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'checked'          => array( 'type' => 'integer' ),
				'missing'          => array( 'type' => 'array' ),
				'global_settings'  => array( 'type' => 'array' ),
				'verdict'          => array( 'type' => 'string' ),
			),
		),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true, 'destructive' => false ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			$uploads = wp_upload_dir();
			$base    = trailingslashit( $uploads['basedir'] ) . 'oxygen/css/';
			$sel_css = is_readable( $base . 'selectors.css' ) ? (string) file_get_contents( $base . 'selectors.css' ) : '';
			$glob_css = is_readable( $base . 'global-settings.css' ) ? (string) file_get_contents( $base . 'global-settings.css' ) : '';

			if ( '' === $sel_css ) {
				return new WP_Error( 'bdox_no_compiled_css', 'selectors.css is missing or empty — run bdox/regenerate-css first.', array( 'status' => 409 ) );
			}

			$top_level = bdox_pack_audit_strip_at_rules( $sel_css );

			$selectors = function_exists( 'Breakdance\\BreakdanceOxygen\\Selectors\\getOxySelectors' )
				? \Breakdance\BreakdanceOxygen\Selectors\getOxySelectors()
				: array();

			$wanted  = isset( $input['names'] ) && is_array( $input['names'] ) ? array_map( 'strval', $input['names'] ) : null;
			$map     = bdox_pack_audit_property_map();
			$missing = array();
			$checked = 0;

			foreach ( $selectors as $selector ) {
				$name = (string) ( $selector['name'] ?? '' );
				$type = (string) ( $selector['type'] ?? '' );
				if ( '' === $name || 'class' !== $type ) {
					continue;
				}
				if ( null !== $wanted && ! in_array( $name, $wanted, true ) ) {
					continue;
				}
				$props = $selector['properties']['breakpoint_base'] ?? null;
				if ( ! is_array( $props ) ) {
					continue;
				}
				$checked++;
				$rules = bdox_pack_audit_rules_for( $top_level, $name );

				foreach ( $map as $path => $declaration ) {
					$value = bdox_pack_audit_dig( $props, $path );
					if ( null === $value || '' === $value || array() === $value ) {
						continue;
					}
					if ( is_array( $value ) && ! isset( $value['style'] ) ) {
						continue;
					}
					if ( false === strpos( $rules, $declaration . ':' ) ) {
						$missing[] = array(
							'selector'    => $name,
							'property'    => $path,
							'declaration' => $declaration,
							'stored'      => is_array( $value ) ? (string) ( $value['style'] ?? '' ) : (string) $value,
							'reason'      => '' === $rules
								? 'the selector has no rule in selectors.css at all — is the class attached to any element?'
								: 'stored but not emitted',
						);
					}
				}

				/*
				 * Border colour without width and style, on a STATE selector (&:hover and friends).
				 * Measured 2026-08-23: on a base selector the compiler falls back to the
				 * border-color longhand, so a colour-only border works there. On a child selector
				 * it emits nothing at all — and a hover that changes only the colour, inheriting
				 * the width from the resting state, is the most natural way to write one.
				 */
				foreach ( (array) ( $selector['children'] ?? array() ) as $child ) {
					$child_name  = (string) ( $child['name'] ?? '' );
					$child_props = $child['properties']['breakpoint_base'] ?? null;
					if ( '' === $child_name || ! is_array( $child_props ) ) {
						continue;
					}
					$child_css = bdox_pack_audit_rules_for( $top_level, $name . ltrim( str_replace( '&', '', $child_name ) ) );
					$sides     = bdox_pack_audit_dig( $child_props, 'borders.borders' );
					if ( ! is_array( $sides ) ) {
						continue;
					}
					foreach ( $sides as $side => $def ) {
						if ( ! is_array( $def ) ) {
							continue;
						}
						$colour = (string) ( $def['color'] ?? '' );
						$width  = $def['width'] ?? null;
						$style  = (string) ( $def['style'] ?? '' );
						$has_w  = is_array( $width ) ? ( null !== ( $width['number'] ?? null ) && '' !== (string) ( $width['number'] ?? '' ) ) : ( null !== $width && '' !== (string) $width );
						$worked_around = false !== strpos( $child_css, 'border-color:' ) || false !== strpos( $child_css, "border-$side:" );
						if ( '' !== $colour && ( ! $has_w || '' === $style ) && ! $worked_around ) {
							$missing[] = array(
								'selector'    => $name . ' ' . $child_name,
								'property'    => "borders.borders.$side.color",
								'declaration' => "border-$side",
								'stored'      => $colour,
								'reason'      => 'border colour without width and style on a state selector — the compiler emits nothing there. On a base selector it would fall back to border-color. Give it width and style, or write border-color in custom_css on the parent.',
							);
						}
					}
				}
			}

			/*
			 * Global settings fields the compiler accepts and ignores. These are checked by
			 * looking for the effect in global-settings.css rather than by trusting the stored
			 * value, because the stored value is exactly what lies here.
			 */
			$globals = array();
			if ( '' !== $glob_css ) {
				$settings = function_exists( 'Breakdance\Data\get_global_settings_array' )
					? \Breakdance\Data\get_global_settings_array()
					: array();
				$buttons  = is_array( $settings ) ? ( $settings['settings']['buttons'] ?? ( $settings['buttons'] ?? null ) ) : null;

				if ( is_array( $buttons ) ) {
					$family = $buttons['typography']['font_family'] ?? ( $buttons['primary']['typography']['font_family'] ?? '' );
					$ma_rodzine = false !== strpos( $glob_css, '--bde-button-font-family' )
						|| (bool) preg_match( '/\.button-atom[^{}]*\{[^}]*font-family/', $glob_css );
					if ( '' !== (string) $family && ! $ma_rodzine ) {
						$globals[] = array(
							'field'  => 'buttons.typography.font_family',
							'stored' => (string) $family,
							'reason' => 'the compiler emits no font-family for .button-atom at all, so buttons fall back to the body font. Set it in a global code stylesheet on .button-atom.',
						);
					}
					$weight = (string) ( $buttons['typography']['font_weight'] ?? '' );
					if ( '' !== $weight && false === strpos( $glob_css, '--bde-button-font-weight:' . $weight ) ) {
						$globals[] = array(
							'field'  => 'buttons.typography.font_weight',
							'stored' => $weight,
							'reason' => 'compiled --bde-button-font-weight does not match the stored value; the compiler keeps its own default.',
						);
					}
					$outline = (string) ( $buttons['secondary']['border_color'] ?? '' );
					if ( '' !== $outline && false === strpos( $glob_css, $outline ) ) {
						$globals[] = array(
							'field'  => 'buttons.secondary.border_color',
							'stored' => $outline,
							'reason' => 'the outline of a secondary button is derived from its text colour; border_color is stored and ignored.',
						);
					}
				}
			}

			$verdict = ( $missing || $globals )
				? 'VALUES LOST — the editor shows them, the stylesheet does not have them. Fix these before measuring anything else.'
				: 'clean — every mapped base-breakpoint property reached selectors.css.';

			return array(
				'checked'         => $checked,
				'missing'         => $missing,
				'global_settings' => $globals,
				'verdict'         => $verdict,
			);
		},
	)
);
