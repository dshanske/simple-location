<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venues from Stadia Maps' geocoding service, a Pelias server, https://docs.stadiamaps.com/geocoding-search-autocomplete/reverse-search/.
 *
 * Its places include OpenStreetMap's and Foursquare's open places. Stadia allows keeping results only on its
 * Standard plan or above, so only references are kept.
 *
 * @since 6.1.0
 */
class Venue_Provider_Stadia extends Venue_Provider_Pelias {
	use Sloc_API_Stadia;

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Venue_Provider::__construct(). The key defaults to the sloc_stadia_api setting.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Stadia Maps', 'simple-location' );
		$this->slug        = 'stadia';
		$this->url         = 'https://stadiamaps.com/';
		$this->description = __( 'Finds places near you with Stadia Maps, using your Stadia Maps API key.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_stadia_api' );
		}
		// Not the Pelias constructor, which names the provider Pelias and uses the Pelias key.
		Venue_Provider::__construct( $args );
	}

	/**
	 * Returns what a site may keep of this provider's places: references, as keeping results needs a Standard plan
	 * or above.
	 *
	 * @since 6.1.0
	 *
	 * @return string Venue_Provider::STORAGE_REFERENCE.
	 */
	public function storage() {
		return self::STORAGE_REFERENCE;
	}

	/**
	 * Returns the reverse search URL.
	 *
	 * @since 6.1.0
	 *
	 * @return string|WP_Error URL, or an error without a key.
	 */
	protected function endpoint() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Stadia Maps', 'simple-location' ) );
		}
		return 'https://api.stadiamaps.com/geocoding/v1/reverse';
	}
}
