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
  $loc['adr'] = get_post_meta( $id, 'mf2_adr');
	return $loc;
   } 

// Return nicely formatted string using the Address/Venue
function get_the_location($id = false, $map = true) {
	$c =  "";
	$loc = get_the_geodata($id);
	if($loc['public']!='1') {
      return "";
  }
  if ( !empty($loc['address']) ) {
    return $loc['address'];
  }
  if (!empty($loc['venue'])) {
    if (filter_var($loc['venue'], FILTER_VALIDATE_URL)) {
      $url = $loc['venue'];
			$venue_id = url_to_postid($url ); 
      if ($venue_id!=0) {
        $loc = get_the_geodata($venue_id); 
      }
    }
    else { 
      if (empty($loc['venue']) ){
        $c .= $loc['venue'] . ', '; 
      }
    $url = get_the_permalink($id);
    }
  }
  $c .= '<p class="h-geo geo location">';
  $c .= '<data class="p-latitude latitude" value="' . $loc['latitude'] . '"></data>';
  $c .= '<data class="p-longitude longitude" value="' . $loc['longitude'] . '"></data>';
  $c .= '<em><span class="p-name">' . $loc['address'] . '</span></em>';
  if ($map) {
    $link = '(<a href="http://www.openstreetmap.org/#map=19/' . $loc['latitude'] . '/' . $loc['longitude'] . '" title="Show Map">' . 'View' . '</a>)</p>';
  }
  else {
    $link = '(<a href="' . $url . '" title="Show Venue">View</a>)</p>';
  }
  $c .= $link;
	return $c;
}

function location_content($content) {
    $content .= get_the_location();
    return $content;
}

add_filter( 'the_content', 'location_content' );

?>
