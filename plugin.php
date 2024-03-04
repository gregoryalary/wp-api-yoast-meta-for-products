<?php

add_action( 'plugins_loaded', 'WPAPIYoast_init' );

/**
 * Plugin Name: Yoast to REST API With Products
 * Description: Adds Yoast fields to page, post and product metadata to WP REST API responses
 * Author: Niels Garve, Pablo Postigo, Tedy Warsitha, Charlie Francis, Grégory Alary
 * Author URI: https://github.com/gregoryalary
 * Version: 1.4.2
 * Plugin URI: https://github.com/gregoryalary/wp-api-yoast-meta-for-products
 */
class Yoast_To_REST_API {

	protected $keys = array(
		'yoast_wpseo_focuskw',
		'yoast_wpseo_title',
		'yoast_wpseo_metadesc',
		'yoast_wpseo_linkdex',
		'yoast_wpseo_metakeywords',
		'yoast_wpseo_meta-robots-noindex',
		'yoast_wpseo_meta-robots-nofollow',
		'yoast_wpseo_meta-robots-adv',
		'yoast_wpseo_canonical',
		'yoast_wpseo_redirect',
		'yoast_wpseo_opengraph-title',
		'yoast_wpseo_opengraph-description',
		'yoast_wpseo_opengraph-image',
		'yoast_wpseo_twitter-title',
		'yoast_wpseo_twitter-description',
		'yoast_wpseo_twitter-image'
	);

	function __construct() {
		error_log('Yoast_To_REST_API: Constructor called');
		add_action( 'rest_api_init', array( $this, 'add_yoast_data' ) );
	}

	function add_yoast_data() {
		error_log('Yoast_To_REST_API: Adding Yoast data to REST API');

		// Posts
		register_rest_field( 'post',
			'yoast_meta',
			array(
				'get_callback'    => array( $this, 'wp_api_encode_yoast' ),
				'update_callback' => array( $this, 'wp_api_update_yoast' ),
				'schema'          => null,
			)
		);

		// Pages
		register_rest_field( 'page',
			'yoast_meta',
			array(
				'get_callback'    => array( $this, 'wp_api_encode_yoast' ),
				'update_callback' => array( $this, 'wp_api_update_yoast' ),
				'schema'          => null,
			)
		);

		// Category
		register_rest_field( 'category',
			'yoast_meta',
			array(
				'get_callback'    => array( $this, 'wp_api_encode_yoast_category' ),
				'update_callback' => null,
				'schema'          => null,
			)
		);

		// Tag
		register_rest_field( 'tag',
			'yoast_meta',
			array(
				'get_callback'    => array( $this, 'wp_api_encode_yoast_tag' ),
				'update_callback' => null,
				'schema'          => null,
			)
		);

		// Product
		register_rest_field( 'product',
			'yoast_meta',
			array(
				'get_callback'    => array( $this, 'wp_api_encode_yoast_tag' ),
				'update_callback' => null,
				'schema'          => null,
			)
		);

		// Terms
		register_rest_field( 'term',
			'yoast_meta',
			array(
				'get_callback'    => array( $this, 'wp_api_encode_yoast' ),
				'update_callback' => array( $this, 'wp_api_update_yoast' ),
				'schema'          => null,
			)
		);

		// Public custom post types
		$types = get_post_types( array(
			'public'   => true,
			'_builtin' => false
		) );

		foreach ( $types as $key => $type ) {
			register_rest_field( $type,
				'yoast_meta',
				array(
					'get_callback'    => array( $this, 'wp_api_encode_yoast' ),
					'update_callback' => array( $this, 'wp_api_update_yoast' ),
					'schema'          => null,
				)
			);
		}

		error_log('Yoast_To_REST_API: Yoast data added successfully');
	}

