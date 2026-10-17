<?php
defined( 'ABSPATH' ) || exit;


/**
 * Adds widget to display Last Seen
 */
class Sloc_Lastseen_Widget extends Sloc_Weather_Widget {

	/**
	 * Widget constructor
	 */
	public function __construct() {
		WP_Widget::__construct(
			'Sloc_Lastseen_Widget',
			__( 'User Last Seen', 'simple-location' ),
			array(
				'description'           => __( 'Displays the location, time, or map of a users location.', 'simple-location' ),
				'show_instance_in_rest' => true,
			)
		);
	}

	/**
	 * Widget worker
	 *
	 * @since 6.0.0 The sun and moon list, location and map are passed through wp_kses().
	 * @since 6.0.0 Respects Show Local Time and Show Astronomical Info; options missing from older widgets use their defaults.
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

		// Widgets saved before an option existed get its default. Astronomical info was always shown before it had a setting.
		$instance = wp_parse_args(
			$instance,
			array(
				'showtime'  => 0,
				'showastro' => 1,
				'showtext'  => 0,
				'showmap'   => 0,
			)
		);

		if ( isset( $instance['user'] ) && 0 !== $instance['user'] ) {
			echo '<ul class="sloc-lastseen-data">';
			$user    = new WP_User( $instance['user'] );
			$geodata = get_user_geodata( $user->ID );
			if ( 1 === (int) $instance['showtime'] ) {
				$format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				$timezone = Loc_Timezone::get_timezone( $user );
				echo wp_kses(
					sprintf(
						'<li>%1$s<time datetime="%2$s">%3$s</time></li>',
						Weather_Provider::get_icon( 'wi-time-1', __( 'Local Time', 'simple-location' ) ),
						esc_attr( wp_date( DATE_W3C, null, $timezone ) ),
						esc_html( wp_date( $format, null, $timezone ) )
					),
					Simple_Location_Plugin::kses_clean()
				);
			}
			if ( 1 === (int) $instance['showastro'] ) {
				echo wp_kses( implode( self::astro_list( $geodata ) ), Simple_Location_Plugin::kses_clean() );
			}
			if ( 1 === (int) $instance['showtext'] ) {
				$location = get_user_location(
					$user->ID,
					array(
						'weather' => false,
						'markup'  => false,
					)
				);
				if ( ! empty( $location ) ) {
					echo wp_kses( '<li>' . $location . '</li>', Simple_Location_Plugin::kses_clean() );
				} else {
					printf( '<li>%1$s</li>', esc_html__( 'No current location information available', 'simple-location' ) );
				}
			}
			if ( 1 === (int) $instance['showmap'] ) {
				echo wp_kses(
					get_user_map(
						$user->ID,
						array(
							'height' => 150,
							'width'  => 150,
						)
					),
					Simple_Location_Plugin::kses_clean()
				);
			}
			echo '</ul>';
		} else {
			esc_html_e( 'No User Set', 'simple-location' );
		}

		echo wp_kses( $args['after_widget'], Simple_Location_Plugin::kses_clean() );
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
				<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				value="<?php echo esc_attr( $instance['title'] ?? '' ); ?>" /></p><p>

		<p>
		<?php esc_html_e( 'Displays last reported user location. Reporting can be set manually in the user profile, updated based on the last post with a location, or updated using a webhook', 'simple-location' ); ?>
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'user' ) ); ?>"><?php esc_html_e( 'User: ', 'simple-location' ); ?></label>
		<?php
		wp_dropdown_users(
			array(
				'id'       => $this->get_field_id( 'user' ),
				'name'     => $this->get_field_name( 'user' ),
				'selected' => $instance['user'] ?? 0,
			)
		);
		?>
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'showtime' ) ); ?>"><?php esc_html_e( 'Show Local Time: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showtime' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showtime' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'showtime' ) ); ?>" type="checkbox" value="1" <?php checked( 1, $instance['showtime'] ?? 0 ); ?> />
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'showastro' ) ); ?>"><?php esc_html_e( 'Show Astronomical Info: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'showastro' ) ); ?>" type="checkbox" value="1" <?php checked( 1, $instance['showastro'] ?? 0 ); ?> />
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'showtext' ) ); ?>"><?php esc_html_e( 'Show Text: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showtext' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showtext' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'showtext' ) ); ?>" type="checkbox" value="1" <?php checked( 1, $instance['showtext'] ?? 0 ); ?> />
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'showmap' ) ); ?>"><?php esc_html_e( 'Show Map: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showmap' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showmap' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'showmap' ) ); ?>" type="checkbox" value="1" <?php checked( 1, $instance['showmap'] ?? 0 ); ?> />
		</p>
		<?php
	}
}
