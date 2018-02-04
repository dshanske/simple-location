<?php
global $profileuser;
$geodata = WP_Geo_Data::get_geodata( $profileuser );
?>
<div id="locationbox-meta">
<h3><?php esc_html_e( 'Last Reported Location', 'simple-location' ); ?></h3>
<p><?php esc_html_e( 'This allows you to set the last reported location for this author. See Simple Location settings for options.', 'simple-location' ); ?></p>
<button
                                        class="lookup-address-button button button-primary"
                                        aria-label="<?php _e( 'Location Lookup', 'simple-location' ); ?>"
                                        title="<?php _e( 'Location Lookup', 'simple-location' ); ?>
                                ">
                                        <?php _e( 'Use My Current Location', 'simple-location' ); ?>

				</button>
<table class="form-table">
<div class="loading">
	<img src="<?php echo esc_url( includes_url( '/images/wpspin-2x.gif' ) ); ?>" class="loading-spinner" />
</div>
<tr><th><label for="latitude"><?php _e( 'Latitude', 'simple-location'); ?></label></th>
<td>
<input type="text" name="latitude" id="latitude" value="<?php echo ifset( $geodata['latitude'], '' ); ?>" class="regular-text" />
<br />
<span class="description"><?php _e( 'Latitude', 'simple-location' ); ?></span>
</td>
</tr>
<tr><th><label for="longitude"><?php _e( 'Longitude', 'simple-location'); ?></label></th>
<td>
<input type="text" name="longitude" id="longitude" value="<?php echo ifset( $geodata['longitude'], '' ); ?>" class="regular-text" />
<br />
<span class="description"><?php _e( 'Longitude', 'simple-location' ); ?></span>
</td>
</tr>
<tr><th><label for="address"><?php _e( 'Address', 'simple-location'); ?></label></th>
<td>
<input type="text" name="address" id="address" value="<?php echo ifset( $geodata['address'], '' ); ?>" class="regular-text" />
<br />
<span class="description"><?php _e( 'Address', 'simple-location' ); ?></span>
</td>
</tr>
<?php Loc_Metabox::geo_public_user( ifset( $geodata['public'], get_option( 'geo_public' ) ) ); ?>
</table>
</div>
