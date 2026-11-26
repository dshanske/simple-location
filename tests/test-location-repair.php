<?php
/**
 * Tests for the location repair tool (#870), with terms shaped like a live site's.
 *
 * @package Simple_Location
 */

/**
 * Location repair tests.
 */
class LocationRepairTest extends WP_UnitTestCase {

	/**
	 * An administrator, since merging locations checks capabilities.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_country', '' );
		update_option( 'sloc_elevation_provider', 'sloc_test' );
		update_option( 'sloc_geo_provider', 'offline' );
		update_option( 'sloc_geo_provider_fallbacks', array() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Creates a location term with its type meta.
	 *
	 * @param string $name   Name.
	 * @param string $type   'country', 'region' or 'locality', or '' for none.
	 * @param string $code   Value of the type meta.
	 * @param int    $parent Parent term.
	 * @return int Term ID.
	 */
	protected function term( $name, $type, $code, $parent = 0 ) {
		$term = wp_insert_term(
			$name,
			'location',
			array(
				'parent' => $parent,
				'slug'   => sanitize_title( $name . '-' . $code . '-' . $parent ),
			)
		);
		if ( '' !== $type ) {
			add_term_meta( $term['term_id'], $type, $code );
		}
		return $term['term_id'];
	}

	/**
	 * Creates a post with coordinates, in a location.
	 *
	 * @param int|null $term       Location term, or null for none.
	 * @param float    $latitude   Latitude.
	 * @param float    $longitude  Longitude.
	 * @param string   $visibility Location visibility.
	 * @return int Post ID.
	 */
	protected function post( $term, $latitude = 40.0, $longitude = -75.0, $visibility = 'public' ) {
		$post = self::factory()->post->create();
		update_post_meta( $post, 'geo_latitude', $latitude );
		update_post_meta( $post, 'geo_longitude', $longitude );
		update_post_meta( $post, 'geo_public', 'public' === $visibility ? 1 : ( 'private' === $visibility ? 0 : 2 ) );
		if ( $term ) {
			Location_Taxonomy::set_location( $post, $term );
		}
		return $post;
	}

	/**
	 * Returns the term IDs a step's changes are about.
	 *
	 * @param array $changes Changes.
	 * @return int[] IDs.
	 */
	protected function ids( $changes ) {
		return array_map( 'intval', wp_list_pluck( $changes, 'id' ) );
	}

	/**
	 * Region codes stored as numbers or names get the code; names are kept for matching; a preview changes nothing.
	 */
	public function test_region_codes() {
		$us      = $this->term( 'United States', 'country', 'US' );
		$alabama = $this->term( 'Alabama', 'region', '264', $us );
		$jersey  = $this->term( 'New Jersey', 'region', 'New Jersey', $us );
		$texas   = $this->term( 'Texas', 'region', 'TX', $us );
		$de      = $this->term( 'Germany', 'country', 'DE' );
		$bavaria = $this->term( 'Bavaria', 'region', 'Bavaria', $de );

		$preview = Sloc_Location_Repair::run( array( 'region_codes' ), false )['region_codes'];
		$this->assertEqualSets( array( $alabama, $jersey, $bavaria ), $this->ids( $preview ) );
		$this->assertSame( '264', get_term_meta( $alabama, 'region', true ), 'A preview changes nothing.' );

		Sloc_Location_Repair::run( array( 'region_codes' ), true );
		$this->assertSame( array( 'AL' ), get_term_meta( $alabama, 'region' ), 'The number isn\'t kept.' );
		$this->assertSame( array( 'NJ', 'New Jersey' ), get_term_meta( $jersey, 'region' ), 'The code first, then the name.' );
		$this->assertSame( array( 'BY', 'Bavaria' ), get_term_meta( $bavaria, 'region' ) );
		$this->assertSame( array( 'TX' ), get_term_meta( $texas, 'region' ) );
		$this->assertEmpty( Sloc_Location_Repair::run( array( 'region_codes' ), false )['region_codes'] );
	}