	/**
	 * Updates post meta with values from post/put request.
	 *
	 * @param array $value
	 * @param object $data
	 * @param string $field_name
	 *
	 * @return array
	 */
	function wp_api_update_yoast( $value, $data, $field_name ) {
		$data_id = is_callable( array( $data, 'get_ID' ) ) ? $data->get_ID() : $data->ID;
		error_log("Yoast_To_REST_API: Updating Yoast data for post ID {$data_id}");

		foreach ( $value as $k => $v ) {

			if ( in_array( $k, $this->keys ) ) {
				error_log("Yoast_To_REST_API: Updating Yoast data for key {$k} with value {$v}");
				! empty( $k ) ? update_post_meta( $data_id, '_' . $k, $v ) : null;
			}
		}

		error_log("Yoast_To_REST_API: Yoast data update completed for post ID {$data_id}");
		return $this->wp_api_encode_yoast( $data_id, null, null );
	}

	function wp_api_encode_yoast( $p, $field_name, $request ) {
		$wpseo_frontend = WPSEO_Frontend_To_REST_API::get_instance();
		$wpseo_frontend->reset();

		query_posts( array(
			'p'         => $p['id'], // ID of a page, post, or custom type
			'post_type' => 'any'
		) );

		the_post();

		$yoast_meta = array(
			'yoast_wpseo_title'     => $wpseo_frontend->get_content_title(),
			'yoast_wpseo_metadesc'  => $wpseo_frontend->metadesc( false ),
			'yoast_wpseo_canonical' => $wpseo_frontend->canonical( false ),
		);

		/**
		 * Filter the returned yoast meta.
		 *
		 * @since 1.4.2
		 * @param array $yoast_meta Array of metadata to return from Yoast.
		 * @param \WP_Post $p The current post object.
		 * @param \WP_REST_Request $request The REST request.
		 * @return array $yoast_meta Filtered meta array.
		 */
		$yoast_meta = apply_filters( 'wpseo_to_api_yoast_meta', $yoast_meta, $p, $request );

		wp_reset_query();

		return (array) $yoast_meta;
	}

	private function wp_api_encode_taxonomy() {
		$wpseo_frontend = WPSEO_Frontend_To_REST_API::get_instance();
		$wpseo_frontend->reset();

		$yoast_meta = array(
			'yoast_wpseo_title'    => $wpseo_frontend->get_taxonomy_title(),
			'yoast_wpseo_metadesc' => $wpseo_frontend->metadesc( false ),
		);

		/**
		 * Filter the returned yoast meta for a taxonomy.
		 *
		 * @since 1.4.2
		 * @param array $yoast_meta Array of metadata to return from Yoast.
		 * @return array $yoast_meta Filtered meta array.
		 */
		$yoast_meta = apply_filters( 'wpseo_to_api_yoast_taxonomy_meta', $yoast_meta );

		return (array) $yoast_meta;
	}

	function wp_api_encode_yoast_category( $category ) {
		query_posts( array(
			'cat' => $category['id'],
		) );

		the_post();

		$res = $this->wp_api_encode_taxonomy();

		wp_reset_query();

		return $res;
	}

	function wp_api_encode_yoast_tag( $tag ) {
		query_posts( array(
			'tag_id' => $tag['id'],
		) );

		the_post();

		$res = $this->wp_api_encode_taxonomy();

		wp_reset_query();

		return $res;
	}
}

function WPAPIYoast_init() {
	error_log('Yoast_To_REST_API: Initializing Yoast to REST API plugin');

	if ( class_exists( 'WPSEO_Frontend' ) ) {
		include __DIR__ . '/classes/class-wpseo-frontend-to-rest-api.php';

		$yoast_To_REST_API = new Yoast_To_REST_API();
	} else {
		add_action( 'admin_notices', 'wpseo_not_loaded' );
	}
}

function wpseo_not_loaded() {
	printf(
		'<div class="error"><p>%s</p></div>',
		__( '<b>Yoast to REST API</b> plugin not working because <b>Yoast SEO</b> plugin is not active.' )
	);
}
