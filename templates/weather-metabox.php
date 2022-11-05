<?php
/**
 * Weather Sidebar Metabox.
 */

$screen = get_current_screen();
if ( 'comment' === $screen->id ) {
	$weather = get_comment_weatherdata( $comment->comment_ID );
} else {
	$weather = get_post_weatherdata();
}

$units    = get_option( 'sloc_measurements' );
$imperial = ( 'imperial' === $units );
if ( array_key_exists( 'code', $weather ) ) {
	$summary = Sloc_Weather_Data::weather_condition_codes( ifset( $weather['code'] ) );
}

if ( empty( $summary ) ) {
	$summary = __( 'None', 'simple-location' );
}

if ( $imperial ) {
	$weather = Weather_Provider::metric_to_imperial( $weather );
}

?>

<div class="location-section location-section-weather">
	<span class="dashicons-before dashicons-palmtree" id="weather-title" title="<?php esc_html_e( 'Weather', 'simple-location' ); ?>"> <?php esc_html_e( 'Weather:', 'simple-location' ); ?></span>
	<span id="weather-label"><?php echo esc_html( $summary ); ?></span>
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
			<label for="weather_code">
				<?php esc_html_e( 'Condition', 'simple-location' ); ?>
			</label>
			<select name="weather_code" id="weather_code">
				<?php Sloc_Weather_Data::code_select( ifset( $weather['code'] ), true ); // phpcs:ignore ?>
			</select>
		</p>

		<p class="field-row">
			<label for="winddegree">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'winddegree', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" max="360" name="winddegree" id="winddegree" value="<?php echo esc_attr( ifset( $weather['winddegree'], '' ) ); ?>" />
		</p>

		<p class="field-row">
			<label for="windspeed">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'windspeed', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" name="windspeed" id="windspeed" step="0.01" value="<?php echo esc_attr( ifset_round( $weather['windspeed'], 2, '' ) ); ?>" />
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
<?php
