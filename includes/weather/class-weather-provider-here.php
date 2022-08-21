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
		$this->description = __( 'HERE offers a free limited plan for up to 2500 weather transactions per month. Historical data not available.', 'simple-location' );
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
	 * @param int $time Time Optional.
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return array();
		}

		$datetime = $this->datetime( $time );

		if ( HOUR_IN_SECONDS < abs( $datetime->getTimestamp() - time() ) ) {
			return array(
				'time'     => $time,
				'datetime' => $datetime,
			);
		}
		$return = array();
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}

			$data = array(
				'apiKey'         => $this->api,
				'product'        => 'observation',
				'latitude'       => $this->latitude,
				'longitude'      => $this->longitude,
				'oneobservation' => 'true',
			);
			$url  = 'https://weather.ls.hereapi.com/weather/1.0/report.json';
			$url  = add_query_arg( $data, $url );
			$args = array(
				'headers'             => array(
					'Accept' => 'application/json',
				),
				'timeout'             => 10,
				'limit_response_size' => 1048576,
				'redirection'         => 1,
				// Use an explicit user-agent for Simple Location.
				'user-agent'          => 'Simple Location for WordPress',
			);

			$response = wp_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			if ( ! isset( $response['observations'] ) ) {
				return $return;
			}
			$current = ifset( $response['observations']['location'][0]['observation'][0] );
			if ( ! $current ) {
				return $return;
			}
			$return['temperature'] = ifset_round( $current['temperature'], 1 );
			$return['dewpoint']    = ifset_round( $current['dewPoint'], 1 );

			$return['humidity'] = ifset_round( $current['humidity'], 1 );

			$return['pressure']   = ifset_round( $current['barometerPressure'], 1 );
			$return['uv']         = ifset_round( $current['uvIndex'], 1 );
			$return['windspeed']  = self::kmh_to_ms( ifset_round( $current['windSpeed'] ) );
			$return['winddegree'] = ifset_round( $current['windDirection'], 1 );
			$return['rain']       = self::cm_to_mm( ifset_round( $current['precipitation1H'], 2 ) );
			$return['snow']       = ifset_round( $current['snowFall'], 2 );
			$return['summary']    = ifset( $current['description'] );
			$return['icon']       = $this->icon_map( $current['icon'] );
			$return['visibility'] = self::km_to_meters( ifset_round( $current['visibility'], 1 ) );
			$return               = array_filter( $this->extra_data( $return ) );

			$this->set_cache( $return );

			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			return $return;
		}
		return false;
	}

	/**
	 * Return array of station data.
	 *
	 * @param string $id Weather type ID.
	 * @return string Icon ID.
	 */
	private function icon_map( $id ) {
		switch ( $id ) {
			case 'sunny':
			case 'clear':
			case 'mostly_sunny':
			case 'mostly_clear':
			case 'passing_clouds':
			case 'more_sun_than_clouds':
			case 'scattered_clouds':
			case 'partly_cloudy':
			case 'a_mixture_of_sun_and_clouds':
			case 'increasing_cloudiness':
			case 'breaks_of_sun_late':
			case 'afternoon_clouds':
			case 'morning_clouds':
			case 'partly_sunny':
			case 'high_level_clouds':
			case 'decreasing_cloudiness':
			case 'clearing_skies':
			case 'high_clouds':
			case 'rain_early':
			case 'heavy_rain_early':
			case 'strong_thunderstorms':
			case 'severe_thunderstorms':
			case 'thundershowers':
			case 'thunderstorms':
			case 'tstorms_early':
			case 'isolated_tstorms_late':
			case 'scattered_tstorms_late':
			case 'tstorms_late':
			case 'tstorms':
			case 'ice_fog':
			case 'more_clouds_than_sun':
			case 'broken_clouds':
			case 'scattered_showers':
			case 'a_few_showers':
			case 'light_showers':
			case 'passing_showers':
			case 'rain_showers':
			case 'showers':
			case 'widely_scattered_tstorms':
			case 'isolated_tstorms':
			case 'a_few_tstorms':
			case 'scattered_tstorms':
			case 'hazy_sunshine':
			case 'haze':
			case 'smoke':
			case 'low_level_haze':
			case 'early_fog_followed_by_sunny_skies':
			case 'early_fog':
			case 'light_fog':
			case 'fog':
			case 'dense_fog':
			case 'night_haze':
			case 'night_smoke':
			case 'night_low_level_haze':
			case 'night_widely_scattered_tstorms':
			case 'night_isolated_tstorms':
			case 'night_a_few_tstorms':
			case 'night_scattered_tstorms':
			case 'night_tstorms':
			case 'night_clear':
			case 'mostly_cloudy':
			case 'cloudy':
			case 'overcast':
			case 'low_clouds':
			case 'hail':
			case 'sleet':
			case 'light_mixture_of_precip':
			case 'icy_mix':
			case 'mixture_of_precip':
			case 'heavy_mixture_of_precip':
			case 'snow_changing_to_rain':
			case 'snow_changing_to_an_icy_mix':
			case 'an_icy_mix_changing_to_snow':
			case 'an_icy_mix_changing_to_rain':
			case 'rain_changing_to_snow':
			case 'rain_changing_to_an_icy_mix':
			case 'light_icy_mix_early':
			case 'icy_mix_early':
			case 'light_icy_mix_late':
			case 'icy_mix_late':
			case 'snow_rain_mix':
			case 'scattered_flurries':
			case 'snow_flurries':
			case 'light_snow_showers':
			case 'snow_showers':
			case 'light_snow':
			case 'flurries_early':
			case 'snow_showers_early':
			case 'light_snow_early':
			case 'flurries_late':
			case 'snow_showers_late':
			case 'light_snow_late':
			case 'night_decreasing_cloudiness':
			case 'night_clearing_skies':
			case 'night_high_level_clouds':
			case 'night_high_clouds':
			case 'night_scattered_showers':
			case 'night_a_few_showers':
			case 'night_light_showers':
			case 'night_passing_showers':
			case 'night_rain_showers':
			case 'night_sprinkles':
			case 'night_showers':
			case 'night_mostly_clear':
			case 'night_passing_clouds':
			case 'night_scattered_clouds':
			case 'night_partly_cloudy':
			case 'increasing_cloudiness':
			case 'night_afternoon_clouds':
			case 'night_morning_clouds':
			case 'night_broken_clouds':
			case 'night_mostly_cloudy':
			case 'light_freezing_rain':
			case 'freezing_rain':
			case 'heavy_rain':
			case 'lots_of_rain':
			case 'tons_of_rain':
			case 'heavy_rain_early':
			case 'heavy_rain_late':
			case 'flash_floods':
			case 'flood':
			case 'drizzle':
			case 'sprinkles':
			case 'light_rain':
			case 'sprinkles_early':
			case 'light_rain_early':
			case 'sprinkles_late':
			case 'light_rain_late':
			case 'rain':
			case 'numerous_showers':
			case 'showery':
			case 'showers_early':
			case 'rain_early':
			case 'showers_late':
			case 'rain_late':
			case 'snow':
			case 'moderate_snow':
			case 'snow_early':
			case 'snow_late':
			case 'heavy_snow':
			case 'heavy_snow_early':
			case 'heavy_snow_late':
			case 'tornado':
			case 'tropical_storm':
			case 'hurricane':
			case 'sandstorm':
			case 'duststorm':
			case 'snowstorm':
			case 'blizzard':
			default:
				return '';
		}
	}

}
