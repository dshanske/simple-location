<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using Pelias API. https://github.com/pelias/documentation/blob/master/reverse.md#reverse-geocoding
 *
 * @since 1.0.0
 */
class Geo_Provider_Pelias extends Geo_Provider {

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @param array $args {
	 *  Arguments.
	 *  @type string $api API Key.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type float $altitude Altitude.
	 *  @type string $address Formatted Address String
	 *  @type int $reverse_zoom Reverse Zoom. Default 18.
	 *  @type string $user User name.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Pelias', 'simple-location' );
		$this->slug = 'pelias';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_pelias_api' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'pelias' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		register_setting(
			'sloc_providers', // Option group.
			'sloc_pelias_api', // Option name.
			array(
				'type'         => 'string',
				'description'  => 'Pelias Service API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'sloc_providers', // Option group.
			'sloc_pelias_url', // Option name.
			array(
				'type'         => 'string',
				'description'  => 'Pelias Service URL',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		add_settings_field(
			'pelias_api', // ID.
			__( 'Pelias API Key', 'simple-location' ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_providers', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => 'sloc_pelias_api',
			)
		);
		add_settings_field(
			'pelias_url', // ID.
			__( 'Pelias Server URL', 'simple-location' ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_providers', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => 'sloc_pelias_url',
			)
		);
	}

	/**
	 * Returns elevation.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	public function elevation() {
		return 0;
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for this Pelias instance', 'simple-location' ) );
		}
		if ( ! wp_http_validate_url( get_option( 'sloc_pelias_url' ) ) ) {
			return new WP_Error( 'missing_pelias_url', __( 'You did not provide a Pelias URL', 'simple-location' ) );
		}

		$args = array(
			'api_key'   => $this->api,
			'point.lat' => $this->latitude,
			'point.lon' => $this->longitude,
			'size'      => 1,
		);
		$url  = trailingslashit( get_option( 'sloc_pelias_url' ) ) . 'reverse/';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		return $this->address_to_mf( $json );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	protected function address_to_mf( $json ) {
		$json    = $json['features'];
		$address = $json[0]['properties'];

		// Give the result with the highest confidence level. At equal confidence levels prioritize venues over addresses'
		foreach ( $json as $feature ) {
			if ( $feature['properties']['confidence'] < $address['confidence'] ) {
				break;
			} elseif ( $feature['properties']['confidence'] > $address['confidence'] ) {
				$address = $feature['properties'];
				continue;
			}
			// Prioritize venue over address
			if ( 'venue' === $feature['properties']['layer'] && 'venue' !== $address['layer'] ) {
				$address = $feature['properties'];
				continue;
			}
			if ( $feature['properties']['distance'] === $address['distance'] && $feature['properties']['distance'] < $address['distance'] ) {
				$address = $features['properties'];
			}
		}

		$number = self::ifnot(
			$address,
			array(
				'housenumber',
			)
		);

		$street = self::ifnot(
			$address,
			array(
				'street',
			)
		);

		$country_code = ifset( $address['country_a'] );
		$country_code = self::country_code_iso3( $country_code );

		// Adjust position of house number/name based on country practice.
		if ( self::house_number( $county_code ) ) {
			$street_address = $street . ' ' . $number;
		} else {
			$street_address = $number . ' ' . $street;
		}
		$street_address = trim( $street_address );

		if ( 'address' !== $address['layer'] ) {
			$name = ifset( $address['name'] );
		}

		$addr = array(
			'name'             => $name,
			'street-address'   => $street_address,
			'street'           => $street,
			'street-number'    => $number,
			'extended-address' => self::ifnot(
				$address,
				array(
					'borough',
					'neighbourhood',
					'suburb',
				)
			),
			'locality'         => self::ifnot(
				$address,
				array(
					'locality',
				)
			),
			'region'           => self::ifnot(
				$address,
				array(
					'region',
				)
			),
			'region-code'      => self::ifnot(
				$address,
				array(
					'region_a',
				)
			),
			'country-name'     => self::ifnot(
				$address,
				array(
					'country',
				)
			),
			'country-code'     => $country_code,
			'postal-code'      => self::ifnot(
				$address,
				array(
					'postalcode',
				)
			),

			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
		);

		if ( WP_DEBUG ) {
			$addr['raw']     = $json;
			$addr['feature'] = $address;
		}

		$addr = array_filter( $addr );
		if ( ! array_key_exists( 'country-code', $addr ) && array_key_exists( 'country-name', $addr ) ) {
			$addr['country-code'] = self::country_code( $addr['country-name'] );
		}
		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
		}
		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}

	/**
	 * Geocode address.
	 *
	 * @param  string $address String representation of location.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function geocode( $address ) {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for this Pelias Instance', 'simple-location' ) );
		}
		$args = array(
			'api_key' => $this->api,
			'text'    => $address,
		);

		$url  = trailingslashit( get_option( 'sloc_pelias_url' ) ) . 'search/';
		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		if ( wp_is_numeric_array( $json ) ) {
			$json = $json[0];
		}
		$address             = $json['features'][0];
		$return              = $this->address_to_mf( $json );
		$return['latitude']  = $address['geometry']['coordinates'][1];
		$return['longitude'] = $address['geometry']['coordinates'][0];
		return array_filter( $return );
	}
}

register_sloc_provider( new Geo_Provider_Pelias() );
