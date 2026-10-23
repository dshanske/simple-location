<?php
/**
 * Location Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Location Provider using Compass.
 * https://github.com/aaronpk/compass
 *
 * @since 1.0.0
 */
class Location_Provider_Compass extends Location_Provider {

	/**
	 * Constructor.
	 *
	 * @since 3.6.4
	 *
	 * @param array $args Arguments. See Location_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Compass', 'simple-location' );
		$this->slug        = 'compass';
		$this->url         = 'https://github.com/aaronpk/compass';
		$this->description = __( 'If you have an instance of Compass, you can retrieve your current or historical location from it', 'simple-location' );
		parent::__construct( $args );
		$this->background = true;
		add_filter( 'user_contactmethods', array( get_called_class(), 'user_contactmethods' ), 12 );
	}

	/**
	 * Adds the Compass API key and URL fields to user profiles.
	 *
	 * @since 4.0.0
	 *
	 * @param string[] $profile_fields Contact methods, keyed by meta key.
	 * @return string[] Contact methods.
	 */
	public static function user_contactmethods( $profile_fields ) {
		$profile_fields['compass_api'] = __( 'Compass API Key', 'simple-location' );
		$profile_fields['compass_url'] = __( 'Compass URL', 'simple-location' );
		return $profile_fields;
	}


	/**
	 * Removes keys from an array; the opposite of wp_array_slice_assoc().
	 *
	 * @since 4.0.2
	 *
	 * @param array $array The array.
	 * @param array $keys  Keys to remove.
	 * @return array The array without the keys.
	 */
	public function array_strip_assoc( $array, $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $array[ $key ] ) ) {
				unset( $array[ $key ] );
			}
		}
		return $array;
	}

	/**
	 * Returns the user whose Compass account to use.
	 *
	 * That is the user set with set_user(), or the current user if none was set. Compass holds a person's
	 * location history, so another user's account is only used when the current user can edit that user,
	 * which is normally only administrators.
	 *
	 * @since 6.0.0
	 *
	 * @return int|WP_Error User ID, 0 if there is no user, or an error if the current user may not use that account.
	 */
	protected function compass_user() {
		$current = get_current_user_id();
		if ( $this->user instanceof WP_User ) {
			$user_id = $this->user->ID;
		} elseif ( is_numeric( $this->user ) && 0 < (int) $this->user ) {
			$user_id = (int) $this->user;
		} else {
			$user_id = $current;
		}
		if ( ! $user_id ) {
			return 0;
		}
		if ( $current !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
			return new WP_Error( 'compass_not_allowed', __( 'You cannot look up another user\'s location in Compass', 'simple-location' ), array( 'user' => $user_id ) );
		}
		return $user_id;
	}

	/**
	 * Converts a time to UTC.
	 *
	 * @since 6.0.0
	 *
	 * @param DateTimeInterface|int|string $time A date and time, a Unix timestamp, or a string DateTime can parse.
	 * @return DateTimeImmutable|WP_Error The time in UTC, or an error if it can't be parsed.
	 */
	protected function utc_time( $time ) {
		try {
			if ( $time instanceof DateTime ) {
				$time = DateTimeImmutable::createFromMutable( $time );
			} elseif ( is_int( $time ) ) {
				$time = new DateTimeImmutable( '@' . $time );
			} elseif ( is_string( $time ) ) {
				$time = new DateTimeImmutable( $time );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_time', __( 'Invalid time', 'simple-location' ), array( 'time' => $time ) );
		}
		if ( ! $time instanceof DateTimeImmutable ) {
			return new WP_Error( 'invalid_time', __( 'Invalid time', 'simple-location' ), array( 'time' => $time ) );
		}
		return $time->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Returns the user's track from Compass between two times.
	 *
	 * @since 4.1.12
	 *
	 * @param DateTime|string $start Start time.
	 * @param DateTime|string $end   End time.
	 * @return array|WP_Error|false|null List of array( longitude, latitude ) points; an error or false if the
	 *                                   request fails, or null without a Compass user, URL or API key.
	 */
	public function query( $start, $end ) {
		if ( is_string( $start ) ) {
			$start = new DateTime( $start );
		}

		if ( is_string( $end ) ) {
			$end = new DateTime( $end );
		}
		$user_id = $this->compass_user();
		if ( ! $user_id || is_wp_error( $user_id ) ) {
			return $user_id ? $user_id : null;
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
				'tz'     => rawurlencode( wp_timezone_string() ),
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

		/* Deny development sites for now */
		$response = wp_safe_remote_get( $url, $args );
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
					0 => clean_coordinate( $array[0], 'longitude' ),
					1 => clean_coordinate( $array[1], 'latitude' ),
				);
			},
			$response
		);
		return $response;
	}

	/**
	 * Removes the altitude from a point.
	 *
	 * @since 4.1.12
	 *
	 * @param array $array Point, as array( longitude, latitude, altitude ).
	 * @return array Point without the altitude.
	 */
	private function drop_altitude( $array ) {
		if ( 3 === count( $array ) ) {
			unset( $array[2] );
		}
		return $array;
	}

	/**
	 * Retrieves the user's location from Compass, at a time or the latest.
	 *
	 * @since 3.6.4
	 * @since 6.1.0 Refuses local and private addresses, encodes the token, and leaves the URL out of errors.
	 *
	 * @param string|int|DateTime $time An ISO8601 time string, unix timestamp, or DateTime. Optional; defaults to the latest location.
	 * @param array               $args Optional arguments to be passed.
	 * @return WP_Error|false|null An error, or false, if no location was found; null when the location is set (read it
	 *                             with get()), or without a Compass user, URL or API key.
	 */
	public function retrieve( $time = null, $args = array() ) {
		$this->reset();
		$user_id = $this->compass_user();
		if ( ! $user_id || is_wp_error( $user_id ) ) {
			return $user_id ? $user_id : null;
		}
		$compass = get_user_meta( $user_id, 'compass_url', true );
		if ( ! $compass ) {
			return null;
		}
		$api = get_user_meta( $user_id, 'compass_api', true );
		if ( ! $api ) {
			return null;
		}
		$url  = add_query_arg( 'token', rawurlencode( $api ), sprintf( '%1$s/api/last/', $compass ) );
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
			$time = $this->utc_time( $time );
			if ( is_wp_error( $time ) ) {
				return $time;
			}
			$this->time = $time->format( DATE_W3C );
			$url        = add_query_arg( 'before', $time->format( 'Y-m-d\TH:i:s' ), $url );
		}
		// The URL comes from the user's profile, so local and private addresses are refused.
		$response = wp_safe_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			// The URL has the token, so it isn't part of the error.
			return new WP_Error(
				$code,
				wp_remote_retrieve_response_message( $response ),
				array(
					'time' => $time,
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
		$this->longitude = clean_coordinate( $coord[0], 'longitude' );
		$this->latitude  = clean_coordinate( $coord[1], 'latitude' );
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
			// Operators that aren't a 2-letter IATA or 3-letter ICAO code, such as a name, are kept as they are.
			$airline = null;
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
		return null;
	}

	/**
	 * Set Speed Parameter
	 *
	 * @param array $properties Property Array.
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
			} elseif ( is_string( $properties['source'] ) && array_key_exists( $properties['source'], $this->get_activity_list() ) ) {
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
	 * @param array $properties Property Array.
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
