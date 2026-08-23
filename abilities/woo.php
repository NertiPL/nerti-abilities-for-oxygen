<?php
/**
 * The WooCommerce half of the builder: which element renders which part of the
 * store, and where its design lives.
 *
 * Registered only when Breakdance WooCommerce for Oxygen is actually installed,
 * so a site that sells nothing never sees these in its ability list.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * The store surfaces, and the elements that render each one.
 *
 * `role` is what the element IS, `use` is when to reach for it. Both are short on
 * purpose: the build procedure already lives in oxygen/get-ecommerce-instructions
 * and repeating it here would only give an agent two versions to reconcile. What
 * that guide does not carry is the design layer — which is the half that decides
 * whether the client can restyle their own shop afterwards.
 *
 * @return array<string, array<string, array<string, string>>>
 */
function bdox_pack_woo_catalog(): array {
	return array(
		'shop'     => array(
			'EssentialElements\\Wooshoppage'        => array(
				'role' => 'The whole shop page rendered by WooCommerce itself.',
				'use'  => 'Fastest way to a working shop page. No control over the card layout — for that, PostsLoop over a Component.',
			),
			'EssentialElements\\Wooproductslist'    => array(
				'role' => 'Native woo product loop: sorting, result count, pagination, add-to-cart.',
				'use'  => 'A product grid anywhere outside an archive template, or an archive where the default card is good enough.',
			),
			'EssentialElements\\Wooloopcartbutton'  => array(
				'role' => 'Add-to-cart button for the product of the current loop iteration.',
				'use'  => 'Inside a product-card Component. Leave content.content.product unset — it then targets the looped product by itself.',
			),
			'EssentialElements\\Woofacetedfilters'  => array(
				'role' => 'WooCommerce block filters: price slider, stock, categories, attributes, active-filter chips.',
				'use'  => 'Filtering with no extra plugin. Filter state travels as URL params the archive query applies.',
			),
			'EssentialElements\\WooShopFilters'     => array(
				'role' => 'The classic woo filter widgets in a sidebar.',
				'use'  => 'When the client expects the old widget look and a full page reload per filter is acceptable.',
			),
			'EssentialElements\\WooBreadcrumb'      => array(
				'role' => 'WooCommerce breadcrumb trail.',
				'use'  => 'Top of archive and product templates.',
			),
			'EssentialElements\\WooWidget'          => array(
				'role' => 'Renders one registered WooCommerce widget.',
				'use'  => 'A single widget (top rated, on sale, cart) outside the filter sidebar.',
			),
		),
		'product'  => array(
			'EssentialElements\\Productbuilder'      => array(
				'role' => 'The root wrapper of a single-product template.',
				'use'  => 'Every woo product part must be a descendant of it. Plain Containers in between are fine.',
			),
			'EssentialElements\\WooProductTitle'     => array(
				'role' => 'Product name.',
				'use'  => 'Own typography group — no class needed for size, weight or colour.',
			),
			'EssentialElements\\Wooproductprice'     => array(
				'role' => 'Price, including the struck-through old price on sale.',
				'use'  => 'TWO separate typography groups: design.typography.current_price and design.typography.old_price. Style them separately; a class on the wrapper cannot tell them apart.',
			),
			'EssentialElements\\Wooproductrating'    => array(
				'role' => 'Star rating plus review count.',
				'use'  => 'Renders an EMPTY wrapper on a product with no reviews — in a flex column it still eats the gap.',
			),
			'EssentialElements\\Wooproductcartbutton' => array(
				'role' => 'Quantity input, variation selects and the add-to-cart button.',
				'use'  => 'Never rebuild this from a Button element — variations and quantity are its whole job.',
			),
			'EssentialElements\\Wooproductstock'     => array(
				'role' => 'Stock status / quantity remaining.',
				'use'  => 'Empty when stock management is off. Same empty-wrapper trap as the rating.',
			),
			'EssentialElements\\Wooproductmeta'      => array(
				'role' => 'SKU, categories, tags.',
				'use'  => 'Bottom of the summary column.',
			),
			'EssentialElements\\Wooproductinfo'      => array(
				'role' => 'Single product field pulled out on its own (SKU, weight, dimensions, an attribute).',
				'use'  => 'When the client wants one specific value in its own spot rather than the whole meta block.',
			),
			'EssentialElements\\Wooproducttabs'      => array(
				'role' => 'Description / additional information / reviews.',
				'use'  => 'design.layout.layout switches the whole thing between tabs and accordion — that is a Design-tab value, not a class.',
			),
			'EssentialElements\\ProductDescription'  => array(
				'role' => 'Full product description.',
				'use'  => 'When the layout puts the description outside the tabs.',
			),
			'EssentialElements\\ProductExcerpt'      => array(
				'role' => 'Short description.',
				'use'  => 'Summary column, above the cart button.',
			),
			'EssentialElements\\ProductReviews'      => array(
				'role' => 'Review list plus the review form.',
				'use'  => 'Accepts submissions — do not fake it with static markup.',
			),
			'EssentialElements\\RelatedProducts'     => array(
				'role' => 'Related products grid.',
				'use'  => 'Below the tabs.',
			),
			'EssentialElements\\UpsellProducts'      => array(
				'role' => 'Upsell products grid.',
				'use'  => 'Only renders when upsells are set on the product.',
			),
			'EssentialElements\\Product'             => array(
				'role' => 'One specific product rendered anywhere.',
				'use'  => 'A featured product on a landing page, not on the product template.',
			),
			'EssentialElements\\Wooproductimages'    => array(
				'role' => 'The stock WooCommerce gallery.',
				'use'  => 'DO NOT USE. Build the gallery as one EssentialElements\\Swiper in dynamic-image mode — see oxygen/get-ecommerce-instructions.',
			),
		),
		'cart'     => array(
			'EssentialElements\\Woopageshoppingcart' => array(
				'role' => 'The whole cart page.',
				'use'  => 'Default choice. Reach for the granular elements below only when the layout has to split them up.',
			),
			'EssentialElements\\WooCartContents'     => array(
				'role' => 'Line items table.',
				'use'  => 'Left column of a two-column cart.',
			),
			'EssentialElements\\WooCartTotals'       => array(
				'role' => 'Totals, shipping estimate, proceed-to-checkout.',
				'use'  => 'Right column, usually sticky.',
			),
			'EssentialElements\\WooCartCrossSells'   => array(
				'role' => 'Cross-sell products.',
				'use'  => 'Below the line items.',
			),
			'EssentialElements\\WooCartEmptyMessage' => array(
				'role' => 'What an empty cart shows.',
				'use'  => 'Worth designing — it is the state a first-time visitor hits.',
			),
			'EssentialElements\\MiniCart'            => array(
				'role' => 'Header cart icon with count and a slide-out panel.',
				'use'  => 'Header template.',
			),
		),
		'checkout' => array(
			'EssentialElements\\Woopagecheckout'         => array(
				'role' => 'The whole checkout page.',
				'use'  => 'Default choice.',
			),
			'EssentialElements\\CheckoutBuilder'         => array(
				'role' => 'Root wrapper for a hand-composed checkout.',
				'use'  => 'The WooCheckout* elements below must be its descendants.',
			),
			'EssentialElements\\WooCheckoutBillingForm'  => array( 'role' => 'Billing fields.', 'use' => 'Inside CheckoutBuilder.' ),
			'EssentialElements\\WooCheckoutShippingForm' => array( 'role' => 'Shipping fields.', 'use' => 'Inside CheckoutBuilder.' ),
			'EssentialElements\\WooCheckoutCouponForm'   => array( 'role' => 'Coupon field.', 'use' => 'Inside CheckoutBuilder.' ),
			'EssentialElements\\WooCheckoutLoginForm'    => array( 'role' => 'Returning-customer login.', 'use' => 'Inside CheckoutBuilder.' ),
			'EssentialElements\\WooCheckoutOrderReview'  => array( 'role' => 'Order summary table.', 'use' => 'Inside CheckoutBuilder.' ),
			'EssentialElements\\WooCheckoutPayment'      => array( 'role' => 'Payment methods and the place-order button.', 'use' => 'Inside CheckoutBuilder.' ),
		),
		'account'  => array(
			'EssentialElements\\Woopageaccount'       => array(
				'role' => 'My Account: login, register, orders, addresses, downloads.',
				'use'  => 'One element for the whole account area.',
			),
			'EssentialElements\\Woopageordertracking' => array(
				'role' => 'Order tracking form.',
				'use'  => 'A standalone "where is my order" page.',
			),
		),
	);
}

