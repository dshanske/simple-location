<?php
/**
 * Provider chains: up to three providers of a type, tried in order.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tries the providers of a type in the site's order until one answers.
 *
 * Each type has its provider setting, which is the first in the chain, and a setting with up to two more. A provider
 * is skipped without a request when the location is outside the area it covers, or, for weather, when it has no
 * weather for the requested time. Any error moves on to the next provider; errors an administrator can fix are
 * recorded by Sloc_Provider_Errors.
 *
 * @since 6.1.0
 */
class Sloc_Provider_Chain {

	/**
	 * Most providers in a chain: the provider and two fallbacks.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX = 3;

	/**
	 * Settings of each type that can be chained: the provider's setting, and the setting with its fallbacks.
	 *
	 * Maps are never chained: the map provider decides how maps look.
	 *
	 * @since 6.1.0
	 * @var array[]
	 */
	const TYPES = array(
		'weather'   => array(
			'option'    => 'sloc_weather_provider',
			'fallbacks' => 'sloc_weather_fallbacks',
		),
		'geo'       => array(
			'option'    => 'sloc_geo_provider',
			'fallbacks' => 'sloc_geo_fallbacks',
		),
		'elevation' => array(
			'option'    => 'sloc_elevation_provider',
			'fallbacks' => 'sloc_elevation_fallbacks',
		),
	);

	/**
	 * Returns the slugs of a type's chain, in order, without unknown providers or repeats.
	 *
	 * @since 6.1.0
	 *
	 * @param string $type Provider type, a key of TYPES.
	 * @return string[] Provider slugs.
	 */
	public static function slugs( $type ) {
		$slugs = self::configured( $type );
		$known = self::known( $type );
		$slugs     = array_values( array_unique( array_filter( $slugs, fn( $slug ) => is_string( $slug ) && isset( $known[ $slug ] ) ) ) );

		/**
		 * Filters the providers a lookup tries, in order.
		 *
		 * @since 6.1.0
		 *
		 * @param string[] $slugs Provider slugs, at most three.
		 * @param string   $type  Provider type, such as 'weather'.
		 */
		$slugs = apply_filters( 'sloc_provider_chain', $slugs, $type );
		return array_slice( array_values( array_filter( (array) $slugs, fn( $slug ) => is_string( $slug ) && isset( $known[ $slug ] ) ) ), 0, self::MAX );
	}

	/**
	 * Returns the slugs saved in a type's settings, in order, unchecked.
	 *
	 * For a provider's is_active(), which runs while providers are still being registered.
	 *
	 * @since 6.1.0
	 *
	 * @param string $type Provider type, a key of TYPES.
	 * @return array Saved values: the provider setting, then the fallbacks.
	 */
	public static function configured( $type ) {
		if ( ! isset( self::TYPES[ $type ] ) ) {
			return array();
		}
		$fallbacks = get_option( self::TYPES[ $type ]['fallbacks'] );
		return array_merge( array( get_option( self::TYPES[ $type ]['option'] ) ), is_array( $fallbacks ) ? $fallbacks : array() );
	}

	/**
	 * Returns the registered providers of a type, keyed by slug.
	 *
	 * @since 6.1.0
	 *
	 * @param string $type Provider type.
	 * @return array Providers' details, keyed by slug.
	 */
	protected static function known( $type ) {
		switch ( $type ) {
			case 'weather':
				return Loc_Config::weather_providers();
			case 'geo':
				return Loc_Config::geo_providers();
			case 'elevation':
				return Loc_Config::elevation_providers();
		}
		return array();
	}

	/**
	 * Returns a provider of a type by slug.
	 *
	 * @since 6.1.0
	 *
	 * @param string $type Provider type.
	 * @param string $slug Provider slug.
	 * @return Sloc_Provider|null Provider.
	 */
	protected static function provider( $type, $slug ) {
		switch ( $type ) {
			case 'weather':
				return Loc_Config::weather_provider( $slug );
			case 'geo':
				return Loc_Config::geo_provider( $slug );
			case 'elevation':
				return Loc_Config::elevation_provider( $slug );
		}
		return null;
	}

