<?php
defined( 'ABSPATH' ) || exit;


/**
 * Adds widget to display weather with per-user profile support
 */
class Sloc_Weather_Widget extends WP_Widget {

	/**
	 * Widget constructor
	 */
	public function __construct() {
		parent::__construct(
			'Sloc_Weather_Widget',
			__( 'Weather', 'simple-location' ),
			array(
				'description'           => __( 'Adds current weather conditions', 'simple-location' ),
				'show_instance_in_rest' => true,
			)
		);
	}

	/**
	 * Widget worker
	 *
	 * @since 6.0.0 Closes the widget wrapper when there is no provider or input.
	 *
	 * @param mixed $args widget parameters
	 * @param mixed $instance saved widget data
	 *
	 * @output echoes current weather
	 */
	public function widget( $args, $instance ) {
		$hide_location = false;
		if ( self::uses_user( $instance ) && 'private' === get_user_geodata( (int) $instance['user'], 'visibility' ) ) {
			if ( empty( $instance['showprivate'] ) ) {
				return;
			}
			$hide_location = true;
		}

		echo $args['before_widget']; // phpcs:ignore

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = wp_kses( apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ), Simple_Location_Plugin::kses_clean() );

		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title']; // phpcs:ignore
		}
		$w = Loc_Config::weather_provider();
		if ( ! $w ) {
			esc_html_e( 'No Weather Provider Set!', 'simple-location' );
			echo $args['after_widget']; // phpcs:ignore
			return;
		}
		if ( isset( $instance['cache_time'] ) ) {
			$w->set_cache_time( $instance['cache_time'] );
		}
		if ( self::uses_user( $instance ) ) {
			$weather = Sloc_Weather_Data::get_weather_by_user( $instance['user'] ); // phpcs:ignore
		} elseif ( ! empty( $instance['latitude'] ) && ! empty( $instance['longitude'] ) ) {
			$w->set( $instance['latitude'], $instance['longitude'] );
			$weather = $w->get_conditions();
		} else {
			esc_html_e( 'No Input Provided', 'simple-location' );
			echo $args['after_widget']; // phpcs:ignore
			return;
		}

		if ( $hide_location && is_array( $weather ) ) {
			$weather = self::without_location( $weather );
		}

		if ( is_wp_error( $weather ) ) {
			echo esc_html( $weather->get_error_message() );
		} elseif ( is_array( $weather ) ) {
			echo wp_kses( self::weather_list( $weather, 'fa-map', $instance ), Simple_Location_Plugin::kses_clean() );
		} elseif ( is_string( $weather ) ) {
			echo esc_html( $weather );
		}
		echo $args['after_widget']; // phpcs:ignore
	}

	/**
	 * Whether the widget shows a user's weather rather than a fixed location.
	 *
	 * @since 6.0.0
	 *
	 * @param array $instance Saved widget settings.
	 * @return bool True if a user is selected.
	 */
	protected static function uses_user( $instance ) {
		return isset( $instance['user'] ) && '-1' !== (string) $instance['user'];
	}

	/**
	 * Removes the parts of the weather that reveal where it was measured.
	 *
	 * Drops the station name and ID, and the coordinates that the astronomical information is calculated from.
	 *
	 * @since 6.0.0
	 *
	 * @param array $weather Weather conditions.
	 * @return array Weather conditions without location details.
	 */
	protected static function without_location( $weather ) {
		return array_diff_key( $weather, array_flip( array( 'name', 'station_id', 'latitude', 'longitude', 'altitude' ) ) );
	}

	protected static function astro_list( $weather ) {
		if ( ! is_array( $weather ) ) {
			return array();
		}
		$calc     = new Astronomical_Calculator( $weather['latitude'], $weather['longitude'], $weather['altitude'] ?? 0 );
		$return   = array();
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
		return $return;
	}

	protected static function weather_list( $weather, $icon = 'fa-map', $instance = null ) {
		if ( ! is_array( $weather ) ) {
			return '';
		}

		$units    = get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
		$return   = array( PHP_EOL );
		$return[] = '<h2>';

		if ( ! empty( $weather['icon'] ) ) {
			$return[] = Weather_Provider::get_icon( $weather['icon'], $weather['summary'] ?? null );
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
			$return[] = sprintf( '<!-- %1$s: %2$s -->', esc_html__( 'Current Conditions Cache Expires At', 'simple-location' ), esc_html( $weather['_expires_at'] ) );
		}

		if ( isset( $instance['showastro'] ) && 1 === (int) $instance['showastro'] && array_key_exists( 'latitude', $weather ) && array_key_exists( 'longitude', $weather ) ) {
			$astro  = self::astro_list( $weather );
			$return = array_merge( $return, $astro );
		}

		$return[] = '</ul>';
		return implode( PHP_EOL, array_filter( $return ) ); // phpcs:ignore
	}

	/**
	 * Widget data updater
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
	 * Widget form
	 *
	 * @param mixed $instance
	 *
	 * @output displays the widget form
	 */
	public function form( $instance ) {
		?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title: ', 'simple-location' ); ?></label>
		<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ?? '' ); ?>" />
		<p>
		<?php esc_html_e( 'Displays current weather based on user location set in user profile. If set for none will use latitude and longitude set', 'simple-location' ); ?>
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'user' ) ); ?>"><?php esc_html_e( 'User: ', 'simple-location' ); ?></label>
		<?php
		wp_dropdown_users(
			array(
				'id'               => $this->get_field_id( 'user' ),
				'name'             => $this->get_field_name( 'user' ),
				'show_option_none' => __( 'None', 'simple-location' ),
				'selected'         => $instance['user'] ?? 0,
			)
		);
		?>
		</p>
			<p><label for="<?php echo esc_attr( $this->get_field_id( 'latitude' ) ); ?>"><?php esc_html_e( 'Latitude: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php echo esc_attr( $this->get_field_name( 'latitude' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'latitude' ) ); ?>" value="<?php echo esc_attr( $instance['latitude'] ?? '' ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'longitude' ) ); ?>"><?php esc_html_e( 'Longitude: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php echo esc_attr( $this->get_field_name( 'longitude' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'longitude' ) ); ?>" value="<?php echo esc_attr( $instance['longitude'] ?? '' ); ?>" />
			</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'showastro' ) ); ?>"><?php esc_html_e( 'Show Astronomical Info: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'showastro' ) ); ?>" type="checkbox" value="1" <?php checked( 1, $instance['showastro'] ?? 0 ); ?> />
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'showprivate' ) ); ?>"><?php esc_html_e( 'Show weather when the user\'s location is private: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showprivate' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showprivate' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'showprivate' ) ); ?>" type="checkbox" value="1" <?php checked( 1, $instance['showprivate'] ?? 0 ); ?> />
			<br /><small><?php esc_html_e( 'The station name and astronomical information are left out, so only the conditions are shown.', 'simple-location' ); ?></small>
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'cache_time' ) ); ?>"><?php esc_html_e( 'Cache Time: ', 'simple-location' ); ?></label>
			<input type="number" name="<?php echo esc_attr( $this->get_field_name( 'cache_time' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'cache_time' ) ); ?>" value="<?php echo esc_attr( $instance['cache_time'] ?? '' ); ?>" />
		</p>
		<?php
	}
}
