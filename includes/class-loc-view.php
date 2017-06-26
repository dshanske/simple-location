<?php

add_action( 'init', array( 'Loc_View', 'content_location' ) );

// Location Display
class Loc_View {
	public static function get_icon( ) {
		// Substitute another svg sprite file
		$sprite = plugin_dir_url( __FILE__ ) . 'location.svg';
		return '<img class="icon-location" aria-hidden="true" src="' . $sprite . '" />';
	}

	public static function get_location($object = null) {
		$loc = WP_Geo_Data::get_geodata( $object );
		// 0 is private
		if (  isset( $loc ) ) {
			if ( '0' === $loc['public'] ) {
				return '';
			}
			$map = Loc_Config::default_map_provider();
			$map->set( $loc['latitude'], $loc['longitude'] );
			$c = '';
			// 1 is full public
			if ( '1' === $loc['public'] ) {
				$c .= self::get_the_geo( $loc['latitude'], $loc['longitude'], $loc['address'] );
				$c = '<a href="' . $map->get_the_map_url( $loc['latitude'], $loc['longitude'] ) . '">' . $c . '</span></a>';
			} else {
				$c = self::get_the_geo( null, null, $loc['address'] );
			}
			return $c;
		}
		return '';
	}

	public static function get_map($object = null) {
		$loc = WP_Geo_Data::get_geodata( $object );
		if ( isset( $loc ) && ( '1' === $loc['public'] ) ) {
			$map = Loc_Config::default_map_provider();
			$map->set( $loc['latitude'], $loc['longitude'] );
			return $map->get_the_map();
		}
		return '';
	}

	// Return marked up coordinates
	public static function get_the_geo($lat, $lon, $address = null) {
		$c = '<span class="p-location">';
		if ( is_string( $address ) ) {
			$c .= $address;
		}
		if ( $lat && $lon ) {
			$c .= '<span class="h-geo">';
			$c .= '<data class="p-latitude" value="' . $lat . '"></data>';
			$c .= '<data class="p-longitude" value="' . $lon . '"></data>';
			$c .= '</span>';
		}
		$c .= '</span>';
		return $c;
	}

	public static function location_content($content) {
		$loc = self::get_location();
		if ( ! empty( $loc ) ) {
			$content .= '<p class="sloc-display">' . self::get_icon() . ' ' . $loc . '</p>';
		}
		return $content;
	}

	public static function content_map($content) {
		if ( is_single() ) {
			$content .= self::get_map();
		}
		return $content;
	}

	// If the Theme Has Not Declared Location Support
	// Add the Location Display to the Content Filter
	public static function content_location() {
		add_filter( 'the_content', array( 'Loc_View', 'content_map' ), 11 );
		if ( ! current_theme_supports( 'simple-location' ) ) {
			add_filter( 'the_content', array( 'Loc_View', 'location_content' ), 12 );
		}
	}

} // Class Ends

?>
