<?php

/**
 * Register a new ability in the system.
 *
 * @param string $name The unique name of the ability to register.
 * @param array  $args The arguments to configure the ability (e.g., description, metadata).
 *
 * @return WP_Ability|null The registered ability object if registration is successful, or null if the function `wp_register_ability` is unavailable.
 */
function blu_register_ability( string $name, array $args ): ?WP_Ability {
	if ( function_exists( 'wp_register_ability' ) ) {

		return wp_register_ability( $name, $args );
	}

	return null;
}

/**
 * Unregisters an ability by its name.
 *
 * @param string $name The name of the ability to unregister.
 *
 * @return WP_Ability|null The unregistered ability object if successful, or null if the function `wp_unregister_ability` does not exist.
 */
function blu_unregister_ability( string $name ): ?WP_Ability {
	if ( function_exists( 'wp_unregister_ability' ) ) {
		return wp_unregister_ability( $name );
	}

	return null;
}

/**
 * Retrieves an ability by its name.
 *
 * @param string $name The name of the ability to retrieve.
 *
 * @return WP_Ability|null The ability object if found, or null if not found or if the function does not exist.
 */
function blu_get_ability( string $name ): ?WP_Ability {
	if ( function_exists( 'wp_get_ability' ) ) {
		return wp_get_ability( $name );
	}

	return null;
}

/**
 * Retrieves a list of all abilities available in the system.
 *
 * @return WP_Ability[] An array of all abilities if the underlying function exists, or an empty array otherwise.
 */
function blu_get_abilities(): array {
	if ( function_exists( 'wp_get_abilities' ) ) {
		return wp_get_abilities();
	}

	return array();
}

/**
 * Registers a new ability category with the specified slug and arguments.
 *
 * @param string $slug The unique identifier for the ability category to be registered.
 * @param array  $args The arguments defining the properties of the ability category.
 *
 * @return WP_Ability_Category|null The registered ability category if successful, or null if the registration function is not available.
 */
function blu_register_ability_category( string $slug, array $args ): ?WP_Ability_Category {
	if ( function_exists( 'wp_register_ability_category' ) ) {
		return wp_register_ability_category( $slug, $args );
	}

	return null;
}

/**
 * Unregisters an ability category by its slug.
 *
 * @param string $slug The slug of the ability category to unregister.
 *
 * @return WP_Ability_Category|null The unregistered ability category object if successful, or null if the function does not exist or the category could not be unregistered.
 */
function blu_unregister_ability_category( string $slug ): ?WP_Ability_Category {
	if ( function_exists( 'wp_unregister_ability_category' ) ) {
		return wp_unregister_ability_category( $slug );
	}

	return null;
}

/**
 * Retrieves the ability category associated with the given slug.
 *
 * @param string $slug The slug identifying the ability category.
 *
 * @return WP_Ability_Category|null The ability category object if found, or null if no category exists or the function is unavailable.
 */
function blu_get_ability_category( string $slug ): ?WP_Ability_Category {
	if ( function_exists( 'wp_get_ability_category' ) ) {
		return wp_get_ability_category( $slug );
	}

	return null;
}

/**
 * Retrieves a list of available ability categories.
 *
 * @return string[] An array of ability categories. If the function `wp_get_ability_categories` is not available, it returns an empty array.
 */
function blu_get_ability_categories(): array {
	if ( function_exists( 'wp_get_ability_categories' ) ) {
		return wp_get_ability_categories();
	}

	return array();
}

/**
 * Filters a list of abilities by the specified category.
 *
 * @param WP_Ability[] $abilities An array of abilities to be filtered.
 * @param string       $category  The category used to filter the abilities.
 *
 * @return WP_Ability[] An array of abilities that match the specified category.
 */
function blu_filter_abilities_by_category( array $abilities, string $category ): array {
	return array_filter(
		$abilities,
		function ( $ability ) use ( $category ) {
			return $ability->get_category() === $category;
		}
	);
}

/**
 * Retrieves a list of abilities filtered by the specified category.
 *
 * @param string $category The category used to filter the abilities.
 *
 * @return WP_Ability[] An array of abilities that belong to the specified category.
 */
function blu_get_abilities_by_category( string $category ): array {
	return blu_filter_abilities_by_category( blu_get_abilities(), $category );
}


/**
 * Get the abilities name by type
 *
 * @param string $type The type
 *
 * @return array
 */
