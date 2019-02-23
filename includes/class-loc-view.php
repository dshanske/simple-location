<?php

add_action( 'init', array( 'Loc_View', 'init' ) );

// Location Display
class Loc_View {

	public static function init() {
		add_filter( 'get_comment_text', array( 'Loc_View', 'location_comment' ), 12, 2 );
		add_filter( 'the_content', array( 'Loc_View', 'content_map' ), 11 );
		if ( ! current_theme_supports( 'simple-location' ) ) {
			add_filter( 'the_content', array( 'Loc_View', 'location_content' ), 12 );
		}
	}

	public static function get_icon() {
		// Substitute another svg sprite file
		$sprite = plugins_url( 'location.svg', dirname( __FILE__ ) );
		return '<img class="icon-location" aria-label="' . __( 'Location: ', 'simple-location' ) . '" aria-hidden="true" src="' . $sprite . '" />';
	}

	public static function display_altitude( $altitude ) {
		$aunits = get_option( 'sloc_measurements' );
		switch ( $aunits ) {
			case 'imperial':
				$altitude = round( $altitude * 3.281 );
				$aunits   = 'ft';
				break;
			default:
				$aunits = 'm';
		}
		return $altitude . $aunits;
	}


	public static function get_location( $object = null, $args = array() ) {
		$loc = WP_Geo_Data::get_geodata( $object );
		if ( ! isset( $loc ) ) {
			return '';
		}
		if ( current_user_can( 'publish_posts' ) && 'public' !== $loc['visibility'] ) {
			$loc['visibility'] = 'public';
			if ( isset( $loc['address'] ) ) {
				/* translators: Prefaces the address 1. with the private status */
				$loc['address'] = sprintf( __( 'Hidden: %1$s', 'simple-location' ), $loc['address'] );
			}
		}
		if ( 'private' === $loc['visibility'] ) {
			return '';
		}
		$defaults = array(
			'height'        => null,
			'width'         => null,
			'map_zoom'      => null,
			'mapboxstyle'   => null,
			'mapboxuser'    => null,
			'weather'       => true,
			'icon'          => true, // Show Location Icon
			'text'          => false, // Show Description
			'description'   => __( 'Location: ', 'simple-location' ),
			'wrapper-class' => array( 'sloc-display' ), // Class or classes to wrap the entire location in
			'wrapper-type'  => 'p', // HTML type to wrap the entire location in
		);
		$default  = apply_filters( 'simple_location_display_defaults', $defaults );
		$args     = wp_parse_args( $args, $defaults );
		$args     = array_merge( $loc, $args );
		$map      = Loc_Config::map_provider();
		$map->set( $loc );
		$wrap  = '<%1$s class="%2$s">%3$s</%1$s>';
		$class = is_array( $args['wrapper-class'] ) ? implode( ' ', $args['wrapper-class'] ) : $args['wrapper-class'];
		$c     = '<span class="p-location">';

		if ( $args['text'] ) {
			$c .= $args['description'];
		}
		if ( 'public' === $args['visibility'] ) {
			$c .= self::get_the_geo( $loc );
			if ( isset( $loc['altitude'] ) ) {
				if ( get_option( 'sloc_altitude' ) < (int) $loc['altitude'] ) {
					$loc['altitude'] = self::display_altitude( $loc['altitude'] );
				} else {
					unset( $loc['altitude'] );
				}
			}
			if ( ! array_key_exists( 'address', $loc ) ) {
				$loc['address'] = dec_to_dms( $loc['latitude'], $loc['longitude'], ifset( $loc['altitude'] ) );
			}

			if ( isset( $loc['altitude'] ) ) {
				$loc['address'] .= sprintf( '(%1$s)', $loc['altitude'] );
			}
			$c .= sprintf( '<a href="%1$s">%2$s</a>', $map->get_the_map_url(), $loc['address'] );
		} else {
			$c = isset( $args['address'] ) ? $args['address'] : '';
		}
		$c .= '</span>';
		if ( isset( $loc['weather'] ) && $args['weather'] ) {
			$c .= self::get_the_weather( $loc['weather'] );
		}
		if ( $args['icon'] ) {
			$c = self::get_icon() . $c;
		}
		return sprintf( $wrap, $args['wrapper-type'], $class, $c );
	}

