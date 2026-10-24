<?php
/**
 * GeoJSON feeds.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serves the locations of posts as GeoJSON, https://datatracker.ietf.org/doc/html/rfc7946, as a feed type.
 *
 * Any feed URL can end in geojson, such as /feed/geojson/ for the latest posts, /location/philadelphia/feed/geojson/
 * for a location's posts, or /2024/feed/geojson/. A post's own feed, such as /2024/07/01/a-post/feed/geojson/, has
 * that post. Each post with a public location is a Point feature; other posts are left out.
 *
 * @since 6.1.0
 */
class Sloc_GeoJSON {

	/**
	 * The feed type, as in /feed/geojson/.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const FEED = 'geojson';

	/**
	 * Registers the feed and its discovery link.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		add_feed( self::FEED, array( __CLASS__, 'feed' ) );
		add_action( 'wp_head', array( __CLASS__, 'link_tag' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_flush_rewrite_rules' ) );
	}

	/**
	 * Flushes the rewrite rules once if they don't have the feed yet, such as after an update, so /feed/geojson/ works.
	 *
	 * Updates flush the rules with the code from before the update, which didn't have the feed. Plain permalinks have no
	 * rules and use ?feed=geojson. It is tried at most once a day, in case something else removes feed rules.
	 *
	 * @since 6.1.0
	 */
	public static function maybe_flush_rewrite_rules() {
		$rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $rules ) || empty( $rules ) || get_transient( 'sloc_geojson_flushed' ) ) {
			return;
		}
		foreach ( array_keys( $rules ) as $rule ) {
			if ( false !== strpos( $rule, self::FEED ) ) {
				return;
			}
		}
		set_transient( 'sloc_geojson_flushed', 1, DAY_IN_SECONDS );
		flush_rewrite_rules( false );
	}

	/**
	 * Echoes the feed: the main query's posts as a FeatureCollection.
	 *
	 * @since 6.1.0
	 */
	public static function feed() {
		global $wp_query;
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/geo+json; charset=' . get_option( 'blog_charset' ), true );
		}
		echo wp_json_encode( self::collection( $wp_query->posts ?? array() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Returns posts as a FeatureCollection.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_Post[] $posts Posts.
	 * @return array FeatureCollection.
	 */
	public static function collection( $posts ) {
		$features = array();
		foreach ( (array) $posts as $post ) {
			$feature = self::feature( $post );
			if ( $feature ) {
				$features[] = $feature;
			}
		}
		/**
		 * Filters the GeoJSON FeatureCollection of a feed.
		 *
		 * @since 6.1.0
		 *
		 * @param array     $collection FeatureCollection, with 'type' and 'features'.
		 * @param WP_Post[] $posts      Posts in the feed, including those without a public location.
		 */
		return apply_filters(
			'sloc_geojson',
			array(
				'type'     => 'FeatureCollection',
				'features' => $features,
			),
			$posts
		);
	}

	/**
	 * Returns a post as a Point feature, if its location is public.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_Post|int $post Post.
	 * @return array|null Feature, or null for a post without a public location, or with a password.
	 */
	public static function feature( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
			return null;
		}
		$geo = get_post_geodata( $post );
		if ( ! is_array( $geo ) || 'public' !== ( $geo['visibility'] ?? '' ) || ! is_numeric( $geo['latitude'] ?? null ) || ! is_numeric( $geo['longitude'] ?? null ) ) {
			return null;
		}
		$coordinates = array( (float) $geo['longitude'], (float) $geo['latitude'] );
		if ( is_numeric( $geo['altitude'] ?? null ) ) {
			$coordinates[] = (float) $geo['altitude'];
		}
		$published = get_post_datetime( $post );
		$feature   = array(
			'type'       => 'Feature',
			'geometry'   => array(
				'type'        => 'Point',
				'coordinates' => $coordinates,
			),
			'properties' => array_filter(
				array(
					'name'      => wp_strip_all_tags( get_the_title( $post ) ),
					'url'       => get_permalink( $post ),
					'published' => $published ? $published->format( DATE_W3C ) : null,
					'address'   => isset( $geo['address'] ) ? wp_strip_all_tags( $geo['address'] ) : null,
				)
			),
		);
		/**
		 * Filters a post's GeoJSON feature. Return null to leave the post out.
		 *
		 * @since 6.1.0
		 *
		 * @param array|null $feature Point feature, with the post's name, url, published time and address as properties.
		 * @param WP_Post    $post    Post.
		 */
		return apply_filters( 'sloc_geojson_feature', $feature, $post );
	}

	/**
	 * Returns the GeoJSON feed URL for the current page: a post with a public location, the latest posts, or a location.
	 *
	 * @since 6.1.0
	 *
	 * @return string URL, or an empty string for a page without one.
	 */
	public static function feed_url() {
		if ( is_singular() ) {
			return self::feature( get_queried_object() ) ? get_post_comments_feed_link( get_queried_object_id(), self::FEED ) : '';
		}
		if ( is_tax( 'location' ) ) {
			return (string) get_term_feed_link( get_queried_object_id(), 'location', self::FEED );
		}
		if ( is_home() || is_front_page() ) {
			return get_feed_link( self::FEED );
		}
		return '';
	}

	/**
	 * Echoes a link to the page's GeoJSON feed, so maps and other tools can find it.
	 *
	 * @since 6.1.0
	 */
	public static function link_tag() {
		$url = self::feed_url();
		if ( '' !== $url ) {
			printf( '<link rel="alternate" type="application/geo+json" title="%1$s" href="%2$s" />' . PHP_EOL, esc_attr__( 'Locations (GeoJSON)', 'simple-location' ), esc_url( $url ) );
		}
	}
}

add_action( 'init', array( 'Sloc_GeoJSON', 'init' ) );