function blu_get_ability_by_type( $type = 'tool' ) {
	$all_abilities     = blu_get_abilities_by_category( 'blu-mcp' );
	$current_abilities = array();
	$type              = in_array( $type, array( 'tool', 'prompt', 'resource' ), true ) ? $type : 'tool';
	foreach ( $all_abilities as $ability ) {
		$meta         = $ability->get_meta();
		$ability_type = 'tool';
		$public = true;

		if ( isset( $meta['mcp']['type'] ) ) {
			$ability_type = $meta['mcp']['type'];
		}
		if ( isset( $meta['mcp']['public'] ) ) {
			$public = $meta['mcp']['public'];
		}

		if ( $public && $ability_type === $type ) {
			$current_abilities[] = $ability->get_name();
		}
	}

	return $current_abilities;
}

/**
 * Filters a list of abilities by a specified namespace.
 *
 * @param WP_Ability[] $abilities An array of abilities to filter.
 * @param string       $namespace The namespace used to filter the abilities.
 *
 * @return WP_Ability[] An array of abilities that match the specified namespace.
 */
function blu_filter_abilities_by_namespace( array $abilities, string $namespace ): array {
	$namespace_prefix = rtrim( $namespace, '/' ) . '/';

	return array_filter(
		$abilities,
		function ( $ability ) use ( $namespace_prefix ) {
			return 0 === strpos( $ability->get_name(), $namespace_prefix );
		}
	);
}

/**
 * Get all abilities that belong to a specific namespace.
 *
 * @param string $namespace The namespace to filter by (e.g., 'my-plugin').
 *
 * @return WP_Ability[] Array of abilities matching the namespace.
 */
function blu_get_abilities_by_namespace( string $namespace ): array {
	return blu_filter_abilities_by_namespace( blu_get_abilities(), $namespace );
}

/**
 * Prepares a standardized ability response.
 *
 * @param int   $status  The HTTP status code of the response.
 * @param mixed $message The response message or data.
 *
 * @return array An associative array containing 'status' and 'response' keys.
 */
function blu_prepare_ability_response( $status, $message ) {
	return array(
		'statusCode' => $status,
		'status'     => blu_get_status_type( $status ),
		'message'    => $message,
	);
}

/**
 * Standardizes a REST API response into a consistent format.
 *
 * @param mixed $response The original response which can be a WP_Error or WP_REST_Response.
 *
 * @return array An associative array containing 'status' and 'response' keys.
 */
function blu_standardize_rest_response( $response ) {

	if ( is_wp_error( $response ) ) {

		$status = $response->get_error_code() ? $response->get_error_code() : 500;

		return blu_prepare_ability_response( $status, $response->get_error_message() );

	} elseif ( $response instanceof \WP_REST_Response ) {

		return blu_prepare_ability_response( $response->get_status(), $response->get_data() );

	} else {
		return blu_prepare_ability_response( 500, 'Unexpected response format.' );
	}
}

/**
 * Maps an HTTP status code to a simplified status type.
 *
 * @param int $status_code The HTTP status code to evaluate.
 *
 * @return string The corresponding status type: 'success', 'error', or 'unknown'.
 */
function blu_get_status_type( $status_code ) {

	$status = 'unknown';

	if ( $status_code >= 200 && $status_code < 400 ) {

		$status = 'success';

	} elseif ( $status_code >= 400 && $status_code <= 599 ) {

		$status = 'error';

	}
	return $status;
}

/**
 * Resolve a user-supplied post-type identifier to its canonical registered slug.
 *
 * Accepts any of: the slug itself ("bmcp_book"), the REST base ("books"),
 * the plural label ("Books"), the singular label ("Book"), or the menu name —
 * case-insensitively. This lets LLM tool callers pass whichever string they
 * have on hand instead of being forced to learn the internal slug.
 *
 * @param string $input The identifier provided by the caller.
 *
 * @return string|null The canonical post-type slug, or null if no match.
 */
function blu_resolve_post_type( string $input ): ?string {
	$input = trim( $input );
	if ( '' === $input ) {
		return null;
	}

	if ( post_type_exists( $input ) ) {
		return $input;
	}

	$needle = strtolower( $input );

	foreach ( get_post_types( array(), 'objects' ) as $slug => $object ) {
		$candidates = array(
			$slug,
			$object->name ?? '',
			$object->rest_base ?? '',
			isset( $object->labels->name ) ? $object->labels->name : '',
			isset( $object->labels->singular_name ) ? $object->labels->singular_name : '',
			isset( $object->labels->menu_name ) ? $object->labels->menu_name : '',
		);
		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate && strtolower( (string) $candidate ) === $needle ) {
				return $slug;
			}
		}
	}

	return null;
}

/**
 * Format a "post type not found" error that gives the LLM enough information
 * to self-correct on the next turn.
 *
 * @param string $input The identifier the caller tried to use.
 *
 * @return array Standardized 400 ability response listing valid options.
 */
