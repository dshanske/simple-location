<?php

/**
 * adds widget to display weather station data with per-provider support
 */
class Sloc_Airport_Widget extends Sloc_Weather_Widget {

	/**
	 * widget constructor
	 */
	public function __construct() {
		WP_Widget::__construct(
			'Sloc_Airport_Widget',
			__( 'Airport Weather Station', 'simple-location' ),
			array(
				'description' => __( 'Adds current weather conditions at an airport', 'simple-location' ),
				'show_instance_in_rest' => true
			)
		);
	}

	public static function provider_list( $option, $name, $id ) {
		$providers = Loc_Config::weather_providers( true );
		if ( count( $providers ) > 1 ) {
				printf( '<select name="%1$s">', esc_attr( $name ) );
			foreach ( $providers as $key => $value ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', $key, selected( $option, $key ), $value ); // phpcs:ignore
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
		echo wp_kses( $args['before_widget'], Simple_Location_Plugin::kses_clean() );

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = wp_kses( apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ), Simple_Location_Plugin::kses_clean() );

		if ( $title ) {
			echo wp_kses( $args['before_title'] . $title . $args['after_title'], Simple_Location_Plugin::kses_clean() );
		}

		if ( isset( $instance['cache_time'] ) ) {
			$cache_time = $instance['cache_time'];
		} else {
			$cache_time = null;
		}

		if ( isset( $instance['airport'] ) ) {
			if ( 3 === strlen( $instance['airport'] ) ) {
				$location = Airport_Location::get( $instance['airport'] );
			} elseif ( 4 === strlen( $instance['airport'] ) ) {
				$location = Airport_Location::get( $instance['airport'], 'ident' );
			}
			if ( is_wp_error( $location ) ) {
				echo esc_html( $location->get_error_message() );
				return;
			}

			$weather = Sloc_Weather_Data::get_weather_by_location( $location['latitude'], $location['longitude'], $cache_time ); // phpcs:ignore
			if ( is_wp_error( $weather ) ) {
				echo esc_html( $weather->get_error_message() );
				return;
			}

			if ( ! isset( $weather['icon'] ) ) {
				$weather['icon'] = 'wi-thermometer';
			}
			if ( ! isset( $weather['name'] ) ) {
				$weather['name'] = $location['name'];
			}
		}
		echo wp_kses( self::weather_list( $weather, 'fa-plane', $instance ), Simple_Location_Plugin::kses_clean() );
		echo wp_kses( $args['after_widget'], Simple_Location_Plugin::kses_clean() );
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
		<?php esc_html_e( 'Displays current weather at an airport', 'simple-location' ); ?>
		</p>
		<p><p><label for="airport"><?php esc_html_e( 'Airport ID(3 or 4 letters): ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php echo esc_attr( $this->get_field_name( 'airport' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'airport' ) ); ?>" value="<?php echo esc_attr( ifset( $instance['airport'] ) ); ?>" />
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
