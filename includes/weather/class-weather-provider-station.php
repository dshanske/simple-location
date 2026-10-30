<?php
/**
 * Station Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weather Provider using Custom Station JSON.
 *
 * Each station is a URL returning JSON, either in the current conditions format, version 1, or in the earlier
 * format, which has the plugin's weather keys at the top level. See the readme's "Custom station format".
 *
 * @since 4.0.0
 * @since 6.1.0 Reads the current conditions format, version 1.
 */
class Weather_Provider_Station extends Weather_Provider {

	/**
	 * Identifier of the current conditions format.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const FORMAT = 'current-conditions';

	/**
	 * Version of the current conditions format this reads.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const FORMAT_VERSION = 1;

	/**
	 * Furthest a station can be, in meters, to give the weather for a location.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const RANGE = 10000;

	/**
	 * Oldest reading, in seconds, that counts as current.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_AGE = 3600;

	/**
	 * Fields of a station in the settings.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FIELDS = array( 'id', 'url', 'latitude', 'longitude', 'attribution', 'attribution_url' );

	/**
	 * Name of the setting holding the stations, which is also its settings section's ID.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const OPTION = 'sloc_stations';

	/**
	 * Settings page, and option group, of the stations: the Stations tab.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const SETTINGS_PAGE = 'sloc_stations';

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Custom Station Provider', 'simple-location' );
		$this->description = __( 'Weather from your own stations: URLs that return the current conditions as JSON. When it is the weather provider, the nearest station within 10 km is used.', 'simple-location' );
		$this->slug        = 'station';
		$this->region      = false;

		parent::__construct( $args );
	}

	/**
	 * Is Provider Active
	 */
	public function is_active() {
		return true;
	}

	/**
	 * Init Function
	 *
	 * @since 6.1.0 The stations are sanitized when saved.
	 */
	public static function init() {
		register_setting(
			static::SETTINGS_PAGE, // option group.
			static::OPTION, // option name.
			array(
				'type'              => 'array',
				'description'       => 'Stations',
				'show_in_rest'      => false,
				'default'           => array(),
				'sanitize_callback' => array( static::class, 'sanitize_stations' ),
			)
		);
	}

	/**
	 * Admin Init Function
	 *
	 * @since 4.0.0
	 * @since 6.1.0 The section is on the Stations tab, whatever the option is called, so subclasses share it.
	 */
	public static function admin_init() {
		add_settings_section(
			static::OPTION,
			static::section_title(),
			array( static::class, 'stations_section' ),
			static::SETTINGS_PAGE
		);
		add_settings_field(
			static::OPTION, // id.
			__( 'Stations', 'simple-location' ), // setting title.
			array( static::class, 'stations_callback' ), // display callback.
			static::SETTINGS_PAGE, // settings page.
			static::OPTION, // settings section.
			array()
		);
	}

	/**
	 * Returns the title of the stations' settings section.
	 *
	 * @since 6.1.0
	 *
	 * @return string Title.
	 */
	protected static function section_title() {
		return __( 'Custom Station Data', 'simple-location' );
	}

	/**
	 * Callback for the stations settings.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Describes the current conditions format and the credit.
	 */
	public static function stations_section() {
		esc_html_e(
			'Enter an ID and URL for each station. The URL may contain a query string application key. It must return the station\'s current conditions as JSON, in the format described in the readme. The latitude and longitude are read from the station when it gives them; when this is the weather provider, the nearest station within 10 km of a post is used. The credit is shown with the station\'s weather, linked if you give a link, in place of any credit the station sends.',
			'simple-location'
		);
	}

	/**
	 * Echoes the form for the station settings.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Has an empty row for a new station, and a Remove checkbox for each saved one, instead of buttons.
	 *
	 * @param array $args Arguments.
	 */
	public static function stations_callback( $args ) {
		$stations = static::get_stations();
		echo '<ul id="location-stations">';
		foreach ( $stations as $key => $station ) {
			static::station_inputs( $key, $station );
		}
		// A row for a new station.
		static::station_inputs( count( $stations ) );
		echo '</ul>';
	}

