<?php
/**
 * Map Shortcode.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Sloc_Map_Shortcode', 'init' ) );

/**
 * The [sloc_map] shortcode, which draws an archive map of several locations inside a post or page.
 *
 * @since 6.1.0
 */
class Sloc_Map_Shortcode {

	/**
	 * Most points drawn on one map. Map image URLs, and providers, have length limits.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_POINTS = 100;

	/**
	 * Registers the shortcode.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		add_shortcode( 'sloc_map', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Renders the [sloc_map] shortcode.
	 *
	 * The points come from, in order of preference: the `track` attribute, drawn as a line; the `points` attribute; the
	 * posts or attachments in `ids`; or the current post's attached images. Posts and images are only drawn when their
	 * location is public, and their markers link to them where the map provider allows.
	 *
	 * @since 6.1.0
	 *
	 * @param array|string $atts {
	 *     Shortcode attributes.
	 *
	 *     @type string $track   A route as an encoded polyline, drawn as a line, simplified to Sloc_Track::MAX_POINTS. Its
	 *                          brackets, backslashes and backticks are percent-encoded, as Sloc_Track::to_attribute() does.
	 *     @type string $markers Markers of a track: 'ends' (default) for its start and end, or 'none'.
	 *     @type string $points Points as "latitude,longitude", separated by `|`, such as "39.95,-75.16|40.71,-74.01".
	 *     @type string $ids    Post or attachment IDs, separated by commas.
	 *     @type int    $width  Map width in pixels. Default the map width setting.
	 *     @type int    $height Map height in pixels. Default from the map width and aspect ratio settings.
	 * }
	 * @return string HTML, or an empty string without a map provider or any points to draw.
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'track'   => '',
				'markers' => 'ends',
				'points'  => '',
				'ids'     => '',
				'width'   => '',
				'height'  => '',
			),
			$atts,
			'sloc_map'
		);

		$track = '' !== trim( (string) $atts['track'] );
		if ( $track ) {
			$locations = Sloc_Track::simplify( Sloc_Track::decode( Sloc_Track::from_attribute( $atts['track'] ) ) );
			// A line needs two points.
			if ( count( $locations ) < 2 ) {
				$locations = array();
			}
		} elseif ( '' !== trim( (string) $atts['points'] ) ) {
			$locations = self::parse_points( $atts['points'] );
		} elseif ( '' !== trim( (string) $atts['ids'] ) ) {
			$locations = self::post_locations( wp_parse_id_list( $atts['ids'] ) );
		} else {
			$locations = self::attachment_locations( get_the_ID() );
		}
		if ( empty( $locations ) ) {
			return '';
		}

		$map = Loc_Config::map_provider();
		if ( ! $map instanceof Map_Provider ) {
			return '';
		}
		// The provider is shared, so its size isn't changed for the page's other maps.
		$map = clone $map;
		if ( $track ) {
			$map->set_track( $atts['markers'] );
		}
		$size = array_filter(
			array(
				'width'  => absint( $atts['width'] ),
				'height' => absint( $atts['height'] ),
			)
		);
		if ( ! empty( $size ) ) {
			$map->set( $size );
		}

		// Points and tracks given in the shortcode have no posts to link to.
		$post_ids = $track ? array() : array_filter( array_keys( $locations ), 'is_int' );
		$post_ids = count( $post_ids ) === count( $locations ) ? $post_ids : array();

		return sprintf(
			'<div class="sloc-map-shortcode">%1$s%2$s</div>',
			$map->get_archive_map_html( array_values( $locations ), $post_ids ),
			wp_kses( $map->get_attribution_html(), Simple_Location_Plugin::kses_clean() )
		);
	}

	/**
	 * Parses points given in the shortcode.
	 *
	 * @since 6.1.0
	 *
	 * @param string $points Points as "latitude,longitude", separated by `|`.
	 * @return array List of array( latitude, longitude ), skipping invalid points, at most MAX_POINTS.
	 */
	public static function parse_points( $points ) {
		$locations = array();
		foreach ( explode( '|', (string) $points ) as $point ) {
			$coordinates = array_map( 'trim', explode( ',', $point ) );
			if ( 2 !== count( $coordinates ) ) {
				continue;
			}
			$latitude  = clean_coordinate( $coordinates[0], 'latitude' );
			$longitude = clean_coordinate( $coordinates[1], 'longitude' );
			if ( false !== $latitude && false !== $longitude ) {
				$locations[ 'point-' . count( $locations ) ] = array( $latitude, $longitude );
			}
		}
		return array_slice( $locations, 0, self::MAX_POINTS, true );
	}

	/**
	 * Returns the public locations of posts or attachments.
	 *
	 * @since 6.1.0
	 *
	 * @param int[] $ids Post or attachment IDs.
	 * @return array Array( latitude, longitude ) points keyed by post ID, in the given order, at most MAX_POINTS.
	 */
	public static function post_locations( $ids ) {
		$locations = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			// Only published posts, and images of them, are shown to visitors.
			if ( ! $post instanceof WP_Post || ! is_post_publicly_viewable( 'attachment' === $post->post_type && $post->post_parent ? $post->post_parent : $post ) ) {
				continue;
			}
			$location = get_post_geodata( $post->ID, false );
			if ( is_array( $location ) && 'public' === ( $location['visibility'] ?? '' ) && isset( $location['latitude'], $location['longitude'] ) ) {
				$locations[ $post->ID ] = array( (float) $location['latitude'], (float) $location['longitude'] );
			}
			if ( count( $locations ) >= self::MAX_POINTS ) {
				break;
			}
		}
		return $locations;
	}

	/**
	 * Returns the public locations of a post's attached images.
	 *
	 * @since 6.1.0
	 *
	 * @param int|false $post_id Post ID.
	 * @return array Array( latitude, longitude ) points keyed by attachment ID, in menu order, at most MAX_POINTS.
	 */
	public static function attachment_locations( $post_id ) {
		if ( ! $post_id ) {
			return array();
		}
		$images = get_children(
			array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'orderby'        => 'menu_order ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		return self::post_locations( array_map( 'intval', (array) $images ) );
	}
}