/**
 * The store-wide styling rules, which is what this ability adds on top of
 * oxygen/get-ecommerce-instructions.
 *
 * @return string[]
 */
function bdox_pack_woo_rules(): array {
	return array(
		'1. Global settings FIRST, and there is a whole woocommerce branch of them on this stack. '
			. 'settings.woocommerce styles every store part site-wide in one write: buttons_links (add-to-cart, '
			. 'checkout, destructive and secondary buttons, their typography and hover), colors (brand, text, '
			. 'headings, borders, text_on_brand), forms.fields (background, borders, focus ring, padding, the '
			. 'quantity input), sale_badge, ratings.star_color and star_size, notices (error / success / info '
			. 'backgrounds and icons), tables, wrappers, spacing (base + ratio + named sizes), typography sizes '
			. 'and weights, products_list defaults (per row, per page, which card parts show), and the quicklook '
			. 'modal. Write it with bdox/patch-global-settings, read it back with bdox/get-global-settings. '
			. 'One write here beats forty element edits and the client can still change it from the UI.',
		'2. Then the element Design tab. Every woo element has its own — the price has SEPARATE typography for '
			. 'the current and the struck-through price, the tabs switch to accordion from design.layout.layout. '
			. 'Read it with bdox/design-map, not get-element-schemas: these elements are among the largest '
			. 'schemas in the builder.',
		'3. A CSS class aimed at a woo element\'s INTERNAL markup is the wrong answer, not merely a worse one. '
			. 'The element emits its own per-instance CSS from those design controls; a class written against '
			. 'its inner tags either loses on specificity or wins by accident and then breaks on the next '
			. 'plugin update, because those class names are the plugin\'s private business.',
		'4. Empty-state trap. A woo part with no data (rating with no reviews, stock with nothing to report, '
			. 'upsells with none set) still renders its wrapper. In a flex or grid summary column that wrapper '
			. 'keeps consuming the gap and the spacing looks broken on exactly the products nobody checked. '
			. 'Omit parts that will usually be empty, or give the element a condition.',
		'5. A store is templates, not pages: one archive template for every product listing, one product '
			. 'template for every product. Build flow, loop rules, the gallery and the template conditions are '
			. 'in oxygen/get-ecommerce-instructions — call it, this ability does not repeat it.',
		'6. After any of the above, bdox/regenerate-css. Woo parts are rendered from the compiled stylesheet '
			. 'like everything else, so an uncompiled change looks like the element ignoring you.',
	);
}

