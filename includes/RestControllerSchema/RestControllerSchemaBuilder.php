<?php
declare( strict_types=1 );

namespace BLU\RestControllerSchema;

use BLU\Abilities\RestApiUtils;

/**
 * Builds ability input schemas from WordPress REST API controller classes.
 *
 * Works before core routes are registered on rest_api_init priority 99 by reading
 * public controller methods (get_collection_params, get_endpoint_args_for_item_schema, etc.).
 */
class RestControllerSchemaBuilder {

	/**
	 * REST controller instance.
	 *
	 * @var \WP_REST_Controller
	 */
	private \WP_REST_Controller $controller;

	/**
	 * @param \WP_REST_Controller $controller REST controller instance.
	 */
	public function __construct( \WP_REST_Controller $controller ) {
		$this->controller = $controller;
	}

	/**
	 * Create a builder from any REST controller.
	 *
	 * @param \WP_REST_Controller $controller REST controller instance.
	 *
	 * @return self
	 */
	public static function from_controller( \WP_REST_Controller $controller ): self {
		return new self( $controller );
	}


	/**
	 * Create a builder from any REST controller.
	 *
	 * @param \WP_REST_Controller $controller REST controller instance.
	 *
	 * @return self
	 */
	public static function for_cpt(): self {
		return new self( new \WP_REST_Post_Types_Controller() );
	}
	/**
	 * Create a builder for the users REST controller.
	 *
	 * @return self
	 */
	public static function for_users(): self {
		return new self( new \WP_REST_Users_Controller() );
	}

	/**
	 * Create a builder for a post-type REST controller.
	 *
	 * @param string $post_type Post type slug (e.g. "page", "post").
	 *
	 * @return self
	 */
	public static function for_post_type( string $post_type ): self {
		return new self( new \WP_REST_Posts_Controller( $post_type ) );
	}

	/**
	 * Create a builder for the site settings REST controller.
	 *
	 * @return self
	 */
	public static function for_settings(): self {
		return new self( new \WP_REST_Settings_Controller() );
	}

	/**
	 * Create a builder for the media (attachments) REST controller.
	 *
	 * @return self
	 */
	public static function for_media(): self {
		return new self( new \WP_REST_Attachments_Controller('attachment') );
	}

	/**
	 * Create a builder for the global styles REST controller.
	 *
	 * @return self
	 */
	public static function for_global_style(): self {
		return new self( new \WP_REST_Global_Styles_Controller() );
	}

	/**
	 * Create a builder for the terms REST controller.
	 *
	 * @return self
	 */
	public static function for_terms( $term = 'category'): self {
		return new self( new \WP_REST_Terms_Controller( $term ) );
	}

	/**
	 * Input schema for listing/searching a collection (GET).
	 *
	 * @return array<string, mixed>
	 */
	public function collection(): array {
		return RestApiUtils::schema_from_controller_args(
			$this->controller->get_collection_params()
		);
	}

	/**
	 * Input schema for creating an item (POST).
	 *
	 * @return array<string, mixed>
	 */
	public function creatable(): array {
		return RestApiUtils::schema_from_controller_args(
			$this->controller->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE )
		);
	}

	/**
	 * Input schema for updating an item body (PUT/PATCH), without route id.
	 *
	 * @return array<string, mixed>
	 */
	public function editable(): array {
		return RestApiUtils::schema_from_controller_args(
			$this->controller->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE )
		);
	}

	/**
	 * Input schema for retrieving a single item (GET).
	 *
	 * @param string $id_description Description for the required id property.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item( string $id_description = 'Unique identifier for the item.' ): array {
		$args = array(
			'context' => $this->controller->get_context_param( array( 'default' => 'view' ) ),
		);

		if ( $this->controller instanceof \WP_REST_Posts_Controller ) {
			$args = array_merge( $args, $this->get_post_item_query_args() );
		}

		return RestApiUtils::schema_from_controller_args(
			$args,
			array(
				'id' => array(
					'type'        => 'integer',
					'description' => $id_description,
				),
			),
			array( 'id' ),
			false
		);
	}

	/**
	 * Input schema for updating an item by ID (PUT/PATCH).
	 *
	 * @param string $id_description Description for the required id property.
	 *
	 * @return array<string, mixed>
	 */
	public function update_with_id( string $id_description = 'Unique identifier for the item.' ): array {
		return RestApiUtils::schema_from_controller_args(
			$this->controller->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
			array(
				'id' => array(
					'type'        => 'integer',
					'description' => $id_description,
				),
			),
			array( 'id' )
		);
	}

	/**
	 * Input schema for deleting an item (DELETE).
	 *
	 * @param array<string, mixed> $endpoint_args   DELETE endpoint argument definitions.
	 * @param string[]             $extra_required  Additional required property names.
	 * @param string               $id_description  Description for the required id property.
	 *
	 * @return array<string, mixed>
	 */
	public function delete_with_id(
		array $endpoint_args,
		array $extra_required = array( 'id' ),
		string $id_description = 'Unique identifier for the item.'
	): array {
		return RestApiUtils::schema_from_controller_args(
			$endpoint_args,
			array(
				'id' => array(
					'type'        => 'integer',
					'description' => $id_description,
				),
			),
			$extra_required
		);
	}

	/**
	 * Input schema with only the context query parameter.
	 *
	 * @return array<string, mixed>
	 */
	public function context_only(): array {
		return RestApiUtils::schema_from_controller_args(
			array(
				'context' => $this->controller->get_context_param( array( 'default' => 'view' ) ),
			),
			array(),
			array(),
			false
		);
	}

	/**
	 * Empty object schema for endpoints with no input parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function empty_object(): array {
		return array(
			'type'       => 'object',
			'properties' => array(),
		);
	}

	/**
	 * Default DELETE args for posts and pages (force deletion).
	 *
	 * @return array<string, mixed>
	 */
	public static function post_delete_endpoint_args(): array {
		return array(
			'force' => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => 'Whether to bypass Trash and force deletion.',
			),
		);
	}

	/**
	 * Default DELETE args for users (force + reassign).
	 *
	 * @return array<string, mixed>
	 */
	public static function user_delete_endpoint_args(): array {
		return array(
			'force'    => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => 'Required to be true, as users do not support trashing.',
			),
			'reassign' => array(
				'type'        => 'integer',
				'description' => 'Reassign the deleted user\'s posts and links to this user ID.',
				'required'    => true,
			),
		);
	}

	/**
	 * Additional GET query args for single post/page items.
	 *
	 * Mirrors WP_REST_Posts_Controller::register_routes() get_item args.
	 *
	 * @return array<string, mixed>
	 */
	private function get_post_item_query_args(): array {
		if ( ! $this->controller instanceof \WP_REST_Posts_Controller ) {
			return array();
		}

		$schema          = $this->controller->get_item_schema();
		$get_item_args   = array();

		if ( isset( $schema['properties']['excerpt'] ) ) {
			$get_item_args['excerpt_length'] = array(
				'description' => 'Override the default excerpt length.',
				'type'        => 'integer',
			);
		}

		if ( isset( $schema['properties']['password'] ) ) {
			$get_item_args['password'] = array(
				'description' => 'The password for the post if it is password protected.',
				'type'        => 'string',
			);
		}

		return $get_item_args;
	}
}
