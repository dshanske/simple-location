<?php
/**
 * Trait for the Nominatim API
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Implements the Nominatim server setting shared by the Nominatim geo and venue providers.
 *
 * A site can use its own Nominatim server, or another hosted one, instead of the public OpenStreetMap server, and can
 * fall back to the public server when its own finds nothing, such as a server that only has data for one region.
 *
 * @since 6.1.0
 */
trait Sloc_API_Nominatim {
	/**
	 * Returns the public OpenStreetMap Nominatim server, used without a server URL setting.
	 *
	 * @since 6.1.0
	 *
	 * @return string URL, without a trailing slash.
	 */
	public static function nominatim_public_url() {
		return 'https://nominatim.openstreetmap.org';
	}

	/**
	 * Returns the Nominatim server to ask: the server URL setting, or the public server.
	 *
	 * @since 6.1.0
	 *
	 * @return string URL, without a trailing slash.
	 */
	public static function nominatim_url() {
		$url = self::sanitize_nominatim_url( get_option( 'sloc_nominatim_url' ) );
		return '' === $url ? self::nominatim_public_url() : untrailingslashit( $url );
	}

	/**
	 * Sanitizes the server URL setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Server URL.
	 * @return string HTTP or HTTPS URL, or an empty string for the public server.
	 */
	public static function sanitize_nominatim_url( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return (string) esc_url_raw( trim( $value ), array( 'http', 'https' ) );
	}

	/**
	 * Requests an endpoint from the Nominatim server, and from the public server when that finds nothing and the
	 * fallback setting is on.
	 *
	 * Nominatim answers a lookup that finds nothing with an error object or an empty list, and either, or a failed
	 * request, counts as nothing found.
	 *
	 * @since 6.1.0
	 *
	 * @param string $endpoint Endpoint, such as 'reverse' or 'search'.
	 * @param array  $query    Query arguments.
	 * @return array|WP_Error Decoded response, or an error.
	 */
	protected function nominatim_json( $endpoint, $query ) {
		$server = self::nominatim_url();
		$json   = $this->fetch_json( $server . '/' . $endpoint, $query );
		$found  = ! is_wp_error( $json ) && ! empty( $json ) && ! isset( $json['error'] );
		if ( $found || self::nominatim_public_url() === $server || ! get_option( 'sloc_nominatim_fallback' ) ) {
			return $json;
		}
		return $this->fetch_json( self::nominatim_public_url() . '/' . $endpoint, $query );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 * @since 6.1.2 The fallback setting is sanitized.
	 */
	public static function init() {
		register_setting(
			'sloc_api',
			'sloc_nominatim_url',
			array(
				'type'              => 'string',
				'description'       => 'Nominatim Server URL',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_nominatim_url' ),
			)
		);
		register_setting(
			'sloc_api',
			'sloc_nominatim_fallback',
			array(
				'type'              => 'boolean',
				'description'       => 'Use the Public Nominatim Server When Yours Finds Nothing',
				'show_in_rest'      => false,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function admin_init() {
		add_settings_field(
			'sloc_nominatim_url',
			__( 'Nominatim Server URL', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => 'sloc_nominatim_url',
				'type'        => 'url',
				'description' => __( 'Optional. The address of your own Nominatim server, or another hosted one, such as https://nominatim.example.com. Leave empty to use the public OpenStreetMap server, which is for light use only.', 'simple-location' ),
			)
		);
		add_settings_field(
			'sloc_nominatim_fallback',
			__( 'Nominatim Public Fallback', 'simple-location' ),
			array( 'Loc_Config', 'checkbox_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => 'sloc_nominatim_fallback',
				'description' => __( 'When your server finds nothing or can\'t be reached, ask the public OpenStreetMap server, for example if your server only has data for your own area. The location is then sent to the public server.', 'simple-location' ),
			)
		);
	}
}
