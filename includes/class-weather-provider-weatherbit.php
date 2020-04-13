<?php

class Weather_Provider_Weatherbit extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Weatherbit', 'simple-location' );
		$this->slug = 'weatherbit';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_weatherbit_api' );
		}
		$args['cache_key'] = '';

		$this->region = false;
		$option       = get_option( 'sloc_weather_provider' );
		if ( 'weatherbit' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_weatherbit_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Weatherbit API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);

	}

	public static function admin_init() {
		add_settings_field(
			'weatherbitapi', // id
			__( 'Weatherbit API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_weatherbit_api',
			)
		);
	}

	public function is_station() {
		return false;
	}

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		/* if ( empty( $this->api ) ) {
			return array();
		} */
		$return = array();
		if ( $this->latitude && $this->longitude ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ) );
				if ( $conditions ) {
					return $conditions;
				}
			}
			$data = array(
				'key'   => $this->api,
				'lat'   => $this->latitude,
				'lon'   => $this->longitude,
				'units' => 'M',

			);
			$url  = 'https://api.weatherbit.io/v2.0/current';
			$url  = add_query_arg( $data, $url );
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
			if ( ! isset( $response['data'] ) ) {
				return $return;
			}
			$response              = $response['data'][0];
			$return['temperature'] = ifset_round( $response['temp'], 1 );
			$return['humidity']    = ifset_round( $response['rh'], 1 );
			$return['pressure']    = ifset_round( $response['pres'], 2 );
			$return['cloudiness']  = ifset_round( $response['clouds'] );

			$return['wind']           = array();
			$return['wind']['speed']  = ifset_round( $response['wind_spd'] );
			$return['wind']['degree'] = ifset_round( $response['wind_dir'], 1 );
			$return['wind']           = array_filter( $return['wind'] );
			$return['rain']           = ifset_round( $response['precip'], 2 );
			$return['visibility']     = ifset_round( $response['vis'], 2 );
			$return['airquality']     = ifset_round( $response['aqi'], 2 );
			$return['uv']             = ifset_round( $response['uv'] );
			$return['snow']           = ifset_round( $response['snow'], 2 );
			$return['summary']        = ifset( $response['weather']['description'] );
			$return['icon']           = $this->icon_map( $response['weather']['code'], ifset( $response['pod'] ) === 'd' );
			$calc                     = new Astronomical_Calculator( $this->latitude, $this->longitude, $this->altitude );
			$return['sunrise']        = $calc->get_iso8601( null );
			$return['sunset']         = $calc->get_iso8601( null, 'sunset' );

			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ), $return, $this->cache_time );
			}
			return array_filter( $return );
		}
		return false;
	}

	private function icon_map( $id, $is_day ) {
		$id = (int) $id;
		switch ( $id ) {
			case 200: // Thunderstorm with light rain
			case 201: // Thunderstorm with rain
			case 202: // Thunderstorm with heavy rain
			case 230: // Thunderstorm with light drizzle
			case 231: // Thunderstorm with drizzle
			case 232: // Thunderstorm with heavy drizzle
				return $is_day ? 'wi-day-thunderstorm' : 'wi-night-thunderstorm';
			case 233: // Thunderstorm with Hail
				return $is_day ? 'wi-day-hail' : 'wi-night-hail';
			case 300: // Light drizzle
			case 301: // Drizzle
				return $is_day ? 'wi-day-sprinkle' : 'wi-night-sprinkle';
			case 302: // Heavy Drizzle
			case 500: // Light Rain
			case 501: // Moderate Rain
				return $is_day ? 'wi-day-rain' : 'wi-night-rain';
			case 502: // Heavy Rain
			case 511: // Freezing Rain
			case 520: // Light shower rain
			case 521: // Shower Rain
			case 522: // Heavy Shower Rain
				return $is_day ? 'wi-day-showers' : 'wi-night-showers';
			case 600: // Light Snow
			case 601: // Snow
			case 602: // Heavy Snow
				return $is_day ? 'wi-day-snow' : 'wi-night-snow';
			case 610: // Mix Snow-Rain
				return $is_day ? 'wi-day-rain-mix' : 'wi-night-rain-mix';
			case 611: // Sleet
			case 612: // Heavy Sleet
				return $is_day ? 'wi-day-sleet' : 'wi-night-sleet';
			case 621: // Snow Shower
				return $is_day ? 'wi-day-sleet-storm' : 'wi-night-sleet-storm';
			case 622: // Heavy Snow Shower
				return $is_day ? 'wi-day-snow-wind' : 'wi-night-snow-wind';
			case 623: // Flurries
				return $is_day ? 'wi-day-snow' : 'wi-night-snow';
			case 700: // Mist
				return 'wi-sprinkle';
			case 711: // Smoke
				return 'wi-smoke';
			case 721: // Haze
				return 'wi-day-haze';
			case 731: // Sand-dust
				return 'wi-dust';
			case 741: // Fog
			case 751: // Freezing Fog
				return $is_day ? 'wi-day-fog' : 'wi-night-fog';
			case 800: // Clear Sky
				return $is_day ? 'wi-day-sunny' : 'wi-night-clear';
			case 801: // Few Clouds
			case 802: // Scattered Clouds
			case 803: // Broken Clouds
			case 804: // Overcast clouds
				return $is_day ? 'wi-day-cloudy' : 'wi-night-cloudy';
			case 900: // Unknown Precipitation
				return 'wi-rain';
			default:
				return '';
		}
	}

}

register_sloc_provider( new Weather_Provider_Weatherbit() );