	/**
	 * Tries each provider of a type's chain until one answers.
	 *
	 * @since 6.1.0
	 *
	 * @param string   $type    Provider type, a key of TYPES.
	 * @param callable $attempt Receives a provider, set to the location, and returns its answer or a WP_Error.
	 * @param array    $args {
	 *     Lookup.
	 *
	 *     @type float           $latitude  Latitude. Optional, such as for geocoding an address.
	 *     @type float           $longitude Longitude. Optional.
	 *     @type float|null      $altitude  Altitude. Optional.
	 *     @type int|string|null $time      For weather, the time to look up. Optional; defaults to now.
	 * }
	 * @return mixed The first answer; otherwise the last error, or the last empty answer when no provider tried
	 *               returned an error. A `no_provider` error when the chain is empty, and `out_of_range` when every
	 *               provider was skipped.
	 */
	public static function run( $type, $attempt, $args ) {
		$slugs = self::slugs( $type );
		if ( empty( $slugs ) ) {
			return new WP_Error( 'no_provider', __( 'No provider is set.', 'simple-location' ) );
		}
		$time  = $args['time'] ?? null;
		$error = null;
		$empty = null;
		$tried = false;
		foreach ( $slugs as $slug ) {
			$provider = self::provider( $type, $slug );
			if ( ! $provider instanceof Sloc_Provider ) {
				continue;
			}
			// A copy, so the lookup leaves the shared provider as it was, such as its cache time or a station.
			$provider = clone $provider;
			if ( $provider instanceof Weather_Provider ) {
				$provider->set( array( 'station_id' => '' ) );
			}
			// Without a location, such as for an address, every provider is tried.
			if ( isset( $args['latitude'], $args['longitude'] ) ) {
				$provider->set( $args['latitude'], $args['longitude'], $args['altitude'] ?? null );
				if ( ! $provider->covers() ) {
					continue;
				}
			}
			if ( $provider instanceof Weather_Provider && ! $provider->supports_time( $time ) ) {
				continue;
			}
			$tried  = true;
			$result = call_user_func( $attempt, $provider );
			if ( is_wp_error( $result ) ) {
				Sloc_Provider_Errors::record( $type, $provider, $result );
				$error = $result;
				continue;
			}
			if ( empty( $result ) && ! is_numeric( $result ) ) {
				$empty = $result;
				continue;
			}
			Sloc_Provider_Errors::resolve( $type, $provider );
			return $result;
		}
		if ( $error ) {
			return $error;
		}
		if ( $tried ) {
			return $empty;
		}
		return new WP_Error( 'out_of_range', __( 'No provider covers this location and time.', 'simple-location' ) );
	}

	/**
	 * Looks up the weather at a location with the weather chain.
	 *
	 * @since 6.1.0
	 *
	 * @param float               $latitude   Latitude.
	 * @param float               $longitude  Longitude.
	 * @param int|string|DateTime $time       Time to look up. Optional; defaults to now.
	 * @param int|null            $cache_time Seconds to cache the result. Optional; defaults to each provider's setting.
	 * @return array|WP_Error Conditions, with the slug of the provider that answered as 'provider', or the last error.
	 */
	public static function weather( $latitude, $longitude, $time = null, $cache_time = null ) {
		return self::run(
			'weather',
			function ( $provider ) use ( $time, $cache_time ) {
				if ( is_numeric( $cache_time ) ) {
					$provider->set_cache_time( $cache_time );
				}
				$conditions = $provider->get_conditions( $time );
				if ( is_array( $conditions ) && ! empty( $conditions ) && empty( $conditions['provider'] ) ) {
					$conditions['provider'] = $provider->get_slug();
				}
				return $conditions;
			},
			array(
				'latitude'  => $latitude,
				'longitude' => $longitude,
				'time'      => $time instanceof DateTimeInterface ? $time->getTimestamp() : $time,
			)
		);
	}

	/**
	 * Looks up the address of a location with the geocoding chain.
	 *
	 * @since 6.1.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return array|WP_Error Address, or the last error.
	 */
	public static function reverse_lookup( $latitude, $longitude ) {
		return self::run(
			'geo',
			fn( $provider ) => $provider->reverse_lookup(),
			array(
				'latitude'  => $latitude,
				'longitude' => $longitude,
			)
		);
	}

	/**
	 * Looks up the location of an address with the geocoding chain.
	 *
	 * @since 6.1.0
	 *
	 * @param string $address Address.
	 * @return array|WP_Error Location, or the last error.
	 */
	public static function geocode( $address ) {
		return self::run( 'geo', fn( $provider ) => $provider->geocode( $address ), array() );
	}

