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
 * secret only the site knows, so the endpoint only fetches maps the site itself published.
 *
 * The first request for a map fetches the image from the provider and stores it in uploads/sloc-maps. Pages then
 * link to the stored file until it is older than the refresh age (30 days by default, never with 0). After that,
 * pages link to the endpoint again, which fetches the image and overwrites the file in place, so pages cached
 * with the old link keep working. If a refresh fails, the stored image is served until the next attempt. Stored
 * images are never deleted automatically; the Clear stored maps button on the settings page deletes them all.
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
	 * Default number of days a stored image is used before it is fetched again.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	const DEFAULT_MAX_AGE = 30;

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
	 * @since 6.1.0 Added 'options', a hash of the provider's other settings that change the image.
	 * @since 6.1.0 Added 'track', the markers of a track map.
	 * @var string[]
	 */
	const PARAMETERS = array( 'provider', 'lat', 'lng', 'points', 'width', 'height', 'zoom', 'style', 'scale', 'options', 'track' );

	/**
	 * Prefix of the transients that hold the provider's URL of a map image the site doesn't store.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const PASSTHROUGH = 'sloc_map_pass_';

	/**
	 * Longest provider URL, in bytes, visitors are redirected to. Longer ones may be refused, so the image is sent instead.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_REDIRECT_LENGTH = 6000;

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
	 * Registers the settings.
	 *
	 * @since 6.0.0
	 * @since 6.1.2 The setting is sanitized.
	 */
	public static function init() {
		register_setting(
			'simloc',
			'sloc_map_cache',
			array(
				'type'              => 'boolean',
				'description'       => 'Serve map images from this site, for map providers whose terms allow it',
				'show_in_rest'      => false,
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		register_setting(
			'simloc',
			'sloc_map_cache_days',
			array(
				'type'              => 'integer',
				'description'       => 'Days a stored map image is used before it is fetched again. 0 never fetches it again.',
				'show_in_rest'      => false,
				'default'           => self::DEFAULT_MAX_AGE,
				'sanitize_callback' => array( __CLASS__, 'sanitize_days' ),
			)
		);
	}

	/**
	 * Sanitizes the refresh age.
	 *
	 * @since 6.0.0
	 *
	 * @param mixed $value Submitted value.
	 * @return int Days, at least 0.
	 */
	public static function sanitize_days( $value ) {
		return is_numeric( $value ) ? max( 0, (int) $value ) : self::DEFAULT_MAX_AGE;
	}

	/**
	 * Adds the settings fields to the map settings, when the map provider's terms allow storing its images.
	 *
	 * Runs after Loc_Config::admin_init(), which adds the map settings section.
	 *
	 * @since 6.0.0
	 */
	public static function admin_init() {
		$map = Loc_Config::map_provider();
		if ( ! $map instanceof Map_Provider || ! $map->is_cacheable() ) {
			return;
		}
		add_settings_field(
			'sloc_map_cache',
			__( 'Serve Maps From This Site', 'simple-location' ),
			array( __CLASS__, 'enabled_field' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_map_cache',
				'provider'  => $map,
			)
		);
		add_settings_field(
			'sloc_map_cache_days',
			__( 'Refresh Stored Maps After (Days)', 'simple-location' ),
			array( __CLASS__, 'max_age_field' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_map_cache_days',
				'provider'  => $map,
			)
		);
	}

	/**
	 * Echos the field that turns serving maps from the site on or off.
	 *
	 * @since 6.0.0
	 *
	 * @param array $args {
	 *  Field arguments.
	 *  @type string       $label_for Option name.
	 *  @type Map_Provider $provider  Current map provider.
	 * }
	 */
	public static function enabled_field( $args ) {
		Loc_Config::checkbox_callback( $args );
		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					// translators: Name of the map provider.
					__( '%s allows its map images to be stored, so this site can fetch them and serve them itself. Your API key stays on the server, and visitors never contact the provider. When off, map images load from the provider and the API key is part of the image link.', 'simple-location' ),
					$args['provider']->get_name()
				)
			)
		);
	}

	/**
	 * Echos the refresh age field and the Clear stored maps button.
	 *
	 * @since 6.0.0
	 *
	 * @param array $args {
	 *  Field arguments.
	 *  @type string       $label_for Option name.
	 *  @type Map_Provider $provider  Current map provider.
	 * }
	 */
	public static function max_age_field( $args ) {
		Loc_Config::number_callback( $args );
		$description = __( 'A stored map is fetched again from the provider after this many days. 0 never fetches it again. Pages cached with the old image keep working.', 'simple-location' );
		$limit       = $args['provider']->get_max_cache_age();
		if ( $limit ) {
			$description .= ' ' . sprintf(
				// translators: 1. Name of the map provider 2. Number of days.
				_n( '%1$s allows storing a map for at most %2$d day, so its maps are refreshed at least that often.', '%1$s allows storing a map for at most %2$d days, so its maps are refreshed at least that often.', $limit, 'simple-location' ),
				$args['provider']->get_name(),
				$limit
			);
		}
		printf( '<p class="description">%s</p>', esc_html( $description ) );

		$count = count( self::stored_files() );
		printf(
			'<p><a class="button" href="%1$s">%2$s</a> %3$s</p>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sloc_clear_map_cache' ), 'sloc_clear_map_cache' ) ),
			esc_html__( 'Clear Stored Maps', 'simple-location' ),
			esc_html(
				sprintf(
					// translators: Number of stored map images.
					_n( '%d map image is stored.', '%d map images are stored.', $count, 'simple-location' ),
					$count
				)
			)
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Clearing deletes every stored map image. Pages show their maps again once they are rebuilt, but pages kept by a page cache may show broken images until their cache expires.', 'simple-location' )
		);
	}

	/**
	 * Deletes every stored map image, from the Clear stored maps button, and returns to the settings page.
	 *
	 * @since 6.0.0
	 */
	public static function handle_clear() {
		check_admin_referer( 'sloc_clear_map_cache' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to clear stored maps.', 'simple-location' ), '', array( 'response' => 403 ) );
		}
		$count    = self::clear();
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=simloc' );
		}
		wp_safe_redirect( add_query_arg( 'sloc_maps_cleared', $count, $redirect ) );
		exit;
	}

	/**
	 * Shows how many map images were cleared, after the Clear stored maps button.
	 *
	 * @since 6.0.0
	 */
	public static function cleared_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a count; the clearing itself checked a nonce.
		if ( ! isset( $_GET['sloc_maps_cleared'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
		$count = absint( $_GET['sloc_maps_cleared'] );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					// translators: Number of deleted map images.
					_n( 'Deleted %d stored map image.', 'Deleted %d stored map images.', $count, 'simple-location' ),
					$count
				)
			)
		);
	}

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
	 * Whether a map provider's images are served from the site.
	 *
	 * @since 6.0.0
	 *
	 * @param Map_Provider $map Map provider.
	 * @return bool True if the provider's terms allow it and the setting is on.
	 */
	public static function is_enabled( $map ) {
		return $map->is_cacheable() && (bool) get_option( 'sloc_map_cache', true );
	}

	/**
	 * Returns how many days a provider's stored images are used before they are fetched again.
	 *
	 * @since 6.0.0
	 *
	 * @param Map_Provider $map Map provider.
	 * @return int Days, or 0 to never fetch them again.
	 */
	public static function get_max_age( $map ) {
		$days = self::sanitize_days( get_option( 'sloc_map_cache_days', self::DEFAULT_MAX_AGE ) );

		/**
		 * Filters how many days a stored map image is used before it is fetched again from the provider.
		 *
		 * The provider's own limit, from its terms, still applies to the filtered value.
		 *
		 * @since 6.0.0
		 *
		 * @param int          $days Days, from the Refresh Stored Maps After setting. 0 never fetches the image again.
		 * @param Map_Provider $map  Map provider.
		 */
		$days  = self::sanitize_days( apply_filters( 'sloc_map_cache_max_age', $days, $map ) );
		$limit = max( 0, (int) $map->get_max_cache_age() );
		if ( $limit && ( 0 === $days || $days > $limit ) ) {
			$days = $limit;
		}
		return $days;
	}

	/**
	 * Whether a stored image is recent enough to use.
	 *
	 * @since 6.0.0
	 *
	 * @param array        $file Stored file, with 'path'.
	 * @param Map_Provider $map  Map provider.
	 * @return bool
	 */
	protected static function is_fresh( $file, $map ) {
		$days = self::get_max_age( $map );
		return 0 === $days || $file['modified'] > time() - $days * DAY_IN_SECONDS;
	}

	/**
	 * Returns the URL a page should use for a map image.
	 *
	 * @since 6.0.0
	 *
	 * @param Map_Provider $map       Map provider, set to the map's location, size and pixel density.
	 * @param array|null   $locations Optional. List of array( latitude, longitude ) points for an archive map.
	 * @return string URL of the stored image; the provider's URL while the provider's image can't be stored; or a signed URL
	 *                of the map image route if it isn't stored or is due for a refresh.
	 */
	public static function get_url( $map, $locations = null ) {
		$args    = $map->get_cache_args( $locations );
		$sig     = self::sign( $args );
		$passing = get_transient( self::PASSTHROUGH . $sig );
		if ( is_string( $passing ) && '' !== $passing ) {
			return $passing;
		}
		$file = self::find( $sig );
		if ( $file && self::is_fresh( $file, $map ) ) {
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
	 * Serves a map image: redirects to the stored file, fetching it first if it isn't stored or is due for a refresh.
	 *
	 * If a refresh fails, the stored image is served, and the next request tries again.
	 *
	 * @since 6.0.0
	 * @since 6.1.0 An image the provider doesn't allow storing redirects to the provider's URL instead, for as long as the provider
	 *              says, or is sent for this request when that URL is too long.
	 *
	 * @param WP_REST_Request $request Request with signed map parameters.
	 * @return WP_REST_Response|WP_Error Redirect to the image, or an error.
	 */
	public static function serve( $request ) {
		$args = self::request_args( $request );
		$map  = self::provider( $args );
		if ( ! $map ) {
			return new WP_Error( 'sloc_map_not_found', __( 'This map is not available.', 'simple-location' ), array( 'status' => 404 ) );
		}
		$sig     = self::sign( $args );
		$passing = get_transient( self::PASSTHROUGH . $sig );
		if ( is_string( $passing ) && '' !== $passing ) {
			return self::passthrough( $passing );
		}
		$file = self::find( $sig );
		if ( ! $file || ! self::is_fresh( $file, $map ) ) {
			$fetched = self::fetch( $map, $args, $sig );
			if ( is_wp_error( $fetched ) && 'sloc_map_passthrough' === $fetched->get_error_code() ) {
				$data = $fetched->get_error_data();
				if ( strlen( $data['url'] ) > self::MAX_REDIRECT_LENGTH ) {
					return self::send_image( $data['body'], $data['type'] );
				}
				set_transient( self::PASSTHROUGH . $sig, $data['url'], $data['time'] );
				return self::passthrough( $data['url'] );
			}
			if ( ! is_wp_error( $fetched ) ) {
				$file = $fetched;
			} elseif ( ! $file ) {
				return $fetched;
			}
		}
		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', $file['url'] );
		$response->header( 'Cache-Control', 'public, max-age=86400' );
		return $response;
	}

	/**
	 * Redirects to the provider's URL of a map image the site doesn't store.
	 *
	 * @since 6.1.0
	 *
	 * @param string $url Provider's URL of the image.
	 * @return WP_REST_Response Redirect, which shared caches don't keep.
	 */
	protected static function passthrough( $url ) {
		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', $url );
		$response->header( 'Cache-Control', 'private, max-age=300' );
		return $response;
	}

	/**
	 * Sends a map image the site doesn't store, for one visitor.
	 *
	 * @since 6.1.0
	 *
	 * @param string $body Image.
	 * @param string $type Media type of the image.
	 * @return WP_REST_Response Response whose image is sent instead of JSON.
	 */
	protected static function send_image( $body, $type ) {
		$response = new WP_REST_Response( null, 200 );
		$response->header( 'Content-Type', $type );
		$response->header( 'Cache-Control', 'private, max-age=300' );
		add_filter(
			'rest_pre_serve_request',
			function ( $served, $result ) use ( $response, $body ) {
				if ( $result !== $response ) {
					return $served;
				}
				echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- An image, sent as is.
				return true;
			},
			10,
			2
		);
		return $response;
	}

	/**
	 * Creates the map provider a signed URL describes.
	 *
	 * @since 6.0.0
	 * @since 6.1.0 Makes a track map a track again.
	 *
	 * @param array $args Map parameters.
	 * @return Map_Provider|null Map provider, or null if the provider doesn't exist or its terms don't allow storing its images.
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
		if ( isset( $args['track'] ) && is_string( $args['track'] ) ) {
			$map->set_track( $args['track'] );
		}
		return $map;
	}

	/**
	 * Fetches a map image from its provider and stores it.
	 *
	 * @since 6.0.0
	 * @since 6.1.0 The provider fetches the image, and can say not to store it: the error 'sloc_map_passthrough' then has the
	 *              provider's 'url' of the image, the 'time', in seconds, to send visitors there, and the image's 'body' and 'type'.
	 *
	 * @param Map_Provider $map  Map provider, set from the parameters.
	 * @param array        $args Map parameters.
	 * @param string       $sig  Signature of the parameters, used as the file name.
	 * @return array|WP_Error Stored file, or an error.
	 */
	protected static function fetch( $map, $args, $sig ) {
		$locations = isset( $args['points'] ) ? array_chunk( Sloc_Polyline::decode( $args['points'] ), 2 ) : null;
		$response  = $map->fetch_static_map(
			$locations,
			array(
				'timeout'             => 15,
				'limit_response_size' => self::MAX_SIZE,
			)
		);
		if ( is_wp_error( $response ) && 'sloc_map_not_found' === $response->get_error_code() ) {
			return $response;
		}
		$body = wp_remote_retrieve_body( $response );
		$info = ( '' === $body || strlen( $body ) >= self::MAX_SIZE ) ? false : getimagesizefromstring( $body );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! $info || ! isset( self::TYPES[ $info['mime'] ] ) ) {
			return new WP_Error( 'sloc_map_fetch_failed', __( 'The map could not be loaded.', 'simple-location' ), array( 'status' => 502 ) );
		}
		$passthrough = (int) $map->get_passthrough_time( $response );
		$url         = $passthrough > 0 ? $map->get_static_map_url( $locations ) : '';
		if ( '' !== $url ) {
			return new WP_Error(
				'sloc_map_passthrough',
				__( 'The map provider does not allow storing this map.', 'simple-location' ),
				array(
					'url'  => $url,
					'time' => $passthrough,
					'body' => $body,
					'type' => $info['mime'],
				)
			);
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
		$directory = array(
			'path' => trailingslashit( $uploads['basedir'] ) . self::DIRECTORY,
			'url'  => trailingslashit( $uploads['baseurl'] ) . self::DIRECTORY,
		);

		/**
		 * Filters where stored map images are kept.
		 *
		 * @since 6.0.0
		 *
		 * @param array $directory {
		 *     Directory. Default uploads/sloc-maps.
		 *
		 *     @type string $path Absolute path of the directory.
		 *     @type string $url  URL of the directory.
		 * }
		 */
		$directory = apply_filters( 'sloc_map_cache_directory', $directory );
		if ( ! is_array( $directory ) || empty( $directory['path'] ) || empty( $directory['url'] ) || ! is_string( $directory['path'] ) || ! is_string( $directory['url'] ) ) {
			return false;
		}
		return array(
			'path' => untrailingslashit( $directory['path'] ),
			'url'  => untrailingslashit( $directory['url'] ),
		);
	}

	/**
	 * Returns the stored image files.
	 *
	 * @since 6.0.0
	 *
	 * @return string[] File names.
	 */
	protected static function stored_files() {
		$directory = self::directory();
		if ( ! $directory || ! is_dir( $directory['path'] ) ) {
			return array();
		}
		$pattern = '/^[0-9a-f]{32}\.(' . implode( '|', self::TYPES ) . ')$/';
		return array_values( preg_grep( $pattern, (array) scandir( $directory['path'] ) ) );
	}

	/**
	 * Finds a stored image.
	 *
	 * @since 6.0.0
	 *
	 * @param string $sig Signature of the map parameters.
	 * @return array|false Stored file, with 'path', 'url' (with the modification time, so a refreshed image gets a new
	 *                     URL) and 'modified', or false if it isn't stored.
	 */
	protected static function find( $sig ) {
		$directory = self::directory();
		if ( ! $directory ) {
			return false;
		}
		foreach ( self::TYPES as $extension ) {
			$path = $directory['path'] . '/' . $sig . '.' . $extension;
			if ( file_exists( $path ) ) {
				$modified = (int) filemtime( $path );
				return array(
					'path'     => $path,
					'url'      => $directory['url'] . '/' . $sig . '.' . $extension . '?v=' . $modified,
					'modified' => $modified,
				);
			}
		}
		return false;
	}

	/**
	 * Stores an image, replacing any stored image of the same map.
	 *
	 * The directory gets an empty index file, so a server that lists directories doesn't list the maps.
	 *
	 * @since 6.0.0
	 *
	 * @param string $sig       Signature of the map parameters, used as the file name.
	 * @param string $extension File extension.
	 * @param string $body      Image data.
	 * @return array|WP_Error Stored file, or an error.
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
		// A refreshed image may come back in another format.
		foreach ( self::TYPES as $other ) {
			if ( $other !== $extension && $filesystem->exists( $directory['path'] . '/' . $sig . '.' . $other ) ) {
				$filesystem->delete( $directory['path'] . '/' . $sig . '.' . $other );
			}
		}
		if ( ! $filesystem->put_contents( $directory['path'] . '/' . $sig . '.' . $extension, $body, $mode ) ) {
			return $error;
		}
		clearstatcache();
		$file = self::find( $sig );
		return $file ? $file : $error;
	}

	/**
	 * Deletes every stored map image.
	 *
	 * @since 6.0.0
	 *
	 * @return int Number of images deleted.
	 */
	public static function clear() {
		$directory = self::directory();
		$count     = 0;
		if ( ! $directory ) {
			return $count;
		}
		$filesystem = self::filesystem();
		foreach ( self::stored_files() as $name ) {
			if ( $filesystem->delete( $directory['path'] . '/' . $name ) ) {
				++$count;
			}
		}
		return $count;
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

add_action( 'init', array( 'Sloc_Map_Cache', 'init' ) );
add_action( 'admin_init', array( 'Sloc_Map_Cache', 'admin_init' ), 11 );
add_action( 'admin_post_sloc_clear_map_cache', array( 'Sloc_Map_Cache', 'handle_clear' ) );
add_action( 'admin_notices', array( 'Sloc_Map_Cache', 'cleared_notice' ) );
add_action( 'rest_api_init', array( 'Sloc_Map_Cache', 'register_routes' ) );
