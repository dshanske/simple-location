<?php
/**
 * Weather Sidebar Metabox.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Loaded with load_template() inside a function, so these variables are local.

$screen = get_current_screen();
if ( 'comment' === $screen->id ) {
	$weather = get_comment_weatherdata( get_comment_ID() );
} else {
	$weather = get_post_weatherdata();
}

$units    = get_option( 'sloc_measurements' );
$imperial = ( 'imperial' === $units );
if ( array_key_exists( 'code', $weather ) ) {
	$summary = Sloc_Weather_Data::weather_condition_codes( $weather['code'] ?? '' );
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
		<input type="hidden" name="weather_provider" id="weather_provider" value="<?php echo esc_attr( $weather['provider'] ?? '' ); ?>" />
		<p class="field-row">
			<label for="temperature">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'temperature', $imperial ) ); ?>
			</label>
			<input type="number" name="temperature" step="<?php echo esc_attr( Weather_Provider::field_step( 'temperature', $imperial ) ); ?>" id="temperature" value="<?php echo esc_attr( sloc_round_value( $weather['temperature'] ?? '', Weather_Provider::field_precision( 'temperature', $imperial ) ) ); ?>" class="widefat" />
			<input type="hidden" name="weather_exact[temperature]" id="temperature_exact" value="" />
		</p>

		<p class="field-row">
			<label for="humidity">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'humidity', $imperial ) ); ?>
			</label>
			<input type="number" min="0" max="100" name="humidity" id="humidity" step="<?php echo esc_attr( Weather_Provider::field_step( 'humidity', $imperial ) ); ?>" value="<?php echo esc_attr( sloc_round_value( $weather['humidity'] ?? '', Weather_Provider::field_precision( 'humidity', $imperial ) ) ); ?>" class="widefat" />
			<input type="hidden" name="weather_exact[humidity]" id="humidity_exact" value="" />
		</p>

		<p class="field-row">
			<label for="weather_code">
				<?php esc_html_e( 'Condition', 'simple-location' ); ?>
			</label>
			<select name="weather_code" id="weather_code">
				<?php Sloc_Weather_Data::code_select( $weather['code'] ?? '', true ); // phpcs:ignore ?>
			</select>
		</p>

		<p class="field-row">
			<label for="winddegree">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'winddegree', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" max="360" name="winddegree" id="winddegree" step="<?php echo esc_attr( Weather_Provider::field_step( 'winddegree', $imperial ) ); ?>" value="<?php echo esc_attr( sloc_round_value( $weather['winddegree'] ?? '', Weather_Provider::field_precision( 'winddegree', $imperial ) ) ); ?>" />
			<input type="hidden" name="weather_exact[winddegree]" id="winddegree_exact" value="" />
		</p>

		<p class="field-row">
			<label for="windspeed">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'windspeed', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" name="windspeed" id="windspeed" step="<?php echo esc_attr( Weather_Provider::field_step( 'windspeed', $imperial ) ); ?>" value="<?php echo esc_attr( sloc_round_value( $weather['windspeed'] ?? '', Weather_Provider::field_precision( 'windspeed', $imperial ) ) ); ?>" />
			<input type="hidden" name="weather_exact[windspeed]" id="windspeed_exact" value="" />
		</p>

		<p class="field-row">
			<label for="pressure">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'pressure', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" name="pressure" id="pressure" step="<?php echo esc_attr( Weather_Provider::field_step( 'pressure', $imperial ) ); ?>" value="<?php echo esc_attr( sloc_round_value( $weather['pressure'] ?? '', Weather_Provider::field_precision( 'pressure', $imperial ) ) ); ?>" />
			<input type="hidden" name="weather_exact[pressure]" id="pressure_exact" value="" />
		</p>

		<p class="field-row">
			<label for="weather_visibility">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'visibility', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" name="weather_visibility" id="weather_visibility" step="<?php echo esc_attr( Weather_Provider::field_step( 'visibility', $imperial ) ); ?>" value="<?php echo esc_attr( sloc_round_value( $weather['visibility'] ?? '', Weather_Provider::field_precision( 'visibility', $imperial ) ) ); ?>" />
			<input type="hidden" name="weather_exact[visibility]" id="weather_visibility_exact" value="" />
		</p>

		<p class="field-row">
			<label for="cloudiness">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'cloudiness', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" max="100" name="cloudiness" id="cloudiness" step="<?php echo esc_attr( Weather_Provider::field_step( 'cloudiness', $imperial ) ); ?>" value="<?php echo esc_attr( sloc_round_value( $weather['cloudiness'] ?? '', Weather_Provider::field_precision( 'cloudiness', $imperial ) ) ); ?>" />
			<input type="hidden" name="weather_exact[cloudiness]" id="cloudiness_exact" value="" />
		</p>

		<p class="field-row">
			<label for="rain">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'rain', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" name="rain" step="<?php echo esc_attr( Weather_Provider::field_step( 'rain', $imperial ) ); ?>" id="rain" value="<?php echo esc_attr( sloc_round_value( $weather['rain'] ?? '', Weather_Provider::field_precision( 'rain', $imperial ) ) ); ?>" />
			<input type="hidden" name="weather_exact[rain]" id="rain_exact" value="" />
		</p>

		<p class="field-row">
			<label for="snow">
				<?php echo wp_kses_post( Weather_Provider::get_form_label( 'snow', $imperial ) ); ?>
			</label>
			<input class="widefat" type="number" min="0" name="snow" step="<?php echo esc_attr( Weather_Provider::field_step( 'snow', $imperial ) ); ?>" id="snow" value="<?php echo esc_attr( sloc_round_value( $weather['snow'] ?? '', Weather_Provider::field_precision( 'snow', $imperial ) ) ); ?>" />
			<input type="hidden" name="weather_exact[snow]" id="snow_exact" value="" />
		</p>

		<p>
			<a href="#weather" class="hide-weather hide-if-no-js button">OK</a>
			<a href="#weather" class="hide-weather hide-if-no-js button-cancel">Cancel</a>
		</p>
	</div>
</div>
<?php
