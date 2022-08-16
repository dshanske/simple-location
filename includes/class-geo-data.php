<?php
/**
 * Geographical Metadata
 *
 * Registers geographic metadata and supplies functions to assist in manipulating it.
 *
 * @package Simple Location
 */

add_action( 'init', array( 'Geo_Data', 'init' ), 1 );

/**
 * Handles Geo Functionality for WordPress objects.
 *
 * @since 1.0.0
 */
class Geo_Data {


	public static $properties = array( 'latitude', 'longitude', 'address', 'trip', 'map_zoom', 'altitude', 'speed', 'heading', 'visibility', 'timezone', 'icon' );

	/**
	 * Geo Data Initialization Function.
	 *
	 * Meant to be attached to init hook. Sets up all the geodata enhancements.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		self::register_meta();

		add_action( 'pre_get_posts', array( __CLASS__, 'remove_maps_pagination' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_location_posts' ) );
		add_action( 'pre_get_comments', array( __CLASS__, 'pre_get_comments' ) );

		add_filter( 'get_comment_text', array( __CLASS__, 'location_comment' ), 12, 2 );
		add_filter( 'the_content', array( __CLASS__, 'content_map' ), 11 );
		if ( ! current_theme_supports( 'simple-location' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'location_content' ), 12 );
		}
	}


	/**
	 * Removes the Pagination from the Map Archive Page.
	 *
	 * Filter query variables.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 4.0.0
	 */
	public static function remove_maps_pagination( $query ) {
		if ( ! array_key_exists( 'map', $query->query_vars ) || ! $query->is_main_query() ) {
			return;
		}
		$query->set( 'meta_query', array( self::filter_geo_query( 'map' ) ) );
		$query->set( 'posts_per_page', SLOC_PER_PAGE );
		$query->set( 'order', 'ASC' );
	}

	/**
	 * Sets visibility property on any metadata capable object.
	 *
	 * Allows visibility to be set on posts, comments, terms, etc.
	 *
	 * @param string $type Object Type.
	 * @param int    $id Post ID, Comment ID, User ID, etc.
	 * @param string $status Visibility to be set.
	 * @since 1.0.0
	 */
	public static function set_visibility( $type, $id, $status ) {
		switch ( $status ) {
			case '0':
			case 'private':
				$status = '0';
				break;
			case '1':
			case 'public':
				$status = '1';
				break;
			case '2':
			case 'protected':
				$status = '2';
				break;
			default:
				delete_metadata( $type, $id, 'geo_public' );
				return false;
		}
		update_metadata( $type, $id, 'geo_public', $status );
	}

	/**
	 * Retrieves visibility property on any metadata capable object.
	 *
	 * Gets visibility from posts, comments, terms, etc.
	 *
	 * @param string $type Object Type.
	 * @param int    $id Post ID, Comment ID, User ID, etc.
	 * @return false|string $status Visibility.
	 * @since 1.0.0
	 */
	public static function get_visibility( $type = null, $id = null ) {
		if ( is_null( $type ) && is_null( $id ) ) {
			$status = false;
		} else {
			$status = get_metadata( $type, $id, 'geo_public', true );
		}

		if ( false === $status ) {
			$status = (int) get_option( 'geo_public' );
		}
		switch ( (int) $status ) {
			case 0:
				return 'private';
			case 1:
				return 'public';
			case 2:
				return 'protected';
			default:
				return false;
		}
	}

	/**
	 * Return meta query arguments based on input.
	 *
	 * @param array $geo WP_Query arguments.
	 *
	 * @since 1.0.0
	 */
	public static function filter_geo_query( $geo ) {
		$args   = array(
			'relation' => 'OR',
			array(
				'key'     => 'geo_longitude',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'geo_address',
				'compare' => 'EXISTS',
			),
		);
		$public = array(
			'key'     => 'geo_public',
			'type'    => 'numeric',
			'compare' => '=',
		);
		switch ( $geo ) {
			case 'all':
				return $args;
			case 'private':
				$public['value'] = (int) 0;
				return $public;
			case 'public':
			case 'map':
				$public['value'] = (int) 1;
				return $public;
			case 'text':
			case 'description':
			case 'protected':
				$public['value'] = (int) 2;
				return $public;
			default:
				return array();
		}
	}


