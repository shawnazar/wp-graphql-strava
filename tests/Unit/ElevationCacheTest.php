<?php
/**
 * Unit tests for elevation profile SVG, user-level encryption, and object cache wrapper.
 *
 * Uses Brain\Monkey to mock WordPress functions.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

namespace GraphQLStrava\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

// Load dependencies that have no WP function calls at file level.
if ( ! function_exists( 'wpgraphql_strava_decode_polyline' ) ) {
	require_once dirname( __DIR__, 2 ) . '/includes/polyline.php';
}

// Define encryption key for tests (64-char hex = 32 bytes).
if ( ! defined( 'WPGRAPHQL_STRAVA_ENCRYPTION_KEY' ) ) {
	define( 'WPGRAPHQL_STRAVA_ENCRYPTION_KEY', bin2hex( random_bytes( 32 ) ) );
}

class ElevationCacheTest extends TestCase {

	/**
	 * Valid encoded polyline for testing.
	 *
	 * @var string
	 */
	private const POLYLINE = 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress functions used by svg.php.
		Functions\stubTranslationFunctions();

		Functions\when( 'get_option' )->alias(
			function ( string $option, $default = '' ) {
				$options = [
					'wpgraphql_strava_svg_color'        => '#0d9488',
					'wpgraphql_strava_svg_stroke_width'  => 2.5,
				];
				return $options[ $option ] ?? $default;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			function ( string $tag, $value ) {
				return $value;
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

		// Load plugin files that define functions (guarded to avoid redeclaration).
		if ( ! function_exists( 'wpgraphql_strava_polyline_to_svg' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/svg.php';
		}
		if ( ! defined( 'WPGRAPHQL_STRAVA_CIPHER' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/encryption.php';
		}
		if ( ! function_exists( 'wpgraphql_strava_cache_get' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/cache.php';
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Elevation profile SVG tests (#167)
	// -------------------------------------------------------------------------

	/**
	 * Test that a valid polyline with elevation gain returns SVG with ep-fill and ep-line classes.
	 */
	public function test_elevation_profile_returns_svg(): void {
		$svg = wpgraphql_strava_elevation_profile_svg( self::POLYLINE, 150.0 );

		$this->assertStringStartsWith( '<svg', $svg );
		$this->assertStringEndsWith( '</svg>', $svg );
		$this->assertStringContainsString( 'ep-fill', $svg );
		$this->assertStringContainsString( 'ep-line', $svg );
	}

	/**
	 * Test that a polyline with fewer than 3 points returns empty string.
	 */
	public function test_elevation_profile_empty_on_insufficient_points(): void {
		// Single coordinate pair: '??'  decodes to one point.
		$svg = wpgraphql_strava_elevation_profile_svg( '??', 100.0 );

		$this->assertSame( '', $svg );
	}

	/**
	 * Test that elevation_gain=0 returns empty string.
	 */
	public function test_elevation_profile_empty_on_zero_elevation(): void {
		$svg = wpgraphql_strava_elevation_profile_svg( self::POLYLINE, 0.0 );

		$this->assertSame( '', $svg );
	}

	/**
	 * Test that the elevation profile SVG contains a dark mode media query.
	 */
	public function test_elevation_profile_has_dark_mode(): void {
		$svg = wpgraphql_strava_elevation_profile_svg( self::POLYLINE, 150.0 );

		$this->assertStringContainsString( 'prefers-color-scheme:dark', $svg );
	}

	// -------------------------------------------------------------------------
	// User encryption tests (#168)
	// -------------------------------------------------------------------------

	/**
	 * Test that get_user_option decrypts an encrypted value from user meta.
	 */
	public function test_get_user_option_returns_decrypted(): void {
		$plain     = 'my-secret-token';
		$encrypted = wpgraphql_strava_encrypt( $plain );

		Functions\expect( 'get_user_meta' )
			->once()
			->with( 1, 'wpgraphql_strava_access_token', true )
			->andReturn( $encrypted );

		$result = wpgraphql_strava_get_user_option( 1, 'wpgraphql_strava_access_token' );

		$this->assertSame( $plain, $result );
	}

	/**
	 * Test that get_user_option returns the default when meta is empty.
	 */
	public function test_get_user_option_returns_default(): void {
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 1, 'wpgraphql_strava_access_token', true )
			->andReturn( '' );

		$result = wpgraphql_strava_get_user_option( 1, 'wpgraphql_strava_access_token', 'fallback' );

		$this->assertSame( 'fallback', $result );
	}

	/**
	 * Test that update_user_option encrypts a sensitive key before storing.
	 */
	public function test_update_user_option_encrypts(): void {
		Functions\expect( 'update_user_meta' )
			->once()
			->with(
				1,
				'wpgraphql_strava_access_token',
				\Mockery::on(
					function ( $value ) {
						// The stored value should be encrypted (starts with "enc:").
						return is_string( $value ) && str_starts_with( $value, 'enc:' );
					}
				)
			)
			->andReturn( true );

		$result = wpgraphql_strava_update_user_option( 1, 'wpgraphql_strava_access_token', 'plain-token' );

		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// Cache wrapper tests (#170)
	// -------------------------------------------------------------------------

	/**
	 * Test that cache_get uses get_transient when no external object cache.
	 *
	 * wp_using_ext_object_cache() is stubbed to return false in bootstrap.php,
	 * so the cache wrapper should fall through to the transient functions.
	 */
	public function test_cache_get_uses_transient_by_default(): void {
		Functions\expect( 'get_transient' )
			->once()
			->with( 'test_key' )
			->andReturn( 'cached_value' );

		$result = wpgraphql_strava_cache_get( 'test_key' );

		$this->assertSame( 'cached_value', $result );
	}

	/**
	 * Test that cache_set uses set_transient when no external object cache.
	 */
	public function test_cache_set_uses_transient_by_default(): void {
		Functions\expect( 'set_transient' )
			->once()
			->with( 'test_key', 'test_value', 3600 )
			->andReturn( true );

		$result = wpgraphql_strava_cache_set( 'test_key', 'test_value', 3600 );

		$this->assertTrue( $result );
	}

	/**
	 * Test that cache_delete uses delete_transient when no external object cache.
	 */
	public function test_cache_delete_uses_transient_by_default(): void {
		Functions\expect( 'delete_transient' )
			->once()
			->with( 'test_key' )
			->andReturn( true );

		$result = wpgraphql_strava_cache_delete( 'test_key' );

		$this->assertTrue( $result );
	}
}
