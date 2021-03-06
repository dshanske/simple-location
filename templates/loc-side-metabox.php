<?php
/**
 * Location Sidebar Metabox.
 *
 * @package Simple_Location
 */

$screen = get_current_screen();
if ( 'comment' === $screen->id ) {
	$geodata = WP_Geo_Data::get_geodata( get_comment( $comment ) );
} else {
	$geodata = WP_Geo_Data::get_geodata();
}
$weather      = ifset( $geodata['weather'], array() );
$location     = wp_get_object_terms( get_the_ID(), 'location', array( 'fields' => 'ids' ) );
$location     = count( $location ) >= 1 ? $location[0] : '';
$display_name = ifset( $geodata['address'] );

$units    = get_option( 'sloc_measurements' );
$imperial = ( 'imperial' === $units );
if ( $imperial ) {
	$weather = Weather_Provider::metric_to_imperial( $weather );
}
$wind = ifset( $weather['wind'], array() );
$trip = ifset( $geodata['trip'], array() );

$public     = array_key_exists( 'visibility', $geodata ) ? $geodata['visibility'] : get_option( 'geo_public' );
$choices    = WP_Geo_Data::geo_public();
$map_return = '';
$zone       = '';
if ( isset( $geodata['latitude'] ) && isset( $geodata['longitude'] ) ) {
	$zone         = Location_Zones::in_zone( $geodata['latitude'], $geodata['longitude'] );
	$map_provider = Loc_Config::map_provider();
	$map_args     = array(
		'latitude'  => ifset( $geodata['latitude'] ),
		'longitude' => ifset( $geodata['longitude'] ),
		'height'    => 200,
		'width'     => 200,
		'map_zoom'  => ifset( $geodata['map_zoom'] ),
	);

	$map_provider->set( array_filter( $map_args ) );
	$map_return = $map_provider->get_the_map();
}

?>

<div class="location-section location-section-main">
	<?php wp_nonce_field( 'location_metabox', 'location_metabox_nonce' ); ?>
	<button class="dashicons-before dashicons-location-alt location-ajax" id="location-title" title="<?php esc_html_e( 'Location', 'simple-location' ); ?>"> <?php esc_html_e( 'Location:', 'simple-location' ); ?></button>
	<span id="location-label"><?php echo esc_html( ifset( $display_name, __( 'None', 'simple-location' ) ) ); ?></span>
	<a href="#location" class="edit-location hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">Location</span>
	<div id="location-fields" class="field-row hide-if-js">
		<label for="address"><?php esc_html_e( 'Textual Description:', 'simple-location' ); ?></label>
		<input type="text" name="address" id="address" value="<?php echo esc_html( ifset( $geodata['address'] ) ); ?>" class="widefat" data-role="none" />

	<label for="location"><?php esc_html_e( 'Location:', 'simple-location' ); ?></label>
	<?php
	wp_dropdown_categories(
		array(
			'taxonomy'         => 'location',
			'class'            => 'widefat',
			'hide_empty'       => 0,
			'name'             => 'tax_input[location][]',
			'id'               => 'location_dropdown',
			'selected'         => $location,
			'orderby'          => 'name',
			'hierarchical'     => 1,
			'show_option_none' => __( 'No Location', 'simple-location' ),

		)
	);
	?>

		<label for="latitude" class="quarter">
			<?php esc_html_e( 'Latitude:', 'simple-location' ); ?>
			<input type="text" name="latitude" id="latitude" class="widefat" value="<?php echo esc_html( ifset( $geodata['latitude'], '' ) ); ?>" />
		   </label>

		<label for="longitude" class="quarter">
		<?php esc_html_e( 'Longitude:', 'simple-location' ); ?>

		<input type="text" name="longitude" id="longitude" class="widefat" value="<?php echo esc_attr( ifset( $geodata['longitude'], '' ) ); ?>" />
		</label>

		<label for="altitude" class="quarter">
			<?php esc_html_e( 'Altitude:', 'simple-location' ); ?>
			<input class="widefat" type="number" name="altitude" id="altitude" step="0.01" value="<?php echo esc_attr( ifset( $geodata['altitude'], '' ) ); ?>" />
		</label>
		<p class="field-row">
			<label for="location_icon">
				<?php esc_html_e( 'Icon:', 'simple-location' ); ?>
			</label>
			<select name="location_icon" id="location_icon">
				<?php Loc_View::icon_select( ifset( $geodata['icon'] ), true ); ?>" />
			</select>
		</p>

		<label for="map_zoom"><?php esc_html_e( 'Map Zoom:', 'simple-location' ); ?></label>
		<input type="number" name="map_zoom" id="map_zoom" class="widefat" max="20" min="1" value="<?php echo esc_attr( ifset( $geodata['map_zoom'], '' ) ); ?>" />

	<span id="hide-map"><?php echo wp_kses_post( $map_return ); ?></span>
		<input type="hidden" name="accuracy" id="accuracy" step="0.01" value="<?php echo esc_attr( ifset_round( $geodata['accuracy'], 2, '' ) ); ?>" />
		<input type="hidden" name="heading" id="heading" step="0.1" value="<?php echo esc_attr( ifset_round( $geodata['heading'], 2, '' ) ); ?>" />
		<input type="hidden" name="speed" id="speed" value="<?php echo esc_attr( ifset( $geodata['speed'], '' ) ); ?>" />

		<p>
			<a href="#location" class="lookup-location hide-if-no-js button button-primary"><?php esc_html_e( 'Lookup', 'simple-location' ); ?></a>
			<a href="#location" class="clear-location-button button-cancel"><?php esc_html_e( 'Clear', 'simple-location' ); ?></a>
			<a href="#location" class="hide-location hide-if-no-js button-secondary"><?php esc_html_e( 'Minimize', 'simple-location' ); ?></a>

		</p>
	</div><!-- #location-fields -->
