<?php
/**
 * Location Provider.
 *
 * @package Simple_Location
 */

/**
 * Location Provider using Compass.
 * https://github.com/aaronpk/compass
 *
 * @since 1.0.0
 */
class Location_Provider_Compass extends Location_Provider {

	public function __construct( $args = array() ) {
		$this->name        = __( 'Compass', 'simple-location' );
		$this->slug        = 'compass';
		$this->url         = 'https://github.com/aaronpk/compass';
		$this->description = __( 'If you have an instance of Compass, you can retrieve your current or historical location from it', 'simple-location' );
		parent::__construct( $args );
		$this->background = true;
		add_filter( 'user_contactmethods', array( get_called_class(), 'user_contactmethods' ), 12 );
	}

	public static function user_contactmethods( $profile_fields ) {
		$profile_fields['compass_api'] = __( 'Compass API Key', 'simple-location' );
		$profile_fields['compass_url'] = __( 'Compass URL', 'simple-location' );
		return $profile_fields;
	}


	// Opposite of array_slice_assoc as this removes only the keys in the $keys array
	public function array_strip_assoc( $array, $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $array[ $key ] ) ) {
				unset( $array[ $key ] );
			}
		}
		return $array;
	}

	public function query( $start, $end ) {
		if ( is_string( $start ) ) {
			$start = new DateTime( $start );
		}

		if ( is_string( $end ) ) {
			$end = new DateTime( $end );
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$compass = get_user_meta( $user_id, 'compass_url', true );
		if ( ! $compass ) {
			return;
		}
		$api = get_user_meta( $user_id, 'compass_api', true );
		if ( ! $api ) {
			return;
		}
		$url = sprintf( '%1$s/api/query/', $compass );
		$url = add_query_arg(
			array(
				'token'  => $api,
				'format' => 'linestring',
				'start'  => $start->format( 'Y-m-d\TH:i:s' ),
				'end'    => $end->format( 'Y-m-d\TH:i:s' ),
				'tz'     => wp_timezone_string(),
			),
			$url
		);

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
		if ( ! isset( $response['linestring'] ) ) {
			return false;
		}
		$response = $response['linestring'];
		if ( ! isset( $response['coordinates'] ) ) {
			return false;
		}
		$response = $response['coordinates'];

		// Drop Altitude if present and round.
		$response = array_map(
			function ( $array ) {
				return array(
					0 => clean_coordinate( $array[0] ),
					1 => clean_coordinate( $array[1] ),
				);
			},
			$response
		);
		return $response;
	}

	private function drop_altitude( $array ) {
		if ( 3 === count( $array ) ) {
			unset( $array[2] );
		}
		return $array;
	}

	public function retrieve( $time = null, $args = array() ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$compass = get_user_meta( $user_id, 'compass_url', true );
		if ( ! $compass ) {
			return;
		}
		$api = get_user_meta( $user_id, 'compass_api', true );
		if ( ! $api ) {
			return;
		}
		$url  = sprintf( '%1$s/api/last/?token=%2$s', $compass, $api );
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
		if ( $time ) {
			if ( is_string( $time ) ) {
				$time = new DateTime( $time );
			}
			$time->setTimezone( new DateTimeZone( 'GMT' ) );
			$this->time = $time->format( DATE_W3C );
			$url        = add_query_arg( 'before', $time->format( 'Y-m-d\TH:i:s' ), $url );
		}
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				$code,
				wp_remote_retrieve_response_message( $response ),
				array(
					'time' => $time,
					'url'  => $url,
				)
			);
		}

		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );
		if ( isset( $response['error'] ) ) {
			return new WP_Error(
				'compass_error',
				$response['error'],
				$response
			);
		}
		if ( ! isset( $response['data'] ) ) {
			return false;
		}
		$response = $response['data'];
		if ( ! isset( $response['geometry'] ) || ! isset( $response['properties'] ) ) {
			return false;
		}
		$coord           = $response['geometry']['coordinates'];
		$this->longitude = clean_coordinate( $coord[0] );
		$this->latitude  = clean_coordinate( $coord[1] );
		$properties      = array_filter( $response['properties'] );
		$this->heading   = array_key_exists( 'heading', $properties ) ? $properties['heading'] : null;
		$this->speed     = $this->set_speed( $properties );

		$this->altitude = isset( $coord[2] ) ? round( $coord[2], 2 ) : null;
		// Altitude is stored by Overland as a property not in the coordinates
		if ( is_null( $this->altitude ) && array_key_exists( 'altitude', $properties ) ) {
			$this->altitude = round( $properties['altitude'], 2 );
			unset( $properties['altitude'] );
		}
		$this->accuracy          = self::ifnot(
			$properties,
			array(
				'accuracy',
				'horizontal_accuracy',
			)
		);
		$this->altitude_accuracy = self::ifnot(
			$properties,
			array(
				'altitude_accuracy',
				'vertical_accuracy',
			)
		);

		if ( array_key_exists( 'airline', $properties ) ) {
			$properties['operator'] = trim( strtoupper( $properties['airline'] ) );
		}

		if ( array_key_exists( 'operator', $properties ) ) {
			if ( 2 === strlen( $properties['operator'] ) ) {
				$airline = Airport_Location::get_airline( $properties['operator'] );
			} elseif ( 3 === strlen( $properties['operator'] ) ) {
				$airline = Airport_Location::get_airline( $properties['operator'], 'icao_code' );
			}
			if ( is_array( $airline ) && array_key_exists( 'name', $airline ) ) {
				$properties['operator'] = $airline['name'];
			}
		}
		if ( ! array_key_exists( 'number', $properties ) ) {
			// Back compat for storing old options over number.
			if ( array_key_exists( 'flight', $properties ) ) {
				$properties['number'] = $properties['flight'];
				unset( $properties['flight'] );
			} elseif ( array_key_exists( 'flight_number', $properties ) ) {
				$properties['number'] = $properties['flight_number'];
				unset( $properties['flight_number'] );
			}
		}

		if ( array_key_exists( 'number', $properties ) ) {
			if ( ! is_numeric( $properties['number'] ) ) {
					$properties['number'] = strtoupper( $properties['number'] );
					$prefixes             = array( 'EIN', 'EI', 'JBU', 'WN' ); // Just happen to be the ones I have done before.
				foreach ( $prefixes as $prefix ) {
					$properties['number'] = str_replace( $prefix, '', $properties['number'] );
				}
			}
		}
		$this->set_activity( $properties );

		// Above handles standard properties the remaining code handles non-standard properties if present.

		$this->other( $properties );
	}

	/**
	 * Set Speed Parameter
	 *
	 * @param array $properties Property Array
	 *
	 * @return null|float Speed.
	 */
	private function set_speed( $properties ) {
		$speed = array_key_exists( 'speed', $properties ) ? $properties['speed'] : null;
		if ( is_null( $speed ) ) {
			if ( array_key_exists( 'ground_speed_knots', $properties ) ) {
				// Convert from knots to meters per second.
				$speed = $this->knots_to_meters( $properties['ground_speed_knots'] );
			} elseif ( array_key_exists( 'ground_speed', $properties ) ) {
				// Convert from miles per hour to meters per second.
				$speed = $this->miph_to_mps( $properties['ground_speed'] );
			}
		}
		return $speed;
	}

	/**
	 * Set Activity Property Based on a variety of fields
	 *
	 * @param array $properties {
	 *  List of Extra Activity Related Properties from Compass Input.
	 *  @type array|string $motion. Motion type.
	 *  @type string $source This can be which website or software it came from or simply the transit type. Optional.
	 *  @type string $origin Origin of the travel.
	 *  @type string $destination Destination for the travel.
	 *  @type string $aircraft Aircraft type if available. Optional.
	 *  @type string $departure Scheduled Time of Departure.
	 *  @type string $arrival Scheduled Time of Arrival.
	 *  @type int|string $number Indicating the flight, train, etc number.
	 */
	private function set_activity( $properties ) {
		if ( array_key_exists( 'motion', $properties ) ) {
			if ( is_string( $properties['motion'] ) ) {
				$this->activity = $properties['motion'];
			} elseif ( is_array( $properties['motion'] ) ) {
				if ( 1 === count( $properties['motion'] ) ) {
					$this->activity = $properties['motion'][0];
				}
			}
		} elseif ( array_key_exists( 'activity', $properties ) ) {
			if ( is_string( $properties['activity'] ) ) {
				$this->activity = $properties['activity'];
			}
		}
		// A lot of this is specific to my tasker implementation that tracks airline activity
		if ( array_key_exists( 'source', $properties ) && is_null( $this->activity ) ) {
			if ( in_array( $properties['source'], array( 'flight wifi', 'flight', 'flightaware.com' ), true ) ) {
				$this->activity = 'plane';
			} elseif ( in_array( $properties['source'], $this->get_activity_list(), true ) ) {
				$this->activity = $properties['source'];
			} else {
				$this->activity = 'unknown';
			}
		}

		if ( 'plane' === $this->activity && empty( $this->annotation ) ) {
			$annotate = array();
			if ( array_key_exists( 'operator', $properties ) ) {
				$annotate[] = $properties['operator'];
			}
			if ( array_key_exists( 'number', $properties ) ) {
				$annotate[] = $properties['number'];
			}
			if ( array_key_exists( 'origin', $properties ) && array_key_exists( 'destination', $properties ) ) {
				$origin      = Airport_Location::get( $properties['origin'] );
				$destination = Airport_Location::get( $properties['destination'] );
				$annotate[]  = sprintf( '%1$s - %2$s', $origin['name'], $destination['name'] );
			}
			$this->annotation = implode( ' ', $annotate );
		}
	}

	/**
	 * Convert extra properties for storage
	 *
	 * @param array $properties Property Array
	 */
	private function other( $properties ) {
		// Store other properties in other
		$this->other = array_filter(
			$this->array_strip_assoc(
				$properties,
				array(
					'heading',
					'speed',
					'accuracy',
					'horizontal_accuracy',
					'altitude',
					'altitude_accuracy',
					'vertical_accuracy',
					'ground_speed',
					'ground_speed_knots',
					'timestamp',
				)
			)
		);
	}
}
