jQuery( document ).ready( function( $ ) {
	const DateTime = luxon.DateTime;
	let weather = 1;

	/**
	 * Rounds a looked-up number to the decimal places its field's step allows (#741).
	 *
	 * @param {*}      value  Value from a lookup.
	 * @param {Object} $field Number field.
	 * @return {*} The number, rounded, or the value unchanged if it isn't a number.
	 */
	function fieldValue( value, $field ) {
		const number = parseFloat( value );
		if ( isNaN( number ) ) {
			return value;
		}
		const step = String( $field.attr( 'step' ) || '1' );
		const point = step.indexOf( '.' );
		return number.toFixed( -1 === point ? 0 : step.length - point - 1 );
	}

	/**
	 * Replaces the venue list, keeping the venue chosen, even if the new list doesn't have it (#247).
	 *
	 * @param {string} html New venue list.
	 */
	function replaceVenueSelect( html ) {
		const current = $( '#venue_id' ).val();
		const $chosen = $( '#venue_id option:selected' ).clone();
		$( '#venue_id' ).replaceWith( html );
		if ( ! current || '0' === current ) {
			return;
		}
		if ( 0 === $( '#venue_id option' ).filter( function() {
			return $( this ).val() === current;
		} ).length ) {
			$( '#venue_id' ).append( $chosen );
		}
		$( '#venue_id' ).val( current );
	}

	/**
	 * Fills a weather field with a looked-up value, rounded for display, and keeps the exact value, which is
	 * saved unless the field is changed (#741).
	 *
	 * @param {string} id    Field ID.
	 * @param {*}      value Value from a lookup.
	 */
	function setWeatherField( id, value ) {
		const $field = $( '#' + id );
		$field.val( fieldValue( value, $field ) );
		$( '#' + id + '_exact' ).val( isNaN( parseFloat( value ) ) ? '' : value );
	}
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
		// A Plus Code is looked up in place of the current location.
		if ( '' !== $.trim( $( '#plus_code' ).val() || '' ) ) {
			reverseLookup();
			return;
		}
		if ( 'HTML5' === slocOptions.lookup ) { // eslint-disable-line camelcase
			if ( ( '' === $( '#latitude' ).val() ) && ( '' === $( '#longitude' ).val() ) ) {
				if ( navigator.geolocation ) {
					navigator.geolocation.getCurrentPosition( setLocation, geolocationFailed, options );
				} else {
					geolocationFailed( null );
				}
			} else {
				reverseLookup();
			}
		} else {
			getCurrentPosition();
			reverseLookup();
		}
	}

	function updateVenues() {
		// "No Location" is -1 (the wp_dropdown_categories() default).
		if ( 0 < parseInt( $( '#location_dropdown' ).val(), 10 ) ) {
			$.ajax( {
				type: 'GET',
				// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
				url: slocOptions.api_url + 'venue/',
				beforeSend( xhr ) {
					// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
					xhr.setRequestHeader( 'X-WP-Nonce', slocOptions.api_nonce );
				},
				data: {
					location: $( '#location_dropdown' ).val(),
					select: 'true'
				},
				success( response ) {
					if ( window.console ) {
						console.log( response );
					}
					if ( 'undefined' === typeof response ) {
					} else {
						if ( ( 'venue_select' in response ) ) {
							replaceVenueSelect( response.venue_select );
						}
					}
				}
			} );
		}
	}

	// Places from the last venue search, as mf2 h-cards.
	let foundPlaces = [];

	/**
	 * Returns the first value of a place's property.
	 *
	 * @param {Object} place Place, as an mf2 h-card.
	 * @param {string} key   Property.
	 * @return {*} Value, or an empty string.
	 */
	function placeValue( place, key ) {
		if ( place && place.properties && Array.isArray( place.properties[ key ] ) && place.properties[ key ].length ) {
			return place.properties[ key ][ 0 ];
		}
		return '';
	}

	/**
	 * Lists the places near the latitude and longitude: the site's venues, then each provider's (#815).
	 */
	function findVenues() {
		const $box = $( '#sloc-places' );
		const latitude = $( '#latitude' ).val();
		const longitude = $( '#longitude' ).val();
		$box.prop( 'hidden', false );
		if ( ! latitude || ! longitude ) {
			$box.text( slocOptions.places.location );
			return;
		}
		$box.text( slocOptions.places.searching );
		$.ajax( {
			type: 'GET',
			url: slocOptions.api_url + 'venue/',
			beforeSend( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', slocOptions.api_nonce );
			},
			data: {
				latitude,
				longitude,
			},
			success( response ) {
				renderPlaces( response );
			},
			error( request ) {
				$box.text( request.responseJSON && request.responseJSON.message ? request.responseJSON.message : slocOptions.places.failed );
			},
		} );
	}

	/**
	 * Shows the places found, in a group for the site's venues and one for each provider.
	 *
	 * @param {Object} response Response, with 'places' and the providers' names by slug as 'providers'.
	 */
	function renderPlaces( response ) {
		const $box = $( '#sloc-places' ).empty();
		const names = ( response && response.providers ) ? response.providers : {};
		const groups = {};
		const order = [];
		foundPlaces = ( response && Array.isArray( response.places ) ) ? response.places : [];
		if ( ! foundPlaces.length ) {
			$box.text( slocOptions.places.none );
			return;
		}
		foundPlaces.forEach( function( place, index ) {
			const provider = placeValue( place, 'provider' ) || 'local';
			if ( ! ( provider in groups ) ) {
				groups[ provider ] = [];
				order.push( provider );
			}
			groups[ provider ].push( index );
		} );
		order.forEach( function( provider ) {
			const isLocal = 'local' === provider;
			const $group = $( '<div class="sloc-places-group"></div>' ).addClass( isLocal ? 'is-local' : 'is-provider' );
			$( '<h4></h4>' )
				.text( isLocal ? slocOptions.places.local : slocOptions.places.provider.replace( '%s', names[ provider ] || provider ) )
				.appendTo( $group );
			const $list = $( '<ul></ul>' ).appendTo( $group );
			groups[ provider ].forEach( function( index ) {
				const place = foundPlaces[ index ];
				const $item = $( '<li></li>' ).appendTo( $list );
				$( '<span class="sloc-place-badge"></span>' )
					.text( isLocal ? slocOptions.places.site : slocOptions.places.new )
					.appendTo( $item );
				$( '<span class="sloc-place-name"></span>' ).text( placeValue( place, 'name' ) ).appendTo( $item );
				const details = [ placeValue( place, 'street-address' ) || placeValue( place, 'label' ) ];
				if ( '' !== placeValue( place, 'distance' ) ) {
					details.push( slocOptions.places.meters.replace( '%s', placeValue( place, 'distance' ) ) );
				}
				const detail = details.filter( Boolean ).join( ', ' );
				if ( detail ) {
					$( '<span class="sloc-place-detail description"></span>' ).text( detail ).appendTo( $item );
				}
				$( '<button type="button" class="button button-small choose-place-button"></button>' )
					.attr( 'data-place', index )
					.text( slocOptions.places.use )
					.appendTo( $item );
			} );
			$box.append( $group );
		} );
	}

	/**
	 * Uses a place found for the post: its venue, which is created for a provider's place.
	 *
	 * @param {number} index Index of the place in foundPlaces.
	 */
	function choosePlace( index ) {
		const place = foundPlaces[ index ];
		if ( ! place ) {
			return;
		}
		$.ajax( {
			type: 'POST',
			url: slocOptions.api_url + 'venue/',
			contentType: 'application/json',
			beforeSend( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', slocOptions.api_nonce );
			},
			data: JSON.stringify( { place } ),
			success( response ) {
				if ( ! response || ! response.venue_id ) {
					return;
				}
				const id = String( response.venue_id );
				if ( 0 === $( '#venue_id option' ).filter( function() {
					return $( this ).val() === id;
				} ).length ) {
					$( '<option></option>' ).val( id ).text( response.title ).appendTo( '#venue_id' );
				}
				$( '#venue_id' ).val( id ).trigger( 'change' );
				$( '#sloc-places' ).prop( 'hidden', true ).empty();
				foundPlaces = [];
			},
			error( request ) {
				alert( request.responseJSON && request.responseJSON.message ? request.responseJSON.message : slocOptions.places.failed );
			},
		} );
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
					hideLoadingSpinner();
					return null;
				}
				position = { timestamp: ( new Date() ).getTime(), coords: response };
				setLocation( position, time );
			},
			error( request, status, error ) {
				hideLoadingSpinner();
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
		const plusCode = $.trim( $( '#plus_code' ).val() || '' );
		if ( ( '' === $( '#longitude' ).val() ) && ( '' === $( '#latitude' ).val() ) && ( '' === plusCode ) ) {
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
				plus_code: plusCode, // eslint-disable-line camelcase
				term: true,
				weather: slocOptions.weather,
				map_zoom: $( '#map_zoom' ).val(), // eslint-disable-line camelcase
				map_style: $( '#map_style' ).val(), // eslint-disable-line camelcase
				height: 200,
				width: 200,
				time,
				units: slocOptions.units,
			},
			success( response ) {
				if ( 'undefined' === typeof response ) {
				} else {
					// A Plus Code's location replaces the one in the form.
					if ( 'plus_code' in response ) {
						$( '#latitude' ).val( response.latitude );
						$( '#longitude' ).val( response.longitude );
						$( '#altitude' ).val( '' );
						$( '#address' ).val( '' );
						$( '#plus_code' ).val( '' );
					}
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
						$( '#altitude' ).val( fieldValue( response.altitude, $( '#altitude' ) ) );
					}

					if ( 'venue_id' in response && ( '' === $( '#venue_id' ).val() ) ) {
						$( '#venue_id' ).val( response.venue_id );
					}

					if ( 'terms' in response ) {
						$( '#location_dropdown' ).replaceWith( response.terms );
					}
					if ( 'nearby_select' in response ) {
						replaceVenueSelect( response.nearby_select );
					}

					if ( 'visibility' in response ) {
						$( '#location-visibility' ).val( response[ 'visibility' ] ); // eslint-disable-line dot-notation
						$( '#location-visibility-label' ).text( slocOptions.visibility_options[ $( '#location-visibility' ).val() ] ); // eslint-disable-line camelcase
					}
					if ( 'timezone' in response ) {
						$( '#post-timezone' ).val( response.timezone );
						$( '#post-timezone-label' ).text( response.timezone );
						$( '#local-timezone' ).val( response.timezone );
						$( '#user-timezone' ).val( response.timezone );
					}
					if ( 'weather' in response ) {
						weather = response.weather;
						if ( ( 'temperature' in weather ) && ( '' === $( '#temperature' ).val() ) ) {
							setWeatherField( 'temperature', weather.temperature );
						}
						if ( ( 'humidity' in weather ) && ( '' === $( '#humidity' ).val() ) ) {
							setWeatherField( 'humidity', weather.humidity );
						}
						if ( ( 'code' in weather ) && ( 'none' === $( '#weather_code' ).val() ) ) {
							$( '#weather_code' ).val( weather.code ).change();
							$( '#weather-label' ).text( weather.summary );
						}
						if ( ( 'pressure' in weather ) && ( '' === $( '#pressure' ).val() ) ) {
							setWeatherField( 'pressure', weather.pressure );
						}
						if ( ( 'cloudiness' in weather ) && ( '' === $( '#cloudiness' ).val() ) ) {
							setWeatherField( 'cloudiness', weather.cloudiness );
						}
						if ( ( 'rain' in weather ) && ( '' === $( '#rain' ).val() ) ) {
							setWeatherField( 'rain', weather.rain );
						}
						if ( ( 'snow' in weather ) && ( '' === $( '#snow' ).val() ) ) {
							setWeatherField( 'snow', weather.snow );
						}
						if ( ( 'visibility' in weather ) && ( '' === $( '#weather_visibility' ).val() ) ) {
							setWeatherField( 'weather_visibility', weather.visibility );
						}
						if ( 'windspeed' in weather && ( '' === $( '#windspeed' ).val() ) ) {
							setWeatherField( 'windspeed', weather.windspeed );
						}
						if ( 'winddegree' in weather && ( '' === $( '#winddegree' ).val() ) ) {
							setWeatherField( 'winddegree', weather.winddegree );
						}
						if ( 'units' in weather ) {
							$( '#units' ).val( weather.units );
						}
						// The provider the weather came from, for its credit.
						if ( 'provider' in weather && ( '' === $( '#weather_provider' ).val() ) ) {
							$( '#weather_provider' ).val( weather.provider );
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
			// When the lookup ends, whether it worked or not (#749).
			complete: hideLoadingSpinner,
		} );
	}

	let mapTimer;

	// Redraws the map preview, at the zoom in the form, without looking the location up again (#795).
	function redrawMap() {
		if ( ! $( '#hide-map' ).length || '' === $( '#latitude' ).val() || '' === $( '#longitude' ).val() ) {
			return;
		}
		$.ajax( {
			type: 'GET',
			url: slocOptions.api_url + 'map/',
			beforeSend( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', slocOptions.api_nonce );
			},
			data: {
				latitude: $( '#latitude' ).val(),
				longitude: $( '#longitude' ).val(),
				zoom: $( '#map_zoom' ).val(),
				style_family: $( '#map_style' ).val(), // eslint-disable-line camelcase
				height: 200,
				width: 200,
			},
			success( response ) {
				if ( response && 'return' in response ) {
					$( '#hide-map' ).html( response.return );
				}
			},
		} );
	}

	// Waits until typing stops, so a zoom of 12 is one request, not two.
	function scheduleMapRedraw() {
		clearTimeout( mapTimer );
		mapTimer = setTimeout( redrawMap, 500 );
	}

	function clearLocation() {
		// The location and weather fields; a screen may have only some of them (#743).
		const fieldIds = [
			'address',
			'latitude',
			'longitude',
			'altitude',
			'accuracy',
			'map_zoom',
			'map_style',
			'speed',
			'heading',
			'plus_code',
			'temperature',
			'humidity',
			'windspeed',
			'winddegree',
			'weather_visibility',
			'pressure',
			'cloudiness',
			'rain',
			'snow',
			'weather_provider',
		];
		if ( ! confirm( 'Are you sure you want to remove the location details?' ) ) {
			return;
		}
		$.each( fieldIds, function( count, id ) {
			$( '#' + id ).val( '' );
			$( '#' + id + '_exact' ).val( '' );
		} );
		$( '#weather_code' ).val( 'none' );
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

	/**
	 * Looks up the address typed when the browser doesn't give its location, because it can't or wasn't allowed to (#99).
	 *
	 * @param {?Object} err The browser's error, or null if it has no geolocation.
	 */
	function geolocationFailed( err ) {
		const address = $.trim( $( '#address' ).val() || '' );
		if ( '' === address ) {
			hideLoadingSpinner();
			alert( ( err && err.message ? err.message + ' ' : '' ) + 'Your browser did not give its location. Type an address, or the latitude and longitude, and look it up again.' );
			return;
		}
		geocodeAddress( address );
	}

	/**
	 * Finds the coordinates of an address, then looks up the rest of the location from them, keeping the address.
	 *
	 * @param {string} address Address.
	 */
	function geocodeAddress( address ) {
		$.ajax( {
			type: 'GET',
			url: slocOptions.api_url + 'geocode/',
			beforeSend( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', slocOptions.api_nonce );
			},
			data: {
				address,
			},
			success( response ) {
				if ( response && ( 'latitude' in response ) && ( 'longitude' in response ) ) {
					$( '#latitude' ).val( response.latitude );
					$( '#longitude' ).val( response.longitude );
					reverseLookup();
				} else {
					hideLoadingSpinner();
					alert( 'The address was not found.' );
				}
			},
			error( request ) {
				hideLoadingSpinner();
				alert( request.responseJSON && request.responseJSON.message ? request.responseJSON.message : 'The address was not found.' );
			},
		} );
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
		.on( 'input change', '#map_zoom', scheduleMapRedraw )
		.on( 'change', '#map_style', scheduleMapRedraw )
		.on( 'change', '#latitude, #longitude', scheduleMapRedraw )
		.on( 'change', '#location_dropdown', function( event ) {
			updateVenues();
			event.preventDefault();
		} )
		.on( 'click', '.find-venues-button', function( event ) {
			findVenues();
			event.preventDefault();
		} )
		.on( 'click', '.choose-place-button', function( event ) {
			choosePlace( parseInt( $( this ).attr( 'data-place' ), 10 ) );
			event.preventDefault();
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
