<?php
/**
 * Non-destructive global-settings patching.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/get-global-settings',
		'label'            => 'Read Global Settings',
		'description'      => 'Reads the global settings straight from storage, with no output schema in the way. The built-in oxygen/get-global-settings validates its result and fails outright on perfectly valid data — a gradient button background returns "output[settings][buttons][primary][background] is not of type string" and you get nothing back, so you cannot even see what you are about to patch. This returns the tree as stored. Pass "path" to fetch one branch (dot notation, e.g. "colors.palette.colors" or "typography.global_typography.typography_presets") instead of several hundred kilobytes.',
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

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/patch-global-settings',
		'label'            => 'Patch Global Settings',
		'description'      => 'Merge a partial settings object into the global settings instead of replacing the whole tree. The built-in set-global-settings persists exactly what you send, so posting only "colors" wipes your global stylesheets, scripts and typography presets. This ability deep-merges, so you send only what changes. It also bypasses the built-in tool\'s input schema, which is generated from the responsive button-design controls and therefore omits keys the renderer actually reads (background_hover, color_hover, typography.color_hover) — send them here and they persist. Recompiles global-settings.css afterwards unless you disable it.',
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
