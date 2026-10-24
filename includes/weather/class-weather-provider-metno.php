<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weather Provider using MET Norway's Locationforecast API, https://api.met.no/weatherapi/locationforecast/2.0/documentation.
 *
 * MET Norway, the Norwegian Meteorological Institute, forecasts the weather anywhere in the world, and needs no key.
 * Current conditions are the forecast for the hour nearest now. Its terms ask each site to name itself and a way to
 * contact it in the User-Agent, to send at most four decimals of coordinates, and to credit MET Norway.
 *
 * @since 6.1.0
 */
class Weather_Provider_Metno extends Weather_Provider {

	/**
	 * Locationforecast endpoint, with the complete set of variables.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const ENDPOINT = 'https://api.met.no/weatherapi/locationforecast/2.0/complete';

	/**
	 * Constructor
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Weather_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'MET Norway', 'simple-location' );
		$this->url         = 'https://api.met.no/';
		$this->description = __( 'The Norwegian Meteorological Institute forecasts the weather anywhere in the world, and needs no key. Current conditions are its forecast for this hour. Data from MET Norway is licensed CC BY 4.0.', 'simple-location' );
		$this->slug        = 'metno';
		parent::__construct( $args );
	}

	/**
	 * Does This Provider Offer Station Data.
	 *
	 * @since 6.1.0
	 *
	 * @return boolean False, as MET Norway's weather is forecast for a location.
	 */
	public function is_station() {
		return false;
	}

	/**
	 * Returns whether the provider has weather for a time: only current weather, within an hour of now.
	 *
	 * @since 6.1.0
	 *
	 * @param int|string|DateTime|null $time Time, or null for now.
	 * @return bool Whether to try the provider for this time.
	 */
	public function supports_time( $time = null ) {
		return $this->is_current( $time );
	}

	/**
	 * Returns the User-Agent MET Norway asks for: the plugin, and the site as the way to contact its owner.
	 *
	 * @since 6.1.0
	 *
	 * @return string User-Agent.
	 */
	public static function user_agent() {
		return sprintf( 'Simple Location for WordPress (%1$s)', home_url( '/' ) );
	}

	/**
	 * Return array of current conditions.
	 *
	 * @since 6.1.0
	 *
	 * @param string|int|DateTime|null $time Time. Optional. Only now, or within an hour of it.
	 * @return array|WP_Error Conditions, or an error.
	 */
	public function get_conditions( $time = null ) {
		if ( ! $this->supports_time( $time ) ) {
			return $this->get_unsupported_time_conditions( $time );
		}
		if ( empty( $this->latitude ) || empty( $this->longitude ) ) {
			return array();
		}
		$conditions = $this->get_cache();
		if ( $conditions ) {
			return $conditions;
		}

		$args = array(
			'lat' => round( (float) $this->latitude, 4 ),
			'lon' => round( (float) $this->longitude, 4 ),
		);
		if ( is_numeric( $this->altitude ) ) {
			$args['altitude'] = (int) round( (float) $this->altitude );
		}
		$json = $this->fetch_json( self::ENDPOINT, $args, array( 'User-Agent' => self::user_agent() ) );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$entry = self::nearest_entry( $json, time() );
		if ( ! isset( $entry['data']['instant']['details'] ) ) {
			return $this->no_results_error();
		}

		$return = $this->convert_data( $entry['data'] );
		$return = $this->extra_data( $return );
		$this->set_cache( $return );

		if ( WP_DEBUG ) {
			$return['raw'] = $entry;
		}
		return $return;
	}

	/**
	 * Returns the forecast entry nearest a time.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $json      Response.
	 * @param int   $timestamp Time.
	 * @return array|null Entry, or null without one.
	 */
	protected static function nearest_entry( $json, $timestamp ) {
		$series = $json['properties']['timeseries'] ?? null;
		if ( ! is_array( $series ) ) {
			return null;
		}
		$nearest  = null;
		$distance = PHP_INT_MAX;
		foreach ( $series as $entry ) {
			$time = is_array( $entry ) ? strtotime( (string) ( $entry['time'] ?? '' ) ) : false;
			if ( false !== $time && abs( $time - $timestamp ) < $distance ) {
				$nearest  = $entry;
				$distance = abs( $time - $timestamp );
			}
		}
		return $nearest;
	}

