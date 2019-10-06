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
		if ( isset( $instance['user'] ) && 0 !== $instance['user'] ) {
			echo '<div>';
			$user    = new WP_User( $instance['user'] );
			$geodata = WP_Geo_Data::get_geodata( $user );
			if ( 1 === (int) $instance['showtime'] ) {
				$format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				$timezone = Post_Timezone::get_timezone( $user );
				echo Weather_Provider::get_icon( 'wi-time-1', __( 'Local Time', 'simple-location' ) ); // phpcs:ignore
				printf( '<time datetime="%1$s">%2$s</time>', esc_attr( wp_date( DATE_W3C, null, $timezone ) ), esc_html( wp_date( $format, null, $timezone ) ) );
			}
			if ( 1 === (int) $instance['showastro'] ) {
				$calc = new Astronomical_Calculator( $geodata['latitude'], $geodata['longitude'], ifset( $geodata['altitude'], 0 ) );

				printf( '<p>%1$s: <time datetime="%2$s">%3$s</time></p>', esc_html__( 'Sunrise', 'simple-location' ), esc_attr( $calc->get_iso8601( null, 'sunrise' ) ), esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'sunrise' ) ) );
				printf( '<p>%1$s: <time datetime="%2$s">%3$s</time></p>', esc_html__( 'Sunset', 'simple-location' ), esc_attr( $calc->get_iso8601( null, 'sunset' ) ), esc_html( $calc->get_formatted( null, get_option( 'time_format' ), 'sunset' ) ) );
			}
			if ( 1 === (int) $instance['showtext'] ) {
				$location = Loc_View::get_location(
					$user,
					array(
						'weather' => false,
						'markup'  => false,
					)
				);
				if ( ! empty( $location ) ) {
					echo $location; // phpcs:ignore
				} else {
					esc_html_e( 'No current location information available', 'simple-location' );
				}
			}
			if ( 1 === (int) $instance['showmap'] ) {
				echo Loc_View::get_map( // phpcs:ignore
					$user,
					array(
						'height' => 150,
						'width'  => 150,
					)
				); // phpcs:ignore
			}
			echo '</div>';
		} else {
			esc_html_e( 'No User Set', 'simple-location' );
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
		<p><label for="showastro"><?php esc_html_e( 'Show Astrological Info: ', 'simple-location' ); ?></label>
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
