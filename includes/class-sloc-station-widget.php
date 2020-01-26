<?php

/**
 * adds widget to display weather station data with per-provider support
 */
class Sloc_Station_Widget extends WP_Widget {

	/**
	 * widget constructor
	 */
	public function __construct() {
		parent::__construct(
			'Sloc_Station_Widget',
			__( 'Weather Station', 'simple-location' ),
			array(
				'description' => __( 'Adds current weather conditions', 'simple-location' ),
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


	private static function markup_parameter( $value, $property, $unit, $type ) {
		return sprintf(
			'<li class="sloc-%1$s">%4$s: %2$s%3$s</li>',
			$property,
			round( $value ),
			$unit,
			$type
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
		if ( isset( $instance['station'] ) ) {
			$weather = Loc_View::get_weather_by_station( $instance['station'], $instance['provider'] ); // phpcs:ignore
			if ( is_wp_error( $weather ) ) {
				echo esc_html( $weather->get_error_message() );
				echo $args['after_widget']; // phpcs:ignore
				return;
			}
			if ( ! isset( $weather['icon'] ) ) {
				$weather['icon'] = 'wi-thermometer';
			}

			$class    = 'sloc-weather-widget';
			$return   = array( PHP_EOL );
			$return[] = '<h2>';
			$return[] = Weather_Provider::get_icon( $weather['icon'], ifset( $weather['summary'] ) );
			if ( ! empty( $weather['summary'] ) ) {
				$return[] = $weather['summary'];
			}
			$return[] = '</h2>';

			if ( isset( $weather['station_id'] ) ) {
				if ( isset( $weather['name'] ) ) {
					$return[] = $weather['name'];
				}
			}
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
			echo implode( PHP_EOL, array_filter( $return ) ); // phpcs:ignore
		}
		echo $args['after_widget']; // phpcs:ignore

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
		<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?> id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" value="
			<?php echo esc_html( ifset( $instance['title'] ) ); ?>" /></p>
		<p>
		<?php esc_html_e( 'Displays current weather at a weather station', 'simple-location' ); ?>
		</p>
		<p><?php self::provider_list( ifset( $instance['provider'] ), $this->get_field_name( 'provider' ), $this->get_field_id( 'provider' ) ); ?>
			<p><label for="station"><?php esc_html_e( 'Station ID: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php echo esc_attr( $this->get_field_name( 'station' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'station' ) ); ?>" value="<?php echo esc_attr( ifset( $instance['station'] ) ); ?>" />
			</p>
		<?php
	}
}
