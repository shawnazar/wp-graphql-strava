<?php
/**
 * Integration tests for heatmap, year_review, and trends shortcodes.
 *
 * Uses Brain\Monkey to mock WordPress functions.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

namespace GraphQLStrava\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class NewShortcodesTest extends TestCase {

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

	/**
	 * Sample activities spanning 2025 and 2026 for year filtering.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sample_activities(): array {
		return [
			[
				'title'         => 'Morning Run',
				'distance'      => 5.12,
				'duration'      => '42m',
				'date'          => '2025-06-15T08:00:00Z',
				'type'          => 'Run',
				'unit'          => 'mi',
				'svgMap'        => '<svg viewBox="0 0 300 200"><polyline points="10,20 30,40" /></svg>',
				'stravaUrl'     => 'https://www.strava.com/activities/111',
				'elevationGain' => 150.0,
			],
			[
				'title'         => 'Evening Ride',
				'distance'      => 20.5,
				'duration'      => '1h 5m',
				'date'          => '2025-09-14T18:00:00Z',
				'type'          => 'Ride',
				'unit'          => 'mi',
				'svgMap'        => '<svg viewBox="0 0 300 200"><polyline points="50,60 70,80" /></svg>',
				'stravaUrl'     => 'https://www.strava.com/activities/222',
				'elevationGain' => 320.0,
			],
			[
				'title'         => 'Lunch Run',
				'distance'      => 3.1,
				'duration'      => '25m',
				'date'          => '2025-03-13T12:00:00Z',
				'type'          => 'Run',
				'unit'          => 'mi',
				'svgMap'        => '<svg viewBox="0 0 300 200"><polyline points="90,10 110,30" /></svg>',
				'stravaUrl'     => 'https://www.strava.com/activities/333',
				'elevationGain' => 80.0,
			],
			[
				'title'         => 'New Year Run',
				'distance'      => 10.0,
				'duration'      => '1h 20m',
				'date'          => '2026-01-05T09:00:00Z',
				'type'          => 'Run',
				'unit'          => 'mi',
				'svgMap'        => '<svg viewBox="0 0 300 200"><polyline points="15,25 35,45" /></svg>',
				'stravaUrl'     => 'https://www.strava.com/activities/444',
				'elevationGain' => 200.0,
			],
		];
	}

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define DAY_IN_SECONDS if not already defined.
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$this->transients = [];
		$this->options    = [
			'wpgraphql_strava_access_token'     => 'test-token',
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

		Functions\when( 'esc_html' )->alias(
			function ( string $text ) {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		Functions\when( 'esc_html__' )->alias(
			function ( string $text ) {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		Functions\when( 'esc_url' )->alias(
			function ( string $url ) {
				return $url;
			}
		);

		Functions\when( 'shortcode_atts' )->alias(
			function ( $defaults, $atts ) {
				return array_merge( $defaults, (array) $atts );
			}
		);

		Functions\when( 'wp_date' )->alias(
			function ( string $format, ?int $timestamp = null ) {
				if ( null === $timestamp ) {
					return gmdate( $format );
				}
				return gmdate( $format, $timestamp );
			}
		);

		Functions\when( 'wp_kses' )->alias(
			function ( string $string ) {
				return $string;
			}
		);

		Functions\when( 'add_action' )->justReturn();
		Functions\when( 'add_shortcode' )->justReturn();

		// Load modules in order.
		if ( ! function_exists( 'wpgraphql_strava_encryption_enabled' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/encryption.php';
		}
		require_once dirname( __DIR__, 2 ) . '/includes/polyline.php';
		if ( ! function_exists( 'wpgraphql_strava_polyline_to_svg' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/svg.php';
		}
		if ( ! function_exists( 'wpgraphql_strava_format_duration' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/cache.php';
		}
		if ( ! function_exists( 'wpgraphql_strava_shortcode_activities' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/shortcodes.php';
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Heatmap shortcode tests.
	// ------------------------------------------------------------------

	public function test_heatmap_renders_overlay_div(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_heatmap( [] );

		$this->assertStringContainsString( 'strava-heatmap', $html );
		$this->assertStringContainsString( 'role="img"', $html );
		// Each activity with svgMap should produce an overlay div.
		$this->assertStringContainsString( '<svg', $html );
	}

	public function test_heatmap_respects_dimensions(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_heatmap( [ 'width' => '600', 'height' => '450' ] );

		$this->assertStringContainsString( 'width:600px', $html );
		$this->assertStringContainsString( 'height:450px', $html );
	}

	public function test_heatmap_returns_empty_when_no_routes(): void {
		// Activities with empty svgMap values.
		$activities = [
			[
				'title'         => 'Indoor Run',
				'distance'      => 5.0,
				'duration'      => '30m',
				'date'          => '2025-06-15T08:00:00Z',
				'type'          => 'Run',
				'unit'          => 'mi',
				'svgMap'        => '',
				'stravaUrl'     => 'https://www.strava.com/activities/555',
				'elevationGain' => 0.0,
			],
		];
		$this->transients['wpgraphql_strava_activities'] = $activities;

		$html = wpgraphql_strava_shortcode_heatmap( [] );

		$this->assertStringContainsString( 'strava-empty', $html );
		$this->assertStringContainsString( 'No route data available.', $html );
	}

	// ------------------------------------------------------------------
	// Year review shortcode tests.
	// ------------------------------------------------------------------

	public function test_year_review_shows_stats(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_year_review( [ 'year' => '2025' ] );

		$this->assertStringContainsString( 'strava-year-review', $html );
		// 3 activities in 2025.
		$this->assertStringContainsString( '3', $html );
		// Total distance: 5.12 + 20.5 + 3.1 = 28.72 => formatted as 28.7.
		$this->assertStringContainsString( '28.7', $html );
		// Total elevation: 150 + 320 + 80 = 550.
		$this->assertStringContainsString( '550', $html );
		// Year in Review heading.
		$this->assertStringContainsString( 'Year in Review', $html );
	}

	public function test_year_review_filters_by_year(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_year_review( [ 'year' => '2025' ] );

		// Should include 2025 activities.
		$this->assertStringContainsString( '2025', $html );
		// Should NOT include the 2026 activity's distance (10.0) in the total.
		// Total for 2025 is 28.7, not 38.7.
		$this->assertStringNotContainsString( '38.7', $html );
		$this->assertStringContainsString( '28.7', $html );
	}

	public function test_year_review_empty_for_wrong_year(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_year_review( [ 'year' => '2020' ] );

		$this->assertStringContainsString( 'strava-empty', $html );
		$this->assertStringContainsString( 'No activities found for this year.', $html );
	}

	// ------------------------------------------------------------------
	// Trends shortcode tests.
	// ------------------------------------------------------------------

	public function test_trends_shows_weekly_chart(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_trends( [] );

		$this->assertStringContainsString( 'strava-trends', $html );
		// Bar chart SVG should be present.
		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( '<rect', $html );
	}

	public function test_trends_filters_by_type(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_trends( [ 'type' => 'Run' ] );

		$this->assertStringContainsString( 'strava-trends', $html );
		// Should render successfully with only Run activities.
		$this->assertStringContainsString( '<svg', $html );
	}

	public function test_trends_empty_when_no_activities(): void {
		$this->options['wpgraphql_strava_access_token'] = '';
		unset( $this->transients['wpgraphql_strava_activities'] );

		$html = wpgraphql_strava_shortcode_trends( [] );

		$this->assertStringContainsString( 'strava-empty', $html );
		$this->assertStringContainsString( 'No activities found.', $html );
	}
}
