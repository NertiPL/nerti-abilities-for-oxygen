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
		'description'      => 'One call that answers "what state is this install actually in" before you change anything. Reports builder and element-plugin versions, registered breakpoints, which global-settings groups exist (containers, colors, palette, buttons, presets — a site missing these has its design system in CSS classes instead, which the client cannot edit), the compiled CSS file sizes, the typography as the browser actually resolves it, and a stale-cache verdict. Typography is read from the compiled global-settings.css on purpose: a Google Font slug drops every non-alphanumeric character rather than hyphenating it, so "gfont-bricolage-grotesque" is stored and emitted verbatim as the font-family while set-global-settings reports success — the verdict says unresolved_font_slug when that happened. typography.base_size has the same shape: only the flat form takes effect, so the stored value proves nothing and the compiled file does. The stale-cache check is the important one: it compares the ".breakdance " prefix count in the element plugin SOURCE against the compiled cache. Source 0 with compiled above 0 means the cache predates a plugin update and the site is rendering old default CSS — the fix is bdox/regenerate-css, not a CSS workaround or a plugin patch.',
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

			// Typography reported from the COMPILED css, not from the stored settings.
			//
			// Both of these fail silently when written. A Google Font slug is
			// strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $family)) — every
			// non-alphanumeric is dropped, not hyphenated — so "gfont-bricolage-grotesque"
			// is accepted, stored, and emitted verbatim as the font-family. And
			// typography.base_size only takes effect when stored flat; the shape
			// set-global-settings demands validates and does nothing, because the twig
			// reads .style, which a breakpoint map has not got. Reading the stored value
			// therefore proves nothing — only the compiled file does.
			$global['typography_compiled'] = bdox_pack_compiled_typography();

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
		'description'      => 'Read or change the oxygen_builder_hidden_elements option — the list a human edits under Oxygen → Settings → Elements. Oxygen pre-fills it with ten Essential Elements on install (Section, Div, Heading, Text, TextLink, WrapperLink, Icon, RichText, CodeBlock, Image2), which are exactly the ones a client-editable build needs: measured 2026-08-21 with oxygen-get-element-schemas on Oxygen 6.2.0-beta.5, EssentialElements Section/Heading/Text/Image2/Button all expose a `design` section while the native OxygenElements Container/Text/Image expose none, so only the Essential versions give the client a Design tab. Pass unhide_all to clear the list. WARNING — THIS OPTION IS NOT EDITOR-ONLY: admin/settings-page/tabs/elements.php subtracts it on the breakdance_builder_elements filter, and Oxygen runs the MCP element list through that same filter, so anything here also vanishes from oxygen-get-element-slugs and oxygen-get-element-schemas. Measured: adding EssentialElements\\Button took get-element-slugs from 164 to 163 and made get-element-schemas refuse it. Hide elements only AFTER the agent has finished building. The response therefore reports BOTH sources of truth: the stored option and the element count MCP can actually see. Note on the empty list: it DOES persist across normal requests on 6.2.0-beta.5 (measured — set to [], reloaded, still []). It is repopulated by addDefaultHiddenElements(), which the guard `count($current) > 0` only lets through when the list is empty AND the caller runs — the setup wizard, or handleVersionChange() on the next Oxygen update. So an empty list survives until Oxygen is updated, then silently re-hides all ten. This ability stores a harmless non-empty placeholder instead, so the unhide survives updates. FINALLY, WHILE THIS PACK IS ACTIVE, HIDING ONE OF THE 13 ELEMENTS IT RESTORES DOES NOTHING: the pack re-adds them on the same filter at priority 20, after the settings page subtracts at priority 10. To actually hide them for the client, narrow the pack\'s own list first — add_filter( "bdox_pack_restored_elements", "__return_empty_array" ) — or deactivate the pack. Handover order: build, then hide, then narrow.',
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
				'hidden'            => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'changed'           => array( 'type' => 'boolean' ),
				'mcp_visible_count' => array( 'type' => 'integer' ),
				'hidden_from_mcp'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
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
				// A non-empty placeholder. An empty list persists across normal
				// requests, but addDefaultHiddenElements() re-fills it whenever it
				// runs on an empty option — setup wizard, or handleVersionChange()
				// on the next Oxygen update — so the unhide would silently revert.
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

			// The second source of truth. get_elements_for_builder() is useless here:
			// it maps over every registered classname and only feeds the filtered list
			// to addPanelRules, so its count does not move when the option changes
			// (measured: 165 before and after hiding an element). The MCP list does.
			$visible = function_exists( 'breakdance_mcp_visible_element_classnames' )
				? breakdance_mcp_visible_element_classnames()
				: array();

			$registered = function_exists( 'Breakdance\\Elements\\get_element_classnames' )
				? \Breakdance\Elements\get_element_classnames()
				: array();

			return array(
				'hidden'            => is_array( $list ) ? $list : array(),
				'changed'           => $changed,
				'mcp_visible_count' => count( $visible ),
				'hidden_from_mcp'   => array_values( array_diff( $registered, $visible ) ),
			);
		},
	)
);