function blu_post_type_not_found_response( string $input ): array {
	$available = array();
	foreach ( get_post_types( array(), 'objects' ) as $slug => $object ) {
		$label       = isset( $object->labels->name ) ? $object->labels->name : $slug;
		$available[] = sprintf( '%s ("%s")', $slug, $label );
	}
	sort( $available );

	return blu_prepare_ability_response(
		400,
		sprintf(
			'Unknown post type "%s". Pass the slug, REST base, or label. Available: %s.',
			$input,
			implode( ', ', $available )
		)
	);
}

/**
 * Slim projection of a WP_Post suitable for list / search responses.
 *
 * Returning full WP_Post objects buries the ID in dozens of internal fields
 * and routinely causes LLMs to drop the ID when summarizing for the user.
 * This projection puts the id first and limits noise.
 *
 * @param WP_Post $post The post to project.
 *
 * @return array
 */
function blu_project_post_summary( WP_Post $post ): array {
	return array(
		'id'       => (int) $post->ID,
		'title'    => get_the_title( $post ),
		'status'   => $post->post_status,
		'type'     => $post->post_type,
		'slug'     => $post->post_name,
		'author'   => (int) $post->post_author,
		'date'     => $post->post_date,
		'modified' => $post->post_modified,
		'excerpt'  => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ),
		'link'     => get_permalink( $post ),
	);
}

/**
 * Full projection of a WP_Post — summary plus raw content. Used by single-item
 * fetches (get) and after writes (add/update) where the caller wants to see
 * what was stored.
 *
 * @param WP_Post $post The post to project.
 *
 * @return array
 */
function blu_project_post_full( WP_Post $post ): array {
	return array_merge(
		blu_project_post_summary( $post ),
		array(
			'content' => $post->post_content,
		)
	);
}

if ( ! function_exists( 'blu_is_valid_list' ) ) {
	/**
	 * Check if the list is a simple array
	 *
	 * @param array $list The list
	 *
	 * @return bool
	 */
	function blu_is_valid_list( $list ) {
		$i = 0;
		foreach ( $list as $k => $_ ) {
			if ( $k !== $i++ ) {
				return false;
			}
		}

		return true;
	}

}

/**
 * Check if input array is a valid input
 *
 * @param array    $input_value The input.
 * @param string   $input_name The input name.
 * @param bool|int $min_items The min amount of items.
 * @param bool|int $max_items The max amount of items.
 *
 * @return WP_Error|bool
 */
function blu_is_valid_input_array( $input_value, $input_name, $min_items = false, $max_items = false ) {

	$error = '';

	if ( ! is_array( $input_value ) ) {
		$error = $input_name . ' must be an array: ' . gettype( $input_value ) . ' given.';
	}

	if ( $min_items && count( $input_value ) < $min_items ) {
		$error = $input_name . ' must contain at least ' . $min_items . ' element';
	}

	if ( $max_items && count( $input_value ) > $max_items ) {
		$error = $input_name . ' cannot be contain more than ' . $max_items . ' element.';

	}

	if ( ! blu_is_valid_list( $input_value ) ) {
		$error = $input_name . ' can\'t be an object-shaped array';
	}

	return '' === $error ? true : new WP_Error( 400, $error );
}


if ( ! function_exists( 'blu_filter_terms_by_patterns' ) ) {

	/**
	 * Filter terms by patterns
	 *
	 * @param array $patterns The patterns
	 * @param array $terms The terms to filter by reference.
	 *
	 * @return void
	 */
	function blu_filter_terms_by_patterns( $patterns, &$terms ) {
		if ( count( $patterns ) > 0 ) {
			$filtered_ids = array();
			foreach ( $terms as $term ) {
				if ( ! isset( $term['name'] ) || ! isset( $term['id'] ) || ! is_string( $term['name'] ) ) {
					continue;
				}
				$term_name = trim( $term['name'] );

				foreach ( $patterns as $pattern ) {

					if ( @preg_match( $pattern, '' ) !== false ) {
						$regex = $pattern;
						if ( substr( $regex, - 1 ) !== 'i' ) {
							// Ensure case-insensitive
							$regex = rtrim( $regex, '/' ) . '/i';
						}
						if ( preg_match( $regex, $term_name ) ) {
							$filtered_ids[] = $term['id'];
							break;
						}
					} elseif ( false !== stripos( $term_name, $pattern ) ) {
						$filtered_ids[] = $term['id'];
						break;
					}
				}
			}

			if ( count( $filtered_ids ) > 0 ) {
				$terms = array_filter(
					$terms,
					function ( $term ) use ( $filtered_ids ) {
						return in_array( $term['id'], $filtered_ids );
					}
				);
			}
		}
	}
}
