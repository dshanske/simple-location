<?php
/**
 * Tests for the location taxonomy (Country → Region → Locality) in includes/class-location-taxonomy.php.
 *
 * @package Simple_Location
 */

/**
 * Location taxonomy tests.
 */
class LocationTaxonomyTest extends WP_UnitTestCase {

	/**
	 * Sample address with every level.
	 *
	 * @var array
	 */
	public static $addr = array(
		'country-name' => 'United States of America',
		'country-code' => 'US',
		'region'       => 'Pennsylvania',
		'region-code'  => 'PA',
		'locality'     => 'Philadelphia',
	);

	/**
	 * Start each test as a logged-out visitor with no home country.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( 0 );
		update_option( 'sloc_country', '' );
	}

	/**
	 * Clean up request data.
	 */
	public function tear_down() {
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * Creates the sample hierarchy and returns the term IDs by level.
	 *
	 * @param array $addr Address. Defaults to the sample address.
	 * @return array Term IDs keyed by country, region, and locality.
	 */
	protected function hierarchy( $addr = null ) {
		$locality  = Location_Taxonomy::get_location( $addr ? $addr : static::$addr, true );
		$ancestors = get_ancestors( $locality, 'location', 'taxonomy' );
		return array(
			'country'  => $ancestors[1],
			'region'   => $ancestors[0],
			'locality' => $locality,
		);
	}

	/**
	 * The taxonomy and its term meta are registered.
	 */
	public function test_register() {
		// The test framework unregisters meta keys after each test, so register again.
		Location_Taxonomy::register();
		$taxonomy = get_taxonomy( 'location' );
		$this->assertInstanceOf( WP_Taxonomy::class, $taxonomy );
		$this->assertTrue( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->public );
		$this->assertContains( 'post', $taxonomy->object_type );
		$this->assertContains( 'venue', $taxonomy->object_type );
		foreach ( array( 'country', 'region', 'locality' ) as $key ) {
			$this->assertTrue( registered_meta_key_exists( 'term', $key, 'location' ), $key );
		}
	}

	/**
	 * The admin and front-end hooks are registered.
	 */
	public function test_hooks_registered() {
		$this->assertSame( 10, has_action( 'created_location', array( 'Location_Taxonomy', 'save_data' ) ) );
		$this->assertSame( 10, has_action( 'edited_location', array( 'Location_Taxonomy', 'save_data' ) ) );
		$this->assertSame( 10, has_action( 'pre_get_posts', array( 'Location_Taxonomy', 'filter_location_posts' ) ) );
		$this->assertSame( 10, has_filter( 'get_the_archive_title', array( 'Location_Taxonomy', 'archive_title' ) ) );
	}

	/**
	 * A full address creates country, region, and locality terms with their codes.
	 */
	public function test_get_location_creates_hierarchy() {
		$terms = $this->hierarchy();
		$this->assertSame( 'US', get_term_meta( $terms['country'], 'country', true ) );
		$this->assertSame( 'PA', get_term_meta( $terms['region'], 'region', true ) );
		$this->assertSame( 'Philadelphia', get_term_meta( $terms['locality'], 'locality', true ) );
		$this->assertSame( 'United States of America', get_term( $terms['country'] )->name );
		$this->assertSame( 'Pennsylvania', get_term( $terms['region'] )->name );
		$this->assertSame( 'Philadelphia', get_term( $terms['locality'] )->name );
	}

	/**
	 * The location data round-trips to an address.
	 */
	public function test_location_data_to_hadr() {
		$terms = $this->hierarchy();
		$this->assertEquals( static::$addr, Location_Taxonomy::location_data_to_hadr( Location_Taxonomy::get_location_data( $terms['locality'] ) ) );
		$this->assertSame( array(), Location_Taxonomy::location_data_to_hadr( array() ) );
		$this->assertFalse( Location_Taxonomy::get_location_data( 999999 ) );
	}

	/**
	 * Looking up the same address again returns the same term without creating new ones.
	 */
	public function test_get_location_existing() {
		$term  = Location_Taxonomy::get_location( static::$addr, true );
		$count = wp_count_terms( array( 'taxonomy' => 'location' ) );
		$this->assertSame( $term, Location_Taxonomy::get_location( static::$addr, true ) );
		$this->assertSame( $term, Location_Taxonomy::get_location( static::$addr ) );
		$this->assertEquals( $count, wp_count_terms( array( 'taxonomy' => 'location' ) ) );
	}

	/**
	 * Without the create flag, an unknown address returns false and creates nothing.
	 */
	public function test_get_location_without_create() {
		$this->assertFalse( Location_Taxonomy::get_location( static::$addr ) );
		$this->assertEquals( 0, wp_count_terms( array( 'taxonomy' => 'location' ) ) );
	}

	/**
	 * A new locality in an existing region reuses the region and country.
	 */
	public function test_get_location_new_locality_existing_region() {
		$terms             = $this->hierarchy();
		$addr              = static::$addr;
		$addr['locality']  = 'Pittsburgh';
		$pittsburgh        = Location_Taxonomy::get_location( $addr, true );
		$pittsburgh_parent = get_term( $pittsburgh )->parent;
		$this->assertNotSame( $terms['locality'], $pittsburgh );
		$this->assertSame( $terms['region'], $pittsburgh_parent );
	}

	/**
	 * An address without a locality resolves to its region.
	 */
	public function test_get_location_region_only() {
		$addr = static::$addr;
		unset( $addr['locality'] );
		$region = Location_Taxonomy::get_location( $addr, true );
		$this->assertSame( 'region', Location_Taxonomy::get_location_type( $region ) );
		$this->assertSame( 'PA', get_term_meta( $region, 'region', true ) );
	}

	/**
	 * An address with only a country resolves to the country.
	 */
	public function test_get_location_country_only() {
		$country = Location_Taxonomy::get_location(
			array(
				'country-name' => 'Canada',
				'country-code' => 'CA',
			),
			true
		);
		$this->assertSame( 'country', Location_Taxonomy::get_location_type( $country ) );
		$this->assertSame( 'CA', get_term_meta( $country, 'country', true ) );
	}

	/**
	 * Localities with the same name in different regions are different terms.
	 */
	public function test_get_location_same_locality_name() {
		$illinois      = Location_Taxonomy::get_location(
			array(
				'country-name' => 'United States',
				'country-code' => 'US',
				'region'       => 'Illinois',
				'region-code'  => 'IL',
				'locality'     => 'Springfield',
			),
			true
		);
		$massachusetts = Location_Taxonomy::get_location(
			array(
				'country-name' => 'United States',
				'country-code' => 'US',
				'region'       => 'Massachusetts',
				'region-code'  => 'MA',
				'locality'     => 'Springfield',
			),
			true
		);
		$this->assertIsInt( $illinois );
		$this->assertIsInt( $massachusetts );
		$this->assertNotSame( $illinois, $massachusetts );
		$this->assertSame( 'MA', get_term_meta( get_term( $massachusetts )->parent, 'region', true ) );
	}

	/**
	 * An address with a country code but no country name still creates the country.
	 */
	public function test_get_location_without_country_name() {
		$addr = static::$addr;
		unset( $addr['country-name'] );
		$terms = $this->hierarchy( $addr );
		$this->assertSame( 'US', get_term_meta( $terms['country'], 'country', true ) );
		$this->assertSame( 'United States', get_term( $terms['country'] )->name );
	}

	/**
	 * Looking up a locality or region without a country code doesn't raise warnings.
	 */
	public function test_lookups_without_country_code() {
		$this->hierarchy();
		$this->assertFalse( Location_Taxonomy::get_locality( array( 'locality' => 'Philadelphia' ) ) );
		$this->assertFalse( Location_Taxonomy::get_region( array( 'region-code' => 'PA' ) ) );
	}

	/**
	 * The country, region, and locality can each be found.
	 */
	public function test_get_country_region_locality() {
		$terms = $this->hierarchy();
		$this->assertSame( $terms['country'], Location_Taxonomy::get_country( 'US' ) );
		$this->assertFalse( Location_Taxonomy::get_country( 'FR' ) );

		$this->assertSame( $terms['region'], Location_Taxonomy::get_region( static::$addr ) );
		$by_name = array(
			'country-code' => 'US',
			'region'       => 'PA',
		);
		$this->assertSame( $terms['region'], Location_Taxonomy::get_region( $by_name ) );
		$this->assertFalse( Location_Taxonomy::get_region( array() ) );
		$this->assertFalse( Location_Taxonomy::get_region( array( 'country-code' => 'CA' ) + static::$addr ) );

		$this->assertSame( $terms['locality'], Location_Taxonomy::get_locality( static::$addr ) );
		$this->assertFalse( Location_Taxonomy::get_locality( array() ) );
		$this->assertFalse( Location_Taxonomy::get_locality( 'Philadelphia' ) );
		$this->assertFalse( Location_Taxonomy::get_locality( array( 'region-code' => 'NY' ) + static::$addr ) );
	}

	/**
	 * The regions of a country are listed.
	 */
	public function test_get_country_regions() {
		$terms = $this->hierarchy();
		$this->assertSame( array( $terms['region'] => 'Pennsylvania' ), Location_Taxonomy::get_country_regions( 'US' ) );
		$this->assertFalse( Location_Taxonomy::get_country_regions( 'FR' ) );
	}

	/**
	 * The localities of a region are listed.
	 */
	public function test_get_region_localities() {
		$terms = $this->hierarchy();
		$this->assertSame( array( $terms['locality'] => 'Philadelphia' ), Location_Taxonomy::get_region_localities( 'PA', 'US' ) );
	}

	/**
	 * The type of a term is derived from its depth.
	 */
	public function test_get_location_type() {
		$terms = $this->hierarchy();
		$this->assertSame( 'country', Location_Taxonomy::get_location_type( $terms['country'] ) );
		$this->assertSame( 'region', Location_Taxonomy::get_location_type( $terms['region'] ) );
		$this->assertSame( 'locality', Location_Taxonomy::get_location_type( $terms['locality'] ) );

		$too_deep = self::factory()->term->create(
			array(
				'taxonomy' => 'location',
				'parent'   => $terms['locality'],
			)
		);
		$this->assertFalse( Location_Taxonomy::get_location_type( $too_deep ) );
		$this->assertFalse( Location_Taxonomy::get_location_type( self::factory()->category->create() ) );
		$this->assertFalse( Location_Taxonomy::get_location_type( 999999 ) );
	}

	/**
	 * Type labels.
	 */
	public function test_location_type_labels() {
		$this->assertSame( 'Country', Location_Taxonomy::location_type( 'country' ) );
		$this->assertSame( 'Region', Location_Taxonomy::location_type( 'region' ) );
		$this->assertSame( 'Locality', Location_Taxonomy::location_type( 'locality' ) );
		$this->assertSame( 'None', Location_Taxonomy::location_type( false ) );
		$this->assertSame( 'None', Location_Taxonomy::location_type( 'planet' ) );
	}

	/**
	 * The country code is found from any level.
	 */
	public function test_get_parent_country() {
		$terms = $this->hierarchy();
		foreach ( $terms as $level => $term_id ) {
			$this->assertSame( 'US', Location_Taxonomy::get_parent_country( $term_id ), $level );
		}
		$this->assertFalse( Location_Taxonomy::get_parent_country( self::factory()->category->create() ) );
		$this->assertFalse( Location_Taxonomy::get_parent_country( 999999 ) );
	}

	/**
	 * Posts can be assigned a location and read back.
	 */
	public function test_post_location() {
		$terms   = $this->hierarchy();
		$post_id = self::factory()->post->create();
		$this->assertFalse( Location_Taxonomy::get_location_taxonomy( $post_id ) );
		$this->assertFalse( Location_Taxonomy::get_post_location( $post_id ) );

		Location_Taxonomy::set_location( $post_id, $terms['locality'] );
		$this->assertSame( $terms['locality'], Location_Taxonomy::get_post_location( $post_id ) );
		$this->assertSame( $terms['locality'], Location_Taxonomy::get_location_taxonomy( $post_id )->term_id );
		$this->assertSame( $terms['locality'], Location_Taxonomy::get_location_taxonomy( array( 'id' => $post_id ) )->term_id );
		$this->assertSame( 'philadelphia', Location_Taxonomy::get_location_taxonomy_slug( $post_id ) );

		$this->assertFalse( Location_Taxonomy::get_location_taxonomy( 999999 ) );
		$this->assertFalse( Location_Taxonomy::get_location_taxonomy_slug( 999999 ) );
		$this->assertSame( '', Location_Taxonomy::get_post_location( 999999 ) );
	}

	/**
	 * A location can be set by passing the post object.
	 */
	public function test_set_location_with_post_object() {
		$this->markTestSkipped( 'Known bug, see #321' );
		$terms = $this->hierarchy();
		$post  = self::factory()->post->create_and_get();
		Location_Taxonomy::set_location( $post, $terms['locality'] );
		$this->assertSame( $terms['locality'], Location_Taxonomy::get_post_location( $post->ID ) );
	}

	/**
	 * The display name lists every level with links and the country flag.
	 */
	public function test_display_name() {
		$terms = $this->hierarchy();
		$name  = Location_Taxonomy::display_name( $terms['locality'] );
		$this->assertStringStartsWith( 'Philadelphia, <a href="' . get_term_link( $terms['region'] ) . '">Pennsylvania</a>, <a href="', $name );
		$this->assertStringContainsString( '🇺🇸 United States of America</a>', $name );

		$this->assertSame( 'Philadelphia, Pennsylvania, 🇺🇸 United States of America', Location_Taxonomy::display_name( $terms['locality'], array( 'links' => false ) ) );
		$this->assertSame( '🇺🇸 United States of America', Location_Taxonomy::display_name( $terms['country'] ) );
		$this->assertSame( 'United States of America', Location_Taxonomy::display_name( $terms['country'], array( 'flag' => false ) ) );
		$this->assertSame(
			'Philadelphia, Pennsylvania, United States of America',
			Location_Taxonomy::display_name(
				$terms['locality'],
				array(
					'links' => false,
					'flag'  => false,
				)
			)
		);
	}

	/**
	 * The site's home country is left out of display names.
	 */
	public function test_display_name_home_country() {
		update_option( 'sloc_country', 'US' );
		$terms = $this->hierarchy();
		$this->assertSame( 'Philadelphia, Pennsylvania', Location_Taxonomy::display_name( $terms['locality'], array( 'links' => false ) ) );
	}

	/**
	 * Display names and links for terms that don't exist are empty, without warnings.
	 */
	public function test_display_name_missing_term() {
		$this->assertSame( '', Location_Taxonomy::display_name( 999999 ) );
		$this->assertSame( '', Location_Taxonomy::get_location_link( 999999 ) );
	}

	/**
	 * The location link points to the term and names every level.
	 */
	public function test_get_location_link() {
		$terms = $this->hierarchy();
		$this->assertSame(
			'<a href="' . get_term_link( $terms['locality'] ) . '">Philadelphia, Pennsylvania, 🇺🇸 United States of America</a>',
			Location_Taxonomy::get_location_link( $terms['locality'] )
		);
		update_option( 'sloc_country', 'US' );
		$this->assertSame( '<a href="' . get_term_link( $terms['region'] ) . '">Pennsylvania</a>', Location_Taxonomy::get_location_link( $terms['region'] ) );
	}

	/**
	 * A post's location link is built from its term.
	 */
	public function test_get_post_location_link() {
		$terms   = $this->hierarchy();
		$post_id = self::factory()->post->create();
		$this->assertSame( '', Location_Taxonomy::get_post_location_link( $post_id ) );
		$this->markTestSkipped( 'Known bug, see #321' );
		Location_Taxonomy::set_location( $post_id, $terms['locality'] );
		$this->assertSame( Location_Taxonomy::get_location_link( $terms['locality'] ), Location_Taxonomy::get_post_location_link( $post_id ) );
	}

	/**
	 * The location list wraps wp_list_categories().
	 */
	public function test_list_locations() {
		$this->markTestSkipped( 'Known bug, see #321' );
		$this->hierarchy();
		$this->assertStringContainsString( 'Philadelphia', Location_Taxonomy::list_locations() );
	}

	/**
	 * A country name fills in the country code.
	 */
	public function test_normalize_address() {
		$address = Location_Taxonomy::normalize_address(
			array(
				'country-name' => 'United States',
				'locality'     => 'Philadelphia',
			)
		);
		$this->assertSame( 'US', $address['country-code'] );
		$this->assertSame( 'United States', $address['country-name'] );
		$this->assertSame( 'Philadelphia', $address['locality'] );
	}

	/**
	 * A region without a code keeps the region as its code.
	 */
	public function test_normalize_address_region_code() {
		$address = Location_Taxonomy::normalize_address( array( 'region' => 'Pennsylvania' ) );
		$this->assertSame( 'Pennsylvania', $address['region'] );
		$this->assertSame( 'Pennsylvania', $address['region-code'] );
	}

	/**
	 * A country code fills in the country name.
	 */
	public function test_normalize_address_country_name() {
		$address = Location_Taxonomy::normalize_address( array( 'country-code' => 'US' ) );
		$this->assertSame( 'US', $address['country-code'] );
		$this->assertSame( 'United States', $address['country-name'] );
	}

	/**
	 * An mf2 h-adr is flattened to its first values.
	 */
	public function test_normalize_address_mf2() {
		$address = Location_Taxonomy::normalize_address(
			array(
				'type'       => array( 'h-adr' ),
				'properties' => array(
					'locality'     => array( 'Philadelphia' ),
					'country-name' => array( 'United States' ),
				),
			)
		);
		$this->assertSame( 'Philadelphia', $address['locality'] );
		$this->assertSame( 'US', $address['country-code'] );
	}

	/**
	 * The archive title names the location type and the location.
	 */
	public function test_archive_title() {
		$terms = $this->hierarchy();
		$this->assertSame( 'Archives', Location_Taxonomy::archive_title( 'Archives' ) );
		$this->go_to( get_term_link( $terms['region'] ) );
		$this->assertSame( 'Region: Pennsylvania, 🇺🇸 United States of America', wp_strip_all_tags( Location_Taxonomy::archive_title( 'Archives' ) ) );
	}

	/**
	 * Location archives show visitors only posts with a public location, and never venues.
	 */
	public function test_filter_location_posts_visitor() {
		$terms      = $this->hierarchy();
		$public     = self::factory()->post->create();
		$private    = self::factory()->post->create();
		$venue      = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		$geo        = array(
			'latitude'  => 39.95,
			'longitude' => -75.16,
		);
		$public_geo = $geo + array( 'visibility' => 'public' );
		set_post_geodata( $public, '', $public_geo );
		set_post_geodata( $private, '', $geo + array( 'visibility' => 'protected' ) );
		set_post_geodata( $venue, '', $public_geo );
		foreach ( array( $public, $private, $venue ) as $post_id ) {
			wp_set_object_terms( $post_id, $terms['locality'], 'location' );
		}

		$this->go_to( get_term_link( $terms['locality'] ) );
		$this->assertSame( array( $public ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	/**
	 * Location archives show users who can read locations every post.
	 */
	public function test_filter_location_posts_privileged() {
		$terms   = $this->hierarchy();
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, 'visibility', 'protected' );
		wp_set_object_terms( $post_id, $terms['locality'], 'location' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->go_to( get_term_link( $terms['locality'] ) );
		$this->assertSame( array( $post_id ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	/**
	 * Location archives show visitors posts that rely on the site's public default visibility.
	 */
	public function test_filter_location_posts_default_visibility() {
		update_option( 'geo_public', 1 );
		$terms   = $this->hierarchy();
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'geo_latitude', 39.95 );
		update_post_meta( $post_id, 'geo_longitude', -75.16 );
		wp_set_object_terms( $post_id, $terms['locality'], 'location' );
		$this->assertSame( 'public', get_post_geodata( $post_id, 'visibility' ) );
		$this->go_to( get_term_link( $terms['locality'] ) );
		$this->assertSame( array( $post_id ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	/**
	 * With a public default, location archives still hide private and protected posts, and show posts with an empty visibility.
	 */
	public function test_filter_location_posts_default_visibility_explicit() {
		update_option( 'geo_public', 1 );
		$terms = $this->hierarchy();
		$posts = array();
		foreach ( array( 'private', 'protected', 'empty' ) as $visibility ) {
			$posts[ $visibility ] = self::factory()->post->create();
			update_post_meta( $posts[ $visibility ], 'geo_latitude', 39.95 );
			update_post_meta( $posts[ $visibility ], 'geo_longitude', -75.16 );
			if ( 'empty' === $visibility ) {
				update_post_meta( $posts[ $visibility ], 'geo_public', '' );
			} else {
				set_post_geodata( $posts[ $visibility ], 'visibility', $visibility );
			}
			wp_set_object_terms( $posts[ $visibility ], $terms['locality'], 'location' );
		}
		$this->assertSame( 'public', get_post_geodata( $posts['empty'], 'visibility' ) );
		$this->go_to( get_term_link( $terms['locality'] ) );
		$this->assertSame( array( $posts['empty'] ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	/**
	 * With a default that isn't public, location archives hide posts without a stored visibility.
	 */
	public function test_filter_location_posts_default_not_public() {
		update_option( 'geo_public', 2 );
		$terms   = $this->hierarchy();
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'geo_latitude', 39.95 );
		update_post_meta( $post_id, 'geo_longitude', -75.16 );
		wp_set_object_terms( $post_id, $terms['locality'], 'location' );
		$this->go_to( get_term_link( $terms['locality'] ) );
		$this->assertSame( array(), $GLOBALS['wp_query']->posts );
	}

	/**
	 * Location archives keep every post type when venues don't support locations.
	 */
	public function test_filter_location_posts_without_venue_support() {
		remove_post_type_support( 'venue', 'geo-location' );
		$expected = array_values( get_post_types_by_support( 'geo-location' ) );
		$terms    = $this->hierarchy();
		$this->go_to( get_term_link( $terms['locality'] ) );
		add_post_type_support( 'venue', 'geo-location' );
		$this->assertContains( 'post', $expected );
		$this->assertSame( $expected, $GLOBALS['wp_query']->get( 'post_type' ) );
	}

	/**
	 * The archive filter leaves other queries alone.
	 */
	public function test_filter_location_posts_other_queries() {
		$query = new WP_Query();
		$this->assertSame( $query, Location_Taxonomy::filter_location_posts( $query ) );
		$this->assertSame( '', $query->get( 'meta_query' ) );
	}

	/**
	 * The admin list has a Type column.
	 */
	public function test_admin_columns() {
		$terms = $this->hierarchy();
		$this->assertSame( 'Type', Location_Taxonomy::manage_column( array() )['location-type'] );
		$this->assertSame( 'Region', Location_Taxonomy::manage_column_content( '', 'location-type', $terms['region'] ) );
		$this->assertSame( 'other', Location_Taxonomy::manage_column_content( 'other', 'description', $terms['region'] ) );
	}

	/**
	 * The parent dropdown is limited to two levels for locations only.
	 */
	public function test_taxonomy_parent_dropdown_args() {
		$this->assertSame( array( 'depth' => 2 ), Location_Taxonomy::taxonomy_parent_dropdown_args( array(), 'location', 'edit' ) );
		$this->assertSame( array(), Location_Taxonomy::taxonomy_parent_dropdown_args( array(), 'category', 'edit' ) );
	}

	/**
	 * Tax queries are passed through to get_pages().
	 */
	public function test_get_pages_query_args() {
		$tax_query = array( array( 'taxonomy' => 'location' ) );
		$this->assertSame( array( 'tax_query' => $tax_query ), Location_Taxonomy::get_pages_query_args( array(), array( 'tax_query' => $tax_query ) ) );  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Not a query.
		$this->assertSame( array(), Location_Taxonomy::get_pages_query_args( array(), array() ) );
	}

	/**
	 * Saving the add form stores the code under the term's type.
	 */
	public function test_save_data_location_code() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$terms = $this->hierarchy();
		$_POST = array(
			'location_taxonomy_meta' => wp_create_nonce( 'create' ),
			'location-code'          => 'pa ',
		);
		Location_Taxonomy::save_data( $terms['region'] );
		$this->assertSame( 'pa', get_term_meta( $terms['region'], 'region', true ) );
	}

	/**
	 * Saving the edit form stores the posted field, or the slug in capitals when it is missing.
	 */
	public function test_save_data_edit_form() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$terms = $this->hierarchy();
		$_POST = array(
			'location_taxonomy_meta' => wp_create_nonce( 'edit' ),
			'country'                => 'CA',
		);
		Location_Taxonomy::save_data( $terms['country'] );
		$this->assertSame( 'CA', get_term_meta( $terms['country'], 'country', true ) );

		unset( $_POST['country'] );
		Location_Taxonomy::save_data( $terms['region'] );
		$this->assertSame( strtoupper( get_term( $terms['region'] )->slug ), get_term_meta( $terms['region'], 'region', true ) );
	}

	/**
	 * Saving requires the form, a valid nonce, and permission to edit the term.
	 */
	public function test_save_data_requires_nonce_and_capability() {
		$terms = $this->hierarchy();
		$_POST = array( 'location-code' => 'XX' );
		Location_Taxonomy::save_data( $terms['region'] );
		$this->assertSame( 'PA', get_term_meta( $terms['region'], 'region', true ), 'No form.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['location_taxonomy_meta'] = 'invalid';
		Location_Taxonomy::save_data( $terms['region'] );
		$this->assertSame( 'PA', get_term_meta( $terms['region'], 'region', true ), 'Invalid nonce.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$_POST['location_taxonomy_meta'] = wp_create_nonce( 'create' );
		Location_Taxonomy::save_data( $terms['region'] );
		$this->assertSame( 'PA', get_term_meta( $terms['region'], 'region', true ), 'No permission.' );
	}

	/**
	 * Saving a term that is too deep to have a type doesn't store anything.
	 */
	public function test_save_data_without_type() {
		if ( PHP_VERSION_ID < 80000 ) {
			$this->markTestSkipped( 'Known bug, see #328' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$terms    = $this->hierarchy();
		$too_deep = self::factory()->term->create(
			array(
				'taxonomy' => 'location',
				'parent'   => $terms['locality'],
			)
		);
		$_POST    = array( 'location_taxonomy_meta' => wp_create_nonce( 'edit' ) );
		Location_Taxonomy::save_data( $too_deep );
		$this->assertSame( array(), get_term_meta( $too_deep ) );
	}

	/**
	 * The add form has a required code field and a nonce.
	 */
	public function test_create_screen_fields() {
		ob_start();
		Location_Taxonomy::create_screen_fields( 'location' );
		Location_Taxonomy::pre_add_form();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="location-code" required', $output );
		$this->assertStringContainsString( 'name="location_taxonomy_meta"', $output );
		$this->assertStringContainsString( '3 levels of hierarchy', $output );
	}

	/**
	 * The edit form shows the field for the term's type.
	 */
	public function test_edit_screen_fields() {
		$terms = $this->hierarchy();
		$cases = array(
			'country'  => '/<option value="US"\s+selected/',
			'region'   => '/name="region"/',
			'locality' => '/name="locality" value="Philadelphia"/',
		);
		foreach ( $cases as $level => $expected ) {
			ob_start();
			Location_Taxonomy::edit_screen_fields( get_term( $terms[ $level ] ), 'location' );
			$this->assertMatchesRegularExpression( $expected, ob_get_clean(), $level );
		}

		$too_deep = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'location',
				'parent'   => $terms['locality'],
			)
		);
		ob_start();
		Location_Taxonomy::edit_screen_fields( $too_deep, 'location' );
		$this->assertStringContainsString( 'Cannot Identify Type', ob_get_clean() );
	}

	/**
	 * The post list filter dropdown is shown for post types with locations.
	 */
	public function test_location_dropdown() {
		$terms = $this->hierarchy();
		wp_set_object_terms( self::factory()->post->create(), $terms['locality'], 'location' );
		$_REQUEST['location'] = 'philadelphia';
		ob_start();
		Location_Taxonomy::location_dropdown( 'post', 'top' );
		$output = ob_get_clean();
		$this->assertMatchesRegularExpression( '/name=[\'"]location[\'"]/', $output );
		$this->assertMatchesRegularExpression( '/value="philadelphia"\s+selected/', $output );

		ob_start();
		Location_Taxonomy::location_dropdown( 'wp_block', 'top' );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * The select meta box shows a dropdown with the post's term selected, for users who can edit terms.
	 */
	public function test_taxonomy_select_meta_box() {
		$terms = $this->hierarchy();
		$post  = self::factory()->post->create_and_get();
		Location_Taxonomy::set_location( $post->ID, $terms['locality'] );
		$box = array( 'args' => array( 'taxonomy' => 'location' ) );

		ob_start();
		Location_Taxonomy::taxonomy_select_meta_box( $post, $box );
		$this->assertStringNotContainsString( '<select', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		Location_Taxonomy::taxonomy_select_meta_box( $post, $box );
		$output = ob_get_clean();
		$this->assertMatchesRegularExpression( '/name=[\'"]tax_input\[location\]\[\][\'"]/', $output );
		$this->assertMatchesRegularExpression( '/value="' . $terms['locality'] . '"\s+selected/', $output );
	}
}
