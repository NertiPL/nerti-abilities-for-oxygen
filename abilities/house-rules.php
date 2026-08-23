<?php
/**
 * The house rules, served at the MCP boundary.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * The rules themselves, as one plain-text block.
 *
 * Kept as data rather than a file on disk so the ability works on any install
 * the pack is dropped onto, including a sandbox deployment with no repo behind it.
 *
 * @return string
 */
function bdox_pack_house_rules(): string {
	return <<<'TXT'
# House rules for building in Oxygen on this site

These sit ON TOP of oxygen/get-instructions. Where they disagree, these win: they are
measured on this stack (Oxygen 6.2 + Breakdance Elements), not written for a generic install.

## 0. Before touching a site you did not build

Take a backup: `updraft/backup-now` then poll `updraft/list-backups` until the entry
appears. Do not report a backup as done off the queue call. If the site has no UpdraftPlus
ability, say so before doing bulk edits rather than after.

## 1. The Design tab comes before any CSS class

A selector is the last resort, not the starting point. Three reasons, in order of weight:

1. The client can change what they can see. The Design tab is English, has sliders and a
   live preview. The Selectors panel is a list of names they will never touch.
2. A Design-tab value compiles to the element's OWN generated selector (`.bde-div-147-224`),
   so it outranks classes without fighting them, and it travels with the element when the
   section is copied to another page.
3. A class only pays for itself on repetition. One place -> Design tab. The same look in
   many places -> one shared class.

Procedure, every time:

    bdox/design-map { slugs: [...], prefix: "design" }   <- can the element do this itself?
      it can        -> oxygen/edit-post, properties.design.*
      it cannot     -> oxygen/get-css-selectors: does the class already exist?
           it does  -> attach it, do not create a twin
           it does not -> only now oxygen/insert-stylesheet

Budget check: a full WooCommerce shop on this stack runs on ~69 selectors total. If one page
is about to get its fifth new class, the Design tab is being skipped.

## 2. Control names do not match the guesses

`oxygen/get-element-schemas` filtered by a guessed path returns EMPTY, which reads as proof
that the control does not exist. It is not. On `EssentialElements\Div`:

  flex / grid, gaps, alignment ........ design.layout_v2.*   (NOT design.layout)
  padding, borders, radius, shadow .... design.container.*   (NOT design.borders)
  text / heading / link colours ....... design.text_colors.*

Use `bdox/design-map` instead: one line per control, whole element in a few hundred tokens.

Two write traps, both measured:
  - `design.layout_v2.v_align` is responsive (`{"breakpoint_base": "center"}`);
    `h_alignment_when_vertical` is a plain enum. The wrong shape errors loudly one way and
    silently the other.
  - With `layout: "vertical"`, horizontal alignment of children comes from `v_align`.
    `h_alignment_when_vertical` belongs to the HORIZONTAL layout and is ignored here.

## 3. Text or RichText, decided by heading, not by length

  heading (h1-h6), any length ............ ALWAYS `EssentialElements\Text`
  not a heading, more than one line ...... `EssentialElements\RichText`
  not a heading, single line ............. `EssentialElements\Text`
  inside a parent with layout_v2 horizontal ... NEVER RichText (its <p> breaks the row)

Why: Text and Heading expose a raw text field, so a client editing a paragraph sees every
`<span>` as code and deletes it. RichText gives them a WYSIWYG. A heading must stay one
element with a level, so RichText's `<p>` wrapper disqualifies it there.

## 4. Things that look like broken JavaScript

  - `meta.classes` takes SELECTOR IDS, not names. A name is accepted without error and
    applies nothing. Read ids from `oxygen/get-css-selectors`.
  - The `hidden` attribute loses to any `display` from a class or the Design tab, because
    the browser rule for `[hidden]` is a plain attribute selector. Hiding an element from
    script: set `style="display:none"` in the element's attributes and clear it in JS, or
    add an explicit `.klasa[hidden] { display: none }` rule.
  - After `oxygen/edit-post` or any selector change, run `bdox/regenerate-css`. Until then
    the front end serves the previous stylesheet and the change looks like it failed.

## 5. Build first, hide last

`oxygen_builder_hidden_elements` hides elements from the MCP element list too, not just from
the editor's Add panel. Hiding elements before the build is finished cuts the agent off from
them. `bdox/element-visibility` reports what is hidden and why.
TXT;
}

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/house-rules',
		'label'            => 'House Rules',
		'description'      => 'The site owner\'s build rules for this Oxygen install, on top of oxygen/get-instructions. CALL THIS RIGHT AFTER oxygen/get-instructions, before the first build or edit, and again whenever a class is about to be created. It is short and it is the difference between a page whose styling the client can edit and a page that needs you back for every change. Covers: back up before touching a site you did not build; the Design tab before any CSS class, with the decision procedure and the selector budget this site actually runs on; the Design-tab control paths that are NOT named the way you would guess (design.layout_v2, design.container) and therefore look absent when you filter get-element-schemas by a guessed name; when to use Text versus RichText; and four failure modes that look like broken JavaScript but are not (meta.classes taking ids not names, [hidden] losing to display, stale CSS before regenerate-css, elements hidden from MCP).',
		'category'         => 'bdox',
		/* Adapter MCP niektorych instalacji zamienia puste {} na pusta tablice PHP,
		   a walidacja odrzuca ja jako „nie obiekt". Stad jedno pole — wolanie
		   {"section":"all"} przechodzi, a przy okazji da sie wyciagnac jeden rozdzial. */
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'section' => array(
					'type'        => 'string',
					'description' => 'Numer rozdzialu (0-5) albo "all". Domyslnie caly tekst.',
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'rules' => array( 'type' => 'string' ),
			),
		),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$sekcja = isset( $input['section'] ) ? (string) $input['section'] : 'all';
			$tekst  = bdox_pack_house_rules();
			if ( 'all' !== $sekcja && '' !== $sekcja ) {
				foreach ( (array) preg_split( '/\n(?=## )/', $tekst ) as $c ) {
					if ( 0 === strpos( ltrim( $c ), '## ' . $sekcja . '.' ) ) {
						return array( 'rules' => $c );
					}
				}
			}
			return array( 'rules' => $tekst );
		},
	)
);
