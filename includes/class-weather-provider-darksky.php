<?php

class Weather_Provider_DarkSky extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Dark Sky', 'simple-location' );
		$this->slug = 'darksky';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_darksky_api' );
		}
		$args['cache_key'] = '';
		parent::__construct( $args );
	}

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions() {
		$return = array( 'units' => $this->temp_unit() );
		if ( $this->latitude && $this->longitude ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ) );
				if ( $conditions ) {
					return $conditions;
				}
			}
			$url  = sprintf( 'https://api.darksky.net/forecast/%1$s/%2$s,%3$s', $this->api, $this->latitude, $this->longitude );
			$args = array(
				'headers'             => array(
					'Accept' => 'application/json',
				),
				'timeout'             => 10,
				'limit_response_size' => 1048576,
				'redirection'         => 1,
				// Use an explicit user-agent for Simple Location
				'user-agent'          => 'Simple Location for WordPress',
			);

			$response = wp_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			if ( ! isset( $response['currently'] ) ) {
				return array();
			}
			$current                  = $response['currently'];
			$return['temperature']    = $current['temperature'];
			$return['humidity']       = $current['humidity'];
			$return['pressure']       = $current['pressure'];
			$return['wind']           = array();
			$return['wind']['speed']  = $current['windSpeed'];
			$return['wind']['degree'] = $current['windBearing'];
			$return['summary']        = $current['summary'];
			$return['icon']           = $this->icon_map( $current['icon'] );
			$return['visibility']     = $current['visibility'];

			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ), $return, $this->cache_time );
			}
			return array_filter( $return );
		}
		return false;
	}

	private function icon_map( $id ) {
		return '';
	}

}

register_sloc_provider( new Weather_Provider_DarkSky() );
