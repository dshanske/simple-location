<?php
/**
 * Geo Lookup Debug Form.
 *
 * @package Simple_Location
 */

$providers = Loc_Config::geo_providers();
?>
<h2><?php esc_html_e( 'Location Lookup', 'simple-location' ); ?></h2>
<form method="get" action="<?php echo esc_url( rest_url( '/sloc_geo/1.0/geocode/' ) ); ?> ">
	<p><label for="latitude"><?php esc_html_e( 'Latitude', 'simple-location' ); ?></label><input type="text" class="widefat" name="latitude" id="latitude" /></p>
	<p><label for="longitude"><?php esc_html_e( 'Longitude', 'simple-location' ); ?></label><input type="text" class="widefat" name="longitude" id="longitude" /></p>
	<p><?php esc_html_e( 'Or Lookup By Address', 'simple-location' ); ?></p>
	<p><label for="address"><?php esc_html_e( 'Address', 'simple-location' ); ?></label><input type="text" class="widefat" name="address" id="address" /></p>
	<p><label for="term"><?php esc_html_e( 'Add New Term if Not Found', 'simple-location' ); ?></label><input type="checkbox" name="term" /></>
	<p><label for="provider"><?php esc_html_e( 'Geocoding Provider', 'simple-location' ); ?></label><select name="provider">
		<?php
		foreach ( $providers as $key => $value ) {
			printf( '<option value="%1$s">%2$s</option>', $key, $value['name'] ); // phpcs:ignore
		}
		?>
		</select>
	</p>
	<?php wp_nonce_field( 'wp_rest' ); ?>
	<?php submit_button( __( 'Lookup', 'simple-location' ) ); ?>
</form>
