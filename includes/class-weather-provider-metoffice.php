<?php

class Weather_Provider_MetOffice extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Met Office(UK)', 'simple-location' );
		$this->slug = 'metofficeuk';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_metoffice_api' );
		}
		$this->region = 'GB';
		$option       = get_option( 'sloc_weather_provider' );
		if ( 'metofficeuk' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_metoffice_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Met Office API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'sloc_metoffice_api', // id
			__( 'Met Office API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_metoffice_api',
			)
		);
	}

	public function set( $lat, $lng = null, $alt = null ) {
		if ( ! $lng && is_array( $lat ) ) {
			if ( isset( $lat['station_id'] ) ) {
				$this->station_id = $lat['station_id'];
			}
		}
			parent::set( $lat, $lng, $alt );
	}

	public function is_station() {
		return true;
	}

	public function get_sitelist() {
		$response = get_transient( 'metoffice_sites' );
		if ( false !== $response ) {
			return $response;
		}
		$url  = 'http://datapoint.metoffice.gov.uk/public/data/val/wxobs/all/json/sitelist?key=' . $this->api;
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

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );
		if ( ! isset( $response['Locations'] ) ) {
			return false;
		}
		$response = $response['Locations']['Location'];
		set_transient( 'metoffice_sites', $response, WEEK_IN_SECONDS );
		return $response;
	}

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		$return = array();
		if ( ! empty( $this->station_id ) ) {
			return self::get_station_data();
		}
		if ( $this->latitude && $this->longitude ) {
			$sitelist = $this->get_sitelist();
			foreach ( $sitelist as $key => $value ) {
				$sitelist[ $key ]['distance'] = round( WP_Geo_Data::gc_distance( $this->latitude, $this->longitude, $value['latitude'], $value['longitude'] ) );
			}
			usort(
				$sitelist,
				function( $a, $b ) {
					return $a['distance'] > $b['distance'];
				}
			);
			if ( 100000 > $sitelist[0]['distance'] ) {
				$this->station_id = $sitelist[0]['id'];
				return self::get_station_data();
			}
		}
		return new WP_Error( 'failed', __( 'Failure', 'simple-location' ) );
	}

	public function weather_type( $type ) {
		$types = array(
			0  => 'Clear night',
			1  => 'Sunny day',
			2  => 'Partly cloudy (night)',
			3  => 'Partly cloudy (day)',
			4  => 'Not used',
			5  => 'Mist',
			6  => 'Fog',
			7  => 'Cloudy',
			8  => 'Overcast',
			9  => 'Light rain shower (night)',
			10 => 'Light rain shower (day)',
			11 => 'Drizzle',
			12 => 'Light rain',
			13 => 'Heavy rain shower (night)',
			14 => 'Heavy rain shower (day)',
			15 => 'Heavy rain',
			16 => 'Sleet shower (night)',
			17 => 'Sleet shower (day)',
			18 => 'Sleet',
			19 => 'Hail shower (night)',
			20 => 'Hail shower (day)',
			21 => 'Hail',
			22 => 'Light snow shower (night)',
			23 => 'Light snow shower (day)',
			24 => 'Light snow',
			25 => 'Heavy snow shower (night)',
			26 => 'Heavy snow shower (day)',
			27 => 'Heavy snow',
			28 => 'Thunder shower (night)',
			29 => 'Thunder shower (day)',
			30 => 'Thunder',
		);
		if ( array_key_exists( intval( $type ), $types ) ) {
			return $types[ intval( $types ) ];
		}
		return __( 'Not Available', 'simple-location' );
	}

	public function station() {
		$list = $this->get_sitelist();
		foreach ( $list as $site ) {
			if ( $site['id'] === $this->station_id ) {
				return $site;
			}
		}
	}

	public function get_station_data() {
		$return             = array(
			'station_id'   => $this->station_id,
			'station_data' => $this->station(),
		);
		$return['distance'] = round( WP_Geo_Data::gc_distance( $this->latitude, $this->longitude, $return['station_data']['latitude'], $return['station_data']['longitude'] ) );

		$url  = sprintf( 'http://datapoint.metoffice.gov.uk/public/data/val/wxobs/all/json/%1$s?res=hourly&key=%2$s', $this->station_id, $this->api );
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

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );
		if ( WP_DEBUG ) {
			$return['raw'] = $response;
		}
		if ( ! isset( $response['SiteRep'] ) ) {
			return $return;
		}

		$response = $response['SiteRep']['DV']['Location'];
		if ( isset( $response['lat'] ) ) {
			$return['latitude']  = $response['lat'];
			$return['longitude'] = $response['lon'];
			$return['altitude']  = $response['elevation'];
		}
		$response = end( $response['Period'] );
		if ( wp_is_numeric_array( $response['Rep'] ) ) {
			$properties = end( $response['Rep'] );
		} else {
			$properties = $response['Rep'];
		}

		$return['temperature'] = ifset( $properties['T'] );
		$return['humidity']    = ifset( $properties['H'] );
		$return['visibility']  = ifset( $properties['V'] );
		$return['pressure']    = ifset( $properties['P'] );
		$wind                  = array();
		$wind['speed']         = ifset( $properties['S'] );
		$wind                  = array_filter( $wind );
		if ( ! empty( $wind ) ) {
			$return['wind'] = $wind;
		}
		$return['summary'] = $this->weather_type( ifset( $properties['W'] ) );
		$return['icon']    = self::icon_map( ifset( $properties['W'] ) );

		return array_filter( $return );
	}

	private function icon_map( $id ) {
		return null;
	}

}

register_sloc_provider( new Weather_Provider_MetOffice() );
