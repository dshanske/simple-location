jQuery( document ).on( 'click', '.lookup-address-button', function($) {
	jQuery.ajax({ 
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'get_sloc_address_data',
				latitude: jQuery("#latitude").val(),
				longitude: jQuery("#longitude").val(),
				accuracy: jQuery("#accuracy").val(),
				altitude: jQuery("#altitude").val(),
				altitude_accuracy: jQuery("#altitude-accuracy").val(),
				speed: jQuery("#speed").val(),
				heading: jQuery("#heading").val()
			},
		success : function( response ) {
			if ( typeof response == 'undefined' ) {
			}
			else {
				if ( response['success'] == 'false' ) {
					alert( response['data'][0]["message"] );
				}
				else {
					if ( typeof response['data'] != 'undefined' ) {
						if ( ( 'display-name' in response['data'] ) && ( jQuery('#address').val() === '' ) ) {
							jQuery("#address").val(response['data']['display-name']) ;
						}
						if ( 'name' in response['data'] ) {
							jQuery("#location-name").val(response['data']['name']) ;
						}
						if ( 'street-address' in response['data'] ) {
							jQuery("#street-address").val(response['data']['street-address']) ;

						}
						if ( 'extended-address' in response['data'] ) {
							jQuery("#extended-address").val(response['data']['extended-address']) ;
						}
						if ( 'locality' in response['data'] ) {
							jQuery("#locality").val(response['data']['locality']) ;
						}
						if ( 'region' in response['data'] ) {
							jQuery("#region").val(response['data']['region']) ;
						}
						if ( 'postal-code' in response['data'] ) {
							jQuery("#postal-code").val(response['data']['postal-code']) ;
						}
						if ( 'country-name' in response['data'] ) {
							jQuery("#country-name").val(response['data']['country-name']) ;
						}
						if ( 'country-code' in response['data'] ) {
							jQuery("#country-code").val(response['data']['country-code']) ;
						}
						if ( 'timezone' in response['data'] ) {
							jQuery("#timezone").val(response['data']['timezone']) ;
						}
						console.log(response);
					}
				}
			}
		},
	  error: function(request, status, error){
			alert(request.responseText);
		}
	});
})

jQuery( document ).on( 'click', '.save-venue-button', function($) {
	jQuery.ajax({ 
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'save_venue_data',
				latitude: jQuery("#latitude").val(),
				longitude: jQuery("#longitude").val(),
				location_name: jQuery("#location-name").val(),
				street_address: jQuery("#street-address").val(),
				extended_address: jQuery("#extended-address").val(),
				locality: jQuery("#locality").val(),
				region: jQuery("#region").val(),
				postal_code: jQuery("#postal-code").val(),
				country_name: jQuery("#country-name").val(),
				country_code: jQuery("#country-code").val()
			},
		success : function( response ) {
      if ( typeof response !== 'undefined' ) {
				if ( typeof response['data'] !== 'undefined' ) {

				}
			}

			console.log(response);
		},
	  error: function(request, status, error){
			alert(request.responseText);
		}
	});
})

function clearLocation() {
  document.getElementById("latitude").value = "";
  document.getElementById("longitude").value = "";
  document.getElementById("street-address").value = "";
  document.getElementById("extended-address").value = "";
  document.getElementById("locality").value = "";
  document.getElementById("region").value = "";
  document.getElementById("postal-code").value = "";
  document.getElementById("country-name").value = "";
  document.getElementById("country-code").value = "";
  document.getElementById("address").value = "";
  document.getElementById("location-name").value = "";
}	

function getLocation() {
	var options = {
		enableHighAccuracy: true,
		maximumAge: 600000
	};
      if (navigator.geolocation) {
	      navigator.geolocation.getCurrentPosition(showPosition, error, options);
      }
      else{alert("Geolocation is not supported by this browser.");}
  }
function showPosition(position)
  {
  document.getElementById("latitude").value = position.coords.latitude;
  document.getElementById("longitude").value = position.coords.longitude;
  document.getElementById("altitude").value = position.coords.altitude;
  document.getElementById("accuracy").value = position.coords.accuracy;
  document.getElementById("altitude-accuracy").value = position.coords.altitudeAccuracy;
  document.getElementById("heading").value = position.coords.heading;
  document.getElementById("speed").value = position.coords.speed;

  }

function error(err) {
	  alert( err.message );
};

function closeWindow( ) {
	jQuery('#closeTBWindow').click(tb_remove);
};

function toggle_timezone() {
	var e = document.getElementById("timezone");
	if ( document.getElementById("override_timezone").checked ) {
		e.removeAttribute( "hidden" ); 
	}
	else { 
		e.setAttribute( "hidden", true );
	}
}
