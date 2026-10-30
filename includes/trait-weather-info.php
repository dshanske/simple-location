<?php
/**
 * Weather Info Helper Trait.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Unit conversions, labels and condition codes for weather data.
 *
 * @since 5.0.0
 */
trait Weather_Info_Trait {

	/**
	 * Converts celsius to fahrenheit.
	 *
	 * @param float $temp Temperature in Celsius.
	 * @return float Temperature in Fahrenheit.
	 */
	public static function celsius_to_fahrenheit( $temp ) {
		return round( ( $temp * 9 / 5 ) + 32, 2 );
	}

	/**
	 * Converts fahrenheit to celsius.
	 *
	 * @param float $temp Temperature in Fahrenheit.
	 * @return float Temperature in Celsius.
	 */
	public static function fahrenheit_to_celsius( $temp ) {
		return round( ( $temp - 32 ) / 1.8, 2 );
	}

	/**
	 * Magnus formula constant a, from Alduchov and Eskridge (1996).
	 *
	 * @since 6.1.0
	 * @var float
	 */
	protected static $magnus_a = 17.625;

	/**
	 * Magnus formula constant b, in Celsius, from Alduchov and Eskridge (1996).
	 *
	 * @since 6.1.0
	 * @var float
	 */
	protected static $magnus_b = 243.04;

	/**
	 * Calculates the dew point from the temperature and relative humidity, with the Magnus formula.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $temperature Air temperature in Celsius.
	 * @param mixed $humidity    Relative humidity in percent.
	 * @return float|null Dew point in Celsius, to one decimal place, or null when an input is missing or the humidity
	 *                    is 0 or less.
	 */
	public static function dew_point( $temperature, $humidity ) {
		if ( ! is_numeric( $temperature ) || ! is_numeric( $humidity ) || (float) $humidity <= 0 ) {
			return null;
		}
		$a     = self::$magnus_a;
		$b     = self::$magnus_b;
		$t     = (float) $temperature;
		$gamma = log( min( 100.0, (float) $humidity ) / 100 ) + $a * $t / ( $b + $t );
		return round( $b * $gamma / ( $a - $gamma ), 1 );
	}

	/**
	 * Calculates the relative humidity from the temperature and dew point, with the Magnus formula.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $temperature Air temperature in Celsius.
	 * @param mixed $dewpoint    Dew point in Celsius.
	 * @return float|null Relative humidity in percent, to one decimal place and at most 100, or null when an input is
	 *                    missing.
	 */
	public static function relative_humidity( $temperature, $dewpoint ) {
		if ( ! is_numeric( $temperature ) || ! is_numeric( $dewpoint ) ) {
			return null;
		}
		$a = self::$magnus_a;
		$b = self::$magnus_b;
		$t = (float) $temperature;
		$d = (float) $dewpoint;
		return round( min( 100.0, 100 * exp( $a * $d / ( $b + $d ) - $a * $t / ( $b + $t ) ) ), 1 );
	}

	/**
	 * Calculates the wind chill, with the 2001 formula of the US National Weather Service and Environment Canada.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $temperature Air temperature in Celsius.
	 * @param mixed $windspeed   Wind speed at 10 meters, in meters per second.
	 * @return float|null Wind chill in Celsius, to one decimal place, or null when an input is missing or outside the
	 *                    formula's range: 10 °C or colder, with wind faster than 4.8 km/h.
	 */
	public static function wind_chill( $temperature, $windspeed ) {
		if ( ! is_numeric( $temperature ) || ! is_numeric( $windspeed ) ) {
			return null;
		}
		$temperature = (float) $temperature;
		$kmh         = (float) $windspeed * 3.6;
		if ( $temperature > 10 || $kmh <= 4.8 ) {
			return null;
		}
		$factor = pow( $kmh, 0.16 );
		return round( 13.12 + 0.6215 * $temperature - 11.37 * $factor + 0.3965 * $temperature * $factor, 1 );
	}

