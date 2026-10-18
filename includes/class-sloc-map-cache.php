<?php
/**
 * Static map images served from the site.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores static map images on the site and serves them, for map providers whose terms allow it.
 *
 * Map image URLs point to this site instead of the provider, so the provider's API key never reaches the browser
 * and visitors never contact the provider. Each URL carries the map's parameters and a signature made with a
 * secret only the site knows, so the endpoint only fetches maps the site itself published. The first request
 * for a map fetches the image from the provider and stores it in the uploads directory; after that, pages link
 * to the stored file.
 *
 * @since 6.0.0
 */
class Sloc_Map_Cache {

	/**
	 * REST namespace.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const REST_NAMESPACE = 'sloc_geo/1.0';

	/**
	 * REST route that serves map images.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const ROUTE = '/map-image';

	/**
	 * Directory under uploads that holds the images.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const DIRECTORY = 'sloc-maps';

	/**
	 * Largest image accepted from a provider, in bytes.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	const MAX_SIZE = 5242880;

	/**
	 * Map parameters a signed URL can carry, which are the parameters that are signed.
	 *
	 * @since 6.0.0
	 * @var string[]
	 */
	const PARAMETERS = array( 'provider', 'lat', 'lng', 'points', 'width', 'height', 'zoom', 'style', 'scale' );

	/**
	 * Image types accepted from providers, with the file extension each is stored with.
	 *
	 * @since 6.0.0
	 * @var string[]
	 */
	const TYPES = array(
		'image/png'  => 'png',
		'image/jpeg' => 'jpg',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
	);

	/**
	 * Registers the map image route.
	 *
	 * The route is public, because visitors' browsers load the images. The permission check accepts only URLs
	 * with a valid signature.
	 *
	 * @since 6.0.0
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'serve' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
				),
			)
		);
	}

	/**
	 * Returns the URL a page should use for a map image.
	 *
	 * @since 6.0.0
	 *
	 * @param Map_Provider $map       Map provider, set to the map's location, size and pixel density.
	 * @param array|null   $locations Optional. List of array( latitude, longitude ) points for an archive map.
	 * @return string URL of the stored image, or a signed URL of the map image route when it isn't stored yet.
	 */
	public static function get_url( $map, $locations = null ) {
		$args = $map->get_cache_args( $locations );
		$sig  = self::sign( $args );
		$file = self::find( $sig );
		if ( $file ) {
			return $file['url'];
		}
		$args['sig'] = $sig;
		return add_query_arg( array_map( 'rawurlencode', $args ), rest_url( self::REST_NAMESPACE . self::ROUTE ) );
	}

