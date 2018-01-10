<?php

/**
 * adds widget to display rel-me links for indieauth with per-user profile support
 */
class Sloc_Weather_Widget extends WP_Widget {

	/**
	 * widget constructor
	 */
	public function __construct() {
		parent::__construct(
			'Sloc_Weather_Widget',
			__( 'Weather', 'simple-location' ),
			array(
				'description' => __( 'Adds current weather conditions', 'simple-location' ),
			)
		);
	}

	/**
	 * widget worker
	 *
	 * @param mixed $args widget parameters
	 * @param mixed $instance saved widget data
	 *
	 * @output echoes current weather
	 */
	public function widget( $args, $instance ) {
		$weather = new Weather_Provider_OpenWeatherMap();
		if ( isset( $instance['user'] ) && 0 !== $instance['user'] ) {
			$loc = WP_Geo_Data::get_geodata( new WP_User( $instance['user'] ) );
			$weather->set_location( $loc['latitude'], $loc['longitude'] );
		} elseif ( isset( $instance['latitude'] ) && isset( $instance['longitude'] ) ) {
			$weather->set_location( $instance['latitude'], $instance['longitude'] );
		}
		echo $weather->get_current_condition();

	}

	/**
	 * widget data updater
	 *
	 * @param mixed $new_instance new widget data
	 * @param mixed $old_instance current widget data
	 *
	 * @return mixed widget data
	 */
	public function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	/**
	 * widget form
	 *
	 * @param mixed $instance
	 *
	 * @output displays the widget form
	 */
	public function form( $instance ) {
?>
		<p>
		<?php esc_html_e( 'Displays current weather based on user location. If set for none will use latitude and longitude set or if not set will use station ID for provider if available.', 'simple-location' ); ?>
		</p>
		<p><label for="user"><?php _e( 'User: ', 'simple-location' ); ?></label>
		<?php
		wp_dropdown_users(
			array(
				'id'               => $this->get_field_id( 'user' ),
				'name'             => $this->get_field_name( 'user' ),
				'show_option_none' => __( 'None', 'simple-location' ),
				'selected'         => ifset( $instance['user'], 0 ),
			)
		);
				?>
		</p>
			<p><label for="latitude"><?php _e( 'Latitude: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php $this->get_field_name( 'latitude' ); ?>" id="<?php $this->get_field_id( 'latitude' ); ?>" value="<?php echo ifset( $instance['latitude'] ); ?>" />
			<label for="longitude"><?php _e( 'Longitude: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php $this->get_field_name( 'longitude' ); ?>" id="<?php $this->get_field_id( 'longitude' ); ?>" value="<?php echo ifset( $instance['longitude'] ); ?>" />
			</p>
		
	<?php
	}
}
