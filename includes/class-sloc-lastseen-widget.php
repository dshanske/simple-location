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
				'description' => __( 'Displays Last Seen', 'simple-location' ),
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
			$location = Loc_View::get_location(
				new WP_User( $instance['user'] ),
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
				value="<?php echo esc_html( ifset( $instance['title'] ) ); ?>" /></p>
		<p>
		<?php esc_html_e( 'Displays last reported user location', 'simple-location' ); ?>
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
		<?php
	}
}