/**
 * Register the ability, but only on a store.
 *
 * The check has to run on the Abilities API hook rather than while this file is
 * being included, and the reason is plugin load order: WordPress loads active
 * plugins in the order of the `active_plugins` option, where
 * `nerti-abilities-for-oxygen` sorts BEFORE `woocommerce`. At include time
 * class_exists( 'WooCommerce' ) is therefore false on a site that plainly has
 * WooCommerce, the ability silently never registers, and the symptom is
 * "Ability 'bdox/woo-map' not found" on a working store. Measured 2026-08-23,
 * which is also why this comment exists instead of a one-line if.
 *
 * Priority 5 runs ahead of ACFW_Pack::boot(), which reads the collected
 * definitions at the default priority.
 *
 * @return void
 */
function bdox_pack_register_woo_map(): void {
	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'EssentialElements\\Wooproductprice' ) ) {
		return;
	}

	bdox_pack_ability(
		array(
			'name'             => 'bdox/woo-map',
			'label'            => 'WooCommerce Element Map',
			'description'      => 'Atlas of the WooCommerce elements on this site: which element renders which part of the store, when to reach for it, and — read live from each schema — which design groups it owns, so you can style it natively instead of writing CSS at it. CALL THIS AFTER oxygen/get-ecommerce-instructions AND BEFORE styling any store page. The two do not overlap: that guide covers how to BUILD a store (templates, loops, the gallery, template conditions); this one covers how to STYLE it and what each element actually is. It also carries the six store-wide rules this stack runs on, the first of which is the one that saves the most work: global settings have a whole "woocommerce" branch (buttons, prices, sale badge, ratings, notices, form fields, tables, spacing, typography, product-list defaults) that themes every store part site-wide in ONE write via bdox/patch-global-settings — before you touch a single element. Filter with "surface": shop, product, cart, checkout, account, or all. Elements the site does not have are reported in "missing" rather than described as if they existed.',
			'category'         => 'bdox',
			'input_schema'     => array(
				'type'                 => 'object',
				'properties'           => array(
					'surface'              => array(
						'type'        => 'string',
						'enum'        => array( 'all', 'shop', 'product', 'cart', 'checkout', 'account' ),
						'description' => 'Which part of the store. Default "all".',
					),
					'include_design_groups' => array(
						'type'        => 'boolean',
						'description' => 'Read each element\'s schema and list its top-level design groups. Default true. Set false for a faster, purely descriptive answer.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'surface'  => array( 'type' => 'string' ),
					'rules'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'elements' => array( 'type' => 'object' ),
					'missing'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
			'annotations'      => array( 'readonly' => true, 'idempotent' => true ),
			'execute_callback' => static function ( array $input ) {
				$guard = bdox_pack_guard();
				if ( is_wp_error( $guard ) ) {
					return $guard;
				}

				$surface = isset( $input['surface'] ) ? (string) $input['surface'] : 'all';
				$groups  = ! array_key_exists( 'include_design_groups', $input ) || (bool) $input['include_design_groups'];
				$katalog = bdox_pack_woo_catalog();

				if ( 'all' !== $surface ) {
					$katalog = isset( $katalog[ $surface ] )
						? array( $surface => $katalog[ $surface ] )
						: array();
				}

				$out     = array();
				$brakuje = array();

				foreach ( $katalog as $powierzchnia => $elementy ) {
					foreach ( $elementy as $slug => $opis ) {
						if ( ! class_exists( $slug ) ) {
							$brakuje[] = $slug;
							continue;
						}

						$wpis = array(
							'surface' => $powierzchnia,
							'role'    => $opis['role'],
							'use'     => $opis['use'],
						);

						if ( $groups && function_exists( 'breakdance_get_element_schema_for_slug' ) ) {
							$schema = breakdance_get_element_schema_for_slug( $slug );
							$design = isset( $schema['properties']['design']['properties'] )
								&& is_array( $schema['properties']['design']['properties'] )
									? array_keys( $schema['properties']['design']['properties'] )
									: array();

							$wpis['design_groups'] = $design
								? $design
								: array( 'none — this element has no Design tab; style it from settings.woocommerce or from its parent' );
						}

						$out[ $slug ] = $wpis;
					}
				}

				return array(
					'surface'  => $surface,
					'rules'    => bdox_pack_woo_rules(),
					'elements' => $out,
					'missing'  => $brakuje,
				);
			},
		)
	);
}

add_action( 'wp_abilities_api_init', 'bdox_pack_register_woo_map', 5 );
