<?php
// Mapbox Map Provider
class Map_Provider_Mapbox extends Map_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Mapbox', 'simple-location' );
		$this->slug = 'mapbox';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_mapbox_api' );
		}

		if ( ! isset( $args['user'] ) ) {
			$args['user'] = get_option( 'sloc_mapbox_user' );
		}

		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_mapbox_style' );
		}

		$option = get_option( 'sloc_map_provider' );
		if ( 'mapbox' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_mapbox_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Mapbox Static Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'sloc_providers',
			'sloc_mapbox_user',
			array(
				'type'         => 'string',
				'description'  => 'Mapbox User',
				'show_in_rest' => false,
				'default'      => 'mapbox',
			)
		);
		register_setting(
			'simloc',
			'sloc_mapbox_style',
			array(
				'type'         => 'string',
				'description'  => 'Mapbox Style',
				'show_in_rest' => false,
				'default'      => 'streets-v10',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'mapboxuser', // id
			__( 'Mapbox User', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_providers',
			'sloc_api',
			array(
				'label_for' => 'sloc_mapbox_user',

			)
		);
		add_settings_field(
			'mapboxstyle', // id
			__( 'Mapbox Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_mapbox_style',
				'provider'  => new Map_Provider_Mapbox(),

			)
		);
		add_settings_field(
			'mapboxapi', // id
			__( 'Mapbox API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_mapbox_api',

			)
		);
	}

	public function default_styles() {
		return array(
			'streets-v11'                  => 'Streets',
			'outdoors-v11'                 => 'Outdoor',
			'light-v10'                    => 'Light',
			'dark-v10'                     => 'Dark',
			'satellite-v9'                 => 'Satellite',
			'satellite-streets-v11'        => 'Satellite Streets',
			'navigation-preview-day-v4'    => 'Navigation Preview Day',
			'navigation-preview-night-v4'  => 'Navigation Preview Night',
			'navigation-guidance-day-v4'   => 'Navigation Guidance Day',
			'navigation-guidance-night-v4' => 'Navigation Guidance Night',
		);
	}

	public function get_styles() {
		if ( empty( $this->user ) ) {
			return array();
		}
		$return = $this->default_styles();
		if ( 'mapbox' === $this->user ) {
			return $return;
		}
		$url          = 'https://api.mapbox.com/styles/v1/' . $this->user . '?access_token=' . $this->api;
				$args = array(
					'headers'             => array(
						'Accept' => 'application/json',
					),
					'timeout'             => 10,
					'limit_response_size' => 1048576,
					'redirection'         => 1,
					// Use an explicit user-agent for Simple Location
					'user-agent'          => 'Simple Location for WordPress',
				);
				$request = wp_remote_get( $url, $args );
				if ( is_wp_error( $request ) ) {
					return $request; // Bail early.
				}
				$body = wp_remote_retrieve_body( $request );
				$data = json_decode( $body );
				if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
					return new WP_Error( '403', $data->message );
				}
				foreach ( $data as $style ) {
					if ( is_object( $style ) ) {
						$return[ $style->id ] = $style->name;
					}
				}
				return $return;
	}


	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	public function get_the_map( $static = true ) {
		if ( $static ) {
			$map  = sprintf( '<img class="sloc-map" src="%s">', $this->get_the_static_map() );
			$link = $this->get_the_map_url();
			return '<a target="_blank" href="' . $link . '">' . $map . '</a>';
		}
	}

	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $this->style ) || empty( $locations ) ) {
			return '';
		}
		$user   = $this->user;
		$styles = $this->default_styles();
		if ( array_key_exists( $this->style, $styles ) ) {
			$user = 'mapbox';
		}
		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( 'pin-s(%1$s,%2$s)', $location[1], $location[0] );
		}
		$polyline = Polyline::encode( $locations );
		$map      = sprintf(
			'https://api.mapbox.com/styles/v1/%1$s/%2$s/static/%3$s,path-5+f44-0.5(%4$s)/auto/%5$sx%6$s?access_token=%7$s',
			$user,
			$this->style,
			implode( ',', $markers ),
			rawurlencode( $polyline ),
			$this->width,
			$this->height,
			$this->api
		);
		return $map;
	}

	public function get_the_static_map() {
		if ( empty( $this->api ) || empty( $this->style ) ) {
			return '';
		}
		$user   = $this->user;
		$styles = $this->default_styles();
		if ( array_key_exists( $this->style, $styles ) ) {
			$user = 'mapbox';
		}
		$map = sprintf(
			'https://api.mapbox.com/styles/v1/%1$s/%2$s/static/pin-s(%3$s,%4$s)/%3$s,%4$s, %5$s,0,0/%6$sx%7$s?access_token=%8$s',
			$user,
			$this->style,
			$this->longitude,
			$this->latitude,
			$this->map_zoom,
			$this->width,
			$this->height,
			$this->api
		);
		return $map;

	}

}

register_sloc_provider( new Map_Provider_Mapbox() );
