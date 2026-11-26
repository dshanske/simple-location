<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reverse geocoding using Traccar Geocoder, https://github.com/traccar/traccar-geocoder.
 *
 * Traccar Geocoder is an open source reverse geocoder for OpenStreetMap data. You run it yourself, or use Traccar's
 * hosted service. A site can list several servers, each with its own API key, such as one with the areas it usually
 * posts from and another rebuilt with the regions it is visiting. They are asked in order until one has the place.
 *
 * Traccar Geocoder has no forward geocoding, so looking up an address returns an error and the geocoding chain moves
 * on to its next provider.
 *
 * @since 6.5.0
 */
class Geo_Provider_Traccar_Geocoder extends Geo_Provider {

	/**
	 * Setting holding the servers.
	 *
	 * @since 6.5.0
	 * @var string
	 */
	const OPTION = 'sloc_traccar_geocoder_servers';

	/**
	 * Setting holding the search distance, in meters.
	 *
	 * @since 6.5.0
	 * @var string
	 */
	const DISTANCE_OPTION = 'sloc_traccar_geocoder_distance';

	/**
	 * Largest search distance accepted, in meters.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const MAX_DISTANCE = 1000;

	/**
	 * Servers to ask, in order.
	 *
	 * @since 6.5.0
	 * @var array[] Each has 'url', without a trailing slash, and 'key'.
	 */
	protected array $servers = array();

	/**
	 * Search distance in meters, or 0 for the server's default.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	protected int $distance = 0;

	/**
	 * Constructor.
	 *
	 * @since 6.5.0
	 *
	 * @param array $args {
	 *     Arguments. See Geo_Provider::__construct() for the others.
	 *
	 *     @type array[] $servers  Servers, each with 'url' and 'key'. Default the servers setting.
	 *     @type int     $distance Search distance in meters, or 0 for the server's default. Default the setting.
	 * }
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Traccar Geocoder', 'simple-location' );
		$this->slug        = 'traccar_geocoder';
		$this->url         = 'https://www.traccar.org/product/geocoder/';
		$this->description = __( 'Traccar Geocoder is an open source reverse geocoder for OpenStreetMap data, which you run yourself or use as Traccar\'s paid hosted service. List several servers, each with its own API key, and they are asked in order. It can\'t look up an address, so add another provider after it for that.', 'simple-location' );

		$this->servers  = self::sanitize_servers( $args['servers'] ?? get_option( self::OPTION ) );
		$this->distance = self::sanitize_distance( $args['distance'] ?? get_option( self::DISTANCE_OPTION ) );

		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 6.5.0
	 */
	public static function init() {
		register_setting(
			'sloc_api',
			self::OPTION,
			array(
				'type'              => 'array',
				'description'       => 'Traccar Geocoder Servers',
				'show_in_rest'      => false,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'save_servers' ),
			)
		);
		register_setting(
			'sloc_api',
			self::DISTANCE_OPTION,
			array(
				'type'              => 'integer',
				'description'       => 'Traccar Geocoder Search Distance',
				'show_in_rest'      => false,
				'default'           => 0,
				'sanitize_callback' => array( __CLASS__, 'sanitize_distance' ),
			)
		);
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 6.5.0
	 */
	public static function admin_init() {
		Sloc_Provider::$api_names['traccar'] ??= __( 'Traccar Geocoder', 'simple-location' );
		add_settings_field(
			self::OPTION,
			__( 'Traccar Geocoder Servers', 'simple-location' ),
			array( __CLASS__, 'servers_callback' ),
			'sloc_api',
			'sloc_api',
			array( 'label_for' => self::OPTION )
		);
		add_settings_field(
			self::DISTANCE_OPTION,
			__( 'Traccar Geocoder Search Distance', 'simple-location' ),
			array( 'Loc_Config', 'number_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => self::DISTANCE_OPTION,
				'description' => __( 'Optional. How far from a location to look for a street or address, in meters. 0 uses the server\'s default, 75 meters.', 'simple-location' ),
			)
		);
	}