	/**
	 * Convert Data into common format
	 *
	 * MET Norway gives the values at the hour, and the precipitation and weather for the hour after it.
	 *
	 * @since 6.1.0
	 *
	 * @param array $data An entry's data: 'instant', and 'next_1_hours' if the forecast has it.
	 * @return array Conditions.
	 */
	public function convert_data( $data ) {
		$details = $data['instant']['details'] ?? array();
		$return  = array(
			'temperature' => self::round_value( $details['air_temperature'] ?? null, 1 ),
			'dewpoint'    => self::round_value( $details['dew_point_temperature'] ?? null, 1 ),
			'humidity'    => self::round_value( $details['relative_humidity'] ?? null, 1 ),
			'pressure'    => self::round_value( $details['air_pressure_at_sea_level'] ?? null, 1 ),
			'cloudiness'  => self::round_value( $details['cloud_area_fraction'] ?? null ),
			'windspeed'   => self::round_value( $details['wind_speed'] ?? null, 1 ),
			'windgust'    => self::round_value( $details['wind_speed_of_gust'] ?? null, 1 ),
			'winddegree'  => self::round_value( $details['wind_from_direction'] ?? null, 1 ),
			'uv'          => self::round_value( $details['ultraviolet_index_clear_sky'] ?? null, 1 ),
		);

		$symbol = self::symbol( $data['next_1_hours']['summary']['symbol_code'] ?? '' );
		if ( '' !== $symbol ) {
			$return['code']    = self::code_map( $symbol );
			$return['summary'] = self::symbol_summary( $symbol );
		}
		$precipitation = self::round_value( $data['next_1_hours']['details']['precipitation_amount'] ?? null, 2 );
		if ( null !== $precipitation ) {
			// MET Norway doesn't divide precipitation into rain and snow, so it is whichever the weather is.
			$return[ ( false !== strpos( $symbol, 'snow' ) ) ? 'snow' : 'rain' ] = $precipitation;
		}

		return self::filter_weather( $return );
	}

	/**
	 * Returns a weather symbol without the time of day, such as 'clearsky' for 'clearsky_night'.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $code Symbol code.
	 * @return string Symbol.
	 */
	public static function symbol( $code ) {
		return is_string( $code ) ? (string) preg_replace( '/_(day|night|polartwilight)$/', '', $code ) : '';
	}

	/**
	 * Returns the descriptions of MET Norway's weather symbols.
	 *
	 * The symbols lightssleetshowersandthunder and lightssnowshowersandthunder are spelled as MET Norway spells them.
	 *
	 * @since 6.1.0
	 *
	 * @return array Descriptions keyed by symbol.
	 */
	protected static function symbols() {
		return array(
			'clearsky'                     => __( 'Clear sky', 'simple-location' ),
			'fair'                         => __( 'Fair', 'simple-location' ),
			'partlycloudy'                 => __( 'Partly cloudy', 'simple-location' ),
			'cloudy'                       => __( 'Cloudy', 'simple-location' ),
			'fog'                          => __( 'Fog', 'simple-location' ),
			'lightrain'                    => __( 'Light rain', 'simple-location' ),
			'rain'                         => __( 'Rain', 'simple-location' ),
			'heavyrain'                    => __( 'Heavy rain', 'simple-location' ),
			'lightrainshowers'             => __( 'Light rain showers', 'simple-location' ),
			'rainshowers'                  => __( 'Rain showers', 'simple-location' ),
			'heavyrainshowers'             => __( 'Heavy rain showers', 'simple-location' ),
			'lightrainandthunder'          => __( 'Light rain and thunder', 'simple-location' ),
			'rainandthunder'               => __( 'Rain and thunder', 'simple-location' ),
			'heavyrainandthunder'          => __( 'Heavy rain and thunder', 'simple-location' ),
			'lightrainshowersandthunder'   => __( 'Light rain showers and thunder', 'simple-location' ),
			'rainshowersandthunder'        => __( 'Rain showers and thunder', 'simple-location' ),
			'heavyrainshowersandthunder'   => __( 'Heavy rain showers and thunder', 'simple-location' ),
			'lightsleet'                   => __( 'Light sleet', 'simple-location' ),
			'sleet'                        => __( 'Sleet', 'simple-location' ),
			'heavysleet'                   => __( 'Heavy sleet', 'simple-location' ),
			'lightsleetshowers'            => __( 'Light sleet showers', 'simple-location' ),
			'sleetshowers'                 => __( 'Sleet showers', 'simple-location' ),
			'heavysleetshowers'            => __( 'Heavy sleet showers', 'simple-location' ),
			'lightsleetandthunder'         => __( 'Light sleet and thunder', 'simple-location' ),
			'sleetandthunder'              => __( 'Sleet and thunder', 'simple-location' ),
			'heavysleetandthunder'         => __( 'Heavy sleet and thunder', 'simple-location' ),
			'lightssleetshowersandthunder' => __( 'Light sleet showers and thunder', 'simple-location' ),
			'sleetshowersandthunder'       => __( 'Sleet showers and thunder', 'simple-location' ),
			'heavysleetshowersandthunder'  => __( 'Heavy sleet showers and thunder', 'simple-location' ),
			'lightsnow'                    => __( 'Light snow', 'simple-location' ),
			'snow'                         => __( 'Snow', 'simple-location' ),
			'heavysnow'                    => __( 'Heavy snow', 'simple-location' ),
			'lightsnowshowers'             => __( 'Light snow showers', 'simple-location' ),
			'snowshowers'                  => __( 'Snow showers', 'simple-location' ),
			'heavysnowshowers'             => __( 'Heavy snow showers', 'simple-location' ),
			'lightsnowandthunder'          => __( 'Light snow and thunder', 'simple-location' ),
			'snowandthunder'               => __( 'Snow and thunder', 'simple-location' ),
			'heavysnowandthunder'          => __( 'Heavy snow and thunder', 'simple-location' ),
			'lightssnowshowersandthunder'  => __( 'Light snow showers and thunder', 'simple-location' ),
			'snowshowersandthunder'        => __( 'Snow showers and thunder', 'simple-location' ),
			'heavysnowshowersandthunder'   => __( 'Heavy snow showers and thunder', 'simple-location' ),
		);
	}

