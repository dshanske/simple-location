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
		$this->name        = __( 'Visual Crossing', 'simple-location' );
		$this->url         = 'https://www.visualcrossing.com/';
		$this->description = __( 'Offers a free plan with 1000 requests per day, plus a metered rate after that of $0.0001 per request. Offer historical data. Requires API key.', 'simple-location' );
		$this->slug        = 'visualcrossing';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_visualcrossing_api' );
		}
		$this->region = false;
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Visual Crossing API Key', 'simple-location' ), 'sloc_visualcrossing_api' );
	}

	/**
	 * Admin Init to Add Settings Field.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Visual Crossing', 'simple-location' ), 'sloc_visualcrossing_api' );
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

		if ( is_null( $time ) || 0 === $time ) {
			$time = time();
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
			'lang'      => 'id',
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
		$return['summary']     = self::conditions_map( ifset( $json['conditions'] ) );
		$return['code']        = self::code_map( ifset( $json['conditions'] ) );

		$return['windspeed']  = round( self::kmh_to_ms( ifset( $json['windspeed'] ) ), 1 );
		$return['windgust']   = round( self::kmh_to_ms( ifset( $json['windgust'] ) ), 1 );
		$return['winddegree'] = ifset_round( $json['winddir'], 1 );
		$return['rain']       = ifset_round( $json['precip'], 2 );
		$return['snow']       = self::cm_to_mm( ifset_round( $json['snow'], 2 ) );
		$return['radiation']  = ifset_round( $json['solarradiation'], 2 );
		$return['visibility'] = self::km_to_meters( ifset_round( $json['visibility'] ) );

		return array_filter( $return );
	}

	/**
	 * Return array of station data.
	 *
	 * @param string $id Weather type ID.
	 * @return string Icon ID.
	 */
	private function conditions_map( $id ) {
		$conditions = array(
			'type_1'  => __( 'Blowing Or Drifting Snow', 'simple-location' ),
			'type_2'  => __( 'Drizzle', 'simple-location' ),
			'type_3'  => __( 'Heavy Drizzle', 'simple-location' ),
			'type_4'  => __( 'Light Drizzle', 'simple-location' ),
			'type_5'  => __( 'Heavy Drizzle/Rain', 'simple-location' ),
			'type_6'  => __( 'Light Drizzle/Rain', 'simple-location' ),
			'type_7'  => __( 'Dust storm', 'simple-location' ),
			'type_8'  => __( 'Fog', 'simple-location' ),
			'type_9'  => __( 'Freezing Drizzle/Freezing Rain', 'simple-location' ),
			'type_10' => __( 'Heavy Freezing Drizzle/Freezing Rain', 'simple-location' ),
			'type_11' => __( 'Light Freezing Drizzle/Freezing Rain', 'simple-location' ),
			'type_12' => __( 'Freezing Fog', 'simple-location' ),
			'type_13' => __( 'Heavy Freezing Rain', 'simple-location' ),
			'type_14' => __( 'Light Freezing Rain', 'simple-location' ),
			'type_15' => __( 'Funnel Cloud/Tornado', 'simple-location' ),
			'type_16' => __( 'Hail Showers', 'simple-location' ),
			'type_17' => __( 'Ice', 'simple-location' ),
			'type_18' => __( 'Lightning Without Thunder', 'simple-location' ),
			'type_19' => __( 'Mist', 'simple-location' ),
			'type_20' => __( 'Precipitation In Vicinity', 'simple-location' ),
			'type_21' => __( 'Rain', 'simple-location' ),
			'type_22' => __( 'Heavy Rain And Snow', 'simple-location' ),
			'type_23' => __( 'Light Rain And Snow', 'simple-location' ),
			'type_24' => __( 'Rain Showers', 'simple-location' ),
			'type_25' => __( 'Heavy Rain', 'simple-location' ),
			'type_26' => __( 'Light Rain', 'simple-location' ),
			'type_27' => __( 'Sky Coverage Decreasing', 'simple-location' ),
			'type_28' => __( 'Sky Coverage Increasing', 'simple-location' ),
			'type_29' => __( 'Sky Unchanged', 'simple-location' ),
			'type_30' => __( 'Smoke Or Haze', 'simple-location' ),
			'type_31' => __( 'Snow', 'simple-location' ),
			'type_32' => __( 'Snow And Rain Showers', 'simple-location' ),
			'type_33' => __( 'Snow Showers', 'simple-location' ),
			'type_34' => __( 'Heavy Snow', 'simple-location' ),
			'type_35' => __( 'Light Snow', 'simple-location' ),
			'type_36' => __( 'Squalls', 'simple-location' ),
			'type_37' => __( 'Thunderstorm', 'simple-location' ),
			'type_38' => __( 'Thunderstorm Without Precipitation', 'simple-location' ),
			'type_39' => __( 'Diamond Dust', 'simple-location' ),
			'type_40' => __( 'Hail', 'simple-location' ),
			'type_41' => __( 'Overcast', 'simple-location' ),
			'type_42' => __( 'Partly cloudy', 'simple-location' ),
			'type_43' => __( 'Clear', 'simple-location' ),
		);
		if ( array_key_exists( $id, $conditions ) ) {
			return $conditions[ $id ];
		}
		return '';
	}

	/*
	Return array of station data.
	 *
	 * @param string $id Weather type ID.
	 * @return string Icon ID.
	 */
	private function code_map( $id ) {
		$conditions = array(
			'type_1'  => 625,
			'type_2'  => 301,
			'type_3'  => 302,
			'type_4'  => 300,
			'type_5'  => 312,
			'type_6'  => 310,
			'type_7'  => 761,
			'type_8'  => 741,
			'type_9'  => 511,
			'type_10' => 511,
			'type_11' => 511,
			'type_12' => 741,
			'type_13' => 511,
			'type_14' => 511,
			'type_15' => 781,
			'type_16' => 624,
			'type_17' => 513,
			'type_18' => 791,
			'type_19' => 701,
			'type_20' => 900,
			'type_21' => 503,
			'type_22' => 622,
			'type_23' => 620,
			'type_24' => 521,
			'type_25' => 504,
			'type_26' => 500,
			'type_27' => '',
			'type_28' => '',
			'type_29' => '',
			'type_30' => 721,
			'type_31' => 601,
			'type_32' => 610,
			'type_33' => 621,
			'type_34' => 602,
			'type_35' => 601,
			'type_36' => 771,
			'type_37' => 211,
			'type_38' => 211,
			'type_39' => 626,
			'type_40' => 624,
			'type_41' => 804,
			'type_42' => 802,
			'type_43' => 800,
		);
		if ( array_key_exists( $id, $conditions ) ) {
			return $conditions[ $id ];
		}
		return '';
	}
}
