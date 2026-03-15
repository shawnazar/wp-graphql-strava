<?php
/**
 * Self-hosted update checker for GitHub releases.
 *
 * Hooks into WordPress's plugin update system so manually-installed
 * copies (from GitHub) receive update notifications and can be
 * updated from the WordPress admin dashboard.
 *
 * WordPress.org installs are unaffected — the official updater
 * takes priority when the plugin is installed from the directory.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub repository owner/name.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_GITHUB_REPO', 'shawnazar/graphql-strava-activities' );

/**
 * Transient key for caching the GitHub release response.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_UPDATE_CACHE_KEY', 'wpgraphql_strava_github_release' );

/**
 * How long to cache the GitHub release check (seconds).
 *
 * @var int
 */
define( 'WPGRAPHQL_STRAVA_UPDATE_CACHE_TTL', 12 * HOUR_IN_SECONDS );

/**
 * Fetch the latest release data from GitHub, with transient caching.
 *
 * @return array<string, mixed>|null Release data or null on failure.
 */
function wpgraphql_strava_get_github_release(): ?array {
	$cached = get_transient( WPGRAPHQL_STRAVA_UPDATE_CACHE_KEY );

	if ( is_array( $cached ) && ! empty( $cached ) ) {
		return $cached;
	}

	$url      = 'https://api.github.com/repos/' . WPGRAPHQL_STRAVA_GITHUB_REPO . '/releases/latest';
	$response = wp_remote_get(
		$url,
		[
			'timeout' => 10,
			'headers' => [
				'Accept' => 'application/vnd.github.v3+json',
			],
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		// Cache the failure briefly to avoid hammering the API.
		set_transient( WPGRAPHQL_STRAVA_UPDATE_CACHE_KEY, [ 'error' => true ], HOUR_IN_SECONDS );
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
		return null;
	}

	$release = [
		'version'     => ltrim( (string) $data['tag_name'], 'v' ),
		'tag_name'    => (string) $data['tag_name'],
		'body'        => (string) ( $data['body'] ?? '' ),
		'html_url'    => (string) ( $data['html_url'] ?? '' ),
		'published_at' => (string) ( $data['published_at'] ?? '' ),
		'zip_url'     => '',
	];

	// Find the release zip asset.
	if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
		foreach ( $data['assets'] as $asset ) {
			if ( isset( $asset['browser_download_url'] ) && str_ends_with( (string) $asset['browser_download_url'], '.zip' ) ) {
				$release['zip_url'] = (string) $asset['browser_download_url'];
				break;
			}
		}
	}

	// Fallback to the GitHub-generated zipball if no asset zip found.
	if ( empty( $release['zip_url'] ) && ! empty( $data['zipball_url'] ) ) {
		$release['zip_url'] = (string) $data['zipball_url'];
	}

	set_transient( WPGRAPHQL_STRAVA_UPDATE_CACHE_KEY, $release, WPGRAPHQL_STRAVA_UPDATE_CACHE_TTL );

	return $release;
}

/**
 * Inject update information into the plugin update transient.
 *
 * Fires on `pre_set_site_transient_update_plugins`.
 *
 * @param object $transient Update transient data.
 * @return object Modified transient data.
 */
function wpgraphql_strava_check_for_updates( object $transient ): object {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$release = wpgraphql_strava_get_github_release();

	if ( null === $release || ! empty( $release['error'] ) || empty( $release['version'] ) ) {
		return $transient;
	}

	// Only show update if the remote version is newer.
	if ( ! version_compare( $release['version'], WPGRAPHQL_STRAVA_VERSION, '>' ) ) {
		return $transient;
	}

	$plugin_file = plugin_basename( WPGRAPHQL_STRAVA_DIR . 'graphql-strava-activities.php' );

	$transient->response[ $plugin_file ] = (object) [
		'slug'        => 'graphql-strava-activities',
		'plugin'      => $plugin_file,
		'new_version' => $release['version'],
		'url'         => 'https://github.com/' . WPGRAPHQL_STRAVA_GITHUB_REPO,
		'package'     => $release['zip_url'],
		'icons'       => [],
		'banners'     => [],
	];

	return $transient;
}

add_filter( 'pre_set_site_transient_update_plugins', 'wpgraphql_strava_check_for_updates' );

/**
 * Provide plugin information for the "View details" modal.
 *
 * Fires on `plugins_api`.
 *
 * @param false|object|array<string,mixed> $result Default result.
 * @param string                           $action API action.
 * @param object                           $args   Request arguments.
 * @return false|object Modified result or false to defer.
 */
function wpgraphql_strava_plugin_info( $result, string $action, object $args ) {
	if ( 'plugin_information' !== $action || 'graphql-strava-activities' !== ( $args->slug ?? '' ) ) {
		return $result;
	}

	$release = wpgraphql_strava_get_github_release();

	if ( null === $release || ! empty( $release['error'] ) ) {
		return $result;
	}

	return (object) [
		'name'            => 'GraphQL Strava Activities',
		'slug'            => 'graphql-strava-activities',
		'version'         => $release['version'],
		'author'          => '<a href="https://shawnazar.me">Shawn Azar</a>',
		'homepage'        => 'https://github.com/' . WPGRAPHQL_STRAVA_GITHUB_REPO,
		'requires'        => '6.0',
		'tested'          => '6.9',
		'requires_php'    => '8.2',
		'download_link'   => $release['zip_url'],
		'trunk'           => $release['zip_url'],
		'last_updated'    => $release['published_at'],
		'sections'        => [
			'description' => 'Compatible with Strava — extends WPGraphQL with activity data, server-side SVG route maps, and photos.',
			'changelog'   => nl2br( esc_html( $release['body'] ) ),
		],
	];
}

add_filter( 'plugins_api', 'wpgraphql_strava_plugin_info', 10, 3 );

/**
 * Clear the update cache when the plugin is updated.
 *
 * Fires on `upgrader_process_complete`.
 *
 * @param object               $upgrader WP_Upgrader instance.
 * @param array<string, mixed> $options  Update details.
 * @return void
 */
function wpgraphql_strava_clear_update_cache( object $upgrader, array $options ): void {
	if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) ) {
		delete_transient( WPGRAPHQL_STRAVA_UPDATE_CACHE_KEY );
	}
}

add_action( 'upgrader_process_complete', 'wpgraphql_strava_clear_update_cache', 10, 2 );
