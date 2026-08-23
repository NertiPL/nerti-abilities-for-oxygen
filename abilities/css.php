<?php
/**
 * CSS cache regeneration and non-destructive selector editing.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

bdox_pack_ability(
	array(
		'name'             => 'bdox/regenerate-css',
		'label'            => 'Regenerate Compiled CSS',
		'description'      => 'Recompile the builder\'s CSS files. Oxygen still ships no MCP tool for this — checked 2026-08-21 against the 49 tools of the 6.2.0-beta.5 server. Its own save paths do regenerate (data/save.php calls generateCacheForPost on a document save and generateCacheForGlobalSettings on a settings or selector save), so you need this only when the data changed outside those paths, or when the compiled files are simply stale. Run it after editing element trees or global settings directly, and whenever the front end does not match what the builder shows — a stale cache is the single most common cause of "the plugin update broke the site", because the compiled files can predate a plugin update and still carry its old default CSS. Pass post_ids to limit the run, omit it to do every builder post. Returns the resulting file sizes so you can confirm the compile actually produced output: a 0-byte variables.css or a missing post-<id>.css means the run did not work.',
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

bdox_pack_ability(
	array(
		'name'             => 'bdox/merge-css-selector',
		'label'            => 'Merge CSS Selector Properties',
		'description'      => 'Merge properties into an existing global selector instead of replacing it. The built-in insert-stylesheet REPLACES a selector\'s whole property map for the breakpoint it touches, so importing a rule that changes one property silently drops every other property that selector had — still true on Oxygen 6.2.0-beta.5, measured 2026-08-21: ".probe{margin:4px}" imported over a selector holding padding plus a custom_css block left only the margin. The most common source of regressions when editing an existing design system. This ability deep-merges per breakpoint: only the keys you pass change. Pass properties keyed by breakpoint id (breakpoint_base, breakpoint_tablet_landscape, breakpoint_tablet_portrait, breakpoint_phone_landscape, breakpoint_phone_portrait). Read the current shape with the built-in get-css-selectors using include_properties first. !important NO LONGER NEEDS THIS ABILITY. Oxygen fixed it in 6.2.0: mcp/design/css-to-properties.php now diverts every !important declaration straight into custom_css with the flag intact, before the typed converters see it. Measured 2026-08-21 on 6.2.0-beta.5 — insert-stylesheet with ".probe{color:#ff0000 !important;padding:8px}" stored padding as a typed property and custom_css as ":selector {\n  color: #f00 !important;\n}". So write !important with the plain built-in tool. What you DO still need this ability for, besides the merge: insert-stylesheet lowercases custom property NAMES. Measured on the same version, ".probe{--myVar:4px;padding:var(--myVar)}" is stored as "--myvar: 4px; padding: var(--myVar)" — the declaration is lowercased, the reference is not, every var(--myVar) dies silently and the import still reports success. Writing custom_css through this ability bypasses that parser. Note the custom_css block compiles to a SECOND rule carrying the same selector, emitted after the typed one, so at equal specificity it also wins on order.',
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
