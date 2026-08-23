<?php
/**
 * A restore point before the work starts, and an honest answer to whether it exists.
 *
 * This drives UpdraftPlus; it does not modify it. The plugin is called through its
 * own public backup hook, exactly as its "Backup Now" button does.
 *
 * Why the pack carries this at all: an agent that is about to rebuild somebody
 * else's site could always have taken a backup — through php-eval anything is
 * possible. What nobody guesses is `do_action( 'updraft_backupnow_backup_all', … )`
 * with the right options array. An ability with a description is the difference
 * between "could have taken a backup" and "took a backup".
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Guard: without the plugin on this request there is nothing to drive.
 *
 * @return true|WP_Error
 */
function bdox_pack_backup_guard() {
	if ( ! class_exists( 'UpdraftPlus_Backup_History' ) ) {
		return new WP_Error(
			'bdox_updraft_missing',
			'UpdraftPlus nie jest zaladowany na tym zadaniu. Sprawdz, czy wtyczka jest wlaczona.',
			array( 'status' => 500 )
		);
	}
	return true;
}

/**
 * One history entry flattened to the fields that mean something.
 *
 * @param int|string $stamp Timestamp (the history key).
 * @param array      $wpis  History entry.
 * @return array<string, mixed>
 */
function bdox_pack_backup_entry( $stamp, array $wpis ): array {
	$czesci = array();
	foreach (
		array(
			'db'      => 'baza',
			'plugins' => 'wtyczki',
			'themes'  => 'motywy',
			'uploads' => 'media',
			'others'  => 'inne',
		) as $klucz => $nazwa
	) {
		if ( ! empty( $wpis[ $klucz ] ) ) {
			$czesci[] = $nazwa;
		}
	}

	return array(
		'timestamp' => (int) $stamp,
		'date'      => gmdate( 'Y-m-d H:i:s', (int) $stamp ) . ' UTC',
		'contains'  => $czesci,
		'label'     => isset( $wpis['label'] ) ? (string) $wpis['label'] : '',
		/* Where the archive landed. An empty service means it sits on this server
		   only — worth knowing before treating the backup as a way back. */
		'service'   => isset( $wpis['service'] ) ? $wpis['service'] : 'none',
	);
}

/**
 * Register both abilities, but only where UpdraftPlus is active.
 *
 * On the hook rather than at include time, for the same reason as woo-map:
 * plugin load order is the order of the `active_plugins` option and nothing in
 * this file may assume it runs after the plugin it talks to.
 *
 * @return void
 */
