jQuery( document ).ready( function( $ ) {

jQuery( document )
	$( '#add-location-zone-button' ).click( function( event ) {
		var n = $( "#location-zones > li" ).length;
		var s = '<li><input type="text" placeholder="Name" name="sloc_zones[' + n + '][name]" /><input placeholder="Latitude" type="text" name="sloc_zones[' + n + '][latitude]" /><input type="text" placeholder="Longitude" name="sloc_zones[' + n + '][longitude]" /><input type="text" placeholder="Radius(in meters)" name="sloc_zones[' + n + '][radius]" /></li>' 
		$( s ).appendTo( '#location-zones' );
	});
	$( '#delete-location-zone-button' ).click( function( event ) {
		$( '#location-zones li:last-of-type' ).remove();
	});
});