	/**
	 * Regions with the same code merge into the one with more posts; a preview sees codes the same preview fixes.
	 */
	public function test_region_duplicates() {
		$us     = $this->term( 'United States', 'country', 'US' );
		$jersey = $this->term( 'New Jersey', 'region', 'NJ', $us );
		$named  = $this->term( 'New Jersey', 'region', 'New Jersey', $us );
		$city   = $this->term( 'Hoboken', 'locality', 'Hoboken', $named );
		$this->post( $jersey );
		$this->post( $jersey );
		$moved = $this->post( $city );

		$preview = Sloc_Location_Repair::run( array( 'region_codes', 'region_duplicates' ), false );
		$this->assertSame( array( $named ), $this->ids( $preview['region_duplicates'] ) );

		Sloc_Location_Repair::run( array( 'region_codes', 'region_duplicates' ), true );
		$this->assertNull( term_exists( $named, 'location' ) );
		$this->assertSame( $jersey, get_term( $city )->parent, 'Its towns move to the region kept.' );
		$this->assertContains( $city, wp_list_pluck( get_the_terms( $moved, 'location' ), 'term_id' ) );
	}

	/**
	 * Towns without a type are marked; towns straight under a country merge into the same town under a region, move
	 * under their region from the place data, or stay where the place data's regions aren't the country's.
	 */
	public function test_types_and_orphans() {
		$gb      = $this->term( 'United Kingdom', 'country', 'GB' );
		$sussex  = $this->term( 'West Sussex', 'region', 'WSX', $gb );
		$crawley = $this->term( 'Crawley', 'locality', 'Crawley', $sussex );
		$orphan  = $this->term( 'Crawley', 'locality', 'Crawley', $gb );
		$post    = $this->post( $orphan, 51.11, -0.19 );

		$us      = $this->term( 'United States', 'country', 'US' );
		$florida = $this->term( 'Florida', 'region', 'FL', $us );
		$typeless = $this->term( 'Fort Lauderdale', '', '', $florida );
		$miami   = $this->term( 'Miami', 'locality', 'Miami', $us );
		$this->post( $miami, 25.7617, -80.1918 );

		$ie     = $this->term( 'Ireland', 'country', 'IE' );
		$dublin = $this->term( 'Dublin', 'locality', 'Dublin', $ie );
		$this->post( $dublin, 53.3498, -6.2603 );

		$report = Sloc_Location_Repair::run( array( 'types', 'orphan_localities' ), true );
		$this->assertSame( array( $typeless ), $this->ids( $report['types'] ) );
		$this->assertSame( 'Fort Lauderdale', get_term_meta( $typeless, 'locality', true ) );

		$this->assertNull( term_exists( $orphan, 'location' ) );
		$this->assertSame( array( $crawley ), wp_list_pluck( get_the_terms( $post, 'location' ), 'term_id' ) );
		$this->assertSame( $florida, get_term( $miami )->parent, 'Moved under the region from the place data.' );
		$this->assertSame( $ie, get_term( $dublin )->parent, 'Ireland\'s regions are counties, which the place data doesn\'t have.' );
		$this->assertCount( 3, $report['orphan_localities'] );
	}

	/**
	 * Towns left with the same name under one region, such as after two regions merge, merge too.
	 */
	public function test_locality_duplicates() {
		$il       = $this->term( 'Israel', 'country', 'IL' );
		$district = $this->term( 'Jerusalem District', 'region', 'JM', $il );
		$named    = $this->term( 'Jerusalem', 'region', 'Jerusalem District', $il );
		$one      = $this->term( 'Jerusalem', 'locality', 'Jerusalem', $district );
		$two      = $this->term( 'Jerusalem', 'locality', 'ירושלים', $named );
		$this->post( $one );
		$this->post( $one );
		$moved = $this->post( $two );

		$report = Sloc_Location_Repair::run( array( 'region_codes', 'region_duplicates', 'locality_duplicates' ), true );
		$this->assertSame( array( $two ), $this->ids( $report['locality_duplicates'] ) );
		$this->assertSame( array( $one ), wp_list_pluck( get_the_terms( $moved, 'location' ), 'term_id' ) );
		$this->assertContains( 'ירושלים', get_term_meta( $one, 'locality' ), 'The other name stays matchable.' );
	}

