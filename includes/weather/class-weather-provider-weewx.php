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
}
