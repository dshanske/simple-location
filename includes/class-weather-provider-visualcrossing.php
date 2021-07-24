<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

/**
 * Weather Provider using Visual Crossing API.
 *
 * @since 1.0.0
 */
class Weather_Provider_VisualCrossing extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Visual Crossing', 'simple-location' );
		$this->slug = 'visualcrossing';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_visualcrossing_api' );
		}
		$this->region = false;
		$option       = get_option( 'sloc_weather_provider' );
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
		register_setting(
			'sloc_providers', // Option group.
			'sloc_visualcrossing_api', // Option name.
			array(
				'type'         => 'string',
				'description'  => 'Visual Crossing API',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	/**
	 * Admin Init to Add Settings Field.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		add_settings_field(
			'visualcrossingapi', // ID.
			__( 'Visual Crossing API', 'simple-location' ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_providers', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => 'sloc_visualcrossing_api',
			)
		);
	}

	/**
	 * Does This Provider Offer Station Data.
	 *
	 * @return boolean If supports station data return true.
	 */
	public function is_station() {
		return false;
	}

	/**
	 * Return array of current conditions
	 *
	 * @param int $time Time. Optional.
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return array();
		}
		$datetime = $this->datetime( $time );

		// Use timeline or current endpoint.
		$timeline = ( HOUR_IN_SECONDS < abs( $datetime->getTimestamp() - time() ) );

		if ( empty( $this->latitude ) || empty( $this->longitude ) ) {
			return array();
		}

		$conditions = $this->get_cache();
		if ( $conditions ) {
			return $conditions;
		}

		$args = array(
			'key'       => $this->api,
			'unitGroup' => 'metric',
		);

		// For historic data.
		if ( $timeline ) {
			$url = sprintf( 'https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/%1$s,%2$s/%3$s', $this->latitude, $this->longitude, $datetime->getTimestamp() );

		} else {
			$url = sprintf( 'https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/%1$s,%2$s', $this->latitude, $this->longitude );
		}

		$json = $this->fetch_json( $url, $args );
		$json = array_filter( $json );
		if ( array_key_exists( 'currentConditions', $json ) ) {
			$json = $json['currentConditions'];
		} elseif ( array_key_exists( 'days', $json ) ) {
			$json = $json['days'][0]['hours'];
			if ( array_key_exists( $datetime->format( 'G' ), $json ) ) {
				$json = $json[ $datetime->format( 'G' ) ];
			} else {
				return array();
			}
		}

		$return = $this->convert_data( $json );
		$return = $this->extra_data( $return, $time );

		$this->set_cache( $return );

		if ( WP_DEBUG ) {
			$return['raw'] = $json;
		}

		return $return;
	}

	/**
	 * Convert Data into common format
	 *
	 * @param string $json Raw JSON.
	 * @return array Current Conditions in Array
	 */
	public function convert_data( $json ) {
		$return                = array();
		$return['temperature'] = ifset_round( $json['temp'], 1 );
		$return['dewpoint']    = ifset_round( $json['dew'], 1 );
		$return['humidity']    = ifset_round( $json['humidity'], 1 );
		$return['pressure']    = ifset_round( $json['pressure'], 1 );
		$return['cloudiness']  = ifset( $json['cloudcover'] );
		$return['summary']     = ifset( $json['conditions'] );

		$return['wind']           = array();
		$return['wind']['speed']  = round( self::kmh_to_ms( ifset( $json['windspeed'] ) ), 1 );
		$return['wind']['gust']   = round( self::kmh_to_ms( ifset( $json['windgust'] ) ), 1 );
		$return['wind']['degree'] = ifset_round( $json['winddir'], 1 );
		$return['wind']           = array_filter( $return['wind'] );
		$return['rain']           = ifset_round( $json['precip'], 2 );
		$return['snow']           = self::cm_to_mm( ifset_round( $json['snow'], 2 ) );
		$return['radiation']      = ifset_round( $json['solarradiation'], 2 );
		$return['visibility']     = self::km_to_meters( ifset_round( $json['visibility'] ) );

		$return['icon'] = $this->icon_map( $json['icon'] );

		return array_filter( $return );
	}

	/**
	 * Return array of station data.
	 *
	 * @param string $id Weather type ID.
	 * @return string Icon ID.
	 */
	private function icon_map( $id ) {
		switch ( $id ) {
			case 'snow':
				return 'wi-snow';
			case 'rain':
				return 'wi-rain';
			case 'fog':
				return 'wi-fog';
			case 'wind':
				return 'wi-windy';
			case 'cloudy':
				return 'wi-cloudy';
			case 'partly-cloudy-day':
				return 'wi-day-cloudy';
			case 'partly-cloudy-night':
				return 'wi-night-cloudy';
			case 'clear-day':
				return 'wi-day-sunny';
			case 'clear-night':
				return 'wi-night-clear';
			default:
				return '';
		}
	}

}

register_sloc_provider( new Weather_Provider_VisualCrossing() );
