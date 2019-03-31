<?php

class Weather_Provider_Apixu extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Apixu', 'simple-location' );
		$this->slug = 'apixu';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_apixu_api' );
		}
		$args['cache_key'] = '';
		parent::__construct( $args );
	}

	public function is_station() {
		return false;
	}

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions() {
		if ( empty( $this->api ) ) {
			return array();
		}
		$return = array();
		if ( $this->latitude && $this->longitude ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ) );
				if ( $conditions ) {
					return $conditions;
				}
			}
			$data = array(
				'key'  => $this->api,
				'q'    => $this->latitude . ',' . $this->longitude,
				'lang' => get_bloginfo( 'language' ),
			);
			$url  = 'https://api.apixu.com/v1/current.json';
			$url  = add_query_arg( $data, $url );
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
			if ( ! isset( $response['current'] ) ) {
				return $return;
			}
			$response              = $response['current'];
			$return['temperature'] = ifset( $response['temp_c'] );
			if ( isset( $current['humidity'] ) ) {
				$return['humidity'] = $response['humidity'];
			}
			$return['pressure'] = ifset( $response['pressure_mb'] );
			if ( isset( $response['cloud'] ) ) {
				$return['cloudiness'] = $response['cloud'];
			}

			$return['wind']           = array();
			$return['wind']['speed']  = ifset( $response['wind_kph'] );
			$return['wind']['degree'] = ifset( $response['wind_degree'] );
			$return['wind']           = array_filter( $return['wind'] );
			$return['rain']           = ifset( $response['precip_mm'] );
			$condition                = ifset( $response['condition'] );
			$return['summary']        = ifset( $condition['text'] );
			$return['icon']           = $this->icon_map( $condition['code'], ifset( $response['is_day'] ) );
			$timezone                 = Loc_Timezone::timezone_for_location( $this->latitude, $this->longitude );
			$return['sunrise']        = sloc_sunrise( $this->latitude, $this->longitude, $timezone );
			$return['sunset']         = sloc_sunset( $this->latitude, $this->longitude, $timezone );

			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ), $return, $this->cache_time );
			}
			return array_filter( $return );
		}
		return false;
	}

	private function icon_map( $id, $is_day ) {
		$id = (int) $id;
		switch ( $id ) {
			case 1000:
				return $is_day ? 'wi-day-sunny' : 'wi-night-clear';
			case 1003:
				return $is_day ? 'wi-day-cloudy' : 'wi-night-partly-cloudy';
			case 1006:
				return $is_day ? 'wi-cloudy' : 'wi-night-cloudy';
			case 1009:
				return $is_day ? 'wi-day-sunny-overcast' : 'wi-night-alt-cloudy';
			case 1030:
				return 'wi-raindrops';
			case 1063:
				return 'wi-raindrop';
			case 1066:
				return 'wi-snowflake-cold';
			case 1069:
				return 'wi-sleet';
			case 1072:
				return 'wi-sprinkle';
			case 1087:
				return 'wi-thunderstorm';
			case 1114:
				return 'wi-snow-wind';
			case 1117:
				return 'wi-snow';
			case 1135:
				return 'wi-fog';
			case 1147:
				return 'wi-fog';
			case 1150:
				return 'wi-sprinkle';
			case 1153:
				return 'wi-sprinkle';
			case 1168:
				return 'wi-sprinkle';
			case 1171:
				return 'wi-sprinkle';
			case 1180:
				return 'wi-rain';
			case 1183:
				return 'wi-rain';
			case 1186:
			case 1189:
			case 1192:
			case 1198:
				return 'wi-rain';
			case 1201:
				return 'wi-storm-showers';
			case 1204:
			case 1207:
				return 'wi-sleet';
			case 1210:
			case 1213:
			case 1216:
			case 1219:
			case 1222:
			case 1225:
			case 1237:
				return 'wi-snow';
			case 1240:
			case 1243:
			case 1249:
			case 1252:
			case 1255:
			case 1258:
				return 'wi-showers';
			case 1261:
			case 1264:
				return 'wi-snow-wind';
			case 1273:
			case 1276:
			case 1279:
			case 1282:
				return 'wi-storm-showers';
			default:
				return '';
		}
	}

}

register_sloc_provider( new Weather_Provider_Apixu() );