	/**
	 * Calculates the heat index, with the method of the US National Weather Service.
	 *
	 * The Rothfusz regression is used, with the National Weather Service's adjustments for very dry and very humid air,
	 * where Steadman's simpler formula gives 80 °F or more.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $temperature Air temperature in Celsius.
	 * @param mixed $humidity    Relative humidity in percent.
	 * @return float|null Heat index in Celsius, to one decimal place, or null when an input is missing or the air is
	 *                    cooler than 80 °F (26.7 °C), where the heat index isn't used.
	 */
	public static function heat_index( $temperature, $humidity ) {
		if ( ! is_numeric( $temperature ) || ! is_numeric( $humidity ) ) {
			return null;
		}
		$t  = (float) $temperature * 9 / 5 + 32;
		$rh = max( 0.0, min( 100.0, (float) $humidity ) );
		if ( $t < 80 ) {
			return null;
		}
		$index = 0.5 * ( $t + 61 + ( $t - 68 ) * 1.2 + $rh * 0.094 );
		if ( ( $index + $t ) / 2 >= 80 ) {
			$index = -42.379 + 2.04901523 * $t + 10.14333127 * $rh - 0.22475541 * $t * $rh - 0.00683783 * $t * $t
				- 0.05481717 * $rh * $rh + 0.00122874 * $t * $t * $rh + 0.00085282 * $t * $rh * $rh
				- 0.00000199 * $t * $t * $rh * $rh;
			if ( $rh < 13 && $t <= 112 ) {
				$index -= ( ( 13 - $rh ) / 4 ) * sqrt( ( 17 - abs( $t - 95 ) ) / 17 );
			} elseif ( $rh > 85 && $t <= 87 ) {
				$index += ( ( $rh - 85 ) / 10 ) * ( ( 87 - $t ) / 5 );
			}
		}
		return round( ( $index - 32 ) * 5 / 9, 1 );
	}

	/**
	 * Converts hPa to inHg.
	 *
	 * @since 6.0.0 Uses 0.02953 inHg per hPa; it used 0.03, which was 1.6% high.
	 *
	 * @param float $hpa HectoPascals.
	 * @return float Inches of Mercury.
	 */
	public static function hpa_to_inhg( $hpa ) {
		return round( $hpa * 0.02953, 2 );
	}

	/**
	 * Converts inHg to hPa
	 *
	 * @since 6.0.0 Uses 0.02953 inHg per hPa; it used 0.03.
	 *
	 * @param float $inhg Inches of Mercury.
	 * @return float HectoPascals.
	 */
	public static function inhg_to_hpa( $inhg ) {
		return round( $inhg / 0.02953, 2 );
	}

	/**
	 * Returns the temperature unit for the measurement setting.
	 *
	 * @since 3.3.1
	 *
	 * @return string 'F' for imperial, otherwise 'C'.
	 */
	public static function temp_unit() {
		switch ( get_option( 'sloc_measurements' ) ) {
			case 'imperial':
				return 'F';
			default:
				return 'C';
		}
	}

