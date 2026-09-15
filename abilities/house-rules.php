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
appears. Do not report a backup as done off the queue call — that call returns when the job is
QUEUED, it runs through WP-Cron, and on a store with media it takes minutes. If the response
says `cron_disabled`, nothing will run it at all. If the site has no UpdraftPlus ability, say so
before doing bulk edits rather than after.

## 1. The Design tab comes before any CSS class

A selector is the last resort, not the starting point. Three reasons, in order of weight:

1. The client can change what they can see. The Design tab is English, has sliders and a
   live preview. The Selectors panel is a list of names they will never touch.
2. A Design-tab value compiles to the element's OWN generated selector (`.bde-div-147-224`),
   so it outranks classes without fighting them, and it travels with the element when the
   section is copied to another page.
3. A class only pays for itself on repetition. One place -> Design tab. The same look in
   many places -> Global Settings and typography/button presets. Shared content -> one Component with instances; expose local overrides explicitly. A class is only for a measured missing native control.

Procedure, every time:

    tokens -> Global Settings -> presets -> Essential Elements Design -> shared Components
    Native Animations / Interactions come before custom JS. Read schemas including hover.
    Two pages must demonstrate source propagation AND an isolated instance override.

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
  - Native save paths compile CSS. Read back and render first; use `bdox/regenerate-css`
    only after direct data patches or when compiled CSS is demonstrably stale.

## 5. Build first, hide last

`oxygen_builder_hidden_elements` hides elements from the MCP element list too, not just from
the editor's Add panel. Hiding elements before the build is finished cuts the agent off from
them. `bdox/element-visibility` reports what is hidden and why.

## 6. A store is styled from three levels, in this order

Same rule as chapter 1, one level deeper, because WooCommerce adds a level ABOVE the Design tab:

    global settings -> settings.woocommerce ..... themes every store part site-wide, one write
    element Design tab .......................... one element, one place
    CSS class ................................... only what neither of the above can express

`settings.woocommerce` is not a stub. It carries buttons (add-to-cart, checkout, secondary,
destructive, their typography, hover and disabled states), colours, form fields down to the
quantity input, the sale badge, star colour and size, notices, tables, wrappers, the spacing
scale, the typography scale, product-list defaults and the quicklook modal. Write it with
`bdox/patch-global-settings`, read it back with `bdox/get-global-settings`.

Then the elements. Every WooCommerce element has its own Design tab and some have more than one
typography group in it — the price styles the current price and the struck-through old price
separately, and no class on the wrapper can tell those two apart. Read them with
`bdox/design-map`, never with `get-element-schemas`: these are among the largest schemas in the
builder, and `design-map` collapses each typography and effects block to a single line.

A class aimed at a WooCommerce element's internal markup is not a worse answer, it is the wrong
one: the element emits its own per-instance CSS from those controls, so the class either loses
on specificity or wins by accident and breaks on the next plugin update.

`bdox/woo-map` names every store element, what it renders and which design groups it owns.
`oxygen/get-ecommerce-instructions` covers the build itself (templates, loops, the gallery) —
call both, they do not overlap.

## 7. Which plugin, and which one not to switch on

