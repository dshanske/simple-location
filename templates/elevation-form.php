<?php
/**
 * Elevation Lookup Debug Form.
 *
 * @package Simple_Location
 */

$providers = Loc_Config::elevation_providers();
?>
<h2><?php esc_html_e( 'Elevation Lookup', 'simple-location' ); ?></h2>
<form method="get" action="<?php echo esc_url( rest_url( '/sloc_geo/1.0/elevation/' ) ); ?> ">
	<p><label for="latitude"><?php esc_html_e( 'Latitude', 'simple-location' ); ?></label><input type="text" class="widefat" name="latitude" id="latitude" /></p>
	<p><label for="longitude"><?php esc_html_e( 'Longitude', 'simple-location' ); ?></label><input type="text" class="widefat" name="longitude" id="longitude" /></p>
	<p><label for="provider"><?php esc_html_e( 'Elevation Provider', 'simple-location' ); ?></label><select name="provider">
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
