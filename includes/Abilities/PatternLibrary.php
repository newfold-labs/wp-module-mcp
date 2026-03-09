<?php

declare( strict_types=1 );

namespace BLU\Abilities;

use NewfoldLabs\WP\Module\Patterns\Library\Items;

/**
 * Pattern Library abilities for searching and fetching patterns.
 *
 * Registers blu/search-patterns and blu/get-pattern-markup MCP tools
 * so the AI editor assistant can find and use curated design patterns.
 */
class PatternLibrary {

	/**
	 * Constructor - registers pattern library abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	private const CACHE_KEY = 'blu_pattern_index';

	/**
	 * Fetch patterns, using a local cache as fallback when the API is unreachable.
	 *
	 * @param array $args Optional arguments for Items::get().
	 * @return array Pattern data (may be empty on total failure).
	 */
	private static function get_patterns( array $args = array() ): array {
		if ( ! class_exists( Items::class ) ) {
			$cached = get_option( self::CACHE_KEY );
			return is_array( $cached ) && ! empty( $cached ) ? $cached : array();
		}

		$data = Items::get( 'patterns', $args );

		if ( ! \is_wp_error( $data ) && is_array( $data ) && ! empty( $data ) ) {
			// Persist a lightweight copy so the MCP ability works even when the API is down.
			$index = array_map(
				function ( $p ) {
					return array(
						'slug'        => $p['slug'] ?? '',
						'title'       => $p['title'] ?? '',
						'description' => $p['description'] ?? '',
						'categories'  => $p['categories'] ?? array(),
						'tags'        => $p['tags'] ?? array(),
					);
				},
				$data
			);
			update_option( self::CACHE_KEY, $index, false );
			return $data;
		}

		// API failed — try the local cache.
		$cached = get_option( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		return array();
	}

	/**
	 * Score patterns against a query using word-level matching.
	 *
	 * Mirrors the client-side scoring in patternLibrary.js — splits the query
	 * into individual words and scores each word against categories, tags,
	 * title, and description. Returns only patterns with a positive score,
	 * sorted by relevance (highest first).
	 *
	 * @param array  $patterns All patterns from Items::get().
	 * @param string $query    Search query string.
	 * @return array Scored and filtered patterns (without content), sorted by relevance.
	 */
	private static function score_patterns( array $patterns, string $query ): array {
		$query_words = array_filter( preg_split( '/\s+/', strtolower( $query ) ) );
		$full_query  = implode( ' ', $query_words );

		$scored = array();

		foreach ( $patterns as $pattern ) {
			$score = 0;
			$title = strtolower( $pattern['title'] ?? '' );
			$desc  = strtolower( $pattern['description'] ?? '' );
			$tags  = array_map( 'strtolower', (array) ( $pattern['tags'] ?? array() ) );
			$cats  = array_map( 'strtolower', (array) ( $pattern['categories'] ?? array() ) );

			foreach ( $query_words as $word ) {
				// Category exact match.
				foreach ( $cats as $cat ) {
					if ( $cat === $word ) {
						$score += 10;
					}
				}

				// Tag: exact match bonus vs partial.
				$tag_exact   = false;
				$tag_partial = false;
				foreach ( $tags as $tag ) {
					if ( $tag === $word ) {
						$tag_exact = true;
					} elseif ( false !== strpos( $tag, $word ) ) {
						$tag_partial = true;
					}
				}
				if ( $tag_exact ) {
					$score += 6;
				} elseif ( $tag_partial ) {
					$score += 3;
				}

				// Title: word boundary match.
				if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '/', $title ) ) {
					$score += 5;
				}

				// Description: contains match.
				if ( false !== strpos( $desc, $word ) ) {
					$score += 2;
				}
			}

			// Multi-word phrase bonus.
			if ( count( $query_words ) > 1 ) {
				if ( false !== strpos( $title, $full_query ) ) {
					$score += 8;
				}
				if ( false !== strpos( $desc, $full_query ) ) {
					$score += 4;
				}
			}

			if ( $score > 0 ) {
				$scored[] = array(
					'slug'        => $pattern['slug'] ?? '',
					'title'       => $pattern['title'] ?? '',
					'description' => $pattern['description'] ?? '',
					'categories'  => $pattern['categories'] ?? array(),
					'tags'        => $pattern['tags'] ?? array(),
					'_score'      => $score,
				);
			}
		}

		// Sort by score descending.
		usort(
			$scored,
			function ( $a, $b ) {
				return $b['_score'] - $a['_score'];
			}
		);

