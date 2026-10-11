<?php
/**
 * Tests for Location_Plugins: the Micropub and Webmention integrations.
 *
 * The Micropub and Webmention plugins aren't loaded; their hooks are called directly with the
 * arguments those plugins pass.
 *
 * @package Simple_Location
 */

/**
 * Micropub and Webmention integration tests.
 */
class MicropubTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Known providers for every test.
	 */
	public function set_up() {
		parent::set_up();
		Geo_Provider_Sloc_Test::reset();
		Weather_Provider_Sloc_Test::reset();
		Geo_Provider_Sloc_Test::$reverse = array(
			'display-name' => 'Philadelphia City Hall',
			'locality'     => 'Philadelphia',
			'region'       => 'Pennsylvania',
			'region-code'  => 'PA',
			'country-name' => 'United States',
			'country-code' => 'US',
		);
		update_option( 'sloc_geo_provider', 'sloc_test' );
		update_option( 'sloc_elevation_provider', 'sloc_test' );
		update_option( 'sloc_weather_provider', 'none' );
		update_option( 'timezone_string', 'America/New_York' );
	}

	/**
	 * Creates a post the way Micropub does, with its meta already saved.
	 *
	 * @param array $meta Meta input.
	 * @return array Micropub arguments, with 'ID' and 'meta_input'.
	 */
	protected function micropub_post( $meta ) {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::factory()->user->create( array( 'role' => 'author' ) ),
				'meta_input'  => $meta,
			)
		);
		return array(
			'ID'         => $post_id,
			'meta_input' => $meta,
		);
	}

	/**
	 * A fake Webmention handler whose item has the given raw microformats.
	 *
	 * @param array|null $raw Raw mf2, or null for no item.
	 * @return object
	 */
	protected function webmention_handler( $raw ) {
		return new class( $raw ) {
			/**
			 * Raw mf2.
			 *
			 * @var array|null
			 */
			private $raw;

			/**
			 * Constructor.
			 *
			 * @param array|null $raw Raw mf2.
			 */
			public function __construct( $raw ) {
				$this->raw = $raw;
			}

			/**
			 * The webmention item.
			 *
			 * @return object|null
			 */
			public function get_webmention_item() {
				if ( is_null( $this->raw ) ) {
					return null;
				}
				$raw = $this->raw;
				return new class( $raw ) {
					/**
					 * Raw mf2.
					 *
					 * @var array
					 */
					private $raw;

					/**
					 * Constructor.
					 *
					 * @param array $raw Raw mf2.
					 */
					public function __construct( $raw ) {
						$this->raw = $raw;
					}

					/**
					 * Raw mf2.
					 *
					 * @return array
					 */
					public function get_raw() {
						return $this->raw;
					}
				};
			}

			/**
			 * First item of a list.
			 *
			 * @param mixed $value Value.
			 * @return mixed
			 */
			public function get_first_array_item( $value ) {
				return is_array( $value ) ? reset( $value ) : $value;
			}
		};
	}

	/**
	 * The integration hooks are registered.
	 */
	public function test_hooks() {
		$this->assertSame( 10, has_filter( 'before_micropub', array( 'Location_Plugins', 'micropub_lookup_location' ) ) );
		$this->assertSame( 10, has_action( 'after_micropub', array( 'Location_Plugins', 'micropub_set_location' ) ) );
		$this->assertSame( 10, has_filter( 'micropub_query', array( 'Location_Plugins', 'micropub_query_geo' ) ) );
		$this->assertSame( 10, has_filter( 'webmention_handler_mf2_set_properties', array( 'Location_Plugins', 'webmention_handler_mf2_set_properties' ) ) );
	}

	/**
	 * Values are read from arrays, taking the first item of lists unless told not to.
	 */
	public function test_array_get() {
		$this->assertSame( 'a', Location_Plugins::array_get( array( 'k' => array( 'a', 'b' ) ), 'k' ) );
		$this->assertSame( array( 'a', 'b' ), Location_Plugins::array_get( array( 'k' => array( 'a', 'b' ) ), 'k', array(), false ) );
		$this->assertSame( 'v', Location_Plugins::array_get( array( 'k' => 'v' ), 'k' ) );
		$this->assertSame( array( 'x' => 1 ), Location_Plugins::array_get( array( 'k' => array( 'x' => 1 ) ), 'k' ) );
		$this->assertSame( 'default', Location_Plugins::array_get( array(), 'k', 'default' ) );
		$this->assertSame( 'default', Location_Plugins::array_get( 'not an array', 'k', 'default' ) );
	}

	/**
	 * Posts that already have a location, or sites without automatic lookup, are left alone.
	 */
	public function test_lookup_location_skipped() {
		$this->assertSame( array( 'type' => array( 'h-entry' ) ), Location_Plugins::micropub_lookup_location( array( 'type' => array( 'h-entry' ) ) ) );

		update_option( 'sloc_auto_micropub', 1 );
		update_option( 'sloc_geolocation_provider', 'dummy' );
		foreach ( array( 'location', 'checkin' ) as $property ) {
			$input = array( 'properties' => array( $property => array( 'geo:1.5,2.5' ) ) );
			$this->assertSame( $input, Location_Plugins::micropub_lookup_location( $input ) );
		}

		$input = array( 'properties' => array( 'content' => array( 'Hello' ) ) );
		update_option( 'sloc_auto_micropub', 0 );
		$this->assertSame( $input, Location_Plugins::micropub_lookup_location( $input ) );

		update_option( 'sloc_auto_micropub', 1 );
		update_option( 'sloc_geolocation_provider', 'HTML5' );
		$this->assertSame( $input, Location_Plugins::micropub_lookup_location( $input ) );

		// Providers that need the user's input can't look up a location in the background.
		update_option( 'sloc_geolocation_provider', 'address' );
		$this->assertSame( $input, Location_Plugins::micropub_lookup_location( $input ) );
	}

	/**
	 * With automatic lookup, the author's current location is added as a private h-geo.
	 */
	public function test_lookup_location() {
		update_option( 'sloc_auto_micropub', 1 );
		update_option( 'sloc_geolocation_provider', 'dummy' );
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		Geo_Data::set_geodata(
			'user',
			$user_id,
			'',
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);

		$output = Location_Plugins::micropub_lookup_location( array( 'properties' => array( 'content' => array( 'Hello' ) ) ) );
		$this->assertSame( array( 'Hello' ), $output['properties']['content'] );
		$this->assertSame( array( 'private' ), $output['properties']['location-visibility'] );
		$this->assertSame( array( 'h-geo' ), $output['properties']['location'][0]['type'] );
		$this->assertEquals( array( self::LAT ), $output['properties']['location'][0]['properties']['latitude'] );
		$this->assertEquals( array( self::LNG ), $output['properties']['location'][0]['properties']['longitude'] );
	}

	/**
	 * Compass is asked for the location at the post's published time.
	 */
	public function test_lookup_location_published() {
		update_option( 'sloc_auto_micropub', 1 );
		update_option( 'sloc_geolocation_provider', 'compass' );
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		update_user_meta( $user_id, 'compass_url', 'https://compass.example.com' );
		update_user_meta( $user_id, 'compass_api', 'TOKEN' );
		wp_set_current_user( $user_id );
		$body = array(
			'data' => array(
				'type'       => 'Feature',
				'geometry'   => array(
					'type'        => 'Point',
					'coordinates' => array( self::LNG, self::LAT ),
				),
				'properties' => array( 'timestamp' => '2025-07-01T16:00:00Z' ),
			),
		);
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $body ) ) );

		$output = Location_Plugins::micropub_lookup_location( array( 'properties' => array( 'published' => array( '2025-07-01T12:00:00-04:00' ) ) ) );
		$this->assertSame( '2025-07-01T16:00:00', $this->last_request()['query']['before'] );
		$this->assertEquals( array( self::LAT ), $output['properties']['location'][0]['properties']['latitude'] );
	}

	/**
	 * Requests without meta or any location are ignored.
	 */
	public function test_set_location_skipped() {
		$post_id = self::factory()->post->create();
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), array( 'ID' => $post_id ) );
		Location_Plugins::micropub_set_location(
			array( 'properties' => array( 'content' => array( 'Hello' ) ) ),
			array(
				'ID'         => $post_id,
				'meta_input' => array(),
			)
		);
		$this->assertSame( array(), get_post_geodata( $post_id ) ?: array() ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$this->assertEmpty( Geo_Provider_Sloc_Test::$last );
	}

	/**
	 * Coordinates are made public, and the address, location term and elevation are looked up.
	 */
	public function test_set_location_coordinates() {
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
				'geo_accuracy'  => 500,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );

		$this->assertSame( array( self::LAT, self::LNG ), Geo_Provider_Sloc_Test::$last );
		$this->assertSame( 'public', get_post_geodata( $args['ID'], 'visibility' ) );
		$this->assertSame( 'Philadelphia City Hall', get_post_meta( $args['ID'], 'geo_address', true ) );
		$this->assertEquals( 50, get_post_meta( $args['ID'], 'geo_altitude', true ) );
		$this->assertEquals( 16, get_post_geodata( $args['ID'], 'zoom' ) );
		$this->assertSame( 'Philadelphia', Location_Taxonomy::get_post_location( $args['ID'] ) ? get_term( Location_Taxonomy::get_post_location( $args['ID'] ) )->name : '' );
	}

	/**
	 * An address sent with the post is kept.
	 */
	public function test_set_location_keeps_address() {
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
				'geo_address'   => 'My office',
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );
		$this->assertSame( 'My office', get_post_meta( $args['ID'], 'geo_address', true ) );
	}

	/**
	 * Weather is looked up for the published time.
	 */
	public function test_set_location_weather() {
		update_option( 'sloc_weather_provider', 'sloc_test' );
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array( 'published' => array( '2025-07-01T12:00:00-04:00' ) ) ), $args );
		$this->assertSame( strtotime( '2025-07-01T16:00:00Z' ), Weather_Provider_Sloc_Test::$time );
		$this->assertNotEmpty( get_post_weatherdata( $args['ID'] ) );
	}

	/**
	 * Coordinates at a venue link the venue and hide the address and coordinates.
	 */
	public function test_set_location_at_venue() {
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		$at_venue = function () use ( $venue_id ) {
			return $venue_id;
		};
		add_filter( 'pre_at_venue', $at_venue );
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );
		remove_filter( 'pre_at_venue', $at_venue );

		$this->assertEquals( $venue_id, get_post_meta( $args['ID'], 'venue_id', true ) );
		$this->assertSame( 'protected', get_post_geodata( $args['ID'], 'visibility' ) );
	}

	/**
	 * A check-in uses building-level zoom and creates a venue from the h-card.
	 */
	public function test_set_location_checkin() {
		$args  = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
			)
		);
		$input = array(
			'properties' => array(
				'checkin' => array(
					'type'       => array( 'h-card' ),
					'properties' => array(
						'name'         => array( 'Philadelphia City Hall' ),
						'latitude'     => array( self::LAT ),
						'longitude'    => array( self::LNG ),
						'locality'     => array( 'Philadelphia' ),
						'region'       => array( 'Pennsylvania' ),
						'country-name' => array( 'United States' ),
						'url'          => array( 'https://www.phila.gov/' ),
					),
				),
			),
		);
		Location_Plugins::micropub_set_location( $input, $args );

		$this->assertEquals( 18, get_post_geodata( $args['ID'], 'zoom' ) );
		$venue_id = get_post_meta( $args['ID'], 'venue_id', true );
		$this->assertNotEmpty( $venue_id );
		$this->assertSame( 'Philadelphia City Hall', get_the_title( $venue_id ) );
		$checkin = get_post_meta( $args['ID'], 'mf2_checkin', true );
		$this->assertSame( array( 'h-card' ), $checkin['type'] );
		$this->assertSame( array( 'Philadelphia City Hall' ), $checkin['properties']['name'] );
		$this->assertSame( array( get_permalink( $venue_id ) ), $checkin['properties']['url'] );
	}

	/**
	 * A location h-adr sets the location term without a reverse lookup.
	 */
	public function test_set_location_address() {
		$args  = $this->micropub_post( array() );
		$input = array(
			'properties' => array(
				'location' => array(
					'type'       => array( 'h-adr' ),
					'properties' => array(
						'locality'     => array( 'Philadelphia' ),
						'region'       => array( 'Pennsylvania' ),
						'region-code'  => array( 'PA' ),
						'country-name' => array( 'United States' ),
						'country-code' => array( 'US' ),
					),
				),
			),
		);
		Location_Plugins::micropub_set_location( $input, $args );
		$this->assertEmpty( Geo_Provider_Sloc_Test::$last );
		$term = Location_Taxonomy::get_post_location( $args['ID'] );
		$this->assertNotEmpty( $term );
		$this->assertSame( 'Philadelphia', get_term( $term )->name );
	}

	/**
	 * Location properties sent as latitude and longitude are reverse geocoded.
	 */
	public function test_set_location_properties() {
		$args = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location(
			array(
				'properties' => array(
					'latitude'  => array( self::LAT ),
					'longitude' => array( self::LNG ),
				),
			),
			$args
		);
		$this->assertSame( array( self::LAT, self::LNG ), Geo_Provider_Sloc_Test::$last );
		$this->assertSame( 'Philadelphia City Hall', get_post_meta( $args['ID'], 'geo_address', true ) );
	}

	/**
	 * Only geo queries are answered.
	 */
	public function test_query_other() {
		$this->assertSame( array( 'resp' ), Location_Plugins::micropub_query_geo( array( 'resp' ), array( 'q' => 'config' ) ) );
	}

	/**
	 * Webmentions without an item or location leave the meta alone.
	 */
	public function test_webmention_without_location() {
		$meta = array( 'key' => 'value' );
		$this->assertSame( $meta, Location_Plugins::webmention_handler_mf2_set_properties( $meta, $this->webmention_handler( null ) ) );
		$this->assertSame( $meta, Location_Plugins::webmention_handler_mf2_set_properties( $meta, $this->webmention_handler( array() ) ) );
		$this->assertSame( $meta, Location_Plugins::webmention_handler_mf2_set_properties( $meta, $this->webmention_handler( array( 'type' => array( 'h-entry' ) ) ) ) );
		$this->assertSame( $meta, Location_Plugins::webmention_handler_mf2_set_properties( $meta, $this->webmention_handler( array( 'properties' => array() ) ) ) );
	}

	/**
	 * A webmention's h-card location becomes the address and coordinates.
	 */
	public function test_webmention_hcard_location() {
		$raw  = array(
			'type'       => array( 'h-entry' ),
			'properties' => array(
				'location' => array(
					array(
						'type'       => array( 'h-card' ),
						'properties' => array(
							'name'         => array( 'Philadelphia City Hall' ),
							'locality'     => array( 'Philadelphia' ),
							'region'       => array( 'Pennsylvania' ),
							'country-name' => array( 'United States' ),
							'latitude'     => array( '39.9524' ),
							'longitude'    => array( '-75.1636' ),
							'altitude'     => array( '14' ),
						),
					),
				),
			),
		);
		$meta = Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) );
		$this->assertSame( 'Philadelphia City Hall, Philadelphia, Pennsylvania, United States', $meta['geo_address'] );
		$this->assertSame( '39.9524', $meta['geo_latitude'] );
		$this->assertSame( '-75.1636', $meta['geo_longitude'] );
		$this->assertSame( '14', $meta['geo_altitude'] );
		$this->assertArrayNotHasKey( 'geo_accuracy', $meta );
	}

	/**
	 * A location with a nested h-geo takes its coordinates from the h-geo.
	 */
	public function test_webmention_nested_geo() {
		$raw  = array(
			'properties' => array(
				'location' => array(
					array(
						'type'       => array( 'h-card' ),
						'properties' => array(
							'name' => array( 'Somewhere' ),
							'geo'  => array(
								array(
									'type'       => array( 'h-geo' ),
									'properties' => array(
										'latitude'  => array( '39.9524' ),
										'longitude' => array( '-75.1636' ),
										'accuracy'  => array( '25' ),
									),
								),
							),
						),
					),
				),
			),
		);
		$meta = Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) );
		$this->assertSame( '39.9524', $meta['geo_latitude'] );
		$this->assertSame( '-75.1636', $meta['geo_longitude'] );
		$this->assertSame( '25', $meta['geo_accuracy'] );
		$this->assertArrayNotHasKey( 'geo_address', $meta );
	}
}
