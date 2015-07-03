<?php 

add_action('init', array('loc_view', 'content_location') );

// Location Display
class loc_view {
	// Return geodata as an array
	public static function get_the_geodata($id = false) {
		if ($id===false) {
			$id = get_the_ID();
		}
		$address = get_post_meta( $id, 'mf2_adr');
		$loc = array();
		$loc['latitude'] = get_post_meta( $id, 'geo_latitude', true );
		$loc['longitude'] = get_post_meta( $id, 'geo_longitude' , true );
		$loc['altitude'] = get_post_meta( $id, 'geo_altitude' , true );
		$loc['public'] = get_post_meta( $id, 'geo_public', true  );
		$loc['address'] = get_post_meta( $id, 'geo_address', true );
		$loc['map'] = get_post_meta( $id, 'geo_map', true );
		$loc['full'] = get_post_meta( $id, 'geo_full', true );
		if ($address!=false) {
			$loc['adr'] = array_pop($address);
		}
		return $loc;
	} 
	public static function get_location($id = false) {
		$loc = self::get_the_geodata($id);
		if($loc['public']!='1') {
			return "";
		}
		if($loc['full']!='1') {
			return self::get_the_adr($loc['adr']);
		}
		else{
			return self::get_the_full_adr($loc['adr']);
		}
	}

	public static function get_map($id = false) {
  	$loc = self::get_the_geodata($id);
		$config = get_option('sloc_options');
		if($config==false) {
			$config = array (
				'height' => '350',
        'width' => '350',
                'zoom' => '14'
    );
		}  
  	if(($loc['map']!='1')||($loc['public']!='1')) {
			return "";
		}
		return self::get_the_geo($loc['latitude'], $loc['longitude']) . google_map_static::get_the_map($loc['latitude'], $loc['longitude'], $config['height'], $config['width'], $config['zoom']);
	}

	// Return marked up coordinates
	public static function get_the_geo($lat, $lon) {
		$c = '<p class="h-geo geo location">';
		$c .= '<data class="p-latitude latitude" value="' . $lat . '"></data>';
		$c .= '<data class="p-longitude longitude" value="' . $lon . '"></data>';
		$c .= '</p>';
		return $c;
	}

	public static function get_the_full_adr($loc) {
		$c = '<span class="h-adr adr">';
		if( isset($loc['name']) ) {
			$c .= '<span class="p-name name">' . $loc['name'] . '</span>, ';
		}
		if( isset($loc['street-address']) ) {
			$c .= '<span class="p-street-address street-address">' . $loc['street-address'] . '</span>, ';
		}
		if( isset($loc['extended-address']) ) {
			$c .= '<span class="p-extended-address extended-address">' . $loc['extended-address'] . '</span>, '; 
		}
		if( isset($loc['locality']) ) {
			$c .= '<span class="p-locality locality">'. $loc['locality'] . '</span>, '; 
		} 
		if( isset($loc['region']) ) {
			$c .= '<span class="p-region region">' . $loc['region'] . '</span>, ';
		} 
		if( isset($loc['country-name']) ) {
			$c .= '<span class="p-country-name country-name">' . $loc['country-name'] . '</span>';
		} 
		$c .= '</span>';
		return $c;
	}

	public static function get_the_adr($loc) {
		$c = '<span class="h-adr adr">';
		if( isset($loc['name']) ) {
			$c .= '<span class="p-name name">' . $loc['name'] . '</span>, ';
		}  
		if( isset($loc['locality']) ) { 
			$c .= '<span class="p-locality locality">'. $loc['locality'] . '</span>, ';
		} 
		if( isset($loc['region']) ) { 
			$c .= '<span class="p-region region">' . $loc['region'] . '</span>, ';
		} 
		if( isset($loc['country-name']) ) {
			$c .= '<span class="p-country-name country-name">' . $loc['country-name'] . '</span>';
		} 
		$c .= '</span>';
		return $c;
	}

	public static function location_content($content) {
		$loc = self::get_location();
		if(!empty($loc)) {
			$content .= '<p><sub>' . _x('Location:', 'simple-location') . ' ' . $loc . '</sub></p>';
		}
		return $content;
	}

	public static function content_map($content) {
		if (is_single() ) {
			$content .= self::get_map();
		}
		return $content;
	}

	// If the Theme Has Not Declared Location Support
	// Add the Location Display to the Content Filter
	public static function content_location() {
		add_filter( 'the_content', array('loc_view', 'content_map'), 20);
		if (!current_theme_supports('simple-location')) {
			add_filter( 'the_content', array('loc_view', 'location_content'), 20 );
		}
	}

} // Class Ends

?>
