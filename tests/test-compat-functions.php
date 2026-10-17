<?php
/**
 * Tests for the polyfills in includes/compat-functions.php.
 *
 * On a WordPress or ClassicPress version that has the function, these test core's version, which
 * the polyfill must match.
 *
 * @package Simple_Location
 */

/**
 * Polyfill tests.
 */
class CompatFunctionsTest extends WP_UnitTestCase {

	/**
	 * Messages caught by the error handler.
	 *
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Catches user-level errors instead of failing the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->errors = array();
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			function ( $level, $message ) {
				$this->errors[] = array( $level, $message );
				return true;
			},
			E_USER_NOTICE | E_USER_WARNING | E_USER_DEPRECATED
		);
	}

	/**
	 * Restores the error handler.
	 */
	public function tear_down() {
		restore_error_handler();
		parent::tear_down();
	}

	/**
	 * The wp_trigger_error() function names the function, strips disallowed HTML, keeps the level, and fires wp_trigger_error_run.
	 */
	public function test_wp_trigger_error() {
		$this->assertTrue( WP_DEBUG, 'The test suite runs with WP_DEBUG on.' );
		$action = new MockAction();
		add_action( 'wp_trigger_error_run', array( $action, 'action' ), 10, 3 );

		wp_trigger_error( 'my_function', 'A <code>value</code> <script>x</script>is wrong.' );
		wp_trigger_error( '', 'No function.', E_USER_WARNING );

		$this->assertSame(
			array(
				array( E_USER_NOTICE, 'my_function(): A <code>value</code> xis wrong.' ),
				array( E_USER_WARNING, 'No function.' ),
			),
			$this->errors
		);
		$this->assertSame( 2, $action->get_call_count() );
		$this->assertSame( array( 'my_function', 'A <code>value</code> <script>x</script>is wrong.', E_USER_NOTICE ), $action->get_args()[0] );
	}

	/**
	 * The wp_trigger_error() function throws for an E_USER_ERROR instead of triggering an error.
	 */
	public function test_wp_trigger_error_user_error() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'my_function(): Fatal.' );
		wp_trigger_error( 'my_function', 'Fatal.', E_USER_ERROR );
	}
}
