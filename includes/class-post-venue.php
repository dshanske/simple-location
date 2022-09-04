<?php
/**
 * Venue Custiomer Post Type
 *
 * Registers a custom post type and sets its behavior.
 *
 */

// add_action( 'init', array( 'Post_Venue', 'init' ) );
// Register Vanue Taxonomy.
add_action( 'init', array( 'Post_Venue', 'register' ), 1 );

class Post_Venue {
	/**
	 * Register the custom post type for venues.
	 */
	public static function register() {
		$labels = array(
			'name' => __( 'Venues', 'simple-location' ),
			'singular_name' => __( 'Venue', 'simple-location' ),
			'add_new' => __( 'Add New', 'simple-location' ),
			'edit_item' => __( 'Edit Venue', 'simple-location' ),
			'new_item' => __( 'New Venue', 'simple-location' ),
			'view_item' => __( 'View Venue', 'simple-location' ),
			'view_items' => __( 'View Venues', 'simple-location' ),
			'search_items' => __( 'Search Venues', 'simple-location' ),
			'not_found' => __( 'No Venues Found', 'simple-location' ),
			'not_found_in_trash' => __( 'No venues found in Trash', 'simple-location' ),
			'all_items' => __( 'All Venues', 'simple-location' ),
			'insert_into_item' => __( 'Insert into Venue', 'simple-location' ),
			'uploaded_to_this_item' => __( 'Uploaded to this venue', 'simple-location' ),
			'featured_image' => __( 'Venue Image', 'simple-location' ),
			'set_featured_image' => __( 'Set Venue Image', 'simple-location' ),
			'remove_featured_image' => __( 'Remove Venue Image', 'simple-location' ),
			'use_featured_image' => __( 'Use Venue Image', 'simple-location' )
		);

		register_post_type( 
			'venue', 
			array(
				'label' => __( 'Venues', 'simple-location' ),
				'labels' => $labels,
				'description' => __( 'Represents a place', 'simple-location' ),
				'public' => true,
				'show_in_rest' => true,
				'menu_icon' => 'dashicons-location',
				'supports' => array( 'title', 'thumbnail', 'geo-location' ),
				'taxonomies' => array( 'location' ),
				'has_archive' => false,
				'delete_with_user' => false,
			)
		);


		register_meta(
			'post',
			'venue_url',
			array(
				'object_subtype'    => 'venue',
				'type'              => 'string',
				'description'       => __( 'URL of Venue', 'simple-location' ),
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'post',
			'venue_alternate',
			array(
				'object_subtype'    => 'venue',
				'type'              => 'array',
				'description'       => __( 'Alternate URLs for Venue', 'simple-location' ),
				'single'            => true,
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'array',
						'items' => array(
							'type' => 'string',
							'format' => 'uri'
						)
					)
				)
			)
		);

		register_meta(
			'post',
			'venue_id',
			array(
				'object_subtype'    => 'post',
				'type'              => 'number',
				'description'       => __( 'Venue Post ID', 'simple-location' ),
				'sanitize_callback' => 'intval',
				'single'            => true,
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'post',
			'venue_radius',
			array(
				'object_subtype'    => 'post',
				'type'              => 'number',
				'description'       => __( 'Radius around the Venue in meters', 'simple-location' ),
				'sanitize_callback' => 'intval',
				'single'            => true,
				'show_in_rest'      => true,
			)
		);


	}


}
