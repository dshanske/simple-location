<?php
/**
 * Base Elevation Provider Class.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieves Elevation information and uses it when no altitude info is provided.
 *
 * @since 4.5.0
 */
abstract class Elevation_Provider extends Sloc_Provider {

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
	 *  @type string $user User name.
	 */
	public function __construct( $args = array() ) {
		$defaults = array(
			'api'       => null,
			'latitude'  => null,
			'longitude' => null,
			'altitude'  => null,
			'user'      => null,

		);
		/**
		 * Filters the default arguments of elevation providers.
		 *
		 * @since 5.0.0
		 *
		 * @param array $defaults Default arguments: api, latitude, longitude, altitude and user.
		 */
		$defaults   = apply_filters( 'sloc_elevation_provider_defaults', $defaults );
		$r          = wp_parse_args( $args, $defaults );
		$this->user = $r['user'];
		$this->api  = $r['api'];
		$this->set( $r['latitude'], $r['longitude'] );

		if ( $this->is_active() && method_exists( $this, 'admin_init' ) ) {
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
			add_action( 'init', array( get_called_class(), 'init' ) );
		}
	}

	/**
	 * Is Provider Active
	 *
	 * @since 5.0.0
	 * @since 6.1.0 Active anywhere in the elevation chain.
	 */
	public function is_active() {
		return in_array( $this->slug, Sloc_Provider_Chain::configured( 'elevation' ), true );
	}

	/**
	 * Returns elevation.
	 *
	 * @return float|WP_Error $elevation Elevation in meters, rounded to two places, or an error when it can't be looked up.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Bundled providers return an error, never null or 0, for missing credentials and empty results.
	 */
	abstract public function elevation();
}
