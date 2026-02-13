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
		Search the pattern library for layouts matching a query. Returns results with title, slug, description, and categories (no markup). Use this when the user asks to add a section, layout, or design element (hero, pricing, testimonials, FAQ, CTA, features, team, gallery, contact, footer, header, etc.). After picking a match, call blu/get-pattern-markup to get the full block markup. Pick a DIFFERENT pattern each time the user asks for the same type of section — avoid repeating the same design.

		ADDITIONAL RULES:
		- PATTERN LIBRARY WORKFLOW: When the user asks to add a new section, layout, or design element, follow this exact sequence: a) Search the pattern library with blu/search-patterns. b) Review ALL returned results — pick the one whose title and description best fit the user's request. If the user has previously used a pattern, pick a DIFFERENT one to provide variety. c) Insert the chosen pattern using blu/add-section with the pattern_slug parameter. Do NOT call blu/get-pattern-markup or pass block_content — the system fetches the markup and automatically customizes the text to fit the page. If the search returns zero results, generate the section markup from scratch using block_content — do NOT tell the user no patterns were found, just build it yourself. Only skip the pattern library for very simple requests (e.g., "add a paragraph").
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
					$category = $input['category'] ?? '';
					$limit    = $input['limit'] ?? 15;

					if ( empty( $query ) ) {
						return blu_prepare_ability_response( 400, 'Missing required parameter: query' );
					}

					$args = array( 'keywords' => $query );
					if ( ! empty( $category ) ) {
						$args['category'] = $category;
					}

					$data = Items::get( 'patterns', $args );

					if ( \is_wp_error( $data ) ) {
						return blu_prepare_ability_response( 503, $data->get_error_message() );
					}

					// Strip content field from results
					$all_results = array_map(
						function ( $pattern ) {
							return array(
								'slug'        => $pattern['slug'] ?? '',
								'title'       => $pattern['title'] ?? '',
								'description' => $pattern['description'] ?? '',
								'categories'  => $pattern['categories'] ?? array(),
								'tags'        => $pattern['tags'] ?? array(),
							);
						},
						$data
					);

					$total_matches = count( $all_results );
					$results       = array_slice( $all_results, 0, (int) $limit );

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

					$data = Items::get( 'patterns' );

					if ( \is_wp_error( $data ) ) {
						return blu_prepare_ability_response( 503, $data->get_error_message() );
					}

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

					return blu_prepare_ability_response(
						200,
						array(
							'slug'        => $match['slug'] ?? '',
							'title'       => $match['title'] ?? '',
							'content'     => $match['content'] ?? '',
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
				),
			)
		);
	}
}
