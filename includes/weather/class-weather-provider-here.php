<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

/**
 * Weather Provider using HERE API.
 *
 * @since 1.0.0
 */
class Weather_Provider_HERE extends Weather_Provider {
	use Sloc_API_Here;

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'HERE', 'simple-location' );
		$this->url         = 'https://developer.here.com/';
		$this->description = __( 'HERE offers a freemium plan which requires a credit card to sign up but offers a generous amount of free queries. Historical data not available.', 'simple-location' );
		$this->slug        = 'here';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_here_api' );
		}
		$args['cache_key'] = '';
		$this->region      = false;
		parent::__construct( $args );
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
	 * Return array of current conditions.
	 *
	 * @since 4.0.0
	 * @since 5.0.26 Uses the HERE Destination Weather API v3; the legacy Weather API was retired.
	 * @since 5.0.26 A time more than an hour from now uses the fallback provider, or returns a `historic_unsupported` error.
	 *
	 * @param int $time Time Optional.
	 * @return array|WP_Error Current Conditions in Array, or an error from the API.
	 */
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return array();
		}

		$datetime = $this->datetime( $time );

		if ( HOUR_IN_SECONDS < abs( $datetime->getTimestamp() - time() ) ) {
			return $this->get_unsupported_time_conditions( $time );
		}
		$return = array();
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}

			$response = $this->fetch_json(
				'https://weather.hereapi.com/v3/report',
				array(
					'apiKey'         => $this->api,
					'products'       => 'observation',
					'location'       => $this->latitude . ',' . $this->longitude,
					'units'          => 'metric',
					'oneObservation' => 'true',
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			if ( ! isset( $response['places'][0]['observations'][0] ) || ! is_array( $response['places'][0]['observations'][0] ) ) {
				return $return;
			}
			$current = $response['places'][0]['observations'][0];

			$return['temperature'] = ifset_round( $current['temperature'], 1 );
			$return['dewpoint']    = ifset_round( $current['dewPoint'], 1 );
			// Humidity is a string in v3.
			$return['humidity']   = isset( $current['humidity'] ) && is_numeric( $current['humidity'] ) ? round( (float) $current['humidity'], 1 ) : null;
			$return['pressure']   = ifset_round( $current['barometerPressure'], 1 );
			$return['uv']         = ifset_round( $current['uvIndex'], 1 );
			$return['windspeed']  = self::kmh_to_ms( ifset_round( $current['windSpeed'], 2 ) );
			$return['winddegree'] = ifset_round( $current['windDirection'], 1 );
			$return['rain']       = self::cm_to_mm( ifset_round( $current['precipitation1H'], 2 ) );
			$return['snow']       = self::cm_to_mm( ifset_round( $current['snowFall'], 2 ) );
			$return['summary']    = ifset( $current['description'] );
			$return['code']       = $this->code_map( ifset( $current['iconId'] ) );
			$return['visibility'] = self::km_to_meters( ifset( $current['visibility'] ) );
			$return               = self::filter_weather( $this->extra_data( $return, $time ) );

			$this->set_cache( $return );

			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			return $return;
		}
		return false;
	}

	/**
	 * Maps a HERE icon ID to a weather condition code.
	 *
	 * @since 5.0.26 Maps the v3 icon IDs; the legacy API's icon names are gone.
	 *
	 * @param int $id HERE icon ID, 1 to 35.
	 * @return int|string Weather condition code, or an empty string if unknown.
	 */
	private function code_map( $id ) {
		$codes = array(
			1  => 800, // Sunny.
			2  => 801, // Mostly Sunny.
			3  => 721, // Haze.
			4  => 802, // Partly Cloudy.
			5  => 802, // High Clouds.
			6  => 803, // Mostly Cloudy.
			7  => 804, // Cloudy.
			8  => 701, // Light Fog.
			9  => 741, // Dense Fog.
			10 => 741, // Ice Fog.
			11 => 751, // Sand Storm.
			12 => 741, // Early Fog.
			13 => 800, // Night Clear.
			14 => 801, // Night Mostly Clear.
			15 => 802, // Night Partly Cloudy.
			16 => 802, // Night High Clouds.
			17 => 803, // Night Mostly Cloudy.
			18 => 300, // Sprinkles.
			19 => 503, // Rain.
			20 => 522, // Heavy Rain.
			21 => 210, // Scattered Thunderstorms.
			22 => 211, // Thunderstorms.
			23 => 212, // Severe Thunderstorms.
			24 => 624, // Hail.
			25 => 781, // Tornado.
			26 => 782, // Hurricane.
			27 => 611, // Sleet.
			28 => 610, // Snow and Rain Mix.
			29 => 600, // Light Snow.
			30 => 601, // Snow.
			31 => 602, // Heavy Snow.
			32 => 540, // Flood.
			33 => 521, // Showers.
			34 => 300, // Night Sprinkles.
			35 => 211, // Night Thunderstorms.
		);
		$id    = (int) $id;
		return isset( $codes[ $id ] ) ? $codes[ $id ] : '';
	}
}