	/**
	 * Top-level terms that aren't countries and have nothing in them are removed; empty countries stay.
	 */
	public function test_broken() {
		$broken = $this->term( 'Old Orchard Beach', 'country', 'Old Orchard Beach' );
		$empty  = $this->term( 'Poland', 'country', 'PL' );
		$used   = $this->term( 'Nowhere', 'country', 'ZZ' );
		$this->post( $used );

		$report = Sloc_Location_Repair::run( array( 'broken' ), true );
		$this->assertSame( array( $broken ), $this->ids( $report['broken'] ) );
		$this->assertNull( term_exists( $broken, 'location' ) );
		$this->assertNotNull( term_exists( $empty, 'location' ) );
		$this->assertNotNull( term_exists( $used, 'location' ) );
	}

	/**
	 * Posts with public coordinates but no location get one; private and protected ones are left out.
	 */
	public function test_untagged() {
		$public    = $this->post( null, 39.9524, -75.1636 );
		$private   = $this->post( null, 39.9524, -75.1636, 'private' );
		$protected = $this->post( null, 39.9524, -75.1636, 'protected' );

		$preview = Sloc_Location_Repair::run( array( 'untagged' ), false )['untagged'];
		$this->assertSame( array( $public, 0 ), $this->ids( $preview ) );
		$this->assertStringContainsString( '2 posts', end( $preview )['change'] );
		$this->assertFalse( get_the_terms( $public, 'location' ) );

		Sloc_Location_Repair::run( array( 'untagged' ), true, array( 'lookup' => 'offline' ) );
		$term = get_the_terms( $public, 'location' )[0];
		$this->assertSame( 'Philadelphia', $term->name );
		$this->assertSame( 'PA', Location_Taxonomy::get_location_data( $term->term_id )['region']['code'] );
		$this->assertFalse( get_the_terms( $private, 'location' ) );
		$this->assertFalse( get_the_terms( $protected, 'location' ) );

		// The limit.
		$this->post( null, 39.9524, -75.1636 );
		$this->post( null, 39.9524, -75.1636 );
		$this->assertCount( 1, Sloc_Location_Repair::run( array( 'untagged' ), false, array( 'limit' => 1 ) )['untagged'] );
	}

	/**
	 * Posts in regions without a code are looked up again, and the emptied region removed.
	 */
	public function test_relookup() {
		$ie      = $this->term( 'Ireland', 'country', 'IE' );
		$kenmare = $this->term( 'Kenmare Municipal District', 'region', 'Kenmare Municipal District', $ie );
		$town    = $this->term( 'Kenmare', 'locality', 'Kenmare', $kenmare );
		$post    = $this->post( $town, 51.8801, -9.5835 );

		$preview = Sloc_Location_Repair::run( array( 'relookup' ), false )['relookup'];
		$this->assertSame( array( $kenmare ), $this->ids( $preview ) );

		Sloc_Location_Repair::run( array( 'relookup' ), true );
		$terms = get_the_terms( $post, 'location' );
		$this->assertNotContains( $town, wp_list_pluck( $terms, 'term_id' ) );
		$this->assertNull( term_exists( $kenmare, 'location' ) );
		$this->assertNull( term_exists( $town, 'location' ) );
	}

