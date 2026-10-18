<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using LocationIQ Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_LocationIQ extends Map_Provider {
	use Sloc_API_LocationIQ;


	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the LocationIQ map API key when one is set.
	 *
	 * @param array $args Arguments. See Map_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'LocationIQ', 'simple-location' );
		$this->slug         = 'locationiq';
		$this->url          = 'https://locationiq.com/';
		$this->description  = __( 'LocationIQ offers Geocoding and Static maps, with a free tier of 5000 requests/day. Sign up for an API key', 'simple-location' );
		$this->max_map_zoom = 18;

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = self::get_map_api_key( 'locationiq' );
		}

		parent::__construct( $args );
	}

	/**
	 * Returns the map styles the provider offers.
	 *
	 * @since 4.0.0
	 *
	 * @return array Style names keyed by ID.
	 */
	public function get_styles() {
		return array();
	}

	/**
	 * Returns the URL of a static map centered on the location, with a marker.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Centered at the map zoom setting, with a marker icon from the v3 API.
	 *
	 * @return string|WP_Error URL, or an error without an API key.
	 */
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Location IQ', 'simple-location' ) );
		}
		$map = add_query_arg(
			array(
				'key'     => $this->api,
				'center'  => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'zoom'    => $this->map_zoom,
				'size'    => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'markers' => sprintf( 'icon:large-red-cutout|%1$s,%2$s', $this->latitude, $this->longitude ),
			),
			'https://maps.locationiq.com/v3/staticmap'
		);
		return $map;
	}

	/**
	 * Returns the URL of a static map with a marker at each location and a path joining them.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the v3 API, with one marker parameter per location and a valid path.
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without an API key or locations.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$points = array();
		foreach ( $locations as $location ) {
			$points[] = sprintf( '%1$s,%2$s', $location[0], $location[1] );
		}

		$map = add_query_arg(
			array(
				'key'    => $this->api,
				'format' => 'png',
				'size'   => sprintf( '%1$sx%2$s', $this->width, $this->height ),
			),
			'https://maps.locationiq.com/v3/staticmap'
		);
		// The v3 API takes one markers parameter per marker.
		foreach ( $points as $point ) {
			$map .= '&markers=icon:large-red-cutout|' . $point;
		}
		// A path needs at least two points.
		if ( count( $points ) > 1 ) {
			$map .= '&path=weight:2|color:blue|' . implode( '|', $points );
		}
		return $map;
	}

	/**
	 * Returns a link to the location on OpenStreetMap, which LocationIQ's maps are drawn from.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Links to OpenStreetMap instead of returning an empty string.
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	/**
	 * Returns the HTML for the map, which is always the static map.
	 *
	 * @since 4.0.0
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML marked up map.
	 */
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html( true );
	}
}