	/**
	 * Looks up the elevation of a location with the elevation chain.
	 *
	 * @since 6.1.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return float|WP_Error Elevation in meters, or the last error.
	 */
	public static function elevation( $latitude, $longitude ) {
		$elevation = self::run(
			'elevation',
			fn( $provider ) => $provider->elevation(),
			array(
				'latitude'  => $latitude,
				'longitude' => $longitude,
			)
		);
		if ( is_wp_error( $elevation ) && 'no_provider' === $elevation->get_error_code() ) {
			return new WP_Error( 'no_elevation_provider', __( 'No elevation provider is set', 'simple-location' ) );
		}
		if ( ! is_numeric( $elevation ) && ! is_wp_error( $elevation ) ) {
			return new WP_Error( 'no_elevation_data', __( 'No elevation was found', 'simple-location' ) );
		}
		return $elevation;
	}

	/**
	 * Sanitizes a fallbacks setting: up to two known provider slugs, without repeats or None.
	 *
	 * @since 6.1.0
	 *
	 * @param string $type  Provider type.
	 * @param mixed  $value Slugs.
	 * @return string[] Slugs.
	 */
	public static function sanitize_fallbacks( $type, $value ) {
		$known = self::known( $type );
		$slugs = array();
		foreach ( is_array( $value ) ? $value : array() as $slug ) {
			$slug = is_string( $slug ) ? sanitize_key( $slug ) : '';
			if ( isset( $known[ $slug ] ) && ! in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
		}
		return array_slice( $slugs, 0, self::MAX - 1 );
	}

	/**
	 * Sanitizes the weather fallbacks setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Slugs.
	 * @return string[] Slugs.
	 */
	public static function sanitize_weather_fallbacks( $value ) {
		return self::sanitize_fallbacks( 'weather', $value );
	}

	/**
	 * Sanitizes the geocoding fallbacks setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Slugs.
	 * @return string[] Slugs.
	 */
	public static function sanitize_geo_fallbacks( $value ) {
		return self::sanitize_fallbacks( 'geo', $value );
	}

	/**
	 * Sanitizes the elevation fallbacks setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Slugs.
	 * @return string[] Slugs.
	 */
	public static function sanitize_elevation_fallbacks( $value ) {
		return self::sanitize_fallbacks( 'elevation', $value );
	}

	/**
	 * Echoes the fallback selects of a type: one per place in the chain after the first.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args {
	 *     Arguments.
	 *
	 *     @type string $label_for   Setting name.
	 *     @type string $type        Provider type.
	 *     @type string $description Description.
	 * }
	 */
	public static function fallbacks_callback( $args ) {
		$name      = $args['label_for'];
		$current   = get_option( $name );
		$current   = is_array( $current ) ? array_values( $current ) : array();
		$providers = self::known( $args['type'] );
		ksort( $providers );
		$places = array(
			__( '2nd', 'simple-location' ),
			__( '3rd', 'simple-location' ),
		);
		foreach ( $places as $index => $place ) {
			$selected = $current[ $index ] ?? 'none';
			printf( '<label>%1$s <select name="%2$s[]"%3$s>', esc_html( $place ), esc_attr( $name ), 0 === $index ? ' id="' . esc_attr( $name ) . '"' : '' );
			printf( '<option value="none"%1$s>%2$s</option>', selected( $selected, 'none', false ), esc_html__( 'None', 'simple-location' ) );
			foreach ( $providers as $slug => $provider ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $slug ), selected( $selected, $slug, false ), esc_html( is_array( $provider ) ? $provider['name'] : $provider ) );
			}
			echo '</select></label> ';
		}
		echo '<p class="description">' . esc_html( $args['description'] ?? '' ) . '</p>';
	}

	/**
	 * Moves the single fallback weather provider setting from before 6.1.0 into the fallbacks.
	 *
	 * @since 6.1.0
	 */
	public static function migrate() {
		$old = get_option( 'sloc_fallback_weather_provider', null );
		if ( null === $old ) {
			return;
		}
		if ( null === get_option( 'sloc_weather_fallbacks', null ) ) {
			update_option( 'sloc_weather_fallbacks', ( is_string( $old ) && '' !== $old && 'none' !== $old ) ? array( $old ) : array() );
		}
		delete_option( 'sloc_fallback_weather_provider' );
	}
}
