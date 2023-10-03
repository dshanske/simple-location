<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

/**
 * Weather Provider using Meteostat API.
 *
 * @since 1.0.0
 */
class Weather_Provider_Meteostat extends Weather_Provider {

	/**
	 * Station Data.
	 *
	 * @var string
	 */
	protected $station;

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Meteostat', 'simple-location' );
		$this->slug        = 'meteostat';
		$this->url         = 'https://meteostat.net';
		$this->description = __( 'Meteostat is an open and free archive for weather data, and offers bulk historic weather data. It does not offer current weather consistently.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_meteostat_api' );
		}
		$this->station = array();
		$this->region  = false;
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Meteostat API', 'simple-location' ), 'sloc_meteostat_api' );
	}

	/**
	 * Admin Init to Add Settings Field.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Meteostat', 'simple-location' ), 'sloc_meteostat_api' );
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
		if ( empty( $this->api ) ) {
			return array();
		}
		$datetime = $this->datetime( $time );

		if ( $this->station_id && ! $this->latitude ) {
			return $this->get_station_data( $time );
		}

		$file = trailingslashit( Simple_Location_Plugin::$path ) . 'data/meteostat.json';
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'filesystem_error', "File doesn't exist", wp_json_encode( $file ) );
		}
		$data     = file_get_contents( $file );
		$sitelist = json_decode( $data, true );
		foreach ( $sitelist as $key => $value ) {
				$sitelist[ $key ]['distance'] = round( geo_distance( $this->latitude, $this->longitude, $value['location']['latitude'], $value['location']['longitude'] ) );
		}
		usort(
			$sitelist,
			function ( $a, $b ) {
				return $a['distance'] > $b['distance'];
			}
		);
		if ( 100000 > $sitelist[0]['distance'] ) {
			$this->station_id = $sitelist[0]['id'];
			$this->station    = array_filter( $sitelist[0] );
			$return           = self::get_station_data( $time );
			unset( $this->station_id );
			$this->set_cache( $return );
			return $return;
		}
		return self::get_fallback_conditions( $time );

		$conditions = $this->get_cache();
		if ( $conditions ) {
			return $conditions;
		}
		return array();
	}

	/**
	 * Return current conditions at the station.
	 *
	 * @param int $time Timestamp.
	 * @return array Current Conditions.
	 */
	public function get_station_data( $time = null ) {
		$conditions = $this->get_cache();
		if ( $conditions ) {
			return $conditions;
		}

		if ( empty( $this->station_id ) ) {
			return new WP_Error( 'station_empty', __( 'Station ID Not Set', 'simple-location' ) );
		}

		$return = array(
			'station_id'   => $this->station_id,
			'station_data' => $this->station,
		);
		if ( ! empty( $this->station ) & array_key_exists( 'latitude', $this->station ) ) {
			$return['distance'] = round( geo_distance( $this->latitude, $this->longitude, $return['station_data']['latitude'], $return['station_data']['longitude'] ) );
		}

		$datetime = $this->datetime( $time );
		$datetime = $datetime->setTimezone( new DateTimeZone( 'GMT' ) );
		$tomorrow = clone $datetime;
		$tomorrow->add( new DateInterval( 'P1D' ) );
		$args = array(
			'station' => $this->station_id,
			'start'   => $datetime->format( 'Y-m-d' ),
			'end'     => $tomorrow->format( 'Y-m-d' ),

		);

		// For historic data.
		$url  = 'https://api.meteostat.net/v2/stations/hourly.php';
		$json = $this->fetch_json( $url, $args, array( 'x-api-key' => $this->api ) );
		if ( array_key_exists( 'meta', $json ) ) {
			$return['meta'] = $json['meta'];
		}

		if ( WP_DEBUG ) {
			$return['raw'] = $json;
		}

		if ( ! array_key_exists( 'data', $json ) ) {
			return array();
		}

		$json = $json['data'];

		if ( array_key_exists( $datetime->format( 'G' ), $json ) ) {
			$json = $json[ $datetime->format( 'G' ) ];
		}

		$return = array_merge( $return, $this->convert_data( $json ) );
		$return = $this->extra_data( $return, $time );

		$this->set_cache( $return );

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
		$return['dewpoint']    = ifset_round( $json['dwpt'], 1 );
		$return['humidity']    = ifset_round( $json['rhum'], 1 );
		$return['pressure']    = ifset_round( $json['pres'], 1 );
		$return['summary']     = ifset( $json['conditions'] );

		$return['windspeed']  = round( self::kmh_to_ms( ifset( $json['wspd'] ) ), 1 );
		$return['windgust']   = round( self::kmh_to_ms( ifset( $json['wpgt'] ) ), 1 );
		$return['winddegree'] = ifset_round( $json['wdir'], 1 );
		$return['rain']       = ifset_round( $json['prcp'], 2 );
		$return['snow']       = self::cm_to_mm( ifset_round( $json['snow'], 2 ) );
		$return['summary']    = ifset( $json['coco'] );
		$return['code']       = $this->code_map( $json['coco'] );

		return array_filter( $return );
	}

	/**
	 * Convert Status Code to Text.
	 *
	 * @param int $code Code.
	 * @return string Textual Summary of Status Code.
	 **/
	public static function get_status( $code ) {
		$conditions = array(
			1  => __( 'Clear', 'simple-location' ),
			2  => __( 'Fair', 'simple-location' ),
			3  => __( 'Cloudy', 'simple-location' ),
			4  => __( 'Overcast', 'simple-location' ),
			5  => __( 'Fog', 'simple-location' ),
			6  => __( 'Freezing Fog', 'simple-location' ),
			7  => __( 'Light Rain', 'simple-location' ),
			8  => __( 'Rain', 'simple-location' ),
			9  => __( 'Heavy Rain', 'simple-location' ),
			10 => __( 'Freezing Rain', 'simple-location' ),
			11 => __( 'Heavy Freezing Rain', 'simple-location' ),
			12 => __( 'Sleet', 'simple-location' ),
			13 => __( 'Heavy Sleet', 'simple-location' ),
			14 => __( 'Light Snowfall', 'simple-location' ),
			15 => __( 'Snowfall', 'simple-location' ),
			16 => __( 'Heavy Snowfall', 'simple-location' ),
			17 => __( 'Rain Shower', 'simple-location' ),
			18 => __( 'Heavy Rain Shower', 'simple-location' ),
			19 => __( 'Sleet Shower', 'simple-location' ),
			20 => __( 'Heavy Sleet Shower', 'simple-location' ),
			21 => __( 'Snow Shower', 'simple-location' ),
			22 => __( 'Heavy Snow Shower', 'simple-location' ),
			23 => __( 'Lightning', 'simple-location' ),
			24 => __( 'Hail', 'simple-location' ),
			25 => __( 'Thunderstorm', 'simple-location' ),
			26 => __( 'Heavy Thunderstorm', 'simple-location' ),
			27 => __( 'Storm', 'simple-location' ),
		);
		if ( array_key_exists( $code, $conditions ) ) {
			return $conditions[ $code ];
		}
		return '';
	}

	/**
	 * Convert Code
	 *
	 * @param int $code Code.
	 * @return string Textual Summary of Status Code.
	 **/
	public static function code_map( $code ) {
		$conditions = array(
			1  => 800,
			2  => 800,
			3  => 802,
			4  => 804,
			5  => 741,
			6  => 741,
			7  => 500,
			8  => 503,
			9  => 504,
			10 => 511,
			11 => 511,
			12 => 611,
			13 => 611,
			14 => 600,
			15 => 601,
			16 => 602,
			17 => 521,
			18 => 522,
			19 => 613,
			20 => 613,
			21 => 621,
			22 => 622,
			23 => 790,
			24 => 624,
			25 => 211,
			26 => 212,
			27 => 202,
		);
		if ( array_key_exists( $code, $conditions ) ) {
			return $conditions[ $code ];
		}
		return '';
	}
}