	/**
	 * Countries and regions get their names in the site language, keeping the old ones.
	 */
	public function test_names() {
		$il     = $this->term( 'ישראל', 'country', 'IL' );
		$region = $this->term( 'מחוז הדרום', 'region', 'D', $il );
		$us     = $this->term( 'United States', 'country', 'US' );

		$report = Sloc_Location_Repair::run( array( 'names' ), true );
		$this->assertEqualSets( array( $il, $region ), $this->ids( $report['names'] ) );
		$this->assertSame( 'Israel', get_term( $il )->name );
		$this->assertSame( 'ישראל', get_term_meta( $il, 'local_name', true ) );
		$this->assertSame( 'Southern District', get_term( $region )->name );
		$this->assertSame( 'United States', get_term( $us )->name );
	}

	/**
	 * Stored addresses are counted, never changed.
	 */
	public function test_addresses() {
		$post = $this->post( null );
		update_post_meta( $post, 'geo_address', 'London Borough of Camden, England, United Kingdom' );
		$other = $this->post( null );
		update_post_meta( $other, 'geo_address', '10 Downing Street, London' );

		$report = Sloc_Location_Repair::run( array( 'addresses' ), true );
		$this->assertStringStartsWith( '1 stored address', $report['addresses'][0]['change'] );
		$this->assertSame( 'London Borough of Camden, England, United Kingdom', get_post_meta( $post, 'geo_address', true ) );
	}

	/**
	 * The choices from the form or command are cleaned, with the default steps without any.
	 */
	public function test_choices() {
		$choices = Sloc_Location_Repair_Tool::choices(
			array(
				'steps'  => array( 'types', 'nope', 'NAMES' ),
				'lookup' => 'chain',
				'limit'  => '9999',
			)
		);
		$this->assertSame( array( 'types', 'names' ), $choices['steps'] );
		$this->assertSame( 'chain', $choices['lookup'] );
		$this->assertSame( 500, $choices['limit'] );

		$defaults = Sloc_Location_Repair_Tool::choices( array( 'lookup' => 'other' ) );
		$this->assertNotContains( 'relookup', $defaults['steps'] );
		$this->assertNotContains( 'names', $defaults['steps'] );
		$this->assertContains( 'untagged', $defaults['steps'] );
		$this->assertSame( 'offline', $defaults['lookup'] );
		$this->assertSame( 50, $defaults['limit'] );
		$this->assertSame( 1, Sloc_Location_Repair_Tool::choices( array( 'limit' => 0 ) )['limit'] );
	}

	/**
	 * The page needs an administrator, and previews with a valid nonce.
	 */
	public function test_page() {
		$us = $this->term( 'United States', 'country', 'US' );
		$this->term( 'Alabama', 'region', '264', $us );

		ob_start();
		Sloc_Location_Repair_Tool::page();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'sloc_repair_steps[]', $html );
		$this->assertStringNotContainsString( '<h2>Preview</h2>', $html );

		$_POST = array(
			'sloc_repair_nonce'   => wp_create_nonce( 'sloc_repair' ),
			'sloc_repair_steps'   => array( 'region_codes' ),
			'sloc_repair_preview' => 'Preview',
		);
		ob_start();
		Sloc_Location_Repair_Tool::page();
		$html = ob_get_clean();
		$this->assertStringContainsString( '<h2>Preview</h2>', $html );
		$this->assertStringContainsString( 'Code 264 becomes AL', $html );
		$this->assertSame( '264', get_term_meta( get_terms( array( 'taxonomy' => 'location', 'name' => 'Alabama', 'hide_empty' => false, 'fields' => 'ids' ) )[0], 'region', true ) );

		$_POST['sloc_repair_nonce'] = 'bad';
		ob_start();
		Sloc_Location_Repair_Tool::page();
		$this->assertStringNotContainsString( '<h2>Preview</h2>', ob_get_clean() );
		$_POST = array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		Sloc_Location_Repair_Tool::page();
		$this->assertSame( '', ob_get_clean() );
	}
}
