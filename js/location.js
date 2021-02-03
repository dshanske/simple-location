jQuery( document ).ready( function( $ ) {
	const DateTime = luxon.DateTime;
	let weather = 1;
	const originalDate = DateTime.fromObject(
		{
			year: $( '#hidden_aa' ).val(),
			month: $( '#hidden_mm' ).val(),
			day: $( '#hidden_jj' ).val(),
			hour: $( '#hidden_hh' ).val(),
			minute: $( '#hidden_mn' ).val(),
			second: $( '#hidden_ss' ).val(),
			zone: $( '#timezone_default' ).val(),
		}
	);
	const currentDate = DateTime.fromObject(
		{
			year: $( '#cur_aa' ).val(),
			month: $( '#cur_mm' ).val(),
			day: $( '#cur_jj' ).val(),
			hour: $( '#cur_hh' ).val(),
			minute: $( '#cur_mn' ).val(),
			zone: $( '#timezone_default' ).val(),
		}
	);
	let time = '';
	function lookupLocation() {
		const attemptedDate = DateTime.fromObject(
			{
				year: $( '#aa' ).val(),
				month: $( '#mm' ).val(),
				day: $( '#jj' ).val(),
				hour: $( '#hh' ).val(),
				minute: $( '#mn' ).val(),
				second: $( '#ss' ).val(),
				zone: $( '#timezone_default' ).val(),
			}
		);
		const options = {
			enableHighAccuracy: true,
			maximumAge: 600000,
		};
		if ( attemptedDate < currentDate ) {
			time = attemptedDate.toISO();
		}
		if ( 'HTML5' === slocOptions.lookup ) { // eslint-disable-line camelcase
			if ( ( '' === $( '#latitude' ).val() ) && ( '' === $( '#longitude' ).val() ) ) {
				if ( navigator.geolocation ) {
					navigator.geolocation.getCurrentPosition( setLocation, error, options );
				} else {
					alert( 'Geolocation is not supported by this browser.' );
				}
			} else {
				reverseLookup();
			}
		} else {
			getCurrentPosition();
			reverseLookup();
		}
	}

	function getCurrentPosition() {
		let position;
		$.ajax( {
			type: 'GET',

			// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
			url: slocOptions.api_url + 'lookup/',
			beforeSend( xhr ) {
				// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
				xhr.setRequestHeader( 'X-WP-Nonce', slocOptions.api_nonce );
			},
			data: {
				time,
				address: $( '#address' ).val(),

			},
			success( response ) {
				if ( window.console ) {
					console.log( response );
				}

				if ( 'undefined' === typeof response ) {
					return null;
				}
				position = { timestamp: ( new Date() ).getTime(), coords: response };
				setLocation( position, time );
			},
			error( request, status, error ) {
				alert( request.responseText );
			},
		} );
	}

	function setLocation( position ) {
		if ( '' === $( '#longitude' ).val() ) {
			$( '#longitude' ).val( position.coords.longitude );
		}
		if ( '' === $( '#latitude' ).val() ) {
			$( '#latitude' ).val( position.coords.latitude );
		}
		$( '#accuracy' ).val( position.coords.accuracy );
		$( '#heading' ).val( position.coords.heading );
		$( '#speed' ).val( position.coords.speed );
		$( '#altitude' ).val( position.coords.altitude );

		if ( position.coords.hasOwnProperty( 'annotation' ) ) {
			$( '#address' ).val( position.coords.annotation );
			$( '#location-label' ).text( position.coords.annotation );
		}
		if ( position.coords.hasOwnProperty( 'icon' ) ) {
			$( '#location_icon' ).val( position.coords.icon );
		}
		if ( position.coords.hasOwnProperty( 'zoom' ) ) {
			$( '#map_zoom' ).val( position.coords.zoom );
		} else {
			$( '#map_zoom' ).val( parseInt( Math.log2( 591657550.5 / ( position.coords.accuracy * 45 ) ) ) + 1 );
		}
		reverseLookup();
	}

	function reverseLookup() {
		if ( ( '' === $( '#longitude' ).val() ) && ( '' === $( '#latitude' ).val() ) ) {
			return;
		}

		$.ajax( {
			type: 'GET',

			// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
			url: slocOptions.api_url + 'geocode/',
			beforeSend( xhr ) {
				// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
				xhr.setRequestHeader( 'X-WP-Nonce', slocOptions.api_nonce );
			},
			data: {
				latitude: $( '#latitude' ).val(),
				longitude: $( '#longitude' ).val(),
				altitude: $( '#altitude' ).val(),
				weather,
				map_zoom: $( '#map_zoom' ).val(), // eslint-disable-line camelcase
				height: 200,
				width: 200,
				time,
				units: slocOptions.units,
			},
			success( response ) {
				if ( 'undefined' === typeof response ) {
				} else {
					if ( ( 'display-name' in response ) && ( '' === $( '#address' ).val() ) ) {
						$( '#address' ).val( response[ 'display-name' ] );
						$( '#location-label' ).text( response[ 'display-name' ] );
					}
					if ( 'name' in response ) {
						$( '#location-name' ).val( response.name );
					}
					if ( 'latitude' in response && ( '' === $( '#latitude' ).val() ) ) {
						$( '#latitude' ).val( response.latitude );
					}
					if ( 'longitude' in response && ( '' === $( '#longitude' ).val() ) ) {
						$( '#longitude' ).val( response.longitude );
					}
					if ( 'map_return' in response ) {
						$( '#hide-map' ).html( response.map_return );
					}
					if ( 'altitude' in response && ( '' === $( '#altitude' ).val() ) ) {
						$( '#altitude' ).val( response.altitude );
					}

					if ( 'term_id' in response ) {
						$('#location_dropdown').append( new Option( response.term_name, response.term_id, false, false ) );
						$('#location_dropdown').val(response['term_id']).change();
					}

					if ( 'visibility' in response ) {
						$( '#location-visibility' ).val( response[ 'visibility' ] ); // eslint-disable-line dot-notation
						$( '#location-visibility-label' ).text( slocOptions.visibility_options[ $( '#location-visibility' ).val() ] ); // eslint-disable-line camelcase
					}
					if ( 'timezone' in response ) {
						$( '#post-timezone' ).val( response.timezone );
						$( '#post-timezone-label' ).text( response.timezone );
						$( '#local-timezone' ).val( response.timezone );
					}
					if ( 'weather' in response ) {
						weather = response.weather;
						if ( ( 'temperature' in weather ) && ( '' === $( '#temperature' ).val() ) ) {
							$( '#temperature' ).val( weather.temperature );
						}
						if ( ( 'humidity' in weather ) && ( '' === $( '#humidity' ).val() ) ) {
							$( '#humidity' ).val( weather.humidity );
						}
						if ( ( 'icon' in weather ) && ( 'none' === $( '#weather_icon' ).val() ) ) {
							$( '#weather_icon' ).val( weather.icon ).change();
						}
						if ( ( 'summary' in weather ) && ( '' === $( '#weather_summary' ).val() ) ) {
							$( '#weather_summary' ).val( weather.summary );
							$( '#weather-label' ).text( weather.summary );
						}
						if ( ( 'pressure' in weather ) && ( '' === $( '#pressure' ).val() ) ) {
							$( '#pressure' ).val( weather.pressure );
						}
						if ( ( 'cloudiness' in weather ) && ( '' === $( '#cloudiness' ).val() ) ) {
							$( '#cloudiness' ).val( weather.cloudiness );
						}
						if ( ( 'rain' in weather ) && ( '' === $( '#rain' ).val() ) ) {
							$( '#rain' ).val( weather.rain );
						}
						if ( ( 'snow' in weather ) && ( '' === $( '#snow' ).val() ) ) {
							$( '#snow' ).val( weather.snow );
						}
						if ( ( 'visibility' in weather ) && ( '' === $( '#weather_visibility' ).val() ) ) {
							$( '#weather_visibility' ).val( weather.visibility );
						}
						if ( 'wind' in weather ) {
							if ( 'speed' in weather.wind ) {
								$( '#wind_speed' ).val( weather.wind.speed );
							}
							if ( 'degree' in weather.wind ) {
								$( '#wind_degree' ).val( weather.wind.degree );
							}
						}
						if ( 'units' in weather ) {
							$( '#units' ).val( weather.units );
						}
					}

					if ( window.console ) {
						console.log( response );
					}
				}
			},
			error( request, status, error ) {
				alert( request.responseText );
			},
			always: hideLoadingSpinner(),
		} );
	}

	function clearLocation() {
		const fieldIds = [
			'address',
			'latitude',
			'longitude',
			'altitude',
			'map_zoom',
			'temperature',
			'humidity',
			'speed',
			'heading',
			'weather_summary',
			'weather_icon',
			'wind_speed',
			'wind_degree',
			'weather-visibility',
			'pressure',
			'cloudiness',
		];
		if ( ! confirm( 'Are you sure you want to remove the location details?' ) ) {
			return;
		}
		$.each( fieldIds, function( count, val ) {
			document.getElementById( val ).value = '';
		} );
		$( '#location-label' ).text( 'None' );
		$( '#weather-label' ).text( 'None' );
		$( '#hide-map' ).html( '' );
	}

	function showLoadingSpinner() {
		$( '#locationsidebox' ).addClass( 'is-loading' );
	}

	function hideLoadingSpinner() {
		$( '#locationsidebox' ).removeClass( 'is-loading' );
	}

	function error( err ) {
		alert( err.message );
	}

	$( document )
		.on( 'click', '.lookup-address-button', function( event ) {
			showLoadingSpinner();
			lookupLocation();
			event.preventDefault();
		} )
		.on( 'click', '.clear-location-button', function( event ) {
			clearLocation();
			event.preventDefault();
		} )
		.on( 'click', '.save-venue-button', function() {
			$.ajax( {
				type: 'POST',

				// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
				url: sloc.api_url + 'reverse/',
				beforeSend( xhr ) {
					// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
					xhr.setRequestHeader( 'X-WP-Nonce', slocOptions.api_nonce );
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
					country_code: $( '#country-code' ).val(), // eslint-disable-line camelcase
				},
				success( response ) {
					if ( 'undefined' !== typeof response ) {
						if ( 'undefined' !== typeof response ) {
						}
					}
					console.log( response );
				},
				error( request, status, error ) {
					alert( request.responseText );
				},
			} );
		} );

	$postTimezoneSelect = $( '#post-timezone-select' );
	$locationDetail = $( '#location-details' );
	$TimezoneDetail = $( '#timezone-browser' );

	$postTimezoneSelect.siblings( 'a.edit-post-timezone' ).click( function( event ) {
		if ( $postTimezoneSelect.is( ':hidden' ) ) {
			$postTimezoneSelect.slideDown( 'fast', function() {
				$postTimezoneSelect.find( 'select' ).focus();
			} );
			$( this ).hide();
		}
		event.preventDefault();
	} );

	$postTimezoneSelect.find( '.save-post-timezone' ).click( function( event ) {
		$postTimezoneSelect.slideUp( 'fast' ).siblings( 'a.edit-post-timezone' ).show().focus();
		$( '#post-timezone-label' ).text( $( '#post-timezone' ).val() );
		event.preventDefault();
	} );

	$postTimezoneSelect.find( '.cancel-post-timezone' ).click( function( event ) {
		$postTimezoneSelect.slideUp( 'fast' ).siblings( 'a.edit-post-timezone' ).show().focus();
		$( '#post_timezone' ).val( $( '#hidden_post_timezone' ).val() );
		event.preventDefault();
	} );

	$postLocationFields = $( '#location-fields' );

	$postLocationFields.siblings( 'a.edit-location' ).click( function( event ) {
		if ( $postLocationFields.is( ':hidden' ) ) {
			$postLocationFields.slideDown( 'fast', function() {
				$postLocationFields.find( 'select' ).focus();
			} );
			$( this ).hide();
		}
		event.preventDefault();
	} );

	$postLocationFields.find( '.lookup-location' ).click( function( event ) {
		showLoadingSpinner();
		lookupLocation();
		$postLocationFields.slideUp( 'fast' ).siblings( 'a.edit-location' ).show().focus();
		event.preventDefault();
	} );

	$postLocationFields.find( '.hide-location' ).click( function( event ) {
		$postLocationFields.slideUp( 'fast' ).siblings( 'a.edit-location' ).show().focus();
		$( '#location-label' ).text( $( '#address' ).val() ); // eslint-disable-line camelcase
		event.preventDefault();
	} );

	$postLocationSelect = $( '#location-visibility-select' );

	$postLocationSelect.siblings( 'a.edit-location-visibility' ).click( function( event ) {
		if ( $postLocationSelect.is( ':hidden' ) ) {
			$postLocationSelect.slideDown( 'fast', function() {
				$postLocationSelect.find( 'select' ).focus();
			} );
			$( this ).hide();
		}
		event.preventDefault();
	} );

	$postLocationSelect.find( '.save-location-visibility' ).click( function( event ) {
		$postLocationSelect.slideUp( 'fast' ).siblings( 'a.edit-location-visibility' ).show().focus();
		$( '#location-visibility-label' ).text( slocOptions.visibility_options[ $( '#location-visibility' ).val() ] ); // eslint-disable-line camelcase
		event.preventDefault();
	} );

	$postLocationSelect.find( '.cancel-location-visibility' ).click( function( event ) {
		$postLocationSelect.slideUp( 'fast' ).siblings( 'a.edit-location-visibility' ).show().focus();
		$( '#location-visibility' ).val( $( '#hidden_location_visibility' ).val() );
		event.preventDefault();
	} );

	$postTripData = $( '#trip-data' );

	$postTripData.siblings( 'a.edit-location-trip' ).click( function( event ) {
		if ( $postTripData.is( ':hidden' ) ) {
			$postTripData.slideDown( 'fast', function() {
				$postTripData.find( 'select' ).focus();
			} );
			$( this ).hide();
		}
		event.preventDefault();
	} );

	$postTripData.find( '.cancel-location-trip' ).click( function( event ) {
		$postTripData.slideUp( 'fast' ).siblings( 'a.edit-location-trip' ).show().focus();
		event.preventDefault();
	} );

	$postTripData.find( '.save-location-trip' ).click( function( event ) {
		$postTripData.slideUp( 'fast' ).siblings( 'a.edit-location-trip' ).show().focus();
		event.preventDefault();
	} );


	$postWeatherFields = $( '#weather-fields' );

	$postWeatherFields.siblings( 'a.edit-weather' ).click( function( event ) {
		if ( $postWeatherFields.is( ':hidden' ) ) {
			$postWeatherFields.slideDown( 'fast', function() {
				$postWeatherFields.find( 'select' ).focus();
			} );
			$( this ).hide();
		}
		event.preventDefault();
	} );

	$postWeatherFields.find( '.hide-weather' ).click( function( event ) {
		$postWeatherFields.slideUp( 'fast' ).siblings( 'a.edit-weather' ).show().focus();
		$( '#weather-label' ).text( $( '#weather_summary' ).val() );
		event.preventDefault();
	} );

	$LocationDetail = $( '#location-lookup' );

	$( 'a.show-location-details' ).click( function( event ) {
		if ( $locationDetail.is( ':hidden' ) ) {
			$locationDetail.slideDown( 'fast' ).siblings( 'a.hide-location-details' ).show().focus();
		} else {
			$locationDetail.slideUp( 'fast' ).siblings( 'a.show-location-details' ).focus();
		}
		event.preventDefault();
	} );

	$( '#location-title' ).click( function( event ) {
		showLoadingSpinner();
		lookupLocation();
		return false;
	} );
} );
