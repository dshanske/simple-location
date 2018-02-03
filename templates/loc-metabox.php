<?php
$screen = get_current_screen();
if ( 'comment' === $screen->id ) {
	$geodata = WP_Geo_Data::get_geodata( get_comment( $comment ) );
} else {
	$geodata = WP_Geo_Data::get_geodata( get_post() );
}
$weather = ifset( $geodata['weather'], array() );
$wind    = ifset( $weather['wind'], array() );
?>
		<div class="location hide-if-no-js">
			<?php wp_nonce_field( 'location_metabox', 'location_metabox_nonce' ); ?>
			<p>
				<?php if ( 'comment' === $screen->id ) { ?>
					<select name="geo_public">
					<?php echo Loc_Metabox::geo_public_select( ifset( $geodata['public'], get_option( 'geo_public' ) ) ); ?>
					</select>
				<?php } ?>
				<button
					class="lookup-address-button button button-primary"
					aria-label="<?php _e( 'Location Lookup', 'simple-location' ); ?>"
					title="<?php _e( 'Location Lookup', 'simple-location' ); ?>
				">
					<?php _e( 'Lookup my Location', 'simple-location' ); ?>
				</button>
				<button class="clear-location-button button">
					<?php _e( 'Clear Location', 'simple-location' ); ?>
				</button>

			</p>
			<p><?php _e( 'You can lookup your current location or information about the location you enter', 'simple-location' ); ?></p>

			<label for="address"><?php _e( 'Location:', 'simple-location' ); ?></label>
			<input type="text" name="address" id="address" value="<?php echo ifset( $geodata['address'] ); ?>" class="widefat" data-role="none" />

			<p class="field-row">
				<label for="latitude" class="quarter">
					<?php _e( 'Latitude:', 'simple-location' ); ?>
					<input type="text" name="latitude" id="latitude" class="widefat" value="<?php echo ifset( $geodata['latitude'], '' ); ?>" />
				</label>

				<label for="longitude" class="quarter">
					<?php _e( 'Longitude:', 'simple-location' ); ?>
					<input type="text" name="longitude" id="longitude" class="widefat" value="<?php echo ifset( $geodata['longitude'], '' ); ?>" />
				</label>
			</p>
			<p class="field-row">
				<label for="temperature" class="quarter">
					<?php _e( 'Temperature: ', 'simple-location' ); ?>
					<input type="text" name="temperature" id="temperature" value="<?php echo ifset( $weather['temperature'], '' ); ?>" class="widefat" />
				</label>

				<label for="humidity" class="quarter">
					<?php _e( 'Humidity: ', 'simple-location' ); ?>
					<input type="text" name="humidity" id="humidity" value="<?php echo ifset( $weather['humidity'], '' ); ?>" class="widefat" />
				</label>

			</p>

			<input type="hidden" name="accuracy" id="accuracy" value="<?php echo ifset( $geodata['accuracy'], '' ); ?>" />
			<input type="hidden" name="heading" id="heading" value="<?php echo ifset( $geodata['heading'], '' ); ?>" />
			<input type="hidden" name="speed" id="speed" value="<?php echo ifset( $geodata['speed'], '' ); ?>" />
			<input type="hidden" name="altitude" id="altitude" value="<?php echo ifset( $geodata['altitude'], '' ); ?>" />


			<p>
				<a href="#location-details" class="show-location-details hide-if-no-js"><?php _e( 'Show Additional Details', 'simple-location' ); ?></a>
			</p>
			<div id="location-details" class="hide-if-js">
				<p><?php _e( 'Location Data below can be used to complete the location description, which will be displayed or is held for future use.', 'simple-location' ); ?></p>

				<p>
					<label for="map_zoom"><?php _e( 'Map Zoom:', 'simple-location' ); ?></label>
					<input type="text" name="map_zoom" id="map_zoom" class="widefat" value="<?php echo ifset( $geodata['map_zoom'], '' ); ?>" />
				</p>

				<p>
					<label for="location-name"><?php _e( 'Location Name', 'simple-location' ); ?></label>
					<input type="text" name="location-name" id="location-name" value="" class="widefat" />
				</p>
				<p>
					<label for="street-address"><?php _e( 'Address', 'simple-location' ); ?></label>
					<input type="text" name="street-address" id="street-address" value="" class="widefat" />
				</p>
				<p>
					<label for="extended-address"><?php _e( 'Extended Address', 'simple-location' ); ?></label>
					<input type="text" name="extended-address" id="extended-address" value="" class="widefat" />
				</p>

				<p>
					<label for="locality"><?php _e( 'City/Town/Village', 'simple-location' ); ?></label>
					<input type="text" name="locality" id="locality" value="<?php echo ifset( $address['locality'], '' ); ?>" class="widefat" />
				</p>
				<!--
				<p>
					<label for="extended-address"><?php _e( 'Neighborhood/Suburb', 'simple-location' ); ?></label>
					<input type="text" name="extended-address" id="extended-address" value="" class="widefat" />
				</p>
				-->
				<p class="field-row">
					<label for="region" class="three-quarters">
						<?php _e( 'State/County/Province', 'simple-location' ); ?>
						<input type="text" name="region" id="region" value="" class="widefat" />
					</label>

					<label for="postal-code" class="quarter">
						<?php _e( 'Postal Code', 'simple-location' ); ?>
						<input type="text" name="postal-code" id="postal-code" value="" class="widefat" />
					</label>
				</p>
				<p class="field-row">
					<label for="country-name" class="three-quarters"><?php _e( 'Country Name', 'simple-location' ); ?>
						<input type="text" name="country-name" id="country-name" value="" class="widefat" />
					</label>

					<label for="country-code" class="quarter"><?php _e( 'Country Code', 'simple-location' ); ?>
						<input type="text" name="country-code" id="country-code" value="" class="widefat" />
					</label>
				</p>
				<p class="field-row">
					<label for="wind_degree" class="quarter"><?php _e( 'Wind Degree', 'simple-location' ); ?>
					<input type="text" name="wind_degree" id="wind_degree" value="<?php echo ifset( $wind['degree'], '' ); ?>" />
					</label>
					<label for="wind_speed" class="quarter"><?php _e( 'Wind Speed', 'simple-location' ); ?>
					<input type="text" name="wind_speed" id="wind_speed" value="<?php echo ifset( $wind['speed'], '' ); ?>" />
					</label>
				</p>
				<p class="field-row">
					<label for="pressure" class="quarter"><?php _e( 'Pressure', 'simple-location' ); ?>
					<input type="text" name="pressure" id="pressure" value="<?php echo ifset( $weather['pressure'], '' ); ?>" />
					</label>
					<label for="visibility" class="quarter"><?php _e( 'Visibility', 'simple-location' ); ?>
					<input type="text" name="visibility" id="visibility" value="<?php echo ifset( $weather['visibility'], '' ); ?>" />
					</label>
				</p>
			<input type="hidden" name="weather_summary" id="weather_summary" value="<?php echo ifset( $weather['summary'], '' ); ?>" />
			<input type="hidden" name="weather_icon" id="weather_icon" value="<?php echo ifset( $weather['icon'], '' ); ?>" />
			<input type="hidden" name="units" id="units" value="<?php echo ifset( $wind['units'], Loc_Metabox::temp_unit() ); ?>" />


				<div class="button-group">
					<button type="button" class="save-venue-button button-secondary" disabled><?php _e( 'Save as Venue', 'simple-location' ); ?> </button>
				</div>
			</div>
		</div>


		<div class="loading">
			<img src="<?php echo esc_url( includes_url( '/images/wpspin-2x.gif' ) ); ?>" class="loading-spinner" />
		</div>
<?php
