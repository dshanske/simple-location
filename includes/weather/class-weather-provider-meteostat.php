<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weather Provider using Meteostat API.
 *
 * @since 1.0.0
 */
class Weather_Provider_Meteostat extends Weather_Provider {

	/**
	 * Station Data.
	 *
	 * @var array
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
		$this->description = __( 'Meteostat is an open archive for weather data, and offers bulk historic weather data. It does not offer current weather consistently. The data is free, but using their JSON API is free for 500 requests a month', 'simple-location' );
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
	 * Bundled list of Meteostat stations, relative to the plugin directory. Built by bin/update-data.php.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const STATIONS_FILE = 'data/meteostat-stations.csv';

	/**
	 * Farthest a station can be from the location, in meters.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	const MAX_DISTANCE = 100000;

	/**
	 * Return array of current conditions
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Finds the station in a slim station list, preferring one with hourly data for the requested time.
	 *
	 * @param int $time Time. Optional.
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return $this->missing_api_key_error();
		}

		if ( $this->station_id && ! $this->latitude ) {
			return $this->get_station_data( $time );
		}

		$station = $this->find_station( $time );
		if ( is_wp_error( $station ) ) {
			return $station;
		}
		if ( $station ) {
			$this->station_id = $station['id'];
			$this->station    = $station;
			$return           = self::get_station_data( $time );
			// Cache by the location that was asked for, not the station.
			$this->station_id = null;
			$this->set_cache( $return, $time );
			return $return;
		}
		return $this->no_results_error();
	}

	/**
	 * Finds the station for the current location and a time, cached for each location and day.
	 *
	 * @since 6.0.0
	 *
	 * @param int|null $time Optional. Requested time as a timestamp. Default now.
	 * @return array|null|WP_Error Station, null if there is none in range, or an error if the station list is missing.
	 */
	protected function find_station( $time = null ) {
		$date = $this->datetime( $time )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d' );
		$key  = 'sloc_meteostat_station_' . md5( implode( ',', array( round( (float) $this->latitude, 2 ), round( (float) $this->longitude, 2 ), $date ) ) );

		$station = get_transient( $key );
		if ( false !== $station ) {
			return is_array( $station ) ? $station : null;
		}
		$station = self::nearest_station( $this->latitude, $this->longitude, $date );
		if ( is_wp_error( $station ) ) {
			return $station;
		}
		// An empty string records that there is no station in range.
		set_transient( $key, $station ?? '', WEEK_IN_SECONDS );
		return $station;
	}

	/**
	 * Finds the nearest station within 100 km, preferring one with hourly data for a date.
	 *
	 * Reads the station list one line at a time, so memory use stays small. A station has data for a date when
	 * the date is within its hourly data, or after it if the station was still reporting within 30 days of when
	 * the list was built. Without such a station in range, the nearest station is used.
	 *
	 * @since 6.0.0
	 *
	 * @param float       $latitude  Latitude.
	 * @param float       $longitude Longitude.
	 * @param string|null $date      Optional. Date as Y-m-d (UTC). Default today.
	 * @param string|null $file      Optional. Station list. Default the bundled list.
	 * @return array|null|WP_Error Station in the shape of Meteostat's station data, null if none is in range, or an error if the list can't be read.
	 */
	public static function nearest_station( $latitude, $longitude, $date = null, $file = null ) {
		if ( null === $file ) {
			$file = trailingslashit( Simple_Location_Plugin::$path ) . self::STATIONS_FILE;
		}
		$handle = is_readable( $file ) ? fopen( $file, 'r' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'filesystem_error', __( 'The Meteostat station list is missing.', 'simple-location' ), $file );
		}
		if ( null === $date ) {
			$date = gmdate( 'Y-m-d' );
		}
		$latitude  = (float) $latitude;
		$longitude = (float) $longitude;

		// The first line says when the list was built; stations reporting close to then are still active.
		$active = '';
		if ( preg_match( '/built (\d{4}-\d{2}-\d{2})/', (string) fgets( $handle ), $match ) ) {
			$active = gmdate( 'Y-m-d', strtotime( $match[1] . ' -30 days' ) );
		}
		$columns = fgetcsv( $handle, 0, ',', '"', '' );

