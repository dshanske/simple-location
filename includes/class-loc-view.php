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


	/**
	 * Returns list of available icons.
	 *
	 * @return array List of Icon Options.
	 */
	public static function get_iconlist() {
		return array(
			'fa-location-arrow'   => __( 'Location Arrow', 'simple-location' ),
			'fa-compass'          => __( 'Compass', 'simple-location' ),
			'fa-map'              => __( 'Map', 'simple-location' ),
			'fa-map-marker'       => __( 'Map Marker', 'simple-location' ),
			'fa-passport'         => __( 'Passport', 'simple-location' ),
			'fa-home'             => __( 'Home', 'simple-location' ),
			'fa-globe-asia'       => __( 'Globe - Asia', 'simple-location' ),
			'fa-globe-americas'   => __( 'Globe - Americas', 'simple-location' ),
			'fa-globe-europe'     => __( 'Globe - Europe', 'simple-location' ),
			'fa-globe-africa'     => __( 'Globe - Africa', 'simple-location' ),
			'fa-plane'            => __( 'Plane', 'simple-location' ),
			'fa-train'            => __( 'Train', 'simple-location' ),
			'fa-taxi'             => __( 'Taxi', 'simple-location' ),
			'fa-tram'             => __( 'Tram', 'simple-location' ),
			'fa-biking'           => __( 'Biking', 'simple-location' ),
			'fa-bus'              => __( 'Bus', 'simple-location' ),
			'fa-bus-alt'          => __( 'Bus (Alt)', 'simple-location' ),
			'fa-car'              => __( 'Car', 'simple-location' ),
			'fa-helicopter'       => __( 'Helicopter', 'simple-location' ),
			'fa-horse'            => __( 'Horse', 'simple-location' ),
			'fa-ship'             => __( 'Ship', 'simple-location' ),
			'fa-running'          => __( 'Running', 'simple-location' ),
			'fa-shuttlevan'       => __( 'Shuttle Van', 'simple-location' ),
			'fa-subway'           => __( 'Subway', 'simple-location' ),
			'fa-suitcase'         => __( 'Suitcase', 'simple-location' ),
			'fa-suitcase-rolling' => __( 'Suitcase - Rolling', 'simple-location' ),
			'fa-walking'          => __( 'Walking', 'simple-location' ),
			'fa-running'          => __( 'Running', 'simple-location' ),

		);
	}

	/**
	 * Generates Pulldown list of Icons.
	 *
	 * @param string  $icon Icon to be Selected.
	 * @param boolean $echo Echo or Return.
	 * @return string Select Option. Optional.
	 */
	public static function icon_select( $icon, $echo = false ) {
		$choices = self::get_iconlist();
		if ( ! $icon ) {
			$icon = 'fa-location-arrow';
		}
		$return = '';
		foreach ( $choices as $value => $text ) {
			$return .= sprintf( '<option value="%1s" %2s>%3s</option>', esc_attr( $value ), selected( $icon, $value, false ), esc_html( $text ) );
		}
		if ( ! $echo ) {
			return $return;
		}
		echo $return; // phpcs:ignore
	}

	/**
	 * Return the marked up icon standardized to the fonts.
	 *
	 * @param string $icon Name of Icon.
	 * @param string $summary Description of Icon. Optional.
	 * @return string marked up icon
	 */
	public static function get_icon( $icon = 'fa-location-arrow', $summary = null ) {
		if ( 'none' === $icon ) {
			return '';
		}
		if ( ! $summary ) {
			$list    = self::get_iconlist();
			$summary = array_key_exists( $icon, $list ) ? $list[ $icon ] : $icon;
		}
		$svg = sprintf( '%1$ssvgs/%2$s.svg', plugin_dir_path( __DIR__ ), $icon );
		if ( file_exists( $svg ) ) {
			$svg = file_get_contents( $svg );
		}
		if ( $svg ) {
			return PHP_EOL . sprintf( '<span class="sloc-location-icon sloc-icon-%1$s" style="display: inline-block; max-height: 1.5rem; margin-right: 0.1rem;" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $icon ), esc_attr( $summary ), $svg );
		}
		return '';
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
		if ( current_user_can( 'read_private_posts' ) && 'public' !== $loc['visibility'] ) {
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
			'markup'        => true, // Mark up with Microformats
			'description'   => __( 'Location: ', 'simple-location' ),
			'wrapper-class' => array( 'sloc-display' ), // Class or classes to wrap the entire location in
			'wrapper-type'  => 'div', // HTML type to wrap the entire location in
		);
		$default  = apply_filters( 'simple_location_display_defaults', $defaults );
		$args     = wp_parse_args( $args, $defaults );
		$args     = array_merge( $loc, $args );
		$map      = Loc_Config::map_provider();
		if ( is_null( $map ) ) {
			return __( 'Error: Invalid Map Provider', 'simple-location' );
		}
		$map->set( $loc );
		$wrap  = '<%1$s class="%2$s">%3$s</%1$s>';
		$class = is_array( $args['wrapper-class'] ) ? $args['wrapper-class'] : explode( ' ', $args['wrapper-class'] );
		if ( $args['markup'] ) {
			$class[] = 'p-location';
			$class[] = 'h-adr';
		}
		$c = array( PHP_EOL );

		if ( $args['text'] ) {
			$c[] = $args['description'];
		}
		if ( 'public' === $args['visibility'] ) {
			if ( $args['markup'] ) {
				$c[] = self::get_the_geo( $loc );
			}
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
			$adclass = $args['markup'] ? 'p-label' : '';
			$c[]     = sprintf( '<a class="%1$s" href="%2$s">%3$s</a>', $adclass, $map->get_the_map_url(), $loc['address'] );
		} elseif ( isset( $args['address'] ) ) {
			$c[] = $args['address'];
		}
		if ( $args['icon'] ) {
			array_unshift( $c, self::get_icon( $loc['icon'] ) );
		}
		if ( isset( $loc['weather'] ) && $args['weather'] ) {
			$c[] = self::get_the_weather( $loc['weather'] );
		}

		$return = implode( PHP_EOL, $c );
		return sprintf( '<%1s class="%2$s">%3$s</%1$s>', $args['wrapper-type'], implode( ' ', $class ), $return );
	}

	public static function get_map( $object = null, $args = array() ) {
		$loc = WP_Geo_Data::get_geodata( $object );
		if ( isset( $loc ) && ( 'public' === $loc['visibility'] ) && ( isset( $loc['latitude'] ) ) && ( isset( $loc['longitude'] ) ) ) {
			$map = Loc_Config::map_provider();
			$loc = array_merge( $loc, $args );
			$map->set( $loc );
			return $map->get_the_map();
		}
		return '';
	}

	public static function get_the_weather( $weather, $args = null ) {
		$defaults = array(
			'style'         => 'simple', // Options are simple, complete, graphic (only)
			'description'   => __( 'Weather: ', 'simple-location' ),
			'wrapper-class' => array( 'sloc-weather' ), // Class or classes to wrap the weather in
			'wrapper-type'  => 'p', // HTML type to wrap the weather in
		);
		$args     = wp_parse_args( $args, $defaults );
		if ( ! is_array( $weather ) || empty( $weather ) ) {
			return '';
		}
		if ( ! isset( $weather['icon'] ) ) {
			$weather['icon'] = 'wi-thermometer';
		}

		$class    = implode( ' ', $args['wrapper-class'] );
		$return   = array( PHP_EOL );
		$return[] = Weather_Provider::get_icon( $weather['icon'], ifset( $weather['summary'] ) );
		if ( 'graphic' !== $args['style'] ) {
			$return[] = self::get_the_temperature( $weather ) . PHP_EOL;
			if ( ! empty( $weather['summary'] ) ) {
				$return[] = sprintf( '<span class="p-weather">%1$s</span>', $weather['summary'] );
			}
		}
		if ( 'complete' === $args['style'] ) {
			$return[] = self::get_weather_extras( $weather );
		}
		if ( isset( $weather['station_id'] ) ) {
			if ( isset( $weather['name'] ) ) {
				$return[] = sprintf( '<p>%1$s</p>', $weather['name'] );
			}
		}
		return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $args['wrapper-type'], esc_attr( $class ), implode( PHP_EOL, array_filter( $return ) ) );
	}

	private static function get_the_temperature( $weather ) {
		if ( ! isset( $weather['temperature'] ) ) {
			return null;
		}
		$units = ifset( $weather['units'] );
		if ( ! $units ) {
			switch ( get_option( 'sloc_measurements' ) ) {
				case 'imperial':
					$units                  = __( 'F', 'simple-location' );
					$weather['temperature'] = Weather_Provider::celsius_to_fahrenheit( $weather['temperature'] );
					break;
				default:
					$units = __( 'C', 'simple-location' );
			}
		}
		return sprintf(
			'<span class="sloc-temp p-temperature h-measure">
		<data class="p-num" value="%1$s">%1$s</data>
		<data class="p-unit" value="&deg;%2$s">&deg;%2$s</data></span>',
			round( $weather['temperature'] ),
			$units
		);
	}

	private static function get_weather_extras( $weather ) {
		$measurements = get_option( 'sloc_measurements' );
		$return       = array();
		if ( isset( $weather['humidity'] ) ) {
			$return[] = self::markup_parameter( $weather['humidity'], 'humidity', '%', __( 'Humidity', 'simple-location' ) );
		}
		if ( isset( $weather['cloudiness'] ) ) {
			$return[] = self::markup_parameter( $weather['cloudiness'], 'cloudiness', '%', __( 'Cloudiness', 'simple-location' ) );
		}
		if ( isset( $weather['visibility'] ) ) {
			$return[] = self::markup_parameter( $weather['visibility'], 'visibility', 'm', __( 'Visibility', 'simple-location' ) );
		}
		return '<ul>' . implode( '', $return ) . '</ul>';
	}

	private static function markup_parameter( $value, $property, $unit, $type ) {
		return sprintf(
			'<li class="sloc-%1$s p-%1$s h-measure">
			<data class="p-type" value="%2$s">%4$s</data>
			<data class="p-num" value="%2$s">%2$s</data>
			<data class="p-unit" value="%3$s">%3$s</data></li>',
			$property,
			$value,
			$unit,
			$type
		);
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
		return self::get_weather_data( $lat, $lng );
	}

	public static function get_weather_by_station( $station, $provider = null ) {
		$provider = Loc_Config::weather_provider( $provider );
		$provider->set( array( 'station_id' => $station ) );
		return $provider->get_conditions();
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
		if ( self::show_map() ) {
			$content .= self::get_map();
		}
		return $content;
	}

	public static function show_map() {
		if ( get_option( 'sloc_map_display' ) ) {
			return true;
		} else {
			return is_single();
		}
	}


} // Class Ends

function get_simple_location( $object = null, $args = array() ) {
	Loc_View::get_location( $object, $args );
}


