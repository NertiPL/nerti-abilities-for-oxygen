<?php
/**
 * Site setup report and builder element visibility.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/setup-report',
		'label'            => 'Builder Setup Report',
		'description'      => 'One call that answers "what state is this install actually in" before you change anything. Reports builder and element-plugin versions, registered breakpoints, which global-settings groups exist (containers, colors, palette, buttons, presets — a site missing these has its design system in CSS classes instead, which the client cannot edit), the compiled CSS file sizes, and a stale-cache verdict. The stale-cache check is the important one: it compares the ".breakdance " prefix count in the element plugin SOURCE against the compiled cache. Source 0 with compiled above 0 means the cache predates a plugin update and the site is rendering old default CSS — the fix is bdox/regenerate-css, not a CSS workaround or a plugin patch.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'versions'         => array( 'type' => 'object' ),
				'breakpoints'      => array( 'type' => 'array' ),
				'global_settings'  => array( 'type' => 'object' ),
				'css_files'        => array( 'type' => 'object' ),
				'stale_cache'      => array( 'type' => 'object' ),
				'builder_posts'    => array( 'type' => 'integer' ),
			),
		),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$versions = array();
			foreach ( get_plugins() as $file => $data ) {
				if ( false !== stripos( $file, 'oxygen' ) || false !== stripos( $file, 'breakdance' ) ) {
					$versions[ $file ] = $data['Version'] ?? '';
				}
			}
			$versions['php']       = PHP_VERSION;
			$versions['wordpress'] = get_bloginfo( 'version' );

			$settings = \Breakdance\Data\get_global_settings_array();
			$settings = is_array( $settings ) && isset( $settings['settings'] ) ? $settings['settings'] : array();

			$global = array(
				'containers'      => isset( $settings['containers'] ),
				'colors'          => isset( $settings['colors'] ),
				'palette_colors'  => count( (array) ( $settings['colors']['palette']['colors'] ?? array() ) ),
				'buttons'         => isset( $settings['buttons'] ),
				'button_presets'  => count( (array) ( $settings['buttons']['button_presets']['button_presets'] ?? array() ) ),
				'typography_presets' => count( (array) ( $settings['typography']['global_typography']['typography_presets'] ?? array() ) ),
				'stylesheets'     => count( (array) ( $settings['code']['stylesheets'] ?? array() ) ),
				'scripts'         => count( (array) ( $settings['code']['scripts'] ?? array() ) ),
			);

			// Stale-cache gate: measure the plugin SOURCE, then the compiled cache.
			//
			// Count ONLY the blanket prefix on a bare element class — ".breakdance
			// .bde-thing {". That pattern is the regression signature: it makes every
			// element default (0,2,0) and therefore unbeatable by a flat project class.
			// Compound rules such as ".breakdance .bde-header-builder.bde-…--sticky .x"
			// are deliberate and always present; counting them reported a healthy site
			// as stale, which is worse than not checking at all.
			$blanket = '/\.breakdance\s+\.bde-[\w-]+\s*\{/';

			$source_prefixed = 0;
			foreach ( array( 'breakdance-elements-for-oxygen', 'oxygen' ) as $plugin_slug ) {
				$plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
				if ( ! is_dir( $plugin_dir ) ) {
					continue;
				}
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS )
				);
				foreach ( $iterator as $file ) {
					if ( 'default.css' === $file->getBasename() ) {
						$source_prefixed += preg_match_all( $blanket, (string) file_get_contents( $file->getPathname() ) );
					}
				}
			}

			$compiled_file     = WP_CONTENT_DIR . '/uploads/oxygen/css/elements.css';
			$compiled_prefixed = file_exists( $compiled_file )
				? preg_match_all( $blanket, (string) file_get_contents( $compiled_file ) )
				: null;

			if ( null === $compiled_prefixed ) {
				$verdict = 'no-compiled-elements-css';
			} elseif ( 0 === $source_prefixed && $compiled_prefixed > 0 ) {
				$verdict = 'STALE — compiled cache predates the plugin. Run bdox/regenerate-css before diagnosing anything else.';
			} elseif ( $source_prefixed > 0 ) {
				$verdict = 'Prefixed defaults are real on this version: element defaults are (0,2,0) and beat flat per-element CSS (0,1,0).';
			} else {
				$verdict = 'OK — source and cache agree, defaults are unprefixed.';
			}

			$breakpoints = array();
			if ( function_exists( 'Breakdance\\Config\\Breakpoints\\get_breakpoints' ) ) {
				$breakpoints = \Breakdance\Config\Breakpoints\get_breakpoints();
			}

			return array(
				'versions'        => $versions,
				'breakpoints'     => $breakpoints,
				'global_settings' => $global,
				'css_files'       => bdox_pack_css_file_sizes(),
				'stale_cache'     => array(
					'source_prefixed_defaults'   => $source_prefixed,
					'compiled_prefixed_defaults' => $compiled_prefixed,
					'verdict'                    => $verdict,
				),
				'builder_posts'   => count( bdox_pack_builder_post_ids() ),
			);
		},
	)
);

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/builder-hidden-elements',
		'label'            => 'Builder Hidden Elements',
		'description'      => 'Read or change which elements the Oxygen editor offers in its "add element" panel. Oxygen ships with ten Essential Elements hidden because they duplicate native Oxygen ones — Section, Div, Heading, Text, TextLink, WrapperLink, Icon, RichText, CodeBlock, Image2 — and those are exactly the ones a client-editable build needs, because only the Essential versions have a Design tab. Pass unhide_all to reveal them. IMPORTANT: saving an EMPTY list does nothing lasting — the builder rebuilds the default hidden list on the next request whenever the option is empty, so unhiding works by storing a non-empty placeholder list instead. This option controls the editor panel only; it is a different mechanism from the MCP visibility this pack fixes with a filter, and it never affects how already-placed elements render.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'unhide_all' => array(
					'type'        => 'boolean',
					'description' => 'Reveal every default-hidden element in the editor panel.',
				),
				'hidden'     => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Explicit list of element classnames to hide. Ignored when unhide_all is true.',
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'hidden'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'changed'        => array( 'type' => 'boolean' ),
				'builder_panel'  => array( 'type' => 'integer' ),
			),
		),
		'annotations'      => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$guard = bdox_pack_guard();
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}

			$option  = 'oxygen_builder_hidden_elements';
			$current = get_option( $option );
			$changed = false;

			if ( ! empty( $input['unhide_all'] ) ) {
				// A non-empty placeholder: an empty list is repopulated with the
				// defaults on the next request, so it would silently re-hide.
				$new = array( 'EssentialElements\\MissingElement' );
			} elseif ( isset( $input['hidden'] ) && is_array( $input['hidden'] ) ) {
				$new = array_values( array_filter( $input['hidden'], 'is_string' ) );
				if ( array() === $new ) {
					$new = array( 'EssentialElements\\MissingElement' );
				}
			} else {
				$new = null; // Read-only call.
			}

			if ( null !== $new ) {
				$encoded = wp_json_encode( $new );
				if ( $encoded !== $current ) {
					update_option( $option, $encoded );
					$changed = true;
				}
			}

			$stored = get_option( $option );
			$list   = is_string( $stored ) ? json_decode( $stored, true ) : $stored;

			$panel = 0;
			if ( function_exists( 'Breakdance\\Elements\\get_elements_for_builder' ) ) {
				$panel = count( \Breakdance\Elements\get_elements_for_builder() );
			}

			return array(
				'hidden'        => is_array( $list ) ? $list : array(),
				'changed'       => $changed,
				'builder_panel' => $panel,
			);
		},
	)
);
