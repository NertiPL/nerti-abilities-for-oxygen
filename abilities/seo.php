<?php
/**
 * Tytuł i opis strony bez wtyczki SEO.
 *
 * Powód (zmierzone 16.09.2026 na try.local, demo „Po godzinach”): przeniesienie 1:1 z designu było
 * „zgodne” element po elemencie, a WordPress oddawał <title>try</title> i zero meta description —
 * design miał jedno i drugie na każdej stronie. Oxygen nie ma pola na tytuł dokumentu ani na opis,
 * a instalacja bez Yoasta/Rank Math nie ma gdzie ich zapisać. Ta paczka daje dwa pola w meta posta
 * i dwa haki, które je wypisują; jeśli na witrynie stoi wtyczka SEO, ona wygrywa (haki ustępują).
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

const NERTI_SEO_TYTUL = '_nerti_seo_tytul';
const NERTI_SEO_OPIS  = '_nerti_seo_opis';

/**
 * Czy inna wtyczka już pisze <title> i description — wtedy nasze haki milczą, żeby nie było dwóch opisów.
 */
function nerti_seo_obca_wtyczka(): bool {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
}

add_filter(
	'pre_get_document_title',
	static function ( $title ) {
		if ( nerti_seo_obca_wtyczka() || ! is_singular() ) {
			return $title;
		}
		$wlasny = get_post_meta( get_queried_object_id(), NERTI_SEO_TYTUL, true );
		return is_string( $wlasny ) && '' !== $wlasny ? $wlasny : $title;
	},
	20
);

add_action(
	'wp_head',
	static function () {
		if ( nerti_seo_obca_wtyczka() || ! is_singular() ) {
			return;
		}
		$opis = get_post_meta( get_queried_object_id(), NERTI_SEO_OPIS, true );
		if ( is_string( $opis ) && '' !== $opis ) {
			echo '<meta name="description" content="' . esc_attr( $opis ) . '">' . "\n";
		}
	},
	1
);

bdox_pack_ability(
	array(
		'name'             => 'nerti/set-seo',
		'label'            => 'Set page title and meta description',
		'description'      => 'Writes the document <title> and the meta description of one post into post meta (_nerti_seo_tytul, _nerti_seo_opis) and outputs them on the front end through pre_get_document_title and wp_head — unless Yoast, Rank Math, SEOPress or AIOSEO is active, in which case that plugin owns the head and this ability still stores the values but they are not printed. Use it in a 1:1 transfer from a static design: element-by-element fidelity says nothing about <head>, and a page whose title is the site name has half of its SEO missing. Empty string clears a field. Working rule: read the page after writing (front-end HTML), never assume the head changed.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id'     => array( 'type' => 'integer', 'minimum' => 1 ),
				'title'       => array( 'type' => 'string', 'description' => 'Full document title, exactly as it should appear in <title>. Empty clears.' ),
				'description' => array( 'type' => 'string', 'description' => 'Meta description, plain text. Empty clears.' ),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		),
		'output_schema'    => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'            => array( 'type' => 'integer' ),
				'title'              => array( 'type' => 'string' ),
				'description'        => array( 'type' => 'string' ),
				'printed_by_pack'    => array( 'type' => 'boolean', 'description' => 'false when an SEO plugin owns the head.' ),
			),
		),
		'annotations'      => array( 'readonly' => false, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$id = (int) $input['post_id'];
			if ( ! get_post( $id ) ) {
				return new WP_Error( 'nerti_seo_post', "Post $id nie istnieje.", array( 'status' => 404 ) );
			}
			foreach ( array( 'title' => NERTI_SEO_TYTUL, 'description' => NERTI_SEO_OPIS ) as $pole => $klucz ) {
				if ( ! array_key_exists( $pole, $input ) ) {
					continue;
				}
				$wartosc = sanitize_text_field( (string) $input[ $pole ] );
				if ( '' === $wartosc ) {
					delete_post_meta( $id, $klucz );
				} else {
					update_post_meta( $id, $klucz, $wartosc );
				}
			}
			return array(
				'post_id'         => $id,
				'title'           => (string) get_post_meta( $id, NERTI_SEO_TYTUL, true ),
				'description'     => (string) get_post_meta( $id, NERTI_SEO_OPIS, true ),
				'printed_by_pack' => ! nerti_seo_obca_wtyczka(),
			);
		},
	)
);

bdox_pack_ability(
	array(
		'name'             => 'nerti/get-seo',
		'label'            => 'Get page title and meta description',
		'description'      => 'Reads the stored document title and meta description of one post (see nerti/set-seo) and reports whether this pack or an SEO plugin prints them.',
		'category'         => 'bdox',
		'input_schema'     => array(
			'type'                 => 'object',
			'properties'           => array( 'post_id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		),
		'annotations'      => array( 'readonly' => true, 'idempotent' => true ),
		'execute_callback' => static function ( array $input ) {
			$id = (int) $input['post_id'];
			return array(
				'post_id'         => $id,
				'title'           => (string) get_post_meta( $id, NERTI_SEO_TYTUL, true ),
				'description'     => (string) get_post_meta( $id, NERTI_SEO_OPIS, true ),
				'printed_by_pack' => ! nerti_seo_obca_wtyczka(),
			);
		},
	)
);