	/**
	 * If the array key exists return it, otherwise return empty string.
	 *
	 * @param array      $array Array.
	 * @param int|string $key Key.
	 * @return mixed The value or empty string.
	 */
	public static function key( $array, $key ) {
		if ( ! is_array( $array ) ) {
			return '';
		}
		if ( array_key_exists( $key, $array ) ) {
			return $array[ $key ];
		}
		return '';
	}

	/**
	 * Echoes an entry of the station form.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Has the credit, its link and, for a saved station, a Remove checkbox.
	 *
	 * @param int   $int   Array key.
	 * @param array $value Station, with the keys in FIELDS. Default empty, for a new station.
	 */
	protected static function station_inputs( $int, $value = array() ) {
		$name   = static::OPTION;
		$labels = array(
			'id'              => __( 'Station ID', 'simple-location' ),
			'url'             => __( 'URL', 'simple-location' ),
			'latitude'        => __( 'Latitude', 'simple-location' ),
			'longitude'       => __( 'Longitude', 'simple-location' ),
			'attribution'     => __( 'Credit', 'simple-location' ),
			'attribution_url' => __( 'Credit Link', 'simple-location' ),
		);
		echo '<li>';
		foreach ( $labels as $field => $label ) {
			$current = self::key( $value, $field );
			printf(
				'<input type="%1$s" name="%2$s[%3$s][%4$s]" value="%5$s" placeholder="%6$s" aria-label="%6$s" />',
				in_array( $field, array( 'url', 'attribution_url' ), true ) ? 'url' : 'text',
				esc_attr( $name ),
				esc_attr( $int ),
				esc_attr( $field ),
				in_array( $field, array( 'url', 'attribution_url' ), true ) ? esc_url( $current ) : esc_attr( $current ),
				esc_attr( $label )
			);
		}
		if ( ! empty( $value ) ) {
			printf(
				' <label><input type="checkbox" name="%1$s[%2$s][remove]" value="1" /> %3$s</label>',
				esc_attr( $name ),
				esc_attr( $int ),
				esc_html__( 'Remove', 'simple-location' )
			);
		}
		echo '</li>';
	}

