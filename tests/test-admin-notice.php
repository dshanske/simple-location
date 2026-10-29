<?php
/**
 * Tests the wp_admin_notice() polyfill and the notices that use it (#774).
 *
 * @package Simple_Location
 */

class AdminNoticeTest extends WP_UnitTestCase {

	public function test_markup() {
		$this->assertSame( '<div class="notice"><p>Hello</p></div>', wp_get_admin_notice( 'Hello' ) );
		$this->assertSame(
			'<div id="message" class="notice notice-success is-dismissible inline extra" data-x="1" hidden><p>Done</p></div>',
			wp_get_admin_notice(
				'Done',
				array(
					'type'               => 'success',
					'dismissible'        => true,
					'id'                 => ' message ',
					'additional_classes' => array( 'inline', 'extra' ),
					'attributes'         => array(
						'data-x' => '1',
						'hidden' => true,
						'off'    => false,
					),
				)
			)
		);
		$this->assertSame( '<div class="notice notice-error"><ul><li>A</li></ul></div>', wp_get_admin_notice( '<ul><li>A</li></ul>', array( 'type' => 'error', 'paragraph_wrap' => false ) ) );
	}

	public function test_filters_and_output() {
		$fired = array();
		add_action(
			'wp_admin_notice',
			function ( $message ) use ( &$fired ) {
				$fired[] = $message;
			}
		);
		add_filter(
			'wp_admin_notice_args',
			function ( $args ) {
				$args['type'] = 'warning';
				return $args;
			}
		);
		add_filter( 'wp_admin_notice_markup', fn( $markup ) => $markup . '<!-- filtered -->' );
		ob_start();
		wp_admin_notice( 'Careful <script>alert(1)</script>' );
		$html = ob_get_clean();
		$this->assertStringStartsWith( '<div class="notice notice-warning"><p>Careful ', $html );
		$this->assertStringNotContainsString( '<script>', $html, 'Passed through wp_kses_post().' );
		$this->assertSame( array( 'Careful <script>alert(1)</script>' ), $fired );
	}

	public function test_type_with_spaces() {
		$this->setExpectedIncorrectUsage( 'wp_get_admin_notice' );
		$this->assertStringContainsString( 'notice-bad type', wp_get_admin_notice( 'x', array( 'type' => 'bad type' ) ) );
	}

	public function test_bulk_notice() {
		$_REQUEST['bulk_lookup_location_count'] = '3';
		ob_start();
		Geo_Base::bulk_action_admin_notices();
		$html = ob_get_clean();
		unset( $_REQUEST['bulk_lookup_location_count'] );
		$this->assertSame( '<div id="message" class="notice notice-success is-dismissible"><p>Updated 3 posts.</p></div>', $html );
	}

	public function test_map_cache_notice() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['sloc_maps_cleared'] = '2';
		ob_start();
		Sloc_Map_Cache::cleared_notice();
		$html = ob_get_clean();
		unset( $_GET['sloc_maps_cleared'] );
		$this->assertSame( '<div class="notice notice-success is-dismissible"><p>Deleted 2 stored map images.</p></div>', $html );
	}
}
