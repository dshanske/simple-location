<?php

add_action( 'init', array( 'loc_view', 'content_location' ) );

// Location Display
class loc_view {
	public static function get_icon( ) {
		// Substitute another svg sprite file
		$sprite = plugin_dir_url( __FILE__ ) . 'location.svg';
		return '<svg class="icon-location" aria-hidden="true"><use xlink:href="' . $sprite . '"></use></svg>';
	}

	public static function get_location($id = false) {
		$loc = WP_Geo_Data::get_geodata( $id );
		$map = new google_map_static();
		$c = '';
		if ( $loc['public'] == 1 ) {
			$c .= self::get_the_geo( $loc['latitude'], $loc['longitude'], $loc['address'] );
			$c = '<a href="' . $map->get_the_map_link( $loc['latitude'], $loc['longitude'] ) . '">' . $c . '</span></a>';
		} else {
			$c .= '</span>';
		}
		return $c;

	}

	public static function get_map($id = false) {
		$loc = WP_Geo_Data::get_geodata( $id );

		if ( $loc['public'] !== 1 ) {
			return '';
		}
		return google_map_static::get_the_map( $loc['latitude'], $loc['longitude'] );
	}

	// Return marked up coordinates
	public static function get_the_geo($lat, $lon, $address = null) {
		$c = '<p class="p-location">';
		if ( is_string( $address ) ){
			$c .= $address;
		}	
		$c .= '<span class="h-geo">';
		$c .= '<data class="p-latitude" value="' . $lat . '"></data>';
		$c .= '<data class="p-longitude" value="' . $lon . '"></data>';
		$c .= '</span>';
		$c .= '</p>';
		return $c;
	}

	public static function location_content($content) {
		$loc = self::get_location();
		if ( ! empty( $loc ) ) {
			$content .= '<p>' . self::get_icon() . ' ' . $loc . '</p>';
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
		add_filter( 'the_content', array( 'loc_view', 'content_map' ), 20 );
		if ( ! current_theme_supports( 'simple-location' ) ) {
			add_filter( 'the_content', array( 'loc_view', 'location_content' ), 20 );
		}
	}

} // Class Ends

?>
