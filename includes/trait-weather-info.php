<?php
/**
 * Weather Info Helper Trait.
 */

/**
 *
 *
 * @since 4.6.0
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
	 * Converts hPa to inHg.
	 *
	 * @param float $hpa HectoPascals.
	 * @return float Inches of Mercury.
	 */
	public static function hpa_to_inhg( $hpa ) {
		return round( $hpa * 0.03, 2 );
	}

	/**
	 * Converts inHg to hPa
	 *
	 * @param float $inhg Inches of Mercury.
	 * @return float HectoPascals.
	 */
	public static function inhg_to_hpa( $inhg ) {
		return round( $inhg / 0.03, 2 );
	}

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
	 */
	public static function get_icon( $icon, $summary = null ) {
		if ( 'none' === $icon ) {
			return '';
		}
		if ( ! $summary ) {
			$summary = $icon;
		}
		$svg = sprintf( '%1$ssvgs/%2$s.svg', plugin_dir_path( __DIR__ ), $icon );
		if ( file_exists( $svg ) ) {
			return PHP_EOL . sprintf( '<span class="sloc-weather-icon sloc-icon-%1$s" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $icon ), esc_attr( $summary ), file_get_contents( $svg ) );
		}
		return '';
	}


	/**
	 * Returns the unit, name and description based on the requested property.
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
		$units = array(
			'temperature' => array(
				'unit'  => __( '&deg;C', 'simple-location' ),
				'label' => __( 'degrees celsius', 'simple-location' ),
				'name'  => __( 'Temperature', 'simple-location' ),
				'icon'  => 'wi-thermometer',
			),
			'windchill'   => array(
				'unit'  => __( '&deg;C', 'simple-location' ),
				'label' => __( 'degrees celsius', 'simple-location' ),
				'name'  => __( 'Wind Chill', 'simple-location' ),
				'icon'  => 'wi-thermometer',
			),
			'heatindex'   => array(
				'unit'  => __( '&deg;C', 'simple-location' ),
				'label' => __( 'degrees celsius', 'simple-location' ),
				'name'  => __( 'Heat Index', 'simple-location' ),
				'icon'  => 'wi-thermometer',
			),
			'dewpoint'    => array(
				'unit'  => __( '&deg;C', 'simple-location' ),
				'label' => __( 'degrees celsius', 'simple-location' ),
				'name'  => __( 'Dewpoint', 'simple-location' ),
				'icon'  => 'wi-thermometer',
			),
			'humidity'    => array(
				'unit'  => __( '%', 'simple-location' ),
				'label' => __( 'percent', 'simple-location' ),
				'name'  => __( 'Humidity', 'simple-location' ),
				'icon'  => 'wi-humidity',
			),
			'pressure'    => array(
				'unit'  => __( 'hPa', 'simple-location' ),
				'label' => __( 'hectopascals', 'simple-location' ),
				'name'  => __( 'Pressure', 'simple-location' ),
				'icon'  => 'wi-barometer',
			),
			'cloudiness'  => array(
				'unit'  => __( '%', 'simple-location' ),
				'label' => __( 'percent', 'simple-location' ),
				'name'  => __( 'Cloudiness', 'simple-location' ),
				'icon'  => 'wi-cloudy',
			),
			'windspeed'   => array(
				'unit'  => __( 'mps', 'simple-location' ),
				'label' => __( 'meters per second', 'simple-location' ),
				'name'  => __( 'Wind Speed', 'simple-location' ),
				'icon'  => 'wi-windy',
			),
			'windgust'    => array(
				'unit'  => __( 'mps', 'simple-location' ),
				'label' => __( 'meters per second', 'simple-location' ),
				'name'  => __( 'Wind Gust', 'simple-location' ),
				'icon'  => 'wi-windy',
			),
			'winddegree'  => array(
				'unit'  => __( '&deg;', 'simple-location' ),
				'label' => __( 'degrees', 'simple-location' ),
				'name'  => __( 'Wind Direction', 'simple-location' ),
				'icon'  => 'wi-windy',
			),
			'rain'        => array(
				'unit'  => __( 'mm', 'simple-location' ),
				'label' => __( 'millimeters in the last hour', 'simple-location' ),
				'name'  => __( 'Rainfall', 'simple-location' ),
				'icon'  => 'wi-rain',
			),
			'snow'        => array(
				'unit'  => __( 'mm', 'simple-location' ),
				'label' => __( 'millimeters in the last hour', 'simple-location' ),
				'name'  => __( 'Snowfall', 'simple-location' ),
				'icon'  => 'wi-snow',
			),
			'visibility'  => array(
				'unit'  => __( 'm', 'simple-location' ),
				'label' => __( 'meters', 'simple-location' ),
				'name'  => __( 'Visibility', 'simple-location' ),
				'icon'  => 'wi-visibility',
			),
			'radiation'   => array(
				'unit'  => __( 'W/m^2', 'simple-location' ),
				'label' => __( 'watts per square meter', 'simple-location' ),
				'name'  => __( 'Solar Radiation', 'simple-location' ),
				'icon'  => 'fa-radiation',
			),
			'illuminance' => array(
				'unit'  => __( 'lx', 'simple-location' ),
				'label' => __( 'lux', 'simple-location' ),
				'name'  => __( 'Illuminance', 'simple-location' ),
				'icon'  => 'sa-sun',
			),
			'uv'          => array(
				'unit'  => '',
				'label' => __( 'index', 'simple-location' ),
				'name'  => __( 'UV Index', 'simple-location' ),
				'icon'  => 'wi-uv',
			),
			'aqi'         => array(
				'unit'  => '',
				'label' => __( 'index', 'simple-location' ),
				'name'  => __( 'Air Quality Index', 'simple-location' ),
			),
			'pm1_0'       => array(
				'unit'  => __( 'µg/m3', 'simple-location' ),
				'label' => __( 'micrograms per cubic meter of air', 'simple-location' ),
				'name'  => __( 'PM1.0', 'simple-location' ),
				'icon'  => 'wi-dust',
			),

			'pm2_5'       => array(
				'unit'  => __( 'µg/m3', 'simple-location' ),
				'label' => __( 'micrograms per cubic meter of air', 'simple-location' ),
				'name'  => __( 'PM2.5', 'simple-location' ),
				'icon'  => 'wi-dust',
			),
			'pm10_0'      => array(
				'unit'  => __( 'µg/m3', 'simple-location' ),
				'label' => __( 'micrograms per cubic meter of air', 'simple-location' ),
				'name'  => __( 'PM10', 'simple-location' ),
				'icon'  => 'wi-dust',
			),
			'co'          => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Carbon Monoxide', 'simple-location' ),
			),
			'co2'         => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Carbon Dioxide', 'simple-location' ),
			),
			'nh3'         => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Ammonia', 'simple-location' ),
			),
			'o3'          => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Ozone', 'simple-location' ),
			),
			'pb'          => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Lead', 'simple-location' ),
			),
			'so2'         => array(
				'unit'  => __( 'ppm', 'simple-location' ),
				'label' => __( 'parts per million', 'simple-location' ),
				'name'  => __( 'Sulfur Dioxide', 'simple-location' ),
			),
		);

			$iunits = array(
				'temperature' => array(
					'unit'  => __( '&deg;F', 'simple-location' ),
					'label' => __( 'degrees fahrenheit', 'simple-location' ),
					'name'  => __( 'Temperature', 'simple-location' ),
					'icon'  => 'wi-thermometer',
				),
				'windchill'   => array(
					'unit'  => __( '&deg;F', 'simple-location' ),
					'label' => __( 'degrees fahrenheit', 'simple-location' ),
					'name'  => __( 'Wind Chill', 'simple-location' ),
					'icon'  => 'wi-thermometer',
				),
				'heatindex'   => array(
					'unit'  => __( '&deg;F', 'simple-location' ),
					'label' => __( 'degrees fahrenheit', 'simple-location' ),
					'name'  => __( 'Heat Index', 'simple-location' ),
					'icon'  => 'wi-thermometer',
				),
				'dewpoint'    => array(
					'unit'  => __( '&deg;F', 'simple-location' ),
					'label' => __( 'degrees fahrenheit', 'simple-location' ),
					'name'  => __( 'Dewpoint', 'simple-location' ),
					'icon'  => 'wi-thermometer',
				),
				'pressure'    => array(
					'unit'  => __( 'inHg', 'simple-location' ),
					'label' => __( 'inches of mercury', 'simple-location' ),
					'name'  => __( 'Pressure', 'simple-location' ),
					'icon'  => 'wi-barometer',
				),
				'windspeed'   => array(
					'unit'  => __( 'MPH', 'simple-location' ),
					'label' => __( 'miles per hour', 'simple-location' ),
					'name'  => __( 'Wind Speed', 'simple-location' ),
					'icon'  => 'wi-windy',
				),
				'windgust'    => array(
					'unit'  => __( 'MPH', 'simple-location' ),
					'label' => __( 'miles per hour', 'simple-location' ),
					'name'  => __( 'Wind Gust', 'simple-location' ),
					'icon'  => 'wi-windy',
				),
				'winddegree'  => array(
					'unit'  => __( '&deg;', 'simple-location' ),
					'label' => __( 'degree', 'simple-location' ),
					'name'  => __( 'Wind Direction', 'simple-location' ),
					'icon'  => 'wi-windy',
				),

				'rain'        => array(
					'unit'  => __( 'in', 'simple-location' ),
					'label' => __( 'inches in the last hour', 'simple-location' ),
					'name'  => __( 'Rainfall', 'simple-location' ),
					'icon'  => 'wi-rain',
				),
				'snow'        => array(
					'unit'  => __( 'in', 'simple-location' ),
					'label' => __( 'inches in the last hour', 'simple-location' ),
					'name'  => __( 'Snowfall', 'simple-location' ),
					'icon'  => 'wi-snow',
				),
				'visibility'  => array(
					'unit'  => __( 'mi', 'simple-location' ),
					'label' => __( 'miles', 'simple-location' ),
					'name'  => __( 'Visibility', 'simple-location' ),
					'icon'  => 'wi-visibility',
				),
			);
			$iunits = array_merge( $units, $iunits );

			$return = $imperial ? $iunits : $units;
			if ( is_null( $property ) ) {
				return $return;
			}
			if ( array_key_exists( $property, $return ) ) {
				return $return[ $property ];
			}
			return false;
	}

}
