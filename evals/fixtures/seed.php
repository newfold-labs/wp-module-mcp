<?php
/**
 * Seed objects referenced by standalone AI eval prompts.
 *
 * Runs only in the disposable wp-env workflow.
 *
 * @package WP_Module_MCP_Evals
 */

( static function () {
	$ids = array();

	$create_post = static function ( $type, $title ) {
		return wp_insert_post(
			array(
				'post_type'    => $type,
				'post_title'   => $title,
				'post_content' => 'A fixture item for MCP AI evaluation.',
				'post_status'  => 'publish',
			),
			true
		);
	};

	foreach (
	array(
		'post_get'    => array( 'post', 'AI Eval Read Post' ),
		'post_delete' => array( 'post', 'AI Eval Delete Post' ),
		'page_get'    => array( 'page', 'AI Eval Read Page' ),
		'page_update' => array( 'page', 'AI Eval Update Page' ),
		'page_delete' => array( 'page', 'AI Eval Delete Page' ),
	) as $key => $spec
	) {
		$ids[ $key ] = $create_post( $spec[0], $spec[1] );
	}

	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC', true );
	foreach ( array( 'media_get', 'media_update', 'media_delete' ) as $key ) {
		$upload = wp_upload_bits( $key . '.png', null, $png );
		if ( ! empty( $upload['error'] ) ) {
			throw new RuntimeException( 'Media fixture upload failed' );
		}
		$ids[ $key ] = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $key,
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
	}

	foreach ( array( 'user_get', 'user_update', 'user_delete' ) as $key ) {
		$ids[ $key ] = wp_create_user( 'mcp_eval_' . $key, wp_generate_password( 32 ), $key . '@example.test' );
	}

	foreach (
	array(
		'category_update' => array( 'category', 'AI Eval Update Category' ),
		'category_delete' => array( 'category', 'AI Eval Delete Category' ),
		'tag_update'      => array( 'post_tag', 'AI Eval Update Tag' ),
		'tag_delete'      => array( 'post_tag', 'AI Eval Delete Tag' ),
		'wc_tag_update'   => array( 'product_tag', 'AI Eval Update Product Tag' ),
		'wc_tag_delete'   => array( 'product_tag', 'AI Eval Delete Product Tag' ),
		'wc_brand_update' => array( 'product_brand', 'AI Eval Update Brand' ),
		'wc_brand_delete' => array( 'product_brand', 'AI Eval Delete Brand' ),
	) as $key => $spec
	) {
		$created_term = wp_insert_term( $spec[1], $spec[0] );
		$ids[ $key ]  = is_wp_error( $created_term ) ? $created_term : $created_term['term_id'];
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'AI Eval Delete Product' );
	$product->set_status( 'publish' );
	$product->set_regular_price( '19.99' );
	$ids['wc_product_delete'] = $product->save();

	$theme                    = get_stylesheet();
	$styles                   = get_posts(
		array(
			'post_type'      => 'wp_global_styles',
			'name'           => 'wp-global-styles-' . $theme,
			'posts_per_page' => 1,
		)
	);
	$ids['global_styles_get'] = $styles ? $styles[0]->ID : wp_insert_post(
		array(
			'post_type'    => 'wp_global_styles',
			'post_title'   => 'Custom Styles',
			'post_name'    => 'wp-global-styles-' . $theme,
			'post_content' => '{}',
			'post_status'  => 'publish',
		),
		true
	);

	foreach ( $ids as $key => $id ) {
		if ( is_wp_error( $id ) || ! is_numeric( $id ) || (int) $id < 1 ) {
			throw new RuntimeException( 'Could not create AI eval fixture' );
		}
		$ids[ $key ] = (int) $id;
	}

	echo 'MCP_EVAL_FIXTURES=' . wp_json_encode( $ids ) . "\n";
} )();