		// Remove internal score field before returning.
		return array_map(
			function ( $item ) {
				unset( $item['_score'] );
				return $item;
			},
			$scored
		);
	}

	/**
	 * Register pattern library abilities.
	 */
	private function register_abilities(): void {
		$this->register_search_patterns();
		$this->register_get_pattern_markup();
	}

	/**
	 * Register the blu/search-patterns ability.
	 */
	private function register_search_patterns(): void {
		// phpcs:disable Generic.Files.LineLength.TooLong -- Tool description includes inline rules for AI context.
		$description = <<<'DESC'
		Search the pattern library for layouts matching a query. Returns results with title, slug, description, and categories (no markup).

		WHEN TO CALL: You MUST call this tool BEFORE blu/add-section or blu/edit-block whenever the user asks to add, replace, or redesign any section or multi-block layout. This includes: hero, pricing, team, FAQ, CTA, features, gallery, contact, header, footer, testimonials, about, stats, logos, services sections, or any request involving multiple blocks. Only skip this for single-block additions (paragraph, heading, image, button).

		WORKFLOW:
		a) Call this tool with a descriptive query.
		b) Pick the result whose title and description best fit the request. Pick a DIFFERENT pattern each time the user asks for the same type.
		c) Pass the chosen pattern_slug to blu/add-section or blu/edit-block — the system fetches markup and customizes text automatically. Do NOT call blu/get-pattern-markup.
		d) If zero results are returned or the search fails, generate markup yourself with block_content — do NOT mention the library or any errors to the user.
		DESC;
		// phpcs:enable Generic.Files.LineLength.TooLong

		blu_register_ability(
			'blu/search-patterns',
			array(
				'label'               => 'Search Patterns',
				'description'         => $description,
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'query'    => array(
							'type'        => 'string',
							'description' => 'Search query to match against pattern titles, descriptions, categories, and tags.',
						),
						'category' => array(
							'type'        => 'string',
							'description' => 'Optional category slug to filter results.',
						),
						'limit'    => array(
							'type'        => 'integer',
							'description' => 'Maximum number of results to return. Defaults to 15.',
						),
					),
					'required'   => array( 'query' ),
				),
				'execute_callback'    => function ( $input = null ) {
					$query    = $input['query'] ?? '';
					$limit    = $input['limit'] ?? 15;

					if ( empty( $query ) ) {
						return blu_prepare_ability_response( 400, 'Missing required parameter: query' );
					}

					$data   = self::get_patterns();
					$scored = self::score_patterns( $data, $query );

					$total_matches = count( $scored );
					$results       = array_slice( $scored, 0, (int) $limit );

					return blu_prepare_ability_response(
						200,
						array(
							'patterns'     => $results,
							'count'        => count( $results ),
							'totalMatches' => $total_matches,
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register the blu/get-pattern-markup ability.
	 */
	private function register_get_pattern_markup(): void {
		blu_register_ability(
			'blu/get-pattern-markup',
			array(
				'label'               => 'Get Pattern Markup',
				'description'         => 'Get the full WordPress block markup for a specific pattern by slug. Call this after blu/search-patterns to retrieve the markup. Then modify the markup to match the user\'s request and pass it to blu/add-section.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'The pattern slug returned from blu/search-patterns.',
						),
					),
					'required'   => array( 'slug' ),
				),
				'execute_callback'    => function ( $input = null ) {
					$slug = $input['slug'] ?? '';

					if ( empty( $slug ) ) {
						return blu_prepare_ability_response( 400, 'Missing required parameter: slug' );
					}

					$data = self::get_patterns();

					// Find pattern by slug
					$match = null;
					foreach ( $data as $pattern ) {
						if ( isset( $pattern['slug'] ) && $pattern['slug'] === $slug ) {
							$match = $pattern;
							break;
						}
					}

					if ( ! $match ) {
						return blu_prepare_ability_response( 404, 'Pattern not found: ' . $slug );
					}

					$content = $match['content'] ?? '';

					if ( empty( $content ) ) {
						return blu_prepare_ability_response( 502, 'Pattern markup is temporarily unavailable. Generate the markup yourself using block_content.' );
					}

					return blu_prepare_ability_response(
						200,
						array(
							'slug'        => $match['slug'] ?? '',
							'title'       => $match['title'] ?? '',
							'content'     => $content,
							'categories'  => $match['categories'] ?? array(),
							'tags'        => $match['tags'] ?? array(),
							'description' => $match['description'] ?? '',
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}
}
