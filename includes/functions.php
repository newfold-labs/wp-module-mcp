<?php

/**
 * Get all abilities that belong to a specific namespace.
 *
 * @param string $namespace The namespace to filter by (e.g., 'my-plugin').
 * @return \WP_Ability[] Array of abilities matching the namespace.
 */
function blu_get_abilities_by_namespace( string $namespace ): array {
	$all_abilities = wp_get_abilities();
	$namespace_prefix = rtrim( $namespace, '/' ) . '/';

	return array_filter(
		$all_abilities,
		function( $ability ) use ( $namespace_prefix ) {
			return str_starts_with( $ability->get_name(), $namespace_prefix );
		}
	);
}

/**
 * Wrapper method for registering an ability.
 * 
 * The wp_register_ability() method is slated for release in WP 6.9
 * but we need backwards compatibility to WP 6.6 (current plus 2 versions).
 *
 * @param string $name The name of the ability
 * @param array $args The arguments for the ability
 * @return void
 */
function newfold_register_ability( string $name, array $args ): void {
	wp_register_ability( $name, $args );
}