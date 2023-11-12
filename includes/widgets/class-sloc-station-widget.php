<?php

/**
 * adds widget to display weather station data with per-provider support
 */
class Sloc_Station_Widget extends Sloc_Weather_Widget {

	/**
	 * widget constructor
	 */
	public function __construct() {
		WP_Widget::__construct(
			'Sloc_Station_Widget',
			__( 'Weather Station', 'simple-location' ),
			array(
				'description' => __( 'Adds current weather conditions', 'simple-location' ),
				'show_instance_in_rest' => true
			)
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
		foreach ( $new_instance as $key => $value ) {
			if ( is_string( $value ) ) {
				$new_instance[ $key ] = trim( $value );
			}
		}
		return $new_instance;
	}

	public static function provider_list( $option, $name, $id ) {
		$providers = Loc_Config::weather_providers( true );
		if ( count( $providers ) > 1 ) {
				printf( '<select name="%1$s">', esc_attr( $name ) );
			foreach ( $providers as $key => $value ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', $key, selected( $option, $key ), $value['name'] ); // phpcs:ignore
			}
				echo '</select>';
				echo '<br /><br />';
		} else {
				printf( '<input name="%1$s" type="radio" id="%1$s" value="%2$s" checked /><span>%3$s</span>', esc_attr( $name ), esc_attr( $value ), esc_html( reset( $providers ) ) );
		}
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
		echo $args['before_widget']; // phpcs:ignore

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = wp_kses( apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ), Simple_Location_Plugin::kses_clean() );

		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title']; // phpcs:ignore
		}

		if ( isset( $instance['cache_time'] ) ) {
			$cache_time = $instance['cache_time'];
		} else {
			$cache_time = null;
		}

		if ( isset( $instance['station'] ) ) {
			$weather = Sloc_Weather_Data::get_weather_by_station( $instance['station'], $instance['provider'], $cache_time ); // phpcs:ignore
			if ( is_wp_error( $weather ) ) {
				if ( 'http_request_failed' === $weather->get_error_code() ) {
					echo esc_html__( 'Unable to Connect to Station', 'simple-location' );
				} else {
					echo esc_html( $weather->get_error_message() );
				}

				echo $args['after_widget']; // phpcs:ignore
				return;
			}
		}
		echo wp_kses( self::weather_list( $weather, 'fa-map', $instance ), Simple_Location_Plugin::kses_clean() );
		// echo $args['after_widget']; // phpcs:ignore
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
		<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?> id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" value="
			<?php echo esc_html( ifset( $instance['title'] ) ); ?>" /></p>
		<p>
		<?php esc_html_e( 'Displays current weather at a weather station', 'simple-location' ); ?>
		</p>
		<p><?php self::provider_list( ifset( $instance['provider'] ), $this->get_field_name( 'provider' ), $this->get_field_id( 'provider' ) ); ?>
			<p><label for="station"><?php esc_html_e( 'Station ID: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php echo esc_attr( $this->get_field_name( 'station' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'station' ) ); ?>" value="<?php echo esc_attr( ifset( $instance['station'] ) ); ?>" />
			</p>
		<p><label for="showastro"><?php esc_html_e( 'Show Astronomical Info: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" type="checkbox" value="1" <?php checked( 1, ifset( $instance['showastro'] ) ); ?> />
		</p>
		<p><label for="cache_time"><?php esc_html_e( 'Cache Time: ', 'simple-location' ); ?></label>
			<input type="number" name="<?php echo esc_attr( $this->get_field_name( 'cache_time' ) ); ?>" id="<?php $this->get_field_id( 'cache_time' ); ?>" value="<?php echo esc_attr( ifset( $instance['cache_time'] ) ); ?>" />
		</p>
		<?php
	}
}
