<?php
/**
 * Base Weather Provider Class.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns weather data.
 *
 * @since 1.0.0
 */
abstract class Weather_Provider extends Sloc_Provider {
	use Weather_Info_Trait;

	/**
	 * Station ID.
	 *
	 * Many weather sites permit a station ID to be set.
	 *
	 * @since 1.0.0
	 * @var $string
	 */
	protected $station_id;

	/**
	 * Units. si, imperial, etc.
	 *
	 * @since 1.0.0
	 * @var $string
	 */
	protected $units;

	/**
	 * Cache Time.
	 *
	 * Cache time in seconds. Defaults to 0.
	 *
	 * @since 1.0.0
	 * @var $int
	 */
	protected $cache_time;

	/**
	 * Where weather credits may be shown: next to the weather, or once in the page footer.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const ATTRIBUTIONS = array( 'next', 'footer' );

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @param array $args {
	 *  Arguments.
	 *  @type string $api API Key.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type string $station_id Station ID.
	 *  @type int $cache_time Cache in Seconds.
	 *  @type string $units Units of Measurement.
	 * }
	 */
	public function __construct( $args = array() ) {
		$defaults = array(
			'api'        => null,
			'latitude'   => null,
			'longitude'  => null,
			'station_id' => null,
			'cache_time' => 0,
			'units'      => get_option( 'sloc_measurements', Loc_Config::measurement_default() ),
		);
		/**
		 * Filters the default arguments of weather providers.
		 *
		 * @since 3.3.0
		 *
		 * @param array $defaults Default arguments: api, latitude, longitude, station_id, cache_time and units.
		 */
		$defaults         = apply_filters( 'sloc_weather_provider_defaults', $defaults );
		$r                = wp_parse_args( $args, $defaults );
		$this->api        = $r['api'];
		$this->station_id = $r['station_id'];
		$this->units      = $r['units'];
		$this->cache_time = intval( $r['cache_time'] );
		$this->set( $r['latitude'], $r['longitude'] );

		if ( $this->is_active() && method_exists( $this, 'init' ) ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'init', array( $this, 'init' ) );
		}
	}

	/**
	 * Returns the credit the provider's terms ask for wherever its weather is shown.
	 *
	 * Providers whose terms ask for none return an empty array.
	 *
	 * @since 6.1.0
	 *
	 * @return array {
	 *     Credit, or an empty array if none is needed.
	 *
	 *     @type string $text     Credit text, such as "Weather data by Open-Meteo.com".
	 *     @type string $url      Link for the credit.
	 *     @type bool   $adjacent Whether the terms require the credit next to the weather, rather than anywhere on the page.
	 * }
	 */
	protected function attribution() {
		return array();
	}

	/**
	 * Returns the provider's credit, after the `sloc_weather_credit` filter.
	 *
	 * @since 6.1.0
	 *
	 * @return array Credit, or an empty array for none. See attribution().
	 */
	public function get_attribution() {
		/**
		 * Filters the credit shown with a weather provider's weather.
		 *
		 * Some providers only require their credit on free plans, such as OpenWeather and Weatherbit; a site on a paid
		 * plan can remove it by returning an empty array. A site can also add a credit for a provider that doesn't
		 * require one.
		 *
		 * @since 6.1.0
		 *
		 * @param array            $credit {
		 *     Credit, or an empty array for none.
		 *
		 *     @type string $text     Credit text.
		 *     @type string $url      Link for the credit.
		 *     @type bool   $adjacent Whether the credit must be next to the weather, rather than in the footer.
		 * }
		 * @param Weather_Provider $provider Weather provider.
		 */
		$credit = apply_filters( 'sloc_weather_credit', $this->attribution(), $this );
		return is_array( $credit ) ? $credit : array();
	}

	/**
	 * Returns the provider's credit as a link.
	 *
	 * @since 6.1.0
	 *
	 * @return string HTML, or an empty string if the provider needs no credit.
	 */
	public function get_attribution_html() {
		$credit = $this->get_attribution();
		if ( empty( $credit['text'] ) ) {
			return '';
		}
		if ( empty( $credit['url'] ) ) {
			return esc_html( $credit['text'] );
		}
		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $credit['url'] ), esc_html( $credit['text'] ) );
	}

	/**
	 * Sanitizes the weather credit setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Setting value.
	 * @return string One of ATTRIBUTIONS, 'next' by default.
	 */
	public static function sanitize_attribution( $value ) {
		return in_array( $value, self::ATTRIBUTIONS, true ) ? $value : 'next';
	}

	/**
	 * Is Provider Active
	 *
	 * @since 1.0.0
	 * @since 6.1.0 Active anywhere in the weather chain.
	 */
	public function is_active() {
		return in_array( $this->slug, Sloc_Provider_Chain::configured( 'weather' ), true );
	}

	/**
	 * Returns whether the provider has weather for a time, so the weather chain can skip it without a request.
	 *
	 * @since 6.1.0
	 *
	 * @param int|string|DateTime|null $time Time, or null for now.
	 * @return bool Whether to try the provider for this time.
	 */
	public function supports_time( $time = null ) {
		return true;
	}

	/**
	 * Removes unset values from weather data.
	 *
	 * Unlike array_filter() without a callback, this keeps 0, 0.0 and '0', which are real
	 * readings (0 °C, no wind, no rain). It removes null, '', false and empty arrays, which
	 * stand for a missing value. `day` is kept when it is false (night).
	 *
	 * @since 6.0.0
	 *
	 * @param array $values Weather data.
	 * @return array Weather data without unset values.
	 */
	protected static function filter_weather( $values ) {
		return array_filter(
			$values,
			function ( $value, $key ) {
				if ( 'day' === $key ) {
					return is_bool( $value );
				}
				return null !== $value && '' !== $value && false !== $value && array() !== $value;
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Rounds a value if it is a number.
	 *
	 * @since 6.0.0
	 *
	 * @param mixed $value     Value.
	 * @param int   $precision Optional. Number of decimal places. Default 0.
	 * @return float|null Rounded value, or null if the value isn't a number.
	 */
	protected static function round_value( $value, $precision = 0 ) {
		return is_numeric( $value ) ? round( $value, $precision ) : null;
	}

	/**
	 * Lux of daylight per watt per square meter of solar radiation, roughly, to compare illuminance with radiation.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const LUX_PER_WATT = 120;

	/**
	 * Least clear-sky radiation, in W/m², at which the sky is estimated from sunlight; below it, about when the sun is
	 * under 10 degrees, the measurement says little about clouds.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MIN_CLEAR_SKY_RADIATION = 150;

	/**
	 * Returns the radiation a clear sky would give at a place and time, in W/m².
	 *
	 * The FAO-56 clear-sky radiation over the 15 minutes around the time, from Astronomical_Calculator.
	 *
	 * @since 6.1.0
	 *
	 * @param Astronomical_Calculator $calc      Calculator for the place.
	 * @param int                     $timestamp Unix timestamp.
	 * @return float Radiation in W/m².
	 */
	public static function clear_sky_radiation( $calc, $timestamp ) {
		// The radiation over the quarter hour, in MJ/m², as an hourly rate, then in W/m².
		return $calc->clear_sky_radiation( $calc->sun_radiation( 0.25, $timestamp + 450 ) ) / 0.25 * 1000000 / 3600;
	}

	/**
	 * Estimates the sky by day from the sunlight measured, as a weather station gives it, by comparing it with what a
	 * clear sky would give at that time and place.
	 *
	 * The ratio, the clearness index, is about 1 under a clear sky and falls as clouds block the sun: 0.8 or more is a
	 * clear sky, then few, scattered and broken clouds, and under 0.3, overcast.
	 *
	 * @since 6.1.0
	 *
	 * @param float|null              $radiation   Solar radiation in W/m², if measured.
	 * @param float|null              $illuminance Illuminance in lux, used without the radiation.
	 * @param Astronomical_Calculator $calc        Calculator for the place.
	 * @param int|DateTime|null       $timestamp   Time. Default now.
	 * @return int|null Condition code, 800 to 804, or null without a measurement or with the sun too low.
	 */
	public static function condition_from_sunlight( $radiation, $illuminance, $calc, $timestamp = null ) {
		if ( is_numeric( $radiation ) ) {
			$measured = (float) $radiation;
		} elseif ( is_numeric( $illuminance ) ) {
			$measured = (float) $illuminance / self::LUX_PER_WATT;
		} else {
			return null;
		}
		if ( $timestamp instanceof DateTimeInterface ) {
			$timestamp = $timestamp->getTimestamp();
		}
		$timestamp = is_numeric( $timestamp ) ? (int) $timestamp : time();
		$clear     = self::clear_sky_radiation( $calc, $timestamp );
		if ( $clear < self::MIN_CLEAR_SKY_RADIATION || $measured < 0 ) {
			return null;
		}
		$index = $measured / $clear;
		$code  = 804;
		foreach ( array(
			800 => 0.8,
			801 => 0.65,
			802 => 0.5,
			803 => 0.3,
		) as $condition => $least ) {
			if ( $index >= $least ) {
				$code = $condition;
				break;
			}
		}
		/**
		 * Filters the condition estimated from the sunlight measured.
		 *
		 * @since 6.1.0
		 *
		 * @param int   $code  Condition code, 800 (clear sky) to 804 (overcast).
		 * @param float $index Clearness index: the radiation measured over that of a clear sky.
		 * @param float $clear Clear-sky radiation in W/m².
		 */
		return (int) apply_filters( 'sloc_condition_from_sunlight', $code, $index, $clear );
	}

	/**
	 * Extra Parameters for location.
	 *
	 * @since 6.0.0 Values of 0, and `day` when it is false, are kept.
	 * @since 6.0.0 Rounds cloudiness to a whole percentage.
	 * @since 6.1.0 Calculates the wind chill and heat index when the provider didn't give them.
	 * @since 6.1.0 Calculates the dew point or relative humidity when the provider gave only one.
	 * @since 6.1.0 Adds the provider's slug as `provider`, so its credit can be shown with the weather.
	 * @since 6.1.0 Estimates the condition from the solar radiation or illuminance when the provider gave none.
	 *
	 * @param array $return Weather data.
	 * @param int   $timestamp Unix timestamp. Optional.
	 * @return array {
	 *  Arguments.
	 *  @type string $provider  Slug of the provider the weather came from, unless set already, such as by a fallback provider.
	 *  @type float  $dewpoint  Dew point in Celsius, if the provider gave it or the humidity. See dew_point().
	 *  @type float  $humidity  Relative humidity in percent, if the provider gave it or the dew point. See relative_humidity().
	 *  @type float  $windchill Wind chill in Celsius, if the provider gave it or it applies. See wind_chill().
	 *  @type float  $heatindex Heat index in Celsius, if the provider gave it or it applies. See heat_index().
	 *  @type int    $code      Condition, if the provider gave it, or estimated by day from the sunlight measured. See
	 *                          condition_from_sunlight().
	 *  @type bool   $day True if daytime, false if night.
	 *  @type string $sunset Sunset in iso8601 format.
	 *  @type string $sunrise Sunrise in iso8601 format.
	 *  @type string $moonset Moonset in iso8601 format.
	 *  @type string $moonrise Moonrise in iso8601 format.
	 *  @type string $localtime Local time.
	 * }
	 */
	public function extra_data( $return, $timestamp = null ) {
		if ( is_null( $timestamp ) ) {
			$timestamp = time();
		}
		if ( isset( $return['cloudiness'] ) && is_numeric( $return['cloudiness'] ) ) {
			$return['cloudiness'] = (int) round( (float) $return['cloudiness'] );
		}
		if ( empty( $return['provider'] ) ) {
			$return['provider'] = $this->slug;
		}
		// A provider's own values are kept. Humidity comes first, as the heat index needs it.
		if ( ! isset( $return['dewpoint'] ) || ! is_numeric( $return['dewpoint'] ) ) {
			$return['dewpoint'] = self::dew_point( $return['temperature'] ?? null, $return['humidity'] ?? null );
		}
		if ( ! isset( $return['humidity'] ) || ! is_numeric( $return['humidity'] ) ) {
			$return['humidity'] = self::relative_humidity( $return['temperature'] ?? null, $return['dewpoint'] ?? null );
		}
		if ( ! isset( $return['windchill'] ) || ! is_numeric( $return['windchill'] ) ) {
			$return['windchill'] = self::wind_chill( $return['temperature'] ?? null, $return['windspeed'] ?? null );
		}
		if ( ! isset( $return['heatindex'] ) || ! is_numeric( $return['heatindex'] ) ) {
			$return['heatindex'] = self::heat_index( $return['temperature'] ?? null, $return['humidity'] ?? null );
		}
		$latitude           = array_key_return( 'latitude', $return, $this->latitude );
		$longitude          = array_key_return( 'longitude', $return, $this->longitude );
		$altitude           = array_key_return( 'altitude', $return, $this->altitude );
		$calc               = new Astronomical_Calculator( $latitude, $longitude, $altitude );
		$return['sunrise']  = $calc->get_iso8601( $timestamp );
		$return['sunset']   = $calc->get_iso8601( $timestamp, 'sunset' );
		$return['moonrise'] = $calc->get_iso8601( $timestamp, 'moonrise' );
		$return['moonset']  = $calc->get_iso8601( $timestamp, 'moonset' );
		$return['day']      = $calc->is_daytime( $timestamp );
		// Without a condition, such as from a station, one is estimated from the sunlight measured (#204).
		if ( empty( $return['code'] ) && ! empty( $return['day'] ) ) {
			$return['code'] = self::condition_from_sunlight( $return['radiation'] ?? null, $return['illuminance'] ?? null, $calc, $timestamp );
		}
		$timezone = Loc_Timezone::timezone_for_location( $latitude, $longitude );
		if ( $timezone instanceof Timezone_Result ) {
			$timezone = $timezone->timezone;
		}
		if ( $timestamp instanceof DateTime ) {
			$datetime = $timestamp;
		} elseif ( is_numeric( $timestamp ) ) {
			$datetime = new DateTime( 'now', $timezone );
			if ( ! is_null( $timestamp ) ) {
				$datetime->setTimestamp( $timestamp );
			}
		} else {
			$datetime = new DateTime();
		}

		$return['localtime'] = $datetime->format( DATE_W3C );

		return self::filter_weather( $return );
	}

	/**
	 * Set caching time
	 *
	 * @param int $cache_time The Cache time in seconds.
	 */
	public function set_cache_time( $cache_time ) {
		$this->cache_time = intval( $cache_time );
	}

	/**
	 * Get cache.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Takes the requested time, so conditions for a past time are cached separately.
	 *
	 * @param int|null $time Optional. Requested time as a timestamp. Default null, for current conditions.
	 * @return false|array Return cached value or false.
	 */
	public function get_cache( $time = null ) {
		if ( 0 === $this->cache_time ) {
			return false;
		}

		$cache = get_transient( $this->cache_key( $time ) );
		return $cache;
	}

	/**
	 * Set cache.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Takes the requested time, so conditions for a past time are cached separately.
	 *
	 * @param array    $value Value to Be Cached.
	 * @param int|null $time  Optional. Requested time as a timestamp. Default null, for current conditions.
	 * @return boolean
	 */
	public function set_cache( $value, $time = null ) {
		if ( 0 === $this->cache_time ) {
			return false;
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		$datetime = date_create_from_format( 'U', time() + $this->cache_time );
		$datetime->setTimezone( wp_timezone() );
		$value['_expires_at'] = $datetime->format( DATE_W3C );

		$cache = set_transient( $this->cache_key( $time ), $value, $this->cache_time );
		return $cache;
	}

	/**
	 * Generate cache key
	 *
	 * Conditions for a time more than an hour from now are keyed by that hour, so each past hour is cached
	 * separately. Current conditions, with no time or a time within the hour, keep the key without a time.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Includes the hour of a requested time that isn't current.
	 *
	 * @param int|null $time Optional. Requested time as a timestamp. Default null, for current conditions.
	 * @return string|false Cache Key, or false without a station or location.
	 **/
	private function cache_key( $time = null ) {
		if ( ! empty( $this->station_id ) ) {
			$key = array( get_called_class(), md5( $this->station_id ) );
		} elseif ( ! empty( $this->latitude ) && ! empty( $this->longitude ) ) {
			$key = array( get_called_class(), md5( implode( ',', array( $this->latitude, $this->longitude ) ) ) );
		} else {
			return false;
		}
		if ( is_numeric( $time ) && 0 !== (int) $time && HOUR_IN_SECONDS < abs( time() - (int) $time ) ) {
			$key[] = gmdate( 'YmdH', (int) $time );
		}
		return implode( '_', $key );
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
	 * Return Station ID if set.
	 *
	 * @return string|null Station ID.
	 */
	public function get_station() {
		return $this->station_id;
	}

	/**
	 * Marks up a measurement.
	 *
	 * @since 4.4.9
	 * @since 6.0.0 Cloudiness is shown as a whole percentage unless a precision is given.
	 *
	 * @param string $property Property.
	 * @param mixed  $value    Value of the Property.
	 * @param array  $args {
	 *     Optional. Arguments.
	 *
	 *     @type boolean     $markup    Mark the value up with microformats. Default true.
	 *     @type string      $container Element to wrap the value in. Default 'li'.
	 *     @type mixed       $label     Unused.
	 *     @type string      $units     'imperial' or 'metric'. Defaults to the measurement setting.
	 *     @type boolean|int $round     False rounds to 2 decimal places, true to a whole number,
	 *                                  and a number to that precision. Default false.
	 * }
	 * @return string Marked up value, or an empty string for an unknown property.
	 */
	public static function markup_value( $property, $value, $args = array() ) {
		$defaults      = array(
			'markup'    => true, // Mark the value up with microformats.
			'container' => 'li', // Wrap in this element.
			'label'     => 'false', // Show the name of the property.
			'units'     => get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) ),
			'round'     => false, // False to round to 2 digits, true to round to integer, a numeric value to round to a specific precision.
		);
		$args          = wp_parse_args( $args, $defaults );
		$args['units'] = ( 'imperial' === $args['units'] );
		$params        = self::get_names( $property, $args['units'] );
		if ( ! $params ) {
			return '';
		}
		// Cloudiness is a whole percentage; values saved before 6.0.0 can have decimals.
		if ( 'cloudiness' === $property && false === $args['round'] ) {
			$args['round'] = true;
		}
		if ( is_numeric( $value ) ) {
			if ( is_numeric( $args['round'] ) ) {
				$value = round( $value, $args['round'] );
			} elseif ( true === $args['round'] ) {
				$value = round( $value );
			} else {
				$value = round( $value, 2 );
			}
		}

		if ( $args['markup'] ) {
			return sprintf(
				'<%1$s class="sloc-%2$s p-%2$s h-measure">
				<data class="p-type" value="%5$s"></data>
				<data class="p-num" value="%3$s">%3$s</data>
				<data class="p-unit" value="%4$s">%4$s</data></%1$s>',
				$args['container'],
				$property,
				$value,
				$params['unit'],
				$params['label']
			);
		}

		return sprintf(
			'<%1$s class="sloc-%2$s">%6$s%5$s: %3$s%4$s</%1$s>',
			$args['container'],
			$property,
			$value,
			$params['unit'],
			$params['name'],
			self::get_icon( $params['icon'] )
		);
	}

	/**
	 * Return array of current conditions. All fields optional.
	 *
	 * All floats rounded to 2 decimal points. Fields describing the location
	 * are expected if this is a station return.
	 *
	 * @param string|int|DateTime $time ISO8601, timestamp, or DateTime.
	 * @return array {
	 *  Current Conditions in Array.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type float $altitude Altitude.
	 *  @type int   $timestamp Timestamp.
	 *  @type string $name Name of the Location.
	 *  @type float $temperature Temperature in Celsius.
	 *  @type float $heatindex Heat Index in Celsius.
	 *  @type float $windchill Wind Chill in Celsius.
	 *  @type float $dewpoint Dewpoint in Celsius.
	 *  @type float $humidity Humidity as a percentage between 0 and 100.
	 *  @type float $pressure Atomospheric Pressure at mean sea level in hPa/mbar
	 *  @type int $cloudiness Cloudiness as a percentage between 0 and 100.
	 *  @type int $windspeed Speed in meters per second.
	 *  @type float $winddegree Degree between 0 and 360.
	 *  @type int $windgust Wind Gust in meters per second.
	 *  @type float $rain Rainfall in millimeters for the last hour.
	 *  @type float $snow Snowfall in millimeters for the last hour.
	 *  @type float $visibility Visibility in meters.
	 *  @type float $radiation Estimated Solar Radiation in W/m^2.
	 *  @type int   $illuminance in Lux.
	 *  @type float $uv UV Index. 0-11+.
	 *  @type int $aqi Air Quality Index.
	 *  @type float $pm1_0 Particular Matter smaller than 1 micrometer  measured in ug/m3.
	 *  @type float $pm2_5 Particular Matter smaller than 2.5 micrometers measured in ug/m3.
	 *  @type float $pm10_0 Particular Matter smaller than 10 micrometers  measured in ug/m3.
	 *  @type int   $co Carbon Monoxide in ppm(parts per million).
	 *  @type int   $co2 Carbon Dioxide in ppm.
	 *  @type int   $nh3 Ammonia in ppm/
	 *  @type int   $o3 Ozone in ppm.
	 *  @type int   $pb  Lead in ppm.
	 *  @type int   $so2 Sulfur Dioxide in ppm.
	 *  @type string $summary Textual Description of Weather.
	 *  @type string $icon Slug for icon to display.
	 *  @type string $sunrise ISO8601 string reflecting sunrise time.
	 *  @type string $sunset ISO8601 string reflecting sunset time.
	 * }
	 */
	abstract public function get_conditions( $time );


	/**
	 * Generates label for a measurement.
	 *
	 * @param string  $property Property Name.
	 * @param boolean $imperial Whether to return metric or imperial units.
	 * @return string Formatted string including the property and appropriate label.
	 */
	public static function get_form_label( $property, $imperial ) {
		$props = self::get_names( $property, $imperial );
		if ( ! $props ) {
			return '';
		}
		/* translators: 1. Property Name 2. Units */
		$label = sprintf( __( '%1$s measured in %2$s', 'simple-location' ), $props['name'], $props['label'] );
		/* translators: 1. Property Name 2. Aria Label 3. Unit of Measurement */
		return sprintf( '%1$s(<span aria-label="%2$s">%3$s</span>):', $props['name'], $label, $props['unit'] );
	}

	/**
	 * Return array of current conditions. All fields optional.
	 *
	 * All floats rounded to 2 decimal points. Fields describing the location
	 * are expected if this is a station return.
	 *
	 * The weather chain tries the next provider itself, so this no longer calls the fallback provider.
	 *
	 * @since 1.0.0
	 * @since 6.1.0 Returns a `no_results` error. Sloc_Provider_Chain tries the next provider.
	 * @deprecated 6.1.0 Return no_results_error(), or the error that stopped the lookup.
	 *
	 * @param string|int|DateTime $time ISO8601, timestamp, or DateTime.
	 * @return WP_Error No conditions from this provider.
	 */
	protected function get_fallback_conditions( $time ) {
		return $this->no_results_error();
	}

	/**
	 * Returns the error for a lookup the provider has no weather for.
	 *
	 * @since 6.1.0
	 *
	 * @return WP_Error A `no_results` error.
	 */
	protected function no_results_error() {
		return new WP_Error(
			'no_results',
			/* translators: %s: Weather provider name. */
			sprintf( __( '%s has no weather for this location and time.', 'simple-location' ), $this->get_name() )
		);
	}

	/**
	 * Returns the error for a provider without its API key, which administrators are told about.
	 *
	 * @since 6.1.0
	 *
	 * @return WP_Error A `missing_api_key` error.
	 */
	protected function missing_api_key_error() {
		return new WP_Error(
			'missing_api_key',
			/* translators: %s: Weather provider name. */
			sprintf( __( 'You have not set an API key for %s', 'simple-location' ), $this->get_name() )
		);
	}

	/**
	 * Returns whether a time is current: within an hour of now.
	 *
	 * @since 6.1.0
	 *
	 * @param int|string|DateTime|null $time Time, or null for now.
	 * @return bool Whether it is current.
	 */
	protected function is_current( $time = null ) {
		return HOUR_IN_SECONDS >= abs( $this->datetime( $time )->getTimestamp() - time() );
	}

	/**
	 * Return conditions for a time this provider has no weather for.
	 *
	 * Providers with only current conditions call this for times more than an hour from now.
	 *
	 * @since 6.0.0
	 *
	 * @param string|int|DateTime $time ISO8601, timestamp, or DateTime.
	 * @since 6.1.0 Always the error. Sloc_Provider_Chain tries the next provider.
	 *
	 * @return WP_Error A `historic_unsupported` error.
	 */
	protected function get_unsupported_time_conditions( $time ) {
		return new WP_Error(
			'historic_unsupported',
			/* translators: %s: Weather provider name. */
			sprintf( __( '%s only provides current weather.', 'simple-location' ), $this->get_name() ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Return summary of current conditions.
	 *
	 * @return string Summary of Current conditions.
	 */
	public function get_current_condition() {
		$conditions = $this->get_conditions( null );
		if ( ! is_array( $conditions ) ) {
			$conditions = array();
		}
		$return  = '<div class="sloc-weather">';
		$return .= $this->get_icon( $conditions['icon'] ?? null, $conditions['summary'] ?? null );
		if ( isset( $conditions['temperature'] ) ) {
			$return .= $this->get_temp( $conditions['temperature'] );
		}
		$return .= '</div>';
		return $return;
	}

	/**
	 * Set and Validate Coordinates.
	 *
	 * A lookup is either by station or by location, so setting one clears the other: coordinates without a station
	 * clear the station, and a station without coordinates clears the location. An array with both keeps both.
	 *
	 * @since 1.0.0
	 * @since 6.1.0 Setting coordinates clears the station, and setting only a station clears the location (#681).
	 *              An empty station_id clears the station, and whitespace around it is trimmed (#352).
	 *
	 * @param array|float $lat Latitude or array of all three properties.
	 * @param float       $lng Longitude. Optional if first property is an array.
	 * @param float       $alt Altitude. Optional.
	 * @return boolean Return False if Validation Failed
	 */
	public function set( $lat, $lng = null, $alt = null ) {
		$location = true;
		if ( ! $lng && is_array( $lat ) ) {
			if ( isset( $lat['units'] ) ) {
				$this->units = $lat['units'];
			}
			$location = isset( $lat['latitude'], $lat['longitude'] );
			if ( array_key_exists( 'station_id', $lat ) ) {
				$station          = is_string( $lat['station_id'] ) ? trim( $lat['station_id'] ) : $lat['station_id'];
				$this->station_id = ( is_scalar( $station ) && '' !== (string) $station ) ? $station : null;
				if ( $this->station_id && ! $location ) {
					$this->latitude  = null;
					$this->longitude = null;
					$this->altitude  = null;
				}
				return parent::set( $lat, $lng, $alt );
			}
		}
		$result = parent::set( $lat, $lng, $alt );
		if ( $result && $location ) {
			$this->station_id = null;
		}
		return $result;
	}


	/**
	 * Passes temperature and returns it either same of converted into preferred units.
	 *
	 * @param float $temperature Temperature in celsius.
	 * @return string Marked up temperature in proper untis.
	 */
	private function get_temp( $temperature ) {
		if ( 'imperial' === $this->units ) {
			$temperature = self::celsius_to_fahrenheit( $temperature );
		}
		return round( $temperature ) . '&deg;' . $this->temp_unit();
	}

	/**
	 * Returns current temperature from conditions.
	 *
	 * @return string Marked up temperature in proper untis.
	 */
	public function get_current_temperature() {
		$conditions = $this->get_conditions( null );
		if ( is_array( $conditions ) && isset( $conditions['temperature'] ) ) {
			return $this->get_temp( $conditions['temperature'] );
		}
		return '';
	}

	/**
	 * Returns list of available icons.
	 *
	 * @return array List of Icon Options.
	 */
	public static function get_iconlist() {
		$neutral = array(
			'wi-cloud'             => __( 'Cloud', 'simple-location' ),
			'wi-cloudy'            => __( 'Cloudy', 'simple-location' ),
			'wi-cloudy-gusts'      => __( 'Cloudy with Gusts', 'simple-location' ),
			'wi-cloudy-windy'      => __( 'Cloudy with Wind', 'simple-location' ),
			'wi-showers'           => __( 'Showers', 'simple-location' ),
			'wi-rain-mix'          => __( 'Rain-Mix', 'simple-location' ),
			'wi-rain'              => __( 'Rain', 'simple-location' ),
			'wi-rain-wind'         => __( 'Rain and Windy', 'simple-location' ),
			'wi-snow'              => __( 'Snow', 'simple-location' ),
			'wi-snow-wind'         => __( 'Snow and Wind', 'simple-location' ),
			'wi-fog'               => __( 'Fog', 'simple-location' ),
			'wi-hot'               => __( 'Hot', 'simple-location' ),
			'wi-lightning'         => __( 'Lightning', 'simple-location' ),
			'wi-sandstorm'         => __( 'Sandstorm', 'simple-location' ),
			'wi-sleet'             => __( 'Sleet', 'simple-location' ),
			'wi-smog'              => __( 'Smog', 'simple-location' ),
			'wi-smoke'             => __( 'Smoke', 'simple-location' ),
			'wi-snowflake-cold'    => __( 'Snowflake-Cold', 'simple-location' ),
			'wi-solar-eclipse'     => __( 'Solar Eclipse', 'simple-location' ),
			'wi-sprinkle'          => __( 'Sprinkles', 'simple-location' ),
			'wi-stars'             => __( 'Stars', 'simple-location' ),
			'wi-storm-showers'     => __( 'Storm Showers', 'simple-location' ),
			'wi-storm-warning'     => __( 'Storm Warning', 'simple-location' ),
			'wi-strong-wind'       => __( 'Strong Winds', 'simple-location' ),
			'wi-thunderstorm'      => __( 'Thunderstorm', 'simple-location' ),
			'wi-windy'             => __( 'Windy', 'simple-location' ),
			'wi-gale-warning'      => __( 'Gale Warning', 'simple-location' ),
			'wi-hail'              => __( 'Hail', 'simple-location' ),
			'wi-hurricane'         => __( 'Hurricane', 'simple-location' ),
			'wi-hurricane-warning' => __( 'Hurricane Warning', 'simple-location' ),
			'wi-dust'              => __( 'Dust', 'simple-location' ),
			'wi-earthquake'        => __( 'Earthquake', 'simple-location' ),
			'wi-fire'              => __( 'Fire', 'simple-location' ),
			'wi-flood'             => __( 'Flood', 'simple-location' ),
			'fa-cloudy-meatball'   => __( 'Cloudy with a Chance of Meatballs', 'simple-location' ),
			'fa-icy'               => __( 'Icy', 'simple-location' ),
			'fa-smog'              => __( 'Smog( Alt )', 'simple-location' ),
		);

		$day   = array(
			'wi-day-sunny'             => __( 'Sunny', 'simple-location' ),
			'wi-day-sunny-overcast'    => __( 'Sunny and Overcast', 'simple-location' ),
			'wi-day-cloudy'            => __( 'Cloudy - Daytime', 'simple-location' ),
			'wi-day-cloudy-gusts'      => __( 'Cloudy with Gusts - Daytime', 'simple-location' ),
			'wi-day-cloudy-high'       => __( 'Cloudy High Winds - Daytime', 'simple-location' ),
			'wi-day-cloudy-windy'      => __( 'Cloudy and Windy - Daytime', 'simple-location' ),
			'wi-day-fog'               => __( 'Fog - Daytime', 'simple-location' ),
			'wi-day-hail'              => __( 'Hail - Daytime', 'simple-location' ),
			'wi-day-haze'              => __( 'Haze - Daytime', 'simple-location' ),
			'wi-day-lightning'         => __( 'Lightning - Daytime', 'simple-location' ),
			'wi-day-light-wind'        => __( 'Lighting and Wind - Daytime', 'simple-location' ),
			'wi-day-rain-mix'          => __( 'Rainy Mix - Daytime', 'simple-location' ),
			'wi-day-rain'              => __( 'Rain - Daytime', 'simple-location' ),
			'wi-day-rain-wind'         => __( 'Rain and Wind - Daytime', 'simple-location' ),
			'wi-day-showers'           => __( 'Showers - Day', 'simple-location' ),
			'wi-day-sleet-storm'       => __( 'Sleet Storm - Day', 'simple-location' ),
			'wi-day-sleet'             => __( 'Sleet - Day', 'simple-location' ),
			'wi-day-snow'              => __( 'Snow - Day', 'simple-location' ),
			'wi-day-snow-thunderstorm' => __( 'Snow and Thunderstorms - Day', 'simple-location' ),
			'wi-day-snow-wind'         => __( 'Snow and Wind - Day', 'simple-location' ),
			'wi-day-sprinkle'          => __( 'Sprinkles - Day', 'simple-location' ),
			'wi-day-storm-showers'     => __( 'Storm Showers - Day', 'simple-location' ),
			'wi-day-thunderstorm'      => __( 'Thunderstorm - Day', 'simple-location' ),
			'wi-day-windy'             => __( 'Windy - Day', 'simple-location' ),
		);
		$night = array(
			'wi-night-clear'             => __( 'Clear Night', 'simple-location' ),
			'wi-night-cloudy'            => __( 'Cloudy - Night', 'simple-location' ),
			'wi-night-cloudy-gusts'      => __( 'Cloudy with Gusts - Night', 'simple-location' ),
			'wi-night-cloudy-high'       => __( 'Cloudy with High Winds - Night', 'simple-location' ),
			'wi-night-cloudy-windy'      => __( 'Cloudy and Windy - Night', 'simple-location' ),
			'wi-night-fog'               => __( 'Fog - Night', 'simple-location' ),
			'wi-night-hail'              => __( 'Hail - Night', 'simple-location' ),
			'wi-night-lightning'         => __( 'Lightning - Night', 'simple-location' ),
			'wi-night-partly-cloudy'     => __( 'Partly Cloudy - Night', 'simple-location' ),
			'wi-night-rain-mix'          => __( 'Rainy Mix - Night', 'simple-location' ),
			'wi-night-rain'              => __( 'Rain - Night', 'simple-location' ),
			'wi-night-rain-wind'         => __( 'Rain and Wind - Night', 'simple-location' ),
			'wi-night-showers'           => __( 'Showers - Night', 'simple-location' ),
			'wi-night-sleet-storm'       => __( 'Sleet Storm - Night', 'simple-location' ),
			'wi-night-sleet'             => __( 'Sleet - Night', 'simple-location' ),
			'wi-night-snow'              => __( 'Snow - Night', 'simple-location' ),
			'wi-night-snow-thunderstorm' => __( 'Snow and Thunderstorm - Night', 'simple-location' ),
			'wi-night-snow-wind'         => __( 'Snow and Wind - Night', 'simple-location' ),
			'wi-night-sprinkle'          => __( 'Sprinkles - Night', 'simple-location' ),
			'wi-night-storm-showers'     => __( 'Storm Showers - Night', 'simple-location' ),
			'wi-night-thunderstorm'      => __( 'Thunderstorms - Night', 'simple-location' ),
			'wi-lunar-eclipse'           => __( 'Lunar Eclipse', 'simple-location' ),
		);
		$misc  = array(
			'wi-barometer'            => __( 'Barometer', 'simple-location' ),
			'wi-barometer-sea-level'  => __( 'Barometer(Sea Level)', 'simple-location' ),
			'wi-thermometer'          => __( 'Thermometer', 'simple-location' ),
			'wi-thermometer-exterior' => __( 'Thermometer - Exterior', 'simple-location' ),
			'wi-thermometer-internal' => __( 'Thermometer - Internal', 'simple-location' ),
			'wi-celsius'              => __( 'Celsius', 'simple-location' ),
			'wi-fahrenheit'           => __( 'Fahrenheit', 'simple-location' ),
			'wi-humidity'             => __( 'Humidity', 'simple-location' ),
			'wi-degrees'              => __( 'Degrees', 'simple-location' ),
			'wi-raindrops'            => __( 'Raindrops', 'simple-location' ),
			'wi-raindrop'             => __( 'Raindrop', 'simple-location' ),
			'wi-horizon'              => __( 'Horizon', 'simple-location' ),
			'wi-na'                   => __( 'N/A', 'simple-location' ),
			'wi-sunrise'              => __( 'Sunrise', 'simple-location' ),
			'wi-sunset'               => __( 'Sunset', 'simple-location' ),
			'wi-umbrella'             => __( 'Umbrella', 'simple-location' ),
			'wi-meteor'               => __( 'Meteor', 'simple-location' ),
			'wi-tornado'              => __( 'Tornado', 'simple-location' ),
			'wi-tsunami'              => __( 'Tsunami', 'simple-location' ),
			'wi-volcano'              => __( 'Volcano', 'simple-location' ),
		);

		$moon = array(
			'wi-moon-first-quarter'     => __( 'First Quarter Moon', 'simple-location' ),
			'wi-moon-full'              => __( 'Full Moon', 'simple-location' ),
			'wi-moon-new'               => __( 'New Moon', 'simple-location' ),
			'wi-moonrise'               => __( 'Moonrise', 'simple-location' ),
			'wi-moonset'                => __( 'Moonset', 'simple-location' ),
			'wi-moon-third-quarter'     => __( 'Third Quarter Moon', 'simple-location' ),
			'wi-moon-waning-crescent-1' => __( 'Waning Crescent 1', 'simple-location' ),
			'wi-moon-waning-crescent-2' => __( 'Waning Crescent 2', 'simple-location' ),
			'wi-moon-waning-crescent-3' => __( 'Waning Crescent 3', 'simple-location' ),
			'wi-moon-waning-crescent-4' => __( 'Waning Crescent 4', 'simple-location' ),
			'wi-moon-waning-crescent-5' => __( 'Waning Crescent 5', 'simple-location' ),
			'wi-moon-waning-crescent-6' => __( 'Waning Crescent 6', 'simple-location' ),
			'wi-moon-waning-gibbous-1'  => __( 'Waning Gibbous 1', 'simple-location' ),
			'wi-moon-waning-gibbous-2'  => __( 'Waning Gibbous 2', 'simple-location' ),
			'wi-moon-waning-gibbous-3'  => __( 'Waning Gibbous 3', 'simple-location' ),
			'wi-moon-waning-gibbous-4'  => __( 'Waning Gibbous 4', 'simple-location' ),
			'wi-moon-waning-gibbous-5'  => __( 'Waning Gibbous 5', 'simple-location' ),
			'wi-moon-waning-gibbous-6'  => __( 'Waning Gibbous 6', 'simple-location' ),
			'wi-moon-waxing-crescent-1' => __( 'Waxing Crescent 1', 'simple-location' ),
			'wi-moon-waxing-crescent-2' => __( 'Waxing Crescent 2', 'simple-location' ),
			'wi-moon-waxing-crescent-3' => __( 'Waxing Crescent 3', 'simple-location' ),
			'wi-moon-waxing-crescent-4' => __( 'Waxing Crescent 4', 'simple-location' ),
			'wi-moon-waxing-crescent-5' => __( 'Waxing Crescent 5', 'simple-location' ),
			'wi-moon-waxing-crescent-6' => __( 'Waxing Crescent 6', 'simple-location' ),
			'wi-moon-waxing-gibbous-1'  => __( 'Waxing Gibbous 1', 'simple-location' ),
			'wi-moon-waxing-gibbous-2'  => __( 'Waxing Gibbous 2', 'simple-location' ),
			'wi-moon-waxing-gibbous-3'  => __( 'Waxing Gibbous 3', 'simple-location' ),
			'wi-moon-waxing-gibbous-4'  => __( 'Waxing Gibbous 4', 'simple-location' ),
			'wi-moon-waxing-gibbous-5'  => __( 'Waxing Gibbous 5', 'simple-location' ),
			'wi-moon-waxing-gibbous-6'  => __( 'Waxing Gibbous 6', 'simple-location' ),
		);
		return array_merge( $neutral, $day, $night, $misc, $moon );
	}

	/**
	 * Generates Pulldown list of Icons.
	 *
	 * @param string  $icon Icon to be Selected.
	 * @param boolean $echo Echo or Return.
	 * @return string|void The options, or nothing when they are echoed.
	 */
	public static function icon_select( $icon, $echo = false ) {
		$choices = self::get_iconlist();
		if ( ! $icon ) {
			$icon = 'none';
		}
		$choices    = array_merge( array( 'none' => esc_html__( 'None', 'simple-location' ) ), $choices );
			$return = '';
		foreach ( $choices as $value => $text ) {
			$return .= sprintf( '<option value="%1s" %2s>%3s</option>', esc_attr( $value ), selected( $icon, $value, false ), esc_html( $text ) );
		}
		if ( ! $echo ) {
			return $return;
		}
		echo wp_kses(
			$return,
			array(
				'option' => array(
					'value'    => array(),
					'selected' => array(),
				),
			)
		);
	}

	/**
	 * Decimal places the editor shows of each weather field, in metric and imperial units: those weather stations
	 * usually report.
	 *
	 * Stored values keep their precision; see Geo_Base::save_meta().
	 *
	 * @since 6.1.0
	 * @var int[][]
	 */
	const FIELD_PRECISION = array(
		'temperature' => array( 1, 1 ), // 0.1 °C or °F.
		'humidity'    => array( 0, 0 ), // 1 %.
		'pressure'    => array( 1, 2 ), // 0.1 hPa or 0.01 inHg.
		'winddegree'  => array( 0, 0 ), // 1 degree.
		'windspeed'   => array( 1, 1 ), // 0.1 m/s or mph.
		'windgust'    => array( 1, 1 ),
		'cloudiness'  => array( 0, 0 ), // 1 %.
		'visibility'  => array( 0, 1 ), // 1 m or 0.1 mile.
		'rain'        => array( 1, 2 ), // 0.1 mm or 0.01 inch.
		'snow'        => array( 1, 1 ), // 0.1 mm or inch.
	);

	/**
	 * Returns the decimal places the editor shows of a weather field.
	 *
	 * @since 6.1.0
	 *
	 * @param string $field    Field, such as 'humidity'.
	 * @param bool   $imperial Whether the units are imperial.
	 * @return int Decimal places. Default 2.
	 */
	public static function field_precision( $field, $imperial = false ) {
		return self::FIELD_PRECISION[ $field ][ $imperial ? 1 : 0 ] ?? 2;
	}

	/**
	 * Returns the step of a weather field's number input, for its decimal places.
	 *
	 * @since 6.1.0
	 *
	 * @param string $field    Field, such as 'humidity'.
	 * @param bool   $imperial Whether the units are imperial.
	 * @return string Step, such as '1' or '0.1'.
	 */
	public static function field_step( $field, $imperial = false ) {
		$precision = self::field_precision( $field, $imperial );
		return 0 === $precision ? '1' : '0.' . str_repeat( '0', $precision - 1 ) . '1';
	}

	/**
	 * Convert array of metric to Imperial Measurements.
	 *
	 * @since 6.1.0 Converts the rain rate too.
	 *
	 * @param array $conditions Conditions.
	 * @return array {
	 *  Below outlines only the datapoints that will be converted.
	 *  @type float $temperature Temperature in Fahrenheit.
	 *  @type float $heatindex Heat Index in Fahrenheit.
	 *  @type float $windchill Wind Chill in Fahrenheit.
	 *  @type float $dewpoint Dewpoint in Fahrenheit.
	 *  @type float $pressure Atomospheric Pressure at mean sea level in inHg.
	 *  @type array $windspeed Speed in miles per hour
	 *  @type int $windgust Wind Gust in miles per hour.
	 *  @type float $rain Rainfall in inches for the last hour.
	 *  @type float $snow Snowfall in inches for the last hour.
	 *  @type float $visibility Visibility in miles.
	 * }
	 */
	public static function metric_to_imperial( $conditions ) {
		if ( ! is_array( $conditions ) ) {
			return $conditions;
		}
		foreach ( array( 'temperature', 'heatindex', 'windchill', 'dewpoint' ) as $temp ) {
			if ( array_key_exists( $temp, $conditions ) && is_numeric( $conditions[ $temp ] ) ) {
				$conditions[ $temp ] = self::celsius_to_fahrenheit( $conditions[ $temp ] );
			}
		}

		if ( array_key_exists( 'pressure', $conditions ) && is_numeric( $conditions['pressure'] ) ) {
			$conditions['pressure'] = self::hpa_to_inhg( $conditions['pressure'] );
		}
		if ( array_key_exists( 'visibility', $conditions ) && is_numeric( $conditions['visibility'] ) ) {
			$conditions['visibility'] = self::meters_to_miles( $conditions['visibility'] );
		}

		foreach ( array( 'windspeed', 'windgust' ) as $speed ) {
			if ( array_key_exists( $speed, $conditions ) && is_numeric( $conditions[ $speed ] ) ) {
				$conditions[ $speed ] = self::mps_to_miph( $conditions[ $speed ] );
			}
		}

		// A rate per hour converts like an amount.
		foreach ( array( 'rain', 'snow', 'rainrate' ) as $fall ) {
			if ( array_key_exists( $fall, $conditions ) && is_numeric( $conditions[ $fall ] ) ) {
				$conditions[ $fall ] = self::mm_to_inches( $conditions[ $fall ] );
			}
		}

		return $conditions;
	}

	/**
	 * Convert array of imperial to metric measurements.
	 *
	 * @since 6.1.0 Converts the rain rate too.
	 *
	 * @param array $conditions Conditions.
	 * @return array $conditions
	 */
	public static function imperial_to_metric( $conditions ) {
		if ( ! is_array( $conditions ) ) {
			return $conditions;
		}
		foreach ( array( 'temperature', 'heatindex', 'windchill', 'dewpoint' ) as $temp ) {
			if ( array_key_exists( $temp, $conditions ) ) {
				$conditions[ $temp ] = self::fahrenheit_to_celsius( $conditions[ $temp ] );
			}
		}

		if ( array_key_exists( 'pressure', $conditions ) ) {
			$conditions['pressure'] = self::inhg_to_hpa( $conditions['pressure'] );
		}
		if ( array_key_exists( 'visibility', $conditions ) ) {
			$conditions['visibility'] = self::miles_to_meters( $conditions['visibility'] );
		}

		foreach ( array( 'windspeed', 'windgust' ) as $speed ) {
			if ( array_key_exists( $speed, $conditions ) ) {
				$conditions[ $speed ] = self::miph_to_mps( $conditions[ $speed ] );
			}
		}

		// A rate per hour converts like an amount.
		foreach ( array( 'rain', 'snow', 'rainrate' ) as $fall ) {
			if ( array_key_exists( $fall, $conditions ) ) {
				$conditions[ $fall ] = self::inches_to_mm( $conditions[ $fall ] );
			}
		}

		return $conditions;
	}
}
