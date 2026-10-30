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
		$this->assertSame( 10, has_filter( 'micropub_query', array( 'Location_Plugins', 'micropub_query_places' ) ) );
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
	 * An altitude the client sent is kept; 0 or NaN is replaced by the elevation lookup.
	 *
	 * @dataProvider data_sent_altitude
	 *
	 * @param mixed $sent     Altitude in the request.
	 * @param float $expected Stored altitude.
	 */
	public function test_set_location_sent_altitude( $sent, $expected ) {
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
				'geo_altitude'  => $sent,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );
		$this->assertEquals( $expected, get_post_meta( $args['ID'], 'geo_altitude', true ) );
	}

	/**
	 * Sent altitudes and the altitude that should be stored.
	 *
	 * @return array
	 */
	public function data_sent_altitude() {
		return array(
			'sent'       => array( 120, 120 ),
			'below sea'  => array( -10.5, -10.5 ),
			'zero'       => array( 0, 123 ),
			'not number' => array( 'NaN', 123 ),
		);
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
		$this->assertEquals( 123, get_post_meta( $args['ID'], 'geo_altitude', true ) );
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
	 * A failed weather lookup stores no weather, and the rest of the location is still saved.
	 */
	public function test_set_location_weather_error() {
		update_option( 'sloc_weather_provider', 'sloc_test' );
		Weather_Provider_Sloc_Test::$conditions = new WP_Error( 'http_request_failed', 'Weather service unavailable' );
		$args                                   = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );
		$this->assertEmpty( get_post_weatherdata( $args['ID'] ) );
		$this->assertSame( 'public', get_post_geodata( $args['ID'], 'visibility' ) );
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
	 * Coordinates at a venue that sets the visibility for posts made there use it, unless the client asked for one (#143).
	 */
	public function test_set_location_at_venue_post_visibility() {
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		update_post_meta( $venue_id, 'venue_post_visibility', 'private' );
		$at_venue = function () use ( $venue_id ) {
			return $venue_id;
		};
		add_filter( 'pre_at_venue', $at_venue );
		$meta = array(
			'geo_latitude'  => self::LAT,
			'geo_longitude' => self::LNG,
		);
		$args = $this->micropub_post( $meta );
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );
		$this->assertSame( 'private', get_post_geodata( $args['ID'], 'visibility' ) );

		$args = $this->micropub_post( $meta );
		Location_Plugins::micropub_set_location( array( 'properties' => array( 'location-visibility' => array( 'public' ) ) ), $args );
		$this->assertSame( 'public', get_post_geodata( $args['ID'], 'visibility' ) );
		remove_filter( 'pre_at_venue', $at_venue );
	}

	/**
	 * A check-in at an existing venue that sets the visibility for posts made there uses it.
	 */
	public function test_set_location_checkin_post_visibility() {
		$venue_id = self::factory()->post->create(
			array(
				'post_type'  => 'venue',
				'post_title' => 'Home',
			)
		);
		update_post_meta( $venue_id, 'venue_url', 'https://home.example.com/' );
		set_post_geodata( $venue_id, 'visibility', 'public' );
		update_post_meta( $venue_id, 'venue_post_visibility', 'protected' );
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
						'name'      => array( 'Home' ),
						'latitude'  => array( self::LAT ),
						'longitude' => array( self::LNG ),
						'url'       => array( 'https://home.example.com/' ),
					),
				),
			),
		);
		Location_Plugins::micropub_set_location( $input, $args );
		$this->assertEquals( $venue_id, get_post_meta( $args['ID'], 'venue_id', true ) );
		$this->assertSame( 'protected', get_post_geodata( $args['ID'], 'visibility' ) );
	}

	/**
	 * Data for a requested location-visibility, with and without a venue.
	 *
	 * @return array Visibility and whether the post is at a venue.
	 */
	public function data_requested_visibility() {
		return array(
			'private'            => array( 'private', false ),
			'protected'          => array( 'protected', false ),
			'public'             => array( 'public', false ),
			'private at a venue' => array( 'private', true ),
			'public at a venue'  => array( 'public', true ),
		);
	}

	/**
	 * A location-visibility sent by the client is used, with or without a venue.
	 *
	 * @dataProvider data_requested_visibility
	 *
	 * @param string $visibility Visibility sent by the client.
	 * @param bool   $at_venue   Whether the coordinates are at a venue.
	 */
	public function test_set_location_uses_requested_visibility( $visibility, $at_venue ) {
		$venue = function () use ( $at_venue ) {
			return $at_venue ? self::factory()->post->create( array( 'post_type' => 'venue' ) ) : false;
		};
		add_filter( 'pre_at_venue', $venue );
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array( 'location-visibility' => array( $visibility ) ) ), $args );
		remove_filter( 'pre_at_venue', $venue );

		$this->assertSame( $visibility, get_post_geodata( $args['ID'], 'visibility' ) );
	}

	/**
	 * Only the last location-visibility value counts, and an unknown value falls back to the default.
	 */
	public function test_set_location_visibility_values() {
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array( 'location-visibility' => array( 'public', 'private' ) ) ), $args );
		$this->assertSame( 'private', get_post_geodata( $args['ID'], 'visibility' ) );

		Location_Plugins::micropub_set_location( array( 'properties' => array( 'location-visibility' => 'protected' ) ), $args );
		$this->assertSame( 'protected', get_post_geodata( $args['ID'], 'visibility' ) );

		Location_Plugins::micropub_set_location( array( 'properties' => array( 'location-visibility' => array( 'secret' ) ) ), $args );
		$this->assertSame( 'public', get_post_geodata( $args['ID'], 'visibility' ) );
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
	 * A check-in keeps the venue's identifier and provider, and a second check-in there finds the venue (#729).
	 */
	public function test_set_location_checkin_uid() {
		$properties = array(
			'name'      => array( 'Philadelphia City Hall' ),
			'latitude'  => array( self::LAT ),
			'longitude' => array( self::LNG ),
			'uid'       => array( 'https://www.openstreetmap.org/way/31414402' ),
			'provider'  => array( 'overpass' ),
		);
		$input      = array(
			'properties' => array(
				'checkin' => array(
					'type'       => array( 'h-card' ),
					'properties' => $properties,
				),
			),
		);
		$args       = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location( $input, $args );
		$venue_id = (int) get_post_meta( $args['ID'], 'venue_id', true );
		$sources  = Post_Venue::get_venue_sources( $venue_id );
		$this->assertSame( 'overpass', $sources[0]['provider'] );
		$this->assertSame( 'https://www.openstreetmap.org/way/31414402', $sources[0]['uid'] );

		$input['properties']['checkin']['properties']['name'] = array( 'City Hall' );
		$second = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location( $input, $second );
		$this->assertSame( $venue_id, (int) get_post_meta( $second['ID'], 'venue_id', true ) );
	}

	/**
	 * Creates a venue at City Hall.
	 *
	 * @param string $title      Title.
	 * @param string $visibility Location visibility.
	 * @return int Venue ID.
	 */
	protected function venue( $title, $visibility = 'public' ) {
		$venue_id = self::factory()->post->create(
			array(
				'post_type'   => 'venue',
				'post_title'  => $title,
				'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ),
			)
		);
		set_post_geodata(
			$venue_id,
			'',
			array(
				'latitude'   => self::LAT,
				'longitude'  => self::LNG,
				'visibility' => $visibility,
			)
		);
		return $venue_id;
	}

	/**
	 * Micropub input with an h-card as the check-in or the location.
	 *
	 * @param string $property   'checkin' or 'location'.
	 * @param array  $properties h-card properties.
	 * @param array  $more       Other post properties.
	 * @return array Input.
	 */
	protected function card_input( $property, $properties, $more = array() ) {
		return array(
			'properties' => array_merge(
				array(
					$property => array(
						'type'       => array( 'h-card' ),
						'properties' => $properties,
					),
				),
				$more
			),
		);
	}

	/**
	 * Number of venues.
	 *
	 * @return int
	 */
	protected function venue_count() {
		return count(
			get_posts(
				array(
					'post_type'   => 'venue',
					'post_status' => 'any',
					'fields'      => 'ids',
					'numberposts' => -1,
				)
			)
		);
	}

	/**
	 * A check-in whose URL is a venue's page on the site, with plain permalinks, is that venue (#816).
	 */
	public function test_set_location_checkin_local_page() {
		$venue_id = $this->venue( 'Our Cafe' );
		$this->assertStringContainsString( '?venue=', get_permalink( $venue_id ) );
		$count = $this->venue_count();
		$args  = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location(
			$this->card_input(
				'checkin',
				array(
					'name' => array( 'Another Name' ),
					'url'  => array( get_permalink( $venue_id ) ),
				)
			),
			$args
		);
		$this->assertSame( $venue_id, (int) get_post_meta( $args['ID'], 'venue_id', true ) );
		$this->assertSame( $count, $this->venue_count(), 'No venue is created.' );
	}

	/**
	 * A check-in whose URL is another kind of post on the site isn't linked to it, and makes no venue (#816).
	 */
	public function test_set_location_checkin_other_post() {
		$post_id = self::factory()->post->create();
		$count   = $this->venue_count();
		$args    = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location(
			$this->card_input(
				'checkin',
				array(
					'name' => array( 'A post' ),
					'url'  => array( get_permalink( $post_id ) ),
				)
			),
			$args
		);
		$this->assertSame( '', get_post_meta( $args['ID'], 'venue_id', true ) );
		$this->assertSame( $count, $this->venue_count() );
		$this->assertNotSame(
			$post_id,
			Post_Venue::add_new_venue(
				array(
					'name' => 'A post',
					'url'  => get_permalink( $post_id ),
				)
			),
			'add_new_venue() never returns another kind of post.'
		);
	}

	/**
	 * A check-in at a private venue, by its identifier, is only linked for a user who can edit the venue (#816).
	 */
	public function test_set_location_checkin_private_venue() {
		$venue_id = $this->venue( 'Home', 'private' );
		add_post_meta( $venue_id, Post_Venue::UID_KEY, 'here:pds:place:home' );
		$input = $this->card_input(
			'checkin',
			array(
				'name'     => array( 'Home' ),
				'uid'      => array( 'here:pds:place:home' ),
				'provider' => array( 'sloc_test' ),
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$args = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location( $input, $args );
		$this->assertSame( '', get_post_meta( $args['ID'], 'venue_id', true ) );
		$this->assertSame( array(), Post_Venue::get_venue_sources( $venue_id ), 'The venue is left as it is.' );

		wp_set_current_user( get_post_field( 'post_author', $venue_id ) );
		$args = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location( $input, $args );
		$this->assertSame( $venue_id, (int) get_post_meta( $args['ID'], 'venue_id', true ) );
		wp_set_current_user( 0 );
	}

	/**
	 * A check-in named only by its address label makes a venue with that name.
	 */
	public function test_set_location_checkin_label() {
		$args = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location(
			$this->card_input(
				'checkin',
				array(
					'label'     => array( 'Corner Shop' ),
					'latitude'  => array( self::LAT ),
					'longitude' => array( self::LNG ),
				)
			),
			$args
		);
		$this->assertSame( 'Corner Shop', get_the_title( (int) get_post_meta( $args['ID'], 'venue_id', true ) ) );
	}

	/**
	 * A location h-card whose page or identifier is a venue links it, with its visibility for posts there (#816).
	 */
	public function test_set_location_card_venue() {
		$by_page = $this->venue( 'Our Cafe' );
		update_post_meta( $by_page, 'venue_post_visibility', 'private' );
		$by_uid = $this->venue( 'Our Bar' );
		add_post_meta( $by_uid, Post_Venue::UID_KEY, 'https://www.openstreetmap.org/node/7' );
		$count = $this->venue_count();

		$args = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location( $this->card_input( 'location', array( 'url' => array( get_permalink( $by_page ) ) ) ), $args );
		$this->assertSame( $by_page, (int) get_post_meta( $args['ID'], 'venue_id', true ) );
		$this->assertSame( 'private', get_post_geodata( $args['ID'], 'visibility' ) );

		$args = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location( $this->card_input( 'location', array( 'uid' => array( 'https://www.openstreetmap.org/node/7' ) ) ), $args );
		$this->assertSame( $by_uid, (int) get_post_meta( $args['ID'], 'venue_id', true ) );
		$this->assertSame( 'protected', get_post_geodata( $args['ID'], 'visibility' ), 'Only the name, without a visibility for posts there.' );

		// The visibility the client asked for wins.
		$args = $this->micropub_post( array() );
		Location_Plugins::micropub_set_location(
			$this->card_input(
				'location',
				array( 'url' => array( get_permalink( $by_page ) ) ),
				array( 'location-visibility' => array( 'public' ) )
			),
			$args
		);
		$this->assertSame( $by_page, (int) get_post_meta( $args['ID'], 'venue_id', true ) );
		$this->assertSame( 'public', get_post_geodata( $args['ID'], 'visibility' ) );
		$this->assertSame( $count, $this->venue_count(), 'A location never creates a venue.' );
	}

	/**
	 * A location h-card that isn't a venue, or is one the user may not use, links none and creates none (#816).
	 */
	public function test_set_location_card_no_venue() {
		$private = $this->venue( 'Home', 'private' );
		add_post_meta( $private, Post_Venue::UID_KEY, 'here:pds:place:home' );
		$count = $this->venue_count();
		foreach ( array( 'here:pds:place:unknown', 'here:pds:place:home' ) as $uid ) {
			$args = $this->micropub_post( array() );
			Location_Plugins::micropub_set_location(
				$this->card_input(
					'location',
					array(
						'name' => array( 'Somewhere' ),
						'uid'  => array( $uid ),
					)
				),
				$args
			);
			$this->assertSame( '', get_post_meta( $args['ID'], 'venue_id', true ), $uid );
		}
		$this->assertSame( $count, $this->venue_count() );
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
	 * A longitude without a latitude is ignored without a warning.
	 */
	public function test_set_location_longitude_only() {
		$args = $this->micropub_post( array( 'geo_longitude' => -75.16 ) );
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );
		$this->assertSame( '', get_post_meta( $args['ID'], 'geo_public', true ) );
	}

	/**
	 * A latitude of 0 is a real coordinate.
	 */
	public function test_set_location_zero_latitude() {
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => 0,
				'geo_longitude' => 10,
				'geo_accuracy'  => 500,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );
		$this->assertEquals( 16, get_post_geodata( $args['ID'], 'zoom' ) );
		$this->assertSame( '1', get_post_meta( $args['ID'], 'geo_public', true ) );
	}

	/**
	 * Without a geo provider, or when its lookup fails, the location is still saved.
	 */
	public function test_set_location_without_geo_provider() {
		update_option( 'sloc_geo_provider', 'missing' );
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );
		$this->assertSame( 'public', get_post_geodata( $args['ID'], 'visibility' ) );
		$this->assertSame( '', get_post_meta( $args['ID'], 'geo_address', true ) );
		$this->assertFalse( Location_Taxonomy::get_post_location( $args['ID'] ) );

		update_option( 'sloc_geo_provider', 'sloc_test' );
		Geo_Provider_Sloc_Test::$reverse = new WP_Error( 'invalid_response', 'Lookup failed' );
		$args                            = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
			)
		);
		Location_Plugins::micropub_set_location( array( 'properties' => array() ), $args );
		$this->assertSame( 'public', get_post_geodata( $args['ID'], 'visibility' ) );
		$this->assertSame( '', get_post_meta( $args['ID'], 'geo_address', true ) );
	}

	/**
	 * A places query without a usable location is a 400 error.
	 */
	public function test_query_places_without_coordinates() {
		$inputs = array(
			'no coordinates'   => array(),
			'latitude only'    => array( 'lat' => '39.95' ),
			'one-part geo URI' => array( 'uri' => 'geo:39.95' ),
			'not numbers'      => array(
				'lat' => 'north',
				'lon' => 'west',
			),
			'out of range'     => array(
				'lat' => '91',
				'lon' => '10',
			),
			'arrays'           => array(
				'lat' => array( '39.95' ),
				'lon' => array( '-75.16' ),
			),
		);
		foreach ( $inputs as $label => $input ) {
			$result = Location_Plugins::micropub_query_places( array( 'resp' ), array( 'q' => 'places' ) + $input );
			$this->assertWPError( $result, $label );
			$this->assertSame( 'invalid_request', $result->get_error_code(), $label );
			$this->assertSame( 400, $result->get_error_data()['status'], $label );
		}
	}

	/**
	 * A places query returns the places near the location as mf2 h-cards.
	 */
	public function test_query_places() {
		update_option( 'sloc_venue_provider', 'sloc_test' );
		Venue_Provider_Sloc_Test::reset();
		$result = Location_Plugins::micropub_query_places(
			new WP_Error( 'invalid_request', 'unknown query' ),
			array(
				'q'   => 'places',
				'uri' => 'geo:' . self::LAT . ',' . self::LNG . ';u=35',
			)
		);
		$this->assertSame( array( 'places' ), array_keys( $result ) );
		$this->assertSame( array( 'h-card' ), $result['places'][0]['type'] );
		$this->assertSame( array( 'Test Cafe' ), $result['places'][0]['properties']['name'] );
		$this->assertSame( array( 'sloc_test' ), $result['places'][0]['properties']['provider'] );
		$this->assertEquals( array( self::LAT ), $result['places'][0]['properties']['latitude'] );
	}

	/**
	 * A places query passes on the limit and whether to list only the site's venues.
	 */
	public function test_query_places_arguments() {
		update_option( 'sloc_venue_provider', 'sloc_test' );
		Venue_Provider_Sloc_Test::reset();
		$venue_id = self::factory()->post->create(
			array(
				'post_type'  => 'venue',
				'post_title' => 'Our Cafe',
			)
		);
		set_post_geodata(
			$venue_id,
			'',
			array(
				'latitude'   => self::LAT,
				'longitude'  => self::LNG,
				'visibility' => 'public',
			)
		);
		$input = array(
			'q'   => 'places',
			'lat' => (string) self::LAT,
			'lon' => (string) self::LNG,
		);

		$result = Location_Plugins::micropub_query_places( array(), $input );
		$this->assertSame( array( 'Our Cafe' ), $result['places'][0]['properties']['name'] );
		$this->assertSame( array( 'Test Cafe' ), $result['places'][1]['properties']['name'] );

		$result = Location_Plugins::micropub_query_places( array(), $input + array( 'limit' => '1' ) );
		$this->assertCount( 1, $result['places'] );

		Venue_Provider_Sloc_Test::reset();
		foreach ( array( '1', 'true', true ) as $local ) {
			$result = Location_Plugins::micropub_query_places( array(), $input + array( 'local' => $local ) );
			$this->assertCount( 1, $result['places'] );
			$this->assertSame( array( 'local' ), $result['places'][0]['properties']['provider'] );
		}
		$this->assertSame( 0, Venue_Provider_Sloc_Test::$calls, 'Only the site is asked.' );

		// A limit that isn't a number, or a local flag that isn't a value, is ignored.
		$result = Location_Plugins::micropub_query_places(
			array(),
			$input + array(
				'limit' => 'many',
				'local' => array( '1' ),
			)
		);
		$this->assertCount( 2, $result['places'] );
	}

	/**
	 * The configuration query lists the places query once, and geo queries are no longer answered.
	 */
	public function test_query_config_and_others() {
		$config = array( 'q' => array( 'config', 'source' ) );
		$result = Location_Plugins::micropub_query_places( $config, array( 'q' => 'config' ) );
		$this->assertSame( array( 'config', 'source', 'places' ), $result['q'] );
		$this->assertSame( $result, Location_Plugins::micropub_query_places( $result, array( 'q' => 'config' ) ) );
		$this->assertSame( array( 'places' ), Location_Plugins::micropub_query_places( array(), array( 'q' => 'config' ) )['q'] );

		$error = new WP_Error( 'forbidden', 'No' );
		$this->assertSame( $error, Location_Plugins::micropub_query_places( $error, array( 'q' => 'config' ) ) );
		$this->assertSame( array( 'resp' ), Location_Plugins::micropub_query_places( array( 'resp' ), array( 'q' => 'source' ) ) );
		$this->assertSame( array( 'resp' ), Location_Plugins::micropub_query_places( array( 'resp' ), array() ) );
		$this->assertSame(
			array( 'resp' ),
			Location_Plugins::micropub_query_places(
				array( 'resp' ),
				array(
					'q'   => 'geo',
					'lat' => self::LAT,
					'lon' => self::LNG,
				)
			),
			'q=geo was removed.'
		);
	}

	/**
	 * The location of a query comes from lat and lon, or a geo URI, which wins.
	 */
	public function test_micropub_coordinates() {
		$this->assertSame(
			array( 0.0, 10.0 ),
			Location_Plugins::micropub_coordinates(
				array(
					'lat' => '0',
					'lon' => '10',
				)
			)
		);
		$this->assertSame( array( 39.9524, -75.1636 ), Location_Plugins::micropub_coordinates( array( 'uri' => 'geo:39.9524,-75.1636;u=35' ) ) );
		$this->assertSame(
			array( 1.0, 2.0 ),
			Location_Plugins::micropub_coordinates(
				array(
					'lat' => '5',
					'lon' => '6',
					'uri' => 'geo:1,2',
				)
			)
		);
		$this->assertSame( array( 39.1234568, 1.0 ), Location_Plugins::micropub_coordinates( array( 'uri' => 'geo:39.12345678%2C1' ) ) );
		$this->assertFalse( Location_Plugins::micropub_coordinates( array( 'uri' => array( 'geo:1,2' ) ) ) );
		$this->assertFalse( Location_Plugins::micropub_coordinates( array( 'uri' => 'https://example.com/' ) ) );
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
	 * A plain-text location is used as the address; a URL is ignored.
	 */
	public function test_webmention_string_location() {
		$raw = array(
			'properties' => array(
				'location' => array( 'https://example.com/venue' ),
			),
		);
		$this->assertSame( array(), Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) ) );

		$raw['properties']['location'] = array( 'Philadelphia, PA' );
		$this->assertSame(
			array( 'geo_address' => 'Philadelphia, PA' ),
			Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) )
		);
	}

	/**
	 * Malformed locations are ignored instead of causing errors.
	 */
	public function test_webmention_malformed_location() {
		foreach ( array(
			'not a list'          => 'Philadelphia',
			'no properties'       => array( array( 'type' => array( 'h-card' ) ) ),
			'string properties'   => array( array( 'properties' => 'Philadelphia' ) ),
			'number'              => array( 42 ),
			'nested list'         => array( array( 'Philadelphia' ) ),
			'string geo'          => array( array( 'properties' => array( 'geo' => array( 'geo:39.9524,-75.1636' ) ) ) ),
			'geo, no properties'  => array( array( 'properties' => array( 'geo' => array( array( 'type' => array( 'h-geo' ) ) ) ) ) ),
			'nested label'        => array(
				array(
					'properties' => array(
						'label' => array( array( 'type' => array( 'h-adr' ) ) ),
						'geo'   => array( 'geo:39.9524,-75.1636' ),
					),
				),
			),
			'array coordinate'    => array( array( 'properties' => array( 'latitude' => array( array( 'value' => '39.9524' ) ) ) ) ),
			'array address parts' => array( array( 'properties' => array( 'name' => array( array( 'type' => array( 'h-card' ) ) ) ) ) ),
		) as $label => $location ) {
			$raw = array( 'properties' => array( 'location' => $location ) );
			$this->assertSame( array(), Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) ), $label );
		}
	}

	/**
	 * Coordinates given as single values rather than lists are kept.
	 */
	public function test_webmention_scalar_coordinates() {
		$raw  = array(
			'properties' => array(
				'location' => array(
					array(
						'properties' => array(
							'latitude'  => '39.9524',
							'longitude' => '-75.1636',
						),
					),
				),
			),
		);
		$meta = Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) );
		$this->assertSame( 39.9524, $meta['geo_latitude'] );
		$this->assertSame( -75.1636, $meta['geo_longitude'] );
	}

	/**
	 * Coordinates that aren't valid or in range are dropped, along with altitude and accuracy.
	 */
	public function test_webmention_invalid_coordinates() {
		foreach ( array(
			'text latitude'         => array( 'abc', '-75.1636' ),
			'latitude out of range' => array( '91', '-75.1636' ),
			'longitude too large'   => array( '39.9524', '999' ),
			'boolean latitude'      => array( true, '-75.1636' ),
			'missing longitude'     => array( '39.9524', null ),
		) as $label => $coordinates ) {
			$props = array(
				'latitude' => array( $coordinates[0] ),
				'altitude' => array( '14' ),
				'accuracy' => array( '25' ),
			);
			if ( null !== $coordinates[1] ) {
				$props['longitude'] = array( $coordinates[1] );
			}
			$raw = array( 'properties' => array( 'location' => array( array( 'properties' => $props ) ) ) );
			$this->assertSame( array(), Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) ), $label );
		}
	}

	/**
	 * Altitude and accuracy that aren't numbers, and negative accuracy, are dropped while valid coordinates are kept.
	 */
	public function test_webmention_invalid_altitude_accuracy() {
		$raw  = array(
			'properties' => array(
				'location' => array(
					array(
						'properties' => array(
							'latitude'  => array( '0' ),
							'longitude' => array( '-75.1636' ),
							'altitude'  => array( 'high' ),
							'accuracy'  => array( '-5' ),
						),
					),
				),
			),
		);
		$meta = Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) );
		$this->assertSame(
			array(
				'geo_latitude'  => 0.0,
				'geo_longitude' => -75.1636,
			),
			$meta
		);

		$raw['properties']['location'][0]['properties']['altitude'] = array( '-10.5' );
		$raw['properties']['location'][0]['properties']['accuracy'] = array( true );
		$meta = Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) );
		$this->assertSame( -10.5, $meta['geo_altitude'] );
		$this->assertArrayNotHasKey( 'geo_accuracy', $meta );
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
		$this->assertSame( 39.9524, $meta['geo_latitude'] );
		$this->assertSame( -75.1636, $meta['geo_longitude'] );
		$this->assertSame( 14.0, $meta['geo_altitude'] );
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
		$this->assertSame( 39.9524, $meta['geo_latitude'] );
		$this->assertSame( -75.1636, $meta['geo_longitude'] );
		$this->assertSame( 25.0, $meta['geo_accuracy'] );
		$this->assertArrayNotHasKey( 'geo_address', $meta );
	}

	/**
	 * A location with a nested h-geo and a label takes its address from the label.
	 */
	public function test_webmention_nested_geo_label() {
		$raw  = array(
			'properties' => array(
				'location' => array(
					array(
						'type'       => array( 'h-card' ),
						'properties' => array(
							'label' => array( 'Home' ),
							'geo'   => array(
								array(
									'type'       => array( 'h-geo' ),
									'properties' => array(
										'latitude'  => array( '39.9524' ),
										'longitude' => array( '-75.1636' ),
									),
								),
							),
						),
					),
				),
			),
		);
		$meta = Location_Plugins::webmention_handler_mf2_set_properties( array(), $this->webmention_handler( $raw ) );
		$this->assertSame( 'Home', $meta['geo_address'] );
		$this->assertSame( 39.9524, $meta['geo_latitude'] );
		$this->assertSame( -75.1636, $meta['geo_longitude'] );
	}

	/**
	 * Data for photos sideloaded with a Micropub post: the requested visibility and the photo's result.
	 *
	 * @return array Requested location-visibility (or null), whether the post is at a venue, expected photo visibility.
	 */
	public function data_sideloaded_photo_visibility() {
		return array(
			'private post'     => array( 'private', false, 'private' ),
			'protected post'   => array( 'protected', false, 'protected' ),
			'public post'      => array( 'public', false, 'public' ),
			'no visibility'    => array( null, false, 'public' ),
			'at a venue'       => array( null, true, 'protected' ),
			'private at venue' => array( 'private', true, 'private' ),
		);
	}

	/**
	 * A photo sideloaded with a Micropub post follows the post's final location visibility.
	 *
	 * Mirrors the Micropub plugin's order: the post is inserted, its photos are sideloaded as attachments
	 * of the post (which runs the attachment metadata hook), and then after_micropub sets the post's location.
	 *
	 * @dataProvider data_sideloaded_photo_visibility
	 *
	 * @param string|null $requested Requested location-visibility, or null for none.
	 * @param bool        $at_venue  Whether the post's coordinates are at a venue.
	 * @param string      $expected  Expected visibility of the photo.
	 */
	public function test_sideloaded_photo_follows_post_visibility( $requested, $at_venue, $expected ) {
		update_option( 'geo_public', 1 );
		$venue = function () use ( $at_venue ) {
			return $at_venue ? self::factory()->post->create( array( 'post_type' => 'venue' ) ) : false;
		};
		add_filter( 'pre_at_venue', $venue );

		// 1. The post is inserted.
		$args = $this->micropub_post(
			array(
				'geo_latitude'  => self::LAT,
				'geo_longitude' => self::LNG,
			)
		);

		// 2. The photo is sideloaded as an attachment of the post, and its metadata generated.
		$photo_id = self::factory()->attachment->create( array( 'post_parent' => $args['ID'] ) );
		Sloc_Media_Metadata::attachment(
			array(
				'image_meta' => array(
					'location' => array(
						'latitude'  => self::LAT,
						'longitude' => self::LNG,
					),
				),
			),
			$photo_id
		);
		$this->assertSame( '', get_post_meta( $photo_id, 'geo_public', true ), 'No visibility is stored on the photo.' );

		// 3. after_micropub sets the post's location.
		$properties = array();
		if ( $requested ) {
			$properties['location-visibility'] = array( $requested );
		}
		Location_Plugins::micropub_set_location( array( 'properties' => $properties ), $args );
		remove_filter( 'pre_at_venue', $venue );

		$this->assertSame( $expected, get_post_geodata( $photo_id, 'visibility' ) );
		$this->assertEquals( self::LAT, get_post_meta( $photo_id, 'geo_latitude', true ) );
	}
}