</div><!-- .location-section -->

<!-- Remove Trip Visibility for Now
<div class="location-section location-section-trip">
	<span class="dashicons-before dashicons-car" id="location-visibility-title" title="<?php esc_html_e( 'Trip', 'simple-location' ); ?>"> <?php esc_html_e( 'Trip:', 'simple-location' ); ?></span>
	<span id="trip-label"><?php echo isset( $geodata['trip'] ) ? esc_html__( 'Set', 'simple-location' ) : esc_html__( 'None', 'simple-location' ); ?></span>
	<a href="#location-trip" class="edit-location-trip hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">Trip</span>
	<div id="trip-data" class="field-row hide-if-js">
			<p class="field-row">	
		 <label for="trip_path">
			   <?php esc_html_e( 'Path: ', 'simple-location' ); ?>
		 </label>
		<input type="text" name="trip_path" id="trip_path" value="<?php echo esc_attr( ifset( $trip['path'] ) ); ?>" />
			</p>
			<a href="#location-trip" class="save-location-trip hide-if-no-js button">OK</a>
			<a href="#location-trip" class="cancel-location-trip hide-if-no-js button-cancel">Cancel</a>
	</div>
</div>
-->

<div class="location-section location-section-visibility">
	<span class="dashicons-before dashicons-hidden" id="location-visibility-title" title="<?php esc_html_e( 'Visibility', 'simple-location' ); ?>"> <?php esc_html_e( 'Visibility:', 'simple-location' ); ?></span>
	<span id="location-visibility-label"><?php echo esc_html( $choices[ $public ] ); ?></span>
	<a href="#location-visibility" class="edit-location-visibility hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">Location Visibility</span>

	<div id="location-visibility-select" class="hide-if-js">
		<input type="hidden" name="hidden_location_visibility" id="hidden_location_visibility" value="<?php echo esc_attr( $public ); ?>" />
		<select name="geo_public" id="location-visibility" width="90%"><?php echo WP_Geo_Data::geo_public_select( $public ); // phpcs:ignore ?></select>
		<a href="#location-visibility" class="save-location-visibility hide-if-no-js button">OK</a>
		<a href="#location-visibility" class="cancel-location-visibility hide-if-no-js button-cancel">Cancel</a>
	</div><!-- #location-visibility-select -->