	/**
	 * Echoes the servers setting: a row for each server, and an empty row for a new one.
	 *
	 * @since 6.5.0
	 *
	 * @param array $args Arguments.
	 */
	public static function servers_callback( $args ) {
		$servers = self::get_servers();
		echo '<ol id="sloc-traccar-geocoder-servers">';
		foreach ( $servers as $index => $server ) {
			self::server_inputs( $index, $server );
		}
		self::server_inputs( count( $servers ) );
		echo '</ol>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Each server\'s address and an API key from its dashboard. Servers are asked in order, and the next one is asked when a server can\'t be reached, refuses the key, is over its limit or doesn\'t have the place. Save to add another server.', 'simple-location' )
		);
	}

	/**
	 * Echoes the inputs for one server.
	 *
	 * @since 6.5.0
	 *
	 * @param int   $index  Position.
	 * @param array $server Server, with 'url' and 'key'. Default empty, for a new server.
	 */
	protected static function server_inputs( $index, $server = array() ) {
		$name = self::OPTION;
		echo '<li>';
		printf(
			'<input type="url" class="regular-text" name="%1$s[%2$d][url]"%3$s value="%4$s" placeholder="%5$s" aria-label="%6$s" /> ',
			esc_attr( $name ),
			(int) $index,
			0 === $index ? ' id="' . esc_attr( $name ) . '"' : '',
			esc_url( $server['url'] ?? '' ),
			esc_attr( 'https://geocoder.example.com' ),
			esc_attr__( 'Server URL', 'simple-location' )
		);
		printf(
			'<input type="password" autocomplete="off" data-lpignore="true" name="%1$s[%2$d][key]" value="%3$s" placeholder="%4$s" aria-label="%4$s" />',
			esc_attr( $name ),
			(int) $index,
			esc_attr( $server['key'] ?? '' ),
			esc_attr__( 'API Key', 'simple-location' )
		);
		if ( ! empty( $server ) ) {
			printf(
				' <label><input type="checkbox" name="%1$s[%2$d][remove]" value="1" /> %3$s</label>',
				esc_attr( $name ),
				(int) $index,
				esc_html__( 'Remove', 'simple-location' )
			);
		}
		echo '</li>';
	}

	/**
	 * Sanitizes a list of servers.
	 *
	 * Rows without a URL, rows marked to be removed, and repeats of a URL already listed are dropped.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $value Servers, each an array with 'url' and 'key'.
	 * @return array[] Servers, each with 'url', without a trailing slash, and 'key'.
	 */
	public static function sanitize_servers( $value ) {
		$servers = array();
		foreach ( is_array( $value ) ? $value : array() as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['remove'] ) || ! is_string( $row['url'] ?? null ) ) {
				continue;
			}
			$url = untrailingslashit( (string) esc_url_raw( trim( $row['url'] ), array( 'http', 'https' ) ) );
			if ( '' === $url || isset( $servers[ $url ] ) ) {
				continue;
			}
			$key             = is_string( $row['key'] ?? null ) ? self::sanitize_api_key( $row['key'] ) : '';
			$servers[ $url ] = array(
				'url' => $url,
				'key' => $key,
			);
		}
		return array_values( $servers );
	}

	/**
	 * Sanitizes the servers setting when it is saved, and forgets the errors of servers no longer listed.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $value Servers from the form.
	 * @return array[] Servers. See sanitize_servers().
	 */
	public static function save_servers( $value ) {
		$servers = self::sanitize_servers( $value );
		$kept    = wp_list_pluck( $servers, 'url' );
		foreach ( self::get_servers() as $old ) {
			if ( ! in_array( $old['url'], $kept, true ) ) {
				Sloc_Provider_Errors::forget( 'geo', self::server_slug( $old['url'] ) );
			}
		}
		return $servers;
	}

	/**
	 * Sanitizes the search distance.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $value Distance in meters.
	 * @return int Distance from 0, for the server's default, to MAX_DISTANCE.
	 */
	public static function sanitize_distance( $value ) {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}
		return max( 0, min( self::MAX_DISTANCE, (int) $value ) );
	}

	/**
	 * Returns the saved servers.
	 *
	 * @since 6.5.0
	 *
	 * @return array[] Servers, each with 'url' and 'key'.
	 */
	public static function get_servers() {
		return self::sanitize_servers( get_option( self::OPTION ) );
	}

	/**
	 * Returns the slug a server's errors are recorded under.
	 *
	 * @since 6.5.0
	 *
	 * @param string $url Server URL.
	 * @return string Slug, such as 'traccar_geocoder-1a2b3c4d'.
	 */
	public static function server_slug( $url ) {
		return 'traccar_geocoder-' . substr( md5( (string) $url ), 0, 8 );
	}

	/**
	 * Returns a copy of the provider standing for one server, to record or clear that server's errors.
	 *
	 * @since 6.5.0
	 *
	 * @param array $server Server, with 'url'.
	 * @return Geo_Provider_Traccar_Geocoder Copy, with the server's slug and a name that includes its host.
	 */
	protected function for_server( $server ) {
		$host = wp_parse_url( $server['url'], PHP_URL_HOST );
		$port = wp_parse_url( $server['url'], PHP_URL_PORT );
		if ( $port ) {
			$host .= ':' . $port;
		}
		$copy       = clone $this;
		$copy->slug = self::server_slug( $server['url'] );
		/* translators: %s: Server host name, such as geocoder.example.com. */
		$copy->name = sprintf( __( 'Traccar Geocoder (%s)', 'simple-location' ), $host ? $host : $server['url'] );
		return $copy;
	}

	/**
	 * Return an address.
	 *
	 * Asks each server in order, and returns the first answer with a street or a city. A server that answers with only a
	 * country or region, which it reads from boundaries, doesn't have the place, so the next server is asked.
	 *
	 * An error an administrator can fix, such as a refused key, is recorded for that server, even when another server
	 * answers, and cleared when it answers again. When no server answers, the error returned isn't recorded again
	 * for the provider as a whole.
	 *
	 * @since 6.5.0
	 *
	 * @return array|WP_Error Microformats2 address elements, or an error: `no_results` when no server has the place,
	 *                        `missing_traccar_geocoder_url` without a server, or the most useful server error.
	 */
	public function reverse_lookup() {
		if ( empty( $this->servers ) ) {
			return new WP_Error( 'missing_traccar_geocoder_url', __( 'You did not provide a Traccar Geocoder server.', 'simple-location' ) );
		}
		$errors = array();
		foreach ( $this->servers as $server ) {
			$query = array(
				'lat' => $this->latitude,
				'lon' => $this->longitude,
				'key' => $server['key'],
			);
			if ( $this->distance > 0 ) {
				$query['distance'] = $this->distance;
			}
			$json = $this->fetch_json( $server['url'] . '/reverse', $query );
			if ( is_wp_error( $json ) ) {
				Sloc_Provider_Errors::record( 'geo', $this->for_server( $server ), $json );
				$errors[] = $json;
				continue;
			}
			Sloc_Provider_Errors::resolve( 'geo', $this->for_server( $server ) );
			$address = ( isset( $json['address'] ) && is_array( $json['address'] ) ) ? $json['address'] : array();
			if ( ! $this->is_usable( $address, $json, $server['url'] ) ) {
				continue;
			}
			return $this->address_to_mf( $json );
		}
		return $this->no_server_answered( $errors );
	}

	/**
	 * Returns whether a server's answer locates the place: whether it has a street or a city.
	 *
	 * @since 6.5.0
	 *
	 * @param array  $address Address from the answer.
	 * @param array  $json    Answer.
	 * @param string $url     Server URL.
	 * @return bool Whether to use it.
	 */
	protected function is_usable( $address, $json, $url ) {
		$usable = ! empty( $address['road'] ) || ! empty( $address['city'] );

		/**
		 * Filters whether a Traccar Geocoder server's answer is used, or the next server asked.
		 *
		 * By default an answer is used when it has a street or a city. An answer with only a country or region means
		 * the location is outside the regions the server has.
		 *
		 * @since 6.5.0
		 *
		 * @param bool   $usable    Whether to use it.
		 * @param array  $address   Address from the answer: house_number, road, suburb, city, county, state,
		 *                          postcode, country and country_code, each only when known.
		 * @param array  $json      Answer.
		 * @param string $url       Server URL.
		 * @param float  $latitude  Latitude looked up.
		 * @param float  $longitude Longitude looked up.
		 */
		return (bool) apply_filters( 'sloc_traccar_geocoder_usable', $usable, $address, $json, $url, $this->latitude, $this->longitude );
	}

	/**
	 * Returns the error for a lookup no server answered.
	 *
	 * Without errors, no server had the place. Otherwise the message is that of the most useful error: one an
	 * administrator can fix, or else the last one. It is returned with its own code, so the chain doesn't record it
	 * again for the provider as a whole; each server's errors are already recorded.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Error[] $errors Errors from the servers.
	 * @return WP_Error Error: `no_results`, or `traccar_geocoder_failed` with the servers' errors in its data.
	 */
	protected function no_server_answered( $errors ) {
		if ( empty( $errors ) ) {
			return new WP_Error( 'no_results', __( 'No Results Found', 'simple-location' ), array( 'status' => 404 ) );
		}
		$main = end( $errors );
		foreach ( $errors as $error ) {
			if ( Sloc_Provider_Errors::is_actionable( $error, $this ) ) {
				$main = $error;
				break;
			}
		}
		return new WP_Error(
			'traccar_geocoder_failed',
			$main->get_error_message(),
			array( 'errors' => $errors )
		);
	}

	/**
	 * Geocode address.
	 *
	 * Traccar Geocoder only looks up coordinates, so this returns an error, and the geocoding chain asks its next
	 * provider.
	 *
	 * @since 6.5.0
	 *
	 * @param string $address String representation of location.
	 * @return WP_Error The `not_supported` error.
	 */
	public function geocode( $address ) {
		return new WP_Error( 'not_supported', __( 'Traccar Geocoder can\'t look up an address.', 'simple-location' ), array( 'status' => 400 ) );
	}

	/**
	 * Converts an answer to microformats2 address properties.
	 *
	 * @since 6.5.0
	 *
	 * @param array $json Answer, with 'address'.
	 * @return array Microformats2 address elements.
	 */
	protected function address_to_mf( $json ) {
		$address      = $json['address'];
		$country_code = strtoupper( (string) ( $address['country_code'] ?? '' ) );
		$number       = (string) ( $address['house_number'] ?? '' );
		$street       = (string) ( $address['road'] ?? '' );
		$street_addr  = self::house_number( $country_code ) ? $number . ' ' . $street : $street . ' ' . $number;
		$region       = self::ifnot( $address, array( 'state', 'county' ) );

		$addr                 = array(
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => trim( $street_addr ),
			'extended-address' => $address['suburb'] ?? null,
			'locality'         => $address['city'] ?? null,
			'region'           => $region,
			'region-code'      => empty( $region ) ? null : self::region_code( $region, $country_code ),
			'country-name'     => $address['country'] ?? self::country_name( $country_code ),
			'postal-code'      => $address['postcode'] ?? null,
			'country-code'     => $country_code,
			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'altitude'         => $this->altitude,
			'raw'              => $json,
		);
		$addr                 = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}
}
