<?php
/**
 * Location Sidebar Metabox.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Loaded with load_template() inside a function, so these variables are local.

$screen = get_current_screen();
if ( 'comment' === $screen->id ) {
	$geodata   = get_comment_geodata( get_comment_ID() );
	$sloc_type = '';
} else {
	$sloc_type = get_post_type();
	$geodata   = get_post_geodata();
	if ( 'venue' === $sloc_type ) {
		$geodata['venue_radius'] = get_post_meta( get_the_ID(), 'venue_radius', true );
		$geodata['venue_url']    = get_post_meta( get_the_ID(), 'venue_url', true );
		$geodata['post_visibility'] = Post_Venue::get_post_visibility( get_the_ID() );
	} else {
		$geodata['venue_id'] = get_post_meta( get_the_ID(), 'venue_id', true );
	}
}


$location = wp_get_object_terms( get_the_ID(), 'location', array( 'fields' => 'ids' ) );
$location = count( $location ) >= 1 ? $location[0] : '';
if ( is_array( $geodata ) && array_key_exists( 'venue_id', $geodata ) && $geodata['venue_id'] ) {
	$display_name = get_the_title( $geodata['venue_id'] );
} else {
	$display_name = $geodata['address'] ?? '';
}

// Without a stored visibility, use the site default, as private, public or protected rather than the option's number.
$public     = $geodata['visibility'] ?? Geo_Data::get_default_visibility();
$choices    = Geo_Base::geo_public();
$map_return = '';
if ( 'attachment' === $sloc_type ) {
	$choices = Geo_Base::visibility_choices( get_the_ID() );
	// An attachment without a visibility of its own inherits one.
	if ( '' === get_post_meta( get_the_ID(), 'geo_public', true ) ) {
		$public = 'inherit';
	}
}
if ( isset( $geodata['latitude'] ) && isset( $geodata['longitude'] ) ) {
	$map_provider = Loc_Config::map_provider();
	$map_args     = array(
		'latitude'  => $geodata['latitude'] ?? '',
		'longitude' => $geodata['longitude'] ?? '',
		'height'    => 200,
		'width'     => 200,
		'map_zoom'  => $geodata['map_zoom'] ?? '',
	);
	if ( $map_provider ) {
		$map_provider->set( array_filter( $map_args ) );
		$map_return = $map_provider->get_the_map();
	}
}

?>

<div class="location-section location-section-main">
	<?php wp_nonce_field( 'location_metabox', 'location_metabox_nonce' ); ?>
	<button class="dashicons-before dashicons-location-alt location-ajax" id="location-title" title="<?php esc_html_e( 'Location', 'simple-location' ); ?>"> <?php esc_html_e( 'Location:', 'simple-location' ); ?></button>
	<span id="location-label"><?php echo esc_html( '' !== (string) $display_name ? $display_name : __( 'None', 'simple-location' ) ); ?></span>
	<a href="#location" class="edit-location hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">Location</span>
	<div id="location-fields" class="field-row hide-if-js">
		<label for="address"><?php esc_html_e( 'Textual Description:', 'simple-location' ); ?></label>
		<input type="text" name="address" id="address" value="<?php echo esc_html( $geodata['address'] ?? '' ); ?>" class="widefat" data-role="none" />

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

	<?php if ( 'venue' === $sloc_type ) { ?>
		<label for="venue_radius" class="quarter">
			<?php esc_html_e( 'Radius Around Venue:', 'simple-location' ); ?>
			<input class="widefat" type="number" name="venue_radius" id="venue_radius" step="1" min="1" value="<?php echo esc_attr( $geodata['venue_radius'] ?? '' ); ?>" />
		</label>
		<label for="venue_url" class="quarter">
			<?php esc_html_e( 'Venue URL:', 'simple-location' ); ?>
			<input class="widefat" type="url" name="venue_url" id="venue_url" value="<?php echo esc_attr( $geodata['venue_url'] ?? '' ); ?>" />
		</label>
		<label for="venue_post_visibility">
			<?php esc_html_e( 'Visibility of Posts Here:', 'simple-location' ); ?>
			<select name="venue_post_visibility" id="venue_post_visibility" class="widefat">
				<option value="" <?php selected( $geodata['post_visibility'] ?? '', '' ); ?>><?php esc_html_e( 'No default', 'simple-location' ); ?></option>
				<?php foreach ( Geo_Base::geo_public() as $sloc_value => $sloc_label ) : ?>
					<option value="<?php echo esc_attr( $sloc_value ); ?>" <?php selected( $geodata['post_visibility'] ?? '', $sloc_value ); ?>><?php echo esc_html( $sloc_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<p class="description"><?php esc_html_e( 'The location visibility given to posts made here, such as Private at home. You can still change it on each post.', 'simple-location' ); ?></p>

	<?php } else { ?>
		<label for="venue_id" class="quarter">
			<?php esc_html_e( 'Venue:', 'simple-location' ); ?>
		<!-- 	 <input class="widefat" type="number" name="venue_id" id="venue_id" step="1" min="1" value="<?php echo esc_attr( $geodata['venue_id'] ?? '' ); ?>" /> -->
			<?php
			$venue_args = array(
				'name'              => 'venue_id',
				'id'                => 'venue_id',
				'show_option_none'  => __( 'No Venue', 'simple-location' ),
				'option_none_value' => '',
				'hierarchical'      => true,
				'post_type'         => 'venue',
				'selected'          => $geodata['venue_id'] ?? '',
			);
			if ( $location ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Venues are listed by location.
				$venue_args['tax_query'] = array(
					array(
						'taxonomy' => 'location',
						'terms'    => $location,
					),
				);
			}
				wp_dropdown_pages( $venue_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the list.
			?>

		</label>
	<?php } ?>


		<p class="field-row">
			<label for="plus_code"><?php esc_html_e( 'Plus Code:', 'simple-location' ); ?></label>
			<input type="text" name="plus_code" id="plus_code" class="widefat" value="" autocomplete="off" aria-describedby="plus-code-description" />
			<span id="plus-code-description" class="description"><?php esc_html_e( 'Optional. A Plus Code, such as 87F6XR2P+WH, or a short one with the town after it, such as XR2P+WH Philadelphia. Lookup replaces the location with it.', 'simple-location' ); ?></span>
		</p>

		<label for="latitude" class="quarter">
			<?php esc_html_e( 'Latitude:', 'simple-location' ); ?>
			<input type="text" name="latitude" id="latitude" class="widefat" value="<?php echo esc_html( $geodata['latitude'] ?? '' ); ?>" />
			</label>

		<label for="longitude" class="quarter">
		<?php esc_html_e( 'Longitude:', 'simple-location' ); ?>

		<input type="text" name="longitude" id="longitude" class="widefat" value="<?php echo esc_attr( $geodata['longitude'] ?? '' ); ?>" />
		</label>

		<label for="altitude" class="quarter">
			<?php esc_html_e( 'Altitude:', 'simple-location' ); ?>
			<input class="widefat" type="number" name="altitude" id="altitude" step="0.01" value="<?php echo esc_attr( $geodata['altitude'] ?? '' ); ?>" />
		</label>

		<p class="field-row">
			<label for="location_icon">
				<?php esc_html_e( 'Icon:', 'simple-location' ); ?>
			</label>
			<select name="location_icon" id="location_icon">
				<?php Geo_Data::icon_select( $geodata['icon'] ?? '', true ); ?>" />
			</select>
		</p>

		<label for="map_zoom"><?php esc_html_e( 'Map Zoom:', 'simple-location' ); ?></label>
		<input type="number" name="map_zoom" id="map_zoom" class="widefat" max="20" min="1" value="<?php echo esc_attr( $geodata['map_zoom'] ?? '' ); ?>" />

	<span id="hide-map"><?php echo wp_kses_post( $map_return ); ?></span>
		<input type="hidden" name="accuracy" id="accuracy" step="0.01" value="<?php echo esc_attr( sloc_round_value( $geodata['accuracy'] ?? '', 2 ) ); ?>" />
		<input type="hidden" name="heading" id="heading" step="0.1" value="<?php echo esc_attr( sloc_round_value( $geodata['heading'] ?? '', 2 ) ); ?>" />
		<input type="hidden" name="speed" id="speed" value="<?php echo esc_attr( $geodata['speed'] ?? '' ); ?>" />

		<p>
			<a href="#location" class="lookup-location hide-if-no-js button button-primary"><?php esc_html_e( 'Lookup', 'simple-location' ); ?></a>
			<a href="#location" class="clear-location-button button-cancel"><?php esc_html_e( 'Clear', 'simple-location' ); ?></a>
			<a href="#location" class="hide-location hide-if-no-js button-secondary"><?php esc_html_e( 'Minimize', 'simple-location' ); ?></a>

		</p>
	</div><!-- #location-fields -->
</div><!-- .location-section -->

<div class="location-section location-section-visibility">
	<span class="dashicons-before dashicons-hidden" id="location-visibility-title" title="<?php esc_html_e( 'Visibility', 'simple-location' ); ?>"> <?php esc_html_e( 'Visibility:', 'simple-location' ); ?></span>
	<span id="location-visibility-label"><?php echo esc_html( $choices[ $public ] ); ?></span>
	<a href="#location-visibility" class="edit-location-visibility hide-if-no-js" role="button"><span aria-hidden="true">Edit</a><span class="screen-reader-text">Location Visibility</span>

	<div id="location-visibility-select" class="hide-if-js">
		<input type="hidden" name="hidden_location_visibility" id="hidden_location_visibility" value="<?php echo esc_attr( $public ); ?>" />
		<select name="geo_public" id="location-visibility" width="90%"><?php echo Geo_Base::geo_public_select( $public, false, $choices ); // phpcs:ignore ?></select>
		<a href="#location-visibility" class="save-location-visibility hide-if-no-js button">OK</a>
		<a href="#location-visibility" class="cancel-location-visibility hide-if-no-js button-cancel">Cancel</a>
	</div><!-- #location-visibility-select -->
</div><!-- .location-section -->
<?php
