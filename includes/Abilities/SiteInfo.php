<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * SiteInfo ability for WordPress site information.
 */
class SiteInfo {

	/**
	 * Constructor - registers site info ability.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register site info ability.
	 */
	private function register_abilities(): void {
		blu_register_ability(
			'blu/get-site-info',
			array(
				'label'               => 'Get Site Info',
				'description'         => 'Provides detailed information about the WordPress site like site name, url, description, admin email, plugins, themes, users, and more',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					return array(
						'site_name'        => get_bloginfo( 'name' ),
						'site_url'         => get_bloginfo( 'url' ),
						'site_description' => get_bloginfo( 'description' ),
						'site_admin_email' => get_bloginfo( 'admin_email' ),
						'wordpress_version' => get_bloginfo( 'version' ),
						'language'         => get_bloginfo( 'language' ),
						'plugins'          => $this->get_plugins_info(),
						'themes'           => array(
							'active' => $this->get_active_theme_info(),
							'all'    => wp_get_themes(),
						),
						'users'            => $this->get_users_info(),
					);
				},
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);
	}

	/**
	 * Get plugins information.
	 *
	 * @return array
	 */
	private function get_plugins_info(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		$plugins = array();
		foreach ( $all_plugins as $plugin_path => $plugin_data ) {
			$plugins[] = array(
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'active'  => in_array( $plugin_path, $active_plugins, true ),
			);
		}

		return $plugins;
	}

	/**
	 * Get active theme information.
	 *
	 * @return array
	 */
	private function get_active_theme_info(): array {
		$theme = wp_get_theme();
		return array(
			'name'    => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
			'author'  => $theme->get( 'Author' ),
		);
	}

	/**
	 * Get users information.
	 *
	 * @return array
	 */
	private function get_users_info(): array {
		$users       = get_users();
		$user_counts = count_users();

		return array(
			'total' => $user_counts['total_users'],
			'roles' => $user_counts['avail_roles'],
		);
	}
}
