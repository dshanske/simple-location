<?php
/**
 * Base Venue Provider Class.
 *
 * @package Simple_Location
 */

/**
 * Retrieves Location Information.
 *
 * @since 1.0.0
 */
abstract class Venue_Provider extends Sloc_Provider {
	use Geolocation_Trait;

	/**
	 * Timezone.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $timezone;

	/**
	 * Offset.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $offset;

	/**
	 * Offset in Seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $offset_seconds;

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
		$defaults   = array(
			'api'       => null,
			'latitude'  => null,
			'longitude' => null,
			'altitude'  => null,
			'user'      => '',
		);
		$defaults   = apply_filters( 'sloc_venue_provider_defaults', $defaults );
		$r          = wp_parse_args( $args, $defaults );
		$this->user = $r['user'];
		$this->api  = $r['api'];
		$this->set( $r['latitude'], $r['longitude'] );

		if ( $this->is_active() && method_exists( $this, 'admin_init' ) ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'init', array( $this, 'init' ) );
		}
	}

	/**
	 * Is Provider Active
	 */
	public function is_active() {
		$option = get_option( 'sloc_venue_provider' );
		return ( $this->slug === $option );
	}

	/**
	 * Returns elevation from provider.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	public function elevation() {
		$provider = Loc_Config::elevation_provider();
		if ( ! $provider ) {
			return 0;
		}

		$provider->set( $this->latitude, $this->longitude );
		return $provider->elevation();
	}

	/**
	 * Returns a list of one or more possible venues in h-card jf2 format.
	 *
	 * @return array Array of jf2 h-card elements under property items.
	 */
	abstract public function reverse_lookup();
}
