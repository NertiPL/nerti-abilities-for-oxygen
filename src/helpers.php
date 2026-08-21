<?php
/**
 * Shared helpers. Every one of these wraps a builder internal that the built-in
 * MCP layer either does not expose or exposes only destructively.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Guard: the builder must be loaded before any of these abilities make sense.
 *
 * @return true|WP_Error
 */
function bdox_pack_guard() {
	if ( ! function_exists( 'Breakdance\\Data\\get_global_settings_array' ) ) {
		return new WP_Error(
			'bdox_builder_missing',
			'Oxygen / Breakdance is not loaded on this request.',
			array( 'status' => 500 )
		);
	}
	return true;
}

/**
 * Recursive merge where $patch wins. Scalars replace; associative arrays merge.
 *
 * List arrays are replaced wholesale rather than merged by index — merging
 * lists by index silently corrupts ordered collections such as stylesheets or
 * button presets, which is exactly the failure this pack exists to avoid.
 *
 * @param mixed $base  Existing value.
 * @param mixed $patch Incoming value.
 * @return mixed
 */
function bdox_pack_deep_merge( $base, $patch ) {
	if ( ! is_array( $base ) || ! is_array( $patch ) ) {
		return $patch;
	}

	// A list (0..n-1 integer keys) is an ordered collection: replace, never merge.
	if ( array_is_list( $patch ) ) {
		return $patch;
	}

	foreach ( $patch as $key => $value ) {
		$base[ $key ] = array_key_exists( $key, $base )
			? bdox_pack_deep_merge( $base[ $key ], $value )
			: $value;
	}

	return $base;
}

/**
 * IDs of every live post built with the builder (pages, templates, headers,
 * footers, components).
 *
 * Revisions and trashed posts are excluded. Builder data is copied onto every
 * revision, so a naive query returns mostly revisions — on a small eight-page
 * site that was 58 revisions and 2 trashed pages against 10 real posts. Feeding
 * those to a cache rebuild writes post-<revision-id>.css files that nothing ever
 * loads, and makes a routine regeneration take seven times longer than it needs.
 *
 * @param bool $include_all Include revisions and trashed posts.
 * @return int[]
 */
function bdox_pack_builder_post_ids( bool $include_all = false ): array {
	global $wpdb;

	if ( $include_all ) {
		$ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_oxygen_data'"
		);

		return array_values( array_map( 'intval', (array) $ids ) );
	}

	$ids = $wpdb->get_col(
		"SELECT DISTINCT m.post_id
		   FROM {$wpdb->postmeta} m
		   JOIN {$wpdb->posts} p ON p.ID = m.post_id
		  WHERE m.meta_key = '_oxygen_data'
		    AND p.post_type != 'revision'
		    AND p.post_status NOT IN ( 'trash', 'auto-draft' )"
	);

	return array_values( array_map( 'intval', (array) $ids ) );
}

/**
 * Rebuild the compiled CSS for the given posts.
 *
 * Both calls are required and in this order: regenerate_post_cache refreshes the
 * tree cache but writes no CSS files, generateCacheForPost writes post-<id>.css.
 * Calling only the first is the most common reason a Design-tab value "does not
 * work" — the value is stored but never compiled.
 *
 * @param int[] $ids Post IDs.
 * @return int[] IDs actually regenerated.
 */
function bdox_pack_regenerate_posts( array $ids ): array {
	$done = array();

	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			continue;
		}
		if ( function_exists( 'Breakdance\\Data\\regenerate_post_cache' ) ) {
			\Breakdance\Data\regenerate_post_cache( $id );
		}
		if ( function_exists( 'Breakdance\\Render\\generateCacheForPost' ) ) {
			\Breakdance\Render\generateCacheForPost( $id );
		}
		$done[] = $id;
	}

	return $done;
}

/**
 * The element tree of a builder post, decoded.
 *
 * `_oxygen_data` is not the tree — it is an object whose single key
 * `tree_json_string` holds the tree as an ENCODED STRING. Decoding one level and
 * subscripting `['root']` gets null, which is the shape mistake to make once.
 *
 * @param int $post_id Post to read.
 * @return array|null Decoded tree, or null when the post is not built with the builder.
 */
function bdox_pack_get_tree( int $post_id ): ?array {
	$outer = json_decode( (string) get_post_meta( $post_id, '_oxygen_data', true ), true );
	if ( ! is_array( $outer ) || ! isset( $outer['tree_json_string'] ) ) {
		return null;
	}

	$tree = json_decode( (string) $outer['tree_json_string'], true );

	return ( is_array( $tree ) && isset( $tree['root'] ) ) ? $tree : null;
}

