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
					<?php echo WP_Geo_Data::geo_public_select( $geodata['visibility'] ); ?>
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

			<p>
				<a href="#location-details" class="show-location-details hide-if-no-js"><?php _e( 'Show Additional Details', 'simple-location' ); ?></a>
			</p>
			<div id="location-details" class="hide-if-js">
				<p><?php _e( 'Location Data below can be used to complete the location description, which will be displayed or is held for future use.', 'simple-location' ); ?></p>

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


<?php
