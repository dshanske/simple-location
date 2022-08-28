<?php
/**
 * Venue Edit Field Template
 *
 */


$geodata = get_term_geodata( intval( $_REQUEST['tag_ID'] ) );
?>
<tr class="form-field">
	<tr>
		<th><label for="latitude"><?php esc_html_e( 'Latitude:', 'simple-location' ); ?></label></th>
		<td><input type="text" name="latitude" id="latitude" value="<?php echo ifset( $geodata['latitude'] );?>" size="10" /></td></tr>   
	<tr>									
	<th><label for="longitude"><?php esc_html_e( 'Longitude:', 'simple-location' ); ?></label></th>
	<td><input type="text" name="longitude" id="longitude" value="<?php echo ifset( $geodata['longitude'] );?>" size="10" />  </td>
	</tr>

	<tr>
		<td><button type="button" class="button lookup-address-button"><?php esc_html_e( 'Get Location', 'simple-location' ); ?></button></td>
	</tr>

	<tr>
		<th><label for="street-address"><?php esc_html_e( 'Address', 'simple-location' ); ?></label></th>
		<td><input type="text" name="street-address" id="street-address" value="" size="50" /></td>
	</tr>

	<tr>
		<th><label for="locality"><?php esc_html_e( 'City/Town/Village', 'simple-location' ); ?></label></th>
		<td><input type="text" name="locality" id="locality" value="<?php echo esc_attr( ifset( $address['locality'], '' ) ); ?>" size="30" /></td>
	</tr>    

	<tr>
		<th><label for="region"><?php esc_html_e( 'State/County/Province', 'simple-location' ); ?></label></th>
		<td><input type="text" name="region" id="region" value="" size="30" /> </td>
	</tr>

	<tr>
		<th><label for="country-code"><?php esc_html_e( 'Country Code', 'simple-location' ); ?></label></th>
		<td><input type="text" name="country-code" id="country-code" value="" size="2" /></td>
	</tr>                                             

	<tr>
		<th><label for="extended-address"><?php esc_html_e( 'Neighborhood/Suburb', 'simple-location' ); ?></label></th>
		<td><input type="text" name="extended-address" id="extended-address" value="" size="30" /></td>
	</tr>                                                                                              
	
	<tr>
		<th><label for="postal-code"><?php esc_html_e( 'Postal Code', 'simple-location' ); ?></label></th>                                   
		<td><input type="text" name="postal-code" id="postal-code" value="" size="10" /></td>
	</tr>                                              

	<tr>
		<th><label for="country-name"><?php esc_html_e( 'Country Name', 'simple-location' ); ?></label></th>
		<td><input type="text" name="country-name" id="country-name" value="" size="30" /></td>
	</tr>
</tr>
<?php
