<?php
/**
 * Integration tests for the REST API and OAuth modules.
 *
 * Uses Brain\Monkey to mock WordPress functions.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

namespace GraphQLStrava\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

class RestOAuthTest extends TestCase {

	/**
	 * Simulated transient storage.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * Simulated options storage.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->transients = [];
		$this->options    = [
			'wpgraphql_strava_access_token'     => 'test-token',
			'wpgraphql_strava_client_id'        => '12345',
			'wpgraphql_strava_client_secret'    => 'secret-value',
			'wpgraphql_strava_units'            => 'mi',
			'wpgraphql_strava_svg_color'        => '#0d9488',
			'wpgraphql_strava_svg_stroke_width' => 2.5,
		];

		Functions\stubTranslationFunctions();

		Functions\when( 'get_option' )->alias(
			function ( string $option, $default = '' ) {
				return $this->options[ $option ] ?? $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( string $option, $value ): bool {
				$this->options[ $option ] = $value;
				return true;
			}
		);

		Functions\when( 'get_transient' )->alias(
			function ( string $key ) {
				return $this->transients[ $key ] ?? false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value, int $ttl = 0 ): bool {
				$this->transients[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				unset( $this->transients[ $key ] );
				return true;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			function ( string $tag, $value ) {
				return $value;
			}
		);

		Functions\when( 'wp_strip_all_tags' )->alias(
			function ( string $str ) {
				return strip_tags( $str ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test mock.
			}
		);

		Functions\when( 'sanitize_text_field' )->alias(
			function ( string $str ) {
				return trim( wp_strip_all_tags( $str ) );
			}
		);

		Functions\when( 'esc_url_raw' )->alias(
			function ( string $url ) {
				return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
			}
		);

		Functions\when( 'esc_attr' )->alias(
			function ( string $text ) {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		Functions\when( 'esc_attr__' )->alias(
			function ( string $text ) {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		Functions\when( 'admin_url' )->alias(
			function ( string $path = '' ) {
				return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
			}
		);

		Functions\when( 'wp_create_nonce' )->alias(
			function ( string $action ) {
				return 'nonce_' . $action;
			}
		);

		Functions\when( 'add_query_arg' )->alias(
			function ( $args, string $url = '' ) {
				if ( is_array( $args ) ) {
					return $url . '?' . http_build_query( $args );
				}
				return $url;
			}
		);

		Functions\when( 'wp_parse_url' )->alias(
			function ( string $url, int $component = -1 ) {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test mock.
			}
		);

		Functions\when( 'register_rest_route' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );

		// Load modules in order.
		if ( ! function_exists( 'wpgraphql_strava_encrypt' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/encryption.php';
		}
		require_once dirname( __DIR__, 2 ) . '/includes/polyline.php';
		if ( ! function_exists( 'wpgraphql_strava_polyline_to_svg' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/svg.php';
		}
		if ( ! function_exists( 'wpgraphql_strava_format_duration' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/cache.php';
		}
		if ( ! function_exists( 'wpgraphql_strava_rest_activities' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/rest-api.php';
		}
		if ( ! function_exists( 'wpgraphql_strava_get_oauth_url' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/oauth.php';
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create a mock WP_REST_Request with get_param support.
	 *
	 * @param array<string, mixed> $params Request parameters.
	 * @return \WP_REST_Request&Mockery\MockInterface
	 */
	private function make_request( array $params = [] ): \WP_REST_Request {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )->andReturnUsing(
			function ( string $key ) use ( $params ) {
				return $params[ $key ] ?? null;
			}
		);
		return $request;
	}

	/**
	 * Seed the cache with sample activities.
	 *
	 * @return array<int, array<string, mixed>> The seeded activities.
	 */
	private function seed_activities(): array {
		$activities = [
			[
				'title'    => 'Morning Run',
				'type'     => 'Run',
				'distance' => 5.12,
				'unit'     => 'mi',
			],
			[
				'title'    => 'Evening Ride',
				'type'     => 'Ride',
				'distance' => 20.5,
				'unit'     => 'mi',
			],
			[
				'title'    => 'Afternoon Run',
				'type'     => 'Run',
				'distance' => 3.1,
				'unit'     => 'mi',
			],
		];

		$this->transients['wpgraphql_strava_activities'] = $activities;

		return $activities;
	}

	// -------------------------------------------------------------------------
	// REST API tests (#165).
	// -------------------------------------------------------------------------

	/**
	 * Test REST returns all activities when count is 0.
	 */
	public function test_rest_returns_all_activities(): void {
		$activities = $this->seed_activities();
		$request    = $this->make_request( [
			'count'   => 0,
			'offset'  => 0,
			'type'    => '',
			'user_id' => 0,
		] );

		$response = wpgraphql_strava_rest_activities( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 3, $response->get_data() );
		$this->assertSame( $activities, $response->get_data() );
	}

	/**
	 * Test REST respects count parameter.
	 */
	public function test_rest_respects_count_param(): void {
		$this->seed_activities();
		$request = $this->make_request( [
			'count'   => 2,
			'offset'  => 0,
			'type'    => '',
			'user_id' => 0,
		] );

		$response = wpgraphql_strava_rest_activities( $request );
		$data     = $response->get_data();

		$this->assertCount( 2, $data );
		$this->assertSame( 'Morning Run', $data[0]['title'] );
		$this->assertSame( 'Evening Ride', $data[1]['title'] );
	}

	/**
	 * Test REST respects offset parameter.
	 */
	public function test_rest_respects_offset_param(): void {
		$this->seed_activities();
		$request = $this->make_request( [
			'count'   => 0,
			'offset'  => 1,
			'type'    => '',
			'user_id' => 0,
		] );

		$response = wpgraphql_strava_rest_activities( $request );
		$data     = $response->get_data();

		$this->assertCount( 2, $data );
		$this->assertSame( 'Evening Ride', $data[0]['title'] );
		$this->assertSame( 'Afternoon Run', $data[1]['title'] );
	}

	/**
	 * Test REST filters by activity type.
	 */
	public function test_rest_filters_by_type(): void {
		$this->seed_activities();
		$request = $this->make_request( [
			'count'   => 0,
			'offset'  => 0,
			'type'    => 'Run',
			'user_id' => 0,
		] );

		$response = wpgraphql_strava_rest_activities( $request );
		$data     = $response->get_data();

		$this->assertCount( 2, $data );
		$this->assertSame( 'Morning Run', $data[0]['title'] );
		$this->assertSame( 'Afternoon Run', $data[1]['title'] );
	}

	/**
	 * Test REST sets X-WP-Total header correctly.
	 */
	public function test_rest_returns_total_header(): void {
		$this->seed_activities();
		$request = $this->make_request( [
			'count'   => 1,
			'offset'  => 0,
			'type'    => '',
			'user_id' => 0,
		] );

		$response = wpgraphql_strava_rest_activities( $request );

		// Total should reflect ALL activities before slicing.
		$this->assertSame( '3', $response->headers['X-WP-Total'] );

		// But data should be sliced to 1.
		$this->assertCount( 1, $response->get_data() );
	}

	/**
	 * Test REST returns empty array when no cached data.
	 */
	public function test_rest_returns_empty_array_when_no_data(): void {
		// No activities seeded — transients are empty.
		$request = $this->make_request( [
			'count'   => 0,
			'offset'  => 0,
			'type'    => '',
			'user_id' => 0,
		] );

		// Empty token prevents API fetch attempt.
		$this->options['wpgraphql_strava_access_token'] = '';
		unset( $this->transients['wpgraphql_strava_activities'] );

		$response = wpgraphql_strava_rest_activities( $request );

		$this->assertSame( [], $response->get_data() );
		$this->assertSame( '0', $response->headers['X-WP-Total'] );
	}

	// -------------------------------------------------------------------------
	// OAuth tests (#166).
	// -------------------------------------------------------------------------

	/**
	 * Test OAuth URL contains the client_id parameter.
	 */
	public function test_oauth_url_contains_client_id(): void {
		$this->options['wpgraphql_strava_client_id'] = '12345';

		$url = wpgraphql_strava_get_oauth_url();

		$this->assertNotEmpty( $url );
		$this->assertStringContainsString( 'client_id=12345', $url );
	}

	/**
	 * Test OAuth URL contains a state nonce parameter.
	 */
	public function test_oauth_url_contains_state_nonce(): void {
		$this->options['wpgraphql_strava_client_id'] = '12345';

		$url = wpgraphql_strava_get_oauth_url();

		$this->assertStringContainsString( 'state=nonce_wpgraphql_strava_oauth', $url );
	}

	/**
	 * Test OAuth URL returns empty string when client_id is not set.
	 */
	public function test_oauth_url_empty_without_client_id(): void {
		$this->options['wpgraphql_strava_client_id'] = '';

		$url = wpgraphql_strava_get_oauth_url();

		$this->assertSame( '', $url );
	}

	/**
	 * Test OAuth redirect URI is built from admin_url.
	 */
	public function test_oauth_redirect_uri_is_admin_url(): void {
		$uri = wpgraphql_strava_get_oauth_redirect_uri();

		$this->assertSame(
			'https://example.com/wp-admin/admin.php?page=wpgraphql-strava-settings',
			$uri
		);

		// Verify it looks like a valid admin URL.
		$parsed = wp_parse_url( $uri );
		$this->assertSame( 'example.com', $parsed['host'] );
		$this->assertStringContainsString( 'wp-admin', $parsed['path'] );
	}
}