	/**
	 * Filters Location in Posts.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 1.0.0
	 */
	public static function filter_location_posts( $query ) {
		if ( ! array_key_exists( 'geo', $query->query_vars ) || ! $query->is_main_query() ) {
			return;
		}

		$geo  = $query->get( 'geo' );
		$args = self::filter_geo_query( $geo );
		if ( ! empty( $args ) ) {
			$query->set( 'meta_query', array( $args ) );
		}
	}


	/**
	 * Filters Location in Comments.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 1.0.0
	 */
	public static function pre_get_comments( $query ) {
		if ( ! isset( $_REQUEST['geo'] ) ) {
			return;
		}
		$geo  = sanitize_text_field( $_REQUEST['geo'] );
		$args = self::filter_geo_query( $geo );
		if ( ! empty( $args ) ) {
			$query->query_vars['meta_query'] = array( $args );
			$query->meta_query->parse_query_vars( $query->query_vars );
		}
	}

	/**
	 * Set GeoData.
	 *
	 * @param string $type
	 * @param int    $id
	 * @param array  $geodata {
	 *   An array of details about a location.
	 *
	 *  @type float $latitude Decimal Latitude.
	 *  @type float $longitude Decimal Longitude.
	 *  @type float $altitude Altitude in Meters.
	 *  @type string $icon Icon.
	 *  @type string $address Textual Description of location.
	 *  @type int $map_zoom Zoom for Map Display.
	 *  @type float $speed Speed in Meters.
	 *  @type float $heading If set, between 0 and 360 degrees.
	 *  @type string $wikipedia_link URL of the Airport Homepage
	 *  @type string $visibility Can be either public, private, or protected.
	 *  @type string $timezone Timezone string.
	 *  @type array $weather Array of Weather Properties.
	 * }
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 1.0.0
	 */
	public static function set_geodata( $type, $id, $geodata ) {
		if ( ! is_array( $geodata ) ) {
			return false;
		}
		$geodata = wp_array_slice_assoc( $geodata, static::$properties );
		if ( isset( $geodata['map_zoom'] ) ) {
			$geodata['zoom'] = $geodata['map_zoom'];
			unset( $geodata['map_zoom'] );
		}

		if ( isset( $geodata['visibility'] ) ) {
			self::set_visibility( $type, $id, $geodata['visibility'] );
			unset( $geodata['visibility'] );
		}
		foreach ( $geodata as $key => $value ) {
			update_metadata( $type, $id, 'geo_' . $key, $value );
		}
		return true;
	}


