<?php
/**
 * Element default CSS and the "you must override this" knowledge base.
 *
 * Every entry in bdox_pack_override_rules() was measured against a live
 * Oxygen 6.2 + Breakdance Elements install, not copied from documentation.
 * The default CSS itself is always read live from defaultCss(), so the report
 * cannot go stale when the plugin updates — only the annotations can, and each
 * one carries the selector it was written against.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * CSS specificity of a selector, as (ids, classes, elements).
 *
 * Deliberately simple: these selectors are all flat class/tag chains. It exists
 * so a caller can compare a default against its own class without guessing.
 *
 * @param string $selector A single (non-comma) selector.
 * @return array{0:int,1:int,2:int}
 */
function bdox_pack_specificity( string $selector ): array {
	$selector = trim( $selector );

	$ids     = preg_match_all( '/#[\w-]+/', $selector );
	$classes = preg_match_all( '/\.[\w-]+|\[[^\]]+\]|:(?!:)(?!hover\b)[\w-]+/', $selector );
	$hovers  = preg_match_all( '/:hover\b/', $selector );
	$tags    = preg_match_all( '/(^|[\s>+~])([a-z][\w-]*)/i', $selector );

	return array( (int) $ids, (int) $classes + (int) $hovers, (int) $tags );
}

/**
 * The declarations that bite, and what to do about them.
 *
 * "breaks" is what actually goes wrong on a real page; "fix" is the override
 * that survives a plugin update. Keyed by the selector the rule lives on.
 *
 * @return array<int,array<string,mixed>>
 */
