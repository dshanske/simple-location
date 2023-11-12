<?php

/**
 * adds widget to display Last Seen
 */
class Sloc_Lastseen_Widget extends WP_Widget {

	/**
	 * widget constructor
	 */
	public function __construct() {
		parent::__construct(
			'Sloc_Lastseen_Widget',
			__( 'User Last Seen', 'simple-location' ),
			array(
				'description' => __( 'Displays the location, time, or map of a users location.', 'simple-location' ),
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

		if ( isset( $instance['user'] ) && 0 !== $instance['user'] ) {
			echo '<ul class="sloc-lastseen-data">';
			$user    = new WP_User( $instance['user'] );
			$geodata = get_geodata( $user );
			if ( 1 === (int) $instance['showtime'] ) {
				$format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				$timezone = Loc_Timezone::get_timezone( $user );
				printf(
					'<li>%1$s<time datetime="%2$s">%3$s</time></li>',
					Weather_Provider::get_icon( 'wi-time-1', __( 'Local Time', 'simple-location' ) ),
					esc_attr( wp_date( DATE_W3C, null, $timezone ) ),
					esc_html( wp_date( $format, null, $timezone ) )
				);
			}
			if ( 1 === (int) $instance['showastro'] ) {
				$calc = new Astronomical_Calculator( $geodata['latitude'], $geodata['longitude'], ifset( $geodata['altitude'], 0 ) );

				printf(
					'<li>%1$s%2$s: <time datetime="%3$s">%4$s</time></li>',
					Weather_Provider::get_icon( 'wi-sunrise', __( 'Sunrise', 'simple-location' ) ),
					esc_html__( 'Sunrise', 'simple-location' ),
					esc_attr( $calc->get_iso8601( null, 'sunrise' ) ),
					esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'sunrise' ) )
				);
				printf(
					'<li>%1$s%2$s: <time datetime="%3$s">%4$s</time></li>',
					Weather_Provider::get_icon( 'wi-sunset', __( 'Sunset', 'simple-location' ) ),
					esc_html__( 'Sunset', 'simple-location' ),
					esc_attr( $calc->get_iso8601( null, 'sunset' ) ),
					esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'sunset' ) )
				);
				printf(
					'<li>%1$s%2$s: <time datetime="%3$s">%4$s</time></li>',
					Weather_Provider::get_icon( 'wi-moonrise', __( 'Moonrise', 'simple-location' ) ),
					esc_html__( 'Moonrise', 'simple-location' ),
					esc_attr( $calc->get_iso8601( null, 'moonrise' ) ),
					esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'moonrise' ) )
				);
				printf(
					'<li>%1$s%2$s: <time datetime="%3$s">%4$s</time></li>',
					Weather_Provider::get_icon( 'wi-moonset', __( 'Moonset', 'simple-location' ) ),
					esc_html__( 'Moonset', 'simple-location' ),
					esc_attr( $calc->get_iso8601( null, 'moonset' ) ),
					esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'moonset' ) )
				);
				$moon = $calc->get_moon_data();
				printf(
					'<li>%1$s%2$s: %3$s(%4$s)</li>',
					Weather_Provider::get_icon( $moon['icon'], __( 'Moon Phase', 'simple-location' ) ),
					esc_html__( 'Moon Phase', 'simple-location' ),
					esc_html( $moon['text'] ),
					esc_html( round( $moon['fraction'] * 100 ) . '%' )
				);
			}
			if ( 1 === (int) $instance['showtext'] ) {
				$location = Geo_Data::get_location(
					$user,
					array(
						'weather' => false,
						'markup'  => false,
					)
				);
				if ( ! empty( $location ) ) {
					printf( '<li>%1$s</li>', $location ); // phpcs:ignore
				} else {
					printf( '<li>%1$s</li>', esc_html__( 'No current location information available', 'simple-location' ) );
				}
			}
			if ( 1 === (int) $instance['showmap'] ) {
				echo get_user_map( // phpcs:ignore
					$user,
					array(
						'height' => 150,
						'width'  => 150,
					)
				); // phpcs:ignore
			}
			echo '</ul>';
		} else {
			esc_html_e( 'No User Set', 'simple-location' );
		}

		echo wp_kses( $args['after_widget'], Simple_Location_Plugin::kses_clean() );
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
				<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?> id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				value="<?php echo esc_html( ifset( $instance['title'] ) ); ?>" /></p><p>

		<p>
		<?php esc_html_e( 'Displays last reported user location. Reporting can be set manually in the user profile, updated based on the last post with a location, or updated using a webhook', 'simple-location' ); ?>
		</p>
		<p><label for="user"><?php esc_html_e( 'User: ', 'simple-location' ); ?></label>
		<?php
		wp_dropdown_users(
			array(
				'id'       => $this->get_field_id( 'user' ),
				'name'     => $this->get_field_name( 'user' ),
				'selected' => ifset( $instance['user'], 0 ),
			)
		);
		?>
		</p>
		<p><label for="showtime"><?php esc_html_e( 'Show Local Time: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showtime' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showtime' ) ); ?>" type="checkbox" value="1" <?php checked( 1, ifset( $instance['showtime'] ) ); ?> />
		</p>
		<p><label for="showastro"><?php esc_html_e( 'Show Astronomical Info: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" type="checkbox" value="1" <?php checked( 1, ifset( $instance['showastro'] ) ); ?> />
		</p>
		<p><label for="showtext"><?php esc_html_e( 'Show Text: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showtext' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showtext' ) ); ?>" type="checkbox" value="1" <?php checked( 1, ifset( $instance['showtext'] ) ); ?> />
		</p>
		<p><label for="showmap"><?php esc_html_e( 'Show Map: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showmap' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showmap' ) ); ?>" type="checkbox" value="1" <?php checked( 1, ifset( $instance['showmap'] ) ); ?> />
		</p>
		<?php
	}
}
