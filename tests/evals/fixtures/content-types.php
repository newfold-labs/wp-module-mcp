<?php
/**
 * Content types used by AI eval prompts, isolated to the wp-env test site.
 *
 * @package WP_Module_MCP_Evals
 */

add_action(
	'init',
	static function () {
		foreach (
			array(
				'books'       => 'Books',
				'portfolio'   => 'Portfolio',
				'event'       => 'Events',
				'testimonial' => 'Testimonials',
			) as $slug => $label
		) {
			register_post_type(
				$slug,
				array(
					'label'        => $label,
					'public'       => true,
					'show_in_rest' => true,
					'supports'     => array( 'title', 'editor', 'excerpt' ),
				)
			);
		}
	}
);
