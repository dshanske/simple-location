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
	 * Image formats the provider can be asked for.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array( 'png', 'jpg' );

	/**
	 * Image format the plugin asks for when none is chosen.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_FORMAT = 'png';

	/**
	 * Width, in pixels, of the whole world at zoom 0 on this provider's static maps.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $tile_size = 256;

	/**
	 * Clickable area of an archive map marker, in pixels from the marker's location. Large markers are tall pins.
	 *
	 * @since 6.1.0
	 * @var int[]
	 */
	protected $marker_box = array( -16, -48, 16, 2 );

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
	 * @since 6.1.0 Uses the image format setting.
	 * @since 6.1.0 Uses the marker settings.
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
				'markers' => sprintf( 'icon:%1$s|%2$s,%3$s', $this->get_marker_icon(), $this->latitude, $this->longitude ),
			),
			'https://maps.locationiq.com/v3/staticmap'
		);
		if ( '' !== $this->format ) {
			$map = add_query_arg( 'format', $this->get_vendor_format(), $map );
		}
		return $map;
	}

	/**
	 * Returns the URL of a static map with a marker at each location and a path joining them.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the v3 API, with one marker parameter per location and a valid path.
	 * @since 6.1.0 Centred and zoomed by the plugin, so its markers can be linked to their posts.
	 * @since 6.1.0 Uses the image format setting.
	 * @since 6.1.0 Markers are in the nearest color LocationIQ has to the marker color.
	 * @since 6.1.0 A track has markers only at its ends, or none, and a line through its points.
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

		$map  = add_query_arg(
			array(
				'key'    => $this->api,
				'format' => $this->get_vendor_format(),
				'size'   => sprintf( '%1$sx%2$s', $this->width, $this->height ),
			),
			'https://maps.locationiq.com/v3/staticmap'
		);
		$view = $this->get_archive_view( $locations );
		if ( null !== $view ) {
			$map = add_query_arg(
				array(
					'center' => sprintf( '%1$s,%2$s', $view['latitude'], $view['longitude'] ),
					'zoom'   => $view['zoom'],
				),
				$map
			);
		}
		// The v3 API takes one markers parameter per marker.
		foreach ( $this->get_marker_locations( $locations ) as $location ) {
			$map .= '&markers=icon:large-' . $this->get_marker_palette_color() . '-cutout|' . sprintf( '%1$s,%2$s', $location[0], $location[1] );
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

	/**
	 * Returns the name of LocationIQ's icon for a single location's marker.
	 *
	 * LocationIQ's icons are pins in tiny, small and large sizes; the tiny pin stands in for a dot.
	 *
	 * @since 6.1.0
	 *
	 * @return string Icon name, such as `large-red-cutout`.
	 */
	protected function get_marker_icon() {
		$sizes = array(
			'small'  => 'small',
			'medium' => 'large',
			'large'  => 'large',
		);
		$size  = 'dot' === $this->marker ? 'tiny' : $sizes[ $this->marker_size ];
		return $size . '-' . $this->get_marker_palette_color() . '-cutout';
	}

	/**
	 * Returns the color of LocationIQ's marker icons closest to the marker color.
	 *
	 * @since 6.1.0
	 *
	 * @return string Color name.
	 */
	public function get_marker_palette_color() {
		$palette = array(
			'red'    => array( 211, 47, 47 ),
			'orange' => array( 245, 124, 0 ),
			'yellow' => array( 251, 192, 45 ),
			'green'  => array( 56, 142, 60 ),
			'blue'   => array( 25, 118, 210 ),
			'purple' => array( 123, 31, 162 ),
			'gray'   => array( 117, 117, 117 ),
			'black'  => array( 33, 33, 33 ),
		);
		$color   = array_map( 'hexdec', str_split( $this->get_marker_hex(), 2 ) );
		$nearest = 'red';
		$best    = PHP_INT_MAX;
		foreach ( $palette as $name => $rgb ) {
			$distance = ( $color[0] - $rgb[0] ) ** 2 + ( $color[1] - $rgb[1] ) ** 2 + ( $color[2] - $rgb[2] ) ** 2;
			if ( $distance < $best ) {
				$best    = $distance;
				$nearest = $name;
			}
		}
		return $nearest;
	}
}
