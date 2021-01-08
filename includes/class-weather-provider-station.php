<?php

class Weather_Provider_Station extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name   = __( 'Custom Station Provider', 'simple-location' );
		$this->slug   = 'station';
		$this->region = false;
		$option       = get_option( 'sloc_weather_provider' );
		// if ( 'station' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		// }
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_stations', // option group.
			'sloc_stations', // option name.
			array(
				'type'         => 'string',
				'description'  => 'Stations',
				'show_in_rest' => false,
				'default'      => array(),
			)
		);
	}

	public static function admin_init() {
		add_settings_section(
			'sloc_stations',
			__( 'Custom Station Data', 'simple-location' ),
			array( static::class, 'stations_section' ),
			'sloc_stations'
		);
		add_settings_field(
			'sloc_stations', // id.
			__( 'Stations', 'simple-location' ), // setting title.
			array( static::class, 'stations_callback' ), // display callback.
			'sloc_stations', // option group.
			'sloc_stations', // settings section.
			array()
		);
	}

	/**
	 * Callback for the stations settings.
	 *
	 * @since 4.0.0
	 */
	public static function stations_section() {
		esc_html_e(
			'Enter Station ID, URL, latitude, and longitude for each station. The URL may contain a query string application key. When using the custom station provider as your default weather provider, it will look for a station within 10km of your current location. Provider expects a json return with the properties outlined in the code.',
			'simple-location'
		);
	}

	/**
	 * Echoes the form for the station settings.
	 *
	 * @param array $args Arguments.
	 */
	public static function stations_callback( $args ) {
		$name   = 'sloc_stations';
		$custom = get_option( $name );
		foreach ( $custom as $key => $value ) {
			$custom[ $key ] = array_filter( $value );
		}
		$custom = array_filter( $custom );
		esc_html_e( 'Enter ID and URL for this Station', 'simple-location' );
		printf( '<ul id="location-stations">' );
		if ( empty( $custom ) ) {
			self::station_inputs( '0' );

		} else {
			foreach ( $custom as $key => $value ) {
				self::station_inputs( $key, $value );
			}
		}
		printf( '</ul>' );
		printf( '<span class="button button-primary" id="add-location-stations-button">%s</span>', esc_html__( 'Add', 'simple-location' ) );
		printf( '<span class="button button-secondary" id="delete-location-stations-button">%s</span>', esc_html__( 'Remove', 'simple-location' ) );
	}

	/**
	 * If the array key exists return it, otherwise return empty string.
	 *
	 * @param array      $array Array.
	 * @param int|string $key Key.
	 * @return mixed The value or empty string.
	 */
	public static function key( $array, $key ) {
		if ( array_key_exists( $key, $array ) ) {
			return $array[ $key ];
		}
		return '';
	}

	/**
	 * Echoes an entry of the station form.
	 *
	 * @param int   $int Array key.
	 * @param array $value {
	 *  The fields of the zone.
	 *  @type string $name Zone Name.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type foat $radius Radius.
	 * }
	 */
	private static function station_inputs( $int, $value = '' ) {
		$output = '<input type="text" name="%1$s[%2$s][%3$s]" id="%4$s" value="%5$s" placeholder="%6$s" />';
		$name   = 'sloc_stations';
		echo '<li>';
		// phpcs:disable
		printf( $output, $name, $int, 'id', esc_attr( $name ), esc_attr( self::key( $value, 'id' ) ), esc_html__( 'Station ID', 'simple-location' ) );
		printf( $output, $name, $int, 'url', esc_attr( $name ), esc_url( self::key( $value, 'url' ) ), esc_html__( 'URL', 'simple-location' ) );
		printf( $output, $name, $int, 'latitude', esc_attr( $name ), esc_attr( self::key( $value, 'latitude' ) ), esc_html__( 'Latitude', 'simple-location' ) );
		printf( $output, $name, $int, 'longitude', esc_attr( $name ), esc_attr( self::key( $value, 'longitude' ) ), esc_html__( 'Longitude', 'simple-location' ) );
		// phpcs:enable
		echo '</li>';
	}

	public function set( $lat, $lng = null, $alt = null ) {
		if ( ! $lng && is_array( $lat ) ) {
			if ( isset( $lat['station_id'] ) ) {
				$this->station_id = trim( $lat['station_id'] );
			}
		}
			parent::set( $lat, $lng, $alt );
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
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		$return = array();

		if ( ! empty ( $time ) ) {
			$datetime = $this->datetime( $time );
			$abs = abs( $datetime->getTimestamp() - time() );
			if ( 3600 < $abs ) {
				return self::get_fallback_conditions( $time );
			}
		}

		if ( ! empty( $this->station_id ) ) {
			return self::get_station_data();
		}
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}

			$sitelist = get_option( 'sloc_stations' );
			foreach ( $sitelist as $key => $value ) {
				$sitelist[ $key ]['distance'] = round( WP_Geo_Data::gc_distance( $this->latitude, $this->longitude, $value['latitude'], $value['longitude'] ) );
			}
			usort(
				$sitelist,
				function( $a, $b ) {
					return $a['distance'] > $b['distance'];
				}
			);
			if ( 10000 > $sitelist[0]['distance'] ) {
				$this->station_id   = $sitelist[0]['id'];
				$return             = self::get_station_data();
				$return['distance'] = $sitelist[0]['distance'];
				unset( $this->station_id );
				$this->set_cache( $return );
				return $return;
			}
		}
		return self::get_fallback_conditions( $time );
	}


	/*
	 * Get Station Data.
	 *
	 * The JSON from the API return must match the current conditions return used by the plugin and specified in the parent class.
	 *
	 */
	public function get_station_data() {
		$return   = array();
		$stations = get_option( 'sloc_stations' );
		$endpoint = null;
		foreach ( $stations as $key => $station ) {
			if ( $this->station_id === trim( $station['id'] ) ) {
				$return = $this->fetch_json( $station['url'], array() );
				if ( ! is_wp_error( $return ) ) {
					if ( array_key_exists( 'latitude', $return ) && array_key_exists( 'longitude', $return ) && empty( $station['latitude'] ) && empty( $station['longitude'] ) ) {
						$station['latitude']  = $return['latitude'];
						$station['longitude'] = $return['longitude'];
						$stations[ $key ]     = $station;
						update_option( 'sloc_stations', $stations );
					}
					// Eliminate not applicable values and in select categories hide empty values.
					foreach ( $return as $k => $prop ) {
						if ( is_string( $prop ) && 'N/A' === trim( $prop ) ) {
							unset( $return[ $k ] );
						}
						if ( in_array( $k, array( 'rain', 'snow', 'pm1_0', 'pm10_0', 'pm2_5' ) ) && 0 === (int) $prop ) {
							unset( $return[ $k ] );
						}
					}

					if ( isset( $return['summary'] ) ) {
						$return['icon'] = self::icon_map( $return['summary'] );
					}
					$return = array_filter( $this->extra_data( $return, $time ) );
					break;
				}
			}
		}

		return $return;
	}

	/**
	 * Return array of station data.
	 *
	 * @param string $id Weather type ID.
	 * @return string Icon ID.
	 */
	private function icon_map( $id ) {
		switch ( $id ) {
			case 'Sunny':
			case 'Mostly Sunny':
				return 'wi-day-sunny';
			case 'Cloudy':
				return 'wi-cloudy';
			case 'A few clouds':
			case 'Few clouds':
				return 'wi-cloud';
			case 'Partly Cloudy':
				return 'wi-day-cloudy';
			case 'Mostly Cloudy':
				return 'wi-cloudy';
			case 'Overcast':
				return 'wi-cloudy';
			case 'Fair/clear and windy':
				return 'wi-windy';
			case 'A few clouds and windy':
				return 'wi-cloudy-windy';
			case 'Partly cloudy and windy':
				return 'wi-cloudy-windy';
			case 'Mostly cloudy and windy':
				return 'wi-cloudy-windy';
			case 'Overcast and windy':
				return 'wi-cloudy-windy';
			case 'Snow':
				return 'wi-snow';
			case 'Rain/snow':
				return 'wi-snow';
			case 'Rain/sleet':
			case 'Rain/sleet':
			case 'Freezing rain':
			case 'Rain/freezing rain':
			case 'Freezing rain/snow':
			case 'Sleet':
				return 'wi-sleet';
			case 'Rain':
			case 'Rain showers (high cloud cover)':
			case 'Rain showers (low cloud cover)':
				return 'wi-rain';
			case 'Thunderstorm (high cloud cover)':
			case 'Thunderstorm (medium cloud cover)':
			case 'Thunderstorm (low cloud cover)':
				return 'wi-thunderstorm';
			case 'Tornado':
				return 'wi-tornado';
			case 'Hurricane conditions':
				return 'wi-hurricane';
			case 'Tropical storm conditions':
				return 'wi-storm-showers';
			case 'Dust':
				return 'wi-dust';
			case 'Smoke':
				return 'wi-smoke';
			case 'Haze':
				return 'wi-day-haze';
			case 'Hot':
				return 'wi-hot';
			case 'Cold':
			case 'Blizzard':
				return 'wi-snow';
			case 'Fog/mist':
				return 'wi-fog';
			default:
				return '';
		}
	}

}

register_sloc_provider( new Weather_Provider_Station() );
