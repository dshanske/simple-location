<?php
/**
 * WeeWX Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weather Provider for WeeWX stations, reading the JSON the weewx-current-json extension writes.
 *
 * Only each station's file URL is entered. The station's ID, name and coordinates are read from the file when the
 * settings are saved, and the file must be in the current conditions format.
 *
 * @since 6.1.0
 * @since 6.2.0 No longer in beta.
 * @since 6.3.0 Gives the weather for the last day, or week, from the station's history.
 */
class Weather_Provider_Weewx extends Weather_Provider_Station {

	/**
	 * Name of the setting holding the stations.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const OPTION = 'sloc_weewx_stations';

	/**
	 * Furthest back, in seconds, that a station's history files can give weather for.
	 *
	 * The current conditions file holds the last day; history-week.json, when the station writes it, the last 7 days.
	 *
	 * @since 6.3.0
	 * @var int
	 */
	const HISTORY_MAX_AGE = WEEK_IN_SECONDS;

	/**
	 * Seconds a station's current.json is kept for past times, so several posts don't fetch it again.
	 *
	 * @since 6.3.0
	 * @var int
	 */
	const DOCUMENT_CACHE = 5 * MINUTE_IN_SECONDS;

	/**
	 * Seconds a station's history-week.json is kept; the extension rewrites it at most once an hour.
	 *
	 * @since 6.3.0
	 * @var int
	 */
	const ARCHIVE_CACHE = HOUR_IN_SECONDS;

	/**
	 * History readings that are totals per interval, which are added up over the hour before the time.
	 *
	 * @since 6.3.0
	 * @var string[]
	 */
	const HISTORY_TOTALS = array( 'rain', 'snow', 'lightning_strikes' );

	/**
	 * Weather properties that aren't read from the history: the history has no wind direction, condition, cloud
	 * cover or AQI, and the rest aren't readings.
	 *
	 * @since 6.3.0
	 * @var string[]
	 */
	const HISTORY_SKIPPED = array( 'winddegree', 'code', 'cloudiness', 'aqi', 'summary', 'icon', 'provider', 'supplement', 'derived_source', 'attribution', 'attribution_url' );

	/**
	 * Constructor
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->name        = __( 'WeeWX', 'simple-location' );
		$this->url         = 'https://github.com/dshanske/weewx-current-json';
		$this->description = __( 'Weather from your WeeWX stations, with the weewx-current-json extension installed. When it is the weather provider, the nearest station within 10 km is used.', 'simple-location' );
		$this->slug        = 'weewx';
	}

	/**
	 * Returns the title of the stations' settings section.
	 *
	 * @since 6.1.0
	 *
	 * @return string Title.
	 */
	protected static function section_title() {
		return __( 'WeeWX Stations', 'simple-location' );
	}

	/**
	 * Describes the WeeWX stations' settings.
	 *
	 * @since 6.1.0
	 */
	public static function stations_section() {
		esc_html_e(
			'Enter the URL of the current.json file each WeeWX station writes with the weewx-current-json extension, such as https://weather.example.com/current.json. The station\'s ID, name and location are read from the file when you save. The credit is shown with the station\'s weather, in place of any credit the station sends.',
			'simple-location'
		);
	}

