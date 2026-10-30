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
		$private = $this->venue( 'Home', self::LAT, self::LNG, 'private' );
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
	 * Converting jf2 to mf2 puts values in lists, nested objects converted, empty values and raw data left out.
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
	 * A provider asked for by name is the only one asked; an unknown one, none.
	 */
	public function test_provider_argument() {
		Venue_Provider_Sloc_Test_Fallback::reset();
		update_option( 'sloc_venue_fallbacks', array( 'sloc_test_fallback' ) );
		Venue_Provider_Sloc_Test::$result = array( 'items' => array() );
		$this->assertSame( array( 'sloc_test_fallback' ), Post_Venue::places( self::LAT, self::LNG )[0]['properties']['provider'] );
		$this->assertSame( array(), Post_Venue::places( self::LAT, self::LNG, array( 'provider' => 'sloc_test' ) ) );
		Venue_Provider_Sloc_Test::reset();
		$this->assertSame( array( 'sloc_test' ), Post_Venue::places( self::LAT, self::LNG, array( 'provider' => 'sloc_test' ) )[0]['properties']['provider'] );
		$this->assertSame( array(), Post_Venue::places( self::LAT, self::LNG, array( 'provider' => 'no_such_provider' ) ) );
		Venue_Provider_Sloc_Test::reset();
		$places = Post_Venue::places(
			self::LAT,
			self::LNG,
			array(
				'provider' => 'sloc_test_fallback',
				'limit'    => 1,
			)
		);
		$this->assertSame( array( 'sloc_test_fallback' ), $places[0]['properties']['provider'] );
		$this->assertSame( 0, Venue_Provider_Sloc_Test::$calls, 'Only the provider asked for.' );
		$this->assertSame( Venue_Provider::DEFAULT_LIMIT, Loc_Config::venue_provider( 'sloc_test_fallback' )->get_limit(), 'The shared provider keeps its limit.' );
	}

	/**
	 * Converting mf2 to jf2 gives one value as the value, several as a list, nested objects converted.
	 */
	public function test_mf2_to_jf2() {
		$this->assertSame(
			array(
				'type'     => 'card',
				'name'     => 'Cafe',
				'url'      => array( 'https://a.example/', 'https://b.example/' ),
				'adr'      => array(
					'type'     => 'adr',
					'locality' => 'Philadelphia',
				),
				'latitude' => 39.9,
			),
			Post_Venue::mf2_to_jf2(
				array(
					'type'       => array( 'h-card' ),
					'properties' => array(
						'name'     => array( 'Cafe' ),
						'url'      => array( 'https://a.example/', 'https://b.example/' ),
						'adr'      => array(
							array(
								'type'       => array( 'h-adr' ),
								'properties' => array( 'locality' => array( 'Philadelphia' ) ),
							),
						),
						'latitude' => array( 39.9 ),
						'empty'    => array(),
						'type'     => array( 'h-entry' ),
						0          => array( 'numbered' ),
					),
				)
			)
		);
		$this->assertSame( 'Cafe', Post_Venue::mf2_to_jf2( 'Cafe' ) );
		$this->assertSame( array( 'name' => 'Cafe' ), Post_Venue::mf2_to_jf2( array( 'name' => 'Cafe' ) ), 'jf2 is left as it is.' );
		$this->assertSame( array( 'type' => 'card' ), Post_Venue::mf2_to_jf2( array( 'properties' => array() ) ) );
	}

	/**
	 * A place from the list is converted back to the card it came from.
	 */
	public function test_round_trip() {
		$card = array(
			'type'      => 'card',
			'name'      => 'Cafe',
			'category'  => array( 'food', 'cafe' ),
			'latitude'  => 39.9,
			'longitude' => -75.1,
		);
		$this->assertSame( $card, Post_Venue::mf2_to_jf2( Post_Venue::jf2_to_mf2( $card ) ) );
	}

	/**
	 * A chosen place that is the site's venue is that venue, if the user may use it.
	 */
	public function test_choose_local_place() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$public    = $this->venue( 'Public Cafe' );
		$protected = $this->venue( 'Office', self::LAT, self::LNG, 'protected' );
		$private   = $this->venue( 'Home', self::LAT, self::LNG, 'private' );
		$card      = fn( $id ) => array(
			'type'       => array( 'h-card' ),
			'properties' => array(
				'name'     => array( get_the_title( $id ) ),
				'uid'      => array( get_permalink( $id ) ),
				'url'      => array( get_permalink( $id ) ),
				'provider' => array( 'local' ),
			),
		);
		$this->assertSame( $public, Post_Venue::choose_place( $card( $public ) ) );
		$this->assertSame( $protected, Post_Venue::choose_place( $card( $protected ) ), 'A protected venue can be used by name.' );
		$this->assertSame( 'not_found', Post_Venue::choose_place( $card( $private ) )->get_error_code() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( $private, Post_Venue::choose_place( $card( $private ) ) );

		$missing                      = $card( $public );
		$missing['properties']['uid'] = array( 'https://elsewhere.example/' );
		$missing['properties']['url'] = array();
		$this->assertSame( 'not_found', Post_Venue::choose_place( $missing )->get_error_code(), 'A site venue that is not on the site.' );
	}

	/**
	 * A site venue is found by its page with plain or pretty permalinks, and as a draft; not on another site.
	 */
	public function test_choose_by_permalink() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$parent = $this->venue( 'Market' );
		$child  = $this->venue( 'Stall', self::LAT, self::LNG, 'public', array( 'post_parent' => $parent ) );
		$draft  = $this->venue( 'Planned', self::LAT, self::LNG, 'public', array( 'post_status' => 'draft' ) );
		$choose = fn( $url ) => Post_Venue::choose_place(
			array(
				'name'     => 'Place',
				'uid'      => $url,
				'provider' => 'local',
			)
		);
		$this->assertStringContainsString( '?venue=', get_permalink( $child ) );
		$this->assertSame( $child, $choose( get_permalink( $child ) ) );
		$this->assertStringContainsString( 'post_type=venue', get_permalink( $draft ) );
		$this->assertSame( $draft, $choose( get_permalink( $draft ) ) );
		$this->assertSame( 'not_found', $choose( 'https://elsewhere.example/?venue=market' )->get_error_code() );
		$this->assertSame( 'not_found', $choose( home_url( '/?venue=no-such-venue' ) )->get_error_code() );

		$this->set_permalink_structure( '/%postname%/' );
		// The post type's rewrite rules are only added with pretty permalinks.
		Post_Venue::register();
		flush_rewrite_rules( false );
		$this->assertStringContainsString( '/venue/market/stall/', get_permalink( $child ) );
		$this->assertSame( $child, $choose( get_permalink( $child ) ) );
		$this->set_permalink_structure( '' );
	}

	/**
	 * A chosen provider place becomes a venue, or is matched to the one the site has.
	 */
	public function test_choose_provider_place() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$place    = array(
			'type'       => array( 'h-card' ),
			'properties' => array(
				'name'      => array( 'Provider Diner' ),
				'uid'       => array( 'here:pds:place:42' ),
				'provider'  => array( 'sloc_test' ),
				'latitude'  => array( self::LAT ),
				'longitude' => array( self::LNG ),
				'distance'  => array( 12 ),
				'nested'    => array( array( 'not' => 'a value' ) ),
			),
		);
		$venue_id = Post_Venue::choose_place( $place );
		$this->assertSame( 'venue', get_post_type( $venue_id ) );
		$this->assertSame( 'Provider Diner', get_the_title( $venue_id ) );
		$this->assertContains( 'here:pds:place:42', get_post_meta( $venue_id, Post_Venue::UID_KEY, false ) );
		$this->assertSame( $venue_id, Post_Venue::choose_place( $place ), 'The same place again is the same venue.' );

		// jf2 works as well.
		$this->assertSame( $venue_id, Post_Venue::choose_place( Post_Venue::mf2_to_jf2( $place ) ) );
	}

	/**
	 * A chosen place that is a private venue the user may not use leaves the venue alone.
	 */
	public function test_choose_hidden_venue() {
		$private = $this->venue( 'Home', self::LAT, self::LNG, 'private' );
		add_post_meta( $private, Post_Venue::UID_KEY, 'here:pds:place:home' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$result = Post_Venue::choose_place(
			array(
				'name'     => 'Home',
				'uid'      => 'here:pds:place:home',
				'url'      => 'https://home.example/',
				'provider' => 'sloc_test',
			)
		);
		$this->assertSame( 'not_found', $result->get_error_code() );
		$this->assertSame( '', get_post_meta( $private, 'venue_url', true ), 'The venue gains nothing.' );
	}

	/**
	 * A chosen place must have a name and can't be another kind of post on the site.
	 */
	public function test_choose_invalid_place() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();
		$this->assertSame( 'invalid_place', Post_Venue::choose_place( 'Cafe' )->get_error_code() );
		$this->assertSame( 'invalid_place', Post_Venue::choose_place( array( 'name' => '  ' ) )->get_error_code() );
		$this->assertSame( 'invalid_place', Post_Venue::choose_place( array( 'name' => array( 'Cafe', 'Two' ) ) )->get_error_code() );
		$this->assertSame(
			'invalid_place',
			Post_Venue::choose_place(
				array(
					'name' => 'Not a venue',
					'url'  => get_permalink( $post_id ),
				)
			)->get_error_code()
		);
		$this->assertSame( 400, Post_Venue::choose_place( 'Cafe' )->get_error_data()['status'] );
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
