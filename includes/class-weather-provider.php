<?php

abstract class Weather_Provider {

	protected $api;
	protected $style;
	protected $latitude;
	protected $longitude;
	protected $station_id; // Most weather sites permit a station ID to be set
	protected $temp_units; // Unit of measurement for temperature: imperial, metric, etc
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
			'cache_key'  => 'slocw',
			'cache_time' => 600,
			'temp_units' => 'imperial',
			'style'      => '',
		);
		$defaults         = apply_filters( 'sloc_weather_provider_defaults', $defaults );
		$r                = wp_parse_args( $args, $defaults );
		$this->style      = $r['style'];
		$this->api        = $r['api'];
		$this->station_id = $r['station_id'];
		$this->temp_units = $r['temp_units'];
		$this->cache_key  = $r['cache_key'];
		$this->cache_time = $r['cache_time'];
		$this->set_location( $r['latitude'], $r['longitude'] );
	}

	public function get_station() {
		return $this->station_id;
	}

	public function metric_to_imperial( $temp ) {
		return ( $temp * 9 / 5 ) + 32;
	}

	public function imperial_to_metric( $temp ) {
		return ( $temp - 32 ) / 1.8;
	}

	/**
	 * Set and Validate Coordinates
	 *
	 * @param $lat Latitude
	 * @param $lng Longitude
	 * @return boolean Return False if Validation Failed
	 */
	public function set_location( $lat, $lng ) {
		// Validate inputs
		if ( ( ! is_numeric( $lat ) ) && ( ! is_numeric( $lng ) ) ) {
			return false;
		}
		$this->latitude  = $lat;
		$this->longitude = $lng;
	}

	public function temp_units() {
		switch ( $this->temp_units ) {
			case 'imperial':
				return 'F';
			default:
				return 'C';
		}
	}


	/**
	 * Get Coordinates
	 *
	 * @return array|boolean Array with Latitude and Longitude false if null
	 */
	public function get_location() {
		$return              = array();
		$return['latitude']  = $this->latitude;
		$return['longitude'] = $this->longitude;
		$return              = array_filter( $return );
		if ( ! empty( $return ) ) {
			return $return;
		}
		return false;
	}


	/**
	 * Return the marked up  icon standardized to the fontse
	 *
	 * @return string marked up icon
	 */
	public static function get_icon( $icon, $summary ) {
		$sprite = plugins_url( 'weather-icons.svg', dirname( __FILE__ ) );
		return '<span aria-label=' . $summary . '" title=' . $summary . '" ><svg class="svg-icon svg-' . $icon . '" aria-hidden="true"><use xlink:href="' . $sprite . '#' . $icon . '"></use></svg></span>';
	}


	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	abstract public function get_conditions();

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
						$return .= round( $conditions['temperature'] ) . '&deg;' . $this->temp_units();
		}
			return $return;
	}

		/**
		 * Return the name of an icon standardized to the iconset
		 *
		 * @return string
		 */
	public function get_current_temperature() {
			$conditions = $this->get_conditions();
		if ( isset( $conditions['temperature'] ) ) {
				return $conditions['temperature'] . '&deg;' . $this->temp_units;
		}
			return '';
	}


		/**
	 * Return Timezone Data for a Set of Coordinates
	 *
	 * @return array|boolean Return Timezone Data or False if Failed
	 */

	protected function timezone() {
		$timezone = Loc_Timezone::timezone_for_location( $this->latitude, $this->longitude );
		if ( $timezone ) {
			$return             = array();
			$return['timezone'] = $timezone->name;
			$return['offset']   = $timezone->offset;
			$return['seconds']  = $timezone->seconds;
			return $return;
		}
		return false;
	}
}