	public static function get_map( $object = null, $args = array() ) {
		$loc = WP_Geo_Data::get_geodata( $object );
		if ( isset( $loc ) && ( 'public' === $loc['visibility'] ) && ( isset( $loc['latitude'] ) ) ) {
			$map = Loc_Config::map_provider();
			$map->set( $loc );
			return $map->get_the_map();
		}
		return '';
	}

	public static function get_the_weather( $weather, $args = null ) {
		$defaults = array();
		$args     = wp_parse_args( $args, $defaults );
		if ( ! is_array( $weather ) || empty( $weather ) ) {
			return '';
		}
		if ( ! isset( $weather['icon'] ) ) {
			$weather['icon'] = 'wi-thermometer';
		}
		$c = '<br />' . Weather_Provider::get_icon( $weather['icon'], ifset( $weather['summary'] ) );
		if ( isset( $weather['temperature'] ) ) {
			$units = ifset( $weather['units'] );
			if ( ! $units ) {
				switch ( get_option( 'sloc_measurements' ) ) {
					case 'imperial':
						$units                  = 'F';
						$weather['temperature'] = Weather_Provider::celsius_to_fahrenheit( $weather['temperature'] );
						break;
					default:
						$units = 'C';
				}
			}
			$c .= '<span class="p-temperature">' . round( $weather['temperature'] ) . '&deg;' . $units . '</span>';
		}
		$c .= '&nbsp;' . ifset( $weather['summary'], '' );
		if ( isset( $weather['station_id'] ) ) {
			if ( isset( $weather['name'] ) ) {
				$c .= sprintf( '<p>%1$s</p>', $weather['name'] );
			}
		}
		return $c;
	}

	public static function get_weather_data( $lat, $lng ) {
		$weather = Loc_Config::weather_provider();
		$weather->set( $lat, $lng );
		return $weather->get_conditions();
	}

	public static function get_weather_by_user( $user ) {
		if ( is_numeric( $user ) && 0 !== $user ) {
			$user = new WP_User( $user );
		}
		if ( ! $user instanceof WP_User ) {
			return '';
		}
		$loc = WP_Geo_Data::get_geodata( $user );
		if ( ! isset( $loc['latitude'] ) ) {
			return '';
		}
		return self::get_weather_by_location( $loc['latitude'], $loc['longitude'] );
	}

	public static function get_weather_by_location( $lat, $lng ) {
		$weather = self::get_weather_data( $lat, $lng );
		return self::get_the_weather( self::get_weather_data( $lat, $lng ) );
	}

	public static function get_weather_by_station( $station, $provider = null ) {
		$provider = Loc_Config::weather_provider( $provider );
		$provider->set( array( 'station_id' => $station ) );
		$weather = $provider->get_conditions();
		return self::get_the_weather( $weather );
	}

	// Return marked up coordinates
	public static function get_the_geo( $loc, $display = false ) {
		$string = $display ? '<span class="p-%1$s">%2$f</span>' : '<data class="p-%1$s" value="%2$f"></data>';
		$return = '';
		foreach ( array( 'latitude', 'longitude', 'altitude' ) as $value ) {
			if ( isset( $loc[ $value ] ) ) {
				$return .= sprintf( $string, $value, $loc[ $value ] );
			}
		}
		return $return;
	}

	public static function location_content( $content ) {
		$loc = self::get_location();
		if ( ! empty( $loc ) ) {
			$content .= $loc;
		}
		return $content;
	}

	public static function location_comment( $comment_text, $comment ) {
		$loc = self::get_location(
			$comment,
			array(
				'text' => false,
				'icon' => false,
			)
		);
		if ( ! empty( $loc ) ) {
			$comment_text .= PHP_EOL . $loc . PHP_EOL;
		}
		return $comment_text;
	}

	public static function content_map( $content ) {
		if ( is_single() ) {
			$content .= self::get_map();
		}
		return $content;
	}

} // Class Ends

function get_simple_location( $object = null, $args = array() ) {
	Loc_View::get_location( $object, $args );
}


