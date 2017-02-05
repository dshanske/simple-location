<?php
/**
 * Location API
 *
 *
 *
 * @package Simple Location
 */

class Location_Controller {
	public static function register_routes() {
		register_rest_route( 'simple_location/v1', '/reverse-address', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( 'Location_Controller', 'reverse' ),
				'args' => array(
					'longitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
					'latitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
				),
			'permissions_callback' => 'is_user_logged_in',
		) ) 
		);
		register_rest_route( 'simple_location/v1', '/venue', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( 'Location_Controller', 'get_venues' ),
				'args' => array(
					'longitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
					'latitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
				),
			'permissions_callback' => 'is_user_logged_in',
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( 'Location_Controller', 'save_venue' ),
				'args' => array(
					'longitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
					'latitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
				),
			'permissions_callback' => 'is_user_logged_in',
			) )
		);
		register_rest_route( 'simple_location/v1', '/venue' . '/(?P<id>[\d]+)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( 'Location_Controller', 'get_venue_by_id' ),
				'args' => array(
					'longitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
					'latitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
				),
			'permissions_callback' => 'is_user_logged_in',
			),
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( 'Location_Controller', 'edit_venue' ),
				'args' => array(
					'longitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
					'latitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
				),
			'permissions_callback' => 'is_user_logged_in',
			),
			array(
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => array( 'Location_Controller', 'delete_venue' ),
				'args' => array(
					'longitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
					'latitude' => array(
						'required' => 'true',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function($param, $request, $key) {
							return is_numeric( $param );
						}
					),
				),
			'permissions_callback' => 'is_user_logged_in',
			) )
		);

	
	}

}

