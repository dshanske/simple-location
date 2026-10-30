<?php
/**
 * Provider errors an administrator can fix.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Records provider errors an administrator can fix, such as a bad API key, and tells administrators about them.
 *
 * An error is recorded when a provider in a chain fails with one, and cleared the next time that provider answers.
 * Administrators see the recorded errors in a notice on the Dashboard and the Simple Location settings, which they
 * can dismiss, and in Site Health. Each new error, and each recovery, also fires an action with a one-line message,
 * for sending elsewhere, such as to a phone.
 *
 * @since 6.1.0
 */
class Sloc_Provider_Errors {

	/**
	 * Setting holding the recorded errors. Not autoloaded.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const OPTION = 'sloc_provider_errors';

	/**
	 * Least seconds between updates of a recorded error that keeps happening, to limit writes.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const THROTTLE = 60;

	/**
	 * Error codes an administrator can fix, whichever provider returns them.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const CODES = array(
		'missing_api_key',
		'no_username_set',
		'missing_username',
		'missing_pelias_url',
		'station_format',
		'station_format_version',
		'station_empty',
		'station_not_found',
		'station_stale',
		'station_health',
	);

	/**
	 * HTTP statuses from a service that an administrator can fix: a bad key, no access, payment, or quota.
	 *
	 * @since 6.1.0
	 * @var int[]
	 */
	const STATUSES = array( 401, 402, 403, 429 );

	/**
	 * Hooks the notices, the dismiss action and the Site Health test.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_post_sloc_dismiss_provider_errors', array( __CLASS__, 'handle_dismiss' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'site_status_tests' ) );
	}

	/**
	 * Returns whether an error is one an administrator can fix.
	 *
	 * Missing settings, a bad key, no access, payment or quota, and, for the site's own stations, any failure to read
	 * the station, since the administrator runs it.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_Error      $error    Error.
	 * @param Sloc_Provider $provider Provider that returned it.
	 * @return bool Whether to record it.
	 */
	public static function is_actionable( $error, $provider ) {
		$code       = $error->get_error_code();
		$data       = $error->get_error_data();
		$status     = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
		$actionable = in_array( $code, self::CODES, true )
			|| ( 'invalid_response' === $code && in_array( $status, self::STATUSES, true ) )
			|| ( $provider instanceof Weather_Provider_Station && in_array( $code, array( 'invalid_response', 'not_json_response', 'unexpected_response', 'http_request_failed' ), true ) );

		/**
		 * Filters whether a provider error is recorded for administrators to fix.
		 *
		 * @since 6.1.0
		 *
		 * @param bool          $actionable Whether to record it.
		 * @param WP_Error      $error      Error.
		 * @param Sloc_Provider $provider   Provider that returned it.
		 */
		return (bool) apply_filters( 'sloc_provider_error_actionable', $actionable, $error, $provider );
	}

	/**
	 * Returns the recorded errors.
	 *
	 * @since 6.1.0
	 *
	 * @return array[] Errors keyed by type and provider slug, such as 'weather:openweathermap'. See record().
	 */
	public static function get_errors() {
		$errors = get_option( self::OPTION );
		return is_array( $errors ) ? array_filter( $errors, 'is_array' ) : array();
	}

	/**
	 * Records an error from a provider, if it is one an administrator can fix.
	 *
	 * A new error, or one with a different code than the one recorded, fires `sloc_provider_error` and shows the
	 * notice again, even if it was dismissed.
	 *
	 * @since 6.1.0
	 *
	 * @param string        $type     Provider type, such as 'weather'.
	 * @param Sloc_Provider $provider Provider.
	 * @param WP_Error      $error    Error.
	 * @return bool Whether it was recorded.
	 */
	public static function record( $type, $provider, $error ) {
		if ( ! is_wp_error( $error ) || ! self::is_actionable( $error, $provider ) ) {
			return false;
		}
		$errors = self::get_errors();
		$key    = $type . ':' . $provider->get_slug();
		$now    = time();
		$code   = (string) $error->get_error_code();
		$old    = $errors[ $key ] ?? null;
		$new    = ! $old || $old['code'] !== $code;
		if ( ! $new && ( $now - (int) $old['last'] ) < self::THROTTLE ) {
			return true;
		}
		$errors[ $key ] = array(
			'type'      => $type,
			'provider'  => $provider->get_slug(),
			'name'      => wp_strip_all_tags( $provider->get_name() ),
			'code'      => $code,
			'message'   => wp_strip_all_tags( $error->get_error_message() ),
			'first'     => $new ? $now : (int) $old['first'],
			'last'      => $now,
			'count'     => $new ? 1 : (int) $old['count'] + 1,
			'dismissed' => $new ? false : ! empty( $old['dismissed'] ),
		);
		update_option( self::OPTION, $errors, false );
		if ( $new ) {
			/**
			 * Fires when a provider starts failing with an error an administrator can fix.
			 *
			 * @since 6.1.0
			 *
			 * @param string   $message One line describing the error, for a notification.
			 * @param array    $record  The recorded error: type, provider slug, provider name, code, message, first and last seen as timestamps, count of times seen at most once a minute, and dismissed.
			 * @param WP_Error $error   Error.
			 */
			do_action( 'sloc_provider_error', self::message( $errors[ $key ] ), $errors[ $key ], $error );
		}
		return true;
	}

