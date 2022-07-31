<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

/**
 * Weather Provider using AerisWeather API.
 *
 * @since 1.0.0
 */
class Weather_Provider_Aeris extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Aeris Weather', 'simple-location' );
		$this->slug        = 'aeris';
		$this->url         = 'https://www.aerisweather.com/';
		$this->description = __( 'While Aeris Weather does not offer a free tier, if you share your personal weather station with PWSWeather, this gives you free access to their API, which includes historic data.', 'simple-location' );
		$this->region      = false;
		$option            = get_option( 'sloc_weather_provider' );
		if ( $this->slug === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Aeris Client ID', 'simple-location' ), 'sloc_aeris_client_id' ); 
		self::register_settings_api( __( 'Aeris Client Secret', 'simple-location' ), 'sloc_aeris_client_secret' ); 
		register_setting(
			'sloc_providers', // Option group.
			'sloc_aeris_pws', // Option name.
			array(
				'type'         => 'number',
				'description'  => 'Include Personal Weather Stations in AerisWeather Data',
				'show_in_rest' => false,
				'default'      => 0,
			)
		);
	}

	/**
	 * Admin Init to Add Settings Field.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'AerisWeather', 'simple-location' ), 'sloc_aeris_client_id', __( 'Client ID', 'simple-location' ) );

		self::add_settings_parameter( __( 'AerisWeather', 'simple-location' ), 'sloc_aeris_client_secret', __( 'Client Secret', 'simple-location' ) );

		add_settings_field(
			'aerisweatherpws', // ID.
			__( 'Include Personal Weather Stations in AerisWeather Provider', 'simple-location' ), // Setting title.
			array( 'Loc_Config', 'checkbox_callback' ), // Display callback.
			'sloc_providers', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => 'sloc_aeris_pws',
			)
		);
	}

	/**
	 * Does This Provider Offer Station Data.
	 *
	 * @return boolean If supports station data return true.
	 */
	public function is_station() {
		return true;
	}

	/**
	 * Return array of current conditions
	 *
	 * @param int $time Time. Optional.
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		$client_id     = get_option( 'sloc_aeris_client_id' );
		$client_secret = get_option( 'sloc_aeris_client_secret' );
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return array();
		}
		$datetime = $this->datetime( $time );

		if ( HOUR_IN_SECONDS < abs( $datetime->getTimestamp() - time() ) ) {
			return array(
				'time'     => $time,
				'datetime' => $datetime,
			);
		}

		if ( ! empty( $this->station_id ) && empty( $this->latitude ) ) {
			return $this->get_station_data();
		}
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}

			$args = array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'p'             => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			);
			if ( 1 === (int) get_option( 'sloc_aeris_pws' ) ) {
				$args['filter'] = 'allstations';
			}

			$url  = 'https://api.aerisapi.com/observations/closest';
			$json = $this->fetch_json( $url, $args );
			if ( array_key_exists( 'success', $json ) && 'false' === $json['success'] ) {
				return $json;
			}
			$return = $this->convert_data( $json['response'][0] );
			$return = $this->extra_data( $return, $time );

			$this->set_cache( $return );

			if ( WP_DEBUG ) {
				$return['raw'] = $json;
			}

			return $return;
		}
	}

	/**
	 * Convert Data into common format
	 *
	 * @param string $json Raw JSON.
	 * @return array Current Conditions in Array
	 */
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
		$return['dewpoint']    = ifset_round( $observation['dewpointC'], 1 );
		$return['heatindex']   = ifset_round( $observation['heatindexC'], 1 );
		$return['windchill']   = ifset_round( $observation['windchillC'], 1 );
		$return['humidity']    = round( $observation['humidity'], 1 );
		$return['pressure']    = ifset_round( $observation['spressureMB'], 1 );
		$return['summary']     = ifset( $observation['weather'] );
		$return['cloudiness']  = ifset( $observation['sky'] );

		$return['wind']           = array();
		$return['wind']['speed']  = self::kmh_to_ms( ifset_round( $observation['windSpeedKPH'] ) );
		$return['wind']['gust']   = self::kmh_to_ms( ifset_round( $observation['windGustKPH'] ) );
		$return['wind']['degree'] = ifset_round( $observation['windDirDEG'], 1 );
		$return['wind']           = array_filter( $return['wind'] );
		$return['rain']           = ifset_round( $observation['precipMM'], 2 );
		$return['snow']           = self::cm_to_mm( ifset_round( $observation['snowDepthCM'], 2 ) );
		$return['radiation']      = ifset_round( $observation['solradWM2'], 2 );
		$return['uv']             = ifset_round( $observation['uvi'], 2 );
		$return['visibility']     = self::km_to_meters( ifset_round( $observation['visibilityKM'] ) );

		$return['icon'] = $this->icon_map( $observation['weatherCoded'] );

		return array_filter( $return );
	}

	/**
	 * Return array of station data.
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_station_data() {
		$client_id     = get_option( 'sloc_aeris_client_id' );
		$client_secret = get_option( 'sloc_aeris_client_secret' );
		$return        = array();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return array();
		}

		if ( empty( $this->station_id ) ) {
			return new WP_Error( 'unable_to_retrieve', __( 'No Station ID Provided', 'simple-location' ) );
		}

		$conditions = $this->get_cache();
		if ( $conditions ) {
			return $conditions;
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
		if ( empty( $return ) ) {
			return new WP_Error( 'unable_to_retrieve', __( 'Unable to Retrieve', 'simple-location' ) );
		}

		if ( WP_DEBUG ) {
			$return['raw'] = $json;
		}

		$this->set_cache( $return );
		return $return;
	}

	/**
	 * Return array of station data.
	 *
	 * @param string $id Weather type ID.
	 * @return string Icon ID.
	 */
	private function icon_map( $id ) {
		$id = explode( ':', $id );
		if ( 3 !== count( $id ) ) {
			return '';
		}
		$coverage  = $id[0];
		$intensity = $id[1];
		$weather   = $id[2];
		switch ( $weather ) {
			case 'A': // Hail.
				return 'wi-hail';
			case 'BD':  // Blowing dust.
				return 'wi-dust';
			case 'BN': // Blowing sand.
				return 'wi-sandstorm';
			case 'BR': // Mist.
				return 'wi-umbrella';
			case 'BS': // Blowing snow.
				return 'wi-snow-wind';
			case 'BY': // Blowing spray.
				return 'wi-rain-wind';
			case 'F': // Fog.
				return 'wi-fog';
			case 'FR':  // Frost.
				return 'wi-snowflake-cold';
			case 'H': // Haze.
				return 'wi-day-haze';
			case 'IF': // Ice fog.
			case 'IC': // Ice crystals.
				return 'icy';
			case 'IP': // Ice pellets / Sleet.
				return 'wi-sleet';
			case 'K': // Smoke.
				return 'wi-smoke';
			case 'L': // Drizzle.
			case 'R': // Rain.
			case 'RW': // Rain showers.
				return 'wi-rain';
			case 'RS': // Rain/snow mix.
			case 'WM': // Wintry mix (snow, sleet, rain).
				return 'wi-rain-mix';
			case 'SI': // Snow/sleet mix.
			case 'S': // Snow.
			case 'SW': // Snow showers.
				return 'wi-snow';
			case 'T': // Thunderstorms.
				return 'wi-thunderstorm';
			case 'UP': // Unknown precipitation. May occur in an automated observation station, which cannot determine the precipitation type falling.
				return 'wi-sprinkle';
			case 'VA': // Volcanic ash.
				return 'wi-volcano';
			case 'WP': // Waterspouts.
				return 'wi-water';
			case 'ZF': // Freezing fog.
				return 'wi-fog';
			case 'ZL': // Freezing drizzle.
			case 'ZR': // Freezing rain.
			case 'ZY': // Freezing spray.
				return 'wi-raindrop';
			case 'FW': // Fair/Mostly sunny. Cloud coverage is 7-32% of the sky.
				return 'wi-day-sunny';
			case 'SC': // Partly cloudy. Cloud coverage is 32-70% of the sky.
				return 'wi-day-cloudy';
			case 'BK': // Mostly Cloudy. Cloud coverage is 70-95% of the sky.
				return 'wi-cloudy';
			case 'OV': // Cloudy/Overcast. Cloud coverage is 95-100% of the sky.
				return 'wi-cloud';
			case 'CL': // Clear. Cloud coverage is 0-7% of the sky.
				return 'wi-day-sunny';
		}
		return '';
	}

}
