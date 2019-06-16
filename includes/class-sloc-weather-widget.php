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
		$measurements = get_option( 'sloc_measurements' );
		echo $args['before_widget']; // phpcs:ignore
		if ( ! empty( $instance['title'] ) ) {
				echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title']; // phpcs:ignore
		}
		$w = Loc_Config::weather_provider();
		if ( isset( $instance['user'] ) && '-1' !== $instance['user'] ) {
			echo Loc_View::get_weather_by_user( $instance['user'] ); // phpcs:ignore
			return;
		} elseif ( ! empty( $instance['latitude'] ) && ! empty( $instance['longitude'] ) ) {
			$w->set( $instance['latitude'], $instance['longitude'] );
		} else {
			return;
		}
		$weather = $w->get_conditions();
		if ( ! isset( $weather['icon'] ) ) {
			$weather['icon'] = 'wi-thermometer';
		}

		$class    = 'sloc-weather-widget';
		$return   = array( PHP_EOL );
		$return[] = '<h2>';
		$return[] = Weather_Provider::get_icon( $weather['icon'], ifset( $weather['summary'] ) );
		if ( ! empty( $weather['summary'] ) ) {
			$return[] = sprintf( '<span class="p-weather">%1$s</span>', $weather['summary'] );
		}
		$return[] = '</h2>';
		$return[] = '<ul>';
		if ( isset( $weather['temperature'] ) ) {
			$units = ifset( $weather['units'] );
			if ( ! $units ) {
				switch ( $measurements ) {
					case 'imperial':
						$units                  = __( 'F', 'simple-location' );
						$weather['temperature'] = round( Weather_Provider::celsius_to_fahrenheit( $weather['temperature'] ) );
						break;
					default:
						$units = __( 'C', 'simple-location' );
				}
			}
			$return[] = sprintf( '<li>%1$s&deg;%2$s</li>', $weather['temperature'], $units );
		}

		if ( isset( $weather['humidity'] ) ) {
			$return[] = self::markup_parameter( $weather['humidity'], 'humidity', '%', __( 'Humidity', 'simple-location' ) );
		}
		if ( isset( $weather['cloudiness'] ) ) {
			$return[] = self::markup_parameter( $weather['cloudiness'], 'cloudiness', '%', __( 'Cloudiness', 'simple-location' ) );
		}
		if ( isset( $weather['visibility'] ) ) {
			$return[] = self::markup_parameter( $weather['visibility'], 'visibility', 'm', __( 'Visibility', 'simple-location' ) );
		}
		$return[] = '</ul>';
		if ( isset( $weather['station_id'] ) ) {
			if ( isset( $weather['name'] ) ) {
				$return[] = sprintf( '<p>%1$s</p>', $weather['name'] );
			}
		}
		echo implode( PHP_EOL, array_filter( $return ) ); // phpcs:ignore
		echo $args['after_widget']; // phpcs:ignore

	}

	private static function markup_parameter( $value, $property, $unit, $type ) {
		return sprintf(
			'<li class="sloc-%1$s">%4$s: %2$s%3$s</li>',
			$property,
			$value,
			$unit,
			$type
		);
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
		<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?> id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" value="<?php echo esc_html( ifset( $instance['title'] ) ); ?>" />
		<p>
		<?php esc_html_e( 'Displays current weather based on user location set in user profile. If set for none will use latitude and longitude set', 'simple-location' ); ?>
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
			<input type="text" size="7" name="<?php echo esc_attr( $this->get_field_name( 'latitude' ) ); ?>" id="<?php $this->get_field_id( 'latitude' ); ?>" value="<?php echo esc_attr( ifset( $instance['latitude'] ) ); ?>" />
			<label for="longitude"><?php esc_html_e( 'Longitude: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php echo esc_attr( $this->get_field_name( 'longitude' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'longitude' ) ); ?>" value="<?php echo esc_attr( ifset( $instance['longitude'] ) ); ?>" />
			</p>
		<?php
	}
}
