<?php

abstract class Weather_Provider extends Sloc_Provider {

	protected $style;
	protected $region; // If this weather provider is limited to a region
	protected $station_id; // Most weather sites permit a station ID to be set
	protected $units; // Unit of measurement: imperial, si, etc
	protected $cache_key; // If set this will cache the retrieved informatin
	protected $cache_time; // This will dictate for how long

	/**
	 * Constructor for the Abstract Class
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param string $args Arguments
	 */
	public function __construct( $args = array() ) {
		$defaults         = array(
			'api'        => null,
			'latitude'   => null,
			'longitude'  => null,
			'station_id' => null,
			'cache_key'  => null, // 'slocw',
			'cache_time' => 600,
			'units'      => get_option( 'sloc_measurements', Loc_Config::measurement_default() ),
			'style'      => '',
		);
		$defaults         = apply_filters( 'sloc_weather_provider_defaults', $defaults );
		$r                = wp_parse_args( $args, $defaults );
		$this->style      = $r['style'];
		$this->api        = $r['api'];
		$this->station_id = $r['station_id'];
		$this->units      = $r['units'];
		$this->cache_key  = $r['cache_key'];
		$this->cache_time = $r['cache_time'];
		$this->set( $r['latitude'], $r['longitude'] );
	}

	// Does this provider allow for station data
	public function is_station() {
		return true;
	}

	public function get_station() {
		return $this->station_id;
	}

	public static function celsius_to_fahrenheit( $temp ) {
		return ( $temp * 9 / 5 ) + 32;
	}

	public static function fahrenheit_to_celsius( $temp ) {
		return ( $temp - 32 ) / 1.8;
	}

	public static function meters_to_feet( $meters ) {
		return floatval( $meters ) * 3.2808399;
	}

	public function temp_unit() {
		switch ( $this->units ) {
			case 'imperial':
				return 'F';
			default:
				return 'C';
		}
	}

	/**
	 * Return the marked up  icon standardized to the fontse
	 *
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
			return PHP_EOL . sprintf( '<span class="sloc-weather-icon sloc-icon-%1$s" style="display: inline-block; max-height: 1.5rem; margin-right: 0.1rem;" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $icon ), esc_attr( $summary ), file_get_contents( $svg ) );
		}
		return '';
	}


	/**
	 * Return array of current conditions
	 *
	 * @param string $time ISO8601 timestamp
	 * @return array Current Conditions in Array
	 */
	abstract public function get_conditions( $time );

		/**
		 * Return summary of current conditions
		 *
		 * @return string Summary of Current conditions
		 */
	public function get_current_condition() {
		$return     = '';
		$conditions = $this->get_conditions();
		$return     = '<div class="sloc-weather">';
		$return    .= $this->get_icon( ifset( $conditions['icon'] ), ifset( $conditions['summary'] ) );
		if ( isset( $conditions['temperature'] ) ) {
			$return .= $this->get_temp( $conditions['temperature'] );
		}
		$return .= '</div>';
		return $return;
	}

	/* Passes temperature and returns it either same or converted with units
	 */
	private function get_temp( $temperature ) {
		if ( 'imperial' === $this->units ) {
			$temperature = self::celsius_to_fahrenheit( $temperature );
		}
		return round( $temperature ) . '&deg;' . $this->temp_unit();
	}

	/**
	 * Return the current temperature with units
	 *
	 * @return string
	 */
	public function get_current_temperature() {
			$conditions = $this->get_conditions();
		if ( isset( $conditions['temperature'] ) ) {
				return $this->get_temp( $conditions['temperature'] );
		}
			return '';
	}


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
			'wi-moon-first-quarter'      => __( 'First Quarter Moon', 'simple-location' ),
			'wi-moon-full'               => __( 'Full Moon', 'simple-location' ),
			'wi-moon-new'                => __( 'New Moon', 'simple-location' ),
			'wi-moonrise'                => __( 'Moonrise', 'simple-location' ),
			'wi-moonset'                 => __( 'Moonset', 'simple-location' ),
			'wi-moon-third-quarter'      => __( 'Third Quarter Moon', 'simple-location' ),
			'wi-moon-waning-crescent-1'  => __( 'Waning Crescent 1', 'simple-location' ),
			'wi-moon-waning-crescent-2'  => __( 'Waning Crescent 2', 'simple-location' ),
			'wi-moon-waning-crescent-3'  => __( 'Waning Crescent 3', 'simple-location' ),
			'wi-moon-waning-crescent-4 ' => __( 'Waning Crescent 4', 'simple-location' ),
			'wi-moon-waning-crescent-5'  => __( 'Waning Crescent 5', 'simple-location' ),
			'wi-moon-waning-crescent-6'  => __( 'Waning Crescent 6', 'simple-location' ),
			'wi-moon-waning-gibbous-1'   => __( 'Waning Gibbous 1', 'simple-location' ),
			'wi-moon-waning-gibbous-2'   => __( 'Waning Gibbous 2', 'simple-location' ),
			'wi-moon-waning-gibbous-3'   => __( 'Waning Gibbous 3', 'simple-location' ),
			'wi-moon-waning-gibbous-4'   => __( 'Waning Gibbous 4', 'simple-location' ),
			'wi-moon-waning-gibbous-5 '  => __( 'Waning Gibbous 5', 'simple-location' ),
			'wi-moon-waning-gibbous-6'   => __( 'Waning Gibbous 6', 'simple-location' ),
			'wi-moon-waxing-crescent-1'  => __( 'Waxing Crescent 1', 'simple-location' ),
			'wi-moon-waxing-crescent-2'  => __( 'Waxing Crescent 2', 'simple-location' ),
			'wi-moon-waxing-crescent-3'  => __( 'Waxing Crescent 3', 'simple-location' ),
			'wi-moon-waxing-crescent-4'  => __( 'Waxing Crescent 4', 'simple-location' ),
			'wi-moon-waxing-crescent-5'  => __( 'Waxing Crescent 5', 'simple-location' ),
			'wi-moon-waxing-crescent-6 ' => __( 'Waxing Crescent 6', 'simple-location' ),
			'wi-moon-waxing-gibbous-1'   => __( 'Waxing Gibbous 1', 'simple-location' ),
			'wi-moon-waxing-gibbous-2'   => __( 'Waxing Gibbous 2', 'simple-location' ),
			'wi-moon-waxing-gibbous-3'   => __( 'Waxing Gibbous 3', 'simple-location' ),
			'wi-moon-waxing-gibbous-4 '  => __( 'Waxing Gibbous 4', 'simple-location' ),
			'wi-moon-waxing-gibbous-5'   => __( 'Waxing Gibbous 5', 'simple-location' ),
			'wi-moon-waxing-gibbous-6'   => __( 'Waxing Gibbous 6', 'simple-location' ),
		);
		return array_merge( $neutral, $day, $night, $misc, $moon );

	}

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
		echo $return; // phpcs:ignore
	}



}
