<?php
$screen = get_current_screen();
if ( 'comment' === $screen->id ) {
	$geodata = WP_Geo_Data::get_geodata( get_comment( $comment ) );
} else {
	$geodata = WP_Geo_Data::get_geodata();
}
$weather = ifset( $geodata['weather'], array() );
$wind    = ifset( $weather['wind'], array() );
$public = isset( $geodata['visibility'] ) ? $geodata['visibility'] : WP_Geo_Data::get_visibility();
$choices = WP_Geo_Data::geo_public();

?>

<div class="location-section location-section-main">
	<?php wp_nonce_field( 'location_metabox', 'location_metabox_nonce' ); ?>
	<span class="dashicons dashicons-location-alt" id="location-title" title="<?php esc_html_e( 'Location', 'simple-location' ); ?>"></span>
	<label for="location"><?php esc_html_e( 'Location:', 'simple-location' ); ?></label>
	<span id="location-label"><?php echo ifset( $geodata['address'], __( 'None', 'simple-location' ) ); ?></span>
	<a href="#location" class="edit-location hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">Location</span>
	<br />
	<div id="location-fields" class="field-row hide-if-js">
			<label for="address"><?php _e( 'Location:', 'simple-location' ); ?></label>
                          <input type="text" name="address" id="address" value="<?php echo ifset( $geodata['address'] ); ?>" class="widefat" data-role="none" />
		<label for="latitude" class="quarter">
        		<?php _e( 'Latitude:', 'simple-location' ); ?>
        		<input type="text" name="latitude" id="latitude" class="widefat" value="<?php echo ifset( $geodata['latitude'], '' ); ?>" />
       		 </label>
		<label for="longitude" class="quarter">
			<?php _e( 'Longitude:', 'simple-location' ); ?>
			<input type="text" name="longitude" id="longitude" class="widefat" value="<?php echo ifset( $geodata['longitude'], '' ); ?>" />
			</label>
                               <label for="altitude" class="quarter">
                                        <?php _e( 'Altitude:', 'simple-location' ); ?>
                                        <input type="text" name="altitude" id="altitude" value="<?php echo ifset( $geodata['altitude'], '' ); ?>" />
                                </label>
                                <p>
                                        <label for="map_zoom"><?php _e( 'Map Zoom:', 'simple-location' ); ?></label>
                                        <input type="text" name="map_zoom" id="map_zoom" class="widefat" value="<?php echo ifset( $geodata['map_zoom'], '' ); ?>" />

				</p>
			<span id="hide-map"></span>
                        <input type="hidden" name="accuracy" id="accuracy" value="<?php echo ifset( $geodata['accuracy'], '' ); ?>" />
                        <input type="hidden" name="heading" id="heading" value="<?php echo ifset( $geodata['heading'], '' ); ?>" />
                        <input type="hidden" name="speed" id="speed" value="<?php echo ifset( $geodata['speed'], '' ); ?>" />

			<a href="#location" class="lookup-location hide-if-no-js button"><?php _e( 'Lookup', 'simple-location' ); ?></a>
			<a href="#location" class="hide-location hide-if-no-js"><?php _e( 'Hide', 'simple-location' ); ?></a>
			<a href="#location" class="clear-location-button"><?php _e( 'Clear', 'simple-location' ); ?></a>
	</div>
</div>
<div class="location-section location-section-visibility">
	<span class="dashicons dashicons-hidden" id="location-visibility-title" title="<?php esc_html_e( 'Visibility', 'simple-location' ); ?>"></span>
	<label for="location-visibility"><?php esc_html_e( 'Visibility:', 'simple-location' ); ?></label>
	<span id="location-visibility-label"><?php echo $choices[ $public ]; ?></span>
	<a href="#location-visibility" class="edit-location-visibility hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">Location Visibility</span>
	<br />
<div id="location-visibility-select" class="hide-if-js">
	<input type="hidden" name="hidden_location_visibility" id="hidden_location_visibility" value="<?php echo esc_attr( $public ); ?>" />
	<input type="hidden" name="location_visibility_default" id="location_visibility_default" value="<?php echo esc_attr( get_option( 'geo_public' ) ); ?>" />
	<select name="geo_public" id="location-visibility" width="90%"><?php echo WP_Geo_Data::geo_public_select( $public ); ?></select>
	<a href="#location-visibility" class="save-location-visibility hide-if-no-js button">OK</a>
	<a href="#location-visibility" class="cancel-location-visibility hide-if-no-js button-cancel">Cancel</a>
	</div>
</div>
<div class="location-section location-section-weather">

	<span class="dashicons dashicons-palmtree" id="weather-title" title="<?php esc_html_e( 'Weather', 'simple-location' ); ?>"></span>
	<label for="weather"><?php esc_html_e( 'Weather:', 'simple-location' ); ?></label>
	<span id="weather-label"><?php echo 'None'; ?></span>
	<a href="#weather" class="edit-weather hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">weather</span>
	<br />
<div id="weather-fields" class="field-row hide-if-js">
                        <p class="field-row">
                                <label for="temperature" class="quarter">
                                        <?php _e( 'Temperature(C): ', 'simple-location' ); ?>
                                        <input type="text" name="temperature" id="temperature" value="<?php echo ifset( $weather['temperature'], '' ); ?>" class="widefat" />
                                </label>

                                <label for="humidity" class="quarter">
                                        <?php _e( 'Humidity: ', 'simple-location' ); ?>
                                        <input type="text" name="humidity" id="humidity" value="<?php echo ifset( $weather['humidity'], '' ); ?>" class="widefat" />
                                </label>
                        </p>
                        <p class="field-row">
                        <label for="humidity" class="half">
                                <?php _e( 'Weather Description: ', 'simple-location' ); ?>
                                <input type="text" name="weather_summary" id="weather_summary" value="<?php echo ifset( $weather['summary'], '' ); ?>" />
                        </p>
                        <p class="field-row">
                                        <label for="weather_icon" class="quarter"><?php _e( 'Icon', 'simple-location' ); ?>
                                        <select name="weather_icon" id="weather_icon">
                                                <?php Weather_Provider::icon_select( ifset( $weather['icon'] ), true ); ?>" />
                                        </select>
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
                                        <label for="cloudiness" class="quarter"><?php _e( 'Cloudiness', 'simple-location' ); ?>
                                        <input type="text" name="cloudiness" id="cloudiness" value="<?php echo ifset( $weather['cloudiness'], '' ); ?>" />
                                        </label>
                                </p>
                                <p class="field-row">
                                        <label for="rain" class="quarter"><?php _e( 'Rain', 'simple-location' ); ?>
                                        <input type="text" name="rain" id="rain" value="<?php echo ifset( $weather['rain'], '' ); ?>" />
                                        </label>
                                        <label for="snow" class="quarter"><?php _e( 'Snow', 'simple-location' ); ?>
                                        <input type="text" name="snow" id="snow" value="<?php echo ifset( $weather['snow'], '' ); ?>" />
                                        </label>
                                </p>




<br />
	<a href="#weather" class="hide-weather hide-if-no-js button">OK</a>
	</div>
</div>
