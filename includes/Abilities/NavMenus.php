<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/** Classic navigation menu actions exposed through the existing ability gateway. */
class NavMenus {
	/** Register menu abilities. */
	public function __construct() {
		$this->register_add();
		$this->register_remove();
	}

	/**
	 * Resolve a page by ID or exact title.
	 *
	 * @param string $value Page title or ID.
	 * @return \WP_Post|null Matching page.
	 */
	private function resolve_page( string $value ): ?\WP_Post {
		if ( ctype_digit( $value ) ) {
			$page = get_post( (int) $value );
			return $page && 'page' === $page->post_type ? $page : null;
		}
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'future' ),
				'title'          => $value,
				'posts_per_page' => 2,
			)
		);
		return 1 === count( $pages ) ? $pages[0] : null;
	}

	/**
	 * Find the page's post_type link within one menu.
	 *
	 * @param int $menu_id Menu term ID.
	 * @param int $page_id Page post ID.
	 * @return \WP_Post|null Matching menu item.
	 */
	private function find_item( int $menu_id, int $page_id ): ?\WP_Post {
		$items = wp_get_nav_menu_items( $menu_id );
		foreach ( $items ? $items : array() as $item ) {
			if ( 'post_type' === $item->type && 'page' === $item->object && $page_id === (int) $item->object_id ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Resolve user-facing identifiers and enforce the page permission.
	 *
	 * @param array $input Ability input.
	 * @return array Menu and page, or null entries.
	 */
	private function resolve_input( array $input ): array {
		$menu = wp_get_nav_menu_object( sanitize_text_field( (string) $input['menu'] ) );
		$page = $this->resolve_page( sanitize_text_field( (string) $input['page'] ) );
		if ( ! $menu || ! $page ) {
			return array( null, null );
		}
		if ( ! current_user_can( 'edit_post', $page->ID ) ) {
			return array( null, null );
		}
		return array( $menu, $page );
	}

	/** Register add-page-to-menu ability. */
	private function register_add(): void {
		blu_register_ability(
			'blu/add-page-to-menu',
			array(
				'label'               => 'Add Page to Classic Menu',
				'description'         => 'Add a page link to an existing classic WordPress navigation menu. Supply the exact menu and page names or their numeric IDs. For block navigation use its REST API instead.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'menu' => array(
							'type'        => 'string',
							'description' => 'Classic menu name or ID.',
						),
						'page' => array(
							'type'        => 'string',
							'description' => 'Page title or ID.',
						),
					),
					'required'   => array( 'menu', 'page' ),
				),
				'execute_callback'    => function ( $input ) {
					list( $menu, $page ) = $this->resolve_input( $input );
					if ( ! $menu || ! $page ) {
						return blu_prepare_ability_response( 404, 'Menu or page not found or not editable' );
					}
					$existing = $this->find_item( (int) $menu->term_id, (int) $page->ID );
					if ( $existing ) {
						return blu_prepare_ability_response(
							200,
							array(
								'id'      => $existing->ID,
								'menu_id' => $menu->term_id,
								'page_id' => $page->ID,
							)
						);
					}
					$request = new \WP_REST_Request( 'POST', '/wp/v2/menu-items' );
					$request->set_body_params(
						array(
							'title'     => get_the_title( $page ),
							'type'      => 'post_type',
							'object'    => 'page',
							'object_id' => $page->ID,
							'menus'     => $menu->term_id,
							'status'    => 'publish',
						)
					);
					return blu_standardize_rest_response( rest_do_request( $request ) );
				},
				'permission_callback' => fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/** Register remove-page-from-menu ability. */
	private function register_remove(): void {
		blu_register_ability(
			'blu/remove-page-from-menu',
			array(
				'label'               => 'Remove Page from Classic Menu',
				'description'         => 'Remove a page link from an existing classic WordPress navigation menu. Supply the exact menu and page names or their numeric IDs.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'menu' => array(
							'type'        => 'string',
							'description' => 'Classic menu name or ID.',
						),
						'page' => array(
							'type'        => 'string',
							'description' => 'Page title or ID.',
						),
					),
					'required'   => array( 'menu', 'page' ),
				),
				'execute_callback'    => function ( $input ) {
					list( $menu, $page ) = $this->resolve_input( $input );
					if ( ! $menu || ! $page ) {
						return blu_prepare_ability_response( 404, 'Menu or page not found or not editable' );
					}
					$item = $this->find_item( (int) $menu->term_id, (int) $page->ID );
					if ( ! $item ) {
						return blu_prepare_ability_response( 404, 'Page link not found in menu' );
					}
					$request = new \WP_REST_Request( 'DELETE', '/wp/v2/menu-items/' . $item->ID );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );
					if ( $response->is_error() ) {
						return blu_standardize_rest_response( $response );
					}
					return blu_prepare_ability_response(
						200,
						array(
							'deleted_id' => $item->ID,
							'menu_id'    => $menu->term_id,
							'page_id'    => $page->ID,
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);
	}
}
