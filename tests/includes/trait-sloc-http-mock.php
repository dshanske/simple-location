<?php
/**
 * Mocks outgoing HTTP requests for provider tests.
 *
 * @package Simple_Location
 */

/**
 * Answers requests from canned responses and records every request made.
 *
 * Responses are matched by substring of the URL. Requests that match nothing fail with
 * a WP_Error, and the bootstrap blocks any request a test didn't mock.
 */
trait Sloc_Http_Mock {

	/**
	 * Requests made since mock_http() was called.
	 *
	 * @var array[] Each has 'url' and 'args'.
	 */
	protected $http_requests = array();

	/**
	 * Responses keyed by URL substring.
	 *
	 * @var array
	 */
	protected $http_responses = array();

	/**
	 * Starts answering requests.
	 *
	 * @param array $responses URL substring => response. A response is a WP_Error, or an array
	 *                         with 'body' (array, encoded as JSON, or string), and optionally
	 *                         'code' (default 200) and 'headers'.
	 */
	protected function mock_http( $responses ) {
		$this->http_responses = $responses;
		$this->http_requests  = array();
		add_filter( 'pre_http_request', array( $this, 'mock_http_response' ), 10, 3 );
	}

	/**
	 * The pre_http_request filter callback.
	 *
	 * @param false|array|WP_Error $pre  Short-circuit value.
	 * @param array                $args Request arguments.
	 * @param string               $url  Request URL.
	 * @return array|WP_Error
	 */
	public function mock_http_response( $pre, $args, $url ) {
		$this->http_requests[] = array(
			'url'  => $url,
			'args' => $args,
		);
		foreach ( $this->http_responses as $pattern => $response ) {
			if ( false === strpos( $url, $pattern ) ) {
				continue;
			}
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$response = wp_parse_args(
				$response,
				array(
					'code'    => 200,
					'body'    => '',
					'headers' => array( 'content-type' => 'application/json' ),
				)
			);
			return array(
				'headers'  => $response['headers'],
				'body'     => is_string( $response['body'] ) ? $response['body'] : wp_json_encode( $response['body'] ),
				'response' => array(
					'code'    => $response['code'],
					'message' => get_status_header_desc( $response['code'] ),
				),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		return new WP_Error( 'unmocked_request', 'Unmocked request: ' . $url );
	}

	/**
	 * Loads a JSON fixture from tests/data.
	 *
	 * @param string $name File name relative to tests/data.
	 * @return array
	 */
	protected function fixture( $name ) {
		return json_decode( file_get_contents( dirname( __DIR__ ) . '/data/' . $name ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * The most recent request.
	 *
	 * @return array With 'url', 'args', 'base' (URL without query) and 'query' (parsed).
	 */
	protected function last_request() {
		$this->assertNotEmpty( $this->http_requests, 'No HTTP request was made.' );
		$request = end( $this->http_requests );
		parse_str( (string) wp_parse_url( $request['url'], PHP_URL_QUERY ), $query );
		$request['query'] = $query;
		$request['base']  = strtok( $request['url'], '?' );
		return $request;
	}
}
