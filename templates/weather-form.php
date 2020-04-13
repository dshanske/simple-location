<hr />
<h2><?php esc_html_e( 'Weather Lookup', 'simple-location' );?></h2>
<form method="get" action="<?php echo esc_url( rest_url( '/sloc_geo/1.0/weather/' ) ); ?> ">
	<p><label for="latitude"><?php esc_html_e( 'Latitude', 'simple-location' ); ?></label><input type="text" class="widefat" name="latitude" id="latitude" /></p>
	<p><label for="longitude"><?php esc_html_e( 'Longitude', 'simple-location' ); ?></label><input type="text" class="widefat" name="longitude" id="longitude" /></p>
	<p><label for="station"><?php esc_html_e( 'Station', 'simple-location' ); ?></label><input type="text" class="widefat" name="station" id="station" /></p>
	<?php wp_nonce_field( 'wp_rest' ); ?>
	<?php submit_button( __( 'Lookup', 'simple-location' ) ); ?>
</form>