`bdox/store-toolbox` lists what a store build needs, what is already installed here, and what is
missing — plus the two that end a session: a security plugin whose firewall blocks the REST API
(the agent's only way in), and a page cache that serves the previous version of every page so a
change that landed correctly reads as a change that failed. Page caching goes on at handover,
after the last `bdox/regenerate-css`.

## 8. Gdzie co siedzi w Essential Elements

Zmierzone 2026-08-23 na BEFO 1.1.0.beta.4. Te ścieżki są powodem, dla którego agent pisze klasę
CSS na coś, co element umie sam: nazwy nie są takie, jakie by się wymyśliło, a
`get-element-schemas` odfiltrowany po zgadniętej nazwie zwraca pustkę wyglądającą jak dowód.

| Czego chcesz | Section | Div / Grid | reszta |
|---|---|---|---|
| flex, grid, odstęp, wyrównanie | `design.layout_v2` | `design.layout_v2` (Grid: `design.grid`) | — |
| padding | **`design.spacing.padding`** | **`design.container.padding`** | — |
| margines zewnętrzny | `design.spacing.margin_top` / `_bottom` | `design.spacing.margin_top` / `_bottom` | tak samo, każdy element |
| tło, gradient, obraz, wideo | `design.background` | `design.background` | — |
| obramowanie, promień, cień | `design.borders` | **`design.container.borders`** | Image2: `design.borders` |
| szerokość / wysokość | `design.size` (w tym `container_width`) | `design.container.width`, `min_height` | Text: `design.size.width`, Image2: `design.image` |
| kolor nagłówków i tekstu w środku | `design.text_colors.headings` / `.text` / `.link` / `.brand` | `design.text_colors.*` | — |
| typografia | — | — | Text: `design.typography` |

Dwie rzeczy, na których się przewraca każdy, kto zgaduje:

- **Padding Section i padding Div to inne gałęzie.** Section ma go w `spacing`, Div i Grid
  w `container`. Marginesy natomiast siedzą w `spacing` u obu.
- **Kolor nagłówka nie schodzi kaskadą z rodzica**, bo ustawienia globalne przypisują go
  wprost do `h1`–`h6`. Odpowiedzią jest `design.text_colors.headings` na Section albo Div —
  to jest właśnie ta kontrolka, nie klasa CSS na wrapperze.

**Przyciski.** `design.button.style` przyjmuje `primary | secondary | custom | text`. Domyślnie
`primary` albo `secondary`, a wygląd obu ustaw RAZ w ustawieniach globalnych (`buttons.primary`,
`buttons.secondary`). `design.button.custom.*` (tło, obrys, kolor, promień, ikona, efekty) to
jednorazowy wyjątek, nie sposób na stylowanie przycisków w serwisie — dwadzieścia przycisków
ustawionych na `custom` to dwadzieścia miejsc do zmiany, kiedy klient poprosi o inny odcień.

Nie zgaduj tej tabeli dla elementu, którego tu nie ma. `bdox/design-map` z `groups_only: true`
przyjmuje wiele slugów naraz i daje dwie–trzy linie na element: najpierw dowiadujesz się, GDZIE
kontrolka siedzi, potem dopiero pytasz o jej wnętrze.

## 9. Puste pole w zakładce Design nie znaczy „bez stylu"

To jest jedna z niewielu rzeczy, które trzeba znać, zanim się zacznie, bo objaw pojawia się
dopiero na froncie i zwykle nie na tym ekranie, na którym się pracowało. Kontrolka zostawiona
pusta nie wyłącza stylu — **oddaje decyzję domyślnej wartości**, a te są widoczne.

Zmierzone 2026-08-23 na tej instalacji:

| Zostawisz puste | Co zobaczysz | Gdzie to naprawić |
|---|---|---|
| kolor hoveru odnośnika | `a:hover` (0,1,1) bije twoją klasę (0,1,0) — link zmienia kolor pod kursorem na globalny | `design.text_colors.link` na Section/Div, albo `colors.links_hover` globalnie, albo **klasa i jej `:hover`** razem (0,2,0) |
| podkreślenie odnośnika | podkreślenie z przeglądarki — **nikt go nie napisał**. Reguła globalna ustawia odnośnikowi wyłącznie kolor, `.bde-text-link` tylko `display:flex`, a `normalize.css` Oxygena rusza w `a` jedynie `background-color` | jeden selektor `a { text-decoration: none }` wstawiony jako PIERWSZY na liście, żeby klasy projektu nadal go biły — albo `text_decoration` na zakładce Design TextLinku |
| kolor przycisku | zestaw `--bde-button-*` z ustawień globalnych, razem z hoverem, obrysem i przejściem | `buttons.primary` / `buttons.secondary` w ustawieniach globalnych, raz dla całej witryny |
| interlinia | Breakdance nie dziedziczy jej — zostawia własną, dopóki nie ustawisz jej wprost. Zaobserwowane przy przenoszeniu Laborii: menu i stopka wychodziły o ~3 px wyższe niż w projekcie | ustaw `lineHeight` jawnie w typografii elementu albo w presecie typografii |

Przyciski zdejmują podkreślenie **tylko w stanie normal**: `.button-atom--primary/secondary/custom`
ustawiają `text-decoration: none` przy (0,1,0). Dlatego odnośnik „ostylowany na przycisk" ręcznie
wygląda inaczej niż prawdziwy Button. Ale jeśli `typography.advanced.links` podkreśla odnośniki
po najechaniu, `a:hover` (0,1,1) wygrywa i **każdy Button jest podkreślony po hover** — zmierzone
2026-09-15 na Oxygen 6.2.0-beta.8. Preset przycisku ma `decoration.line` wyłącznie dla normal,
kontrolki dekoracji hover nie ma. Poprawka w tej kolejności:

1. `buttons.primary.typography.typography.custom.customTypography.advanced.decoration.line`
   = `{"breakpoint_base": ["none"]}` (i tak samo secondary), żeby stan normal był wpisany jawnie;
2. JEDEN nazwany arkusz w `code.stylesheets`, nigdy per element, zawężony do wariantów z tłem
   i obrysem, żeby `.button-atom--text` zachował podkreślenie odnośnika:
   `.button-atom:is(.button-atom--primary, .button-atom--secondary, .button-atom--custom):is(:hover, :focus-visible, :active) { text-decoration-line: none }`.

Przy okazji dwie domyślne, które też trzeba nadpisać tym samym arkuszem: focus-visible to
`outline: auto 3px currentColor` bez odsunięcia (biały na białym tle, czyli niewidoczny) oraz
`transition-property: all`. Sprawdzaj computed style na froncie w stanach normal, hover,
focus-visible i active, nie w kanwie buildera.

`bdox/element-defaults` wypisuje pełną listę takich domyślnych razem ze specyficznością
i poprawką, która przeżyje aktualizację wtyczki. Wołaj ją, zanim zaczniesz walczyć z CSS-em,
którego nie napisałeś.

### Trzy inne rzeczy, które wypełniają się same

- **`@media` spoza zarejestrowanych breakpointów jest po cichu pomijane.** Progi tej instalacji
  to 1119, 1023, 767 i 479 — zapytanie skopiowane z Tailwinda (640, 1280) zniknie, a import
  zgłosi sukces. Sprawdzaj `oxygen/get-breakpoints` i kopiuj zapytania dosłownie; dokładny próg
  spoza listy wpisuj przez `custom_css` w `bdox/merge-css-selector`.
- **Animacji wejścia nie zapiszesz przez `edit-post`** — `settings.animations` zostanie
  odrzucone. Służy do tego `oxygen/set-element-animations`. Przy przenoszeniu Laborii wyszły
  przy okazji dwie rzeczy: `offset: 0` nie znaczy zera, bo skrypt wejścia podstawia wtedy własne
  120 px, więc żeby trafić w konkretny próg, trzeba go wpisać jawnie; a przy
  `prefers-reduced-motion` Breakdance nie robi przenikania, tylko od razu pokazuje element.
- **`insert-stylesheet` ZASTĘPUJE właściwości selektora, nie dokleja.** Import reguły z jedną
  deklaracją kasuje wszystkie pozostałe w tym breakpoincie. Do zmiany istniejącej klasy jest
  `bdox/merge-css-selector`, który scala per breakpoint.

## 10. Zero zewnętrznych hostów przed zgodą na cookies

Dwa źródła Oxygen dokłada sam, zanim odwiedzający cokolwiek kliknie. Zmierzone 2026-09-15
na Oxygen 6.2.0-beta.8.

- **Google Fonts.** Font wybrany jako `gfont-*` ładuje się z `fonts.googleapis.com` i
  `fonts.gstatic.com`. Lokalnie: Custom Fonts w `preferences.customFonts` i przełączenie
  `typography.heading_font` / `body_font` na id tej rodziny. Generator `@font-face` Oxygena
  NIE zna `unicode-range`, więc podzbiory Google (latin, latin-ext) nadpisałyby się nawzajem —
  zarejestruj rodzinę z `hasExternalCssUrl: true` i własnym arkuszem w `uploads`. Nazwy plików
  bez podkatalogów: `write_file_to_bucket()` przepuszcza nazwę przez `sanitize_file_name()`,
  `dm-sans/plik.css` zapisuje się jako `dm-sansplik.css`, a front dostaje 404 i cicho spada
  na Arial. Dowód: `CSS.getPlatformFontsForNode` (CDP) przed i po, nie sama nazwa w `font-family`.
- **GSAP z unpkg.com.** Animacje wejścia, przewijania i sticky biorą
  `%%BREAKDANCE_REUSABLE_GSAP%%` z `getReusableDependenciesUrls()`, a ta przepuszcza adresy
  przez filtr `breakdance_reusable_dependencies_urls` (także `oxygen_…`). Ustawienia w panelu
  nie ma; filtr w mu-pluginie i pliki w `uploads`, bez zmian w samym Oxygenie. Po zmianie
  sprawdź na froncie `gsap.version`, jeden egzemplarz i postęp ScrollTriggera po przewinięciu.
TXT;
}