	/**
	 * Echoes an entry of the WeeWX stations form: the file's URL and the credit, with what was read from the file.
	 *
	 * @since 6.1.0
	 *
	 * @param int   $int   Array key.
	 * @param array $value Station. Default empty, for a new station.
	 */
	protected static function station_inputs( $int, $value = array() ) {
		$name   = static::OPTION;
		$labels = array(
			'url'             => __( 'URL of current.json', 'simple-location' ),
			'attribution'     => __( 'Credit', 'simple-location' ),
			'attribution_url' => __( 'Credit Link', 'simple-location' ),
		);
		echo '<li>';
		foreach ( $labels as $field => $label ) {
			$current = self::key( $value, $field );
			printf(
				'<input type="%1$s" name="%2$s[%3$s][%4$s]" value="%5$s" placeholder="%6$s" aria-label="%6$s" />',
				'attribution' === $field ? 'text' : 'url',
				esc_attr( $name ),
				esc_attr( $int ),
				esc_attr( $field ),
				'attribution' === $field ? esc_attr( $current ) : esc_url( $current ),
				esc_attr( $label )
			);
		}
		if ( ! empty( $value ) ) {
			$read = '' === (string) self::key( $value, 'name' ) ? self::key( $value, 'id' ) : sprintf( '%1$s (%2$s)', self::key( $value, 'name' ), self::key( $value, 'id' ) );
			printf( ' <span class="description">%s</span>', esc_html( $read ) );
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
	 * Sanitizes the WeeWX stations setting, reading each new station's file for its ID, name and location.
	 *
	 * A station saved from the form has no ID, so its file is read; a station saved by the plugin, such as with
	 * learned coordinates, keeps what it has. A file that can't be read leaves a settings error, and the station is
	 * kept with an ID from its URL.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Stations.
	 * @return array[] Stations, with the keys in FIELDS and 'name'.
	 */
	public static function sanitize_stations( $value ) {
		$stations = array();
		$ids      = array();
		foreach ( is_array( $value ) ? $value : array() as $index => $row ) {
			if ( ! is_array( $row ) || ! empty( $row['remove'] ) ) {
				continue;
			}
			$url = esc_url_raw( trim( (string) self::key( $row, 'url' ) ), array( 'http', 'https' ) );
			if ( '' === $url ) {
				continue;
			}
			$station = array(
				'id'              => trim( sanitize_text_field( (string) self::key( $row, 'id' ) ) ),
				'url'             => $url,
				'name'            => sanitize_text_field( (string) self::key( $row, 'name' ) ),
				'latitude'        => clean_coordinate( self::key( $row, 'latitude' ), 'latitude' ),
				'longitude'       => clean_coordinate( self::key( $row, 'longitude' ), 'longitude' ),
				'attribution'     => trim( sanitize_text_field( (string) self::key( $row, 'attribution' ) ) ),
				'attribution_url' => esc_url_raw( trim( (string) self::key( $row, 'attribution_url' ) ), array( 'http', 'https' ) ),
			);
			if ( '' === $station['id'] ) {
				$station = static::read_station( $station, $index );
			}
			if ( false === $station['latitude'] || false === $station['longitude'] ) {
				$station['latitude']  = '';
				$station['longitude'] = '';
			}
			// IDs must be unique, for the widgets.
			$id     = $station['id'];
			$suffix = 2;
			while ( in_array( $station['id'], $ids, true ) ) {
				$station['id'] = $id . '-' . $suffix;
				++$suffix;
			}
			$ids[]      = $station['id'];
			$stations[] = $station;
		}
		return $stations;
	}

	/**
	 * Reads a station's ID, name and location from its file.
	 *
	 * @since 6.1.0
	 *
	 * @param array $station Station, with its URL.
	 * @param int   $index   Row of the station in the form, for the error.
	 * @return array Station.
	 */
	protected static function read_station( $station, $index ) {
		$provider = new self();
		$json     = $provider->fetch_json( $station['url'], array() );
		$document = is_wp_error( $json ) ? $json : static::parse_document( $json );
		if ( is_wp_error( $document ) ) {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error(
					static::OPTION,
					'sloc_weewx_station_' . $index,
					/* translators: 1: URL 2: Error message. */
					sprintf( __( 'Could not read the WeeWX station at %1$s: %2$s', 'simple-location' ), $station['url'], $document->get_error_message() )
				);
			}
			$station['id'] = sanitize_title( (string) wp_parse_url( $station['url'], PHP_URL_HOST ) );
			return $station;
		}
		$station['id']        = $document['station_id'] ?? sanitize_title( (string) wp_parse_url( $station['url'], PHP_URL_HOST ) );
		$station['name']      = $document['name'] ?? '';
		$station['latitude']  = $document['latitude'] ?? '';
		$station['longitude'] = $document['longitude'] ?? '';
		return $station;
	}

	/**
	 * Reads a WeeWX station's JSON, which must be in the current conditions format.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $json Decoded JSON.
	 * @return array|WP_Error Conditions, or an error. See Weather_Provider_Station::parse_document().
	 */
	public static function parse_document( $json ) {
		if ( ! is_array( $json ) || ! array_key_exists( 'format', $json ) ) {
			return new WP_Error( 'station_format', __( 'This is not a weewx-current-json file', 'simple-location' ) );
		}
		return parent::parse_document( $json );
	}

	/**
	 * Returns whether the provider has weather for a time: current conditions, or up to HISTORY_MAX_AGE ago from the
	 * station's history files.
	 *
	 * A station without a week's history gives nothing for times older than its current.json holds, and the next
	 * provider is tried.
	 *
	 * @since 6.3.0
	 *
	 * @param int|string|DateTime|null $time Time, or null for now.
	 * @return bool Whether to try the provider for this time.
	 */
	public function supports_time( $time = null ) {
		$age = time() - $this->datetime( $time )->getTimestamp();
		return -static::MAX_AGE <= $age && static::HISTORY_MAX_AGE >= $age;
	}

	/**
	 * Returns the conditions at a time: the current conditions, or, for a past time, the station's readings then.
	 *
	 * @since 6.3.0 Gives the weather for past times from the station's history; see get_past_conditions().
	 *
	 * @param int|string|DateTime|null $time Time, or null for now.
	 * @return array|WP_Error Conditions, an empty array without readings for a past time, or an error.
	 */
	public function get_conditions( $time = null ) {
		$timestamp = $this->datetime( $time )->getTimestamp();
		if ( static::MAX_AGE >= abs( $timestamp - time() ) || ! $this->supports_time( $time ) ) {
			return parent::get_conditions( $time );
		}
		return $this->get_past_conditions( $timestamp );
	}

	/**
	 * Returns a station's readings at a past time, from the history in its current.json or its history-week.json.
	 *
	 * The station is the one set, or the nearest. The readings are those of the history interval the time is in; see
	 * history_conditions(). There is no wind direction, condition, cloud cover or AQI, which the history doesn't
	 * have; the condition can be estimated from the sunlight, as for current conditions, or filled in by Open-Meteo.
	 *
	 * @since 6.3.0
	 *
	 * @param int $timestamp Time, in Unix seconds.
	 * @return array|WP_Error Conditions, an empty array when the station has no readings then, or an error.
	 */
	protected function get_past_conditions( $timestamp ) {
		$cached = $this->get_cache( $timestamp );
		if ( $cached ) {
			return $cached;
		}

		$station = $this->find_station();
		if ( ! $station ) {
			return $this->no_results_error();
		}
		$url      = (string) self::key( $station, 'url' );
		$document = $this->get_document( $url, static::DOCUMENT_CACHE );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		$info = static::parse_document( $document );
		if ( is_wp_error( $info ) ) {
			return $info;
		}

		$units      = $document['units'] ?? 'metric';
		$conditions = static::history_conditions( $document['history'] ?? null, $timestamp, $units );
		$week       = $document['archives']['week'] ?? null;
		if ( null === $conditions && is_string( $week ) && '' !== $week ) {
			$archive = $this->get_document( WP_Http::make_absolute_url( $week, $url ), static::ARCHIVE_CACHE );
			if ( is_wp_error( $archive ) ) {
				return $archive;
			}
			if ( static::ARCHIVE_FORMAT !== ( $archive['format'] ?? null ) || static::FORMAT_VERSION !== (int) ( $archive['version'] ?? 0 ) ) {
				return new WP_Error( 'station_format', __( 'The station\'s week of history is not in a format this plugin can read', 'simple-location' ) );
			}
			$conditions = static::history_conditions( $archive['history'] ?? null, $timestamp, $archive['units'] ?? 'metric' );
		}
		if ( empty( $conditions ) ) {
			return array();
		}

		$return = array_merge( wp_array_slice_assoc( $info, array( 'station_id', 'name', 'latitude', 'longitude', 'altitude', 'attribution', 'attribution_url' ) ), $conditions );
		if ( '' !== (string) self::key( $station, 'attribution' ) ) {
			$return['attribution']     = $station['attribution'];
			$return['attribution_url'] = self::key( $station, 'attribution_url' );
		}
		if ( empty( $return['attribution_url'] ) ) {
			unset( $return['attribution_url'] );
		}
		if ( isset( $station['distance'] ) ) {
			$return['distance'] = $station['distance'];
		}
		$return = self::filter_weather( $this->extra_data( $return, $timestamp ) );
		$this->set_cache( $return, $timestamp );
		return $return;
	}

	/**
	 * Returns the station to use: the one set, or the nearest within RANGE of the location.
	 *
	 * @since 6.3.0
	 *
	 * @return array|null Station, with 'distance' when it is the nearest, or null.
	 */
	protected function find_station() {
		if ( ! empty( $this->station_id ) ) {
			foreach ( static::get_stations() as $station ) {
				if ( trim( (string) self::key( $station, 'id' ) ) === (string) $this->station_id ) {
					return $station;
				}
			}
			return null;
		}
		if ( is_numeric( $this->latitude ) && is_numeric( $this->longitude ) ) {
			return $this->get_nearest_station();
		}
		return null;
	}

	/**
	 * Fetches a station's file, keeping it for a time so several lookups don't fetch it again.
	 *
	 * @since 6.3.0
	 *
	 * @param string $url   File URL.
	 * @param int    $cache Seconds to keep it.
	 * @return array|WP_Error Decoded file, or an error.
	 */
	protected function get_document( $url, $cache ) {
		$key      = 'sloc_weewx_' . md5( $url );
		$document = get_transient( $key );
		if ( is_array( $document ) ) {
			return $document;
		}
		$document = $this->fetch_json( $url, array() );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		if ( ! is_array( $document ) ) {
			return new WP_Error( 'station_format', __( 'The station did not return a JSON object', 'simple-location' ) );
		}
		set_transient( $key, $document, $cache );
		return $document;
	}

	/**
	 * Returns the readings of a history at a time: those of the interval the time is in.
	 *
	 * The history has `start`, when its first interval begins, `interval`, the length of each in seconds, and a list
	 * for each reading, oldest first, with null for an interval without data. Rain, snow and lightning strikes are
	 * totals per interval, so they are added up over the intervals in the hour up to the time, which is what the
	 * plugin's mean. The timestamp is the end of the interval.
	 *
	 * @since 6.3.0
	 *
	 * @param mixed  $history   History.
	 * @param int    $timestamp Time, in Unix seconds.
	 * @param string $units     Optional. Units of the history: metric, or us. Default metric.
	 * @return array|null Readings in the plugin's units, with 'timestamp'; an empty array when the history covers the
	 *                    time without readings for it; or null when it doesn't cover the time.
	 */
	public static function history_conditions( $history, $timestamp, $units = 'metric' ) {
		if ( ! is_array( $history ) || ! is_numeric( $history['start'] ?? null ) || ! is_numeric( $history['interval'] ?? null ) || 0 >= (int) $history['interval'] ) {
			return null;
		}
		$start    = (int) $history['start'];
		$interval = (int) $history['interval'];
		$lists    = array_filter( $history, 'is_array' );
		$count    = empty( $lists ) ? 0 : max( array_map( 'count', $lists ) );
		if ( $timestamp < $start ) {
			return null;
		}
		$index = intdiv( $timestamp - $start, $interval );
		if ( $index >= $count ) {
			return null;
		}

		$readings = array();
		foreach ( $lists as $key => $list ) {
			if ( ! in_array( $key, Sloc_Weather_Data::$properties, true ) || in_array( $key, static::HISTORY_SKIPPED, true ) ) {
				continue;
			}
			$list = array_values( $list );
			if ( in_array( $key, static::HISTORY_TOTALS, true ) ) {
				$total = null;
				$first = max( 0, $index - max( 1, (int) round( HOUR_IN_SECONDS / $interval ) ) + 1 );
				for ( $i = $first; $i <= $index; $i++ ) {
					if ( is_numeric( $list[ $i ] ?? null ) ) {
						$total = (float) $total + (float) $list[ $i ];
					}
				}
				if ( null !== $total ) {
					$readings[ $key ] = round( $total, 2 );
				}
			} elseif ( is_numeric( $list[ $index ] ?? null ) ) {
				$readings[ $key ] = (float) $list[ $index ];
			}
		}
		if ( empty( $readings ) ) {
			return array();
		}
		if ( 'us' === $units ) {
			$readings = self::imperial_to_metric( $readings );
		}
		$readings              = Sloc_Weather_Data::without_quiet_lightning( $readings );
		$readings['timestamp'] = $start + ( $index + 1 ) * $interval;
		return $readings;
	}
}