	/**
	 * Clears a provider's recorded error, after it answered.
	 *
	 * @since 6.1.0
	 *
	 * @param string        $type     Provider type.
	 * @param Sloc_Provider $provider Provider.
	 * @return bool Whether an error was cleared.
	 */
	public static function resolve( $type, $provider ) {
		$errors = self::get_errors();
		$key    = $type . ':' . $provider->get_slug();
		if ( ! isset( $errors[ $key ] ) ) {
			return false;
		}
		$record = $errors[ $key ];
		unset( $errors[ $key ] );
		update_option( self::OPTION, $errors, false );

		/**
		 * Fires when a provider with a recorded error answers again.
		 *
		 * @since 6.1.0
		 *
		 * @param string $message One line saying the provider works again, for a notification.
		 * @param array  $record  The error that was recorded. See `sloc_provider_error`.
		 */
		do_action( 'sloc_provider_error_resolved', self::message( $record, true ), $record );
		return true;
	}

	/**
	 * Returns one line about a recorded error, or the provider's recovery from it.
	 *
	 * @since 6.1.0
	 * @since 6.3.0 A station's health warnings, of type health, say so; see Weather_Provider_Weewx::record_health().
	 *
	 * @param array $record   Recorded error.
	 * @param bool  $resolved Whether the provider works again. Default false.
	 * @return string Plain text, without line breaks.
	 */
	public static function message( $record, $resolved = false ) {
		$types = array(
			'weather'   => __( 'weather', 'simple-location' ),
			'geo'       => __( 'geocoding', 'simple-location' ),
			'elevation' => __( 'elevation', 'simple-location' ),
			'venue'     => __( 'venue', 'simple-location' ),
		);
		$type  = $types[ $record['type'] ] ?? $record['type'];
		if ( 'health' === $record['type'] ) {
			$message = $resolved
				/* translators: %s: Provider name, such as WeeWX. */
				? sprintf( __( 'Simple Location: the %s station warnings have cleared.', 'simple-location' ), $record['name'] )
				/* translators: 1: Provider name, such as WeeWX 2: Station and its warnings, such as Backyard: Wind sensor battery low. */
				: sprintf( __( 'Simple Location: a %1$s station needs attention: %2$s', 'simple-location' ), $record['name'], $record['message'] );
		} elseif ( $resolved ) {
			/* translators: 1: Provider name 2: Kind of lookup, such as weather. */
			$message = sprintf( __( 'Simple Location: %1$s %2$s lookups work again.', 'simple-location' ), $record['name'], $type );
		} else {
			/* translators: 1: Provider name 2: Kind of lookup, such as weather 3: Error message. */
			$message = sprintf( __( 'Simple Location: %1$s %2$s lookups are failing: %3$s', 'simple-location' ), $record['name'], $type, $record['message'] );
		}
		$message = trim( preg_replace( '/\s+/', ' ', $message ) );

		/**
		 * Filters the one-line message about a provider error, or the provider's recovery.
		 *
		 * @since 6.1.0
		 *
		 * @param string $message  Plain text, on one line.
		 * @param array  $record   Recorded error. See `sloc_provider_error`.
		 * @param bool   $resolved Whether the provider works again.
		 */
		return (string) apply_filters( 'sloc_provider_error_message', $message, $record, $resolved );
	}

