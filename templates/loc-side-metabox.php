<?php
$screen = get_current_screen();
if ( 'comment' === $screen->id ) {
	$geodata = WP_Geo_Data::get_geodata( get_comment( $comment ) );
} else {
	$geodata = WP_Geo_Data::get_geodata();
}
$weather = ifset( $geodata['weather'], array() );
$wind    = ifset( $weather['wind'], array() );
$public =  array_key_exists( 'visibility', $geodata ) ? $geodata['visibility'] : get_option( 'geo_public' );
$choices = WP_Geo_Data::geo_public();
$map_return = '';
$zone = '';
if ( isset( $geodata['latitude'] ) && isset( $geodata['longitude'] ) ) {
	$zone = Location_Zones::in_zone( $geodata['latitude'], $geodata['longitude'] );
	$map      = Loc_Config::map_provider();
	$map_args = array(
		'latitude'  => ifset( $geodata['latitude'] ),
		'longitude' => ifset( $geodata['longitude'] ),
		'height'    => 200,
		'width'     => 200,
		'map_zoom' => ifset( $geodata['map_zoom'] )
	);
	
	$map->set( array_filter( $map_args ) );
	$map_return = $map->get_the_map();
}

?>

<div class="location-section location-section-main">
	<?php wp_nonce_field( 'location_metabox', 'location_metabox_nonce' ); ?>
	<button class="dashicons-before dashicons-location-alt location-ajax" id="location-title" title="<?php esc_html_e( 'Location', 'simple-location' ); ?>"> <?php esc_html_e( 'Location:', 'simple-location' ); ?></button>
	<span id="location-label"><?php echo ifset( $geodata['address'], __( 'None', 'simple-location' ) ); ?></span>
	<a href="#location" class="edit-location hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">Location</span>
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
            <input class="widefat" type="text" name="altitude" id="altitude" value="<?php echo ifset( $geodata['altitude'], '' ); ?>" />
        </label>

        <label for="map_zoom"><?php _e( 'Map Zoom:', 'simple-location' ); ?></label>
        <input type="text" name="map_zoom" id="map_zoom" class="widefat" value="<?php echo ifset( $geodata['map_zoom'], '' ); ?>" />

	<span id="hide-map"><?php echo $map_return; ?></span>
        <input type="hidden" name="accuracy" id="accuracy" value="<?php echo ifset( $geodata['accuracy'], '' ); ?>" />
        <input type="hidden" name="heading" id="heading" value="<?php echo ifset( $geodata['heading'], '' ); ?>" />
        <input type="hidden" name="speed" id="speed" value="<?php echo ifset( $geodata['speed'], '' ); ?>" />

		<p>
            <a href="#location" class="lookup-location hide-if-no-js button button-primary"><?php _e( 'Lookup', 'simple-location' ); ?></a>
            <a href="#location" class="clear-location-button button-cancel"><?php _e( 'Clear', 'simple-location' ); ?></a>
            <a href="#location" class="hide-location hide-if-no-js button-secondary"><?php _e( 'Hide', 'simple-location' ); ?></a>

        </p>
	</div><!-- #location-fields -->
</div><!-- .location-section -->

<div class="location-section location-section-visibility">
	<span class="dashicons-before dashicons-hidden" id="location-visibility-title" title="<?php esc_html_e( 'Visibility', 'simple-location' ); ?>"> <?php esc_html_e( 'Visibility:', 'simple-location' ); ?></span>
	<span id="location-visibility-label"><?php echo $choices[ $public ]; ?></span>
	<a href="#location-visibility" class="edit-location-visibility hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">Location Visibility</span>

    <div id="location-visibility-select" class="hide-if-js">
    	<input type="hidden" name="hidden_location_visibility" id="hidden_location_visibility" value="<?php echo esc_attr( $public ); ?>" />
    	<select name="geo_public" id="location-visibility" width="90%"><?php echo WP_Geo_Data::geo_public_select( $public ); ?></select>
        <a href="#location-visibility" class="save-location-visibility hide-if-no-js button">OK</a>
        <a href="#location-visibility" class="cancel-location-visibility hide-if-no-js button-cancel">Cancel</a>
	</div><!-- #location-visibility-select -->
