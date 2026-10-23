<?php
/**
 * Location Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Location Provider using Dawarich, a self-hosted location history service.
 * https://dawarich.app/
 *
 * @since 6.1.0
 */
class Location_Provider_Dawarich extends Location_Provider_Account {

	/**
	 * Most points asked for in one request for a track.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const PER_PAGE = 1000;

	/**
	 * Most pages of points read for one track.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_PAGES = 10;

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Location_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Dawarich', 'simple-location' );
		$this->slug        = 'dawarich';
		$this->url         = 'https://dawarich.app/';
		$this->description = __( 'If you run Dawarich, the site can read your current or past location from it. Each user enters their Dawarich URL and API key on their profile.', 'simple-location' );
		parent::__construct( $args );
	}

	/**
	 * Returns the description of the API key field.
	 *
	 * @since 6.1.0
	 *
	 * @return string Description.
	 */
	protected function get_key_description() {
		return __( 'Your API key, from the Account page in Dawarich.', 'simple-location' );
	}

	/**
	 * Requests points from Dawarich.
	 *
	 * @since 6.1.0
	 *
	 * @param array $account Account, from get_account().
	 * @param array $query   Query parameters other than the key.
	 * @return array|WP_Error List of points, or an error.
	 */
	protected function points( $account, $query ) {
		$query['api_key'] = $account['key'];
		$json             = $this->fetch_json( $account['url'] . '/api/v1/points', $query );
		if ( is_wp_error( $json ) ) {
			$data = $json->get_error_data();
			// No points is an empty list, which fetch_json() doesn't count as JSON.
			if ( 'not_json_response' === $json->get_error_code() && is_array( $data ) && '[]' === trim( $data['body'] ?? '' ) ) {
				return array();
			}
			// Dawarich explains a refused request in the response's error field.
			$body = is_array( $data ) ? json_decode( $data['body'] ?? '', true ) : null;
			if ( is_array( $body ) && ! empty( $body['error'] ) && is_string( $body['error'] ) ) {
				return new WP_Error( 'dawarich_error', $body['error'], array( 'status' => $data['status'] ?? 0 ) );
			}
			return $json;
		}
		return array_values( array_filter( $json, 'is_array' ) );
	}

	/**
	 * Retrieves the user's location from Dawarich: the latest point, or the latest point before a time.
	 *
	 * @since 6.1.0
	 *
	 * @param string|int|DateTime $time An ISO8601 time string, unix timestamp, or DateTime. Optional; defaults to the latest location.
	 * @param array               $args Optional arguments. Unused.
	 * @return WP_Error|false|null An error, or false, if no location was found; null when the location is set (read it
	 *                             with get()), or without a user, URL or API key.
	 */
	public function retrieve( $time = null, $args = array() ) {
		$this->reset();
		$account = $this->get_account();
		if ( ! is_array( $account ) ) {
			return $account;
		}
		$query = array(
			'order'    => 'desc',
			'per_page' => 1,
		);
		if ( $time ) {
			$time = $this->utc_time( $time );
			if ( is_wp_error( $time ) ) {
				return $time;
			}
			$query['end_at'] = $time->format( 'Y-m-d\TH:i:s\Z' );
		}
		$points = $this->points( $account, $query );
		if ( is_wp_error( $points ) ) {
			return $points;
		}
		if ( empty( $points ) ) {
			return false;
		}
		$point = $points[0];
		$set   = $this->set_point(
			$point['latitude'] ?? null,
			$point['longitude'] ?? null,
			array(
				'altitude'          => $point['altitude'] ?? null,
				'accuracy'          => $point['accuracy'] ?? null,
				'altitude_accuracy' => $point['vertical_accuracy'] ?? null,
				'kmh'               => $point['velocity'] ?? null,
				'heading'           => $point['course'] ?? null,
				'time'              => $point['timestamp'] ?? null,
			)
		);
		return $set ? null : false;
	}

	/**
	 * Returns the user's track from Dawarich between two times.
	 *
	 * @since 6.1.0
	 *
	 * @param DateTime|string $start Start time.
	 * @param DateTime|string $end   End time.
	 * @return array|WP_Error|false|null List of array( longitude, latitude ) points; an error if the request fails;
	 *                                   false if there are no points; or null without a user, URL or API key.
	 */
	public function query( $start, $end ) {
		$account = $this->get_account();
		if ( ! is_array( $account ) ) {
			return $account;
		}
		$start = $this->utc_time( $start );
		$end   = $this->utc_time( $end );
		if ( is_wp_error( $start ) ) {
			return $start;
		}
		if ( is_wp_error( $end ) ) {
			return $end;
		}
		$track = array();
		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$points = $this->points(
				$account,
				array(
					'start_at' => $start->format( 'Y-m-d\TH:i:s\Z' ),
					'end_at'   => $end->format( 'Y-m-d\TH:i:s\Z' ),
					'order'    => 'asc',
					'per_page' => self::PER_PAGE,
					'page'     => $page,
				)
			);
			if ( is_wp_error( $points ) ) {
				return $points;
			}
			$track = array_merge( $track, self::track( $points, 'latitude', 'longitude' ) );
			if ( count( $points ) < self::PER_PAGE ) {
				break;
			}
		}
		return empty( $track ) ? false : $track;
	}
}