	/**
	 * Shows administrators the errors they haven't dismissed, on the Dashboard and the Simple Location settings.
	 *
	 * @since 6.1.0
	 * @since 6.2.0 The notice is printed with wp_admin_notice().
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( 'dashboard' !== $screen->id && false === strpos( $screen->id, 'simloc' ) ) ) {
			return;
		}
		$errors = array_filter( self::get_errors(), fn( $record ) => empty( $record['dismissed'] ) );
		if ( empty( $errors ) ) {
			return;
		}
		$items = '';
		foreach ( $errors as $record ) {
			$items .= sprintf( '<li>%s</li>', esc_html( self::describe( $record ) ) );
		}
		wp_admin_notice(
			sprintf(
				'<p><strong>%1$s</strong></p><ul>%2$s</ul><p><a href="%3$s">%4$s</a> | <a href="%5$s">%6$s</a></p>',
				esc_html__( 'Simple Location providers need attention', 'simple-location' ),
				$items,
				esc_url( admin_url( 'admin.php?page=simloc&tab=api' ) ),
				esc_html__( 'Check the settings', 'simple-location' ),
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sloc_dismiss_provider_errors' ), 'sloc_dismiss_provider_errors' ) ),
				esc_html__( 'Dismiss', 'simple-location' )
			),
			array(
				'type'           => 'error',
				'paragraph_wrap' => false,
			)
		);
	}

	/**
	 * Describes a recorded error for administrators: the message, how often, and since when.
	 *
	 * @since 6.1.0
	 *
	 * @param array $record Recorded error.
	 * @return string Plain text.
	 */
	public static function describe( $record ) {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return sprintf(
			/* translators: 1: One-line error message 2: Times seen 3: Date and time first seen 4: Date and time last seen. */
			_n( '%1$s (%2$d time, from %3$s to %4$s)', '%1$s (%2$d times, from %3$s to %4$s)', (int) $record['count'], 'simple-location' ),
			self::message( $record ),
			(int) $record['count'],
			wp_date( $format, (int) $record['first'] ),
			wp_date( $format, (int) $record['last'] )
		);
	}

	/**
	 * Dismisses the recorded errors' notice, until a new error.
	 *
	 * @since 6.1.0
	 */
	public static function handle_dismiss() {
		check_admin_referer( 'sloc_dismiss_provider_errors' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to dismiss this notice.', 'simple-location' ), '', array( 'response' => 403 ) );
		}
		self::dismiss();
		$redirect = wp_get_referer();
		wp_safe_redirect( $redirect ? $redirect : admin_url() );
		exit;
	}

	/**
	 * Marks every recorded error dismissed.
	 *
	 * @since 6.1.0
	 */
	public static function dismiss() {
		$errors = self::get_errors();
		foreach ( $errors as $key => $record ) {
			$errors[ $key ]['dismissed'] = true;
		}
		update_option( self::OPTION, $errors, false );
	}

	/**
	 * Adds the providers test to Site Health.
	 *
	 * @since 6.1.0
	 *
	 * @param array $tests Site Health tests.
	 * @return array Tests.
	 */
	public static function site_status_tests( $tests ) {
		$tests['direct']['sloc_providers'] = array(
			'label' => __( 'Simple Location providers', 'simple-location' ),
			'test'  => array( __CLASS__, 'site_health_test' ),
		);
		return $tests;
	}

	/**
	 * Site Health test: whether any provider has a recorded error, dismissed or not.
	 *
	 * @since 6.1.0
	 *
	 * @return array Test result.
	 */
	public static function site_health_test() {
		$errors = self::get_errors();
		$result = array(
			'label'       => __( 'Simple Location providers are working', 'simple-location' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Simple Location', 'simple-location' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'No provider has failed with an error that needs your attention.', 'simple-location' ) . '</p>',
			'actions'     => '',
			'test'        => 'sloc_providers',
		);
		if ( empty( $errors ) ) {
			return $result;
		}
		$items = '';
		foreach ( $errors as $record ) {
			$items .= '<li>' . esc_html( self::describe( $record ) ) . '</li>';
		}
		$result['label']       = __( 'Simple Location providers need attention', 'simple-location' );
		$result['status']      = 'recommended';
		$result['badge']['color'] = 'orange';
		$result['description'] = '<p>' . esc_html__( 'These providers failed with errors you can fix, such as a missing or rejected API key. Other providers in the chain were tried instead. Each one clears when the provider works again.', 'simple-location' ) . '</p><ul>' . $items . '</ul>';
		$result['actions']     = sprintf( '<p><a href="%1$s">%2$s</a></p>', esc_url( admin_url( 'admin.php?page=simloc&tab=api' ) ), esc_html__( 'Check the settings', 'simple-location' ) );
		return $result;
	}
}

add_action( 'init', array( 'Sloc_Provider_Errors', 'init' ) );
