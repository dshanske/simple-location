<?php

class Weather_Provider_Aeris extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name   = __( 'Aeris Weather', 'simple-location' );
		$this->slug   = 'aeris';
		$this->region = false;
		$option       = get_option( 'sloc_weather_provider' );
		if ( $this->slug === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_aeris_client_id', // option name
			array(
				'type'         => 'string',
				'description'  => 'AerisWeather Client ID',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'sloc_providers', // option group
			'sloc_aeris_client_secret', // option name
			array(
				'type'         => 'string',
				'description'  => 'AerisWeather Client Secret',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'aerisweatherid', // id
			__( 'AerisWeather Client ID', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_aeris_client_id',
			)
		);
		add_settings_field(
			'aerisweathersecret', // id
			__( 'AerisWeather Client Secret', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_aeris_client_secret',
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

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		$client_id     = get_option( 'sloc_aeris_client_id' );
		$client_secret = get_option( 'sloc_aeris_client_secret' );
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return array();
		}

		if ( ! empty( $this->station_id ) && empty( $this->latitude ) ) {
			return $this->get_station_data();
		}
		if ( $this->latitude && $this->longitude ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ) );
				if ( $conditions ) {
					return $conditions;
				}
			}

			$args = array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'p'             => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'filter'        => 'allstations',
			);

			$url  = 'https://api.aerisapi.com/observations/closest';
			$json = $this->fetch_json( $url, $args );
			if ( array_key_exists( 'success', $json ) && 'false' === $json['success'] ) {
				return $json;
			}
			$return = $this->convert_data( $json['response'][0] );
			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ), $return, $this->cache_time );
			}
			if ( WP_DEBUG ) {
				$return['raw'] = $json;
			}
			return $return;
		}
	}

	public function convert_data( $json ) {
		$return               = array();
		$return['station_id'] = ifset( $json['id'] );
		if ( array_key_exists( 'loc', $json ) ) {
			$return['latitude']  = $json['loc']['lat'];
			$return['longitude'] = $json['loc']['long'];
		}
		if ( array_key_exists( 'profile', $json ) ) {
			$return['altitude'] = ifset( $json['profile']['elevM'] );
		}
		if ( array_key_exists( 'relativeTo', $json ) && array_key_exists( 'distanceKm', $json['relativeTo'] ) ) {
			$return['distance'] = $json['relativeTo']['distanceKm'] * 1000;
		}

		$observation           = $json['ob'];
		$return['temperature'] = round( $observation['tempC'], 1 );
		$return['humidity']    = round( $observation['humidity'], 1 );
		$return['pressure']    = ifset_round( $observation['pressureMB'], 1 );
		$return['summary']     = ifset( $observation['weather'] );
		$return['cloudiness']  = ifset( $observation['sky'] );

		$return['wind']           = array();
		$return['wind']['speed']  = round( $observation['windSpeedKPH'] );
		$return['wind']['degree'] = ifset_round( $observation['windDirDEG'], 1 );
		$return['wind']           = array_filter( $return['wind'] );
		$return['rain']           = ifset_round( $observation['precipMM'], 2 );
		$return['snow']           = ifset_round( $observation['snowDepthCM'], 2 );
		$return['uv']             = ifset_round( $observation['uvi'], 2 );
		if ( array_key_exists( 'visibilityKM', $observation ) ) {
			$return['visibility'] = $observation['visibilityKM'] * 1000;
		}

		$return['icon'] = $this->icon_map( $observation['weatherCoded'] );

		$calc              = new Astronomical_Calculator( $this->latitude, $this->longitude, $this->altitude );
		$return['sunrise'] = $calc->get_iso8601( null );
		$return['sunset']  = $calc->get_iso8601( null, 'sunset' );

		return array_filter( $return );
	}

	public function get_station_data() {
		$client_id     = get_option( 'sloc_aeris_client_id' );
		$client_secret = get_option( 'sloc_aeris_client_secret' );
		$return        = array();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return array();
		}

		if ( ! empty( $this->station_id ) ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->station_id ) );
				if ( $conditions ) {
					return $conditions;
				}
			}

			$args = array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'p'             => $this->station_id,
			);

			$url = 'https://api.aerisapi.com/observations/closest';

			$json = $this->fetch_json( $url, $args );
			if ( array_key_exists( 'success', $json ) && 'false' === $json['success'] ) {
				return $json;
			}
			$return = $this->convert_data( $json['response'][0] );
			if ( WP_DEBUG ) {
				$return['raw'] = $json;
			}

			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->station_id ), $return, $this->cache_time );
			}
			return $return;
		}
		return new WP_Error( 'unable_to_retrieve', __( 'Unable to Retrieve', 'simple-location' ) );
	}

	private function icon_map( $id ) {
		return '';
	}

}

register_sloc_provider( new Weather_Provider_Aeris() );