</div><!-- .location-section -->

<div class="location-section location-section-weather">
	<span class="dashicons-before dashicons-palmtree" id="weather-title" title="<?php esc_html_e( 'Weather', 'simple-location' ); ?>"> <?php esc_html_e( 'Weather:', 'simple-location' ); ?></span>
	<span id="weather-label"><?php echo ifset( $weather['summary'], __( 'None', 'simple-location' ) ); ?></span>
	<a href="#weather" class="edit-weather hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">weather</span>

    <div id="weather-fields" class="field-row hide-if-js">
        <p class="field-row">
            <label for="temperature">
                <?php _e( 'Temperature(C): ', 'simple-location' ); ?>
            </label>
            <input type="text" name="temperature" id="temperature" value="<?php echo ifset( $weather['temperature'], '' ); ?>" class="widefat" />
        </p>

        <p class="field-row">
            <label for="humidity">
                <?php _e( 'Humidity: ', 'simple-location' ); ?>
            </label>
            <input type="text" name="humidity" id="humidity" value="<?php echo ifset( $weather['humidity'], '' ); ?>" class="widefat" />
        </p>

        <p class="field-row">
            <label for="weather_summary" class="half">
                <?php _e( 'Weather Description: ', 'simple-location' ); ?>
            </label>
            <input class="widefat" type="text" name="weather_summary" id="weather_summary" value="<?php echo ifset( $weather['summary'], '' ); ?>" />
        </p>

        <p class="field-row">
            <label for="weather_icon">
                <?php _e( 'Icon', 'simple-location' ); ?>
            </label>
            <select name="weather_icon" id="weather_icon">
                <?php Weather_Provider::icon_select( ifset( $weather['icon'] ), true ); ?>" />
            </select>
        </p>

        <p class="field-row">
            <label for="wind_degree">
                <?php _e( 'Wind Degree', 'simple-location' ); ?>
            </label>
            <input class="widefat" type="text" name="wind_degree" id="wind_degree" value="<?php echo ifset( $wind['degree'], '' ); ?>" />
        </p>

        <p class="field-row">
            <label for="wind_speed">
                <?php _e( 'Wind Speed', 'simple-location' ); ?>
            </label>
            <input class="widefat" type="text" name="wind_speed" id="wind_speed" value="<?php echo ifset( $wind['speed'], '' ); ?>" />
        </p>

        <p class="field-row">
            <label for="pressure">
                <?php _e( 'Pressure', 'simple-location' ); ?>
            </label>
            <input class="widefat" type="text" name="pressure" id="pressure" value="<?php echo ifset( $weather['pressure'], '' ); ?>" />
        </p>

        <p class="field-row">
            <label for="weather_visibility">
                <?php _e( 'Visibility', 'simple-location' ); ?>
            </label>
            <input class="widefat" type="text" name="weather_visibility" id="weather_visibility" value="<?php echo ifset( $weather['visibility'], '' ); ?>" />
        </p>

        <p class="field-row">
            <label for="cloudiness">
                <?php _e( 'Cloudiness', 'simple-location' ); ?>
            </label>
            <input class="widefat" type="text" name="cloudiness" id="cloudiness" value="<?php echo ifset( $weather['cloudiness'], '' ); ?>" />
        </p>

        <p class="field-row">
            <label for="rain">
                <?php _e( 'Rain', 'simple-location' ); ?>
            </label>
            <input class="widefat" type="text" name="rain" id="rain" value="<?php echo ifset( $weather['rain'], '' ); ?>" />
        </p>

        <p class="field-row">
            <label for="snow">
                <?php _e( 'Snow', 'simple-location' ); ?>
            </label>
            <input class="widefat" type="text" name="snow" id="snow" value="<?php echo ifset( $weather['snow'], '' ); ?>" />
        </p>

        <p>
            <a href="#weather" class="hide-weather hide-if-no-js button">OK</a>
            <a href="#weather" class="hide-weather hide-if-no-js button-cancel">Cancel</a>
        </p>
	</div>
</div>