	/**
	 * Get Geo Meta Data on an Object.
	 *
	 * @param string  $type Object type.
	 * @param int     $id Object ID.
	 * @param boolean $full Return just location and visibility or everything.
	 * @return array $geodata See get_geodata and set_geodata for full list.
	 *
	 * @since 1.0.0
	 */
	private static function get_geometadata( $type, $id, $full = true ) {
		$geodata              = array();
		$geodata['longitude'] = get_metadata( $type, $id, 'geo_longitude', true );
		$geodata['latitude']  = get_metadata( $type, $id, 'geo_latitude', true );
		$geodata['altitude']  = get_metadata( $type, $id, 'geo_altitude', true );
		$geodata['trip']      = get_metadata( $type, $id, 'geo_trip', true );
		$geodata['address']   = get_metadata( $type, $id, 'geo_address', true );
		$geodata['icon']      = get_metadata( $type, $id, 'geo_icon', true );
		if ( empty( $geodata['icon'] ) ) {
			$geodata['icon'] = self::get_default_icon();
		}
		$geodata['visibility'] = self::get_visibility( $type, $id );

		if ( $full ) {
			$geodata['timezone'] = get_metadata( $type, $id, 'geo_timezone', true );
			$geodata['map_zoom'] = get_metadata( $type, $id, 'geo_zoom', true );
			$geodata['weather']  = get_metadata( $type, $id, 'geo_weather', true );
		}
		$geodata = array_filter( $geodata );
		if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) && empty( $geodata['trip'] ) ) {
			return null;
		}
		return array_filter( $geodata );
	}

	/**
	 * Does this object have location data.
	 *
	 * @param mixed $object Object type.
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 1.0.0
	 */
	public static function has_location( $object = null ) {
		$data = self::get_geodata( $object );
		return ! is_null( $data );
	}


	/**
	 * Get GeoData on an Object.
	 *
	 * @param mixed   $object Can be WP_Comment, WP_User, WP_Post, WP_Term, or int which will be considered a post id.
	 * @param boolean $full Return all or just some of the data.
	 * @return array $geodata {
	 *  An array of details about a location.
	 *
	 *  @type float $latitude Decimal Latitude.
	 *  @type float $longitude Decimal Longitude.
	 *  @type float $altitude Altitude in Meters.
	 *  @type string $address Textual Description of location.
	 *  @type int $map_zoom Zoom for Map Display.
	 *  @type float $speed Speed in Meters.
	 *  @type float $heading If set, between 0 and 360 degrees.
	 *  @type string $wikipedia_link URL of the Airport Homepage
	 *  @type string $visibility Can be either public, private, or protected.
	 *  @type string $timezone Timezone string.
	 *  @type array $weather Array of Weather Properties.
	 * }
	 *
	 * @since 1.0.0
	 */
	public static function get_geodata( $type, $id, $full = true ) {
		$geodata = self::get_geometadata( $type, $id, $full );
		if ( ! $geodata ) {
			return null;
		}
		return $geodata;
	}


	/**
	 * Registers Geo Metadata.
	 *
	 * @since 1.0.0
	 */
	public static function register_meta() {
		$args = array(
			'sanitize_callback' => 'clean_coordinate',
			'type'              => 'number',
			'description'       => __( 'Latitude', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_latitude', $args );
		register_meta( 'comment', 'geo_latitude', $args );
		register_meta( 'user', 'geo_latitude', $args );
		register_meta( 'term', 'geo_latitude', $args );

		$args = array(
			'sanitize_callback' => 'clean_coordinate',
			'type'              => 'number',
			'description'       => __( 'Longitude', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_longitude', $args );
		register_meta( 'comment', 'geo_longitude', $args );
		register_meta( 'user', 'geo_longitude', $args );
		register_meta( 'term', 'geo_longitude', $args );

		$args = array(
			'type'         => 'string',
			'description'  => __( 'Timezone of Location', 'simple-location' ),
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_timezone', $args );
		register_meta( 'comment', 'geo_timezone', $args );
		register_meta( 'user', 'geo_timezone', $args );
		register_meta( 'term', 'geo_timezone', $args );

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_address' ),
			'type'              => 'string',
			'description'       => __( 'Geodata Address', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_address', $args );
		register_meta( 'comment', 'geo_address', $args );
		register_meta( 'user', 'geo_address', $args );
		register_meta( 'term', 'geo_address', $args );

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'esc_attr' ),
			'type'              => 'string',
			'description'       => __( 'Geodata Icon', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_icon', $args );
		register_meta( 'comment', 'geo_icon', $args );
		register_meta( 'user', 'geo_icon', $args );
		register_meta( 'term', 'geo_icon', $args );

		// Numeric Geo Properties
		$numerics = array(
			'altitude' => __( 'Altitude', 'simple-location' ),
			'zoom'     => __( 'Geodata Zoom for Map Display', 'simple-location' ),
			/*
			 * Officially 0 is private 1 is public and absence or non-zero is assumed public.
			 * Therefore any non-zero number could be used to specify different display options.
			 */
			'public'   => __( 'Geodata Public', 'simple-location' ),
		);
		foreach ( $numerics as $prop => $description ) {
			$args = array(
				'type'         => 'number',
				'description'  => $description,
				'single'       => true,
				'show_in_rest' => false,
			);
			foreach ( array( 'post', 'comment', 'user', 'term' ) as $type ) {
				register_meta( $type, 'geo_' . $prop, $args );
			}
		}
	}

	public static function display_altitude( $altitude ) {
		$aunits = get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
		switch ( $aunits ) {
			case 'imperial':
				$altitude = round( $altitude * 3.281 );
				$aunits   = 'ft';
				break;
			default:
				$aunits = 'm';
		}
		return $altitude . $aunits;
	}

	// Return marked up coordinates
	public static function get_the_geo( $loc, $display = false ) {
		$string = $display ? '<span class="p-%1$s">%2$f</span>' : '<data class="p-%1$s" value="%2$f"></data>';
		$return = '';
		foreach ( array( 'latitude', 'longitude', 'altitude' ) as $value ) {
			if ( isset( $loc[ $value ] ) ) {
				$return .= sprintf( $string, $value, $loc[ $value ] );
			}
		}
		return $return;
	}

	public static function get_map( $type, $id, $args = array() ) {
		$loc = self::get_geodata( $type, $id );
		if ( is_array( $loc ) && ( 'public' === $loc['visibility'] ) ) {
			$map = Loc_Config::map_provider();
			if ( ! $map instanceof Map_Provider ) {
				return '';
			}
			if ( isset( $loc['latitude'] ) && ( isset( $loc['longitude'] ) ) ) {
				$loc = array_merge( $loc, $args );
				$map->set( $loc );
				return $map->get_the_map();
			}
		}
		return '';
	}

	public static function show_map() {
		if ( get_option( 'sloc_map_display' ) ) {
			return true;
		} else {
			return is_single();
		}
	}

	public static function content_map( $content ) {
		if ( self::show_map() ) {
			$content .= self::get_map( 'post', get_the_ID() );
		}
		return $content;
	}

	public static function location_content( $content ) {
		$loc = self::get_location( 'post', get_the_ID() );
		if ( ! empty( $loc ) ) {
			$content .= $loc;
		}
		return $content;
	}

	public static function location_comment( $comment_text, $comment ) {
		$loc = self::get_location(
			'comment',
			$comment->comment_ID,
			array(
				'text' => false,
				'icon' => false,
			)
		);
		if ( ! empty( $loc ) ) {
			$comment_text .= PHP_EOL . $loc . PHP_EOL;
		}
		return $comment_text;
	}

	/**
	 * Returns the default icon.
	 *
	 * @return string Default Icon.
	 */
	public static function get_default_icon() {
		return 'fa-location-arrow';
	}

	/**
	 * Returns list of available icons.
	 *
	 * @return array List of Icon Options.
	 */
	public static function get_iconlist() {
		return array(
			'fa-location-arrow'            => __( 'Location Arrow', 'simple-location' ),
			'fa-compass'                   => __( 'Compass', 'simple-location' ),
			'fa-map'                       => __( 'Map', 'simple-location' ),
			'fa-map-marker'                => __( 'Map Marker', 'simple-location' ),
			'fa-passport'                  => __( 'Passport', 'simple-location' ),
			'fa-home'                      => __( 'Home', 'simple-location' ),
			'fa-globe-asia'                => __( 'Globe - Asia', 'simple-location' ),
			'fa-globe-americas'            => __( 'Globe - Americas', 'simple-location' ),
			'fa-globe-europe'              => __( 'Globe - Europe', 'simple-location' ),
			'fa-globe-africa'              => __( 'Globe - Africa', 'simple-location' ),
			'fa-plane'                     => __( 'Plane', 'simple-location' ),
			'fa-train'                     => __( 'Train', 'simple-location' ),
			'fa-taxi'                      => __( 'Taxi', 'simple-location' ),
			'fa-tram'                      => __( 'Tram', 'simple-location' ),
			'fa-biking'                    => __( 'Biking', 'simple-location' ),
			'fa-bus'                       => __( 'Bus', 'simple-location' ),
			'fa-bus-alt'                   => __( 'Bus (Alt)', 'simple-location' ),
			'fa-car'                       => __( 'Car', 'simple-location' ),
			'fa-helicopter'                => __( 'Helicopter', 'simple-location' ),
			'fa-horse'                     => __( 'Horse', 'simple-location' ),
			'fa-ship'                      => __( 'Ship', 'simple-location' ),
			'fa-running'                   => __( 'Running', 'simple-location' ),
			'fa-shuttlevan'                => __( 'Shuttle Van', 'simple-location' ),
			'fa-subway'                    => __( 'Subway', 'simple-location' ),
			'fa-suitcase'                  => __( 'Suitcase', 'simple-location' ),
			'fa-suitcase-rolling'          => __( 'Suitcase - Rolling', 'simple-location' ),
			'fa-walking'                   => __( 'Walking', 'simple-location' ),
			'fa-running'                   => __( 'Running', 'simple-location' ),
			'americanairlines'             => __( 'American Airlines', 'simple-location' ),
			's7airlines'                   => __( 'S7 Airlines', 'simple-location' ),
			'unitedairlines'               => __( 'United Airlines', 'simple-location' ),
			'pegasusairlines'              => __( 'Pegasus Airlines', 'simple-location' ),
			'ethiopianairlines'            => __( 'Ethiopian Airlines', 'simple-location' ),
			'southwestairlines'            => __( 'Southwest Airlines', 'simple-location' ),
			'lotpolishairlines'            => __( 'Lot Polish Airlines', 'simple-location' ),
			'chinaeasternairlines'         => __( 'China Eastern Airlines', 'simple-location' ),
			'chinasouthernairlines'        => __( 'China Southern Airlines', 'simple-location' ),
			'aerlingus'                    => __( 'Aer Lingus', 'simple-location' ),
			'aeroflot'                     => __( 'Aeroflot', 'simple-location' ),
			'aeromexico'                   => __( 'Aeromexico', 'simple-location' ),
			'aircanada'                    => __( 'Air Canada', 'simple-location' ),
			'airchina'                     => __( 'Air China', 'simple-location' ),
			'airfrance'                    => __( 'Air France', 'simple-location' ),
			'airasia'                      => __( 'Air Asia', 'simple-location' ),
			'airbus'                       => __( 'Airbus', 'simple-location' ),
			'boeing'                       => __( 'Boeing', 'simple-location' ),
			'emirates'                     => __( 'Emirates', 'simple-location' ),
			'etihadairways'                => __( 'Etihad Airways', 'simple-location' ),
			'qatarairways'                 => __( 'Qatar Airways', 'simple-location' ),
			'ryanair'                      => __( 'Ryanair', 'simple-location' ),
			'sanfranciscomunicipalrailway' => __( 'San Francisco Municipal Railway', 'simple-location' ),
			'shanghaimetro'                => __( 'Shanghai Metro', 'simple-location' ),
			'turkishairlines'              => __( 'Turkish Airlines', 'simple-location' ),
			'wizzair'                      => __( 'Wizz Air', 'simple-location' ),
			'alitalia'                     => __( 'Alitalia', 'simple-location' ),
			'ana'                          => __( 'ANA', 'simple-location' ),
			'delta'                        => __( 'Delta', 'simple-location' ),
			'easyjet'                      => __( 'easyJet', 'simple-location' ),
			'lufthansa'                    => __( 'Lufthansa', 'simple-location' ),
			'britishairways'               => __( 'British Airways', 'simple-location' ),
		);
	}


	/**
	 * Generates Pulldown list of Icons.
	 *
	 * @param string  $icon Icon to be Selected.
	 * @param boolean $echo Echo or Return.
	 * @return string Select Option. Optional.
	 */
	public static function icon_select( $icon, $echo = false ) {
		$choices = self::get_iconlist();
		if ( ! $icon ) {
			$icon = self::get_default_icon();
		}
		$return = '';
		foreach ( $choices as $value => $text ) {
			$return .= sprintf( '<option value="%1s" %2s>%3s</option>', esc_attr( $value ), selected( $icon, $value, false ), esc_html( $text ) );
		}
		if ( ! $echo ) {
			return $return;
		}
		echo wp_kses(
			$return,
			array(
				'option' => array(
					'selected' => array(),
					'value'    => array(),
				),
			)
		);
	}

	/**
	 * Return the marked up icon standardized to the fonts.
	 *
	 * @param string $icon Name of Icon.
	 * @param string $summary Description of Icon. Optional.
	 * @return string marked up icon
	 */
	public static function get_icon( $icon = null, $summary = null ) {
		if ( is_null( $icon ) ) {
			$icon = self::get_default_icon();
		}
		if ( 'none' === $icon ) {
			return '';
		}
		if ( ! $summary ) {
			$list    = self::get_iconlist();
			$summary = array_key_exists( $icon, $list ) ? $list[ $icon ] : $icon;
		}
		$svg = sprintf( '%1$ssvgs/%2$s.svg', plugin_dir_path( __DIR__ ), $icon );
		if ( file_exists( $svg ) ) {
			$svg = file_get_contents( $svg );
		}
		if ( $svg ) {
			return PHP_EOL . sprintf( '<span class="sloc-location-icon sloc-icon-%1$s" style="display: inline-block; max-height: 1em; margin-right: 0.1em;" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $icon ), esc_attr( $summary ), $svg );
		}
		return '';
	}

	public static function get_location( $type, $id, $args = array() ) {
		$loc = self::get_geodata( $type, $id );
		if ( ! is_array( $loc ) ) {
			return '';
		}
		if ( Geo_Base::current_user_can_read( $object ) && 'public' !== $loc['visibility'] ) {
			$loc['visibility'] = 'public';
			if ( isset( $loc['address'] ) ) {
				/* translators: Prefaces the address 1. with the private status */
				$loc['address'] = sprintf( __( 'Hidden: %1$s', 'simple-location' ), $loc['address'] );
			}
		}
		if ( 'private' === $loc['visibility'] ) {
			return '';
		}
		$defaults = array(
			'height'        => null,
			'width'         => null,
			'map_zoom'      => null,
			'mapboxstyle'   => null,
			'mapboxuser'    => null,
			'weather'       => true,
			'taxonomy'      => get_option( 'sloc_taxonomy_display' ), // Show taxonomy instead of address field.
			'link'          => true, // Add Map Link
			'icon'          => true, // Show Location Icon
			'text'          => false, // Show Description
			'markup'        => true, // Mark up with Microformats
			'description'   => __( 'Location: ', 'simple-location' ),
			'wrapper-class' => array( 'sloc-display' ), // Class or classes to wrap the entire location in
			'wrapper-type'  => 'div', // HTML type to wrap the entire location in
		);
		$default  = apply_filters( 'simple_location_display_defaults', $defaults );
		$args     = wp_parse_args( $args, $defaults );
		$args     = array_merge( $loc, $args );
		$map      = Loc_Config::map_provider();
		if ( is_null( $map ) ) {
			return __( 'Error: Invalid Map Provider', 'simple-location' );
		}
		$map->set( $loc );
		$wrap  = '<%1$s class="%2$s">%3$s</%1$s>';
		$class = is_array( $args['wrapper-class'] ) ? $args['wrapper-class'] : explode( ' ', $args['wrapper-class'] );
		if ( $args['markup'] ) {
			$class[] = 'p-location';
			$class[] = 'h-adr';
		}
		$c = array( PHP_EOL );

		if ( $args['text'] ) {
			$c[] = $args['description'];
		}

		if ( 'public' === $args['visibility'] ) {
			if ( $args['markup'] ) {
				$c[] = self::get_the_geo( $loc );
			}
			if ( isset( $loc['altitude'] ) ) {
				if ( get_option( 'sloc_altitude' ) < (int) $loc['altitude'] ) {
					$loc['altitude'] = self::display_altitude( $loc['altitude'] );
				} else {
					unset( $loc['altitude'] );
				}
			}
			if ( ! array_key_exists( 'address', $loc ) ) {
				if ( ! array_key_exists( 'latitude', $loc ) ) {
					$loc['address'] = '';
				} else {
					$loc['address'] = dec_to_dms( $loc['latitude'], $loc['longitude'], ifset( $loc['altitude'] ) );
				}
			}

			if ( isset( $loc['altitude'] ) ) {
				$loc['address'] .= sprintf( '(%1$s)', $loc['altitude'] );
			}
			$adclass = $args['markup'] ? 'p-label' : '';
			if ( $args['link'] ) {
				$c[] = sprintf( '<a class="%1$s" href="%2$s">%3$s</a>', $adclass, $map->get_the_map_url(), $loc['address'] );
			} else {
				$c[] = sprintf( '<span class="%1$s">%2$s</span>', $adclass, $loc['address'] );
			}
		} elseif ( isset( $args['address'] ) ) {
			$c[] = $args['address'];
		}
		if ( $args['icon'] ) {
			array_unshift( $c, self::get_icon( $loc['icon'] ) );
		}
		if ( isset( $loc['weather'] ) && $args['weather'] ) {
			$c[] = Sloc_Weather_Data::get_the_weather( $loc['weather'] );
		}

		$return = implode( PHP_EOL, $c );
		return sprintf( '<%1s class="%2$s">%3$s</%1$s>', $args['wrapper-type'], implode( ' ', $class ), $return );
	}

}
