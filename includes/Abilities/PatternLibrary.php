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
		blu_register_ability(
			'blu/search-patterns',
			array(
				'label'               => 'Search Patterns',
				'description'         => 'Search the pattern library for layouts matching a query. Returns up to 5 results with title, slug, description, and categories (no markup). Use this when the user asks to add a section, layout, or design element (hero, pricing, testimonials, FAQ, CTA, features, team, gallery, contact, footer, header, etc.). After picking a match, call blu/get-pattern-markup to get the full block markup.',
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
							'description' => 'Maximum number of results to return. Defaults to 5.',
						),
					),
					'required'   => array( 'query' ),
				),
				'execute_callback'    => function ( $input = null ) {
					$query    = $input['query'] ?? '';
					$category = $input['category'] ?? '';
					$limit    = $input['limit'] ?? 5;

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

					// Strip content and limit results
					$results = array_slice(
						array_map(
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
						),
						0,
						(int) $limit
					);

					return blu_prepare_ability_response(
						200,
						array(
							'patterns' => $results,
							'count'    => count( $results ),
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
