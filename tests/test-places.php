<?php
/**
 * Tests for the places near a location (#814).
 *
 * @package Simple_Location
 */

/**
 * Places tests.
 */
class PlacesTest extends WP_UnitTestCase {

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * The fake venue provider, alone in the chain.
	 */
	public function set_up() {
		parent::set_up();
		Venue_Provider_Sloc_Test::reset();
		update_option( 'sloc_venue_provider', 'sloc_test' );
		delete_option( 'sloc_venue_fallbacks' );
		delete_option( 'sloc_venue_radius' );
		delete_option( 'sloc_venue_limit' );
		update_option( 'geo_public', 1 );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		Venue_Provider_Sloc_Test::reset();
		parent::tear_down();
	}

	/**
	 * Creates a venue.
	 *
	 * @param string $title      Title.
	 * @param float  $lat        Latitude.
	 * @param float  $lng        Longitude.
	 * @param string $visibility Location visibility.
	 * @param array  $post       Other post fields.
	 * @return int Venue ID.
	 */
	protected function venue( $title, $lat = self::LAT, $lng = self::LNG, $visibility = 'public', $post = array() ) {
		$venue_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'venue',
					'post_title'  => $title,
					'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ),
				),
				$post
			)
		);
		set_post_geodata(
			$venue_id,
			'',
			array(
				'latitude'   => $lat,
				'longitude'  => $lng,
				'visibility' => $visibility,
			)
		);
		return $venue_id;
	}

	/**
	 * Names of a list of places.
	 *
	 * @param array[] $places mf2 h-cards.
	 * @return string[]
	 */
	protected function names( $places ) {
		return array_map( fn( $place ) => $place['properties']['name'][0], $places );
	}

	/**
	 * The site's venues come first, nearest first, with their page and details; then the provider's.
	 */
	public function test_local_first() {
		$near     = $this->venue( 'Near Cafe', self::LAT + 0.0005 );
		$here     = $this->venue( 'Here Cafe' );
		$category = self::factory()->term->create(
			array(
				'taxonomy' => 'venue_type',
				'name'     => 'Food',
			)
		);
		wp_set_object_terms( $here, $category, 'venue_type' );
		update_post_meta( $here, 'venue_url', 'https://cafe.example.com/' );
		update_post_meta( $here, 'venue_street_address', '1 Penn Square' );
		set_post_geodata( $here, 'address', 'City Hall, Philadelphia' );

		$places = Post_Venue::places( self::LAT, self::LNG );
		$this->assertSame( array( 'Here Cafe', 'Near Cafe', 'Test Cafe' ), $this->names( $places ) );

		$card = $places[0];
		$this->assertSame( array( 'h-card' ), $card['type'] );
		$this->assertSame( array( get_permalink( $here ), 'https://cafe.example.com/' ), $card['properties']['url'] );
		$this->assertSame( array( get_permalink( $here ) ), $card['properties']['uid'] );
		$this->assertSame( array( 'local' ), $card['properties']['provider'] );
		$this->assertSame( array( 'Food' ), $card['properties']['category'] );
		$this->assertSame( array( self::LAT ), $card['properties']['latitude'] );
		$this->assertSame( array( self::LNG ), $card['properties']['longitude'] );
		$this->assertSame( array( '1 Penn Square' ), $card['properties']['street-address'] );
		$this->assertSame( array( 'City Hall, Philadelphia' ), $card['properties']['label'] );
		$this->assertSame( array( 0 ), $card['properties']['distance'] );
		$this->assertSame( array( 56 ), $places[1]['properties']['distance'] );
		$this->assertArrayNotHasKey( 'altitude', $card['properties'], 'Empty values are left out.' );
		$this->assertSame( array( 'sloc_test' ), $places[2]['properties']['provider'] );
		$this->assertSame( get_permalink( $near ), $places[1]['properties']['uid'][0] );
	}

	/**
	 * Venues outside the radius, and those without coordinates, aren't the site's places here.
	 */
	public function test_radius() {
		update_option( 'sloc_venue_radius', 100 );
		$this->venue( 'Far Cafe', self::LAT + 0.002 );
		$this->venue( 'Nearby Cafe', self::LAT + 0.0005 );
		$this->assertSame( array( 'Nearby Cafe', 'Test Cafe' ), $this->names( Post_Venue::places( self::LAT, self::LNG ) ) );
	}

	/**
	 * Provider places the site already has, by identifier or link, are left out; one found by identifier beyond the
	 * radius is listed as the site's.
	 */
	public function test_duplicates() {
		$by_uid = $this->venue( 'Our Diner' );
		add_post_meta( $by_uid, Post_Venue::UID_KEY, 'here:pds:place:1' );
		$by_link = $this->venue( 'Our Bar' );
		update_post_meta( $by_link, 'venue_alternate', array( 'https://www.openstreetmap.org/node/2' ) );
		$far = $this->venue( 'Our Museum', self::LAT + 0.01 );
		add_post_meta( $far, Post_Venue::UID_KEY, 'here:pds:place:3' );

		Venue_Provider_Sloc_Test::$result = array(
			'items' => array(
				array(
					'name'      => 'Diner',
					'uid'       => 'here:pds:place:1',
					'latitude'  => self::LAT,
					'longitude' => self::LNG,
				),
				array(
					'name'      => 'Bar',
					'uid'       => 'https://www.openstreetmap.org/node/2',
					'latitude'  => self::LAT,
					'longitude' => self::LNG,
				),
				array(
					'name'      => 'Museum',
					'uid'       => 'here:pds:place:3',
					'latitude'  => self::LAT,
					'longitude' => self::LNG,
				),
				array(
					'name'      => 'Park',
					'url'       => 'https://park.example.com/',
					'latitude'  => self::LAT,
					'longitude' => self::LNG,
				),
			),
		);
		$this->assertSame( array( 'Our Diner', 'Our Bar', 'Our Museum', 'Park' ), $this->names( Post_Venue::places( self::LAT, self::LNG ) ) );
	}

	/**
	 * Private venues are left out unless the user can edit them; protected venues show without their location.
	 */
	public function test_private_venues() {
		$private   = $this->venue( 'Home', self::LAT, self::LNG, 'private' );
		$this->venue( 'Office', self::LAT, self::LNG, 'protected' );
		$this->venue( 'Secret', self::LAT, self::LNG, 'public', array( 'post_status' => 'private' ) );
		$this->venue( 'Planned', self::LAT, self::LNG, 'public', array( 'post_status' => 'draft' ) );
		$this->venue( 'Locked', self::LAT, self::LNG, 'public', array( 'post_password' => 'pass' ) );
		add_post_meta( $private, Post_Venue::UID_KEY, 'here:pds:place:home' );
		Venue_Provider_Sloc_Test::$result = array(
			'items' => array(
				array(
					'name'      => 'Home at the provider',
					'uid'       => 'here:pds:place:home',
					'latitude'  => self::LAT,
					'longitude' => self::LNG,
				),
			),
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$places = Post_Venue::places( self::LAT, self::LNG );
		$this->assertSame( array( 'Office', 'Home at the provider' ), $this->names( $places ), 'A venue the user may not see is no duplicate.' );
		$this->assertSame( array( 'name', 'url', 'uid', 'provider' ), array_keys( $places[0]['properties'] ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$places = Post_Venue::places( self::LAT, self::LNG );
		$this->assertEqualSets( array( 'Home', 'Office', 'Secret', 'Planned', 'Locked' ), $this->names( $places ) );
		foreach ( $places as $place ) {
			$this->assertArrayHasKey( 'latitude', $place['properties'], 'Editors see every venue in full.' );
		}

		// The site default applies to a venue without a visibility.
		wp_set_current_user( 0 );
		update_option( 'geo_public', 0 );
		$default = $this->venue( 'Default' );
		delete_post_meta( $default, 'geo_public' );
		$this->assertNotContains( 'Default', $this->names( Post_Venue::places( self::LAT, self::LNG ) ) );
	}

	/**
	 * The limit caps the places; the provider isn't asked when the site's venues fill it, or for only the site's.
	 */
	public function test_limit_and_local() {
		$this->venue( 'One' );
		$this->venue( 'Two' );
		$this->assertCount( 1, Post_Venue::places( self::LAT, self::LNG, array( 'limit' => 1 ) ) );
		$this->assertCount( 2, Post_Venue::places( self::LAT, self::LNG, array( 'limit' => 2 ) ) );
		$this->assertSame( 0, Venue_Provider_Sloc_Test::$calls );
		$this->assertSame( array( 'One', 'Two' ), $this->names( Post_Venue::places( self::LAT, self::LNG, array( 'local' => true ) ) ), 'The oldest first at the same distance.' );
		$this->assertSame( 0, Venue_Provider_Sloc_Test::$calls );
		$this->assertCount( 3, Post_Venue::places( self::LAT, self::LNG, array( 'limit' => 500 ) ) );
		$this->assertSame( 1, Venue_Provider_Sloc_Test::$calls );

		// The provider is asked for the limit, not its setting.
		update_option( 'sloc_venue_limit', 1 );
		$items = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$items[] = array(
				'name'      => 'Shop ' . $i,
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			);
		}
		Venue_Provider_Sloc_Test::$result = array( 'items' => $items );
		$this->assertCount( 1, Post_Venue::places( self::LAT, self::LNG ), 'The setting is the default limit.' );
		$this->assertCount( 7, Post_Venue::places( self::LAT, self::LNG, array( 'limit' => 7 ) ) );
	}

	/**
	 * Without a venue provider, only the site's venues; invalid coordinates, none; the list can be filtered.
	 */
	public function test_edges() {
		$this->venue( 'Ours' );
		update_option( 'sloc_venue_provider', 'none' );
		$this->assertSame( array( 'Ours' ), $this->names( Post_Venue::places( self::LAT, self::LNG ) ) );
		$this->assertSame( array(), Post_Venue::places( 91, self::LNG ) );
		$this->assertSame( array(), Post_Venue::places( 'north', self::LNG ) );

		$filter = function ( $places, $lat, $lng, $args ) {
			$this->assertSame( self::LAT, $lat );
			$this->assertTrue( $args['local'] );
			return array();
		};
		add_filter( 'sloc_places', $filter, 10, 4 );
		$this->assertSame( array(), Post_Venue::places( (string) self::LAT, self::LNG, array( 'local' => true ) ) );
		remove_filter( 'sloc_places', $filter, 10 );
	}

	/**
	 * A provider error leaves the site's venues.
	 */
	public function test_provider_error() {
		$this->venue( 'Ours' );
		Venue_Provider_Sloc_Test::$result = new WP_Error( 'unable_to_retrieve', 'Down' );
		$this->assertSame( array( 'Ours' ), $this->names( Post_Venue::places( self::LAT, self::LNG ) ) );
	}

	/**
	 * jf2 becomes mf2: values in lists, nested objects converted, empty values and raw data left out.
	 */
	public function test_jf2_to_mf2() {
		$this->assertSame(
			array(
				'type'       => array( 'h-card' ),
				'properties' => array(
					'name'     => array( 'Cafe' ),
					'category' => array( 'food', 'cafe' ),
					'latitude' => array( 0.0 ),
					'open'     => array( false ),
					'adr'      => array(
						array(
							'type'       => array( 'h-adr' ),
							'properties' => array( 'locality' => array( 'Philadelphia' ) ),
						),
					),
				),
			),
			Post_Venue::jf2_to_mf2(
				array(
					'type'     => 'card',
					'name'     => 'Cafe',
					'category' => array( 'food', 'cafe', '' ),
					'latitude' => 0.0,
					'open'     => false,
					'adr'      => array(
						'type'     => 'adr',
						'locality' => 'Philadelphia',
					),
					'url'      => '',
					'tel'      => null,
					'raw'      => array( 'id' => 1 ),
					'extra'    => array( 'not' => 'a value' ),
				)
			)
		);
		$this->assertSame( array( 'h-card' ), Post_Venue::jf2_to_mf2( array( 'name' => 'Untyped' ) )['type'] );
	}

	/**
	 * A provider's limit can be set, and the chain asks for a number of venues.
	 */
	public function test_provider_limit() {
		$provider = new Venue_Provider_Sloc_Test();
		$provider->set_limit( 3 );
		$this->assertSame( 3, $provider->get_limit() );
		$provider->set_limit( 500 );
		$this->assertSame( Venue_Provider::MAX_LIMIT, $provider->get_limit() );
		$provider->set_limit( 'many' );
		$this->assertSame( Venue_Provider::DEFAULT_LIMIT, $provider->get_limit() );

		$items = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$items[] = array(
				'name'      => 'Shop ' . $i,
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			);
		}
		Venue_Provider_Sloc_Test::$result = array( 'items' => $items );
		$this->assertCount( 5, Sloc_Provider_Chain::venues( self::LAT, self::LNG )['items'], 'The setting, 10, by default.' );
		$this->assertCount( 4, Sloc_Provider_Chain::venues( self::LAT, self::LNG, 4 )['items'] );
		$this->assertCount( 5, Sloc_Provider_Chain::venues( self::LAT, self::LNG )['items'], 'The shared provider keeps its limit.' );
	}
}
