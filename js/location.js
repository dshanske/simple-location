jQuery( document ).ready( function( $ ) {

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
		if ( '' === $( '#longitude' ).val() ) {
			$( '#longitude' ).val( position.coords.longitude ) ;
		}
		if ( '' === $( '#latitude' ).val() ) {
			$( '#latitude' ).val( position.coords.latitude ) ;
		}
		$( '#accuracy' ).val( position.coords.accuracy );
		$( '#heading' ).val( position.coords.heading );
		$( '#speed' ).val( position.coords.speed );
		$( '#altitude' ).val( position.coords.altitude );
		$( '#map_zoom' ).val( parseInt( Math.log2( 591657550.5 / ( position.coords.accuracy * 45 ) ) ) + 1 );
		$.ajax({
				type: 'GET',

				// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
				url: sloc.api_url + 'reverse/',
				beforeSend: function( xhr ) {

					// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
					xhr.setRequestHeader( 'X-WP-Nonce', sloc.api_nonce );
				},
				data: {
					latitude: $( '#latitude' ).val(),
					longitude: $( '#longitude' ).val(),
					altitude: $( '#altitude' ).val(),
					map_zoom: $( '#map_zoom' ).val()
				},
				success: function( response ) {
					if ( 'undefined' == typeof response ) {
					} else {
						if ( ( 'display-name' in response ) && ( '' === $( '#address' ).val() ) ) {
							$( '#address' ).val( response['display-name']) ;
						}
						if ( 'name' in response ) {
							$( '#location-name' ).val( response.name ) ;
						}
						if ( 'latitude' in response ) {
							$( '#latitude' ).val( response.latitude ) ;
						}
						if ( 'longitude' in response ) {
							$( '#longitude' ).val( response.longitude ) ;
						}
						if ( 'street-address' in response ) {
							$( '#street-address' ).val( response['street-address']) ;

						}
						if ( 'extended-address' in response ) {
							$( '#extended-address' ).val( response['extended-address']) ;
						}
						if ( 'locality' in response ) {
							$( '#locality' ).val( response.locality ) ;
						}
						if ( 'region' in response ) {
							$( '#region' ).val( response.region ) ;
						}
						if ( 'postal-code' in response ) {
							$( '#postal-code' ).val( response['postal-code']) ;
						}
						if ( 'country-name' in response ) {
							$( '#country-name' ).val( response['country-name']) ;
						}
						if ( 'country-code' in response ) {
							$( '#country-code' ).val( response['country-code']) ;
						}
						if ( 'timezone' in response ) {
							$( '#post-timezone' ).val( response.timezone ) ;
							$( '#post-timezone-label' ).text( response.timezone );
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
		$.ajax({
			type: 'GET',

			// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
			url: sloc.api_url + 'weather/',
			beforeSend: function( xhr ) {

				// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
				xhr.setRequestHeader( 'X-WP-Nonce', sloc.api_nonce );
			},
			data: {
				latitude: $( '#latitude' ).val(),
				longitude: $( '#longitude' ).val()
			},
			success: function( response ) {
				if ( 'undefined' == typeof response ) {
				} else {
					if ( ( 'temperature' in response ) && ( '' === $( '#temperature' ).val() ) ) {
						$( '#temperature' ).val( response.temperature ) ;
					}
					if ( ( 'humidity' in response ) && ( '' === $( '#humidity' ).val() ) ) {
						$( '#humidity' ).val( response.humidity ) ;
					}
					if ( ( 'icon' in response ) && ( '' === $( '#weather_icon' ).val() ) ) {
						$( '#weather_icon' ).val( response.icon ) ;
					}
					if ( ( 'summary' in response ) && ( '' === $( '#weather_summary' ).val() ) ) {
						$( '#weather_summary' ).val( response.summary ) ;
					}
					if ( ( 'pressure' in response ) && ( '' === $( '#pressure' ).val() ) ) {
						$( '#pressure' ).val( response.pressure ) ;
					}
					if ( ( 'visibility' in response ) && ( '' === $( '#visibility' ).val() ) ) {
						$( '#visibility' ).val( response.visibility ) ;
					}
					if ( 'wind' in response ) {
						if ( 'speed' in response.wind ) {
							$( '#wind_speed' ).val( response.wind.speed ) ;
						}
						if ( 'degree' in response.wind ) {
							$( '#wind_degree' ).val( response.wind.degree ) ;
						}
					}
					if ( 'units' in response ) {
						$( '#units' ).val( response.units ) ;
					}
					console.log( response );
				}
			},
			error: function( request, status, error ) {
				alert( request.responseText );
			}
		});
	}

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
	}


	$( document )
		.on( 'click', '.lookup-weather-button', function() {
			getWeather();
		})
		.on( 'click', '.lookup-address-button', function() {
			getFullLocation();
		})
		.on( 'click', '.save-venue-button', function() {
			$.ajax({
				type: 'POST',

				// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
				url: sloc.api_url + 'reverse/',
				beforeSend: function( xhr ) {

					// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
					xhr.setRequestHeader( 'X-WP-Nonce', sloc.api_nonce );
				},
				data: {
					action: 'save_venue_data',
					latitude: $( '#latitude' ).val(),
					longitude: $( '#longitude' ).val(),
					location_name: $( '#location-name' ).val(), // eslint-disable-line camelcase
					street_address: $( '#street-address' ).val(), // eslint-disable-line camelcase
					extended_address: $( '#extended-address' ).val(), // eslint-disable-line camelcase
					locality: $( '#locality' ).val(),
					region: $( '#region' ).val(),
					postal_code: $( '#postal-code' ).val(), // eslint-disable-line camelcase
					country_name: $( '#country-name' ).val(), // eslint-disable-line camelcase
					country_code: $( '#country-code' ).val() // eslint-disable-line camelcase
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
