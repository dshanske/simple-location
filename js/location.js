function getFullLocation() {
	var options = {
		enableHighAccuracy: true,
		maximumAge: 600000
	};
      if ( navigator.geolocation ) {
	navigator.geolocation.getCurrentPosition( reverseLookup, error, options );
      } else {
alert( 'Geolocation is not supported by this browser.' );
}
  }

function reverseLookup( position ) {
	if ( '' === jQuery( '#longitude' ).val() ) {
		jQuery( '#longitude' ).val( position.coords.longitude ) ;
	}
	if ( '' === jQuery( '#latitude' ).val() ) {
		jQuery( '#latitude' ).val( position.coords.latitude ) ;
	}
	jQuery( '#accuracy' ).val( position.coords.accuracy );
	jQuery( '#heading' ).val( position.coords.heading );
	jQuery( '#speed' ).val( position.coords.speed );
	jQuery( '#altitude' ).val( position.coords.altitude );
	jQuery( '#map_zoom' ).val( parseInt( Math.log2( 591657550.5 / ( position.coords.accuracy * 45 ) ) ) + 1 );
	jQuery.ajax({
			type: 'GET',

			// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
			url: sloc.api_url + 'reverse/',
			beforeSend: function( xhr ) {

				// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
				xhr.setRequestHeader( 'X-WP-Nonce', sloc.api_nonce );
			},
			data: {
				latitude: jQuery( '#latitude' ).val(),
				longitude: jQuery( '#longitude' ).val(),
				altitude: jQuery( '#altitude' ).val(),
				map_zoom: jQuery( '#map_zoom' ).val()
			},
		success: function( response ) {
			if ( 'undefined' == typeof response ) {
			} else {
				if ( ( 'display-name' in response ) && ( '' === jQuery( '#address' ).val() ) ) {
							jQuery( '#address' ).val( response['display-name']) ;
						}
						if ( 'name' in response ) {
							jQuery( '#location-name' ).val( response.name ) ;
						}
						if ( 'latitude' in response ) {
							jQuery( '#latitude' ).val( response.latitude ) ;
						}
						if ( 'longitude' in response ) {
							jQuery( '#longitude' ).val( response.longitude ) ;
						}
						if ( 'street-address' in response ) {
							jQuery( '#street-address' ).val( response['street-address']) ;

						}
						if ( 'extended-address' in response ) {
							jQuery( '#extended-address' ).val( response['extended-address']) ;
						}
						if ( 'locality' in response ) {
							jQuery( '#locality' ).val( response.locality ) ;
						}
						if ( 'region' in response ) {
							jQuery( '#region' ).val( response.region ) ;
						}
						if ( 'postal-code' in response ) {
							jQuery( '#postal-code' ).val( response['postal-code']) ;
						}
						if ( 'country-name' in response ) {
							jQuery( '#country-name' ).val( response['country-name']) ;
						}
						if ( 'country-code' in response ) {
							jQuery( '#country-code' ).val( response['country-code']) ;
						}
						if ( 'timezone' in response ) {
							jQuery( '#post-timezone' ).val( response.timezone ) ;
							jQuery( '#post-timezone-label' ).text( response.timezone );
						}
						console.log( response );
			}
		},
	  error: function( request, status, error ) {
		alert( request.responseText );
		}
	});
}

function getWeather() {
	jQuery.ajax({
			type: 'GET',

			// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
			url: sloc.api_url + 'weather/',
			beforeSend: function( xhr ) {

				// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
				xhr.setRequestHeader( 'X-WP-Nonce', sloc.api_nonce );
			},
			data: {
				latitude: jQuery( '#latitude' ).val(),
				longitude: jQuery( '#longitude' ).val()
			},
		success: function( response ) {
			if ( 'undefined' == typeof response ) {
			} else {
				if ( ( 'temperature' in response ) && ( '' === jQuery( '#temperature' ).val() ) ) {
					jQuery( '#temperature' ).val( response.temperature ) ;
				}
				if ( ( 'humidity' in response ) && ( '' === jQuery( '#humidity' ).val() ) ) {
					jQuery( '#humidity' ).val( response.humidity ) ;
				}
				if ( ( 'icon' in response ) && ( '' === jQuery( '#weather_icon' ).val() ) ) {
					jQuery( '#weather_icon' ).val( response.icon ) ;
				}
				if ( ( 'summary' in response ) && ( '' === jQuery( '#weather_summary' ).val() ) ) {
					jQuery( '#weather_summary' ).val( response.summary ) ;
				}
				if ( ( 'pressure' in response ) && ( '' === jQuery( '#pressure' ).val() ) ) {
					jQuery( '#pressure' ).val( response.pressure ) ;
				}
				if ( ( 'visibility' in response ) && ( '' === jQuery( '#visibility' ).val() ) ) {
					jQuery( '#visibility' ).val( response.visibility ) ;
				}
				if ( 'wind' in response ) {
					if ( 'speed' in response.wind ) {
						jQuery( '#wind_speed' ).val( response.wind.speed ) ;
					}
					if ( 'degree' in response.wind ) {
						jQuery( '#wind_degree' ).val( response.wind.degree ) ;
					}
				}
				console.log( response );
			}
		},
	error: function( request, status, error ) {
			alert( request.responseText );
		}
	});
}