function bdox_pack_override_rules(): array {
	return array(
		array(
			'selector'    => 'a, .button-atom--text',
			'source'      => 'global-settings.css',
			'declaration' => 'color: var(--bde-links-color) / :hover color: var(--bde-links-color-hover)',
			'breaks'      => 'Every link and every link-coloured icon takes the global link colour. Left unset it is the Breakdance blue, so a design with no explicit link colour renders blue links and blue hovers.',
			'fix'         => 'Site-wide: set colors.links and colors.links_hover in global settings, or override the --bde-links-color / --bde-links-color-hover variables ("inherit" makes links take their parent colour, which is usually what a design system wants). Per section: a Div exposes design.text_colors.link, which emits "<div-selector> a { color: … }" into the per-element stylesheet — the cleanest local fix, and it is a Design-tab control the client can use. Either way, give every link and button class an explicit hover colour: an undeclared hover falls back to the global value.',
			'must'        => true,
		),
		array(
			'selector'    => '.bde-text-link > *',
			'source'      => 'EssentialElements\\TextLink',
			'declaration' => 'margin: 0; color: currentColor',
			'breaks'      => 'Specificity is (0,1,0) — the universal selector adds nothing — so this ties with a flat project class and is decided by load order. Element defaults load first, so a class on the child still wins. It only takes effect when the child has no colour of its own, which is the intent.',
			'fix'         => 'Nothing required. Prefer colouring the link itself and letting currentColor inherit; that is what the rule is for.',
			'must'        => false,
		),
		array(
			'selector'    => 'design.typography.* (RichText)',
			'source'      => 'EssentialElements\\RichText',
			'declaration' => 'per-role typography: default, h1..h4, paragraph, link, list',
			'breaks'      => 'RichText does not accept design.typography.color the way Text and Heading do — edit-post rejects it as an unknown property. Its typography is split per role, so the path is design.typography.default.color (and .h2.color, .link.color, and so on).',
			'fix'         => 'Use design.typography.default.color for body copy, and the per-role branches for headings and links inside the same block. This is why RichText is the better element for prose: one element carries a whole type scale the client can edit.',
			'must'        => false,
		),
		array(
			'selector'    => 'content.content.tag (Text)',
			'source'      => 'EssentialElements\\Text',
			'declaration' => 'no tag control in the schema',
			'breaks'      => 'Text exposes only content.content.text, so edit-post rejects a tag. Trees written directly can carry a "tags" key and it does render (existing pages emit span and div that way), which makes the schema and the renderer disagree.',
			'fix'         => 'Do not send a tag through edit-post. Use Heading when the tag matters semantically, or TextLink, which does expose content.content.tag.',
			'must'        => false,
		),
		array(
			'selector'    => '.bde-section .section-container',
			'source'      => 'EssentialElements\\Section',
			'declaration' => 'max-width: var(--bde-section-width); padding: var(--bde-section-vertical-padding) var(--bde-section-horizontal-padding)',
			'breaks'      => 'Specificity (0,2,0) beats any project class (0,1,0), so section width and padding cannot be set from a class at all.',
			'fix'         => 'Set containers.sections.container_width, vertical_padding and horizontal_padding in global settings. That is the only source of section width — do not restate it in CSS.',
			'must'        => true,
		),
		array(
			'selector'    => '.bde-div',
			'source'      => 'EssentialElements\\Div',
			'declaration' => 'display: flex; flex-direction: column; align-items: flex-start; text-align: left; max-width: 100%; position: relative; background-size: cover',
			'breaks'      => 'Every container without its own display becomes a left-aligned flex column, so children stop stretching to full width. Converting plain containers to Div without handling this reflows the whole page.',
			'fix'         => 'Set the Layout control on each Div (it emits display + flex-direction to the per-element stylesheet, which loads last and wins). For containers that must behave like a plain block, register one custom selector .bde-div { display: block; align-items: initial; text-align: inherit } placed FIRST in the selector list, so project classes still override it.',
			'must'        => true,
		),
		array(
			'selector'    => '.bde-container-link',
			'source'      => 'EssentialElements\\WrapperLink',
			'declaration' => 'display: flex; flex-direction: column; align-items: flex-start; text-decoration: none; transition-property: background-color, border-color, box-shadow, color',
			'breaks'      => 'Same flex-column trap as Div, plus a 300ms transition on colours that fights a shorter transition set in a class.',
			'fix'         => 'Same as Div. Declare the full transition shorthand in the class — that is the reliable route. Do NOT try to set --defaultTransition through insert-stylesheet: measured 2026-07-27 on Oxygen 6.2.0-beta.2, the CSS importer lowercases custom property NAMES, so it stores --defaulttransition and var(--defaultTransition) never resolves. The import reports success, which is what makes it expensive. If you must set a camelCase custom property, write it through bdox/merge-css-selector into the custom_css slot, which bypasses the importer and preserves case.',
			'must'        => true,
		),
		array(
			'selector'    => '.bde-text',
			'source'      => 'EssentialElements\\Text',
			'declaration' => 'max-width: 100%; margin: 0; color: var(--bde-text-color, var(--bde-body-text-color))',
			'breaks'      => 'Colour comes through a custom property, so a class that sets only "color" competes with this rule instead of feeding it. Margin is zeroed.',
			'fix'         => 'In the class, set --bde-text-color alongside color to the same value — custom properties cascade independently, so the default rule simply consumes your value. Set margins in the Design tab or from a utility class registered after the component classes.',
			'must'        => true,
		),
		array(
			'selector'    => '.bde-rich-text',
			'source'      => 'EssentialElements\\RichText',
			'declaration' => 'max-width: 100%; color: var(--bde-text-color, var(--bde-body-text-color))',
			'breaks'      => 'Same colour mechanism as Text. No margin reset here, so spacing from the Design tab works.',
			'fix'         => 'Same --bde-text-color trick. Prefer RichText over Text for body copy: its Design tab targets the inner paragraph, so colour and spacing behave.',
			'must'        => false,
		),
		array(
			'selector'    => '.bde-heading',
			'source'      => 'EssentialElements\\Heading',
			'declaration' => 'max-width: 100%; margin: 0',
			'breaks'      => 'Heading margins are zeroed. No colour rule, so heading colour from the Design tab works.',
			'fix'         => 'Set margin_top / margin_bottom in the Design tab. Remember the global h1..h6 selector also applies — it typically sets line-height and font-family, which will beat an inherited value on a heading you split out of a larger block.',
			'must'        => false,
		),
		array(
			'selector'    => '.bde-columns',
			'source'      => 'EssentialElements\\Columns',
			'declaration' => 'display: flex; flex-direction: row; flex-wrap: nowrap; gap: var(--bde-column-gap)',
			'breaks'      => 'Columns never wrap, and the gap comes from the global column_gap rather than from your class.',
			'fix'         => 'Set containers.column_gap globally. For wrapping, declare flex-wrap in the class or use the responsive controls on the element.',
			'must'        => false,
		),
		array(
			'selector'    => '.bde-column',
			'source'      => 'EssentialElements\\Column',
			'declaration' => 'width: calc(var(--column-width) - var(--bde-column-gap) * ((var(--columnCount) - 1) / var(--columnCount)))',
			'breaks'      => 'Column width is computed from variables the element sets, so a width declared in a class is fighting arithmetic rather than a fixed value.',
			'fix'         => 'Use the Column Size control; it sets --column-width. Oxygen counts --columnCount itself.',
			'must'        => false,
		),
	);
}

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/element-defaults',
		'label'            => 'Element Defaults And Required Overrides',
		'description'      => 'Report the CSS an element emits before you style anything, and which of those declarations you MUST override for a design to survive. Read this before writing CSS for an element, and whenever a class "does not work": the usual cause is an element default with equal or higher specificity, not a typo. Returns the live default_css read from the element itself, its parsed declarations with specificity, and — for the elements that are known to bite — what breaks and the override that survives a plugin update. Omit element_slugs to get every element that emits any default CSS; pass only_must_override to get just the short list every project has to handle.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'element_slugs'      => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Element classnames, e.g. "EssentialElements\\\\Div". Omit for every element that emits default CSS.',
				),
				'only_must_override' => array(
					'type'        => 'boolean',
					'description' => 'Return only the declarations flagged as must-override. Default false.',
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'elements'       => array( 'type' => 'object' ),
				'override_rules' => array( 'type' => 'array' ),
				'cascade_note'   => array( 'type' => 'string' ),
			),
		),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			$only_must = ! empty( $input['only_must_override'] );

			$rules = array_values(
				array_filter(
					bdox_pack_override_rules(),
					static fn( $rule ) => ! $only_must || ! empty( $rule['must'] )
				)
			);

			if ( $only_must ) {
				return array(
					'elements'       => (object) array(),
					'override_rules' => $rules,
					'cascade_note'   => bdox_pack_cascade_note(),
				);
			}

			$slugs = isset( $input['element_slugs'] ) && is_array( $input['element_slugs'] )
				? $input['element_slugs']
				: ( function_exists( 'Breakdance\\Elements\\get_element_classnames' )
					? \Breakdance\Elements\get_element_classnames()
					: array() );

			$elements = array();

			foreach ( $slugs as $slug ) {
				if ( ! is_string( $slug ) || ! class_exists( $slug ) || ! method_exists( $slug, 'defaultCss' ) ) {
					continue;
				}

				$css = (string) $slug::defaultCss();
				if ( '' === trim( $css ) ) {
					continue;
				}

				$blocks = array();
				if ( preg_match_all( '/([^{}]+)\{([^}]*)\}/', $css, $matches, PREG_SET_ORDER ) ) {
					foreach ( $matches as $match ) {
						$selector = trim( preg_replace( '/\s+/', ' ', $match[1] ) );
						$decls    = array_values(
							array_filter(
								array_map( 'trim', explode( ';', $match[2] ) ),
								static fn( $decl ) => '' !== $decl
							)
						);

						$blocks[] = array(
							'selector'     => $selector,
							'specificity'  => bdox_pack_specificity( explode( ',', $selector )[0] ),
							'declarations' => $decls,
						);
					}
				}

				$elements[ $slug ] = array(
					'default_css' => trim( preg_replace( '/\s+/', ' ', $css ) ),
					'blocks'      => $blocks,
				);
			}

			return array(
				'elements'       => $elements ?: (object) array(),
				'override_rules' => $rules,
				'cascade_note'   => bdox_pack_cascade_note(),
			);
		},
	)
);

/**
 * The load order that decides every one of these fights.
 *
 * @return string
 */
function bdox_pack_cascade_note(): string {
	return 'Front-end load order: post-<id>-defaults.css (element defaults) -> global-settings.css -> selectors.css (your classes) -> variables.css -> post-<id>.css (Design tab, per element). '
		. 'At equal specificity the later file wins, so Design-tab values beat your classes and your classes beat element defaults. '
		. 'A default with HIGHER specificity than a flat class — .bde-section .section-container (0,2,0) or .bde-text-link > * (0,1,1) — wins regardless of order, and those are the ones that must be handled through global settings or the Design tab rather than CSS.';
}
