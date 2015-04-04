<?php 

// Return geodata as an array
function get_the_geodata($id = false) {
	if ($id===false) {
		$id = get_the_ID();
	}
	$loc = array();
	$loc['latitude'] = get_post_meta( $id, 'geo_latitude', true );
  $loc['longitude'] = get_post_meta( $id, 'geo_longitude' , true );
  $loc['altitude'] = get_post_meta( $id, 'geo_altitude' , true );
  $loc['public'] = get_post_meta( $id, 'geo_public', true  );
  $loc['address'] = get_post_meta( $id, 'geo_address', true );
  $loc['adr'] = array_pop(get_post_meta( $id, 'mf2_adr'));
	return $loc;
   } 

function get_simple_location($id = false) {
  $loc = get_the_geodata($id);
  if($loc['public']!='1') {
      return "";
  }
  return sloc_get_the_adr($loc['adr']);
}

function get_simple_map($id = false) {
  $loc = get_the_geodata($id);
  if($loc['public']!='1') {
      return "";
  }
  return sloc_get_the_map($loc['latitude'], $loc['longitude']);
}

// Return code for map linked to OSM
function sloc_get_the_map($lat, $lon) {
  $link = 'http://www.openstreetmap.org/#map=15/' . $lat . '/' . $lon;
  $map = plugin_dir_url( __FILE__ ) . 'staticmap.php?center=' . $lat . ',' . $lon . '&zoom=14&size=300x300&markers=' . $lat . ',' . $lon;
  $c = '<a href="' . $link . '"><img src="' . $map . '" /></a>';
  return $c;
}

// Return marked up coordinates
function sloc_get_the_geo($lat, $lon) {
  $c .= '<p class="h-geo geo location">';
  $c .= '<data class="p-latitude latitude" value="' . $lat . '"></data>';
  $c .= '<data class="p-longitude longitude" value="' . $lon . '"></data>';
  $c .= '</p>';
  return $c;
}

function sloc_get_the_full_adr($loc) {
  $c = '<span class="h-adr">';
  if( isset($loc['name']) ) {
    $c .= '<span class="p-name">' . $loc['name'] . '</span>, ';
  }
  if( isset($loc['street-address']) ) {
    $c .= '<span class="p-street-address">' . $loc['street-address'] . '</span>, ';
  }
  if( isset($loc['extended-address']) ) {
    $c .= '<span class="p-extended-address">' . $loc['extended-address'] . '</span>, '; 
  }
  if( isset($loc['locality']) ) {
    $c .= '<span class="p-locality">'. $loc['locality'] . '</span>, '; 
  } 
  if( isset($loc['region']) ) {
    $c .= '<span class="p-region">' . $loc['region'] . '</span>, ';
  } 
  if( isset($loc['country-name']) ) {
    $c .= '<span class="p-country-name">' . $loc['country-name'] . '</span>';
  } 
  $c .= '</span>';
  return $c;
}

function sloc_get_the_adr($loc) {
  $c = '<span class="h-adr">';
  if( isset($loc['name']) ) {
    $c .= '<span class="p-name">' . $loc['name'] . '</span>, ';
  }  
  if( isset($loc['locality']) ) { 
    $c .= '<span class="p-locality">'. $loc['locality'] . '</span>, ';
  } 
  if( isset($loc['region']) ) { 
    $c .= '<span class="p-region">' . $loc['region'] . '</span>, ';
  } 
  if( isset($loc['country-name']) ) {
    $c .= '<span class="p-country-name">' . $loc['country-name'] . '</span>';
  } 
  $c .= '</span>';
  return $c;
}


function location_content($content) {
    $loc = get_simple_location();
    if(!empty($loc)) {
      $content .= '<p><sub>Location: ' . $loc . '</sub></p>';
    }
    return $content;
}

function simple_embed_map($content) {
  if (is_single() ) {
    $content .= get_simple_map();
  }
  return $content;
}
?>
