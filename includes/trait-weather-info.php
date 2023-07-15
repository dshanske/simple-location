<?php
/**
 * Weather Info Helper Trait.
 */

/**
 *
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
		$defaults = array(
			'unit'  => '',
			'label' => '',
			'name'  => '',
			'icon'  => '',
		);
		$units    = array(
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
				'icon'  => 'md-aqi',
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
				return wp_parse_args( $return[ $property ], $defaults );
			}
			return false;
	}

	/*
	 * An adaptation of the OpenWeatherMap weather condition codes, replacing the use of icon mapping.
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
			'702'  => __( 'Frost', 'simple-locaiton' ),
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


	/*
	 * Map Codes to Icons
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
