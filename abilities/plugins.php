<?php
/**
 * What to install on a WooCommerce build, what is already here, and what must
 * not be switched on while an agent is working.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * The catalogue.
 *
 * `match` is a case-insensitive substring tested against the installed plugins'
 * NAMES rather than their folder slugs. Slugs differ between the free and paid
 * build of the same plugin and between Polish resellers of the same gateway;
 * the display name is the stable half.
 *
 * @return array<int, array<string, string>>
 */
function bdox_pack_toolbox_catalog(): array {
	return array(
		// --- the stack itself -------------------------------------------------
		array(
			'name'  => 'WooCommerce',
			'match' => 'woocommerce',
			'group' => 'fundament',
			'role'  => 'The store: products, cart, checkout, orders, taxes, shipping zones.',
			'when'  => 'Always, first. Every woo element and template type only exists while it is active.',
		),
		array(
			'name'  => 'Oxygen',
			'match' => 'oxygen',
			'group' => 'fundament',
			'role'  => 'The builder and its MCP server.',
			'when'  => 'Always.',
		),
		array(
			'name'  => 'Breakdance Elements for Oxygen',
			'match' => 'breakdance elements for oxygen',
			'group' => 'fundament',
			'role'  => 'The element library with real Design tabs, plus global settings.',
			'when'  => 'Always. Without it the native Oxygen elements carry no design section and nothing can be styled from the UI — which is the whole reason a client can maintain their own site.',
		),
		array(
			'name'  => 'Breakdance WooCommerce for Oxygen',
			'match' => 'breakdance woocommerce for oxygen',
			'group' => 'fundament',
			'role'  => 'The store elements: shop, product, cart, checkout, account, filters — each with its own Design tab.',
			'when'  => 'Every store. Without it the shop can only be built from shortcodes and nothing about it is designable. See bdox/woo-map.',
		),
		array(
			'name'  => 'Agent Connector for WP',
			'match' => 'agent connector',
			'group' => 'fundament',
			'role'  => 'Exposes abilities over MCP, owns authentication, permissions and the audit trail, and sandboxes any PHP an agent writes.',
			'when'  => 'Whenever an agent works on the site.',
		),

		// --- before you touch anything ---------------------------------------
		array(
			'name'  => 'UpdraftPlus',
			'match' => 'updraftplus',
			'group' => 'kopia zapasowa',
			'role'  => 'Backups of database and files, with remote storage.',
			'when'  => 'BEFORE the first writing change on a site you did not build. With the updraft ability pack installed this is updraft/backup-now then updraft/list-backups until the entry appears. Without it, an agent has no honest way to promise a rollback point.',
		),

		// --- content ----------------------------------------------------------
		array(
			'name'  => 'WP All Import',
			'match' => 'wp all import',
			'group' => 'tresc',
			'role'  => 'Imports products, categories, images and custom fields from CSV/XML, with scheduled re-runs.',
			'when'  => 'A catalogue that comes from the client\'s ERP or wholesaler, and any store above a few dozen products. The WooCommerce add-on maps variations, attributes and stock.',
		),
		array(
			'name'  => 'Secure Custom Fields',
			'match' => 'secure custom fields',
			'group' => 'tresc',
			'role'  => 'Custom fields, post types and taxonomies (the fork of ACF that ships from WordPress).',
			'when'  => 'Product data the shop needs but WooCommerce has no field for — technical specs, downloads, a catalogue number. Bind it into the layout with oxygen/get-dynamic-fields.',
		),
		array(
			'name'  => 'WP Dummy Content Generator',
			'match' => 'dummy content',
			'group' => 'tresc',
			'role'  => 'Throwaway posts and products.',
			'when'  => 'Building a template before the real catalogue exists. Remove the dummies before handover.',
		),
		array(
			'name'  => 'Duplicate Page',
			'match' => 'duplicate page',
			'group' => 'tresc',
			'role'  => 'Clones a page or template with its builder data.',
			'when'  => 'Trying a layout variant without risking the original.',
		),

		// --- finding things ---------------------------------------------------
		array(
			'name'  => 'FiboSearch',
			'match' => 'fibosearch',
			'group' => 'szukanie i filtry',
			'role'  => 'AJAX product search with thumbnails, prices and category scoping.',
			'when'  => 'Any catalogue where the visitor arrives knowing what they want. The single highest-conversion addition to a store header.',
		),
		array(
			'name'  => 'FacetWP',
			'match' => 'facetwp',
			'group' => 'szukanie i filtry',
			'role'  => 'Paid faceted filtering across any field or taxonomy, with its own builder element.',
			'when'  => 'Filters the built-in Woofacetedfilters element cannot express — filtering on custom fields, cross-post-type facets, multi-facet URLs.',
		),
		array(
			'name'  => 'WP Grid Builder',
			'match' => 'grid builder',
			'group' => 'szukanie i filtry',
			'role'  => 'Paid faceting plus grid layouts.',
			'when'  => 'Same brief as FacetWP; pick one, never both.',
		),

		// --- visibility -------------------------------------------------------
		array(
			'name'  => 'Rank Math SEO',
			'match' => 'rank math',
			'group' => 'widocznosc',
			'role'  => 'Titles, meta, schema, sitemaps, redirects; product and shop schema out of the box.',
			'when'  => 'Every store, from day one. Its own abilities (rank-math/*) audit and fix on this install.',
		),
		array(
			'name'  => 'Google Site Kit',
			'match' => 'site kit',
			'group' => 'widocznosc',
			'role'  => 'Search Console, Analytics and Tag Manager wiring from inside WordPress.',
			'when'  => 'Launch day, so the store starts collecting data from its first visitor rather than from whenever someone remembers.',
		),

		// --- talking to customers --------------------------------------------
		array(
			'name'  => 'Forminator',
			'match' => 'forminator',
			'group' => 'kontakt',
			'role'  => 'Forms, quizzes and calculators with submission storage.',
			'when'  => 'Anything past the built-in form element: multi-step enquiries, quote calculators, file uploads.',
		),
		array(
			'name'  => 'Ecomail',
			'match' => 'ecomail',
			'group' => 'kontakt',
			'role'  => 'Newsletter and automation, with WooCommerce order sync.',
			'when'  => 'Abandoned-cart and post-purchase mail. Note the consent trap: its tracking script must not fire before the visitor accepts marketing cookies.',
		),

		// --- money and parcels (PL) ------------------------------------------
		array(
			'name'  => 'Przelewy24 / PayU / Autopay / Stripe',
			'match' => 'przelewy24|payu|autopay|stripe|tpay',
			'group' => 'platnosci',
			'role'  => 'Payment gateway: BLIK, cards, bank transfers, deferred payment.',
			'when'  => 'Before the store can take a single order. Pick the one whose contract the client already has; installing a second gateway only adds a second thing to reconcile.',
		),
		array(
			'name'  => 'InPost / Paczkomaty',
			'match' => 'inpost|paczkomat',
			'group' => 'wysylka',
			'role'  => 'Parcel-locker picker in checkout plus label generation.',
			'when'  => 'A Polish store shipping physical goods. The locker map inside checkout is what customers expect; without it they type an address they will not use.',
		),
		array(
			'name'  => 'Furgonetka / BaseLinker',
			'match' => 'furgonetka|baselinker',
			'group' => 'wysylka',
			'role'  => 'Multi-carrier shipping broker / order-management hub across marketplaces.',
			'when'  => 'More than one carrier, or the client already sells on Allegro and wants one order queue.',
		),
		array(
			'name'  => 'Fakturownia / wFirma / iFirma',
			'match' => 'fakturownia|wfirma|ifirma',
			'group' => 'faktury',
			'role'  => 'Issues invoices from WooCommerce orders and files them in the client\'s accounting system.',
			'when'  => 'A Polish store selling B2B, or any store whose accountant asks where the invoices are. Pick the one the accountant already uses.',
		),

		// --- weight and speed -------------------------------------------------
		array(
			'name'  => 'Imsanity',
			'match' => 'imsanity',
			'group' => 'media',
			'role'  => 'Downsizes oversized uploads on the way in.',
			'when'  => 'Any site where the client uploads photos straight off a phone or a camera. Set it before the first bulk import, not after.',
		),
		array(
			'name'  => 'WebP Express',
			'match' => 'webp',
			'group' => 'media',
			'role'  => 'Serves WebP versions of existing JPEG/PNG uploads.',
			'when'  => 'A catalogue with hundreds of product photos, where the image weight is the page weight.',
		),
		array(
			'name'  => 'WPCode',
			'match' => 'wpcode|insert headers',
			'group' => 'kod',
			'role'  => 'Snippet manager with per-page conditions, instead of edits to a theme file.',
			'when'  => 'Tracking pixels, small woo hooks, anything that must survive a theme or builder update. Snippets live in the database, so they also survive a redeploy.',
		),

		// --- the law ----------------------------------------------------------
		array(
			'name'  => 'nerti-cookies',
			'match' => 'nerti cookies|nerti-cookies',
			'group' => 'zgody i prawo',
			'role'  => 'Consent banner that blocks scripts BEFORE consent, with a consent log tied to the policy version.',
			'when'  => 'Any store with analytics, a pixel, an embedded map or video — which is every store. Install before the first tracking script, because a banner added afterwards is a banner that has already leaked.',
		),
	);
}

