<?php
// LocationIQ Geocode API Provider
class Geo_Provider_LocationIQ extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'LocationIQ', 'simple-location' );
		$this->slug = 'locationiq';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_locationiq_api' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'locationiq' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_locationiq_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Location IQ API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'locationiq_api', // id
			__( 'LocationIQ API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_locationiq_api',
			)
		);
	}
	public function elevation() {
		return 0;
	}




	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Bing', 'simple-location' ) );
		}
		$args = array(
			'key'    => $this->api,
			'format' => 'json',
			'lat'    => $this->latitude,
			'lon'    => $this->longitude,
		);

		$json = $this->fetch_json( 'https://us1.locationiq.com/v1/reverse.php', $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$address = $json['address'];

		if ( 'us' === $address['country_code'] ) {
			$region = self::ifnot(
				$address,
				array(
					'state',
					'county',
				)
			);
		} else {
			$region = self::ifnot(
				$address,
				array(
					'county',
					'state',
				)
			);
		}
		$street  = ifset( $address['house_number'], '' ) . ' ';
		$street .= self::ifnot(
			$address,
			array(
				'road',
				'highway',
				'footway',
			)
		);
		$addr    = array(
			'name'             => self::ifnot(
				$address,
				array(
					'attraction',
					'building',
					'hotel',
					'address29',
					'address26',
				)
			),
			'street-address'   => $street,
			'extended-address' => self::ifnot(
				$address,
				array(
					'boro',
					'neighbourhood',
					'suburb',
				)
			),
			'locality'         => self::ifnot(
				$address,
				array(
					'hamlet',
					'village',
					'town',
					'city',
				)
			),
			'region'           => $region,
			'country-name'     => self::ifnot(
				$address,
				array(
					'country',
				)
			),
			'postal-code'      => self::ifnot(
				$address,
				array(
					'postcode',
				)
			),
			'country-code'     => strtoupper( $address['country_code'] ),
			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'raw'              => $address,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$codes                = json_decode(
				wp_remote_retrieve_body(
					wp_remote_get( 'http://country.io/names.json' )
				),
				true
			);
			$addr['country-name'] = $codes[ $addr['country-code'] ];
		}

		$addr                 = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );

		if ( WP_DEBUG ) {
			$addr['raw'] = $json;
		}
		return $addr;
	}
}

register_sloc_provider( new Geo_Provider_LocationIQ() );
