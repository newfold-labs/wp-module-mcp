<?php

namespace BLU;

use BLU\Abilities\PatternLibrary;

/**
 * Tests for PatternLibrary abilities.
 *
 * @covers \BLU\Abilities\PatternLibrary
 */
class PatternLibraryWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Helper: invoke a private static method on PatternLibrary via Reflection.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments to pass.
	 * @return mixed
	 */
	private static function call_static( string $method, array $args = array() ) {
		$ref = new \ReflectionMethod( PatternLibrary::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		delete_option( 'blu_pattern_index' );
		parent::tearDown();
	}

	// ── score_patterns tests ──────────────────────────────────────────

	/**
	 * Verifies score_patterns returns empty array when no patterns match.
	 */
	public function test_score_patterns_returns_empty_for_no_matches() {
		$patterns = array(
			array(
				'slug'        => 'hero-one',
				'title'       => 'Hero Section',
				'description' => 'A bold hero section.',
				'categories'  => array( 'hero' ),
				'tags'        => array( 'bold', 'dark' ),
			),
		);

		$result = self::call_static( 'score_patterns', array( $patterns, 'zzzznotfound' ) );
		$this->assertSame( array(), $result );
	}

	/**
	 * Verifies score_patterns returns matching patterns sorted by relevance.
	 */
	public function test_score_patterns_returns_matches_sorted_by_score() {
		$patterns = array(
			array(
				'slug'        => 'pricing-basic',
				'title'       => 'Basic Pricing Table',
				'description' => 'A simple pricing layout.',
				'categories'  => array( 'pricing' ),
				'tags'        => array( 'table', 'simple' ),
			),
			array(
				'slug'        => 'pricing-fancy',
				'title'       => 'Fancy Pricing',
				'description' => 'An elaborate pricing section with toggle.',
				'categories'  => array( 'pricing' ),
				'tags'        => array( 'pricing', 'toggle' ),
			),
		);

		$result = self::call_static( 'score_patterns', array( $patterns, 'pricing' ) );

		$this->assertNotEmpty( $result );
		$this->assertCount( 2, $result );
		// Fancy has category + tag exact + title match, so it should rank first.
		$this->assertSame( 'pricing-fancy', $result[0]['slug'] );
	}

	/**
	 * Verifies score_patterns strips the internal _score field from results.
	 */
	public function test_score_patterns_strips_internal_score() {
		$patterns = array(
			array(
				'slug'        => 'cta-one',
				'title'       => 'Call to Action',
				'description' => 'A CTA block.',
				'categories'  => array( 'cta' ),
				'tags'        => array(),
			),
		);

		$result = self::call_static( 'score_patterns', array( $patterns, 'cta' ) );
		$this->assertArrayNotHasKey( '_score', $result[0] );
	}

	/**
	 * Verifies score_patterns applies multi-word phrase bonus.
	 */
	public function test_score_patterns_phrase_bonus() {
		$patterns = array(
			array(
				'slug'        => 'hero-split',
				'title'       => 'Hero Split Layout',
				'description' => 'A hero split layout with image and text.',
				'categories'  => array( 'hero' ),
				'tags'        => array(),
			),
			array(
				'slug'        => 'hero-centered',
				'title'       => 'Centered Hero',
				'description' => 'A centered hero with background.',
				'categories'  => array( 'hero' ),
				'tags'        => array( 'split' ),
			),
		);

		$result = self::call_static( 'score_patterns', array( $patterns, 'hero split' ) );

		$this->assertNotEmpty( $result );
		// hero-split should rank first because "hero split" phrase appears in its title.
		$this->assertSame( 'hero-split', $result[0]['slug'] );
	}

	// ── get_patterns cache fallback tests ─────────────────────────────

	/**
	 * Verifies get_patterns falls back to cached index when API fails.
	 */
	public function test_get_patterns_returns_cached_index_on_api_failure() {
		$cached = array(
			array(
				'slug'        => 'cached-pattern',
				'title'       => 'Cached Pattern',
				'description' => 'From cache.',
				'categories'  => array( 'general' ),
				'tags'        => array(),
			),
		);
		update_option( 'blu_pattern_index', $cached, false );

		// Items::get() will fail in the test environment (no API), so the cache is used.
		$result = self::call_static( 'get_patterns' );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
		$this->assertSame( 'cached-pattern', $result[0]['slug'] );
	}

	/**
	 * Verifies get_patterns returns empty array when API fails and no cache exists.
	 */
	public function test_get_patterns_returns_empty_when_no_cache() {
		delete_option( 'blu_pattern_index' );

		$result = self::call_static( 'get_patterns' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// ── Ability execute_callback tests via Abilities API ──────────────

	/**
	 * Helper: execute a registered ability by name, setting up an admin user.
	 *
	 * @param string $ability_name The registered ability name.
	 * @param array  $input        Input to pass to the ability.
	 * @return mixed The result of the ability execution.
	 */
	private function execute_ability( string $ability_name, array $input ) {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		// Ensure abilities are registered.
		new PatternLibrary();

		// Set up an admin user so permission_callback passes.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$ability = blu_get_ability( $ability_name );
		$this->assertNotNull( $ability, "Ability {$ability_name} should be registered." );

		return $ability->execute( $input );
	}

	/**
	 * Verifies blu/get-pattern-markup returns 502 when pattern has no content.
	 */
	public function test_get_pattern_markup_returns_502_when_content_missing() {
		$cached = array(
			array(
				'slug'        => 'no-content-pattern',
				'title'       => 'Pattern Without Content',
				'description' => 'Cached without markup.',
				'categories'  => array( 'hero' ),
				'tags'        => array(),
			),
		);
		update_option( 'blu_pattern_index', $cached, false );

		$result = $this->execute_ability( 'blu/get-pattern-markup', array( 'slug' => 'no-content-pattern' ) );

		$this->assertIsArray( $result );
		$this->assertSame( 502, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( 'unavailable', $result['message'] );
	}

	/**
	 * Verifies blu/get-pattern-markup returns 404 for unknown slug.
	 */
	public function test_get_pattern_markup_returns_404_for_unknown_slug() {
		$cached = array(
			array(
				'slug'        => 'existing-pattern',
				'title'       => 'Existing',
				'description' => 'Exists.',
				'categories'  => array(),
				'tags'        => array(),
			),
		);
		update_option( 'blu_pattern_index', $cached, false );

		$result = $this->execute_ability( 'blu/get-pattern-markup', array( 'slug' => 'does-not-exist' ) );

		$this->assertIsArray( $result );
		$this->assertSame( 404, $result['statusCode'] );
	}

	/**
	 * Verifies blu/get-pattern-markup returns 200 with content when available.
	 */
	public function test_get_pattern_markup_returns_200_with_content() {
		$cached = array(
			array(
				'slug'        => 'has-content',
				'title'       => 'Pattern With Content',
				'description' => 'Has markup.',
				'categories'  => array( 'hero' ),
				'tags'        => array(),
				'content'     => '<!-- wp:heading --><h2>Hello</h2><!-- /wp:heading -->',
			),
		);
		update_option( 'blu_pattern_index', $cached, false );

		$result = $this->execute_ability( 'blu/get-pattern-markup', array( 'slug' => 'has-content' ) );

		$this->assertIsArray( $result );
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertArrayHasKey( 'content', $result['message'] );
		$this->assertStringContainsString( 'Hello', $result['message']['content'] );
	}

	/**
	 * Verifies blu/search-patterns returns matching results.
	 */
	public function test_search_patterns_returns_matches() {
		$cached = array(
			array(
				'slug'        => 'hero-bold',
				'title'       => 'Bold Hero',
				'description' => 'A bold hero section.',
				'categories'  => array( 'hero' ),
				'tags'        => array( 'bold' ),
			),
			array(
				'slug'        => 'pricing-table',
				'title'       => 'Pricing Table',
				'description' => 'A pricing table.',
				'categories'  => array( 'pricing' ),
				'tags'        => array(),
			),
		);
		update_option( 'blu_pattern_index', $cached, false );

		$result = $this->execute_ability( 'blu/search-patterns', array( 'query' => 'hero' ) );

		$this->assertIsArray( $result );
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertArrayHasKey( 'patterns', $result['message'] );
		$this->assertSame( 1, $result['message']['count'] );
		$this->assertSame( 'hero-bold', $result['message']['patterns'][0]['slug'] );
	}

	/**
	 * Verifies blu/search-patterns returns empty when no matches found.
	 */
	public function test_search_patterns_returns_empty_for_no_matches() {
		$cached = array(
			array(
				'slug'        => 'hero-bold',
				'title'       => 'Bold Hero',
				'description' => 'A bold hero section.',
				'categories'  => array( 'hero' ),
				'tags'        => array(),
			),
		);
		update_option( 'blu_pattern_index', $cached, false );

		$result = $this->execute_ability( 'blu/search-patterns', array( 'query' => 'zzzznotfound' ) );

		$this->assertIsArray( $result );
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 0, $result['message']['count'] );
	}
}