function bdox_pack_register_backup(): void {
	if ( ! class_exists( 'UpdraftPlus_Backup_History' ) ) {
		return;
	}

	bdox_pack_ability(
		array(
			'name'             => 'updraft/backup-now',
			'label'            => 'Backup Now',
			'description'      => 'Starts an UpdraftPlus backup. CALL THIS BEFORE any destructive or wide-reaching change on a site you did not build: bulk edits, a plugin or builder update, a theme migration, a database rewrite, deleting posts, selectors or global settings. TWO THINGS THAT ARE EASY TO GET WRONG. (1) This returns when the job is QUEUED, not when the backup exists. It has to work that way: firing the UpdraftPlus hook directly archives synchronously, and on a WooCommerce site with media that blew past a 120-second MCP timeout mid-backup, leaving a half-finished job — so this schedules the same hook through WP-Cron and returns immediately. Never report "backup done" off this call; poll updraft/list-backups with newer_than set to the returned started_after until an entry appears. On a store with tens of gigabytes of uploads a full backup runs for tens of minutes, so prefer what="database" when the change only touches content, selectors or settings — it finishes in seconds and is a real restore point for that class of change. (2) If the response has cron_disabled = true, NOTHING will run the job on its own and there will be no backup — say so instead of proceeding.',
			'category'         => 'updraft',
			'input_schema'     => array(
				'type'                 => 'object',
				'properties'           => array(
					'what'  => array(
						'type'        => 'string',
						'enum'        => array( 'all', 'database', 'files' ),
						'description' => 'Zakres kopii. Domyslnie "all". "database" konczy sie w kilkadziesiat sekund i wystarcza przed zmianami tresci, selektorow i ustawien globalnych.',
					),
					'label' => array(
						'type'        => 'string',
						'description' => 'Krotka notka zapisana przy kopii, np. "przed migracja selektorow". Dzieki niej wlasciwy punkt powrotu da sie potem znalezc.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'queued'         => array( 'type' => 'boolean' ),
					'what'           => array( 'type' => 'string' ),
					'started_after'  => array( 'type' => 'integer' ),
					'backups_before' => array( 'type' => 'integer' ),
					'cron_disabled'  => array( 'type' => 'boolean' ),
					'note'           => array( 'type' => 'string' ),
				),
			),
			'annotations'      => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
			'execute_callback' => static function ( array $input ) {
				$guard = bdox_pack_backup_guard();
				if ( is_wp_error( $guard ) ) {
					return $guard;
				}

				$co    = isset( $input['what'] ) ? (string) $input['what'] : 'all';
				$akcje = array(
					'all'      => 'updraft_backupnow_backup_all',
					'database' => 'updraft_backupnow_backup_database',
					'files'    => 'updraft_backupnow_backup',
				);
				if ( ! isset( $akcje[ $co ] ) ) {
					return new WP_Error( 'bdox_zly_zakres', 'Nieznany zakres kopii: ' . $co );
				}

				$przed = count( (array) UpdraftPlus_Backup_History::get_history() );
				$teraz = time();

				/* The same options the panel's "Backup Now" button sends. `always_keep`
				   holds the backup out of rotation — a pre-migration copy has to outlive
				   the next few automatic backups, or it drops out of history before
				   anybody needs it. */
				$opcje = array(
					'nocloud'       => 0,
					'use_nonce'     => '',
					'use_timestamp' => '',
					'incremental'   => false,
					'always_keep'   => true,
					'label'         => isset( $input['label'] ) ? (string) $input['label'] : 'agent',
				);

				$zaplanowano    = wp_schedule_single_event( $teraz + 5, $akcje[ $co ], array( $opcje ) );
				$cron_wylaczony = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

				/* Without this the event waits for a random visitor to the site. */
				if ( ! $cron_wylaczony ) {
					spawn_cron();
				}

				return array(
					'queued'         => false !== $zaplanowano,
					'what'           => $co,
					'started_after'  => $teraz,
					'backups_before' => $przed,
					'cron_disabled'  => $cron_wylaczony,
					'note'           => $cron_wylaczony
						? 'UWAGA: DISABLE_WP_CRON jest wlaczone. Zdarzenie stoi w kolejce, ale nic go samo nie odpali — potrzebny zewnetrzny cron albo `wp cron event run ' . $akcje[ $co ] . '`. NIE zakladaj, ze kopia powstanie.'
						: 'Kopia zakolejkowana, NIE ukonczona. Odpytuj updraft/list-backups z newer_than = started_after, az pojawi sie wpis. Przy duzych mediach to kilkadziesiat minut — jesli tyle nie masz, zrob kopie z what="database".',
				);
			},
		)
	);

	bdox_pack_ability(
		array(
			'name'             => 'updraft/list-backups',
			'label'            => 'List Backups',
			'description'      => 'Lists UpdraftPlus backups, newest first: timestamp, readable date, what each one contains (baza / wtyczki / motywy / media / inne), its label, and whether it went to remote storage or sits on this server only. Two uses. (1) Confirm a job started with updraft/backup-now has actually FINISHED — an entry appears in history only once its archives are written, so a new timestamp is the completion signal; pass newer_than = started_after to poll for exactly that job. (2) Before destructive work, check whether a usable backup already exists: if the newest is days old, or contains only "baza" while you are about to touch files, take a fresh one instead of trusting it.',
			'category'         => 'updraft',
			'input_schema'     => array(
				'type'                 => 'object',
				'properties'           => array(
					'limit'      => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => 'Ile najnowszych kopii zwrocic. Domyslnie 10.',
					),
					'newer_than' => array(
						'type'        => 'integer',
						'description' => 'Zwroc tylko kopie nowsze niz ten znacznik czasu. Podaj "started_after" z updraft/backup-now, zeby odpytywac o konkretne zadanie.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'count'   => array( 'type' => 'integer' ),
					'backups' => array( 'type' => 'array' ),
				),
			),
			'annotations'      => array(
				'readonly'   => true,
				'idempotent' => true,
			),
			'execute_callback' => static function ( array $input ) {
				$guard = bdox_pack_backup_guard();
				if ( is_wp_error( $guard ) ) {
					return $guard;
				}

				$historia = (array) UpdraftPlus_Backup_History::get_history();
				krsort( $historia );

				$prog  = isset( $input['newer_than'] ) ? (int) $input['newer_than'] : 0;
				$limit = isset( $input['limit'] ) ? (int) $input['limit'] : 10;

				$out = array();
				foreach ( $historia as $stamp => $wpis ) {
					if ( (int) $stamp <= $prog ) {
						continue;
					}
					$out[] = bdox_pack_backup_entry( $stamp, (array) $wpis );
					if ( count( $out ) >= $limit ) {
						break;
					}
				}

				return array(
					'count'   => count( $out ),
					'backups' => $out,
				);
			},
		)
	);
}

add_action( 'wp_abilities_api_init', 'bdox_pack_register_backup', 5 );

ACFW_Pack::category(
	'updraft',
	array(
		'label'       => 'UpdraftPlus',
		'description' => 'Kopia zapasowa przed pracą na cudzej witrynie i sprawdzenie, czy naprawdę powstała.',
	)
);
