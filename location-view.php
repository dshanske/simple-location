<?php 

// Return geodata as an array
function get_the_geodata($id = false) {
	if ($id===false) {
		$id = get_the_ID();
	}
	$loc = array();
	$loc['latitude'] = get_post_meta( $id, 'geo_latitude', true );
  $loc['longitude'] = get_post_meta( $id, 'geo_longitude' , true );
  $loc['public'] = get_post_meta( $id, 'geo_public', true  );
  $loc['address'] = get_post_meta( $id, 'geo_address', true );
  $loc['venue'] = get_post_meta( $id, 'geo_venue', true );
	return $loc;
   } 

// Return nicely formatted string using the Address/Venue
function get_the_location($id = false) {
	$c =  "";
	$loc = get_the_geodata($id);
	if($loc['public']!='0') {
		if (!empty($loc['venue'])) {
      if (filter_var($loc['venue'], FILTER_VALIDATE_URL)) {
          $url = $loc['venue'];
			    $venue_id = url_to_postid($url ); 
          if ($venue_id!=0) {
            $loc = get_the_geodata($venue_id); 
            $loc['venue'] = $url;
          }
      }
      else {
        $c .= $loc['venue'] . ', '; 
      }
		}
    $c .= '<p class="h-geo geo">';
    $c .= '<data class="p-latitude latitude" value="' . $loc['latitude'] . '"></data>';
    $c .= '<data class="p-longitude longitude" value="' . $loc['longitude'] . '"></data></p>';
		$c .=  $loc['address'];
	}
	return $c;
}

function location_content($content) {
    $content .= get_the_location();
    return $content;
}

add_filter( 'the_content', 'location_content' );

?>