/**
 * Things that must not be switched on while an agent is working, and why.
 *
 * @return string[]
 */
function bdox_pack_toolbox_warnings(): array {
	return array(
		'all-in-one-wp-security-and-firewall — its firewall can block the REST API, which is the agent\'s only '
			. 'way in. Switching it on mid-build ends the session and the symptom (every tool call fails at once) '
			. 'looks like the connector broke.',
		'litespeed-cache / WP Rocket / any page cache — the front end then serves the previous version of the '
			. 'page, so a change that landed correctly reads as a change that failed, and the next move is '
			. 'usually to "fix" something that was never broken. Turn page caching on at handover, after the '
			. 'last bdox/regenerate-css.',
		'A second faceting plugin, a second gateway, a second SEO plugin — two plugins competing for the same '
			. 'output produce a bug that only appears on some URLs and cannot be reproduced from the settings screens.',
	);
}

bdox_pack_ability(
	array(
		'name'             => 'bdox/store-toolbox',
		'label'            => 'Store Plugin Toolbox',
		'description'      => 'Which plugins a WooCommerce build on this stack actually needs, what each one is for, when it earns its place — and, checked live against this install, which of them are here, which are installed but switched off, and which are missing. Call it when planning a store, when the client asks "what else do we need", when something the site should be able to do has no plugin behind it, or before installing anything, so you check what is already there first. Also returns "warnings": the plugins that must NOT be switched on while an agent is working, with the reason — a security plugin whose firewall blocks the REST API cuts the agent off mid-build, and a page cache makes every correct change look like it failed. Grouped: fundament, backup, content, search and filters, visibility, contact, payments (PL), shipping (PL), invoicing (PL), media, code, consent. Filter with "group" for one group only.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'group'        => array(
					'type'        => 'string',
					'description' => 'Return one group only, e.g. "platnosci" or "wysylka". Empty returns everything.',
				),
				'only_missing' => array(
					'type'        => 'boolean',
					'description' => 'Return only what is NOT active on this site — the gap list.',
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'site'     => array( 'type' => 'object' ),
				'catalog'  => array( 'type' => 'array' ),
				'warnings' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$zainstalowane = get_plugins();
			$aktywne       = array();
			$obecne        = array();

			foreach ( $zainstalowane as $plik => $dane ) {
				$nazwa = strtolower( (string) $dane['Name'] );
				$czynna = is_plugin_active( $plik );

				$obecne[ $nazwa ] = $czynna ? 'active' : 'installed-inactive';
				if ( $czynna ) {
					$aktywne[] = (string) $dane['Name'];
				}
			}

			$grupa        = isset( $input['group'] ) ? strtolower( (string) $input['group'] ) : '';
			$tylko_brakow = ! empty( $input['only_missing'] );
			$out          = array();

			foreach ( bdox_pack_toolbox_catalog() as $wpis ) {
				if ( '' !== $grupa && $wpis['group'] !== $grupa ) {
					continue;
				}

				$status = 'absent';
				foreach ( $obecne as $nazwa => $stan ) {
					if ( preg_match( '/' . $wpis['match'] . '/i', $nazwa ) ) {
						$status = $stan;
						break;
					}
				}

				if ( $tylko_brakow && 'active' === $status ) {
					continue;
				}

				$wpis['status'] = $status;
				unset( $wpis['match'] );
				$out[] = $wpis;
			}

			return array(
				'site'     => array(
					'active_plugins'  => $aktywne,
					'woocommerce'     => class_exists( 'WooCommerce' ),
					'woo_elements'    => class_exists( 'EssentialElements\\Wooproductprice' ),
					'backup_ability'  => function_exists( 'bdox_pack_backup_guard' ),
				),
				'catalog'  => $out,
				'warnings' => bdox_pack_toolbox_warnings(),
			);
		},
	)
);
