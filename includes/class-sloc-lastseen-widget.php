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
		if ( isset( $instance['user'] ) && 0 !== $instance['user'] ) {
			echo '<div>';
			_e( 'Last Seen: ', 'simple-location' );
			echo Loc_View::get_location(
				new WP_User( $instance['user'] ),
				array(
					'weather' => false,
				)
			);
			echo '</div>';
		}
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
		<p>
		<?php esc_html_e( 'Displays last reported user location', 'simple-location' ); ?>
		</p>
		<p><label for="user"><?php _e( 'User: ', 'simple-location' ); ?></label>
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
	<?php
	}
}
