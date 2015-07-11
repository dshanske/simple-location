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
		if ($address!=false) {
			$loc['adr'] = array_pop($address);
		}
		return $loc;
	} 
	public static function get_location($id = false) {
		$loc = self::get_the_geodata($id);
		$map = new google_map_static();
		if( isset ($loc['adr']) ) {
			$adr = $loc['adr'];
		}
		else {
			$adr = array();
		}
		$final = array();
		if($loc['public']==0) {
			return "";
		}
    $c = '<span class="h-adr adr">';
    if( isset($adr['name']) ) {
      $final[] =  '<span class="p-name name">' . $adr['name'] . '</span>';
    }
		if ($loc['public']==3) {
    	if( isset($adr['street-address']) ) {
      	$final[] = '<span class="p-street-address street-address">' . $adr['street-address'] . '</span>';
    	}
    	if( isset($adr['extended-address']) ) {
      	$final[] = '<span class="p-extended-address extended-address">' . $adr['extended-address'] . '</span>';
    	}
		}
		if ($loc['public']>=2) {
	    if( isset($adr['locality']) ) {
 	     $final[] = '<span class="p-locality locality">'. $adr['locality'] . '</span>';
 	    }
		}
    if( isset($adr['region']) ) {
      $final[] = '<span class="p-region region">' . $adr['region'] . '</span>';
    }
    if( isset($adr['country-name']) ) {
      	$final[] = '<span class="p-country-name country-name">' . $adr['country-name'] . '</span>';
		}
		$c .= implode(", ", $final);
    if ($loc['public']==3) {
			$c .= self::get_the_geo($loc['latitude'], $loc['longitude']); 
			$c = '<a href="' . $map->get_the_map_link($loc['latitude'], $loc['longitude']) . '">' . $c . '</span></a>';
		}
		else {
    	$c .= '</span>';
		}
		return $c;



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
  	if(($loc['map']!='1')||($loc['public']!=3)) {
			return "";
		}
		return google_map_static::get_the_map($loc['latitude'], $loc['longitude'], $config['height'], $config['width'], $config['zoom']);
	}

	// Return marked up coordinates
	public static function get_the_geo($lat, $lon) {
		$c = '<p class="h-geo geo location">';
		$c .= '<data class="p-latitude latitude" value="' . $lat . '"></data>';
		$c .= '<data class="p-longitude longitude" value="' . $lon . '"></data>';
		$c .= '</p>';
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
