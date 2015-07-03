<?php 

// On Activation, add terms
register_activation_hook( __FILE__, 'sloc_defaults' );

function sloc_defaults() {
  if (!get_option('sloc_options') ) {
    $option = array (
        'height' => '350',
        'width' => '350',
        'zoom' => '14'
    );
    update_option('sloc_options', $option);
  }
}

add_filter('admin_init', array('loc_config', 'admin_init'), 10, 4);

class loc_config {

	/**
	 * Add Settings to the Discussions Page
	 *
	 */
	public static function admin_init() {
		register_setting(
			'writing', // settings page
			'sloc_options' // option name
		);
		add_settings_field(
			'height', // id
			'Map Height', // setting title
			array('loc_config', 'number_callback'), // display callback
			'writing', // settings page
 			'default', // settings section
			array( 'name' => 'height')
		);
		add_settings_field(
			'width', // id
			'Map Width', // setting title
			array( 'loc_config', 'number_callback'), // display callback
			'writing', // settings page
			'default', // settings section
			array( 'name' => 'width')
		);
		add_settings_field(
			'zoom', // id
			'Map Zoom', // setting title
			array('loc_config', 'number_callback'), // display callback
			'writing', // settings page
			'default', // settings section
			array( 'name' => 'zoom')
		);
	}

	public static function checkbox_callback(array $args) {
		$options = get_option('sloc_options');
		$name = $args['name'];
		$checked = $options[$name];
		echo "<input name='sloc_options[$name]' type='hidden' value='0' />";
		echo "<input name='sloc_options[$name]' type='checkbox' value='1' " . checked( 1, $checked, false ) . " /> ";
	}

	public static function number_callback(array $args) {
		$options = get_option('sloc_options');
		$name = $args['name'];
		$text = $options[$name];
		echo "<input name='sloc_options[$name]' type='number' value='" . $text . "' /> ";
	}
}