	/**
	 * Return the marked up icon standardized to the fonts.
	 *
	 * @param string $icon Name of Icon.
	 * @param string $summary Description of Icon. Optional.
	 * @return string marked up icon
	 *
	 * @since 6.0.0 Only loads icons from svgs/.
	 */
	public static function get_icon( $icon, $summary = null ) {
		if ( 'none' === $icon ) {
			return '';
		}
		if ( ! $summary ) {
			$summary = $icon;
		}
		// Only a file name, so the icon can't point outside svgs/.
		$svg = sprintf( '%1$ssvgs/%2$s.svg', plugin_dir_path( __DIR__ ), basename( (string) $icon ) );
		if ( sloc_file_exists( $svg ) ) {
			return PHP_EOL . sprintf( '<span class="sloc-weather-icon sloc-icon-%1$s" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $icon ), esc_attr( $summary ), sloc_get_contents( $svg ) );
		}
		return '';
	}


	/**
	 * Returns the unit, name and description based on the requested property.
	 *
	 * @since 6.2.0 Adds the VOC and NOx indices, the snow depth and the snow rate.
	 * @since 6.3.0 Adds the apparent temperature, the cloud base, and the lightning strikes and their distance.
	 *
	 * @param string|null $property Property to return. Null will return entire array.
	 * @param boolean     $imperial Whether imperial or default units.
	 *
	 * @return array {
	 *  @type string $unit Unit of Measurement.
	 *  @type string $label Label for Measurement.
	 *  @type string $name Name for Property.
	 *  @type string $icon Icon for Property.
	 * }
	 */
	public static function get_names( $property, $imperial = false ) {
		$defaults = array(
			'unit'  => '',
			'label' => '',
			'name'  => '',
			'icon'  => '',
		);
		$units    = array(
			'temperature'        => array(
				'unit'  => __( '&deg;C', 'simple-location' ),
				'label' => __( 'degrees celsius', 'simple-location' ),
				'name'  => __( 'Temperature', 'simple-location' ),
				'icon'  => 'wi-thermometer',
			),
			'windchill'          => array(
				'unit'  => __( '&deg;C', 'simple-location' ),
				'label' => __( 'degrees celsius', 'simple-location' ),
				'name'  => __( 'Wind Chill', 'simple-location' ),
				'icon'  => 'wi-thermometer',
			),
			'heatindex'          => array(
				'unit'  => __( '&deg;C', 'simple-location' ),
				'label' => __( 'degrees celsius', 'simple-location' ),
				'name'  => __( 'Heat Index', 'simple-location' ),
				'icon'  => 'wi-thermometer',
			),
			'apptemp'            => array(
				'unit'  => __( '&deg;C', 'simple-location' ),
				'label' => __( 'degrees celsius', 'simple-location' ),
				'name'  => __( 'Feels Like', 'simple-location' ),
				'icon'  => 'wi-thermometer',
			),
			'dewpoint'           => array(
				'unit'  => __( '&deg;C', 'simple-location' ),
				'label' => __( 'degrees celsius', 'simple-location' ),
				'name'  => __( 'Dewpoint', 'simple-location' ),
				'icon'  => 'wi-thermometer',
			),
			'humidity'           => array(
				'unit'  => __( '%', 'simple-location' ),
				'label' => __( 'percent', 'simple-location' ),
				'name'  => __( 'Humidity', 'simple-location' ),
				'icon'  => 'wi-humidity',
			),
			'pressure'           => array(
				'unit'  => __( 'hPa', 'simple-location' ),
				'label' => __( 'hectopascals', 'simple-location' ),
				'name'  => __( 'Pressure', 'simple-location' ),
				'icon'  => 'wi-barometer',
			),
			'cloudiness'         => array(
				'unit'  => __( '%', 'simple-location' ),
				'label' => __( 'percent', 'simple-location' ),
				'name'  => __( 'Cloudiness', 'simple-location' ),
				'icon'  => 'wi-cloudy',
			),
			'windspeed'          => array(
				'unit'  => __( 'mps', 'simple-location' ),
				'label' => __( 'meters per second', 'simple-location' ),
				'name'  => __( 'Wind Speed', 'simple-location' ),
				'icon'  => 'wi-windy',
			),
			'windgust'           => array(
				'unit'  => __( 'mps', 'simple-location' ),
				'label' => __( 'meters per second', 'simple-location' ),
				'name'  => __( 'Wind Gust', 'simple-location' ),
				'icon'  => 'wi-windy',
			),
			'winddegree'         => array(
				'unit'  => __( '&deg;', 'simple-location' ),
				'label' => __( 'degrees', 'simple-location' ),
				'name'  => __( 'Wind Direction', 'simple-location' ),
				'icon'  => 'wi-windy',
			),
			'rain'               => array(
				'unit'  => __( 'mm', 'simple-location' ),
				'label' => __( 'millimeters in the last hour', 'simple-location' ),
				'name'  => __( 'Rainfall', 'simple-location' ),
				'icon'  => 'wi-rain',
			),
			'rainrate'           => array(
				'unit'  => __( 'mm/h', 'simple-location' ),
				'label' => __( 'millimeters per hour', 'simple-location' ),
				'name'  => __( 'Rain Rate', 'simple-location' ),
				'icon'  => 'wi-raindrops',
			),
			'snowrate'           => array(
				'unit'  => __( 'mm/h', 'simple-location' ),
				'label' => __( 'millimeters per hour', 'simple-location' ),
				'name'  => __( 'Snow Rate', 'simple-location' ),
				'icon'  => 'wi-snow',
			),
			'snow'               => array(
				'unit'  => __( 'mm', 'simple-location' ),
				'label' => __( 'millimeters in the last hour', 'simple-location' ),
				'name'  => __( 'Snowfall', 'simple-location' ),
				'icon'  => 'wi-snow',
			),
			'snowdepth'          => array(
				'unit'  => __( 'mm', 'simple-location' ),
				'label' => __( 'millimeters on the ground', 'simple-location' ),
				'name'  => __( 'Snow Depth', 'simple-location' ),
				'icon'  => 'wi-snowflake-cold',
			),
			'visibility'         => array(
				'unit'  => __( 'm', 'simple-location' ),
				'label' => __( 'meters', 'simple-location' ),
				'name'  => __( 'Visibility', 'simple-location' ),
				'icon'  => 'wi-visibility',
			),
			'cloudbase'          => array(
				'unit'  => __( 'm', 'simple-location' ),
				'label' => __( 'meters above the ground', 'simple-location' ),
				'name'  => __( 'Cloud Base', 'simple-location' ),
				'icon'  => 'wi-cloud-up',
			),
			'lightning_strikes'  => array(
				'unit'  => '',
				'label' => __( 'strikes in the last hour', 'simple-location' ),
				'name'  => __( 'Lightning Strikes', 'simple-location' ),
				'icon'  => 'wi-lightning',
			),
			'lightning_distance' => array(
				'unit'  => __( 'km', 'simple-location' ),
				'label' => __( 'kilometers to the latest strike', 'simple-location' ),
				'name'  => __( 'Lightning Distance', 'simple-location' ),
				'icon'  => 'wi-lightning',
			),
			'radiation'          => array(
				'unit'  => __( 'W/m^2', 'simple-location' ),
				'label' => __( 'watts per square meter', 'simple-location' ),
				'name'  => __( 'Solar Radiation', 'simple-location' ),
				'icon'  => 'fa-radiation',
			),
			'illuminance'        => array(
				'unit'  => __( 'lx', 'simple-location' ),
				'label' => __( 'lux', 'simple-location' ),
				'name'  => __( 'Illuminance', 'simple-location' ),
				'icon'  => 'sa-sun',
			),
			'uv'                 => array(
				'unit'  => '',
				'label' => __( 'index', 'simple-location' ),
				'name'  => __( 'UV Index', 'simple-location' ),
				'icon'  => 'wi-uv',
			),
			'aqi'                => array(
				'unit'  => '',
				'label' => __( 'index', 'simple-location' ),
				'name'  => __( 'Air Quality Index', 'simple-location' ),
				'icon'  => 'md-aqi',
			),
			'pm1_0'              => array(
				'unit'  => __( 'µg/m3', 'simple-location' ),
				'label' => __( 'micrograms per cubic meter of air', 'simple-location' ),
				'name'  => __( 'PM1.0', 'simple-location' ),
				'icon'  => 'wi-dust',
			),

			'pm2_5'              => array(
				'unit'  => __( 'µg/m3', 'simple-location' ),
				'label' => __( 'micrograms per cubic meter of air', 'simple-location' ),
				'name'  => __( 'PM2.5', 'simple-location' ),
				'icon'  => 'wi-dust',
			),
			'pm10_0'             => array(
				'unit'  => __( 'µg/m3', 'simple-location' ),
				'label' => __( 'micrograms per cubic meter of air', 'simple-location' ),
				'name'  => __( 'PM10', 'simple-location' ),
				'icon'  => 'wi-dust',
			),
			'co'                 => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Carbon Monoxide', 'simple-location' ),
			),
			'co2'                => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Carbon Dioxide', 'simple-location' ),
			),
			'nh3'                => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Ammonia', 'simple-location' ),
			),
			'o3'                 => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Ozone', 'simple-location' ),
			),
			'pb'                 => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Lead', 'simple-location' ),
			),
			'so2'                => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Sulfur Dioxide', 'simple-location' ),
			),
			// Sensirion gas indices, 1 to 500: for VOC, 100 is the sensor's recent average; for NOx, 1 is the baseline.
			'voc_index'          => array(
				'unit'  => '',
				'label' => __( 'index', 'simple-location' ),
				'name'  => __( 'VOC Index', 'simple-location' ),
				'icon'  => 'md-aqi',
			),
			'nox_index'          => array(
				'unit'  => '',
				'label' => __( 'index', 'simple-location' ),
				'name'  => __( 'NOx Index', 'simple-location' ),
				'icon'  => 'md-aqi',
			),
		);

			$iunits = array(
				'temperature'        => array(
					'unit'  => __( '&deg;F', 'simple-location' ),
					'label' => __( 'degrees fahrenheit', 'simple-location' ),
					'name'  => __( 'Temperature', 'simple-location' ),
					'icon'  => 'wi-thermometer',
				),
				'windchill'          => array(
					'unit'  => __( '&deg;F', 'simple-location' ),
					'label' => __( 'degrees fahrenheit', 'simple-location' ),
					'name'  => __( 'Wind Chill', 'simple-location' ),
					'icon'  => 'wi-thermometer',
				),
				'heatindex'          => array(
					'unit'  => __( '&deg;F', 'simple-location' ),
					'label' => __( 'degrees fahrenheit', 'simple-location' ),
					'name'  => __( 'Heat Index', 'simple-location' ),
					'icon'  => 'wi-thermometer',
				),
				'apptemp'            => array(
					'unit'  => __( '&deg;F', 'simple-location' ),
					'label' => __( 'degrees fahrenheit', 'simple-location' ),
					'name'  => __( 'Feels Like', 'simple-location' ),
					'icon'  => 'wi-thermometer',
				),
				'dewpoint'           => array(
					'unit'  => __( '&deg;F', 'simple-location' ),
					'label' => __( 'degrees fahrenheit', 'simple-location' ),
					'name'  => __( 'Dewpoint', 'simple-location' ),
					'icon'  => 'wi-thermometer',
				),
				'pressure'           => array(
					'unit'  => __( 'inHg', 'simple-location' ),
					'label' => __( 'inches of mercury', 'simple-location' ),
					'name'  => __( 'Pressure', 'simple-location' ),
					'icon'  => 'wi-barometer',
				),
				'windspeed'          => array(
					'unit'  => __( 'MPH', 'simple-location' ),
					'label' => __( 'miles per hour', 'simple-location' ),
					'name'  => __( 'Wind Speed', 'simple-location' ),
					'icon'  => 'wi-windy',
				),
				'windgust'           => array(
					'unit'  => __( 'MPH', 'simple-location' ),
					'label' => __( 'miles per hour', 'simple-location' ),
					'name'  => __( 'Wind Gust', 'simple-location' ),
					'icon'  => 'wi-windy',
				),
				'winddegree'         => array(
					'unit'  => __( '&deg;', 'simple-location' ),
					'label' => __( 'degree', 'simple-location' ),
					'name'  => __( 'Wind Direction', 'simple-location' ),
					'icon'  => 'wi-windy',
				),

				'rain'               => array(
					'unit'  => __( 'in', 'simple-location' ),
					'label' => __( 'inches in the last hour', 'simple-location' ),
					'name'  => __( 'Rainfall', 'simple-location' ),
					'icon'  => 'wi-rain',
				),
				'rainrate'           => array(
					'unit'  => __( 'in/h', 'simple-location' ),
					'label' => __( 'inches per hour', 'simple-location' ),
					'name'  => __( 'Rain Rate', 'simple-location' ),
					'icon'  => 'wi-raindrops',
				),
				'snowrate'           => array(
					'unit'  => __( 'in/h', 'simple-location' ),
					'label' => __( 'inches per hour', 'simple-location' ),
					'name'  => __( 'Snow Rate', 'simple-location' ),
					'icon'  => 'wi-snow',
				),
				'snow'               => array(
					'unit'  => __( 'in', 'simple-location' ),
					'label' => __( 'inches in the last hour', 'simple-location' ),
					'name'  => __( 'Snowfall', 'simple-location' ),
					'icon'  => 'wi-snow',
				),
				'snowdepth'          => array(
					'unit'  => __( 'in', 'simple-location' ),
					'label' => __( 'inches on the ground', 'simple-location' ),
					'name'  => __( 'Snow Depth', 'simple-location' ),
					'icon'  => 'wi-snowflake-cold',
				),
				'visibility'         => array(
					'unit'  => __( 'mi', 'simple-location' ),
					'label' => __( 'miles', 'simple-location' ),
					'name'  => __( 'Visibility', 'simple-location' ),
					'icon'  => 'wi-visibility',
				),
				'cloudbase'          => array(
					'unit'  => __( 'ft', 'simple-location' ),
					'label' => __( 'feet above the ground', 'simple-location' ),
					'name'  => __( 'Cloud Base', 'simple-location' ),
					'icon'  => 'wi-cloud-up',
				),
				'lightning_distance' => array(
					'unit'  => __( 'mi', 'simple-location' ),
					'label' => __( 'miles to the latest strike', 'simple-location' ),
					'name'  => __( 'Lightning Distance', 'simple-location' ),
					'icon'  => 'wi-lightning',
				),
			);
			$iunits = array_merge( $units, $iunits );

			$return = $imperial ? $iunits : $units;
			if ( is_null( $property ) ) {
				return $return;
			}
			if ( array_key_exists( $property, $return ) ) {
				return wp_parse_args( $return[ $property ], $defaults );
			}
			return false;
	}

	/**
	 * Returns the category of an air quality index on the US EPA scale: its name and colors.
	 *
	 * The US EPA's categories and colors, as AirNow shows them. The text color is black on the three lightest
	 * colors and white on the others, so the name stays readable on its category's color.
	 *
	 * @since 6.2.0
	 *
	 * @param mixed $aqi Air quality index.
	 * @return array|null {
	 *     The category, or null without an index.
	 *
	 *     @type string $slug       Category slug, such as 'moderate', for a CSS class.
	 *     @type string $name       Category name, translated.
	 *     @type string $color      Category color, as #rrggbb.
	 *     @type string $text_color Readable text color on the category color, as #rrggbb.
	 * }
	 */
	public static function aqi_category( $aqi ) {
		if ( ! is_numeric( $aqi ) || $aqi < 0 ) {
			return null;
		}
		$aqi        = (int) round( (float) $aqi );
		$categories = array(
			array( 50, 'good', __( 'Good', 'simple-location' ), '#00e400', '#000000' ),
			array( 100, 'moderate', __( 'Moderate', 'simple-location' ), '#ffff00', '#000000' ),
			array( 150, 'unhealthy-sensitive', __( 'Unhealthy for Sensitive Groups', 'simple-location' ), '#ff7e00', '#000000' ),
			array( 200, 'unhealthy', __( 'Unhealthy', 'simple-location' ), '#ff0000', '#ffffff' ),
			array( 300, 'very-unhealthy', __( 'Very Unhealthy', 'simple-location' ), '#8f3f97', '#ffffff' ),
			array( PHP_INT_MAX, 'hazardous', __( 'Hazardous', 'simple-location' ), '#7e0023', '#ffffff' ),
		);
		foreach ( $categories as $category ) {
			if ( $aqi <= $category[0] ) {
				break;
			}
		}
		$category = array(
			'slug'       => $category[1],
			'name'       => $category[2],
			'color'      => $category[3],
			'text_color' => $category[4],
		);

		/**
		 * Filters the category of an air quality index, such as for another scale or other colors.
		 *
		 * @since 6.2.0
		 *
		 * @param array|null $category Category, with 'slug', 'name', 'color' and 'text_color', or null to show no category.
		 * @param int        $aqi      Air quality index, rounded.
		 */
		$category = apply_filters( 'sloc_aqi_category', $category, $aqi );
		return is_array( $category ) ? $category : null;
	}

	/**
	 * Returns the description of a weather condition code, or all of them.
	 *
	 * An adaptation of the OpenWeatherMap weather condition codes, replacing the use of icon mapping.
	 *
	 * @since 5.0.0
	 *
	 * @param string|int|null $code Condition code. Optional.
	 * @return string|array The description, an empty string for an unknown code, or every description keyed by code when the code isn't a number.
	 */
	public static function weather_condition_codes( $code = null ) {
		$map = array(
			'none' => __( 'None', 'simple-location' ),
			/* Thunderstorms */
			'200'  => __( 'thunderstorm with light rain', 'simple-location' ),
			'201'  => __( 'thunderstorm with rain', 'simple-location' ),
			'202'  => __( 'thunderstorm with heavy rain', 'simple-location' ),
			'210'  => __( 'light thunderstorm', 'simple-location' ),
			'211'  => __( 'thunderstorm', 'simple-location' ),
			'212'  => __( 'heavy thunderstorm', 'simple-location' ),
			'221'  => __( 'ragged thunderstorm', 'simple-location' ),
			'230'  => __( 'thunderstorm with light drizzle', 'simple-location' ),
			'231'  => __( 'thunderstorm with drizzle', 'simple-location' ),
			'232'  => __( 'thunderstorm with heavy drizzle', 'simple-location' ),
			'233'  => __( 'thunderstorm with hail', 'simple-location' ),
			/* Drizzle */
			'300'  => __( 'light intensity drizzle', 'simple-location' ),
			'301'  => __( 'drizzle', 'simple-location' ),
			'302'  => __( 'heavy intensity drizzle', 'simple-location' ),
			'309'  => __( 'freezing drizzle', 'simple-location' ),
			'310'  => __( 'light intensity drizzle rain', 'simple-location' ),
			'311'  => __( 'drizzle rain', 'simple-location' ),
			'312'  => __( 'heavy intensity drizzle rain', 'simple-location' ),
			'313'  => __( 'shower rain and drizzle', 'simple-location' ),
			'314'  => __( 'heavy shower rain and drizzle', 'simple-location' ),
			'321'  => __( 'shower drizzle', 'simple-location' ),
			/* Wind */
			'400'  => __( 'Windy', 'simple-location' ),
			/* Rain */
			'500'  => __( 'light rain', 'simple-location' ),
			'503'  => __( 'moderate rain', 'simple-location' ),
			'504'  => __( 'extreme rain', 'simple-location' ),
			'511'  => __( 'freezing rain', 'simple-location' ),
			'512'  => __( 'rain and wind', 'simple-location' ),
			'513'  => __( 'ice', 'simple-location' ),
			'520'  => __( 'light intensity shower rain', 'simple-location' ),
			'521'  => __( 'shower rain', 'simple-location' ),
			'522'  => __( 'heavy intensity shower rain', 'simple-location' ),
			'531'  => __( 'ragged shower rain', 'simple-location' ),
			'540'  => __( 'flood', 'simple-location' ),
			'541'  => __( 'flash flooding', 'simple-location' ),
			/* Snow */
			'600'  => __( 'light snow', 'simple-location' ),
			'601'  => __( 'Snow', 'simple-location' ),
			'602'  => __( 'Heavy snow', 'simple-location' ),
			'610'  => __( 'Mix of Snow/Rain', 'simple-location' ),
			'611'  => __( 'Sleet', 'simple-location' ),
			'612'  => __( 'Light shower sleet', 'simple-location' ),
			'613'  => __( 'Shower sleet', 'simple-location' ),
			'615'  => __( 'Light rain and snow', 'simple-location' ),
			'616'  => __( 'Rain and snow', 'simple-location' ),
			'620'  => __( 'Light shower snow', 'simple-location' ),
			'621'  => __( 'Shower snow', 'simple-location' ),
			'622'  => __( 'Heavy shower snow', 'simple-location' ),
			'623'  => __( 'Flurries', 'simple-location' ),
			'624'  => __( 'Hail', 'simple-location' ),
			'625'  => __( 'Snow and Wind', 'simple-location' ),
			'626'  => __( 'Diamond dust', 'simple-location' ),
			/* Atmosphere */
			'701'  => __( 'mist', 'simple-location' ),
			'702'  => __( 'Frost', 'simple-location' ),
			'703'  => __( 'Icy', 'simple-location' ),
			'711'  => __( 'Smoke', 'simple-location' ),
			'721'  => __( 'Haze', 'simple-location' ),
			'731'  => __( 'sand/dust', 'simple-location' ),
			'741'  => __( 'fog', 'simple-location' ),
			'751'  => __( 'sand', 'simple-location' ),
			'761'  => __( 'dust', 'simple-location' ),
			'762'  => __( 'volcanic ash', 'simple-location' ),
			'771'  => __( 'squalls', 'simple-location' ),
			'772'  => __( 'waterspouts', 'simple-location' ),
			'781'  => __( 'tornado', 'simple-location' ),
			'782'  => __( 'hurricane', 'simple-location' ),
			'790'  => __( 'lightning', 'simple-location' ),

			/* Cloudiness */
			'800'  => __( 'Clear Sky', 'simple-location' ),
			'801'  => __( 'Few Clouds', 'simple-location' ),
			'802'  => __( 'Scattered Clouds', 'simple-location' ),
			'803'  => __( 'Broken Clouds', 'simple-location' ),
			'804'  => __( 'Overcast Clouds', 'simple-location' ),
			'900'  => __( 'Unknown Precipitation', 'simple-location' ),
		);

		if ( ! is_numeric( $code ) ) {
			return $map;
		}

		if ( array_key_exists( $code, $map ) ) {
			return $map[ $code ];
		}
		return '';
	}


	/**
	 * Returns the icon for a weather condition code.
	 *
	 * @since 5.0.0
	 *
	 * @param string|int $code   Condition code.
	 * @param boolean    $is_day Whether it is daytime, for conditions with day and night icons. Default true.
	 * @return string Icon name, or an empty string for an unknown code.
	 */
	public static function weather_condition_icons( $code, $is_day = true ) {
		switch ( $code ) {
			case '200':
			case '201':
			case '202':
			case '210':
			case '211':
			case '212':
			case '221':
			case '230':
			case '231':
			case '232':
			case '233':
				return $is_day ? 'wi-day-thunderstorm' : 'wi-night-thunderstorm';
			case '300':
			case '301':
			case '302':
			case '309':
			case '310':
			case '311':
			case '312':
			case '313':
			case '314':
			case '321':
				return $is_day ? 'wi-day-sprinkle' : 'wi-night-sprinkle';
			case '400':
				return $is_day ? 'wi-day-windy' : 'wi-windy';
			case '500':
			case '503':
			case '504':
			case '511':
				return $is_day ? 'wi-day-rain' : 'wi-night-rain';
			case '512':
				return $is_day ? 'wi-day-rain-wind' : 'wi-night-rain-wind';
			case '513':
				return 'wi-snowflake-cold';
			case '520':
			case '521':
			case '522':
			case '531':
				return $is_day ? 'wi-day-showers' : 'wi-night-showers';
			case '540':
			case '541':
				return 'wi-flood';
			case '600':
			case '601':
			case '602':
				return $is_day ? 'wi-day-snow' : 'wi-night-snow';
			case '610':
				return $is_day ? 'wi-day-rain-mix' : 'wi-night-rain-mix';
			case '611':
			case '612':
			case '613':
				return $is_day ? 'wi-day-sleet' : 'wi-night-sleet';
			case '615':
			case '616':
				return $is_day ? 'wi-day-rain-mix' : 'wi-night-rain-mix';
			case '620':
			case '621':
			case '622':
			case '623':
				return $is_day ? 'wi-day-snow-wind' : 'wi-night-snow-wind';

			case '624':
				return $is_day ? 'wi-day-hail' : 'wi-night-hail';
			case '625':
				return $is_day ? 'wi-day-snow-wind' : 'wi-night-snow-wind';
			case '626':
				return $is_day ? 'wi-day-sleet' : 'wi-night-sleet';
			case '701':
			case '702':
			case '703':
			case '711':
				return 'wi-smoke';
			case '721':
				return 'wi-day-haze';
			case '731':
				return 'wi-dust';
			case '741':
				return $is_day ? 'wi-day-fog' : 'wi-night-fog';
			case '751':
				return 'wi-sandstorm';
			case '761':
				return 'wi-dust';
			case '762':
				return 'wi-volcano';
			case '771':
			case '772':
				return 'wi-gale-warning';
			case '781':
				return 'wi-tornado';
			case '782':
				return 'wi-hurricane';
			case '790':
				return $is_day ? 'wi-day-lightning' : 'wi-night-lightning';
			case '800':
				return $is_day ? 'wi-day-sunny' : 'wi-night-clear';
			case '801':
				return $is_day ? 'wi-day-cloudy' : 'wi-night-cloudy';
			case '802':
			case '803':
				return $is_day ? 'wi-day-cloudy' : 'wi-night-partly-cloudy';
			case '804':
				return $is_day ? 'wi-day-cloudy' : 'wi-night-cloudy';
			case '900':
				return 'wi-raindrops';
		}
		return '';
	}
}
