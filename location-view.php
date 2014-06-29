<?php 

// Return geodata as an array
function get_the_geodata($id = false)
   {
	if ($id===false)
	   {
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
function the_location($id = false)
   {
	$c =  "";
	$loc = get_the_geodata($id);
	if($loc['public']=='0')
	    {
		$c .= 'Location: ';
		if (!empty($loc['venue']))
		     {
			 $c .= $loc['venue'] . ', '; 
		     }
		$c .=  $loc['address'];
	    }
	return $c;
   }

// Get the full address
function get_the_address($id = false)
   {	
   }

?>
