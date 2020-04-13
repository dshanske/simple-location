<form method="get" action="<?php echo esc_url( rest_url( '/sloc_geo/1.0/geocode/' ) ); ?> ">
	<p><label for="latitude"><?php esc_html_e( 'Latitude', 'simple-location' ); ?></label><input type="text" class="widefat" name="latitude" id="latitude" /></p>
	<p><label for="longitude"><?php esc_html_e( 'Longitude', 'simple-location' ); ?></label><input type="text" class="widefat" name="longitude" id="longitude" /></p>
	<p><label for="weather"><?php esc_html_e( 'Retrieve Weather', 'simple-location' ); ?></label><input type="checkbox" name="weather" id="weather" /></p>
	<?php wp_nonce_field( 'wp_rest' ); ?>
	<?php submit_button( __( 'Lookup', 'simple-location' ) ); ?>
</form>