</div><!-- .location-section -->

<div class="location-section location-section-weather">
	<span class="dashicons-before dashicons-palmtree" id="weather-title" title="<?php esc_html_e( 'Weather', 'simple-location' ); ?>"> <?php esc_html_e( 'Weather:', 'simple-location' ); ?></span>
	<span id="weather-label"><?php echo esc_html( ifset( $weather['summary'], __( 'None', 'simple-location' ) ) ); ?></span>
	<a href="#weather" class="edit-weather hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">weather</span>

	<div id="weather-fields" class="field-row hide-if-js">
		<p class="field-row">
			<label for="temperature">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'temperature', $imperial ) ); ?>
			</label>
			<input type="number" name="temperature" step="0.01" id="temperature" value="<?php echo esc_attr( ifset_round( $weather['temperature'], 2, '' ) ); ?>" class="widefat" />
		</p>

		<p class="field-row">
			<label for="humidity">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'humidity', $imperial ) ); ?>
			</label>
			<input type="number" min="0" max="100" name="humidity" id="humidity" step="0.01" value="<?php echo esc_attr( ifset_round( $weather['humidity'], 2, '' ) ); ?>" class="widefat" />
		</p>

		<p class="field-row">
			<label for="weather_summary" class="half">
				<?php esc_html_e( 'Weather Description: ', 'simple-location' ); ?>
			</label>
			<input class="widefat" type="text" name="weather_summary" id="weather_summary" value="<?php echo esc_html( ifset( $weather['summary'], '' ) ); ?>" />
		</p>

		<p class="field-row">
			<label for="weather_icon">
				<?php esc_html_e( 'Icon', 'simple-location' ); ?>
			</label>
			<select name="weather_icon" id="weather_icon">
				<?php Weather_Provider::icon_select( ifset( $weather['icon'] ), true ); // phpcs:ignore ?>" />
			</select>
		</p>

		<p class="field-row">
			<label for="wind_degree">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'wind-degree', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" max="360" name="wind_degree" id="wind_degree" value="<?php echo esc_attr( ifset( $wind['degree'], '' ) ); ?>" />
		</p>

		<p class="field-row">
			<label for="wind_speed">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'wind-speed', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" name="wind_speed" id="wind_speed" step="0.01" value="<?php echo esc_attr( ifset_round( $wind['speed'], 2, '' ) ); ?>" />
		</p>

		<p class="field-row">
			<label for="pressure">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'pressure', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" name="pressure" id="pressure" step="0.01" value="<?php echo esc_attr( ifset_round( $weather['pressure'], 2, '' ) ); ?>" />
		</p>

		<p class="field-row">
			<label for="weather_visibility">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'visibility', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" name="weather_visibility" id="weather_visibility" step="0.01" value="<?php echo esc_attr( ifset_round( $weather['visibility'], 2, '' ) ); ?>" />
		</p>

		<p class="field-row">
			<label for="cloudiness">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'cloudiness', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" max="100" name="cloudiness" id="cloudiness" value="<?php echo esc_attr( ifset( $weather['cloudiness'], '' ) ); ?>" />
		</p>

		<p class="field-row">
			<label for="rain">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'rain', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" name="rain" step="0.01" id="rain" value="<?php echo esc_attr( ifset_round( $weather['rain'], 2, '' ) ); ?>" />
		</p>

		<p class="field-row">
			<label for="snow">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'snow', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" name="snow" step="0.01" id="snow" value="<?php echo esc_attr( ifset_round( $weather['snow'], 2, '' ) ); ?>" />
		</p>

		<p>
			<a href="#weather" class="hide-weather hide-if-no-js button">OK</a>
			<a href="#weather" class="hide-weather hide-if-no-js button-cancel">Cancel</a>
		</p>
	</div>
</div>