	/**
	 * Signs map parameters with a secret only the site knows.
	 *
	 * @since 6.0.0
	 *
	 * @param array $args Map parameters, as strings.
	 * @return string Signature, as 32 hexadecimal characters.
	 */
	public static function sign( $args ) {
		$args = array_intersect_key( $args, array_flip( self::PARAMETERS ) );
		ksort( $args );
		return substr( hash_hmac( 'sha256', http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ), wp_salt( 'sloc_map' ) ), 0, 32 );
	}

	/**
	 * Returns the signed map parameters from a request.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string[] Map parameters present in the request.
	 */
	protected static function request_args( $request ) {
		$args = array();
		foreach ( self::PARAMETERS as $name ) {
			$value = $request->get_param( $name );
			if ( is_scalar( $value ) ) {
				$args[ $name ] = (string) $value;
			}
		}
		return $args;
	}

	/**
	 * Allows only requests whose signature matches their map parameters.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error True, or an error for a missing or wrong signature.
	 */
	public static function permission_check( $request ) {
		$sig = $request->get_param( 'sig' );
		if ( is_string( $sig ) && hash_equals( self::sign( self::request_args( $request ) ), $sig ) ) {
			return true;
		}
		return new WP_Error( 'rest_forbidden', __( 'This map image link is not valid.', 'simple-location' ), array( 'status' => 403 ) );
	}

	/**
	 * Serves a map image: redirects to the stored file, fetching and storing it first if needed.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Request with signed map parameters.
	 * @return WP_REST_Response|WP_Error Redirect to the image, or an error.
	 */
	public static function serve( $request ) {
		$args = self::request_args( $request );
		$sig  = self::sign( $args );
		$file = self::find( $sig );
		if ( ! $file ) {
			$file = self::fetch( $args, $sig );
			if ( is_wp_error( $file ) ) {
				return $file;
			}
		}
		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', $file['url'] );
		$response->header( 'Cache-Control', 'public, max-age=86400' );
		return $response;
	}

	/**
	 * Creates the map provider a signed URL describes.
	 *
	 * @since 6.0.0
	 *
	 * @param array $args Map parameters.
	 * @return Map_Provider|null Map provider, or null if the provider doesn't exist or doesn't allow its images to be stored.
	 */
	protected static function provider( $args ) {
		if ( empty( $args['provider'] ) ) {
			return null;
		}
		$provider = Loc_Config::map_provider( $args['provider'] );
		if ( ! $provider instanceof Map_Provider || ! $provider->is_cacheable() ) {
			return null;
		}
		$class = get_class( $provider );
		$map   = new $class(
			array(
				'latitude'  => $args['lat'] ?? null,
				'longitude' => $args['lng'] ?? null,
				'width'     => (int) ( $args['width'] ?? 0 ),
				'height'    => (int) ( $args['height'] ?? 0 ),
				'map_zoom'  => (int) ( $args['zoom'] ?? 0 ),
				'style'     => $args['style'] ?? '',
			)
		);
		$map->set_scale( (int) ( $args['scale'] ?? 1 ) );
		return $map;
	}

	/**
	 * Fetches a map image from its provider and stores it.
	 *
	 * @since 6.0.0
	 *
	 * @param array  $args Map parameters.
	 * @param string $sig  Signature of the parameters, used as the file name.
	 * @return array|WP_Error Stored file, with 'path' and 'url', or an error.
	 */
	protected static function fetch( $args, $sig ) {
		$map = self::provider( $args );
		if ( ! $map ) {
			return new WP_Error( 'sloc_map_not_found', __( 'This map is not available.', 'simple-location' ), array( 'status' => 404 ) );
		}
		if ( isset( $args['points'] ) ) {
			$url = $map->get_archive_map( array_chunk( Sloc_Polyline::decode( $args['points'] ), 2 ) );
		} else {
			$url = $map->get_the_static_map();
		}
		if ( empty( $url ) || is_wp_error( $url ) ) {
			return new WP_Error( 'sloc_map_not_found', __( 'This map is not available.', 'simple-location' ), array( 'status' => 404 ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'limit_response_size' => self::MAX_SIZE,
			)
		);
		$body     = wp_remote_retrieve_body( $response );
		$info     = ( '' === $body || strlen( $body ) >= self::MAX_SIZE ) ? false : getimagesizefromstring( $body );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! $info || ! isset( self::TYPES[ $info['mime'] ] ) ) {
			return new WP_Error( 'sloc_map_fetch_failed', __( 'The map could not be loaded.', 'simple-location' ), array( 'status' => 502 ) );
		}
		return self::store( $sig, self::TYPES[ $info['mime'] ], $body );
	}

	/**
	 * Returns the directory that holds the images.
	 *
	 * @since 6.0.0
	 *
	 * @return array|false With 'path' and 'url', or false if the uploads directory isn't available.
	 */
	protected static function directory() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}
		return array(
			'path' => trailingslashit( $uploads['basedir'] ) . self::DIRECTORY,
			'url'  => trailingslashit( $uploads['baseurl'] ) . self::DIRECTORY,
		);
	}

	/**
	 * Finds a stored image.
	 *
	 * @since 6.0.0
	 *
	 * @param string $sig Signature of the map parameters.
	 * @return array|false Stored file, with 'path' and 'url', or false if it isn't stored.
	 */
	protected static function find( $sig ) {
		$directory = self::directory();
		if ( ! $directory ) {
			return false;
		}
		foreach ( self::TYPES as $extension ) {
			$name = $sig . '.' . $extension;
			if ( file_exists( $directory['path'] . '/' . $name ) ) {
				return array(
					'path' => $directory['path'] . '/' . $name,
					'url'  => $directory['url'] . '/' . $name,
				);
			}
		}
		return false;
	}

	/**
	 * Stores an image.
	 *
	 * The directory gets an empty index file, so a server that lists directories doesn't list the maps.
	 *
	 * @since 6.0.0
	 *
	 * @param string $sig       Signature of the map parameters, used as the file name.
	 * @param string $extension File extension.
	 * @param string $body      Image data.
	 * @return array|WP_Error Stored file, with 'path' and 'url', or an error.
	 */
	protected static function store( $sig, $extension, $body ) {
		$directory = self::directory();
		$error     = new WP_Error( 'sloc_map_store_failed', __( 'The map could not be stored.', 'simple-location' ), array( 'status' => 500 ) );
		if ( ! $directory || ! wp_mkdir_p( $directory['path'] ) ) {
			return $error;
		}
		$filesystem = self::filesystem();
		$mode       = fileperms( ABSPATH . 'index.php' ) & 0777 | 0644;
		if ( ! $filesystem->exists( $directory['path'] . '/index.html' ) ) {
			$filesystem->put_contents( $directory['path'] . '/index.html', '', $mode );
		}
		$name = $sig . '.' . $extension;
		if ( ! $filesystem->put_contents( $directory['path'] . '/' . $name, $body, $mode ) ) {
			return $error;
		}
		return array(
			'path' => $directory['path'] . '/' . $name,
			'url'  => $directory['url'] . '/' . $name,
		);
	}

	/**
	 * Returns a filesystem object that writes files directly.
	 *
	 * Map images are written during front-end requests, where WP_Filesystem() can't ask for credentials.
	 *
	 * @since 6.0.0
	 *
	 * @return WP_Filesystem_Direct Filesystem.
	 */
	protected static function filesystem() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		return new WP_Filesystem_Direct( null );
	}
}

add_action( 'rest_api_init', array( 'Sloc_Map_Cache', 'register_routes' ) );
