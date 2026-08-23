<?php
/**
 * Non-destructive global-settings patching.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

bdox_pack_ability(
	array(
		'name'             => 'bdox/get-global-settings',
		'label'            => 'Read Global Settings',
		'description'      => 'Reads the global settings straight from storage, with no output schema in the way. The built-in oxygen/get-global-settings validates its own result and still fails on data the builder itself wrote. Re-measured 2026-08-21 on Oxygen 6.2.0-beta.5: a site whose primary button has a custom corner radius returns "output[settings][buttons][primary][corner_radius][unit] is not of type object,null" and you get NOTHING back — not even the parts that validate. The cause is a shape mismatch inside Oxygen: the output schema declares buttons.primary.corner_radius as a breakpoint-keyed map, but the builder stores it flat as {number, unit, style} (buttons.button_presets[].styles.corner_radius IS breakpoint-keyed, so the same control is stored two different ways). Soflyy did fix the empty-settings case in beta.5 — an install with no settings now returns {"settings":{}} instead of failing — so on a blank site the built-in tool works and this one is redundant. On any real site it is not. Pass "path" to fetch one branch (dot notation, e.g. "colors.palette.colors" or "typography.global_typography.typography_presets") instead of several hundred kilobytes.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'path' => array(
					'type'        => 'string',
					'description' => 'Optional dot-separated path inside "settings". Numeric segments index into lists. Returns null when the path does not exist.',
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'path'     => array( 'type' => array( 'string', 'null' ) ),
				'settings' => array(),
			),
		),
		'annotations'      => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			if ( ! function_exists( 'Breakdance\\Data\\get_global_settings_array' ) ) {
				return new WP_Error( 'bdox_settings_unavailable', 'Global settings storage is not available on this build.', array( 'status' => 500 ) );
			}

			$current = \Breakdance\Data\get_global_settings_array();
			$value   = is_array( $current ) && isset( $current['settings'] ) && is_array( $current['settings'] )
				? $current['settings']
				: array();

			$path = isset( $input['path'] ) ? trim( (string) $input['path'] ) : '';
			if ( '' !== $path ) {
				foreach ( explode( '.', $path ) as $segment ) {
					if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
						$value = null;
						break;
					}
					$value = $value[ $segment ];
				}
			}

			return array(
				'path'     => '' === $path ? null : $path,
				'settings' => $value,
			);
		},
	)
);

bdox_pack_ability(
	array(
		'name'             => 'bdox/patch-global-settings',
		'label'            => 'Patch Global Settings',
		'description'      => 'Writes global settings that the built-in oxygen/set-global-settings refuses. USE THE BUILT-IN TOOL FIRST — since Oxygen 6.2.0-beta.5 it deep-merges on its own (breakdance_mcp_deep_merge_settings in mcp/tools/global-settings.php), so the old reason for this ability is gone: measured 2026-08-21, sending only typography.base_font_size left colors.palette and typography.body_font untouched. The one thing it still cannot do is write a key its input schema does not declare on an object closed with additionalProperties:false. Measured on the same version: buttons.primary.background_hover goes through fine (that level is open), but buttons.primary.typography.color_hover is rejected with "color_hover is not a valid property of object" — and elements/macros/atom-v1-button-css.twig reads exactly that key for the button hover text colour. Same for typography.color_hover on any nested typography object. Come here for those keys, and for anything else the schema rejects while the renderer reads it. The other confirmed case is typography.base_size: the built-in input schema demands a breakpoint map, which validates and does nothing because the twig reads .style, a key the map has not got — send the flat {number,unit,style} through here instead. Measured 2026-08-21 on 6.2.0-beta.5. Note the follow-on: once base_size is stored flat, later oxygen/set-global-settings calls fail their OUTPUT validation ("base_size[number] is not of type object,null") even though the write went through — read state back with bdox/get-global-settings, not the built-in. Recompiles global-settings.css afterwards unless you disable it.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'settings'   => array(
					'type'        => 'object',
					'description' => 'Partial settings tree, same shape as get-global-settings returns under "settings". Only the keys you pass are touched. Lists (stylesheets, scripts, button_presets, palette colors) are replaced wholesale, not merged item by item — read the current list, change it, send it back complete.',
				),
				'regenerate' => array(
					'type'        => 'boolean',
					'description' => 'Recompile global-settings.css afterwards. Default true.',
				),
			),
			'required'             => array( 'settings' ),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'settings'      => array( 'type' => 'object' ),
				'changed_paths' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'annotations'      => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			if ( ! function_exists( 'Breakdance\\Data\\save_global_settings' ) ) {
				return new WP_Error( 'bdox_settings_unavailable', 'Global settings storage is not available on this build.', array( 'status' => 500 ) );
			}

			$current  = \Breakdance\Data\get_global_settings_array();
			$settings = is_array( $current ) && isset( $current['settings'] ) && is_array( $current['settings'] )
				? $current['settings']
				: array();

			$merged = bdox_pack_deep_merge( $settings, $input['settings'] );

			$json = wp_json_encode( array( 'settings' => $merged === array() ? new stdClass() : $merged ) );
			if ( false === $json ) {
				return new WP_Error( 'bdox_settings_encode_failed', 'The merged settings could not be encoded.', array( 'status' => 500 ) );
			}

			\Breakdance\Data\save_global_settings( $json );

			if ( ( ! array_key_exists( 'regenerate', $input ) || (bool) $input['regenerate'] )
				&& function_exists( 'Breakdance\\Render\\generateCacheForGlobalSettings' ) ) {
				\Breakdance\Render\generateCacheForGlobalSettings();
			}

			return array(
				'settings'      => $merged,
				'changed_paths' => array_keys( (array) $input['settings'] ),
			);
		},
	)
);
