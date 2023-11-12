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
				'show_instance_in_rest' => true
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

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = wp_kses( apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ), Simple_Location_Plugin::kses_clean() );

		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title']; // phpcs:ignore
		}
		$w = Loc_Config::weather_provider();
		if ( isset( $instance['cache_time'] ) ) {
			$w->set_cache_time( $instance['cache_time'] );
		}
		if ( isset( $instance['user'] ) && '-1' !== $instance['user'] ) {
			$weather = Sloc_Weather_Data::get_weather_by_user( $instance['user'] ); // phpcs:ignore
		} elseif ( ! empty( $instance['latitude'] ) && ! empty( $instance['longitude'] ) ) {
			$w->set( $instance['latitude'], $instance['longitude'] );
			$weather = $w->get_conditions();
		} else {
			echo 'no';
			return;
		}

		if ( is_wp_error( $weather ) ) {
			echo $weather->get_error_message();
		} elseif ( is_array( $weather ) ) {
			echo wp_kses( self::weather_list( $weather, 'fa-map', $instance ), Simple_Location_Plugin::kses_clean() );
		} elseif ( is_string( $weather ) ) {
			echo esc_html( $weather );
		}
		echo $args['after_widget']; // phpcs:ignore
	}

	protected static function weather_list( $weather, $icon = 'fa-map', $instance = null ) {
		if ( ! is_array( $weather ) ) {
			return '';
		}

		$measurements = get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
		$return       = array( PHP_EOL );
		$return[]     = '<h2>';

		if ( ! empty( $weather['icon'] ) ) {
			$return[] = Weather_Provider::get_icon( $weather['icon'], ifset( $weather['summary'] ) );
		}

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
		if ( array_key_exists( 'units', $weather ) ) {
			$units = $weather['units'];
		} else {
			$units = get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
		}
		if ( 'imperial' === $units ) {
			$weather = Weather_Provider::metric_to_imperial( $weather );
		}

		// Unpack wind.
		if ( array_key_exists( 'wind', $weather ) ) {
			foreach ( $weather['wind'] as $key => $value ) {
				$weather[ 'wind-' . $key ] = $value;
			}
			unset( $weather['wind'] );
		}

		$args = array(
			'units'  => $units,
			'markup' => false,
		);
		foreach ( $weather as $key => $value ) {
			$return[] = Weather_Provider::markup_value( $key, $value, $args );
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
