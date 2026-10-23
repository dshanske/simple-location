<?php
/**
 * Location Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Location Provider using GeoPulse, a self-hosted location timeline.
 * https://github.com/tess1o/geopulse
 *
 * Needs GeoPulse 2.1.0 or later, which has API tokens. A token with the read scope is enough.
 *
 * @since 6.1.0
 */
class Location_Provider_GeoPulse extends Location_Provider_Account {

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Location_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'GeoPulse', 'simple-location' );
		$this->slug        = 'geopulse';
		$this->url         = 'https://github.com/tess1o/geopulse';
		$this->description = __( 'If you run GeoPulse 2.1.0 or later, the site can read your current or past location from it. Each user enters their GeoPulse URL and an API token on their profile.', 'simple-location' );
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
		return __( 'An API token from GeoPulse, created with only the read scope.', 'simple-location' );
	}

	/**
	 * Requests a GeoPulse API.
	 *
	 * The token is sent in the Authorization header, so it isn't part of the URL.
	 *
	 * @since 6.1.0
	 *
	 * @param array  $account Account, from get_account().
	 * @param string $path    API path after /api/v1.
	 * @param array  $query   Query parameters.
	 * @return array|WP_Error Response, or an error.
	 */
	protected function request( $account, $path, $query ) {
		$json = $this->fetch_json( $account['url'] . '/api/v1' . $path, $query, array( 'Authorization' => 'Bearer ' . $account['key'] ) );
		if ( is_wp_error( $json ) ) {
			// GeoPulse explains a refused request as a problem detail.
			$data = $json->get_error_data();
			$body = is_array( $data ) ? json_decode( $data['body'] ?? '', true ) : null;
			if ( is_array( $body ) ) {
				$message = $body['detail'] ?? $body['title'] ?? $body['message'] ?? null;
				if ( is_string( $message ) && '' !== $message ) {
					return new WP_Error( 'geopulse_error', $message, array( 'status' => $data['status'] ?? 0 ) );
				}
			}
		}
		return $json;
	}

	/**
	 * Retrieves the user's location from GeoPulse: the latest point, or the latest point before a time.
	 *
	 * @since 6.1.0
	 *
	 * @param string|int|DateTime $time An ISO8601 time string, unix timestamp, or DateTime. Optional; defaults to the latest location.
	 * @param array               $args Optional arguments. Unused.
	 * @return WP_Error|false|null An error, or false, if no location was found; null when the location is set (read it
	 *                             with get()), or without a user, URL or API token.
	 */
	public function retrieve( $time = null, $args = array() ) {
		$this->reset();
		$account = $this->get_account();
		if ( ! is_array( $account ) ) {
			return $account;
		}
		$query = array(
			'size'          => 1,
			'sortBy'        => 'timestamp',
			'sortDirection' => 'desc',
		);
		if ( $time ) {
			$time = $this->utc_time( $time );
			if ( is_wp_error( $time ) ) {
				return $time;
			}
			$query['to'] = $time->format( 'Y-m-d\TH:i:s\Z' );
		}
		$json = $this->request( $account, '/gps/points', $query );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$point = $json['items'][0] ?? null;
		if ( ! is_array( $point ) ) {
			return false;
		}
		$set = $this->set_point(
			$point['coordinates']['lat'] ?? null,
			$point['coordinates']['lng'] ?? null,
			array(
				'altitude' => $point['altitude'] ?? null,
				'accuracy' => $point['accuracy'] ?? null,
				'kmh'      => $point['velocity'] ?? null,
				'time'     => $point['timestamp'] ?? null,
			)
		);
		return $set ? null : false;
	}

	/**
	 * Returns the user's track from GeoPulse between two times, simplified by GeoPulse.
	 *
	 * @since 6.1.0
	 *
	 * @param DateTime|string $start Start time.
	 * @param DateTime|string $end   End time.
	 * @return array|WP_Error|false|null List of array( longitude, latitude ) points; an error if the request fails;
	 *                                   false if there are no points; or null without a user, URL or API token.
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
		$json = $this->request(
			$account,
			'/gps/points/path',
			array(
				'from'     => $start->format( 'Y-m-d\TH:i:s\Z' ),
				'to'       => $end->format( 'Y-m-d\TH:i:s\Z' ),
				'simplify' => 'true',
			)
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$points = ( isset( $json['points'] ) && is_array( $json['points'] ) ) ? $json['points'] : array();
		$track  = self::track( $points, 'latitude', 'longitude' );
		return empty( $track ) ? false : $track;
	}
}