/**
 * Write a tree back, preserving the wrapper's other keys.
 *
 * @param int   $post_id Post to write.
 * @param array $tree    Full tree, including `root`.
 * @return void
 */
function bdox_pack_save_tree( int $post_id, array $tree ): void {
	$outer = json_decode( (string) get_post_meta( $post_id, '_oxygen_data', true ), true );
	$outer = is_array( $outer ) ? $outer : array();

	$outer['tree_json_string'] = wp_json_encode( $tree );

	update_post_meta( $post_id, '_oxygen_data', wp_slash( wp_json_encode( $outer ) ) );
}

/**
 * Apply a callback to one node, addressed by id, returning a NEW tree.
 *
 * Deliberately not a reference-based finder. A helper built on `function &find`
 * makes every write land through an alias, and a caller that then inserts into
 * the returned handle duplicates whole subtrees — measured on a production site
 * where seventeen pages grew a second copy of a section. Copying is cheap here;
 * the trees are small.
 *
 * @param array    $node    Node to walk (start at the tree's `root`).
 * @param int      $id      Node id to match.
 * @param callable $mutator fn(array $node): array
 * @param bool     $found   Set to true when the id was matched.
 * @return array
 */
function bdox_pack_map_node( array $node, int $id, callable $mutator, bool &$found ): array {
	if ( isset( $node['id'] ) && (int) $node['id'] === $id ) {
		$found = true;
		$node  = $mutator( $node );
	}

	if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
		foreach ( $node['children'] as $index => $child ) {
			if ( is_array( $child ) ) {
				$node['children'][ $index ] = bdox_pack_map_node( $child, $id, $mutator, $found );
			}
		}
	}

	return $node;
}

/**
 * Sizes of the compiled CSS files, so a caller can tell a real regeneration
 * from a no-op. A 0-byte variables.css or a missing post-<id>.css is the
 * signature of a stale cache.
 *
 * @return array<string,int>
 */
function bdox_pack_css_file_sizes(): array {
	$dir   = WP_CONTENT_DIR . '/uploads/oxygen/css/';
	$sizes = array();

	foreach ( glob( $dir . '*.css' ) ?: array() as $file ) {
		$sizes[ basename( $file ) ] = (int) filesize( $file );
	}

	ksort( $sizes );

	return $sizes;
}

/**
 * Resolved typography as the browser will see it, read from the compiled
 * global-settings.css rather than from the stored settings tree.
 *
 * Returns the three values plus a verdict. `unresolved_font_slug` is the one that
 * matters: it means a `gfont-…` slug reached the stylesheet as the font-family,
 * so the Google Font never loaded and every heading fell back to the generic.
 * Nothing about that is visible in the builder, and set-global-settings reported
 * success.
 *
 * @param string $file Override the stylesheet path. Empty means the real compiled file;
 *                     the parameter exists so the verdict itself can be tested against fixtures.
 * @return array{heading:string,body:string,base_size:string,verdict:string}
 */
function bdox_pack_compiled_typography( string $file = '' ): array {
	$file = '' !== $file ? $file : WP_CONTENT_DIR . '/uploads/oxygen/css/global-settings.css';

	if ( ! is_readable( $file ) ) {
		return array(
			'heading'   => '',
			'body'      => '',
			'base_size' => '',
			'verdict'   => 'no-compiled-global-settings-css — run bdox/regenerate-css',
		);
	}

	$css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	$read = static function ( string $var ) use ( $css ): string {
		// Take the LAST declaration: later blocks override earlier defaults.
		if ( ! preg_match_all( '/' . preg_quote( $var, '/' ) . ':([^;}]+)/', $css, $m ) ) {
			return '';
		}
		return trim( (string) end( $m[1] ) );
	};

	$heading = $read( '--bde-heading-font-family' );
	$body    = $read( '--bde-body-font-family' );

	$broken = array_filter(
		array( 'heading_font' => $heading, 'body_font' => $body ),
		static fn( string $v ): bool => 0 === strncmp( $v, 'gfont-', 6 )
	);

	return array(
		'heading'   => $heading,
		'body'      => $body,
		'base_size' => $read( '--bde-base-font-size' ),
		'verdict'   => $broken
			? 'unresolved_font_slug in ' . implode( ', ', array_keys( $broken ) )
				. ' — the slug drops every non-alphanumeric character, so "Bricolage Grotesque"'
				. ' is "gfont-bricolagegrotesque", never "gfont-bricolage-grotesque".'
				. ' Font families live in plugin/fonts/integrations/google-fonts/google-font-list.json.'
			: 'ok',
	);
}
