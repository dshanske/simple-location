<?php
/**
 * Elevation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elevation using Open Topo Data, from the public API or a self-hosted server.
 *
 * @since 6.1.0
 */
class Elevation_Provider_OpenTopoData extends Elevation_Provider {

	/**
	 * The public Open Topo Data API, used without a server URL setting.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const PUBLIC_URL = 'https://api.opentopodata.org';

	/**
	 * Dataset used without a dataset setting. It covers the whole world, including the sea floor.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_DATASET = 'mapzen';

	/**
	 * Server URL, without a trailing slash.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $server = self::PUBLIC_URL;

	/**
	 * Dataset name, or several separated by commas, tried in order until one has data for the point.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $dataset = self::DEFAULT_DATASET;

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args {
	 *     Arguments. See Elevation_Provider::__construct() for the others.
	 *
	 *     @type string $server  Server URL. Default the sloc_opentopodata_url setting, or the public API.
	 *     @type string $dataset Dataset. Default the sloc_opentopodata_dataset setting, or DEFAULT_DATASET.
	 * }
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Open Topo Data', 'simple-location' );
		$this->slug        = 'opentopodata';
		$this->url         = 'https://www.opentopodata.org/';
		$this->description = __( 'Open Topo Data is an open source elevation API. Its public API needs no key, and allows 1,000 requests a day. You can also run your own server with your own elevation data.', 'simple-location' );

		$server = self::sanitize_url( $args['server'] ?? get_option( 'sloc_opentopodata_url' ) );
		if ( '' !== $server ) {
			$this->server = untrailingslashit( $server );
		}
		$dataset = self::sanitize_dataset( $args['dataset'] ?? get_option( 'sloc_opentopodata_dataset' ) );
		if ( '' !== $dataset ) {
			$this->dataset = $dataset;
		}

		parent::__construct( $args );
	}

	/**
	 * Sanitizes the server URL setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Server URL.
	 * @return string HTTP or HTTPS URL, or an empty string for the public API.
	 */
	public static function sanitize_url( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return (string) esc_url_raw( trim( $value ), array( 'http', 'https' ) );
	}

	/**
	 * Sanitizes the dataset setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Dataset name, or several separated by commas.
	 * @return string Dataset names separated by commas, or an empty string for the default dataset.
	 */
	public static function sanitize_dataset( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$datasets = array_filter( array_map( 'trim', explode( ',', strtolower( $value ) ) ) );
		$datasets = array_filter(
			$datasets,
			function ( $dataset ) {
				return (bool) preg_match( '/^[a-z0-9_-]+$/', $dataset );
			}
		);
		return implode( ',', $datasets );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		register_setting(
			'sloc_api',
			'sloc_opentopodata_url',
			array(
				'type'              => 'string',
				'description'       => 'Open Topo Data Server URL',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
			)
		);
		register_setting(
			'sloc_api',
			'sloc_opentopodata_dataset',
			array(
				'type'              => 'string',
				'description'       => 'Open Topo Data Dataset',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_dataset' ),
			)
		);
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function admin_init() {
		add_settings_field(
			'sloc_opentopodata_url',
			__( 'Open Topo Data Server URL', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => 'sloc_opentopodata_url',
				'type'        => 'url',
				'description' => __( 'Optional. The address of your own Open Topo Data server. Leave empty to use the public API.', 'simple-location' ),
			)
		);
		add_settings_field(
			'sloc_opentopodata_dataset',
			__( 'Open Topo Data Dataset', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => 'sloc_opentopodata_dataset',
				'type'        => 'text',
				'description' => sprintf(
					/* translators: %s: Default dataset name. */
					__( 'Optional. Default %s, which covers the whole world. The public API also has srtm30m, aster30m, ned10m (USA), eudem25m (Europe) and others. Separate several with commas to try each in turn until one has data for the location.', 'simple-location' ),
					self::DEFAULT_DATASET
				),
			)
		);
	}

	/**
	 * Returns elevation.
	 *
	 * @since 6.1.0
	 *
	 * @return float|WP_Error Elevation in meters, rounded to two places, or an error, including when the datasets have no
	 *                        data for the location.
	 */
	public function elevation() {
		$url  = sprintf( '%1$s/v1/%2$s', $this->server, $this->dataset );
		$json = $this->fetch_json(
			$url,
			array(
				'locations' => $this->latitude . ',' . $this->longitude,
			)
		);
		if ( is_wp_error( $json ) ) {
			// Open Topo Data explains a refused request in the response's error field.
			$data = $json->get_error_data();
			$body = is_array( $data ) ? json_decode( $data['body'] ?? '', true ) : null;
			if ( is_array( $body ) && ! empty( $body['error'] ) && is_string( $body['error'] ) ) {
				return new WP_Error( 'opentopodata_error', $body['error'], array( 'status' => $data['status'] ?? 0 ) );
			}
			return $json;
		}
		if ( ( $json['status'] ?? '' ) !== 'OK' ) {
			$message = ( ! empty( $json['error'] ) && is_string( $json['error'] ) ) ? $json['error'] : __( 'No elevation found', 'simple-location' );
			return new WP_Error( 'opentopodata_error', $message );
		}
		$elevation = $json['results'][0]['elevation'] ?? null;
		if ( ! is_numeric( $elevation ) ) {
			// Outside the datasets, Open Topo Data returns null, which isn't sea level.
			return new WP_Error( 'no_elevation_data', __( 'The elevation dataset has no data for this location', 'simple-location' ), array( 'status' => 404 ) );
		}
		return round( (float) $elevation, 2 );
	}
}
