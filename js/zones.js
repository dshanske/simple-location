jQuery( document ).ready( function( $ ) {
	jQuery( document );
	$( '#add-location-zone-button' ).click( function( event ) {
		const n = $( '#location-zones > li' ).length;
		const s = '<li><input type="text" placeholder="Name" name="sloc_zones[' + n + '][name]" /><input placeholder="Latitude" type="text" name="sloc_zones[' + n + '][latitude]" /><input type="text" placeholder="Longitude" name="sloc_zones[' + n + '][longitude]" /><input type="text" placeholder="Radius(in meters)" name="sloc_zones[' + n + '][radius]" /></li>';
		$( s ).appendTo( '#location-zones' );
	} );
	$( '#delete-location-zone-button' ).click( function( event ) {
		$( '#location-zones li:last-of-type' ).remove();
	} );
	$( '#add-location-stations-button' ).click( function( event ) {
		const n = $( '#location-stations > li' ).length;
		const s = '<li><input type="text" placeholder="Station ID" name="sloc_stations[' + n + '][id]" /><input placeholder="URL" type="text" name="sloc_stations[' + n + '][url]" /></li>';
		$( s ).appendTo( '#location-stations' );
	} );
	$( '#delete-location-stations-button' ).click( function( event ) {
		$( '#location-stations li:last-of-type' ).remove();
	} );
} );

