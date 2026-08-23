<?php
/**
 * Ability-pack registry.
 *
 * Identical to the registry shipped with the other unofficial packs, and
 * guarded by the same class_exists check, so whichever pack loads first owns
 * the class and the rest reuse it.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/*
 * Deklaracja WARUNKOWA, i to jest cala poprawka.
 *
 * Klasa zadeklarowana bezwarunkowo na najwyzszym poziomie pliku jest wiazana przez
 * PHP JUZ NA ETAPIE KOMPILACJI tego pliku (early binding), czyli ZANIM wykona sie
 * jakikolwiek kod w srodku - w tym straznik `if ( class_exists(...) ) return;`.
 * Kiedy druga paczka abilities kompilowala swoj bootstrap, nazwa byla juz zajeta
 * i PHP przerywalo kompilacje bledem "Cannot declare class ACFW_Pack, because the
 * name is already in use" - straznik nie mial szansy sie odezwac. Objaw: biala
 * strona na kazdej witrynie, gdzie stoja dwie paczki naraz.
 *
 * Klasa zadeklarowana WEWNATRZ bloku `if` nie podlega early bindingowi: powstaje
 * dopiero, gdy wykonanie do niej dojdzie. Dopiero wtedy straznik cokolwiek znaczy.
 *
 * Zmierzone 2026-08-23 na laboria.local, trzy paczki: nerti-abilities-for-oxygen,
 * unofficial-abilities-for-contact-form-7, unofficial-abilities-for-wordpress-seo.
 */
if ( ! class_exists( 'ACFW_Pack' ) ) :

final class ACFW_Pack {

	/** @var array<string,array> ability name => $args (plus 'name'). */
	private static array $abilities = array();

	/** @var array<string,array> category slug => $args. */
	private static array $categories = array();

	/**
	 * Register an ability category. Reuse a slug across ability files freely;
	 * last definition wins.
	 */
	public static function category( string $slug, array $args ): void {
		self::$categories[ $slug ] = $args;
	}

	/**
	 * Queue an ability definition. Do NOT pass a permission_callback —
	 * Agent Connector owns auth.
	 */
	public static function ability( array $def ): void {
		if ( empty( $def['name'] ) || ! is_string( $def['name'] ) ) {
			return;
		}
		self::$abilities[ $def['name'] ] = $def;
	}

	/** Enumerate queued ability definitions (used by coverage tooling). */
	public static function definitions(): array {
		return self::$abilities;
	}

	/** Wire the queued categories + abilities onto the Abilities API hooks. */
	public static function boot(): void {
		add_action(
			'wp_abilities_api_categories_init',
			static function (): void {
				if ( ! function_exists( 'wp_register_ability_category' ) ) {
					return;
				}
				foreach ( self::$categories as $slug => $args ) {
					wp_register_ability_category( $slug, $args );
				}
			}
		);

		add_action(
			'wp_abilities_api_init',
			static function (): void {
				if ( ! function_exists( 'agent_connector_for_wp_register_ability' ) ) {
					return; // Host inactive — register nothing.
				}
				foreach ( self::$abilities as $name => $def ) {
					$args = $def;
					unset( $args['name'] );
					agent_connector_for_wp_register_ability( $name, $args );
				}
			}
		);
	}
}

endif;
