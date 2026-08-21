<?php
/**
 * Media-library images that actually render.
 *
 * The built-in `edit-post` cannot produce a working media-library image at a
 * named size. Measured 2026-07-27 on Oxygen 6.2.0-beta.2 and re-measured 2026-08-21 on
 * 6.2.0-beta.5, unchanged: an `OxygenElements\Image`
 * with `from: media_library` and `size: "large"` renders the builder's 540x540 grey
 * placeholder, while `edit-post` reports success and reads the data back exactly as
 * written. The element template resolves the src as
 * `content.image.media.sizes[content.image.size].url`, but `media` is schema'd with
 * only `id`, `url`, `alt` and `additionalProperties: false`, so `sizes` cannot be
 * written through the API at all — the lookup resolves empty and `|default()` fires.
 * `srcset` has the same problem one level over: it reads
 * `content.image.media.attributes.srcset`, and `attributes` is absent from the schema
 * too, so an API-created image can never emit a srcset.
 *
 * Reported upstream as soflyy/agent-connector-for-wp#79. This ability fills both maps
 * from WordPress's own attachment metadata and writes them straight into the tree.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Every registered size of an attachment, in the shape the element template indexes.
 *
 * @param int $attachment_id Attachment.
 * @return array<string,array{url:string,width:int,height:int}>
 */
function bdox_pack_attachment_sizes( int $attachment_id ): array {
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $meta ) ) {
		return array();
	}

	$full = (string) wp_get_attachment_url( $attachment_id );
	$base = trailingslashit( dirname( $full ) );
	$out  = array();

	foreach ( (array) ( $meta['sizes'] ?? array() ) as $name => $size ) {
		if ( empty( $size['file'] ) ) {
			continue;
		}
		$out[ (string) $name ] = array(
			'url'    => $base . $size['file'],
			'width'  => (int) ( $size['width'] ?? 0 ),
			'height' => (int) ( $size['height'] ?? 0 ),
		);
	}

	// "full" is not in the sizes map — it is the original, and it is the value the
	// builder's own size control offers first.
	$out['full'] = array(
		'url'    => $full,
		'width'  => (int) ( $meta['width'] ?? 0 ),
		'height' => (int) ( $meta['height'] ?? 0 ),
	);

	return $out;
}