		$nearest  = null;
		$covering = null;
		// A degree of latitude is about 111 km, so rows farther than that in latitude can be skipped cheaply.
		$range = self::MAX_DISTANCE / 111000;
		while ( false !== ( $line = fgets( $handle ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			// Only the name, the last column, can be quoted, so the coordinates can be read without parsing the row.
			$start = explode( ',', $line, 4 );
			if ( count( $start ) < 4 || abs( (float) $start[1] - $latitude ) > $range ) {
				continue;
			}
			$row = str_getcsv( rtrim( $line, "\r\n" ), ',', '"', '' );
			if ( count( $row ) !== count( $columns ) ) {
				continue;
			}
			$distance = geo_distance( $latitude, $longitude, $row[1], $row[2] );
			if ( $distance > self::MAX_DISTANCE ) {
				continue;
			}
			$row       = array_combine( $columns, $row );
			$candidate = array( $distance, $row );
			if ( ! $nearest || $distance < $nearest[0] ) {
				$nearest = $candidate;
			}
			$has_data = '' !== $row['hourly_start'] && $row['hourly_start'] <= $date && ( $date <= $row['hourly_end'] || ( '' !== $active && $row['hourly_end'] >= $active ) );
			if ( $has_data && ( ! $covering || $distance < $covering[0] ) ) {
				$covering = $candidate;
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$found = $covering ?? $nearest;
		if ( ! $found ) {
			return null;
		}
		$row = $found[1];
		return array_filter(
			array(
				'id'        => $row['id'],
				'name'      => array( 'en' => $row['name'] ),
				'country'   => $row['country'],
				'region'    => $row['region'],
				'location'  => array(
					'latitude'  => (float) $row['latitude'],
					'longitude' => (float) $row['longitude'],
					'elevation' => '' === $row['elevation'] ? null : (int) $row['elevation'],
				),
				'timezone'  => $row['timezone'],
				'inventory' => array(
					'hourly' => array(
						'start' => $row['hourly_start'],
						'end'   => $row['hourly_end'],
					),
				),
			)
		);
	}

	/**
	 * Return current conditions at the station.
	 *
	 * @param int $time Timestamp.
	 * @return array Current Conditions.
	 */
	public function get_station_data( $time = null ) {
		$conditions = $this->get_cache( $time );
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
		if ( ! empty( $this->latitude ) && isset( $this->station['location']['latitude'], $this->station['location']['longitude'] ) ) {
			$return['distance'] = round( geo_distance( $this->latitude, $this->longitude, $this->station['location']['latitude'], $this->station['location']['longitude'] ) );
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
		$url  = 'https://meteostat.p.rapidapi.com/stations/hourly';
		$json = $this->fetch_json(
			$url,
			$args,
			array(
				'x-rapidapi-key'  => $this->api,
				'x-rapidapi-host' => 'meteostat.p.rapidapi.com',
			)
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
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

		$this->set_cache( $return, $time );

		return $return;
	}

	/**
	 * Convert Data into common format
	 *
	 * @since 6.0.0 The summary is the condition's name, not its numeric code.
	 * @since 6.2.0 Meteostat's snow is the snow depth, in mm, stored as snowdepth, not as snowfall ten times too large.
	 *
	 * @param string $json Raw JSON.
	 * @return array Current Conditions in Array
	 */
	public function convert_data( $json ) {
		$return                = array();
		$return['temperature'] = self::round_value( $json['temp'] ?? null, 1 );
		$return['dewpoint']    = self::round_value( $json['dwpt'] ?? null, 1 );
		$return['humidity']    = self::round_value( $json['rhum'] ?? null, 1 );
		$return['pressure']    = self::round_value( $json['pres'] ?? null, 1 );

		$return['windspeed']  = self::round_value( self::kmh_to_ms( $json['wspd'] ?? null ), 1 );
		$return['windgust']   = self::round_value( self::kmh_to_ms( $json['wpgt'] ?? null ), 1 );
		$return['winddegree'] = self::round_value( $json['wdir'] ?? null, 1 );
		$return['rain']       = self::round_value( $json['prcp'] ?? null, 2 );
		// Meteostat's snow is the snow on the ground, already in millimeters.
		$return['snowdepth']  = self::round_value( $json['snow'] ?? null, 2 );
		$coco                 = $json['coco'] ?? null;
		if ( is_numeric( $coco ) ) {
			$return['summary'] = self::get_status( (int) $coco );
			$return['code']    = self::code_map( (int) $coco );
		}

		return self::filter_weather( $return );
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

	/**
	 * Returns the credit for the provider's weather.
	 *
	 * Meteostat's data is CC BY 4.0, credited in any reasonable manner.
	 *
	 * @since 6.1.0
	 *
	 * @return array Credit. See Weather_Provider::attribution().
	 */
	protected function attribution() {
		return array(
			'text'     => __( 'Source: Meteostat and its data providers', 'simple-location' ),
			'url'      => 'https://meteostat.net/',
			'adjacent' => false,
		);
	}
}
