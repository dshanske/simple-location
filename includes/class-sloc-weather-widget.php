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
		echo $args['before_widget']; // phpcs:ignore
		if ( ! empty( $instance['title'] ) ) {
				echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title']; // phpcs:ignore
		}
		$w = Loc_Config::weather_provider();
		if ( isset( $instance['cache_time'] ) ) {
			$w->set_cache_time( $instance['cache_time'] );
		}
		if ( isset( $instance['user'] ) && '-1' !== $instance['user'] ) {
			$weather = Loc_View::get_weather_by_user( $instance['user'] ); // phpcs:ignore
		} elseif ( ! empty( $instance['latitude'] ) && ! empty( $instance['longitude'] ) ) {
			$w->set( $instance['latitude'], $instance['longitude'] );
			$weather = $w->get_conditions();
		} else {
			echo 'no';
			return;
		}
		if ( ! isset( $weather['icon'] ) ) {
			$weather['icon'] = 'wi-thermometer';
		}

		echo self::weather_list( $weather, 'fa-map', $instance );
		echo $args['after_widget']; // phpcs:ignore
	}



	/**
	 * Marks up a measurement.
	 *
	 * @param array Measurements {
	 *  @type float $value Value of measurement.
	 *  @type string $property The class property to be used.
	 *  @type string $unit The symbol or name for the unit
	 *  @type string $name The display name of the property
	 *  @type string $icon The property icon.
	 * }
	 * @return string Marked up parameter.
	 */
	protected static function markup_parameter( $params ) {
		return sprintf(
			'<li class="sloc-%1$s">%5$s%4$s: %2$s%3$s</li>',
			$params['property'],
			round( $params['value'], 2 ),
			$params['unit'],
			$params['name'],
			Weather_Provider::get_icon( $params['icon'] )
		);
	}

	protected static function weather_list( $weather, $icon = 'fa-map', $instance = null ) {
		$measurements = get_option( 'sloc_measurements' );
		$return       = array( PHP_EOL );
		$return[]     = '<h2>';
		$return[]     = Weather_Provider::get_icon( $weather['icon'], ifset( $weather['summary'] ) );
		if ( ! empty( $weather['summary'] ) ) {
			$return[] = $weather['summary'];
		}
		$return[] = '</h2>';

		$return[] = '<ul class="sloc-weather-display">';

		if ( isset( $weather['name'] ) ) {
			$return[] = sprintf( '<li>%1$s%2$s</li>', Weather_Provider::get_icon( $icon ), $weather['name'] );
		} elseif ( isset( $weather['station_id'] ) ) {
			$return[] = sprintf( '<li>%1$s%2$s</li>', Weather_Provider::get_icon( $icon ), $weather['station_id'] );
		}
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
			$return[] = self::markup_parameter(
				array(
					'value'    => $weather['temperature'],
					'property' => 'temperature',
					'unit'     => '&deg;' . $units,
					'name'     => __( 'Temperature', 'simple-location' ),
					'icon'     => 'wi-thermometer',
				)
			);
		}

		if ( isset( $weather['humidity'] ) ) {
			$return[] = self::markup_parameter(
				array(
					'value'    => $weather['humidity'],
					'property' => 'humidity',
					'unit'     => '%',
					'name'     => __( 'Humidity', 'simple-location' ),
					'icon'     => 'wi-humidity',
				)
			);
		}
		if ( isset( $weather['pressure'] ) ) {
			$return[] = self::markup_parameter(
				array(
					'value'    => $weather['pressure'],
					'property' => 'pressure',
					'unit'     => 'hPa',
					'name'     => __( 'Pressure', 'simple-location' ),
					'icon'     => 'wi-barometer',
				)
			);
		}
		if ( isset( $weather['cloudiness'] ) ) {
			$return[] = self::markup_parameter(
				array(
					'value'    => $weather['cloudiness'],
					'property' => 'cloudiness',
					'unit'     => '%',
					'name'     => __( 'Cloudiness', 'simple-location' ),
					'icon'     => 'wi-cloudy',
				)
			);
		}
		if ( isset( $weather['visibility'] ) ) {
			$return[] = self::markup_parameter(
				array(
					'value'    => $weather['visibility'],
					'property' => 'visibility',
					'unit'     => 'm',
					'name'     => __( 'Visibility', 'simple-location' ),
					'icon'     => 'wi-visibility',
				)
			);
		}
		if ( isset( $weather['wind'] ) ) {
			$return[] = self::markup_parameter(
				array(
					'value'    => $weather['wind']['speed'],
					'property' => 'wind-speed',
					'unit'     => 'm/hr',
					'name'     => __( 'Wind Speed', 'simple-location' ),
					'icon'     => 'wi-windy',
				)
			);
		}
		if ( isset( $weather['rain'] ) ) {
			$return[] = self::markup_parameter(
				array(
					'value'    => $weather['rain'],
					'property' => 'rain',
					'unit'     => 'mm/hr',
					'name'     => __( 'Rain', 'simple-location' ),
					'icon'     => 'wi-rain',
				)
			);
		}
		if ( isset( $weather['snow'] ) ) {
			$return[] = self::markup_parameter(
				array(
					'value'    => $weather['snow'],
					'property' => 'snow',
					'unit'     => 'mm/hr',
					'name'     => __( 'Snow', 'simple-location' ),
					'icon'     => 'wi-snow',
				)
			);

		}
		if ( isset( $weather['uv'] ) ) {
			$return[] = self::markup_parameter(
				array(
					'value'    => $weather['uv'],
					'property' => 'uv',
					'unit'     => '',
					'name'     => __( 'UV Index', 'simple-location' ),
					'icon'     => 'wi-uv',
				)
			);
		}
		if ( isset( $weather['_expires_at'] ) ) {
			$return[] = printf( '<!-- %1$s: %2$s -->', __( 'Current Conditions Cache Expires At', 'simple-location' ), $weather['_expires_at'] );
		}

		if ( isset( $instance['showastro'] ) && 1 === (int) $instance['showastro'] && array_key_exists( 'latitude', $weather ) && array_key_exists( 'longitude', $weather ) ) {
			$calc     = new Astronomical_Calculator( $weather['latitude'], $weather['longitude'], ifset( $weather['altitude'], 0 ) );
			$return[] = sprintf(
				'<li>%1$s%2$s: <time datetime="%3$s">%4$s</time></li>',
				Weather_Provider::get_icon( 'wi-sunrise', __( 'Sunrise', 'simple-location' ) ),
				esc_html__( 'Sunrise', 'simple-location' ),
				esc_attr( $calc->get_iso8601( null, 'sunrise' ) ),
				esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'sunrise' ) )
			);
			$return[] = sprintf(
				'<li>%1$s%2$s: <time datetime="%3$s">%4$s</time></li>',
				Weather_Provider::get_icon( 'wi-sunset', __( 'Sunset', 'simple-location' ) ),
				esc_html__( 'Sunset', 'simple-location' ),
				esc_attr( $calc->get_iso8601( null, 'sunset' ) ),
				esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'sunset' ) )
			);
			$return[] = sprintf(
				'<li>%1$s%2$s: <time datetime="%3$s">%4$s</time></li>',
				Weather_Provider::get_icon( 'wi-moonrise', __( 'Moonrise', 'simple-location' ) ),
				esc_html__( 'Moonrise', 'simple-location' ),
				esc_attr( $calc->get_iso8601( null, 'moonrise' ) ),
				esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'moonrise' ) )
			);
			$return[] = sprintf(
				'<li>%1$s%2$s: <time datetime="%3$s">%4$s</time></li>',
				Weather_Provider::get_icon( 'wi-moonset', __( 'Moonset', 'simple-location' ) ),
				esc_html__( 'Moonset', 'simple-location' ),
				esc_attr( $calc->get_iso8601( null, 'moonset' ) ),
				esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'moonset' ) )
			);
			$moon     = $calc->get_moon_data();
			$return[] = sprintf(
				'<li>%1$s%2$s: %3$s(%4$s)</li>',
				Weather_Provider::get_icon( $moon['icon'], __( 'Moon Phase', 'simple-location' ) ),
				esc_html__( 'Moon Phase', 'simple-location' ),
				esc_html( $moon['text'] ),
				esc_html( round( $moon['fraction'] * 100 ) . '%' )
			);
		}

		$return[] = '</ul>';
		return implode( PHP_EOL, array_filter( $return ) ); // phpcs:ignore
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