	/**
	 * Returns the description of a weather symbol.
	 *
	 * @since 6.1.0
	 *
	 * @param string $symbol Symbol, without the time of day.
	 * @return string Description, or an empty string for an unknown symbol.
	 */
	public static function symbol_summary( $symbol ) {
		return self::symbols()[ $symbol ] ?? '';
	}

	/**
	 * Converts a weather symbol to a condition code.
	 *
	 * @since 6.1.0
	 *
	 * @param string $symbol Symbol, without the time of day.
	 * @return int|string Condition code, or an empty string for an unknown symbol.
	 */
	public static function code_map( $symbol ) {
		$codes = array(
			'clearsky'         => 800,
			'fair'             => 801,
			'partlycloudy'     => 802,
			'cloudy'           => 804,
			'fog'              => 741,
			'lightrain'        => 500,
			'rain'             => 501,
			'heavyrain'        => 502,
			'lightrainshowers' => 520,
			'rainshowers'      => 521,
			'heavyrainshowers' => 522,
			'lightsleet'       => 611,
			'sleet'            => 611,
			'heavysleet'       => 611,
			'lightsleetshowers' => 612,
			'sleetshowers'     => 613,
			'heavysleetshowers' => 613,
			'lightsnow'        => 600,
			'snow'             => 601,
			'heavysnow'        => 602,
			'lightsnowshowers' => 620,
			'snowshowers'      => 621,
			'heavysnowshowers' => 622,
		);
		if ( isset( $codes[ $symbol ] ) ) {
			return $codes[ $symbol ];
		}
		if ( false !== strpos( $symbol, 'thunder' ) && isset( self::symbols()[ $symbol ] ) ) {
			// Thunder with rain is graded by the rain; with sleet or snow it is a thunderstorm.
			if ( false !== strpos( $symbol, 'rain' ) ) {
				if ( 0 === strpos( $symbol, 'light' ) ) {
					return 200;
				}
				return ( 0 === strpos( $symbol, 'heavy' ) ) ? 202 : 201;
			}
			return 211;
		}
		return '';
	}

	/**
	 * Returns the credit for the provider's weather.
	 *
	 * MET Norway's data is licensed CC BY 4.0, which asks for credit wherever it is shown.
	 *
	 * @since 6.1.0
	 *
	 * @return array Credit. See Weather_Provider::attribution().
	 */
	protected function attribution() {
		return array(
			'text'     => __( 'Weather data from MET Norway', 'simple-location' ),
			'url'      => 'https://www.met.no/en',
			'adjacent' => true,
		);
	}
}
