<?php

/**
 * adds widget to display weather with per-user profile support
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
		echo $args['before_widget'];
		if( isset( $instance['title'] ) ) {
			printf( '<h2>%1$s</h2>', esc_html( $instance['title'] ) );
		}
		$weather = Loc_Config::weather_provider();
		if ( isset( $instance['user'] ) && 0 !== $instance['user'] ) {
			echo Loc_View::get_weather_by_user( $instance['user'] );
			return;
		} elseif ( isset( $instance['latitude'] ) && isset( $instance['longitude'] ) ) {
			$weather->set( $instance['latitude'], $instance['longitude'] );
		} elseif ( isset( $instance['station'] ) ) {
		}
		echo Loc_View::get_the_weather( $weather->get_conditions() ); // phpcs:ignore
		echo $args['after_widget'];

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
		<p><label for="title"><?php esc_html_e( 'Title: ', 'simple-location' ); ?></label>
		<input type="text" size="30" name="<?php $this->get_field_name( 'title' ); ?> id="<?php $this->get_field_id( 'title' ); ?>" value="<?php echo esc_html( ifset( $instance['title'] ) ); ?>" />
		<p>
		<?php esc_html_e( 'Displays current weather based on user location. If set for none will use latitude and longitude set or if not set will use station ID for provider if available.', 'simple-location' ); ?>
		</p>
		<p><label for="user"><?php esc_html_e( 'User: ', 'simple-location' ); ?></label>
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
			<p><label for="latitude"><?php esc_html_e( 'Latitude: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php $this->get_field_name( 'latitude' ); ?>" id="<?php $this->get_field_id( 'latitude' ); ?>" value="<?php echo esc_attr( ifset( $instance['latitude'] ) ); ?>" />
			<label for="longitude"><?php esc_html_e( 'Longitude: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php $this->get_field_name( 'longitude' ); ?>" id="<?php $this->get_field_id( 'longitude' ); ?>" value="<?php echo esc_attr( ifset( $instance['longitude'] ) ); ?>" />
			</p>
		<?php
	}
}