ACFW_Pack::ability(
	array(
		'name'             => 'bdox/set-image',
		'label'            => 'Set Media Library Image',
		'description'      => 'Point an image element at a media-library attachment so it actually renders, at a named size and with a srcset. Use this instead of setting content.image through edit-post whenever the source is the media library: the built-in path cannot work. Measured 2026-07-27 on Oxygen 6.2.0-beta.2, re-measured 2026-08-21 on 6.2.0-beta.5 and unchanged (the wpmedia control still schemas media as {id, url, alt} with additionalProperties: false, and both OxygenElements\\Image and EssentialElements\\Image2 still resolve src as content.image.media.sizes[size].url) — an OxygenElements\\Image with from: media_library and size: "large" renders the builder\'s 540x540 grey placeholder while edit-post reports success and reads the data back exactly as written. The element resolves its src as content.image.media.sizes[size].url, but the MCP schema declares media with only id, url and alt plus additionalProperties: false, so sizes can never be set and the lookup falls through to the placeholder. attributes.srcset is unreachable the same way, so API-created images emit no srcset at all. This ability fills both maps from WordPress\'s own attachment metadata and writes them into the element tree, then recompiles the post\'s CSS. Passing a size the attachment does not have is the one way to still get a placeholder, so unknown sizes are rejected with the list of real ones. Upstream report: soflyy/agent-connector-for-wp#79.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id'       => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post, template, header, footer or component holding the element.',
				),
				'element_id'    => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Tree node id of the image element (from get-post-tree).',
				),
				'attachment_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Media library attachment id. Use search-media to find one.',
				),
				'size'          => array(
					'type'        => 'string',
					'default'     => 'full',
					'description' => 'Registered image size to render, e.g. "full", "large", "medium". Must exist for this attachment; the ability returns the available list when it does not.',
				),
				'alt'           => array(
					'type'        => 'string',
					'description' => 'Alt text to write onto the element. Omit to keep the element reading the attachment\'s own alt text, which is empty on most uploads and then renders no alt attribute at all.',
				),
				'regenerate'    => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Recompile the post CSS afterwards. Leave on unless you are batching several writes.',
				),
			),
			'required'             => array( 'post_id', 'element_id', 'attachment_id' ),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'          => array( 'type' => 'integer' ),
				'element_id'       => array( 'type' => 'integer' ),
				'element_type'     => array( 'type' => 'string' ),
				'size'             => array( 'type' => 'string' ),
				'url'              => array( 'type' => 'string' ),
				'srcset'           => array( 'type' => 'string' ),
				'alt_mode'         => array( 'type' => 'string' ),
				'available_sizes'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'regenerated'      => array( 'type' => 'boolean' ),
			),
		),
		'annotations'      => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$post_id       = (int) $input['post_id'];
			$element_id    = (int) $input['element_id'];
			$attachment_id = (int) $input['attachment_id'];
			$size          = (string) ( $input['size'] ?? 'full' );
			$regenerate    = (bool) ( $input['regenerate'] ?? true );

			if ( 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
				return new WP_Error(
					'bdox_not_an_image',
					sprintf( 'Attachment %d is not an image in the media library.', $attachment_id ),
					array( 'status' => 404 )
				);
			}

			$sizes = bdox_pack_attachment_sizes( $attachment_id );
			if ( ! isset( $sizes[ $size ] ) ) {
				return new WP_Error(
					'bdox_unknown_size',
					sprintf(
						'Attachment %d has no size "%s". Available: %s.',
						$attachment_id,
						$size,
						implode( ', ', array_keys( $sizes ) )
					),
					array( 'status' => 400 )
				);
			}

			$tree = bdox_pack_get_tree( $post_id );
			if ( null === $tree ) {
				return new WP_Error(
					'bdox_not_a_builder_post',
					sprintf( 'Post %d has no builder tree.', $post_id ),
					array( 'status' => 404 )
				);
			}

			// The element does NOT render media.alt from the tree. Its template reads
			// get_attachment_alt(content.image.media.id) when content.image.alt is
			// "from_media_library", and content.image.custom_alt when it is "custom".
			// Measured 2026-07-27: an attachment with no _wp_attachment_image_alt
			// renders an <img> with no alt attribute at all. So a caller-supplied alt
			// has to switch the element to custom mode to survive.
			$alt = (string) ( $input['alt'] ?? '' );

			$media = array(
				'id'         => $attachment_id,
				'url'        => $sizes['full']['url'],
				'alt'        => '' !== $alt ? $alt : (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'sizes'      => $sizes,
				'attributes' => array(
					'srcset' => (string) wp_get_attachment_image_srcset( $attachment_id, $size ),
					'sizes'  => (string) wp_get_attachment_image_sizes( $attachment_id, $size ),
				),
			);

			$type  = '';
			$found = false;

			$tree['root'] = bdox_pack_map_node(
				$tree['root'],
				$element_id,
				static function ( array $node ) use ( $media, $size, $alt, &$type ): array {
					$type = (string) ( $node['data']['type'] ?? '' );

					$image = $node['data']['properties']['content']['image'] ?? array();
					$image = is_array( $image ) ? $image : array();

					$image['from']  = 'media_library';
					$image['media'] = $media;
					$image['size']  = $size;

					if ( '' !== $alt ) {
						$image['alt']        = 'custom';
						$image['custom_alt'] = $alt;
					} elseif ( ! isset( $image['alt'] ) ) {
						$image['alt'] = 'from_media_library';
					}

					$node['data']['properties']['content']['image'] = $image;

					return $node;
				},
				$found
			);

			if ( ! $found ) {
				return new WP_Error(
					'bdox_element_not_found',
					sprintf( 'Post %d has no element with id %d.', $post_id, $element_id ),
					array( 'status' => 404 )
				);
			}

			bdox_pack_save_tree( $post_id, $tree );

			if ( $regenerate ) {
				bdox_pack_regenerate_posts( array( $post_id ) );
			}

			return array(
				'post_id'         => $post_id,
				'element_id'      => $element_id,
				'element_type'    => $type,
				'size'            => $size,
				'url'             => $sizes[ $size ]['url'],
				'srcset'          => $media['attributes']['srcset'],
				'alt_mode'        => '' !== $alt ? 'custom' : 'from_media_library (attachment alt: ' . ( '' !== $media['alt'] ? 'set' : 'EMPTY — no alt attribute will render' ) . ')',
				'available_sizes' => array_keys( $sizes ),
				'regenerated'     => $regenerate,
			);
		},
	)
);