bdox_pack_ability(
	array(
		'name'             => 'bdox/house-rules',
		'label'            => 'House Rules',
		'description'      => 'The site owner\'s build rules for this Oxygen install, on top of oxygen/get-instructions. CALL THIS RIGHT AFTER oxygen/get-instructions, before the first build or edit, and again whenever a class is about to be created. It is short and it is the difference between a page whose styling the client can edit and a page that needs you back for every change. Covers: back up before touching a site you did not build; the Design tab before any CSS class, with the decision procedure and the selector budget this site actually runs on; the Design-tab control paths that are NOT named the way you would guess (design.layout_v2, design.container) and therefore look absent when you filter get-element-schemas by a guessed name; when to use Text versus RichText; four failure modes that look like broken JavaScript but are not (meta.classes taking ids not names, [hidden] losing to display, stale CSS before regenerate-css, elements hidden from MCP); the three levels a WooCommerce store is styled from, starting with the settings.woocommerce branch that themes the whole store in one write; which plugins a store build needs versus the two that will cut the agent off mid-session; and a measured table of WHERE each design control lives on Section, Div, Grid, Text, Image2 and Button — including the two that everyone gets wrong, padding sitting in a different branch on Section than on Div, and heading colour not cascading from a parent because global settings set it on the tag; and why an EMPTY control in the Design tab is not the absence of a style but a vote for the default — which is how links end up underlined and changing colour on hover when nobody wrote either rule.',
		'category'         => 'bdox',
		/* Jedno pole, zeby dalo sie wyciagnac jeden rozdzial zamiast calosci. Puste
		   wolanie tez dziala — default w input_schema podstawia bdox_pack_ability(). */
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'section' => array(
					'type'        => 'string',
					'description' => 'Numer rozdzialu (0-9) albo "all". Domyslnie caly tekst.',
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
