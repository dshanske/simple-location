<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using the HERE Map Image API v3.
 *
 * @since 1.0.0
 * @since 6.0.0 Uses Map Image API v3. Map scheme types were replaced by styles, so the type setting is gone.
 */
class Map_Provider_Here extends Map_Provider {
	use Sloc_API_Here;

	/**
	 * Image formats the provider can be asked for.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array( 'png', 'png8', 'jpg' );

	/**
	 * Image format the plugin asks for when none is chosen.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_FORMAT = 'png8';

	/**
	 * Base URL of the Map Image API v3.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const BASE_URL = 'https://image.maps.hereapi.com/mia/v3/base/mc/';

	/**
	 * URL of the Map Image API's copyright list.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const COPYRIGHT_URL = 'https://image.maps.hereapi.com/mia/v3/copyright';

	/**
	 * Width, in pixels, of the whole world at zoom 0 on this provider's static maps.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $tile_size = 256;

	/**
	 * Clickable area of an archive map marker, in pixels from the marker's location. Points are circles centred on the location.
	 *
	 * @since 6.1.0
	 * @var int[]
	 */
	protected $marker_box = array( -10, -10, 10, 10 );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the HERE map API key when one is set.
	 *
	 * @param array $args Arguments. See Map_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'HERE Maps', 'simple-location' );
		$this->slug         = 'here';
		$this->url          = 'https://developer.here.com/';
		$this->description  = __( 'HERE offers a free limited plan for up to 30,000 map transactions per month', 'simple-location' );
		$this->max_width    = 2048;
		$this->max_height   = 2048;
		$this->max_map_zoom = 20;

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = self::get_map_api_key( 'here' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = $this->get_style_setting( 'sloc_here_style' );
		}
		// Styles from the retired API, such as alps, aren't valid in v3.
		if ( ! is_string( $args['style'] ) || ! array_key_exists( $args['style'], $this->get_styles() ) ) {
			$args['style'] = 'explore.day';
		}

		parent::__construct( $args );
	}

	/**
	 * Returns the provider's style for each style family.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] Style IDs keyed by family. See Map_Provider::get_family_styles().
	 */
	public function get_family_styles() {
		return array(
			'standard'  => 'explore.day',
			'light'     => 'lite.day',
			'dark'      => 'explore.night',
			'satellite' => 'explore.satellite.day',
			'terrain'   => 'topo.day',
		);
	}

	/**
	 * Returns the Map Image API v3 styles.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Map Image API v3 styles.
	 *
	 * @return array Styles keyed by ID.
	 */
	public function get_styles() {
		return array(
			'explore.day'             => __( 'Explore (Day)', 'simple-location' ),
			'explore.night'           => __( 'Explore (Night)', 'simple-location' ),
			'explore.satellite.day'   => __( 'Explore Satellite (Day)', 'simple-location' ),
			'lite.day'                => __( 'Lite (Day)', 'simple-location' ),
			'lite.night'              => __( 'Lite (Night)', 'simple-location' ),
			'lite.satellite.day'      => __( 'Lite Satellite (Day)', 'simple-location' ),
			'logistics.day'           => __( 'Logistics (Day)', 'simple-location' ),
			'logistics.night'         => __( 'Logistics (Night)', 'simple-location' ),
			'logistics.satellite.day' => __( 'Logistics Satellite (Day)', 'simple-location' ),
			'satellite.day'           => __( 'Satellite (Day)', 'simple-location' ),
			'topo.day'                => __( 'Topographic (Day)', 'simple-location' ),
			'topo.night'              => __( 'Topographic (Night)', 'simple-location' ),
		);
	}

	/**
	 * Returns the static map image URL, centered on the location with a marker.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses Map Image API v3.
	 * @since 6.0.0 Draws a high-density map when the scale is 2.
	 * @since 6.1.0 Uses the image format setting.
	 * @since 6.1.0 Uses the marker settings.
	 * @since 6.1.0 Leaves the copyright notice off the image when it is printed under the map.
	 * @since 6.1.0 Draws the scale bar setting.
	 * @since 6.1.0 Labels are in the site's language.
	 *
	 * @return string URL, or an empty string without an API key.
	 */
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$url = sprintf(
			'%1$scenter:%2$s,%3$s;zoom=%4$s/%5$sx%6$s/%7$s',
			self::BASE_URL,
			$this->latitude,
			$this->longitude,
			$this->map_zoom,
			$this->width * $this->scale,
			$this->height * $this->scale,
			$this->get_vendor_format( array( 'jpg' => 'jpeg' ) )
		);

		$args = array(
			'apiKey'  => $this->api,
			'style'   => $this->style,
			'overlay' => sprintf( 'point:%1$s,%2$s;%3$s', $this->latitude, $this->longitude, $this->get_marker_style() ),
		);
		$args = $this->add_language_code( $args );
		// A high-density map is twice the size at scale 2, showing the same area.
		if ( 1 < $this->scale ) {
			$args['scale'] = $this->scale;
		}
		// The notice is only left off the image when it is printed under the map.
		if ( '' !== $this->get_attribution_html() ) {
			$args['copyright'] = 'false';
		}
		return add_query_arg( $this->add_scalebar( $args ), $url );
	}

	/**
	 * Adds the scale bar to a Map Image API query.
	 *
	 * HERE places the scale bar itself. Both units put the site's units on top.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Query parameters.
	 * @return array Query parameters.
	 */
	protected function add_scalebar( $args ) {
		$units    = array(
			'metric'   => 'km',
			'imperial' => 'mi',
			'both'     => $this->site_uses_imperial() ? 'miKm' : 'kmMi',
		);
		$scalebar = $this->get_scalebar_units();
		if ( isset( $units[ $scalebar ] ) ) {
			$args['scaleBar'] = $units[ $scalebar ];
		}
		return $args;
	}

	/**
	 * Returns the overlay style of a single location's marker.
	 *
	 * @since 6.1.0
	 *
	 * @return string Style properties, such as `icon=pin;size=medium;color=%23d32f2f`.
	 */
	protected function get_marker_style() {
		if ( 'dot' === $this->marker ) {
			$widths = array(
				'small'  => 8,
				'medium' => 12,
				'large'  => 16,
			);
			return sprintf( 'style=circle;width=%1$d;color=%%23%2$s', $widths[ $this->marker_size ], $this->get_marker_hex() );
		}
		return sprintf( 'icon=pin;size=%1$s;color=%%23%2$s', $this->marker_size, $this->get_marker_hex() );
	}

	/**
	 * Returns the pixel densities the provider can draw the static map at.
	 *
	 * HERE's size limit counts image pixels, so a high-density map is only offered when the doubled size fits.
	 *
	 * @since 6.0.0
	 *
	 * @return int[] Pixel densities.
	 */
	public function get_scales() {
		if ( $this->width * 2 > $this->max_width || $this->height * 2 > $this->max_height ) {
			return array( 1 );
		}
		return array( 1, 2 );
	}

	/**
	 * Returns the position part of an archive map's URL.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string `center:lat,lng;zoom=z` for the plugin's view, or `overlay:padding=32` for HERE to fit the locations.
	 */
	protected function get_archive_position( $locations ) {
		$view = $this->get_archive_view( $locations );
		if ( null === $view ) {
			return 'overlay:padding=32';
		}
		return sprintf( 'center:%1$s,%2$s;zoom=%3$s', $view['latitude'], $view['longitude'], $view['zoom'] );
	}

	/**
	 * Returns a map image URL showing the route through a list of locations, sized to fit them.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses Map Image API v3.
	 * @since 6.1.0 Centred and zoomed by the plugin, so its markers can be linked to their posts.
	 * @since 6.1.0 Drawn at the map's pixel density, for high-density images.
	 * @since 6.1.0 Uses the image format setting.
	 * @since 6.1.0 Markers are in the marker color.
	 * @since 6.1.0 Draws the scale bar setting.
	 * @since 6.1.0 Labels are in the site's language.
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

		$args = array(
			'apiKey'  => $this->api,
			'style'   => $this->style,
			// A style after the last point applies to all of them.
			'overlay' => 'point:' . implode( '|', $points ) . '|color=%23' . $this->get_marker_hex(),
		);
		$args = $this->add_language_code( $args );
		// A high-density map is twice the size at scale 2, showing the same area.
		if ( 1 < $this->scale ) {
			$args['scale'] = $this->scale;
		}
		$url = add_query_arg(
			$this->add_scalebar( $args ),
			sprintf(
				'%1$s%2$s/%3$sx%4$s/%5$s',
				self::BASE_URL,
				$this->get_archive_position( $locations ),
				$this->width * $this->scale,
				$this->height * $this->scale,
				$this->get_vendor_format( array( 'jpg' => 'jpeg' ) )
			)
		);
		// Lines and points are separate overlays, and a line needs at least two points.
		if ( count( $points ) > 1 ) {
			$url .= '&overlay=line:' . implode( ',', $points ) . ';width=4';
		}
		return $url;
	}

	/**
	 * Returns a link to the location on HERE WeGo.
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://wego.here.com/?map=%1$s,%2$s,%3$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	/**
	 * Returns the map HTML.
	 *
	 * @since 6.1.0 Followed by the map's copyright notice when the attribution setting puts it under the map.
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML.
	 */
	public function get_the_map( $static = true ) {
		$map = $this->get_the_static_map_html( true );
		return '' === $map ? '' : $map . $this->get_attribution_html();
	}

	/**
	 * Returns the map's copyright notice to show under the map, when the attribution setting puts it there.
	 *
	 * HERE requires `© year HERE` with the data suppliers for the map's area and zoom, from the Map Image API's
	 * copyright list, which is stored for a day. Archive maps, which have no single location, keep the notice on the image.
	 *
	 * @since 6.1.0
	 *
	 * @return string HTML of a paragraph with the notice, or an empty string if it is drawn on the image.
	 */
	public function get_attribution_html() {
		if ( 'below' !== $this->attribution || null === $this->latitude || null === $this->longitude || empty( $this->api ) ) {
			return '';
		}
		$copyrights = $this->get_copyrights();
		if ( null === $copyrights ) {
			return '';
		}
		$labels = array();
		foreach ( $this->get_copyright_layers( $copyrights ) as $layer ) {
			foreach ( (array) ( $copyrights['copyrights'][ $layer ] ?? array() ) as $copyright ) {
				if ( is_array( $copyright ) && $this->copyright_applies( $copyright ) && ! empty( $copyright['label'] ) && is_string( $copyright['label'] ) ) {
					$labels[ $copyright['label'] ] = true;
				}
			}
		}
		unset( $labels['HERE'] );
		// translators: Year.
		$notice = sprintf( __( '© %s HERE', 'simple-location' ), gmdate( 'Y' ) );
		if ( $labels ) {
			$notice .= ', ' . implode( ', ', array_keys( $labels ) );
		}
		return '<p class="sloc-map-attribution">' . esc_html( $notice ) . '</p>';
	}

	/**
	 * Returns the Map Image API's copyright list, stored for a day.
	 *
	 * @since 6.1.0
	 *
	 * @return array|null Copyright list, with 'copyrights' and 'resources', or null if it can't be fetched.
	 */
	protected function get_copyrights() {
		$copyrights = get_transient( 'sloc_here_copyright' );
		if ( is_array( $copyrights ) ) {
			return $copyrights;
		}
		$json = $this->fetch_json( self::COPYRIGHT_URL, array( 'apiKey' => $this->api ) );
		if ( is_wp_error( $json ) || ! is_array( $json ) || ! isset( $json['copyrights'] ) || ! is_array( $json['copyrights'] ) ) {
			return null;
		}
		set_transient( 'sloc_here_copyright', $json, DAY_IN_SECONDS );
		return $json;
	}

	/**
	 * Returns the copyright layers of the map's style, or every layer if the list doesn't name the style.
	 *
	 * @since 6.1.0
	 *
	 * @param array $copyrights Copyright list.
	 * @return string[] Layer keys of 'copyrights'.
	 */
	protected function get_copyright_layers( $copyrights ) {
		$layers = $copyrights['resources']['base']['styles'][ $this->style ] ?? null;
		if ( is_array( $layers ) && $layers ) {
			return array_filter( $layers, 'is_string' );
		}
		return array_map( 'strval', array_keys( $copyrights['copyrights'] ) );
	}

	/**
	 * Whether a copyright applies to the map's zoom and location.
	 *
	 * @since 6.1.0
	 *
	 * @param array $copyright Copyright, with optional 'minLevel', 'maxLevel' and 'boundingBoxes'.
	 * @return bool
	 */
	protected function copyright_applies( $copyright ) {
		$zoom = (int) $this->map_zoom;
		if ( ( isset( $copyright['minLevel'] ) && $zoom < (int) $copyright['minLevel'] ) || ( isset( $copyright['maxLevel'] ) && $zoom > (int) $copyright['maxLevel'] ) ) {
			return false;
		}
		if ( empty( $copyright['boundingBoxes'] ) || ! is_array( $copyright['boundingBoxes'] ) ) {
			return true;
		}
		foreach ( $copyright['boundingBoxes'] as $box ) {
			if ( ! is_array( $box ) || ! isset( $box['west'], $box['south'], $box['east'], $box['north'] ) ) {
				continue;
			}
			$in_longitude = $box['west'] <= $box['east']
				? ( $this->longitude >= $box['west'] && $this->longitude <= $box['east'] )
				// A box across the antimeridian.
				: ( $this->longitude >= $box['west'] || $this->longitude <= $box['east'] );
			if ( $in_longitude && $this->latitude >= $box['south'] && $this->latitude <= $box['north'] ) {
				return true;
			}
		}
		return false;
	}
}
