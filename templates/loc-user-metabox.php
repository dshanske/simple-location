<?php
/**
 * Location User Metabox.
 *
 * @package Simple_Location
 */

global $profileuser;
$geodata = get_user_geodata( $profileuser );
$geodata = is_array( $geodata ) ? $geodata : array();
?>
<div id="locationbox-meta">
<h3><?php esc_html_e( 'Last Reported Location', 'simple-location' ); ?></h3>
<p><?php esc_html_e( 'This allows you to set the last reported location for this author. This can be automatically updated when you post. See Simple Location settings for options.', 'simple-location' ); ?></p>

<?php if ( 'dummy' !== get_option( 'sloc_geolocation_provider' ) ) { ?>
<button	class="lookup-address-button button button-primary" aria-label="<?php esc_html_e( 'Location Lookup', 'simple-location' ); ?>" title="<?php esc_html_e( 'Location Lookup', 'simple-location' ); ?>">
	<?php esc_html_e( 'Use My Current Location', 'simple-location' ); ?></button>
<?php } ?>
<table class="form-table">
<tr><th><label for="latitude"><?php esc_html_e( 'Latitude', 'simple-location' ); ?></label></th>
<td>
<input type="text" name="latitude" id="latitude" value="<?php echo esc_attr( ifset( $geodata['latitude'], '' ) ); ?>" class="regular-text" />
<br />
<span class="description"><?php esc_html_e( 'Latitude', 'simple-location' ); ?></span>
</td>
</tr>
<tr><th><label for="longitude"><?php esc_html_e( 'Longitude', 'simple-location' ); ?></label></th>
<td>
<input type="text" name="longitude" id="longitude" value="<?php echo esc_attr( ifset( $geodata['longitude'], '' ) ); ?>" class="regular-text" />
<br />
<span class="description"><?php esc_html_e( 'Longitude', 'simple-location' ); ?></span>
</td>
</tr>
<tr><th><label for="timezone"><?php esc_html_e( 'Time Zone', 'simple-location' ); ?></label></th>
<td>
<select name="timezone" id="user-timezone" width="90%">
<?php echo Loc_Timezone::wp_timezone_choice( ifset( $geodata['timezone'] ) ); // phpcs:ignore ?>
</select>
<br />
<span class="description"><?php esc_html_e( 'Time Zone', 'simple-location' ); ?></span>
</td>
</tr>
<tr><th><label for="altitude"><?php esc_html_e( 'Altitude', 'simple-location' ); ?></label></th>
<td>
<input type="text" name="altitude" id="altitude" value="<?php echo esc_attr( ifset( $geodata['altitude'], '' ) ); ?>" class="regular-text" />
<br />
<span class="description"><?php esc_html_e( 'Altitude', 'simple-location' ); ?></span>
</td>
</tr>
<tr><th><label for="address"><?php esc_html_e( 'Address', 'simple-location' ); ?></label></th>
<td>
<input type="text" name="address" id="address" value="<?php echo esc_attr( ifset( $geodata['address'], '' ) ); ?>" class="regular-text" />
<br />
<span class="description"><?php esc_attr_e( 'Address', 'simple-location' ); ?></span>
</td>
</tr>
<?php Geo_Base::geo_public_user( ifset( $geodata['visibility'] ) ); // phpcs:ignore ?>
</table>
</div>