jQuery( document ).on( 'click', '.lookup-weather-button', function( $ ) {
	getWeather();
});


jQuery( document ).on( 'click', '.lookup-address-button', function( $ ) {
	getFullLocation();
});

jQuery( document ).on( 'click', '.save-venue-button', function( $ ) {
	jQuery.ajax({
		type: 'POST',

		// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
		url: sloc.api_url + 'reverse/',
		beforeSend: function( xhr ) {

			// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
			xhr.setRequestHeader( 'X-WP-Nonce', sloc.api_nonce );
		},
			data: {
				action: 'save_venue_data',
				latitude: jQuery( '#latitude' ).val(),
				longitude: jQuery( '#longitude' ).val(),
				location_name: jQuery( '#location-name' ).val(),
				street_address: jQuery( '#street-address' ).val(),
				extended_address: jQuery( '#extended-address' ).val(),
				locality: jQuery( '#locality' ).val(),
				region: jQuery( '#region' ).val(),
				postal_code: jQuery( '#postal-code' ).val(),
				country_name: jQuery( '#country-name' ).val(),
				country_code: jQuery( '#country-code' ).val()
			},
		success: function( response ) {
      if ( 'undefined' !== typeof response ) {
				if ( 'undefined' !== typeof response ) {

				}
			}

			console.log( response );
		},
		error: function( request, status, error ) {
			alert( request.responseText );
		}
	});
});

function clearLocation() {
  document.getElementById( 'latitude' ).value = '';
  document.getElementById( 'longitude' ).value = '';
  document.getElementById( 'street-address' ).value = '';
  document.getElementById( 'extended-address' ).value = '';
  document.getElementById( 'locality' ).value = '';
  document.getElementById( 'region' ).value = '';
  document.getElementById( 'postal-code' ).value = '';
  document.getElementById( 'country-name' ).value = '';
  document.getElementById( 'country-code' ).value = '';
  document.getElementById( 'address' ).value = '';
  document.getElementById( 'location-name' ).value = '';
}

function error( err ) {
	alert( err.message );
};

jQuery( document ).ready( function( $ ) {
	$postTimezoneSelect = $( '#post-timezone-select' );
	$locationDetail = $( '#location-detail' );
	$labelDetail = $( '#timezone-browser' );
	$postTimezoneSelect.siblings( 'a.edit-post-timezone' ).click( function( event ) {
		event.preventDefault();
		if ( $postTimezoneSelect.is( ':hidden' ) ) {
			$postTimezoneSelect.slideDown( 'fast', function() {
				$postTimezoneSelect.find( 'select' ).focus();
});
			$( this ).hide();
		}
	});
	$postTimezoneSelect.find( '.save-post-timezone' ).click( function( event ) {
		$postTimezoneSelect.slideUp( 'fast' ).siblings( 'a.edit-post-timezone' ).show().focus();
		$( '#post-timezone-label' ).text( $( '#post-timezone' ).val() );
		event.preventDefault();
	});
	$postTimezoneSelect.find( '.cancel-post-timezone' ).click( function( event ) {
		$postTimezoneSelect.slideUp( 'fast' ).siblings( 'a.edit-post-timezone' ).show().focus();
		$( '#post_timezone' ).val( $( '#hidden_post_timezone' ).val() );
		event.preventDefault();
	});
	$locationDetail.siblings( 'a.show-location-details' ).click( function( event ) {
		event.preventDefault();
		if ( $locationDetail.is( ':hidden' ) ) {
			$locationDetail.slideDown( 'fast' ).siblings( 'a.hide-location-details' ).show().focus();
		} else {
			$locationDetail.slideUp( 'fast' ).siblings( 'a.show-location-details' ).focus();
		}

	});
	$labelDetail.click( function( event ) {
		$( '#post-timezone' ).val( jstz.determine().name() );
		$( '#post-timezone-label' ).text( jstz.determine().name() );
		event.preventDefault();
	});

});
