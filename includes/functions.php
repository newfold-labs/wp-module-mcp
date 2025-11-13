<?php

/**
 * Register a new ability in the system.
 *
 * @param string $name The unique name of the ability to register.
 * @param array $args The arguments to configure the ability (e.g., description, metadata).
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

	return [];
}

/**
 * Registers a new ability category with the specified slug and arguments.
 *
 * @param string $slug The unique identifier for the ability category to be registered.
 * @param array $args The arguments defining the properties of the ability category.
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

	return [];
}

/**
 * Filters a list of abilities by the specified category.
 *
 * @param WP_Ability[] $abilities An array of abilities to be filtered.
 * @param string $category The category used to filter the abilities.
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
 * Filters a list of abilities by a specified namespace.
 *
 * @param WP_Ability[] $abilities An array of abilities to filter.
 * @param string $namespace The namespace used to filter the abilities.
 *
 * @return WP_Ability[] An array of abilities that match the specified namespace.
 */
function blu_filter_abilities_by_namespace( array $abilities, string $namespace ): array {
	$namespace_prefix = rtrim( $namespace, '/' ) . '/';

	return array_filter(
		$abilities,
		function ( $ability ) use ( $namespace_prefix ) {
			return str_starts_with( $ability->get_name(), $namespace_prefix );
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
 * @param int $status The HTTP status code of the response.
 * @param mixed $message The response message or data.
 *
 * @return array An associative array containing 'status' and 'response' keys.
 */
function blu_prepare_ability_response( $status, $message ) {
	return array(
		'code'		=> $status,
		'data'  	=> array( 'status' => $status ),
		'message' 	=> $message
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