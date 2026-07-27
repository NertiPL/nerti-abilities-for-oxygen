<?php
/**
 * CSS cache regeneration and non-destructive selector editing.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/regenerate-css',
		'label'            => 'Regenerate Compiled CSS',
		'description'      => 'Recompile the builder\'s CSS files. Run this after editing element trees or global settings directly, and whenever the front end does not match what the builder shows — a stale cache is the single most common cause of "the plugin update broke the site", because the compiled files can predate a plugin update and still carry its old default CSS. Pass post_ids to limit the run, omit it to do every builder post. Returns the resulting file sizes so you can confirm the compile actually produced output: a 0-byte variables.css or a missing post-<id>.css means the run did not work.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'post_ids'        => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'description' => 'Builder post IDs to recompile. Omit to recompile every post that has builder data.',
				),
				'global_settings' => array(
					'type'        => 'boolean',
					'description' => 'Also recompile global-settings.css, selectors.css and variables.css. Default true.',
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'regenerated_posts' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'global_settings'   => array( 'type' => 'boolean' ),
				'css_files'         => array( 'type' => 'object' ),
			),
		),
		'annotations'      => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			$ids = isset( $input['post_ids'] ) && is_array( $input['post_ids'] )
				? $input['post_ids']
				: bdox_pack_builder_post_ids();

			$done = bdox_pack_regenerate_posts( $ids );

			$do_global = ! array_key_exists( 'global_settings', $input ) || (bool) $input['global_settings'];
			if ( $do_global && function_exists( 'Breakdance\\Render\\generateCacheForGlobalSettings' ) ) {
				\Breakdance\Render\generateCacheForGlobalSettings();
			}

			return array(
				'regenerated_posts' => $done,
				'global_settings'   => $do_global,
				'css_files'         => bdox_pack_css_file_sizes(),
			);
		},
	)
);

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/merge-css-selector',
		'label'            => 'Merge CSS Selector Properties',
		'description'      => 'Merge properties into an existing global selector instead of replacing it. The built-in insert-stylesheet REPLACES a selector\'s whole property map, so importing a rule that changes one property silently drops every other property that selector had, including its other breakpoints — the most common source of regressions when editing an existing design system. This ability deep-merges per breakpoint: only the keys you pass change. Pass properties keyed by breakpoint id (breakpoint_base, breakpoint_tablet_landscape, breakpoint_tablet_portrait, breakpoint_phone_landscape, breakpoint_phone_portrait). Read the current shape with the built-in get-css-selectors using include_properties first. ALSO THE ONLY WAY TO GET !important INTO A SELECTOR: insert-stylesheet strips !important from every declaration before it routes them, so it is lost whether the property lands in a typed slot or in custom_css. Measured 2026-07-27 on Oxygen 6.2.0-beta.2 with "will-change: transform !important" — an untyped property that fell through to custom_css untouched by any typed handler and still lost the !important, which places the strip BEFORE routing rather than inside it. The compiled selectors.css of a whole production site contained zero !important. Writing the custom_css slot through this ability bypasses that parser: pass properties {"breakpoint_base":{"custom_css":{"custom_css":":selector { color: #abc !important }"}}} and the !important survives into the compiled CSS. The same route preserves custom property NAMES containing capital letters, which insert-stylesheet lowercases (--myVar is stored as --myvar, so every var(--myVar) reference dies silently while the import still reports success). Note the custom_css block compiles to a SECOND rule carrying the same selector, emitted after the typed one, so at equal specificity it also wins on order.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'name'       => array(
					'type'        => 'string',
					'description' => 'Selector name as stored, without the leading dot for classes (e.g. "card", or "body.breakdance .card" for a custom selector).',
				),
				'id'         => array(
					'type'        => 'string',
					'description' => 'Selector id. Takes precedence over name when both are given.',
				),
				'properties' => array(
					'type'        => 'object',
					'description' => 'Breakpoint-keyed property tree to merge in, same shape as get-css-selectors returns.',
				),
				'regenerate' => array(
					'type'        => 'boolean',
					'description' => 'Recompile selectors.css afterwards. Default true.',
				),
			),
			'required'             => array( 'properties' ),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'id'         => array( 'type' => 'string' ),
				'name'       => array( 'type' => 'string' ),
				'properties' => array( 'type' => 'object' ),
			),
		),
		'annotations'      => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			if ( ! function_exists( 'Breakdance\\BreakdanceOxygen\\Selectors\\getOxySelectors' )
				|| ! function_exists( 'breakdance_mcp_save_selectors' ) ) {
				return new WP_Error( 'bdox_selectors_unavailable', 'Selector storage is not available on this build.', array( 'status' => 500 ) );
			}

			$selectors = \Breakdance\BreakdanceOxygen\Selectors\getOxySelectors();
			$target    = null;

			foreach ( $selectors as $index => $selector ) {
				$matches = isset( $input['id'] )
					? ( ( $selector['id'] ?? null ) === $input['id'] )
					: ( isset( $input['name'] ) && ( $selector['name'] ?? null ) === $input['name'] );

				if ( $matches ) {
					$target = $index;
					break;
				}
			}

			if ( null === $target ) {
				return new WP_Error(
					'bdox_selector_not_found',
					'No selector matched. List them with get-css-selectors; class names are stored without the leading dot.',
					array( 'status' => 404 )
				);
			}

			$selectors[ $target ]['properties'] = bdox_pack_deep_merge(
				$selectors[ $target ]['properties'] ?? array(),
				$input['properties']
			);

			$collections = function_exists( 'Breakdance\\BreakdanceOxygen\\Selectors\\getOxySelectorsCollections' )
				? \Breakdance\BreakdanceOxygen\Selectors\getOxySelectorsCollections()
				: null;

			breakdance_mcp_save_selectors( $selectors, $collections );

			if ( ( ! array_key_exists( 'regenerate', $input ) || (bool) $input['regenerate'] )
				&& function_exists( 'Breakdance\\Render\\generateCacheForGlobalSettings' ) ) {
				\Breakdance\Render\generateCacheForGlobalSettings();
			}

			return array(
				'id'         => (string) ( $selectors[ $target ]['id'] ?? '' ),
				'name'       => (string) ( $selectors[ $target ]['name'] ?? '' ),
				'properties' => $selectors[ $target ]['properties'],
			);
		},
	)
);
