<?php
/**
 * Update checks against GitHub Releases.
 *
 * WordPress 5.8+ ships a hook built for exactly this: declare an `Update URI`
 * header and WP fires `update_plugins_<hostname>` for that plugin alone. No
 * update-checker library, no cron, no settings page, and — importantly — no
 * filtering of the global `site_transient_update_plugins`, which is the usual
 * way self-hosted updaters break unrelated plugins.
 *
 * @package BdoxAbilityPack
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

const BDOX_PACK_REPO           = 'NertiPL/unofficial-abilities-for-breakdance-elements';
const BDOX_PACK_UPDATE_TTL     = 12 * HOUR_IN_SECONDS;
const BDOX_PACK_UPDATE_CACHE   = 'bdox_pack_latest_release';

/**
 * Latest release from the GitHub API, cached.
 *
 * Cached on failure too, as a short-lived empty marker. Without that, a repo
 * that is unreachable (rate limit, network, renamed) means an API call on every
 * admin page load.
 *
 * @param bool $force Skip the cache.
 * @return array{version:string,package:string,url:string,published:string}|null
 */
function bdox_pack_latest_release( bool $force = false ): ?array {
	if ( ! $force ) {
		$cached = get_site_transient( BDOX_PACK_UPDATE_CACHE );
		if ( is_array( $cached ) ) {
			// An empty array is the cached-failure marker, not a release.
			return empty( $cached['version'] ) ? null : $cached;
		}
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . BDOX_PACK_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'bdox-ability-pack/' . BDOX_PACK_VERSION,
			),
		)
	);

	$release = null;

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_array( $body ) && ! empty( $body['tag_name'] ) && empty( $body['draft'] ) ) {
			// Prefer an uploaded .zip asset over GitHub's source tarball: the
			// asset is the built plugin, the tarball carries repo scaffolding.
			$package = '';
			foreach ( (array) ( $body['assets'] ?? array() ) as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && str_ends_with( (string) $asset['name'], '.zip' ) ) {
					$package = (string) $asset['browser_download_url'];
					break;
				}
			}

			if ( '' === $package ) {
				$package = (string) ( $body['zipball_url'] ?? '' );
			}

			if ( '' !== $package ) {
				$release = array(
					'version'   => ltrim( (string) $body['tag_name'], 'vV' ),
					'package'   => $package,
					'url'       => (string) ( $body['html_url'] ?? '' ),
					'published' => (string) ( $body['published_at'] ?? '' ),
				);
			}
		}
	}

	set_site_transient(
		BDOX_PACK_UPDATE_CACHE,
		$release ?? array(),
		$release ? BDOX_PACK_UPDATE_TTL : 15 * MINUTE_IN_SECONDS
	);

	return $release;
}

/**
 * Answer WordPress's update question for this plugin only.
 *
 * Fired by WP because the plugin header declares `Update URI` on github.com.
 *
 * @param array|false $update      Update payload assembled so far.
 * @param array       $plugin_data Parsed plugin headers.
 * @param string      $plugin_file Plugin file, relative to the plugins dir.
 * @return array|false
 */
function bdox_pack_check_update( $update, array $plugin_data, string $plugin_file ) {
	// Another plugin's Update URI can share the host; only answer for ours.
	if ( ! str_contains( (string) ( $plugin_data['UpdateURI'] ?? '' ), BDOX_PACK_REPO ) ) {
		return $update;
	}

	// "Check again" on the updates screen must mean it. Without this the 12-hour
	// cache makes a freshly published release invisible for up to half a day,
	// and the button looks broken.
	$force = isset( $_GET['force-check'] ) && '' !== $_GET['force-check']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$release = bdox_pack_latest_release( $force );
	if ( null === $release ) {
		return $update;
	}

	// Return the payload even when we are already current. WordPress decides
	// which bucket it belongs in: a newer version goes to `response`, anything
	// else to `no_update`. Returning false when up to date would keep the
	// plugin out of BOTH, and a plugin in neither bucket gets no "Enable
	// auto-updates" control on the plugins screen — which reads as "updates
	// are not wired up" even though they are.
	return array(
		'id'           => 'github.com/' . BDOX_PACK_REPO,
		'slug'         => dirname( $plugin_file ),
		'plugin'       => $plugin_file,
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires_php' => (string) ( $plugin_data['RequiresPHP'] ?? '8.1' ),
	);
}
add_filter( 'update_plugins_github.com', 'bdox_pack_check_update', 10, 3 );

/**
 * Rename the extracted folder back to the plugin's own directory name.
 *
 * A GitHub ZIP unpacks to whatever the archive happens to carry — a versioned
 * folder for an uploaded asset, `repo-<sha>` for a tarball. Without this,
 * WordPress installs the update as a SEPARATE plugin and deactivates the one
 * being updated, which reads to the user as "the update broke the site".
 *
 * @param string      $source        Directory the archive was extracted to.
 * @param string      $remote_source Parent temp directory.
 * @param \WP_Upgrader $upgrader     Upgrader instance.
 * @param array       $extra         Hook args; carries the plugin file on updates.
 * @return string|\WP_Error
 */
function bdox_pack_fix_update_folder( $source, $remote_source, $upgrader, $extra = array() ) {
	$plugin = (string) ( $extra['plugin'] ?? '' );

	if ( '' === $plugin || ! str_contains( $plugin, 'unofficial-abilities-for-breakdance-elements' ) ) {
		return $source;
	}

	$desired = trailingslashit( $remote_source ) . dirname( $plugin );

	if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
		return $source;
	}

	global $wp_filesystem;
	if ( ! $wp_filesystem || ! $wp_filesystem->move( $source, $desired, true ) ) {
		return new WP_Error(
			'bdox_pack_rename_failed',
			'Could not normalise the extracted plugin folder name. Install the ZIP manually from the releases page.'
		);
	}

	return trailingslashit( $desired );
}
add_filter( 'upgrader_source_selection', 'bdox_pack_fix_update_folder', 10, 4 );

/**
 * Force one fresh update check after this plugin's own version changes.
 *
 * The update that installs a new version runs entirely inside the OLD version's
 * process, so the `update_plugins` transient it leaves behind was written by the
 * code being replaced — and WordPress then throttles the next check for an hour.
 * Measured on 0.6.0 → 0.6.1: the plugin sat in neither the `response` nor the
 * `no_update` bucket afterwards, so the plugins screen said auto-updates were
 * unavailable even though the fix for exactly that was already installed.
 * Comparing the stored version against the running one costs one option read and
 * fires once per version.
 */
function bdox_pack_reset_update_cache_on_version_change(): void {
	if ( get_site_option( 'bdox_pack_installed_version' ) === BDOX_PACK_VERSION ) {
		return;
	}

	update_site_option( 'bdox_pack_installed_version', BDOX_PACK_VERSION );
	delete_site_transient( BDOX_PACK_UPDATE_CACHE );
	delete_site_transient( 'update_plugins' );
}
add_action( 'init', 'bdox_pack_reset_update_cache_on_version_change' );
