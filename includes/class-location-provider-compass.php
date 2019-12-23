<?php

class Location_Provider_Compass extends Location_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Compass', 'simple-location' );
		$this->slug = 'compass';
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
			$this->time = $time;
			$url        = add_query_arg( 'before', $time, $url );
		}
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );
		if ( ! isset( $response['data'] ) ) {
			return false;
		}
		$response = $response['data'];
		if ( ! isset( $response['geometry'] ) || ! isset( $response['properties'] ) ) {
			return false;
		}
		$coord           = $response['geometry']['coordinates'];
		$this->longitude = $coord[0];
		$this->latitude  = $coord[1];
		$this->altitude  = isset( $coord[2] ) ? round( $coord[2], 2 ) : null;
		$properties      = array_filter( $response['properties'] );
		$this->heading   = array_key_exists( 'heading', $properties ) ? $properties['heading'] : null;
		$this->speed     = array_key_exists( 'speed', $properties ) ? $properties['speed'] : null;
		// Altitude is stored by Overland as a property not in the coordinates
		if ( is_null( $this->altitude ) && array_key_exists( 'altitude', $properties ) ) {
			$this->altitude = $properties['altitude'];
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
		if ( array_key_exists( 'activity', $properties ) ) {
			if ( is_string( $properties['activity'] ) ) {
				$this->activity = $properties['activity'];
			}
		}
		if ( is_null( $this->altitude ) && array_key_exists( 'altitude', $properties ) ) {
			$this->altitude = $properties['altitude'];
		}
		// A lot of this is specific to my tasker implementation that tracks airline activity
		if ( array_key_exists( 'source', $properties ) && is_null( $this->activity ) ) {
			switch ( $properties['source'] ) {
				case 'flight':
					$this->activity = __( 'Flight', 'simple-location' );
					break;
				case 'train':
					$this->activity = __( 'Train', 'simple-location' );
					break;
			}
			if ( array_key_exists( 'airline', $properties ) ) {
				$properties['airline'] = strtoupper( $properties['airline'] );
			}
			if ( 'flight' === $properties['source'] && empty( $this->annotation ) ) {
				$annotate = array();
				if ( array_key_exists( 'airline', $properties ) ) {
					$annotate[] = $properties['airline'];
				}
				if ( array_key_exists( 'number', $properties ) ) {
					$properties['flight_number'] = $properties['number'];
					unset( $properties['number'] );
				}
				if ( array_key_exists( 'flight_number', $properties ) ) {
					if ( ! is_numeric( $properties['flight_number'] ) ) {
						$properties['flight_number'] = strtoupper( $properties['flight_number'] );
						$prefixes                    = array( 'EIN', 'EI', 'JBU', 'WN' );
						foreach ( $prefixes as $prefix ) {
							$properties['flight_number'] = str_replace( $prefix, '', $properties['flight_number'] );
						}
					}
					$annotate[] = $properties['flight_number'];
				}
				if ( array_key_exists( 'origin', $properties ) && array_key_exists( 'destination', $properties ) ) {
					$origin      = Airport_Location::get( $properties['origin'] );
					$destination = Airport_Location::get( $properties['destination'] );
					$annotate[]  = sprintf( '%1$s - %2$s', $origin['name'], $destination['name'] );
				}
				$this->annotation = implode( ' ', $annotate );
			}
		}
		if ( is_null( $this->speed ) ) {
			if ( array_key_exists( 'ground_speed_knots', $properties ) ) {
				// Convert from knots to meters per second
				$this->speed = round( $properties['ground_speed_knots'] * 0.51444444 );
			} elseif ( array_key_exists( 'ground_speed', $properties ) ) {
				// Convert from miles per hour to meters per second
				$this->speed = round( $properties['ground_speed'] * 0.44704 );
			}
		}

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

register_sloc_provider( new Location_Provider_Compass() );