	/**
	 * Sanitizes the stations setting.
	 *
	 * Rows without an ID or URL, and rows marked to be removed, are dropped.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Stations from the form.
	 * @return array[] Stations, each with the keys in FIELDS.
	 */
	public static function sanitize_stations( $value ) {
		$stations = array();
		foreach ( is_array( $value ) ? $value : array() as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['remove'] ) ) {
				continue;
			}
			$station = array(
				'id'              => trim( sanitize_text_field( (string) self::key( $row, 'id' ) ) ),
				'url'             => esc_url_raw( trim( (string) self::key( $row, 'url' ) ), array( 'http', 'https' ) ),
				'latitude'        => clean_coordinate( self::key( $row, 'latitude' ), 'latitude' ),
				'longitude'       => clean_coordinate( self::key( $row, 'longitude' ), 'longitude' ),
				'attribution'     => trim( sanitize_text_field( (string) self::key( $row, 'attribution' ) ) ),
				'attribution_url' => esc_url_raw( trim( (string) self::key( $row, 'attribution_url' ) ), array( 'http', 'https' ) ),
			);
			if ( '' === $station['id'] || '' === $station['url'] ) {
				continue;
			}
			// A coordinate only counts with the other.
			if ( false === $station['latitude'] || false === $station['longitude'] ) {
				$station['latitude']  = '';
				$station['longitude'] = '';
			}
			$stations[] = $station;
		}
		return $stations;
	}

	/**
	 * Returns the saved stations.
	 *
	 * @since 6.1.0
	 *
	 * @return array[] Stations, each an array with the keys in FIELDS.
	 */
	public static function get_stations() {
		$stations = get_option( static::OPTION );
		return is_array( $stations ) ? array_values( array_filter( $stations, 'is_array' ) ) : array();
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
	 * @since 4.0.0
	 * @since 6.1.0 Conditions for a station ID are cached too, and the nearest station only counts stations with coordinates.
	 * @since 6.1.0 Returns an error instead of using the fallback provider: the station's error when it can't be
	 *              read, `station_stale` when its reading is too old, and `no_results` without a station in range.
	 *
	 * @param int $time Timestamp.
	 * @return array|WP_Error Current Conditions in Array, or an error.
	 */
	public function get_conditions( $time = null ) {
		$datetime = $this->datetime( $time );
		if ( ! $this->supports_time( $time ) ) {
			return $this->get_unsupported_time_conditions( $time );
		}

		$conditions = $this->get_cache();
		if ( $conditions ) {
			return $conditions;
		}

		$return = array();
		if ( ! empty( $this->station_id ) ) {
			$return = $this->get_station_data();
			if ( is_wp_error( $return ) ) {
				return $return;
			}
		} elseif ( $this->latitude && $this->longitude ) {
			$nearest = $this->get_nearest_station();
			if ( $nearest ) {
				$this->station_id = $nearest['id'];
				$return           = $this->get_station_data();
				$this->station_id = null;
				if ( is_wp_error( $return ) ) {
					return $return;
				}
				$return['distance'] = $nearest['distance'];
			}
		}

		if ( empty( $return ) ) {
			return $this->no_results_error();
		}
		if ( isset( $return['timestamp'] ) && is_numeric( $return['timestamp'] ) && static::MAX_AGE > abs( $datetime->getTimestamp() - (int) $return['timestamp'] ) ) {
			$this->set_cache( $return );
			return $return;
		}
		return new WP_Error(
			'station_stale',
			/* translators: %s: Provider name. */
			sprintf( __( 'The %s station has no reading from the last hour.', 'simple-location' ), $this->get_name() )
		);
	}

	/**
	 * Returns whether a station is within range of the location, or a station is set.
	 *
	 * @since 6.1.0
	 *
	 * @return bool Whether to try the provider here.
	 */
	public function covers() {
		if ( ! empty( $this->station_id ) || ! is_numeric( $this->latitude ) || ! is_numeric( $this->longitude ) ) {
			return true;
		}
		return null !== $this->get_nearest_station();
	}

	/**
	 * Returns whether the provider has weather for a time: only current conditions, within MAX_AGE of now.
	 *
	 * @since 6.1.0
	 *
	 * @param int|string|DateTime|null $time Time, or null for now.
	 * @return bool Whether to try the provider for this time.
	 */
	public function supports_time( $time = null ) {
		return static::MAX_AGE >= abs( $this->datetime( $time )->getTimestamp() - time() );
	}

	/**
	 * Returns the nearest station with coordinates within RANGE of the location.
	 *
	 * @since 6.1.0
	 *
	 * @return array|null The station, with its 'distance' in meters, or null if none is in range.
	 */
	protected function get_nearest_station() {
		$nearest = null;
		foreach ( static::get_stations() as $station ) {
			$latitude  = self::key( $station, 'latitude' );
			$longitude = self::key( $station, 'longitude' );
			if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
				continue;
			}
			$distance = round( geo_distance( $this->latitude, $this->longitude, (float) $latitude, (float) $longitude ) );
			if ( $distance < static::RANGE && ( null === $nearest || $distance < $nearest['distance'] ) ) {
				$nearest             = $station;
				$nearest['distance'] = $distance;
			}
		}
		return $nearest;
	}

	/**
	 * Get Station Data.
	 *
	 * Fetches the station's JSON and reads it, learning the station's coordinates when the settings have none. The
	 * station's credit in the settings, if any, replaces the one the station sends.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Reads the current conditions format, version 1, and the station's credit.
	 *
	 * @return array|WP_Error Conditions, or an error if the station isn't set up or its JSON can't be read.
	 */
	public function get_station_data() {
		$stations = static::get_stations();
		foreach ( $stations as $key => $station ) {
			if ( trim( (string) self::key( $station, 'id' ) ) !== (string) $this->station_id ) {
				continue;
			}
			$json = $this->fetch_json( self::key( $station, 'url' ), array() );
			if ( is_wp_error( $json ) ) {
				return $json;
			}
			$return = static::parse_document( $json );
			if ( is_wp_error( $return ) ) {
				return $return;
			}
			if ( isset( $return['latitude'], $return['longitude'] ) && ! is_numeric( self::key( $station, 'latitude' ) ) ) {
				$station['latitude']  = $return['latitude'];
				$station['longitude'] = $return['longitude'];
				$stations[ $key ]     = $station;
				update_option( static::OPTION, $stations );
			}
			if ( '' !== (string) self::key( $station, 'attribution' ) ) {
				$return['attribution']     = $station['attribution'];
				$return['attribution_url'] = self::key( $station, 'attribution_url' );
			}
			if ( empty( $return['attribution_url'] ) ) {
				unset( $return['attribution_url'] );
			}
			return self::filter_weather( $this->extra_data( $return, null ) );
		}
		return new WP_Error( 'station_not_found', __( 'There is no station with that ID', 'simple-location' ), array( 'station_id' => $this->station_id ) );
	}

	/**
	 * Reads a station's JSON: the current conditions format, version 1, or the earlier format.
	 *
	 * @since 6.1.0
	 * @since 6.2.0 An AQI whose `derived.aqi` scale isn't us_epa isn't stored.
	 *
	 * @param mixed $json Decoded JSON.
	 * @return array|WP_Error Conditions in the plugin's units, with 'timestamp', and the station's 'station_id', 'name',
	 *                        'latitude', 'longitude', 'altitude', 'attribution' and 'attribution_url' when it gives
	 *                        them; or an error for another format or a later version.
	 */
	public static function parse_document( $json ) {
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'station_format', __( 'The station did not return a JSON object', 'simple-location' ) );
		}
		if ( ! array_key_exists( 'format', $json ) ) {
			return static::parse_legacy( $json );
		}
		if ( static::FORMAT !== $json['format'] ) {
			/* translators: %s: Format identifier. */
			return new WP_Error( 'station_format', sprintf( __( 'The station uses an unknown format, %s', 'simple-location' ), is_scalar( $json['format'] ) ? (string) $json['format'] : '' ) );
		}
		if ( ! isset( $json['version'] ) || static::FORMAT_VERSION !== (int) $json['version'] ) {
			return new WP_Error( 'station_format_version', __( 'The station uses a version of the current conditions format this plugin can\'t read', 'simple-location' ) );
		}

		$observations = ( isset( $json['observations'] ) && is_array( $json['observations'] ) ) ? $json['observations'] : array();
		$return       = array();
		foreach ( Sloc_Weather_Data::$properties as $key ) {
			if ( in_array( $key, array( 'summary', 'icon', 'code', 'provider', 'attribution', 'attribution_url' ), true ) ) {
				continue;
			}
			if ( isset( $observations[ $key ] ) && is_numeric( $observations[ $key ] ) ) {
				$return[ $key ] = (float) $observations[ $key ];
			}
		}
		// The plugin's AQI is on the US EPA scale; an index on another scale isn't stored as one.
		$derived = ( isset( $json['derived'] ) && is_array( $json['derived'] ) ) ? $json['derived'] : array();
		if ( isset( $derived['aqi'] ) && 'us_epa' !== $derived['aqi'] ) {
			unset( $return['aqi'] );
		}
		if ( isset( $json['units'] ) && 'us' === $json['units'] ) {
			$return = self::imperial_to_metric( $return );
		}
		$return = Sloc_Weather_Data::without_quiet_lightning( $return );
		$return = array_merge( $return, static::parse_condition( $observations ) );
		if ( isset( $json['time'] ) && is_numeric( $json['time'] ) ) {
			$return['timestamp'] = (int) $json['time'];
		}

		$station = ( isset( $json['station'] ) && is_array( $json['station'] ) ) ? $json['station'] : array();
		if ( isset( $station['id'] ) && is_scalar( $station['id'] ) ) {
			$return['station_id'] = sanitize_text_field( (string) $station['id'] );
		}
		if ( isset( $station['name'] ) && is_string( $station['name'] ) ) {
			$return['name'] = sanitize_text_field( $station['name'] );
		}
		$latitude  = clean_coordinate( $station['latitude'] ?? null, 'latitude' );
		$longitude = clean_coordinate( $station['longitude'] ?? null, 'longitude' );
		if ( false !== $latitude && false !== $longitude ) {
			$return['latitude']  = $latitude;
			$return['longitude'] = $longitude;
		}
		if ( isset( $station['altitude'] ) && is_numeric( $station['altitude'] ) ) {
			$return['altitude'] = (float) $station['altitude'];
		}
		$credit = ( isset( $station['attribution'] ) && is_array( $station['attribution'] ) ) ? $station['attribution'] : array();
		if ( isset( $credit['text'] ) && is_string( $credit['text'] ) && '' !== trim( $credit['text'] ) ) {
			$return['attribution'] = sanitize_text_field( $credit['text'] );
			if ( isset( $credit['url'] ) && is_string( $credit['url'] ) ) {
				$return['attribution_url'] = esc_url_raw( $credit['url'], array( 'http', 'https' ) );
			}
		}
		return $return;
	}

	/**
	 * Reads the condition of a station: its code, when it is one the plugin knows, otherwise its summary.
	 *
	 * @since 6.1.0
	 *
	 * @param array $values Values with 'code' and 'summary'.
	 * @return array With 'code' and 'summary' when they are known.
	 */
	protected static function parse_condition( $values ) {
		$return = array();
		if ( isset( $values['code'] ) && is_numeric( $values['code'] ) && '' !== self::weather_condition_codes( (int) $values['code'] ) ) {
			$return['code'] = (int) $values['code'];
		}
		if ( isset( $values['summary'] ) && is_string( $values['summary'] ) && '' !== trim( $values['summary'] ) ) {
			$return['summary'] = sanitize_text_field( $values['summary'] );
			if ( ! isset( $return['code'] ) ) {
				$return['code'] = self::code_map( $return['summary'] );
			}
		}
		return $return;
	}

	/**
	 * Reads the earlier format: the plugin's weather keys at the top level, in its units, with wind as an object.
	 *
	 * @since 6.1.0
	 * @since 6.1.0 A wind object without some of its values no longer warns.
	 *
	 * @param array $json Decoded JSON.
	 * @return array Conditions.
	 */
	protected static function parse_legacy( $json ) {
		$return = array();
		foreach ( $json as $key => $value ) {
			// Values a station doesn't have are sent as N/A.
			if ( is_string( $value ) && 'N/A' === trim( $value ) ) {
				continue;
			}
			if ( 'wind' === $key ) {
				foreach ( array( 'speed', 'degree', 'gust' ) as $part ) {
					if ( is_array( $value ) && isset( $value[ $part ] ) && is_numeric( $value[ $part ] ) ) {
						$return[ 'wind' . $part ] = $value[ $part ];
					}
				}
				continue;
			}
			$return[ $key ] = $value;
		}
		unset( $return['code'] );
		return array_merge( Sloc_Weather_Data::without_quiet_lightning( $return ), static::parse_condition( $json ) );
	}

	/**
	 * Returns the condition code for a station's summary.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Static, so documents can be read without a provider.
	 *
	 * @param string $id Summary, such as "Partly Cloudy".
	 * @return int|string Condition code, or an empty string for an unknown summary.
	 */
	protected static function code_map( $id ) {
		switch ( $id ) {
			case 'Sunny':
			case 'Clear':
				return 800;
			case 'Mostly Sunny':
				return 801;
			case 'Cloudy':
				return 802;
			case 'A few clouds':
			case 'Few clouds':
				return 801;
			case 'Partly Cloudy':
				return 802;
			case 'Mostly Cloudy':
				return 803;
			case 'Overcast':
				return 804;
			case 'Fair/clear and windy':
				return 400;
			case 'A few clouds and windy':
				return 801;
			case 'Partly cloudy and windy':
				return 802;
			case 'Mostly cloudy and windy':
				return 803;
			case 'Overcast and windy':
				return 804;
			case 'Snow':
				return 601;
			case 'Rain/snow':
				return 616;
			case 'Rain/sleet':
			case 'Rain/sleet':
				return 620;
			case 'Freezing rain':
			case 'Rain/freezing rain':
			case 'Freezing rain/snow':
				return 511;
			case 'Sleet':
				return 611;
			case 'Rain':
			case 'Rain showers (high cloud cover)':
			case 'Rain showers (low cloud cover)':
				return 521;
			case 'Thunderstorm (high cloud cover)':
			case 'Thunderstorm (medium cloud cover)':
			case 'Thunderstorm (low cloud cover)':
				return 211;
			case 'Tornado':
				return 781;
			case 'Hurricane conditions':
				return 782;
			case 'Tropical storm conditions':
				return 522;
			case 'Dust':
				return 761;
			case 'Smoke':
				return 711;
			case 'Haze':
				return 721;
			case 'Hot':
				return '';
			case 'Cold':
				return '';
			case 'Blizzard':
				return 622;
			case 'Fog/mist':
				return 741;
			default:
				return '';
		}
	}
}
