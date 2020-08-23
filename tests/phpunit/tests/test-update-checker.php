<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Update Checker tests.
 *
 * @since 0.2.0
 *
 * @package updates-api-inspector
 */

use SHC\Updates_API_Inspector\Update_Checker;

/**
 * Test various aspects of the Update_Checker class.
 *
 * @group update-checker
 *
 * @since 0.2.0
 *
 * @todo expand these tests...
 */
class Test_Update_Checker extends Updates_API_Inspector_UnitTestCase {
	/**
	 * Update_Checker instance.
	 *
	 * @since 0.2.0
	 *
	 * @var Update_Checker
	 */
	protected $checker;

	/**
	 * Reset the Update_Checker singleton to an initial state between tests.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$this->checker = Update_Checker::get_instance();
		// Since the update checker is a Singleton, we need to "reset" it before running tests.
		$this->checker->do_check( '' );
	}

	/**
	 * Test that the basic checking works.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_do_check() {
		$actual = $this->checker->do_check( 'core' );

		$this->assertNotEmpty( $actual['request_url'] );
		$this->assertNotEmpty( $actual['request'] );
	}

	/**
	 * Test that pre_http_request return value is properly captured.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_do_check_pre_http_request_error() {
		add_filter( 'pre_http_request', array( $this, 'error_response' ) );

		foreach ( array( 'core', 'plugins', 'themes' ) as $type ) {
			$actual = $this->checker->do_check( $type );

			$this->assertNotEmpty( $actual['request_url'] );
			$this->assertNotEmpty( $actual['request'] );
			$this->assertWPError( $actual['request_error'] );
			$this->assertEmpty( $actual['api_response'] );
			$this->assertEmpty( $actual['transient_as_set'] );
			$this->assertEmpty( $actual['transient_as_read'] );
		}
	}

	/**
	 * Test that http_response return value is properly captured.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_do_check_http_response_error() {
		add_filter( 'http_response', array( $this, 'error_response' ) );

		foreach ( array( 'core', 'plugins', 'themes' ) as $type ) {
			$actual = $this->checker->do_check( $type );

			$this->assertNotEmpty( $actual['request_url'] );
			$this->assertNotEmpty( $actual['request'] );
			$this->assertWPError( $actual['request_error'] );
			$this->assertEmpty( $actual['api_response'] );
			$this->assertEmpty( $actual['transient_as_set'] );
			$this->assertEmpty( $actual['transient_as_read'] );
		}
	}

	/**
	 * Callback for various filters that returns a WP_Error instance.
	 *
	 * @since 0.2.0
	 *
	 * @return WP_Error
	 */
	public function error_response() {
		return new WP_Error( 'mock_failure', 'This is a test' );
	}
}
