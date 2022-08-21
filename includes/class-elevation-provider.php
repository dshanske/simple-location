<?php
/**
 * Base Elevation Provider Class.
 *
 * @package Simple_Location
 */

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
	 */
	public function is_active() {
		$option = get_option( 'sloc_elevation_provider' );
		return ( $this->slug === $option );
	}

	/**
	 * Returns elevation.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	abstract public function elevation();
}
