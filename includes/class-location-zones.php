<?php
add_action( 'init', array( 'Location_Zones', 'init' ) );
add_action( 'admin_init', array( 'Location_Zones', 'admin_init' ) );

class Location_Zones {

	public static function init() {
		$cls = get_called_class();
		add_action( 'admin_enqueue_scripts', array( $cls, 'enqueue' ) );

		register_setting(
			'simloc', // settings page
			'sloc_zones', // option name
			array(
				'type'         => 'string',
				'description'  => 'Zones',
				'show_in_rest' => false,
				'default'      => array(),
			)
		);
	}

	public static function admin_init() {
		$cls = get_called_class();
		add_settings_section(
			'sloc_zone_section',
			__( 'Geofencing Zones Settings', 'simple-location' ),
			array( $cls, 'sloc_zones' ),
			'simloc'
		);
		add_settings_field(
			'sloc_zones', // id
			__( 'Zones', 'simple-location' ), // setting title
			array( $cls, 'zone_callback' ), // display callback
			'simloc', // settings page
			'sloc_zone_section', // settings section
			array()
		);
	}


	public static function save_zone( $meta_type, $object_id ) {
		if ( isset( $_POST['latitude'] ) || isset( $_POST['longitude'] ) || isset( $_POST['address'] ) ) {
			$zone = self::in_zone( $_POST['latitude'], $_POST['longitude'] );
			if ( empty( $zone ) ) {
				WP_Geo_Data::set_visibility( $meta_type, $object_id, $_POST['geo_public'] );
			} else {
				$_POST['address'] = $zone;
				WP_Geo_Data::set_visibility( $meta_type, $object_id, 'protected' );
				update_metadata( $meta_type, $object_id, 'geo_zone', $zone );
			}
		} else {
			delete_metadata( $meta_type, $object_id, 'geo_public' );
			delete_metadata( $meta_type, $object_id, 'geo_zone' );
		}
	}

	public static function sloc_zones() {
		esc_html_e(
			'Enter Name, Latitude, Longitude, and Radius for each Zone. When a location is within the radius of this zone, the description will be set to the name of the zone,
			and the visibility would be set to protected',
			'simple-location'
		);
	}

	public static function zone_callback( $args ) {
		$name   = 'sloc_zones';
		$custom = get_option( $name );
		foreach ( $custom as $key => $value ) {
			$custom[ $key ] = array_filter( $value );
		}
		$custom = array_filter( $custom );
		esc_html_e( 'Enter Name, Latitude, Longitude, and Radius for this Zone', 'simple-location' );
		printf( '<ul id="location-zones">' );
		if ( empty( $custom ) ) {
			self::zone_inputs( '0' );

		} else {
			foreach ( $custom as $key => $value ) {
				self::zone_inputs( $key, $value );
			}
		}
		printf( '</ul>' );
		printf( '<span class="button button-primary" id="add-location-zone-button">%s</span>', esc_html__( 'Add', 'simple-location' ) );
		printf( '<span class="button button-secondary" id="delete-location-zone-button">%s</span>', esc_html__( 'Remove', 'simple-location' ) );
	}

	public static function ifset( $array, $key ) {
		if ( array_key_exists( $key, $array ) ) {
			return $array[ $key ];
		}
		return '';
	}

	private static function zone_inputs( $int, $value = array() ) {
		$output = '<input type="text" name="%1$s[%2$s][%3$s]" id="%4$s" value="%5$s" placeholder="%6$s" />';
		$name   = 'sloc_zones';
		echo '<li>';
		// phpcs:disable
		printf( $output, $name, $int, 'name', esc_attr( $name ), esc_attr( self::ifset( $value, 'name' ) ), esc_html__( 'Name', 'simple-location' ) );
		printf( $output, $name, $int, 'latitude', esc_attr( $name ), esc_attr( self::ifset( $value, 'latitude' ) ), esc_html__( 'Latitude', 'simple-location' ) );
		printf( $output, $name, $int, 'longitude', esc_attr( $name ), esc_attr( self::ifset( $value, 'longitude' ) ), esc_html__( 'Longitude', 'simple-location' ) );
		printf( $output, $name, $int, 'radius', esc_attr( $name ), esc_attr( self::ifset( $value, 'radius' ) ), esc_html__( 'Radius(in meters)', 'simple-location' ) );
		// phpcs:enable
		echo '</li>';
	}

	public static function enqueue( $hook_suffix ) {
		wp_enqueue_script(
			'sloc_zones',
			plugins_url( 'js/zones.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			Simple_Location_Plugin::$version,
			true
		);
	}

	public static function in_zone( $lat, $lng ) {
		$zones = get_option( 'sloc_zones', array() );
		foreach ( $zones as $zone ) {
			if ( WP_Geo_Data::in_radius( $zone['latitude'], $zone['longitude'], $lat, $lng, $zone['radius'] ) ) {
				return $zone['name'];
			}
		}
		return '';
	}

}

