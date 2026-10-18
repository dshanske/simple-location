<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weather Provider using xWeather API formerly AerisWeather.
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
		$this->name        = __( 'Vaisala Xweather', 'simple-location' );
		$this->slug        = 'aeris';
		$this->url         = 'https://www.xweather.com/';
		$this->description = __( 'While Xweather does not offer a free tier, if you share your personal weather station with PWSWeather, this gives you free access to their API, which includes historic data.', 'simple-location' );
		$this->region      = false;
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Xweather Client ID', 'simple-location' ), 'sloc_aeris_client_id' );
		self::register_settings_api( __( 'Xweather Client Secret', 'simple-location' ), 'sloc_aeris_client_secret' );
		register_setting(
			'sloc_providers', // Option group.
			'sloc_aeris_pws', // Option name.
			array(
				'type'         => 'number',
				'description'  => 'Include Personal Weather Stations in Xweather Data',
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
		self::add_settings_parameter( __( 'Xweather', 'simple-location' ), 'sloc_aeris_client_id', __( 'Client ID', 'simple-location' ) );

		self::add_settings_parameter( __( 'Xweather', 'simple-location' ), 'sloc_aeris_client_secret', __( 'Client Secret', 'simple-location' ) );

		add_settings_field(
			'aerisweatherpws', // ID.
			__( 'Include Personal Weather Stations in Xweather Provider', 'simple-location' ), // Setting title.
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

		if ( ! empty( $this->station_id ) && empty( $this->latitude ) ) {
			return $this->get_station_data( $time );
		}
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache( $time );
			if ( $conditions ) {
				return $conditions;
			}

			$args = array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'p'             => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			);
			if ( is_numeric( $time ) && 0 !== $time ) {
				$args['from'] = $time;
			}

			if ( 1 === (int) get_option( 'sloc_aeris_pws' ) ) {
				$args['filter'] = 'allstations';
			}

			$url         = 'https://data.api.xweather.com/observations/closest';
			$json        = $this->fetch_json( $url, $args );
			$observation = $this->first_observation( $json );
			if ( is_wp_error( $observation ) ) {
				return $observation;
			}
			$return = $this->convert_data( $observation );
			$return = $this->extra_data( $return, $time );

			$this->set_cache( $return, $time );

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
		if ( ! is_array( $json ) || ! isset( $json['ob'] ) || ! is_array( $json['ob'] ) ) {
			return array();
		}
		$return               = array();
		$return['station_id'] = $json['id'] ?? null;
		if ( array_key_exists( 'loc', $json ) ) {
			$return['latitude']  = $json['loc']['lat'];
			$return['longitude'] = $json['loc']['long'];
		}
		if ( array_key_exists( 'profile', $json ) ) {
			$return['altitude'] = $json['profile']['elevM'] ?? null;
		}
		if ( isset( $json['relativeTo']['distanceKM'] ) && is_numeric( $json['relativeTo']['distanceKM'] ) ) {
			$return['distance'] = self::km_to_meters( $json['relativeTo']['distanceKM'] );
		}

		$observation           = $json['ob'];
		$return['temperature'] = self::round_value( $observation['tempC'] ?? null, 1 );
		$return['dewpoint']    = self::round_value( $observation['dewpointC'] ?? null, 1 );
		$return['heatindex']   = self::round_value( $observation['heatindexC'] ?? null, 1 );
		$return['windchill']   = self::round_value( $observation['windchillC'] ?? null, 1 );
		$return['humidity']    = self::round_value( $observation['humidity'] ?? null, 1 );
		$return['pressure']    = self::round_value( $observation['spressureMB'] ?? null, 1 );
		$return['summary']     = $observation['weather'] ?? null;
		$return['cloudiness']  = $observation['sky'] ?? null;

		$return['windspeed']  = self::kmh_to_ms( self::round_value( $observation['windSpeedKPH'] ?? null ) );
		$return['windgust']   = self::kmh_to_ms( self::round_value( $observation['windGustKPH'] ?? null ) );
		$return['winddegree'] = self::round_value( $observation['windDirDEG'] ?? null, 1 );
		$return['rain']       = self::round_value( $observation['precipMM'] ?? null, 2 );
		$return['snow']       = self::cm_to_mm( self::round_value( $observation['snowDepthCM'] ?? null, 2 ) );
		$return['radiation']  = self::round_value( $observation['solradWM2'] ?? null, 2 );
		$return['uv']         = self::round_value( $observation['uvi'] ?? null, 2 );
		$visibility           = $observation['visibilityKM'] ?? null;
		$return['visibility'] = is_numeric( $visibility ) ? round( self::km_to_meters( $visibility ) ) : null;

		$return['code'] = $this->code_map( $observation['weatherCoded'] ?? '' );

		return self::filter_weather( $return );
	}

	/**
	 * Return array of station data.
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_station_data( $time = null ) {
		$client_id     = get_option( 'sloc_aeris_client_id' );
		$client_secret = get_option( 'sloc_aeris_client_secret' );
		$return        = array();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return array();
		}

		if ( empty( $this->station_id ) ) {
			return new WP_Error( 'unable_to_retrieve', __( 'No Station ID Provided', 'simple-location' ) );
		}

		$conditions = $this->get_cache( $time );
		if ( $conditions ) {
			return $conditions;
		}

		$args = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'p'             => $this->station_id,
		);

		if ( is_numeric( $time ) && 0 !== $time ) {
			$args['from'] = $time;
		}

		$url = 'https://data.api.xweather.com/observations/closest';

		$json        = $this->fetch_json( $url, $args );
		$observation = $this->first_observation( $json );
		if ( is_wp_error( $observation ) ) {
			return $observation;
		}

		$return = $this->convert_data( $observation );
		if ( empty( $return ) ) {
			return new WP_Error( 'unable_to_retrieve', __( 'Unable to Retrieve', 'simple-location' ) );
		}

		if ( WP_DEBUG ) {
			$return['raw'] = $json;
		}

		$this->set_cache( $return, $time );
		return $return;
	}

	/**
	 * Returns the first observation in an Xweather response.
	 *
	 * Xweather reports errors in the body with "success": false, and an empty response when there is no data.
	 *
	 * @since 6.0.0
	 *
	 * @param array|WP_Error $json Decoded response.
	 * @return array|WP_Error Observation, or an error.
	 */
	protected function first_observation( $json ) {
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( ! is_array( $json ) || empty( $json['success'] ) ) {
			$code    = isset( $json['error']['code'] ) && is_string( $json['error']['code'] ) ? $json['error']['code'] : 'unable_to_retrieve';
			$message = isset( $json['error']['description'] ) && is_string( $json['error']['description'] ) ? $json['error']['description'] : __( 'Unable to Retrieve', 'simple-location' );
			return new WP_Error( $code, $message );
		}
		$response = $json['response'] ?? array();
		if ( is_array( $response ) && wp_is_numeric_array( $response ) ) {
			$response = reset( $response );
		}
		if ( ! is_array( $response ) || empty( $response['ob'] ) ) {
			return new WP_Error( 'unable_to_retrieve', __( 'No Observation Found', 'simple-location' ) );
		}
		return $response;
	}

	/**
	 * Return array of station data.
	 *
	 * @param string $id Weather type ID.
	 * @return string Icon ID.
	 */
	private function code_map( $id ) {
		if ( ! is_string( $id ) ) {
			return '';
		}
		$id = explode( ':', $id );
		if ( 3 !== count( $id ) ) {
			return '';
		}
		// The code is coverage:intensity:weather; only the weather part maps to a condition.
		$weather = $id[2];
		switch ( $weather ) {
			case 'A': // Hail.
				return 624;
			case 'BD':  // Blowing dust.
				return 761;
			case 'BN': // Blowing sand.
				return 751;
			case 'BR': // Mist.
				return 701;
			case 'BS': // Blowing snow.
				return 625;
			case 'BY': // Blowing spray.
				return '512';
			case 'F': // Fog.
				return 741;
			case 'FR':  // Frost.
				return 702;
			case 'H': // Haze.
				return 721;
			case 'IF': // Ice fog.
			case 'IC': // Ice crystals.
				return 703;
			case 'IP': // Ice pellets / Sleet.
				return 611;
			case 'K': // Smoke.
				return 711;
			case 'L': // Drizzle.
				return 301;
			case 'R': // Rain.
				return 500;
			case 'RW': // Rain showers.
				return 521;
			case 'RS': // Rain/snow mix.
				return 610;
			case 'WM': // Wintry mix (snow, sleet, rain).
				return 615;
			case 'SI': // Snow/sleet mix.
				return 613;
			case 'S': // Snow.
				return 601;
			case 'SW': // Snow showers.
				return 621;
			case 'T': // Thunderstorms.
				return 211;
			case 'UP': // Unknown precipitation. May occur in an automated observation station, which cannot determine the precipitation type falling.
				return 900;
			case 'VA': // Volcanic ash.
				return 762;
			case 'WP': // Waterspouts.
				return 772;
			case 'ZF': // Freezing fog.
				return 731;
			case 'ZL': // Freezing drizzle.
			case 'ZR': // Freezing rain.
				return 511;
			case 'ZY': // Freezing spray.
				return 511;
			case 'FW': // Fair/Mostly sunny. Cloud coverage is 7-32% of the sky.
				return 801;
			case 'SC': // Partly cloudy. Cloud coverage is 32-70% of the sky.
				return 802;
			case 'BK': // Mostly Cloudy. Cloud coverage is 70-95% of the sky.
				return 803;
			case 'OV': // Cloudy/Overcast. Cloud coverage is 95-100% of the sky.
				return 804;
			case 'CL': // Clear. Cloud coverage is 0-7% of the sky.
				return 800;
		}
		return '';
	}
}
